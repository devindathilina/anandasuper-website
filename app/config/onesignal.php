<?php
if (!defined('ANANDA_SUPER_SECURE_ACCESS')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

function sendOneSignalNotification(array $fields): bool {
    $appId = $_ENV['ONESIGNAL_APP_ID'] ?? '';
    $apiKey = $_ENV['ONESIGNAL_REST_API_KEY'] ?? '';

    if (!$appId || !$apiKey) {
        error_log('OneSignal configuration is missing.');
        return false;
    }

    $fields['app_id'] = $appId;

    $ch = curl_init('https://api.onesignal.com/notifications');
    if ($ch === false) {
        error_log('OneSignal cURL initialization failed.');
        return false;
    }

    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json; charset=utf-8',
            "Authorization: Basic $apiKey"
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($fields),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log('OneSignal cURL Error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }

    $responseData = json_decode($response, true);
    curl_close($ch);

    if (empty($responseData) || isset($responseData['errors'])) {
        error_log('OneSignal API Error: ' . $response);
        return false;
    }

    return true;
}

function sendPublicPush(string $title, string $message, array $additionalData = []): bool {
    $title = trim($title);
    $message = trim($message);

    if (empty($title) || empty($message)) {
        error_log('OneSignal: Title and message are required.');
        return false;
    }

    $fields = [
        'included_segments' => ['All'],
        'headings' => ['en' => $title],
        'contents' => ['en' => $message],
        'priority' => 10
    ];

    if (!empty($additionalData)) {
        $fields['data'] = $additionalData;
    }

    return sendOneSignalNotification($fields);
}

function sendPushNotificationToPlayer(string $playerId, string $title, string $message, array $additionalData = []): bool {
    $playerId = trim($playerId);
    $title = trim($title);
    $message = trim($message);

    if (empty($playerId)) {
        error_log('OneSignal: Player ID is required.');
        return false;
    }

    if (empty($title) || empty($message)) {
        error_log('OneSignal: Title and message are required.');
        return false;
    }

    $fields = [
        'include_player_ids' => [$playerId],
        'headings' => ['en' => $title],
        'contents' => ['en' => $message],
        'priority' => 10
    ];

    if (!empty($additionalData)) {
        $fields['data'] = $additionalData;
    }

    return sendOneSignalNotification($fields);
}
?>
