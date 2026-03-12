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
    <title>Add Administrator</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { font-family: Arial, sans-serif; }
        .form-card { max-width: 400px; margin: 60px auto; padding: 20px; background: white; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
        .form-card input { width:100%; padding:10px; margin:8px 0; border:1px solid #ccc; border-radius:4px; }
        .form-card button { width:100%; padding:10px; background: darkgreen; color:white; border:none; border-radius:4px; cursor:pointer; }
        .form-card button:hover { background: green; }
        .message { margin-bottom:15px; padding:10px; background:#f0f0f0; border-radius:4px; }
    </style>
</head>
<body>
<nav>
    <a href="dashboard.php">Dashboard</a> |
    <a href="restricted.php">Restricted List</a> |
<?php if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
    <a href="activity.php">Activity Log</a> |
    <a href="subadmin_activity.php">Sub-admin Logs</a> |
<?php endif; ?>
    <a href="logout.php">Logout</a>
</nav>

<div class="form-card">
    <h2>Add Administrator</h2>
    <?php if ($message): ?><div class="message"><?=htmlspecialchars($message)?></div><?php endif; ?>
    <form method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Add User</button>
    </form>
</div>
</body>
</html>