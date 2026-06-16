<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function fta_icon(string $name, string $class = 'app-icon'): string
{
    $svgClass = e($class);
    if ($name === 'score' || $name === 'football') {
        return '<svg class="' . $svgClass . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<circle cx="12" cy="12" r="9"></circle>'
            . '<path d="M9.4 8.4 12 6.5l2.6 1.9-1 3.1h-3.2l-1-3.1Z"></path>'
            . '<path d="m10.4 11.5-2.8 2.1.9 3.1"></path>'
            . '<path d="m13.6 11.5 2.8 2.1-.9 3.1"></path>'
            . '<path d="M9.4 8.4 6.6 8"></path>'
            . '<path d="M14.6 8.4 17.4 8"></path>'
            . '<path d="m8.5 16.7 3.5 1.8 3.5-1.8"></path>'
            . '</svg>';
    }

    if ($name === 'target' || $name === 'dartboard') {
        return '<svg class="' . $svgClass . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<circle cx="11" cy="13" r="7"></circle>'
            . '<circle cx="11" cy="13" r="4"></circle>'
            . '<circle cx="11" cy="13" r="1.4" fill="currentColor" stroke="none"></circle>'
            . '<path d="M15.5 8.5 20 4"></path>'
            . '<path d="M17.7 4H20v2.3"></path>'
            . '<path d="M20 4h-2.3"></path>'
            . '</svg>';
    }

    $icons = [
        'home' => 'bi-house-door-fill',
        'facebook' => 'bi-facebook',
        'telegram' => 'bi-telegram',
        'tiktok' => 'bi-tiktok',
        'more' => 'bi-three-dots',
        'star' => 'bi-star-fill',
        'settings' => 'bi-gear-fill',
        'refresh' => 'bi-arrow-clockwise',
        'calendar' => 'bi-calendar2-week-fill',
        'x' => 'bi-x-lg',
        'globe' => 'bi-translate',
        'trophy' => 'bi-trophy-fill',
        'ticket' => 'bi-ticket-perforated-fill',
        'spark' => 'bi-stars',
        'news' => 'bi-newspaper',
        'mail' => 'bi-envelope-fill',
        'inbox' => 'bi-envelope-fill',
        'activity' => 'bi-activity',
        'play' => 'bi-play-circle-fill',
        'target' => 'bi-bullseye',
        'shield' => 'bi-shield-check',
        'wallet' => 'bi-wallet2',
        'person' => 'bi-person-fill',
        'phone' => 'bi-telephone-fill',
        'lock' => 'bi-lock-fill',
        'ball' => 'bi-dribbble',
        'link' => 'bi-box-arrow-up-right',
        'chevron' => 'bi-chevron-down',
        'trash' => 'bi-trash3-fill',
        'expand' => 'bi-arrows-fullscreen',
        'check' => 'bi-check2-circle',
        'controller' => 'bi-controller',
        'market' => 'bi-bar-chart-line-fill',
        'odds' => 'bi-graph-up-arrow',
        'gold' => 'bi-coin',
        'currency' => 'bi-cash-coin',
        'fuel' => 'bi-fuel-pump-fill',
        'trend_up' => 'bi-arrow-up-right-circle-fill',
        'trend_down' => 'bi-arrow-down-right-circle-fill',
    ];

    $iconClass = $icons[$name] ?? $icons['spark'];
    return '<i class="bi ' . e($iconClass . ' ' . $class) . '" aria-hidden="true"></i>';
}

function fta_category_icon_name(string $category): string
{
    $name = strtolower($category);
    if (str_contains($name, 'ibet')) {
        return 'ticket';
    }
    if (str_contains($name, 'sport')) {
        return 'trophy';
    }
    if (str_contains($name, 'news')) {
        return 'news';
    }
    if (str_contains($name, 'score')) {
        return 'score';
    }
    if ($name === 'live' || str_contains($name, ' live')) {
        return 'play';
    }
    if (str_contains($name, 'prediction')) {
        return 'target';
    }
    if (str_contains($name, 'market') || str_contains($name, 'gold') || str_contains($name, 'currency') || str_contains($name, 'fuel')) {
        return 'market';
    }
    if (str_contains($name, 'odds') || str_contains($name, 'ပေါက်ကြေး')) {
        return 'odds';
    }
    if (str_contains($name, '555') || str_contains($name, 'mix')) {
        return 'spark';
    }
    return 'ball';
}

function fta_category_icon_html(array $category): string
{
    $iconPath = trim((string) ($category['icon_path'] ?? ''));
    if ($iconPath !== '') {
        return '<img class="category-custom-icon" src="' . e(fta_image_src($iconPath)) . '" alt="">';
    }

    return fta_icon(fta_category_icon_name((string) ($category['name'] ?? '')));
}

