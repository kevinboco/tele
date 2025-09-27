<?php
// flow_p.php — Reportes /p (con listado completo y export CSV)
require_once __DIR__.'/helpers.php';

/** Utils */
function p_money($n){ return number_format((float)$n, 0, ',', '.'); }
function p_b64e($s){ return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function p_b64d($s){ return base64_decode(strtr($s, '-_', '+/')); }

/** Enviar documento (CSV) a Telegram */
function p_send_document($chat_id, $filepath, $filename, $caption="") {
    global $TOKEN;
    if (!file_exists($filepath)) return false;

    $ch = curl_init("https://api.telegram.org/bot{$TOKEN}/sendDocument");
    $cfile = new CURLFile($filepath, mime_content_type($filepath) ?: 'text/csv', $filename);
    $post = [
        'chat_id' => $chat_id,
        'caption' => $caption,
        'document'=> $cfile,
    ];
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
    ]);
    $res = curl_exec($ch);
    $ok  = $res !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
    curl_close($ch);
    return $ok;
}

/** Entry */
function p_entrypoint($chat_id, $estado): void {
    if (!empty($estado) && ($estado['flujo'] ?? '') === 'p') {
        p_resend_current_step($chat_id, $estado);
        return;
    }
    $estado = ["flujo"=>"p", "paso"=>"p_menu"];
    saveState($chat_id, $estado);
    p_send_main_menu($chat_id);
}

/** Menú principal */
function p_send_main_menu($chat_id): void {
    $kb = [
        "inline_keyboard" => [
            [["text"=>"🔎 Por prestatario","callback_data"=>"p_menu_deudor"]],
            [["text"=>"🔎 Por prestamista","callback_data"=>"p_menu_prestamista"]],
            [["text"=>"📊 Resumen global por prestamista","callback_data"=>"p_menu_global"]],
            [["text"=>"📄 Ver TODO (paginado)","callback_data"=>"p_menu_all_0"]],
            [["text"=>"⬇️ Exportar CSV (todo)","callback_data"=>"p_menu_export"]],
        ]
    ];
    sendMessage($chat_id, "Elige el reporte:", $kb);
}

/** Reenviar paso */
function p_resend_current_step($chat_id, $estado): void {
    switch ($estado['paso'] ?? '') {
        case 'p_menu': p_send_main_menu($chat_id); break;
        case 'p_sel_deudor': p_show_deudor_picker($chat_id); break;
        case 'p_sel_prestamista': p_show_prestamista_picker($chat_id); break;
        case 'p_all':
            $page = (int)($estado['p_page'] ?? 0);
            p_show_all_paginated($chat_id, $page);
            break;
        default:
            sendMessage($chat_id, "Usa /cancel para reiniciar.");
    }
}

/** Listados de opciones (a partir de la tabla prestamos) */
function p_show_deudor_picker($chat_id): void {
    $conn = db(); if(!$conn){ sendMessage($chat_id,"❌ Error de DB."); return; }
    $stmt = $conn->prepare("SELECT deudor, SUM(monto) s FROM prestamos WHERE chat_id=? GROUP BY deudor ORDER BY s DESC LIMIT 25");
    $stmt->bind_param("i", $chat_id);
    $stmt->execute(); $res = $stmt->get_result();
    $kb = ["inline_keyboard"=>[]];
    while($r = $res->fetch_assoc()){
        $label = $r['deudor']." ( $".p_money($r['s'])." )";
        $kb["inline_keyboard"][] = [[ "text"=>$label, "callback_data"=>"p_deu_".p_b64e($r['deudor']) ]];
    }
    $stmt->close(); $conn->close();

    if (empty($kb["inline_keyboard"])) {
        sendMessage($chat_id, "No hay préstamos registrados todavía.");
    } else {
        sendMessage($chat_id, "Elige *prestatario*:", $kb);
    }
}

