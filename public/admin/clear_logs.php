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
    echo json_encode(['status' => 'error', 'message' => 'Only super admins can clear logs']);
    exit;
}

require_once(__DIR__ . '/../../backend/app/config/database.php');
require_once(__DIR__ . '/../../backend/app/services/AdminAudit.php');

$db = new DatabaseConnection();
$pdo = $db->pdo;

try {
    $pdo->beginTransaction();

    // 1. Calculate current revenue to archive
    $stmtRevenue = $pdo->prepare("
        SELECT COALESCE(SUM(total_fee), 0) as total_revenue, COUNT(*) as log_count
        FROM vehicle_logs
        WHERE payment_status = 'paid'
    ");
    $stmtRevenue->execute();
    $revenueData = $stmtRevenue->fetch(PDO::FETCH_ASSOC);
    $currentRevenue = (float)$revenueData['total_revenue'];
    $logCount = (int)$revenueData['log_count'];

    // 2. Archive the revenue before clearing logs
    $stmtArchive = $pdo->prepare("
        INSERT INTO revenue_archive (archived_revenue, admin_who_cleared, log_count_cleared)
        VALUES (?, ?, ?)
    ");
    $stmtArchive->execute([$currentRevenue, $_SESSION['admin_username'] ?? 'unknown', $logCount]);

    // 3. Clear the vehicle_logs table
    $stmtClear = $pdo->prepare("TRUNCATE TABLE vehicle_logs");
    $stmtClear->execute();

    // 4. Record the action
    if (!empty($_SESSION['admin_username'])) {
        $auditMsg = "cleared vehicle logs database (archived revenue: Ksh " . number_format($currentRevenue, 2) . " from $logCount records)";
        AdminAudit::log($pdo, $_SESSION['admin_username'], $auditMsg);
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => "✓ Vehicle logs cleared successfully! Revenue (Ksh " . number_format($currentRevenue, 2) . ") has been archived and is preserved."
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to clear logs: ' . $e->getMessage()
    ]);
}
?>
