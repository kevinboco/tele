<?php
$host = "mysql.hostinger.com;
$user =  "u648222299_keboco5";    // tu usuario de MySQL
$pass = "Bucaramanga3011";       // tu contraseña (vacía por defecto en XAMPP)
$db   =  "u648222299_viajes";  // el nombre real de tu base de datos

$conexion = new mysqli($host, $user, $pass, $db);

if ($conexion->connect_error) {
    die("Error en la conexión: " . $conexion->connect_error);
}
?>
