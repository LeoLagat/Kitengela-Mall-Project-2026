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

if (empty($_SESSION['db_clear_token'])) {
    $_SESSION['db_clear_token'] = bin2hex(random_bytes(16));
}

if (!empty($_SESSION['admin_username'])) {
    AdminAudit::log($pdo, $_SESSION['admin_username'], 'visited database search');
}

$tables = [
    'vehicle_logs' => [
        'label' => 'Vehicle Logs',
        'columns' => ['id', 'plate_number', 'bay_id', 'entry_time', 'exit_time', 'total_fee', 'payment_status', 'mpesa_checkout_id', 'phone_number', 'nominal_fee', 'paid_at', 'is_manual_bypass', 'bypassed_by', 'bypassed_at'],
        'searchable' => ['plate_number', 'payment_status', 'mpesa_checkout_id', 'phone_number', 'bypassed_by']
    ],
    'mpesa_transactions' => [
        'label' => 'MPESA Transactions',
        'columns' => ['id', 'log_id', 'plate_number', 'phone_number', 'amount', 'status', 'checkout_id', 'receipt_number', 'created_at'],
        'searchable' => ['plate_number', 'phone_number', 'status', 'checkout_id', 'receipt_number']
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

$clearableTables = [
    'vehicle_logs',
    'mpesa_transactions',
    'owner_accounts',
    'owner_vehicle_fees',
    'staff_vehicles',
    'restricted_vehicles',
    'admin_activity',
    'revenue_archive'
];

$bulkClearableTables = array_values(array_filter(
    $clearableTables,
    static fn ($tableName) => $tableName !== 'revenue_archive'
));

$clearMessage = '';
$clearError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_table_data') {
    $token = $_POST['token'] ?? '';
    $clearTarget = $_POST['clear_target'] ?? '';

    if (!hash_equals($_SESSION['db_clear_token'], $token)) {
        $clearError = 'Invalid clear request. Please refresh and try again.';
    } else {
        $tablesToClear = [];

        if ($clearTarget === '__all__') {
            $tablesToClear = $bulkClearableTables;
        } elseif (in_array($clearTarget, $clearableTables, true)) {
            $tablesToClear = [$clearTarget];
        } else {
            $clearError = 'Selected table is not allowed to be cleared here.';
        }

        if ($clearError === '') {
            $archiveVehicleRevenue = in_array('vehicle_logs', $tablesToClear, true)
                && !in_array('revenue_archive', $tablesToClear, true);

            $deleteOrder = [
                'mpesa_transactions',
                'owner_vehicle_fees',
                'owner_accounts',
                'staff_vehicles',
                'restricted_vehicles',
                'admin_activity',
                'vehicle_logs',
                'revenue_archive'
            ];

            try {
                $pdo->beginTransaction();

                $archiveSummary = null;
                if ($archiveVehicleRevenue) {
                    $stmtRevenue = $pdo->prepare(
                        "SELECT COALESCE(SUM(total_fee), 0) AS total_revenue, COUNT(*) AS log_count
                         FROM vehicle_logs
                         WHERE payment_status IN ('paid', 'invoiced')"
                    );
                    $stmtRevenue->execute();
                    $archiveSummary = $stmtRevenue->fetch(PDO::FETCH_ASSOC) ?: ['total_revenue' => 0, 'log_count' => 0];

                    if ((float) $archiveSummary['total_revenue'] > 0 || (int) $archiveSummary['log_count'] > 0) {
                        $stmtArchive = $pdo->prepare(
                            "INSERT INTO revenue_archive (archived_revenue, admin_who_cleared, log_count_cleared, notes)
                             VALUES (?, ?, ?, ?)"
                        );
                        $stmtArchive->execute([
                            (float) $archiveSummary['total_revenue'],
                            $_SESSION['admin_username'] ?? 'unknown',
                            (int) $archiveSummary['log_count'],
                            'Archived automatically from Database Search clear action'
                        ]);
                    }
                }

                $clearedLabels = [];
                foreach ($deleteOrder as $tableName) {
                    if (!in_array($tableName, $tablesToClear, true)) {
                        continue;
                    }

                    $pdo->exec("DELETE FROM {$tableName}");
                    $clearedLabels[] = $tables[$tableName]['label'];
                }

                if (!empty($_SESSION['admin_username'])) {
                    $auditMessage = 'cleared table data from: ' . implode(', ', $clearedLabels);
                    if ($archiveVehicleRevenue && $archiveSummary !== null) {
                        $auditMessage .= ' (archived Ksh ' . number_format((float) $archiveSummary['total_revenue'], 2) . ')';
                    }
                    AdminAudit::log($pdo, $_SESSION['admin_username'], $auditMessage);
                }

                $pdo->commit();

                $clearMessage = 'Cleared: ' . implode(', ', $clearedLabels) . '.';
                if ($archiveVehicleRevenue && $archiveSummary !== null) {
                    $clearMessage .= ' Vehicle log revenue was archived before clearing.';
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $clearError = 'Clear failed: ' . $e->getMessage();
            }
        }
    }
}

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

        .danger-toggle {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: none;
            border: 1px solid lightcoral;
            border-radius: 6px;
            color: firebrick;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 10px;
            cursor: pointer;
            opacity: 0.7;
            margin-bottom: 6px;
        }

        .danger-toggle:hover {
            opacity: 1;
            background: mistyrose;
        }

        .danger-card {
            background: white;
            border: 1px solid lightcoral;
            border-left: 8px solid firebrick;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 8px 20px gainsboro;
        }

        .danger-card h3 {
            margin: 0 0 8px 0;
            color: firebrick;
        }

        .danger-card p {
            margin: 0 0 12px 0;
            color: dimgray;
        }

        .danger-form {
            display: grid;
            grid-template-columns: 2fr auto;
            gap: 10px;
            align-items: center;
        }

        .danger-form select {
            border: 1px solid lightgray;
            border-radius: 8px;
            padding: 10px;
            font-size: 14px;
            width: 100%;
        }

        .danger-button {
            border: none;
            border-radius: 8px;
            background: firebrick;
            color: white;
            font-weight: 700;
            padding: 10px 14px;
            cursor: pointer;
        }

        .danger-button:hover {
            background: brown;
        }

        .ok {
            background: honeydew;
            border: 1px solid palegreen;
            color: darkgreen;
            border-radius: 8px;
            padding: 10px 12px;
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
            <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search term (plate, phone number, receipt, username, owner, reason)">
            <button type="submit">Search</button>
        </form>
        <p class="meta">Table: <?= htmlspecialchars($selectedConfig['label']) ?> | Rows shown: <?= count($rows) ?></p>
    </section>

    <button class="danger-toggle" onclick="toggleDangerCard()" id="dangerToggleBtn">&#9656; Clear Table Data</button>

    <section class="danger-card" id="dangerCard" style="display:none;">
        <h3>Clear Table Data</h3>
        <p>Delete records from one selected table or all clearable tables. The Administrators table is protected, and the bulk clear option preserves Revenue Archive history.</p>
        <form method="POST" class="danger-form" onsubmit="return confirm('This will permanently delete data from the selected table set. Continue?');">
            <input type="hidden" name="action" value="clear_table_data">
            <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['db_clear_token']) ?>">
            <select name="clear_target" required>
                <option value="<?= htmlspecialchars($selectedTable) ?>">Selected table: <?= htmlspecialchars($selectedConfig['label']) ?></option>
                <option value="__all__">All clearable tables except Revenue Archive</option>
                <?php foreach ($clearableTables as $tableName): ?>
                    <?php if ($tableName === $selectedTable): ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <option value="<?= htmlspecialchars($tableName) ?>"><?= htmlspecialchars($tables[$tableName]['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="danger-button">Clear Data</button>
        </form>
    </section>

    <?php if ($clearMessage !== ''): ?>
        <div class="ok"><?= htmlspecialchars($clearMessage) ?></div>
    <?php endif; ?>

    <?php if ($clearError !== ''): ?>
        <div class="warn"><?= htmlspecialchars($clearError) ?></div>
    <?php endif; ?>

    <script>
        <?php if ($clearMessage !== '' || $clearError !== ''): ?>
        // Keep panel open after a clear action so admin sees the result
        document.getElementById('dangerCard').style.display = 'block';
        document.getElementById('dangerToggleBtn').innerHTML = '&#9662; Clear Table Data';
        <?php endif; ?>

        function toggleDangerCard() {
            const card = document.getElementById('dangerCard');
            const btn  = document.getElementById('dangerToggleBtn');
            if (card.style.display === 'none') {
                card.style.display = 'block';
                btn.innerHTML = '&#9662; Clear Table Data';
            } else {
                card.style.display = 'none';
                btn.innerHTML = '&#9656; Clear Table Data';
            }
        }
    </script>

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
