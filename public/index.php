<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kitengela Mall Parking</title>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
    gap:20px;
}

.links a{
    color:white;
    text-decoration:none;
    font-weight:600;
}

/* CONTAINER */

.container{
    width:90%;
    margin:auto;
    margin-top:40px;
}

/* CARD */

.card{
    background:white;
    padding:30px;
    border-radius:10px;
    box-shadow:0 3px 10px rgba(0,0,0,0.1);
    text-align:center;
}

/* BAY GRID */

.bay-boxes{
    margin-top:20px;
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:20px;
}

/* BAY BOX */

.bay-box{
    background:#ecf7ec;
    border:1px solid #d4e9d4;
    border-radius:10px;
    padding:20px;
}

.bay-title{
    font-size:18px;
    font-weight:bold;
    color:#2d862d;
    margin-bottom:10px;
}

.bay-count{
    font-size:22px;
}

.bay-number{
    font-size:32px;
    font-weight:bold;
    color:#34495e;
}

.bay-label{
    display:block;
    font-size:12px;
    color:#555;
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

<?php
require_once(__DIR__ . '/../backend/app/config/database.php');

$db = new DatabaseConnection();
$pdo = $db->pdo;

$stmt = $pdo->prepare("
SELECT floor_level, COUNT(*) AS cnt
FROM parking_bays
WHERE current_status='vacant'
GROUP BY floor_level
");

$stmt->execute();

$vacantByFloor = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<nav>

<div class="logo">Kitengela Mall Parking</div>

<div class="links">
<a href="gate/entry.php">Entry</a>
<a href="gate/exit.php">Exit</a>
<a href="admin/dashboard.php">Dashboard</a>
</div>

</nav>


<div class="container">

<div class="card">

<h2>Welcome to Kitengela Mall Parking System</h2>

<p>This system manages vehicle entry, billing, and exit automatically.</p>

<hr>

<h3>Available Bays</h3>

<div class="bay-boxes">

<?php if(count($vacantByFloor)): ?>

<?php foreach($vacantByFloor as $row): ?>

<div class="bay-box">

<div class="bay-title">
<?= htmlspecialchars($row['floor_level']); ?>
</div>

<div class="bay-count">

<span class="bay-number">
<?= intval($row['cnt']); ?>
</span>

<span class="bay-label">
AVAILABLE SPACE
</span>

</div>

</div>

<?php endforeach; ?>

<?php else: ?>

<div class="bay-box">

<div class="bay-title">No Available Bays</div>

<div class="bay-count">
<span class="bay-number">0</span>
<span class="bay-label">AVAILABLE SPACE</span>
</div>

</div>

<?php endif; ?>

</div>

</div>

</div>


<footer>
© <?php echo date("Y"); ?> Kitengela Mall Parking System
</footer>

</body>
</html>