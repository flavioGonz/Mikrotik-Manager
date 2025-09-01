<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data) || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se recibieron datos o el formato es incorrecto.']);
    exit;
}

$created_count = 0;
$updated_count = 0;

// Preparar las sentencias SQL una sola vez
$stmt_check = $conn->prepare("SELECT id FROM routers WHERE ip_address = ?");
$stmt_update = $conn->prepare("UPDATE routers SET name = ?, api_user = ?, api_password = ?, lat = ?, lng = ? WHERE ip_address = ?");
$stmt_insert = $conn->prepare("INSERT INTO routers (name, ip_address, api_user, api_password, lat, lng) VALUES (?, ?, ?, ?, ?, ?)");

foreach ($data as $router) {
    // Validar que el router tiene los campos mínimos
    if (!isset($router['name'], $router['ip_address'], $router['lat'], $router['lng'])) {
        continue; // Omitir routers con datos incompletos
    }
    
    // Asignar variables y valores por defecto para campos opcionales
    $name = $router['name'];
    $ip_address = $router['ip_address'];
    $api_user = $router['api_user'] ?? null;
    $api_password = $router['api_password'] ?? null;
    $lat = $router['lat'];
    $lng = $router['lng'];

    // 1. Verificar si el router ya existe por IP
    $stmt_check->bind_param("s", $ip_address);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows > 0) {
        // 2. Si existe, ACTUALIZAR
        $stmt_update->bind_param("sssdds", $name, $api_user, $api_password, $lat, $lng, $ip_address);
        $stmt_update->execute();
        $updated_count++;
    } else {
        // 3. Si no existe, INSERTAR
        $stmt_insert->bind_param("ssssdd", $name, $ip_address, $api_user, $api_password, $lat, $lng);
        $stmt_insert->execute();
        $created_count++;
    }
}

// Cerrar las sentencias preparadas
$stmt_check->close();
$stmt_update->close();
$stmt_insert->close();
$conn->close();

echo json_encode([
    'success' => true,
    'created' => $created_count,
    'updated' => $updated_count
]);
?>