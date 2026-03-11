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
            $status = ($vehicleModel->isOwner($plate) ? 'invoiced' : 'paid');
            $nominal = ($vehicleModel->isOwner($plate) ? $fee : 0);

            $stmt = $pdo->prepare(
                "UPDATE vehicle_logs 
                 SET payment_status = :status,
                     exit_time = NOW(),
                     total_fee = 0,
                     nominal_fee = :nominal
                 WHERE id = :id"
            );
            $stmt->execute([':status' => $status, ':nominal' => $nominal, ':id' => $row['id']]);

            // also free up the bay that was assigned
            if (!empty($row['bay_id'])) {
                $upd = $pdo->prepare("UPDATE parking_bays SET current_status='vacant' WHERE id = ?");
                $upd->execute([$row['bay_id']]);
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
            <p>Your parking duration was within the grace period. No payment is required.</p>
            <script>
                // show goodbye overlay then redirect home
                document.addEventListener('DOMContentLoaded', function() {
                    const overlay = document.createElement('div');
                    overlay.style.position = 'fixed';
                    overlay.style.top = '0';
                    overlay.style.left = '0';
                    overlay.style.width = '100%';
                    overlay.style.height = '100%';
                    overlay.style.background = 'rgba(0,0,0,0.8)';
                    overlay.style.color = 'white';
                    overlay.style.display = 'flex';
                    overlay.style.justifyContent = 'center';
                    overlay.style.alignItems = 'center';
                    overlay.style.zIndex = '10000';
                    overlay.innerHTML = '<div style="text-align:center;font-size:32px;"><p>Goodbye!</p><p>Gate is opening...</p></div>';
                    document.body.appendChild(overlay);
                    setTimeout(function() {
                        window.location.href = '../index.php';
                    }, 3000);
                });
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