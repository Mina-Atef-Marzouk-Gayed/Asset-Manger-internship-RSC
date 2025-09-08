<?php
include 'db.php';
include 'asset_validation.php'; // contains convertToMySQLDate, validateDate, checkAssignmentOverlap, formatOverlapErrorMessage

$types = $conn->query("SELECT * FROM assettypes ORDER BY TypeName")->fetch_all(MYSQLI_ASSOC);
$emps  = $conn->query("SELECT * FROM employees ORDER BY Name")->fetch_all(MYSQLI_ASSOC);

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type   = (int)($_POST['type_id'] ?? 0);
    $serial = trim($_POST['serial'] ?? '');
    $tag    = trim($_POST['tag'] ?? '');
    $status = $_POST['status'] ?? 'Working';
    $emp    = isset($_POST['employee_id']) && $_POST['employee_id'] !== '' ? (int)$_POST['employee_id'] : null;
    $drecv  = $_POST['date_received'] ?? null;
    $specInput = $_POST['spec'] ?? [];

    $errors = [];
    if ($type <= 0) $errors[] = "Type is required.";
    if ($serial === '') $errors[] = "Serial Number is required.";
    if (!empty($drecv) && !preg_match('/^\d{2}-\d{2}-\d{4}$/', $drecv)) $errors[] = "Date Received must be dd-mm-yyyy.";

    $dateReceived = convertToMySQLDate($drecv);

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("INSERT INTO assets (TypeID, SerialNumber, TagNumber, Status, AssignedTo) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('isssi', $type, $serial, $tag, $status, $emp);
            $stmt->execute();
            $assetId = $stmt->insert_id;
            $stmt->close();

            // Handle assignment
            if ($emp && $dateReceived) {
                $overlap = checkAssignmentOverlap($conn, $assetId, $dateReceived);
                if ($overlap['exists']) {
                    $msg = 'Asset added, but assignment failed: ' . formatOverlapErrorMessage($overlap);
                } else {
                    $ins = $conn->prepare("INSERT INTO asset_assignments (AssetID, EmployeeID, DateReceived) VALUES (?, ?, ?)");
                    $ins->bind_param('iis', $assetId, $emp, $dateReceived);
                    $ins->execute();
                    $ins->close();
                    $msg = 'Asset added and assigned successfully';
                }
            } else {
                $msg = 'Asset added successfully';
            }

            // Save specs
            if (!empty($specInput) && is_array($specInput)) {
                $st2 = $conn->prepare("INSERT INTO assetspecs (AssetID, AttributeID, ValueText) VALUES (?, ?, ?)");
                foreach ($specInput as $aid => $val) {
                    $v = is_array($val) ? implode(',', $val) : $val;
                    $aidInt = (int)$aid;
                    $st2->bind_param('iis', $assetId, $aidInt, $v);
                    $st2->execute();
                }
                $st2->close();
            }

        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) { // Duplicate entry
                if (strpos($e->getMessage(), 'SerialNumber') !== false) {
                    $msg = "Error: Serial Number '$serial' already exists. Please use a unique Serial Number.";
                } elseif (strpos($e->getMessage(), 'TagNumber') !== false) {
                    $msg = "Error: Tag Number '$tag' already exists. Please use a unique Tag Number.";
                } else {
                    $msg = "Duplicate entry error: " . $e->getMessage();
                }
            } else {
                $msg = "Database error: " . $e->getMessage();
            }
        }
    } else {
        $msg = implode('<br>', $errors);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Add Asset</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
</head>
<body class="container py-4">

<h2>Add Asset</h2>
<p><a class="btn btn-sm btn-secondary" href="assets.php"><i class="fas fa-arrow-left"></i> Back</a></p>

<?php if ($msg): ?>
<div class="alert alert-info"><?= $msg ?></div>
<?php endif; ?>

<form method="post">
<div class="row g-3">
    <div class="col-md-4">
        <label>Type</label>
        <select id="type" name="type_id" class="form-select" required onchange="loadAttrs()">
            <option value=''>--</option>
            <?php foreach($types as $t): ?>
                <option value="<?= $t['TypeID'] ?>" <?= (isset($_POST['type_id']) && $_POST['type_id']==$t['TypeID'])?'selected':'' ?>>
                    <?= htmlspecialchars($t['TypeName']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label>Serial Number</label>
        <input name="serial" class="form-control" value="<?= htmlspecialchars($_POST['serial'] ?? '') ?>" required>
    </div>
    <div class="col-md-4">
        <label>Tag Number</label>
        <input name="tag" class="form-control" value="<?= htmlspecialchars($_POST['tag'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label>Status</label>
        <select name="status" class="form-select">
            <option value="Working" <?= ($_POST['status']??'')=='Working'?'selected':'' ?>>Working</option>
            <option value="In Repair" <?= ($_POST['status']??'')=='In Repair'?'selected':'' ?>>In Repair</option>
            <option value="Trashed" <?= ($_POST['status']??'')=='Trashed'?'selected':'' ?>>Trashed</option>
        </select>
    </div>
    <div class="col-md-4">
        <label>Assigned Employee</label>
        <select name="employee_id" class="form-select">
            <option value=''>--</option>
            <?php foreach($emps as $e): ?>
                <option value="<?= $e['EmployeeID'] ?>" <?= (isset($_POST['employee_id']) && $_POST['employee_id']==$e['EmployeeID'])?'selected':'' ?>>
                    <?= htmlspecialchars($e['Name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <label>Date Received</label>
        <input type='text' name='date_received' class='form-control datepicker' value="<?= htmlspecialchars($_POST['date_received'] ?? '') ?>" placeholder='dd-mm-yyyy' readonly>
    </div>
</div>

<hr>
<h5>Specifications</h5>
<div id="attrs"></div>

<div class="mt-3">
    <button class="btn btn-primary"><i class="fas fa-save"></i> Save Asset</button>
</div>
</form>

<script>
$(function() {
    $(".datepicker").datepicker({ dateFormat: 'dd-mm-yy', changeMonth:true, changeYear:true, yearRange:'1990:+80' });
    if ($('#type').val()) loadAttrs();
});

async function loadAttrs() {
    const tid = $('#type').val();
    const box = $('#attrs');
    box.html('');
    if(!tid) return;
    const res = await fetch('manage_type_ajax.php?type_id=' + encodeURIComponent(tid));
    const attrs = await res.json();
    attrs.forEach(a => {
        const div = $('<div class="mb-2"></div>');
        const label = $('<label></label>').text(a.Name + (a.is_required?' *':''));
        let input;
        switch(a.DataType){
            case 'Date': input=$('<input type="date" class="form-control">'); break;
            case 'Number': input=$('<input type="number" class="form-control">'); break;
            case 'Boolean': 
                input=$('<select class="form-select"><option value="">--</option><option value="Yes">Yes</option><option value="No">No</option></select>'); 
                break;
            default: input=$('<input type="text" class="form-control">');
        }
        input.attr('name','spec['+a.AttributeID+']');
        if(a.is_required) input.prop('required',true);
        div.append(label).append(input);
        box.append(div);
    });
}
</script>

</body>
</html>
