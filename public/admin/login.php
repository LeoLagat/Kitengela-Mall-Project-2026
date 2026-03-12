<?php
// admin login restored
session_start();

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = strtolower($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    require_once(__DIR__ . '/../../backend/app/config/database.php');
    $db = new DatabaseConnection();
    $pdo = $db->pdo;

    $stmt = $pdo->prepare("SELECT * FROM administrators WHERE LOWER(username)=?");
    $stmt->execute([$user]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    // verify hashed password
    if ($admin && password_verify($pass, $admin['password'])) {
        // successful login
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id']       = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];

        require_once(__DIR__ . '/../../backend/app/services/AdminAudit.php');
        AdminAudit::log($pdo, $admin['username'], 'login');

        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Login</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .login-card { max-width:400px; margin:120px auto; padding:30px; background:white; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,0.1); }
        .login-card input, .login-card button { width:100%; padding:12px; margin:8px 0; }
        .login-card button { background:darkgreen; color:white; border:none; cursor:pointer; }
        .login-card button:hover { background:green; }
        .error { color:red; text-align:center; }
        .btn-home { display:inline-block; margin-top:12px; padding:6px 12px; background:darkslategray; color:white; text-decoration:none; border-radius:4px; }
        .btn-home:hover { background:slategray; }
    </style>
</head>
<body>
<div class="login-card">
    <h2>Administrator Login</h2>
    <?php if ($error): ?><div class="error"><?=htmlspecialchars($error)?></div><?php endif; ?>
    <form method="POST">
        <input type="text" name="username" placeholder="Username" required autofocus>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Log In</button>
    </form>
    <a href="../index.php" class="btn-home">&larr; Home</a>
</div>
</body>
</html>