<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/layout.php';

function fta_provider_json_encode($value): string
{
    return json_encode(
        $value,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
    ) ?: '{}';
}

function fta_provider_json_decode($value): array
{
    $decoded = json_decode((string) $value, true);
    return is_array($decoded) ? $decoded : [];
}

function fta_provider_string_excerpt($value, int $length = 600): string
{
    $text = is_scalar($value) ? (string) $value : fta_provider_json_encode($value);
    $text = trim($text);
    return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
}

function fta_provider_api_error_message(array $response, string $fallback): string
{
    $json = is_array($response['json'] ?? null) ? $response['json'] : [];
    foreach (['message', 'error', 'detail', 'title'] as $key) {
        $message = trim((string) ($json[$key] ?? ''));
        if ($message !== '') {
            return $message;
        }
    }

    $body = trim((string) ($response['body'] ?? ''));
    if ($body !== '') {
        return fta_provider_string_excerpt($body, 220);
    }

    return $fallback;
}

function fta_provider_sanitize_debug($value)
{
    if (is_array($value)) {
        $sanitized = [];
        foreach ($value as $key => $child) {
            $keyText = strtolower((string) $key);
            if (str_contains($keyText, 'password') || str_contains($keyText, 'token') || str_contains($keyText, 'cookie')) {
                $sanitized[$key] = '[redacted]';
                continue;
            }
            $sanitized[$key] = fta_provider_sanitize_debug($child);
        }
        return $sanitized;
    }

    return $value;
}

function fta_provider_debug_failure(string $message, array $debug = []): array
{
    $reference = 'FTA-' . strtoupper(bin2hex(random_bytes(4)));
    $dir = fta_project_path('storage/logs');
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    @file_put_contents(
        $dir . DIRECTORY_SEPARATOR . 'provider_debug.log',
        '[' . date('c') . '] ' . $reference . ' ' . fta_provider_json_encode([
            'message' => $message,
            'debug' => fta_provider_sanitize_debug($debug),
        ]) . PHP_EOL,
        FILE_APPEND
    );

    return [
        'error' => $message,
        'debug_reference' => $reference,
        'debug' => fta_provider_sanitize_debug($debug),
    ];
}

function fta_provider_http_request(string $method, string $url, array $headers = [], $body = null): array
{
    if (!function_exists('curl_init')) {
        return ['status' => 0, 'body' => '', 'json' => null, 'headers' => [], 'error' => 'cURL extension is not available.'];
    }

    $responseHeaders = [];
    $curl = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 35,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_USERAGENT => 'FullTimeArena/1.0',
        CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$responseHeaders): int {
            $trimmed = trim($headerLine);
            if ($trimmed !== '' && str_contains($trimmed, ':')) {
                [$name, $value] = explode(':', $trimmed, 2);
                $responseHeaders[strtolower(trim($name))] = trim($value);
            }
            return strlen($headerLine);
        },
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = is_string($body) ? $body : fta_provider_json_encode($body);
    }

    curl_setopt_array($curl, $options);
    $raw = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $effectiveUrl = (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
    curl_close($curl);

    $bodyText = $raw === false ? '' : (string) $raw;
    $json = json_decode($bodyText, true);
    return [
        'status' => $status,
        'body' => $bodyText,
        'json' => is_array($json) ? $json : null,
        'headers' => $responseHeaders,
        'error' => $error,
        'url' => $effectiveUrl !== '' ? $effectiveUrl : $url,
    ];
}

function fta_provider_http_json_post(string $url, array $payload, array $headers = []): array
{
    $headers = array_merge(['Accept: application/json', 'Content-Type: application/json'], $headers);
    return fta_provider_http_request('POST', $url, $headers, $payload);
}

function fta_provider_http_json_get(string $url, array $headers = []): array
{
    $headers = array_merge(['Accept: application/json'], $headers);
    return fta_provider_http_request('GET', $url, $headers);
}

function fta_provider_random_suffix(int $length = 3): string
{
    $letters = 'abcdefghijklmnopqrstuvwxyz';
    $suffix = '';
    for ($i = 0; $i < $length; $i++) {
        $suffix .= $letters[random_int(0, strlen($letters) - 1)];
    }
    return $suffix;
}

function fta_provider_random_numeric_string(int $length): string
{
    $value = '';
    for ($i = 0; $i < $length; $i++) {
        $value .= (string) random_int(0, 9);
    }
    return $value;
}

