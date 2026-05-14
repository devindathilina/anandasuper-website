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
$service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : null;

try {
    if (!empty($service_id) && $service_id > 0) {
        $stmt = $db->prepare("SELECT service_id, service_name, service_type, is_active FROM services WHERE service_id = ?");
        $stmt->bind_param('i', $service_id);
    } elseif (!empty($search)) {
        $stmt = $db->prepare("SELECT service_id, service_name, service_type, is_active FROM services WHERE service_name LIKE ? LIMIT 20");
        $searchTerm = "%$search%";
        $stmt->bind_param('s', $searchTerm);
    } else {
        $stmt = $db->prepare("SELECT service_id, service_name, service_type, is_active FROM services");
    }

    if (!$stmt || !$stmt->execute()) {
        throw new Exception();
    }

    $result = $stmt->get_result();
    $services = [];

    while ($row = $result->fetch_assoc()) {
        $services[] = [
            'service_id'   => (int)$row['service_id'],
            'service_name' => $row['service_name'] ?? '',
            'service_type' => $row['service_type'] ?? '',
            'is_active'    => (int)$row['is_active']
        ];
    }

    echo json_encode(['success' => true, 'services' => $services]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
} finally {
    if (isset($stmt)) $stmt->close();
}
?>
