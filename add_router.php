<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

// Obtener los datos JSON enviados desde el frontend
$data = json_decode(file_get_contents('php://input'), true);

// Validar que los datos necesarios estén presentes
if (
    empty($data['name']) || 
    empty($data['ip_address']) || 
    !isset($data['lat']) || 
    !isset($data['lng'])
) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios (Nombre, IP, Coordenadas).']);
    exit;
}

// Asignar variables
$name = $data['name'];
$ip_address = $data['ip_address'];
$api_user = $data['api_user'] ?? ''; // Opcional, por defecto vacío
$api_password = $data['api_password'] ?? ''; // Opcional, por defecto vacío
$lat = $data['lat'];
$lng = $data['lng'];

// Usar sentencias preparadas para prevenir inyección SQL
$stmt = $conn->prepare(
    "INSERT INTO routers (name, ip_address, api_user, api_password, lat, lng, status) VALUES (?, ?, ?, ?, ?, ?, 'PENDING')"
);
// "ssssdd" significa: string, string, string, string, double, double
$stmt->bind_param("ssssdd", $name, $ip_address, $api_user, $api_password, $lat, $lng);

if ($stmt->execute()) {
    // Devolver éxito y el ID del nuevo router
    echo json_encode(['success' => true, 'new_id' => $conn->insert_id]);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Error al guardar en la base de datos: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>