<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Admin Password - Kitengela Mall</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .dashboard-container {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            gap: 32px;
            justify-content: center;
            margin-bottom: 32px;
        }
        .dashboard-box {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(44,130,44,0.08);
            padding: 24px 36px;
            min-width: 220px;
            text-align: center;
            border: 1.5px solid #e0e0e0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .dashboard-box h3 {
            color: #2d862d;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        .dashboard-box p {
            font-size: 32px;
            font-weight: 700;
            color: #34495e;
            margin: 0;
        }
        @media (max-width: 900px) {
            .dashboard-container {
                flex-direction: column;
                gap: 18px;
                align-items: center;
            }
            .dashboard-box {
                min-width: 90vw;
                max-width: 98vw;
                padding: 18px 8px;
            }
        }
    </style>
</head>
<body>
<nav>
    <div class="logo">Admin Panel</div>
    <div class="links">
        <a href="../index.php">Home</a>
        <a href="dashboard.php">Dashboard</a>
        <a href="logout.php" style="color:#f00;">Logout</a>
    </div>
</nav>
<div class="container">
    <h2>Reset Admin Password</h2>
    <div class="dashboard-container">
        <div class="dashboard-box">
            <h3>New Password</h3>
            <p><?php echo htmlspecialchars($newPassword); ?></p>
        </div>
        <div class="dashboard-box">
            <h3>SQL Statement</h3>
            <p style="font-size:16px;word-break:break-all;">
                UPDATE administrators SET password = '<?php echo addslashes($newPassword); ?>' WHERE username = 'admin';
            </p>
        </div>
    </div>
</div>
</body>
</html>