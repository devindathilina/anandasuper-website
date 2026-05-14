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
    $date_start = isset($_POST['date_start']) ? trim($_POST['date_start']) : '';
    $date_end = isset($_POST['date_end']) ? trim($_POST['date_end']) : '';
    $is_active = isset($_POST['is_active']) ? trim($_POST['is_active']) : '';
    $offer_status = isset($_POST['offer_status']) ? trim($_POST['offer_status']) : '';

    $totalRecordsQuery = "SELECT COUNT(*) as total FROM offers";
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
        $conditions[] = "(o.offer_name LIKE ? OR o.offer_code LIKE ? OR o.description LIKE ?)";
        $searchTerm = "%$searchValue%";
        $params = array_fill(0, 3, $searchTerm);
        $types = 'sss';
    }

    if (!empty($date_start)) {
        $conditions[] = "DATE(o.created_at) >= ?";
        $params[] = $date_start;
        $types .= 's';
    }

    if (!empty($date_end)) {
        $conditions[] = "DATE(o.created_at) <= ?";
        $params[] = $date_end;
        $types .= 's';
    }

    if ($is_active !== '') {
        $conditions[] = "o.is_active = ?";
        $params[] = $is_active;
        $types .= 's';
    }

    if (!empty($offer_status)) {
        $today = date('Y-m-d');
        if ($offer_status === 'active') {
            $conditions[] = "(o.start_date <= ? AND o.end_date >= ?)";
            $params[] = $today;
            $params[] = $today;
            $types .= 'ss';
        } elseif ($offer_status === 'expired') {
            $conditions[] = "o.end_date < ?";
            $params[] = $today;
            $types .= 's';
        } elseif ($offer_status === 'upcoming') {
            $conditions[] = "o.start_date > ?";
            $params[] = $today;
            $types .= 's';
        }
    }

    $whereClause = '';
    if (!empty($conditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
    }

    $totalFilteredQuery = "SELECT COUNT(*) as total FROM offers o $whereClause";

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

    $fetchQuery = "SELECT o.offer_id, o.offer_name, o.description, o.offer_code, o.off_percentage,
                  o.start_date, o.end_date, o.offer_image, o.is_active,
                  o.created_at, o.updated_at
                  FROM offers o
                  $whereClause
                  ORDER BY o.offer_id DESC
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

    $today = date('Y-m-d');

    $data = [];
    while ($row_data = $result->fetch_assoc()) {
        $start_date = $row_data['start_date'];
        $end_date = $row_data['end_date'];

        if ($today < $start_date) {
            $offer_period_status = 'Upcoming';
            $offer_period_class = 'secondary';
        } elseif ($today > $end_date) {
            $offer_period_status = 'Expired';
            $offer_period_class = 'danger';
        } else {
            $offer_period_status = 'Running';
            $offer_period_class = 'primary';
        }

        $data[] = [
            'offer_id'           => (int)$row_data['offer_id'],
            'offer_name'         => $showNA($row_data['offer_name']),
            'description'        => $showNA($row_data['description']),
            'offer_code'         => $showNA($row_data['offer_code']),
            'off_percentage'     => $showNA($row_data['off_percentage']),
            'start_date'         => $showNA($row_data['start_date']),
            'end_date'           => $showNA($row_data['end_date']),
            'offer_image'        => $showNA($row_data['offer_image']),
            'is_active'          => (int)$row_data['is_active'],
            'created_at'         => $showNA($row_data['created_at']),
            'updated_at'         => $showNA($row_data['updated_at']),
            'offer_period_status' => $offer_period_status,
            'offer_period_class'  => $offer_period_class
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
