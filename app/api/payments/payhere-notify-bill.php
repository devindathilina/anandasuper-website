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
        2 => ['text' => 'Successful', 'order_status' => 'Processing', 'payment_status' => 'Paid'],
        0 => ['text' => 'Pending', 'order_status' => 'Pending', 'payment_status' => 'Pending'],
        -1 => ['text' => 'Cancelled', 'order_status' => 'Failed', 'payment_status' => 'Refunded'],
        -2 => ['text' => 'Failed', 'order_status' => 'Failed', 'payment_status' => 'Failed'],
        -3 => ['text' => 'Chargedback', 'order_status' => 'Failed', 'payment_status' => 'Refunded'],
        default => ['text' => 'Unknown', 'order_status' => 'Pending', 'payment_status' => 'Pending']
    };
}

function updateBillReloadOrderStatus($db, $orderId, $status_data) {
    $stmt = $db->prepare("UPDATE bill_reload_orders SET
        status = ?,
        payment_status = ?,
        updated_at = NOW()
        WHERE bill_reload_order_id = ? LIMIT 1");

    if ($stmt) {
        $stmt->bind_param("ssi", $status_data['order_status'], $status_data['payment_status'], $orderId);
        $stmt->execute();
        $stmt->close();
    }
}

function sendAdminBillReloadOrderEmail($db, $orderId, $paymentId, $orderRefNo, $statusCode, $statusMessage, $paymentIdPayhere = null) {
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
            "SELECT p.*, bro.bill_reload_ref_no, bro.account_number, bro.amount, bro.service_fee, bro.total_amount,
                    bro.note_store, bro.created_at,
                    c.first_name, c.last_name, c.phone as customer_phone, c.customer_id,
                    s.service_name, s.service_type
             FROM payments p
             INNER JOIN bill_reload_orders bro ON p.bill_reload_order_id = bro.bill_reload_order_id
             INNER JOIN customers c ON bro.customer_id = c.customer_id
             INNER JOIN services s ON bro.service_id = s.service_id
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

        $html .= "<h2 style='margin: 0 0 16px;'>Bill/Reload Order " . htmlspecialchars($status_text) . " - " . htmlspecialchars($data['bill_reload_ref_no']) . "</h2>";

        $html .= "<table style='width: 100%; border-collapse: collapse; margin-bottom: 16px;'>";
        $html .= "<tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Customer:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($data['first_name'] . ' ' . $data['last_name']) . "</td></tr>";
        $html .= "<tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Phone:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($data['customer_phone']) . "</td></tr>";
        $html .= "<tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Service:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($data['service_name']) . " (" . htmlspecialchars($data['service_type']) . ")</td></tr>";
        $html .= "<tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Account Number:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($data['account_number']) . "</td></tr>";
        $html .= "<tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Amount:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>LKR " . number_format($data['amount'], 2) . "</td></tr>";
        $html .= "<tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Service Fee:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>LKR " . number_format($data['service_fee'], 2) . "</td></tr>";
        $html .= "<tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Total:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>LKR " . number_format($data['total_amount'], 2) . "</strong></td></tr>";

        if ($data['note_store']) {
            $html .= "<tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Note:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($data['note_store']) . "</td></tr>";
        }

        $html .= "<tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Payment Status:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($status_text) . "</td></tr>";
        $html .= "<tr><td style='padding: 8px;'><strong>Payment:</strong></td><td style='padding: 8px;'>Online Payment (IPG)</td></tr>";
        $html .= "</table>";

        $html .= "</body></html>";

        $subject = "Bill/Reload Order " . htmlspecialchars($status_text) . " - " . htmlspecialchars($data['bill_reload_ref_no']);

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
    $stmt = $db->prepare("SELECT payment_id, bill_reload_order_id, amount, currency FROM payments WHERE payment_ref_no = ? AND bill_reload_order_id IS NOT NULL LIMIT 1");
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
    updateBillReloadOrderStatus($db, $order_id_internal, $status_data);

    $stmt = $db->prepare("SELECT bill_reload_ref_no FROM bill_reload_orders WHERE bill_reload_order_id = ? LIMIT 1");
    $stmt->bind_param("i", $order_id_internal);
    $stmt->execute();
    $stmt->bind_result($order_ref_no);
    $stmt->fetch();
    $stmt->close();

    sendAdminBillReloadOrderEmail($db, $order_id_internal, $payment_id, $order_ref_no, $status_code, $status_message, $payment_id_payhere);

    echo 'OK';
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo 'Server Error';
    exit;
}
?>
