<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$status = $_GET['status'] ?? '';

$pageTitle = 'Payment Result';
$message = '';

if ($status === 'success') {
    $pageTitle = 'Payment Successful';
    $message = 'Thank you for your payment. Your bill/reload order has been confirmed and will be processed shortly.';
} elseif ($status === 'cancel') {
    $pageTitle = 'Payment Cancelled';
    $message = 'Your payment was cancelled. If you have any questions, please contact our support team.';
}

$iconBg = '#f3f4f6';
$iconColor = '#6b7280';
$iconSvg = '
    <circle cx="12" cy="12" r="10"></circle>
    <line x1="12" y1="8" x2="12" y2="12"></line>
    <line x1="12" y1="16" x2="12.01" y2="16"></line>
';

if ($status === 'success') {
    $iconBg = '#d1fae5';
    $iconColor = '#059669';

    $iconSvg = '
        <polyline points="20 6 9 17 4 12"></polyline>
    ';
} elseif ($status === 'cancel') {
    $iconBg = '#fee2e2';
    $iconColor = '#dc2626';

    $iconSvg = '
        <line x1="18" y1="6" x2="6" y2="18"></line>
        <line x1="6" y1="6" x2="18" y2="18"></line>
    ';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Ananda Super</title>
</head>

<body style="margin:0;padding:20px;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,Ubuntu,sans-serif;background:#ffffff;min-height:100vh;display:flex;align-items:center;justify-content:center;">

    <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,0.1);max-width:480px;width:100%;text-align:center;overflow:hidden;">

        <div style="padding:40px 40px 20px;">

            <div style="width:64px;height:64px;margin:0 auto;background:<?= htmlspecialchars($iconBg) ?>;border-radius:50%;display:flex;align-items:center;justify-content:center;">

                <svg viewBox="0 0 24 24"
                     fill="none"
                     stroke="<?= htmlspecialchars($iconColor) ?>"
                     stroke-width="2.5"
                     stroke-linecap="round"
                     stroke-linejoin="round"
                     style="width:32px;height:32px;">

                    <?= $iconSvg ?>

                </svg>
            </div>
        </div>

        <div style="padding:20px 40px 40px;">

            <h1 style="margin:0 0 12px 0;font-size:22px;font-weight:600;color:#111827;">
                <?= htmlspecialchars($pageTitle) ?>
            </h1>

            <p style="margin:0 0 24px 0;color:#6b7280;line-height:1.6;font-size:15px;">
                <?= htmlspecialchars($message) ?>
            </p>

            <a href="javascript:void(0);"
               onclick="window.close();"
               style="display:inline-block;padding:12px 32px;background:#7c3aed;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:500;font-size:15px;">

                Close
            </a>
        </div>

        <div style="padding:20px;border-top:1px solid #e5e7eb;font-size:12px;color:#9ca3af;">

            <p style="margin:0;">
                &copy; <?= date('Y') ?> Ananda Super. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
