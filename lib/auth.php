<?php

declare(strict_types=1);

function admin_users(): array
{
    $path = HAYA_TOKU_STORAGE_DIR . '/admins.php';
    return file_exists($path) ? require $path : ['admin' => password_hash('hayatoku123', PASSWORD_DEFAULT)];
}

function attempt_login(string $loginId, string $password): bool
{
    $users = admin_users();
    if (!isset($users[$loginId])) {
        return false;
    }

    if (!password_verify($password, $users[$loginId])) {
        return false;
    }

    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_login_id'] = $loginId;
    return true;
}

function is_admin_logged_in(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}

function require_admin_login(): void
{
    if (!is_admin_logged_in()) {
        redirect_to('/admin/login.php');
    }
}

function logout_admin(): void
{
    $_SESSION = [];
    if (session_id() !== '') {
        session_destroy();
    }
}
