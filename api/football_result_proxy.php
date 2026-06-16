<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const FTA_RESULT_API_BASE = 'https://api.hlainghtetaung.com/football/result/';

function fta_result_proxy_response(int $status, array $payload): void
{
    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
    );
    if (!is_string($json)) {
        $status = 500;
        $json = '{"status":"error","message":"Result API JSON encoding failed."}';
    }

    http_response_code($status);
    echo $json;
    exit;
}

function fta_result_proxy_param(string $key): string
{
    return trim((string) ($_GET[$key] ?? ''));
}

function fta_result_proxy_url(): string
{
    $type = fta_result_proxy_param('type');

    if ($type === 'matches') {
        $date = fta_result_proxy_param('d');
        if (!preg_match('/^\d{8}$/', $date)) {
            fta_result_proxy_response(400, ['status' => 'error', 'message' => 'Date must use YYYYMMDD format.']);
        }

        return FTA_RESULT_API_BASE . '?' . http_build_query([
            'action' => 'get_matches',
            'd' => $date,
        ]);
    }

    if ($type === 'table') {
        $leagueId = fta_result_proxy_param('league_id');
        if (!preg_match('/^\d+$/', $leagueId)) {
            fta_result_proxy_response(400, ['status' => 'error', 'message' => 'League ID is required.']);
        }

        return FTA_RESULT_API_BASE . '?' . http_build_query([
            'action' => 'get_table',
            'league_id' => $leagueId,
        ]);
    }

    if ($type === 'facts') {
        $matchId = fta_result_proxy_param('match_id');
        if (!preg_match('/^\d+$/', $matchId)) {
            fta_result_proxy_response(400, ['status' => 'error', 'message' => 'Match ID is required.']);
        }

        return FTA_RESULT_API_BASE . 'facts.php?' . http_build_query(['match_id' => $matchId]);
    }

    if ($type === 'details') {
        $matchId = fta_result_proxy_param('id');
        if (!preg_match('/^\d+$/', $matchId)) {
            fta_result_proxy_response(400, ['status' => 'error', 'message' => 'Match ID is required.']);
        }

        return FTA_RESULT_API_BASE . 'details.php?' . http_build_query(['id' => $matchId]);
    }

    fta_result_proxy_response(400, ['status' => 'error', 'message' => 'Unsupported result request type.']);
}

function fta_result_proxy_fetch(string $url): string
{
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
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
            throw new RuntimeException($error !== '' ? $error : 'Football result API request failed.');
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
    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        throw new RuntimeException('Football result API request failed.');
    }

    return $body;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    fta_result_proxy_response(405, ['status' => 'error', 'message' => 'Only GET requests are supported.']);
}

try {
    $remoteBody = fta_result_proxy_fetch(fta_result_proxy_url());
    $decoded = json_decode($remoteBody, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Football result API returned invalid JSON.');
    }

    $json = json_encode(
        $decoded,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
    );
    echo is_string($json) ? $json : '{"status":"error","message":"Result API JSON encoding failed."}';
} catch (Throwable $error) {
    fta_result_proxy_response(502, [
        'status' => 'error',
        'message' => 'Could not connect to football result API.',
    ]);
}
