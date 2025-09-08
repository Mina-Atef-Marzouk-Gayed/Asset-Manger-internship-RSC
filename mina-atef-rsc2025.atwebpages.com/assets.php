<?php
/**
 * Assets Management
 * Displays a list of all assets with search, filtering, and management capabilities
 */

include 'db.php';

// ============================================================================
// INPUT VALIDATION & SORTING
// ============================================================================

// Get sort parameters
$sort_column = $_GET['sort'] ?? 'AssetID';
$sort_order = $_GET['order'] ?? 'DESC';

// Validate sort column
$allowed_columns = ['AssetID', 'SerialNumber', 'TagNumber', 'TypeName', 'Status', 'EmpName', 'AssignDate'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'AssetID';
}

// Validate sort order
$sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

// ============================================================================
// DATABASE QUERIES
// ============================================================================

/**
 * Build ORDER BY clause for sorting
 */
function buildOrderByClause($sort_column, $sort_order) {
    switch($sort_column) {
        case 'AssetID':
            return "a.AssetID $sort_order";
        case 'SerialNumber':
            return "a.SerialNumber $sort_order";
        case 'TagNumber':
            return "a.TagNumber $sort_order";
        case 'TypeName':
            return "t.TypeName $sort_order";
        case 'Status':
            return "a.Status $sort_order";
        case 'EmpName':
            return "e.Name $sort_order";
        case 'AssignDate':
            return "aa.DateReceived $sort_order"; // ✅ Use DateReceived only
        default:
            return "a.AssetID DESC";
    }
}

/**
 * Get asset summary statistics
 */
function getAssetSummary($conn) {
    $sql = "SELECT
                COUNT(*) as total_assets,
                COUNT(CASE WHEN AssignedTo IS NOT NULL THEN 1 END) as assigned_assets,
                COUNT(CASE WHEN AssignedTo IS NULL THEN 1 END) as unassigned_assets,
                COUNT(CASE WHEN Status = 'Working' THEN 1 END) as working_assets,
                COUNT(CASE WHEN Status = 'In Repair' THEN 1 END) as repair_assets,
                COUNT(CASE WHEN Status = 'Trashed' THEN 1 END) as trashed_assets
            FROM assets";
    
    $result = $conn->query($sql);
    return $result->fetch_assoc();
}

/**
 * Get asset types for dropdown
 */
function getAssetTypes($conn) {
    $sql = "SELECT TypeID, TypeName FROM assettypes ORDER BY TypeName";
    $result = $conn->query($sql);
    $types = [];
    while ($type = $result->fetch_assoc()) {
        $types[] = $type;
    }
    return $types;
}

/**
 * Build search conditions and parameters
 */
function buildSearchConditions($search_params) {
    $conditions = [];
    $params = [];
    $types = '';
    
    if (!empty($search_params['search_serial'])) {
        $conditions[] = "a.SerialNumber LIKE ?";
        $params[] = "%" . $search_params['search_serial'] . "%";
        $types .= 's';
    }
    
    if (!empty($search_params['search_type'])) {
        $conditions[] = "a.TypeID = ?";
        $params[] = $search_params['search_type'];
        $types .= 'i';
    }
    
    if (!empty($search_params['search_status'])) {
        $conditions[] = "a.Status = ?";
        $params[] = $search_params['search_status'];
        $types .= 's';
    }
    
    // Handle unassigned assets filter
    if (isset($search_params['unassigned']) && $search_params['unassigned'] == '1') {
        $conditions[] = "a.AssignedTo IS NULL";
    }
    
    return [
        'conditions' => $conditions,
        'params' => $params,
        'types' => $types
    ];
}

/**
 * Get assets with search and sorting
 */
/**
 * Get assets with full assignment history
 */
