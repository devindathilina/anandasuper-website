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
    'invalid_payment_status' => 'Invalid payment status.',
    'invalid_service_type' => 'Invalid service type.',
    'auth_failed' => 'Invalid or expired session token.',
    'unauthorized' => 'Unauthorized or invalid session token.',
    'server_error' => 'Internal server error.'
];

const VALID_STATUSES = ['Pending', 'Processing', 'Completed', 'Failed', 'Refunded'];
const VALID_PAYMENT_STATUSES = ['Pending', 'Paid', 'Failed', 'Refunded'];
const VALID_SERVICE_TYPES = ['Bill', 'Reload'];

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

    if (isset($data['payment_status']) && $data['payment_status'] !== '') {
        $paymentStatus = $data['payment_status'];
        if (!in_array($paymentStatus, VALID_PAYMENT_STATUSES, true)) handleError('invalid_payment_status');
        $input['payment_status'] = $paymentStatus;
    }

    if (isset($data['service_type']) && $data['service_type'] !== '') {
        $serviceType = $data['service_type'];
        if (!in_array($serviceType, VALID_SERVICE_TYPES, true)) handleError('invalid_service_type');
        $input['service_type'] = $serviceType;
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

function buildWhereClause($customerId, $status = null, $paymentStatus = null, $serviceType = null) {
    $where = "bro.customer_id = ?";

    if ($status) {
        $where .= " AND bro.status = ?";
    }

    if ($paymentStatus) {
        $where .= " AND bro.payment_status = ?";
    }

    if ($serviceType) {
        $where .= " AND s.service_type = ?";
    }

    return $where;
}

function buildSearchParams($status, $paymentStatus, $serviceType, $customerId, &$params, &$types) {
    $params[] = $customerId;
    $types .= "i";

    if ($status) {
        $params[] = $status;
        $types .= "s";
    }

    if ($paymentStatus) {
        $params[] = $paymentStatus;
        $types .= "s";
    }

    if ($serviceType) {
        $params[] = $serviceType;
        $types .= "s";
    }
}

function getTotalOrders($db, $where, $params = [], $types = '') {
    $sql = "SELECT COUNT(*) AS total_orders
            FROM bill_reload_orders bro
            INNER JOIN services s ON bro.service_id = s.service_id
            WHERE $where";
    $result = executeQuery($db, $sql, $params, $types);
    if ($result === false) return false;

    $row = $result->fetch_assoc();
    return $row ? (int)$row['total_orders'] : 0;
}

function fetchOrdersList($db, $where, $page, $limit, $params = [], $types = '') {
    $offset = ($page - 1) * $limit;
    $sql = "SELECT bro.bill_reload_order_id, bro.bill_reload_ref_no, bro.service_id,
                    bro.account_number, bro.amount, bro.service_fee, bro.total_amount,
                    bro.status, bro.payment_status, bro.processed_at,
                    bro.created_at, bro.updated_at,
                    s.service_name, s.service_type
            FROM bill_reload_orders bro
            INNER JOIN services s ON bro.service_id = s.service_id
            WHERE $where
            ORDER BY bro.created_at DESC
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
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
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
$paymentStatus = $input['payment_status'] ?? null;
$serviceType = $input['service_type'] ?? null;
$where = buildWhereClause($customerId, $status, $paymentStatus, $serviceType);
buildSearchParams($status, $paymentStatus, $serviceType, $customerId, $params, $types);

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
