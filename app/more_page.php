<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';

try {
    fta_settings();
} catch (Throwable $error) {
    fta_render_db_error($error);
    exit;
}

$lang = fta_current_lang();
$page = 'more.php';
$user = fta_current_user();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        fta_check_csrf();
        if ((string) ($_POST['action'] ?? '') === 'change_password') {
            if (!$user) {
                throw new RuntimeException(t('auth_session_expired', 'Please login again.'));
            }
            fta_change_user_password($user, $_POST);
            fta_flash('success', t('password_changed', 'Password changed successfully.'));
        }
        if ((string) ($_POST['action'] ?? '') === 'update_profile') {
            if (!$user) {
                throw new RuntimeException(t('auth_session_expired', 'Please login again.'));
            }
            $profileImage = fta_upload_image('profile_image', 'profiles');
            fta_update_user_profile($user, $_POST, $profileImage);
            fta_flash('success', t('profile_updated', 'Profile updated.'));
        }
        if ((string) ($_POST['action'] ?? '') === 'remove_profile_image') {
            if (!$user) {
                throw new RuntimeException(t('auth_session_expired', 'Please login again.'));
            }
            fta_remove_user_profile_image($user);
            fta_flash('success', t('profile_photo_removed', 'Profile photo removed.'));
        }
        if ((string) ($_POST['action'] ?? '') === 'save_payout') {
            if (!$user) {
                throw new RuntimeException(t('auth_session_expired', 'Please login again.'));
            }
            fta_save_user_payout_accounts((int) $user['id'], $_POST);
            fta_flash('success', t('payout_saved', 'Payout account saved.'));
        }
        if ((string) ($_POST['action'] ?? '') === 'clear_payout') {
            if (!$user) {
                throw new RuntimeException(t('auth_session_expired', 'Please login again.'));
            }
            fta_clear_user_payout_accounts((int) $user['id']);
            fta_flash('success', t('payout_removed', 'Payout account removed.'));
        }
        if ((string) ($_POST['action'] ?? '') === 'create_game_account') {
            if (!$user) {
                throw new RuntimeException(t('auth_session_expired', 'Please login again.'));
            }
            fta_create_user_game_account($user, (string) ($_POST['provider_key'] ?? ''));
            fta_flash('success', t('game_account_created', 'Game account is ready.'));
        }
        if ((string) ($_POST['action'] ?? '') === 'connect_game_account') {
            if (!$user) {
                throw new RuntimeException(t('auth_session_expired', 'Please login again.'));
            }
            fta_connect_user_game_account($user, (string) ($_POST['provider_key'] ?? ''), (string) ($_POST['external_username'] ?? ''));
            fta_flash('success', t('game_account_connected', 'Game account connected.'));
        }
        if ((string) ($_POST['action'] ?? '') === 'revoke_session') {
            if (!$user) {
                throw new RuntimeException(t('auth_session_expired', 'Please login again.'));
            }
            fta_start_session();
            $currentToken = (string) ($_SESSION['user_session_token'] ?? $_COOKIE[fta_remember_cookie_name()] ?? '');
            fta_revoke_user_session($user, (int) ($_POST['session_id'] ?? 0), $currentToken);
            fta_flash('success', t('session_signed_out', 'Device signed out.'));
        }
    } catch (Throwable $error) {
        fta_flash('error', fta_user_safe_error_message($error));
    }
    fta_redirect(fta_lang_url($lang, $page));
}

