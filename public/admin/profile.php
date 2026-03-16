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
$recentCount = count($recentActivity);

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

        .profile-shell {
            display: grid;
            gap: 16px;
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

        .stat-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 2px;
        }

        .stat-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: mintcream;
            border: 1px solid palegreen;
            color: darkgreen;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 700;
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

        .table-wrap {
            overflow-x: auto;
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

        .input-wrap {
            position: relative;
        }

        .input-wrap .input-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: dimgray;
            font-size: 13px;
            pointer-events: none;
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
            padding-left: 30px;
            padding-right: 34px;
        }
        .field input[type="password"]:focus {
            outline: none;
            border-color: steelblue;
            background: white;
            box-shadow: 0 0 0 3px lightsteelblue;
        }

        .toggle-pw {
            all: unset;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: dimgray;
            line-height: 1;
        }

        .toggle-pw:hover {
            color: darkslategray;
        }

        .pw-meter {
            height: 6px;
            border-radius: 999px;
            background: gainsboro;
            margin-top: 8px;
            overflow: hidden;
        }

        .pw-meter-fill {
            height: 100%;
            width: 0;
            background: firebrick;
            transition: width .2s, background .2s;
        }

        .pw-meter-label {
            margin-top: 6px;
            font-size: 12px;
            color: dimgray;
        }

        .caps-warning {
            display: none;
            margin-top: 8px;
            font-size: 12px;
            color: maroon;
            background: mistyrose;
            border: 1px solid lightcoral;
            border-radius: 6px;
            padding: 6px 8px;
        }

        .caps-warning.show {
            display: block;
        }

        .match-hint {
            margin-top: 6px;
            font-size: 12px;
            color: dimgray;
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
            transition: opacity .2s, transform .1s;
        }
        .btn-save:hover { background: royalblue; }

        .btn-save:active { transform: scale(.99); }

        .btn-save:disabled {
            opacity: .6;
            cursor: not-allowed;
            transform: none;
        }

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

        @media (max-width: 700px) {
            .page-header {
                align-items: flex-start;
            }

            .page-header-text h2 {
                font-size: 20px;
            }
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
        <a href="logout.php" style="color:red;">Logout</a>
    </div>
</nav>

<div class="container profile-shell">

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

    <div class="stat-chips">
        <span class="stat-chip">Actions Logged: <?= (int)$stats['total_actions'] ?></span>
        <span class="stat-chip">Recent Shown: <?= $recentCount ?></span>
        <span class="stat-chip">Last Activity: <?= !empty($stats['last_seen']) ? htmlspecialchars($stats['last_seen']) : 'No activity yet' ?></span>
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
        <div class="table-wrap">
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
        </div>
        <?php endif; ?>
    </div>

    <!-- Change password -->
    <div class="section-card pw-card">
        <h3>&#128274; Change Password</h3>

        <?php if ($pwMessage): ?>
            <div class="alert alert-success" role="alert" aria-live="polite">&#9989; <?= htmlspecialchars($pwMessage) ?></div>
        <?php endif; ?>
        <?php if ($pwError): ?>
            <div class="alert alert-error" role="alert" aria-live="assertive">&#9888;&#65039; <?= htmlspecialchars($pwError) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" id="passwordForm">
            <div class="pw-grid">
                <div class="field">
                    <label for="current_password">Current Password</label>
                    <div class="input-wrap">
                        <span class="input-icon">&#128274;</span>
                        <input type="password" id="current_password" name="current_password" placeholder="Enter current password" required autocomplete="current-password">
                        <button type="button" class="toggle-pw" onclick="togglePw(this, 'current_password')" title="Show password" aria-label="Show password" aria-pressed="false">&#128065;</button>
                    </div>
                </div>
                <div class="field">
                    <label for="new_password">New Password</label>
                    <div class="input-wrap">
                        <span class="input-icon">&#128274;</span>
                        <input type="password" id="new_password" name="new_password" placeholder="Min. 6 characters" required minlength="6" autocomplete="new-password">
                        <button type="button" class="toggle-pw" onclick="togglePw(this, 'new_password')" title="Show password" aria-label="Show password" aria-pressed="false">&#128065;</button>
                    </div>
                    <div class="pw-meter"><div id="pwMeterFill" class="pw-meter-fill"></div></div>
                    <div id="pwMeterLabel" class="pw-meter-label">Password strength: too weak</div>
                    <div id="capsWarning" class="caps-warning" aria-live="polite">Caps Lock appears to be ON.</div>
                </div>
                <div class="field">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="input-wrap">
                        <span class="input-icon">&#128274;</span>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat new password" required minlength="6" autocomplete="new-password">
                        <button type="button" class="toggle-pw" onclick="togglePw(this, 'confirm_password')" title="Show password" aria-label="Show password" aria-pressed="false">&#128065;</button>
                    </div>
                    <div id="matchHint" class="match-hint">Passwords must match before update.</div>
                </div>
            </div>
            <div class="pw-submit">
                <button type="submit" name="change_password" class="btn-save">&#128274; Update Password</button>
            </div>
        </form>
    </div>

</div>
<script>
function togglePw(btn, inputId) {
    const input = document.getElementById(inputId);
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.setAttribute('aria-pressed', input.type === 'text' ? 'true' : 'false');
    btn.setAttribute('aria-label', input.type === 'text' ? 'Hide password' : 'Show password');
    btn.title = input.type === 'text' ? 'Hide password' : 'Show password';
    btn.textContent = input.type === 'password' ? '\u{1F441}' : '\u{1F648}';
}

const passwordForm = document.getElementById('passwordForm');
const newPassword = document.getElementById('new_password');
const confirmPassword = document.getElementById('confirm_password');
const meterFill = document.getElementById('pwMeterFill');
const meterLabel = document.getElementById('pwMeterLabel');
const matchHint = document.getElementById('matchHint');
const capsWarning = document.getElementById('capsWarning');

function updateStrength() {
    const value = newPassword.value;
    let score = 0;

    if (value.length >= 6) score++;
    if (value.length >= 10) score++;
    if (/[A-Z]/.test(value) && /[a-z]/.test(value)) score++;
    if (/\d/.test(value)) score++;
    if (/[^A-Za-z0-9]/.test(value)) score++;

    const strengthLevels = [
        { width: 20, label: 'too weak', color: 'firebrick' },
        { width: 35, label: 'weak', color: 'orangered' },
        { width: 55, label: 'fair', color: 'goldenrod' },
        { width: 75, label: 'good', color: 'seagreen' },
        { width: 100, label: 'strong', color: 'darkgreen' }
    ];

    const level = value.length === 0 ? strengthLevels[0] : strengthLevels[Math.min(score, 4)];
    meterFill.style.width = level.width + '%';
    meterFill.style.background = level.color;
    meterLabel.textContent = 'Password strength: ' + level.label;
}

function updateMatchHint() {
    if (!confirmPassword.value) {
        matchHint.textContent = 'Passwords must match before update.';
        matchHint.style.color = 'dimgray';
        return;
    }

    if (newPassword.value === confirmPassword.value) {
        matchHint.textContent = 'Passwords match.';
        matchHint.style.color = 'darkgreen';
    } else {
        matchHint.textContent = 'Passwords do not match yet.';
        matchHint.style.color = 'maroon';
    }
}

function updateCapsState(event) {
    if (event.getModifierState && event.getModifierState('CapsLock')) {
        capsWarning.classList.add('show');
    } else {
        capsWarning.classList.remove('show');
    }
}

newPassword.addEventListener('input', function() {
    updateStrength();
    updateMatchHint();
});
confirmPassword.addEventListener('input', updateMatchHint);
newPassword.addEventListener('keydown', updateCapsState);
newPassword.addEventListener('keyup', updateCapsState);
newPassword.addEventListener('blur', function() {
    capsWarning.classList.remove('show');
});

passwordForm.addEventListener('submit', function(event) {
    if (newPassword.value !== confirmPassword.value) {
        event.preventDefault();
        matchHint.textContent = 'Passwords do not match yet.';
        matchHint.style.color = 'maroon';
        confirmPassword.focus();
        return;
    }

    const submitBtn = passwordForm.querySelector('.btn-save');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Updating...';
});

updateStrength();
</script>
</body>
</html>
