<?php
include 'db.php';

$msg = '';
//Titles and Departments management page
// Handle add actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['entity'], $_POST['name'])) {
    $entity = $_POST['entity']; //            <input type="hidden" name="entity" value="title">
    $name = trim($_POST['name']);
    if ($name !== '') {
      if ($entity === 'title') {
        $stmt = $conn->prepare("INSERT INTO titles (TitleName) VALUES (?)");
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $msg = 'Title added';
      } elseif ($entity === 'department') {
        $stmt = $conn->prepare("INSERT INTO departments (DepartmentName) VALUES (?)");
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $msg = 'Department added';
      }
    }
  }

  // Handle delete actions
  if (isset($_POST['delete'], $_POST['entity'], $_POST['id'])) {
    $entity = $_POST['entity'];
    $id = (int)$_POST['id'];
    if ($entity === 'title') {
      $stmt = $conn->prepare("DELETE FROM titles WHERE TitleID=?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $msg = 'Title deleted';
    } elseif ($entity === 'department') {
      $stmt = $conn->prepare("DELETE FROM departments WHERE DepartmentID=?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $msg = 'Department deleted';
    }
  }

    header("Location: " . $_SERVER['PHP_SELF'] . "?msg=" . urlencode($msg));
  exit;
}

// Fetch lists
$titles = $conn->query("SELECT TitleID, TitleName FROM titles ORDER BY TitleName")->fetch_all(MYSQLI_ASSOC);
$depts  = $conn->query("SELECT DepartmentID, DepartmentName FROM departments ORDER BY DepartmentName")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Manage Lookups</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="container py-4">
  <h2>Titles and Departments</h2>
  <p>
    <a class="btn btn-sm btn-secondary" href="index.php"><i class="fas fa-arrow-left"></i> Back</a>
    <a class="btn btn-sm btn-primary" href="locations.php"><i class="fas fa-map-marker-alt"></i> Manage Locations</a>
  </p>

  <?php if ($msg): ?>
    <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">Titles</div>
        <div class="card-body">
          <form method="post" class="d-flex gap-2 mb-3">
            <input type="hidden" name="entity" value="title">
            <input name="name" class="form-control" placeholder="New title" required>
            <button class="btn btn-primary">Add</button>
          </form>
          <ul class="list-group">
            <?php foreach ($titles as $t): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><?= htmlspecialchars($t['TitleName']) ?></span>
                <form method="post" class="m-0" onsubmit="return confirm('Delete this title?');">
                  <input type="hidden" name="entity" value="title">
                  <input type="hidden" name="id" value="<?= $t['TitleID'] ?>">
                  <button class="btn btn-sm btn-outline-danger" name="delete" value="1">Delete</button>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card">
        <div class="card-header">Departments</div>
        <div class="card-body">
          <form method="post" class="d-flex gap-2 mb-3">
            <input type="hidden" name="entity" value="department">
            <input name="name" class="form-control" placeholder="New department" required>
            <button class="btn btn-primary">Add</button>
          </form>
          <ul class="list-group">
            <?php foreach ($depts as $d): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><?= htmlspecialchars($d['DepartmentName']) ?></span>
                <form method="post" class="m-0" onsubmit="return confirm('Delete this department?');">
                  <input type="hidden" name="entity" value="department">
                  <input type="hidden" name="id" value="<?= $d['DepartmentID'] ?>">
                  <button class="btn btn-sm btn-outline-danger" name="delete" value="1">Delete</button>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>


  </div>
</body>
</html>
