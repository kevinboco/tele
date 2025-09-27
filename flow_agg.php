<?php
// flow_agg.php
require_once __DIR__.'/helpers.php';

function agg_entrypoint($chat_id, $estado) {
    // Si ya estás en agg, reenvía paso actual
    if (!empty($estado) && ($estado['flujo'] ?? '') === 'agg') {
        return agg_resend_current_step($chat_id, $estado);
    }
    // Nuevo flujo
    $conn = db();
    if ($conn) {
        $chat_id_int = (int)$chat_id;
        $res = $conn->query("SELECT * FROM conductores WHERE chat_id='$chat_id_int' LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $conductor = $res->fetch_assoc();
            $estado = [
                "flujo" => "agg",
                "paso"  => "fecha",
                "conductor_id" => $conductor["id"],
                "nombre"  => $conductor["nombre"],
                "cedula"  => $conductor["cedula"],
                "vehiculo"=> $conductor["vehiculo"]
            ];
            saveState($chat_id, $estado);
            sendMessage($chat_id, "📅 Selecciona la fecha del viaje:", kbFechaAgg());
        } else {
            $estado = ["flujo"=>"agg","paso"=>"nombre"];
            saveState($chat_id, $estado);
            sendMessage($chat_id, "✍️ Ingresa tu *nombre* para registrarte:");
        }
        $conn->close();
    } else {
        sendMessage($chat_id, "❌ Error de conexión a la base de datos.");
    }
}

function agg_resend_current_step($chat_id, $estado) {
    switch ($estado['paso'] ?? '') {
        case 'fecha': sendMessage($chat_id, "📅 Ya estás en este paso: selecciona la fecha del viaje:", kbFechaAgg()); break;
        case 'anio':  sendMessage($chat_id, "✍️ Ingresa el *año* del viaje (ejemplo: 2025):"); break;
        case 'mes':   sendMessage($chat_id, "📅 Ingresa el *mes* (01 a 12):"); break;
        case 'dia':   sendMessage($chat_id, "📅 Ingresa el *día*:"); break;
        case 'ruta':  sendMessage($chat_id, "🛣️ Selecciona o crea la ruta:"); break;
        case 'nueva_ruta_salida': sendMessage($chat_id, "📍 Ingresa el *punto de salida* de la nueva ruta:"); break;
        case 'nueva_ruta_destino':sendMessage($chat_id, "🏁 Ingresa el *destino* de la ruta:"); break;
        case 'nueva_ruta_tipo':
            $opts=["inline_keyboard"=>[
                [["text"=>"➡️ Solo ida","callback_data"=>"tipo_ida"]],
                [["text"=>"↔️ Ida y vuelta","callback_data"=>"tipo_idavuelta"]],
            ]];
            sendMessage($chat_id, "🚦 Selecciona el *tipo de viaje*:", $opts);
            break;
        case 'foto': sendMessage($chat_id, "📸 Envía la *foto* del viaje:"); break;
        default: sendMessage($chat_id, "Continuamos donde ibas. Escribe /cancel para reiniciar.");
    }
}

