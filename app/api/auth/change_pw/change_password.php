<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/api_functions.php';
require_once __DIR__ . '/../../../config/jwt_functions.php';
require_once __DIR__ . '/../../../config/auth_functions.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../../');
$dotenv->load();

const ERRORS = [
    'invalid_method' => 'Invalid request.',
    'invalid_json' => 'Invalid request.',
    'missing_token' => 'Missing session token.',
    'missing_current_password' => 'Current password is required.',
    'missing_new_password' => 'New password is required.',
    'invalid_password_length' => 'New password must be at least 6 characters long.',
    'same_password' => 'New password cannot be the same as current password.',
    'auth_failed' => 'Invalid or expired session token.',
    'customer_not_found' => 'Customer not found.',
    'incorrect_password' => 'Current password is incorrect.',
    'server_error' => 'Internal server error.'
];

function sendResponse($data, $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function handleError($errorKey, $customMessage = null, $httpCode = 400) {
    $message = $customMessage ?? ERRORS[$errorKey] ?? ERRORS['server_error'];
    sendResponse(['success' => false, 'message' => $message, 'data' => null], $httpCode);
}

function validateInput($data) {
    if (empty($data)) handleError('invalid_json');
    if (empty($data['session_token'])) handleError('missing_token');
    if (!isset($data['current_password']) || trim($data['current_password']) === '') handleError('missing_current_password');
    if (!isset($data['new_password']) || trim($data['new_password']) === '') handleError('missing_new_password');

    $currentPassword = trim($data['current_password']);
    $newPassword = trim($data['new_password']);

    if (strlen($newPassword) < 6) handleError('invalid_password_length');
    if ($currentPassword === $newPassword) handleError('same_password');

    return [
        'session_token' => $data['session_token'],
        'current_password' => $currentPassword,
        'new_password' => $newPassword
    ];
}

function executeQuery($db, $sql, $params = [], $types = '') {
    $stmt = $db->prepare($sql);
    if ($stmt === false) return false;

    if (!empty($params) && !$stmt->bind_param($types, ...$params)) {
        $stmt->close();
        return false;
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }

    $result = $stmt->get_result();
    $stmt->close();
    return $result;
}

function fetchCustomerPassword($db, $customerId) {
    $sql = "SELECT password FROM customers WHERE customer_id = ? LIMIT 1";
    return executeQuery($db, $sql, [$customerId], 'i');
}

function updatePassword($db, $customerId, $newPasswordHash) {
    $stmt = $db->prepare("UPDATE customers SET password = ? WHERE customer_id = ?");
    if (!$stmt) return false;

    $stmt->bind_param('si', $newPasswordHash, $customerId);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function logPasswordChange($db, $customerId, $ipAddress) {
    $stmt = $db->prepare("INSERT INTO customer_password_log (customer_id, success, ip_address) VALUES (?, 1, ?)");
    if (!$stmt) return false;

    $stmt->bind_param('is', $customerId, $ipAddress);
    $stmt->execute();
    $stmt->close();
    return true;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    handleError('invalid_method');
}

$client_ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: '0.0.0.0';

if (isRateLimited($db, $client_ip, 'cust_change_password', 5, 60)) {
    sendResponse(['success' => false, 'message' => 'Too many password change attempts. Please try again later.', 'data' => null]);
}

$data = json_decode(file_get_contents('php://input'), true);
$input = validateInput($data);

$auth_result = validateCustomerAuthentication($input['session_token'], $db, $_ENV['JWT_SECRET_KEY']);
if (!$auth_result['success']) {
    logLoginAttempt($db, $client_ip, 'cust_change_password', false);
    handleError('auth_failed', $auth_result['message'], 401);
}

$customerId = (int)$auth_result['customer_id'];

$result = fetchCustomerPassword($db, $customerId);
if ($result === false) {
    logLoginAttempt($db, $client_ip, 'cust_change_password', false);
    handleError('server_error', null, 500);
}

$customer = $result->fetch_assoc();
if (!$customer) {
    logLoginAttempt($db, $client_ip, 'cust_change_password', false);
    handleError('customer_not_found');
}

if (!password_verify($input['current_password'], $customer['password'])) {
    logLoginAttempt($db, $client_ip, 'cust_change_password', false);
    handleError('incorrect_password');
}

$db->begin_transaction();

try {
    $newPasswordHash = password_hash($input['new_password'], PASSWORD_DEFAULT);

    if (!updatePassword($db, $customerId, $newPasswordHash)) {
        throw new Exception('Failed to update password');
    }

    logPasswordChange($db, $customerId, $client_ip);

    $db->commit();
    logLoginAttempt($db, $client_ip, 'cust_change_password', true);

    sendResponse([
        'success' => true,
        'message' => 'Password changed successfully.',
        'data' => null
    ]);

} catch (Exception $e) {
    $db->rollback();
    logLoginAttempt($db, $client_ip, 'cust_change_password', false);
    handleError('server_error', null, 500);
}
?>
