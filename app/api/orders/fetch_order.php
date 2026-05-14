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
    if (!isset($data['order_id']) || $data['order_id'] === '') handleError('missing_order_id');

    $input = [
        'session_token' => $data['session_token'],
        'order_id' => (int)$data['order_id']
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

function fetchOrder($db, $orderId, $customerId) {
    $sql = "SELECT o.order_id, o.order_ref_no, o.status, o.price_type,
                    o.order_total, o.ordered_datetime, o.picked_up_datetime,
                    o.payment_status, o.note_customer, o.note_store,
                    o.rating, o.rating_text
            FROM orders o
            WHERE o.order_id = ? AND o.customer_id = ? AND o.is_active = 1";
    $params = [$orderId, $customerId];
    $types = "ii";

    return executeQuery($db, $sql, $params, $types);
}

function fetchOrderItems($db, $orderId) {
    $sql = "SELECT oi.order_item_id, oi.product_id, oi.unit_id,
                    oi.qty, oi.price_unit, oi.price_total, oi.price_type, oi.item_note,
                    p.product_name, p.product_image,
                    pu.unit_code,
                    pc.category_name
            FROM order_items oi
            INNER JOIN products p ON oi.product_id = p.product_id
            INNER JOIN product_units pu ON oi.unit_id = pu.unit_id
            LEFT JOIN product_category pc ON p.category_id = pc.category_id
            WHERE oi.order_id = ? AND oi.is_active = 1
            ORDER BY oi.order_item_id ASC";
    $params = [$orderId];
    $types = "i";

    return executeQuery($db, $sql, $params, $types);
}

function formatOrder($result) {
    if ($result === false) return null;

    $row = $result->fetch_assoc();
    if (!$row) return null;

    return [
        'order_id' => (int)$row['order_id'],
        'order_ref_no' => $row['order_ref_no'],
        'status' => $row['status'],
        'price_type' => $row['price_type'],
        'order_total' => (float)$row['order_total'],
        'ordered_datetime' => $row['ordered_datetime'],
        'picked_up_datetime' => $row['picked_up_datetime'],
        'payment_status' => $row['payment_status'],
        'note_customer' => $row['note_customer'],
        'note_store' => $row['note_store'],
        'rating' => $row['rating'] !== null ? (int)$row['rating'] : null,
        'rating_text' => $row['rating_text']
    ];
}

function formatOrderItems($result) {
    $items = [];
    if ($result === false) return $items;

    while ($row = $result->fetch_assoc()) {
        $product_image = $row['product_image'] ? 'assets/product/' . $row['product_image'] : null;

        $items[] = [
            'order_item_id' => (int)$row['order_item_id'],
            'product_id' => (int)$row['product_id'],
            'unit_id' => (int)$row['unit_id'],
            'product_name' => $row['product_name'],
            'product_image' => $product_image,
            'category_name' => $row['category_name'],
            'unit_code' => $row['unit_code'],
            'qty' => (int)$row['qty'],
            'price_unit' => (float)$row['price_unit'],
            'price_total' => (float)$row['price_total'],
            'price_type' => $row['price_type'],
            'item_note' => $row['item_note']
        ];
    }
    return $items;
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
$orderId = $input['order_id'];

$order_result = fetchOrder($db, $orderId, $customerId);
if ($order_result === false) handleError('server_error', null, 500);

$order = formatOrder($order_result);
if ($order === null) handleError('order_not_found', null, 404);

$items_result = fetchOrderItems($db, $orderId);
if ($items_result === false) handleError('server_error', null, 500);

$items = formatOrderItems($items_result);
$order['items'] = $items;

$responseData = buildResponseData($order);

sendResponse($responseData);
?>
