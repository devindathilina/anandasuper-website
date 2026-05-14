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

    $input = ['session_token' => $data['session_token']];
    $input['latest_only'] = isset($data['latest_only']) && filter_var($data['latest_only'], FILTER_VALIDATE_BOOLEAN) === true;

    if (!$input['latest_only']) {
        if (!isset($data['page'], $data['limit'])) handleError('missing_pagination');
        $input['page'] = max(1, (int)$data['page']);
        $input['limit'] = min(100, max(1, (int)$data['limit']));

        if (isset($data['search']) && $data['search'] !== '') {
            $search = trim($data['search']);
            if (strlen($search) < 2) handleError('invalid_search');
            $input['search'] = substr($search, 0, 100);
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

function buildWhereClause($search = null) {
    $where = "o.is_active = 1 AND o.start_date <= CURDATE() AND o.end_date >= CURDATE()";

    if ($search) {
        $where .= " AND (o.offer_name LIKE ? OR o.offer_code LIKE ?)";
    }

    return $where;
}

function buildSearchParams($search, &$params, &$types) {
    if ($search) {
        $searchPattern = "%$search%";
        $params[] = $searchPattern;
        $params[] = $searchPattern;
        $types .= "ss";
    }
}

function getTotalOffers($db, $where, $params = [], $types = '') {
    $sql = "SELECT COUNT(*) AS total_offers FROM offers o WHERE $where";
    $result = executeQuery($db, $sql, $params, $types);
    if ($result === false) return false;

    $row = $result->fetch_assoc();
    return $row ? (int)$row['total_offers'] : 0;
}

function fetchOffers($db, $where, $params = [], $types = '', $latestOnly = false, $page = null, $limit = null) {
    $sql = "SELECT o.offer_id, o.offer_name, o.description, o.offer_code, o.off_percentage,
                   o.start_date, o.end_date, o.offer_image
            FROM offers o
            WHERE $where
            ORDER BY o.end_date DESC";

    if ($latestOnly) {
        $sql .= " LIMIT 10";
    } else {
        $sql .= " LIMIT ?, ?";
        $params[] = ($page - 1) * $limit;
        $params[] = $limit;
        $types .= "ii";
    }

    return executeQuery($db, $sql, $params, $types);
}

function formatOffers($result) {
    $offers = [];
    while ($row = $result->fetch_assoc()) {
        $offer_image = $row['offer_image'] ? 'assets/offer/' . $row['offer_image'] : null;
        $offers[] = [
            'offer_id' => (int)$row['offer_id'],
            'offer_name' => $row['offer_name'],
            'description' => $row['description'],
            'offer_code' => $row['offer_code'],
            'off_percentage' => (float)$row['off_percentage'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'offer_image' => $offer_image
        ];
    }
    return $offers;
}

function buildResponseData($offers, $latestOnly, $pagination = []) {
    return [
        'success' => true,
        'message' => '',
        'data' => $latestOnly ? ['offers' => $offers] : array_merge(['offers' => $offers], $pagination)
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
$search = $input['latest_only'] ? null : ($input['search'] ?? null);
$where = buildWhereClause($search);
buildSearchParams($search, $params, $types);

$pagination = [];
if (!$input['latest_only']) {
    $total_offers = getTotalOffers($db, $where, $params, $types);
    if ($total_offers === false) handleError('server_error', null, 500);

    $total_pages = (int)ceil($total_offers / $input['limit']);
    $hasMore = $input['page'] < $total_pages;

    $pagination = [
        'page' => $input['page'],
        'limit' => $input['limit'],
        'total_pages' => $total_pages,
        'total_offers' => $total_offers,
        'hasMore' => $hasMore
    ];
}

$result = fetchOffers($db, $where, $params, $types, $input['latest_only'], $input['page'] ?? null, $input['limit'] ?? null);

if ($result === false) handleError('server_error', null, 500);

$offers = formatOffers($result);
$responseData = buildResponseData($offers, $input['latest_only'], $pagination);

sendResponse($responseData);
?>
