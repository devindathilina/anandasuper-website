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
$payment_method_id = isset($_GET['payment_method_id']) ? intval($_GET['payment_method_id']) : null;

try {
    if (!empty($payment_method_id) && $payment_method_id > 0) {
        $stmt = $db->prepare("SELECT payment_method_id, payment_method_name FROM payment_methods WHERE payment_method_id = ?");
        $stmt->bind_param('i', $payment_method_id);
    } elseif (!empty($search)) {
        $stmt = $db->prepare("SELECT payment_method_id, payment_method_name FROM payment_methods WHERE payment_method_name LIKE ? LIMIT 20");
        $searchTerm = "%$search%";
        $stmt->bind_param('s', $searchTerm);
    } else {
        $stmt = $db->prepare("SELECT payment_method_id, payment_method_name FROM payment_methods");
    }

    if (!$stmt || !$stmt->execute()) {
        throw new Exception();
    }

    $result = $stmt->get_result();
    $payment_methods = [];

    while ($row = $result->fetch_assoc()) {
        $payment_methods[] = [
            'payment_method_id' => (int)$row['payment_method_id'],
            'payment_method_name' => $row['payment_method_name'] ?? ''
        ];
    }

    echo json_encode(['success' => true, 'payment_methods' => $payment_methods]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
} finally {
    if (isset($stmt)) $stmt->close();
}
?>