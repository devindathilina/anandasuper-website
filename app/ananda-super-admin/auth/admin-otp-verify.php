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

if (checkAttempts($ip, 'admin_otp_verify')) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many verification attempts. Please try again later.']);
    exit;
}

if (!isset($_SESSION['ananda_super_admin_pending_verification'])) {
    echo json_encode(['success' => false, 'message' => 'No pending verification found. Please login again.']);
    exit;
}

$pending = $_SESSION['ananda_super_admin_pending_verification'];

if (time() - $pending['created_at'] > 600) {
    unset($_SESSION['ananda_super_admin_pending_verification']);
    echo json_encode(['success' => false, 'message' => 'Verification session expired. Please login again.']);
    exit;
}

$admin_id = intval($_POST['admin_id'] ?? 0);
$otp_code = trim($_POST['otp_code'] ?? '');

if ($admin_id !== $pending['admin_id']) {
    logAttempt($ip, 'admin_otp_verify', false);
    echo json_encode(['success' => false, 'message' => 'Invalid verification request']);
    exit;
}

if (empty($otp_code) || !preg_match('/^\d{6}$/', $otp_code)) {
    logAttempt($ip, 'admin_otp_verify', false);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid 6-digit verification code']);
    exit;
}

try {
    $stmt = $db->prepare("
        SELECT otp_id, otp_code, expires_at, is_verified
        FROM admin_otp
        WHERE admin_id = ?
        AND expires_at > NOW()
        AND is_verified = 0
        ORDER BY created_at DESC
        LIMIT 1
    ");

    if (!$stmt) {
        logAttempt($ip, 'admin_otp_verify', false);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
        exit;
    }

    $stmt->bind_param('i', $admin_id);
    if (!$stmt->execute()) {
        logAttempt($ip, 'admin_otp_verify', false);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
        exit;
    }

    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        logAttempt($ip, 'admin_otp_verify', false);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code']);
        exit;
    }

    $otp_record = $result->fetch_assoc();

    if ($otp_record['otp_code'] !== $otp_code) {
        logAttempt($ip, 'admin_otp_verify', false);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code']);
        exit;
    }

    $stmt = $db->prepare("UPDATE admin_otp SET is_verified = 1 WHERE otp_id = ?");
    if (!$stmt) {
        logAttempt($ip, 'admin_otp_verify', false);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
        exit;
    }

    $stmt->bind_param('i', $otp_record['otp_id']);
    if (!$stmt->execute()) {
        logAttempt($ip, 'admin_otp_verify', false);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['ananda_super_admin_id'] = $admin_id;
    $_SESSION['ananda_super_admin_username'] = $pending['admin_username'];
    $_SESSION['ananda_super_admin_email'] = $pending['admin_email'];
    $_SESSION['ananda_super_admin_first_name'] = $pending['admin_first_name'];
    $_SESSION['ananda_super_admin_last_name'] = $pending['admin_last_name'];
    $_SESSION['ananda_super_admin_role'] = $pending['admin_role'] ?? 'Normal Admin';

    unset($_SESSION['ananda_super_admin_pending_verification']);

    logAttempt($ip, 'admin_otp_verify', true);

    echo json_encode([
        'success' => true,
        'message' => 'Verification successful! Redirecting to dashboard...'
    ]);

} catch (Exception $e) {
    logAttempt($ip, 'admin_otp_verify', false);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>