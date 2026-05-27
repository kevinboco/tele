<?php
// helpers.php - VERSIÓN CORREGIDA (sin exit en mutex)
require_once __DIR__.'/config.php';

function db() {
    $mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    return $mysqli->connect_error ? null : $mysqli;
}

function sendMessage($chat_id, $text, $opts = null, $parse='Markdown') {
    global $APIURL;
    if (!$chat_id) return;
    $data = ["chat_id"=>$chat_id, "text"=>$text, "parse_mode"=>$parse];
    if ($opts) $data["reply_markup"] = json_encode($opts);
    @file_get_contents($APIURL . "sendMessage?" . http_build_query($data));
}

function answerCallbackQuery($cb_id) {
    global $APIURL;
    if (!$cb_id) return;
    @file_get_contents($APIURL . "answerCallbackQuery?" . http_build_query(["callback_query_id"=>$cb_id]));
}

function stateFile($chat_id) {
    return __DIR__ . "/estado_" . ($chat_id ?: "unknown") . ".json";
}

function loadState($chat_id) {
    $f = stateFile($chat_id);
    if (!file_exists($f)) return [];
    $st = json_decode(file_get_contents($f), true) ?: [];
    // TTL
    if (!empty($st) && isset($st['last_ts']) && (time() - $st['last_ts'] > STATE_TTL)) {
        @unlink($f);
        return [];
    }
    return $st;
}

