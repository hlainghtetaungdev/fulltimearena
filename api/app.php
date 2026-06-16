<?php
declare(strict_types=1);

$rawInput = file_get_contents('php://input') ?: '';
$jsonInput = json_decode($rawInput, true);
if (!is_array($jsonInput)) {
    $jsonInput = [];
}

$requestedLang = strtolower(trim((string) ($_GET['lang'] ?? $jsonInput['lang'] ?? 'en')));
if (!in_array($requestedLang, ['en', 'my', 'jp', 'th'], true)) {
    $requestedLang = 'en';
}

define('FTA_LANG', $requestedLang);

require_once __DIR__ . '/../app/layout.php';

fta_send_security_headers(true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization, X-FTA-Auth, X-Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function fta_app_response(int $status, array $payload): void
{
    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
    );
    if (!is_string($json)) {
        $status = 500;
        $json = '{"status":"error","message":"App API JSON encoding failed."}';
    }

    http_response_code($status);
    echo $json;
    exit;
}

function fta_app_origin(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return ($https ? 'https' : 'http') . '://' . $host;
}

function fta_app_absolute_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (preg_match('/^https?:\/\//i', $url)) {
        return $url;
    }
    return fta_app_origin() . '/' . ltrim($url, '/');
}

function fta_app_image_url(?string $path): string
{
    return fta_app_absolute_url(fta_image_src($path));
}

function fta_app_user_payload(array $user): array
{
    $payload = fta_auth_public_user($user);
    if (!empty($payload['profile_image_url'])) {
        $payload['profile_image_url'] = fta_app_absolute_url((string) $payload['profile_image_url']);
    }
    return $payload;
}

function fta_app_payment_account_payload(array $account): array
{
    $payload = fta_payment_account_payload($account);
    $payload['logo_url'] = fta_app_absolute_url((string) ($payload['logo_url'] ?? ''));
    return $payload;
}

function fta_app_payout_payload(array $user): array
{
    return array_map(static function (array $account): array {
        $account['logo_url'] = fta_app_absolute_url((string) ($account['logo_url'] ?? ''));
        return $account;
    }, fta_payout_payload($user));
}

function fta_app_clean_text(array $input, string $key, int $max): string
{
    return substr(trim((string) ($input[$key] ?? '')), 0, $max);
}

function fta_app_public_category(array $category): array
{
    $iconPath = trim((string) ($category['icon_path'] ?? ''));

    return [
        'id' => (int) ($category['id'] ?? 0),
        'name' => fta_category_display_name($category),
        'link_url' => fta_clean_link($category['link_url'] ?? ''),
        'icon' => fta_category_icon_name((string) ($category['name'] ?? '')),
        'icon_url' => $iconPath !== '' ? fta_app_image_url($iconPath) : '',
    ];
}

function fta_app_public_categories(): array
{
    $reservedLinks = ['news.php', 'inbox.php', 'more.php'];
    $reservedNames = ['news', 'mail', 'inbox', 'more'];
    $items = [];
    $user = fta_api_current_user() ?: fta_current_user();

    foreach (fta_active_categories_for_user($user) as $category) {
        $link = strtolower(trim((string) ($category['link_url'] ?? '')));
        $name = strtolower(trim((string) ($category['name'] ?? '')));

        if (in_array($link, $reservedLinks, true) || in_array($name, $reservedNames, true)) {
            continue;
        }

        $items[] = fta_app_public_category($category);
    }

    return $items;
}

function fta_app_public_ads(): array
{
    return array_map(static function (array $ad): array {
        return [
            'id' => (int) ($ad['id'] ?? 0),
            'image_url' => fta_app_image_url($ad['image_path'] ?? ''),
            'link_url' => fta_clean_link($ad['link_url'] ?? ''),
        ];
    }, fta_active_ads());
}

function fta_app_public_notifications(?array $user = null): array
{
    if (function_exists('fta_notifications_for_user')) {
        return array_map('fta_notification_payload', fta_notifications_for_user($user, 50));
    }

    return array_map('fta_notification_payload', fta_active_notifications(50));
}