function fta_provider_random_alphanumeric_string(int $length): string
{
    $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $value = '';
    for ($i = 0; $i < $length; $i++) {
        $value .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $value;
}

function fta_provider_normalize_amount($amount)
{
    $amount = (float) $amount;
    return floor($amount) === $amount ? (int) $amount : round($amount, 2);
}

function fta_provider_config_for_agent(int $agentId, string $providerKey): ?array
{
    $providerKey = strtolower(trim($providerKey));
    $catalog = fta_provider_catalog();
    $provider = $catalog[$providerKey] ?? null;
    $config = fta_agent_provider_config($agentId, $providerKey);
    if (!$provider || !$config || empty($config['active'])) {
        return null;
    }

    $config['provider'] = $provider;
    $config['agent_id'] = $agentId;
    $config['agent_username'] = fta_decrypt_secret((string) ($config['agent_username_enc'] ?? ''), 'provider_username');
    $config['agent_password'] = fta_decrypt_secret((string) ($config['agent_password_enc'] ?? ''), 'provider_password');
    if ($config['agent_username'] === '' || $config['agent_password'] === '') {
        return null;
    }

    return $config;
}

function fta_provider_user_name(array $user): string
{
    return trim((string) ($user['full_name'] ?? $user['name'] ?? 'FullTime User'));
}

function fta_provider_user_phone(array $user): string
{
    return trim((string) ($user['phone_e164'] ?? $user['phone_number'] ?? $user['phone'] ?? ''));
}

function fta_provider_auth_headers(string $token, array $provider): array
{
    $token = preg_replace('/^Bearer\s+/i', '', trim($token));
    return [
        'Authorization: Bearer ' . $token,
        'Origin: ' . $provider['origin'],
        'Referer: ' . $provider['referer'],
    ];
}

function fta_555mix_auth_context(array $config): array
{
    $provider = $config['provider'];
    $response = fta_provider_http_json_post(
        $provider['login_url'],
        ['username' => $config['agent_username'], 'password' => $config['agent_password']],
        ['Origin: ' . $provider['origin'], 'Referer: ' . $provider['referer']]
    );
    if (($response['status'] ?? 0) !== 200 || empty($response['json']['accessToken'])) {
        return fta_provider_debug_failure(fta_provider_api_error_message($response, '555mix login failed.'), [
            'provider' => '555mix',
            'step' => 'agent_login',
            'url' => $provider['login_url'],
            'http_status' => $response['status'] ?? null,
            'response_json' => $response['json'] ?? null,
            'response_body' => $response['body'] ?? '',
        ]);
    }
    return ['provider' => $provider, 'token' => (string) $response['json']['accessToken'], 'login_response' => $response];
}

function fta_555mix_member_items($payload): array
{
    if (!is_array($payload)) {
        return [];
    }
    if (isset($payload[0]) && is_array($payload[0])) {
        return $payload;
    }
    if (isset($payload['content']) && is_array($payload['content'])) {
        return $payload['content'];
    }
    if (isset($payload['data']) && is_array($payload['data'])) {
        return $payload['data'];
    }
    return [];
}

function fta_555mix_build_commissions(array $agentProfile): array
{
    $commissions = [];
    foreach (($agentProfile['commissions'] ?? []) as $commission) {
        $commissionId = (int) ($commission['id'] ?? 0);
        $matchCount = (int) ($commission['matchCount'] ?? 0);
        $winCommission = (int) ($commission['winCommission'] ?? 0);
        if ($commissionId <= 0 || $matchCount <= 0) {
            continue;
        }
        $commissions[] = [
            'id' => $commissionId,
            'matchCount' => $matchCount,
            'betCommission' => 0,
            'winCommission' => $winCommission,
        ];
    }
    return $commissions;
}

function fta_555mix_agent_profile(array $auth): array
{
    $provider = $auth['provider'];
    $response = fta_provider_http_json_get($provider['me_url'], fta_provider_auth_headers($auth['token'], $provider));
    if (($response['status'] ?? 0) !== 200 || !is_array($response['json'] ?? null)) {
        return fta_provider_debug_failure(fta_provider_api_error_message($response, '555mix agent profile failed.'), [
            'provider' => '555mix',
            'step' => 'agent_profile',
            'url' => $provider['me_url'],
            'http_status' => $response['status'] ?? null,
            'response_json' => $response['json'] ?? null,
            'response_body' => $response['body'] ?? '',
        ]);
    }
    if (fta_555mix_build_commissions($response['json']) === []) {
        return fta_provider_debug_failure('555mix agent profile is missing commissions.', [
            'provider' => '555mix',
            'step' => 'agent_profile',
            'response_json' => $response['json'],
        ]);
    }
    return ['profile' => $response['json'], 'response' => $response];
}

function fta_555mix_find_member_by_username(array $auth, string $username): array
{
    $provider = $auth['provider'];
    $response = fta_provider_http_json_get($provider['members_url'], fta_provider_auth_headers($auth['token'], $provider));
    if (($response['status'] ?? 0) !== 200 || !is_array($response['json'] ?? null)) {
        return fta_provider_debug_failure(fta_provider_api_error_message($response, '555mix member lookup failed.'), [
            'provider' => '555mix',
            'step' => 'member_lookup',
            'url' => $provider['members_url'],
            'http_status' => $response['status'] ?? null,
            'response_json' => $response['json'] ?? null,
            'response_body' => $response['body'] ?? '',
        ]);
    }
    foreach (fta_555mix_member_items($response['json']) as $member) {
        if (trim((string) ($member['username'] ?? '')) === $username) {
            return ['member' => $member];
        }
    }
    return fta_provider_debug_failure('555mix member not found.', [
        'provider' => '555mix',
        'step' => 'member_lookup',
        'request_payload' => ['username' => $username],
        'response_json' => ['items' => count(fta_555mix_member_items($response['json']))],
    ]);
}

function fta_555mix_create_member_account(array $config, array $user): array
{
    $auth = fta_555mix_auth_context($config);
    if (!empty($auth['error'])) {
        return $auth;
    }
    $agentProfile = fta_555mix_agent_profile($auth);
    if (!empty($agentProfile['error'])) {
        return $agentProfile;
    }

    $provider = $auth['provider'];
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $suffix = fta_provider_random_suffix(3);
        $password = fta_provider_random_numeric_string(8);
        $singleBetCommission = is_array($agentProfile['profile']['singleBetCommission'] ?? null) ? $agentProfile['profile']['singleBetCommission'] : [];
        $payload = [
            'name' => fta_provider_user_name($user),
            'username' => $suffix,
            'password' => $password,
            'mobile' => fta_provider_user_phone($user),
            'betLimitation' => [
                'maxForSingleBet' => (int) ($config['bet_limit_single'] ?? 0),
                'maxForMixBet' => (int) ($config['bet_limit_mix'] ?? 0),
            ],
            'commissions' => fta_555mix_build_commissions($agentProfile['profile']),
            'singleBetCommission' => [
                'betCommission' => 0,
                'highBetCommission' => 0,
                'winCommission' => (int) ($singleBetCommission['winCommission'] ?? 5),
                'highWinCommission' => (int) ($singleBetCommission['highWinCommission'] ?? 8),
            ],
            'twoDCommission' => 0,
            'threeDCommission' => 0,
            'twoThreeAllowed' => array_key_exists('twoThreeAllowed', $agentProfile['profile']) ? (bool) $agentProfile['profile']['twoThreeAllowed'] : true,
        ];
        $response = fta_provider_http_json_post($provider['signup_url'], $payload, fta_provider_auth_headers($auth['token'], $provider));
        $status = (int) ($response['status'] ?? 0);
        if (($status === 201 || $status === 200) && (($response['json']['success'] ?? false) === true)) {
            $location = (string) ($response['headers']['location'] ?? '');
            $username = $location !== '' ? basename(parse_url($location, PHP_URL_PATH) ?: '') : '';
            if ($username === '') {
                $username = (string) $config['agent_username'] . $suffix;
            }

            $memberId = 0;
            $lookup = fta_555mix_find_member_by_username($auth, $username);
            if (empty($lookup['error'])) {
                $memberId = (int) ($lookup['member']['id'] ?? 0);
            }

            return [
                'provider_key' => '555mix',
                'provider_label' => $provider['label'],
                'external_username' => $username,
                'external_member_id' => $memberId > 0 ? $memberId : null,
                'external_password' => $password,
                'username_suffix' => $suffix,
                'api_payload' => fta_provider_json_encode($payload),
                'api_response' => fta_provider_json_encode([
                    'login' => $auth['login_response']['json'] ?? $auth['login_response']['body'] ?? '',
                    'signup' => $response['json'] ?? $response['body'] ?? '',
                    'headers' => $response['headers'] ?? [],
                    'member_lookup' => empty($lookup['error']) ? ($lookup['member'] ?? null) : null,
                ]),
            ];
        }

        $message = strtolower(fta_provider_api_error_message($response, '555mix signup failed.'));
        if (!str_contains($message, 'exist') && !str_contains($message, 'duplicate')) {
            return fta_provider_debug_failure(fta_provider_api_error_message($response, '555mix signup failed.'), [
                'provider' => '555mix',
                'step' => 'member_signup',
                'url' => $provider['signup_url'],
                'request_payload' => $payload,
                'http_status' => $response['status'] ?? null,
                'response_json' => $response['json'] ?? null,
                'response_body' => $response['body'] ?? '',
            ]);
        }
    }

    return fta_provider_debug_failure('Could not generate a unique 555mix username.', ['provider' => '555mix', 'step' => 'member_signup']);
}

function fta_555mix_resolve_member_id(array $auth, array $gameAccount): array
{
    $memberId = (int) ($gameAccount['external_member_id'] ?? 0);
    if ($memberId > 0) {
        return ['member_id' => $memberId];
    }
    $lookup = fta_555mix_find_member_by_username($auth, (string) ($gameAccount['external_username'] ?? ''));
    if (!empty($lookup['error'])) {
        return $lookup;
    }
    $memberId = (int) ($lookup['member']['id'] ?? 0);
    if ($memberId <= 0) {
        return fta_provider_debug_failure('555mix member id missing.', ['provider' => '555mix', 'step' => 'member_lookup']);
    }
    fta_provider_update_game_account_member_id((int) ($gameAccount['id'] ?? 0), $memberId);
    return ['member_id' => $memberId, 'member' => $lookup['member']];
}

