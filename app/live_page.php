<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';

try {
    fta_settings();
} catch (Throwable $error) {
    fta_render_db_error($error);
    exit;
}

fta_render_head(t('football_live'), 'public-page live-page');
fta_render_public_header('live');
?>
<main class="app-shell narrow">
    <section class="live-shell" data-live-page>
        <div class="result-topbar">
            <div>
                <span><?= e(t('live_streams')) ?></span>
                <h1><?= fta_icon('play') ?><?= e(t('football_live')) ?></h1>
            </div>
            <button class="icon-btn result-refresh" type="button" data-live-refresh aria-label="<?= e(t('refresh_live')) ?>">
                <?= fta_icon('refresh') ?>
            </button>
        </div>

        <div class="live-tabs" role="tablist" aria-label="<?= e(t('football_live')) ?>">
            <button class="active" type="button" data-live-filter="all"><?= e(t('matches')) ?></button>
            <button type="button" data-live-filter="live"><?= e(t('live_matches')) ?></button>
            <button type="button" data-live-filter="upcoming"><?= e(t('upcoming_matches')) ?></button>
        </div>

        <div class="news-state" data-live-loading>
            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            <span><?= e(t('loading_live')) ?></span>
        </div>

        <div class="notice error" data-live-error hidden>
            <strong><?= e(t('live_error_title')) ?></strong>
            <span><?= e(t('live_error_message')) ?></span>
        </div>

        <div class="live-list" data-live-list></div>
    </section>
</main>

<div class="live-player-modal" data-live-player hidden>
    <div class="live-player-card" role="dialog" aria-modal="true" aria-labelledby="live-player-title">
        <div class="live-player-head">
            <div>
                <span><?= e(t('now_playing')) ?></span>
                <h2 id="live-player-title" data-live-player-title><?= e(t('football_live')) ?></h2>
            </div>
            <button class="modal-close" type="button" data-live-player-close aria-label="<?= e(t('close_player')) ?>"><?= fta_icon('x') ?></button>
        </div>
        <div class="live-player-frame">
            <iframe data-live-player-frame title="<?= e(t('football_live')) ?>" allow="autoplay; encrypted-media; fullscreen; picture-in-picture" allowfullscreen referrerpolicy="no-referrer" hidden></iframe>
            <video data-live-player-video controls playsinline hidden></video>
            <img class="live-watermark" src="<?= e(fta_logo_url()) ?>" alt="">
            <button class="live-fullscreen-btn" type="button" data-live-fullscreen aria-label="<?= e(t('fullscreen', 'Fullscreen')) ?>">
                <?= fta_icon('expand') ?>
            </button>
        </div>
        <div class="live-player-status">
            <span><?= e(t('current_stream', 'Current stream')) ?></span>
            <strong data-live-player-quality></strong>
        </div>
        <div class="live-stream-choices" data-live-stream-choices></div>
    </div>
</div>
<?php
fta_render_footer_nav('live');
fta_render_telegram_modal();
?>
<script>
    window.FTA_LIVE_TEXT = {
        liveMatches: <?= json_encode(t('live_matches')) ?>,
        upcomingMatches: <?= json_encode(t('upcoming_matches')) ?>,
        loadingLive: <?= json_encode(t('loading_live')) ?>,
        liveError: <?= json_encode(t('live_error_message')) ?>,
        noLiveMatches: <?= json_encode(t('no_live_matches')) ?>,
        noUpcomingMatches: <?= json_encode(t('no_upcoming_matches')) ?>,
        watchLive: <?= json_encode(t('watch_live')) ?>,
        stream: <?= json_encode(t('stream')) ?>,
        kickoff: <?= json_encode(t('kickoff')) ?>,
        liveNow: <?= json_encode(t('live_now')) ?>,
        upcoming: <?= json_encode(t('upcoming')) ?>,
        refreshLive: <?= json_encode(t('refresh_live')) ?>,
        nowPlaying: <?= json_encode(t('now_playing')) ?>,
        closePlayer: <?= json_encode(t('close_player')) ?>,
        selectStream: <?= json_encode(t('select_stream')) ?>,
        noStreams: <?= json_encode(t('no_streams')) ?>,
        currentStream: <?= json_encode(t('current_stream', 'Current stream')) ?>,
        fullscreen: <?= json_encode(t('fullscreen', 'Fullscreen')) ?>
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/hls.js@1/dist/hls.min.js"></script>
<script src="<?= e(fta_base_url('assets/js/live.js')) ?>?v=<?= e(fta_asset_version('assets/js/live.js')) ?>"></script>
<?php fta_render_page_end(); ?>
