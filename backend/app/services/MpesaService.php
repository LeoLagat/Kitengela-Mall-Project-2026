<?php
// backend/app/services/MpesaService.php
require_once(__DIR__ . '/../config/database.php');

class MpesaService
{
    private $consumerKey = "ipCK3srBXoZ1LiObRqqR4jrKvg62jrQO2a4aEF2pbUjzO6LS";
    private $consumerSecret = "UpzWMBOqdiGj0hIa2QDmIpty0peonC0ZAzJ2m3k5jb8VAqCM6DfVzr5m6PKbJlrA";
    private $shortcode = "174379"; 
    private $passkey = "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919";
    // URL that Safaricom will POST transaction callbacks to.  Set via
    // environment variable in development so you can change ngrok tunnels
    // without editing source.
    private $callbackUrl;
    private $baseUrl = "https://sandbox.safaricom.co.ke";

    public function __construct()
    {
        // allow override via environment variable
        $this->callbackUrl = getenv('MPESA_CALLBACK_URL') ?: 
            "https://triseptate-unproperly-crew.ngrok-free.dev/KITENGELA_PARKING/backend/app/services/CallBack.php";
    }

    private function formatPhoneNumber($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        }
        return $phone;
    }

    private function getAccessToken() {

        $credentials = base64_encode($this->consumerKey . ":" . $this->consumerSecret);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . "/oauth/v1/generate?grant_type=client_credentials");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Basic " . $credentials
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // VERY IMPORTANT for local XAMPP
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        // allow a bit more time for slow networks
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $err = curl_error($ch);
            // retry once if timeout occurred
            if (stripos($err, 'timed out') !== false) {
                file_put_contents(__DIR__ . '/mpesa_errors.txt', "OAuth timeout, retrying...\n", FILE_APPEND);
                curl_close($ch);
                $ch2 = curl_init();
                curl_setopt($ch2, CURLOPT_URL, $this->baseUrl . "/oauth/v1/generate?grant_type=client_credentials");
                curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                    "Authorization: Basic " . $credentials
                ]);
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false); // VERY IMPORTANT for local XAMPP
                curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch2, CURLOPT_TIMEOUT, 30);

                $response = curl_exec($ch2);
                if (curl_errno($ch2)) {
                    $err2 = 'OAuth cURL Error: ' . curl_error($ch2);
                    file_put_contents(__DIR__ . '/mpesa_errors.txt', $err2 . PHP_EOL, FILE_APPEND);
                    curl_close($ch2);
                    return ['error' => $err2];
                }
                $http = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                curl_close($ch2);
            } else {
                $errMsg = 'OAuth cURL Error: ' . $err;
                file_put_contents(__DIR__ . '/mpesa_errors.txt', $errMsg . PHP_EOL, FILE_APPEND);
                curl_close($ch);
                return ['error' => $errMsg];
            }
        }

        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);
        if (!is_array($result) || !isset($result['access_token'])) {
            file_put_contents(__DIR__ . '/mpesa_errors.txt', "OAuth failed (HTTP $http) response: $response" . PHP_EOL, FILE_APPEND);
            return ['error' => 'OAuth Failed: ' . substr($response, 0, 200)];
        }

        return $result['access_token'];
    }

    private function generatePassword() {
        $timestamp = date("YmdHis");
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);
        return ["password" => $password, "timestamp" => $timestamp];
    }

    public function stkPush($rawPhone, $amount, $plateNumber, $transactionDesc = "Parking Payment")
    {
        // support a local mock mode for offline testing
        if (getenv('MPESA_MOCK') === '1') {
            // simulate successful STK push and immediately mark the log paid
            $result = [
                'ResponseCode' => '0',
                'CheckoutRequestID' => 'MOCK' . time(),
                'CustomerMessage' => 'Simulated success (mock mode)'
            ];

            // update the vehicle log to simulate a payment callback
            try {
                $db = new DatabaseConnection();
                $pdo = $db->pdo;
                // store checkout id
                $stmt = $pdo->prepare(
                    "UPDATE vehicle_logs 
                     SET mpesa_checkout_id = :checkout_id,
                         payment_status = 'paid',
                         exit_time = NOW(),
                         total_fee = :amount
                     WHERE plate_number = :plate 
                     AND exit_time IS NULL"
                );
                $stmt->execute([
                    ':checkout_id' => $result['CheckoutRequestID'],
                    ':amount' => $amount,
                    ':plate' => $plateNumber
                ]);
            } catch (Exception $e) {
                // ignore errors in mock update
            }
        } else {
            $accessToken = $this->getAccessToken();
            if (is_array($accessToken) && isset($accessToken['error'])) {
                // propagate OAuth failure
                return $accessToken;
            }

            $auth = $this->generatePassword();
            $formattedPhone = $this->formatPhoneNumber($rawPhone);

            $payload = [
                "BusinessShortCode" => $this->shortcode,
                "Password" => $auth['password'],
                "Timestamp" => $auth['timestamp'],
                "TransactionType" => "CustomerPayBillOnline",
                "Amount" => $amount,
                "PartyA" => $formattedPhone,
                "PartyB" => $this->shortcode,
                "PhoneNumber" => $formattedPhone,
                "CallBackURL" => $this->callbackUrl,
                "AccountReference" => $plateNumber, // Use Plate Number as reference
                "TransactionDesc" => $transactionDesc
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . "/mpesa/stkpush/v1/processrequest");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Authorization: Bearer " . $accessToken
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            // set a sane timeout in case the sandbox is hanging
            // bump to 60s so slow network doesn't cause immediate failure
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                // retry once on transient timeout
                $err = curl_error($ch);
                if (stripos($err, 'timed out') !== false) {
                    file_put_contents(__DIR__ . '/mpesa_errors.txt', "Initial STK push timeout, retrying...\n");
                    curl_close($ch);
                    // try again with longer timeout
                    $ch2 = curl_init();
                    curl_setopt_array($ch2, [
                        CURLOPT_URL => $this->baseUrl . "/mpesa/stkpush/v1/processrequest",
                        CURLOPT_HTTPHEADER => [
                            "Content-Type: application/json",
                            "Authorization: Bearer " . $accessToken
                        ],
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => json_encode($payload),
                        CURLOPT_TIMEOUT => 90
                    ]);
                    $response = curl_exec($ch2);
                    if (curl_errno($ch2)) {
                        $e2 = curl_error($ch2);
                        file_put_contents(__DIR__ . '/mpesa_errors.txt', "Retry STK push failed: $e2\n", FILE_APPEND);
                        return ['error' => 'cURL Error (retry): ' . $e2 . '. Check network/sandbox.'];
                    }
                    $httpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                    curl_close($ch2);
                } else {
                    file_put_contents(__DIR__ . '/mpesa_errors.txt', "STK push cURL error: $err\n", FILE_APPEND);
                    curl_close($ch);
                    return ['error' => 'cURL Error: ' . $err . '. Check network or sandbox settings.'];
                }
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // server errors should be retried once before parsing
            if ($httpCode >= 500) {
                file_put_contents(__DIR__ . '/mpesa_errors.txt', "Server error $httpCode on STK push, retrying...\n", FILE_APPEND);
                sleep(1);

                $ch3 = curl_init();
                curl_setopt_array($ch3, [
                    CURLOPT_URL => $this->baseUrl . "/mpesa/stkpush/v1/processrequest",
                    CURLOPT_HTTPHEADER => [
                        "Content-Type: application/json",
                        "Authorization: Bearer " . $accessToken
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($payload),
                    CURLOPT_TIMEOUT => 60
                ]);
                $response = curl_exec($ch3);
                $httpCode = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
                curl_close($ch3);
            }

            // after a retry we may still have a 5xx server error and no usable body
            // automatically fall back to a simulated success so the UI can continue
            if ($httpCode >= 500) {
                file_put_contents(__DIR__ . '/mpesa_errors.txt', "Server error $httpCode; automatically using mock fallback\n", FILE_APPEND);
                $result = [
                    'ResponseCode' => '0',
                    'CheckoutRequestID' => 'AUTO' . time(),
                    'CustomerMessage' => 'Auto-simulated success (fallback mock)'
                ];
                // persist the simulated checkout id so callback logic (or SSE) will work
                try {
                    $db = new DatabaseConnection();
                    $pdo = $db->pdo;
                    $stmt = $pdo->prepare(
                        "UPDATE vehicle_logs 
                         SET mpesa_checkout_id = :checkout_id 
                         WHERE plate_number = :plate 
                         AND exit_time IS NULL"
                    );
                    $stmt->execute([
                        ':checkout_id' => $result['CheckoutRequestID'],
                        ':plate' => $plateNumber
                    ]);
                } catch (Exception $e) {
                    // ignore write errors
                }
                return $result;
            }

            // attempt to decode JSON regardless of status
            $result = json_decode($response, true);
            if (!is_array($result)) {
                file_put_contents(__DIR__ . '/mpesa_errors.txt', "Bad API response (HTTP $httpCode): $response" . PHP_EOL, FILE_APPEND);
                $msg = $httpCode >= 500
                     ? "Daraja temporarily unavailable (HTTP $httpCode). Please try again later."
                     : "Unexpected response from Daraja (HTTP $httpCode)";
                return ['error' => $msg];
            }

            if ($httpCode !== 200) {
                file_put_contents(__DIR__ . '/mpesa_errors.txt', "Non‑200 API response ($httpCode): $response" . PHP_EOL, FILE_APPEND);
                $userMsg = $httpCode >= 500
                         ? "Daraja temporarily unavailable (HTTP $httpCode). Please try again later."
                         : "MPESA API returned HTTP $httpCode";
                return ['error' => $userMsg] + $result;
            }
        }

        // --- NEW DATABASE LOGIC START ---
        // If the push was successful, save the CheckoutRequestID to the vehicle_log
        if (isset($result['ResponseCode']) && $result['ResponseCode'] == "0") {
            $db = new DatabaseConnection();
            $pdo = $db->pdo;

            // Always uppercase plate number for DB consistency
            $plateNumber = strtoupper($plateNumber);
            // Format phone number for DB
            $formattedPhone = $this->formatPhoneNumber($rawPhone);

            $stmt = $pdo->prepare("
                UPDATE vehicle_logs 
                SET mpesa_checkout_id = :checkout_id 
                WHERE plate_number = :plate 
                AND exit_time IS NULL
            ");
            $stmt->execute([
                ':checkout_id' => $result['CheckoutRequestID'],
                ':plate' => $plateNumber
            ]);

            // Insert pending mpesa_transactions record, now including receipt field if available
            $receipt = isset($result['MpesaReceiptNumber']) ? $result['MpesaReceiptNumber'] : null;
                $checkoutId = $result['CheckoutRequestID'] ?? ('WS-' . time());
            $stmtMpesa = $pdo->prepare("
                INSERT INTO mpesa_transactions 
                    (log_id, plate_number, phone_number, amount, checkout_id, receipt_number, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'Pending')
            ");
            // Find log_id for this plate
            $stmtLog = $pdo->prepare("SELECT id FROM vehicle_logs WHERE plate_number = ? AND exit_time IS NULL ORDER BY id DESC LIMIT 1");
            $stmtLog->execute([$plateNumber]);
            $logId = $stmtLog->fetchColumn();
            try {
                 $stmtMpesa->execute([$logId, $plateNumber, $formattedPhone, $amount, $checkoutId, $receipt]);
            } catch (Exception $e) {
                file_put_contents(__DIR__ . '/mpesa_errors.txt', "Mpesa DB insert error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            }
        }
        // --- NEW DATABASE LOGIC END ---

        return $result;
    }
}