function p_show_prestamista_picker($chat_id): void {
    $conn = db(); if(!$conn){ sendMessage($chat_id,"❌ Error de DB."); return; }
    $stmt = $conn->prepare("SELECT prestamista, SUM(monto) s FROM prestamos WHERE chat_id=? GROUP BY prestamista ORDER BY s DESC LIMIT 25");
    $stmt->bind_param("i", $chat_id);
    $stmt->execute(); $res = $stmt->get_result();
    $kb = ["inline_keyboard"=>[]];
    while($r = $res->fetch_assoc()){
        $label = $r['prestamista']." ( $".p_money($r['s'])." )";
        $kb["inline_keyboard"][] = [[ "text"=>$label, "callback_data"=>"p_pre_".p_b64e($r['prestamista']) ]];
    }
    $stmt->close(); $conn->close();

    if (empty($kb["inline_keyboard"])) {
        sendMessage($chat_id, "No hay préstamos registrados todavía.");
    } else {
        sendMessage($chat_id, "Elige *prestamista*:", $kb);
    }
}

/** Callbacks */
function p_handle_callback($chat_id, &$estado, string $cb_data, ?string $cb_id=null): void {
    if (($estado['flujo'] ?? '') !== 'p') return;

    // Submenús
    if ($cb_data === 'p_menu_deudor') {
        $estado['paso'] = 'p_sel_deudor'; saveState($chat_id, $estado);
        p_show_deudor_picker($chat_id);
        if ($cb_id) answerCallbackQuery($cb_id); return;
    }
    if ($cb_data === 'p_menu_prestamista') {
        $estado['paso'] = 'p_sel_prestamista'; saveState($chat_id, $estado);
        p_show_prestamista_picker($chat_id);
        if ($cb_id) answerCallbackQuery($cb_id); return;
    }
    if ($cb_data === 'p_menu_global') {
        p_show_global_by_prestamista($chat_id);
        if ($cb_id) answerCallbackQuery($cb_id); return;
    }

    // Listado completo paginado
    if (strpos($cb_data, 'p_menu_all_') === 0) {
        $page = (int)substr($cb_data, strlen('p_menu_all_')); // p_menu_all_0,1,2...
        $estado['paso'] = 'p_all';
        $estado['p_page'] = $page;
        saveState($chat_id, $estado);
        p_show_all_paginated($chat_id, $page);
        if ($cb_id) answerCallbackQuery($cb_id); return;
    }
    if (strpos($cb_data, 'p_all_page_') === 0) {
        $page = (int)substr($cb_data, strlen('p_all_page_'));
        $estado['p_page'] = $page; saveState($chat_id, $estado);
        p_show_all_paginated($chat_id, $page);
        if ($cb_id) answerCallbackQuery($cb_id); return;
    }

    // Export CSV
    if ($cb_data === 'p_menu_export') {
        p_export_csv($chat_id);
        if ($cb_id) answerCallbackQuery($cb_id); return;
    }

    // Reportes por entidad
    if (strpos($cb_data, 'p_deu_') === 0) {
        $deu = p_b64d(substr($cb_data, 6));
        p_show_report_by_deudor($chat_id, $deu);
        if ($cb_id) answerCallbackQuery($cb_id); return;
    }
    if (strpos($cb_data, 'p_pre_') === 0) {
        $pre = p_b64d(substr($cb_data, 6));
        p_show_report_by_prestamista($chat_id, $pre);
        if ($cb_id) answerCallbackQuery($cb_id); return;
    }

    if ($cb_id) answerCallbackQuery($cb_id);
}

/** Texto (no se usa; todo botón) */
function p_handle_text($chat_id, &$estado, string $text=null, $photo=null): void {
    if (($estado['flujo'] ?? '') !== 'p') return;
    p_resend_current_step($chat_id, $estado);
}

