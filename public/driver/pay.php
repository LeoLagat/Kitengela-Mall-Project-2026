<?php
require_once(__DIR__ . '/../../backend/app/config/database.php');

// Create database connection
$db = new DatabaseConnection();
$pdo = $db->pdo;

// Redirect if accessed directly without submitting the form
if (!isset($_POST['plate'])) {
    header("Location: ../gate/exit.php");
    exit();
}

$plate = htmlspecialchars(strtoupper(trim($_POST['plate'])));
$fee = 0;
$duration_text = "";
$free_exit = false; // indicates zero-fee grace period
$exit_reason = "";

if ($plate) {
    // Always use uppercase for plate numbers
    $plate = strtoupper(trim($plate));
    // Secure query to find the vehicle in the correct table 'vehicle_logs'
    // We check for NULL exit_time to find active sessions
    $stmt = $pdo->prepare(
        "SELECT * FROM vehicle_logs WHERE plate_number = :plate_number AND exit_time IS NULL"
    );
    $stmt->execute([':plate_number' => $plate]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // compute fee using model so staff plates are exempt
        require_once(__DIR__ . '/../../backend/app/models/Vehicle.php');
        $vehicleModel = new Vehicle($pdo);
        $fee = $vehicleModel->calculateFee ($row['entry_time'], $plate);

        // duration for display
        $entry_time = new DateTime($row['entry_time']);  
        $current_time = new DateTime();
        $interval = $entry_time->diff($current_time);
        $hours = $interval->h + ($interval->days * 24);
        $minutes = $interval->i;
        $duration_text = $hours . "h " . $minutes . "m";

        // special handling for free exit or owners
        if ($fee === 0 || $vehicleModel->isOwner($plate)) {
            // owners get invoiced; staff free get paid
            $isOwnerVehicle = $vehicleModel->isOwner($plate);
            $staffInfo      = $vehicleModel->getStaffInfo($plate);
            $isStaffVehicle = ($staffInfo !== false);
            $suspiciousStaff = false;
            if ($isStaffVehicle) {
                // flag if this staff plate already exited once today (possible impersonation)
                $suspiciousStaff = ($vehicleModel->staffExitCountToday($plate) >= 1);
            }
            $status = ($isOwnerVehicle ? 'invoiced' : 'paid');
            $nominal = ($isOwnerVehicle ? $fee : 0);
            $currentDue = ($isOwnerVehicle ? round($fee * 0.7, 2) : 0);

            $totalMinutes = ($hours * 60) + $minutes;
            if ($isOwnerVehicle) {
                $exit_reason = "Owner vehicle: parking charges were moved to owner account (invoiced), so direct gate payment is not required.";
            } elseif ($isStaffVehicle) {
                $exit_reason = "Staff vehicle: this vehicle is registered as a staff member vehicle and receives complimentary parking.";
            } elseif ($totalMinutes <= 30) {
                $exit_reason = "Grace period applied: parking duration was within 30 minutes, so no payment was required.";
            } else {
                $exit_reason = "Fee exemption applied for this vehicle category, so no gate payment was required.";
            }

            $stmt = $pdo->prepare(
                "UPDATE vehicle_logs 
                 SET payment_status = :status,
                     exit_time = NOW(),
                     total_fee = :total_fee,
                     nominal_fee = :nominal
                 WHERE id = :id"
            );
            $stmt->execute([
                ':status' => $status,
                ':total_fee' => $currentDue,
                ':nominal' => $nominal,
                ':id' => $row['id']
            ]);

            // also free up the bay that was assigned
            if (!empty($row['bay_id'])) {
                $upd = $pdo->prepare("UPDATE parking_bays SET current_status='vacant' WHERE id = ?");
                $upd->execute([$row['bay_id']]);
            }

            // audit: log every staff free exit so admins can review unusual patterns
            if ($isStaffVehicle) {
                require_once(__DIR__ . '/../../backend/app/services/AdminAudit.php');
                $employeeName = htmlspecialchars($staffInfo['employee_name'] ?? 'Unknown');
                $auditMsg = "[STAFF EXIT] Plate: $plate | Employee: $employeeName";
                if ($suspiciousStaff) {
                    $auditMsg .= " | ⚠ SUSPICIOUS: this plate already exited today";
                }
                AdminAudit::log($pdo, 'system', $auditMsg);
            }

            // mark for later display
            $free_exit = true;
        }

    } else {
        // Redirect back to exit gate with an error if the car isn't found
        header("Location: ../gate/exit.php?error=notfound");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Gateway - Kitengela Mall</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* small overrides for pay page */
        .fee-display { font-size: 45px; }
        .bill-details p { display: flex; justify-content: space-between; }
        
        /* Optimize payment checkout to fit on screen */
        html, body {
            height: 100%;
            overflow: auto;
        }
        
        body {
            display: flex;
            flex-direction: column;
        }
        
        nav {
            flex-shrink: 0;
        }
        
        .page {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 95%;
            max-width: 600px;
            margin: 10px auto;
            padding: 0;
        }
        
        .card {
            padding: 20px !important;
            width: 100%;
            box-sizing: border-box;
        }
        
        .card h2 {
            font-size: 24px !important;
            margin-bottom: 10px !important;
        }
        
        .card h1 {
            font-size: 20px !important;
            margin-bottom: 8px !important;
        }
        
        .fee-display {
            font-size: 32px !important;
            margin: 10px 0 !important;
            font-weight: 900;
        }
        
        .bill-details {
            margin: 8px 0 !important;
        }
        
        .bill-details p {
            margin: 4px 0 !important;
            font-size: 12px !important;
            display: flex;
            justify-content: space-between;
        }
        
        .bill-details span {
            font-size: 12px !important;
        }
        
        .bill-details strong {
            font-size: 12px !important;
        }
        
        .alert-success {
            padding: 10px 12px !important;
            margin-bottom: 10px !important;
            font-size: 12px !important;
            line-height: 1.3 !important;
        }
        
        input[type="text"],
        input[type="password"],
        input[type="tel"],
        .phone-input {
            padding: 10px !important;
            margin-bottom: 10px !important;
            font-size: 14px !important;
        }
        
        button {
            padding: 10px 15px !important;
            margin-bottom: 8px !important;
            font-size: 14px !important;
        }
        
        .cancel-link {
            font-size: 12px !important;
            margin-top: 5px !important;
        }
        
        footer {
            flex-shrink: 0;
            padding: 8px !important;
            font-size: 11px !important;
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div style="font-size: 20px; font-weight: bold;">Kitengela Mall Parking System</div>
    <div style="font-size: 14px;">Payment Gateway</div>
</nav>

<div class="page">
    <div class="card">
        <?php if (!empty($free_exit)): ?>
            <h2>Thank You!</h2>
            <p><strong>Exit Approved.</strong> Reason: <?php echo htmlspecialchars($exit_reason ?: 'No gate payment was required.'); ?></p>

            <script>
                // Redirect home after 8 seconds without overlay
                setTimeout(function() {
                    window.location.href = '../index.php?welcome=exit';
                }, 8000);
            </script>
        <?php else: ?>
            <h2>Parking Checkout</h2>

            <div class="alert-success">
                <strong>✅ Vehicle Located.</strong><br>
                Please proceed with payment below to clear your outstanding parking balance and open the exit barrier.
            </div>
            
            <div class="fee-display">Ksh <?php echo number_format($fee, 2); ?></div>

            <div class="bill-details">
                <p><span>Vehicle Plate:</span> <strong><?php echo $plate; ?></strong></p>
                <p><span>Duration:</span> <strong><?php echo $duration_text; ?></strong></p>
                <p><span>Billing Rate:</span> <strong>First hour Ksh 50, then Ksh 20 per additional hour (30‑min grace)</strong></p>
            </div>
            
            <form action="process_mpesa.php" method="POST">
        <?php if (!$free_exit): ?>
            <input type="hidden" name="plate" value="<?php echo $plate; ?>">
            <input type="hidden" name="amount" value="<?php echo $fee; ?>">
            
            <input type="text" name="phone_number" placeholder="M-Pesa Number (e.g. 0712345678)" required class="phone-input">

            <button type="submit" class="btn-pay">PAY VIA M-PESA</button>
        </form>

        <a href="../gate/exit.php" class="cancel-link">Cancel and Return to Exit</a>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</div>

<footer>
    &copy; <?= date("Y"); ?> Kitengela Mall Parking System
</footer>

</body>
</html>