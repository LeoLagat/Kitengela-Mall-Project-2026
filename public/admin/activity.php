<?php
session_start();
// require login (both super and sub admins may view own activity)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
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

// ensure table exists (AdminAudit will create or migrate as needed)
// optionally handle clearing all logs if super_admin requested
if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin' && isset($_POST['clear'])) {
    $pdo->exec("TRUNCATE TABLE admin_activity");
}

// export CSV if requested via GET (dashboard form)
if (isset($_GET['from']) && isset($_GET['to'])) {
    $from = $_GET['from'];
    $to = $_GET['to'];
    // validate
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        die('Invalid date format');
    }
    // prepare statement depending on role
    if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin') {
        $stmtExport = $pdo->prepare(
            "SELECT created_at, username, action, ip_address
             FROM admin_activity
             WHERE created_at BETWEEN ? AND ?");
        $stmtExport->execute(["$from 00:00:00", "$to 23:59:59"]);
    } else {
        $stmtExport = $pdo->prepare(
            "SELECT created_at, username, action, ip_address
             FROM admin_activity
             WHERE username = ?
               AND created_at BETWEEN ? AND ?");
        $stmtExport->execute([$_SESSION['admin_username'], "$from 00:00:00", "$to 23:59:59"]);
    }
    $rowsExport = $stmtExport->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=activity_' . $from . '_to_' . $to . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Time','Username','Action','IP']);
    foreach ($rowsExport as $r) {
        fputcsv($out, [$r['created_at'],$r['username'],$r['action'],$r['ip_address']]);
    }
    fclose($out);
    exit;
}

// build query depending on role
if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin') {
    $stmt = $pdo->query(
        "SELECT created_at, username, action, ip_address
         FROM admin_activity
         ORDER BY created_at DESC
         LIMIT 500"
    );
} else {
    $stmt = $pdo->prepare(
        "SELECT created_at, username, action, ip_address
         FROM admin_activity
         WHERE username = ?
         ORDER BY created_at DESC
         LIMIT 500"
    );
    $stmt->execute([$_SESSION['admin_username']]);
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// simple UI
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admin Activity Log</title>
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
        <a href="subadmin_activity.php">Sub-admin Logs</a>
        <a href="logout.php" style="color:mistyrose;">Logout</a>
    </div>
</nav>

<div class="container" style="margin-top:20px;">
    <h2>Administrator Activity</h2>

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
    <div style="display:flex;align-items:center;gap:10px;margin-top:14px;padding:10px 14px;background:whitesmoke;border-left:4px solid slategray;border-radius:4px;">
        <span style="font-size:18px;line-height:1;">&#128274;</span>
        <span style="font-size:13px;color:dimgray;">
            <strong style="color:darkslategray;">Auto-purge enabled</strong> &mdash;
            only the <strong>500 most recent</strong> entries are kept. Older records are removed automatically.
        </span>
    </div>
    <?php if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
        <form method="post" onsubmit="return confirm('Delete all activity logs?');" style="margin-top:10px;">
            <button type="submit" name="clear" style="background:crimson;color:white;border:none;padding:8px 14px;border-radius:4px;cursor:pointer;">Clear Activity Log</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
