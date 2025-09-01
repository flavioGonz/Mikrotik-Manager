<?php
header('Content-Type: application/json');
require 'db_connect.php';

$sql = "SELECT id, name, ip_address, api_user, api_password, lat, lng, model, version, uptime, cpu_load, status, last_checked 
        FROM routers";
$result = $conn->query($sql);

$routers = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $routers[] = $row;
    }
}

echo json_encode($routers);
$conn->close();
?>
