<?php
// Centralized secure session initializer
// Usage: require_once 'session.php'; (instead of calling session_start() directly)

// Simple configuration
$SESSION_TIMEOUT = 1800; // 30 minutes inactivity
$REGENERATE_INTERVAL = 300; // regenerate id every 5 minutes

// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Cookie security
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $domain = $_SERVER['HTTP_HOST'] ?? '';

    // Prefer samesite support when available (PHP 7.3+)
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        // Fallback for older PHP
        session_set_cookie_params(0, '/', $domain, $secure, true);
        ini_set('session.cookie_httponly', 1);
    }

    // Harden session behavior
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.gc_maxlifetime', (string)$SESSION_TIMEOUT);
    session_name('bautista1_sid');
    session_start();
}

// Helper: compute request fingerprint to mitigate session hijack
// On Heroku/proxies, use X-Forwarded-For header if available (more reliable than REMOTE_ADDR)
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    // X-Forwarded-For can contain multiple IPs; use the first one (client's IP)
    $forwarded_ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
    $client_ip = $forwarded_ips[0] ?? $client_ip;
}

$__session_fingerprint = hash('sha256',
    $client_ip . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '')
);

// If fingerprint exists and doesn't match, force logout
if (isset($_SESSION['fingerprint']) && $_SESSION['fingerprint'] !== $__session_fingerprint) {
    // Clear and destroy session
    $_SESSION = [];
    if (session_status() !== PHP_SESSION_NONE) session_destroy();

    // Return 401 for AJAX, otherwise redirect to login
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session invalid']);
        exit;
    } else {
        header('Location: login.php');
        exit;
    }
}

// Inactivity timeout
if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > $SESSION_TIMEOUT) {
    $_SESSION = [];
    if (session_status() !== PHP_SESSION_NONE) session_destroy();

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired']);
        exit;
    } else {
        header('Location: login.php?expired=1');
        exit;
    }
}

// Regenerate session id periodically to reduce fixation risk
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
}
if ((time() - (int)$_SESSION['created']) > $REGENERATE_INTERVAL) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// Update last activity every request
$_SESSION['last_activity'] = time();

// Expose a small helper to set fingerprint when logging in
if (!function_exists('session_set_fingerprint')) {
    function session_set_fingerprint() {
        if (session_status() !== PHP_SESSION_ACTIVE) return;
        $_SESSION['fingerprint'] = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $_SESSION['created'] = time();
        $_SESSION['last_activity'] = time();
    }
}

?>