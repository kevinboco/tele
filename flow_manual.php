<?php
// flow_manual.php - VERSIÓN COMPLETA CON LOGS DETALLADOS
require_once __DIR__.'/helpers.php';

// Función de log específica para manual
function manual_log($chat_id, $mensaje) {
    $log_file = __DIR__ . "/manual_debug.log";
    $hora = date('H:i:s');
    file_put_contents($log_file, "[$hora][Chat:$chat_id] $mensaje\n", FILE_APPEND);
}

function manual_entrypoint($chat_id, $estado) {
    manual_log($chat_id, "=== ENTRYPOINT INICIADO ===");
    
    // Si ya estás en manual, reenvía el paso
    if (!empty($estado) && ($estado['flujo'] ?? '') === 'manual') {
        manual_log($chat_id, "Flujo manual ya activo, reenviando paso: " . ($estado['paso'] ?? 'desconocido'));
        return manual_resend_current_step($chat_id, $estado);
    }
    // Nuevo flujo
    manual_log($chat_id, "Nuevo flujo manual - guardando estado");
    $estado = [
        "flujo" => "manual",
        "paso" => "manual_filtro_conductor",
        "manual_page" => 0
    ];
    saveState($chat_id, $estado);

    manual_log($chat_id, "Enviando mensaje para filtrar conductor");
    sendMessage($chat_id, "✍️ Escribe la *primera letra* del nombre del conductor para filtrar:");
}

/* ========= GRID LAYOUT CON PAGINACIÓN - 1 COLUMNA (CONDUCTORES) ========= */
function manual_kb_grid_paginado(array $items, string $callback_prefix, int $pagina = 0): array {
    $kb = ["inline_keyboard" => []];
    
    // ORDENAR ALFABÉTICAMENTE de la A a la Z
    usort($items, function($a, $b) {
        $nombreA = $a['nombre'] ?? $a;
        $nombreB = $b['nombre'] ?? $b;
        return strcasecmp($nombreA, $nombreB);
    });
    
    // 10 elementos por página (1 columna = más legible)
    $items_por_pagina = 10;
    $total_paginas = ceil(count($items) / $items_por_pagina);
    
    // Items para esta página
    $items_pagina = array_slice($items, $pagina * $items_por_pagina, $items_por_pagina);
    
    // UNA COLUMNA - cada conductor en su propia fila
    foreach ($items_pagina as $item) {
        $id = $item['id'] ?? $item;
        $text = $item['nombre'] ?? $item['ruta'] ?? $item['vehiculo'] ?? $item;
        
        // Mostrar nombre completo sin recortar
        $kb["inline_keyboard"][] = [[
            "text" => $text,
            "callback_data" => $callback_prefix . $id
        ]];
    }
    
    // Botones de navegación
    $nav_buttons = [];
    if ($pagina > 0) {
        $nav_buttons[] = ["text" => "⬅️ Anterior", "callback_data" => "manual_page_" . ($pagina - 1)];
    }
    
    if ($total_paginas > 1) {
        $nav_buttons[] = ["text" => "📄 " . ($pagina + 1) . "/" . $total_paginas, "callback_data" => "manual_info"];
    }
    
    if ($pagina < $total_paginas - 1) {
        $nav_buttons[] = ["text" => "Siguiente ➡️", "callback_data" => "manual_page_" . ($pagina + 1)];
    }
    
    if (!empty($nav_buttons)) {
        $kb["inline_keyboard"][] = $nav_buttons;
    }
    
    $kb["inline_keyboard"][] = [[ "text"=>"➕ Nuevo conductor", "callback_data"=>"manual_nuevo" ]];
    $kb["inline_keyboard"][] = [[ "text"=>"🔙 Volver a filtrar", "callback_data"=>"manual_volver_filtro_conductor" ]];
    
    return $kb;
}

