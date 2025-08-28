<?php
// Token del bot de Telegram
$botToken = "7574806582:AAFKWFTIGy-vrqEpijTV9BClkpHAz0lZ2Yw";

// URL base para Telegram API
$telegramAPI = "https://api.telegram.org/bot$botToken/";

// Conexión MySQL
$conexion = new mysqli("localhost", "root", "", "viajes");
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
?>