function fta_555mix_sync_request_transaction(array $request, array $gameAccount, array $requestData): array
{
    if (!empty($requestData['provider_sync']['transaction_id'])) {
        return ['request_data' => $requestData];
    }
    $config = fta_provider_config_for_agent((int) ($gameAccount['agent_id'] ?? $request['agent_id'] ?? 0), '555mix');
    if (!$config) {
        return ['error' => '555mix API config is not ready for this agent.'];
    }
    $auth = fta_555mix_auth_context($config);
    if (!empty($auth['error'])) {
        return $auth;
    }
    $memberIdInfo = fta_555mix_resolve_member_id($auth, $gameAccount);
    if (!empty($memberIdInfo['error'])) {
        return $memberIdInfo;
    }
    $provider = $auth['provider'];
    $memberId = (int) $memberIdInfo['member_id'];
    $command = ($request['request_type'] ?? '') === 'withdraw' ? 'remove' : 'add';
    $payload = ['command' => $command, 'amount' => fta_provider_normalize_amount($request['amount'] ?? 0), 'credit' => false];
    $url = rtrim((string) $provider['transactions_members_url'], '/') . '/' . $memberId;
    $response = fta_provider_http_json_post($url, $payload, fta_provider_auth_headers($auth['token'], $provider));
    if (($response['status'] ?? 0) !== 200 || empty($response['json']['id'])) {
        return fta_provider_debug_failure(fta_provider_api_error_message($response, '555mix transaction failed.'), [
            'provider' => '555mix',
            'step' => 'member_transaction',
            'url' => $url,
            'request_payload' => $payload,
            'http_status' => $response['status'] ?? null,
            'response_json' => $response['json'] ?? null,
            'response_body' => $response['body'] ?? '',
        ]);
    }
    $requestData['game_provider'] = $provider['label'];
    $requestData['game_provider_key'] = '555mix';
    $requestData['game_username'] = $gameAccount['external_username'] ?? ($requestData['game_username'] ?? '-');
    $requestData['provider_sync'] = [
        'provider_key' => '555mix',
        'provider_label' => $provider['label'],
        'member_id' => $memberId,
        'transaction_id' => (string) ($response['json']['id'] ?? ''),
        'transaction_type' => (string) ($response['json']['transactionType'] ?? ''),
        'reference_no' => (string) ($response['json']['referenceNo'] ?? ''),
        'balance' => $response['json']['balance'] ?? null,
        'command' => $command,
    ];
    return ['request_data' => $requestData, 'provider_sync' => $requestData['provider_sync']];
}

function fta_sports899_extract_token($value): string
{
    if (is_string($value)) {
        return preg_replace('/^Bearer\s+/i', '', trim($value));
    }
    if (!is_array($value)) {
        return '';
    }
    foreach (['token', 'access_token', 'accessToken', 'bearer_token'] as $key) {
        if (isset($value[$key])) {
            $token = fta_sports899_extract_token($value[$key]);
            if ($token !== '') {
                return $token;
            }
        }
    }
    foreach ($value as $child) {
        if (is_array($child)) {
            $token = fta_sports899_extract_token($child);
            if ($token !== '') {
                return $token;
            }
        }
    }
    return '';
}

function fta_sports899_auth_context(array $config, string $kind = 'lapi'): array
{
    $provider = $config['provider'];
    $url = $kind === 'napi' ? $provider['napi_login_url'] : $provider['login_url'];
    $response = fta_provider_http_json_post(
        $url,
        ['usercode' => $config['agent_username'], 'password' => $config['agent_password']],
        ['Origin: ' . $provider['origin'], 'Referer: ' . $provider['referer']]
    );
    $token = fta_sports899_extract_token($response['json'] ?? null);
    if (($response['status'] ?? 0) !== 200 || $token === '') {
        return fta_provider_debug_failure(fta_provider_api_error_message($response, $kind === 'napi' ? 'sports 899 napi login failed.' : 'sports 899 login failed.'), [
            'provider' => 'sports 899',
            'step' => $kind === 'napi' ? 'napi_login' : 'agent_login',
            'url' => $url,
            'http_status' => $response['status'] ?? null,
            'response_json' => $response['json'] ?? null,
            'response_body' => $response['body'] ?? '',
        ]);
    }
    return ['provider' => $provider, 'token' => $token, 'login_response' => $response];
}

function fta_sports899_extract_member_record($payload, string $username): ?array
{
    $username = strtoupper(trim($username));
    $items = [];
    if (is_array($payload['data'] ?? null)) {
        $items = $payload['data'];
    } elseif (is_array($payload)) {
        $items = $payload;
    }
    foreach ($items as $item) {
        if (is_array($item) && strtoupper(trim((string) ($item['usercode'] ?? ''))) === $username) {
            return $item;
        }
    }
    return null;
}

function fta_sports899_find_member_by_usercode(array $auth, string $username): array
{
    $provider = $auth['provider'];
    $headers = fta_provider_auth_headers($auth['token'], $provider);
    $queries = [
        ['search' => $username, 'sortDirection' => 'asc', 'sortColumn' => 'usercode', 'limit' => 100],
        ['search' => '', 'sortDirection' => 'asc', 'sortColumn' => 'usercode', 'limit' => 9999999],
    ];
    $last = null;
    $lastUrl = $provider['downlines_url'];
    foreach ($queries as $query) {
        $url = $provider['downlines_url'] . '?' . http_build_query($query);
        $response = fta_provider_http_json_get($url, $headers);
        $last = $response;
        $lastUrl = $url;
        if (($response['status'] ?? 0) !== 200 || !is_array($response['json'] ?? null)) {
            continue;
        }
        $member = fta_sports899_extract_member_record($response['json'], $username);
        if (is_array($member)) {
            return ['member' => $member, 'response' => $response];
        }
    }
    return fta_provider_debug_failure('sports 899 member lookup failed.', [
        'provider' => 'sports 899',
        'step' => 'member_lookup',
        'url' => $lastUrl,
        'http_status' => $last['status'] ?? null,
        'response_json' => $last['json'] ?? null,
        'response_body' => $last['body'] ?? '',
    ]);
}

