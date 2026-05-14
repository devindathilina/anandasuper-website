<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/../../auth/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method Not Allowed']));
}

try {
    $date_start = trim($_GET['date_start'] ?? '');
    $date_end = trim($_GET['date_end'] ?? '');

    $orderConditions = ["o.is_active = 1", "o.status = 'Picked Up'", "o.payment_status = 'Paid'"];
    $billReloadConditions = ["bro.status = 'Completed'", "bro.payment_status = 'Paid'"];
    $orderParams = [];
    $billReloadParams = [];
    $orderTypes = '';
    $billReloadTypes = '';

    if (!empty($date_start)) {
        $orderConditions[] = "o.ordered_datetime >= ?";
        $orderParams[] = $date_start . ' 00:00:00';
        $orderTypes .= 's';
        $billReloadConditions[] = "COALESCE(bro.processed_at, bro.created_at) >= ?";
        $billReloadParams[] = $date_start . ' 00:00:00';
        $billReloadTypes .= 's';
    }
    if (!empty($date_end)) {
        $orderConditions[] = "o.ordered_datetime <= ?";
        $orderParams[] = $date_end . ' 23:59:59';
        $orderTypes .= 's';
        $billReloadConditions[] = "COALESCE(bro.processed_at, bro.created_at) <= ?";
        $billReloadParams[] = $date_end . ' 23:59:59';
        $billReloadTypes .= 's';
    }

    $orderWhere = 'WHERE ' . implode(' AND ', $orderConditions);
    $billReloadWhere = 'WHERE ' . implode(' AND ', $billReloadConditions);

    $summaryStmt = $db->prepare("
        SELECT
            COALESCE(SUM(combined.order_count), 0) as total_orders,
            COALESCE(SUM(combined.total_sales), 0) as total_sales,
            COALESCE(SUM(combined.total_sales) / NULLIF(SUM(combined.order_count), 0), 0) as avg_order_value
        FROM (
            SELECT
                COUNT(DISTINCT o.order_id) as order_count,
                COALESCE(SUM(o.order_total), 0) as total_sales
            FROM orders o
            $orderWhere

            UNION ALL

            SELECT
                COUNT(DISTINCT bro.bill_reload_order_id) as order_count,
                COALESCE(SUM(bro.total_amount), 0) as total_sales
            FROM bill_reload_orders bro
            $billReloadWhere
        ) combined
    ");

    $summaryParams = array_merge($orderParams, $billReloadParams);
    $summaryTypes = $orderTypes . $billReloadTypes;
    if (!empty($summaryParams)) {
        $summaryStmt->bind_param($summaryTypes, ...$summaryParams);
    }
    $summaryStmt->execute();
    $summary = $summaryStmt->get_result()->fetch_assoc();
    $summaryStmt->close();

    $itemsStmt = $db->prepare("
        SELECT COALESCE(SUM(oi.qty), 0) as total_items_sold
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.order_id
        WHERE oi.is_active = 1 AND o.is_active = 1 AND o.status = 'Picked Up' AND o.payment_status = 'Paid'
    ");
    $itemsStmt->execute();
    $totalItemsSold = $itemsStmt->get_result()->fetch_assoc();
    $itemsStmt->close();

    $customersStmt = $db->prepare("SELECT COUNT(customer_id) as total_customers FROM customers WHERE is_active = 1");
    $customersStmt->execute();
    $totalCustomers = $customersStmt->get_result()->fetch_assoc();
    $customersStmt->close();

    $monthlyStmt = $db->prepare("
        SELECT
            monthly.month,
            DATE_FORMAT(STR_TO_DATE(CONCAT(monthly.month, '-01'), '%Y-%m-%d'), '%M %Y') as month_label,
            COALESCE(SUM(monthly.total_sales), 0) as total_sales,
            COALESCE(SUM(monthly.order_count), 0) as order_count
        FROM (
            SELECT
                DATE_FORMAT(o.ordered_datetime, '%Y-%m') as month,
                COALESCE(SUM(o.order_total), 0) as total_sales,
                COUNT(o.order_id) as order_count
            FROM orders o
            WHERE o.is_active = 1 AND o.status = 'Picked Up' AND o.payment_status = 'Paid'
            GROUP BY DATE_FORMAT(o.ordered_datetime, '%Y-%m')

            UNION ALL

            SELECT
                DATE_FORMAT(COALESCE(bro.processed_at, bro.created_at), '%Y-%m') as month,
                COALESCE(SUM(bro.total_amount), 0) as total_sales,
                COUNT(bro.bill_reload_order_id) as order_count
            FROM bill_reload_orders bro
            WHERE bro.status = 'Completed' AND bro.payment_status = 'Paid'
            GROUP BY DATE_FORMAT(COALESCE(bro.processed_at, bro.created_at), '%Y-%m')
        ) monthly
        GROUP BY monthly.month
        ORDER BY month ASC
        LIMIT 12
    ");
    $monthlyStmt->execute();
    $monthlyResult = $monthlyStmt->get_result();
    $monthlyData = [];
    while ($row = $monthlyResult->fetch_assoc()) {
        $monthlyData[] = [
            'month' => $row['month'],
            'month_label' => $row['month_label'],
            'total_sales' => (float)$row['total_sales'],
            'order_count' => (int)$row['order_count']
        ];
    }
    $monthlyStmt->close();

    $custMonthlyStmt = $db->prepare("
        SELECT
            DATE_FORMAT(c.created_at, '%Y-%m') as month,
            DATE_FORMAT(c.created_at, '%M %Y') as month_label,
            COUNT(c.customer_id) as new_customers
        FROM customers c
        WHERE c.is_active = 1
        GROUP BY DATE_FORMAT(c.created_at, '%Y-%m'), DATE_FORMAT(c.created_at, '%M %Y')
        ORDER BY month ASC
        LIMIT 12
    ");
    $custMonthlyStmt->execute();
    $custMonthlyResult = $custMonthlyStmt->get_result();
    $customerMonthlyData = [];
    while ($row = $custMonthlyResult->fetch_assoc()) {
        $customerMonthlyData[] = [
            'month' => $row['month'],
            'month_label' => $row['month_label'],
            'new_customers' => (int)$row['new_customers']
        ];
    }
    $custMonthlyStmt->close();

    $growthData = [];
    $prevSales = $prevCustomers = null;
    foreach ($monthlyData as $i => $month) {
        $sales = $month['total_sales'];
        $growth = ($prevSales && $prevSales > 0) ? (($sales - $prevSales) / $prevSales) * 100 : 0;

        $custGrowth = 0;
        $newCustomers = 0;
        if (isset($customerMonthlyData[$i])) {
            $newCustomers = $customerMonthlyData[$i]['new_customers'];
            $custGrowth = ($prevCustomers && $prevCustomers > 0) ? (($newCustomers - $prevCustomers) / $prevCustomers) * 100 : 0;
            $prevCustomers = $newCustomers;
        }

        $growthData[] = [
            'month' => $month['month'],
            'month_label' => $month['month_label'],
            'growth' => round($growth, 2),
            'new_customers' => $newCustomers,
            'customer_growth' => round($custGrowth, 2)
        ];
        $prevSales = $sales;
    }

    $oneYearAgo = date('Y-m-d', strtotime('-1 year')) . ' 00:00:00';
    $popularStmt = $db->prepare("
        SELECT 
            p.product_id,
            p.product_name,
            p.product_image,
            SUM(oi.qty) as total_qty_sold,
            SUM(oi.price_total) as total_revenue,
            COUNT(DISTINCT oi.order_id) as order_count
        FROM order_items oi
        INNER JOIN products p ON oi.product_id = p.product_id
        INNER JOIN orders o ON oi.order_id = o.order_id
        WHERE oi.is_active = 1 AND o.is_active = 1 AND o.status = 'Picked Up' AND o.payment_status = 'Paid' AND o.ordered_datetime >= ?
        GROUP BY p.product_id, p.product_name, p.product_image
        ORDER BY total_qty_sold DESC
        LIMIT 10
    ");
    $popularStmt->bind_param('s', $oneYearAgo);
    $popularStmt->execute();
    $popularResult = $popularStmt->get_result();
    $popularProducts = [];
    while ($row = $popularResult->fetch_assoc()) {
        $popularProducts[] = [
            'product_id' => (int)$row['product_id'],
            'product_name' => $row['product_name'],
            'product_image' => $row['product_image'],
            'total_qty_sold' => (int)$row['total_qty_sold'],
            'total_revenue' => (float)$row['total_revenue'],
            'order_count' => (int)$row['order_count']
        ];
    }
    $popularStmt->close();

    echo json_encode([
        'success' => true,
        'summary' => [
            'total_orders' => (int)$summary['total_orders'],
            'total_sales' => (float)$summary['total_sales'],
            'avg_order_value' => (float)$summary['avg_order_value'],
            'total_items_sold' => (int)$totalItemsSold['total_items_sold'],
            'total_customers' => (int)$totalCustomers['total_customers']
        ],
        'monthly_data' => $monthlyData,
        'growth_data' => $growthData,
        'popular_products' => $popularProducts
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
}
?>
