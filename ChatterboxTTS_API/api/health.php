<?php
declare(strict_types=1);

require __DIR__ . '/http.php';

header('Content-Type: application/json; charset=utf-8');

$configPath = dirname(__DIR__) . '/config.php';
if (!is_file($configPath)) {
    echo json_encode([
        'configured' => false,
        'api_reachable' => false,
        'message' => 'Copy config.example.php to config.php to enable synthesis.',
    ]);
    exit;
}

$config = require $configPath;
$healthUrl = $config['kokoro_health_url'] ?? null;
$authHeaders = chatterbox_auth_headers($config['api_key'] ?? '');

if (!$healthUrl) {
    echo json_encode([
        'configured' => true,
        'api_reachable' => null,
        'message' => 'Health URL not configured. Try generating speech to test the API.',
    ]);
    exit;
}

$result = chatterbox_http_get($healthUrl, 5, $authHeaders);
$reachable = $result['error'] === null && $result['status'] >= 200 && $result['status'] < 300;

echo json_encode([
    'configured' => true,
    'api_reachable' => $reachable,
    'status_code' => $result['status'],
    'message' => $reachable
        ? 'Kokoro API is online.'
        : 'Kokoro API is not running. Start it with start-kokoro-api.bat.',
    'detail' => $result['error'] ?? (json_decode($result['body'], true) ?? trim($result['body'])),
]);
