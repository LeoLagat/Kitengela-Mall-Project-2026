<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once(__DIR__ . '/../../backend/app/config/database.php');
$db = new DatabaseConnection();
$pdo = $db->pdo;

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $stmt2 = $pdo->prepare("INSERT INTO administrators (username, password) VALUES (?, ?)");
        $stmt2->execute([$uname, $hash]);
        $message = "User '$uname' added successfully.";
        }
    } else {
        $message = "Please provide both username and password.";
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