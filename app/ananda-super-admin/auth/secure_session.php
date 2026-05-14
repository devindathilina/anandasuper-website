<?php
if (!defined('ANANDA_SUPER_SECURE_ACCESS')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

if (!defined('SECURE_SESSION_INCLUDED')) {
    define('SECURE_SESSION_INCLUDED', true);
}

function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax'
        ]);
        return session_start();
    }
    return true;
}
?>