function fta_sports899_create_member_account(array $config, array $user): array
{
    $auth = fta_sports899_auth_context($config);
    if (!empty($auth['error'])) {
        return $auth;
    }
    $provider = $auth['provider'];
    $napi = null;
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $suffix = strtolower(fta_provider_random_suffix(3));
        $password = fta_provider_random_numeric_string(9);
        $single = max(1, (int) ($config['bet_limit_single'] ?? 250000));
        $mix = max(1, (int) ($config['bet_limit_mix'] ?? 100000));
        $payload = [
            'role_id' => '6',
            'usercode' => $suffix,
            'contact' => '',
            'password' => $password,
            'min_bet_limit' => 1000,
            'max_single_bet_limit' => $single,
            'match_limit' => max(500000, $single),
            'min_myanmar_perlay_limit_per_combination' => 500,
            'max_myanmar_perlay_limit_per_combination' => max(100000, $mix),
            'two_d_limit' => 100000,
            'two_d_min_per_bet' => 100,
            'two_d_max_per_bet' => 100000,
            'three_d_limit' => 10000,
            'three_d_min_per_bet' => 100,
            'three_d_max_per_bet' => 10000,
            'commissions' => [
                'commissionSM' => '1.00',
                'commissionLG' => '1.00',
                'perlay2' => '6.00',
                'perlay38' => '12.00',
                'perlay911' => '13.00',
            ],
        ];
        $response = fta_provider_http_json_post($provider['signup_url'], $payload, fta_provider_auth_headers($auth['token'], $provider));
        $status = strtolower(trim((string) ($response['json']['status'] ?? '')));
        $data = is_array($response['json']['data'] ?? null) ? $response['json']['data'] : [];
        $username = trim((string) ($data['usercode'] ?? ''));
        if (($response['status'] ?? 0) === 200 && ($status === '' || $status === 'success') && $username !== '') {
            $memberId = (int) ($data['id'] ?? 0);
            $lookup = [];
            if ($memberId <= 0) {
                $napi ??= fta_sports899_auth_context($config, 'napi');
                if (empty($napi['error'])) {
                    $lookup = fta_sports899_find_member_by_usercode($napi, $username);
                    if (empty($lookup['error'])) {
                        $memberId = (int) ($lookup['member']['id'] ?? 0);
                    }
                }
            }
            return [
                'provider_key' => 'sports_899',
                'provider_label' => $provider['label'],
                'external_username' => $username,
                'external_member_id' => $memberId > 0 ? $memberId : null,
                'external_password' => trim((string) ($data['password'] ?? $password)),
                'username_suffix' => $suffix,
                'api_payload' => fta_provider_json_encode($payload),
                'api_response' => fta_provider_json_encode([
                    'login' => $auth['login_response']['json'] ?? $auth['login_response']['body'] ?? '',
                    'signup' => $response['json'] ?? $response['body'] ?? '',
                    'member_lookup' => empty($lookup['error']) ? ($lookup['member'] ?? null) : null,
                ]),
            ];
        }
        $message = strtolower(fta_provider_api_error_message($response, 'sports 899 signup failed.'));
        if (!str_contains($message, 'exist') && !str_contains($message, 'duplicate') && !str_contains($message, 'already')) {
            return fta_provider_debug_failure(fta_provider_api_error_message($response, 'sports 899 signup failed.'), [
                'provider' => 'sports 899',
                'step' => 'member_signup',
                'url' => $provider['signup_url'],
                'request_payload' => $payload,
                'http_status' => $response['status'] ?? null,
                'response_json' => $response['json'] ?? null,
                'response_body' => $response['body'] ?? '',
            ]);
        }
    }
    return fta_provider_debug_failure('Could not generate a unique sports 899 usercode.', ['provider' => 'sports 899', 'step' => 'member_signup']);
}

function fta_sports899_resolve_member_id(array $auth, array $gameAccount): array
{
    $memberId = (int) ($gameAccount['external_member_id'] ?? 0);
    if ($memberId > 0) {
        return ['member_id' => $memberId];
    }
    $apiResponse = fta_provider_json_decode($gameAccount['api_response'] ?? '');
    if (is_array($apiResponse['member_lookup'] ?? null)) {
        $memberId = (int) ($apiResponse['member_lookup']['id'] ?? 0);
    }
    if ($memberId <= 0 && is_array($apiResponse['signup']['data'] ?? null)) {
        $memberId = (int) ($apiResponse['signup']['data']['id'] ?? 0);
    }
    if ($memberId <= 0) {
        $lookup = fta_sports899_find_member_by_usercode($auth, (string) ($gameAccount['external_username'] ?? ''));
        if (!empty($lookup['error'])) {
            return $lookup;
        }
        $memberId = (int) ($lookup['member']['id'] ?? 0);
    }
    if ($memberId <= 0) {
        return fta_provider_debug_failure('sports 899 member id missing.', ['provider' => 'sports 899', 'step' => 'member_lookup']);
    }
    fta_provider_update_game_account_member_id((int) ($gameAccount['id'] ?? 0), $memberId);
    return ['member_id' => $memberId];
}

function fta_sports899_sync_request_transaction(array $request, array $gameAccount, array $requestData): array
{
    if (!empty($requestData['provider_sync']['transaction_id'])) {
        return ['request_data' => $requestData];
    }
    $config = fta_provider_config_for_agent((int) ($gameAccount['agent_id'] ?? $request['agent_id'] ?? 0), 'sports_899');
    if (!$config) {
        return ['error' => 'sports 899 API config is not ready for this agent.'];
    }
    $lapi = fta_sports899_auth_context($config);
    if (!empty($lapi['error'])) {
        return $lapi;
    }
    $napi = fta_sports899_auth_context($config, 'napi');
    if (!empty($napi['error'])) {
        return $napi;
    }
    $memberIdInfo = fta_sports899_resolve_member_id($napi, $gameAccount);
    if (!empty($memberIdInfo['error'])) {
        return $memberIdInfo;
    }
    $provider = $lapi['provider'];
    $type = ($request['request_type'] ?? '') === 'withdraw' ? 'withdraw' : 'deposit';
    $payload = ['amount' => fta_provider_normalize_amount($request['amount'] ?? 0), 'type' => $type, 'user_id' => (int) $memberIdInfo['member_id']];
    $response = fta_provider_http_json_post($provider['wallets_url'], $payload, fta_provider_auth_headers($lapi['token'], $provider));
    $status = strtolower(trim((string) ($response['json']['status'] ?? '')));
    if (($response['status'] ?? 0) !== 200 || $status === 'error' || $status === 'failed') {
        return fta_provider_debug_failure(fta_provider_api_error_message($response, 'sports 899 wallet sync failed.'), [
            'provider' => 'sports 899',
            'step' => 'wallet_payment',
            'url' => $provider['wallets_url'],
            'request_payload' => $payload,
            'http_status' => $response['status'] ?? null,
            'response_json' => $response['json'] ?? null,
            'response_body' => $response['body'] ?? '',
        ]);
    }
    $requestData['game_provider'] = $provider['label'];
    $requestData['game_provider_key'] = 'sports_899';
    $requestData['game_username'] = $gameAccount['external_username'] ?? ($requestData['game_username'] ?? '-');
    $requestData['provider_sync'] = [
        'provider_key' => 'sports_899',
        'provider_label' => $provider['label'],
        'member_id' => (int) $memberIdInfo['member_id'],
        'transaction_id' => 'sports899-' . $type . '-' . date('YmdHis'),
        'type' => $type,
        'amount' => $payload['amount'],
        'message' => (string) ($response['json']['message'] ?? ''),
        'response_body' => fta_provider_string_excerpt($response['json'] ?? $response['body'] ?? ''),
    ];
    return ['request_data' => $requestData, 'provider_sync' => $requestData['provider_sync']];
}

function fta_xzone_login_urls(array $provider, string $role): array
{
    $loginUrl = trim((string) ($provider['login_url'] ?? ''));
    if ($loginUrl === '') {
        return [];
    }
    if ($role !== 'member') {
        return [$loginUrl];
    }
    return array_values(array_unique(array_filter([
        preg_replace('~/login/agent/?$~i', '/login/member', $loginUrl),
        preg_replace('~/agent/?$~i', '/member', $loginUrl),
        preg_replace('~/agent/?$~i', '', $loginUrl),
    ])));
}

function fta_xzone_extract_token($value): string
{
    if (is_string($value)) {
        $value = preg_replace('/^Bearer\s+/i', '', trim($value));
        return preg_match('/^[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+$/', $value) ? $value : '';
    }
    if (!is_array($value)) {
        return '';
    }
    foreach (['token', 'access_token', 'accessToken', 'jwt'] as $key) {
        if (isset($value[$key])) {
            $token = fta_xzone_extract_token($value[$key]);
            if ($token !== '') {
                return $token;
            }
        }
    }
    foreach ($value as $child) {
        $token = fta_xzone_extract_token($child);
        if ($token !== '') {
            return $token;
        }
    }
    return '';
}

