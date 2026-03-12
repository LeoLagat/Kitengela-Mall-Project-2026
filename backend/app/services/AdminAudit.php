<?php
// backend/app/services/AdminAudit.php

require_once(__DIR__ . '/../config/database.php');

class AdminAudit {
    // ensure table exists (runs once per request)
    private static function ensureTable(PDO $pdo) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS admin_activity (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NOT NULL,
                action VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (admin_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    // log an action for given admin id
    public static function log(PDO $pdo, int $adminId, string $action) {
        try {
            self::ensureTable($pdo);
            $stmt = $pdo->prepare(
                "INSERT INTO admin_activity (admin_id, action, ip_address)
                 VALUES (:aid, :act, :ip)"
            );
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $stmt->execute([
                ':aid' => $adminId,
                ':act' => $action,
                ':ip'  => $ip
            ]);
        } catch (Exception $e) {
            // if logging fails we intentionally do not block the user
            error_log("Audit log failure: " . $e->getMessage());
        }
    }
}
