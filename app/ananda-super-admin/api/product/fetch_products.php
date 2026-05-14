<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/../../auth/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_POST['draw']) || !isset($_POST['start']) || !isset($_POST['length'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid Input']);
        die();
    }

    $draw = intval($_POST['draw']);
    $row = intval($_POST['start']);
    $rowperpage = intval($_POST['length']);
    $searchValue = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';
    $category_filter = isset($_POST['category_id']) ? $_POST['category_id'] : null;
    $is_active_filter = isset($_POST['is_active']) ? $_POST['is_active'] : null;
    $is_available_filter = isset($_POST['is_available']) ? $_POST['is_available'] : null;

    $totalRecordsQuery = "SELECT COUNT(*) as total FROM products";
    $stmt = $db->prepare($totalRecordsQuery);
    if (!$stmt || !$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'Internal Server Error']);
        die();
    }
    $totalRecords = $stmt->get_result()->fetch_row()[0];
    $stmt->close();

    $conditions = [];
    $params = [];
    $types = '';

    if (!empty($searchValue)) {
        $conditions[] = "(p.product_name LIKE ? OR p.barcode LIKE ?)";
        $searchTerm = "%$searchValue%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }

    if (!empty($category_filter) && intval($category_filter) > 0) {
        $conditions[] = "p.category_id = ?";
        $params[] = intval($category_filter);
        $types .= 'i';
    }

    if ($is_active_filter === '0' || $is_active_filter === '1') {
        $conditions[] = "p.is_active = ?";
        $params[] = intval($is_active_filter);
        $types .= 'i';
    }

    if ($is_available_filter === '0' || $is_available_filter === '1') {
        $conditions[] = "p.is_available = ?";
        $params[] = intval($is_available_filter);
        $types .= 'i';
    }

    $whereClause = '';
    if (!empty($conditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
    }

    $totalFilteredQuery = "SELECT COUNT(*) as total FROM products p $whereClause";

    $stmt = $db->prepare($totalFilteredQuery);
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Internal Server Error']);
        die();
    }
    if (!empty($params) && !$stmt->bind_param($types, ...$params)) {
        $stmt->close();
        echo json_encode(['success' => false, 'error' => 'Internal Server Error']);
        die();
    }
    if (!$stmt->execute()) {
        $stmt->close();
        echo json_encode(['success' => false, 'error' => 'Internal Server Error']);
        die();
    }
    $totalFiltered = $stmt->get_result()->fetch_row()[0];
    $stmt->close();

    $fetchQuery = "SELECT p.product_id, p.product_name, p.product_image, p.barcode, p.wholesale_price, p.retail_price,
                          p.qty, p.low_stock_threshold,
                          p.is_active, p.is_available, p.created_at, p.updated_at,
                          pc.category_name, pu.unit_code
                  FROM products p
                  LEFT JOIN product_category pc ON p.category_id = pc.category_id
                  LEFT JOIN product_units pu ON p.unit_id = pu.unit_id
                  $whereClause
                  ORDER BY p.product_id DESC
                  LIMIT ?, ?";

    $paramsWithLimit = array_merge($params, [$row, $rowperpage]);
    $typesWithLimit = $types . 'ii';

    $stmt = $db->prepare($fetchQuery);
    if (!$stmt || !$stmt->bind_param($typesWithLimit, ...$paramsWithLimit) || !$stmt->execute()) {
        if ($stmt) $stmt->close();
        echo json_encode(['success' => false, 'error' => 'Internal Server Error']);
        die();
    }

    $result = $stmt->get_result();
    if (!$result) {
        $stmt->close();
        echo json_encode(['success' => false, 'error' => 'Internal Server Error']);
        die();
    }

    $showNA = function($value) {
        return htmlspecialchars(empty($value) ? 'N/A' : $value, ENT_QUOTES, 'UTF-8');
    };

    $data = [];
    while ($row_data = $result->fetch_assoc()) {
        $qty = (float)$row_data['qty'];
        $lowStockThreshold = (int)$row_data['low_stock_threshold'];
        $isLowStock = $qty <= $lowStockThreshold;

        $data[] = [
            'product_id'            => (int)$row_data['product_id'],
            'product_name'          => htmlspecialchars($row_data['product_name'], ENT_QUOTES, 'UTF-8'),
            'product_image'         => htmlspecialchars($row_data['product_image'] ?? '', ENT_QUOTES, 'UTF-8'),
            'category_name'         => htmlspecialchars($row_data['category_name'] ?? '', ENT_QUOTES, 'UTF-8'),
            'unit_code'             => htmlspecialchars($row_data['unit_code'], ENT_QUOTES, 'UTF-8'),
            'barcode'               => htmlspecialchars($row_data['barcode'] ?? '', ENT_QUOTES, 'UTF-8'),
            'wholesale_price'       => (float)$row_data['wholesale_price'],
            'retail_price'          => (float)$row_data['retail_price'],
            'qty'                   => $qty,
            'low_stock_threshold'   => $lowStockThreshold,
            'is_low_stock'          => $isLowStock,
            'is_active'             => (int)$row_data['is_active'],
            'is_available'          => (int)$row_data['is_available'],
            'created_at'            => $showNA($row_data['created_at']),
            'updated_at'            => $showNA($row_data['updated_at'])
        ];
    }

    $stmt->close();

    $response = [
        "draw" => $draw,
        "recordsTotal" => $totalRecords,
        "recordsFiltered" => $totalFiltered,
        "data" => $data
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Internal Server Error']);
    die();
}
?>
