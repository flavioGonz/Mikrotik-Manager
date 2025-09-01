<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['id']) || empty($data['name']) || empty($data['ip_address'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID, Nombre e IP son obligatorios.']);
    exit;
}

$id = $data['id'];
$name = $data['name'];
$ip_address = $data['ip_address'];
$api_user = $data['api_user'] ?? '';

// Lógica para actualizar contraseña solo si no está vacía
if (!empty($data['api_password'])) {
    $sql = "UPDATE routers SET name = ?, ip_address = ?, api_user = ?, api_password = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssi", $name, $ip_address, $api_user, $data['api_password'], $id);
} else {
    $sql = "UPDATE routers SET name = ?, ip_address = ?, api_user = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $name, $ip_address, $api_user, $id);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al actualizar en la base de datos.']);
}
$stmt->close(); $conn->close();
?>