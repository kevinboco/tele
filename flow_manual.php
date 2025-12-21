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
    $col_count = 0;
    
    foreach ($letras as $letra) {
        // Corregir: Solo crear callback_data para letras A-Z y "TODOS"
        if ($letra === 'TODOS') {
            $callback_data = "manual_letra_TODOS";
            $text = "TODOS";
        } else {
            $callback_data = "manual_letra_" . $letra;
            $text = $letra;
        }
        
        // Resaltar la letra seleccionada
        if ($letra_actual && 
            (($letra_actual === 'TODOS' && $letra === 'TODOS') || 
             ($letra_actual === $letra))) {
            $text = "✅ " . $text;
        }
        
        $row[] = [
            "text" => $text,
            "callback_data" => $callback_data
        ];
        $col_count++;
        
        // 6 columnas por fila
        if ($col_count === 6) {
            $kb["inline_keyboard"][] = $row;
            $row = [];
            $col_count = 0;
        }
    }
    
    // Última fila si queda
    if (!empty($row)) {
        // Rellenar fila incompleta
        while (count($row) < 6) {
            $row[] = ["text" => " ", "callback_data" => "manual_noop"];
        }
        $kb["inline_keyboard"][] = $row;
    }
    
    // Botón para nuevo conductor
    $kb["inline_keyboard"][] = [[
        "text" => "➕ Nuevo conductor",
        "callback_data" => "manual_nuevo"
    ]];
    
    $mensaje = "🔠 *Elige la letra inicial del conductor:*\n\n";
    if ($letra_actual) {
        if ($letra_actual === 'TODOS') {
            $mensaje .= "Filtro actual: TODOS los conductores\n";
        } else {
            $mensaje .= "Filtro actual: Letra *$letra_actual*\n";
        }
    }
    $mensaje .= "\nO crea un nuevo conductor con el botón de abajo.";
    
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
    
    // ORDENAR ALFABÉTICAMENTE de la A a la Z
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
        // Completar la fila con botones vacíos si es necesario
        while (count($row) < 3) {
            $row[] = ["text" => " ", "callback_data" => "manual_noop"];
        }
        $kb["inline_keyboard"][] = $row;
    }
    
    // Botones de navegación
    $nav_buttons = [];
    if ($pagina > 0) {
        $nav_buttons[] = ["text" => "⬅️ Anterior", "callback_data" => "manual_page_" . ($pagina - 1)];
    }
    
    // Indicador de página central
    if ($total_paginas > 1) {
        $nav_buttons[] = ["text" => "📄 " . ($pagina + 1) . "/" . $total_paginas, "callback_data" => "manual_info"];
    }
    
    if ($pagina < $total_paginas - 1) {
        $nav_buttons[] = ["text" => "Siguiente ➡️", "callback_data" => "manual_page_" . ($pagina + 1)];
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

/* ========= FUNCIÓN PARA AGREGAR BOTÓN VOLVER ========= */
function manual_add_back_button(array $kb, string $back_step): array {
    $kb["inline_keyboard"][] = [[ 
        "text" => "⬅️ Volver", 
        "callback_data" => "manual_back_" . $back_step 
    ]];
    return $kb;
}

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
                $mensaje = "📋 *Conductores encontrados:*\n";
                $mensaje .= "Filtro: ";
                $mensaje .= (!$letra_filtro || $letra_filtro === 'TODOS') ? "TODAS las letras" : "letra *$letra_filtro*";
                $mensaje .= " (" . count($conductores) . " conductores)\n\n";
                $mensaje .= "Selecciona un conductor:";
                sendMessage($chat_id, $mensaje, $kb);
            } else {
                $mensaje = "❌ No se encontraron conductores";
                if ($letra_filtro && $letra_filtro !== 'TODOS') {
                    $mensaje .= " con la letra *$letra_filtro*";
                }
                $mensaje .= ".\n\n";
                $mensaje .= "¿Deseas crear un nuevo conductor?";
                $kb = [
                    "inline_keyboard" => [
                        [["text" => "➕ Crear nuevo conductor", "callback_data" => "manual_nuevo"]],
                        [["text" => "🔠 Cambiar letra", "callback_data" => "manual_cambiar_letra"]]
                    ]
                ];
                sendMessage($chat_id, $mensaje, $kb);
            }
            break;
            
        case 'manual_nombre_nuevo':
            sendMessage($chat_id, "✍️ Escribe el *nombre* del nuevo conductor:"); 
            break;
        case 'manual_ruta_menu':
            // Cargar rutas frescas desde BD
            $rutas = $conn ? obtenerRutasAdmin($conn, $chat_id) : [];
            if ($rutas) {
                $kb = manual_kb_grid($rutas, 'manual_ruta_sel_');
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nueva ruta", "callback_data"=>"manual_ruta_nueva" ]];
                $kb = manual_add_back_button($kb, 'menu_letra');
                sendMessage($chat_id, "Selecciona una *ruta* o crea una nueva:", $kb);
            } else {
                sendMessage($chat_id, "No tienes rutas guardadas.\n✍️ Escribe la *ruta del viaje*:");
                $estado['paso']='manual_ruta_nueva_texto'; saveState($chat_id,$estado);
            }
            break;
        case 'manual_ruta_nueva_texto':
            sendMessage($chat_id, "✍️ Escribe la *ruta del viaje*:"); 
            break;
        // ... resto de los casos se mantienen igual
        default:
            sendMessage($chat_id, "Continuamos donde ibas. Escribe /cancel para reiniciar.");
    }
    $conn?->close();
}

