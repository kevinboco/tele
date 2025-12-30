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
    
    // Mostrar menú de conductores como texto
    manual_mostrar_menu_conductores($chat_id, $estado);
}

/* ========= MOSTRAR MENÚ DE CONDUCTORES COMO TEXTO ========= */
function manual_mostrar_menu_conductores($chat_id, $estado) {
    $conn = db();
    $conductores = $conn ? obtenerConductoresAdmin($conn, $chat_id) : [];
    $conn?->close();
    
    if ($conductores) {
        // Ordenar alfabéticamente
        usort($conductores, function($a, $b) {
            $nombreA = $a['nombre'] ?? $a;
            $nombreB = $b['nombre'] ?? $b;
            return strcasecmp($nombreA, $nombreB);
        });
        
        $mensaje = "📋 *LISTA DE CONDUCTORES*\n\n";
        $opciones = [];
        
        foreach ($conductores as $index => $conductor) {
            $numero = $index + 1;
            $nombre = $conductor['nombre'];
            $mensaje .= "{$numero}. {$nombre}\n";
            $opciones[$numero] = [
                'id' => $conductor['id'],
                'nombre' => $nombre
            ];
        }
        
        // Guardar opciones en el estado para validación
        $estado['opciones_conductores'] = $opciones;
        $estado['total_conductores'] = count($conductores);
        saveState($chat_id, $estado);
        
        $mensaje .= "\n✏️ Para seleccionar, *escribe el número* correspondiente\n";
        $mensaje .= "📝 Para crear nuevo conductor, *escribe: NUEVO*";
        
        // Teclado simplificado
        $kb = [
            "inline_keyboard" => [
                [
                    ["text" => "🔄 Actualizar lista", "callback_data" => "manual_refresh"],
                    ["text" => "❌ Cancelar", "callback_data" => "manual_cancel"]
                ]
            ]
        ];
        
        sendMessage($chat_id, $mensaje, $kb);
    } else {
        // No hay conductores, ir directamente a crear uno nuevo
        $estado['paso'] = 'manual_nombre_nuevo'; 
        saveState($chat_id, $estado);
        sendMessage($chat_id, "No tienes conductores guardados.\n✍️ Escribe el *nombre* del nuevo conductor:");
    }
}

/* ========= MOSTRAR MENÚ DE RUTAS COMO TEXTO ========= */
function manual_mostrar_menu_rutas($chat_id, $estado) {
    $conn = db();
    $rutas = $conn ? obtenerRutasAdmin($conn, $chat_id) : [];
    $conn?->close();
    
    if ($rutas) {
        // Ordenar alfabéticamente
        usort($rutas, function($a, $b) {
            $rutaA = $a['ruta'] ?? $a;
            $rutaB = $b['ruta'] ?? $b;
            return strcasecmp($rutaA, $rutaB);
        });
        
        $mensaje = "🛣️ *LISTA DE RUTAS*\n\n";
        $opciones = [];
        
        foreach ($rutas as $index => $ruta) {
            $numero = $index + 1;
            $nombreRuta = $ruta['ruta'];
            $mensaje .= "{$numero}. {$nombreRuta}\n";
            $opciones[$numero] = [
                'id' => $ruta['id'],
                'ruta' => $nombreRuta
            ];
        }
        
        // Guardar opciones en el estado
        $estado['opciones_rutas'] = $opciones;
        $estado['total_rutas'] = count($rutas);
        saveState($chat_id, $estado);
        
        $mensaje .= "\n✏️ Para seleccionar, *escribe el número* correspondiente\n";
        $mensaje .= "📝 Para crear nueva ruta, *escribe: NUEVO*";
        
        // Teclado con opciones
        $kb = [
            "inline_keyboard" => [
                [
                    ["text" => "🔄 Actualizar", "callback_data" => "manual_refresh_rutas"],
                    ["text" => "⬅️ Volver", "callback_data" => "manual_back_menu"]
                ]
            ]
        ];
        
        sendMessage($chat_id, $mensaje, $kb);
    } else {
        // No hay rutas, ir directamente a crear una nueva
        $estado['paso'] = 'manual_ruta_nueva_texto'; 
        saveState($chat_id, $estado);
        sendMessage($chat_id, "No tienes rutas guardadas.\n✍️ Escribe la *ruta del viaje*:");
    }
}

