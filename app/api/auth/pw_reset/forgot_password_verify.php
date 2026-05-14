<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/api_functions.php';
require_once __DIR__ . '/../../../config/sms.php';
require_once __DIR__ . '/../../../config/auth_functions.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../../');
$dotenv->load();

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = "Invalid request method.";
    echo json_encode($response);
    exit;
}

$client_ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: '0.0.0.0';

if (isRateLimited($db, $client_ip, 'cust_forgot_password_verify', 5, 60)) {
    $response['message'] = "Too many verification attempts. Please try again later.";
    echo json_encode($response);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (empty($data)) {
    $response['message'] = "Invalid JSON data provided.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_verify', false);
    echo json_encode($response);
    exit;
}

if (!isset($data['otp_code'], $data['customer_id'], $data['phone'], $data['new_password'])) {
    $response['message'] = "Missing required fields.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_verify', false);
    echo json_encode($response);
    exit;
}

$otp_code = trim($data['otp_code']);
$customer_id = (int)$data['customer_id'];
$phone = trim($data['phone']);
$new_password = $data['new_password'];

if (!filter_var($customer_id, FILTER_VALIDATE_INT) || $customer_id <= 0) {
    $response['message'] = "Invalid customer.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_verify', false);
    echo json_encode($response);
    exit;
}

if (empty($otp_code) || !is_numeric($otp_code) || strlen($otp_code) !== 4) {
    $response['message'] = "Invalid OTP code format.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_verify', false);
    echo json_encode($response);
    exit;
}

if (strlen($new_password) < 6) {
    $response['message'] = "Password must be at least 6 characters long.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_verify', false);
    echo json_encode($response);
    exit;
}

if (strlen($new_password) > 255) {
    $response['message'] = "Invalid password.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_verify', false);
    echo json_encode($response);
    exit;
}

$phone = preg_replace('/[^0-9]/', '', $phone);

if (strlen($phone) !== 10 || !preg_match('/^\d{10}$/', $phone)) {
    $response['message'] = "Phone number must be exactly 10 digits.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_verify', false);
    echo json_encode($response);
    exit;
}

$stmt = $db->prepare("SELECT 1 FROM customer_otp WHERE customer_id = ? AND otp_code = ? AND is_verified = 0 AND NOW() <= expired_at");
if (!$stmt) {
    $response['message'] = "Internal Server Error";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_verify', false);
    echo json_encode($response);
    exit;
}

$stmt->bind_param("ii", $customer_id, $otp_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $response['message'] = "Invalid or expired OTP.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_verify', false);
    echo json_encode($response);
    exit;
}
$stmt->close();

$stmt = $db->prepare("SELECT phone, first_name FROM customers WHERE customer_id = ? AND is_active = 1");
if (!$stmt) {
    $response['message'] = "Internal Server Error";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_verify', false);
    echo json_encode($response);
    exit;
}

$stmt->bind_param("i", $customer_id);
$stmt->execute();
$customer_result = $stmt->get_result();

if ($customer_result->num_rows === 0) {
    $stmt->close();
    $response['message'] = "Customer not found or not verified.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_verify', false);
    echo json_encode($response);
    exit;
}

$customer_data = $customer_result->fetch_assoc();
$stmt->close();

if ($phone !== $customer_data['phone']) {
    $response['message'] = "Phone number does not match our records.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_verify', false);
    echo json_encode($response);
    exit;
}

$db->begin_transaction();

try {
    $password_hashed = password_hash($new_password, PASSWORD_DEFAULT);

    $stmt = $db->prepare("UPDATE customers SET password = ?, session_token = NULL WHERE customer_id = ?");
    if (!$stmt) {
        throw new Exception("Internal Server Error");
    }

    $stmt->bind_param("si", $password_hashed, $customer_id);
    if (!$stmt->execute()) {
        throw new Exception("Internal Server Error");
    }
    $stmt->close();

    $stmt = $db->prepare("UPDATE customer_otp SET is_verified = 1 WHERE customer_id = ? AND otp_code = ?");
    if (!$stmt) {
        throw new Exception("Internal Server Error");
    }

    $stmt->bind_param("ii", $customer_id, $otp_code);
    if (!$stmt->execute()) {
        throw new Exception("Internal Server Error");
    }
    $stmt->close();

    $stmt = $db->prepare("INSERT INTO customer_password_log (customer_id, success, ip_address, created_at, updated_at)
                          VALUES (?, 1, ?, NOW(), NOW())");
    if (!$stmt) {
        throw new Exception("Internal Server Error");
    }

    $stmt->bind_param("is", $customer_id, $client_ip);
    if (!$stmt->execute()) {
        throw new Exception("Internal Server Error");
    }
    $stmt->close();

    $db->commit();

    $sms_message = "Dear {$customer_data['first_name']}, your password has been successfully reset. Regards, $app_name.";
    if (sendSMS($phone, $sms_message)) {
        logLoginAttempt($db, $client_ip, 'cust_forgot_password_verify', true);
    } else {
        logLoginAttempt($db, $client_ip, 'cust_forgot_password_verify', true);
    }

    $response['success'] = true;
    $response['message'] = "Password reset successful. You can now login with your new password.";

} catch (Exception $e) {
    $db->rollback();

    $stmt = $db->prepare("INSERT INTO customer_password_log (customer_id, success, ip_address, created_at, updated_at)
                          VALUES (?, 0, ?, NOW(), NOW())");
    if ($stmt) {
        $stmt->bind_param("is", $customer_id, $client_ip);
        $stmt->execute();
        $stmt->close();
    }

    $response['message'] = "Password reset failed. Please try again.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_verify', false);
}

echo json_encode($response);
?>
