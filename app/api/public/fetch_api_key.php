<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../');
$dotenv->load();

const API_KEY_LENGTH = 64;
const MAX_INPUT_LENGTH = 500;
const MAX_ATTEMPTS = 5;
const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

const ERRORS = [
    400 => 'Invalid request',
    403 => 'Forbidden - Invalid client',
    405 => 'Method not allowed',
    409 => 'Conflict',
    500 => 'Internal Server Error',
    503 => 'Service unavailable'
];

function sendResponse($data, $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function handleError($httpCode, $customMessage = null) {
    $message = $customMessage ?? ERRORS[$httpCode] ?? 'Internal Server Error';
    sendResponse(['error' => $message], $httpCode);
}

function sanitizeInput($input) {
    if (!is_string($input)) return null;
    $input = trim($input);
    return (empty($input) || strlen($input) > MAX_INPUT_LENGTH) ? null : htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function isValidUUID($uuid) {
    return preg_match(UUID_PATTERN, $uuid) === 1;
}

function generateApiKey() {
    return bin2hex(random_bytes(API_KEY_LENGTH / 2));
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

function fetchExistingKey($db, $deviceId) {
    $result = executeQuery($db, "SELECT api_key, is_active FROM api_keys WHERE device_id = ? LIMIT 1", [$deviceId], 's');
    if ($result === false || $result->num_rows === 0) return null;

    return $result->fetch_assoc();
}

function isKeyUnique($db, $apiKey) {
    $result = executeQuery($db, "SELECT api_key_id FROM api_keys WHERE api_key = ? LIMIT 1", [$apiKey], 's');
    return $result === false || $result->num_rows === 0;
}

function createApiKey($db, $apiKey, $deviceId) {
    $stmt = $db->prepare("INSERT INTO api_keys (api_key, device_id, is_active, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())");
    if ($stmt === false || !$stmt->bind_param("ss", $apiKey, $deviceId) || !$stmt->execute()) {
        if ($stmt) $stmt->close();
        return $db->errno === 1062 ? 'duplicate' : false;
    }

    $id = $db->insert_id;
    $stmt->close();
    return $id;
}

$userAgent = sanitizeInput($_SERVER['HTTP_USER_AGENT'] ?? '');
if (!$userAgent || strpos($userAgent, 'Dart/3.') !== 0) {
    handleError(403);
}

$requestMethod = $_SERVER['REQUEST_METHOD'];
if (!in_array($requestMethod, ['POST', 'GET'])) {
    handleError(405);
}

$deviceId = $requestMethod === 'POST'
    ? sanitizeInput(json_decode(file_get_contents('php://input'), true)['device_id'] ?? null)
    : sanitizeInput($_GET['device_id'] ?? null);

if (!$deviceId || !isValidUUID($deviceId)) {
    handleError(400);
}

try {
    $existingKey = fetchExistingKey($db, $deviceId);

    if ($existingKey) {
        if ($existingKey['is_active']) {
            sendResponse([
                'apiKey' => $existingKey['api_key'],
                'deviceId' => $deviceId,
                'status' => 'existing'
            ]);
        }
        handleError(403, 'Access denied');
    }

    $newApiKey = null;
    for ($i = 0; $i < MAX_ATTEMPTS; $i++) {
        $newApiKey = generateApiKey();
        if (isKeyUnique($db, $newApiKey)) break;
    }

    if (!$newApiKey || !isKeyUnique($db, $newApiKey)) {
        handleError(503);
    }

    $apiKeyId = createApiKey($db, $newApiKey, $deviceId);

    if ($apiKeyId === 'duplicate') {
        handleError(409);
    }

    if (!$apiKeyId) {
        handleError(500);
    }

    sendResponse([
        'apiKey' => $newApiKey,
        'deviceId' => $deviceId,
        'apiKeyId' => $apiKeyId,
        'status' => 'new'
    ]);

} catch (Exception $e) {
    handleError(500);
}
?>