<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth.php';

secureLogout();

header('Location: ../index.php');
exit;
?>