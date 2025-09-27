<?php
// flow_prestamos.php
require_once __DIR__.'/helpers.php';

// === Helpers para persistir nombres de prestatarios y prestamistas ===
function obtenerPrestatarios($conn, $owner_chat_id) {
    $rows = [];
    if (!$conn) return $rows;
    $owner_chat_id = (int)$owner_chat_id;
    $res = $conn->query("SELECT id, nombre FROM prestatarios WHERE owner_chat_id=$owner_chat_id ORDER BY id DESC LIMIT 25");
    if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
    return $rows;
}
function crearPrestatario($conn, $owner_chat_id, $nombre) {
    $stmt = $conn->prepare("INSERT IGNORE INTO prestatarios (owner_chat_id, nombre) VALUES (?, ?)");
    $stmt->bind_param("is", $owner_chat_id, $nombre);
    $stmt->execute(); $stmt->close();
}
function obtenerPrestatarioPorId($conn, $id, $owner_chat_id) {
    $id = (int)$id; $owner_chat_id = (int)$owner_chat_id;
    $res = $conn->query("SELECT id, nombre FROM prestatarios WHERE id=$id AND owner_chat_id=$owner_chat_id LIMIT 1");
    return ($res && $res->num_rows) ? $res->fetch_assoc() : null;
}

function obtenerPrestamistas($conn, $owner_chat_id) {
    $rows = [];
    if (!$conn) return $rows;
    $owner_chat_id = (int)$owner_chat_id;
    $res = $conn->query("SELECT id, nombre FROM prestamistas WHERE owner_chat_id=$owner_chat_id ORDER BY id DESC LIMIT 25");
    if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
    return $rows;
}
function crearPrestamista($conn, $owner_chat_id, $nombre) {
    $stmt = $conn->prepare("INSERT IGNORE INTO prestamistas (owner_chat_id, nombre) VALUES (?, ?)");
    $stmt->bind_param("is", $owner_chat_id, $nombre);
    $stmt->execute(); $stmt->close();
}
function obtenerPrestamistaPorId($conn, $id, $owner_chat_id) {
    $id = (int)$id; $owner_chat_id = (int)$owner_chat_id;
    $res = $conn->query("SELECT id, nombre FROM prestamistas WHERE id=$id AND owner_chat_id=$owner_chat_id LIMIT 1");
    return ($res && $res->num_rows) ? $res->fetch_assoc() : null;
}

// === Entrypoint ===
function prestamos_entrypoint($chat_id, $estado): void {
    if (!empty($estado) && ($estado['flujo'] ?? '') === 'prestamos') {
        prestamos_resend_current_step($chat_id, $estado);
        return;
    }
    $estado = ["flujo"=>"prestamos", "paso"=>"prestatario_menu"];
    saveState($chat_id, $estado);

    $conn = db();
    $prestatarios = $conn ? obtenerPrestatarios($conn, $chat_id) : [];
    if ($prestatarios) {
        $kb = ["inline_keyboard"=>[]];
        foreach ($prestatarios as $p) {
            $kb["inline_keyboard"][] = [["text"=>$p['nombre'], "callback_data"=>"prestamo_prestatario_sel_".$p['id']]];
        }
        $kb["inline_keyboard"][] = [["text"=>"➕ Nuevo prestatario","callback_data"=>"prestamo_prestatario_nuevo"]];
        sendMessage($chat_id, "👤 ¿A quién se le presta?", $kb);
    } else {
        $estado['paso'] = 'prestatario_nuevo_texto';
        saveState($chat_id, $estado);
        sendMessage($chat_id, "No tienes prestatarios guardados.\n✍️ Escribe el *nombre* del prestatario:");
    }
    $conn?->close();
}

