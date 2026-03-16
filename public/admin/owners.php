<?php

session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$message = null;
$messageType = 'success';

require_once(__DIR__ . '/../../backend/app/config/database.php');
$db = new DatabaseConnection();
$pdo = $db->pdo;

require_once(__DIR__ . '/../../backend/app/services/AdminAudit.php');
if (!empty($_SESSION['admin_username'])) {
    AdminAudit::log($pdo, $_SESSION['admin_username'], 'visited owners page');
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['plate'])) {
    $plate = strtoupper(trim($_POST['plate']));
    if ($plate !== '') {
        try {
            $stmt = $pdo->prepare("INSERT INTO owner_accounts (plate_number, owner_name, invoice_monthly) VALUES (?, ?, ?)");
            $stmt->execute([$plate, $_POST['name'] ?? null, isset($_POST['invoice']) ? 1 : 0]);
            $message = "Added owner $plate.";
        } catch (Exception $e) {
            $messageType = 'error';
            $message = "Error: " . $e->getMessage();
        }
    } else {
        $messageType = 'error';
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

$activeCount = 0;
$expiredCount = 0;
$totalDue = 0;
foreach ($owners as $owner) {
    if (($owner['status'] ?? '') === 'Active') {
        $activeCount++;
    } else {
        $expiredCount++;
    }

    $totalDue += isset($owner['total_due']) ? (float) $owner['total_due'] : 0;
}

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
    <style>
        .owners-page {
            width: 95%;
            max-width: 1150px;
            margin: 50px auto 90px auto;
            display: grid;
            gap: 22px;
        }

        .owners-hero {
            background: white;
            border: 1px solid lightgray;
            border-left: 8px solid darkgreen;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 10px 24px gainsboro;
        }

        .owners-hero h2 {
            margin: 0;
            font-size: 34px;
            color: forestgreen;
            line-height: 1.2;
        }

        .owners-hero p {
            margin: 10px 0 0 0;
            color: dimgray;
            font-size: 17px;
        }

        .owners-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(180px, 1fr));
            gap: 12px;
        }

        .summary-card {
            background: white;
            border: 1px solid lightgray;
            border-radius: 12px;
            padding: 14px 16px;
            box-shadow: 0 6px 16px gainsboro;
        }

        .summary-title {
            display: block;
            color: dimgray;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
        }

        .summary-value {
            display: block;
            margin-top: 2px;
            color: darkgreen;
            font-size: 28px;
            font-weight: 700;
        }

        .owners-grid {
            display: grid;
            grid-template-columns: minmax(300px, 380px) 1fr;
            gap: 20px;
            align-items: start;
        }

        .owners-card {
            background: white;
            border: 1px solid lightgray;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 8px 20px gainsboro;
        }

        .owners-card h3 {
            margin-top: 0;
            margin-bottom: 14px;
            font-size: 24px;
            color: forestgreen;
        }

        .owner-form {
            margin: 0;
            width: 100%;
            max-width: none;
            display: grid;
            gap: 12px;
        }

        .form-group {
            display: grid;
            gap: 6px;
        }

        .form-group label {
            color: darkslategray;
            font-weight: 600;
        }

        .owner-form input[type="text"] {
            width: 100%;
            padding: 12px 14px;
            border-radius: 8px;
            border: 2px solid lightgray;
            font-size: 16px;
            box-sizing: border-box;
            text-align: left;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-top: 2px;
        }

        .checkbox-group label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: darkslategray;
            font-weight: 600;
        }

        .form-hint {
            margin: 0;
            color: dimgray;
            font-size: 13px;
        }

        .status-message {
            margin: 0;
        }

        .table-wrap {
            overflow-x: auto;
        }

        .owner-table {
            margin-top: 0;
        }

        .owner-table td,
        .owner-table th {
            min-width: 120px;
        }

        .status-chip {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-chip.active {
            background: honeydew;
            color: darkgreen;
            border: 1px solid palegreen;
        }

        .status-chip.expired {
            background: mistyrose;
            color: darkred;
            border: 1px solid lightcoral;
        }

        .empty-state {
            background: floralwhite;
            border: 1px dashed burlywood;
            color: saddlebrown;
            border-radius: 10px;
            padding: 16px;
            margin-top: 12px;
            font-weight: 600;
        }

        @media (max-width: 980px) {
            .owners-grid {
                grid-template-columns: 1fr;
            }

            .owners-summary {
                grid-template-columns: 1fr;
            }

            .owners-page {
                margin-top: 30px;
            }

            .owners-hero h2 {
                font-size: 28px;
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
        <a href="staff.php">Staff Parking</a>
<?php if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
        <a href="add_user.php">Add User</a>
        <a href="activity.php">Activity Log</a>
        <a href="subadmin_activity.php">Sub-admin Logs</a>
<?php endif; ?>
        <a href="logout.php" style="color:red;">Logout</a>
    </div>
</nav>

<main class="owners-page">
    <section class="owners-hero">
        <h2>Owner Vehicles (Invoiced)</h2>
        <p>Manage recurring business-owner parking accounts, check current status, and monitor payment exposure.</p>
    </section>

    <section class="owners-summary">
        <article class="summary-card">
            <span class="summary-title">Active Accounts</span>
            <span class="summary-value"><?= $activeCount ?></span>
        </article>
        <article class="summary-card">
            <span class="summary-title">Expired Accounts</span>
            <span class="summary-value"><?= $expiredCount ?></span>
        </article>
        <article class="summary-card">
            <span class="summary-title">Total Due</span>
            <span class="summary-value">KES <?= number_format($totalDue, 2) ?></span>
        </article>
    </section>

    <?php if ($message !== null): ?>
        <div class="<?= $messageType === 'error' ? 'error' : 'success' ?> status-message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <section class="owners-grid">
        <article class="owners-card">
            <h3>Add Owner Account</h3>
            <form method="POST" class="owner-form">
                <div class="form-group">
                    <label for="plate">Plate Number</label>
                    <input id="plate" name="plate" required class="auto-uppercase" autocomplete="off" placeholder="KDA 123A">
                </div>

                <div class="form-group">
                    <label for="name">Owner Name (optional)</label>
                    <input id="name" name="name" class="auto-uppercase" autocomplete="off" placeholder="MALL TENANT LTD">
                </div>

                <div class="form-group checkbox-group">
                    <label>
                        <input type="checkbox" name="invoice" checked>
                        Invoice monthly
                    </label>
                </div>

                <p class="form-hint">Plate and owner text is auto-converted to uppercase for consistency.</p>
                <button type="submit">Add Owner</button>
            </form>
        </article>

        <article class="owners-card">
            <h3>Current Owner List</h3>
            <?php if (empty($owners)): ?>
                <div class="empty-state">No owner vehicles have been added yet.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="owner-table">
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
                                    <td><?= htmlspecialchars($row['owner_name'] ?? 'N/A') ?></td>
                                    <td><?= $row['invoice_monthly'] ? 'Yes' : 'No' ?></td>
                                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                                    <td>
                                        <span class="status-chip <?= ($row['status'] === 'Active') ? 'active' : 'expired' ?>">
                                            <?= htmlspecialchars($row['status']) ?>
                                        </span>
                                    </td>
                                    <td>KES <?= isset($row['total_due']) ? number_format($row['total_due'], 2) : '0.00' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>
    </section>
</main>
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