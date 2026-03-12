<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once(__DIR__ . '/../../backend/app/config/database.php');
$db = new DatabaseConnection();
$pdo = $db->pdo;

require_once(__DIR__ . '/../../backend/app/services/AdminAudit.php');
if (isset($_SESSION['admin_id'])) {
    AdminAudit::log($pdo, $_SESSION['admin_id'], 'visited activity log');
}

// ensure table exists (AdminAudit will create on first log) but we can just query
$stmt = $pdo->query(
    "SELECT a.created_at, ad.username, a.action, a.ip_address
     FROM admin_activity a
     LEFT JOIN administrators ad ON ad.id = a.admin_id
     ORDER BY a.created_at DESC
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
