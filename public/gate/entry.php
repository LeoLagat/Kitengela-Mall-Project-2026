<?php
require_once "../../backend/app/controllers/GateController.php";

$message = "";
$success = false;
$plateInput = '';
$assignedBay = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plate = strtoupper(trim($_POST['plate'] ?? ''));
    $plateInput = $plate;
    $controller = new GateController();
    $result = $controller->processEntry($plate);

    $message = $result['message'];
    $success = $result['success'];

    if ($success) {
        preg_match('/Assigned Bay:\s*([^ ]+)/', $message, $matches);
        $assignedBay = isset($matches[1]) ? $matches[1] : '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Entry Gate | Kitengela Mall</title>

<style>

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    margin: 0;
    min-height: 100vh;
    background: whitesmoke;
    color: darkslategray;
    display: flex;
    flex-direction: column;
}

nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    background: linear-gradient(90deg, darkgreen 0%, seagreen 100%);
    padding: 12px 24px;
    position: sticky;
    top: 0;
    z-index: 100;
}

.logo {
    color: white;
    font-size: 24px;
    font-weight: 700;
    letter-spacing: 1px;
}

.links {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
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

.links a.active {
    background: white;
    color: darkgreen;
}

.server-time {
    font-size: 14px;
    color: dimgray;
    text-align: center;
    margin-top: 8px;
}

.page {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 28px 16px;
}

.card {
    background: white;
    padding: 26px;
    border-radius: 14px;
    box-shadow: 0 12px 28px gainsboro;
    border: 1px solid lightgray;
    width: 100%;
    max-width: 430px;
    text-align: center;
}

.card h2 {
    margin: 0;
    color: forestgreen;
    font-size: 30px;
}

.subtitle {
    margin-top: 8px;
    margin-bottom: 18px;
    color: dimgray;
    font-size: 14px;
}

.status-error,
.status-success {
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 16px;
    font-weight: 700;
    text-align: left;
}

.status-error {
    color: maroon;
    background: mistyrose;
    border: 1px solid lightcoral;
}

.status-success {
    color: darkgreen;
    background: honeydew;
    border: 1px solid palegreen;
    text-align: center;
}

.bay-pill {
    display: inline-block;
    margin-top: 8px;
    margin-bottom: 8px;
    background: mintcream;
    color: darkgreen;
    border: 1px solid palegreen;
    border-radius: 999px;
    padding: 6px 12px;
    font-size: 24px;
    font-weight: 800;
    letter-spacing: 1px;
}

.progress {
    width: 100%;
    height: 8px;
    border-radius: 999px;
    background: gainsboro;
    overflow: hidden;
    margin-top: 12px;
}

.progress > span {
    display: block;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, seagreen, darkgreen);
    animation: shrink 3s linear forwards;
}

@keyframes shrink {
    from { width: 100%; }
    to { width: 0%; }
}

.field {
    text-align: left;
    margin-bottom: 12px;
}

.field label {
    display: block;
    font-size: 13px;
    color: dimgray;
    font-weight: 700;
    margin-bottom: 6px;
}

.field input {
    width: 100%;
    padding: 12px;
    border: 1px solid lightgray;
    border-radius: 8px;
    font-size: 16px;
    text-transform: uppercase;
    box-sizing: border-box;
    text-align: left;
    background: whitesmoke;
}

.field input:focus {
    outline: none;
    border-color: seagreen;
    box-shadow: 0 0 0 3px lightgreen;
    background: white;
}

button {
    width: 100%;
    padding: 12px;
    margin-top: 4px;
    background: linear-gradient(90deg, darkgreen, seagreen);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
}

button:hover {
    opacity: 0.9;
}

button:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

footer {
    text-align: center;
    padding: 14px;
    background: gainsboro;
    color: darkslategray;
}

@media (max-width: 760px) {
    nav {
        flex-direction: column;
        align-items: flex-start;
    }

    .logo {
        font-size: 22px;
    }
}

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

<?php if (!$success): ?>
<p class="server-time">
Server time: <?= date('Y-m-d H:i:s'); ?>
</p>
<?php endif; ?>

<div class="page">

<div class="card">

<h2>Entry Gate</h2>
<p class="subtitle">Enter the vehicle plate number to assign a parking bay.</p>

<?php if (!$success && $message): ?>

<div class="status-error">
Warning: <?= htmlspecialchars($message); ?>
</div>

<?php endif; ?>

<?php if ($success): ?>

<div class="status-success">
    Entry recorded successfully.
    <?php if ($assignedBay !== ''): ?>
        <div class="bay-pill"><?= htmlspecialchars($assignedBay) ?></div>
    <?php endif; ?>
    <div><?= htmlspecialchars($message) ?></div>
    <div class="progress" aria-hidden="true"><span></span></div>
    <div style="margin-top:8px;color:dimgray;font-size:13px;">Returning to home screen...</div>
</div>

<?php else: ?>

<form method="POST" id="entryForm">

<div class="field">
    <label for="plate">Plate Number</label>
    <input id="plate" type="text" name="plate" placeholder="KBC 123A" required autofocus autocomplete="off" value="<?= htmlspecialchars($plateInput) ?>" oninput="this.value = this.value.toUpperCase()">
</div>

<button type="submit">Confirm Plate</button>

</form>

<?php endif; ?>

</div>

</div>

<?php if ($success): ?>
<script>
window.setTimeout(function() {
    window.location.href = '../index.php';
}, 3000);
</script>
<?php endif; ?>

<script>
document.getElementById('entryForm')?.addEventListener('submit', function() {
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Processing...';
});
</script>

<footer>
© <?= date("Y"); ?> Kitengela Mall Parking System
</footer>

</body>
</html>