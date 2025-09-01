<?php
header('Content-Type: application/json');
require_once 'db_connect.php';
require_once 'logger.php';

$data = json_decode(file_get_contents('php://input'), true);

$id     = $data['id']           ?? null;
$name   = $data['name']         ?? null;
$ip     = $data['ip_address']   ?? null;
$user   = $data['api_user']     ?? null;
$pass   = $data['api_password'] ?? null;
$port   = $data['api_port']     ?? 8333;

if (!$id || !$name || !$ip || !$user || !$pass) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios.']);
    exit;
}

$stmt = $conn->prepare("
    UPDATE routers
    SET name = ?, ip_address = ?, api_user = ?, api_password = ?, api_port = ?
    WHERE id = ?
");
$stmt->bind_param("ssssii", $name, $ip, $user, $pass, $port, $id);

if ($stmt->execute()) {
    log_router("Router editado: id=$id ($name $ip:$port)");
    log_audit('system', 'EDIT_ROUTER', "id=$id $name $ip:$port");
    echo json_encode(['success' => true, 'message' => 'Router actualizado correctamente.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al actualizar en la base de datos.']);
}

$stmt->close();
$conn->close();
