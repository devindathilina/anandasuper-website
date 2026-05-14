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
    $is_active_filter = isset($_POST['is_active']) ? $_POST['is_active'] : null;

    $totalRecordsQuery = "SELECT COUNT(*) as total FROM product_category";
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
        $conditions[] = "category_name LIKE ?";
        $searchTerm = "%$searchValue%";
        $params[] = $searchTerm;
        $types .= 's';
    }

    if ($is_active_filter === '0' || $is_active_filter === '1') {
        $conditions[] = "is_active = ?";
        $params[] = intval($is_active_filter);
        $types .= 'i';
    }

    $whereClause = '';
    if (!empty($conditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
    }

    $totalFilteredQuery = "SELECT COUNT(*) as total FROM product_category $whereClause";

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

    $fetchQuery = "SELECT category_id, category_name, is_active, created_at, updated_at
                  FROM product_category $whereClause
                  ORDER BY category_id DESC
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
        $data[] = [
            'category_id'    => (int)$row_data['category_id'],
            'category_name'  => $showNA($row_data['category_name']),
            'is_active'      => (int)$row_data['is_active'],
            'created_at'     => $showNA($row_data['created_at']),
            'updated_at'     => $showNA($row_data['updated_at'])
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