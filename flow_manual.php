<?php
require_once __DIR__.'/helpers.php';

/* =========================================================
   ENTRYPOINT
========================================================= */

function manual_entrypoint($chat_id, $estado) {

    // Siempre iniciar flujo nuevo
    $estado = [
        "flujo" => "manual",
        "paso" => "manual_menu_letra",
        "manual_page" => 0,
        "manual_letra_filtro" => null
    ];
    saveState($chat_id, $estado);

    manual_show_letter_selector($chat_id);
}

/* =========================================================
   SELECTOR DE LETRAS
========================================================= */

function manual_show_letter_selector($chat_id, $letra_actual = null) {

    $letras = range('A', 'Z');
    array_unshift($letras, 'TODOS');

    $kb = ["inline_keyboard" => []];
    $row = [];

    foreach ($letras as $letra) {

        $callback = ($letra === 'TODOS')
            ? "manual_letra_todos"
            : "manual_letra_$letra";

        $text = $letra;
        if ($letra_actual === $letra) {
            $text = "✅ $letra";
        }

        $row[] = [
            "text" => $text,
            "callback_data" => $callback
        ];

        if (count($row) === 6) {
            $kb["inline_keyboard"][] = $row;
            $row = [];
        }
    }

    if ($row) {
        $kb["inline_keyboard"][] = $row;
    }

    $kb["inline_keyboard"][] = [[
        "text" => "➕ Nuevo conductor",
        "callback_data" => "manual_nuevo"
    ]];

    sendMessage(
        $chat_id,
        "🔠 *Selecciona una letra para filtrar conductores*",
        $kb
    );
}

/* =========================================================
   OBTENER CONDUCTORES
========================================================= */

function manual_obtener_conductores_filtrados($conn, $chat_id, $letra = null) {

    if ($letra && $letra !== 'TODOS') {
        $sql = "SELECT id,nombre
                FROM conductores_admin
                WHERE user_id = ?
                AND LOWER(nombre) LIKE ?
                ORDER BY LOWER(nombre)";
        $like = strtolower($letra).'%';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $chat_id, $like);
    } else {
        $sql = "SELECT id,nombre
                FROM conductores_admin
                WHERE user_id = ?
                ORDER BY LOWER(nombre)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $chat_id);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r;
    $stmt->close();

    return $out;
}

/* =========================================================
   GRID PAGINADO
========================================================= */

function manual_kb_grid_paginado($items, $prefix, $pagina, $letra) {

    $kb = ["inline_keyboard" => []];
    $por_pagina = 15;

    $items_pagina = array_slice($items, $pagina * $por_pagina, $por_pagina);
    $row = [];

    foreach ($items_pagina as $i) {

        $txt = mb_strlen($i['nombre']) > 15
            ? mb_substr($i['nombre'],0,12).'...'
            : $i['nombre'];

        $row[] = [
            "text" => $txt,
            "callback_data" => $prefix.$i['id']
        ];

        if (count($row) === 3) {
            $kb["inline_keyboard"][] = $row;
            $row = [];
        }
    }

    if ($row) $kb["inline_keyboard"][] = $row;

    $total = ceil(count($items) / $por_pagina);
    $nav = [];

    if ($pagina > 0)
        $nav[] = ["text"=>"⬅️","callback_data"=>"manual_page_".($pagina-1)];

    if ($total > 1)
        $nav[] = ["text"=>($pagina+1)."/".$total,"callback_data"=>"manual_info"];

    if ($pagina < $total-1)
        $nav[] = ["text"=>"➡️","callback_data"=>"manual_page_".($pagina+1)];

    if ($nav) $kb["inline_keyboard"][] = $nav;

    $kb["inline_keyboard"][] = [[
        "text"=>"🔠 Cambiar letra ($letra)",
        "callback_data"=>"manual_cambiar_letra"
    ]];

    $kb["inline_keyboard"][] = [[
        "text"=>"➕ Nuevo conductor",
        "callback_data"=>"manual_nuevo"
    ]];

    return $kb;
}

/* =========================================================
   CALLBACKS
========================================================= */

function manual_handle_callback($chat_id, &$estado, $cb, $cb_id=null) {

    if (($estado['flujo'] ?? '') !== 'manual') return;

    /* ---------- LETRAS ---------- */

    if (strpos($cb, 'manual_letra_') === 0) {

        $letra = ($cb === 'manual_letra_todos')
            ? 'TODOS'
            : substr($cb, 13);

        $estado['manual_letra_filtro'] = $letra;
        $estado['manual_page'] = 0;
        $estado['paso'] = 'manual_menu';
        saveState($chat_id, $estado);

        $conn = db();
        $items = manual_obtener_conductores_filtrados($conn, $chat_id, $letra);
        $conn->close();

        if (!$items) {
            sendMessage(
                $chat_id,
                "No hay conductores con la letra *$letra*",
                [
                    "inline_keyboard"=>[
                        [["text"=>"➕ Crear","callback_data"=>"manual_nuevo"]],
                        [["text"=>"🔠 Cambiar letra","callback_data"=>"manual_cambiar_letra"]]
                    ]
                ]
            );
        } else {
            sendMessage(
                $chat_id,
                "👤 *Selecciona conductor* ($letra)",
                manual_kb_grid_paginado($items,'manual_sel_',0,$letra)
            );
        }

        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    /* ---------- CAMBIAR LETRA ---------- */

    if ($cb === 'manual_cambiar_letra') {
        $estado['paso'] = 'manual_menu_letra';
        saveState($chat_id,$estado);
        manual_show_letter_selector($chat_id,$estado['manual_letra_filtro']);
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    /* ---------- PAGINACIÓN ---------- */

    if (strpos($cb,'manual_page_')===0) {

        $estado['manual_page'] = (int)substr($cb,12);
        saveState($chat_id,$estado);

        $conn=db();
        $items = manual_obtener_conductores_filtrados(
            $conn,
            $chat_id,
            $estado['manual_letra_filtro']
        );
        $conn->close();

        sendMessage(
            $chat_id,
            "👤 *Selecciona conductor*",
            manual_kb_grid_paginado(
                $items,
                'manual_sel_',
                $estado['manual_page'],
                $estado['manual_letra_filtro']
            )
        );

        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    /* ---------- SELECCIONAR CONDUCTOR ---------- */

    if (strpos($cb,'manual_sel_')===0) {

        $id = (int)substr($cb,11);
        $conn=db();
        $row = obtenerConductorAdminPorId($conn,$id,$chat_id);
        $conn->close();

        if (!$row) {
            sendMessage($chat_id,"⚠️ Conductor no encontrado");
            return;
        }

        $estado['manual_nombre'] = $row['nombre'];
        $estado['paso'] = 'manual_ruta_menu';
        saveState($chat_id,$estado);

        sendMessage($chat_id,"👤 Conductor: *{$row['nombre']}*\n\nContinúa…");
        return;
    }

    if ($cb === 'manual_nuevo') {
        $estado['paso'] = 'manual_nombre_nuevo';
        saveState($chat_id,$estado);
        sendMessage($chat_id,"✍️ Escribe el *nombre del conductor*:");
    }

    if ($cb_id) answerCallbackQuery($cb_id);
}
