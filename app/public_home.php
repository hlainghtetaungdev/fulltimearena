<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';

try {
    $ads = fta_active_ads();
    $categories = array_values(array_filter(fta_active_categories_for_user(fta_current_user()), static function (array $category): bool {
        $link = strtolower(trim((string) ($category['link_url'] ?? '')));
        $name = strtolower(trim((string) ($category['name'] ?? '')));
        $reservedLinks = ['news.php', 'inbox.php', 'more.php'];
        $reservedNames = ['news', 'mail', 'inbox', 'more'];

        return !in_array($link, $reservedLinks, true) && !in_array($name, $reservedNames, true);
    }));
} catch (Throwable $error) {
    fta_render_db_error($error);
    exit;
}

fta_render_head(t('dashboard'), 'public-page');
fta_render_public_header('home');
?>
<main class="app-shell">
    <section class="slide-section" aria-label="<?= e(t('ads')) ?>">
        <div class="ad-slider" data-ad-slider>
            <?php if ($ads): ?>
                <?php foreach ($ads as $index => $ad): ?>
                    <?php
                    $link = fta_clean_link($ad['link_url'] ?? '');
                    $slideClass = $index === 0 ? 'ad-slide active' : 'ad-slide';
                    ?>
                    <div class="<?= e($slideClass) ?>" data-slide>
                        <?php if ($link !== ''): ?>
                            <a href="<?= e($link) ?>" <?= preg_match('/^https?:\/\//i', $link) ? 'target="_blank" rel="noopener"' : '' ?>>
                                <img src="<?= e(fta_image_src($ad['image_path'])) ?>" alt="<?= e(t('ads')) ?>">
                            </a>
                        <?php else: ?>
                            <img src="<?= e(fta_image_src($ad['image_path'])) ?>" alt="<?= e(t('ads')) ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="ad-slide active fallback-slide">
                    <img src="<?= e(fta_image_src('uploads/ads/sample-ad-1.png')) ?>" alt="<?= e(t('ads')) ?>">
                </div>
            <?php endif; ?>
        </div>
        <?php if (count($ads) > 1): ?>
            <div class="slider-dots" data-ad-dots aria-label="<?= e(t('ads')) ?>">
                <?php foreach ($ads as $index => $ad): ?>
                    <button type="button" class="<?= $index === 0 ? 'active' : '' ?>" data-ad-dot="<?= (int) $index ?>" aria-label="<?= e(t('ads')) ?> <?= (int) $index + 1 ?>"></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="quick-grid-section">
        <div class="section-heading">
            <h2><?= e(t('categories')) ?></h2>
        </div>
        <div class="category-grid">
            <?php foreach ($categories as $category): ?>
                <?php
                $link = fta_clean_link($category['link_url'] ?? '');
                $href = $link === '' ? fta_lang_url(fta_current_lang(), 'unit.php') : $link;
                ?>
                <a class="category-tile" href="<?= e($href) ?>" <?= preg_match('/^https?:\/\//i', $href) ? 'target="_blank" rel="noopener"' : '' ?>>
                    <span class="tile-icon"><?= fta_category_icon_html($category) ?></span>
                    <span class="tile-label"><?= e(fta_category_display_name($category)) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
</main>
<?php
fta_render_footer_nav('home');
fta_render_telegram_modal();
fta_render_page_end();
