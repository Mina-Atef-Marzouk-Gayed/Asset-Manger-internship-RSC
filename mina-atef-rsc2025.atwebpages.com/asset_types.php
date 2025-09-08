<?php 
include 'db.php'; 
$msg=''; 



// Add new type
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_type'])) {
    $name = trim($_POST['type_name']);
    if($name != '') { 
        $stmt = $conn->prepare("INSERT IGNORE INTO assettypes (TypeName) VALUES (?)");
        $stmt->bind_param("s", $name);
        if($stmt->execute()){
            $msg = '✅ Type added';
        } else {
            $msg = '❌ Error adding type: ' . $stmt->error;
        }
        $stmt->close();
    }
}

// Delete type (with check)
if(isset($_GET['delete_type'])) { 
    $id = (int)$_GET['delete_type']; 
    
    // Check if any assets are using this type
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM assets WHERE TypeID=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if($row['cnt'] > 0){
        $msg = "❌ Cannot delete this type. It is still assigned to one or more assets.";
    } else {
        $stmt = $conn->prepare("DELETE FROM assettypes WHERE TypeID=?");
        $stmt->bind_param("i", $id);
        if($stmt->execute()){
            $msg = "✅ Asset type deleted successfully.";
        } else {
            $msg = "❌ Error deleting type: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get sort parameters
$sort_column = $_GET['sort'] ?? 'TypeName';
$sort_order = $_GET['order'] ?? 'ASC';

// Validate sort column
$allowed_columns = ['TypeID', 'TypeName', 'created_at'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'TypeName';
}

// Validate sort order
$sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

// Build ORDER BY clause
$order_by = '';
switch($sort_column) {
    case 'TypeID':
        $order_by = "TypeID $sort_order";
        break;
    case 'TypeName':
        $order_by = "TypeName $sort_order";
        break;
    case 'created_at':
        $order_by = "created_at $sort_order";
        break;
    default:
        $order_by = "TypeName ASC";
}

// Get list of types
$types=$conn->query("SELECT * FROM assettypes ORDER BY $order_by")->fetch_all(MYSQLI_ASSOC);

// Function to generate sort URL
function getSortUrl($column, $current_sort, $current_order) {
    $new_order = ($current_sort === $column && $current_order === 'ASC') ? 'DESC' : 'ASC';
    return "?sort=$column&order=$new_order";
}

// Function to get sort icon
function getSortIcon($column, $current_sort, $current_order) { //        <th ....<?= getSortUrl('TypeID', $sort_column, $sort_order)
    if ($current_sort === $column) {
        return $current_order === 'ASC' ? '↑' : '↓';
    }
    return '↕';
}
?>
<!doctype html>
<html>
<head>
<meta charset='utf-8'>
<title>Asset Types</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.sortable { cursor: pointer; }
.sortable:hover { background-color: #f8f9fa; }
.sort-icon { margin-left: 5px; color: #6c757d; }
</style>
</head>
<body class="container py-4">
  <h2>Asset Types</h2>
  <a class="btn btn-sm btn-secondary mb-3" href="index.php">Back</a>

  <?php if($msg): ?>
    <div class="alert alert-info"><?=htmlspecialchars($msg)?></div>
  <?php endif; ?>

  <form method="post" class="mb-3 d-flex gap-2">
    <input name="type_name" class="form-control w-50" placeholder="Type name e.g. Laptop" required>
    <input type="hidden" name="add_type" value="1">
    <button class="btn btn-primary">Add Type</button>
  </form>

  <h5>Existing Types (click to manage attributes)</h5>
  
  <table class="table table-bordered">
    <thead>
      <tr>
        <th class="sortable" onclick="window.location.href='<?= getSortUrl('TypeID', $sort_column, $sort_order) ?>'">
          ID <span class="sort-icon"><?= getSortIcon('TypeID', $sort_column, $sort_order) ?></span>
        </th>
        <th class="sortable" onclick="window.location.href='<?= getSortUrl('TypeName', $sort_column, $sort_order) ?>'">
          Type Name <span class="sort-icon"><?= getSortIcon('TypeName', $sort_column, $sort_order) ?></span>
        </th>
        <th class="sortable" onclick="window.location.href='<?= getSortUrl('created_at', $sort_column, $sort_order) ?>'">
          Created <span class="sort-icon"><?= getSortIcon('created_at', $sort_column, $sort_order) ?></span>
        </th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($types as $t): ?>
        <tr>
          <td><?= $t['TypeID'] ?></td>
          <td><a href="manage_type.php?id=<?=$t['TypeID']?>"><?=htmlspecialchars($t['TypeName'])?></a></td>
          <td><?= date('Y-m-d', strtotime($t['created_at'])) ?></td>
          <td>
            <a class="btn btn-sm btn-danger" 
               href="asset_types.php?delete_type=<?=$t['TypeID']?>" 
               onclick="return confirm('Delete this type? This removes attributes and may affect linked assets.')">
               Delete
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
