<?php
// Configuración de la base de datos
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Usuario por defecto de XAMPP
define('DB_PASSWORD', ''); // Contraseña por defecto de XAMPP es vacía
define('DB_NAME', 'mikrotik_manager');

// Intentar conectar a la base de datos MySQL
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Chequear la conexión
if ($conn->connect_error) {
    die("ERROR: No se pudo conectar. " . $conn->connect_error);
}

// Establecer el juego de caracteres a UTF-8
$conn->set_charset("utf8mb4");
?>