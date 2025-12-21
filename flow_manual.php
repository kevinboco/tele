<?php
// flow_manual.php
require_once __DIR__.'/helpers.php';

function manual_entrypoint($chat_id, $estado) {
    // Si ya estás en manual, reenvía el paso
    if (!empty($estado) && ($estado['flujo'] ?? '') === 'manual') {
        return manual_resend_current_step($chat_id, $estado);
    }
    // Nuevo flujo - Primero elegir letra
    $estado = [
        "flujo" => "manual",
        "paso" => "manual_menu_letra",
        "manual_page" => 0,
        "manual_letra_filtro" => null
    ];
    saveState($chat_id, $estado);
    
    // Mostrar teclado de letras
    manual_show_letter_selector($chat_id);
}

/* ========= NUEVO: SELECTOR DE LETRAS ========= */
function manual_show_letter_selector($chat_id, $letra_actual = null) {
    $letras = range('A', 'Z');
    array_unshift($letras, 'TODOS'); // Opción para ver todos
    
    $kb = ["inline_keyboard" => []];
    $row = [];
    
    foreach ($letras as $letra) {
        $callback_data = $letra === 'TODOS' ? "manual_letra_todos" : "manual_letra_$letra";
        
        // Resaltar la letra seleccionada
        $text = $letra;
        if ($letra_actual && 
            (($letra_actual === 'TODOS' && $letra === 'TODOS') || 
             (strtoupper($letra_actual) === $letra))) {
            $text = "✅ " . $text;
        }
        
        $row[] = [
            "text" => $text,
            "callback_data" => $callback_data
        ];
        
        // 6 letras por fila
        if (count($row) === 6) {
            $kb["inline_keyboard"][] = $row;
            $row = [];
        }
    }
    
    // Última fila si queda
    if (!empty($row)) {
        $kb["inline_keyboard"][] = $row;
    }
    
    // Botón para comenzar sin filtrar
    $kb["inline_keyboard"][] = [[
        "text" => "➕ Nuevo conductor",
        "callback_data" => "manual_nuevo"
    ]];
    
    $mensaje = "🔠 *Selecciona una letra para filtrar conductores*\n\n";
    if ($letra_actual) {
        if ($letra_actual === 'TODOS') {
            $mensaje .= "Filtro actual: TODOS los conductores\n";
        } else {
            $mensaje .= "Filtro actual: Letra *$letra_actual*\n";
        }
    }
    $mensaje .= "O crea un nuevo conductor con el botón de abajo.";
    
    sendMessage($chat_id, $mensaje, $kb);
}

/* ========= MODIFICADA: Función para obtener conductores con filtro ========= */
function manual_obtener_conductores_filtrados($conn, $chat_id, $letra_filtro = null) {
    if ($letra_filtro && $letra_filtro !== 'TODOS') {
        // Filtrar por letra inicial (case-insensitive)
        $sql = "SELECT id, nombre FROM conductores_admin WHERE user_id = ? AND (LOWER(nombre) LIKE LOWER(?) OR LOWER(nombre) LIKE LOWER(?)) ORDER BY LOWER(nombre)";
        $letra_like = $letra_filtro . '%';
        $letra_like_minuscula = strtolower($letra_filtro) . '%';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $chat_id, $letra_like, $letra_like_minuscula);
    } else {
        // Sin filtro o TODOS
        $sql = "SELECT id, nombre FROM conductores_admin WHERE user_id = ? ORDER BY LOWER(nombre)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $chat_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $conductores = [];
    while ($row = $result->fetch_assoc()) {
        $conductores[] = $row;
    }
    $stmt->close();
    
    return $conductores;
}

