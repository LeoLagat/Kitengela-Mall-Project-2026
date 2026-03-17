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
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['plate'])) {
        $plate = strtoupper(trim($_POST['plate']));
        $reason = trim($_POST['reason'] ?? '');
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO restricted_vehicles (plate_number, reason) VALUES (?, ?)");
            $stmt->execute([$plate, $reason === '' ? null : $reason]);
            if ($stmt->rowCount() > 0) {
                $message = "Added $plate to restricted list.";
            } else {
                $messageType = 'error';
                $message = "$plate is already in the restricted list.";
            }
        } catch (Exception $e) {
            $messageType = 'error';
            $message = "Error: " . $e->getMessage();
        }
    }
}

// fetch restricted list (wrap in try/catch in case table still missing)
$rows = [];
$deletedRows = [];
try {
    $stmt = $pdo->query("SELECT plate_number, reason, added_at FROM restricted_vehicles WHERE deleted_at IS NULL ORDER BY plate_number");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // if table absence causes error, show empty list and message later
    $message = "Warning: restricted_vehicles table not found yet.";
}

// Fetch deleted restricted vehicles (recycle bin) - only for super_admin
if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin') {
    try {
        $stmt = $pdo->query("SELECT plate_number, reason, added_at, deleted_at FROM restricted_vehicles WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
        $deletedRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // ignore if query fails
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Restricted Vehicles</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .restricted-page {
            width: 95%;
            max-width: 1100px;
            margin: 50px auto 90px auto;
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
        }

        .restricted-hero {
            background: white;
            border: 1px solid lightgray;
            border-left: 8px solid darkgreen;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 10px 24px gainsboro;
        }

        .restricted-hero h2 {
            margin: 0;
            color: forestgreen;
            font-size: 34px;
            line-height: 1.2;
        }

        .restricted-hero p {
            margin-top: 10px;
            margin-bottom: 0;
            color: dimgray;
            font-size: 17px;
        }

        .restricted-grid {
            display: grid;
            grid-template-columns: minmax(300px, 380px) 1fr;
            gap: 20px;
            align-items: start;
        }

        .restricted-card {
            background: white;
            border: 1px solid lightgray;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 8px 20px gainsboro;
        }

        .restricted-card h3 {
            margin-top: 0;
            margin-bottom: 14px;
            font-size: 24px;
            color: forestgreen;
        }

        .restricted-form {
            display: grid;
            gap: 12px;
        }

        .restricted-form label {
            display: grid;
            gap: 6px;
            color: darkslategray;
            font-weight: 600;
        }

        .restricted-form input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 8px;
            border: 2px solid lightgray;
            font-size: 16px;
            box-sizing: border-box;
        }

        .restricted-form small {
            color: dimgray;
        }

        .restricted-count {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: mintcream;
            color: darkgreen;
            border: 1px solid palegreen;
            border-radius: 999px;
            padding: 6px 12px;
            font-weight: 700;
            margin-top: 4px;
            margin-bottom: 10px;
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

        .table-wrap td:last-child,
        .table-wrap th:last-child {
            width: 130px;
            text-align: center;
        }

        .btn-remove {
            width: auto;
            background-color: firebrick;
            box-shadow: 0 4px 0 darkred;
            padding: 8px 12px;
            font-size: 14px;
        }

        .btn-remove:hover {
            background-color: red;
            box-shadow: 0 6px 0 firebrick;
        }

        .btn-remove:active {
            box-shadow: 0 2px 0 firebrick;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            width: auto;
            background: none;
            box-shadow: none;
        }

        .restore-btn {
            background: seagreen;
            color: white;
        }

        .restore-btn:hover {
            background: darkgreen;
        }

        .permanent-delete-btn {
            background: darkred;
            color: white;
        }

        .permanent-delete-btn:hover {
            background: maroon;
        }

        .recycle-bin-section {
            margin-top: 40px;
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

        @media (max-width: 900px) {
            .restricted-grid {
                grid-template-columns: 1fr;
            }

            .restricted-page {
                margin-top: 30px;
            }

            .restricted-hero h2 {
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
        <a href="staff.php">Staff Parking</a>
        <a href="owners.php">Owner Vehicles</a>
        <a href="restricted.php" class="active">Restricted List</a>
        <?php if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
        <a href="add_user.php">Add User</a>
        <a href="activity.php">Activity Log</a>
        <a href="subadmin_activity.php">Sub-admin Logs</a>
<?php endif; ?>
        <a href="logout.php" style="color:red;">Logout</a>
    </div>
</nav>

<main class="restricted-page">
    <section class="restricted-hero">
        <h2>Restricted / Banned Vehicles</h2>
        <p>Block flagged vehicles from entering parking and keep your watchlist up to date from one place.</p>
    </section>

    <?php if ($message): ?>
        <div class="<?= $messageType === 'error' ? 'error' : 'success' ?> status-message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <section class="restricted-grid">
        <article class="restricted-card">
            <h3>Add Vehicle</h3>
            <form method="POST" class="restricted-form">
                <label>
                    Plate Number
                    <input name="plate" required class="auto-uppercase" autocomplete="off" placeholder="KDA 123A">
                </label>
                <label>
                    Reason (optional)
                    <input name="reason" class="auto-uppercase" autocomplete="off" placeholder="UNPAID FEES / SECURITY ALERT">
                </label>
                <small>Plate and reason are auto-converted to uppercase for consistency.</small>
                <button type="submit">Add to Restricted List</button>
            </form>
        </article>

        <article class="restricted-card">
            <h3>Current Restricted List</h3>
            <div class="restricted-count">
                <span>Total:</span>
                <span><?= count($rows) ?> vehicle<?= count($rows) === 1 ? '' : 's' ?></span>
            </div>

            <?php if (empty($rows)): ?>
                <div class="empty-state">No restricted vehicles yet. Add a plate to start enforcing restrictions.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Plate</th>
                                <th>Reason</th>
                                <th>Added</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['plate_number']) ?></td>
                                    <td><?= htmlspecialchars($row['reason'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($row['added_at']) ?></td>
                                    <td>
                                        <?php if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
                                            <button class="action-btn btn-remove" onclick="deleteRestrictedVehicle('<?= htmlspecialchars($row['plate_number']) ?>')">Remove</button>
                                        <?php else: ?>
                                            <form method="POST" onsubmit="return confirm('Remove <?= htmlspecialchars($row['plate_number']) ?> from restricted list?');">
                                                <input type="hidden" name="remove_plate" value="<?= htmlspecialchars($row['plate_number']) ?>">
                                                <button type="submit" class="btn-remove">Remove</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>
    </section>

    <?php if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
    <section class="recycle-bin-section">
        <article class="restricted-card">
            <h3>♻️ Recycle Bin (Deleted Restricted Vehicles)</h3>
            <?php if (empty($deletedRows)): ?>
                <div class="empty-state">No deleted restricted vehicles in recycle bin.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Plate</th>
                                <th>Reason</th>
                                <th>Deleted On</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deletedRows as $row): ?>
                                <tr style="opacity: 0.7;">
                                    <td><?= htmlspecialchars($row['plate_number']) ?></td>
                                    <td><?= htmlspecialchars($row['reason'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($row['deleted_at']) ?></td>
                                    <td>
                                        <button class="action-btn restore-btn" onclick="restoreRestrictedVehicle('<?= htmlspecialchars($row['plate_number']) ?>')">Restore</button>
                                        <button class="action-btn permanent-delete-btn" onclick="permanentlyDeleteRestrictedVehicle('<?= htmlspecialchars($row['plate_number']) ?>')">Delete Permanently</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>
    </section>
    <?php endif; ?>
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

function deleteRestrictedVehicle(plate) {
    if (confirm(`Move restricted vehicle ${plate} to recycle bin?\n\nYou can restore it later if needed.`)) {
        fetch('manage_restricted_vehicles.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=soft_delete&plate=' + encodeURIComponent(plate)
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            if (data.status === 'success') location.reload();
        })
        .catch(err => alert('Error: ' + err));
    }
}

function restoreRestrictedVehicle(plate) {
    if (confirm(`Restore restricted vehicle ${plate} from recycle bin?`)) {
        fetch('manage_restricted_vehicles.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=restore&plate=' + encodeURIComponent(plate)
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            if (data.status === 'success') location.reload();
        })
        .catch(err => alert('Error: ' + err));
    }
}

function permanentlyDeleteRestrictedVehicle(plate) {
    const confirmMsg = `⚠️ WARNING: Permanently delete restricted vehicle ${plate}?\n\nThis will completely remove this record and cannot be undone.`;
    if (confirm(confirmMsg)) {
        const doubleCheck = confirm('This is your FINAL confirmation. Click OK to permanently delete, or Cancel to abort.');
        if (doubleCheck) {
            fetch('manage_restricted_vehicles.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=permanent_delete&plate=' + encodeURIComponent(plate)
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.status === 'success') location.reload();
            })
            .catch(err => alert('Error: ' + err));
        }
    }
}
</script>
</body>
</html>