function fta_xzone_extract_user_id($value): int
{
    if (!is_array($value)) {
        return 0;
    }
    foreach (['user_id', 'id'] as $key) {
        $id = (int) ($value[$key] ?? 0);
        if ($id > 0) {
            return $id;
        }
    }
    foreach ($value as $child) {
        if (is_array($child)) {
            $id = fta_xzone_extract_user_id($child);
            if ($id > 0) {
                return $id;
            }
        }
    }
    return 0;
}

function fta_xzone_login_with_role(array $provider, string $usercode, string $password, string $role): array
{
    $payload = ['usercode' => $usercode, 'password' => $password, 'role' => $role];
    $lastResponse = null;
    $lastUrl = '';
    foreach (fta_xzone_login_urls($provider, $role) as $url) {
        $response = fta_provider_http_json_post($url, $payload, ['Origin: ' . $provider['origin'], 'Referer: ' . $provider['referer']]);
        $lastResponse = $response;
        $lastUrl = $url;
        if (($response['status'] ?? 0) !== 200 || !is_array($response['json'] ?? null)) {
            continue;
        }
        $data = is_array($response['json']['data'] ?? null) ? $response['json']['data'] : [];
        $user = is_array($data['user'] ?? null) ? $data['user'] : [];
        $token = fta_xzone_extract_token($response['json']);
        $userId = fta_xzone_extract_user_id($user) ?: fta_xzone_extract_user_id($data) ?: fta_xzone_extract_user_id($response['json']);
        if ($role === 'agent' && $token === '') {
            continue;
        }
        if ($role === 'member' && $userId <= 0) {
            continue;
        }
        return ['url' => $url, 'payload' => $payload, 'response' => $response, 'token' => $token, 'user' => $user, 'user_id' => $userId];
    }
    return ['url' => $lastUrl, 'payload' => $payload, 'response' => $lastResponse, 'token' => '', 'user' => [], 'user_id' => 0];
}

function fta_xzone_auth_context(array $config): array
{
    $provider = $config['provider'];
    $login = fta_xzone_login_with_role($provider, $config['agent_username'], $config['agent_password'], 'agent');
    $response = $login['response'] ?? [];
    if (($response['status'] ?? 0) !== 200 || (string) ($login['token'] ?? '') === '') {
        return fta_provider_debug_failure(fta_provider_api_error_message(is_array($response) ? $response : [], 'sports x zone login failed.'), [
            'provider' => 'sports x zone',
            'step' => 'agent_login',
            'url' => $login['url'] ?? $provider['login_url'],
            'http_status' => $response['status'] ?? null,
            'response_json' => $response['json'] ?? null,
            'response_body' => $response['body'] ?? '',
        ]);
    }
    return ['provider' => $provider, 'token' => (string) $login['token'], 'login_response' => $response];
}

function fta_xzone_amount_range($maxAmount, $minAmount = 0): array
{
    return ['min_bet_amount' => (string) max(0, (int) $minAmount), 'max_bet_amount' => (string) max(0, (int) $maxAmount)];
}

function fta_xzone_full_half_range($fullTimeMax, $firstHalfMax = null, $minAmount = 0): array
{
    $firstHalfMax ??= $fullTimeMax;
    return ['full_time' => fta_xzone_amount_range($fullTimeMax, $minAmount), 'first_half' => fta_xzone_amount_range($firstHalfMax, $minAmount)];
}

function fta_xzone_config_payload(): array
{
    return [
        'perlay_limit' => fta_xzone_amount_range(200000, 500),
        'body_match_limit' => ['full_time' => '1000000', 'first_half' => '500000'],
        'ou_match_limit' => ['full_time' => '1000000', 'first_half' => '500000'],
        'hdp_ou_limit' => fta_xzone_full_half_range(1000000, 500000, 1000),
        'correct_score_limit' => fta_xzone_full_half_range(500000, 500000, 1000),
        'odd_even_limit' => fta_xzone_full_half_range(300000, 300000, 1000),
        '_1x2_limit' => fta_xzone_full_half_range(500000, 500000, 1000),
        'twod_limit' => ['min_bet_amount' => '100', 'max_bet_amount' => '10000', 'match_limit' => 0],
        'threed_limit' => ['min_bet_amount' => '100', 'max_bet_amount' => '10000', 'match_limit' => 0],
        'ibet_limit' => [
            'ibet_min_bet' => 0,
            'ibet_max_bet' => 0,
            'ibet_match_per_bet' => 0,
            'ibet_parlay_max_bet' => 0,
            'ibet_parlay_match_per_bet' => 0,
            'ibet_mm_parlay_max_bet' => 0,
        ],
    ];
}

function fta_xzone_create_member_account(array $config, array $user): array
{
    $auth = fta_xzone_auth_context($config);
    if (!empty($auth['error'])) {
        return $auth;
    }
    $provider = $auth['provider'];
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $suffix = strtolower(fta_provider_random_suffix(3));
        $password = fta_provider_random_alphanumeric_string(8);
        $payload = [
            'role_name' => 'member',
            'usercode' => $suffix,
            'password' => $password,
            'contact' => '',
            'is_disable_slot' => false,
            'config' => fta_xzone_config_payload(),
        ];
        $response = fta_provider_http_json_post($provider['users_url'], $payload, fta_provider_auth_headers($auth['token'], $provider));
        $status = strtolower(trim((string) ($response['json']['status'] ?? '')));
        if (($response['status'] ?? 0) === 200 && ($status === '' || $status === 'success')) {
            $data = is_array($response['json']['data'] ?? null) ? $response['json']['data'] : [];
            $username = trim((string) ($data['usercode'] ?? '')) ?: trim((string) $config['agent_username']) . $suffix;
            $memberId = fta_xzone_extract_user_id($data);
            return [
                'provider_key' => 'sports_x_zone',
                'provider_label' => $provider['label'],
                'external_username' => $username,
                'external_member_id' => $memberId > 0 ? $memberId : null,
                'external_password' => trim((string) ($data['password'] ?? $password)),
                'username_suffix' => $suffix,
                'download_url' => trim((string) ($data['url'] ?? '')) ?: null,
                'api_payload' => fta_provider_json_encode($payload),
                'api_response' => fta_provider_json_encode([
                    'login' => $auth['login_response']['json'] ?? $auth['login_response']['body'] ?? '',
                    'signup' => $response['json'] ?? $response['body'] ?? '',
                ]),
            ];
        }
        $message = strtolower(fta_provider_api_error_message($response, 'sports x zone signup failed.'));
        if (!str_contains($message, 'exist') && !str_contains($message, 'duplicate') && !str_contains($message, 'already')) {
            return fta_provider_debug_failure(fta_provider_api_error_message($response, 'sports x zone signup failed.'), [
                'provider' => 'sports x zone',
                'step' => 'member_signup',
                'url' => $provider['users_url'],
                'request_payload' => $payload,
                'http_status' => $response['status'] ?? null,
                'response_json' => $response['json'] ?? null,
                'response_body' => $response['body'] ?? '',
            ]);
        }
    }
    return fta_provider_debug_failure('Could not generate a unique sports x zone usercode.', ['provider' => 'sports x zone', 'step' => 'member_signup']);
}

function fta_xzone_resolve_user_id(array $auth, array $gameAccount): array
{
    $userId = (int) ($gameAccount['external_member_id'] ?? 0);
    if ($userId > 0) {
        return ['user_id' => $userId];
    }
    $apiResponse = fta_provider_json_decode($gameAccount['api_response'] ?? '');
    $userId = fta_xzone_extract_user_id($apiResponse['signup'] ?? []);
    if ($userId <= 0) {
        $password = fta_decrypt_secret((string) ($gameAccount['external_password_enc'] ?? ''), 'game_account_password');
        $login = fta_xzone_login_with_role($auth['provider'], (string) ($gameAccount['external_username'] ?? ''), $password, 'member');
        $userId = (int) ($login['user_id'] ?? 0);
    }
    if ($userId <= 0) {
        return fta_provider_debug_failure('sports x zone user id missing.', ['provider' => 'sports x zone', 'step' => 'user_lookup']);
    }
    fta_provider_update_game_account_member_id((int) ($gameAccount['id'] ?? 0), $userId);
    return ['user_id' => $userId];
}

