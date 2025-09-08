<?php
include 'db.php';

$asset_id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$error = '';
$asset_details = null;

// Fetch asset details
if ($asset_id) {
    $stmt = $conn->prepare("
        SELECT a.*, t.TypeName, e.Name AS EmpName 
        FROM assets a 
        JOIN assettypes t ON t.TypeID = a.TypeID 
        LEFT JOIN employees e ON e.EmployeeID = a.AssignedTo 
        WHERE a.AssetID = ?
    ");
    $stmt->bind_param("i", $asset_id);
    $stmt->execute();
    $asset_details = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$asset_details) {
    $error = "Asset not found.";
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $asset_id && $asset_details) {
    $stmt = $conn->prepare("DELETE FROM asset_assignments WHERE AssetID = ?");
    $stmt->bind_param("i", $asset_id); $stmt->execute(); $stmt->close();

    $stmt = $conn->prepare("DELETE FROM assetspecs WHERE AssetID = ?");
    $stmt->bind_param("i", $asset_id); $stmt->execute(); $stmt->close();

    $stmt = $conn->prepare("DELETE FROM assets WHERE AssetID = ?");
    $stmt->bind_param("i", $asset_id); $stmt->execute(); $stmt->close();

    header("Location: assets.php?msg=Asset deleted successfully");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Delete Asset #<?= $asset_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">

<h2>Delete Asset #<?= $asset_id ?></h2>
<a href="assets.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Assets</a>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($asset_details): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-danger text-white">
            <strong>Asset Details</strong>
        </div>
        <div class="card-body">
            <p><strong>Serial Number:</strong> <?= htmlspecialchars($asset_details['SerialNumber']) ?></p>
            <p><strong>Tag Number:</strong> <?= htmlspecialchars($asset_details['TagNumber'] ?? '-') ?></p>
            <p><strong>Type:</strong> <?= htmlspecialchars($asset_details['TypeName']) ?></p>
            <p><strong>Status:</strong> <?= htmlspecialchars($asset_details['Status']) ?></p>
            <p><strong>Assigned To:</strong> <?= htmlspecialchars($asset_details['EmpName'] ?? 'Unassigned') ?></p>
            <p><strong>Date Created:</strong> <?= htmlspecialchars(substr($asset_details['DateCreated'] ?? '', 0, 10)) ?></p>
        </div>
        <div class="card-footer">
            <form method="post" onsubmit="return confirm('Are you sure you want to delete this asset?');" class="d-inline">
                <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Delete Asset</button>
            </form>
            <a href="assets.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
        </div>
    </div>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html>
