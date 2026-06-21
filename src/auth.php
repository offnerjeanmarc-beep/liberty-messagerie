<?php
/**
 * Authentification admin (session) + protection CSRF.
 * À inclure dans les pages web après bootstrap.php.
 */

declare(strict_types=1);

function auth_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        ]);
        session_start();
    }
}

function auth_check_login(string $user, string $pass): bool
{
    $admin = $GLOBALS['CONFIG']['admin'];
    if (!hash_equals($admin['user'], $user)) {
        return false;
    }
    return password_verify($pass, $admin['password_hash']);
}

function auth_login(string $user): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $user;
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function auth_is_logged(): bool
{
    return !empty($_SESSION['uid']);
}

/** Redirige vers login si non connecté. */
function auth_require(): void
{
    auth_start();
    if (!auth_is_logged()) {
        header('Location: login.php');
        exit;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(?string $token): bool
{
    return !empty($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}
