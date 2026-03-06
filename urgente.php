<?php
include("nav.php");
// Conexión a la base de datos
define('DB_HOST', 'mysql.hostinger.com');
define('DB_USER', 'u648222299_keboco5');
define('DB_PASS', 'Bucaramanga3011');
define('DB_NAME', 'u648222299_viajes');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// ============================================
// 1. CREAR TABLA DE CONFIGURACIÓN SI NO EXISTE
// ============================================
$sql_crear_tabla = "
CREATE TABLE IF NOT EXISTS config_prestamistas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    prestamista VARCHAR(100) UNIQUE NOT NULL,
    interes_prestamista DECIMAL(5,2) DEFAULT 10.00,
    comision_personal DECIMAL(5,2) DEFAULT 0.00,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($sql_crear_tabla);

// ============================================
// 2. FECHA DE CORTE - CAMBIO DE 10% A 13%
// ============================================
$FECHA_CORTE = new DateTime('2025-10-29');

// ============================================
// 3. PROCESAR GUARDADO DE CONFIGURACIÓN
// ============================================
if (isset($_POST['guardar_config'])) {
    $prestamista = $_POST['prestamista_config'];
    $interes_prestamista = floatval($_POST['interes_prestamista']);
    $comision_personal = floatval($_POST['comision_personal']);
    
    // Insertar o actualizar configuración
    $sql = "INSERT INTO config_prestamistas (prestamista, interes_prestamista, comision_personal) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            interes_prestamista = VALUES(interes_prestamista),
            comision_personal = VALUES(comision_personal),
            fecha_actualizacion = CURRENT_TIMESTAMP";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sdd", $prestamista, $interes_prestamista, $comision_personal);
    
    if ($stmt->execute()) {
        $mensaje_config = "✅ Configuración guardada para <strong>$prestamista</strong>";
        $tipo_mensaje = "success";
    } else {
        $mensaje_config = "❌ Error al guardar configuración";
        $tipo_mensaje = "error";
    }
}

// ============================================
// 4. OBTENER CONFIGURACIÓN ACTUAL DE PRESTAMISTAS
// ============================================
$config_prestamistas = [];
$sql_config = "SELECT * FROM config_prestamistas ORDER BY prestamista";
$result_config = $conn->query($sql_config);
if ($result_config) {
    while ($row = $result_config->fetch_assoc()) {
        $config_prestamistas[$row['prestamista']] = [
            'interes' => $row['interes_prestamista'],
            'comision' => $row['comision_personal']
        ];
    }
}

// ============================================
// 5. VARIABLE PARA MODO PAGADO/PENDIENTE
// ============================================
$modo_pagados = isset($_POST['modo_pagados']) && $_POST['modo_pagados'] == '1';
$fecha_pago_seleccionada = $_POST['fecha_pago'] ?? '';

// ============================================
// 6. FUNCIÓN PARA CALCULAR MESES
// ============================================
function calcularMesesAutomaticos($fecha_prestamo) {
    $hoy = new DateTime();
    $fecha_prestamo_obj = new DateTime($fecha_prestamo);
    
    if ($fecha_prestamo_obj > $hoy) {
        return 1;
    }
    
    $diferencia = $fecha_prestamo_obj->diff($hoy);
    $meses = $diferencia->y * 12 + $diferencia->m;
    
    if ($diferencia->d > 0 || $meses == 0) {
        $meses++;
    }
    
    return max(1, $meses);
}

// Variables para mantener los valores del formulario
$deudores_seleccionados = [];
$prestamista_seleccionado = '';
$fecha_desde = '';
$fecha_hasta = '';
$empresa_seleccionada = '';

// Arreglos para los reportes
$prestamos_por_deudor = [];
$otros_prestamos_por_deudor = [];

// ARRAY para almacenar IDs excluidos
$prestamos_excluidos = isset($_POST['prestamos_excluidos']) ? explode(',', $_POST['prestamos_excluidos']) : [];

// Totales generales
$total_capital_general = 0;
$total_general = 0;
$total_interes_prestamista_general = 0;
$total_comision_personal_general = 0;

$otros_total_capital_general = 0;
$otros_total_general = 0;
$otros_total_interes_prestamista_general = 0;
$otros_total_comision_personal_general = 0;

// Obtener fechas únicas de pagos
$sql_fechas_pago = "SELECT DISTINCT DATE(pagado_at) as fecha_pago 
                    FROM prestamos 
                    WHERE pagado_at IS NOT NULL 
                    ORDER BY pagado_at DESC";
$result_fechas_pago = $conn->query($sql_fechas_pago);
$fechas_pago_disponibles = [];
if ($result_fechas_pago && $result_fechas_pago->num_rows > 0) {
    while ($row = $result_fechas_pago->fetch_assoc()) {
        $fechas_pago_disponibles[] = $row['fecha_pago'];
    }
}

// Obtener empresas desde VIAJES
$sql_empresas = "SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa != '' ORDER BY empresa";
$result_empresas = $conn->query($sql_empresas);

// Obtener lista de prestamistas
$sql_prestamistas_lista = "SELECT DISTINCT prestamista FROM prestamos WHERE prestamista != '' ORDER BY prestamista";
$result_prestamistas_lista = $conn->query($sql_prestamistas_lista);

