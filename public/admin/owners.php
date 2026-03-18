<?php

session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$message = null;
$messageType = 'success';
$lastComputedAt = $_SESSION['owners_last_computed_at'] ?? null;

require_once(__DIR__ . '/../../backend/app/config/database.php');
$db = new DatabaseConnection();
$pdo = $db->pdo;

require_once(__DIR__ . '/../../backend/app/services/AdminAudit.php');
if (!empty($_SESSION['admin_username'])) {
    AdminAudit::log($pdo, $_SESSION['admin_username'], 'visited owners page');
}

function syncOwnerBilling(PDO $pdo): void {
    // Ensure every active invoiced owner has a fee ledger row.
    $ensureOwnerFeeRows = $pdo->prepare(
        "INSERT INTO owner_vehicle_fees (plate_number, owner_name, nominal_fee, discount_given, total_due, due_period)
         SELECT a.plate_number,
             COALESCE(a.owner_name, ''),
             0,
             0,
             0,
             DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
         FROM owner_accounts a
         LEFT JOIN owner_vehicle_fees f ON f.plate_number = a.plate_number
         WHERE a.deleted_at IS NULL
        AND a.invoice_monthly = 1
        AND f.plate_number IS NULL"
    );
    $ensureOwnerFeeRows->execute();

    // Backfill historical owner exits that were saved with total_fee = 0.
    $backfillLogs = $pdo->prepare(
        "UPDATE vehicle_logs vl
         INNER JOIN owner_accounts oa
             ON oa.plate_number = vl.plate_number
            AND oa.invoice_monthly = 1
            AND oa.deleted_at IS NULL
         SET vl.total_fee = ROUND(vl.nominal_fee * 0.7, 2)
             WHERE (vl.payment_status = 'invoiced' OR vl.payment_status IS NULL OR vl.payment_status = '')
           AND vl.exit_time IS NOT NULL
           AND COALESCE(vl.nominal_fee, 0) > 0
           AND COALESCE(vl.total_fee, 0) = 0"
    );
    $backfillLogs->execute();

    // Rebuild owner accumulated nominal fees from unpaid owner-invoice logs.
    $syncOwnerFees = $pdo->prepare(
        "UPDATE owner_vehicle_fees f
         LEFT JOIN (
             SELECT plate_number,
                    COALESCE(SUM(nominal_fee), 0) AS nominal_total,
                    MAX(exit_time) AS last_exit
             FROM vehicle_logs
             WHERE exit_time IS NOT NULL
               AND COALESCE(nominal_fee, 0) > 0
               AND (payment_status = 'invoiced' OR payment_status IS NULL OR payment_status = '')
             GROUP BY plate_number
         ) v ON v.plate_number = f.plate_number
         LEFT JOIN owner_accounts a ON a.plate_number = f.plate_number
         SET f.nominal_fee = COALESCE(v.nominal_total, 0),
             f.due_period = CASE
                 WHEN v.last_exit IS NULL THEN f.due_period
                 ELSE DATE_ADD(v.last_exit, INTERVAL 1 MONTH)
             END,
             f.owner_name = COALESCE(a.owner_name, f.owner_name)
         WHERE a.deleted_at IS NULL"
    );
    $syncOwnerFees->execute();

    // Keep owner invoice balances in sync: total_due is nominal fee less 30%.
    $update = $pdo->prepare("UPDATE owner_vehicle_fees SET discount_given = nominal_fee * 0.3, total_due = nominal_fee - (nominal_fee * 0.3)");
    $update->execute();
}

