<?php
session_start();

// Ensure only super_admin can access this
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if (empty($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Only super admins can delete vehicles']);
    exit;
}

require_once(__DIR__ . '/../../backend/app/config/database.php');
require_once(__DIR__ . '/../../backend/app/services/AdminAudit.php');

$db = new DatabaseConnection();
$pdo = $db->pdo;

$action = $_POST['action'] ?? null;
$plate = strtoupper(trim($_POST['plate'] ?? ''));

if (!$plate) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Plate number required']);
    exit;
}

try {
    if ($action === 'soft_delete') {
        // Move to recycle bin (soft delete)
        $stmt = $pdo->prepare("UPDATE owner_accounts SET deleted_at = NOW() WHERE plate_number = ? AND deleted_at IS NULL");
        $result = $stmt->execute([$plate]);
        
        if ($stmt->rowCount() > 0) {
            AdminAudit::log($pdo, $_SESSION['admin_username'], "moved vehicle $plate to recycle bin");
            echo json_encode([
                'status' => 'success',
                'message' => "Vehicle $plate moved to recycle bin"
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Vehicle not found or already deleted'
            ]);
        }
    } 
    elseif ($action === 'permanent_delete') {
        // Permanently delete from recycle bin
        $stmt = $pdo->prepare("DELETE FROM owner_accounts WHERE plate_number = ? AND deleted_at IS NOT NULL");
        $result = $stmt->execute([$plate]);
        
        if ($stmt->rowCount() > 0) {
            AdminAudit::log($pdo, $_SESSION['admin_username'], "permanently deleted vehicle $plate from recycle bin");
            echo json_encode([
                'status' => 'success',
                'message' => "Vehicle $plate permanently deleted"
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Vehicle not found in recycle bin'
            ]);
        }
    }
    elseif ($action === 'restore') {
        // Restore from recycle bin
        $stmt = $pdo->prepare("UPDATE owner_accounts SET deleted_at = NULL WHERE plate_number = ? AND deleted_at IS NOT NULL");
        $result = $stmt->execute([$plate]);
        
        if ($stmt->rowCount() > 0) {
            AdminAudit::log($pdo, $_SESSION['admin_username'], "restored vehicle $plate from recycle bin");
            echo json_encode([
                'status' => 'success',
                'message' => "Vehicle $plate restored"
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Vehicle not found or not in recycle bin'
            ]);
        }
    }
    else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error processing request: ' . $e->getMessage()
    ]);
}
?>