/* ========= GRID PARA RUTAS FILTRADAS - 1 COLUMNA ========= */
function manual_kb_grid_rutas_filtradas(array $items, string $callback_prefix, int $pagina = 0): array {
    $kb = ["inline_keyboard" => []];
    
    // ORDENAR ALFABÉTICAMENTE
    usort($items, function($a, $b) {
        $nombreA = $a['ruta'] ?? $a;
        $nombreB = $b['ruta'] ?? $b;
        return strcasecmp($nombreA, $nombreB);
    });
    
    // 10 elementos por página (1 columna = más legible)
    $items_por_pagina = 10;
    $total_paginas = ceil(count($items) / $items_por_pagina);
    $items_pagina = array_slice($items, $pagina * $items_por_pagina, $items_por_pagina);
    
    // UNA COLUMNA - cada ruta en su propia fila
    foreach ($items_pagina as $item) {
        $id = $item['id'] ?? $item;
        $text = $item['ruta'] ?? $item['nombre'] ?? $item;
        
        // Mostrar ruta completa sin recortar
        $kb["inline_keyboard"][] = [[
            "text" => $text,
            "callback_data" => $callback_prefix . $id
        ]];
    }
    
    // Botones de navegación
    $nav_buttons = [];
    if ($pagina > 0) {
        $nav_buttons[] = ["text" => "⬅️ Anterior", "callback_data" => "manual_page_ruta_" . ($pagina - 1)];
    }
    
    if ($total_paginas > 1) {
        $nav_buttons[] = ["text" => "📄 " . ($pagina + 1) . "/" . $total_paginas, "callback_data" => "manual_info_ruta"];
    }
    
    if ($pagina < $total_paginas - 1) {
        $nav_buttons[] = ["text" => "Siguiente ➡️", "callback_data" => "manual_page_ruta_" . ($pagina + 1)];
    }
    
    if (!empty($nav_buttons)) {
        $kb["inline_keyboard"][] = $nav_buttons;
    }
    
    $kb["inline_keyboard"][] = [[ "text"=>"➕ Nueva ruta", "callback_data"=>"manual_ruta_nueva" ]];
    $kb["inline_keyboard"][] = [[ "text"=>"🔙 Volver a filtrar", "callback_data"=>"manual_volver_filtro_ruta" ]];
    
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

/* ========= FUNCIÓN PARA AGREGAR BOTÓN OMITIR (GENÉRICO) ========= */
function manual_add_skip_button(array $kb, string $callback_data = "manual_skip_image"): array {
    $kb["inline_keyboard"][] = [[ 
        "text" => "⏭️ Omitir", 
        "callback_data" => $callback_data 
    ]];
    return $kb;
}

function manual_resend_current_step($chat_id, $estado) {
    manual_log($chat_id, "Resend current step: " . ($estado['paso'] ?? 'null'));
    $conn = db();
    switch ($estado['paso']) {
        case 'manual_filtro_conductor':
            sendMessage($chat_id, "✍️ Escribe la *primera letra* del nombre del conductor para filtrar:");
            break;
            
        case 'manual_filtro_conductor_sin_resultados':
            $letra = $estado['manual_filtro_letra'] ?? '';
            $kb = [
                "inline_keyboard" => [
                    [
                        ["text" => "➕ Crear nuevo conductor", "callback_data" => "manual_nuevo"],
                        ["text" => "🔙 Volver a intentar", "callback_data" => "manual_volver_filtro_conductor"]
                    ]
                ]
            ];
            sendMessage($chat_id, "⚠️ No se encontraron conductores que empiecen con *" . strtoupper($letra) . "*.\n\n¿Qué deseas hacer?", $kb);
            break;
            
        case 'manual_menu':
            $estado['paso'] = 'manual_filtro_conductor';
            saveState($chat_id, $estado);
            sendMessage($chat_id, "✍️ Escribe la *primera letra* del nombre del conductor para filtrar:");
            break;
            
        case 'manual_nombre_nuevo':
            sendMessage($chat_id, "✍️ Escribe el *nombre* del nuevo conductor:"); 
            break;
            
        case 'manual_filtro_ruta':
            sendMessage($chat_id, "✍️ Escribe la *primera letra* de la ruta para filtrar:");
            break;
            
        case 'manual_filtro_ruta_sin_resultados':
            $letra = $estado['manual_filtro_ruta_letra'] ?? '';
            $kb = [
                "inline_keyboard" => [
                    [
                        ["text" => "➕ Crear nueva ruta", "callback_data" => "manual_ruta_nueva"],
                        ["text" => "🔙 Volver a intentar", "callback_data" => "manual_volver_filtro_ruta"]
                    ]
                ]
            ];
            sendMessage($chat_id, "⚠️ No se encontraron rutas que empiecen con *" . strtoupper($letra) . "*.\n\n¿Qué deseas hacer?", $kb);
            break;
            
        case 'manual_ruta_menu':
            $estado['paso'] = 'manual_filtro_ruta';
            saveState($chat_id, $estado);
            sendMessage($chat_id, "✍️ Escribe la *primera letra* de la ruta para filtrar:");
            break;
            
        case 'manual_ruta_nueva_texto':
            sendMessage($chat_id, "✍️ Escribe la *ruta del viaje*:"); 
            break;
            
        case 'manual_ruta':
            sendMessage($chat_id, "🛣️ Ingresa la *ruta del viaje*:"); 
            break;
            
        case 'manual_fecha':
            $kb = kbFechaManual();
            $kb = manual_add_back_button($kb, 'filtro_ruta');
            sendMessage($chat_id, "📅 Selecciona la *fecha*:", $kb); 
            break;
            
        case 'manual_fecha_mes':
            $anio=$estado["anio"] ?? date("Y");
            $kb = kbMeses($anio);
            $kb = manual_add_back_button($kb, 'fecha');
            sendMessage($chat_id, "📆 Selecciona el *mes*:", $kb); 
            break;
            
        case 'manual_fecha_dia_input':
            $anio=(int)($estado["anio"] ?? date("Y"));
            $mes =(int)($estado["mes"]  ?? date("m"));
            $maxDias=cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
            sendMessage($chat_id, "✍️ Escribe el *día* del mes (1–$maxDias):"); 
            break;
            
        case 'manual_vehiculo_menu':
            $vehiculos = $conn ? obtenerVehiculosAdmin($conn, $chat_id) : [];
            if ($vehiculos) {
                $kb = manual_kb_grid($vehiculos, 'manual_vehiculo_sel_');
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nuevo vehículo", "callback_data"=>"manual_vehiculo_nuevo" ]];
                $kb = manual_add_back_button($kb, 'fecha');
                sendMessage($chat_id, "🚐 Selecciona el *tipo de vehículo* o crea uno nuevo:", $kb);
            } else {
                sendMessage($chat_id, "No tienes vehículos guardados.\n✍️ Escribe el *tipo de vehículo* (ej.: Toyota Hilux 4x4):");
                $estado['paso']='manual_vehiculo_nuevo_texto'; saveState($chat_id,$estado);
            }
            break;
            
        case 'manual_vehiculo_nuevo_texto':
            sendMessage($chat_id, "✍️ Escribe el *tipo de vehículo*:"); 
            break;
            
        case 'manual_empresa_menu':
            $empresas = $conn ? obtenerEmpresasAdmin($conn, $chat_id) : [];
            if ($empresas) {
                $kb = manual_kb_grid($empresas, 'manual_empresa_sel_');
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nueva empresa", "callback_data"=>"manual_empresa_nuevo" ]];
                $kb = manual_add_back_button($kb, 'vehiculo_menu');
                sendMessage($chat_id, "🏢 Selecciona la *empresa* o crea una nueva:", $kb);
            } else {
                sendMessage($chat_id, "No tienes empresas guardadas.\n✍️ Escribe el *nombre de la empresa*:");
                $estado['paso']='manual_empresa_nuevo_texto'; saveState($chat_id,$estado);
            }
            break;
            
        case 'manual_empresa_nuevo_texto':
            sendMessage($chat_id, "✍️ Escribe el *nombre de la empresa*:"); 
            break;
            
        case 'manual_pago_parcial_pregunta':
            $kb = [
                "inline_keyboard" => [
                    [
                        ["text" => "✅ Sí, hay pago parcial", "callback_data" => "manual_pago_si"],
                        ["text" => "❌ No, sin pago parcial", "callback_data" => "manual_pago_no"]
                    ],
                    [
                        ["text" => "⬅️ Volver", "callback_data" => "manual_back_empresa_menu"]
                    ]
                ]
            ];
            sendMessage($chat_id, "💵 ¿Hay *pago parcial* para este viaje?", $kb);
            break;
            
        case 'manual_pago_parcial_monto':
            sendMessage($chat_id, "💰 Escribe el *monto del pago parcial* (ej: 1500000):"); 
            break;
            
        case 'manual_imagen':
            $kb = [
                "inline_keyboard" => [
                    [
                        ["text" => "⬅️ Volver", "callback_data" => "manual_back_pago_menu"]
                    ]
                ]
            ];
            sendMessage($chat_id, "📸 *Envía la foto/factura* del viaje (OBLIGATORIA).\n\nDebes enviar una imagen para continuar.", $kb);
            break;
            
        case 'manual_epicrisis':
            $kb = [
                "inline_keyboard" => [
                    [
                        ["text" => "⬅️ Volver", "callback_data" => "manual_back_imagen"]
                    ]
                ]
            ];
            $kb = manual_add_skip_button($kb, "manual_skip_epicrisis");
            sendMessage($chat_id, "📋 *Epicrisis* (OPCIONAL)\n\nEnvía la foto de la epicrisis o usa *Omitir* para continuar sin ella.", $kb);
            break;
            
        default:
            manual_log($chat_id, "Paso no reconocido: " . ($estado['paso'] ?? 'null'));
            sendMessage($chat_id, "Continuamos donde ibas. Escribe /cancel para reiniciar.");
    }
    $conn?->close();
}

/* ========= FUNCIÓN GRID ORIGINAL (para otras listas: vehículos, empresas) ========= */
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
    manual_log($chat_id, "CALLBACK RECIBIDO: $cb_data");
    
    if (($estado["flujo"] ?? "") !== "manual") {
        manual_log($chat_id, "ERROR: flujo no es manual, es: " . ($estado["flujo"] ?? "null"));
        return;
    }
    
    // ✅ SIEMPRE responder al callback query primero para evitar el "cargando"
    if ($cb_id) {
        manual_log($chat_id, "Respondiendo callback query: $cb_id");
        answerCallbackQuery($cb_id);
    }

    // ========= VOLVER A FILTRAR CONDUCTOR =========
    if ($cb_data === 'manual_volver_filtro_conductor') {
        manual_log($chat_id, "Volver a filtrar conductor");
        $estado['paso'] = 'manual_filtro_conductor';
        $estado['manual_page'] = 0;
        unset($estado['manual_filtro_letra']);
        saveState($chat_id, $estado);
        sendMessage($chat_id, "✍️ Escribe la *primera letra* del nombre del conductor para filtrar:");
        return;
    }

    // ========= VOLVER A FILTRAR RUTA =========
    if ($cb_data === 'manual_volver_filtro_ruta') {
        manual_log($chat_id, "Volver a filtrar ruta");
        $estado['paso'] = 'manual_filtro_ruta';
        $estado['manual_page_ruta'] = 0;
        unset($estado['manual_filtro_ruta_letra']);
        saveState($chat_id, $estado);
        sendMessage($chat_id, "✍️ Escribe la *primera letra* de la ruta para filtrar:");
        return;
    }

    // ========= PAGINACIÓN CONDUCTORES =========
    if (strpos($cb_data, 'manual_page_') === 0 && strpos($cb_data, 'manual_page_ruta_') === false) {
        $pagina = (int)substr($cb_data, strlen('manual_page_'));
        manual_log($chat_id, "Paginación conductores - página: $pagina");
        $estado['manual_page'] = $pagina;
        saveState($chat_id, $estado);
        
        $letra = $estado['manual_filtro_letra'] ?? '';
        $conn = db();
        $conductores = $conn ? obtenerConductoresPorLetra($conn, $chat_id, $letra) : [];
        $conn?->close();
        
        if ($conductores) {
            $kb = manual_kb_grid_paginado($conductores, 'manual_sel_', $pagina);
            sendMessage($chat_id, "Conductores que empiezan con *" . strtoupper($letra) . "*:\nElige uno o crea uno nuevo:", $kb);
        }
        return;
    }

    // ========= PAGINACIÓN RUTAS =========
    if (strpos($cb_data, 'manual_page_ruta_') === 0) {
        $pagina = (int)substr($cb_data, strlen('manual_page_ruta_'));
        manual_log($chat_id, "Paginación rutas - página: $pagina");
        $estado['manual_page_ruta'] = $pagina;
        saveState($chat_id, $estado);
        
        $letra = $estado['manual_filtro_ruta_letra'] ?? '';
        $conn = db();
        $rutas = $conn ? obtenerRutasPorLetra($conn, $chat_id, $letra) : [];
        $conn?->close();
        
        if ($rutas) {
            $kb = manual_kb_grid_rutas_filtradas($rutas, 'manual_ruta_sel_', $pagina);
            sendMessage($chat_id, "Rutas que empiezan con *" . strtoupper($letra) . "*:\nSelecciona una o crea una nueva:", $kb);
        }
        return;
    }

    // ========= INFO PAGINACIÓN =========
    if ($cb_data === 'manual_info') {
        manual_log($chat_id, "Info paginación conductores");
        $letra = $estado['manual_filtro_letra'] ?? '';
        $conn = db();
        $conductores = $conn ? obtenerConductoresPorLetra($conn, $chat_id, $letra) : [];
        $conn?->close();
        return;
    }
    
    if ($cb_data === 'manual_info_ruta') {
        manual_log($chat_id, "Info paginación rutas");
        $letra = $estado['manual_filtro_ruta_letra'] ?? '';
        $conn = db();
        $rutas = $conn ? obtenerRutasPorLetra($conn, $chat_id, $letra) : [];
        $conn?->close();
        return;
    }

    // ========= BOTÓN VOLVER =========
    if (strpos($cb_data, 'manual_back_') === 0) {
        $back_step = substr($cb_data, strlen('manual_back_'));
        manual_log($chat_id, "Botón volver a: $back_step");
        manual_handle_back($chat_id, $estado, $back_step);
        return;
    }

    // ========= OMITIR EPICRISIS =========
    if ($cb_data === 'manual_skip_epicrisis') {
        manual_log($chat_id, "Omitiendo epicrisis");
        $estado['manual_epicrisis'] = null;
        manual_insert_viaje_and_close($chat_id, $estado);
        return;
    }

    // ✅ CORREGIDO: Seleccionar conductor existente
    if (strpos($cb_data, 'manual_sel_') === 0) {
        $idSel = (int)substr($cb_data, strlen('manual_sel_'));
        manual_log($chat_id, "Seleccionando conductor ID: $idSel");
        
        $conn = db(); 
        $row = obtenerConductorAdminPorId($conn, $idSel, $chat_id); 
        $conn?->close();
        
        if (!$row) { 
            manual_log($chat_id, "ERROR: Conductor no encontrado ID: $idSel");
            sendMessage($chat_id, "⚠️ Conductor no encontrado. Vuelve a intentarlo con /manual.");
        } else {
            manual_log($chat_id, "Conductor encontrado: " . $row['nombre']);
            $estado['manual_nombre'] = $row['nombre'];
            $estado['paso'] = 'manual_filtro_ruta'; 
            // Limpiar páginas anteriores
            $estado['manual_page_ruta'] = 0;
            unset($estado['manual_filtro_ruta_letra']);
            saveState($chat_id, $estado);

            manual_log($chat_id, "Estado actualizado, nuevo paso: manual_filtro_ruta");
            sendMessage($chat_id, "👤 Conductor: *{$row['nombre']}*\n\n✍️ Escribe la *primera letra* de la ruta para filtrar:");
        }
        return;
    }

    // Crear nuevo conductor
    if ($cb_data === 'manual_nuevo') {
        manual_log($chat_id, "Crear nuevo conductor");
        $estado['paso'] = 'manual_nombre_nuevo'; 
        saveState($chat_id, $estado);
        sendMessage($chat_id, "✍️ Escribe el *nombre* del nuevo conductor:");
        return;
    }

    // ✅ CORREGIDO: Seleccionar ruta existente
    if (strpos($cb_data, 'manual_ruta_sel_') === 0) {
        $idRuta = (int)substr($cb_data, strlen('manual_ruta_sel_'));
        manual_log($chat_id, "Seleccionando ruta ID: $idRuta");
        
        $conn = db(); 
        $r = obtenerRutaAdminPorId($conn, $idRuta, $chat_id); 
        $conn?->close();
        
        if (!$r) {
            manual_log($chat_id, "ERROR: Ruta no encontrada ID: $idRuta");
            sendMessage($chat_id, "⚠️ Ruta no encontrada. Vuelve a intentarlo.");
        } else {
            manual_log($chat_id, "Ruta encontrada: " . $r['ruta']);
            $estado['manual_ruta'] = $r['ruta'];
            $estado['paso'] = 'manual_fecha'; 
            saveState($chat_id, $estado);
            $kb = kbFechaManual();
            $kb = manual_add_back_button($kb, 'filtro_ruta');
            sendMessage($chat_id, "🛣️ Ruta: *{$r['ruta']}*\n\n📅 Selecciona la *fecha*:", $kb);
        }
        return;
    }

    // Crear nueva ruta
    if ($cb_data === 'manual_ruta_nueva') {
        manual_log($chat_id, "Crear nueva ruta");
        $estado['paso'] = 'manual_ruta_nueva_texto'; 
        saveState($chat_id, $estado);
        sendMessage($chat_id, "✍️ Escribe la *ruta del viaje*:");
        return;
    }

    // Fecha hoy
    if ($cb_data === 'mfecha_hoy') {
        manual_log($chat_id, "Fecha seleccionada: hoy");
        $estado['manual_fecha'] = date("Y-m-d");
        $estado['paso'] = 'manual_vehiculo_menu'; 
        saveState($chat_id, $estado);

        $conn = db(); 
        $vehiculos = $conn ? obtenerVehiculosAdmin($conn, $chat_id) : []; 
        $conn?->close();
        
        if ($vehiculos) {
            $kb = manual_kb_grid($vehiculos, 'manual_vehiculo_sel_');
            $kb["inline_keyboard"][] = [["text" => "➕ Nuevo vehículo", "callback_data" => "manual_vehiculo_nuevo"]];
            $kb = manual_add_back_button($kb, 'fecha');
            sendMessage($chat_id, "🚐 Selecciona el *tipo de vehículo* o crea uno nuevo:", $kb);
        } else {
            manual_log($chat_id, "No hay vehículos, pidiendo texto");
            $estado['paso'] = 'manual_vehiculo_nuevo_texto'; 
            saveState($chat_id, $estado);
            sendMessage($chat_id, "No tienes vehículos guardados.\n✍️ Escribe el *tipo de vehículo* (ej.: Toyota Hilux 4x4):");
        }
        return;
    }
    
    // Fecha otra
    if ($cb_data === 'mfecha_otro') {
        manual_log($chat_id, "Fecha seleccionada: otra fecha");
        $anio = date("Y"); 
        $estado["anio"] = $anio;
        $estado["paso"] = "manual_fecha_mes"; 
        saveState($chat_id, $estado);
        $kb = kbMeses($anio);
        $kb = manual_add_back_button($kb, 'fecha');
        sendMessage($chat_id, "📆 Selecciona el *mes* ($anio):", $kb);
        return;
    }
    
    // Selección de mes
    if (strpos($cb_data, 'mmes_') === 0) {
        $parts = explode('_', $cb_data);
        $estado["anio"] = $parts[1] ?? date("Y");
        $estado["mes"]  = $parts[2] ?? date("m");
        $estado["paso"] = "manual_fecha_dia_input"; 
        saveState($chat_id, $estado);
        $anio=(int)$estado["anio"]; 
        $mes=(int)$estado["mes"];
        $maxDias = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
        manual_log($chat_id, "Mes seleccionado: $mes/$anio, días máximos: $maxDias");
        sendMessage($chat_id, "✍️ Escribe el *día* del mes (1–$maxDias):");
        return;
    }

    // Vehículo seleccionar
    if (strpos($cb_data, 'manual_vehiculo_sel_') === 0) {
        $idVeh = (int)substr($cb_data, strlen('manual_vehiculo_sel_'));
        manual_log($chat_id, "Seleccionando vehículo ID: $idVeh");
        
        $conn = db(); 
        $v = obtenerVehiculoAdminPorId($conn, $idVeh, $chat_id); 
        $conn?->close();
        
        if (!$v) {
            manual_log($chat_id, "ERROR: Vehículo no encontrado ID: $idVeh");
            sendMessage($chat_id, "⚠️ Vehículo no encontrado. Vuelve a intentarlo.");
        } else {
            manual_log($chat_id, "Vehículo encontrado: " . $v['vehiculo']);
            $estado['manual_vehiculo'] = $v['vehiculo'];
            $estado['paso'] = 'manual_empresa_menu'; 
            saveState($chat_id, $estado);

            $conn = db(); 
            $empresas = $conn ? obtenerEmpresasAdmin($conn, $chat_id) : []; 
            $conn?->close();
            
            if ($empresas) {
                $kb = manual_kb_grid($empresas, 'manual_empresa_sel_');
                $kb["inline_keyboard"][] = [["text" => "➕ Nueva empresa", "callback_data" => "manual_empresa_nuevo"]];
                $kb = manual_add_back_button($kb, 'vehiculo_menu');
                sendMessage($chat_id, "🏢 Selecciona la *empresa* o crea una nueva:", $kb);
            } else {
                manual_log($chat_id, "No hay empresas, pidiendo texto");
                $estado['paso'] = 'manual_empresa_nuevo_texto'; 
                saveState($chat_id, $estado);
                sendMessage($chat_id, "No tienes empresas guardadas.\n✍️ Escribe el *nombre de la empresa*:");
            }
        }
        return;
    }
    
    // Vehículo nuevo
    if ($cb_data === 'manual_vehiculo_nuevo') {
        manual_log($chat_id, "Crear nuevo vehículo");
        $estado['paso'] = 'manual_vehiculo_nuevo_texto'; 
        saveState($chat_id, $estado);
        sendMessage($chat_id, "✍️ Escribe el *tipo de vehículo*:");
        return;
    }

    // Empresa seleccionar
    if (strpos($cb_data, 'manual_empresa_sel_') === 0) {
        $idEmp = (int)substr($cb_data, strlen('manual_empresa_sel_'));
        manual_log($chat_id, "Seleccionando empresa ID: $idEmp");
        
        $conn = db(); 
        $e = obtenerEmpresaAdminPorId($conn, $idEmp, $chat_id); 
        $conn?->close();
        
        if (!$e) {
            manual_log($chat_id, "ERROR: Empresa no encontrada ID: $idEmp");
            sendMessage($chat_id, "⚠️ Empresa no encontrada. Vuelve a intentarlo.");
        } else {
            manual_log($chat_id, "Empresa encontrada: " . $e['nombre']);
            $estado['manual_empresa'] = $e['nombre'];
            $estado['paso'] = 'manual_pago_parcial_pregunta'; 
            saveState($chat_id, $estado);
            
            $kb = [
                "inline_keyboard" => [
                    [
                        ["text" => "✅ Sí, hay pago parcial", "callback_data" => "manual_pago_si"],
                        ["text" => "❌ No, sin pago parcial", "callback_data" => "manual_pago_no"]
                    ],
                    [
                        ["text" => "⬅️ Volver", "callback_data" => "manual_back_empresa_menu"]
                    ]
                ]
            ];
            sendMessage($chat_id, "💵 ¿Hay *pago parcial* para este viaje?", $kb);
        }
        return;
    }
    
    // Empresa nueva
    if ($cb_data === 'manual_empresa_nuevo') {
        manual_log($chat_id, "Crear nueva empresa");
        $estado['paso'] = 'manual_empresa_nuevo_texto'; 
        saveState($chat_id, $estado);
        sendMessage($chat_id, "✍️ Escribe el *nombre de la empresa*:");
        return;
    }

    // Pago parcial - Sí
    if ($cb_data === 'manual_pago_si') {
        manual_log($chat_id, "Pago parcial: Sí");
        $estado['paso'] = 'manual_pago_parcial_monto'; 
        saveState($chat_id, $estado);
        sendMessage($chat_id, "💰 Escribe el *monto del pago parcial* (ej: 1500000):");
        return;
    }
    
    // Pago parcial - No
    if ($cb_data === 'manual_pago_no') {
        manual_log($chat_id, "Pago parcial: No");
        $estado['manual_pago_parcial'] = null;
        $estado['paso'] = 'manual_imagen';
        saveState($chat_id, $estado);
        
        $kb = [
            "inline_keyboard" => [
                [
                    ["text" => "⬅️ Volver", "callback_data" => "manual_back_pago_menu"]
                ]
            ]
        ];
        sendMessage($chat_id, "📸 *Envía la foto/factura* del viaje (OBLIGATORIA).\n\nDebes enviar una imagen para continuar.", $kb);
        return;
    }
    
    manual_log($chat_id, "Callback no manejado: $cb_data");
}

