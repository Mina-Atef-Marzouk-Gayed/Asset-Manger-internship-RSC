<?php
include 'db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

function fetchAttributes($conn, $typeId) {
    $stmt = $conn->prepare("
        SELECT AttributeID, Name, DataType, is_required, TypeID
        FROM assetattributes
        WHERE TypeID = ?
        ORDER BY AttributeID
    ");
    $stmt->bind_param('i', $typeId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $res;
}

try {
    if ($method === 'POST') {
        $action = $_POST['action'] ?? '';
        $allowedTypes = ['Text','Number','Date','Boolean'];

        if ($action === 'add') {
            $typeId = (int)($_POST['type_id'] ?? 0);
            $name = trim($_POST['attr_name'] ?? '');
            $dataType = $_POST['data_type'] ?? 'Text';
            $isRequired = ($_POST['is_required'] ?? '0') === '1' ? 1 : 0;

            if ($typeId <= 0 || $name === '') throw new Exception('Missing fields');
            if (!in_array($dataType, $allowedTypes, true)) $dataType = 'Text';

            $stmt = $conn->prepare("
                INSERT INTO assetattributes (TypeID, Name, DataType, is_required)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param('issi', $typeId, $name, $dataType, $isRequired);
            $stmt->execute();
            $stmt->close();

            echo json_encode([
                'ok' => true,
                'message' => 'Attribute added',
                'attributes' => fetchAttributes($conn, $typeId)
            ]);
            exit;
        }

        if ($action === 'delete') {
            $attrId = (int)($_POST['attribute_id'] ?? 0);
            $typeId = (int)($_POST['type_id'] ?? 0);

            if ($attrId <= 0 || $typeId <= 0) throw new Exception('Missing id');

            $stmt = $conn->prepare("DELETE FROM assetattributes WHERE AttributeID=?");
            $stmt->bind_param('i', $attrId);
            $stmt->execute();
            $stmt->close();

            echo json_encode([
                'ok' => true,
                'message' => 'Attribute deleted',
                'attributes' => fetchAttributes($conn, $typeId)
            ]);
            exit;
        }

        if ($action === 'update') {
            $attrId = (int)($_POST['attribute_id'] ?? 0);
            $typeId = (int)($_POST['type_id'] ?? 0);
            $name = trim($_POST['attr_name'] ?? '');
            $dataType = $_POST['data_type'] ?? 'Text';
            $isRequired = ($_POST['is_required'] ?? '0') === '1' ? 1 : 0;

            if ($attrId <= 0 || $typeId <= 0) throw new Exception('Missing id');
            if ($name === '') throw new Exception('Name is required');
            if (!in_array($dataType, $allowedTypes, true)) $dataType = 'Text';

            $stmt = $conn->prepare("
                UPDATE assetattributes
                SET Name=?, DataType=?, is_required=?
                WHERE AttributeID=?
            ");
            $stmt->bind_param('ssii', $name, $dataType, $isRequired, $attrId);
            $stmt->execute();
            $stmt->close();

            echo json_encode([
                'ok' => true,
                'message' => 'Attribute updated',
                'attributes' => fetchAttributes($conn, $typeId)
            ]);
            exit;
        }

        throw new Exception('Unknown action');
    }

    echo json_encode([ 'ok' => false, 'error' => 'Invalid method' ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([ 'ok' => false, 'error' => $e->getMessage() ]);
}
