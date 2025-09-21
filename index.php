<?php
// === Configuración inicial ===
error_reporting(E_ALL);
ini_set('display_errors', 1);

$token = "7574806582:AAFKWFTIGy-vrqEpijTV9BClkpHAz0lZ2Yw";
$apiURL = "https://api.telegram.org/bot$token/";

// Recibir update de Telegram
$update = json_decode(file_get_contents("php://input"), true);

// Log para depuración
file_put_contents("debug.log", print_r($update, true), FILE_APPEND);

$chat_id = null;
$text = null;

// Extraer información básica del update
if (isset($update["message"])) {
    $chat_id = $update["message"]["chat"]["id"];
    $text = trim($update["message"]["text"] ?? "");
} elseif (isset($update["callback_query"])) {
    $chat_id = $update["callback_query"]["message"]["chat"]["id"];
}

// === Funciones auxiliares ===
function enviarMensaje($apiURL, $chat_id, $texto, $opciones = null) {
    $data = [
        "chat_id" => $chat_id,
        "text" => $texto,
        "parse_mode" => "Markdown"
    ];
    if ($opciones) {
        $data["reply_markup"] = json_encode($opciones);
    }
    file_get_contents($apiURL . "sendMessage?" . http_build_query($data));
}

function guardarEstado($chat_id, $estado) {
    file_put_contents("estado_$chat_id.json", json_encode($estado));
}

function cargarEstado($chat_id) {
    $archivo = "estado_$chat_id.json";
    if (file_exists($archivo)) {
        return json_decode(file_get_contents($archivo), true);
    }
    return null;
}

function obtenerRutasUsuario($conn, $conductor_id) {
    $rutas = [];
    $res = $conn->query("SELECT DISTINCT ruta FROM viajes WHERE conductor_id = $conductor_id");
    while ($fila = $res->fetch_assoc()) {
        $rutas[] = $fila["ruta"];
    }
    return $rutas;
}

// === Procesamiento principal ===
if ($chat_id) {
    $estado = cargarEstado($chat_id) ?? [];

    // Detectar comandos
    if ($text == "/agg") {
        $estado = ["paso" => "registro"];
        guardarEstado($chat_id, $estado);
        enviarMensaje($apiURL, $chat_id, "📝 Ingresa tu *nombre completo*:");
    } elseif ($text == "/manual") {
        $estado = ["paso" => "manual_nombre"];
        guardarEstado($chat_id, $estado);
        enviarMensaje($apiURL, $chat_id, "✍️ Ingresa el *nombre del conductor*:");
    } elseif (isset($update["callback_query"])) {
        $data = $update["callback_query"]["data"];

        if ($data == "fecha_hoy") {
            $estado["fecha"] = date("Y-m-d");
            $estado["paso"] = "ruta";

            $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
            $rutas = obtenerRutasUsuario($conn, $estado["conductor_id"]);
            $opciones = ["inline_keyboard" => []];
            foreach ($rutas as $r) {
                $opciones["inline_keyboard"][] = [["text" => $r, "callback_data" => "ruta_" . $r]];
            }
            $opciones["inline_keyboard"][] = [["text" => "➕ Nueva ruta", "callback_data" => "ruta_nueva"]];
            enviarMensaje($apiURL, $chat_id, "🛣️ Selecciona la ruta:", $opciones);
            $conn->close();
        } elseif ($data == "fecha_otra") {
            $estado["paso"] = "anio";
            enviarMensaje($apiURL, $chat_id, "✍️ Ingresa el *año* del viaje (ejemplo: 2025):");
        } elseif (strpos($data, "ruta_") === 0) {
            $ruta = substr($data, 5);
            $estado["ruta"] = $ruta;
            $estado["paso"] = "foto";
            enviarMensaje($apiURL, $chat_id, "📸 Envía una *foto del viaje*:");
        }
        guardarEstado($chat_id, $estado);
    } else {
        // === Flujo de conversación ===
        switch ($estado["paso"] ?? "") {
            case "registro":
                $estado["nombre"] = $text;
                $estado["conductor_id"] = rand(1000, 9999); // demo
                $estado["paso"] = "fecha";
                $opciones = [
                    "inline_keyboard" => [
                        [["text" => "📅 Hoy", "callback_data" => "fecha_hoy"]],
                        [["text" => "✍️ Otra fecha", "callback_data" => "fecha_otra"]]
                    ]
                ];
                enviarMensaje($apiURL, $chat_id, "📅 Selecciona la fecha del viaje:", $opciones);
                break;

            // === NUEVOS PASOS: Año → Mes → Día ===
            case "anio":
                if (!preg_match('/^\d{4}$/', $text)) {
                    enviarMensaje($apiURL, $chat_id, "⚠️ Ingresa el *año* con 4 dígitos. Ejemplo: 2025");
                    break;
                }
                $estado["anio"] = (int)$text;
                $estado["paso"] = "mes";
                enviarMensaje($apiURL, $chat_id, "✍️ Ingresa el *mes* del viaje (1-12):");
                break;

            case "mes":
                if (!preg_match('/^\d{1,2}$/', $text) || (int)$text < 1 || (int)$text > 12) {
                    enviarMensaje($apiURL, $chat_id, "⚠️ Mes inválido. Ingresa un número entre 1 y 12.");
                    break;
                }
                $estado["mes"] = str_pad((int)$text, 2, "0", STR_PAD_LEFT);
                $estado["paso"] = "dia";
                enviarMensaje($apiURL, $chat_id, "✍️ Ingresa el *día* del viaje (1-31):");
                break;

            case "dia":
                if (!preg_match('/^\d{1,2}$/', $text)) {
                    enviarMensaje($apiURL, $chat_id, "⚠️ Día inválido. Ingresa el día como número.");
                    break;
                }
                $dia = (int)$text;
                $anio = $estado["anio"] ?? null;
                $mes  = $estado["mes"] ?? null;
                if (!$anio || !$mes || !checkdate((int)$mes, $dia, (int)$anio)) {
                    enviarMensaje($apiURL, $chat_id, "⚠️ Fecha inválida. Intenta de nuevo.");
                    break;
                }
                $estado["fecha"] = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
                $estado["paso"] = "ruta";

                $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
                $rutas = obtenerRutasUsuario($conn, $estado["conductor_id"]);
                $opciones = ["inline_keyboard" => []];
                foreach ($rutas as $r) {
                    $opciones["inline_keyboard"][] = [["text" => $r, "callback_data" => "ruta_" . $r]];
                }
                $opciones["inline_keyboard"][] = [["text" => "➕ Nueva ruta", "callback_data" => "ruta_nueva"]];
                enviarMensaje($apiURL, $chat_id, "🛣️ Selecciona la ruta:", $opciones);
                $conn->close();
                break;
        }

        guardarEstado($chat_id, $estado);
    }
}
?>
