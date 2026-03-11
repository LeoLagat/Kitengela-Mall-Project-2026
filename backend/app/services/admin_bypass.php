<?php
// backend/app/services/admin_bypass.php
session_start();
// ensure only authorized users can invoke bypass
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
require_once(__DIR__ . '/../config/database.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['plate'])) {
    $plate = $_POST['plate'];
    $db = new DatabaseConnection();
    $pdo = $db->pdo;

    try {
        $pdo->beginTransaction(); // Use a transaction to ensure both updates happen together

        // 1. Find the bay_id associated with this active vehicle
        $stmtFind = $pdo->prepare("SELECT bay_id FROM vehicle_logs WHERE plate_number = :plate AND exit_time IS NULL LIMIT 1");
        $stmtFind->execute([':plate' => $plate]);
        $vehicle = $stmtFind->fetch(PDO::FETCH_ASSOC);

        if ($vehicle) {
            $bayId = $vehicle['bay_id'];

            // 2. Update vehicle_logs to mark as paid and exited
            $stmtLog = $pdo->prepare("
                UPDATE vehicle_logs 
                SET payment_status = 'paid',
                    exit_time = NOW()
                WHERE plate_number = :plate 
                AND exit_time IS NULL
            ");
            $stmtLog->execute([':plate' => $plate]);

            // 3. Update the parking bay status to 'vacant'
            // Assuming your table is named 'parking_bays' and the column is 'current_status'
            $stmtBay = $pdo->prepare("
                UPDATE parking_bays 
                SET current_status = 'vacant' 
                WHERE id = :bay_id
            ");
            $stmtBay->execute([':bay_id' => $bayId]);

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => "Manual bypass successful. Bay $bayId is now vacant!"]);
        } else {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => "No active vehicle found for $plate."]);
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => "Database Error: " . $e->getMessage()]);
    }
}