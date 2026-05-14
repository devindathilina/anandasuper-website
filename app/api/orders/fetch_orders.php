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
    'missing_pagination' => 'Invalid request.',
    'invalid_status' => 'Invalid order status.',
    'invalid_price_type' => 'Invalid price type.',
    'auth_failed' => 'Invalid or expired session token.',
    'unauthorized' => 'Unauthorized or invalid session token.',
    'server_error' => 'Internal server error.'
];

const VALID_STATUSES = ['Pending', 'Confirmed', 'Preparing', 'Ready', 'Picked Up', 'Cancelled', 'Refunded'];
const VALID_PRICE_TYPES = ['Wholesale', 'Retail'];

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
    if (!isset($data['page'], $data['limit'])) handleError('missing_pagination');

    $input = ['session_token' => $data['session_token']];
    $input['page'] = max(1, (int)$data['page']);
    $input['limit'] = min(100, max(1, (int)$data['limit']));

    if (isset($data['status']) && $data['status'] !== '') {
        $status = $data['status'];
        if (!in_array($status, VALID_STATUSES, true)) handleError('invalid_status');
        $input['status'] = $status;
    }

    if (isset($data['price_type']) && $data['price_type'] !== '') {
        $priceType = $data['price_type'];
        if (!in_array($priceType, VALID_PRICE_TYPES, true)) handleError('invalid_price_type');
        $input['price_type'] = $priceType;
    }

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

function buildWhereClause($customerId, $status = null, $priceType = null) {
    $where = "o.is_active = 1 AND o.customer_id = ?";

    if ($status) {
        $where .= " AND o.status = ?";
    }

    if ($priceType) {
        $where .= " AND o.price_type = ?";
    }

    return $where;
}

function buildSearchParams($status, $priceType, $customerId, &$params, &$types) {
    $params[] = $customerId;
    $types .= "i";

    if ($status) {
        $params[] = $status;
        $types .= "s";
    }

    if ($priceType) {
        $params[] = $priceType;
        $types .= "s";
    }
}

function getTotalOrders($db, $where, $params = [], $types = '') {
    $sql = "SELECT COUNT(*) AS total_orders FROM orders o WHERE $where";
    $result = executeQuery($db, $sql, $params, $types);
    if ($result === false) return false;

    $row = $result->fetch_assoc();
    return $row ? (int)$row['total_orders'] : 0;
}

function fetchOrdersList($db, $where, $page, $limit, $params = [], $types = '') {
    $offset = ($page - 1) * $limit;
    $sql = "SELECT o.order_id, o.order_ref_no, o.status, o.price_type,
                    o.order_total, o.ordered_datetime, o.picked_up_datetime,
                    o.payment_status, o.rating
            FROM orders o
            WHERE $where
            ORDER BY o.ordered_datetime DESC
            LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $limit;
    $types .= "ii";

    return executeQuery($db, $sql, $params, $types);
}

function formatOrdersList($result) {
    $orders = [];
    if ($result === false) return $orders;

    while ($row = $result->fetch_assoc()) {
        $orders[] = [
            'order_id' => (int)$row['order_id'],
            'order_ref_no' => $row['order_ref_no'],
            'status' => $row['status'],
            'price_type' => $row['price_type'],
            'order_total' => (float)$row['order_total'],
            'ordered_datetime' => $row['ordered_datetime'],
            'picked_up_datetime' => $row['picked_up_datetime'],
            'payment_status' => $row['payment_status'],
            'rating' => $row['rating'] !== null ? (int)$row['rating'] : null
        ];
    }
    return $orders;
}

function buildResponseData($orders, $pagination = []) {
    return [
        'success' => true,
        'message' => '',
        'data' => array_merge(['orders' => $orders], $pagination)
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

$params = [];
$types = '';
$status = $input['status'] ?? null;
$priceType = $input['price_type'] ?? null;
$where = buildWhereClause($customerId, $status, $priceType);
buildSearchParams($status, $priceType, $customerId, $params, $types);

$total_orders = getTotalOrders($db, $where, $params, $types);
if ($total_orders === false) handleError('server_error', null, 500);

$total_pages = (int)ceil($total_orders / $input['limit']);
$hasMore = $input['page'] < $total_pages;

$pagination = [
    'page' => $input['page'],
    'limit' => $input['limit'],
    'total_pages' => $total_pages,
    'total_orders' => $total_orders,
    'has_more' => $hasMore
];

$result = fetchOrdersList($db, $where, $input['page'], $input['limit'], $params, $types);
if ($result === false) handleError('server_error', null, 500);

$orders = formatOrdersList($result);
$responseData = buildResponseData($orders, $pagination);

sendResponse($responseData);
?>
