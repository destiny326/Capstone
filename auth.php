<?php
// ============================================================
// auth.php — Session Guard & Role Enforcement
// Include at the top of every protected page.
// ============================================================

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Require a logged-in student/staff (Microsoft login).
 * Redirects to login page if not authenticated.
 */
function requireUserLogin(): void {
    if (
        empty($_SESSION['logged_in']) ||
        empty($_SESSION['auth_type']) ||
        $_SESSION['auth_type'] !== 'microsoft'
    ) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit;
    }
    // Regenerate session ID periodically to prevent fixation
    if (empty($_SESSION['last_regenerated']) || time() - $_SESSION['last_regenerated'] > 300) {
        session_regenerate_id(true);
        $_SESSION['last_regenerated'] = time();
    }
}

/**
 * Require a logged-in admin.
 * Redirects to admin login page if not authenticated as admin.
 */
function requireAdminLogin(string $requiredRole = 'admin'): void {
    if (
        empty($_SESSION['logged_in']) ||
        empty($_SESSION['auth_type']) ||
        $_SESSION['auth_type'] !== 'database'
    ) {
        header('Location: admin_login.php');
        exit;
    }
    if ($requiredRole === 'admin' && ($_SESSION['admin_role'] ?? '') !== 'admin') {
        http_response_code(403);
        die('<h1>403 Forbidden</h1><p>Insufficient privileges.</p>');
    }
}

/**
 * Allow both kitchen staff and admins.
 */
function requireKitchenOrAdmin(): void {
    if (
        empty($_SESSION['logged_in']) ||
        empty($_SESSION['auth_type']) ||
        $_SESSION['auth_type'] !== 'database'
    ) {
        header('Location: admin_login.php');
        exit;
    }
}

/**
 * Generate Microsoft OAuth2 authorization URL.
 */
function getMicrosoftLoginUrl(): string {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

    $params = http_build_query([
        'client_id'     => AZURE_CLIENT_ID,
        'response_type' => 'code',
        'redirect_uri'  => AZURE_REDIRECT_URI,
        'response_mode' => 'query',
        'scope'         => AZURE_SCOPES,
        'state'         => $state,
    ]);

    return AZURE_AUTHORITY . '/oauth2/v2.0/authorize?' . $params;
}

/**
 * Destroy session and log out.
 */
function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}
