<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/api_functions.php';

const INTERNAL_ERROR = ['error' => 'Internal Server Error'];

function sendResponse($data, $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function fetchTerms($db, &$stmt = null) {
    $stmt = $db->prepare("SELECT term_id, term_text FROM ananda_super_terms LIMIT 1");
    if ($stmt === false) return false;

    if (!$stmt->execute() ||
        ($result = $stmt->get_result()) === false) {
        return false;
    }

    if ($result->num_rows === 0) {
        $stmt->close();
        return null;
    }

    $data = $result->fetch_assoc();
    $stmt->close();

    return [
        'id' => (int)$data['term_id'],
        'text' => $data['term_text']
    ];
}

$data = fetchTerms($db, $stmt);

if ($data === false) {
    sendResponse(INTERNAL_ERROR, 500);
}

sendResponse([
    'success' => true,
    'data' => $data
]);
?>