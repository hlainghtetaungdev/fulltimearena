<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/admin_auth.php';
require_once __DIR__ . '/../app/layout.php';

function admin_pages(): array
{
    return [
        'dashboard' => ['label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
        'agents' => ['label' => 'Sub Admin Management', 'icon' => 'bi-person-badge-fill'],
        'settings' => ['label' => 'Settings', 'icon' => 'bi-sliders'],
        'ads' => ['label' => 'Ads Slides', 'icon' => 'bi-images'],
        'live_matches' => ['label' => 'Live Matches', 'icon' => 'bi-broadcast-pin'],
        'notifications' => ['label' => 'Notifications', 'icon' => 'bi-envelope-paper-fill'],
        'categories' => ['label' => 'Categories', 'icon' => 'bi-grid-3x3-gap-fill'],
        'submissions' => ['label' => 'Predictions', 'icon' => 'bi-inboxes-fill'],
        'security' => ['label' => 'Security', 'icon' => 'bi-shield-lock-fill'],
    ];
}

function admin_valid_page(string $page): string
{
    return array_key_exists($page, admin_pages()) ? $page : 'dashboard';
}

function admin_requested_page(): string
{
    return admin_valid_page((string) ($_GET['page'] ?? 'dashboard'));
}

function admin_action_page(string $action): string
{
    return match ($action) {
        'save_settings', 'push_update' => 'settings',
        'create_agent', 'update_agent', 'change_agent_password' => 'agents',
        'add_ad', 'delete_ad', 'toggle_ad', 'update_ad' => 'ads',
        'add_live_match', 'update_live_match', 'delete_live_match' => 'live_matches',
        'send_notification', 'toggle_notification', 'delete_notification' => 'notifications',
        'update_categories', 'add_category', 'delete_category' => 'categories',
        'clear_submissions', 'update_submission_winners', 'bulk_submission_status' => 'submissions',
        'change_password' => 'security',
        default => 'dashboard',
    };
}

function admin_post_redirect_page(string $action): string
{
    $requested = (string) ($_POST['redirect_page'] ?? '');
    return admin_valid_page($requested !== '' ? $requested : admin_action_page($action));
}

function admin_page_url(string $page = 'dashboard'): string
{
    $page = admin_valid_page($page);
    if ($page === 'dashboard') {
        return fta_admin_url();
    }

    return fta_admin_url('?page=' . rawurlencode($page));
}

function admin_redirect_input(string $page): void
{
    ?>
    <input type="hidden" name="redirect_page" value="<?= e(admin_valid_page($page)) ?>">
    <?php
}

function admin_datetime_value(string $value): string
{
    if ($value === '' || strtotime($value) === false) {
        return '';
    }

    return date('Y-m-d\TH:i', strtotime($value));
}

function admin_live_match_input(): array
{
    $homeName = trim((string) ($_POST['home_name'] ?? ''));
    $awayName = trim((string) ($_POST['away_name'] ?? ''));
    if ($homeName === '' || $awayName === '') {
        throw new RuntimeException('Home and away team names are required.');
    }

    $streams = fta_live_streams_from_text((string) ($_POST['streams'] ?? ''));

    return [
        'league_name' => trim((string) ($_POST['league_name'] ?? '')),
        'match_time' => trim((string) ($_POST['match_time'] ?? '')),
        'status_text' => trim((string) ($_POST['status_text'] ?? '')),
        'is_live' => (string) ($_POST['match_type'] ?? 'live') === 'upcoming' ? 0 : 1,
        'home_name' => $homeName,
        'home_logo' => trim((string) ($_POST['home_logo'] ?? '')),
        'away_name' => $awayName,
        'away_logo' => trim((string) ($_POST['away_logo'] ?? '')),
        'streams_json' => json_encode($streams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        'active' => !empty($_POST['active']) ? 1 : 0,
    ];
}

try {
    fta_require_admin();
    $adminUser = fta_admin_user();
    $activePage = admin_requested_page();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        fta_check_csrf();
        $action = (string) ($_POST['action'] ?? '');
        $redirectPage = admin_post_redirect_page($action);

        if ($action === 'save_settings') {
            fta_save_setting('form_open', !empty($_POST['form_open']) ? '1' : '0');
            fta_save_setting('form_start_at', trim((string) ($_POST['form_start_at'] ?? '')));
            fta_save_setting('form_end_at', trim((string) ($_POST['form_end_at'] ?? '')));
            fta_save_setting('team_a_name', trim((string) ($_POST['team_a_name'] ?? 'Team A')));
            fta_save_setting('team_b_name', trim((string) ($_POST['team_b_name'] ?? 'Team B')));
            fta_save_setting('prize_total', trim((string) ($_POST['prize_total'] ?? '1,000,000 Kyat')));
            fta_save_setting('prize_each', trim((string) ($_POST['prize_each'] ?? '50,000 Kyat')));
            fta_save_setting('telegram_popup_title', trim((string) ($_POST['telegram_popup_title'] ?? 'Join FullTime Arena')));
            fta_save_setting('telegram_popup_text', trim((string) ($_POST['telegram_popup_text'] ?? 'Get updates on Telegram.')));
            $telegramBotToken = trim((string) ($_POST['telegram_bot_token'] ?? ''));
            if ($telegramBotToken !== '') {
                fta_save_setting('telegram_bot_token', $telegramBotToken);
            }
            fta_save_setting('app_announcement_enabled', !empty($_POST['app_announcement_enabled']) ? '1' : '0');
            fta_save_setting('app_announcement_title', trim((string) ($_POST['app_announcement_title'] ?? 'FullTime Arena')));
            fta_save_setting('app_announcement_text', trim((string) ($_POST['app_announcement_text'] ?? '')));
            fta_save_setting('live_refresh_seconds', (string) max(15, min(300, (int) ($_POST['live_refresh_seconds'] ?? 60))));
            fta_save_setting('live_player_note', trim((string) ($_POST['live_player_note'] ?? '')));
            fta_save_setting('score_detail_enabled', !empty($_POST['score_detail_enabled']) ? '1' : '0');
            $guideVideos = fta_app_guide_videos_from_post($_POST);
            fta_save_setting('app_guide_videos', json_encode($guideVideos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            fta_save_setting('app_guide_youtube_url', (string) ($guideVideos[0]['url'] ?? ''));
            fta_save_setting('app_update_download_url', fta_clean_link($_POST['app_update_download_url'] ?? ''));

            $currentSettings = fta_settings();
            if (!empty($_POST['clear_team_a_logo'])) {
                fta_delete_upload($currentSettings['team_a_logo'] ?? '');
                fta_save_setting('team_a_logo', '');
            }
            if (!empty($_POST['clear_team_b_logo'])) {
                fta_delete_upload($currentSettings['team_b_logo'] ?? '');
                fta_save_setting('team_b_logo', '');
            }

            $teamALogo = fta_upload_image('team_a_logo', 'team');
            if ($teamALogo !== '') {
                fta_delete_upload($currentSettings['team_a_logo'] ?? '');
                fta_save_setting('team_a_logo', $teamALogo);
            }

            $teamBLogo = fta_upload_image('team_b_logo', 'team');
            if ($teamBLogo !== '') {
                fta_delete_upload($currentSettings['team_b_logo'] ?? '');
                fta_save_setting('team_b_logo', $teamBLogo);
            }

            fta_flash('success', 'Dashboard settings saved.');
        }

        if ($action === 'add_ad') {
            $image = fta_upload_image('ad_image', 'ads');
            if ($image === '') {
                throw new RuntimeException('Please choose an ad image.');
            }

            $statement = db()->prepare('INSERT INTO ads (image_path, link_url, sort_order, active) VALUES (:image_path, :link_url, :sort_order, 1)');
            $statement->execute([
                'image_path' => $image,
                'link_url' => fta_clean_link($_POST['link_url'] ?? ''),
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            ]);
            fta_flash('success', 'Ad slide added.');
        }

        if ($action === 'delete_ad') {
            $id = (int) ($_POST['id'] ?? 0);
            $statement = db()->prepare('SELECT image_path FROM ads WHERE id = :id');
            $statement->execute(['id' => $id]);
            $ad = $statement->fetch();
            if ($ad) {
                fta_delete_upload($ad['image_path']);
                db()->prepare('DELETE FROM ads WHERE id = :id')->execute(['id' => $id]);
                fta_flash('success', 'Ad slide deleted.');
            }
        }

        if ($action === 'toggle_ad') {
            $id = (int) ($_POST['id'] ?? 0);
            $active = !empty($_POST['active']) ? 1 : 0;
            db()->prepare('UPDATE ads SET active = :active WHERE id = :id')->execute(['active' => $active, 'id' => $id]);
            fta_flash('success', 'Ad status updated.');
        }

        if ($action === 'update_ad') {
            $id = (int) ($_POST['id'] ?? 0);
            $statement = db()->prepare('UPDATE ads SET link_url = :link_url, sort_order = :sort_order, active = :active WHERE id = :id');
            $statement->execute([
                'id' => $id,
                'link_url' => fta_clean_link($_POST['link_url'] ?? ''),
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                'active' => !empty($_POST['active']) ? 1 : 0,
            ]);
            fta_flash('success', 'Ad slide updated.');
        }

        if ($action === 'add_live_match') {
            $match = admin_live_match_input();
            $statement = db()->prepare('
                INSERT INTO live_matches (
                    league_name, match_time, status_text, is_live, home_name, home_logo,
                    away_name, away_logo, streams_json, sort_order, active
                ) VALUES (
                    :league_name, :match_time, :status_text, :is_live, :home_name, :home_logo,
                    :away_name, :away_logo, :streams_json, :sort_order, :active
                )
            ');
            $statement->execute($match);
            fta_flash('success', 'Live match added.');
        }

        if ($action === 'update_live_match') {
            $id = (int) ($_POST['id'] ?? 0);
            $match = admin_live_match_input();
            $match['id'] = $id;
            $statement = db()->prepare('
                UPDATE live_matches SET
                    league_name = :league_name,
                    match_time = :match_time,
                    status_text = :status_text,
                    is_live = :is_live,
                    home_name = :home_name,
                    home_logo = :home_logo,
                    away_name = :away_name,
                    away_logo = :away_logo,
                    streams_json = :streams_json,
                    sort_order = :sort_order,
                    active = :active
                WHERE id = :id
            ');
            $statement->execute($match);
            fta_flash('success', 'Live match updated.');
        }

        if ($action === 'delete_live_match') {
            $id = (int) ($_POST['id'] ?? 0);
            db()->prepare('DELETE FROM live_matches WHERE id = :id')->execute(['id' => $id]);
            fta_flash('success', 'Live match deleted.');
        }

        if ($action === 'create_agent') {
            fta_create_agent_account($_POST, (int) ($adminUser['id'] ?? 0));
            fta_flash('success', 'Sub admin account created.');
        }

        if ($action === 'update_agent') {
            fta_update_agent_account($_POST);
            fta_flash('success', 'Sub admin account updated.');
        }

        if ($action === 'change_agent_password') {
            fta_change_staff_password((int) ($_POST['agent_id'] ?? 0), (string) ($_POST['new_password'] ?? ''));
            fta_flash('success', 'Sub admin password changed.');
        }

        if ($action === 'send_notification') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $body = trim((string) ($_POST['body'] ?? ''));
            if ($title === '' || $body === '') {
                throw new RuntimeException('Notification title and text are required.');
            }

            $statement = db()->prepare('INSERT INTO notifications (title, body, active) VALUES (:title, :body, 1)');
            $statement->execute([
                'title' => substr($title, 0, 180),
                'body' => $body,
            ]);
            fta_save_setting('site_version', (string) time());
            fta_flash('success', 'Notification sent to Inbox.');
        }

        if ($action === 'toggle_notification') {
            $id = (int) ($_POST['id'] ?? 0);
            $active = !empty($_POST['active']) ? 1 : 0;
            db()->prepare('UPDATE notifications SET active = :active WHERE id = :id')->execute(['active' => $active, 'id' => $id]);
            fta_flash('success', 'Notification status updated.');
        }

        if ($action === 'delete_notification') {
            $id = (int) ($_POST['id'] ?? 0);
            db()->prepare('DELETE FROM notifications WHERE id = :id')->execute(['id' => $id]);
            fta_flash('success', 'Notification deleted.');
        }

        if ($action === 'update_categories') {
            $categories = $_POST['categories'] ?? [];
            if (is_array($categories)) {
                $statement = db()->prepare('UPDATE categories SET name = :name, link_url = :link_url, sort_order = :sort_order, active = :active WHERE id = :id');
                foreach ($categories as $id => $category) {
                    if (!is_array($category)) {
                        continue;
                    }

                    $id = (int) $id;
                    $existingStatement = db()->prepare('SELECT icon_path FROM categories WHERE id = :id');
                    $existingStatement->execute(['id' => $id]);
                    $existing = $existingStatement->fetch();

                    if (!empty($category['clear_icon']) && $existing) {
                        fta_delete_upload($existing['icon_path'] ?? '');
                        db()->prepare('UPDATE categories SET icon_path = "" WHERE id = :id')->execute(['id' => $id]);
                    }

                    $iconPath = fta_upload_image_from_array('category_icons', $id, 'category-icons');
                    if ($iconPath !== '') {
                        fta_delete_upload($existing['icon_path'] ?? '');
                        db()->prepare('UPDATE categories SET icon_path = :icon_path WHERE id = :id')->execute(['icon_path' => $iconPath, 'id' => $id]);
                    }

                    $statement->execute([
                        'id' => $id,
                        'name' => trim((string) ($category['name'] ?? '')),
                        'link_url' => fta_clean_link($category['link_url'] ?? ''),
                        'sort_order' => (int) ($category['sort_order'] ?? 0),
                        'active' => !empty($category['active']) ? 1 : 0,
                    ]);
                }
            }
            fta_flash('success', 'Categories updated.');
        }

        if ($action === 'add_category') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Category name is required.');
            }

            $iconPath = fta_upload_image('category_icon', 'category-icons');
            $statement = db()->prepare('INSERT INTO categories (name, link_url, icon_path, sort_order, active) VALUES (:name, :link_url, :icon_path, :sort_order, 1)');
            $statement->execute([
                'name' => $name,
                'link_url' => fta_clean_link($_POST['link_url'] ?? ''),
                'icon_path' => $iconPath,
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            ]);
            fta_flash('success', 'Category added.');
        }

        if ($action === 'delete_category') {
            $id = (int) ($_POST['id'] ?? 0);
            $statement = db()->prepare('SELECT icon_path FROM categories WHERE id = :id');
            $statement->execute(['id' => $id]);
            $category = $statement->fetch();
            if ($category) {
                fta_delete_upload($category['icon_path'] ?? '');
            }
            db()->prepare('DELETE FROM categories WHERE id = :id')->execute(['id' => $id]);
            fta_flash('success', 'Category deleted.');
        }

        if ($action === 'clear_submissions') {
            if ((string) ($_POST['clear_confirm'] ?? '') !== 'CLEAR') {
                throw new RuntimeException('Type CLEAR to delete prediction data.');
            }
            db()->exec('TRUNCATE TABLE submissions');
            fta_flash('success', 'Prediction submissions cleared.');
        }

        if ($action === 'update_submission_winners') {
            $ids = array_values(array_filter(array_map('intval', (array) ($_POST['submission_ids'] ?? []))));
            $statusInput = is_array($_POST['result_status'] ?? null) ? $_POST['result_status'] : [];
            $save = db()->prepare('UPDATE submissions SET result_status = :status, is_winner = :is_winner WHERE id = :id');
            foreach ($ids as $id) {
                $status = (string) ($statusInput[$id] ?? 'pending');
                if (!in_array($status, ['pending', 'win', 'lose'], true)) {
                    $status = 'pending';
                }
                $save->execute([
                    'id' => $id,
                    'status' => $status,
                    'is_winner' => $status === 'win' ? 1 : 0,
                ]);
            }
            fta_flash('success', 'Prediction results saved.');
        }

        if ($action === 'bulk_submission_status') {
            $status = (string) ($_POST['bulk_status'] ?? 'pending');
            if (!in_array($status, ['pending', 'win', 'lose'], true)) {
                throw new RuntimeException('Prediction status is invalid.');
            }
            db()->prepare('UPDATE submissions SET result_status = :status, is_winner = :is_winner')->execute([
                'status' => $status,
                'is_winner' => $status === 'win' ? 1 : 0,
            ]);
            fta_flash('success', 'All prediction submissions updated.');
        }

        if ($action === 'push_update') {
            fta_save_setting('site_version', (string) time());
            fta_flash('success', 'App update version changed. Users can press Update app data or refresh.');
        }

        if ($action === 'change_password') {
            $password = (string) ($_POST['new_password'] ?? '');
            if (strlen($password) < 8) {
                throw new RuntimeException('Password must be at least 8 characters.');
            }
            if (($adminUser['role'] ?? '') === 'super') {
                fta_change_staff_password((int) $adminUser['id'], $password);
            } else {
                $statement = db()->prepare('UPDATE admin_users SET password_hash = :hash WHERE id = :id');
                $statement->execute([
                    'hash' => password_hash($password, PASSWORD_DEFAULT),
                    'id' => (int) $adminUser['id'],
                ]);
            }
            fta_flash('success', 'Super password changed.');
        }

        fta_redirect(admin_page_url($redirectPage));
    }

    $settings = fta_settings();
    $guideVideos = fta_app_guide_videos_from_settings($settings);
    $ads = fta_all_ads();
    $liveMatches = fta_all_live_matches();
    $notifications = fta_all_notifications(100);
    $categories = fta_all_categories();
    $submissions = fta_submissions(300);
    $agents = fta_all_agents(500);
    $analysis = fta_agent_analysis(null);
    $flashes = fta_take_flashes();
} catch (Throwable $error) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? '');
        fta_flash('error', $error->getMessage());
        fta_redirect(admin_page_url(admin_post_redirect_page($action)));
    }

    fta_render_db_error($error);
    exit;
}

