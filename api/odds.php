<?php
declare(strict_types=1);

$requestedLang = strtolower(trim((string) ($_GET['lang'] ?? 'en')));
if (!in_array($requestedLang, ['en', 'my', 'jp', 'th'], true)) {
    $requestedLang = 'en';
}
define('FTA_LANG', $requestedLang);

require_once __DIR__ . '/../app/layout.php';

fta_send_security_headers(true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization, X-FTA-Auth, X-Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

const FTA_ODDS_API_BASE = 'https://api.hlainghtetaung.com/football/mmodds/';
const FTA_ODDS_SOURCE = 'Myanmar Odds API Documentation (c) Hlaing Htet Aung';

function fta_odds_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

function fta_odds_day(): string
{
    $day = strtolower(trim((string) ($_GET['day'] ?? $_GET['target_day'] ?? 'today')));
    return $day === 'tomorrow' ? 'tomorrow' : 'today';
}

function fta_odds_timezone(): DateTimeZone
{
    return new DateTimeZone('Asia/Yangon');
}

function fta_odds_fetch(string $day): array
{
    $url = $day === 'tomorrow' ? FTA_ODDS_API_BASE . 'index.php?tomorrow' : FTA_ODDS_API_BASE;
    $raw = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch) {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 14,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: FullTimeArena/1.0'],
            ]);
            $raw = curl_exec($ch);
            curl_close($ch);
        }
    }
    if (!is_string($raw) || trim($raw) === '') {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 14,
                'header' => "Accept: application/json\r\nUser-Agent: FullTimeArena/1.0\r\n",
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
    }
    if (!is_string($raw) || trim($raw) === '') {
        throw new RuntimeException('Odds API returned an empty response.');
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        throw new RuntimeException('Odds API returned invalid JSON.');
    }
    return ['url' => $url, 'json' => $json];
}

function fta_odds_archive_root(string $day): string
{
    $day = $day === 'tomorrow' ? 'tomorrow' : 'today';
    return fta_project_path('storage/odds/' . $day);
}

function fta_odds_archive_dir(string $day, string $date): string
{
    return fta_odds_archive_root($day) . DIRECTORY_SEPARATOR . preg_replace('/[^0-9\-]/', '', $date);
}

function fta_odds_slot_path(string $day, array $slot): string
{
    return fta_odds_archive_dir($day, (string) $slot['date']) . DIRECTORY_SEPARATOR . (string) $slot['file'];
}

function fta_odds_active_slot(): ?array
{
    $now = new DateTimeImmutable('now', fta_odds_timezone());
    $hour = (int) $now->format('G');
    if ($hour < 7 || $hour > 19) {
        return null;
    }
    $slot = $hour - 6;
    return [
        'slot' => $slot,
        'file' => $slot . 'hr.json',
        'date' => $now->format('Y-m-d'),
        'captured_at' => $now->format('Y-m-d H:i:s'),
        'hour_label' => sprintf('%02d:00', $hour),
    ];
}

function fta_odds_match_key(string $league, string $home, string $away): string
{
    return substr(hash('sha1', strtolower(trim($league) . '|' . trim($home) . '|' . trim($away))), 0, 18);
}

function fta_odds_clean_url($url): string
{
    $url = trim((string) $url);
    return preg_match('/^https?:\/\//i', $url) ? $url : '';
}

function fta_odds_parse_value(string $value): ?float
{
    $value = strtoupper(trim($value));
    if ($value === '') {
        return null;
    }
    $line = 0.0;
    $price = 0.0;
    if (preg_match('/^L([+-]\d+(?:\.\d+)?)?$/', $value, $matches)) {
        $price = isset($matches[1]) ? (float) $matches[1] : 0.0;
        return $price;
    }
    if (preg_match('/^(\d+(?:\.\d+)?)([+-]\d+(?:\.\d+)?)?$/', $value, $matches)) {
        $line = (float) $matches[1];
        $price = isset($matches[2]) ? (float) $matches[2] : 0.0;
        return ($line * 1000) + $price;
    }
    if (preg_match('/^[+-]\d+(?:\.\d+)?$/', $value)) {
        return (float) $value;
    }
    return null;
}

function fta_odds_change(string $current, string $previous): array
{
    $current = trim($current);
    $previous = trim($previous);
    if ($current === '' || $previous === '' || $current === $previous) {
        return ['tone' => 'same', 'label' => '0', 'previous' => $previous];
    }

    $currentValue = fta_odds_parse_value($current);
    $previousValue = fta_odds_parse_value($previous);
    $tone = 'same';
    if ($currentValue !== null && $previousValue !== null) {
        $tone = $currentValue > $previousValue ? 'up' : ($currentValue < $previousValue ? 'down' : 'same');
    } elseif ($current !== $previous) {
        $tone = 'up';
    }

    return [
        'tone' => $tone,
        'label' => $previous . ' → ' . $current,
        'previous' => $previous,
    ];
}

