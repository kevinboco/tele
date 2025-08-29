<?php
$input = file_get_contents("php://input");
file_put_contents("debug.txt", $input . PHP_EOL, FILE_APPEND);
?>


<?php
// Mostrar errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Token del bot
$token = "7574806582:AAFKWFTIGy-vrqEpijTV9BClkpHAz0lZ2Yw";
$apiURL = "https://api.telegram.org/bot$token/";

// Recibir datos de Telegram
$update = file_get_contents("php://input");
$update = json_decode($update, true);

// Extraer datos
$chat_id = $update["message"]["chat"]["id"];
$text = $update["message"]["text"];

// Responder
if ($text == "/start") {
    $mensaje = "👋 Hola! Soy el bot de viajes. Escribe:\n\n📌 /viaje Nombre Cedula Ruta Fecha Vehiculo";
} elseif (strpos($text, "/viaje") === 0) {
    // Dividir datos
    $partes = explode(" ", $text, 6); 
    if (count($partes) < 6) {
        $mensaje = "⚠️ Formato incorrecto. Usa:\n/viaje Nombre Cedula Ruta Fecha Vehiculo";
    } else {
        $nombre = $partes[1];
        $cedula = $partes[2];
        $ruta = $partes[3];
        $fecha = $partes[4];
        $vehiculo = $partes[5];

        // Conexión a la BD
        $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
        if ($conn->connect_error) {
            $mensaje = "❌ Error de conexión BD";
        } else {
            $sql = "INSERT INTO viajes (nombre, cedula, fecha, ruta, tipo_vehiculo) 
                    VALUES ('$nombre','$cedula','$fecha','$ruta','$vehiculo')";
            if ($conn->query($sql) === TRUE) {
                $mensaje = "✅ Viaje registrado con éxito!";
            } else {
                $mensaje = "❌ Error al registrar: " . $conn->error;
            }
            $conn->close();
        }
    }
} else {
    $mensaje = "❓ No te entendí. Usa /start para ver comandos.";
}

// Enviar respuesta
file_get_contents($apiURL."sendMessage?chat_id=".$chat_id."&text=".urlencode($mensaje));
?>
