<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/../../auth/auth.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$countConfigurations = [
    'order' => ['type' => 'order_counts'],
    'bill_reload_order' => ['type' => 'bill_reload_order_counts'],
    'customer' => ['type' => 'date_counts', 'table' => 'customers'],
    'offer' => ['type' => 'offer_counts'],
    'admin' => ['type' => 'admin_counts'],
    'product' => ['type' => 'product_counts'],
    'product_category' => ['type' => 'date_counts', 'table' => 'product_category'],
    'product_unit' => ['type' => 'is_active', 'table' => 'product_units'],
    'payment_method' => ['type' => 'date_counts', 'table' => 'payment_methods'],
    'service' => ['type' => 'service_counts']
];

$countType = $_REQUEST['type'] ?? '';
if (!isset($countConfigurations[$countType])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid count type']);
    exit;
}

$admin_info = getAnandaSuperAdminInfo();

$super_admin_only_types = ['offer', 'admin'];
if (!$admin_info || $admin_info['admin_role'] !== 'Super Admin' && in_array($countType, $super_admin_only_types, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. This operation requires Super Admin privileges.']);
    exit;
}

function executeQuery($db, $query, $params = [], $types = '') {
    if (empty($params)) {
        $result = $db->query($query);
        if (!$result) throw new Exception('Query failed');
        return $result->fetch_assoc();
    }

    $stmt = $db->prepare($query);
    if (!$stmt) throw new Exception('Query failed');

    if (!empty($types) && !$stmt->bind_param($types, ...$params)) {
        $stmt->close();
        throw new Exception('Query failed');
    }

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Query failed');
    }

    $counts = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $counts;
}

function sendCounts($counts) {
    $filtered = array_filter($counts, fn($v) => is_int($v) || is_string($v));
    echo json_encode(['success' => true, 'counts' => array_map('intval', $filtered)]);
}

try {
    $config = $countConfigurations[$countType];

    if ($config['type'] === 'is_active') {
        sendCounts(executeQuery($db, "SELECT COUNT(*) as total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
            FROM `{$config['table']}`"));

    } elseif ($config['type'] === 'order_counts') {
        sendCounts(executeQuery($db, "SELECT COUNT(*) as total,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'Preparing' THEN 1 ELSE 0 END) as preparing,
            SUM(CASE WHEN status = 'Ready' THEN 1 ELSE 0 END) as ready,
            SUM(CASE WHEN status = 'Picked Up' THEN 1 ELSE 0 END) as picked_up,
            SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN status = 'Refunded' THEN 1 ELSE 0 END) as refunded
            FROM orders"));

    } elseif ($config['type'] === 'date_counts') {
        $table = $config['table'];
        $dateField = $table === 'customers' ? 'created_at' : 'created_at';
        $today = date('Y-m-d');
        $weekAgo = date('Y-m-d', strtotime('-7 days'));
        $monthAgo = date('Y-m-d', strtotime('-30 days'));
        $weekAgoStart = $table === 'customers' ? $weekAgo . ' 00:00:00' : $weekAgo;
        $todayEnd = $table === 'customers' ? $today . ' 23:59:59' : $today;

        sendCounts(executeQuery($db, "SELECT COUNT(*) as total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive,
            SUM(CASE WHEN DATE($dateField) = ? THEN 1 ELSE 0 END) as joined_today,
            SUM(CASE WHEN $dateField >= ? AND $dateField <= ? THEN 1 ELSE 0 END) as joined_this_week,
            SUM(CASE WHEN $dateField >= ? AND $dateField <= ? THEN 1 ELSE 0 END) as joined_this_month
            FROM $table",
            [$today, $weekAgoStart, $todayEnd, $monthAgo, $todayEnd], 'sssss'));

    } elseif ($config['type'] === 'offer_counts') {
        $today = date('Y-m-d');
        sendCounts(executeQuery($db, "SELECT COUNT(*) as total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive,
            SUM(CASE WHEN is_active = 1 AND ? >= start_date AND ? <= end_date THEN 1 ELSE 0 END) as running_now,
            SUM(CASE WHEN start_date > ? THEN 1 ELSE 0 END) as upcoming,
            SUM(CASE WHEN end_date < ? THEN 1 ELSE 0 END) as expired
            FROM offers",
            [$today, $today, $today, $today], 'ssss'));

    } elseif ($config['type'] === 'admin_counts') {
        $today = date('Y-m-d');
        $weekAgo = date('Y-m-d', strtotime('-7 days'));
        $monthAgo = date('Y-m-d', strtotime('-30 days'));

        sendCounts(executeQuery($db, "SELECT COUNT(*) as total,
            SUM(CASE WHEN admin_is_active = 1 THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN admin_is_active = 0 THEN 1 ELSE 0 END) as inactive,
            SUM(CASE WHEN admin_role = 'Super Admin' THEN 1 ELSE 0 END) as super_admin,
            SUM(CASE WHEN admin_role = 'Normal Admin' THEN 1 ELSE 0 END) as normal_admin,
            SUM(CASE WHEN DATE(admin_created_at) = ? THEN 1 ELSE 0 END) as joined_today,
            SUM(CASE WHEN admin_created_at >= ? AND admin_created_at <= ? THEN 1 ELSE 0 END) as joined_this_week,
            SUM(CASE WHEN admin_created_at >= ? AND admin_created_at <= ? THEN 1 ELSE 0 END) as joined_this_month
            FROM ananda_super_admin",
            [$today, $weekAgo . ' 00:00:00', $today . ' 23:59:59', $monthAgo . ' 00:00:00', $today . ' 23:59:59'], 'sssss'));

    } elseif ($config['type'] === 'product_counts') {
        sendCounts(executeQuery($db, "SELECT COUNT(*) as total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive,
            SUM(CASE WHEN is_available = 1 THEN 1 ELSE 0 END) as available,
            SUM(CASE WHEN is_available = 0 THEN 1 ELSE 0 END) as unavailable,
            SUM(CASE WHEN qty <= low_stock_threshold AND qty > 0 THEN 1 ELSE 0 END) as low_stock,
            SUM(CASE WHEN qty = 0 THEN 1 ELSE 0 END) as out_of_stock
            FROM products"));

    } elseif ($config['type'] === 'service_counts') {
        sendCounts(executeQuery($db, "SELECT COUNT(*) as total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive,
            SUM(CASE WHEN service_type = 'Bill' THEN 1 ELSE 0 END) as bill,
            SUM(CASE WHEN service_type = 'Reload' THEN 1 ELSE 0 END) as reload
            FROM services"));

    } elseif ($config['type'] === 'bill_reload_order_counts') {
        sendCounts(executeQuery($db, "SELECT COUNT(*) as total,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'Processing' THEN 1 ELSE 0 END) as processing,
            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'Failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = 'Refunded' THEN 1 ELSE 0 END) as refunded
            FROM bill_reload_orders"));
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>