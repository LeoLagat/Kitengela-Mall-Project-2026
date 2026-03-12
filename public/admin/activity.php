<?php
session_start();
// require login and super_admin only
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true ||
    empty($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}
require_once(__DIR__ . '/../../backend/app/config/database.php');
$db = new DatabaseConnection();
$pdo = $db->pdo;

require_once(__DIR__ . '/../../backend/app/services/AdminAudit.php');
if (!empty($_SESSION['admin_username'])) {
    AdminAudit::log($pdo, $_SESSION['admin_username'], 'visited activity log');
}

// ensure table exists (AdminAudit will create or migrate as needed) then read
$stmt = $pdo->query(
    "SELECT created_at, username, action, ip_address
     FROM admin_activity
     ORDER BY created_at DESC
     LIMIT 500"
);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// simple UI
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admin Activity Log</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>table{width:100%;border-collapse:collapse;}th,td{padding:8px;border:1px solid #ccc;text-align:left;}th{background:#f4f4f4;}</style>
</head>
<body>
<nav>
    <div class="logo">Admin Panel</div>
    <div class="links">
        <a href="dashboard.php">Dashboard</a>
        <a href="activity.php">Activity Log</a>
        <a href="subadmin_activity.php">Sub-admin Logs</a>
        <a href="logout.php" style="color:#ffdddd;">Logout</a>
    </div>
</nav>

<div class="container" style="margin-top:20px;">
    <h2>Administrator Activity</h2>
    <p>Listing most recent 500 actions. Contains logins, page visits, downloads, etc.</p>
    <table>
        <thead><tr><th>Time</th><th>Admin</th><th>Action</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?=htmlspecialchars($r['created_at'])?></td>
                <td><?=htmlspecialchars($r['username'] ?? 'unknown')?></td>
                <td><?=htmlspecialchars($r['action'])?></td>
                <td><?=htmlspecialchars($r['ip_address'])?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
