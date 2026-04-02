<?php

declare(strict_types=1);

/**
 * Standalone session-based authentication for the SEO admin panel.
 * Credentials are read from environment variables ADMIN_USER / ADMIN_PASSWORD.
 */

if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    session_name('seo_admin');
    session_start();
}

function getAdminUser(): string {
    return getenv('ADMIN_USER') ?: 'admin';
}

function getAdminPassword(): string {
    return getenv('ADMIN_PASSWORD') ?: 'admin';
}

function isAuthenticated(): bool {
    return !empty($_SESSION['seo_authenticated']);
}

function requireAuth(): void {
    if (!isAuthenticated()) {
        header('Location: /login.php');
        exit;
    }
}

function requireAuthApi(): void {
    if (!isAuthenticated()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
}

function attemptLogin(string $user, string $password): bool {
    if ($user === getAdminUser() && $password === getAdminPassword()) {
        session_regenerate_id(true);
        $_SESSION['seo_authenticated'] = true;
        $_SESSION['seo_user'] = $user;
        $_SESSION['seo_login_time'] = time();
        return true;
    }
    return false;
}

function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
