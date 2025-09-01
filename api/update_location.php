<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || empty($data['id']) || !isset($data['lat']) || !isset($data['lng'])) {
    echo json_encode(["success" => false, "message" => "Datos incompletos."]);
    exit;
}

$id  = intval($data['id']);
$lat = floatval($data['lat']);
$lng = floatval($data['lng']);

$stmt = $conn->prepare("UPDATE routers SET lat = ?, lng = ? WHERE id = ?");
$stmt->bind_param("ddi", $lat, $lng, $id);

$response = ["success" => false, "message" => ""];

if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = "Ubicación actualizada.";
} else {
    $response['message'] = "Error al actualizar ubicación: " . $stmt->error;
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>
