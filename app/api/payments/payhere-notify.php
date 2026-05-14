<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/email.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../');
$dotenv->load();

header('Content-Type: text/plain');

$merchant_id = $_ENV['PAYHERE_MERCHANT_ID'] ?? '';
$merchant_secret = $_ENV['PAYHERE_MERCHANT_SECRET'] ?? '';
$order_id = $_POST['order_id'] ?? '';
$payment_id_payhere = $_POST['payment_id'] ?? '';
$payhere_amount = $_POST['payhere_amount'] ?? '';
$payhere_currency = $_POST['payhere_currency'] ?? '';
$status_code = $_POST['status_code'] ?? '';
$md5sig = $_POST['md5sig'] ?? '';
$posted_merchant_id = $_POST['merchant_id'] ?? '';
$status_message = $_POST['status_message'] ?? '';
$method = $_POST['method'] ?? '';

if (empty($merchant_id) || empty($merchant_secret) || empty($order_id) || empty($payment_id_payhere) ||
    empty($payhere_amount) || empty($payhere_currency) || empty($status_code) || empty($md5sig) || empty($posted_merchant_id)) {
    http_response_code(500);
    die('Server Error');
}

if ($posted_merchant_id !== $merchant_id) {
    http_response_code(500);
    die('Server Error');
}

$local_md5sig = strtoupper(
    md5(
        $merchant_id .
        $order_id .
        $payhere_amount .
        $payhere_currency .
        $status_code .
        strtoupper(md5($merchant_secret))
    )
);

if ($local_md5sig !== $md5sig) {
    http_response_code(400);
    echo 'Server Error';
    exit;
}

function getStatusMapping($status_code) {
    return match((int)$status_code) {
        2 => ['text' => 'Successful', 'order_status' => 'Confirmed', 'payment_status' => 'Paid'],
        0 => ['text' => 'Pending', 'order_status' => 'Pending', 'payment_status' => 'Pending'],
        -1 => ['text' => 'Cancelled', 'order_status' => 'Cancelled', 'payment_status' => 'Cancelled'],
        -2 => ['text' => 'Failed', 'order_status' => 'Cancelled', 'payment_status' => 'Failed'],
        -3 => ['text' => 'Chargedback', 'order_status' => 'Cancelled', 'payment_status' => 'Refunded'],
        default => ['text' => 'Unknown', 'order_status' => 'Pending', 'payment_status' => 'Pending']
    };
}

