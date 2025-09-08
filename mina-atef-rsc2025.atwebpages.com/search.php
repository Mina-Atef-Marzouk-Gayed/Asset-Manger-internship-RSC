<?php
include 'db.php';

// --- Get filters ---
$employee       = trim($_GET['employee'] ?? '');
$serial         = trim($_GET['serial'] ?? '');
$tag            = trim($_GET['tag'] ?? '');
$status         = $_GET['status'] ?? '';
$from           = $_GET['from'] ?? '';
$to             = $_GET['to'] ?? '';
$current_only   = isset($_GET['current_only']) && $_GET['current_only'] == '1';
$unassigned_only= isset($_GET['unassigned_only']) && $_GET['unassigned_only'] == '1';
$dateFieldChoice= $_GET['date_field'] ?? 'received'; 
$dateField      = $dateFieldChoice === 'returned' ? 'aa.DateReturned' : 'aa.DateReceived';
$asset_type     = trim($_GET['asset_type'] ?? '');
$location       = trim($_GET['location'] ?? '');
$department     = trim($_GET['department'] ?? '');

// --- Sorting ---
$sort_column = $_GET['sort'] ?? 'AssetID';
$sort_order  = strtoupper($_GET['order'] ?? 'ASC');
if (!in_array($sort_order, ['ASC', 'DESC'])) $sort_order = 'ASC';

// --- Dropdowns ---
$asset_types  = $conn->query("SELECT TypeID, TypeName FROM assettypes ORDER BY TypeName")->fetch_all(MYSQLI_ASSOC);

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
        $row['DisplayName'] = $row['ParentName'] ? $row['ParentName'] . ' → ' . $row['LocationName'] : $row['LocationName'];
        $locations[] = $row;
    }
    return $locations;
}

$locations   = getFormattedLocations($conn);
$departments = $conn->query("SELECT DepartmentName FROM departments ORDER BY DepartmentName")->fetch_all(MYSQLI_ASSOC);

