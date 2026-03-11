<?php
// public/driver/check_status.php
require_once(__DIR__ . '/../../backend/app/config/database.php');

$plate = $_GET['plate'] ?? '';

$db = new DatabaseConnection();
$pdo = $db->pdo;

/// Check the most recent record for this plate, regardless of exit status
$stmt = $pdo->prepare(
    "SELECT payment_status 
     FROM vehicle_logs 
     WHERE plate_number = :plate
     ORDER BY id DESC
     LIMIT 1"
);

$stmt->execute([':plate' => $plate]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

// Return payment status
if ($result) {
    echo json_encode(['status' => $result['payment_status']]);
} else {
    echo json_encode(['status' => 'pending']);
}