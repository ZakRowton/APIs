<?php
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'WhisperClient.php';

neus_whisper_cors();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    neus_whisper_json_response(['ok' => false, 'error' => 'Method not allowed. Use POST multipart/form-data with field "file".'], 405);
}

neus_whisper_require_api_key();

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    neus_whisper_json_response(['ok' => false, 'error' => 'Missing upload field "file".'], 400);
}

$file = $_FILES['file'];
$error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($error !== UPLOAD_ERR_OK) {
    neus_whisper_json_response(['ok' => false, 'error' => 'Upload failed with code ' . $error], 400);
}

$maxBytes = (int) (neus_whisper_env('NEUS_WHISPER_MAX_UPLOAD_BYTES', '26214400') ?? '26214400');
$size = (int) ($file['size'] ?? 0);
if ($size <= 0 || $size > $maxBytes) {
    neus_whisper_json_response(['ok' => false, 'error' => 'File too large or empty.'], 413);
}

$tmpPath = (string) ($file['tmp_name'] ?? '');
if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
    neus_whisper_json_response(['ok' => false, 'error' => 'Invalid upload.'], 400);
}

$originalName = basename((string) ($file['name'] ?? 'audio.bin'));
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowed = ['wav', 'mp3', 'm4a', 'ogg', 'webm', 'flac', 'mp4', 'mpeg', 'aac', 'opus'];
if ($ext !== '' && !in_array($ext, $allowed, true)) {
    neus_whisper_json_response(['ok' => false, 'error' => 'Unsupported audio type: ' . $ext], 415);
}

$uploadDir = neus_whisper_path('runtime' . DIRECTORY_SEPARATOR . 'uploads');
neus_whisper_ensure_dir($uploadDir);
$storedPath = $uploadDir . DIRECTORY_SEPARATOR . bin2hex(random_bytes(8)) . ($ext !== '' ? '.' . $ext : '.bin');

if (!move_uploaded_file($tmpPath, $storedPath)) {
    neus_whisper_json_response(['ok' => false, 'error' => 'Could not store uploaded file.'], 500);
}

$options = [
    'language' => $_POST['language'] ?? null,
    'prompt' => $_POST['prompt'] ?? null,
    'response_format' => $_POST['response_format'] ?? 'json',
    'temperature' => $_POST['temperature'] ?? '0.0',
];

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'WhisperServerSupervisor.php';

try {
    $client = new WhisperClient();
    WhisperServerSupervisor::ensureRunning($client);
    $result = $client->transcribe($storedPath, $options);
    if ($result['ok'] !== true) {
        // 502 = upstream whisper-server failed, but the client sent valid audio.
        // Include retryable flag so the frontend can auto-retry.
        $payload = ['ok' => false, 'error' => $result['error'] ?? 'Transcription failed.'];
        $retryable = $result['retryable'] ?? null;
        if ($retryable === true) {
            $payload['retryable'] = true;
        }
        neus_whisper_json_response($payload, 502);
    }
    neus_whisper_json_response([
        'ok' => true,
        'text' => $result['text'] ?? '',
        'segments' => $result['segments'] ?? null,
        'source' => $result['source'] ?? 'unknown',
        'filename' => $originalName,
    ]);
} finally {
    @unlink($storedPath);
}