function updateOrderStatus($db, $orderId, $status_data) {
    $stmt = $db->prepare("UPDATE orders SET
        status = ?,
        payment_status = ?,
        updated_at = NOW()
        WHERE order_id = ? LIMIT 1");

    if ($stmt) {
        $stmt->bind_param("ssi", $status_data['order_status'], $status_data['payment_status'], $orderId);
        $stmt->execute();
        $stmt->close();
    }
}

function sendAdminOrderEmail($db, $orderId, $paymentId, $orderRefNo, $statusCode, $statusMessage, $paymentIdPayhere = null) {
    try {
        $adminEmails = [
            $_ENV['NOTIFICATION_EMAIL'] ?? null,
            $_ENV['NOTIFICATION_EMAIL_2'] ?? null
        ];
        $adminEmails = array_filter($adminEmails);

        if (empty($adminEmails)) {
            return false;
        }

        $stmt = $db->prepare(
            "SELECT p.*, o.order_ref_no, o.order_total, o.price_type, o.note_customer, o.ordered_datetime,
                    c.first_name, c.last_name, c.phone as customer_phone, c.customer_id,
                    GROUP_CONCAT(
                        CONCAT(pr.product_name, ' (', pu.unit_code, ') x', oi.qty, ' - LKR ', oi.price_total)
                        SEPARATOR '\n'
                    ) as items_details
             FROM payments p
             INNER JOIN orders o ON p.order_id = o.order_id
             INNER JOIN customers c ON o.customer_id = c.customer_id
             LEFT JOIN order_items oi ON o.order_id = oi.order_id
             LEFT JOIN products pr ON oi.product_id = pr.product_id
             LEFT JOIN product_units pu ON oi.unit_id = pu.unit_id
             WHERE p.payment_id = ? LIMIT 1"
        );

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("i", $paymentId);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }

        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $stmt->close();
            return false;
        }

        $data = $result->fetch_assoc();
        $stmt->close();

        $status_mapping = getStatusMapping($statusCode);
        $status_text = $status_mapping['text'];

        $html = "<html><body style='font-family: Arial, sans-serif; line-height: 1.4; color: #333; padding: 20px;'>";

        $html .= "<h2 style='margin: 0 0 16px;'>Order " . htmlspecialchars($status_text) . " - " . htmlspecialchars($data['order_ref_no']) . "</h2>";

        $html .= "<table style='width: 100%; border-collapse: collapse; margin-bottom: 16px;'>";
        $html .= "<tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Customer:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($data['first_name'] . ' ' . $data['last_name']) . "</td></tr>";
        $html .= "<tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Phone:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($data['customer_phone']) . "</td></tr>";
        $html .= "<tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Items:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . nl2br(htmlspecialchars($data['items_details'])) . "</td></tr>";
        $html .= "<tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Total:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>LKR " . number_format($data['order_total'], 2) . "</strong></td></tr>";

        if ($data['note_customer']) {
            $html .= "<tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Note:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($data['note_customer']) . "</td></tr>";
        }

        $html .= "<tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Payment Status:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($status_text) . "</td></tr>";
        $html .= "<tr><td style='padding: 8px;'><strong>Payment:</strong></td><td style='padding: 8px;'>Online Payment (IPG)</td></tr>";
        $html .= "</table>";

        $html .= "</body></html>";

        $subject = "Order " . htmlspecialchars($status_text) . " - " . htmlspecialchars($data['order_ref_no']);

        $success = true;
        foreach ($adminEmails as $adminEmail) {
            if (!sendEmail($subject, $html, $adminEmail, 'Admin')) {
                $success = false;
            }
        }

        return $success;

    } catch (Exception $e) {
        error_log("Admin email error: " . $e->getMessage());
        return false;
    }
}

try {
    $stmt = $db->prepare("SELECT payment_id, order_id, amount, currency FROM payments WHERE payment_ref_no = ? LIMIT 1");
    if (!$stmt) {
        http_response_code(500);
        echo 'Server Error';
        exit;
    }

    $stmt->bind_param("s", $order_id);
    if (!$stmt->execute()) {
        $stmt->close();
        http_response_code(500);
        echo 'Server Error';
        exit;
    }

    $stmt->bind_result($payment_id, $order_id_internal, $original_amount, $original_currency);
    if (!$stmt->fetch()) {
        $stmt->close();
        http_response_code(500);
        echo 'Server Error';
        exit;
    }
    $stmt->close();

    if (abs(floatval($payhere_amount) - floatval($original_amount)) > 0.01) {
        http_response_code(500);
        echo 'Server Error';
        exit;
    }

    $update_payment = $db->prepare("UPDATE payments SET
        payment_id_payhere = ?,
        payhere_amount = ?,
        payhere_currency = ?,
        status_code = ?,
        status_message = ?,
        method = ?,
        md5sig = ?,
        updated_at = NOW()
        WHERE payment_id = ?");

    if (!$update_payment) {
        http_response_code(500);
        echo 'Server Error';
        exit;
    }

    $update_payment->bind_param("sdsisssi", $payment_id_payhere, $payhere_amount, $payhere_currency, $status_code, $status_message, $method, $md5sig, $payment_id);
    if (!$update_payment->execute()) {
        $update_payment->close();
        http_response_code(500);
        echo 'Server Error';
        exit;
    }
    $update_payment->close();

    $status_code_int = (int)$status_code;
    $status_data = getStatusMapping($status_code_int);
    updateOrderStatus($db, $order_id_internal, $status_data);

    $stmt = $db->prepare("SELECT order_ref_no FROM orders WHERE order_id = ? LIMIT 1");
    $stmt->bind_param("i", $order_id_internal);
    $stmt->execute();
    $stmt->bind_result($order_ref_no);
    $stmt->fetch();
    $stmt->close();

    sendAdminOrderEmail($db, $order_id_internal, $payment_id, $order_ref_no, $status_code, $status_message, $payment_id_payhere);

    echo 'OK';
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo 'Server Error';
    exit;
}
?>
