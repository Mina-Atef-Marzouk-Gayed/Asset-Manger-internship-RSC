<?php
/**
 * Asset Manager - Main Dashboard
 * Provides an overview of the asset management system with quick stats and navigation
 */

include 'db.php';

// ============================================================================
// DATABASE QUERIES
// ============================================================================

/**
 * Get system statistics
 */
function getSystemStats($conn) {
    $stats = [];
    
    // Total assets
    $result = $conn->query("SELECT COUNT(*) AS count FROM assets");
    $stats['total_assets'] = $result->fetch_assoc()['count'];
    
    // Total employees
    $result = $conn->query("SELECT COUNT(*) AS count FROM employees");
    $stats['total_employees'] = $result->fetch_assoc()['count'];
    
    // Total asset types
    $result = $conn->query("SELECT COUNT(*) AS count FROM assettypes");
    $stats['total_asset_types'] = $result->fetch_assoc()['count'];
    
    // Assigned assets
    $result = $conn->query("SELECT COUNT(*) AS count FROM assets WHERE AssignedTo IS NOT NULL");
    $stats['assigned_assets'] = $result->fetch_assoc()['count'];
    
    // Unassigned assets
    $result = $conn->query("SELECT COUNT(*) AS count FROM assets WHERE AssignedTo IS NULL");
    $stats['unassigned_assets'] = $result->fetch_assoc()['count'];
    
    return $stats;
}

// ============================================================================
// DATA RETRIEVAL
// ============================================================================

$stats = getSystemStats($conn);

// ============================================================================
// HTML OUTPUT
// ============================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Asset Manager - Dashboard</title>
    
    <!-- Stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* Custom Styles */
        .backup-btn { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            border: none; 
            color: white; 
        }
        
        .backup-btn:hover { 
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%); 
            color: white; 
            transform: translateY(-2px); 
            transition: all 0.3s ease; 
        }
        
        .stats-card { 
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); 
            color: white; 
        }
        
        .nav-btn {
            transition: all 0.3s ease;
        }
        
        .nav-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>

<body class="container py-4">
    <!-- ============================================================================
         HEADER
         ============================================================================ -->
    <header class="mb-4">
        <h1><i class="fas fa-cubes"></i> Asset Manager</h1>
        <p class="text-muted">Comprehensive asset and employee management system</p>
    </header>

    <!-- ============================================================================
         MAIN NAVIGATION
         ============================================================================ -->
    <section class="mb-4">
        <div class="row g-3">
            <div class="col-md-2">
                <a class="btn btn-primary w-100 nav-btn" href="employees.php">
                    <i class="fas fa-users"></i><br>
                    <small>Employees</small>
                </a>
            </div>
            <div class="col-md-2">
                <a class="btn btn-success w-100 nav-btn" href="assets.php">
                    <i class="fas fa-laptop"></i><br>
                    <small>Assets</small>
                </a>
            </div>
            <div class="col-md-2">
                <a class="btn btn-warning w-100 nav-btn" href="search.php">
                    <i class="fas fa-search"></i><br>
                    <small>Search & Export</small>
                </a>
            </div>
            <div class="col-md-2">
                <a class="btn btn-info w-100 nav-btn" href="history.php">
                    <i class="fas fa-history"></i><br>
                    <small>History</small>
                </a>
            </div>
            <div class="col-md-2">
                <a class="btn btn-outline-dark w-100 nav-btn" href="lookups.php">
                    <i class="fas fa-cog"></i><br>
                    <small>Settings</small>
                </a>
            </div>
            <div class="col-md-2">
                <a class="btn btn-secondary w-100 nav-btn" href="asset_types.php">
                    <i class="fas fa-tags"></i><br>
                    <small>Asset Types</small>
                </a>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-md-2">
                <a class="btn btn-danger w-100 nav-btn" href="locations.php">
                    <i class="fas fa-map-marker-alt"></i><br>
                    <small>Locations</small>
                </a>
            </div>
        </div>
    </section>

    <!-- ============================================================================
         QUICK STATISTICS
         ============================================================================ -->
    <section class="mb-4">
        <div class="row">
            <div class="col-md-8">
                <div class="card stats-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-chart-bar"></i> System Overview
                        </h5>
                        <div class="row text-center">
                            <div class="col-md-2">
                                <h4><?= number_format($stats['total_assets']) ?></h4>
                                <small>Total Assets</small>
                            </div>
                            <div class="col-md-2">
                                <h4><?= number_format($stats['total_employees']) ?></h4>
                                <small>Employees</small>
                            </div>
                            <div class="col-md-2">
                                <h4><?= number_format($stats['total_asset_types']) ?></h4>
                                <small>Asset Types</small>
                            </div>
                            <div class="col-md-2">
                                <h4><?= number_format($stats['assigned_assets']) ?></h4>
                                <small>Assigned</small>
                            </div>
                            <div class="col-md-2">
                                <h4><?= number_format($stats['unassigned_assets']) ?></h4>
                                <small>Unassigned</small>
                            </div>
                            <div class="col-md-2">
                                <h4><?= $stats['total_assets'] > 0 ? round(($stats['assigned_assets'] / $stats['total_assets']) * 100) : 0 ?>%</h4>
                                <small>Utilization</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ============================================================================
                 DATA PROTECTION
                 ============================================================================ -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fas fa-shield-alt"></i> Data Protection
                        </h6>
                        <p class="card-text small">
                            Protect your data with regular backups. XAMPP can be shut down unexpectedly, 
                            so always keep a backup of your database.
                        </p>
                        <a href="backup_db.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-download"></i> Create Backup Now
                        </a>
                    </div>
                </div>
                

            </div>
        </div>
    </section>

    <!-- ============================================================================
        IMPORTANT REMINDER
    ============================================================================ -->
    <section>
        <div class="alert alert-warning">
            <h6><i class="fas fa-exclamation-triangle"></i> Important Reminder:</h6>
            <p class="mb-0">
                Always create regular backups of your database, especially before making major changes or updates. 
                Use the backup button above to download your data in multiple formats.
            </p>
        </div>
    </section>
</body>
</html>
