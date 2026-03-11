 <?php
// This class handles all vehicle parking operations
class Vehicle {

    // Database connection
    private $conn;

    // Constructor to receive database connection
    public function __construct($db) {
        $this->conn = $db;
    }

    // Calculate parking fee based on entry time.
    // If a plate number is supplied, check staff table and waive the fee for
    // registered employees (free parking).
    public function calculateFee($entryTime, $plateNumber = null) {
        if ($plateNumber) {
            $stmt = $this->conn->prepare(
                "SELECT 1 FROM staff_vehicles WHERE plate_number = ? LIMIT 1"
            );
            $stmt->execute([$plateNumber]);
            if ($stmt->fetchColumn()) {
                // staff vehicle – free parking
                return 0;
            }
        }

        $entry = new DateTime($entryTime);
        $now   = new DateTime();

        $diff = $entry->diff($now);
        $totalMinutes = ($diff->days * 1440) + ($diff->h * 60) + $diff->i;

        // Grace period: free if 30 minutes or less
        if ($totalMinutes <= 30) {
            return 0;
        }

        // Up to one hour after grace: flat 50
        if ($totalMinutes <= 60) {
            return 50;
        }

        // Beyond the first hour: 50 + 20 per additional hour (partial hours count as full)
        $hours = ceil($totalMinutes / 60);
        // subtract the first hour which is already covered by the base fee
        $extraHours = $hours - 1;
        $fee = 50 + ($extraHours * 20);

        // cap the fee if the vehicle has stayed a whole day (12h) to prevent runaway
        if ($hours >= 12) {
            $fee = 1000; // flat day rate, adjust as needed
        }

        // owners are not given a reduced fee here; their discount is applied
        // later when invoices are generated.  We want nominal_fee to reflect the
        // full charge so accounting can calculate 50% billing separately.
        // (previously we halved the fee which led to owners being invoiced too little)

        return $fee;
    }

    // Record vehicle entry into the parking
    public function recordEntry($plate, $bayId) {
    //  Create a timestamp in PHP (Nairobi time)
        // Insert entry record using database clock for consistency
        $stmt = $this->conn->prepare(
            "INSERT INTO vehicle_logs 
             (plate_number, bay_id, entry_time, total_fee, payment_status) 
            VALUES (?, ?, NOW(), 0, 'unpaid')"
        );
        $stmt->execute([$plate, $bayId]);

        // Mark parking bay as occupied
        $this->conn->prepare(
            "UPDATE parking_bays SET current_status = 'occupied' WHERE id = ?"
        )->execute([$bayId]);

        return true;
    }

    // Handle vehicle exit process
    public function recordExit($plate) {

        // Get active vehicle record (not exited yet)
        $stmt = $this->conn->prepare(
            "SELECT * FROM vehicle_logs
             WHERE plate_number = ?
             AND exit_time IS NULL
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$plate]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        // If no active record found
        if (!$record) return false;

        // Calculate parking fee (pass plate so staff can be exempt)
        $fee = $this->calculateFee($record['entry_time'], $record['plate_number']);

        // Complete exit; include plate so owner logic can run
        $this->completeExit($record['id'], $fee, $record['bay_id'], $record['plate_number']);

        return $fee;
    }

    // helper: check whether a plate belongs to an owner account
    public function isOwner($plate) {
        $stmt = $this->conn->prepare(
            "SELECT 1 FROM owner_accounts WHERE plate_number = ? AND invoice_monthly = 1 LIMIT 1"
        );
        $stmt->execute([$plate]);
        return (bool) $stmt->fetchColumn();
    }

    // return array of overstaying vehicles (currently parked > 8 hours)
    public function overstays($hoursThreshold = 8) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM vehicle_logs
             WHERE exit_time IS NULL
               AND entry_time < NOW() - INTERVAL ? HOUR"
        );
        $stmt->execute([$hoursThreshold]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Count vehicles currently inside the parking
    public function vehiclesInside(): int {

        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) 
             FROM vehicle_logs
             WHERE exit_time IS NULL"
        );
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    // Calculate total revenue from paid vehicles
   public function totalRevenue(): float {
    $stmt = $this->conn->prepare(
        "SELECT COALESCE(SUM(total_fee), 0)
         FROM vehicle_logs
         WHERE payment_status = 'paid'"
    );
    $stmt->execute();

    return (float) $stmt->fetchColumn();
}

    // Get active vehicle details by plate number
    public function getActiveVehicle($plate) {

        $stmt = $this->conn->prepare(
            "SELECT * FROM vehicle_logs
             WHERE plate_number = ?
             AND exit_time IS NULL
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$plate]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Finish exit and free parking bay
    // if $plate is an owner with invoice_monthly, we zero total_fee and mark as invoiced
    public function completeExit($logId, $fee, $bayId, $plate = null) {
        $status = 'paid';
        $totalFee = $fee;
        $nominal = 0;

        if ($plate && $this->isOwner($plate)) {
            // owners are invoiced later at half the nominal fee; store the full
            // amount in nominal_fee and keep total_fee zero so they are exempt now
            $status = 'invoiced';
            $nominal = $fee;
            $totalFee = 0;
        }

        // update using database clock to avoid drift
        $stmt = $this->conn->prepare(
            "UPDATE vehicle_logs
             SET exit_time = NOW(),
                 total_fee = ?,
                 nominal_fee = ?,
                 payment_status = ?
             WHERE id = ?"
        );
        $stmt->execute([$totalFee, $nominal, $status, $logId]);

        // Free the parking bay
        $this->conn->prepare(
            "UPDATE parking_bays
             SET current_status = 'vacant' 
             WHERE id = ?"
        )->execute([$bayId]);

        return true;
    }
}