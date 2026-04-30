<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Conexión BD
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Inicializar sesión para extras
if (!isset($_SESSION['extras'])) {
    $_SESSION['extras'] = array();
}

// Inicializar sesión para nombres cambiados
if (!isset($_SESSION['nombres_cambiados'])) {
    $_SESSION['nombres_cambiados'] = array();
}

// Inicializar sesión para totales manuales
if (!isset($_SESSION['totales_manuales'])) {
    $_SESSION['totales_manuales'] = array();
}

// Procesar acción de mover a extras
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['export_word'])) {
        // No hacer nada, solo exportar
    } elseif (isset($_POST['action']) && $_POST['action'] === 'mover_extras' && isset($_POST['ids_seleccionados'])) {
        $empresa_origen = $_POST['empresa_origen'];
        $ids = explode(',', $_POST['ids_seleccionados']);
        
        foreach ($ids as $id) {
            $id = intval($id);
            $sql = "SELECT v.*, rc.clasificacion 
                    FROM viajes v
                    LEFT JOIN ruta_clasificacion rc ON v.ruta COLLATE utf8mb4_general_ci = rc.ruta COLLATE utf8mb4_general_ci 
                        AND v.tipo_vehiculo COLLATE utf8mb4_general_ci = rc.tipo_vehiculo COLLATE utf8mb4_general_ci
                    WHERE v.id = ? AND v.empresa = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $id, $empresa_origen);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $clasificacion = $row['clasificacion'];
                $costo = obtener_tarifa($clasificacion, $row['tipo_vehiculo'], $row['empresa'], $conn);
                $row['costo'] = $costo;
                
                // Si el nombre fue cambiado, mantener el nombre cambiado
                if (isset($_SESSION['nombres_cambiados'][$id])) {
                    $row['nombre'] = $_SESSION['nombres_cambiados'][$id];
                }
                
                $_SESSION['extras'][] = $row;
            }
            $stmt->close();
        }
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'limpiar_extras') {
        $_SESSION['extras'] = array();
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'eliminar_extra' && isset($_POST['extra_index'])) {
        $index = intval($_POST['extra_index']);
        if (isset($_SESSION['extras'][$index])) {
            array_splice($_SESSION['extras'], $index, 1);
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
        exit;
    }
    
    // Cambiar nombres de conductores en una tabla
    if (isset($_POST['action']) && $_POST['action'] === 'cambiar_conductores') {
        $empresa = $_POST['empresa_cambio'];
        $nombres = isset($_POST['nombres_conductores']) ? $_POST['nombres_conductores'] : array();
        $nombres = array_values(array_filter($nombres, function($n) { return !empty(trim($n)); }));
        
        if (!empty($nombres)) {
            $_SESSION['nombres_cambiados_empresa'][$empresa] = $nombres;
        } else {
            unset($_SESSION['nombres_cambiados_empresa'][$empresa]);
        }
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
        exit;
    }
    
    // Guardar total manual
    if (isset($_POST['action']) && $_POST['action'] === 'guardar_total_manual') {
        $key = $_POST['total_key'];
        $valor = floatval(str_replace(['.', ','], ['', '.'], $_POST['total_valor']));
        $_SESSION['totales_manuales'][$key] = $valor;
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
        exit;
    }
    
    // Guardar total manual de extras
    if (isset($_POST['action']) && $_POST['action'] === 'guardar_total_extras') {
        $valor = floatval(str_replace(['.', ','], ['', '.'], $_POST['total_extras_valor']));
        $_SESSION['totales_manuales']['__extras__'] = $valor;
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
        exit;
    }
    
    // Restaurar nombres originales
    if (isset($_POST['action']) && $_POST['action'] === 'restaurar_nombres') {
        $_SESSION['nombres_cambiados'] = array();
        $_SESSION['nombres_cambiados_empresa'] = array();
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
        exit;
    }
    
    // Restaurar totales manuales
    if (isset($_POST['action']) && $_POST['action'] === 'restaurar_totales') {
        $_SESSION['totales_manuales'] = array();
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
        exit;
    }
}

// Obtener parámetros
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';
$empresas_seleccionadas = isset($_GET['empresas']) ? $_GET['empresas'] : array();

// Presupuesto base
$PRESUPUESTO_BASE = 13000000;

function obtener_tarifa($clasificacion, $tipo_vehiculo, $empresa, $conn) {
    if (empty($clasificacion) || empty($empresa)) {
        return 0;
    }
    
    $sql = "SELECT completo, medio, extra, carrotanque, siapana, 
                   riohacha_completo, riohacha_medio, nazareth_siapana_maicao, 
                   nazareth_siapana_flor_de_la_guajira
            FROM tarifas 
            WHERE empresa = ? AND tipo_vehiculo = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    $stmt->bind_param("ss", $empresa, $tipo_vehiculo);
    $stmt->execute();
    $result = $stmt->get_result();
    $tarifa = $result->fetch_assoc();
    $stmt->close();
    
    if (!$tarifa) return 0;
    
    switch($clasificacion) {
        case 'completo': return isset($tarifa['completo']) ? floatval($tarifa['completo']) : 0;
        case 'medio': return isset($tarifa['medio']) ? floatval($tarifa['medio']) : 0;
        case 'extra': return isset($tarifa['extra']) ? floatval($tarifa['extra']) : 0;
        case 'carrotanque': return isset($tarifa['carrotanque']) ? floatval($tarifa['carrotanque']) : 0;
        case 'siapana': return isset($tarifa['siapana']) ? floatval($tarifa['siapana']) : 0;
        case 'riohacha_completo': return isset($tarifa['riohacha_completo']) ? floatval($tarifa['riohacha_completo']) : 0;
        case 'riohacha_medio': return isset($tarifa['riohacha_medio']) ? floatval($tarifa['riohacha_medio']) : 0;
        case 'nazareth_siapana_maicao': return isset($tarifa['nazareth_siapana_maicao']) ? floatval($tarifa['nazareth_siapana_maicao']) : 0;
        case 'nazareth_siapana_flor_de_la_guajira': return isset($tarifa['nazareth_siapana_flor_de_la_guajira']) ? floatval($tarifa['nazareth_siapana_flor_de_la_guajira']) : 0;
        default: return 0;
    }
}

// Obtener empresas que empiezan con P.
$empresas_disponibles = array();
$sql_emp = "SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa != '' AND empresa LIKE 'P.%' ORDER BY empresa";
$res_emp = $conn->query($sql_emp);
if ($res_emp) {
    while ($row = $res_emp->fetch_assoc()) {
        $empresas_disponibles[] = $row['empresa'];
    }
}

// Obtener todos los conductores únicos para el autocompletado
$todos_conductores = array();
$sql_cond = "SELECT DISTINCT nombre FROM viajes WHERE nombre IS NOT NULL AND nombre != '' ORDER BY nombre";
$res_cond = $conn->query($sql_cond);
if ($res_cond) {
    while ($row = $res_cond->fetch_assoc()) {
        $todos_conductores[] = $row['nombre'];
    }
}

function calcular_acumulado_extras($extras) {
    $acumulado = 0;
    $resultados = array();
    foreach ($extras as $index => $extra) {
        $acumulado += $extra['costo'];
        $resultados[] = array('index' => $index, 'data' => $extra, 'acumulado' => $acumulado);
    }
    return $resultados;
}

// Función para obtener el total efectivo (manual o calculado)
function obtenerTotalEfectivo($key, $total_calculado) {
    if (isset($_SESSION['totales_manuales'][$key])) {
        return $_SESSION['totales_manuales'][$key];
    }
    return $total_calculado;
}