/** Reporte por prestatario */
function p_show_report_by_deudor($chat_id, $deudor): void {
    $conn = db(); if(!$conn){ sendMessage($chat_id,"❌ Error de DB."); return; }

    $stmt = $conn->prepare("SELECT COUNT(*) n, COALESCE(SUM(monto),0) s FROM prestamos WHERE chat_id=? AND deudor=?");
    $stmt->bind_param("is", $chat_id, $deudor);
    $stmt->execute(); $stmt->bind_result($n,$s); $stmt->fetch(); $stmt->close();

    $stmt = $conn->prepare("SELECT prestamista, COALESCE(SUM(monto),0) s FROM prestamos WHERE chat_id=? AND deudor=? GROUP BY prestamista ORDER BY s DESC");
    $stmt->bind_param("is", $chat_id, $deudor);
    $stmt->execute(); $res = $stmt->get_result();

    $lines = [];
    while($r = $res->fetch_assoc()){
        $lines[] = "• {$r['prestamista']}: $ ".p_money($r['s']);
    }
    $stmt->close(); $conn->close();

    $msg = "📌 *Prestatario:* {$deudor}\n".
           "🧮 *Préstamos:* {$n}\n".
           "💵 *Total:* $ ".p_money($s)."\n";
    if ($lines) $msg .= "\n👤 *Por prestamista:*\n".implode("\n",$lines);
    sendMessage($chat_id, $msg);
}

/** Reporte por prestamista */
function p_show_report_by_prestamista($chat_id, $prestamista): void {
    $conn = db(); if(!$conn){ sendMessage($chat_id,"❌ Error de DB."); return; }

    $stmt = $conn->prepare("SELECT COUNT(*) n, COALESCE(SUM(monto),0) s FROM prestamos WHERE chat_id=? AND prestamista=?");
    $stmt->bind_param("is", $chat_id, $prestamista);
    $stmt->execute(); $stmt->bind_result($n,$s); $stmt->fetch(); $stmt->close();

    $stmt = $conn->prepare("SELECT deudor, COALESCE(SUM(monto),0) s FROM prestamos WHERE chat_id=? AND prestamista=? GROUP BY deudor ORDER BY s DESC");
    $stmt->bind_param("is", $chat_id, $prestamista);
    $stmt->execute(); $res = $stmt->get_result();

    $lines = [];
    while($r = $res->fetch_assoc()){
        $lines[] = "• {$r['deudor']}: $ ".p_money($r['s']);
    }
    $stmt->close(); $conn->close();

    $msg = "👤 *Prestamista:* {$prestamista}\n".
           "🧮 *Préstamos:* {$n}\n".
           "💵 *Total prestado:* $ ".p_money($s)."\n";
    if ($lines) $msg .= "\n👥 *Por prestatario:*\n".implode("\n",$lines);
    sendMessage($chat_id, $msg);
}

/** Resumen global agrupado por prestamista */
function p_show_global_by_prestamista($chat_id): void {
    $conn = db(); if(!$conn){ sendMessage($chat_id,"❌ Error de DB."); return; }
    $stmt = $conn->prepare("SELECT prestamista, COUNT(*) n, COALESCE(SUM(monto),0) s FROM prestamos WHERE chat_id=? GROUP BY prestamista ORDER BY s DESC");
    $stmt->bind_param("i", $chat_id);
    $stmt->execute(); $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        sendMessage($chat_id, "No hay préstamos registrados.");
        $stmt->close(); $conn->close();
        return;
    }

    $lines = [];
    $gran_total = 0;
    while($r = $res->fetch_assoc()){
        $gran_total += (float)$r['s'];
        $lines[] = "• {$r['prestamista']} — $ ".p_money($r['s'])." ({$r['n']} movs)";
    }
    $stmt->close(); $conn->close();

    $msg = "📊 *Resumen por prestamista*\n".
           implode("\n", $lines).
           "\n\n💰 *Total general:* $ ".p_money($gran_total);
    sendMessage($chat_id, $msg);
}