/* ========= MANEJO DEL BOTÓN VOLVER ========= */
function manual_handle_back($chat_id, &$estado, $back_step) {
    manual_log($chat_id, "Handle back: $back_step");
    
    switch ($back_step) {
        case 'filtro_conductor':
            $estado['paso'] = 'manual_filtro_conductor';
            unset($estado['manual_nombre'], $estado['manual_filtro_letra']);
            break;
            
        case 'filtro_ruta':
            $estado['paso'] = 'manual_filtro_ruta';
            unset($estado['manual_ruta'], $estado['manual_filtro_ruta_letra']);
            break;
            
        case 'menu':
            $estado['paso'] = 'manual_filtro_conductor';
            unset($estado['manual_nombre'], $estado['manual_filtro_letra']);
            break;
            
        case 'ruta_menu':
            $estado['paso'] = 'manual_filtro_ruta';
            unset($estado['manual_ruta'], $estado['manual_filtro_ruta_letra']);
            break;
            
        case 'fecha':
            $estado['paso'] = 'manual_fecha';
            unset($estado['manual_fecha'], $estado['anio'], $estado['mes']);
            break;
            
        case 'vehiculo_menu':
            $estado['paso'] = 'manual_vehiculo_menu';
            unset($estado['manual_vehiculo']);
            break;
            
        case 'empresa_menu':
            $estado['paso'] = 'manual_empresa_menu';
            unset($estado['manual_empresa']);
            break;
            
        case 'pago_menu':
            $estado['paso'] = 'manual_pago_parcial_pregunta';
            unset($estado['manual_pago_parcial']);
            break;

        case 'imagen':
            $estado['paso'] = 'manual_imagen';
            unset($estado['manual_epicrisis']);
            break;
            
        default:
            $estado['paso'] = 'manual_filtro_conductor';
            break;
    }
    
    saveState($chat_id, $estado);
    manual_resend_current_step($chat_id, $estado);
}

