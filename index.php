<?php
// === Configuración inicial ===
error_reporting(E_ALL);
ini_set('display_errors', 1);

$token = "7574806582:AAAFKWFTIGy-vrqEpijTV9BClkpHAz0lZ2Yw";
$apiURL = "https://api.telegram.org/bot$token/";

// Recibir update de Telegram
$update = json_decode(file_get_contents("php://input"), true);

// Conexión a la BD
$host = "mysql.hostinger.com";
$user = "u648222299_keboco5";    
$pass = "Bucaramanga3011";       
$db   = "u648222299_viajes";  
$conexion = new mysqli($host, $user, $pass, $db);
if ($conexion->connect_error) {
    die("Error en la conexión: " . $conexion->connect_error);
}

// Función enviar mensaje
function enviarMensaje($apiURL, $chat_id, $texto, $reply_markup = null) {
    $params = [
        "chat_id" => $chat_id,
        "text" => $texto,
        "parse_mode" => "HTML"
    ];
    if ($reply_markup) {
        $params["reply_markup"] = json_encode($reply_markup);
    }
    file_get_contents($apiURL . "sendMessage?" . http_build_query($params));
}

// === Identificar datos de update ===
$message = $update["message"] ?? null;
$callback_query = $update["callback_query"] ?? null;

$chat_id = $message["chat"]["id"] ?? ($callback_query["message"]["chat"]["id"] ?? null);
$user_id = $message["from"]["id"] ?? ($callback_query["from"]["id"] ?? null);
$text    = $message["text"] ?? null;

// Estado por usuario
$estadoFile = __DIR__ . "/estado_$user_id.json";
$estado = file_exists($estadoFile) ? json_decode(file_get_contents($estadoFile), true) : [];

// === Comando /start ===
if ($text == "/start") {
    enviarMensaje($apiURL, $chat_id, "👋 Hola, bienvenido.\nUsa:\n📌 /agg para registrar un viaje\n📌 /misviajes para ver tus viajes");
    $estado = [];
    file_put_contents($estadoFile, json_encode($estado));
}

// === Comando /agg (NO TOCADO) ===
elseif ($text == "/agg") {
    $estado = ["paso" => "fecha"];
    enviarMensaje($apiURL, $chat_id, "📅 Ingresa la fecha del viaje (formato YYYY-MM-DD):");
    file_put_contents($estadoFile, json_encode($estado));
}

// === Comando /misviajes ===
elseif ($text == "/misviajes") {
    $sql = "SELECT fecha, ruta FROM viajes WHERE user_id='$user_id' ORDER BY fecha DESC";
    $res = $conexion->query($sql);

    if ($res && $res->num_rows > 0) {
        $mensaje = "🧾 <b>Tus viajes:</b>\n\n";
        while ($row = $res->fetch_assoc()) {
            $mensaje .= "📅 " . $row["fecha"] . " | 🛣️ " . $row["ruta"] . "\n";
        }
        $mensaje .= "\n✅ Total viajes: " . $res->num_rows;
    } else {
        $mensaje = "😕 No tienes viajes registrados aún.";
    }

    enviarMensaje($apiURL, $chat_id, $mensaje);
}

// === Flujo paso a paso (texto dentro de /agg) ===
elseif (!empty($estado) && !$callback_query) {
    switch ($estado["paso"]) {
        case "fecha":
            $estado["fecha"] = trim($text);
            $estado["paso"] = "ruta";
            enviarMensaje($apiURL, $chat_id, "🛣️ Ingresa la ruta (ejemplo: Maicao-Nazareth solo ida / vuelta):");
            break;

        case "ruta":
            $estado["ruta"] = trim($text);
            $estado["paso"] = "imagen";
            enviarMensaje($apiURL, $chat_id, "📷 Envía la imagen del viaje:");
            break;

        case "imagen":
            if (isset($message["photo"])) {
                $file_id = end($message["photo"])["file_id"];

                $fecha = $conexion->real_escape_string($estado["fecha"]);
                $ruta  = $conexion->real_escape_string($estado["ruta"]);
                $sql = "INSERT INTO viajes (user_id, fecha, ruta, imagen) 
                        VALUES ('$user_id', '$fecha', '$ruta', '$file_id')";
                $conexion->query($sql);

                enviarMensaje($apiURL, $chat_id, "✅ Viaje registrado con éxito.\nUsa /misviajes para verlos.");
                $estado = [];
            } else {
                enviarMensaje($apiURL, $chat_id, "⚠️ Por favor envía una imagen.");
            }
            break;
    }
    file_put_contents($estadoFile, json_encode($estado));
}

// === Manejo de botones inline (callback_query) ===
elseif ($callback_query) {
    // Aquí podrías manejar botones si los usas en el futuro
    file_put_contents($estadoFile, json_encode($estado));
    file_get_contents($apiURL . "answerCallbackQuery?callback_query_id=" . $update["callback_query"]["id"]);
}

// === Respuesta por defecto ===
else {
    if ($chat_id) {
        enviarMensaje(
            $apiURL,
            $chat_id,
            "⚠️ No entendí ese mensaje.\n\nUsa uno de estos comandos:\n📌 /agg para registrar un viaje\n📌 /misviajes para ver tus viajes"
        );
    }
}
?>
