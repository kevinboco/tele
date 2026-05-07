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
        $kb = manual_kb_grid_paginado($conductores, 'manual_sel_', 0);
        sendMessage($chat_id, "Elige un *conductor* o crea uno nuevo:", $kb);
    } else {
        $estado['paso'] = 'manual_nombre_nuevo'; saveState($chat_id, $estado);
        sendMessage($chat_id, "No tienes conductores guardados.\n✍️ Escribe el *nombre* del nuevo conductor:");
    }
}

/* ========= GRID LAYOUT CON PAGINACIÓN MEJORADA ========= */
function manual_kb_grid_paginado(array $items, string $callback_prefix, int $pagina = 0): array {
    $kb = ["inline_keyboard" => []];
    $row = [];
    
    // ORDENAR ALFABÉTICAMENTE de la A a la Z
    usort($items, function($a, $b) {
        $nombreA = $a['nombre'] ?? $a;
        $nombreB = $b['nombre'] ?? $b;
        return strcasecmp($nombreA, $nombreB);
    });
    
    // 15 elementos por página
    $items_por_pagina = 15;
    $total_paginas = ceil(count($items) / $items_por_pagina);
    
    // Items para esta página
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
    $conn = db();
    switch ($estado['paso']) {
        case 'manual_menu':
            $conductores = $conn ? obtenerConductoresAdmin($conn, $chat_id) : [];
            if ($conductores) {
                $pagina = $estado['manual_page'] ?? 0;
                $kb = manual_kb_grid_paginado($conductores, 'manual_sel_', $pagina);
                sendMessage($chat_id, "Elige un *conductor* o crea uno nuevo:", $kb);
            } else {
                sendMessage($chat_id, "No tienes conductores guardados.\n✍️ Escribe el *nombre* del nuevo conductor:");
                $estado['paso']='manual_nombre_nuevo'; saveState($chat_id,$estado);
            }
            break;
        case 'manual_nombre_nuevo':
            sendMessage($chat_id, "✍️ Escribe el *nombre* del nuevo conductor:"); break;
        case 'manual_ruta_menu':
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
        case 'manual_imagen':
            // ----- EVIDENCIA (OBLIGATORIA) -----
            $kb = [
                "inline_keyboard" => [
                    [
                        ["text" => "⬅️ Volver", "callback_data" => "manual_back_pago_menu"]
                    ]
                ]
            ];
            // NOTA: En evidencia NO hay botón omitir porque es OBLIGATORIA
            sendMessage($chat_id, "📸 *Envía la foto/factura* del viaje (OBLIGATORIA).\n\nDebes enviar una imagen para continuar.", $kb);
            break;
        case 'manual_epicrisis':
            // ----- EPICRISIS (OPCIONAL) -----
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
            $kb = manual_kb_grid_paginado($conductores, 'manual_sel_', $pagina);
            sendMessage($chat_id, "Elige un *conductor* o crea uno nuevo:", $kb);
        }
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    // ========= INFO PAGINACIÓN =========
    if ($cb_data === 'manual_info') {
        if ($cb_id) answerCallbackQuery($cb_id, "Página " . ($estado['manual_page'] + 1) . " de " . ceil(count(obtenerConductoresAdmin(db(), $chat_id)) / 15));
        return;
    }

    // ========= BOTÓN VOLVER =========
    if (strpos($cb_data, 'manual_back_') === 0) {
        $back_step = substr($cb_data, strlen('manual_back_'));
        manual_handle_back($chat_id, $estado, $back_step);
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    // ========= OMITIR EPICRISIS =========
    if ($cb_data === 'manual_skip_epicrisis') {
        $estado['manual_epicrisis'] = null;
        manual_insert_viaje_and_close($chat_id, $estado);
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
        $estado['manual_pago_parcial'] = null;
        $estado['paso'] = 'manual_imagen';  // Va a evidencia (OBLIGATORIA)
        saveState($chat_id, $estado);
        
        $kb = [
            "inline_keyboard" => [
                [
                    ["text" => "⬅️ Volver", "callback_data" => "manual_back_pago_menu"]
                ]
            ]
        ];
        // Sin botón omitir - la evidencia es OBLIGATORIA
        sendMessage($chat_id, "📸 *Envía la foto/factura* del viaje (OBLIGATORIA).\n\nDebes enviar una imagen para continuar.", $kb);
    }

    if ($cb_id) answerCallbackQuery($cb_id);
}

/* ========= MANEJO DEL BOTÓN VOLVER ========= */
function manual_handle_back($chat_id, &$estado, $back_step) {
    switch ($back_step) {
        case 'menu':
            $estado['paso'] = 'manual_menu';
            unset($estado['manual_nombre']);
            break;
            
        case 'ruta_menu':
            $estado['paso'] = 'manual_ruta_menu';
            unset($estado['manual_ruta']);
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
            // Volver desde epicrisis a evidencia
            $estado['paso'] = 'manual_imagen';
            unset($estado['manual_epicrisis']);
            break;
            
        default:
            $estado['paso'] = 'manual_menu';
            break;
    }
    
    saveState($chat_id, $estado);
    manual_resend_current_step($chat_id, $estado);
}

function manual_handle_text($chat_id, &$estado, $text, $photo) {
    if (($estado["flujo"] ?? "") !== "manual") return;

    switch ($estado["paso"]) {
        case "manual_nombre":
        case "manual_nombre_nuevo":
            $nombre = trim($text);
            if ($nombre==="") { sendMessage($chat_id, "⚠️ El nombre no puede estar vacío. Escribe el *nombre* del nuevo conductor:"); break; }
            
            $conn = db(); 
            if ($conn) { 
                crearConductorAdmin($conn, $chat_id, $nombre); 
                $conn->close(); 
            }
            
            $estado["manual_nombre"] = $nombre;
            $estado["paso"] = "manual_ruta_menu"; 
            saveState($chat_id,$estado);

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

        case "manual_ruta":
        case "manual_ruta_nueva_texto":
            $rutaTxt = trim($text);
            if ($rutaTxt==="") { sendMessage($chat_id, "⚠️ La ruta no puede estar vacía. Escribe la *ruta del viaje*:"); break; }
            
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
            
            $conn = db(); 
            if ($conn) { 
                crearVehiculoAdmin($conn, $chat_id, $vehTxt); 
                $conn->close(); 
            }
            
            $estado["manual_vehiculo"] = $vehTxt;
            $estado['paso'] = 'manual_empresa_menu'; saveState($chat_id,$estado);

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
            if (!is_numeric($monto) || $monto <= 0) {
                sendMessage($chat_id, "⚠️ El monto debe ser un número positivo (ej: 1500000). Escribe el *monto del pago parcial*:");
                break;
            }
            
            $estado["manual_pago_parcial"] = (int)$monto;
            
            // Ir a evidencia (OBLIGATORIA)
            $estado['paso'] = 'manual_imagen';
            saveState($chat_id, $estado);
            
            $kb = [
                "inline_keyboard" => [
                    [
                        ["text" => "⬅️ Volver", "callback_data" => "manual_back_pago_menu"]
                    ]
                ]
            ];
            // Sin botón omitir - la evidencia es OBLIGATORIA
            sendMessage($chat_id, "📸 *Envía la foto/factura* del viaje (OBLIGATORIA).\n\nDebes enviar una imagen para continuar.", $kb);
            break;

        case "manual_imagen":
            // Procesar imagen de EVIDENCIA (OBLIGATORIA)
            manual_process_evidencia($chat_id, $estado, $photo);
            break;

        case "manual_epicrisis":
            // Procesar imagen de EPICRISIS (OPCIONAL)
            manual_process_epicrisis($chat_id, $estado, $photo);
            break;

        default:
            sendMessage($chat_id, "❌ Usa */manual* para registrar un viaje manual. */cancel* para reiniciar.");
            clearState($chat_id);
            break;
    }
}

/* ========= PROCESAR IMAGEN DE EVIDENCIA (OBLIGATORIA) ========= */
function manual_process_evidencia($chat_id, &$estado, $photo) {
    $file_id = null;
    
    if (!empty($photo) && is_array($photo)) {
        $tmp = end($photo);
        if (is_array($tmp) && !empty($tmp['file_id'])) {
            $file_id = $tmp['file_id'];
        }
        reset($photo);
    }
    
    $doc = $GLOBALS['update']['message']['document'] ?? null;
    if (!$file_id && $doc && isset($doc['mime_type']) && strpos($doc['mime_type'], 'image/') === 0) {
        $file_id = $doc['file_id'];
    }
    
    if (!$file_id) { 
        $kb = [
            "inline_keyboard" => [
                [
                    ["text" => "⬅️ Volver", "callback_data" => "manual_back_pago_menu"]
                ]
            ]
        ];
        // Sin omitir - ES OBLIGATORIA
        sendMessage($chat_id, "⚠️ La foto de evidencia es *OBLIGATORIA*. Debes enviar una imagen para continuar.", $kb);
        return;
    }

    global $TOKEN;
    $info = @json_decode(@file_get_contents("https://api.telegram.org/bot{$TOKEN}/getFile?file_id=".urlencode($file_id)), true);
    
    if (!$info || empty($info['ok']) || empty($info['result']['file_path'])) {
        sendMessage($chat_id, "❌ No pude obtener el archivo desde Telegram.");
        return;
    }
    
    $file_path = $info['result']['file_path'];
    $fileUrl   = "https://api.telegram.org/file/bot{$TOKEN}/{$file_path}";

    $uploads = __DIR__ . "/uploads/";
    if (!is_dir($uploads)) @mkdir($uploads, 0775, true);
    
    $nombreArchivo = time() . "_evidencia_" . basename($file_path);
    $destino       = $uploads . $nombreArchivo;

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
        sendMessage($chat_id, "❌ No pude guardar la imagen. Reenvíala, por favor.");
        return;
    }

    // Guardar evidencia yavanzar a epicrisis (OPCIONAL)
    $estado['manual_imagen'] = $nombreArchivo;
    $estado['paso'] = 'manual_epicrisis';
    saveState($chat_id, $estado);
    
    // Preguntar por epicrisis
    $kb = [
        "inline_keyboard" => [
            [
                ["text" => "⬅️ Volver", "callback_data" => "manual_back_pago_menu"]
            ]
        ]
    ];
    $kb = manual_add_skip_button($kb, "manual_skip_epicrisis");
    sendMessage($chat_id, "✅ Evidencia guardada.\n\n📋 *Epicrisis* (OPCIONAL)\n\nEnvía la foto de la epicrisis o usa *Omitir* para continuar sin ella.", $kb);
}