function fta_render_head(string $title, string $class = ''): void
{
    $lang = fta_current_lang();
    fta_send_security_headers(false);
    fta_start_session();
    if (!empty($_COOKIE[fta_remember_cookie_name()])) {
        fta_current_user();
    }
    if (str_contains($class, 'public-page') && !str_contains($class, 'auth-page') && !fta_current_user()) {
        fta_redirect(fta_lang_url($lang, 'login.php'));
    }
    $version = fta_setting('site_version', '1');
    ?>
<!doctype html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($title) ?> | <?= e(FTA_APP_NAME) ?></title>
    <meta name="theme-color" content="#075ed0">
    <meta name="description" content="FullTime Arena football prediction, live score, news, odds, and updates.">
    <link rel="manifest" href="<?= e(fta_base_url('manifest.webmanifest.php')) ?>">
    <link rel="icon" href="<?= e(fta_logo_url()) ?>">
    <link rel="apple-touch-icon" href="<?= e(fta_logo_url()) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(fta_base_url('assets/css/styles.css')) ?>?v=<?= e(fta_asset_version('assets/css/styles.css')) ?>">
    <script>
        window.FTA_CONFIG = {
            baseUrl: <?= json_encode(fta_base_url(), JSON_UNESCAPED_SLASHES) ?>,
            lang: <?= json_encode($lang) ?>,
            version: <?= json_encode($version) ?>
        };
    </script>
</head>
<body class="<?= e($class) ?>">
    <?php
}

function fta_render_public_header(string $active = 'home'): void
{
    $lang = fta_current_lang();
    $pages = [
        'prediction' => 'prediction.php',
        'news' => 'news.php',
        'live_score' => 'live-score.php',
        'live' => 'live.php',
        'unit' => 'unit.php',
        'inbox' => 'inbox.php',
        'more' => 'more.php',
        'login' => 'login.php',
        'signup' => 'signup.php',
        'forgot' => 'forgot-password.php',
    ];
    $page = $pages[$active] ?? '';
    $user = fta_current_user();
    $agentContact = function_exists('fta_agent_contact_for_user') ? fta_agent_contact_for_user($user) : [];
    $languages = [
        ['code' => 'en', 'label' => 'English', 'short' => 'EN', 'flag' => 'flag-us'],
        ['code' => 'my', 'label' => 'မြန်မာ', 'short' => 'MY', 'flag' => 'flag-mm'],
        ['code' => 'jp', 'label' => '日本語', 'short' => 'JP', 'flag' => 'flag-jp'],
        ['code' => 'th', 'label' => 'ไทย', 'short' => 'TH', 'flag' => 'flag-th'],
    ];
    $activeLanguage = $languages[0];
    foreach ($languages as $language) {
        if ($language['code'] === $lang) {
            $activeLanguage = $language;
            break;
        }
    }
    ?>
    <header class="site-header">
        <a class="brand" href="<?= e(fta_lang_url($lang)) ?>" aria-label="<?= e(FTA_APP_NAME) ?>">
            <img src="<?= e(fta_logo_url()) ?>" alt="" class="brand-logo">
            <span><?= e(FTA_APP_NAME) ?></span>
        </a>
        <div class="site-header-actions">
            <?php if ($user): ?>
                <?php if ($agentContact): ?>
                    <button class="agent-profile-btn" type="button" data-agent-contact-toggle>
                        <?= fta_icon('person') ?><span><?= e(t('agent_profile', 'Agent Profile')) ?></span>
                    </button>
                <?php endif; ?>
            <?php else: ?>
                <a class="header-auth-link" href="<?= e(fta_lang_url($lang, 'login.php')) ?>"><?= fta_icon('person') ?><span><?= e(t('login', 'Login')) ?></span></a>
            <?php endif; ?>
        </div>
    </header>
    <?php if ($agentContact): ?>
        <div class="agent-contact-modal" data-agent-contact-modal hidden>
            <div class="agent-contact-card" role="dialog" aria-modal="true" aria-labelledby="agent-contact-title">
                <button type="button" class="modal-close" data-agent-contact-close aria-label="Close"><?= fta_icon('x') ?></button>
                <span class="agent-contact-kicker"><?= e(t('agent_profile', 'Agent Profile')) ?></span>
                <h2 id="agent-contact-title"><?= e($agentContact['agent_name'] ?? 'Agent') ?></h2>
                <p><?= e(t('promo_code', 'Promocode')) ?>: <?= e($agentContact['promo_code'] ?? '') ?></p>
                <div class="agent-contact-list">
                    <?php foreach (['phone' => 'phone', 'viber' => 'phone', 'telegram' => 'telegram', 'facebook' => 'facebook', 'tiktok' => 'tiktok'] as $key => $icon): ?>
                        <?php if (!empty($agentContact[$key])): ?>
                            <a href="<?= e($key === 'phone' ? 'tel:' . $agentContact[$key] : (preg_match('/^https?:\/\//i', (string) $agentContact[$key]) ? (string) $agentContact[$key] : '#')) ?>" target="<?= $key === 'phone' ? '_self' : '_blank' ?>" rel="noopener">
                                <?= fta_icon($icon) ?><span><?= e(ucfirst($key)) ?></span><strong><?= e($agentContact[$key]) ?></strong>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php
}

