<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/db.php';
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
    'invalid_image' => 'Invalid image data.',
    'invalid_price_type' => 'Invalid price type. Must be Wholesale or Retail.',
    'n8n_error' => 'Failed to process image with OCR service.',
    'server_error' => 'Internal server error.'
];

const VALID_PRICE_TYPES = ['Wholesale', 'Retail'];

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

    if (!isset($data['image_base64']) || $data['image_base64'] === '') {
        handleError('missing_fields', 'image_base64 is required.');
    }

    $imageData = $data['image_base64'];
    if (!preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/', $imageData)) {
        handleError('invalid_image', 'Image must be a valid base64 encoded JPEG, PNG, or WebP image.');
    }

    if (strlen($imageData) > 14000000) {
        handleError('invalid_image', 'Image size exceeds maximum allowed size of 10MB.');
    }

    return [
        'session_token' => $data['session_token'],
        'price_type' => $data['price_type'],
        'image_base64' => $imageData
    ];
}

function callN8NWebhook($imageBase64, $priceType) {
    $webhookUrl = $_ENV['N8N_UPLOAD_ORDER_WEBHOOK_URL'] ?? '';
    $secretKey = $_ENV['N8N_SECRET_KEY'] ?? '';

    if (empty($webhookUrl) || empty($secretKey)) {
        handleError('server_error');
    }

    $payload = json_encode([
        'image_base64' => $imageBase64,
        'price_type' => $priceType
    ]);

    $ch = curl_init($webhookUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-N8N-Secret: ' . $secretKey
        ],
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        handleError('n8n_error');
    }

    if ($httpCode !== 200) {
        handleError('n8n_error');
    }

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        handleError('n8n_error');
    }

    return $result;
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

try {
    $n8nResult = callN8NWebhook($input['image_base64'], $input['price_type']);

    if (!isset($n8nResult['success']) || $n8nResult['success'] !== true) {
        handleError('n8n_error');
    }

    sendResponse([
        'success' => true,
        'message' => 'Order processed successfully',
        'data' => $n8nResult['data'] ?? []
    ]);

} catch (Exception $e) {
    handleError('server_error');
}
?>
