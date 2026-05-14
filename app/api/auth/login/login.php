<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/api_functions.php';
require_once __DIR__ . '/../../../config/jwt_functions.php';
require_once __DIR__ . '/../../../config/auth_functions.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../../');
$dotenv->load();

$response = ['success' => false, 'message' => '', 'data' => null];

function validatePhone($phone) {
    $raw = preg_replace('/[^0-9+]/', '', $phone);
    return preg_match('/^\+?[0-9]+$/', $raw) ? $raw : '';
}

function getCustomerByPhone($db, $phone) {
    $stmt = $db->prepare("SELECT customer_id, first_name, last_name, password, is_active
                          FROM customers
                          WHERE phone = ?");
    if (!$stmt) return null;

    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();
    $stmt->close();

    return $customer;
}

function updateCustomerToken($db, $customer_id, $token, $onesignal_player_id = null) {
    if ($onesignal_player_id !== null && trim($onesignal_player_id) !== '') {
        $stmt = $db->prepare("UPDATE customers SET session_token = ?, onesignal_player_id = ? WHERE customer_id = ?");
        $stmt->bind_param("ssi", $token, $onesignal_player_id, $customer_id);
    } else {
        $stmt = $db->prepare("UPDATE customers SET session_token = ? WHERE customer_id = ?");
        $stmt->bind_param("si", $token, $customer_id);
    }
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = "Invalid request method.";
} else {
    $client_ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: '0.0.0.0';

    if (isRateLimited($db, $client_ip, 'cust_login')) {
        $response['message'] = "Too many login attempts. Please try again later.";
    } else {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['phone'], $input['password'])) {
            $response['message'] = "Phone number and password are required.";
            logLoginAttempt($db, $client_ip, 'cust_login', false);
        } else {
            $phone = validatePhone($input['phone']);
            $password = $input['password'];
            $onesignal_player_id = isset($input['onesignal_player_id']) ? trim($input['onesignal_player_id']) : null;

            if (empty($phone) || empty($password)) {
                $response['message'] = "Phone number and password are required.";
                logLoginAttempt($db, $client_ip, 'cust_login', false);
            } else {
                $customer = getCustomerByPhone($db, $phone);

                if (!$customer) {
                    $response['message'] = "Invalid phone number or account does not exist.";
                    logLoginAttempt($db, $client_ip, 'cust_login', false);
                } elseif ($customer['is_active'] != 1) {
                    $response['message'] = "Account is inactive.";
                    logLoginAttempt($db, $client_ip, 'cust_login', false);
                } elseif (!password_verify($password, $customer['password'])) {
                    $response['message'] = "Invalid password.";
                    logLoginAttempt($db, $client_ip, 'cust_login', false);
                } else {
                    $session_token = generateJWT($customer['customer_id'], $phone, $_ENV['JWT_SECRET_KEY']);

                    if (!updateCustomerToken($db, $customer['customer_id'], $session_token, $onesignal_player_id)) {
                        $response['message'] = "Unable to update session token.";
                        logLoginAttempt($db, $client_ip, 'cust_login', false);
                    } else {
                        $response['success'] = true;
                        $response['message'] = "Login successful.";
                        $response['data'] = [
                            'first_name' => $customer['first_name'],
                            'last_name' => $customer['last_name'],
                            'phone' => $phone,
                            'session_token' => $session_token
                        ];
                        logLoginAttempt($db, $client_ip, 'cust_login', true);
                    }
                }
            }
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
exit();
?>
