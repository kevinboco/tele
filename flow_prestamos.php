<?php
// flow_prestamos.php
require_once __DIR__.'/helpers.php';

/* ==========================
   Utilidades de nombres
========================== */
function norm_spaces(string $s): string {
    // trim + colapsar espacios internos
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    return $s;
}
function nicecase(string $s): string {
    // Capitaliza “tipo nombre” sin gritar (Juan Alberto Echeto)
    $s = mb_strtolower($s, 'UTF-8');
    return preg_replace_callback('/\b(\p{L})(\p{L}*)/u', function($m){
        return mb_strtoupper($m[1], 'UTF-8') . $m[2];
    }, $s);
}

/* ==========================
   Sugerencias desde la BD
========================== */
function fetchDistinctNames(int $chat_id, string $col, int $limit=40): array {
    $validCols = ['deudor','prestamista'];
    if (!in_array($col, $validCols, true)) return [];
    $conn = db();
    if (!$conn) return [];
    $stmt = $conn->prepare("SELECT DISTINCT $col AS nombre 
                            FROM prestamos 
                            WHERE chat_id = ? AND $col IS NOT NULL AND $col <> '' 
                            ORDER BY MAX(id) DESC LIMIT ?");
    // Si tu tabla no tiene id autoincrement, puedes cambiar el ORDER BY por MAX(created_at)
    if (!$stmt) { $conn->close(); return []; }
    $stmt->bind_param("ii", $chat_id, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $n = norm_spaces($row['nombre']);
        if ($n !== '') $out[] = $n;
    }
    $stmt->close();
    $conn->close();
    // Ordenar alfabético por claridad (ya vienen recientes primero por el ORDER BY, ajusta a gusto)
    sort($out, SORT_NATURAL|SORT_FLAG_CASE);
    return array_values(array_unique($out));
}

/* ====================================
   Keyboards (con paginación) por nombres
==================================== */
function kbNombreOpciones(array $opts, string $tipoPaso, int $page=0, int $perPage=6): array {
    // $tipoPaso: 'p_deudor' o 'p_prestamista' para prefijar callback
    $kb = ["inline_keyboard" => []];
    $total = count($opts);
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(0, min($pages-1, $page));
    $start = $page * $perPage;
    $slice = array_slice($opts, $start, $perPage);

    foreach ($slice as $i => $name) {
        // Guardaremos el índice real (start+i) y el flujo decidirá el array desde el estado
        $kb["inline_keyboard"][] = [
            ["text" => $name, "callback_data" => "pick_{$tipoPaso}_".($start+$i)]
        ];
    }

    // Fila: Escribir otro
    $kb["inline_keyboard"][] = [
        ["text" => "✍️ Escribir otro", "callback_data" => "pick_{$tipoPaso}_otro"]
    ];

    // Paginación si hace falta
    if ($pages > 1) {
        $prev = max(0, $page-1);
        $next = min($pages-1, $page+1);
        $kb["inline_keyboard"][] = [
            ["text"=>"⬅️ Prev","callback_data"=>"page_{$tipoPaso}_$prev"],
            ["text"=>"Página ".($page+1)."/$pages","callback_data"=>"noop"],
            ["text"=>"Next ➡️","callback_data"=>"page_{$tipoPaso}_$next"],
        ];
    }
    return $kb;
}

/** Keyboards propios (ya existentes) */
function kbPrestamoFecha() {
    return [
        "inline_keyboard" => [
            [["text"=>"📅 Hoy","callback_data"=>"pfecha_hoy"]],
            [["text"=>"📆 Otra fecha","callback_data"=>"pfecha_otro"]],
        ]
    ];
}
function kbPrestamoMeses($anio) {
    $labels = [
        1=>"Enero",2=>"Febrero",3=>"Marzo",4=>"Abril",
        5=>"Mayo",6=>"Junio",7=>"Julio",8=>"Agosto",
        9=>"Septiembre",10=>"Octubre",11=>"Noviembre",12=>"Diciembre"
    ];
    $kb=["inline_keyboard"=>[]];
    for ($i=1;$i<=12;$i+=2) {
        $row=[];
        $row[]=["text"=>$labels[$i]." $anio","callback_data"=>"pmes_".$anio."_".str_pad($i,2,"0",STR_PAD_LEFT)];
        if ($i+1<=12) $row[]=["text"=>$labels[$i+1]." $anio","callback_data"=>"pmes_".$anio."_".str_pad($i+1,2,"0",STR_PAD_LEFT)];
        $kb["inline_keyboard"][]=$row;
    }
    return $kb;
}

/* ==========================
   Entrypoint
========================== */
function prestamos_entrypoint($chat_id, $estado): void
{
    if (!empty($estado) && ($estado['flujo'] ?? '') === 'prestamos') {
        prestamos_resend_current_step($chat_id, $estado);
        return;
    }
    $estado = ["flujo"=>"prestamos","paso"=>"p_deudor"];
    // Pre-cargar sugerencias
    $estado['deudor_opts'] = fetchDistinctNames((int)$chat_id, 'deudor');
    $estado['deudor_page'] = 0;
    saveState($chat_id, $estado);

    // Si hay opciones previas, ofrecer lista + “Escribir otro”
    if (!empty($estado['deudor_opts'])) {
        sendMessage($chat_id, "👤 *¿A quién se le presta?* (elige o escribe)", kbNombreOpciones($estado['deudor_opts'], 'p_deudor', $estado['deudor_page']));
    } else {
        sendMessage($chat_id, "👤 *¿A quién se le presta?* (nombre completo)");
    }
}

/* ==========================
   Re-enviar paso actual
========================== */
function prestamos_resend_current_step($chat_id, $estado): void
{
    switch ($estado['paso'] ?? '') {
        case 'p_deudor':
            if (!empty($estado['deudor_opts'])) {
                $p = (int)($estado['deudor_page'] ?? 0);
                sendMessage($chat_id, "👤 *¿A quién se le presta?* (elige o escribe)", kbNombreOpciones($estado['deudor_opts'], 'p_deudor', $p));
            } else {
                sendMessage($chat_id, "👤 *¿A quién se le presta?* (nombre)");
            }
            break;

        case 'p_deudor_manual':
            sendMessage($chat_id, "✍️ *Escribe el nombre* del deudor:");
            break;

        case 'p_prestamista':
            if (empty($estado['prestamista_opts'])) {
                $estado['prestamista_opts'] = fetchDistinctNames((int)$chat_id, 'prestamista');
                $estado['prestamista_page'] = 0;
                saveState($chat_id, $estado);
            }
            if (!empty($estado['prestamista_opts'])) {
                $p = (int)($estado['prestamista_page'] ?? 0);
                sendMessage($chat_id, "🧾 *¿Quién lo presta?* (elige o escribe)", kbNombreOpciones($estado['prestamista_opts'], 'p_prestamista', $p));
            } else {
                sendMessage($chat_id, "🧾 *¿Quién lo presta?* (nombre)");
            }
            break;

        case 'p_prestamista_manual':
            sendMessage($chat_id, "✍️ *Escribe el nombre* de quien presta:");
            break;

        case 'p_monto':
            sendMessage($chat_id, "💵 *¿Cuánto se prestó?* (solo número, ej.: 1500000)");
            break;

        case 'p_fecha':
            sendMessage($chat_id, "📅 *Fecha del préstamo*:", kbPrestamoFecha());
            break;

        case 'p_fecha_mes':
            $anio = $estado['p_anio'] ?? date("Y");
            sendMessage($chat_id, "📆 Selecciona el *mes*:", kbPrestamoMeses($anio));
            break;

        case 'p_fecha_dia': {
            $anio=(int)($estado['p_anio'] ?? date('Y'));
            $mes =(int)($estado['p_mes']  ?? date('m'));
            $max=cal_days_in_month(CAL_GREGORIAN,$mes,$anio);
            sendMessage($chat_id, "✍️ Escribe el *día* (1–$max):");
            break;
        }

        case 'p_foto':
            sendMessage($chat_id, "📸 *Envía la captura* de la transferencia (foto o imagen).");
            break;

        default:
            sendMessage($chat_id, "Escribe /cancel para reiniciar.");
    }
}

/* ==========================
   Callbacks (botones)
========================== */
function prestamos_handle_callback($chat_id, &$estado, string $cb_data, ?string $cb_id=null): void
{
    if (($estado['flujo'] ?? '') !== 'prestamos') return;

    // Navegación de páginas para nombres
    if (preg_match('/^page_(p_deudor|p_prestamista)_(\d+)$/', $cb_data, $m)) {
        $tipo = $m[1];
        $page = (int)$m[2];
        if ($tipo === 'p_deudor') {
            $estado['deudor_page'] = $page;
            saveState($chat_id, $estado);
            sendMessage($chat_id, "👤 *¿A quién se le presta?*", kbNombreOpciones($estado['deudor_opts'] ?? [], 'p_deudor', $page));
        } else {
            $estado['prestamista_page'] = $page;
            saveState($chat_id, $estado);
            sendMessage($chat_id, "🧾 *¿Quién lo presta?*", kbNombreOpciones($estado['prestamista_opts'] ?? [], 'p_prestamista', $page));
        }
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    // Selección de nombre (por índice) o “otro”
    if (preg_match('/^pick_(p_deudor|p_prestamista)_(\d+|otro)$/', $cb_data, $m)) {
        $tipo = $m[1];
        $idx  = $m[2];

        if ($tipo === 'p_deudor') {
            if ($idx === 'otro') {
                $estado['paso'] = 'p_deudor_manual';
                saveState($chat_id, $estado);
                sendMessage($chat_id, "✍️ *Escribe el nombre* del deudor:");
            } else {
                $i = (int)$idx;
                $opts = $estado['deudor_opts'] ?? [];
                if (isset($opts[$i])) {
                    $estado['p_deudor'] = $opts[$i];
                    // Siguiente: prestamista
                    $estado['paso'] = 'p_prestamista';
                    // Pre-cargar sugerencias prestamista
                    $estado['prestamista_opts'] = fetchDistinctNames((int)$chat_id, 'prestamista');
                    $estado['prestamista_page'] = 0;
                    saveState($chat_id, $estado);
                    if (!empty($estado['prestamista_opts'])) {
                        sendMessage($chat_id, "🧾 *¿Quién lo presta?* (elige o escribe)", kbNombreOpciones($estado['prestamista_opts'], 'p_prestamista', 0));
                    } else {
                        sendMessage($chat_id, "🧾 *¿Quién lo presta?* (nombre)");
                    }
                } else {
                    sendMessage($chat_id, "⚠️ Opción no válida. Escribe el nombre del deudor:");
                    $estado['paso'] = 'p_deudor_manual';
                    saveState($chat_id, $estado);
                }
            }
        } else { // p_prestamista
            if ($idx === 'otro') {
                $estado['paso'] = 'p_prestamista_manual';
                saveState($chat_id, $estado);
                sendMessage($chat_id, "✍️ *Escribe el nombre* de quien presta:");
            } else {
                $i = (int)$idx;
                $opts = $estado['prestamista_opts'] ?? [];
                if (isset($opts[$i])) {
                    $estado['p_prestamista'] = $opts[$i];
                    $estado['paso'] = 'p_monto';
                    saveState($chat_id, $estado);
                    sendMessage($chat_id, "💵 *¿Cuánto se prestó?* (solo número, ej.: 1500000)");
                } else {
                    sendMessage($chat_id, "⚠️ Opción no válida. Escribe el nombre de quien presta:");
                    $estado['paso'] = 'p_prestamista_manual';
                    saveState($chat_id, $estado);
                }
            }
        }
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    // Fecha “hoy” u “otro”
    if ($cb_data === 'pfecha_hoy') {
        $estado['p_fecha'] = date('Y-m-d');
        $estado['paso']    = 'p_foto';
        saveState($chat_id, $estado);
        sendMessage($chat_id, "📸 *Envía la captura* de la transferencia.");
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    if ($cb_data === 'pfecha_otro') {
        $estado['p_anio'] = date('Y');
        $estado['paso']   = 'p_fecha_mes';
        saveState($chat_id, $estado);
        sendMessage($chat_id, "📆 Selecciona el *mes*:", kbPrestamoMeses($estado['p_anio']));
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    if (strpos($cb_data, 'pmes_') === 0) {
        $parts = explode('_', $cb_data);
        $estado['p_anio'] = $parts[1] ?? date('Y');
        $estado['p_mes']  = $parts[2] ?? date('m');
        $estado['paso']   = 'p_fecha_dia';
        saveState($chat_id, $estado);
        $anio=(int)$estado['p_anio']; $mes=(int)$estado['p_mes'];
        $max=cal_days_in_month(CAL_GREGORIAN,$mes,$anio);
        sendMessage($chat_id, "✍️ Escribe el *día* (1–$max):");
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    if ($cb_data === 'noop') {
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    if ($cb_id) answerCallbackQuery($cb_id);
}

/* ==========================
   Texto / Foto
========================== */
function prestamos_handle_text($chat_id, &$estado, string $text=null, $photo=null): void
{
    if (($estado['flujo'] ?? '') !== 'prestamos') return;

    switch ($estado['paso']) {
        case 'p_deudor':
        case 'p_deudor_manual':
            $txt = norm_spaces($text ?? '');
            if ($txt==='') { sendMessage($chat_id, "⚠️ Escribe el *nombre* del deudor."); return; }
            $estado['p_deudor'] = nicecase($txt);
            $estado['paso']     = 'p_prestamista';
            // precargar sugerencias prestamista
            $estado['prestamista_opts'] = fetchDistinctNames((int)$chat_id, 'prestamista');
            $estado['prestamista_page'] = 0;
            saveState($chat_id, $estado);
            if (!empty($estado['prestamista_opts'])) {
                sendMessage($chat_id, "🧾 *¿Quién lo presta?* (elige o escribe)", kbNombreOpciones($estado['prestamista_opts'], 'p_prestamista', 0));
            } else {
                sendMessage($chat_id, "🧾 *¿Quién lo presta?* (nombre)");
            }
            return;

        case 'p_prestamista':
        case 'p_prestamista_manual':
            $txt = norm_spaces($text ?? '');
            if ($txt==='') { sendMessage($chat_id, "⚠️ Escribe el *nombre* de quien presta."); return; }
            $estado['p_prestamista'] = nicecase($txt);
            $estado['paso']          = 'p_monto';
            saveState($chat_id, $estado);
            sendMessage($chat_id, "💵 *¿Cuánto se prestó?* (solo número, ej.: 1500000)");
            return;

        case 'p_monto':
            $raw = preg_replace('/[^\d]/','', (string)$text);
            if ($raw==='' || !ctype_digit($raw)) { sendMessage($chat_id, "⚠️ Ingresa solo *números* (ej.: 1500000)."); return; }
            $estado['p_monto'] = (int)$raw;
            $estado['paso']    = 'p_fecha';
            saveState($chat_id, $estado);
            sendMessage($chat_id, "📅 *Fecha del préstamo*:", kbPrestamoFecha());
            return;

        case 'p_fecha_dia':
            $anio=(int)($estado['p_anio'] ?? date('Y'));
            $mes =(int)($estado['p_mes']  ?? date('m'));
            if (!preg_match('/^\d{1,2}$/', (string)$text)) {
                $max=cal_days_in_month(CAL_GREGORIAN,$mes,$anio);
                sendMessage($chat_id, "⚠️ Debe ser un número entre 1 y $max. Escribe el *día*:");
                return;
            }
            $dia=(int)$text; $max=cal_days_in_month(CAL_GREGORIAN,$mes,$anio);
            if ($dia<1 || $dia>$max) { sendMessage($chat_id, "⚠️ Día fuera de rango (1–$max)."); return; }
            $estado['p_fecha'] = sprintf('%04d-%02d-%02d',$anio,$mes,$dia);
            $estado['paso']    = 'p_foto';
            saveState($chat_id, $estado);
            sendMessage($chat_id, "📸 *Envía la captura* de la transferencia.");
            return;

        case 'p_foto':
            // Aceptar photo o document image/*
            file_put_contents("debug.txt", "[PRESTAMOS][{$chat_id}] paso=foto; hasPhoto=".(!empty($photo))."; hasDoc=".(isset($GLOBALS['update']['message']['document'])?'1':'0')."\n", FILE_APPEND);

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
            if (!$file_id) { sendMessage($chat_id, "⚠️ Envía una *imagen* válida (foto o archivo de imagen)."); return; }

            global $TOKEN;
            $info = @json_decode(@file_get_contents("https://api.telegram.org/bot{$TOKEN}/getFile?file_id=".urlencode($file_id)), true);
            if (!$info || empty($info['ok']) || empty($info['result']['file_path'])) {
                sendMessage($chat_id, "❌ No pude obtener el archivo desde Telegram.");
                return;
            }
            $file_path = $info['result']['file_path'];
            $fileUrl   = "https://api.telegram.org/file/bot{$TOKEN}/{$file_path}";

            $uploads = __DIR__ . "/uploads/";
            if (!is_dir($uploads)) @mkdir($uploads, 0775, true);
            $nombreArchivo = time() . "_prestamo_" . basename($file_path);
            $destino       = $uploads . $nombreArchivo;

            $ok = false;
            if (function_exists('curl_init')) {
                $ch=curl_init($fileUrl); $fp=fopen($destino,'wb');
                curl_setopt_array($ch,[CURLOPT_FILE=>$fp,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>30]);
                $ok=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
                curl_close($ch); fclose($fp);
                if ($code!==200) $ok=false;
            } else {
                $data=@file_get_contents($fileUrl);
                if ($data!==false) $ok=(file_put_contents($destino,$data)!==false);
            }
            if (!$ok || !file_exists($destino)) { sendMessage($chat_id,"❌ No pude guardar la imagen. Reenvíala, por favor."); return; }

            // Insertar en BD
            $conn = db();
            if (!$conn) { sendMessage($chat_id, "❌ Error de conexión a la base de datos."); return; }

            // Normaliza/corrige nombres justo antes de guardar
            $deudor = nicecase(norm_spaces($estado['p_deudor'] ?? ''));
            $prestamista = nicecase(norm_spaces($estado['p_prestamista'] ?? ''));

            $stmt = $conn->prepare("INSERT INTO prestamos (chat_id, deudor, prestamista, monto, fecha, imagen, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            if (!$stmt) { sendMessage($chat_id, "❌ Error preparando la inserción."); $conn->close(); return; }

            $stmt->bind_param("ississ", $chat_id, $deudor, $prestamista, $estado['p_monto'], $estado['p_fecha'], $nombreArchivo);

            if ($stmt->execute()) {
                $montoFmt = number_format($estado['p_monto'], 0, ',', '.');
                sendMessage($chat_id,
                    "✅ *Préstamo registrado*\n".
                    "👤 Deudor: {$deudor}\n".
                    "🧾 Prestamista: {$prestamista}\n".
                    "💵 Monto: $ $montoFmt\n".
                    "📅 Fecha: {$estado['p_fecha']}"
                );
            } else {
                sendMessage($chat_id, "❌ Error al guardar: ".$conn->error);
            }
            $stmt->close(); $conn->close();

            clearState($chat_id);
            return;

        default:
            sendMessage($chat_id, "❌ Usa */prestamos* para iniciar el flujo. */cancel* para reiniciar.");
            clearState($chat_id);
            return;
    }
}