function fta_app_submission_status(?string $storageId): bool
{
    return fta_submission_exists($storageId, fta_client_ip(), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

function fta_app_setting_int(array $settings, string $key, int $default, int $min, int $max): int
{
    $value = (int) ($settings[$key] ?? $default);
    return max($min, min($max, $value));
}

function fta_app_bootstrap(array $input): array
{
    $settings = fta_settings();
    $user = fta_api_current_user() ?: fta_current_user();
    $storageId = trim((string) ($_GET['storage_id'] ?? $input['storage_id'] ?? ''));
    $storageId = $storageId === '' ? null : substr($storageId, 0, 128);

    return [
        'status' => 'success',
        'app' => [
            'name' => FTA_APP_NAME,
            'lang' => fta_current_lang(),
            'logo_url' => fta_app_image_url('logo.png'),
            'version' => $settings['site_version'] ?? '1',
        ],
        'api' => [
            'app_url' => fta_app_absolute_url(fta_base_url('api/app.php')),
            'live_url' => fta_app_absolute_url(fta_base_url('api/football_live_proxy.php')),
            'result_url' => fta_app_absolute_url(fta_base_url('api/football_result_proxy.php')),
            'market_url' => fta_app_absolute_url(fta_base_url('api/market_data.php')),
            'odds_url' => fta_app_absolute_url(fta_base_url('api/odds.php')),
            'news_url' => 'https://api.hlainghtetaung.com/football/news/',
        ],
        'links' => [
            'facebook' => FTA_FACEBOOK_URL,
            'telegram' => FTA_TELEGRAM_URL,
            'tiktok' => FTA_TIKTOK_URL,
        ],
        'agent_contact' => function_exists('fta_agent_contact_for_user') ? fta_agent_contact_for_user($user) : [],
        'ibet_rules' => fta_app_ibet_rules_payload(fta_ibet_rules_for_user($user)),
        'settings' => [
            'form_open' => ($settings['form_open'] ?? '0') === '1',
            'form_is_open' => fta_form_is_open($settings),
            'app_announcement_enabled' => ($settings['app_announcement_enabled'] ?? '0') === '1',
            'app_announcement_title' => $settings['app_announcement_title'] ?? FTA_APP_NAME,
            'app_announcement_text' => $settings['app_announcement_text'] ?? '',
            'live_refresh_seconds' => fta_app_setting_int($settings, 'live_refresh_seconds', 60, 15, 300),
            'live_player_note' => $settings['live_player_note'] ?? '',
            'score_detail_enabled' => ($settings['score_detail_enabled'] ?? '1') === '1',
            'team_a_name' => $settings['team_a_name'] ?? t('team_a'),
            'team_a_logo' => fta_app_image_url($settings['team_a_logo'] ?? ''),
            'team_b_name' => $settings['team_b_name'] ?? t('team_b'),
            'team_b_logo' => fta_app_image_url($settings['team_b_logo'] ?? ''),
            'prize_total' => $settings['prize_total'] ?? '1,000,000 Kyat',
            'prize_each' => $settings['prize_each'] ?? '50,000 Kyat',
            'telegram_popup_title' => $settings['telegram_popup_title'] ?? t('join_telegram_title'),
            'telegram_popup_text' => $settings['telegram_popup_text'] ?? t('join_telegram_text'),
            'app_guide_videos' => fta_app_guide_videos_from_settings($settings),
            'app_guide_youtube_url' => fta_clean_link($settings['app_guide_youtube_url'] ?? ''),
            'app_guide_youtube_embed_url' => fta_youtube_embed_url($settings['app_guide_youtube_url'] ?? ''),
            'app_update_download_url' => fta_clean_link($settings['app_update_download_url'] ?? ''),
        ],
        'prediction' => [
            'already_submitted' => fta_app_submission_status($storageId),
        ],
        'ads' => fta_app_public_ads(),
        'categories' => fta_app_public_categories(),
        'translations' => fta_load_translations(fta_current_lang()),
    ];
}

function fta_app_ibet_rules_payload(array $rules): array
{
    $split = static fn (string $value): array => array_values(array_filter(array_map('trim', preg_split('/\R+/', $value) ?: [])));
    return [
        'football_rules' => $split((string) ($rules['football_rules'] ?? '')),
        'egame_rules' => $split((string) ($rules['egame_rules'] ?? '')),
        'updated_at' => (string) ($rules['updated_at'] ?? ''),
        'history' => array_map(static function (array $row) use ($split): array {
            return [
                'football_rules' => $split((string) ($row['football_rules'] ?? '')),
                'egame_rules' => $split((string) ($row['egame_rules'] ?? '')),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }, (array) ($rules['history'] ?? [])),
    ];
}

function fta_app_require_user(): array
{
    fta_require_https_for_auth(true);
    $user = fta_api_current_user();
    if (!$user) {
        fta_app_response(401, ['status' => 'error', 'message' => t('auth_session_expired', 'Please login again.')]);
    }

    return $user;
}

function fta_app_submit_prediction(array $input): array
{
    $settings = fta_settings();
    $user = fta_api_current_user();
    if (!fta_form_is_open($settings)) {
        return ['ok' => false, 'message' => t('form_closed_message')];
    }

    $required = ['ht_result', 'ft_result', 'first_scorer', 'wallet_type', 'wallet_name', 'wallet_number'];
    foreach ($required as $field) {
        if (trim((string) ($input[$field] ?? '')) === '') {
            return ['ok' => false, 'message' => t('required_message')];
        }
    }

    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'FullTimeArena Android App');
    $ip = fta_client_ip();
    $storageId = trim((string) ($input['storage_id'] ?? ''));
    $storageId = $storageId === '' ? null : substr($storageId, 0, 128);

    if (fta_submission_exists($storageId, $ip, $ua)) {
        return ['ok' => false, 'message' => t('already_submitted')];
    }

    $deviceJson = json_encode($input['device'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($deviceJson) || $deviceJson === '[]') {
        $deviceJson = '{}';
    }
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
            'browser_language' => fta_app_clean_text($input, 'browser_language', 80),
            'screen_size' => fta_app_clean_text($input, 'screen_size', 80),
            'device_json' => $deviceJson,
            'ht_result' => fta_app_clean_text($input, 'ht_result', 50),
            'ft_result' => fta_app_clean_text($input, 'ft_result', 50),
            'first_scorer' => fta_app_clean_text($input, 'first_scorer', 120),
            'wallet_type' => fta_app_clean_text($input, 'wallet_type', 30),
            'wallet_name' => fta_app_clean_text($input, 'wallet_name', 120),
            'wallet_number' => fta_app_clean_text($input, 'wallet_number', 80),
        ]);
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            return ['ok' => false, 'message' => t('already_submitted')];
        }
        throw $exception;
    }

    return ['ok' => true, 'message' => t('success_message')];
}

