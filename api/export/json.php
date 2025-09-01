<?php
require_once __DIR__ . '/../db_connect.php';

// Obtener todos los datos de los routers
$result = $conn->query("SELECT id, name, ip_address, api_user, api_password, lat, lng FROM routers");
$routers = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $routers[] = $row;
    }
}
$conn->close();

// Headers para forzar la descarga del archivo
header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="mikrotik_backup_' . date('Y-m-d') . '.json"');

// Imprimir el JSON
echo json_encode($routers, JSON_PRETTY_PRINT);
exit;
?>