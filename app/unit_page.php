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
$user = fta_current_user();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $returnMode = 'deposit';
    try {
        fta_check_csrf();
        if (!$user) {
            throw new RuntimeException(t('auth_session_expired', 'Please login again.'));
        }

        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'submit_deposit') {
            $returnMode = 'deposit';
            $proofPath = fta_save_unit_proof_from_upload('proof');
            fta_submit_unit_request($user, 'deposit', $_POST, $proofPath);
            fta_flash('success', t('unit_request_submitted', 'Request submitted.'));
        }
        if ($action === 'submit_withdraw') {
            $returnMode = 'withdraw';
            fta_submit_unit_request($user, 'withdraw', $_POST);
            fta_flash('success', t('unit_request_submitted', 'Request submitted.'));
        }
    } catch (Throwable $error) {
        fta_flash('error', fta_user_safe_error_message($error));
    }
    fta_redirect(fta_lang_url($lang, 'unit.php') . '?mode=' . $returnMode);
}

$hasAgent = $user && !empty($user['agent_id']);
$gameAccounts = $hasAgent ? fta_user_game_accounts($user) : [];
$paymentAccounts = $hasAgent ? fta_agent_payment_accounts((int) $user['agent_id'], true) : [];
$hasGameAccounts = !empty($gameAccounts);
$hasPaymentAccounts = !empty($paymentAccounts);
$initialMode = (string) ($_GET['mode'] ?? 'deposit');
$initialMode = $initialMode === 'withdraw' ? 'withdraw' : 'deposit';

fta_render_head(t('unit', 'Unit'), 'public-page unit-page');
fta_render_public_header('unit');
?>
<main class="app-shell narrow">
    <section class="unit-hero">
        <div>
            <span><?= e(t('unit', 'Unit')) ?></span>
            <h1><?= e(t('unit_in_out', 'Unit In / Out')) ?></h1>
            <p><?= e(t('unit_intro', 'Deposit and withdraw with your agent-linked game account.')) ?></p>
        </div>
    </section>

    <?php foreach (fta_take_flashes() as $flash): ?>
        <div class="notice <?= e($flash['type'] ?? 'success') ?>"><?= e($flash['message'] ?? '') ?></div>
    <?php endforeach; ?>

    <?php if (!$hasAgent): ?>
        <section class="more-panel">
            <p class="empty-copy"><?= e(t('agent_required', 'Your account must be linked with a valid promocode.')) ?></p>
        </section>
    <?php else: ?>
        <section class="more-panel unit-mode-panel">
            <div class="compact-title unit-mode-title">
                <?= fta_icon($initialMode === 'deposit' ? 'wallet' : 'activity') ?>
                <h2><?= e($initialMode === 'deposit' ? t('unit_in', 'Deposit') : t('unit_out', 'Withdraw')) ?></h2>
            </div>
            <?php if ($initialMode === 'deposit'): ?>
                <form method="post" enctype="multipart/form-data" class="unit-form">
                    <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                    <input type="hidden" name="action" value="submit_deposit">
                    <label>
                        <span><?= e(t('game_account', 'Game account')) ?></span>
                        <select name="game_account_id" required>
                            <?php if (!$hasGameAccounts): ?>
                                <option value=""><?= e(t('no_game_account', 'No game account yet.')) ?></option>
                            <?php endif; ?>
                            <?php foreach ($gameAccounts as $account): ?>
                                <option value="<?= (int) $account['id'] ?>"><?= e($account['provider_label'] . ' / ' . $account['external_username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <div class="agent-payment-list">
                        <?php foreach ($paymentAccounts as $index => $account): ?>
                            <?php $channel = fta_payout_channels()[(string) $account['method']] ?? ['brand' => strtoupper((string) $account['method']), 'label' => ucfirst((string) $account['method'])]; ?>
                            <?php $logo = fta_payout_channel_logo((string) $account['method']); ?>
                            <label class="agent-payment-card">
                                <input type="radio" name="payment_account_id" value="<?= (int) $account['id'] ?>" <?= $index === 0 ? 'checked' : '' ?> required>
                                <span class="payment-brand-mark logo-mark">
                                    <?php if ($logo !== ''): ?>
                                        <img src="<?= e($logo) ?>" alt="<?= e($channel['label']) ?>">
                                    <?php else: ?>
                                        <?= e($channel['brand']) ?>
                                    <?php endif; ?>
                                </span>
                                <span>
                                    <strong><?= e($channel['label']) ?></strong>
                                    <small>
                                        <?= e($account['account_name']) ?> /
                                        <button class="copy-mini" type="button" data-copy="<?= e($account['account_number']) ?>"><?= e($account['account_number']) ?></button>
                                    </small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                        <?php if (!$hasPaymentAccounts): ?>
                            <p class="empty-copy"><?= e(t('no_payment_account', 'No deposit payment account from your agent yet.')) ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if (!$hasGameAccounts): ?>
                        <div class="empty-action-card">
                            <p><?= e(t('create_game_account_first', 'Create or connect a game account from More first.')) ?></p>
                            <a class="ghost-btn" href="<?= e(fta_lang_url($lang, 'more.php') . '#create-account-section') ?>"><?= e(t('create_account', 'Create account')) ?></a>
                        </div>
                    <?php endif; ?>
                    <label>
                        <span><?= e(t('amount', 'Amount')) ?></span>
                        <input type="number" name="amount" min="1" step="1" required>
                    </label>
                    <label>
                        <span><?= e(t('screenshot', 'Screenshot')) ?> (10MB)</span>
                        <input type="file" name="proof" accept="image/*" required>
                    </label>
                    <button class="primary-btn wide" type="submit" <?= (!$hasGameAccounts || !$hasPaymentAccounts) ? 'disabled' : '' ?>><?= e(t('submit', 'Submit')) ?></button>
                </form>
            <?php else: ?>
                <form method="post" class="unit-form">
                    <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
                    <input type="hidden" name="action" value="submit_withdraw">
                    <label>
                        <span><?= e(t('game_account', 'Game account')) ?></span>
                        <select name="game_account_id" required>
                            <?php if (!$hasGameAccounts): ?>
                                <option value=""><?= e(t('no_game_account', 'No game account yet.')) ?></option>
                            <?php endif; ?>
                            <?php foreach ($gameAccounts as $account): ?>
                                <option value="<?= (int) $account['id'] ?>"><?= e($account['provider_label'] . ' / ' . $account['external_username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <?php if (!$hasGameAccounts): ?>
                        <div class="empty-action-card">
                            <p><?= e(t('create_game_account_first', 'Create or connect a game account from More first.')) ?></p>
                            <a class="ghost-btn" href="<?= e(fta_lang_url($lang, 'more.php') . '#create-account-section') ?>"><?= e(t('create_account', 'Create account')) ?></a>
                        </div>
                    <?php endif; ?>
                    <label>
                        <span><?= e(t('amount', 'Amount')) ?></span>
                        <input type="number" name="amount" min="1" step="1" required>
                    </label>
                    <p class="warning-copy"><?= e(t('withdraw_payout_required', 'Withdrawal requires a saved Payout Account in More.')) ?></p>
                    <button class="primary-btn wide" type="submit" <?= !$hasGameAccounts ? 'disabled' : '' ?>><?= e(t('submit', 'Submit')) ?></button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
<?php
fta_render_footer_nav('unit');
fta_render_page_end();
