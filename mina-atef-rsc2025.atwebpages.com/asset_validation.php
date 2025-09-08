<?php
// asset_validation.php

// Convert dd-mm-yyyy to MySQL date
// asset_validation.php
function convertToMySQLDate($date) {
    if (empty($date)) return null;
    $d = DateTime::createFromFormat('d-m-Y', $date);
    return $d ? $d->format('Y-m-d') : null;
}


// Validate MySQL date format Y-m-d
function validateDate($date) {
    if (empty($date)) return false;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
}

// Check assignment overlap and conflicts (Issue 1: Adham & Pola Case)
function checkAssignmentOverlap($conn, $assetID, $dateReceived, $dateReturned = null, $excludeAssignmentID = null) {
    // First check for active assignments (date_returned IS NULL)
    $sql = "SELECT aa.AssignmentID, aa.EmployeeID, e.Name as EmployeeName, aa.DateReceived, aa.DateReturned
            FROM asset_assignments aa
            JOIN employees e ON aa.EmployeeID = e.EmployeeID
            WHERE aa.AssetID = ?
            AND (aa.DateReturned IS NULL OR ? <= aa.DateReturned)";
    
    if ($excludeAssignmentID) {
        $sql .= " AND aa.AssignmentID <> ?";
    }
    
    $stmt = $conn->prepare($sql);
    if ($excludeAssignmentID) {
        $stmt->bind_param("iss", $assetID, $dateReceived, $excludeAssignmentID);
    } else {
        $stmt->bind_param("is", $assetID, $dateReceived);
    }
    $stmt->execute();
    $conflict = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($conflict) {
        return [
            'exists' => true,
            'assignment_id' => $conflict['AssignmentID'],
            'employee' => [
                'id' => $conflict['EmployeeID'],
                'name' => $conflict['EmployeeName'],
                'date_received' => $conflict['DateReceived'],
                'date_returned' => $conflict['DateReturned']
            ]
        ];
    }
    
    return ['exists' => false];
}

// Format overlap error (Issue 1: Improved error message)
function formatOverlapErrorMessage($overlapInfo) {
    if (!$overlapInfo['exists']) return '';
    $emp = $overlapInfo['employee'];
    $employeeName = $emp['name'] ?? 'Unknown Employee';
    
    if ($emp['date_returned'] === null) {
        // Asset is still assigned (active assignment)
        return "This asset is still assigned to {$employeeName} or the received date overlaps. Please check the return date.";
    } else {
        // Chronological conflict
        return "This asset is still assigned to {$employeeName} or the received date overlaps. Please check the return date.";
    }
}
?>
