<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once(__DIR__ . '/../../backend/app/config/database.php');
$db  = new DatabaseConnection();
$pdo = $db->pdo;

require_once(__DIR__ . '/../../backend/app/services/AdminAudit.php');
if (!empty($_SESSION['admin_username'])) {
    AdminAudit::log($pdo, $_SESSION['admin_username'], 'viewed own profile');
}

// ── Load profile from DB ──────────────────────────────────────────────────
$profileStmt = $pdo->prepare(
    "SELECT id, username, role, created_at
     FROM administrators
     WHERE id = ? OR username = ?
     ORDER BY id = ? DESC
     LIMIT 1"
);
$profileStmt->execute([
    $_SESSION['admin_id']       ?? 0,
    $_SESSION['admin_username'] ?? '',
    $_SESSION['admin_id']       ?? 0,
]);
$profile = $profileStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'id'         => null,
    'username'   => $_SESSION['admin_username'] ?? 'unknown',
    'role'       => $_SESSION['admin_role']     ?? 'admin',
    'created_at' => null,
];

// ── Activity stats ────────────────────────────────────────────────────────
$statsStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total_actions, MAX(created_at) AS last_seen
     FROM admin_activity WHERE username = ?"
);
$statsStmt->execute([$profile['username']]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_actions' => 0, 'last_seen' => null];

// ── Recent activity (last 10) ─────────────────────────────────────────────
$recentStmt = $pdo->prepare(
    "SELECT created_at, action, ip_address
     FROM admin_activity
     WHERE username = ?
     ORDER BY created_at DESC
     LIMIT 10"
);
$recentStmt->execute([$profile['username']]);
$recentActivity = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Handle password change ────────────────────────────────────────────────
$pwMessage = '';
$pwError   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$current || !$new || !$confirm) {
        $pwError = 'All password fields are required.';
    } elseif (strlen($new) < 6) {
        $pwError = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $pwError = 'New password and confirmation do not match.';
    } else {
        // verify current password
        $checkStmt = $pdo->prepare("SELECT password FROM administrators WHERE id = ?");
        $checkStmt->execute([$profile['id']]);
        $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if ($row && password_verify($current, $row['password'])) {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE administrators SET password = ? WHERE id = ?")
                ->execute([$hash, $profile['id']]);
            AdminAudit::log($pdo, $profile['username'], 'changed own password');
            $pwMessage = 'Password updated successfully.';
        } else {
            $pwError = 'Current password is incorrect.';
        }
    }
}

$isSuperAdmin = ($profile['role'] === 'super_admin');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Profile &mdash; Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .container {
            display: block;
            min-height: 0;
            width: 95%;
            max-width: 900px;
            margin: 24px auto 48px;
        }

        /* ── Page header ─────────────────────────── */
        .page-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 22px;
        }
        .avatar {
            width: 58px;
            height: 58px;
            background: linear-gradient(135deg, darkgreen, seagreen);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: white;
            flex-shrink: 0;
        }
        .page-header-text h2 {
            margin: 0 0 2px;
            color: darkslategray;
            font-size: 22px;
        }
        .page-header-text p {
            margin: 0;
            color: dimgray;
            font-size: 13px;
        }
        .role-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            text-transform: capitalize;
            margin-left: 8px;
            vertical-align: middle;
        }
        .role-badge.super-admin {
            background: darkgreen;
            color: white;
        }
        .role-badge.admin {
            background: steelblue;
            color: white;
        }

        /* ── Cards ───────────────────────────────── */
        .section-card {
            background: white;
            border: 1px solid gainsboro;
            border-left: 5px solid seagreen;
            border-radius: 8px;
            padding: 18px;
            margin-bottom: 18px;
        }
        .section-card.pw-card {
            border-left-color: steelblue;
        }
        .section-card h3 {
            margin: 0 0 14px;
            color: darkslategray;
            font-size: 15px;
        }

        /* ── Profile detail grid ─────────────────── */
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 10px;
        }
        .detail-item {
            background: whitesmoke;
            border: 1px solid lightgray;
            border-radius: 6px;
            padding: 10px 12px;
        }
        .detail-item .lbl {
            display: block;
            color: dimgray;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        .detail-item .val {
            color: darkslategray;
            font-size: 14px;
            font-weight: 700;
        }

        /* ── Recent activity table ───────────────── */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px 10px;
            border: 1px solid gainsboro;
            text-align: left;
            font-size: 13px;
        }
        th {
            background: whitesmoke;
            color: darkslategray;
        }
        .no-rows {
            text-align: center;
            color: dimgray;
            padding: 14px;
        }

        /* ── Password form ───────────────────────── */
        .pw-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
        }
        .field {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .field label {
            font-size: 12px;
            font-weight: 600;
            color: dimgray;
        }
        .field input[type="password"] {
            padding: 9px 11px;
            border: 1px solid silver;
            border-radius: 6px;
            font-size: 13px;
            background: whitesmoke;
            width: 100%;
            box-sizing: border-box;
            text-transform: none;
            text-align: left;
        }
        .field input[type="password"]:focus {
            outline: none;
            border-color: steelblue;
            background: white;
            box-shadow: 0 0 0 3px rgba(70,130,180,0.15);
        }
        .pw-submit {
            margin-top: 14px;
        }
        .btn-save {
            padding: 10px 22px;
            background: steelblue;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            width: auto;
        }
        .btn-save:hover { background: royalblue; }

        /* ── Alerts ──────────────────────────────── */
        .alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 14px;
        }
        .alert-success {
            background: honeydew;
            color: darkgreen;
            border: 1px solid lightgreen;
        }
        .alert-error {
            background: mistyrose;
            color: maroon;
            border: 1px solid lightcoral;
        }
    </style>
