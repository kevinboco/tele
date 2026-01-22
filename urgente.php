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
    
    // Validar que la suma sea 13% para préstamos nuevos
    $suma_porcentajes = $interes_prestamista + $comision_personal;
    
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
        
        if (abs($suma_porcentajes - 13.00) > 0.01) {
            $mensaje_config .= "<br><small>⚠ Nota: La suma de porcentajes es $suma_porcentajes% (debería ser 13% para préstamos nuevos)</small>";
        }
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
// 5. FUNCIÓN PARA CALCULAR MESES (SIN CAMBIOS)
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
$estado_seleccionado = $_POST['estado_prestamos'] ?? 'pendientes'; // NUEVO: estado de préstamos
$fecha_pago_desde = $_POST['fecha_pago_desde'] ?? ''; // NUEVO: fecha pago desde
$fecha_pago_hasta = $_POST['fecha_pago_hasta'] ?? ''; // NUEVO: fecha pago hasta

// Arreglos para los reportes
$prestamos_por_deudor = [];
$otros_prestamos_por_deudor = [];

// ARRAY para almacenar IDs excluidos (para que afecte la tabla de configuración)
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

// Obtener empresas desde VIAJES
$sql_empresas = "SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa != '' ORDER BY empresa";
$result_empresas = $conn->query($sql_empresas);

// Obtener lista de prestamistas para la tabla de configuración
$sql_prestamistas_lista = "SELECT DISTINCT prestamista FROM prestamos WHERE prestamista != '' ORDER BY prestamista";
$result_prestamistas_lista = $conn->query($sql_prestamistas_lista);