/* ========= PROCESAR IMAGEN DE EPICRISIS (OPCIONAL) ========= */
function manual_process_epicrisis($chat_id, &$estado, $photo) {
    $file_id = null;
    
    if (!empty($photo) && is_array($photo)) {
        $tmp = end($photo);
        if (is_array($tmp) && !empty($tmp['file_id'])) {
            $file_id = $tmp['file_id'];
        }
        reset($photo);
    }
    
    $doc = $GLOBALS['update']['message']['document'] ?? null;
    if (!$file_id && $doc && isset($doc['mime_type']) && strpos($doc['mime_type'], 'image/') === 0) {
        $file_id = $doc['file_id'];
    }
    
    if (!$file_id) { 
        $kb = [
            "inline_keyboard" => [
                [
                    ["text" => "⬅️ Volver", "callback_data" => "manual_back_imagen"]
                ]
            ]
        ];
        $kb = manual_add_skip_button($kb, "manual_skip_epicrisis");
        sendMessage($chat_id, "⚠️ No se detectó una imagen válida. Envía la *epicrisis* o usa *Omitir* para continuar sin ella.", $kb);
        return;
    }

    global $TOKEN;
    $info = @json_decode(@file_get_contents("https://api.telegram.org/bot{$TOKEN}/getFile?file_id=".urlencode($file_id)), true);
    
    if (!$info || empty($info['ok']) || empty($info['result']['file_path'])) {
        sendMessage($chat_id, "❌ No pude obtener el archivo desde Telegram.");
        return;
    }
    
    $file_path = $info['result']['file_path'];
    $fileUrl   = "https://api.telegram.org/file/bot{$TOKEN}/{$file_path}";

    $uploads = __DIR__ . "/uploads/";
    if (!is_dir($uploads)) @mkdir($uploads, 0775, true);
    
    $nombreArchivo = time() . "_epicrisis_" . basename($file_path);
    $destino       = $uploads . $nombreArchivo;

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
        sendMessage($chat_id, "❌ No pude guardar la epicrisis. Reenvíala o usa *Omitir*.");
        return;
    }

    // Guardar epicrisis y finalizar
    $estado['manual_epicrisis'] = $nombreArchivo;
    manual_insert_viaje_and_close($chat_id, $estado);
}

