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

$welcomeMessage = '';
if (isset($_GET['welcome'])) {
    if ($_GET['welcome'] === 'exit') {
        $welcomeMessage = 'Welcome! Exit completed successfully. Ready for the next vehicle.';
    }
}

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

<?php if ($welcomeMessage !== ''): ?>
<div id="welcomeBanner" style="background:honeydew;color:darkgreen;border:1px solid palegreen;border-radius:8px;padding:10px 12px;margin-bottom:14px;font-weight:700;transition:opacity 0.6s ease,margin 0.4s ease,padding 0.4s ease,border 0.4s ease;">
<?= htmlspecialchars($welcomeMessage); ?>
</div>
<script>
(function(){
    var b = document.getElementById('welcomeBanner');
    if(!b) return;
    setTimeout(function(){
        b.style.opacity = '0';
        b.style.marginBottom = '0';
        b.style.paddingTop = '0';
        b.style.paddingBottom = '0';
        b.style.border = 'none';
        setTimeout(function(){ b.remove(); }, 600);
    }, 3000);
})();
</script>
<?php endif; ?>


<h2>Welcome to Kitengela Mall Parking System</h2>
<p>This system manages vehicle entry, billing, and exit automatically.</p>

<div style="display: flex; flex-direction: row; gap: 24px; justify-content: center; margin: 32px 0 24px 0; flex-wrap: wrap;">
    <a href="gate/entry.php" class="main-action-btn" style="max-width: 260px; min-width: 180px; text-decoration: none;">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><path d="M8 36q-1.65 0-2.825-1.175Q4 33.65 4 32V16q0-1.65 1.175-2.825Q6.35 12 8 12h32q1.65 0 2.825 1.175Q44 14.35 44 16v16q0 1.65-1.175 2.825Q41.65 36 40 36Zm0-2h32q.85 0 1.425-.575Q42 32.85 42 32V16q0-.85-.575-1.425Q40.85 14 40 14H8q-.85 0-1.425.575Q6 15.15 6 16v16q0 .85.575 1.425Q7.15 34 8 34Zm0 0V14v20Z"/><circle cx="14" cy="24" r="3"/><circle cx="34" cy="24" r="3"/></svg>
        Entry
    </a>
    <a href="gate/exit.php" class="main-action-btn" style="max-width: 260px; min-width: 180px; text-decoration: none;">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><path d="M8 36q-1.65 0-2.825-1.175Q4 33.65 4 32V16q0-1.65 1.175-2.825Q6.35 12 8 12h32q1.65 0 2.825 1.175Q44 14.35 44 16v16q0 1.65-1.175 2.825Q41.65 36 40 36Zm0-2h32q.85 0 1.425-.575Q42 32.85 42 32V16q0-.85-.575-1.425Q40.85 14 40 14H8q-.85 0-1.425.575Q6 15.15 6 16v16q0 .85.575 1.425Q7.15 34 8 34Zm0 0V14v20Z"/><path d="M24 18v6.15l5.2 5.2 1.4-1.4-4.6-4.6V18Z"/></svg>
        Exit
    </a>
</div>

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