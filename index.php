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

// Manejo de estados + TTL
$estadoFile = __DIR__ . "/estado_" . ($chat_id ?: "unknown") . ".json";
$estado = file_exists($estadoFile) ? json_decode(file_get_contents($estadoFile), true) : [];
if (!empty($estado) && isset($estado['last_ts']) && (time() - $estado['last_ts'] > 600)) {
    @unlink($estadoFile);
    $estado = [];
    if ($chat_id && !$callback_query) {
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

function obtenerRutasUsuario($conn, $conductor_id) { // usado en /agg
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
    $estado['last_ts'] = time();
    file_put_contents($estadoFile, json_encode($estado, JSON_UNESCAPED_UNICODE), LOCK_EX);
}
function limpiarEstado($estadoFile) {
    if (file_exists($estadoFile)) unlink($estadoFile);
}

// Helper para re-enviar el paso actual del flujo AGG si el usuario repite /agg (NO TOCAR)
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
        case 'anio': enviarMensaje($apiURL, $chat_id, "✍️ Ingresa el *año* del viaje (ejemplo: 2025):"); break;
        case 'mes':  enviarMensaje($apiURL, $chat_id, "📅 Ingresa el *mes* (01 a 12):"); break;
        case 'dia':  enviarMensaje($apiURL, $chat_id, "📅 Ingresa el *día*:"); break;
        case 'ruta': enviarMensaje($apiURL, $chat_id, "🛣️ Selecciona la ruta (o crea una nueva):"); break;
        case 'nueva_ruta_salida': enviarMensaje($apiURL, $chat_id, "📍 Ingresa el *punto de salida* de la nueva ruta:"); break;
        case 'nueva_ruta_destino': enviarMensaje($apiURL, $chat_id, "🏁 Ingresa el *destino* de la ruta:"); break;
        case 'nueva_ruta_tipo':
            $opcionesTipo = [
                "inline_keyboard" => [
                    [ ["text"=>"➡️ Solo ida","callback_data"=>"tipo_ida"] ],
                    [ ["text"=>"↔️ Ida y vuelta","callback_data"=>"tipo_idavuelta"] ],
                ]
            ];
            enviarMensaje($apiURL, $chat_id, "🚦 Selecciona el *tipo de viaje*:", $opcionesTipo);
            break;
        case 'foto': enviarMensaje($apiURL, $chat_id, "📸 Envía la *foto* del viaje:"); break;
        default: enviarMensaje($apiURL, $chat_id, "Continuamos donde ibas. Si quieres cancelar, escribe /cancel.");
    }
}

