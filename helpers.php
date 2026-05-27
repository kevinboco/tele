<?php
// helpers.php - VERSIÓN OPTIMIZADA
require_once __DIR__.'/config.php';

// Caché en memoria para evitar lecturas repetidas de archivos
$GLOBALS['state_cache'] = [];
$GLOBALS['db_connection'] = null;

/**
 * Conexión a BD con singleton y reconnect automático
 */
function db() {
    global $db_connection;
    
    // Verificar si la conexión existe y sigue viva
    if ($db_connection && $db_connection->ping()) {
        return $db_connection;
    }
    
    // Crear nueva conexión
    $mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($mysqli->connect_error) {
        error_log("DB Connection failed: " . $mysqli->connect_error);
        return null;
    }
    
    $mysqli->set_charset("utf8mb4");
    $db_connection = $mysqli;
    return $db_connection;
}

/**
 * Enviar mensaje con async para no bloquear
 */
function sendMessage($chat_id, $text, $opts = null, $parse = 'Markdown') {
    global $APIURL;
    if (!$chat_id) return;
    
    $data = [
        "chat_id" => $chat_id, 
        "text" => $text, 
        "parse_mode" => $parse
    ];
    if ($opts) {
        $data["reply_markup"] = json_encode($opts);
    }
    
    // Usar stream context con timeout para no bloquear
    $context = stream_context_create([
        'http' => [
            'timeout' => 2, // 2 segundos máximo
            'ignore_errors' => true
        ]
    ]);
    @file_get_contents($APIURL . "sendMessage?" . http_build_query($data), false, $context);
}

/**
 * Responder callback query rápido
 */
function answerCallbackQuery($cb_id, $text = null, $show_alert = false) {
    global $APIURL;
    if (!$cb_id) return;
    
    $data = ["callback_query_id" => $cb_id];
    if ($text) {
        $data["text"] = $text;
        $data["show_alert"] = $show_alert;
    }
    
    @file_get_contents($APIURL . "answerCallbackQuery?" . http_build_query($data));
}

/**
 * Archivo de estado con mejor organización
 */
function stateFile($chat_id) {
    return __DIR__ . "/estados/estado_" . ($chat_id ?: "unknown") . ".json";
}

/**
 * Cargar estado con caché en memoria
 */
function loadState($chat_id) {
    global $state_cache;
    
    // Retornar de caché si existe
    if (isset($state_cache[$chat_id])) {
        return $state_cache[$chat_id];
    }
    
    $f = stateFile($chat_id);
    if (!file_exists($f)) {
        $state_cache[$chat_id] = [];
        return [];
    }
    
    $content = @file_get_contents($f);
    if ($content === false) {
        $state_cache[$chat_id] = [];
        return [];
    }
    
    $st = json_decode($content, true) ?: [];
    
    // Verificar TTL
    if (!empty($st) && isset($st['last_ts']) && (time() - $st['last_ts'] > STATE_TTL)) {
        @unlink($f);
        $state_cache[$chat_id] = [];
        return [];
    }
    
    $state_cache[$chat_id] = $st;
    return $st;
}

/**
 * Guardar estado con caché y escritura asíncrona
 */
