<?php
if (!defined('ANANDA_SUPER_SECURE_ACCESS')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

if (!defined('ADMIN_SECURITY_HEADERS_INCLUDED')) {
    define('ADMIN_SECURITY_HEADERS_INCLUDED', true);
}

require_once __DIR__ . '/../../vendor/autoload.php';

if (!isset($_ENV['ALLOWED_DOMAINS'])) {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 3));
    $dotenv->load();
}

function setSecurityHeaders($type = 'admin') {
    if (headers_sent()) {
        return;
    }
    
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cross-Origin-Opener-Policy: same-origin');
    
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
    
    if ($type === 'admin') {
        header("Content-Security-Policy: object-src 'none'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https: data:; style-src 'self' 'unsafe-inline' https: data:; img-src 'self' data: https: http:; font-src 'self' https: data:; connect-src 'self' https: wss:; frame-src 'self' https:; manifest-src 'self'; base-uri 'self'; form-action 'self';");
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    } elseif ($type === 'api') {
        header("Content-Security-Policy: default-src 'none'; script-src 'none'; style-src 'none'; img-src 'none'; connect-src 'none'; font-src 'none'; object-src 'none'; media-src 'none'; frame-src 'none'; base-uri 'none'; form-action 'none'; require-trusted-types-for 'script';");
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}


function validateOrigin($allowedOrigins = []) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (!isset($_ENV['ALLOWED_DOMAINS'])) {
        http_response_code(500);
        die('Server Error');
    }
    $allowedDomains = array_map('trim', explode(',', $_ENV['ALLOWED_DOMAINS']));
    $defaultAllowed = [];
    foreach ($allowedDomains as $domain) {
        $defaultAllowed[] = 'https://' . $domain;
        $defaultAllowed[] = 'http://' . $domain;
    }
    $allowedOrigins = array_merge($defaultAllowed, $allowedOrigins);
    if (empty($origin)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }
    $originDomain = parse_url($origin, PHP_URL_HOST);
    $isAllowed = false;
    foreach ($allowedOrigins as $allowed) {
        $allowedDomain = parse_url($allowed, PHP_URL_HOST);
        if ($originDomain === $allowedDomain) {
            $isAllowed = true;
            break;
        }
    }
    if (!$isAllowed) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Origin not allowed']);
        exit;
    }
    if (in_array($origin, $allowedOrigins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    }
}
?>