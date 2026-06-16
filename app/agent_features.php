<?php
declare(strict_types=1);

function fta_admin_base_dir(): string
{
    return defined('FTA_ADMIN_DIR') ? (string) FTA_ADMIN_DIR : 'hha';
}

function fta_admin_url(string $path = ''): string
{
    return fta_base_url(trim(fta_admin_base_dir(), '/') . '/' . ltrim($path, '/'));
}

function fta_staff_is_active(array $staff): bool
{
    if (empty($staff['active'])) {
        return false;
    }

    $expiresAt = trim((string) ($staff['expires_at'] ?? ''));
    return $expiresAt === '' || strtotime($expiresAt) === false || strtotime($expiresAt) >= time();
}

function fta_staff_by_id(int $id): ?array
{
    $statement = db()->prepare('SELECT * FROM staff_accounts WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $staff = $statement->fetch();
    return is_array($staff) ? $staff : null;
}

function fta_staff_by_username(string $username): ?array
{
    $statement = db()->prepare('SELECT * FROM staff_accounts WHERE username = :username LIMIT 1');
    $statement->execute(['username' => trim($username)]);
    $staff = $statement->fetch();
    return is_array($staff) ? $staff : null;
}

function fta_staff_by_promo_code(string $promoCode): ?array
{
    $promoCode = strtoupper(trim($promoCode));
    if ($promoCode === '') {
        return null;
    }

    $statement = db()->prepare('
        SELECT *
        FROM staff_accounts
        WHERE UPPER(promo_code) = :promo_code
          AND role = "agent"
        LIMIT 1
    ');
    $statement->execute(['promo_code' => $promoCode]);
    $staff = $statement->fetch();
    return is_array($staff) && fta_staff_is_active($staff) ? $staff : null;
}

function fta_staff_login(string $username, string $password, array $roles = []): ?array
{
    $staff = fta_staff_by_username($username);
    if (!$staff || !fta_staff_is_active($staff) || !password_verify($password, (string) $staff['password_hash'])) {
        return null;
    }

    if ($roles && !in_array((string) $staff['role'], $roles, true)) {
        return null;
    }

    if (fta_password_needs_rehash_secure((string) $staff['password_hash'])) {
        $statement = db()->prepare('UPDATE staff_accounts SET password_hash = :hash WHERE id = :id');
        $statement->execute([
            'hash' => fta_password_hash_secure($password),
            'id' => (int) $staff['id'],
        ]);
    }

    db()->prepare('UPDATE staff_accounts SET last_login_at = NOW() WHERE id = :id')->execute(['id' => (int) $staff['id']]);
    return $staff;
}

function fta_login_staff(array $staff): void
{
    fta_start_session();
    session_regenerate_id(true);
    $_SESSION['staff_user_id'] = (int) $staff['id'];
    $_SESSION['staff_role'] = (string) $staff['role'];
    if ((string) $staff['role'] === 'super') {
        $_SESSION['admin_user_id'] = (int) $staff['id'];
    }
}

function fta_current_staff(array $roles = []): ?array
{
    fta_start_session();
    $id = (int) ($_SESSION['staff_user_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    $staff = fta_staff_by_id($id);
    if (!$staff || !fta_staff_is_active($staff)) {
        unset($_SESSION['staff_user_id'], $_SESSION['staff_role'], $_SESSION['admin_user_id']);
        return null;
    }

    if ($roles && !in_array((string) $staff['role'], $roles, true)) {
        return null;
    }

    return $staff;
}

function fta_logout_staff(): void
{
    fta_start_session();
    unset($_SESSION['staff_user_id'], $_SESSION['staff_role'], $_SESSION['admin_user_id']);
    session_regenerate_id(true);
}

function fta_staff_display_name(array $staff): string
{
    return trim((string) ($staff['display_name'] ?? '')) ?: (string) ($staff['username'] ?? 'Agent');
}

function fta_all_agents(int $limit = 500): array
{
    $statement = db()->prepare('
        SELECT s.*,
               (SELECT COUNT(*) FROM users u WHERE u.agent_id = s.id) AS user_count,
               (SELECT COALESCE(SUM(amount), 0) FROM unit_requests r WHERE r.agent_id = s.id AND r.request_type = "deposit" AND r.status = "approved") AS deposit_total,
               (SELECT COALESCE(SUM(amount), 0) FROM unit_requests r WHERE r.agent_id = s.id AND r.request_type = "withdraw" AND r.status = "approved") AS withdraw_total
        FROM staff_accounts s
        WHERE s.role = "agent"
        ORDER BY s.created_at DESC
        LIMIT :limit
    ');
    $statement->bindValue('limit', max(1, min(1000, $limit)), PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function fta_create_agent_account(array $input, int $createdBy): void
{
    $username = trim((string) ($input['username'] ?? ''));
    $displayName = trim((string) ($input['display_name'] ?? ''));
    $promoCode = strtoupper(trim((string) ($input['promo_code'] ?? '')));
    $password = (string) ($input['password'] ?? '');
    $expiresAt = trim((string) ($input['expires_at'] ?? ''));

    if (!preg_match('/^[A-Za-z0-9_.-]{3,80}$/', $username)) {
        throw new RuntimeException('Agent username must be 3-80 letters, numbers, dot, dash, or underscore.');
    }
    if (!preg_match('/^[A-Z0-9_-]{3,80}$/', $promoCode)) {
        throw new RuntimeException('Promocode must be 3-80 letters or numbers.');
    }
    if (strlen($password) < 8) {
        throw new RuntimeException('Agent password must be at least 8 characters.');
    }
    if ($expiresAt !== '' && strtotime($expiresAt) === false) {
        throw new RuntimeException('Expiry date is invalid.');
    }

    $statement = db()->prepare('
        INSERT INTO staff_accounts (role, username, display_name, password_hash, promo_code, expires_at, active, created_by)
        VALUES ("agent", :username, :display_name, :password_hash, :promo_code, :expires_at, 1, :created_by)
    ');
    $statement->execute([
        'username' => $username,
        'display_name' => $displayName,
        'password_hash' => fta_password_hash_secure($password),
        'promo_code' => $promoCode,
        'expires_at' => $expiresAt === '' ? null : date('Y-m-d H:i:s', strtotime($expiresAt)),
        'created_by' => $createdBy ?: null,
    ]);
}

function fta_update_agent_account(array $input): void
{
    $id = (int) ($input['agent_id'] ?? 0);
    $displayName = trim((string) ($input['display_name'] ?? ''));
    $promoCode = strtoupper(trim((string) ($input['promo_code'] ?? '')));
    $expiresAt = trim((string) ($input['expires_at'] ?? ''));
    $active = !empty($input['active']) ? 1 : 0;

    if ($id <= 0) {
        throw new RuntimeException('Agent is required.');
    }
    if (!preg_match('/^[A-Z0-9_-]{3,80}$/', $promoCode)) {
        throw new RuntimeException('Promocode must be 3-80 letters or numbers.');
    }
    if ($expiresAt !== '' && strtotime($expiresAt) === false) {
        throw new RuntimeException('Expiry date is invalid.');
    }

    $statement = db()->prepare('
        UPDATE staff_accounts
        SET display_name = :display_name,
            promo_code = :promo_code,
            expires_at = :expires_at,
            active = :active
        WHERE id = :id AND role = "agent"
    ');
    $statement->execute([
        'id' => $id,
        'display_name' => $displayName,
        'promo_code' => $promoCode,
        'expires_at' => $expiresAt === '' ? null : date('Y-m-d H:i:s', strtotime($expiresAt)),
        'active' => $active,
    ]);
}

function fta_change_staff_password(int $staffId, string $password): void
{
    if ($staffId <= 0) {
        throw new RuntimeException('Account is required.');
    }
    if (strlen($password) < 8) {
        throw new RuntimeException('Password must be at least 8 characters.');
    }

    $statement = db()->prepare('UPDATE staff_accounts SET password_hash = :hash WHERE id = :id');
    $statement->execute([
        'hash' => fta_password_hash_secure($password),
        'id' => $staffId,
    ]);
}

function fta_agent_users(int $agentId, int $limit = 300): array
{
    $statement = db()->prepare('
        SELECT u.*,
               COUNT(s.id) AS prediction_count,
               SUM(CASE WHEN s.result_status = "win" THEN 1 ELSE 0 END) AS win_count
        FROM users u
        LEFT JOIN submissions s ON s.user_id = u.id
        WHERE u.agent_id = :agent_id
        GROUP BY u.id
        ORDER BY u.created_at DESC
        LIMIT :limit
    ');
    $statement->bindValue('agent_id', $agentId, PDO::PARAM_INT);
    $statement->bindValue('limit', max(1, min(500, $limit)), PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function fta_agent_user_detail(int $agentId, int $userId): ?array
{
    if ($agentId <= 0 || $userId <= 0) {
        return null;
    }

    $statement = db()->prepare('
        SELECT u.*,
               (SELECT COUNT(*) FROM submissions s WHERE s.user_id = u.id) AS prediction_count,
               (SELECT COUNT(*) FROM submissions s WHERE s.user_id = u.id AND s.result_status = "win") AS win_count,
               (SELECT COUNT(*) FROM submissions s WHERE s.user_id = u.id AND s.result_status = "lose") AS lose_count,
               (SELECT COUNT(*) FROM submissions s WHERE s.user_id = u.id AND (s.result_status = "pending" OR s.result_status IS NULL)) AS pending_prediction_count,
               (SELECT COUNT(*) FROM unit_requests r WHERE r.user_id = u.id) AS unit_request_count,
               (SELECT COALESCE(SUM(r.amount), 0) FROM unit_requests r WHERE r.user_id = u.id AND r.request_type = "deposit" AND r.status = "approved") AS deposit_total,
               (SELECT COALESCE(SUM(r.amount), 0) FROM unit_requests r WHERE r.user_id = u.id AND r.request_type = "withdraw" AND r.status = "approved") AS withdraw_total,
               (SELECT COUNT(*) FROM unit_requests r WHERE r.user_id = u.id AND r.status = "pending") AS pending_unit_count
        FROM users u
        WHERE u.id = :user_id
          AND u.agent_id = :agent_id
        LIMIT 1
    ');
    $statement->execute([
        'user_id' => $userId,
        'agent_id' => $agentId,
    ]);
    $user = $statement->fetch();
    return is_array($user) ? $user : null;
}

function fta_agent_game_accounts_by_user(int $agentId): array
{
    $statement = db()->prepare('
        SELECT g.*, u.full_name, u.phone_e164
        FROM user_game_accounts g
        INNER JOIN users u ON u.id = g.user_id
        WHERE g.agent_id = :agent_id
        ORDER BY u.created_at DESC, g.provider_label ASC
    ');
    $statement->execute(['agent_id' => $agentId]);
    $grouped = [];
    foreach ($statement->fetchAll() as $account) {
        $grouped[(int) $account['user_id']][] = $account;
    }
    return $grouped;
}

function fta_agent_analysis(?int $agentId = null): array
{
    $where = $agentId ? 'WHERE u.agent_id = :agent_id' : '';
    $statement = db()->prepare("
        SELECT
            COUNT(*) AS total_users,
            SUM(CASE WHEN DATE(u.created_at) = CURRENT_DATE THEN 1 ELSE 0 END) AS new_users_today
        FROM users u
        {$where}
    ");
    if ($agentId) {
        $statement->bindValue('agent_id', $agentId, PDO::PARAM_INT);
    }
    $statement->execute();
    $userStats = $statement->fetch() ?: [];
    $totalUsers = (int) ($userStats['total_users'] ?? 0);

    $requestWhere = $agentId ? 'WHERE agent_id = :agent_id' : '';
    $requestStatement = db()->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN request_type = 'deposit' AND status = 'approved' THEN amount ELSE 0 END), 0) AS deposit_total,
            COALESCE(SUM(CASE WHEN request_type = 'withdraw' AND status = 'approved' THEN amount ELSE 0 END), 0) AS withdraw_total,
            COALESCE(SUM(CASE WHEN request_type = 'deposit' AND DATE(created_at) = CURRENT_DATE THEN amount ELSE 0 END), 0) AS today_deposit_total,
            COALESCE(SUM(CASE WHEN request_type = 'withdraw' AND DATE(created_at) = CURRENT_DATE THEN amount ELSE 0 END), 0) AS today_withdraw_total,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS pending_amount_total,
            COALESCE(SUM(CASE WHEN status = 'pending' AND request_type = 'deposit' THEN amount ELSE 0 END), 0) AS pending_deposit_amount,
            COALESCE(SUM(CASE WHEN status = 'pending' AND request_type = 'withdraw' THEN amount ELSE 0 END), 0) AS pending_withdraw_amount,
            COUNT(*) AS total_requests,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_requests,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_requests,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_requests,
            SUM(CASE WHEN status = 'pending' AND request_type = 'deposit' THEN 1 ELSE 0 END) AS pending_deposit_requests,
            SUM(CASE WHEN status = 'pending' AND request_type = 'withdraw' THEN 1 ELSE 0 END) AS pending_withdraw_requests,
            SUM(CASE WHEN status = 'approved' AND request_type = 'deposit' THEN 1 ELSE 0 END) AS approved_deposit_requests,
            SUM(CASE WHEN status = 'approved' AND request_type = 'withdraw' THEN 1 ELSE 0 END) AS approved_withdraw_requests,
            SUM(CASE WHEN status = 'rejected' AND request_type = 'deposit' THEN 1 ELSE 0 END) AS rejected_deposit_requests,
            SUM(CASE WHEN status = 'rejected' AND request_type = 'withdraw' THEN 1 ELSE 0 END) AS rejected_withdraw_requests,
            SUM(CASE WHEN DATE(created_at) = CURRENT_DATE THEN 1 ELSE 0 END) AS today_requests,
            SUM(CASE WHEN status = 'approved' AND DATE(reviewed_at) = CURRENT_DATE THEN 1 ELSE 0 END) AS today_approved_requests,
            SUM(CASE WHEN status = 'rejected' AND DATE(reviewed_at) = CURRENT_DATE THEN 1 ELSE 0 END) AS today_rejected_requests,
            COALESCE(AVG(CASE WHEN reviewed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, reviewed_at) ELSE NULL END), 0) AS avg_review_minutes
        FROM unit_requests
        {$requestWhere}
    ");
    if ($agentId) {
        $requestStatement->bindValue('agent_id', $agentId, PDO::PARAM_INT);
    }
    $requestStatement->execute();
    $request = $requestStatement->fetch() ?: [];

    return [
        'total_users' => $totalUsers,
        'new_users_today' => (int) ($userStats['new_users_today'] ?? 0),
        'deposit_total' => (float) ($request['deposit_total'] ?? 0),
        'withdraw_total' => (float) ($request['withdraw_total'] ?? 0),
        'today_deposit_total' => (float) ($request['today_deposit_total'] ?? 0),
        'today_withdraw_total' => (float) ($request['today_withdraw_total'] ?? 0),
        'pending_amount_total' => (float) ($request['pending_amount_total'] ?? 0),
        'pending_deposit_amount' => (float) ($request['pending_deposit_amount'] ?? 0),
        'pending_withdraw_amount' => (float) ($request['pending_withdraw_amount'] ?? 0),
        'total_requests' => (int) ($request['total_requests'] ?? 0),
        'pending_requests' => (int) ($request['pending_requests'] ?? 0),
        'approved_requests' => (int) ($request['approved_requests'] ?? 0),
        'rejected_requests' => (int) ($request['rejected_requests'] ?? 0),
        'pending_deposit_requests' => (int) ($request['pending_deposit_requests'] ?? 0),
        'pending_withdraw_requests' => (int) ($request['pending_withdraw_requests'] ?? 0),
        'approved_deposit_requests' => (int) ($request['approved_deposit_requests'] ?? 0),
        'approved_withdraw_requests' => (int) ($request['approved_withdraw_requests'] ?? 0),
        'rejected_deposit_requests' => (int) ($request['rejected_deposit_requests'] ?? 0),
        'rejected_withdraw_requests' => (int) ($request['rejected_withdraw_requests'] ?? 0),
        'today_requests' => (int) ($request['today_requests'] ?? 0),
        'today_approved_requests' => (int) ($request['today_approved_requests'] ?? 0),
        'today_rejected_requests' => (int) ($request['today_rejected_requests'] ?? 0),
        'avg_review_minutes' => (float) ($request['avg_review_minutes'] ?? 0),
    ];
}

function fta_secret_key(string $purpose): string
{
    $material = FTA_DB_NAME . '|' . FTA_DB_USER . '|' . FTA_DB_PASS . '|' . FTA_APP_NAME . '|' . $purpose;
    return hash('sha256', $material, true);
}

function fta_encrypt_secret(string $value, string $purpose): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (str_starts_with($value, 'enc::')) {
        return $value;
    }
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('OpenSSL is required for secure credential storage.');
    }

    $nonce = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($value, 'aes-256-gcm', fta_secret_key($purpose), OPENSSL_RAW_DATA, $nonce, $tag, 'fta');
    if (!is_string($cipher) || $tag === '') {
        throw new RuntimeException('Credential encryption failed.');
    }

    return 'enc::' . base64_encode($nonce . $tag . $cipher);
}

function fta_decrypt_secret(string $value, string $purpose): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (!str_starts_with($value, 'enc::')) {
        return $value;
    }
    if (!function_exists('openssl_decrypt')) {
        return '';
    }

    $raw = base64_decode(substr($value, 5), true);
    if (!is_string($raw) || strlen($raw) <= 28) {
        return '';
    }

    $nonce = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', fta_secret_key($purpose), OPENSSL_RAW_DATA, $nonce, $tag, 'fta');
    return is_string($plain) ? $plain : '';
}

function fta_provider_agent_username(int $agentId, string $providerKey): string
{
    $config = fta_agent_provider_config($agentId, $providerKey);
    if (!$config || empty($config['active'])) {
        return '';
    }
    return fta_decrypt_secret((string) ($config['agent_username_enc'] ?? ''), 'provider_username');
}

function fta_validate_connected_username(int $agentId, string $providerKey, string $username): void
{
    $agentUsername = fta_provider_agent_username($agentId, $providerKey);
    if ($agentUsername === '') {
        throw new RuntimeException('This provider is not connected by your agent yet.');
    }
    $candidate = strtolower(trim($username));
    $prefix = strtolower(trim($agentUsername));
    if ($candidate === '' || strlen($candidate) > 120) {
        throw new RuntimeException('Game username is required.');
    }
    if (!str_starts_with($candidate, $prefix)) {
        throw new RuntimeException('This username does not match your promocode owner agent account prefix.');
    }
    if (strlen($candidate) < strlen($prefix)) {
        throw new RuntimeException('Game username is too short.');
    }
}

function fta_connect_user_game_account(array $user, string $providerKey, string $externalUsername): array
{
    fta_action_rate_limit('connect_game_account', (string) ($user['id'] ?? fta_client_ip()), 15);
    $agentId = (int) ($user['agent_id'] ?? 0);
    if ($agentId <= 0) {
        throw new RuntimeException('Agent promocode is not linked to this account.');
    }

    $providerKey = strtolower(trim($providerKey));
    $config = fta_agent_provider_config($agentId, $providerKey);
    if (!$config || empty($config['active'])) {
        throw new RuntimeException('This game provider is not available yet.');
    }

    $existing = db()->prepare('SELECT * FROM user_game_accounts WHERE user_id = :user_id AND provider_key = :provider_key LIMIT 1');
    $existing->execute(['user_id' => (int) $user['id'], 'provider_key' => $providerKey]);
    if ($existing->fetch()) {
        throw new RuntimeException('This provider account is already linked. Ask your agent to edit it if it is wrong.');
    }

    $externalUsername = trim($externalUsername);
    fta_validate_connected_username($agentId, $providerKey, $externalUsername);

    $duplicate = db()->prepare('SELECT id FROM user_game_accounts WHERE agent_id = :agent_id AND provider_key = :provider_key AND LOWER(external_username) = LOWER(:username) LIMIT 1');
    $duplicate->execute([
        'agent_id' => $agentId,
        'provider_key' => $providerKey,
        'username' => $externalUsername,
    ]);
    if ($duplicate->fetch()) {
        throw new RuntimeException('This game username is already connected.');
    }

    $payload = json_encode(['connected_by_user' => true, 'connected_at' => date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $statement = db()->prepare('
        INSERT INTO user_game_accounts (
            user_id, agent_id, provider_key, provider_label, external_username,
            external_password_enc, username_suffix, download_url, api_payload, api_response
        ) VALUES (
            :user_id, :agent_id, :provider_key, :provider_label, :external_username,
            "", "", NULL, :api_payload, ""
        )
    ');
    $statement->execute([
        'user_id' => (int) $user['id'],
        'agent_id' => $agentId,
        'provider_key' => $providerKey,
        'provider_label' => fta_provider_label($providerKey),
        'external_username' => $externalUsername,
        'api_payload' => $payload,
    ]);

    $created = db()->prepare('SELECT * FROM user_game_accounts WHERE id = :id');
    $created->execute(['id' => (int) db()->lastInsertId()]);
    return $created->fetch() ?: [];
}

function fta_update_user_game_account_for_agent(int $agentId, int $accountId, string $externalUsername): void
{
    $statement = db()->prepare('SELECT * FROM user_game_accounts WHERE id = :id AND agent_id = :agent_id LIMIT 1');
    $statement->execute(['id' => $accountId, 'agent_id' => $agentId]);
    $account = $statement->fetch();
    if (!$account) {
        throw new RuntimeException('Game account was not found.');
    }
    $externalUsername = trim($externalUsername);
    fta_validate_connected_username($agentId, (string) $account['provider_key'], $externalUsername);
    $duplicate = db()->prepare('
        SELECT COUNT(*)
        FROM user_game_accounts
        WHERE agent_id = :agent_id
          AND provider_key = :provider_key
          AND external_username = :username
          AND id <> :id
    ');
    $duplicate->execute([
        'agent_id' => $agentId,
        'provider_key' => (string) $account['provider_key'],
        'username' => $externalUsername,
        'id' => $accountId,
    ]);
    if ((int) $duplicate->fetchColumn() > 0) {
        throw new RuntimeException('This game username is already connected.');
    }
    db()->prepare('UPDATE user_game_accounts SET external_username = :username, updated_at = NOW() WHERE id = :id AND agent_id = :agent_id')->execute([
        'username' => $externalUsername,
        'id' => $accountId,
        'agent_id' => $agentId,
    ]);
}

function fta_provider_catalog(): array
{
    return [
        '555mix' => [
            'label' => '555mix',
            'icon' => 'spark',
            'supports_api' => true,
            'supports_auto_create' => true,
            'login_url' => 'https://api.555mix.com/api/auth/agent/login',
            'me_url' => 'https://api.555mix.com/api/user/me',
            'members_url' => 'https://api.555mix.com/api/members',
            'signup_url' => 'https://api.555mix.com/api/auth/member/signup',
            'transactions_members_url' => 'https://api.555mix.com/api/transactions/members',
            'origin' => 'https://ag.bet555mix.com',
            'referer' => 'https://ag.bet555mix.com/',
        ],
        'ibet' => [
            'label' => 'iBet 789',
            'icon' => 'ticket',
            'supports_api' => true,
            'supports_auto_create' => false,
            'login_url' => 'https://ag.ibet789.com/Public/Default1.aspx?r=103502489',
            'payments_url' => 'https://ag.ibet789.com/_Part/Payment.aspx?pg=payment',
            'referer' => 'https://ag.ibet789.com/Public/Default1.aspx?r=103502489',
        ],
        'sports_x_zone' => [
            'label' => 'Sports X Zone',
            'icon' => 'trophy',
            'supports_api' => true,
            'supports_auto_create' => true,
            'login_url' => 'https://main.3xscores.org/api/main/auth/login/agent',
            'users_url' => 'https://main.3xscores.org/api/main/users',
            'payments_url' => 'https://main.3xscores.org/api/main/wallets/payments',
            'graphql_url' => 'https://gateway.3xscores.org/',
            'origin' => 'https://ag.sportsxzone.com',
            'referer' => 'https://ag.sportsxzone.com/',
        ],
        'sports_899' => [
            'label' => 'Sports 899',
            'icon' => 'trophy',
            'supports_api' => true,
            'supports_auto_create' => true,
            'login_url' => 'https://lapi.sports899.org/api/login',
            'signup_url' => 'https://lapi.sports899.org/api/users/agents/store',
            'wallets_url' => 'https://lapi.sports899.org/api/users/wallets/deposit-withdraw',
            'napi_login_url' => 'https://napi.sports899.org/napi/login',
            'downlines_url' => 'https://napi.sports899.org/napi/users/downlines',
            'origin' => 'https://ag.sports899.live',
            'referer' => 'https://ag.sports899.live/',
        ],
    ];
}

function fta_provider_label(string $providerKey): string
{
    $catalog = fta_provider_catalog();
    return $catalog[$providerKey]['label'] ?? ucfirst(str_replace('_', ' ', $providerKey));
}

function fta_agent_provider_configs(int $agentId): array
{
    $statement = db()->prepare('SELECT * FROM agent_provider_configs WHERE agent_id = :agent_id ORDER BY provider_label ASC');
    $statement->execute(['agent_id' => $agentId]);
    $catalog = fta_provider_catalog();
    return array_map(static function (array $row) use ($catalog): array {
        $providerKey = (string) ($row['provider_key'] ?? '');
        $row['supports_auto_create'] = !empty($catalog[$providerKey]['supports_auto_create']);
        return $row;
    }, $statement->fetchAll());
}

function fta_agent_provider_config(int $agentId, string $providerKey): ?array
{
    $statement = db()->prepare('SELECT * FROM agent_provider_configs WHERE agent_id = :agent_id AND provider_key = :provider_key LIMIT 1');
    $statement->execute(['agent_id' => $agentId, 'provider_key' => $providerKey]);
    $config = $statement->fetch();
    return is_array($config) ? $config : null;
}

function fta_save_agent_provider_config(int $agentId, array $input): void
{
    $providerKey = trim((string) ($input['provider_key'] ?? ''));
    $catalog = fta_provider_catalog();
    if (!isset($catalog[$providerKey])) {
        throw new RuntimeException('Provider is invalid.');
    }

    $username = trim((string) ($input['agent_username'] ?? ''));
    $password = trim((string) ($input['agent_password'] ?? ''));
    $existing = fta_agent_provider_config($agentId, $providerKey);

    if ($username === '' && !$existing) {
        throw new RuntimeException('Agent username is required.');
    }
    if ($password === '' && !$existing) {
        throw new RuntimeException('Agent password is required.');
    }

    $statement = db()->prepare('
        INSERT INTO agent_provider_configs (
            agent_id, provider_key, provider_label, agent_username_enc, agent_password_enc,
            bet_limit_single, bet_limit_mix, active
        ) VALUES (
            :agent_id, :provider_key, :provider_label, :agent_username_enc, :agent_password_enc,
            :bet_limit_single, :bet_limit_mix, :active
        )
        ON DUPLICATE KEY UPDATE
            provider_label = VALUES(provider_label),
            agent_username_enc = VALUES(agent_username_enc),
            agent_password_enc = VALUES(agent_password_enc),
            bet_limit_single = VALUES(bet_limit_single),
            bet_limit_mix = VALUES(bet_limit_mix),
            active = VALUES(active)
    ');
    $statement->execute([
        'agent_id' => $agentId,
        'provider_key' => $providerKey,
        'provider_label' => $catalog[$providerKey]['label'],
        'agent_username_enc' => $username !== '' ? fta_encrypt_secret($username, 'provider_username') : (string) ($existing['agent_username_enc'] ?? ''),
        'agent_password_enc' => $password !== '' ? fta_encrypt_secret($password, 'provider_password') : (string) ($existing['agent_password_enc'] ?? ''),
        'bet_limit_single' => max(0, (int) ($input['bet_limit_single'] ?? 0)),
        'bet_limit_mix' => max(0, (int) ($input['bet_limit_mix'] ?? 0)),
        'active' => !empty($input['active']) ? 1 : 0,
    ]);
}

function fta_run_provider_health_check(int $agentId, string $providerKey): array
{
    $config = fta_agent_provider_config($agentId, $providerKey);
    $status = 'not_configured';
    $message = 'Provider credentials are not configured.';
    $lastSuccess = null;

    if ($config && !empty($config['active'])) {
        $username = fta_decrypt_secret((string) ($config['agent_username_enc'] ?? ''), 'provider_username');
        $password = fta_decrypt_secret((string) ($config['agent_password_enc'] ?? ''), 'provider_password');
        if ($username !== '' && $password !== '') {
            require_once __DIR__ . '/../api/provider_service.php';
            $apiConfig = fta_provider_config_for_agent($agentId, $providerKey);
            $result = ['error' => 'Provider API check is not implemented.'];
            if ($apiConfig) {
                $result = match ($providerKey) {
                    '555mix' => fta_555mix_auth_context($apiConfig),
                    'sports_899' => fta_sports899_auth_context($apiConfig),
                    'sports_x_zone' => fta_xzone_auth_context($apiConfig),
                    'ibet' => fta_ibet_login($apiConfig),
                    default => ['error' => 'Provider API check is not implemented.'],
                };
                if ($providerKey === 'ibet' && empty($result['error']) && !empty($result['cookie_file']) && is_file((string) $result['cookie_file'])) {
                    @unlink((string) $result['cookie_file']);
                }
            }
            if (empty($result['error'])) {
                $status = 'ok';
                $message = 'Provider API login successful.';
                $lastSuccess = date('Y-m-d H:i:s');
            } else {
                $status = 'error';
                $message = (string) $result['error'];
                $reference = trim((string) ($result['debug_reference'] ?? ''));
                if ($reference !== '') {
                    $message .= ' Ref: ' . $reference;
                }
            }
        } else {
            $status = 'error';
            $message = 'Encrypted credentials could not be decrypted.';
        }
    }

    $statement = db()->prepare('
        INSERT INTO agent_provider_health_checks (agent_id, provider_key, status, message, checked_at, last_success_at)
        VALUES (:agent_id, :provider_key, :status, :message, NOW(), :last_success_at)
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            message = VALUES(message),
            checked_at = NOW(),
            last_success_at = COALESCE(VALUES(last_success_at), last_success_at)
    ');
    $statement->execute([
        'agent_id' => $agentId,
        'provider_key' => $providerKey,
        'status' => $status,
        'message' => substr($message, 0, 250),
        'last_success_at' => $lastSuccess,
    ]);

    return ['status' => $status, 'message' => $message];
}

function fta_provider_health_checks(int $agentId): array
{
    $statement = db()->prepare('SELECT * FROM agent_provider_health_checks WHERE agent_id = :agent_id');
    $statement->execute(['agent_id' => $agentId]);
    $rows = [];
    foreach ($statement->fetchAll() as $row) {
        $rows[(string) $row['provider_key']] = $row;
    }
    return $rows;
}

function fta_user_game_accounts(array $user): array
{
    $statement = db()->prepare('SELECT * FROM user_game_accounts WHERE user_id = :user_id ORDER BY provider_label ASC');
    $statement->execute(['user_id' => (int) $user['id']]);
    return $statement->fetchAll();
}

function fta_available_provider_configs_for_user(array $user): array
{
    $agentId = (int) ($user['agent_id'] ?? 0);
    if ($agentId <= 0) {
        return [];
    }

    $statement = db()->prepare('
        SELECT provider_key, provider_label, active
        FROM agent_provider_configs
        WHERE agent_id = :agent_id AND active = 1
        ORDER BY provider_label ASC
    ');
    $statement->execute(['agent_id' => $agentId]);
    $catalog = fta_provider_catalog();
    return array_map(static function (array $row) use ($catalog): array {
        $providerKey = (string) ($row['provider_key'] ?? '');
        $row['supports_auto_create'] = !empty($catalog[$providerKey]['supports_auto_create']);
        return $row;
    }, $statement->fetchAll());
}

function fta_create_user_game_account(array $user, string $providerKey): array
{
    fta_action_rate_limit('create_game_account', (string) ($user['id'] ?? fta_client_ip()), 30);
    $agentId = (int) ($user['agent_id'] ?? 0);
    if ($agentId <= 0) {
        throw new RuntimeException('Agent promocode is not linked to this account.');
    }

    $config = fta_agent_provider_config($agentId, $providerKey);
    if (!$config || empty($config['active'])) {
        throw new RuntimeException('This game provider is not available yet.');
    }

    $existing = db()->prepare('SELECT * FROM user_game_accounts WHERE user_id = :user_id AND provider_key = :provider_key LIMIT 1');
    $existing->execute(['user_id' => (int) $user['id'], 'provider_key' => $providerKey]);
    $row = $existing->fetch();
    if ($row) {
        return $row;
    }

    require_once __DIR__ . '/../api/provider_service.php';
    return fta_provider_create_user_game_account($user, $providerKey);
}

function fta_payout_channels(): array
{
    return [
        'kbz' => ['label' => 'KBZ Pay', 'key' => 'kbz_pay', 'brand' => 'KBZ', 'logo_path' => 'assets/kbzpay.png'],
        'wave' => ['label' => 'Wave Money', 'key' => 'wave_money', 'brand' => 'Wave', 'logo_path' => 'assets/wavemoney.png'],
        'aya' => ['label' => 'Aya Pay', 'key' => 'aya_pay', 'brand' => 'AYA', 'logo_path' => 'assets/ayapay.png'],
        'yucho' => ['label' => 'Yucho Bank', 'key' => 'yucho_bank', 'brand' => 'Yucho', 'logo_path' => 'assets/yucho.png'],
        'kbank' => ['label' => 'Kasikornbank', 'key' => 'kasikorn_bank', 'brand' => 'KBank', 'logo_path' => 'assets/kbank.png'],
    ];
}

function fta_payout_channel_logo(string $method): string
{
    $path = fta_payout_channels()[$method]['logo_path'] ?? '';
    return $path !== '' ? fta_base_url($path) : '';
}

function fta_user_payout_accounts(array $user): array
{
    $accounts = [];
    foreach (fta_payout_channels() as $method => $channel) {
        $accounts[$method] = [
            'method' => $method,
            'label' => t((string) ($channel['key'] ?? $method), $channel['label']),
            'logo_url' => fta_payout_channel_logo($method),
            'account_name' => trim((string) ($user['payout_' . $method . '_name'] ?? '')),
            'account_number' => trim((string) ($user['payout_' . $method . '_number'] ?? '')),
        ];
    }
    return $accounts;
}

function fta_user_has_payout_account(array $user): bool
{
    foreach (fta_user_payout_accounts($user) as $account) {
        if ($account['account_name'] !== '' && $account['account_number'] !== '') {
            return true;
        }
    }
    return false;
}

function fta_save_user_payout_accounts(int $userId, array $input): void
{
    fta_action_rate_limit('save_payout', (string) ($userId ?: fta_client_ip()), 15);
    $fields = [];
    foreach (fta_payout_channels() as $method => $channel) {
        $name = trim((string) ($input[$method . '_name'] ?? ''));
        $number = preg_replace('/\s+/', '', trim((string) ($input[$method . '_number'] ?? '')));
        if (($name === '') xor ($number === '')) {
            throw new RuntimeException($channel['label'] . ' name and number must both be filled.');
        }
        $fields[$method] = ['name' => $name, 'number' => $number];
    }

    $sets = ['updated_at = NOW()'];
    $params = ['id' => $userId];
    foreach ($fields as $method => $values) {
        $sets[] = 'payout_' . $method . '_name = :' . $method . '_name';
        $sets[] = 'payout_' . $method . '_number = :' . $method . '_number';
        $params[$method . '_name'] = $values['name'];
        $params[$method . '_number'] = $values['number'];
    }

    $statement = db()->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id');
    $statement->execute($params);
}

function fta_clear_user_payout_accounts(int $userId): void
{
    fta_action_rate_limit('clear_payout', (string) ($userId ?: fta_client_ip()), 15);
    $sets = ['updated_at = NOW()'];
    foreach (array_keys(fta_payout_channels()) as $method) {
        $sets[] = 'payout_' . $method . '_name = ""';
        $sets[] = 'payout_' . $method . '_number = ""';
    }
    db()->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute(['id' => $userId]);
}

function fta_agent_payment_accounts(int $agentId, bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM agent_payment_accounts WHERE agent_id = :agent_id';
    if ($activeOnly) {
        $sql .= ' AND active = 1';
    }
    $sql .= ' ORDER BY method ASC, id DESC';
    $statement = db()->prepare($sql);
    $statement->execute(['agent_id' => $agentId]);
    return $statement->fetchAll();
}

function fta_save_agent_payment_account(int $agentId, array $input): void
{
    $method = trim((string) ($input['method'] ?? ''));
    if (!isset(fta_payout_channels()[$method])) {
        throw new RuntimeException('Payment method is invalid.');
    }

    $name = trim((string) ($input['account_name'] ?? ''));
    $number = preg_replace('/\s+/', '', trim((string) ($input['account_number'] ?? '')));
    if ($name === '' || $number === '') {
        throw new RuntimeException('Payment account name and number are required.');
    }

    $id = (int) ($input['payment_id'] ?? 0);
    if ($id > 0) {
        $statement = db()->prepare('
            UPDATE agent_payment_accounts
            SET method = :method, account_name = :account_name, account_number = :account_number,
                note = :note, active = :active
            WHERE id = :id AND agent_id = :agent_id
        ');
        $statement->execute([
            'id' => $id,
            'agent_id' => $agentId,
            'method' => $method,
            'account_name' => $name,
            'account_number' => $number,
            'note' => trim((string) ($input['note'] ?? '')),
            'active' => !empty($input['active']) ? 1 : 0,
        ]);
        return;
    }

    $statement = db()->prepare('
        INSERT INTO agent_payment_accounts (agent_id, method, account_name, account_number, note, active)
        VALUES (:agent_id, :method, :account_name, :account_number, :note, 1)
    ');
    $statement->execute([
        'agent_id' => $agentId,
        'method' => $method,
        'account_name' => $name,
        'account_number' => $number,
        'note' => trim((string) ($input['note'] ?? '')),
    ]);
}

function fta_delete_agent_payment_account(int $agentId, int $paymentId): void
{
    db()->prepare('DELETE FROM agent_payment_accounts WHERE id = :id AND agent_id = :agent_id')->execute([
        'id' => $paymentId,
        'agent_id' => $agentId,
    ]);
}

function fta_save_unit_proof_from_upload(string $field): string
{
    if (empty($_FILES[$field]) || (int) $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    $file = $_FILES[$field];
    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Screenshot upload failed.');
    }
    if ((int) $file['size'] > 10 * 1024 * 1024) {
        throw new RuntimeException('Screenshot is too large. Maximum size is 10 MB.');
    }

    return fta_save_uploaded_image($file, 'payment-proofs', 10 * 1024 * 1024);
}

function fta_save_unit_proof_from_base64(string $base64, string $name = '', string $mime = ''): string
{
    $base64 = trim($base64);
    if ($base64 === '') {
        return '';
    }
    if (str_contains($base64, ',')) {
        $base64 = substr($base64, (int) strpos($base64, ',') + 1);
    }
    $binary = base64_decode($base64, true);
    if (!is_string($binary) || $binary === '') {
        throw new RuntimeException('Screenshot upload failed.');
    }
    if (strlen($binary) > 10 * 1024 * 1024) {
        throw new RuntimeException('Screenshot is too large. Maximum size is 10 MB.');
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
    $mime = (string) ($info['mime'] ?: $mime);
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Only JPG, PNG, WEBP, and GIF images are supported.');
    }

    $relativeFolder = 'uploads/payment-proofs';
    $targetFolder = fta_project_path($relativeFolder);
    if (!is_dir($targetFolder) && !mkdir($targetFolder, 0775, true) && !is_dir($targetFolder)) {
        throw new RuntimeException('Upload folder could not be created.');
    }
    $filename = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extensions[$mime];
    file_put_contents($targetFolder . DIRECTORY_SEPARATOR . $filename, $binary, LOCK_EX);
    return $relativeFolder . '/' . $filename;
}

function fta_submit_unit_request(array $user, string $type, array $input, string $proofPath = ''): void
{
    $type = $type === 'withdraw' ? 'withdraw' : 'deposit';
    fta_action_rate_limit('unit_' . $type, (string) ($user['id'] ?? fta_client_ip()), 15);
    $amount = (float) ($input['amount'] ?? 0);
    $gameAccountId = (int) ($input['game_account_id'] ?? 0);
    $paymentAccountId = (int) ($input['payment_account_id'] ?? 0);
    $agentId = (int) ($user['agent_id'] ?? 0);

    if ($agentId <= 0) {
        throw new RuntimeException('Your account is not linked to an agent promocode.');
    }
    if ($amount <= 0) {
        throw new RuntimeException('Amount must be greater than zero.');
    }
    if ($gameAccountId <= 0) {
        throw new RuntimeException('Please choose a game account.');
    }
    if ($type === 'deposit' && $paymentAccountId <= 0) {
        throw new RuntimeException('Please choose a deposit payment account.');
    }
    if ($type === 'deposit' && trim($proofPath) === '') {
        throw new RuntimeException('Please upload your payment screenshot.');
    }
    if ($type === 'withdraw' && !fta_user_has_payout_account($user)) {
        throw new RuntimeException('Please connect a payout account before withdrawal.');
    }

    $game = db()->prepare('SELECT * FROM user_game_accounts WHERE id = :id AND user_id = :user_id LIMIT 1');
    $game->execute(['id' => $gameAccountId, 'user_id' => (int) $user['id']]);
    $gameAccount = $game->fetch();
    if (!$gameAccount) {
        throw new RuntimeException('Game account was not found.');
    }

    $paymentAccount = null;
    if ($type === 'deposit') {
        $payment = db()->prepare('SELECT * FROM agent_payment_accounts WHERE id = :id AND agent_id = :agent_id AND active = 1 LIMIT 1');
        $payment->execute(['id' => $paymentAccountId, 'agent_id' => $agentId]);
        $paymentAccount = $payment->fetch();
        if (!$paymentAccount) {
            throw new RuntimeException('Deposit payment account was not found.');
        }
    }

    $payload = [
        'game_account_id' => (int) ($gameAccount['id'] ?? 0),
        'game_provider_key' => (string) ($gameAccount['provider_key'] ?? ''),
        'game_provider' => (string) ($gameAccount['provider_label'] ?? ''),
        'game_username' => (string) ($gameAccount['external_username'] ?? ''),
        'game_account' => [
            'username' => $gameAccount['external_username'] ?? '',
            'provider' => $gameAccount['provider_label'] ?? '',
        ],
        'payment_account_id' => $paymentAccount ? (int) ($paymentAccount['id'] ?? 0) : null,
        'payment_account' => $paymentAccount ? [
            'method' => $paymentAccount['method'] ?? '',
            'name' => $paymentAccount['account_name'] ?? '',
            'number' => $paymentAccount['account_number'] ?? '',
        ] : null,
        'proof_image_path' => $proofPath,
        'payout_accounts' => $type === 'withdraw' ? fta_user_payout_accounts($user) : null,
    ];

    $statement = db()->prepare('
        INSERT INTO unit_requests (
            public_id, user_id, agent_id, game_account_id, payment_account_id,
            request_type, amount, status, proof_path, request_data
        ) VALUES (
            :public_id, :user_id, :agent_id, :game_account_id, :payment_account_id,
            :request_type, :amount, "pending", :proof_path, :request_data
        )
    ');
    $statement->execute([
        'public_id' => fta_public_id(),
        'user_id' => (int) $user['id'],
        'agent_id' => $agentId,
        'game_account_id' => $gameAccountId,
        'payment_account_id' => $paymentAccountId ?: null,
        'request_type' => $type,
        'amount' => $amount,
        'proof_path' => $proofPath,
        'request_data' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    fta_send_unit_request_telegram((int) db()->lastInsertId());
}

function fta_unit_requests_for_user(int $userId, int $limit = 100): array
{
    $statement = db()->prepare('
        SELECT r.*, g.external_username, g.provider_label
        FROM unit_requests r
        LEFT JOIN user_game_accounts g ON g.id = r.game_account_id
        WHERE r.user_id = :user_id
        ORDER BY r.created_at DESC
        LIMIT :limit
    ');
    $statement->bindValue('user_id', $userId, PDO::PARAM_INT);
    $statement->bindValue('limit', max(1, min(200, $limit)), PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function fta_unit_requests_for_agent(int $agentId, int $limit = 300): array
{
    $statement = db()->prepare('
        SELECT r.*, u.full_name, u.phone_e164, g.external_username, g.provider_label
        FROM unit_requests r
        INNER JOIN users u ON u.id = r.user_id
        LEFT JOIN user_game_accounts g ON g.id = r.game_account_id
        WHERE r.agent_id = :agent_id
        ORDER BY r.created_at DESC
        LIMIT :limit
    ');
    $statement->bindValue('agent_id', $agentId, PDO::PARAM_INT);
    $statement->bindValue('limit', max(1, min(500, $limit)), PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function fta_all_unit_requests(int $limit = 500): array
{
    $statement = db()->prepare('
        SELECT r.*, u.full_name, u.phone_e164, s.username AS agent_username, g.external_username, g.provider_label
        FROM unit_requests r
        INNER JOIN users u ON u.id = r.user_id
        LEFT JOIN staff_accounts s ON s.id = r.agent_id
        LEFT JOIN user_game_accounts g ON g.id = r.game_account_id
        ORDER BY r.created_at DESC
        LIMIT :limit
    ');
    $statement->bindValue('limit', max(1, min(1000, $limit)), PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function fta_unit_request_by_id(int $requestId, int $agentId = 0): ?array
{
    $statement = db()->prepare('
        SELECT r.*, u.full_name, u.phone_e164, g.external_username, g.provider_label
        FROM unit_requests r
        INNER JOIN users u ON u.id = r.user_id
        LEFT JOIN user_game_accounts g ON g.id = r.game_account_id
        WHERE r.id = :id AND (:agent_id = 0 OR r.agent_id = :agent_id_match)
        LIMIT 1
    ');
    $statement->execute([
        'id' => $requestId,
        'agent_id' => $agentId,
        'agent_id_match' => $agentId,
    ]);
    $request = $statement->fetch();
    return is_array($request) ? $request : null;
}

function fta_unit_request_sync_error(array $syncResult): string
{
    $message = trim((string) ($syncResult['error'] ?? 'Provider sync failed.'));
    $reference = trim((string) ($syncResult['debug_reference'] ?? ''));
    return $reference !== '' ? $message . ' Ref: ' . $reference : $message;
}

function fta_public_absolute_url(string $path = ''): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return ($https ? 'https' : 'http') . '://' . $host . fta_base_url($path);
}

function fta_telegram_bot_token(): string
{
    $token = trim((string) getenv('FTA_TELEGRAM_BOT_TOKEN'));
    return $token !== '' ? $token : trim(fta_setting('telegram_bot_token', ''));
}

function fta_telegram_send_message(string $chatId, string $message): bool
{
    $token = fta_telegram_bot_token();
    $chatId = trim($chatId);
    if ($token === '' || $chatId === '' || trim($message) === '') {
        return false;
    }

    $payload = json_encode([
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($payload)) {
        return false;
    }

    $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        return is_string($response) && $status >= 200 && $status < 300;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 10,
        ],
    ]);
    return @file_get_contents($url, false, $context) !== false;
}

function fta_link_telegram_chat_by_promo(string $promoCode, string $chatId): ?array
{
    $promoCode = strtoupper(trim($promoCode));
    $chatId = trim($chatId);
    if ($promoCode === '' || $chatId === '') {
        return null;
    }

    $statement = db()->prepare('
        SELECT * FROM staff_accounts
        WHERE role = "agent"
          AND active = 1
          AND UPPER(promo_code) = :promo
          AND (expires_at IS NULL OR expires_at > NOW())
        LIMIT 1
    ');
    $statement->execute(['promo' => $promoCode]);
    $agent = $statement->fetch();
    if (!$agent) {
        return null;
    }

    db()->prepare('UPDATE staff_accounts SET telegram_chat_id = :chat_id, telegram_linked_at = NOW() WHERE id = :id')->execute([
        'chat_id' => $chatId,
        'id' => (int) $agent['id'],
    ]);

    $agent['telegram_chat_id'] = $chatId;
    return $agent;
}

function fta_send_unit_request_telegram(int $requestId): void
{
    $request = fta_unit_request_by_id($requestId, 0);
    if (!$request || empty($request['agent_id'])) {
        return;
    }

    $agentStatement = db()->prepare('SELECT telegram_chat_id, promo_code, display_name, username FROM staff_accounts WHERE id = :id LIMIT 1');
    $agentStatement->execute(['id' => (int) $request['agent_id']]);
    $agent = $agentStatement->fetch();
    $chatId = trim((string) ($agent['telegram_chat_id'] ?? ''));
    if ($chatId === '') {
        return;
    }

    $details = fta_unit_request_money_details($request);
    $lines = [
        '<b>New ' . e(ucfirst((string) $request['request_type'])) . ' Request</b>',
        'Promo: <b>' . e((string) ($agent['promo_code'] ?? '-')) . '</b>',
        'Request: <code>' . e((string) $request['public_id']) . '</code>',
        'User: ' . e((string) ($request['full_name'] ?? '-')) . ' / ' . e((string) ($request['phone_e164'] ?? '-')),
        'Game: ' . e((string) ($request['provider_label'] ?? '-')) . ' / <code>' . e((string) ($request['external_username'] ?? '-')) . '</code>',
        'Amount: <b>' . e(number_format((float) ($request['amount'] ?? 0))) . ' MMK</b>',
        'Status: Pending',
    ];

    if (!empty($details['payment_account'])) {
        $payment = $details['payment_account'];
        $lines[] = 'Deposit to: ' . e((string) ($payment['method'] ?? '')) . ' / ' . e((string) ($payment['name'] ?? '')) . ' / <code>' . e((string) ($payment['number'] ?? '')) . '</code>';
    }
    foreach ($details['payout_accounts'] as $payout) {
        $lines[] = 'Payout: ' . e((string) ($payout['label'] ?? $payout['method'] ?? '')) . ' / ' . e((string) ($payout['account_name'] ?? '')) . ' / <code>' . e((string) ($payout['account_number'] ?? '')) . '</code>';
    }

    $lines[] = 'Open: ' . fta_public_absolute_url('agent/?page=requests');
    fta_telegram_send_message($chatId, implode("\n", $lines));
}

function fta_update_unit_request_status(int $requestId, int $agentId, string $status, string $note = ''): void
{
    if (!in_array($status, ['approved', 'rejected'], true)) {
        throw new RuntimeException('Request status is invalid.');
    }
    $request = fta_unit_request_by_id($requestId, $agentId);
    if (!$request) {
        throw new RuntimeException('Unit request was not found.');
    }
    if ((string) ($request['status'] ?? 'pending') !== 'pending') {
        throw new RuntimeException('This request was already reviewed and cannot be changed.');
    }

    $requestData = json_decode((string) ($request['request_data'] ?? ''), true);
    $requestData = is_array($requestData) ? $requestData : [];
    $alreadySynced = !empty($requestData['provider_sync']['transaction_id']);
    if (($request['status'] ?? '') === 'approved' && $alreadySynced && $status !== 'approved') {
        throw new RuntimeException('This approved request is already synced with the provider and cannot be changed.');
    }

    $reviewToken = bin2hex(random_bytes(16));
    $lockStatement = db()->prepare("
        UPDATE unit_requests
        SET review_token = :review_token, review_started_at = NOW()
        WHERE id = :id
          AND status = 'pending'
          AND (:agent_id = 0 OR agent_id = :agent_id_match)
          AND (review_token IS NULL OR review_started_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE))
    ");
    $lockStatement->execute([
        'id' => $requestId,
        'agent_id' => $agentId,
        'agent_id_match' => $agentId,
        'review_token' => $reviewToken,
    ]);
    if ($lockStatement->rowCount() !== 1) {
        throw new RuntimeException('This request is already being reviewed or was completed. Please refresh.');
    }

    try {
        if ($status === 'approved' && !$alreadySynced) {
            require_once __DIR__ . '/../api/provider_service.php';
            $syncResult = fta_provider_sync_unit_request($request);
            if (!empty($syncResult['error'])) {
                $requestData['provider_last_error'] = [
                    'message' => (string) $syncResult['error'],
                    'reference' => (string) ($syncResult['debug_reference'] ?? ''),
                    'logged_at' => date('Y-m-d H:i:s'),
                ];
                db()->prepare('
                    UPDATE unit_requests
                    SET request_data = :request_data
                    WHERE id = :id AND review_token = :review_token
                ')->execute([
                    'id' => $requestId,
                    'review_token' => $reviewToken,
                    'request_data' => json_encode($requestData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
                throw new RuntimeException(fta_unit_request_sync_error($syncResult));
            }
            if (is_array($syncResult['request_data'] ?? null)) {
                $requestData = $syncResult['request_data'];
                db()->prepare('
                    UPDATE unit_requests
                    SET request_data = :request_data
                    WHERE id = :id AND review_token = :review_token
                ')->execute([
                    'id' => $requestId,
                    'review_token' => $reviewToken,
                    'request_data' => json_encode($requestData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }
        }

        unset($requestData['provider_last_error']);
        $statement = db()->prepare("
            UPDATE unit_requests
            SET status = :status,
                admin_note = :admin_note,
                request_data = :request_data,
                reviewed_at = NOW(),
                reviewed_by_agent_id = :reviewed_by_agent_id,
                review_token = NULL,
                review_started_at = NULL
            WHERE id = :id
              AND status = 'pending'
              AND review_token = :review_token
              AND (:agent_id = 0 OR agent_id = :agent_id_match)
        ");
        $statement->execute([
            'id' => $requestId,
            'agent_id' => $agentId,
            'agent_id_match' => $agentId,
            'review_token' => $reviewToken,
            'status' => $status,
            'admin_note' => $note,
            'request_data' => json_encode($requestData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'reviewed_by_agent_id' => $agentId > 0 ? $agentId : 0,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('This request was already reviewed by another action. Please refresh.');
        }
    } catch (Throwable $error) {
        db()->prepare('
            UPDATE unit_requests
            SET review_token = NULL, review_started_at = NULL
            WHERE id = :id AND review_token = :review_token
        ')->execute([
            'id' => $requestId,
            'review_token' => $reviewToken,
        ]);
        throw $error;
    }

    fta_insert_user_notification(
        (int) $request['user_id'],
        (int) ($request['agent_id'] ?? 0),
        ucfirst((string) ($request['request_type'] ?? 'unit')) . ' request ' . ucfirst($status),
        'Your ' . (string) ($request['request_type'] ?? 'unit') . ' request for ' . number_format((float) ($request['amount'] ?? 0)) . ' MMK is now ' . $status . ($note !== '' ? '. Note: ' . $note : '.')
    );
}

function fta_unit_request_payload(array $request): array
{
    $moneyDetails = fta_unit_request_money_details($request);
    $requestData = fta_unit_request_json($request);

    return [
        'id' => (int) ($request['id'] ?? 0),
        'public_id' => (string) ($request['public_id'] ?? ''),
        'type' => (string) ($request['request_type'] ?? ''),
        'amount' => (float) ($request['amount'] ?? 0),
        'status' => (string) ($request['status'] ?? 'pending'),
        'game_username' => (string) ($request['external_username'] ?? ''),
        'provider_label' => (string) ($request['provider_label'] ?? ''),
        'admin_note' => (string) ($request['admin_note'] ?? ''),
        'proof_url' => !empty($request['proof_path']) ? fta_image_src((string) $request['proof_path']) : '',
        'payment_account' => $moneyDetails['payment_account'],
        'payout_accounts' => $moneyDetails['payout_accounts'],
        'provider_sync' => is_array($requestData['provider_sync'] ?? null) ? $requestData['provider_sync'] : null,
        'provider_last_error' => is_array($requestData['provider_last_error'] ?? null) ? $requestData['provider_last_error'] : null,
        'created_at' => (string) ($request['created_at'] ?? ''),
    ];
}

function fta_unit_request_json(array $request): array
{
    $data = json_decode((string) ($request['request_data'] ?? ''), true);
    return is_array($data) ? $data : [];
}

function fta_unit_request_money_details(array $request): array
{
    $data = fta_unit_request_json($request);
    $payment = is_array($data['payment_account'] ?? null) ? $data['payment_account'] : null;
    $payouts = is_array($data['payout_accounts'] ?? null) ? $data['payout_accounts'] : [];

    if (!$payment && (int) ($request['payment_account_id'] ?? 0) > 0) {
        $paymentStatement = db()->prepare('SELECT method, account_name, account_number FROM agent_payment_accounts WHERE id = :id LIMIT 1');
        $paymentStatement->execute(['id' => (int) $request['payment_account_id']]);
        $paymentRow = $paymentStatement->fetch();
        if ($paymentRow) {
            $payment = [
                'method' => $paymentRow['method'] ?? '',
                'name' => $paymentRow['account_name'] ?? '',
                'number' => $paymentRow['account_number'] ?? '',
            ];
        }
    }

    if (!$payouts && (string) ($request['request_type'] ?? '') === 'withdraw' && (int) ($request['user_id'] ?? 0) > 0) {
        $userStatement = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $userStatement->execute(['id' => (int) $request['user_id']]);
        $userRow = $userStatement->fetch();
        if ($userRow) {
            $payouts = array_values(fta_user_payout_accounts($userRow));
        }
    }

    $payouts = array_values(array_filter($payouts, static function (array $account): bool {
        return trim((string) ($account['account_name'] ?? '')) !== '' || trim((string) ($account['account_number'] ?? '')) !== '';
    }));
    return [
        'payment_account' => $payment,
        'payout_accounts' => $payouts,
    ];
}

function fta_default_ibet_rules(): array
{
    return [
        'football_rules' => implode("\n", [
            'မောင်းအဆ 500',
            '-70 အောက်ကြေးများ ကစားလို့မရ',
            'ဘီလာရုဇ် ၊ ရီဂျင်နယ် ၊ မြန်မာနေရှင်နယ် နှင့် တက္ကသိုလ် (University) လိဂ်များ ကစားလို့မရ',
            'U17 to U23 ပွဲများ / Women (W) / Reverse (R) လိဂ်များ ကစားလို့မရ',
            'မြန်မာကြေး: Live မရ',
        ]),
        'egame_rules' => implode("\n", [
            'E-Games / Saba (500 Units)',
            'မောင်းတွဲလို့မရ',
            'ပထမပိုင်းမရ / ဒုတိယပိုင်းမရ ( ပွဲချိန်ပြည့်ပဲရ )',
            'All Over / Under Series ကစားလို့မရ',
        ]),
        'updated_at' => '',
        'history' => [],
    ];
}

function fta_agent_ibet_rules(int $agentId): array
{
    $default = fta_default_ibet_rules();
    if ($agentId <= 0) {
        return $default;
    }
    $statement = db()->prepare('SELECT * FROM agent_ibet_rules WHERE agent_id = :agent_id LIMIT 1');
    $statement->execute(['agent_id' => $agentId]);
    $rules = $statement->fetch();
    $history = db()->prepare('SELECT football_rules, egame_rules, created_at FROM agent_ibet_rule_history WHERE agent_id = :agent_id ORDER BY created_at DESC, id DESC LIMIT 3');
    $history->execute(['agent_id' => $agentId]);
    if (!$rules) {
        $default['history'] = $history->fetchAll();
        return $default;
    }
    return [
        'football_rules' => (string) ($rules['football_rules'] ?? $default['football_rules']),
        'egame_rules' => (string) ($rules['egame_rules'] ?? $default['egame_rules']),
        'updated_at' => (string) ($rules['updated_at'] ?? ''),
        'history' => $history->fetchAll(),
    ];
}

function fta_save_agent_ibet_rules(int $agentId, string $footballRules, string $egameRules): void
{
    $current = fta_agent_ibet_rules($agentId);
    db()->prepare('INSERT INTO agent_ibet_rule_history (agent_id, football_rules, egame_rules) VALUES (:agent_id, :football_rules, :egame_rules)')->execute([
        'agent_id' => $agentId,
        'football_rules' => $current['football_rules'],
        'egame_rules' => $current['egame_rules'],
    ]);
    db()->prepare('
        INSERT INTO agent_ibet_rules (agent_id, football_rules, egame_rules)
        VALUES (:agent_id, :football_rules, :egame_rules)
        ON DUPLICATE KEY UPDATE football_rules = VALUES(football_rules), egame_rules = VALUES(egame_rules)
    ')->execute([
        'agent_id' => $agentId,
        'football_rules' => trim($footballRules),
        'egame_rules' => trim($egameRules),
    ]);
}

function fta_ibet_rules_for_user(?array $user): array
{
    return fta_agent_ibet_rules((int) ($user['agent_id'] ?? 0));
}

function fta_agent_category_permissions(int $agentId): array
{
    if ($agentId <= 0) {
        return [];
    }
    $statement = db()->prepare('SELECT category_id, active FROM agent_category_permissions WHERE agent_id = :agent_id');
    $statement->execute(['agent_id' => $agentId]);
    $items = [];
    foreach ($statement->fetchAll() as $row) {
        $items[(int) $row['category_id']] = !empty($row['active']);
    }
    return $items;
}

function fta_save_agent_category_permissions(int $agentId, array $input): void
{
    $categories = fta_all_categories();
    $activeIds = array_map('intval', (array) ($input['category_ids'] ?? []));
    $statement = db()->prepare('
        INSERT INTO agent_category_permissions (agent_id, category_id, active)
        VALUES (:agent_id, :category_id, :active)
        ON DUPLICATE KEY UPDATE active = VALUES(active)
    ');
    foreach ($categories as $category) {
        $statement->execute([
            'agent_id' => $agentId,
            'category_id' => (int) $category['id'],
            'active' => in_array((int) $category['id'], $activeIds, true) ? 1 : 0,
        ]);
    }
}

function fta_active_categories_for_user(?array $user = null): array
{
    $categories = fta_active_categories();
    $agentId = (int) ($user['agent_id'] ?? 0);
    if ($agentId <= 0) {
        return $categories;
    }
    $permissions = fta_agent_category_permissions($agentId);
    return array_values(array_filter($categories, static function (array $category) use ($permissions): bool {
        $id = (int) ($category['id'] ?? 0);
        return !array_key_exists($id, $permissions) || $permissions[$id];
    }));
}

function fta_contact_profile(int $agentId): array
{
    $statement = db()->prepare('SELECT * FROM agent_contact_profiles WHERE agent_id = :agent_id LIMIT 1');
    $statement->execute(['agent_id' => $agentId]);
    $profile = $statement->fetch();
    return is_array($profile) ? $profile : [
        'agent_id' => $agentId,
        'phone' => '',
        'viber' => '',
        'telegram' => '',
        'facebook' => '',
        'tiktok' => '',
    ];
}

function fta_save_contact_profile(int $agentId, array $input): void
{
    $statement = db()->prepare('
        INSERT INTO agent_contact_profiles (agent_id, phone, viber, telegram, facebook, tiktok)
        VALUES (:agent_id, :phone, :viber, :telegram, :facebook, :tiktok)
        ON DUPLICATE KEY UPDATE
            phone = VALUES(phone),
            viber = VALUES(viber),
            telegram = VALUES(telegram),
            facebook = VALUES(facebook),
            tiktok = VALUES(tiktok)
    ');
    $statement->execute([
        'agent_id' => $agentId,
        'phone' => trim((string) ($input['phone'] ?? '')),
        'viber' => trim((string) ($input['viber'] ?? '')),
        'telegram' => trim((string) ($input['telegram'] ?? '')),
        'facebook' => trim((string) ($input['facebook'] ?? '')),
        'tiktok' => trim((string) ($input['tiktok'] ?? '')),
    ]);
}

function fta_agent_contact_for_user(?array $user): array
{
    $agentId = (int) ($user['agent_id'] ?? 0);
    if ($agentId <= 0) {
        return [];
    }
    $agent = fta_staff_by_id($agentId);
    if (!$agent) {
        return [];
    }
    $profile = fta_contact_profile($agentId);
    return [
        'agent_name' => fta_staff_display_name($agent),
        'promo_code' => (string) ($agent['promo_code'] ?? ''),
        'phone' => (string) ($profile['phone'] ?? ''),
        'viber' => (string) ($profile['viber'] ?? ''),
        'telegram' => (string) ($profile['telegram'] ?? ''),
        'facebook' => (string) ($profile['facebook'] ?? ''),
        'tiktok' => (string) ($profile['tiktok'] ?? ''),
    ];
}

function fta_notifications_for_user(?array $user, int $limit = 50): array
{
    $agentId = (int) ($user['agent_id'] ?? 0);
    $userId = (int) ($user['id'] ?? 0);
    $statement = db()->prepare('
        SELECT *
        FROM notifications
        WHERE active = 1
          AND (user_id IS NULL OR user_id = :user_id)
          AND (agent_id IS NULL OR agent_id = :agent_id)
        ORDER BY id DESC
        LIMIT :limit
    ');
    $statement->bindValue('user_id', $userId, PDO::PARAM_INT);
    $statement->bindValue('agent_id', $agentId, PDO::PARAM_INT);
    $statement->bindValue('limit', max(1, min(100, $limit)), PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function fta_agent_send_notification(int $agentId, string $title, string $body): void
{
    $title = trim($title);
    $body = trim($body);
    if ($title === '' || $body === '') {
        throw new RuntimeException('Notification title and text are required.');
    }

    $statement = db()->prepare('INSERT INTO notifications (agent_id, title, body, active) VALUES (:agent_id, :title, :body, 1)');
    $statement->execute([
        'agent_id' => $agentId,
        'title' => substr($title, 0, 180),
        'body' => $body,
    ]);
    fta_save_setting('site_version', (string) time());
}

function fta_insert_user_notification(int $userId, ?int $agentId, string $title, string $body): void
{
    $title = trim($title);
    $body = trim($body);
    if ($userId <= 0 || $title === '' || $body === '') {
        return;
    }

    $statement = db()->prepare('
        INSERT INTO notifications (agent_id, user_id, title, body, active)
        VALUES (:agent_id, :user_id, :title, :body, 1)
    ');
    $statement->execute([
        'agent_id' => $agentId,
        'user_id' => $userId,
        'title' => substr($title, 0, 180),
        'body' => $body,
    ]);
    fta_save_setting('site_version', (string) time());
}

function fta_game_account_payload(array $account): array
{
    return [
        'id' => (int) ($account['id'] ?? 0),
        'provider_key' => (string) ($account['provider_key'] ?? ''),
        'provider_label' => (string) ($account['provider_label'] ?? ''),
        'external_username' => (string) ($account['external_username'] ?? ''),
        'created_at' => (string) ($account['created_at'] ?? ''),
    ];
}

function fta_payment_account_payload(array $account): array
{
    $channels = fta_payout_channels();
    $method = (string) ($account['method'] ?? '');
    return [
        'id' => (int) ($account['id'] ?? 0),
        'method' => $method,
        'label' => isset($channels[$method]) ? t((string) ($channels[$method]['key'] ?? $method), (string) $channels[$method]['label']) : ucfirst($method),
        'logo_url' => fta_payout_channel_logo($method),
        'account_name' => (string) ($account['account_name'] ?? ''),
        'account_number' => (string) ($account['account_number'] ?? ''),
        'note' => (string) ($account['note'] ?? ''),
    ];
}

function fta_payout_payload(array $user): array
{
    return array_values(fta_user_payout_accounts($user));
}