// FUNCIÓN PARA CALCULAR GANANCIA DE UN PRESTAMISTA
function calcularGananciaPrestamista($conn, $prestamista_nombre, $config_prestamistas, $prestamos_excluidos = [], $empresa_filtrada = '', $modo_pagados = false, $fecha_pago = '') {
    $FECHA_CORTE = '2025-10-29';
    
    // Preparar condición para excluidos
    $condicion_excluidos = '';
    if (!empty($prestamos_excluidos)) {
        $ids_excluidos = array_map('intval', $prestamos_excluidos);
        $condicion_excluidos = "AND id NOT IN (" . implode(',', $ids_excluidos) . ")";
    }
    
    // Preparar condición para empresa
    $condicion_empresa = '';
    if (!empty($empresa_filtrada)) {
        $condicion_empresa = "AND empresa = ?";
    }
    
    // CONDICIÓN PARA MODO PAGADO/PENDIENTE
    if ($modo_pagados) {
        if (empty($fecha_pago)) {
            return [
                'total_viejos' => 0,
                'total_nuevos' => 0,
                'total_prestado' => 0,
                'tu_ganancia' => 0,
                'config' => isset($config_prestamistas[$prestamista_nombre]) 
                    ? $config_prestamistas[$prestamista_nombre] 
                    : ['interes' => 10.00, 'comision' => 0.00]
            ];
        }
        $condicion_pagado = "AND DATE(pagado_at) = ?";
    } else {
        $condicion_pagado = "AND (pagado_at IS NULL)";
    }
    
    $sql = "SELECT 
                monto,
                fecha,
                CASE 
                    WHEN fecha < ? THEN 'viejo'
                    ELSE 'nuevo'
                END as tipo
            FROM prestamos 
            WHERE prestamista = ? 
            $condicion_pagado
            $condicion_empresa
            $condicion_excluidos";
    
    $stmt = $conn->prepare($sql);
    
    $params = [$FECHA_CORTE, $prestamista_nombre];
    $types = "ss";
    
    if ($modo_pagados) {
        $params[] = $fecha_pago;
        $types .= "s";
    }
    
    if (!empty($empresa_filtrada)) {
        $params[] = $empresa_filtrada;
        $types .= "s";
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $total_viejos = 0;
    $total_nuevos = 0;
    $tu_ganancia_total = 0;
    
    $config = isset($config_prestamistas[$prestamista_nombre]) 
        ? $config_prestamistas[$prestamista_nombre] 
        : ['interes' => 10.00, 'comision' => 0.00];
    
    while ($row = $result->fetch_assoc()) {
        $meses = $modo_pagados ? 1 : calcularMesesAutomaticos($row['fecha']);
        
        if ($row['tipo'] == 'viejo') {
            $total_viejos += $row['monto'];
        } else {
            $total_nuevos += $row['monto'];
            $tu_ganancia_total += ($row['monto'] * ($config['comision'] / 100) * $meses);
        }
    }
    
    return [
        'total_viejos' => $total_viejos,
        'total_nuevos' => $total_nuevos,
        'total_prestado' => $total_viejos + $total_nuevos,
        'tu_ganancia' => $tu_ganancia_total,
        'config' => $config
    ];
}

// CALCULAR SUMA TOTAL DE TODAS LAS COMISIONES
$total_todas_tus_comisiones = 0;
$ganancias_por_prestamista = [];

if ($result_prestamistas_lista && $result_prestamistas_lista->num_rows > 0) {
    $result_prestamistas_lista->data_seek(0);
    while ($prest = $result_prestamistas_lista->fetch_assoc()) {
        $prestamista_nombre = $prest['prestamista'];
        
        $datos_ganancia = calcularGananciaPrestamista($conn, $prestamista_nombre, $config_prestamistas, $prestamos_excluidos, '', $modo_pagados, $fecha_pago_seleccionada);
        
        $ganancias_por_prestamista[$prestamista_nombre] = $datos_ganancia;
        $total_todas_tus_comisiones += $datos_ganancia['tu_ganancia'];
    }
}

// Si es POST procesamos el reporte
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['guardar_config'])) {
    $deudores_seleccionados = isset($_POST['deudores']) ? $_POST['deudores'] : [];
    if (is_string($deudores_seleccionados)) {
        $deudores_seleccionados = $deudores_seleccionados !== '' ? explode(',', $deudores_seleccionados) : [];
    }
    $prestamista_seleccionado = $_POST['prestamista'] ?? '';
    $fecha_desde = $_POST['fecha_desde'] ?? '';
    $fecha_hasta = $_POST['fecha_hasta'] ?? '';
    $empresa_seleccionada = $_POST['empresa'] ?? '';
    $prestamos_excluidos = isset($_POST['prestamos_excluidos']) ? explode(',', $_POST['prestamos_excluidos']) : [];
    $modo_pagados = isset($_POST['modo_pagados']) && $_POST['modo_pagados'] == '1';
    $fecha_pago_seleccionada = $_POST['fecha_pago'] ?? '';

    // Prestamistas únicos según el modo
    if ($modo_pagados) {
        $sql_base_prestamistas = "SELECT DISTINCT prestamista FROM prestamos WHERE prestamista != ''";
        
        if (!empty($fecha_pago_seleccionada)) {
            $sql_base_prestamistas .= " AND DATE(pagado_at) = ?";
            $stmt = $conn->prepare($sql_base_prestamistas);
            $stmt->bind_param("s", $fecha_pago_seleccionada);
            $stmt->execute();
            $result_prestamistas = $stmt->get_result();
        } else {
            $sql_base_prestamistas .= " ORDER BY prestamista";
            $result_prestamistas = $conn->query($sql_base_prestamistas);
        }
    } else {
        $sql_prestamistas = "SELECT DISTINCT prestamista 
                             FROM prestamos 
                             WHERE prestamista != '' 
                               AND (pagado_at IS NULL)
                             ORDER BY prestamista";
        $result_prestamistas = $conn->query($sql_prestamistas);
    }

    // CONDUCTORES DESDE VIAJES
    $conductores_filtrados = [];
    if (!$modo_pagados && !empty($fecha_desde) && !empty($fecha_hasta)) {
        if (!empty($empresa_seleccionada)) {
            $sql_conductores = "SELECT DISTINCT nombre 
                                FROM viajes 
                                WHERE fecha BETWEEN ? AND ? 
                                  AND empresa = ? 
                                  AND nombre IS NOT NULL 
                                  AND nombre != '' 
                                ORDER BY nombre";
            $stmt = $conn->prepare($sql_conductores);
            $stmt->bind_param("sss", $fecha_desde, $fecha_hasta, $empresa_seleccionada);
        } else {
            $sql_conductores = "SELECT DISTINCT nombre 
                                FROM viajes 
                                WHERE fecha BETWEEN ? AND ? 
                                  AND nombre IS NOT NULL 
                                  AND nombre != '' 
                                ORDER BY nombre";
            $stmt = $conn->prepare($sql_conductores);
            $stmt->bind_param("ss", $fecha_desde, $fecha_hasta);
        }

        $stmt->execute();
        $result_conductores = $stmt->get_result();

        while ($conductor = $result_conductores->fetch_assoc()) {
            $conductores_filtrados[] = $conductor['nombre'];
        }
    }

    // PRÉSTAMOS DE DEUDORES SELECCIONADOS
    if (!empty($prestamista_seleccionado) && !empty($empresa_seleccionada)) {
        
        $sql_base = "SELECT 
                        id,
                        deudor,
                        prestamista,
                        monto,
                        fecha
                    FROM prestamos 
                    WHERE prestamista = ?
                      AND empresa = ?";
        
        if ($modo_pagados) {
            if (!empty($fecha_pago_seleccionada)) {
                $sql_base .= " AND DATE(pagado_at) = ?";
            } else {
                $sql_base .= " AND 1=0";
            }
        } else {
            $sql_base .= " AND (pagado_at IS NULL)";
        }
        
        if (!$modo_pagados && !empty($deudores_seleccionados)) {
            $placeholders = str_repeat('?,', count($deudores_seleccionados) - 1) . '?';
            $sql_base .= " AND deudor IN ($placeholders)";
        }
        
        $sql_base .= " ORDER BY deudor, fecha";
        
        $stmt = $conn->prepare($sql_base);
        
        $params = [$prestamista_seleccionado, $empresa_seleccionada];
        $types = "ss";
        
        if ($modo_pagados && !empty($fecha_pago_seleccionada)) {
            $params[] = $fecha_pago_seleccionada;
            $types .= "s";
        }
        
        if (!$modo_pagados && !empty($deudores_seleccionados)) {
            foreach ($deudores_seleccionados as $deudor) {
                $params[] = $deudor;
                $types .= "s";
            }
        }
        
        if (count($params) > 2 || ($modo_pagados && !empty($fecha_pago_seleccionada))) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result_detalle = $stmt->get_result();

            $config_actual = isset($config_prestamistas[$prestamista_seleccionado]) 
                ? $config_prestamistas[$prestamista_seleccionado] 
                : ['interes' => 10.00, 'comision' => 0.00];

            while ($fila = $result_detalle->fetch_assoc()) {
                $deudor = $fila['deudor'];
                
                if ($modo_pagados && !in_array($deudor, $deudores_seleccionados)) {
                    $deudores_seleccionados[] = $deudor;
                }
                
                $meses = $modo_pagados ? 1 : calcularMesesAutomaticos($fila['fecha']);
                $fecha_prestamo_dt = new DateTime($fila['fecha']);
                $es_excluido = in_array($fila['id'], $prestamos_excluidos);
                
                $es_prestamo_viejo = ($fecha_prestamo_dt < $FECHA_CORTE);
                
                if ($es_prestamo_viejo) {
                    $interes_prestamista_monto = $fila['monto'] * (10 / 100) * $meses;
                    $comision_personal_monto = 0;
                    $tasa_interes = 10;
                    $total_prestamo = $fila['monto'] + $interes_prestamista_monto;
                } else {
                    $interes_prestamista_monto = $fila['monto'] * ($config_actual['interes'] / 100) * $meses;
                    $comision_personal_monto = $fila['monto'] * ($config_actual['comision'] / 100) * $meses;
                    $tasa_interes = $config_actual['interes'];
                    $total_prestamo = $fila['monto'] + $interes_prestamista_monto;
                }

                if (!isset($prestamos_por_deudor[$deudor])) {
                    $prestamos_por_deudor[$deudor] = [
                        'total_capital' => 0,
                        'total_general' => 0,
                        'total_interes_prestamista' => 0,
                        'total_comision_personal' => 0,
                        'cantidad_prestamos' => 0,
                        'cantidad_viejos' => 0,
                        'cantidad_nuevos' => 0,
                        'prestamos_detalle' => []
                    ];
                }

                if (!$es_excluido) {
                    $prestamos_por_deudor[$deudor]['total_capital'] += $fila['monto'];
                    $prestamos_por_deudor[$deudor]['total_general'] += $total_prestamo;
                    $prestamos_por_deudor[$deudor]['total_interes_prestamista'] += $interes_prestamista_monto;
                    $prestamos_por_deudor[$deudor]['total_comision_personal'] += $comision_personal_monto;
                    $prestamos_por_deudor[$deudor]['cantidad_prestamos']++;
                }
                
                if ($es_prestamo_viejo) {
                    $prestamos_por_deudor[$deudor]['cantidad_viejos']++;
                } else {
                    $prestamos_por_deudor[$deudor]['cantidad_nuevos']++;
                }

                $prestamos_por_deudor[$deudor]['prestamos_detalle'][] = [
                    'id' => $fila['id'],
                    'monto' => $fila['monto'],
                    'fecha' => $fila['fecha'],
                    'meses' => $meses,
                    'tipo' => $es_prestamo_viejo ? 'viejo' : 'nuevo',
                    'tasa_interes' => $tasa_interes,
                    'interes_prestamista' => $interes_prestamista_monto,
                    'comision_personal' => $comision_personal_monto,
                    'total' => $total_prestamo,
                    'incluido' => !$es_excluido,
                    'excluido' => $es_excluido,
                    'pagado_at' => $modo_pagados ? $fecha_pago_seleccionada : NULL
                ];
            }
        }
    }

    // OTROS DEUDORES
    if (!$modo_pagados && !empty($prestamista_seleccionado) && !empty($empresa_seleccionada)) {
        $condicion_excluidos = '';
        if (!empty($prestamos_excluidos)) {
            $ids_excluidos = array_map('intval', $prestamos_excluidos);
            $condicion_excluidos = " AND id NOT IN (" . implode(',', $ids_excluidos) . ")";
        }
        
        $sql_otros = "SELECT 
                        id,
                        deudor,
                        prestamista,
                        monto,
                        fecha
                      FROM prestamos
                      WHERE prestamista = ?
                        AND empresa = ?
                        AND (pagado_at IS NULL)
                        AND deudor IS NOT NULL
                        AND deudor != ''
                        $condicion_excluidos
                      ORDER BY deudor, fecha";
        $stmt_otros = $conn->prepare($sql_otros);
        $stmt_otros->bind_param("ss", $prestamista_seleccionado, $empresa_seleccionada);
        $stmt_otros->execute();
        $result_otros = $stmt_otros->get_result();

        $config_actual = isset($config_prestamistas[$prestamista_seleccionado]) 
            ? $config_prestamistas[$prestamista_seleccionado] 
            : ['interes' => 10.00, 'comision' => 0.00];

        while ($fila = $result_otros->fetch_assoc()) {
            $deudor = $fila['deudor'];

            if (in_array($deudor, $deudores_seleccionados)) {
                continue;
            }

            $meses = calcularMesesAutomaticos($fila['fecha']);
            $fecha_prestamo_dt = new DateTime($fila['fecha']);
            $es_prestamo_viejo = ($fecha_prestamo_dt < $FECHA_CORTE);

            if ($es_prestamo_viejo) {
                $interes_prestamista_monto = $fila['monto'] * (10 / 100) * $meses;
                $comision_personal_monto = 0;
                $total_prestamo = $fila['monto'] + $interes_prestamista_monto;
            } else {
                $interes_prestamista_monto = $fila['monto'] * ($config_actual['interes'] / 100) * $meses;
                $comision_personal_monto = $fila['monto'] * ($config_actual['comision'] / 100) * $meses;
                $total_prestamo = $fila['monto'] + $interes_prestamista_monto;
            }

            if (!isset($otros_prestamos_por_deudor[$deudor])) {
                $otros_prestamos_por_deudor[$deudor] = [
                    'total_capital' => 0,
                    'total_general' => 0,
                    'total_interes_prestamista' => 0,
                    'total_comision_personal' => 0,
                    'cantidad_prestamos' => 0,
                    'cantidad_viejos' => 0,
                    'cantidad_nuevos' => 0
                ];
            }

            $otros_prestamos_por_deudor[$deudor]['total_capital'] += $fila['monto'];
            $otros_prestamos_por_deudor[$deudor]['total_general'] += $total_prestamo;
            $otros_prestamos_por_deudor[$deudor]['total_interes_prestamista'] += $interes_prestamista_monto;
            $otros_prestamos_por_deudor[$deudor]['total_comision_personal'] += $comision_personal_monto;
            $otros_prestamos_por_deudor[$deudor]['cantidad_prestamos']++;
            
            if ($es_prestamo_viejo) {
                $otros_prestamos_por_deudor[$deudor]['cantidad_viejos']++;
            } else {
                $otros_prestamos_por_deudor[$deudor]['cantidad_nuevos']++;
            }
        }
    }

} else {
    $sql_prestamistas = "SELECT DISTINCT prestamista FROM prestamos WHERE prestamista != '' AND (pagado_at IS NULL) ORDER BY prestamista";
    $result_prestamistas = $conn->query($sql_prestamistas);
    $conductores_filtrados = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Préstamos - <?php echo $modo_pagados ? 'Pagados' : 'Pendientes'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{
            --bg-main: #020617;
            --bg-card: #0b1220;
            --bg-card-soft: #020617;
            --accent: #38bdf8;
            --accent-soft: rgba(56,189,248,0.15);
            --accent-strong: #0ea5e9;
            --danger: #ef4444;
            --success: #22c55e;
            --text-main: #e5e7eb;
            --text-soft: #9ca3af;
            --border-subtle: #1f2937;
            --table-header: #020617;
        }

        *{
            box-sizing:border-box;
        }

        body{
            margin:0;
            padding:0;
            font-family:'Inter',system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
            background: radial-gradient(circle at top, #0f172a 0, #020617 40%, #000 100%);
            color:var(--text-main);
        }

        .container{
            max-width:1600px;
            margin:20px auto 40px;
            padding:24px;
            background:linear-gradient(135deg,rgba(15,23,42,0.95),rgba(2,6,23,0.98));
            border-radius:24px;
            border:1px solid rgba(148,163,184,0.25);
            box-shadow:0 20px 45px rgba(15,23,42,0.9);
            backdrop-filter:blur(18px);
        }

        h1{
            margin-top:0;
            font-size:1.9rem;
            font-weight:700;
            background:linear-gradient(90deg,#38bdf8,#a855f7,#22c55e);
            -webkit-background-clip:text;
            color:transparent;
        }

        .page-header{
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:16px;
            margin-bottom:18px;
        }

        .page-header-badge{
            display:inline-flex;
            align-items:center;
            gap:6px;
            padding:4px 12px;
            border-radius:999px;
            background:rgba(56,189,248,0.12);
            border:1px solid rgba(56,189,248,0.4);
            font-size:0.75rem;
            text-transform:uppercase;
            color:var(--accent);
        }

        .total-comisiones-general {
            background: linear-gradient(135deg, #1e40af, #1d4ed8);
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 20px;
            border: 2px solid rgba(56, 189, 248, 0.5);
            text-align: center;
            box-shadow: 0 10px 25px rgba(30, 64, 175, 0.4);
        }

        .total-comisiones-general strong {
            font-size: 1.1rem;
            color: white;
            margin-right: 10px;
        }

        .monto-total {
            font-size: 1.8rem;
            font-weight: 800;
            color: #22c55e;
            text-shadow: 0 2px 10px rgba(34, 197, 94, 0.5);
        }

        .modo-switch-container {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        .modo-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #1f2937;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #22c55e;
        }

        input:checked + .slider:before {
            transform: translateX(30px);
        }

        .modo-label {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .modo-pendientes {
            color: #f59e0b;
        }

        .modo-pagados {
            color: #22c55e;
        }

        .fecha-pago-container {
            flex: 1;
            max-width: 300px;
        }

        .config-section {
            background: var(--bg-card);
            border-radius: 18px;
            border: 1px solid var(--border-subtle);
            padding: 18px 20px;
            margin-bottom: 24px;
        }

        .config-section h3 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--accent);
        }

        .config-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 0.85rem;
            background: var(--bg-card-soft);
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid var(--border-subtle);
        }

        .config-table th {
            background: linear-gradient(180deg, var(--table-header), #020617);
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            color: var(--text-soft);
        }

        .config-table td {
            padding: 8px 12px;
            border-bottom: 1px solid rgba(31,41,55,0.9);
        }

        .config-input {
            width: 100%;
            padding: 6px 8px;
            border-radius: 8px;
            border: 1px solid rgba(148,163,184,0.35);
            background: #020617;
            color: var(--text-main);
            font-size: 0.85rem;
            text-align: center;
        }

        .btn-guardar {
            width: auto;
            padding: 6px 16px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
        }

        .ganancia-cell {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 8px;
            text-align: center;
            font-weight: 700;
            color: #22c55e;
        }

        .badge-tipo {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 5px;
        }

        .badge-viejo {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.4);
        }

        .badge-nuevo {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.4);
        }

        .nota-pagados{
            background:rgba(34,197,94,0.11);
            border-radius:14px;
            padding:12px 14px;
            border:1px solid rgba(34,197,94,0.4);
            font-size:0.85rem;
            margin-bottom:18px;
        }

        .form-card{
            background:var(--bg-card);
            border-radius:18px;
            border:1px solid var(--border-subtle);
            padding:16px 18px 18px;
            margin-bottom:20px;
        }

        select,button,input{
            width:100%;
            padding:9px 11px;
            margin:4px 0;
            border-radius:10px;
            border:1px solid rgba(148,163,184,0.35);
            background:#020617;
            color:var(--text-main);
            font-size:0.9rem;
        }

        button{
            border-radius:999px;
            background:linear-gradient(135deg,var(--accent),var(--accent-strong));
            border:none;
            font-weight:600;
            text-transform:uppercase;
            font-size:0.8rem;
            cursor:pointer;
            box-shadow:0 10px 18px rgba(56,189,248,0.35);
        }

        .resultados{margin-top:26px;}

        table{
            width:100%;
            border-collapse:collapse;
            margin-top:12px;
            font-size:0.85rem;
            background:var(--bg-card-soft);
            border-radius:16px;
            overflow:hidden;
            border:1px solid var(--border-subtle);
        }

        th,td{
            border-bottom:1px solid rgba(31,41,55,0.9);
            padding:8px 9px;
            text-align:left;
        }

        thead th{
            background:linear-gradient(180deg,var(--table-header),#020617);
            font-size:0.78rem;
            text-transform:uppercase;
            color:var(--text-soft);
        }

        .moneda{text-align:right;}

        .form-row{
            display:flex;
            flex-wrap:wrap;
            gap:18px;
        }
        .form-col{flex:1 1 280px;}

        .detalle-toggle{
            cursor:pointer;
            color:var(--accent);
            font-weight:500;
        }
        .detalle-toggle::before{
            content:"▸ ";
        }

        .excluido{
            background:rgba(127,29,29,0.45) !important;
            text-decoration:line-through;
            color:#9ca3af;
        }

        .abono-input {
            width:90px;
            padding:4px;
            font-size:0.78rem;
            text-align:center;
            border-radius:8px;
            background:#020617;
            margin-right:5px;
        }

        .capital-pendiente {
            font-size:0.7rem;
            color: #f59e0b;
            margin-left: 8px;
            font-weight: bold;
            background: rgba(245, 158, 11, 0.15);
            padding: 2px 6px;
            border-radius: 12px;
            border: 1px solid rgba(245, 158, 11, 0.4);
        }

        .sobrante-info {
            font-size:0.7rem;
            color: #22c55e;
            margin-left: 8px;
            font-weight: bold;
            background: rgba(34, 197, 94, 0.15);
            padding: 2px 6px;
            border-radius: 12px;
            border: 1px solid rgba(34, 197, 94, 0.4);
        }

        .meses-input{
            width:60px;
            padding:4px;
            font-size:0.78rem;
            text-align:center;
            border-radius:8px;
            background:#020617;
        }

        .checkbox-excluir{
            transform:scale(1.2);
            cursor:pointer;
        }

        .acciones{text-align:center;}

        .info-meses{
            background:rgba(251,191,36,0.08);
            border-radius:14px;
            padding:10px 12px;
            margin:10px 0 16px;
            border:1px solid rgba(245,158,11,0.4);
        }

        .buscador-container{
            position:relative;
            margin-bottom:10px;
        }

        .deudores-container{
            border:1px solid var(--border-subtle);
            border-radius:14px;
            max-height:220px;
            overflow-y:auto;
            background:#020617;
        }

        .deudor-item{
            padding:7px 9px;
            cursor:pointer;
            border-bottom:1px solid rgba(31,41,55,0.9);
            display:flex;
            align-items:center;
            justify-content:space-between;
        }

        .deudor-item.selected{
            background:linear-gradient(90deg,#1d4ed8,#22c55e);
            color:white;
        }

        .fecha-row{
            display:flex;
            flex-wrap:wrap;
            gap:12px;
        }
        .fecha-col{
            flex:1 1 180px;
        }

        .subtitulo-cuadro{
            margin-top:20px;
            font-size:1rem;
            font-weight:600;
        }

        .cuadro-otros{
            background:linear-gradient(145deg,#020617,#020617);
            padding:10px 12px 14px;
            border-radius:16px;
            border:1px solid var(--border-subtle);
            margin-top:8px;
        }

        .info-porcentajes {
            background: rgba(56, 189, 248, 0.08);
            border-radius: 14px;
            padding: 12px 14px;
            margin: 10px 0 16px;
            border: 1px solid rgba(56, 189, 248, 0.4);
        }

        .alerta-empresa {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.4);
            border-radius: 12px;
            padding: 12px 15px;
            margin: 10px 0;
            color: #f59e0b;
        }

        @media (max-width: 900px){
            .container{padding:16px;}
            .config-table th:nth-child(2),
            .config-table td:nth-child(2) {
                display: none;
            }
        }

        .filtro-modo-activo {
            border-left: 4px solid #22c55e;
        }
        
        .filtro-modo-inactivo {
            border-left: 4px solid #f59e0b;
        }

        .total-hoy {
            font-weight: 700;
            color: #22c55e;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div>
                <div class="page-header-badge">
                    <span class="icon">📊</span>
                    <span>Panel de préstamos <?php echo $modo_pagados ? 'pagados' : 'pendientes'; ?></span>
                </div>
                <h1>Reporte de Préstamos Consolidados</h1>
            </div>
        </div>
        
        <!-- Switch para cambiar entre modo pendiente/pagado -->
        <div class="modo-switch-container">
            <div class="modo-toggle">
                <label class="switch">
                    <input type="checkbox" id="toggleModo" name="modo_pagados" value="1" 
                           <?php echo $modo_pagados ? 'checked' : ''; ?>
                           onchange="toggleModoPagados()">
                    <span class="slider"></span>
                </label>
                <span class="modo-label">
                    <?php if ($modo_pagados): ?>
                        <span class="modo-pagados">✅ MODO PRÉSTAMOS PAGADOS</span>
                    <?php else: ?>
                        <span class="modo-pendientes">⏳ MODO PRÉSTAMOS PENDIENTES</span>
                    <?php endif; ?>
                </span>
            </div>
            
            <?php if ($modo_pagados): ?>
            <div class="fecha-pago-container">
                <label for="fecha_pago">Fecha de pago:</label>
                <select name="fecha_pago" id="fecha_pago" required>
                    <option value="">-- Seleccionar fecha de pago --</option>
                    <?php foreach ($fechas_pago_disponibles as $fecha): ?>
                        <option value="<?php echo htmlspecialchars($fecha); ?>" 
                            <?php echo $fecha_pago_seleccionada == $fecha ? 'selected' : ''; ?>>
                            <?php echo date('d/m/Y', strtotime($fecha)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- TOTAL GENERAL DE TODAS TUS COMISIONES -->
        <div class="total-comisiones-general">
            <strong>🎯 TOTAL DE TODAS TUS COMISIONES:</strong>
            <span class="monto-total">$ <?php echo number_format($total_todas_tus_comisiones, 0, ',', '.'); ?></span>
            <br>
            <small style="color: rgba(255,255,255,0.8); font-size: 0.85rem;">
                <?php if ($modo_pagados): ?>
                    Suma de todas las comisiones de préstamos PAGADOS en la fecha seleccionada
                <?php else: ?>
                    Suma de todas las comisiones de préstamos PENDIENTES
                <?php endif; ?>
            </small>
        </div>
        
        <!-- Configuración de Porcentajes por Prestamista -->
        <div class="config-section">
            <h3>⚙ Configurar Porcentajes por Prestamista</h3>
            
            <?php if (isset($mensaje_config)): ?>
                <div class="mensaje-config <?php echo $tipo_mensaje == 'success' ? 'mensaje-success' : 'mensaje-error'; ?>">
                    <?php echo $mensaje_config; ?>
                </div>
            <?php endif; ?>
            
            <div class="info-porcentajes">
                <strong>📅 REGLA DE FECHA DE CORTE:</strong><br>
                • <span class="badge-tipo badge-viejo">Préstamos VIEJOS</span> (antes del 29-10-2025): <strong>10% todo para el prestamista, 0% comisión para ti</strong><br>
                • <span class="badge-tipo badge-nuevo">Préstamos NUEVOS</span> (después del 29-10-2025): <strong>Total 13% (configurado abajo)</strong>
            </div>
            
            <table class="config-table">
                <thead>
                    <tr>
                        <th>Prestamista</th>
                        <th>Total <?php echo $modo_pagados ? 'Pagado' : 'Prestado'; ?> Activo</th>
                        <th>Interés del Prestamista (%)</th>
                        <th>Tu Comisión (%)</th>
                        <th><strong style="color: var(--accent);">TU GANANCIA TOTAL</strong></th>
                        <th>Total para Deudor (%)</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_prestamistas_lista && $result_prestamistas_lista->num_rows > 0): ?>
                        <?php 
                        $result_prestamistas_lista->data_seek(0);
                        while ($prest = $result_prestamistas_lista->fetch_assoc()): 
                            $prestamista_nombre = $prest['prestamista'];
                            
                            $datos_ganancia = isset($ganancias_por_prestamista[$prestamista_nombre]) 
                                ? $ganancias_por_prestamista[$prestamista_nombre] 
                                : ['total_viejos' => 0, 'total_nuevos' => 0, 'total_prestado' => 0, 'tu_ganancia' => 0, 'config' => ['interes' => 10.00, 'comision' => 0.00]];
                            
                            $total_viejos = $datos_ganancia['total_viejos'];
                            $total_nuevos = $datos_ganancia['total_nuevos'];
                            $total_prestado = $datos_ganancia['total_prestado'];
                            $tu_ganancia = $datos_ganancia['tu_ganancia'];
                            $config = $datos_ganancia['config'];
                            
                            $total_porcentaje = $config['interes'] + $config['comision'];
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($prestamista_nombre); ?></strong></td>
                                
                                <td class="moneda" style="text-align: center;">
                                    <strong>$ <?php echo number_format($total_prestado, 0, ',', '.'); ?></strong>
                                    <br>
                                    <small style="font-size: 0.75rem;">
                                        <span class="badge-tipo badge-viejo">$ <?php echo number_format($total_viejos, 0, ',', '.'); ?> viejos</span>
                                        <br>
                                        <span class="badge-tipo badge-nuevo">$ <?php echo number_format($total_nuevos, 0, ',', '.'); ?> nuevos</span>
                                    </small>
                                </td>
                                
                                <td>
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="prestamista_config" value="<?php echo htmlspecialchars($prestamista_nombre); ?>">
                                        <input type="number" name="interes_prestamista" 
                                               class="config-input" 
                                               value="<?php echo number_format($config['interes'], 2, '.', ''); ?>"
                                               step="0.1" min="0" max="100" required>
                                </td>
                                <td>
                                        <input type="number" name="comision_personal" 
                                               class="config-input" 
                                               value="<?php echo number_format($config['comision'], 2, '.', ''); ?>"
                                               step="0.1" min="0" max="100" required>
                                </td>
                                
                                <td class="ganancia-cell">
                                    <strong>$ <?php echo number_format($tu_ganancia, 0, ',', '.'); ?></strong>
                                    <br>
                                    <small>
                                        (<?php echo number_format($config['comision'], 1); ?>% de $<?php echo number_format($total_nuevos, 0, ',', '.'); ?> nuevos)
                                    </small>
                                </td>
                                
                                <td style="text-align: center; font-weight: 600; color: var(--accent);">
                                    <?php echo number_format($total_porcentaje, 2); ?>%
                                </td>
                                <td>
                                        <button type="submit" name="guardar_config" class="btn-guardar">
                                            💾 Guardar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 20px; color: var(--text-soft);">
                                No hay prestamistas registrados
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="nota-pagados">
            <strong>Modo actual:</strong> 
            <?php if ($modo_pagados): ?>
                Estás viendo <strong>préstamos PAGADOS</strong> <?php echo !empty($fecha_pago_seleccionada) ? 'el ' . date('d/m/Y', strtotime($fecha_pago_seleccionada)) : ''; ?>.
            <?php else: ?>
                Estás viendo <strong>préstamos PENDIENTES de pago</strong>.
            <?php endif; ?>
        </div>
        
        <form method="POST" id="formPrincipal">
            <input type="hidden" name="modo_pagados" id="inputModoPagados" value="<?php echo $modo_pagados ? '1' : '0'; ?>">
            <input type="hidden" name="prestamos_excluidos" id="prestamosExcluidos" 
                   value="<?php echo htmlspecialchars(implode(',', $prestamos_excluidos)); ?>">
            
            <div class="filtro-fechas form-card <?php echo $modo_pagados ? 'filtro-modo-activo' : 'filtro-modo-inactivo'; ?>">
                <h3>Filtro para préstamos <?php echo $modo_pagados ? 'pagados' : 'pendientes'; ?></h3>
                
                <div class="fecha-row">
                    <?php if ($modo_pagados): ?>
                    <div class="fecha-col">
                        <label for="fecha_pago">Fecha de pago:</label>
                        <select name="fecha_pago" id="fecha_pago" required>
                            <option value="">-- Seleccionar fecha de pago --</option>
                            <?php foreach ($fechas_pago_disponibles as $fecha): ?>
                                <option value="<?php echo htmlspecialchars($fecha); ?>" 
                                    <?php echo $fecha_pago_seleccionada == $fecha ? 'selected' : ''; ?>>
                                    <?php echo date('d/m/Y', strtotime($fecha)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                    <div class="fecha-col">
                        <label for="fecha_desde">Fecha Desde:</label>
                        <input type="date" name="fecha_desde" id="fecha_desde" 
                               value="<?php echo htmlspecialchars($fecha_desde); ?>" required>
                    </div>
                    <div class="fecha-col">
                        <label for="fecha_hasta">Fecha Hasta:</label>
                        <input type="date" name="fecha_hasta" id="fecha_hasta" 
                               value="<?php echo htmlspecialchars($fecha_hasta); ?>" required>
                    </div>
                    <?php endif; ?>
                    
                    <div class="fecha-col">
                        <label for="empresa">Empresa:</label>
                        <select name="empresa" id="empresa" required>
                            <option value="">-- Seleccionar Empresa --</option>
                            <?php 
                            if ($result_empresas && $result_empresas->num_rows > 0) {
                                $result_empresas->data_seek(0);
                                while ($empresa = $result_empresas->fetch_assoc()): 
                                    if (!empty($empresa['empresa'])):
                            ?>
                                    <option value="<?php echo htmlspecialchars($empresa['empresa']); ?>" 
                                        <?php echo $empresa_seleccionada == $empresa['empresa'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($empresa['empresa']); ?>
                                    </option>
                            <?php 
                                    endif;
                                endwhile; 
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <?php if (!empty($empresa_seleccionada)): ?>
                <div class="alerta-empresa">
                    <strong>⚠ FILTRO DE EMPRESA ACTIVO:</strong> Solo se mostrarán préstamos <?php echo $modo_pagados ? 'pagados' : 'pendientes'; ?> de la empresa <strong>"<?php echo htmlspecialchars($empresa_seleccionada); ?>"</strong>.
                </div>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <?php if (!$modo_pagados): ?>
                <div class="form-col">
                    <div class="form-card">
                        <div class="form-group">
                            <label for="deudores">Seleccionar Conductores:</label>
                            
                            <div class="buscador-container">
                                <input type="text" id="buscadorDeudores" class="buscador-input" 
                                       placeholder="Buscar conductor...">
                                <span class="buscador-icon">🔍</span>
                            </div>

                            <div class="botones-seleccion">
                                <button type="button" onclick="seleccionarTodos()">Seleccionar todos</button>
                                <button type="button" onclick="deseleccionarTodos()">Deseleccionar todos</button>
                            </div>
                            
                            <div class="deudores-container" id="listaDeudores">
                                <?php 
                                if (!empty($conductores_filtrados)) {
                                    foreach ($conductores_filtrados as $conductor): 
                                        $es_seleccionado = in_array($conductor, $deudores_seleccionados);
                                ?>
                                    <div class="deudor-item <?php echo $es_seleccionado ? 'selected' : ''; ?>" 
                                         data-value="<?php echo htmlspecialchars($conductor); ?>">
                                        <span><?php echo htmlspecialchars($conductor); ?></span>
                                        <span class="deudor-pill">Conductor</span>
                                    </div>
                                <?php 
                                    endforeach; 
                                } else {
                                    echo '<div style="padding: 10px; text-align: center; color: #9ca3af;">';
                                    if (!empty($fecha_desde) && !empty($fecha_hasta)) {
                                        echo 'No se encontraron conductores';
                                    } else {
                                        echo 'Selecciona fechas para ver conductores';
                                    }
                                    echo '</div>';
                                }
                                ?>
                            </div>
                            
                            <input type="hidden" name="deudores" id="deudoresSeleccionados" 
                                   value="<?php echo htmlspecialchars(implode(',', $deudores_seleccionados)); ?>">
                            
                            <div class="contador-deudores" id="contadorDeudores">
                                <?php 
                                if (!empty($conductores_filtrados)) {
                                    $total_conductores = count($conductores_filtrados);
                                    $seleccionados = count($deudores_seleccionados);
                                    echo "Seleccionados: $seleccionados de $total_conductores conductores";
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="form-col">
                    <div class="form-card">
                        <div class="form-group">
                            <label for="prestamista">Seleccionar Prestamista:</label>
                            <select name="prestamista" id="prestamista" required>
                                <option value="">-- Seleccionar Prestamista --</option>
                                <?php 
                                if (isset($result_prestamistas) && $result_prestamistas && $result_prestamistas->num_rows > 0) {
                                    $result_prestamistas->data_seek(0);
                                    while ($prestamista = $result_prestamistas->fetch_assoc()): 
                                        $config_p = isset($config_prestamistas[$prestamista['prestamista']]) 
                                            ? $config_prestamistas[$prestamista['prestamista']] 
                                            : ['interes' => 10.00, 'comision' => 0.00];
                                        $total_p = $config_p['interes'] + $config_p['comision'];
                                ?>
                                        <option value="<?php echo htmlspecialchars($prestamista['prestamista']); ?>" 
                                            <?php echo $prestamista_seleccionado == $prestamista['prestamista'] ? 'selected' : ''; ?>
                                            data-interes="<?php echo $config_p['interes']; ?>"
                                            data-comision="<?php echo $config_p['comision']; ?>"
                                            data-total="<?php echo $total_p; ?>">
                                            <?php echo htmlspecialchars($prestamista['prestamista']); ?> 
                                            (<?php echo number_format($config_p['interes'], 1); ?>% + <?php echo number_format($config_p['comision'], 1); ?>% = <?php echo number_format($total_p, 1); ?>%)
                                        </option>
                                <?php endwhile; 
                                }
                                ?>
                            </select>
                        </div>
                        
                        <button type="submit">Generar reporte</button>
                    </div>
                </div>
            </div>
        </form>

        <?php if (!empty($prestamista_seleccionado)): ?>
        <div class="resultados">
            <h2 style="font-size:1.1rem;font-weight:600;margin-bottom:6px;">
                Resultados para: <span style="color:var(--accent);"><?php echo htmlspecialchars($prestamista_seleccionado); ?></span>
                <?php 
                $config_actual = isset($config_prestamistas[$prestamista_seleccionado]) 
                    ? $config_prestamistas[$prestamista_seleccionado] 
                    : ['interes' => 10.00, 'comision' => 0.00];
                ?>
            </h2>
            
            <div class="info-meses">
                <strong>📅 REGLA DE FECHA DE CORTE APLICADA:</strong><br>
                • <span class="badge-tipo badge-viejo">Préstamos VIEJOS</span> (antes del 29-10-2025): <strong>10% interés todo para <?php echo htmlspecialchars($prestamista_seleccionado); ?>, 0% comisión para ti</strong><br>
                • <span class="badge-tipo badge-nuevo">Préstamos NUEVOS</span> (después del 29-10-2025): <strong><?php echo number_format($config_actual['interes'], 1); ?>% para <?php echo htmlspecialchars($prestamista_seleccionado); ?> + <?php echo number_format($config_actual['comision'], 1); ?>% comisión para ti</strong>
            </div>

            <!-- CUADRO 1 -->
            <div class="subtitulo-cuadro">
                Cuadro 1: Préstamos <?php echo $modo_pagados ? 'pagados' : 'de conductores y otros deudores seleccionados'; ?>
            </div>

            <?php if (empty($prestamos_por_deudor)): ?>
                <div style="background-color: rgba(239,68,68,0.12); border:1px solid rgba(239,68,68,0.4); padding: 12px; border-radius: 10px; margin: 15px 0;">
                    No se encontraron préstamos.
                </div>
            <?php else: ?>
            <table id="tablaReporte">
                <thead>
                    <tr>
                        <th>Deudor</th>
                        <th>Préstamos</th>
                        <th>Capital</th>
                        <th>Interés Prestamista</th>
                        <th>Tu Comisión</th>
                        <th>Total a Pagar</th>
                    </tr>
                </thead>
                <tbody id="cuerpoReporte">
                    <?php 
                    $total_capital_general = 0;
                    $total_general = 0;
                    $total_interes_prestamista_general = 0;
                    $total_comision_personal_general = 0;
                    ?>
                    <?php foreach ($prestamos_por_deudor as $deudor => $datos): ?>
                    <?php 
                        $total_capital_general += $datos['total_capital'];
                        $total_general += $datos['total_general'];
                        $total_interes_prestamista_general += $datos['total_interes_prestamista'];
                        $total_comision_personal_general += $datos['total_comision_personal'];
                    ?>
                    <tr class="header-deudor" id="fila-<?php echo md5($deudor); ?>">
                        <td>
                            <span class="detalle-toggle" onclick="toggleDetalle('<?php echo md5($deudor); ?>')">
                                <?php echo htmlspecialchars($deudor); ?>
                            </span>
                        </td>
                        <td><?php echo $datos['cantidad_prestamos']; ?></td>
                        <td class="moneda capital-deudor">$ <?php echo number_format($datos['total_capital'], 0, ',', '.'); ?></td>
                        <td class="moneda interes-prestamista-deudor">$ <?php echo number_format($datos['total_interes_prestamista'], 0, ',', '.'); ?></td>
                        <td class="moneda comision-deudor">$ <?php echo number_format($datos['total_comision_personal'], 0, ',', '.'); ?></td>
                        <td class="moneda total-deudor">$ <?php echo number_format($datos['total_general'], 0, ',', '.'); ?></td>
                    </tr>
                    
                    <!-- Detalle de cada préstamo -->
                    <tr class="detalle-prestamo" id="detalle-<?php echo md5($deudor); ?>" style="display:none;">
                        <td colspan="6">
                            <table style="width: 100%; background-color: #020617;">
                                <thead>
                                    <tr>
                                        <th>Incluir</th>
                                        <th>Abono</th>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Capital</th>
                                        <?php if (!$modo_pagados): ?>
                                        <th>Meses</th>
                                        <?php endif; ?>
                                        <th>Int. Prestamista</th>
                                        <th>Tu Comisión</th>
                                        <th>Total a Pagar HOY</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($datos['prestamos_detalle'] as $index => $detalle): 
                                        $es_excluido = $detalle['excluido'] ?? false;
                                    ?>
                                    <tr class="fila-prestamo <?php echo $detalle['tipo'] == 'viejo' ? 'prestamo-viejo' : 'prestamo-nuevo'; ?> <?php echo $es_excluido ? 'excluido' : ''; ?>" 
                                        data-deudor="<?php echo md5($deudor); ?>" data-id="<?php echo $detalle['id']; ?>"
                                        data-monto="<?php echo $detalle['monto']; ?>"
                                        data-interes="<?php echo $detalle['interes_prestamista']; ?>"
                                        data-comision="<?php echo $detalle['comision_personal']; ?>">
                                        <td class="acciones">
                                            <input type="checkbox" class="checkbox-excluir" <?php echo !$es_excluido ? 'checked' : ''; ?> 
                                                   onchange="togglePrestamo(this, <?php echo $detalle['id']; ?>)">
                                        </td>
                                        <td>
                                            <input type="number" class="abono-input" placeholder="Abono" value="0"
                                                   onchange="calcularAbono(this)" min="0" step="1000">
                                        </td>
                                        <td><?php echo $detalle['fecha']; ?></td>
                                        <td>
                                            <span class="badge-tipo <?php echo $detalle['tipo'] == 'viejo' ? 'badge-viejo' : 'badge-nuevo'; ?>">
                                                <?php echo $detalle['tipo'] == 'viejo' ? 'Viejo' : 'Nuevo'; ?>
                                            </span>
                                        </td>
                                        <td class="moneda">
                                            $ <?php echo number_format($detalle['monto'], 0, ',', '.'); ?>
                                            <span class="capital-pendiente" id="pendiente-<?php echo $detalle['id']; ?>">
                                                Faltan: $<?php echo number_format($detalle['monto'], 0, ',', '.'); ?>
                                            </span>
                                        </td>
                                        <?php if (!$modo_pagados): ?>
                                        <td class="acciones">
                                            <input type="number" class="meses-input" value="<?php echo $detalle['meses']; ?>" 
                                                   min="1" max="36" onchange="recalcularPrestamo(this)"
                                                   data-monto="<?php echo $detalle['monto']; ?>"
                                                   data-tipo="<?php echo $detalle['tipo']; ?>">
                                        </td>
                                        <?php endif; ?>
                                        <td class="moneda interes-prestamista-prestamo">$ <?php echo number_format($detalle['interes_prestamista'], 0, ',', '.'); ?></td>
                                        <td class="moneda comision-prestamo">$ <?php echo number_format($detalle['comision_personal'], 0, ',', '.'); ?></td>
                                        <td class="moneda total-prestamo total-hoy">$ <?php echo number_format($detalle['total'], 0, ',', '.'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Totales generales CUADRO 1 -->
                    <tr class="totales">
                        <td colspan="2"><strong>TOTAL GENERAL</strong></td>
                        <td class="moneda" id="total-capital-general">$ <?php echo number_format($total_capital_general, 0, ',', '.'); ?></td>
                        <td class="moneda" id="total-interes-prestamista-general">$ <?php echo number_format($total_interes_prestamista_general, 0, ',', '.'); ?></td>
                        <td class="moneda" id="total-comision-general">$ <?php echo number_format($total_comision_personal_general, 0, ',', '.'); ?></td>
                        <td class="moneda" id="total-general">$ <?php echo number_format($total_general, 0, ',', '.'); ?></td>
                    </tr>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- CUADRO 2: OTROS DEUDORES -->
            <?php if (!$modo_pagados && !empty($otros_prestamos_por_deudor)): ?>
            <div class="subtitulo-cuadro">
                Cuadro 2: Otros deudores
            </div>
            <div class="cuadro-otros">
                <table>
                    <thead>
                        <tr>
                            <th>Incluir</th>
                            <th>Deudor</th>
                            <th>Préstamos</th>
                            <th>Capital</th>
                            <th>Interés Prestamista</th>
                            <th>Tu Comisión</th>
                            <th>Total a pagar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($otros_prestamos_por_deudor as $deudor => $datos): ?>
                        <tr>
                            <td class="acciones">
                                <input type="checkbox"
                                       class="checkbox-otro-deudor"
                                       data-deudor="<?php echo htmlspecialchars($deudor); ?>"
                                       onchange="toggleOtroDeudor(this)">
                            </td>
                            <td><?php echo htmlspecialchars($deudor); ?></td>
                            <td><?php echo $datos['cantidad_prestamos']; ?></td>
                            <td class="moneda">$ <?php echo number_format($datos['total_capital'], 0, ',', '.'); ?></td>
                            <td class="moneda">$ <?php echo number_format($datos['total_interes_prestamista'], 0, ',', '.'); ?></td>
                            <td class="moneda">$ <?php echo number_format($datos['total_comision_personal'], 0, ',', '.'); ?></td>
                            <td class="moneda">$ <?php echo number_format($datos['total_general'], 0, ',', '.'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

        </div>
        <?php endif; ?>
    </div>

    <script>
        let deudoresSeleccionados = <?php echo json_encode($deudores_seleccionados); ?>;
        let prestamosExcluidos = <?php echo json_encode($prestamos_excluidos); ?>;
        let modoPagados = <?php echo $modo_pagados ? 'true' : 'false'; ?>;
        let abonos = {};

        document.addEventListener('DOMContentLoaded', function() {
            actualizarListaDeudores();
            actualizarContador();
            actualizarCampoExcluidos();
            inicializarAbonos();
            
            const empresaSelect = document.getElementById('empresa');
            if (empresaSelect) {
                empresaSelect.addEventListener('change', function() {
                    if (this.value === '') {
                        this.style.borderColor = '#ef4444';
                    }
                });
            }
        });

        function toggleModoPagados() {
            const toggle = document.getElementById('toggleModo');
            document.getElementById('inputModoPagados').value = toggle.checked ? '1' : '0';
            document.getElementById('formPrincipal').submit();
        }

        function toggleDeudor(element) {
            const valor = element.getAttribute('data-value');
            const index = deudoresSeleccionados.indexOf(valor);
            
            if (index === -1) {
                deudoresSeleccionados.push(valor);
                element.classList.add('selected');
            } else {
                deudoresSeleccionados.splice(index, 1);
                element.classList.remove('selected');
            }
            
            document.getElementById('deudoresSeleccionados').value = deudoresSeleccionados.join(',');
            actualizarContador();
        }

        function actualizarListaDeudores() {
            document.querySelectorAll('.deudor-item').forEach(item => {
                const valor = item.getAttribute('data-value');
                if (deudoresSeleccionados.includes(valor)) {
                    item.classList.add('selected');
                }
                item.addEventListener('click', function() {
                    toggleDeudor(this);
                });
            });
        }

        function actualizarContador() {
            const items = document.querySelectorAll('.deudor-item');
            document.getElementById('contadorDeudores').textContent = 
                `Seleccionados: ${deudoresSeleccionados.length} de ${items.length} conductores`;
        }

        function actualizarCampoExcluidos() {
            document.getElementById('prestamosExcluidos').value = prestamosExcluidos.join(',');
        }

        function seleccionarTodos() {
            deudoresSeleccionados = [];
            document.querySelectorAll('.deudor-item').forEach(item => {
                const valor = item.getAttribute('data-value');
                deudoresSeleccionados.push(valor);
                item.classList.add('selected');
            });
            document.getElementById('deudoresSeleccionados').value = deudoresSeleccionados.join(',');
            actualizarContador();
        }

        function deseleccionarTodos() {
            deudoresSeleccionados = [];
            document.querySelectorAll('.deudor-item').forEach(item => {
                item.classList.remove('selected');
            });
            document.getElementById('deudoresSeleccionados').value = '';
            actualizarContador();
        }

        document.getElementById('buscadorDeudores')?.addEventListener('input', function(e) {
            const filtro = e.target.value.toLowerCase();
            document.querySelectorAll('.deudor-item').forEach(item => {
                const texto = item.textContent.toLowerCase();
                item.style.display = texto.includes(filtro) ? '' : 'none';
            });
        });
        
        function toggleDetalle(id) {
            const detalle = document.getElementById('detalle-' + id);
            if (detalle) {
                detalle.style.display = detalle.style.display === 'none' ? 'table-row' : 'none';
            }
        }
        
        function togglePrestamo(checkbox, prestamoId) {
            const fila = checkbox.closest('.fila-prestamo');
            if (!fila) return;

            if (!checkbox.checked) {
                fila.classList.add('excluido');
                if (!prestamosExcluidos.includes(prestamoId.toString())) {
                    prestamosExcluidos.push(prestamoId.toString());
                }
            } else {
                fila.classList.remove('excluido');
                const index = prestamosExcluidos.indexOf(prestamoId.toString());
                if (index !== -1) {
                    prestamosExcluidos.splice(index, 1);
                }
            }
            
            actualizarCampoExcluidos();
            const deudorId = fila.dataset.deudor;
            actualizarTotalesDeudor(deudorId);
        }
        
        function recalcularPrestamo(input) {
            if (modoPagados) {
                alert('⚠ En modo pagados no se pueden modificar los meses.');
                return;
            }
            
            const fila = input.closest('.fila-prestamo');
            const monto = parseFloat(fila.getAttribute('data-monto'));
            const meses = parseInt(input.value);
            const tipo = input.getAttribute('data-tipo');
            
            if (tipo === 'viejo') {
                const interesPrestamistaMonto = monto * (10 / 100) * meses;
                const comisionPersonalMonto = 0;
                const total = monto + interesPrestamistaMonto;
                
                fila.querySelector('.interes-prestamista-prestamo').textContent = '$ ' + formatNumber(interesPrestamistaMonto);
                fila.querySelector('.comision-prestamo').textContent = '$ ' + formatNumber(comisionPersonalMonto);
                fila.querySelector('.total-prestamo').textContent = '$ ' + formatNumber(total);
                
                fila.setAttribute('data-interes', interesPrestamistaMonto);
                fila.setAttribute('data-comision', comisionPersonalMonto);
            } else {
                const prestamistaSelect = document.getElementById('prestamista');
                const selectedOption = prestamistaSelect.options[prestamistaSelect.selectedIndex];
                const interesPrestamista = parseFloat(selectedOption.getAttribute('data-interes') || 0);
                const comisionPersonal = parseFloat(selectedOption.getAttribute('data-comision') || 0);
                
                const interesPrestamistaMonto = monto * (interesPrestamista / 100) * meses;
                const comisionPersonalMonto = monto * (comisionPersonal / 100) * meses;
                const total = monto + interesPrestamistaMonto;
                
                fila.querySelector('.interes-prestamista-prestamo').textContent = '$ ' + formatNumber(interesPrestamistaMonto);
                fila.querySelector('.comision-prestamo').textContent = '$ ' + formatNumber(comisionPersonalMonto);
                fila.querySelector('.total-prestamo').textContent = '$ ' + formatNumber(total);
                
                fila.setAttribute('data-interes', interesPrestamistaMonto);
                fila.setAttribute('data-comision', comisionPersonalMonto);
            }
            
            const checkbox = fila.querySelector('.checkbox-excluir');
            if (checkbox && checkbox.checked) {
                const deudorId = fila.dataset.deudor;
                actualizarTotalesDeudor(deudorId);
            }
            
            const abonoInput = fila.querySelector('.abono-input');
            if (abonoInput && parseFloat(abonoInput.value) > 0) {
                calcularAbono(abonoInput);
            }
        }
        
        // FUNCIÓN DE ABONO CORREGIDA - AHORA USA SOLO EL SOBRANTE PARA TOTAL GENERAL
        function calcularAbono(input) {
            const fila = input.closest('.fila-prestamo');
            const prestamoId = fila.getAttribute('data-id');
            const monto = parseFloat(fila.getAttribute('data-monto'));
            const interes = parseFloat(fila.getAttribute('data-interes'));
            const comision = parseFloat(fila.getAttribute('data-comision'));
            const abono = parseFloat(input.value) || 0;
            
            abonos[prestamoId] = abono;
            
            const totalIntereses = interes + comision;
            const sobrante = abono - totalIntereses; // ESTE ES EL VALOR CLAVE
            
            if (abono > 0) {
                if (sobrante >= 0) {
                    // ✅ LÓGICA CORRECTA: El sobrante es lo que va al TOTAL GENERAL
                    const faltaCapital = monto - sobrante;
                    
                    // Actualizar FALTANTE (amarillo)
                    const spanPendiente = document.getElementById('pendiente-' + prestamoId);
                    if (spanPendiente) {
                        spanPendiente.textContent = 'Faltan: $' + formatNumber(faltaCapital);
                    }
                    
                    // ✅ TOTAL GENERAL = SOLO EL SOBRANTE
                    const totalCell = fila.querySelector('.total-prestamo');
                    if (totalCell) {
                        totalCell.textContent = '$ ' + formatNumber(sobrante);
                        totalCell.classList.add('total-hoy');
                        
                        // Añadir indicador visual de que es el sobrante
                        if (!fila.querySelector('.sobrante-info')) {
                            const infoSpan = document.createElement('span');
                            infoSpan.className = 'sobrante-info';
                            infoSpan.textContent = 'Sobrante: $' + formatNumber(sobrante);
                            totalCell.parentNode.appendChild(infoSpan);
                        }
                    }
                } else {
                    const spanPendiente = document.getElementById('pendiente-' + prestamoId);
                    if (spanPendiente) {
                        spanPendiente.textContent = '⚠ Abono insuficiente (faltan $' + formatNumber(Math.abs(sobrante)) + ' para intereses)';
                    }
                }
            } else {
                // Si no hay abono, restaurar valores originales
                const spanPendiente = document.getElementById('pendiente-' + prestamoId);
                if (spanPendiente) {
                    spanPendiente.textContent = 'Faltan: $' + formatNumber(monto);
                }
                
                const totalCell = fila.querySelector('.total-prestamo');
                if (totalCell) {
                    const totalOriginal = monto + interes;
                    totalCell.textContent = '$ ' + formatNumber(totalOriginal);
                }
                
                // Remover indicador de sobrante si existe
                const infoSpan = fila.querySelector('.sobrante-info');
                if (infoSpan) {
                    infoSpan.remove();
                }
            }
            
            // Actualizar totales del deudor y generales
            const deudorId = fila.dataset.deudor;
            actualizarTotalesDeudor(deudorId);
        }
        
        function inicializarAbonos() {
            document.querySelectorAll('.fila-prestamo').forEach(fila => {
                const prestamoId = fila.getAttribute('data-id');
                const monto = parseFloat(fila.getAttribute('data-monto'));
                const spanPendiente = document.getElementById('pendiente-' + prestamoId);
                
                if (spanPendiente) {
                    spanPendiente.textContent = 'Faltan: $' + formatNumber(monto);
                }
                
                if (!abonos[prestamoId]) {
                    abonos[prestamoId] = 0;
                }
            });
        }
        
        // FUNCIÓN ACTUALIZADA: ahora usa el sobrante para totales
        function actualizarTotalesDeudor(deudorId) {
            const filasPrestamos = document.querySelectorAll('.fila-prestamo[data-deudor="' + deudorId + '"]');
            let totalCapital = 0;
            let totalGeneral = 0;
            let totalInteresPrestamista = 0;
            let totalComisionPersonal = 0;
            let prestamosIncluidos = 0;
            
            filasPrestamos.forEach(fila => {
                const checkbox = fila.querySelector('.checkbox-excluir');
                if (checkbox && checkbox.checked && !fila.classList.contains('excluido')) {
                    // Obtener valores actualizados
                    const totalCell = fila.querySelector('.total-prestamo');
                    const interesCell = fila.querySelector('.interes-prestamista-prestamo');
                    const comisionCell = fila.querySelector('.comision-prestamo');
                    
                    // Extraer números de las celdas
                    const total = parseFloat(totalCell.textContent.replace(/[^\d]/g, ''));
                    const interes = parseFloat(interesCell.textContent.replace(/[^\d]/g, ''));
                    const comision = parseFloat(comisionCell.textContent.replace(/[^\d]/g, ''));
                    
                    // Capital actual = monto original - lo que se ha devuelto (sobrante)
                    const spanPendiente = fila.querySelector('.capital-pendiente');
                    let capitalActual = 0;
                    if (spanPendiente) {
                        const pendienteText = spanPendiente.textContent;
                        const match = pendienteText.match(/\d+/g);
                        if (match) {
                            capitalActual = parseInt(match.join(''));
                        }
                    }
                    
                    totalCapital += capitalActual;
                    totalGeneral += total; // ESTO AHORA ES EL SOBRANTE
                    totalInteresPrestamista += interes;
                    totalComisionPersonal += comision;
                    prestamosIncluidos++;
                }
            });
            
            const filaDeudor = document.getElementById('fila-' + deudorId);
            if (!filaDeudor) return;

            filaDeudor.querySelector('.capital-deudor').textContent = '$ ' + formatNumber(totalCapital);
            filaDeudor.querySelector('.total-deudor').textContent = '$ ' + formatNumber(totalGeneral);
            filaDeudor.querySelector('.interes-prestamista-deudor').textContent = '$ ' + formatNumber(totalInteresPrestamista);
            filaDeudor.querySelector('.comision-deudor').textContent = '$ ' + formatNumber(totalComisionPersonal);
            filaDeudor.querySelector('td:nth-child(2)').textContent = prestamosIncluidos;
            
            actualizarTotalesGenerales();
        }
        
        // FUNCIÓN ACTUALIZADA: ahora suma los sobrantes
        function actualizarTotalesGenerales() {
            let totalCapital = 0;
            let totalGeneral = 0;
            let totalInteresPrestamista = 0;
            let totalComisionPersonal = 0;
            
            document.querySelectorAll('.header-deudor').forEach(fila => {
                totalCapital += parseFloat(fila.querySelector('.capital-deudor').textContent.replace(/[^\d]/g, ''));
                totalGeneral += parseFloat(fila.querySelector('.total-deudor').textContent.replace(/[^\d]/g, ''));
                totalInteresPrestamista += parseFloat(fila.querySelector('.interes-prestamista-deudor').textContent.replace(/[^\d]/g, ''));
                totalComisionPersonal += parseFloat(fila.querySelector('.comision-deudor').textContent.replace(/[^\d]/g, ''));
            });
            
            document.getElementById('total-capital-general').textContent = '$ ' + formatNumber(totalCapital);
            document.getElementById('total-general').textContent = '$ ' + formatNumber(totalGeneral);
            document.getElementById('total-interes-prestamista-general').textContent = '$ ' + formatNumber(totalInteresPrestamista);
            document.getElementById('total-comision-general').textContent = '$ ' + formatNumber(totalComisionPersonal);
        }

        function toggleOtroDeudor(checkbox) {
            const deudor = checkbox.getAttribute('data-deudor');
            const index = deudoresSeleccionados.indexOf(deudor);

            if (checkbox.checked) {
                if (index === -1) {
                    deudoresSeleccionados.push(deudor);
                }
            } else {
                if (index !== -1) {
                    deudoresSeleccionados.splice(index, 1);
                }
            }

            document.getElementById('deudoresSeleccionados').value = deudoresSeleccionados.join(',');
            document.getElementById('formPrincipal').submit();
        }
        
        function formatNumber(num) {
            return Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
    </script>
</body>
</html>
<?php
$conn->close();
?>