function manual_handle_text($chat_id, &$estado, $text, $photo) {
    manual_log($chat_id, "TEXTO RECIBIDO: '$text', paso actual: " . ($estado["paso"] ?? "null"));
    
    if (($estado["flujo"] ?? "") !== "manual") {
        manual_log($chat_id, "ERROR: flujo no es manual, es: " . ($estado["flujo"] ?? "null"));
        return;
    }

    switch ($estado["paso"]) {
        // ========= FILTRO CONDUCTOR =========
        case "manual_filtro_conductor":
            $letra = mb_substr(trim($text), 0, 1);
            manual_log($chat_id, "Filtrando conductor por letra: '$letra'");
            
            if ($letra === "" || !preg_match('/[a-zA-ZáéíóúÁÉÍÓÚñÑ]/u', $letra)) {
                manual_log($chat_id, "Letra inválida: '$letra'");
                sendMessage($chat_id, "⚠️ Debes escribir una letra válida. Escribe la *primera letra* del nombre del conductor:");
                break;
            }
            
            $estado['manual_filtro_letra'] = $letra;
            $estado['manual_page'] = 0;
            
            $conn = db();
            manual_log($chat_id, "Conexión BD: " . ($conn ? "OK" : "FALLO"));
            
            if ($conn) {
                $conductores = obtenerConductoresPorLetra($conn, $chat_id, $letra);
                manual_log($chat_id, "Conductores encontrados: " . count($conductores));
                manual_log($chat_id, "Primer conductor: " . print_r($conductores[0] ?? "ninguno", true));
            } else {
                $conductores = [];
                manual_log($chat_id, "ERROR: No hay conexión a BD");
            }
            $conn?->close();
            
            if (!empty($conductores)) {
                $estado['paso'] = 'manual_sel_conductor';
                saveState($chat_id, $estado);
                $kb = manual_kb_grid_paginado($conductores, 'manual_sel_', 0);
                sendMessage($chat_id, "Conductores que empiezan con *" . strtoupper($letra) . "*:\nElige uno o crea uno nuevo:", $kb);
            } else {
                $estado['paso'] = 'manual_filtro_conductor_sin_resultados';
                saveState($chat_id, $estado);
                $kb = [
                    "inline_keyboard" => [
                        [
                            ["text" => "➕ Crear nuevo conductor", "callback_data" => "manual_nuevo"],
                            ["text" => "🔙 Volver a intentar", "callback_data" => "manual_volver_filtro_conductor"]
                        ]
                    ]
                ];
                sendMessage($chat_id, "⚠️ No se encontraron conductores que empiecen con *" . strtoupper($letra) . "*.\n\n¿Qué deseas hacer?", $kb);
            }
            break;

        // ========= FILTRO RUTA =========
        case "manual_filtro_ruta":
            $letra = mb_substr(trim($text), 0, 1);
            manual_log($chat_id, "Filtrando ruta por letra: '$letra'");
            
            if ($letra === "" || !preg_match('/[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9]/u', $letra)) {
                manual_log($chat_id, "Letra inválida para ruta: '$letra'");
                sendMessage($chat_id, "⚠️ Debes escribir una letra o número válido. Escribe la *primera letra* de la ruta:");
                break;
            }
            
            $estado['manual_filtro_ruta_letra'] = $letra;
            $estado['manual_page_ruta'] = 0;
            
            $conn = db();
            manual_log($chat_id, "Conexión BD para rutas: " . ($conn ? "OK" : "FALLO"));
            
            if ($conn) {
                $rutas = obtenerRutasPorLetra($conn, $chat_id, $letra);
                manual_log($chat_id, "Rutas encontradas: " . count($rutas));
                manual_log($chat_id, "Primera ruta: " . print_r($rutas[0] ?? "ninguna", true));
            } else {
                $rutas = [];
                manual_log($chat_id, "ERROR: No hay conexión a BD para rutas");
            }
            $conn?->close();
            
            if (!empty($rutas)) {
                $estado['paso'] = 'manual_sel_ruta';
                saveState($chat_id, $estado);
                $kb = manual_kb_grid_rutas_filtradas($rutas, 'manual_ruta_sel_', 0);
                sendMessage($chat_id, "Rutas que empiezan con *" . strtoupper($letra) . "*:\nSelecciona una o crea una nueva:", $kb);
            } else {
                $estado['paso'] = 'manual_filtro_ruta_sin_resultados';
                saveState($chat_id, $estado);
                $kb = [
                    "inline_keyboard" => [
                        [
                            ["text" => "➕ Crear nueva ruta", "callback_data" => "manual_ruta_nueva"],
                            ["text" => "🔙 Volver a intentar", "callback_data" => "manual_volver_filtro_ruta"]
                        ]
                    ]
                ];
                sendMessage($chat_id, "⚠️ No se encontraron rutas que empiecen con *" . strtoupper($letra) . "*.\n\n¿Qué deseas hacer?", $kb);
            }
            break;

        case "manual_nombre_nuevo":
            $nombre = trim($text);
            manual_log($chat_id, "Nuevo conductor nombre: '$nombre'");
            
            if ($nombre==="") { 
                sendMessage($chat_id, "⚠️ El nombre no puede estar vacío. Escribe el *nombre* del nuevo conductor:"); 
                break; 
            }
            
            $conn = db(); 
            if ($conn) { 
                crearConductorAdmin($conn, $chat_id, $nombre); 
                $conn->close(); 
            }
            
            $estado["manual_nombre"] = $nombre;
            $estado["paso"] = "manual_filtro_ruta"; 
            $estado['manual_page_ruta'] = 0;
            unset($estado['manual_filtro_ruta_letra']);
            saveState($chat_id, $estado);

            manual_log($chat_id, "Conductor guardado, pasando a filtro de ruta");
            sendMessage($chat_id, "👤 Conductor guardado: *{$nombre}*\n\n✍️ Escribe la *primera letra* de la ruta para filtrar:");
            break;

        case "manual_ruta_nueva_texto":
            $rutaTxt = trim($text);
            manual_log($chat_id, "Nueva ruta: '$rutaTxt'");
            
            if ($rutaTxt==="") { 
                sendMessage($chat_id, "⚠️ La ruta no puede estar vacía. Escribe la *ruta del viaje*:"); 
                break; 
            }
            
            $conn = db(); 
            if ($conn) { 
                crearRutaAdmin($conn, $chat_id, $rutaTxt); 
                $conn->close(); 
            }
            
            $estado["manual_ruta"] = $rutaTxt;
            $estado["paso"] = "manual_fecha"; 
            saveState($chat_id, $estado);
            $kb = kbFechaManual();
            $kb = manual_add_back_button($kb, 'filtro_ruta');
            manual_log($chat_id, "Ruta guardada, pasando a fecha");
            sendMessage($chat_id, "🛣️ Ruta guardada: *{$rutaTxt}*\n\n📅 Selecciona la *fecha*:", $kb);
            break;

        case "manual_fecha_dia_input":
            $anio=(int)($estado["anio"] ?? date("Y")); 
            $mes=(int)($estado["mes"] ?? date("m"));
            manual_log($chat_id, "Ingresando día para $mes/$anio: '$text'");
            
            if (!preg_match('/^\d{1,2}$/', $text)) {
                $max=cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
                sendMessage($chat_id, "⚠️ Debe ser un número entre 1 y $max. Escribe el *día* del mes:"); 
                break;
            }
            $dia=(int)$text; 
            $max=cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
            if ($dia<1 || $dia>$max) { 
                sendMessage($chat_id, "⚠️ El día debe estar entre 1 y $max. Inténtalo de nuevo:"); 
                break; 
            }
            $estado["manual_fecha"] = sprintf("%04d-%02d-%02d",$anio,$mes,$dia);
            manual_log($chat_id, "Fecha seleccionada: " . $estado["manual_fecha"]);

            $estado['paso'] = 'manual_vehiculo_menu'; 
            saveState($chat_id, $estado);
            
            $conn = db(); 
            $vehiculos = $conn ? obtenerVehiculosAdmin($conn, $chat_id) : []; 
            $conn?->close();
            
            if ($vehiculos) {
                $kb = manual_kb_grid($vehiculos, 'manual_vehiculo_sel_');
                $kb["inline_keyboard"][] = [["text"=>"➕ Nuevo vehículo", "callback_data"=>"manual_vehiculo_nuevo"]];
                $kb = manual_add_back_button($kb, 'fecha');
                sendMessage($chat_id, "🚐 Selecciona el *tipo de vehículo* o crea uno nuevo:", $kb);
            } else {
                manual_log($chat_id, "No hay vehículos, pidiendo texto");
                $estado['paso']='manual_vehiculo_nuevo_texto'; 
                saveState($chat_id, $estado);
                sendMessage($chat_id, "No tienes vehículos guardados.\n✍️ Escribe el *tipo de vehículo* (ej.: Toyota Hilux 4x4):");
            }
            break;

        case "manual_vehiculo_nuevo_texto":
            $vehTxt = trim($text);
            manual_log($chat_id, "Nuevo vehículo: '$vehTxt'");
            
            if ($vehTxt==="") { 
                sendMessage($chat_id, "⚠️ El *tipo de vehículo* no puede estar vacío. Escríbelo nuevamente:"); 
                break; 
            }
            
            $conn = db(); 
            if ($conn) { 
                crearVehiculoAdmin($conn, $chat_id, $vehTxt); 
                $conn->close(); 
            }
            
            $estado["manual_vehiculo"] = $vehTxt;
            $estado['paso'] = 'manual_empresa_menu'; 
            saveState($chat_id, $estado);

            $conn = db(); 
            $empresas = $conn ? obtenerEmpresasAdmin($conn, $chat_id) : []; 
            $conn?->close();
            
            if ($empresas) {
                $kb = manual_kb_grid($empresas, 'manual_empresa_sel_');
                $kb["inline_keyboard"][] = [["text"=>"➕ Nueva empresa", "callback_data"=>"manual_empresa_nuevo"]];
                $kb = manual_add_back_button($kb, 'vehiculo_menu');
                sendMessage($chat_id, "🏢 Selecciona la *empresa* o crea una nueva:", $kb);
            } else {
                manual_log($chat_id, "No hay empresas, pidiendo texto");
                $estado['paso']='manual_empresa_nuevo_texto'; 
                saveState($chat_id, $estado);
                sendMessage($chat_id, "No tienes empresas guardadas.\n✍️ Escribe el *nombre de la empresa*:");
            }
            break;

        case "manual_empresa_nuevo_texto":
            $empTxt = trim($text);
            manual_log($chat_id, "Nueva empresa: '$empTxt'");
            
            if ($empTxt==="") { 
                sendMessage($chat_id, "⚠️ El *nombre de la empresa* no puede estar vacío. Escríbelo nuevamente:"); 
                break; 
            }
            
            $conn = db(); 
            if ($conn) { 
                crearEmpresaAdmin($conn, $chat_id, $empTxt); 
                $conn->close(); 
            }
            
            $estado["manual_empresa"] = $empTxt;
            $estado['paso'] = 'manual_pago_parcial_pregunta'; 
            saveState($chat_id, $estado);
            
            $kb = [
                "inline_keyboard" => [
                    [
                        ["text" => "✅ Sí, hay pago parcial", "callback_data" => "manual_pago_si"],
                        ["text" => "❌ No, sin pago parcial", "callback_data" => "manual_pago_no"]
                    ],
                    [
                        ["text" => "⬅️ Volver", "callback_data" => "manual_back_empresa_menu"]
                    ]
                ]
            ];
            sendMessage($chat_id, "💵 ¿Hay *pago parcial* para este viaje?", $kb);
            break;

        case "manual_pago_parcial_monto":
            $monto = trim($text);
            manual_log($chat_id, "Pago parcial monto: '$monto'");
            
            if (!is_numeric($monto) || $monto <= 0) {
                sendMessage($chat_id, "⚠️ El monto debe ser un número positivo (ej: 1500000). Escribe el *monto del pago parcial*:");
                break;
            }
            
            $estado["manual_pago_parcial"] = (int)$monto;
            $estado['paso'] = 'manual_imagen';
            saveState($chat_id, $estado);
            
            $kb = [
                "inline_keyboard" => [
                    [
                        ["text" => "⬅️ Volver", "callback_data" => "manual_back_pago_menu"]
                    ]
                ]
            ];
            sendMessage($chat_id, "📸 *Envía la foto/factura* del viaje (OBLIGATORIA).\n\nDebes enviar una imagen para continuar.", $kb);
            break;

        case "manual_imagen":
            manual_log($chat_id, "Procesando evidencia (foto)");
            manual_process_evidencia($chat_id, $estado, $photo);
            break;

        case "manual_epicrisis":
            manual_log($chat_id, "Procesando epicrisis");
            manual_process_epicrisis($chat_id, $estado, $photo);
            break;

        default:
            manual_log($chat_id, "Paso no reconocido en handle_text: " . ($estado["paso"] ?? "null"));
            sendMessage($chat_id, "❌ Usa */manual* para registrar un viaje manual. */cancel* para reiniciar.");
            clearState($chat_id);
            break;
    }
}

