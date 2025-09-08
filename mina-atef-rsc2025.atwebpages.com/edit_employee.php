<?php
include 'db.php';

$id = (int)($_GET['id'] ?? 0);
$emp = null;
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM employees WHERE EmployeeID=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $emp = $stmt->get_result()->fetch_assoc();
}

// Load lookups
$titles = $conn->query("SELECT TitleID, TitleName FROM titles ORDER BY TitleName")->fetch_all(MYSQLI_ASSOC);
$depts  = $conn->query("SELECT DepartmentID, DepartmentName FROM departments ORDER BY DepartmentName")->fetch_all(MYSQLI_ASSOC);

// Get locations with hierarchical structure
function getFormattedLocations($conn) {
    $sql = "SELECT l1.LocationID, l1.LocationName, l1.ParentID, 
           IFNULL(l2.LocationName, '') as ParentName
           FROM locations l1
           LEFT JOIN locations l2 ON l1.ParentID = l2.LocationID
           ORDER BY IFNULL(l2.LocationName, l1.LocationName), l1.LocationName";
    
    $result = $conn->query($sql);
    $locations = [];
    
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['ParentName'])) {
            $row['DisplayName'] = $row['ParentName'] . ' → ' . $row['LocationName'];
        } else {
            $row['DisplayName'] = $row['LocationName'];
        }
        $locations[] = $row;
    }
    
    return $locations;
}

$locs = getFormattedLocations($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['Name'];
    $titleId = $_POST['TitleID'] !== '' ? (int)$_POST['TitleID'] : null;
    $deptId  = $_POST['DepartmentID'] !== '' ? (int)$_POST['DepartmentID'] : null;
    $locId   = $_POST['LocationID'] !== '' ? (int)$_POST['LocationID'] : null;

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE employees SET Name=?, TitleID=?, DepartmentID=?, LocationID=? WHERE EmployeeID=?");
        $stmt->bind_param("siiii", $name, $titleId, $deptId, $locId, $id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO Employees (Name, TitleID, DepartmentID, LocationID) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("siii", $name, $titleId, $deptId, $locId);
        $stmt->execute();
    }
    header("Location: employees.php");
    exit;
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Edit Employee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
<a href="employees.php" class="btn btn-secondary btn-sm">Back</a>
<h2><?= $id > 0 ? "Edit" : "Add" ?> Employee</h2>
<form method="post" class="mt-3">
    <div class="mb-3">
        <label class="form-label">Name</label>
        <input name="Name" class="form-control" value="<?= htmlspecialchars($emp['Name'] ?? '') ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Title</label>
        <select name="TitleID" class="form-select">
            <option value="">-- Title --</option>
            <?php foreach($titles as $t): ?>
              <option value="<?= $t['TitleID'] ?>" <?= isset($emp['TitleID']) && $emp['TitleID']==$t['TitleID']? 'selected':'' ?>><?= htmlspecialchars($t['TitleName']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">Department</label>
        <select name="DepartmentID" class="form-select">
            <option value="">-- Department --</option>
            <?php foreach($depts as $d): ?>
              <option value="<?= $d['DepartmentID'] ?>" <?= isset($emp['DepartmentID']) && $emp['DepartmentID']==$d['DepartmentID']? 'selected':'' ?>><?= htmlspecialchars($d['DepartmentName']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">Location</label>
        <select name="LocationID" class="form-select" required>
            <option value="">-- Location --</option>
            <?php foreach($locs as $l): ?>
              <option value="<?= $l['LocationID'] ?>" <?= isset($emp['LocationID']) && $emp['LocationID']==$l['LocationID']? 'selected':'' ?>><?= htmlspecialchars($l['DisplayName']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary">Save</button>
</form>
</body>
</html>
