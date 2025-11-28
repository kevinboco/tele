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
            
        case 'manual_nombre_nuevo':
            sendMessage($chat_id, "✍️ Escribe el *nombre* del nuevo conductor:"); 
            break;
            
        case 'manual_ruta_menu':
            // Cargar rutas frescas desde BD
            $rutas = $conn ? obtenerRutasAdmin($conn, $chat_id) : [];
            if ($rutas) {
                $kb = manual_kb_grid($rutas, 'manual_ruta_sel_');
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nueva ruta", "callback_data"=>"manual_ruta_nueva" ]];
                $kb = manual_add_back_button($kb, 'menu');
                sendMessage($chat_id, "Selecciona una *ruta* o crea una nueva:", $kb);
            } else {
                sendMessage($chat_id, "No tienes rutas guardadas.\n✍️ Escribe la *ruta del viaje*:");
                $estado['paso']='manual_ruta_nueva_texto'; 
                saveState($chat_id,$estado);
            }
            break;
            
        case 'manual_ruta_nueva_texto':
            sendMessage($chat_id, "✍️ Escribe la *ruta del viaje*:"); 
            break;
            
        case 'manual_fecha':
            $kb = kbFechaManual();
            $kb = manual_add_back_button($kb, 'ruta_menu');
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
            // Cargar vehículos frescos desde BD
            $vehiculos = $conn ? obtenerVehiculosAdmin($conn, $chat_id) : [];
            if ($vehiculos) {
                $kb = manual_kb_grid($vehiculos, 'manual_vehiculo_sel_');
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nuevo vehículo", "callback_data"=>"manual_vehiculo_nuevo" ]];
                $kb = manual_add_back_button($kb, 'fecha');
                sendMessage($chat_id, "🚐 Selecciona el *tipo de vehículo* o crea uno nuevo:", $kb);
            } else {
                sendMessage($chat_id, "No tienes vehículos guardados.\n✍️ Escribe el *tipo de vehículo* (ej.: Toyota Hilux 4x4):");
                $estado['paso']='manual_vehiculo_nuevo_texto'; 
                saveState($chat_id,$estado);
            }
            break;
            
        case 'manual_vehiculo_nuevo_texto':
            sendMessage($chat_id, "✍️ Escribe el *tipo de vehículo*:"); 
            break;
            
        case 'manual_empresa_menu':
            // Cargar empresas frescas desde BD
            $empresas = $conn ? obtenerEmpresasAdmin($conn, $chat_id) : [];
            if ($empresas) {
                $kb = manual_kb_grid($empresas, 'manual_empresa_sel_');
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nueva empresa", "callback_data"=>"manual_empresa_nuevo" ]];
                $kb = manual_add_back_button($kb, 'vehiculo_menu');
                sendMessage($chat_id, "🏢 Selecciona la *empresa* o crea una nueva:", $kb);
            } else {
                sendMessage($chat_id, "No tienes empresas guardadas.\n✍️ Escribe el *nombre de la empresa*:");
                $estado['paso']='manual_empresa_nuevo_texto'; 
                saveState($chat_id,$estado);
            }
            break;
            
        case 'manual_empresa_nuevo_texto':
            sendMessage($chat_id, "✍️ Escribe el *nombre de la empresa*:"); 
            break;
            
        default:
            sendMessage($chat_id, "Continuamos donde ibas. Escribe /cancel para reiniciar.");
    }
    $conn?->close();
}

