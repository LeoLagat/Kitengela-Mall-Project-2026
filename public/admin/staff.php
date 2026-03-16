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
if (!empty($_SESSION['admin_username'])) {
    AdminAudit::log($pdo, $_SESSION['admin_username'], 'visited staff page');
}

// handle form submission
$message = '';
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['plate'])) {
    $plate = strtoupper(trim($_POST['plate']));
    $name = trim($_POST['name'] ?? '');
    try {
        $stmt = $pdo->prepare("INSERT INTO staff_vehicles (plate_number, employee_name) VALUES (?, ?)");
        $stmt->execute([$plate, $name === '' ? null : $name]);
        $message = "Added $plate to staff list.";
    } catch (Exception $e) {
        $messageType = 'error';
        $message = "Error: " . $e->getMessage();
    }
}

// fetch current staff list
$stmt = $pdo->query("SELECT plate_number, employee_name, created_at FROM staff_vehicles ORDER BY plate_number");

$staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalStaff = count($staff);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Staff Vehicles</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .staff-page {
            width: 95%;
            max-width: 1120px;
            margin: 50px auto 90px auto;
            display: grid;
            gap: 22px;
        }

        .staff-hero {
            background: white;
            border: 1px solid lightgray;
            border-left: 8px solid darkgreen;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 10px 24px gainsboro;
        }

        .staff-hero h2 {
            margin: 0;
            font-size: 34px;
            color: forestgreen;
            line-height: 1.2;
        }

        .staff-hero p {
            margin: 10px 0 0 0;
            color: dimgray;
            font-size: 17px;
        }

        .staff-grid {
            display: grid;
            grid-template-columns: minmax(300px, 380px) 1fr;
            gap: 20px;
            align-items: start;
        }

        .staff-card {
            background: white;
            border: 1px solid lightgray;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 8px 20px gainsboro;
        }

        .staff-card h3 {
            margin-top: 0;
            margin-bottom: 14px;
            font-size: 24px;
            color: forestgreen;
        }

        .staff-count {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: mintcream;
            color: darkgreen;
            border: 1px solid palegreen;
            border-radius: 999px;
            padding: 6px 12px;
            font-weight: 700;
            margin-top: 2px;
            margin-bottom: 12px;
        }

        .staff-form {
            display: grid;
            gap: 12px;
        }

        .staff-form label {
            display: grid;
            gap: 6px;
            color: darkslategray;
            font-weight: 600;
        }

        .staff-form input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 8px;
            border: 2px solid lightgray;
            font-size: 16px;
            box-sizing: border-box;
            text-align: left;
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

        .table-wrap table {
            margin-top: 0;
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
            .staff-grid {
                grid-template-columns: 1fr;
            }

            .staff-page {
                margin-top: 30px;
            }

            .staff-hero h2 {
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
<?php if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
        <a href="add_user.php">Add User</a>
        <a href="activity.php">Activity Log</a>
        <a href="subadmin_activity.php">Sub-admin Logs</a>
<?php endif; ?>
        <a href="logout.php" style="color:red;">Logout</a>
    </div>
</nav>

<main class="staff-page">
    <section class="staff-hero">
        <h2>Staff / Employee Vehicles</h2>
        <p>Maintain a trusted list of employee vehicles eligible for free parking and quick gate validation.</p>
    </section>

    <?php if ($message): ?>
        <div class="<?= $messageType === 'error' ? 'error' : 'success' ?> status-message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <section class="staff-grid">
        <article class="staff-card">
            <h3>Add Staff Vehicle</h3>
            <form method="POST" class="staff-form">
                <label>
                    Plate Number
                    <input name="plate" required class="auto-uppercase" autocomplete="off" placeholder="KDA 123A">
                </label>
                <label>
                    Employee Name (optional)
                    <input name="name" class="auto-uppercase" autocomplete="off" placeholder="JANE DOE">
                </label>
                <p class="form-hint">Plate and name are auto-converted to uppercase for consistency.</p>
                <button type="submit">Add to Staff List</button>
            </form>
        </article>

        <article class="staff-card">
            <h3>Current Staff List</h3>
            <div class="staff-count">
                <span>Total:</span>
                <span><?= $totalStaff ?> vehicle<?= $totalStaff === 1 ? '' : 's' ?></span>
            </div>

            <?php if (empty($staff)): ?>
                <div class="empty-state">No staff vehicles found yet. Add a vehicle to begin.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Plate</th>
                                <th>Name</th>
                                <th>Added</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staff as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['plate_number']) ?></td>
                                    <td><?= htmlspecialchars($row['employee_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($row['created_at']) ?></td>
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