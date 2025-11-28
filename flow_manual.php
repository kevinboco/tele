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

function manual_handle_callback($chat_id, &$estado, $cb_data, $cb_id=null) {
    if (($estado["flujo"] ?? "") !== "manual") return;

    error_log("Callback recibido: " . $cb_data);

    // ========= PAGINACIÓN =========
    if (strpos($cb_data, 'manual_page_') === 0) {
        $parts = explode('_', $cb_data);
        if (count($parts) >= 4) {
            $pagina_actual = (int)$parts[2];
            $nueva_pagina = (int)$parts[3];
            $filtro_letra = $parts[4] ?? null;
            
            $estado['manual_page'] = $nueva_pagina;
            saveState($chat_id, $estado);
            
            $conn = db();
            $conductores = $conn ? obtenerConductoresAdmin($conn, $chat_id) : [];
            $conn?->close();
            
            // Aplicar filtro si existe
            if ($filtro_letra && $filtro_letra !== 'todos') {
                $conductores = manual_filtrar_conductores_por_letra($conductores, $filtro_letra);
            }
            
            if ($conductores) {
                $kb = manual_kb_grid_paginado($conductores, 'manual_sel_', $nueva_pagina, $filtro_letra);
                
                $mensaje = "👥 *TODOS LOS CONDUCTORES*\n\nElige un conductor o crea uno nuevo:";
                if ($filtro_letra && $filtro_letra !== 'todos') {
                    $mensaje = "🔍 Conductores con letra *'$filtro_letra'*:\n\nElige un conductor o crea uno nuevo:";
                }
                
                sendMessage($chat_id, $mensaje, $kb);
            }
        }
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    // ========= INFO PAGINACIÓN =========
    if ($cb_data === 'manual_info') {
        if ($cb_id) answerCallbackQuery($cb_id, "Información de página");
        return;
    }

    // ========= BOTÓN VOLVER AL FILTRO =========
    if ($cb_data === 'manual_back_filtro') {
        $estado['paso'] = 'manual_filtro_letra';
        unset($estado['filtro_letra'], $estado['manual_page']);
        saveState($chat_id, $estado);
        sendMessage($chat_id, "🔍 *FILTRAR CONDUCTOR POR LETRA*\n\nEscribe una *letra* para filtrar los conductores (A-Z):\nO escribe *TODOS* para ver todos los conductores.");
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    // ========= BOTÓN VOLVER =========
    if (strpos($cb_data, 'manual_back_') === 0) {
        $back_step = substr($cb_data, strlen('manual_back_'));
        manual_handle_back($chat_id, $estado, $back_step);
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    // ========= NUEVO CONDUCTOR =========
    if ($cb_data === 'manual_nuevo') {
        $estado['paso'] = 'manual_nombre_nuevo'; 
        saveState($chat_id,$estado);
        sendMessage($chat_id, "✍️ Escribe el *nombre* del nuevo conductor:");
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    // ========= SELECCIONAR CONDUCTOR EXISTENTE =========
    if (strpos($cb_data, 'manual_sel_') === 0) {
        $idSel = (int)substr($cb_data, strlen('manual_sel_'));
        $conn = db(); 
        $row = obtenerConductorAdminPorId($conn, $idSel, $chat_id); 
        $conn?->close();
        
        if (!$row) { 
            sendMessage($chat_id, "⚠️ Conductor no encontrado. Vuelve a intentarlo con /manual."); 
        } else {
            $estado['manual_nombre'] = $row['nombre'];
            $estado['paso'] = 'manual_ruta_menu'; 
            saveState($chat_id,$estado);

            // Cargar rutas frescas desde BD
            $conn = db(); 
            $rutas = $conn ? obtenerRutasAdmin($conn, $chat_id) : []; 
            $conn?->close();
            
            if ($rutas) {
                $kb = manual_kb_grid($rutas, 'manual_ruta_sel_');
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nueva ruta", "callback_data"=>"manual_ruta_nueva" ]];
                $kb = manual_add_back_button($kb, 'menu');
                sendMessage($chat_id, "👤 Conductor: *{$row['nombre']}*\n\nSelecciona una *ruta* o crea una nueva:", $kb);
            } else {
                $estado['paso']='manual_ruta_nueva_texto'; 
                saveState($chat_id,$estado);
                sendMessage($chat_id, "👤 Conductor: *{$row['nombre']}*\n\n✍️ Escribe la *ruta del viaje*:");
            }
        }
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    // ... resto de los callbacks para rutas, fechas, etc. permanecen igual ...

    if ($cb_id) answerCallbackQuery($cb_id);
}

/* ========= FUNCIÓN PARA AGREGAR BOTÓN VOLVER ========= */
function manual_add_back_button(array $kb, string $back_step): array {
    $kb["inline_keyboard"][] = [[ 
        "text" => "⬅️ Volver", 
        "callback_data" => "manual_back_" . $back_step 
    ]];
    return $kb;
}

/* ========= MANEJO DEL BOTÓN VOLVER ========= */
function manual_handle_back($chat_id, &$estado, $back_step) {
    switch ($back_step) {
        case 'filtro':
            $estado['paso'] = 'manual_filtro_letra';
            unset($estado['filtro_letra'], $estado['manual_page']);
            break;
            
        case 'menu':
            $estado['paso'] = 'manual_menu';
            break;
            
        case 'ruta_menu':
            $estado['paso'] = 'manual_ruta_menu';
            break;
            
        case 'fecha':
            $estado['paso'] = 'manual_fecha';
            break;
            
        case 'vehiculo_menu':
            $estado['paso'] = 'manual_vehiculo_menu';
            break;
            
        default:
            $estado['paso'] = 'manual_filtro_letra';
            unset($estado['filtro_letra'], $estado['manual_page']);
            break;
    }
    
    saveState($chat_id, $estado);
    manual_resend_current_step($chat_id, $estado);
}

// Las demás funciones permanecen igual...