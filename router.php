<?php
// router.php
require_once __DIR__.'/helpers.php';
require_once __DIR__.'/flow_agg.php';
require_once __DIR__.'/flow_manual.php';

function routeUpdate($update) {
    $chat_id = $update["message"]["chat"]["id"] 
        ?? ($update["callback_query"]["message"]["chat"]["id"] ?? null);
    $text    = trim($update["message"]["text"] ?? "");
    $photo   = $update["message"]["photo"] ?? null;
    $cb_data = $update["callback_query"]["data"] ?? null;
    $cb_id   = $update["callback_query"]["id"] ?? null;
    $update_id = $update['update_id'] ?? null;

    // Mutex + Dedupe
    [$lock, $release] = withMutex($chat_id);
    dedupe($chat_id, $update_id);

    // Comandos globales
    if ($text === "/cancel" || $text === "/reset") {
        clearState($chat_id);
        @unlink(__DIR__ . "/last_update_" . $chat_id . ".txt");
        sendMessage($chat_id, "🧹 Listo. Se canceló el flujo y limpié tu estado. Usa /agg o /manual para empezar de nuevo.");
        $release(); return;
    }
    if ($text === "/start") {
        $opts = [
            "inline_keyboard" => [
                [[ "text"=>"➕ Agregar viaje (asistido)", "callback_data"=>"cmd_agg" ]],
                [[ "text"=>"📝 Registrar viaje (manual)", "callback_data"=>"cmd_manual" ]],
            ]
        ];
        sendMessage($chat_id, "👋 ¡Hola! Soy el bot de viajes.\n\n• Usa */agg* para flujo asistido.\n• Usa */manual* para registrar *nombre, ruta y fecha*.\n• Usa */cancel* para reiniciar.", $opts);
        $release(); return;
    }

    // Estado actual para decidir a qué flujo enviar
    $estado = loadState($chat_id);
    $flujo  = $estado['flujo'] ?? null;

    // Atajos desde /start
    if ($cb_data === "cmd_agg")   { $release(); return agg_entrypoint($chat_id, $estado); }
    if ($cb_data === "cmd_manual"){ $release(); return manual_entrypoint($chat_id, $estado); }

    // Entrada explícita a cada flujo
    if ($text === "/agg")   { $release(); return agg_entrypoint($chat_id, $estado); }
    if ($text === "/manual"){ $release(); return manual_entrypoint($chat_id, $estado); }

    // Callbacks: rutea por flujo en curso
    if ($cb_data && $flujo === 'agg')    { agg_handle_callback($chat_id, $estado, $cb_data, $cb_id);   saveState($chat_id, $estado); $release(); return; }
    if ($cb_data && $flujo === 'manual') { manual_handle_callback($chat_id, $estado, $cb_data, $cb_id); saveState($chat_id, $estado); $release(); return; }
    if ($cb_id) answerCallbackQuery($cb_id);

    // Mensajes de texto: rutea por flujo
    if (!empty($estado)) {
        if ($flujo === 'agg')    { agg_handle_text($chat_id, $estado, $text, $photo);   saveState($chat_id, $estado); $release(); return; }
        if ($flujo === 'manual') { manual_handle_text($chat_id, $estado, $text, $photo); saveState($chat_id, $estado); $release(); return; }
    }

    // Fuera de flujo
    if ($chat_id && !$cb_data) {
        sendMessage($chat_id, "❌ Debes usar */agg* para viaje asistido o */manual* para viaje manual. También */cancel* para reiniciar.");
    }
    $release();
}