function fta_odds_normalize(array $json, string $day): array
{
    $groups = [];
    $flat = [];
    $totalMatches = 0;
    $data = is_array($json['data'] ?? null) ? $json['data'] : [];

    foreach ($data as $league => $matches) {
        $leagueName = trim((string) $league);
        $leagueMatches = [];
        foreach ((array) $matches as $match) {
            if (!is_array($match)) {
                continue;
            }
            $home = trim((string) ($match['home_team'] ?? ''));
            $away = trim((string) ($match['away_team'] ?? ''));
            if ($home === '' && $away === '') {
                continue;
            }
            $key = fta_odds_match_key($leagueName, $home, $away);
            $item = [
                'key' => $key,
                'league' => $leagueName,
                'home_team' => $home,
                'away_team' => $away,
                'hdp_odds' => trim((string) ($match['hdp_odds'] ?? '')),
                'ou_odds' => trim((string) ($match['ou_odds'] ?? '')),
                'fav_team' => trim((string) ($match['fav_team'] ?? '')),
                'home_logo' => fta_odds_clean_url($match['home_logo'] ?? ''),
                'away_logo' => fta_odds_clean_url($match['away_logo'] ?? ''),
            ];
            $leagueMatches[] = $item;
            $flat[$key] = $item;
            $totalMatches++;
        }
        if ($leagueMatches) {
            $groups[] = ['league' => $leagueName, 'matches' => $leagueMatches];
        }
    }

    return [
        'status' => (string) ($json['status'] ?? 'success'),
        'target_day' => $day,
        'last_updated' => (string) ($json['last_updated'] ?? ''),
        'total_leagues' => (int) ($json['total_leagues'] ?? count($groups)),
        'total_matches' => $totalMatches,
        'leagues' => $groups,
        'flat' => $flat,
        'source' => FTA_ODDS_SOURCE,
    ];
}

function fta_odds_snapshot_payload(array $json, string $day, array $slot, string $sourceUrl): array
{
    return [
        'captured_at' => $slot['captured_at'],
        'target_day' => $day,
        'slot' => $slot['slot'],
        'hour_label' => $slot['hour_label'],
        'source_url' => $sourceUrl,
        'response' => $json,
    ];
}

function fta_odds_store_snapshot(string $day, array $json, string $sourceUrl): ?array
{
    $slot = fta_odds_active_slot();
    if (!$slot) {
        return null;
    }

    $dir = fta_odds_archive_dir($day, $slot['date']);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $path = fta_odds_slot_path($day, $slot);
    $recorded = false;
    if (!is_file($path)) {
        file_put_contents(
            $path,
            json_encode(fta_odds_snapshot_payload($json, $day, $slot, $sourceUrl), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            LOCK_EX
        );
        $recorded = true;
    }

    return [
        'slot' => $slot['slot'],
        'file' => $slot['file'],
        'date' => $slot['date'],
        'recorded' => $recorded,
    ];
}

function fta_odds_record_missing_slot(string $day): ?array
{
    $slot = fta_odds_active_slot();
    if (!$slot) {
        return null;
    }
    $path = fta_odds_slot_path($day, $slot);
    if (is_file($path)) {
        return [
            'slot' => $slot['slot'],
            'file' => $slot['file'],
            'date' => $slot['date'],
            'recorded' => false,
            'exists' => true,
        ];
    }
    $fetch = fta_odds_fetch($day);
    return fta_odds_store_snapshot($day, $fetch['json'], $fetch['url']);
}

function fta_odds_record_all_due(): array
{
    $results = [];
    foreach (['today', 'tomorrow'] as $day) {
        try {
            $results[$day] = fta_odds_record_missing_slot($day) ?? ['recorded' => false, 'outside_window' => true];
        } catch (Throwable $error) {
            $results[$day] = ['recorded' => false, 'error' => $error->getMessage()];
        }
    }
    return [
        'status' => 'success',
        'mode' => 'record',
        'window' => '07:00-19:59',
        'results' => $results,
    ];
}

function fta_odds_read_snapshot(string $path): ?array
{
    $raw = is_file($path) ? file_get_contents($path) : false;
    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }
    $json = json_decode($raw, true);
    return is_array($json) ? $json : null;
}

