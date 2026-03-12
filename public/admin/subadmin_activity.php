<?php
session_start();
// only super_admins can access
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true 
    || $_SESSION['admin_role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

require_once(__DIR__ . '/../../backend/app/config/database.php');
$db = new DatabaseConnection();
$pdo = $db->pdo;

// allow filtering by date range
$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;

// build query
$sql = "SELECT a.created_at, a.username, a.action, a.ip_address
        FROM admin_activity a
        JOIN administrators ad ON ad.username = a.username
        WHERE ad.role = 'admin'";
$params = [];
if ($from && $to) {
    $sql .= " AND a.created_at BETWEEN ? AND ?";
    $params[] = "$from 00:00:00";
    $params[] = "$to 23:59:59";
}
$sql .= " ORDER BY a.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// if download requested
if ($from && $to && isset($_GET['download'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=subadmin_activity_' . $from . '_to_' . $to . '.csv');
    $out = fopen('php://output','w');
    fputcsv($out, ['Time','Username','Action','IP']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['created_at'],$r['username'],$r['action'],$r['ip_address']]);
    }
    fclose($out);
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sub‑admin Activity</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>table{width:100%;border-collapse:collapse;}th,td{padding:8px;border:1px solid #ccc;text-align:left;}th{background:#f4f4f4;}</style>
</head>
<body>
<nav>
    <div class="logo">Admin Panel</div>
    <div class="links">
        <a href="dashboard.php">Dashboard</a>
        <a href="activity.php">Activity Log</a>
        <a href="subadmin_activity.php" class="active">Sub‑admin Logs</a>
        <a href="logout.php" style="color:#ffdddd;">Logout</a>
    </div>
</nav>

<div class="container" style="margin-top:20px;">
    <h2>Sub‑administrator Activity</h2>
    <form method="get" style="margin-bottom:15px;">
        <label>From <input type="date" name="from" value="<?=htmlspecialchars($from)?>" required></label>
        <label>To <input type="date" name="to" value="<?=htmlspecialchars($to)?>" required></label>
        <button type="submit">Filter</button>
        <?php if ($from && $to): ?>
            <button name="download" value="1" style="margin-left:10px;">Download CSV</button>
        <?php endif; ?>
    </form>
    <table>
        <thead><tr><th>Time</th><th>User</th><th>Action</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?=htmlspecialchars($r['created_at'])?></td>
                <td><?=htmlspecialchars($r['username'])?></td>
                <td><?=htmlspecialchars($r['action'])?></td>
                <td><?=htmlspecialchars($r['ip_address'])?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
