<?php
if (!defined('ANANDA_SUPER_SECURE_ACCESS')) {
    http_response_code(403);
    exit('Direct access not allowed');
}
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secret_key = $_ENV['JWT_SECRET_KEY'];

function generateJWT($customer_id, $phone, $secret_key) {
    $issued_at = time();
    $expiration = $issued_at + (30 * 24 * 60 * 60);
    $payload = [
        "customer_id" => $customer_id,
        "phone" => $phone,
        "iat" => $issued_at,
        "exp" => $expiration
    ];
    return JWT::encode($payload, $secret_key, 'HS256');
}

function verifyJWT($token, $secret_key)
{
    try {
        $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
        return (array) $decoded;
    } catch (Exception $e) {
        return null;
    }
}

?>