$languages = [
    ['code' => 'en', 'label' => 'English', 'flag' => 'flag-us'],
    ['code' => 'my', 'label' => 'မြန်မာ', 'flag' => 'flag-mm'],
    ['code' => 'jp', 'label' => '日本語', 'flag' => 'flag-jp'],
    ['code' => 'th', 'label' => 'ไทย', 'flag' => 'flag-th'],
];
$activeLanguage = $languages[0];
foreach ($languages as $language) {
    if ($language['code'] === $lang) {
        $activeLanguage = $language;
        break;
    }
}
$predictionHistory = fta_prediction_history($user, null, 50);
$payoutAccounts = $user ? fta_user_payout_accounts($user) : [];
$paymentHistory = $user ? fta_unit_requests_for_user((int) $user['id'], 50) : [];
$gameAccounts = $user ? fta_user_game_accounts($user) : [];
$providers = $user ? fta_available_provider_configs_for_user($user) : [];
fta_start_session();
$currentSessionToken = (string) ($_SESSION['user_session_token'] ?? $_COOKIE[fta_remember_cookie_name()] ?? '');
$userSessions = $user ? fta_user_sessions($user, $currentSessionToken) : [];
$profileImage = $user ? fta_user_profile_image_url($user) : '';
$appGuideVideos = fta_app_guide_videos_from_settings(fta_settings());

