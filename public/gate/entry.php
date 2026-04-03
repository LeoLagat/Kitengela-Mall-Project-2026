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

.page {
    flex: 1 1 auto;
    display: flex;
    flex-direction: row;
    justify-content: center;
    align-items: center;
    min-height: 0;
    padding: 0 8px;
    box-sizing: border-box;
    overflow-y: auto;
    min-height: 80vh;
    height: 100%;
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
@media (max-width: 480px) {
    .card {
        padding: 20px;
    }

    .welcome-title {
        font-size: 22px;
    }

    .bay-pill {
        font-size: 20px;
        padding: 6px 10px;
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




<div class="page" style="gap: 32px; align-items: center; min-height: 70vh;">
    <!-- Left Info Container -->
    <div style="flex:1; max-width:400px; display:flex; flex-direction:column; gap:24px; justify-content:center; height:100%;">
        <div style="background:lemonchiffon;border-left:6px solid orange;border-radius:12px;padding:18px 18px 18px 18px;text-align:left;color:darkorange;font-size:20px;font-weight:700;">
        NOTICE :   
        Please park at your designated bay as assigned by the system. Thank you for your cooperation!
        </div>
        <div style="background: #eaffea; border: 2px solid #b2f2b2; border-radius: 12px; padding: 18px 18px 18px 18px; text-align: left; color: #2d862d; font-size: 17px; font-weight: 700;">
            <div style="font-weight: bold; font-size: 22px; margin-bottom: 6px; color: forestgreen;">Parking Rates</div>
            <div style="display: flex; flex-direction: column; gap: 4px; font-weight: 600; color: #1b5e20;">
                <span>&#10003; <b>First 30 minutes</b> — Free (grace period)</span>
                <span>&#10003; <b>Up to 1 hour</b> — Ksh 50</span>
                <span>&#10003; <b>Each additional hour</b> — Ksh 20</span>
                <span>&#10003; <b>Full day (12+ hours)</b> — Ksh 1,000 flat rate</span>
                <span>&#10003; <b>Staff &amp; owner vehicles</b> — Complimentary</span>
            </div>
            <div style="margin-top: 8px; color: dimgray; font-size: 13px; font-weight:400;">Payment is processed via M-Pesa at exit.</div>
        </div>
    </div>
    <!-- Right Entry Form Container -->
    <div style="flex:2; min-width:340px; max-width:600px; margin:0 auto; display:flex; align-items:center; justify-content:center; height:100%;">
        <div class="card" style="width:100%;max-width:620px; margin:auto;">
            <div style="display:flex;justify-content:center;align-items:center;margin-bottom:18px;">
                <!-- Card Icon SVG -->
                <svg width="80" height="54" viewBox="0 0 80 54" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <rect x="3" y="7" width="74" height="40" rx="7" fill="#e0ffe0" stroke="seagreen" stroke-width="3"/>
                  <rect x="10" y="18" width="60" height="8" rx="2" fill="#b2f2b2" />
                  <rect x="10" y="32" width="18" height="6" rx="2" fill="#b2f2b2" />
                  <rect x="32" y="32" width="18" height="6" rx="2" fill="#b2f2b2" />
                  <rect x="54" y="32" width="16" height="6" rx="2" fill="#b2f2b2" />
                </svg>
            </div>
            <h2 style="color:forestgreen;">Entry Gate</h2>
            <p class="subtitle" style="font-size:16px;">Enter the vehicle plate number to assign a parking bay.</p>
            <?php if (!$success && $message): ?>
                <div class="status-error" style="margin-bottom: 16px;">
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
                <form method="POST" id="entryForm" style="margin-top:18px;">
                    <div class="field">
                        <label for="plate">Plate Number</label>
                        <input id="plate" type="text" name="plate" placeholder="KBC 123A" required autofocus autocomplete="off" value="<?= htmlspecialchars($plateInput) ?>" oninput="this.value = this.value.toUpperCase()" style="border: 2px solid #2d862d; border-radius: 8px; font-size: 20px; padding: 16px;">
                    </div>
                    <button type="submit" style="font-size:18px;">Enter &amp; Assign Bay</button>
                </form>
            <?php endif; ?>
        </div>
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