/* ========= MOSTRAR MENÚ DE VEHÍCULOS COMO TEXTO ========= */
function manual_mostrar_menu_vehiculos($chat_id, $estado) {
    $conn = db();
    $vehiculos = $conn ? obtenerVehiculosAdmin($conn, $chat_id) : [];
    $conn?->close();
    
    if ($vehiculos) {
        // Ordenar alfabéticamente
        usort($vehiculos, function($a, $b) {
            $vehA = $a['vehiculo'] ?? $a;
            $vehB = $b['vehiculo'] ?? $b;
            return strcasecmp($vehA, $vehB);
        });
        
        $mensaje = "🚐 *LISTA DE VEHÍCULOS*\n\n";
        $opciones = [];
        
        foreach ($vehiculos as $index => $vehiculo) {
            $numero = $index + 1;
            $nombreVehiculo = $vehiculo['vehiculo'];
            $mensaje .= "{$numero}. {$nombreVehiculo}\n";
            $opciones[$numero] = [
                'id' => $vehiculo['id'],
                'vehiculo' => $nombreVehiculo
            ];
        }
        
        // Guardar opciones en el estado
        $estado['opciones_vehiculos'] = $opciones;
        $estado['total_vehiculos'] = count($vehiculos);
        saveState($chat_id, $estado);
        
        $mensaje .= "\n✏️ Para seleccionar, *escribe el número* correspondiente\n";
        $mensaje .= "📝 Para crear nuevo vehículo, *escribe: NUEVO*";
        
        // Teclado con opciones
        $kb = [
            "inline_keyboard" => [
                [
                    ["text" => "🔄 Actualizar", "callback_data" => "manual_refresh_vehiculos"],
                    ["text" => "⬅️ Volver", "callback_data" => "manual_back_fecha"]
                ]
            ]
        ];
        
        sendMessage($chat_id, $mensaje, $kb);
    } else {
        // No hay vehículos, ir directamente a crear uno nuevo
        $estado['paso'] = 'manual_vehiculo_nuevo_texto'; 
        saveState($chat_id, $estado);
        sendMessage($chat_id, "No tienes vehículos guardados.\n✍️ Escribe el *tipo de vehículo* (ej.: Toyota Hilux 4x4):");
    }
}

/* ========= MOSTRAR MENÚ DE EMPRESAS COMO TEXTO ========= */
function manual_mostrar_menu_empresas($chat_id, $estado) {
    $conn = db();
    $empresas = $conn ? obtenerEmpresasAdmin($conn, $chat_id) : [];
    $conn?->close();
    
    if ($empresas) {
        // Ordenar alfabéticamente
        usort($empresas, function($a, $b) {
            $empA = $a['nombre'] ?? $a;
            $empB = $b['nombre'] ?? $b;
            return strcasecmp($empA, $empB);
        });
        
        $mensaje = "🏢 *LISTA DE EMPRESAS*\n\n";
        $opciones = [];
        
        foreach ($empresas as $index => $empresa) {
            $numero = $index + 1;
            $nombreEmpresa = $empresa['nombre'];
            $mensaje .= "{$numero}. {$nombreEmpresa}\n";
            $opciones[$numero] = [
                'id' => $empresa['id'],
                'nombre' => $nombreEmpresa
            ];
        }
        
        // Guardar opciones en el estado
        $estado['opciones_empresas'] = $opciones;
        $estado['total_empresas'] = count($empresas);
        saveState($chat_id, $estado);
        
        $mensaje .= "\n✏️ Para seleccionar, *escribe el número* correspondiente\n";
        $mensaje .= "📝 Para crear nueva empresa, *escribe: NUEVO*";
        
        // Teclado con opciones
        $kb = [
            "inline_keyboard" => [
                [
                    ["text" => "🔄 Actualizar", "callback_data" => "manual_refresh_empresas"],
                    ["text" => "⬅️ Volver", "callback_data" => "manual_back_vehiculo_menu"]
                ]
            ]
        ];
        
        sendMessage($chat_id, $mensaje, $kb);
    } else {
        // No hay empresas, ir directamente a crear una nueva
        $estado['paso'] = 'manual_empresa_nuevo_texto'; 
        saveState($chat_id, $estado);
        sendMessage($chat_id, "No tienes empresas guardadas.\n✍️ Escribe el *nombre de la empresa*:");
    }
}

