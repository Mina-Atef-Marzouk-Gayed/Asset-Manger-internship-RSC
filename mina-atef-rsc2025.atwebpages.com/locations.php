<?php
/**
 * Locations Management
 * Displays and manages locations with hierarchical parent-child relationships
 */

include 'db.php';

// ============================================================================
// INPUT PROCESSING
// ============================================================================

$msg = '';
$error = '';

// Handle add/edit actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Add new location
    if ($action === 'add') {
        $name = trim($_POST['name']);
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        
        if ($name !== '') {
            $stmt = $conn->prepare("INSERT INTO locations (LocationName, ParentID) VALUES (?, ?)");
            $stmt->bind_param('si', $name, $parentId);
            if ($stmt->execute()) {
                $msg = 'Location added successfully';
            } else {
                $error = 'Error adding location: ' . $conn->error;
            }
        } else {
            $error = 'Location name cannot be empty';
        }
    }
    
    // Edit existing location
    elseif ($action === 'edit' && isset($_POST['location_id'])) {
        $locationId = (int)$_POST['location_id'];
        $name = trim($_POST['name']);
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        
        // Prevent setting a location as its own parent or child
        if ($parentId === $locationId) {
            $error = 'A location cannot be its own parent';
        } 
        // Check if the selected parent is a child of this location (to prevent circular references)
        elseif ($parentId !== null && isChildLocation($conn, $locationId, $parentId)) {
            $error = 'Cannot set a child location as parent (circular reference)';
        }
        else if ($name !== '') {
            $stmt = $conn->prepare("UPDATE locations SET LocationName = ?, ParentID = ? WHERE LocationID = ?");
            $stmt->bind_param('sii', $name, $parentId, $locationId);
            if ($stmt->execute()) {
                $msg = 'Location updated successfully';
            } else {
                $error = 'Error updating location: ' . $conn->error;
            }
        } else {
            $error = 'Location name cannot be empty';
        }
    }
    
    // Delete location
    elseif ($action === 'delete' && isset($_POST['location_id'])) {
        $locationId = (int)$_POST['location_id'];
        
        // Check if location has children
        $childrenCheck = $conn->prepare("SELECT COUNT(*) as count FROM locations WHERE ParentID = ?");
        $childrenCheck->bind_param('i', $locationId);
        $childrenCheck->execute();
        $childCount = $childrenCheck->get_result()->fetch_assoc()['count'];
        
        // Check if location is used by employees
        $employeeCheck = $conn->prepare("SELECT COUNT(*) as count FROM employees WHERE LocationID = ?");
        $employeeCheck->bind_param('i', $locationId);
        $employeeCheck->execute();
        $employeeCount = $employeeCheck->get_result()->fetch_assoc()['count'];
        
        if ($childCount > 0) {
            $error = 'Cannot delete location with child locations. Remove child locations first.';
        } elseif ($employeeCount > 0) {
            $error = 'Cannot delete location that is assigned to employees.';
        } else {
            $stmt = $conn->prepare("DELETE FROM locations WHERE LocationID = ?");
            $stmt->bind_param('i', $locationId);
            if ($stmt->execute()) {
                $msg = 'Location deleted successfully';
            } else {
                $error = 'Error deleting location: ' . $conn->error;
            }
        }
    }
    
    if (!$error) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?msg=" . urlencode($msg));
        exit;
    }
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Check if a location is a child of another location
 * Used to prevent circular references when updating parent-child relationships
 */
function isChildLocation($conn, $parentId, $childId) {
    // Get all children of the parent
    $stmt = $conn->prepare("SELECT LocationID FROM locations WHERE ParentID = ?");
    $stmt->bind_param('i', $parentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if ($row['LocationID'] == $childId) {
            return true; // Direct child
        }
        
        // Recursively check children of children
        if (isChildLocation($conn, $row['LocationID'], $childId)) {
            return true; // Indirect child
        }
    }
    
    return false;
}

/**
 * Get all locations with hierarchical structure
 */
