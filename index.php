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

// === Manejo de comandos ===
if ($text == "/start") {
    enviarMensaje($apiURL, $chat_id, "👋 Hola! Soy el bot de viajes. 
📌 /agg para agregar viaje paso a paso
📌 /manual para registrar viaje manualmente");
    exit;
}

if ($text == "/agg") {
    // Igual que antes
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

// === 🔹 NUEVO: flujo manual ===
if ($text == "/manual") {
    $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
    $res = $conn->query("SELECT * FROM conductores WHERE chat_id='$chat_id'");
    if ($res && $res->num_rows > 0) {
        $conductor = $res->fetch_assoc();
        $estado = [
            "paso" => "anio", // empieza directo en año
            "conductor_id" => $conductor["id"],
            "nombre" => $conductor["nombre"],
            "cedula" => $conductor["cedula"],
            "vehiculo" => $conductor["vehiculo"]
        ];
        file_put_contents($estadoFile, json_encode($estado));
        enviarMensaje($apiURL, $chat_id, "✍️ Ingresa el *año* del viaje (ejemplo: 2025):");
    } else {
        $estado = ["paso" => "nombre"];
        file_put_contents($estadoFile, json_encode($estado));
        enviarMensaje($apiURL, $chat_id, "✍️ Ingresa tu *nombre* para registrarte:");
    }
    exit;
}

// === Flujo normal paso a paso ===
if (!empty($estado) && !$callback_query) {
    switch ($estado["paso"]) {
        // aquí sigue todo tu flujo normal (igual que antes)
    }
    file_put_contents($estadoFile, json_encode($estado));
}

// === Manejo de botones inline ===
if ($callback_query) {
    // aquí sigue TODO igual, no se tocó nada
}

// === Cualquier otro texto fuera del flujo ===
if ($chat_id && empty($estado) && !$callback_query) {
    enviarMensaje($apiURL, $chat_id, "❌ Debes usar /agg o /manual para agregar un nuevo viaje.");
}
?>
