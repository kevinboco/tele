<?php
// === Configuración inicial ===
error_reporting(E_ALL);
ini_set('display_errors', 1);

$token = "7574806582:AAFKWFTIGy-vrqEpijTV9BClkpHAz0lZ2Yw";
$apiURL = "https://api.telegram.org/bot$token/";

// === Función para enviar mensajes ===
function enviarMensaje($apiURL, $chat_id, $texto, $keyboard = null) {
    $data = [
        "chat_id" => $chat_id,
        "text" => $texto,
        "parse_mode" => "Markdown"
    ];
    if ($keyboard) {
        $data["reply_markup"] = json_encode($keyboard);
    }
    file_get_contents($apiURL . "sendMessage?" . http_build_query($data));
}

// === Recibir update de Telegram ===
$update = json_decode(file_get_contents("php://input"), true);

$chat_id = $update["message"]["chat"]["id"] ?? null;
$text = trim($update["message"]["text"] ?? "");
$photo = $update["message"]["photo"] ?? null;

if (!$chat_id) {
    exit; // nada que hacer
}

// === Manejo de estado por usuario ===
$estadoFile = __DIR__ . "/estado_$chat_id.json";
$estado = file_exists($estadoFile) ? json_decode(file_get_contents($estadoFile), true) : [];

// === Comandos iniciales ===
if ($text === "/start") {
    enviarMensaje($apiURL, $chat_id, "👋 Bienvenido. Usa /agg para registrar un viaje.");
    if (file_exists($estadoFile)) unlink($estadoFile);
    $estado = [];
    exit;
}

if ($text === "/agg") {
    $estado = ["paso" => "nombre"];
    file_put_contents($estadoFile, json_encode($estado));
    enviarMensaje($apiURL, $chat_id, "✍️ Ingresa tu *nombre* completo:");
    exit;
}

// === Flujo paso a paso ===
switch ($estado["paso"] ?? null) {
    case "nombre":
        $estado["nombre"] = $text;
        $estado["paso"] = "cedula";
        enviarMensaje($apiURL, $chat_id, "🪪 Ingresa tu *cédula*:");
        break;

    case "cedula":
        if (!ctype_digit($text)) {
            enviarMensaje($apiURL, $chat_id, "⚠️ La cédula debe ser solo números. Intenta de nuevo.");
            break;
        }
        $estado["cedula"] = $text;
        $estado["paso"] = "anio";
        enviarMensaje($apiURL, $chat_id, "📅 Ingresa el *año* del viaje (ejemplo: 2025).");
        break;

    case "anio":
        if (preg_match('/^\d{4}$/', $text) && $text >= 2024 && $text <= 2030) {
            $estado["anio"] = $text;
            $estado["paso"] = "mes";
            enviarMensaje($apiURL, $chat_id, "✅ Año registrado: {$text}\n\nAhora ingresa el *mes* (01 a 12).");
        } else {
            enviarMensaje($apiURL, $chat_id, "⚠️ El año debe ser un número entre 2024 y 2030. Intenta de nuevo.");
        }
        break;

    case "mes":
        if (preg_match('/^(0?[1-9]|1[0-2])$/', $text)) {
            $estado["mes"] = str_pad($text, 2, "0", STR_PAD_LEFT);
            $estado["paso"] = "dia";
            enviarMensaje($apiURL, $chat_id, "✅ Mes registrado: {$estado['mes']}\n\nAhora ingresa el *día*.");
        } else {
            enviarMensaje($apiURL, $chat_id, "⚠️ El mes debe estar entre 01 y 12. Intenta de nuevo.");
        }
        break;

    case "dia":
        $anio = $estado["anio"];
        $mes  = $estado["mes"];
        $maxDias = cal_days_in_month(CAL_GREGORIAN, (int)$mes, (int)$anio);

        if (preg_match('/^\d{1,2}$/', $text) && $text >= 1 && $text <= $maxDias) {
            $estado["dia"] = str_pad($text, 2, "0", STR_PAD_LEFT);
            $estado["fecha"] = "{$estado['anio']}-{$estado['mes']}-{$estado['dia']}";
            $estado["paso"] = "ruta";
            enviarMensaje($apiURL, $chat_id, "✅ Fecha registrada: {$estado['fecha']}\n\n✍️ Ingresa la *ruta* del viaje.");
        } else {
            enviarMensaje($apiURL, $chat_id, "⚠️ Día inválido para ese mes. Debe estar entre 1 y $maxDias. Intenta de nuevo.");
        }
        break;

    case "ruta":
        $estado["ruta"] = $text;
        $estado["paso"] = "vehiculo";
        enviarMensaje($apiURL, $chat_id, "🚐 Ingresa el *tipo de vehículo*:");
        break;

    case "vehiculo":
        $estado["vehiculo"] = $text;
        $estado["paso"] = "foto";
        enviarMensaje($apiURL, $chat_id, "📷 Envía una *foto* como comprobante.");
        break;

    case "foto":
        if (!$photo) {
            enviarMensaje($apiURL, $chat_id, "⚠️ Debes enviar una *foto*.");
        } else {
            // Guardar foto
            $file_id = end($photo)["file_id"];
            $fileInfo = json_decode(file_get_contents("https://api.telegram.org/bot$token/getFile?file_id=$file_id"), true);
            $nombreArchivo = null;
            if (isset($fileInfo["result"]["file_path"])) {
                $file_path = $fileInfo["result"]["file_path"];
                $fileUrl   = "https://api.telegram.org/file/bot$token/$file_path";
                $carpeta = __DIR__ . "/uploads/";
                if (!is_dir($carpeta)) mkdir($carpeta, 0777, true);
                $nombreArchivo = time() . "_" . basename($file_path);
                $rutaCompleta  = $carpeta . $nombreArchivo;
                file_put_contents($rutaCompleta, file_get_contents($fileUrl));
            }

            if ($nombreArchivo) {
                $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
                $sql = "INSERT INTO viajes (nombre, cedula, fecha, ruta, tipo_vehiculo, imagen) 
                        VALUES ('{$estado['nombre']}','{$estado['cedula']}','{$estado['fecha']}','{$estado['ruta']}','{$estado['vehiculo']}','$nombreArchivo')";
                if ($conn->query($sql) === TRUE) {
                    enviarMensaje($apiURL, $chat_id, "✅ Viaje registrado con éxito!");
                } else {
                    enviarMensaje($apiURL, $chat_id, "❌ Error al registrar: " . $conn->error);
                }
            } else {
                enviarMensaje($apiURL, $chat_id, "❌ Error al guardar la imagen.");
            }

            // Cerrar flujo
            if (file_exists($estadoFile)) unlink($estadoFile);
            $estado = [];
        }
        break;

    default:
        // Si no hay flujo activo
        enviarMensaje($apiURL, $chat_id, "❌ Debes usar /agg para agregar un nuevo viaje.");
        break;
}

// Guardar estado actualizado
if (!empty($estado)) {
    file_put_contents($estadoFile, json_encode($estado));
}
