<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/admin_auth.php';

fta_start_session();
unset($_SESSION['admin_user_id']);
fta_logout_staff();
session_regenerate_id(true);
fta_redirect(fta_admin_url('login.php'));
