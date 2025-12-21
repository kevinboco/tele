<?php
require_once __DIR__ . '/helpers.php';

/* ======================
   ENTRY
====================== */
function manual_entrypoint($chat_id, $estado) {

    $estado = [
        'flujo' => 'manual',
        'paso'  => 'letra_conductor'
    ];
    saveState($chat_id, $estado);

    manual_ask_driver_letter($chat_id);
}

/* ======================
   LETRAS
====================== */
function manual_ask_driver_letter($chat_id) {

    $rows = [
        ['A','B','C','D','E','F'],
        ['G','H','I','J','K','L'],
        ['M','N','O','P','Q','R'],
        ['S','T','U','V','W','X'],
        ['Y','Z','Ñ'],
        [['text' => '+ Nuevo conductor', 'callback_data' => 'driver_new']]
    ];

    $keyboard = [];
    foreach ($rows as $r) {
        $row = [];
        foreach ($r as $l) {
            if (is_array($l)) {
                $row[] = $l;
            } else {
                $row[] = [
                    'text' => $l,
                    'callback_data' => 'driver_letter_' . $l
                ];
            }
        }
        $keyboard[] = $row;
    }

    sendMessage(
        $chat_id,
        "🔤 Elige la letra inicial del conductor:",
        ['inline_keyboard' => $keyboard]
    );
}

/* ======================
   CALLBACKS
====================== */
function manual_handle_callback($chat_id, $data, &$estado) {

    if ($data === 'driver_new') {
        $estado['paso'] = 'nuevo_conductor';
        saveState($chat_id, $estado);
        sendMessage($chat_id, "✍️ Escribe el nombre del nuevo conductor:");
        return;
    }

    if (strpos($data, 'driver_letter_') === 0) {

        $letra = strtoupper(substr($data, 14));

        $estado['paso']  = 'listar';
        $estado['letra'] = $letra;
        saveState($chat_id, $estado);

        manual_list_drivers($chat_id, $letra);
        return;
    }

    if (strpos($data, 'driver_select_') === 0) {

        $id = (int)substr($data, 14);
        $estado['conductor_id'] = $id;
        saveState($chat_id, $estado);

        sendMessage($chat_id, "✅ Conductor seleccionado.");
        return;
    }
}

/* ======================
   LISTAR
====================== */
function manual_list_drivers($chat_id, $letra) {

    $db = db();
    $stmt = $db->prepare("
        SELECT id, nombre
        FROM conductores
        WHERE chat_id = ?
        AND nombre COLLATE utf8mb4_general_ci LIKE CONCAT(?, '%')
        ORDER BY nombre
    ");
    $stmt->bind_param("is", $chat_id, $letra);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        sendMessage($chat_id, "❌ No hay conductores con la letra *{$letra}*", null, true);
        return;
    }

    $kb = [];
    while ($r = $res->fetch_assoc()) {
        $kb[] = [[
            'text' => $r['nombre'],
            'callback_data' => 'driver_select_' . $r['id']
        ]];
    }

    sendMessage(
        $chat_id,
        "🚗 Conductores con *{$letra}*:",
        ['inline_keyboard' => $kb],
        true
    );
}

/* ======================
   TEXTO
====================== */
function manual_handle_text($chat_id, $text, &$estado) {

    if (($estado['paso'] ?? '') !== 'nuevo_conductor') return;

    $nombre = trim($text);
    if ($nombre === '') return;

    $db = db();
    $stmt = $db->prepare("INSERT INTO conductores (chat_id, nombre) VALUES (?, ?)");
    $stmt->bind_param("is", $chat_id, $nombre);
    $stmt->execute();

    sendMessage($chat_id, "✅ Conductor guardado: *{$nombre}*", null, true);

    $estado['paso'] = 'letra_conductor';
    saveState($chat_id, $estado);

    manual_ask_driver_letter($chat_id);
}
