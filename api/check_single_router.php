<?php
/**
 * Verifica un router individual (invocado desde el botón 🔄 en el mapa).
 */
header('Content-Type: application/json');
require_once 'db_connect.php';
require_once 'logger.php';
require_once 'ros_rest_client.php';

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se proporcionó un ID.']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM routers WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$router = $res->fetch_assoc();
$stmt->close();

if (!$router) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Router no encontrado.']);
    exit;
}

$host = $router['ip_address'];
$user = $router['api_user'];
$pass = $router['api_password'];
$port = $router['api_port'] ?? 8333;
$ssl  = (int)($router['api_ssl'] ?? 0) === 1;

$new_status = 'FAIL';
try {
    $res = ros_rest_call($host, $user, $pass, '/system/resource', 'GET', null, $port, $ssl, 5);
    if ($res) {
        $new_status = 'OK';
    }
} catch (Throwable $e) {
    $new_status = 'FAIL';
}

// Update en DB
$stmt = $conn->prepare("UPDATE routers SET status=?, last_checked=NOW() WHERE id=?");
$stmt->bind_param("si", $new_status, $id);
$stmt->execute();
$stmt->close();

// Log
log_router("Verificación manual: id=$id host=$host:$port => $new_status");
log_audit('user', 'CHECK_SINGLE', "id=$id host=$host:$port => $new_status");

echo json_encode(['success' => true, 'new_status' => $new_status]);
$conn->close();
