<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';

try {
    fta_settings();
} catch (Throwable $error) {
    fta_render_db_error($error);
    exit;
}

fta_render_head(t('market_data', 'Market Data'), 'public-page market-data-page');
fta_render_public_header('market_data');
?>
<main class="app-shell narrow">
    <section class="market-shell" data-market-page>
        <div class="market-topbar">
            <div>
                <span><?= e(t('gold_currency_fuel', 'Gold / Currency / Fuel')) ?></span>
                <h1><?= fta_icon('market') ?><?= e(t('market_data', 'Market Data')) ?></h1>
            </div>
            <button class="icon-btn market-refresh" type="button" data-market-refresh aria-label="<?= e(t('refresh_market', 'Refresh market data')) ?>">
                <?= fta_icon('refresh') ?>
            </button>
        </div>

        <div class="market-tabs" role="tablist" aria-label="<?= e(t('market_data', 'Market Data')) ?>">
            <button class="active" type="button" data-market-tab="gold"><?= fta_icon('gold') ?><span><?= e(t('gold', 'Gold')) ?></span></button>
            <button type="button" data-market-tab="currency"><?= fta_icon('currency') ?><span><?= e(t('currency', 'Currency')) ?></span></button>
            <button type="button" data-market-tab="fuel"><?= fta_icon('fuel') ?><span><?= e(t('fuel', 'Fuel')) ?></span></button>
        </div>

        <label class="market-region-control" data-market-region-wrap hidden>
            <span><?= e(t('region', 'Region')) ?></span>
            <select data-market-region></select>
        </label>

        <div class="market-meta">
            <span data-market-updated><?= e(t('update_time', 'Update Time')) ?>: -</span>
            <small>Rate API Documentation © Hlaing Htet Aung</small>
        </div>

        <div class="news-state" data-market-loading>
            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            <span><?= e(t('loading_market', 'Loading market data...')) ?></span>
        </div>

        <div class="notice error" data-market-error hidden>
            <strong><?= e(t('market_error_title', 'Market data unavailable')) ?></strong>
            <span data-market-error-text><?= e(t('market_error_message', 'Could not load market data right now. Please try again.')) ?></span>
        </div>

        <div class="market-grid" data-market-list></div>

        <section class="market-history-panel" data-market-history hidden>
            <div class="market-history-head">
                <div>
                    <span><?= e(t('last_7_days', 'Last 7 days')) ?></span>
                    <h2 data-market-history-title></h2>
                </div>
                <button class="icon-btn" type="button" data-market-history-close aria-label="<?= e(t('close_message', 'Close')) ?>"><?= fta_icon('x') ?></button>
            </div>
            <div data-market-history-body></div>
        </section>
    </section>
</main>
<?php
fta_render_footer_nav('home');
fta_render_telegram_modal();
?>
<script>
    window.FTA_MARKET_TEXT = {
        updateTime: <?= json_encode(t('update_time', 'Update Time')) ?>,
        buy: <?= json_encode(t('buy', 'Buy')) ?>,
        sell: <?= json_encode(t('sell', 'Sell')) ?>,
        rate: <?= json_encode(t('rate', 'Rate')) ?>,
        noData: <?= json_encode(t('no_data', 'No data available.')) ?>,
        last7Days: <?= json_encode(t('last_7_days', 'Last 7 days')) ?>,
        errorMessage: <?= json_encode(t('market_error_message', 'Could not load market data right now. Please try again.')) ?>,
        loadingHistory: <?= json_encode(t('loading_history', 'Loading history...')) ?>
    };
</script>
<script src="<?= e(fta_base_url('assets/js/market-data.js')) ?>?v=<?= e(fta_asset_version('assets/js/market-data.js')) ?>"></script>
<?php fta_render_page_end(); ?>