function fta_app_prediction_history(): array
{
    $user = fta_app_require_user();

    $storageId = trim((string) ($_GET['storage_id'] ?? ''));
    $storageId = $storageId === '' ? null : substr($storageId, 0, 128);
    $items = array_map('fta_prediction_history_payload', fta_prediction_history($user, $storageId, 50));

    return [
        'status' => 'success',
        'data' => $items,
    ];
}

function fta_app_unit_bootstrap(): array
{
    $user = fta_app_require_user();
    $providers = array_map(static function (array $provider): array {
        return [
            'provider_key' => (string) ($provider['provider_key'] ?? ''),
            'provider_label' => (string) ($provider['provider_label'] ?? ''),
            'active' => !empty($provider['active']),
            'supports_auto_create' => !empty($provider['supports_auto_create']),
        ];
    }, fta_available_provider_configs_for_user($user));

    return [
        'status' => 'success',
        'game_accounts' => array_map('fta_game_account_payload', fta_user_game_accounts($user)),
        'available_providers' => $providers,
        'payment_accounts' => array_map('fta_app_payment_account_payload', fta_agent_payment_accounts((int) ($user['agent_id'] ?? 0), true)),
        'payout_accounts' => fta_app_payout_payload($user),
        'has_payout_account' => fta_user_has_payout_account($user),
        'payment_history' => array_map('fta_unit_request_payload', fta_unit_requests_for_user((int) $user['id'], 100)),
        'sessions' => fta_user_sessions($user, fta_api_bearer_token()),
        'agent_contact' => fta_agent_contact_for_user($user),
    ];
}

function fta_app_create_game_account(array $input): array
{
    $user = fta_app_require_user();
    try {
        $account = fta_create_user_game_account($user, trim((string) ($input['provider_key'] ?? '')));
        return [
            'status' => 'success',
            'message' => t('game_account_created', 'Game account is ready.'),
            'account' => fta_game_account_payload($account),
        ];
    } catch (RuntimeException $error) {
        fta_app_response(422, ['status' => 'error', 'message' => fta_user_safe_error_message($error)]);
    }
}

