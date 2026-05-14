<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/api_functions.php';
require_once __DIR__ . '/../../config/jwt_functions.php';
require_once __DIR__ . '/../../config/auth_functions.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../');
$dotenv->load();


const ERRORS = [
    'invalid_method' => 'Invalid request.',
    'invalid_json' => 'Invalid request.',
    'missing_token' => 'Missing session token.',
    'missing_order_id' => 'Order ID is required.',
    'auth_failed' => 'Invalid or expired session token.',
    'unauthorized' => 'Unauthorized or invalid session token.',
    'order_not_found' => 'Order not found.',
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
    if (!isset($data['bill_reload_order_id']) || $data['bill_reload_order_id'] === '') handleError('missing_order_id');

    $input = [
        'session_token' => $data['session_token'],
        'bill_reload_order_id' => (int)$data['bill_reload_order_id']
    ];

    return $input;
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

function fetchBillReloadOrder($db, $orderId, $customerId) {
    $sql = "SELECT bro.bill_reload_order_id, bro.bill_reload_ref_no, bro.customer_id,
                    bro.service_id, bro.account_number, bro.amount, bro.service_fee, bro.total_amount,
                    bro.status, bro.payment_status, bro.processed_at, bro.note_store,
                    bro.created_at, bro.updated_at,
                    s.service_name, s.service_type
            FROM bill_reload_orders bro
            INNER JOIN services s ON bro.service_id = s.service_id
            WHERE bro.bill_reload_order_id = ? AND bro.customer_id = ?";
    $params = [$orderId, $customerId];
    $types = "ii";

    return executeQuery($db, $sql, $params, $types);
}

function formatOrder($result) {
    if ($result === false) return null;

    $row = $result->fetch_assoc();
    if (!$row) return null;

    return [
        'bill_reload_order_id' => (int)$row['bill_reload_order_id'],
        'bill_reload_ref_no' => $row['bill_reload_ref_no'],
        'service_id' => (int)$row['service_id'],
        'service_name' => $row['service_name'],
        'service_type' => $row['service_type'],
        'account_number' => $row['account_number'],
        'amount' => (float)$row['amount'],
        'service_fee' => (float)$row['service_fee'],
        'total_amount' => (float)$row['total_amount'],
        'status' => $row['status'],
        'payment_status' => $row['payment_status'],
        'processed_at' => $row['processed_at'],
        'note_store' => $row['note_store'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ];
}

function buildResponseData($order) {
    return [
        'success' => true,
        'message' => '',
        'data' => ['order' => $order]
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    handleError('invalid_method');
}

$data = json_decode(file_get_contents('php://input'), true);
$input = validateInput($data);

$auth_result = validateCustomerAuthentication($input['session_token'], $db, $_ENV['JWT_SECRET_KEY']);
if (!$auth_result['success']) {
    handleError('auth_failed', $auth_result['message'], 401);
}

$customerId = (int)$auth_result['customer_id'];
$orderId = $input['bill_reload_order_id'];

$order_result = fetchBillReloadOrder($db, $orderId, $customerId);
if ($order_result === false) handleError('server_error', null, 500);

$order = formatOrder($order_result);
if ($order === null) handleError('order_not_found', null, 404);

$responseData = buildResponseData($order);

sendResponse($responseData);
?>
