<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/../../auth/auth.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search = mb_substr($search, 0, 100, 'UTF-8');
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : null;

try {
    if (!empty($category_id) && $category_id > 0) {
        $stmt = $db->prepare("SELECT category_id, category_name FROM product_category WHERE category_id = ?");
        $stmt->bind_param('i', $category_id);
    } elseif (!empty($search)) {
        $stmt = $db->prepare("SELECT category_id, category_name FROM product_category WHERE category_name LIKE ? LIMIT 20");
        $searchTerm = "%$search%";
        $stmt->bind_param('s', $searchTerm);
    } else {
        $stmt = $db->prepare("SELECT category_id, category_name FROM product_category");
    }

    if (!$stmt || !$stmt->execute()) {
        throw new Exception();
    }

    $result = $stmt->get_result();
    $categories = [];

    while ($row = $result->fetch_assoc()) {
        $categories[] = [
            'category_id'   => (int)$row['category_id'],
            'category_name' => $row['category_name'] ?? ''
        ];
    }

    echo json_encode(['success' => true, 'categories' => $categories]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
} finally {
    if (isset($stmt)) $stmt->close();
}
?>