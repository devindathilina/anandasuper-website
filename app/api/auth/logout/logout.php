<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/api_functions.php';
require_once __DIR__ . '/../../../config/jwt_functions.php';
require_once __DIR__ . '/../../../config/auth_functions.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../../');
$dotenv->load();

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'data' => null];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = "Invalid request method.";
    echo json_encode($response);
    exit;
}


$data = json_decode(file_get_contents('php://input'), true);
if (empty($data)) {
    $response['message'] = "Invalid JSON data provided.";
    echo json_encode($response);
    exit;
}

if (empty($data['session_token'])) {
    $response['message'] = "Session token is required.";
    echo json_encode($response);
    exit;
}

$session_token = $data['session_token'];

$auth_result = validateCustomerAuthentication($session_token, $db, $_ENV['JWT_SECRET_KEY']);
if (!$auth_result['success']) {
    $response['message'] = $auth_result['message'];
    echo json_encode($response);
    exit;
}

$customer_id = $auth_result['customer_id'];

$updateStmt = $db->prepare("UPDATE customers SET session_token = NULL, onesignal_player_id = NULL WHERE customer_id = ? AND session_token = ?");
if (!$updateStmt) {
    $response['message'] = "Internal server error.";
    echo json_encode($response);
    exit;
}

$updateStmt->bind_param("is", $customer_id, $session_token);
if (!$updateStmt->execute()) {
    $updateStmt->close();
    $response['message'] = "Internal server error.";
    echo json_encode($response);
    exit;
}

$affected_rows = $updateStmt->affected_rows;
$updateStmt->close();

if ($affected_rows === 0) {
    $response['message'] = "Invalid session token or customer already logged out.";
    echo json_encode($response);
    exit;
}

$response['success'] = true;
$response['message'] = "Logged out successfully.";
echo json_encode($response);
?>
