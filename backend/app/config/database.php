<?php
// Centralized DB connection for Kitengela Mall
// ensures PDO and timezone consistency
if (!class_exists('DatabaseConnection')) {
class DatabaseConnection {
    private $host = "localhost";
    private $user = "root";
    private $pass = "";
    private $dbname = "kitengela_mall_db";
    public $pdo;

    public function __construct() {
        // PHP timezone should be set once globally
        date_default_timezone_set('Africa/Nairobi');

        try {
            $this->pdo = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname}",
                $this->user,
                $this->pass
            );

            // Set error mode to exception for better debugging
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // ensure MySQL uses Nairobi timezone as well
            $this->pdo->exec("SET time_zone = '+03:00'");

            // --- automatic lightweight migration ---
            // add mpesa_checkout_id to vehicle_logs if missing (allows payments to record checkout IDs)
            try {
                $this->pdo->exec("ALTER TABLE vehicle_logs ADD COLUMN mpesa_checkout_id VARCHAR(255) NULL");
            } catch (PDOException $ignore) {
                // column already exists or table missing; ignore
            }

            // add useful indexes for quick lookup during polling
            try {
                $this->pdo->exec("CREATE INDEX idx_plate_number ON vehicle_logs(plate_number)");
            } catch (PDOException $ignore) {
                // index may already exist
            }
            try {
                $this->pdo->exec("CREATE INDEX idx_plate_payment ON vehicle_logs(plate_number,payment_status)");
            } catch (PDOException $ignore) {
            }

            // ensure a trigger exists to free a bay when a log is closed
            try {
                $this->pdo->exec("DROP TRIGGER IF EXISTS free_bay_after_exit");
                $this->pdo->exec("CREATE TRIGGER free_bay_after_exit\
                    AFTER UPDATE ON vehicle_logs\
                    FOR EACH ROW\
                    BEGIN\
                        IF NEW.exit_time IS NOT NULL AND OLD.exit_time IS NULL THEN\
                            UPDATE parking_bays\
                               SET current_status = 'vacant'\
                             WHERE id = NEW.bay_id;\
                        END IF;\
                    END");
            } catch (PDOException $ignore) {
                // if trigger creation fails (e.g. permissions), ignore silently
            }

            // normalize bay statuses in case data drifted (runs on every connection)
            try {
                $this->pdo->exec("\
                    UPDATE parking_bays pb\
                    LEFT JOIN vehicle_logs vl ON vl.bay_id = pb.id AND vl.exit_time IS NULL\
                    SET pb.current_status = \
                        CASE WHEN vl.id IS NULL THEN 'vacant' ELSE 'occupied' END");
            } catch (PDOException $ignore) {
                // not critical if normalization fails
            }

            // ensure staff vehicle table exists for free parking
            try {
                $this->pdo->exec("\
                    CREATE TABLE IF NOT EXISTS staff_vehicles (\
                        id INT AUTO_INCREMENT PRIMARY KEY,\
                        plate_number VARCHAR(20) UNIQUE NOT NULL,\
                        employee_name VARCHAR(100) NULL,\
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP\
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch (PDOException $ignore) {
                // ignore if creation fails
            }

            // ensure owner accounts table exists for invoiced business owners
            try {
                $this->pdo->exec("\
                    CREATE TABLE IF NOT EXISTS owner_accounts (\
                        id INT AUTO_INCREMENT PRIMARY KEY,\
                        plate_number VARCHAR(20) UNIQUE NOT NULL,\
                        owner_name VARCHAR(100) NULL,\
                        invoice_monthly BOOLEAN DEFAULT TRUE,\
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP\
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch (PDOException $e) {
                file_put_contents(__DIR__ . '/mpesa_errors.txt', "Migration error owner_accounts: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            }

            // ensure restricted list exists for barred plates
            try {
                $this->pdo->exec("\
                    CREATE TABLE IF NOT EXISTS restricted_vehicles (\
                        id INT AUTO_INCREMENT PRIMARY KEY,\
                        plate_number VARCHAR(20) UNIQUE NOT NULL,\
                        reason VARCHAR(255) NULL,\
                        added_at DATETIME DEFAULT CURRENT_TIMESTAMP\
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch (PDOException $e) {
                file_put_contents(__DIR__ . '/mpesa_errors.txt', "Migration error restricted_vehicles: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            }

            // add nominal_fee column to vehicle_logs if missing
            try {
                $this->pdo->exec("ALTER TABLE vehicle_logs ADD COLUMN nominal_fee DECIMAL(10,2) DEFAULT 0");
            } catch (PDOException $ignore) {
            }

            // ensure mpesa_transactions has required columns
            try {
                $this->pdo->exec("ALTER TABLE mpesa_transactions ADD COLUMN log_id INT NULL");
            } catch (PDOException $ignore) {
            }
            try {
                $this->pdo->exec("ALTER TABLE mpesa_transactions ADD COLUMN checkout_id VARCHAR(255) NULL");
            } catch (PDOException $ignore) {
            }
            try {
                $this->pdo->exec("ALTER TABLE mpesa_transactions ADD COLUMN receipt_number VARCHAR(255) NULL");
            } catch (PDOException $ignore) {
            }


        } catch (PDOException $e) {
            die("Database connection failed.");
        }
    }
}
} // end class_exists guard
?>