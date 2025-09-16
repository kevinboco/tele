<?php
// === Configuración inicial ===
error_reporting(E_ALL);
ini_set('display_errors', 1);

$token = "7574806582:AAAFKWFTIGy-vrqEpijTV9BClkpHAz0lZ2Yw";
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

function abrirDB() {
    return new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
}

// === Manejo de comandos ===
if ($text == "/start") {
    enviarMensaje($apiURL, $chat_id, "👋 Hola! Soy el bot de viajes. 
📌 /agg para agregar viaje paso a paso
📌 /mis_viajes para ver tus viajes (fecha y ruta)");
    exit;
}

// === NUEVO: /mis_viajes (solo fecha y ruta) ===
if ($text == "/mis_viajes") {
    if (!$chat_id) exit;

    $conn = abrirDB();
    if ($conn->connect_error) {
        enviarMensaje($apiURL, $chat_id, "❌ No se pudo conectar a la base de datos.");
        exit;
    }

    // Buscar cédula por chat_id
    $cedula = null;
    $stmt = $conn->prepare("SELECT cedula FROM conductores WHERE chat_id=?");
    $stmt->bind_param("s", $chat_id);
    $stmt->execute();
    $stmt->bind_result($cedula);
    $stmt->fetch();
    $stmt->close();

    if (!$cedula) {
        enviarMensaje($apiURL, $chat_id, "⚠️ Aún no estás registrado. Usa /agg para registrarte y cargar tu primer viaje.");
        $conn->close();
        exit;
    }

    // Últimos 10 viajes válidos
    $stmt = $conn->prepare("
        SELECT fecha, ruta
        FROM viajes
        WHERE cedula = ?
          AND fecha IS NOT NULL
          AND fecha <> '0000-00-00'
        ORDER BY fecha DESC, id DESC
        LIMIT 10
    ");
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
    $conn->close();

    if (empty($lineas)) {
        enviarMensaje($apiURL, $chat_id, "📭 *No tienes viajes registrados con fecha válida.*\nUsa /agg para agregar uno nuevo.");
    } else {
        $txt = "🧾 *Tus viajes (últimos 10)*\n\n" . implode("\n", $lineas) . 
               "\n\n📊 Total viajes: *" . count($lineas) . "*";
        enviarMensaje($apiURL, $chat_id, $txt);
    }
    exit;
}

if ($text == "/agg") {
    // Verificar si ya está registrado
    $conn = abrirDB();
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

        // Botones fecha
        $opcionesFecha = [
            "inline_keyboard" => [
                [ ["text" => "📅 Hoy", "callback_data" => "fecha_hoy"] ],
                [ ["text" => "✍️ Otra fecha", "callback_data" => "fecha_manual"] ]
            ]
        ];
        enviarMensaje($apiURL, $chat_id, "📅 Selecciona la fecha del viaje:", $opcionesFecha);
    } else {
        // Registro inicial
        $estado = ["paso" => "nombre"];
        file_put_contents($estadoFile, json_encode($estado));
        enviarMensaje($apiURL, $chat_id, "✍️ Ingresa tu *nombre* para registrarte:");
    }
    exit;
}

// === Manejo de flujo paso a paso (texto) ===
elseif (!empty($estado) && !$callback_query) {
    switch ($estado["paso"]) {
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
            $conn = abrirDB();
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

        case "anio":
            if (preg_match('/^\d{4}$/', $text) && $text >= 2024 && $text <= 2030) {
                $estado["anio"] = $text;
                $estado["paso"] = "mes";
                enviarMensaje($apiURL, $chat_id, "✅ Año registrado: {$text}\n\nAhora ingresa el *mes* (01 a 12).");
            } else {
                enviarMensaje($apiURL, $chat_id, "⚠️ El año debe estar entre 2024 y 2030. Intenta de nuevo.");
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

                $conn = abrirDB();
                $rutas = obtenerRutasUsuario($conn, $estado["conductor_id"]);
                $opcionesRutas = ["inline_keyboard" => []];
                foreach ($rutas as $ruta) {
                    $opcionesRutas["inline_keyboard"][] = [["text" => $ruta, "callback_data" => "ruta_" . $ruta]];
                }
                $opcionesRutas["inline_keyboard"][] = [["text" => "➕ Nueva ruta", "callback_data" => "ruta_nueva"]];
                enviarMensaje($apiURL, $chat_id, "🛣️ Selecciona la ruta:", $opcionesRutas);
            } else {
                enviarMensaje($apiURL, $chat_id, "⚠️ Día inválido para ese mes. Debe estar entre 1 y $maxDias. Intenta de nuevo.");
            }
            break;

        case "nueva_ruta_salida":
            $estado["salida"] = $text;
            $estado["paso"] = "nueva_ruta_destino";
            enviarMensaje($apiURL, $chat_id, "🏁 Ingresa el *destino* de la ruta:");
            break;

        case "nueva_ruta_destino":
            $estado["destino"] = $text;
            $estado["paso"] = "nueva_ruta_tipo";
            $opcionesTipo = [
                "inline_keyboard" => [
                    [ ["text" => "➡️ Solo ida", "callback_data" => "tipo_ida"] ],
                    [ ["text" => "↔️ Ida y vuelta", "callback_data" => "tipo_idavuelta"] ]
                ]
            ];
            enviarMensaje($apiURL, $chat_id, "🚦 Selecciona el *tipo de viaje*:", $opcionesTipo);
            break;

        case "foto":
            if (!$photo) {
                enviarMensaje($apiURL, $chat_id, "⚠️ Debes enviar una *foto*.");
            } else {
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
                    $conn = abrirDB();
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
            }
            if (file_exists($estadoFile)) unlink($estadoFile);
            $estado = [];
            break;
    }
    file_put_contents($estadoFile, json_encode($estado));
}

// === Manejo de botones inline ===
elseif ($callback_query) {
    $chat_id = $callback_chat;

    if ($callback_query == "fecha_hoy") {
        $estado["fecha"] = date("Y-m-d");
        $estado["paso"] = "ruta";
        $conn = abrirDB();
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
        $conn = abrirDB();
        $conn->query("INSERT INTO rutas (conductor_id, ruta) VALUES ('{$estado['conductor_id']}','{$estado['ruta']}')");
        $estado["paso"] = "foto";
        enviarMensaje($apiURL, $chat_id, "✅ Ruta guardada: *{$estado['ruta']}*\n\n📸 Ahora envía la *foto* del viaje:");
    }

    file_put_contents($estadoFile, json_encode($estado));
    file_get_contents($apiURL."answerCallbackQuery?callback_query_id=".$update["callback_query"]["id"]);
}

// === NUEVO: cualquier otro texto fuera de flujo ===
else {
    if ($chat_id) {
        enviarMensaje($apiURL, $chat_id, "⚠️ Comando no reconocido. 
Usa:\n👉 /agg para agregar un nuevo viaje\n👉 /mis_viajes para ver tus viajes");
    }
}
?>
