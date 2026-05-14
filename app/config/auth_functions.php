<?php
if (!defined('ANANDA_SUPER_SECURE_ACCESS')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/jwt_functions.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

function validateCustomerAuthentication($session_token, $db, $secret_key) {
    if (empty($session_token)) {
        return ['success' => false, 'message' => 'Missing session token.'];
    }

    $customerData = verifyJWT($session_token, $secret_key);
    if (!$customerData || empty($customerData['customer_id'])) {
        return ['success' => false, 'message' => 'Invalid or expired session token.'];
    }

    $customer_id = (int)$customerData['customer_id'];

    $stmt = $db->prepare("SELECT customer_id, first_name, last_name, phone, is_active
                          FROM customers
                          WHERE customer_id = ? AND session_token = ? AND is_active = 1
                          LIMIT 1");

    if (!$stmt) {
        return ['success' => false, 'message' => 'Internal server error.'];
    }

    $stmt->bind_param("is", $customer_id, $session_token);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['success' => false, 'message' => 'Internal server error.'];
    }

    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        return ['success' => false, 'message' => 'Unauthorized or invalid session token.'];
    }

    $customer_data = $result->fetch_assoc();
    $stmt->close();

    return [
        'success' => true,
        'customer_id' => $customer_id,
        'customer_data' => [
            'customer_id' => $customer_data['customer_id'],
            'first_name' => $customer_data['first_name'],
            'last_name' => $customer_data['last_name'],
            'phone' => $customer_data['phone'],
            'is_active' => $customer_data['is_active']
        ]
    ];
}

function refreshCustomerToken($session_token, $db, $secret_key, $onesignal_player_id = null) {
    if (empty($session_token)) {
        return ['success' => false, 'message' => 'Session token is required.'];
    }

    $customerData = verifyJWT($session_token, $secret_key);
    if (!$customerData || empty($customerData['customer_id'])) {
        return ['success' => false, 'message' => 'Invalid or expired session token.'];
    }

    $customer_id = (int)$customerData['customer_id'];
    $phone = $customerData['phone'] ?? '';

    if (empty($phone)) {
        $customer = getCustomerBySession($db, $session_token);
        if (!$customer) {
            return ['success' => false, 'message' => 'Customer not found.'];
        }
        $phone = $customer['phone'];
    }

    $newToken = generateJWT($customer_id, $phone, $secret_key);

    if ($onesignal_player_id !== null && trim($onesignal_player_id) !== '') {
        $stmt = $db->prepare("UPDATE customers SET session_token = ?, onesignal_player_id = ? WHERE customer_id = ? AND session_token = ?");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Internal server error.'];
        }

        $stmt->bind_param("ssis", $newToken, $onesignal_player_id, $customer_id, $session_token);
        $success = $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
    } else {
        $stmt = $db->prepare("UPDATE customers SET session_token = ? WHERE customer_id = ? AND session_token = ?");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Internal server error.'];
        }

        $stmt->bind_param("sis", $newToken, $customer_id, $session_token);
        $success = $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
    }

    if (!$success || $affected_rows === 0) {
        return ['success' => false, 'message' => 'Failed to update session token.'];
    }

    $auth_result = validateCustomerAuthentication($newToken, $db, $secret_key);
    if (!$auth_result['success']) {
        return ['success' => false, 'message' => 'Failed to validate new session token.'];
    }

    return [
        'success' => true,
        'message' => 'Session token refreshed successfully.',
        'session_token' => $newToken,
        'customer_data' => $auth_result['customer_data']
    ];
}

function getCustomerBySession($db, $session_token) {
    $stmt = $db->prepare("SELECT customer_id, first_name, last_name, phone FROM customers WHERE session_token = ? LIMIT 1");
    if (!$stmt) return null;

    $stmt->bind_param("s", $session_token);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();
    $stmt->close();

    return $customer;
}

function sendAuthResponse($success, $message, $data = null, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

function logLoginAttempt($db, $ip_address, $attempt_type, $success = false) {
    $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, attempt_type, status) VALUES (?, ?, ?)");
    if (!$stmt) return false;

    $status = $success ? 1 : 0;
    $stmt->bind_param("ssi", $ip_address, $attempt_type, $status);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

function isRateLimited($db, $ip_address, $attempt_type, $max_attempts = 5, $time_window_minutes = 60) {
    $stmt = $db->prepare("SELECT COUNT(*) as attempt_count FROM login_attempts
                          WHERE ip_address = ? AND attempt_type = ?
                          AND attempt_time > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    if (!$stmt) return false;

    $stmt->bind_param("ssi", $ip_address, $attempt_type, $time_window_minutes);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row['attempt_count'] >= $max_attempts;
}

function isOTPRateLimited($db, $customer_id, $require_cooldown = false) {
    $stmt = $db->prepare("SELECT
                            MAX(created_at) as last_otp_time,
                            COUNT(*) as otp_count
                          FROM customer_otp
                          WHERE customer_id = ?
                          AND created_at > DATE_SUB(NOW(), INTERVAL 60 MINUTE)");
    if (!$stmt) return ['limited' => false, 'message' => ''];

    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    $last_otp_time = $row['last_otp_time'];
    $otp_count = $row['otp_count'];

    if ($otp_count >= 5) {
        return ['limited' => true, 'message' => 'Too many otp attempts. Please try again later.'];
    }

    if ($require_cooldown && $last_otp_time !== null) {
        $stmt = $db->prepare("SELECT TIMESTAMPDIFF(SECOND, ?, NOW()) as seconds_since");
        if (!$stmt) return ['limited' => false, 'message' => ''];

        $stmt->bind_param("s", $last_otp_time);
        $stmt->execute();
        $time_result = $stmt->get_result();
        $time_row = $time_result->fetch_assoc();
        $stmt->close();

        $seconds_since = (int)$time_row['seconds_since'];
        $seconds_remaining = 120 - $seconds_since;

        if ($seconds_since < 120) {
            $minutes = floor($seconds_remaining / 60);
            $seconds = $seconds_remaining % 60;
            return [
                'limited' => true,
                'message' => "Please wait {$minutes} minute" . ($minutes != 1 ? 's' : '') .
                            ($seconds > 0 ? " and {$seconds} second" . ($seconds != 1 ? 's' : '') : '') .
                            " before requesting another OTP."
            ];
        }
    }

    return ['limited' => false, 'message' => ''];
}
?>
