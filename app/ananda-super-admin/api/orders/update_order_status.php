<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../../config/sms.php';
require_once __DIR__ . '/../../../config/onesignal.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['order_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required field: order_id']);
    exit;
}

$order_id = intval($_POST['order_id']);
$status = isset($_POST['status']) ? trim($_POST['status']) : null;
$payment_status = isset($_POST['payment_status']) ? trim($_POST['payment_status']) : null;

$validStatuses = ['Pending', 'Confirmed', 'Preparing', 'Ready', 'Picked Up', 'Cancelled', 'Refunded'];
$validPaymentStatuses = ['Pending', 'Paid', 'Failed', 'Refunded'];

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

if ($status === null && $payment_status === null) {
    echo json_encode(['success' => false, 'message' => 'No fields provided to update']);
    exit;
}

if ($status !== null && !in_array($status, $validStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid order status']);
    exit;
}

if ($payment_status !== null && !in_array($payment_status, $validPaymentStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment status']);
    exit;
}

try {
    $currentStmt = $db->prepare("SELECT status FROM orders WHERE order_id = ?");
    if (!$currentStmt) {
        echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
        exit;
    }
    $currentStmt->bind_param('i', $order_id);
    if (!$currentStmt->execute()) {
        $currentStmt->close();
        echo json_encode(['success' => false, 'message' => 'Failed to fetch order']);
        exit;
    }
    $currentResult = $currentStmt->get_result();
    $currentOrder = $currentResult->fetch_assoc();
    $currentStmt->close();

    if (!$currentOrder) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    $currentStatus = $currentOrder['status'];
    $shouldRestock = false;

    if ($status !== null) {
        $statusesThatRequireRestock = ['Cancelled', 'Refunded'];
        $statusesThatAreNotRestocked = ['Cancelled', 'Refunded'];

        if (in_array($status, $statusesThatRequireRestock) &&
            !in_array($currentStatus, $statusesThatAreNotRestocked)) {
            $shouldRestock = true;
        }
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

        $setParts[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $order_id;
        $types .= 'i';

        $sql = "UPDATE orders SET " . implode(', ', $setParts) . " WHERE order_id = ?";
        $stmt = $db->prepare($sql);

        if (!$stmt) {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
            exit;
        }

        $stmt->bind_param($types, ...$params);

        if (!$stmt->execute()) {
            $stmt->close();
            $db->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to update order']);
            exit;
        }

        $stmt->close();

        if ($shouldRestock) {
            $itemsStmt = $db->prepare("
                SELECT product_id, qty
                FROM order_items
                WHERE order_id = ? AND is_active = 1
            ");
            if (!$itemsStmt) {
                $db->rollback();
                echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
                exit;
            }

            $itemsStmt->bind_param('i', $order_id);
            if (!$itemsStmt->execute()) {
                $itemsStmt->close();
                $db->rollback();
                echo json_encode(['success' => false, 'message' => 'Failed to fetch order items']);
                exit;
            }

            $itemsResult = $itemsStmt->get_result();
            $items = $itemsResult->fetch_all(MYSQLI_ASSOC);
            $itemsStmt->close();

            foreach ($items as $item) {
                $lockStmt = $db->prepare("SELECT product_id, qty FROM products WHERE product_id = ? FOR UPDATE");
                if (!$lockStmt) {
                    $db->rollback();
                    echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
                    exit;
                }

                $lockStmt->bind_param('i', $item['product_id']);
                if (!$lockStmt->execute()) {
                    $lockStmt->close();
                    $db->rollback();
                    echo json_encode(['success' => false, 'message' => 'Failed to lock product']);
                    exit;
                }

                $productRow = $lockStmt->get_result()->fetch_assoc();
                $lockStmt->close();

                if (!$productRow) {
                    $db->rollback();
                    echo json_encode(['success' => false, 'message' => 'Product not found']);
                    exit;
                }

                $restockStmt = $db->prepare("UPDATE products SET qty = qty + ? WHERE product_id = ? AND qty >= 0");
                if (!$restockStmt) {
                    $db->rollback();
                    echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
                    exit;
                }

                $restockStmt->bind_param('ii', $item['qty'], $item['product_id']);
                if (!$restockStmt->execute()) {
                    $restockStmt->close();
                    $db->rollback();
                    echo json_encode(['success' => false, 'message' => 'Failed to restock products']);
                    exit;
                }

                if ($restockStmt->affected_rows === 0) {
                    $restockStmt->close();
                    $db->rollback();
                    echo json_encode(['success' => false, 'message' => 'Cannot restock: product quantity is negative']);
                    exit;
                }

                $restockStmt->close();
            }
        }

        $db->commit();

        $response = ['success' => true, 'message' => 'Order updated successfully'];

        if ($shouldRestock) {
            $response['message'] = "Order marked as {$status}. Products have been restocked.";
        }

        $notification_statuses = [
            'Ready' => [
                'sms' => 'ready for pickup. Please visit the store to pick it up',
                'push_title' => 'Order Ready for Pickup',
                'push_message' => 'Your order is ready for pickup. Please visit the store to pick it up.'
            ],
            'Picked Up' => [
                'sms' => 'has been picked up',
                'push_title' => 'Order Picked Up',
                'push_message' => 'Your order has been successfully picked up. Thank you for shopping with us!'
            ],
            'Cancelled' => [
                'sms' => 'has been cancelled. If you have paid, you will be refunded',
                'push_title' => 'Order Cancelled',
                'push_message' => 'Your order has been cancelled. If you have made a payment, you will be refunded.'
            ],
            'Refunded' => [
                'sms' => 'has been refunded',
                'push_title' => 'Order Refunded',
                'push_message' => 'Your order has been refunded. The amount will be credited to your account.'
            ]
        ];

        if (isset($notification_statuses[$status])) {
            $orderStmt = $db->prepare("
                SELECT o.order_ref_no, c.phone, c.first_name, c.last_name, c.onesignal_player_id
                FROM orders o
                INNER JOIN customers c ON o.customer_id = c.customer_id
                WHERE o.order_id = ?
            ");
            if ($orderStmt && $orderStmt->bind_param('i', $order_id) && $orderStmt->execute()) {
                $orderResult = $orderStmt->get_result()->fetch_assoc();
                $orderStmt->close();

                if ($orderResult) {
                    $order_ref = $orderResult['order_ref_no'];
                    $customer_name = trim($orderResult['first_name'] . ' ' . $orderResult['last_name']);
                    $phone = $orderResult['phone'];
                    $onesignal_player_id = $orderResult['onesignal_player_id'];
                    $international_phone = '94' . ltrim($phone, '0');

                    $sms_message = "Hi {$customer_name}, your order #{$order_ref} {$notification_statuses[$status]['sms']}. Thank you for ordering with $app_name!";
                    $sms_sent = sendSMS($international_phone, $sms_message);

                    if ($sms_sent) {
                        $response['sms_sent'] = true;
                        $response['message'] .= " SMS notification sent to customer.";
                    } else {
                        $response['sms_sent'] = false;
                        $response['message'] .= " SMS notification failed.";
                    }

                    if (!empty($onesignal_player_id)) {
                        $push_message = "Hi {$customer_name}, your order #{$order_ref} {$notification_statuses[$status]['sms']}. Thank you for ordering with $app_name!";
                        $push_sent = sendPushNotificationToPlayer(
                            $onesignal_player_id,
                            $notification_statuses[$status]['push_title'],
                            $push_message,
                            ['order_id' => $order_id, 'order_ref_no' => $order_ref, 'status' => $status]
                        );

                        if ($push_sent) {
                            $response['push_sent'] = true;
                            $response['message'] .= " Push notification sent.";
                        } else {
                            $response['push_sent'] = false;
                            $response['message'] .= " Push notification failed.";
                        }
                    }
                }
            }
        }

        echo json_encode($response);

    } catch (Exception $e) {
        $db->rollback();
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>