function manual_resend_current_step($chat_id, $estado) {
    $conn = db();
    switch ($estado['paso']) {
        case 'manual_menu':
            manual_mostrar_menu_conductores($chat_id, $estado);
            break;
            
        case 'manual_nombre_nuevo':
            sendMessage($chat_id, "✍️ Escribe el *nombre* del nuevo conductor:"); 
            break;
            
        case 'manual_ruta_menu':
            manual_mostrar_menu_rutas($chat_id, $estado);
            break;
            
        case 'manual_ruta_nueva_texto':
            sendMessage($chat_id, "✍️ Escribe la *ruta del viaje*:"); 
            break;
            
        case 'manual_ruta':
            sendMessage($chat_id, "🛣️ Ingresa la *ruta del viaje*:"); 
            break;
            
        case 'manual_fecha':
            $kb = kbFechaManual();
            $kb = manual_add_back_button($kb, 'ruta_menu');
            sendMessage($chat_id, "📅 Selecciona la *fecha*:", $kb); 
            break;
            
        case 'manual_fecha_mes':
            $anio = $estado["anio"] ?? date("Y");
            $kb = kbMeses($anio);
            $kb = manual_add_back_button($kb, 'fecha');
            sendMessage($chat_id, "📆 Selecciona el *mes*:", $kb); 
            break;
            
        case 'manual_fecha_dia_input':
            $anio = (int)($estado["anio"] ?? date("Y"));
            $mes = (int)($estado["mes"] ?? date("m"));
            $maxDias = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
            sendMessage($chat_id, "✍️ Escribe el *día* del mes (1–$maxDias):"); 
            break;
            
        case 'manual_vehiculo_menu':
            manual_mostrar_menu_vehiculos($chat_id, $estado);
            break;
            
        case 'manual_vehiculo_nuevo_texto':
            sendMessage($chat_id, "✍️ Escribe el *tipo de vehículo*:"); 
            break;
            
        case 'manual_empresa_menu':
            manual_mostrar_menu_empresas($chat_id, $estado);
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
            
        default:
            sendMessage($chat_id, "Continuamos donde ibas. Escribe /cancel para reiniciar.");
    }
    $conn?->close();
}

/* ========= FUNCIÓN PARA AGREGAR BOTÓN VOLVER ========= */
function manual_add_back_button(array $kb, string $back_step): array {
    $kb["inline_keyboard"][] = [[ 
        "text" => "⬅️ Volver", 
        "callback_data" => "manual_back_" . $back_step 
    ]];
    return $kb;
}

