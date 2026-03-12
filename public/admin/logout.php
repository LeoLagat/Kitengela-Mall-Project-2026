<?php
session_start();

// log logout if we know who is logged in
if (!empty($_SESSION['admin_username'])) {
    require_once(__DIR__ . '/../../backend/app/config/database.php');
    require_once(__DIR__ . '/../../backend/app/services/AdminAudit.php');
    $db = new DatabaseConnection();
    $pdo = $db->pdo;
    AdminAudit::log($pdo, $_SESSION['admin_username'], 'logout');
}

$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;