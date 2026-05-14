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
$unit_id = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : null;

try {
    if (!empty($unit_id) && $unit_id > 0) {
        $stmt = $db->prepare("SELECT unit_id, unit_code FROM product_units WHERE unit_id = ?");
        $stmt->bind_param('i', $unit_id);
    } elseif (!empty($search)) {
        $stmt = $db->prepare("SELECT unit_id, unit_code FROM product_units WHERE unit_code LIKE ? LIMIT 20");
        $searchTerm = "%$search%";
        $stmt->bind_param('s', $searchTerm);
    } else {
        $stmt = $db->prepare("SELECT unit_id, unit_code FROM product_units");
    }

    if (!$stmt || !$stmt->execute()) {
        throw new Exception();
    }

    $result = $stmt->get_result();
    $units = [];

    while ($row = $result->fetch_assoc()) {
        $units[] = [
            'unit_id'   => (int)$row['unit_id'],
            'unit_code' => $row['unit_code'] ?? ''
        ];
    }

    echo json_encode(['success' => true, 'units' => $units]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
} finally {
    if (isset($stmt)) $stmt->close();
}
?>
