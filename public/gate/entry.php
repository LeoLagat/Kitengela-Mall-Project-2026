<?php
require_once "../../backend/app/controllers/GateController.php";

$message = "";
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plate = strtoupper($_POST['plate']);
    $controller = new GateController();
    $result = $controller->processEntry($plate);

    $message = $result['message'];
    $success = $result['success'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Entry Gate | Kitengela Mall</title>

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

/* SERVER TIME */

.server-time{
    font-size:14px;
    color:grey;
    text-align:center;
    margin-top:5px;
}

/* PAGE CENTERING */

.page{
    display:flex;
    justify-content:center;
    align-items:center;
    margin-top:50px;
}

/* CARD */

.card{
    background:white;
    padding:35px;
    border-radius:10px;
    box-shadow:0 3px 10px rgba(0,0,0,0.1);
    width:350px;
    text-align:center;
}

/* FORM */

input{
    width:100%;
    padding:12px;
    margin-top:15px;
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

/* FOOTER */

footer{
    margin-top:40px;
    text-align:center;
    padding:15px;
    background:#eee;
}

</style>

<style>
.server-time { display: <?php echo $success ? 'none' : 'block'; ?>; }
</style>

</head>

<body>

<nav>
<div class="logo">Kitengela Mall Parking</div>

<div class="links">
<a href="../index.php">Home</a>
<a href="../gate/entry.php" class="active">Entry</a>
<a href="../gate/exit.php">Exit</a>
</div>
</nav>

<p class="server-time">
Server time: <?= date('Y-m-d H:i:s'); ?>
</p>

<div class="page">

<div class="card">

<h2>Entry Gate</h2>
<p>Enter the vehicle plate number below</p>

<?php if (!$success && $message): ?>

<div style="color:darkred;background:mistyrose;padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid darkred;font-weight:bold;">
⚠️ <?php echo $message; ?>
</div>

<?php endif; ?>

<form method="POST" id="entryForm">

<input type="text" name="plate" placeholder="KBC 123A" required autofocus autocomplete="off" style="text-transform:uppercase;" oninput="this.value = this.value.toUpperCase()">

<button type="submit">Confirm Plates</button>

</form>

</div>

</div>

<?php if ($success): ?>
    <div style="background:#e6ffe6;color:#246b24;padding:20px;border-radius:10px;margin-bottom:20px;font-size:22px;font-weight:bold;">
        <?php
        // Extract assigned bay from message
        preg_match('/Assigned Bay: ([^ ]+)/', $message, $matches);
        $assignedBay = isset($matches[1]) ? $matches[1] : '';
        ?>
        Your parking bay: <span style="color:#2d862d;font-size:28px;"><?= htmlspecialchars($assignedBay) ?></span>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded',function(){
        const ov=document.createElement('div');
        ov.style.position='fixed';
        ov.style.top='0';
        ov.style.left='0';
        ov.style.width='100%';
        ov.style.height='100%';
        ov.style.background='rgba(0,0,0,0.7)';
        ov.style.color='white';
        ov.style.display='flex';
        ov.style.justifyContent='center';
        ov.style.alignItems='center';
        ov.style.zIndex='10000';
        ov.innerHTML='<div style="text-align:center;font-size:32px;"><p>WELCOME</p><p>Gate is opening...</p></div>';
        document.body.appendChild(ov);
        setTimeout(function(){
            window.location.href='../index.php';
        },3000);
    });
    </script>
<?php endif; ?>

<footer>
© <?= date("Y"); ?> Kitengela Mall Parking System
</footer>

</body>
</html>