function fta_odds_snapshot_response(array $snapshot): array
{
    $response = $snapshot['response'] ?? $snapshot;
    return is_array($response) ? $response : [];
}

function fta_odds_snapshot_files(string $day, ?string $date = null): array
{
    $dirs = [];
    if ($date !== null) {
        $dir = fta_odds_archive_dir($day, $date);
        $dirs = is_dir($dir) ? [$dir] : [];
    } else {
        $root = fta_odds_archive_root($day);
        $dirs = is_dir($root) ? glob($root . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] : [];
        rsort($dirs, SORT_STRING);
    }

    $files = [];
    foreach ($dirs as $dir) {
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*hr.json') ?: [] as $path) {
            if (preg_match('/(\d+)hr\.json$/', $path, $matches)) {
                $files[] = [
                    'path' => $path,
                    'slot' => (int) $matches[1],
                    'file' => basename($path),
                    'date' => basename(dirname($path)),
                ];
            }
        }
    }

    usort($files, static function (array $a, array $b): int {
        return [$a['date'], $a['slot']] <=> [$b['date'], $b['slot']];
    });
    return $files;
}

function fta_odds_latest_snapshot(string $day): ?array
{
    $files = fta_odds_snapshot_files($day);
    if (!$files) {
        return null;
    }
    $file = end($files);
    $snapshot = fta_odds_read_snapshot($file['path']);
    if (!$snapshot) {
        return null;
    }
    return ['meta' => $file, 'snapshot' => $snapshot];
}

function fta_odds_compare_snapshot(string $day, ?array $activeArchive): ?array
{
    $date = $activeArchive['date'] ?? (new DateTimeImmutable('now', fta_odds_timezone()))->format('Y-m-d');
    $slot = (int) ($activeArchive['slot'] ?? 99);
    $files = array_values(array_filter(fta_odds_snapshot_files($day, $date), static function (array $file) use ($slot): bool {
        return (int) $file['slot'] < $slot;
    }));
    if (!$files) {
        return null;
    }
    $file = end($files);
    $snapshot = fta_odds_read_snapshot($file['path']);
    if (!$snapshot) {
        return null;
    }
    return ['meta' => $file, 'snapshot' => $snapshot];
}

function fta_odds_apply_changes(array $normalized, ?array $previousNormalized): array
{
    $previousFlat = is_array($previousNormalized['flat'] ?? null) ? $previousNormalized['flat'] : [];
    foreach ($normalized['leagues'] as &$league) {
        foreach ($league['matches'] as &$match) {
            $previous = is_array($previousFlat[$match['key']] ?? null) ? $previousFlat[$match['key']] : null;
            $match['hdp_change'] = $previous ? fta_odds_change($match['hdp_odds'], (string) ($previous['hdp_odds'] ?? '')) : ['tone' => 'same', 'label' => '0'];
            $match['ou_change'] = $previous ? fta_odds_change($match['ou_odds'], (string) ($previous['ou_odds'] ?? '')) : ['tone' => 'same', 'label' => '0'];
        }
        unset($match);
    }
    unset($league);
    unset($normalized['flat']);
    return $normalized;
}

function fta_odds_rates(string $day): array
{
    $archive = null;
    $fetch = null;
    try {
        $fetch = fta_odds_fetch($day);
        $archive = fta_odds_store_snapshot($day, $fetch['json'], $fetch['url']);
        try {
            fta_odds_record_missing_slot($day === 'today' ? 'tomorrow' : 'today');
        } catch (Throwable $ignored) {
        }
        $normalized = fta_odds_normalize($fetch['json'], $day);
    } catch (Throwable $error) {
        $latest = fta_odds_latest_snapshot($day);
        if (!$latest) {
            throw $error;
        }
        $archive = [
            'slot' => $latest['meta']['slot'],
            'file' => $latest['meta']['file'],
            'date' => $latest['meta']['date'],
            'recorded' => false,
            'stale' => true,
        ];
        $normalized = fta_odds_normalize(fta_odds_snapshot_response($latest['snapshot']), $day);
    }

    $compare = fta_odds_compare_snapshot($day, $archive);
    $previous = $compare ? fta_odds_normalize(fta_odds_snapshot_response($compare['snapshot']), $day) : null;
    $payload = fta_odds_apply_changes($normalized, $previous);
    $payload['archive'] = [
        'active_slot' => $archive['slot'] ?? null,
        'active_file' => $archive['file'] ?? '',
        'recorded' => (bool) ($archive['recorded'] ?? false),
        'compare_slot' => $compare['meta']['slot'] ?? null,
        'stale' => (bool) ($archive['stale'] ?? false),
    ];
    return $payload;
}

