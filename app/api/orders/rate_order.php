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
    'missing_rating' => 'Rating is required.',
    'invalid_rating' => 'Rating must be between 1 and 5.',
    'invalid_rating_text' => 'Rating text must be between 2 and 500 characters if provided.',
    'auth_failed' => 'Invalid or expired session token.',
    'order_not_found' => 'Order not found.',
    'order_not_picked_up' => 'Only picked up or refunded orders can be rated.',
    'order_already_rated' => 'Order has already been rated.',
    'rating_failed' => 'Failed to save order rating.',
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
    if (!isset($data['rating']) || $data['rating'] === '') handleError('missing_rating');

    $orderId = (int)$data['order_id'];
    $rating = (int)$data['rating'];
    $ratingText = isset($data['rating_text']) ? trim($data['rating_text']) : '';

    if ($orderId <= 0) handleError('missing_order_id');
    if ($rating < 1 || $rating > 5) handleError('invalid_rating');
    if ($ratingText !== '' && (strlen($ratingText) < 2 || strlen($ratingText) > 500)) {
        handleError('invalid_rating_text');
    }

    return [
        'session_token' => $data['session_token'],
        'order_id' => $orderId,
        'rating' => $rating,
        'rating_text' => $ratingText !== '' ? $ratingText : null
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

function executeUpdate($db, $sql, $params = [], $types = '') {
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

    $affectedRows = $stmt->affected_rows;
    $stmt->close();
    return $affectedRows;
}

function fetchOrderForRating($db, $orderId, $customerId) {
    $sql = "SELECT order_id, status, rating, rating_text
            FROM orders
            WHERE order_id = ? AND customer_id = ? AND is_active = 1
            LIMIT 1";
    return executeQuery($db, $sql, [$orderId, $customerId], 'ii');
}

function saveOrderRating($db, $orderId, $customerId, $rating, $ratingText) {
    $sql = "UPDATE orders
            SET rating = ?, rating_text = ?
            WHERE order_id = ? AND customer_id = ? AND is_active = 1 AND rating IS NULL";
    return executeUpdate($db, $sql, [$rating, $ratingText, $orderId, $customerId], 'isii');
}

function buildResponseData($orderId, $rating, $ratingText) {
    return [
        'success' => true,
        'message' => 'Order rating submitted successfully.',
        'data' => [
            'order_id' => $orderId,
            'rating' => $rating,
            'rating_text' => $ratingText
        ]
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    handleError('invalid_method');
}

$data = json_decode(file_get_contents('php://input'), true);
$input = validateInput($data);

$authResult = validateCustomerAuthentication($input['session_token'], $db, $_ENV['JWT_SECRET_KEY']);
if (!$authResult['success']) {
    handleError('auth_failed', $authResult['message'], 401);
}

$customerId = (int)$authResult['customer_id'];
$orderId = $input['order_id'];
$rating = $input['rating'];
$ratingText = $input['rating_text'];

$orderResult = fetchOrderForRating($db, $orderId, $customerId);
if ($orderResult === false) handleError('server_error', null, 500);

$order = $orderResult->fetch_assoc();
if (!$order) handleError('order_not_found', null, 404);

$orderStatus = strtolower(trim($order['status']));
if ($orderStatus !== 'picked up' && $orderStatus !== 'refunded') {
    handleError('order_not_picked_up');
}

if ($order['rating'] !== null) {
    handleError('order_already_rated');
}

$affectedRows = saveOrderRating($db, $orderId, $customerId, $rating, $ratingText);

if ($affectedRows === false || $affectedRows === 0) {
    handleError('rating_failed', null, 500);
}

sendResponse(buildResponseData($orderId, $rating, $ratingText));
?>
