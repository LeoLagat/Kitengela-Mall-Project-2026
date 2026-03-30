<?php
session_start();

// require admin login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// reuse the shared connection class from backend config
require_once(__DIR__ . '/../../backend/app/config/database.php');

$db = new DatabaseConnection();
$pdo = $db->pdo;

// parse dates
$from   = $_GET['from']     ?? null;
$to     = $_GET['to']       ?? null;
$download = isset($_GET['download']) && $_GET['download'] === '1';

// include audit helper
require_once(__DIR__ . '/../../backend/app/services/AdminAudit.php');

// validate dates if provided
$rows = [];
$ownerInvoiceRows = [];
$dateError = '';
if ($from && $to) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $dateError = 'Invalid date format.';
    } elseif ($from > $to) {
        $dateError = '"From" date cannot be after "To" date.';
    } else {
                $stmt = $pdo->prepare(
                        "SELECT plate_number, entry_time, exit_time, total_fee, payment_status
                         FROM vehicle_logs
                         WHERE (
                                            (entry_time  BETWEEN ? AND ?)
                                    OR  (exit_time   BETWEEN ? AND ?)
                             )
                         ORDER BY entry_time ASC"
                );
                $stmt->execute(["$from 00:00:00", "$to 23:59:59", "$from 00:00:00", "$to 23:59:59"]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $ownerInvoiceStmt = $pdo->prepare(
            "SELECT oa.plate_number,
                    COALESCE(oa.owner_name, '') AS owner_name,
                    COUNT(*) AS vehicle_count,
                    COALESCE(SUM(vl.nominal_fee), 0) AS nominal_total,
                    COALESCE(SUM(vl.total_fee), 0) AS total_due,
                    MAX(vl.exit_time) AS last_exit
             FROM vehicle_logs vl
             INNER JOIN owner_accounts oa
                 ON oa.plate_number = vl.plate_number
                AND oa.invoice_monthly = 1
                AND oa.deleted_at IS NULL
             WHERE vl.exit_time IS NOT NULL
               AND COALESCE(vl.nominal_fee, 0) > 0
               AND (vl.payment_status = 'invoiced' OR vl.payment_status IS NULL OR vl.payment_status = '')
               AND (
                      (vl.entry_time BETWEEN ? AND ?)
                   OR (vl.exit_time BETWEEN ? AND ?)
               )
             GROUP BY oa.plate_number, oa.owner_name
             ORDER BY last_exit DESC"
        );
        $ownerInvoiceStmt->execute(["$from 00:00:00", "$to 23:59:59", "$from 00:00:00", "$to 23:59:59"]);
        $ownerInvoiceRows = array_filter(
            $ownerInvoiceStmt->fetchAll(PDO::FETCH_ASSOC),
            function($row) {
                return isset($row['total_due']) && (float)$row['total_due'] > 0;
            }
        );
    }
}

// --- CSV download path (only after preview was shown) ---
if ($from && $to && $download && !$dateError) {
    if (!empty($_SESSION['admin_username'])) {
        AdminAudit::log($pdo, $_SESSION['admin_username'], "downloaded revenue report $from to $to");
    }
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=revenue_' . $from . '_to_' . $to . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Plate Number', 'Entry Time', 'Exit Time', 'Status', 'Amount (Ksh)']);
    $total = 0;
    foreach ($rows as $r) {
        fputcsv($out, [$r['plate_number'], $r['entry_time'], $r['exit_time'], $r['payment_status'], $r['total_fee']]);
        if (in_array($r['payment_status'], ['paid', 'invoiced'], true)) {
            $total += (float)$r['total_fee'];
        }
    }

    $ownerInvoiceTotal = 0;
    if (!empty($ownerInvoiceRows)) {
        fputcsv($out, []);
        fputcsv($out, ['Outstanding Owner Invoices']);
        fputcsv($out, ['Plate Number', 'Owner Name', 'Trips In Invoice', 'Nominal Total (Ksh)', 'Total Due (Ksh)', 'Last Invoiced Exit']);
        foreach ($ownerInvoiceRows as $invoiceRow) {
            fputcsv($out, [
                $invoiceRow['plate_number'],
                $invoiceRow['owner_name'],
                $invoiceRow['vehicle_count'],
                $invoiceRow['nominal_total'],
                $invoiceRow['total_due'],
                $invoiceRow['last_exit']
            ]);
            $ownerInvoiceTotal += (float) $invoiceRow['total_due'];
        }
        fputcsv($out, ['Outstanding owner invoice total', '', '', '', $ownerInvoiceTotal, '']);
    }

    // archived revenue (from cleared logs) — not date-filterable, included as footnote
    $archivedTotal = 0;
    try {
        $archivedTotal = (float)$pdo->query("SELECT COALESCE(SUM(archived_revenue),0) FROM revenue_archive")->fetchColumn();
    } catch (Exception $e) {}

    fputcsv($out, []);
    fputcsv($out, ['Revenue from date range (vehicle_logs)', '', '', '', $total]);
    fputcsv($out, ['Archived revenue (cleared logs, all time)', '', '', '', $archivedTotal]);
    fputcsv($out, ['COMBINED TOTAL (matches dashboard)', '', '', '', $total + $archivedTotal]);
    fclose($out);
    exit;
}

