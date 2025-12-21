<?php
require_once __DIR__ . '/helpers.php';

/* =========================
   ENTRYPOINT
========================= */
function manual_entrypoint($chat_id, $estado) {

    if (!empty($estado) && ($estado['flujo'] ?? '') === 'manual') {
        return manual_handle_step($chat_id, $estado);
    }

    $estado = [
        'flujo' => 'manual',
        'paso'  => 'letra_conductor'
    ];
    saveState($chat_id, $estado);

    return manual_ask_driver_letter($chat_id);
}

/* =========================
   STEP ROUTER
========================= */
function manual_handle_step($chat_id, $estado) {

    switch ($estado['paso']) {

        case 'letra_conductor':
            return manual_ask_driver_letter($chat_id);

        case 'listar_conductores':
            // Espera callback, no mensaje
            return;

        case 'conductor_seleccionado':
            return manual_after_driver($chat_id, $estado);

        case 'nuevo_conductor':
            sendMessage($chat_id, "✍️ Escribe el nombre del nuevo conductor:");
            return;

        default:
            sendMessage($chat_id, "⚠️ Estado inválido. Usa /cancel.");
            clearState($chat_id);
            return;
    }
}

/* =========================
   LETRAS
========================= */
function manual_ask_driver_letter($chat_id) {

    $letras = [
        ['A','B','C','D','E','F'],
        ['G','H','I','J','K','L'],
        ['M','N','O','P','Q','R'],
        ['S','T','U','V','W','X'],
        ['Y','Z','Ñ'],
        [['text' => '+ Nuevo conductor', 'callback_data' => 'driver_new']]
    ];

    $keyboard = [];
    foreach ($letras as $row) {
        $btns = [];
        foreach ($row as $l) {
            if (is_array($l)) {
                $btns[] = $l;
            } else {
                $btns[] = [
                    'text' => $l,
                    'callback_data' => 'driver_letter_' . $l
                ];
            }
        }
        $keyboard[] = $btns;
    }

    sendMessage($chat_id, "🔤 Elige la letra inicial del conductor:", [
        'inline_keyboard' => $keyboard
    ]);
}

/* =========================
   CALLBACK HANDLER
========================= */
function manual_handle_callback($chat_id, $data, &$estado) {

    /* ---- NUEVO CONDUCTOR ---- */
    if ($data === 'driver_new') {
        $estado['paso'] = 'nuevo_conductor';
        saveState($chat_id, $estado);
        sendMessage($chat_id, "✍️ Escribe el nombre del nuevo conductor:");
        return;
    }

    /* ---- LETRA ---- */
    if (strpos($data, 'driver_letter_') === 0) {

        $letra = strtoupper(substr($data, 14));

        $estado['paso']  = 'listar_conductores';
        $estado['letra'] = $letra;
        saveState($chat_id, $estado);

        manual_list_drivers($chat_id, $letra);
        return;
    }

    /* ---- SELECCIÓN ---- */
    if (strpos($data, 'driver_select_') === 0) {

        $driver_id = (int)substr($data, 14);

        $estado['paso'] = 'conductor_seleccionado';
        $estado['conductor_id'] = $driver_id;
        saveState($chat_id, $estado);

        manual_after_driver($chat_id, $estado);
        return;
    }
}

/* =========================
   LISTAR CONDUCTORES
========================= */
function manual_list_drivers($chat_id, $letra) {

    $db = db();

    $sql = "
        SELECT id, nombre
        FROM conductores
        WHERE chat_id = ?
        AND nombre COLLATE utf8mb4_general_ci LIKE CONCAT(?, '%')
        ORDER BY nombre
    ";

    $stmt = $db->prepare($sql);
    $stmt->bind_param("is", $chat_id, $letra);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        sendMessage(
            $chat_id,
            "❌ No hay conductores con la letra *{$letra}*",
            null,
            true
        );
        return;
    }

    $keyboard = [];
    while ($row = $res->fetch_assoc()) {
        $keyboard[] = [[
            'text' => $row['nombre'],
            'callback_data' => 'driver_select_' . $row['id']
        ]];
    }

    sendMessage(
        $chat_id,
        "🚗 Conductores con *{$letra}*:",
        ['inline_keyboard' => $keyboard],
        true
    );
}

/* =========================
   NUEVO CONDUCTOR (MENSAJE)
========================= */
function manual_handle_text($chat_id, $text, &$estado) {

    if (($estado['paso'] ?? '') !== 'nuevo_conductor') {
        return;
    }

    $nombre = trim($text);
    if ($nombre === '') {
        sendMessage($chat_id, "⚠️ Nombre inválido.");
        return;
    }

    $db = db();
    $stmt = $db->prepare(
        "INSERT INTO conductores (chat_id, nombre) VALUES (?, ?)"
    );
    $stmt->bind_param("is", $chat_id, $nombre);
    $stmt->execute();

    sendMessage($chat_id, "✅ Conductor guardado: *{$nombre}*", null, true);

    // Volver automáticamente a la letra del nuevo conductor
    $estado['paso'] = 'letra_conductor';
    saveState($chat_id, $estado);

    manual_ask_driver_letter($chat_id);
}

/* =========================
   DESPUÉS DEL CONDUCTOR
========================= */
function manual_after_driver($chat_id, $estado) {

    $db = db();
    $stmt = $db->prepare("SELECT nombre FROM conductores WHERE id = ?");
    $stmt->bind_param("i", $estado['conductor_id']);
    $stmt->execute();
    $nombre = $stmt->get_result()->fetch_assoc()['nombre'] ?? 'Desconocido';

    sendMessage(
        $chat_id,
        "✅ Conductor seleccionado: *{$nombre}*\n\n➡️ Continúa el flujo aquí.",
        null,
        true
    );

    // AQUÍ SIGUE TU LÓGICA REAL (vehículo, ruta, valor, etc.)
    // $estado['paso'] = 'siguiente_paso';
    // saveState($chat_id, $estado);
}