function fta_odds_history(string $day, string $matchKey): array
{
    $current = null;
    try {
        $fetch = fta_odds_fetch($day);
        fta_odds_store_snapshot($day, $fetch['json'], $fetch['url']);
        $current = fta_odds_normalize($fetch['json'], $day);
    } catch (Throwable $error) {
        $current = null;
    }

    $today = (new DateTimeImmutable('now', fta_odds_timezone()))->format('Y-m-d');
    $rows = [];
    $title = '';
    $previous = null;

    foreach (fta_odds_snapshot_files($day, $today) as $file) {
        $snapshot = fta_odds_read_snapshot($file['path']);
        if (!$snapshot) {
            continue;
        }
        $normalized = fta_odds_normalize(fta_odds_snapshot_response($snapshot), $day);
        $match = is_array($normalized['flat'][$matchKey] ?? null) ? $normalized['flat'][$matchKey] : null;
        if (!$match) {
            continue;
        }
        $title = $title ?: $match['home_team'] . ' vs ' . $match['away_team'];
        $rows[] = [
            'slot' => $file['slot'],
            'file' => $file['file'],
            'hour_label' => (string) ($snapshot['hour_label'] ?? sprintf('%02d:00', $file['slot'] + 6)),
            'captured_at' => (string) ($snapshot['captured_at'] ?? ''),
            'hdp_odds' => (string) ($match['hdp_odds'] ?? ''),
            'ou_odds' => (string) ($match['ou_odds'] ?? ''),
            'fav_team' => (string) ($match['fav_team'] ?? ''),
            'hdp_change' => $previous ? fta_odds_change((string) ($match['hdp_odds'] ?? ''), (string) ($previous['hdp_odds'] ?? '')) : ['tone' => 'same', 'label' => '0'],
            'ou_change' => $previous ? fta_odds_change((string) ($match['ou_odds'] ?? ''), (string) ($previous['ou_odds'] ?? '')) : ['tone' => 'same', 'label' => '0'],
        ];
        $previous = $match;
    }

    if ($current && isset($current['flat'][$matchKey])) {
        $match = $current['flat'][$matchKey];
        $title = $title ?: $match['home_team'] . ' vs ' . $match['away_team'];
        $last = end($rows);
        $lastSame = $last
            && (string) ($last['hdp_odds'] ?? '') === (string) ($match['hdp_odds'] ?? '')
            && (string) ($last['ou_odds'] ?? '') === (string) ($match['ou_odds'] ?? '');
        if (!$lastSame) {
            $rows[] = [
                'slot' => 0,
                'file' => 'current',
                'hour_label' => t('now', 'Now'),
                'captured_at' => (new DateTimeImmutable('now', fta_odds_timezone()))->format('Y-m-d H:i:s'),
                'hdp_odds' => (string) ($match['hdp_odds'] ?? ''),
                'ou_odds' => (string) ($match['ou_odds'] ?? ''),
                'fav_team' => (string) ($match['fav_team'] ?? ''),
                'hdp_change' => $previous ? fta_odds_change((string) ($match['hdp_odds'] ?? ''), (string) ($previous['hdp_odds'] ?? '')) : ['tone' => 'same', 'label' => '0'],
                'ou_change' => $previous ? fta_odds_change((string) ($match['ou_odds'] ?? ''), (string) ($previous['ou_odds'] ?? '')) : ['tone' => 'same', 'label' => '0'],
            ];
        }
    }

    return [
        'status' => 'success',
        'target_day' => $day,
        'match_key' => $matchKey,
        'title' => $title,
        'rows' => $rows,
        'source' => FTA_ODDS_SOURCE,
    ];
}

try {
    $day = fta_odds_day();
    $mode = strtolower(trim((string) ($_GET['mode'] ?? 'rates')));
    if ($mode === 'history') {
        $key = trim((string) ($_GET['key'] ?? ''));
        if (!preg_match('/^[a-f0-9]{12,40}$/', $key)) {
            fta_odds_response(400, ['status' => 'error', 'message' => t('odds_error_message', 'Could not load odds right now. Please try again.')]);
        }
        fta_odds_response(200, fta_odds_history($day, $key));
    }
    if ($mode === 'record') {
        fta_odds_response(200, fta_odds_record_all_due());
    }

    fta_odds_response(200, fta_odds_rates($day));
} catch (Throwable $error) {
    fta_odds_response(502, [
        'status' => 'error',
        'message' => t('odds_error_message', 'Could not load odds right now. Please try again.'),
    ]);
}
