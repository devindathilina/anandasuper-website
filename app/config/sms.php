<?php
if (!defined('ANANDA_SUPER_SECURE_ACCESS')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

function sendSMS(string $mobile_number, string $message): bool {
    $api_key = $_ENV['TEXTIT_API_KEY'] ?? '';

    if (empty($api_key)) {
        error_log("SMS service configuration error: Missing TEXTIT_API_KEY");
        return false;
    }

    if (empty($mobile_number)) {
        error_log("SMS service error: Empty mobile number");
        return false;
    }

    if (empty($message)) {
        error_log("SMS service error: Empty message body");
        return false;
    }

    $url = "https://api.textit.biz/";
    $payload = json_encode([
        "to" => $mobile_number,
        "text" => $message
    ]);

    $headers = [
        "Content-Type: application/json",
        "Accept: */*",
        "X-API-VERSION: v1",
        "Authorization: Basic " . $api_key
    ];

    $ch = curl_init($url);
    if ($ch === false) {
        error_log("SMS service initialization error: curl_init failed");
        return false;
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || !empty($error)) {
        error_log("SMS service connection error: " . $error);
        return false;
    }

    if ($http_code >= 200 && $http_code < 300) {
        return true;
    }

    error_log("SMS API error: HTTP $http_code - Response: " . $response);
    return false;
}

?>