function fta_app_connect_game_account(array $input): array
{
    $user = fta_app_require_user();
    try {
        $account = fta_connect_user_game_account(
            $user,
            trim((string) ($input['provider_key'] ?? '')),
            trim((string) ($input['external_username'] ?? $input['username'] ?? ''))
        );
        return [
            'status' => 'success',
            'message' => t('game_account_connected', 'Game account connected.'),
            'account' => fta_game_account_payload($account),
        ];
    } catch (RuntimeException $error) {
        fta_app_response(422, ['status' => 'error', 'message' => fta_user_safe_error_message($error)]);
    }
}

function fta_app_save_payout(array $input): array
{
    $user = fta_app_require_user();
    try {
        fta_save_user_payout_accounts((int) $user['id'], $input);
        return ['status' => 'success', 'message' => t('payout_saved', 'Payout account saved.')];
    } catch (RuntimeException $error) {
        fta_app_response(422, ['status' => 'error', 'message' => fta_user_safe_error_message($error)]);
    }
}

function fta_app_clear_payout(): array
{
    $user = fta_app_require_user();
    fta_clear_user_payout_accounts((int) $user['id']);
    return ['status' => 'success', 'message' => t('payout_removed', 'Payout account removed.')];
}

function fta_app_submit_unit(array $input, string $proofPath = ''): array
{
    $user = fta_app_require_user();
    $type = trim((string) ($input['request_type'] ?? $input['type'] ?? 'deposit'));
    try {
        fta_submit_unit_request($user, $type, $input, $proofPath);
        return ['status' => 'success', 'message' => t('unit_request_submitted', 'Request submitted.')];
    } catch (RuntimeException $error) {
        fta_app_response(422, ['status' => 'error', 'message' => fta_user_safe_error_message($error)]);
    }
}

function fta_app_payment_history(): array
{
    $user = fta_app_require_user();
    return [
        'status' => 'success',
        'data' => array_map('fta_unit_request_payload', fta_unit_requests_for_user((int) $user['id'], 100)),
    ];
}

function fta_app_auth_login(array $input): array
{
    fta_require_https_for_auth(true);
    try {
        $user = fta_auth_login($input);
        $payload = fta_api_auth_payload($user, !empty($input['remember']), $input);
        $payload['user'] = fta_app_user_payload($user);
        return $payload;
    } catch (RuntimeException $error) {
        fta_app_response(422, ['status' => 'error', 'message' => fta_user_safe_error_message($error)]);
    }
}

function fta_app_auth_signup(array $input): array
{
    fta_require_https_for_auth(true);
    try {
        $user = fta_auth_signup($input);
        $payload = fta_api_auth_payload($user, true, $input);
        $payload['message'] = t('auth_signup_success', 'Account created successfully.');
        $payload['user'] = fta_app_user_payload($user);
        return $payload;
    } catch (RuntimeException $error) {
        fta_app_response(422, ['status' => 'error', 'message' => fta_user_safe_error_message($error)]);
    }
}

function fta_app_auth_me(): array
{
    $user = fta_app_require_user();

    return [
        'status' => 'success',
        'user' => fta_app_user_payload($user),
    ];
}

function fta_app_auth_logout(): array
{
    fta_require_https_for_auth(true);
    fta_revoke_session_token(fta_api_bearer_token(), 'api');
    return ['status' => 'success', 'message' => t('logout_success', 'Logged out successfully.')];
}

function fta_app_auth_revoke_session(array $input): array
{
    $user = fta_app_require_user();
    try {
        fta_revoke_user_session($user, (int) ($input['session_id'] ?? 0), fta_api_bearer_token());
        return ['status' => 'success', 'message' => t('session_signed_out', 'Device signed out.')];
    } catch (RuntimeException $error) {
        fta_app_response(422, ['status' => 'error', 'message' => fta_user_safe_error_message($error)]);
    }
}

function fta_app_auth_change_password(array $input): array
{
    $user = fta_app_require_user();

    try {
        fta_change_user_password($user, $input);
        return ['status' => 'success', 'message' => t('password_changed', 'Password changed successfully.')];
    } catch (RuntimeException $error) {
        fta_app_response(422, ['status' => 'error', 'message' => fta_user_safe_error_message($error)]);
    }
}

