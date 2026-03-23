<?php
// Manual simulation endpoint disabled by default for safety.
session_start();
header('Content-Type: application/json');

$simEnabled = getenv('MPESA_ENABLE_SIMULATE_ENDPOINT') === '1';
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if (!$simEnabled || !$isAdmin) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'Simulation endpoint is disabled.'
    ]);
    exit;
}

echo json_encode([
    'ok' => false,
    'error' => 'Simulation endpoint is locked. Use real callback flow for payment confirmation.'
]);
