<?php
// ============================================================
//  H.A.P.A.G. — Auth & Session Helpers
//  includes/auth.php
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400 * 7,   // 7 days
        'path'     => '/',
        'secure'   => false,        // set true on HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Returns current logged-in user array or null.
 */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Returns true when a user is logged in.
 */
function is_logged_in(): bool {
    return isset($_SESSION['user']['id']);
}

/**
 * Returns true when the logged-in user is an admin.
 */
function is_admin(): bool {
    return is_logged_in() && !empty($_SESSION['user']['is_admin']);
}

/**
 * Log a user in and store in session.
 */
function login_user(array $user): void {
    // Never store the password hash in session
    unset($user['password_hash']);
    $_SESSION['user'] = $user;
    session_regenerate_id(true);
}

/**
 * Log out and destroy session.
 */
function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Redirect to login page if not authenticated.
 * Pass $admin=true to also require admin role.
 */
function require_login(bool $admin = false): void {
    if (!is_logged_in()) {
        header('Location: /hapag/index.php?auth=login#register');
        exit;
    }
    if ($admin && !is_admin()) {
        http_response_code(403);
        exit('Access denied.');
    }
}
