<?php
/**
 * Employee Details View
 * Displays comprehensive information about an employee including their assets, history, and statistics
 */

include 'db.php';

// ============================================================================
// INPUT VALIDATION & INITIALIZATION
// ============================================================================

$employee_id = (int)($_GET['id'] ?? 0);
if (!$employee_id) {
    header('Location: employees.php');
    exit;
}

// ============================================================================
// DATABASE QUERIES
// ============================================================================

/**
 * Get employee details with related information
 */
function getEmployeeDetails($conn, $employee_id) {
// Employee details
$sql = "SELECT e.*, ti.TitleName, d.DepartmentName, 
               l1.LocationName, l1.ParentID,
               IFNULL(l2.LocationName, '') as ParentLocationName,
               CASE 
                   WHEN l2.LocationName IS NOT NULL THEN CONCAT(l2.LocationName, ' → ', l1.LocationName)
                   ELSE l1.LocationName
               END as LocationDisplayName
        FROM employees e
        LEFT JOIN titles ti ON ti.TitleID = e.TitleID
        LEFT JOIN departments d ON d.DepartmentID = e.DepartmentID
        LEFT JOIN locations l1 ON l1.LocationID = e.LocationID
        LEFT JOIN locations l2 ON l1.ParentID = l2.LocationID
        WHERE e.EmployeeID = ?";

    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Get all assets assigned to employee (including trashed)
 */
function getEmployeeAssets($conn, $employee_id) {
// Employee assets
$sql = "SELECT a.*, t.TypeName, a.TagNumber,
               aa.DateReceived AS AssignDate
        FROM assets a
        JOIN assettypes t ON t.TypeID = a.TypeID
        LEFT JOIN asset_assignments aa 
          ON aa.AssetID = a.AssetID 
         AND aa.EmployeeID = a.AssignedTo 
         AND aa.DateReturned IS NULL
        WHERE a.AssignedTo = ?
        ORDER BY a.Status, a.SerialNumber";



    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get complete asset assignment history for employee
 */
function getEmployeeAssetHistory($conn, $employee_id, $limit = 50) {
    // Get employee name first
    $stmt = $conn->prepare("SELECT Name FROM employees WHERE EmployeeID = ?");
    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $employeeName = $result['Name'] ?? '';
    $stmt->close();
    
    if (empty($employeeName)) {
        return [];
    }
    
    // Get all assets this employee has ever been assigned to (from assethistory)
// Asset history
$sql = "SELECT DISTINCT 
            ah.AssetID,
            ah.OldValue,
            ah.NewValue,
            ah.ActionDate,
            ah.Notes,
            'HISTORY' as RecordType
        FROM assethistory ah
        WHERE (ah.OldValue = ? OR ah.NewValue = ?)
        AND (ah.OldValue != 'Unassigned' OR ah.NewValue != 'Unassigned')
        ORDER BY ah.ActionDate DESC
        LIMIT ?";

    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssi', $employeeName, $employeeName, $limit);
    $stmt->execute();
    $history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Enrich history with asset details including TagNumber
    $enrichedHistory = [];
    foreach ($history as $item) {
// Enrich history with asset details
$sql2 = "SELECT a.SerialNumber, a.TagNumber, t.TypeName, a.Status
         FROM assets a
         JOIN assettypes t ON t.TypeID = a.TypeID
         WHERE a.AssetID = ?";

        
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param('i', $item['AssetID']);
        $stmt2->execute();
        $assetDetails = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        
        if ($assetDetails) {
            $enrichedHistory[] = array_merge($item, $assetDetails);
        }
    }
    
    return $enrichedHistory;
}


/**
 * Get asset statistics for employee
 */
function getEmployeeAssetStats($conn, $employee_id) {
// Asset statistics
$sql = "SELECT 
            COUNT(*) as total_assets,
            COUNT(CASE WHEN Status = 'Working' THEN 1 END) as working_assets,
            COUNT(CASE WHEN Status = 'In Repair' THEN 1 END) as repair_assets,
            COUNT(CASE WHEN Status = 'Trashed' THEN 1 END) as trashed_assets
        FROM assets 
        WHERE AssignedTo = ?";

    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Get asset specifications
 */
function getAssetSpecifications($conn, $asset_id) {
// Asset specifications
$sql = "SELECT aa.Name, aa.DataType, aps.ValueText
        FROM assetspecs aps
        JOIN assetattributes aa ON aa.AttributeID = aps.AttributeID
        WHERE aps.AssetID = ?
        ORDER BY aa.Name";

    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $asset_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ============================================================================
// DATA RETRIEVAL
// ============================================================================

$employee = getEmployeeDetails($conn, $employee_id);
if (!$employee) {
    header('Location: employees.php');
    exit;
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Working': return 'success';// green badge
        case 'In Repair': return 'warning'; // Yellow badge
        case 'Trashed': return 'danger';//Red badge
        default: return 'secondary';//Gray badge
    }
}

$assets = getEmployeeAssets($conn, $employee_id);
$history = getEmployeeAssetHistory($conn, $employee_id);
$stats = getEmployeeAssetStats($conn, $employee_id);

// ============================================================================
// HTML OUTPUT
// ============================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Details - <?= htmlspecialchars($employee['Name']) ?></title>
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* Custom Styles */
        .employee-header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .stats-card { 
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); 
            color: white; 
            border-radius: 10px;
        }
        
        .asset-card {
            border-left: 4px solid #007bff;
            margin-bottom: 1rem;
        }
        
        .asset-card.working { border-left-color: #28a745; }
        .asset-card.repair { border-left-color: #ffc107; }
        .asset-card.trashed { border-left-color: #dc3545; }
        
        .specs-table th { background-color: #f8f9fa; }
        
        .history-item { 
            border-left: 3px solid #6c757d; 
            padding-left: 1rem; 
            margin-bottom: 0.5rem; 
        }
        
        .back-btn { margin-bottom: 1rem; }
    </style>
</head>

<body class="container py-4">
    <!-- ============================================================================
         NAVIGATION
         ============================================================================ -->
    <div class="back-btn">
        <a href="employees.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Employees
        </a>
    </div>

    <!-- ============================================================================
         EMPLOYEE HEADER
         ============================================================================ -->
    <div class="employee-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1><i class="fas fa-user"></i> <?= htmlspecialchars($employee['Name']) ?></h1>
                <p class="mb-1">
                    <strong>Title:</strong> <?= htmlspecialchars($employee['TitleName'] ?? 'Not Assigned') ?>
                </p>
                <p class="mb-1">
                    <strong>Department:</strong> <?= htmlspecialchars($employee['DepartmentName'] ?? 'Not Assigned') ?>
                </p>
                <p class="mb-0">
                    <strong>Location:</strong> <?= htmlspecialchars($employee['LocationDisplayName'] ?? 'Not Assigned') ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <h3>Employee ID: <?= $employee['EmployeeID'] ?></h3>
            </div>
        </div>
    </div>

    <!-- ============================================================================
         ASSET STATISTICS
         ============================================================================ -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card stats-card">
                <div class="card-body text-center">
                    <h4 class="card-title"><?= $stats['total_assets'] ?></h4>
                    <p class="card-text mb-0">Total Assets</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card stats-card">
                <div class="card-body text-center">
                    <h4 class="card-title"><?= $stats['working_assets'] ?></h4>
                    <p class="card-text mb-0">Working</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card stats-card">
                <div class="card-body text-center">
                    <h4 class="card-title"><?= $stats['repair_assets'] ?></h4>
                    <p class="card-text mb-0">In Repair</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card stats-card">
                <div class="card-body text-center">
                    <h4 class="card-title"><?= $stats['trashed_assets'] ?></h4>
                    <p class="card-text mb-0">Trashed</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================================
         MAIN CONTENT
         ============================================================================ -->
    <div class="row">
        <!-- Currently Assigned Assets Section -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-laptop"></i> Currently Assigned Assets</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($assets)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p>No assets currently assigned to this employee.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($assets as $asset): ?>
                            <div class="card asset-card <?= strtolower(str_replace(' ', '-', $asset['Status'])) ?>">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                           <h6 class="card-title">
    <i class="fas fa-tag"></i> 
    <?= htmlspecialchars($asset['SerialNumber']) ?> 
    <?php if(!empty($asset['TagNumber'])): ?>
        (Tag: <?= htmlspecialchars($asset['TagNumber']) ?>)
    <?php endif; ?>
</h6>

                                            <p class="mb-1">
                                                <strong>Type:</strong> <?= htmlspecialchars($asset['TypeName']) ?>
                                            </p>
                                            <p class="mb-1">
                                                <strong>Status:</strong> 
                                                <span class="badge bg-<?= getStatusBadgeClass($asset['Status']) ?>">
                                                    <?= htmlspecialchars($asset['Status']) ?>
                                                </span>
                                            </p>
                                            <p class="mb-1">
                                                <strong>Date Received:</strong> 
                                                <?= !empty($asset['AssignDate']) ? date('Y-m-d', strtotime($asset['AssignDate'])) : 'Not set' ?>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mt-2">
                                                <a href="view_asset.php?id=<?= $asset['AssetID'] ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i> View Details
                                                </a>
                                                <a href="edit_asset.php?id=<?= $asset['AssetID'] ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i> Edit <?= htmlspecialchars($asset['SerialNumber']) ?>
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Asset Specifications -->
                                    <?php
                                    $specs = getAssetSpecifications($conn, $asset['AssetID']);
                                    if (!empty($specs)):
                                    ?>
                                    <div class="mt-3">
                                        <h6><i class="fas fa-cogs"></i> Specifications:</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm specs-table">
                                                <thead>
                                                    <tr>
                                                        <th>Attribute</th>
                                                        <th>Value</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($specs as $spec): ?>
                                                        <tr>
                                                            <td><strong><?= htmlspecialchars($spec['Name']) ?></strong></td>
                                                            <td><?= htmlspecialchars($spec['ValueText']) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-md-4">
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-tools"></i> Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="edit_employee.php?id=<?= $employee['EmployeeID'] ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit"></i> Edit Employee
                        </a>
                        <a href="add_asset.php?employee_id=<?= $employee['EmployeeID'] ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-plus"></i> Assign Asset
                        </a>
                        
                    </div>
                </div>
            </div>

            <!-- Complete Assignment History -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-history"></i> Complete Assignment History</h6>
                </div>
                <div class="card-body">
                    <?php 
                    // Get complete asset assignment history
                    $assetHistory = getEmployeeAssetHistory($conn, $employee_id, 50);
                    ?>
                    
                    <?php if (empty($assetHistory)): ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-clock fa-2x mb-2"></i>
                            <p>No assignment history available.</p>
                        </div>
                    <?php else: ?>
                        <div class="history-list">
                            <?php foreach ($assetHistory as $item): ?>
                                <div class="history-item border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
<strong><?= htmlspecialchars($item['SerialNumber']) ?></strong>
<?php if(!empty($item['TagNumber'])): ?>
    <span class="text-muted">(Tag: <?= htmlspecialchars($item['TagNumber']) ?>)</span>
<?php endif; ?>
<br>
<small class="text-muted"><?= htmlspecialchars($item['TypeName']) ?></small>

                                            <br>
                                            <span class="badge bg-<?= getStatusBadgeClass($item['Status']) ?>">
                                                <?= htmlspecialchars($item['Status']) ?>
                                            </span>
                                        </div>
                                        <small class="text-muted">
                                            <?= date('Y-m-d', strtotime($item['ActionDate'])) ?>
                                        </small>
                                    </div>
                                    
                                    <div class="mt-1">
                                        <?php if ($item['OldValue'] && $item['NewValue']): ?>
                                            <?php if ($item['OldValue'] == 'Unassigned'): ?>
                                                <span class="text-success">
                                                    <i class="fas fa-plus-circle"></i> Assigned to <?= htmlspecialchars($item['NewValue']) ?>
                                                </span>
                                            <?php elseif ($item['NewValue'] == 'Unassigned'): ?>
                                                <span class="text-danger">
                                                    <i class="fas fa-minus-circle"></i> Unassigned from <?= htmlspecialchars($item['OldValue']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-warning">
                                                    <i class="fas fa-exchange-alt"></i> 
                                                    <?= htmlspecialchars($item['OldValue']) ?> → <?= htmlspecialchars($item['NewValue']) ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php elseif ($item['NewValue'] && $item['NewValue'] != 'Unassigned'): ?>
                                            <span class="text-success">
                                                <i class="fas fa-plus-circle"></i> Assigned to <?= htmlspecialchars($item['NewValue']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($item['Notes']): ?>
                                        <small class="text-muted d-block mt-1">
                                            <i class="fas fa-comment"></i> <?= htmlspecialchars($item['Notes']) ?>
                                        </small>
                                    <?php endif; ?>
                                    
                                    
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
