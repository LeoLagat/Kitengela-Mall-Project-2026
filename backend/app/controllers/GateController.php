<?php
require_once __DIR__ . '/../config/database.php';

class GateController {
    private $db;

    public function __construct() {
        $this->db = (new DatabaseConnection())->pdo;
    }

   
public function processEntry($plate) {
    // Always use uppercase for plate numbers
    $plate = strtoupper(trim($plate));
    // deny entry if plate is on the restricted list
    $blk = $this->db->prepare("SELECT 1 FROM restricted_vehicles WHERE plate_number = ? LIMIT 1");
    $blk->execute([$plate]);
    if ($blk->fetchColumn()) {
        return [
            "success" => false,
            "message" => "ENTRY DENIED – this vehicle is restricted"
        ];
    }
    // Check if vehicle already inside
    $checkVehicle = $this->db->prepare("
        SELECT id FROM vehicle_logs
        WHERE plate_number = ?
        AND exit_time IS NULL
    ");
    $checkVehicle->execute([$plate]);

    if ($checkVehicle->rowCount() > 0) {
        return [
            "success" => false,
            "message" => "Vehicle already inside the mall!"
        ];
    }

    //  Get first vacant bay using YOUR column name
    $getBay = $this->db->prepare("
        SELECT id, bay_number 
        FROM parking_bays
        WHERE current_status = 'vacant'
        LIMIT 1
    ");
    $getBay->execute();
    $bay = $getBay->fetch(PDO::FETCH_ASSOC);

    if (!$bay) {
        return [
            "success" => false,
            "message" => "Parking Full!"
        ];
    }

    $bayId = $bay['id'];
    $bayNumber = $bay['bay_number'];

    // Insert vehicle log
    $stmt = $this->db->prepare("
        INSERT INTO vehicle_logs
        (plate_number, bay_id, entry_time, payment_status)
        VALUES (?, ?, NOW(), 'pending')
    ");
    $stmt->execute([$plate, $bayId]);

    // Update bay to occupied
    $update = $this->db->prepare("
       UPDATE parking_bays
       SET current_status = 'occupied'
       WHERE id = ?
    ");
    $update->execute([$bayId]);

    return [
        "success" => true,
        "message" => "Barrier Opened. Assigned Bay: " . $bayNumber
    ];
}
public function storeExit()
{
    if (!isset($_POST['plate'])) {
        $_SESSION['exit_error'] = "Plate number required.";
        header("Location: exit.php");
        exit;
    }

    $plate = strtoupper(trim($_POST['plate']));

    $check = $this->db->prepare("
        SELECT * FROM vehicle_logs
        WHERE plate_number = ?
        AND exit_time IS NULL
        AND payment_status = 'pending'
        LIMIT 1
    ");
    $check->execute([$plate]);
    $vehicle = $check->fetch(PDO::FETCH_ASSOC);

    if (!$vehicle) {
        $_SESSION['exit_error'] = "Vehicle not found or already exited.";
        header("Location: exit.php");
        exit;
    }

    //  LOCATION
    header("Location: driver/pay.php?plate=" . urlencode($plate));
    exit;
}
public function completeExit($plate)
{
    require_once __DIR__ . '/../models/Vehicle.php';
    $vehicleModel = new Vehicle($this->db);

    $plate = strtoupper(trim($plate));
    $stmt = $this->db->prepare("
        SELECT * FROM vehicle_logs
        WHERE plate_number = ?
        AND exit_time IS NULL
        LIMIT 1
    ");
    $stmt->execute([$plate]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$log) return false;

    $amount = $vehicleModel->calculateFee ($log['entry_time'], $plate);

  $updateLog = $this->db->prepare("
    UPDATE vehicle_logs
    SET exit_time = NOW(),
        total_fee = ?,
        payment_status = 'paid'
    WHERE id = ?
");
$updateLog->execute([$amount, $log['id']]);

    $updateBay = $this->db->prepare("
        UPDATE parking_bays
        SET current_status = 'vacant'
        WHERE id = ?
    ");
    $updateBay->execute([$log['bay_id']]);

    return true;
}

}