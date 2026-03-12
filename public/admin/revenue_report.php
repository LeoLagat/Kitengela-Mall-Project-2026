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
$from = $_GET['from'] ?? null;
$to   = $_GET['to']   ?? null;

// include audit helper
require_once(__DIR__ . '/../../backend/app/services/AdminAudit.php');

if ($from && $to) {
    // log download action (admin must be logged in by earlier check)
    if (!empty($_SESSION['admin_username'])) {
        AdminAudit::log($pdo, $_SESSION['admin_username'], "downloaded revenue report $from to $to");
    }
    // validate simple YYYY-MM-DD format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        die('Invalid date format');
    }

    $stmt = $pdo->prepare(
        "SELECT plate_number, entry_time, exit_time, total_fee
         FROM vehicle_logs
         WHERE payment_status = 'paid'
           AND exit_time BETWEEN ? AND ?
         ORDER BY exit_time ASC"
    );
    $stmt->execute(["$from 00:00:00", "$to 23:59:59"]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // output CSV headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=revenue_' . $from . '_to_' . $to . '.csv');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Plate Number','Entry Time','Exit Time','Amount']);
    $total = 0;
    foreach ($rows as $r) {
        fputcsv($out, [$r['plate_number'], $r['entry_time'], $r['exit_time'], $r['total_fee']]);
        $total += $r['total_fee'];
    }
    fputcsv($out, []);
    fputcsv($out, ['Total','','',$total]);
    fclose($out);
    exit;
}

// if no dates provided, just show a simple selection form
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Revenue Report</title>
    <style>
        body { font-family: Arial, sans-serif; padding:20px; }
        form { display:flex; gap:10px; align-items:flex-end; }
        label { display:flex; flex-direction:column; font-size:14px; }
        button { padding:6px 12px; }
    </style>
</head>
<body>
<h2>Generate Revenue Report</h2>
<form method="get" action="revenue_report.php">
    <label>From <input type="date" name="from" required></label>
    <label>To <input type="date" name="to" required></label>
    <button type="submit">Download CSV</button>
</form>
<p><a href="dashboard.php">Back to dashboard</a></p>
</body>
</html>
