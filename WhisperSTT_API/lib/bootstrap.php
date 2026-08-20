<?php
declare(strict_types=1);

/**
 * Load NeusWhisper config from config.local.env (KEY=VALUE lines).
 */
function neus_whisper_root(): string
{
    return dirname(__DIR__);
}

/**
 * Parse the first available config file into a key/value map.
 *
 * These are only fallback defaults: the real process environment
 * (Docker `environment:`, shell exports) always takes precedence so the
 * same image works on a Hostinger VPS without editing committed files.
 *
 * @return array<string, string>
 */
function neus_whisper_file_env(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }
    $cache = [];

    $root = neus_whisper_root();
    $files = [
        $root . DIRECTORY_SEPARATOR . 'config.local.env',
        $root . DIRECTORY_SEPARATOR . '.env',
        $root . DIRECTORY_SEPARATOR . 'config.example.env',
    ];
    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            continue;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            if ($key === '') {
                continue;
            }
            $cache[$key] = trim(substr($line, $pos + 1));
        }
        break;
    }

    return $cache;
}

function neus_whisper_env(string $key, ?string $default = null): ?string
{
    // 1. Real process environment (Docker/container env, shell exports).
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    }
    // 2. Fallback to the local config file (native dev convenience).
    if ($value === null || $value === false || $value === '') {
        $value = neus_whisper_file_env()[$key] ?? null;
    }
    if ($value === null || $value === '') {
        return $default;
    }
    return (string) $value;
}

function neus_whisper_path(string $relative): string
{
    $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    return neus_whisper_root() . DIRECTORY_SEPARATOR . $relative;
}

function neus_whisper_json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function neus_whisper_cors(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');
}

function neus_whisper_require_api_key(): void
{
    $expected = neus_whisper_env('NEUS_WHISPER_API_KEY', '');
    if ($expected === null || trim($expected) === '') {
        return;
    }
    $provided = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (!hash_equals(trim($expected), trim((string) $provided))) {
        neus_whisper_json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
    }
}

function neus_whisper_ensure_dir(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}
