<?php
// Mostrar errores (solo para pruebas, quítalo en producción)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Token del bot
$token = "7574806582:AAAFKWFTIGy-vrqEpijTV9BClkpHAz0lZ2Yw";
$apiURL = "https://api.telegram.org/bot$token/";

// Recibir datos de Telegram
$update = json_decode(file_get_contents("php://input"), true);

// Guardar update en un log para depuración
file_put_contents("debug.txt", print_r($update, true) . PHP_EOL, FILE_APPEND);

// Variables del mensaje
$chat_id = $update["message"]["chat"]["id"] ?? null;
$text    = $update["message"]["text"] ?? "";
$photo   = $update["message"]["photo"] ?? null;
$caption = $update["message"]["caption"] ?? "";

// --- Comando /start ---
if ($text == "/start") {
    $mensaje = "👋 Hola! Soy el bot de viajes.\n\n📌 Usa:\n/viaje Nombre Cedula Ruta Fecha Vehiculo\nY adjunta una foto como evidencia (opcional).";

// --- Comando /viaje (con texto o con foto + caption) ---
} elseif (strpos($text, "/viaje") === 0 || ($photo && strpos($caption, "/viaje") === 0)) {

    // Determinar de dónde viene el texto (/viaje ...)
    $textoViaje = $text ?: $caption;
    $partes = explode(" ", $textoViaje, 6);

    if (count($partes) < 6) {
        $mensaje = "⚠️ Formato incorrecto. Usa:\n/viaje Nombre Cedula Ruta Fecha Vehiculo";
    } else {
        $nombre   = $partes[1];
        $cedula   = $partes[2];
        $ruta     = $partes[3];
        $fecha    = $partes[4];
        $vehiculo = $partes[5];

        // --- Procesar imagen si existe ---
        $ruta_imagen = null;
        if ($photo) {
            $file_id = end($photo)["file_id"]; // La foto más grande
            $fileInfo = file_get_contents("https://api.telegram.org/bot$token/getFile?file_id=$file_id");
            $fileInfo = json_decode($fileInfo, true);

            if (isset($fileInfo["result"]["file_path"])) {
                $file_path = $fileInfo["result"]["file_path"];
                $fileUrl   = "https://api.telegram.org/file/bot$token/$file_path";

                // Carpeta donde guardar las fotos
                $carpeta = __DIR__ . "/uploads/";
                if (!is_dir($carpeta)) {
                    mkdir($carpeta, 0777, true);
                }

                $nombreArchivo = time() . "_" . basename($file_path);
                $rutaCompleta  = $carpeta . $nombreArchivo;

                if (file_put_contents($rutaCompleta, file_get_contents($fileUrl))) {
                    $ruta_imagen = "uploads/" . $nombreArchivo;
                }
            }
        }

        // --- Guardar en la BD ---
        $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
        if ($conn->connect_error) {
            $mensaje = "❌ Error de conexión BD";
        } else {
            $sql = "INSERT INTO viajes (nombre, cedula, fecha, ruta, tipo_vehiculo, imagen) 
                    VALUES ('$nombre','$cedula','$fecha','$ruta','$vehiculo','$ruta_imagen')";
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

// --- Responder al usuario ---
file_get_contents($apiURL."sendMessage?chat_id=".$chat_id."&text=".urlencode($mensaje));
?>

