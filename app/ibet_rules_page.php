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
$acceptUrl = 'https://www.ibet788.com/';
$rules = fta_ibet_rules_for_user(fta_current_user());

$defaultFootballRules = [
    'မောင်းအဆ 500',
    '-70 အောက်ကြေးများ ကစားလို့မရ',
    'ဘီလာရုဇ် ၊ ရီဂျင်နယ် ၊ မြန်မာနေရှင်နယ် နှင့် တက္ကသိုလ် (University) လိဂ်များ ကစားလို့မရ',
    'U17 to U23 ပွဲများ / Women (W) / Reverse (R) လိဂ်များ ကစားလို့မရ',
    'မြန်မာကြေး: Live မရ',
];

$defaultEgameRules = [
    'E-Games / Saba (500 Units)',
    'မောင်းတွဲလို့မရ',
    'ပထမပိုင်းမရ / ဒုတိယပိုင်းမရ ( ပွဲချိန်ပြည့်ပဲရ )',
    'All Over / Under Series ကစားလို့မရ',
];

$splitRules = static function (string $text): array {
    return array_values(array_filter(array_map(
        static fn (string $line): string => trim($line),
        preg_split('/\R+/', $text) ?: []
    )));
};

$ruleSetIsBroken = static function (array $items, int $minimumCount): bool {
    if (count($items) < $minimumCount) {
        return true;
    }

    foreach ($items as $line) {
        $line = trim((string) $line);
        if ($line === '' || str_contains($line, 'á€')) {
            return true;
        }

        if (preg_match('/^[\x{102B}-\x{103E}]/u', $line)) {
            return true;
        }

        if (preg_match('/^(?:ားလို့မရ|လို့မရ)$/u', $line)) {
            return true;
        }
    }

    return false;
};

$footballRules = $splitRules((string) ($rules['football_rules'] ?? ''));
$egameRules = $splitRules((string) ($rules['egame_rules'] ?? ''));

if ($ruleSetIsBroken($footballRules, count($defaultFootballRules))) {
    $footballRules = $defaultFootballRules;
}

if ($ruleSetIsBroken($egameRules, count($defaultEgameRules))) {
    $egameRules = $defaultEgameRules;
}

$renderRuleCards = static function (array $items): void {
    echo '<div class="ibet-rule-card-list">';
    foreach ($items as $index => $rule) {
        echo '<article class="ibet-rule-card">';
        echo '<span class="ibet-rule-number">' . e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) . '</span>';
        echo '<p class="ibet-rule-text">' . nl2br(e((string) $rule)) . '</p>';
        echo '</article>';
    }
    echo '</div>';
};

fta_render_head(t('ibet_rules_title', 'iBet 789 Rules'), 'public-page ibet-rules-page');
fta_render_public_header('home');
?>
<main class="app-shell narrow ibet-rules-shell">
    <section class="ibet-rules-hero">
        <span class="ibet-logo-mark">IBET789</span>
        <div>
            <h1><?= e(t('ibet_rules_title', 'စည်းကမ်းသတ်မှတ်ချက်များ')) ?></h1>
            <?php if (!empty($rules['updated_at'])): ?><span class="ibet-updated">Updated: <?= e((string) $rules['updated_at']) ?></span><?php endif; ?>
            <p class="ibet-important">
                <?= fta_icon('shield') ?>
                <span>စည်းကမ်းများကို သေချာဖတ်ရှုပြီး လက်ခံမှသာ iBet 789 သို့ ဝင်ရောက်နိုင်ပါမည်။</span>
            </p>
        </div>
    </section>

    <section class="ibet-rule-group">
        <div class="ibet-rule-heading"><?= fta_icon('ticket') ?><span>Shwe Yoe</span></div>
        <div class="ibet-rule-heading"><?= fta_icon('football') ?><span>Football Rules</span></div>
        <?php $renderRuleCards($footballRules); ?>
    </section>

    <section class="ibet-rule-group">
        <div class="ibet-rule-heading"><?= fta_icon('controller') ?><span>E-Games / Saba</span></div>
        <?php $renderRuleCards($egameRules); ?>
    </section>

    <section class="ibet-rule-group">
        <div class="ibet-rule-heading"><?= fta_icon('check') ?><span><?= e(t('important_notice', 'Important Notice')) ?></span></div>
        <p class="ibet-important">
            <?= fta_icon('shield') ?>
            <span>စည်းကမ်းသတ်မှတ်ချက်များ ငြိစွန်းလျှင် အလျော်မရှိ အစားသာရှိမည်ဖြစ်ကြောင်း အသိပေးအပ်ပါသည်။</span>
        </p>
    </section>

    <?php if (!empty($rules['history'])): ?>
        <section class="ibet-rule-group">
            <div class="ibet-rule-heading"><?= fta_icon('calendar') ?><span>Last 3 Rule History</span></div>
            <?php foreach ((array) $rules['history'] as $history): ?>
                <details class="ibet-history-item">
                    <summary><?= e((string) ($history['created_at'] ?? '')) ?></summary>
                    <p><?= nl2br(e((string) ($history['football_rules'] ?? ''))) ?></p>
                    <p><?= nl2br(e((string) ($history['egame_rules'] ?? ''))) ?></p>
                </details>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <nav class="ibet-rules-actions" aria-label="<?= e(t('ibet_rules_title', 'iBet 789 Rules')) ?>">
        <a class="danger-btn" href="<?= e(fta_lang_url($lang)) ?>"><?= e(t('decline_rules', 'လက်မခံပါ')) ?></a>
        <a class="success-btn" href="<?= e($acceptUrl) ?>" target="_blank" rel="noopener"><?= e(t('accept_rules', 'လက်ခံပါသည်')) ?></a>
    </nav>
</main>
<?php
fta_render_footer_nav('home');
fta_render_page_end();