function manual_handle_callback($chat_id, &$estado, $cb_data, $cb_id=null) {
    if (($estado["flujo"] ?? "") !== "manual") return;

    // ========= BOTONES DE ACTUALIZAR =========
    if ($cb_data === 'manual_refresh') {
        manual_mostrar_menu_conductores($chat_id, $estado);
        if ($cb_id) answerCallbackQuery($cb_id, "Lista actualizada");
        return;
    }
    
    if ($cb_data === 'manual_refresh_rutas') {
        manual_mostrar_menu_rutas($chat_id, $estado);
        if ($cb_id) answerCallbackQuery($cb_id, "Lista actualizada");
        return;
    }
    
    if ($cb_data === 'manual_refresh_vehiculos') {
        manual_mostrar_menu_vehiculos($chat_id, $estado);
        if ($cb_id) answerCallbackQuery($cb_id, "Lista actualizada");
        return;
    }
    
    if ($cb_data === 'manual_refresh_empresas') {
        manual_mostrar_menu_empresas($chat_id, $estado);
        if ($cb_id) answerCallbackQuery($cb_id, "Lista actualizada");
        return;
    }
    
    // ========= CANCELAR =========
    if ($cb_data === 'manual_cancel') {
        clearState($chat_id);
        sendMessage($chat_id, "❌ Operación cancelada. Usa /manual para empezar de nuevo.");
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

    // FECHA
    if ($cb_data === 'mfecha_hoy') {
        $estado['manual_fecha'] = date("Y-m-d");
        $estado['paso'] = 'manual_vehiculo_menu'; 
        saveState($chat_id, $estado);
        manual_mostrar_menu_vehiculos($chat_id, $estado);
    }
    
    if ($cb_data === 'mfecha_otro') {
        $anio = date("Y"); 
        $estado["anio"] = $anio;
        $estado["paso"] = "manual_fecha_mes"; 
        saveState($chat_id, $estado);
        $kb = kbMeses($anio);
        $kb = manual_add_back_button($kb, 'fecha');
        sendMessage($chat_id, "📆 Selecciona el *mes* ($anio):", $kb);
    }
    
    if (strpos($cb_data, 'mmes_') === 0) {
        $parts = explode('_', $cb_data);
        $estado["anio"] = $parts[1] ?? date("Y");
        $estado["mes"] = $parts[2] ?? date("m");
        $estado["paso"] = "manual_fecha_dia_input"; 
        saveState($chat_id, $estado);
        $anio = (int)$estado["anio"]; 
        $mes = (int)$estado["mes"];
        $maxDias = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
        sendMessage($chat_id, "✍️ Escribe el *día* del mes (1–$maxDias):");
    }

    // MANEJO DE PAGO PARCIAL
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
    // Limpiar opciones almacenadas
    unset(
        $estado['opciones_conductores'],
        $estado['opciones_rutas'],
        $estado['opciones_vehiculos'],
        $estado['opciones_empresas'],
        $estado['total_conductores'],
        $estado['total_rutas'],
        $estado['total_vehiculos'],
        $estado['total_empresas']
    );
    
    switch ($back_step) {
        case 'menu':
            $estado['paso'] = 'manual_menu';
            // Limpiar datos si es necesario
            unset($estado['manual_nombre']);
            manual_mostrar_menu_conductores($chat_id, $estado);
            break;
            
        case 'ruta_menu':
            $estado['paso'] = 'manual_ruta_menu';
            // Limpiar datos de ruta
            unset($estado['manual_ruta']);
            manual_mostrar_menu_rutas($chat_id, $estado);
            break;
            
        case 'fecha':
            $estado['paso'] = 'manual_fecha';
            // Limpiar datos de fecha
            unset($estado['manual_fecha'], $estado['anio'], $estado['mes']);
            $kb = kbFechaManual();
            $kb = manual_add_back_button($kb, 'ruta_menu');
            sendMessage($chat_id, "📅 Selecciona la *fecha*:", $kb);
            break;
            
        case 'vehiculo_menu':
            $estado['paso'] = 'manual_vehiculo_menu';
            // Limpiar datos de vehículo
            unset($estado['manual_vehiculo']);
            manual_mostrar_menu_vehiculos($chat_id, $estado);
            break;
            
        case 'empresa_menu':
            $estado['paso'] = 'manual_empresa_menu';
            // Limpiar datos de empresa
            unset($estado['manual_empresa']);
            manual_mostrar_menu_empresas($chat_id, $estado);
            break;
            
        default:
            // Si no reconoce el paso, volver al menú principal
            $estado['paso'] = 'manual_menu';
            manual_mostrar_menu_conductores($chat_id, $estado);
            break;
    }
    
    saveState($chat_id, $estado);
}

function manual_handle_text($chat_id, &$estado, $text, $photo) {
    if (($estado["flujo"] ?? "") !== "manual") return;

    $text = trim($text);
    $text_upper = strtoupper($text);
    
    switch ($estado["paso"]) {
        case "manual_menu":
            // Verificar si es "NUEVO" o un número
            if ($text_upper === "NUEVO") {
                $estado["paso"] = "manual_nombre_nuevo"; 
                saveState($chat_id, $estado);
                sendMessage($chat_id, "✍️ Escribe el *nombre* del nuevo conductor:");
                break;
            }
            
            // Verificar si es un número válido
            if (is_numeric($text) && isset($estado['opciones_conductores'])) {
                $numero = (int)$text;
                $opciones = $estado['opciones_conductores'] ?? [];
                
                if (isset($opciones[$numero])) {
                    $conductor = $opciones[$numero];
                    $estado['manual_nombre'] = $conductor['nombre'];
                    $estado['paso'] = 'manual_ruta_menu'; 
                    
                    // Limpiar opciones de conductores
                    unset($estado['opciones_conductores'], $estado['total_conductores']);
                    saveState($chat_id, $estado);
                    
                    // Mostrar menú de rutas
                    manual_mostrar_menu_rutas($chat_id, $estado);
                } else {
                    $total = $estado['total_conductores'] ?? 0;
                    sendMessage($chat_id, "⚠️ Número inválido. Escribe un número entre 1 y {$total} o 'NUEVO':");
                }
            } else {
                sendMessage($chat_id, "⚠️ Opción inválida. Escribe el *número* del conductor o 'NUEVO':");
            }
            break;
            
        case "manual_nombre_nuevo":
            $nombre = trim($text);
            if ($nombre === "") { 
                sendMessage($chat_id, "⚠️ El nombre no puede estar vacío. Escribe el *nombre* del nuevo conductor:"); 
                break; 
            }
            
            // Guardar en BD
            $conn = db(); 
            if ($conn) { 
                crearConductorAdmin($conn, $chat_id, $nombre); 
                $conn->close(); 
            }
            
            $estado["manual_nombre"] = $nombre;
            $estado["paso"] = "manual_ruta_menu"; 
            saveState($chat_id, $estado);
            
            // Mostrar menú de rutas
            manual_mostrar_menu_rutas($chat_id, $estado);
            break;
            
        case "manual_ruta_menu":
            // Verificar si es "NUEVO" o un número
            if ($text_upper === "NUEVO") {
                $estado["paso"] = "manual_ruta_nueva_texto"; 
                saveState($chat_id, $estado);
                sendMessage($chat_id, "✍️ Escribe la *ruta del viaje*:");
                break;
            }
            
            // Verificar si es un número válido
            if (is_numeric($text) && isset($estado['opciones_rutas'])) {
                $numero = (int)$text;
                $opciones = $estado['opciones_rutas'] ?? [];
                
                if (isset($opciones[$numero])) {
                    $ruta = $opciones[$numero];
                    $estado['manual_ruta'] = $ruta['ruta'];
                    $estado['paso'] = 'manual_fecha'; 
                    
                    // Limpiar opciones de rutas
                    unset($estado['opciones_rutas'], $estado['total_rutas']);
                    saveState($chat_id, $estado);
                    
                    $kb = kbFechaManual();
                    $kb = manual_add_back_button($kb, 'ruta_menu');
                    sendMessage($chat_id, "🛣️ Ruta seleccionada: *{$ruta['ruta']}*\n\n📅 Selecciona la *fecha*:", $kb);
                } else {
                    $total = $estado['total_rutas'] ?? 0;
                    sendMessage($chat_id, "⚠️ Número inválido. Escribe un número entre 1 y {$total} o 'NUEVO':");
                }
            } else {
                sendMessage($chat_id, "⚠️ Opción inválida. Escribe el *número* de la ruta o 'NUEVO':");
            }
            break;
            
        case "manual_ruta_nueva_texto":
            $rutaTxt = trim($text);
            if ($rutaTxt === "") { 
                sendMessage($chat_id, "⚠️ La ruta no puede estar vacía. Escribe la *ruta del viaje*:"); 
                break; 
            }
            
            // Guardar en BD
            $conn = db(); 
            if ($conn) { 
                crearRutaAdmin($conn, $chat_id, $rutaTxt); 
                $conn->close(); 
            }
            
            $estado["manual_ruta"] = $rutaTxt;
            $estado["paso"] = "manual_fecha"; 
            saveState($chat_id, $estado);
            
            $kb = kbFechaManual();
            $kb = manual_add_back_button($kb, 'ruta_menu');
            sendMessage($chat_id, "🛣️ Ruta guardada: *{$rutaTxt}*\n\n📅 Selecciona la *fecha*:", $kb);
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
            saveState($chat_id, $estado);
            
            // Mostrar menú de vehículos
            manual_mostrar_menu_vehiculos($chat_id, $estado);
            break;
            
        case "manual_vehiculo_menu":
            // Verificar si es "NUEVO" o un número
            if ($text_upper === "NUEVO") {
                $estado["paso"] = "manual_vehiculo_nuevo_texto"; 
                saveState($chat_id, $estado);
                sendMessage($chat_id, "✍️ Escribe el *tipo de vehículo*:");
                break;
            }
            
            // Verificar si es un número válido
            if (is_numeric($text) && isset($estado['opciones_vehiculos'])) {
                $numero = (int)$text;
                $opciones = $estado['opciones_vehiculos'] ?? [];
                
                if (isset($opciones[$numero])) {
                    $vehiculo = $opciones[$numero];
                    $estado['manual_vehiculo'] = $vehiculo['vehiculo'];
                    $estado['paso'] = 'manual_empresa_menu'; 
                    
                    // Limpiar opciones de vehículos
                    unset($estado['opciones_vehiculos'], $estado['total_vehiculos']);
                    saveState($chat_id, $estado);
                    
                    // Mostrar menú de empresas
                    manual_mostrar_menu_empresas($chat_id, $estado);
                } else {
                    $total = $estado['total_vehiculos'] ?? 0;
                    sendMessage($chat_id, "⚠️ Número inválido. Escribe un número entre 1 y {$total} o 'NUEVO':");
                }
            } else {
                sendMessage($chat_id, "⚠️ Opción inválida. Escribe el *número* del vehículo o 'NUEVO':");
            }
            break;
            
        case "manual_vehiculo_nuevo_texto":
            $vehTxt = trim($text);
            if ($vehTxt === "") { 
                sendMessage($chat_id, "⚠️ El *tipo de vehículo* no puede estar vacío. Escríbelo nuevamente:"); 
                break; 
            }
            
            // Guardar en BD
            $conn = db(); 
            if ($conn) { 
                crearVehiculoAdmin($conn, $chat_id, $vehTxt); 
                $conn->close(); 
            }
            
            $estado["manual_vehiculo"] = $vehTxt;
            $estado['paso'] = 'manual_empresa_menu'; 
            saveState($chat_id, $estado);
            
            // Mostrar menú de empresas
            manual_mostrar_menu_empresas($chat_id, $estado);
            break;
            
        case "manual_empresa_menu":
            // Verificar si es "NUEVO" o un número
            if ($text_upper === "NUEVO") {
                $estado["paso"] = "manual_empresa_nuevo_texto"; 
                saveState($chat_id, $estado);
                sendMessage($chat_id, "✍️ Escribe el *nombre de la empresa*:");
                break;
            }
            
            // Verificar si es un número válido
            if (is_numeric($text) && isset($estado['opciones_empresas'])) {
                $numero = (int)$text;
                $opciones = $estado['opciones_empresas'] ?? [];
                
                if (isset($opciones[$numero])) {
                    $empresa = $opciones[$numero];
                    $estado['manual_empresa'] = $empresa['nombre'];
                    
                    // Limpiar opciones de empresas
                    unset($estado['opciones_empresas'], $estado['total_empresas']);
                    
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
                } else {
                    $total = $estado['total_empresas'] ?? 0;
                    sendMessage($chat_id, "⚠️ Número inválido. Escribe un número entre 1 y {$total} o 'NUEVO':");
                }
            } else {
                sendMessage($chat_id, "⚠️ Opción inválida. Escribe el *número* de la empresa o 'NUEVO':");
            }
            break;
            
        case "manual_empresa_nuevo_texto":
            $empTxt = trim($text);
            if ($empTxt === "") { 
                sendMessage($chat_id, "⚠️ El *nombre de la empresa* no puede estar vacío. Escríbelo nuevamente:"); 
                break; 
            }
            
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
    if (!$conn) { 
        sendMessage($chat_id, "❌ Error de conexión a la base de datos."); 
        clearState($chat_id); 
        return; 
    }
    
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

// Función auxiliar para teclado de fecha
function kbFechaManual(): array {
    return [
        "inline_keyboard" => [
            [
                ["text" => "📅 Hoy", "callback_data" => "mfecha_hoy"],
                ["text" => "📆 Otra fecha", "callback_data" => "mfecha_otro"]
            ]
        ]
    ];
}

// Función auxiliar para teclado de meses
function kbMeses($anio): array {
    $meses = ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];
    $kb = ["inline_keyboard" => []];
    $fila = [];
    
    foreach ($meses as $idx => $mes) {
        $mesNum = $idx + 1;
        $fila[] = ["text" => $mes, "callback_data" => "mmes_{$anio}_{$mesNum}"];
        
        if (count($fila) === 3) {
            $kb["inline_keyboard"][] = $fila;
            $fila = [];
        }
    }
    
    if (!empty($fila)) {
        $kb["inline_keyboard"][] = $fila;
    }
    
    return $kb;
}