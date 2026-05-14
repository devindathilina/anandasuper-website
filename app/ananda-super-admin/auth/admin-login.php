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
        return $result->fetch_row()[0] >= 5;
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

if (!isset($_POST['ananda_super_admin_login_csrf_token']) ||
    !hash_equals($_SESSION['ananda_super_admin_login_csrf_token'], $_POST['ananda_super_admin_login_csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6);
if (!$ip) {
    $ip = '127.0.0.1';
}

if (checkAttempts($ip, 'admin_login')) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many login attempts. Please try again later.']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($username) || empty($password)) {
    logAttempt($ip, 'admin_login', false);
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit;
}

try {
    $stmt = $db->prepare("SELECT admin_id, admin_password, admin_email, admin_first_name, admin_last_name, admin_role FROM ananda_super_admin WHERE admin_username = ? AND admin_is_active = 1 LIMIT 1");
    if (!$stmt) {
        logAttempt($ip, 'admin_login', false);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
        exit;
    }

    $stmt->bind_param('s', $username);
    if (!$stmt->execute()) {
        logAttempt($ip, 'admin_login', false);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
        exit;
    }

    $result = $stmt->get_result();
    if ($result->num_rows !== 1) {
        logAttempt($ip, 'admin_login', false);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }

    $admin = $result->fetch_assoc();
    $allowed_roles = ['Super Admin', 'Normal Admin'];
    if (!in_array($admin['admin_role'], $allowed_roles, true)) {
        logAttempt($ip, 'admin_login', false);
        echo json_encode(['success' => false, 'message' => 'Account role is not permitted']);
        exit;
    }

    if (!password_verify($password, $admin['admin_password'])) {
        logAttempt($ip, 'admin_login', false);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }

    $otp_code = sprintf('%06d', mt_rand(100000, 999999));
    $expires_at = date('Y-m-d H:i:s', time() + 600);

    $stmt = $db->prepare("INSERT INTO admin_otp (admin_id, otp_code, expires_at) VALUES (?, ?, ?)");
    if (!$stmt) {
        logAttempt($ip, 'admin_login', false);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
        exit;
    }

    $stmt->bind_param('iss', $admin['admin_id'], $otp_code, $expires_at);
    if (!$stmt->execute()) {
        logAttempt($ip, 'admin_login', false);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
        exit;
    }

    $admin_email = $admin['admin_email'];
    if (empty($admin_email)) {
        logAttempt($ip, 'admin_login', false);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
        exit;
    }

    $email_subject = "{$admin['admin_role']} Login Verification Code - ANANDA SUPER";
    $email_body = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #F5F0FA;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff; border: 2px solid #6B4C93; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);'>
            <div style='text-align: center; margin-bottom: 20px; padding: 15px; background-color: #6B4C93; border-radius: 8px 8px 0 0;'>
                <h1 style='color: #ffffff; margin: 0; font-size: 28px;'>ANANDA SUPER</h1>
                <p style='color: #ffffff; margin: 5px 0 0 0; font-size: 14px;'>Admin Login Verification</p>
            </div>

            <div style='padding: 20px;'>
                <p style='font-size: 16px; color: #000000;'>Hello {$admin['admin_first_name']} {$admin['admin_last_name']},</p>
                <p style='font-size: 16px; color: #000000;'>A login attempt was made for <strong style='color: #6B4C93;'>{$admin['admin_role']}</strong> account '<strong style='color: #6B4C93;'>{$username}</strong>'. Please use the verification code below to complete the login:</p>

                <div style='text-align: center; margin: 30px 0;'>
                    <div style='display: inline-block; background-color: #6B4C93; padding: 20px 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(107, 76, 147, 0.3);'>
                        <p style='margin: 0; font-size: 14px; color: #ffffff; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;'>Verification Code</p>
                        <p style='margin: 10px 0 0 0; font-size: 32px; font-weight: bold; letter-spacing: 4px; color: #ffffff; font-family: monospace;'>{$otp_code}</p>
                    </div>
                </div>

                <div style='background-color: #E8DFF0; border-left: 4px solid #6B4C93; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <p style='margin: 0; color: #000000; font-weight: bold;'>Security Notice</p>
                    <p style='margin: 8px 0 0 0; color: #000000;'>This code will expire in 10 minutes. If this login attempt was not authorized, please investigate immediately.</p>
                </div>

                <div style='background-color: #f8f8f8; border: 1px solid #ddd; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <p style='margin: 0; color: #000000; font-weight: bold;'>Login Details</p>
                    <p style='margin: 8px 0 0 0; color: #333333; font-size: 14px;'>
                        Username: <strong>{$username}</strong><br>
                        Role: <strong>{$admin['admin_role']}</strong><br>
                        Admin Name: <strong>{$admin['admin_first_name']} {$admin['admin_last_name']}</strong><br>
                        Login attempt from IP: <strong>{$ip}</strong><br>
                        Time: <strong>" . date('Y-m-d H:i:s') . " : Sri Lankan Standard Time</strong>
                    </p>
                </div>
            </div>

            <div style='background-color: #1a1a1a; padding: 15px; text-align: center; border-radius: 0 0 8px 8px;'>
                <p style='margin: 0; color: #ffffff; font-size: 12px;'>
                    ANANDA SUPER
                </p>
            </div>
        </div>
    </body>
    </html>";

    if (sendEmail($email_subject, $email_body, $admin_email, $admin['admin_first_name'])) {
        logAttempt($ip, 'admin_login', true);

        $_SESSION['ananda_super_admin_pending_verification'] = [
            'admin_id' => $admin['admin_id'],
            'admin_username' => $username,
            'admin_email' => $admin['admin_email'],
            'admin_first_name' => $admin['admin_first_name'],
            'admin_last_name' => $admin['admin_last_name'],
            'admin_role' => $admin['admin_role'],
            'created_at' => time()
        ];

        echo json_encode([
            'success' => true,
            'requires_otp' => true,
            'admin_id' => $admin['admin_id'],
            'message' => 'Verification code sent to your email'
        ]);
    } else {
        logAttempt($ip, 'admin_login', false);
        echo json_encode(['success' => false, 'message' => 'Unable to send verification email. Please try again.']);
    }

} catch (Exception $e) {
    logAttempt($ip, 'admin_login', false);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>