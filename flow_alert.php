<?php
// flow_alert.php - Sistema de alertas por presupuesto de empresa
require_once __DIR__.'/helpers.php';

/* ========= FUNCIONES DE BASE DE DATOS ========= */
function obtenerEmpresasConViajes($conn, $chat_id) {
    $sql = "SELECT DISTINCT empresa FROM viajes 
            WHERE empresa IS NOT NULL AND empresa != ''
            ORDER BY empresa ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $empresas = [];
    while ($row = $result->fetch_assoc()) {
        $empresas[] = $row['empresa'];
    }
    $stmt->close();
    return $empresas;
}

function obtenerPresupuestosEmpresa($conn, $chat_id, $mes = null, $anio = null) {
    if ($mes === null) $mes = date('n');
    if ($anio === null) $anio = date('Y');
    
    $sql = "SELECT * FROM presupuestos_empresa 
            WHERE chat_id = ? AND mes = ? AND anio = ? AND activo = 1
            ORDER BY empresa ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $chat_id, $mes, $anio);
    $stmt->execute();
    $result = $stmt->get_result();
    $presupuestos = [];
    while ($row = $result->fetch_assoc()) {
        $presupuestos[] = $row;
    }
    $stmt->close();
    return $presupuestos;
}

function guardarPresupuestoEmpresa($conn, $chat_id, $empresa, $presupuesto, $mes, $anio) {
    // Primero verificar si ya existe
    $sql = "SELECT id FROM presupuestos_empresa 
            WHERE empresa = ? AND mes = ? AND anio = ? AND chat_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siii", $empresa, $mes, $anio, $chat_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Actualizar
        $sql = "UPDATE presupuestos_empresa SET presupuesto = ?, activo = 1 
                WHERE empresa = ? AND mes = ? AND anio = ? AND chat_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("dsiii", $presupuesto, $empresa, $mes, $anio, $chat_id);
    } else {
        // Insertar nuevo
        $sql = "INSERT INTO presupuestos_empresa (empresa, presupuesto, mes, anio, chat_id, activo) 
                VALUES (?, ?, ?, ?, ?, 1)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sdiii", $empresa, $presupuesto, $mes, $anio, $chat_id);
    }
    
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function obtenerClasificacionRuta($conn, $ruta, $tipo_vehiculo) {
    $sql = "SELECT clasificacion FROM rutas_clasificacion 
            WHERE ruta = ? AND tipo_vehiculo = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $ruta, $tipo_vehiculo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $clasificacion = $row['clasificacion'];
    } else {
        // Si no encuentra exacto, buscar solo por ruta
        $sql = "SELECT clasificacion FROM rutas_clasificacion 
                WHERE ruta = ? LIMIT 1";
        $stmt2 = $conn->prepare($sql);
        $stmt2->bind_param("s", $ruta);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        if ($row2 = $result2->fetch_assoc()) {
            $clasificacion = $row2['clasificacion'];
        } else {
            $clasificacion = null;
        }
        $stmt2->close();
    }
    
    $stmt->close();
    return $clasificacion;
}

function obtenerTarifa($conn, $empresa, $tipo_vehiculo, $clasificacion) {
    // Primero intentar con empresa exacta
    $sql = "SELECT $clasificacion as tarifa FROM tarifas 
            WHERE empresa = ? AND tipo_vehiculo = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $empresa, $tipo_vehiculo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $tarifa = $row['tarifa'] ?? 0;
    } else {
        $tarifa = 0;
    }
    
    $stmt->close();
    return floatval($tarifa);
}

function calcularGastosEmpresa($conn, $empresa, $mes, $anio) {
    $gastos_totales = 0;
    
    // Obtener todos los viajes de la empresa en el mes/año
    $sql = "SELECT v.ruta, v.tipo_vehiculo 
            FROM viajes v 
            WHERE v.empresa = ? 
            AND MONTH(v.fecha) = ? 
            AND YEAR(v.fecha) = ?
            AND v.ruta IS NOT NULL 
            AND v.tipo_vehiculo IS NOT NULL";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $empresa, $mes, $anio);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($viaje = $result->fetch_assoc()) {
        // Obtener clasificación de la ruta
        $clasificacion = obtenerClasificacionRuta($conn, $viaje['ruta'], $viaje['tipo_vehiculo']);
        
        if ($clasificacion) {
            // Obtener tarifa según empresa, tipo_vehiculo y clasificación
            $tarifa = obtenerTarifa($conn, $empresa, $viaje['tipo_vehiculo'], $clasificacion);
            
            if ($tarifa > 0) {
                $gastos_totales += $tarifa;
            }
        }
    }
    
    $stmt->close();
    return $gastos_totales;
}

