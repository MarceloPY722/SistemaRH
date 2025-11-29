<?php
// Session configuration compatible con ngrok y segura
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    @session_start();
}

// Encabezados de seguridad (solo si no se enviaron aún)
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');

    if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'ngrok') !== false) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }
}

function debug_session() {
    return [
        'session_id' => session_id(),
        'session_name' => session_name(),
        'cookie_domain' => ini_get('session.cookie_domain'),
        'cookie_path' => ini_get('session.cookie_path'),
        'server_name' => $_SERVER['SERVER_NAME'] ?? 'not set',
        'http_host' => $_SERVER['HTTP_HOST'] ?? 'not set',
        'https' => isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : 'not set'
    ];
}
?>