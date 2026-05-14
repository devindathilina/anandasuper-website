<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$token = $_GET['token'] ?? '';

if (empty($token)) {
    http_response_code(400);
    die('Invalid request. Missing payment token.');
}

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(400);
    die('Invalid payment token.');
}

$stmt = $db->prepare("
    SELECT 
        p.*, 
        o.order_ref_no,
        o.customer_id
    FROM payments p
    INNER JOIN orders o 
        ON p.order_id = o.order_id
    WHERE p.payment_token = ?
    AND p.status_code = 0
    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);
    die('Server Error');
}

$stmt->bind_param("s", $token);

if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(500);
    die('Server Error');
}

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    http_response_code(404);
    die('Payment not found or already processed.');
}

$payment_data = $result->fetch_assoc();
$stmt->close();

$payment_id = $payment_data['payment_id'];
$order_id = $payment_data['order_id'];
$payment_ref_no = $payment_data['payment_ref_no'];
$order_ref_no = $payment_data['order_ref_no'];

$first_name = $payment_data['first_name'] ?? '';
$last_name  = $payment_data['last_name'] ?? '';
$email      = $payment_data['email'] ?? '';
$phone      = $payment_data['phone'] ?? '';
$address    = $payment_data['address'] ?? '';
$city       = $payment_data['city'] ?? '';
$country    = $payment_data['country'] ?? 'Sri Lanka';

$amount   = $payment_data['amount'] ?? 0;
$currency = $payment_data['currency'] ?? 'LKR';

$merchant_id     = $_ENV['PAYHERE_MERCHANT_ID'] ?? '';
$merchant_secret = $_ENV['PAYHERE_MERCHANT_SECRET'] ?? '';

if (empty($merchant_id) || empty($merchant_secret)) {
    http_response_code(500);
    die('Payment gateway configuration error.');
}

$hash = strtoupper(
    md5(
        $merchant_id .
        $payment_ref_no .
        number_format($amount, 2, '.', '') .
        $currency .
        strtoupper(md5($merchant_secret))
    )
);

$return_url   = $_ENV['PAYHERE_RETURN_URL'] ?? '';
$cancel_url   = $_ENV['PAYHERE_CANCEL_URL'] ?? '';
$notify_url   = $_ENV['PAYHERE_NOTIFY_URL'] ?? '';
$checkout_url = $_ENV['PAYHERE_CHECKOUT_URL'] ?? 'https://www.payhere.lk/pay/checkout';

if (
    empty($return_url) ||
    empty($cancel_url) ||
    empty($notify_url)
) {
    http_response_code(500);
    die('Payment gateway configuration error.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Payment - Ananda Super</title>
</head>

<body style="margin:0;padding:20px;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,Ubuntu,sans-serif;background:#ffffff;min-height:100vh;display:flex;align-items:center;justify-content:center;">

    <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,0.1);max-width:480px;width:100%;overflow:hidden;">

        <div style="padding:32px 32px 24px;border-bottom:1px solid #e5e7eb;text-align:center;">
            <h1 style="margin:0 0 8px 0;font-size:22px;font-weight:600;color:#111827;">
                Complete Your Payment
            </h1>

            <p style="margin:0;font-size:14px;color:#6b7280;">
                Ananda Super - Kandangoda, Kuruwita 70500
            </p>
        </div>

        <div style="padding:32px;">

            <form method="post"
                  action="<?= htmlspecialchars($checkout_url) ?>"
                  id="payhere-form">

                <input type="hidden" name="merchant_id" value="<?= htmlspecialchars($merchant_id) ?>">
                <input type="hidden" name="return_url" value="<?= htmlspecialchars($return_url) ?>">
                <input type="hidden" name="cancel_url" value="<?= htmlspecialchars($cancel_url) ?>">
                <input type="hidden" name="notify_url" value="<?= htmlspecialchars($notify_url) ?>">

                <input type="hidden" name="order_id" value="<?= htmlspecialchars($payment_ref_no) ?>">
                <input type="hidden" name="items" value="Order Payment">
                <input type="hidden" name="currency" value="LKR">
                <input type="hidden" name="amount" value="<?= number_format($amount, 2, '.', '') ?>">

                <input type="hidden" name="first_name" value="<?= htmlspecialchars($first_name) ?>">
                <input type="hidden" name="last_name" value="<?= htmlspecialchars($last_name) ?>">
                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                <input type="hidden" name="phone" value="<?= htmlspecialchars($phone) ?>">
                <input type="hidden" name="address" value="<?= htmlspecialchars($address) ?>">
                <input type="hidden" name="city" value="<?= htmlspecialchars($city) ?>">
                <input type="hidden" name="country" value="<?= htmlspecialchars($country) ?>">

                <input type="hidden" name="hash" value="<?= htmlspecialchars($hash) ?>">

                <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:20px;margin-bottom:24px;">

                    <h3 style="margin:0 0 16px 0;font-size:14px;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:0.5px;">
                        Payment Summary
                    </h3>

                    <div style="display:flex;justify-content:space-between;margin-bottom:12px;font-size:14px;">
                        <span style="color:#6b7280;">Order Reference:</span>

                        <span style="font-weight:500;color:#111827;">
                            <?= htmlspecialchars($order_ref_no) ?>
                        </span>
                    </div>

                    <div style="border-top:1px solid #e5e7eb;padding-top:16px;margin-top:16px;display:flex;justify-content:space-between;font-size:14px;">

                        <span style="color:#6b7280;">
                            Total Amount:
                        </span>

                        <span style="color:#7c3aed;font-size:18px;font-weight:600;">
                            LKR <?= number_format($amount, 2) ?>
                        </span>
                    </div>
                </div>

                <div style="background:#f5f3ff;border:1px solid #ddd6fe;padding:16px;border-radius:8px;margin-bottom:24px;">

                    <h4 style="margin:0 0 8px 0;color:#5b21b6;font-size:14px;font-weight:600;">
                        Secure Payment
                    </h4>

                    <p style="margin:0;color:#6b7280;font-size:13px;line-height:1.5;">
                        You will be redirected to PayHere's secure payment gateway to complete your payment. Your payment information is encrypted and secure.
                    </p>
                </div>

                <div style="display:flex;gap:12px;flex-wrap:wrap;">

                    <button type="submit"
                            id="pay-btn"
                            style="flex:1;padding:14px 24px;border:none;border-radius:8px;font-size:15px;font-weight:500;cursor:pointer;background:#7c3aed;color:#ffffff;min-width:180px;">

                        Pay LKR <?= number_format($amount, 2) ?>
                    </button>

                    <button type="button"
                            onclick="window.close()"
                            style="flex:1;padding:14px 24px;border:1px solid #d1d5db;border-radius:8px;font-size:15px;font-weight:500;cursor:pointer;background:#f3f4f6;color:#374151;min-width:180px;">

                        Cancel
                    </button>
                </div>

            </form>
        </div>

        <div style="text-align:center;padding:20px 32px;border-top:1px solid #e5e7eb;font-size:12px;color:#9ca3af;">
            <p style="margin:0;">
                &copy; <?= date('Y') ?> Ananda Super. All rights reserved.
            </p>
        </div>
    </div>

    <script>
        document
            .getElementById('payhere-form')
            .addEventListener('submit', function () {

                const payBtn = document.getElementById('pay-btn');

                payBtn.innerHTML = 'Redirecting...';
                payBtn.disabled = true;
                payBtn.style.opacity = '0.6';
                payBtn.style.cursor = 'not-allowed';
            });
    </script>

</body>
</html>