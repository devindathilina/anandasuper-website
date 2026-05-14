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

const PRODUCT_IMAGE_PATH = 'assets/product/';

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

    if (isset($data['category_id']) && $data['category_id'] !== '') {
        $input['category_id'] = (int)$data['category_id'];
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

function buildWhereClause($search = null, $categoryId = null) {
    $where = "p.is_active = 1 AND p.is_available = 1";

    if ($search) {
        $where .= " AND (p.product_name LIKE ? OR p.barcode LIKE ? OR p.description LIKE ?)";
    }

    if ($categoryId !== null) {
        $where .= " AND p.category_id = ?";
    }

    return $where;
}

function buildSearchParams($search, $categoryId, &$params, &$types) {
    if ($search) {
        $escapedSearch = addcslashes($search, '%_\\');
        $searchPattern = "%$escapedSearch%";
        $params[] = $searchPattern;
        $params[] = $searchPattern;
        $params[] = $searchPattern;
        $types .= "sss";
    }

    if ($categoryId !== null) {
        $params[] = $categoryId;
        $types .= "i";
    }
}

function getTotalProducts($db, $where, $params = [], $types = '') {
    $sql = "SELECT COUNT(*) AS total_products FROM products p WHERE $where";
    $result = executeQuery($db, $sql, $params, $types);
    if ($result === false) return false;

    $row = $result->fetch_assoc();
    return $row ? (int)$row['total_products'] : 0;
}

function fetchProducts($db, $where, $page, $limit, $params = [], $types = '') {
    $sql = "SELECT p.product_id, p.product_name, p.description, p.product_image,
                   p.barcode, p.wholesale_price, p.retail_price, p.qty,
                   pc.category_id, pc.category_name,
                   pu.unit_id, pu.unit_code
            FROM products p
            LEFT JOIN product_category pc ON p.category_id = pc.category_id AND pc.is_active = 1
            LEFT JOIN product_units pu ON p.unit_id = pu.unit_id AND pu.is_active = 1
            WHERE $where
            ORDER BY p.product_name ASC
            LIMIT ?, ?";
    $params[] = ($page - 1) * $limit;
    $params[] = $limit;
    $types .= "ii";

    return executeQuery($db, $sql, $params, $types);
}

function formatProducts($result) {
    $products = [];
    if ($result === false) return $products;

    while ($row = $result->fetch_assoc()) {
        $product_image = $row['product_image'] ? PRODUCT_IMAGE_PATH . $row['product_image'] : null;

        $products[] = [
            'product_id' => (int)$row['product_id'],
            'product_name' => $row['product_name'],
            'description' => $row['description'],
            'product_image' => $product_image,
            'barcode' => $row['barcode'],
            'wholesale_price' => (float)$row['wholesale_price'],
            'retail_price' => (float)$row['retail_price'],
            'qty' => (float)$row['qty'],
            'category' => $row['category_name'] ? [
                'category_id' => (int)$row['category_id'],
                'category_name' => $row['category_name']
            ] : null,
            'unit' => $row['unit_code'] ? [
                'unit_id' => (int)$row['unit_id'],
                'unit_code' => $row['unit_code']
            ] : null
        ];
    }
    return $products;
}

function buildResponseData($products, $pagination = []) {
    return [
        'success' => true,
        'message' => '',
        'data' => array_merge(['products' => $products], $pagination)
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
$categoryId = $input['category_id'] ?? null;
$where = buildWhereClause($search, $categoryId);
buildSearchParams($search, $categoryId, $params, $types);

$total_products = getTotalProducts($db, $where, $params, $types);
if ($total_products === false) handleError('server_error', null, 500);

$total_pages = (int)ceil($total_products / $input['limit']);
$hasMore = $input['page'] < $total_pages;

$pagination = [
    'page' => $input['page'],
    'limit' => $input['limit'],
    'total_pages' => $total_pages,
    'total_products' => $total_products,
    'hasMore' => $hasMore
];

$result = fetchProducts($db, $where, $input['page'], $input['limit'], $params, $types);

if ($result === false) handleError('server_error', null, 500);

$products = formatProducts($result);
$responseData = buildResponseData($products, $pagination);

sendResponse($responseData);
?>
