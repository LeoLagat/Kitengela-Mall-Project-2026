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


html, body {
    height: 100%;
    margin: 0;
    padding: 0;
}
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: whitesmoke;
    color: darkslategray;
    min-height: 100vh;
    height: 100vh;
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
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
    flex: 1 1 auto;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 0;
    padding: 0 8px;
    box-sizing: border-box;
    overflow-y: auto;
}

.card {
    background: white;
    padding: 26px;
    border-radius: 14px;
    box-shadow: 0 12px 28px gainsboro;
    border: 1px solid lightgray;
    width: 100%;
    max-width: 600px;
    text-align: center;
    box-sizing: border-box;
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

.welcome-title {
    margin: 0;
    font-size: 26px;
    color: forestgreen;
    letter-spacing: 0.5px;
}

.welcome-subtitle {
    margin-top: 6px;
    margin-bottom: 10px;
    color: darkslategray;
    font-size: 14px;
    font-weight: 600;
}

.plate-chip {
    display: inline-block;
    margin-top: 6px;
    margin-bottom: 8px;
    background: mintcream;
    color: darkgreen;
    border: 1px solid palegreen;
    border-radius: 999px;
    padding: 6px 14px;
    font-size: 17px;
    font-weight: 800;
    letter-spacing: 1px;
}

.welcome-note {
    margin-top: 4px;
    color: dimgray;
    font-size: 13px;
    font-weight: 600;
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
    flex-shrink: 0;
    width: 100%;
    box-sizing: border-box;
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

<body class="entry-exit-no-scroll">

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
<div style="background:lemonchiffon;border-left:4px solid orange;border-radius:10px;padding:12px 16px;margin-bottom:16px;text-align:left;font-size:14px;color:darkorange;">
    <strong>Please park at your designated bay as assigned by the system. Thank you for your cooperation!</strong>
</div>

<?php if (!$success): ?>
<div style="
    background: honeydew;
    border: 1px solid palegreen;
    border-left: 5px solid seagreen;
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 16px;
    text-align: left;
    font-size: 13px;
    color: darkslategray;
">
    <div style="font-weight: 800; color: darkgreen; margin-bottom: 6px; font-size: 14px;">Parking Rates</div>
    <div style="display: flex; flex-direction: column; gap: 4px;">
        <span>&#10003; &nbsp;<strong>First 30 minutes</strong> &mdash; Free (grace period)</span>
        <span>&#10003; &nbsp;<strong>Up to 1 hour</strong> &mdash; Ksh 50</span>
        <span>&#10003; &nbsp;<strong>Each additional hour</strong> &mdash; Ksh 20</span>
        <span>&#10003; &nbsp;<strong>Full day (12+ hours)</strong> &mdash; Ksh 1,000 flat rate</span>
        <span>&#10003; &nbsp;<strong>Staff &amp; owner vehicles</strong> &mdash; Complimentary</span>
    </div>
    <div style="margin-top: 8px; color: dimgray; font-size: 11px;">Payment is processed via M-Pesa at exit.</div>
</div>
<?php endif; ?>

<?php if (!$success && $message): ?>

<div class="status-error">
Warning: <?= htmlspecialchars($message); ?>
</div>

<?php endif; ?>

<?php if ($success): ?>

<div class="status-success">
    <h3 class="welcome-title">Welcome to Kitengela Mall</h3>
    <p class="welcome-subtitle">Your vehicle has been checked in successfully.</p>
    <div class="plate-chip"><?= htmlspecialchars($plateInput) ?></div>
    <?php if ($assignedBay !== ''): ?>
        <div class="bay-pill"><?= htmlspecialchars($assignedBay) ?></div>
    <?php endif; ?>
    <div><?= htmlspecialchars($message) ?></div>
    <div class="welcome-note">Enjoy your visit. Your parking slot is reserved.</div>
    <div class="progress" aria-hidden="true"><span></span></div>
    <div style="margin-top:8px;color:dimgray;font-size:13px;">Returning to home screen...</div>
</div>

<?php else: ?>

<form method="POST" id="entryForm">

<div class="field">
    <label for="plate">Plate Number</label>
    <input id="plate" type="text" name="plate" placeholder="KBC 123A" required autofocus autocomplete="off" value="<?= htmlspecialchars($plateInput) ?>" oninput="this.value = this.value.toUpperCase()">
</div>

<button type="submit" class="main-action-btn">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><path d="M8 36q-1.65 0-2.825-1.175Q4 33.65 4 32V16q0-1.65 1.175-2.825Q6.35 12 8 12h32q1.65 0 2.825 1.175Q44 14.35 44 16v16q0 1.65-1.175 2.825Q41.65 36 40 36Zm0-2h32q.85 0 1.425-.575Q42 32.85 42 32V16q0-.85-.575-1.425Q40.85 14 40 14H8q-.85 0-1.425.575Q6 15.15 6 16v16q0 .85.575 1.425Q7.15 34 8 34Zm0 0V14v20Z"/><circle cx="14" cy="24" r="3"/><circle cx="34" cy="24" r="3"/></svg>
    Enter &amp; Assign Bay
</button>

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