function verificarAlertasPresupuesto($conn, $chat_id) {
    $mes_actual = date('n');
    $anio_actual = date('Y');
    
    $alertas = [];
    
    // Obtener todos los presupuestos activos del mes actual
    $presupuestos = obtenerPresupuestosEmpresa($conn, $chat_id, $mes_actual, $anio_actual);
    
    foreach ($presupuestos as $presupuesto) {
        // Calcular gastos de esta empresa
        $gastos = calcularGastosEmpresa($conn, $presupuesto['empresa'], $mes_actual, $anio_actual);
        
        // Si los gastos superan el presupuesto Y no se ha notificado
        if ($gastos > $presupuesto['presupuesto'] && !$presupuesto['notificado']) {
            $alertas[] = [
                'empresa' => $presupuesto['empresa'],
                'presupuesto' => $presupuesto['presupuesto'],
                'gastos' => $gastos,
                'exceso' => $gastos - $presupuesto['presupuesto'],
                'porcentaje' => ($gastos / $presupuesto['presupuesto'] * 100) - 100
            ];
            
            // Marcar como notificado
            $sql = "UPDATE presupuestos_empresa SET notificado = 1 
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $presupuesto['id']);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    return $alertas;
}

/* ========= ENTRYPOINT Y MANEJO DE ESTADO ========= */
function alert_entrypoint($chat_id, $estado) {
    // Si ya estás en alert, reenvía el paso
    if (!empty($estado) && ($estado['flujo'] ?? '') === 'alert') {
        return alert_resend_current_step($chat_id, $estado);
    }
    
    // Nuevo flujo
    $estado = [
        "flujo" => "alert",
        "paso" => "alert_menu"
    ];
    saveState($chat_id, $estado);
    
    // Menú principal
    $kb = [
        "inline_keyboard" => [
            [
                ["text" => "📊 Ver presupuestos", "callback_data" => "alert_ver_presupuestos"],
                ["text" => "⚙️ Configurar presupuesto", "callback_data" => "alert_configurar"]
            ],
            [
                ["text" => "🔔 Verificar alertas ahora", "callback_data" => "alert_verificar"],
                ["text" => "📈 Reporte gastos vs presupuesto", "callback_data" => "alert_reporte"]
            ],
            [
                ["text" => "🔄 Volver al inicio", "callback_data" => "cmd_start"]
            ]
        ]
    ];
    
    sendMessage($chat_id, 
        "🚨 *SISTEMA DE ALERTAS POR PRESUPUESTO*\n\n" .
        "Selecciona una opción:\n\n" .
        "• *Ver presupuestos*: Muestra los presupuestos configurados\n" .
        "• *Configurar presupuesto*: Asigna presupuesto a una empresa\n" .
        "• *Verificar alertas*: Revisa si hay empresas sobre presupuesto\n" .
        "• *Reporte*: Compara gastos reales vs presupuesto", 
        $kb
    );
}

function alert_resend_current_step($chat_id, $estado) {
    switch ($estado['paso']) {
        case 'alert_menu':
            alert_entrypoint($chat_id, $estado);
            break;
        case 'alert_seleccionar_empresa':
            alert_mostrar_empresas($chat_id, $estado);
            break;
        case 'alert_ingresar_presupuesto':
            sendMessage($chat_id, "💰 Ingresa el monto del presupuesto para *{$estado['empresa_seleccionada']}*:\n\nEjemplo: 10000000");
            break;
        case 'alert_seleccionar_periodo':
            alert_mostrar_periodo($chat_id, $estado);
            break;
        default:
            alert_entrypoint($chat_id, $estado);
    }
}

function alert_mostrar_empresas($chat_id, $estado) {
    $conn = db();
    $empresas = $conn ? obtenerEmpresasConViajes($conn, $chat_id) : [];
    $conn?->close();
    
    if (empty($empresas)) {
        sendMessage($chat_id, "ℹ️ No hay empresas registradas en viajes.\nPrimero registra algunos viajes con /manual o /agg.");
        $estado['paso'] = 'alert_menu'; saveState($chat_id, $estado);
        return;
    }
    
    $kb = ["inline_keyboard" => []];
    $row = [];
    
    foreach ($empresas as $empresa) {
        $row[] = [
            "text" => (strlen($empresa) > 15) ? substr($empresa, 0, 12)."..." : $empresa,
            "callback_data" => "alert_empresa_sel_" . base64_encode($empresa)
        ];
        
        if (count($row) === 2) {
            $kb["inline_keyboard"][] = $row;
            $row = [];
        }
    }
    
    if (!empty($row)) {
        $kb["inline_keyboard"][] = $row;
    }
    
    $kb["inline_keyboard"][] = [
        ["text" => "⬅️ Volver", "callback_data" => "alert_back_menu"]
    ];
    
    sendMessage($chat_id, "🏢 *Selecciona una empresa* para asignarle presupuesto:", $kb);
}

function alert_mostrar_periodo($chat_id, $estado) {
    $mes_actual = date('n');
    $anio_actual = date('Y');
    
    $kb = [
        "inline_keyboard" => [
            [
                ["text" => "📅 Mes actual (" . nombreMes($mes_actual) . " $anio_actual)", "callback_data" => "alert_periodo_actual"]
            ],
            [
                ["text" => "📆 Otro mes", "callback_data" => "alert_otro_mes"]
            ],
            [
                ["text" => "⬅️ Volver", "callback_data" => "alert_back_empresa"]
            ]
        ]
    ];
    
    sendMessage($chat_id, 
        "📅 *Selecciona el periodo* para el presupuesto de *{$estado['empresa_seleccionada']}*:\n\n" .
        "• *Mes actual*: " . nombreMes($mes_actual) . " $anio_actual\n" .
        "• *Otro mes*: Selecciona un mes y año diferente", 
        $kb
    );
}

function nombreMes($mes) {
    $meses = [
        1 => "Enero", 2 => "Febrero", 3 => "Marzo", 4 => "Abril",
        5 => "Mayo", 6 => "Junio", 7 => "Julio", 8 => "Agosto",
        9 => "Septiembre", 10 => "Octubre", 11 => "Noviembre", 12 => "Diciembre"
    ];
    return $meses[$mes] ?? "Desconocido";
}

/* ========= MANEJO DE CALLBACKS ========= */
function alert_handle_callback($chat_id, &$estado, $cb_data, $cb_id = null) {
    // También verificar cancelación en callbacks (por si acaso)
    if (trim(strtolower($cb_data)) === '/cancel' || trim(strtolower($cb_data)) === 'cancel') {
        clearState($chat_id);
        sendMessage($chat_id, "✅ Configuración de alertas cancelada.");
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }
    
    if (($estado["flujo"] ?? "") !== "alert") return;
    
    // Manejar botón volver
    if ($cb_data === "alert_back_menu") {
        $estado['paso'] = 'alert_menu'; saveState($chat_id, $estado);
        alert_entrypoint($chat_id, $estado);
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }
    
    if ($cb_data === "alert_back_empresa") {
        $estado['paso'] = 'alert_seleccionar_empresa'; saveState($chat_id, $estado);
        alert_mostrar_empresas($chat_id, $estado);
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }
    
    // Menú principal
    if ($cb_data === "alert_ver_presupuestos") {
        alert_mostrar_presupuestos($chat_id);
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }
    
    if ($cb_data === "alert_configurar") {
        $estado['paso'] = 'alert_seleccionar_empresa'; saveState($chat_id, $estado);
        alert_mostrar_empresas($chat_id, $estado);
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }
    
    if ($cb_data === "alert_verificar") {
        alert_verificar_y_notificar($chat_id);
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }
    
    if ($cb_data === "alert_reporte") {
        alert_generar_reporte($chat_id);
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }
    
    // Seleccionar empresa
    if (strpos($cb_data, 'alert_empresa_sel_') === 0) {
        $empresa_encoded = substr($cb_data, strlen('alert_empresa_sel_'));
        $empresa = base64_decode($empresa_encoded);
        
        $estado['empresa_seleccionada'] = $empresa;
        $estado['paso'] = 'alert_ingresar_presupuesto';
        saveState($chat_id, $estado);
        
        sendMessage($chat_id, 
            "🏢 *Empresa seleccionada:* $empresa\n\n" .
            "💰 *Ingresa el monto del presupuesto:*\n\n" .
            "Ejemplos:\n" .
            "• 10000000 (10 millones)\n" .
            "• 5000000 (5 millones)\n" .
            "• 15000000 (15 millones)\n\n" .
            "Escribe solo el número, sin puntos ni comas."
        );
        
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }
    
    // Periodo
    if ($cb_data === "alert_periodo_actual") {
        $estado['mes'] = date('n');
        $estado['anio'] = date('Y');
        alert_guardar_presupuesto($chat_id, $estado);
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }
    
    if ($cb_data === "alert_otro_mes") {
        // Por ahora usamos el mes actual
        $estado['mes'] = date('n');
        $estado['anio'] = date('Y');
        alert_guardar_presupuesto($chat_id, $estado);
        if ($cb_id) answerCallbackQuery($cb_id);
        return;
    }
    
    if ($cb_id) answerCallbackQuery($cb_id);
}

/* ========= FUNCIONES DE ALERTAS OPTIMIZADAS ========= */
function alert_mostrar_presupuestos($chat_id) {
    $conn = db();
    if (!$conn) {
        sendMessage($chat_id, "❌ Error de conexión a la base de datos.");
        return;
    }
    
    $mes_actual = date('n');
    $anio_actual = date('Y');
    
    // Obtener presupuestos
    $presupuestos = obtenerPresupuestosEmpresa($conn, $chat_id, $mes_actual, $anio_actual);
    
    if (empty($presupuestos)) {
        $conn->close();
        sendMessage($chat_id, "📭 No tienes presupuestos configurados para el mes actual.\nUsa 'Configurar presupuesto' para agregar uno.");
        return;
    }
    
    // OPTIMIZACIÓN: Calcular gastos por empresa de manera eficiente
    $gastos_por_empresa = [];
    
    foreach ($presupuestos as $p) {
        $empresa = $p['empresa'];
        $gastos_por_empresa[$empresa] = calcularGastosEmpresa($conn, $empresa, $mes_actual, $anio_actual);
    }
    
    // Generar mensaje
    $mensaje = "📊 *PRESUPUESTOS CONFIGURADOS - " . nombreMes($mes_actual) . " $anio_actual*\n\n";
    
    $total_presupuesto = 0;
    $total_gastado = 0;
    
    foreach ($presupuestos as $p) {
        $empresa = $p['empresa'];
        $gastos = $gastos_por_empresa[$empresa] ?? 0;
        $presupuesto = floatval($p['presupuesto']);
        
        $total_presupuesto += $presupuesto;
        $total_gastado += $gastos;
        
        $porcentaje = ($presupuesto > 0) ? ($gastos / $presupuesto * 100) : 0;
        $diferencia = $gastos - $presupuesto;
        
        // Emoji según estado
        if ($gastos == 0) {
            $estado_emoji = "⚪";
        } elseif ($porcentaje <= 80) {
            $estado_emoji = "🟢";
        } elseif ($porcentaje <= 100) {
            $estado_emoji = "🟡";
        } else {
            $estado_emoji = "🔴";
        }
        
        $mensaje .= "$estado_emoji *{$empresa}*\n";
        $mensaje .= "   📅 " . nombreMes($mes_actual) . " $anio_actual\n";
        $mensaje .= "   💰 Presupuesto: $" . number_format($presupuesto, 0, ',', '.') . "\n";
        $mensaje .= "   💸 Gastado: $" . number_format($gastos, 0, ',', '.') . "\n";
        $mensaje .= "   📊 " . number_format($porcentaje, 1) . "%\n";
        
        if ($diferencia > 0) {
            $mensaje .= "   ⚠️ *Exceso: +$" . number_format($diferencia, 0, ',', '.') . "*\n";
        } elseif ($diferencia < 0) {
            $mensaje .= "   ✅ Restan: $" . number_format(abs($diferencia), 0, ',', '.') . "\n";
        }
        $mensaje .= "   ────────────\n";
    }
    
    // Totales generales
    $porcentaje_total = ($total_presupuesto > 0) ? ($total_gastado / $total_presupuesto * 100) : 0;
    
    $mensaje .= "\n📊 *RESUMEN GENERAL*\n";
    $mensaje .= "💰 Presupuesto total: $" . number_format($total_presupuesto, 0, ',', '.') . "\n";
    $mensaje .= "💸 Gastado total: $" . number_format($total_gastado, 0, ',', '.') . "\n";
    $mensaje .= "📈 " . number_format($porcentaje_total, 1) . "% del presupuesto\n";
    
    if ($total_gastado > $total_presupuesto) {
        $mensaje .= "🚨 *TOTAL EXCEDIDO*: $" . number_format($total_gastado - $total_presupuesto, 0, ',', '.') . "\n";
    }
    
    $conn->close();
    
    // Enviar mensaje (dividir si es muy largo)
    if (strlen($mensaje) > 4000) {
        $partes = str_split($mensaje, 3500);
        foreach ($partes as $parte) {
            sendMessage($chat_id, $parte);
        }
    } else {
        sendMessage($chat_id, $mensaje);
    }
}

function alert_verificar_y_notificar($chat_id) {
    $conn = db();
    if (!$conn) {
        sendMessage($chat_id, "❌ Error de conexión a la base de datos.");
        return;
    }
    
    $alertas = verificarAlertasPresupuesto($conn, $chat_id);
    $conn->close();
    
    if (empty($alertas)) {
        sendMessage($chat_id, "✅ Todas las empresas están dentro de su presupuesto. ¡Buen trabajo!");
        return;
    }
    
    $mensaje = "🚨 *ALERTAS DE PRESUPUESTO SUPERADO*\n\n";
    
    foreach ($alertas as $alerta) {
        $mensaje .= "🏢 *{$alerta['empresa']}*\n";
        $mensaje .= "💰 Presupuesto: $" . number_format($alerta['presupuesto'], 0, ',', '.') . "\n";
        $mensaje .= "💸 Gastado: $" . number_format($alerta['gastos'], 0, ',', '.') . "\n";
        $mensaje .= "📈 Exceso: $" . number_format($alerta['exceso'], 0, ',', '.') . " (" . number_format($alerta['porcentaje'], 1) . "%)\n";
        $mensaje .= "────────────\n";
    }
    
    $mensaje .= "\n⚠️ *Acciones recomendadas:*\n";
    $mensaje .= "• Revisar viajes registrados\n";
    $mensaje .= "• Ajustar presupuesto si es necesario\n";
    
    sendMessage($chat_id, $mensaje);
}

function alert_generar_reporte($chat_id) {
    $conn = db();
    if (!$conn) {
        sendMessage($chat_id, "❌ Error de conexión a la base de datos.");
        return;
    }
    
    $mes_actual = date('n');
    $anio_actual = date('Y');
    
    $presupuestos = obtenerPresupuestosEmpresa($conn, $chat_id, $mes_actual, $anio_actual);
    
    if (empty($presupuestos)) {
        sendMessage($chat_id, "📭 No hay presupuestos configurados para el mes actual.\nConfigura presupuestos primero.");
        $conn->close();
        return;
    }
    
    $mensaje = "📈 *REPORTE GASTOS vs PRESUPUESTO*\n";
    $mensaje .= "📅 Periodo: " . nombreMes($mes_actual) . " $anio_actual\n\n";
    
    $total_presupuesto = 0;
    $total_gastado = 0;
    
    foreach ($presupuestos as $p) {
        $gastos = calcularGastosEmpresa($conn, $p['empresa'], $mes_actual, $anio_actual);
        $presupuesto = floatval($p['presupuesto']);
        
        $total_presupuesto += $presupuesto;
        $total_gastado += $gastos;
        
        $porcentaje = ($presupuesto > 0) ? ($gastos / $presupuesto * 100) : 0;
        
        $emoji = ($porcentaje <= 80) ? "🟢" : (($porcentaje <= 100) ? "🟡" : "🔴");
        
        $mensaje .= "$emoji *{$p['empresa']}*\n";
        $mensaje .= "  Presupuesto: $" . number_format($presupuesto, 0, ',', '.') . "\n";
        $mensaje .= "  Gastado: $" . number_format($gastos, 0, ',', '.') . "\n";
        $mensaje .= "  (" . number_format($porcentaje, 1) . "%)\n\n";
    }
    
    $total_porcentaje = ($total_presupuesto > 0) ? ($total_gastado / $total_presupuesto * 100) : 0;
    
    $mensaje .= "📊 *TOTAL GENERAL*\n";
    $mensaje .= "💰 Presupuesto total: $" . number_format($total_presupuesto, 0, ',', '.') . "\n";
    $mensaje .= "💸 Gastado total: $" . number_format($total_gastado, 0, ',', '.') . "\n";
    $mensaje .= "📈 " . number_format($total_porcentaje, 1) . "% del presupuesto total\n";
    
    if ($total_gastado > $total_presupuesto) {
        $mensaje .= "🚨 *GASTO TOTAL SOBREPASADO*\n";
        $mensaje .= "Exceso: $" . number_format($total_gastado - $total_presupuesto, 0, ',', '.') . "\n";
    }
    
    $conn->close();
    sendMessage($chat_id, $mensaje);
}

function alert_guardar_presupuesto($chat_id, &$estado) {
    if (!isset($estado['empresa_seleccionada']) || !isset($estado['presupuesto_monto'])) {
        sendMessage($chat_id, "❌ Error: Datos incompletos. Comienza de nuevo con /alert");
        clearState($chat_id);
        return;
    }
    
    $conn = db();
    if (!$conn) {
        sendMessage($chat_id, "❌ Error de conexión a la base de datos.");
        clearState($chat_id);
        return;
    }
    
    $empresa = $estado['empresa_seleccionada'];
    $presupuesto = floatval($estado['presupuesto_monto']);
    $mes = $estado['mes'] ?? date('n');
    $anio = $estado['anio'] ?? date('Y');
    
    $success = guardarPresupuestoEmpresa($conn, $chat_id, $empresa, $presupuesto, $mes, $anio);
    $conn->close();
    
    if ($success) {
        $mensaje = "✅ *PRESUPUESTO CONFIGURADO CORRECTAMENTE*\n\n";
        $mensaje .= "🏢 Empresa: *$empresa*\n";
        $mensaje .= "📅 Periodo: " . nombreMes($mes) . " $anio\n";
        $mensaje .= "💰 Presupuesto: $" . number_format($presupuesto, 0, ',', '.') . "\n\n";
        $mensaje .= "El sistema ahora monitoreará los gastos de esta empresa.";
        
        sendMessage($chat_id, $mensaje);
    } else {
        sendMessage($chat_id, "❌ Error al guardar el presupuesto. Intenta nuevamente.");
    }
    
    clearState($chat_id);
}

/* ========= MANEJO DE TEXTO ========= */
function alert_handle_text($chat_id, &$estado, $text, $photo) {
    // VERIFICAR CANCELACIÓN PRIMERO
    if (trim(strtolower($text)) === '/cancel' || trim(strtolower($text)) === 'cancel') {
        clearState($chat_id);
        sendMessage($chat_id, "✅ Configuración de alertas cancelada.");
        return;
    }
    
    // Solo procesar si estamos en flujo alert
    if (($estado["flujo"] ?? "") !== "alert") return;
    
    switch ($estado["paso"]) {
        case "alert_ingresar_presupuesto":
            // Validar que sea un número válido
            $monto = trim($text);
            
            if (!is_numeric($monto) || $monto <= 0) {
                sendMessage($chat_id, "⚠️ El monto debe ser un número positivo.\nEjemplo: 10000000\n\nEscribe el presupuesto:");
                break;
            }
            
            $estado['presupuesto_monto'] = $monto;
            $estado['paso'] = 'alert_seleccionar_periodo';
            saveState($chat_id, $estado);
            
            alert_mostrar_periodo($chat_id, $estado);
            break;
            
        default:
            sendMessage($chat_id, "❌ Comando no reconocido. Usa /alert para volver al menú.");
            clearState($chat_id);
            break;
    }
}

/* ========= FUNCIÓN PARA EJECUTAR CHECKS AUTOMÁTICOS ========= */
function alert_check_automatico() {
    // Obtener todos los chat_id únicos con presupuestos activos
    $conn = db();
    if (!$conn) return;
    
    $sql = "SELECT DISTINCT chat_id FROM presupuestos_empresa WHERE activo = 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $alertas = verificarAlertasPresupuesto($conn, $row['chat_id']);
        
        if (!empty($alertas)) {
            foreach ($alertas as $alerta) {
                $mensaje = "🚨 *ALERTA AUTOMÁTICA - PRESUPUESTO SUPERADO*\n\n";
                $mensaje .= "🏢 Empresa: *{$alerta['empresa']}*\n";
                $mensaje .= "💰 Presupuesto: $" . number_format($alerta['presupuesto'], 0, ',', '.') . "\n";
                $mensaje .= "💸 Gastado: $" . number_format($alerta['gastos'], 0, ',', '.') . "\n";
                $mensaje .= "📈 Exceso: $" . number_format($alerta['exceso'], 0, ',', '.') . "\n";
                $mensaje .= "(" . number_format($alerta['porcentaje'], 1) . "% sobre el presupuesto)\n\n";
                $mensaje .= "📊 Usa /alert para ver detalles y gestionar.";
                
                sendMessage($row['chat_id'], $mensaje);
            }
        }
    }
    
    $stmt->close();
    $conn->close();
}
?>