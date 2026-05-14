<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/db.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../');
$dotenv->load();

const ERRORS = [
    'invalid_method' => 'Invalid request method.',
    'invalid_json' => 'Invalid request body.',
    'unauthorized' => 'Unauthorized access.',
    'missing_fields' => 'Missing required fields.',
    'invalid_price_type' => 'Invalid price type. Must be Wholesale or Retail.',
    'server_error' => 'Internal server error.'
];

const VALID_PRICE_TYPES = ['Wholesale', 'Retail'];
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

    if (!isset($data['items']) || !is_array($data['items'])) {
        handleError('missing_fields', 'items array is required.');
    }

    $validatedItems = [];
    foreach ($data['items'] as $index => $item) {
        if (!is_array($item)) {
            handleError('invalid_json', "Item at index $index is not valid.");
        }

        if (!isset($item['item']) || trim($item['item']) === '') {
            handleError('missing_fields', "item name is required at index $index.");
        }

        $qty = isset($item['qty']) ? (int)$item['qty'] : 1;
        if ($qty < 1) {
            $qty = 1;
        }

        $validatedItems[] = [
            'raw_text' => trim($item['item']),
            'qty' => $qty
        ];
    }

    return [
        'price_type' => $data['price_type'],
        'items' => $validatedItems
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

function fuzzyMatchProducts($db, $items, $priceType) {
    $products = getActiveProducts($db);

    $matched = [];
    $unmatched = [];
    $matchedProductIds = [];

    foreach ($items as $item) {
        $rawText = $item['raw_text'];
        $qty = $item['qty'];
        $bestMatch = null;
        $bestScore = 0;

        foreach ($products as $product) {
            $productName = $product['product_name'];

            $score = calculateSimilarity($rawText, $productName);

            if (strpos($productName, $rawText) !== false || strpos($rawText, $productName) !== false) {
                $score = max($score, 75);
            }

            if ($score > $bestScore && $score >= SIMILARITY_THRESHOLD) {
                $bestScore = $score;
                $bestMatch = $product;
            }
        }

        if ($bestMatch !== null) {
            $productId = (int)$bestMatch['product_id'];

            $matchKey = "{$productId}_{$bestMatch['unit_id']}";

            if (isset($matchedProductIds[$matchKey])) {
                $matchedProductIds[$matchKey]['quantity'] += $qty;
            } else {
                $price = $priceType === 'Wholesale'
                    ? (float)$bestMatch['wholesale_price']
                    : (float)$bestMatch['retail_price'];

                $matchedItem = [
                    'product_id' => $productId,
                    'product_name' => $bestMatch['product_name'],
                    'unit_id' => (int)$bestMatch['unit_id'],
                    'unit_code' => $bestMatch['unit_code'],
                    'wholesale_price' => (float)$bestMatch['wholesale_price'],
                    'retail_price' => (float)$bestMatch['retail_price'],
                    'price' => $price,
                    'quantity' => $qty,
                    'product_image' => $bestMatch['product_image'] ?? null,
                    'category_name' => null,
                    'confidence_score' => round($bestScore, 2),
                    'raw_text' => $rawText
                ];

                $matched[] = $matchedItem;
                $matchedProductIds[$matchKey] = &$matched[count($matched) - 1];
            }
        } else {
            $unmatched[] = [
                'raw_text' => $rawText,
                'qty' => $qty
            ];
        }
    }

    $matched = array_values($matched);

    return [
        'matched' => $matched,
        'unmatched' => $unmatched
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    handleError('invalid_method');
}

validateSecretKey();

$data = json_decode(file_get_contents('php://input'), true);
$input = validateInput($data);

try {
    $result = fuzzyMatchProducts($db, $input['items'], $input['price_type']);

    sendResponse([
        'success' => true,
        'message' => 'Products matched successfully',
        'data' => $result
    ]);

} catch (Exception $e) {
    handleError('server_error');
}
?>
