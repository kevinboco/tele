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
        "paso" => "manual_menu",
        "manual_page" => 0
    ];
    saveState($chat_id, $estado);

    // Cargar conductores frescos desde BD
    $conn = db();
    $conductores = $conn ? obtenerConductoresAdmin($conn, $chat_id) : [];
    $conn?->close();

    if ($conductores) {
        $kb = manual_kb_lista_paginada($conductores, 'manual_sel_', 0);
        sendMessage($chat_id, "Elige un *conductor* o crea uno nuevo:", $kb);
    } else {
        $estado['paso'] = 'manual_nombre_nuevo'; saveState($chat_id, $estado);
        sendMessage($chat_id, "No tienes conductores guardados.\n✍️ Escribe el *nombre* del nuevo conductor:");
    }
}

/* ========= FORMATO LISTA PARA CONDUCTORES ========= */
function manual_kb_lista_paginada(array $items, string $callback_prefix, int $pagina = 0): array {
    $kb = ["inline_keyboard" => []];
    
    // ORDENAR ALFABÉTICAMENTE de la A a la Z
    usort($items, function($a, $b) {
        $nombreA = $a['nombre'] ?? $a;
        $nombreB = $b['nombre'] ?? $b;
        return strcasecmp($nombreA, $nombreB);
    });
    
    // 10 elementos por página (más manejable en lista)
    $items_por_pagina = 10;
    $total_paginas = ceil(count($items) / $items_por_pagina);
    
    // Items para esta página
    $items_pagina = array_slice($items, $pagina * $items_por_pagina, $items_por_pagina);
    
    // Cada conductor en su propia fila (formato lista)
    foreach ($items_pagina as $item) {
        $id = $item['id'] ?? $item;
        $text = $item['nombre'] ?? $item['ruta'] ?? $item['vehiculo'] ?? $item;
        
        // Mostrar nombre completo sin recortar
        $kb["inline_keyboard"][] = [[
            "text" => "👤 " . $text,
            "callback_data" => $callback_prefix . $id
        ]];
    }
    
    // Botones de navegación - MEJOR VISUALIZACIÓN
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
    
    // Botón nuevo conductor (siempre al final)
    $kb["inline_keyboard"][] = [[ "text"=>"➕ Nuevo conductor", "callback_data"=>"manual_nuevo" ]];
    
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
        case 'manual_menu':
            // Cargar conductores frescos desde BD
            $conductores = $conn ? obtenerConductoresAdmin($conn, $chat_id) : [];
            if ($conductores) {
                $pagina = $estado['manual_page'] ?? 0;
                $kb = manual_kb_lista_paginada($conductores, 'manual_sel_', $pagina);
                sendMessage($chat_id, "Elige un *conductor* o crea uno nuevo:", $kb);
            } else {
                sendMessage($chat_id, "No tienes conductores guardados.\n✍️ Escribe el *nombre* del nuevo conductor:");
                $estado['paso']='manual_nombre_nuevo'; saveState($chat_id,$estado);
            }
            break;
        case 'manual_nombre_nuevo':
            sendMessage($chat_id, "✍️ Escribe el *nombre* del nuevo conductor:"); break;
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
                $estado['paso']='manual_ruta_nueva_texto'; saveState($chat_id,$estado);
            }
            break;
        case 'manual_ruta_nueva_texto':
            sendMessage($chat_id, "✍️ Escribe la *ruta del viaje*:"); break;
        case 'manual_ruta':
            sendMessage($chat_id, "🛣️ Ingresa la *ruta del viaje*:"); break;
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
                $estado['paso']='manual_vehiculo_nuevo_texto'; saveState($chat_id,$estado);
            }
            break;
        case 'manual_vehiculo_nuevo_texto':
            sendMessage($chat_id, "✍️ Escribe el *tipo de vehículo*:"); break;
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
                $estado['paso']='manual_empresa_nuevo_texto'; saveState($chat_id,$estado);
            }
            break;
        case 'manual_empresa_nuevo_texto':
            sendMessage($chat_id, "✍️ Escribe el *nombre de la empresa*:"); break;
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

    // ========= PAGINACIÓN =========
    if (strpos($cb_data, 'manual_page_') === 0) {
        $pagina = (int)substr($cb_data, strlen('manual_page_'));
        $estado['manual_page'] = $pagina;
        saveState($chat_id, $estado);
        
        $conn = db();
        $conductores = $conn ? obtenerConductoresAdmin($conn, $chat_id) : [];
        $conn?->close();
        
        if ($conductores) {
            $kb = manual_kb_lista_paginada($conductores, 'manual_sel_', $pagina);
            sendMessage($chat_id, "Elige un *conductor* o crea uno nuevo:", $kb);
        }
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    // ========= INFO PAGINACIÓN =========
    if ($cb_data === 'manual_info') {
        if ($cb_id) answerCallbackQuery($cb_id, "Página " . ($estado['manual_page'] + 1) . " de " . ceil(count(obtenerConductoresAdmin(db(), $chat_id)) / 10));
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
        if (!$row) { sendMessage($chat_id, "⚠️ Conductor no encontrado. Vuelve a intentarlo con /manual."); }
        else {
            $estado['manual_nombre'] = $row['nombre'];
            $estado['paso'] = 'manual_ruta_menu'; 
            saveState($chat_id,$estado);

            // Cargar rutas frescas desde BD
            $conn = db(); $rutas = $conn ? obtenerRutasAdmin($conn, $chat_id) : []; $conn?->close();
            if ($rutas) {
                $kb = manual_kb_grid($rutas, 'manual_ruta_sel_');
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nueva ruta", "callback_data"=>"manual_ruta_nueva" ]];
                $kb = manual_add_back_button($kb, 'menu');
                sendMessage($chat_id, "👤 Conductor: *{$row['nombre']}*\n\nSelecciona una *ruta* o crea una nueva:", $kb);
            } else {
                $estado['paso']='manual_ruta_nueva_texto'; saveState($chat_id,$estado);
                sendMessage($chat_id, "👤 Conductor: *{$row['nombre']}*\n\n✍️ Escribe la *ruta del viaje*:");
            }
        }
    }

    // Crear nuevo conductor
    if ($cb_data === 'manual_nuevo') {
        $estado['paso'] = 'manual_nombre_nuevo'; saveState($chat_id,$estado);
        sendMessage($chat_id, "✍️ Escribe el *nombre* del nuevo conductor:");
    }

    // Seleccionar ruta existente
    if (strpos($cb_data, 'manual_ruta_sel_') === 0) {
        $idRuta = (int)substr($cb_data, strlen('manual_ruta_sel_'));
        $conn = db(); $r = obtenerRutaAdminPorId($conn, $idRuta, $chat_id); $conn?->close();
        if (!$r) sendMessage($chat_id, "⚠️ Ruta no encontrada. Vuelve a intentarlo.");
        else {
            $estado['manual_ruta'] = $r['ruta'];
            $estado['paso'] = 'manual_fecha'; saveState($chat_id,$estado);
            $kb = kbFechaManual();
            $kb = manual_add_back_button($kb, 'ruta_menu');
            sendMessage($chat_id, "🛣️ Ruta: *{$r['ruta']}*\n\n📅 Selecciona la *fecha*:", $kb);
        }
    }

    // Crear nueva ruta
    if ($cb_data === 'manual_ruta_nueva') {
        $estado['paso'] = 'manual_ruta_nueva_texto'; saveState($chat_id,$estado);
        sendMessage($chat_id, "✍️ Escribe la *ruta del viaje*:");
    }

    // Fecha
    if ($cb_data === 'mfecha_hoy') {
        $estado['manual_fecha'] = date("Y-m-d");
        $estado['paso'] = 'manual_vehiculo_menu'; saveState($chat_id,$estado);

        // Cargar vehículos frescos desde BD
        $conn = db(); $vehiculos = $conn ? obtenerVehiculosAdmin($conn, $chat_id) : []; $conn?->close();
        if ($vehiculos) {
            $kb = manual_kb_grid($vehiculos, 'manual_vehiculo_sel_');
            $kb["inline_keyboard"][] = [[ "text"=>"➕ Nuevo vehículo", "callback_data"=>"manual_vehiculo_nuevo" ]];
            $kb = manual_add_back_button($kb, 'fecha');
            sendMessage($chat_id, "🚐 Selecciona el *tipo de vehículo* o crea uno nuevo:", $kb);
        } else {
            $estado['paso']='manual_vehiculo_nuevo_texto'; saveState($chat_id,$estado);
            sendMessage($chat_id, "No tienes vehículos guardados.\n✍️ Escribe el *tipo de vehículo* (ej.: Toyota Hilux 4x4):");
        }
    }
    if ($cb_data === 'mfecha_otro') {
        $anio = date("Y"); $estado["anio"]=$anio;
        $estado["paso"]="manual_fecha_mes"; saveState($chat_id,$estado);
        $kb = kbMeses($anio);
        $kb = manual_add_back_button($kb, 'fecha');
        sendMessage($chat_id, "📆 Selecciona el *mes* ($anio):", $kb);
    }
    if (strpos($cb_data, 'mmes_') === 0) {
        $parts = explode('_', $cb_data);
        $estado["anio"] = $parts[1] ?? date("Y");
        $estado["mes"]  = $parts[2] ?? date("m");
        $estado["paso"] = "manual_fecha_dia_input"; saveState($chat_id,$estado);
        $anio=(int)$estado["anio"]; $mes=(int)$estado["mes"];
        $maxDias = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
        sendMessage($chat_id, "✍️ Escribe el *día* del mes (1–$maxDias):");
    }

    // Vehículo
    if (strpos($cb_data, 'manual_vehiculo_sel_') === 0) {
        $idVeh = (int)substr($cb_data, strlen('manual_vehiculo_sel_'));
        $conn = db(); $v = obtenerVehiculoAdminPorId($conn, $idVeh, $chat_id); $conn?->close();
        if (!$v) sendMessage($chat_id, "⚠️ Vehículo no encontrado. Vuelve a intentarlo.");
        else {
            $estado['manual_vehiculo'] = $v['vehiculo'];
            $estado['paso'] = 'manual_empresa_menu'; saveState($chat_id,$estado);

            // Cargar empresas frescas desde BD
            $conn = db(); $empresas = $conn ? obtenerEmpresasAdmin($conn, $chat_id) : []; $conn?->close();
            if ($empresas) {
                $kb = manual_kb_grid($empresas, 'manual_empresa_sel_');
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nueva empresa", "callback_data"=>"manual_empresa_nuevo" ]];
                $kb = manual_add_back_button($kb, 'vehiculo_menu');
                sendMessage($chat_id, "🏢 Selecciona la *empresa* o crea una nueva:", $kb);
            } else {
                $estado['paso']='manual_empresa_nuevo_texto'; saveState($chat_id,$estado);
                sendMessage($chat_id, "No tienes empresas guardadas.\n✍️ Escribe el *nombre de la empresa*:");
            }
        }
    }
    if ($cb_data === 'manual_vehiculo_nuevo') {
        $estado['paso'] = 'manual_vehiculo_nuevo_texto'; saveState($chat_id,$estado);
        sendMessage($chat_id, "✍️ Escribe el *tipo de vehículo*:");
    }

    // Empresa seleccionar / crear y preguntar por pago parcial
    if (strpos($cb_data, 'manual_empresa_sel_') === 0) {
        $idEmp = (int)substr($cb_data, strlen('manual_empresa_sel_'));
        $conn = db(); $e = obtenerEmpresaAdminPorId($conn, $idEmp, $chat_id); $conn?->close();
        if (!$e) sendMessage($chat_id, "⚠️ Empresa no encontrada. Vuelve a intentarlo.");
        else {
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
    }
    
    if ($cb_data === 'manual_empresa_nuevo') {
        $estado['paso'] = 'manual_empresa_nuevo_texto'; saveState($chat_id,$estado);
        sendMessage($chat_id, "✍️ Escribe el *nombre de la empresa*:");
    }

    // Manejo de pago parcial
    if ($cb_data === 'manual_pago_si') {
        $estado['paso'] = 'manual_pago_parcial_monto'; 
        saveState($chat_id, $estado);
        sendMessage($chat_id, "💰 Escribe el *monto del pago parcial* (ej: 1500000):");
    }
    
    if ($cb_data === 'manual_pago_no') {
        // No hay pago parcial, proceder a guardar el viaje
        $estado['manual_pago_parcial'] = null;
        manual_insert_viaje_and_close($chat_id, $estado);
    }

    if ($cb_id) answerCallbackQuery($cb_id);
}

/* ========= MANEJO DEL BOTÓN VOLVER ========= */
function manual_handle_back($chat_id, &$estado, $back_step) {
    switch ($back_step) {
        case 'menu':
            $estado['paso'] = 'manual_menu';
            // Limpiar datos si es necesario
            unset($estado['manual_nombre']);
            break;
            
        case 'ruta_menu':
            $estado['paso'] = 'manual_ruta_menu';
            // Limpiar datos de ruta
            unset($estado['manual_ruta']);
            break;
            
        case 'fecha':
            $estado['paso'] = 'manual_fecha';
            // Limpiar datos de fecha
            unset($estado['manual_fecha'], $estado['anio'], $estado['mes']);
            break;
            
        case 'vehiculo_menu':
            $estado['paso'] = 'manual_vehiculo_menu';
            // Limpiar datos de vehículo
            unset($estado['manual_vehiculo']);
            break;
            
        case 'empresa_menu':
            $estado['paso'] = 'manual_empresa_menu';
            // Limpiar datos de empresa
            unset($estado['manual_empresa']);
            break;
            
        default:
            // Si no reconoce el paso, volver al menú principal
            $estado['paso'] = 'manual_menu';
            break;
    }
    
    saveState($chat_id, $estado);
    manual_resend_current_step($chat_id, $estado);
}

function manual_handle_text($chat_id, &$estado, $text, $photo) {
    if (($estado["flujo"] ?? "") !== "manual") return;

    switch ($estado["paso"]) {
        case "manual_nombre": // compat
        case "manual_nombre_nuevo":
            $nombre = trim($text);
            if ($nombre==="") { sendMessage($chat_id, "⚠️ El nombre no puede estar vacío. Escribe el *nombre* del nuevo conductor:"); break; }
            
            // Guardar en BD
            $conn = db(); 
            if ($conn) { 
                crearConductorAdmin($conn, $chat_id, $nombre); 
                $conn->close(); 
            }
            
            $estado["manual_nombre"] = $nombre;
            $estado["paso"] = "manual_ruta_menu"; 
            saveState($chat_id,$estado);

            // Cargar rutas frescas desde BD
            $conn = db(); $rutas = $conn ? obtenerRutasAdmin($conn, $chat_id) : []; $conn?->close();
            if ($rutas) {
                $kb = manual_kb_grid($rutas, 'manual_ruta_sel_');
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nueva ruta", "callback_data"=>"manual_ruta_nueva" ]];
                $kb = manual_add_back_button($kb, 'menu');
                sendMessage($chat_id, "👤 Conductor guardado: *{$nombre}*\n\nSelecciona una *ruta* o crea una nueva:", $kb);
            } else {
                $estado['paso']='manual_ruta_nueva_texto'; saveState($chat_id,$estado);
                sendMessage($chat_id, "👤 Conductor guardado: *{$nombre}*\n\n✍️ Escribe la *ruta del viaje*:");
            }
            break;

        case "manual_ruta": // compat
        case "manual_ruta_nueva_texto":
            $rutaTxt = trim($text);
            if ($rutaTxt==="") { sendMessage($chat_id, "⚠️ La ruta no puede estar vacía. Escribe la *ruta del viaje*:"); break; }
            
            // Guardar en BD
            $conn = db(); 
            if ($conn) { 
                crearRutaAdmin($conn, $chat_id, $rutaTxt); 
                $conn->close(); 
            }
            
            $estado["manual_ruta"] = $rutaTxt;
            $estado["paso"] = "manual_fecha"; saveState($chat_id,$estado);
            $kb = kbFechaManual();
            $kb = manual_add_back_button($kb, 'ruta_menu');
            sendMessage($chat_id, "🛣️ Ruta guardada: *{$rutaTxt}*\n\n📅 Selecciona la *fecha*:", $kb);
            break;

        case "manual_fecha_dia_input":
            $anio=(int)($estado["anio"] ?? date("Y")); $mes=(int)($estado["mes"] ?? date("m"));
            if (!preg_match('/^\d{1,2}$/', $text)) {
                $max=cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
                sendMessage($chat_id, "⚠️ Debe ser un número entre 1 y $max. Escribe el *día* del mes:"); break;
            }
            $dia=(int)$text; $max=cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
            if ($dia<1 || $dia>$max) { sendMessage($chat_id, "⚠️ El día debe estar entre 1 y $max. Inténtalo de nuevo:"); break; }
            $estado["manual_fecha"] = sprintf("%04d-%02d-%02d",$anio,$mes,$dia);

            $estado['paso'] = 'manual_vehiculo_menu'; saveState($chat_id,$estado);
            
            // Cargar vehículos frescos desde BD
            $conn = db(); $vehiculos = $conn ? obtenerVehiculosAdmin($conn, $chat_id) : []; $conn?->close();
            if ($vehiculos) {
                $kb = manual_kb_grid($vehiculos, 'manual_vehiculo_sel_');
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nuevo vehículo", "callback_data"=>"manual_vehiculo_nuevo" ]];
                $kb = manual_add_back_button($kb, 'fecha');
                sendMessage($chat_id, "🚐 Selecciona el *tipo de vehículo* o crea uno nuevo:", $kb);
            } else {
                $estado['paso']='manual_vehiculo_nuevo_texto'; saveState($chat_id,$estado);
                sendMessage($chat_id, "No tienes vehículos guardados.\n✍️ Escribe el *tipo de vehículo* (ej.: Toyota Hilux 4x4):");
            }
            break;

        case "manual_vehiculo_nuevo_texto":
            $vehTxt = trim($text);
            if ($vehTxt==="") { sendMessage($chat_id, "⚠️ El *tipo de vehículo* no puede estar vacío. Escríbelo nuevamente:"); break; }
            
            // Guardar en BD
            $conn = db(); 
            if ($conn) { 
                crearVehiculoAdmin($conn, $chat_id, $vehTxt); 
                $conn->close(); 
            }
            
            $estado["manual_vehiculo"] = $vehTxt;
            $estado['paso'] = 'manual_empresa_menu'; saveState($chat_id,$estado);

            // Cargar empresas frescas desde BD
            $conn = db(); $emp = $conn ? obtenerEmpresasAdmin($conn, $chat_id) : []; $conn?->close();
            if ($emp) {
                $kb = manual_kb_grid($emp, 'manual_empresa_sel_');
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nueva empresa", "callback_data"=>"manual_empresa_nuevo" ]];
                $kb = manual_add_back_button($kb, 'vehiculo_menu');
                sendMessage($chat_id, "🏢 Selecciona la *empresa* o crea una nueva:", $kb);
            } else {
                $estado['paso']='manual_empresa_nuevo_texto'; saveState($chat_id,$estado);
                sendMessage($chat_id, "No tienes empresas guardadas.\n✍️ Escribe el *nombre de la empresa*:");
            }
            break;

        case "manual_empresa_nuevo_texto":
            $empTxt = trim($text);
            if ($empTxt==="") { sendMessage($chat_id, "⚠️ El *nombre de la empresa* no puede estar vacío. Escríbelo nuevamente:"); break; }
            
            // Guardar en BD
            $conn = db(); 
            if ($conn) { 
                crearEmpresaAdmin($conn, $chat_id, $empTxt); 
                $conn->close(); 
            }
            
            $estado["manual_empresa"] = $empTxt;
            
            // Preguntar por pago parcial
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
            // Validar que sea un número
            $monto = trim($text);
            if (!is_numeric($monto) || $monto <= 0) {
                sendMessage($chat_id, "⚠️ El monto debe ser un número positivo (ej: 1500000). Escribe el *monto del pago parcial*:");
                break;
            }
            
            // Convertir a entero
            $estado["manual_pago_parcial"] = (int)$monto;
            
            // Guardar el viaje
            manual_insert_viaje_and_close($chat_id, $estado);
            break;

        default:
            sendMessage($chat_id, "❌ Usa */manual* para registrar un viaje manual. */cancel* para reiniciar.");
            clearState($chat_id);
            break;
    }
}

function manual_insert_viaje_and_close($chat_id, &$estado) {
    $conn = db();
    if (!$conn) { sendMessage($chat_id, "❌ Error de conexión a la base de datos."); clearState($chat_id); return; }
    
    // Preparar la consulta con el nuevo campo pago_parcial
    $stmt = $conn->prepare("INSERT INTO viajes (nombre, ruta, fecha, cedula, tipo_vehiculo, empresa, imagen, pago_parcial) VALUES (?, ?, ?, NULL, ?, ?, NULL, ?)");
    $pago_parcial = $estado["manual_pago_parcial"] ?? null;
    $stmt->bind_param("sssssi", 
        $estado["manual_nombre"], 
        $estado["manual_ruta"], 
        $estado["manual_fecha"], 
        $estado["manual_vehiculo"], 
        $estado["manual_empresa"],
        $pago_parcial
    );
    
    if ($stmt->execute()) {
        $mensaje = "✅ Viaje (manual) registrado:\n👤 " . $estado["manual_nombre"] .
                   "\n🛣️ " . $estado["manual_ruta"] .
                   "\n📅 " . $estado["manual_fecha"] .
                   "\n🚐 " . $estado["manual_vehiculo"] .
                   "\n🏢 " . $estado["manual_empresa"];
        
        // Agregar información del pago parcial si existe
        if (isset($estado["manual_pago_parcial"])) {
            
            $monto_formateado = number_format($estado["manual_pago_parcial"], 0, ',', '.');
            $mensaje .= "\n💰 Pago parcial: $" . $monto_formateado;
        }
        
        $mensaje .= "\n\nAtajos rápidos: /agg /manual";
        
        sendMessage($chat_id, $mensaje);
    } else {
        sendMessage($chat_id, "❌ Error al guardar el viaje: " . $conn->error);
    }
    $stmt->close(); 
    $conn->close();
    clearState($chat_id);
}