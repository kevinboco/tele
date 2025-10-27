<?php
// flow_prestamos.php (versión extendida con comisión de intermediación)
// REQUIERE que la tabla `prestamos` tenga estas columnas:
//
// ALTER TABLE prestamos
// ADD COLUMN comision_gestor_nombre VARCHAR(100) NULL AFTER prestamista,
// ADD COLUMN comision_gestor_porcentaje DECIMAL(5,2) NULL AFTER comision_gestor_nombre,
// ADD COLUMN comision_base_monto BIGINT NULL AFTER comision_gestor_porcentaje,
// ADD COLUMN comision_origen_prestamista VARCHAR(100) NULL AFTER comision_base_monto,
// ADD COLUMN comision_origen_porcentaje DECIMAL(5,2) NULL AFTER comision_origen_prestamista;
//
// Esta versión soporta:
// - Caso normal: tu plata → sin comisión, todo como antes
// - Caso “plata de otra persona”: prestamista real + tu comisión
//
// Flujo nuevo después de monto:
//   p_comision_pregunta  -> comi_no | comi_si
//   si comi_no -> vamos directo a fecha
//   si comi_si -> p_comision_origen
//              -> p_comision_porcentaje_origen
//              -> p_comision_porcentaje
//              -> p_comision_nombre_gestor
//              -> fecha
//

require_once __DIR__.'/helpers.php';

/* ========= util ========= */
function norm_spaces(string $s): string {
    return trim(preg_replace('/\s+/u', ' ', $s ?? ''));
}
function nicecase(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    return preg_replace_callback('/\b(\p{L})(\p{L}*)/u', function($m){
        return mb_strtoupper($m[1], 'UTF-8') . $m[2];
    }, $s);
}

/* ========= acceso tablas simples ========= */
function fetch_names_admin(int $chat_id, string $which): array {
    // $which: 'deudor' | 'prestamista'
    $table = $which === 'deudor' ? 'deudores_admin' : 'prestamistas_admin';
    $conn = db(); if (!$conn) return [];
    $stmt = $conn->prepare("SELECT nombre FROM $table WHERE owner_chat_id=? ORDER BY nombre ASC");
    if (!$stmt) { $conn->close(); return []; }
    $stmt->bind_param("i", $chat_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $n = norm_spaces($row['nombre']);
        if ($n !== '') $out[] = $n;
    }
    $stmt->close(); $conn->close();
    return $out;
}

function upsert_name_admin(int $chat_id, string $which, string $nombre): void {
    $table = $which === 'deudor' ? 'deudores_admin' : 'prestamistas_admin';
    $bonito = nicecase(norm_spaces($nombre));
    $conn = db(); if (!$conn) return;
    $stmt = $conn->prepare("INSERT IGNORE INTO $table (owner_chat_id, nombre) VALUES (?, ?)");
    if ($stmt) {
        $stmt->bind_param("is", $chat_id, $bonito);
        $stmt->execute();
        $stmt->close();
    }
    $conn->close();
}

/* ========= keyboards ========= */
function kbNombreLista(array $opts, string $tipoPaso): array {
    // $tipoPaso: 'p_deudor' | 'p_prestamista'
    $kb = ["inline_keyboard" => []];
    foreach ($opts as $i => $name) {
        $kb["inline_keyboard"][] = [
            ["text"=>$name, "callback_data"=>"pick_{$tipoPaso}_$i"]
        ];
    }
    $kb["inline_keyboard"][] = [
        ["text"=>"✍️ Escribir otro", "callback_data"=>"pick_{$tipoPaso}_otro"]
    ];
    return $kb;
}

function kbPrestamoFecha() {
    return [
        "inline_keyboard" => [
            [["text"=>"📅 Hoy","callback_data"=>"pfecha_hoy"]],
            [["text"=>"📆 Otra fecha","callback_data"=>"pfecha_otro"]],
        ]
    ];
}

function kbPrestamoMeses($anio) {
    $labels=[
        1=>"Enero",2=>"Febrero",3=>"Marzo",4=>"Abril",5=>"Mayo",6=>"Junio",
        7=>"Julio",8=>"Agosto",9=>"Septiembre",10=>"Octubre",11=>"Noviembre",12=>"Diciembre"
    ];
    $kb=["inline_keyboard"=>[]];
    for ($i=1;$i<=12;$i+=2) {
        $row=[];
        $row[]=["text"=>$labels[$i]." $anio","callback_data"=>"pmes_".$anio."_".str_pad($i,2,"0",STR_PAD_LEFT)];
        if ($i+1<=12) {
            $row[]=["text"=>$labels[$i+1]." $anio","callback_data"=>"pmes_".$anio."_".str_pad($i+1,2,"0",STR_PAD_LEFT)];
        }
        $kb["inline_keyboard"][]=$row;
    }
    return $kb;
}

