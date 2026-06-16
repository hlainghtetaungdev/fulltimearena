<?php
declare(strict_types=1);

define('FTA_API_APP_NAME', 'FullTime Arena');
define('FTA_API_LANGS', ['en', 'my', 'jp', 'th']);

define('FTA_API_DB_HOST', getenv('FTA_DB_HOST') ?: 'localhost');
define('FTA_API_DB_PORT', getenv('FTA_DB_PORT') ?: '3306');
define('FTA_API_DB_NAME', getenv('FTA_DB_NAME') ?: 'zkfzpszw_fta_db');
define('FTA_API_DB_USER', getenv('FTA_DB_USER') ?: 'zkfzpszw_fta');
define('FTA_API_DB_PASS', getenv('FTA_DB_PASS') ?: 'Hsu2072018@');

define('FTA_API_PUBLIC_ASSET_URL', rtrim(getenv('FTA_PUBLIC_ASSET_URL') ?: 'https://fulltimearena.com', '/'));
define('FTA_API_SUPER_USERNAME', getenv('FTA_SUPER_USERNAME') ?: 'hha');
define('FTA_API_SUPER_PASSWORD', getenv('FTA_SUPER_PASSWORD') ?: 'Hsu2072018@');

define('FTA_API_FACEBOOK_URL', 'https://www.facebook.com/fulltimearena');
define('FTA_API_TELEGRAM_URL', 'https://t.me/fulltimearena');
define('FTA_API_TIKTOK_URL', 'https://www.tiktok.com/@fulltimearena');

define('FTA_API_PROXY_URLS', [
    'live' => 'https://api.hlainghtetaung.com/football/live/',
    'result' => 'https://api.hlainghtetaung.com/football/result/',
    'news' => 'https://api.hlainghtetaung.com/football/news/',
    'market' => 'https://api.hlainghtetaung.com/rate/api.php',
    'odds' => 'https://api.hlainghtetaung.com/football/mmodds/',
]);
