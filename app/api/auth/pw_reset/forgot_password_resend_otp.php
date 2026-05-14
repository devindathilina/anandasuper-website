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

if (isRateLimited($db, $client_ip, 'cust_forgot_password_otp', 10, 60)) {
    $response['message'] = "Too many OTP requests. Please try again later.";
    echo json_encode($response);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (empty($data)) {
    $response['message'] = "Invalid JSON data provided.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_otp', false);
    echo json_encode($response);
    exit;
}

if (!isset($data['phone'], $data['customer_id']) || empty(trim($data['phone'])) || empty(trim($data['customer_id']))) {
    $response['message'] = "Phone number and customer ID are required.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_otp', false);
    echo json_encode($response);
    exit;
}

$phone = trim($data['phone']);
$customer_id = (int)$data['customer_id'];

$phone = preg_replace('/[^0-9]/', '', $phone);

if (!filter_var($customer_id, FILTER_VALIDATE_INT) || $customer_id <= 0) {
    $response['message'] = "Invalid Customer ID.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_otp', false);
    echo json_encode($response);
    exit;
}

if (strlen($phone) !== 10 || !preg_match('/^\d{10}$/', $phone)) {
    $response['message'] = "Phone number must be exactly 10 digits.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_otp', false);
    echo json_encode($response);
    exit;
}

$stmt = $db->prepare("SELECT customer_id, first_name FROM customers WHERE phone = ? AND customer_id = ? AND is_active = 1");
if (!$stmt) {
    $response['message'] = "Internal Server Error";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_otp', false);
    echo json_encode($response);
    exit;
}

$stmt->bind_param("si", $phone, $customer_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $stmt->close();
    $response['message'] = "Customer and phone number not found or not verified. Please register first.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_otp', false);
    echo json_encode($response);
    exit;
}

$stmt->bind_result($db_customer_id, $first_name);
$stmt->fetch();
$stmt->close();

$otp_rate_limit = isOTPRateLimited($db, $db_customer_id, true);
if ($otp_rate_limit['limited']) {
    $response['message'] = $otp_rate_limit['message'];
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_otp', false);
    echo json_encode($response);
    exit;
}

$stmt = $db->prepare("SELECT customer_password_log_id FROM customer_password_log
                      WHERE customer_id = ? AND success = 1 AND updated_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
if (!$stmt) {
    $response['message'] = "Internal Server Error";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_otp', false);
    echo json_encode($response);
    exit;
}

$stmt->bind_param("i", $customer_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();
    $response['message'] = "Password was recently reset. Please wait 1 hour before requesting another reset.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_otp', false);
    echo json_encode($response);
    exit;
}
$stmt->close();

$otp_code = rand(1000, 9999);

$stmt = $db->prepare("INSERT INTO customer_otp (customer_id, otp_code, is_verified, created_at, expired_at)
                      VALUES (?, ?, 0, NOW(), DATE_ADD(NOW(), INTERVAL 5 MINUTE))
                      ON DUPLICATE KEY UPDATE otp_code = VALUES(otp_code), is_verified = 0,
                      created_at = NOW(), expired_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE)");

if (!$stmt) {
    $response['message'] = "Internal Server Error";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_otp', false);
    echo json_encode($response);
    exit;
}

$stmt->bind_param("ii", $customer_id, $otp_code);
if (!$stmt->execute()) {
    $stmt->close();
    $response['message'] = "Internal Server Error";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_otp', false);
    echo json_encode($response);
    exit;
}
$stmt->close();

$sms_message = "Dear $first_name, your new password reset OTP code is: $otp_code. It is valid for 5 minutes. Regards, $app_name.";
if (!sendSMS($phone, $sms_message)) {
    $response['message'] = "SMS could not be sent. Please try again later.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password_otp', false);
    echo json_encode($response);
    exit;
}

logLoginAttempt($db, $client_ip, 'cust_forgot_password_otp', true);

$response['success'] = true;
$response['message'] = "A new password reset OTP has been sent to your phone.";
echo json_encode($response);
?>
