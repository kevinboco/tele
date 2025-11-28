<?php
// flow_manual.php
require_once __DIR__.'/helpers.php';

function manual_entrypoint($chat_id, $estado) {
    // Si ya estás en manual, reenvía el paso
    if (!empty($estado) && ($estado['flujo'] ?? '') === 'manual') {
        return manual_resend_current_step($chat_id, $estado);
    }
    // Nuevo flujo
    $estado = [
        "flujo" => "manual",
        "paso" => "manual_filtro_letra"
    ];
    saveState($chat_id, $estado);

    sendMessage($chat_id, "🔍 *FILTRAR CONDUCTOR POR LETRA*\n\nEscribe una *letra* para filtrar los conductores (A-Z):\nO escribe *TODOS* para ver todos los conductores.");
}

function manual_resend_current_step($chat_id, $estado) {
    $conn = db();
    switch ($estado['paso']) {
        case 'manual_filtro_letra':
            sendMessage($chat_id, "🔍 *FILTRAR CONDUCTOR POR LETRA*\n\nEscribe una *letra* para filtrar los conductores (A-Z):\nO escribe *TODOS* para ver todos los conductores.");
            break;
            
        case 'manual_menu':
            // Cargar conductores frescos desde BD
            $conductores = $conn ? obtenerConductoresAdmin($conn, $chat_id) : [];
            if ($conductores) {
                $pagina = $estado['manual_page'] ?? 0;
                $filtro_letra = $estado['filtro_letra'] ?? null;
                
                // Aplicar filtro si existe
                if ($filtro_letra && $filtro_letra !== 'todos') {
                    $conductores = manual_filtrar_conductores_por_letra($conductores, $filtro_letra);
                }
                
                $kb = manual_kb_grid_paginado($conductores, 'manual_sel_', $pagina, $filtro_letra);
                
                $mensaje = "👥 *TODOS LOS CONDUCTORES*\n\nElige un conductor o crea uno nuevo:";
                if ($filtro_letra && $filtro_letra !== 'todos') {
                    $mensaje = "🔍 Conductores con letra *'$filtro_letra'*:\n\nElige un conductor o crea uno nuevo:";
                }
                
                sendMessage($chat_id, $mensaje, $kb);
            } else {
                sendMessage($chat_id, "❌ No tienes conductores guardados.\n✍️ Escribe el *nombre* del nuevo conductor:");
                $estado['paso']='manual_nombre_nuevo'; 
                saveState($chat_id,$estado);
            }
            break;
            
        // ... resto del código igual ...
    }
    $conn?->close();
}

