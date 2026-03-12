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
// only super_admins may add other users
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_SESSION['admin_role'] !== 'super_admin') {
        $message = "Only the main admin can create new users.";
    } else {
        $uname = strtolower(trim($_POST['username'] ?? ''));
        $pwd = $_POST['password'] ?? '';
        if ($uname && $pwd) {
            // insert new administrator, avoid duplicates
            $stmt = $pdo->prepare("SELECT id FROM administrators WHERE LOWER(username)=?");
            $stmt->execute([$uname]);
            if ($stmt->fetch()) {
                $message = "A user with that username already exists.";
            } else {
                $hash = password_hash($pwd, PASSWORD_DEFAULT);
                $stmt2 = $pdo->prepare("INSERT INTO administrators (username, password, role) VALUES (?, ?, 'admin')");
                $stmt2->execute([$uname, $hash]);
                $message = "Sub‑admin '$uname' added successfully.";
            }
        } else {
            $message = "Please provide both username and password.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Administrator</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .page-wrapper {
            min-height: calc(100vh - 60px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
        }
        .form-card {
            width: 100%;
            max-width: 440px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 6px 28px rgba(0,0,0,0.10);
            overflow: hidden;
        }
        .form-card-header {
            background: linear-gradient(90deg, #0a5a0a 0%, #074e07 100%);
            padding: 28px 32px 22px;
            color: #fff;
        }
        .form-card-header h2 {
            margin: 0 0 4px;
            font-size: 22px;
            color: #fff;
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
        .alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info    { background: #f0f0f0; color: #333;    border: 1px solid #ddd; }
        .field-group {
            margin-bottom: 18px;
        }
        .field-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #444;
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
            color: #888;
            pointer-events: none;
        }
        .field-group input {
            width: 100%;
            padding: 10px 12px 10px 36px;
            border: 1px solid #d0d5dd;
            border-radius: 7px;
            font-size: 14px;
            color: #333;
            background: #fafafa;
            box-sizing: border-box;
            transition: border-color .2s, box-shadow .2s;
        }
        .field-group input:focus {
            outline: none;
            border-color: #2d862d;
            box-shadow: 0 0 0 3px rgba(45,134,45,0.15);
            background: #fff;
        }
        .toggle-pw {
            position: absolute;
            right: 11px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 15px;
            color: #888;
            padding: 0;
        }
        .btn-submit {
            width: 100%;
            padding: 11px;
            background: linear-gradient(90deg, #0a5a0a 0%, #1a8a1a 100%);
            color: #fff;
            border: none;
            border-radius: 7px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            letter-spacing: .3px;
            transition: opacity .2s;
            margin-top: 4px;
        }
        .btn-submit:hover { opacity: .88; }
        .form-hint {
            font-size: 12px;
            color: #888;
            margin-top: 16px;
            text-align: center;
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
        <?php endif; ?>
        <a href="logout.php" style="color:#ffdddd;">Logout</a>
    </div>
</nav>

<div class="page-wrapper">
    <div class="form-card">
        <div class="form-card-header">
            <h2>&#128100; Add Sub-Administrator</h2>
            <p>New accounts are created with the <strong>sub-admin</strong> role by default.</p>
        </div>
        <div class="form-card-body">
            <?php if ($message):
                $isSuccess = strpos($message, 'successfully') !== false;
                $isError   = !$isSuccess;
                $alertClass = $isSuccess ? 'alert-success' : 'alert-error';
                $icon       = $isSuccess ? '&#9989;' : '&#9888;';
            ?>
            <div class="alert <?= $alertClass ?>">
                <span><?= $icon ?></span>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="field-group">
                    <label for="username">Username</label>
                    <div class="input-wrap">
                        <span>&#128100;</span>
                        <input type="text" id="username" name="username"
                               placeholder="e.g. john_doe"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               required autofocus>
                    </div>
                </div>

                <div class="field-group">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <span>&#128274;</span>
                        <input type="password" id="password" name="password"
                               placeholder="Enter a strong password" required>
                        <button type="button" class="toggle-pw" onclick="togglePw()" title="Show/hide password">&#128065;</button>
                    </div>
                </div>

                <button type="submit" class="btn-submit">&#43; Add Administrator</button>
            </form>

            <p class="form-hint">Only super-admins can create new accounts. New users will be assigned the <em>sub-admin</em> role.</p>
        </div>
    </div>
</div>

<script>
function togglePw() {
    const input = document.getElementById('password');
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>