if ($callback_query) {
    $chat_id = $callback_chat;

    if ($callback_query == "fecha_hoy") {
        $estado["fecha"] = date("Y-m-d");
        $estado["paso"] = "ruta";
        $mensaje = "🛣️ Selecciona o ingresa la *ruta*:";
        // Aquí generas botones con las rutas del usuario
    } elseif ($callback_query == "fecha_manual") {
        $estado["paso"] = "fecha_manual";
        $mensaje = "✍️ Escribe la fecha en formato YYYY-MM-DD:";
    }

    file_put_contents($estadoFile, json_encode($estado));

    // Enviar respuesta obligatoria a Telegram (para que no quede cargando)
    file_get_contents($apiURL."answerCallbackQuery?callback_query_id=".$update["callback_query"]["id"]);
}
