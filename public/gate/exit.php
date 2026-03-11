<?php
// Catch error messages sent back from the payment page
$message = "";
if (isset($_GET['error']) && $_GET['error'] == 'notfound') {
    $message = "Vehicle not found or has already been cleared.";
}

// free exit notification
$freeNotice = '';
if (isset($_GET['free']) && $_GET['free'] == '1') {
    $freeNotice = "<div class='success'>Parking duration was under grace period; no payment required.</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Exit Gate - Kitengela Mall</title>

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

.links a.active{
    border-bottom:2px solid white;
}

/* PAGE CENTER */

.page{
    display:flex;
    justify-content:center;
    align-items:center;
    margin-top:60px;
}

/* CARD */

.card{
    background:white;
    padding:35px;
    border-radius:10px;
    width:380px;
    box-shadow:0 3px 10px rgba(0,0,0,0.1);
}

/* SUBTITLE */

.subtitle{
    color:grey;
    margin-bottom:25px;
}

/* FORM */

input{
    width:100%;
    padding:12px;
    border:1px solid #ccc;
    border-radius:5px;
    font-size:16px;
}

button{
    width:100%;
    padding:12px;
    margin-top:15px;
    background:#2d862d;
    color:white;
    border:none;
    border-radius:5px;
    font-size:16px;
    cursor:pointer;
}

button:hover{
    background:#246b24;
}

/* ALERTS */

.alert-danger{
    background:#f8d7da;
    color:#721c24;
    padding:12px;
    border-radius:6px;
    border:1px solid #f5c6cb;
    margin-bottom:20px;
}

.success{
    background:#d4edda;
    color:#155724;
    padding:12px;
    border-radius:6px;
    border:1px solid #c3e6cb;
    margin-bottom:20px;
}

/* FOOTER */

footer{
    margin-top:50px;
    text-align:center;
    padding:15px;
    background:#eee;
}

</style>

</head>

<body>

<nav>

<div class="logo">Kitengela Mall Parking</div>

<div class="links">
<a href="../index.php">Home</a>
<a href="../gate/entry.php">Entry</a>
<a href="../gate/exit.php" class="active">Exit</a>
</div>

</nav>


<div class="page">

<div class="card">

<h2>Vehicle Exit</h2>

<p class="subtitle">
Enter the plate number to compute fees and open the barrier.
</p>

<?php if ($freeNotice): ?>
<?= $freeNotice ?>
<?php endif; ?>

<?php if ($message): ?>
<div class="alert-danger">
⚠️ <?= $message ?>
</div>
<?php endif; ?>


<?php if ($freeNotice): ?>

<script>

document.addEventListener('DOMContentLoaded',()=>{

const ov=document.createElement('div')

ov.style.position='fixed'
ov.style.top='0'
ov.style.left='0'
ov.style.width='100%'
ov.style.height='100%'
ov.style.background='rgba(0,0,0,0.7)'
ov.style.color='white'
ov.style.display='flex'
ov.style.justifyContent='center'
ov.style.alignItems='center'
ov.style.zIndex='10000'

ov.innerHTML='<div style="text-align:center;font-size:32px;"><p>Enjoy your stay!</p><p>Gate is opening...</p></div>'

document.body.appendChild(ov)

setTimeout(()=>{
window.location='exit.php'
},3000)

})

</script>

<?php endif; ?>


<form action="../driver/pay.php" method="POST">

<div style="margin-bottom:20px;">

<label style="display:block;margin-bottom:8px;font-weight:bold;color:dimgray;">
ENTER VEHICLE PLATE NUMBER
</label>

<input type="text" name="plate" placeholder="E.G. KAA 123A" required autofocus oninput="this.value = this.value.toUpperCase()" style="text-transform:uppercase;">

</div>

<button type="submit">
PROCESS EXIT
</button>

</form>

</div>

</div>


<footer>
© <?= date("Y"); ?> Kitengela Mall Parking System
</footer>

</body>
</html>