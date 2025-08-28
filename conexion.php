<?php
$host = "localhost";
$user = "root";   // tu usuario de MySQL
$pass = "";       // tu contraseña (vacía por defecto en XAMPP)
$db   = "viajes"; // el nombre real de tu base de datos

$conexion = new mysqli($host, $user, $pass, $db);

if ($conexion->connect_error) {
    die("Error en la conexión: " . $conexion->connect_error);
}
?>
