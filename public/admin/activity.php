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

// load profile details for current admin
$profileStmt = $pdo->prepare(
    "SELECT id, username, role, created_at
     FROM administrators
     WHERE id = ? OR username = ?
     ORDER BY id = ? DESC
     LIMIT 1"
);
$profileStmt->execute([
    $_SESSION['admin_id'] ?? 0,
    $_SESSION['admin_username'] ?? '',
    $_SESSION['admin_id'] ?? 0
]);
$adminProfile = $profileStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'username' => $_SESSION['admin_username'] ?? 'unknown',
    'role' => $_SESSION['admin_role'] ?? 'admin',
    'created_at' => null,
];

$activityStatsStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total_actions, MAX(created_at) AS last_seen
     FROM admin_activity
     WHERE username = ?"
);
$activityStatsStmt->execute([$adminProfile['username']]);
$activityStats = $activityStatsStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_actions' => 0, 'last_seen' => null];

// ensure table exists (AdminAudit will create or migrate as needed)
// optionally handle clearing all logs if super_admin requested
if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin' && isset($_POST['clear'])) {
    $pdo->exec("TRUNCATE TABLE admin_activity");
    header('Location: activity.php?cleared=1');
    exit;
}

$isSuperAdmin = !empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin';
$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;
$hasRange = !empty($from) && !empty($to);
$validRange = $hasRange
    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)
    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to);

if ($hasRange && !$validRange) {
    die('Invalid date format');
}

// export CSV only when explicitly requested
if ($validRange && isset($_GET['download'])) {
    if ($isSuperAdmin) {
        $stmtExport = $pdo->prepare(
            "SELECT created_at, username, action, ip_address
             FROM admin_activity
             WHERE created_at BETWEEN ? AND ?
             ORDER BY created_at DESC"
        );
        $stmtExport->execute(["$from 00:00:00", "$to 23:59:59"]);
    } else {
        $stmtExport = $pdo->prepare(
            "SELECT created_at, username, action, ip_address
             FROM admin_activity
             WHERE username = ?
               AND created_at BETWEEN ? AND ?
             ORDER BY created_at DESC"
        );
        $stmtExport->execute([$_SESSION['admin_username'], "$from 00:00:00", "$to 23:59:59"]);
    }

    $rowsExport = $stmtExport->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=activity_' . $from . '_to_' . $to . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Time','Username','Action','IP']);
    foreach ($rowsExport as $r) {
        fputcsv($out, [$r['created_at'], $r['username'], $r['action'], $r['ip_address']]);
    }
    fclose($out);
    exit;
}