function manual_handle_text($chat_id, &$estado, $text, $photo) {
    if (($estado["flujo"] ?? "") !== "manual") {
        return;
    }

    $text = trim($text);

    switch ($estado["paso"]) {
        case "manual_filtro_letra":
            if (strtoupper($text) === 'TODOS') {
                // Mostrar todos los conductores
                $estado['paso'] = 'manual_menu';
                $estado['manual_page'] = 0;
                $estado['filtro_letra'] = 'todos';
                saveState($chat_id, $estado);
                
                $conn = db();
                $conductores = $conn ? obtenerConductoresAdmin($conn, $chat_id) : [];
                $conn?->close();
                
                if ($conductores && count($conductores) > 0) {
                    $kb = manual_kb_grid_paginado($conductores, 'manual_sel_', 0, 'todos');
                    sendMessage($chat_id, "👥 *TODOS LOS CONDUCTORES*\n\nElige un conductor o crea uno nuevo:", $kb);
                } else {
                    sendMessage($chat_id, "❌ No tienes conductores guardados.\n✍️ Escribe el *nombre* del nuevo conductor:");
                    $estado['paso']='manual_nombre_nuevo'; 
                    saveState($chat_id,$estado);
                }
            } else if (preg_match('/^[a-zA-Z]$/', $text)) {
                // Filtrar por letra específica
                $letra = strtoupper($text);
                
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

        case "manual_nombre_nuevo":
            if ($text === "") { 
                sendMessage($chat_id, "⚠️ El nombre no puede estar vacío. Escribe el *nombre* del nuevo conductor:"); 
                break; 
            }
            
            // Guardar en BD
            $conn = db(); 
            if ($conn) { 
                crearConductorAdmin($conn, $chat_id, $text); 
                $conn->close(); 
            }
            
            $estado["manual_nombre"] = $text;
            $estado["paso"] = "manual_ruta_menu"; 
            saveState($chat_id,$estado);

            // Cargar rutas frescas desde BD
            $conn = db(); 
            $rutas = $conn ? obtenerRutasAdmin($conn, $chat_id) : []; 
            $conn?->close();
            
            if ($rutas && count($rutas) > 0) {
                $kb = manual_kb_grid($rutas, 'manual_ruta_sel_');
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nueva ruta", "callback_data"=>"manual_ruta_nueva" ]];
                $kb = manual_add_back_button($kb, 'menu');
                sendMessage($chat_id, "👤 Conductor guardado: *{$text}*\n\nSelecciona una *ruta* o crea una nueva:", $kb);
            } else {
                $estado['paso']='manual_ruta_nueva_texto'; 
                saveState($chat_id,$estado);
                sendMessage($chat_id, "👤 Conductor guardado: *{$text}*\n\n✍️ Escribe la *ruta del viaje*:");
            }
            break;

        case "manual_ruta_nueva_texto":
            if ($text === "") { 
                sendMessage($chat_id, "⚠️ La ruta no puede estar vacía. Escribe la *ruta del viaje*:"); 
                break; 
            }
            
            // Guardar en BD
            $conn = db(); 
            if ($conn) { 
                crearRutaAdmin($conn, $chat_id, $text); 
                $conn->close(); 
            }
            
            $estado["manual_ruta"] = $text;
            $estado["paso"] = "manual_fecha"; 
            saveState($chat_id,$estado);
            
            $kb = kbFechaManual();
            $kb = manual_add_back_button($kb, 'ruta_menu');
            sendMessage($chat_id, "🛣️ Ruta guardada: *{$text}*\n\n📅 Selecciona la *fecha*:", $kb);
            break;

        case "manual_fecha_dia_input":
            $anio = (int)($estado["anio"] ?? date("Y")); 
            $mes = (int)($estado["mes"] ?? date("m"));
            
            if (!preg_match('/^\d{1,2}$/', $text)) {
                $max = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
                sendMessage($chat_id, "⚠️ Debe ser un número entre 1 y $max. Escribe el *día* del mes:"); 
                break;
            }
            
            $dia = (int)$text; 
            $max = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
            
            if ($dia < 1 || $dia > $max) { 
                sendMessage($chat_id, "⚠️ El día debe estar entre 1 y $max. Inténtalo de nuevo:"); 
                break; 
            }
            
            $estado["manual_fecha"] = sprintf("%04d-%02d-%02d", $anio, $mes, $dia);
            $estado['paso'] = 'manual_vehiculo_menu'; 
            saveState($chat_id,$estado);
            
            // Cargar vehículos frescos desde BD
            $conn = db(); 
            $vehiculos = $conn ? obtenerVehiculosAdmin($conn, $chat_id) : []; 
            $conn?->close();
            
            if ($vehiculos && count($vehiculos) > 0) {
                $kb = manual_kb_grid($vehiculos, 'manual_vehiculo_sel_');
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nuevo vehículo", "callback_data"=>"manual_vehiculo_nuevo" ]];
                $kb = manual_add_back_button($kb, 'fecha');
                sendMessage($chat_id, "🚐 Selecciona el *tipo de vehículo* o crea uno nuevo:", $kb);
            } else {
                $estado['paso']='manual_vehiculo_nuevo_texto'; 
                saveState($chat_id,$estado);
                sendMessage($chat_id, "No tienes vehículos guardados.\n✍️ Escribe el *tipo de vehículo* (ej.: Toyota Hilux 4x4):");
            }
            break;

        case "manual_vehiculo_nuevo_texto":
            if ($text === "") { 
                sendMessage($chat_id, "⚠️ El *tipo de vehículo* no puede estar vacío. Escríbelo nuevamente:"); 
                break; 
            }
            
            // Guardar en BD
            $conn = db(); 
            if ($conn) { 
                crearVehiculoAdmin($conn, $chat_id, $text); 
                $conn->close(); 
            }
            
            $estado["manual_vehiculo"] = $text;
            $estado['paso'] = 'manual_empresa_menu'; 
            saveState($chat_id,$estado);

            // Cargar empresas frescas desde BD
            $conn = db(); 
            $emp = $conn ? obtenerEmpresasAdmin($conn, $chat_id) : []; 
            $conn?->close();
            
            if ($emp && count($emp) > 0) {
                $kb = manual_kb_grid($emp, 'manual_empresa_sel_');
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nueva empresa", "callback_data"=>"manual_empresa_nuevo" ]];
                $kb = manual_add_back_button($kb, 'vehiculo_menu');
                sendMessage($chat_id, "🏢 Selecciona la *empresa* o crea una nueva:", $kb);
            } else {
                $estado['paso']='manual_empresa_nuevo_texto'; 
                saveState($chat_id,$estado);
                sendMessage($chat_id, "No tienes empresas guardadas.\n✍️ Escribe el *nombre de la empresa*:");
            }
            break;

        case "manual_empresa_nuevo_texto":
            if ($text === "") { 
                sendMessage($chat_id, "⚠️ El *nombre de la empresa* no puede estar vacío. Escríbelo nuevamente:"); 
                break; 
            }
            
            // Guardar en BD
            $conn = db(); 
            if ($conn) { 
                crearEmpresaAdmin($conn, $chat_id, $text); 
                $conn->close(); 
            }
            
            $estado["manual_empresa"] = $text;
            manual_insert_viaje_and_close($chat_id, $estado);
            break;

        default:
            sendMessage($chat_id, "❌ Usa */manual* para registrar un viaje manual. */cancel* para reiniciar.");
            clearState($chat_id);
            break;
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

/* ========= GRID LAYOUT CON PAGINACIÓN ========= */
function manual_kb_grid_paginado(array $items, string $callback_prefix, int $pagina = 0, string $filtro_letra = null): array {
    $kb = ["inline_keyboard" => []];
    $row = [];
    
    // ORDENAR ALFABÉTICAMENTE
    usort($items, function($a, $b) {
        $nombreA = $a['nombre'] ?? $a;
        $nombreB = $b['nombre'] ?? $b;
        return strcasecmp($nombreA, $nombreB);
    });
    
    $items_por_pagina = 15;
    $total_paginas = ceil(count($items) / $items_por_pagina);
    $items_pagina = array_slice($items, $pagina * $items_por_pagina, $items_por_pagina);
    
    foreach ($items_pagina as $item) {
        $id = $item['id'] ?? $item;
        $text = $item['nombre'] ?? $item['ruta'] ?? $item['vehiculo'] ?? $item;
        
        if (mb_strlen($text) > 15) {
            $text = mb_substr($text, 0, 12) . '...';
        }
        
        $row[] = [
            "text" => $text,
            "callback_data" => $callback_prefix . $id
        ];
        
        if (count($row) === 3) {
            $kb["inline_keyboard"][] = $row;
            $row = [];
        }
    }
    
    if (!empty($row)) {
        $kb["inline_keyboard"][] = $row;
    }
    
    // Botones de navegación
    $nav_buttons = [];
    if ($pagina > 0) {
        $nav_buttons[] = ["text" => "⬅️ Anterior", "callback_data" => "manual_page_" . $pagina . "_" . ($pagina - 1) . ($filtro_letra ? "_" . $filtro_letra : "")];
    }
    
    if ($total_paginas > 1) {
        $nav_buttons[] = ["text" => "📄 " . ($pagina + 1) . "/" . $total_paginas, "callback_data" => "manual_info"];
    }
    
    if ($pagina < $total_paginas - 1) {
        $nav_buttons[] = ["text" => "Siguiente ➡️", "callback_data" => "manual_page_" . $pagina . "_" . ($pagina + 1) . ($filtro_letra ? "_" . $filtro_letra : "")];
    }
    
    if (!empty($nav_buttons)) {
        $kb["inline_keyboard"][] = $nav_buttons;
    }
    
    // Botones finales
    $kb["inline_keyboard"][] = [
        ["text" => "➕ Nuevo conductor", "callback_data" => "manual_nuevo"],
        ["text" => "🔍 Cambiar letra", "callback_data" => "manual_back_filtro"]
    ];
    
    return $kb;
}

// ... el resto de las funciones (manual_handle_callback, manual_add_back_button, etc.) permanecen igual ...