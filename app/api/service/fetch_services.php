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
    'invalid_search' => 'Search query must be at least 2 characters.',
    'auth_failed' => 'Invalid or expired session token.',
    'unauthorized' => 'Unauthorized or invalid session token.',
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
    if (!isset($data['page'], $data['limit'])) handleError('missing_pagination');

    $input = ['session_token' => $data['session_token']];
    $input['page'] = max(1, (int)$data['page']);
    $input['limit'] = min(100, max(1, (int)$data['limit']));

    if (isset($data['search']) && $data['search'] !== '') {
        $search = trim($data['search']);
        if (strlen($search) < 2) handleError('invalid_search');
        $input['search'] = substr($search, 0, 100);
    }

    if (isset($data['service_type']) && $data['service_type'] !== '') {
        $serviceType = $data['service_type'];
        if (in_array($serviceType, ['Bill', 'Reload'], true)) {
            $input['service_type'] = $serviceType;
        }
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

function buildWhereClause($search = null, $serviceType = null) {
    $where = "is_active = 1";

    if ($search) {
        $where .= " AND (service_name LIKE ?)";
    }

    if ($serviceType !== null) {
        $where .= " AND service_type = ?";
    }

    return $where;
}

function buildSearchParams($search, $serviceType, &$params, &$types) {
    if ($search) {
        $escapedSearch = addcslashes($search, '%_\\');
        $searchPattern = "%$escapedSearch%";
        $params[] = $searchPattern;
        $types .= "s";
    }

    if ($serviceType !== null) {
        $params[] = $serviceType;
        $types .= "s";
    }
}

function getTotalServices($db, $where, $params = [], $types = '') {
    $sql = "SELECT COUNT(*) AS total_services FROM services WHERE $where";
    $result = executeQuery($db, $sql, $params, $types);
    if ($result === false) return false;

    $row = $result->fetch_assoc();
    return $row ? (int)$row['total_services'] : 0;
}

function fetchServices($db, $where, $page, $limit, $params = [], $types = '') {
    $sql = "SELECT service_id, service_name, service_type, is_active, created_at, updated_at
            FROM services
            WHERE $where
            ORDER BY service_type ASC, service_name ASC
            LIMIT ?, ?";
    $params[] = ($page - 1) * $limit;
    $params[] = $limit;
    $types .= "ii";

    return executeQuery($db, $sql, $params, $types);
}

function formatServices($result) {
    $services = [];
    if ($result === false) return $services;

    while ($row = $result->fetch_assoc()) {
        $services[] = [
            'service_id' => (int)$row['service_id'],
            'service_name' => $row['service_name'],
            'service_type' => $row['service_type'],
            'is_active' => (bool)$row['is_active']
        ];
    }
    return $services;
}

function buildResponseData($services, $pagination = []) {
    return [
        'success' => true,
        'message' => '',
        'data' => array_merge(['services' => $services], $pagination)
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

$params = [];
$types = '';
$search = $input['search'] ?? null;
$serviceType = $input['service_type'] ?? null;
$where = buildWhereClause($search, $serviceType);
buildSearchParams($search, $serviceType, $params, $types);

$total_services = getTotalServices($db, $where, $params, $types);
if ($total_services === false) handleError('server_error', null, 500);

$total_pages = (int)ceil($total_services / $input['limit']);
$hasMore = $input['page'] < $total_pages;

$pagination = [
    'page' => $input['page'],
    'limit' => $input['limit'],
    'total_pages' => $total_pages,
    'total_services' => $total_services,
    'hasMore' => $hasMore
];

$result = fetchServices($db, $where, $input['page'], $input['limit'], $params, $types);

if ($result === false) handleError('server_error', null, 500);

$services = formatServices($result);
$responseData = buildResponseData($services, $pagination);

sendResponse($responseData);
?>
