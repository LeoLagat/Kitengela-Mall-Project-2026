<?php
// manual helper script used during mock/testing mode
require_once(__DIR__ . '/../../backend/app/config/database.php');
$plate = $_GET['plate'] ?? '';
if ($plate) {
    $db = new DatabaseConnection();
    $pdo = $db->pdo;

    // locate the active log for this plate
    $stmt = $pdo->prepare("SELECT id AS log_id, bay_id, total_fee FROM vehicle_logs WHERE plate_number = ? AND exit_time IS NULL ORDER BY id DESC LIMIT 1");
    $stmt->execute([$plate]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $logId = $row['log_id'];
        $bayId = $row['bay_id'];
        $amount = $row['total_fee'];

        // if the database didn't yet contain the fee (possible if user bypassed
        // payment initiation), recompute using entry_time so we still charge
        if (!$amount) {
            $entry = new DateTime($row['entry_time']);
            $now = new DateTime();
            $diff = $entry->diff($now);
            $totalMinutes = ($diff->days * 1440) + ($diff->h * 60) + $diff->i;
            if ($totalMinutes <= 30) {
                $amount = 0;
            } elseif ($totalMinutes <= 60) {
                $amount = 50;
            } else {
                $hours = ceil($totalMinutes / 60);
                $extra = $hours - 1;
                $amount = 50 + ($extra * 20);
            }
        }

        // mark log as paid and stamp exit time
        $upd = $pdo->prepare("UPDATE vehicle_logs SET payment_status='paid', exit_time=NOW(), total_fee = :amt WHERE id = :id");
        $upd->execute([':amt' => $amount, ':id' => $logId]);

        // insert a fake mpesa_transactions record so other parts of the app behave
        $ins = $pdo->prepare(
            "INSERT INTO mpesa_transactions (log_id, phone_number, checkout_id, receipt_number, amount, status) VALUES (?, ?, ?, ?, ?, 'Completed')"
        );
        $ins->execute([
            $logId,
            '',                             // phone unknown in simulation
            'SIM-' . time(),                // synthetic checkout id
            'SIM-' . time(),                // synthetic receipt
            $amount
        ]);

        // free the bay if set
        if ($bayId) {
            $updBay = $pdo->prepare("UPDATE parking_bays SET current_status='vacant' WHERE id = ?");
            $updBay->execute([$bayId]);
        }
    }
}
// reply minimal
header('Content-Type: application/json');
echo json_encode(['ok'=>true]);