function saveState($chat_id, $estado) {
    $f = stateFile($chat_id);
    $estado['last_ts'] = time();
    file_put_contents($f, json_encode($estado, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function clearState($chat_id) {
    $f = stateFile($chat_id);
    if (file_exists($f)) @unlink($f);
}

// ============ MUTEX CORREGIDO (SIN EXIT) ============
function withMutex($chat_id) {
    // Versión corregida - NO hace exit, solo continúa sin lock
    $lock = null;
    if ($chat_id) {
        $lockFile = __DIR__ . "/lock_" . $chat_id . ".lock";
        $lock = fopen($lockFile, 'c');
        if ($lock && !flock($lock, LOCK_EX | LOCK_NB)) {
            // No hacer exit, solo registrar y continuar sin lock
            file_put_contents(__DIR__ . "/debug.txt", date('H:i:s') . " [LOCK] Chat $chat_id ocupado - continuando sin lock\n", FILE_APPEND);
            fclose($lock);
            $lock = null;
        }
    }
    return [$lock, function() use ($lock) {
        if ($lock) { 
            flock($lock, LOCK_UN); 
            fclose($lock); 
        }
    }];
}

// ============ DEDUPE CORREGIDO (SIN EXIT) ============
function dedupe($chat_id, $update_id) {
    if (!$chat_id || $update_id===null) return;
    $f = __DIR__ . "/last_update_" . $chat_id . ".txt";
    $last = is_file($f) ? (int)file_get_contents($f) : -1;
    if ($update_id <= $last) {
        // No hacer exit, solo registrar y continuar
        file_put_contents(__DIR__ . "/debug.txt", date('H:i:s') . " [DEDUPE] Update $update_id duplicado, continuando\n", FILE_APPEND);
        return;
    }
    file_put_contents($f, (string)$update_id, LOCK_EX);
}

// ============ TECLADOS ============
function kbFechaAgg() {
    return [
        "inline_keyboard" => [
            [ ["text"=>"📅 Hoy","callback_data"=>"fecha_hoy"] ],
            [ ["text"=>"✍️ Otra fecha","callback_data"=>"fecha_manual"] ],
        ]
    ];
}

function kbFechaManual() {
    return [
        "inline_keyboard" => [
            [["text"=>"📅 Hoy","callback_data"=>"mfecha_hoy"]],
            [["text"=>"📆 Otra fecha","callback_data"=>"mfecha_otro"]],
        ]
    ];
}

function kbMeses($anio) {
    $labels=[1=>"Enero",2=>"Febrero",3=>"Marzo",4=>"Abril",5=>"Mayo",6=>"Junio",7=>"Julio",8=>"Agosto",9=>"Septiembre",10=>"Octubre",11=>"Noviembre",12=>"Diciembre"];
    $kb=["inline_keyboard"=>[]];
    for ($i=1;$i<=12;$i+=2) {
        $row=[];
        $row[]=["text"=>$labels[$i]." $anio","callback_data"=>"mmes_".$anio."_".str_pad($i,2,"0",STR_PAD_LEFT)];
        if ($i+1<=12) $row[]=["text"=>$labels[$i+1]." $anio","callback_data"=>"mmes_".$anio."_".str_pad($i+1,2,"0",STR_PAD_LEFT)];
        $kb["inline_keyboard"][]=$row;
    }
    return $kb;
}

// ============ FUNCIONES DE CONDUCTORES ============
function obtenerRutasUsuario($conn, $conductor_id) {
    $rutas=[]; if(!$conn) return $rutas;
    $conductor_id=(int)$conductor_id;
    $sql="SELECT ruta FROM rutas WHERE conductor_id=$conductor_id ORDER BY id DESC";
    if ($res=$conn->query($sql)) while($row=$res->fetch_assoc()) $rutas[]=$row['ruta'];
    return $rutas;
}

function obtenerConductoresAdmin($conn, $owner) {
    $rows=[]; if(!$conn) return $rows;
    $owner=(int)$owner;
    $sql="SELECT id, nombre FROM conductores_admin WHERE owner_chat_id=$owner ORDER BY nombre ASC LIMIT 100";
    if ($res=$conn->query($sql)) while($r=$res->fetch_assoc()) $rows[]=$r;
    return $rows;
}

function obtenerConductoresPorLetra($conn, $owner, $letra) {
    $rows = []; 
    if (!$conn) return $rows;
    $owner = (int)$owner;
    $letra = $conn->real_escape_string($letra);
    $sql = "SELECT id, nombre FROM conductores_admin 
            WHERE owner_chat_id=$owner 
            AND LOWER(nombre) LIKE LOWER('{$letra}%') 
            ORDER BY nombre ASC LIMIT 100";
    if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    return $rows;
}

function crearConductorAdmin($conn, $owner, $nombre) {
    if(!$conn) return false; 
    $owner=(int)$owner;
    $stmt=$conn->prepare("INSERT IGNORE INTO conductores_admin (owner_chat_id, nombre) VALUES (?, ?)");
    $stmt->bind_param("is", $owner, $nombre);
    $ok=$stmt->execute(); 
    $stmt->close(); 
    return $ok;
}

function obtenerConductorAdminPorId($conn, $id, $owner) {
    if(!$conn) return null; 
    $id=(int)$id; 
    $owner=(int)$owner;
    $res=$conn->query("SELECT id, nombre FROM conductores_admin WHERE id=$id AND owner_chat_id=$owner LIMIT 1");
    return ($res && $res->num_rows)? $res->fetch_assoc():null;
}

// ============ FUNCIONES DE RUTAS ============
function obtenerRutasAdmin($conn, $owner) {
    $rows=[]; 
    if(!$conn) return $rows;
    $owner=(int)$owner;
    $res=$conn->query("SELECT id, ruta FROM rutas_admin WHERE owner_chat_id=$owner ORDER BY ruta ASC LIMIT 100");
    if($res) while($r=$res->fetch_assoc()) $rows[]=$r; 
    return $rows;
}

function obtenerRutasPorLetra($conn, $owner, $letra) {
    $rows = []; 
    if (!$conn) return $rows;
    $owner = (int)$owner;
    $letra = $conn->real_escape_string($letra);
    $sql = "SELECT id, ruta FROM rutas_admin 
            WHERE owner_chat_id=$owner 
            AND LOWER(ruta) LIKE LOWER('{$letra}%') 
            ORDER BY ruta ASC LIMIT 100";
    if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    return $rows;
}

function crearRutaAdmin($conn, $owner, $ruta) {
    if(!$conn) return false; 
    $owner=(int)$owner;
    $stmt=$conn->prepare("INSERT IGNORE INTO rutas_admin (owner_chat_id, ruta) VALUES (?, ?)");
    $stmt->bind_param("is", $owner, $ruta); 
    $ok=$stmt->execute(); 
    $stmt->close(); 
    return $ok;
}

function obtenerRutaAdminPorId($conn, $id, $owner) {
    if(!$conn) return null; 
    $id=(int)$id; 
    $owner=(int)$owner;
    $res=$conn->query("SELECT id, ruta FROM rutas_admin WHERE id=$id AND owner_chat_id=$owner LIMIT 1");
    return ($res && $res->num_rows)? $res->fetch_assoc():null;
}

// ============ FUNCIONES DE VEHÍCULOS ============
function obtenerVehiculosAdmin($conn, $owner) {
    $rows=[]; 
    if(!$conn) return $rows; 
    $owner=(int)$owner;
    $res=$conn->query("SELECT id, vehiculo FROM vehiculos_admin WHERE owner_chat_id=$owner ORDER BY vehiculo ASC LIMIT 100");
    if($res) while($r=$res->fetch_assoc()) $rows[]=$r; 
    return $rows;
}

function crearVehiculoAdmin($conn, $owner, $vehiculo) {
    if(!$conn) return false; 
    $owner=(int)$owner;
    $stmt=$conn->prepare("INSERT IGNORE INTO vehiculos_admin (owner_chat_id, vehiculo) VALUES (?, ?)");
    $stmt->bind_param("is", $owner, $vehiculo); 
    $ok=$stmt->execute(); 
    $stmt->close(); 
    return $ok;
}

function obtenerVehiculoAdminPorId($conn, $id, $owner) {
    if(!$conn) return null; 
    $id=(int)$id; 
    $owner=(int)$owner;
    $res=$conn->query("SELECT id, vehiculo FROM vehiculos_admin WHERE id=$id AND owner_chat_id=$owner LIMIT 1");
    return ($res && $res->num_rows)? $res->fetch_assoc():null;
}

// ============ FUNCIONES DE EMPRESAS ============
function obtenerEmpresasAdmin($conn, $owner) {
    $rows=[]; 
    if(!$conn) return $rows; 
    $owner=(int)$owner;
    $res=$conn->query("SELECT id, nombre FROM empresas_admin WHERE owner_chat_id=$owner ORDER BY nombre ASC LIMIT 100");
    if($res) while($r=$res->fetch_assoc()) $rows[]=$r; 
    return $rows;
}

function crearEmpresaAdmin($conn, $owner, $nombre) {
    if(!$conn) return false; 
    $owner=(int)$owner;
    $stmt=$conn->prepare("INSERT IGNORE INTO empresas_admin (owner_chat_id, nombre) VALUES (?, ?)");
    $stmt->bind_param("is", $owner, $nombre); 
    $ok=$stmt->execute(); 
    $stmt->close(); 
    return $ok;
}

function obtenerEmpresaAdminPorId($conn, $id, $owner) {
    if(!$conn) return null; 
    $id=(int)$id; 
    $owner=(int)$owner;
    $res=$conn->query("SELECT id, nombre FROM empresas_admin WHERE id=$id AND owner_chat_id=$owner LIMIT 1");
    return ($res && $res->num_rows)? $res->fetch_assoc():null;
}