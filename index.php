<?php
// Mostrar errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Token del bot
$token = "7574806582:AAFKWFTIGy-vrqEpijTV9BClkpHAz0lZ2Yw";
$apiURL = "https://api.telegram.org/bot$token/";

// Recibir datos de Telegram
$update = file_get_contents("php://input");
file_put_contents("debug.txt", $update . PHP_EOL, FILE_APPEND); // Para depurar
$update = json_decode($update, true);

// Extraer datos básicos
$chat_id = $update["message"]["chat"]["id"] ?? null;
$text = $update["message"]["text"] ?? "";
$photo = $update["message"]["photo"] ?? null; // Si viene foto

// --- Conexión BD ---
$conn = new mysqli("srvXXXX.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    file_get_contents($apiURL."sendMessage?chat_id=".$chat_id."&text=".urlencode("❌ Error de conexión BD"));
    exit;
}

// --- Lógica ---
if ($text == "/start") {
    $mensaje = "👋 Hola! Soy el bot de viajes.\n\n📌 Usa:\n/viaje Nombre Cedula Ruta Fecha Vehiculo";
} elseif (strpos($text, "/viaje") === 0) {
    // Dividir datos
    $partes = explode(" ", $text, 6); 
    if (count($partes) < 6) {
        $mensaje = "⚠️ Formato incorrecto.\nEjemplo:\n/viaje Juan 12345 Maicao 2025-08-29 Bus";
    } else {
        $nombre   = $conn->real_escape_string($partes[1]);
        $cedula   = $conn->real_escape_string($partes[2]);
        $ruta     = $conn->real_escape_string($partes[3]);
        $fecha    = $conn->real_escape_string($partes[4]);
        $vehiculo = $conn->real_escape_string($partes[5]);

        // --- Si viene una foto, la descargamos ---
        $ruta_imagen = NULL;
        if ($photo) {
            $file_id = end($photo)["file_id"]; // última resolución
            $fileInfo = file_get_contents("https://api.telegram.org/bot$token/getFile?file_id=$file_id");
            $fileInfo = json_decode($fileInfo, true);

            if (isset($fileInfo["result"]["file_path"])) {
                $file_path = $fileInfo["result"]["file_path"];
                $fileUrl = "https://api.telegram.org/file/bot$token/$file_path";

                // Carpeta donde guardar
                $carpeta = __DIR__ . "/uploads/";
                if (!is_dir($carpeta)) {
                    mkdir($carpeta, 0777, true);
                }

                // Nombre único
                $nombreArchivo = time() . "_" . basename($file_path);
                $rutaCompleta = $carpeta . $nombreArchivo;

                // Guardar en servidor
                file_put_contents($rutaCompleta, file_get_contents($fileUrl));

                // Ruta que guardaremos en BD (relativa)
                $ruta_imagen = "uploads/" . $nombreArchivo;
            }
        }

        // --- Insertar en la BD ---
        $sql = "INSERT INTO viajes (nombre, cedula, fecha, ruta, tipo_vehiculo, imagen) 
                VALUES ('$nombre','$cedula','$fecha','$ruta','$vehiculo','$ruta_imagen')";

        if ($conn->query($sql) === TRUE) {
            $mensaje = "✅ Viaje registrado con éxito!";
        } else {
            $mensaje = "❌ Error al registrar: " . $conn->error;
        }
    }
} else {
    $mensaje = "❓ No te entendí. Usa /start para ver comandos.";
}

// --- Responder en Telegram ---
if ($chat_id) {
    file_get_contents($apiURL."sendMessage?chat_id=".$chat_id."&text=".urlencode($mensaje));
}

$conn->close();
?>
