<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/db.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../');
$dotenv->load();

const ERRORS = [
    'invalid_method'     => 'Invalid request method.',
    'invalid_json'       => 'Invalid request body.',
    'unauthorized'       => 'Unauthorized access.',
    'missing_fields'     => 'Missing required fields.',
    'invalid_price_type' => 'Invalid price type. Must be Wholesale or Retail.',
    'product_not_found'  => 'Product not found.',
    'server_error'       => 'Internal server error.'
];

const VALID_PRICE_TYPES   = ['Wholesale', 'Retail'];
const SIMILARITY_THRESHOLD = 60;

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

function validateSecretKey() {
    $secretKey = $_ENV['N8N_SECRET_KEY'] ?? '';
    if (empty($secretKey)) {
        handleError('server_error');
    }

    $providedKey = $_SERVER['HTTP_X_N8N_SECRET'] ?? '';
    if ($providedKey !== $secretKey) {
        handleError('unauthorized', null, 401);
    }
}

function validateInput($data) {
    if (empty($data)) {
        handleError('invalid_json');
    }

    if (!isset($data['price_type']) || $data['price_type'] === '') {
        handleError('missing_fields', 'price_type is required.');
    }

    if (!in_array($data['price_type'], VALID_PRICE_TYPES)) {
        handleError('invalid_price_type');
    }

    if (!isset($data['product_name']) || trim($data['product_name']) === '') {
        handleError('missing_fields', 'product_name is required.');
    }

    return [
        'price_type'   => $data['price_type'],
        'product_name' => trim($data['product_name'])
    ];
}

function getActiveProducts($db) {
    $sql = "SELECT p.product_id, p.product_name, p.unit_id, p.wholesale_price, p.retail_price,
                   p.product_image, p.is_available, p.is_active,
                   pu.unit_code
            FROM products p
            INNER JOIN product_units pu ON p.unit_id = pu.unit_id
            WHERE p.is_active = 1 AND p.is_available = 1
            ORDER BY p.product_name";

    $result = $db->query($sql);
    if ($result === false) {
        return [];
    }

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }

    return $products;
}

function calculateSimilarity($str1, $str2) {
    $str1 = strtolower(trim($str1));
    $str2 = strtolower(trim($str2));

    similar_text($str1, $str2, $percent);
    return $percent;
}

function matchProductByName($db, $productName, $priceType) {
    $products = getActiveProducts($db);

    $bestMatch = null;
    $bestScore = 0;

    foreach ($products as $product) {
        $score = calculateSimilarity($productName, $product['product_name']);

        if (strpos($product['product_name'], $productName) !== false ||
            strpos($productName, $product['product_name']) !== false) {
            $score = max($score, 75);
        }

        if ($score > $bestScore && $score >= SIMILARITY_THRESHOLD) {
            $bestScore = $score;
            $bestMatch = $product;
        }
    }

    if ($bestMatch === null) {
        return null;
    }

    $price = $priceType === 'Wholesale'
        ? (float)$bestMatch['wholesale_price']
        : (float)$bestMatch['retail_price'];

    return [
        'product_id'       => (int)$bestMatch['product_id'],
        'product_name'     => $bestMatch['product_name'],
        'unit_id'          => (int)$bestMatch['unit_id'],
        'unit_code'        => $bestMatch['unit_code'],
        'wholesale_price'  => (float)$bestMatch['wholesale_price'],
        'retail_price'     => (float)$bestMatch['retail_price'],
        'price'            => $price,
        'product_image'    => $bestMatch['product_image'] ?? null,
        'category_name'    => null,
        'confidence_score' => round($bestScore, 2),
        'matched_name'     => $productName
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    handleError('invalid_method');
}

validateSecretKey();

$data  = json_decode(file_get_contents('php://input'), true);
$input = validateInput($data);

try {
    $product = matchProductByName($db, $input['product_name'], $input['price_type']);

    if ($product === null) {
        sendResponse([
            'success' => false,
            'message' => 'No matching product found',
            'data'    => null
        ], 200);
    }

    sendResponse([
        'success' => true,
        'message' => 'Product matched successfully',
        'data'    => $product
    ]);

} catch (Exception $e) {
    handleError('server_error');
}
?>