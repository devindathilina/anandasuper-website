<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/api_functions.php';
require_once __DIR__ . '/../../../config/sms.php';
require_once __DIR__ . '/../../../config/auth_functions.php';


header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = "Invalid request method.";
    echo json_encode($response);
    exit;
}

$client_ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: '0.0.0.0';

if (isRateLimited($db, $client_ip, 'cust_otp', 10, 60)) {
    $response['message'] = "Too many registration attempts. Please try again later.";
    echo json_encode($response);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (empty($data)) {
    $response['message'] = "Invalid JSON data provided.";
    logLoginAttempt($db, $client_ip, 'cust_otp', false);
    echo json_encode($response);
    exit;
}

$first_name = trim($data['first_name'] ?? '');
$last_name = trim($data['last_name'] ?? '');
$phone_raw = trim($data['phone'] ?? '');
$password = $data['password'] ?? '';
$terms_agreed = (int)($data['terms_agreed'] ?? 0);

$phone = preg_replace('/[^0-9]/', '', $phone_raw);

$errors = [];

if (empty($first_name) || empty($last_name) || empty($phone) || empty($password)) {
    $errors[] = 'Please fill in all required fields!';
}
if (strlen($first_name) < 2) {
    $errors[] = 'First name must be at least 2 characters long!';
}
if (strlen($first_name) > 100) {
    $errors[] = 'First name cannot exceed 100 characters!';
}
if (strlen($last_name) < 2) {
    $errors[] = 'Last name must be at least 2 characters long!';
}
if (strlen($last_name) > 100) {
    $errors[] = 'Last name cannot exceed 100 characters!';
}
if (strlen($phone) !== 10 || !preg_match('/^\d{10}$/', $phone)) {
    $errors[] = 'Phone number must be exactly 10 digits!';
}
if (strlen($password) < 6) {
    $errors[] = 'Password must be at least 6 characters long!';
}
if (!filter_var($terms_agreed, FILTER_VALIDATE_INT) || $terms_agreed !== 1) {
    $errors[] = 'You must agree to the terms to register!';
}

if (!empty($errors)) {
    $error_message = implode(' ', $errors);
    $response['message'] = $error_message;
    logLoginAttempt($db, $client_ip, 'cust_otp', false);
    echo json_encode($response);
    exit;
}

$stmt = $db->prepare("SELECT customer_id FROM customers WHERE phone = ?");
if (!$stmt) {
    $response['message'] = "Internal Server Error";
    logLoginAttempt($db, $client_ip, 'cust_otp', false);
    echo json_encode($response);
    exit;
}
$stmt->bind_param("s", $phone);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    $response['message'] = "Phone number is already registered.";
    logLoginAttempt($db, $client_ip, 'cust_otp', false);
    echo json_encode($response);
    exit;
}
$stmt->close();

$db->begin_transaction();

$password_hashed = password_hash($password, PASSWORD_DEFAULT);
$is_active = 0;

$stmt = $db->prepare("INSERT INTO customers (first_name, last_name, phone, password, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
if (!$stmt) {
    $db->rollback();
    $response['message'] = "Internal Server Error";
    logLoginAttempt($db, $client_ip, 'cust_otp', false);
    echo json_encode($response);
    exit;
}
$stmt->bind_param("ssssi", $first_name, $last_name, $phone, $password_hashed, $is_active);
if (!$stmt->execute()) {
    $stmt->close();
    $db->rollback();
    $response['message'] = "Internal Server Error";
    logLoginAttempt($db, $client_ip, 'cust_otp', false);
    echo json_encode($response);
    exit;
}
$customer_id = $db->insert_id;
$stmt->close();

$otp_rate_limit = isOTPRateLimited($db, $customer_id, false);
if ($otp_rate_limit['limited']) {
    $db->rollback();
    $response['message'] = $otp_rate_limit['message'];
    logLoginAttempt($db, $client_ip, 'cust_otp', false);
    echo json_encode($response);
    exit;
}

$otp_code = rand(1000, 9999);
$otp_is_verified = 0;

$stmt = $db->prepare("INSERT INTO customer_otp (customer_id, otp_code, is_verified, created_at, expired_at) VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 5 MINUTE))");
if (!$stmt) {
    $db->rollback();
    $response['message'] = "Internal Server Error";
    logLoginAttempt($db, $client_ip, 'cust_otp', false);
    echo json_encode($response);
    exit;
}
$stmt->bind_param("iii", $customer_id, $otp_code, $otp_is_verified);
if (!$stmt->execute()) {
    $stmt->close();
    $db->rollback();
    $response['message'] = "Internal Server Error";
    logLoginAttempt($db, $client_ip, 'cust_otp', false);
    echo json_encode($response);
    exit;
}
$stmt->close();


$sms_message = "Dear $first_name, your OTP code is: $otp_code. It is valid for 5 minutes. Regards, $app_name.";
if (!sendSMS($phone, $sms_message)) {
    $db->rollback();
    $response['message'] = "SMS could not be sent. Please try again later.";
    logLoginAttempt($db, $client_ip, 'cust_otp', false);
    echo json_encode($response);
    exit;
}

$db->commit();

logLoginAttempt($db, $client_ip, 'cust_otp', true);

$response['success'] = true;
$response['message'] = [
    "status" => "Registration successful.",
    "detail" => "Please check your SMS for the OTP code.",
    "customer_id" => $customer_id,
    "phone" => $phone
];
echo json_encode($response);
?>
