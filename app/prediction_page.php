<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';

try {
    $settings = fta_settings();
    $isOpen = fta_form_is_open($settings);
    $serverAlreadySubmitted = fta_submission_exists(null, fta_client_ip(), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $result = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $result = fta_submit_prediction($settings);
        $serverAlreadySubmitted = $serverAlreadySubmitted || $result['ok'];
    }
} catch (Throwable $error) {
    fta_render_db_error($error);
    exit;
}

fta_render_head(t('prediction'), 'public-page prediction-page');
fta_render_public_header('prediction');
?>
<main class="app-shell narrow">
    <section class="match-card prediction-hero">
        <div class="section-heading compact">
            <h1><?= fta_icon('target') ?><?= e(t('prediction')) ?></h1>
            <span class="<?= $isOpen ? 'status-open' : 'status-closed' ?>"><?= e($isOpen ? t('play_now') : t('form_closed')) ?></span>
        </div>
        <div class="team-versus">
            <div class="team-box">
                <img src="<?= e(fta_image_src($settings['team_a_logo'] ?? '')) ?>" alt="">
                <strong><?= e($settings['team_a_name'] ?? t('team_a')) ?></strong>
            </div>
            <span class="versus"><?= e(t('versus')) ?></span>
            <div class="team-box">
                <img src="<?= e(fta_image_src($settings['team_b_logo'] ?? '')) ?>" alt="">
                <strong><?= e($settings['team_b_name'] ?? t('team_b')) ?></strong>
            </div>
        </div>
    </section>

    <details class="rules-accordion">
        <summary><?= fta_icon('shield') ?><span><?= e(t('rules')) ?></span></summary>
        <div class="rules-content">
            <dl>
                <div>
                    <dt><?= e(t('prize_total')) ?></dt>
                    <dd><?= e($settings['prize_total'] ?? '1,000,000 Kyat') ?></dd>
                </div>
                <div>
                    <dt><?= e(t('prize_each')) ?></dt>
                    <dd><?= e($settings['prize_each'] ?? '50,000 Kyat') ?></dd>
                </div>
            </dl>
            <ul>
                <li><?= e(t('rule_follow')) ?></li>
                <li><?= e(t('rule_once')) ?></li>
            </ul>
        </div>
    </details>

    <?php if ($result): ?>
        <div class="notice <?= $result['ok'] ? 'success' : 'error' ?>">
            <strong><?= e($result['ok'] ? t('success_title') : t('form_closed')) ?></strong>
            <span><?= e($result['message']) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($result && $result['ok']): ?>
        <section class="form-card center">
            <h2><?= e(t('success_title')) ?></h2>
            <p><?= e(t('success_message')) ?></p>
            <a class="primary-btn wide" href="<?= e(fta_lang_url(fta_current_lang())) ?>"><?= e(t('back_home')) ?></a>
        </section>
        <script>window.FTA_MARK_SUBMITTED = true;</script>
    <?php elseif (!$isOpen): ?>
        <section class="form-card center">
            <h2><?= e(t('form_closed')) ?></h2>
            <p><?= e(t('form_closed_message')) ?></p>
            <a class="ghost-btn wide" href="<?= e(fta_lang_url(fta_current_lang())) ?>"><?= e(t('back_home')) ?></a>
        </section>
    <?php elseif ($serverAlreadySubmitted): ?>
        <section class="form-card center" data-already-submitted-panel>
            <h2><?= e(t('already_submitted')) ?></h2>
            <p><?= e(t('rule_once')) ?></p>
            <a class="ghost-btn wide" href="<?= e(fta_lang_url(fta_current_lang())) ?>"><?= e(t('back_home')) ?></a>
        </section>
    <?php else: ?>
        <form class="form-card prediction-form" method="post" data-prediction-form data-already-title="<?= e(t('already_submitted')) ?>" data-already-body="<?= e(t('rule_once')) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
            <input type="hidden" name="storage_id" data-storage-id>
            <input type="hidden" name="device_json" data-device-json>
            <input type="hidden" name="browser_language" data-browser-language>
            <input type="hidden" name="screen_size" data-screen-size>

            <label>
                <span class="field-label"><?= fta_icon('activity') ?><?= e(t('ht_result')) ?></span>
                <input type="text" name="ht_result" placeholder="0-0" required maxlength="50" autocomplete="off">
            </label>
            <label>
                <span class="field-label"><?= fta_icon('ball') ?><?= e(t('ft_result')) ?></span>
                <input type="text" name="ft_result" placeholder="2-1" required maxlength="50" autocomplete="off">
            </label>
            <label>
                <span class="field-label"><?= fta_icon('trophy') ?><?= e(t('first_scorer')) ?></span>
                <input type="text" name="first_scorer" required maxlength="120" autocomplete="off">
            </label>

            <fieldset>
                <legend class="field-label"><?= fta_icon('wallet') ?><?= e(t('payment_method')) ?></legend>
                <div class="segmented">
                    <label>
                        <input type="radio" name="wallet_type" value="KBZ Pay" required>
                        <span><?= e(t('kbz_pay')) ?></span>
                    </label>
                    <label>
                        <input type="radio" name="wallet_type" value="Wave Money" required>
                        <span><?= e(t('wave_money')) ?></span>
                    </label>
                </div>
            </fieldset>

            <label>
                <span class="field-label"><?= fta_icon('shield') ?><?= e(t('wallet_name')) ?></span>
                <input type="text" name="wallet_name" required maxlength="120" autocomplete="name">
            </label>
            <label>
                <span class="field-label"><?= fta_icon('wallet') ?><?= e(t('wallet_number')) ?></span>
                <input type="tel" name="wallet_number" required maxlength="80" autocomplete="tel">
            </label>

            <button class="primary-btn wide" type="submit"><?= fta_icon('play') ?><span><?= e(t('submit')) ?></span></button>
        </form>
    <?php endif; ?>
</main>
<?php
fta_render_footer_nav('prediction');
fta_render_telegram_modal();
?>
<script src="<?= e(fta_base_url('assets/js/prediction.js')) ?>?v=<?= e(fta_asset_version('assets/js/prediction.js')) ?>"></script>
<?php fta_render_page_end(); ?>