function fta_app_update_profile(array $input): array
{
    $user = fta_app_require_user();
    try {
        $imagePath = '';
        if (!empty($input['profile_image_base64'])) {
            $base64 = (string) ($input['profile_image_base64'] ?? '');
            if (str_contains($base64, ',')) {
                $base64 = substr($base64, (int) strpos($base64, ',') + 1);
            }
            $binary = base64_decode($base64, true);
            if (!is_string($binary)) {
                throw new RuntimeException(t('profile_image_invalid', 'Please upload a valid profile photo.'));
            }
            $imagePath = fta_save_image_binary($binary, 'profiles', 5 * 1024 * 1024);
        }

        $updatedUser = fta_update_user_profile($user, $input, $imagePath);
        return [
            'status' => 'success',
            'message' => t('profile_updated', 'Profile updated.'),
            'user' => fta_app_user_payload($updatedUser),
        ];
    } catch (RuntimeException $error) {
        fta_app_response(422, ['status' => 'error', 'message' => fta_user_safe_error_message($error)]);
    }
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $type = trim((string) ($_GET['type'] ?? $jsonInput['type'] ?? 'bootstrap'));

    if ($method === 'GET' && ($type === 'bootstrap' || $type === '')) {
        fta_app_response(200, fta_app_bootstrap($jsonInput));
    }

    if ($method === 'GET' && $type === 'submission-status') {
        $storageId = trim((string) ($_GET['storage_id'] ?? ''));
        fta_app_response(200, [
            'status' => 'success',
            'already_submitted' => fta_app_submission_status($storageId === '' ? null : substr($storageId, 0, 128)),
        ]);
    }

    if ($method === 'GET' && $type === 'prediction-history') {
        fta_app_response(200, fta_app_prediction_history());
    }

    if ($method === 'GET' && $type === 'notifications') {
        $user = fta_api_current_user() ?: fta_current_user();
        fta_app_response(200, [
            'status' => 'success',
            'data' => fta_app_public_notifications($user),
        ]);
    }

    if ($method === 'GET' && $type === 'unit-bootstrap') {
        fta_app_response(200, fta_app_unit_bootstrap());
    }

    if ($method === 'GET' && $type === 'payment-history') {
        fta_app_response(200, fta_app_payment_history());
    }

    if ($method === 'POST' && $type === 'submit-prediction') {
        $result = fta_app_submit_prediction($jsonInput);
        fta_app_response($result['ok'] ? 200 : 422, [
            'status' => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);
    }

    if ($method === 'POST' && $type === 'auth-login') {
        fta_app_response(200, fta_app_auth_login($jsonInput));
    }

    if ($method === 'POST' && $type === 'auth-signup') {
        fta_app_response(200, fta_app_auth_signup($jsonInput));
    }

    if ($method === 'GET' && $type === 'auth-me') {
        fta_app_response(200, fta_app_auth_me());
    }

    if ($method === 'POST' && $type === 'auth-logout') {
        fta_app_response(200, fta_app_auth_logout());
    }

    if ($method === 'POST' && $type === 'auth-revoke-session') {
        fta_app_response(200, fta_app_auth_revoke_session($jsonInput));
    }

    if ($method === 'POST' && $type === 'auth-change-password') {
        fta_app_response(200, fta_app_auth_change_password($jsonInput));
    }

    if ($method === 'POST' && $type === 'auth-update-profile') {
        fta_app_response(200, fta_app_update_profile($jsonInput));
    }

    if ($method === 'POST' && $type === 'create-game-account') {
        fta_app_response(200, fta_app_create_game_account($jsonInput));
    }

    if ($method === 'POST' && $type === 'connect-game-account') {
        fta_app_response(200, fta_app_connect_game_account($jsonInput));
    }

    if ($method === 'POST' && $type === 'save-payout-accounts') {
        fta_app_response(200, fta_app_save_payout($jsonInput));
    }

    if ($method === 'POST' && $type === 'clear-payout-accounts') {
        fta_app_response(200, fta_app_clear_payout());
    }

    if ($method === 'POST' && $type === 'submit-unit-request') {
        $input = $_POST ?: $jsonInput;
        $proofPath = '';
        if (!empty($_FILES['proof'])) {
            $proofPath = fta_save_unit_proof_from_upload('proof');
        } elseif (!empty($input['proof_base64'])) {
            $proofPath = fta_save_unit_proof_from_base64(
                (string) ($input['proof_base64'] ?? ''),
                (string) ($input['proof_name'] ?? ''),
                (string) ($input['proof_type'] ?? '')
            );
        }
        fta_app_response(200, fta_app_submit_unit($input, $proofPath));
    }

    fta_app_response(400, ['status' => 'error', 'message' => 'Unsupported app API request.']);
} catch (Throwable $error) {
    fta_app_response(500, ['status' => 'error', 'message' => t('request_failed', 'Request failed. Please try again.')]);
}
