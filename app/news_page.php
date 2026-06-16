<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';

try {
    fta_settings();
} catch (Throwable $error) {
    fta_render_db_error($error);
    exit;
}

fta_render_head(t('news'), 'public-page news-page');
fta_render_public_header('news');
?>
<main class="app-shell narrow">
    <section class="news-shell" data-news-page>
        <div class="news-topbar">
            <div>
                <span><?= e(t('latest_updates')) ?></span>
                <h1><?= fta_icon('news') ?><?= e(t('football_news')) ?></h1>
            </div>
            <button class="icon-btn news-refresh" type="button" data-news-refresh aria-label="<?= e(t('refresh_news')) ?>">
                <?= fta_icon('refresh') ?>
            </button>
        </div>

        <div class="news-state" data-news-loading>
            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            <span><?= e(t('loading_news')) ?></span>
        </div>

        <div class="notice error" data-news-error hidden>
            <strong><?= e(t('news_error_title')) ?></strong>
            <span><?= e(t('news_error_message')) ?></span>
        </div>

        <div class="news-list" data-news-list></div>

        <article class="news-detail" data-news-detail hidden>
            <button class="ghost-btn news-back" type="button" data-news-back><?= fta_icon('home') ?><span><?= e(t('back_to_news')) ?></span></button>
            <img data-news-detail-image alt="">
            <div class="news-detail-meta">
                <span data-news-detail-date></span>
                <span data-news-detail-source></span>
            </div>
            <h2 data-news-detail-title></h2>
            <div class="news-body" data-news-detail-body></div>
        </article>
    </section>
</main>
<?php
fta_render_footer_nav('news');
fta_render_telegram_modal();
?>
<script>
    window.FTA_NEWS_TEXT = {
        readMore: <?= json_encode(t('read_more')) ?>,
        noNews: <?= json_encode(t('no_news')) ?>,
        imageAlt: <?= json_encode(t('news_image_alt')) ?>,
        sourceCredit: <?= json_encode(t('news_source_credit', 'Source > ballonestar')) ?>
    };
</script>
<script src="<?= e(fta_base_url('assets/js/news.js')) ?>?v=<?= e(fta_asset_version('assets/js/news.js')) ?>"></script>
<?php fta_render_page_end(); ?>
