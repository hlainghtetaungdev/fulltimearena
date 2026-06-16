<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function fta_project_path(string $path = ''): string
{
    $root = dirname(__DIR__);
    return $path === '' ? $root : $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function fta_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function fta_is_local_request(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?: '';
    if (in_array($host, ['localhost', '127.0.0.1', '::1', '10.0.2.2'], true)) {
        return true;
    }

    return (bool) preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/', $host);
}

function fta_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $sessionPath = fta_project_path('storage/sessions');
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0775, true);
    }

    if (is_dir($sessionPath) && is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }

    session_name('FTA_SESSION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => fta_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function fta_db_name(): string
{
    return preg_replace('/[^A-Za-z0-9_]/', '', FTA_DB_NAME) ?: 'fulltimearena';
}

function db(): PDO
{
    static $pdo = null;
    static $initialized = false;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $serverDsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', FTA_DB_HOST, FTA_DB_PORT);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $server = new PDO($serverDsn, FTA_DB_USER, FTA_DB_PASS, $options);
    $database = fta_db_name();
    $server->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', FTA_DB_HOST, FTA_DB_PORT, $database);
    $pdo = new PDO($dsn, FTA_DB_USER, FTA_DB_PASS, $options);

    if (!$initialized) {
        fta_initialize_database($pdo);
        $initialized = true;
    }

    return $pdo;
}

function fta_initialize_database(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
            setting_value MEDIUMTEXT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(80) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS staff_accounts (
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
            KEY idx_staff_promo (promo_code),
            KEY idx_staff_active (active, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    fta_ensure_column($pdo, 'staff_accounts', 'telegram_chat_id', 'VARCHAR(80) NULL AFTER promo_code');
    fta_ensure_column($pdo, 'staff_accounts', 'telegram_linked_at', 'DATETIME NULL AFTER telegram_chat_id');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(140) NOT NULL,
            phone_country VARCHAR(8) NOT NULL,
            phone_number VARCHAR(32) NOT NULL,
            phone_e164 VARCHAR(24) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            last_login_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_users_phone_country (phone_country),
            KEY idx_users_active (active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    fta_ensure_column($pdo, 'users', 'agent_id', 'BIGINT UNSIGNED NULL AFTER id');
    fta_ensure_column($pdo, 'users', 'promo_code_used', 'VARCHAR(80) NULL AFTER agent_id');
    fta_ensure_column($pdo, 'users', 'payout_kbz_name', 'VARCHAR(120) NULL AFTER password_hash');
    fta_ensure_column($pdo, 'users', 'payout_kbz_number', 'VARCHAR(80) NULL AFTER payout_kbz_name');
    fta_ensure_column($pdo, 'users', 'payout_wave_name', 'VARCHAR(120) NULL AFTER payout_kbz_number');
    fta_ensure_column($pdo, 'users', 'payout_wave_number', 'VARCHAR(80) NULL AFTER payout_wave_name');
    fta_ensure_column($pdo, 'users', 'payout_aya_name', 'VARCHAR(120) NULL AFTER payout_wave_number');
    fta_ensure_column($pdo, 'users', 'payout_aya_number', 'VARCHAR(80) NULL AFTER payout_aya_name');
    fta_ensure_column($pdo, 'users', 'payout_yucho_name', 'VARCHAR(120) NULL AFTER payout_aya_number');
    fta_ensure_column($pdo, 'users', 'payout_yucho_number', 'VARCHAR(80) NULL AFTER payout_yucho_name');
    fta_ensure_column($pdo, 'users', 'payout_kbank_name', 'VARCHAR(120) NULL AFTER payout_yucho_number');
    fta_ensure_column($pdo, 'users', 'payout_kbank_number', 'VARCHAR(80) NULL AFTER payout_kbank_name');
    fta_ensure_column($pdo, 'users', 'profile_image_path', 'VARCHAR(255) NULL AFTER full_name');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS auth_rate_limits (
            scope VARCHAR(40) NOT NULL,
            identifier_hash CHAR(64) NOT NULL,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            locked_until DATETIME NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (scope, identifier_hash),
            KEY idx_auth_rate_locked (locked_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_sessions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            selector CHAR(32) NOT NULL UNIQUE,
            token_hash CHAR(64) NOT NULL,
            session_type VARCHAR(24) NOT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            location_lat DECIMAL(10,7) NULL,
            location_lng DECIMAL(10,7) NULL,
            location_accuracy DECIMAL(10,2) NULL,
            location_label VARCHAR(255) NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_used_at DATETIME NULL,
            revoked_at DATETIME NULL,
            KEY idx_user_sessions_user (user_id),
            KEY idx_user_sessions_lookup (selector, revoked_at, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    fta_ensure_column($pdo, 'user_sessions', 'location_lat', 'DECIMAL(10,7) NULL AFTER user_agent');
    fta_ensure_column($pdo, 'user_sessions', 'location_lng', 'DECIMAL(10,7) NULL AFTER location_lat');
    fta_ensure_column($pdo, 'user_sessions', 'location_accuracy', 'DECIMAL(10,2) NULL AFTER location_lng');
    fta_ensure_column($pdo, 'user_sessions', 'location_label', 'VARCHAR(255) NULL AFTER location_accuracy');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ads (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            image_path VARCHAR(255) NOT NULL,
            link_url VARCHAR(500) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            link_url VARCHAR(500) NULL,
            icon_path VARCHAR(255) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    fta_ensure_column($pdo, 'categories', 'icon_path', 'VARCHAR(255) NULL AFTER link_url');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS agent_category_permissions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            agent_id BIGINT UNSIGNED NOT NULL,
            category_id INT UNSIGNED NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_agent_category (agent_id, category_id),
            KEY idx_agent_category_agent (agent_id),
            KEY idx_agent_category_active (active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS live_matches (
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
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_live_active (active, is_live, sort_order, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    fta_ensure_column($pdo, 'notifications', 'agent_id', 'BIGINT UNSIGNED NULL AFTER id');
    fta_ensure_column($pdo, 'notifications', 'user_id', 'BIGINT UNSIGNED NULL AFTER agent_id');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS submissions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            public_id VARCHAR(32) NOT NULL UNIQUE,
            user_id BIGINT UNSIGNED NULL,
            storage_id VARCHAR(128) NULL,
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
            UNIQUE KEY uniq_storage_id (storage_id),
            KEY idx_submissions_user (user_id),
            KEY idx_submissions_result (result_status),
            KEY idx_submissions_winner (is_winner),
            KEY idx_ip_created (ip_address, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    fta_ensure_column($pdo, 'submissions', 'user_id', 'BIGINT UNSIGNED NULL AFTER public_id');
    fta_ensure_column($pdo, 'submissions', 'result_status', 'VARCHAR(16) NOT NULL DEFAULT \'pending\' AFTER wallet_number');
    fta_ensure_column($pdo, 'submissions', 'is_winner', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER wallet_number');
    $pdo->exec("UPDATE submissions SET result_status = 'win' WHERE is_winner = 1 AND result_status = 'pending'");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS agent_payment_accounts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            agent_id BIGINT UNSIGNED NOT NULL,
            method VARCHAR(40) NOT NULL,
            account_name VARCHAR(120) NOT NULL,
            account_number VARCHAR(80) NOT NULL,
            note VARCHAR(255) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_agent_payment_agent (agent_id),
            KEY idx_agent_payment_active (active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_game_accounts (
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
            KEY idx_game_user_agent (agent_id),
            KEY idx_game_provider (provider_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    fta_ensure_column($pdo, 'user_game_accounts', 'external_member_id', 'BIGINT UNSIGNED NULL AFTER external_username');
    fta_ensure_column($pdo, 'user_game_accounts', 'username_suffix', 'VARCHAR(30) NULL AFTER external_password_enc');
    fta_ensure_column($pdo, 'user_game_accounts', 'download_url', 'VARCHAR(255) NULL AFTER username_suffix');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS agent_provider_configs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            agent_id BIGINT UNSIGNED NOT NULL,
            provider_key VARCHAR(60) NOT NULL,
            provider_label VARCHAR(120) NOT NULL,
            agent_username_enc VARCHAR(512) NOT NULL,
            agent_password_enc VARCHAR(512) NOT NULL,
            bet_limit_single INT UNSIGNED NOT NULL DEFAULT 0,
            bet_limit_mix INT UNSIGNED NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_agent_provider (agent_id, provider_key),
            KEY idx_provider_configs_active (active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS agent_provider_health_checks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            agent_id BIGINT UNSIGNED NOT NULL,
            provider_key VARCHAR(60) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'not_configured',
            message VARCHAR(255) NULL,
            checked_at DATETIME NULL,
            last_success_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_agent_provider_health (agent_id, provider_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS unit_requests (
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
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_unit_user (user_id),
            KEY idx_unit_agent (agent_id),
            KEY idx_unit_status (status),
            KEY idx_unit_type (request_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    fta_ensure_column($pdo, 'unit_requests', 'review_token', 'VARCHAR(64) NULL AFTER admin_note');
    fta_ensure_column($pdo, 'unit_requests', 'review_started_at', 'DATETIME NULL AFTER review_token');
    fta_ensure_column($pdo, 'unit_requests', 'reviewed_at', 'DATETIME NULL AFTER review_started_at');
    fta_ensure_column($pdo, 'unit_requests', 'reviewed_by_agent_id', 'BIGINT UNSIGNED NULL AFTER reviewed_at');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS agent_contact_profiles (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            agent_id BIGINT UNSIGNED NOT NULL UNIQUE,
            phone VARCHAR(80) NULL,
            viber VARCHAR(120) NULL,
            telegram VARCHAR(160) NULL,
            facebook VARCHAR(255) NULL,
            tiktok VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS agent_ibet_rules (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            agent_id BIGINT UNSIGNED NOT NULL UNIQUE,
            football_rules MEDIUMTEXT NOT NULL,
            egame_rules MEDIUMTEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS agent_ibet_rule_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            agent_id BIGINT UNSIGNED NOT NULL,
            football_rules MEDIUMTEXT NOT NULL,
            egame_rules MEDIUMTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ibet_history_agent (agent_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    fta_seed_settings($pdo);
    fta_seed_admin($pdo);
    fta_seed_super_staff($pdo);
    fta_seed_categories($pdo);
    fta_sync_default_category_links($pdo);
    fta_seed_ads($pdo);
}

function fta_ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $table = preg_replace('/[^A-Za-z0-9_]/', '', $table);
    $column = preg_replace('/[^A-Za-z0-9_]/', '', $column);
    $statement = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column));
    if (!$statement->fetch()) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

function fta_seed_settings(PDO $pdo): void
{
    $defaults = [
        'form_open' => '1',
        'form_start_at' => '',
        'form_end_at' => '',
        'team_a_name' => 'Team A',
        'team_a_logo' => '',
        'team_b_name' => 'Team B',
        'team_b_logo' => '',
        'prize_total' => '1,000,000 Kyat',
        'prize_each' => '50,000 Kyat',
        'site_version' => (string) time(),
        'telegram_popup_title' => 'Join FullTime Arena',
        'telegram_popup_text' => 'Get updates, odds, news, and prediction reminders on Telegram.',
        'telegram_bot_token' => '',
        'app_announcement_enabled' => '0',
        'app_announcement_title' => 'FullTime Arena',
        'app_announcement_text' => '',
        'live_refresh_seconds' => '60',
        'live_player_note' => 'Choose the best available stream quality for your connection.',
        'score_detail_enabled' => '1',
        'app_guide_youtube_url' => '',
        'app_guide_videos' => '[]',
        'app_update_download_url' => '',
    ];

    $statement = $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (:key, :value)');
    foreach ($defaults as $key => $value) {
        $statement->execute(['key' => $key, 'value' => $value]);
    }
}

function fta_seed_admin(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $statement = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:username, :password_hash)');
    $statement->execute([
        'username' => FTA_DEFAULT_ADMIN_USER,
        'password_hash' => password_hash(FTA_DEFAULT_ADMIN_PASS, PASSWORD_DEFAULT),
    ]);
}

function fta_seed_super_staff(PDO $pdo): void
{
    $count = (int) $pdo->query("SELECT COUNT(*) FROM staff_accounts WHERE role = 'super'")->fetchColumn();
    if ($count > 0) {
        return;
    }

    $statement = $pdo->prepare('
        INSERT INTO staff_accounts (role, username, display_name, password_hash, promo_code, active)
        VALUES ("super", :username, :display_name, :password_hash, NULL, 1)
    ');
    $statement->execute([
        'username' => FTA_DEFAULT_ADMIN_USER,
        'display_name' => 'Super Admin',
        'password_hash' => password_hash(FTA_DEFAULT_ADMIN_PASS, PASSWORD_DEFAULT),
    ]);
}

function fta_seed_categories(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $categories = [
        ['555Mix', '', 10],
        ['iBet 789', '', 20],
        ['Sports X Zone', '', 30],
        ['Sports 899', '', 40],
        ['News', 'news.php', 50],
        ['Live Score', 'live-score.php', 60],
        ['Live', 'live.php', 70],
        ['Market Data', 'market-data.php', 75],
        ['ပေါက်ကြေးများ', 'odds.php', 78],
        ['Prediction', 'prediction.php', 80],
    ];

    $statement = $pdo->prepare('INSERT INTO categories (name, link_url, icon_path, sort_order, active) VALUES (:name, :link_url, "", :sort_order, 1)');
    foreach ($categories as [$name, $link, $order]) {
        $statement->execute(['name' => $name, 'link_url' => $link, 'sort_order' => $order]);
    }
}

function fta_sync_default_category_links(PDO $pdo): void
{
    $links = [
        'news' => 'news.php',
        'live score' => 'live-score.php',
        'live' => 'live.php',
        'market data' => 'market-data.php',
        'ပေါက်ကြေးများ' => 'odds.php',
        'prediction' => 'prediction.php',
    ];

    $statement = $pdo->prepare('UPDATE categories SET link_url = :link_url WHERE LOWER(name) = :name AND (link_url IS NULL OR link_url = "" OR link_url = "#")');
    foreach ($links as $name => $link) {
        $statement->execute(['name' => $name, 'link_url' => $link]);
    }

    $market = $pdo->prepare('
        INSERT INTO categories (name, link_url, icon_path, sort_order, active)
        SELECT "Market Data", "market-data.php", "", 75, 1
        WHERE NOT EXISTS (SELECT 1 FROM categories WHERE LOWER(name) = "market data")
    ');
    $market->execute();

    $odds = $pdo->prepare('
        INSERT INTO categories (name, link_url, icon_path, sort_order, active)
        SELECT "ပေါက်ကြေးများ", "odds.php", "", 78, 1
        WHERE NOT EXISTS (SELECT 1 FROM categories WHERE LOWER(name) = "ပေါက်ကြေးများ" OR link_url = "odds.php")
    ');
    $odds->execute();

    $ibet = $pdo->prepare('UPDATE categories SET link_url = "ibet-rules.php" WHERE LOWER(name) LIKE "%ibet%"');
    $ibet->execute();
}

function fta_seed_ads(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM ads')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $samples = [
        ['uploads/ads/sample-ad-1.png', 10],
        ['uploads/ads/sample-ad-2.png', 20],
    ];

    $statement = $pdo->prepare('INSERT INTO ads (image_path, link_url, sort_order, active) VALUES (:image_path, "", :sort_order, 1)');
    foreach ($samples as [$imagePath, $order]) {
        if (is_file(fta_project_path($imagePath))) {
            $statement->execute(['image_path' => $imagePath, 'sort_order' => $order]);
        }
    }
}
