<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once 'secure_session.php';
require_once 'security_headers.php';

if (!startSecureSession()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server Error']);
    exit;
}

setSecurityHeaders('api');
header('Content-Type: application/json');

require_once '../../config/db.php';
require_once '../../config/email.php';

function checkAttempts($ip, $type) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_type = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("ss", $ip, $type);
        if (!$stmt->execute()) {
            return false;
        }
        $result = $stmt->get_result();
        return $result->fetch_row()[0] >= 3;
    } catch (Exception $e) {
        return false;
    }
}

function logAttempt($ip, $type, $success = false) {
    global $db;
    try {
        $success_int = $success ? 1 : 0;
        $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, attempt_type, status) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssi", $ip, $type, $success_int);
            $stmt->execute();
        }
    } catch (Exception $e) {
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['ananda_super_admin_otp_csrf_token']) ||
    !hash_equals($_SESSION['ananda_super_admin_login_csrf_token'], $_POST['ananda_super_admin_otp_csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6);
if (!$ip) {
    $ip = '127.0.0.1';
}

if (checkAttempts($ip, 'admin_otp')) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many resend attempts. Please try again later.']);
    exit;
}

if (!isset($_SESSION['ananda_super_admin_pending_verification'])) {
    echo json_encode(['success' => false, 'message' => 'No pending verification found. Please login again.']);
    exit;
}

$pending = $_SESSION['ananda_super_admin_pending_verification'];
$pending_role = $pending['admin_role'] ?? 'Normal Admin';

if (time() - $pending['created_at'] > 600) {
    unset($_SESSION['ananda_super_admin_pending_verification']);
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

$admin_id = intval($_POST['admin_id'] ?? 0);

if ($admin_id !== $pending['admin_id']) {
    logAttempt($ip, 'admin_otp', false);
    echo json_encode(['success' => false, 'message' => 'Invalid resend request']);
    exit;
}

try {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM admin_otp
        WHERE admin_id = ?
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    ");

    if (!$stmt) {
        logAttempt($ip, 'admin_otp', false);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
        exit;
    }

    $stmt->bind_param('i', $admin_id);
    if (!$stmt->execute()) {
        logAttempt($ip, 'admin_otp', false);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
        exit;
    }

    $result = $stmt->get_result();
    $recent_count = $result->fetch_row()[0];

    if ($recent_count > 0) {
        echo json_encode(['success' => false, 'message' => 'Please wait at least 1 minute before requesting a new code']);
        exit;
    }

    $otp_code = sprintf('%06d', mt_rand(100000, 999999));
    $expires_at = date('Y-m-d H:i:s', time() + 600);

    $stmt = $db->prepare("INSERT INTO admin_otp (admin_id, otp_code, expires_at) VALUES (?, ?, ?)");
    if (!$stmt) {
        logAttempt($ip, 'admin_otp', false);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
        exit;
    }

    $stmt->bind_param('iss', $admin_id, $otp_code, $expires_at);
    if (!$stmt->execute()) {
        logAttempt($ip, 'admin_otp', false);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
        exit;
    }

    $stmt = $db->prepare("SELECT admin_email, admin_first_name, admin_last_name FROM ananda_super_admin WHERE admin_id = ? AND admin_is_active = 1 LIMIT 1");
    if (!$stmt) {
        logAttempt($ip, 'admin_otp', false);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
        exit;
    }

    $stmt->bind_param('i', $admin_id);
    if (!$stmt->execute()) {
        logAttempt($ip, 'admin_otp', false);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
        exit;
    }

    $result = $stmt->get_result();
    if ($result->num_rows !== 1) {
        logAttempt($ip, 'admin_otp', false);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
        exit;
    }

    $admin = $result->fetch_assoc();
    $admin_email = $admin['admin_email'];

    if (empty($admin_email)) {
        logAttempt($ip, 'admin_otp', false);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
        exit;
    }

    $email_subject = "{$pending_role} Login Verification Code - ANANDA SUPER";
    $email_body = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #F5F0FA;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff; border: 2px solid #6B4C93; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);'>
            <div style='text-align: center; margin-bottom: 20px; padding: 15px; background-color: #6B4C93; border-radius: 8px 8px 0 0;'>
                <h1 style='color: #ffffff; margin: 0; font-size: 28px;'>ANANDA SUPER</h1>
                <p style='color: #ffffff; margin: 5px 0 0 0; font-size: 14px;'>Admin Login Verification (Code Resent)</p>
            </div>

            <div style='padding: 20px;'>
                <p style='font-size: 16px; color: #000000;'>Hello {$admin['admin_first_name']} {$admin['admin_last_name']},</p>
                <p style='font-size: 16px; color: #000000;'>A new verification code has been requested for <strong style='color: #6B4C93;'>{$pending_role}</strong> account '<strong style='color: #6B4C93;'>{$pending['admin_username']}</strong>'. Please use the verification code below to complete the login:</p>

                <div style='text-align: center; margin: 30px 0;'>
                    <div style='display: inline-block; background-color: #6B4C93; padding: 20px 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(107, 76, 147, 0.3);'>
                        <p style='margin: 0; font-size: 14px; color: #ffffff; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;'>New Verification Code</p>
                        <p style='margin: 10px 0 0 0; font-size: 32px; font-weight: bold; letter-spacing: 4px; color: #ffffff; font-family: monospace;'>{$otp_code}</p>
                    </div>
                </div>

                <div style='background-color: #E8DFF0; border-left: 4px solid #6B4C93; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <p style='margin: 0; color: #000000; font-weight: bold;'>Security Notice</p>
                    <p style='margin: 8px 0 0 0; color: #000000;'>This is a resent verification code. It will expire in 10 minutes. If you didn't request this resend, please investigate immediately.</p>
                </div>

                <div style='background-color: #f8f8f8; border: 1px solid #ddd; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <p style='margin: 0; color: #000000; font-weight: bold;'>Resend Details</p>
                    <p style='margin: 8px 0 0 0; color: #333333; font-size: 14px;'>
                        Username: <strong>{$pending['admin_username']}</strong><br>
                        Role: <strong>{$pending_role}</strong><br>
                        Admin Name: <strong>{$admin['admin_first_name']} {$admin['admin_last_name']}</strong><br>
                        Resend request from IP: <strong>{$ip}</strong><br>
                        Time: <strong>" . date('Y-m-d H:i:s') . "</strong>
                    </p>
                </div>
            </div>

            <div style='background-color: #1a1a1a; padding: 15px; text-align: center; border-radius: 0 0 8px 8px;'>
                <p style='margin: 0; color: #ffffff; font-size: 12px;'>
                    ANANDA SUPER Admin Panel
                </p>
            </div>
        </div>
    </body>
    </html>";

    if (sendEmail($email_subject, $email_body, $admin_email, $admin['admin_first_name'])) {
        logAttempt($ip, 'admin_otp', true);

        echo json_encode([
            'success' => true,
            'message' => 'A new verification code has been sent to your email'
        ]);
    } else {
        logAttempt($ip, 'admin_otp', false);
        echo json_encode(['success' => false, 'message' => 'Unable to send verification email. Please try again.']);
    }

} catch (Exception $e) {
    logAttempt($ip, 'admin_otp', false);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>