<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);

$name   = $data['name']        ?? null;
$ip     = $data['ip_address']  ?? null;
$user   = $data['api_user']    ?? null;
$pass   = $data['api_password']?? null;
$lat    = $data['lat']         ?? null;
$lng    = $data['lng']         ?? null;
$port   = $data['api_port']    ?? 8333; 

if (!$name || !$ip || !$user || !$pass) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios.']);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO routers (name, ip_address, api_user, api_password, lat, lng, api_port, status, last_checked)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING', NOW())
");
$stmt->bind_param("ssssdii", $name, $ip, $user, $pass, $lat, $lng, $port);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al insertar en la base de datos.']);
}

$stmt->close();
$conn->close();
