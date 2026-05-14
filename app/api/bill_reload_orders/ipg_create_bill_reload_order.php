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
    'service_not_found' => 'Service not found.',
    'service_unavailable' => 'Service is not available.',
    'invalid_amount' => 'Amount must be greater than 0.',
    'amount_too_large' => 'Amount exceeds the maximum allowed value.',
    'invalid_account_number' => 'Account number is required.',
    'invalid_account_number_length' => 'Account number length is invalid.',
    'rate_not_found' => 'Service fee rate not found.',
    'order_creation_failed' => 'Failed to create order.',
    'payment_creation_failed' => 'Failed to create payment record.',
    'server_error' => 'Internal server error.'
];

const ALLOWED_FIELDS = ['session_token', 'service_id', 'account_number', 'amount'];
const MIN_ACCOUNT_LENGTH = 1;
const MAX_ACCOUNT_LENGTH = 255;
const MAX_ORDER_AMOUNT = 10000000.00;
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

    foreach (array_keys($data) as $key) {
        if (!in_array($key, ALLOWED_FIELDS)) {
            handleError('invalid_fields', "Field '$key' is not allowed.");
        }
    }

    if (!isset($data['service_id']) || $data['service_id'] === '') {
        handleError('missing_fields', 'service_id is required.');
    }

    if (!isset($data['account_number']) || trim($data['account_number']) === '') {
        handleError('missing_fields', 'account_number is required.');
    }

    if (!isset($data['amount']) || $data['amount'] === '') {
        handleError('missing_fields', 'amount is required.');
    }

    $serviceId = (int)$data['service_id'];
    if ($serviceId <= 0) {
        handleError('invalid_fields', 'Invalid service_id.');
    }

    $accountNumber = trim((string)$data['account_number']);
    $accountNumberLength = strlen($accountNumber);
    if ($accountNumberLength < MIN_ACCOUNT_LENGTH || $accountNumberLength > MAX_ACCOUNT_LENGTH) {
        handleError(
            'invalid_account_number_length',
            'Account number must be between ' . MIN_ACCOUNT_LENGTH . ' and ' . MAX_ACCOUNT_LENGTH . ' characters.'
        );
    }

    $amountRaw = is_string($data['amount']) ? trim($data['amount']) : (string)$data['amount'];
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $amountRaw)) {
        handleError('invalid_amount', 'Amount must be a valid number with up to 2 decimal places.');
    }

    $amount = (float)$amountRaw;
    if ($amount <= 0) {
        handleError('invalid_amount');
    }
    if ($amount > MAX_ORDER_AMOUNT) {
        handleError(
            'amount_too_large',
            'Amount must be ' . number_format(MAX_ORDER_AMOUNT, 2, '.', '') . ' or less.'
        );
    }

    return [
        'session_token' => $data['session_token'],
        'service_id' => $serviceId,
        'account_number' => $accountNumber,
        'amount' => round($amount, 2)
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

function validateService($db, $serviceId) {
    $sql = "SELECT service_id, service_name, service_type, is_active
            FROM services
            WHERE service_id = ? AND is_active = 1
            LIMIT 1";

    $result = executeQuery($db, $sql, [$serviceId], 'i');
    if ($result === false) {
        handleError('server_error');
    }

    if ($result->num_rows === 0) {
        handleError('service_not_found');
    }

    return $result->fetch_assoc();
}

function getServiceFeeRate($db) {
    $sql = "SELECT rate_value FROM rates WHERE rate_name = 'bill_payment' LIMIT 1";
    $result = executeQuery($db, $sql, [], '');

    if ($result === false || $result->num_rows === 0) {
        handleError('rate_not_found');
    }

    $row = $result->fetch_assoc();
    return (float)$row['rate_value'];
}

function generateBillReloadRefNo($db) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $maxAttempts = ORDER_REF_MAX_RETRIES;

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $refNo = 'BRO-';
        for ($i = 0; $i < 8; $i++) {
            $refNo .= $characters[random_int(0, strlen($characters) - 1)];
        }

        $sql = "SELECT bill_reload_order_id FROM bill_reload_orders WHERE bill_reload_ref_no = ? LIMIT 1";
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

function getServicePrefix($serviceType) {
    return $serviceType === 'Bill' ? 'BILL' : 'RELOAD';
}

function createBillReloadOrderWithPayment($db, $customerId, $input, $service, $customerDetails) {
    $db->begin_transaction();

    try {
        $billReloadRefNo = generateBillReloadRefNo($db);
        if ($billReloadRefNo === null) {
            $db->rollback();
            return false;
        }

        $paymentRefNo = generatePaymentRefNo($db);
        if ($paymentRefNo === null) {
            $db->rollback();
            return false;
        }

        $paymentToken = generatePaymentToken();
        $serviceFee = round((float)$input['service_fee'], 2);
        $amount = round((float)$input['amount'], 2);
        $totalAmount = round($amount + $serviceFee, 2);

        $sql = "INSERT INTO bill_reload_orders (bill_reload_ref_no, customer_id, service_id, account_number, amount, service_fee, total_amount, status, payment_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', 'Pending')";

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            $db->rollback();
            return false;
        }

        $stmt->bind_param('sisdddd',
            $billReloadRefNo,
            $customerId,
            $input['service_id'],
            $input['account_number'],
            $amount,
            $serviceFee,
            $totalAmount
        );

        if (!$stmt->execute()) {
            $stmt->close();
            $db->rollback();
            return false;
        }

        $orderId = $db->insert_id;
        $stmt->close();

        $servicePrefix = getServicePrefix($service['service_type']);
        $itemsDesc = "{$service['service_name']} - {$servicePrefix} [Account: {$input['account_number']}]";

        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ipAddress = filter_var($clientIp, FILTER_VALIDATE_IP);
        if ($ipAddress === false) {
            $ipAddress = '0.0.0.0';
        }

        $paymentSql = "INSERT INTO payments (bill_reload_order_id, payment_ref_no, first_name, last_name, email, phone, address, city, country, amount, currency, items, ip_address, payment_token, status_code)
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
            $totalAmount,
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
            'bill_reload_order_id' => $orderId,
            'bill_reload_ref_no' => $billReloadRefNo,
            'amount' => $amount,
            'service_fee' => $serviceFee,
            'total_amount' => $totalAmount,
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

function buildResponseData($orderResult, $service, $input) {
    $baseUrl = 'https://anandasuper.com';

    return [
        'success' => true,
        'message' => 'Order created successfully. Please complete payment.',
        'data' => [
            'bill_reload_order_id' => $orderResult['bill_reload_order_id'],
            'bill_reload_ref_no' => $orderResult['bill_reload_ref_no'],
            'service_id' => $input['service_id'],
            'service_name' => $service['service_name'],
            'service_type' => $service['service_type'],
            'account_number' => $input['account_number'],
            'amount' => (float)$orderResult['amount'],
            'service_fee' => (float)$orderResult['service_fee'],
            'total_amount' => (float)$orderResult['total_amount'],
            'payment_ref_no' => $orderResult['payment_ref_no'],
            'payment_url' => $baseUrl . '/public/payment-bill.php?token=' . $orderResult['payment_token']
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

$service = validateService($db, $input['service_id']);

if ($service['service_type'] === 'Bill') {
    $serviceFeeRate = getServiceFeeRate($db);
    $calculatedServiceFee = round(($input['amount'] * $serviceFeeRate) / 100, 2);
} else {
    $calculatedServiceFee = 0.0;
}
$input['service_fee'] = $calculatedServiceFee;

$customerDetails = getCustomerDetails($db, $customerId);
if ($customerDetails === null) {
    handleError('server_error');
}

$orderResult = createBillReloadOrderWithPayment($db, $customerId, $input, $service, $customerDetails);

if ($orderResult === false) {
    handleError('order_creation_failed');
}

$responseData = buildResponseData($orderResult, $service, $input);

sendResponse($responseData);
?>