// build log list query depending on role and optional date range
if ($isSuperAdmin) {
    if ($validRange) {
        $stmt = $pdo->prepare(
            "SELECT created_at, username, action, ip_address
             FROM admin_activity
             WHERE created_at BETWEEN ? AND ?
             ORDER BY created_at DESC
             LIMIT 500"
        );
        $stmt->execute(["$from 00:00:00", "$to 23:59:59"]);
    } else {
        $stmt = $pdo->query(
            "SELECT created_at, username, action, ip_address
             FROM admin_activity
             ORDER BY created_at DESC
             LIMIT 500"
        );
    }
} else {
    if ($validRange) {
        $stmt = $pdo->prepare(
            "SELECT created_at, username, action, ip_address
             FROM admin_activity
             WHERE username = ?
               AND created_at BETWEEN ? AND ?
             ORDER BY created_at DESC
             LIMIT 500"
        );
        $stmt->execute([$_SESSION['admin_username'], "$from 00:00:00", "$to 23:59:59"]);
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
}

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalRows = count($rows);
$isFiltered = $validRange;

// simple UI
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admin Activity Log</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .activity-page {
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

        .profile-card {
            background: white;
            border: 1px solid gainsboro;
            border-left: 5px solid seagreen;
            border-radius: 8px;
            padding: 14px;
            margin-bottom: 14px;
        }
        .profile-title {
            margin: 0 0 10px;
            color: darkslategray;
            font-size: 16px;
        }
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 10px;
        }
        .profile-item {
            background: whitesmoke;
            border: 1px solid lightgray;
            border-radius: 6px;
            padding: 10px;
        }
        .profile-item .label {
            display: block;
            color: dimgray;
            font-size: 12px;
            margin-bottom: 4px;
        }
        .profile-item .value {
            color: black;
            font-size: 14px;
            font-weight: 600;
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
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
        }

        .table-wrap th,
        .table-wrap td {
            padding: 8px;
            border: 1px solid silver;
            text-align: left;
        }

        .table-wrap th {
            background: whitesmoke;
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

            .activity-page {
                margin-top: 28px;
            }

            .page-head h2 {
                font-size: 28px;
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
        <a href="subadmin_activity.php">Sub-admin Logs</a>
        <a href="profile.php">My Profile</a>
        <a href="logout.php" style="color:red;">Logout</a>
    </div>
</nav>

<main class="activity-page">
    <section class="page-head">
        <h2>Administrator Activity</h2>
        <p>Review your security trail, filter by date range, and export logs when needed.</p>
    </section>

    <section class="summary-row">
        <span class="metric-chip">Showing <?= $totalRows ?> record<?= $totalRows === 1 ? '' : 's' ?></span>
        <?php if ($isFiltered): ?>
            <span class="metric-chip">Filtered: <?= htmlspecialchars($from) ?> to <?= htmlspecialchars($to) ?></span>
        <?php else: ?>
            <span class="metric-chip">Filter not applied</span>
        <?php endif; ?>
        <span class="metric-chip">Role: <?= htmlspecialchars(str_replace('_', ' ', (string)($adminProfile['role'] ?? 'admin'))) ?></span>
    </section>

    <section class="content-card">
        <div class="profile-card">
            <h3 class="profile-title">Admin Profile</h3>
            <div class="profile-grid">
                <div class="profile-item">
                    <span class="label">Username</span>
                    <span class="value"><?= htmlspecialchars($adminProfile['username'] ?? 'unknown') ?></span>
                </div>
                <div class="profile-item">
                    <span class="label">Role</span>
                    <span class="value"><?= htmlspecialchars(str_replace('_', ' ', (string)($adminProfile['role'] ?? 'admin'))) ?></span>
                </div>
                <div class="profile-item">
                    <span class="label">Account Created</span>
                    <span class="value"><?= !empty($adminProfile['created_at']) ? htmlspecialchars($adminProfile['created_at']) : 'Not available' ?></span>
                </div>
                <div class="profile-item">
                    <span class="label">Total Logged Actions</span>
                    <span class="value"><?= (int)($activityStats['total_actions'] ?? 0) ?></span>
                </div>
                <div class="profile-item">
                    <span class="label">Last Activity</span>
                    <span class="value"><?= !empty($activityStats['last_seen']) ? htmlspecialchars($activityStats['last_seen']) : 'No activity yet' ?></span>
                </div>
            </div>
        </div>

        <div class="toolbar">
            <form method="get" class="filter-form">
                <label>
                    From
                    <input type="date" name="from" value="<?= htmlspecialchars($from ?? '') ?>">
                </label>
                <label>
                    To
                    <input type="date" name="to" value="<?= htmlspecialchars($to ?? '') ?>">
                </label>
                <button type="submit">Apply Filter</button>
                <?php if ($isFiltered): ?>
                    <button type="submit" name="download" value="1">Download CSV</button>
                <?php endif; ?>
            </form>

            <?php if ($isSuperAdmin): ?>
                <form method="post" onsubmit="return confirm('Delete all activity logs?');">
                    <button type="submit" name="clear" class="btn-danger">Clear Activity Log</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (isset($_GET['cleared'])): ?>
            <div class="notice">Activity logs have been cleared successfully.</div>
        <?php endif; ?>

        <?php if (empty($rows)): ?>
            <div class="empty-state">No activity entries found for the selected criteria.</div>
        <?php else: ?>
            <div class="table-wrap">
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