/* ========= PROCESAR IMAGEN DE EVIDENCIA (OBLIGATORIA) ========= */
function manual_process_evidencia($chat_id, &$estado, $photo) {
    manual_log($chat_id, "=== PROCESANDO EVIDENCIA ===");
    
    $file_id = null;
    
    if (!empty($photo) && is_array($photo)) {
        $tmp = end($photo);
        if (is_array($tmp) && !empty($tmp['file_id'])) {
            $file_id = $tmp['file_id'];
            manual_log($chat_id, "File ID de foto: " . substr($file_id, 0, 20) . "...");
        }
        reset($photo);
    }
    
    $doc = $GLOBALS['update']['message']['document'] ?? null;
    if (!$file_id && $doc && isset($doc['mime_type']) && strpos($doc['mime_type'], 'image/') === 0) {
        $file_id = $doc['file_id'];
        manual_log($chat_id, "File ID de documento: " . substr($file_id, 0, 20) . "...");
    }
    
    if (!$file_id) { 
        manual_log($chat_id, "ERROR: No se detectó imagen");
        $kb = [
            "inline_keyboard" => [
                [
                    ["text" => "⬅️ Volver", "callback_data" => "manual_back_pago_menu"]
                ]
            ]
        ];
        sendMessage($chat_id, "⚠️ La foto de evidencia es *OBLIGATORIA*. Debes enviar una imagen para continuar.", $kb);
        return;
    }

    global $TOKEN;
    manual_log($chat_id, "Obteniendo información del archivo desde Telegram");
    $info = @json_decode(@file_get_contents("https://api.telegram.org/bot{$TOKEN}/getFile?file_id=".urlencode($file_id)), true);
    
    if (!$info || empty($info['ok']) || empty($info['result']['file_path'])) {
        manual_log($chat_id, "ERROR: No se pudo obtener file_path de Telegram");
        sendMessage($chat_id, "❌ No pude obtener el archivo desde Telegram.");
        return;
    }
    
    $file_path = $info['result']['file_path'];
    $fileUrl   = "https://api.telegram.org/file/bot{$TOKEN}/{$file_path}";
    manual_log($chat_id, "URL del archivo: " . substr($fileUrl, 0, 80) . "...");

    $uploads = __DIR__ . "/uploads/";
    if (!is_dir($uploads)) {
        manual_log($chat_id, "Creando directorio uploads");
        @mkdir($uploads, 0775, true);
    }
    
    $nombreArchivo = time() . "_evidencia_" . basename($file_path);
    $destino       = $uploads . $nombreArchivo;
    manual_log($chat_id, "Guardando en: $destino");

    $ok = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($fileUrl);
        $fp = fopen($destino, 'wb');
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30
        ]);
        $ok = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        manual_log($chat_id, "CURL - HTTP Code: $code, Resultado: " . ($ok ? "OK" : "FALLO"));
        if ($code !== 200) $ok = false;
    } else {
        $data = @file_get_contents($fileUrl);
        if ($data !== false) {
            $ok = (file_put_contents($destino, $data) !== false);
            manual_log($chat_id, "file_get_contents - Resultado: " . ($ok ? "OK" : "FALLO"));
        }
    }
    
    if (!$ok || !file_exists($destino)) {
        manual_log($chat_id, "ERROR: No se pudo guardar la imagen");
        sendMessage($chat_id, "❌ No pude guardar la imagen. Reenvíala, por favor.");
        return;
    }

    manual_log($chat_id, "Evidencia guardada exitosamente: $nombreArchivo");
    $estado['manual_imagen'] = $nombreArchivo;
    $estado['paso'] = 'manual_epicrisis';
    saveState($chat_id, $estado);
    
    $kb = [
        "inline_keyboard" => [
            [
                ["text" => "⬅️ Volver", "callback_data" => "manual_back_imagen"]
            ]
        ]
    ];
    $kb = manual_add_skip_button($kb, "manual_skip_epicrisis");
    sendMessage($chat_id, "✅ Evidencia guardada.\n\n📋 *Epicrisis* (OPCIONAL)\n\nEnvía la foto de la epicrisis o usa *Omitir* para continuar sin ella.", $kb);
}

