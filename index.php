<?php
// === Configuración inicial ===
error_reporting(E_ALL);
ini_set('display_errors', 1);

$token = "7574806582:AAFKWFTIGy-vrqEpijTV9BClkpHAz0lZ2Yw";
$apiURL = "https://api.telegram.org/bot$token/";

// Conexión BD
$host = "mysql.hostinger.com";
$user = "u648222299_keboco5";
$pass = "Bucaramanga3011";
$db   = "u648222299_viajes";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}

// Recibir update de Telegram
$update = json_decode(file_get_contents("php://input"), true);
$chatId = $update["message"]["chat"]["id"] ?? ($update["callback_query"]["message"]["chat"]["id"] ?? null);
$text   = $update["message"]["text"] ?? null;
$callbackData = $update["callback_query"]["data"] ?? null;

// === Funciones auxiliares ===
function sendMessage($chatId, $text, $keyboard = null) {
    global $apiURL;
    $payload = ["chat_id" => $chatId, "text" => $text, "parse_mode" => "HTML"];
    if ($keyboard) {
        $payload["reply_markup"] = json_encode($keyboard);
    }
    file_get_contents($apiURL . "sendMessage?" . http_build_query($payload));
}

// === FLUJOS EXISTENTES (NO MODIFICADOS) ===
// Aquí van todos tus otros flujos (/agg, /ata, etc.)
// 👆 Los dejo intactos porque me pediste no tocarlos.

// ====================================================
// === NUEVO FLUJO /manual ===
// ====================================================
if ($text == "/manual") {
    // Paso 1: seleccionar conductor
    $res = $conn->query("SELECT id, nombre FROM conductores WHERE chat_id='$chatId'");
    $buttons = [];
    while ($row = $res->fetch_assoc()) {
        $buttons[][] = ["text" => $row['nombre'], "callback_data" => "manual_conductor_" . $row['id']];
    }
    $buttons[][] = ["text" => "➕ Nuevo conductor", "callback_data" => "manual_nuevo_conductor"];
    $keyboard = ["inline_keyboard" => $buttons];
    sendMessage($chatId, "Selecciona el conductor:", $keyboard);
}

