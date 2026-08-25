<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

require_once __DIR__ . DIRECTORY_SEPARATOR . 'WhisperServerSupervisor.php';

final class WhisperClient
{
    private string $root;
    private string $modelPath;
    private string $serverBaseUrl;
    private string $inferencePath;
    private string $ffmpeg;
    private int $threads;

    public function __construct()
    {
        $this->root = neus_whisper_root();
        $cppDir = neus_whisper_env('WHISPER_CPP_DIR', 'whisper.cpp') ?? 'whisper.cpp';
        $modelRel = neus_whisper_env('WHISPER_MODEL', 'models/ggml-base.en.bin') ?? 'models/ggml-base.en.bin';

        $this->modelPath = neus_whisper_path($modelRel);
        $host = neus_whisper_env('WHISPER_SERVER_HOST', '127.0.0.1') ?? '127.0.0.1';
        $port = neus_whisper_env('WHISPER_SERVER_PORT', '8081') ?? '8081';
        $this->serverBaseUrl = 'http://' . $host . ':' . $port;
        $this->inferencePath = neus_whisper_env('WHISPER_SERVER_INFERENCE_PATH', '/inference') ?? '/inference';
        $this->ffmpeg = neus_whisper_env('FFMPEG', 'ffmpeg') ?? 'ffmpeg';
        $this->threads = max(1, (int) (neus_whisper_env('WHISPER_THREADS', '4') ?? '4'));
    }

    public function status(): array
    {
        return [
            'model_path' => $this->modelPath,
            'model_exists' => is_file($this->modelPath),
            'whisper_cli' => $this->resolveWhisperCli(),
            'whisper_server_url' => $this->serverBaseUrl,
            'whisper_server_up' => $this->isServerUp(),
            'ffmpeg' => $this->ffmpeg,
            'auto_start' => WhisperServerSupervisor::isAutoStartEnabled(),
        ];
    }

    public function isServerUp(): bool
    {
        return $this->pingWhisperServer();
    }

    /**
     * @param array<string, scalar|null> $options
     * @return array{ok: bool, text?: string, segments?: mixed, source?: string, error?: string, raw?: mixed, retryable?: bool}
     */
    public function transcribe(string $inputPath, array $options = []): array
    {
        if (!is_file($inputPath)) {
            return ['ok' => false, 'error' => 'Audio file not found.'];
        }

        if (!$this->isServerUp()) {
            WhisperServerSupervisor::ensureRunning($this);
        }

        // Preferred path: proxy to whisper-server with retry-on-busy.
        // The whisper-server is single-threaded — when a request arrives while
        // it's still processing the previous one, the connection is refused.
        // We retry a few times with a short backoff instead of failing 502.
        $serverError = null;
        $lastRetryable = null;
        $maxAttempts = (int) (neus_whisper_env('NEUS_WHISPER_RETRY_ATTEMPTS', '3') ?? '3');
        $retryDelay  = (int) (neus_whisper_env('NEUS_WHISPER_RETRY_DELAY_MS', '500') ?? '500');

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            if (!$this->isServerUp() && $attempt > 1) {
                // Only skip if the server is genuinely down, not just busy.
                // On the first attempt we try anyway — the ping itself can fail
                // when the server is mid-processing.
                usleep($retryDelay * 1000);
                continue;
            }

            $viaServer = $this->transcribeViaServer($inputPath, $options);
            if ($viaServer['ok'] === true) {
                return $viaServer;
            }

            $serverError = $viaServer['error'] ?? 'whisper-server request failed';
            $lastRetryable = $viaServer['retryable'] ?? null;

            // Only retry transient failures (connection issues, timeouts).
            // If the server responded with a non-transient error (e.g. "No
            // transcription text" — the audio had no speech), don't retry.
            $retryable = $viaServer['retryable'] ?? null;
            if ($retryable === false) {
                break; // definitive failure — don't retry
            }

            $isTransient = (
                str_contains($serverError, 'Connection refused') ||
                str_contains($serverError, 'timed out') ||
                str_contains($serverError, 'Empty reply') ||
                str_contains($serverError, 'Connection reset') ||
                str_contains($serverError, 'request failed')
            );

            if ($isTransient && $attempt < $maxAttempts) {
                usleep($retryDelay * 1000 * $attempt); // linear backoff
                continue;
            }
            break;
        }

