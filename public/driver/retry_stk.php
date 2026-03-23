<?php
require_once(__DIR__ . '/../../backend/app/config/database.php');
require_once(__DIR__ . '/../../backend/app/services/MpesaService.php');

session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$plate = strtoupper(trim($data['plate'] ?? ''));

if ($plate === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Plate number is required']);
    exit;
}

if (!isset($_SESSION['stk_retry_last'])) {
    $_SESSION['stk_retry_last'] = [];
}

$cooldownSeconds = 30;
$lastRetry = $_SESSION['stk_retry_last'][$plate] ?? 0;
$now = time();

if (($now - $lastRetry) < $cooldownSeconds) {
    $remaining = $cooldownSeconds - ($now - $lastRetry);
    http_response_code(429);
    echo json_encode([
        'ok' => false,
        'error' => 'Please wait before retrying STK.',
        'cooldown_seconds' => $remaining
    ]);
    exit;
}

try {
    $db = new DatabaseConnection();
    $pdo = $db->pdo;

    $stmt = $pdo->prepare("\
        SELECT id, total_fee, phone_number, payment_status
        FROM vehicle_logs
        WHERE plate_number = :plate
          AND exit_time IS NULL
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([':plate' => $plate]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$log) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Active parking session not found for this plate.']);
        exit;
    }

    if (($log['payment_status'] ?? '') === 'paid') {
        echo json_encode(['ok' => false, 'error' => 'Payment already completed.']);
        exit;
    }

    $amount = (float)($log['total_fee'] ?? 0);
    $phone = trim((string)($log['phone_number'] ?? ''));

    if ($amount <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Payment amount is not ready. Please return to exit and start again.']);
        exit;
    }

    if ($phone === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Phone number missing. Please return to exit and enter your number again.']);
        exit;
    }

    $mpesa = new MpesaService();
    $response = $mpesa->stkPush($phone, $amount, $plate, 'Parking Fee Retry');

    if (!is_array($response) || ($response['ResponseCode'] ?? null) !== '0') {
        $message = is_array($response)
            ? ($response['error'] ?? ($response['CustomerMessage'] ?? 'STK retry failed'))
            : 'STK retry failed';

        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => $message]);
        exit;
    }

    $_SESSION['stk_retry_last'][$plate] = $now;

    echo json_encode([
        'ok' => true,
        'message' => 'STK prompt sent. Complete payment on your phone.',
        'cooldown_seconds' => $cooldownSeconds
    ]);
} catch (Exception $e) {
    file_put_contents(
        __DIR__ . '/../../backend/app/services/mpesa_errors.txt',
        'Retry STK error for ' . $plate . ': ' . $e->getMessage() . PHP_EOL,
        FILE_APPEND
    );
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error while retrying STK.']);
}