// FUNCIÓN PARA CALCULAR GANANCIA DE UN PRESTAMISTA (MODIFICADA para incluir empresa)
function calcularGananciaPrestamista($conn, $prestamista_nombre, $config_prestamistas, $prestamos_excluidos = [], $empresa_filtrada = '') {
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
    
    $sql = "SELECT 
                monto,
                fecha,
                CASE 
                    WHEN fecha < ? THEN 'viejo'
                    ELSE 'nuevo'
                END as tipo
            FROM prestamos 
            WHERE prestamista = ? 
            AND pagado = 0
            $condicion_empresa
            $condicion_excluidos";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($empresa_filtrada)) {
        $stmt->bind_param("sss", $FECHA_CORTE, $prestamista_nombre, $empresa_filtrada);
    } else {
        $stmt->bind_param("ss", $FECHA_CORTE, $prestamista_nombre);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $total_viejos = 0;
    $total_nuevos = 0;
    $tu_ganancia_total = 0;
    
    $config = isset($config_prestamistas[$prestamista_nombre]) 
        ? $config_prestamistas[$prestamista_nombre] 
        : ['interes' => 10.00, 'comision' => 0.00];
    
    while ($row = $result->fetch_assoc()) {
        $meses = calcularMesesAutomaticos($row['fecha']);
        
        if ($row['tipo'] == 'viejo') {
            $total_viejos += $row['monto'];
            // Viejos: 0% comisión para ti
        } else {
            $total_nuevos += $row['monto'];
            // Nuevos: tu porcentaje configurado
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

// ============================================
// NUEVO: CALCULAR SUMA TOTAL DE TODAS LAS COMISIONES
// ============================================
$total_todas_tus_comisiones = 0;
$ganancias_por_prestamista = []; // Para almacenar datos de cada prestamista

if ($result_prestamistas_lista && $result_prestamistas_lista->num_rows > 0) {
    $result_prestamistas_lista->data_seek(0);
    while ($prest = $result_prestamistas_lista->fetch_assoc()) {
        $prestamista_nombre = $prest['prestamista'];
        
        // Calcular ganancia para este prestamista (SIN filtro de empresa para la tabla de configuración general)
        $datos_ganancia = calcularGananciaPrestamista($conn, $prestamista_nombre, $config_prestamistas, $prestamos_excluidos, '');
        
        // Almacenar datos para mostrar en tabla
        $ganancias_por_prestamista[$prestamista_nombre] = $datos_ganancia;
        
        // Sumar al total general
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
    $estado_seleccionado = $_POST['estado_prestamos'] ?? 'pendientes'; // NUEVO
    $fecha_pago_desde = $_POST['fecha_pago_desde'] ?? ''; // NUEVO
    $fecha_pago_hasta = $_POST['fecha_pago_hasta'] ?? ''; // NUEVO
    $prestamos_excluidos = isset($_POST['prestamos_excluidos']) ? explode(',', $_POST['prestamos_excluidos']) : [];

    // Prestamistas únicos (según estado seleccionado)
    $sql_prestamistas = "SELECT DISTINCT prestamista FROM prestamos WHERE prestamista != '' ";
    
    // Añadir condición según estado seleccionado
    if ($estado_seleccionado == 'pendientes') {
        $sql_prestamistas .= "AND pagado = 0 ";
    } elseif ($estado_seleccionado == 'pagados') {
        $sql_prestamistas .= "AND pagado = 1 ";
    }
    // Si es 'todos', no se añade condición
    
    $sql_prestamistas .= "ORDER BY prestamista";
    $result_prestamistas = $conn->query($sql_prestamistas);

    // ==========================
    // 1) CONDUCTORES DESDE VIAJES
    // ==========================
    $conductores_filtrados = [];
    if (!empty($fecha_desde) && !empty($fecha_hasta)) {
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

    // ==========================
    // 2) PRÉSTAMOS DE DEUDORES SELECCIONADOS (CUADRO 1)
    // ==========================
    if (!empty($deudores_seleccionados) && !empty($prestamista_seleccionado) && !empty($empresa_seleccionada)) {
        $placeholders = str_repeat('?,', count($deudores_seleccionados) - 1) . '?';

        $sql = "SELECT 
                    id,
                    deudor,
                    prestamista,
                    monto,
                    fecha,
                    pagado,
                    pagado_at  -- NUEVO: incluir fecha de pago
                FROM prestamos 
                WHERE deudor IN ($placeholders) 
                  AND prestamista = ?
                  AND empresa = ?";
        
        // Agregar condición según estado seleccionado
        switch ($estado_seleccionado) {
            case 'pendientes':
                $sql .= " AND pagado = 0";
                break;
            case 'pagados':
                $sql .= " AND pagado = 1";
                // Agregar filtro por fecha de pago si se especificó
                if (!empty($fecha_pago_desde) && !empty($fecha_pago_hasta)) {
                    $sql .= " AND pagado_at BETWEEN ? AND ?";
                } elseif (!empty($fecha_pago_desde)) {
                    $sql .= " AND pagado_at >= ?";
                } elseif (!empty($fecha_pago_hasta)) {
                    $sql .= " AND pagado_at <= ?";
                }
                break;
            // 'todos' no agrega condición
        }
        
        $sql .= " ORDER BY deudor, fecha";

        $stmt = $conn->prepare($sql);
        
        // Preparar parámetros según si hay filtro de fecha de pago
        $params = array_merge($deudores_seleccionados, [$prestamista_seleccionado, $empresa_seleccionada]);
        
        if ($estado_seleccionado == 'pagados') {
            if (!empty($fecha_pago_desde) && !empty($fecha_pago_hasta)) {
                $params[] = $fecha_pago_desde . ' 00:00:00';
                $params[] = $fecha_pago_hasta . ' 23:59:59';
                $types = str_repeat('s', count($deudores_seleccionados)) . 'ssss';
            } elseif (!empty($fecha_pago_desde)) {
                $params[] = $fecha_pago_desde . ' 00:00:00';
                $types = str_repeat('s', count($deudores_seleccionados)) . 'sss';
            } elseif (!empty($fecha_pago_hasta)) {
                $params[] = $fecha_pago_hasta . ' 23:59:59';
                $types = str_repeat('s', count($deudores_seleccionados)) . 'sss';
            } else {
                $types = str_repeat('s', count($deudores_seleccionados)) . 'ss';
            }
        } else {
            $types = str_repeat('s', count($deudores_seleccionados)) . 'ss';
        }
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result_detalle = $stmt->get_result();

        // Obtener configuración para este prestamista (SOLO PARA PRÉSTAMOS NUEVOS)
        $config_actual = isset($config_prestamistas[$prestamista_seleccionado]) 
            ? $config_prestamistas[$prestamista_seleccionado] 
            : ['interes' => 10.00, 'comision' => 0.00];

        while ($fila = $result_detalle->fetch_assoc()) {
            $deudor = $fila['deudor'];
            $meses = calcularMesesAutomaticos($fila['fecha']);
            $fecha_prestamo_dt = new DateTime($fila['fecha']);
            $es_excluido = in_array($fila['id'], $prestamos_excluidos);
            $esta_pagado = $fila['pagado'] == 1;
            $fecha_pago = $fila['pagado_at'] ?? null; // NUEVO
            
            // DETERMINAR SI ES PRÉSTAMO VIEJO O NUEVO
            $es_prestamo_viejo = ($fecha_prestamo_dt < $FECHA_CORTE);
            
            if ($es_prestamo_viejo) {
                // PRÉSTAMOS ANTES DEL 29-10-2025: 10% todo para prestamista, 0% para ti
                $interes_prestamista_monto = $fila['monto'] * (10 / 100) * $meses;
                $comision_personal_monto = 0;
                $tasa_interes = 10;
                $total_prestamo = $fila['monto'] + $interes_prestamista_monto;
            } else {
                // PRÉSTAMOS DESPUÉS DEL 29-10-2025: Usar configuración personalizada
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
                    'cantidad_pagados' => 0, // NUEVO
                    'prestamos_detalle' => []
                ];
            }

            // Solo sumar si NO está excluido
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
            
            if ($esta_pagado) {
                $prestamos_por_deudor[$deudor]['cantidad_pagados']++;
            }

            $prestamos_por_deudor[$deudor]['prestamos_detalle'][] = [
                'id' => $fila['id'],
                'monto' => $fila['monto'],
                'fecha' => $fila['fecha'],
                'pagado' => $esta_pagado,
                'pagado_at' => $fecha_pago, // NUEVO
                'meses' => $meses,
                'tipo' => $es_prestamo_viejo ? 'viejo' : 'nuevo',
                'tasa_interes' => $tasa_interes,
                'interes_prestamista' => $interes_prestamista_monto,
                'comision_personal' => $comision_personal_monto,
                'total' => $total_prestamo,
                'incluido' => !$es_excluido,
                'excluido' => $es_excluido
            ];
        }
    }

    // ==========================
    // 3) OTROS DEUDORES (NO SELECCIONADOS) - CUADRO 2
    // ==========================
    if (!empty($prestamista_seleccionado) && !empty($empresa_seleccionada)) {
        // Preparar condición para excluidos
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
                        fecha,
                        pagado,
                        pagado_at  -- NUEVO
                      FROM prestamos
                      WHERE prestamista = ?
                        AND empresa = ?
                        AND deudor IS NOT NULL
                        AND deudor != ''
                        $condicion_excluidos";
        
        // Agregar condición según estado seleccionado
        switch ($estado_seleccionado) {
            case 'pendientes':
                $sql_otros .= " AND pagado = 0";
                break;
            case 'pagados':
                $sql_otros .= " AND pagado = 1";
                // Agregar filtro por fecha de pago si se especificó
                if (!empty($fecha_pago_desde) && !empty($fecha_pago_hasta)) {
                    $sql_otros .= " AND pagado_at BETWEEN ? AND ?";
                } elseif (!empty($fecha_pago_desde)) {
                    $sql_otros .= " AND pagado_at >= ?";
                } elseif (!empty($fecha_pago_hasta)) {
                    $sql_otros .= " AND pagado_at <= ?";
                }
                break;
            // 'todos' no agrega condición
        }
        
        $sql_otros .= " ORDER BY deudor, fecha";
        
        $stmt_otros = $conn->prepare($sql_otros);
        
        // Preparar parámetros según si hay filtro de fecha de pago
        if ($estado_seleccionado == 'pagados') {
            if (!empty($fecha_pago_desde) && !empty($fecha_pago_hasta)) {
                $stmt_otros->bind_param("ssss", $prestamista_seleccionado, $empresa_seleccionada, 
                                       $fecha_pago_desde . ' 00:00:00', $fecha_pago_hasta . ' 23:59:59');
            } elseif (!empty($fecha_pago_desde)) {
                $stmt_otros->bind_param("sss", $prestamista_seleccionado, $empresa_seleccionada, 
                                       $fecha_pago_desde . ' 00:00:00');
            } elseif (!empty($fecha_pago_hasta)) {
                $stmt_otros->bind_param("sss", $prestamista_seleccionado, $empresa_seleccionada, 
                                       $fecha_pago_hasta . ' 23:59:59');
            } else {
                $stmt_otros->bind_param("ss", $prestamista_seleccionado, $empresa_seleccionada);
            }
        } else {
            $stmt_otros->bind_param("ss", $prestamista_seleccionado, $empresa_seleccionada);
        }
        
        $stmt_otros->execute();
        $result_otros = $stmt_otros->get_result();

        // Obtener configuración para este prestamista
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
            $esta_pagado = $fila['pagado'] == 1; // NUEVO

            if ($es_prestamo_viejo) {
                // PRÉSTAMOS VIEJOS: 10% todo para prestamista
                $interes_prestamista_monto = $fila['monto'] * (10 / 100) * $meses;
                $comision_personal_monto = 0;
                $total_prestamo = $fila['monto'] + $interes_prestamista_monto;
            } else {
                // PRÉSTAMOS NUEVOS: Usar configuración
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
                    'cantidad_nuevos' => 0,
                    'cantidad_pagados' => 0 // NUEVO
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
            
            if ($esta_pagado) {
                $otros_prestamos_por_deudor[$deudor]['cantidad_pagados']++;
            }
        }
    }

} else {
    // GET: solo prestamistas
    $sql_prestamistas = "SELECT DISTINCT prestamista FROM prestamos WHERE prestamista != '' AND pagado = 0 ORDER BY prestamista";
    $result_prestamistas = $conn->query($sql_prestamistas);
    $conductores_filtrados = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Préstamos - Gestión Completa</title>
    <!-- Fuente bonita -->
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
            box-shadow:
                0 20px 45px rgba(15,23,42,0.9),
                0 0 0 1px rgba(15,23,42,0.8);
            backdrop-filter:blur(18px);
        }

        h1{
            margin-top:0;
            font-size:1.9rem;
            font-weight:700;
            letter-spacing:0.03em;
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
            letter-spacing:0.12em;
            color:var(--accent);
        }

        /* NUEVO: Estilo para TOTAL GENERAL DE COMISIONES */
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
            letter-spacing: 1px;
        }

        /* NUEVO: Estilo para tabla de configuración */
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
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--accent);
        }

        .config-section h3::before {
            content: "⚙";
            font-size: 1.2rem;
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
            letter-spacing: 0.05em;
            color: var(--text-soft);
        }

        .config-table td {
            padding: 8px 12px;
            border-bottom: 1px solid rgba(31,41,55,0.9);
        }

        .config-table tr:last-child td {
            border-bottom: none;
        }

        .config-table tr:hover {
            background: rgba(15,23,42,0.9);
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

        .config-input:focus {
            border-color: var(--accent);
            outline: none;
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
            transition: all 0.2s;
        }

        .btn-guardar:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.4);
        }

        .mensaje-config {
            padding: 10px 14px;
            border-radius: 10px;
            margin: 10px 0;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .mensaje-success {
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.4);
            color: #22c55e;
        }

        .mensaje-error {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #ef4444;
        }

        .ganancia-cell {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 8px;
            text-align: center;
            font-weight: 700;
            color: #22c55e;
        }

        .ganancia-cell small {
            color: rgba(34, 197, 94, 0.8);
            font-weight: 500;
        }

        /* Estilos para mostrar tipo de préstamo */
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

        /* NUEVO: Estilo para préstamos pagados */
        .badge-pagado {
            background: rgba(56, 189, 248, 0.2);
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.4);
        }

        .fila-pagada {
            background: rgba(56, 189, 248, 0.05) !important;
            border-left: 3px solid #38bdf8;
        }

        .texto-pagado {
            color: #38bdf8 !important;
            font-style: italic;
        }

        /* Resto de estilos (igual que antes) */
        .nota-pagados{
            background:rgba(34,197,94,0.11);
            border-radius:14px;
            padding:12px 14px;
            border:1px solid rgba(34,197,94,0.4);
            font-size:0.85rem;
            display:flex;
            align-items:flex-start;
            gap:8px;
            margin-bottom:18px;
        }

        .form-card{
            background:var(--bg-card);
            border-radius:18px;
            border:1px solid var(--border-subtle);
            padding:16px 18px 18px;
            margin-bottom:20px;
        }

        .form-group{
            margin-bottom:16px;
        }
        label{
            display:block;
            margin-bottom:6px;
            font-weight:500;
            font-size:0.85rem;
            color:var(--text-soft);
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
            outline:none;
            transition:all .18s ease;
        }

        select:focus,input:focus{
            border-color:var(--accent);
            box-shadow:0 0 0 1px rgba(56,189,248,0.4);
        }

        button{
            border-radius:999px;
            background:linear-gradient(135deg,var(--accent),var(--accent-strong));
            border:none;
            font-weight:600;
            text-transform:uppercase;
            letter-spacing:0.08em;
            font-size:0.8rem;
            cursor:pointer;
            padding:10px 16px;
            box-shadow:0 10px 18px rgba(56,189,248,0.35);
        }
        button:hover{
            transform:translateY(-1px);
            box-shadow:0 14px 26px rgba(56,189,248,0.5);
            filter:brightness(1.05);
        }
        button:active{
            transform:translateY(0);
            box-shadow:0 8px 14px rgba(56,189,248,0.35);
        }

        select[multiple]{height:200px;}

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
            position:sticky;
            top:0;
            z-index:1;
            font-size:0.78rem;
            text-transform:uppercase;
            letter-spacing:0.08em;
            color:var(--text-soft);
        }

        tbody tr:nth-child(even){
            background:#020617;
        }
        tbody tr:nth-child(odd){
            background:#020617;
        }

        tbody tr:hover{
            background:rgba(15,23,42,0.9);
        }

        .totales{
            background:rgba(15,23,42,0.96);
            font-weight:600;
        }

        .moneda{text-align:right;font-variant-numeric:tabular-nums;}

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
            font-size:0.8rem;
            opacity:0.85;
        }

        .detalle-prestamo{
            background:#020617;
        }

        .header-deudor{
            background:#020617;
        }

        .excluido{
            background:rgba(127,29,29,0.45) !important;
            text-decoration:line-through;
            color:#9ca3af;
        }

        .interes-input,.meses-input,.comision-input{
            width:72px;
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
            font-size:0.82rem;
            color:#facc15;
        }

        .config-celene{
            background:rgba(56,189,248,0.06);
            padding:12px 14px;
            border-radius:14px;
            margin:10px 0;
            border:1px solid rgba(56,189,248,0.5);
        }

        .buscador-container{
            position:relative;
            margin-bottom:10px;
        }
        .buscador-input{
            width:100%;
            padding:8px 30px 8px 10px;
            border-radius:999px;
            border:1px solid rgba(148,163,184,0.4);
            background:#020617;
            font-size:0.85rem;
        }
        .buscador-icon{
            position:absolute;
            right:10px;
            top:50%;
            transform:translateY(-50%);
            color:#6b7280;
            font-size:0.9rem;
        }

        .contador-deudores{
            font-size:0.78rem;
            color:var(--text-soft);
            margin-top:5px;
        }

        .deudor-item{
            padding:7px 9px;
            cursor:pointer;
            border-bottom:1px solid rgba(31,41,55,0.9);
            font-size:0.85rem;
            display:flex;
            align-items:center;
            justify-content:space-between;
        }

        .deudor-item:hover{
            background:rgba(30,64,175,0.5);
        }

        .deudor-item.selected{
            background:linear-gradient(90deg,#1d4ed8,#22c55e);
            color:white;
            border-bottom-color:transparent;
        }

        .deudores-container{
            border:1px solid var(--border-subtle);
            border-radius:14px;
            max-height:220px;
            overflow-y:auto;
            background:#020617;
        }

        .deudor-pill{
            font-size:0.7rem;
            padding:2px 7px;
            border-radius:999px;
            background:rgba(15,23,42,0.9);
            border:1px solid rgba(148,163,184,0.4);
        }

        .botones-seleccion{
            margin:6px 0 10px;
            display:flex;
            flex-wrap:wrap;
            gap:8px;
        }
        .botones-seleccion button{
            width:auto;
            padding:6px 10px;
            font-size:0.75rem;
            box-shadow:none;
            background:rgba(15,23,42,0.9);
            border:1px solid rgba(148,163,184,0.7);
            text-transform:none;
            letter-spacing:0.03em;
        }
        .botones-seleccion button:hover{
            box-shadow:0 0 0 1px rgba(148,163,184,0.9);
            transform:none;
        }

        .filtro-fechas{
            background:var(--bg-card);
            padding:14px 16px;
            border-radius:16px;
            margin:12px 0 18px;
            border:1px solid var(--border-subtle);
        }

        .filtro-fechas small{
            font-size:0.78rem;
            color:var(--text-soft);
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
            display:flex;
            align-items:center;
            gap:8px;
        }
        .subtitulo-cuadro::before{
            content:"◆";
            font-size:0.75rem;
            color:var(--accent);
        }

        .cuadro-otros{
            background:linear-gradient(145deg,#020617,#020617);
            padding:10px 12px 14px;
            border-radius:16px;
            border:1px solid var(--border-subtle);
            margin-top:8px;
        }

        .checkbox-otro-deudor{
            transform:scale(1.2);
            cursor:pointer;
        }

        @media (max-width: 900px){
            .container{padding:16px;}
            .page-header{
                flex-direction:column;
                align-items:flex-start;
            }
            .config-table th:nth-child(2),
            .config-table td:nth-child(2) {
                display: none;
            }
            .total-comisiones-general {
                padding: 12px 16px;
            }
            .monto-total {
                font-size: 1.4rem;
            }
        }

        small{
            font-size:0.77rem;
            color:var(--text-soft);
        }
        
        .info-porcentajes {
            background: rgba(56, 189, 248, 0.08);
            border-radius: 14px;
            padding: 12px 14px;
            margin: 10px 0 16px;
            border: 1px solid rgba(56, 189, 248, 0.4);
            font-size: 0.85rem;
        }

        .info-porcentajes strong {
            color: var(--accent);
        }
        
        .hidden-input {
            display: none;
        }
        
        .alerta-empresa {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.4);
            border-radius: 12px;
            padding: 12px 15px;
            margin: 10px 0;
            font-size: 0.85rem;
            color: #f59e0b;
        }
        
        /* NUEVO: Estilo para filtro de estado */
        .filtro-estado {
            background: linear-gradient(135deg, #3b0764, #1e40af);
            border-radius: 16px;
            padding: 16px 18px;
            margin-bottom: 20px;
            border: 2px solid rgba(139, 92, 246, 0.5);
        }
        
        .filtro-estado h3 {
            margin-top: 0;
            margin-bottom: 12px;
            color: #a855f7;
            font-size: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div>
                <div class="page-header-badge">
                    <span class="icon">📊</span>
                    <span>Panel de préstamos - Gestión completa</span>
                </div>
                <h1>Reporte de Préstamos Consolidados</h1>
            </div>
        </div>
        
        <!-- NUEVO: TOTAL GENERAL DE TODAS TUS COMISIONES -->
        <div class="total-comisiones-general">
            <strong>🎯 TOTAL DE TODAS TUS COMISIONES (PRÉSTAMOS PENDIENTES):</strong>
            <span class="monto-total">$ <?php echo number_format($total_todas_tus_comisiones, 0, ',', '.'); ?></span>
            <br>
            <small style="color: rgba(255,255,255,0.8); font-size: 0.85rem;">
                Suma de todas las comisiones de todos los prestamistas (solo préstamos nuevos pendientes)
            </small>
        </div>
        
        <!-- NUEVA SECCIÓN: Filtro de estado de préstamos -->
        <div class="filtro-estado form-card">
            <h3>📋 Seleccionar tipo de préstamos a mostrar</h3>
            <div class="form-row">
                <div class="form-col">
                    <label for="estado_prestamos">Mostrar:</label>
                    <select name="estado_prestamos" id="estado_prestamos" onchange="toggleFiltroPagados()">
                        <option value="pendientes" <?php echo $estado_seleccionado == 'pendientes' ? 'selected' : ''; ?>>Solo préstamos pendientes</option>
                        <option value="pagados" <?php echo $estado_seleccionado == 'pagados' ? 'selected' : ''; ?>>Solo préstamos pagados</option>
                        <option value="todos" <?php echo $estado_seleccionado == 'todos' ? 'selected' : ''; ?>>Todos los préstamos</option>
                    </select>
                </div>
                
                <!-- Filtro de fecha de pago (solo visible cuando se seleccionan pagados) -->
                <div class="form-col" id="filtro_pagados_container" style="<?php echo $estado_seleccionado == 'pagados' ? '' : 'display:none;'; ?>">
                    <label for="fecha_pago_desde">Fecha de Pago Desde:</label>
                    <input type="date" name="fecha_pago_desde" id="fecha_pago_desde" 
                           value="<?php echo htmlspecialchars($fecha_pago_desde); ?>">
                    <br>
                    <label for="fecha_pago_hasta">Fecha de Pago Hasta:</label>
                    <input type="date" name="fecha_pago_hasta" id="fecha_pago_hasta" 
                           value="<?php echo htmlspecialchars($fecha_pago_hasta); ?>">
                    <small>Filtrar por fecha en que se marcó como pagado</small>
                </div>
            </div>
            <div class="info-porcentajes">
                <strong>💡 Instrucciones:</strong><br>
                • <strong>Préstamos pendientes:</strong> Solo los que aún no se han pagado (pagado = 0)<br>
                • <strong>Préstamos pagados:</strong> Solo los que ya se pagaron (pagado = 1), puedes filtrar por fecha de pago<br>
                • <strong>Todos:</strong> Muestra tanto pendientes como pagados
            </div>
        </div>
        
        <!-- NUEVA SECCIÓN: Configuración de Porcentajes por Prestamista -->
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
                        <th>Total Prestado Activo</th>
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
                        // Resetear el puntero para recorrer de nuevo
                        $result_prestamistas_lista->data_seek(0);
                        while ($prest = $result_prestamistas_lista->fetch_assoc()): 
                            $prestamista_nombre = $prest['prestamista'];
                            
                            // Usar datos ya calculados anteriormente (SIN filtro de empresa para tabla general)
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
                                
                                <!-- COLUMNA: Total Prestado Activo -->
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
                                
                                <!-- COLUMNA NUEVA: Tu Ganancia Total -->
                                <td class="ganancia-cell">
                                    <strong>$ <?php echo number_format($tu_ganancia, 0, ',', '.'); ?></strong>
                                    <br>
                                    <small>
                                        (<?php echo number_format($config['comision'], 1); ?>% de $<?php echo number_format($total_nuevos, 0, ',', '.'); ?> nuevos)
                                    </small>
                                </td>
                                
                                <td style="text-align: center; font-weight: 600; color: var(--accent);">
                                    <?php echo number_format($total_porcentaje, 2); ?>%
                                    <?php if ($total_porcentaje != 13.00): ?>
                                        <br><small style="color: #f59e0b;">(debería ser 13%)</small>
                                    <?php endif; ?>
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
            
            <div class="info-porcentajes">
                <strong>💡 ¿Cómo funciona?</strong><br>
                1. <strong>Total Prestado Activo</strong>: Suma de todos los préstamos pendientes (viejos + nuevos).<br>
                2. <strong>Tu Ganancia Total</strong>: Calculada automáticamente basada en tu % de comisión sobre préstamos NUEVOS.<br>
                3. <strong>TOTAL GENERAL ARRIBA</strong>: Suma de todas tus comisiones de todos los prestamistas.<br>
                4. <strong>Nota</strong>: Los préstamos excluidos en el reporte NO se incluyen en este cálculo.<br>
                Ejemplo: Para Celene, si prestó $8,000,000 nuevos y tienes 5% comisión, tu ganancia es $400,000.
            </div>
        </div>
        
        <div class="nota-pagados" style="<?php echo $estado_seleccionado != 'pendientes' ? 'background:rgba(56,189,248,0.11); border:1px solid rgba(56,189,248,0.4);' : ''; ?>">
            <?php if ($estado_seleccionado == 'pendientes'): ?>
                <strong>Nota:</strong> Esta vista muestra solo préstamos que están <strong>pendientes de pago</strong> (pagado = 0). Los préstamos ya pagados no aparecen en esta lista.
            <?php elseif ($estado_seleccionado == 'pagados'): ?>
                <strong>Nota:</strong> Esta vista muestra solo préstamos que están <strong>pagados</strong> (pagado = 1). <?php echo !empty($fecha_pago_desde) || !empty($fecha_pago_hasta) ? 'Filtrados por fecha de pago.' : ''; ?>
            <?php else: ?>
                <strong>Nota:</strong> Esta vista muestra <strong>todos los préstamos</strong>, tanto pendientes como pagados.
            <?php endif; ?>
        </div>
        
        <form method="POST" id="formPrincipal">
            <!-- Campo oculto para almacenar IDs excluidos -->
            <input type="hidden" name="prestamos_excluidos" id="prestamosExcluidos" 
                   value="<?php echo htmlspecialchars(implode(',', $prestamos_excluidos)); ?>">
            
            <!-- Filtro de Fechas y Empresa -->
            <div class="filtro-fechas form-card">
                <h3>Filtro de conductores (tabla VIAJES)</h3>
                <div class="fecha-row">
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
                <small>Usa el mismo rango de fechas y empresa que en la vista de pago para que salgan los mismos conductores.</small>
                <?php if (!empty($empresa_seleccionada)): ?>
                <div class="alerta-empresa">
                    <strong>⚠ FILTRO DE EMPRESA ACTIVO:</strong> Solo se mostrarán préstamos de la empresa <strong>"<?php echo htmlspecialchars($empresa_seleccionada); ?>"</strong> en ambos cuadros.
                </div>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <div class="form-card">
                        <div class="form-group">
                            <label for="deudores">Seleccionar Conductores (haz clic para seleccionar):</label>
                            
                            <!-- Buscador para conductores -->
                            <div class="buscador-container">
                                <input type="text" id="buscadorDeudores" class="buscador-input" 
                                       placeholder="Buscar conductor...">
                                <span class="buscador-icon">🔍</span>
                            </div>

                            <!-- Botones de selección rápida -->
                            <div class="botones-seleccion">
                                <button type="button" onclick="seleccionarTodos()">Seleccionar todos</button>
                                <button type="button" onclick="deseleccionarTodos()">Deseleccionar todos</button>
                            </div>
                            
                            <!-- Lista personalizada de conductores -->
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
                                    echo '<div style="padding: 10px; text-align: center; color: #9ca3af; font-size:0.85rem;">';
                                    if (!empty($fecha_desde) && !empty($fecha_hasta)) {
                                        echo 'No se encontraron conductores para el rango de fechas y empresa seleccionados';
                                    } else {
                                        echo 'Selecciona un rango de fechas para ver los conductores';
                                    }
                                    echo '</div>';
                                }
                                ?>
                            </div>
                            
                            <!-- Campo oculto para almacenar los valores seleccionados -->
                            <input type="hidden" name="deudores" id="deudoresSeleccionados" 
                                   value="<?php echo htmlspecialchars(implode(',', $deudores_seleccionados)); ?>">
                            
                            <div class="contador-deudores" id="contadorDeudores">
                                <?php 
                                if (!empty($conductores_filtrados)) {
                                    $total_conductores = count($conductores_filtrados);
                                    $seleccionados = count($deudores_seleccionados);
                                    echo "Seleccionados: $seleccionados de $total_conductores conductores";
                                } else {
                                    if (!empty($fecha_desde) && !empty($fecha_hasta)) {
                                        echo "No se encontraron conductores";
                                    } else {
                                        echo "Selecciona fechas para ver conductores";
                                    }
                                }
                                ?>
                            </div>
                            <small>Haz clic en cada conductor para seleccionarlo o quitarlo del reporte.</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-card">
                        <div class="form-group">
                            <label for="prestamista">Seleccionar Prestamista:</label>
                            <select name="prestamista" id="prestamista" required>
                                <option value="">-- Seleccionar Prestamista --</option>
                                <?php 
                                if (isset($result_prestamistas) && $result_prestamistas->num_rows > 0) {
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
                            <small>Los porcentajes entre paréntesis son los configurados arriba (para préstamos nuevos).</small>
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
                <span style="font-size:0.9rem; color:var(--text-soft); margin-left:10px;">
                    (<?php 
                    if ($estado_seleccionado == 'pendientes') echo 'Solo préstamos PENDIENTES';
                    elseif ($estado_seleccionado == 'pagados') echo 'Solo préstamos PAGADOS';
                    else echo 'TODOS los préstamos';
                    ?>)
                </span>
                <?php 
                $config_actual = isset($config_prestamistas[$prestamista_seleccionado]) 
                    ? $config_prestamistas[$prestamista_seleccionado] 
                    : ['interes' => 10.00, 'comision' => 0.00];
                ?>
                <span style="font-size:0.9rem; color:var(--text-soft); margin-left:10px;">
                    (Configuración para préstamos nuevos: <?php echo number_format($config_actual['interes'], 1); ?>% interés + <?php echo number_format($config_actual['comision'], 1); ?>% comisión)
                </span>
            </h2>
            
            <?php if (!empty($empresa_seleccionada) || (!empty($fecha_desde) && !empty($fecha_hasta))): ?>
            <div class="info-meses">
                <strong>Filtro de conductores aplicado:</strong>
                <?php if (!empty($empresa_seleccionada)): ?>
                    Empresa <strong>"<?php echo htmlspecialchars($empresa_seleccionada); ?>"</strong> |
                <?php endif; ?>
                Fechas: <strong><?php echo htmlspecialchars($fecha_desde); ?></strong> al <strong><?php echo htmlspecialchars($fecha_hasta); ?></strong>
                <?php if ($estado_seleccionado == 'pagados' && (!empty($fecha_pago_desde) || !empty($fecha_pago_hasta))): ?>
                    | Fecha pago: 
                    <?php if (!empty($fecha_pago_desde)): ?>
                        desde <strong><?php echo htmlspecialchars($fecha_pago_desde); ?></strong>
                    <?php endif; ?>
                    <?php if (!empty($fecha_pago_hasta)): ?>
                        hasta <strong><?php echo htmlspecialchars($fecha_pago_hasta); ?></strong>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="info-meses">
                <strong>📅 REGLA DE FECHA DE CORTE APLICADA:</strong><br>
                • <span class="badge-tipo badge-viejo">Préstamos VIEJOS</span> (antes del 29-10-2025): <strong>10% interés todo para <?php echo htmlspecialchars($prestamista_seleccionado); ?>, 0% comisión para ti</strong><br>
                • <span class="badge-tipo badge-nuevo">Préstamos NUEVOS</span> (después del 29-10-2025): <strong><?php echo number_format($config_actual['interes'], 1); ?>% para <?php echo htmlspecialchars($prestamista_seleccionado); ?> + <?php echo number_format($config_actual['comision'], 1); ?>% comisión para ti</strong>
                = Total <?php echo number_format($config_actual['interes'] + $config_actual['comision'], 1); ?>% para el deudor
            </div>

            <!-- ===================== -->
            <!-- CUADRO 1 -->
            <!-- ===================== -->
            <div class="subtitulo-cuadro">
                Cuadro 1: Préstamos de conductores y otros deudores seleccionados
                <?php if (!empty($prestamos_por_deudor)): 
                    $total_viejos = array_sum(array_column($prestamos_por_deudor, 'cantidad_viejos'));
                    $total_nuevos = array_sum(array_column($prestamos_por_deudor, 'cantidad_nuevos'));
                    $total_pagados = array_sum(array_column($prestamos_por_deudor, 'cantidad_pagados'));
                ?>
                    <span style="font-size:0.85rem; color:var(--text-soft); margin-left:15px;">
                        (<span class="badge-tipo badge-viejo"><?php echo $total_viejos; ?> viejos</span> | 
                        <span class="badge-tipo badge-nuevo"><?php echo $total_nuevos; ?> nuevos</span>
                        <?php if ($total_pagados > 0): ?>
                            | <span class="badge-tipo badge-pagado"><?php echo $total_pagados; ?> pagados</span>
                        <?php endif; ?>
                        )
                    </span>
                <?php endif; ?>
            </div>

            <?php if (empty($prestamos_por_deudor)): ?>
                <div style="background-color: rgba(239,68,68,0.12); border:1px solid rgba(239,68,68,0.4); padding: 12px; border-radius: 10px; margin: 15px 0; font-size:0.85rem;">
                    <strong>No se encontraron préstamos</strong> para los deudores seleccionados en la empresa <strong>"<?php echo htmlspecialchars($empresa_seleccionada); ?>"</strong>.
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
                        <th>Total a pagar</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody id="cuerpoReporte">
                    <?php 
                    $total_capital_general = 0;
                    $total_general = 0;
                    $total_interes_prestamista_general = 0;
                    $total_comision_personal_general = 0;
                    $total_viejos_general = 0;
                    $total_nuevos_general = 0;
                    $total_pagados_general = 0;
                    ?>
                    <?php foreach ($prestamos_por_deudor as $deudor => $datos): ?>
                    <?php 
                        $total_capital_general += $datos['total_capital'];
                        $total_general += $datos['total_general'];
                        $total_interes_prestamista_general += $datos['total_interes_prestamista'];
                        $total_comision_personal_general += $datos['total_comision_personal'];
                        $total_viejos_general += $datos['cantidad_viejos'];
                        $total_nuevos_general += $datos['cantidad_nuevos'];
                        $total_pagados_general += $datos['cantidad_pagados'];
                    ?>
                    <tr class="header-deudor <?php echo $datos['cantidad_pagados'] > 0 && $datos['cantidad_prestamos'] == $datos['cantidad_pagados'] ? 'fila-pagada' : ''; ?>" 
                        id="fila-<?php echo md5($deudor); ?>">
                        <td>
                            <span class="detalle-toggle" onclick="toggleDetalle('<?php echo md5($deudor); ?>')">
                                <?php echo htmlspecialchars($deudor); ?>
                            </span>
                            <?php if ($datos['cantidad_viejos'] > 0 || $datos['cantidad_nuevos'] > 0 || $datos['cantidad_pagados'] > 0): ?>
                                <br>
                                <small style="font-size:0.75rem;">
                                    <?php if ($datos['cantidad_viejos'] > 0): ?>
                                        <span class="badge-tipo badge-viejo"><?php echo $datos['cantidad_viejos']; ?> v</span>
                                    <?php endif; ?>
                                    <?php if ($datos['cantidad_nuevos'] > 0): ?>
                                        <span class="badge-tipo badge-nuevo"><?php echo $datos['cantidad_nuevos']; ?> n</span>
                                    <?php endif; ?>
                                    <?php if ($datos['cantidad_pagados'] > 0): ?>
                                        <span class="badge-tipo badge-pagado"><?php echo $datos['cantidad_pagados']; ?> p</span>
                                    <?php endif; ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $datos['cantidad_prestamos']; ?></td>
                        <td class="moneda capital-deudor">$ <?php echo number_format($datos['total_capital'], 0, ',', '.'); ?></td>
                        <td class="moneda interes-prestamista-deudor">$ <?php echo number_format($datos['total_interes_prestamista'], 0, ',', '.'); ?></td>
                        <td class="moneda comision-deudor">$ <?php echo number_format($datos['total_comision_personal'], 0, ',', '.'); ?></td>
                        <td class="moneda total-deudor">$ <?php echo number_format($datos['total_general'], 0, ',', '.'); ?></td>
                        <td>
                            <?php if ($datos['cantidad_pagados'] == $datos['cantidad_prestamos'] && $datos['cantidad_prestamos'] > 0): ?>
                                <span class="badge-tipo badge-pagado">Pagado</span>
                            <?php elseif ($datos['cantidad_pagados'] > 0): ?>
                                <span class="badge-tipo badge-pagado">Parcial</span>
                            <?php else: ?>
                                <span class="badge-tipo badge-nuevo">Pendiente</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <!-- Detalle de cada préstamo -->
                    <tr class="detalle-prestamo" id="detalle-<?php echo md5($deudor); ?>" style="display:none;">
                        <td colspan="7">
                            <table style="width: 100%; background-color: #020617;">
                                <thead>
                                    <tr>
                                        <th>Incluir</th>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Estado</th>
                                        <th>Fecha Pago</th>
                                        <th>Monto</th>
                                        <th>Meses</th>
                                        <th>Int. Prestamista $</th>
                                        <th>Tu Comisión $</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($datos['prestamos_detalle'] as $index => $detalle): 
                                        $es_excluido = $detalle['excluido'] ?? false;
                                        $esta_pagado = $detalle['pagado'] ?? false;
                                        $fecha_pago = $detalle['pagado_at'] ?? null;
                                    ?>
                                    <tr class="fila-prestamo <?php echo $detalle['tipo'] == 'viejo' ? 'prestamo-viejo' : 'prestamo-nuevo'; ?> <?php echo $es_excluido ? 'excluido' : ''; ?> <?php echo $esta_pagado ? 'fila-pagada' : ''; ?>" 
                                        data-deudor="<?php echo md5($deudor); ?>" data-id="<?php echo $detalle['id']; ?>">
                                        <td class="acciones">
                                            <?php if (!$esta_pagado): // Solo mostrar checkbox si no está pagado ?>
                                            <input type="checkbox" class="checkbox-excluir" <?php echo !$es_excluido ? 'checked' : ''; ?> 
                                                   onchange="togglePrestamo(this, <?php echo $detalle['id']; ?>)">
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $detalle['fecha']; ?></td>
                                        <td>
                                            <span class="badge-tipo <?php echo $detalle['tipo'] == 'viejo' ? 'badge-viejo' : 'badge-nuevo'; ?>">
                                                <?php echo $detalle['tipo'] == 'viejo' ? 'Viejo' : 'Nuevo'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($esta_pagado): ?>
                                                <span class="badge-tipo badge-pagado">Pagado</span>
                                            <?php else: ?>
                                                <span class="badge-tipo badge-nuevo">Pendiente</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($fecha_pago): 
                                                $fecha_pago_dt = new DateTime($fecha_pago);
                                                echo $fecha_pago_dt->format('Y-m-d');
                                            else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="moneda monto-prestamo">$ <?php echo number_format($detalle['monto'], 0, ',', '.'); ?></td>
                                        <td class="acciones">
                                            <?php if (!$esta_pagado): // Solo editable si no está pagado ?>
                                            <input type="number" class="meses-input" value="<?php echo $detalle['meses']; ?>" 
                                                   min="1" max="36" onchange="recalcularPrestamo(this)"
                                                   data-monto="<?php echo $detalle['monto']; ?>"
                                                   data-tipo="<?php echo $detalle['tipo']; ?>">
                                            <?php else: ?>
                                                <?php echo $detalle['meses']; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="moneda interes-prestamista-prestamo">$ <?php echo number_format($detalle['interes_prestamista'], 0, ',', '.'); ?></td>
                                        <td class="moneda comision-prestamo">$ <?php echo number_format($detalle['comision_personal'], 0, ',', '.'); ?></td>
                                        <td class="moneda total-prestamo">$ <?php echo number_format($detalle['total'], 0, ',', '.'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Totales generales CUADRO 1 -->
                    <tr class="totales">
                        <td colspan="2">
                            <strong>TOTAL GENERAL CUADRO 1</strong>
                            <br>
                            <small>
                                <span class="badge-tipo badge-viejo"><?php echo $total_viejos_general; ?> viejos</span> | 
                                <span class="badge-tipo badge-nuevo"><?php echo $total_nuevos_general; ?> nuevos</span>
                                <?php if ($total_pagados_general > 0): ?>
                                    | <span class="badge-tipo badge-pagado"><?php echo $total_pagados_general; ?> pagados</span>
                                <?php endif; ?>
                            </small>
                        </td>
                        <td class="moneda" id="total-capital-general">$ <?php echo number_format($total_capital_general, 0, ',', '.'); ?></td>
                        <td class="moneda" id="total-interes-prestamista-general">$ <?php echo number_format($total_interes_prestamista_general, 0, ',', '.'); ?></td>
                        <td class="moneda" id="total-comision-general">$ <?php echo number_format($total_comision_personal_general, 0, ',', '.'); ?></td>
                        <td class="moneda" id="total-general">$ <?php echo number_format($total_general, 0, ',', '.'); ?></td>
                        <td>
                            <?php if ($estado_seleccionado == 'pendientes'): ?>
                                <span class="badge-tipo badge-nuevo">Pendientes</span>
                            <?php elseif ($estado_seleccionado == 'pagados'): ?>
                                <span class="badge-tipo badge-pagado">Pagados</span>
                            <?php else: ?>
                                <span class="badge-tipo">Mixto</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- =========================== -->
            <!-- CUADRO 2: OTROS DEUDORES    -->
            <!-- =========================== -->
            <div class="subtitulo-cuadro">
                Cuadro 2: Otros deudores (nómina, facturas, etc.)
                <?php if (!empty($otros_prestamos_por_deudor)): 
                    $otros_total_viejos = array_sum(array_column($otros_prestamos_por_deudor, 'cantidad_viejos'));
                    $otros_total_nuevos = array_sum(array_column($otros_prestamos_por_deudor, 'cantidad_nuevos'));
                    $otros_total_pagados = array_sum(array_column($otros_prestamos_por_deudor, 'cantidad_pagados'));
                ?>
                    <span style="font-size:0.85rem; color:var(--text-soft); margin-left:15px;">
                        (<span class="badge-tipo badge-viejo"><?php echo $otros_total_viejos; ?> viejos</span> | 
                        <span class="badge-tipo badge-nuevo"><?php echo $otros_total_nuevos; ?> nuevos</span>
                        <?php if ($otros_total_pagados > 0): ?>
                            | <span class="badge-tipo badge-pagado"><?php echo $otros_total_pagados; ?> pagados</span>
                        <?php endif; ?>
                        )
                    </span>
                <?php endif; ?>
            </div>
            <div class="cuadro-otros">
            <?php if (empty($otros_prestamos_por_deudor)): ?>
                <div style="background-color: rgba(148,163,184,0.06); border:1px solid rgba(148,163,184,0.3); padding: 10px; border-radius: 10px; margin: 10px 0; font-size:0.85rem;">
                    No hay otros deudores con préstamos diferentes a los ya seleccionados en la empresa <strong>"<?php echo htmlspecialchars($empresa_seleccionada); ?>"</strong>.
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Incluir en Cuadro 1</th>
                            <th>Deudor</th>
                            <th>Préstamos</th>
                            <th>Capital</th>
                            <th>Interés Prestamista</th>
                            <th>Tu Comisión</th>
                            <th>Total a pagar</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $otros_total_capital_general = 0;
                        $otros_total_general = 0;
                        $otros_total_interes_prestamista_general = 0;
                        $otros_total_comision_personal_general = 0;
                        $otros_total_viejos_general = 0;
                        $otros_total_nuevos_general = 0;
                        $otros_total_pagados_general = 0;
                        ?>
                        <?php foreach ($otros_prestamos_por_deudor as $deudor => $datos): ?>
                        <?php
                            $otros_total_capital_general += $datos['total_capital'];
                            $otros_total_general += $datos['total_general'];
                            $otros_total_interes_prestamista_general += $datos['total_interes_prestamista'];
                            $otros_total_comision_personal_general += $datos['total_comision_personal'];
                            $otros_total_viejos_general += $datos['cantidad_viejos'];
                            $otros_total_nuevos_general += $datos['cantidad_nuevos'];
                            $otros_total_pagados_general += $datos['cantidad_pagados'];
                        ?>
                        <tr class="<?php echo $datos['cantidad_pagados'] > 0 && $datos['cantidad_prestamos'] == $datos['cantidad_pagados'] ? 'fila-pagada' : ''; ?>">
                            <td class="acciones">
                                <input type="checkbox"
                                       class="checkbox-otro-deudor"
                                       data-deudor="<?php echo htmlspecialchars($deudor); ?>"
                                       onchange="toggleOtroDeudor(this)">
                            </td>
                            <td>
                                <?php echo htmlspecialchars($deudor); ?>
                                <?php if ($datos['cantidad_viejos'] > 0 || $datos['cantidad_nuevos'] > 0 || $datos['cantidad_pagados'] > 0): ?>
                                    <br>
                                    <small style="font-size:0.75rem;">
                                        <?php if ($datos['cantidad_viejos'] > 0): ?>
                                            <span class="badge-tipo badge-viejo"><?php echo $datos['cantidad_viejos']; ?> v</span>
                                        <?php endif; ?>
                                        <?php if ($datos['cantidad_nuevos'] > 0): ?>
                                            <span class="badge-tipo badge-nuevo"><?php echo $datos['cantidad_nuevos']; ?> n</span>
                                        <?php endif; ?>
                                        <?php if ($datos['cantidad_pagados'] > 0): ?>
                                            <span class="badge-tipo badge-pagado"><?php echo $datos['cantidad_pagados']; ?> p</span>
                                        <?php endif; ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $datos['cantidad_prestamos']; ?></td>
                            <td class="moneda">$ <?php echo number_format($datos['total_capital'], 0, ',', '.'); ?></td>
                            <td class="moneda">$ <?php echo number_format($datos['total_interes_prestamista'], 0, ',', '.'); ?></td>
                            <td class="moneda">$ <?php echo number_format($datos['total_comision_personal'], 0, ',', '.'); ?></td>
                            <td class="moneda">$ <?php echo number_format($datos['total_general'], 0, ',', '.'); ?></td>
                            <td>
                                <?php if ($datos['cantidad_pagados'] == $datos['cantidad_prestamos'] && $datos['cantidad_prestamos'] > 0): ?>
                                    <span class="badge-tipo badge-pagado">Pagado</span>
                                <?php elseif ($datos['cantidad_pagados'] > 0): ?>
                                    <span class="badge-tipo badge-pagado">Parcial</span>
                                <?php else: ?>
                                    <span class="badge-tipo badge-nuevo">Pendiente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- Totales OTROS DEUDORES -->
                        <tr class="totales">
                            <td colspan="3">
                                <strong>TOTAL GENERAL CUADRO 2</strong>
                                <br>
                                <small>
                                    <span class="badge-tipo badge-viejo"><?php echo $otros_total_viejos_general; ?> viejos</span> | 
                                    <span class="badge-tipo badge-nuevo"><?php echo $otros_total_nuevos_general; ?> nuevos</span>
                                    <?php if ($otros_total_pagados_general > 0): ?>
                                        | <span class="badge-tipo badge-pagado"><?php echo $otros_total_pagados_general; ?> pagados</span>
                                    <?php endif; ?>
                                </small>
                            </td>
                            <td class="moneda">$ <?php echo number_format($otros_total_capital_general, 0, ',', '.'); ?></td>
                            <td class="moneda">$ <?php echo number_format($otros_total_interes_prestamista_general, 0, ',', '.'); ?></td>
                            <td class="moneda">$ <?php echo number_format($otros_total_comision_personal_general, 0, ',', '.'); ?></td>
                            <td class="moneda">$ <?php echo number_format($otros_total_general, 0, ',', '.'); ?></td>
                            <td>
                                <?php if ($estado_seleccionado == 'pendientes'): ?>
                                    <span class="badge-tipo badge-nuevo">Pendientes</span>
                                <?php elseif ($estado_seleccionado == 'pagados'): ?>
                                    <span class="badge-tipo badge-pagado">Pagados</span>
                                <?php else: ?>
                                    <span class="badge-tipo">Mixto</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
            </div>

        </div>
        <?php endif; ?>
    </div>

    <script>
        // ARRAY PARA ALMACENAR DEUDORES SELECCIONADOS
        let deudoresSeleccionados = <?php echo json_encode($deudores_seleccionados); ?>;
        // ARRAY PARA ALMACENAR PRÉSTAMOS EXCLUIDOS
        let prestamosExcluidos = <?php echo json_encode($prestamos_excluidos); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            actualizarListaDeudores();
            actualizarContador();
            actualizarCampoExcluidos();
            toggleFiltroPagados(); // Asegurar que se muestre/oculte correctamente
            
            // Validar que se haya seleccionado empresa
            const empresaSelect = document.getElementById('empresa');
            const formPrincipal = document.getElementById('formPrincipal');
            
            if (empresaSelect) {
                empresaSelect.addEventListener('change', function() {
                    if (this.value === '') {
                        this.style.borderColor = '#ef4444';
                        this.style.boxShadow = '0 0 0 1px rgba(239,68,68,0.4)';
                    } else {
                        this.style.borderColor = '';
                        this.style.boxShadow = '';
                    }
                });
                
                formPrincipal.addEventListener('submit', function(e) {
                    if (empresaSelect.value === '') {
                        e.preventDefault();
                        alert('⚠ Por favor selecciona una empresa antes de generar el reporte.');
                        empresaSelect.focus();
                        empresaSelect.style.borderColor = '#ef4444';
                        empresaSelect.style.boxShadow = '0 0 0 1px rgba(239,68,68,0.4)';
                    }
                });
            }
        });

        function toggleFiltroPagados() {
            const estadoSelect = document.getElementById('estado_prestamos');
            const filtroPagados = document.getElementById('filtro_pagados_container');
            
            if (estadoSelect.value === 'pagados') {
                filtroPagados.style.display = 'block';
            } else {
                filtroPagados.style.display = 'none';
            }
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
            const items = document.querySelectorAll('.deudor-item');
            items.forEach(item => {
                const valor = item.getAttribute('data-value');
                if (deudoresSeleccionados.includes(valor)) {
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
                item.addEventListener('click', function() {
                    toggleDeudor(this);
                });
            });
        }

        function actualizarContador() {
            const items = document.querySelectorAll('.deudor-item');
            const total = items.length;
            const seleccionados = deudoresSeleccionados.length;
            
            if (total > 0) {
                document.getElementById('contadorDeudores').textContent = 
                    `Seleccionados: ${seleccionados} de ${total} conductores`;
            }
        }

        function actualizarCampoExcluidos() {
            document.getElementById('prestamosExcluidos').value = prestamosExcluidos.join(',');
        }

        function seleccionarTodos() {
            const items = document.querySelectorAll('.deudor-item');
            deudoresSeleccionados = [];
            
            items.forEach(item => {
                const valor = item.getAttribute('data-value');
                deudoresSeleccionados.push(valor);
                item.classList.add('selected');
            });
            
            document.getElementById('deudoresSeleccionados').value = deudoresSeleccionados.join(',');
            actualizarContador();
        }

        function deseleccionarTodos() {
            const items = document.querySelectorAll('.deudor-item');
            deudoresSeleccionados = [];
            
            items.forEach(item => {
                item.classList.remove('selected');
            });
            
            document.getElementById('deudoresSeleccionados').value = '';
            actualizarContador();
        }

        document.getElementById('buscadorDeudores').addEventListener('input', function(e) {
            const filtro = e.target.value.toLowerCase();
            const items = document.querySelectorAll('.deudor-item');
            let contador = 0;
            
            items.forEach(item => {
                const texto = item.textContent.toLowerCase();
                if (texto.includes(filtro)) {
                    item.style.display = '';
                    contador++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            const contadorElement = document.getElementById('contadorDeudores');
            if (filtro === '') {
                actualizarContador();
            } else {
                contadorElement.textContent = `Mostrando ${contador} conductor(es) que coinciden con "${filtro}"`;
            }
        });
        
        function toggleDetalle(id) {
            const detalle = document.getElementById('detalle-' + id);
            if (!detalle) return;
            detalle.style.display = detalle.style.display === 'none' || detalle.style.display === '' ? 'table-row' : 'none';
        }
        
        function togglePrestamo(checkbox, prestamoId) {
            const fila = checkbox.closest('.fila-prestamo');
            if (!fila) return;

            if (!checkbox.checked) {
                fila.classList.add('excluido');
                // Agregar a array de excluidos
                if (!prestamosExcluidos.includes(prestamoId.toString())) {
                    prestamosExcluidos.push(prestamoId.toString());
                }
            } else {
                fila.classList.remove('excluido');
                // Remover de array de excluidos
                const index = prestamosExcluidos.indexOf(prestamoId.toString());
                if (index !== -1) {
                    prestamosExcluidos.splice(index, 1);
                }
            }
            
            // Actualizar campo oculto
            actualizarCampoExcluidos();
            
            const deudorId = fila.dataset.deudor;
            actualizarTotalesDeudor(deudorId);
        }
        
        function recalcularPrestamo(input) {
            const fila = input.closest('.fila-prestamo');
            const monto = parseFloat(fila.querySelector('.monto-prestamo').textContent.replace(/[^\d]/g, ''));
            const inputMeses = fila.querySelector('.meses-input');
            const meses = parseInt(inputMeses.value);
            const tipo = inputMeses.getAttribute('data-tipo');
            
            if (tipo === 'viejo') {
                // PRÉSTAMOS VIEJOS: Siempre 10% para prestamista, 0% para ti
                const interesPrestamistaMonto = monto * (10 / 100) * meses;
                const comisionPersonalMonto = 0;
                const total = monto + interesPrestamistaMonto;
                
                const celdaInteresPrestamista = fila.querySelector('.interes-prestamista-prestamo');
                const celdaComision = fila.querySelector('.comision-prestamo');
                const celdaTotal = fila.querySelector('.total-prestamo');
                
                celdaInteresPrestamista.textContent = '$ ' + formatNumber(interesPrestamistaMonto);
                celdaComision.textContent = '$ ' + formatNumber(comisionPersonalMonto);
                celdaTotal.textContent = '$ ' + formatNumber(total);
            } else {
                // PRÉSTAMOS NUEVOS: Usar configuración del prestamista
                const prestamistaSelect = document.getElementById('prestamista');
                const selectedOption = prestamistaSelect.options[prestamistaSelect.selectedIndex];
                const interesPrestamista = parseFloat(selectedOption.getAttribute('data-interes') || 0);
                const comisionPersonal = parseFloat(selectedOption.getAttribute('data-comision') || 0);
                
                const interesPrestamistaMonto = monto * (interesPrestamista / 100) * meses;
                const comisionPersonalMonto = monto * (comisionPersonal / 100) * meses;
                const total = monto + interesPrestamistaMonto;
                
                const celdaInteresPrestamista = fila.querySelector('.interes-prestamista-prestamo');
                const celdaComision = fila.querySelector('.comision-prestamo');
                const celdaTotal = fila.querySelector('.total-prestamo');
                
                celdaInteresPrestamista.textContent = '$ ' + formatNumber(interesPrestamistaMonto);
                celdaComision.textContent = '$ ' + formatNumber(comisionPersonalMonto);
                celdaTotal.textContent = '$ ' + formatNumber(total);
            }
            
            const checkbox = fila.querySelector('.checkbox-excluir');
            if (checkbox && checkbox.checked) {
                const deudorId = fila.dataset.deudor;
                actualizarTotalesDeudor(deudorId);
            }
        }
        
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
                    const monto = parseFloat(fila.querySelector('.monto-prestamo').textContent.replace(/[^\d]/g, ''));
                    const total = parseFloat(fila.querySelector('.total-prestamo').textContent.replace(/[^\d]/g, ''));
                    const interesPrestamista = parseFloat(fila.querySelector('.interes-prestamista-prestamo').textContent.replace(/[^\d]/g, ''));
                    const comision = parseFloat(fila.querySelector('.comision-prestamo').textContent.replace(/[^\d]/g, ''));
                    
                    totalCapital += monto;
                    totalGeneral += total;
                    totalInteresPrestamista += interesPrestamista;
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

        // Seleccionar otros deudores para pasarlos al cuadro 1
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