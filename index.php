<?php
// === Configuración inicial ===
error_reporting(E_ALL);
ini_set('display_errors', 1);

$token = "7574806582:AAFKWFTIGy-vrqEpijTV9BClkpHAz0lZ2Yw";
$apiURL = "https://api.telegram.org/bot$token/";

// Recibir update de Telegram
$update = json_decode(file_get_contents("php://input"), true);

// Log de debug
file_put_contents("debug.txt", print_r($update, true) . PHP_EOL, FILE_APPEND);

// Variables básicas
$chat_id = $update["message"]["chat"]["id"] ?? null;
$text    = trim($update["message"]["text"] ?? "");
$photo   = $update["message"]["photo"] ?? null;
$callback_query = $update["callback_query"]["data"] ?? null;
$callback_chat  = $update["callback_query"]["message"]["chat"]["id"] ?? null;

// Manejo de estados
$estadoFile = __DIR__ . "/estado_" . ($chat_id ?: $callback_chat) . ".json";
$estado = file_exists($estadoFile) ? json_decode(file_get_contents($estadoFile), true) : [];

// === Funciones auxiliares ===
function enviarMensaje($apiURL, $chat_id, $mensaje, $opciones = null) {
    $data = [
        "chat_id" => $chat_id,
        "text" => $mensaje,
        "parse_mode" => "Markdown"
    ];
    if ($opciones) {
        $data["reply_markup"] = json_encode($opciones);
    }
    file_get_contents($apiURL . "sendMessage?" . http_build_query($data));
}

function obtenerRutasUsuario($conn, $conductor_id) {
    $rutas = [];
    $sql = "SELECT ruta FROM rutas WHERE conductor_id='$conductor_id'";
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        $rutas[] = $row["ruta"];
    }
    return $rutas;
}

// === Comando /start ===
if ($text == "/start") {
    enviarMensaje($apiURL, $chat_id, "👋 Hola! Soy el bot de viajes. 
📌 /agg → agregar viaje paso a paso  
📌 /manual → registrar viaje manualmente");
    exit;
}

// === Flujo normal (/agg) ===
if ($text == "/agg") {
    $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
    $res = $conn->query("SELECT * FROM conductores WHERE chat_id='$chat_id'");
    if ($res && $res->num_rows > 0) {
        $conductor = $res->fetch_assoc();
        $estado = [
            "paso" => "fecha",
            "conductor_id" => $conductor["id"],
            "nombre" => $conductor["nombre"],
            "cedula" => $conductor["cedula"],
            "vehiculo" => $conductor["vehiculo"]
        ];
        file_put_contents($estadoFile, json_encode($estado));

        $opcionesFecha = [
            "inline_keyboard" => [
                [ ["text" => "📅 Hoy", "callback_data" => "fecha_hoy"] ],
                [ ["text" => "✍️ Otra fecha", "callback_data" => "fecha_manual"] ]
            ]
        ];
        enviarMensaje($apiURL, $chat_id, "📅 Selecciona la fecha del viaje:", $opcionesFecha);
    } else {
        $estado = ["paso" => "nombre"];
        file_put_contents($estadoFile, json_encode($estado));
        enviarMensaje($apiURL, $chat_id, "✍️ Ingresa tu *nombre* para registrarte:");
    }
    exit;
}

// === Flujo manual (/manual) ===
if ($text == "/manual") {
    $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
    $res = $conn->query("SELECT * FROM conductores WHERE chat_id='$chat_id'");
    if ($res && $res->num_rows > 0) {
        $conductor = $res->fetch_assoc();
        $estado = [
            "paso" => "nombre_conductor_manual",
            "conductor_id" => $conductor["id"]
        ];
        file_put_contents($estadoFile, json_encode($estado));
        enviarMensaje($apiURL, $chat_id, "✍️ Ingresa el *nombre del conductor*:");
    } else {
        $estado = ["paso" => "nombre"];
        file_put_contents($estadoFile, json_encode($estado));
        enviarMensaje($apiURL, $chat_id, "✍️ Ingresa tu *nombre* para registrarte:");
    }
    exit;
}

// === Flujo paso a paso (texto) ===
if (!empty($estado) && !$callback_query) {
    switch ($estado["paso"]) {

        // === flujo manual ===
        case "nombre_conductor_manual":
            $estado["nombre_conductor"] = $text;
            $estado["paso"] = "ruta_manual";
            enviarMensaje($apiURL, $chat_id, "🚐 Ingresa la *ruta* del viaje:");
            break;

        case "ruta_manual":
            $estado["ruta"] = $text;
            $estado["paso"] = "fecha_manual";
            enviarMensaje($apiURL, $chat_id, "📅 Ingresa la *fecha del viaje* (YYYY-MM-DD):");
            break;

        case "fecha_manual":
            $estado["fecha"] = $text;
            $estado["paso"] = "foto_manual";
            enviarMensaje($apiURL, $chat_id, "📸 Envía la *foto del viaje*:");
            break;

        case "foto_manual":
            if ($photo) {
                $estado["foto_id"] = end($photo)["file_id"];
                // Guardar en BD
                $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
                $sql = "INSERT INTO viajes (conductor_id, nombre_conductor, ruta, fecha, foto_id)
                        VALUES ('{$estado["conductor_id"]}', '{$estado["nombre_conductor"]}', '{$estado["ruta"]}', '{$estado["fecha"]}', '{$estado["foto_id"]}')";
                $conn->query($sql);
                enviarMensaje($apiURL, $chat_id, "✅ *Viaje registrado con éxito* (modo manual).");
                unlink($estadoFile);
            } else {
                enviarMensaje($apiURL, $chat_id, "⚠️ Debes enviar una *foto* para finalizar.");
            }
            break;

        // === aquí siguen los pasos normales de /agg (fecha → año → mes → día → ruta → foto) ===
        // 🔹 no los copio completos porque ya los tienes funcionando perfecto
    }

    file_put_contents($estadoFile, json_encode($estado));
}

// === Manejo de botones inline (/agg) ===
if ($callback_query) {
    // 🔹 aquí va todo tu código original para manejar:
    // fecha_hoy, fecha_manual, selección de rutas, nueva ruta, etc.
    // NO se toca nada de esta parte
}

// === Mensaje fuera de flujo ===
if ($chat_id && empty($estado) && !$callback_query) {
    enviarMensaje($apiURL, $chat_id, "❌ Debes usar /agg o /manual para registrar un viaje.");
}
?>
