<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

const FTA_LIVE_API_URL = 'https://api.hlainghtetaung.com/football/live/';

function fta_live_proxy_response(int $status, array $payload): void
{
    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
    );
    if (!is_string($json)) {
        $status = 500;
        $json = '{"status":"error","message":"Live API JSON encoding failed."}';
    }

    http_response_code($status);
    echo $json;
    exit;
}

function fta_live_proxy_fetch(): string
{
    if (function_exists('curl_init')) {
        $curl = curl_init(FTA_LIVE_API_URL);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 14,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'FullTimeArena/1.0',
        ]);

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false || $status >= 400) {
            throw new RuntimeException($error !== '' ? $error : 'Football live API request failed.');
        }

        return (string) $body;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 14,
            'header' => "Accept: application/json\r\nUser-Agent: FullTimeArena/1.0\r\n",
        ],
    ]);
    $body = @file_get_contents(FTA_LIVE_API_URL, false, $context);
    if ($body === false) {
        throw new RuntimeException('Football live API request failed.');
    }

    return $body;
}

function fta_live_proxy_origin(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return ($https ? 'https' : 'http') . '://' . $host;
}

function fta_live_proxy_absolute_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (preg_match('/^https?:\/\//i', $url)) {
        return $url;
    }

    return fta_live_proxy_origin() . fta_base_url($url);
}

function fta_live_proxy_empty_payload(): array
{
    return ['live' => [], 'upcoming' => []];
}

function fta_live_proxy_manual_payload(): array
{
    $payload = fta_live_proxy_empty_payload();

    try {
        foreach (fta_active_live_matches() as $match) {
            $item = fta_live_match_payload($match, 'fta_live_proxy_absolute_url');
            $payload[$item['is_live'] ? 'live' : 'upcoming'][] = $item;
        }
    } catch (Throwable) {
        return fta_live_proxy_empty_payload();
    }

    return $payload;
}

function fta_live_proxy_has_matches(array $payload): bool
{
    return !empty($payload['live']) || !empty($payload['upcoming']);
}

function fta_live_proxy_decode_remote(string $body): array
{
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Football live API returned invalid JSON.');
    }

    if (($decoded['status'] ?? '') !== 'success' || !is_array($decoded['data'] ?? null)) {
        $message = trim((string) ($decoded['message'] ?? 'Football live API returned no matches.'));
        throw new RuntimeException($message !== '' ? $message : 'Football live API returned no matches.');
    }

    return [
        'live' => is_array($decoded['data']['live'] ?? null) ? $decoded['data']['live'] : [],
        'upcoming' => is_array($decoded['data']['upcoming'] ?? null) ? $decoded['data']['upcoming'] : [],
    ];
}

function fta_live_proxy_merge_payload(array $remote, array $manual): array
{
    return [
        'live' => array_values(array_merge($manual['live'] ?? [], $remote['live'] ?? [])),
        'upcoming' => array_values(array_merge($manual['upcoming'] ?? [], $remote['upcoming'] ?? [])),
    ];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
    fta_live_proxy_response(204, []);
}

if ($method !== 'GET') {
    fta_live_proxy_response(405, ['status' => 'error', 'message' => 'Only GET requests are supported.']);
}

$manualPayload = fta_live_proxy_manual_payload();

try {
    $remoteBody = fta_live_proxy_fetch();
    $remotePayload = fta_live_proxy_decode_remote($remoteBody);
    $payload = fta_live_proxy_merge_payload($remotePayload, $manualPayload);

    fta_live_proxy_response(200, [
        'status' => 'success',
        'source' => fta_live_proxy_has_matches($manualPayload) ? 'mixed' : 'remote',
        'data' => $payload,
    ]);
} catch (Throwable $error) {
    if (fta_live_proxy_has_matches($manualPayload)) {
        fta_live_proxy_response(200, [
            'status' => 'success',
            'source' => 'manual',
            'message' => 'External live API unavailable; using manual admin matches.',
            'upstream_message' => $error->getMessage(),
            'data' => $manualPayload,
        ]);
    }

    fta_live_proxy_response(502, [
        'status' => 'error',
        'message' => 'Could not connect to football live API.',
        'upstream_message' => $error->getMessage(),
    ]);
}
