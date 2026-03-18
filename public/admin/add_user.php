<?php
session_start();
// only super_admin can access
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true ||
    empty($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

require_once(__DIR__ . '/../../backend/app/config/database.php');
$db = new DatabaseConnection();
$pdo = $db->pdo;

// log admin visiting the add-user page
require_once(__DIR__ . '/../../backend/app/services/AdminAudit.php');
if (!empty($_SESSION['admin_username'])) {
    AdminAudit::log($pdo, $_SESSION['admin_username'], 'visited add user page');
}

$message = '';
$currentUsername = strtolower($_SESSION['admin_username'] ?? '');
// only super_admins may add other users
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_SESSION['admin_role'] !== 'super_admin') {
        $message = "Only the main admin can create new users.";
    } elseif (isset($_POST['remove_admin_id'])) {
        $removeId = (int) ($_POST['remove_admin_id'] ?? 0);
        $removeRole = $_POST['remove_role'] ?? 'admin';
        if (!in_array($removeRole, ['admin', 'super_admin'], true)) {
            $removeRole = 'admin';
        }
        if ($removeId <= 0) {
            $message = "Invalid administrator selected.";
        } else {
            $checkStmt = $pdo->prepare("SELECT id, username, role FROM administrators WHERE id = ?");
            $checkStmt->execute([$removeId]);
            $targetAdmin = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$targetAdmin) {
                $message = "Administrator account not found.";
            } elseif (($targetAdmin['role'] ?? '') !== $removeRole) {
                $message = "Role mismatch for selected administrator.";
            } elseif (!empty($_SESSION['admin_username']) && strtolower($targetAdmin['username']) === $currentUsername) {
                $message = "You cannot remove your own account while logged in.";
            } elseif ($removeRole === 'super_admin') {
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM administrators WHERE role = 'super_admin'");
                $countStmt->execute();
                $superCount = (int) $countStmt->fetchColumn();
                if ($superCount <= 1) {
                    $message = "Cannot remove the last super-admin account.";
                } else {
                    $deleteStmt = $pdo->prepare("DELETE FROM administrators WHERE id = ? AND role = 'super_admin'");
                    $deleteStmt->execute([$removeId]);
                    if ($deleteStmt->rowCount() > 0) {
                        $removedUser = $targetAdmin['username'];
                        $message = "Super-admin '$removedUser' removed successfully.";
                        if (!empty($_SESSION['admin_username'])) {
                            AdminAudit::log($pdo, $_SESSION['admin_username'], "removed super-admin user $removedUser");
                        }
                    } else {
                        $message = "No super-admin was removed.";
                    }
                }
            } else {
                $deleteStmt = $pdo->prepare("DELETE FROM administrators WHERE id = ? AND role = 'admin'");
                $deleteStmt->execute([$removeId]);
                if ($deleteStmt->rowCount() > 0) {
                    $removedUser = $targetAdmin['username'];
                    $message = "Sub-admin '$removedUser' removed successfully.";
                    if (!empty($_SESSION['admin_username'])) {
                        AdminAudit::log($pdo, $_SESSION['admin_username'], "removed sub-admin user $removedUser");
                    }
                } else {
                    $message = "No sub-admin was removed.";
                }
            }
        }
    } else {
        $uname = strtolower(trim($_POST['username'] ?? ''));
        $pwd = $_POST['password'] ?? '';
        $newRole = $_POST['new_role'] ?? 'admin';
        if (!in_array($newRole, ['admin', 'super_admin'], true)) {
            $newRole = 'admin';
        }
        if ($uname && $pwd) {
            // insert new administrator, avoid duplicates
            $stmt = $pdo->prepare("SELECT id FROM administrators WHERE LOWER(username)=?");
            $stmt->execute([$uname]);
            if ($stmt->fetch()) {
                $message = "A user with that username already exists.";
            } else {
                $hash = password_hash($pwd, PASSWORD_DEFAULT);
                $stmt2 = $pdo->prepare("INSERT INTO administrators (username, password, role) VALUES (?, ?, ?)");
                $stmt2->execute([$uname, $hash, $newRole]);
                if ($newRole === 'super_admin') {
                    $message = "Super-admin '$uname' added successfully.";
                    if (!empty($_SESSION['admin_username'])) {
                        AdminAudit::log($pdo, $_SESSION['admin_username'], "added super-admin user $uname");
                    }
                } else {
                    $message = "Sub-admin '$uname' added successfully.";
                    if (!empty($_SESSION['admin_username'])) {
                        AdminAudit::log($pdo, $_SESSION['admin_username'], "added sub-admin user $uname");
                    }
                }
            }
        } else {
            $message = "Please provide both username and password.";
        }
    }
}