function saveState($chat_id, $estado) {
    global $state_cache;
    
    // Actualizar caché inmediatamente
    $estado['last_ts'] = time();
    $state_cache[$chat_id] = $estado;
    
    // Escribir archivo en background si es posible
    $f = stateFile($chat_id);
    
    // Crear directorio si no existe
    $dir = dirname($f);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    // Escritura con flag LOCK_EX
    file_put_contents($f, json_encode($estado, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/**
 * Limpiar estado
 */
function clearState($chat_id) {
    global $state_cache;
    
    unset($state_cache[$chat_id]);
    
    $f = stateFile($chat_id);
    if (file_exists($f)) {
        @unlink($f);
    }
}

/**
 * Mutex optimizado - SIN EXIT, con timeout y reintentos
 */
function withMutex($chat_id, $timeout_seconds = 2) {
    if (!$chat_id) {
        return [null, function() {}];
    }
    
    // Intentar usar APCu para mutex en memoria (mucho más rápido)
    if (function_exists('apcu_add')) {
        $key = "mutex_chat_{$chat_id}";
        if (apcu_add($key, true, $timeout_seconds)) {
            return [$key, function() use ($key) { 
                apcu_delete($key); 
            }];
        }
        // No se pudo obtener lock, continuar sin él
        return [null, function() {}];
    }
    
    // Fallback a archivos con reintentos
    $lockFile = __DIR__ . "/locks/lock_" . $chat_id . ".lock";
    
    // Crear directorio de locks si no existe
    $lockDir = dirname($lockFile);
    if (!is_dir($lockDir)) {
        mkdir($lockDir, 0755, true);
    }
    
    $lock = fopen($lockFile, 'c');
    if (!$lock) {
        return [null, function() {}];
    }
    
    // Intentar obtener lock con reintentos
    $start = microtime(true);
    $attempts = 0;
    
    while (!flock($lock, LOCK_EX | LOCK_NB)) {
        $attempts++;
        $elapsed = microtime(true) - $start;
        
        if ($elapsed > $timeout_seconds) {
            // Timeout - continuar sin lock
            fclose($lock);
            return [null, function() {}];
        }
        
        // Espera progresiva: 50ms, 100ms, 150ms...
        $wait = min(50 * $attempts, 200);
        usleep($wait * 1000);
    }
    
    return [$lock, function() use ($lock) { 
        if ($lock) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }];
}

/**
 * Deduplicación optimizada con APCu o archivos
 */
function dedupe($chat_id, $update_id) {
    if (!$chat_id || $update_id === null) {
        return;
    }
    
    // Usar APCu para deduplicación ultrarrápida
    if (function_exists('apcu_fetch')) {
        $key = "last_update_{$chat_id}";
        $last = apcu_fetch($key, $success);
        
        if ($success && $update_id <= $last) {
            // Update duplicado - salir silenciosamente
            exit;
        }
        
        apcu_store($key, $update_id, 5); // Expira en 5 segundos
        return;
    }
    
    // Fallback a archivos
    $f = __DIR__ . "/last_update_" . $chat_id . ".txt";
    
    $last = is_file($f) ? (int)@file_get_contents($f) : -1;
    
    if ($update_id <= $last) {
        exit; // Ya procesado
    }
    
    file_put_contents($f, (string)$update_id, LOCK_EX);
}

// ============ TEclados y helpers existentes (optimizados) ============

function kbFechaAgg() {
    return [
        "inline_keyboard" => [
            [["text" => "📅 Hoy", "callback_data" => "fecha_hoy"]],
            [["text" => "✍️ Otra fecha", "callback_data" => "fecha_manual"]],
        ]
    ];
}

function kbFechaManual() {
    return [
        "inline_keyboard" => [
            [["text" => "📅 Hoy", "callback_data" => "mfecha_hoy"]],
            [["text" => "📆 Otra fecha", "callback_data" => "mfecha_otro"]],
        ]
    ];
}

function kbMeses($anio) {
    $labels = [
        1 => "Enero", 2 => "Febrero", 3 => "Marzo", 4 => "Abril",
        5 => "Mayo", 6 => "Junio", 7 => "Julio", 8 => "Agosto",
        9 => "Septiembre", 10 => "Octubre", 11 => "Noviembre", 12 => "Diciembre"
    ];
    
    $kb = ["inline_keyboard" => []];
    for ($i = 1; $i <= 12; $i += 2) {
        $row = [];
        $row[] = ["text" => $labels[$i] . " $anio", "callback_data" => "mmes_{$anio}_" . str_pad($i, 2, "0", STR_PAD_LEFT)];
        
        if ($i + 1 <= 12) {
            $row[] = ["text" => $labels[$i + 1] . " $anio", "callback_data" => "mmes_{$anio}_" . str_pad($i + 1, 2, "0", STR_PAD_LEFT)];
        }
        
        $kb["inline_keyboard"][] = $row;
    }
    return $kb;
}

// ============ Funciones de BD optimizadas con caché ============

/**
 * Obtener rutas con caché por request
 */
function obtenerRutasUsuario($conn, $conductor_id) {
    static $cache = [];
    
    if (!$conn) return [];
    
    $conductor_id = (int)$conductor_id;
    
    if (isset($cache[$conductor_id])) {
        return $cache[$conductor_id];
    }
    
    $rutas = [];
    $sql = "SELECT ruta FROM rutas WHERE conductor_id = $conductor_id ORDER BY id DESC LIMIT 50";
    
    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $rutas[] = $row['ruta'];
        }
    }
    
    $cache[$conductor_id] = $rutas;
    return $rutas;
}

function obtenerConductoresAdmin($conn, $owner) {
    if (!$conn) return [];
    
    $owner = (int)$owner;
    $sql = "SELECT id, nombre FROM conductores_admin WHERE owner_chat_id = $owner ORDER BY nombre ASC LIMIT 100";
    
    $rows = [];
    if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    return $rows;
}

function obtenerConductoresPorLetra($conn, $owner, $letra) {
    if (!$conn) return [];
    
    $owner = (int)$owner;
    $letra = $conn->real_escape_string(substr($letra, 0, 1));
    
    $sql = "SELECT id, nombre FROM conductores_admin 
            WHERE owner_chat_id = $owner 
            AND LOWER(nombre) LIKE LOWER('{$letra}%') 
            ORDER BY nombre ASC LIMIT 100";
    
    $rows = [];
    if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    return $rows;
}

function crearConductorAdmin($conn, $owner, $nombre) {
    if (!$conn) return false;
    
    $owner = (int)$owner;
    $stmt = $conn->prepare("INSERT IGNORE INTO conductores_admin (owner_chat_id, nombre) VALUES (?, ?)");
    if (!$stmt) return false;
    
    $stmt->bind_param("is", $owner, $nombre);
    $ok = $stmt->execute();
    $stmt->close();
    
    return $ok;
}

function obtenerConductorAdminPorId($conn, $id, $owner) {
    if (!$conn) return null;
    
    $id = (int)$id;
    $owner = (int)$owner;
    
    $res = $conn->query("SELECT id, nombre FROM conductores_admin WHERE id = $id AND owner_chat_id = $owner LIMIT 1");
    
    return ($res && $res->num_rows) ? $res->fetch_assoc() : null;
}

function obtenerRutasAdmin($conn, $owner) {
    if (!$conn) return [];
    
    $owner = (int)$owner;
    $sql = "SELECT id, ruta FROM rutas_admin WHERE owner_chat_id = $owner ORDER BY ruta ASC LIMIT 100";
    
    $rows = [];
    if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    return $rows;
}

function obtenerRutasPorLetra($conn, $owner, $letra) {
    if (!$conn) return [];
    
    $owner = (int)$owner;
    $letra = $conn->real_escape_string(substr($letra, 0, 1));
    
    $sql = "SELECT id, ruta FROM rutas_admin 
            WHERE owner_chat_id = $owner 
            AND LOWER(ruta) LIKE LOWER('{$letra}%') 
            ORDER BY ruta ASC LIMIT 100";
    
    $rows = [];
    if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    return $rows;
}

function crearRutaAdmin($conn, $owner, $ruta) {
    if (!$conn) return false;
    
    $owner = (int)$owner;
    $stmt = $conn->prepare("INSERT IGNORE INTO rutas_admin (owner_chat_id, ruta) VALUES (?, ?)");
    if (!$stmt) return false;
    
    $stmt->bind_param("is", $owner, $ruta);
    $ok = $stmt->execute();
    $stmt->close();
    
    return $ok;
}

function obtenerRutaAdminPorId($conn, $id, $owner) {
    if (!$conn) return null;
    
    $id = (int)$id;
    $owner = (int)$owner;
    
    $res = $conn->query("SELECT id, ruta FROM rutas_admin WHERE id = $id AND owner_chat_id = $owner LIMIT 1");
    
    return ($res && $res->num_rows) ? $res->fetch_assoc() : null;
}

function obtenerVehiculosAdmin($conn, $owner) {
    if (!$conn) return [];
    
    $owner = (int)$owner;
    $sql = "SELECT id, vehiculo FROM vehiculos_admin WHERE owner_chat_id = $owner ORDER BY vehiculo ASC LIMIT 100";
    
    $rows = [];
    if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    return $rows;
}

function crearVehiculoAdmin($conn, $owner, $vehiculo) {
    if (!$conn) return false;
    
    $owner = (int)$owner;
    $stmt = $conn->prepare("INSERT IGNORE INTO vehiculos_admin (owner_chat_id, vehiculo) VALUES (?, ?)");
    if (!$stmt) return false;
    
    $stmt->bind_param("is", $owner, $vehiculo);
    $ok = $stmt->execute();
    $stmt->close();
    
    return $ok;
}

function obtenerVehiculoAdminPorId($conn, $id, $owner) {
    if (!$conn) return null;
    
    $id = (int)$id;
    $owner = (int)$owner;
    
    $res = $conn->query("SELECT id, vehiculo FROM vehiculos_admin WHERE id = $id AND owner_chat_id = $owner LIMIT 1");
    
    return ($res && $res->num_rows) ? $res->fetch_assoc() : null;
}

function obtenerEmpresasAdmin($conn, $owner) {
    if (!$conn) return [];
    
    $owner = (int)$owner;
    $sql = "SELECT id, nombre FROM empresas_admin WHERE owner_chat_id = $owner ORDER BY nombre ASC LIMIT 100";
    
    $rows = [];
    if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    return $rows;
}

function crearEmpresaAdmin($conn, $owner, $nombre) {
    if (!$conn) return false;
    
    $owner = (int)$owner;
    $stmt = $conn->prepare("INSERT IGNORE INTO empresas_admin (owner_chat_id, nombre) VALUES (?, ?)");
    if (!$stmt) return false;
    
    $stmt->bind_param("is", $owner, $nombre);
    $ok = $stmt->execute();
    $stmt->close();
    
    return $ok;
}

function obtenerEmpresaAdminPorId($conn, $id, $owner) {
    if (!$conn) return null;
    
    $id = (int)$id;
    $owner = (int)$owner;
    
    $res = $conn->query("SELECT id, nombre FROM empresas_admin WHERE id = $id AND owner_chat_id = $owner LIMIT 1");
    
    return ($res && $res->num_rows) ? $res->fetch_assoc() : null;
}

/**
 * Limpiar caché completa (útil para debugging)
 */
function clearCache() {
    global $state_cache;
    $state_cache = [];
    
    if (function_exists('apcu_clear_cache')) {
        apcu_clear_cache();
    }
}