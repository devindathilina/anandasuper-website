<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/../../auth/auth.php';

function sendResponse($code, $message) {
    http_response_code($code);
    echo $message;
    exit;
}

function executeUpdate($db, $query, $types, ...$params) {
    $stmt = $db->prepare($query);
    if (!$stmt || !$stmt->bind_param($types, ...$params) || !$stmt->execute()) {
        return false;
    }
    $stmt->close();
    return true;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, 'Method not allowed.');
}

if (!isset($_POST['type'], $_POST['id'])) {
    sendResponse(400, 'Missing parameters.');
}

$type = $_POST['type'];
$id = (int)$_POST['id'];
$is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : null;
$is_available = isset($_POST['is_available']) ? (int)$_POST['is_available'] : null;

$config = [
    'customer' => ['table' => 'customers', 'id' => 'customer_id', 'msg' => 'Customer status updated successfully.'],
    'product' => ['table' => 'products', 'id' => 'product_id', 'msg' => 'Product status updated successfully.'],
    'product_category' => ['table' => 'product_category', 'id' => 'category_id', 'msg' => 'Product category status updated successfully.'],
    'product_unit' => ['table' => 'product_units', 'id' => 'unit_id', 'msg' => 'Product unit status updated successfully.'],
    'offer' => ['table' => 'offers', 'id' => 'offer_id', 'msg' => 'Offer status updated successfully.'],
    'payment_method' => ['table' => 'payment_methods', 'id' => 'payment_method_id', 'msg' => 'Payment method status updated successfully.'],
    'service' => ['table' => 'services', 'id' => 'service_id', 'msg' => 'Service status updated successfully.'],
    'admin' => ['table' => 'ananda_super_admin', 'id' => 'admin_id', 'msg' => 'Admin status updated successfully.', 'super_admin_only' => true]
];

if (!isset($config[$type])) {
    sendResponse(400, 'Invalid type.');
}

$cfg = $config[$type];

if (isset($cfg['super_admin_only']) && $cfg['super_admin_only']) {
    $admin_info = getAnandaSuperAdminInfo();
    if (!$admin_info || $admin_info['admin_role'] !== 'Super Admin') {
        sendResponse(403, 'Access denied. Super Admin privileges required.');
    }
}

if ($type === 'product' && $is_available !== null) {
    if (executeUpdate($db, "UPDATE {$cfg['table']} SET is_available = ? WHERE {$cfg['id']} = ?", 'ii', $is_available, $id)) {
        echo 'Product availability updated successfully.';
    } else {
        sendResponse(500, 'Internal Server Error');
    }
    exit;
}

if ($is_active === null) {
    sendResponse(400, 'Missing is_active parameter.');
}

if (executeUpdate($db, "UPDATE {$cfg['table']} SET is_active = ? WHERE {$cfg['id']} = ?", 'ii', $is_active, $id)) {
    echo $cfg['msg'];
} else {
    sendResponse(500, 'Internal Server Error');
}
?>
