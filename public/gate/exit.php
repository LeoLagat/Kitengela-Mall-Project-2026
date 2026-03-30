<?php
// Catch error messages sent back from the payment page
$message = "";
if (isset($_GET['error']) && $_GET['error'] == 'notfound') {
    $message = "Vehicle not found or has already been cleared.";
}

// free exit notification
$isFreeExit = (isset($_GET['free']) && $_GET['free'] == '1');
$exitReason = isset($_GET['reason']) ? trim((string)$_GET['reason']) : '';
if ($exitReason === '') {
    $exitReason = 'No payment required under the grace period.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Exit Gate - Kitengela Mall</title>

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
    width: 100%;
    max-width: 440px;
    border: 1px solid lightgray;
    box-shadow: 0 12px 28px gainsboro;
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
    background: mistyrose;
    color: maroon;
    border: 1px solid lightcoral;
}

.status-success {
    background: honeydew;
    color: darkgreen;
    border: 1px solid palegreen;
    text-align: center;
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
    animation: shrink 8s linear forwards;
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
    margin-bottom: 6px;
    font-weight: 700;
    color: dimgray;
    font-size: 13px;
}

.field input {
    width: 100%;
    padding: 12px;
    border: 1px solid lightgray;
    border-radius: 8px;
    font-size: 16px;
    text-transform: uppercase;
    box-sizing: border-box;
    background: whitesmoke;
    text-align: left;
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

<?php if ($isFreeExit): ?>
<div class="status-success">
<div style="font-size:23px;font-weight:800;color:forestgreen;letter-spacing:0.3px;">Exit Approved</div>
<div style="margin-top:6px;color:darkslategray;font-size:14px;font-weight:600;">Reason: <?= htmlspecialchars($exitReason) ?></div>
<div style="display:inline-block;margin-top:10px;background:mintcream;border:1px solid palegreen;border-radius:999px;color:darkgreen;padding:6px 14px;font-size:13px;font-weight:800;">Thank you for visiting Kitengela Mall</div>
<div class="progress" aria-hidden="true"><span></span></div>
<div style="margin-top:8px;color:dimgray;font-size:13px;">Returning to home screen...</div>
</div>
<?php endif; ?>

<?php if ($message): ?>
<div class="status-error">
Warning: <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>


<form action="../driver/pay.php" method="POST">

<div class="field">

<label for="plate">
Enter Vehicle Plate Number
</label>

<input id="plate" type="text" name="plate" placeholder="E.G. KAA 123A" required autofocus autocomplete="off" oninput="this.value = this.value.toUpperCase()">

</div>

<button type="submit">
PROCESS EXIT
</button>

</form>

</div>

</div>

<?php if ($isFreeExit): ?>
<script>
window.setTimeout(function () {
    window.location = '../index.php?welcome=exit';
}, 8000);
</script>
<?php endif; ?>

<script>
document.querySelector('form')?.addEventListener('submit', function() {
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