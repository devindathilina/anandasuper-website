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
    'invalid_method' => 'Invalid request method.',
    'invalid_json' => 'Invalid request body.',
    'missing_token' => 'Missing session token.',
    'auth_failed' => 'Invalid or expired session token.',
    'missing_fields' => 'Missing required fields.',
    'invalid_fields' => 'Invalid field value provided.',
    'missing_items' => 'No items in order.',
    'invalid_price_type' => 'Invalid price type. Must be Wholesale or Retail.',
    'product_not_found' => 'Product not found.',
    'product_unavailable' => 'Product is not available.',
    'insufficient_stock' => 'Insufficient stock for product.',
    'unit_mismatch' => 'Unit does not match product.',
    'invalid_qty' => 'Quantity must be at least 1.',
    'item_note_too_long' => 'Item note must be less than 500 characters.',
    'note_customer_too_long' => 'Customer note must be less than 500 characters.',
    'order_creation_failed' => 'Failed to create order.',
    'payment_creation_failed' => 'Failed to create payment record.',
    'server_error' => 'Internal server error.'
];

const ALLOWED_FIELDS = ['session_token', 'price_type', 'items', 'note_customer'];
const ALLOWED_ITEM_FIELDS = ['product_id', 'unit_id', 'quantity', 'item_note'];
const VALID_PRICE_TYPES = ['Wholesale', 'Retail'];
const MAX_STRING_LENGTH = 500;
const ORDER_REF_MAX_RETRIES = 5;

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
    if (empty($data)) {
        handleError('invalid_json');
    }

    if (empty($data['session_token'])) {
        handleError('missing_token');
    }

    if (!isset($data['price_type']) || $data['price_type'] === '') {
        handleError('missing_fields', 'price_type is required.');
    }

    if (!in_array($data['price_type'], VALID_PRICE_TYPES)) {
        handleError('invalid_price_type');
    }

    foreach (array_keys($data) as $key) {
        if (!in_array($key, ALLOWED_FIELDS)) {
            handleError('invalid_fields', "Field '$key' is not allowed.");
        }
    }

    if (!isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
        handleError('missing_items');
    }

    $input = [
        'session_token' => $data['session_token'],
        'price_type' => $data['price_type'],
        'items' => []
    ];

    if (isset($data['note_customer'])) {
        $noteCustomer = trim((string)$data['note_customer']);
        if ($noteCustomer !== '' && strlen($noteCustomer) > MAX_STRING_LENGTH) {
            handleError('note_customer_too_long');
        }
        $input['note_customer'] = $noteCustomer !== '' ? $noteCustomer : null;
    } else {
        $input['note_customer'] = null;
    }

    $input['note_store'] = null;

    $seenProductUnits = [];

    foreach ($data['items'] as $index => $item) {
        if (!is_array($item)) {
            handleError('invalid_fields', "Item at index $index is not valid.");
        }

        foreach (array_keys($item) as $key) {
            if (!in_array($key, ALLOWED_ITEM_FIELDS)) {
                handleError('invalid_fields', "Field '$key' in items is not allowed.");
            }
        }

        if (!isset($item['product_id']) || $item['product_id'] === '') {
            handleError('missing_fields', "product_id is required for item at index $index.");
        }

        if (!isset($item['unit_id']) || $item['unit_id'] === '') {
            handleError('missing_fields', "unit_id is required for item at index $index.");
        }

        if (!isset($item['quantity']) || $item['quantity'] === '') {
            handleError('missing_fields', "quantity is required for item at index $index.");
        }

        $productId = (int)$item['product_id'];
        $unitId = (int)$item['unit_id'];
        $quantity = (int)$item['quantity'];

        if ($productId <= 0) {
            handleError('invalid_fields', "Invalid product_id at index $index.");
        }

        if ($unitId <= 0) {
            handleError('invalid_fields', "Invalid unit_id at index $index.");
        }

        if ($quantity < 1) {
            handleError('invalid_qty');
        }

        $productUnitKey = "{$productId}_{$unitId}";
        if (isset($seenProductUnits[$productUnitKey])) {
            handleError('invalid_fields', "Duplicate product_id and unit_id combination at index $index.");
        }
        $seenProductUnits[$productUnitKey] = true;

        $itemNote = null;
        if (isset($item['item_note'])) {
            $itemNote = trim((string)$item['item_note']);
            if ($itemNote !== '' && strlen($itemNote) > MAX_STRING_LENGTH) {
                handleError('item_note_too_long');
            }
            $itemNote = $itemNote !== '' ? $itemNote : null;
        }

        $input['items'][] = [
            'product_id' => $productId,
            'unit_id' => $unitId,
            'quantity' => $quantity,
            'item_note' => $itemNote
        ];
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

function validateProducts($db, $items, $priceType) {
    $productIds = array_column($items, 'product_id');
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
    $types = str_repeat('i', count($productIds));

    $sql = "SELECT p.product_id, p.unit_id, p.product_name, p.is_available, p.is_active,
                   p.qty, p.wholesale_price, p.retail_price,
                   pu.unit_code
            FROM products p
            INNER JOIN product_units pu ON p.unit_id = pu.unit_id
            WHERE p.product_id IN ($placeholders) AND p.is_active = 1";

    $result = executeQuery($db, $sql, $productIds, $types);
    if ($result === false) {
        handleError('server_error');
    }

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[(int)$row['product_id']] = $row;
    }

    $validatedItems = [];

    foreach ($items as $item) {
        $productId = $item['product_id'];

        if (!isset($products[$productId])) {
            handleError('product_not_found', "Product ID $productId not found.");
        }

        $product = $products[$productId];

        if ((int)$product['is_available'] !== 1) {
            handleError('product_unavailable', "Product '{$product['product_name']}' is not available.");
        }

        if ($item['unit_id'] !== (int)$product['unit_id']) {
            handleError('unit_mismatch', "Unit does not match product '{$product['product_name']}'.");
        }

        if ($item['quantity'] > (int)$product['qty']) {
            handleError('insufficient_stock', "Insufficient stock for '{$product['product_name']}'. Available: {$product['qty']}");
        }

        $price = $priceType === 'Wholesale'
            ? (float)$product['wholesale_price']
            : (float)$product['retail_price'];

        $validatedItems[] = [
            'product_id' => $productId,
            'unit_id' => $item['unit_id'],
            'quantity' => $item['quantity'],
            'item_note' => $item['item_note'],
            'product_name' => $product['product_name'],
            'unit_code' => $product['unit_code'],
            'price_unit' => $price,
            'price_total' => $price * $item['quantity'],
            'price_type' => $priceType
        ];
    }

    return $validatedItems;
}

