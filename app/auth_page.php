<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';

$mode = defined('FTA_AUTH_MODE') ? FTA_AUTH_MODE : 'login';
if (!in_array($mode, ['login', 'signup', 'forgot'], true)) {
    $mode = 'login';
}

try {
    fta_settings();
    fta_require_https_for_auth(false);
} catch (Throwable $error) {
    fta_render_db_error($error);
    exit;
}

$lang = fta_current_lang();
$countries = fta_auth_countries();
$defaultCountry = 'my';
$errorMessage = '';
$values = [
    'full_name' => '',
    'phone_country' => $defaultCountry,
    'phone_number' => '',
    'promo_code' => '',
    'remember' => '1',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $mode !== 'forgot') {
    $values['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
    $values['phone_country'] = strtolower(trim((string) ($_POST['phone_country'] ?? $defaultCountry)));
    $values['phone_number'] = trim((string) ($_POST['phone_number'] ?? ''));
    $values['promo_code'] = strtoupper(trim((string) ($_POST['promo_code'] ?? '')));
    $values['remember'] = !empty($_POST['remember']) ? '1' : '0';

    try {
        fta_check_csrf();
        if ($mode === 'signup') {
            $user = fta_auth_signup($_POST);
            fta_login_user($user, true, $_POST);
            fta_flash('success', t('auth_signup_success', 'Account created successfully.'));
            fta_redirect(fta_lang_url($lang));
        }

        $user = fta_auth_login($_POST);
        fta_login_user($user, !empty($_POST['remember']), $_POST);
        fta_flash('success', t('auth_login_success', 'Login successful.'));
        fta_redirect(fta_lang_url($lang));
    } catch (Throwable $error) {
        $errorMessage = $error->getMessage();
    }
}

$title = $mode === 'signup' ? t('signup', 'Sign up') : ($mode === 'forgot' ? t('forgot_password', 'Forgot password') : t('login', 'Login'));
$currentUser = fta_current_user();
$authPage = $mode === 'signup' ? 'signup.php' : ($mode === 'forgot' ? 'forgot-password.php' : 'login.php');
$authFlag = ['en' => 'us', 'my' => 'mm', 'jp' => 'jp', 'th' => 'th'][$lang] ?? 'us';

fta_render_head($title, 'public-page auth-page');
?>
<details class="auth-language-switch auth-language-dropdown">
    <summary aria-label="<?= e(t('choose_language', 'Choose Language')) ?>">
        <span class="flag-icon flag-<?= e($authFlag) ?>" aria-hidden="true"></span>
        <span><?= e(strtoupper($lang)) ?></span>
        <?= fta_icon('chevron') ?>
    </summary>
    <div class="auth-language-menu" aria-label="<?= e(t('language', 'Language')) ?>">
        <a class="<?= $lang === 'en' ? 'active' : '' ?>" href="<?= e(fta_lang_url('en', $authPage)) ?>"><span class="flag-icon flag-us" aria-hidden="true"></span><span>English</span></a>
        <a class="<?= $lang === 'my' ? 'active' : '' ?>" href="<?= e(fta_lang_url('my', $authPage)) ?>"><span class="flag-icon flag-mm" aria-hidden="true"></span><span>မြန်မာ</span></a>
        <a class="<?= $lang === 'jp' ? 'active' : '' ?>" href="<?= e(fta_lang_url('jp', $authPage)) ?>"><span class="flag-icon flag-jp" aria-hidden="true"></span><span>日本語</span></a>
        <a class="<?= $lang === 'th' ? 'active' : '' ?>" href="<?= e(fta_lang_url('th', $authPage)) ?>"><span class="flag-icon flag-th" aria-hidden="true"></span><span>ไทย</span></a>
    </div>
</details>
<main class="app-shell narrow">
    <section class="auth-shell">
        <div class="auth-card">
            <div class="auth-logo-lockup">
                <img src="<?= e(fta_logo_url()) ?>" alt="">
                <span><?= fta_icon($mode === 'signup' ? 'person' : 'lock') ?></span>
            </div>
            <div class="auth-title">
                <span><?= e(FTA_APP_NAME) ?></span>
                <h1><?= e($title) ?></h1>
                <p><?= e($mode === 'signup' ? t('signup_intro', 'Create your FullTime Arena account.') : ($mode === 'forgot' ? t('forgot_intro', 'Password reset is handled by admin support.') : t('login_intro', 'Login securely with your phone number.'))) ?></p>
            </div>

            <?php if ($mode !== 'forgot'): ?>
                <div class="auth-mode-tabs" aria-label="<?= e(t('account', 'Account')) ?>">
                    <a class="<?= $mode === 'login' ? 'active' : '' ?>" href="<?= e(fta_lang_url($lang, 'login.php')) ?>"><?= e(t('login', 'Login')) ?></a>
                    <a class="<?= $mode === 'signup' ? 'active' : '' ?>" href="<?= e(fta_lang_url($lang, 'signup.php')) ?>"><?= e(t('signup', 'Sign up')) ?></a>
                </div>
            <?php endif; ?>

            <?php foreach (fta_take_flashes() as $flash): ?>
                <div class="notice <?= e($flash['type'] ?? 'success') ?>">
                    <span><?= e($flash['message'] ?? '') ?></span>
                </div>
            <?php endforeach; ?>

            <?php if ($errorMessage !== ''): ?>
                <div class="notice error">
                    <strong><?= e(t('auth_error_title', 'Could not continue')) ?></strong>
                    <span><?= e($errorMessage) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($currentUser && $mode !== 'forgot'): ?>
                <div class="notice success">
                    <strong><?= e(t('already_logged_in', 'You are already logged in.')) ?></strong>
                    <span><?= e($currentUser['full_name'] ?? '') ?></span>
                </div>
                <a class="primary-btn wide" href="<?= e(fta_lang_url($lang)) ?>"><?= e(t('back_home', 'Back home')) ?></a>
            <?php elseif ($mode === 'forgot'): ?>
                <div class="forgot-panel">
                    <strong><?= e(t('forgot_admin_title', 'Ask admin to change your password')) ?></strong>
                    <p><?= e(t('forgot_admin_text', 'OTP reset is not enabled. Contact admin and request a password change.')) ?></p>
                    <a class="primary-btn wide" href="https://t.me/admfulltimearena" target="_blank" rel="noopener"><?= fta_icon('telegram') ?><span>t.me/admfulltimearena</span></a>
                    <a class="ghost-btn wide" href="<?= e(fta_lang_url($lang, 'login.php')) ?>"><?= e(t('login', 'Login')) ?></a>
                </div>
            <?php else: ?>
                <form method="post" class="auth-form" autocomplete="<?= $mode === 'signup' ? 'on' : 'off' ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                    <input type="hidden" name="location_lat" data-auth-location-lat>
                    <input type="hidden" name="location_lng" data-auth-location-lng>
                    <input type="hidden" name="location_accuracy" data-auth-location-accuracy>
                    <input type="hidden" name="location_label" data-auth-location-label>

                    <?php if ($mode === 'signup'): ?>
                        <label>
                            <span><?= fta_icon('person') ?><?= e(t('full_name', 'Full Name')) ?></span>
                            <input type="text" name="full_name" value="<?= e($values['full_name']) ?>" maxlength="140" autocomplete="name" required>
                        </label>

                        <label>
                            <span><?= fta_icon('ticket') ?><?= e(t('promo_code', 'Promocode')) ?></span>
                            <input type="text" name="promo_code" value="<?= e($values['promo_code']) ?>" maxlength="80" autocomplete="off" required>
                        </label>
                    <?php endif; ?>

                    <label>
                        <span><?= fta_icon('phone') ?><?= e(t('phone_number', 'Phone Number')) ?></span>
                        <div class="phone-row">
                            <select name="phone_country" required>
                                <?php foreach ($countries as $code => $country): ?>
                                    <option value="<?= e($code) ?>" <?= $values['phone_country'] === $code ? 'selected' : '' ?>>+<?= e($country['dial']) ?> <?= e($country['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="tel" name="phone_number" value="<?= e($values['phone_number']) ?>" inputmode="tel" autocomplete="tel" placeholder="09..." required>
                        </div>
                    </label>

                    <label>
                        <span><?= fta_icon('lock') ?><?= e(t('password', 'Password')) ?></span>
                        <input type="password" name="password" minlength="8" autocomplete="<?= $mode === 'signup' ? 'new-password' : 'current-password' ?>" required>
                    </label>

                    <?php if ($mode === 'signup'): ?>
                        <label>
                            <span><?= fta_icon('lock') ?><?= e(t('confirm_password', 'Confirm Password')) ?></span>
                            <input type="password" name="confirm_password" minlength="8" autocomplete="new-password" required>
                        </label>
                    <?php else: ?>
                        <label class="auth-remember">
                            <input type="checkbox" name="remember" value="1" <?= $values['remember'] === '1' ? 'checked' : '' ?>>
                            <span class="auth-remember-box"><?= fta_icon('check') ?></span>
                            <span class="auth-remember-copy"><?= e(t('remember_me', 'Remember me')) ?></span>
                        </label>
                    <?php endif; ?>

                    <button class="primary-btn wide" type="submit">
                        <?= fta_icon($mode === 'signup' ? 'person' : 'lock') ?>
                        <span><?= e($mode === 'signup' ? t('signup_now', 'Sign up now') : t('login', 'Login')) ?></span>
                    </button>
                </form>

                <div class="auth-links">
                    <?php if ($mode === 'signup'): ?>
                        <a href="<?= e(fta_lang_url($lang, 'login.php')) ?>"><?= e(t('already_have_account', 'Already have an account? Login')) ?></a>
                    <?php else: ?>
                        <a href="<?= e(fta_lang_url($lang, 'signup.php')) ?>"><?= e(t('signup_now', 'Sign up now')) ?></a>
                        <a href="<?= e(fta_lang_url($lang, 'forgot-password.php')) ?>"><?= e(t('forgot_password', 'Forgot password')) ?></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<script>
(() => {
    const form = document.querySelector('.auth-form');
    if (!form || !('geolocation' in navigator)) return;
    const lat = form.querySelector('[data-auth-location-lat]');
    const lng = form.querySelector('[data-auth-location-lng]');
    const accuracy = form.querySelector('[data-auth-location-accuracy]');
    const label = form.querySelector('[data-auth-location-label]');
    const save = (position) => {
        const coords = position.coords || {};
        if (lat) lat.value = String(coords.latitude || '');
        if (lng) lng.value = String(coords.longitude || '');
        if (accuracy) accuracy.value = String(coords.accuracy || '');
        if (label && coords.latitude && coords.longitude) {
            label.value = Number(coords.latitude).toFixed(5) + ', ' + Number(coords.longitude).toFixed(5);
        }
    };
    const requestLocation = () => {
        navigator.geolocation.getCurrentPosition(save, () => {}, {
            enableHighAccuracy: false,
            timeout: 8000,
            maximumAge: 300000
        });
    };
    if (navigator.permissions && navigator.permissions.query) {
        navigator.permissions.query({ name: 'geolocation' }).then((status) => {
            if (status.state !== 'denied') requestLocation();
        }).catch(requestLocation);
    } else {
        requestLocation();
    }
})();
</script>
<?php
fta_render_page_end();
?>
