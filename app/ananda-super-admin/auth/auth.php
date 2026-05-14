<?php
if (!defined('ANANDA_SUPER_SECURE_ACCESS')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

require_once __DIR__ . '/secure_session.php';
require_once __DIR__ . '/security_headers.php';

setSecurityHeaders('admin');
require_once __DIR__ . '/../../config/db.php';

if (!startSecureSession()) {
    http_response_code(500);
    exit('Server Error');
}

$ananda_super_current_admin = verifyAndGetAdminSession();
if (!$ananda_super_current_admin) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    header('Location: index.php');
    exit;
}

if (!csrfSafeMethod($_SERVER['REQUEST_METHOD'])) {
    enforceCsrfProtection();
}

function getCsrfToken() {
    if (empty($_SESSION['ananda_super_admin_csrf_token'])) {
        $_SESSION['ananda_super_admin_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['ananda_super_admin_csrf_token'];
}

function csrfInputField() {
    return '<input type="hidden" name="ananda_super_admin_csrf_token" value="' .
           htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function validateCsrfToken($submittedToken) {
    return isset($_SESSION['ananda_super_admin_csrf_token']) &&
           is_string($submittedToken) &&
           hash_equals($_SESSION['ananda_super_admin_csrf_token'], $submittedToken);
}

function csrfSafeMethod($method) {
    return in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true);
}

function enforceCsrfProtection() {
    if (!csrfSafeMethod($_SERVER['REQUEST_METHOD'])) {
        $submittedToken = '';
        if (!empty($_SERVER['HTTP_ANTI_CSRF_TOKEN'])) {
            $submittedToken = $_SERVER['HTTP_ANTI_CSRF_TOKEN'];
        } elseif (!empty($_POST['ananda_super_admin_csrf_token'])) {
            $submittedToken = $_POST['ananda_super_admin_csrf_token'];
        }
        if (empty($submittedToken) || !validateCsrfToken($submittedToken)) {
            http_response_code(403);
            die('CSRF token validation failed');
        }
    }
}

function verifyAndGetAdminSession() {
    global $db;

    if (!isset($_SESSION['ananda_super_admin_id']) ||
        !is_numeric($_SESSION['ananda_super_admin_id']) ||
        $_SESSION['ananda_super_admin_id'] <= 0) {
        return null;
    }

    $admin_id = (int) $_SESSION['ananda_super_admin_id'];

    if (!$db || $db->connect_error) {
        return null;
    }

    $stmt = $db->prepare("
        SELECT admin_id, admin_username, admin_email, admin_first_name, admin_last_name, admin_role
        FROM ananda_super_admin
        WHERE admin_id = ? AND admin_is_active = 1
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $admin_id);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }

    $result = $stmt->get_result();
    $stmt->close();

    if ($result && $result->num_rows === 1) {
        $data = $result->fetch_assoc();
        $allowed_roles = ['Super Admin', 'Normal Admin'];
        if (!in_array($data['admin_role'], $allowed_roles, true)) {
            return null;
        }

        if (!empty($_SESSION['ananda_super_admin_role']) && $_SESSION['ananda_super_admin_role'] !== $data['admin_role']) {
            return null;
        }

        $GLOBALS['ananda_super_admin_username'] = htmlspecialchars($data['admin_username'], ENT_QUOTES, 'UTF-8');
        $GLOBALS['ananda_super_admin_email'] = htmlspecialchars($data['admin_email'], ENT_QUOTES, 'UTF-8');
        $GLOBALS['ananda_super_admin_first_name'] = htmlspecialchars($data['admin_first_name'], ENT_QUOTES, 'UTF-8');
        $GLOBALS['ananda_super_admin_last_name'] = htmlspecialchars($data['admin_last_name'], ENT_QUOTES, 'UTF-8');
        $GLOBALS['ananda_super_admin_role'] = htmlspecialchars($data['admin_role'], ENT_QUOTES, 'UTF-8');
        $_SESSION['ananda_super_admin_role'] = $data['admin_role'];

        return $data;
    }

    return null;
}

function getAnandaSuperAdminInfo() {
    global $ananda_super_current_admin;
    return $ananda_super_current_admin;
}

function getAnandaSuperAdminRole() {
    if (!empty($_SESSION['ananda_super_admin_role']) && is_string($_SESSION['ananda_super_admin_role'])) {
        return $_SESSION['ananda_super_admin_role'];
    }

    $admin = getAnandaSuperAdminInfo();
    if (is_array($admin) && !empty($admin['admin_role']) && is_string($admin['admin_role'])) {
        return $admin['admin_role'];
    }

    return null;
}

function secureLogout() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
        $_SESSION = array();

        if (isset($_COOKIE[session_name()])) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        session_destroy();
    }
}

require_once __DIR__ . '/access_control.php';
