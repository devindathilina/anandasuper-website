<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/api_functions.php';
require_once __DIR__ . '/../../../config/jwt_functions.php';
require_once __DIR__ . '/../../../config/auth_functions.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../../');
$dotenv->load();

$secret_key = $_ENV['JWT_SECRET_KEY'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendAuthResponse(false, "Invalid request method.", null, 405);
}

$client_ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: '0.0.0.0';

if (isRateLimited($db, $client_ip, 'cust_token_refresh', 10, 60)) {
    sendAuthResponse(false, "Too many token refresh attempts. Please try again later.", null, 429);
}

$inputData = json_decode(file_get_contents('php://input'), true);
if (empty($inputData['session_token'])) {
    logLoginAttempt($db, $client_ip, 'cust_token_refresh', false);
    sendAuthResponse(false, "Session token is required.", null, 400);
}

$session_token = $inputData['session_token'];
$onesignal_player_id = isset($inputData['onesignal_player_id']) ? trim($inputData['onesignal_player_id']) : null;

$result = refreshCustomerToken($session_token, $db, $secret_key, $onesignal_player_id);

if ($result['success']) {
    logLoginAttempt($db, $client_ip, 'cust_token_refresh', true);
    sendAuthResponse(true, $result['message'], [
        'session_token' => $result['session_token']
    ]);
} else {
    logLoginAttempt($db, $client_ip, 'cust_token_refresh', false);
    sendAuthResponse(false, $result['message'], null, 401);
}
?>
