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
            file_put_contents(__DIR__ . '/mpesa_errors.txt', "MPESA_MOCK blocked: refusing auto-success for plate $plateNumber" . PHP_EOL, FILE_APPEND);
            return ['error' => 'MPESA_MOCK is disabled for gate authorization. Use real STK callback to complete payment.'];
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
            // fail closed: never auto-mark success without genuine callback
            if ($httpCode >= 500) {
                file_put_contents(__DIR__ . '/mpesa_errors.txt', "Server error $httpCode; refusing simulated success fallback\n", FILE_APPEND);
                return ['error' => "Daraja temporarily unavailable (HTTP $httpCode). Please try again later."];
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
                SET mpesa_checkout_id = :checkout_id,
                    phone_number = :phone,
                    payment_status = 'pending'
                WHERE plate_number = :plate
                AND exit_time IS NULL
            ");
            $stmt->execute([
                ':checkout_id' => $result['CheckoutRequestID'],
                ':phone'       => $formattedPhone,
                ':plate'       => $plateNumber
            ]);

            // Pending row intentionally not inserted here.
            // mpesa_transactions is written only by CallBack.php once payment is confirmed Completed.
        }
        // --- NEW DATABASE LOGIC END ---

        return $result;
    }
}
