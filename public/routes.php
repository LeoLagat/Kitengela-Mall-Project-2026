<?php

// Load required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/GateController.php';
require_once __DIR__ . '/../models/Vehicle.php';
require_once __DIR__ . '/../services/MpesaService.php';

// Instantiate controller
$gateController = new GateController();

// Get route from URL
$route = $_GET['route'] ?? 'home';

// Basic routing
switch ($route) {

    // Home
    case 'home':
        require_once __DIR__ . '/index.php';
        break;

    // Gate Entry
    case 'entry':
        require_once __DIR__ . '/gate/entry.php';
        break;

    // Gate Exit
    case 'exit':
        require_once __DIR__ . '/gate/exit.php';
        break;

    // Driver Payment Page
    case 'pay':
        require_once __DIR__ . '/driver/pay.php';
        break;

    // Admin Dashboard
    case 'dashboard':
        require_once __DIR__ . '/admin/dashboard.php';
        break;

    // Handle Entry Logic (Controller)
    case 'store-entry':
        $gateController->storeEntry();
        break;

    // Handle Exit Logic (Controller)
    case 'store-exit':
        $gateController->storeExit();
        break;

    // Default (404)

    default:
        echo "<h2>404 - Page Not Found</h2>";
        break;
}