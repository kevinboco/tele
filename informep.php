<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }
$conn->set_charset('utf8mb4');

if (!isset($_SESSION['extras'])) { $_SESSION['extras'] = array(); }
if (!isset($_SESSION['nombres_cambiados'])) { $_SESSION['nombres_cambiados'] = array(); }
if (!isset($_SESSION['totales_manuales'])) { $_SESSION['totales_manuales'] = array(); }
if (!isset($_SESSION['tablas_personalizadas'])) { $_SESSION['tablas_personalizadas'] = array(); }
if (!isset($_SESSION['ids_movidos'])) { $_SESSION['ids_movidos'] = array(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'mover_viajes' && isset($_POST['ids_seleccionados'])) {
        $empresa_origen = $_POST['empresa_origen'];
        $destino = $_POST['destino'];
        $ids = explode(',', $_POST['ids_seleccionados']);
        
        foreach ($ids as $id) {
            $id_original = $id;
            
            // Si el origen es una tabla personalizada
            if (strpos($empresa_origen, '__personal__') === 0) {
                $tabla_origen_nombre = str_replace('__personal__', '', $empresa_origen);
                foreach ($_SESSION['tablas_personalizadas'] as $tidx => &$tabla) {
                    if ($tabla['nombre'] === $tabla_origen_nombre) {
                        foreach ($tabla['filas'] as $fidx => $fila) {
                            if ($fila['id'] === $id) {
                                $fila['empresa_origen'] = $tabla_origen_nombre;
                                $fila['costo'] = floatval($fila['valor'] ?? $fila['costo']);
                                
                                if ($destino === '__extras__') {
                                    $_SESSION['extras'][] = $fila;
                                } elseif (strpos($destino, '__personal__') === 0) {
                                    $tabla_destino_nombre = str_replace('__personal__', '', $destino);
                                    foreach ($_SESSION['tablas_personalizadas'] as &$td) {
                                        if ($td['nombre'] === $tabla_destino_nombre) {
                                            $td['filas'][] = $fila;
                                            break;
                                        }
                                    }
                                }
                                // ELIMINAR de la tabla origen
                                array_splice($_SESSION['tablas_personalizadas'][$tidx]['filas'], $fidx, 1);
                                break 2;
                            }
                        }
                        break;
                    }
                }
            }
            // Si el origen es EXTRAS
            elseif ($empresa_origen === '__extras__') {
                foreach ($_SESSION['extras'] as $eidx => $extra) {
                    if ($extra['id'] === $id) {
                        if (strpos($destino, '__personal__') === 0) {
                            $tabla_destino_nombre = str_replace('__personal__', '', $destino);
                            $extra['empresa_origen'] = 'EXTRAS';
                            foreach ($_SESSION['tablas_personalizadas'] as &$td) {
                                if ($td['nombre'] === $tabla_destino_nombre) {
                                    $td['filas'][] = $extra;
                                    break;
                                }
                            }
                        }
                        // ELIMINAR de extras
                        array_splice($_SESSION['extras'], $eidx, 1);
                        break;
                    }
                }
            }
            // Origen es una empresa normal
            else {
                $sql = "SELECT v.*, rc.clasificacion 
                        FROM viajes v
                        LEFT JOIN ruta_clasificacion rc ON v.ruta COLLATE utf8mb4_general_ci = rc.ruta COLLATE utf8mb4_general_ci 
                            AND v.tipo_vehiculo COLLATE utf8mb4_general_ci = rc.tipo_vehiculo COLLATE utf8mb4_general_ci
                        WHERE v.id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", intval($id));
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $clasificacion = $row['clasificacion'];
                    $costo = obtener_tarifa($clasificacion, $row['tipo_vehiculo'], $row['empresa'], $conn);
                    $row['costo'] = $costo;
                    $row['valor'] = $costo;
                    if (isset($_SESSION['nombres_cambiados'][intval($id)])) {
                        $row['nombre'] = $_SESSION['nombres_cambiados'][intval($id)];
                    }
                    $row['empresa_origen'] = $empresa_origen;
                    
                    if ($destino === '__extras__') {
                        $_SESSION['extras'][] = $row;
                    } elseif (strpos($destino, '__personal__') === 0) {
                        $tabla_destino_nombre = str_replace('__personal__', '', $destino);
                        foreach ($_SESSION['tablas_personalizadas'] as &$td) {
                            if ($td['nombre'] === $tabla_destino_nombre) {
                                $td['filas'][] = $row;
                                break;
                            }
                        }
                    }
                    
                    // MARCAR como movido para que no aparezca en la tabla original
                    $_SESSION['ids_movidos'][] = intval($id);
                }
                $stmt->close();
            }
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
            // Devolver el ID a disponibles
            $id_devuelto = $_SESSION['extras'][$index]['id'];
            $_SESSION['ids_movidos'] = array_filter($_SESSION['ids_movidos'], function($v) use ($id_devuelto) { return $v != $id_devuelto; });
            array_splice($_SESSION['extras'], $index, 1);
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
        exit;
    }
    
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
    
    if (isset($_POST['action']) && $_POST['action'] === 'guardar_total_manual') {
        $key = $_POST['total_key'];
        $valor = floatval(str_replace(['.', ','], ['', '.'], $_POST['total_valor']));
        $_SESSION['totales_manuales'][$key] = $valor;
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'guardar_total_extras') {
        $_SESSION['totales_manuales']['__extras__'] = floatval(str_replace(['.', ','], ['', '.'], $_POST['total_extras_valor']));
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'guardar_total_personal') {
        $key = $_POST['total_key'];
        $_SESSION['totales_manuales'][$key] = floatval(str_replace(['.', ','], ['', '.'], $_POST['total_valor']));
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'crear_tabla_personalizada') {
        $nombre = trim($_POST['nombre_tabla']);
        if (!empty($nombre)) {
            $existe = false;
            foreach ($_SESSION['tablas_personalizadas'] as $tabla) {
                if ($tabla['nombre'] === $nombre) { $existe = true; break; }
            }
            if (!$existe) {
                $_SESSION['tablas_personalizadas'][] = array(
                    'nombre' => $nombre,
                    'filas' => array(),
                    'next_id' => 1
                );
            }
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'agregar_fila_personalizada') {
        $tabla_nombre = $_POST['tabla_nombre'];
        foreach ($_SESSION['tablas_personalizadas'] as &$tabla) {
            if ($tabla['nombre'] === $tabla_nombre) {
                $nueva_fila = array(
                    'id' => 'p_' . $tabla['next_id'],
                    'fecha' => $_POST['fecha'],
                    'nombre' => $_POST['nombre'],
                    'cedula' => $_POST['cedula'],
                    'ruta' => $_POST['ruta'],
                    'tipo_vehiculo' => $_POST['tipo_vehiculo'],
                    'clasificacion' => 'manual',
                    'costo' => floatval(str_replace(['.', ','], ['', '.'], $_POST['valor'])),
                    'valor' => floatval(str_replace(['.', ','], ['', '.'], $_POST['valor'])),
                    'empresa_origen' => $tabla_nombre
                );
                $tabla['filas'][] = $nueva_fila;
                $tabla['next_id']++;
                break;
            }
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'eliminar_fila_personalizada') {
        $tabla_nombre = $_POST['tabla_nombre'];
        $fila_id = $_POST['fila_id'];
        foreach ($_SESSION['tablas_personalizadas'] as $tidx => &$tabla) {
            if ($tabla['nombre'] === $tabla_nombre) {
                foreach ($tabla['filas'] as $fidx => $fila) {
                    if ($fila['id'] === $fila_id) {
                        // Si la fila vino de la BD, devolver el ID
                        if (is_numeric($fila_id)) {
                            $_SESSION['ids_movidos'] = array_filter($_SESSION['ids_movidos'], function($v) use ($fila_id) { return $v != intval($fila_id); });
                        }
                        array_splice($_SESSION['tablas_personalizadas'][$tidx]['filas'], $fidx, 1);
                        break 2;
                    }
                }
                break;
            }
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'eliminar_tabla_personalizada') {
        $tabla_nombre = $_POST['tabla_nombre'];
        foreach ($_SESSION['tablas_personalizadas'] as $tidx => $tabla) {
            if ($tabla['nombre'] === $tabla_nombre) {
                // Devolver todos los IDs de BD a disponibles
                foreach ($tabla['filas'] as $fila) {
                    if (is_numeric($fila['id'])) {
                        $_SESSION['ids_movidos'] = array_filter($_SESSION['ids_movidos'], function($v) use ($fila) { return $v != intval($fila['id']); });
                    }
                }
                array_splice($_SESSION['tablas_personalizadas'], $tidx, 1);
                break;
            }
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'restaurar_nombres') {
        $_SESSION['nombres_cambiados'] = array();
        $_SESSION['nombres_cambiados_empresa'] = array();
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'restaurar_totales') {
        $_SESSION['totales_manuales'] = array();
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'restaurar_movidos') {
        $_SESSION['ids_movidos'] = array();
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
        exit;
    }
}

$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';
$empresas_seleccionadas = isset($_GET['empresas']) ? $_GET['empresas'] : array();
$PRESUPUESTO_BASE = 13000000;

function obtener_tarifa($clasificacion, $tipo_vehiculo, $empresa, $conn) {
    if (empty($clasificacion) || empty($empresa)) { return 0; }
    $sql = "SELECT completo, medio, extra, carrotanque, siapana, riohacha_completo, riohacha_medio, nazareth_siapana_maicao, nazareth_siapana_flor_de_la_guajira FROM tarifas WHERE empresa = ? AND tipo_vehiculo = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    $stmt->bind_param("ss", $empresa, $tipo_vehiculo);
    $stmt->execute();
    $result = $stmt->get_result();
    $tarifa = $result->fetch_assoc();
    $stmt->close();
    if (!$tarifa) return 0;
    switch($clasificacion) {
        case 'completo': return floatval($tarifa['completo'] ?? 0);
        case 'medio': return floatval($tarifa['medio'] ?? 0);
        case 'extra': return floatval($tarifa['extra'] ?? 0);
        case 'carrotanque': return floatval($tarifa['carrotanque'] ?? 0);
        case 'siapana': return floatval($tarifa['siapana'] ?? 0);
        case 'riohacha_completo': return floatval($tarifa['riohacha_completo'] ?? 0);
        case 'riohacha_medio': return floatval($tarifa['riohacha_medio'] ?? 0);
        case 'nazareth_siapana_maicao': return floatval($tarifa['nazareth_siapana_maicao'] ?? 0);
        case 'nazareth_siapana_flor_de_la_guajira': return floatval($tarifa['nazareth_siapana_flor_de_la_guajira'] ?? 0);
        default: return 0;
    }
}

$empresas_disponibles = array();
$sql_emp = "SELECT DISTINCT empresa FROM viajes WHERE empresa LIKE 'P.%' ORDER BY empresa";
$res_emp = $conn->query($sql_emp);
if ($res_emp) { while ($row = $res_emp->fetch_assoc()) { $empresas_disponibles[] = $row['empresa']; } }

$todos_conductores = array();
$sql_cond = "SELECT DISTINCT nombre FROM viajes WHERE nombre IS NOT NULL AND nombre != '' ORDER BY nombre";
$res_cond = $conn->query($sql_cond);
if ($res_cond) { while ($row = $res_cond->fetch_assoc()) { $todos_conductores[] = $row['nombre']; } }

function calcular_acumulado_extras($extras) {
    $acumulado = 0; $resultados = array();
    foreach ($extras as $index => $extra) { $acumulado += $extra['costo']; $resultados[] = array('index' => $index, 'data' => $extra, 'acumulado' => $acumulado); }
    return $resultados;
}

function obtenerTotalEfectivo($key, $total_calculado) {
    return isset($_SESSION['totales_manuales'][$key]) ? $_SESSION['totales_manuales'][$key] : $total_calculado;
}

function obtenerDatosParaExportar($conn, $fecha_desde, $fecha_hasta, $empresas_seleccionadas, $PRESUPUESTO_BASE) {
    $datos = array(); $alertas = array();
    $ids_excluir = array_merge(
        array_column($_SESSION['extras'] ?? array(), 'id'),
        $_SESSION['ids_movidos'] ?? array()
    );
    // Tambien excluir IDs que estan en tablas personalizadas
    foreach ($_SESSION['tablas_personalizadas'] ?? array() as $tp) {
        foreach ($tp['filas'] as $fila) {
            if (is_numeric($fila['id'])) {
                $ids_excluir[] = intval($fila['id']);
            }
        }
    }
    $ids_excluir = array_unique($ids_excluir);
    
    foreach ($empresas_seleccionadas as $empresa_actual) {
        $sql = "SELECT v.id, v.nombre, v.cedula, v.fecha, v.ruta, v.tipo_vehiculo, v.empresa, rc.clasificacion FROM viajes v LEFT JOIN ruta_clasificacion rc ON v.ruta COLLATE utf8mb4_general_ci = rc.ruta COLLATE utf8mb4_general_ci AND v.tipo_vehiculo COLLATE utf8mb4_general_ci = rc.tipo_vehiculo COLLATE utf8mb4_general_ci WHERE v.empresa = ?";
        $params = array($empresa_actual); $types = "s";
        if (!empty($fecha_desde)) { $sql .= " AND v.fecha >= ?"; $params[] = $fecha_desde; $types .= "s"; }
        if (!empty($fecha_hasta)) { $sql .= " AND v.fecha <= ?"; $params[] = $fecha_hasta; $types .= "s"; }
        if (!empty($ids_excluir)) { $placeholders = implode(',', array_fill(0, count($ids_excluir), '?')); $sql .= " AND v.id NOT IN ($placeholders)"; foreach ($ids_excluir as $id) { $params[] = $id; $types .= "i"; } }
        $sql .= " ORDER BY v.fecha ASC, v.id ASC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params); $stmt->execute(); $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $all_rows = array(); $acumulado_total = 0;
                $nombres_cambiados = isset($_SESSION['nombres_cambiados_empresa'][$empresa_actual]) ? $_SESSION['nombres_cambiados_empresa'][$empresa_actual] : array();
                while ($row = $result->fetch_assoc()) {
                    $clasificacion = $row['clasificacion']; $costo = obtener_tarifa($clasificacion, $row['tipo_vehiculo'], $row['empresa'], $conn); $acumulado_total += $costo;
                    $all_rows[] = array('id' => $row['id'], 'fecha' => $row['fecha'], 'nombre' => $row['nombre'], 'nombre_original' => $row['nombre'], 'cedula' => $row['cedula'], 'ruta' => $row['ruta'], 'tipo_vehiculo' => $row['tipo_vehiculo'], 'clasificacion' => $clasificacion, 'costo' => $costo);
                }
                if (!empty($nombres_cambiados)) {
                    $tablas_conductores = array();
                    foreach ($nombres_cambiados as $idx => $nombre_conductor) {
                        $key_tabla = $empresa_actual . '||' . $nombre_conductor;
                        $tablas_conductores[$idx] = array('nombre_conductor' => $nombre_conductor, 'key' => $key_tabla, 'rows' => array(), 'total_calculado' => 0, 'total' => 0);
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
                    foreach ($tablas_conductores as &$tabla) { $tabla['total'] = obtenerTotalEfectivo($tabla['key'], $tabla['total_calculado']); }
                    $total_general_efectivo = array_sum(array_column($tablas_conductores, 'total'));
                    $datos[$empresa_actual] = array('tipo' => 'multiple', 'tablas' => $tablas_conductores, 'total_general' => $acumulado_total, 'total_general_efectivo' => $total_general_efectivo, 'key' => $empresa_actual);
                } else {
                    $rows_data = array(); $acumulado = 0;
                    foreach ($all_rows as $row) { $acumulado += $row['costo']; $rows_data[] = array_merge($row, array('acumulado' => $acumulado)); }
                    $key_tabla = $empresa_actual . '||_simple_';
                    $datos[$empresa_actual] = array('tipo' => 'simple', 'key' => $key_tabla, 'rows' => $rows_data, 'total' => $acumulado, 'total_efectivo' => obtenerTotalEfectivo($key_tabla, $acumulado));
                }
            } else { $datos[$empresa_actual] = array('tipo' => 'simple', 'key' => $empresa_actual . '||_simple_', 'rows' => array(), 'total' => 0, 'total_efectivo' => 0); }
            $stmt->close();
        }
    }
    return array('datos' => $datos, 'alertas' => $alertas);
}

$extras_con_acumulado = calcular_acumulado_extras($_SESSION['extras']);
$total_extras_calculado = array_sum(array_column($_SESSION['extras'], 'costo'));
$total_extras = obtenerTotalEfectivo('__extras__', $total_extras_calculado);

$resultado = obtenerDatosParaExportar($conn, $fecha_desde, $fecha_hasta, $empresas_seleccionadas, $PRESUPUESTO_BASE);
$datos_empresas = $resultado['datos'];
$alertas = $resultado['alertas'];

// Totales de tablas personalizadas
$tablas_personalizadas_data = array();
foreach ($_SESSION['tablas_personalizadas'] as $tp) {
    $total_calc = array_sum(array_column($tp['filas'], 'costo'));
    $key = '__personal__' . $tp['nombre'];
    $total_ef = obtenerTotalEfectivo($key, $total_calc);
    $tablas_personalizadas_data[] = array(
        'nombre' => $tp['nombre'],
        'key' => $key,
        'filas' => $tp['filas'],
        'total_calculado' => $total_calc,
        'total' => $total_ef
    );
}

$total_puestos = 0; $resumen_empresas = array();
foreach ($empresas_seleccionadas as $empresa) {
    if (!isset($datos_empresas[$empresa])) continue;
    $data = $datos_empresas[$empresa];
    $total_empresa = ($data['tipo'] === 'multiple') ? $data['total_general_efectivo'] : $data['total_efectivo'];
    $resumen_empresas[$empresa] = $total_empresa;
    $total_puestos += $total_empresa;
}
foreach ($tablas_personalizadas_data as $tp) {
    $resumen_empresas['📋 ' . $tp['nombre']] = $tp['total'];
    $total_puestos += $tp['total'];
}
$total_general = $total_puestos + $total_extras;

// Destinos disponibles para mover
$destinos_disponibles = array();
$destinos_disponibles['__extras__'] = '⭐ EXTRAS';
foreach ($empresas_seleccionadas as $empresa) {
    if (!isset($datos_empresas[$empresa])) continue;
    $data = $datos_empresas[$empresa];
    if ($data['tipo'] === 'multiple') {
        foreach ($data['tablas'] as $tabla) {
            $destinos_disponibles[$empresa . '||' . $tabla['nombre_conductor']] = $empresa . ' - ' . $tabla['nombre_conductor'];
        }
    } else {
        $destinos_disponibles[$empresa] = $empresa;
    }
}
foreach ($_SESSION['tablas_personalizadas'] as $tp) {
    $destinos_disponibles['__personal__' . $tp['nombre']] = '📋 ' . $tp['nombre'];
}

if (isset($_POST['export_word'])) {
    header("Content-Type: application/msword");
    header("Content-Disposition: attachment; filename=informe_viajes_" . date('Y-m-d') . ".doc");
    header("Cache-Control: no-cache, must-revalidate");
    ?><!DOCTYPE html><html><head><meta charset="UTF-8"><title>Informe de Viajes</title>
    <style>body{font-family:Arial;margin:20px;font-size:11pt}h1{color:#1a73e8;font-size:18pt}h2{background:#1a73e8;color:white;padding:8px 12px;font-size:14pt;margin-top:20px}h3{background:#455a64;color:white;padding:6px 10px;font-size:12pt;margin-top:15px}.info-filtros{margin-bottom:20px;padding:10px;background:#f0f2f5}table{width:100%;border-collapse:collapse;margin-bottom:20px}th{background:#f8f9fa;border:1px solid #ccc;padding:8px;font-weight:bold}td{border:1px solid #ccc;padding:6px 8px}.total-row{background:#e8f0fe;font-weight:bold}.costo{text-align:right}.extras-table{margin-top:30px;border:2px solid #ff9800}.extras-title{background:#ff9800;color:white;padding:8px 12px;font-size:14pt;font-weight:bold}.resumen-table{margin-top:30px;border:2px solid #1a73e8}.resumen-title{background:#1a73e8;color:white;padding:8px 12px;font-size:14pt;font-weight:bold}.subtotal-row{background:#e3f2fd;font-weight:bold}.gran-total-row{background:#1a73e8;color:white;font-weight:bold;font-size:12pt}</style></head><body>
    <h1>Informe de Viajes</h1><div class="info-filtros"><strong>Periodo:</strong> <?php echo $fecha_desde ? date('d/m/Y', strtotime($fecha_desde)) : 'Todo'; ?> - <?php echo $fecha_hasta ? date('d/m/Y', strtotime($fecha_hasta)) : 'Todo'; ?><br><strong>Empresas:</strong> <?php echo implode(', ', $empresas_seleccionadas); ?></div>
    <?php foreach ($datos_empresas as $empresa => $data): ?>
        <?php if ($data['tipo'] === 'multiple'): ?>
            <?php foreach ($data['tablas'] as $tabla): if (empty($tabla['rows'])) continue; ?>
                <h3><?php echo htmlspecialchars($empresa); ?> - <?php echo htmlspecialchars($tabla['nombre_conductor']); ?></h3>
                <table><thead><tr><th>Fecha</th><th>Conductor</th><th>Ruta</th><th>Valor</th></tr></thead><tbody>
                    <?php foreach($tabla['rows'] as $row): ?><tr><td><?php echo date('d/m/Y', strtotime($row['fecha'])); ?></td><td><?php echo htmlspecialchars($row['nombre']); ?></td><td><?php echo htmlspecialchars($row['ruta']); ?></td><td class="costo">$ <?php echo number_format($row['costo'], 0, ',', '.'); ?></td></tr><?php endforeach; ?>
                    <tr class="total-row"><td colspan="3" style="text-align:right;">TOTAL:</td><td class="costo">$ <?php echo number_format($tabla['total'], 0, ',', '.'); ?></td></tr>
                </tbody></table>
            <?php endforeach; ?>
        <?php else: ?>
            <?php if (empty($data['rows'])) continue; ?>
            <h2><?php echo htmlspecialchars($empresa); ?></h2>
            <table><thead><tr><th>Fecha</th><th>Conductor</th><th>Ruta</th><th>Valor</th></tr></thead><tbody>
                <?php foreach($data['rows'] as $row): ?><tr><td><?php echo date('d/m/Y', strtotime($row['fecha'])); ?></td><td><?php echo htmlspecialchars($row['nombre']); ?></td><td><?php echo htmlspecialchars($row['ruta']); ?></td><td class="costo">$ <?php echo number_format($row['costo'], 0, ',', '.'); ?></td></tr><?php endforeach; ?>
                <tr class="total-row"><td colspan="3" style="text-align:right;">TOTAL:</td><td class="costo">$ <?php echo number_format($data['total_efectivo'], 0, ',', '.'); ?></td></tr>
            </tbody></table>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php foreach ($tablas_personalizadas_data as $tp): if (empty($tp['filas'])) continue; ?>
        <h2> <?php echo htmlspecialchars($tp['nombre']); ?></h2>
        <table><thead><tr><th>Fecha</th><th>Conductor</th><th>Ruta</th><th>Valor</th></tr></thead><tbody>
            <?php foreach($tp['filas'] as $fila): ?><tr><td><?php echo date('d/m/Y', strtotime($fila['fecha'])); ?></td><td><?php echo htmlspecialchars($fila['nombre']); ?></td><td><?php echo htmlspecialchars($fila['ruta']); ?></td><td class="costo">$ <?php echo number_format($fila['costo'], 0, ',', '.'); ?></td></tr><?php endforeach; ?>
            <tr class="total-row"><td colspan="3" style="text-align:right;">TOTAL:</td><td class="costo">$ <?php echo number_format($tp['total'], 0, ',', '.'); ?></td></tr>
        </tbody></table>
    <?php endforeach; ?>
    <?php if (!empty($_SESSION['extras'])): ?>
        <div class="extras-table"><div class="extras-title"> EXTRAS </div>
        <table><thead><tr><th>Fecha</th><th>Conductor</th><th>Ruta</th><th>Valor</th></tr></thead><tbody>
            <?php foreach($extras_con_acumulado as $ex): ?><tr><td><?php echo date('d/m/Y', strtotime($ex['data']['fecha'])); ?></td><td><?php echo htmlspecialchars($ex['data']['nombre']); ?></td><td><?php echo htmlspecialchars($ex['data']['ruta']); ?></td><td class="costo">$ <?php echo number_format($ex['data']['costo'], 0, ',', '.'); ?></td></tr><?php endforeach; ?>
            <tr class="total-row"><td colspan="3" style="text-align:right;">TOTAL EXTRAS:</td><td class="costo">$ <?php echo number_format($total_extras, 0, ',', '.'); ?></td></tr>
        </tbody></table></div>
    <?php endif; ?>
    <div class="resumen-table"><div class="resumen-title">RESUMEN GENERAL</div>
    <table><thead><tr><th>Puesto de Salud / Tabla</th><th style="text-align:right;">Total</th></tr></thead><tbody>
        <?php foreach ($resumen_empresas as $nombre => $total): ?><tr><td><?php echo htmlspecialchars($nombre); ?></td><td class="costo">$ <?php echo number_format($total, 0, ',', '.'); ?></td></tr><?php endforeach; ?>
        <tr class="subtotal-row"><td style="text-align:right;"><strong>SUBTOTAL</strong></td><td class="costo"><strong>$ <?php echo number_format($total_puestos, 0, ',', '.'); ?></strong></td></tr>
        <?php if (!empty($_SESSION['extras'])): ?><tr><td> EXTRAS</td><td class="costo">$ <?php echo number_format($total_extras, 0, ',', '.'); ?></td></tr><?php endif; ?>
        <tr class="gran-total-row"><td style="text-align:right;"><strong>TOTAL GENERAL</strong></td><td class="costo"><strong>$ <?php echo number_format($total_general, 0, ',', '.'); ?></strong></td></tr>
    </tbody></table></div></body></html><?php exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Informe de Viajes</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#f0f2f5;padding:20px}.container{max-width:1600px;margin:0 auto}
        .header{background:linear-gradient(135deg,#1a73e8 0%,#0d47a1 100%);color:white;padding:20px 25px;border-radius:12px 12px 0 0;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px}
        .header h1{font-size:24px}.header p{opacity:0.9;font-size:14px}.btn-word{background:#2e7d32;color:white;border:none;padding:10px 25px;border-radius:8px;cursor:pointer;font-weight:600;font-size:14px}.btn-word:hover{background:#1b5e20}
        .btn-restaurar{background:#c62828;color:white;border:none;padding:10px 25px;border-radius:8px;cursor:pointer;font-weight:600;font-size:14px;margin-left:10px}.btn-restaurar:hover{background:#b71c1c}
        .btn-restaurar-totales{background:#6a1b9a;color:white;border:none;padding:10px 25px;border-radius:8px;cursor:pointer;font-weight:600;font-size:14px;margin-left:10px}.btn-restaurar-totales:hover{background:#4a148c}
        .btn-crear-tabla{background:#00897b;color:white;border:none;padding:10px 25px;border-radius:8px;cursor:pointer;font-weight:600;font-size:14px;margin-left:10px}.btn-crear-tabla:hover{background:#00695c}
        .alertas-container{margin-bottom:25px}.alerta-presupuesto{background:#ffebee;border-left:4px solid #f44336;border-radius:8px;padding:12px 20px;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:15px;box-shadow:0 2px 4px rgba(0,0,0,0.1)}
        .alerta-mensaje{display:flex;align-items:center;gap:12px}.alerta-icono{font-size:24px}.alerta-texto{font-size:14px;color:#333}.badge-exceso{display:inline-block;background:#f44336;color:white;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:bold;margin-left:10px}
        .btn-ir-tabla{background:#ff9800;color:white;border:none;padding:6px 16px;border-radius:20px;cursor:pointer;font-size:12px;font-weight:600}.btn-ir-tabla:hover{background:#e65100}
        .filtros-card{background:white;padding:20px 25px;border-radius:12px;margin-bottom:25px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
        .filtros-row{display:flex;flex-wrap:wrap;gap:20px;align-items:flex-end;margin-bottom:20px}.filtro-group{display:flex;flex-direction:column;gap:6px}.filtro-group label{font-size:12px;font-weight:600;color:#5f6368;text-transform:uppercase}
        .filtro-group input,.filtro-group select{padding:10px 15px;border:1px solid #dadce0;border-radius:8px;font-size:14px;min-width:200px}
        .btn-filtrar,.btn-limpiar{padding:10px 25px;border-radius:8px;cursor:pointer;font-weight:600;border:none}.btn-filtrar{background:#1a73e8;color:white}.btn-filtrar:hover{background:#1557b0}.btn-limpiar{background:#5f6368;color:white}
        .empresas-section{border-top:1px solid #e8eaed;padding-top:20px}.empresas-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;flex-wrap:wrap;gap:15px}
        .btn-group{display:flex;gap:10px}.btn-seleccion{background:#f1f3f4;border:none;padding:6px 16px;border-radius:20px;cursor:pointer;font-size:12px}.btn-seleccion.all{background:#34a853;color:white}.btn-seleccion.none{background:#ea4335;color:white}
        .empresas-grid{display:flex;flex-wrap:wrap;gap:12px}.empresa-checkbox{display:flex;align-items:center;gap:8px;background:#f8f9fa;padding:6px 14px;border-radius:25px;border:1px solid #dadce0;cursor:pointer;font-size:13px}
        .extras-table{background:linear-gradient(135deg,#fff8e7 0%,#fff3d6 100%);border-radius:12px;margin-bottom:30px;overflow-x:auto;box-shadow:0 4px 12px rgba(0,0,0,0.15);border:2px solid #ff9800}
        .extras-header{background:#ff9800;color:white;padding:15px 20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px}
        .btn-limpiar-extras{background:#e65100;color:white;border:none;padding:8px 20px;border-radius:8px;cursor:pointer;font-weight:600}
        .empresa-table{background:white;border-radius:12px;margin-bottom:30px;overflow-x:auto;box-shadow:0 2px 8px rgba(0,0,0,0.1);scroll-margin-top:100px}
        .table-header{background:#1a73e8;color:white;padding:15px 20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px}.table-header h2{font-size:18px}
        .table-header-conductor{background:#455a64;color:white;padding:12px 20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px}.table-header-conductor h3{font-size:16px}
        .table-header-personal{background:#00897b;color:white;padding:12px 20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px}.table-header-personal h3{font-size:16px}
        .acciones-header{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
        .btn-mover-extras{background:#ff9800;color:white;border:none;padding:8px 20px;border-radius:8px;cursor:pointer;font-weight:600}.btn-mover-extras:disabled{background:#ccc;cursor:not-allowed}
        .btn-eliminar-extra,.btn-eliminar-fila{background:#ea4335;color:white;border:none;padding:4px 12px;border-radius:6px;cursor:pointer;font-size:11px}
        .btn-agregar-fila{background:#00897b;color:white;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;font-size:11px;font-weight:600}
        .btn-eliminar-tabla{background:#c62828;color:white;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;font-size:11px;font-weight:600}
        .cambio-conductores-section{background:#e3f2fd;border:2px solid #1a73e8;border-radius:10px;padding:15px;margin-bottom:15px;display:flex;flex-wrap:wrap;gap:15px;align-items:flex-end}
        .cambio-conductores-section .filtro-group{flex:1;min-width:200px}
        .autocomplete-wrapper{position:relative;width:100%}.autocomplete-wrapper input{width:100%;padding:10px 15px;border:1px solid #dadce0;border-radius:8px;font-size:14px}
        .autocomplete-list{position:absolute;top:100%;left:0;right:0;background:white;border:1px solid #dadce0;border-top:none;border-radius:0 0 8px 8px;max-height:200px;overflow-y:auto;z-index:1000;display:none;box-shadow:0 4px 12px rgba(0,0,0,0.15)}
        .autocomplete-list div{padding:10px 15px;cursor:pointer;border-bottom:1px solid #f0f0f0}.autocomplete-list div:hover{background:#e3f2fd}
        .tags-conductores{display:flex;flex-wrap:wrap;gap:5px;margin-top:5px}
        .tag-conductor{display:inline-flex;align-items:center;gap:5px;background:#1a73e8;color:white;padding:4px 12px;border-radius:20px;font-size:12px}.tag-conductor .remove-tag{cursor:pointer;font-weight:bold;margin-left:5px}.tag-conductor .remove-tag:hover{color:#ffc107}
        .btn-cambiar-conductores{background:#1a73e8;color:white;border:none;padding:10px 25px;border-radius:8px;cursor:pointer;font-weight:600}.btn-cambiar-conductores:hover{background:#1557b0}.btn-cambiar-conductores:disabled{background:#90caf9;cursor:not-allowed}
        .busqueda-ruta{display:flex;gap:10px;align-items:center;background:rgba(255,255,255,0.2);padding:5px 15px;border-radius:25px}.busqueda-ruta input{padding:6px 12px;border:none;border-radius:20px;font-size:12px;width:180px;outline:none}.busqueda-ruta button{background:white;color:#1a73e8;border:none;padding:5px 12px;border-radius:20px;cursor:pointer;font-size:11px;font-weight:600}
        .input-hint{font-size:11px;color:#5f6368;margin-top:3px}
        table{width:100%;border-collapse:collapse;min-width:1000px}th{background:#f8f9fa;padding:12px 8px;text-align:left;font-weight:600;color:#5f6368;border-bottom:2px solid #dadce0;font-size:12px;white-space:nowrap}
        td{padding:10px 8px;border-bottom:1px solid #e8eaed;color:#202124;font-size:12px;vertical-align:middle}
        .checkbox-col{width:30px;text-align:center}.costo{font-weight:600;color:#1a73e8;text-align:right!important}.acumulado{font-weight:700;color:#34a853;text-align:right!important}
        .ruta-cell{max-width:220px;white-space:normal;word-break:break-word;line-height:1.4}
        .drag-instruction{font-size:11px;background:rgba(255,255,255,0.2);padding:5px 12px;border-radius:20px;display:inline-flex;align-items:center;gap:6px}
        .sin-datos{text-align:center;padding:40px;color:#5f6368}
        html{scroll-behavior:smooth}.btn-header-group{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
        .sub-table-wrapper{border:1px solid #e8eaed;border-radius:8px;margin-bottom:15px;overflow:hidden}
        .total-general-row{background:#1a73e8;color:white;font-weight:bold;padding:12px 20px;text-align:right;font-size:14px}
        .btn-guardar-total{background:#1a73e8;color:white;border:none;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:11px;font-weight:600}.btn-guardar-total:hover{background:#1557b0}
        .extras-total-editable{display:flex;align-items:center;gap:8px;justify-content:flex-end;padding:10px 15px;background:#ffe0b2}
        .extras-total-editable input{width:130px;padding:5px 10px;border:1px solid #ff9800;border-radius:6px;text-align:right;font-weight:600;font-size:13px}
        .resumen-table{background:white;border-radius:12px;margin-top:30px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.15);border:2px solid #1a73e8}
        .resumen-header{background:linear-gradient(135deg,#1a73e8 0%,#0d47a1 100%);color:white;padding:15px 25px;font-size:18px;font-weight:700}
        .resumen-table table{min-width:600px}.resumen-table th{font-size:13px;padding:14px 15px;background:#f0f4ff}.resumen-table td{font-size:13px;padding:12px 15px}
        .subtotal-row td{background:#e3f2fd;font-weight:bold;font-size:13px}.gran-total-row td{background:#1a73e8;color:white;font-weight:bold;font-size:14px;padding:14px 15px}
        .modal-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:2000;justify-content:center;align-items:center}
        .modal-content{background:white;border-radius:12px;padding:25px;width:90%;max-width:500px;box-shadow:0 8px 32px rgba(0,0,0,0.3)}
        .modal-content h3{margin-bottom:15px}.modal-content .filtro-group{margin-bottom:12px}.modal-content input,.modal-content select{width:100%;padding:8px 12px;border:1px solid #dadce0;border-radius:6px}
        .modal-buttons{display:flex;gap:10px;justify-content:flex-end;margin-top:15px}
        .btn-modal-primary{background:#1a73e8;color:white;border:none;padding:8px 20px;border-radius:6px;cursor:pointer;font-weight:600}.btn-modal-cancel{background:#ccc;color:#333;border:none;padding:8px 20px;border-radius:6px;cursor:pointer}
        select.destino-select{padding:6px 10px;border:1px solid #ff9800;border-radius:6px;font-size:11px;font-weight:600;background:white;max-width:180px}
    </style>
</head>
<body>
<div class="container">
<div class="header">
    <div><h1>Informe de Viajes por Puesto de Salud</h1><p>Arrastra el mouse sobre los checkboxes | Shift + Click para rango</p></div>
    <div class="btn-header-group">
        <form method="POST" style="display:inline"><input type="hidden" name="export_word" value="1"><button class="btn-word">Generar Word</button></form>
        <form method="POST" style="display:inline"><input type="hidden" name="action" value="restaurar_nombres"><button class="btn-restaurar">Restaurar Nombres</button></form>
        <form method="POST" style="display:inline"><input type="hidden" name="action" value="restaurar_totales"><button class="btn-restaurar-totales">Restaurar Totales</button></form>
        <form method="POST" style="display:inline"><input type="hidden" name="action" value="restaurar_movidos"><button class="btn-restaurar" style="background:#e65100">Restaurar Movidos</button></form>
        <button class="btn-crear-tabla" onclick="mostrarModalCrearTabla()">Nueva tabla personalizada</button>
    </div>
</div>

<?php if (!empty($alertas)): ?><div class="alertas-container"><?php foreach ($alertas as $alerta): ?>
    <div class="alerta-presupuesto"><div class="alerta-mensaje"><span class="alerta-icono">&#9888;</span><span class="alerta-texto"><strong><?php echo htmlspecialchars($alerta['empresa']); ?></strong> excede presupuesto <span class="badge-exceso">Total: $ <?php echo number_format($alerta['total'], 0, ',', '.'); ?></span></span></div><button class="btn-ir-tabla" onclick="irATabla('<?php echo htmlspecialchars($alerta['empresa']); ?>')">Ir</button></div>
<?php endforeach; ?></div><?php endif; ?>

<form method="GET" id="filtroForm"><div class="filtros-card">
    <div class="filtros-row">
        <div class="filtro-group"><label>Fecha desde</label><input type="date" name="fecha_desde" value="<?php echo htmlspecialchars($fecha_desde); ?>"></div>
        <div class="filtro-group"><label>Fecha hasta</label><input type="date" name="fecha_hasta" value="<?php echo htmlspecialchars($fecha_hasta); ?>"></div>
        <div class="filtro-group"><button type="submit" class="btn-filtrar">Filtrar</button></div>
        <div class="filtro-group"><a href="?" class="btn-limpiar">Limpiar</a></div>
    </div>
    <div class="empresas-section"><div class="empresas-header"><h3>Puestos de Salud (P.)</h3><div class="btn-group"><button type="button" class="btn-seleccion all" onclick="seleccionarTodas(true)">Todas</button><button type="button" class="btn-seleccion none" onclick="seleccionarTodas(false)">Ninguna</button></div></div>
    <div class="empresas-grid" id="empresasGrid"><?php foreach ($empresas_disponibles as $empresa): ?><label class="empresa-checkbox"><input type="checkbox" name="empresas[]" value="<?php echo htmlspecialchars($empresa); ?>" <?php echo (in_array($empresa, $empresas_seleccionadas) || (empty($empresas_seleccionadas) && empty($_GET))) ? 'checked' : ''; ?>> <?php echo htmlspecialchars($empresa); ?></label><?php endforeach; ?></div></div>
</div></form>

<?php if (!empty($_SESSION['extras'])): ?>
<div class="extras-table">
    <div class="extras-header"><h2>EXTRAS</h2><form method="POST" style="display:inline"><input type="hidden" name="action" value="limpiar_extras"><button class="btn-limpiar-extras">Limpiar todas</button></form></div>
    <div style="overflow-x:auto"><table style="min-width:1200px"><thead><tr><th>#</th><th><input type="checkbox" id="select-all-__extras__" onchange="toggleSeleccionarTodosExtras(this)"></th><th>Fecha</th><th>Conductor</th><th>Cedula</th><th>Ruta</th><th>Tipo</th><th>Origen</th><th>Clasificacion</th><th>Valor</th><th>Acumulado</th><th>Accion</th></tr></thead><tbody id="tbody-__extras__">
    <?php $idx_extra=0; foreach($extras_con_acumulado as $extra): $idx_extra++; ?>
        <tr><td style="text-align:center"><?php echo $idx_extra; ?></td><td class="checkbox-col"><input type="checkbox" class="fila-check-__extras__" value="<?php echo $extra['data']['id']; ?>"></td><td><?php echo date('d/m/Y', strtotime($extra['data']['fecha'])); ?></td><td><strong><?php echo htmlspecialchars($extra['data']['nombre'] ?? '-'); ?></strong></td><td><?php echo htmlspecialchars($extra['data']['cedula'] ?? '-'); ?></td><td class="ruta-cell"><?php echo htmlspecialchars($extra['data']['ruta'] ?? '-'); ?></td><td><?php echo htmlspecialchars($extra['data']['tipo_vehiculo'] ?? '-'); ?></td><td><?php echo htmlspecialchars($extra['data']['empresa_origen'] ?? $extra['data']['empresa'] ?? '-'); ?></td><td><?php echo htmlspecialchars($extra['data']['clasificacion'] ?? '-'); ?></td><td class="costo">$ <?php echo number_format($extra['data']['costo'], 0, ',', '.'); ?></td><td class="acumulado">$ <?php echo number_format($extra['acumulado'], 0, ',', '.'); ?></td><td><form method="POST" style="display:inline"><input type="hidden" name="action" value="eliminar_extra"><input type="hidden" name="extra_index" value="<?php echo $extra['index']; ?>"><button class="btn-eliminar-extra">X</button></form></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:10px 15px;background:#ffe0b2;flex-wrap:wrap">
        <select class="destino-select" id="destino___extras__"><?php foreach($destinos_disponibles as $dk=>$dv): if($dk=='__extras__') continue; ?><option value="<?php echo htmlspecialchars($dk); ?>"><?php echo htmlspecialchars($dv); ?></option><?php endforeach; ?></select>
        <button class="btn-mover-extras" onclick="moverSeleccionadosExtras()" id="btn-mover-__extras__">Mover seleccionados</button>
        <form method="POST" id="form-__extras__" style="display:none"><input type="hidden" name="action" value="mover_viajes"><input type="hidden" name="empresa_origen" value="__extras__"><input type="hidden" name="ids_seleccionados" id="ids-__extras__"><input type="hidden" name="destino" id="destino_hidden___extras__"></form>
    </div>
    <div class="extras-total-editable">
        <span style="font-weight:700;margin-right:10px">TOTAL EXTRAS:</span>
        <?php if($total_extras!=$total_extras_calculado): ?><span style="font-size:11px;color:#999;text-decoration:line-through;margin-right:5px">$ <?php echo number_format($total_extras_calculado, 0, ',', '.'); ?></span><?php endif; ?>
        <span style="font-weight:700;margin-right:10px">$ <?php echo number_format($total_extras, 0, ',', '.'); ?></span>
        <form method="POST" style="display:inline-flex;align-items:center;gap:6px"><input type="hidden" name="action" value="guardar_total_extras"><input type="number" name="total_extras_valor" value="<?php echo $total_extras; ?>" step="1" style="width:130px;padding:5px 10px;border:1px solid #ff9800;border-radius:6px;text-align:right;font-weight:600;font-size:13px"><button class="btn-guardar-total" style="background:#ff9800">Guardar</button></form>
    </div>
</div>
<?php endif; ?>

<?php
if (!empty($empresas_seleccionadas)) {
    foreach ($empresas_seleccionadas as $empresa_actual) {
        $empresa_id_base = 'emp_' . preg_replace('/[^a-zA-Z0-9]/', '_', $empresa_actual);
        $empresa_anchor = 'tabla_' . preg_replace('/[^a-zA-Z0-9]/', '_', $empresa_actual);
        if (!isset($datos_empresas[$empresa_actual])) { ?><div class="empresa-table" id="<?php echo $empresa_anchor; ?>"><div class="table-header"><h2><?php echo htmlspecialchars($empresa_actual); ?></h2></div><div class="sin-datos">No hay viajes registrados</div></div><?php continue; }
        $data = $datos_empresas[$empresa_actual];
        $es_nazareth = (strtolower(trim($empresa_actual)) === 'p.nazareth');
        $nombres_seleccionados = isset($_SESSION['nombres_cambiados_empresa'][$empresa_actual]) ? $_SESSION['nombres_cambiados_empresa'][$empresa_actual] : array();
        
        if ($data['tipo'] === 'multiple'):
            $total_general = $data['total_general']; $total_general_efectivo = $data['total_general_efectivo']; ?>
            <div class="empresa-table" id="<?php echo $empresa_anchor; ?>">
                <div class="table-header"><h2><?php echo htmlspecialchars($empresa_actual); ?> <span class="badge-exceso" style="background:#1a73e8">2 Conductores</span></h2>
                <div style="display:flex;gap:15px;align-items:center;flex-wrap:wrap"><?php if($es_nazareth): ?><div class="busqueda-ruta"><input type="text" id="buscar_ruta_<?php echo $empresa_id_base; ?>" placeholder="Ej: Riohacha, Maicao..." autocomplete="off"><button type="button" onclick="seleccionarPorRuta('<?php echo $empresa_id_base; ?>')">Buscar</button></div><?php endif; ?><div class="drag-instruction">Arrastra sobre checkboxes</div></div></div>
                <div class="cambio-conductores-section">
                    <div class="filtro-group" style="flex:2;min-width:300px"><label>Buscar o escribir conductor (max. 2) - Enter para agregar</label><div class="autocomplete-wrapper"><input type="text" id="input_conductor_<?php echo $empresa_id_base; ?>" placeholder="Escribe y selecciona o presiona Enter..." autocomplete="off" onkeyup="buscarConductores('<?php echo $empresa_id_base; ?>')" onfocus="buscarConductores('<?php echo $empresa_id_base; ?>')" onkeydown="manejarTeclaConductor(event,'<?php echo $empresa_id_base; ?>')"><div class="autocomplete-list" id="autocomplete_<?php echo $empresa_id_base; ?>"></div></div><div class="input-hint">Selecciona de la lista o presiona Enter para nombre libre</div><div class="tags-conductores" id="tags_<?php echo $empresa_id_base; ?>"><?php foreach($nombres_seleccionados as $idx=>$nombre): ?><span class="tag-conductor"><?php echo htmlspecialchars($nombre); ?> <span class="remove-tag" onclick="removerConductor('<?php echo $empresa_id_base; ?>',<?php echo $idx; ?>)">X</span></span><?php endforeach; ?></div></div>
                    <div class="filtro-group" style="flex:0 0 auto"><button class="btn-cambiar-conductores" id="btn_cambiar_<?php echo $empresa_id_base; ?>" onclick="cambiarConductores('<?php echo $empresa_id_base; ?>','<?php echo htmlspecialchars($empresa_actual); ?>')" <?php echo empty($nombres_seleccionados)?'disabled':''; ?>>Cambiar Conductores</button></div>
                </div>
                <?php foreach ($data['tablas'] as $tab_idx => $tabla): 
                    $empresa_id = $empresa_id_base . '_c' . $tab_idx; $conductor_nombre = $tabla['nombre_conductor']; $rows_data = $tabla['rows']; $total_calculado = $tabla['total_calculado']; $total_efectivo = $tabla['total']; $key_tabla = $tabla['key']; $es_manual = isset($_SESSION['totales_manuales'][$key_tabla]); ?>
                    <div class="sub-table-wrapper">
                        <div class="table-header-conductor"><h3><?php echo htmlspecialchars($conductor_nombre); ?></h3><div class="acciones-header"><select class="destino-select" id="destino_<?php echo $empresa_id; ?>"><?php foreach($destinos_disponibles as $dk=>$dv): if($dk==($empresa_actual.'||'.$conductor_nombre)) continue; ?><option value="<?php echo htmlspecialchars($dk); ?>"><?php echo htmlspecialchars($dv); ?></option><?php endforeach; ?></select><button class="btn-mover-extras" onclick="moverSeleccionados('<?php echo $empresa_id; ?>')" id="btn-mover-<?php echo $empresa_id; ?>">Mover</button></div></div>
                        <form method="POST" id="form-<?php echo $empresa_id; ?>"><input type="hidden" name="action" value="mover_viajes"><input type="hidden" name="empresa_origen" value="<?php echo htmlspecialchars($empresa_actual); ?>"><input type="hidden" name="ids_seleccionados" id="ids-<?php echo $empresa_id; ?>"><input type="hidden" name="destino" id="destino_hidden_<?php echo $empresa_id; ?>"></form>
                        <div style="overflow-x:auto"><table style="min-width:1000px"><thead><tr><th><input type="checkbox" id="select-all-<?php echo $empresa_id; ?>" onchange="toggleSeleccionarTodos(this,'<?php echo $empresa_id; ?>')"></th><th>#</th><th>Fecha</th><th>Conductor</th><th>Cedula</th><th>Ruta</th><th>Tipo</th><th>Clasificacion</th><th>Valor</th><th>Acumulado</th></tr></thead><tbody id="tbody-<?php echo $empresa_id; ?>"><?php $contador=0;$acum=0;foreach($rows_data as $row):$contador++;$acum+=$row['costo']; ?><tr data-ruta="<?php echo strtolower($row['ruta']??''); ?>"><td class="checkbox-col"><input type="checkbox" class="fila-check-<?php echo $empresa_id; ?>" value="<?php echo $row['id']; ?>"></td><td style="text-align:center"><?php echo $contador; ?></td><td><?php echo date('d/m/Y',strtotime($row['fecha'])); ?></td><td><strong><?php echo htmlspecialchars($row['nombre']??'-'); ?></strong></td><td><?php echo htmlspecialchars($row['cedula']??'-'); ?></td><td class="ruta-cell"><?php echo htmlspecialchars($row['ruta']??'-'); ?></td><td><?php echo htmlspecialchars($row['tipo_vehiculo']??'-'); ?></td><td><?php echo htmlspecialchars($row['clasificacion']??'-'); ?></td><td class="costo">$ <?php echo number_format($row['costo'],0,',','.'); ?></td><td class="acumulado">$ <?php echo number_format($acum,0,',','.'); ?></td></tr><?php endforeach; ?></tbody></table></div>
                        <div style="background:#e8f0fe;padding:12px 20px;display:flex;align-items:center;justify-content:flex-end;gap:12px;flex-wrap:wrap"><strong>TOTAL <?php echo htmlspecialchars($conductor_nombre); ?>:</strong><?php if($es_manual): ?><span style="font-size:11px;color:#999;text-decoration:line-through">$ <?php echo number_format($total_calculado,0,',','.'); ?></span><span style="color:#e65100">></span><?php endif; ?><span style="font-weight:700;font-size:14px;color:#1a73e8">$ <?php echo number_format($total_efectivo,0,',','.'); ?></span><form method="POST" style="display:inline-flex;align-items:center;gap:6px"><input type="hidden" name="action" value="guardar_total_manual"><input type="hidden" name="total_key" value="<?php echo htmlspecialchars($key_tabla); ?>"><input type="number" name="total_valor" value="<?php echo $total_efectivo; ?>" step="1" style="width:110px;padding:4px 8px;border:1px solid #1a73e8;border-radius:6px;text-align:right;font-weight:600;font-size:12px"><button class="btn-guardar-total">Guardar</button></form></div>
                    </div>
                    <script>(function(){var eid='<?php echo $empresa_id; ?>';var cbs=document.querySelectorAll('.fila-check-'+eid);var dragging=false;cbs.forEach(function(cb){cb.addEventListener('mousedown',function(){dragging=true;cb.checked=!cb.checked;updateSelectAllCheckbox(eid);actualizarBotonMover(eid)});cb.addEventListener('mouseenter',function(){if(dragging){cb.checked=!cb.checked;updateSelectAllCheckbox(eid);actualizarBotonMover(eid)}})});document.addEventListener('mouseup',function(){dragging=false});var lastCb=null;cbs.forEach(function(cb){cb.addEventListener('click',function(e){if(e.shiftKey&&lastCb){var arr=Array.from(cbs);var s=Math.min(arr.indexOf(this),arr.indexOf(lastCb));var en=Math.max(arr.indexOf(this),arr.indexOf(lastCb));for(var i=s;i<=en;i++)arr[i].checked=this.checked;updateSelectAllCheckbox(eid);actualizarBotonMover(eid)}lastCb=this})});window.updateSelectAllCheckbox=function(id){var sa=document.getElementById('select-all-'+id);if(!sa)return;var cs=document.querySelectorAll('.fila-check-'+id);var all=Array.from(cs).every(function(c){return c.checked});var some=Array.from(cs).some(function(c){return c.checked});sa.checked=all;sa.indeterminate=!all&&some};window.actualizarBotonMover=function(id){var cs=document.querySelectorAll('.fila-check-'+id);var chk=Array.from(cs).filter(function(c){return c.checked});var btn=document.getElementById('btn-mover-'+id);if(btn){btn.disabled=chk.length===0;btn.innerHTML=chk.length>0?'Mover '+chk.length+' seleccionados':'Mover'}};window.toggleSeleccionarTodos=function(cb,id){document.querySelectorAll('.fila-check-'+id).forEach(function(c){c.checked=cb.checked});updateSelectAllCheckbox(id);actualizarBotonMover(id)};window.moverSeleccionados=function(id){var cs=document.querySelectorAll('.fila-check-'+id);var ids=Array.from(cs).filter(function(c){return c.checked}).map(function(c){return c.value});if(ids.length===0)return;document.getElementById('ids-'+id).value=ids.join(',');document.getElementById('destino_hidden_'+id).value=document.getElementById('destino_'+id).value;document.getElementById('form-'+id).submit()};cbs.forEach(function(cb){cb.addEventListener('change',function(){updateSelectAllCheckbox(eid);actualizarBotonMover(eid)})});actualizarBotonMover(eid)})();</script>
                <?php endforeach; ?>
                <div class="total-general-row">TOTAL <?php echo htmlspecialchars($empresa_actual); ?>: $ <?php echo number_format($total_general_efectivo,0,',','.'); ?><?php if($total_general_efectivo!=$total_general): ?> <span style="font-size:10px;opacity:0.7">(calculado: $ <?php echo number_format($total_general,0,',','.'); ?>)</span><?php endif; ?></div>
            </div>
            <?php if($es_nazareth): ?><script>window.seleccionarPorRuta=function(eid){var inp=document.getElementById('buscar_ruta_'+eid);var txt=inp.value.trim().toLowerCase();if(!txt){alert('Escribe una palabra');return}var subs=document.querySelectorAll('[id^="tbody-'+eid+'_c"]');var sel=0;subs.forEach(function(tb){tb.querySelectorAll('tr').forEach(function(fila){var cel=fila.querySelector('.ruta-cell');if(cel){var cb=fila.querySelector('input[type="checkbox"]');if(cb&&cel.textContent.toLowerCase().includes(txt)){cb.checked=true;sel++}}})});subs.forEach(function(tb){var sid=tb.id.replace('tbody-','');if(typeof updateSelectAllCheckbox==='function')updateSelectAllCheckbox(sid);if(typeof actualizarBotonMover==='function')actualizarBotonMover(sid)});if(sel===0)alert('No se encontraron rutas con "'+txt+'"')};</script><?php endif; ?>
        <?php else: 
            $rows_data=$data['rows'];$total_calculado=$data['total'];$total_efectivo=$data['total_efectivo'];$empresa_id=$empresa_id_base;$key_tabla=$data['key'];$es_manual=isset($_SESSION['totales_manuales'][$key_tabla]);
            if(empty($rows_data)){?><div class="empresa-table" id="<?php echo $empresa_anchor; ?>"><div class="table-header"><h2><?php echo htmlspecialchars($empresa_actual); ?></h2></div><div class="sin-datos">No hay viajes registrados</div></div><?php continue;} ?>
            <div class="empresa-table" id="<?php echo $empresa_anchor; ?>">
                <div class="table-header"><h2><?php echo htmlspecialchars($empresa_actual); ?><?php if(!empty($nombres_seleccionados)): ?> <span class="badge-exceso" style="background:#1a73e8">Nombres cambiados</span><?php endif; ?></h2><div style="display:flex;gap:15px;align-items:center;flex-wrap:wrap"><?php if($es_nazareth): ?><div class="busqueda-ruta"><input type="text" id="buscar_ruta_<?php echo $empresa_id; ?>" placeholder="Ej: Riohacha, Maicao..." autocomplete="off"><button type="button" onclick="seleccionarPorRuta('<?php echo $empresa_id; ?>')">Buscar</button></div><?php endif; ?><div class="drag-instruction">Arrastra sobre checkboxes</div><div class="acciones-header"><select class="destino-select" id="destino_<?php echo $empresa_id; ?>"><?php foreach($destinos_disponibles as $dk=>$dv): if($dk==$empresa_actual) continue; ?><option value="<?php echo htmlspecialchars($dk); ?>"><?php echo htmlspecialchars($dv); ?></option><?php endforeach; ?></select><button class="btn-mover-extras" onclick="moverSeleccionados('<?php echo $empresa_id; ?>')" id="btn-mover-<?php echo $empresa_id; ?>">Mover</button></div></div></div>
                <div class="cambio-conductores-section"><div class="filtro-group" style="flex:2;min-width:300px"><label>Buscar o escribir conductor (max. 2) - Enter para agregar</label><div class="autocomplete-wrapper"><input type="text" id="input_conductor_<?php echo $empresa_id; ?>" placeholder="Escribe y selecciona o presiona Enter..." autocomplete="off" onkeyup="buscarConductores('<?php echo $empresa_id; ?>')" onfocus="buscarConductores('<?php echo $empresa_id; ?>')" onkeydown="manejarTeclaConductor(event,'<?php echo $empresa_id; ?>')"><div class="autocomplete-list" id="autocomplete_<?php echo $empresa_id; ?>"></div></div><div class="input-hint">Selecciona de la lista o presiona Enter para nombre libre</div><div class="tags-conductores" id="tags_<?php echo $empresa_id; ?>"><?php foreach($nombres_seleccionados as $idx=>$nombre): ?><span class="tag-conductor"><?php echo htmlspecialchars($nombre); ?> <span class="remove-tag" onclick="removerConductor('<?php echo $empresa_id; ?>',<?php echo $idx; ?>)">X</span></span><?php endforeach; ?></div></div><div class="filtro-group" style="flex:0 0 auto"><button class="btn-cambiar-conductores" id="btn_cambiar_<?php echo $empresa_id; ?>" onclick="cambiarConductores('<?php echo $empresa_id; ?>','<?php echo htmlspecialchars($empresa_actual); ?>')" <?php echo empty($nombres_seleccionados)?'disabled':''; ?>>Cambiar Conductores</button></div></div>
                <form method="POST" id="form-<?php echo $empresa_id; ?>"><input type="hidden" name="action" value="mover_viajes"><input type="hidden" name="empresa_origen" value="<?php echo htmlspecialchars($empresa_actual); ?>"><input type="hidden" name="ids_seleccionados" id="ids-<?php echo $empresa_id; ?>"><input type="hidden" name="destino" id="destino_hidden_<?php echo $empresa_id; ?>"></form>
                <div style="overflow-x:auto"><table style="min-width:1000px"><thead><tr><th><input type="checkbox" id="select-all-<?php echo $empresa_id; ?>" onchange="toggleSeleccionarTodos(this,'<?php echo $empresa_id; ?>')"></th><th>#</th><th>Fecha</th><th>Conductor</th><th>Cedula</th><th>Ruta</th><th>Tipo</th><th>Clasificacion</th><th>Valor</th><th>Acumulado</th></tr></thead><tbody id="tbody-<?php echo $empresa_id; ?>"><?php $contador=0;foreach($rows_data as $row):$contador++; ?><tr data-ruta="<?php echo strtolower($row['ruta']??''); ?>"><td class="checkbox-col"><input type="checkbox" class="fila-check-<?php echo $empresa_id; ?>" value="<?php echo $row['id']; ?>"></td><td style="text-align:center"><?php echo $contador; ?></td><td><?php echo date('d/m/Y',strtotime($row['fecha'])); ?></td><td><strong><?php echo htmlspecialchars($row['nombre']??'-'); ?></strong></td><td><?php echo htmlspecialchars($row['cedula']??'-'); ?></td><td class="ruta-cell"><?php echo htmlspecialchars($row['ruta']??'-'); ?></td><td><?php echo htmlspecialchars($row['tipo_vehiculo']??'-'); ?></td><td><?php echo htmlspecialchars($row['clasificacion']??'-'); ?></td><td class="costo">$ <?php echo number_format($row['costo'],0,',','.'); ?></td><td class="acumulado">$ <?php echo number_format($row['acumulado'],0,',','.'); ?></td></tr><?php endforeach; ?></tbody></table></div>
                <div style="background:#e8f0fe;padding:12px 20px;display:flex;align-items:center;justify-content:flex-end;gap:12px;flex-wrap:wrap"><strong>TOTAL <?php echo htmlspecialchars($empresa_actual); ?>:</strong><?php if($es_manual): ?><span style="font-size:11px;color:#999;text-decoration:line-through">$ <?php echo number_format($total_calculado,0,',','.'); ?></span><span style="color:#e65100">></span><?php endif; ?><span style="font-weight:700;font-size:14px;color:#1a73e8">$ <?php echo number_format($total_efectivo,0,',','.'); ?></span><form method="POST" style="display:inline-flex;align-items:center;gap:6px"><input type="hidden" name="action" value="guardar_total_manual"><input type="hidden" name="total_key" value="<?php echo htmlspecialchars($key_tabla); ?>"><input type="number" name="total_valor" value="<?php echo $total_efectivo; ?>" step="1" style="width:110px;padding:4px 8px;border:1px solid #1a73e8;border-radius:6px;text-align:right;font-weight:600;font-size:12px"><button class="btn-guardar-total">Guardar</button></form></div>
            </div>
            <script>(function(){var eid='<?php echo $empresa_id; ?>';var cbs=document.querySelectorAll('.fila-check-'+eid);var dragging=false;cbs.forEach(function(cb){cb.addEventListener('mousedown',function(){dragging=true;cb.checked=!cb.checked;updateSelectAllCheckbox(eid);actualizarBotonMover(eid)});cb.addEventListener('mouseenter',function(){if(dragging){cb.checked=!cb.checked;updateSelectAllCheckbox(eid);actualizarBotonMover(eid)}})});document.addEventListener('mouseup',function(){dragging=false});var lastCb=null;cbs.forEach(function(cb){cb.addEventListener('click',function(e){if(e.shiftKey&&lastCb){var arr=Array.from(cbs);var s=Math.min(arr.indexOf(this),arr.indexOf(lastCb));var en=Math.max(arr.indexOf(this),arr.indexOf(lastCb));for(var i=s;i<=en;i++)arr[i].checked=this.checked;updateSelectAllCheckbox(eid);actualizarBotonMover(eid)}lastCb=this})});window.updateSelectAllCheckbox=function(id){var sa=document.getElementById('select-all-'+id);if(!sa)return;var cs=document.querySelectorAll('.fila-check-'+id);var all=Array.from(cs).every(function(c){return c.checked});var some=Array.from(cs).some(function(c){return c.checked});sa.checked=all;sa.indeterminate=!all&&some};window.actualizarBotonMover=function(id){var cs=document.querySelectorAll('.fila-check-'+id);var chk=Array.from(cs).filter(function(c){return c.checked});var btn=document.getElementById('btn-mover-'+id);if(btn){btn.disabled=chk.length===0;btn.innerHTML=chk.length>0?'Mover '+chk.length+' seleccionados':'Mover'}};window.toggleSeleccionarTodos=function(cb,id){document.querySelectorAll('.fila-check-'+id).forEach(function(c){c.checked=cb.checked});updateSelectAllCheckbox(id);actualizarBotonMover(id)};window.moverSeleccionados=function(id){var cs=document.querySelectorAll('.fila-check-'+id);var ids=Array.from(cs).filter(function(c){return c.checked}).map(function(c){return c.value});if(ids.length===0)return;document.getElementById('ids-'+id).value=ids.join(',');document.getElementById('destino_hidden_'+id).value=document.getElementById('destino_'+id).value;document.getElementById('form-'+id).submit()};cbs.forEach(function(cb){cb.addEventListener('change',function(){updateSelectAllCheckbox(eid);actualizarBotonMover(eid)})});actualizarBotonMover(eid);<?php if($es_nazareth): ?>window.seleccionarPorRuta=function(eid2){var inp=document.getElementById('buscar_ruta_'+eid2);var txt=inp.value.trim().toLowerCase();if(!txt){alert('Escribe una palabra');return}var filas=document.querySelectorAll('#tbody-'+eid2+' tr');var sel=0;filas.forEach(function(fila){var cel=fila.querySelector('.ruta-cell');if(cel){var cb=fila.querySelector('.fila-check-'+eid2);if(cb&&cel.textContent.toLowerCase().includes(txt)){cb.checked=true;sel++}}});updateSelectAllCheckbox(eid2);actualizarBotonMover(eid2);if(sel===0)alert('No se encontraron rutas con "'+txt+'"')};<?php endif; ?>)();</script>
        <?php endif;
    }
} else { ?><div class="empresa-table"><div class="sin-datos">Selecciona al menos una empresa en los filtros</div></div><?php } ?>

<?php foreach ($tablas_personalizadas_data as $tpidx => $tp): 
    $pers_id = 'pers_' . $tpidx; $tp_nombre = $tp['nombre']; $tp_filas = $tp['filas']; $tp_total_calc = $tp['total_calculado']; $tp_total = $tp['total']; $tp_key = $tp['key']; $tp_es_manual = isset($_SESSION['totales_manuales'][$tp_key]); ?>
    <div class="empresa-table">
        <div class="table-header-personal"><h3><?php echo htmlspecialchars($tp_nombre); ?></h3><div class="acciones-header"><button class="btn-agregar-fila" onclick="mostrarModalAgregarFila('<?php echo htmlspecialchars($tp_nombre); ?>')">+ Agregar viaje</button><select class="destino-select" id="destino_<?php echo $pers_id; ?>"><?php foreach($destinos_disponibles as $dk=>$dv): if($dk=='__personal__'.$tp_nombre) continue; ?><option value="<?php echo htmlspecialchars($dk); ?>"><?php echo htmlspecialchars($dv); ?></option><?php endforeach; ?></select><button class="btn-mover-extras" onclick="moverSeleccionados('<?php echo $pers_id; ?>')" id="btn-mover-<?php echo $pers_id; ?>">Mover</button><form method="POST" style="display:inline"><input type="hidden" name="action" value="eliminar_tabla_personalizada"><input type="hidden" name="tabla_nombre" value="<?php echo htmlspecialchars($tp_nombre); ?>"><button class="btn-eliminar-tabla" onclick="return confirm('Eliminar esta tabla?')">Eliminar tabla</button></form></div></div>
        <form method="POST" id="form-<?php echo $pers_id; ?>"><input type="hidden" name="action" value="mover_viajes"><input type="hidden" name="empresa_origen" value="__personal__<?php echo htmlspecialchars($tp_nombre); ?>"><input type="hidden" name="ids_seleccionados" id="ids-<?php echo $pers_id; ?>"><input type="hidden" name="destino" id="destino_hidden_<?php echo $pers_id; ?>"></form>
        <div style="overflow-x:auto"><table style="min-width:1000px"><thead><tr><th><input type="checkbox" id="select-all-<?php echo $pers_id; ?>" onchange="toggleSeleccionarTodos(this,'<?php echo $pers_id; ?>')"></th><th>#</th><th>Fecha</th><th>Conductor</th><th>Cedula</th><th>Ruta</th><th>Tipo</th><th>Origen</th><th>Valor</th><th>Accion</th></tr></thead><tbody id="tbody-<?php echo $pers_id; ?>"><?php $contador=0;foreach($tp_filas as $fila):$contador++; ?><tr><td class="checkbox-col"><input type="checkbox" class="fila-check-<?php echo $pers_id; ?>" value="<?php echo $fila['id']; ?>"></td><td style="text-align:center"><?php echo $contador; ?></td><td><?php echo date('d/m/Y',strtotime($fila['fecha'])); ?></td><td><strong><?php echo htmlspecialchars($fila['nombre']??'-'); ?></strong></td><td><?php echo htmlspecialchars($fila['cedula']??'-'); ?></td><td class="ruta-cell"><?php echo htmlspecialchars($fila['ruta']??'-'); ?></td><td><?php echo htmlspecialchars($fila['tipo_vehiculo']??'-'); ?></td><td><?php echo htmlspecialchars($fila['empresa_origen']??$fila['empresa']??'-'); ?></td><td class="costo">$ <?php echo number_format($fila['costo'],0,',','.'); ?></td><td><form method="POST" style="display:inline"><input type="hidden" name="action" value="eliminar_fila_personalizada"><input type="hidden" name="tabla_nombre" value="<?php echo htmlspecialchars($tp_nombre); ?>"><input type="hidden" name="fila_id" value="<?php echo $fila['id']; ?>"><button class="btn-eliminar-fila">X</button></form></td></tr><?php endforeach; ?></tbody></table></div>
        <div style="background:#e0f2f1;padding:12px 20px;display:flex;align-items:center;justify-content:flex-end;gap:12px;flex-wrap:wrap"><strong>TOTAL <?php echo htmlspecialchars($tp_nombre); ?>:</strong><?php if($tp_es_manual): ?><span style="font-size:11px;color:#999;text-decoration:line-through">$ <?php echo number_format($tp_total_calc,0,',','.'); ?></span><span style="color:#e65100">></span><?php endif; ?><span style="font-weight:700;font-size:14px;color:#00695c">$ <?php echo number_format($tp_total,0,',','.'); ?></span><form method="POST" style="display:inline-flex;align-items:center;gap:6px"><input type="hidden" name="action" value="guardar_total_personal"><input type="hidden" name="total_key" value="<?php echo htmlspecialchars($tp_key); ?>"><input type="number" name="total_valor" value="<?php echo $tp_total; ?>" step="1" style="width:110px;padding:4px 8px;border:1px solid #00897b;border-radius:6px;text-align:right;font-weight:600;font-size:12px"><button class="btn-guardar-total" style="background:#00897b">Guardar</button></form></div>
    </div>
    <script>(function(){var eid='<?php echo $pers_id; ?>';var cbs=document.querySelectorAll('.fila-check-'+eid);var dragging=false;cbs.forEach(function(cb){cb.addEventListener('mousedown',function(){dragging=true;cb.checked=!cb.checked;updateSelectAllCheckbox(eid);actualizarBotonMover(eid)});cb.addEventListener('mouseenter',function(){if(dragging){cb.checked=!cb.checked;updateSelectAllCheckbox(eid);actualizarBotonMover(eid)}})});document.addEventListener('mouseup',function(){dragging=false});var lastCb=null;cbs.forEach(function(cb){cb.addEventListener('click',function(e){if(e.shiftKey&&lastCb){var arr=Array.from(cbs);var s=Math.min(arr.indexOf(this),arr.indexOf(lastCb));var en=Math.max(arr.indexOf(this),arr.indexOf(lastCb));for(var i=s;i<=en;i++)arr[i].checked=this.checked;updateSelectAllCheckbox(eid);actualizarBotonMover(eid)}lastCb=this})});window.updateSelectAllCheckbox=function(id){var sa=document.getElementById('select-all-'+id);if(!sa)return;var cs=document.querySelectorAll('.fila-check-'+id);var all=Array.from(cs).every(function(c){return c.checked});var some=Array.from(cs).some(function(c){return c.checked});sa.checked=all;sa.indeterminate=!all&&some};window.actualizarBotonMover=function(id){var cs=document.querySelectorAll('.fila-check-'+id);var chk=Array.from(cs).filter(function(c){return c.checked});var btn=document.getElementById('btn-mover-'+id);if(btn){btn.disabled=chk.length===0;btn.innerHTML=chk.length>0?'Mover '+chk.length+' seleccionados':'Mover'}};window.toggleSeleccionarTodos=function(cb,id){document.querySelectorAll('.fila-check-'+id).forEach(function(c){c.checked=cb.checked});updateSelectAllCheckbox(id);actualizarBotonMover(id)};window.moverSeleccionados=function(id){var cs=document.querySelectorAll('.fila-check-'+id);var ids=Array.from(cs).filter(function(c){return c.checked}).map(function(c){return c.value});if(ids.length===0)return;document.getElementById('ids-'+id).value=ids.join(',');document.getElementById('destino_hidden_'+id).value=document.getElementById('destino_'+id).value;document.getElementById('form-'+id).submit()};cbs.forEach(function(cb){cb.addEventListener('change',function(){updateSelectAllCheckbox(eid);actualizarBotonMover(eid)})});actualizarBotonMover(eid)})();</script>
<?php endforeach; ?>

<div class="resumen-table"><div class="resumen-header">RESUMEN GENERAL</div><div style="overflow-x:auto"><table style="min-width:600px"><thead><tr><th>Puesto de Salud / Tabla</th><th style="text-align:right">Total</th></tr></thead><tbody>
    <?php foreach($resumen_empresas as $nombre=>$total): ?><tr><td><?php echo htmlspecialchars($nombre); ?></td><td class="costo">$ <?php echo number_format($total,0,',','.'); ?></td></tr><?php endforeach; ?>
    <tr class="subtotal-row"><td style="text-align:right"><strong>SUBTOTAL</strong></td><td class="costo"><strong>$ <?php echo number_format($total_puestos,0,',','.'); ?></strong></td></tr>
    <?php if(!empty($_SESSION['extras'])): ?><tr><td>EXTRAS</td><td class="costo">$ <?php echo number_format($total_extras,0,',','.'); ?></td></tr><?php endif; ?>
    <tr class="gran-total-row"><td style="text-align:right"><strong>TOTAL GENERAL</strong></td><td class="costo"><strong>$ <?php echo number_format($total_general,0,',','.'); ?></strong></td></tr>
</tbody></table></div></div></div>

<div class="modal-overlay" id="modalCrearTabla"><div class="modal-content"><h3>Nueva tabla personalizada</h3><form method="POST"><input type="hidden" name="action" value="crear_tabla_personalizada"><div class="filtro-group"><label>Nombre de la tabla</label><input type="text" name="nombre_tabla" placeholder="Ej: Viajes Uribia" required></div><div class="modal-buttons"><button type="button" class="btn-modal-cancel" onclick="cerrarModal('modalCrearTabla')">Cancelar</button><button type="submit" class="btn-modal-primary">Crear</button></div></form></div></div>

<div class="modal-overlay" id="modalAgregarFila"><div class="modal-content"><h3>Agregar viaje manualmente</h3><form method="POST"><input type="hidden" name="action" value="agregar_fila_personalizada"><input type="hidden" name="tabla_nombre" id="modal_tabla_nombre"><div class="filtro-group"><label>Fecha</label><input type="date" name="fecha" required></div><div class="filtro-group"><label>Conductor</label><input type="text" name="nombre" placeholder="Nombre del conductor" required></div><div class="filtro-group"><label>Cedula</label><input type="text" name="cedula" placeholder="Cedula"></div><div class="filtro-group"><label>Ruta</label><input type="text" name="ruta" placeholder="Ruta del viaje" required></div><div class="filtro-group"><label>Tipo de vehiculo</label><input type="text" name="tipo_vehiculo" placeholder="Burbuja, Camion 350..." required></div><div class="filtro-group"><label>Valor</label><input type="number" name="valor" placeholder="0" step="1" required></div><div class="modal-buttons"><button type="button" class="btn-modal-cancel" onclick="cerrarModal('modalAgregarFila')">Cancelar</button><button type="submit" class="btn-modal-primary">Agregar</button></div></form></div></div>

<script>
var todosLosConductores = <?php echo json_encode($todos_conductores); ?>;
var conductoresSeleccionados = {};
<?php foreach ($empresas_seleccionadas as $empresa): $emp_id = 'emp_' . preg_replace('/[^a-zA-Z0-9]/', '_', $empresa); $nombres = isset($_SESSION['nombres_cambiados_empresa'][$empresa]) ? $_SESSION['nombres_cambiados_empresa'][$empresa] : array(); ?>
conductoresSeleccionados['<?php echo $emp_id; ?>'] = <?php echo json_encode($nombres); ?>;
<?php endforeach; ?>

function buscarConductores(eid){var inp=document.getElementById('input_conductor_'+eid);var lista=document.getElementById('autocomplete_'+eid);var texto=inp.value.trim();if(!texto){lista.style.display='none';return}var coincidencias=todosLosConductores.filter(function(c){return c.toLowerCase().indexOf(texto.toLowerCase())!==-1});if(coincidencias.length===0){lista.innerHTML='<div style="padding:10px;color:#999">Sin coincidencias - Presiona Enter para agregar "'+texto+'"</div>';lista.style.display='block';return}lista.innerHTML=coincidencias.map(function(c){return '<div onclick="seleccionarConductor(\''+eid+'\',\''+c.replace(/'/g,"\\'")+'\')">'+c+'</div>'}).join('')+'<div style="padding:8px 15px;background:#f0f0f0;color:#666;font-size:11px;border-top:1px solid #ddd">O presiona Enter para agregar: <strong>'+texto+'</strong></div>';lista.style.display='block'}
function manejarTeclaConductor(event,eid){if(event.key==='Enter'){event.preventDefault();var inp=document.getElementById('input_conductor_'+eid);var texto=inp.value.trim();if(!texto)return;agregarConductorLibre(eid,texto);inp.value='';document.getElementById('autocomplete_'+eid).style.display='none'}}
function agregarConductorLibre(eid,conductor){if(!conductoresSeleccionados[eid])conductoresSeleccionados[eid]=[];if(conductoresSeleccionados[eid].length>=2){alert('Maximo 2 conductores');return}if(conductoresSeleccionados[eid].indexOf(conductor)!==-1){alert('Ya seleccionado');return}conductoresSeleccionados[eid].push(conductor);actualizarTags(eid);actualizarBotonCambiar(eid)}
function seleccionarConductor(eid,conductor){if(!conductoresSeleccionados[eid])conductoresSeleccionados[eid]=[];if(conductoresSeleccionados[eid].length>=2){alert('Maximo 2 conductores');return}if(conductoresSeleccionados[eid].indexOf(conductor)!==-1){alert('Ya seleccionado');return}conductoresSeleccionados[eid].push(conductor);actualizarTags(eid);actualizarBotonCambiar(eid);document.getElementById('input_conductor_'+eid).value='';document.getElementById('autocomplete_'+eid).style.display='none'}
function removerConductor(eid,idx){if(conductoresSeleccionados[eid]){conductoresSeleccionados[eid].splice(idx,1);actualizarTags(eid);actualizarBotonCambiar(eid)}}
function actualizarTags(eid){var tc=document.getElementById('tags_'+eid);if(!tc)return;tc.innerHTML=(conductoresSeleccionados[eid]||[]).map(function(c,i){return '<span class="tag-conductor">'+c+' <span class="remove-tag" onclick="removerConductor(\''+eid+'\','+i+')">X</span></span>'}).join('')}
function actualizarBotonCambiar(eid){var btn=document.getElementById('btn_cambiar_'+eid);if(!btn)return;btn.disabled=(conductoresSeleccionados[eid]||[]).length===0}
function cambiarConductores(eid,empresa){var conductores=conductoresSeleccionados[eid]||[];if(conductores.length===0){alert('Selecciona al menos un conductor');return}var form=document.createElement('form');form.method='POST';form.style.display='none';form.innerHTML='<input type="hidden" name="action" value="cambiar_conductores"><input type="hidden" name="empresa_cambio" value="'+empresa+'">'+conductores.map(function(c){return '<input type="hidden" name="nombres_conductores[]" value="'+c+'">'}).join('');document.body.appendChild(form);form.submit()}
function seleccionarTodas(sel){document.querySelectorAll('#empresasGrid input[type="checkbox"]').forEach(function(cb){cb.checked=sel});document.getElementById('filtroForm').submit()}
document.querySelectorAll('#empresasGrid input[type="checkbox"]').forEach(function(cb){cb.addEventListener('change',function(){document.getElementById('filtroForm').submit()})});
function irATabla(empresa){var el=document.getElementById('tabla_'+empresa.replace(/[^a-zA-Z0-9]/g,'_'));if(el){el.scrollIntoView({behavior:'smooth',block:'start'});el.style.boxShadow='0 0 0 3px #ff9800, 0 4px 12px rgba(0,0,0,0.15)';setTimeout(function(){el.style.boxShadow=''},2000)}}
document.addEventListener('click',function(e){document.querySelectorAll('.autocomplete-list').forEach(function(lista){if(!lista.parentElement.contains(e.target))lista.style.display='none'})});
function mostrarModalCrearTabla(){document.getElementById('modalCrearTabla').style.display='flex'}
function mostrarModalAgregarFila(nombre){document.getElementById('modal_tabla_nombre').value=nombre;document.getElementById('modalAgregarFila').style.display='flex'}
function cerrarModal(id){document.getElementById(id).style.display='none'}
window.addEventListener('click',function(e){if(e.target.classList.contains('modal-overlay'))e.target.style.display='none'});

// Funciones para mover desde EXTRAS
function toggleSeleccionarTodosExtras(cb){document.querySelectorAll('.fila-check-__extras__').forEach(function(c){c.checked=cb.checked});actualizarBotonMoverExtras()}
function actualizarBotonMoverExtras(){var cs=document.querySelectorAll('.fila-check-__extras__');var chk=Array.from(cs).filter(function(c){return c.checked});var btn=document.getElementById('btn-mover-__extras__');if(btn){btn.disabled=chk.length===0;btn.innerHTML=chk.length>0?'Mover '+chk.length+' seleccionados':'Mover'}}
function moverSeleccionadosExtras(){var cs=document.querySelectorAll('.fila-check-__extras__');var ids=Array.from(cs).filter(function(c){return c.checked}).map(function(c){return c.value});if(ids.length===0)return;document.getElementById('ids-__extras__').value=ids.join(',');document.getElementById('destino_hidden___extras__').value=document.getElementById('destino___extras__').value;document.getElementById('form-__extras__').submit()}
document.querySelectorAll('.fila-check-__extras__').forEach(function(cb){cb.addEventListener('change',actualizarBotonMoverExtras)});
actualizarBotonMoverExtras();
</script>
</body>
</html>