// --- Dynamic attributes ---
$type_attributes = [];
if ($asset_type !== '') {
    $stmt = $conn->prepare("SELECT Name, AttributeID FROM assetattributes WHERE TypeID = ? ORDER BY Name");
    $stmt->bind_param('i', $asset_type);
    $stmt->execute();
    $type_attributes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// --- Base SQL ---
$sql = "SELECT 
            a.AssetID, a.SerialNumber, a.TagNumber, t.TypeName, a.Status,
            aa.DateReceived, aa.DateReturned,
            e.Name AS EmpName, ti.TitleName AS EmpTitle, 
            d.DepartmentName AS EmpDept, 
            CASE 
                WHEN l2.LocationName IS NOT NULL 
                THEN CONCAT(l2.LocationName, ' → ', l.LocationName)
                ELSE l.LocationName
            END as EmpLoc";


// --- Joins (always join assets, assettypes, assignments left joined) ---
$joins = " FROM assets a
           JOIN assettypes t ON t.TypeID = a.TypeID
           LEFT JOIN asset_assignments aa ON aa.AssetID = a.AssetID
           LEFT JOIN employees e ON e.EmployeeID = aa.EmployeeID
           LEFT JOIN titles ti ON ti.TitleID = e.TitleID
           LEFT JOIN departments d ON d.DepartmentID = e.DepartmentID
           LEFT JOIN locations l ON l.LocationID = e.LocationID
           LEFT JOIN locations l2 ON l.ParentID = l2.LocationID";


// --- Dynamic joins for attributes ---
$attribute_columns = [];
if ($asset_type !== '') {
    foreach ($type_attributes as $attr) {
        $attr_id    = $attr['AttributeID'];
        $attr_name  = $attr['Name'];
        $attr_alias = 'attr_' . $attr_id;
        $sql .= ", $attr_alias.ValueText AS `" . str_replace('`', '``', $attr_name) . "`";
        $joins .= " LEFT JOIN assetspecs $attr_alias 
                    ON $attr_alias.AssetID = a.AssetID 
                    AND $attr_alias.AttributeID = $attr_id";
        $attribute_columns[] = $attr_name;
    }
}

$sql .= $joins . " WHERE 1=1";

// --- Filters ---
$args = [];
$types = '';

if ($current_only) {
    $sql .= " AND (aa.DateReturned IS NULL OR aa.DateReturned = '0000-00-00 00:00:00')";
}

if ($unassigned_only) {
    $sql .= " AND (a.AssignedTo IS NULL OR aa.DateReturned IS NOT NULL)";
}

if ($employee !== '') { $sql .= " AND e.Name LIKE ?"; $args[] = "%$employee%"; $types .= 's'; }
if ($serial !== '')   { $sql .= " AND a.SerialNumber LIKE ?"; $args[] = "%$serial%"; $types .= 's'; }
if ($tag !== '')      { $sql .= " AND a.TagNumber LIKE ?"; $args[] = "%$tag%"; $types .= 's'; }
if ($status !== '')   { $sql .= " AND a.Status = ?"; $args[] = $status; $types .= 's'; }
if ($asset_type !== '') { $sql .= " AND t.TypeID = ?"; $args[] = $asset_type; $types .= 'i'; }
if ($from !== '')     { $sql .= " AND $dateField >= ?"; $args[] = $from; $types .= 's'; }
if ($to !== '')       { $sql .= " AND $dateField <= ?"; $args[] = $to; $types .= 's'; }
if ($location !== '') {
    $locationID = (int)$location;
    $locRes = $conn->query("SELECT ParentID FROM locations WHERE LocationID = $locationID")->fetch_assoc();
    if ($locRes && $locRes['ParentID'] === null) {
        $sql .= " AND (l.LocationID = ? OR l.ParentID = ?)";
        $args[] = $locationID; $args[] = $locationID; $types .= 'ii';
    } else {
        $sql .= " AND l.LocationID = ?"; $args[] = $locationID; $types .= 'i';
    }
}
if ($department !== '') { $sql .= " AND d.DepartmentName = ?"; $args[] = $department; $types .= 's'; }

// --- Sorting ---
$allowedSortColumns = [
    'AssetID' => 'a.AssetID',
    'EmpName' => 'e.Name',
    'EmpTitle'=> 'ti.TitleName',
    'EmpDept' => 'd.DepartmentName',
    'EmpLoc'  => "CASE WHEN l2.LocationName IS NOT NULL THEN CONCAT(l2.LocationName, ' → ', l.LocationName) ELSE l.LocationName END",
    'SerialNumber' => 'a.SerialNumber',
    'TagNumber' => 'a.TagNumber',
    'TypeName' => 't.TypeName',
    'Status' => 'a.Status',
    'DateReceived' => 'aa.DateReceived',
    'DateReturned' => 'aa.DateReturned'
];

if (array_key_exists($sort_column, $allowedSortColumns)) {
    $orderBy = $allowedSortColumns[$sort_column];
} elseif (in_array($sort_column, $attribute_columns)) {
    $orderBy = "`$sort_column`";
} else {
    $orderBy = 'a.AssetID';
}

$sql .= " ORDER BY $orderBy $sort_order";

// --- Prepare and execute ---
$stmt = $conn->prepare($sql);
if ($args) $stmt->bind_param($types, ...$args);
$stmt->execute();
$res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// --- Export ---
if (isset($_GET['export']) && $_GET['export'] == '1') {
    $filename = 'search_results_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM

    $csv_headers = ['ID', 'Name', 'Title', 'Department', 'Location', 'Serial', 'Tag', 'Type', 'Status'];
    if ($asset_type !== '') foreach ($attribute_columns as $attr) $csv_headers[] = $attr;
    $csv_headers[] = 'Date Received'; 
    $csv_headers[] = 'Date Returned';
fputcsv($output, $csv_headers, ",", '"', "\\");

    foreach ($res as $r) {
        $row = [
            $r['AssetID'], $r['EmpName'] ?? 'Unassigned', $r['EmpTitle'] ?? '',
            $r['EmpDept'] ?? '', $r['EmpLoc'] ?? '',
            $r['SerialNumber'], $r['TagNumber'], $r['TypeName'], $r['Status']
        ];
        if ($asset_type !== '') {
            foreach ($attribute_columns as $attr) $row[] = $r[$attr] ?? '';
        }
        $row[] = $r['DateReceived'] ? substr($r['DateReceived'], 0, 10) : '';
        $row[] = $r['DateReturned'] ? substr($r['DateReturned'], 0, 10) : '';
fputcsv($output, $row, ",", '"', "\\");
    }
    fclose($output);
    exit;
}

// --- Helpers ---
function getSortUrl($column, $current_sort, $current_order, $params) {
    $new_order = ($current_sort === $column && $current_order === 'ASC') ? 'DESC' : 'ASC';
    $params['sort'] = $column;
    $params['order'] = $new_order;
    return '?' . http_build_query($params);
}
function getSortIcon($column, $current_sort, $current_order) {
    if ($current_sort === $column) return $current_order === 'ASC' ? '↑' : '↓';
    return '↕';
}
?>

<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Search Assets</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.sortable { cursor: pointer; }
.sortable:hover { background-color: #f8f9fa; }
.sort-icon { margin-left: 5px; color: #6c757d; }
</style>
</head>
<body class="container py-4">

<h2>Search Assets</h2>
<p><a class="btn btn-sm btn-secondary" href="index.php">Back</a></p>

<form method="get" class="mb-3">
  <div class="row g-3">
    <div class="col-md-2"><input name="employee" class="form-control" placeholder="Employee name" value="<?= htmlspecialchars($employee) ?>"></div>
    <div class="col-md-2"><input name="serial" class="form-control" placeholder="Serial number" value="<?= htmlspecialchars($serial) ?>"></div>
    <div class="col-md-2"><input name="tag" class="form-control" placeholder="Tag number" value="<?= htmlspecialchars($tag) ?>"></div>

    <div class="col-md-2">
      <select name="status" class="form-select">
        <option value="">All Statuses</option>
        <option value="Working" <?= $status==='Working'?'selected':'' ?>>Working</option>
        <option value="In Repair" <?= $status==='In Repair'?'selected':'' ?>>In Repair</option>
        <option value="Trashed" <?= $status==='Trashed'?'selected':'' ?>>Trashed</option>
      </select>
    </div>
    <div class="col-md-2">
      <select name="asset_type" class="form-select" onchange="this.form.submit()">
        <option value="">All Types</option>
        <?php foreach ($asset_types as $type): ?>
          <option value="<?= $type['TypeID'] ?>"<?= $asset_type==$type['TypeID']?' selected':'' ?>>
            <?= htmlspecialchars($type['TypeName']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select name="location" class="form-select">
        <option value="">All Locations</option>
        <?php foreach ($locations as $loc): ?>
          <option value="<?= $loc['LocationID'] ?>"<?= $location==$loc['LocationID']?' selected':'' ?>><?= htmlspecialchars($loc['DisplayName']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select name="department" class="form-select">
        <option value="">All Departments</option>
        <?php foreach ($departments as $dept): ?>
          <option<?= $department===$dept['DepartmentName']?' selected':'' ?>><?= htmlspecialchars($dept['DepartmentName']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="row g-3 mt-2">
    <div class="col-md-3 d-flex align-items-center gap-4">
      <div class="form-check">
        <input class="form-check-input" type="radio" name="date_field" id="df_received" value="received" <?= $dateFieldChoice==='received'?'checked':'' ?>>
        <label class="form-check-label" for="df_received">Date Received</label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="radio" name="date_field" id="df_returned" value="returned" <?= $dateFieldChoice==='returned'?'checked':'' ?>>
        <label class="form-check-label" for="df_returned">Date Returned</label>
      </div>
    </div>
    <div class="col-md-3"><input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>"></div>
    <div class="col-md-3"><input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>"></div>
    <div class="col-md-3 d-flex gap-2">
      <div class="form-check align-self-center">
        <input class="form-check-input" type="checkbox" id="current_only" name="current_only" value="1" <?= $current_only?'checked':'' ?>>
        <label class="form-check-label" for="current_only">Current Only</label>
      </div>
      <div class="form-check align-self-center">
        <input class="form-check-input" type="checkbox" id="unassigned_only" name="unassigned_only" value="1" <?= $unassigned_only?'checked':'' ?>>
        <label class="form-check-label" for="unassigned_only">Unassigned Only</label>
      </div>
      <button class="btn btn-primary">Search</button>
      <button type="submit" name="export" value="1" class="btn btn-success">Export Excel</button>
    </div>
  </div>
</form>

<?php if (empty($res)): ?>
  <div class="alert alert-secondary">No results</div>
<?php else: ?>
<?php
$current_params = $_GET;
?>
<table class="table table-striped">
  <thead>
    <tr>
      <th class="sortable" onclick="window.location.href='<?= getSortUrl('AssetID', $sort_column, $sort_order, $current_params) ?>'">
        ID <span class="sort-icon"><?= getSortIcon('AssetID', $sort_column, $sort_order) ?></span>
      </th>
      <th class="sortable" onclick="window.location.href='<?= getSortUrl('EmpName', $sort_column, $sort_order, $current_params) ?>'">
        Name <span class="sort-icon"><?= getSortIcon('EmpName', $sort_column, $sort_order) ?></span>
      </th>
      <th class="sortable" onclick="window.location.href='<?= getSortUrl('EmpTitle', $sort_column, $sort_order, $current_params) ?>'">
        Title <span class="sort-icon"><?= getSortIcon('EmpTitle', $sort_column, $sort_order) ?></span>
      </th>
      <th class="sortable" onclick="window.location.href='<?= getSortUrl('EmpDept', $sort_column, $sort_order, $current_params) ?>'">
        Department <span class="sort-icon"><?= getSortIcon('EmpDept', $sort_column, $sort_order) ?></span>
      </th>
      <th class="sortable" onclick="window.location.href='<?= getSortUrl('EmpLoc', $sort_column, $sort_order, $current_params) ?>'">
        Location <span class="sort-icon"><?= getSortIcon('EmpLoc', $sort_column, $sort_order) ?></span>
      </th>
      <th class="sortable" onclick="window.location.href='<?= getSortUrl('SerialNumber', $sort_column, $sort_order, $current_params) ?>'">
        Serial <span class="sort-icon"><?= getSortIcon('SerialNumber', $sort_column, $sort_order) ?></span>
      </th>
      <th class="sortable" onclick="window.location.href='<?= getSortUrl('TagNumber', $sort_column, $sort_order, $current_params) ?>'">
        Tag <span class="sort-icon"><?= getSortIcon('TagNumber', $sort_column, $sort_order) ?></span>
      </th>
      <th class="sortable" onclick="window.location.href='<?= getSortUrl('TypeName', $sort_column, $sort_order, $current_params) ?>'">
        Type <span class="sort-icon"><?= getSortIcon('TypeName', $sort_column, $sort_order) ?></span>
      </th>
      <th class="sortable" onclick="window.location.href='<?= getSortUrl('Status', $sort_column, $sort_order, $current_params) ?>'">
        Status <span class="sort-icon"><?= getSortIcon('Status', $sort_column, $sort_order) ?></span>
      </th>
      <?php if ($asset_type !== ''): ?>
        <?php foreach ($attribute_columns as $attr): ?>
          <th class="sortable" onclick="window.location.href='<?= getSortUrl($attr, $sort_column, $sort_order, $current_params) ?>'">
            <?= htmlspecialchars($attr) ?> <span class="sort-icon"><?= getSortIcon($attr, $sort_column, $sort_order) ?></span>
          </th>
        <?php endforeach; ?>
      <?php endif; ?>
      <th class="sortable" onclick="window      <th class="sortable" onclick="window.location.href='<?= getSortUrl('DateReceived', $sort_column, $sort_order, $current_params) ?>'">
        Date Received <span class="sort-icon"><?= getSortIcon('DateReceived', $sort_column, $sort_order) ?></span>
      </th>
      <th class="sortable" onclick="window.location.href='<?= getSortUrl('DateReturned', $sort_column, $sort_order, $current_params) ?>'">
        Date Returned <span class="sort-icon"><?= getSortIcon('DateReturned', $sort_column, $sort_order) ?></span>
      </th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($res as $r): ?>
    <tr>
      <td><?= $r['AssetID'] ?></td>
      <td><?= htmlspecialchars($r['EmpName'] ?? 'Unassigned') ?></td>
      <td><?= htmlspecialchars($r['EmpTitle'] ?? '') ?></td>
      <td><?= htmlspecialchars($r['EmpDept'] ?? '') ?></td>
      <td><?= htmlspecialchars($r['EmpLoc'] ?? '') ?></td>
      <td><?= htmlspecialchars($r['SerialNumber']) ?></td>
      <td><?= htmlspecialchars($r['TagNumber']) ?></td>
      <td><?= htmlspecialchars($r['TypeName']) ?></td>
      <td><?= htmlspecialchars($r['Status']) ?></td>
      <?php if ($asset_type !== ''): ?>
        <?php foreach ($attribute_columns as $attr): ?>
          <td><?= htmlspecialchars($r[$attr] ?? '') ?></td>
        <?php endforeach; ?>
      <?php endif; ?>
      <td><?= htmlspecialchars($r['DateReceived'] ? substr($r['DateReceived'], 0, 10) : '') ?></td>
      <td><?= htmlspecialchars($r['DateReturned'] ? substr($r['DateReturned'], 0, 10) : '') ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

</body>
</html>