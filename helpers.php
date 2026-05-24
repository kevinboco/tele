<?php
// helpers.php

// ========= CONEXIÓN A LA BASE DE DATOS =========
function db() {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $db   = 'bot_viajes';
    
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        error_log("Error de conexión: " . $conn->connect_error);
        return null;
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// ========= FUNCIONES DE ESTADO =========
function saveState($chat_id, $estado) {
    $file = __DIR__ . "/estados/{$chat_id}.json";
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    file_put_contents($file, json_encode($estado, JSON_UNESCAPED_UNICODE));
}

function loadState($chat_id) {
    $file = __DIR__ . "/estados/{$chat_id}.json";
    if (!file_exists($file)) return null;
    return json_decode(file_get_contents($file), true);
}

function clearState($chat_id) {
    $file = __DIR__ . "/estados/{$chat_id}.json";
    if (file_exists($file)) unlink($file);
}

// ========= FUNCIONES DE MENSAJERÍA =========
function sendMessage($chat_id, $text, $reply_markup = null) {
    global $TOKEN;
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }
    $url = "https://api.telegram.org/bot{$TOKEN}/sendMessage";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

function answerCallbackQuery($callback_query_id, $text = null, $show_alert = false) {
    global $TOKEN;
    $data = [
        'callback_query_id' => $callback_query_id
    ];
    if ($text) {
        $data['text'] = $text;
        $data['show_alert'] = $show_alert;
    }
    $url = "https://api.telegram.org/bot{$TOKEN}/answerCallbackQuery";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

// ========= TECLADOS =========
function kbFechaManual() {
    return [
        "inline_keyboard" => [
            [["text" => "📅 Hoy", "callback_data" => "mfecha_hoy"]],
            [["text" => "📆 Elegir otra fecha", "callback_data" => "mfecha_otro"]]
        ]
    ];
}

function kbMeses($anio) {
    $meses = [
        "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
        "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
    ];
    $kb = ["inline_keyboard" => []];
    $row = [];
    foreach ($meses as $i => $mes) {
        $row[] = [
            "text" => $mes,
            "callback_data" => "mmes_{$anio}_" . ($i + 1)
        ];
        if (count($row) == 3) {
            $kb["inline_keyboard"][] = $row;
            $row = [];
        }
    }
    if (!empty($row)) $kb["inline_keyboard"][] = $row;
    return $kb;
}

// ========= CONDUCTORES (GLOBAL - sin chat_id) =========
function obtenerConductoresPorLetra($conn, $letra) {
    $stmt = $conn->prepare("SELECT id, nombre FROM conductores WHERE nombre LIKE ? ORDER BY nombre");
    $like = $letra . '%';
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();
    $conductores = [];
    while ($row = $result->fetch_assoc()) {
        $conductores[] = $row;
    }
    $stmt->close();
    return $conductores;
}

function obtenerConductorAdminPorId($conn, $id) {
    $stmt = $conn->prepare("SELECT id, nombre FROM conductores WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row;
}

function crearConductorAdmin($conn, $nombre) {
    // Verificar si ya existe (sin filtrar por chat_id)
    $stmt = $conn->prepare("SELECT id FROM conductores WHERE nombre = ?");
    $stmt->bind_param("s", $nombre);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->fetch_assoc()) {
        $stmt->close();
        return false; // Ya existe
    }
    $stmt->close();
    
    $stmt = $conn->prepare("INSERT INTO conductores (nombre) VALUES (?)");
    $stmt->bind_param("s", $nombre);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// ========= RUTAS (GLOBAL - sin chat_id) =========
function obtenerRutasPorLetra($conn, $letra) {
    $stmt = $conn->prepare("SELECT id, ruta FROM rutas WHERE ruta LIKE ? ORDER BY ruta");
    $like = $letra . '%';
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();
    $rutas = [];
    while ($row = $result->fetch_assoc()) {
        $rutas[] = $row;
    }
    $stmt->close();
    return $rutas;
}

function obtenerRutaAdminPorId($conn, $id) {
    $stmt = $conn->prepare("SELECT id, ruta FROM rutas WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row;
}

function crearRutaAdmin($conn, $ruta) {
    // Verificar si ya existe (sin filtrar por chat_id)
    $stmt = $conn->prepare("SELECT id FROM rutas WHERE ruta = ?");
    $stmt->bind_param("s", $ruta);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->fetch_assoc()) {
        $stmt->close();
        return false; // Ya existe
    }
    $stmt->close();
    
    $stmt = $conn->prepare("INSERT INTO rutas (ruta) VALUES (?)");
    $stmt->bind_param("s", $ruta);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// ========= VEHÍCULOS (GLOBAL - sin chat_id) =========
function obtenerVehiculosAdmin($conn) {
    $stmt = $conn->prepare("SELECT id, vehiculo FROM vehiculos ORDER BY vehiculo");
    $stmt->execute();
    $result = $stmt->get_result();
    $vehiculos = [];
    while ($row = $result->fetch_assoc()) {
        $vehiculos[] = $row;
    }
    $stmt->close();
    return $vehiculos;
}

function obtenerVehiculoAdminPorId($conn, $id) {
    $stmt = $conn->prepare("SELECT id, vehiculo FROM vehiculos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row;
}

function crearVehiculoAdmin($conn, $vehiculo) {
    // Verificar si ya existe (sin filtrar por chat_id)
    $stmt = $conn->prepare("SELECT id FROM vehiculos WHERE vehiculo = ?");
    $stmt->bind_param("s", $vehiculo);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->fetch_assoc()) {
        $stmt->close();
        return false; // Ya existe
    }
    $stmt->close();
    
    $stmt = $conn->prepare("INSERT INTO vehiculos (vehiculo) VALUES (?)");
    $stmt->bind_param("s", $vehiculo);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// ========= EMPRESAS (GLOBAL - sin chat_id) =========
function obtenerEmpresasAdmin($conn) {
    $stmt = $conn->prepare("SELECT id, nombre FROM empresas ORDER BY nombre");
    $stmt->execute();
    $result = $stmt->get_result();
    $empresas = [];
    while ($row = $result->fetch_assoc()) {
        $empresas[] = $row;
    }
    $stmt->close();
    return $empresas;
}

function obtenerEmpresaAdminPorId($conn, $id) {
    $stmt = $conn->prepare("SELECT id, nombre FROM empresas WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row;
}

function crearEmpresaAdmin($conn, $nombre) {
    // Verificar si ya existe (sin filtrar por chat_id)
    $stmt = $conn->prepare("SELECT id FROM empresas WHERE nombre = ?");
    $stmt->bind_param("s", $nombre);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->fetch_assoc()) {
        $stmt->close();
        return false; // Ya existe
    }
    $stmt->close();
    
    $stmt = $conn->prepare("INSERT INTO empresas (nombre) VALUES (?)");
    $stmt->bind_param("s", $nombre);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// ========= OBTENER TODOS LOS CONDUCTORES (para /agg) =========
function obtenerConductoresAdmin($conn) {
    $stmt = $conn->prepare("SELECT id, nombre FROM conductores ORDER BY nombre");
    $stmt->execute();
    $result = $stmt->get_result();
    $conductores = [];
    while ($row = $result->fetch_assoc()) {
        $conductores[] = $row;
    }
    $stmt->close();
    return $conductores;
}