// Función para obtener datos de las tablas
function obtenerDatosParaExportar($conn, $fecha_desde, $fecha_hasta, $empresas_seleccionadas, $extras, $PRESUPUESTO_BASE) {
    $datos = array();
    $ids_en_extras = array_column($extras, 'id');
    $alertas = array();
    
    foreach ($empresas_seleccionadas as $empresa_actual) {
        $sql = "SELECT v.id, v.nombre, v.cedula, v.fecha, v.ruta, v.tipo_vehiculo, v.empresa, rc.clasificacion
                FROM viajes v
                LEFT JOIN ruta_clasificacion rc 
                    ON v.ruta COLLATE utf8mb4_general_ci = rc.ruta COLLATE utf8mb4_general_ci
                    AND v.tipo_vehiculo COLLATE utf8mb4_general_ci = rc.tipo_vehiculo COLLATE utf8mb4_general_ci
                WHERE v.empresa = ?";
        
        $params = array($empresa_actual);
        $types = "s";
        
        if (!empty($fecha_desde)) {
            $sql .= " AND v.fecha >= ?";
            $params[] = $fecha_desde;
            $types .= "s";
        }
        if (!empty($fecha_hasta)) {
            $sql .= " AND v.fecha <= ?";
            $params[] = $fecha_hasta;
            $types .= "s";
        }
        
        if (!empty($ids_en_extras)) {
            $placeholders = implode(',', array_fill(0, count($ids_en_extras), '?'));
            $sql .= " AND v.id NOT IN ($placeholders)";
            foreach ($ids_en_extras as $id) {
                $params[] = $id;
                $types .= "i";
            }
        }
        
        $sql .= " ORDER BY v.fecha ASC, v.id ASC";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $all_rows = array();
                $acumulado_total = 0;
                $nombres_cambiados = isset($_SESSION['nombres_cambiados_empresa'][$empresa_actual]) ? 
                    $_SESSION['nombres_cambiados_empresa'][$empresa_actual] : array();
                
                while ($row = $result->fetch_assoc()) {
                    $clasificacion = $row['clasificacion'];
                    $costo = obtener_tarifa($clasificacion, $row['tipo_vehiculo'], $row['empresa'], $conn);
                    $acumulado_total += $costo;
                    
                    $all_rows[] = array(
                        'id' => $row['id'],
                        'fecha' => $row['fecha'],
                        'nombre' => $row['nombre'],
                        'nombre_original' => $row['nombre'],
                        'cedula' => $row['cedula'],
                        'ruta' => $row['ruta'],
                        'tipo_vehiculo' => $row['tipo_vehiculo'],
                        'clasificacion' => $clasificacion,
                        'costo' => $costo
                    );
                }
                
                if (!empty($nombres_cambiados)) {
                    $tablas_conductores = array();
                    foreach ($nombres_cambiados as $idx => $nombre_conductor) {
                        $key = $empresa_actual . '|' . $nombre_conductor;
                        $tablas_conductores[$idx] = array(
                            'nombre_conductor' => $nombre_conductor,
                            'key' => $key,
                            'rows' => array(),
                            'total_calculado' => 0,
                            'total' => 0
                        );
                    }
                    
                    $contador_intercalado = 0;
                    foreach ($all_rows as $row) {
                        $conductor_idx = $contador_intercalado % count($nombres_cambiados);
                        $row['nombre'] = $nombres_cambiados[$conductor_idx];
                        $_SESSION['nombres_cambiados'][$row['id']] = $row['nombre'];
                        
                        $tablas_conductores[$conductor_idx]['rows'][] = $row;
                        $tablas_conductores[$conductor_idx]['total_calculado'] += $row['costo'];
                        
                        $contador_intercalado++;
                    }
                    
                    // Aplicar totales manuales
                    foreach ($tablas_conductores as &$tabla) {
                        $tabla['total'] = obtenerTotalEfectivo($tabla['key'], $tabla['total_calculado']);
                    }
                    
                    $total_general_efectivo = array_sum(array_column($tablas_conductores, 'total'));
                    
                    $datos[$empresa_actual] = array(
                        'tipo' => 'multiple',
                        'tablas' => $tablas_conductores,
                        'total_general' => $acumulado_total,
                        'total_general_efectivo' => $total_general_efectivo,
                        'key' => $empresa_actual
                    );
                    
                    foreach ($tablas_conductores as $tabla) {
                        if ($tabla['total'] > $PRESUPUESTO_BASE) {
                            $exceso = $tabla['total'] - $PRESUPUESTO_BASE;
                            $alertas[] = array(
                                'empresa' => $empresa_actual . ' - ' . $tabla['nombre_conductor'],
                                'total' => $tabla['total'],
                                'exceso' => $exceso,
                                'presupuesto' => $PRESUPUESTO_BASE
                            );
                        }
                    }
                } else {
                    $rows_data = array();
                    $acumulado = 0;
                    foreach ($all_rows as $row) {
                        $acumulado += $row['costo'];
                        $rows_data[] = array_merge($row, array('acumulado' => $acumulado));
                    }
                    
                    $key = $empresa_actual;
                    $total_efectivo = obtenerTotalEfectivo($key, $acumulado);
                    
                    $datos[$empresa_actual] = array(
                        'tipo' => 'simple',
                        'key' => $key,
                        'rows' => $rows_data,
                        'total' => $acumulado,
                        'total_efectivo' => $total_efectivo
                    );
                    
                    if ($total_efectivo > $PRESUPUESTO_BASE) {
                        $exceso = $total_efectivo - $PRESUPUESTO_BASE;
                        $alertas[] = array(
                            'empresa' => $empresa_actual,
                            'total' => $total_efectivo,
                            'exceso' => $exceso,
                            'presupuesto' => $PRESUPUESTO_BASE
                        );
                    }
                }
            } else {
                $datos[$empresa_actual] = array('tipo' => 'simple', 'key' => $empresa_actual, 'rows' => array(), 'total' => 0, 'total_efectivo' => 0);
            }
            $stmt->close();
        }
    }
    
    return array('datos' => $datos, 'alertas' => $alertas);
}

$extras_con_acumulado = calcular_acumulado_extras($_SESSION['extras']);
$total_extras_calculado = array_sum(array_column($_SESSION['extras'], 'costo'));
$total_extras = obtenerTotalEfectivo('__extras__', $total_extras_calculado);

$resultado = obtenerDatosParaExportar($conn, $fecha_desde, $fecha_hasta, $empresas_seleccionadas, $_SESSION['extras'], $PRESUPUESTO_BASE);
$datos_empresas = $resultado['datos'];
$alertas = $resultado['alertas'];

// Calcular totales para el resumen
$total_puestos = 0;
foreach ($datos_empresas as $empresa => $data) {
    if ($data['tipo'] === 'multiple') {
        $total_puestos += $data['total_general_efectivo'];
    } else {
        $total_puestos += $data['total_efectivo'];
    }
}
$total_general = $total_puestos + $total_extras;

