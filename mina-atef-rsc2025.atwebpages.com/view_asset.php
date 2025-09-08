<?php
include 'db.php'; 
$id = (int)($_GET['id'] ?? 0);

// Get asset details with type and assigned employee
$asset = $conn->query("
    SELECT a.AssetID, a.SerialNumber, a.TagNumber, a.Status, t.TypeName, e.Name AS EmpName
    FROM assets a
    JOIN assettypes t ON t.TypeID = a.TypeID
    LEFT JOIN employees e ON e.EmployeeID = a.AssignedTo
    WHERE a.AssetID = $id
")->fetch_assoc();

// Get latest assignment dates
$assignment = $conn->query("
    SELECT DateReceived, DateReturned
    FROM asset_assignments
    WHERE AssetID = $id
    ORDER BY AssignmentID DESC
    LIMIT 1
")->fetch_assoc();

$dRecv = !empty($assignment['DateReceived']) ? substr($assignment['DateReceived'],0,10) : '';
$dRet  = !empty($assignment['DateReturned']) ? substr($assignment['DateReturned'],0,10) : '';

// Specifications
$sort_column = $_GET['sort'] ?? 'Name';
$sort_order = $_GET['order'] ?? 'ASC';
$allowed_columns = ['Name', 'ValueText'];
if (!in_array($sort_column, $allowed_columns)) { $sort_column = 'Name'; }
$sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

$order_by = ($sort_column === 'ValueText') ? "s.ValueText $sort_order" : "aa.Name $sort_order";

$specs = $conn->prepare("
    SELECT s.*, aa.Name 
    FROM assetspecs s
    JOIN assetattributes aa ON aa.AttributeID = s.AttributeID
    WHERE s.AssetID = ?
    ORDER BY $order_by
");
$specs->bind_param('i',$id);
$specs->execute();
$sp = $specs->get_result()->fetch_all(MYSQLI_ASSOC);

function getSortUrl($column, $current_sort, $current_order, $asset_id) {
    $new_order = ($current_sort === $column && $current_order === 'ASC') ? 'DESC' : 'ASC';
    return "?id=$asset_id&sort=$column&order=$new_order";
}
function getSortIcon($column, $current_sort, $current_order) {
    if ($current_sort === $column) return $current_order === 'ASC' ? '↑' : '↓';
    return '↕';
}
?>
<!doctype html>
<html>
<head>
<meta charset='utf-8'>
<title>View Asset</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
.sortable { cursor: pointer; }
.sortable:hover { background-color: #f8f9fa; }
.sort-icon { margin-left: 5px; color: #6c757d; }
</style>
</head>
<body class="container py-4">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Asset #<?= $id ?></h2>
    <a class="btn btn-secondary" href="assets.php"><i class="fas fa-arrow-left"></i> Back to Assets</a>
</div>

<?php if(!$asset){ echo '<div class="alert alert-danger">Not found</div>'; exit;} ?>

<!-- Asset Details -->
<div class="card mb-4">
    <div class="card-header"><h4><i class="fas fa-info-circle"></i> Asset Details</h4></div>
    <div class="card-body">
        <table class="table table-borderless">
            <tr><th>Type</th><td><?=htmlspecialchars($asset['TypeName'])?></td></tr>
            <tr><th>Serial Number</th><td><?=htmlspecialchars($asset['SerialNumber'])?></td></tr>
            <tr><th>Tag Number</th><td><?=htmlspecialchars($asset['TagNumber'] ?? '-')?></td></tr>
            <tr><th>Status</th><td><?=htmlspecialchars($asset['Status'])?></td></tr>
            <tr><th>Assigned To</th><td><?= htmlspecialchars($asset['EmpName'] ?? 'Not assigned') ?></td></tr>
            <tr><th>Date Received</th><td><?=htmlspecialchars($dRecv)?></td></tr>
            <tr><th>Date Returned</th><td><?=htmlspecialchars($dRet)?></td></tr>
        </table>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mb-4">
    <div class="card-header"><h4><i class="fas fa-tools"></i> Quick Actions</h4></div>
    <div class="card-body">
        <div class="btn-group" role="group">
            <a href="edit_asset.php?id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Edit Asset</a>
            <a href="view_asset.php?id=<?= $id ?>" class="btn btn-info"><i class="fas fa-refresh"></i> Refresh</a>
        </div>
    </div>
</div>

<!-- Specifications -->
<div class="card mb-4">
    <div class="card-header"><h4><i class="fas fa-cogs"></i> Specifications</h4></div>
    <div class="card-body">
        <?php if (empty($sp)): ?>
            <p class="text-muted">No specifications found for this asset.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th class="sortable" onclick="window.location.href='<?= getSortUrl('Name', $sort_column, $sort_order, $id) ?>'">
                            Attribute Name <span class="sort-icon"><?= getSortIcon('Name', $sort_column, $sort_order) ?></span>
                        </th>
                        <th class="sortable" onclick="window.location.href='<?= getSortUrl('ValueText', $sort_column, $sort_order, $id) ?>'">
                            Value <span class="sort-icon"><?= getSortIcon('ValueText', $sort_column, $sort_order) ?></span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($sp as $s): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['Name']) ?></strong></td>
                            <td><?= htmlspecialchars($s['ValueText']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Asset History -->
<div class="card mb-4">
    <div class="card-header"><h4><i class="fas fa-history"></i> Asset History</h4></div>
    <div class="card-body">
        <?php
        $hist = $conn->prepare("SELECT OldValue, NewValue, ActionDate, Notes FROM assethistory WHERE AssetID=? ORDER BY ActionDate DESC");
        $hist->bind_param('i',$id);
        $hist->execute();
        $his = $hist->get_result();

        if($his->num_rows==0){
            echo "<p class='text-muted'>No history found.</p>";
        }else{
            echo "<div class='table-responsive'>
                    <table class='table table-bordered table-striped'>
                        <thead class='table-dark'>
                            <tr>
                                <th>Old Value</th>
                                <th>New Value</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>";
            while($row=$his->fetch_assoc()){
                echo "<tr>
                        <td>".htmlspecialchars($row['OldValue'])."</td>
                        <td>".htmlspecialchars($row['NewValue'])."</td>
                        <td>".htmlspecialchars($row['Notes'])."</td>
                      </tr>";
            }
            echo "</tbody></table></div>";
        }
        ?>
    </div>
</div>

</body>
</html>
