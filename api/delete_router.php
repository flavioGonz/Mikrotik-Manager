<?php
header('Content-Type: application/json');
require_once 'db_connect.php';
require_once 'logger.php';

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se proporcionó un ID de router.']);
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM routers WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Error preparando statement: " . $conn->error);
    }
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            log_router("Router eliminado: id=$id");
            log_audit('system', 'DELETE_ROUTER', "id=$id");
            echo json_encode(['success' => true, 'message' => 'Router eliminado correctamente.']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Router no encontrado.']);
        }
    } else {
        throw new Exception("Error al ejecutar DELETE: " . $stmt->error);
    }

    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
