<?php
declare(strict_types=1);

define('FTA_APP_NAME', 'FullTime Arena');
define('FTA_DEFAULT_LANG', 'en');
define('FTA_LANGS', ['en', 'my', 'jp', 'th']);

define('FTA_FACEBOOK_URL', 'https://www.facebook.com/fulltimearena');
define('FTA_TELEGRAM_URL', 'https://t.me/fulltimearena');
define('FTA_TIKTOK_URL', 'https://www.tiktok.com/@fulltimearena');

define('FTA_DB_HOST', getenv('FTA_DB_HOST') ?: 'localhost');
define('FTA_DB_PORT', getenv('FTA_DB_PORT') ?: '3306');
define('FTA_DB_NAME', getenv('FTA_DB_NAME') ?: 'zkfzpszw_fta_db');
define('FTA_DB_USER', getenv('FTA_DB_USER') ?: 'zkfzpszw_fta');
define('FTA_DB_PASS', getenv('FTA_DB_PASS') ?: 'Hsu2072018@');

define('FTA_DEFAULT_ADMIN_USER', getenv('FTA_ADMIN_USER') ?: 'hha');
define('FTA_DEFAULT_ADMIN_PASS', getenv('FTA_ADMIN_PASS') ?: 'Hsu2072018@');

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