function prestamos_resend_current_step($chat_id, $estado): void {
    switch ($estado['paso'] ?? '') {
        case 'prestatario_menu':
            $conn = db();
            $prestatarios = $conn ? obtenerPrestatarios($conn, $chat_id) : [];
            $conn?->close();
            if ($prestatarios) {
                $kb = ["inline_keyboard"=>[]];
                foreach ($prestatarios as $p) {
                    $kb["inline_keyboard"][] = [["text"=>$p['nombre'], "callback_data"=>"prestamo_prestatario_sel_".$p['id']]];
                }
                $kb["inline_keyboard"][] = [["text"=>"➕ Nuevo prestatario","callback_data"=>"prestamo_prestatario_nuevo"]];
                sendMessage($chat_id, "👤 ¿A quién se le presta?", $kb);
            } else {
                sendMessage($chat_id, "✍️ Escribe el *nombre* del prestatario:");
            }
            break;

        case 'prestamista_menu':
            $conn = db();
            $prestamistas = $conn ? obtenerPrestamistas($conn, $chat_id) : [];
            $conn?->close();
            if ($prestamistas) {
                $kb = ["inline_keyboard"=>[]];
                foreach ($prestamistas as $p) {
                    $kb["inline_keyboard"][] = [["text"=>$p['nombre'], "callback_data"=>"prestamo_prestamista_sel_".$p['id']]];
                }
                $kb["inline_keyboard"][] = [["text"=>"➕ Nuevo prestamista","callback_data"=>"prestamo_prestamista_nuevo"]];
                sendMessage($chat_id, "👥 ¿Quién presta?", $kb);
            } else {
                sendMessage($chat_id, "✍️ Escribe el *nombre* del prestamista:");
            }
            break;

        case 'monto':  sendMessage($chat_id, "💰 Ingresa el *monto prestado* (solo números):"); break;
        case 'fecha':  sendMessage($chat_id, "📅 Ingresa la *fecha* (YYYY-MM-DD):"); break;
        case 'captura':sendMessage($chat_id, "📸 Envía la *captura de la transferencia*:"); break;
        default:       sendMessage($chat_id, "Usa /cancel para reiniciar.");
    }
}

function prestamos_handle_callback($chat_id, &$estado, string $cb_data, ?string $cb_id=null): void {
    if (($estado["flujo"] ?? "") !== "prestamos") return;

    // Seleccionar prestatario
    if (strpos($cb_data,'prestamo_prestatario_sel_')===0) {
        $id = (int)substr($cb_data, strlen('prestamo_prestatario_sel_'));
        $conn = db(); $row = obtenerPrestatarioPorId($conn, $id, $chat_id); $conn?->close();
        if ($row) {
            $estado['prestatario'] = $row['nombre'];
            $estado['paso'] = 'prestamista_menu';
            saveState($chat_id, $estado);
            prestamos_resend_current_step($chat_id, $estado);
        } else {
            sendMessage($chat_id,"⚠️ Prestatario no encontrado.");
        }
    }
    if ($cb_data === 'prestamo_prestatario_nuevo') {
        $estado['paso'] = 'prestatario_nuevo_texto';
        saveState($chat_id, $estado);
        sendMessage($chat_id, "✍️ Escribe el *nombre* del prestatario:");
    }

    // Seleccionar prestamista
    if (strpos($cb_data,'prestamo_prestamista_sel_')===0) {
        $id = (int)substr($cb_data, strlen('prestamo_prestamista_sel_'));
        $conn = db(); $row = obtenerPrestamistaPorId($conn, $id, $chat_id); $conn?->close();
        if ($row) {
            $estado['prestamista'] = $row['nombre'];
            $estado['paso'] = 'monto';
            saveState($chat_id, $estado);
            sendMessage($chat_id,"💰 Ingresa el *monto prestado*:");
        } else {
            sendMessage($chat_id,"⚠️ Prestamista no encontrado.");
        }
    }
    if ($cb_data === 'prestamo_prestamista_nuevo') {
        $estado['paso'] = 'prestamista_nuevo_texto';
        saveState($chat_id, $estado);
        sendMessage($chat_id,"✍️ Escribe el *nombre* del prestamista:");
    }

    if ($cb_id) answerCallbackQuery($cb_id);
}

