<?php
// === Configuración inicial ===
error_reporting(E_ALL);
ini_set('display_errors', 1);

$token = "7574806582:AAFKWFTIGy-vrqEpijTV9BClkpHAz0lZ2Yw";
$apiURL = "https://api.telegram.org/bot$token/";

// Recibir update de Telegram
$raw = file_get_contents("php://input");
$update = json_decode($raw, true) ?: [];

// Log de debug
file_put_contents("debug.txt", date('Y-m-d H:i:s') . " " . print_r($update, true) . PHP_EOL, FILE_APPEND);

// Variables básicas
$chat_id        = $update["message"]["chat"]["id"] ?? ($update["callback_query"]["message"]["chat"]["id"] ?? null);
$text           = trim($update["message"]["text"] ?? "");
$photo          = $update["message"]["photo"] ?? null;
$callback_query = $update["callback_query"]["data"] ?? null;

// ===== Candado por chat (mutex) para evitar flujos simultáneos =====
$lock = null;
if ($chat_id) {
    $lockFile = __DIR__ . "/lock_" . $chat_id . ".lock";
    $lock = fopen($lockFile, 'c'); // crea si no existe
    if ($lock && !flock($lock, LOCK_EX | LOCK_NB)) {
        // Otro request del mismo chat en curso → salir rápido
        file_put_contents("debug.txt", "[LOCK] Chat $chat_id ocupado\n", FILE_APPEND);
        exit;
    }
    // Liberar candado al terminar
    register_shutdown_function(function() use ($lock) {
        if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
    });
}

// ===== Deduplicación por update_id (evita reprocesar reintentos) =====
$update_id = $update['update_id'] ?? null;
if ($chat_id && $update_id !== null) {
    $uidFile = __DIR__ . "/last_update_" . $chat_id . ".txt";
    $last = is_file($uidFile) ? (int)file_get_contents($uidFile) : -1;
    if ($update_id <= $last) {
        // Ya procesado o viejo
        exit;
    }
    file_put_contents($uidFile, (string)$update_id, LOCK_EX);
}

// Manejo de estados
$estadoFile = __DIR__ . "/estado_" . ($chat_id ?: "unknown") . ".json";
$estado = file_exists($estadoFile) ? json_decode(file_get_contents($estadoFile), true) : [];

// ===== TTL de estado (expira tras 10 minutos de inactividad) =====
if (!empty($estado) && isset($estado['last_ts']) && (time() - $estado['last_ts'] > 600)) {
    @unlink($estadoFile);
    $estado = [];
    if ($chat_id && !$callback_query) {
        // Aviso opcional de expiración
        // (No hacemos exit para permitir que el mensaje actual inicie algo nuevo)
        @file_put_contents("debug.txt", "[TTL] Estado expirado para $chat_id\n", FILE_APPEND);
    }
}

// === Helpers ===
function enviarMensaje($apiURL, $chat_id, $mensaje, $opciones = null) {
    if (!$chat_id) return;
    $data = [
        "chat_id" => $chat_id,
        "text" => $mensaje,
        "parse_mode" => "Markdown"
    ];
    if ($opciones) $data["reply_markup"] = json_encode($opciones);
    @file_get_contents($apiURL . "sendMessage?" . http_build_query($data));
}

function db() {
    $mysqli = @new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
    return $mysqli->connect_error ? null : $mysqli;
}

function obtenerRutasUsuario($conn, $conductor_id) {
    $rutas = [];
    if (!$conn) return $rutas;
    $conductor_id = (int)$conductor_id;
    $sql = "SELECT ruta FROM rutas WHERE conductor_id=$conductor_id ORDER BY id DESC";
    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_assoc()) $rutas[] = $row["ruta"];
    }
    return $rutas;
}

