<?php
session_start();

// log logout if we know who is logged in
if (!empty($_SESSION['admin_username'])) {
    require_once(__DIR__ . '/../../backend/app/config/database.php');
    require_once(__DIR__ . '/../../backend/app/services/AdminAudit.php');
    $db = new DatabaseConnection();
    $pdo = $db->pdo;
    AdminAudit::log($pdo, $_SESSION['admin_username'], 'logout');
}

// fully clear session data and cookie
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}
session_destroy();

$redirectUrl = 'login.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Signed Out</title>
    <meta http-equiv="refresh" content="2;url=login.php">
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, darkgreen, seagreen);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: darkslategray;
            padding: 20px;
        }

        .logout-card {
            width: 100%;
            max-width: 460px;
            background: white;
            border: 1px solid lightgray;
            border-radius: 14px;
            padding: 30px 24px;
            text-align: center;
            box-shadow: 0 14px 32px rgba(0, 0, 0, 0.2);
            animation: fade-in 0.25s ease-out;
        }

        .logout-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 12px;
            border-radius: 50%;
            background: honeydew;
            border: 2px solid palegreen;
            color: darkgreen;
            display: grid;
            place-items: center;
            font-size: 30px;
            font-weight: 700;
        }

        h1 {
            margin: 0;
            font-size: 24px;
            color: darkgreen;
        }

        p {
            margin: 10px 0 0;
            color: dimgray;
            font-size: 14px;
        }

        .progress {
            margin-top: 18px;
            background: gainsboro;
            border-radius: 999px;
            height: 8px;
            overflow: hidden;
        }

        .progress > span {
            display: block;
            height: 100%;
            width: 100%;
            background: linear-gradient(90deg, seagreen, darkgreen);
            animation: shrink 2s linear forwards;
        }

        .actions {
            margin-top: 18px;
        }

        .btn {
            display: inline-block;
            text-decoration: none;
            background: darkgreen;
            color: white;
            border-radius: 8px;
            padding: 10px 16px;
            font-weight: 600;
            font-size: 14px;
        }

        .btn:hover {
            background: seagreen;
        }

        @keyframes shrink {
            from { width: 100%; }
            to { width: 0%; }
        }

        @keyframes fade-in {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <main class="logout-card" role="status" aria-live="polite">
        <div class="logout-icon">&#10003;</div>
        <h1>Signed Out</h1>
        <p>Your admin session has ended successfully.</p>
        <p>Redirecting to login page...</p>

        <div class="progress" aria-hidden="true"><span></span></div>

        <div class="actions">
            <a class="btn" href="<?= htmlspecialchars($redirectUrl) ?>">Go to Login</a>
        </div>
    </main>

    <script>
    window.setTimeout(function () {
        window.location.replace('<?= htmlspecialchars($redirectUrl) ?>');
    }, 2000);
    </script>
</body>
</html>