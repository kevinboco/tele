<?php
// router.ph
require_once __DIR__.'/helpers.php';
require_once __DIR__.'/flow_agg.php';
require_once __DIR__.'/flow_manual.php';
require_once __DIR__.'/flow_prestamos.php';
require_once __DIR__.'/flow_p.php';
require_once __DIR__.'/flow_alert.php';   // <-- NUEVO FLUJO ALERT

function routeUpdate(array $update): void
{
    // Exponer el update completo
    $GLOBALS['update'] = $update;

    // Campos base del update
    $chat_id   = $update["message"]["chat"]["id"]
        ?? ($update["callback_query"]["message"]["chat"]["id"] ?? null);
    $text      = trim($update["message"]["text"] ?? "");
    $photo     = $update["message"]["photo"] ?? null;
    $cb_data   = $update["callback_query"]["data"] ?? null;
    $cb_id     = $update["callback_query"]["id"] ?? null;
    $update_id = $update['update_id'] ?? null;

    // Mutex + deduplicación por chat
    [$lock, $release] = withMutex($chat_id);
    dedupe($chat_id, $update_id);

    // Comandos globales
    if ($text === "/cancel" || $text === "/reset") {
        clearState($chat_id);
        @unlink(__DIR__ . "/last_update_" . $chat_id . ".txt");
        sendMessage($chat_id, "🧹 Listo. Se canceló el flujo y limpié tu estado. Usa /agg, /manual, /prestamos, /p o /alert para empezar.");
        $release(); return;
    }

    if ($text === "/start") {
        $opts = [
            "inline_keyboard" => [
                [["text"=>"➕ Agregar viaje (asistido)", "callback_data"=>"cmd_agg"]],
                [["text"=>"📝 Registrar viaje (manual)",  "callback_data"=>"cmd_manual"]],
                [["text"=>"💳 Registrar préstamo",        "callback_data"=>"cmd_prestamos"]],
                [["text"=>"📈 Reportes de préstamos",     "callback_data"=>"cmd_p"]],
                [["text"=>"🚨 Prueba Alertas",           "callback_data"=>"cmd_alert"]], // <-- NUEVO BOTÓN
            ]
        ];
        sendMessage(
            $chat_id,
            "👋 ¡Hola! Soy el bot de la asociación.\n\n" .
            "• */agg* para flujo asistido\n" .
            "• */manual* para registrar viaje manual\n" .
            "• */prestamos* para registrar un préstamo\n" .
            "• */p* para reportes y ver/descargar toda la tabla\n" .
            "• */alert* para probar el nuevo flujo de alertas\n" . // <-- NUEVA OPCIÓN
            "• */cancel* para reiniciar",
            $opts
        );
        $release(); return;
    }

    // Cargar estado actual
    $estado = loadState($chat_id);
    $flujo  = $estado['flujo'] ?? null;

    // Atajos desde /start (botones principales)
    if ($cb_data === "cmd_agg")       { $release(); agg_entrypoint($chat_id, $estado);        return; }
    if ($cb_data === "cmd_manual")    { $release(); manual_entrypoint($chat_id, $estado);     return; }
    if ($cb_data === "cmd_prestamos") { $release(); prestamos_entrypoint($chat_id, $estado);  return; }
    if ($cb_data === "cmd_p")         { $release(); p_entrypoint($chat_id, $estado);          return; }
    if ($cb_data === "cmd_alert")     { $release(); alert_entrypoint($chat_id, $estado);      return; } // <-- NUEVO

    // Entrada explícita por comando
    if ($text === "/agg")       { $release(); agg_entrypoint($chat_id, $estado);       return; }
    if ($text === "/manual")    { $release(); manual_entrypoint($chat_id, $estado);    return; }
    if ($text === "/prestamos") { $release(); prestamos_entrypoint($chat_id, $estado); return; }
    if ($text === "/p")         { $release(); p_entrypoint($chat_id, $estado);         return; }
    if ($text === "/alert")     { $release(); alert_entrypoint($chat_id, $estado);     return; } // <-- NUEVO

    // Callbacks → ruteo por flujo activo
    if ($cb_data && $flujo === 'agg')       { agg_handle_callback($chat_id, $estado, $cb_data, $cb_id);       $release(); return; }
    if ($cb_data && $flujo === 'manual')    { manual_handle_callback($chat_id, $estado, $cb_data, $cb_id);    $release(); return; }
    if ($cb_data && $flujo === 'prestamos') { prestamos_handle_callback($chat_id, $estado, $cb_data, $cb_id); $release(); return; }
    if ($cb_data && $flujo === 'p')         { p_handle_callback($chat_id, $estado, $cb_data, $cb_id);         $release(); return; }
    if ($cb_data && $flujo === 'alert')     { alert_handle_callback($chat_id, $estado, $cb_data, $cb_id);     $release(); return; } // <-- NUEVO
    if ($cb_id) answerCallbackQuery($cb_id); // limpia el "cargando" si ningún flujo lo manejó

    // Mensajes de texto/foto → ruteo por flujo activo
    if (!empty($estado)) {
        if ($flujo === 'agg')       { agg_handle_text($chat_id, $estado, $text, $photo);       $release(); return; }
        if ($flujo === 'manual')    { manual_handle_text($chat_id, $estado, $text, $photo);    $release(); return; }
        if ($flujo === 'prestamos') { prestamos_handle_text($chat_id, $estado, $text, $photo); $release(); return; }
        if ($flujo === 'p')         { p_handle_text($chat_id, $estado, $text, $photo);         $release(); return; }
        if ($flujo === 'alert')     { alert_handle_text($chat_id, $estado, $text, $photo);     $release(); return; } // <-- NUEVO
    }

    // Fuera de cualquier flujo
    if ($chat_id && !$cb_data) {
        sendMessage($chat_id, "❌ Elige un flujo: */agg*, */manual*, */prestamos*, */p* o */alert*. También */cancel* para reiniciar.");
    }
    $release();
}