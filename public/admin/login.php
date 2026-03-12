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
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0a3d0a 0%, #1a6b1a 55%, #145214 100%);
            padding: 20px;
        }

        .login-shell {
            width: 100%;
            max-width: 420px;
        }

        /* branding */
        .login-brand {
            text-align: center;
            margin-bottom: 28px;
        }
        .login-brand .brand-icon {
            width: 62px;
            height: 62px;
            background: rgba(255,255,255,0.15);
            border: 2px solid rgba(255,255,255,0.35);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 12px;
            backdrop-filter: blur(4px);
        }
        .login-brand h1 {
            color: #fff;
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 4px;
            letter-spacing: .5px;
        }
        .login-brand p {
            color: rgba(255,255,255,.7);
            font-size: 13px;
            margin: 0;
        }

        /* card */
        .login-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.30);
            overflow: hidden;
        }
        .login-card-body {
            padding: 36px 36px 30px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #1a401a;
            margin: 0 0 22px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }

        /* alert */
        .alert-error {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-left: 4px solid #dc2626;
            border-radius: 7px;
            font-size: 14px;
            color: #991b1b;
            margin-bottom: 20px;
            animation: shake .35s ease;
        }
        @keyframes shake {
            0%,100%{transform:translateX(0)}
            25%{transform:translateX(-6px)}
            75%{transform:translateX(6px)}
        }

        /* fields */
        .field-group {
            margin-bottom: 18px;
        }
        .field-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        .input-wrap {
            position: relative;
        }
        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 15px;
            color: #9ca3af;
            pointer-events: none;
        }
        .field-group input {
            width: 100%;
            padding: 10px 12px 10px 38px;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            color: #111;
            background: #f9fafb;
            transition: border-color .2s, box-shadow .2s, background .2s;
        }
        .field-group input:focus {
            outline: none;
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(22,163,74,.15);
            background: #fff;
        }
        .toggle-pw {
            position: absolute;
            right: 11px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 15px;
            color: #9ca3af;
            padding: 0;
            line-height: 1;
            transition: color .2s;
        }
        .toggle-pw:hover { color: #374151; }

        /* submit */
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(90deg, #0a5a0a 0%, #1a8a1a 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: .4px;
            transition: opacity .2s, transform .1s;
            margin-top: 6px;
        }
        .btn-login:hover  { opacity: .88; }
        .btn-login:active { transform: scale(.98); }

        /* footer */
        .login-card-footer {
            padding: 14px 36px 20px;
            border-top: 1px solid #f3f4f6;
            display: flex;
            justify-content: center;
        }
        .btn-home {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #6b7280;
            text-decoration: none;
            padding: 6px 14px;
            border-radius: 6px;
            transition: background .2s, color .2s;
        }
        .btn-home:hover { background: #f3f4f6; color: #111; }
    </style>
</head>
<body>

<div class="login-shell">
    <!-- Branding -->
    <div class="login-brand">
        <div class="brand-icon">&#128663;</div>
        <h1>Kitengela Parking</h1>
        <p>Administrator Portal</p>
    </div>

    <!-- Card -->
    <div class="login-card">
        <div class="login-card-body">
            <div class="section-title">&#128274;&nbsp;Sign in</div>

            <?php if ($error): ?>
            <div class="alert-error">
                <span>&#9888;</span>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off" novalidate>
                <div class="field-group">
                    <label for="username">Username</label>
                    <div class="input-wrap">
                        <span class="input-icon">&#128100;</span>
                        <input type="text" id="username" name="username"
                               placeholder="Enter your username"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               required autofocus>
                    </div>
                </div>

                <div class="field-group">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <span class="input-icon">&#128274;</span>
                        <input type="password" id="password" name="password"
                               placeholder="Enter your password" required>
                        <button type="button" class="toggle-pw" onclick="togglePw(this)" title="Show / hide password">&#128065;</button>
                    </div>
                </div>

                <button type="submit" class="btn-login">Log In &rarr;</button>
            </form>
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
}
</script>
</body>
</html>