function guardarEstado($estadoFile, $estado) {
    $estado['last_ts'] = time(); // marca de tiempo para TTL
    file_put_contents($estadoFile, json_encode($estado, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function limpiarEstado($estadoFile) {
    if (file_exists($estadoFile)) unlink($estadoFile);
}

// Helper para re-enviar el paso actual del flujo AGG si el usuario repite /agg
function reenviarPasoActualAgg($apiURL, $chat_id, $estado) {
    switch ($estado['paso'] ?? '') {
        case 'fecha':
            $opcionesFecha = [
                "inline_keyboard" => [
                    [ ["text"=>"📅 Hoy","callback_data"=>"fecha_hoy"] ],
                    [ ["text"=>"✍️ Otra fecha","callback_data"=>"fecha_manual"] ],
                ]
            ];
            enviarMensaje($apiURL, $chat_id, "📅 Ya estás en este paso: selecciona la fecha del viaje:", $opcionesFecha);
            break;
        case 'anio':
            enviarMensaje($apiURL, $chat_id, "✍️ Ingresa el *año* del viaje (ejemplo: 2025):");
            break;
        case 'mes':
            enviarMensaje($apiURL, $chat_id, "📅 Ingresa el *mes* (01 a 12):");
            break;
        case 'dia':
            enviarMensaje($apiURL, $chat_id, "📅 Ingresa el *día*:");
            break;
        case 'ruta':
            enviarMensaje($apiURL, $chat_id, "🛣️ Selecciona la ruta (o crea una nueva):");
            break;
        case 'nueva_ruta_salida':
            enviarMensaje($apiURL, $chat_id, "📍 Ingresa el *punto de salida* de la nueva ruta:");
            break;
        case 'nueva_ruta_destino':
            enviarMensaje($apiURL, $chat_id, "🏁 Ingresa el *destino* de la ruta:");
            break;
        case 'nueva_ruta_tipo':
            $opcionesTipo = [
                "inline_keyboard" => [
                    [ ["text"=>"➡️ Solo ida","callback_data"=>"tipo_ida"] ],
                    [ ["text"=>"↔️ Ida y vuelta","callback_data"=>"tipo_idavuelta"] ],
                ]
            ];
            enviarMensaje($apiURL, $chat_id, "🚦 Selecciona el *tipo de viaje*:", $opcionesTipo);
            break;
        case 'foto':
            enviarMensaje($apiURL, $chat_id, "📸 Envía la *foto* del viaje:");
            break;
        default:
            enviarMensaje($apiURL, $chat_id, "Continuamos donde ibas. Si quieres cancelar, escribe /cancel.");
    }
}

// === /cancel (o /reset) para limpiar estado y colas locales ===
if ($text === "/cancel" || $text === "/reset") {
    @unlink($estadoFile);
    @unlink(__DIR__ . "/last_update_" . $chat_id . ".txt");
    enviarMensaje($apiURL, $chat_id, "🧹 Listo. Se canceló el flujo y limpié tu estado. Usa /agg o /manual para empezar de nuevo.");
    exit;
}

// === /start ===
if ($text === "/start") {
    $opts = [
        "inline_keyboard" => [
            [ ["text" => "➕ Agregar viaje (asistido)", "callback_data" => "cmd_agg"] ],
            [ ["text" => "📝 Registrar viaje (manual)", "callback_data" => "cmd_manual"] ],
        ]
    ];
    enviarMensaje($apiURL, $chat_id, "👋 ¡Hola! Soy el bot de viajes.\n\n• Usa */agg* para flujo asistido.\n• Usa */manual* para registrar *nombre, ruta y fecha* directamente.\n• Usa */cancel* para reiniciar tu sesión.", $opts);
    exit;
}

// === Comandos directos ===
if ($text === "/agg") {
    // Si ya hay un flujo AGG activo, no inicies otro: reenvía el paso actual
    if (!empty($estado) && ($estado['flujo'] ?? '') === 'agg') {
        reenviarPasoActualAgg($apiURL, $chat_id, $estado);
        guardarEstado($estadoFile, $estado); // refresca TTL
        exit;
    }

    // Verificar si ya está registrado
    $conn = db();
    if ($conn) {
        $chat_id_int = (int)$chat_id;
        $res = $conn->query("SELECT * FROM conductores WHERE chat_id='$chat_id_int' LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $conductor = $res->fetch_assoc();
            $estado = [
                "flujo" => "agg",
                "paso" => "fecha",
                "conductor_id" => $conductor["id"],
                "nombre" => $conductor["nombre"],
                "cedula" => $conductor["cedula"],
                "vehiculo" => $conductor["vehiculo"]
            ];
            guardarEstado($estadoFile, $estado);
            $opcionesFecha = [
                "inline_keyboard" => [
                    [ ["text" => "📅 Hoy", "callback_data" => "fecha_hoy"] ],
                    [ ["text" => "✍️ Otra fecha", "callback_data" => "fecha_manual"] ]
                ]
            ];
            enviarMensaje($apiURL, $chat_id, "📅 Selecciona la fecha del viaje:", $opcionesFecha);
        } else {
            // Registro inicial asistido
            $estado = ["flujo" => "agg", "paso" => "nombre"];
            guardarEstado($estadoFile, $estado);
            enviarMensaje($apiURL, $chat_id, "✍️ Ingresa tu *nombre* para registrarte:");
        }
        $conn?->close();
    } else {
        enviarMensaje($apiURL, $chat_id, "❌ Error de conexión a la base de datos.");
    }
    exit;
}

if ($text === "/manual") {
    // Si ya hay flujo manual activo, continúa en el paso actual (opcional)
    if (!empty($estado) && ($estado['flujo'] ?? '') === 'manual') {
        // Reafirma el paso
        switch ($estado['paso']) {
            case 'manual_nombre':
                enviarMensaje($apiURL, $chat_id, "✍️ Ingresa el *nombre del conductor*:");
                break;
            case 'manual_ruta':
                enviarMensaje($apiURL, $chat_id, "🛣️ Ingresa la *ruta del viaje*:");
                break;
            case 'manual_fecha':
                enviarMensaje($apiURL, $chat_id, "📅 Ingresa la *fecha del viaje* (AAAA-MM-DD):");
                break;
        }
        guardarEstado($estadoFile, $estado);
        exit;
    }

    $estado = ["flujo" => "manual", "paso" => "manual_nombre"];
    guardarEstado($estadoFile, $estado);
    enviarMensaje($apiURL, $chat_id, "✍️ Ingresa el *nombre del conductor*:");
    exit;
}

// === Manejo de botones inline (comandos y flujo) ===
if ($callback_query) {
    // Atajos desde /start
    if ($callback_query === "cmd_agg") {
        // Simula /agg
        if (!empty($estado) && ($estado['flujo'] ?? '') === 'agg') {
            reenviarPasoActualAgg($apiURL, $chat_id, $estado);
            guardarEstado($estadoFile, $estado);
        } else {
            $conn = db();
            if ($conn) {
                $chat_id_int = (int)$chat_id;
                $res = $conn->query("SELECT * FROM conductores WHERE chat_id='$chat_id_int' LIMIT 1");
                if ($res && $res->num_rows > 0) {
                    $conductor = $res->fetch_assoc();
                    $estado = [
                        "flujo" => "agg",
                        "paso" => "fecha",
                        "conductor_id" => $conductor["id"],
                        "nombre" => $conductor["nombre"],
                        "cedula" => $conductor["cedula"],
                        "vehiculo" => $conductor["vehiculo"]
                    ];
                    guardarEstado($estadoFile, $estado);
                    $opcionesFecha = [
                        "inline_keyboard" => [
                            [ ["text" => "📅 Hoy", "callback_data" => "fecha_hoy"] ],
                            [ ["text" => "✍️ Otra fecha", "callback_data" => "fecha_manual"] ]
                        ]
                    ];
                    enviarMensaje($apiURL, $chat_id, "📅 Selecciona la fecha del viaje:", $opcionesFecha);
                } else {
                    $estado = ["flujo" => "agg", "paso" => "nombre"];
                    guardarEstado($estadoFile, $estado);
                    enviarMensaje($apiURL, $chat_id, "✍️ Ingresa tu *nombre* para registrarte:");
                }
                $conn->close();
            } else {
                enviarMensaje($apiURL, $chat_id, "❌ Error de conexión a la base de datos.");
            }
        }
    } elseif ($callback_query === "cmd_manual") {
        if (!empty($estado) && ($estado['flujo'] ?? '') === 'manual') {
            // Reafirma paso
            switch ($estado['paso']) {
                case 'manual_nombre':
                    enviarMensaje($apiURL, $chat_id, "✍️ Ingresa el *nombre del conductor*:");
                    break;
                case 'manual_ruta':
                    enviarMensaje($apiURL, $chat_id, "🛣️ Ingresa la *ruta del viaje*:");
                    break;
                case 'manual_fecha':
                    enviarMensaje($apiURL, $chat_id, "📅 Ingresa la *fecha del viaje* (AAAA-MM-DD):");
                    break;
            }
            guardarEstado($estadoFile, $estado);
        } else {
            $estado = ["flujo" => "manual", "paso" => "manual_nombre"];
            guardarEstado($estadoFile, $estado);
            enviarMensaje($apiURL, $chat_id, "✍️ Ingresa el *nombre del conductor*:");
        }
    }
}

// === Manejo de botones inline ya dentro del flujo ===
if ($callback_query && !empty($estado)) {
    // Flujo AGG: fecha/ruta/tipo
    if (($estado["flujo"] ?? "") === "agg") {
        if ($callback_query == "fecha_hoy") {
            $estado["fecha"] = date("Y-m-d");
            $estado["paso"] = "ruta";
            $conn = db();
            $rutas = $conn ? obtenerRutasUsuario($conn, $estado["conductor_id"]) : [];
            $opcionesRutas = ["inline_keyboard" => []];
            foreach ($rutas as $ruta) {
                $opcionesRutas["inline_keyboard"][] = [ ["text" => $ruta, "callback_data" => "ruta_" . $ruta] ];
            }
            $opcionesRutas["inline_keyboard"][] = [["text" => "➕ Nueva ruta", "callback_data" => "ruta_nueva"]];
            enviarMensaje($apiURL, $chat_id, "🛣️ Selecciona la ruta:", $opcionesRutas);
            $conn?->close();

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
            $conn = db();
            if ($conn) {
                $stmt = $conn->prepare("INSERT INTO rutas (conductor_id, ruta) VALUES (?, ?)");
                $stmt->bind_param("is", $estado["conductor_id"], $estado["ruta"]);
                $stmt->execute();
                $stmt->close();
                $conn->close();
            }
            $estado["paso"] = "foto";
            enviarMensaje($apiURL, $chat_id, "✅ Ruta guardada: *{$estado['ruta']}*\n\n📸 Ahora envía la *foto* del viaje:");
        }
        guardarEstado($estadoFile, $estado);
    }

    // Responder callback para quitar el "cargando"
    if (isset($update["callback_query"]["id"])) {
        @file_get_contents($apiURL."answerCallbackQuery?callback_query_id=".$update["callback_query"]["id"]);
    }
}

// === Manejo de flujo por TEXTO (un solo switch para ambos flujos) ===
if (!empty($estado) && !$callback_query) {

    switch ($estado["paso"]) {

        // ===== FLUJO AGG =====
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
            $conn = db();
            if ($conn) {
                $stmt = $conn->prepare("INSERT INTO conductores (chat_id, nombre, cedula, vehiculo) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $chat_id, $estado['nombre'], $estado['cedula'], $estado['vehiculo']);
                $stmt->execute();
                $estado["conductor_id"] = $stmt->insert_id ?: $conn->insert_id;
                $stmt->close();
                $conn->close();

                $estado["paso"] = "fecha";
                $opcionesFecha = [
                    "inline_keyboard" => [
                        [ ["text" => "📅 Hoy", "callback_data" => "fecha_hoy"] ],
                        [ ["text" => "✍️ Otra fecha", "callback_data" => "fecha_manual"] ]
                    ]
                ];
                enviarMensaje($apiURL, $chat_id, "📅 Selecciona la fecha del viaje:", $opcionesFecha);
            } else {
                enviarMensaje($apiURL, $chat_id, "❌ Error de conexión a la base de datos.");
            }
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
            $anio = (int)$estado["anio"];
            $mes  = (int)$estado["mes"];
            $maxDias = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
            if (preg_match('/^\d{1,2}$/', $text) && (int)$text >= 1 && (int)$text <= $maxDias) {
                $estado["dia"] = str_pad($text, 2, "0", STR_PAD_LEFT);
                $estado["fecha"] = "{$estado['anio']}-{$estado['mes']}-{$estado['dia']}";
                $estado["paso"] = "ruta";

                $conn = db();
                $rutas = $conn ? obtenerRutasUsuario($conn, $estado["conductor_id"]) : [];
                $opcionesRutas = ["inline_keyboard" => []];
                foreach ($rutas as $ruta) {
                    $opcionesRutas["inline_keyboard"][] = [ ["text" => $ruta, "callback_data" => "ruta_" . $ruta] ];
                }
                $opcionesRutas["inline_keyboard"][] = [["text" => "➕ Nueva ruta", "callback_data" => "ruta_nueva"]];
                enviarMensaje($apiURL, $chat_id, "🛣️ Selecciona la ruta:", $opcionesRutas);
                $conn?->close();

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
                break;
            }
            // Descargar y guardar la foto (elige el tamaño más pequeño para rapidez)
            $file_id = $photo[0]["file_id"] ?? end($photo)["file_id"];
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
                $conn = db();
                if ($conn) {
                    $stmt = $conn->prepare("INSERT INTO viajes (nombre, cedula, fecha, ruta, tipo_vehiculo, imagen) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssss", $estado['nombre'], $estado['cedula'], $estado['fecha'], $estado['ruta'], $estado['vehiculo'], $nombreArchivo);
                    if ($stmt->execute()) {
                        enviarMensaje($apiURL, $chat_id, "✅ Viaje registrado con éxito!");
                    } else {
                        enviarMensaje($apiURL, $chat_id, "❌ Error al registrar: " . $conn->error);
                    }
                    $stmt->close();
                    $conn->close();
                } else {
                    enviarMensaje($apiURL, $chat_id, "❌ Error de conexión a la base de datos.");
                }
            } else {
                enviarMensaje($apiURL, $chat_id, "❌ Error al guardar la imagen.");
            }

            // Cerrar flujo
            limpiarEstado($estadoFile);
            $estado = [];
            break;

        // ===== FLUJO MANUAL =====
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

            $conn = db();
            if (!$conn) {
                enviarMensaje($apiURL, $chat_id, "❌ Error de conexión a la base de datos.");
                limpiarEstado($estadoFile);
                $estado = [];
                break;
            }

            $stmt = $conn->prepare("INSERT INTO viajes (nombre, ruta, fecha, cedula, tipo_vehiculo, imagen) VALUES (?, ?, ?, NULL, NULL, NULL)");
            $stmt->bind_param("sss", $estado["manual_nombre"], $estado["manual_ruta"], $estado["manual_fecha"]);

            if ($stmt->execute()) {
                enviarMensaje($apiURL, $chat_id, "✅ Viaje (manual) registrado:\n👤 " . $estado["manual_nombre"] . "\n🛣️ " . $estado["manual_ruta"] . "\n📅 " . $estado["manual_fecha"]);
            } else {
                enviarMensaje($apiURL, $chat_id, "❌ Error al guardar el viaje: " . $conn->error);
            }
            $stmt->close();
            $conn->close();

            limpiarEstado($estadoFile);
            $estado = [];
            break;

        default:
            // Sin estado válido: pedir comando
            enviarMensaje($apiURL, $chat_id, "❌ Debes usar */agg* o */manual* para registrar un viaje. Escribe */cancel* para reiniciar si algo quedó colgado.");
            limpiarEstado($estadoFile);
            $estado = [];
            break;
    }

    // Guardar cambios de estado (si sigue vivo)
    if (!empty($estado)) guardarEstado($estadoFile, $estado);
    exit;
}

// === Manejo de callbacks sin estado (por si llegan sueltos) ===
if ($callback_query && empty($estado)) {
    // Quita el "cargando" aunque no tengamos estado
    if (isset($update["callback_query"]["id"])) {
        @file_get_contents($apiURL."answerCallbackQuery?callback_query_id=".$update["callback_query"]["id"]);
    }
}

// === Cualquier otro texto fuera del flujo ===
if ($chat_id && empty($estado) && !$callback_query) {
    enviarMensaje($apiURL, $chat_id, "❌ Debes usar */agg* para agregar un viaje asistido o */manual* para registrar un viaje manual. También */cancel* para reiniciar.");
}
?>