function getAssets($conn, $search_conditions, $order_by) {
    $sql = "SELECT 
                a.AssetID,
                a.SerialNumber,
                a.TagNumber,
                t.TypeName,
                a.Status,
                e.Name AS EmpName,
                ti.TitleName AS EmpTitle,
                d.DepartmentName AS Department,
                CASE 
                    WHEN l2.LocationName IS NOT NULL 
                        THEN CONCAT(l2.LocationName, ' → ', l.LocationName)
                    ELSE l.LocationName
                END as Location,
                aa.DateReceived,
                aa.DateReturned
            FROM assets a
            JOIN assettypes t ON t.TypeID = a.TypeID
            LEFT JOIN (
                SELECT x.*
                FROM asset_assignments x
                INNER JOIN (
                    SELECT AssetID, MAX(AssignmentID) AS MaxAssignID
                    FROM asset_assignments
                    GROUP BY AssetID
                ) y ON x.AssetID = y.AssetID AND x.AssignmentID = y.MaxAssignID
            ) aa ON aa.AssetID = a.AssetID
            LEFT JOIN employees e ON e.EmployeeID = aa.EmployeeID
            LEFT JOIN titles ti ON ti.TitleID = e.TitleID
            LEFT JOIN departments d ON d.DepartmentID = e.DepartmentID
            LEFT JOIN locations l ON l.LocationID = e.LocationID
            LEFT JOIN locations l2 ON l.ParentID = l2.LocationID";
    
    if (!empty($search_conditions['conditions'])) {
        $sql .= " WHERE " . implode(" AND ", $search_conditions['conditions']);
    }
    
    $sql .= " ORDER BY $order_by";
    
    if (!empty($search_conditions['params'])) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($search_conditions['types'], ...$search_conditions['params']);
        $stmt->execute();
        return $stmt->get_result();
    } else {
        return $conn->query($sql);
    }
}



// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Generate sort URL with preserved search parameters
 */
function getSortUrl($column, $current_sort, $current_order) {
    global $_GET;
    $new_order = ($current_sort === $column && $current_order === 'ASC') ? 'DESC' : 'ASC';
    
    $params = $_GET;
    $params['sort'] = $column;
    $params['order'] = $new_order;
    
    return '?' . http_build_query($params);
}

/**
 * Get sort icon
 */
function getSortIcon($column, $current_sort, $current_order) {
    if ($current_sort === $column) {
        return $current_order === 'ASC' ? '↑' : '↓';
    }
    return '↕';
}

/**
 * Format date for display
 */
function formatDate($date) {
    return !empty($date) ? substr($date, 0, 10) : '';
}

/**
 * Get status badge class
 */
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Working': return 'success';
        case 'In Repair': return 'warning';
        case 'Trashed': return 'danger';
        default: return 'secondary';
    }
}

// ============================================================================
// DATA RETRIEVAL
// ============================================================================

$order_by = buildOrderByClause($sort_column, $sort_order);
$summary = getAssetSummary($conn);
$asset_types = getAssetTypes($conn);
$search_conditions = buildSearchConditions($_GET);
$assets = getAssets($conn, $search_conditions, $order_by);
$result_count = $assets->num_rows;

// ============================================================================
// HTML OUTPUT
// ============================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Assets - Asset Manager</title>
    
    <!-- Stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* Custom Styles */
        .sortable { 
            cursor: pointer; 
        }
        
        .sortable:hover { 
            background-color: #f8f9fa; 
        }
        
        .sort-icon { 
            margin-left: 5px; 
            color: #6c757d; 
        }
        
        .summary-card { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
        }
        
        .action-buttons {
            white-space: nowrap;
        }
    </style>
</head>

