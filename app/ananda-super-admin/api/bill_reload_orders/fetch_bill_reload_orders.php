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
    $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : null;

    $totalRecordsQuery = "SELECT COUNT(*) as total FROM bill_reload_orders";
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
        $conditions[] = "(bro.bill_reload_ref_no LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR bro.account_number LIKE ?)";
        $searchTerm = "%$searchValue%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ssss';
    }

    if (!empty($date_start)) {
        $conditions[] = "DATE(bro.created_at) >= ?";
        $params[] = $date_start;
        $types .= 's';
    }

    if (!empty($date_end)) {
        $conditions[] = "DATE(bro.created_at) <= ?";
        $params[] = $date_end;
        $types .= 's';
    }

    if (!empty($status)) {
        $conditions[] = "bro.status = ?";
        $params[] = $status;
        $types .= 's';
    }

    if (!empty($payment_status)) {
        $conditions[] = "bro.payment_status = ?";
        $params[] = $payment_status;
        $types .= 's';
    }

    if (!empty($customer_id)) {
        $conditions[] = "bro.customer_id = ?";
        $params[] = $customer_id;
        $types .= 'i';
    }

    if (!empty($service_id)) {
        $conditions[] = "bro.service_id = ?";
        $params[] = $service_id;
        $types .= 'i';
    }

    $whereClause = '';
    if (!empty($conditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
    }

    $totalFilteredQuery = "SELECT COUNT(*) as total FROM bill_reload_orders bro
                           INNER JOIN customers c ON bro.customer_id = c.customer_id
                           INNER JOIN services s ON bro.service_id = s.service_id
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

    $fetchQuery = "SELECT bro.bill_reload_order_id, bro.bill_reload_ref_no, bro.customer_id,
                   CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                   s.service_name, s.service_type, bro.account_number,
                   bro.amount, bro.service_fee, bro.total_amount,
                   bro.status, bro.payment_status, bro.processed_at,
                   bro.note_store, bro.created_at, bro.updated_at
                   FROM bill_reload_orders bro
                   INNER JOIN customers c ON bro.customer_id = c.customer_id
                   INNER JOIN services s ON bro.service_id = s.service_id
                   $whereClause
                   ORDER BY bro.bill_reload_order_id DESC
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
        'Processing' => 'style="background-color: #0dcaf0; color: white;"',
        'Completed' => 'style="background-color: #198754; color: white;"',
        'Failed' => 'style="background-color: #dc3545; color: white;"',
        'Refunded' => 'style="background-color: #d63384; color: white;"'
    ];

    $data = [];
    while ($row_data = $result->fetch_assoc()) {
        $status = $row_data['status'];
        $paymentStatus = $row_data['payment_status'];

        $data[] = [
            'bill_reload_order_id' => (int)$row_data['bill_reload_order_id'],
            'bill_reload_ref_no'    => $showNA($row_data['bill_reload_ref_no']),
            'customer_id'           => (int)$row_data['customer_id'],
            'customer_name'         => $showNA($row_data['customer_name']),
            'service_id'             => (int)$row_data['service_id'],
            'service_name'          => $showNA($row_data['service_name']),
            'service_type'          => $showNA($row_data['service_type']),
            'account_number'         => $showNA($row_data['account_number']),
            'amount'                 => (float)$row_data['amount'],
            'service_fee'            => (float)$row_data['service_fee'],
            'total_amount'           => (float)$row_data['total_amount'],
            'status'                 => $showNA($status),
            'status_class'           => $statusClass[$status] ?? 'secondary',
            'payment_status'         => $showNA($paymentStatus),
            'processed_at'           => $showNA($row_data['processed_at']),
            'note_store'             => $showNA($row_data['note_store']),
            'created_at'             => $showNA($row_data['created_at']),
            'updated_at'             => $showNA($row_data['updated_at'])
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
