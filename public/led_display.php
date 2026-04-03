<?php
// simple display page for LED/monitor showing only available parking spots
// this page does not require authentication; it should be placed on the display

require_once(__DIR__ . '/../backend/app/config/database.php');
$db = new DatabaseConnection();
$pdo = $db->pdo;

// count vacant bays by floor
$stmt = $pdo->prepare("SELECT floor_level, COUNT(*) AS cnt
    FROM parking_bays
    WHERE current_status = 'vacant'
    GROUP BY floor_level");
$stmt->execute();
$vacantByFloor = $stmt->fetchAll(PDO::FETCH_ASSOC);

// auto-refresh every 10 seconds
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="10">
    <title>Available Bays</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: black; color: lime; margin:0; padding:0; }
        h1 { font-size: 3.5em; margin:0; letter-spacing: 2px; }
        .header-container { display:flex; justify-content:center; align-items:center; height: 15vh; }
        .floors { display: flex; justify-content: flex-start; flex-wrap: nowrap; gap: 50px; margin: 60px 20px; overflow-x: auto; padding-bottom:20px; align-items: flex-start; }
        .floor-wrap { display: flex; flex-direction: column; align-items: center; }
.floor-wrap { display: flex; flex-direction: column; align-items: center; }
        .floors::-webkit-scrollbar { height: 12px; }
        .floors::-webkit-scrollbar-thumb { background: dimgray; border-radius: 6px; }
        .floor-block { background: royalblue; color: white; padding: 40px 60px; min-width: 400px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
        .floor-title { font-size: 1.8em; margin-bottom: 10px; text-align:center; color: white; font-weight: bold; }
        .floor-count { font-size: 2.5em; text-align:center; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header-container">
        <h1>Available Parking Bays</h1>
    </div>
    <div class="floors">
        <?php if (count($vacantByFloor) > 0): ?>
            <?php foreach($vacantByFloor as $row): ?>
                <div class="floor-wrap">
                    <div class="floor-title"><?=htmlspecialchars($row['floor_level']);?></div>
                    <div class="floor-block">
                        <div class="floor-count"><?=intval($row['cnt']);?> AVAILABLE SPACE</div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="floor-block">
                <div class="floor-count">None</div>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>