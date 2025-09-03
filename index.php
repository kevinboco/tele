<?php
// Mostrar errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Token del bot
$token = "7574806582:AAFKWFTIGy-vrqEpijTV9BClkpHAz0lZ2Yw";
$apiURL = "https://api.telegram.org/bot$token/";

// Recibir datos de Telegram
$update = json_decode(file_get_contents("php://input"), true);

// Guardar log para depuración
file_put_contents("debug.txt", print_r($update, true) . PHP_EOL, FILE_APPEND);

// Extraer datos principales
$chat_id   = $update["message"]["chat"]["id"] ?? ($update["callback_query"]["message"]["chat"]["id"] ?? null);
$text      = $update["message"]["text"] ?? "";
$photo     = $update["message"]["photo"] ?? null;
$caption   = $update["message"]["caption"] ?? "";
$callback  = $update["callback_query"]["data"] ?? null;

// === Manejo de estados ===
$estadoFile = __DIR__ . "/estado_$chat_id.json";
$estado = file_exists($estadoFile) ? json_decode(file_get_contents($estadoFile), true) : [];

// --- Conexión BD ---
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD");
}

// --- Comando /start ---
if ($text == "/start") {
    $mensaje = "👋 Hola! Soy el bot de viajes. Escribe:\n\n📌 /viaje Nombre Cedula Ruta Fecha Vehiculo (con foto)\n📌 /agg para agregar viaje paso a paso";

// --- Comando /agg ---
} elseif ($text == "/agg") {
    // ¿Ya está registrado el conductor?
    $res = $conn->query("SELECT * FROM conductores WHERE chat_id='$chat_id' LIMIT 1");
    if ($res->num_rows == 0) {
        // Nuevo registro
        $estado = ["paso" => "nombre"];
        $mensaje = "✍️ Ingresa tu *nombre*:";
    } else {
        // Ya existe → saltamos directo a fecha
        $estado = ["paso" => "fecha"];
        $keyboard = [
            "inline_keyboard" => [
                [["text" => "📅 Hoy", "callback_data" => "fecha_hoy"]],
                [["text" => "✍️ Otra fecha", "callback_data" => "fecha_manual"]]
            ]
        ];
        file_put_contents($estadoFile, json_encode($estado));
        enviar($chat_id, "📅 Selecciona la fecha del viaje:", $keyboard);
        exit;
    }
    file_put_contents($estadoFile, json_encode($estado));

// === Flujo paso a paso ===
} elseif (!empty($estado)) {
    switch ($estado["paso"]) {
        // Registro de conductor
        case "nombre":
            $estado["nombre"] = $text;
            $estado["paso"] = "cedula";
            $mensaje = "🔢 Ingresa tu *cédula*:";
            break;

        case "cedula":
            $estado["cedula"] = $text;
            $estado["paso"] = "vehiculo";
            $mensaje = "🚐 Ingresa tu *vehículo*:";
            break;

        case "vehiculo":
            $estado["vehiculo"] = $text;
            // Guardar conductor en BD
            $sql = "INSERT INTO conductores (chat_id, nombre, cedula, vehiculo)
                    VALUES ('$chat_id','{$estado['nombre']}','{$estado['cedula']}','{$estado['vehiculo']}')";
            $conn->query($sql);

            $estado = ["paso" => "fecha"];
            $keyboard = [
                "inline_keyboard" => [
                    [["text" => "📅 Hoy", "callback_data" => "fecha_hoy"]],
                    [["text" => "✍️ Otra fecha", "callback_data" => "fecha_manual"]]
                ]
            ];
            file_put_contents($estadoFile, json_encode($estado));
            enviar($chat_id, "✅ Conductor registrado!\n\n📅 Selecciona la fecha del viaje:", $keyboard);
            exit;

        // Selección de fecha
        case "esperando_fecha_manual":
            $estado["fecha"] = $text;
            $estado["paso"] = "ruta";
            $keyboard = rutasTeclado($conn, $chat_id);
            file_put_contents($estadoFile, json_encode($estado));
            enviar($chat_id, "🛣️ Selecciona la *ruta*:", $keyboard);
            exit;

        case "esperando_ruta_manual":
            $estado["ruta"] = $text;
            // Guardar ruta nueva
            $res = $conn->query("SELECT id FROM conductores WHERE chat_id='$chat_id'");
            $row = $res->fetch_assoc();
            $cid = $row["id"];
            $conn->query("INSERT INTO rutas (conductor_id, ruta) VALUES ($cid, '{$estado['ruta']}')");

            $estado["paso"] = "foto";
            $mensaje = "📸 Envía la *foto* del viaje:";
            break;

        case "foto":
            if (!$photo) {
                $mensaje = "⚠️ Debes enviar una *foto*.";
            } else {
                // Procesar imagen
                $file_id = end($photo)["file_id"];
                $fileInfo = file_get_contents("https://api.telegram.org/bot$token/getFile?file_id=$file_id");
                $fileInfo = json_decode($fileInfo, true);

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

                if (!$nombreArchivo) {
                    $mensaje = "❌ Error al guardar la imagen.";
                } else {
                    // Obtener datos del conductor
                    $res = $conn->query("SELECT * FROM conductores WHERE chat_id='$chat_id' LIMIT 1");
                    $c = $res->fetch_assoc();

                    $sql = "INSERT INTO viajes (nombre, cedula, fecha, ruta, tipo_vehiculo, imagen)
                            VALUES ('{$c['nombre']}','{$c['cedula']}','{$estado['fecha']}','{$estado['ruta']}','{$c['vehiculo']}','$nombreArchivo')";
                    if ($conn->query($sql)) {
                        $mensaje = "✅ Viaje registrado con éxito!";
                    } else {
                        $mensaje = "❌ Error BD: ".$conn->error;
                    }
                }
                unlink($estadoFile);
            }
            break;
    }
    file_put_contents($estadoFile, json_encode($estado));

// --- Callbacks (inline buttons) ---
} elseif ($callback) {
    if ($callback == "fecha_hoy") {
        $estado["fecha"] = date("Y-m-d");
        $estado["paso"] = "ruta";
        $keyboard = rutasTeclado($conn, $chat_id);
        file_put_contents($estadoFile, json_encode($estado));
        enviar($chat_id, "🛣️ Selecciona la *ruta*:", $keyboard);
        exit;

    } elseif ($callback == "fecha_manual") {
        $estado["paso"] = "esperando_fecha_manual";
        file_put_contents($estadoFile, json_encode($estado));
        enviar($chat_id, "✍️ Ingresa la fecha manualmente (YYYY-MM-DD):");
        exit;

    } elseif (strpos($callback, "ruta_") === 0) {
        $rutaSel = substr($callback, 5);
        if ($rutaSel == "nueva") {
            $estado["paso"] = "esperando_ruta_manual";
            file_put_contents($estadoFile, json_encode($estado));
            enviar($chat_id, "✍️ Escribe el nombre de la nueva ruta:");
            exit;
        } else {
            $estado["ruta"] = $rutaSel;
            $estado["paso"] = "foto";
            file_put_contents($estadoFile, json_encode($estado));
            enviar($chat_id, "📸 Envía la *foto* del viaje:");
            exit;
        }
    }
}

// --- Enviar respuesta simple ---
if (isset($mensaje)) {
    enviar($chat_id, $mensaje);
}

// === Funciones ===
function enviar($chat_id, $texto, $keyboard = null) {
    global $apiURL;
    $data = ["chat_id" => $chat_id, "text" => $texto, "parse_mode" => "Markdown"];
    if ($keyboard) $data["reply_markup"] = json_encode($keyboard);
    file_get_contents($apiURL."sendMessage?".http_build_query($data));
}

function rutasTeclado($conn, $chat_id) {
    $res = $conn->query("SELECT id FROM conductores WHERE chat_id='$chat_id'");
    $row = $res->fetch_assoc();
    $cid = $row["id"];

    $res = $conn->query("SELECT ruta FROM rutas WHERE conductor_id=$cid");
    $keyboard = ["inline_keyboard" => []];
    while ($r = $res->fetch_assoc()) {
        $keyboard["inline_keyboard"][] = [["text" => $r["ruta"], "callback_data" => "ruta_".$r["ruta"]]];
    }
    $keyboard["inline_keyboard"][] = [["text" => "➕ Nueva ruta", "callback_data" => "ruta_nueva"]];
    return $keyboard;
}
?>
