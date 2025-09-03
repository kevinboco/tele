<?php
// Conexión a la BD
$pdo = new PDO("mysql:host=localhost;dbname=tu_base", "usuario", "clave");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Token del bot
$token = "TU_TOKEN";
$apiURL = "https://api.telegram.org/bot$token/";

// Leer actualización
$update = json_decode(file_get_contents("php://input"), true);

$chat_id = $update["message"]["chat"]["id"] ?? $update["callback_query"]["message"]["chat"]["id"];
$text    = $update["message"]["text"] ?? $update["callback_query"]["data"];

// Función para enviar mensaje
function sendMessage($chat_id, $text, $keyboard = null) {
    global $apiURL;
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }
    file_get_contents($apiURL . "sendMessage?" . http_build_query($data));
}

// Paso 1: iniciar flujo
if ($text == "/agg") {
    sendMessage($chat_id, "✍️ Ingresa tu nombre:");
    file_put_contents("estado_$chat_id.txt", "nombre");
    exit;
}

// Leer estado
$estado = @file_get_contents("estado_$chat_id.txt");

// Paso 2: Guardar conductor
if ($estado == "nombre") {
    $nombre = trim($text);
    file_put_contents("tmp_nombre_$chat_id.txt", $nombre);
    sendMessage($chat_id, "🔢 Ingresa tu cédula:");
    file_put_contents("estado_$chat_id.txt", "cedula");
    exit;
}

if ($estado == "cedula") {
    $cedula = trim($text);
    file_put_contents("tmp_cedula_$chat_id.txt", $cedula);
    sendMessage($chat_id, "🚐 Ingresa tu vehículo:");
    file_put_contents("estado_$chat_id.txt", "vehiculo");
    exit;
}

if ($estado == "vehiculo") {
    $vehiculo = trim($text);

    $nombre = file_get_contents("tmp_nombre_$chat_id.txt");
    $cedula = file_get_contents("tmp_cedula_$chat_id.txt");

    // Insertar conductor si no existe
    $stmt = $pdo->prepare("INSERT IGNORE INTO conductores (nombre, cedula, vehiculo) VALUES (:n, :c, :v)");
    $stmt->execute([':n' => $nombre, ':c' => $cedula, ':v' => $vehiculo]);

    // Recuperar ID del conductor
    $stmt = $pdo->prepare("SELECT id FROM conductores WHERE cedula = :c");
    $stmt->execute([':c' => $cedula]);
    $id_conductor = $stmt->fetchColumn();
    file_put_contents("tmp_conductor_$chat_id.txt", $id_conductor);

    // Pedir fecha
    $keyboard = [
        "keyboard" => [
            [["text" => "📅 Hoy"], ["text" => "📝 Otra fecha"]]
        ],
        "resize_keyboard" => true,
        "one_time_keyboard" => true
    ];
    sendMessage($chat_id, "✅ Conductor registrado!\n\n📅 Selecciona la fecha del viaje:", $keyboard);
    file_put_contents("estado_$chat_id.txt", "fecha");
    exit;
}

// Paso 3: Guardar fecha
if ($estado == "fecha") {
    if ($text == "📅 Hoy") {
        $fecha = date("Y-m-d");
    } else {
        sendMessage($chat_id, "✍️ Ingresa la fecha en formato YYYY-MM-DD:");
        file_put_contents("estado_$chat_id.txt", "fecha_manual");
        exit;
    }
    file_put_contents("tmp_fecha_$chat_id.txt", $fecha);

    // Preguntar por ruta
    $keyboard = [
        "keyboard" => [
            [["text" => "➕ Nueva ruta"]]
        ],
        "resize_keyboard" => true,
        "one_time_keyboard" => true
    ];
    sendMessage($chat_id, "🛣️ Selecciona la ruta:", $keyboard);
    file_put_contents("estado_$chat_id.txt", "ruta");
    exit;
}

if ($estado == "fecha_manual") {
    $fecha = trim($text);
    file_put_contents("tmp_fecha_$chat_id.txt", $fecha);

    // Preguntar por ruta
    $keyboard = [
        "keyboard" => [
            [["text" => "➕ Nueva ruta"]]
        ],
        "resize_keyboard" => true,
        "one_time_keyboard" => true
    ];
    sendMessage($chat_id, "🛣️ Selecciona la ruta:", $keyboard);
    file_put_contents("estado_$chat_id.txt", "ruta");
    exit;
}

// Paso 4: Guardar ruta
if ($estado == "ruta") {
    if ($text == "➕ Nueva ruta") {
        sendMessage($chat_id, "✍️ Escribe el nombre de la nueva ruta:");
        file_put_contents("estado_$chat_id.txt", "nueva_ruta");
        exit;
    }
}

// Insertar nueva ruta
if ($estado == "nueva_ruta") {
    $ruta = trim($text);

    $stmt = $pdo->prepare("INSERT IGNORE INTO rutas (nombre_ruta) VALUES (:r)");
    $stmt->execute([':r' => $ruta]);

    // Recuperar ID de ruta
    $stmt = $pdo->prepare("SELECT id FROM rutas WHERE nombre_ruta = :r");
    $stmt->execute([':r' => $ruta]);
    $id_ruta = $stmt->fetchColumn();

    $id_conductor = file_get_contents("tmp_conductor_$chat_id.txt");
    $fecha = file_get_contents("tmp_fecha_$chat_id.txt");

    // Insertar en viajes
    $stmt = $pdo->prepare("INSERT INTO viajes (id_conductor, id_ruta, fecha) VALUES (:c, :r, :f)");
    $stmt->execute([':c' => $id_conductor, ':r' => $id_ruta, ':f' => $fecha]);

    sendMessage($chat_id, "✅ Viaje registrado correctamente!\n\n🛣️ Ruta: $ruta\n📅 Fecha: $fecha");

    // Limpiar estado
    unlink("estado_$chat_id.txt");
    exit;
}
?>
