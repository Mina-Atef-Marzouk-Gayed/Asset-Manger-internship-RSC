<?php include 'db.php';?>
<?php
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

if($_SERVER['REQUEST_METHOD']==='POST'){ 
  $name = trim($_POST['name']);
  $titleId = $_POST['title_id'] !== '' ? (int)$_POST['title_id'] : null;
  $deptId  = $_POST['department_id'] !== '' ? (int)$_POST['department_id'] : null;
  $locId   = $_POST['location_id'] !== '' ? (int)$_POST['location_id'] : null;

  $stmt = $conn->prepare("INSERT INTO employees (Name, TitleID, DepartmentID, LocationID) VALUES (?, ?, ?, ?)");
  $stmt->bind_param("siii", $name, $titleId, $deptId, $locId);
  $stmt->execute();
  header('Location: employees.php'); exit;
} ?>
<!doctype html><html><head><meta charset='utf-8'><title>Add Employee</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="container py-4">
<h2>Add Employee</h2>
<form method="post">
  <div class="mb-2"><input name="name" class="form-control" placeholder="Name" required></div>
  <div class="mb-2">
    <select name="title_id" class="form-select">
      <option value="">-- Title --</option>
      <?php foreach($titles as $t){ echo "<option value='{$t['TitleID']}'>".htmlspecialchars($t['TitleName'])."</option>"; } ?>
    </select>
  </div>
  <div class="mb-2">
    <select name="department_id" class="form-select">
      <option value="">-- Department --</option>
      <?php foreach($depts as $d){ echo "<option value='{$d['DepartmentID']}'>".htmlspecialchars($d['DepartmentName'])."</option>"; } ?>
    </select>
  </div>
  <div class="mb-2">
    <select name="location_id" class="form-select" required>
      <option value="">-- Location --</option>
      <?php foreach($locs as $l){ echo "<option value='{$l['LocationID']}'>".htmlspecialchars($l['DisplayName'])."</option>"; } ?>
    </select>
  </div>
  <button class="btn btn-primary">Save</button> <a class="btn btn-secondary" href="employees.php">Cancel</a>
</form></body></html>
