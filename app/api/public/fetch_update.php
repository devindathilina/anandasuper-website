<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/api_functions.php';

const ALLOWED_PLATFORMS = ['google', 'apple'];
const INTERNAL_ERROR = ['error' => 'Internal Server Error'];
const INVALID_INPUT = ['error' => 'Invalid request.'];

function handleError($error, $httpCode = 500, &$stmt = null) {
    if ($stmt) $stmt->close();
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode($error);
    exit;
}

function validatePlatform($platform) {
    if (!is_string($platform) || empty($platform)) {
        return false;
    }

    $platform = trim($platform);
    return strlen($platform) <= 6 && in_array($platform, ALLOWED_PLATFORMS);
}

function executeQuery($db, $platform, &$stmt = null) {
    $stmt = $db->prepare("SELECT min_version, update_url FROM app_versions WHERE platform = ? LIMIT 1");
    if ($stmt === false) return false;

    if (!$stmt->bind_param("s", $platform) ||
        !$stmt->execute() ||
        ($result = $stmt->get_result()) === false) {
        return false;
    }

    if ($result->num_rows === 0) return false;

    $data = $result->fetch_assoc();
    $stmt->close();

    return $data;
}

$platform = $_GET['platform'] ?? null;

if (!validatePlatform($platform)) {
    handleError(INVALID_INPUT, 400);
}

$data = executeQuery($db, $platform, $stmt);

if ($data === false) {
    handleError(INTERNAL_ERROR);
}

http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => [
        'minVersion' => $data['min_version'],
        'updateUrl' => $data['update_url']
    ]
]);
?>
