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

<!-- Main Page Content -->
<div class="page" style="display: flex; flex-direction: row; justify-content: center; align-items: flex-start; gap: 32px; width: 100%; max-width: 1200px; margin: 0 auto;">
    <div style="flex: 1; max-width: 320px; display: flex; flex-direction: column; gap: 18px; margin-top: 24px;">
        <div style="background: #fff3cd; border-left: 6px solid orange; border-radius: 12px; padding: 16px 18px 12px 18px; text-align: left; color: #ff9800; font-size: 17px; font-weight: 700;">
            <span style="color: #e65100; font-weight: bold;">Kindly park at your assigned bay only.</span><br>
            <span style="font-weight: 400; color: #e65100;">If you park in a different basement or bay than assigned, you will be fined <b>Ksh 50</b> upon exit.</span>
        </div>
        <div style="background: #eaffea; border: 2px solid #b2f2b2; border-radius: 12px; padding: 16px 18px 12px 18px; text-align: left; color: #2d862d; font-size: 17px; font-weight: 700;">
            <div style="font-weight: bold; font-size: 18px; margin-bottom: 6px;">Parking Rates</div>
            <div style="display: flex; flex-direction: column; gap: 4px; font-weight: 400; color: #1b5e20;">
                <span>&#10003; <b>First 30 minutes</b> — Free (grace period)</span>
                <span>&#10003; <b>Up to 1 hour</b> — Ksh 50</span>
                <span>&#10003; <b>Each additional hour</b> — Ksh 20</span>
                <span>&#10003; <b>Full day (12+ hours)</b> — Ksh 1,000 flat rate</span>
                <span>&#10003; <b>Staff &amp; owner vehicles</b> — Complimentary</span>
            </div>
            <div style="margin-top: 8px; color: dimgray; font-size: 12px;">Payment is processed via M-Pesa at exit.</div>
        </div>
    </div>
    <div class="card" style="flex: 2; min-width: 340px; max-width: 600px; margin: 0 auto;">
        <h2 style="margin-bottom: 0; color: #2d862d;">Entry Gate</h2>
        <p class="subtitle" style="margin-top: 8px; margin-bottom: 18px; color: dimgray; font-size: 16px;">Enter the vehicle plate number to assign a parking bay.</p>
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
            <form method="POST" id="entryForm" style="width:100%;max-width:100%;margin-top:18px;">
                <div class="field">
                    <label for="plate">Plate Number</label>
                    <input id="plate" type="text" name="plate" placeholder="KBC 123A" required autofocus autocomplete="off" value="<?= htmlspecialchars($plateInput) ?>" oninput="this.value = this.value.toUpperCase()" style="border: 2px solid #2d862d; border-radius: 8px; font-size: 20px; padding: 16px;">
                </div>
                <button type="submit" class="main-action-btn">
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
    <div style="flex: 1; max-width: 320px; display: flex; flex-direction: column; gap: 18px; margin-top: 24px;">
        <div style="background: #fff3cd; border-left: 6px solid orange; border-radius: 12px; padding: 16px 18px 12px 18px; text-align: left; color: #ff9800; font-size: 17px; font-weight: 700;">
            <span style="color: #e65100; font-weight: bold;">Kindly park at your assigned bay only.</span><br>
            <span style="font-weight: 400; color: #e65100;">If you park in a different basement or bay than assigned, you will be fined <b>Ksh 50</b> upon exit.</span>
        </div>
        <div style="background: #eaffea; border: 2px solid #b2f2b2; border-radius: 12px; padding: 16px 18px 12px 18px; text-align: left; color: #2d862d; font-size: 17px; font-weight: 700;">
            <div style="font-weight: bold; font-size: 18px; margin-bottom: 6px;">Parking Rates</div>
            <div style="display: flex; flex-direction: column; gap: 4px; font-weight: 400; color: #1b5e20;">
                <span>&#10003; <b>First 30 minutes</b> — Free (grace period)</span>
                <span>&#10003; <b>Up to 1 hour</b> — Ksh 50</span>
                <span>&#10003; <b>Each additional hour</b> — Ksh 20</span>
                <span>&#10003; <b>Full day (12+ hours)</b> — Ksh 1,000 flat rate</span>
                <span>&#10003; <b>Staff &amp; owner vehicles</b> — Complimentary</span>
            </div>
            <div style="margin-top: 8px; color: dimgray; font-size: 12px;">Payment is processed via M-Pesa at exit.</div>
        </div>
    </div>
    <div class="card" style="flex: 2; min-width: 340px; max-width: 600px; margin: 0 auto;">
        <h2 style="margin-bottom: 0; color: #2d862d;">Entry Gate</h2>
        <p class="subtitle" style="margin-top: 8px; margin-bottom: 18px; color: dimgray; font-size: 16px;">Enter the vehicle plate number to assign a parking bay.</p>
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
            <form method="POST" id="entryForm" style="width:100%;max-width:100%;margin-top:18px;">
                <div class="field">
                    <label for="plate">Plate Number</label>
                    <input id="plate" type="text" name="plate" placeholder="KBC 123A" required autofocus autocomplete="off" value="<?= htmlspecialchars($plateInput) ?>" oninput="this.value = this.value.toUpperCase()" style="border: 2px solid #2d862d; border-radius: 8px; font-size: 20px; padding: 16px;">
                </div>
                <button type="submit" class="main-action-btn">
                    Enter &amp; Assign Bay
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
                <div class="page" style="display: flex; flex-direction: row; justify-content: center; align-items: flex-start; gap: 32px; width: 100%; max-width: 1200px; margin: 0 auto;">
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
    min-height: calc(100vh - 80px);
    height: calc(100vh - 80px);
    padding: 0 8px;
    box-sizing: border-box;
    overflow: hidden;
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
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    min-height: 70vh;
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