function fta_render_footer_nav(string $active = 'home'): void
{
    $lang = fta_current_lang();
    ?>
    <nav class="bottom-nav" aria-label="<?= e(t('footer_nav')) ?>">
        <a class="<?= $active === 'home' ? 'active' : '' ?>" href="<?= e(fta_lang_url($lang)) ?>"><?= fta_icon('home') ?><span><?= e(t('home')) ?></span></a>
        <a class="<?= $active === 'news' ? 'active' : '' ?>" href="<?= e(fta_lang_url($lang, 'news.php')) ?>"><?= fta_icon('news') ?><span><?= e(t('news')) ?></span></a>
        <a class="<?= $active === 'unit' ? 'active' : '' ?>" href="<?= e(fta_lang_url($lang, 'unit.php')) ?>" data-unit-choice-toggle><?= fta_icon('wallet') ?><span><?= e(t('unit', 'Unit')) ?></span></a>
        <a class="<?= $active === 'inbox' ? 'active' : '' ?>" href="<?= e(fta_lang_url($lang, 'inbox.php')) ?>" data-mail-nav><?= fta_icon('mail') ?><span class="mail-badge" data-mail-badge hidden>0</span><span><?= e(t('mail', 'Mail')) ?></span></a>
        <a class="<?= $active === 'more' ? 'active' : '' ?>" href="<?= e(fta_lang_url($lang, 'more.php')) ?>"><?= fta_icon('more') ?><span><?= e(t('more')) ?></span></a>
    </nav>
    <div class="unit-choice-modal" data-unit-choice-modal hidden>
        <div class="unit-choice-dialog" role="dialog" aria-modal="true" aria-labelledby="unit-choice-title">
            <button type="button" class="modal-close" data-unit-choice-close aria-label="Close"><?= fta_icon('x') ?></button>
            <span><?= e(t('unit', 'Unit')) ?></span>
            <h2 id="unit-choice-title"><?= e(t('unit_choice_title', 'Choose unit action')) ?></h2>
            <div class="unit-choice-actions">
                <a class="primary-btn" href="<?= e(fta_lang_url($lang, 'unit.php') . '?mode=deposit') ?>"><?= fta_icon('wallet') ?><span><?= e(t('unit_in', 'In / Deposit')) ?></span></a>
                <a class="ghost-btn" href="<?= e(fta_lang_url($lang, 'unit.php') . '?mode=withdraw') ?>"><?= fta_icon('activity') ?><span><?= e(t('unit_out', 'Out / Withdraw')) ?></span></a>
            </div>
        </div>
    </div>
    <?php
}

function fta_render_telegram_modal(): void
{
    ?>
    <div class="telegram-modal" data-telegram-modal hidden>
        <div class="telegram-card" role="dialog" aria-modal="true" aria-labelledby="telegram-title">
            <button type="button" class="modal-close" data-close-telegram aria-label="Close"><?= fta_icon('x') ?></button>
            <div class="telegram-mark">
                <img src="<?= e(fta_logo_url()) ?>" alt="" class="telegram-logo">
                <span><?= fta_icon('telegram') ?></span>
            </div>
            <h2 id="telegram-title"><?= e(fta_setting('telegram_popup_title', t('join_telegram_title'))) ?></h2>
            <p><?= e(fta_setting('telegram_popup_text', t('join_telegram_text'))) ?></p>
            <div class="dialog-actions">
                <a class="primary-btn" href="<?= e(FTA_TELEGRAM_URL) ?>" target="_blank" rel="noopener"><?= fta_icon('telegram') ?><span><?= e(t('join_now')) ?></span></a>
                <button type="button" class="ghost-btn" data-close-telegram><?= e(t('later')) ?></button>
            </div>
            <label class="check-row">
                <input type="checkbox" data-hide-telegram-today>
                <span><?= e(t('dont_show_today')) ?></span>
            </label>
        </div>
    </div>
    <?php
}

function fta_render_page_end(): void
{
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= e(fta_base_url('assets/js/app.js')) ?>?v=<?= e(fta_asset_version('assets/js/app.js')) ?>"></script>
</body>
</html>
    <?php
}

function fta_render_db_error(Throwable $error): void
{
    http_response_code(500);
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup needed | FullTime Arena</title>
    <link rel="stylesheet" href="<?= e(fta_base_url('assets/css/styles.css')) ?>">
</head>
<body class="setup-page">
    <main class="setup-box">
        <img src="<?= e(fta_logo_url()) ?>" alt="" class="setup-logo">
        <h1>Database connection needed</h1>
        <p>Start MySQL in XAMPP, then reload this page. The app will create the <strong>fulltimearena</strong> database automatically.</p>
        <code><?= e($error->getMessage()) ?></code>
    </main>
</body>
</html>
    <?php
}
