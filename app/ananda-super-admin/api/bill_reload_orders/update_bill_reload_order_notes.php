<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/../../auth/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['bill_reload_order_id']) || !isset($_POST['admin_notes'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$bill_reload_order_id = intval($_POST['bill_reload_order_id']);
$admin_notes = trim($_POST['admin_notes']);

if ($bill_reload_order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

if (strlen($admin_notes) > 500) {
    echo json_encode(['success' => false, 'message' => 'Admin notes cannot exceed 500 characters']);
    exit;
}

try {
    $stmt = $db->prepare("
        UPDATE bill_reload_orders
        SET note_store = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE bill_reload_order_id = ?
    ");

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
        exit;
    }

    $null = null;
    $note = empty($admin_notes) ? $null : $admin_notes;
    $stmt->bind_param('si', $note, $bill_reload_order_id);

    if (!$stmt->execute()) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Failed to update notes']);
        exit;
    }

    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Admin notes updated successfully']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>
