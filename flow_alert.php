<?php
// flow_alert.php - Flujo de alertas y mensajes

function alert_entrypoint($chat_id, $estado) {
    sendMessage($chat_id, "🚨 *MODO ALERTAS*\n\n👋 ¡Hola! Dime, ¿qué entendiste sobre las alertas?");
    
    $estado = [
        "flujo" => "alert",
        "paso" => "alert_pregunta"
    ];
    saveState($chat_id, $estado);
}

function alert_resend_current_step($chat_id, $estado) {
    sendMessage($chat_id, "🚨 Continuamos con alertas. ¿Qué entendiste sobre las alertas?\n(Escribe tu respuesta o /cancel para salir)");
}

function alert_handle_callback($chat_id, &$estado, $cb_data, $cb_id = null) {
    if (($estado["flujo"] ?? "") !== "alert") return;
    
    // Manejar botones si necesitas en el futuro
    if ($cb_id) answerCallbackQuery($cb_id);
}

function alert_handle_text($chat_id, &$estado, $text, $photo) {
    if (($estado["flujo"] ?? "") !== "alert") return;

    switch ($estado["paso"]) {
        case "alert_pregunta":
            $respuesta = trim($text);
            
            if (empty($respuesta)) {
                sendMessage($chat_id, "⚠️ Por favor, escribe lo que entendiste sobre las alertas.");
                break;
            }
            
            // Procesar la respuesta
            sendMessage($chat_id, "✅ *Excelente comprensión!*\n\nEntendiste: *$respuesta*\n\n🚨 Las alertas te permitirán recibir notificaciones importantes.\n\nUsa /alert para otra prueba o /cancel para salir.");
            
            // Opcional: Guardar en BD si necesitas
            // $conn = db();
            // if ($conn) {
            //     $stmt = $conn->prepare("INSERT INTO alert_logs (chat_id, respuesta) VALUES (?, ?)");
            //     $stmt->bind_param("is", $chat_id, $respuesta);
            //     $stmt->execute();
            //     $stmt->close();
            //     $conn->close();
            // }
            
            clearState($chat_id);
            break;
            
        default:
            sendMessage($chat_id, "⚠️ Algo salió mal. Usa /cancel y luego /alert para empezar.");
            clearState($chat_id);
            break;
    }
}