/* ========= MODIFICADA: GRID LAYOUT CON PAGINACIÓN Y FILTRO ========= */
function manual_kb_grid_paginado(array $items, string $callback_prefix, int $pagina = 0, string $letra_filtro = null): array {
    $kb = ["inline_keyboard" => []];
    $row = [];
    
    // ORDENAR ALFABÉTICAMENTE de la A a la Z (ya viene ordenado de la BD, pero por si acaso)
    usort($items, function($a, $b) {
        $nombreA = $a['nombre'] ?? $a;
        $nombreB = $b['nombre'] ?? $b;
        return strcasecmp($nombreA, $nombreB);
    });
    
    $items_por_pagina = 15; // 5 filas × 3 columnas = 15 items
    $total_paginas = ceil(count($items) / $items_por_pagina);
    
    // Items para esta página
    $items_pagina = array_slice($items, $pagina * $items_por_pagina, $items_por_pagina);
    
    foreach ($items_pagina as $item) {
        $id = $item['id'] ?? $item;
        $text = $item['nombre'] ?? $item['ruta'] ?? $item['vehiculo'] ?? $item;
        
        // Acortar nombres largos para mejor visualización
        if (mb_strlen($text) > 15) {
            $text = mb_substr($text, 0, 12) . '...';
        }
        
        $row[] = [
            "text" => $text,
            "callback_data" => $callback_prefix . $id
        ];
        
        // 3 columnas
        if (count($row) === 3) {
            $kb["inline_keyboard"][] = $row;
            $row = [];
        }
    }
    
    // Fila incompleta
    if (!empty($row)) {
        $kb["inline_keyboard"][] = $row;
    }
    
    // Botones de navegación
    $nav_buttons = [];
    if ($pagina > 0) {
        $nav_buttons[] = ["text" => "⬅️ Anterior", "callback_data" => "manual_page_" . $pagina . "_" . ($pagina - 1)];
    }
    
    // Indicador de página central
    if ($total_paginas > 1) {
        $nav_buttons[] = ["text" => "📄 " . ($pagina + 1) . "/" . $total_paginas, "callback_data" => "manual_info"];
    }
    
    if ($pagina < $total_paginas - 1) {
        $nav_buttons[] = ["text" => "Siguiente ➡️", "callback_data" => "manual_page_" . $pagina . "_" . ($pagina + 1)];
    }
    
    if (!empty($nav_buttons)) {
        $kb["inline_keyboard"][] = $nav_buttons;
    }
    
    // Botón para cambiar letra filtro
    $filtro_text = $letra_filtro ? "Letra: $letra_filtro" : "Sin filtro";
    $kb["inline_keyboard"][] = [[ 
        "text" => "🔠 Cambiar letra ($filtro_text)", 
        "callback_data" => "manual_cambiar_letra" 
    ]];
    
    // Botón nuevo conductor
    $kb["inline_keyboard"][] = [[ 
        "text" => "➕ Nuevo conductor", 
        "callback_data" => "manual_nuevo" 
    ]];
    
    return $kb;
}