if (isset($_GET['silent_sync']) && $_GET['silent_sync'] === '1') {
    header('Content-Type: application/json');
    try {
        syncOwnerBilling($pdo);
        $currentDate = date('Y-m-d');

        $summaryStmt = $pdo->prepare(
            "SELECT
                SUM(
                    CASE
                        WHEN DATE_ADD(a.created_at, INTERVAL 1 MONTH) >= :currentDate THEN 1
                        WHEN DATE_ADD(a.created_at, INTERVAL 1 MONTH) < :currentDate AND (f.total_due IS NULL OR f.total_due <= 0) THEN 1
                        ELSE 0
                    END
                ) AS active_count,
                SUM(
                    CASE
                        WHEN DATE_ADD(a.created_at, INTERVAL 1 MONTH) < :currentDate AND COALESCE(f.total_due, 0) > 0 THEN 1
                        ELSE 0
                    END
                ) AS expired_count,
                COALESCE(SUM(COALESCE(f.total_due, 0)), 0) AS total_due
            FROM owner_accounts a
            LEFT JOIN owner_vehicle_fees f ON a.plate_number = f.plate_number
            WHERE a.deleted_at IS NULL"
        );
        $summaryStmt->execute([':currentDate' => $currentDate]);
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $dueStmt = $pdo->prepare(
            "SELECT a.plate_number, COALESCE(f.total_due, 0) AS total_due
             FROM owner_accounts a
             LEFT JOIN owner_vehicle_fees f ON a.plate_number = f.plate_number
             WHERE a.deleted_at IS NULL"
        );
        $dueStmt->execute();
        $dueRows = $dueStmt->fetchAll(PDO::FETCH_ASSOC);

        $dueByPlate = [];
        foreach ($dueRows as $row) {
            $dueByPlate[$row['plate_number']] = (float) $row['total_due'];
        }

        echo json_encode([
            'status' => 'success',
            'activeCount' => (int) ($summary['active_count'] ?? 0),
            'expiredCount' => (int) ($summary['expired_count'] ?? 0),
            'totalDue' => (float) ($summary['total_due'] ?? 0),
            'dueByPlate' => $dueByPlate
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Silent sync failed'
        ]);
    }
    exit;
}


