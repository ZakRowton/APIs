<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

/**
 * Starts whisper-server when NEUS_WHISPER_AUTO_START=1 and host is local.
 */
final class WhisperServerSupervisor
{
    public static function isAutoStartEnabled(): bool
    {
        $flag = strtolower(trim(neus_whisper_env('NEUS_WHISPER_AUTO_START', '1') ?? '1'));
        return in_array($flag, ['1', 'true', 'yes', 'on'], true);
    }

    public static function isLocalHost(): bool
    {
        $host = trim(neus_whisper_env('WHISPER_SERVER_HOST', '127.0.0.1') ?? '127.0.0.1');
        return in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    }

    /**
     * @return array{started: bool, message: string}
     */
    public static function ensureRunning(WhisperClient $client): array
    {
        if ($client->isServerUp()) {
            return ['started' => false, 'message' => 'already running'];
        }
        if (!self::isAutoStartEnabled()) {
            return ['started' => false, 'message' => 'auto-start disabled'];
        }
        if (!self::isLocalHost()) {
            return ['started' => false, 'message' => 'remote whisper host (use Docker/systemd on Hostinger VPS)'];
        }

        $lockPath = neus_whisper_path('runtime' . DIRECTORY_SEPARATOR . 'whisper-server-start.lock');
        neus_whisper_ensure_dir(dirname($lockPath));
        $lock = fopen($lockPath, 'c+');
        if ($lock === false) {
            return ['started' => false, 'message' => 'could not acquire start lock'];
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            flock($lock, LOCK_SH);
            self::waitForServer($client, 45);
            flock($lock, LOCK_UN);
            fclose($lock);
            return ['started' => true, 'message' => 'waited for peer start'];
        }

        try {
            if ($client->isServerUp()) {
                return ['started' => false, 'message' => 'already running'];
            }
            $result = self::launchStartScript();
            if ($result['ok'] !== true) {
                return ['started' => false, 'message' => $result['error'] ?? 'start script failed'];
            }
            self::waitForServer($client, 60);
            return [
                'started' => true,
                'message' => $client->isServerUp() ? 'started' : 'start script ran but server not ready yet',
            ];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function waitForServer(WhisperClient $client, int $maxSeconds): void
    {
        $deadline = time() + max(1, $maxSeconds);
        while (time() < $deadline) {
            if ($client->isServerUp()) {
                return;
            }
            usleep(500_000);
        }
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    private static function launchStartScript(): array
    {
        $root = neus_whisper_root();
        if (PHP_OS_FAMILY === 'Windows') {
            $script = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'start-server.ps1';
            if (!is_file($script)) {
                return ['ok' => false, 'error' => 'Missing scripts/start-server.ps1'];
            }
            $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg($script);
        } else {
            $script = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'start-server.sh';
            if (!is_file($script)) {
                return ['ok' => false, 'error' => 'Missing scripts/start-server.sh'];
            }
            $cmd = 'bash ' . escapeshellarg($script);
        }

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($cmd, $descriptors, $pipes, $root);
        if (!is_resource($process)) {
            return ['ok' => false, 'error' => 'proc_open failed'];
        }
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        return ['ok' => true];
    }
}