function fta_xzone_sync_request_transaction(array $request, array $gameAccount, array $requestData): array
{
    if (!empty($requestData['provider_sync']['transaction_id'])) {
        return ['request_data' => $requestData];
    }
    $config = fta_provider_config_for_agent((int) ($gameAccount['agent_id'] ?? $request['agent_id'] ?? 0), 'sports_x_zone');
    if (!$config) {
        return ['error' => 'sports x zone API config is not ready for this agent.'];
    }
    $auth = fta_xzone_auth_context($config);
    if (!empty($auth['error'])) {
        return $auth;
    }
    $userIdInfo = fta_xzone_resolve_user_id($auth, $gameAccount);
    if (!empty($userIdInfo['error'])) {
        return $userIdInfo;
    }
    $provider = $auth['provider'];
    $action = ($request['request_type'] ?? '') === 'withdraw' ? 'withdrawl' : 'deposit';
    $payload = ['user_id' => (int) $userIdInfo['user_id'], 'amount' => fta_provider_normalize_amount($request['amount'] ?? 0), 'action' => $action];
    if ($action === 'withdrawl') {
        $payload['remark'] = '';
    }
    $response = fta_provider_http_json_post($provider['payments_url'], $payload, fta_provider_auth_headers($auth['token'], $provider));
    $status = strtolower(trim((string) ($response['json']['status'] ?? '')));
    if (($response['status'] ?? 0) !== 200 || $status === 'error' || $status === 'failed') {
        return fta_provider_debug_failure(fta_provider_api_error_message($response, 'sports x zone wallet sync failed.'), [
            'provider' => 'sports x zone',
            'step' => 'wallet_payment',
            'url' => $provider['payments_url'],
            'request_payload' => $payload,
            'http_status' => $response['status'] ?? null,
            'response_json' => $response['json'] ?? null,
            'response_body' => $response['body'] ?? '',
        ]);
    }
    $requestData['game_provider'] = $provider['label'];
    $requestData['game_provider_key'] = 'sports_x_zone';
    $requestData['game_username'] = $gameAccount['external_username'] ?? ($requestData['game_username'] ?? '-');
    $requestData['provider_sync'] = [
        'provider_key' => 'sports_x_zone',
        'provider_label' => $provider['label'],
        'member_id' => (int) $userIdInfo['user_id'],
        'transaction_id' => trim((string) ($response['json']['data']['id'] ?? $response['json']['id'] ?? '')) ?: 'xzone-' . date('YmdHis'),
        'action' => $action,
        'amount' => $payload['amount'],
        'message' => (string) ($response['json']['message'] ?? ''),
        'response_body' => fta_provider_string_excerpt($response['json'] ?? $response['body'] ?? ''),
    ];
    return ['request_data' => $requestData, 'provider_sync' => $requestData['provider_sync']];
}

function fta_ibet_build_absolute_url(string $baseUrl, string $relativeUrl): string
{
    $relativeUrl = trim($relativeUrl);
    if ($relativeUrl === '' || preg_match('#^https?://#i', $relativeUrl)) {
        return $relativeUrl !== '' ? $relativeUrl : $baseUrl;
    }
    $parts = parse_url($baseUrl);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        return $relativeUrl;
    }
    $origin = $parts['scheme'] . '://' . $parts['host'] . (!empty($parts['port']) ? ':' . $parts['port'] : '');
    if (str_starts_with($relativeUrl, '//')) {
        return $parts['scheme'] . ':' . $relativeUrl;
    }
    if (str_starts_with($relativeUrl, '/')) {
        return $origin . $relativeUrl;
    }
    $basePath = (string) ($parts['path'] ?? '/');
    $directory = preg_replace('#/[^/]*$#', '/', $basePath) ?: '/';
    $segments = array_merge(array_filter(explode('/', trim($directory, '/'))), array_filter(explode('/', trim($relativeUrl, '/'))));
    $normalized = [];
    foreach ($segments as $segment) {
        if ($segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($normalized);
            continue;
        }
        $normalized[] = $segment;
    }
    return $origin . '/' . implode('/', $normalized);
}

function fta_ibet_http_request(string $url, string $cookieFile, $postFields = null, string $referer = '', bool $allowRedirects = true): array
{
    if (!function_exists('curl_init')) {
        return ['error' => 'cURL extension is not available on this server.', 'status' => 0, 'body' => '', 'headers' => []];
    }
    $responseHeaders = [];
    $curl = curl_init($url);
    $headers = ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 'Origin: https://ag.ibet789.com'];
    if ($postFields !== null) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    if ($referer !== '') {
        $headers[] = 'Referer: ' . $referer;
    }
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 35,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => $allowRedirects,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_ENCODING => '',
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$responseHeaders): int {
            $trimmed = trim($headerLine);
            if ($trimmed !== '' && str_contains($trimmed, ':')) {
                [$name, $value] = explode(':', $trimmed, 2);
                $responseHeaders[strtolower(trim($name))] = trim($value);
            }
            return strlen($headerLine);
        },
    ]);
    if ($postFields !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, is_array($postFields) ? http_build_query($postFields, '', '&') : (string) $postFields);
    }
    $body = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $effectiveUrl = (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
    curl_close($curl);
    return ['status' => $status, 'body' => $body === false ? '' : (string) $body, 'headers' => $responseHeaders, 'error' => $error, 'url' => $effectiveUrl ?: $url];
}

function fta_ibet_load_dom(string $html): ?DOMDocument
{
    if (!class_exists('DOMDocument') || $html === '') {
        return null;
    }
    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return $loaded ? $dom : null;
}

function fta_ibet_hidden_fields(string $html): array
{
    $dom = fta_ibet_load_dom($html);
    if (!$dom) {
        return [];
    }
    $allowed = array_fill_keys(['__VIEWSTATE', '__VIEWSTATEGENERATOR', '__EVENTVALIDATION', '__VIEWSTATEENCRYPTED'], true);
    $fields = [];
    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//input[@name]') as $input) {
        $name = trim((string) $input->getAttribute('name'));
        if ($name !== '' && isset($allowed[$name])) {
            $fields[$name] = (string) $input->getAttribute('value');
        }
    }
    return $fields;
}

