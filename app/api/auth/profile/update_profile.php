<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/jwt_functions.php';
require_once __DIR__ . '/../../../config/auth_functions.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../../');
$dotenv->load();

const ERRORS = [
    'invalid_method' => 'Invalid request.',
    'invalid_json' => 'Invalid request.',
    'missing_token' => 'Missing session token.',
    'auth_failed' => 'Invalid or expired session token.',
    'customer_not_found' => 'Customer not found.',
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

    $firstName = null;
    $lastName = null;
    $errors = [];

    if (isset($data['first_name']) && trim($data['first_name']) !== '') {
        $firstName = trim($data['first_name']);
        if (strlen($firstName) < 2) {
            $errors[] = 'First name must be at least 2 characters long!';
        }
        if (strlen($firstName) > 100) {
            $errors[] = 'First name cannot exceed 100 characters!';
        }
    }

    if (isset($data['last_name']) && trim($data['last_name']) !== '') {
        $lastName = trim($data['last_name']);
        if (strlen($lastName) < 2) {
            $errors[] = 'Last name must be at least 2 characters long!';
        }
        if (strlen($lastName) > 100) {
            $errors[] = 'Last name cannot exceed 100 characters!';
        }
    }

    if ($firstName === null && $lastName === null) {
        $errors[] = 'At least one field (first_name or last_name) is required!';
    }

    if (!empty($errors)) {
        handleError('invalid_json', implode(' ', $errors));
    }

    return [
        'session_token' => $data['session_token'],
        'first_name' => $firstName,
        'last_name' => $lastName
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

function fetchCustomer($db, $customerId) {
    $sql = "SELECT customer_id, first_name, last_name, phone FROM customers WHERE customer_id = ? LIMIT 1";
    return executeQuery($db, $sql, [$customerId], 'i');
}

function updateProfile($db, $customerId, $firstName, $lastName) {
    $fields = [];
    $params = [];
    $types = '';

    if ($firstName !== null) {
        $fields[] = 'first_name = ?';
        $params[] = $firstName;
        $types .= 's';
    }

    if ($lastName !== null) {
        $fields[] = 'last_name = ?';
        $params[] = $lastName;
        $types .= 's';
    }

    $params[] = $customerId;
    $types .= 'i';

    $sql = "UPDATE customers SET " . implode(', ', $fields) . " WHERE customer_id = ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) return false;

    $stmt->bind_param($types, ...$params);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
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

$result = fetchCustomer($db, $customerId);
if ($result === false) {
    handleError('server_error', null, 500);
}

$customer = $result->fetch_assoc();
if (!$customer) {
    handleError('customer_not_found');
}

$db->begin_transaction();

try {
    if (!updateProfile($db, $customerId, $input['first_name'], $input['last_name'])) {
        throw new Exception('Failed to update profile');
    }

    $db->commit();

    $updatedCustomer = fetchCustomer($db, $customerId);
    $customerData = $updatedCustomer ? $updatedCustomer->fetch_assoc() : null;

    sendResponse([
        'success' => true,
        'message' => 'Profile updated successfully.',
        'data' => [
            'customer' => [
                'customer_id' => (int)$customerData['customer_id'],
                'first_name' => $customerData['first_name'],
                'last_name' => $customerData['last_name'],
                'phone' => $customerData['phone']
            ]
        ]
    ]);

} catch (Exception $e) {
    $db->rollback();
    handleError('server_error', null, 500);
}
?>
