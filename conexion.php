<?php
$host = "mysql.hostinger.com";
$user = "u648222299_keboco5";    
$pass = "Bucaramanga3011";       
$db   = "u648222299_viajes";  

$conexion = new mysqli($host, $user, $pass, $db);

if ($conexion->connect_error) {
    die("Error en la conexión: " . $conexion->connect_error);
}
?>