/** Listado completo paginado */
function p_show_all_paginated($chat_id, int $page=0, int $limit=10): void {
    $offset = max(0, $page) * $limit;

    $conn = db(); if(!$conn){ sendMessage($chat_id,"❌ Error de DB."); return; }

    // Total rows y suma total
    $stmt = $conn->prepare("SELECT COUNT(*), COALESCE(SUM(monto),0) FROM prestamos WHERE chat_id=?");
    $stmt->bind_param("i", $chat_id);
    $stmt->execute(); $stmt->bind_result($total_rows,$sum_total); $stmt->fetch(); $stmt->close();

    if ($total_rows == 0) {
        sendMessage($chat_id, "No hay préstamos registrados.");
        $conn->close(); return;
    }

    // Página
    $stmt = $conn->prepare("SELECT id, deudor, prestamista, monto, fecha FROM prestamos WHERE chat_id=? ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("iii", $chat_id, $limit, $offset);
    $stmt->execute(); $res = $stmt->get_result();

    $rows = [];
    while($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close(); $conn->close();

    $from = $offset + 1;
    $to   = min($offset + $limit, $total_rows);
    $msg  = "📄 *Préstamos* ($from–$to de $total_rows)\n";

    foreach ($rows as $i=>$r) {
        $n = $from + $i;
        $msg .= "\n*#{$n}*  $".p_money($r['monto'])." — {$r['fecha']}\n";
        $msg .= "👥 {$r['prestamista']} → 👤 {$r['deudor']}\n";
        $msg .= "_ID: {$r['id']}_";
    }

    $msg .= "\n\n💰 *Suma total:* $ ".p_money($sum_total);

    // Botonera de paginación
    $kb = ["inline_keyboard"=>[]];
    $buttons = [];
    if ($page > 0) {
        $buttons[] = ["text"=>"⬅️ Anterior","callback_data"=>"p_all_page_".($page-1)];
    }
    if ($to < $total_rows) {
        $buttons[] = ["text"=>"Siguiente ➡️","callback_data"=>"p_all_page_".($page+1)];
    }
    if (!empty($buttons)) $kb["inline_keyboard"][] = $buttons;

    sendMessage($chat_id, $msg, !empty($buttons) ? $kb : null);
}

/** Exportar CSV de toda la tabla y enviarlo al chat */
function p_export_csv($chat_id): void {
    $conn = db(); if(!$conn){ sendMessage($chat_id,"❌ Error de DB."); return; }

    $stmt = $conn->prepare("SELECT id, deudor, prestamista, monto, fecha, imagen, created_at FROM prestamos WHERE chat_id=? ORDER BY id DESC");
    $stmt->bind_param("i", $chat_id);
    $stmt->execute(); $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        sendMessage($chat_id, "No hay préstamos para exportar.");
        $stmt->close(); $conn->close(); return;
    }

    $dir = __DIR__ . "/uploads/";
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $filename = "prestamos_{$chat_id}_".date('Ymd_His').".csv";
    $path = $dir . $filename;

    $fp = fopen($path, 'w');
    // Encabezados
    fputcsv($fp, ['id','deudor','prestamista','monto','fecha','imagen','created_at'], ';');

    $sum = 0;
    while($r = $res->fetch_assoc()){
        $sum += (float)$r['monto'];
        fputcsv($fp, [
            $r['id'],
            $r['deudor'],
            $r['prestamista'],
            $r['monto'],
            $r['fecha'],
            $r['imagen'],
            $r['created_at'],
        ], ';');
    }
    fclose($fp);
    $stmt->close(); $conn->close();

    // Enviar a Telegram
    $ok = p_send_document($chat_id, $path, $filename, "CSV generado. Total: $ ".p_money($sum));
    if (!$ok) {
        sendMessage($chat_id, "⚠️ No pude enviar el CSV. Revisa permisos de cURL/hosting.");
    }
}
