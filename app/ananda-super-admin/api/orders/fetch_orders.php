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
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';
    $payment_status = isset($_POST['payment_status']) ? trim($_POST['payment_status']) : '';
    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
    $price_type = isset($_POST['price_type']) ? trim($_POST['price_type']) : '';

    $totalRecordsQuery = "SELECT COUNT(*) as total FROM orders";
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
        $conditions[] = "(o.order_ref_no LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ?)";
        $searchTerm = "%$searchValue%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'sss';
    }

    if (!empty($date_start)) {
        $conditions[] = "DATE(o.ordered_datetime) >= ?";
        $params[] = $date_start;
        $types .= 's';
    }

    if (!empty($date_end)) {
        $conditions[] = "DATE(o.ordered_datetime) <= ?";
        $params[] = $date_end;
        $types .= 's';
    }

    if (!empty($status)) {
        $conditions[] = "o.status = ?";
        $params[] = $status;
        $types .= 's';
    }

    if (!empty($payment_status)) {
        $conditions[] = "o.payment_status = ?";
        $params[] = $payment_status;
        $types .= 's';
    }

    if (!empty($customer_id)) {
        $conditions[] = "o.customer_id = ?";
        $params[] = $customer_id;
        $types .= 'i';
    }

    if (!empty($price_type)) {
        $conditions[] = "o.price_type = ?";
        $params[] = $price_type;
        $types .= 's';
    }

    $whereClause = '';
    if (!empty($conditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
    }

    $totalFilteredQuery = "SELECT COUNT(*) as total FROM orders o
                           INNER JOIN customers c ON o.customer_id = c.customer_id
                           $whereClause";

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

    $fetchQuery = "SELECT o.order_id, o.order_ref_no, o.customer_id,
                   CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                   o.status, o.price_type, o.ordered_datetime, o.picked_up_datetime,
                   o.payment_status, o.order_total, o.note_customer, o.note_store,
                   o.rating, o.is_active, o.created_at, o.updated_at
                   FROM orders o
                   INNER JOIN customers c ON o.customer_id = c.customer_id
                   $whereClause
                   ORDER BY o.order_id DESC
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

    $statusClass = [
        'Pending' => 'style="background-color: #6c757d; color: white;"',
        'Confirmed' => 'style="background-color: #0d6efd; color: white;"',
        'Preparing' => 'style="background-color: #fd7e14; color: white;"',
        'Ready' => 'style="background-color: #ffc107; color: black;"',
        'Picked Up' => 'style="background-color: #0dcaf0; color: black;"',
        'On The Way' => 'style="background-color: #6f42c1; color: white;"',
        'Delivered' => 'style="background-color: #198754; color: white;"',
        'Cancelled' => 'style="background-color: #dc3545; color: white;"',
        'Refunded' => 'style="background-color: #d63384; color: white;"'
    ];

    $data = [];
    while ($row_data = $result->fetch_assoc()) {
        $status = $row_data['status'];
        $paymentStatus = $row_data['payment_status'];

        $data[] = [
            'order_id'           => (int)$row_data['order_id'],
            'order_ref_no'       => $showNA($row_data['order_ref_no']),
            'customer_id'        => (int)$row_data['customer_id'],
            'customer_name'      => $showNA($row_data['customer_name']),
            'price_type'         => $showNA($row_data['price_type']),
            'status'             => $showNA($status),
            'status_class'       => $statusClass[$status] ?? 'secondary',
            'ordered_datetime'   => $showNA($row_data['ordered_datetime']),
            'picked_up_datetime' => $showNA($row_data['picked_up_datetime']),
            'payment_status'     => $showNA($paymentStatus),
            'order_total'        => number_format((float)$row_data['order_total'], 2),
            'note_customer'      => $showNA($row_data['note_customer']),
            'note_store'         => $showNA($row_data['note_store']),
            'rating'             => $row_data['rating'] ?? null,
            'is_active'          => (int)$row_data['is_active'],
            'created_at'         => $showNA($row_data['created_at']),
            'updated_at'         => $showNA($row_data['updated_at'])
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