fta_render_head(t('more'), 'public-page more-page');
fta_render_public_header('more');
?>
<main class="app-shell narrow">
    <section class="more-hero more-profile">
        <img src="<?= e($profileImage !== '' ? $profileImage : fta_logo_url()) ?>" alt="" class="more-logo">
        <div>
            <span><?= e(t('profile', 'Profile')) ?></span>
            <h1><?= e($user['full_name'] ?? t('account', 'Account')) ?></h1>
            <p><?= e($user['phone_e164'] ?? $user['phone_number'] ?? '') ?></p>
        </div>
        <details class="profile-edit-details">
            <summary class="ghost-btn"><?= fta_icon('person') ?><span><?= e(t('edit_profile', 'Edit profile')) ?></span></summary>
            <form method="post" enctype="multipart/form-data" class="profile-edit-form">
                <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                <input type="hidden" name="action" value="update_profile">
                <label>
                    <span><?= e(t('full_name', 'Full Name')) ?></span>
                    <input type="text" name="full_name" value="<?= e($user['full_name'] ?? '') ?>" maxlength="140" required>
                </label>
                <label>
                    <span><?= e(t('profile_photo', 'Profile photo')) ?></span>
                    <input type="file" name="profile_image" accept="image/*">
                </label>
                <button class="settings-action more-action-primary" type="submit"><?= fta_icon('check') ?><span><?= e(t('save_profile', 'Save profile')) ?></span></button>
            </form>
            <?php if ($profileImage !== ''): ?>
                <form method="post" class="profile-edit-form compact" onsubmit="return confirm('Remove profile photo?')">
                    <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                    <input type="hidden" name="action" value="remove_profile_image">
                    <button class="ghost-btn more-action-secondary" type="submit"><?= fta_icon('trash') ?><span><?= e(t('remove_photo', 'Remove photo')) ?></span></button>
                </form>
            <?php endif; ?>
        </details>
    </section>

    <?php foreach (fta_take_flashes() as $flash): ?>
        <div class="notice <?= e($flash['type'] ?? 'success') ?>">
            <span><?= e($flash['message'] ?? '') ?></span>
        </div>
    <?php endforeach; ?>

    <details class="more-panel more-accordion">
        <summary class="more-accordion-summary">
            <?= fta_icon('globe') ?>
            <span><?= e(t('choice_language', 'Choice Language')) ?></span>
            <small><span class="flag-icon <?= e($activeLanguage['flag']) ?>" aria-hidden="true"></span><?= e($activeLanguage['label']) ?></small>
            <?= fta_icon('chevron') ?>
        </summary>
        <div class="more-accordion-content">
            <div class="language-picker more-language-picker" aria-label="<?= e(t('choice_language', 'Choice Language')) ?>">
                <div class="more-current-language">
                <span class="flag-icon <?= e($activeLanguage['flag']) ?>" aria-hidden="true"></span>
                <span><?= e($activeLanguage['label']) ?></span>
                </div>
            <div class="language-picker-menu">
            <?php foreach ($languages as $language): ?>
                <a class="<?= $lang === $language['code'] ? 'active' : '' ?>" href="<?= e(fta_lang_url($language['code'], $page)) ?>">
                    <span class="flag-icon <?= e($language['flag']) ?>" aria-hidden="true"></span>
                    <span><?= e($language['label']) ?></span>
                </a>
            <?php endforeach; ?>
            </div>
            </div>
        </div>
    </details>

    <details class="more-panel more-accordion">
        <summary class="more-accordion-summary">
            <?= fta_icon('link') ?>
            <span><?= e(t('follow_us_on', 'Follow us')) ?></span>
            <small><?= e(t('social_media', 'Social media')) ?></small>
            <?= fta_icon('chevron') ?>
        </summary>
        <div class="more-accordion-content">
        <div class="social-grid" aria-label="<?= e(t('social_media', 'Social media')) ?>">
            <a class="social-card facebook" href="<?= e(FTA_FACEBOOK_URL) ?>" target="_blank" rel="noopener">
                <?= fta_icon('facebook') ?>
                <span>
                    <strong><?= e(t('facebook')) ?></strong>
                    <small>facebook.com/fulltimearena</small>
                </span>
            </a>
            <a class="social-card telegram" href="<?= e(FTA_TELEGRAM_URL) ?>" target="_blank" rel="noopener">
                <?= fta_icon('telegram') ?>
                <span>
                    <strong><?= e(t('telegram')) ?></strong>
                    <small>t.me/fulltimearena</small>
                </span>
            </a>
            <a class="social-card tiktok" href="<?= e(FTA_TIKTOK_URL) ?>" target="_blank" rel="noopener">
                <?= fta_icon('tiktok') ?>
                <span>
                    <strong><?= e(t('tiktok')) ?></strong>
                    <small>tiktok.com/@fulltimearena</small>
                </span>
            </a>
        </div>
        </div>
    </details>

    <details class="more-panel more-accordion">
        <summary class="more-accordion-summary">
            <?= fta_icon('play') ?>
            <span><?= e(t('app_guide', 'App Guide')) ?></span>
            <small><?= e(t('app_guide_subtitle', 'How to use the app')) ?></small>
            <?= fta_icon('chevron') ?>
        </summary>
        <div class="more-accordion-content">
            <?php foreach ($appGuideVideos as $video): ?>
                <article class="guide-video-card">
                    <strong><?= e($video['title'] ?? t('app_guide', 'App Guide')) ?></strong>
                    <?php if (!empty($video['embed_url'])): ?>
                        <div class="guide-video-frame">
                            <iframe src="<?= e($video['embed_url']) ?>" title="<?= e($video['title'] ?? t('app_guide', 'App Guide')) ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                        </div>
                    <?php else: ?>
                        <a class="settings-action more-action-primary" href="<?= e($video['url'] ?? '') ?>" target="_blank" rel="noopener">
                            <?= fta_icon('play') ?><span><?= e(t('watch_guide_video', 'Watch guide video')) ?></span>
                        </a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
            <?php if (!$appGuideVideos): ?>
                <div class="empty-action-card">
                    <p><?= e(t('app_guide_empty', 'Guide video is not available yet.')) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </details>

    <details id="create-account-section" class="more-panel more-accordion">
        <summary class="more-accordion-summary">
            <?= fta_icon('ticket') ?>
            <span><?= e(t('create_account', 'Create Account')) ?></span>
            <small><?= count($gameAccounts) ?> / <?= count($providers) ?></small>
            <?= fta_icon('chevron') ?>
        </summary>
        <div class="more-accordion-content">
            <div class="game-account-grid">
                <?php foreach ($providers as $provider): ?>
                    <?php
                    $exists = array_values(array_filter($gameAccounts, static fn (array $account): bool => (string) $account['provider_key'] === (string) $provider['provider_key']));
                    ?>
                    <article class="game-account-card">
                        <strong><?= e($provider['provider_label']) ?></strong>
                        <?php if ($exists): ?>
                            <span><?= e($exists[0]['external_username'] ?? '') ?></span>
                        <?php else: ?>
                            <div class="account-connect-actions">
                                <?php if (!isset($provider['supports_auto_create']) || $provider['supports_auto_create']): ?>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                                        <input type="hidden" name="action" value="create_game_account">
                                        <input type="hidden" name="provider_key" value="<?= e($provider['provider_key']) ?>">
                                        <button class="ghost-btn" type="submit"><?= e(t('get_acc', 'Get Acc')) ?></button>
                                    </form>
                                <?php endif; ?>
                                <details class="connect-account-details">
                                    <summary class="ghost-btn"><?= e(t('connect_acc', 'Connect Acc')) ?></summary>
                                    <form method="post" class="connect-account-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                                        <input type="hidden" name="action" value="connect_game_account">
                                        <input type="hidden" name="provider_key" value="<?= e($provider['provider_key']) ?>">
                                        <small><?= e(t('connect_account_rule', 'Username must start with your promocode owner agent username.')) ?></small>
                                        <input type="text" name="external_username" required placeholder="uuzmsyaaf001">
                                        <button class="primary-btn" type="submit"><?= e(t('save', 'Save')) ?></button>
                                    </form>
                                </details>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
                <?php if (!$providers): ?>
                    <div class="empty-action-card">
                        <p><?= e(t('no_game_provider', 'No game provider is available from your agent yet.')) ?></p>
                        <a class="ghost-btn" href="<?= e(fta_lang_url($lang)) ?>"><?= e(t('back_home', 'Back home')) ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </details>

    <details class="more-panel more-accordion">
        <summary class="more-accordion-summary">
            <?= fta_icon('target') ?>
            <span><?= e(t('prediction_history', 'Prediction History')) ?></span>
            <small><?= count($predictionHistory) ?></small>
            <?= fta_icon('chevron') ?>
        </summary>
        <div class="more-accordion-content prediction-history-list">
            <?php foreach ($predictionHistory as $history): ?>
                <?php $resultStatus = (string) ($history['result_status'] ?? (!empty($history['is_winner']) ? 'win' : 'pending')); ?>
                <article class="prediction-history-card <?= $resultStatus === 'win' ? 'is-win' : ($resultStatus === 'lose' ? 'is-lose' : 'is-pending') ?>">
                    <div>
                        <strong><?= e($history['public_id']) ?></strong>
                        <span><?= e($history['created_at']) ?></span>
                    </div>
                    <em><?= e(t($resultStatus, ucfirst($resultStatus))) ?></em>
                    <p>HT <?= e($history['ht_result']) ?> / FT <?= e($history['ft_result']) ?> / <?= e($history['first_scorer']) ?></p>
                </article>
            <?php endforeach; ?>
            <?php if (!$predictionHistory): ?>
                <div class="empty-action-card">
                    <p><?= e(t('no_prediction_history', 'No prediction history yet.')) ?></p>
                    <a class="ghost-btn" href="<?= e(fta_lang_url($lang, 'prediction.php')) ?>"><?= e(t('play_now', 'Submit Prediction')) ?></a>
                </div>
            <?php endif; ?>
        </div>
    </details>

    <details id="payout-account-section" class="more-panel more-accordion">
        <summary class="more-accordion-summary">
            <?= fta_icon('wallet') ?>
            <span><?= e(t('payout_account', 'Payout Account')) ?></span>
            <small><?= e(t('payout_bank_options', 'KBZ / Wave / Aya / Yucho / KBank')) ?></small>
            <?= fta_icon('chevron') ?>
        </summary>
        <div class="more-accordion-content">
            <p class="warning-copy"><?= e(t('payout_warning', 'Please make sure your account name and phone number are correct. If money is sent to a wrong number you connected, it is your responsibility.')) ?></p>
            <form method="post" class="payout-form">
                <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                <input type="hidden" name="action" value="save_payout">
                <?php foreach ($payoutAccounts as $method => $account): ?>
                    <div class="payout-channel-card payment-brand-<?= e($method) ?>">
                        <?php $logo = fta_payout_channel_logo((string) $method); ?>
                        <div class="payment-brand-mark logo-mark">
                            <?php if ($logo !== ''): ?><img src="<?= e($logo) ?>" alt="<?= e($account['label']) ?>"><?php else: ?><?= e(fta_payout_channels()[$method]['brand'] ?? strtoupper($method)) ?><?php endif; ?>
                        </div>
                        <strong><?= e($account['label']) ?></strong>
                        <label>
                            <span><?= e(t('wallet_name', 'Wallet name')) ?></span>
                            <input type="text" name="<?= e($method) ?>_name" value="<?= e($account['account_name']) ?>">
                        </label>
                        <label>
                            <span><?= e(t('wallet_number', 'Wallet number')) ?></span>
                            <input type="tel" name="<?= e($method) ?>_number" value="<?= e($account['account_number']) ?>">
                        </label>
                    </div>
                <?php endforeach; ?>
                <button class="settings-action more-action-primary" type="submit">
                    <?= fta_icon('check') ?><span><?= e(t('save', 'Save')) ?></span>
                </button>
            </form>
            <form method="post" onsubmit="return confirm('Remove payout accounts?')">
                <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                <input type="hidden" name="action" value="clear_payout">
                <button class="ghost-btn more-action-secondary" type="submit">
                    <?= fta_icon('trash') ?><span><?= e(t('remove', 'Remove')) ?></span>
                </button>
            </form>
        </div>
    </details>

    <details class="more-panel more-accordion">
        <summary class="more-accordion-summary">
            <?= fta_icon('activity') ?>
            <span><?= e(t('payment_history', 'Payment History')) ?></span>
            <small><?= count($paymentHistory) ?></small>
            <?= fta_icon('chevron') ?>
        </summary>
        <div class="more-accordion-content payment-history-list">
            <?php foreach (array_slice($paymentHistory, 0, 5) as $request): ?>
                <?php $moneyDetails = fta_unit_request_money_details($request); ?>
                <article class="payment-history-card status-<?= e($request['status']) ?>">
                    <div>
                        <strong><?= e($request['public_id']) ?></strong>
                        <span><?= e($request['created_at']) ?></span>
                    </div>
                    <em><?= e(t((string) $request['status'], ucfirst((string) $request['status']))) ?></em>
                    <p><?= e(ucfirst((string) $request['request_type'])) ?> / <?= number_format((float) $request['amount']) ?> / <?= e($request['provider_label'] ?? '') ?> <?= e($request['external_username'] ?? '') ?></p>
                    <?php if (!empty($request['admin_note'])): ?>
                        <small><?= e($request['admin_note']) ?></small>
                    <?php endif; ?>
                    <button class="ghost-btn payment-detail-open" type="button" data-payment-detail-open="payment-detail-<?= (int) $request['id'] ?>"><?= e(t('request_detail', 'Request detail')) ?></button>
                    <div id="payment-detail-<?= (int) $request['id'] ?>" class="payment-detail-template" hidden>
                        <h2><?= e(t('request_detail', 'Request detail')) ?></h2>
                        <p><strong><?= e($request['public_id']) ?></strong></p>
                        <p><?= e(t('status', 'Status')) ?>: <?= e(t((string) $request['status'], ucfirst((string) $request['status']))) ?></p>
                        <p><?= e(t('amount', 'Amount')) ?>: <?= number_format((float) $request['amount']) ?></p>
                        <p><?= e(t('game_account', 'Game account')) ?>: <?= e(($request['provider_label'] ?? '') . ' / ' . ($request['external_username'] ?? '')) ?></p>
                        <?php if (!empty($request['proof_path'])): ?><a href="<?= e(fta_image_src($request['proof_path'])) ?>" target="_blank" rel="noopener"><?= e(t('view_proof', 'View proof')) ?></a><?php endif; ?>
                        <?php if (!empty($moneyDetails['payment_account'])): ?>
                            <p><?= e(t('payment_method', 'Payment method')) ?>: <?= e(($moneyDetails['payment_account']['method'] ?? '') . ' / ' . ($moneyDetails['payment_account']['name'] ?? '') . ' / ' . ($moneyDetails['payment_account']['number'] ?? '')) ?></p>
                        <?php endif; ?>
                        <?php foreach ($moneyDetails['payout_accounts'] as $payout): ?>
                            <p><?= e(t('payout_account', 'Payout Account')) ?>: <?= e(($payout['label'] ?? $payout['method'] ?? '') . ' / ' . ($payout['account_name'] ?? '') . ' / ' . ($payout['account_number'] ?? '')) ?></p>
                        <?php endforeach; ?>
                        <?php if (!empty($request['admin_note'])): ?><p><?= e(t('admin_note', 'Admin note')) ?>: <?= e($request['admin_note']) ?></p><?php endif; ?>
                    </div>
                    <details class="payment-request-detail">
                        <summary><?= e(t('detail', 'Detail')) ?></summary>
                        <?php if (!empty($request['proof_path'])): ?><a href="<?= e(fta_image_src($request['proof_path'])) ?>" target="_blank" rel="noopener"><?= e(t('view_proof', 'View proof')) ?></a><?php endif; ?>
                        <?php if (!empty($moneyDetails['payment_account'])): ?>
                            <span><?= e(t('payment_method', 'Payment method')) ?>: <?= e(($moneyDetails['payment_account']['method'] ?? '') . ' / ' . ($moneyDetails['payment_account']['name'] ?? '') . ' / ' . ($moneyDetails['payment_account']['number'] ?? '')) ?></span>
                        <?php endif; ?>
                        <?php foreach ($moneyDetails['payout_accounts'] as $payout): ?>
                            <span><?= e(t('payout_account', 'Payout Account')) ?>: <?= e(($payout['label'] ?? $payout['method'] ?? '') . ' / ' . ($payout['account_name'] ?? '') . ' / ' . ($payout['account_number'] ?? '')) ?></span>
                        <?php endforeach; ?>
                    </details>
                </article>
            <?php endforeach; ?>
            <?php if (count($paymentHistory) > 5): ?>
                <details class="history-see-more">
                    <summary class="ghost-btn"><?= fta_icon('chevron') ?><span><?= e(t('see_more', 'See more')) ?></span><small><?= count($paymentHistory) - 5 ?></small></summary>
                    <div class="history-see-more-list">
                        <?php foreach (array_slice($paymentHistory, 5) as $request): ?>
                            <?php $moneyDetails = fta_unit_request_money_details($request); ?>
                            <article class="payment-history-card status-<?= e($request['status']) ?>">
                                <div>
                                    <strong><?= e($request['public_id']) ?></strong>
                                    <span><?= e($request['created_at']) ?></span>
                                </div>
                                <em><?= e(t((string) $request['status'], ucfirst((string) $request['status']))) ?></em>
                                <p><?= e(ucfirst((string) $request['request_type'])) ?> / <?= number_format((float) $request['amount']) ?> / <?= e($request['provider_label'] ?? '') ?> <?= e($request['external_username'] ?? '') ?></p>
                                <?php if (!empty($request['admin_note'])): ?>
                                    <small><?= e($request['admin_note']) ?></small>
                                <?php endif; ?>
                                <button class="ghost-btn payment-detail-open" type="button" data-payment-detail-open="payment-detail-more-<?= (int) $request['id'] ?>"><?= e(t('request_detail', 'Request detail')) ?></button>
                                <div id="payment-detail-more-<?= (int) $request['id'] ?>" class="payment-detail-template" hidden>
                                    <h2><?= e(t('request_detail', 'Request detail')) ?></h2>
                                    <p><strong><?= e($request['public_id']) ?></strong></p>
                                    <p><?= e(t('status', 'Status')) ?>: <?= e(t((string) $request['status'], ucfirst((string) $request['status']))) ?></p>
                                    <p><?= e(t('amount', 'Amount')) ?>: <?= number_format((float) $request['amount']) ?></p>
                                    <p><?= e(t('game_account', 'Game account')) ?>: <?= e(($request['provider_label'] ?? '') . ' / ' . ($request['external_username'] ?? '')) ?></p>
                                    <?php if (!empty($request['proof_path'])): ?><a href="<?= e(fta_image_src($request['proof_path'])) ?>" target="_blank" rel="noopener"><?= e(t('view_proof', 'View proof')) ?></a><?php endif; ?>
                                    <?php if (!empty($moneyDetails['payment_account'])): ?>
                                        <p><?= e(t('payment_method', 'Payment method')) ?>: <?= e(($moneyDetails['payment_account']['method'] ?? '') . ' / ' . ($moneyDetails['payment_account']['name'] ?? '') . ' / ' . ($moneyDetails['payment_account']['number'] ?? '')) ?></p>
                                    <?php endif; ?>
                                    <?php foreach ($moneyDetails['payout_accounts'] as $payout): ?>
                                        <p><?= e(t('payout_account', 'Payout Account')) ?>: <?= e(($payout['label'] ?? $payout['method'] ?? '') . ' / ' . ($payout['account_name'] ?? '') . ' / ' . ($payout['account_number'] ?? '')) ?></p>
                                    <?php endforeach; ?>
                                    <?php if (!empty($request['admin_note'])): ?><p><?= e(t('admin_note', 'Admin note')) ?>: <?= e($request['admin_note']) ?></p><?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
            <?php if (!$paymentHistory): ?>
                <div class="empty-action-card">
                    <p><?= e(t('no_payment_history', 'No payment history yet.')) ?></p>
                    <a class="ghost-btn" href="<?= e(fta_lang_url($lang, 'unit.php')) ?>"><?= e(t('unit_in_out', 'Unit In / Out')) ?></a>
                </div>
            <?php endif; ?>
        </div>
    </details>

    <section class="more-panel more-accordion">
        <div class="more-accordion-summary static-summary">
            <?= fta_icon('shield') ?>
            <span><?= e(t('login_devices', 'Login devices')) ?></span>
            <small><?= count($userSessions) ?></small>
        </div>
        <div class="session-device-list">
            <?php foreach ($userSessions as $session): ?>
                <article class="session-device-card <?= !empty($session['current']) ? 'current' : '' ?>">
                    <button class="session-device-main" type="button" data-payment-detail-open="session-detail-<?= (int) $session['id'] ?>">
                        <strong><?= e($session['device'] ?: t('unknown_device', 'Unknown device')) ?></strong>
                        <span><?= e($session['ip_address'] ?: '-') ?> / <?= e($session['type']) ?></span>
                    </button>
                    <small><?= e(t('last_used', 'Last used')) ?>: <?= e($session['last_used_at'] ?: $session['created_at']) ?></small>
                    <?php if (!empty($session['current'])): ?><em><?= e(t('current_device', 'Current device')) ?></em><?php endif; ?>
                    <?php if (empty($session['current'])): ?>
                        <form method="post" class="session-signout-form" onsubmit="return confirm('<?= e(t('sign_out_device_confirm', 'Sign out this device?')) ?>')">
                            <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                            <input type="hidden" name="action" value="revoke_session">
                            <input type="hidden" name="session_id" value="<?= (int) $session['id'] ?>">
                            <button class="ghost-btn danger mini-session-btn" type="submit"><?= fta_icon('x') ?><span><?= e(t('sign_out_device', 'Sign out')) ?></span></button>
                        </form>
                    <?php endif; ?>
                    <template id="session-detail-<?= (int) $session['id'] ?>">
                        <div class="payment-detail-summary">
                            <h2><?= e($session['device'] ?: t('unknown_device', 'Unknown device')) ?></h2>
                            <p><?= e(t('login_device_detail', 'Login device detail')) ?></p>
                        </div>
                        <div class="detail-grid">
                            <span><?= e(t('ip_address', 'IP address')) ?></span><strong><?= e($session['ip_address'] ?: '-') ?></strong>
                            <span><?= e(t('location_detail', 'Location detail')) ?></span><strong><?= e($session['location_label'] ?: '-') ?></strong>
                            <span><?= e(t('latitude', 'Latitude')) ?></span><strong><?= e($session['location_lat'] === null ? '-' : (string) $session['location_lat']) ?></strong>
                            <span><?= e(t('longitude', 'Longitude')) ?></span><strong><?= e($session['location_lng'] === null ? '-' : (string) $session['location_lng']) ?></strong>
                            <span><?= e(t('accuracy', 'Accuracy')) ?></span><strong><?= e($session['location_accuracy'] === null ? '-' : (string) $session['location_accuracy']) ?></strong>
                            <span><?= e(t('last_used', 'Last used')) ?></span><strong><?= e($session['last_used_at'] ?: $session['created_at']) ?></strong>
                            <span><?= e(t('login_device', 'Device')) ?></span><strong><?= e($session['user_agent'] ?: '-') ?></strong>
                        </div>
                    </template>
                </article>
            <?php endforeach; ?>
            <?php if (!$userSessions): ?>
                <p class="empty-copy"><?= e(t('no_login_devices', 'No saved login devices yet.')) ?></p>
            <?php endif; ?>
        </div>
    </section>

    <details class="more-panel more-accordion more-actions">
        <summary class="more-accordion-summary">
            <?= fta_icon('settings') ?>
            <span><?= e(t('setting', 'Setting')) ?></span>
            <small><?= e(t('update_clear_password', 'Update / Cache / Password')) ?></small>
            <?= fta_icon('chevron') ?>
        </summary>
        <div class="more-accordion-content more-action-list">
        <button class="settings-action more-action-primary" type="button" data-update-app>
            <?= fta_icon('refresh') ?><span><?= e(t('update_data', 'Update data')) ?></span>
        </button>
        <button class="ghost-btn more-action-secondary" type="button" data-clear-cache data-done-label="<?= e(t('cache_cleared', 'Cache cleared.')) ?>">
            <?= fta_icon('trash') ?><span><?= e(t('clear_cache', 'Clear cache')) ?></span>
        </button>
        <details class="more-password-box">
            <summary class="ghost-btn more-action-secondary">
                <?= fta_icon('lock') ?><span><?= e(t('change_password', 'Change Password')) ?></span>
            </summary>
            <form method="post" class="more-password-form">
                <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                <input type="hidden" name="action" value="change_password">
                <label>
                    <span><?= e(t('old_password', 'Old Password')) ?></span>
                    <input type="password" name="old_password" autocomplete="current-password" required>
                </label>
                <label>
                    <span><?= e(t('new_password', 'New Password')) ?></span>
                    <input type="password" name="new_password" minlength="8" autocomplete="new-password" required>
                </label>
                <label>
                    <span><?= e(t('confirm_password', 'Confirm Password')) ?></span>
                    <input type="password" name="confirm_password" minlength="8" autocomplete="new-password" required>
                </label>
                <button class="settings-action more-action-primary" type="submit">
                    <?= fta_icon('check') ?><span><?= e(t('save_password', 'Save Password')) ?></span>
                </button>
            </form>
        </details>
        <form method="post" action="<?= e(fta_lang_url($lang, 'logout.php')) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
            <button class="ghost-btn more-action-secondary" type="submit">
                <?= fta_icon('x') ?><span><?= e(t('logout', 'Logout')) ?></span>
            </button>
        </form>
        </div>
    </details>

    <div class="payment-detail-modal" data-payment-detail-modal hidden>
        <div class="payment-detail-card" role="dialog" aria-modal="true" aria-labelledby="payment-detail-title">
            <button type="button" class="modal-close" data-payment-detail-close aria-label="Close"><?= fta_icon('x') ?></button>
            <div data-payment-detail-body></div>
        </div>
    </div>
</main>
<?php
fta_render_footer_nav('more');
fta_render_telegram_modal();
fta_render_page_end();
