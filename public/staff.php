<?php
require_once(__DIR__ . '/../backend/app/config/database.php');

$db = new DatabaseConnection();
$pdo = $db->pdo;

// Fetch staff vehicles
$stmt = $pdo->prepare("SELECT plate_number, entry_time FROM staff_vehicles");
$stmt->execute();
$staffVehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<title>Staff Parking - Kitengela Mall</title>

<style>

/* GENERAL */

body{
    font-family: Arial, sans-serif;
    margin:0;
    background:AliceBlue;
}

/* NAVBAR */

nav{
    display:flex;
    justify-content:space-between;
    align-items:center;
    background:ForestGreen;
    padding:12px 25px;
}

.logo{
    color:white;
    font-size:20px;
    font-weight:bold;
}

.links{
    display:flex;
    gap:20px;
}

.links a{
    color:white;
    text-decoration:none;
    font-weight:600;
}

/* CONTAINER */

.container{
    width:100%;
    max-width:900px;
    margin:auto;
    margin-top:40px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

/* CARD */

.card{
    background:white;
    padding:30px;
    border-radius:10px;
    box-shadow:0 3px 10px rgba(0,0,0,0.1);
    width: 100%;
    box-sizing: border-box;
}

/* TABLE */

table{
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
}

th,td{
    padding:12px;
    border:1px solid LightGray;
    text-align:center;
}

th{
    background:WhiteSmoke;
}

tr:nth-child(even){
    background:Snow;
}

/* FOOTER */

footer{
    margin-top:40px;
    text-align:center;
    padding:15px;
    background:Gainsboro;
}

</style>

</head>

<body>

<nav>

<div class="logo">Kitengela Mall Parking</div>

<div class="links">
<a href="index.php">Home</a>
<a href="driver/pay.php">Pay</a>
<a href="gate/entry.php">Entry</a>
<a href="gate/exit.php">Exit</a>
</div>

</nav>


<div class="container" style="display: flex; flex-direction: column; align-items: flex-start; width: 100%; max-width: 900px;">
    <div class="card" style="width: 100%; box-sizing: border-box;">
        <h2 style="display: block; width: 100%; margin-bottom: 1em;">Staff Parking</h2>
        <table style="width: 100%; display: block;">
            <thead style="display: table; width: 100%; table-layout: fixed;">
                <tr>
                    <th>Plate Number</th>
                    <th>Entry Time</th>
                </tr>
            </thead>
            <tbody style="display: table; width: 100%; table-layout: fixed;">
                <?php foreach ($staffVehicles as $vehicle): ?>
                <tr>
                    <td><?= htmlspecialchars(strtoupper($vehicle['plate_number'])); ?></td>
                    <td><?= htmlspecialchars($vehicle['entry_time']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<footer>
© <?= date("Y"); ?> Kitengela Mall Parking System
</footer>

</body>
</html>
