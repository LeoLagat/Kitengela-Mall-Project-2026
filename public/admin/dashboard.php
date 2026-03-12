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

/* GENERAL */
body{
    font-family: Arial, sans-serif;
    margin:0;
    background:#f5f7fa;
}

/* NAVBAR */
nav{
    display:flex;
    justify-content:space-between;
    align-items:center;
    background:#2d862d;
    padding:12px 25px;
}

.logo{
    color:white;
    font-size:20px;
    font-weight:bold;
}

.links{
    display:flex;
    gap:18px;
}

.links a{
    color:white;
    text-decoration:none;
    font-weight:600;
}

/* CONTAINER */

.container{
    width:95%;
    margin:auto;
    margin-top:20px;
}

/* DASHBOARD GRID */

.dashboard-container{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:25px;
}

.dashboard-box{
    background:white;
    padding:25px;
    border-radius:10px;
    text-align:center;
    box-shadow:0 2px 8px rgba(0,0,0,0.08);
}

.dashboard-box h3{
    margin-bottom:10px;
    color:#2d862d;
}

.dashboard-box p{
    font-size:28px;
    font-weight:bold;
}

/* TABLE */

table{
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
    background:white;
    table-layout:fixed;
}

th,td{
    padding:12px;
    border:1px solid #ddd;
    text-align:center;
}

th{
    background:#f4f4f4;
}

/* BUTTON */

.bypass-btn{
    background:#28a745;
    color:white;
    border:none;
    padding:6px 12px;
    cursor:pointer;
    border-radius:4px;
}

.bypass-btn:hover{
    background:#218838;
}

.status-paid{
    color:green;
    font-weight:bold;
}

.status-pending{
    color:orange;
    font-weight:bold;
}

/* FOOTER */

footer{
    margin-top:40px;
    text-align:center;
    padding:15px;
    background:#eee;
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
    <a href="add_user.php">Add User</a>
    <a href="activity.php">Activity Log</a>
    <a href="subadmin_activity.php">Sub-admin Logs</a>
<?php endif; ?>
<a href="logout.php" style="color:#ffdddd;">Logout</a>
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

<h2>Parking Overview</h2>

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
        <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
            <label>From <input type="date" name="from" required></label>
            <label>To <input type="date" name="to" required></label>
            <button type="submit" style="padding:6px 12px;">Download</button>
        </div>
    </form>
</div>

<?php if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
<div class="dashboard-box">
    <h3>Activity Log</h3>
    <form method="get" action="activity.php">
        <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
            <label>From <input type="date" name="from" required></label>
            <label>To <input type="date" name="to" required></label>
            <button type="submit" style="padding:6px 12px;">Download</button>
        </div>
    </form>
</div>
<?php endif; ?>

</div>


<?php if (!empty($overstays)): ?>
<div style="margin-top:20px;padding:10px;background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;">
<strong>Attention:</strong> <?= count($overstays) ?> vehicle(s) have been inside more than 8 hours.
</div>
<?php endif; ?>


<div style="margin-top:30px">

<h3>Active Vehicles & Manual Gate Control</h3>

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

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):


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

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>


<script>

function bypassPayment(plate){

if(confirm("Mark "+plate+" as PAID and open the gate?")){

fetch('../../backend/app/services/admin_bypass.php',{

method:'POST',

headers:{'Content-Type':'application/x-www-form-urlencoded'},

body:'plate='+encodeURIComponent(plate)

})

.then(res=>res.json())

.then(data=>{

alert(data.message)

if(data.status==='success') location.reload()

})

.catch(()=>alert("Bypass service unavailable"))

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
// Update every second
setInterval(updateDurations, 1000);

</script>


<footer>
© <?= date("Y") ?> Kitengela Mall Administration
</footer>

</body>
</html>