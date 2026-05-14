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

if (isRateLimited($db, $client_ip, 'cust_forgot_password', 10, 60)) {
    $response['message'] = "Too many password reset attempts. Please try again later.";
    echo json_encode($response);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (empty($data)) {
    $response['message'] = "Invalid JSON data provided.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password', false);
    echo json_encode($response);
    exit;
}

if (!isset($data['phone']) || empty(trim($data['phone']))) {
    $response['message'] = "Phone number is required.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password', false);
    echo json_encode($response);
    exit;
}

$phone_raw = trim($data['phone']);
$phone = preg_replace('/[^0-9]/', '', $phone_raw);

if (strlen($phone) !== 10 || !preg_match('/^\d{10}$/', $phone)) {
    $response['message'] = "Phone number must be exactly 10 digits.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password', false);
    echo json_encode($response);
    exit;
}

$stmt = $db->prepare("SELECT customer_id, first_name, is_active FROM customers WHERE phone = ?");
if (!$stmt) {
    $response['message'] = "Internal Server Error";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password', false);
    echo json_encode($response);
    exit;
}

$stmt->bind_param("s", $phone);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $stmt->close();
    $response['message'] = "Phone number not found. Please register first.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password', false);
    echo json_encode($response);
    exit;
}

$stmt->bind_result($customer_id, $first_name, $is_active);
$stmt->fetch();
$stmt->close();

$otp_rate_limit = isOTPRateLimited($db, $customer_id, false);
if ($otp_rate_limit['limited']) {
    $response['message'] = $otp_rate_limit['message'];
    logLoginAttempt($db, $client_ip, 'cust_forgot_password', false);
    echo json_encode($response);
    exit;
}

if ($is_active != 1) {
    $response['message'] = "Account not verified. Please verify your account first.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password', false);
    echo json_encode($response);
    exit;
}

$stmt = $db->prepare("SELECT customer_password_log_id FROM customer_password_log
                      WHERE customer_id = ? AND success = 1 AND updated_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
if (!$stmt) {
    $response['message'] = "Internal Server Error";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password', false);
    echo json_encode($response);
    exit;
}

$stmt->bind_param("i", $customer_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();
    $response['message'] = "Password was recently reset. Please wait 1 hour before requesting another reset.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password', false);
    echo json_encode($response);
    exit;
}
$stmt->close();

$db->begin_transaction();

$otp_code = rand(1000, 9999);
$otp_is_verified = 0;

$stmt = $db->prepare("INSERT INTO customer_otp (customer_id, otp_code, is_verified, created_at, expired_at)
                      VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 5 MINUTE))
                      ON DUPLICATE KEY UPDATE otp_code = VALUES(otp_code), is_verified = 0,
                      created_at = NOW(), expired_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE)");

if (!$stmt) {
    $db->rollback();
    $response['message'] = "Internal Server Error";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password', false);
    echo json_encode($response);
    exit;
}

$stmt->bind_param("iii", $customer_id, $otp_code, $otp_is_verified);
if (!$stmt->execute()) {
    $stmt->close();
    $db->rollback();
    $response['message'] = "Internal Server Error";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password', false);
    echo json_encode($response);
    exit;
}
$stmt->close();

$sms_message = "Dear $first_name, your password reset OTP code is: $otp_code. It is valid for 5 minutes. Regards, $app_name.";
if (!sendSMS($phone, $sms_message)) {
    $db->rollback();
    $response['message'] = "SMS could not be sent. Please try again later.";
    logLoginAttempt($db, $client_ip, 'cust_forgot_password', false);
    echo json_encode($response);
    exit;
}

$db->commit();

logLoginAttempt($db, $client_ip, 'cust_forgot_password', true);

$response['success'] = true;
$response['message'] = [
    "status" => "Password reset OTP sent.",
    "detail" => "Please check your SMS for the OTP code.",
    "customer_id" => $customer_id,
    "phone" => $phone
];
echo json_encode($response);
?>