function agg_handle_callback($chat_id, &$estado, $cb_data, $cb_id=null) {
    if (($estado["flujo"] ?? "") !== "agg") return;

    if ($cb_data == "fecha_hoy") {
        $estado["fecha"] = date("Y-m-d");
        $estado["paso"]  = "ruta";
        $conn = db();
        $rutas = $conn ? obtenerRutasUsuario($conn, $estado["conductor_id"]) : [];
        $kb = ["inline_keyboard"=>[]];
        foreach ($rutas as $ruta) $kb["inline_keyboard"][] = [[ "text"=>$ruta, "callback_data"=>"ruta_" . $ruta ]];
        $kb["inline_keyboard"][] = [["text"=>"➕ Nueva ruta", "callback_data"=>"ruta_nueva"]];
        sendMessage($chat_id, "🛣️ Selecciona la ruta:", $kb);
        $conn?->close();
    } elseif ($cb_data == "fecha_manual") {
        $estado["paso"] = "anio";
        sendMessage($chat_id, "✍️ Ingresa el *año* del viaje (ejemplo: 2025):");
    } elseif (strpos($cb_data, "ruta_") === 0) {
        $ruta = substr($cb_data, 5);
        if ($ruta == "nueva") {
            $estado["paso"] = "nueva_ruta_salida";
            sendMessage($chat_id, "📍 Ingresa el *punto de salida* de la nueva ruta:");
        } else {
            $estado["ruta"] = $ruta;
            $estado["paso"] = "foto";
            sendMessage($chat_id, "📸 Envía la *foto* del viaje:");
        }
    } elseif ($cb_data == "tipo_ida" || $cb_data == "tipo_idavuelta") {
        $tipo = ($cb_data == "tipo_ida") ? "Solo ida" : "Ida y vuelta";
        $estado["ruta"] = $estado["salida"] . " - " . $estado["destino"] . " (" . $tipo . ")";
        $conn = db();
        if ($conn) {
            $stmt = $conn->prepare("INSERT INTO rutas (conductor_id, ruta) VALUES (?, ?)");
            $stmt->bind_param("is", $estado["conductor_id"], $estado["ruta"]);
            $stmt->execute(); $stmt->close(); $conn->close();
        }
        $estado["paso"] = "foto";
        sendMessage($chat_id, "✅ Ruta guardada: *{$estado['ruta']}*\n\n📸 Ahora envía la *foto* del viaje:");
    }
    if ($cb_id) answerCallbackQuery($cb_id);
}