function prestamos_handle_text($chat_id, &$estado, string $text=null, $photo=null): void {
    if (($estado["flujo"] ?? "") !== "prestamos") return;

    switch ($estado['paso']) {
        case 'prestatario_nuevo_texto':
            $nombre = trim($text ?? '');
            if ($nombre===''){ sendMessage($chat_id,"⚠️ No puede estar vacío."); return; }
            $conn=db(); if($conn){ crearPrestatario($conn,$chat_id,$nombre); $conn->close();}
            $estado['prestatario']=$nombre;
            $estado['paso']='prestamista_menu'; saveState($chat_id,$estado);
            prestamos_resend_current_step($chat_id,$estado);
            break;

        case 'prestamista_nuevo_texto':
            $nombre = trim($text ?? '');
            if ($nombre===''){ sendMessage($chat_id,"⚠️ No puede estar vacío."); return; }
            $conn=db(); if($conn){ crearPrestamista($conn,$chat_id,$nombre); $conn->close();}
            $estado['prestamista']=$nombre;
            $estado['paso']='monto'; saveState($chat_id,$estado);
            sendMessage($chat_id,"💰 Ingresa el *monto prestado*:");
            break;

        case 'monto':
            if (!is_numeric($text)){ sendMessage($chat_id,"⚠️ Debe ser un número."); return; }
            $estado['monto']=(float)$text;
            $estado['paso']='fecha'; saveState($chat_id,$estado);
            sendMessage($chat_id,"📅 Ingresa la *fecha* (YYYY-MM-DD):");
            break;

        case 'fecha':
            if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$text)){ sendMessage($chat_id,"⚠️ Formato inválido. Usa YYYY-MM-DD."); return; }
            $estado['fecha']=$text;
            $estado['paso']='captura'; saveState($chat_id,$estado);
            sendMessage($chat_id,"📸 Envía la *captura de la transferencia*:");
            break;

        case 'captura':
            if(!$photo){ sendMessage($chat_id,"⚠️ Debes enviar una *imagen*."); return; }
            $file_id = $photo[0]["file_id"] ?? end($photo)["file_id"];
            $fileInfo = apiRequest("getFile",["file_id"=>$file_id]);
            $nombreArchivo=null;
            if(isset($fileInfo["result"]["file_path"])){
                $file_path=$fileInfo["result"]["file_path"];
                $fileUrl="https://api.telegram.org/file/bot".BOT_TOKEN."/$file_path";
                $carpeta=__DIR__."/uploads/";
                if(!is_dir($carpeta)) mkdir($carpeta,0777,true);
                $nombreArchivo=time()."_".basename($file_path);
                file_put_contents($carpeta.$nombreArchivo,file_get_contents($fileUrl));
            }
            $estado['captura']=$nombreArchivo ?? 'sin_imagen';

            // Guardar en DB
            $conn=db();
            if($conn){
                $stmt=$conn->prepare("INSERT INTO prestamos (prestatario,prestamista,monto,fecha,captura) VALUES (?,?,?,?,?)");
                $stmt->bind_param("ssdss",$estado['prestatario'],$estado['prestamista'],$estado['monto'],$estado['fecha'],$estado['captura']);
                if($stmt->execute()){
                    sendMessage($chat_id,"✅ Préstamo registrado:\n👤 A: *{$estado['prestatario']}*\n👥 De: *{$estado['prestamista']}*\n💰 {$estado['monto']}\n📅 {$estado['fecha']}");
                } else {
                    sendMessage($chat_id,"❌ Error al guardar: ".$conn->error);
                }
                $stmt->close(); $conn->close();
            }
            clearState($chat_id);
            break;
    }
}
