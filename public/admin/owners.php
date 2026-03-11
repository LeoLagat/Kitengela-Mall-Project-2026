<?php

session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$message = null;

require_once(__DIR__ . '/../../backend/app/config/database.php');
$db = new DatabaseConnection();
$pdo = $db->pdo;


if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['plate'])) {
    $plate = strtoupper(trim($_POST['plate']));
    if ($plate !== '') {
        try {
            $stmt = $pdo->prepare("INSERT INTO owner_accounts (plate_number, owner_name, invoice_monthly) VALUES (?, ?, ?)");
            $stmt->execute([$plate, $_POST['name'] ?? null, isset($_POST['invoice']) ? 1 : 0]);
            $message = "Added owner $plate.";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    } else {
        $message = "Error: Plate number cannot be empty.";
    }
}

// fetch current owner list, only show non-expired
$currentDate = date('Y-m-d');

// Enhanced status logic:
// 1. If due_period (last exit + 1 month) is in the future, status is Active
// 2. If due_period is past, but total_due <= 0, status is Active
// 3. If due_period is past and total_due > 0, status is Expired

// New status logic:
// 1. Status is Active for 1 month after created_at
// 2. After 1 month, if total_due <= 0, status is Active
// 3. After 1 month, if total_due > 0, status is Expired
$stmt = $pdo->prepare("
SELECT a.plate_number, a.owner_name, a.invoice_monthly, a.created_at,
    DATE_ADD(a.created_at, INTERVAL 1 MONTH) AS active_until,
    f.total_due,
    CASE
        WHEN DATE_ADD(a.created_at, INTERVAL 1 MONTH) >= ? THEN 'Active'
        WHEN DATE_ADD(a.created_at, INTERVAL 1 MONTH) < ? AND (f.total_due IS NULL OR f.total_due <= 0) THEN 'Active'
        ELSE 'Expired'
    END AS status
FROM owner_accounts a
LEFT JOIN owner_vehicle_fees f ON a.plate_number = f.plate_number
ORDER BY a.plate_number");
$stmt->execute([$currentDate, $currentDate]);
$owners = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Apply a standard 30% discount to all owner_vehicle_fees using nominal_fee and update total_due
$update = $pdo->prepare("UPDATE owner_vehicle_fees SET discount_given = nominal_fee * 0.3, total_due = nominal_fee - (nominal_fee * 0.3)");
$update->execute();

// Function to update logs and accumulated nominal_fee when owner exits
function updateOwnerExit($pdo, $plate_number, $exit_time, $nominal_fee) {
    // Update vehicle_logs with exit and fee
    $log = $pdo->prepare("UPDATE vehicle_logs SET exit_time = ?, nominal_fee = ? WHERE plate_number = ? AND exit_time IS NULL");
    $log->execute([$exit_time, $nominal_fee, $plate_number]);
    // Add nominal_fee to accumulated fee in owner_vehicle_fees
    $acc = $pdo->prepare("UPDATE owner_vehicle_fees SET nominal_fee = nominal_fee + ? WHERE plate_number = ?");
    $acc->execute([$nominal_fee, $plate_number]);
    // Set due_period to 1 month after this exit
    $due = $pdo->prepare("UPDATE owner_vehicle_fees SET due_period = DATE_ADD(?, INTERVAL 1 MONTH) WHERE plate_number = ?");
    $due->execute([$exit_time, $plate_number]);
}

// TEMP: Reset nominal_fee and accumulate from all exited logs
$plates = ['KCW546H', 'KJU685', 'KMB999R'];
foreach ($plates as $plate) {
    // Reset nominal_fee
    $reset = $pdo->prepare("UPDATE owner_vehicle_fees SET nominal_fee = 0 WHERE plate_number = ?");
    $reset->execute([$plate]);
    // Sum all exited nominal fees
    $sum = $pdo->prepare("SELECT SUM(nominal_fee) FROM vehicle_logs WHERE plate_number = ? AND exit_time IS NOT NULL");
    $sum->execute([$plate]);
    $total = $sum->fetchColumn();
    if ($total !== false) {
        $acc = $pdo->prepare("UPDATE owner_vehicle_fees SET nominal_fee = ? WHERE plate_number = ?");
        $acc->execute([$total, $plate]);
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Business Owner Vehicles</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav>
    <div class="logo">Admin Panel</div>
    <div class="links">
        <a href="dashboard.php">Dashboard</a>
        <a href="restricted.php">Restricted List</a>
        <a href="staff.php">Staff Parking</a>
        <a href="add_user.php">Add User</a>
        <a href="logout.php" style="color:#f00;">Logout</a>
    </div>
</nav>

<div class="container" style="display: flex; flex-direction: column; align-items: flex-start;">
    <h2 style="margin-bottom: 0.5em; width: 100%;">Owner Vehicles (Invoiced)</h2>

    <?php if (isset($message)): ?>
        <div class="success">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="owner-form" style="margin-bottom: 2em; width: 100%; max-width: 350px;">
        <div class="form-group">
            <label for="plate">Plate Number:</label>
            <input id="plate" name="plate" required class="auto-uppercase" autocomplete="off">
        </div>

        <div class="form-group">
            <label for="name">Owner Name (optional):</label>
            <input id="name" name="name" class="auto-uppercase" autocomplete="off">
        </div>

        <div class="form-group checkbox-group">
            <label>
                <input type="checkbox" name="invoice" checked>
                Invoice monthly
            </label>
        </div>

        <button type="submit">Add</button>
    </form>

    <h3 style="margin-top: 2em; width: 100%;">Current Owner List</h3>
    <table class="owner-table" style="width: 100%;">
        <thead>
            <tr>
                <th>Plate</th>
                <th>Name</th>
                <th>Invoiced?</th>
                <th>Added</th>
                <th>Status</th>
                <th>Total Due</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($owners as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['plate_number']) ?></td>
                    <td><?= htmlspecialchars($row['owner_name']) ?></td>
                    <td><?= $row['invoice_monthly'] ? 'Yes' : 'No' ?></td>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                    <td><?= htmlspecialchars($row['status']) ?></td>
                    <td><?= isset($row['total_due']) ? number_format($row['total_due'], 2) : '0.00' ?></td>
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