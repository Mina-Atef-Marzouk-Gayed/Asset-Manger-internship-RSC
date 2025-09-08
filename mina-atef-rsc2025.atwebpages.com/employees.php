<?php
/**
 * Employees Management
 * Displays a list of all employees with their details, asset counts, and management actions
 */

include 'db.php';

// ============================================================================
// INPUT VALIDATION & SORTING
// ============================================================================

// Get sort parameters
$sort_column = $_GET['sort'] ?? 'EmployeeID';
$sort_order = $_GET['order'] ?? 'ASC';

// Validate sort column
$allowed_columns = ['EmployeeID', 'Name', 'Title', 'Department', 'Location', 'AssetCount'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'EmployeeID';
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
        case 'EmployeeID':
            return "e.EmployeeID $sort_order";
        case 'Name':
            return "e.Name $sort_order";
        case 'Title':
            return "ti.TitleName $sort_order";
        case 'Department':
            return "d.DepartmentName $sort_order";
        case 'Location':
            return "l.LocationName $sort_order";
        case 'AssetCount':
            return "AssetCount $sort_order";
        default:
            return "e.EmployeeID ASC";
    }
}

/**
 * Get employee summary statistics
 */
function getEmployeeSummary($conn) {
$sql = "SELECT 
            COUNT(DISTINCT e.EmployeeID) AS total_employees,
            COUNT(a.AssetID) AS total_assets_assigned
        FROM employees e
        LEFT JOIN assets a ON a.AssignedTo = e.EmployeeID";

    
    $result = $conn->query($sql);
    return $result->fetch_assoc();
}

/**
 * Get employees with asset counts
 */
function getEmployeesWithAssets($conn, $order_by) {
$sql = "SELECT 
            e.EmployeeID, 
            e.Name, 
            ti.TitleName AS Title, 
            d.DepartmentName AS Department, 
            l1.LocationName AS Location,
            CASE 
                WHEN l2.LocationName IS NOT NULL THEN CONCAT(l2.LocationName, ' → ', l1.LocationName)
                ELSE l1.LocationName
            END as LocationDisplayName,
            COUNT(a.AssetID) AS AssetCount
        FROM employees e
        LEFT JOIN titles ti ON ti.TitleID = e.TitleID
        LEFT JOIN departments d ON d.DepartmentID = e.DepartmentID
        LEFT JOIN locations l1 ON l1.LocationID = e.LocationID
        LEFT JOIN locations l2 ON l1.ParentID = l2.LocationID
        LEFT JOIN assets a ON a.AssignedTo = e.EmployeeID
        GROUP BY e.EmployeeID, e.Name, ti.TitleName, d.DepartmentName, l1.LocationName, l2.LocationName
        ORDER BY $order_by";

    
    return $conn->query($sql);
}

/**
 * Get detailed asset count breakdown for an employee
 */