/* ========= FUNCIÓN GRID ORIGINAL (para otras listas) ========= */
function manual_kb_grid(array $items, string $callback_prefix): array {
    $kb = ["inline_keyboard" => []];
    $row = [];
    
    foreach ($items as $item) {
        $id = $item['id'] ?? $item;
        $text = $item['nombre'] ?? $item['ruta'] ?? $item['vehiculo'] ?? $item;
        
        $row[] = [
            "text" => $text,
            "callback_data" => $callback_prefix . $id
        ];
        
        if (count($row) === 2) {
            $kb["inline_keyboard"][] = $row;
            $row = [];
        }
    }
    
    if (!empty($row)) {
        $kb["inline_keyboard"][] = $row;
    }
    
    return $kb;
}

function manual_handle_callback($chat_id, &$estado, $cb_data, $cb_id=null) {
    if (($estado["flujo"] ?? "") !== "manual") return;

    // ========= NUEVO: Callback no operativo =========
    if ($cb_data === 'manual_noop') {
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    // ========= NUEVO: Manejo de selección de letra =========
    if (strpos($cb_data, 'manual_letra_') === 0) {
        // Extraer la letra correctamente
        $letra = substr($cb_data, strlen('manual_letra_'));
        
        // Validar que sea una letra válida
        if (!preg_match('/^[A-Z]$|^TODOS$/', $letra)) {
            if ($cb_id) answerCallbackQuery($cb_id, "❌ Letra no válida");
            return;
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
            $mensaje = "📋 *Conductores encontrados:*\n";
            $mensaje .= "Filtro: ";
            $mensaje .= $letra === 'TODOS' ? "TODAS las letras" : "letra *$letra*";
            $mensaje .= " (" . count($conductores) . " conductores)\n\n";
            $mensaje .= "Selecciona un conductor:";
            sendMessage($chat_id, $mensaje, $kb);
        } else {
            $mensaje = "❌ No se encontraron conductores con la letra *$letra*.\n\n";
            $mensaje .= "¿Deseas crear un nuevo conductor o cambiar la letra?";
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

    // ========= PAGINACIÓN =========
    if (strpos($cb_data, 'manual_page_') === 0) {
        $pagina = (int)substr($cb_data, strlen('manual_page_'));
        $estado['manual_page'] = $pagina;
        saveState($chat_id, $estado);
        
        $letra_filtro = $estado['manual_letra_filtro'] ?? null;
        $conn = db();
        $conductores = $conn ? manual_obtener_conductores_filtrados($conn, $chat_id, $letra_filtro) : [];
        $conn?->close();
        
        if ($conductores) {
            $kb = manual_kb_grid_paginado($conductores, 'manual_sel_', $pagina, $letra_filtro);
            $mensaje = "📋 *Conductores encontrados:*\n";
            $mensaje .= "Filtro: ";
            $mensaje .= (!$letra_filtro || $letra_filtro === 'TODOS') ? "TODAS las letras" : "letra *$letra_filtro*";
            $mensaje .= " (" . count($conductores) . " conductores)\n\n";
            $mensaje .= "Selecciona un conductor:";
            sendMessage($chat_id, $mensaje, $kb);
        }
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    // ========= INFO PAGINACIÓN =========
    if ($cb_data === 'manual_info') {
        $letra_filtro = $estado['manual_letra_filtro'] ?? null;
        $conn = db();
        $conductores = $conn ? manual_obtener_conductores_filtrados($conn, $chat_id, $letra_filtro) : [];
        $conn?->close();
        $total_paginas = ceil(count($conductores) / 15);
        $mensaje = "Página " . ($estado['manual_page'] + 1) . " de " . $total_paginas;
        if ($cb_id) answerCallbackQuery($cb_id, $mensaje);
        return;
    }

    // ========= BOTÓN VOLVER =========
    if (strpos($cb_data, 'manual_back_') === 0) {
        $back_step = substr($cb_data, strlen('manual_back_'));
        manual_handle_back($chat_id, $estado, $back_step);
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    // Seleccionar conductor existente
    if (strpos($cb_data, 'manual_sel_') === 0) {
        $idSel = (int)substr($cb_data, strlen('manual_sel_'));
        $conn = db(); $row = obtenerConductorAdminPorId($conn, $idSel, $chat_id); $conn?->close();
        if (!$row) { 
            sendMessage($chat_id, "⚠️ Conductor no encontrado. Vuelve a intentarlo con /manual.");
            if ($cb_id) answerCallbackQuery($cb_id, "❌ Conductor no encontrado");
        }
        else {
            $estado['manual_nombre'] = $row['nombre'];
            $estado['paso'] = 'manual_ruta_menu'; 
            saveState($chat_id,$estado);

            // Cargar rutas frescas desde BD
            $conn = db(); $rutas = $conn ? obtenerRutasAdmin($conn, $chat_id) : []; $conn?->close();
            if ($rutas) {
                $kb = manual_kb_grid($rutas, 'manual_ruta_sel_');
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nueva ruta", "callback_data"=>"manual_ruta_nueva" ]];
                $kb = manual_add_back_button($kb, 'menu_letra');
                sendMessage($chat_id, "👤 Conductor: *{$row['nombre']}*\n\nSelecciona una *ruta* o crea una nueva:", $kb);
            } else {
                $estado['paso']='manual_ruta_nueva_texto'; saveState($chat_id,$estado);
                sendMessage($chat_id, "👤 Conductor: *{$row['nombre']}*\n\n✍️ Escribe la *ruta del viaje*:");
            }
            if ($cb_id) answerCallbackQuery($cb_id);
        }
        return;
    }

    // Crear nuevo conductor
    if ($cb_data === 'manual_nuevo') {
        $estado['paso'] = 'manual_nombre_nuevo'; saveState($chat_id,$estado);
        sendMessage($chat_id, "✍️ Escribe el *nombre* del nuevo conductor:");
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    // ... resto del código de callbacks se mantiene igual
    
    // Si llega aquí, no se manejó el callback
    if ($cb_id) answerCallbackQuery($cb_id, "❌ Acción no reconocida");
}

/* ========= MANEJO DEL BOTÓN VOLVER ========= */
function manual_handle_back($chat_id, &$estado, $back_step) {
    switch ($back_step) {
        case 'menu_letra':
            $estado['paso'] = 'manual_menu_letra';
            // Mantener el filtro de letra
            break;
            
        case 'menu':
            $estado['paso'] = 'manual_menu';
            // Mantener datos actuales
            break;
            
        case 'ruta_menu':
            $estado['paso'] = 'manual_ruta_menu';
            // Limpiar datos de ruta
            unset($estado['manual_ruta']);
            break;
            
        // ... otros casos se mantienen igual
            
        default:
            // Si no reconoce el paso, volver al selector de letra
            $estado['paso'] = 'manual_menu_letra';
            break;
    }
    
    saveState($chat_id, $estado);
    manual_resend_current_step($chat_id, $estado);
}

// ... resto del código (manual_handle_text y manual_insert_viaje_and_close) se mantienen igual