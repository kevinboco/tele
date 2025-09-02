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

// --- FUNCIÓN auxiliar para enviar mensajes ---
function enviarMensaje($apiURL, $chat_id, $mensaje, $replyMarkup = null) {
    $url = $apiURL."sendMessage?chat_id=".$chat_id."&text=".urlencode($mensaje);
    if ($replyMarkup) {
        $url .= "&reply_markup=".urlencode($replyMarkup);
    }
    file_get_contents($url);
}

// --- FUNCIÓN para guardar en BD ---
function guardarEnBD($nombre, $cedula, $fecha, $ruta, $vehiculo, $nombreArchivo) {
    $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
    if ($conn->connect_error) {
        return "❌ Error de conexión BD";
    } else {
        $sql = "INSERT INTO viajes (nombre, cedula, fecha, ruta, tipo_vehiculo, imagen) 
                VALUES ('$nombre','$cedula','$fecha','$ruta','$vehiculo','$nombreArchivo')";
        if ($conn->query($sql) === TRUE) {
            $msg = "✅ Viaje registrado con éxito!";
        } else {
            $msg = "❌ Error al registrar: " . $conn->error;
        }
        $conn->close();
        return $msg;
    }
}

// =========================
//   FLUJO /start
// =========================
if ($text == "/start") {
    $mensaje = "👋 Hola! Soy el bot de viajes.\n\n"
             . "📌 Puedes usar:\n"
             . "1️⃣ /viaje Nombre Cedula Ruta Fecha Vehiculo + Foto (modo rápido)\n"
             . "2️⃣ /viaje2 (modo guiado con botones)";

// =========================
//   FLUJO /viaje (EXISTENTE)
// =========================
} elseif (strpos($text, "/viaje") === 0 || ($photo && strpos($caption, "/viaje") === 0)) {

    $textoViaje = $text ?: $caption; // usa caption si viene con foto
    $partes = explode(" ", $textoViaje, 6);

    if (count($partes) < 6) {
        $mensaje = "⚠️ Formato incorrecto. Usa:\n/viaje Nombre Cedula Ruta Fecha Vehiculo";
    } elseif (!$photo) {
        $mensaje = "⚠️ Debes adjuntar una foto obligatoriamente junto con el comando.";
    } else {
        $nombre   = $partes[1];
        $cedula   = $partes[2];
        $ruta     = $partes[3];
        $fecha    = $partes[4];
        $vehiculo = $partes[5];
        $nombreArchivo = null;

        // --- Procesar imagen ---
        $file_id = end($photo)["file_id"]; // tomar la de mejor calidad
        $fileInfo = file_get_contents("https://api.telegram.org/bot$token/getFile?file_id=$file_id");
        $fileInfo = json_decode($fileInfo, true);

        if (isset($fileInfo["result"]["file_path"])) {
            $file_path = $fileInfo["result"]["file_path"];
            $fileUrl   = "https://api.telegram.org/file/bot$token/$file_path";

            $carpeta = __DIR__ . "/uploads/";
            if (!is_dir($carpeta)) {
                mkdir($carpeta, 0777, true);
            }

            $nombreArchivo = time() . "_" . basename($file_path);
            $rutaCompleta  = $carpeta . $nombreArchivo;

            if (!file_put_contents($rutaCompleta, file_get_contents($fileUrl))) {
                $nombreArchivo = null;
            }
        }

        if (!$nombreArchivo) {
            $mensaje = "❌ Error al guardar la imagen. Intenta de nuevo.";
        } else {
            $mensaje = guardarEnBD($nombre,$cedula,$fecha,$ruta,$vehiculo,$nombreArchivo);
        }
    }

// =========================
//   FLUJO /viaje2 (GUIADO)
// =========================
} elseif ($text == "/viaje2") {
    $mensaje = "🚍 Vamos a registrar tu viaje.\n\n✍️ Por favor escribe tu nombre:";
    file_put_contents("estado_$chat_id.txt", "esperando_nombre");
    file_put_contents("tmp_$chat_id.txt", json_encode([]));

} elseif (file_exists("estado_$chat_id.txt")) {
    $estado = trim(file_get_contents("estado_$chat_id.txt"));
    $tmp = json_decode(file_get_contents("tmp_$chat_id.txt"), true);

    if ($estado == "esperando_nombre" && $text) {
        $tmp["nombre"] = $text;
        file_put_contents("tmp_$chat_id.txt", json_encode($tmp));
        file_put_contents("estado_$chat_id.txt", "esperando_cedula");
        $mensaje = "📋 Ahora escribe tu cédula:";

    } elseif ($estado == "esperando_cedula" && $text) {
        $tmp["cedula"] = $text;
        file_put_contents("tmp_$chat_id.txt", json_encode($tmp));
        file_put_contents("estado_$chat_id.txt", "esperando_ruta");
        $mensaje = "🛣️ Escribe la ruta del viaje:";

    } elseif ($estado == "esperando_ruta" && $text) {
        $tmp["ruta"] = $text;
        file_put_contents("tmp_$chat_id.txt", json_encode($tmp));
        file_put_contents("estado_$chat_id.txt", "esperando_fecha");
        $mensaje = "📅 Escribe la fecha del viaje (YYYY-MM-DD):";

    } elseif ($estado == "esperando_fecha" && $text) {
        $tmp["fecha"] = $text;
        file_put_contents("tmp_$chat_id.txt", json_encode($tmp));
        file_put_contents("estado_$chat_id.txt", "esperando_vehiculo");

        $keyboard = [
            "keyboard" => [
                [["text" => "🚐 Bus"], ["text" => "🚖 Taxi"], ["text" => "🚗 Carro"]],
            ],
            "resize_keyboard" => true,
            "one_time_keyboard" => true
        ];
        $replyMarkup = json_encode($keyboard);

        enviarMensaje($apiURL, $chat_id, "🚘 Selecciona el tipo de vehículo:", $replyMarkup);
        exit;

    } elseif ($estado == "esperando_vehiculo" && $text) {
        $tmp["vehiculo"] = $text;
        file_put_contents("tmp_$chat_id.txt", json_encode($tmp));
        file_put_contents("estado_$chat_id.txt", "esperando_foto");
        $mensaje = "📸 Ahora adjunta la foto del viaje:";

    } elseif ($estado == "esperando_foto" && $photo) {
        $tmp = json_decode(file_get_contents("tmp_$chat_id.txt"), true);

        // --- Procesar imagen ---
        $file_id = end($photo)["file_id"];
        $fileInfo = file_get_contents("https://api.telegram.org/bot$token/getFile?file_id=$file_id");
        $fileInfo = json_decode($fileInfo, true);

        $nombreArchivo = null;
        if (isset($fileInfo["result"]["file_path"])) {
            $file_path = $fileInfo["result"]["file_path"];
            $fileUrl   = "https://api.telegram.org/file/bot$token/$file_path";

            $carpeta = __DIR__ . "/uploads/";
            if (!is_dir($carpeta)) {
                mkdir($carpeta, 0777, true);
            }

            $nombreArchivo = time() . "_" . basename($file_path);
            $rutaCompleta  = $carpeta . $nombreArchivo;

            if (!file_put_contents($rutaCompleta, file_get_contents($fileUrl))) {
                $nombreArchivo = null;
            }
        }

        if (!$nombreArchivo) {
            $mensaje = "❌ Error al guardar la imagen. Intenta de nuevo.";
        } else {
            $mensaje = guardarEnBD($tmp["nombre"], $tmp["cedula"], $tmp["fecha"], $tmp["ruta"], $tmp["vehiculo"], $nombreArchivo);
        }

        // limpiar estado
        unlink("estado_$chat_id.txt");
        unlink("tmp_$chat_id.txt");

    } else {
        $mensaje = "⚠️ Respuesta no válida. Intenta de nuevo.";
    }

// =========================
//   FLUJO DESCONOCIDO
// =========================
} else {
    $mensaje = "❓ No te entendí. Usa /start para ver comandos.";
}

// --- Enviar respuesta ---
enviarMensaje($apiURL, $chat_id, $mensaje);
?>