function getAssetCountBreakdown($conn, $employee_id) {
$sql = "SELECT 
            COUNT(CASE WHEN Status = 'Working' THEN 1 END) as working,
            COUNT(CASE WHEN Status = 'In Repair' THEN 1 END) as repair,
            COUNT(CASE WHEN Status = 'Trashed' THEN 1 END) as trashed
        FROM assets 
        WHERE AssignedTo = ?";

    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Generate sort URL
 */
function getSortUrl($column, $current_sort, $current_order) {
    $new_order = ($current_sort === $column && $current_order === 'ASC') ? 'DESC' : 'ASC';
    return "?sort=$column&order=$new_order";
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
 * Get asset count CSS class
 */
function getAssetCountClass($count) {
    if ($count === 0) return 'zero';
    if ($count <= 2) return 'low';
    if ($count <= 5) return 'medium';
    return 'high';
}

// ============================================================================
// DATA RETRIEVAL
// ============================================================================

$order_by = buildOrderByClause($sort_column, $sort_order);
$summary = getEmployeeSummary($conn);
$employees = getEmployeesWithAssets($conn, $order_by);

// ============================================================================
// HTML OUTPUT
// ============================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employees - Asset Manager</title>
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
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
        
        .asset-count { 
            font-weight: bold; 
        }
        
        .asset-count.zero { 
            color: #6c757d; 
        }
        
        .asset-count.low { 
            color: #28a745; 
        }
        
        .asset-count.medium { 
            color: #ffc107; 
        }
        
        .asset-count.high { 
            color: #dc3545; 
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
            <h2><i class="fas fa-users"></i> Employees</h2>
            <p class="text-muted mb-0">Manage employee information and asset assignments</p>
        </div>
        <div>
            <a href="add_employee.php" class="btn btn-success">
                <i class="fas fa-plus"></i> Add Employee
            </a>
            
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </header>

    <!-- ============================================================================
         SUMMARY STATISTICS
         ============================================================================ -->
    <section class="mb-4">
        <div class="row">
            <div class="col-md-6">
                <div class="card summary-card">
                    <div class="card-body text-center">
                        <h4 class="card-title"><?= number_format($summary['total_employees']) ?></h4>
                        <p class="card-text mb-0">Total Employees</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card summary-card">
                    <div class="card-body text-center">
                        <h4 class="card-title"><?= number_format($summary['total_assets_assigned']) ?></h4>
                        <p class="card-text mb-0">Assets Assigned</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================================
         EMPLOYEES TABLE
         ============================================================================ -->
    <section>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list"></i> Employee List</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th class="sortable" onclick="window.location.href='<?= getSortUrl('EmployeeID', $sort_column, $sort_order) ?>'">
                                    ID <span class="sort-icon"><?= getSortIcon('EmployeeID', $sort_column, $sort_order) ?></span>
                                </th>
                                <th class="sortable" onclick="window.location.href='<?= getSortUrl('Name', $sort_column, $sort_order) ?>'">
                                    Name <span class="sort-icon"><?= getSortIcon('Name', $sort_column, $sort_order) ?></span>
                                </th>
                                <th class="sortable" onclick="window.location.href='<?= getSortUrl('Title', $sort_column, $sort_order) ?>'">
                                    Title <span class="sort-icon"><?= getSortIcon('Title', $sort_column, $sort_order) ?></span>
                                </th>
                                <th class="sortable" onclick="window.location.href='<?= getSortUrl('Department', $sort_column, $sort_order) ?>'">
                                    Department <span class="sort-icon"><?= getSortIcon('Department', $sort_column, $sort_order) ?></span>
                                </th>
                                <th class="sortable" onclick="window.location.href='<?= getSortUrl('Location', $sort_column, $sort_order) ?>'">
                                    Location <span class="sort-icon"><?= getSortIcon('Location', $sort_column, $sort_order) ?></span>
                                </th>
                                <th class="sortable" onclick="window.location.href='<?= getSortUrl('AssetCount', $sort_column, $sort_order) ?>'">
                                    Assets <span class="sort-icon"><?= getSortIcon('AssetCount', $sort_column, $sort_order) ?></span>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($employee = $employees->fetch_assoc()): ?>
                                <?php
                                $asset_count = $employee['AssetCount'];
                                $count_class = getAssetCountClass($asset_count);
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($employee['EmployeeID']) ?></strong></td>
                                    <td><?= htmlspecialchars($employee['Name']) ?></td>
                                    <td><?= htmlspecialchars($employee['Title'] ?? 'Not Assigned') ?></td>
                                    <td><?= htmlspecialchars($employee['Department'] ?? 'Not Assigned') ?></td>
                                    <td><?= htmlspecialchars($employee['LocationDisplayName'] ?? 'Not Assigned') ?></td>
                                    <td class="asset-count <?= $count_class ?>">
                                        <?= $asset_count ?>
                                        <?php if ($asset_count > 0): ?>
                                            <small class="text-muted">(<?= $asset_count == 1 ? 'asset' : 'assets' ?>)</small>
                                            
                                                  <a href="view_employee.php?id=<?= $employee['EmployeeID'] ?>" 
                                            title="View Details">
                                            <?php                                    
                                            $detailed_count = getAssetCountBreakdown($conn, $employee['EmployeeID']);
                                            ?>
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                            <br>
                                            
                                            <small class="text-muted">
                                                <span class="text-success"><?= $detailed_count['working'] ?>W</span> / 
                                                <span class="text-warning"><?= $detailed_count['repair'] ?>R</span> / 
                                                <span class="text-danger"><?= $detailed_count['trashed'] ?>T</span>
                                                
                                            </small>
                                      
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-buttons">

                                        <a href="edit_employee.php?id=<?= $employee['EmployeeID'] ?>" 
                                           class="btn btn-sm btn-primary" title="Edit Employee">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        
                                        <a href="delete_employee.php?id=<?= $employee['EmployeeID'] ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Are you sure you want to delete this employee?')"
                                           title="Delete Employee">
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
