<?php
require_once __DIR__ . '/../db_connect.php';

// Obtener todos los datos
$result = $conn->query("SELECT id, name, ip_address, api_user, api_password, lat, lng FROM routers");
$routers = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $routers[] = $row;
    }
}
$conn->close();

// Headers para forzar la descarga
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="mikrotik_backup_' . date('Y-m-d') . '.csv"');

// Abrir el "flujo de salida" de PHP para escribir el archivo
$output = fopen('php://output', 'w');

// Escribir la fila de encabezados
fputcsv($output, ['ID', 'Nombre', 'IP/Dominio', 'Usuario API', 'Password API', 'Latitud', 'Longitud']);

// Escribir los datos de cada router
foreach ($routers as $router) {
    fputcsv($output, [
        $router['id'],
        $router['name'],
        $router['ip_address'],
        $router['api_user'],
        $router['api_password'],
        $router['lat'],
        $router['lng']
    ]);
}

fclose($output);
exit;
?>