/* Make the entry icon fill the available space and center it */
.entry-icon-large {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100vw;
    height: calc(100vh - 180px); /* Adjust for nav and footer */
    max-width: 100vw;
    max-height: 80vh;
    margin: 0 auto 18px auto;
    box-sizing: border-box;
}
.entry-icon-large svg {
    width: 60vw;
    height: 60vw;
    max-width: 420px;
    max-height: 60vh;
    display: block;
}
@media (max-width: 600px) {
  .entry-icon-large svg {
    width: 90vw;
    height: 40vh;
    max-width: 98vw;
    max-height: 40vh;
  }
  .entry-icon-large {
    height: 40vh;
    min-height: 200px;
  }
}

/* Entry page: fit icon and form together, no scroll, center everything */
.entry-kiosk-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: calc(100vh - 120px); /* nav+footer space */
    width: 100vw;
    max-width: 100vw;
    margin: 0 auto;
    box-sizing: border-box;
    position: absolute;
    top: 60px;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 2;
}
.entry-icon-kiosk {
    margin-bottom: 18px;
    display: flex;
    justify-content: center;
    align-items: center;
}
.entry-icon-kiosk svg {
    width: 120px;
    height: 80px;
    max-width: 30vw;
    max-height: 12vh;
    display: block;
}
#entryForm {
    width: 100%;
    max-width: 340px;
}
@media (max-width: 600px) {
  .entry-kiosk-wrap {
    min-height: calc(100vh - 80px);
    padding: 0 2vw;
    top: 40px;
  }
  .entry-icon-kiosk svg {
    width: 90px;
    height: 60px;
    max-width: 60vw;
    max-height: 10vh;
  }
}

.entry-icon-kiosk {
    background: linear-gradient(135deg, #219a21 60%, #2d862d 100%);
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(44,130,44,0.10);
    border: 2px solid #2d862d;
    display: flex;
    justify-content: center;
    align-items: center;
    width: 120px;
    height: 28px;
    margin: 24px auto 16px auto;
    position: relative;
    left: 0;
    right: 0;
}
.entry-icon-kiosk svg {
    width: 100px;
    height: 18px;
    display: block;
    margin: 0 auto;
}

.entry-info-box {
    background: #fff8e1;
    border-left: 6px solid orange;
    border-radius: 12px;
    padding: 18px 18px 18px 18px;
    margin-bottom: 18px;
    text-align: center;
    color: #ff9800;
    font-size: 17px;
    font-weight: 700;
    position: relative;
}
.entry-info-box .entry-icon-kiosk {
    background: linear-gradient(135deg, #219a21 60%, #2d862d 100%);
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(44,130,44,0.10);
    border: 2px solid #2d862d;
    display: inline-flex;
    justify-content: center;
    align-items: center;
    width: 60px;
    height: 28px;
    margin: 0 0 8px 0;
    vertical-align: middle;
}
.entry-info-box .entry-icon-kiosk svg {
    width: 44px;
    height: 18px;
    display: block;
    margin: 0 auto;
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
?>
<div class="page" style="display: flex; flex-direction: row; justify-content: center; align-items: flex-start; gap: 32px; width: 100%; max-width: 1200px; margin: 0 auto;">
            <span style="color: #e65100; font-weight: bold;">Kindly park at your assigned bay only.</span><br>
            <span style="font-weight: 400; color: #e65100;">If you park in a different basement or bay than assigned, you will be fined  upon exit.</span>
        </div>
        <div style="background: #eaffea; border: 2px solid #b2f2b2; border-radius: 12px; padding: 16px 18px 12px 18px; margin-bottom: 18px; text-align: left; color: #2d862d; font-size: 17px; font-weight: 700;">
            <div style="font-weight: bold; font-size: 18px; margin-bottom: 6px;"></div>
            <div style="display: flex; flex-direction: column; gap: 4px; font-weight: 400; color: #1b5e20;">
                <span>&#10003; <b>First 30 minutes</b> — Free (grace period)</span>
                <span>&#10003; <b>Up to 1 hour</b> — Ksh 50</span>
                <span>&#10003; <b>Each additional hour</b> — Ksh 20</span>
                <span>&#10003; <b>Full day (12+ hours)</b> — Ksh 1,000 flat rate</span>
                <span>&#10003; <b>Staff &amp; owner vehicles</b> — Complimentary</span>
            </div>
            <div style="margin-top: 8px; color: dimgray; font-size: 12px;">Payment is processed via M-Pesa at exit.</div>
        </div>
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
            <form method="POST" id="entryForm" style="width:100%;max-width:100%;margin-top:18px;">
                <div class="field">
                    <label for="plate">Plate Number</label>
                    <input id="plate" type="text" name="plate" placeholder="KBC 123A" required autofocus autocomplete="off" value="<?= htmlspecialchars($plateInput) ?>" oninput="this.value = this.value.toUpperCase()" style="border: 2px solid #2d862d; border-radius: 8px; font-size: 20px; padding: 16px;">
                </div>
                <button type="submit" class="main-action-btn">
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