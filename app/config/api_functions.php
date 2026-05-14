<?php
if (!defined('ANANDA_SUPER_SECURE_ACCESS')) {
    http_response_code(403);
    exit('Direct access not allowed');
}
require_once __DIR__ . '/db.php';

function sanitizeInputApiKey($input, $maxLength = 500) {
    if (!is_string($input)) {
        return null;
    }
    $input = trim($input);
    if (empty($input)) {
        return null;
    }
    if (strlen($input) > $maxLength) {
        return null;
    }
    return $input;
}

function sendError($code, $message) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['error' => $message]);
    exit;
}

function generateApiKey($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

function hashApiKey($apiKey) {
    return hash('sha256', $apiKey);
}

function verifyApiKey($inputKey, $storedHash) {
    return hash('sha256', $inputKey) === $storedHash;
}

$headers = getallheaders();
$rawBody = file_get_contents('php://input');

$authorizationHeader = null;
foreach ($headers as $key => $value) {
    if (strtolower($key) === 'authorization') {
        $authorizationHeader = $value;
        break;
    }
}

$apiKey = null;
if ($authorizationHeader && preg_match('/Bearer\s(\S+)/', $authorizationHeader, $matches)) {
    $apiKey = sanitizeInputApiKey($matches[1], 255);
}

$userAgent = sanitizeInputApiKey($_SERVER['HTTP_USER_AGENT'] ?? '', 500);
if (!$userAgent || strpos($userAgent, 'Dart/3.') !== 0) {
    sendError(403, 'Unauthorized');
}

if (!$apiKey) {
    sendError(401, 'Unauthorized');
}

if (strlen($apiKey) !== 64) {
    sendError(401, 'Unauthorized');
}

try {
    $stmt = $db->prepare("SELECT api_key_id, device_id, is_active, created_at FROM api_keys WHERE api_key = ? AND is_active = 1 LIMIT 1");
    if (!$stmt) {
        sendError(500, 'Internal Server Error');
    }

    if (!$stmt->bind_param("s", $apiKey)) {
        $stmt->close();
        sendError(500, 'Internal Server Error');
    }

    if (!$stmt->execute()) {
        $stmt->close();
        sendError(500, 'Internal Server Error');
    }

    $result = $stmt->get_result();
    if ($result === false) {
        $stmt->close();
        sendError(500, 'Internal Server Error');
    }

    if ($result->num_rows === 0) {
        $stmt->close();
        sendError(401, 'Unauthorized');
    }

    $apiKeyData = $result->fetch_assoc();
    $stmt->close();

    $GLOBALS['api_key_data'] = $apiKeyData;

} catch (Exception $e) {
    sendError(500, 'Internal Server Error');
}
?>