// ===== Helpers específicos para MANUAL (conductores_admin y rutas_admin) =====
function obtenerConductoresAdmin($conn, $owner_chat_id) {
    $rows = [];
    if (!$conn) return $rows;
    $owner_chat_id = (int)$owner_chat_id;
    $sql = "SELECT id, nombre FROM conductores_admin WHERE owner_chat_id=$owner_chat_id ORDER BY id DESC LIMIT 25";
    if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) $rows[] = $r;
    }
    return $rows;
}
function crearConductorAdmin($conn, $owner_chat_id, $nombre) {
    if (!$conn) return false;
    $owner_chat_id = (int)$owner_chat_id;
    $stmt = $conn->prepare("INSERT IGNORE INTO conductores_admin (owner_chat_id, nombre) VALUES (?, ?)");
    $stmt->bind_param("is", $owner_chat_id, $nombre);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
function obtenerConductorAdminPorId($conn, $id, $owner_chat_id) {
    if (!$conn) return null;
    $id = (int)$id;
    $owner_chat_id = (int)$owner_chat_id;
    $res = $conn->query("SELECT id, nombre FROM conductores_admin WHERE id=$id AND owner_chat_id=$owner_chat_id LIMIT 1");
    if ($res && $res->num_rows) return $res->fetch_assoc();
    return null;
}
function obtenerRutasAdmin($conn, $owner_chat_id) {
    $rows = [];
    if (!$conn) return $rows;
    $owner_chat_id = (int)$owner_chat_id;
    $res = $conn->query("SELECT id, ruta FROM rutas_admin WHERE owner_chat_id=$owner_chat_id ORDER BY id DESC LIMIT 25");
    if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
    return $rows;
}
function crearRutaAdmin($conn, $owner_chat_id, $ruta) {
    if (!$conn) return false;
    $owner_chat_id = (int)$owner_chat_id;
    $stmt = $conn->prepare("INSERT IGNORE INTO rutas_admin (owner_chat_id, ruta) VALUES (?, ?)");
    $stmt->bind_param("is", $owner_chat_id, $ruta);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
function obtenerRutaAdminPorId($conn, $id, $owner_chat_id) {
    if (!$conn) return null;
    $id = (int)$id; $owner_chat_id = (int)$owner_chat_id;
    $res = $conn->query("SELECT id, ruta FROM rutas_admin WHERE id=$id AND owner_chat_id=$owner_chat_id LIMIT 1");
    if ($res && $res->num_rows) return $res->fetch_assoc();
    return null;
}

// ======= Helpers NUEVOS para fecha con botones (solo MANUAL) =======
function kbFechaManual() {
    return [
        "inline_keyboard" => [
            [["text"=>"📅 Hoy","callback_data"=>"mfecha_hoy"]],
            [["text"=>"📆 Otra fecha","callback_data"=>"mfecha_otro"]],
        ]
    ];
}
function kbMeses($anio) {
    $labels = [
        1=>"Enero",2=>"Febrero",3=>"Marzo",4=>"Abril",
        5=>"Mayo",6=>"Junio",7=>"Julio",8=>"Agosto",
        9=>"Septiembre",10=>"Octubre",11=>"Noviembre",12=>"Diciembre"
    ];
    $kb=["inline_keyboard"=>[]];
    for ($i=1;$i<=12;$i+=2) {
        $row=[];
        $row[]=["text"=>$labels[$i]." $anio","callback_data"=>"mmes_".$anio."_".str_pad($i,2,"0",STR_PAD_LEFT)];
        if ($i+1<=12) $row[]=["text"=>$labels[$i+1]." $anio","callback_data"=>"mmes_".$anio."_".str_pad($i+1,2,"0",STR_PAD_LEFT)];
        $kb["inline_keyboard"][]=$row;
    }
    return $kb;
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
// --------- /agg (NO CAMBIADO) ---------
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

// --------- /manual (conductores + rutas + fecha con botones) ---------
if ($text === "/manual") {
    $conn = db();

    // Si ya estás en flujo manual, reenvía el paso actual (sin romper nada)
    if (!empty($estado) && ($estado['flujo'] ?? '') === 'manual') {
        switch ($estado['paso']) {
            case 'manual_menu':
                $conductores = $conn ? obtenerConductoresAdmin($conn, $chat_id) : [];
                if ($conductores) {
                    $kb = ["inline_keyboard" => []];
                    foreach ($conductores as $c) {
                        $kb["inline_keyboard"][] = [[ "text" => $c['nombre'], "callback_data" => "manual_sel_".$c['id'] ]];
                    }
                    $kb["inline_keyboard"][] = [[ "text" => "➕ Nuevo conductor", "callback_data" => "manual_nuevo" ]];
                    enviarMensaje($apiURL, $chat_id, "Elige un *conductor* o crea uno nuevo:", $kb);
                } else {
                    $estado['paso'] = 'manual_nombre_nuevo';
                    enviarMensaje($apiURL, $chat_id, "No tienes conductores guardados.\n✍️ Escribe el *nombre* del nuevo conductor:");
                }
                guardarEstado($estadoFile, $estado);
                $conn?->close();
                exit;

            case 'manual_nombre_nuevo':
                enviarMensaje($apiURL, $chat_id, "✍️ Escribe el *nombre* del nuevo conductor:");
                guardarEstado($estadoFile, $estado);
                $conn?->close();
                exit;

            case 'manual_ruta_menu':
                $rutas = $conn ? obtenerRutasAdmin($conn, $chat_id) : [];
                if ($rutas) {
                    $kb = ["inline_keyboard" => []];
                    foreach ($rutas as $r) {
                        $kb["inline_keyboard"][] = [[ "text" => $r['ruta'], "callback_data" => "manual_ruta_sel_".$r['id'] ]];
                    }
                    $kb["inline_keyboard"][] = [[ "text" => "➕ Nueva ruta", "callback_data" => "manual_ruta_nueva" ]];
                    enviarMensaje($apiURL, $chat_id, "Selecciona una *ruta* o crea una nueva:", $kb);
                } else {
                    $estado['paso'] = 'manual_ruta_nueva_texto';
                    enviarMensaje($apiURL, $chat_id, "No tienes rutas guardadas.\n✍️ Escribe la *ruta del viaje*:");
                }
                guardarEstado($estadoFile, $estado);
                $conn?->close();
                exit;

            case 'manual_ruta_nueva_texto':
                enviarMensaje($apiURL, $chat_id, "✍️ Escribe la *ruta del viaje*:");
                guardarEstado($estadoFile, $estado);
                $conn?->close();
                exit;

            case 'manual_ruta':
                enviarMensaje($apiURL, $chat_id, "🛣️ Ingresa la *ruta del viaje*:");
                guardarEstado($estadoFile, $estado);
                $conn?->close();
                exit;

            case 'manual_fecha':
                // NUEVO: botones Hoy / Otra fecha
                enviarMensaje($apiURL, $chat_id, "📅 Selecciona la *fecha*:", kbFechaManual());
                guardarEstado($estadoFile, $estado);
                $conn?->close();
                exit;

            case 'manual_fecha_mes':
                // Repite selección de mes si se vuelve a entrar
                $anio = $estado["anio"] ?? date("Y");
                enviarMensaje($apiURL, $chat_id, "📆 Selecciona el *mes*:", kbMeses($anio));
                guardarEstado($estadoFile, $estado);
                $conn?->close();
                exit;

            case 'manual_fecha_dia_input':
                // Repite petición de día si ya está en ese paso
                $anio = (int)($estado["anio"] ?? date("Y"));
                $mes  = (int)($estado["mes"]  ?? date("m"));
                $maxDias = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
                enviarMensaje($apiURL, $chat_id, "✍️ Escribe el *día* del mes (1–$maxDias):");
                guardarEstado($estadoFile, $estado);
                $conn?->close();
                exit;
        }
    }

    // Primer ingreso a /manual: mostrar lista o pedir nombre
    $estado = ["flujo" => "manual", "paso" => "manual_menu"];
    $conductores = $conn ? obtenerConductoresAdmin($conn, $chat_id) : [];

    if ($conductores) {
        $kb = ["inline_keyboard" => []];
        foreach ($conductores as $c) {
            $kb["inline_keyboard"][] = [[ "text" => $c['nombre'], "callback_data" => "manual_sel_".$c['id'] ]];
        }
        $kb["inline_keyboard"][] = [[ "text" => "➕ Nuevo conductor", "callback_data" => "manual_nuevo" ]];
        guardarEstado($estadoFile, $estado);
        enviarMensaje($apiURL, $chat_id, "Elige un *conductor* o crea uno nuevo:", $kb);
    } else {
        $estado['paso'] = 'manual_nombre_nuevo';
        guardarEstado($estadoFile, $estado);
        enviarMensaje($apiURL, $chat_id, "No tienes conductores guardados.\n✍️ Escribe el *nombre* del nuevo conductor:");
    }
    $conn?->close();
    exit;
}

// === Manejo de botones inline (comandos /start) ===
if ($callback_query) {
    // Atajos desde /start
    if ($callback_query === "cmd_agg") {
        // Simula /agg (NO MODIFICADO)
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
        // Simula /manual
        $conn = db();
        $estado = ["flujo" => "manual", "paso" => "manual_menu"];
        $conductores = $conn ? obtenerConductoresAdmin($conn, $chat_id) : [];
        if ($conductores) {
            $kb = ["inline_keyboard" => []];
            foreach ($conductores as $c) {
                $kb["inline_keyboard"][] = [[ "text" => $c['nombre'], "callback_data" => "manual_sel_".$c['id'] ]];
            }
            $kb["inline_keyboard"][] = [[ "text" => "➕ Nuevo conductor", "callback_data" => "manual_nuevo" ]];
            guardarEstado($estadoFile, $estado);
            enviarMensaje($apiURL, $chat_id, "Elige un *conductor* o crea uno nuevo:", $kb);
        } else {
            $estado['paso'] = 'manual_nombre_nuevo';
            guardarEstado($estadoFile, $estado);
            enviarMensaje($apiURL, $chat_id, "No tienes conductores guardados.\n✍️ Escribe el *nombre* del nuevo conductor:");
        }
        $conn?->close();
    }
}

// === Manejo de botones inline ya dentro del flujo ===
if ($callback_query && !empty($estado)) {
    // ===== Flujo AGG: fecha/ruta/tipo (NO CAMBIADO) =====
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

    // ===== Callbacks del flujo MANUAL =====
    if (($estado["flujo"] ?? "") === "manual") {
        // Seleccionar conductor existente
        if (strpos($callback_query, 'manual_sel_') === 0) {
            $idSel = (int)substr($callback_query, strlen('manual_sel_'));
            $conn = db();
            $row = obtenerConductorAdminPorId($conn, $idSel, $chat_id);
            $conn?->close();
            if (!$row) {
                enviarMensaje($apiURL, $chat_id, "⚠️ Conductor no encontrado. Vuelve a intentarlo con /manual.");
            } else {
                $estado['manual_nombre'] = $row['nombre'];
                // pasa a menú de rutas
                $estado['paso'] = 'manual_ruta_menu';
                guardarEstado($estadoFile, $estado);

                $conn = db();
                $rutas = $conn ? obtenerRutasAdmin($conn, $chat_id) : [];
                $conn?->close();
                if ($rutas) {
                    $kb = ["inline_keyboard" => []];
                    foreach ($rutas as $r) {
                        $kb["inline_keyboard"][] = [[ "text" => $r['ruta'], "callback_data" => "manual_ruta_sel_".$r['id'] ]];
                    }
                    $kb["inline_keyboard"][] = [[ "text" => "➕ Nueva ruta", "callback_data" => "manual_ruta_nueva" ]];
                    enviarMensaje($apiURL, $chat_id, "👤 Conductor: *{$row['nombre']}*\n\nSelecciona una *ruta* o crea una nueva:", $kb);
                } else {
                    $estado['paso'] = 'manual_ruta_nueva_texto';
                    guardarEstado($estadoFile, $estado);
                    enviarMensaje($apiURL, $chat_id, "👤 Conductor: *{$row['nombre']}*\n\n✍️ Escribe la *ruta del viaje*:");
                }
            }
        }

        // Elegir crear nuevo conductor
        if ($callback_query === 'manual_nuevo') {
            $estado['paso'] = 'manual_nombre_nuevo';
            guardarEstado($estadoFile, $estado);
            enviarMensaje($apiURL, $chat_id, "✍️ Escribe el *nombre* del nuevo conductor:");
        }

        // Seleccionar ruta existente
        if (strpos($callback_query, 'manual_ruta_sel_') === 0) {
            $idRuta = (int)substr($callback_query, strlen('manual_ruta_sel_'));
            $conn = db();
            $r = obtenerRutaAdminPorId($conn, $idRuta, $chat_id);
            $conn?->close();
            if (!$r) {
                enviarMensaje($apiURL, $chat_id, "⚠️ Ruta no encontrada. Vuelve a intentarlo.");
            } else {
                $estado['manual_ruta'] = $r['ruta'];
                $estado['paso'] = 'manual_fecha';
                guardarEstado($estadoFile, $estado);
                // Botones fecha
                enviarMensaje($apiURL, $chat_id, "🛣️ Ruta: *{$r['ruta']}*\n\n📅 Selecciona la *fecha*:", kbFechaManual());
            }
        }

        // Crear nueva ruta (abre input)
        if ($callback_query === 'manual_ruta_nueva') {
            $estado['paso'] = 'manual_ruta_nueva_texto';
            guardarEstado($estadoFile, $estado);
            enviarMensaje($apiURL, $chat_id, "✍️ Escribe la *ruta del viaje*:");
        }

        // ====== Fecha por botones (manual) ======
        if ($callback_query === 'mfecha_hoy') {
            $fecha = date("Y-m-d");
            $estado['manual_fecha'] = $fecha;

            // Insertar viaje manual y cerrar
            $conn = db();
            if (!$conn) {
                enviarMensaje($apiURL, $chat_id, "❌ Error de conexión a la base de datos.");
                limpiarEstado($estadoFile); $estado = [];
            } else {
                $stmt = $conn->prepare("INSERT INTO viajes (nombre, ruta, fecha, cedula, tipo_vehiculo, imagen) VALUES (?, ?, ?, NULL, NULL, NULL)");
                $stmt->bind_param("sss", $estado["manual_nombre"], $estado["manual_ruta"], $estado["manual_fecha"]);
                if ($stmt->execute()) {
                    enviarMensaje($apiURL, $chat_id, "✅ Viaje (manual) registrado:\n👤 " . $estado["manual_nombre"] . "\n🛣️ " . $estado["manual_ruta"] . "\n📅 " . $estado["manual_fecha"]);
                } else {
                    enviarMensaje($apiURL, $chat_id, "❌ Error al guardar el viaje: " . $conn->error);
                }
                $stmt->close(); $conn->close();
                limpiarEstado($estadoFile); $estado = [];
            }
        }

        if ($callback_query === 'mfecha_otro') {
            // Año se establece automático al actual -> seleccionar mes
            $anio = date("Y");
            $estado["anio"] = $anio;
            $estado["paso"] = "manual_fecha_mes";
            guardarEstado($estadoFile, $estado);
            enviarMensaje($apiURL, $chat_id, "📆 Selecciona el *mes* ($anio):", kbMeses($anio));
        }

        if (strpos($callback_query, 'mmes_') === 0) {
            // mmes_YYYY_MM
            $parts = explode('_', $callback_query);
            $anio = $parts[1] ?? date("Y");
            $mes  = $parts[2] ?? date("m");
            $estado["anio"] = $anio;
            $estado["mes"]  = $mes;

            // Pedir DÍA por texto
            $maxDias = cal_days_in_month(CAL_GREGORIAN, (int)$mes, (int)$anio);
            $estado["paso"] = "manual_fecha_dia_input";
            guardarEstado($estadoFile, $estado);
            enviarMensaje($apiURL, $chat_id, "✍️ Escribe el *día* del mes (1–$maxDias):");
        }
    }

    // Responder callback para quitar el "cargando"
    if (isset($update["callback_query"]["id"])) {
        @file_get_contents($apiURL."answerCallbackQuery?callback_query_id=".$update["callback_query"]["id"]);
    }
}

// === Manejo de flujo por TEXTO (un solo switch para ambos flujos) ===
if (!empty($estado) && !$callback_query) {

    switch ($estado["paso"]) {

        // ===== FLUJO AGG (NO CAMBIADO) =====
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
                        enviarMensaje($apiURL, $chat_id, "puedes usar /agg para agregar otro viaje");
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

        // ===== MANUAL (mejorado con conductores, rutas y fecha) =====

        case "manual_nombre": // compat
            $estado["manual_nombre"] = $text;
            $estado["paso"] = "manual_ruta_menu";
            guardarEstado($estadoFile, $estado);
            // mostrar menú de rutas
            $conn = db();
            $rutas = $conn ? obtenerRutasAdmin($conn, $chat_id) : [];
            $conn?->close();
            if ($rutas) {
                $kb = ["inline_keyboard" => []];
                foreach ($rutas as $r) $kb["inline_keyboard"][] = [[ "text"=>$r['ruta'], "callback_data"=>"manual_ruta_sel_".$r['id'] ]];
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nueva ruta", "callback_data"=>"manual_ruta_nueva" ]];
                enviarMensaje($apiURL, $chat_id, "Selecciona una *ruta* o crea una nueva:", $kb);
            } else {
                $estado['paso'] = 'manual_ruta_nueva_texto';
                guardarEstado($estadoFile, $estado);
                enviarMensaje($apiURL, $chat_id, "✍️ Escribe la *ruta del viaje*:");
            }
            break;

        case "manual_nombre_nuevo":
            $nombreNuevo = trim($text);
            if ($nombreNuevo === "") { enviarMensaje($apiURL, $chat_id, "⚠️ El nombre no puede estar vacío. Escribe el *nombre* del nuevo conductor:"); break; }
            $conn = db(); if ($conn) { crearConductorAdmin($conn, $chat_id, $nombreNuevo); $conn->close(); }
            $estado["manual_nombre"] = $nombreNuevo;
            $estado["paso"] = "manual_ruta_menu";
            guardarEstado($estadoFile, $estado);
            // menu rutas
            $conn = db(); $rutas = $conn ? obtenerRutasAdmin($conn, $chat_id) : []; $conn?->close();
            if ($rutas) {
                $kb = ["inline_keyboard" => []];
                foreach ($rutas as $r) $kb["inline_keyboard"][] = [[ "text"=>$r['ruta'], "callback_data"=>"manual_ruta_sel_".$r['id'] ]];
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nueva ruta", "callback_data"=>"manual_ruta_nueva" ]];
                enviarMensaje($apiURL, $chat_id, "👤 Conductor guardado: *{$nombreNuevo}*\n\nSelecciona una *ruta* o crea una nueva:", $kb);
            } else {
                $estado['paso'] = 'manual_ruta_nueva_texto';
                guardarEstado($estadoFile, $estado);
                enviarMensaje($apiURL, $chat_id, "👤 Conductor guardado: *{$nombreNuevo}*\n\n✍️ Escribe la *ruta del viaje*:");
            }
            break;

        case "manual_ruta": // compat
            $rutaTxt = trim($text);
            if ($rutaTxt === "") { enviarMensaje($apiURL, $chat_id, "⚠️ La ruta no puede estar vacía. Escribe la *ruta* o usa el menú:"); break; }
            $conn = db(); if ($conn) { crearRutaAdmin($conn, $chat_id, $rutaTxt); $conn->close(); }
            $estado["manual_ruta"] = $rutaTxt;
            $estado["paso"] = "manual_fecha";
            enviarMensaje($apiURL, $chat_id, "🛣️ Ruta guardada: *{$rutaTxt}*\n\n📅 Selecciona la *fecha*:", kbFechaManual());
            break;

        case "manual_ruta_nueva_texto":
            $rutaTxt = trim($text);
            if ($rutaTxt === "") { enviarMensaje($apiURL, $chat_id, "⚠️ La ruta no puede estar vacía. Escribe la *ruta del viaje*:"); break; }
            $conn = db(); if ($conn) { crearRutaAdmin($conn, $chat_id, $rutaTxt); $conn->close(); }
            $estado["manual_ruta"] = $rutaTxt;
            $estado["paso"] = "manual_fecha";
            enviarMensaje($apiURL, $chat_id, "🛣️ Ruta guardada: *{$rutaTxt}*\n\n📅 Selecciona la *fecha*:", kbFechaManual());
            break;

        // NUEVO: pedir día por texto tras seleccionar mes
        case "manual_fecha_dia_input":
            $anio = (int)($estado["anio"] ?? date("Y"));
            $mes  = (int)($estado["mes"]  ?? date("m"));
            if (!preg_match('/^\d{1,2}$/', $text)) {
                $maxDias = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
                enviarMensaje($apiURL, $chat_id, "⚠️ Debe ser un número entre 1 y $maxDias. Escribe el *día* del mes:");
                break;
            }
            $dia = (int)$text;
            $maxDias = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
            if ($dia < 1 || $dia > $maxDias) {
                enviarMensaje($apiURL, $chat_id, "⚠️ El día debe estar entre 1 y $maxDias. Inténtalo de nuevo:");
                break;
            }

            // construir fecha YYYY-MM-DD
            $fecha = sprintf("%04d-%02d-%02d", $anio, $mes, $dia);
            $estado["manual_fecha"] = $fecha;

            // Insertar viaje manual y cerrar
            $conn = db();
            if (!$conn) {
                enviarMensaje($apiURL, $chat_id, "❌ Error de conexión a la base de datos.");
                limpiarEstado($estadoFile); $estado = [];
                break;
            }
            $stmt = $conn->prepare("INSERT INTO viajes (nombre, ruta, fecha, cedula, tipo_vehiculo, imagen) VALUES (?, ?, ?, NULL, NULL, NULL)");
            $stmt->bind_param("sss", $estado["manual_nombre"], $estado["manual_ruta"], $estado["manual_fecha"]);
            if ($stmt->execute()) {
                enviarMensaje($apiURL, $chat_id, "✅ Viaje (manual) registrado:\n👤 " . $estado["manual_nombre"] . "\n🛣️ " . $estado["manual_ruta"] . "\n📅 " . $estado["manual_fecha"]);
                enviarMensaje($apiURL, $chat_id, "Puedes usar /manual para registrar otro viaje ");
            } else {
                enviarMensaje($apiURL, $chat_id, "❌ Error al guardar el viaje: " . $conn->error);
            }
            $stmt->close(); $conn->close();

            limpiarEstado($estadoFile); $estado = [];
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

// === Callbacks sin estado ===
if ($callback_query && empty($estado)) {
    if (isset($update["callback_query"]["id"])) {
        @file_get_contents($apiURL."answerCallbackQuery?callback_query_id=".$update["callback_query"]["id"]);
    }
}

// === Cualquier otro texto fuera del flujo ===
if ($chat_id && empty($estado) && !$callback_query) {
    enviarMensaje($apiURL, $chat_id, "❌ Debes usar */agg* para agregar un viaje asistido o */manual* para registrar un viaje manual. También */cancel* para reiniciar.");
}
?>
