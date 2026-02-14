function alert_mostrar_presupuestos($chat_id) {
    $conn = db();
    if (!$conn) {
        sendMessage($chat_id, "❌ Error de conexión a la base de datos.");
        return;
    }
    
    $mes_actual = date('n');
    $anio_actual = date('Y');
    
    // 1. Obtener todos los presupuestos (una sola consulta)
    $presupuestos = obtenerPresupuestosEmpresa($conn, $chat_id, $mes_actual, $anio_actual);
    
    if (empty($presupuestos)) {
        $conn->close();
        sendMessage($chat_id, "📭 No tienes presupuestos configurados para el mes actual.\nUsa 'Configurar presupuesto' para agregar uno.");
        return;
    }
    
    // 2. Obtener TODOS los gastos de UNA SOLA VEZ para todas las empresas
    $gastos_por_empresa = [];
    
    // Crear lista de empresas para el IN clause
    $empresas = array_column($presupuestos, 'empresa');
    $placeholders = implode(',', array_fill(0, count($empresas), '?'));
    $tipos = str_repeat('s', count($empresas));
    
    // Consulta OPTIMIZADA: calcula gastos totales por empresa en UNA SOLA CONSULTA
    $sql = "SELECT 
                v.empresa,
                SUM(t.{$this->getColumnaClasificacion()}) as total_gastos
            FROM viajes v
            INNER JOIN ruta_clasificacion rc ON v.ruta = rc.ruta AND v.tipo_vehiculo = rc.tipo_vehiculo
            INNER JOIN tarifas t ON v.empresa = t.empresa AND v.tipo_vehiculo = t.tipo_vehiculo
            WHERE v.empresa IN ($placeholders)
                AND MONTH(v.fecha) = ? 
                AND YEAR(v.fecha) = ?
                AND v.ruta IS NOT NULL 
                AND v.tipo_vehiculo IS NOT NULL
            GROUP BY v.empresa";
    
    $stmt = $conn->prepare($sql);
    
    // Combinar parámetros: empresas (strings) + mes (int) + año (int)
    $params = array_merge($empresas, [$mes_actual, $anio_actual]);
    $stmt->bind_param($tipos . "ii", ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $gastos_por_empresa[$row['empresa']] = floatval($row['total_gastos']);
    }
    $stmt->close();
    
    // 3. Generar mensaje CON LOS DATOS YA CALCULADOS
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
            $estado_texto = "Sin gastos";
        } elseif ($porcentaje <= 80) {
            $estado_emoji = "🟢";
            $estado_texto = "Bien";
        } elseif ($porcentaje <= 100) {
            $estado_emoji = "🟡";
            $estado_texto = "Alerta";
        } else {
            $estado_emoji = "🔴";
            $estado_texto = "EXCEDIDO";
        }
        
        $mensaje .= "$estado_emoji *{$empresa}*\n";
        $mensaje .= "   📅 " . nombreMes($p['mes']) . " {$p['anio']}\n";
        $mensaje .= "   💰 Presupuesto: $" . number_format($presupuesto, 0, ',', '.') . "\n";
        $mensaje .= "   💸 Gastado: $" . number_format($gastos, 0, ',', '.') . "\n";
        $mensaje .= "   📊 " . number_format($porcentaje, 1) . "% - $estado_texto\n";
        
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

// Función auxiliar necesaria
function getColumnaClasificacion() {
    return "clasificacion"; // Ajusta según tu estructura real de BD
}