<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once(__DIR__ . '/../../backend/app/config/database.php');
require_once(__DIR__ . '/../../backend/app/services/AdminAudit.php');

$db = new DatabaseConnection();
$pdo = $db->pdo;

$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;

// Preview and CSV download logic
$rows = [];
$error = '';
$showPreview = false;
if ($from && $to) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $error = 'Invalid date format.';
    } elseif ($from > $to) {
        $error = 'Invalid date range.';
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, plate_number, bay_id, entry_time, exit_time, total_fee, payment_status, nominal_fee, paid_at, is_manual_bypass, bypassed_by, bypassed_at
             FROM vehicle_logs
             WHERE (entry_time BETWEEN ? AND ?)
                OR (exit_time BETWEEN ? AND ?)
             ORDER BY entry_time ASC"
        );
        $stmt->execute(["$from 00:00:00", "$to 23:59:59", "$from 00:00:00", "$to 23:59:59"]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $showPreview = true;
    }
    // CSV download only if ?download=1
    if (isset($_GET['download']) && !$error) {
        if (!empty($_SESSION['admin_username'])) {
            AdminAudit::log($pdo, $_SESSION['admin_username'], "downloaded vehicle log report $from to $to");
        }
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename=vehicle_logs_' . $from . '_to_' . $to . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'ID','Plate Number','Bay ID','Entry Time','Exit Time','Payment Status','Total Fee','Nominal Fee','Paid At','Manual Bypass','Bypassed By','Bypassed At'
        ]);
        $totalRows = count($rows);
        $paidCount = 0;
        $pendingCount = 0;
        $bypassCount = 0;
        $totalFee = 0;
        foreach ($rows as $row) {
            $status = strtolower((string)($row['payment_status'] ?? ''));
            if ($status === 'paid') {
                $paidCount++;
                $totalFee += (float)$row['total_fee'];
            } elseif ($status === 'pending') {
                $pendingCount++;
            }
            if (!empty($row['is_manual_bypass']) && (int)$row['is_manual_bypass'] === 1) {
                $bypassCount++;
            }
            fputcsv($out, [
                $row['id'],$row['plate_number'],$row['bay_id'],$row['entry_time'],$row['exit_time'],$row['payment_status'],$row['total_fee'],$row['nominal_fee'],$row['paid_at'],((int)$row['is_manual_bypass'] === 1 ? 'yes' : 'no'),$row['bypassed_by'],$row['bypassed_at'],
            ]);
        }
        fputcsv($out, []);
        fputcsv($out, ['Summary']);
        fputcsv($out, ['Total Records', $totalRows]);
        fputcsv($out, ['Paid Records', $paidCount]);
        fputcsv($out, ['Pending Records', $pendingCount]);
        fputcsv($out, ['Manual Bypass Records', $bypassCount]);
        fputcsv($out, ['Paid Revenue Total', number_format($totalFee, 2, '.', '')]);
        fclose($out);
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Vehicle Log Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: whitesmoke;
            color: darkslategray;
        }
        .card {
            max-width: 700px;
            background: white;
            border: 1px solid lightgray;
            border-radius: 10px;
            padding: 18px;
        }
        form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-end;
        }
        label {
            display: flex;
            flex-direction: column;
            font-size: 14px;
            font-weight: 600;
        }
        input[type="date"] {
            margin-top: 4px;
            padding: 8px;
            border: 1px solid lightgray;
            border-radius: 6px;
        }
        button {
            padding: 9px 14px;
            border: none;
            border-radius: 6px;
            background: darkgreen;
            color: white;
            cursor: pointer;
            font-weight: 700;
        }
        button:hover {
            background: seagreen;
        }
        a {
            color: darkgreen;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="card">
        <h2>Vehicle Log Report</h2>
        <form method="get" action="vehicle_log_report.php" style="margin-bottom:18px;">
            <label>From <input type="date" name="from" value="<?= htmlspecialchars($from ?? '') ?>" required></label>
            <label>To <input type="date" name="to" value="<?= htmlspecialchars($to ?? '') ?>" required></label>
            <button type="submit">Preview Report</button>
            <?php if ($showPreview && !$error): ?>
                <a href="vehicle_log_report.php?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&download=1" class="btn" style="background:seagreen;color:white;margin-left:10px;">&#8659; Download CSV</a>
            <?php endif; ?>
        </form>
        <?php if ($error): ?>
            <div style="background:mistyrose;border-left:4px solid crimson;padding:10px 14px;border-radius:6px;color:crimson;font-weight:600;"> <?= htmlspecialchars($error) ?> </div>
        <?php endif; ?>
        <?php if ($showPreview && !$error): ?>
            <div style="overflow-x:auto;max-height:420px;">
                <table style="width:100%;font-size:13px;">
                    <thead>
                        <tr>
                            <th>ID</th><th>Plate</th><th>Bay</th><th>Entry</th><th>Exit</th><th>Status</th><th>Total Fee</th><th>Nominal Fee</th><th>Paid At</th><th>Bypass?</th><th>Bypassed By</th><th>Bypassed At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['id']) ?></td>
                            <td><?= htmlspecialchars($r['plate_number']) ?></td>
                            <td><?= htmlspecialchars($r['bay_id']) ?></td>
                            <td><?= htmlspecialchars($r['entry_time']) ?></td>
                            <td><?= htmlspecialchars($r['exit_time']) ?></td>
                            <td><?= htmlspecialchars($r['payment_status']) ?></td>
                            <td><?= htmlspecialchars($r['total_fee']) ?></td>
                            <td><?= htmlspecialchars($r['nominal_fee']) ?></td>
                            <td><?= htmlspecialchars($r['paid_at']) ?></td>
                            <td><?= ((int)$r['is_manual_bypass'] === 1 ? 'yes' : 'no') ?></td>
                            <td><?= htmlspecialchars($r['bypassed_by']) ?></td>
                            <td><?= htmlspecialchars($r['bypassed_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top:10px;font-size:13px;color:dimgray;">Showing <?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?> for the selected range.</div>
        <?php endif; ?>
        <p style="margin-top:18px;"><a href="dashboard.php">Back to dashboard</a></p>
    </div>
</body>
</html>