$ownerAction = $_POST['owner_action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ownerAction !== '') {
    try {
        if ($ownerAction === 'compute_total') {
            syncOwnerBilling($pdo);
            $lastComputedAt = date('Y-m-d H:i:s');
            $_SESSION['owners_last_computed_at'] = $lastComputedAt;
            $message = 'Owner totals recomputed successfully.';
            if (!empty($_SESSION['admin_username'])) {
                AdminAudit::log($pdo, $_SESSION['admin_username'], 'computed owner totals');
            }
        } elseif ($ownerAction === 'record_owner_payment') {
            $payPlate = strtoupper(trim($_POST['pay_plate'] ?? ''));
            if ($payPlate === '') {
                throw new Exception('Owner plate number is required for payment.');
            }

            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
            }

            syncOwnerBilling($pdo);

            $dueStmt = $pdo->prepare(
                "SELECT COALESCE(f.total_due, 0) AS total_due
                 FROM owner_vehicle_fees f
                 INNER JOIN owner_accounts a ON a.plate_number = f.plate_number
                 WHERE a.deleted_at IS NULL
                   AND a.invoice_monthly = 1
                   AND f.plate_number = ?
                 LIMIT 1"
            );
            $dueStmt->execute([$payPlate]);
            $dueRow = $dueStmt->fetch(PDO::FETCH_ASSOC);
            $dueAmount = $dueRow ? (float) $dueRow['total_due'] : 0;

            if ($dueAmount <= 0) {
                $message = 'No outstanding balance found for ' . $payPlate . '.';
            } else {
                // Use the latest exited owner log as the payment evidence row.
                $latestStmt = $pdo->prepare(
                    "SELECT id
                     FROM vehicle_logs
                     WHERE plate_number = ?
                       AND exit_time IS NOT NULL
                       AND COALESCE(nominal_fee, 0) > 0
                       AND (payment_status = 'invoiced' OR payment_status IS NULL OR payment_status = '')
                     ORDER BY exit_time DESC, id DESC
                     LIMIT 1"
                );
                $latestStmt->execute([$payPlate]);
                $latestLogId = (int) $latestStmt->fetchColumn();

                if ($latestLogId <= 0) {
                    throw new Exception('No payable owner log found for ' . $payPlate . '.');
                }

                $settleOlderStmt = $pdo->prepare(
                    "UPDATE vehicle_logs
                     SET payment_status = 'paid',
                         paid_at = NOW(),
                         total_fee = 0
                     WHERE plate_number = ?
                       AND id <> ?
                       AND exit_time IS NOT NULL
                       AND COALESCE(nominal_fee, 0) > 0
                       AND (payment_status = 'invoiced' OR payment_status IS NULL OR payment_status = '')"
                );
                $settleOlderStmt->execute([$payPlate, $latestLogId]);

                $settleLatestStmt = $pdo->prepare(
                    "UPDATE vehicle_logs
                     SET payment_status = 'paid',
                         paid_at = NOW(),
                         total_fee = ?
                     WHERE id = ?"
                );
                $settleLatestStmt->execute([$dueAmount, $latestLogId]);

                syncOwnerBilling($pdo);

                $advanceDueStmt = $pdo->prepare(
                    "UPDATE owner_vehicle_fees
                     SET due_period = DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
                     WHERE plate_number = ?"
                );
                $advanceDueStmt->execute([$payPlate]);

                $message = 'Payment received for ' . $payPlate . ': KES ' . number_format($dueAmount, 2) . '. Balance updated to 0.00.';
                if (!empty($_SESSION['admin_username'])) {
                    AdminAudit::log(
                        $pdo,
                        $_SESSION['admin_username'],
                        'received monthly owner payment for ' . $payPlate . ' (KES ' . number_format($dueAmount, 2) . ')'
                    );
                }
            }

            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $messageType = 'error';
        $message = 'Error: ' . $e->getMessage();
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['plate'])) {
    $plate = strtoupper(trim($_POST['plate']));
    if ($plate !== '') {
        try {
            $ownerName = $_POST['name'] ?? null;
            $invoiceMonthly = isset($_POST['invoice']) ? 1 : 0;

            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
            }

            $stmt = $pdo->prepare("INSERT INTO owner_accounts (plate_number, owner_name, invoice_monthly) VALUES (?, ?, ?)");
            $stmt->execute([$plate, $ownerName, $invoiceMonthly]);

            if ($invoiceMonthly === 1) {
                // Create ledger row immediately so totals always have a target row.
                $ledgerInsert = $pdo->prepare(
                    "INSERT INTO owner_vehicle_fees (plate_number, owner_name, nominal_fee, discount_given, total_due, due_period)
                     SELECT ?, ?, 0, 0, 0, DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
                     WHERE NOT EXISTS (
                         SELECT 1 FROM owner_vehicle_fees WHERE plate_number = ?
                     )"
                );
                $ledgerInsert->execute([$plate, $ownerName, $plate]);

                $ledgerNameSync = $pdo->prepare("UPDATE owner_vehicle_fees SET owner_name = ? WHERE plate_number = ?");
                $ledgerNameSync->execute([$ownerName, $plate]);
            }

            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            $message = "Added owner $plate.";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $messageType = 'error';
            $message = "Error: " . $e->getMessage();
        }
    } else {
        $messageType = 'error';
        $message = "Error: Plate number cannot be empty.";
    }
}

// fetch current owner list, only show non-expired (and non-deleted)
$currentDate = date('Y-m-d');
syncOwnerBilling($pdo);

