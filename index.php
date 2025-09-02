<?php
// Mostrar errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Token del bot
$token = "7574806582:AAFKWFTIGy-vrqEpijTV9BClkpHAz0lZ2Yw";
$apiURL = "https://api.telegram.org/bot$token/";

// Recibir datos de Telegram
$update = json_decode(file_get_contents("php://input"), true);

// Guardar log para depuración
file_put_contents("debug.txt", print_r($update, true) . PHP_EOL, FILE_APPEND);

// Extraer datos principales
$chat_id = $update["message"]["chat"]["id"] ?? null;
$text    = $update["message"]["text"] ?? "";
$photo   = $update["message"]["photo"] ?? null;
$caption = $update["message"]["caption"] ?? "";

// --- Comando /start ---
if ($text == "/start") {
    $mensaje = "👋 Hola! Soy el bot de viajes. Escribe:\n\n📌 /viaje Nombre Cedula Ruta Fecha Vehiculo\n\n⚠️ IMPORTANTE: Debes adjuntar una foto junto con el comando.\n\nEjemplo:\n/viaje JuanPerez 123456789 Maicao 2025-09-02 Bus";

// --- Registrar viaje ---
} elseif (($photo && strpos($caption, "/viaje") === 0) || (strpos($text, "/viaje") === 0)) {

    $textoViaje = $text ?: $caption; // usa caption si viene con foto
    $partes = explode(" ", $textoViaje, 6);

    if (count($partes) < 6) {
        $mensaje = "⚠️ Formato incorrecto. Usa:\n/viaje Nombre Cedula Ruta Fecha Vehiculo\nEjemplo:\n/viaje JuanPerez 123456789 Maicao 2025-09-02 Bus";
    } elseif (!$photo) {
        // 🚫 No adjuntó imagen
        $mensaje = "⚠️ Debes adjuntar una foto obligatoriamente junto con el comando.";
    } else {
        $nombre   = trim($partes[1]);
        $cedula   = trim($partes[2]);
        $ruta     = trim($partes[3]);
        $fecha    = trim($partes[4]);
        $vehiculo = trim($partes[5]);
        $nombreArchivo = null;

        // --- VALIDACIONES ---
        if (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ ]+$/u", $nombre)) {
            $mensaje = "❌ El nombre solo debe contener letras.";
        } elseif (!ctype_digit($cedula)) {
            $mensaje = "❌ La cédula solo debe contener números.";
        } elseif (!preg_match("/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ ]+$/u", $ruta)) {
            $mensaje = "❌ La ruta solo debe contener texto.";
        } elseif (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha) || !strtotime($fecha)) {
            $mensaje = "❌ La fecha debe estar en formato AAAA-MM-DD (ej: 2025-09-02).";
        } elseif (!preg_match("/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ ]+$/u", $vehiculo)) {
            $mensaje = "❌ El vehículo solo debe contener texto.";
        } else {
            // --- Procesar imagen ---
            $file_id = end($photo)["file_id"]; // tomar la de mejor calidad
            $fileInfo = file_get_contents("https://api.telegram.org/bot$token/getFile?file_id=$file_id");
            $fileInfo = json_decode($fileInfo, true);

            if (isset($fileInfo["result"]["file_path"])) {
                $file_path = $fileInfo["result"]["file_path"];
                $fileUrl   = "https://api.telegram.org/file/bot$token/$file_path";

                // Carpeta donde guardar
                $carpeta = __DIR__ . "/uploads/";
                if (!is_dir($carpeta)) {
                    mkdir($carpeta, 0777, true);
                }

                // Guardar con nombre único
                $nombreArchivo = time() . "_" . basename($file_path);
                $rutaCompleta  = $carpeta . $nombreArchivo;

                // Descargar la imagen
                if (!file_put_contents($rutaCompleta, file_get_contents($fileUrl))) {
                    $nombreArchivo = null;
                }
            }

            if (!$nombreArchivo) {
                $mensaje = "❌ Error al guardar la imagen. Intenta de nuevo.";
            } else {
                // --- Guardar en BD ---
                $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
                if ($conn->connect_error) {
                    $mensaje = "❌ Error de conexión BD";
                } else {
                    $sql = "INSERT INTO viajes (nombre, cedula, fecha, ruta, tipo_vehiculo, imagen) 
                            VALUES ('$nombre','$cedula','$fecha','$ruta','$vehiculo','$nombreArchivo')";
                    if ($conn->query($sql) === TRUE) {
                        $mensaje = "✅ Viaje registrado con éxito!";
                    } else {
                        $mensaje = "❌ Error al registrar: " . $conn->error;
                    }
                    $conn->close();
                }
            }
        }
    }

} else {
    $mensaje = "❓ No te entendí. Usa /start para ver comandos.";
}

// --- Enviar respuesta ---
file_get_contents($apiURL."sendMessage?chat_id=".$chat_id."&text=".urlencode($mensaje));
?>