// === CallbackQuery: flujo manual ===
if ($callbackData) {
    // Selección conductor existente
    if (strpos($callbackData, "manual_conductor_") === 0) {
        $conductorId = str_replace("manual_conductor_", "", $callbackData);
        $conn->query("UPDATE conductores SET chat_id='$chatId' WHERE id=$conductorId"); // asegura chat_id
        $res = $conn->query("SELECT * FROM conductores WHERE id=$conductorId");
        $conductor = $res->fetch_assoc();
        $nombreConductor = $conductor['nombre'];

        // Guardamos en sesión temporal (archivo)
        file_put_contents("manual_$chatId.json", json_encode(["conductor" => $conductor]));

        // Paso 2: seleccionar ruta
        $resR = $conn->query("SELECT id, ruta FROM rutas");
        $buttons = [];
        while ($row = $resR->fetch_assoc()) {
            $buttons[][] = ["text" => $row['ruta'], "callback_data" => "manual_ruta_" . $row['id']];
        }
        $buttons[][] = ["text" => "➕ Nueva ruta", "callback_data" => "manual_nueva_ruta"];
        $keyboard = ["inline_keyboard" => $buttons];
        sendMessage($chatId, "Selecciona la ruta:", $keyboard);
    }

    // Nueva conductor
    if ($callbackData == "manual_nuevo_conductor") {
        sendMessage($chatId, "Escribe el nombre del nuevo conductor:");
        file_put_contents("manual_step_$chatId.txt", "espera_conductor");
    }

    // Selección ruta existente
    if (strpos($callbackData, "manual_ruta_") === 0) {
        $rutaId = str_replace("manual_ruta_", "", $callbackData);
        $resR = $conn->query("SELECT * FROM rutas WHERE id=$rutaId");
        $ruta = $resR->fetch_assoc();
        $datos = json_decode(file_get_contents("manual_$chatId.json"), true);
        $datos['ruta'] = $ruta['ruta'];
        file_put_contents("manual_$chatId.json", json_encode($datos));

        // Paso 3: seleccionar año
        $añoActual = date("Y");
        $buttons = [
            [["text" => $añoActual, "callback_data" => "manual_año_" . $añoActual]],
            [["text" => $añoActual + 1, "callback_data" => "manual_año_" . ($añoActual + 1)]]
        ];
        $keyboard = ["inline_keyboard" => $buttons];
        sendMessage($chatId, "Selecciona el año:", $keyboard);
    }

    // Nueva ruta
    if ($callbackData == "manual_nueva_ruta") {
        sendMessage($chatId, "Escribe el nombre de la nueva ruta:");
        file_put_contents("manual_step_$chatId.txt", "espera_ruta");
    }

    // Selección año
    if (strpos($callbackData, "manual_año_") === 0) {
        $año = str_replace("manual_año_", "", $callbackData);
        $datos = json_decode(file_get_contents("manual_$chatId.json"), true);
        $datos['año'] = $año;
        file_put_contents("manual_$chatId.json", json_encode($datos));

        $mesActual = date("n");
        $mesAnterior = $mesActual - 1 > 0 ? $mesActual - 1 : 12;
        $mesSiguiente = $mesActual + 1 <= 12 ? $mesActual + 1 : 1;

        $meses = [$mesAnterior, $mesActual, $mesSiguiente];
        $buttons = [];
        foreach ($meses as $m) {
            $buttons[][] = ["text" => date("F", mktime(0,0,0,$m,10)), "callback_data" => "manual_mes_" . $m];
        }
        $keyboard = ["inline_keyboard" => $buttons];
        sendMessage($chatId, "Selecciona el mes:", $keyboard);
    }

    // Selección mes
    if (strpos($callbackData, "manual_mes_") === 0) {
        $mes = str_replace("manual_mes_", "", $callbackData);
        $datos = json_decode(file_get_contents("manual_$chatId.json"), true);
        $datos['mes'] = $mes;
        file_put_contents("manual_$chatId.json", json_encode($datos));

        $diasEnMes = cal_days_in_month(CAL_GREGORIAN, $mes, $datos['año']);
        $buttons = [];
        for ($d = 1; $d <= $diasEnMes; $d++) {
            $buttons[][] = ["text" => (string)$d, "callback_data" => "manual_dia_" . $d];
        }
        $keyboard = ["inline_keyboard" => $buttons];
        sendMessage($chatId, "Selecciona el día:", $keyboard);
    }

    // Selección día (guardar viaje)
    if (strpos($callbackData, "manual_dia_") === 0) {
        $dia = str_replace("manual_dia_", "", $callbackData);
        $datos = json_decode(file_get_contents("manual_$chatId.json"), true);
        $fecha = $datos['año'] . "-" . $datos['mes'] . "-" . str_pad($dia, 2, "0", STR_PAD_LEFT);

        // Insertar en viajes
        $nombre = $datos['conductor']['nombre'];
        $cedula = $datos['conductor']['cedula'];
        $ruta = $datos['ruta'];
        $vehiculo = $datos['conductor']['vehiculo'];

        $stmt = $conn->prepare("INSERT INTO viajes (nombre, cedula, fecha, ruta, tipo_vehiculo) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $nombre, $cedula, $fecha, $ruta, $vehiculo);
        $stmt->execute();

        sendMessage($chatId, "✅ Viaje guardado:\nConductor: $nombre\nRuta: $ruta\nFecha: $fecha\nVehículo: $vehiculo");
        unlink("manual_$chatId.json");
        unlink("manual_step_$chatId.txt");
    }
}

// === Mensajes de texto capturados (para nuevo conductor o ruta) ===
if ($text && file_exists("manual_step_$chatId.txt")) {
    $step = file_get_contents("manual_step_$chatId.txt");

    if ($step == "espera_conductor") {
        $nombre = $conn->real_escape_string($text);
        $conn->query("INSERT INTO conductores (chat_id, nombre) VALUES ('$chatId', '$nombre')");
        unlink("manual_step_$chatId.txt");
        sendMessage($chatId, "✅ Conductor agregado. Escribe /manual de nuevo para seleccionarlo.");
    }

    if ($step == "espera_ruta") {
        $ruta = $conn->real_escape_string($text);
        $conn->query("INSERT INTO rutas (ruta) VALUES ('$ruta')");
        unlink("manual_step_$chatId.txt");
        sendMessage($chatId, "✅ Ruta agregada. Escribe /manual de nuevo para seleccionarla.");
    }
}
?>