/* === NUEVO: teclado para comisión sí/no === */
function kbComisionPregunta() {
    return [
        "inline_keyboard" => [
            [["text"=>"💼 No, es mi plata / sin comisión","callback_data"=>"comi_no"]],
            [["text"=>"🤝 Sí, yo cobro comisión","callback_data"=>"comi_si"]],
        ]
    ];
}

/* ========= entry ========= */
function prestamos_entrypoint($chat_id, $estado): void
{
    if (!empty($estado) && ($estado['flujo'] ?? '') === 'prestamos') {
        prestamos_resend_current_step($chat_id, $estado);
        return;
    }
    $estado = ["flujo"=>"prestamos","paso"=>"p_deudor"];
    // cargar listas desde tablas admin
    $estado['deudor_opts'] = fetch_names_admin((int)$chat_id, 'deudor');
    saveState($chat_id, $estado);

    if (!empty($estado['deudor_opts'])) {
        sendMessage(
            $chat_id,
            "👤 *¿A quién se le presta?* (elige o escribe)",
            kbNombreLista($estado['deudor_opts'], 'p_deudor')
        );
    } else {
        sendMessage($chat_id, "👤 *¿A quién se le presta?* (nombre completo)");
    }
}

/* ========= resend ========= */
function prestamos_resend_current_step($chat_id, $estado): void
{
    switch ($estado['paso'] ?? '') {

        case 'p_deudor':
            if (!empty($estado['deudor_opts'])) {
                sendMessage(
                    $chat_id,
                    "👤 *¿A quién se le presta?* (elige o escribe)",
                    kbNombreLista($estado['deudor_opts'], 'p_deudor')
                );
            } else {
                sendMessage($chat_id, "👤 *¿A quién se le presta?* (nombre)");
            }
            break;

        case 'p_deudor_manual':
            sendMessage($chat_id, "✍️ *Escribe el nombre* del deudor:");
            break;

        case 'p_prestamista':
            if (empty($estado['prestamista_opts'])) {
                $estado['prestamista_opts'] = fetch_names_admin((int)$chat_id, 'prestamista');
                saveState($chat_id, $estado);
            }
            if (!empty($estado['prestamista_opts'])) {
                sendMessage(
                    $chat_id,
                    "🧾 *¿Quién lo presta?* (elige o escribe)",
                    kbNombreLista($estado['prestamista_opts'], 'p_prestamista')
                );
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

        /* === NUEVO PASO: preguntar si hay comisión === */
        case 'p_comision_pregunta':
            sendMessage(
                $chat_id,
                "📌 *¿Tú vas a cobrar comisión por este préstamo aunque la plata sea de otra persona?*",
                kbComisionPregunta()
            );
            break;

        /* === SUBFLUJO COMISIÓN: pedir origen del capital === */
        case 'p_comision_origen':
            sendMessage(
                $chat_id,
                "🏦 *¿De quién es realmente la plata?* (escribe el nombre de quien puso el dinero)"
            );
            break;

        /* === SUBFLUJO COMISIÓN: % que cobra el dueño del capital === */
        case 'p_comision_porcentaje_origen':
            sendMessage(
                $chat_id,
                "📈 *¿Qué porcentaje cobra la persona que puso la plata?* (solo número, ej.: 8 para 8%)"
            );
            break;

        /* === SUBFLUJO COMISIÓN: tu % de comisión === */
        case 'p_comision_porcentaje':
            sendMessage(
                $chat_id,
                "💸 *¿Cuál es TU porcentaje de comisión?* (solo número, ej.: 2 para 2%)"
            );
            break;

        /* === SUBFLUJO COMISIÓN: tu nombre como gestor === */
        case 'p_comision_nombre_gestor':
            sendMessage(
                $chat_id,
                "✍️ Escribe tu nombre (quien cobra la comisión)."
            );
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

/* ========= callbacks ========= */
function prestamos_handle_callback($chat_id, &$estado, string $cb_data, ?string $cb_id=null): void
{
    if (($estado['flujo'] ?? '') !== 'prestamos') return;

    // Picks de deudor / prestamista
    if (preg_match('/^pick_(p_deudor|p_prestamista)_(\d+|otro)$/', $cb_data, $m)) {
        $tipo = $m[1]; $idx = $m[2];

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
                    // siguiente: prestamista
                    $estado['paso'] = 'p_prestamista';
                    $estado['prestamista_opts'] = fetch_names_admin((int)$chat_id, 'prestamista');
                    saveState($chat_id, $estado);
                    if (!empty($estado['prestamista_opts'])) {
                        sendMessage(
                            $chat_id,
                            "🧾 *¿Quién lo presta?* (elige o escribe)",
                            kbNombreLista($estado['prestamista_opts'], 'p_prestamista')
                        );
                    } else {
                        sendMessage($chat_id, "🧾 *¿Quién lo presta?* (nombre)");
                    }
                } else {
                    $estado['paso']='p_deudor_manual';
                    saveState($chat_id,$estado);
                    sendMessage($chat_id, "⚠️ Opción inválida. Escribe el nombre del deudor:");
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
                    $estado['paso']='p_prestamista_manual';
                    saveState($chat_id,$estado);
                    sendMessage($chat_id, "⚠️ Opción inválida. Escribe el nombre de quien presta:");
                }
            }
        }
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    // Fecha rápida / otra fecha
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

    /* === NUEVO: manejo de comisión === */
    if ($cb_data === 'comi_no') {
        // sin comisión -> limpiamos cualquier cosa previa
        unset($estado['comision_gestor_nombre']);
        unset($estado['comision_gestor_porcentaje']);
        unset($estado['comision_base_monto']);
        unset($estado['comision_origen_prestamista']);
        unset($estado['comision_origen_porcentaje']);

        $estado['paso'] = 'p_fecha';
        saveState($chat_id, $estado);
        sendMessage($chat_id, "📅 *Fecha del préstamo*:", kbPrestamoFecha());
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    if ($cb_data === 'comi_si') {
        // vamos a pedir detalles de comisión
        $estado['paso'] = 'p_comision_origen';
        saveState($chat_id, $estado);
        sendMessage(
            $chat_id,
            "🏦 *¿De quién es realmente la plata?* (escribe el nombre de quien puso el dinero)"
        );
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    if ($cb_id) answerCallbackQuery($cb_id);
}

/* ========= texto/foto ========= */
function prestamos_handle_text($chat_id, &$estado, string $text=null, $photo=null): void
{
    if (($estado['flujo'] ?? '') !== 'prestamos') return;

    switch ($estado['paso']) {

        case 'p_deudor':
        case 'p_deudor_manual': {
            $txt = norm_spaces($text ?? '');
            if ($txt==='') {
                sendMessage($chat_id, "⚠️ Escribe el *nombre* del deudor.");
                return;
            }
            $bonito = nicecase($txt);
            $estado['p_deudor'] = $bonito;
            upsert_name_admin((int)$chat_id, 'deudor', $bonito);

            $estado['paso'] = 'p_prestamista';
            $estado['prestamista_opts'] = fetch_names_admin((int)$chat_id, 'prestamista');
            saveState($chat_id, $estado);

            if (!empty($estado['prestamista_opts'])) {
                sendMessage(
                    $chat_id,
                    "🧾 *¿Quién lo presta?* (elige o escribe)",
                    kbNombreLista($estado['prestamista_opts'], 'p_prestamista')
                );
            } else {
                sendMessage($chat_id, "🧾 *¿Quién lo presta?* (nombre)");
            }
            return;
        }

        case 'p_prestamista':
        case 'p_prestamista_manual': {
            $txt = norm_spaces($text ?? '');
            if ($txt==='') {
                sendMessage($chat_id, "⚠️ Escribe el *nombre* de quien presta.");
                return;
            }
            $bonito = nicecase($txt);
            $estado['p_prestamista'] = $bonito;
            upsert_name_admin((int)$chat_id, 'prestamista', $bonito);

            $estado['paso'] = 'p_monto';
            saveState($chat_id, $estado);
            sendMessage($chat_id, "💵 *¿Cuánto se prestó?* (solo número, ej.: 1500000)");
            return;
        }

        case 'p_monto': {
            $raw = preg_replace('/[^\d]/','', (string)$text);
            if ($raw==='' || !ctype_digit($raw)) {
                sendMessage($chat_id, "⚠️ Ingresa solo *números* (ej.: 1500000).");
                return;
            }
            $estado['p_monto'] = (int)$raw;

            // Después del monto preguntar si hay comisión
            $estado['paso']    = 'p_comision_pregunta';
            saveState($chat_id, $estado);
            sendMessage(
                $chat_id,
                "📌 *¿Tú vas a cobrar comisión por este préstamo aunque la plata sea de otra persona?*",
                kbComisionPregunta()
            );
            return;
        }

        /* === SUBFLUJO COMISIÓN === */

        // 1. Quién puso realmente la plata
        case 'p_comision_origen': {
            $origen = norm_spaces($text ?? '');
            if ($origen==='') {
                sendMessage($chat_id, "⚠️ Escribe el nombre de quien puso la plata.");
                return;
            }

            $bonitoOrigen = nicecase($origen);

            // esto: el dueño real del capital pasa a ser el prestamista oficial del préstamo
            $estado['comision_origen_prestamista'] = $bonitoOrigen;
            $estado['p_prestamista'] = $bonitoOrigen;

            // y lo registramos también en la tabla de prestamistas conocidos
            upsert_name_admin((int)$chat_id, 'prestamista', $bonitoOrigen);

            $estado['paso'] = 'p_comision_porcentaje';
            saveState($chat_id, $estado);
            sendMessage($chat_id, "📈 *¿Qué porcentaje cobra la persona que puso la plata?* (solo número, ej.: 8 para 8%)");
            return;

        }

        // 2. % del dueño del capital (ej.: Selene cobra 8%)
        case 'p_comision_porcentaje_origen': {
            $raw = preg_replace('/[^\d.]/','', (string)$text);
            if ($raw==='' || !is_numeric($raw)) {
                sendMessage($chat_id, "⚠️ Ingresa solo número (ej.: 8 para 8%).");
                return;
            }

            $estado['comision_origen_porcentaje'] = (float)$raw;

            // siguiente: tu % de comisión
            $estado['paso'] = 'p_comision_porcentaje';
            saveState($chat_id, $estado);
            sendMessage(
                $chat_id,
                "💸 *¿Cuál es TU porcentaje de comisión?* (solo número, ej.: 2 para 2%)"
            );
            return;
        }

        // 3. % de comisión tuya (ej.: tú cobras 2%)
        case 'p_comision_porcentaje': {
            $raw = preg_replace('/[^\d.]/','', (string)$text);
            if ($raw==='' || !is_numeric($raw)) {
                sendMessage($chat_id, "⚠️ Ingresa solo número (ej.: 2 para 2%).");
                return;
            }

            $estado['comision_gestor_porcentaje'] = (float)$raw;
            // base = monto total prestado
            $estado['comision_base_monto'] = (int)($estado['p_monto'] ?? 0);

            // siguiente: tu nombre (quien cobra la comisión)
            $estado['paso'] = 'p_comision_nombre_gestor';
            saveState($chat_id, $estado);
            sendMessage(
                $chat_id,
                "✍️ Escribe tu nombre (quien cobra la comisión)."
            );
            return;
        }

        // 4. Tu nombre para esa comisión
        case 'p_comision_nombre_gestor': {
            $gestor = norm_spaces($text ?? '');
            if ($gestor==='') {
                sendMessage($chat_id, "⚠️ Escribe tu nombre.");
                return;
            }

            $estado['comision_gestor_nombre'] = nicecase($gestor);

            // listo, seguimos con la fecha
            $estado['paso'] = 'p_fecha';
            saveState($chat_id, $estado);
            sendMessage($chat_id, "📅 *Fecha del préstamo*:", kbPrestamoFecha());
            return;
        }

        case 'p_fecha_dia': {
            $anio=(int)($estado['p_anio'] ?? date('Y'));
            $mes =(int)($estado['p_mes']  ?? date('m'));
            if (!preg_match('/^\d{1,2}$/', (string)$text)) {
                $max=cal_days_in_month(CAL_GREGORIAN,$mes,$anio);
                sendMessage($chat_id, "⚠️ Debe ser un número entre 1 y $max. Escribe el *día*:");
                return;
            }
            $dia=(int)$text;
            $max=cal_days_in_month(CAL_GREGORIAN,$mes,$anio);
            if ($dia<1 || $dia>$max) {
                sendMessage($chat_id, "⚠️ Día fuera de rango (1–$max).");
                return;
            }
            $estado['p_fecha'] = sprintf('%04d-%02d-%02d',$anio,$mes,$dia);
            $estado['paso']    = 'p_foto';
            saveState($chat_id, $estado);
            sendMessage($chat_id, "📸 *Envía la captura* de la transferencia.");
            return;
        }

        case 'p_foto': {

            file_put_contents(
                "debug.txt",
                "[PRESTAMOS][{$chat_id}] paso=foto; hasPhoto=".(!empty($photo))."; hasDoc=".(isset($GLOBALS['update']['message']['document'])?'1':'0')."\n",
                FILE_APPEND
            );

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
            if (!$file_id) {
                sendMessage($chat_id, "⚠️ Envía una *imagen* válida (foto o archivo de imagen).");
                return;
            }

            global $TOKEN;
            $info = @json_decode(@file_get_contents(
                "https://api.telegram.org/bot{$TOKEN}/getFile?file_id=".urlencode($file_id)
            ), true);

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
                $ch=curl_init($fileUrl);
                $fp=fopen($destino,'wb');
                curl_setopt_array($ch,[
                    CURLOPT_FILE=>$fp,
                    CURLOPT_FOLLOWLOCATION=>true,
                    CURLOPT_TIMEOUT=>30
                ]);
                $ok=curl_exec($ch);
                $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
                curl_close($ch);
                fclose($fp);
                if ($code!==200) $ok=false;
            } else {
                $data=@file_get_contents($fileUrl);
                if ($data!==false) {
                    $ok=(file_put_contents($destino,$data)!==false);
                }
            }
            if (!$ok || !file_exists($destino)) {
                sendMessage($chat_id,"❌ No pude guardar la imagen. Reenvíala, por favor.");
                return;
            }

            // Guardar préstamo
            $conn = db();
            if (!$conn) {
                sendMessage($chat_id, "❌ Error de conexión a la base de datos.");
                return;
            }

            $deudor = nicecase(norm_spaces($estado['p_deudor'] ?? ''));
            $prestamista = nicecase(norm_spaces($estado['p_prestamista'] ?? ''));

            // asegurar catálogo
            upsert_name_admin((int)$chat_id, 'deudor', $deudor);
            upsert_name_admin((int)$chat_id, 'prestamista', $prestamista);

            // valores de comisión si existen
            $comi_nombre       = $estado['comision_gestor_nombre']       ?? null;
            $comi_pct          = $estado['comision_gestor_porcentaje']   ?? null;
            $comi_base         = $estado['comision_base_monto']          ?? null;
            $comi_origen       = $estado['comision_origen_prestamista']  ?? null;
            $comi_origen_pct   = $estado['comision_origen_porcentaje']   ?? null;

            $stmt = $conn->prepare("
                INSERT INTO prestamos
                (chat_id,
                 deudor,
                 prestamista,
                 monto,
                 fecha,
                 imagen,
                 comision_gestor_nombre,
                 comision_gestor_porcentaje,
                 comision_base_monto,
                 comision_origen_prestamista,
                 comision_origen_porcentaje,
                 created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            if (!$stmt) {
                sendMessage($chat_id, "❌ Error preparando la inserción.");
                $conn->close();
                return;
            }

            // bind_param:
            // i = int
            // s = string
            // d = double/float
            // orden debe coincidir con la query
            $stmt->bind_param(
                "ississsdisd",
                $chat_id,
                $deudor,
                $prestamista,
                $estado['p_monto'],
                $estado['p_fecha'],
                $nombreArchivo,
                $comi_nombre,
                $comi_pct,
                $comi_base,
                $comi_origen,
                $comi_origen_pct
            );

            if ($stmt->execute()) {
                $montoFmt = number_format($estado['p_monto'], 0, ',', '.');

                $extraComi = "";
                if (!empty($comi_nombre) && $comi_pct !== null) {
                    $baseFmt = number_format($comi_base ?? 0, 0, ',', '.');
                    $extraComi =
                        "\n🏷 Comisión / Intermediación" .
                        "\n   👤 Quien cobra comisión: {$comi_nombre}".
                        "\n   💸 % Comisión propia: {$comi_pct}%".
                        "\n   💵 Base comisión: $ $baseFmt";

                    if (!empty($comi_origen)) {
                        $extraComi .=
                        "\n   🏦 Dueño del capital: {$comi_origen}";
                        if ($comi_origen_pct !== null) {
                            $extraComi .=
                            "\n   📈 % Dueño del capital: {$comi_origen_pct}%";
                        }
                    }
                }

                sendMessage(
                    $chat_id,
                    "✅ *Préstamo registrado*\n".
                    "👤 Deudor: {$deudor}\n".
                    "🧾 Prestamista (capital): {$prestamista}\n".
                    "💵 Monto total: $ $montoFmt\n".
                    "📅 Fecha: {$estado['p_fecha']}".
                    $extraComi
                );
            } else {
                sendMessage($chat_id, "❌ Error al guardar: ".$conn->error);
            }
            $stmt->close(); $conn->close();

            clearState($chat_id);
            return;
        }

        default:
            sendMessage($chat_id,
                "❌ Usa */prestamos* para iniciar el flujo. */cancel* para reiniciar."
            );
            clearState($chat_id);
            return;
    }
}
