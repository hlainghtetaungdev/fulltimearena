<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';

try {
    fta_settings();
} catch (Throwable $error) {
    fta_render_db_error($error);
    exit;
}

fta_render_head(t('live_score'), 'public-page live-score-page');
fta_render_public_header('live_score');
?>
<main class="app-shell narrow">
    <section class="result-shell" data-live-score-page>
        <div class="result-topbar">
            <div>
                <span><?= e(t('match_center')) ?></span>
                <h1><?= fta_icon('score') ?><?= e(t('live_score')) ?></h1>
            </div>
            <div class="result-toolbar">
                <label class="date-icon-control" aria-label="<?= e(t('match_date')) ?>">
                    <?= fta_icon('calendar') ?>
                    <input type="date" data-match-date>
                </label>
                <button class="icon-btn result-refresh" type="button" data-result-refresh aria-label="<?= e(t('refresh_results')) ?>">
                    <?= fta_icon('refresh') ?>
                </button>
            </div>
        </div>

        <div class="news-state" data-result-loading>
            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            <span><?= e(t('loading_matches')) ?></span>
        </div>

        <div class="notice error" data-result-error hidden>
            <strong><?= e(t('result_error_title')) ?></strong>
            <span data-result-error-text><?= e(t('result_error_message')) ?></span>
        </div>

        <section class="result-matches-panel" data-matches-panel>
            <div class="result-section-title">
                <h2><?= e(t('matches')) ?></h2>
                <span data-result-date-label></span>
            </div>
            <div class="result-filter-bar" data-result-filters aria-label="<?= e(t('match_filters', 'Match filters')) ?>">
                <button class="active" type="button" data-result-filter="all">
                    <span><?= e(t('filter_all', 'All')) ?></span>
                    <em data-filter-count="all"></em>
                </button>
                <button type="button" data-result-filter="live">
                    <span><?= e(t('status_live')) ?></span>
                    <em data-filter-count="live"></em>
                </button>
                <button type="button" data-result-filter="finished">
                    <span><?= e(t('status_finished')) ?></span>
                    <em data-filter-count="finished"></em>
                </button>
                <button type="button" data-result-filter="upcoming">
                    <span><?= e(t('status_upcoming')) ?></span>
                    <em data-filter-count="upcoming"></em>
                </button>
            </div>
            <div class="league-list" data-league-list></div>
        </section>

        <section class="result-detail-panel" data-result-detail hidden>
            <button class="ghost-btn result-back" type="button" data-back-to-matches><?= fta_icon('home') ?><span><?= e(t('back_to_matches')) ?></span></button>
            <div data-result-detail-content></div>
        </section>
    </section>
</main>
<?php
fta_render_footer_nav('live_score');
fta_render_telegram_modal();
?>
<script>
    window.FTA_RESULT_TEXT = {
        selectDate: <?= json_encode(t('select_date')) ?>,
        loadingMatches: <?= json_encode(t('loading_matches')) ?>,
        loadingDetails: <?= json_encode(t('loading_details')) ?>,
        noFilteredMatches: <?= json_encode(t('no_filtered_matches', 'No matches found for this filter.')) ?>,
        summary: <?= json_encode(t('summary')) ?>,
        errorMessage: <?= json_encode(t('result_error_message')) ?>,
        noMatches: <?= json_encode(t('no_matches')) ?>,
        noData: <?= json_encode(t('no_data')) ?>,
        matches: <?= json_encode(t('matches')) ?>,
        leagueTable: <?= json_encode(t('league_table')) ?>,
        viewTable: <?= json_encode(t('view_table')) ?>,
        h2h: <?= json_encode(t('h2h')) ?>,
        details: <?= json_encode(t('details')) ?>,
        standings: <?= json_encode(t('standings')) ?>,
        goals: <?= json_encode(t('goals')) ?>,
        statistics: <?= json_encode(t('statistics')) ?>,
        substitutions: <?= json_encode(t('substitutions')) ?>,
        playedShort: <?= json_encode(t('played_short')) ?>,
        winsShort: <?= json_encode(t('wins_short')) ?>,
        drawsShort: <?= json_encode(t('draws_short')) ?>,
        lossesShort: <?= json_encode(t('losses_short')) ?>,
        gdShort: <?= json_encode(t('gd_short')) ?>,
        ptsShort: <?= json_encode(t('pts_short')) ?>,
        homeWins: <?= json_encode(t('home_wins')) ?>,
        awayWins: <?= json_encode(t('away_wins')) ?>,
        totalMatches: <?= json_encode(t('total_matches')) ?>,
        lastFive: <?= json_encode(t('last_five')) ?>,
        draws: <?= json_encode(t('draws')) ?>,
        statusUpcoming: <?= json_encode(t('status_upcoming')) ?>,
        statusLive: <?= json_encode(t('status_live')) ?>,
        statusFinished: <?= json_encode(t('status_finished')) ?>,
        statLabels: {
            possession: <?= json_encode(t('possession')) ?>,
            shots: <?= json_encode(t('shots')) ?>,
            shots_on_target: <?= json_encode(t('shots_on_target')) ?>,
            corners: <?= json_encode(t('corners')) ?>,
            offsides: <?= json_encode(t('offsides')) ?>,
            fouls: <?= json_encode(t('fouls')) ?>,
            yellow_cards: <?= json_encode(t('yellow_cards')) ?>,
            red_cards: <?= json_encode(t('red_cards')) ?>,
            throw_ins: <?= json_encode(t('throw_ins')) ?>,
            xg: <?= json_encode(t('xg')) ?>
        }
    };
</script>
<script src="<?= e(fta_base_url('assets/js/live-score.js')) ?>?v=<?= e(fta_asset_version('assets/js/live-score.js')) ?>"></script>
<?php fta_render_page_end(); ?>
