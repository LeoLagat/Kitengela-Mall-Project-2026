<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once(__DIR__ . '/../../backend/app/config/database.php');
$db = new DatabaseConnection();
$pdo = $db->pdo;

// audit this page visit
require_once(__DIR__ . '/../../backend/app/services/AdminAudit.php');
if (isset($_SESSION['admin_id'])) {
    AdminAudit::log($pdo, $_SESSION['admin_id'], 'visited staff page');
}

// handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['plate'])) {
    $plate = strtoupper(trim($_POST['plate']));
    try {
        $stmt = $pdo->prepare("INSERT INTO staff_vehicles (plate_number, employee_name) VALUES (?, ?)");
        $stmt->execute([$plate, $_POST['name'] ?? null]);
        $message = "Added $plate to staff list.";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// fetch current staff list
$stmt = $pdo->query("SELECT plate_number, employee_name, created_at FROM staff_vehicles ORDER BY plate_number");

$staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Staff Vehicles</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav>
    <div class="logo">Admin Panel</div>
    <div class="links">
        <a href="dashboard.php">Dashboard</a>
        <a href="restricted.php">Restricted List</a>
        <a href="add_user.php">Add User</a>
        <a href="activity.php">Activity Log</a>
        <a href="logout.php" style="color:#f00;">Logout</a>
    </div>
</nav>

<div class="container" style="display: flex; flex-direction: column; align-items: flex-start;">
    <h2 style="margin-bottom: 0.5em; width: 100%;">Staff / Employee Vehicles (Free Parking)</h2>
    <?php if ($message): ?>
        <div class="success"><?=htmlspecialchars($message)?></div>
    <?php endif; ?>
    <form method="POST" style="margin-bottom: 2em; width: 100%; max-width: 350px;">
        <label>Plate Number: <input name="plate" required class="auto-uppercase"></label><br>
        <label>Name (optional): <input name="name" class="auto-uppercase"></label><br>
        <button type="submit">Add</button>
    </form>

    <h3 style="margin-top: 2em; width: 100%;">Current List</h3>
    <table style="width: 100%;">
        <thead><tr><th>Plate</th><th>Name</th><th>Added</th></tr></thead>
        <tbody>
        <?php foreach ($staff as $row): ?>
            <tr>
    </body>
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
                <td><?=htmlspecialchars($row['plate_number'])?></td>
                <td><?=htmlspecialchars($row['employee_name'])?></td>
                <td><?=htmlspecialchars($row['created_at'])?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>