function agg_handle_text($chat_id, &$estado, $text, $photo) {
    if (($estado["flujo"] ?? "") !== "agg") return;

    switch ($estado["paso"]) {
        case "nombre":
            $estado["nombre"] = $text;
            $estado["paso"]   = "cedula";
            sendMessage($chat_id, "🔢 Ingresa tu *cédula*:");
            break;

        case "cedula":
            $estado["cedula"] = $text;
            $estado["paso"]   = "vehiculo";
            sendMessage($chat_id, "🚐 Ingresa tu *vehículo*:");
            break;

        case "vehiculo":
            $estado["vehiculo"] = $text;
            $conn = db();
            if ($conn) {
                $stmt = $conn->prepare("INSERT INTO conductores (chat_id, nombre, cedula, vehiculo) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $chat_id, $estado['nombre'], $estado['cedula'], $estado['vehiculo']);
                $stmt->execute();
                $estado["conductor_id"] = $stmt->insert_id ?: $conn->insert_id;
                $stmt->close(); $conn->close();
                $estado["paso"] = "fecha";
                sendMessage($chat_id, "📅 Selecciona la fecha del viaje:", kbFechaAgg());
            } else {
                sendMessage($chat_id, "❌ Error de conexión a la base de datos.");
            }
            break;

        case "anio":
            if (preg_match('/^\d{4}$/', $text) && $text >= 2024 && $text <= 2030) {
                $estado["anio"] = $text;
                $estado["paso"] = "mes";
                sendMessage($chat_id, "✅ Año registrado: {$text}\n\nAhora ingresa el *mes* (01 a 12).");
            } else {
                sendMessage($chat_id, "⚠️ El año debe estar entre 2024 y 2030. Intenta de nuevo.");
            }
            break;

        case "mes":
            if (preg_match('/^(0?[1-9]|1[0-2])$/', $text)) {
                $estado["mes"]  = str_pad($text, 2, "0", STR_PAD_LEFT);
                $estado["paso"] = "dia";
                sendMessage($chat_id, "✅ Mes registrado: {$estado['mes']}\n\nAhora ingresa el *día*.");
            } else {
                sendMessage($chat_id, "⚠️ El mes debe estar entre 01 y 12. Intenta de nuevo.");
            }
            break;

        case "dia":
            $anio=(int)$estado["anio"]; $mes=(int)$estado["mes"];
            $maxDias = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
            if (preg_match('/^\d{1,2}$/', $text) && (int)$text >= 1 && (int)$text <= $maxDias) {
                $estado["dia"]   = str_pad($text, 2, "0", STR_PAD_LEFT);
                $estado["fecha"] = "{$estado['anio']}-{$estado['mes']}-{$estado['dia']}";
                $estado["paso"]  = "ruta";

                $conn = db();
                $rutas = $conn ? obtenerRutasUsuario($conn, $estado["conductor_id"]) : [];
                $kb=["inline_keyboard"=>[]];
                foreach ($rutas as $ruta) $kb["inline_keyboard"][] = [[ "text"=>$ruta, "callback_data"=>"ruta_" . $ruta ]];
                $kb["inline_keyboard"][] = [["text"=>"➕ Nueva ruta","callback_data"=>"ruta_nueva"]];
                sendMessage($chat_id, "🛣️ Selecciona la ruta:", $kb);
                $conn?->close();
            } else {
                sendMessage($chat_id, "⚠️ Día inválido. Debe estar entre 1 y $maxDias. Intenta de nuevo.");
            }
            break;

        case "nueva_ruta_salida":
            $estado["salida"] = $text;
            $estado["paso"]   = "nueva_ruta_destino";
            sendMessage($chat_id, "🏁 Ingresa el *destino* de la ruta:");
            break;

        case "nueva_ruta_destino":
            $estado["destino"] = $text;
            $estado["paso"]    = "nueva_ruta_tipo";
            $opts=["inline_keyboard"=>[
                [["text"=>"➡️ Solo ida","callback_data"=>"tipo_ida"]],
                [["text"=>"↔️ Ida y vuelta","callback_data"=>"tipo_idavuelta"]],
            ]];
            sendMessage($chat_id, "🚦 Selecciona el *tipo de viaje*:", $opts);
            break;

        case "foto":
            if (!$photo) { sendMessage($chat_id, "⚠️ Debes enviar una *foto*."); break; }
            global $TOKEN;
            $file_id = $photo[0]["file_id"] ?? end($photo)["file_id"];
            $fileInfo = json_decode(file_get_contents("https://api.telegram.org/bot$TOKEN/getFile?file_id=$file_id"), true);
            $nombreArchivo = null;
            if (isset($fileInfo["result"]["file_path"])) {
                $file_path = $fileInfo["result"]["file_path"];
                $fileUrl   = "https://api.telegram.org/file/bot$TOKEN/$file_path";
                $carpeta = __DIR__ . "/uploads/";
                if (!is_dir($carpeta)) mkdir($carpeta, 0777, true);
                $nombreArchivo = time() . "_" . basename($file_path);
                $rutaCompleta  = $carpeta . $nombreArchivo;
                file_put_contents($rutaCompleta, file_get_contents($fileUrl));
            }
            if ($nombreArchivo) {
                $conn = db();
                if ($conn) {
                    $stmt = $conn->prepare("INSERT INTO viajes (nombre, cedula, fecha, ruta, tipo_vehiculo, empresa, imagen) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssss", $estado['nombre'], $estado['cedula'], $estado['fecha'], $estado['ruta'], $estado['vehiculo'], $estado['empresa'] ?? NULL, $nombreArchivo);
                    if ($stmt->execute()) sendMessage($chat_id, "✅ Viaje registrado con éxito!");
                    else sendMessage($chat_id, "❌ Error al registrar: " . $conn->error);
                    $stmt->close(); $conn->close();
                } else { sendMessage($chat_id, "❌ Error de conexión a la base de datos."); }
            } else {
                sendMessage($chat_id, "❌ Error al guardar la imagen.");
            }
            clearState($chat_id);
            break;

        default:
            sendMessage($chat_id, "❌ Usa */agg* para registrar un viaje asistido. */cancel* para reiniciar.");
            clearState($chat_id);
            break;
    }
}
