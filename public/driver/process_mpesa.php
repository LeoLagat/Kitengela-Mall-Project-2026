<?php
// public/driver/process_mpesa.php
require_once(__DIR__ . '/../../backend/app/services/MpesaService.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phone = $_POST['phone_number'];
    $amount = $_POST['amount'];
    $plate = $_POST['plate'];

    // Convert phone format: 07... to 2547...
    if (str_starts_with($phone, "0")) {
        $phone = "254" . substr($phone, 1);
    }

    // ensure the fee is recorded in the log so our SSE loop,
    // revenue reports and simulator have a value to work with
    try {
        $db = new DatabaseConnection();
        $pdo = $db->pdo;
        $upd = $pdo->prepare(
            "UPDATE vehicle_logs SET total_fee = :amt WHERE plate_number = :plate AND exit_time IS NULL"
        );
        $upd->execute([':amt' => $amount, ':plate' => $plate]);
    } catch (Exception $e) {
        // non‑fatal, just log it
        file_put_contents(__DIR__ . '/../../backend/app/services/mpesa_errors.txt',
            "Failed to write fee to log for $plate: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    }

    $mpesa = new MpesaService();
    // stkPush will now also update the mpesa_checkout_id in your DB
    $response = $mpesa->stkPush($phone, $amount, $plate, "Parking Fee");

    if (is_array($response) && isset($response['ResponseCode']) && $response['ResponseCode'] == "0") {
        // Redirect to the waiting page to poll for payment confirmation
        header("Location: waiting.php?plate=" . urlencode($plate));
        exit();
    } else {
        // Provide more detail about what went wrong
        $msg = '';

        if (is_array($response)) {
            $msg = $response['CustomerMessage'] ?? '';
            if (isset($response['error'])) {
                $msg .= ($msg ? ' - ' : '') . htmlspecialchars($response['error']);
            }
        } else {
            // unexpected return type
            $msg = 'Invalid response from stkPush(): ' . htmlspecialchars(var_export($response, true));
        }

        if (!$msg) {
            $msg = "Could not connect to Safaricom Daraja API. Check network or sandbox settings.";
        }

        echo "<div style='text-align:center; padding:50px; font-family:sans-serif;'>
                <h2 style='color:red;'>Payment Initiation Failed</h2>
                <p>" . $msg . "</p>
                <br><a href='pay.php'>Try Again</a>
              </div>";
    }
}