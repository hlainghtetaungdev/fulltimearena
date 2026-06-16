<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$lang = fta_current_lang();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        fta_check_csrf();
        fta_logout_user();
        fta_flash('success', t('logout_success', 'Logged out successfully.'));
    } catch (Throwable $error) {
        fta_flash('error', $error->getMessage());
    }
}

fta_redirect(fta_lang_url($lang));
