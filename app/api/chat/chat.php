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
    'server_error' => 'Internal server error.'
];

const ALLOWED_FIELDS = ['session_token', 'message', 'history'];

const SYSTEM_PROMPT = 'You are the official customer support assistant for Ananda Super - Kadangoda, a grocery and household items store located in Kuruwita, Sri Lanka.

Your role is to help customers with:
- Grocery product inquiries
- Product prices
- Stock availability
- Shop information
- Opening hours
- Contact details
- Order guidance through the app

Response Style:
- Use a friendly, polite, and professional Sinhala + English mixed language style.
- Keep replies short, natural, and easy to understand.
- Avoid robotic, repetitive, or overly formal replies.
- Use emojis moderately for friendliness.

Store Information:
- Shop Name: Ananda Super - Kadangoda
- Location: Q8MW+34M, Kuruwita 70500
- Open Daily: 6.00 AM – 10.30 PM
- Contact Numbers:
  - 0452262712
  - 0776761835

Important Rules:
- Delivery service is currently NOT available.
- Customers should visit the store directly for purchases.
- Never guess prices, stock, or product details.
- Do NOT ask users to upload images in the chat.
- The Upload Order feature exists separately inside the app.

Product Inquiry Handling:
- For product searches, prices, and stock availability, guide customers to:
  - Use the Products section in the app
  - OR use the Upload Order feature available inside the app

Upload Order Guidance:
- Explain that customers can use the Upload Order feature inside the app to upload:
  - A clear handwritten item list
  - A printed item list
  - Product photos
- The image should clearly show:
  - Product names
  - Required quantities
- Never tell users to upload images directly into the chat.

Example Replies:

Delivery:
"Sorry 😊 Currently delivery service is not available. Please visit our store for purchases."

Opening Hours:
"අපි හැමදාම උදේ 6.00 AM සිට රාත්‍රී 10.30 PM දක්වා open 😊"

Location:
"📍 Ananda Super - Kadangoda, Q8MW+34M, Kuruwita 70500"

Contact:
"📞 0452262712 / 0776761835"

Product Inquiry:
"Please use the Products section in the app to search items 😊"

Upload Order:
"You can use the Upload Order feature inside the app to upload a clear handwritten or printed item list with quantities 😊"

Unclear Product Request:
"Please check the Products section in the app or use the Upload Order feature for better assistance 😊"

Always prioritize helpful, polite, and customer-friendly communication.';

function sendResponse($data, $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function handleError($errorKey, $customMessage = null, $httpCode = 400) {
    $message = $customMessage ?? ERRORS[$errorKey] ?? ERRORS['server_error'];
    sendResponse(['success' => false, 'message' => $message], $httpCode);
}

const MAX_HISTORY = 10;

function callGeminiAPI($apiKey, $message, $history = [], $customerName = '') {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    $userMessage = $message;
    if (!empty($customerName)) {
        $userMessage = "Customer name: {$customerName}\n\n" . $message;
    }

    $messages = [];
    foreach ($history as $h) {
        $messages[] = [
            'role' => $h['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $h['content']]]
        ];
    }
    $messages[] = [
        'role' => 'user',
        'parts' => [['text' => $userMessage]]
    ];

    $payload = [
        'contents' => $messages,
        'systemInstruction' => [
            'parts' => [['text' => SYSTEM_PROMPT]]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 500
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return null;
    }

    $data = json_decode($response, true);
    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        return $data['candidates'][0]['content']['parts'][0]['text'];
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    handleError('invalid_method');
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data)) {
    handleError('invalid_json');
}

foreach (array_keys($data) as $key) {
    if (!in_array($key, ALLOWED_FIELDS)) {
        handleError('invalid_fields', "Field '$key' is not allowed.");
    }
}

if (empty($data['session_token'])) {
    handleError('missing_token');
}

if (empty($data['message'])) {
    handleError('missing_fields', 'message is required.');
}

$session_token = trim($data['session_token']);
$message = trim($data['message']);

$history = [];
if (isset($data['history']) && is_array($data['history'])) {
    $historyCount = 0;
    foreach ($data['history'] as $h) {
        if ($historyCount >= MAX_HISTORY) break;
        if (isset($h['role']) && isset($h['content'])) {
            $history[] = ['role' => $h['role'], 'content' => $h['content']];
            $historyCount++;
        }
    }
}

if (strlen($message) > 1000) {
    handleError('invalid_fields', 'Message is too long.');
}

$authResult = validateCustomerAuthentication($session_token, $db, $_ENV['JWT_SECRET_KEY']);
if (!$authResult['success']) {
    handleError('auth_failed', $authResult['message'], 401);
}

$customerName = trim($authResult['customer_data']['first_name'] . ' ' . $authResult['customer_data']['last_name']);

$apiKey = $_ENV['GEMINI_API_KEY'] ?? '';

if (empty($apiKey)) {
    handleError('server_error', 'Internal server error.');
}

$responseText = callGeminiAPI($apiKey, $message, $history, $customerName);

if ($responseText === null) {
    handleError('server_error', 'Failed to get response from AI.');
}

sendResponse([
    'success' => true,
    'message' => 'Response generated successfully.',
    'data' => [
        'reply' => $responseText
    ]
]);
?>