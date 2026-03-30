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
require_once(__DIR__ . '/AdminAudit.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['plate'])) {
    $plate = strtoupper(trim($_POST['plate']));
    $db = new DatabaseConnection();
    $pdo = $db->pdo;

    try {
        $pdo->beginTransaction();

        // 1. Search for ANY active vehicle log for this plate (regardless of payment status)
        $stmtFind = $pdo->prepare("SELECT id, bay_id, payment_status, entry_time FROM vehicle_logs WHERE plate_number = :plate AND exit_time IS NULL LIMIT 1");
        $stmtFind->execute([':plate' => $plate]);
        $vehicle = $stmtFind->fetch(PDO::FETCH_ASSOC);

        if ($vehicle) {
            // Vehicle found and active - process the exit
            $vehicleId = $vehicle['id'];
            $bayId = $vehicle['bay_id'];
            $oldStatus = $vehicle['payment_status'];
            $bypassedBy = $_SESSION['admin_username'] ?? null;

            // 2. Update vehicle_logs: Mark as paid AND exited (even if payment was pending/incomplete)
            $stmtLog = $pdo->prepare("
                UPDATE vehicle_logs 
                SET payment_status = 'paid',
                    exit_time = NOW(),
                    is_manual_bypass = 1,
                    bypassed_by = :bypassed_by,
                    bypassed_at = NOW()
                WHERE id = :id
            ");
            $stmtLog->execute([
                ':id' => $vehicleId,
                ':bypassed_by' => $bypassedBy
            ]);

            // 3. Update the parking bay status to 'vacant'
            $stmtBay = $pdo->prepare("
                UPDATE parking_bays 
                SET current_status = 'vacant' 
                WHERE id = :bay_id
            ");
            $stmtBay->execute([':bay_id' => $bayId]);

            // 4. Log this bypass action with previous status
            if (!empty($_SESSION['admin_username'])) {
                $auditMsg = "executed manual bypass for vehicle $plate (bay $bayId) | previous status: $oldStatus";
                AdminAudit::log($pdo, $_SESSION['admin_username'], $auditMsg);
            }

            $pdo->commit();
            echo json_encode([
                'status' => 'success', 
                'message' => "✓ Gate forced open for $plate. Bay $bayId is now vacant. Reason: manual admin override for controlled exit. (Previous payment status: $oldStatus)"
            ]);
        } else {
            // Vehicle not found in active logs - try to find ANY record to verify it exists
            $stmtCheck = $pdo->prepare("SELECT id, bay_id FROM vehicle_logs WHERE plate_number = :plate ORDER BY entry_time DESC LIMIT 1");
            $stmtCheck->execute([':plate' => $plate]);
            $existingRecord = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            if ($existingRecord) {
                // Vehicle exists in system but log shows it already exited
                // Force open the gate anyway (for recovery/override scenarios)
                $stmtMark = $pdo->prepare(
                    "UPDATE vehicle_logs
                     SET is_manual_bypass = 1,
                         bypassed_by = :bypassed_by,
                         bypassed_at = NOW()
                     WHERE id = :id"
                );
                $stmtMark->execute([
                    ':id' => $existingRecord['id'],
                    ':bypassed_by' => $_SESSION['admin_username'] ?? null
                ]);

                if (!empty($_SESSION['admin_username'])) {
                    AdminAudit::log($pdo, $_SESSION['admin_username'], "executed emergency bypass for vehicle $plate (vehicle already exited but gate forced open)");
                }
                if ($pdo->inTransaction()) {
                    $pdo->commit();
                }
                echo json_encode([
                    'status' => 'success', 
                    'message' => "✓ EMERGENCY OVERRIDE: Gate forced open for $plate. Reason: vehicle record was already closed, so authorized emergency override was applied."
                ]);
            } else {
                // Vehicle doesn't exist in system at all - still allow bypass
                // This handles cases where vehicle entry wasn't logged or new vehicles need emergency exit
                if (!empty($_SESSION['admin_username'])) {
                    AdminAudit::log($pdo, $_SESSION['admin_username'], "executed bypass for unknown vehicle $plate (not in system - emergency access)");
                }
                if ($pdo->inTransaction()) {
                    $pdo->commit();
                }
                echo json_encode([
                    'status' => 'success', 
                    'message' => "✓ EMERGENCY ACCESS: Gate forced open for $plate. Reason: no active system record was found, so authorized emergency access was granted."
                ]);
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => "Database Error: " . $e->getMessage()]);
    }
}
?>