/* ========= INSERTAR VIAJE Y CERRAR FLUJO ========= */
function manual_insert_viaje_and_close($chat_id, &$estado) {
    $conn = db();
    if (!$conn) { 
        sendMessage($chat_id, "❌ Error de conexión a la base de datos."); 
        clearState($chat_id); 
        return; 
    }
    
    // Preparar consulta con ambos campos de imagen
    $stmt = $conn->prepare("INSERT INTO viajes (nombre, ruta, fecha, cedula, tipo_vehiculo, empresa, imagen, epicrisis, pago_parcial) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?)");
    $pago_parcial = $estado["manual_pago_parcial"] ?? null;
    $imagen = $estado["manual_imagen"] ?? null;
    $epicrisis = $estado["manual_epicrisis"] ?? null;
    
    $stmt->bind_param("sssssssi", 
        $estado["manual_nombre"], 
        $estado["manual_ruta"], 
        $estado["manual_fecha"], 
        $estado["manual_vehiculo"], 
        $estado["manual_empresa"],
        $imagen,
        $epicrisis,
        $pago_parcial
    );
    
    if ($stmt->execute()) {
        $mensaje = "✅ *Viaje registrado exitosamente*\n\n" .
                   "👤 *Conductor:* " . $estado["manual_nombre"] . "\n" .
                   "🛣️ *Ruta:* " . $estado["manual_ruta"] . "\n" .
                   "📅 *Fecha:* " . $estado["manual_fecha"] . "\n" .
                   "🚐 *Vehículo:* " . $estado["manual_vehiculo"] . "\n" .
                   "🏢 *Empresa:* " . $estado["manual_empresa"];
        
        // Pago parcial
        if (isset($estado["manual_pago_parcial"])) {
            $monto_formateado = number_format($estado["manual_pago_parcial"], 0, ',', '.');
            $mensaje .= "\n💰 *Pago parcial:* $" . $monto_formateado;
        }
        
        // Evidencia (siempre debe estar)
        $mensaje .= "\n📸 *Evidencia:* ✅ Adjuntada";
        
        // Epicrisis
        if (isset($estado["manual_epicrisis"])) {
            $mensaje .= "\n📋 *Epicrisis:* ✅ Adjuntada";
        } else {
            $mensaje .= "\n📋 *Epicrisis:* ❌ No adjuntada";
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