function getLocationsHierarchy($conn, $parentId = null, $level = 0) {
    $locations = [];
    
    $sql = "SELECT LocationID, LocationName, ParentID FROM locations ";
    if ($parentId === null) {
        $sql .= "WHERE ParentID IS NULL ";
        $stmt = $conn->prepare($sql);
    } else {
        $sql .= "WHERE ParentID = ? ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $parentId);
    }
    
    $sql .= "ORDER BY LocationName";
    $stmt = $conn->prepare($sql);
    
    if ($parentId !== null) {
        $stmt->bind_param('i', $parentId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $row['level'] = $level;
        $locations[] = $row;
        
        // Get children recursively
        $children = getLocationsHierarchy($conn, $row['LocationID'], $level + 1);
        $locations = array_merge($locations, $children);
    }
    
    return $locations;
}

// ============================================================================
// DATA RETRIEVAL
// ============================================================================

// Get all locations for the hierarchy view
$locations = getLocationsHierarchy($conn);

// Get all locations for the dropdown (flat list)
$locationsFlat = $conn->query("SELECT LocationID, LocationName FROM locations ORDER BY LocationName")->fetch_all(MYSQLI_ASSOC);

// Get location details if editing
$editLocation = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM locations WHERE LocationID = ?");
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editLocation = $stmt->get_result()->fetch_assoc();
}

// Get message from URL if redirected
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}
?>

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Manage Locations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .location-tree .location-item {
            padding: 8px 12px;
            border-radius: 4px;
            margin-bottom: 4px;
            background-color: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .location-tree .location-name {
            flex-grow: 1;
        }
        
        .location-tree .location-actions {
            white-space: nowrap;
        }
        
        .location-level-0 { margin-left: 0; }
        .location-level-1 { margin-left: 20px; }
        .location-level-2 { margin-left: 40px; }
        .location-level-3 { margin-left: 60px; }
        .location-level-4 { margin-left: 80px; }
        .location-level-5 { margin-left: 100px; }
    </style>
</head>
<body class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-map-marker-alt"></i> Manage Locations</h2>
        <div>
            <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>
    
    <?php if ($msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Location Form (Add/Edit) -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <?= $editLocation ? 'Edit Location' : 'Add New Location' ?>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="<?= $editLocation ? 'edit' : 'add' ?>">
                        
                        <?php if ($editLocation): ?>
                            <input type="hidden" name="location_id" value="<?= $editLocation['LocationID'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Location Name</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($editLocation['LocationName'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Parent Location (Optional)</label>
                            <select name="parent_id" class="form-select">
                                <option value="">-- No Parent (Top Level) --</option>
                                <?php foreach ($locationsFlat as $loc): ?>
                                    <?php if (!$editLocation || $loc['LocationID'] != $editLocation['LocationID']): ?>
                                        <option value="<?= $loc['LocationID'] ?>" <?= (isset($editLocation['ParentID']) && $editLocation['ParentID'] == $loc['LocationID']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($loc['LocationName']) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <?= $editLocation ? 'Update Location' : 'Add Location' ?>
                            </button>
                            
                            <?php if ($editLocation): ?>
                                <a href="locations.php" class="btn btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Location Hierarchy -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    Location Hierarchy
                </div>
                <div class="card-body">
                    <?php if (empty($locations)): ?>
                        <p class="text-muted">No locations found. Add your first location using the form.</p>
                    <?php else: ?>
                        <div class="location-tree">
                            <?php foreach ($locations as $loc): ?>
                                <div class="location-item location-level-<?= $loc['level'] ?>">
                                    <div class="location-name">
                                        <?php if ($loc['level'] > 0): ?>
                                            <i class="fas fa-level-down-alt fa-rotate-90 me-2 text-muted"></i>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($loc['LocationName']) ?>
                                    </div>
                                    <div class="location-actions">
                                        <a href="?edit=<?= $loc['LocationID'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this location?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="location_id" value="<?= $loc['LocationID'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
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