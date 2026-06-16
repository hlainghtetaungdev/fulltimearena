<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';

fta_send_security_headers(true);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'name' => FTA_APP_NAME,
    'version' => fta_setting('site_version', '1'),
    'download_url' => fta_clean_link(fta_setting('app_update_download_url', '')),
    'guide_videos' => fta_app_guide_videos_from_settings(fta_settings()),
], JSON_UNESCAPED_SLASHES);
