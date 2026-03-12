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

require_once(__DIR__ . '/../../backend/app/services/AdminAudit.php');
if (!empty($_SESSION['admin_username'])) {
    AdminAudit::log($pdo, $_SESSION['admin_username'], 'visited sub-admin activity log');
}

// handle clear logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    $pdo->exec("
        DELETE a FROM admin_activity a
        JOIN administrators ad ON ad.username = a.username
        WHERE ad.role = 'admin'
    ");
    header('Location: subadmin_activity.php?cleared=1');
    exit;
}
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
    <style>
        .container {
            display: block;
            min-height: 0;
            width: 95%;
            max-width: 1000px;
            margin: 20px auto 40px;
        }
        h2 { margin: 0 0 14px; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:8px; border:1px solid silver; text-align:left; }
        th { background:whitesmoke; }
    </style>
</head>
<body>
<nav>
    <div class="logo">Admin Panel</div>
    <div class="links">
        <a href="dashboard.php">Dashboard</a>
        <a href="activity.php">Activity Log</a>
        <a href="subadmin_activity.php" class="active">Sub‑admin Logs</a>
        <a href="logout.php" style="color:mistyrose;">Logout</a>
    </div>
</nav>
<div class="container" style="margin-top:20px;">
    <h2>Sub‑administrator Activity</h2>

    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:15px;">
        <form method="get" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <label style="display:flex;align-items:center;gap:5px;">From <input type="date" name="from" value="<?=htmlspecialchars($from ?? '')?>" required></label>
            <label style="display:flex;align-items:center;gap:5px;">To <input type="date" name="to" value="<?=htmlspecialchars($to ?? '')?>" required></label>
            <button type="submit">Filter</button>
            <?php if ($from && $to): ?>
                <button name="download" value="1">Download CSV</button>
            <?php endif; ?>
        </form>

        <form method="post" onsubmit="return confirm('Are you sure you want to permanently clear ALL sub-admin activity logs? This cannot be undone.');">
            <button type="submit" name="clear_logs" value="1"
                style="background:crimson;color:white;border:none;padding:8px 14px;border-radius:4px;cursor:pointer;">
                Clear All Logs
            </button>
        </form>
    </div>

     <?php if (isset($_GET['cleared'])): ?>
        <div style="margin-bottom:12px;padding:10px;background:honeydew;color:darkgreen;border:1px solid lightgreen;border-radius:4px;">
            Sub-admin activity logs have been cleared successfully.
        </div>
    <?php endif; ?>

    
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
    <div style="display:flex;align-items:center;gap:10px;margin-top:14px;padding:10px 14px;background:whitesmoke;border-left:4px solid slategray;border-radius:4px;">
        <span style="font-size:18px;line-height:1;">&#128274;</span>
        <span style="font-size:13px;color:dimgray;">
            <strong style="color:darkslategray;">Auto-purge enabled</strong> &mdash;
            only the <strong>500 most recent</strong> entries are kept. Older records are removed automatically.
        </span>
    </div>
</div>
</body>
</html>