$subAdmins = [];
$subAdminStmt = $pdo->prepare("SELECT id, username, created_at FROM administrators WHERE role = 'admin' ORDER BY created_at DESC, username ASC");
$subAdminStmt->execute();
$subAdmins = $subAdminStmt->fetchAll(PDO::FETCH_ASSOC);

$superAdmins = [];
$superAdminStmt = $pdo->prepare("SELECT id, username, created_at FROM administrators WHERE role = 'super_admin' ORDER BY created_at DESC, username ASC");
$superAdminStmt->execute();
$superAdmins = $superAdminStmt->fetchAll(PDO::FETCH_ASSOC);
$superAdminCount = count($superAdmins);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Administrator</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, honeydew, whitesmoke);
        }

        .page-wrapper {
            min-height: calc(100vh - 60px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
        }

        .form-card {
            width: 100%;
            max-width: 480px;
            background: white;
            border-radius: 14px;
            border: 1px solid lightgray;
            box-shadow: 0 12px 30px gainsboro;
            overflow: hidden;
        }

        .form-card-header {
            background: linear-gradient(90deg, darkgreen 0%, seagreen 100%);
            padding: 28px 32px 22px;
            color: white;
        }

        nav {
            background: linear-gradient(90deg, darkgreen 0%, seagreen 100%);
        }

        .form-card-header h2 {
            margin: 0 0 4px;
            font-size: 22px;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-card-header p {
            margin: 0;
            font-size: 13px;
            opacity: 0.82;
        }

        .form-card-body {
            padding: 28px 32px 32px;
        }

        .security-note {
            margin-bottom: 18px;
            padding: 10px 12px;
            border-radius: 8px;
            background: mintcream;
            border: 1px solid palegreen;
            color: darkgreen;
            font-size: 13px;
        }

        .alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .alert-success { background: honeydew; color: darkgreen; border: 1px solid palegreen; }
        .alert-error   { background: mistyrose; color: maroon; border: 1px solid lightcoral; }
        .alert-info    { background: whitesmoke; color: darkslategray; border: 1px solid lightgray; }

        .field-group {
            margin-bottom: 18px;
        }

        .field-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: darkslategray;
            margin-bottom: 6px;
        }

        .field-group .input-wrap {
            position: relative;
        }

        .field-group .input-wrap span {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 15px;
            color: dimgray;
            pointer-events: none;
        }

        .field-group input {
            width: 100%;
            padding: 10px 12px 10px 36px;
            border: 1px solid lightgray;
            border-radius: 8px;
            font-size: 14px;
            color: darkslategray;
            background: whitesmoke;
            box-sizing: border-box;
            transition: border-color .2s, box-shadow .2s, background .2s;
            text-transform: none;
            text-align: left;
        }

        .field-group input:focus {
            outline: none;
            border-color: seagreen;
            box-shadow: 0 0 0 3px lightgreen;
            background: white;
        }

        .field-hint {
            margin-top: 6px;
            font-size: 12px;
            color: dimgray;
        }

        .toggle-pw {
            all: unset;
            position: absolute;
            right: 11px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 15px;
            color: dimgray;
            padding: 0;
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

        .btn-submit {
            width: 100%;
            padding: 11px;
            background: linear-gradient(90deg, darkgreen 0%, seagreen 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: .3px;
            transition: opacity .2s, transform .1s;
            margin-top: 4px;
        }

        .btn-submit:hover { opacity: .88; }

        .btn-submit:active { transform: scale(.99); }

        .btn-submit:disabled {
            opacity: .6;
            cursor: not-allowed;
            transform: none;
        }

        .form-hint {
            font-size: 12px;
            color: dimgray;
            margin-top: 16px;
            text-align: center;
        }

        .subadmin-list {
            margin-top: 22px;
            padding-top: 16px;
            border-top: 1px solid lightgray;
        }

        .subadmin-list h3 {
            margin: 0 0 10px 0;
            font-size: 18px;
            color: forestgreen;
        }

        .role-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid lightgray;
            border-radius: 8px;
            font-size: 14px;
            color: darkslategray;
            background: whitesmoke;
            box-sizing: border-box;
        }

        .role-select:focus {
            outline: none;
            border-color: seagreen;
            box-shadow: 0 0 0 3px lightgreen;
            background: white;
        }

        .subadmin-list table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .subadmin-list th,
        .subadmin-list td {
            border: 1px solid lightgray;
            padding: 8px;
            text-align: left;
            font-size: 13px;
        }

        .subadmin-list th {
            background: whitesmoke;
            color: darkslategray;
        }

        .remove-admin-btn {
            border: none;
            border-radius: 6px;
            padding: 7px 10px;
            background: firebrick;
            color: white;
            font-weight: 700;
            cursor: pointer;
            font-size: 12px;
        }

        .remove-admin-btn:hover {
            background: red;
        }

        .remove-super-btn {
            border: none;
            border-radius: 6px;
            padding: 7px 10px;
            background: saddlebrown;
            color: white;
            font-weight: 700;
            cursor: pointer;
            font-size: 12px;
        }

        .remove-super-btn:hover {
            background: sienna;
        }

        .remove-super-btn:disabled {
            background: darkgray;
            cursor: not-allowed;
        }

        .subadmin-empty {
            background: floralwhite;
            border: 1px dashed burlywood;
            color: saddlebrown;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 13px;
            font-weight: 600;
        }

        @media (max-width: 520px) {
            .form-card-body {
                padding: 22px 18px 22px;
            }

            .form-card-header {
                padding: 22px 18px 18px;
            }
        }
    </style>
</head>
<body>
<nav>
    <div class="logo">Admin Panel</div>
    <div class="links">
        <a href="dashboard.php">Dashboard</a>
        <a href="restricted.php">Restricted List</a>
        <?php if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
            <a href="activity.php">Activity Log</a>
            <a href="subadmin_activity.php">Sub-admin Logs</a>
            <a href="database_search.php">Database Search</a>
        <?php endif; ?>
        <a href="profile.php">My Profile</a>
        <a href="logout.php" style="color:red;">Logout</a>
    </div>
</nav>

<div class="page-wrapper">
    <div class="form-card">
        <div class="form-card-header">
            <h2>&#128100; Manage Administrators</h2>
            <p>Create sub-admin or super-admin accounts and manage removals safely.</p>
        </div>
        <div class="form-card-body">
            <div class="security-note">Only trusted personnel should be granted admin access.</div>

            <?php if ($message):
                $isSuccess = strpos($message, 'successfully') !== false;
                $isError   = !$isSuccess;
                $alertClass = $isSuccess ? 'alert-success' : 'alert-error';
                $icon       = $isSuccess ? '&#9989;' : '&#9888;';
            ?>
            <div class="alert <?= $alertClass ?>" role="alert" aria-live="polite">
                <span><?= $icon ?></span>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
            <?php endif; ?>

            <form id="addAdminForm" method="POST" autocomplete="off">
                <div class="field-group">
                    <label for="username">Username</label>
                    <div class="input-wrap">
                        <span>&#128100;</span>
                        <input type="text" id="username" name="username"
                               placeholder="e.g. john_doe"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               required autofocus autocomplete="username" autocapitalize="none" spellcheck="false">
                    </div>
                    <p class="field-hint">Usernames are stored in lowercase.</p>
                </div>

                <div class="field-group">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <span>&#128274;</span>
                        <input type="password" id="password" name="password"
                               placeholder="Enter a strong password" required minlength="6" autocomplete="new-password">
                        <button type="button" class="toggle-pw" onclick="togglePw(this, 'password')" title="Show password" aria-label="Show password" aria-pressed="false">&#128065;</button>
                    </div>
                    <div class="pw-meter"><div id="pwMeterFill" class="pw-meter-fill"></div></div>
                    <div id="pwMeterLabel" class="pw-meter-label">Password strength: too weak</div>
                    <div id="capsWarning" class="caps-warning" aria-live="polite">Caps Lock appears to be ON.</div>
                </div>

                <div class="field-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="input-wrap">
                        <span>&#128274;</span>
                        <input type="password" id="confirm_password" name="confirm_password"
                               placeholder="Re-enter password" required minlength="6" autocomplete="new-password">
                        <button type="button" class="toggle-pw" onclick="togglePw(this, 'confirm_password')" title="Show password" aria-label="Show password" aria-pressed="false">&#128065;</button>
                    </div>
                    <p id="passwordMatchHint" class="field-hint">Passwords must match before submission.</p>
                </div>

                <div class="field-group">
                    <label for="new_role">Account Role</label>
                    <select id="new_role" name="new_role" class="role-select">
                        <option value="admin" <?= (($_POST['new_role'] ?? 'admin') === 'admin') ? 'selected' : '' ?>>Sub-admin</option>
                        <option value="super_admin" <?= (($_POST['new_role'] ?? '') === 'super_admin') ? 'selected' : '' ?>>Super-admin</option>
                    </select>
                    <p class="field-hint">Use super-admin only for fully trusted top-level administrators.</p>
                </div>

                <button type="submit" class="btn-submit">&#43; Add Administrator</button>
            </form>

            <p class="form-hint">Only super-admins can create and remove administrator accounts.</p>

            <section class="subadmin-list">
                <h3>Existing Sub-admins</h3>
                <?php if (empty($subAdmins)): ?>
                    <div class="subadmin-empty">No sub-admin accounts found.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subAdmins as $admin): ?>
                                <tr>
                                    <td><?= htmlspecialchars($admin['username']) ?></td>
                                    <td><?= htmlspecialchars($admin['created_at']) ?></td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Remove sub-admin <?= htmlspecialchars($admin['username']) ?>?');" style="margin:0;">
                                            <input type="hidden" name="remove_admin_id" value="<?= (int) $admin['id'] ?>">
                                            <button type="submit" class="remove-admin-btn">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <section class="subadmin-list">
                <h3>Existing Super-admins</h3>
                <?php if (empty($superAdmins)): ?>
                    <div class="subadmin-empty">No super-admin accounts found.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($superAdmins as $admin): ?>
                                <?php
                                    $isCurrent = !empty($_SESSION['admin_username']) && strtolower($admin['username']) === $currentUsername;
                                    $canRemoveSuper = (!$isCurrent && $superAdminCount > 1);
                                ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($admin['username']) ?>
                                        <?php if ($isCurrent): ?>
                                            (you)
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($admin['created_at']) ?></td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Remove super-admin <?= htmlspecialchars($admin['username']) ?>?');" style="margin:0;">
                                            <input type="hidden" name="remove_admin_id" value="<?= (int) $admin['id'] ?>">
                                            <input type="hidden" name="remove_role" value="super_admin">
                                            <button type="submit" class="remove-super-btn" <?= $canRemoveSuper ? '' : 'disabled' ?>>Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="field-hint">Protection rules: you cannot remove your own account or the last remaining super-admin.</p>
                <?php endif; ?>
            </section>
        </div>
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

const form = document.getElementById('addAdminForm');
const passwordInput = document.getElementById('password');
const confirmPasswordInput = document.getElementById('confirm_password');
const meterFill = document.getElementById('pwMeterFill');
const meterLabel = document.getElementById('pwMeterLabel');
const matchHint = document.getElementById('passwordMatchHint');
const capsWarning = document.getElementById('capsWarning');

function updateStrength() {
    const value = passwordInput.value;
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
    const a = passwordInput.value;
    const b = confirmPasswordInput.value;

    if (!b) {
        matchHint.textContent = 'Passwords must match before submission.';
        matchHint.style.color = 'dimgray';
        return;
    }

    if (a === b) {
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

passwordInput.addEventListener('input', function() {
    updateStrength();
    updateMatchHint();
});
confirmPasswordInput.addEventListener('input', updateMatchHint);
passwordInput.addEventListener('keydown', updateCapsState);
passwordInput.addEventListener('keyup', updateCapsState);
passwordInput.addEventListener('blur', function() {
    capsWarning.classList.remove('show');
});

form.addEventListener('submit', function(event) {
    if (passwordInput.value !== confirmPasswordInput.value) {
        event.preventDefault();
        matchHint.textContent = 'Passwords do not match yet.';
        matchHint.style.color = 'maroon';
        confirmPasswordInput.focus();
        return;
    }

    const submitBtn = form.querySelector('.btn-submit');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Creating account...';
});

updateStrength();
</script>
</body>
</html>