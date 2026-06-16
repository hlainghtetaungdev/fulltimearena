<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function fta_admin_user(): ?array
{
    fta_start_session();
    $staff = fta_current_staff(['super']);
    if ($staff) {
        return [
            'id' => (int) $staff['id'],
            'username' => (string) $staff['username'],
            'display_name' => fta_staff_display_name($staff),
            'role' => 'super',
            'staff' => $staff,
        ];
    }

    if (!empty($_SESSION['admin_user_id'])) {
        $statement = db()->prepare('SELECT id, username FROM admin_users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => (int) $_SESSION['admin_user_id']]);
        $user = $statement->fetch();
        if ($user) {
            return [
                'id' => (int) $user['id'],
                'username' => (string) $user['username'],
                'display_name' => (string) $user['username'],
                'role' => 'legacy_admin',
            ];
        }
    }

    return null;
}

function fta_admin_is_logged_in(): bool
{
    return fta_admin_user() !== null;
}

function fta_require_admin(): void
{
    if (!fta_admin_is_logged_in()) {
        fta_redirect(fta_admin_url('login.php'));
    }
}
