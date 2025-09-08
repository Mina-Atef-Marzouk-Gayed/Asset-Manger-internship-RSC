<?php
include 'db.php';

$serial = trim($_GET['serial'] ?? '');
$params = [];
$where = '';
if ($serial !== '') {
    $where = " WHERE a.SerialNumber LIKE ?";
    $like = "%$serial%";
}

// Get sort parameters
$sort_column = $_GET['sort'] ?? 'ActionDate';
$sort_order = $_GET['order'] ?? 'DESC';

// Validate sort column
$allowed_columns = [ 'SerialNumber', 'OldValue', 'NewValue', 'ActionDate'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'ActionDate';
}

// Validate sort order
$sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

// Build ORDER BY clause
$order_by = '';
switch($sort_column) {

    case 'SerialNumber':
        $order_by = "a.SerialNumber $sort_order";
        break;
    case 'OldValue':
        $order_by = "h.OldValue $sort_order";
        break;
    case 'NewValue':
        $order_by = "h.NewValue $sort_order";
        break;
    case 'ActionDate':
        $order_by = "h.ActionDate $sort_order";
        break;
    default:
        $order_by = "h.ActionDate DESC";
}

// Fetch history records with asset serial numbers
$query = "
    SELECT a.SerialNumber, h.OldValue, h.NewValue, h.ActionDate
    FROM assethistory h
    JOIN assets a ON a.AssetID = h.AssetID
" . $where . "
    AND h.OldValue LIKE '%:%' AND h.NewValue LIKE '%:%'
    ORDER BY $order_by
";

if ($serial !== '') {
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $rows = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
}

// Function to generate sort URL
function getSortUrl($column, $current_sort, $current_order, $serial = '') {
    $new_order = ($current_sort === $column && $current_order === 'ASC') ? 'DESC' : 'ASC';
    $url = "?sort=$column&order=$new_order";
    if ($serial !== '') {
        $url .= "&serial=" . urlencode($serial);
    }
    return $url;
}

// Function to get sort icon
function getSortIcon($column, $current_sort, $current_order) {
    if ($current_sort === $column) {
        return $current_order === 'ASC' ? '↑' : '↓';
    }
    return '↕';
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Asset History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    .sortable { cursor: pointer; }
    .sortable:hover { background-color: #f8f9fa; }
    .sort-icon { margin-left: 5px; color: #6c757d; }
    </style>
</head>
<body class="container py-4">
    <h2>Asset History specs only</h2>
    <h5>View the asset history (Working / In Repair / Trash) with notes inside the asset details.</h5>
    <p><a class="btn btn-sm btn-secondary" href="index.php">Back</a></p>

    <form method="get" class="row g-2 mb-3">
        <div class="col-auto">
            <input type="text" name="serial" class="form-control" placeholder="Search by serial" value="<?= htmlspecialchars($serial) ?>">
        </div>
        <div class="col-auto">
            <button class="btn btn-primary">Search</button>
        </div>
        <?php if ($serial !== ''): ?>
        <div class="col-auto">
            <a class="btn btn-outline-secondary" href="history.php">Clear</a>
        </div>
        <?php endif; ?>
    </form>

    <table class="table table-striped table-bordered">
        <thead>
            <tr>

            
                <th class="sortable" onclick="window.location.href='<?= getSortUrl('SerialNumber', $sort_column, $sort_order, $serial) ?>'">
                    Asset Serial <span class="sort-icon"><?= getSortIcon('SerialNumber', $sort_column, $sort_order) ?></span>
                </th>
                <th class="sortable" onclick="window.location.href='<?= getSortUrl('OldValue', $sort_column, $sort_order, $serial) ?>'">
                    Old Value <span class="sort-icon"><?= getSortIcon('OldValue', $sort_column, $sort_order) ?></span>
                </th>
                <th class="sortable" onclick="window.location.href='<?= getSortUrl('NewValue', $sort_column, $sort_order, $serial) ?>'">
                    New Value <span class="sort-icon"><?= getSortIcon('NewValue', $sort_column, $sort_order) ?></span>
                </th>
                <th class="sortable" onclick="window.location.href='<?= getSortUrl('ActionDate', $sort_column, $sort_order, $serial) ?>'">
                    Action Date <span class="sort-icon"><?= getSortIcon('ActionDate', $sort_column, $sort_order) ?></span>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    
               
                    <td><?= htmlspecialchars($r['SerialNumber']) ?></td>
                    <td><?= htmlspecialchars($r['OldValue']) ?></td>
                    <td><?= htmlspecialchars($r['NewValue']) ?></td>
                    <td><?= htmlspecialchars($r['ActionDate']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="text-center text-muted">No results</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
