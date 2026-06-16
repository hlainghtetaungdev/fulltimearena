<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';

try {
    fta_settings();
} catch (Throwable $error) {
    fta_render_db_error($error);
    exit;
}

fta_render_head(t('mail', 'Mail'), 'public-page inbox-page');
fta_render_public_header('inbox');
?>
<main class="app-shell narrow">
    <section class="inbox-shell" data-inbox-page>
        <div class="result-topbar">
            <div>
                <span><?= e(t('notifications', 'Notifications')) ?></span>
                <h1><?= fta_icon('mail') ?><?= e(t('mail', 'Mail')) ?></h1>
            </div>
            <div class="inbox-topbar-actions">
                <button class="icon-btn result-refresh" type="button" data-inbox-refresh aria-label="<?= e(t('refresh_inbox', 'Refresh inbox')) ?>">
                    <?= fta_icon('refresh') ?>
                </button>
                <button class="icon-btn result-refresh" type="button" data-inbox-mark-all hidden aria-label="<?= e(t('mark_all_read', 'Mark all read')) ?>">
                    <?= fta_icon('trash') ?>
                </button>
                <button class="icon-btn result-refresh" type="button" data-inbox-clear hidden aria-label="<?= e(t('clear_messages', 'Clear messages')) ?>">
                    <?= fta_icon('x') ?>
                </button>
            </div>
        </div>

        <div class="inbox-summary" data-inbox-summary hidden></div>

        <div class="notice success" data-notification-permission>
            <strong><?= e(t('notification_permission_title', 'Allow notifications')) ?></strong>
            <span><?= e(t('notification_permission_text', 'Enable notifications to see new inbox messages on this device.')) ?></span>
            <button class="primary-btn" type="button" data-request-notification><?= e(t('allow_notifications', 'Allow notifications')) ?></button>
        </div>

        <div class="news-state" data-inbox-loading>
            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            <span><?= e(t('loading_inbox', 'Loading inbox...')) ?></span>
        </div>

        <div class="notice error" data-inbox-error hidden>
            <strong><?= e(t('inbox_error_title', 'Inbox unavailable')) ?></strong>
            <span><?= e(t('inbox_error_message', 'Could not load notifications right now. Please try again.')) ?></span>
        </div>

        <div class="inbox-list" data-inbox-list></div>
    </section>
</main>

<div class="inbox-message-modal" data-inbox-modal hidden>
    <div class="inbox-message-card" role="dialog" aria-modal="true" aria-labelledby="inbox-message-title">
        <button class="modal-close" type="button" data-inbox-modal-close aria-label="<?= e(t('close_message', 'Close')) ?>"><?= fta_icon('x') ?></button>
        <span class="tile-icon"><?= fta_icon('mail') ?></span>
        <h2 id="inbox-message-title" data-inbox-modal-title><?= e(t('mail', 'Mail')) ?></h2>
        <span data-inbox-modal-date></span>
        <div class="credential-copy-list" data-inbox-credentials hidden></div>
        <p data-inbox-modal-body></p>
    </div>
</div>
<?php
fta_render_footer_nav('inbox');
fta_render_telegram_modal();
?>
<script>
    window.FTA_INBOX_TEXT = {
        empty: <?= json_encode(t('no_notifications', 'No notifications yet.')) ?>,
        error: <?= json_encode(t('inbox_error_message', 'Could not load notifications right now. Please try again.')) ?>,
        permissionGranted: <?= json_encode(t('notification_permission_granted', 'Notifications are enabled.')) ?>,
        permissionDenied: <?= json_encode(t('notification_permission_denied', 'Notifications are disabled for this browser.')) ?>,
        readMessage: <?= json_encode(t('read_message', 'Read full message')) ?>,
        unread: <?= json_encode(t('unread', 'Unread')) ?>,
        markAllRead: <?= json_encode(t('mark_all_read', 'Mark all read')) ?>,
        allRead: <?= json_encode(t('all_read', 'All caught up.')) ?>,
        unreadMessages: <?= json_encode(t('unread_messages', 'Unread messages')) ?>,
        copy: <?= json_encode(t('copy', 'Copy')) ?>,
        copied: <?= json_encode(t('copied', 'Copied')) ?>,
        clearMessages: <?= json_encode(t('clear_messages', 'Clear messages')) ?>,
        clearConfirm: <?= json_encode(t('clear_messages_confirm', 'Clear all visible mail messages from Inbox?')) ?>
    };
</script>
<script src="<?= e(fta_base_url('assets/js/inbox.js')) ?>?v=<?= e(fta_asset_version('assets/js/inbox.js')) ?>"></script>
<?php fta_render_page_end(); ?>
