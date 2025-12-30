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
    ];
    saveState($chat_id, $estado);

    // Cargar conductores frescos desde BD
    $conn = db();
    $conductores = $conn ? obtenerConductoresAdmin($conn, $chat_id) : [];
    $conn?->close();

    if ($conductores) {
        // Crear lista de conductores como texto
        $lista = enviarListaConductoresCompleta($chat_id, $conductores);
        
        // También dar opción de crear nuevo
        $kb = [
            "inline_keyboard" => [
                [["text" => "➕ Crear nuevo conductor", "callback_data" => "manual_nuevo"]],
                [["text" => "🔄 Actualizar lista", "callback_data" => "manual_refresh"]]
            ]
        ];
        sendMessage($chat_id, "¿Qué deseas hacer?", $kb);
        
    } else {
        $estado['paso'] = 'manual_nombre_nuevo'; 
        saveState($chat_id, $estado);
        sendMessage($chat_id, "No tienes conductores guardados.\n✍️ Escribe el *nombre* del nuevo conductor:");
    }
}

/* ========= FUNCIÓN PARA ENVIAR LISTA COMPLETA DE CONDUCTORES ========= */
function enviarListaConductoresCompleta($chat_id, $conductores) {
    // Ordenar alfabéticamente
    usort($conductores, function($a, $b) {
        $nombreA = $a['nombre'] ?? '';
        $nombreB = $b['nombre'] ?? '';
        return strcasecmp($nombreA, $nombreB);
    });
    
    // Dividir en grupos para evitar límites de Telegram
    $grupos = array_chunk($conductores, 50); // 50 conductores por mensaje
    $total_grupos = count($grupos);
    
    foreach ($grupos as $indice => $grupo) {
        $lista = "📋 *LISTA DE CONDUCTORES*";
        if ($total_grupos > 1) {
            $lista .= " (Parte " . ($indice + 1) . " de $total_grupos)";
        }
        $lista .= ":\n\n";
        
        $numero_base = $indice * 50 + 1;
        
        foreach ($grupo as $i => $conductor) {
            $numero = $numero_base + $i;
            $nombre = htmlspecialchars($conductor['nombre'] ?? '');
            $lista .= "$numero. `{$nombre}`\n";
        }
        
        // Solo en el último mensaje agregar instrucciones
        if ($indice === $total_grupos - 1) {
            $lista .= "\n✍️ *Copia y pega el NOMBRE del conductor que deseas usar*\n";
            $lista .= "📝 *Ejemplo:* Si quieres usar 'Juan Pérez', escribe exactamente 'Juan Pérez'";
        }
        
        sendMessage($chat_id, $lista);
    }
    
    return count($conductores);
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
                // Mostrar lista completa de conductores
                enviarListaConductoresCompleta($chat_id, $conductores);
                
                $kb = [
                    "inline_keyboard" => [
                        [["text" => "➕ Crear nuevo conductor", "callback_data" => "manual_nuevo"]],
                        [["text" => "🔄 Actualizar lista", "callback_data" => "manual_refresh"]]
                    ]
                ];
                sendMessage($chat_id, "¿Qué deseas hacer?", $kb);
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

    // ========= REFRESCAR LISTA =========
    if ($cb_data === 'manual_refresh') {
        $conn = db();
        $conductores = $conn ? obtenerConductoresAdmin($conn, $chat_id) : [];
        $conn?->close();
        
        if ($conductores) {
            enviarListaConductoresCompleta($chat_id, $conductores);
            
            $kb = [
                "inline_keyboard" => [
                    [["text" => "➕ Crear nuevo conductor", "callback_data" => "manual_nuevo"]],
                    [["text" => "🔄 Actualizar lista", "callback_data" => "manual_refresh"]]
                ]
            ];
            sendMessage($chat_id, "¿Qué deseas hacer?", $kb);
        } else {
            sendMessage($chat_id, "No tienes conductores guardados.");
            $estado['paso'] = 'manual_nombre_nuevo'; 
            saveState($chat_id, $estado);
            sendMessage($chat_id, "✍️ Escribe el *nombre* del nuevo conductor:");
        }
        
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

    // Seleccionar conductor existente (ahora se hace por texto)
    // Este callback ya no se usa para conductores, pero lo mantengo por compatibilidad
    if (strpos($cb_data, 'manual_sel_') === 0) {
        sendMessage($chat_id, "⚠️ *Nueva forma de seleccionar:*\n\nAhora escribe directamente el NOMBRE del conductor que ves en la lista.\n\nUsa /manual para ver la lista nuevamente.");
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }

    // Crear nuevo conductor
    if ($cb_data === 'manual_nuevo') {
        $estado['paso'] = 'manual_nombre_nuevo'; saveState($chat_id,$estado);
        sendMessage($chat_id, "✍️ Escribe el *nombre* del nuevo conductor:");
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
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
        case "manual_menu":
            // El usuario envía el nombre del conductor (selección por texto)
            $nombre_buscado = trim($text);
            
            if (empty($nombre_buscado)) { 
                sendMessage($chat_id, "⚠️ El nombre no puede estar vacío. Escribe el *nombre* del conductor:");
                break; 
            }
            
            // Buscar el conductor en la base de datos
            $conn = db();
            $conductores = $conn ? obtenerConductoresAdmin($conn, $chat_id) : [];
            $conn?->close();
            
            // Buscar coincidencias EXACTAS primero (ignorando mayúsculas/minúsculas)
            $conductor_exacto = null;
            foreach ($conductores as $conductor) {
                if (strcasecmp($conductor['nombre'], $nombre_buscado) === 0) {
                    $conductor_exacto = $conductor;
                    break;
                }
            }
            
            if ($conductor_exacto) {
                // Conductor encontrado exactamente
                $estado["manual_nombre"] = $conductor_exacto['nombre'];
                $estado["paso"] = "manual_ruta_menu"; 
                saveState($chat_id, $estado);
                
                // Cargar rutas frescas desde BD
                $conn = db(); 
                $rutas = $conn ? obtenerRutasAdmin($conn, $chat_id) : []; 
                $conn?->close();
                
                if ($rutas) {
                    $kb = manual_kb_grid($rutas, 'manual_ruta_sel_');
                    $kb["inline_keyboard"][] = [[ "text"=>"➕ Nueva ruta", "callback_data"=>"manual_ruta_nueva" ]];
                    $kb = manual_add_back_button($kb, 'menu');
                    sendMessage($chat_id, "✅ Conductor seleccionado: *{$conductor_exacto['nombre']}*\n\nSelecciona una *ruta* o crea una nueva:", $kb);
                } else {
                    $estado['paso']='manual_ruta_nueva_texto'; 
                    saveState($chat_id,$estado);
                    sendMessage($chat_id, "✅ Conductor seleccionado: *{$conductor_exacto['nombre']}*\n\n✍️ Escribe la *ruta del viaje*:");
                }
                
            } else {
                // Buscar coincidencias parciales
                $sugerencias = [];
                foreach ($conductores as $conductor) {
                    if (stripos($conductor['nombre'], $nombre_buscado) !== false) {
                        $sugerencias[] = $conductor['nombre'];
                    }
                }
                
                if (!empty($sugerencias)) {
                    // Mostrar sugerencias
                    $mensaje = "❌ *No se encontró exactamente:* `{$nombre_buscado}`\n\n";
                    $mensaje .= "🔍 *Coincidencias encontradas:*\n";
                    
                    foreach ($sugerencias as $sugerencia) {
                        $mensaje .= "• `{$sugerencia}`\n";
                    }
                    
                    $mensaje .= "\n✍️ *Copia y pega el nombre EXACTO del conductor:*";
                    sendMessage($chat_id, $mensaje);
                } else {
                    // No se encontró nada similar
                    sendMessage($chat_id, "❌ No se encontró ningún conductor con el nombre: *{$nombre_buscado}*\n\n📝 *Usa /manual para ver la lista completa de conductores.*");
                }
            }
            break;

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
                sendMessage($chat_id, "✅ Conductor guardado: *{$nombre}*\n\nSelecciona una *ruta* o crea una nueva:", $kb);
            } else {
                $estado['paso']='manual_ruta_nueva_texto'; saveState($chat_id,$estado);
                sendMessage($chat_id, "✅ Conductor guardado: *{$nombre}*\n\n✍️ Escribe la *ruta del viaje*:");
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
            sendMessage($chat_id, "✅ Ruta guardada: *{$rutaTxt}*\n\n📅 Selecciona la *fecha*:", $kb);
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