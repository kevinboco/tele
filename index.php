<?php
// === Configuración inicial ===
error_reporting(E_ALL);
ini_set('display_errors', 1);

$token = "7574806582:AAFKWFTIGy-vrqEpijTV9BClkpHAz0lZ2Yw";
$apiURL = "https://api.telegram.org/bot$token/";

// Conexión BD
$host = "mysql.hostinger.com";
$user = "u648222299_keboco5";    
$pass = "Bucaramanga3011";       
$db   = "u648222299_viajes";  

$conexion = new mysqli($host, $user, $pass, $db);
if ($conexion->connect_error) {
    die("Error en la conexión: " . $conexion->connect_error);
}

// === Funciones ===
function enviarMensaje($apiURL, $chat_id, $texto, $teclado = null) {
    $payload = [
        "chat_id" => $chat_id,
        "text"    => $texto,
        "parse_mode" => "HTML"
    ];
    if ($teclado) {
        $payload["reply_markup"] = json_encode($teclado);
    }
    file_get_contents($apiURL."sendMessage?".http_build_query($payload));
}

// === Recibir update de Telegram ===
$update = json_decode(file_get_contents("php://input"), true);
$chat_id = $update["message"]["chat"]["id"] ?? ($update["callback_query"]["message"]["chat"]["id"] ?? null);
$mensaje = $update["message"]["text"] ?? "";
$callback_query = $update["callback_query"]["data"] ?? null;

// === Estado ===
$estadoFile = __DIR__."/estado_$chat_id.json";
$estado = file_exists($estadoFile) ? json_decode(file_get_contents($estadoFile), true) : [];

// === Comandos ===
if ($mensaje == "/start") {
    enviarMensaje($apiURL, $chat_id, "👋 Bienvenido. Usa:\n/agg para registrar un viaje\n/misviajes para ver tus viajes");
    $estado = [];
    file_put_contents($estadoFile, json_encode($estado));
}
elseif ($mensaje == "/agg") {
    enviarMensaje($apiURL, $chat_id, "🚗 Digita tu <b>nombre completo</b>:");
    $estado = ["paso" => "nombre"];
    file_put_contents($estadoFile, json_encode($estado));
}
elseif ($mensaje == "/misviajes") {
    $sql = "SELECT fecha, ruta FROM viajes WHERE nombre = ? ORDER BY fecha DESC";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $estado["nombre"] ?? "");
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $texto = "🗓 <b>Tus viajes:</b>\n\n";
        while ($row = $res->fetch_assoc()) {
            $texto .= "📍 <b>{$row['fecha']}</b> → {$row['ruta']}\n";
        }
        $texto .= "\n✅ Total viajes: ".$res->num_rows;
    } else {
        $texto = "⚠️ No tienes viajes registrados.";
    }
    enviarMensaje($apiURL, $chat_id, $texto);
}

// === Flujo paso a paso (texto) ===
elseif (!empty($estado) && !$callback_query) {
    switch ($estado["paso"]) {
        case "nombre":
            $estado["nombre"] = $mensaje;
            enviarMensaje($apiURL, $chat_id, "📌 Ingresa tu cédula:");
            $estado["paso"] = "cedula";
            break;

        case "cedula":
            $estado["cedula"] = $mensaje;
            enviarMensaje($apiURL, $chat_id, "📅 Ingresa la fecha del viaje (YYYY-MM-DD):");
            $estado["paso"] = "fecha";
            break;

        case "fecha":
            $estado["fecha"] = $mensaje;
            enviarMensaje($apiURL, $chat_id, "🚖 Ingresa la ruta:");
            $estado["paso"] = "ruta";
            break;

        case "ruta":
            $estado["ruta"] = $mensaje;
            enviarMensaje($apiURL, $chat_id, "🚙 Tipo de vehículo:", [
                "inline_keyboard" => [
                    [
                        ["text" => "Burbuja", "callback_data" => "vehiculo_burbuja"],
                        ["text" => "Camioneta", "callback_data" => "vehiculo_camioneta"]
                    ]
                ]
            ]);
            $estado["paso"] = "vehiculo";
            break;
    }
    file_put_contents($estadoFile, json_encode($estado));
}

// === Botones inline ===
elseif ($callback_query) {
    if (strpos($callback_query, "vehiculo_") === 0) {
        $tipoVehiculo = ucfirst(str_replace("vehiculo_", "", $callback_query));
        $estado["tipo_vehiculo"] = $tipoVehiculo;

        // Insertar en BD
        $sql = "INSERT INTO viajes (nombre, cedula, fecha, ruta, tipo_vehiculo) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("sssss", $estado["nombre"], $estado["cedula"], $estado["fecha"], $estado["ruta"], $estado["tipo_vehiculo"]);
        $stmt->execute();

        enviarMensaje($apiURL, $chat_id, "✅ Viaje registrado:\n👤 {$estado['nombre']}\n🪪 {$estado['cedula']}\n📅 {$estado['fecha']}\n📍 {$estado['ruta']}\n🚙 {$estado['tipo_vehiculo']}");
        $estado = [];
    }
    file_put_contents($estadoFile, json_encode($estado));
    file_get_contents($apiURL."answerCallbackQuery?callback_query_id=".$update["callback_query"]["id"]);
}

// === Respuesta por defecto ===
else {
    if ($chat_id) {
        enviarMensaje($apiURL, $chat_id, "⚠️ No entendí ese mensaje.\n\nUsa:\n/agg para registrar un viaje\n/misviajes para ver tus viajes");
    }
}
