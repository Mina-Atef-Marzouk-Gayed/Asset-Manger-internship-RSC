<?php 
include 'db.php';
include 'functions.php';
/*


// Function to get complete asset history from database
function getAssetHistory($conn, $assetID) {
    $history = [];
    
    // Get current asset state (dates from asset_assignments)
$sql = "SELECT 
            a.AssetID,
            a.SerialNumber,
            a.TagNumber,
            t.TypeName,
            a.Status,
            e.Name as EmployeeName,
            ti.TitleName AS EmpTitle,
            d.DepartmentName AS EmpDept,
            l.LocationName AS EmpLoc,
            aa.DateReceived AS DateReceived,
            aa.DateReturned AS DateReturned,
            a.created_at,
            'CURRENT' as RecordType
        FROM assets a
        JOIN assettypes t ON t.TypeID = a.TypeID
        LEFT JOIN employees e ON e.EmployeeID = a.AssignedTo
        LEFT JOIN titles ti ON ti.TitleID = e.TitleID
        LEFT JOIN departments d ON d.DepartmentID = e.DepartmentID
        LEFT JOIN locations l ON l.LocationID = e.LocationID
        LEFT JOIN asset_assignments aa 
            ON aa.AssetID = a.AssetID 
            AND aa.DateReturned IS NULL 
            AND (a.AssignedTo IS NULL OR aa.EmployeeID = a.AssignedTo)
        WHERE a.AssetID = ?";


    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $assetID);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($current) {
        $history[] = $current;
    }
    
    // Get all historical records from assethistory
    $sql2 = "SELECT 
                ah.AssetID,
                ah.OldValue,
                ah.NewValue,
                ah.ActionDate,
                ah.Notes,
                'HISTORY' as RecordType
            FROM assethistory ah
            WHERE ah.AssetID = ? 
            AND (ah.Notes LIKE '%assigned%' OR ah.Notes LIKE '%returned%' OR ah.Notes LIKE '%reassigned%')
            ORDER BY ah.ActionDate ASC";
    
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param('i', $assetID);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    
    while ($row = $result2->fetch_assoc()) {
        // Parse the notes to extract dates and employee information
        $parsedData = parseAssignmentNotes($row['Notes'], $row['OldValue'], $row['NewValue']);
        
        // Get employee details if we have an employee name
        $empDetails = null;
        if (!empty($parsedData['employeeName']) && $parsedData['employeeName'] != 'Unassigned') {
            $empDetails = getEmployeeDetails($conn, $parsedData['employeeName']);
        }
        
        // If no employee name from parsing, try to get it from OldValue or NewValue
        if (empty($parsedData['employeeName']) || $parsedData['employeeName'] == 'Unassigned') {
            if ($row['NewValue'] != 'Unassigned') {
                $empDetails = getEmployeeDetails($conn, $row['NewValue']);
            } elseif ($row['OldValue'] != 'Unassigned') {
                $empDetails = getEmployeeDetails($conn, $row['OldValue']);
            }
        }
        
        // Create historical record with parsed data
        $historicalRecord = [
            'AssetID' => $row['AssetID'],
            'SerialNumber' => '',
            'TypeName' => '',
            'Status' => '',
            'EmployeeName' => $empDetails ? $empDetails['Name'] : '',
            'EmpTitle' => $empDetails ? $empDetails['Title'] : '',
            'EmpDept' => $empDetails ? $empDetails['Department'] : '',
            'EmpLoc' => $empDetails ? $empDetails['Location'] : '',
            'DateReceived' => $parsedData['dateReceived'] ?: '',
            'DateReturned' => $parsedData['dateReturned'] ?: '',
            'created_at' => $row['ActionDate'],
            'RecordType' => 'HISTORY',
            'Notes' => $row['Notes']
        ];
        
        $history[] = $historicalRecord;
    }
    $stmt2->close();
    
    return $history;
}

// Function to parse assignment notes and extract dates and employee info
function parseAssignmentNotes($notes, $oldValue, $newValue) {
    $result = [
        'employeeName' => '',
        'dateReceived' => '',
        'dateReturned' => ''
    ];
    
    // Extract employee name from notes or values
    if (strpos($notes, 'assigned to ') !== false) {
        $result['employeeName'] = trim(str_replace('Asset assigned to ', '', $notes));
    } elseif (strpos($notes, 'reassigned to ') !== false) {
        $result['employeeName'] = trim(str_replace('Asset reassigned to ', '', $notes));
    } elseif (strpos($notes, 'returned from ') !== false) {
        $result['employeeName'] = trim(str_replace('Asset returned from ', '', $notes));
    } elseif ($newValue != 'Unassigned') {
        $result['employeeName'] = $newValue;
    } elseif ($oldValue != 'Unassigned') {
        $result['employeeName'] = $oldValue;
    }
    
    // Extract dates from notes with more comprehensive patterns
    if (preg_match('/Asset assigned on (\d{4}-\d{2}-\d{2})/', $notes, $matches)) {
        $result['dateReceived'] = $matches[1];
    }
    if (preg_match('/Asset returned on (\d{4}-\d{2}-\d{2})/', $notes, $matches)) {
        $result['dateReturned'] = $matches[1];
    }
    if (preg_match('/Initial asset assignment on (\d{4}-\d{2}-\d{2})/', $notes, $matches)) {
        $result['dateReceived'] = $matches[1];
    }
    if (preg_match('/Initial asset return on (\d{4}-\d{2}-\d{2})/', $notes, $matches)) {
        $result['dateReturned'] = $matches[1];
    }
    
    // Additional patterns for better date extraction
    if (preg_match('/assigned.*?(\d{4}-\d{2}-\d{2})/', $notes, $matches)) {
        $result['dateReceived'] = $matches[1];
    }
    if (preg_match('/returned.*?(\d{4}-\d{2}-\d{2})/', $notes, $matches)) {
        $result['dateReturned'] = $matches[1];
    }
    
    // Handle more date patterns
    if (preg_match('/(\d{4}-\d{2}-\d{2})/', $notes, $matches)) {
        // If we found a date but haven't assigned it yet, determine context
        if (empty($result['dateReceived']) && empty($result['dateReturned'])) {
            if (strpos($notes, 'assigned') !== false || strpos($notes, 'assignment') !== false) {
                $result['dateReceived'] = $matches[1];
            } elseif (strpos($notes, 'returned') !== false || strpos($notes, 'return') !== false) {
                $result['dateReturned'] = $matches[1];
            }
        }
    }
    
    return $result;
}

// Function to get employee details by name

function getAssetSpecHistory($conn, $assetID) {
    $specHistory = [];
    
    // Get specification changes from assethistory
    $sql = "SELECT 
                ah.AssetID,
                ah.OldValue,
                ah.NewValue,
                ah.ActionDate,
                ah.Notes,
                'SPEC_CHANGE' as RecordType
            FROM assethistory ah
            WHERE ah.AssetID = ? 
            AND ah.OldValue NOT LIKE '%Unassigned%'
            AND ah.NewValue NOT LIKE '%Unassigned%'
            AND ah.Notes NOT LIKE '%assigned%'
            AND ah.Notes NOT LIKE '%returned%'
            AND (ah.OldValue LIKE '%:%' OR ah.NewValue LIKE '%:%')
            ORDER BY ah.ActionDate ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $assetID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Clean up the notes for specification changes
        $cleanNotes = '';
        if (strpos($row['OldValue'], ':') !== false && strpos($row['NewValue'], ':') !== false) {
            $oldParts = explode(':', $row['OldValue'], 2);
            $newParts = explode(':', $row['NewValue'], 2);
            if (count($oldParts) == 2 && count($newParts) == 2) {
                $attributeName = trim($oldParts[0]);
                $oldValue = trim($oldParts[1]);
                $newValue = trim($newParts[1]);
                $cleanNotes = "$attributeName: $oldValue → $newValue";
            }
        }
        
        $specRecord = [
            'AssetID' => $row['AssetID'],
            'SerialNumber' => '',
            'TypeName' => '',
            'Status' => '',
            'EmployeeName' => '',
            'EmpTitle' => '',
            'EmpDept' => '',
            'EmpLoc' => '',
            'DateReceived' => '',
            'DateReturned' => '',
            'created_at' => $row['ActionDate'],
            'RecordType' => 'SPEC_CHANGE',
            'Notes' => $cleanNotes ?: $row['Notes']
        ];
        
        $specHistory[] = $specRecord;
    }
    $stmt->close();
    
    return $specHistory;
}

// Get search parameters
$employee = trim($_GET['employee'] ?? '');
$employee_id = trim($_GET['employee_id'] ?? '');
$serial = trim($_GET['serial'] ?? '');
$status = $_GET['status'] ?? '';
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$asset_type = trim($_GET['asset_type'] ?? '');
$location = trim($_GET['location'] ?? '');
$department = trim($_GET['department'] ?? '');
$include_history = isset($_GET['include_history']) ? true : false;

// Build the main SQL query to get all assets
$sql = "SELECT DISTINCT a.AssetID
        FROM Assets a
        JOIN AssetTypes t ON t.TypeID = a.TypeID
        LEFT JOIN Employees e ON e.EmployeeID = a.AssignedTo
        LEFT JOIN Titles ti ON ti.TitleID = e.TitleID
        LEFT JOIN Departments d ON d.DepartmentID = e.DepartmentID
        LEFT JOIN Locations l ON l.LocationID = e.LocationID
        WHERE 1=1";

$args = [];
$types = '';

// Add search conditions
if ($employee_id != '') {
    $sql .= " AND a.AssignedTo = ?";
    $args[] = $employee_id;
    $types .= 'i';
} elseif ($employee != '') {
    $sql .= " AND e.Name LIKE ?";
    $args[] = "%$employee%";
    $types .= 's';
}

if ($serial != '') {
    $sql .= " AND a.SerialNumber LIKE ?";
    $args[] = "%$serial%";
    $types .= 's';
}
$tagnumber = trim($_GET['tagnumber'] ?? '');
if ($tagnumber != '') {
    $sql .= " AND a.TagNumber LIKE ?";
    $args[] = "%$tagnumber%";
    $types .= 's';
}

if (in_array($status, ['Working', 'In Repair', 'Trashed'])) {
    $sql .= " AND a.Status = ?";
    $args[] = $status;
    $types .= 's';
}

if ($from != '') {
    $sql .= " AND a.DateReceived >= ?";
    $args[] = $from;
    $types .= 's';
}

if ($to != '') {
    $sql .= " AND a.DateReceived <= ?";
    $args[] = $to;
    $types .= 's';
}

if ($asset_type != '') {
    $sql .= " AND t.TypeID = ?";
    $args[] = $asset_type;
    $types .= 's';
}

if ($location != '') {
    $sql .= " AND l.LocationName = ?";
    $args[] = $location;
    $types .= 's';
}

if ($department != '') {
    $sql .= " AND d.DepartmentName = ?";
    $args[] = $department;
    $types .= 's';
}

$sql .= " ORDER BY a.AssetID DESC";

// Execute the query to get asset IDs
$stmt = $conn->prepare($sql);
if (!empty($args)) {
    $stmt->bind_param($types, ...$args);
}
$stmt->execute();
$result = $stmt->get_result();
$assetIDs = [];
while ($row = $result->fetch_assoc()) {
    $assetIDs[] = $row['AssetID'];
}
$stmt->close();

// Generate filename
if ($employee_id != '') {
    $filename = 'employee_' . $employee_id . '_assets_' . date('Y-m-d_H-i-s') . '.csv';
} else {
    $filename = 'assets_with_history_' . date('Y-m-d_H-i-s') . '.csv';
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 support in Excel
fwrite($output, "\xEF\xBB\xBF");

// Write CSV headers
$headers = [
    'Asset ID',
    'Serial Number', 
    'TagNumber',
    'Type',
    'Status',
    'Employee Name',
    'Title',
    'Department',
    'Location',
    'Date Received',
    'Date Returned',
    'Created Date',
    'Record Type',
    'Notes'
];
fputcsv($output, $headers);

// Process each asset to get current + historical data
foreach ($assetIDs as $assetID) {
    $assetHistory = getAssetHistory($conn, $assetID);
    $specHistory = getAssetSpecHistory($conn, $assetID);
    
    if (!empty($assetHistory) || !empty($specHistory)) {
        // Combine and sort all history: oldest first, then current
        $allHistory = array_merge($assetHistory, $specHistory);
        usort($allHistory, function($a, $b) {
            if ($a['RecordType'] == 'HISTORY' && $b['RecordType'] == 'CURRENT') return -1;
            if ($a['RecordType'] == 'CURRENT' && $b['RecordType'] == 'HISTORY') return 1;
            if ($a['RecordType'] == 'SPEC_CHANGE' && $b['RecordType'] == 'CURRENT') return -1;
            if ($a['RecordType'] == 'CURRENT' && $b['RecordType'] == 'SPEC_CHANGE') return 1;
            return strtotime($a['created_at']) - strtotime($b['created_at']);
        });
        
        // Enhanced deduplication and consolidation logic
        $uniqueHistory = [];
        $seenNotes = [];
        $seenAssignments = [];
        $specChanges = [];
        
        foreach ($allHistory as $record) {
            if ($record['RecordType'] == 'HISTORY') {
                // Create a unique key for assignment/return events
                $assignmentKey = '';
                if (!empty($record['EmployeeName']) && !empty($record['DateReceived'])) {
                    $assignmentKey = $record['EmployeeName'] . '_' . $record['DateReceived'] . '_assigned';
                } elseif (!empty($record['EmployeeName']) && !empty($record['DateReturned'])) {
                    $assignmentKey = $record['EmployeeName'] . '_' . $record['DateReturned'] . '_returned';
                }
                
                // Check if this is a duplicate note
                $noteKey = $record['Notes'] . '_' . $record['EmployeeName'];
                
                // Only add if it's not a duplicate note or assignment event, and has meaningful content
                if (!empty($record['Notes']) && 
                    empty($seenNotes[$noteKey]) && 
                    (empty($assignmentKey) || empty($seenAssignments[$assignmentKey]))) {
                    
                    $uniqueHistory[] = $record;
                    
                    // Mark as seen
                    $seenNotes[$noteKey] = true;
                    if (!empty($assignmentKey)) {
                        $seenAssignments[$assignmentKey] = true;
                    }
                }
            } elseif ($record['RecordType'] == 'SPEC_CHANGE') {
                // Group spec changes by timestamp for consolidation
                $timestamp = $record['created_at'];
                if (!isset($specChanges[$timestamp])) {
                    $specChanges[$timestamp] = [];
                }
                $specChanges[$timestamp][] = $record;
            } elseif ($record['RecordType'] == 'CURRENT') {
                // Always include current state
                $uniqueHistory[] = $record;
            }
        }
        
        // Consolidate spec changes into single rows
        foreach ($specChanges as $timestamp => $changes) {
            if (count($changes) > 1) {
                // Combine multiple spec changes into one row
                $combinedNotes = [];
                foreach ($changes as $change) {
                    if (!empty($change['Notes'])) {
                        $combinedNotes[] = $change['Notes'];
                    }
                }
                
                if (!empty($combinedNotes)) {
                    $consolidatedRecord = [
                        'AssetID' => $changes[0]['AssetID'],
                        'SerialNumber' => '',
                        'TypeName' => '',
                        'Status' => '',
                        'EmployeeName' => '',
                        'EmpTitle' => '',
                        'EmpDept' => '',
                        'EmpLoc' => '',
                        'DateReceived' => '',
                        'DateReturned' => '',
                        'created_at' => $timestamp,
                        'RecordType' => 'SPEC_CHANGE',
                        'Notes' => implode(', ', $combinedNotes)
                    ];
                    $uniqueHistory[] = $consolidatedRecord;
                }
    } else {
                // Single spec change, add as is
                $uniqueHistory[] = $changes[0];
            }
        }
        
        // Sort the deduplicated history by creation date
        usort($uniqueHistory, function($a, $b) {
            if ($a['RecordType'] == 'CURRENT') return 1; // Current always last
            if ($b['RecordType'] == 'CURRENT') return -1;
            return strtotime($a['created_at']) - strtotime($b['created_at']);
        });
        
        // Output deduplicated history records
        foreach ($uniqueHistory as $record) {
            $row = [
                $record['AssetID'],
                $record['SerialNumber'] ?? '',
                $record['TagNumber'] ?? '',
                $record['TypeName'] ?? '',
                $record['Status'] ?? '',
                $record['EmployeeName'] ?? '',
                $record['EmpTitle'] ?? '',
                $record['EmpDept'] ?? '',
                $record['EmpLoc'] ?? '',
                !empty($record['DateReceived']) ? date('Y-m-d', strtotime($record['DateReceived'])) : '',
                !empty($record['DateReturned']) ? date('Y-m-d', strtotime($record['DateReturned'])) : '',
                !empty($record['created_at']) ? date('Y-m-d H:i:s', strtotime($record['created_at'])) : '',
                $record['RecordType'],
                $record['Notes'] ?? ''
            ];
            fputcsv($output, $row);
        }
    }
}

// ✅ Close output AFTER finishing all assets
fclose($output);
exit;
?>