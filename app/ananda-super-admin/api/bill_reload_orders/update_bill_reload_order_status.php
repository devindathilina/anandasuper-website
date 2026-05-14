<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);

require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../../config/sms.php';
require_once __DIR__ . '/../../../config/onesignal.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

if (!isset($_POST['bill_reload_order_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required field: bill_reload_order_id'
    ]);
    exit;
}

$bill_reload_order_id = intval($_POST['bill_reload_order_id']);

$status = isset($_POST['status'])
    ? trim($_POST['status'])
    : null;

$payment_status = isset($_POST['payment_status'])
    ? trim($_POST['payment_status'])
    : null;

$validStatuses = [
    'Pending',
    'Processing',
    'Completed',
    'Failed',
    'Refunded'
];

$validPaymentStatuses = [
    'Pending',
    'Paid',
    'Failed',
    'Refunded'
];

if ($bill_reload_order_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid order ID'
    ]);
    exit;
}

if ($status === null && $payment_status === null) {
    echo json_encode([
        'success' => false,
        'message' => 'No fields provided to update'
    ]);
    exit;
}

if ($status !== null && !in_array($status, $validStatuses, true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid order status'
    ]);
    exit;
}

if ($payment_status !== null && !in_array($payment_status, $validPaymentStatuses, true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid payment status'
    ]);
    exit;
}

try {

    $currentStmt = $db->prepare("
        SELECT
            bro.status,
            bro.payment_status,
            bro.bill_reload_ref_no,
            bro.customer_id,

            c.first_name,
            c.last_name,
            c.phone,
            c.onesignal_player_id

        FROM bill_reload_orders bro

        INNER JOIN customers c
            ON bro.customer_id = c.customer_id

        WHERE bro.bill_reload_order_id = ?
    ");

    if (!$currentStmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Internal Server Error'
        ]);
        exit;
    }

    $currentStmt->bind_param('i', $bill_reload_order_id);

    if (!$currentStmt->execute()) {

        $currentStmt->close();

        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch order'
        ]);

        exit;
    }

    $currentResult = $currentStmt->get_result();

    $currentOrder = $currentResult->fetch_assoc();

    $currentStmt->close();

    if (!$currentOrder) {

        echo json_encode([
            'success' => false,
            'message' => 'Order not found'
        ]);

        exit;
    }

    $db->begin_transaction();

    try {

        $setParts = [];
        $params = [];
        $types = '';

        if ($status !== null) {

            $setParts[] = "status = ?";
            $params[] = $status;
            $types .= 's';
        }

        if ($payment_status !== null) {

            $setParts[] = "payment_status = ?";
            $params[] = $payment_status;
            $types .= 's';
        }

        if (
            ($status === 'Completed' && $currentOrder['payment_status'] === 'Paid')
            ||
            ($payment_status === 'Paid' && $currentOrder['status'] === 'Completed')
        ) {

            $setParts[] = "processed_at = CURRENT_TIMESTAMP";
        }

        $setParts[] = "updated_at = CURRENT_TIMESTAMP";

        $params[] = $bill_reload_order_id;

        $types .= 'i';

        $sql = "
            UPDATE bill_reload_orders
            SET " . implode(', ', $setParts) . "
            WHERE bill_reload_order_id = ?
        ";

        $stmt = $db->prepare($sql);

        if (!$stmt) {

            $db->rollback();

            echo json_encode([
                'success' => false,
                'message' => 'Internal Server Error'
            ]);

            exit;
        }

        $stmt->bind_param($types, ...$params);

        if (!$stmt->execute()) {

            $stmt->close();

            $db->rollback();

            echo json_encode([
                'success' => false,
                'message' => 'Failed to update order'
            ]);

            exit;
        }

        $stmt->close();

        $db->commit();

        $response = [
            'success' => true,
            'message' => 'Order updated successfully'
        ];

        if ($status !== null) {

            $response['message'] =
                "Order status updated to {$status}";
        }

        if ($payment_status !== null) {

            $response['message'] =
                "Payment status updated to {$payment_status}";
        }

        if (
            ($status === 'Completed' && $currentOrder['payment_status'] === 'Paid')
            ||
            ($payment_status === 'Paid' && $currentOrder['status'] === 'Completed')
        ) {

            $response['message'] .=
                '. Order marked as processed.';
        }

        $notification_statuses = [

            'Processing' => [
                'sms' => 'is now being processed',
                'push_title' => 'Order Processing',
                'push_message' => 'Your bill/reload order is now being processed.'
            ],

            'Completed' => [
                'sms' => 'has been completed successfully',
                'push_title' => 'Order Completed',
                'push_message' => 'Your bill/reload order has been completed successfully.'
            ],

            'Failed' => [
                'sms' => 'has failed. Please contact support if payment was deducted',
                'push_title' => 'Order Failed',
                'push_message' => 'Your bill/reload order has failed. Please contact support if needed.'
            ],

            'Refunded' => [
                'sms' => 'has been refunded',
                'push_title' => 'Order Refunded',
                'push_message' => 'Your bill/reload order has been refunded.'
            ]
        ];

        if ($status !== null && isset($notification_statuses[$status])) {

            $bill_reload_ref_no = $currentOrder['bill_reload_ref_no'];

            $customer_name = trim(
                $currentOrder['first_name'] . ' ' .
                $currentOrder['last_name']
            );

            $phone = trim($currentOrder['phone']);

            $onesignal_player_id = trim(
                (string)($currentOrder['onesignal_player_id'] ?? '')
            );

            if (!empty($phone)) {

                $international_phone =
                    '94' . ltrim($phone, '0');

                $sms_message =
                    "Hi {$customer_name}, your bill/reload order #{$bill_reload_ref_no} " .
                    $notification_statuses[$status]['sms'] .
                    ". Thank you for using {$app_name}!";

                $sms_sent = sendSMS(
                    $international_phone,
                    $sms_message
                );

                if ($sms_sent) {

                    $response['sms_sent'] = true;

                    $response['message'] .=
                        " SMS notification sent.";
                } else {

                    $response['sms_sent'] = false;

                    $response['message'] .=
                        " SMS notification failed.";
                }
            }

            if (!empty($onesignal_player_id)) {

                $push_message =
                    "Hi {$customer_name}, your bill/reload order #{$bill_reload_ref_no} " .
                    $notification_statuses[$status]['sms'] .
                    ". Thank you for using {$app_name}!";

                $push_sent = sendPushNotificationToPlayer(
                    $onesignal_player_id,
                    $notification_statuses[$status]['push_title'],
                    $push_message,
                    [
                        'bill_reload_order_id' => $bill_reload_order_id,
                        'bill_reload_ref_no' => $bill_reload_ref_no,
                        'status' => $status
                    ]
                );

                if ($push_sent) {

                    $response['push_sent'] = true;

                    $response['message'] .=
                        " Push notification sent.";
                } else {

                    $response['push_sent'] = false;

                    $response['message'] .=
                        " Push notification failed.";
                }
            }
        }

        echo json_encode($response);

    } catch (Exception $e) {

        $db->rollback();

        echo json_encode([
            'success' => false,
            'message' => 'Internal server error'
        ]);
    }

} catch (Exception $e) {

    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>