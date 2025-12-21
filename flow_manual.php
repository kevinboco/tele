<?php
require_once __DIR__.'/helpers.php';

/* ================= ENTRYPOINT ================= */

function manual_entrypoint($chat_id, $estado) {
    if (!empty($estado) && ($estado['flujo'] ?? '') === 'manual') {
        return manual_resend_current_step($chat_id, $estado);
    }

    $estado = [
        "flujo" => "manual",
        "paso"  => "manual_letra_menu"
    ];
    saveState($chat_id, $estado);

    sendMessage(
        $chat_id,
        "🔤 Elige la *letra inicial* del conductor:",
        manual_kb_letras()
    );
}

/* ================= TECLADO DE LETRAS ================= */

function manual_kb_letras(): array {
    $letras = array_merge(range('A','Z'), ['Ñ']);
    $kb = ["inline_keyboard" => []];
    $row = [];

    foreach ($letras as $l) {
        $row[] = [
            "text" => $l,
            "callback_data" => "manual_letra_" . $l
        ];

        if (count($row) === 6) {
            $kb["inline_keyboard"][] = $row;
            $row = [];
        }
    }

    if ($row) $kb["inline_keyboard"][] = $row;

    $kb["inline_keyboard"][] = [
        ["text" => "➕ Nuevo conductor", "callback_data" => "manual_nuevo"]
    ];

    return $kb;
}

/* ================= TECLADO CONDUCTORES ================= */

function manual_kb_conductores(array $items): array {
    $kb = ["inline_keyboard" => []];
    $row = [];

    foreach ($items as $c) {
        $row[] = [
            "text" => $c['nombre'],
            "callback_data" => "manual_sel_" . $c['id']
        ];

        if (count($row) === 2) {
            $kb["inline_keyboard"][] = $row;
            $row = [];
        }
    }

    if ($row) $kb["inline_keyboard"][] = $row;

    $kb["inline_keyboard"][] = [
        ["text" => "🔤 Cambiar letra", "callback_data" => "manual_back_letra"]
    ];

    return $kb;
}

/* ================= REENVÍO ================= */

function manual_resend_current_step($chat_id, $estado) {
    $conn = db();

    switch ($estado['paso']) {

        case 'manual_letra_menu':
            sendMessage($chat_id, "🔤 Elige la *letra inicial* del conductor:", manual_kb_letras());
            break;

        case 'manual_conductor_menu':
            $letra = $estado['manual_letra'];
            $conductores = obtenerConductoresPorLetra($conn, $chat_id, $letra);

            if ($conductores) {
                sendMessage(
                    $chat_id,
                    "👤 Conductores que empiezan por *$letra*:",
                    manual_kb_conductores($conductores)
                );
            } else {
                sendMessage(
                    $chat_id,
                    "⚠️ No hay conductores con la letra *$letra*.\n✍️ Escribe el *nombre* del nuevo conductor:"
                );
                $estado['paso'] = 'manual_nombre_nuevo';
                saveState($chat_id, $estado);
            }
            break;

        case 'manual_nombre_nuevo':
            sendMessage($chat_id, "✍️ Escribe el *nombre* del nuevo conductor:");
            break;
    }

    $conn?->close();
}

/* ================= CALLBACKS ================= */

function manual_handle_callback($chat_id, &$estado, $cb_data, $cb_id=null) {
    if (($estado['flujo'] ?? '') !== 'manual') return;

    /* === LETRA === */
    if (strpos($cb_data, 'manual_letra_') === 0) {
        answerCallbackQuery($cb_id); // 🔴 OBLIGATORIO

        $letra = substr($cb_data, 13);
        $estado['manual_letra'] = $letra;
        $estado['paso'] = 'manual_conductor_menu';
        saveState($chat_id, $estado);

        $conn = db();
        $conductores = obtenerConductoresPorLetra($conn, $chat_id, $letra);
        $conn?->close();

        if ($conductores) {
            sendMessage(
                $chat_id,
                "👤 Conductores que empiezan por *$letra*:",
                manual_kb_conductores($conductores)
            );
        } else {
            sendMessage(
                $chat_id,
                "⚠️ No hay conductores con la letra *$letra*.\n✍️ Escribe el *nombre* del nuevo conductor:"
            );
            $estado['paso'] = 'manual_nombre_nuevo';
            saveState($chat_id, $estado);
        }
        return;
    }

    /* === VOLVER === */
    if ($cb_data === 'manual_back_letra') {
        answerCallbackQuery($cb_id);

        $estado['paso'] = 'manual_letra_menu';
        unset($estado['manual_letra']);
        saveState($chat_id, $estado);

        manual_resend_current_step($chat_id, $estado);
        return;
    }

    /* === SELECCIONAR CONDUCTOR === */
    if (strpos($cb_data, 'manual_sel_') === 0) {
        answerCallbackQuery($cb_id);

        $id = (int)substr($cb_data, 11);
        $conn = db();
        $row = obtenerConductorAdminPorId($conn, $id, $chat_id);
        $conn?->close();

        if (!$row) {
            sendMessage($chat_id, "⚠️ Conductor no encontrado.");
            return;
        }

        $estado['manual_nombre'] = $row['nombre'];
        $estado['paso'] = 'manual_ruta_menu';
        saveState($chat_id, $estado);

        sendMessage($chat_id, "👤 Conductor seleccionado: *{$row['nombre']}*");
        return;
    }

    /* === NUEVO === */
    if ($cb_data === 'manual_nuevo') {
        answerCallbackQuery($cb_id);

        $estado['paso'] = 'manual_nombre_nuevo';
        saveState($chat_id, $estado);

        sendMessage($chat_id, "✍️ Escribe el *nombre* del nuevo conductor:");
        return;
    }
}

/* ================= TEXTO ================= */

function manual_handle_text($chat_id, &$estado, $text, $photo) {
    if (($estado['flujo'] ?? '') !== 'manual') return;

    if ($estado['paso'] === 'manual_nombre_nuevo') {
        $nombre = trim($text);
        if ($nombre === '') {
            sendMessage($chat_id, "⚠️ El nombre no puede estar vacío.");
            return;
        }

        $conn = db();
        crearConductorAdmin($conn, $chat_id, $nombre);
        $conn?->close();

        $estado['manual_nombre'] = $nombre;
        $estado['paso'] = 'manual_ruta_menu';
        saveState($chat_id, $estado);

        sendMessage($chat_id, "✅ Conductor guardado: *$nombre*");
    }
}

/* ================= BD ================= */

function obtenerConductoresPorLetra($conn, $chat_id, $letra) {
    $stmt = $conn->prepare("
        SELECT id, nombre
        FROM conductores
        WHERE chat_id = ?
          AND UPPER(nombre) LIKE CONCAT(?, '%')
        ORDER BY nombre ASC
    ");
    $stmt->bind_param("is", $chat_id, $letra);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->fetch_all(MYSQLI_ASSOC);
}
