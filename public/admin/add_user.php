<?php
session_start();
// only super_admin can access
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true ||
    empty($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

require_once(__DIR__ . '/../../backend/app/config/database.php');
$db = new DatabaseConnection();
$pdo = $db->pdo;

// log admin visiting the add-user page
require_once(__DIR__ . '/../../backend/app/services/AdminAudit.php');
if (!empty($_SESSION['admin_username'])) {
    AdminAudit::log($pdo, $_SESSION['admin_username'], 'visited add user page');
}

$message = '';
// only super_admins may add other users
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_SESSION['admin_role'] !== 'super_admin') {
        $message = "Only the main admin can create new users.";
    } else {
        $uname = strtolower(trim($_POST['username'] ?? ''));
        $pwd = $_POST['password'] ?? '';
        if ($uname && $pwd) {
            // insert new administrator, avoid duplicates
            $stmt = $pdo->prepare("SELECT id FROM administrators WHERE LOWER(username)=?");
            $stmt->execute([$uname]);
            if ($stmt->fetch()) {
                $message = "A user with that username already exists.";
            } else {
                $hash = password_hash($pwd, PASSWORD_DEFAULT);
                $stmt2 = $pdo->prepare("INSERT INTO administrators (username, password, role) VALUES (?, ?, 'admin')");
                $stmt2->execute([$uname, $hash]);
                $message = "Sub‑admin '$uname' added successfully.";
            }
        } else {
            $message = "Please provide both username and password.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Administrator</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, honeydew, whitesmoke);
        }

        .page-wrapper {
            min-height: calc(100vh - 60px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
        }

        .form-card {
            width: 100%;
            max-width: 480px;
            background: white;
            border-radius: 14px;
            border: 1px solid lightgray;
            box-shadow: 0 12px 30px gainsboro;
            overflow: hidden;
        }

        .form-card-header {
            background: linear-gradient(90deg, darkgreen 0%, seagreen 100%);
            padding: 28px 32px 22px;
            color: white;
        }

        nav {
            background: linear-gradient(90deg, darkgreen 0%, seagreen 100%);
        }

        .form-card-header h2 {
            margin: 0 0 4px;
            font-size: 22px;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-card-header p {
            margin: 0;
            font-size: 13px;
            opacity: 0.82;
        }

        .form-card-body {
            padding: 28px 32px 32px;
        }

        .security-note {
            margin-bottom: 18px;
            padding: 10px 12px;
            border-radius: 8px;
            background: mintcream;
            border: 1px solid palegreen;
            color: darkgreen;
            font-size: 13px;
        }

        .alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .alert-success { background: honeydew; color: darkgreen; border: 1px solid palegreen; }
        .alert-error   { background: mistyrose; color: maroon; border: 1px solid lightcoral; }
        .alert-info    { background: whitesmoke; color: darkslategray; border: 1px solid lightgray; }

        .field-group {
            margin-bottom: 18px;
        }

        .field-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: darkslategray;
            margin-bottom: 6px;
        }

        .field-group .input-wrap {
            position: relative;
        }

        .field-group .input-wrap span {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 15px;
            color: dimgray;
            pointer-events: none;
        }

        .field-group input {
            width: 100%;
            padding: 10px 12px 10px 36px;
            border: 1px solid lightgray;
            border-radius: 8px;
            font-size: 14px;
            color: darkslategray;
            background: whitesmoke;
            box-sizing: border-box;
            transition: border-color .2s, box-shadow .2s, background .2s;
            text-transform: none;
            text-align: left;
        }

        .field-group input:focus {
            outline: none;
            border-color: seagreen;
            box-shadow: 0 0 0 3px lightgreen;
            background: white;
        }

        .field-hint {
            margin-top: 6px;
            font-size: 12px;
            color: dimgray;
        }

        .toggle-pw {
            all: unset;
            position: absolute;
            right: 11px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 15px;
            color: dimgray;
            padding: 0;
            line-height: 1;
        }

        .toggle-pw:hover {
            color: darkslategray;
        }

        .pw-meter {
            height: 6px;
            border-radius: 999px;
            background: gainsboro;
            margin-top: 8px;
            overflow: hidden;
        }

        .pw-meter-fill {
            height: 100%;
            width: 0;
            background: firebrick;
            transition: width .2s, background .2s;
        }

        .pw-meter-label {
            margin-top: 6px;
            font-size: 12px;
            color: dimgray;
        }

        .caps-warning {
            display: none;
            margin-top: 8px;
            font-size: 12px;
            color: maroon;
            background: mistyrose;
            border: 1px solid lightcoral;
            border-radius: 6px;
            padding: 6px 8px;
        }

        .caps-warning.show {
            display: block;
        }

        .btn-submit {
            width: 100%;
            padding: 11px;
            background: linear-gradient(90deg, darkgreen 0%, seagreen 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: .3px;
            transition: opacity .2s, transform .1s;
            margin-top: 4px;
        }

        .btn-submit:hover { opacity: .88; }

        .btn-submit:active { transform: scale(.99); }

        .btn-submit:disabled {
            opacity: .6;
            cursor: not-allowed;
            transform: none;
        }

        .form-hint {
            font-size: 12px;
            color: dimgray;
            margin-top: 16px;
            text-align: center;
        }

        @media (max-width: 520px) {
            .form-card-body {
                padding: 22px 18px 22px;
            }

            .form-card-header {
                padding: 22px 18px 18px;
            }
        }
    </style>
</head>
<body>
<nav>
    <div class="logo">Admin Panel</div>
    <div class="links">
        <a href="dashboard.php">Dashboard</a>
        <a href="restricted.php">Restricted List</a>
        <?php if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
            <a href="activity.php">Activity Log</a>
            <a href="subadmin_activity.php">Sub-admin Logs</a>
        <?php endif; ?>
        <a href="profile.php">My Profile</a>
        <a href="logout.php" style="color:red;">Logout</a>
    </div>
</nav>

<div class="page-wrapper">
    <div class="form-card">
        <div class="form-card-header">
            <h2>&#128100; Add Sub-Administrator</h2>
            <p>New accounts are created with the <strong>sub-admin</strong> role by default.</p>
        </div>
        <div class="form-card-body">
            <div class="security-note">Only trusted personnel should be granted admin access.</div>

            <?php if ($message):
                $isSuccess = strpos($message, 'successfully') !== false;
                $isError   = !$isSuccess;
                $alertClass = $isSuccess ? 'alert-success' : 'alert-error';
                $icon       = $isSuccess ? '&#9989;' : '&#9888;';
            ?>
            <div class="alert <?= $alertClass ?>" role="alert" aria-live="polite">
                <span><?= $icon ?></span>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="field-group">
                    <label for="username">Username</label>
                    <div class="input-wrap">
                        <span>&#128100;</span>
                        <input type="text" id="username" name="username"
                               placeholder="e.g. john_doe"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               required autofocus autocomplete="username" autocapitalize="none" spellcheck="false">
                    </div>
                    <p class="field-hint">Usernames are stored in lowercase.</p>
                </div>

                <div class="field-group">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <span>&#128274;</span>
                        <input type="password" id="password" name="password"
                               placeholder="Enter a strong password" required minlength="6" autocomplete="new-password">
                        <button type="button" class="toggle-pw" onclick="togglePw(this, 'password')" title="Show password" aria-label="Show password" aria-pressed="false">&#128065;</button>
                    </div>
                    <div class="pw-meter"><div id="pwMeterFill" class="pw-meter-fill"></div></div>
                    <div id="pwMeterLabel" class="pw-meter-label">Password strength: too weak</div>
                    <div id="capsWarning" class="caps-warning" aria-live="polite">Caps Lock appears to be ON.</div>
                </div>

                <div class="field-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="input-wrap">
                        <span>&#128274;</span>
                        <input type="password" id="confirm_password" name="confirm_password"
                               placeholder="Re-enter password" required minlength="6" autocomplete="new-password">
                        <button type="button" class="toggle-pw" onclick="togglePw(this, 'confirm_password')" title="Show password" aria-label="Show password" aria-pressed="false">&#128065;</button>
                    </div>
                    <p id="passwordMatchHint" class="field-hint">Passwords must match before submission.</p>
                </div>

                <button type="submit" class="btn-submit">&#43; Add Administrator</button>
            </form>

            <p class="form-hint">Only super-admins can create new accounts. New users will be assigned the <em>sub-admin</em> role.</p>
        </div>
    </div>
</div>

<script>
function togglePw(btn, inputId) {
    const input = document.getElementById(inputId);
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.setAttribute('aria-pressed', input.type === 'text' ? 'true' : 'false');
    btn.setAttribute('aria-label', input.type === 'text' ? 'Hide password' : 'Show password');
    btn.title = input.type === 'text' ? 'Hide password' : 'Show password';
    btn.textContent = input.type === 'password' ? '\u{1F441}' : '\u{1F648}';
}

const form = document.querySelector('form');
const passwordInput = document.getElementById('password');
const confirmPasswordInput = document.getElementById('confirm_password');
const meterFill = document.getElementById('pwMeterFill');
const meterLabel = document.getElementById('pwMeterLabel');
const matchHint = document.getElementById('passwordMatchHint');
const capsWarning = document.getElementById('capsWarning');

function updateStrength() {
    const value = passwordInput.value;
    let score = 0;

    if (value.length >= 6) score++;
    if (value.length >= 10) score++;
    if (/[A-Z]/.test(value) && /[a-z]/.test(value)) score++;
    if (/\d/.test(value)) score++;
    if (/[^A-Za-z0-9]/.test(value)) score++;

    const strengthLevels = [
        { width: 20, label: 'too weak', color: 'firebrick' },
        { width: 35, label: 'weak', color: 'orangered' },
        { width: 55, label: 'fair', color: 'goldenrod' },
        { width: 75, label: 'good', color: 'seagreen' },
        { width: 100, label: 'strong', color: 'darkgreen' }
    ];

    const level = value.length === 0 ? strengthLevels[0] : strengthLevels[Math.min(score, 4)];
    meterFill.style.width = level.width + '%';
    meterFill.style.background = level.color;
    meterLabel.textContent = 'Password strength: ' + level.label;
}

function updateMatchHint() {
    const a = passwordInput.value;
    const b = confirmPasswordInput.value;

    if (!b) {
        matchHint.textContent = 'Passwords must match before submission.';
        matchHint.style.color = 'dimgray';
        return;
    }

    if (a === b) {
        matchHint.textContent = 'Passwords match.';
        matchHint.style.color = 'darkgreen';
    } else {
        matchHint.textContent = 'Passwords do not match yet.';
        matchHint.style.color = 'maroon';
    }
}

function updateCapsState(event) {
    if (event.getModifierState && event.getModifierState('CapsLock')) {
        capsWarning.classList.add('show');
    } else {
        capsWarning.classList.remove('show');
    }
}

passwordInput.addEventListener('input', function() {
    updateStrength();
    updateMatchHint();
});
confirmPasswordInput.addEventListener('input', updateMatchHint);
passwordInput.addEventListener('keydown', updateCapsState);
passwordInput.addEventListener('keyup', updateCapsState);
passwordInput.addEventListener('blur', function() {
    capsWarning.classList.remove('show');
});

form.addEventListener('submit', function(event) {
    if (passwordInput.value !== confirmPasswordInput.value) {
        event.preventDefault();
        matchHint.textContent = 'Passwords do not match yet.';
        matchHint.style.color = 'maroon';
        confirmPasswordInput.focus();
        return;
    }

    const submitBtn = form.querySelector('.btn-submit');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Creating account...';
});

updateStrength();
</script>
</body>
</html>