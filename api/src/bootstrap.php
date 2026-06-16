<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config.php';

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

function fta_api_cors(): void
{
    if (headers_sent()) {
        return;
    }

    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '*');
    $allowed = [
        'https://fulltimearena.com',
        'https://www.fulltimearena.com',
        'https://ag.fulltimearena.com',
        'https://ss.fulltimearena.com',
    ];
    header('Access-Control-Allow-Origin: ' . (in_array($origin, $allowed, true) ? $origin : '*'));
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization, X-Requested-With, X-XSRF-TOKEN');
    header('Access-Control-Max-Age: 86400');
    header('X-Content-Type-Options: nosniff');
}

function fta_api_json(int $status, array $payload): void
{
    fta_api_cors();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function fta_api_db_name(): string
{
    return preg_replace('/[^A-Za-z0-9_]/', '', FTA_API_DB_NAME) ?: 'fulltimearena';
}

function fta_api_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $database = fta_api_db_name();

    try {
        $server = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', FTA_API_DB_HOST, FTA_API_DB_PORT), FTA_API_DB_USER, FTA_API_DB_PASS, $options);
        $server->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (Throwable $ignored) {
        // Shared hosting users may not have CREATE DATABASE permission; try the configured DB directly.
    }

    $pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', FTA_API_DB_HOST, FTA_API_DB_PORT, $database), FTA_API_DB_USER, FTA_API_DB_PASS, $options);
    fta_api_schema($pdo);
    return $pdo;
}

function fta_api_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
        setting_value MEDIUMTEXT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS staff_accounts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        role VARCHAR(24) NOT NULL DEFAULT 'agent',
        username VARCHAR(80) NOT NULL UNIQUE,
        display_name VARCHAR(140) NULL,
        password_hash VARCHAR(255) NOT NULL,
        promo_code VARCHAR(80) NULL UNIQUE,
        active TINYINT(1) NOT NULL DEFAULT 1,
        expires_at DATETIME NULL,
        created_by BIGINT UNSIGNED NULL,
        last_login_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_staff_role (role),
        KEY idx_staff_active (active, expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    fta_api_ensure_column($pdo, 'staff_accounts', 'telegram_chat_id', 'VARCHAR(80) NULL');
    fta_api_ensure_column($pdo, 'staff_accounts', 'telegram_linked_at', 'DATETIME NULL');

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(80) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        agent_id BIGINT UNSIGNED NULL,
        promo_code_used VARCHAR(80) NULL,
        full_name VARCHAR(140) NOT NULL,
        profile_image_path VARCHAR(255) NULL,
        phone_country VARCHAR(8) NOT NULL,
        phone_number VARCHAR(32) NOT NULL,
        phone_e164 VARCHAR(24) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        last_login_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_users_agent (agent_id),
        KEY idx_users_active (active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    foreach ([
        ['users', 'payout_kbz_name', 'VARCHAR(120) NULL'],
        ['users', 'payout_kbz_number', 'VARCHAR(80) NULL'],
        ['users', 'payout_wave_name', 'VARCHAR(120) NULL'],
        ['users', 'payout_wave_number', 'VARCHAR(80) NULL'],
        ['users', 'payout_aya_name', 'VARCHAR(120) NULL'],
        ['users', 'payout_aya_number', 'VARCHAR(80) NULL'],
        ['users', 'payout_yucho_name', 'VARCHAR(120) NULL'],
        ['users', 'payout_yucho_number', 'VARCHAR(80) NULL'],
        ['users', 'payout_kbank_name', 'VARCHAR(120) NULL'],
        ['users', 'payout_kbank_number', 'VARCHAR(80) NULL'],
    ] as [$table, $column, $definition]) {
        fta_api_ensure_column($pdo, $table, $column, $definition);
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS api_tokens (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        token_hash CHAR(64) NOT NULL UNIQUE,
        actor_type VARCHAR(16) NOT NULL,
        actor_id BIGINT UNSIGNED NOT NULL,
        role VARCHAR(24) NOT NULL,
        name VARCHAR(120) NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        expires_at DATETIME NULL,
        last_used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_api_tokens_actor (actor_type, actor_id),
        KEY idx_api_tokens_lookup (role, expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ads (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        image_path VARCHAR(255) NOT NULL,
        link_url VARCHAR(500) NULL,
        sort_order INT NOT NULL DEFAULT 0,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        link_url VARCHAR(500) NULL,
        icon_path VARCHAR(255) NULL,
        sort_order INT NOT NULL DEFAULT 0,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        agent_id BIGINT UNSIGNED NULL,
        user_id BIGINT UNSIGNED NULL,
        title VARCHAR(180) NOT NULL,
        body TEXT NOT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_notifications_agent (agent_id),
        KEY idx_notifications_user (user_id),
        KEY idx_notifications_active (active, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS submissions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        public_id VARCHAR(32) NOT NULL UNIQUE,
        user_id BIGINT UNSIGNED NULL,
        storage_id VARCHAR(128) NULL UNIQUE,
        ip_address VARCHAR(45) NOT NULL,
        user_agent TEXT NULL,
        os_version VARCHAR(160) NULL,
        browser_language VARCHAR(80) NULL,
        screen_size VARCHAR(80) NULL,
        device_json MEDIUMTEXT NULL,
        ht_result VARCHAR(50) NOT NULL,
        ft_result VARCHAR(50) NOT NULL,
        first_scorer VARCHAR(120) NOT NULL,
        wallet_type VARCHAR(30) NOT NULL,
        wallet_name VARCHAR(120) NOT NULL,
        wallet_number VARCHAR(80) NOT NULL,
        result_status VARCHAR(16) NOT NULL DEFAULT 'pending',
        is_winner TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_submissions_user (user_id),
        KEY idx_submissions_status (result_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS unit_requests (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        public_id VARCHAR(32) NOT NULL UNIQUE,
        user_id BIGINT UNSIGNED NOT NULL,
        agent_id BIGINT UNSIGNED NULL,
        game_account_id BIGINT UNSIGNED NULL,
        payment_account_id BIGINT UNSIGNED NULL,
        request_type VARCHAR(16) NOT NULL,
        amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        status VARCHAR(16) NOT NULL DEFAULT 'pending',
        proof_path VARCHAR(255) NULL,
        request_data MEDIUMTEXT NULL,
        admin_note TEXT NULL,
        reviewed_at DATETIME NULL,
        reviewed_by_agent_id BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_unit_user (user_id),
        KEY idx_unit_agent (agent_id),
        KEY idx_unit_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS agent_payment_accounts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        agent_id BIGINT UNSIGNED NOT NULL,
        method VARCHAR(40) NOT NULL,
        account_name VARCHAR(120) NOT NULL,
        account_number VARCHAR(80) NOT NULL,
        note VARCHAR(255) NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_agent_payment_agent (agent_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_game_accounts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        agent_id BIGINT UNSIGNED NULL,
        provider_key VARCHAR(60) NOT NULL,
        provider_label VARCHAR(120) NOT NULL,
        external_username VARCHAR(120) NOT NULL,
        external_member_id BIGINT UNSIGNED NULL,
        external_password_enc VARCHAR(512) NULL,
        username_suffix VARCHAR(30) NULL,
        download_url VARCHAR(255) NULL,
        api_payload MEDIUMTEXT NULL,
        api_response MEDIUMTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_provider (user_id, provider_key),
        KEY idx_game_user_agent (agent_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS live_matches (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        league_name VARCHAR(180) NULL,
        match_time VARCHAR(80) NULL,
        status_text VARCHAR(80) NULL,
        is_live TINYINT(1) NOT NULL DEFAULT 1,
        home_name VARCHAR(160) NOT NULL,
        home_logo VARCHAR(500) NULL,
        away_name VARCHAR(160) NOT NULL,
        away_logo VARCHAR(500) NULL,
        streams_json MEDIUMTEXT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS agent_category_permissions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        agent_id BIGINT UNSIGNED NOT NULL,
        category_id INT UNSIGNED NOT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_agent_category (agent_id, category_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS agent_provider_configs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        agent_id BIGINT UNSIGNED NOT NULL,
        provider_key VARCHAR(60) NOT NULL,
        provider_label VARCHAR(120) NOT NULL,
        agent_username_enc VARCHAR(512) NOT NULL DEFAULT '',
        agent_password_enc VARCHAR(512) NOT NULL DEFAULT '',
        bet_limit_single INT UNSIGNED NOT NULL DEFAULT 0,
        bet_limit_mix INT UNSIGNED NOT NULL DEFAULT 0,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_agent_provider (agent_id, provider_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS agent_provider_health_checks (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        agent_id BIGINT UNSIGNED NOT NULL,
        provider_key VARCHAR(60) NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'unknown',
        message VARCHAR(255) NULL,
        checked_at DATETIME NULL,
        last_success_at DATETIME NULL,
        UNIQUE KEY uniq_agent_provider_health (agent_id, provider_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS agent_contact_profiles (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        agent_id BIGINT UNSIGNED NOT NULL UNIQUE,
        phone VARCHAR(80) NULL,
        viber VARCHAR(120) NULL,
        telegram VARCHAR(160) NULL,
        facebook VARCHAR(255) NULL,
        tiktok VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS agent_ibet_rules (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        agent_id BIGINT UNSIGNED NOT NULL UNIQUE,
        football_rules MEDIUMTEXT NOT NULL,
        egame_rules MEDIUMTEXT NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS agent_ibet_rule_history (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        agent_id BIGINT UNSIGNED NOT NULL,
        football_rules MEDIUMTEXT NOT NULL,
        egame_rules MEDIUMTEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_ibet_history_agent (agent_id, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    fta_api_seed($pdo);
}

function fta_api_ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $statement = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column));
    if (!$statement->fetch()) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

function fta_api_seed(PDO $pdo): void
{
    $defaults = [
        'form_open' => '1',
        'site_version' => (string) time(),
        'team_a_name' => 'Team A',
        'team_b_name' => 'Team B',
        'prize_total' => '1,000,000 Kyat',
        'prize_each' => '50,000 Kyat',
        'form_start_at' => '',
        'form_end_at' => '',
        'team_a_logo' => '',
        'team_b_logo' => '',
        'telegram_popup_title' => 'Join FullTime Arena',
        'telegram_popup_text' => 'Get updates on Telegram.',
        'telegram_bot_token' => '',
        'app_announcement_enabled' => '0',
        'app_announcement_title' => 'FullTime Arena',
        'app_announcement_text' => '',
        'app_guide_videos' => '[]',
        'app_guide_youtube_url' => '',
        'app_update_download_url' => '',
        'live_refresh_seconds' => '60',
        'live_player_note' => '',
        'score_detail_enabled' => '1',
    ];
    $statement = $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (:key, :value)');
    foreach ($defaults as $key => $value) {
        $statement->execute(['key' => $key, 'value' => $value]);
    }

    $count = (int) $pdo->query("SELECT COUNT(*) FROM staff_accounts WHERE role = 'super'")->fetchColumn();
    if ($count === 0 && FTA_API_SUPER_PASSWORD !== '') {
        $insert = $pdo->prepare('INSERT INTO staff_accounts (role, username, display_name, password_hash, active) VALUES ("super", :username, "Super Admin", :hash, 1)');
        $insert->execute(['username' => FTA_API_SUPER_USERNAME, 'hash' => password_hash(FTA_API_SUPER_PASSWORD, PASSWORD_DEFAULT)]);
    }
}

function fta_api_input(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $json = json_decode($raw, true);
    return is_array($json) ? array_merge($_POST, $json) : $_POST;
}

function fta_api_segments(): array
{
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    $script = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($script !== '' && str_starts_with($path, $script)) {
        $path = trim(substr($path, strlen($script)), '/');
    }
    $segments = array_values(array_filter(explode('/', $path), 'strlen'));
    if (($segments[0] ?? '') === 'index.php') {
        array_shift($segments);
    }
    if (($segments[0] ?? '') === 'api') {
        array_shift($segments);
    }
    return $segments;
}

function fta_api_public_id(int $length = 16): string
{
    return strtoupper(bin2hex(random_bytes((int) ceil($length / 2))));
}

function fta_api_asset(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }
    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }
    return FTA_API_PUBLIC_ASSET_URL . '/' . ltrim($path, '/');
}

function fta_api_bool($value, bool $default = false): int
{
    if ($value === null || $value === '') {
        return $default ? 1 : 0;
    }
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }
    $text = strtolower(trim((string) $value));
    return in_array($text, ['1', 'true', 'yes', 'on', 'active', 'approved', 'live'], true) ? 1 : 0;
}

function fta_api_clean_url($value): string
{
    $url = trim((string) $value);
    return preg_match('/^https?:\/\//i', $url) ? $url : '';
}

function fta_api_datetime_or_null($value): ?string
{
    $text = trim((string) $value);
    if ($text === '' || strtotime($text) === false) {
        return null;
    }
    return date('Y-m-d H:i:s', strtotime($text));
}

function fta_api_upload_optional(string $field, string $folder, string $fallback = ''): string
{
    if (empty($_FILES[$field]['tmp_name'])) {
        return $fallback;
    }
    return fta_api_upload($field, $folder);
}

function fta_api_sort_order($value): int
{
    return max(-999999, min(999999, (int) $value));
}

function fta_api_public_staff_row(array $row): array
{
    unset($row['password_hash']);
    return $row;
}

function fta_api_ibet_rules_for_agent(int $agentId): array
{
    $pdo = fta_api_db();
    $statement = $pdo->prepare('SELECT * FROM agent_ibet_rules WHERE agent_id = :agent_id LIMIT 1');
    $statement->execute(['agent_id' => $agentId]);
    $rules = $statement->fetch() ?: [
        'id' => null,
        'agent_id' => $agentId,
        'football_rules' => 'Football rules will be announced by your agent.',
        'egame_rules' => 'E-Game rules will be announced by your agent.',
        'updated_at' => '',
    ];
    $history = $pdo->prepare('SELECT football_rules, egame_rules, created_at FROM agent_ibet_rule_history WHERE agent_id = :agent_id ORDER BY id DESC LIMIT 3');
    $history->execute(['agent_id' => $agentId]);
    $rules['history'] = $history->fetchAll();
    return $rules;
}

function fta_api_settings(): array
{
    $rows = fta_api_db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = (string) $row['setting_value'];
    }
    foreach (['team_a_logo', 'team_b_logo'] as $key) {
        if (!empty($settings[$key])) {
            $settings[$key] = fta_api_asset($settings[$key]);
        }
    }
    return $settings;
}

function fta_api_bearer(): string
{
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
        return trim($matches[1]);
    }
    return '';
}

function fta_api_create_token(string $actorType, int $actorId, string $role, string $name = 'web'): string
{
    $plain = bin2hex(random_bytes(40));
    $statement = fta_api_db()->prepare('INSERT INTO api_tokens (token_hash, actor_type, actor_id, role, name, ip_address, user_agent, expires_at) VALUES (:hash, :actor_type, :actor_id, :role, :name, :ip, :ua, DATE_ADD(NOW(), INTERVAL 30 DAY))');
    $statement->execute([
        'hash' => hash('sha256', $plain),
        'actor_type' => $actorType,
        'actor_id' => $actorId,
        'role' => $role,
        'name' => substr($name, 0, 120),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'ua' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
    return $plain;
}

function fta_api_actor(array $roles): array
{
    $token = fta_api_bearer();
    if ($token === '') {
        fta_api_json(401, ['status' => 'error', 'message' => 'Authentication required.']);
    }

    $statement = fta_api_db()->prepare('SELECT * FROM api_tokens WHERE token_hash = :hash AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1');
    $statement->execute(['hash' => hash('sha256', $token)]);
    $row = $statement->fetch();
    if (!$row || !in_array((string) $row['role'], $roles, true)) {
        fta_api_json(403, ['status' => 'error', 'message' => 'Access denied.']);
    }

    $table = $row['actor_type'] === 'user' ? 'users' : 'staff_accounts';
    $userStatement = fta_api_db()->prepare("SELECT * FROM {$table} WHERE id = :id LIMIT 1");
    $userStatement->execute(['id' => (int) $row['actor_id']]);
    $actor = $userStatement->fetch();
    if (!$actor || empty($actor['active'])) {
        fta_api_json(401, ['status' => 'error', 'message' => 'Session expired.']);
    }

    fta_api_db()->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE id = :id')->execute(['id' => (int) $row['id']]);
    return ['token_row' => $row, 'actor' => $actor, 'role' => (string) $row['role']];
}

function fta_api_public_user(array $user): array
{
    unset($user['password_hash']);
    if (!empty($user['profile_image_path'])) {
        $user['profile_image_url'] = fta_api_asset($user['profile_image_path']);
    }
    return $user;
}

function fta_api_public_staff(array $staff): array
{
    unset($staff['password_hash']);
    return $staff;
}

function fta_api_normalize_phone(array $input): string
{
    $e164 = trim((string) ($input['phone_e164'] ?? ''));
    if ($e164 !== '') {
        return '+' . ltrim(preg_replace('/[^\d+]/', '', $e164), '+');
    }
    $dial = ['my' => '95', 'jp' => '81', 'th' => '66', 'en' => '95'];
    $country = strtolower(trim((string) ($input['phone_country'] ?? 'my')));
    $number = preg_replace('/\D+/', '', (string) ($input['phone_number'] ?? ''));
    $number = ltrim($number, '0');
    return '+' . ($dial[$country] ?? '95') . $number;
}

function fta_api_upload(string $field, string $folder): string
{
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return '';
    }
    $info = @getimagesize($_FILES[$field]['tmp_name']);
    if (!$info) {
        throw new RuntimeException('Invalid image upload.');
    }
    $imageType = (int) ($info[2] ?? 0);
    if ($imageType === IMAGETYPE_PNG) {
        $ext = 'png';
    } elseif (defined('IMAGETYPE_WEBP') && $imageType === IMAGETYPE_WEBP) {
        $ext = 'webp';
    } else {
        $ext = 'jpg';
    }
    $dir = dirname(__DIR__) . '/uploads/' . trim($folder, '/');
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = date('YmdHis') . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Upload failed.');
    }
    return 'uploads/' . trim($folder, '/') . '/' . $name;
}

function fta_api_proxy(string $source): void
{
    $urls = FTA_API_PROXY_URLS;
    if (empty($urls[$source])) {
        fta_api_json(404, ['status' => 'error', 'message' => 'Proxy source not found.']);
    }
    $url = $urls[$source];
    if ($_GET) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($_GET);
    }
    $headers = ['Accept: application/json', 'User-Agent: FullTimeArena/1.0'];
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
    } else {
        $context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 20, 'header' => implode("\r\n", $headers)]]);
        $body = @file_get_contents($url, false, $context);
        $status = is_string($body) ? 200 : 502;
    }
    if (!is_string($body) || $body === '' || $status >= 400) {
        fta_api_json(502, ['status' => 'error', 'message' => 'Upstream service unavailable.']);
    }
    fta_api_cors();
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo $body;
    exit;
}

function fta_api_login_user(array $input): void
{
    $phone = fta_api_normalize_phone($input);
    $rawPhone = trim((string) ($input['phone_number'] ?? ''));
    $statement = fta_api_db()->prepare('SELECT * FROM users WHERE phone_e164 = :phone OR phone_number = :raw_phone LIMIT 1');
    $statement->execute(['phone' => $phone, 'raw_phone' => $rawPhone]);
    $user = $statement->fetch();
    if (!$user || empty($user['active']) || !password_verify((string) ($input['password'] ?? ''), (string) $user['password_hash'])) {
        fta_api_json(422, ['status' => 'error', 'message' => 'Invalid phone number or password.']);
    }
    fta_api_db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute(['id' => (int) $user['id']]);
    $token = fta_api_create_token('user', (int) $user['id'], 'user', (string) ($input['device_name'] ?? 'web'));
    fta_api_json(200, ['status' => 'success', 'token' => $token, 'role' => 'user', 'data' => fta_api_public_user($user)]);
}

function fta_api_signup_user(array $input): void
{
    $password = (string) ($input['password'] ?? '');
    $confirm = (string) ($input['password_confirmation'] ?? $input['confirm_password'] ?? '');
    if (strlen($password) < 8 || $password !== $confirm) {
        fta_api_json(422, ['status' => 'error', 'message' => 'Password confirmation does not match.']);
    }
    $phone = fta_api_normalize_phone($input);
    $promo = strtoupper(trim((string) ($input['promo_code'] ?? '')));
    $agentStatement = fta_api_db()->prepare('SELECT * FROM staff_accounts WHERE role = "agent" AND promo_code = :promo AND active = 1 LIMIT 1');
    $agentStatement->execute(['promo' => $promo]);
    $agent = $agentStatement->fetch();
    if (!$agent) {
        fta_api_json(422, ['status' => 'error', 'message' => 'Invalid promocode.']);
    }
    $statement = fta_api_db()->prepare('INSERT INTO users (agent_id, promo_code_used, full_name, phone_country, phone_number, phone_e164, password_hash, active, last_login_at) VALUES (:agent_id, :promo, :name, :country, :number, :phone, :hash, 1, NOW())');
    try {
        $statement->execute([
            'agent_id' => (int) $agent['id'],
            'promo' => $promo,
            'name' => trim((string) ($input['full_name'] ?? '')),
            'country' => strtolower(trim((string) ($input['phone_country'] ?? 'my'))),
            'number' => trim((string) ($input['phone_number'] ?? '')),
            'phone' => $phone,
            'hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    } catch (Throwable $ignored) {
        fta_api_json(422, ['status' => 'error', 'message' => 'This phone number is already registered.']);
    }
    $id = (int) fta_api_db()->lastInsertId();
    $user = fta_api_db()->query('SELECT * FROM users WHERE id = ' . $id)->fetch();
    $token = fta_api_create_token('user', $id, 'user', (string) ($input['device_name'] ?? 'web'));
    fta_api_json(201, ['status' => 'success', 'token' => $token, 'role' => 'user', 'data' => fta_api_public_user($user)]);
}

function fta_api_login_staff(array $input, string $role): void
{
    $username = trim((string) ($input['username'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $statement = fta_api_db()->prepare('SELECT * FROM staff_accounts WHERE role = :role AND username = :username LIMIT 1');
    $statement->execute(['role' => $role, 'username' => $username]);
    $staff = $statement->fetch();

    if (!$staff && $role === 'super') {
        $legacy = fta_api_db()->prepare('SELECT * FROM admin_users WHERE username = :username LIMIT 1');
        $legacy->execute(['username' => $username]);
        $admin = $legacy->fetch();
        if ($admin && password_verify($password, (string) $admin['password_hash'])) {
            $insert = fta_api_db()->prepare('INSERT INTO staff_accounts (role, username, display_name, password_hash, active) VALUES ("super", :username, :display_name, :hash, 1)');
            $insert->execute(['username' => $username, 'display_name' => $username, 'hash' => $admin['password_hash']]);
            $statement->execute(['role' => $role, 'username' => $username]);
            $staff = $statement->fetch();
        }
    }

    if (!$staff || empty($staff['active']) || !password_verify($password, (string) $staff['password_hash'])) {
        fta_api_json(422, ['status' => 'error', 'message' => 'Invalid username or password.']);
    }
    if (!empty($staff['expires_at']) && strtotime((string) $staff['expires_at']) < time()) {
        fta_api_json(422, ['status' => 'error', 'message' => 'This account has expired.']);
    }
    fta_api_db()->prepare('UPDATE staff_accounts SET last_login_at = NOW() WHERE id = :id')->execute(['id' => (int) $staff['id']]);
    $token = fta_api_create_token('staff', (int) $staff['id'], $role, (string) ($input['device_name'] ?? 'web'));
    fta_api_json(200, ['status' => 'success', 'token' => $token, 'role' => $role, 'data' => fta_api_public_staff($staff)]);
}

function fta_api_logout(array $session): void
{
    fta_api_db()->prepare('DELETE FROM api_tokens WHERE id = :id')->execute(['id' => (int) $session['token_row']['id']]);
    fta_api_json(200, ['status' => 'success', 'message' => 'Logged out.']);
}

function fta_api_public_bootstrap(): void
{
    $settings = fta_api_settings();
    $ads = array_map(static fn (array $ad): array => $ad + ['image_url' => fta_api_asset($ad['image_path'] ?? '')],
        fta_api_db()->query('SELECT * FROM ads WHERE active = 1 ORDER BY sort_order ASC, id DESC')->fetchAll());
    $categories = array_map(static fn (array $category): array => $category + ['icon_url' => fta_api_asset($category['icon_path'] ?? '')],
        fta_api_db()->query('SELECT * FROM categories WHERE active = 1 ORDER BY sort_order ASC, id ASC')->fetchAll());
    fta_api_json(200, [
        'status' => 'success',
        'app' => ['name' => FTA_API_APP_NAME, 'logo_url' => fta_api_asset('logo.png')],
        'settings' => $settings,
        'ads' => $ads,
        'categories' => $categories,
        'links' => ['facebook' => FTA_API_FACEBOOK_URL, 'telegram' => FTA_API_TELEGRAM_URL, 'tiktok' => FTA_API_TIKTOK_URL],
    ]);
}

function fta_api_user_route(array $segments, string $method): void
{
    $input = fta_api_input();
    if (($segments[1] ?? '') === 'auth' && ($segments[2] ?? '') === 'login' && $method === 'POST') {
        fta_api_login_user($input);
    }
    if (($segments[1] ?? '') === 'auth' && ($segments[2] ?? '') === 'signup' && $method === 'POST') {
        fta_api_signup_user($input);
    }

    $session = fta_api_actor(['user']);
    $user = $session['actor'];
    $pdo = fta_api_db();

    if (($segments[1] ?? '') === 'auth' && ($segments[2] ?? '') === 'me') {
        fta_api_json(200, ['status' => 'success', 'data' => fta_api_public_user($user)]);
    }
    if (($segments[1] ?? '') === 'auth' && ($segments[2] ?? '') === 'logout' && $method === 'POST') {
        fta_api_logout($session);
    }
    if (($segments[1] ?? '') === 'bootstrap') {
        $settings = fta_api_settings();
        $ads = array_map(static fn (array $ad): array => $ad + ['image_url' => fta_api_asset($ad['image_path'] ?? '')],
            $pdo->query('SELECT * FROM ads WHERE active = 1 ORDER BY sort_order ASC, id DESC')->fetchAll());
        $categoriesStatement = $pdo->prepare('
            SELECT c.*
            FROM categories c
            LEFT JOIN agent_category_permissions p ON p.category_id = c.id AND p.agent_id = :agent_id
            WHERE c.active = 1 AND COALESCE(p.active, 1) = 1
            ORDER BY c.sort_order ASC, c.id ASC
        ');
        $categoriesStatement->execute(['agent_id' => (int) ($user['agent_id'] ?? 0)]);
        $categories = array_map(static fn (array $category): array => $category + ['icon_url' => fta_api_asset($category['icon_path'] ?? '')], $categoriesStatement->fetchAll());
        fta_api_json(200, [
            'status' => 'success',
            'app' => ['name' => FTA_API_APP_NAME, 'logo_url' => fta_api_asset('logo.png')],
            'settings' => $settings,
            'ads' => $ads,
            'categories' => $categories,
            'links' => ['facebook' => FTA_API_FACEBOOK_URL, 'telegram' => FTA_API_TELEGRAM_URL, 'tiktok' => FTA_API_TIKTOK_URL],
        ]);
    }
    if (($segments[1] ?? '') === 'profile') {
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $profileImage = fta_api_upload_optional('profile_image', 'profiles', (string) ($user['profile_image_path'] ?? ''));
            $statement = $pdo->prepare('
                UPDATE users SET
                    full_name = :full_name,
                    profile_image_path = :profile_image_path,
                    payout_kbz_name = :payout_kbz_name,
                    payout_kbz_number = :payout_kbz_number,
                    payout_wave_name = :payout_wave_name,
                    payout_wave_number = :payout_wave_number,
                    payout_aya_name = :payout_aya_name,
                    payout_aya_number = :payout_aya_number,
                    payout_yucho_name = :payout_yucho_name,
                    payout_yucho_number = :payout_yucho_number,
                    payout_kbank_name = :payout_kbank_name,
                    payout_kbank_number = :payout_kbank_number,
                    updated_at = NOW()
                WHERE id = :id
            ');
            $statement->execute([
                'id' => (int) $user['id'],
                'full_name' => trim((string) ($input['full_name'] ?? $user['full_name'] ?? '')),
                'profile_image_path' => $profileImage,
                'payout_kbz_name' => trim((string) ($input['payout_kbz_name'] ?? $user['payout_kbz_name'] ?? '')),
                'payout_kbz_number' => trim((string) ($input['payout_kbz_number'] ?? $user['payout_kbz_number'] ?? '')),
                'payout_wave_name' => trim((string) ($input['payout_wave_name'] ?? $user['payout_wave_name'] ?? '')),
                'payout_wave_number' => trim((string) ($input['payout_wave_number'] ?? $user['payout_wave_number'] ?? '')),
                'payout_aya_name' => trim((string) ($input['payout_aya_name'] ?? $user['payout_aya_name'] ?? '')),
                'payout_aya_number' => trim((string) ($input['payout_aya_number'] ?? $user['payout_aya_number'] ?? '')),
                'payout_yucho_name' => trim((string) ($input['payout_yucho_name'] ?? $user['payout_yucho_name'] ?? '')),
                'payout_yucho_number' => trim((string) ($input['payout_yucho_number'] ?? $user['payout_yucho_number'] ?? '')),
                'payout_kbank_name' => trim((string) ($input['payout_kbank_name'] ?? $user['payout_kbank_name'] ?? '')),
                'payout_kbank_number' => trim((string) ($input['payout_kbank_number'] ?? $user['payout_kbank_number'] ?? '')),
            ]);
            $userStatement = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
            $userStatement->execute(['id' => (int) $user['id']]);
            fta_api_json(200, ['status' => 'success', 'message' => 'Profile updated.', 'data' => fta_api_public_user($userStatement->fetch())]);
        }
        fta_api_json(200, ['status' => 'success', 'data' => fta_api_public_user($user)]);
    }
    if (($segments[1] ?? '') === 'game-accounts') {
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $id = (int) ($segments[2] ?? $input['id'] ?? 0);
            $providerKey = trim((string) ($input['provider_key'] ?? 'ibet789'));
            $providerLabel = trim((string) ($input['provider_label'] ?? 'iBet 789'));
            $externalUsername = trim((string) ($input['external_username'] ?? ''));
            if ($externalUsername === '') {
                fta_api_json(422, ['status' => 'error', 'message' => 'Game username is required.']);
            }
            if ($id > 0) {
                $statement = $pdo->prepare('UPDATE user_game_accounts SET external_username = :username, updated_at = NOW() WHERE id = :id AND user_id = :user_id');
                $statement->execute(['username' => $externalUsername, 'id' => $id, 'user_id' => (int) $user['id']]);
            } else {
                $statement = $pdo->prepare('
                    INSERT INTO user_game_accounts (user_id, agent_id, provider_key, provider_label, external_username)
                    VALUES (:user_id, :agent_id, :provider_key, :provider_label, :external_username)
                    ON DUPLICATE KEY UPDATE external_username = VALUES(external_username), updated_at = NOW()
                ');
                $statement->execute([
                    'user_id' => (int) $user['id'],
                    'agent_id' => (int) ($user['agent_id'] ?? 0) ?: null,
                    'provider_key' => $providerKey,
                    'provider_label' => $providerLabel,
                    'external_username' => $externalUsername,
                ]);
            }
            fta_api_json(200, ['status' => 'success', 'message' => 'Game account saved.']);
        }
        $statement = $pdo->prepare('SELECT * FROM user_game_accounts WHERE user_id = :id ORDER BY provider_label ASC');
        $statement->execute(['id' => (int) $user['id']]);
        fta_api_json(200, ['status' => 'success', 'data' => $statement->fetchAll()]);
    }
    if (($segments[1] ?? '') === 'payment-accounts') {
        $statement = $pdo->prepare('SELECT * FROM agent_payment_accounts WHERE agent_id = :agent_id AND active = 1 ORDER BY method ASC, id DESC');
        $statement->execute(['agent_id' => (int) ($user['agent_id'] ?? 0)]);
        fta_api_json(200, ['status' => 'success', 'data' => $statement->fetchAll()]);
    }
    if (($segments[1] ?? '') === 'contact-profile') {
        $statement = $pdo->prepare('SELECT * FROM agent_contact_profiles WHERE agent_id = :agent_id LIMIT 1');
        $statement->execute(['agent_id' => (int) ($user['agent_id'] ?? 0)]);
        fta_api_json(200, ['status' => 'success', 'data' => $statement->fetch() ?: null]);
    }
    if (($segments[1] ?? '') === 'ibet-rules') {
        fta_api_json(200, ['status' => 'success', 'data' => fta_api_ibet_rules_for_agent((int) ($user['agent_id'] ?? 0))]);
    }
    if (($segments[1] ?? '') === 'notifications' && $method === 'GET') {
        $statement = $pdo->prepare('SELECT * FROM notifications WHERE active = 1 AND ((agent_id IS NULL AND user_id IS NULL) OR agent_id = :agent_id OR user_id = :user_id) ORDER BY id DESC LIMIT 100');
        $statement->execute(['agent_id' => $user['agent_id'] ?? 0, 'user_id' => (int) $user['id']]);
        fta_api_json(200, ['status' => 'success', 'data' => $statement->fetchAll()]);
    }
    if (($segments[1] ?? '') === 'predictions' && $method === 'GET') {
        $statement = $pdo->prepare('SELECT * FROM submissions WHERE user_id = :id ORDER BY id DESC LIMIT 100');
        $statement->execute(['id' => (int) $user['id']]);
        fta_api_json(200, ['status' => 'success', 'data' => $statement->fetchAll()]);
    }
    if (($segments[1] ?? '') === 'predictions' && $method === 'POST') {
        $statement = $pdo->prepare('INSERT INTO submissions (public_id, user_id, ip_address, user_agent, ht_result, ft_result, first_scorer, wallet_type, wallet_name, wallet_number) VALUES (:public_id, :user_id, :ip, :ua, :ht, :ft, :scorer, :wallet_type, :wallet_name, :wallet_number)');
        $statement->execute([
            'public_id' => fta_api_public_id(),
            'user_id' => (int) $user['id'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ht' => trim((string) ($input['ht_result'] ?? '')),
            'ft' => trim((string) ($input['ft_result'] ?? '')),
            'scorer' => trim((string) ($input['first_scorer'] ?? '')),
            'wallet_type' => trim((string) ($input['wallet_type'] ?? '')),
            'wallet_name' => trim((string) ($input['wallet_name'] ?? '')),
            'wallet_number' => trim((string) ($input['wallet_number'] ?? '')),
        ]);
        fta_api_json(201, ['status' => 'success', 'message' => 'Prediction submitted.']);
    }
    if (($segments[1] ?? '') === 'unit-requests' && $method === 'GET') {
        $statement = $pdo->prepare('
            SELECT r.*, g.external_username, g.provider_label, p.method AS payment_method, p.account_name AS payment_account_name, p.account_number AS payment_account_number
            FROM unit_requests r
            LEFT JOIN user_game_accounts g ON g.id = r.game_account_id
            LEFT JOIN agent_payment_accounts p ON p.id = r.payment_account_id
            WHERE r.user_id = :id
            ORDER BY r.id DESC
            LIMIT 100
        ');
        $statement->execute(['id' => (int) $user['id']]);
        fta_api_json(200, ['status' => 'success', 'data' => $statement->fetchAll()]);
    }
    if (($segments[1] ?? '') === 'unit-requests' && $method === 'POST') {
        $proof = fta_api_upload('proof', 'payment-proofs');
        $statement = $pdo->prepare('INSERT INTO unit_requests (public_id, user_id, agent_id, game_account_id, payment_account_id, request_type, amount, proof_path, request_data) VALUES (:public_id, :user_id, :agent_id, :game_account_id, :payment_account_id, :request_type, :amount, :proof_path, :request_data)');
        $statement->execute([
            'public_id' => fta_api_public_id(),
            'user_id' => (int) $user['id'],
            'agent_id' => $user['agent_id'] ?? null,
            'game_account_id' => (int) ($input['game_account_id'] ?? 0) ?: null,
            'payment_account_id' => (int) ($input['payment_account_id'] ?? 0) ?: null,
            'request_type' => in_array(($input['request_type'] ?? ''), ['deposit', 'withdraw'], true) ? $input['request_type'] : 'deposit',
            'amount' => (float) ($input['amount'] ?? 0),
            'proof_path' => $proof,
            'request_data' => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        fta_api_json(201, ['status' => 'success', 'message' => 'Unit request submitted.']);
    }
    fta_api_json(404, ['status' => 'error', 'message' => 'User API route not found.']);
}

function fta_api_agent_route(array $segments, string $method): void
{
    $input = fta_api_input();
    if (($segments[1] ?? '') === 'auth' && ($segments[2] ?? '') === 'login' && $method === 'POST') {
        fta_api_login_staff($input, 'agent');
    }
    $session = fta_api_actor(['agent']);
    $agent = $session['actor'];
    $pdo = fta_api_db();
    $agentId = (int) $agent['id'];

    if (($segments[1] ?? '') === 'auth' && ($segments[2] ?? '') === 'me') {
        fta_api_json(200, ['status' => 'success', 'data' => fta_api_public_staff($agent)]);
    }
    if (($segments[1] ?? '') === 'auth' && ($segments[2] ?? '') === 'logout' && $method === 'POST') {
        fta_api_logout($session);
    }
    if (($segments[1] ?? '') === 'dashboard') {
        $stats = [
            'total_users' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE agent_id = {$agentId}")->fetchColumn(),
            'pending_requests' => (int) $pdo->query("SELECT COUNT(*) FROM unit_requests WHERE agent_id = {$agentId} AND status = 'pending'")->fetchColumn(),
            'deposit_total' => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM unit_requests WHERE agent_id = {$agentId} AND request_type = 'deposit' AND status = 'approved'")->fetchColumn(),
            'withdraw_total' => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM unit_requests WHERE agent_id = {$agentId} AND request_type = 'withdraw' AND status = 'approved'")->fetchColumn(),
            'total_requests' => (int) $pdo->query("SELECT COUNT(*) FROM unit_requests WHERE agent_id = {$agentId}")->fetchColumn(),
            'approved_requests' => (int) $pdo->query("SELECT COUNT(*) FROM unit_requests WHERE agent_id = {$agentId} AND status = 'approved'")->fetchColumn(),
            'rejected_requests' => (int) $pdo->query("SELECT COUNT(*) FROM unit_requests WHERE agent_id = {$agentId} AND status = 'rejected'")->fetchColumn(),
            'pending_amount_total' => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM unit_requests WHERE agent_id = {$agentId} AND status = 'pending'")->fetchColumn(),
            'new_users_today' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE agent_id = {$agentId} AND DATE(created_at) = CURRENT_DATE")->fetchColumn(),
            'today_requests' => (int) $pdo->query("SELECT COUNT(*) FROM unit_requests WHERE agent_id = {$agentId} AND DATE(created_at) = CURRENT_DATE")->fetchColumn(),
            'today_deposit_total' => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM unit_requests WHERE agent_id = {$agentId} AND request_type = 'deposit' AND DATE(created_at) = CURRENT_DATE")->fetchColumn(),
            'today_withdraw_total' => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM unit_requests WHERE agent_id = {$agentId} AND request_type = 'withdraw' AND DATE(created_at) = CURRENT_DATE")->fetchColumn(),
        ];
        fta_api_json(200, ['status' => 'success', 'data' => $stats]);
    }
    if (($segments[1] ?? '') === 'users') {
        $userId = (int) ($segments[2] ?? 0);
        if ($userId > 0 && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $password = (string) ($input['new_password'] ?? $input['password'] ?? '');
            if ($password !== '') {
                if (strlen($password) < 8) {
                    fta_api_json(422, ['status' => 'error', 'message' => 'Password must be at least 8 characters.']);
                }
                $passwordStatement = $pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id AND agent_id = :agent_id');
                $passwordStatement->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $userId, 'agent_id' => $agentId]);
            }
            fta_api_json(200, ['status' => 'success', 'message' => 'User updated.']);
        }
        if ($userId > 0) {
            $detail = $pdo->prepare('SELECT * FROM users WHERE id = :id AND agent_id = :agent_id LIMIT 1');
            $detail->execute(['id' => $userId, 'agent_id' => $agentId]);
            $row = $detail->fetch();
            if (!$row) {
                fta_api_json(404, ['status' => 'error', 'message' => 'User not found.']);
            }
            $games = $pdo->prepare('SELECT * FROM user_game_accounts WHERE user_id = :id ORDER BY provider_label ASC');
            $games->execute(['id' => $userId]);
            $requests = $pdo->prepare('SELECT * FROM unit_requests WHERE user_id = :id ORDER BY id DESC LIMIT 50');
            $requests->execute(['id' => $userId]);
            $predictions = $pdo->prepare('SELECT * FROM submissions WHERE user_id = :id ORDER BY id DESC LIMIT 50');
            $predictions->execute(['id' => $userId]);
            fta_api_json(200, ['status' => 'success', 'data' => [
                'user' => fta_api_public_user($row),
                'game_accounts' => $games->fetchAll(),
                'unit_requests' => $requests->fetchAll(),
                'predictions' => $predictions->fetchAll(),
            ]]);
        }
        $statement = $pdo->prepare('
            SELECT u.id, u.full_name, u.phone_e164, u.promo_code_used, u.active, u.created_at, u.last_login_at,
                   COUNT(s.id) AS prediction_count,
                   SUM(CASE WHEN s.result_status = "win" THEN 1 ELSE 0 END) AS win_count
            FROM users u
            LEFT JOIN submissions s ON s.user_id = u.id
            WHERE u.agent_id = :id
            GROUP BY u.id
            ORDER BY u.id DESC
            LIMIT 300
        ');
        $statement->execute(['id' => $agentId]);
        fta_api_json(200, ['status' => 'success', 'data' => $statement->fetchAll()]);
    }
    if (($segments[1] ?? '') === 'game-accounts') {
        $id = (int) ($segments[2] ?? $input['id'] ?? 0);
        if ($id <= 0) {
            fta_api_json(422, ['status' => 'error', 'message' => 'Game account is required.']);
        }
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $username = trim((string) ($input['external_username'] ?? ''));
            if ($username === '') {
                fta_api_json(422, ['status' => 'error', 'message' => 'External username is required.']);
            }
            $statement = $pdo->prepare('UPDATE user_game_accounts SET external_username = :username, updated_at = NOW() WHERE id = :id AND agent_id = :agent_id');
            $statement->execute(['username' => $username, 'id' => $id, 'agent_id' => $agentId]);
            fta_api_json(200, ['status' => 'success', 'message' => 'Game account updated.']);
        }
    }
    if (($segments[1] ?? '') === 'unit-requests') {
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true) && (int) ($segments[2] ?? 0) > 0) {
            $id = (int) ($segments[2] ?? 0);
            $status = in_array(($input['status'] ?? ''), ['pending', 'approved', 'rejected'], true) ? $input['status'] : 'pending';
            $statement = $pdo->prepare('UPDATE unit_requests SET status = :status, admin_note = :note, reviewed_at = NOW(), reviewed_by_agent_id = :agent_id WHERE id = :id AND agent_id = :agent_id');
            $statement->execute(['status' => $status, 'note' => trim((string) ($input['admin_note'] ?? '')), 'agent_id' => $agentId, 'id' => $id]);
            fta_api_json(200, ['status' => 'success', 'message' => 'Request updated.']);
        }
        $statement = $pdo->prepare('
            SELECT r.*, u.full_name, u.phone_e164, g.external_username, g.provider_label
            FROM unit_requests r
            LEFT JOIN users u ON u.id = r.user_id
            LEFT JOIN user_game_accounts g ON g.id = r.game_account_id
            WHERE r.agent_id = :id
            ORDER BY r.id DESC
            LIMIT 300
        ');
        $statement->execute(['id' => $agentId]);
        fta_api_json(200, ['status' => 'success', 'data' => $statement->fetchAll()]);
    }
    if (($segments[1] ?? '') === 'notifications') {
        if ($method === 'DELETE') {
            $pdo->prepare('DELETE FROM notifications WHERE id = :id AND agent_id = :agent_id')->execute(['id' => (int) ($segments[2] ?? 0), 'agent_id' => $agentId]);
            fta_api_json(200, ['status' => 'success', 'message' => 'Notification deleted.']);
        }
        if ($method === 'POST') {
            $statement = $pdo->prepare('INSERT INTO notifications (agent_id, title, body, active) VALUES (:agent_id, :title, :body, 1)');
            $statement->execute(['agent_id' => $agentId, 'title' => trim((string) ($input['title'] ?? '')), 'body' => trim((string) ($input['body'] ?? ''))]);
            fta_api_json(201, ['status' => 'success', 'message' => 'Notification sent.']);
        }
        $statement = $pdo->prepare('SELECT * FROM notifications WHERE agent_id = :id ORDER BY id DESC LIMIT 100');
        $statement->execute(['id' => $agentId]);
        fta_api_json(200, ['status' => 'success', 'data' => $statement->fetchAll()]);
    }
    $resource = (string) ($segments[1] ?? '');
    if ($resource === 'payments') {
        $id = (int) ($segments[2] ?? $input['id'] ?? 0);
        if ($method === 'DELETE') {
            $pdo->prepare('DELETE FROM agent_payment_accounts WHERE id = :id AND agent_id = :agent_id')->execute(['id' => $id, 'agent_id' => $agentId]);
            fta_api_json(200, ['status' => 'success', 'message' => 'Payment account deleted.']);
        }
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $data = [
                'agent_id' => $agentId,
                'method' => trim((string) ($input['method'] ?? 'kbzpay')),
                'account_name' => trim((string) ($input['account_name'] ?? '')),
                'account_number' => trim((string) ($input['account_number'] ?? '')),
                'note' => trim((string) ($input['note'] ?? '')),
                'active' => fta_api_bool($input['active'] ?? '1', true),
            ];
            if ($data['account_name'] === '' || $data['account_number'] === '') {
                fta_api_json(422, ['status' => 'error', 'message' => 'Account name and number are required.']);
            }
            if ($id > 0) {
                $data['id'] = $id;
                $pdo->prepare('UPDATE agent_payment_accounts SET method = :method, account_name = :account_name, account_number = :account_number, note = :note, active = :active WHERE id = :id AND agent_id = :agent_id')->execute($data);
            } else {
                $pdo->prepare('INSERT INTO agent_payment_accounts (agent_id, method, account_name, account_number, note, active) VALUES (:agent_id, :method, :account_name, :account_number, :note, :active)')->execute($data);
            }
            fta_api_json(200, ['status' => 'success', 'message' => 'Payment account saved.']);
        }
        $statement = $pdo->prepare('SELECT * FROM agent_payment_accounts WHERE agent_id = :agent_id ORDER BY method ASC, id DESC');
        $statement->execute(['agent_id' => $agentId]);
        fta_api_json(200, ['status' => 'success', 'data' => $statement->fetchAll()]);
    }
    if ($resource === 'providers') {
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $providerKey = trim((string) ($input['provider_key'] ?? 'ibet789'));
            $data = [
                'agent_id' => $agentId,
                'provider_key' => $providerKey,
                'provider_label' => trim((string) ($input['provider_label'] ?? 'iBet 789')),
                'agent_username_enc' => trim((string) ($input['agent_username_enc'] ?? $input['agent_username'] ?? '')),
                'agent_password_enc' => trim((string) ($input['agent_password_enc'] ?? $input['agent_password'] ?? '')),
                'bet_limit_single' => (int) ($input['bet_limit_single'] ?? 0),
                'bet_limit_mix' => (int) ($input['bet_limit_mix'] ?? 0),
                'active' => fta_api_bool($input['active'] ?? '1', true),
            ];
            $pdo->prepare('
                INSERT INTO agent_provider_configs (agent_id, provider_key, provider_label, agent_username_enc, agent_password_enc, bet_limit_single, bet_limit_mix, active)
                VALUES (:agent_id, :provider_key, :provider_label, :agent_username_enc, :agent_password_enc, :bet_limit_single, :bet_limit_mix, :active)
                ON DUPLICATE KEY UPDATE provider_label = VALUES(provider_label), agent_username_enc = VALUES(agent_username_enc),
                    agent_password_enc = VALUES(agent_password_enc), bet_limit_single = VALUES(bet_limit_single), bet_limit_mix = VALUES(bet_limit_mix),
                    active = VALUES(active), updated_at = NOW()
            ')->execute($data);
            fta_api_json(200, ['status' => 'success', 'message' => 'Provider saved.']);
        }
        if (($segments[2] ?? '') === 'health' && $method === 'POST') {
            $providerKey = trim((string) ($input['provider_key'] ?? 'ibet789'));
            $pdo->prepare('
                INSERT INTO agent_provider_health_checks (agent_id, provider_key, status, message, checked_at, last_success_at)
                VALUES (:agent_id, :provider_key, "ok", "Saved configuration is reachable.", NOW(), NOW())
                ON DUPLICATE KEY UPDATE status = VALUES(status), message = VALUES(message), checked_at = NOW(), last_success_at = NOW()
            ')->execute(['agent_id' => $agentId, 'provider_key' => $providerKey]);
            fta_api_json(200, ['status' => 'success', 'message' => 'Health check recorded.']);
        }
        $statement = $pdo->prepare('SELECT * FROM agent_provider_configs WHERE agent_id = :agent_id ORDER BY provider_label ASC');
        $statement->execute(['agent_id' => $agentId]);
        $health = $pdo->prepare('SELECT * FROM agent_provider_health_checks WHERE agent_id = :agent_id');
        $health->execute(['agent_id' => $agentId]);
        fta_api_json(200, ['status' => 'success', 'data' => ['items' => $statement->fetchAll(), 'health' => $health->fetchAll()]]);
    }
    if ($resource === 'contact') {
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $pdo->prepare('
                INSERT INTO agent_contact_profiles (agent_id, phone, viber, telegram, facebook, tiktok)
                VALUES (:agent_id, :phone, :viber, :telegram, :facebook, :tiktok)
                ON DUPLICATE KEY UPDATE phone = VALUES(phone), viber = VALUES(viber), telegram = VALUES(telegram),
                    facebook = VALUES(facebook), tiktok = VALUES(tiktok), updated_at = NOW()
            ')->execute([
                'agent_id' => $agentId,
                'phone' => trim((string) ($input['phone'] ?? '')),
                'viber' => trim((string) ($input['viber'] ?? '')),
                'telegram' => trim((string) ($input['telegram'] ?? '')),
                'facebook' => fta_api_clean_url($input['facebook'] ?? ''),
                'tiktok' => fta_api_clean_url($input['tiktok'] ?? ''),
            ]);
            fta_api_json(200, ['status' => 'success', 'message' => 'Contact profile saved.']);
        }
        $statement = $pdo->prepare('SELECT * FROM agent_contact_profiles WHERE agent_id = :agent_id LIMIT 1');
        $statement->execute(['agent_id' => $agentId]);
        fta_api_json(200, ['status' => 'success', 'data' => $statement->fetch() ?: ['phone' => '', 'viber' => '', 'telegram' => '', 'facebook' => '', 'tiktok' => '']]);
    }
    if ($resource === 'ibet_rules') {
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $current = fta_api_ibet_rules_for_agent($agentId);
            $pdo->prepare('INSERT INTO agent_ibet_rule_history (agent_id, football_rules, egame_rules) VALUES (:agent_id, :football_rules, :egame_rules)')->execute([
                'agent_id' => $agentId,
                'football_rules' => (string) ($current['football_rules'] ?? ''),
                'egame_rules' => (string) ($current['egame_rules'] ?? ''),
            ]);
            $pdo->prepare('
                INSERT INTO agent_ibet_rules (agent_id, football_rules, egame_rules)
                VALUES (:agent_id, :football_rules, :egame_rules)
                ON DUPLICATE KEY UPDATE football_rules = VALUES(football_rules), egame_rules = VALUES(egame_rules), updated_at = NOW()
            ')->execute([
                'agent_id' => $agentId,
                'football_rules' => trim((string) ($input['football_rules'] ?? '')),
                'egame_rules' => trim((string) ($input['egame_rules'] ?? '')),
            ]);
            $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES ("site_version", :value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute(['value' => (string) time()]);
            fta_api_json(200, ['status' => 'success', 'message' => 'iBet rules saved.']);
        }
        fta_api_json(200, ['status' => 'success', 'data' => fta_api_ibet_rules_for_agent($agentId)]);
    }
    if ($resource === 'categories') {
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $permissions = $input['permissions'] ?? $input['categories'] ?? [];
            if (is_string($permissions)) {
                $decoded = json_decode($permissions, true);
                $permissions = is_array($decoded) ? $decoded : [];
            }
            $save = $pdo->prepare('
                INSERT INTO agent_category_permissions (agent_id, category_id, active)
                VALUES (:agent_id, :category_id, :active)
                ON DUPLICATE KEY UPDATE active = VALUES(active)
            ');
            foreach ((array) $permissions as $categoryId => $active) {
                $save->execute(['agent_id' => $agentId, 'category_id' => (int) $categoryId, 'active' => fta_api_bool($active)]);
            }
            $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES ("site_version", :value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute(['value' => (string) time()]);
            fta_api_json(200, ['status' => 'success', 'message' => 'Category visibility saved.']);
        }
        $items = $pdo->query('SELECT * FROM categories ORDER BY sort_order ASC, id ASC')->fetchAll();
        $permissionRows = $pdo->prepare('SELECT category_id, active FROM agent_category_permissions WHERE agent_id = :agent_id');
        $permissionRows->execute(['agent_id' => $agentId]);
        $permissions = [];
        foreach ($permissionRows->fetchAll() as $row) {
            $permissions[(int) $row['category_id']] = (int) $row['active'];
        }
        fta_api_json(200, ['status' => 'success', 'data' => ['items' => $items, 'permissions' => $permissions]]);
    }
    if ($resource === 'security' && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
        $password = (string) ($input['new_password'] ?? $input['password'] ?? '');
        if (strlen($password) < 8) {
            fta_api_json(422, ['status' => 'error', 'message' => 'Password must be at least 8 characters.']);
        }
        $pdo->prepare('UPDATE staff_accounts SET password_hash = :hash WHERE id = :id AND role = "agent"')->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $agentId]);
        fta_api_json(200, ['status' => 'success', 'message' => 'Password changed.']);
    }
    if ($resource === 'security') {
        fta_api_json(200, ['status' => 'success', 'data' => []]);
    }
    fta_api_json(404, ['status' => 'error', 'message' => 'Agent API route not found.']);
}

function fta_api_super_route(array $segments, string $method): void
{
    $input = fta_api_input();
    if (($segments[1] ?? '') === 'auth' && ($segments[2] ?? '') === 'login' && $method === 'POST') {
        fta_api_login_staff($input, 'super');
    }
    $session = fta_api_actor(['super']);
    $super = $session['actor'];
    $pdo = fta_api_db();

    if (($segments[1] ?? '') === 'auth' && ($segments[2] ?? '') === 'me') {
        fta_api_json(200, ['status' => 'success', 'data' => fta_api_public_staff($super)]);
    }
    if (($segments[1] ?? '') === 'auth' && ($segments[2] ?? '') === 'logout' && $method === 'POST') {
        fta_api_logout($session);
    }
    if (($segments[1] ?? '') === 'dashboard') {
        fta_api_json(200, ['status' => 'success', 'data' => [
            'agents' => (int) $pdo->query("SELECT COUNT(*) FROM staff_accounts WHERE role = 'agent'")->fetchColumn(),
            'users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'pending_requests' => (int) $pdo->query("SELECT COUNT(*) FROM unit_requests WHERE status = 'pending'")->fetchColumn(),
            'predictions' => (int) $pdo->query('SELECT COUNT(*) FROM submissions')->fetchColumn(),
            'ads' => (int) $pdo->query('SELECT COUNT(*) FROM ads')->fetchColumn(),
            'categories' => (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
            'approved_predictions' => (int) $pdo->query("SELECT COUNT(*) FROM submissions WHERE result_status = 'win'")->fetchColumn(),
            'pending_predictions' => (int) $pdo->query("SELECT COUNT(*) FROM submissions WHERE result_status = 'pending'")->fetchColumn(),
            'deposit_total' => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM unit_requests WHERE request_type = 'deposit' AND status = 'approved'")->fetchColumn(),
            'withdraw_total' => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM unit_requests WHERE request_type = 'withdraw' AND status = 'approved'")->fetchColumn(),
        ]]);
    }
    if (($segments[1] ?? '') === 'agents') {
        $agentId = (int) ($segments[2] ?? $input['id'] ?? $input['agent_id'] ?? 0);
        if ($method === 'DELETE') {
            $pdo->prepare('UPDATE staff_accounts SET active = 0 WHERE id = :id AND role = "agent"')->execute(['id' => $agentId]);
            fta_api_json(200, ['status' => 'success', 'message' => 'Agent disabled.']);
        }
        if ($method === 'POST') {
            if ($agentId > 0) {
                $statement = $pdo->prepare('UPDATE staff_accounts SET display_name = :display_name, promo_code = :promo, expires_at = :expires_at, active = :active WHERE id = :id AND role = "agent"');
                $statement->execute([
                    'id' => $agentId,
                    'display_name' => trim((string) ($input['display_name'] ?? '')),
                    'promo' => strtoupper(trim((string) ($input['promo_code'] ?? ''))),
                    'expires_at' => fta_api_datetime_or_null($input['expires_at'] ?? ''),
                    'active' => fta_api_bool($input['active'] ?? ''),
                ]);
                $password = (string) ($input['new_password'] ?? '');
                if ($password !== '') {
                    if (strlen($password) < 8) {
                        fta_api_json(422, ['status' => 'error', 'message' => 'Password must be at least 8 characters.']);
                    }
                    $pdo->prepare('UPDATE staff_accounts SET password_hash = :hash WHERE id = :id AND role = "agent"')->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $agentId]);
                }
                fta_api_json(200, ['status' => 'success', 'message' => 'Agent updated.']);
            } else {
                $username = trim((string) ($input['username'] ?? ''));
                $password = (string) ($input['password'] ?? '');
                if ($username === '' || strlen($password) < 8) {
                    fta_api_json(422, ['status' => 'error', 'message' => 'Username and password are required.']);
                }
                $statement = $pdo->prepare('INSERT INTO staff_accounts (role, username, display_name, promo_code, password_hash, expires_at, active, created_by) VALUES ("agent", :username, :display_name, :promo, :hash, :expires_at, 1, :created_by)');
                $statement->execute([
                    'username' => $username,
                    'display_name' => trim((string) ($input['display_name'] ?? '')),
                    'promo' => strtoupper(trim((string) ($input['promo_code'] ?? ''))),
                    'expires_at' => fta_api_datetime_or_null($input['expires_at'] ?? ''),
                    'hash' => password_hash($password, PASSWORD_DEFAULT),
                    'created_by' => (int) $super['id'],
                ]);
                fta_api_json(201, ['status' => 'success', 'message' => 'Agent created.']);
            }
        }
        $rows = $pdo->query("
            SELECT s.id, s.role, s.username, s.display_name, s.promo_code, s.active, s.expires_at, s.created_at, s.last_login_at,
                   (SELECT COUNT(*) FROM users u WHERE u.agent_id = s.id) AS user_count,
                   (SELECT COALESCE(SUM(amount),0) FROM unit_requests r WHERE r.agent_id = s.id AND r.request_type = 'deposit' AND r.status = 'approved') AS deposit_total,
                   (SELECT COALESCE(SUM(amount),0) FROM unit_requests r WHERE r.agent_id = s.id AND r.request_type = 'withdraw' AND r.status = 'approved') AS withdraw_total
            FROM staff_accounts s
            WHERE s.role = 'agent'
            ORDER BY s.id DESC
        ")->fetchAll();
        fta_api_json(200, ['status' => 'success', 'data' => $rows]);
    }
    if (($segments[1] ?? '') === 'settings') {
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $currentSettings = fta_api_settings();
            $input['team_a_logo'] = fta_api_upload_optional('team_a_logo_file', 'team', (string) ($input['team_a_logo'] ?? $currentSettings['team_a_logo'] ?? ''));
            $input['team_b_logo'] = fta_api_upload_optional('team_b_logo_file', 'team', (string) ($input['team_b_logo'] ?? $currentSettings['team_b_logo'] ?? ''));
            foreach (['form_open', 'app_announcement_enabled', 'score_detail_enabled'] as $checkboxKey) {
                $input[$checkboxKey] = (string) fta_api_bool($input[$checkboxKey] ?? '');
            }
            if (isset($input['app_update_download_url'])) {
                $input['app_update_download_url'] = fta_api_clean_url($input['app_update_download_url']);
            }
            if (isset($input['live_refresh_seconds'])) {
                $input['live_refresh_seconds'] = (string) max(15, min(300, (int) $input['live_refresh_seconds']));
            }
            $statement = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            foreach ($input as $key => $value) {
                $statement->execute(['key' => preg_replace('/[^A-Za-z0-9_]/', '', (string) $key), 'value' => is_scalar($value) ? (string) $value : json_encode($value)]);
            }
        }
        fta_api_json(200, ['status' => 'success', 'data' => fta_api_settings()]);
    }

    $resource = $segments[1] ?? '';
    if ($resource === 'security' && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
        $password = (string) ($input['new_password'] ?? $input['password'] ?? '');
        if (strlen($password) < 8) {
            fta_api_json(422, ['status' => 'error', 'message' => 'Password must be at least 8 characters.']);
        }
        $pdo->prepare('UPDATE staff_accounts SET password_hash = :hash WHERE id = :id AND role = "super"')->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => (int) $super['id']]);
        fta_api_json(200, ['status' => 'success', 'message' => 'Password changed.']);
    }
    if ($resource === 'security') {
        fta_api_json(200, ['status' => 'success', 'data' => []]);
    }
    $map = [
        'ads' => 'ads',
        'categories' => 'categories',
        'live_matches' => 'live_matches',
        'notifications' => 'notifications',
        'submissions' => 'submissions',
    ];
    if (isset($map[$resource])) {
        $table = $map[$resource];
        if ($method === 'DELETE') {
            $pdo->prepare("DELETE FROM {$table} WHERE id = :id")->execute(['id' => (int) ($segments[2] ?? 0)]);
            fta_api_json(200, ['status' => 'success', 'message' => 'Deleted.']);
        }
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            if ($resource === 'ads') {
                $input['image_path'] = fta_api_upload_optional('ad_image', 'ads', (string) ($input['image_path'] ?? ''));
                $input['link_url'] = fta_api_clean_url($input['link_url'] ?? '');
                $input['sort_order'] = fta_api_sort_order($input['sort_order'] ?? 0);
                $input['active'] = fta_api_bool($input['active'] ?? ($segments[2] ?? 0 ? '' : '1'), !isset($segments[2]));
            }
            if ($resource === 'categories') {
                $input['icon_path'] = fta_api_upload_optional('icon', 'category-icons', (string) ($input['icon_path'] ?? ''));
                $input['link_url'] = fta_api_clean_url($input['link_url'] ?? '');
                $input['sort_order'] = fta_api_sort_order($input['sort_order'] ?? 0);
                $input['active'] = fta_api_bool($input['active'] ?? ($segments[2] ?? 0 ? '' : '1'), !isset($segments[2]));
            }
            if ($resource === 'live_matches') {
                $streams = $input['streams_json'] ?? $input['streams'] ?? '';
                if (is_string($streams) && $streams !== '' && $streams[0] !== '[') {
                    $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $streams))));
                    $streams = json_encode(array_map(static function (string $line): array {
                        return ['label' => 'Live Stream', 'url' => $line];
                    }, $lines), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                $input['streams_json'] = is_string($streams) ? $streams : json_encode($streams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $input['is_live'] = fta_api_bool($input['is_live'] ?? $input['match_type'] ?? '1', true);
                $input['active'] = fta_api_bool($input['active'] ?? ($segments[2] ?? 0 ? '' : '1'), !isset($segments[2]));
                $input['sort_order'] = fta_api_sort_order($input['sort_order'] ?? 0);
            }
            if ($resource === 'notifications') {
                $input['active'] = fta_api_bool($input['active'] ?? ($segments[2] ?? 0 ? '' : '1'), !isset($segments[2]));
            }
            if ($resource === 'submissions') {
                $status = (string) ($input['result_status'] ?? 'pending');
                $input['result_status'] = in_array($status, ['pending', 'win', 'lose'], true) ? $status : 'pending';
                $input['is_winner'] = $input['result_status'] === 'win' ? 1 : 0;
            }
            fta_api_upsert_resource($table, (int) ($segments[2] ?? 0), $input);
            fta_api_json(200, ['status' => 'success', 'message' => 'Saved.']);
        }
        fta_api_json(200, ['status' => 'success', 'data' => $pdo->query("SELECT * FROM {$table} ORDER BY id DESC LIMIT 300")->fetchAll()]);
    }
    fta_api_json(404, ['status' => 'error', 'message' => 'Super API route not found.']);
}

function fta_api_upsert_resource(string $table, int $id, array $input): void
{
    $allowed = [
        'ads' => ['image_path', 'link_url', 'sort_order', 'active'],
        'categories' => ['name', 'link_url', 'icon_path', 'sort_order', 'active'],
        'live_matches' => ['league_name', 'match_time', 'status_text', 'is_live', 'home_name', 'home_logo', 'away_name', 'away_logo', 'streams_json', 'sort_order', 'active'],
        'notifications' => ['agent_id', 'user_id', 'title', 'body', 'active'],
        'submissions' => ['result_status', 'is_winner'],
    ][$table] ?? [];
    $data = array_intersect_key($input, array_flip($allowed));
    if (!$data) {
        return;
    }
    if ($id > 0) {
        $sets = implode(', ', array_map(static fn (string $key): string => "`{$key}` = :{$key}", array_keys($data)));
        $data['id'] = $id;
        fta_api_db()->prepare("UPDATE {$table} SET {$sets} WHERE id = :id")->execute($data);
        return;
    }
    $columns = implode(', ', array_map(static fn (string $key): string => "`{$key}`", array_keys($data)));
    $values = implode(', ', array_map(static fn (string $key): string => ":{$key}", array_keys($data)));
    fta_api_db()->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$values})")->execute($data);
}

function fta_api_handle(): void
{
    fta_api_cors();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    try {
        $segments = fta_api_segments();
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!$segments || ($segments[0] ?? '') === 'up') {
            fta_api_json(200, ['status' => 'ok', 'app' => FTA_API_APP_NAME, 'mode' => 'plain-php']);
        }
        if (($segments[0] ?? '') === 'public' && ($segments[1] ?? '') === 'bootstrap') {
            fta_api_public_bootstrap();
        }
        if (($segments[0] ?? '') === 'proxy') {
            fta_api_proxy((string) ($segments[1] ?? ''));
        }
        if (($segments[0] ?? '') === 'user') {
            fta_api_user_route($segments, $method);
        }
        if (($segments[0] ?? '') === 'agent') {
            fta_api_agent_route($segments, $method);
        }
        if (($segments[0] ?? '') === 'super') {
            fta_api_super_route($segments, $method);
        }
        fta_api_json(404, ['status' => 'error', 'message' => 'API route not found.', 'path' => $segments]);
    } catch (Throwable $error) {
        fta_api_json(500, ['status' => 'error', 'message' => 'API failed.', 'detail' => $error->getMessage()]);
    }
}