function manual_handle_text($chat_id, &$estado, $text, $photo) {
    if (($estado["flujo"] ?? "") !== "manual") {
        error_log("No está en flujo manual");
        return;
    }

    error_log("Paso actual: " . ($estado["paso"] ?? "null"));
    error_log("Texto recibido: " . $text);

    switch ($estado["paso"]) {
        case "manual_filtro_letra":
            $letra = trim($text);
            error_log("Procesando filtro con: " . $letra);
            
            if (strtoupper($letra) === 'TODOS') {
                // Mostrar todos los conductores
                $estado['paso'] = 'manual_menu';
                $estado['manual_page'] = 0;
                $estado['filtro_letra'] = 'todos';
                saveState($chat_id, $estado);
                
                $conn = db();
                $conductores = $conn ? obtenerConductoresAdmin($conn, $chat_id) : [];
                $conn?->close();
                
                if ($conductores) {
                    $kb = manual_kb_grid_paginado($conductores, 'manual_sel_', 0, 'todos');
                    sendMessage($chat_id, "👥 *TODOS LOS CONDUCTORES*\n\nElige un conductor o crea uno nuevo:", $kb);
                } else {
                    sendMessage($chat_id, "❌ No tienes conductores guardados.\n✍️ Escribe el *nombre* del nuevo conductor:");
                    $estado['paso']='manual_nombre_nuevo'; 
                    saveState($chat_id,$estado);
                }
            } else if (preg_match('/^[a-zA-Z]$/', $letra)) {
                // Filtrar por letra específica
                $letra = strtoupper($letra);
                
                $conn = db();
                $conductores = $conn ? obtenerConductoresAdmin($conn, $chat_id) : [];
                $conn?->close();
                
                $conductores_filtrados = manual_filtrar_conductores_por_letra($conductores, $letra);
                
                if (empty($conductores_filtrados)) {
                    sendMessage($chat_id, "❌ No hay conductores que empiecen con la letra *'$letra'*.\n\nEscribe otra letra (A-Z) o *TODOS* para ver todos:");
                } else {
                    $estado['paso'] = 'manual_menu';
                    $estado['manual_page'] = 0;
                    $estado['filtro_letra'] = $letra;
                    saveState($chat_id, $estado);
                    
                    $kb = manual_kb_grid_paginado($conductores_filtrados, 'manual_sel_', 0, $letra);
                    sendMessage($chat_id, "🔍 Conductores con letra *'$letra'*:\n\nElige un conductor o crea uno nuevo:", $kb);
                }
            } else {
                sendMessage($chat_id, "⚠️ Por favor, escribe una *sola letra* (A-Z) o *TODOS* para ver todos los conductores:");
            }
            break;

        // ... resto del código igual ...
    }
}

/* ========= FUNCIÓN PARA FILTRAR CONDUCTORES POR LETRA ========= */
function manual_filtrar_conductores_por_letra(array $conductores, string $letra): array {
    $filtrados = [];
    $letraLower = strtolower($letra);
    
    foreach ($conductores as $conductor) {
        $nombre = $conductor['nombre'] ?? '';
        if (empty($nombre)) continue;
        
        $primeraLetra = strtolower(substr($nombre, 0, 1));
        
        if ($primeraLetra === $letraLower) {
            $filtrados[] = $conductor;
        }
    }
    
    return $filtrados;
}

/* ========= GRID LAYOUT CON PAGINACIÓN MEJORADA ========= */
function manual_kb_grid_paginado(array $items, string $callback_prefix, int $pagina = 0, string $filtro_letra = null): array {
    $kb = ["inline_keyboard" => []];
    $row = [];
    
    // ORDENAR ALFABÉTICAMENTE de la A a la Z
    usort($items, function($a, $b) {
        $nombreA = $a['nombre'] ?? $a;
        $nombreB = $b['nombre'] ?? $b;
        return strcasecmp($nombreA, $nombreB);
    });
    
    // 15 elementos por página (más manejable)
    $items_por_pagina = 15;
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
        $nav_buttons[] = ["text" => "⬅️ Anterior", "callback_data" => "manual_page_" . $pagina . "_" . ($pagina - 1) . ($filtro_letra ? "_" . $filtro_letra : "")];
    }
    
    // Indicador de página central
    if ($total_paginas > 1) {
        $nav_buttons[] = ["text" => "📄 " . ($pagina + 1) . "/" . $total_paginas, "callback_data" => "manual_info"];
    }
    
    if ($pagina < $total_paginas - 1) {
        $nav_buttons[] = ["text" => "Siguiente ➡️", "callback_data" => "manual_page_" . $pagina . "_" . ($pagina + 1) . ($filtro_letra ? "_" . $filtro_letra : "")];
    }
    
    if (!empty($nav_buttons)) {
        $kb["inline_keyboard"][] = $nav_buttons;
    }
    
    // Botón nuevo conductor y volver al filtro
    $kb["inline_keyboard"][] = [
        ["text" => "➕ Nuevo conductor", "callback_data" => "manual_nuevo"],
        ["text" => "🔍 Cambiar letra", "callback_data" => "manual_back_filtro"]
    ];
    
    return $kb;
}

// Las demás funciones permanecen igual...