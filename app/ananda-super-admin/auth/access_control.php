<?php
if (!defined('ANANDA_SUPER_SECURE_ACCESS')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

$file_name = basename($_SERVER['PHP_SELF']);
$admin_info = getAnandaSuperAdminInfo();
$admin_role = $admin_info['admin_role'] ?? null;

if (!$admin_role) {
    header('Location: index.php');
    exit;
}

if ($admin_role === 'Super Admin') {
    return;
}

$role_access = [
    'Normal Admin' => [
        'dashboard.php',
        'change_admin_password.php',
        'customers.php',
        'add_customer.php',
        'edit_customer.php',
        'orders.php',
        'view_order.php',
        'update_order_status.php',
        'update_order_notes.php',
        'bill_reload_orders.php',
        'view_bill_reload_order.php',
        'update_bill_reload_order_status.php',
        'update_bill_reload_order_notes.php',
        'products.php',
        'add_product.php',
        'edit_product.php',
        'product_categories.php',
        'add_product_category.php',
        'edit_product_category.php',
        'product_units.php',
        'add_product_unit.php',
        'edit_product_unit.php',
        'services.php',
        'add_service.php',
        'edit_service.php',
        'offers.php',
        'add_offer.php',
        'edit_offer.php',
        'fetch_customers.php',
        'fetch_orders.php',
        'fetch_bill_reload_orders.php',
        'fetch_products.php',
        'fetch_product_category.php',
        'fetch_product_units.php',
        'fetch_services.php',
        'fetch_offers.php',
        'fetch_dashboard.php',
        'fetch_counts.php',
        'change_status.php'
    ]
];

$allowed_files = $role_access[$admin_role] ?? [];

if (in_array($file_name, $allowed_files, true)) {
    return;
}

header('Location: dashboard.php');
exit();
?>