        // Fallback: local whisper-cli, which requires the model file on disk.
        if (!is_file($this->modelPath)) {
            if ($serverError !== null) {
                // Carry the retryable flag from the server response.
                // "No transcription text" (no speech) is NOT retryable; connection
                // errors ARE retryable.
                $retryable = $lastRetryable ?? true;
                return ['ok' => false, 'error' => 'whisper-server error: ' . $serverError, 'retryable' => $retryable];
            }
            return [
                'ok' => false,
                'error' => 'whisper-server is not reachable yet (on first boot the model may still be '
                    . 'downloading). Check ' . $this->serverBaseUrl . ' and try again shortly.',
                'retryable' => true,
            ];
        }

        return $this->transcribeViaCli($inputPath, $options);
    }

    /**
     * @param array<string, scalar|null> $options
     * @return array{ok: bool, text?: string, segments?: mixed, source?: string, error?: string, raw?: mixed}
     */
    private function transcribeViaServer(string $inputPath, array $options): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'curl extension required for whisper-server proxy.'];
        }

        $mime = mime_content_type($inputPath) ?: 'application/octet-stream';
        $curlFile = curl_file_create($inputPath, $mime, basename($inputPath));
        $post = [
            'file' => $curlFile,
            'response_format' => (string) ($options['response_format'] ?? 'json'),
            'temperature' => (string) ($options['temperature'] ?? '0.0'),
        ];
        if (!empty($options['language'])) {
            $post['language'] = (string) $options['language'];
        }
        if (!empty($options['prompt'])) {
            $post['prompt'] = (string) $options['prompt'];
        }

        $url = rtrim($this->serverBaseUrl, '/') . $this->inferencePath;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 600,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return ['ok' => false, 'error' => 'whisper-server request failed: ' . $err];
        }
        if ($status < 200 || $status >= 300) {
            return ['ok' => false, 'error' => 'whisper-server HTTP ' . $status . ': ' . substr((string) $body, 0, 500)];
        }

        return $this->normalizeWhisperPayload((string) $body, 'whisper-server');
    }

    /**
     * @param array<string, scalar|null> $options
     * @return array{ok: bool, text?: string, segments?: mixed, source?: string, error?: string, raw?: mixed}
     */
    private function transcribeViaCli(string $inputPath, array $options): array
    {
        $cli = $this->resolveWhisperCli();
        if ($cli === null) {
            return [
                'ok' => false,
                'error' => 'whisper-server is not running and whisper-cli was not found. Start scripts/start-server.ps1 or build whisper.cpp.',
            ];
        }

        $workDir = neus_whisper_path('runtime' . DIRECTORY_SEPARATOR . 'jobs');
        neus_whisper_ensure_dir($workDir);
        $jobId = bin2hex(random_bytes(8));
        $wavPath = $workDir . DIRECTORY_SEPARATOR . $jobId . '.wav';
        $outPrefix = $workDir . DIRECTORY_SEPARATOR . $jobId . '_out';

        try {
            if (!$this->ensureWav16kMono($inputPath, $wavPath)) {
                return ['ok' => false, 'error' => 'Could not convert audio to 16 kHz mono WAV. Install ffmpeg or upload 16-bit WAV.'];
            }

            $args = [
                $cli,
                '-m', $this->modelPath,
                '-f', $wavPath,
                '-oj',
                '-of', $outPrefix,
                '-t', (string) $this->threads,
                '-nt',
            ];
            if (!empty($options['language'])) {
                $args[] = '-l';
                $args[] = (string) $options['language'];
            }
            if (!empty($options['prompt'])) {
                $args[] = '--prompt';
                $args[] = (string) $options['prompt'];
            }

            [$code, $output] = $this->runProcess($args);
            if ($code !== 0) {
                return ['ok' => false, 'error' => 'whisper-cli failed: ' . trim($output)];
            }

            $jsonPath = $outPrefix . '.json';
            if (!is_file($jsonPath)) {
                return ['ok' => false, 'error' => 'whisper-cli did not produce JSON output.'];
            }

            return $this->normalizeWhisperPayload((string) file_get_contents($jsonPath), 'whisper-cli');
        } finally {
            foreach (glob($workDir . DIRECTORY_SEPARATOR . $jobId . '*') ?: [] as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * @return array{ok: bool, text?: string, segments?: mixed, source?: string, error?: string, raw?: mixed}
     */
    private function normalizeWhisperPayload(string $body, string $source): array
    {
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            $text = trim($body);
            if ($text === '') {
                return ['ok' => false, 'error' => 'Empty transcription response.'];
            }
            return ['ok' => true, 'text' => $text, 'source' => $source];
        }

        $text = trim((string) ($decoded['text'] ?? $decoded['result'] ?? ''));
        if ($text === '' && isset($decoded['transcription'])) {
            $text = trim((string) $decoded['transcription']);
        }
        if ($text === '' && isset($decoded['segments']) && is_array($decoded['segments'])) {
            $parts = [];
            foreach ($decoded['segments'] as $segment) {
                if (is_array($segment) && isset($segment['text'])) {
                    $parts[] = trim((string) $segment['text']);
                }
            }
            $text = trim(implode(' ', $parts));
        }

        if ($text === '') {
            return ['ok' => false, 'error' => 'No transcription text in response.', 'raw' => $decoded, 'retryable' => false];
        }

        return [
            'ok' => true,
            'text' => $text,
            'segments' => $decoded['segments'] ?? null,
            'source' => $source,
            'raw' => $decoded,
        ];
    }

    private function pingWhisperServer(): bool
    {
        if (!function_exists('curl_init')) {
            return false;
        }
        $ch = curl_init(rtrim($this->serverBaseUrl, '/') . '/');
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 3,
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $status >= 200 && $status < 500;
    }

    private function resolveWhisperCli(): ?string
    {
        $candidates = [
            neus_whisper_path('whisper.cpp' . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'Release' . DIRECTORY_SEPARATOR . 'whisper-cli.exe'),
            neus_whisper_path('whisper.cpp' . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'whisper-cli.exe'),
            neus_whisper_path('whisper.cpp' . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'Release' . DIRECTORY_SEPARATOR . 'whisper-cli'),
            neus_whisper_path('whisper.cpp' . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'whisper-cli'),
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    public function resolveWhisperServerBin(): ?string
    {
        $candidates = [
            neus_whisper_path('whisper.cpp' . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'Release' . DIRECTORY_SEPARATOR . 'whisper-server.exe'),
            neus_whisper_path('whisper.cpp' . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'whisper-server.exe'),
            neus_whisper_path('whisper.cpp' . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'Release' . DIRECTORY_SEPARATOR . 'whisper-server'),
            neus_whisper_path('whisper.cpp' . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'whisper-server'),
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    private function ensureWav16kMono(string $inputPath, string $outputPath): bool
    {
        $ext = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));
        if ($ext === 'wav') {
            return copy($inputPath, $outputPath);
        }

        $args = [
            $this->ffmpeg,
            '-y',
            '-i', $inputPath,
            '-ar', '16000',
            '-ac', '1',
            '-c:a', 'pcm_s16le',
            $outputPath,
        ];
        [$code] = $this->runProcess($args);
        return $code === 0 && is_file($outputPath);
    }

    /**
     * @param list<string> $args
     * @return array{0: int, 1: string}
     */
    private function runProcess(array $args): array
    {
        $command = '';
        foreach ($args as $arg) {
            $command .= ($command === '' ? '' : ' ') . escapeshellarg($arg);
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptors, $pipes, $this->root);
        if (!is_resource($process)) {
            return [1, 'proc_open failed'];
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        return [$code, trim($stdout . "\n" . $stderr)];
    }
}
