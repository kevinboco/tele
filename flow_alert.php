// router.php - AGREGAR al inicio con los otros requires:
require_once __DIR__.'/helpers.php';
require_once __DIR__.'/flow_agg.php';
require_once __DIR__.'/flow_manual.php';
require_once __DIR__.'/flow_prestamos.php';
require_once __DIR__.'/flow_p.php';
require_once __DIR__.'/flow_alert.php';   // <-- NUEVO FLUJO DE ALERTAS

// En la función routeUpdate(), AGREGAR:

// A. En los comandos globales (/start):
if ($text === "/start") {
    $opts = [
        "inline_keyboard" => [
            [["text"=>"➕ Agregar viaje (asistido)", "callback_data"=>"cmd_agg"]],
            [["text"=>"📝 Registrar viaje (manual)",  "callback_data"=>"cmd_manual"]],
            [["text"=>"💳 Registrar préstamo",        "callback_data"=>"cmd_prestamos"]],
            [["text"=>"📈 Reportes de préstamos",     "callback_data"=>"cmd_p"]],
            [["text"=>"🚨 Alertas de presupuesto",    "callback_data"=>"cmd_alert"]], // <-- NUEVO
        ]
    ];
    sendMessage(
        $chat_id,
        "👋 ¡Hola! Soy el bot de la asociación.\n\n" .
        "• */agg* para flujo asistido\n" .
        "• */manual* para registrar viaje manual\n" .
        "• */prestamos* para registrar un préstamo\n" .
        "• */p* para reportes y ver/descargar toda la tabla\n" .
        "• */alert* para sistema de alertas por presupuesto\n" . // <-- NUEVO
        "• */cancel* para reiniciar",
        $opts
    );
    $release(); return;
}

// B. En los atajos desde /start:
if ($cb_data === "cmd_alert") { $release(); alert_entrypoint($chat_id, $estado); return; }

// C. En entrada por comando:
if ($text === "/alert") { $release(); alert_entrypoint($chat_id, $estado); return; }

// D. En ruteo de callbacks:
if ($cb_data && $flujo === 'alert') { alert_handle_callback($chat_id, $estado, $cb_data, $cb_id); $release(); return; }

// E. En ruteo de mensajes de texto:
if ($flujo === 'alert') { alert_handle_text($chat_id, $estado, $text, $photo); $release(); return; }

// F. Actualizar mensaje fuera de flujo:
if ($chat_id && !$cb_data) {
    sendMessage($chat_id, "❌ Elige un flujo: */agg*, */manual*, */prestamos*, */p* o */alert*. También */cancel* para reiniciar.");
}