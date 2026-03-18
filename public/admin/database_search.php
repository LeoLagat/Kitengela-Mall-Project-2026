<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
if (empty($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'super_admin') {
    header('Location: dashboard.php');
    exit;
}

require_once(__DIR__ . '/../../backend/app/config/database.php');
require_once(__DIR__ . '/../../backend/app/services/AdminAudit.php');

$db = new DatabaseConnection();
$pdo = $db->pdo;

if (!empty($_SESSION['admin_username'])) {
    AdminAudit::log($pdo, $_SESSION['admin_username'], 'visited database search');
}

$tables = [
    'vehicle_logs' => [
        'label' => 'Vehicle Logs',
        'columns' => ['id', 'plate_number', 'bay_id', 'entry_time', 'exit_time', 'total_fee', 'payment_status', 'nominal_fee', 'paid_at'],
        'searchable' => ['plate_number', 'payment_status']
    ],
    'owner_accounts' => [
        'label' => 'Owner Accounts',
        'columns' => ['id', 'plate_number', 'owner_name', 'invoice_monthly', 'created_at'],
        'searchable' => ['plate_number', 'owner_name']
    ],
    'owner_vehicle_fees' => [
        'label' => 'Owner Vehicle Fees',
        'columns' => ['id', 'plate_number', 'owner_name', 'nominal_fee', 'discount_given', 'total_due', 'due_period', 'created_at'],
        'searchable' => ['plate_number', 'owner_name']
    ],
    'staff_vehicles' => [
        'label' => 'Staff Vehicles',
        'columns' => ['id', 'plate_number', 'employee_name', 'created_at'],
        'searchable' => ['plate_number', 'employee_name']
    ],
    'restricted_vehicles' => [
        'label' => 'Restricted Vehicles',
        'columns' => ['id', 'plate_number', 'reason', 'added_at'],
        'searchable' => ['plate_number', 'reason']
    ],
    'administrators' => [
        'label' => 'Administrators',
        'columns' => ['id', 'username', 'role', 'created_at'],
        'searchable' => ['username', 'role']
    ],
    'admin_activity' => [
        'label' => 'Admin Activity',
        'columns' => ['id', 'created_at', 'username', 'action', 'ip_address'],
        'searchable' => ['username', 'action', 'ip_address']
    ],
    'revenue_archive' => [
        'label' => 'Revenue Archive',
        'columns' => ['id', 'archived_revenue', 'archived_date', 'admin_who_cleared', 'log_count_cleared', 'notes'],
        'searchable' => ['admin_who_cleared', 'notes']
    ]
];

$selectedTable = $_GET['table'] ?? 'vehicle_logs';
if (!isset($tables[$selectedTable])) {
    $selectedTable = 'vehicle_logs';
}
$searchTerm = trim($_GET['q'] ?? '');
$limit = 100;

$selectedConfig = $tables[$selectedTable];
$columnsSql = implode(', ', $selectedConfig['columns']);
$sql = "SELECT $columnsSql FROM $selectedTable";
$params = [];

if ($searchTerm !== '') {
    $whereParts = [];
    foreach ($selectedConfig['searchable'] as $idx => $col) {
        $paramKey = ':term' . $idx;
        $whereParts[] = "$col LIKE $paramKey";
        $params[$paramKey] = '%' . $searchTerm . '%';
    }
    if (!empty($whereParts)) {
        $sql .= ' WHERE ' . implode(' OR ', $whereParts);
    }
}

if (in_array('id', $selectedConfig['columns'], true)) {
    $sql .= ' ORDER BY id DESC';
} elseif (in_array('created_at', $selectedConfig['columns'], true)) {
    $sql .= ' ORDER BY created_at DESC';
}
$sql .= ' LIMIT ' . $limit;

$rows = [];
$errorMessage = '';
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $errorMessage = 'Query failed: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Database Search</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .db-page {
            width: 95%;
            max-width: 1200px;
            margin: 40px auto 80px auto;
            display: grid;
            gap: 16px;
        }

        .db-hero {
            background: white;
            border: 1px solid lightgray;
            border-left: 8px solid darkgreen;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 8px 20px gainsboro;
        }

        .db-hero h2 {
            margin: 0;
            color: forestgreen;
            font-size: 32px;
        }

        .db-hero p {
            margin: 8px 0 0 0;
            color: dimgray;
        }

        .search-card {
            background: white;
            border: 1px solid lightgray;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 8px 20px gainsboro;
        }

        .search-form {
            display: grid;
            grid-template-columns: 1fr 2fr auto;
            gap: 10px;
        }

        .search-form select,
        .search-form input {
            border: 1px solid lightgray;
            border-radius: 8px;
            padding: 10px;
            font-size: 14px;
            box-sizing: border-box;
            width: 100%;
        }

        .search-form button {
            border: none;
            border-radius: 8px;
            background: darkgreen;
            color: white;
            font-weight: 700;
            padding: 10px 14px;
            cursor: pointer;
        }

        .search-form button:hover {
            background: seagreen;
        }

        .meta {
            margin-top: 10px;
            color: dimgray;
            font-size: 13px;
            font-weight: 600;
        }

        .table-wrap {
            overflow-x: auto;
            background: white;
            border: 1px solid lightgray;
            border-radius: 12px;
            padding: 10px;
            box-shadow: 0 8px 20px gainsboro;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
        }

        th, td {
            border: 1px solid lightgray;
            padding: 8px;
            text-align: left;
            font-size: 13px;
            vertical-align: top;
        }

        th {
            background: whitesmoke;
            color: darkslategray;
        }

        .warn {
            background: mistyrose;
            border: 1px solid lightcoral;
            color: maroon;
            border-radius: 8px;
            padding: 10px 12px;
        }

        .empty {
            background: floralwhite;
            border: 1px dashed burlywood;
            color: saddlebrown;
            border-radius: 8px;
            padding: 12px;
            font-weight: 600;
        }

        @media (max-width: 900px) {
            .search-form {
                grid-template-columns: 1fr;
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
        <a href="staff.php">Staff Parking</a>
        <a href="owners.php">Owner Vehicles</a>
        <a href="add_user.php">Manage Admins</a>
        <a href="activity.php">Activity Log</a>
        <a href="subadmin_activity.php">Sub-admin Logs</a>
        <a href="database_search.php" class="active">Database Search</a>
        <a href="logout.php" style="color:red;">Logout</a>
    </div>
</nav>

<main class="db-page">
    <section class="db-hero">
        <h2>Database Search</h2>
        <p>Search approved tables using a safe query interface. Results are read-only and limited to 100 rows.</p>
    </section>

    <section class="search-card">
        <form method="GET" class="search-form">
            <select name="table">
                <?php foreach ($tables as $tableName => $cfg): ?>
                    <option value="<?= htmlspecialchars($tableName) ?>" <?= $tableName === $selectedTable ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cfg['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search term (plate, username, owner, reason, etc)">
            <button type="submit">Search</button>
        </form>
        <p class="meta">Table: <?= htmlspecialchars($selectedConfig['label']) ?> | Rows shown: <?= count($rows) ?></p>
    </section>

    <?php if ($errorMessage !== ''): ?>
        <div class="warn"><?= htmlspecialchars($errorMessage) ?></div>
    <?php elseif (empty($rows)): ?>
        <div class="empty">No matching records found.</div>
    <?php else: ?>
        <section class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <?php foreach (array_keys($rows[0]) as $col): ?>
                            <th><?= htmlspecialchars($col) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($row as $value): ?>
                                <td><?= htmlspecialchars((string) $value) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
