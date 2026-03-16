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
$totalRows = count($rows);
$isFiltered = ($from && $to);

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
        .subadmin-page {
            width: 95%;
            max-width: 1120px;
            margin: 45px auto 90px auto;
            display: grid;
            gap: 18px;
        }

        .page-head {
            background: white;
            border: 1px solid lightgray;
            border-left: 8px solid darkgreen;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 10px 24px gainsboro;
        }

        .page-head h2 {
            margin: 0;
            color: forestgreen;
            font-size: 33px;
            line-height: 1.2;
        }

        .page-head p {
            margin: 8px 0 0 0;
            color: dimgray;
            font-size: 16px;
        }

        .summary-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .metric-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: mintcream;
            color: darkgreen;
            border: 1px solid palegreen;
            border-radius: 999px;
            padding: 6px 12px;
            font-weight: 700;
        }

        .content-card {
            background: white;
            border: 1px solid lightgray;
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 8px 20px gainsboro;
        }

        .toolbar {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px;
            align-items: center;
            margin-bottom: 14px;
        }

        .filter-form {
            display: flex;
            align-items: end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-form label {
            display: grid;
            gap: 5px;
            color: darkslategray;
            font-weight: 600;
            font-size: 14px;
        }

        .filter-form input[type="date"] {
            padding: 10px 12px;
            border-radius: 8px;
            border: 2px solid lightgray;
            font-size: 14px;
            box-sizing: border-box;
        }

        .btn-danger {
            background: firebrick;
            box-shadow: 0 4px 0 darkred;
        }

        .btn-danger:hover {
            background: red;
            box-shadow: 0 6px 0 firebrick;
        }

        .btn-danger:active {
            box-shadow: 0 2px 0 firebrick;
        }

        .notice {
            margin-bottom: 14px;
            padding: 12px;
            background: honeydew;
            color: darkgreen;
            border: 1px solid palegreen;
            border-radius: 8px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        .table-wrap table {
            margin-top: 0;
        }

        .empty-state {
            background: floralwhite;
            border: 1px dashed burlywood;
            color: saddlebrown;
            border-radius: 10px;
            padding: 16px;
            font-weight: 600;
        }

        .info-strip {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 14px;
            padding: 10px 14px;
            background: whitesmoke;
            border-left: 4px solid slategray;
            border-radius: 4px;
        }

        .info-strip span:last-child {
            font-size: 13px;
            color: dimgray;
        }

        @media (max-width: 900px) {
            .toolbar {
                grid-template-columns: 1fr;
            }

            .page-head h2 {
                font-size: 28px;
            }

            .subadmin-page {
                margin-top: 28px;
            }
        }
    </style>
</head>
<body>
<nav>
    <div class="logo">Admin Panel</div>
    <div class="links">
        <a href="dashboard.php">Dashboard</a>
        <a href="activity.php">Activity Log</a>
        <a href="subadmin_activity.php" class="active">Sub‑admin Logs</a>
        <a href="profile.php">My Profile</a>
        <a href="logout.php" style="color:red;">Logout</a>
    </div>
</nav>
<main class="subadmin-page">
    <section class="page-head">
        <h2>Sub‑administrator Activity</h2>
        <p>Track all sub-admin actions, filter by period, export records, and manage old log cleanup.</p>
    </section>

    <section class="summary-row">
        <span class="metric-chip">Showing <?= $totalRows ?> record<?= $totalRows === 1 ? '' : 's' ?></span>
        <?php if ($isFiltered): ?>
            <span class="metric-chip">Filtered: <?= htmlspecialchars($from) ?> to <?= htmlspecialchars($to) ?></span>
        <?php else: ?>
            <span class="metric-chip">Filter not applied</span>
        <?php endif; ?>
    </section>

    <section class="content-card">
        <div class="toolbar">
            <form method="get" class="filter-form">
                <label>
                    From
                    <input type="date" name="from" value="<?= htmlspecialchars($from ?? '') ?>" required>
                </label>
                <label>
                    To
                    <input type="date" name="to" value="<?= htmlspecialchars($to ?? '') ?>" required>
                </label>
                <button type="submit">Apply Filter</button>
                <?php if ($isFiltered): ?>
                    <button name="download" value="1">Download CSV</button>
                <?php endif; ?>
            </form>

            <form method="post" onsubmit="return confirm('Are you sure you want to permanently clear ALL sub-admin activity logs? This cannot be undone.');">
                <button type="submit" name="clear_logs" value="1" class="btn-danger">Clear All Logs</button>
            </form>
        </div>

        <?php if (isset($_GET['cleared'])): ?>
            <div class="notice">Sub-admin activity logs have been cleared successfully.</div>
        <?php endif; ?>

        <?php if (empty($rows)): ?>
            <div class="empty-state">No activity records found for the selected period.</div>
        <?php else: ?>
            <div class="table-wrap">
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
        <?php endif; ?>

        <div class="info-strip">
            <span style="font-size:18px;line-height:1;">&#128274;</span>
            <span>
                <strong style="color:darkslategray;">Auto-purge enabled.</strong>
                Only the <strong>500 most recent</strong> entries are kept and older records are removed automatically.
            </span>
        </div>
    </section>
</main>
</body>
</html>
