<?php
session_start();

// simple authorization: ensure admin logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// use central database connection class
require_once(__DIR__ . '/../../backend/app/config/database.php');

require_once(__DIR__ . '/../../backend/app/models/Vehicle.php');
require_once(__DIR__ . '/../../backend/app/services/AdminAudit.php');

$db = new DatabaseConnection();
$pdo = $db->pdo;

// record that the current administrator accessed the dashboard
if (!empty($_SESSION['admin_username'])) {
    AdminAudit::log($pdo, $_SESSION['admin_username'], 'viewed dashboard');
}

$vehicle = new Vehicle($pdo);

$vehiclesInside = $vehicle->vehiclesInside();
$totalRevenue   = $vehicle->totalRevenue();
$overstays = $vehicle->overstays(8);

// bay counts
$stmt = $pdo->prepare("
SELECT
SUM(current_status='occupied') AS occupied,
SUM(current_status='vacant') AS vacant
FROM parking_bays
");
$stmt->execute();
$bayCounts = $stmt->fetch(PDO::FETCH_ASSOC);

$occupiedBays = (int)$bayCounts['occupied'];
$vacantBays   = (int)$bayCounts['vacant'];
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Dashboard</title>

<style>

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    margin: 0;
    background: whitesmoke;
    color: darkslategray;
}

nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    background: linear-gradient(90deg, darkgreen 0%, seagreen 100%);
    padding: 12px 24px;
    position: sticky;
    top: 0;
    z-index: 100;
}

.logo {
    color: white;
    font-size: 26px;
    font-weight: 700;
    letter-spacing: 1px;
}

.links {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.links a {
    color: white;
    text-decoration: none;
    font-weight: 600;
    padding: 6px 10px;
    border-radius: 6px;
}

.links a:hover {
    background: forestgreen;
}

.container {
    width: 95%;
    max-width: 1180px;
    margin: 26px auto 50px auto;
    display: grid;
    gap: 18px;
}

.hero {
    background: white;
    border: 1px solid lightgray;
    border-left: 8px solid darkgreen;
    border-radius: 14px;
    padding: 20px;
    box-shadow: 0 10px 20px silver;
}

.hero h2 {
    margin: 0;
    font-size: 34px;
    color: forestgreen;
    line-height: 1.2;
}

.hero p {
    margin: 8px 0 0 0;
    color: dimgray;
    font-size: 15px;
}

.summary-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.summary-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: mintcream;
    border: 1px solid palegreen;
    color: darkgreen;
    border-radius: 999px;
    padding: 6px 12px;
    font-size: 13px;
    font-weight: 700;
}

.dashboard-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
}

.dashboard-box {
    background: white;
    padding: 20px;
    border-radius: 12px;
    border: 1px solid lightgray;
    text-align: center;
    box-shadow: 0 8px 18px gainsboro;
}

.dashboard-box h3 {
    margin: 0 0 8px 0;
    color: forestgreen;
    font-size: 18px;
}

.dashboard-box p {
    margin: 0;
    font-size: 29px;
    font-weight: 700;
    color: darkslategray;
}

.dashboard-box form label {
    color: dimgray;
    font-size: 13px;
    font-weight: 600;
}

.dashboard-box form input[type="date"] {
    margin-left: 4px;
    padding: 6px 8px;
    border: 1px solid lightgray;
    border-radius: 6px;
}

.quick-form {
    display: flex;
    gap: 8px;
    justify-content: center;
    flex-wrap: wrap;
}

.quick-btn {
    padding: 8px 12px;
    border: none;
    border-radius: 6px;
    background: darkgreen;
    color: white;
    font-weight: 700;
    cursor: pointer;
}

.quick-btn:hover {
    background: seagreen;
}

.danger-btn {
    background: crimson;
}

.danger-btn:hover {
    background: firebrick;
}

.overstay-alert {
    padding: 12px 14px;
    background: mistyrose;
    color: maroon;
    border: 1px solid lightcoral;
    border-left: 6px solid firebrick;
    border-radius: 10px;
}

.section-card {
    background: white;
    border: 1px solid lightgray;
    border-radius: 12px;
    padding: 18px;
    box-shadow: 0 8px 18px gainsboro;
}

.section-card h3 {
    margin: 0;
    color: forestgreen;
}

.table-wrap {
    margin-top: 14px;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    min-width: 760px;
}

th, td {
    padding: 11px;
    border: 1px solid lightgray;
    text-align: center;
}

th {
    background: whitesmoke;
    color: darkslategray;
}

