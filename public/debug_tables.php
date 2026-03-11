<?php
require_once(__DIR__ . '/../backend/app/config/database.php');
$db = new DatabaseConnection();
$pdo = $db->pdo;

// attempt to create owner_accounts in case migration failed earlier
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS owner_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        plate_number VARCHAR(20) UNIQUE NOT NULL,
        owner_name VARCHAR(100) NULL,
        invoice_monthly BOOLEAN DEFAULT TRUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "owner_accounts creation attempted\n";
} catch (PDOException $e) {
    echo "creation error: " . $e->getMessage() . "\n";
}

$rows = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "Tables:\n";
print_r($rows);

// dump owner_accounts content
try {
    $owners = $pdo->query('SELECT * FROM owner_accounts')->fetchAll(PDO::FETCH_ASSOC);
    echo "\nowners table:\n";
    print_r($owners);
} catch (Exception $e) {
    echo "could not read owner_accounts: " . $e->getMessage() . "\n";
}

// dump latest vehicle_logs entries that were invoiced
try {
    $rows2 = $pdo->query('SELECT * FROM vehicle_logs WHERE payment_status IN ("invoiced","paid") ORDER BY id DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
    echo "\nrecent vehicle_logs:\n";
    print_r($rows2);
} catch (Exception $e) {
    echo "could not read vehicle_logs: " . $e->getMessage() . "\n";
}

// dump recent mpesa transactions
try {
    $txs = $pdo->query('SELECT * FROM mpesa_transactions ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
    echo "\nmpesa_transactions:\n";
    print_r($txs);
} catch (Exception $e) {
    echo "could not read mpesa_transactions: " . $e->getMessage() . "\n";
}

// show table structure to diagnose missing columns
try {
    $cols = $pdo->query('SHOW COLUMNS FROM mpesa_transactions')->fetchAll(PDO::FETCH_ASSOC);
    echo "\nmpesa_transactions columns:\n";
    print_r($cols);
} catch (Exception $e) {
    echo "could not describe mpesa_transactions: " . $e->getMessage() . "\n";
}

// show response log tail
$logfile = __DIR__ . '/../backend/app/services/mpesa_response.txt';
if (file_exists($logfile)) {
    echo "\nmpesa_response.txt tail:\n";
    echo shell_exec("tail -n 20 " . escapeshellarg($logfile));
}
