<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function fta_send_security_headers(bool $api = false): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(self)');
    header('Cross-Origin-Resource-Policy: same-origin');
    if (!$api) {
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net data:; img-src 'self' data: https: blob:; media-src 'self' https: blob:; frame-src 'self' https:; connect-src 'self' https:; base-uri 'self'; form-action 'self'");
    }
}

function fta_current_lang(): string
{
    $lang = defined('FTA_LANG') ? FTA_LANG : FTA_DEFAULT_LANG;
    return in_array($lang, FTA_LANGS, true) ? $lang : FTA_DEFAULT_LANG;
}

function fta_load_translations(string $lang): array
{
    $path = fta_project_path($lang . '/lang.php');
    if (!is_file($path)) {
        $path = fta_project_path(FTA_DEFAULT_LANG . '/lang.php');
    }

    $translations = require $path;
    return is_array($translations) ? $translations : [];
}

function t(string $key, ?string $fallback = null): string
{
    static $translations = null;
    if ($translations === null) {
        $translations = fta_load_translations(fta_current_lang());
    }

    return $translations[$key] ?? $fallback ?? $key;
}

function fta_base_url(string $path = ''): string
{
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = rtrim($dir, '/');
    $leaf = basename($dir);

    if (in_array($leaf, array_merge(FTA_LANGS, ['admin', 'hha', 'agent', 'api']), true)) {
        $dir = str_replace('\\', '/', dirname($dir));
    }

    if ($dir === '/' || $dir === '.') {
        $dir = '';
    }

    $path = ltrim($path, '/');
    return rtrim($dir, '/') . ($path === '' ? '' : '/' . $path);
}

function fta_lang_url(string $lang, string $page = ''): string
{
    $page = ltrim($page, '/');
    return fta_base_url($lang . '/' . $page);
}

function fta_logo_url(): string
{
    return fta_base_url('logo.png');
}

function fta_asset_version(string $relativePath): string
{
    $path = fta_project_path($relativePath);
    $modified = is_file($path) ? (string) filemtime($path) : '0';
    return fta_setting('site_version', '1') . '-' . $modified;
}

function fta_clean_link(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $url) || str_starts_with($url, '/') || preg_match('/^[A-Za-z0-9_\-\.\/]+(\?[A-Za-z0-9_\-\.=&%]+)?$/', $url)) {
        return $url;
    }

    return '';
}

function fta_category_display_name(array $category): string
{
    $link = strtolower(trim((string) ($category['link_url'] ?? '')));
    $name = trim((string) ($category['name'] ?? ''));
    if ($link === 'odds.php' || str_contains(strtolower($name), 'ပေါက်ကြေး') || str_contains(strtolower($name), 'odds')) {
        return t('odds_title', $name !== '' ? $name : 'Odds');
    }
    return $name;
}

function fta_settings(): array
{
    $rows = db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = (string) $row['setting_value'];
    }

    return $settings;
}

function fta_setting(string $key, string $default = ''): string
{
    static $settings = null;
    if ($settings === null) {
        $settings = fta_settings();
    }

    return $settings[$key] ?? $default;
}

function fta_save_setting(string $key, string $value): void
{
    $statement = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $statement->execute(['key' => $key, 'value' => $value]);
}

function fta_active_ads(): array
{
    return db()->query('SELECT * FROM ads WHERE active = 1 ORDER BY sort_order ASC, id DESC')->fetchAll();
}

function fta_all_ads(): array
{
    return db()->query('SELECT * FROM ads ORDER BY sort_order ASC, id DESC')->fetchAll();
}

function fta_active_categories(): array
{
    return db()->query('SELECT * FROM categories WHERE active = 1 ORDER BY sort_order ASC, id ASC')->fetchAll();
}

function fta_all_categories(): array
{
    return db()->query('SELECT * FROM categories ORDER BY sort_order ASC, id ASC')->fetchAll();
}

function fta_clean_stream_url(?string $url): string
{
    $url = trim((string) $url);
    return preg_match('/^https?:\/\//i', $url) ? $url : '';
}

function fta_youtube_video_id(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }

    $parts = parse_url($url);
    $host = strtolower((string) ($parts['host'] ?? ''));
    $path = trim((string) ($parts['path'] ?? ''), '/');
    $videoId = '';

    if (str_contains($host, 'youtu.be')) {
        $videoId = strtok($path, '/') ?: '';
    } elseif (str_contains($host, 'youtube.com')) {
        parse_str((string) ($parts['query'] ?? ''), $query);
        $videoId = (string) ($query['v'] ?? '');
        if ($videoId === '' && preg_match('~^(embed|shorts)/([^/?#]+)~', $path, $matches)) {
            $videoId = (string) $matches[2];
        }
    }

    if (!preg_match('/^[A-Za-z0-9_-]{6,20}$/', $videoId)) {
        return '';
    }

    return $videoId;
}

function fta_youtube_embed_url(?string $url): string
{
    $videoId = fta_youtube_video_id($url);
    return $videoId === '' ? '' : 'https://www.youtube.com/embed/' . $videoId;
}

function fta_youtube_thumbnail_url(?string $url): string
{
    $videoId = fta_youtube_video_id($url);
    return $videoId === '' ? '' : 'https://img.youtube.com/vi/' . $videoId . '/hqdefault.jpg';
}

function fta_app_guide_videos_from_settings(array $settings): array
{
    $items = [];
    $decoded = json_decode((string) ($settings['app_guide_videos'] ?? ''), true);
    if (is_array($decoded)) {
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $url = fta_clean_link($item['url'] ?? '');
            if ($url === '') {
                continue;
            }
            $items[] = [
                'title' => trim((string) ($item['title'] ?? '')) ?: t('app_guide', 'App Guide'),
                'url' => $url,
                'embed_url' => fta_youtube_embed_url($url),
                'thumbnail_url' => fta_youtube_thumbnail_url($url),
            ];
        }
    }

    if (!$items) {
        $legacyUrl = fta_clean_link($settings['app_guide_youtube_url'] ?? '');
        if ($legacyUrl !== '') {
            $items[] = [
                'title' => t('app_guide', 'App Guide'),
                'url' => $legacyUrl,
                'embed_url' => fta_youtube_embed_url($legacyUrl),
                'thumbnail_url' => fta_youtube_thumbnail_url($legacyUrl),
            ];
        }
    }

    return $items;
}