// --- compute totals for preview ---
$totalRevenue   = 0;
$paidCount      = 0;
$invoicedCount  = 0;
$pendingCount   = 0;
$failedCount    = 0;
$unresolvedCount = 0;
$ownerInvoiceCount = count($ownerInvoiceRows);
$ownerInvoiceVehicleCount = 0;
$ownerInvoiceTotalDue = 0;
foreach ($rows as $r) {
    if ($r['payment_status'] === 'paid') {
        $totalRevenue += (float)$r['total_fee'];
        $paidCount++;
    } elseif ($r['payment_status'] === 'invoiced') {
        $totalRevenue += (float)$r['total_fee'];
        $invoicedCount++;
    } elseif (
        empty($r['exit_time'])
        && in_array(($r['payment_status'] ?? ''), ['pending', 'unpaid', ''], true)
    ) {
        // Vehicle is still inside and has not started/completed exit payment.
        $pendingCount++;
    } elseif ($r['payment_status'] === 'failed') {
        $failedCount++;
    } else {
        // Catch any inconsistent or legacy states so they don't inflate pending vehicles.
        $unresolvedCount++;
    }
}
foreach ($ownerInvoiceRows as $ownerInvoiceRow) {
    $ownerInvoiceVehicleCount += (int) ($ownerInvoiceRow['vehicle_count'] ?? 0);
    $ownerInvoiceTotalDue += (float) ($ownerInvoiceRow['total_due'] ?? 0);
}
// archived revenue from previous log clears (all-time, not date-filterable)
$archivedRevenue = 0;
try {
    $archivedRevenue = (float)$pdo->query("SELECT COALESCE(SUM(archived_revenue),0) FROM revenue_archive")->fetchColumn();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Revenue Report</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: whitesmoke; color: darkslategray; margin: 0; }
        nav { display:flex; justify-content:space-between; align-items:center; background:linear-gradient(90deg,darkgreen,seagreen); padding:12px 24px; }
        nav .logo { color:white; font-size:22px; font-weight:700; }
        nav a { color:white; text-decoration:none; font-weight:600; padding:6px 10px; border-radius:6px; }
        nav a:hover { background:forestgreen; }
        .page { width:95%; max-width:1100px; margin:30px auto 60px; display:grid; gap:18px; }
        .card { background:white; border:1px solid lightgray; border-radius:12px; padding:22px; box-shadow:0 8px 18px gainsboro; }
        .card h2 { margin:0 0 16px; color:forestgreen; font-size:22px; }
        .filter-form { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; }
        .filter-form label { display:flex; flex-direction:column; gap:4px; font-size:13px; font-weight:600; color:dimgray; }
        .filter-form input[type=date] { padding:7px 10px; border:1px solid lightgray; border-radius:6px; font-size:14px; }
        .btn { padding:8px 18px; border:none; border-radius:7px; font-weight:700; cursor:pointer; font-size:14px; }
        .btn-green { background:darkgreen; color:white; }
        .btn-green:hover { background:seagreen; }
        .btn-download { background:steelblue; color:white; }
        .btn-download:hover { background:royalblue; }
        .summary-bar { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
        .chip { padding:6px 14px; border-radius:999px; font-size:13px; font-weight:700; border:1px solid; }
        .chip-revenue { background:honeydew; border-color:seagreen; color:darkgreen; }
        .chip-paid    { background:aliceblue; border-color:steelblue; color:steelblue; }
        .chip-invoiced{ background:lavender; border-color:slateblue; color:slateblue; }
        .chip-pending { background:linen; border-color:darkorange; color:sienna; }
        .chip-failed  { background:mistyrose; border-color:crimson; color:crimson; }
        .chip-other   { background:aliceblue; border-color:slategray; color:slategray; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th { background:seagreen; color:white; padding:10px 12px; text-align:left; }
        td { padding:9px 12px; border-bottom:1px solid lightgray; }
        tr:hover td { background:mintcream; }
        .status-paid     { color:seagreen; font-weight:700; }
        .status-invoiced { color:slateblue; font-weight:700; }
        .status-pending  { color:darkorange; font-weight:700; }
        .no-data { text-align:center; padding:30px; color:dimgray; }
        .error-msg { background:mistyrose; border-left:4px solid crimson; border-radius:6px; padding:12px 16px; color:crimson; font-weight:600; }
    </style>
</head>
<body>
<nav>
    <div class="logo">Admin Panel</div>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="logout.php" style="color:salmon;">Logout</a>
    </div>
</nav>

<div class="page">
    <div class="card">
        <h2>Revenue Report</h2>

        <!-- Date filter form -->
        <form method="get" action="revenue_report.php" class="filter-form">
            <label>From
                <input type="date" name="from" value="<?= htmlspecialchars($from ?? '') ?>" required>
            </label>
            <label>To
                <input type="date" name="to" value="<?= htmlspecialchars($to ?? '') ?>" required>
            </label>
            <button type="submit" class="btn btn-green">Preview Report</button>
        </form>
    </div>

    <?php if ($dateError): ?>
    <div class="error-msg"><?= htmlspecialchars($dateError) ?></div>

    <?php elseif ($from && $to): ?>
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
            <div>
                <h2 style="margin:0;">Results: <?= htmlspecialchars($from) ?> &rarr; <?= htmlspecialchars($to) ?></h2>
                <p style="margin:4px 0 0;color:dimgray;font-size:13px;"><?= count($rows) ?> log record(s) and <?= $ownerInvoiceCount ?> outstanding owner invoice account(s) found. Verify the data below, then click Download CSV.</p>
            </div>
            <?php if (!empty($rows) || !empty($ownerInvoiceRows)): ?>
                <a href="revenue_report.php?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&download=1"
               class="btn btn-download">&#8659; Download CSV</a>
            <?php endif; ?>
        </div>

        <?php if (!empty($rows) || !empty($ownerInvoiceRows)): ?>
        <div class="summary-bar">
            <span class="chip chip-revenue">Date range revenue: Ksh <?= number_format($totalRevenue, 2) ?></span>
            <span class="chip chip-paid">Paid: <?= $paidCount ?></span>
            <span class="chip chip-invoiced">Owner invoice due: Ksh <?= number_format($ownerInvoiceTotalDue, 2) ?></span>
            <span class="chip chip-invoiced">Owner invoice vehicles: <?= $ownerInvoiceVehicleCount ?></span>
            <span class="chip chip-pending">Inside / Pending Checkout: <?= $pendingCount ?></span>
            <?php if ($failedCount > 0): ?>
            <span class="chip chip-failed">Failed Payments: <?= $failedCount ?></span>
            <?php endif; ?>
        </div>
        <?php if ($archivedRevenue > 0): ?>
        <div style="background:lightyellow;border-left:4px solid goldenrod;border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:13px;">
            <strong>Note:</strong> Archived revenue from previously cleared logs (all-time): <strong>Ksh <?= number_format($archivedRevenue, 2) ?></strong>.
            Combined total matching the dashboard: <strong>Ksh <?= number_format($totalRevenue + $archivedRevenue, 2) ?></strong>.
            Archived records have no date metadata and cannot be filtered by date range.
        </div>
        <?php endif; ?>

        <?php if (!empty($ownerInvoiceRows)): ?>
        <div style="background:lavenderblush;border-left:4px solid slateblue;border-radius:6px;padding:12px 14px;margin-bottom:14px;font-size:13px;color:indigo;">
            <strong>Outstanding Owner Invoices</strong><br>
            These owner vehicle trips have been invoiced but not yet paid. Total outstanding amount for the selected date range: <strong>Ksh <?= number_format($ownerInvoiceTotalDue, 2) ?></strong>.
        </div>

        <div style="overflow-x:auto;margin-bottom:18px;">
        <table>
            <thead>
                <tr>
                    <th>Owner Plate</th>
                    <th>Owner Name</th>
                    <th>Vehicles In Invoice</th>
                    <th>Nominal Total (Ksh)</th>
                    <th>Total Due (Ksh)</th>
                    <th>Last Invoiced Exit</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ownerInvoiceRows as $ownerInvoiceRow): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($ownerInvoiceRow['plate_number']) ?></strong></td>
                    <td><?= htmlspecialchars($ownerInvoiceRow['owner_name'] ?: 'N/A') ?></td>
                    <td><?= (int) ($ownerInvoiceRow['vehicle_count'] ?? 0) ?></td>
                    <td>Ksh <?= number_format((float) ($ownerInvoiceRow['nominal_total'] ?? 0), 2) ?></td>
                    <td><strong>Ksh <?= number_format((float) ($ownerInvoiceRow['total_due'] ?? 0), 2) ?></strong></td>
                    <td><?= htmlspecialchars($ownerInvoiceRow['last_exit']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>

        <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Plate Number</th>
                    <th>Entry Time</th>
                    <th>Exit Time</th>
                    <th>Status</th>
                    <th>Amount (Ksh)</th>
                </tr>
            </thead>
            <tbody>
            <?php $i = 1; foreach ($rows as $r):
                $statusClass = match($r['payment_status']) {
                    'paid'     => 'status-paid',
                    'invoiced' => 'status-invoiced',
                    default    => 'status-pending',
                };
            ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars($r['plate_number']) ?></strong></td>
                    <td><?= htmlspecialchars($r['entry_time']) ?></td>
                    <td><?= $r['exit_time'] ? htmlspecialchars($r['exit_time']) : '<em style="color:dimgray;">Still inside</em>' ?></td>
                    <td class="<?= $statusClass ?>"><?= htmlspecialchars(ucfirst($r['payment_status'])) ?></td>
                    <td><?= $r['total_fee'] > 0 ? 'Ksh ' . number_format((float)$r['total_fee'], 2) : '<em style="color:dimgray;">0.00</em>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php else: ?>
        <div class="no-data">No vehicle records or outstanding owner invoices found for the selected date range.</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