.bypass-btn {
    background: darkgreen;
    color: white;
    border: none;
    padding: 8px 12px;
    cursor: pointer;
    border-radius: 6px;
    font-weight: 700;
}

.bypass-btn:hover {
    background: seagreen;
}

.status-paid {
    color: darkgreen;
    font-weight: 700;
}

.status-pending {
    color: darkorange;
    font-weight: 700;
}

.empty-state {
    margin-top: 14px;
    padding: 16px;
    border-radius: 10px;
    border: 1px dashed burlywood;
    background: floralwhite;
    color: saddlebrown;
    text-align: center;
    font-weight: 700;
}

footer {
    margin-top: 40px;
    text-align: center;
    padding: 15px;
    background: gainsboro;
    color: darkslategray;
}

@media (max-width: 920px) {
    nav {
        align-items: flex-start;
        flex-direction: column;
    }

    .links {
        justify-content: flex-start;
    }

    .hero h2 {
        font-size: 28px;
    }
}

</style>

</head>

<body>

<nav>

<div class="logo">Admin Panel</div>

<div class="links">
<a href="../index.php">Home</a>
<a href="../gate/entry.php">Entry</a>
<a href="../gate/exit.php">Exit</a>
<a href="staff.php">Staff Parking</a>
<a href="owners.php">Owner Vehicles</a>
<a href="restricted.php">Restricted List</a>
<?php if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
    <a href="add_user.php">Manage Admins</a>
    <a href="activity.php">Activity Log</a>
    <a href="subadmin_activity.php">Sub-admin Logs</a>
    <a href="database_search.php">Database Search</a>
<?php endif; ?>
<a href="profile.php">My Profile</a>
<a href="logout.php" style="color:red;">Logout</a>
</div>

</nav>


<div class="container">
<?php
// show message when download page gets GET parameters
if (isset($_GET['from']) && isset($_GET['to'])) {
    // redirect to revenue_report to keep dashboard clean
    $from = htmlspecialchars($_GET['from']);
    $to   = htmlspecialchars($_GET['to']);
    header("Location: revenue_report.php?from=$from&to=$to");
    exit;
}
?>

<section class="hero">
    <h2>Parking Overview</h2>
    <p>Monitor occupancy, revenue, overstay risk, and active gate traffic from one dashboard.</p>
</section>

<section class="summary-chips">
    <span class="summary-chip">Inside: <?= (int)$vehiclesInside ?></span>
    <span class="summary-chip">Occupied: <?= $occupiedBays ?></span>
    <span class="summary-chip">Vacant: <?= $vacantBays ?></span>
    <span class="summary-chip">Overstays: <?= count($overstays) ?></span>
</section>

<div class="dashboard-container">

<div class="dashboard-box">
<h3>Vehicles Inside</h3>
<p><?= (int)$vehiclesInside ?></p>
</div>

<div class="dashboard-box">
<h3>Occupied Bays</h3>
<p><?= $occupiedBays ?></p>
</div>

<div class="dashboard-box">
<h3>Vacant Bays</h3>
<p><?= $vacantBays ?></p>
</div>

<div class="dashboard-box">
<h3>Overstays (>8h)</h3>
<p><?= count($overstays) ?></p>
</div>

<div class="dashboard-box">
<h3>Total Revenue</h3>
<p>Ksh <?= number_format($totalRevenue,2) ?></p>
</div>

<!-- revenue report form -->
<div class="dashboard-box">
    <h3>Revenue Report</h3>
    <form method="get" action="revenue_report.php">
        <input type="hidden" name="from_id" value="25">
        <div class="quick-form">
            <label>From <input type="date" name="from" required></label>
            <label>To <input type="date" name="to" required></label>
            <button type="submit" class="quick-btn">Download</button>
        </div>
    </form>
</div>

<?php if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
<div class="dashboard-box">
    <h3>Activity Log</h3>
    <form method="get" action="activity.php">
        <div class="quick-form">
            <label>From <input type="date" name="from" required></label>
            <label>To <input type="date" name="to" required></label>
            <button type="submit" class="quick-btn">Download</button>
        </div>
    </form>
</div>

<div class="dashboard-box">
    <h3>Vehicle Logs Management</h3>
    <div class="quick-form">
        <button type="button" class="quick-btn danger-btn" onclick="clearVehicleLogs()">Clear Vehicle Logs</button>
    </div>
</div>
<?php endif; ?>

</div>


