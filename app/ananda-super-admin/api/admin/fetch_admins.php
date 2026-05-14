<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/../../auth/auth.php';

$admin_info = getAnandaSuperAdminInfo();
if (!$admin_info || $admin_info['admin_role'] !== 'Super Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    die();
}

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
    $admin_role_filter = isset($_POST['admin_role']) ? $_POST['admin_role'] : null;
    $date_start = isset($_POST['date_start']) ? trim($_POST['date_start']) : '';
    $date_end = isset($_POST['date_end']) ? trim($_POST['date_end']) : '';

    $totalRecordsQuery = "SELECT COUNT(*) as total FROM ananda_super_admin";
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
        $conditions[] = "(a.admin_username LIKE ? OR a.admin_first_name LIKE ? OR a.admin_last_name LIKE ? OR a.admin_email LIKE ?)";
        $searchTerm = "%$searchValue%";
        $params = array_fill(0, 4, $searchTerm);
        $types = 'ssss';
    }

    if ($is_active_filter === '0' || $is_active_filter === '1') {
        $conditions[] = "a.admin_is_active = ?";
        $params[] = intval($is_active_filter);
        $types .= 'i';
    }

    if ($admin_role_filter === 'Super Admin' || $admin_role_filter === 'Normal Admin') {
        $conditions[] = "a.admin_role = ?";
        $params[] = $admin_role_filter;
        $types .= 's';
    }

    if (!empty($date_start) && !empty($date_end)) {
        $conditions[] = "a.admin_created_at >= ? AND a.admin_created_at <= ?";
        $endDateTime = $date_end . ' 23:59:59';
        $params = array_merge($params, [$date_start, $endDateTime]);
        $types .= 'ss';
    } elseif (!empty($date_start)) {
        $conditions[] = "a.admin_created_at >= ?";
        $params[] = $date_start;
        $types .= 's';
    } elseif (!empty($date_end)) {
        $conditions[] = "a.admin_created_at <= ?";
        $endDateTime = $date_end . ' 23:59:59';
        $params[] = $endDateTime;
        $types .= 's';
    }

    $whereClause = '';
    if (!empty($conditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
    }

    $totalFilteredQuery = "SELECT COUNT(*) as total FROM ananda_super_admin a $whereClause";

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

    $fetchQuery = "SELECT a.admin_id, a.admin_username, a.admin_email, a.admin_first_name, a.admin_last_name,
                  a.admin_role, a.admin_is_active, a.admin_created_at, a.admin_updated_at
                  FROM ananda_super_admin a
                  $whereClause
                  ORDER BY a.admin_id DESC
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
            'admin_id'         => (int)$row_data['admin_id'],
            'admin_username'   => $showNA($row_data['admin_username']),
            'admin_email'      => $showNA($row_data['admin_email']),
            'admin_first_name' => $showNA($row_data['admin_first_name']),
            'admin_last_name'  => $showNA($row_data['admin_last_name']),
            'admin_role'       => $showNA($row_data['admin_role']),
            'admin_is_active'   => (int)$row_data['admin_is_active'],
            'admin_created_at'  => $showNA($row_data['admin_created_at']),
            'admin_updated_at'  => $showNA($row_data['admin_updated_at'])
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