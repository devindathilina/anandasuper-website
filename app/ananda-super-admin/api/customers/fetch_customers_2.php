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
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : null;

try {
    if (!empty($customer_id) && $customer_id > 0) {
        $stmt = $db->prepare("SELECT customer_id, first_name, last_name, phone FROM customers WHERE customer_id = ?");
        $stmt->bind_param('i', $customer_id);
    } elseif (!empty($search)) {
        $stmt = $db->prepare("SELECT customer_id, first_name, last_name, phone
                             FROM customers
                             WHERE (first_name LIKE ? OR last_name LIKE ? OR phone LIKE ?)
                             LIMIT 20");
        $searchTerm = "%$search%";
        $stmt->bind_param('sss', $searchTerm, $searchTerm, $searchTerm);
    } else {
        $stmt = $db->prepare("SELECT customer_id, first_name, last_name, phone FROM customers WHERE is_active = 1 ORDER BY customer_id DESC LIMIT 100");
    }

    if (!$stmt || !$stmt->execute()) {
        throw new Exception();
    }

    $result = $stmt->get_result();
    $customers = [];

    while ($row = $result->fetch_assoc()) {
        $fullName = trim($row['first_name'] . ' ' . $row['last_name']);
        $customers[] = [
            'customer_id'   => (int)$row['customer_id'],
            'customer_name' => $fullName ?? '',
            'phone'          => $row['phone'] ?? ''
        ];
    }

    echo json_encode(['success' => true, 'customers' => $customers]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
} finally {
    if (isset($stmt)) $stmt->close();
}
?>