<body class="container py-4">
    <!-- ============================================================================
         HEADER
         ============================================================================ -->
    <header class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-laptop"></i> Assets</h2>
            <p class="text-muted mb-0">Manage and track all system assets</p>
        </div>
        <div>
            <a href="add_asset.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Asset
            </a>
            
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </header>

    <!-- ============================================================================
         SEARCH FORM
         ============================================================================ -->
    <section class="mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-search"></i> Search Assets</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="search_serial" class="form-label">Serial Number</label>
                        <input type="text" class="form-control" id="search_serial" name="search_serial" 
                               value="<?= htmlspecialchars($_GET['search_serial'] ?? '') ?>" 
                               placeholder="Enter serial number...">
                    </div>
                    <div class="col-md-3">
                        <label for="search_type" class="form-label">Asset Type</label>
                        <select class="form-select" id="search_type" name="search_type">
                            <option value="">All Types</option>
                            <?php foreach ($asset_types as $type): ?>
                                <option value="<?= $type['TypeID'] ?>" 
                                        <?= ($_GET['search_type'] ?? '') == $type['TypeID'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type['TypeName']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="search_status" class="form-label">Status</label>
                        <select class="form-select" id="search_status" name="search_status">
                            <option value="">All Statuses</option>
                            <option value="Working" <?= ($_GET['search_status'] ?? '') == 'Working' ? 'selected' : '' ?>>Working</option>
                            <option value="In Repair" <?= ($_GET['search_status'] ?? '') == 'In Repair' ? 'selected' : '' ?>>In Repair</option>
                            <option value="Trashed" <?= ($_GET['search_status'] ?? '') == 'Trashed' ? 'selected' : '' ?>>Trashed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <?php if (isset($_GET['search_serial']) || isset($_GET['search_type']) || isset($_GET['search_status']) || isset($_GET['unassigned'])): ?>
                                <a href="assets.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Clear Search
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
                
                <!-- Quick Filter Buttons -->
                <div class="mt-3">
                    <small class="text-muted">Quick Filters:</small>
                    <div class="btn-group btn-group-sm" role="group">
                        <a href="assets.php" class="btn <?= (!isset($_GET['search_status']) && !isset($_GET['unassigned'])) ? 'btn-primary' : 'btn-outline-primary' ?>">
                            <i class="fas fa-list"></i> All Assets
                        </a>
                        <a href="assets.php?search_status=Working" class="btn <?= ($_GET['search_status'] ?? '') == 'Working' ? 'btn-success' : 'btn-outline-success' ?>">
                            <i class="fas fa-check-circle"></i> Working
                        </a>
                        <a href="assets.php?search_status=In Repair" class="btn <?= ($_GET['search_status'] ?? '') == 'In Repair' ? 'btn-warning' : 'btn-outline-warning' ?>">
                            <i class="fas fa-tools"></i> In Repair
                        </a>
                        <a href="assets.php?search_status=Trashed" class="btn <?= ($_GET['search_status'] ?? '') == 'Trashed' ? 'btn-danger' : 'btn-outline-danger' ?>">
                            <i class="fas fa-trash"></i> Trashed
                        </a>
                        <a href="assets.php?unassigned=1" class="btn <?= isset($_GET['unassigned']) ? 'btn-info' : 'btn-outline-info' ?>">
                            <i class="fas fa-user-slash"></i> Unassigned
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================================
         SUCCESS MESSAGE
         ============================================================================ -->
    <?php if (isset($_GET['msg'])): ?>
        <section class="mb-4">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </section>
    <?php endif; ?>

    <!-- ============================================================================
         SEARCH RESULTS SUMMARY
         ============================================================================ -->
    <?php if (isset($_GET['search_serial']) || isset($_GET['search_type']) || isset($_GET['search_status']) || isset($_GET['unassigned'])): ?>
        <section class="mb-4">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Search results: <strong><?= number_format($result_count) ?></strong> assets found
            </div>
        </section>
    <?php endif; ?>

    <!-- ============================================================================
         SUMMARY STATISTICS
         ============================================================================ -->
    <section class="mb-4">
        <div class="row">
            <div class="col-md-2">
                <div class="card summary-card">
                    <div class="card-body text-center">
                        <h4 class="card-title"><?= number_format($summary['total_assets']) ?></h4>
                        <p class="card-text mb-0">Total Assets</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card summary-card">
                    <div class="card-body text-center">
                        <h4 class="card-title"><?= number_format($summary['assigned_assets']) ?></h4>
                        <p class="card-text mb-0">Assigned</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card summary-card">
                    <div class="card-body text-center">
                        <h4 class="card-title"><?= number_format($summary['unassigned_assets']) ?></h4>
                        <p class="card-text mb-0">Unassigned</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card summary-card">
                    <div class="card-body text-center">
                        <h4 class="card-title"><?= number_format($summary['working_assets']) ?></h4>
                        <p class="card-text mb-0">Working</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card summary-card">
                    <div class="card-body text-center">
                        <h4 class="card-title"><?= number_format($summary['repair_assets']) ?></h4>
                        <p class="card-text mb-0">In Repair</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card summary-card">
                    <div class="card-body text-center">
                        <h4 class="card-title"><?= number_format($summary['trashed_assets']) ?></h4>
                        <p class="card-text mb-0">Trashed</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================================
         ASSETS TABLE
         ============================================================================ -->

<section>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-list"></i> Asset List</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th class="sortable" onclick="window.location.href='<?= getSortUrl('AssetID', $sort_column, $sort_order) ?>'">
                                ID <span class="sort-icon"><?= getSortIcon('AssetID', $sort_column, $sort_order) ?></span>
                            </th>
                            <th class="sortable" onclick="window.location.href='<?= getSortUrl('EmpName', $sort_column, $sort_order) ?>'">
                                Name <span class="sort-icon"><?= getSortIcon('EmpName', $sort_column, $sort_order) ?></span>
                            </th>
                            <th>Title</th>
                            <th>Department</th>
                            <th>Location</th>
                            <th class="sortable" onclick="window.location.href='<?= getSortUrl('SerialNumber', $sort_column, $sort_order) ?>'">
                                Serial <span class="sort-icon"><?= getSortIcon('SerialNumber', $sort_column, $sort_order) ?></span>
                            </th>
                            <th class="sortable" onclick="window.location.href='<?= getSortUrl('TagNumber', $sort_column, $sort_order) ?>'">
                                Tag <span class="sort-icon"><?= getSortIcon('TagNumber', $sort_column, $sort_order) ?></span>
                            </th>
                            <th class="sortable" onclick="window.location.href='<?= getSortUrl('TypeName', $sort_column, $sort_order) ?>'">
                                Type <span class="sort-icon"><?= getSortIcon('TypeName', $sort_column, $sort_order) ?></span>
                            </th>
                            <th class="sortable" onclick="window.location.href='<?= getSortUrl('Status', $sort_column, $sort_order) ?>'">
                                Status <span class="sort-icon"><?= getSortIcon('Status', $sort_column, $sort_order) ?></span>
                            </th>
                            <th class="sortable" onclick="window.location.href='<?= getSortUrl('DateReceived', $sort_column, $sort_order) ?>'">
                                Date Received <span class="sort-icon"><?= getSortIcon('DateReceived', $sort_column, $sort_order) ?></span>
                            </th>
                            <th class="sortable" onclick="window.location.href='<?= getSortUrl('DateReturned', $sort_column, $sort_order) ?>'">
                                Date Returned <span class="sort-icon"><?= getSortIcon('DateReturned', $sort_column, $sort_order) ?></span>
                            </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($asset = $assets->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($asset['AssetID']) ?></strong></td>
                                <td><?= htmlspecialchars($asset['EmpName'] ?? 'Unassigned') ?></td>
                                <td><?= htmlspecialchars($asset['EmpTitle'] ?? '') ?></td>
                                <td><?= htmlspecialchars($asset['Department'] ?? '') ?></td>
                                <td><?= htmlspecialchars($asset['Location'] ?? '') ?></td>
                                <td><?= htmlspecialchars($asset['SerialNumber']) ?></td>
                                <td><?= htmlspecialchars($asset['TagNumber']) ?></td>
                                <td><?= htmlspecialchars($asset['TypeName']) ?></td>
                                <td>
                                    <span class="badge bg-<?= getStatusBadgeClass($asset['Status']) ?>">
                                        <?= htmlspecialchars($asset['Status']) ?>
                                    </span>
                                </td>
                                <td><?= !empty($asset['DateReceived']) ? date('d-m-Y', strtotime($asset['DateReceived'])) : '' ?></td>
                                <td><?= !empty($asset['DateReturned']) ? date('d-m-Y', strtotime($asset['DateReturned'])) : '' ?></td>
                                <td class="action-buttons">
                                    <a href="edit_asset.php?id=<?= $asset['AssetID'] ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="view_asset.php?id=<?= $asset['AssetID'] ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="delete_asset.php?id=<?= $asset['AssetID'] ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Are you sure you want to delete this asset?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>


</body>
</html>