/* ========= PROCESAR IMAGEN DE EPICRISIS (OPCIONAL) ========= */
function manual_process_epicrisis($chat_id, &$estado, $photo) {
    manual_log($chat_id, "=== PROCESANDO EPICRISIS ===");
    
    $file_id = null;
    
    if (!empty($photo) && is_array($photo)) {
        $tmp = end($photo);
        if (is_array($tmp) && !empty($tmp['file_id'])) {
            $file_id = $tmp['file_id'];
            manual_log($chat_id, "File ID epicrisis: " . substr($file_id, 0, 20) . "...");
        }
        reset($photo);
    }
    
    $doc = $GLOBALS['update']['message']['document'] ?? null;
    if (!$file_id && $doc && isset($doc['mime_type']) && strpos($doc['mime_type'], 'image/') === 0) {
        $file_id = $doc['file_id'];
        manual_log($chat_id, "File ID documento epicrisis: " . substr($file_id, 0, 20) . "...");
    }
    
    if (!$file_id) { 
        manual_log($chat_id, "No se envió epicrisis (opcional)");
        $estado['manual_epicrisis'] = null;
        manual_insert_viaje_and_close($chat_id, $estado);
        return;
    }

    global $TOKEN;
    $info = @json_decode(@file_get_contents("https://api.telegram.org/bot{$TOKEN}/getFile?file_id=".urlencode($file_id)), true);
    
    if (!$info || empty($info['ok']) || empty($info['result']['file_path'])) {
        manual_log($chat_id, "ERROR: No se pudo obtener file_path de epicrisis");
        $estado['manual_epicrisis'] = null;
        manual_insert_viaje_and_close($chat_id, $estado);
        return;
    }
    
    $file_path = $info['result']['file_path'];
    $fileUrl   = "https://api.telegram.org/file/bot{$TOKEN}/{$file_path}";

    $uploads = __DIR__ . "/uploads/";
    if (!is_dir($uploads)) @mkdir($uploads, 0775, true);
    
    $nombreArchivo = time() . "_epicrisis_" . basename($file_path);
    $destino       = $uploads . $nombreArchivo;
    manual_log($chat_id, "Guardando epicrisis en: $destino");

    $ok = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($fileUrl);
        $fp = fopen($destino, 'wb');
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30
        ]);
        $ok = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        if ($code !== 200) $ok = false;
    } else {
        $data = @file_get_contents($fileUrl);
        if ($data !== false) {
            $ok = (file_put_contents($destino, $data) !== false);
        }
    }
    
    if (!$ok || !file_exists($destino)) {
        manual_log($chat_id, "ERROR: No se pudo guardar la epicrisis");
        $estado['manual_epicrisis'] = null;
    } else {
        manual_log($chat_id, "Epicrisis guardada: $nombreArchivo");
        $estado['manual_epicrisis'] = $nombreArchivo;
    }
    
    manual_insert_viaje_and_close($chat_id, $estado);
}

