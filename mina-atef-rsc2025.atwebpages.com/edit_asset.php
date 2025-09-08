<?php
include 'db.php';
include 'asset_validation.php'; // contains convertToMySQLDate, validateDate, checkAssignmentOverlap, formatOverlapErrorMessage

$asset_id = (int) ($_GET['id'] ?? 0);

// --- Fetch asset info ---
function getAsset($conn, $asset_id) {
    $stmt = $conn->prepare("
        SELECT a.*, e.Name AS EmployeeName, t.TypeName
        FROM assets a
        LEFT JOIN employees e ON a.AssignedTo = e.EmployeeID
        LEFT JOIN assettypes t ON a.TypeID = t.TypeID
        WHERE a.AssetID = ?
    ");
    $stmt->bind_param("i", $asset_id);
    $stmt->execute();
    $asset = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $asset;
}

// --- Fetch asset specs ---
function getSpecs($conn, $asset_id) {
    $stmt = $conn->prepare("
        SELECT s.SpecID, s.ValueText, at.Name AS AttributeName, at.DataType AS AttributeType
        FROM assetspecs s
        JOIN assetattributes at ON s.AttributeID = at.AttributeID
        WHERE s.AssetID = ?
    ");
    $stmt->bind_param("i", $asset_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $specs = [];
    while ($row = $res->fetch_assoc()) $specs[] = $row;
    $stmt->close();
    return $specs;
}

// --- Fetch all employees ---
$allEmployees = $conn->query("SELECT EmployeeID, Name FROM employees")->fetch_all(MYSQLI_ASSOC);

// --- Fetch asset and specs ---
$asset = getAsset($conn, $asset_id);
if (!$asset) die("Asset not found!");
$specs = getSpecs($conn, $asset_id);

// --- Fetch current assignment ---
$stmtCur = $conn->prepare("
    SELECT aa.AssignmentID, aa.EmployeeID, e.Name as EmployeeName, aa.DateReceived, aa.DateReturned
    FROM asset_assignments aa
    JOIN employees e ON aa.EmployeeID = e.EmployeeID
    WHERE aa.AssetID=? AND aa.DateReturned IS NULL
    ORDER BY aa.AssignmentID DESC LIMIT 1
");
$stmtCur->bind_param("i", $asset_id);
$stmtCur->execute();
$currentAssignment = $stmtCur->get_result()->fetch_assoc();
$stmtCur->close();

$errorMessages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serialNumber   = trim($_POST['serial_number'] ?? '');
    $tagNumber      = trim($_POST['tag_number'] ?? '');
    $status         = $_POST['status'] ?? '';
    $rawDateReceived = $_POST['DateReceived'] ?? null;
    $rawDateReturned = $_POST['DateReturned'] ?? null;
    $assignedTo     = $_POST['assigned_to'] ?? null;
    $notes          = trim($_POST['notes'] ?? '');
    $specInput      = $_POST['specs'] ?? [];

    $dateReceived = convertToMySQLDate($rawDateReceived);
    $dateReturned = convertToMySQLDate($rawDateReturned);

    // --- Validate dates ---
    if (!empty($rawDateReceived) && !$dateReceived) $errorMessages[] = "Date Received invalid (dd-mm-yyyy).";
    if (!empty($rawDateReturned) && !$dateReturned) $errorMessages[] = "Date Returned invalid (dd-mm-yyyy).";
    if ($dateReceived && $dateReturned && strtotime($dateReceived) > strtotime($dateReturned))
        $errorMessages[] = "Date Received must be earlier than Date Returned.";

    // --- Validate unique serial/tag ---
    $stmt = $conn->prepare("SELECT AssetID FROM assets WHERE SerialNumber=? AND AssetID<>?");
    $stmt->bind_param("si", $serialNumber, $asset_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $errorMessages[] = "Serial Number '$serialNumber' already used.";
    $stmt->close();

    $stmt = $conn->prepare("SELECT AssetID FROM assets WHERE TagNumber=? AND AssetID<>?");
    $stmt->bind_param("si", $tagNumber, $asset_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $errorMessages[] = "Tag Number '$tagNumber' already used.";
    $stmt->close();

    $assignedToBind = $assignedTo ? (int)$assignedTo : null;

    // --- Check for overlapping assignment ---
    if ($dateReceived && $assignedToBind) {
        $excludeID = $currentAssignment['AssignmentID'] ?? null;
        $overlapCheck = checkAssignmentOverlap($conn, $asset_id, $dateReceived, $dateReturned, $excludeID);
        if ($overlapCheck['exists']) $errorMessages[] = formatOverlapErrorMessage($overlapCheck);
    }

    if (empty($errorMessages)) {
        // --- Update asset ---
        $stmt = $conn->prepare("UPDATE assets SET Status=?, AssignedTo=?, SerialNumber=?, TagNumber=? WHERE AssetID=?");
        $stmt->bind_param("sissi", $status, $assignedToBind, $serialNumber, $tagNumber, $asset_id);
        $stmt->execute();
        $stmt->close();

        $conn->begin_transaction();
        try {
            $empID = $assignedToBind;
            $rec = $dateReceived;
            $ret = $dateReturned;

// --- Handle assignment ---
if ($currentAssignment) {
    // Update the existing open assignment row.
    // This will change the EmployeeID (e.g. Randa -> Beshoy) and dates on that same row.
    if ($assignedToBind !== null) {
        $stmt = $conn->prepare("
            UPDATE asset_assignments
            SET EmployeeID = ?, DateReceived = ?, DateReturned = ?
            WHERE AssignmentID = ?
        ");
        // types: i = EmployeeID, s = DateReceived, s = DateReturned, i = AssignmentID
        $stmt->bind_param("issi", $assignedToBind, $rec, $ret, $currentAssignment['AssignmentID']);
        $stmt->execute();
        $stmt->close();
    } else {
        // If form set assigned_to to empty (unassign), just update the dates (close if DateReturned provided)
        $stmt = $conn->prepare("
            UPDATE asset_assignments
            SET DateReceived = ?, DateReturned = ?
            WHERE AssignmentID = ?
        ");
        $stmt->bind_param("ssi", $rec, $ret, $currentAssignment['AssignmentID']);
        $stmt->execute();
        $stmt->close();
    }
} else {
    // No open assignment exists → create a new one (only when employee and received date are provided)
    if ($assignedToBind && $rec) {
        $stmt = $conn->prepare("
            INSERT INTO asset_assignments (AssetID, EmployeeID, DateReceived, DateReturned, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("iiss", $asset_id, $assignedToBind, $rec, $ret);
        $stmt->execute();
        $stmt->close();
    }
}



            // --- Update specs and history ---
            foreach ($specInput as $specID => $val) {
                foreach ($specs as $s) {
                    if ($s['SpecID'] == $specID && $s['ValueText'] !== $val) {
                        $stmt = $conn->prepare("UPDATE assetspecs SET ValueText=? WHERE SpecID=?");
                        $stmt->bind_param("si", $val, $specID);
                        $stmt->execute();
                        $stmt->close();

                        $oldVal = $s['AttributeName'].": ".$s['ValueText'];
                        $newVal = $s['AttributeName'].": ".$val;
                        $stmt = $conn->prepare("INSERT INTO assethistory (AssetID, OldValue, NewValue, Notes, ActionDate) VALUES (?, ?, ?, ?, NOW())");
                        $stmt->bind_param("isss", $asset_id, $oldVal, $newVal, $notes);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }

            // --- Save notes/history for assignment changes ---
            if (($asset['AssignedTo'] != $assignedToBind) || ($asset['Status'] != $status)) {
                $oldVal = $asset['AssignedTo'] ? getEmployeeName($conn, $asset['AssignedTo']) : 'Unassigned';
                $newVal = $assignedToBind ? getEmployeeName($conn, $assignedToBind) : 'Unassigned';
                if ($oldVal != $newVal) {
                    $stmt = $conn->prepare("INSERT INTO assethistory (AssetID, OldValue, NewValue, Notes, ActionDate) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->bind_param("isss", $asset_id, $oldVal, $newVal, $notes);
                    $stmt->execute();
                    $stmt->close();
                }

                if ($asset['Status'] != $status) {
                    $stmt = $conn->prepare("INSERT INTO assethistory (AssetID, OldValue, NewValue, Notes, ActionDate) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->bind_param("isss", $asset_id, $asset['Status'], $status, $notes);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $errorMessages[] = "Database error: ".$e->getMessage();
        }

        if (empty($errorMessages)) {
            header("Location: edit_asset.php?id=$asset_id&saved=1");
            exit;
        }
    }
}

// Refresh specs for display
$specs = getSpecs($conn, $asset_id);

// --- Helper to get employee name ---
function getEmployeeName($conn, $empID) {
    $stmt = $conn->prepare("SELECT Name FROM employees WHERE EmployeeID=?");
    $stmt->bind_param("i", $empID);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res['Name'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Asset #<?= $asset_id ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
    <style>
        .card-header i { margin-right: 5px; }
        .form-check-inline { margin-right: 15px; }
        .required { color: red; }
    </style>
</head>
<body class="container py-4">

<!-- Header -->
<div class="mb-4 d-flex justify-content-between align-items-center">
    <h2><i class="fas fa-edit"></i> Edit Asset #<?= $asset_id ?></h2>
    <a href="assets.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<!-- Error/Success Messages -->
<?php if (!empty($errorMessages)): ?>
    <div class="alert alert-danger">
        <ul>
            <?php foreach ($errorMessages as $err) echo "<li>$err</li>"; ?>
        </ul>
    </div>
<?php elseif(isset($_GET['saved'])): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> Changes saved successfully!</div>
<?php endif; ?>

<!-- Edit Form -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <h4><i class="fas fa-laptop"></i> Asset Details</h4>
    </div>
    <div class="card-body">
        <form method="POST">
            <!-- Serial & Tag Number -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label><i class="fas fa-barcode"></i> Serial Number <span class="required">*</span></label>
                    <input type="text" name="serial_number" class="form-control" value="<?= htmlspecialchars($asset['SerialNumber']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label><i class="fas fa-tag"></i> Tag Number</label>
                    <input type="text" name="tag_number" class="form-control" value="<?= htmlspecialchars($asset['TagNumber'] ?? '') ?>">
                </div>
            </div>

            <!-- Assigned To & Status -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label><i class="fas fa-user"></i> Assigned To</label>
                    <select name="assigned_to" class="form-select">
                        <option value="">-- Unassigned --</option>
                        <?php foreach ($allEmployees as $emp): ?>
                            <option value="<?= $emp['EmployeeID'] ?>" <?= ($asset['AssignedTo']==$emp['EmployeeID'])?'selected':'' ?>>
                                <?= htmlspecialchars($emp['Name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label><i class="fas fa-info-circle"></i> Status</label>
                    <select name="status" class="form-select">
                        <option value="Working" <?= $asset['Status']=="Working"?"selected":"" ?>>Working</option>
                        <option value="In Repair" <?= $asset['Status']=="In Repair"?"selected":"" ?>>In Repair</option>
                        <option value="Trashed" <?= $asset['Status']=="Trashed"?"selected":"" ?>>Trashed</option>
                    </select>
                </div>
            </div>

            <!-- Date Received & Returned -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label><i class="fas fa-calendar-plus"></i> Date Received</label>
                    <input type="text" name="DateReceived" class="form-control datepicker" 
                           value="<?= !empty($currentAssignment['DateReceived']) ? date('d-m-Y', strtotime($currentAssignment['DateReceived'])) : '' ?>" readonly>
                </div>
                <div class="col-md-6 mb-3">
                    <label><i class="fas fa-calendar-minus"></i> Date Returned</label>
                    <input type="text" name="DateReturned" class="form-control datepicker" 
                           value="<?= !empty($currentAssignment['DateReturned']) ? date('d-m-Y', strtotime($currentAssignment['DateReturned'])) : '' ?>" readonly>
                </div>
            </div>

            <!-- Specifications -->
            <?php if(!empty($specs)): ?>
                <h5 class="mt-4"><i class="fas fa-cogs"></i> Specifications</h5>
                <?php foreach ($specs as $s): ?>
                    <div class="mb-3">
                        <label><?= htmlspecialchars($s['AttributeName']) ?></label>
                        <?php if($s['AttributeType']==='Boolean'): ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="specs[<?= $s['SpecID'] ?>]" value="Yes" <?= $s['ValueText']==='Yes'?'checked':'' ?>>
                                <label class="form-check-label">Yes</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="specs[<?= $s['SpecID'] ?>]" value="No" <?= $s['ValueText']==='No'?'checked':'' ?>>
                                <label class="form-check-label">No</label>
                            </div>
                        <?php else: ?>
                            <input type="text" name="specs[<?= $s['SpecID'] ?>]" class="form-control" value="<?= htmlspecialchars($s['ValueText']) ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Notes -->
            <div class="mb-3">
                <label><i class="fas fa-sticky-note"></i> Notes</label>
                <textarea name="notes" class="form-control" rows="4"><?= htmlspecialchars($_POST['notes'] ?? $asset['Notes'] ?? '') ?></textarea>
            </div>

            <!-- Buttons -->
            <div class="d-flex justify-content-end gap-2">
                <a href="assets.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
$(function() {
    $(".datepicker").datepicker({
        dateFormat: 'dd-mm-yy',
        changeMonth: true,
        changeYear: true,
        yearRange: '1990:+50'
    });
});
</script>

</body>
</html>