</head>
<body>
<nav>
    <div class="logo">Admin Panel</div>
    <div class="links">
        <a href="dashboard.php">Dashboard</a>
        <?php if ($isSuperAdmin): ?>
            <a href="activity.php">Activity Log</a>
            <a href="subadmin_activity.php">Sub-admin Logs</a>
        <?php else: ?>
            <a href="activity.php">My Activity</a>
        <?php endif; ?>
        <a href="profile.php" class="active">My Profile</a>
        <a href="logout.php" style="color:mistyrose;">Logout</a>
    </div>
</nav>

<div class="container">

    <!-- Page header -->
    <div class="page-header">
        <div class="avatar">&#128100;</div>
        <div class="page-header-text">
            <h2>
                <?= htmlspecialchars($profile['username']) ?>
                <span class="role-badge <?= $isSuperAdmin ? 'super-admin' : 'admin' ?>">
                    <?= $isSuperAdmin ? 'Super Admin' : 'Sub Admin' ?>
                </span>
            </h2>
            <p>Administrator account &mdash; Kitengela Parking</p>
        </div>
    </div>

    <!-- Profile details -->
    <div class="section-card">
        <h3>&#128203; Account Details</h3>
        <div class="detail-grid">
            <div class="detail-item">
                <span class="lbl">Username</span>
                <span class="val"><?= htmlspecialchars($profile['username']) ?></span>
            </div>
            <div class="detail-item">
                <span class="lbl">Role</span>
                <span class="val"><?= $isSuperAdmin ? 'Super Admin' : 'Sub Admin' ?></span>
            </div>
            <div class="detail-item">
                <span class="lbl">Account Created</span>
                <span class="val"><?= !empty($profile['created_at']) ? htmlspecialchars($profile['created_at']) : 'N/A' ?></span>
            </div>
            <div class="detail-item">
                <span class="lbl">Total Actions Logged</span>
                <span class="val"><?= (int)$stats['total_actions'] ?></span>
            </div>
            <div class="detail-item">
                <span class="lbl">Last Activity</span>
                <span class="val"><?= !empty($stats['last_seen']) ? htmlspecialchars($stats['last_seen']) : 'No activity yet' ?></span>
            </div>
            <div class="detail-item">
                <span class="lbl">Account ID</span>
                <span class="val">#<?= (int)($profile['id'] ?? 0) ?></span>
            </div>
        </div>
    </div>

    <!-- Recent activity -->
    <div class="section-card">
        <h3>&#128336; Recent Activity (last 10 actions)</h3>
        <?php if (empty($recentActivity)): ?>
            <p class="no-rows">No activity recorded yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>Time</th><th>Action</th><th>IP Address</th></tr>
            </thead>
            <tbody>
            <?php foreach ($recentActivity as $a): ?>
                <tr>
                    <td><?= htmlspecialchars($a['created_at']) ?></td>
                    <td><?= htmlspecialchars($a['action']) ?></td>
                    <td><?= htmlspecialchars($a['ip_address'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Change password -->
    <div class="section-card pw-card">
        <h3>&#128274; Change Password</h3>

        <?php if ($pwMessage): ?>
            <div class="alert alert-success">&#9989; <?= htmlspecialchars($pwMessage) ?></div>
        <?php endif; ?>
        <?php if ($pwError): ?>
            <div class="alert alert-error">&#9888;&#65039; <?= htmlspecialchars($pwError) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="pw-grid">
                <div class="field">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" placeholder="Enter current password" required>
                </div>
                <div class="field">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" placeholder="Min. 6 characters" required>
                </div>
                <div class="field">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat new password" required>
                </div>
            </div>
            <div class="pw-submit">
                <button type="submit" name="change_password" class="btn-save">&#128274; Update Password</button>
            </div>
        </form>
    </div>

</div>
</body>
</html>
