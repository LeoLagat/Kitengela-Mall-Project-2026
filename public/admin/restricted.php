<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once(__DIR__ . '/../../backend/app/config/database.php');
$db = new DatabaseConnection();
$pdo = $db->pdo;

require_once(__DIR__ . '/../../backend/app/services/AdminAudit.php');
if (!empty($_SESSION['admin_username'])) {
    AdminAudit::log($pdo, $_SESSION['admin_username'], 'visited restricted page');
}

// make sure migration has run for restricted list (in case db.php was not loaded earlier)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS restricted_vehicles (\
        id INT AUTO_INCREMENT PRIMARY KEY,\
        plate_number VARCHAR(20) UNIQUE NOT NULL,\
        reason VARCHAR(255) DEFAULT NULL,\
        added_at DATETIME DEFAULT CURRENT_TIMESTAMP\
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // ignore if migration fails
}


// handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['plate'])) {
    $plate = strtoupper(trim($_POST['plate']));
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO restricted_vehicles (plate_number, reason) VALUES (?, ?)");
        $stmt->execute([$plate, $_POST['reason'] ?? null]);
        $message = "Added $plate to restricted list.";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// fetch restricted list (wrap in try/catch in case table still missing)
$rows = [];
try {
    $stmt = $pdo->query("SELECT plate_number, reason, added_at FROM restricted_vehicles ORDER BY plate_number");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // if table absence causes error, show empty list and message later
    $message = "Warning: restricted_vehicles table not found yet.";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Restricted Vehicles</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav>
    <div class="logo">Admin Panel</div>
    <div class="links">
        <a href="dashboard.php">Dashboard</a>
        <a href="entry.php">Entry</a>
        <a href="exit.php">Exit</a>
        <a href="staff.php">Staff Parking</a>
        <a href="owners.php">Owner Vehicles</a>
        <a href="restricted.php" class="active">Restricted List</a>
        <a href="add_user.php">Add User</a>
        <a href="activity.php">Activity Log</a>
        <a href="logout.php" style="color:#f00;">Logout</a>
    </div>
</nav>

<div class="container" style="display: flex; flex-direction: column; align-items: flex-start;">
    <h2 style="margin-bottom: 0.5em; width: 100%;">Restricted / Banned Vehicles</h2>
    <?php if ($message): ?>
        <div class="error">
            <?=htmlspecialchars($message)?></div>
    <?php endif; ?>
    <form method="POST" style="margin-bottom: 2em; width: 100%; max-width: 350px;">
        <label>Plate Number: <input name="plate" required class="auto-uppercase" autocomplete="off"></label><br>
        <label>Reason (optional): <input name="reason" class="auto-uppercase" autocomplete="off"></label><br>
        <button type="submit">Ban</button>
    </form>
    <h3 style="margin-top: 2em; width: 100%;">Current Restricted List</h3>
    <table style="width: 100%;">
        <thead><tr><th>Plate</th><th>Reason</th><th>Added</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?=htmlspecialchars($row['plate_number'])?></td>
                <td><?=htmlspecialchars($row['reason'])?></td>
                <td><?=htmlspecialchars($row['added_at'])?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
// Auto-uppercase all relevant text inputs
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input.auto-uppercase').forEach(function(input) {
        input.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    });
});
</script>
</body>
</html>