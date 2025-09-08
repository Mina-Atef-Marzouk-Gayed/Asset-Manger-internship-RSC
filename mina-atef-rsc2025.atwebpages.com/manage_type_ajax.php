<?php
include 'db.php';

$tid = (int)($_GET['type_id'] ?? 0);

$out = [];

if ($tid > 0) {
    $stmt = $conn->prepare("
        SELECT AttributeID, Name, DataType, TypeID 
        FROM assetattributes 
        WHERE TypeID = ? 
        ORDER BY AttributeID
    ");
    $stmt->bind_param('i', $tid);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $out[] = $row;
    }
    $stmt->close();
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($out);
