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
📌 /manual para registrar viaje rápido (nombre, ruta, fecha)
📌 /mis_viajes para ver tus viajes (fecha y ruta)");
    exit;
}

// === NUEVO: flujo /manual ===
if ($text == "/manual") {
    $estado = ["paso" => "manual_nombre"];
    file_put_contents($estadoFile, json_encode($estado));
    enviarMensaje($apiURL, $chat_id, "✍️ Ingresa el *nombre del conductor*:");
    exit;
}

// === NUEVO: /mis_viajes (solo fecha y ruta; últimos 10) ===
if ($text == "/mis_viajes") {
    if (!$chat_id) { exit; }

    $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
    if ($conn->connect_error) {
        enviarMensaje($apiURL, $chat_id, "❌ No se pudo conectar a la base de datos.");
        exit;
    }

    $cedula = null;
    if ($stmt = $conn->prepare("SELECT cedula FROM conductores WHERE chat_id=?")) {
        $stmt->bind_param("s", $chat_id);
        $stmt->execute();
        $stmt->bind_result($cedula);
        $stmt->fetch();
        $stmt->close();
    }

    if (!$cedula) {
        enviarMensaje($apiURL, $chat_id, "⚠️ Aún no estás registrado. Usa /agg para registrarte y cargar tu primer viaje.");
        $conn->close();
        exit;
    }

    if ($stmt = $conn->prepare("
        SELECT fecha, ruta
        FROM viajes
        WHERE cedula = ?
          AND fecha IS NOT NULL
          AND fecha <> '0000-00-00'
        ORDER BY fecha DESC, id DESC
        LIMIT 10
    ")) {
        $stmt->bind_param("s", $cedula);
        $stmt->execute();
        $res = $stmt->get_result();

        $lineas = [];
        while ($row = $res->fetch_assoc()) {
            $f = $row['fecha'];
            $r = $row['ruta'] ?: "(sin ruta)";
            $lineas[] = "• *{$f}* — {$r}";
        }
        $stmt->close();
    }
    $conn->close();

    if (empty($lineas)) {
        enviarMensaje($apiURL, $chat_id, "📭 *No tienes viajes registrados con fecha válida.*\nUsa /agg para agregar uno nuevo.");
    } else {
        $txt = "🧾 *Tus viajes (últimos 10)*\n\n" . implode("\n", $lineas);
        enviarMensaje($apiURL, $chat_id, $txt);
    }
    exit;
}

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

// === Manejo de flujo paso a paso (texto) ===
elseif (!empty($estado) && !$callback_query) {
    switch ($estado["paso"]) {
        // --- FLUJO MANUAL ---
        case "manual_nombre":
            $estado["manual_nombre"] = $text;
            $estado["paso"] = "manual_ruta";
            enviarMensaje($apiURL, $chat_id, "🛣️ Ingresa la *ruta del viaje*:");
            break;

        case "manual_ruta":
            $estado["manual_ruta"] = $text;
            $estado["paso"] = "manual_fecha";
            enviarMensaje($apiURL, $chat_id, "📅 Ingresa la *fecha del viaje* (AAAA-MM-DD):");
            break;

        case "manual_fecha":
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
                enviarMensaje($apiURL, $chat_id, "⚠️ La fecha debe estar en formato AAAA-MM-DD. Ejemplo: 2025-09-17");
                break;
            }
            $estado["manual_fecha"] = $text;

            $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
            if ($conn->connect_error) {
                enviarMensaje($apiURL, $chat_id, "❌ Error de conexión a la base de datos.");
                exit;
            }

            $nombre = $conn->real_escape_string($estado["manual_nombre"]);
            $ruta   = $conn->real_escape_string($estado["manual_ruta"]);
            $fecha  = $conn->real_escape_string($estado["manual_fecha"]);

            $sql = "INSERT INTO viajes (nombre, ruta, fecha, cedula, tipo_vehiculo, imagen) 
                    VALUES ('$nombre', '$ruta', '$fecha', NULL, NULL, NULL)";

            if ($conn->query($sql) === TRUE) {
                enviarMensaje($apiURL, $chat_id, "✅ Viaje (manual) registrado:\n👤 $nombre\n🛣️ $ruta\n📅 $fecha");
            } else {
                enviarMensaje($apiURL, $chat_id, "❌ Error al guardar el viaje: " . $conn->error);
            }

            $conn->close();
            if (file_exists($estadoFile)) unlink($estadoFile);
            $estado = [];
            break;

        // --- FLUJO ORIGINAL (/agg) ---
        case "nombre":
            $estado["nombre"] = $text;
            $estado["paso"] = "cedula";
            enviarMensaje($apiURL, $chat_id, "🔢 Ingresa tu *cédula*:");
            break;

        case "cedula":
            $estado["cedula"] = $text;
            $estado["paso"] = "vehiculo";
            enviarMensaje($apiURL, $chat_id, "🚐 Ingresa tu *vehículo*:");
            break;

        case "vehiculo":
            $estado["vehiculo"] = $text;
            $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
            $conn->query("INSERT INTO conductores (chat_id, nombre, cedula, vehiculo) 
                          VALUES ('$chat_id','{$estado['nombre']}','{$estado['cedula']}','{$estado['vehiculo']}')");
            $estado["conductor_id"] = $conn->insert_id;
            $estado["paso"] = "fecha";
            file_put_contents($estadoFile, json_encode($estado));
            $opcionesFecha = [
                "inline_keyboard" => [
                    [ ["text" => "📅 Hoy", "callback_data" => "fecha_hoy"] ],
                    [ ["text" => "✍️ Otra fecha", "callback_data" => "fecha_manual"] ]
                ]
            ];
            enviarMensaje($apiURL, $chat_id, "📅 Selecciona la fecha del viaje:", $opcionesFecha);
            break;

        // ... resto de pasos de tu flujo original (anio, mes, dia, rutas, foto, etc.) ...
    }
    file_put_contents($estadoFile, json_encode($estado));
}

// === Manejo de botones inline (callback_query) ===
elseif ($callback_query) {
    $chat_id = $callback_chat;

    if ($callback_query == "fecha_hoy") {
        $estado["fecha"] = date("Y-m-d");
        $estado["paso"] = "ruta";
        $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
        $rutas = obtenerRutasUsuario($conn, $estado["conductor_id"]);
        $opcionesRutas = ["inline_keyboard" => []];
        foreach ($rutas as $ruta) {
            $opcionesRutas["inline_keyboard"][] = [["text" => $ruta, "callback_data" => "ruta_" . $ruta]];
        }
        $opcionesRutas["inline_keyboard"][] = [["text" => "➕ Nueva ruta", "callback_data" => "ruta_nueva"]];
        enviarMensaje($apiURL, $chat_id, "🛣️ Selecciona la ruta:", $opcionesRutas);

    } elseif ($callback_query == "fecha_manual") {
        $estado["paso"] = "anio";
        enviarMensaje($apiURL, $chat_id, "✍️ Ingresa el *año* del viaje (ejemplo: 2025):");

    } elseif (strpos($callback_query, "ruta_") === 0) {
        $ruta = substr($callback_query, 5);
        if ($ruta == "nueva") {
            $estado["paso"] = "nueva_ruta_salida";
            enviarMensaje($apiURL, $chat_id, "📍 Ingresa el *punto de salida* de la nueva ruta:");
        } else {
            $estado["ruta"] = $ruta;
            $estado["paso"] = "foto";
            enviarMensaje($apiURL, $chat_id, "📸 Envía la *foto* del viaje:");
        }

    } elseif ($callback_query == "tipo_ida" || $callback_query == "tipo_idavuelta") {
        $tipo = ($callback_query == "tipo_ida") ? "Solo ida" : "Ida y vuelta";
        $estado["ruta"] = $estado["salida"] . " - " . $estado["destino"] . " (" . $tipo . ")";
        $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
        $conn->query("INSERT INTO rutas (conductor_id, ruta) VALUES ('{$estado['conductor_id']}','{$estado['ruta']}')");
        $estado["paso"] = "foto";
        enviarMensaje($apiURL, $chat_id, "✅ Ruta guardada: *{$estado['ruta']}*\n\n📸 Ahora envía la *foto* del viaje:");
    }

    file_put_contents($estadoFile, json_encode($estado));
    file_get_contents($apiURL."answerCallbackQuery?callback_query_id=".$update["callback_query"]["id"]);
}

// === Cualquier otro texto fuera del flujo ===
else {
    if ($chat_id) {
        enviarMensaje($apiURL, $chat_id, "❌ Debes usar /agg o /manual para agregar un nuevo viaje.");
    }
}
?>
