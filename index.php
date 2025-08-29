<?php
// Mostrar errores (solo para debug, quitar en producción)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Token del bot
$token = "7574806582:AAFKWFTIGy-vrqEpijTV9BClkpHAz0lZ2Yw";
$apiURL = "https://api.telegram.org/bot$token/";

// Recibir datos de Telegram
$update = file_get_contents("php://input");
$update = json_decode($update, true);

if (!$update || !isset($update["message"])) {
    exit; // No hay mensaje válido
}

// Extraer datos
$chat_id = $update["message"]["chat"]["id"];
$text    = isset($update["message"]["text"]) ? $update["message"]["text"] : "";
$photo   = isset($update["message"]["photo"]) ? $update["message"]["photo"] : null;

// Función para enviar respuesta a Telegram
function enviarMensaje($apiURL, $chat_id, $mensaje) {
    file_get_contents($apiURL."sendMessage?chat_id=".$chat_id."&text=".urlencode($mensaje));
}

// Responder comandos
if ($text == "/start") {
    $mensaje = "👋 Hola! Soy el bot de viajes.\n\n📌 Usa:\n/viaje Nombre Cedula Ruta Fecha Vehiculo\nY adjunta una foto como evidencia (opcional).";
    enviarMensaje($apiURL, $chat_id, $mensaje);

} elseif (strpos($text, "/viaje") === 0) {
    // Dividir datos (máx 6 partes: comando + 5 datos)
    $partes = explode(" ", $text, 6);

    if (count($partes) < 6) {
        $mensaje = "⚠️ Formato incorrecto.\nUsa:\n/viaje Nombre Cedula Ruta Fecha Vehiculo";
        enviarMensaje($apiURL, $chat_id, $mensaje);
    } else {
        $nombre   = $partes[1];
        $cedula   = $partes[2];
        $ruta     = $partes[3];
        $fecha    = $partes[4];
        $vehiculo = $partes[5];
        $ruta_imagen = null;

        // --- Procesar imagen si viene ---
        if ($photo) {
            $file_id = end($photo)["file_id"]; // última resolución
            $fileInfo = file_get_contents("https://api.telegram.org/bot$token/getFile?file_id=$file_id");
            $fileInfo = json_decode($fileInfo, true);

            if (isset($fileInfo["result"]["file_path"])) {
                $file_path = $fileInfo["result"]["file_path"];
                $fileUrl = "https://api.telegram.org/file/bot$token/$file_path";

                // Carpeta uploads
                $carpeta = __DIR__ . "/uploads/";
                if (!is_dir($carpeta)) {
                    mkdir($carpeta, 0777, true);
                }

                // Nombre único
                $nombreArchivo = time() . "_" . basename($file_path);
                $rutaCompleta = $carpeta . $nombreArchivo;

                // Guardar archivo
                if (file_put_contents($rutaCompleta, file_get_contents($fileUrl))) {
                    $ruta_imagen = "uploads/" . $nombreArchivo; // guardar ruta relativa
                }
            }
        }

        // --- Guardar en base de datos ---
        $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
        if ($conn->connect_error) {
            enviarMensaje($apiURL, $chat_id, "❌ Error de conexión a la BD");
            exit;
        }

        $sql = "INSERT INTO viajes (nombre, cedula, fecha, ruta, tipo_vehiculo, imagen) 
                VALUES ('$nombre','$cedula','$fecha','$ruta','$vehiculo','$ruta_imagen')";

        if ($conn->query($sql) === TRUE) {
            $mensaje = "✅ Viaje registrado con éxito!";
        } else {
            $mensaje = "❌ Error al registrar: " . $conn->error;
        }
        $conn->close();

        enviarMensaje($apiURL, $chat_id, $mensaje);
    }
} else {
    $mensaje = "❓ No te entendí. Usa /start para ver comandos.";
    enviarMensaje($apiURL, $chat_id, $mensaje);
}
?>
