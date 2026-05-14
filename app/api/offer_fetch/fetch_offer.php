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
    'missing_offer_id' => 'Offer ID is required.',
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
    if (!isset($data['offer_id']) || $data['offer_id'] === '') handleError('missing_offer_id');

    return [
        'session_token' => $data['session_token'],
        'offer_id' => (int)$data['offer_id']
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

function fetchOffer($db, $offer_id) {
    $sql = "SELECT o.offer_id, o.offer_name, o.description, o.offer_code, o.off_percentage,
                   o.start_date, o.end_date, o.offer_image
            FROM offers o
            WHERE o.offer_id = ? AND o.is_active = 1 AND o.start_date <= CURDATE() AND o.end_date >= CURDATE()
            LIMIT 1";
    return executeQuery($db, $sql, [$offer_id], 'i');
}

function formatOffer($row) {
    $offer_image = $row['offer_image'] ? 'assets/offer/' . $row['offer_image'] : null;

    return [
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    handleError('invalid_method');
}

$data = json_decode(file_get_contents('php://input'), true);
$input = validateInput($data);

$auth_result = validateCustomerAuthentication($input['session_token'], $db, $_ENV['JWT_SECRET_KEY']);
if (!$auth_result['success']) {
    handleError('auth_failed', $auth_result['message'], 401);
}

$offer_id = $input['offer_id'];

$result = fetchOffer($db, $offer_id);

if ($result === false) {
    handleError('server_error', null, 500);
}

$row = $result->fetch_assoc();

if (!$row) {
    sendResponse([
        'success' => false,
        'message' => 'Offer not found or expired/inactive.',
        'data' => null
    ]);
}

$offer = formatOffer($row);

sendResponse([
    'success' => true,
    'message' => 'Offer found',
    'data' => ['offer' => $offer]
]);
?>
