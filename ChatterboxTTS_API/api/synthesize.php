<?php
declare(strict_types=1);

require __DIR__ . '/http.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

$configPath = dirname(__DIR__) . '/config.php';
if (!is_file($configPath)) {
    http_response_code(503);
    echo json_encode([
        'error' => 'Configuration missing. Copy config.example.php to config.php and start the Kokoro API server.',
    ]);
    exit;
}

$config = require $configPath;
$rawBody = file_get_contents('php://input') ?: '';
$payload = json_decode($rawBody, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body.']);
    exit;
}

$text = trim((string) ($payload['text'] ?? ''));
if ($text === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Text is required.']);
    exit;
}

if (mb_strlen($text) > 5000) {
    http_response_code(400);
    echo json_encode(['error' => 'Text must be 5000 characters or fewer.']);
    exit;
}

$voice = trim((string) ($payload['voice'] ?? $config['default_voice'] ?? 'af_heart'));
$speed = isset($payload['speed']) ? (float) $payload['speed'] : (float) ($config['default_speed'] ?? 1.0);
$speed = max(0.5, min(2.0, $speed));

$requestBody = json_encode([
    'input' => $text,
    'voice' => $voice,
    'speed' => $speed,
    'response_format' => 'wav',
], JSON_UNESCAPED_UNICODE);

if ($requestBody === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to encode request payload.']);
    exit;
}

$result = chatterbox_http_post(
    $config['kokoro_api_url'],
    $requestBody,
    (int) ($config['request_timeout'] ?? 60),
    chatterbox_auth_headers($config['api_key'] ?? '')
);

if ($result['error'] !== null) {
    http_response_code(502);
    echo json_encode([
        'error' => 'Could not reach the Kokoro API server.',
        'detail' => $result['error'],
        'hint' => 'Start the API locally (start-kokoro-api.bat) or deploy with Docker on your VPS (see DEPLOY.md).',
    ]);
    exit;
}

$statusCode = $result['status'];
$body = $result['body'];
$contentType = $result['headers']['content-type'] ?? 'application/octet-stream';

if ($statusCode >= 400) {
    http_response_code($statusCode >= 500 ? 502 : $statusCode);
    $decoded = json_decode($body, true);
    if (is_array($decoded)) {
        echo json_encode([
            'error' => $decoded['error'] ?? $decoded['detail'] ?? 'Kokoro API returned an error.',
            'detail' => $decoded,
        ]);
    } else {
        echo json_encode([
            'error' => 'Kokoro API returned an error.',
            'detail' => trim($body) !== '' ? trim($body) : "HTTP {$statusCode}",
        ]);
    }
    exit;
}

if (stripos($contentType, 'application/json') !== false) {
    $decoded = json_decode($body, true);
    if (is_array($decoded) && isset($decoded['audio_content'])) {
        $audioBase64 = $decoded['audio_content'];
    } else {
        http_response_code(502);
        echo json_encode(['error' => 'Unexpected JSON response from Kokoro API.', 'detail' => $decoded]);
        exit;
    }
} else {
    $audioBase64 = base64_encode($body);
}

echo json_encode([
    'audio_base64' => $audioBase64,
    'content_type' => stripos($contentType, 'json') !== false ? 'audio/wav' : $contentType,
    'text_length' => mb_strlen($text),
    'generation_seconds' => isset($result['headers']['x-generation-seconds'])
        ? (float) $result['headers']['x-generation-seconds']
        : null,
    'audio_duration' => isset($result['headers']['x-audio-duration'])
        ? (float) $result['headers']['x-audio-duration']
        : null,
    'voice' => $result['headers']['x-voice'] ?? $voice,
]);