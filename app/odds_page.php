<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';

try {
    fta_settings();
} catch (Throwable $error) {
    fta_render_db_error($error);
    exit;
}

fta_render_head(t('odds_title', 'ပေါက်ကြေးများ'), 'public-page odds-page');
fta_render_public_header('odds');
?>
<main class="app-shell narrow">
    <section class="odds-shell" data-odds-page>
        <div class="market-topbar">
            <div>
                <span><?= e(t('odds_subtitle', 'Myanmar football odds')) ?></span>
                <h1><?= fta_icon('odds') ?><?= e(t('odds_title', 'ပေါက်ကြေးများ')) ?></h1>
            </div>
            <button class="icon-btn market-refresh" type="button" data-odds-refresh aria-label="<?= e(t('refresh_odds', 'Refresh odds')) ?>">
                <?= fta_icon('refresh') ?>
            </button>
        </div>

        <div class="market-tabs odds-tabs" role="tablist" aria-label="<?= e(t('odds_title', 'ပေါက်ကြေးများ')) ?>">
            <button class="active" type="button" data-odds-tab="today"><?= fta_icon('calendar') ?><span><?= e(t('today', 'Today')) ?></span></button>
            <button type="button" data-odds-tab="tomorrow"><?= fta_icon('calendar') ?><span><?= e(t('tomorrow', 'Tomorrow')) ?></span></button>
        </div>

        <div class="news-state" data-odds-loading>
            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            <span><?= e(t('loading_odds', 'Loading odds...')) ?></span>
        </div>

        <div class="notice error" data-odds-error hidden>
            <strong><?= e(t('odds_error_title', 'Odds unavailable')) ?></strong>
            <span data-odds-error-text><?= e(t('odds_error_message', 'Could not load odds right now. Please try again.')) ?></span>
        </div>

        <div class="odds-league-list" data-odds-list></div>

        <div class="odds-history-modal" data-odds-history hidden>
            <div class="odds-history-dialog" role="dialog" aria-modal="true" aria-labelledby="odds-history-title">
                <div class="market-history-head">
                    <div>
                        <span><?= e(t('odds_analysis', 'Odds analysis')) ?></span>
                        <h2 id="odds-history-title" data-odds-history-title></h2>
                    </div>
                    <button class="icon-btn" type="button" data-odds-history-close aria-label="<?= e(t('close_message', 'Close')) ?>"><?= fta_icon('x') ?></button>
                </div>
                <div data-odds-history-body></div>
            </div>
        </div>
    </section>
</main>
<?php
fta_render_footer_nav('home');
fta_render_telegram_modal();
?>
<script>
    window.FTA_ODDS_TEXT = {
        noData: <?= json_encode(t('no_data', 'No data available.')) ?>,
        errorMessage: <?= json_encode(t('odds_error_message', 'Could not load odds right now. Please try again.')) ?>,
        loadingHistory: <?= json_encode(t('loading_history', 'Loading history...')) ?>,
        handicap: <?= json_encode(t('handicap', 'Handicap')) ?>,
        overUnder: <?= json_encode(t('over_under', 'Over/Under')) ?>,
        lastHour: <?= json_encode(t('last_hour_change', 'Last 1 hr change')) ?>,
        hourlyRecord: <?= json_encode(t('hourly_record', 'Hourly record')) ?>,
        matches: <?= json_encode(t('matches', 'Matches')) ?>
    };
</script>
<script src="<?= e(fta_base_url('assets/js/odds.js')) ?>?v=<?= e(fta_asset_version('assets/js/odds.js')) ?>"></script>
<?php fta_render_page_end(); ?>
