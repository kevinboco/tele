<?php
// flow_agg.php
require_once __DIR__.'/helpers.php';

/** Punto de entrada de /agg o botón "Agregar viaje (asistido)" */
function agg_entrypoint($chat_id, $estado): void
{
    // Si ya estás en agg, reenvía el paso actual
    if (!empty($estado) && ($estado['flujo'] ?? '') === 'agg') {
        agg_resend_current_step($chat_id, $estado);
        return;
    }

    // Nuevo flujo AGG
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

/** Reenvía el paso actual del flujo AGG */
function agg_resend_current_step($chat_id, $estado): void
{
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

/** Manejo de callbacks (botones) del flujo AGG */
function agg_handle_callback($chat_id, &$estado, string $cb_data, ?string $cb_id=null): void
{
    if (($estado["flujo"] ?? "") !== "agg") return;

    if ($cb_data === "fecha_hoy") {
        $estado["fecha"] = date("Y-m-d");
        $estado["paso"]  = "ruta";
        saveState($chat_id, $estado);

        $conn = db();
        $rutas = $conn ? obtenerRutasUsuario($conn, $estado["conductor_id"]) : [];
        $kb = ["inline_keyboard"=>[]];
        foreach ($rutas as $ruta) $kb["inline_keyboard"][] = [[ "text"=>$ruta, "callback_data"=>"ruta_" . $ruta ]];
        $kb["inline_keyboard"][] = [["text"=>"➕ Nueva ruta", "callback_data"=>"ruta_nueva"]];
        sendMessage($chat_id, "🛣️ Selecciona la ruta:", $kb);
        $conn?->close();

    } elseif ($cb_data === "fecha_manual") {
        $estado["paso"] = "anio";
        saveState($chat_id, $estado);
        sendMessage($chat_id, "✍️ Ingresa el *año* del viaje (ejemplo: 2025):");

    } elseif (strpos($cb_data, "ruta_") === 0) {
        $ruta = substr($cb_data, 5);
        if ($ruta === "nueva") {
            $estado["paso"] = "nueva_ruta_salida";
            saveState($chat_id, $estado);
            sendMessage($chat_id, "📍 Ingresa el *punto de salida* de la nueva ruta:");
        } else {
            $estado["ruta"] = $ruta;
            $estado["paso"] = "foto";
            saveState($chat_id, $estado);
            sendMessage($chat_id, "📸 Envía la *foto* del viaje:");
        }

    } elseif ($cb_data === "tipo_ida" || $cb_data === "tipo_idavuelta") {
        $tipo = ($cb_data === "tipo_ida") ? "Solo ida" : "Ida y vuelta";
        $estado["ruta"] = $estado["salida"] . " - " . $estado["destino"] . " (" . $tipo . ")";
        $conn = db();
        if ($conn) {
            $stmt = $conn->prepare("INSERT INTO rutas (conductor_id, ruta) VALUES (?, ?)");
            $stmt->bind_param("is", $estado["conductor_id"], $estado["ruta"]);
            $stmt->execute(); $stmt->close(); $conn->close();
        }
        $estado["paso"] = "foto";
        saveState($chat_id, $estado);
        sendMessage($chat_id, "✅ Ruta guardada: *{$estado['ruta']}*\n\n📸 Ahora envía la *foto* del viaje:");
    }

    if ($cb_id) answerCallbackQuery($cb_id);
}

/** Manejo de texto/foto del flujo AGG */
function agg_handle_text($chat_id, &$estado, string $text=null, $photo=null): void
{
    if (($estado["flujo"] ?? "") !== "agg") return;

    switch ($estado["paso"]) {
        case "nombre":
            $estado["nombre"] = $text;
            $estado["paso"]   = "cedula";
            saveState($chat_id, $estado);
            sendMessage($chat_id, "🔢 Ingresa tu *cédula*:");
            return;

        case "cedula":
            $estado["cedula"] = $text;
            $estado["paso"]   = "vehiculo";
            saveState($chat_id, $estado);
            sendMessage($chat_id, "🚐 Ingresa tu *vehículo*:");
            return;

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
                saveState($chat_id, $estado);
                sendMessage($chat_id, "📅 Selecciona la fecha del viaje:", kbFechaAgg());
            } else {
                sendMessage($chat_id, "❌ Error de conexión a la base de datos.");
            }
            return;

        case "anio":
            if (preg_match('/^\d{4}$/', $text) && $text >= 2024 && $text <= 2030) {
                $estado["anio"] = $text;
                $estado["paso"] = "mes";
                saveState($chat_id, $estado);
                sendMessage($chat_id, "✅ Año registrado: {$text}\n\nAhora ingresa el *mes* (01 a 12).");
            } else {
                sendMessage($chat_id, "⚠️ El año debe estar entre 2024 y 2030. Intenta de nuevo.");
            }
            return;

        case "mes":
            if (preg_match('/^(0?[1-9]|1[0-2])$/', $text)) {
                $estado["mes"]  = str_pad($text, 2, "0", STR_PAD_LEFT);
                $estado["paso"] = "dia";
                saveState($chat_id, $estado);
                sendMessage($chat_id, "✅ Mes registrado: {$estado['mes']}\n\nAhora ingresa el *día*.");
            } else {
                sendMessage($chat_id, "⚠️ El mes debe estar entre 01 y 12. Intenta de nuevo.");
            }
            return;

        case "dia":
            $anio=(int)$estado["anio"]; $mes=(int)$estado["mes"];
            $maxDias = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
            if (preg_match('/^\d{1,2}$/', $text) && (int)$text >= 1 && (int)$text <= $maxDias) {
                $estado["dia"]   = str_pad($text, 2, "0", STR_PAD_LEFT);
                $estado["fecha"] = "{$estado['anio']}-{$estado['mes']}-{$estado['dia']}";
                $estado["paso"]  = "ruta";
                saveState($chat_id, $estado);

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
            return;

        case "nueva_ruta_salida":
            $estado["salida"] = $text;
            $estado["paso"]   = "nueva_ruta_destino";
            saveState($chat_id, $estado);
            sendMessage($chat_id, "🏁 Ingresa el *destino* de la ruta:");
            return;

        case "nueva_ruta_destino":
            $estado["destino"] = $text;
            $estado["paso"]    = "nueva_ruta_tipo";
            saveState($chat_id, $estado);
            $opts=["inline_keyboard"=>[
                [["text"=>"➡️ Solo ida","callback_data"=>"tipo_ida"]],
                [["text"=>"↔️ Ida y vuelta","callback_data"=>"tipo_idavuelta"]],
            ]];
            sendMessage($chat_id, "🚦 Selecciona el *tipo de viaje*:", $opts);
            return;

        case "foto":
            // Aceptar photo o document con mime image/*
            file_put_contents("debug.txt", "[AGG][{$chat_id}] paso=foto; hasPhoto=".(!empty($photo))."; hasDoc=".(isset($GLOBALS['update']['message']['document'])?'1':'0')."\n", FILE_APPEND);

            $file_id = null;
            if (!empty($photo) && is_array($photo)) {
                $tmp = end($photo);
                if (is_array($tmp) && !empty($tmp['file_id'])) $file_id = $tmp['file_id'];
                reset($photo);
            }
            $doc = $GLOBALS['update']['message']['document'] ?? null;
            if (!$file_id && $doc && isset($doc['mime_type']) && strpos($doc['mime_type'], 'image/') === 0) {
                $file_id = $doc['file_id'];
            }
            if (!$file_id) {
                sendMessage($chat_id, "⚠️ Debes enviar una *foto* (como foto o archivo de imagen).");
                return;
            }

            global $TOKEN;
            $apiGetFile = "https://api.telegram.org/bot{$TOKEN}/getFile?file_id=" . urlencode($file_id);
            $info = @json_decode(@file_get_contents($apiGetFile), true);
            if (!$info || empty($info['ok']) || empty($info['result']['file_path'])) {
                sendMessage($chat_id, "❌ No pude obtener el archivo desde Telegram. Intenta de nuevo.");
                file_put_contents("debug.txt", "[AGG][{$chat_id}] ERROR getFile: ".print_r($info,true)."\n", FILE_APPEND);
                return;
            }

            $file_path = $info['result']['file_path'];
            $fileUrl   = "https://api.telegram.org/file/bot{$TOKEN}/{$file_path}";

            $uploads = __DIR__ . "/uploads/";
            if (!is_dir($uploads)) @mkdir($uploads, 0775, true);
            $nombreArchivo = time() . "_" . basename($file_path);
            $destino       = $uploads . $nombreArchivo;

            $okDownload = false;
            if (function_exists('curl_init')) {
                $ch = curl_init($fileUrl);
                $fp = fopen($destino, 'wb');
                curl_setopt_array($ch, [
                    CURLOPT_FILE => $fp,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 30,
                ]);
                $okDownload = curl_exec($ch);
                $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch); fclose($fp);
                if ($http !== 200) $okDownload = false;
            } else {
                $data = @file_get_contents($fileUrl);
                if ($data !== false) $okDownload = (file_put_contents($destino, $data) !== false);
            }
            if (!$okDownload || !file_exists($destino)) {
                sendMessage($chat_id, "❌ No pude guardar la imagen. Reenvía la foto.");
                file_put_contents("debug.txt", "[AGG][{$chat_id}] ERROR download: {$fileUrl}\n", FILE_APPEND);
                return;
            }

            // Insertar viaje
            $conn = db();
            if (!$conn) {
                sendMessage($chat_id, "❌ Error de conexión a la base de datos.");
                return;
            }
            $empresa = $estado['empresa'] ?? null; // AGG no siempre tiene empresa
            $stmt = $conn->prepare("INSERT INTO viajes (nombre, cedula, fecha, ruta, tipo_vehiculo, empresa, imagen) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                sendMessage($chat_id, "❌ Error interno preparando la inserción.");
                file_put_contents("debug.txt", "[AGG][{$chat_id}] ERROR prepare: ".$conn->error."\n", FILE_APPEND);
                $conn->close();
                return;
            }
            $stmt->bind_param("sssssss",
                $estado['nombre'],
                $estado['cedula'],
                $estado['fecha'],
                $estado['ruta'],
                $estado['vehiculo'],
                $empresa,
                $nombreArchivo
            );
            if ($stmt->execute()) {
                sendMessage($chat_id, "✅ Viaje registrado con éxito. ¡Gracias!");
            } else {
                sendMessage($chat_id, "❌ Error al registrar el viaje: " . $conn->error);
                file_put_contents("debug.txt", "[AGG][{$chat_id}] ERROR execute: ".$conn->error."\n", FILE_APPEND);
            }
            $stmt->close(); $conn->close();

            // Cerrar flujo y SALIR sin que el router vuelva a tocar estado
            clearState($chat_id);
            return;

        default:
            // Si llegó aquí, el estado no coincide con el paso esperado
            sendMessage($chat_id, "❌ Usa */agg* para registrar un viaje asistido. */cancel* para reiniciar.");
            clearState($chat_id);
            return;
    }
}
