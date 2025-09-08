<?php
include 'db.php';

$msg = '';
$error = '';

// Get database name from connection
$database_name = $conn->query("SELECT DATABASE() as db_name")->fetch_assoc()['db_name'] ?? '';

// Handle backup request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $include_data = isset($_POST['include_data']);
    try {
        downloadSQLBackup($conn, $include_data, $database_name);
    } catch (Exception $e) {
        $error = 'Backup failed: ' . $e->getMessage();
    }
}

// SQL Backup function
function downloadSQLBackup($conn, $include_data, $database_name) {
    $filename = 'asset_manager_backup_' . date('Y-m-d_H-i-s') . '.sql';

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

    echo "-- Asset Manager Database Backup\n";
    echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "-- Database: " . $database_name . "\n\n";

    // Get all tables
    $tables = $conn->query("SHOW TABLES")->fetch_all(MYSQLI_ASSOC);

    foreach ($tables as $table) {
        $table_name = array_values($table)[0];

        // Table structure
        $create_table = $conn->query("SHOW CREATE TABLE `$table_name`")->fetch_assoc();
        echo "\n-- Table structure for `$table_name`\n";
        echo "DROP TABLE IF EXISTS `$table_name`;\n";
        echo $create_table['Create Table'] . ";\n\n";

        // Table data
        if ($include_data) {
            $data = $conn->query("SELECT * FROM `$table_name`")->fetch_all(MYSQLI_ASSOC);
            if (!empty($data)) {
                echo "-- Data for `$table_name`\n";
                foreach ($data as $row) {
                    $values = array_map(function($value) {
                        return $value === null ? 'NULL' : "'" . addslashes($value) . "'";
                    }, $row);
                    echo "INSERT INTO `$table_name` VALUES (" . implode(', ', $values) . ");\n";
                }
                echo "\n";
            }
        }
    }

    exit;
}

// Optional: fetch database stats to display on page
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_tables,
        SUM(table_rows) as total_rows,
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as total_size_mb
    FROM information_schema.tables 
    WHERE table_schema = '" . $conn->real_escape_string($database_name) . "'
")->fetch_assoc();

$table_list = $conn->query("
    SELECT 
        table_name AS table_name,
        table_rows AS table_rows,
        ROUND((data_length + index_length) / 1024 / 1024, 2) as size_mb
    FROM information_schema.tables 
    WHERE table_schema = '" . $conn->real_escape_string($database_name) . "' 
    ORDER BY table_name
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Database Backup - Asset Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <div class="mb-4">
        <h2>Database Backup (SQL Only)</h2>
        <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="mb-4">
        <form method="post">
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="include_data" id="include_data" checked>
                <label class="form-check-label" for="include_data">Include Data</label>
            </div>
            <button type="submit" class="btn btn-primary">Download SQL Backup</button>
        </form>
    </div>

    <div class="card">
        <div class="card-header">Database Tables Overview</div>
        <div class="card-body">
            <p><strong>Total Tables:</strong> <?= $stats['total_tables'] ?? 0 ?> |
               <strong>Total Rows:</strong> <?= $stats['total_rows'] ?? 0 ?> |
               <strong>Total Size:</strong> <?= $stats['total_size_mb'] ?? 0 ?> MB</p>

            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Table Name</th>
                        <th>Records</th>
                        <th>Size (MB)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($table_list as $table): ?>
                        <tr>
                            <td><?= htmlspecialchars($table['table_name'] ?? '') ?></td>
                            <td><?= number_format($table['table_rows'] ?? 0) ?></td>
                            <td><?= $table['size_mb'] ?? 0 ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
