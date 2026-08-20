<?php
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'WhisperClient.php';

neus_whisper_cors();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'WhisperServerSupervisor.php';

$client = new WhisperClient();
WhisperServerSupervisor::ensureRunning($client);
$status = $client->status();

neus_whisper_json_response([
    'ok' => true,
    'service' => 'NeusWhisper',
    'model_exists' => $status['model_exists'],
    'model_path' => $status['model_path'],
    'whisper_server_up' => $status['whisper_server_up'],
    'whisper_server_url' => $status['whisper_server_url'],
    'whisper_cli' => $status['whisper_cli'],
    'ffmpeg' => $status['ffmpeg'],
]);
