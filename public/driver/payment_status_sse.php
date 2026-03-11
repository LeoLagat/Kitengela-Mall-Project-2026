<?php
// public/driver/payment_status_sse.php
// Server‑Sent Events endpoint that pushes status updates for a given plate.
require_once(__DIR__ . '/../../backend/app/config/database.php');

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

$plate = $_GET['plate'] ?? '';

if (!$plate) {
    // nothing to do
    exit;
}

$db = new DatabaseConnection();
$pdo = $db->pdo;

$lastStatus = null;

// Keep the connection open and poll the DB.  In a real production system
// you'd hook into a message queue or trigger to avoid polling.
while (true) {
    try {
        $stmt = $pdo->prepare(
            "SELECT payment_status FROM vehicle_logs WHERE plate_number = ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$plate]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $status = $row['payment_status'] ?? 'pending';
    } catch (Exception $e) {
        // on error stop
        break;
    }

    if ($status !== $lastStatus) {
        echo "data: " . json_encode(['status' => $status]) . "\n\n";
        ob_flush();
        flush();
        $lastStatus = $status;

        if ($status === 'paid' || $status === 'failed') {
            break; // stop after final state
        }
    }

    // sleep briefly before checking again
    sleep(1);
}

// close connection
?>