function fta_app_guide_videos_from_post(array $input): array
{
    $titles = $input['app_guide_titles'] ?? [];
    $urls = $input['app_guide_urls'] ?? [];
    $delete = array_map('strval', (array) ($input['app_guide_delete'] ?? []));
    $titles = is_array($titles) ? array_values($titles) : [];
    $urls = is_array($urls) ? array_values($urls) : [];
    $videos = [];

    foreach ($urls as $index => $url) {
        if (in_array((string) $index, $delete, true)) {
            continue;
        }
        $cleanUrl = fta_clean_link($url);
        if ($cleanUrl === '') {
            continue;
        }
        $videos[] = [
            'title' => substr(trim((string) ($titles[$index] ?? '')), 0, 120) ?: t('app_guide', 'App Guide'),
            'url' => $cleanUrl,
        ];
        if (count($videos) >= 12) {
            break;
        }
    }

    return $videos;
}

function fta_live_streams_from_text(string $text): array
{
    $streams = [];
    $lines = preg_split('/\R+/', $text) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $label = '';
        $url = $line;
        if (str_contains($line, '|')) {
            [$label, $url] = array_map('trim', explode('|', $line, 2));
        }

        $url = fta_clean_stream_url($url);
        if ($url === '') {
            continue;
        }

        $streams[] = [
            'label' => $label,
            'url' => $url,
        ];
    }

    return $streams;
}

function fta_live_streams_to_text(?string $json): string
{
    $decoded = json_decode((string) $json, true);
    if (!is_array($decoded)) {
        return '';
    }

    $lines = [];
    foreach ($decoded as $stream) {
        if (!is_array($stream)) {
            continue;
        }
        $url = fta_clean_stream_url((string) ($stream['url'] ?? ''));
        if ($url === '') {
            continue;
        }
        $label = trim((string) ($stream['label'] ?? $stream['name'] ?? $stream['title'] ?? ''));
        $lines[] = $label !== '' ? $label . ' | ' . $url : $url;
    }

    return implode("\n", $lines);
}

function fta_all_live_matches(): array
{
    return db()->query('SELECT * FROM live_matches ORDER BY sort_order ASC, id DESC')->fetchAll();
}

function fta_active_live_matches(): array
{
    return db()->query('SELECT * FROM live_matches WHERE active = 1 ORDER BY sort_order ASC, id DESC')->fetchAll();
}

