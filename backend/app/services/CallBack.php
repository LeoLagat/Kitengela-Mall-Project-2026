<?php
// backend/app/services/callback.php

require_once(__DIR__ . '/../config/database.php');

// 1. Get the raw JSON data
$mpesaResponse = file_get_contents('php://input');
$logFile = __DIR__ . "/mpesa_response.txt";
file_put_contents($logFile, $mpesaResponse . PHP_EOL, FILE_APPEND);

// Decode JSON safely
$data = json_decode($mpesaResponse, true);

if (!$data) {
    http_response_code(400);
    die("No data received");
}

// Extract basic callback info safely
$stkCallback = $data['Body']['stkCallback'] ?? null;

if (!$stkCallback) {
    http_response_code(400);
    die("Invalid callback structure");
}

$resultCode = $stkCallback['ResultCode'] ?? null;
$checkoutRequestID = $stkCallback['CheckoutRequestID'] ?? null;
$resultDesc = $stkCallback['ResultDesc'] ?? '';

$db = new DatabaseConnection();
$pdo = $db->pdo;

// Payment Successful
if ($resultCode === 0) {

    $callbackData = $stkCallback['CallbackMetadata']['Item'] ?? [];

    // Initialize variables
    $amount = 0;
    $phoneNumber = '';
    $transactionDate = null;
    $receiptNumber = ''; // Added variable for receipt

    foreach ($callbackData as $item) {
        if ($item['Name'] == 'Amount') $amount = $item['Value'];
        if ($item['Name'] == 'PhoneNumber') $phoneNumber = $item['Value'];
        if ($item['Name'] == 'TransactionDate') $transactionDate = $item['Value'];
        if ($item['Name'] == 'MpesaReceiptNumber') $receiptNumber = $item['Value']; // Extract Receipt
    }

    // Convert MPESA TransactionDate to MySQL DATETIME
    if ($transactionDate) {
        $transactionDate = DateTime::createFromFormat('YmdHis', $transactionDate)->format('Y-m-d H:i:s');
    } else {
        $transactionDate = date('Y-m-d H:i:s'); // fallback
    }

    try {
        // 1. Find vehicle log id and bay_id
        // fetch the related vehicle log so we can capture plate_number as well
        $stmtFind = $pdo->prepare("SELECT id AS log_id, bay_id, plate_number FROM vehicle_logs WHERE mpesa_checkout_id = :checkout_id LIMIT 1");
        $stmtFind->execute([':checkout_id' => $checkoutRequestID]);
        $vehicle = $stmtFind->fetch(PDO::FETCH_ASSOC);

        $logId = $vehicle['log_id'] ?? null;
        $bayId = $vehicle['bay_id'] ?? null;
        $plateNumber = $vehicle['plate_number'] ?? null;

        if (!$logId) {
            file_put_contents(__DIR__ . '/mpesa_errors.txt', "No vehicle log found for checkout_id: $checkoutRequestID" . PHP_EOL, FILE_APPEND);
            http_response_code(200);
            echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Vehicle log not found']);
            exit;
        }

        // Idempotency check: if this checkout was already marked completed with a receipt,
        // do not process it again.
        $stmtCheck = $pdo->prepare("\
            SELECT id
            FROM mpesa_transactions
            WHERE checkout_id = ?
              AND status = 'Completed'
              AND receipt_number IS NOT NULL
              AND receipt_number <> ''
            LIMIT 1
        ");
        $stmtCheck->execute([$checkoutRequestID]);
        if ($stmtCheck->fetchColumn()) {
            http_response_code(200);
            echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Transaction already processed']);
            exit;
        }

        $pdo->beginTransaction();

        // Update the existing mpesa_transactions row with receipt_number and status
        $stmtMpesa = $pdo->prepare("
            UPDATE mpesa_transactions 
            SET receipt_number = ?, amount = ?, status = 'Completed' 
            WHERE checkout_id = ? AND status = 'Pending'
        ");
        $stmtMpesa->execute([$receiptNumber, $amount, $checkoutRequestID]);

        if ($stmtMpesa->rowCount() === 0) {
            // No pending transaction found, insert new
            $stmtInsert = $pdo->prepare("
                INSERT INTO mpesa_transactions 
                (log_id, plate_number, phone_number, amount, checkout_id, receipt_number, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'Completed', NOW())
            ");
            $stmtInsert->execute([$logId, $plateNumber, $phoneNumber, $amount, $checkoutRequestID, $receiptNumber]);
            file_put_contents(__DIR__ . '/mpesa_errors.txt', "Inserted new mpesa_transaction for callback: $checkoutRequestID" . PHP_EOL, FILE_APPEND);
        }

        // 3. Update vehicle_logs 
        // We remove the specific 'payment_status' check in the WHERE clause to ensure 
        // it updates correctly even if the status was 'per', 'parked', or 'pending'.
       // ... (existing code to extract $amount and $logId)

// 3. Update vehicle_logs 
// We include total_fee = :amount to ensure revenue is recorded in the log
    // Inside CallBack.php (Successful Payment Section)
    // update log to paid and stamp exit time with DB clock
    $stmtVehicle = $pdo->prepare("
    UPDATE vehicle_logs 
    SET payment_status = 'paid',
        exit_time = NOW(),
        total_fee = :amount  
    WHERE mpesa_checkout_id = :checkout_id
     ");
     $stmtVehicle->execute([
    ':amount' => $amount, 
    ':checkout_id' => $checkoutRequestID
    ]);


        // 4. Update parking_bays: Set bay to vacant
        if ($bayId) {
            $stmtBay = $pdo->prepare("
                UPDATE parking_bays 
                SET current_status = 'vacant' 
                WHERE id = :bay_id
            ");
            $stmtBay->execute([':bay_id' => $bayId]);
        }

        $pdo->commit();

        http_response_code(200);
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        file_put_contents(__DIR__ . '/mpesa_errors.txt', "DB Error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        http_response_code(500);
        echo "Database Error";
    }

} else {
        // Payment failed or cancelled
        file_put_contents(__DIR__ . '/mpesa_errors.txt', "Payment Failed: $resultDesc" . PHP_EOL, FILE_APPEND);

        try {
            $stmt = $pdo->prepare("\
                    UPDATE vehicle_logs
                    SET payment_status = 'failed',
                        total_fee = COALESCE(total_fee, 0)
                    WHERE mpesa_checkout_id = :checkout_id
            ");
            $stmt->execute([':checkout_id' => $checkoutRequestID]);

            // defensive cleanup – free the bay if the log exists
            $stmtBay = $pdo->prepare("\
                    UPDATE parking_bays pb
                    JOIN vehicle_logs vl ON vl.bay_id = pb.id
                    SET pb.current_status = 'vacant'
                    WHERE vl.mpesa_checkout_id = :checkout_id
            ");
            $stmtBay->execute([':checkout_id' => $checkoutRequestID]);

            http_response_code(200);
            echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Payment Failed Logged']);
        } catch (Exception $e) {
            file_put_contents(__DIR__ . '/mpesa_errors.txt', "DB Error on Failure: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            http_response_code(500);
            echo "Database Error on Failure";
        }
    }

?>