/* ========= INSERTAR VIAJE Y CERRAR FLUJO ========= */
function manual_insert_viaje_and_close($chat_id, &$estado) {
    manual_log($chat_id, "=== INSERTANDO VIAJE EN BD ===");
    
    $conn = db();
    if (!$conn) { 
        manual_log($chat_id, "ERROR: No hay conexión a BD");
        sendMessage($chat_id, "❌ Error de conexión a la base de datos."); 
        clearState($chat_id); 
        return; 
    }
    
    // Asegurar que todos los campos existan
    $nombre = $estado["manual_nombre"] ?? 'Desconocido';
    $ruta = $estado["manual_ruta"] ?? 'No especificada';
    $fecha = $estado["manual_fecha"] ?? date("Y-m-d");
    $vehiculo = $estado["manual_vehiculo"] ?? 'No especificado';
    $empresa = $estado["manual_empresa"] ?? 'No especificada';
    $imagen = $estado["manual_imagen"] ?? null;
    $epicrisis = $estado["manual_epicrisis"] ?? null;
    $pago_parcial = $estado["manual_pago_parcial"] ?? null;
    
    manual_log($chat_id, "Datos a insertar: nombre=$nombre, ruta=$ruta, fecha=$fecha, vehiculo=$vehiculo, empresa=$empresa, pago_parcial=$pago_parcial");
    
    $stmt = $conn->prepare("INSERT INTO viajes (nombre, ruta, fecha, cedula, tipo_vehiculo, empresa, imagen, epicrisis, pago_parcial) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssi", $nombre, $ruta, $fecha, $vehiculo, $empresa, $imagen, $epicrisis, $pago_parcial);
    
    if ($stmt->execute()) {
        manual_log($chat_id, "✅ Viaje insertado correctamente");
        $mensaje = "✅ *Viaje registrado exitosamente*\n\n" .
                   "👤 *Conductor:* " . $nombre . "\n" .
                   "🛣️ *Ruta:* " . $ruta . "\n" .
                   "📅 *Fecha:* " . $fecha . "\n" .
                   "🚐 *Vehículo:* " . $vehiculo . "\n" .
                   "🏢 *Empresa:* " . $empresa;
        
        if ($pago_parcial) {
            $monto_formateado = number_format($pago_parcial, 0, ',', '.');
            $mensaje .= "\n💰 *Pago parcial:* $" . $monto_formateado;
        }
        
        $mensaje .= "\n📸 *Evidencia:* " . ($imagen ? "✅ Adjuntada" : "❌ No adjuntada");
        $mensaje .= "\n📋 *Epicrisis:* " . ($epicrisis ? "✅ Adjuntada" : "❌ No adjuntada");
        $mensaje .= "\n\nAtajos rápidos: /agg /manual /p";
        
        sendMessage($chat_id, $mensaje);
    } else {
        manual_log($chat_id, "❌ ERROR al insertar: " . $conn->error);
        sendMessage($chat_id, "❌ Error al guardar el viaje: " . $conn->error);
    }
    $stmt->close(); 
    $conn->close();
    clearState($chat_id);
    manual_log($chat_id, "=== FLUJO MANUAL FINALIZADO ===");
}