/* ========= MODIFICADA: manual_handle_callback ========= */
function manual_handle_callback($chat_id, &$estado, $cb_data, $cb_id=null) {
    if (($estado["flujo"] ?? "") !== "manual") return;

    // ========= NUEVO: Manejo de selección de letra =========
    if (strpos($cb_data, 'manual_letra_') === 0) {
        if ($cb_data === 'manual_letra_todos') {
            $letra = 'TODOS';
        } else {
            $letra = substr($cb_data, strlen('manual_letra_'));
        }
        
        $estado['manual_letra_filtro'] = $letra;
        $estado['manual_page'] = 0; // Resetear a página 0
        $estado['paso'] = 'manual_menu';
        saveState($chat_id, $estado);
        
        $conn = db();
        $conductores = $conn ? manual_obtener_conductores_filtrados($conn, $chat_id, $letra) : [];
        $conn?->close();
        
        if ($conductores) {
            $kb = manual_kb_grid_paginado($conductores, 'manual_sel_', 0, $letra);
            $mensaje = "Conductores filtrados por ";
            $mensaje .= $letra === 'TODOS' ? "TODAS las letras" : "letra *$letra*";
            $mensaje .= " (" . count($conductores) . " encontrados)";
            sendMessage($chat_id, $mensaje, $kb);
        } else {
            $mensaje = "No se encontraron conductores con la letra *$letra*.\n";
            $mensaje .= "¿Deseas crear un nuevo conductor?";
            $kb = [
                "inline_keyboard" => [
                    [["text" => "➕ Crear nuevo conductor", "callback_data" => "manual_nuevo"]],
                    [["text" => "🔠 Cambiar letra", "callback_data" => "manual_cambiar_letra"]]
                ]
            ];
            sendMessage($chat_id, $mensaje, $kb);
        }
        
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }
    
    // ========= NUEVO: Botón para cambiar letra =========
    if ($cb_data === 'manual_cambiar_letra') {
        $estado['paso'] = 'manual_menu_letra';
        saveState($chat_id, $estado);
        manual_show_letter_selector($chat_id, $estado['manual_letra_filtro'] ?? null);
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    // ========= PAGINACIÓN MODIFICADA (incluye letra filtro) =========
    if (strpos($cb_data, 'manual_page_') === 0) {
        $parts = explode('_', $cb_data);
        if (count($parts) >= 4) {
            $letra_filtro = $estado['manual_letra_filtro'] ?? null;
            $pagina_actual = (int)$parts[2];
            $nueva_pagina = (int)$parts[3];
            
            // Validar que la nueva página sea válida
            $estado['manual_page'] = $nueva_pagina;
            saveState($chat_id, $estado);
            
            $conn = db();
            $conductores = $conn ? manual_obtener_conductores_filtrados($conn, $chat_id, $letra_filtro) : [];
            $conn?->close();
            
            if ($conductores) {
                $kb = manual_kb_grid_paginado($conductores, 'manual_sel_', $nueva_pagina, $letra_filtro);
                sendMessage($chat_id, "Elige un *conductor* o crea uno nuevo:", $kb);
            }
        }
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    // ========= Resto del código existente (sin cambios) =========
    // ... (el resto de tu función manual_handle_callback se mantiene igual)
    
    // ========= BOTÓN VOLVER MODIFICADO =========
    if (strpos($cb_data, 'manual_back_') === 0) {
        $back_step = substr($cb_data, strlen('manual_back_'));
        if ($back_step === 'menu') {
            // Volver al selector de letra en lugar de directamente al menú
            $estado['paso'] = 'manual_menu_letra';
            saveState($chat_id, $estado);
            manual_show_letter_selector($chat_id, $estado['manual_letra_filtro'] ?? null);
        } else {
            manual_handle_back($chat_id, $estado, $back_step);
        }
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }
    
    // ========= ... resto del código existente =========
}

/* ========= MODIFICADA: manual_resend_current_step ========= */
function manual_resend_current_step($chat_id, $estado) {
    $conn = db();
    switch ($estado['paso']) {
        case 'manual_menu_letra':
            manual_show_letter_selector($chat_id, $estado['manual_letra_filtro'] ?? null);
            break;
            
        case 'manual_menu':
            // Cargar conductores filtrados desde BD
            $letra_filtro = $estado['manual_letra_filtro'] ?? null;
            $conductores = $conn ? manual_obtener_conductores_filtrados($conn, $chat_id, $letra_filtro) : [];
            if ($conductores) {
                $pagina = $estado['manual_page'] ?? 0;
                $kb = manual_kb_grid_paginado($conductores, 'manual_sel_', $pagina, $letra_filtro);
                $mensaje = "Conductores filtrados por ";
                $mensaje .= ($letra_filtro === null || $letra_filtro === 'TODOS') ? "TODAS las letras" : "letra *$letra_filtro*";
                $mensaje .= " (" . count($conductores) . " encontrados)";
                sendMessage($chat_id, $mensaje, $kb);
            } else {
                $mensaje = "No tienes conductores guardados";
                if ($letra_filtro && $letra_filtro !== 'TODOS') {
                    $mensaje .= " con la letra *$letra_filtro*";
                }
                $mensaje .= ".\n✍️ Escribe el *nombre* del nuevo conductor:";
                sendMessage($chat_id, $mensaje);
                $estado['paso']='manual_nombre_nuevo'; saveState($chat_id,$estado);
            }
            break;
            
        // ... resto del código existente
    }
    $conn?->close();
}

// También necesitarás modificar las funciones de back para incluir el nuevo paso
function manual_handle_back($chat_id, &$estado, $back_step) {
    switch ($back_step) {
        case 'menu_letra':
            $estado['paso'] = 'manual_menu_letra';
            break;
        // ... resto de casos
    }
    
    saveState($chat_id, $estado);
    manual_resend_current_step($chat_id, $estado);
}