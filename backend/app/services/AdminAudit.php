<?php
// backend/app/services/AdminAudit.php

require_once(__DIR__ . '/../config/database.php');

class AdminAudit {
    // ensure table exists and migrate old structure if needed (runs once per request)
    private static function ensureTable(PDO $pdo) {
        // create table with username column; admin_id may still exist in old installs
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS admin_activity (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NULL,
                username VARCHAR(100) NULL,
                action VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // if old column admin_id exists, migrate to username and drop it
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM admin_activity")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('admin_id', $cols)) {
                // ensure username column exists
                if (!in_array('username', $cols)) {
                    $pdo->exec("ALTER TABLE admin_activity ADD COLUMN username VARCHAR(100) NULL");
                }
                // copy names from administrators table
                $pdo->exec(
                    "UPDATE admin_activity a
                     JOIN administrators ad ON ad.id = a.admin_id
                     SET a.username = ad.username"
                );
                // mark column nullable but drop afterwards
                $pdo->exec("ALTER TABLE admin_activity DROP COLUMN admin_id");
            }
        } catch (Exception $e) {
            // ignore migration errors
        }
    }

    // log an action for given admin username
    public static function log(PDO $pdo, string $adminUsername, string $action) {
        try {
            self::ensureTable($pdo);
            $stmt = $pdo->prepare(
                "INSERT INTO admin_activity (username, action, ip_address)
                 VALUES (:uname, :act, :ip)"
            );
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $stmt->execute([
                ':uname' => $adminUsername,
                ':act'   => $action,
                ':ip'    => $ip
            ]);

            // trim table to most recent 500 entries globally
            try {
                $pdo->exec(
                    "DELETE FROM admin_activity
                     WHERE id NOT IN (
                         SELECT id FROM (
                             SELECT id FROM admin_activity
                             ORDER BY created_at DESC
                             LIMIT 500
                         ) tmp
                     )"
                );
            } catch (Exception $e) {
                // safe to ignore cleanup failure
            }
        } catch (Exception $e) {
            // if logging fails we intentionally do not block the user
            error_log("Audit log failure: " . $e->getMessage());
        }
    }
}