function fta_ibet_alert_message(string $html): string
{
    if (preg_match('/alert\s*\((?:\'([^\']*)\'|"([^"]*)")\)/i', $html, $matches)) {
        return html_entity_decode(stripslashes((string) ($matches[1] !== '' ? $matches[1] : ($matches[2] ?? ''))), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return '';
}

function fta_ibet_option_value(string $html, string $targetUser): string
{
    $dom = fta_ibet_load_dom($html);
    if (!$dom) {
        return '';
    }
    $target = strtolower(trim($targetUser));
    $contains = '';
    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//select[@id="MemberList1_lstAccounts"]/option') as $option) {
        $value = trim((string) $option->getAttribute('value'));
        $label = trim(preg_replace('/\s+/', ' ', (string) $option->textContent));
        $valueLower = strtolower($value);
        $labelLower = strtolower($label);
        if ($valueLower === $target || $labelLower === $target) {
            return $value;
        }
        if ($contains === '' && (str_contains($valueLower, $target) || str_contains($labelLower, $target))) {
            $contains = $value;
        }
    }
    return $contains;
}

function fta_ibet_form_action_url(string $html, string $baseUrl): string
{
    $dom = fta_ibet_load_dom($html);
    if (!$dom) {
        return $baseUrl;
    }
    $form = (new DOMXPath($dom))->query('//form[@id="form1"]')->item(0);
    return $form ? fta_ibet_build_absolute_url($baseUrl, (string) $form->getAttribute('action')) : $baseUrl;
}

function fta_ibet_amount_units($amountMmk): string
{
    $units = round(((float) $amountMmk) / 1000, 3);
    if ($units <= 0) {
        return '';
    }
    return floor($units) === $units ? (string) (int) $units : rtrim(rtrim(number_format($units, 3, '.', ''), '0'), '.');
}

function fta_ibet_login(array $config): array
{
    $provider = $config['provider'];
    $cookieFile = tempnam(sys_get_temp_dir(), 'fta_ibet_');
    if ($cookieFile === false) {
        return fta_provider_debug_failure('ibet login failed.', ['provider' => 'ibet', 'step' => 'agent_login', 'api_message' => 'Could not create cookie file.']);
    }
    $loginPage = fta_ibet_http_request($provider['login_url'], $cookieFile, null, $provider['referer']);
    if (($loginPage['status'] ?? 0) !== 200 || !empty($loginPage['error'])) {
        @unlink($cookieFile);
        return fta_provider_debug_failure('ibet login failed.', ['provider' => 'ibet', 'step' => 'agent_login_page', 'http_status' => $loginPage['status'] ?? null, 'api_message' => $loginPage['error'] ?? '', 'response_body' => $loginPage['body'] ?? '']);
    }
    $payload = array_merge(['__EVENTTARGET' => 'btnSignIn', '__EVENTARGUMENT' => ''], fta_ibet_hidden_fields($loginPage['body'] ?? ''), [
        'txtUserName' => $config['agent_username'],
        'txtPassword' => $config['agent_password'],
    ]);
    $login = fta_ibet_http_request($provider['login_url'], $cookieFile, $payload, $provider['referer'], false);
    $location = trim((string) ($login['headers']['location'] ?? ''));
    if (!in_array((int) ($login['status'] ?? 0), [301, 302, 303], true) || $location === '') {
        @unlink($cookieFile);
        $message = fta_ibet_alert_message($login['body'] ?? '');
        return fta_provider_debug_failure($message !== '' ? $message : 'ibet login failed.', ['provider' => 'ibet', 'step' => 'agent_login', 'http_status' => $login['status'] ?? null, 'response_body' => $login['body'] ?? '']);
    }
    $nextUrl = fta_ibet_build_absolute_url($provider['login_url'], $location);
    $landing = fta_ibet_http_request($nextUrl, $cookieFile, null, $provider['login_url']);
    if (($landing['status'] ?? 0) >= 400 || !empty($landing['error'])) {
        @unlink($cookieFile);
        return fta_provider_debug_failure('ibet login failed.', ['provider' => 'ibet', 'step' => 'agent_login_redirect', 'url' => $nextUrl, 'http_status' => $landing['status'] ?? null, 'api_message' => $landing['error'] ?? '', 'response_body' => $landing['body'] ?? '']);
    }
    return ['provider' => $provider, 'cookie_file' => $cookieFile, 'login_page_response' => $loginPage, 'login_response' => $login, 'landing_response' => $landing];
}

function fta_ibet_process_transaction(array $config, string $targetUser, $amountMmk, string $requestType): array
{
    $context = fta_ibet_login($config);
    if (!empty($context['error'])) {
        return $context;
    }
    try {
        $provider = $context['provider'];
        $cookie = $context['cookie_file'];
        $paymentPage = fta_ibet_http_request($provider['payments_url'], $cookie, null, $provider['login_url']);
        if (($paymentPage['status'] ?? 0) !== 200 || !empty($paymentPage['error'])) {
            return fta_provider_debug_failure('ibet payment page failed.', ['provider' => 'ibet', 'step' => 'payment_page', 'http_status' => $paymentPage['status'] ?? null, 'api_message' => $paymentPage['error'] ?? '', 'response_body' => $paymentPage['body'] ?? '']);
        }
        $searchPayload = array_merge(['__EVENTTARGET' => 'btnSearch', '__EVENTARGUMENT' => ''], fta_ibet_hidden_fields($paymentPage['body'] ?? ''), [
            'txtSearchUserName' => $targetUser,
            'MemberList1$lstAccounts' => '',
            'ctl03$txtAmount' => '',
            'ctl03$txtRemark' => '',
        ]);
        $search = fta_ibet_http_request($provider['payments_url'], $cookie, $searchPayload, $provider['payments_url']);
        if (($search['status'] ?? 0) !== 200 || !empty($search['error'])) {
            return fta_provider_debug_failure('ibet member lookup failed.', ['provider' => 'ibet', 'step' => 'member_search', 'http_status' => $search['status'] ?? null, 'response_body' => $search['body'] ?? '']);
        }
        $targetValue = fta_ibet_option_value($search['body'] ?? '', $targetUser);
        if ($targetValue === '') {
            return fta_provider_debug_failure('ibet member not found.', ['provider' => 'ibet', 'step' => 'member_search', 'request_payload' => ['username' => $targetUser], 'response_body' => $search['body'] ?? '']);
        }
        $selectPayload = array_merge(['__EVENTTARGET' => 'MemberList1$lstAccounts', '__EVENTARGUMENT' => ''], fta_ibet_hidden_fields($search['body'] ?? ''), [
            'txtSearchUserName' => $targetUser,
            'MemberList1$lstAccounts' => $targetValue,
            'ctl03$txtAmount' => '',
            'ctl03$txtRemark' => '',
        ]);
        $select = fta_ibet_http_request($provider['payments_url'], $cookie, $selectPayload, $provider['payments_url']);
        if (($select['status'] ?? 0) !== 200 || !empty($select['error'])) {
            return fta_provider_debug_failure('ibet member select failed.', ['provider' => 'ibet', 'step' => 'member_select', 'http_status' => $select['status'] ?? null, 'response_body' => $select['body'] ?? '']);
        }
        $units = fta_ibet_amount_units($amountMmk);
        if ($units === '') {
            return fta_provider_debug_failure('ibet amount is invalid.', ['provider' => 'ibet', 'step' => 'wallet_payment', 'request_payload' => ['amount_mmk' => $amountMmk]]);
        }
        $type = strtolower($requestType) === 'withdraw' ? 'withdraw' : 'deposit';
        $submitted = $type === 'withdraw' ? '-' . ltrim($units, '-') : $units;
        $finalUrl = fta_ibet_form_action_url($select['body'] ?? '', $select['url'] ?? $provider['payments_url']);
        $transferPayload = array_merge(['__EVENTTARGET' => 'ctl03$btnSave', '__EVENTARGUMENT' => ''], fta_ibet_hidden_fields($select['body'] ?? ''), [
            'txtSearchUserName' => $targetUser,
            'MemberList1$lstAccounts' => $targetValue,
            'ctl03$txtAmount' => $submitted,
            'ctl03$txtRemark' => 'MSY',
            'ctl03$btnSave' => 'Submit',
        ]);
        $transfer = fta_ibet_http_request($finalUrl, $cookie, $transferPayload, $finalUrl);
        if (($transfer['status'] ?? 0) !== 200 || !empty($transfer['error'])) {
            return fta_provider_debug_failure('ibet transaction failed.', ['provider' => 'ibet', 'step' => 'wallet_payment', 'http_status' => $transfer['status'] ?? null, 'api_message' => $transfer['error'] ?? '', 'response_body' => $transfer['body'] ?? '']);
        }
        $message = fta_ibet_alert_message($transfer['body'] ?? '');
        $lower = strtolower($message);
        $success = $message !== '' && (str_contains($lower, 'successful') || str_contains($lower, 'success'));
        if (!$success && str_contains((string) ($transfer['body'] ?? ''), 'ctl03_g')) {
            $success = $message === '' || (!str_contains($lower, 'fail') && !str_contains($lower, 'error'));
        }
        if (!$success) {
            return fta_provider_debug_failure($message !== '' ? $message : 'ibet transaction failed.', ['provider' => 'ibet', 'step' => 'wallet_payment', 'response_body' => $transfer['body'] ?? '']);
        }
        return [
            'submitted_amount' => $submitted,
            'provider_amount_units' => $units,
            'message' => $message !== '' ? $message : ($type === 'withdraw' ? 'withdraw success' : 'deposit success'),
            'response_body' => fta_provider_string_excerpt((string) ($transfer['body'] ?? '')),
        ];
    } finally {
        $cookieFile = trim((string) ($context['cookie_file'] ?? ''));
        if ($cookieFile !== '' && is_file($cookieFile)) {
            @unlink($cookieFile);
        }
    }
}

function fta_ibet_sync_request_transaction(array $request, array $gameAccount, array $requestData): array
{
    if (!empty($requestData['provider_sync']['transaction_id'])) {
        return ['request_data' => $requestData];
    }
    $config = fta_provider_config_for_agent((int) ($gameAccount['agent_id'] ?? $request['agent_id'] ?? 0), 'ibet');
    if (!$config) {
        return ['error' => 'iBet 789 API config is not ready for this agent.'];
    }
    $type = ($request['request_type'] ?? '') === 'withdraw' ? 'withdraw' : 'deposit';
    $result = fta_ibet_process_transaction($config, (string) ($gameAccount['external_username'] ?? ''), $request['amount'] ?? 0, $type);
    if (!empty($result['error'])) {
        return $result;
    }
    $requestData['game_provider'] = $config['provider']['label'];
    $requestData['game_provider_key'] = 'ibet';
    $requestData['game_username'] = $gameAccount['external_username'] ?? ($requestData['game_username'] ?? '-');
    $requestData['provider_sync'] = [
        'provider_key' => 'ibet',
        'provider_label' => $config['provider']['label'],
        'transaction_id' => 'ibet-' . $type . '-' . date('YmdHis'),
        'command' => $type,
        'request_amount_mmk' => (float) ($request['amount'] ?? 0),
        'provider_amount_units' => (float) ($result['provider_amount_units'] ?? 0),
        'submitted_amount' => (string) ($result['submitted_amount'] ?? ''),
        'message' => (string) ($result['message'] ?? ''),
        'response_body' => fta_provider_string_excerpt((string) ($result['response_body'] ?? '')),
    ];
    return ['request_data' => $requestData, 'provider_sync' => $requestData['provider_sync']];
}

function fta_provider_update_game_account_member_id(int $accountId, int $memberId): void
{
    if ($accountId <= 0 || $memberId <= 0) {
        return;
    }
    db()->prepare('UPDATE user_game_accounts SET external_member_id = :member_id WHERE id = :id')->execute([
        'member_id' => $memberId,
        'id' => $accountId,
    ]);
}

function fta_provider_create_user_game_account(array $user, string $providerKey): array
{
    $providerKey = strtolower(trim($providerKey));
    $agentId = (int) ($user['agent_id'] ?? 0);
    $config = fta_provider_config_for_agent($agentId, $providerKey);
    if (!$config) {
        throw new RuntimeException('This game provider API is not available yet.');
    }
    if (empty($config['provider']['supports_auto_create'])) {
        throw new RuntimeException($config['provider']['label'] . ' account must be linked manually by your agent.');
    }

    $result = match ($providerKey) {
        '555mix' => fta_555mix_create_member_account($config, $user),
        'sports_899' => fta_sports899_create_member_account($config, $user),
        'sports_x_zone' => fta_xzone_create_member_account($config, $user),
        default => ['error' => 'Create account API is not implemented for this provider.'],
    };

    if (!empty($result['error'])) {
        $message = (string) $result['error'];
        $reference = trim((string) ($result['debug_reference'] ?? ''));
        throw new RuntimeException($reference !== '' ? $message . ' Ref: ' . $reference : $message);
    }

    $statement = db()->prepare('
        INSERT INTO user_game_accounts (
            user_id, agent_id, provider_key, provider_label, external_username,
            external_member_id, external_password_enc, username_suffix, download_url,
            api_payload, api_response
        ) VALUES (
            :user_id, :agent_id, :provider_key, :provider_label, :external_username,
            :external_member_id, :external_password_enc, :username_suffix, :download_url,
            :api_payload, :api_response
        )
    ');
    $statement->execute([
        'user_id' => (int) $user['id'],
        'agent_id' => $agentId,
        'provider_key' => (string) $result['provider_key'],
        'provider_label' => (string) $result['provider_label'],
        'external_username' => (string) $result['external_username'],
        'external_member_id' => $result['external_member_id'] ?? null,
        'external_password_enc' => fta_encrypt_secret((string) ($result['external_password'] ?? ''), 'game_account_password'),
        'username_suffix' => (string) ($result['username_suffix'] ?? ''),
        'download_url' => $result['download_url'] ?? null,
        'api_payload' => (string) ($result['api_payload'] ?? ''),
        'api_response' => (string) ($result['api_response'] ?? ''),
    ]);

    $accountId = (int) db()->lastInsertId();
    fta_insert_user_notification(
        (int) $user['id'],
        $agentId,
        'Game account ready',
        "Your {$result['provider_label']} account is ready.\nUsername: {$result['external_username']}\nPassword: {$result['external_password']}"
    );

    $created = db()->prepare('SELECT * FROM user_game_accounts WHERE id = :id');
    $created->execute(['id' => $accountId]);
    return $created->fetch() ?: [];
}

function fta_provider_sync_unit_request(array $request): array
{
    $requestData = fta_provider_json_decode($request['request_data'] ?? '');
    if (!empty($requestData['provider_sync']['transaction_id'])) {
        return ['request_data' => $requestData, 'provider_sync' => $requestData['provider_sync']];
    }

    $statement = db()->prepare('SELECT * FROM user_game_accounts WHERE id = :id AND user_id = :user_id LIMIT 1');
    $statement->execute([
        'id' => (int) ($request['game_account_id'] ?? 0),
        'user_id' => (int) ($request['user_id'] ?? 0),
    ]);
    $gameAccount = $statement->fetch();
    if (!$gameAccount) {
        return ['error' => 'This request is not linked to a valid game account.'];
    }

    return match ((string) ($gameAccount['provider_key'] ?? '')) {
        '555mix' => fta_555mix_sync_request_transaction($request, $gameAccount, $requestData),
        'sports_899' => fta_sports899_sync_request_transaction($request, $gameAccount, $requestData),
        'sports_x_zone' => fta_xzone_sync_request_transaction($request, $gameAccount, $requestData),
        'ibet' => fta_ibet_sync_request_transaction($request, $gameAccount, $requestData),
        default => ['error' => 'Provider sync API is not implemented for this account.'],
    };
}