$pages = admin_pages();
$pageTitle = $pages[$activePage]['label'] ?? 'Dashboard';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(FTA_APP_NAME) ?> Super</title>
    <link rel="icon" href="<?= e(fta_logo_url()) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(fta_base_url('assets/css/styles.css')) ?>?v=<?= e(fta_asset_version('assets/css/styles.css')) ?>">
</head>
<body class="admin-page">
    <header class="admin-app-header">
        <button class="icon-btn admin-menu-toggle" type="button" data-admin-menu-toggle aria-label="Open admin menu">
            <i class="bi bi-list app-icon" aria-hidden="true"></i>
        </button>
        <a class="brand admin-brand" href="<?= e(admin_page_url()) ?>">
            <img src="<?= e(fta_logo_url()) ?>" alt="" class="brand-logo">
            <span><?= e(FTA_APP_NAME) ?></span>
        </a>
        <div class="admin-header-actions">
            <span class="admin-user-chip"><i class="bi bi-person-circle" aria-hidden="true"></i><?= e($adminUser['display_name'] ?? $adminUser['username'] ?? 'super') ?></span>
            <a class="ghost-btn admin-public-link" href="<?= e(fta_lang_url(FTA_DEFAULT_LANG)) ?>" target="_blank" rel="noopener">View Site</a>
            <a class="ghost-btn" href="<?= e(fta_admin_url('logout.php')) ?>">Logout</a>
        </div>
    </header>

    <div class="admin-scrim" data-admin-menu-close></div>
    <div class="admin-layout">
        <aside class="admin-sidebar" data-admin-sidebar>
            <div class="sidebar-heading">
                <img src="<?= e(fta_logo_url()) ?>" alt="">
                <div>
                    <strong>Super Panel</strong>
                    <span>Agents, app, and prediction control</span>
                </div>
            </div>
            <nav class="admin-nav" aria-label="Admin navigation">
                <?php foreach ($pages as $page => $item): ?>
                    <a class="<?= $activePage === $page ? 'active' : '' ?>" href="<?= e(admin_page_url($page)) ?>">
                        <i class="bi <?= e($item['icon']) ?>" aria-hidden="true"></i>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <main class="admin-shell">
            <?php foreach ($flashes as $flash): ?>
                <div class="notice <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endforeach; ?>

            <div class="admin-page-title">
                <div>
                    <span>FullTime Arena</span>
                    <h1><?= e($pageTitle) ?></h1>
                </div>
                <form method="post" class="admin-update-form">
                    <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                    <input type="hidden" name="action" value="push_update">
                    <?php admin_redirect_input($activePage); ?>
                    <button class="primary-btn" type="submit"><i class="bi bi-cloud-arrow-up-fill" aria-hidden="true"></i><span>Push Update</span></button>
                </form>
            </div>

            <?php if ($activePage === 'dashboard'): ?>
                <section class="admin-stats">
                    <article>
                        <span>Total Users</span>
                        <strong><?= (int) ($analysis['total_users'] ?? 0) ?></strong>
                    </article>
                    <article>
                        <span>Deposit Total</span>
                        <strong><?= number_format((float) ($analysis['deposit_total'] ?? 0)) ?></strong>
                    </article>
                    <article>
                        <span>Withdraw Total</span>
                        <strong><?= number_format((float) ($analysis['withdraw_total'] ?? 0)) ?></strong>
                    </article>
                    <article>
                        <span>Sub Admins</span>
                        <strong><?= count($agents) ?></strong>
                    </article>
                </section>

                <section class="admin-panel analytics-panel">
                    <div class="panel-title">
                        <h2>Platform Analytics</h2>
                        <span>All-user overview across every active sub admin.</span>
                    </div>
                    <div class="analytics-grid">
                        <article><i class="bi bi-person-plus-fill" aria-hidden="true"></i><span>New users today</span><strong><?= (int) ($analysis['new_users_today'] ?? 0) ?></strong></article>
                        <article><i class="bi bi-arrow-down-circle-fill" aria-hidden="true"></i><span>Today deposit</span><strong><?= number_format((float) ($analysis['today_deposit_total'] ?? 0)) ?></strong></article>
                        <article><i class="bi bi-arrow-up-circle-fill" aria-hidden="true"></i><span>Today withdraw</span><strong><?= number_format((float) ($analysis['today_withdraw_total'] ?? 0)) ?></strong></article>
                        <article><i class="bi bi-cash-stack" aria-hidden="true"></i><span>Total requests</span><strong><?= (int) ($analysis['total_requests'] ?? 0) ?></strong></article>
                        <article><i class="bi bi-check2-circle" aria-hidden="true"></i><span>Approved today</span><strong><?= (int) ($analysis['today_approved_requests'] ?? 0) ?></strong></article>
                        <article><i class="bi bi-x-octagon-fill" aria-hidden="true"></i><span>Rejected today</span><strong><?= (int) ($analysis['today_rejected_requests'] ?? 0) ?></strong></article>
                        <article><i class="bi bi-wallet2" aria-hidden="true"></i><span>Pending amount</span><strong><?= number_format((float) ($analysis['pending_amount_total'] ?? 0)) ?></strong></article>
                        <article><i class="bi bi-stopwatch-fill" aria-hidden="true"></i><span>Avg review</span><strong><?= number_format((float) ($analysis['avg_review_minutes'] ?? 0), 1) ?> min</strong></article>
                    </div>
                    <div class="analytics-status-row">
                        <span>Pending: <strong><?= (int) ($analysis['pending_requests'] ?? 0) ?></strong></span>
                        <span>Approved: <strong><?= (int) ($analysis['approved_requests'] ?? 0) ?></strong></span>
                        <span>Rejected: <strong><?= (int) ($analysis['rejected_requests'] ?? 0) ?></strong></span>
                        <span>Pending In/Out: <strong><?= (int) ($analysis['pending_deposit_requests'] ?? 0) ?> / <?= (int) ($analysis['pending_withdraw_requests'] ?? 0) ?></strong></span>
                        <span>Approved In/Out: <strong><?= (int) ($analysis['approved_deposit_requests'] ?? 0) ?> / <?= (int) ($analysis['approved_withdraw_requests'] ?? 0) ?></strong></span>
                        <span>Rejected In/Out: <strong><?= (int) ($analysis['rejected_deposit_requests'] ?? 0) ?> / <?= (int) ($analysis['rejected_withdraw_requests'] ?? 0) ?></strong></span>
                    </div>
                </section>

                <section class="admin-panel">
                    <div class="panel-title">
                        <h2>Quick Manage</h2>
                        <span>Choose one section from the sidebar or cards.</span>
                    </div>
                    <div class="admin-quick-grid">
                        <?php foreach (array_filter(array_keys($pages), fn ($page) => $page !== 'dashboard') as $page): ?>
                            <a href="<?= e(admin_page_url($page)) ?>">
                                <i class="bi <?= e($pages[$page]['icon']) ?>" aria-hidden="true"></i>
                                <strong><?= e($pages[$page]['label']) ?></strong>
                                <span>Open management page</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($activePage === 'agents'): ?>
                <section class="admin-panel">
                    <div class="panel-title">
                        <h2>Create Sub Admin</h2>
                        <span>Every signup must use one active promocode.</span>
                    </div>
                    <form method="post" class="admin-form grid-form">
                        <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                        <input type="hidden" name="action" value="create_agent">
                        <?php admin_redirect_input($activePage); ?>
                        <label>
                            <span>Username</span>
                            <input type="text" name="username" required autocomplete="off" placeholder="agent001">
                        </label>
                        <label>
                            <span>Display name</span>
                            <input type="text" name="display_name" placeholder="Agent name">
                        </label>
                        <label>
                            <span>Promocode</span>
                            <input type="text" name="promo_code" required autocomplete="off" placeholder="FTA001">
                        </label>
                        <label>
                            <span>Password</span>
                            <input type="password" name="password" required minlength="8" autocomplete="new-password">
                        </label>
                        <label>
                            <span>Account expiry</span>
                            <input type="datetime-local" name="expires_at">
                        </label>
                        <button class="primary-btn wide-field" type="submit">Create Sub Admin</button>
                    </form>
                </section>

                <section class="admin-panel">
                    <div class="panel-title">
                        <h2>Sub Admin Accounts</h2>
                        <span><?= count($agents) ?> account(s)</span>
                    </div>
                    <div class="submission-table-wrap">
                        <table class="submission-table">
                            <thead>
                                <tr>
                                    <th>Account</th>
                                    <th>Promocode</th>
                                    <th>Users</th>
                                    <th>Totals</th>
                                    <th>Update</th>
                                    <th>Password</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($agents as $agent): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($agent['display_name'] ?: $agent['username']) ?></strong>
                                            <span><?= e($agent['username']) ?> · <?= !empty($agent['active']) ? 'Active' : 'Disabled' ?></span>
                                            <span>Expires: <?= e($agent['expires_at'] ?: 'No expiry') ?></span>
                                        </td>
                                        <td><strong><?= e($agent['promo_code'] ?? '') ?></strong></td>
                                        <td><?= (int) ($agent['user_count'] ?? 0) ?></td>
                                        <td>
                                            <span>In: <?= number_format((float) ($agent['deposit_total'] ?? 0)) ?></span>
                                            <span>Out: <?= number_format((float) ($agent['withdraw_total'] ?? 0)) ?></span>
                                        </td>
                                        <td>
                                            <form method="post" class="inline-admin-actions agent-inline-form">
                                                <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                                                <input type="hidden" name="action" value="update_agent">
                                                <input type="hidden" name="agent_id" value="<?= (int) $agent['id'] ?>">
                                                <?php admin_redirect_input($activePage); ?>
                                                <input type="text" name="display_name" value="<?= e($agent['display_name'] ?? '') ?>" placeholder="Display name">
                                                <input type="text" name="promo_code" value="<?= e($agent['promo_code'] ?? '') ?>" placeholder="Promocode">
                                                <input type="datetime-local" name="expires_at" value="<?= e(admin_datetime_value((string) ($agent['expires_at'] ?? ''))) ?>">
                                                <label class="check-row">
                                                    <input type="checkbox" name="active" value="1" <?= !empty($agent['active']) ? 'checked' : '' ?>>
                                                    <span>Active</span>
                                                </label>
                                                <button class="ghost-btn" type="submit">Save</button>
                                            </form>
                                        </td>
                                        <td>
                                            <form method="post" class="inline-admin-actions">
                                                <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                                                <input type="hidden" name="action" value="change_agent_password">
                                                <input type="hidden" name="agent_id" value="<?= (int) $agent['id'] ?>">
                                                <?php admin_redirect_input($activePage); ?>
                                                <input type="password" name="new_password" minlength="8" placeholder="New password" required>
                                                <button class="ghost-btn" type="submit">Change</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$agents): ?>
                                    <tr><td colspan="6">No sub admin account yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (false && $activePage === 'units'): ?>
                <section class="admin-panel">
                    <div class="panel-title">
                        <h2>Unit Requests</h2>
                        <span>Super can review all deposit and withdraw requests.</span>
                    </div>
                    <div class="submission-table-wrap">
                        <table class="submission-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>User</th>
                                    <th>Agent</th>
                                    <th>Type</th>
                                    <th>Game</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($unitRequests as $request): ?>
                                    <?php $moneyDetails = fta_unit_request_money_details($request); ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($request['public_id']) ?></strong>
                                            <span><?= e($request['created_at']) ?></span>
                                        </td>
                                        <td>
                                            <span><?= e($request['full_name'] ?? '') ?></span>
                                            <span><?= e($request['phone_e164'] ?? '') ?></span>
                                        </td>
                                        <td><?= e($request['agent_username'] ?? '-') ?></td>
                                        <td>
                                            <strong><?= e(ucfirst((string) $request['request_type'])) ?></strong>
                                            <span><?= number_format((float) $request['amount']) ?></span>
                                            <?php if (!empty($request['proof_path'])): ?>
                                                <a href="<?= e(fta_image_src($request['proof_path'])) ?>" target="_blank" rel="noopener">Proof</a>
                                            <?php endif; ?>
                                            <?php if (!empty($moneyDetails['payment_account'])): ?>
                                                <span>Deposit to: <?= e(($moneyDetails['payment_account']['method'] ?? '') . ' · ' . ($moneyDetails['payment_account']['name'] ?? '') . ' · ' . ($moneyDetails['payment_account']['number'] ?? '')) ?></span>
                                            <?php endif; ?>
                                            <?php foreach ($moneyDetails['payout_accounts'] as $payout): ?>
                                                <span>Payout: <?= e(($payout['label'] ?? $payout['method'] ?? '') . ' · ' . ($payout['account_name'] ?? '') . ' · ' . ($payout['account_number'] ?? '')) ?></span>
                                            <?php endforeach; ?>
                                        </td>
                                        <td>
                                            <span><?= e($request['provider_label'] ?? '') ?></span>
                                            <span><?= e($request['external_username'] ?? '') ?></span>
                                            <?php $unitData = json_decode((string) ($request['request_data'] ?? ''), true); ?>
                                            <?php if (!empty($unitData['provider_sync']['transaction_id'])): ?>
                                                <span>Synced: <?= e((string) $unitData['provider_sync']['transaction_id']) ?></span>
                                            <?php elseif (!empty($unitData['provider_last_error']['message'])): ?>
                                                <span class="danger-text">API: <?= e((string) $unitData['provider_last_error']['message']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="post" class="inline-admin-actions">
                                                <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                                                <input type="hidden" name="action" value="update_unit_request">
                                                <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                                <?php admin_redirect_input($activePage); ?>
                                                <select name="status">
                                                    <?php foreach (['pending', 'approved', 'rejected'] as $status): ?>
                                                        <option value="<?= e($status) ?>" <?= ($request['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="text" name="admin_note" value="<?= e($request['admin_note'] ?? '') ?>" placeholder="Note">
                                                <button class="ghost-btn" type="submit">Save</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$unitRequests): ?>
                                    <tr><td colspan="6">No unit request yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($activePage === 'settings'): ?>
                <section class="admin-panel">
                    <div class="panel-title">
                        <h2>Dashboard Settings</h2>
                        <span>Prediction form, teams, prize, and Telegram popup.</span>
                    </div>
                    <form method="post" enctype="multipart/form-data" class="admin-form grid-form">
                        <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                        <input type="hidden" name="action" value="save_settings">
                        <?php admin_redirect_input($activePage); ?>

                        <label class="switch-row">
                            <input type="checkbox" name="form_open" value="1" <?= ($settings['form_open'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <span>Prediction form open</span>
                        </label>
                        <label>
                            <span>Start time</span>
                            <input type="datetime-local" name="form_start_at" value="<?= e(admin_datetime_value($settings['form_start_at'] ?? '')) ?>">
                        </label>
                        <label>
                            <span>End time</span>
                            <input type="datetime-local" name="form_end_at" value="<?= e(admin_datetime_value($settings['form_end_at'] ?? '')) ?>">
                        </label>
                        <label>
                            <span>Prize total</span>
                            <input type="text" name="prize_total" value="<?= e($settings['prize_total'] ?? '') ?>">
                        </label>
                        <label>
                            <span>Prize per correct winner</span>
                            <input type="text" name="prize_each" value="<?= e($settings['prize_each'] ?? '') ?>">
                        </label>
                        <label>
                            <span>Telegram popup title</span>
                            <input type="text" name="telegram_popup_title" value="<?= e($settings['telegram_popup_title'] ?? '') ?>">
                        </label>
                        <label class="wide-field">
                            <span>Telegram popup text</span>
                            <textarea name="telegram_popup_text" rows="3"><?= e($settings['telegram_popup_text'] ?? '') ?></textarea>
                        </label>
                        <label class="wide-field">
                            <span>Telegram bot token</span>
                            <input type="password" name="telegram_bot_token" placeholder="<?= trim((string) ($settings['telegram_bot_token'] ?? '')) !== '' ? 'Saved - enter a new token to replace' : '123456:ABC...' ?>">
                        </label>

                        <label class="switch-row">
                            <input type="checkbox" name="app_announcement_enabled" value="1" <?= ($settings['app_announcement_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <span>Show app announcement on Home</span>
                        </label>
                        <label>
                            <span>Announcement title</span>
                            <input type="text" name="app_announcement_title" value="<?= e($settings['app_announcement_title'] ?? 'FullTime Arena') ?>">
                        </label>
                        <label class="wide-field">
                            <span>Announcement text</span>
                            <textarea name="app_announcement_text" rows="3"><?= e($settings['app_announcement_text'] ?? '') ?></textarea>
                        </label>
                        <label>
                            <span>Live refresh seconds</span>
                            <input type="number" name="live_refresh_seconds" min="15" max="300" step="5" value="<?= e($settings['live_refresh_seconds'] ?? '60') ?>">
                        </label>
                        <label class="wide-field">
                            <span>Live player note</span>
                            <textarea name="live_player_note" rows="2"><?= e($settings['live_player_note'] ?? '') ?></textarea>
                        </label>
                        <label class="switch-row">
                            <input type="checkbox" name="score_detail_enabled" value="1" <?= ($settings['score_detail_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                            <span>Enable app live score match details</span>
                        </label>
                        <div class="wide-field guide-video-admin">
                            <span>App Guide Videos (YouTube)</span>
                            <?php $guideRows = $guideVideos ?: [['title' => '', 'url' => '']]; ?>
                            <?php foreach ($guideRows as $index => $video): ?>
                                <div class="guide-video-row">
                                    <label>
                                        <span>Title</span>
                                        <input type="text" name="app_guide_titles[]" value="<?= e($video['title'] ?? '') ?>" placeholder="How to deposit">
                                    </label>
                                    <label>
                                        <span>YouTube Link</span>
                                        <input type="url" name="app_guide_urls[]" value="<?= e($video['url'] ?? '') ?>" placeholder="https://www.youtube.com/watch?v=...">
                                    </label>
                                    <?php if (!empty($video['url'])): ?>
                                        <label class="check-row">
                                            <input type="checkbox" name="app_guide_delete[]" value="<?= (int) $index ?>">
                                            <span>Delete</span>
                                        </label>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <div class="guide-video-row is-new">
                                <label>
                                    <span>New Title</span>
                                    <input type="text" name="app_guide_titles[]" placeholder="How to withdraw">
                                </label>
                                <label>
                                    <span>New YouTube Link</span>
                                    <input type="url" name="app_guide_urls[]" placeholder="https://youtu.be/...">
                                </label>
                            </div>
                        </div>
                        <label class="wide-field">
                            <span>Update Application Download Link (app only)</span>
                            <input type="url" name="app_update_download_url" value="<?= e($settings['app_update_download_url'] ?? '') ?>" placeholder="https://example.com/fulltimearena.apk">
                        </label>

                        <div class="team-admin">
                            <label>
                                <span>Team A Name</span>
                                <input type="text" name="team_a_name" value="<?= e($settings['team_a_name'] ?? 'Team A') ?>">
                            </label>
                            <div class="upload-preview">
                                <img src="<?= e(fta_image_src($settings['team_a_logo'] ?? '')) ?>" alt="">
                                <label>
                                    <span>Team A Logo</span>
                                    <input type="file" name="team_a_logo" accept="image/*">
                                </label>
                                <label class="check-row">
                                    <input type="checkbox" name="clear_team_a_logo" value="1">
                                    <span>Clear logo</span>
                                </label>
                            </div>
                        </div>

                        <div class="team-admin">
                            <label>
                                <span>Team B Name</span>
                                <input type="text" name="team_b_name" value="<?= e($settings['team_b_name'] ?? 'Team B') ?>">
                            </label>
                            <div class="upload-preview">
                                <img src="<?= e(fta_image_src($settings['team_b_logo'] ?? '')) ?>" alt="">
                                <label>
                                    <span>Team B Logo</span>
                                    <input type="file" name="team_b_logo" accept="image/*">
                                </label>
                                <label class="check-row">
                                    <input type="checkbox" name="clear_team_b_logo" value="1">
                                    <span>Clear logo</span>
                                </label>
                            </div>
                        </div>

                        <button class="primary-btn wide-field" type="submit">Save Settings</button>
                    </form>
                </section>
            <?php endif; ?>

            <?php if ($activePage === 'ads'): ?>
                <section class="admin-panel">
                    <div class="panel-title">
                        <h2>Ads Slide Images</h2>
                        <span>Recommended size: 1500 x 500.</span>
                    </div>
                    <form method="post" enctype="multipart/form-data" class="admin-form inline-form">
                        <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                        <input type="hidden" name="action" value="add_ad">
                        <?php admin_redirect_input($activePage); ?>
                        <label>
                            <span>Image</span>
                            <input type="file" name="ad_image" accept="image/*" required>
                        </label>
                        <label>
                            <span>Forward link optional</span>
                            <input type="url" name="link_url" placeholder="https://example.com">
                        </label>
                        <label>
                            <span>Sort</span>
                            <input type="number" name="sort_order" value="0">
                        </label>
                        <button class="primary-btn" type="submit">Add Slide</button>
                    </form>
                </section>

                <section class="admin-panel">
                    <div class="panel-title">
                        <h2>Manage Slides</h2>
                        <span><?= count($ads) ?> slide(s)</span>
                    </div>
                    <div class="admin-list">
                        <?php foreach ($ads as $ad): ?>
                            <article class="ad-admin-row">
                                <img src="<?= e(fta_image_src($ad['image_path'])) ?>" alt="">
                                <div>
                                    <strong><?= e($ad['link_url'] ?: 'No link') ?></strong>
                                    <span>Sort <?= (int) $ad['sort_order'] ?> - <?= $ad['active'] ? 'Active' : 'Hidden' ?></span>
                                </div>
                                <form method="post" class="ad-edit-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                                    <input type="hidden" name="action" value="update_ad">
                                    <input type="hidden" name="id" value="<?= (int) $ad['id'] ?>">
                                    <?php admin_redirect_input($activePage); ?>
                                    <label>
                                        <span>Link</span>
                                        <input type="text" name="link_url" value="<?= e($ad['link_url']) ?>" placeholder="https:// or empty">
                                    </label>
                                    <label>
                                        <span>Sort</span>
                                        <input type="number" name="sort_order" value="<?= (int) $ad['sort_order'] ?>">
                                    </label>
                                    <label class="check-row">
                                        <input type="checkbox" name="active" value="1" <?= $ad['active'] ? 'checked' : '' ?>>
                                        <span>Active</span>
                                    </label>
                                    <button class="primary-btn" type="submit">Save</button>
                                </form>
                                <form method="post" onsubmit="return confirm('Delete this ad slide?')">
                                    <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_ad">
                                    <input type="hidden" name="id" value="<?= (int) $ad['id'] ?>">
                                    <?php admin_redirect_input($activePage); ?>
                                    <button class="danger-btn" type="submit">Delete</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$ads): ?>
                            <p class="muted">No ad slides yet.</p>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($activePage === 'live_matches'): ?>
                <section class="admin-panel">
                    <div class="panel-title">
                        <h2>Manual Live Match</h2>
                        <span>Used by web and app when the external live API is down. Stream lines format: Label | URL.</span>
                    </div>
                    <form method="post" class="admin-form grid-form">
                        <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                        <input type="hidden" name="action" value="add_live_match">
                        <?php admin_redirect_input($activePage); ?>
                        <label>
                            <span>League</span>
                            <input type="text" name="league_name" placeholder="Premier League">
                        </label>
                        <label>
                            <span>Kickoff / Time</span>
                            <input type="text" name="match_time" placeholder="20:30">
                        </label>
                        <label>
                            <span>Status</span>
                            <input type="text" name="status_text" placeholder="Live now">
                        </label>
                        <label>
                            <span>Type</span>
                            <select name="match_type">
                                <option value="live">Live</option>
                                <option value="upcoming">Upcoming</option>
                            </select>
                        </label>
                        <label>
                            <span>Home team</span>
                            <input type="text" name="home_name" required>
                        </label>
                        <label>
                            <span>Home logo URL</span>
                            <input type="text" name="home_logo" placeholder="https://...">
                        </label>
                        <label>
                            <span>Away team</span>
                            <input type="text" name="away_name" required>
                        </label>
                        <label>
                            <span>Away logo URL</span>
                            <input type="text" name="away_logo" placeholder="https://...">
                        </label>
                        <label class="wide-field">
                            <span>Streams</span>
                            <textarea name="streams" rows="4" placeholder="HD | https://example.com/live.m3u8&#10;Normal | https://example.com/live.mp4"></textarea>
                        </label>
                        <label>
                            <span>Sort</span>
                            <input type="number" name="sort_order" value="0">
                        </label>
                        <label class="switch-row">
                            <input type="checkbox" name="active" value="1" checked>
                            <span>Active</span>
                        </label>
                        <button class="primary-btn wide-field" type="submit">Add Live Match</button>
                    </form>
                </section>

                <section class="admin-panel">
                    <div class="panel-title">
                        <h2>Manage Live Matches</h2>
                        <span><?= count($liveMatches) ?> manual match(es)</span>
                    </div>
                    <div class="admin-list">
                        <?php foreach ($liveMatches as $match): ?>
                            <article class="live-admin-row">
                                <div class="live-admin-summary">
                                    <strong><?= e(($match['home_name'] ?? 'Home') . ' vs ' . ($match['away_name'] ?? 'Away')) ?></strong>
                                    <span><?= e($match['league_name'] ?: 'No league') ?> · <?= !empty($match['is_live']) ? 'Live' : 'Upcoming' ?> · <?= e($match['active'] ? 'Active' : 'Hidden') ?></span>
                                </div>
                                <form method="post" class="admin-form grid-form live-edit-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                                    <input type="hidden" name="action" value="update_live_match">
                                    <input type="hidden" name="id" value="<?= (int) $match['id'] ?>">
                                    <?php admin_redirect_input($activePage); ?>
                                    <label>
                                        <span>League</span>
                                        <input type="text" name="league_name" value="<?= e($match['league_name']) ?>">
                                    </label>
                                    <label>
                                        <span>Kickoff / Time</span>
                                        <input type="text" name="match_time" value="<?= e($match['match_time']) ?>">
                                    </label>
                                    <label>
                                        <span>Status</span>
                                        <input type="text" name="status_text" value="<?= e($match['status_text']) ?>">
                                    </label>
                                    <label>
                                        <span>Type</span>
                                        <select name="match_type">
                                            <option value="live" <?= !empty($match['is_live']) ? 'selected' : '' ?>>Live</option>
                                            <option value="upcoming" <?= empty($match['is_live']) ? 'selected' : '' ?>>Upcoming</option>
                                        </select>
                                    </label>
                                    <label>
                                        <span>Home team</span>
                                        <input type="text" name="home_name" value="<?= e($match['home_name']) ?>" required>
                                    </label>
                                    <label>
                                        <span>Home logo URL</span>
                                        <input type="text" name="home_logo" value="<?= e($match['home_logo']) ?>">
                                    </label>
                                    <label>
                                        <span>Away team</span>
                                        <input type="text" name="away_name" value="<?= e($match['away_name']) ?>" required>
                                    </label>
                                    <label>
                                        <span>Away logo URL</span>
                                        <input type="text" name="away_logo" value="<?= e($match['away_logo']) ?>">
                                    </label>
                                    <label class="wide-field">
                                        <span>Streams</span>
                                        <textarea name="streams" rows="4"><?= e(fta_live_streams_to_text($match['streams_json'])) ?></textarea>
                                    </label>
                                    <label>
                                        <span>Sort</span>
                                        <input type="number" name="sort_order" value="<?= (int) $match['sort_order'] ?>">
                                    </label>
                                    <label class="switch-row">
                                        <input type="checkbox" name="active" value="1" <?= $match['active'] ? 'checked' : '' ?>>
                                        <span>Active</span>
                                    </label>
                                    <button class="primary-btn wide-field" type="submit">Save Match</button>
                                </form>
                                <form method="post" onsubmit="return confirm('Delete this live match?')">
                                    <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_live_match">
                                    <input type="hidden" name="id" value="<?= (int) $match['id'] ?>">
                                    <?php admin_redirect_input($activePage); ?>
                                    <button class="danger-btn" type="submit">Delete</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$liveMatches): ?>
                            <p class="muted">No manual live matches yet. Add one when the external live source is unavailable.</p>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($activePage === 'notifications'): ?>
                <section class="admin-panel">
                    <div class="panel-title">
                        <h2>Send Inbox Notification</h2>
                        <span>Users see this in Mail/Inbox. If permission is granted, web/app can show a local notification.</span>
                    </div>
                    <form method="post" class="admin-form grid-form">
                        <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                        <input type="hidden" name="action" value="send_notification">
                        <?php admin_redirect_input($activePage); ?>
                        <label>
                            <span>Title</span>
                            <input type="text" name="title" maxlength="180" required placeholder="FullTime Arena">
                        </label>
                        <label class="wide-field">
                            <span>Text</span>
                            <textarea name="body" rows="4" required placeholder="Write notification text..."></textarea>
                        </label>
                        <button class="primary-btn wide-field" type="submit">Send Notification</button>
                    </form>
                </section>

                <section class="admin-panel">
                    <div class="panel-title">
                        <h2>Notification History</h2>
                        <span><?= count($notifications) ?> message(s)</span>
                    </div>
                    <div class="admin-list">
                        <?php foreach ($notifications as $notification): ?>
                            <article class="notification-admin-row">
                                <div>
                                    <strong><?= e($notification['title']) ?></strong>
                                    <span><?= e($notification['created_at']) ?> · <?= $notification['active'] ? 'Active' : 'Hidden' ?></span>
                                    <p><?= e($notification['body']) ?></p>
                                </div>
                                <form method="post" class="inline-admin-actions">
                                    <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                                    <input type="hidden" name="action" value="toggle_notification">
                                    <input type="hidden" name="id" value="<?= (int) $notification['id'] ?>">
                                    <?php admin_redirect_input($activePage); ?>
                                    <label class="check-row">
                                        <input type="checkbox" name="active" value="1" <?= $notification['active'] ? 'checked' : '' ?>>
                                        <span>Active</span>
                                    </label>
                                    <button class="primary-btn" type="submit">Save</button>
                                </form>
                                <form method="post" onsubmit="return confirm('Delete this notification?')">
                                    <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_notification">
                                    <input type="hidden" name="id" value="<?= (int) $notification['id'] ?>">
                                    <?php admin_redirect_input($activePage); ?>
                                    <button class="danger-btn" type="submit">Delete</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$notifications): ?>
                            <p class="muted">No notifications sent yet.</p>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($activePage === 'categories'): ?>
                <section class="admin-panel">
                    <div class="panel-title">
                        <h2>Categories</h2>
                        <span>Public page shows 4 items per row.</span>
                    </div>
                    <form method="post" enctype="multipart/form-data" class="category-editor">
                        <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                        <input type="hidden" name="action" value="update_categories">
                        <?php admin_redirect_input($activePage); ?>
                        <?php foreach ($categories as $category): ?>
                            <div class="category-edit-row">
                                <div class="category-icon-edit">
                                    <span class="admin-category-icon">
                                        <?php if (!empty($category['icon_path'])): ?>
                                            <img src="<?= e(fta_image_src($category['icon_path'])) ?>" alt="">
                                        <?php else: ?>
                                            <?= fta_icon(fta_category_icon_name($category['name'])) ?>
                                        <?php endif; ?>
                                    </span>
                                    <label>
                                        <span>Icon</span>
                                        <input type="file" name="category_icons[<?= (int) $category['id'] ?>]" accept="image/*">
                                    </label>
                                    <label class="check-row">
                                        <input type="checkbox" name="categories[<?= (int) $category['id'] ?>][clear_icon]" value="1">
                                        <span>Clear</span>
                                    </label>
                                </div>
                                <input type="text" name="categories[<?= (int) $category['id'] ?>][name]" value="<?= e($category['name']) ?>" aria-label="Category name">
                                <input type="text" name="categories[<?= (int) $category['id'] ?>][link_url]" value="<?= e($category['link_url']) ?>" aria-label="Category link" placeholder="prediction.php or https://">
                                <input type="number" name="categories[<?= (int) $category['id'] ?>][sort_order]" value="<?= (int) $category['sort_order'] ?>" aria-label="Sort">
                                <label class="check-row">
                                    <input type="checkbox" name="categories[<?= (int) $category['id'] ?>][active]" value="1" <?= $category['active'] ? 'checked' : '' ?>>
                                    <span>Active</span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                        <button class="primary-btn" type="submit">Save Categories</button>
                    </form>
                </section>

                <section class="admin-panel">
                    <div class="panel-title">
                        <h2>Add Category</h2>
                    </div>
                    <form method="post" enctype="multipart/form-data" class="admin-form inline-form category-add-form">
                        <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                        <input type="hidden" name="action" value="add_category">
                        <?php admin_redirect_input($activePage); ?>
                        <label>
                            <span>Name</span>
                            <input type="text" name="name" required>
                        </label>
                        <label>
                            <span>Link</span>
                            <input type="text" name="link_url" placeholder="prediction.php or https://">
                        </label>
                        <label>
                            <span>Sort</span>
                            <input type="number" name="sort_order" value="100">
                        </label>
                        <label>
                            <span>Icon</span>
                            <input type="file" name="category_icon" accept="image/*">
                        </label>
                        <button class="primary-btn" type="submit">Add Category</button>
                    </form>

                    <details class="delete-category-box">
                        <summary>Delete category</summary>
                        <?php foreach ($categories as $category): ?>
                            <form method="post" class="mini-delete" onsubmit="return confirm('Delete this category?')">
                                <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                                <?php admin_redirect_input($activePage); ?>
                                <span><?= e($category['name']) ?></span>
                                <button class="danger-btn" type="submit">Delete</button>
                            </form>
                        <?php endforeach; ?>
                    </details>
                </section>
            <?php endif; ?>

            <?php if ($activePage === 'submissions'): ?>
                <section class="admin-panel">
                    <div class="panel-title">
                        <h2>Prediction Submissions</h2>
                        <form method="post" class="clear-form" onsubmit="return confirm('This will permanently delete all prediction submissions. Continue?')">
                            <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                            <input type="hidden" name="action" value="clear_submissions">
                            <?php admin_redirect_input($activePage); ?>
                            <input type="text" name="clear_confirm" placeholder="Type CLEAR">
                            <button class="danger-btn" type="submit">Clear Data</button>
                        </form>
                    </div>

                    <div class="correct-filter" data-correct-filter>
                        <div class="correct-filter-title">
                            <div>
                                <span>Correct Answer Filter</span>
                                <strong>HT / FT / First scorer matching submissions</strong>
                            </div>
                            <em><strong data-filter-count><?= count($submissions) ?></strong> / <?= count($submissions) ?> shown</em>
                        </div>
                        <div class="correct-filter-grid">
                            <label>
                                <span>HT Result</span>
                                <input type="text" data-answer-filter="ht" placeholder="1-0" autocomplete="off">
                            </label>
                            <label>
                                <span>FT Result</span>
                                <input type="text" data-answer-filter="ft" placeholder="2-1" autocomplete="off">
                            </label>
                            <label>
                                <span>First scorer</span>
                                <input type="text" data-answer-filter="first" placeholder="Player name" autocomplete="off">
                            </label>
                            <button class="ghost-btn" type="button" data-filter-reset>Reset</button>
                        </div>
                    </div>

                    <form method="post" class="winner-save-form" onsubmit="return confirm('Update all prediction submissions to this status?')">
                        <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                        <input type="hidden" name="action" value="bulk_submission_status">
                        <?php admin_redirect_input($activePage); ?>
                        <div class="winner-save-bar">
                            <span>Bulk update all predictions</span>
                            <select name="bulk_status" class="status-select">
                                <option value="pending">Pending</option>
                                <option value="win">Win</option>
                                <option value="lose">Lose</option>
                            </select>
                            <button class="ghost-btn" type="submit">Apply All</button>
                        </div>
                    </form>

                    <form method="post" class="winner-save-form">
                        <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                        <input type="hidden" name="action" value="update_submission_winners">
                        <?php admin_redirect_input($activePage); ?>
                        <div class="winner-save-bar">
                            <span>Choose Pending, Win, or Lose. If super does nothing, users keep seeing Pending.</span>
                            <button class="primary-btn" type="submit">Save Results</button>
                        </div>
                    <div class="submission-table-wrap">
                        <table class="submission-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>User</th>
                                    <th>Answer</th>
                                    <th>Payment</th>
                                    <th>Result</th>
                                    <th>Device Info</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($submissions as $submission): ?>
                                    <tr data-submission-row
                                        data-ht-result="<?= e($submission['ht_result']) ?>"
                                        data-ft-result="<?= e($submission['ft_result']) ?>"
                                        data-first-scorer="<?= e($submission['first_scorer']) ?>">
                                        <td>
                                            <input type="hidden" name="submission_ids[]" value="<?= (int) $submission['id'] ?>">
                                            <strong><?= e($submission['public_id']) ?></strong>
                                            <span><?= e($submission['created_at']) ?></span>
                                        </td>
                                        <td>
                                            <span><?= e($submission['user_full_name'] ?? 'Guest') ?></span>
                                            <span><?= e($submission['user_phone_e164'] ?? '') ?></span>
                                        </td>
                                        <td>
                                            <span>HT: <?= e($submission['ht_result']) ?></span>
                                            <span>FT: <?= e($submission['ft_result']) ?></span>
                                            <span>First: <?= e($submission['first_scorer']) ?></span>
                                        </td>
                                        <td>
                                            <span><?= e($submission['wallet_type']) ?></span>
                                            <span><?= e($submission['wallet_name']) ?></span>
                                            <span><?= e($submission['wallet_number']) ?></span>
                                        </td>
                                        <td>
                                            <?php $resultStatus = in_array((string) ($submission['result_status'] ?? ''), ['pending', 'win', 'lose'], true) ? (string) $submission['result_status'] : (!empty($submission['is_winner']) ? 'win' : 'pending'); ?>
                                            <select name="result_status[<?= (int) $submission['id'] ?>]" class="status-select">
                                                <option value="pending" <?= $resultStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="win" <?= $resultStatus === 'win' ? 'selected' : '' ?>>Win</option>
                                                <option value="lose" <?= $resultStatus === 'lose' ? 'selected' : '' ?>>Lose</option>
                                            </select>
                                        </td>
                                        <td>
                                            <span><?= e($submission['os_version']) ?></span>
                                            <span><?= e($submission['screen_size']) ?></span>
                                            <span>ID: <?= e($submission['storage_id']) ?></span>
                                            <details>
                                                <summary>User agent and local data</summary>
                                                <pre><?= e($submission['user_agent']) . "\n\n" . e($submission['device_json']) ?></pre>
                                            </details>
                                        </td>
                                        <td><?= e($submission['ip_address']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr data-filter-empty hidden>
                                    <td colspan="7">No matching submissions for this correct answer.</td>
                                </tr>
                                <?php if (!$submissions): ?>
                                    <tr>
                                        <td colspan="7">No submissions yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    </form>
                </section>
            <?php endif; ?>

            <?php if ($activePage === 'security'): ?>
                <section class="admin-panel">
                    <div class="panel-title">
                        <h2>Admin Password</h2>
                        <span>Use at least 8 characters.</span>
                    </div>
                    <form method="post" class="admin-form inline-form security-form">
                        <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                        <input type="hidden" name="action" value="change_password">
                        <?php admin_redirect_input($activePage); ?>
                        <label>
                            <span>New password</span>
                            <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
                        </label>
                        <button class="primary-btn" type="submit">Change Password</button>
                    </form>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <script>
        (() => {
            const body = document.body;
            const toggle = document.querySelector('[data-admin-menu-toggle]');
            const close = document.querySelector('[data-admin-menu-close]');
            const links = document.querySelectorAll('.admin-nav a');
            const setOpen = (open) => body.classList.toggle('sidebar-open', open);
            if (toggle) toggle.addEventListener('click', () => setOpen(!body.classList.contains('sidebar-open')));
            if (close) close.addEventListener('click', () => setOpen(false));
            links.forEach((link) => link.addEventListener('click', () => setOpen(false)));
        })();

        (() => {
            const filter = document.querySelector('[data-correct-filter]');
            if (!filter) return;

            const rows = Array.from(document.querySelectorAll('[data-submission-row]'));
            const emptyRow = document.querySelector('[data-filter-empty]');
            const count = filter.querySelector('[data-filter-count]');
            const fields = {
                ht: filter.querySelector('[data-answer-filter="ht"]'),
                ft: filter.querySelector('[data-answer-filter="ft"]'),
                first: filter.querySelector('[data-answer-filter="first"]')
            };
            const reset = filter.querySelector('[data-filter-reset]');
            const normalize = (value) => String(value || '').trim().toLowerCase().replace(/\s+/g, ' ');

            const applyFilter = () => {
                const ht = normalize(fields.ht && fields.ht.value);
                const ft = normalize(fields.ft && fields.ft.value);
                const first = normalize(fields.first && fields.first.value);
                const hasFilter = Boolean(ht || ft || first);
                let visible = 0;

                rows.forEach((row) => {
                    const rowHt = normalize(row.dataset.htResult);
                    const rowFt = normalize(row.dataset.ftResult);
                    const rowFirst = normalize(row.dataset.firstScorer);
                    const matched = (!ht || rowHt === ht)
                        && (!ft || rowFt === ft)
                        && (!first || rowFirst === first || rowFirst.includes(first));

                    row.hidden = !matched;
                    row.classList.toggle('is-correct-match', hasFilter && matched);
                    if (matched) visible += 1;
                });

                if (count) count.textContent = String(visible);
                if (emptyRow) emptyRow.hidden = visible > 0 || rows.length === 0;
            };

            Object.values(fields).forEach((field) => {
                if (field) field.addEventListener('input', applyFilter);
            });

            if (reset) {
                reset.addEventListener('click', () => {
                    Object.values(fields).forEach((field) => {
                        if (field) field.value = '';
                    });
                    applyFilter();
                });
            }

            applyFilter();
        })();
    </script>
</body>
</html>
