<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

// Último timestamp que el frontend conoce
$last_update = $_GET['last_update'] ?? '1970-01-01 00:00:00';

$response = [
    "update_available" => false,
    "new_timestamp" => $last_update
];

// Consultar el último "last_checked" de todos los routers
$sql = "SELECT MAX(last_checked) AS new_timestamp FROM routers";
$result = $conn->query($sql);

if ($result && $row = $result->fetch_assoc()) {
    if (!empty($row['new_timestamp']) && $row['new_timestamp'] > $last_update) {
        $response['update_available'] = true;
        $response['new_timestamp'] = $row['new_timestamp'];
    }
}

echo json_encode($response);
$conn->close();
?>
