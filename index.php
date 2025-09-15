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
$text    = $update["message"]["text"] ?? "";
$photo   = $update["message"]["photo"] ?? null;
$caption = $update["message"]["caption"] ?? "";
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
📌 /viaje Nombre Cedula Ruta Fecha Vehiculo (con foto)
📌 /agg para agregar viaje paso a paso");

// --- Comando /agg ---
} elseif ($text == "/agg") {
    // Verificar si ya está registrado
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
            // Guardar en BD
            $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
            $conn->query("INSERT INTO conductores (chat_id, nombre, cedula, vehiculo) 
                          VALUES ('$chat_id','{$estado['nombre']}','{$estado['cedula']}','{$estado['vehiculo']}')");
            $estado["conductor_id"] = $conn->insert_id;
            $estado["paso"] = "fecha";
            file_put_contents($estadoFile, json_encode($estado));
            // Botones fecha
            $opcionesFecha = [
                "inline_keyboard" => [
                    [ ["text" => "📅 Hoy", "callback_data" => "fecha_hoy"] ],
                    [ ["text" => "✍️ Otra fecha", "callback_data" => "fecha_manual"] ]
                ]
            ];
            enviarMensaje($apiURL, $chat_id, "📅 Selecciona la fecha del viaje:", $opcionesFecha);
            break;

        case "fecha_manual":
            $estado["fecha"] = $text;
            $estado["paso"] = "ruta";
            // Mostrar rutas guardadas
            $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
            $rutas = obtenerRutasUsuario($conn, $estado["conductor_id"]);
            $opcionesRutas = ["inline_keyboard" => []];
            foreach ($rutas as $ruta) {
                $opcionesRutas["inline_keyboard"][] = [
                    ["text" => $ruta, "callback_data" => "ruta_" . $ruta]
                ];
            }
            $opcionesRutas["inline_keyboard"][] = [["text" => "➕ Nueva ruta", "callback_data" => "ruta_nueva"]];
            enviarMensaje($apiURL, $chat_id, "🛣️ Selecciona la ruta:", $opcionesRutas);
            break;

        case "nueva_ruta":
            $estado["ruta"] = $text;
            $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
            $conn->query("INSERT INTO rutas (conductor_id, ruta) VALUES ('{$estado['conductor_id']}','{$estado['ruta']}')");
            $estado["paso"] = "foto";
            enviarMensaje($apiURL, $chat_id, "📸 Envía la *foto* del viaje:");
            break;

        case "foto":
            if (!$photo) {
                enviarMensaje($apiURL, $chat_id, "⚠️ Debes enviar una *foto*.");
            } else {
                // Procesar foto
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
                    $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
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
                unlink($estadoFile);
            }
            break;
    }
    file_put_contents($estadoFile, json_encode($estado));
}

// === Manejo de botones inline (callback_query) ===
elseif ($callback_query) {
    $chat_id = $callback_chat;

    if ($callback_query == "fecha_hoy") {
        $estado["fecha"] = date("Y-m-d");
        $estado["paso"] = "ruta";

        // Mostrar rutas guardadas
        $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
        $rutas = obtenerRutasUsuario($conn, $estado["conductor_id"]);
        $opcionesRutas = ["inline_keyboard" => []];
        foreach ($rutas as $ruta) {
            $opcionesRutas["inline_keyboard"][] = [
                ["text" => $ruta, "callback_data" => "ruta_" . $ruta]
            ];
        }
        $opcionesRutas["inline_keyboard"][] = [["text" => "➕ Nueva ruta", "callback_data" => "ruta_nueva"]];
        enviarMensaje($apiURL, $chat_id, "🛣️ Selecciona la ruta:", $opcionesRutas);

    } elseif ($callback_query == "fecha_manual") {
        $estado["paso"] = "fecha_manual";
        enviarMensaje($apiURL, $chat_id, "✍️ Escribe la fecha en formato año-mes-dia:");

    } elseif (strpos($callback_query, "ruta_") === 0) {
        $ruta = substr($callback_query, 5);
        if ($ruta == "nueva") {
            $estado["paso"] = "nueva_ruta";
            enviarMensaje($apiURL, $chat_id, "✍️ Escribe el nombre de la nueva ruta:");
        } else {
            $estado["ruta"] = $ruta;
            $estado["paso"] = "foto";
            enviarMensaje($apiURL, $chat_id, "📸 Envía la *foto* del viaje:");
        }
    }

    file_put_contents($estadoFile, json_encode($estado));

    // Siempre responder callback para quitar el "cargando"
    file_get_contents($apiURL."answerCallbackQuery?callback_query_id=".$update["callback_query"]["id"]);
}


else {
    if ($chat_id) {
        enviarMensaje($apiURL, $chat_id, "❓ No te entendí. Usa /start para ver comandos.");
    }
}
?>