<?php if (!empty($overstays)): ?>
<div class="overstay-alert">
<strong>Attention:</strong> <?= count($overstays) ?> vehicle(s) have been inside more than 8 hours.
</div>
<?php endif; ?>


<div class="section-card">

<h3>Active Vehicles & Manual Gate Control</h3>

<div class="table-wrap">

<table>

<thead>
<tr>
<th>Plate Number</th>
<th>Entry Time</th>
<th>Duration</th>
<th>Payment Status</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php

$stmt = $pdo->prepare("
SELECT plate_number, entry_time, payment_status
FROM vehicle_logs
WHERE exit_time IS NULL
ORDER BY entry_time DESC
");

$stmt->execute();

$activeVehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($activeVehicles)):

?>

<tr>
<td colspan="5"><div class="empty-state">No active vehicles are currently inside.</div></td>
</tr>

<?php

else:

foreach ($activeVehicles as $row):


$entry = new DateTime($row['entry_time']);
$now = new DateTime();
$interval = $entry->diff($now);
$totalMinutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
$hours = floor($totalMinutes / 60);
$minutes = $totalMinutes % 60;
$duration = $hours . 'h ' . $minutes . 'm';

?>

<tr>

<td><?= htmlspecialchars($row['plate_number']) ?></td>

<td><?= $row['entry_time'] ?></td>

<td class="duration" data-entry="<?= htmlspecialchars($row['entry_time']) ?>">
<?= $duration ?>
</td>

<td>
<span class="status-<?= strtolower($row['payment_status']) ?>">
<?= ucfirst($row['payment_status']) ?>
</span>
</td>

<td>

<button class="bypass-btn"
onclick="bypassPayment('<?= htmlspecialchars($row['plate_number']) ?>')">
Manual Bypass
</button>

</td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

</tbody>

</table>

</div>

</div>

</div>


<script>

function bypassPayment(plate){
    const reminderMsg = `⚠️ MANUAL GATE BYPASS REMINDER\n\n` +
        `Vehicle: ${plate}\n` +
        `Action: FORCE GATE OPEN - Allow any vehicle to exit\n` +
        `Effect: Vehicle will EXIT immediately (regardless of payment status)\n\n` +
        `Use this ONLY for:\n` +
        `• Pending M-Pesa transactions (payment delayed in database)\n` +
        `• Authorized emergency exits\n` +
        `• Pre-approved by manager/supervisor\n` +
        `• Vehicles stuck due to system delays\n\n` +
        `⚠️ This will mark the vehicle as PAID even if payment not yet confirmed!\n\n` +
        `Are you sure you want to proceed?`;
    
    if(confirm(reminderMsg)){
        const finalConfirm = confirm(`FINAL CONFIRMATION:\n\nForce gate open for ${plate}?\n\nThis action will:\n✓ Allow vehicle to exit\n✓ Mark as PAID\n✓ Be logged for audit\n\nContinue?`);
        
        if(finalConfirm){
            fetch('../../backend/app/services/admin_bypass.php',{
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'plate='+encodeURIComponent(plate)
            })
            .then(res=>res.json())
            .then(data=>{
                alert(data.message);
                if(data.status==='success') location.reload();
            })
            .catch(()=>alert("Bypass service unavailable"));
        }
    }
}

function clearVehicleLogs(){
    const confirmMsg = "⚠️ WARNING: This will permanently delete ALL vehicle logs from the database. This action cannot be undone.\n\nAre you absolutely sure you want to continue?";
    
    if(confirm(confirmMsg)){
        const doubleCheck = confirm("This is your FINAL confirmation. Click OK to clear all vehicle logs, or Cancel to abort.");
        
        if(doubleCheck){
            fetch('clear_logs.php',{
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:''
            })
            .then(res=>res.json())
            .then(data=>{
                alert(data.message);
                if(data.status==='success') location.reload();
            })
            .catch(err=>{
                alert("Error clearing logs: " + err);
            });
        }
    }
}

/* LIVE DURATION */

function updateDurations() {
    document.querySelectorAll('.duration').forEach(td => {
        const entry = new Date(td.dataset.entry.replace(' ', 'T'));
        const now = new Date();
        let diff = Math.floor((now - entry) / 60000);
        const hours = Math.floor(diff / 60);
        const minutes = diff % 60;
        td.textContent = hours + 'h ' + minutes + 'm';
    });
}

// Update immediately on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateDurations);
} else {
    updateDurations();
}

// Update every 30 seconds
setInterval(updateDurations, 30000);

</script>


<footer>
© <?= date("Y") ?> Kitengela Mall Administration
</footer>

</body>
</html>