// Si es exportación a Word
if (isset($_POST['export_word'])) {
    header("Content-Type: application/msword");
    header("Content-Disposition: attachment; filename=informe_viajes_" . date('Y-m-d') . ".doc");
    header("Cache-Control: no-cache, must-revalidate");
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Informe de Viajes</title>
        <style>
            body { font-family: Arial, Helvetica, sans-serif; margin: 20px; font-size: 11pt; }
            h1 { color: #1a73e8; font-size: 18pt; }
            h2 { background: #1a73e8; color: white; padding: 8px 12px; font-size: 14pt; margin-top: 20px; }
            h3 { background: #455a64; color: white; padding: 6px 10px; font-size: 12pt; margin-top: 15px; }
            .info-filtros { margin-bottom: 20px; padding: 10px; background: #f0f2f5; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th { background: #f8f9fa; border: 1px solid #ccc; padding: 8px; font-weight: bold; }
            td { border: 1px solid #ccc; padding: 6px 8px; vertical-align: top; }
            .total-row { background: #e8f0fe; font-weight: bold; }
            .costo { text-align: right; }
            .extras-table { margin-top: 30px; border: 2px solid #ff9800; }
            .extras-title { background: #ff9800; color: white; padding: 8px 12px; font-size: 14pt; font-weight: bold; }
            .resumen-table { margin-top: 30px; border: 2px solid #1a73e8; }
            .resumen-title { background: #1a73e8; color: white; padding: 8px 12px; font-size: 14pt; font-weight: bold; }
            .subtotal-row { background: #e3f2fd; font-weight: bold; }
            .gran-total-row { background: #1a73e8; color: white; font-weight: bold; font-size: 12pt; }
        </style>
    </head>
    <body>
        <h1>📊 Informe de Viajes por Puesto de Salud</h1>
        <div class="info-filtros">
            <strong>📅 Período:</strong> <?php echo $fecha_desde ? date('d/m/Y', strtotime($fecha_desde)) : 'Todo'; ?> - <?php echo $fecha_hasta ? date('d/m/Y', strtotime($fecha_hasta)) : 'Todo'; ?><br>
            <strong>🏥 Empresas:</strong> <?php echo !empty($empresas_seleccionadas) ? implode(', ', $empresas_seleccionadas) : 'Ninguna'; ?>
        </div>
        
        <?php foreach ($datos_empresas as $empresa => $data): ?>
            <?php if ($data['tipo'] === 'multiple'): ?>
                <?php foreach ($data['tablas'] as $tabla): if (empty($tabla['rows'])) continue; ?>
                    <h3>🏥 <?php echo htmlspecialchars($empresa); ?> - <?php echo htmlspecialchars($tabla['nombre_conductor']); ?></h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Conductor</th>
                                <th>Ruta</th>
                                <th>Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($tabla['rows'] as $row): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($row['fecha'])); ?></td>
                                <td><?php echo htmlspecialchars($row['nombre'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['ruta'] ?? '-'); ?></td>
                                <td class="costo">$ <?php echo number_format($row['costo'], 0, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="3" style="text-align:right;">TOTAL:</td>
                                <td class="costo">$ <?php echo number_format($tabla['total'], 0, ',', '.'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                <?php endforeach; ?>
            <?php else: ?>
                <?php if (empty($data['rows'])) continue; ?>
                <h2>🏥 <?php echo htmlspecialchars($empresa); ?></h2>
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Conductor</th>
                            <th>Ruta</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data['rows'] as $row): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($row['fecha'])); ?></td>
                            <td><?php echo htmlspecialchars($row['nombre'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['ruta'] ?? '-'); ?></td>
                            <td class="costo">$ <?php echo number_format($row['costo'], 0, ',', '.'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="3" style="text-align:right;">TOTAL:</td>
                            <td class="costo">$ <?php echo number_format($data['total_efectivo'], 0, ',', '.'); ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <?php if (!empty($_SESSION['extras'])): ?>
            <div class="extras-table">
                <div class="extras-title">⭐ EXTRAS ⭐</div>
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Conductor</th>
                            <th>Ruta</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($extras_con_acumulado as $ex): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($ex['data']['fecha'])); ?></td>
                            <td><?php echo htmlspecialchars($ex['data']['nombre'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($ex['data']['ruta'] ?? '-'); ?></td>
                            <td class="costo">$ <?php echo number_format($ex['data']['costo'], 0, ',', '.'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="3" style="text-align:right;">TOTAL EXTRAS:</td>
                            <td class="costo">$ <?php echo number_format($total_extras, 0, ',', '.'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <!-- TABLA RESUMEN -->
        <div class="resumen-table">
            <div class="resumen-title">📋 RESUMEN GENERAL</div>
            <table>
                <thead>
                    <tr>
                        <th>Puesto de Salud</th>
                        <th style="text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($empresas_seleccionadas as $empresa): 
                        if (!isset($datos_empresas[$empresa])) continue;
                        $data = $datos_empresas[$empresa];
                        $total_empresa = ($data['tipo'] === 'multiple') ? $data['total_general_efectivo'] : $data['total_efectivo'];
                    ?>
                    <tr>
                        <td>🏥 <?php echo htmlspecialchars($empresa); ?></td>
                        <td class="costo">$ <?php echo number_format($total_empresa, 0, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="subtotal-row">
                        <td style="text-align:right;"><strong>SUBTOTAL PUESTOS</strong></td>
                        <td class="costo"><strong>$ <?php echo number_format($total_puestos, 0, ',', '.'); ?></strong></td>
                    </tr>
                    <?php if (!empty($_SESSION['extras'])): ?>
                    <tr>
                        <td>⭐ EXTRAS</td>
                        <td class="costo">$ <?php echo number_format($total_extras, 0, ',', '.'); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="gran-total-row">
                        <td style="text-align:right;"><strong>TOTAL GENERAL</strong></td>
                        <td class="costo"><strong>$ <?php echo number_format($total_general, 0, ',', '.'); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe de Viajes</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            padding: 20px;
        }
        
        .container { max-width: 1600px; margin: 0 auto; }
        
        .header {
            background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
            color: white;
            padding: 20px 25px;
            border-radius: 12px 12px 0 0;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 { font-size: 24px; margin-bottom: 5px; }
        .header p { opacity: 0.9; font-size: 14px; }
        
        .btn-word {
            background: #2e7d32;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
        }
        .btn-word:hover { background: #1b5e20; }
        
        .btn-restaurar {
            background: #c62828;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            margin-left: 10px;
        }
        .btn-restaurar:hover { background: #b71c1c; }
        
        .btn-restaurar-totales {
            background: #6a1b9a;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            margin-left: 10px;
        }
        .btn-restaurar-totales:hover { background: #4a148c; }
        
        .alertas-container { margin-bottom: 25px; }
        .alerta-presupuesto {
            background: #ffebee;
            border-left: 4px solid #f44336;
            border-radius: 8px;
            padding: 12px 20px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .alerta-mensaje { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .alerta-icono { font-size: 24px; }
        .alerta-texto { font-size: 14px; color: #333; }
        .alerta-texto strong { color: #c62828; }
        .badge-exceso {
            display: inline-block;
            background: #f44336;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            margin-left: 10px;
        }
        .btn-ir-tabla {
            background: #ff9800;
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
        }
        .btn-ir-tabla:hover { background: #e65100; }
        
        .filtros-card {
            background: white;
            padding: 20px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .filtros-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-end;
            margin-bottom: 20px;
        }
        
        .filtro-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .filtro-group label {
            font-size: 12px;
            font-weight: 600;
            color: #5f6368;
            text-transform: uppercase;
        }
        
        .filtro-group input {
            padding: 10px 15px;
            border: 1px solid #dadce0;
            border-radius: 8px;
            font-size: 14px;
            min-width: 200px;
        }
        
        .btn-filtrar, .btn-limpiar {
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            border: none;
        }
        .btn-filtrar { background: #1a73e8; color: white; }
        .btn-filtrar:hover { background: #1557b0; }
        .btn-limpiar { background: #5f6368; color: white; }
        
        .empresas-section {
            border-top: 1px solid #e8eaed;
            padding-top: 20px;
        }
        
        .empresas-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .btn-group { display: flex; gap: 10px; }
        .btn-seleccion {
            background: #f1f3f4;
            border: none;
            padding: 6px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
        }
        .btn-seleccion.all { background: #34a853; color: white; }
        .btn-seleccion.none { background: #ea4335; color: white; }
        
        .empresas-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .empresa-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8f9fa;
            padding: 6px 14px;
            border-radius: 25px;
            border: 1px solid #dadce0;
            cursor: pointer;
            font-size: 13px;
        }
        
        .extras-table {
            background: linear-gradient(135deg, #fff8e7 0%, #fff3d6 100%);
            border-radius: 12px;
            margin-bottom: 30px;
            overflow-x: auto;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border: 2px solid #ff9800;
        }
        
        .extras-header {
            background: #ff9800;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .btn-limpiar-extras {
            background: #e65100;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .empresa-table {
            background: white;
            border-radius: 12px;
            margin-bottom: 30px;
            overflow-x: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            scroll-margin-top: 100px;
        }
        
        .table-header {
            background: #1a73e8;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .table-header h2 { font-size: 18px; }
        
        .table-header-conductor {
            background: #455a64;
            color: white;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .table-header-conductor h3 { font-size: 16px; }
        
        .acciones-header { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        
        .btn-mover-extras {
            background: #ff9800;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-mover-extras:disabled { background: #ccc; cursor: not-allowed; }
        
        .btn-eliminar-extra {
            background: #ea4335;
            color: white;
            border: none;
            padding: 4px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
        }
        
        .cambio-conductores-section {
            background: #e3f2fd;
            border: 2px solid #1a73e8;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        
        .cambio-conductores-section .filtro-group {
            flex: 1;
            min-width: 200px;
        }
        
        .autocomplete-wrapper {
            position: relative;
            width: 100%;
        }
        
        .autocomplete-wrapper input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #dadce0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .autocomplete-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #dadce0;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .autocomplete-list div {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .autocomplete-list div:hover {
            background: #e3f2fd;
        }
        
        .tags-conductores {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 5px;
        }
        
        .tag-conductor {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #1a73e8;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .tag-conductor .remove-tag {
            cursor: pointer;
            font-weight: bold;
            margin-left: 5px;
        }
        
        .tag-conductor .remove-tag:hover {
            color: #ffc107;
        }
        
        .btn-cambiar-conductores {
            background: #1a73e8;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-cambiar-conductores:hover { background: #1557b0; }
        .btn-cambiar-conductores:disabled { background: #90caf9; cursor: not-allowed; }
        
        .busqueda-ruta {
            display: flex;
            gap: 10px;
            align-items: center;
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 25px;
        }
        .busqueda-ruta input {
            padding: 6px 12px;
            border: none;
            border-radius: 20px;
            font-size: 12px;
            width: 180px;
            outline: none;
        }
        .busqueda-ruta button {
            background: white;
            color: #1a73e8;
            border: none;
            padding: 5px 12px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
        }
        .busqueda-ruta button:hover {
            background: #e8eaed;
        }
        
        .input-hint {
            font-size: 11px;
            color: #5f6368;
            margin-top: 3px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }
        
        th {
            background: #f8f9fa;
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            color: #5f6368;
            border-bottom: 2px solid #dadce0;
            font-size: 12px;
            white-space: nowrap;
        }
        
        td {
            padding: 10px 8px;
            border-bottom: 1px solid #e8eaed;
            color: #202124;
            font-size: 12px;
            vertical-align: middle;
        }
        
        .checkbox-col { width: 30px; text-align: center; }
        .costo { font-weight: 600; color: #1a73e8; text-align: right !important; }
        .acumulado { font-weight: 700; color: #34a853; text-align: right !important; }
        
        .ruta-cell {
            max-width: 220px;
            white-space: normal;
            word-break: break-word;
            line-height: 1.4;
        }
        
        .drag-instruction {
            font-size: 11px;
            background: rgba(255,255,255,0.2);
            padding: 5px 12px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .sin-datos { text-align: center; padding: 40px; color: #5f6368; }
        
        html { scroll-behavior: smooth; }
        
        .btn-header-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .sub-table-wrapper {
            border: 1px solid #e8eaed;
            border-radius: 8px;
            margin-bottom: 15px;
            overflow: hidden;
        }
        
        .total-general-row {
            background: #1a73e8;
            color: white;
            font-weight: bold;
            padding: 12px 20px;
            text-align: right;
            font-size: 14px;
        }
        
        /* Estilos para totales editables */
        .total-editable {
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: flex-end;
        }
        
        .total-editable input {
            width: 120px;
            padding: 4px 8px;
            border: 1px solid #1a73e8;
            border-radius: 6px;
            text-align: right;
            font-weight: 600;
            font-size: 12px;
            color: #1a73e8;
        }
        
        .total-editable .total-calculado-original {
            font-size: 10px;
            color: #999;
            text-decoration: line-through;
            margin-right: 5px;
        }
        
        .btn-guardar-total {
            background: #1a73e8;
            color: white;
            border: none;
            padding: 4px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
        }
        .btn-guardar-total:hover { background: #1557b0; }
        
        .extras-total-editable {
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: flex-end;
            padding: 10px 15px;
            background: #ffe0b2;
        }
        
        .extras-total-editable input {
            width: 130px;
            padding: 5px 10px;
            border: 1px solid #ff9800;
            border-radius: 6px;
            text-align: right;
            font-weight: 600;
            font-size: 13px;
        }
        
        /* Estilos para la tabla resumen */
        .resumen-table {
            background: white;
            border-radius: 12px;
            margin-top: 30px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border: 2px solid #1a73e8;
        }
        
        .resumen-header {
            background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
            color: white;
            padding: 15px 25px;
            font-size: 18px;
            font-weight: 700;
        }
        
        .resumen-table table {
            min-width: 600px;
        }
        
        .resumen-table th {
            font-size: 13px;
            padding: 14px 15px;
            background: #f0f4ff;
        }
        
        .resumen-table td {
            font-size: 13px;
            padding: 12px 15px;
        }
        
        .subtotal-row td {
            background: #e3f2fd;
            font-weight: bold;
            font-size: 13px;
        }
        
        .gran-total-row td {
            background: #1a73e8;
            color: white;
            font-weight: bold;
            font-size: 14px;
            padding: 14px 15px;
        }
        
        @media (max-width: 1200px) {
            .table-header { flex-direction: column; text-align: center; }
            .acciones-header { justify-content: center; }
            .cambio-conductores-section { flex-direction: column; }
            .btn-header-group { justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>📊 Informe de Viajes por Puesto de Salud</h1>
                <p>✨ Arrastra el mouse sobre los checkboxes para seleccionar múltiples filas | Shift + Click para seleccionar rango</p>
            </div>
            <div class="btn-header-group">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="export_word" value="1">
                    <button type="submit" class="btn-word">📄 Generar Word</button>
                </form>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="restaurar_nombres">
                    <button type="submit" class="btn-restaurar">🔄 Restaurar Nombres</button>
                </form>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="restaurar_totales">
                    <button type="submit" class="btn-restaurar-totales">🔢 Restaurar Totales</button>
                </form>
            </div>
        </div>
        
        <!-- NOTIFICACIONES DE ALERTA -->
        <?php if (!empty($alertas)): ?>
        <div class="alertas-container">
            <?php foreach ($alertas as $alerta): ?>
            <div class="alerta-presupuesto">
                <div class="alerta-mensaje">
                    <span class="alerta-icono">⚠️</span>
                    <span class="alerta-texto">
                        <strong><?php echo htmlspecialchars($alerta['empresa']); ?></strong> 
                        excede presupuesto de <strong>$ <?php echo number_format($alerta['presupuesto'], 0, ',', '.'); ?></strong>
                        (Exceso: $ <?php echo number_format($alerta['exceso'], 0, ',', '.'); ?>)
                        <span class="badge-exceso">Total: $ <?php echo number_format($alerta['total'], 0, ',', '.'); ?></span>
                    </span>
                </div>
                <button class="btn-ir-tabla" onclick="irATabla('<?php echo htmlspecialchars($alerta['empresa']); ?>')">📍 Ir a la tabla</button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <form method="GET" action="" id="filtroForm">
            <div class="filtros-card">
                <div class="filtros-row">
                    <div class="filtro-group">
                        <label>📅 Fecha desde</label>
                        <input type="date" name="fecha_desde" value="<?php echo htmlspecialchars($fecha_desde); ?>">
                    </div>
                    <div class="filtro-group">
                        <label>📅 Fecha hasta</label>
                        <input type="date" name="fecha_hasta" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                    </div>
                    <div class="filtro-group">
                        <button type="submit" class="btn-filtrar">🔍 Filtrar</button>
                    </div>
                    <div class="filtro-group">
                        <a href="?" class="btn-limpiar">🗑️ Limpiar</a>
                    </div>
                </div>
                
                <div class="empresas-section">
                    <div class="empresas-header">
                        <h3>🏥 Puestos de Salud (P.)</h3>
                        <div class="btn-group">
                            <button type="button" class="btn-seleccion all" onclick="seleccionarTodas(true)">✅ Seleccionar todas</button>
                            <button type="button" class="btn-seleccion none" onclick="seleccionarTodas(false)">❌ Quitar todas</button>
                        </div>
                    </div>
                    <div class="empresas-grid" id="empresasGrid">
                        <?php foreach ($empresas_disponibles as $empresa): ?>
                        <label class="empresa-checkbox">
                            <input type="checkbox" name="empresas[]" value="<?php echo htmlspecialchars($empresa); ?>" 
                                   <?php echo (in_array($empresa, $empresas_seleccionadas) || (empty($empresas_seleccionadas) && empty($_GET))) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($empresa); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </form>
        
        <!-- TABLA DE EXTRAS -->
        <?php if (!empty($_SESSION['extras'])): ?>
        <div class="extras-table">
            <div class="extras-header">
                <h2>⭐ EXTRAS ⭐</h2>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="limpiar_extras">
                    <button type="submit" class="btn-limpiar-extras">🗑️ Limpiar todas</button>
                </form>
            </div>
            <div style="overflow-x: auto; max-width: 100%;">
                <table style="min-width: 1200px;">
                    <thead>
                        <tr>
                            <th>#</th><th>Fecha</th><th>Conductor</th><th>Cédula</th><th>Ruta</th><th>Tipo</th>
                            <th>Empresa Origen</th><th>Clasificación</th><th>Valor</th><th>Acumulado</th><th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $idx_extra = 0; foreach ($extras_con_acumulado as $extra): $idx_extra++; ?>
                        <tr>
                            <td style="text-align: center;"><?php echo $idx_extra; ?></td>
                            <td style="white-space: nowrap;"><?php echo $extra['data']['fecha'] ? date('d/m/Y', strtotime($extra['data']['fecha'])) : '-'; ?></td>
                            <td><strong><?php echo htmlspecialchars($extra['data']['nombre'] ?? '-'); ?></strong></td>
                            <td><?php echo htmlspecialchars($extra['data']['cedula'] ?? '-'); ?></td>
                            <td class="ruta-cell"><?php echo htmlspecialchars($extra['data']['ruta'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($extra['data']['tipo_vehiculo'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($extra['data']['empresa'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($extra['data']['clasificacion'] ?? '-'); ?></td>
                            <td class="costo">$ <?php echo number_format($extra['data']['costo'], 0, ',', '.'); ?></td>
                            <td class="acumulado">$ <?php echo number_format($extra['acumulado'], 0, ',', '.'); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="eliminar_extra">
                                    <input type="hidden" name="extra_index" value="<?php echo $extra['index']; ?>">
                                    <button type="submit" class="btn-eliminar-extra">✖</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Total editable de extras -->
            <div class="extras-total-editable">
                <span style="font-weight: 700; margin-right: 10px;">TOTAL EXTRAS:</span>
                <?php if ($total_extras != $total_extras_calculado): ?>
                <span style="font-size:11px;color:#999;text-decoration:line-through;margin-right:5px;">$ <?php echo number_format($total_extras_calculado, 0, ',', '.'); ?></span>
                <?php endif; ?>
                <span style="font-weight:700;margin-right:10px;">$ <?php echo number_format($total_extras, 0, ',', '.'); ?></span>
                <form method="POST" style="display: inline-flex; align-items: center; gap: 6px;">
                    <input type="hidden" name="action" value="guardar_total_extras">
                    <input type="number" name="total_extras_valor" value="<?php echo $total_extras; ?>" step="1" style="width:130px;padding:5px 10px;border:1px solid #ff9800;border-radius:6px;text-align:right;font-weight:600;font-size:13px;">
                    <button type="submit" class="btn-guardar-total" style="background:#ff9800;">💾 Guardar</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- TABLAS POR EMPRESA -->
        <?php
        if (!empty($empresas_seleccionadas)) {
            foreach ($empresas_seleccionadas as $empresa_actual) {
                $empresa_id_base = 'emp_' . preg_replace('/[^a-zA-Z0-9]/', '_', $empresa_actual);
                $empresa_anchor = 'tabla_' . preg_replace('/[^a-zA-Z0-9]/', '_', $empresa_actual);
                
                if (!isset($datos_empresas[$empresa_actual])) {
                    ?>
                    <div class="empresa-table" id="<?php echo $empresa_anchor; ?>">
                        <div class="table-header">
                            <h2>🏥 <?php echo htmlspecialchars($empresa_actual); ?></h2>
                        </div>
                        <div class="sin-datos">📭 No hay viajes registrados para este período</div>
                    </div>
                    <?php
                    continue;
                }
                
                $data = $datos_empresas[$empresa_actual];
                $es_nazareth = (strtolower(trim($empresa_actual)) === 'p.nazareth');
                $nombres_seleccionados = isset($_SESSION['nombres_cambiados_empresa'][$empresa_actual]) ? 
                    $_SESSION['nombres_cambiados_empresa'][$empresa_actual] : array();
                
                if ($data['tipo'] === 'multiple'):
                    $total_general = $data['total_general'];
                    $total_general_efectivo = $data['total_general_efectivo'];
                    $key_empresa = $data['key'];
                    ?>
                    <div class="empresa-table" id="<?php echo $empresa_anchor; ?>">
                        <div class="table-header">
                            <h2>
                                🏥 <?php echo htmlspecialchars($empresa_actual); ?>
                                <span class="badge-exceso" style="background: #1a73e8;">✏️ 2 Conductores</span>
                            </h2>
                            <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                                <?php if ($es_nazareth): ?>
                                <div class="busqueda-ruta">
                                    <input type="text" id="buscar_ruta_<?php echo $empresa_id_base; ?>" placeholder="Ej: Riohacha, Maicao..." autocomplete="off">
                                    <button type="button" onclick="seleccionarPorRuta('<?php echo $empresa_id_base; ?>')">🔍 Seleccionar rutas</button>
                                </div>
                                <?php endif; ?>
                                <div class="drag-instruction">🖱️ Arrastra sobre los checkboxes</div>
                            </div>
                        </div>
                        
                        <!-- SECCIÓN DE CAMBIO DE CONDUCTORES -->
                        <div class="cambio-conductores-section">
                            <div class="filtro-group" style="flex: 2; min-width: 300px;">
                                <label>👤 Buscar o escribir nombre del conductor (máx. 2) - Presiona Enter para agregar</label>
                                <div class="autocomplete-wrapper">
                                    <input type="text" 
                                           id="input_conductor_<?php echo $empresa_id_base; ?>" 
                                           placeholder="Escribe y selecciona o presiona Enter..." 
                                           autocomplete="off"
                                           onkeyup="buscarConductores('<?php echo $empresa_id_base; ?>')"
                                           onfocus="buscarConductores('<?php echo $empresa_id_base; ?>')"
                                           onkeydown="manejarTeclaConductor(event, '<?php echo $empresa_id_base; ?>')">
                                    <div class="autocomplete-list" id="autocomplete_<?php echo $empresa_id_base; ?>"></div>
                                </div>
                                <div class="input-hint">💡 Escribe y selecciona de la lista o presiona Enter para agregar un nombre libre</div>
                                <div class="tags-conductores" id="tags_<?php echo $empresa_id_base; ?>">
                                    <?php foreach ($nombres_seleccionados as $idx => $nombre): ?>
                                    <span class="tag-conductor">
                                        <?php echo htmlspecialchars($nombre); ?>
                                        <span class="remove-tag" onclick="removerConductor('<?php echo $empresa_id_base; ?>', <?php echo $idx; ?>)">✕</span>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="filtro-group" style="flex: 0 0 auto;">
                                <button type="button" 
                                        class="btn-cambiar-conductores" 
                                        id="btn_cambiar_<?php echo $empresa_id_base; ?>"
                                        onclick="cambiarConductores('<?php echo $empresa_id_base; ?>', '<?php echo htmlspecialchars($empresa_actual); ?>')"
                                        <?php echo empty($nombres_seleccionados) ? 'disabled' : ''; ?>>
                                    ✏️ Cambiar Conductores
                                </button>
                            </div>
                        </div>
                        
                        <?php foreach ($data['tablas'] as $tab_idx => $tabla): 
                            $empresa_id = $empresa_id_base . '_c' . $tab_idx;
                            $conductor_nombre = $tabla['nombre_conductor'];
                            $rows_data = $tabla['rows'];
                            $total_calculado = $tabla['total_calculado'];
                            $total_efectivo = $tabla['total'];
                            $key_tabla = $tabla['key'];
                            $excede = $total_efectivo > $PRESUPUESTO_BASE;
                            $es_manual = isset($_SESSION['totales_manuales'][$key_tabla]);
                        ?>
                        <div class="sub-table-wrapper">
                            <div class="table-header-conductor" style="<?php echo $excede ? 'background: #f44336;' : ''; ?>">
                                <h3>
                                    👤 <?php echo htmlspecialchars($conductor_nombre); ?>
                                    <?php if ($excede): ?>
                                        <span class="badge-exceso">⚠️ Excede presupuesto</span>
                                    <?php endif; ?>
                                </h3>
                                <div class="acciones-header">
                                    <button type="button" class="btn-mover-extras" 
                                            onclick="moverSeleccionados('<?php echo $empresa_id; ?>')"
                                            id="btn-mover-<?php echo $empresa_id; ?>">
                                        ➡️ Mover seleccionados a EXTRAS
                                    </button>
                                </div>
                            </div>
                            
                            <form method="POST" id="form-<?php echo $empresa_id; ?>">
                                <input type="hidden" name="action" value="mover_extras">
                                <input type="hidden" name="empresa_origen" value="<?php echo htmlspecialchars($empresa_actual); ?>">
                                <input type="hidden" name="ids_seleccionados" id="ids-<?php echo $empresa_id; ?>">
                                <div style="overflow-x: auto; max-width: 100%;">
                                    <table style="min-width: 1000px;">
                                        <thead>
                                            <tr>
                                                <th><input type="checkbox" id="select-all-<?php echo $empresa_id; ?>" onchange="toggleSeleccionarTodos(this, '<?php echo $empresa_id; ?>')"></th>
                                                <th>#</th><th>Fecha</th><th>Conductor</th><th>Cédula</th><th>Ruta</th><th>Tipo</th><th>Clasificación</th><th>Valor Viaje</th><th>Acumulado</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbody-<?php echo $empresa_id; ?>">
                                            <?php $contador = 0; $acum = 0; foreach ($rows_data as $row): $contador++; $acum += $row['costo']; ?>
                                            <tr data-ruta="<?php echo htmlspecialchars(strtolower($row['ruta'] ?? '')); ?>">
                                                <td class="checkbox-col"><input type="checkbox" class="fila-check-<?php echo $empresa_id; ?>" value="<?php echo $row['id']; ?>"></td>
                                                <td style="text-align: center;"><?php echo $contador; ?></td>
                                                <td style="white-space: nowrap;"><?php echo date('d/m/Y', strtotime($row['fecha'])); ?></td>
                                                <td><strong><?php echo htmlspecialchars($row['nombre'] ?? '-'); ?></strong></td>
                                                <td><?php echo htmlspecialchars($row['cedula'] ?? '-'); ?></td>
                                                <td class="ruta-cell"><?php echo htmlspecialchars($row['ruta'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($row['tipo_vehiculo'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($row['clasificacion'] ?? '-'); ?></td>
                                                <td class="costo">$ <?php echo number_format($row['costo'], 0, ',', '.'); ?></td>
                                                <td class="acumulado">$ <?php echo number_format($acum, 0, ',', '.'); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </form>
                            
                            <!-- Total editable -->
                            <div style="background:#e8f0fe;padding:12px 20px;display:flex;align-items:center;justify-content:flex-end;gap:12px;flex-wrap:wrap;">
                                <strong>TOTAL <?php echo htmlspecialchars($conductor_nombre); ?>:</strong>
                                <?php if ($es_manual): ?>
                                <span style="font-size:11px;color:#999;text-decoration:line-through;">$ <?php echo number_format($total_calculado, 0, ',', '.'); ?></span>
                                <span style="color:#e65100;">➜</span>
                                <?php endif; ?>
                                <span style="font-weight:700;font-size:14px;color:#1a73e8;">$ <?php echo number_format($total_efectivo, 0, ',', '.'); ?></span>
                                <form method="POST" style="display:inline-flex;align-items:center;gap:6px;">
                                    <input type="hidden" name="action" value="guardar_total_manual">
                                    <input type="hidden" name="total_key" value="<?php echo htmlspecialchars($key_tabla); ?>">
                                    <input type="number" name="total_valor" value="<?php echo $total_efectivo; ?>" step="1" style="width:110px;padding:4px 8px;border:1px solid #1a73e8;border-radius:6px;text-align:right;font-weight:600;font-size:12px;">
                                    <button type="submit" class="btn-guardar-total">💾</button>
                                </form>
                            </div>
                        </div>
                        
                        <script>
                        (function() {
                            const empresaId = '<?php echo $empresa_id; ?>';
                            const checkboxes = document.querySelectorAll(`.fila-check-${empresaId}`);
                            
                            let isDragging = false;
                            let lastToggledIndex = -1;
                            
                            checkboxes.forEach(checkbox => {
                                checkbox.addEventListener('mousedown', (e) => {
                                    isDragging = true;
                                    lastToggledIndex = Array.from(checkboxes).indexOf(checkbox);
                                    checkbox.checked = !checkbox.checked;
                                    updateSelectAllCheckbox(empresaId);
                                    actualizarBotonMover(empresaId);
                                });
                                
                                checkbox.addEventListener('mouseenter', () => {
                                    if (isDragging) {
                                        const currentIndex = Array.from(checkboxes).indexOf(checkbox);
                                        if (currentIndex !== lastToggledIndex) {
                                            checkbox.checked = !checkbox.checked;
                                            updateSelectAllCheckbox(empresaId);
                                            actualizarBotonMover(empresaId);
                                        }
                                    }
                                });
                            });
                            
                            document.addEventListener('mouseup', () => {
                                isDragging = false;
                                lastToggledIndex = -1;
                            });
                            
                            let lastChecked = null;
                            checkboxes.forEach(checkbox => {
                                checkbox.addEventListener('click', function(e) {
                                    if (e.shiftKey && lastChecked !== null) {
                                        const checkboxesArray = Array.from(checkboxes);
                                        const currentIndex = checkboxesArray.indexOf(this);
                                        const lastIndex = checkboxesArray.indexOf(lastChecked);
                                        const start = Math.min(currentIndex, lastIndex);
                                        const end = Math.max(currentIndex, lastIndex);
                                        for (let i = start; i <= end; i++) {
                                            checkboxesArray[i].checked = this.checked;
                                        }
                                        updateSelectAllCheckbox(empresaId);
                                        actualizarBotonMover(empresaId);
                                    }
                                    lastChecked = this;
                                });
                            });
                            
                            function updateSelectAllCheckbox(empId) {
                                const selectAll = document.getElementById(`select-all-${empId}`);
                                if (!selectAll) return;
                                const cbs = document.querySelectorAll(`.fila-check-${empId}`);
                                const todosChecked = Array.from(cbs).every(cb => cb.checked);
                                const algunosChecked = Array.from(cbs).some(cb => cb.checked);
                                if (todosChecked) {
                                    selectAll.checked = true;
                                    selectAll.indeterminate = false;
                                } else if (algunosChecked) {
                                    selectAll.checked = false;
                                    selectAll.indeterminate = true;
                                } else {
                                    selectAll.checked = false;
                                    selectAll.indeterminate = false;
                                }
                            }
                            
                            window.updateSelectAllCheckbox = updateSelectAllCheckbox;
                            
                            window.actualizarBotonMover = function(empId) {
                                const cbs = document.querySelectorAll(`.fila-check-${empId}`);
                                const checkeados = Array.from(cbs).filter(cb => cb.checked);
                                const btn = document.getElementById(`btn-mover-${empId}`);
                                if (btn) {
                                    btn.disabled = checkeados.length === 0;
                                    if (checkeados.length > 0) {
                                        btn.innerHTML = `➡️ Mover ${checkeados.length} seleccionado(s) a EXTRAS`;
                                    } else {
                                        btn.innerHTML = `➡️ Mover seleccionados a EXTRAS`;
                                    }
                                }
                            };
                            
                            window.toggleSeleccionarTodos = function(checkbox, empId) {
                                const cbs = document.querySelectorAll(`.fila-check-${empId}`);
                                cbs.forEach(cb => cb.checked = checkbox.checked);
                                updateSelectAllCheckbox(empId);
                                window.actualizarBotonMover(empId);
                            };
                            
                            window.moverSeleccionados = function(empId) {
                                const cbs = document.querySelectorAll(`.fila-check-${empId}`);
                                const idsSeleccionados = Array.from(cbs).filter(cb => cb.checked).map(cb => cb.value);
                                if (idsSeleccionados.length === 0) return;
                                document.getElementById(`ids-${empId}`).value = idsSeleccionados.join(',');
                                document.getElementById(`form-${empId}`).submit();
                            };
                            
                            checkboxes.forEach(cb => {
                                cb.addEventListener('change', () => {
                                    updateSelectAllCheckbox(empresaId);
                                    window.actualizarBotonMover(empresaId);
                                });
                            });
                            window.actualizarBotonMover(empresaId);
                        })();
                        </script>
                        <?php endforeach; ?>
                        
                        <!-- Total general de la empresa (solo informativo, no editable directamente) -->
                        <div class="total-general-row">
                            💰 TOTAL <?php echo htmlspecialchars($empresa_actual); ?>: 
                            $ <?php echo number_format($total_general_efectivo, 0, ',', '.'); ?>
                            <?php if ($total_general_efectivo != $total_general): ?>
                            <span style="font-size:10px;opacity:0.7;"> (calculado: $ <?php echo number_format($total_general, 0, ',', '.'); ?>)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($es_nazareth): ?>
                    <script>
                        window.seleccionarPorRuta = function(empId) {
                            const inputBusqueda = document.getElementById(`buscar_ruta_${empId}`);
                            const textoBusqueda = inputBusqueda.value.trim().toLowerCase();
                            if (textoBusqueda === "") { alert("Escribe una palabra para buscar en las rutas"); return; }
                            
                            const subTablas = document.querySelectorAll(`[id^="tbody-${empId}_c"]`);
                            let seleccionadas = 0;
                            subTablas.forEach(tbody => {
                                const filas = tbody.querySelectorAll('tr');
                                filas.forEach(fila => {
                                    const celdaRuta = fila.querySelector('.ruta-cell');
                                    if (celdaRuta) {
                                        const ruta = celdaRuta.textContent.toLowerCase();
                                        const checkbox = fila.querySelector('input[type="checkbox"]');
                                        if (checkbox && ruta.includes(textoBusqueda)) {
                                            checkbox.checked = true;
                                            seleccionadas++;
                                        }
                                    }
                                });
                            });
                            
                            subTablas.forEach(tbody => {
                                const subId = tbody.id.replace('tbody-', '');
                                if (typeof updateSelectAllCheckbox === 'function') updateSelectAllCheckbox(subId);
                                if (typeof actualizarBotonMover === 'function') actualizarBotonMover(subId);
                            });
                            
                            if (seleccionadas === 0) alert(`No se encontraron rutas que contengan "${textoBusqueda}"`);
                        };
                    </script>
                    <?php endif; ?>
                    
                <?php else: 
                    $rows_data = $data['rows'];
                    $total_calculado = $data['total'];
                    $total_efectivo = $data['total_efectivo'];
                    $excede = $total_efectivo > $PRESUPUESTO_BASE;
                    $empresa_id = $empresa_id_base;
                    $key_tabla = $data['key'];
                    $es_manual = isset($_SESSION['totales_manuales'][$key_tabla]);
                    
                    if (empty($rows_data)) {
                        ?>
                        <div class="empresa-table" id="<?php echo $empresa_anchor; ?>">
                            <div class="table-header">
                                <h2>🏥 <?php echo htmlspecialchars($empresa_actual); ?></h2>
                            </div>
                            <div class="sin-datos">📭 No hay viajes registrados para este período</div>
                        </div>
                        <?php
                        continue;
                    }
                ?>
                <div class="empresa-table" id="<?php echo $empresa_anchor; ?>">
                    <div class="table-header" style="<?php echo $excede ? 'background: #f44336;' : ''; ?>">
                        <h2>
                            🏥 <?php echo htmlspecialchars($empresa_actual); ?>
                            <?php if ($excede): ?>
                                <span class="badge-exceso">⚠️ Excede presupuesto</span>
                            <?php endif; ?>
                            <?php if (!empty($nombres_seleccionados)): ?>
                                <span class="badge-exceso" style="background: #1a73e8;">✏️ Nombres cambiados</span>
                            <?php endif; ?>
                        </h2>
                        <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                            <?php if ($es_nazareth): ?>
                            <div class="busqueda-ruta">
                                <input type="text" id="buscar_ruta_<?php echo $empresa_id; ?>" placeholder="Ej: Riohacha, Maicao..." autocomplete="off">
                                <button type="button" onclick="seleccionarPorRuta('<?php echo $empresa_id; ?>')">🔍 Seleccionar rutas</button>
                            </div>
                            <?php endif; ?>
                            <div class="drag-instruction">🖱️ Arrastra sobre los checkboxes</div>
                            <div class="acciones-header">
                                <button type="button" class="btn-mover-extras" 
                                        onclick="moverSeleccionados('<?php echo $empresa_id; ?>')"
                                        id="btn-mover-<?php echo $empresa_id; ?>">
                                    ➡️ Mover seleccionados a EXTRAS
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SECCIÓN DE CAMBIO DE CONDUCTORES -->
                    <div class="cambio-conductores-section">
                        <div class="filtro-group" style="flex: 2; min-width: 300px;">
                            <label>👤 Buscar o escribir nombre del conductor (máx. 2) - Presiona Enter para agregar</label>
                            <div class="autocomplete-wrapper">
                                <input type="text" 
                                       id="input_conductor_<?php echo $empresa_id; ?>" 
                                       placeholder="Escribe y selecciona o presiona Enter..." 
                                       autocomplete="off"
                                       onkeyup="buscarConductores('<?php echo $empresa_id; ?>')"
                                       onfocus="buscarConductores('<?php echo $empresa_id; ?>')"
                                       onkeydown="manejarTeclaConductor(event, '<?php echo $empresa_id; ?>')">
                                <div class="autocomplete-list" id="autocomplete_<?php echo $empresa_id; ?>"></div>
                            </div>
                            <div class="input-hint">💡 Escribe y selecciona de la lista o presiona Enter para agregar un nombre libre</div>
                            <div class="tags-conductores" id="tags_<?php echo $empresa_id; ?>">
                                <?php foreach ($nombres_seleccionados as $idx => $nombre): ?>
                                <span class="tag-conductor">
                                    <?php echo htmlspecialchars($nombre); ?>
                                    <span class="remove-tag" onclick="removerConductor('<?php echo $empresa_id; ?>', <?php echo $idx; ?>)">✕</span>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="filtro-group" style="flex: 0 0 auto;">
                            <button type="button" 
                                    class="btn-cambiar-conductores" 
                                    id="btn_cambiar_<?php echo $empresa_id; ?>"
                                    onclick="cambiarConductores('<?php echo $empresa_id; ?>', '<?php echo htmlspecialchars($empresa_actual); ?>')"
                                    <?php echo empty($nombres_seleccionados) ? 'disabled' : ''; ?>>
                                ✏️ Cambiar Conductores
                            </button>
                        </div>
                    </div>
                    
                    <form method="POST" id="form-<?php echo $empresa_id; ?>">
                        <input type="hidden" name="action" value="mover_extras">
                        <input type="hidden" name="empresa_origen" value="<?php echo htmlspecialchars($empresa_actual); ?>">
                        <input type="hidden" name="ids_seleccionados" id="ids-<?php echo $empresa_id; ?>">
                        <div style="overflow-x: auto; max-width: 100%;">
                            <table style="min-width: 1000px;">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="select-all-<?php echo $empresa_id; ?>" onchange="toggleSeleccionarTodos(this, '<?php echo $empresa_id; ?>')"></th>
                                        <th>#</th><th>Fecha</th><th>Conductor</th><th>Cédula</th><th>Ruta</th><th>Tipo</th><th>Clasificación</th><th>Valor Viaje</th><th>Acumulado</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-<?php echo $empresa_id; ?>">
                                    <?php $contador = 0; foreach ($rows_data as $row): $contador++; ?>
                                    <tr data-ruta="<?php echo htmlspecialchars(strtolower($row['ruta'] ?? '')); ?>">
                                        <td class="checkbox-col"><input type="checkbox" class="fila-check-<?php echo $empresa_id; ?>" value="<?php echo $row['id']; ?>"></td>
                                        <td style="text-align: center;"><?php echo $contador; ?></td>
                                        <td style="white-space: nowrap;"><?php echo date('d/m/Y', strtotime($row['fecha'])); ?></td>
                                        <td><strong><?php echo htmlspecialchars($row['nombre'] ?? '-'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['cedula'] ?? '-'); ?></td>
                                        <td class="ruta-cell"><?php echo htmlspecialchars($row['ruta'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['tipo_vehiculo'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['clasificacion'] ?? '-'); ?></td>
                                        <td class="costo">$ <?php echo number_format($row['costo'], 0, ',', '.'); ?></td>
                                        <td class="acumulado">$ <?php echo number_format($row['acumulado'], 0, ',', '.'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                    
                    <!-- Total editable -->
                    <div style="background:#e8f0fe;padding:12px 20px;display:flex;align-items:center;justify-content:flex-end;gap:12px;flex-wrap:wrap;">
                        <strong>TOTAL <?php echo htmlspecialchars($empresa_actual); ?>:</strong>
                        <?php if ($es_manual): ?>
                        <span style="font-size:11px;color:#999;text-decoration:line-through;">$ <?php echo number_format($total_calculado, 0, ',', '.'); ?></span>
                        <span style="color:#e65100;">➜</span>
                        <?php endif; ?>
                        <span style="font-weight:700;font-size:14px;color:#1a73e8;">$ <?php echo number_format($total_efectivo, 0, ',', '.'); ?></span>
                        <form method="POST" style="display:inline-flex;align-items:center;gap:6px;">
                            <input type="hidden" name="action" value="guardar_total_manual">
                            <input type="hidden" name="total_key" value="<?php echo htmlspecialchars($key_tabla); ?>">
                            <input type="number" name="total_valor" value="<?php echo $total_efectivo; ?>" step="1" style="width:110px;padding:4px 8px;border:1px solid #1a73e8;border-radius:6px;text-align:right;font-weight:600;font-size:12px;">
                            <button type="submit" class="btn-guardar-total">💾</button>
                        </form>
                    </div>
                </div>
                
                <script>
                (function() {
                    const empresaId = '<?php echo $empresa_id; ?>';
                    const checkboxes = document.querySelectorAll(`.fila-check-${empresaId}`);
                    
                    let isDragging = false;
                    let lastToggledIndex = -1;
                    
                    const toggleCheckbox = (checkbox, isEntering) => {
                        if (isEntering && checkbox) {
                            if (lastToggledIndex !== -1) {
                                const currentIndex = Array.from(checkboxes).indexOf(checkbox);
                                const start = Math.min(lastToggledIndex, currentIndex);
                                const end = Math.max(lastToggledIndex, currentIndex);
                                for (let i = start; i <= end; i++) {
                                    if (i !== lastToggledIndex) {
                                        checkboxes[i].checked = checkbox.checked;
                                    }
                                }
                            }
                            lastToggledIndex = Array.from(checkboxes).indexOf(checkbox);
                        }
                    };
                    
                    checkboxes.forEach(checkbox => {
                        checkbox.addEventListener('mousedown', (e) => {
                            isDragging = true;
                            lastToggledIndex = Array.from(checkboxes).indexOf(checkbox);
                            checkbox.checked = !checkbox.checked;
                            updateSelectAllCheckbox(empresaId);
                            actualizarBotonMover(empresaId);
                        });
                        
                        checkbox.addEventListener('mouseenter', () => {
                            if (isDragging) {
                                const currentIndex = Array.from(checkboxes).indexOf(checkbox);
                                if (currentIndex !== lastToggledIndex) {
                                    checkbox.checked = !checkbox.checked;
                                    toggleCheckbox(checkbox, true);
                                    updateSelectAllCheckbox(empresaId);
                                    actualizarBotonMover(empresaId);
                                }
                            }
                        });
                    });
                    
                    document.addEventListener('mouseup', () => {
                        isDragging = false;
                        lastToggledIndex = -1;
                    });
                    
                    let lastChecked = null;
                    checkboxes.forEach(checkbox => {
                        checkbox.addEventListener('click', function(e) {
                            if (e.shiftKey && lastChecked !== null) {
                                const checkboxesArray = Array.from(checkboxes);
                                const currentIndex = checkboxesArray.indexOf(this);
                                const lastIndex = checkboxesArray.indexOf(lastChecked);
                                const start = Math.min(currentIndex, lastIndex);
                                const end = Math.max(currentIndex, lastIndex);
                                for (let i = start; i <= end; i++) {
                                    checkboxesArray[i].checked = this.checked;
                                }
                                updateSelectAllCheckbox(empresaId);
                                actualizarBotonMover(empresaId);
                            }
                            lastChecked = this;
                        });
                    });
                    
                    function updateSelectAllCheckbox(empId) {
                        const selectAll = document.getElementById(`select-all-${empId}`);
                        if (!selectAll) return;
                        const cbs = document.querySelectorAll(`.fila-check-${empId}`);
                        const todosChecked = Array.from(cbs).every(cb => cb.checked);
                        const algunosChecked = Array.from(cbs).some(cb => cb.checked);
                        if (todosChecked) {
                            selectAll.checked = true;
                            selectAll.indeterminate = false;
                        } else if (algunosChecked) {
                            selectAll.checked = false;
                            selectAll.indeterminate = true;
                        } else {
                            selectAll.checked = false;
                            selectAll.indeterminate = false;
                        }
                    }
                    
                    window.updateSelectAllCheckbox = updateSelectAllCheckbox;
                    window.actualizarBotonMover = function(empId) {
                        const cbs = document.querySelectorAll(`.fila-check-${empId}`);
                        const checkeados = Array.from(cbs).filter(cb => cb.checked);
                        const btn = document.getElementById(`btn-mover-${empId}`);
                        if (btn) {
                            btn.disabled = checkeados.length === 0;
                            if (checkeados.length > 0) {
                                btn.innerHTML = `➡️ Mover ${checkeados.length} seleccionado(s) a EXTRAS`;
                            } else {
                                btn.innerHTML = `➡️ Mover seleccionados a EXTRAS`;
                            }
                        }
                    };
                    
                    window.toggleSeleccionarTodos = function(checkbox, empId) {
                        const cbs = document.querySelectorAll(`.fila-check-${empId}`);
                        cbs.forEach(cb => cb.checked = checkbox.checked);
                        updateSelectAllCheckbox(empId);
                        window.actualizarBotonMover(empId);
                    };
                    
                    window.moverSeleccionados = function(empId) {
                        const cbs = document.querySelectorAll(`.fila-check-${empId}`);
                        const idsSeleccionados = Array.from(cbs).filter(cb => cb.checked).map(cb => cb.value);
                        if (idsSeleccionados.length === 0) return;
                        document.getElementById(`ids-${empId}`).value = idsSeleccionados.join(',');
                        document.getElementById(`form-${empId}`).submit();
                    };
                    
                    checkboxes.forEach(cb => {
                        cb.addEventListener('change', () => {
                            updateSelectAllCheckbox(empresaId);
                            window.actualizarBotonMover(empresaId);
                        });
                    });
                    window.actualizarBotonMover(empresaId);
                    
                    <?php if ($es_nazareth): ?>
                    window.seleccionarPorRuta = function(empId) {
                        const inputBusqueda = document.getElementById(`buscar_ruta_${empId}`);
                        const textoBusqueda = inputBusqueda.value.trim().toLowerCase();
                        if (textoBusqueda === "") { alert("Escribe una palabra para buscar en las rutas"); return; }
                        const filas = document.querySelectorAll(`#tbody-${empId} tr`);
                        let seleccionadas = 0;
                        filas.forEach(fila => {
                            const celdaRuta = fila.querySelector('.ruta-cell');
                            if (celdaRuta) {
                                const ruta = celdaRuta.textContent.toLowerCase();
                                const checkbox = fila.querySelector(`.fila-check-${empId}`);
                                if (checkbox && ruta.includes(textoBusqueda)) {
                                    checkbox.checked = true;
                                    seleccionadas++;
                                }
                            }
                        });
                        updateSelectAllCheckbox(empId);
                        actualizarBotonMover(empId);
                        if (seleccionadas === 0) alert(`No se encontraron rutas que contengan "${textoBusqueda}"`);
                    };
                    <?php endif; ?>
                })();
                </script>
                <?php endif; ?>
                <?php
            }
        } else {
            ?>
            <div class="empresa-table">
                <div class="sin-datos">🔍 Selecciona al menos una empresa en los filtros para ver el informe</div>
            </div>
            <?php
        }
        $conn->close();
        ?>
        
        <!-- TABLA RESUMEN GENERAL -->
        <div class="resumen-table">
            <div class="resumen-header">📋 RESUMEN GENERAL</div>
            <div style="overflow-x: auto; max-width: 100%;">
                <table style="min-width: 600px;">
                    <thead>
                        <tr>
                            <th>Puesto de Salud</th>
                            <th style="text-align:right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empresas_seleccionadas as $empresa): 
                            if (!isset($datos_empresas[$empresa])) continue;
                            $data = $datos_empresas[$empresa];
                            $total_empresa = ($data['tipo'] === 'multiple') ? $data['total_general_efectivo'] : $data['total_efectivo'];
                        ?>
                        <tr>
                            <td>🏥 <?php echo htmlspecialchars($empresa); ?></td>
                            <td class="costo">$ <?php echo number_format($total_empresa, 0, ',', '.'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="subtotal-row">
                            <td style="text-align:right;"><strong>SUBTOTAL PUESTOS</strong></td>
                            <td class="costo"><strong>$ <?php echo number_format($total_puestos, 0, ',', '.'); ?></strong></td>
                        </tr>
                        <?php if (!empty($_SESSION['extras'])): ?>
                        <tr>
                            <td>⭐ EXTRAS</td>
                            <td class="costo">$ <?php echo number_format($total_extras, 0, ',', '.'); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="gran-total-row">
                            <td style="text-align:right;"><strong>TOTAL GENERAL</strong></td>
                            <td class="costo"><strong>$ <?php echo number_format($total_general, 0, ',', '.'); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        const todosLosConductores = <?php echo json_encode($todos_conductores); ?>;
        const conductoresSeleccionados = {};
        
        <?php foreach ($empresas_seleccionadas as $empresa): 
            $emp_id = 'emp_' . preg_replace('/[^a-zA-Z0-9]/', '_', $empresa);
            $nombres = isset($_SESSION['nombres_cambiados_empresa'][$empresa]) ? $_SESSION['nombres_cambiados_empresa'][$empresa] : array();
        ?>
        conductoresSeleccionados['<?php echo $emp_id; ?>'] = <?php echo json_encode($nombres); ?>;
        <?php endforeach; ?>
        
        function buscarConductores(empresaId) {
            const input = document.getElementById('input_conductor_' + empresaId);
            const lista = document.getElementById('autocomplete_' + empresaId);
            const texto = input.value.trim();
            
            if (texto === '') {
                lista.style.display = 'none';
                return;
            }
            
            const textoLower = texto.toLowerCase();
            const coincidencias = todosLosConductores.filter(conductor => 
                conductor.toLowerCase().includes(textoLower)
            );
            
            if (coincidencias.length === 0) {
                lista.innerHTML = '<div style="padding:10px;color:#999;">Sin coincidencias - Presiona Enter para agregar "' + texto + '"</div>';
                lista.style.display = 'block';
                return;
            }
            
            lista.innerHTML = coincidencias.map(conductor => 
                `<div onclick="seleccionarConductor('${empresaId}', '${conductor.replace(/'/g, "\\'")}')">${conductor}</div>`
            ).join('') + '<div style="padding:8px 15px;background:#f0f0f0;color:#666;font-size:11px;border-top:1px solid #ddd;">💡 O presiona Enter para agregar: <strong>' + texto + '</strong></div>';
            lista.style.display = 'block';
        }
        
        function manejarTeclaConductor(event, empresaId) {
            if (event.key === 'Enter') {
                event.preventDefault();
                const input = document.getElementById('input_conductor_' + empresaId);
                const texto = input.value.trim();
                
                if (texto === '') return;
                
                agregarConductorLibre(empresaId, texto);
                input.value = '';
                document.getElementById('autocomplete_' + empresaId).style.display = 'none';
            }
        }
        
        function agregarConductorLibre(empresaId, conductor) {
            if (!conductoresSeleccionados[empresaId]) {
                conductoresSeleccionados[empresaId] = [];
            }
            
            if (conductoresSeleccionados[empresaId].length >= 2) {
                alert('Máximo 2 conductores por tabla');
                return;
            }
            
            if (conductoresSeleccionados[empresaId].includes(conductor)) {
                alert('Este conductor ya está seleccionado');
                return;
            }
            
            conductoresSeleccionados[empresaId].push(conductor);
            actualizarTags(empresaId);
            actualizarBotonCambiar(empresaId);
        }
        
        function seleccionarConductor(empresaId, conductor) {
            if (!conductoresSeleccionados[empresaId]) {
                conductoresSeleccionados[empresaId] = [];
            }
            
            if (conductoresSeleccionados[empresaId].length >= 2) {
                alert('Máximo 2 conductores por tabla');
                return;
            }
            
            if (conductoresSeleccionados[empresaId].includes(conductor)) {
                alert('Este conductor ya está seleccionado');
                return;
            }
            
            conductoresSeleccionados[empresaId].push(conductor);
            actualizarTags(empresaId);
            actualizarBotonCambiar(empresaId);
            
            const input = document.getElementById('input_conductor_' + empresaId);
            input.value = '';
            document.getElementById('autocomplete_' + empresaId).style.display = 'none';
        }
        
        function removerConductor(empresaId, index) {
            if (conductoresSeleccionados[empresaId]) {
                conductoresSeleccionados[empresaId].splice(index, 1);
                actualizarTags(empresaId);
                actualizarBotonCambiar(empresaId);
            }
        }
        
        function actualizarTags(empresaId) {
            const tagsContainer = document.getElementById('tags_' + empresaId);
            if (!tagsContainer) return;
            
            const conductores = conductoresSeleccionados[empresaId] || [];
            
            tagsContainer.innerHTML = conductores.map((conductor, idx) => 
                `<span class="tag-conductor">
                    ${conductor}
                    <span class="remove-tag" onclick="removerConductor('${empresaId}', ${idx})">✕</span>
                </span>`
            ).join('');
        }
        
        function actualizarBotonCambiar(empresaId) {
            const btn = document.getElementById('btn_cambiar_' + empresaId);
            if (!btn) return;
            
            const conductores = conductoresSeleccionados[empresaId] || [];
            btn.disabled = conductores.length === 0;
        }
        
        function cambiarConductores(empresaId, empresaNombre) {
            const conductores = conductoresSeleccionados[empresaId] || [];
            
            if (conductores.length === 0) {
                alert('Selecciona al menos un conductor');
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'cambiar_conductores';
            form.appendChild(actionInput);
            
            const empresaInput = document.createElement('input');
            empresaInput.type = 'hidden';
            empresaInput.name = 'empresa_cambio';
            empresaInput.value = empresaNombre;
            form.appendChild(empresaInput);
            
            conductores.forEach((conductor) => {
                const nombreInput = document.createElement('input');
                nombreInput.type = 'hidden';
                nombreInput.name = 'nombres_conductores[]';
                nombreInput.value = conductor;
                form.appendChild(nombreInput);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
        
        function seleccionarTodas(seleccionar) {
            const checkboxes = document.querySelectorAll('#empresasGrid input[type="checkbox"]');
            checkboxes.forEach(cb => cb.checked = seleccionar);
            document.getElementById('filtroForm').submit();
        }
        
        document.querySelectorAll('#empresasGrid input[type="checkbox"]').forEach(cb => {
            cb.addEventListener('change', () => document.getElementById('filtroForm').submit());
        });
        
        function irATabla(empresa) {
            const idTabla = 'tabla_' + empresa.replace(/[^a-zA-Z0-9]/g, '_');
            const elemento = document.getElementById(idTabla);
            if (elemento) {
                elemento.scrollIntoView({ behavior: 'smooth', block: 'start' });
                elemento.style.transition = 'box-shadow 0.3s';
                elemento.style.boxShadow = '0 0 0 3px #ff9800, 0 4px 12px rgba(0,0,0,0.15)';
                setTimeout(() => {
                    elemento.style.boxShadow = '';
                }, 2000);
            }
        }
        
        document.addEventListener('click', function(e) {
            const autocompleteLists = document.querySelectorAll('.autocomplete-list');
            autocompleteLists.forEach(lista => {
                const wrapper = lista.parentElement;
                if (!wrapper.contains(e.target)) {
                    lista.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>