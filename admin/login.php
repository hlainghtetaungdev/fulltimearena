<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/admin_auth.php';

try {
    if (fta_admin_is_logged_in()) {
        fta_redirect(fta_admin_url());
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        fta_check_csrf();
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $staff = fta_staff_login($username, $password, ['super']);
        if ($staff) {
            fta_login_staff($staff);
            fta_redirect(fta_admin_url());
        }

        $statement = db()->prepare('SELECT * FROM admin_users WHERE username = :username LIMIT 1');
        $statement->execute(['username' => $username]);
        $user = $statement->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_user_id'] = (int) $user['id'];
            fta_redirect(fta_admin_url());
        }

        fta_flash('error', 'Invalid username or password.');
        fta_redirect(fta_admin_url('login.php'));
    }
} catch (Throwable $error) {
    require_once __DIR__ . '/../app/layout.php';
    fta_render_db_error($error);
    exit;
}

$flashes = fta_take_flashes();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login | <?= e(FTA_APP_NAME) ?></title>
    <link rel="icon" href="<?= e(fta_logo_url()) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(fta_base_url('assets/css/styles.css')) ?>">
</head>
<body class="admin-login-page">
    <main class="login-card">
        <img src="<?= e(fta_logo_url()) ?>" alt="" class="login-logo">
        <h1><?= e(FTA_APP_NAME) ?></h1>
        <p>Super Login</p>

        <?php foreach ($flashes as $flash): ?>
            <div class="notice <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>

        <form method="post" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?= e(fta_csrf_token()) ?>">
            <label>
                <span>Username</span>
                <input type="text" name="username" required autocomplete="username" autofocus>
            </label>
            <label>
                <span>Password</span>
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <button class="primary-btn wide" type="submit">Login</button>
        </form>
        <small>First super login: <strong>admin</strong> / <strong>FullTime@123</strong>. Change it after logging in.</small>
    </main>
</body>
</html>