function generateOrderRefNo($db) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $maxAttempts = ORDER_REF_MAX_RETRIES;

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $refNo = 'ORD-';
        for ($i = 0; $i < 8; $i++) {
            $refNo .= $characters[random_int(0, strlen($characters) - 1)];
        }

        $sql = "SELECT order_id FROM orders WHERE order_ref_no = ? LIMIT 1";
        $result = executeQuery($db, $sql, [$refNo], 's');

        if ($result === false) {
            continue;
        }

        if ($result->num_rows === 0) {
            return $refNo;
        }
    }

    return null;
}

function generatePaymentRefNo($db) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $maxAttempts = ORDER_REF_MAX_RETRIES;

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $refNo = 'PAY-';
        for ($i = 0; $i < 12; $i++) {
            $refNo .= $characters[random_int(0, strlen($characters) - 1)];
        }

        $sql = "SELECT payment_id FROM payments WHERE payment_ref_no = ? LIMIT 1";
        $result = executeQuery($db, $sql, [$refNo], 's');

        if ($result === false) {
            continue;
        }

        if ($result->num_rows === 0) {
            return $refNo;
        }
    }

    return null;
}

function generatePaymentToken() {
    return bin2hex(random_bytes(32));
}

function buildPaymentItemsDescription() {
    return 'Ananda Super Order';
}