// Status logic:
// 1. If billing due date is in the future, status is Active
// 2. If billing due date is past and total_due <= 0, status is Active
// 3. If billing due date is past and total_due > 0, status is Expired
$stmt = $pdo->prepare("
SELECT a.plate_number, a.owner_name, a.invoice_monthly, a.created_at,
    COALESCE(f.due_period, DATE_ADD(a.created_at, INTERVAL 1 MONTH)) AS active_until,
    f.total_due,
    CASE
        WHEN COALESCE(f.due_period, DATE_ADD(a.created_at, INTERVAL 1 MONTH)) >= ? THEN 'Active'
        WHEN COALESCE(f.due_period, DATE_ADD(a.created_at, INTERVAL 1 MONTH)) < ? AND (f.total_due IS NULL OR f.total_due <= 0) THEN 'Active'
        ELSE 'Expired'
    END AS status
FROM owner_accounts a
LEFT JOIN owner_vehicle_fees f ON a.plate_number = f.plate_number
WHERE a.deleted_at IS NULL
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

// Fetch deleted vehicles (recycle bin) - only for super_admin
$deletedVehicles = [];
if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin') {
    $stmt = $pdo->prepare("
    SELECT a.plate_number, a.owner_name, a.invoice_monthly, a.created_at, a.deleted_at,
        f.total_due
    FROM owner_accounts a
    LEFT JOIN owner_vehicle_fees f ON a.plate_number = f.plate_number
    WHERE a.deleted_at IS NOT NULL
    ORDER BY a.deleted_at DESC");
    $stmt->execute();
    $deletedVehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }

        .delete-btn {
            background: crimson;
            color: white;
        }

        .delete-btn:hover {
            background: firebrick;
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

        .owners-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .owners-actions form {
            margin: 0;
        }

        .compute-meta {
            margin: 10px 0 0 0;
            color: dimgray;
            font-size: 13px;
            font-weight: 600;
        }

        .owners-actions button {
            border: none;
            border-radius: 8px;
            padding: 10px 14px;
            color: white;
            font-weight: 700;
            cursor: pointer;
        }

        .compute-btn {
            background: steelblue;
        }

        .compute-btn:hover {
            background: royalblue;
        }

        .revenue-btn {
            background: darkgreen;
        }

        .revenue-btn:hover {
            background: seagreen;
        }

        .pay-owner-btn {
            background: darkgreen;
            border: none;
            border-radius: 6px;
            color: white;
            padding: 6px 10px;
            font-weight: 700;
            cursor: pointer;
            font-size: 12px;
        }

        .pay-owner-btn:hover {
            background: seagreen;
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
    <a href="database_search.php">Database Search</a>
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
            <span id="ownersActiveCount" class="summary-value"><?= $activeCount ?></span>
        </article>
        <article class="summary-card">
            <span class="summary-title">Expired Accounts</span>
            <span id="ownersExpiredCount" class="summary-value"><?= $expiredCount ?></span>
        </article>
        <article class="summary-card">
            <span class="summary-title">Total Due</span>
            <span id="ownersTotalDue" class="summary-value">KES <?= number_format($totalDue, 2) ?></span>
        </article>
    </section>

    <?php if ($message !== null): ?>
        <div class="<?= $messageType === 'error' ? 'error' : 'success' ?> status-message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <section class="owners-card">
        <h3>Owner Totals Actions</h3>
        <div class="owners-actions">
            <form method="POST">
                <input type="hidden" name="owner_action" value="compute_total">
                <button type="submit" class="compute-btn">Compute Total</button>
            </form>
        </div>
        <p class="compute-meta">
            Last computed: <?= $lastComputedAt ? htmlspecialchars($lastComputedAt) : 'Not computed manually yet' ?>
        </p>
    </section>

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
                                <th>Payment</th>
                                <?php if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
                                    <th>Action</th>
                                <?php endif; ?>
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
                                    <td class="owner-due-cell" data-plate="<?= htmlspecialchars($row['plate_number']) ?>">KES <?= isset($row['total_due']) ? number_format($row['total_due'], 2) : '0.00' ?></td>
                                    <td>
                                        <?php $rowDue = isset($row['total_due']) ? (float) $row['total_due'] : 0; ?>
                                        <?php if ($rowDue > 0): ?>
                                            <form method="POST" onsubmit="return confirm('Receive payment of KES <?= number_format($rowDue, 2) ?> for <?= htmlspecialchars($row['plate_number']) ?>?');" style="margin:0;">
                                                <input type="hidden" name="owner_action" value="record_owner_payment">
                                                <input type="hidden" name="pay_plate" value="<?= htmlspecialchars($row['plate_number']) ?>">
                                                <button type="submit" class="pay-owner-btn">Receive Payment</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: dimgray; font-size: 12px;">No Due</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
                                        <td>
                                            <button class="action-btn delete-btn" onclick="deleteVehicle('<?= htmlspecialchars($row['plate_number']) ?>')">Delete</button>
                                        </td>
                                    <?php endif; ?>
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
        <article class="owners-card">
            <h3>♻️ Recycle Bin (Deleted Vehicles)</h3>
            <?php if (empty($deletedVehicles)): ?>
                <div class="empty-state">No deleted vehicles in recycle bin.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="owner-table">
                        <thead>
                            <tr>
                                <th>Plate</th>
                                <th>Name</th>
                                <th>Deleted On</th>
                                <th>Total Due</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deletedVehicles as $row): ?>
                                <tr style="opacity: 0.7;">
                                    <td><?= htmlspecialchars($row['plate_number']) ?></td>
                                    <td><?= htmlspecialchars($row['owner_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($row['deleted_at']) ?></td>
                                    <td>KES <?= isset($row['total_due']) ? number_format($row['total_due'], 2) : '0.00' ?></td>
                                    <td>
                                        <button class="action-btn restore-btn" onclick="restoreVehicle('<?= htmlspecialchars($row['plate_number']) ?>')">Restore</button>
                                        <button class="action-btn permanent-delete-btn" onclick="permanentlyDeleteVehicle('<?= htmlspecialchars($row['plate_number']) ?>')">Delete Permanently</button>
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

    function formatKes(amount) {
        return 'KES ' + Number(amount || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function runSilentOwnerSync() {
        const active = document.activeElement;
        const isTyping = active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA');
        if (document.hidden || isTyping) {
            return;
        }

        fetch('owners.php?silent_sync=1', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(res) {
            return res.json();
        })
        .then(function(data) {
            if (!data || data.status !== 'success') {
                return;
            }

            const activeEl = document.getElementById('ownersActiveCount');
            const expiredEl = document.getElementById('ownersExpiredCount');
            const totalEl = document.getElementById('ownersTotalDue');

            if (activeEl) activeEl.textContent = String(data.activeCount || 0);
            if (expiredEl) expiredEl.textContent = String(data.expiredCount || 0);
            if (totalEl) totalEl.textContent = formatKes(data.totalDue || 0);

            if (data.dueByPlate) {
                document.querySelectorAll('.owner-due-cell[data-plate]').forEach(function(cell) {
                    const plate = cell.getAttribute('data-plate') || '';
                    if (Object.prototype.hasOwnProperty.call(data.dueByPlate, plate)) {
                        cell.textContent = formatKes(data.dueByPlate[plate]);
                    }
                });
            }
        })
        .catch(function() {
            // ignore intermittent sync failures to keep UX smooth
        });
    }

    setInterval(runSilentOwnerSync, 15000);
});

function deleteVehicle(plate) {
    if (confirm(`Move vehicle ${plate} to recycle bin?\n\nYou can restore it later if needed.`)) {
        fetch('manage_owner_vehicles.php', {
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

function restoreVehicle(plate) {
    if (confirm(`Restore vehicle ${plate} from recycle bin?`)) {
        fetch('manage_owner_vehicles.php', {
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

function permanentlyDeleteVehicle(plate) {
    const confirmMsg = `⚠️ WARNING: Permanently delete vehicle ${plate}?\n\nThis will completely remove this record and cannot be undone.`;
    if (confirm(confirmMsg)) {
        const doubleCheck = confirm('This is your FINAL confirmation. Click OK to permanently delete, or Cancel to abort.');
        if (doubleCheck) {
            fetch('manage_owner_vehicles.php', {
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