function fta_live_match_payload(array $match, ?callable $urlResolver = null): array
{
    $urlResolver ??= static fn (string $url): string => $url;
    $streams = [];
    $decoded = json_decode((string) ($match['streams_json'] ?? ''), true);
    if (is_array($decoded)) {
        foreach ($decoded as $index => $stream) {
            if (!is_array($stream)) {
                continue;
            }
            $url = fta_clean_stream_url((string) ($stream['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $label = trim((string) ($stream['label'] ?? ''));
            $streams[] = [
                'name' => $label !== '' ? $label : 'Stream ' . ($index + 1),
                'label' => $label !== '' ? $label : 'Stream ' . ($index + 1),
                'url' => $url,
            ];
        }
    }

    return [
        'id' => 'manual-' . (int) ($match['id'] ?? 0),
        'league_name' => trim((string) ($match['league_name'] ?? '')),
        'match_time' => trim((string) ($match['match_time'] ?? '')),
        'status' => trim((string) ($match['status_text'] ?? '')),
        'is_live' => !empty($match['is_live']),
        'home_team' => [
            'name' => trim((string) ($match['home_name'] ?? 'Home')),
            'logo' => $urlResolver(trim((string) ($match['home_logo'] ?? ''))),
        ],
        'away_team' => [
            'name' => trim((string) ($match['away_name'] ?? 'Away')),
            'logo' => $urlResolver(trim((string) ($match['away_logo'] ?? ''))),
        ],
        'streams' => $streams,
    ];
}

function fta_all_notifications(int $limit = 100): array
{
    $statement = db()->prepare('SELECT * FROM notifications ORDER BY id DESC LIMIT :limit');
    $statement->bindValue('limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function fta_active_notifications(int $limit = 50): array
{
    $statement = db()->prepare('SELECT * FROM notifications WHERE active = 1 ORDER BY id DESC LIMIT :limit');
    $statement->bindValue('limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function fta_notification_payload(array $notification): array
{
    return [
        'id' => (int) ($notification['id'] ?? 0),
        'title' => trim((string) ($notification['title'] ?? '')),
        'body' => trim((string) ($notification['body'] ?? '')),
        'created_at' => trim((string) ($notification['created_at'] ?? '')),
    ];
}

function fta_image_src(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return fta_logo_url();
    }

    $version = is_file(fta_project_path($path)) ? '?v=' . filemtime(fta_project_path($path)) : '';
    return fta_base_url($path) . $version;
}

function fta_auth_countries(): array
{
    return [
        'my' => ['label' => 'Myanmar', 'dial' => '95', 'min' => 7, 'max' => 10],
        'jp' => ['label' => 'Japan', 'dial' => '81', 'min' => 9, 'max' => 10],
        'th' => ['label' => 'Thailand', 'dial' => '66', 'min' => 8, 'max' => 9],
    ];
}

function fta_auth_is_secure_context(): bool
{
    return fta_is_https() || fta_is_local_request();
}

function fta_require_https_for_auth(bool $api = false): void
{
    if (fta_auth_is_secure_context()) {
        return;
    }

    if ($api) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Login requires HTTPS.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    if ($host !== '') {
        fta_redirect('https://' . $host . $uri);
    }

    throw new RuntimeException('Login requires HTTPS.');
}

function fta_password_hash_secure(string $password): string
{
    if (defined('PASSWORD_ARGON2ID')) {
        $hash = password_hash($password, PASSWORD_ARGON2ID);
        if (is_string($hash) && $hash !== '') {
            return $hash;
        }
    }

    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function fta_password_needs_rehash_secure(string $hash): bool
{
    if (defined('PASSWORD_ARGON2ID') && str_starts_with($hash, '$argon2id$')) {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID);
    }

    return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
}

function fta_normalize_phone(string $country, string $phone): ?array
{
    $country = strtolower(trim($country));
    $countries = fta_auth_countries();
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if ($digits === '') {
        return null;
    }

    foreach ($countries as $code => $details) {
        $dial = (string) $details['dial'];
        if (str_starts_with($digits, '00' . $dial) || str_starts_with($digits, $dial)) {
            $country = $code;
            break;
        }
    }

    if (str_starts_with($digits, '09')) {
        $country = 'my';
    }

    if (!isset($countries[$country])) {
        $country = 'my';
    }

    $dial = $countries[$country]['dial'];
    if (str_starts_with($digits, '00' . $dial)) {
        $digits = substr($digits, strlen('00' . $dial));
    } elseif (str_starts_with($digits, $dial)) {
        $digits = substr($digits, strlen($dial));
    }

    $digits = ltrim($digits, '0');
    $length = strlen($digits);
    if ($length < $countries[$country]['min'] || $length > $countries[$country]['max']) {
        return null;
    }

    return [
        'country' => $country,
        'national' => $digits,
        'e164' => '+' . $dial . $digits,
        'display' => '+' . $dial . ' ' . $digits,
    ];
}

function fta_auth_public_user(array $user): array
{
    $agentId = (int) ($user['agent_id'] ?? 0);
    $agentName = '';
    $promoCode = (string) ($user['promo_code_used'] ?? '');
    if ($agentId > 0 && function_exists('fta_staff_by_id')) {
        $agent = fta_staff_by_id($agentId);
        if ($agent) {
            $agentName = fta_staff_display_name($agent);
            $promoCode = $promoCode ?: (string) ($agent['promo_code'] ?? '');
        }
    }

    return [
        'id' => (int) ($user['id'] ?? 0),
        'agent_id' => $agentId,
        'agent_name' => $agentName,
        'promo_code_used' => $promoCode,
        'full_name' => (string) ($user['full_name'] ?? ''),
        'profile_image_url' => fta_user_profile_image_url($user),
        'phone_country' => (string) ($user['phone_country'] ?? ''),
        'phone_number' => (string) ($user['phone_number'] ?? ''),
        'phone_e164' => (string) ($user['phone_e164'] ?? ''),
    ];
}

function fta_user_profile_image_url(?array $user): string
{
    $path = trim((string) ($user['profile_image_path'] ?? ''));
    return $path === '' ? '' : fta_image_src($path);
}

function fta_user_by_phone(string $phoneE164): ?array
{
    $statement = db()->prepare('SELECT * FROM users WHERE phone_e164 = :phone_e164 LIMIT 1');
    $statement->execute(['phone_e164' => $phoneE164]);
    $user = $statement->fetch();
    return is_array($user) ? $user : null;
}

function fta_user_by_phone_data(array $phone): ?array
{
    $local = '0' . (string) ($phone['national'] ?? '');
    $statement = db()->prepare('
        SELECT *
        FROM users
        WHERE phone_e164 = :phone_e164
           OR (phone_country = :phone_country AND phone_number IN (:national, :local))
        LIMIT 1
    ');
    $statement->execute([
        'phone_e164' => (string) ($phone['e164'] ?? ''),
        'phone_country' => (string) ($phone['country'] ?? ''),
        'national' => (string) ($phone['national'] ?? ''),
        'local' => $local,
    ]);
    $user = $statement->fetch();
    return is_array($user) ? $user : null;
}

function fta_user_by_id(int $id): ?array
{
    $statement = db()->prepare('SELECT * FROM users WHERE id = :id AND active = 1 LIMIT 1');
    $statement->execute(['id' => $id]);
    $user = $statement->fetch();
    return is_array($user) ? $user : null;
}

function fta_create_user(string $fullName, array $phone, string $password, ?array $agent = null): array
{
    $statement = db()->prepare('
        INSERT INTO users (agent_id, promo_code_used, full_name, phone_country, phone_number, phone_e164, password_hash)
        VALUES (:agent_id, :promo_code_used, :full_name, :phone_country, :phone_number, :phone_e164, :password_hash)
    ');
    $statement->execute([
        'agent_id' => $agent ? (int) ($agent['id'] ?? 0) : null,
        'promo_code_used' => $agent ? strtoupper((string) ($agent['promo_code'] ?? '')) : null,
        'full_name' => $fullName,
        'phone_country' => $phone['country'],
        'phone_number' => $phone['national'],
        'phone_e164' => $phone['e164'],
        'password_hash' => fta_password_hash_secure($password),
    ]);

    return fta_user_by_id((int) db()->lastInsertId()) ?? [];
}

function fta_auth_identifier(string $action, string $identifier): array
{
    return [$action, hash('sha256', strtolower($identifier) . '|' . fta_client_ip())];
}

function fta_auth_rate_wait(int $attempts): int
{
    if ($attempts <= 1) {
        return 15;
    }
    if ($attempts === 2) {
        return 30;
    }
    return 300;
}

function fta_auth_rate_assert(string $action, string $identifier): void
{
    [$scope, $hash] = fta_auth_identifier($action, $identifier);
    $statement = db()->prepare('SELECT locked_until FROM auth_rate_limits WHERE scope = :scope AND identifier_hash = :hash LIMIT 1');
    $statement->execute(['scope' => $scope, 'hash' => $hash]);
    $row = $statement->fetch();
    if (!$row || empty($row['locked_until'])) {
        return;
    }

    $lockedUntil = strtotime((string) $row['locked_until']);
    if ($lockedUntil !== false && $lockedUntil > time()) {
        $seconds = max(1, $lockedUntil - time());
        throw new RuntimeException(str_replace('{seconds}', (string) $seconds, t('rate_limit_message', 'Please wait {seconds}s before trying again.')));
    }
}

function fta_auth_rate_fail(string $action, string $identifier): void
{
    [$scope, $hash] = fta_auth_identifier($action, $identifier);
    $statement = db()->prepare('SELECT attempts FROM auth_rate_limits WHERE scope = :scope AND identifier_hash = :hash LIMIT 1');
    $statement->execute(['scope' => $scope, 'hash' => $hash]);
    $row = $statement->fetch();
    $attempts = (int) (($row['attempts'] ?? 0) + 1);
    $lockedUntil = date('Y-m-d H:i:s', time() + fta_auth_rate_wait($attempts));

    $upsert = db()->prepare('
        INSERT INTO auth_rate_limits (scope, identifier_hash, attempts, locked_until)
        VALUES (:scope, :hash, :attempts, :locked_until)
        ON DUPLICATE KEY UPDATE attempts = :attempts, locked_until = :locked_until
    ');
    $upsert->execute([
        'scope' => $scope,
        'hash' => $hash,
        'attempts' => $attempts,
        'locked_until' => $lockedUntil,
    ]);
}

function fta_auth_rate_clear(string $action, string $identifier): void
{
    [$scope, $hash] = fta_auth_identifier($action, $identifier);
    $statement = db()->prepare('DELETE FROM auth_rate_limits WHERE scope = :scope AND identifier_hash = :hash');
    $statement->execute(['scope' => $scope, 'hash' => $hash]);
}

function fta_action_rate_limit(string $action, string $identifier, int $seconds = 15): void
{
    $seconds = max(5, min(600, $seconds));
    [$scope, $hash] = fta_auth_identifier('action_' . $action, $identifier);
    $statement = db()->prepare('SELECT locked_until FROM auth_rate_limits WHERE scope = :scope AND identifier_hash = :hash LIMIT 1');
    $statement->execute(['scope' => $scope, 'hash' => $hash]);
    $row = $statement->fetch();
    $lockedUntil = $row ? strtotime((string) ($row['locked_until'] ?? '')) : false;
    if ($lockedUntil !== false && $lockedUntil > time()) {
        $wait = max(1, $lockedUntil - time());
        throw new RuntimeException(str_replace('{seconds}', (string) $wait, t('rate_limit_message', 'Please wait {seconds}s before trying again.')));
    }

    $upsert = db()->prepare('
        INSERT INTO auth_rate_limits (scope, identifier_hash, attempts, locked_until)
        VALUES (:scope, :hash, 1, :locked_until)
        ON DUPLICATE KEY UPDATE attempts = attempts + 1, locked_until = VALUES(locked_until)
    ');
    $upsert->execute([
        'scope' => $scope,
        'hash' => $hash,
        'locked_until' => date('Y-m-d H:i:s', time() + $seconds),
    ]);
}

function fta_user_safe_error_message(Throwable|string $error): string
{
    $message = $error instanceof Throwable ? $error->getMessage() : (string) $error;
    $message = trim($message);
    if ($message === '') {
        return t('request_failed', 'Request failed. Please try again.');
    }

    if (preg_match('/\b(SQLSTATE|PDO|SELECT|INSERT|UPDATE|DELETE|stack trace|token_hash|password_hash|provider_last_error|debug_reference)\b/i', $message)) {
        return t('request_failed', 'Request failed. Please try again.');
    }

    return $message;
}

function fta_session_location_from_input(array $input): array
{
    $source = is_array($input['location'] ?? null) ? $input['location'] : $input;
    $lat = isset($source['latitude']) ? (float) $source['latitude'] : (isset($source['location_lat']) ? (float) $source['location_lat'] : null);
    $lng = isset($source['longitude']) ? (float) $source['longitude'] : (isset($source['location_lng']) ? (float) $source['location_lng'] : null);
    $accuracy = isset($source['accuracy']) ? (float) $source['accuracy'] : (isset($source['location_accuracy']) ? (float) $source['location_accuracy'] : null);
    $label = trim((string) ($source['label'] ?? $source['location_label'] ?? ''));

    if ($lat !== null && ($lat < -90 || $lat > 90)) {
        $lat = null;
    }
    if ($lng !== null && ($lng < -180 || $lng > 180)) {
        $lng = null;
    }
    if ($accuracy !== null && ($accuracy < 0 || $accuracy > 1000000)) {
        $accuracy = null;
    }
    if ($label === '' && $lat !== null && $lng !== null) {
        $label = number_format($lat, 5, '.', '') . ', ' . number_format($lng, 5, '.', '');
    }

    return [
        'latitude' => $lat,
        'longitude' => $lng,
        'accuracy' => $accuracy,
        'label' => substr($label, 0, 255),
    ];
}

function fta_user_session_payload(array $session, string $currentSelector = ''): array
{
    $userAgent = (string) ($session['user_agent'] ?? '');
    $lat = $session['location_lat'] ?? null;
    $lng = $session['location_lng'] ?? null;
    $accuracy = $session['location_accuracy'] ?? null;
    return [
        'id' => (int) ($session['id'] ?? 0),
        'type' => (string) ($session['session_type'] ?? ''),
        'ip_address' => (string) ($session['ip_address'] ?? ''),
        'location_label' => (string) ($session['location_label'] ?? ''),
        'location_lat' => $lat === null || $lat === '' ? null : (float) $lat,
        'location_lng' => $lng === null || $lng === '' ? null : (float) $lng,
        'location_accuracy' => $accuracy === null || $accuracy === '' ? null : (float) $accuracy,
        'device' => fta_parse_os($userAgent),
        'user_agent' => $userAgent,
        'created_at' => (string) ($session['created_at'] ?? ''),
        'last_used_at' => (string) ($session['last_used_at'] ?? ''),
        'expires_at' => (string) ($session['expires_at'] ?? ''),
        'current' => $currentSelector !== '' && hash_equals($currentSelector, (string) ($session['selector'] ?? '')),
    ];
}

function fta_user_sessions(array $user, string $currentToken = ''): array
{
    $currentSelector = '';
    if ($currentToken !== '' && str_contains($currentToken, ':')) {
        [$currentSelector] = explode(':', $currentToken, 2);
    }

    $statement = db()->prepare('
        SELECT id, selector, session_type, ip_address, user_agent, location_lat, location_lng, location_accuracy, location_label, created_at, last_used_at, expires_at
        FROM user_sessions
        WHERE user_id = :user_id
          AND revoked_at IS NULL
          AND expires_at > NOW()
        ORDER BY COALESCE(last_used_at, created_at) DESC
        LIMIT 20
    ');
    $statement->execute(['user_id' => (int) ($user['id'] ?? 0)]);
    return array_map(static fn (array $session): array => fta_user_session_payload($session, $currentSelector), $statement->fetchAll());
}

function fta_create_user_session(array $user, string $type, int $ttlSeconds, array $locationInput = []): string
{
    $selector = bin2hex(random_bytes(16));
    $token = bin2hex(random_bytes(32));
    $location = fta_session_location_from_input($locationInput);
    $statement = db()->prepare('
        INSERT INTO user_sessions (
            user_id, selector, token_hash, session_type, ip_address, user_agent,
            location_lat, location_lng, location_accuracy, location_label, expires_at
        )
        VALUES (
            :user_id, :selector, :token_hash, :session_type, :ip_address, :user_agent,
            :location_lat, :location_lng, :location_accuracy, :location_label, :expires_at
        )
    ');
    $statement->execute([
        'user_id' => (int) $user['id'],
        'selector' => $selector,
        'token_hash' => hash('sha256', $token),
        'session_type' => $type,
        'ip_address' => fta_client_ip(),
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        'location_lat' => $location['latitude'],
        'location_lng' => $location['longitude'],
        'location_accuracy' => $location['accuracy'],
        'location_label' => $location['label'],
        'expires_at' => date('Y-m-d H:i:s', time() + $ttlSeconds),
    ]);

    return $selector . ':' . $token;
}

function fta_user_from_session_token(string $rawToken, string $type): ?array
{
    if (!str_contains($rawToken, ':')) {
        return null;
    }

    [$selector, $token] = explode(':', $rawToken, 2);
    if (!preg_match('/^[a-f0-9]{32}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $statement = db()->prepare('
        SELECT u.*
        FROM user_sessions s
        INNER JOIN users u ON u.id = s.user_id
        WHERE s.selector = :selector
          AND s.session_type = :session_type
          AND s.revoked_at IS NULL
          AND s.expires_at > NOW()
          AND u.active = 1
          AND s.token_hash = :token_hash
        LIMIT 1
    ');
    $statement->execute([
        'selector' => $selector,
        'session_type' => $type,
        'token_hash' => hash('sha256', $token),
    ]);
    $user = $statement->fetch();
    if (!$user) {
        return null;
    }

    $touch = db()->prepare('UPDATE user_sessions SET last_used_at = NOW() WHERE selector = :selector');
    $touch->execute(['selector' => $selector]);
    return $user;
}

function fta_remember_cookie_name(): string
{
    return 'fta_user_remember';
}

function fta_set_remember_cookie(string $token, int $ttlSeconds): void
{
    setcookie(fta_remember_cookie_name(), $token, [
        'expires' => time() + $ttlSeconds,
        'path' => '/',
        'secure' => fta_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function fta_clear_remember_cookie(): void
{
    setcookie(fta_remember_cookie_name(), '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => fta_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function fta_login_user(array $user, bool $remember, array $locationInput = []): void
{
    fta_start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];

    $statement = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
    $statement->execute(['id' => (int) $user['id']]);

    $ttl = $remember ? 60 * 60 * 24 * 30 : 60 * 60 * 24;
    $token = fta_create_user_session($user, $remember ? 'web_remember' : 'web_session', $ttl, $locationInput);
    $_SESSION['user_session_token'] = $token;
    if ($remember) {
        fta_set_remember_cookie($token, $ttl);
    }
}

function fta_current_user(): ?array
{
    fta_start_session();
    if (!empty($_SESSION['user_id'])) {
        $sessionToken = (string) ($_SESSION['user_session_token'] ?? '');
        if ($sessionToken !== '') {
            $sessionUser = fta_user_from_session_token($sessionToken, 'web_session') ?: fta_user_from_session_token($sessionToken, 'web_remember');
            if (!$sessionUser || (int) ($sessionUser['id'] ?? 0) !== (int) $_SESSION['user_id']) {
                unset($_SESSION['user_id'], $_SESSION['user_session_token']);
                fta_clear_remember_cookie();
                return null;
            }
            return $sessionUser;
        }

        return fta_user_by_id((int) $_SESSION['user_id']);
    }

    $remember = (string) ($_COOKIE[fta_remember_cookie_name()] ?? '');
    if ($remember !== '') {
        $user = fta_user_from_session_token($remember, 'web_remember');
        if ($user) {
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_session_token'] = $remember;
            return $user;
        }
        fta_clear_remember_cookie();
    }

    return null;
}

function fta_logout_user(): void
{
    fta_start_session();
    $sessionToken = (string) ($_SESSION['user_session_token'] ?? '');
    unset($_SESSION['user_id']);
    unset($_SESSION['user_session_token']);
    if ($sessionToken !== '' && str_contains($sessionToken, ':')) {
        [$selector] = explode(':', $sessionToken, 2);
        $statement = db()->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE selector = :selector');
        $statement->execute(['selector' => $selector]);
    }
    $remember = (string) ($_COOKIE[fta_remember_cookie_name()] ?? '');
    if ($remember !== '' && str_contains($remember, ':')) {
        [$selector] = explode(':', $remember, 2);
        $statement = db()->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE selector = :selector');
        $statement->execute(['selector' => $selector]);
    }
    fta_clear_remember_cookie();
    session_regenerate_id(true);
}

function fta_api_bearer_token(): string
{
    $headers = [
        (string) ($_SERVER['HTTP_X_FTA_AUTH'] ?? ''),
        (string) ($_SERVER['HTTP_X_AUTHORIZATION'] ?? ''),
        (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''),
        (string) ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''),
    ];

    if (function_exists('apache_request_headers')) {
        foreach ((array) apache_request_headers() as $name => $value) {
            $lower = strtolower((string) $name);
            if (in_array($lower, ['authorization', 'x-fta-auth', 'x-authorization'], true)) {
                $headers[] = (string) $value;
            }
        }
    }

    foreach ($headers as $header) {
        $header = trim($header);
        if ($header === '') {
            continue;
        }
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return trim($matches[1]);
        }
        if (preg_match('/^[a-f0-9]{32}:[a-f0-9]{64}$/i', $header)) {
            return $header;
        }
    }

    return '';
}

function fta_api_current_user(): ?array
{
    $token = fta_api_bearer_token();
    return $token === '' ? null : fta_user_from_session_token($token, 'api');
}

function fta_revoke_session_token(string $rawToken, string $type): void
{
    if (!str_contains($rawToken, ':')) {
        return;
    }

    [$selector] = explode(':', $rawToken, 2);
    if (!preg_match('/^[a-f0-9]{32}$/', $selector)) {
        return;
    }

    $statement = db()->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE selector = :selector AND session_type = :session_type');
    $statement->execute(['selector' => $selector, 'session_type' => $type]);
}

function fta_revoke_user_session(array $user, int $sessionId, string $currentToken = ''): void
{
    if ($sessionId <= 0) {
        throw new RuntimeException(t('request_failed', 'Request failed. Please try again.'));
    }
    $currentSelector = '';
    if ($currentToken !== '' && str_contains($currentToken, ':')) {
        [$currentSelector] = explode(':', $currentToken, 2);
    }

    $statement = db()->prepare('SELECT selector FROM user_sessions WHERE id = :id AND user_id = :user_id AND revoked_at IS NULL LIMIT 1');
    $statement->execute([
        'id' => $sessionId,
        'user_id' => (int) ($user['id'] ?? 0),
    ]);
    $row = $statement->fetch();
    if (!$row) {
        throw new RuntimeException(t('request_failed', 'Request failed. Please try again.'));
    }
    if ($currentSelector !== '' && hash_equals($currentSelector, (string) ($row['selector'] ?? ''))) {
        throw new RuntimeException(t('cannot_sign_out_current_device', 'You cannot sign out the current device here.'));
    }

    $update = db()->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE id = :id AND user_id = :user_id');
    $update->execute([
        'id' => $sessionId,
        'user_id' => (int) ($user['id'] ?? 0),
    ]);
}

function fta_auth_signup(array $input): array
{
    $fullName = trim((string) ($input['full_name'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $confirm = (string) ($input['confirm_password'] ?? '');
    $promoCode = strtoupper(trim((string) ($input['promo_code'] ?? '')));
    $phone = fta_normalize_phone((string) ($input['phone_country'] ?? ''), (string) ($input['phone_number'] ?? ''));
    $nameLength = function_exists('mb_strlen') ? mb_strlen($fullName) : strlen($fullName);

    if ($nameLength < 2 || $nameLength > 140) {
        throw new RuntimeException(t('auth_name_error', 'Please enter your full name.'));
    }
    if (!$phone) {
        throw new RuntimeException(t('auth_phone_error', 'Please enter a valid Myanmar, Japan, or Thailand phone number.'));
    }
    if (strlen($password) < 8) {
        throw new RuntimeException(t('auth_password_error', 'Password must be at least 8 characters.'));
    }
    if (!hash_equals($password, $confirm)) {
        throw new RuntimeException(t('auth_confirm_error', 'Passwords do not match.'));
    }
    if ($promoCode === '') {
        throw new RuntimeException(t('promo_required', 'Promocode is required.'));
    }
    $agent = function_exists('fta_staff_by_promo_code') ? fta_staff_by_promo_code($promoCode) : null;
    if (!$agent) {
        throw new RuntimeException(t('promo_invalid', 'Promocode is invalid or expired.'));
    }

    fta_auth_rate_assert('signup', $phone['e164']);
    if (fta_user_by_phone_data($phone)) {
        fta_auth_rate_fail('signup', $phone['e164']);
        throw new RuntimeException(t('auth_phone_exists', 'This phone number is already registered.'));
    }

    try {
        $user = fta_create_user($fullName, $phone, $password, $agent);
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            fta_auth_rate_fail('signup', $phone['e164']);
            throw new RuntimeException(t('auth_phone_exists', 'This phone number is already registered.'));
        }
        throw $exception;
    }

    fta_auth_rate_clear('signup', $phone['e164']);
    return $user;
}

function fta_auth_login(array $input): array
{
    $phone = fta_normalize_phone((string) ($input['phone_country'] ?? ''), (string) ($input['phone_number'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $identifier = $phone['e164'] ?? fta_client_ip();

    fta_auth_rate_assert('login', $identifier);
    if (!$phone || $password === '') {
        fta_auth_rate_fail('login', $identifier);
        throw new RuntimeException(t('auth_invalid_login', 'Phone number or password is incorrect.'));
    }

    $user = fta_user_by_phone_data($phone);
    if (!$user || empty($user['active']) || !password_verify($password, (string) $user['password_hash'])) {
        fta_auth_rate_fail('login', $phone['e164']);
        throw new RuntimeException(t('auth_invalid_login', 'Phone number or password is incorrect.'));
    }

    if (fta_password_needs_rehash_secure((string) $user['password_hash'])) {
        $statement = db()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $statement->execute([
            'hash' => fta_password_hash_secure($password),
            'id' => (int) $user['id'],
        ]);
    }

    fta_auth_rate_clear('login', $phone['e164']);
    $statement = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
    $statement->execute(['id' => (int) $user['id']]);
    return $user;
}

function fta_change_user_password(array $user, array $input): void
{
    fta_action_rate_limit('change_password', (string) ($user['id'] ?? fta_client_ip()), 30);
    $oldPassword = (string) ($input['old_password'] ?? '');
    $newPassword = (string) ($input['new_password'] ?? '');
    $confirmPassword = (string) ($input['confirm_password'] ?? '');
    $freshUser = fta_user_by_id((int) ($user['id'] ?? 0));

    if (!$freshUser) {
        throw new RuntimeException(t('auth_session_expired', 'Please login again.'));
    }
    if ($oldPassword === '' || !password_verify($oldPassword, (string) ($freshUser['password_hash'] ?? ''))) {
        throw new RuntimeException(t('old_password_error', 'Old password is incorrect.'));
    }
    if (strlen($newPassword) < 8) {
        throw new RuntimeException(t('new_password_error', 'New password must be at least 8 characters.'));
    }
    if (!hash_equals($newPassword, $confirmPassword)) {
        throw new RuntimeException(t('auth_confirm_error', 'Passwords do not match.'));
    }

    $statement = db()->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id');
    $statement->execute([
        'hash' => fta_password_hash_secure($newPassword),
        'id' => (int) $freshUser['id'],
    ]);
}

function fta_api_auth_payload(array $user, bool $remember, array $locationInput = []): array
{
    $ttl = $remember ? 60 * 60 * 24 * 30 : 60 * 60 * 24 * 7;
    return [
        'status' => 'success',
        'message' => t('auth_login_success', 'Login successful.'),
        'token' => fta_create_user_session($user, 'api', $ttl, $locationInput),
        'user' => fta_auth_public_user($user),
        'expires_in' => $ttl,
    ];
}

function fta_csrf_token(): string
{
    fta_start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function fta_check_csrf(): void
{
    fta_start_session();
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        throw new RuntimeException('Your session expired. Please try again.');
    }
}

function fta_flash(string $type, string $message): void
{
    fta_start_session();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function fta_take_flashes(): array
{
    fta_start_session();
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function fta_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function fta_client_ip(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        $ip = trim(explode(',', $candidate)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return '0.0.0.0';
}

function fta_parse_os(string $ua): string
{
    $patterns = [
        ['/Windows NT 10\.0/i', 'Windows 10/11'],
        ['/Windows NT 6\.3/i', 'Windows 8.1'],
        ['/Windows NT 6\.2/i', 'Windows 8'],
        ['/Windows NT 6\.1/i', 'Windows 7'],
        ['/Android ([0-9\.]+)/i', 'Android %s'],
        ['/iPhone OS ([0-9_]+)/i', 'iOS %s'],
        ['/CPU OS ([0-9_]+)/i', 'iPadOS %s'],
        ['/Mac OS X ([0-9_\.]+)/i', 'macOS %s'],
        ['/Linux/i', 'Linux'],
    ];

    foreach ($patterns as [$pattern, $label]) {
        if (preg_match($pattern, $ua, $matches)) {
            return isset($matches[1]) ? sprintf($label, str_replace('_', '.', $matches[1])) : $label;
        }
    }

    return 'Unknown';
}

function fta_form_is_open(array $settings): bool
{
    if (($settings['form_open'] ?? '0') !== '1') {
        return false;
    }

    $now = time();
    $start = trim($settings['form_start_at'] ?? '');
    $end = trim($settings['form_end_at'] ?? '');

    if ($start !== '' && strtotime($start) !== false && $now < strtotime($start)) {
        return false;
    }

    if ($end !== '' && strtotime($end) !== false && $now > strtotime($end)) {
        return false;
    }

    return true;
}

function fta_upload_image(string $field, string $folder): string
{
    if (empty($_FILES[$field]) || (int) $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    return fta_save_uploaded_image($_FILES[$field], $folder);
}

function fta_upload_image_from_array(string $field, int $key, string $folder): string
{
    if (empty($_FILES[$field]) || !isset($_FILES[$field]['error'][$key])) {
        return '';
    }

    $file = [
        'name' => $_FILES[$field]['name'][$key] ?? '',
        'type' => $_FILES[$field]['type'][$key] ?? '',
        'tmp_name' => $_FILES[$field]['tmp_name'][$key] ?? '',
        'error' => $_FILES[$field]['error'][$key] ?? UPLOAD_ERR_NO_FILE,
        'size' => $_FILES[$field]['size'][$key] ?? 0,
    ];

    if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    return fta_save_uploaded_image($file, $folder);
}

function fta_save_uploaded_image(array $file, string $folder, int $maxBytes = 6291456): string
{
    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed.');
    }

    if ((int) $file['size'] > $maxBytes) {
        $maxMb = max(1, (int) ceil($maxBytes / 1024 / 1024));
        throw new RuntimeException('Image is too large. Maximum size is ' . $maxMb . ' MB.');
    }

    $info = @getimagesize((string) $file['tmp_name']);
    if (!$info || empty($info['mime'])) {
        throw new RuntimeException('Please upload a valid image file.');
    }

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    $mime = $info['mime'];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Only JPG, PNG, WEBP, and GIF images are supported.');
    }

    $relativeFolder = 'uploads/' . trim($folder, '/');
    $targetFolder = fta_project_path($relativeFolder);
    if (!is_dir($targetFolder) && !mkdir($targetFolder, 0775, true) && !is_dir($targetFolder)) {
        throw new RuntimeException('Upload folder could not be created.');
    }

    $filename = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extensions[$mime];
    $target = $targetFolder . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        throw new RuntimeException('Could not save uploaded image.');
    }

    return $relativeFolder . '/' . $filename;
}

function fta_save_image_binary(string $binary, string $folder, int $maxBytes = 6291456): string
{
    if ($binary === '') {
        throw new RuntimeException('Please upload a valid image file.');
    }
    if (strlen($binary) > $maxBytes) {
        $maxMb = max(1, (int) ceil($maxBytes / 1024 / 1024));
        throw new RuntimeException('Image is too large. Maximum size is ' . $maxMb . ' MB.');
    }

    $info = @getimagesizefromstring($binary);
    if (!$info || empty($info['mime'])) {
        throw new RuntimeException('Please upload a valid image file.');
    }

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $mime = (string) $info['mime'];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Only JPG, PNG, WEBP, and GIF images are supported.');
    }

    $relativeFolder = 'uploads/' . trim($folder, '/');
    $targetFolder = fta_project_path($relativeFolder);
    if (!is_dir($targetFolder) && !mkdir($targetFolder, 0775, true) && !is_dir($targetFolder)) {
        throw new RuntimeException('Upload folder could not be created.');
    }

    $filename = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extensions[$mime];
    file_put_contents($targetFolder . DIRECTORY_SEPARATOR . $filename, $binary, LOCK_EX);
    return $relativeFolder . '/' . $filename;
}

function fta_update_user_profile(array $user, array $input, string $imagePath = ''): array
{
    fta_action_rate_limit('update_profile', (string) ($user['id'] ?? fta_client_ip()), 15);
    $freshUser = fta_user_by_id((int) ($user['id'] ?? 0));
    if (!$freshUser) {
        throw new RuntimeException(t('auth_session_expired', 'Please login again.'));
    }

    $fullName = trim((string) ($input['full_name'] ?? $freshUser['full_name'] ?? ''));
    $nameLength = function_exists('mb_strlen') ? mb_strlen($fullName) : strlen($fullName);
    if ($nameLength < 2 || $nameLength > 140) {
        throw new RuntimeException(t('auth_name_error', 'Please enter your full name.'));
    }

    $oldImage = (string) ($freshUser['profile_image_path'] ?? '');
    $removeImage = !empty($input['remove_profile_image']);
    $nextImage = $removeImage ? '' : $oldImage;
    if ($imagePath !== '') {
        $nextImage = $imagePath;
    }

    $statement = db()->prepare('
        UPDATE users
        SET full_name = :full_name,
            profile_image_path = :profile_image_path,
            updated_at = NOW()
        WHERE id = :id
    ');
    $statement->execute([
        'full_name' => $fullName,
        'profile_image_path' => $nextImage === '' ? null : $nextImage,
        'id' => (int) $freshUser['id'],
    ]);

    if (($removeImage || $imagePath !== '') && $oldImage !== '' && $oldImage !== $nextImage) {
        fta_delete_upload($oldImage);
    }

    return fta_user_by_id((int) $freshUser['id']) ?? $freshUser;
}

function fta_remove_user_profile_image(array $user): array
{
    return fta_update_user_profile($user, [
        'full_name' => (string) ($user['full_name'] ?? ''),
        'remove_profile_image' => '1',
    ]);
}

function fta_delete_upload(?string $relativePath): void
{
    $relativePath = trim((string) $relativePath);
    if ($relativePath === '' || !str_starts_with($relativePath, 'uploads/')) {
        return;
    }

    $path = realpath(fta_project_path($relativePath));
    $uploads = realpath(fta_project_path('uploads'));
    if ($path && $uploads && str_starts_with($path, $uploads) && is_file($path)) {
        @unlink($path);
    }
}

function fta_public_id(): string
{
    return 'FTA-' . strtoupper(bin2hex(random_bytes(4)));
}

function fta_submission_exists(?string $storageId, string $ip, string $ua): bool
{
    if ($storageId !== null && $storageId !== '') {
        $statement = db()->prepare('SELECT id FROM submissions WHERE storage_id = :storage_id LIMIT 1');
        $statement->execute(['storage_id' => $storageId]);
        if ($statement->fetch()) {
            return true;
        }
    }

    $statement = db()->prepare('SELECT id FROM submissions WHERE ip_address = :ip AND user_agent = :ua LIMIT 1');
    $statement->execute(['ip' => $ip, 'ua' => $ua]);
    return (bool) $statement->fetch();
}

function fta_submit_prediction(array $settings): array
{
    fta_check_csrf();
    $user = fta_current_user();

    if (!fta_form_is_open($settings)) {
        return ['ok' => false, 'message' => t('form_closed_message')];
    }

    $required = ['ht_result', 'ft_result', 'first_scorer', 'wallet_type', 'wallet_name', 'wallet_number'];
    foreach ($required as $field) {
        if (trim((string) ($_POST[$field] ?? '')) === '') {
            return ['ok' => false, 'message' => t('required_message')];
        }
    }

    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $ip = fta_client_ip();
    $storageId = trim((string) ($_POST['storage_id'] ?? ''));
    $storageId = $storageId === '' ? null : substr($storageId, 0, 128);

    if (fta_submission_exists($storageId, $ip, $ua)) {
        return ['ok' => false, 'message' => t('already_submitted')];
    }

    $deviceJson = (string) ($_POST['device_json'] ?? '{}');
    if (strlen($deviceJson) > 60000) {
        $deviceJson = substr($deviceJson, 0, 60000);
    }

    $statement = db()->prepare('
        INSERT INTO submissions (
            public_id, user_id, storage_id, ip_address, user_agent, os_version, browser_language, screen_size,
            device_json, ht_result, ft_result, first_scorer, wallet_type, wallet_name, wallet_number
        ) VALUES (
            :public_id, :user_id, :storage_id, :ip_address, :user_agent, :os_version, :browser_language, :screen_size,
            :device_json, :ht_result, :ft_result, :first_scorer, :wallet_type, :wallet_name, :wallet_number
        )
    ');

    try {
        $statement->execute([
            'public_id' => fta_public_id(),
            'user_id' => $user ? (int) $user['id'] : null,
            'storage_id' => $storageId,
            'ip_address' => $ip,
            'user_agent' => $ua,
            'os_version' => fta_parse_os($ua),
            'browser_language' => substr((string) ($_POST['browser_language'] ?? ''), 0, 80),
            'screen_size' => substr((string) ($_POST['screen_size'] ?? ''), 0, 80),
            'device_json' => $deviceJson,
            'ht_result' => substr(trim((string) $_POST['ht_result']), 0, 50),
            'ft_result' => substr(trim((string) $_POST['ft_result']), 0, 50),
            'first_scorer' => substr(trim((string) $_POST['first_scorer']), 0, 120),
            'wallet_type' => substr(trim((string) $_POST['wallet_type']), 0, 30),
            'wallet_name' => substr(trim((string) $_POST['wallet_name']), 0, 120),
            'wallet_number' => substr(trim((string) $_POST['wallet_number']), 0, 80),
        ]);
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            return ['ok' => false, 'message' => t('already_submitted')];
        }
        throw $exception;
    }

    return ['ok' => true, 'message' => t('success_message')];
}

function fta_submissions(int $limit = 200): array
{
    $statement = db()->prepare('
        SELECT s.*, u.full_name AS user_full_name, u.phone_e164 AS user_phone_e164
        FROM submissions s
        LEFT JOIN users u ON u.id = s.user_id
        ORDER BY s.created_at DESC
        LIMIT :limit
    ');
    $statement->bindValue('limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function fta_prediction_history(?array $user, ?string $storageId = null, int $limit = 50): array
{
    $limit = max(1, min(100, $limit));
    if ($user) {
        $statement = db()->prepare('
            SELECT *
            FROM submissions
            WHERE user_id = :user_id
               OR (storage_id IS NOT NULL AND storage_id <> "" AND storage_id = :storage_id)
            ORDER BY created_at DESC
            LIMIT :limit
        ');
        $statement->bindValue('user_id', (int) $user['id'], PDO::PARAM_INT);
        $statement->bindValue('storage_id', (string) ($storageId ?? ''), PDO::PARAM_STR);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    if ($storageId !== null && $storageId !== '') {
        $statement = db()->prepare('
            SELECT *
            FROM submissions
            WHERE storage_id = :storage_id
            ORDER BY created_at DESC
            LIMIT :limit
        ');
        $statement->bindValue('storage_id', $storageId, PDO::PARAM_STR);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    return [];
}

function fta_prediction_history_payload(array $submission): array
{
    $status = (string) ($submission['result_status'] ?? '');
    if (!in_array($status, ['pending', 'win', 'lose'], true)) {
        $status = !empty($submission['is_winner']) ? 'win' : 'pending';
    }

    return [
        'id' => (int) ($submission['id'] ?? 0),
        'public_id' => (string) ($submission['public_id'] ?? ''),
        'ht_result' => (string) ($submission['ht_result'] ?? ''),
        'ft_result' => (string) ($submission['ft_result'] ?? ''),
        'first_scorer' => (string) ($submission['first_scorer'] ?? ''),
        'wallet_type' => (string) ($submission['wallet_type'] ?? ''),
        'wallet_name' => (string) ($submission['wallet_name'] ?? ''),
        'wallet_number' => (string) ($submission['wallet_number'] ?? ''),
        'is_winner' => $status === 'win',
        'result' => $status,
        'status' => $status,
        'created_at' => (string) ($submission['created_at'] ?? ''),
    ];
}

function fta_all_users(int $limit = 300): array
{
    $statement = db()->prepare('
        SELECT u.*,
               COUNT(s.id) AS prediction_count,
               SUM(CASE WHEN s.result_status = "win" THEN 1 ELSE 0 END) AS win_count
        FROM users u
        LEFT JOIN submissions s ON s.user_id = u.id
        GROUP BY u.id
        ORDER BY u.created_at DESC
        LIMIT :limit
    ');
    $statement->bindValue('limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

require_once __DIR__ . '/agent_features.php';