function createOrderWithPayment($db, $customerId, $input, $validatedItems, $customerDetails) {
    $db->begin_transaction();

    try {
        $orderRefNo = generateOrderRefNo($db);
        if ($orderRefNo === null) {
            $db->rollback();
            return false;
        }

        $paymentRefNo = generatePaymentRefNo($db);
        if ($paymentRefNo === null) {
            $db->rollback();
            return false;
        }

        $paymentToken = generatePaymentToken();
        $orderTotal = round((float) array_sum(array_column($validatedItems, 'price_total')), 2);

        $sql = "INSERT INTO orders (order_ref_no, customer_id, price_type, order_total, note_customer, note_store, status, payment_status)
                VALUES (?, ?, ?, ?, ?, NULL, 'Pending', 'Pending')";

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            $db->rollback();
            return false;
        }

        $stmt->bind_param('sisds',
            $orderRefNo,
            $customerId,
            $input['price_type'],
            $orderTotal,
            $input['note_customer']
        );

        if (!$stmt->execute()) {
            $stmt->close();
            $db->rollback();
            return false;
        }

        $orderId = $db->insert_id;
        $stmt->close();

        foreach ($validatedItems as $item) {
            $sql = "SELECT product_id, qty FROM products WHERE product_id = ? FOR UPDATE";
            $lockStmt = $db->prepare($sql);
            if (!$lockStmt) {
                $db->rollback();
                return false;
            }

            $lockStmt->bind_param('i', $item['product_id']);
            if (!$lockStmt->execute()) {
                $lockStmt->close();
                $db->rollback();
                return false;
            }

            $productRow = $lockStmt->get_result()->fetch_assoc();
            $lockStmt->close();

            if (!$productRow) {
                $db->rollback();
                return false;
            }

            $currentStock = (int)$productRow['qty'];

            if ($item['quantity'] > $currentStock) {
                $db->rollback();
                return false;
            }

            $sql = "INSERT INTO order_items (order_id, product_id, unit_id, qty, price_unit, price_total, price_type, item_note)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $db->prepare($sql);
            if (!$stmt) {
                $db->rollback();
                return false;
            }

            $stmt->bind_param('iiiissss',
                $orderId,
                $item['product_id'],
                $item['unit_id'],
                $item['quantity'],
                $item['price_unit'],
                $item['price_total'],
                $item['price_type'],
                $item['item_note']
            );

            if (!$stmt->execute()) {
                $stmt->close();
                $db->rollback();
                return false;
            }

            $stmt->close();

            $sql = "UPDATE products SET qty = qty - ? WHERE product_id = ? AND qty >= ?";
            $stmt = $db->prepare($sql);
            if (!$stmt) {
                $db->rollback();
                return false;
            }

            $stmt->bind_param('iii', $item['quantity'], $item['product_id'], $item['quantity']);

            if (!$stmt->execute()) {
                $stmt->close();
                $db->rollback();
                return false;
            }

            if ($stmt->affected_rows === 0) {
                $stmt->close();
                $db->rollback();
                return false;
            }

            $stmt->close();
        }

        $itemsDesc = buildPaymentItemsDescription();

        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ipAddress = filter_var($clientIp, FILTER_VALIDATE_IP);
        if ($ipAddress === false) {
            $ipAddress = '0.0.0.0';
        }

        $paymentSql = "INSERT INTO payments (order_id, payment_ref_no, first_name, last_name, email, phone, address, city, country, amount, currency, items, ip_address, payment_token, status_code)
                       VALUES (?, ?, ?, ?, ?, ?, 'Ananda Super,Kandangoda', 'Kuruwita', 'Sri Lanka', ?, 'LKR', ?, ?, ?, 0)";

        $paymentStmt = $db->prepare($paymentSql);
        if (!$paymentStmt) {
            $db->rollback();
            return false;
        }

        $email = $customerDetails['phone'] . '@anandasuper.com';

        $paymentStmt->bind_param('isssssdsss',
            $orderId,
            $paymentRefNo,
            $customerDetails['first_name'],
            $customerDetails['last_name'],
            $email,
            $customerDetails['phone'],
            $orderTotal,
            $itemsDesc,
            $ipAddress,
            $paymentToken
        );

        if (!$paymentStmt->execute()) {
            $paymentStmt->close();
            $db->rollback();
            return false;
        }

        $paymentStmt->close();
        $db->commit();
        return [
            'order_id' => $orderId,
            'order_ref_no' => $orderRefNo,
            'order_total' => $orderTotal,
            'payment_ref_no' => $paymentRefNo,
            'payment_token' => $paymentToken
        ];

    } catch (Exception $e) {
        $db->rollback();
        return false;
    }
}

function getCustomerDetails($db, $customerId) {
    $sql = "SELECT first_name, last_name, phone FROM customers WHERE customer_id = ? LIMIT 1";
    $result = executeQuery($db, $sql, [$customerId], 'i');

    if ($result === false || $result->num_rows === 0) {
        return null;
    }

    return $result->fetch_assoc();
}

function buildResponseData($orderResult, $validatedItems) {
    $baseUrl = 'https://anandasuper.com';

    return [
        'success' => true,
        'message' => 'Order created successfully. Please complete payment.',
        'data' => [
            'order_id' => $orderResult['order_id'],
            'order_ref_no' => $orderResult['order_ref_no'],
            'order_total' => (float)$orderResult['order_total'],
            'payment_ref_no' => $orderResult['payment_ref_no'],
            'payment_url' => $baseUrl . '/public/payment.php?token=' . $orderResult['payment_token'],
            'items_count' => count($validatedItems),
            'items' => array_map(function($item) {
                return [
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'unit_code' => $item['unit_code'],
                    'quantity' => $item['quantity'],
                    'price_unit' => $item['price_unit'],
                    'price_total' => $item['price_total'],
                    'item_note' => $item['item_note']
                ];
            }, $validatedItems)
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

$validatedItems = validateProducts($db, $input['items'], $input['price_type']);

$customerDetails = getCustomerDetails($db, $customerId);
if ($customerDetails === null) {
    handleError('server_error');
}

$orderResult = createOrderWithPayment($db, $customerId, $input, $validatedItems, $customerDetails);

if ($orderResult === false) {
    handleError('order_creation_failed');
}

$responseData = buildResponseData($orderResult, $validatedItems);

sendResponse($responseData);
?>
