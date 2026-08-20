<?php
declare(strict_types=1);

/**
 * POST JSON to a URL and return [statusCode, headers, body, error].
 *
 * @return array{status:int, headers:array<string,string>, body:string, error:?string}
 */
function chatterbox_auth_headers(?string $apiKey = null): array
{
    if ($apiKey === null || $apiKey === '') {
        return [];
    }

    return ['Authorization: Bearer ' . $apiKey];
}

/**
 * POST JSON to a URL and return [statusCode, headers, body, error].
 *
 * @param array<int, string> $extraHeaders
 * @return array{status:int, headers:array<string,string>, body:string, error:?string}
 */
function chatterbox_http_post(string $url, string $jsonBody, int $timeout = 120, array $extraHeaders = []): array
{
    $headers = array_merge(
        [
            'Content-Type: application/json',
            'Accept: audio/wav, application/json',
        ],
        $extraHeaders
    );

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonBody,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($response === false) {
            return ['status' => 0, 'headers' => [], 'body' => '', 'error' => $error ?: 'cURL request failed.'];
        }

        $rawHeaders = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        return [
            'status' => $status,
            'headers' => chatterbox_parse_headers($rawHeaders),
            'body' => $body,
            'error' => null,
        ];
    }

    $headerLines = implode("\r\n", array_merge(
        [
            'Content-Type: application/json',
            'Accept: audio/wav, application/json',
        ],
        $extraHeaders
    ));

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => $headerLines . "\r\n",
            'content' => $jsonBody,
            'timeout' => $timeout,
            'ignore_errors' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        $lastError = error_get_last();
        return [
            'status' => 0,
            'headers' => [],
            'body' => '',
            'error' => $lastError['message'] ?? 'HTTP request failed.',
        ];
    }

    $status = 0;
    $headers = [];
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $line) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $line, $matches)) {
                $status = (int) $matches[1];
            } elseif (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }
    }

    return ['status' => $status, 'headers' => $headers, 'body' => $body, 'error' => null];
}

/**
 * GET a URL and return [statusCode, body, error].
 *
 * @return array{status:int, body:string, error:?string}
 */
function chatterbox_http_get(string $url, int $timeout = 5, array $extraHeaders = []): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER => $extraHeaders,
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            return ['status' => 0, 'body' => '', 'error' => $error ?: 'cURL request failed.'];
        }

        return ['status' => $status, 'body' => $body, 'error' => null];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $extraHeaders) . (empty($extraHeaders) ? '' : "\r\n"),
            'timeout' => $timeout,
            'ignore_errors' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        $lastError = error_get_last();
        return [
            'status' => 0,
            'body' => '',
            'error' => $lastError['message'] ?? 'HTTP request failed.',
        ];
    }

    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
        $status = (int) $matches[1];
    }

    return ['status' => $status, 'body' => $body, 'error' => null];
}

/**
 * @return array<string, string>
 */
function chatterbox_parse_headers(string $rawHeaders): array
{
    $headers = [];
    foreach (explode("\r\n", $rawHeaders) as $line) {
        if (!str_contains($line, ':')) {
            continue;
        }
        [$name, $value] = explode(':', $line, 2);
        $headers[strtolower(trim($name))] = trim($value);
    }

    return $headers;
}
