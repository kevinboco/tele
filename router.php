<?php
// router.php
require_once __DIR__.'/helpers.php';
require_once __DIR__.'/flow_agg.php';
require_once __DIR__.'/flow_manual.php';
require_once __DIR__.'/flow_prestamos.php';

function routeUpdate(array $update): void
{
    // Deja disponible todo el update (para leer document vs photo en los flujos)
    $GLOBALS['update'] = $update;

    // Datos base del update
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
        sendMessage($chat_id, "🧹 Listo. Se canceló el flujo y limpié tu estado. Usa /agg, /manual o /prestamos para empezar.");
        $release(); return;
    }

    if ($text === "/start") {
        $opts = [
            "inline_keyboard" => [
                [["text"=>"➕ Agregar viaje (asistido)", "callback_data"=>"cmd_agg"]],
                [["text"=>"📝 Registrar viaje (manual)",  "callback_data"=>"cmd_manual"]],
                [["text"=>"💳 Registrar préstamo",        "callback_data"=>"cmd_prestamos"]],
            ]
        ];
        sendMessage(
            $chat_id,
            "👋 ¡Hola! Soy el bot de la asociación.\n\n" .
            "• */agg* para flujo asistido\n" .
            "• */manual* para registrar viaje manual\n" .
            "• */prestamos* para registrar un préstamo\n" .
            "• */cancel* para reiniciar",
            $opts
        );
        $release(); return;
    }

    // Carga de estado actual
    $estado = loadState($chat_id);
    $flujo  = $estado['flujo'] ?? null;

    // Atajos desde /start (botones)
    if ($cb_data === "cmd_agg")       { $release(); agg_entrypoint($chat_id, $estado);        return; }
    if ($cb_data === "cmd_manual")    { $release(); manual_entrypoint($chat_id, $estado);     return; }
    if ($cb_data === "cmd_prestamos") { $release(); prestamos_entrypoint($chat_id, $estado);  return; }

    // Entrada explícita por comando
    if ($text === "/agg")       { $release(); agg_entrypoint($chat_id, $estado);       return; }
    if ($text === "/manual")    { $release(); manual_entrypoint($chat_id, $estado);    return; }
    if ($text === "/prestamos") { $release(); prestamos_entrypoint($chat_id, $estado); return; }

    // Callbacks → ruteo por flujo activo
    if ($cb_data && $flujo === 'agg')       { agg_handle_callback($chat_id, $estado, $cb_data, $cb_id);       $release(); return; }
    if ($cb_data && $flujo === 'manual')    { manual_handle_callback($chat_id, $estado, $cb_data, $cb_id);    $release(); return; }
    if ($cb_data && $flujo === 'prestamos') { prestamos_handle_callback($chat_id, $estado, $cb_data, $cb_id); $release(); return; }
    if ($cb_id) answerCallbackQuery($cb_id);

    // Mensajes de texto/foto → ruteo por flujo activo
    if (!empty($estado)) {
        if ($flujo === 'agg')       { agg_handle_text($chat_id, $estado, $text, $photo);       $release(); return; }
        if ($flujo === 'manual')    { manual_handle_text($chat_id, $estado, $text, $photo);    $release(); return; }
        if ($flujo === 'prestamos') { prestamos_handle_text($chat_id, $estado, $text, $photo); $release(); return; }
    }

    // Fuera de cualquier flujo
    if ($chat_id && !$cb_data) {
        sendMessage($chat_id, "❌ Elige un flujo: */agg*, */manual* o */prestamos*. También */cancel* para reiniciar.");
    }
    $release();
}
