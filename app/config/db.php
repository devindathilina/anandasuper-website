<?php
if (!defined('ANANDA_SUPER_SECURE_ACCESS')) {
    http_response_code(403);
    exit('Direct access not allowed');
}
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$db_config_host = $_ENV['DB_HOST'];
$db_config_name = $_ENV['DB_NAME'];
$db_config_username = $_ENV['DB_USER'];
$db_config_password = $_ENV['DB_PASS'];

$db = new mysqli($db_config_host, $db_config_username, $db_config_password, $db_config_name);

if ($db->connect_error) {
    echo "Internal Server Error";
    exit();
}
$db->set_charset("utf8mb4");
$db->query("SET time_zone = '+05:30'");
date_default_timezone_set('Asia/Colombo');
$app_name = "ANANDA SUPER";
?>