<?php
// admin login restored
session_start();

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = strtolower($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    require_once(__DIR__ . '/../../backend/app/config/database.php');
    $db = new DatabaseConnection();
    $pdo = $db->pdo;

    $stmt = $pdo->prepare("SELECT * FROM administrators WHERE LOWER(username)=?");
    $stmt->execute([$user]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    // verify hashed password
    if ($admin && password_verify($pass, $admin['password'])) {
        // successful login
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id']       = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        // if username is leolagat, make sure they are super_admin regardless of DB value
        $role = $admin['role'] ?? 'admin';
        if (strtolower($admin['username']) === 'leolagat') {
            $role = 'super_admin';
            // persist role change in database if needed
            try {
                $pdo->prepare("UPDATE administrators SET role='super_admin' WHERE id=?")
                    ->execute([$admin['id']]);
            } catch (Exception $e) {
                // ignore
            }
        }
        $_SESSION['admin_role']     = $role;

        require_once(__DIR__ . '/../../backend/app/services/AdminAudit.php');
        AdminAudit::log($pdo, $admin['username'], 'login');

        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login &mdash; Kitengela Parking</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(145deg, darkgreen 0%, #1a6b1a 50%, #0d4d0d 100%);
            padding: 24px 16px;
            line-height: 1.5;
            color: darkslategray;
        }

        /* ── Shell ───────────────────────────────── */
        .login-shell {
            width: 100%;
            max-width: 420px;
            animation: slideUp .45s cubic-bezier(.22,.68,0,1.2) both;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(28px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Branding ────────────────────────────── */
        .login-brand {
            text-align: center;
            margin-bottom: 26px;
        }
        .brand-icon {
            width: 66px;
            height: 66px;
            background: rgba(255,255,255,.14);
            border: 2px solid rgba(255,255,255,.32);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            margin-bottom: 14px;
            backdrop-filter: blur(6px);
        }
        .login-brand h1 {
            color: white;
            font-size: 23px;
            font-weight: 700;
            margin-bottom: 4px;
            letter-spacing: .4px;
        }
        .login-brand p {
            color: rgba(255,255,255,.68);
            font-size: 13px;
        }

        .security-tag {
            margin-top: 9px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: white;
            background: darkgreen;
            border: 1px solid seagreen;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .2px;
        }

        /* ── Card ────────────────────────────────── */
        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 24px 64px rgba(0,0,0,.32), 0 4px 16px rgba(0,0,0,.16);
            overflow: hidden;
        }
        .login-card-body {
            padding: 32px 32px 26px;
        }

        /* ── Section title ───────────────────────── */
        .section-title {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 15px;
            font-weight: 700;
            color: darkslategray;
            margin-bottom: 22px;
        }
        .section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: gainsboro;
        }

        .section-subtitle {
            font-size: 12px;
            color: dimgray;
            margin-top: -14px;
            margin-bottom: 18px;
        }

        .assist-text {
            margin-top: 10px;
            margin-bottom: 2px;
            color: dimgray;
            font-size: 12px;
            text-align: center;
        }

        /* ── Error alert ─────────────────────────── */
        .alert-error {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
            background: mistyrose;
            border: 1px solid lightcoral;
            border-left: 4px solid crimson;
            border-radius: 8px;
            font-size: 13.5px;
            color: maroon;
            margin-bottom: 20px;
            animation: shake .35s ease;
        }
        @keyframes shake {
            0%,100% { transform: translateX(0); }
            25%      { transform: translateX(-5px); }
            75%      { transform: translateX(5px); }
        }

        /* ── Fields ──────────────────────────────── */
        .field-group {
            margin-bottom: 18px;
        }
        .field-group label {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
            color: dimgray;
            margin-bottom: 6px;
            letter-spacing: .3px;
        }
        .input-wrap {
            position: relative;
        }
        .input-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 14px;
            pointer-events: none;
            opacity: .55;
        }
        .field-group input {
            /* layout */
            display: block;
            width: 100%;
            padding: 11px 42px 11px 40px;
            box-sizing: border-box;
            /* typography — override any global sheet */
            font-family: inherit;
            font-size: 14px;
            color: darkslategray;
            text-transform: none;
            text-align: left;
            /* appearance */
            background: whitesmoke;
            border: 1.5px solid lightgray;
            border-radius: 9px;
            outline: none;
            transition: border-color .2s, box-shadow .2s, background .2s;
            /* kill any browser/global margin */
            margin: 0;
        }
        .field-group input::placeholder {
            color: silver;
            text-transform: none;
        }
        .field-group input:focus {
            border-color: seagreen;
            box-shadow: 0 0 0 3px rgba(46,139,87,.18);
            background: white;
        }

        /* ── Password toggle ─────────────────────── */
        .toggle-pw {
            /* reset ALL global button styles */
            all: unset;
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 14px;
            opacity: .5;
            transition: opacity .2s;
            line-height: 1;
        }
        .toggle-pw:hover { opacity: .9; }

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

        /* ── Submit button ───────────────────────── */
        .btn-login {
            /* reset global button */
            all: unset;
            box-sizing: border-box;
            display: block;
            width: 100%;
            padding: 13px;
            margin-top: 8px;
            background: linear-gradient(90deg, darkgreen 0%, seagreen 100%);
            color: white;
            border-radius: 9px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 700;
            text-align: center;
            letter-spacing: .4px;
            cursor: pointer;
            transition: opacity .2s, transform .1s, box-shadow .2s;
            box-shadow: 0 4px 14px rgba(0,100,0,.30);
        }
        .btn-login:hover  { opacity: .88; box-shadow: 0 6px 18px rgba(0,100,0,.38); }
        .btn-login:active { transform: scale(.98); box-shadow: none; }
        .btn-login:disabled {
            opacity: .6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-login.loading {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-login.loading::before {
            content: '';
            width: 14px;
            height: 14px;
            border: 2px solid white;
            border-right-color: transparent;
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* ── Footer ──────────────────────────────── */
        .login-card-footer {
            padding: 14px 32px 18px;
            border-top: 1px solid ghostwhite;
            display: flex;
            justify-content: center;
        }
        .btn-home {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: darkgray;
            text-decoration: none;
            padding: 6px 14px;
            border-radius: 6px;
            transition: background .18s, color .18s;
        }
        .btn-home:hover { background: ghostwhite; color: darkslategray; }

        @media (max-width: 460px) {
            .login-card-body {
                padding: 24px 20px 20px;
            }

            .login-card-footer {
                padding: 12px 20px 16px;
            }

            .login-brand h1 {
                font-size: 21px;
            }
        }
    </style>
</head>
<body>

<div class="login-shell">
    <!-- Branding -->
    <div class="login-brand">
        <div class="brand-icon">&#128663;</div>
        <h1>Kitengela Parking</h1>
        <p>Administrator Portal</p>
        <div class="security-tag">&#128274; Secure Sign-In</div>
    </div>

    <!-- Card -->
    <div class="login-card">
        <div class="login-card-body">
            <div class="section-title">&#128274;&nbsp;Sign in</div>
            <p class="section-subtitle">Use your admin credentials to access the control panel.</p>

            <?php if ($error): ?>
            <div class="alert-error" role="alert" aria-live="assertive">
                <span>&#9888;</span>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="field-group">
                    <label for="username">Username</label>
                    <div class="input-wrap">
                        <span class="input-icon">&#128100;</span>
                        <input type="text" id="username" name="username"
                               placeholder="Enter your username"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               required autofocus autocomplete="username" autocapitalize="none" spellcheck="false">
                    </div>
                </div>

                <div class="field-group">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <span class="input-icon">&#128274;</span>
                        <input type="password" id="password" name="password"
                               placeholder="Enter your password" required autocomplete="current-password" aria-describedby="capsWarning">
                        <button type="button" class="toggle-pw" onclick="togglePw(this)" title="Show password" aria-label="Show password" aria-pressed="false">&#128065;</button>
                    </div>
                    <div id="capsWarning" class="caps-warning" aria-live="polite">Caps Lock appears to be ON.</div>
                </div>

                <button type="submit" class="btn-login">Log In &rarr;</button>
            </form>

            <p class="assist-text">Need help accessing your account? Contact the super admin.</p>
        </div>

        <div class="login-card-footer">
            <a href="../index.php" class="btn-home">&larr; Back to Home</a>
        </div>
    </div>
</div>

<script>
function togglePw(btn) {
    const input = document.getElementById('password');
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.textContent = input.type === 'password' ? '\u{1F441}' : '\u{1F648}';
    btn.setAttribute('aria-pressed', input.type === 'text' ? 'true' : 'false');
    btn.setAttribute('aria-label', input.type === 'text' ? 'Hide password' : 'Show password');
    btn.title = input.type === 'text' ? 'Hide password' : 'Show password';
}

const passwordInput = document.getElementById('password');
const capsWarning = document.getElementById('capsWarning');
const usernameInput = document.getElementById('username');

function updateCapsState(event) {
    if (event.getModifierState && event.getModifierState('CapsLock')) {
        capsWarning.classList.add('show');
    } else {
        capsWarning.classList.remove('show');
    }
}

passwordInput.addEventListener('keydown', updateCapsState);
passwordInput.addEventListener('keyup', updateCapsState);
passwordInput.addEventListener('blur', function() {
    capsWarning.classList.remove('show');
});

try {
    const lastUsername = localStorage.getItem('adminLastUsername');
    if (lastUsername && !usernameInput.value) {
        usernameInput.value = lastUsername;
    }
} catch (e) {
    // ignore storage access errors
}

document.querySelector('form').addEventListener('submit', function () {
    const btn = document.querySelector('.btn-login');
    try {
        localStorage.setItem('adminLastUsername', usernameInput.value.trim());
    } catch (e) {
        // ignore storage access errors
    }

    btn.disabled = true;
    btn.classList.add('loading');
    btn.textContent = 'Signing in…';
});
</script>
</body>
</html>