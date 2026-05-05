<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Cargar PhpWord
require_once 'vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// Conexión BD
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Inicializar sesión para tablas personalizadas
if (!isset($_SESSION['tablas_personalizadas'])) {
    $_SESSION['tablas_personalizadas'] = array();
}

// Mantener compatibilidad con 'extras' antiguo
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

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['export_word'])) {
        // Se procesa al final del archivo
    } elseif (isset($_POST['action'])) {
        switch($_POST['action']) {
            case 'mover_a_tabla':
                if (isset($_POST['ids_seleccionados']) && isset($_POST['tabla_destino'])) {
                    $empresa_origen = $_POST['empresa_origen'];
                    $tabla_destino = $_POST['tabla_destino'];
                    $ids = explode(',', $_POST['ids_seleccionados']);
                    
                    if (!isset($_SESSION['tablas_personalizadas'][$tabla_destino])) {
                        $_SESSION['tablas_personalizadas'][$tabla_destino] = array(
                            'nombre' => $tabla_destino,
                            'viajes' => array(),
                            'fecha_creacion' => date('Y-m-d H:i:s')
                        );
                    }
                    
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
                            
                            if (isset($_SESSION['nombres_cambiados'][$id])) {
                                $row['nombre'] = $_SESSION['nombres_cambiados'][$id];
                            }
                            
                            $_SESSION['tablas_personalizadas'][$tabla_destino]['viajes'][] = $row;
                        }
                        $stmt->close();
                    }
                    
                    header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
                    exit;
                }
                break;
                
            case 'crear_tabla':
                if (isset($_POST['nombre_tabla']) && !empty(trim($_POST['nombre_tabla']))) {
                    $nombre_tabla = trim($_POST['nombre_tabla']);
                    if (!isset($_SESSION['tablas_personalizadas'][$nombre_tabla])) {
                        $_SESSION['tablas_personalizadas'][$nombre_tabla] = array(
                            'nombre' => $nombre_tabla,
                            'viajes' => array(),
                            'fecha_creacion' => date('Y-m-d H:i:s')
                        );
                    }
                }
                header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
                exit;
                break;
                
            case 'eliminar_tabla':
                if (isset($_POST['tabla_nombre'])) {
                    $nombre_tabla = $_POST['tabla_nombre'];
                    unset($_SESSION['tablas_personalizadas'][$nombre_tabla]);
                    $key_tabla = 'tabla__' . $nombre_tabla;
                    if (isset($_SESSION['totales_manuales'][$key_tabla])) {
                        unset($_SESSION['totales_manuales'][$key_tabla]);
                    }
                }
                header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
                exit;
                break;
                
            case 'renombrar_tabla':
                if (isset($_POST['tabla_original']) && isset($_POST['tabla_nuevo_nombre']) && !empty(trim($_POST['tabla_nuevo_nombre']))) {
                    $original = $_POST['tabla_original'];
                    $nuevo = trim($_POST['tabla_nuevo_nombre']);
                    if (isset($_SESSION['tablas_personalizadas'][$original]) && !isset($_SESSION['tablas_personalizadas'][$nuevo])) {
                        $_SESSION['tablas_personalizadas'][$nuevo] = $_SESSION['tablas_personalizadas'][$original];
                        $_SESSION['tablas_personalizadas'][$nuevo]['nombre'] = $nuevo;
                        unset($_SESSION['tablas_personalizadas'][$original]);
                        
                        $total_key_old = 'tabla__' . $original;
                        $total_key_new = 'tabla__' . $nuevo;
                        if (isset($_SESSION['totales_manuales'][$total_key_old])) {
                            $_SESSION['totales_manuales'][$total_key_new] = $_SESSION['totales_manuales'][$total_key_old];
                            unset($_SESSION['totales_manuales'][$total_key_old]);
                        }
                    }
                }
                header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
                exit;
                break;
                
            case 'eliminar_viaje_tabla':
                if (isset($_POST['tabla_nombre']) && isset($_POST['viaje_index'])) {
                    $tabla = $_POST['tabla_nombre'];
                    $index = intval($_POST['viaje_index']);
                    if (isset($_SESSION['tablas_personalizadas'][$tabla]['viajes'][$index])) {
                        array_splice($_SESSION['tablas_personalizadas'][$tabla]['viajes'], $index, 1);
                    }
                }
                header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
                exit;
                break;
                
            case 'guardar_total_tabla':
                if (isset($_POST['total_key']) && isset($_POST['total_valor'])) {
                    $key = $_POST['total_key'];
                    $valor = floatval(str_replace(['.', ','], ['', '.'], $_POST['total_valor']));
                    $_SESSION['totales_manuales'][$key] = $valor;
                }
                header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
                exit;
                break;
                
            case 'guardar_total_manual':
                if (isset($_POST['total_key']) && isset($_POST['total_valor'])) {
                    $key = $_POST['total_key'];
                    $valor = floatval(str_replace(['.', ','], ['', '.'], $_POST['total_valor']));
                    $_SESSION['totales_manuales'][$key] = $valor;
                }
                header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
                exit;
                break;
                
            case 'guardar_total_extras':
                if (isset($_POST['total_extras_valor'])) {
                    $valor = floatval(str_replace(['.', ','], ['', '.'], $_POST['total_extras_valor']));
                    $_SESSION['totales_manuales']['__extras__'] = $valor;
                }
                header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
                exit;
                break;
                
            case 'mover_extras':
                if (isset($_POST['ids_seleccionados'])) {
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
                break;
                
            case 'limpiar_extras':
                $_SESSION['extras'] = array();
                if (isset($_SESSION['totales_manuales']['__extras__'])) {
                    unset($_SESSION['totales_manuales']['__extras__']);
                }
                header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
                exit;
                break;
                
            case 'eliminar_extra':
                if (isset($_POST['extra_index'])) {
                    $index = intval($_POST['extra_index']);
                    if (isset($_SESSION['extras'][$index])) {
                        array_splice($_SESSION['extras'], $index, 1);
                    }
                }
                header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
                exit;
                break;
                
            case 'cambiar_conductores':
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
                break;
                
            case 'restaurar_nombres':
                $_SESSION['nombres_cambiados'] = array();
                $_SESSION['nombres_cambiados_empresa'] = array();
                header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
                exit;
                break;
                
            case 'restaurar_totales':
                $_SESSION['totales_manuales'] = array();
                header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
                exit;
                break;
        }
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

function obtenerTotalEfectivo($key, $total_calculado) {
    if (isset($_SESSION['totales_manuales'][$key])) {
        return $_SESSION['totales_manuales'][$key];
    }
    return $total_calculado;
}

function obtenerDatosParaExportar($conn, $fecha_desde, $fecha_hasta, $empresas_seleccionadas, $extras, $PRESUPUESTO_BASE) {
    $datos = array();
    $ids_en_extras = array_column($extras, 'id');
    
    $ids_en_tablas = array();
    foreach ($_SESSION['tablas_personalizadas'] as $tabla) {
        foreach ($tabla['viajes'] as $viaje) {
            $ids_en_tablas[] = $viaje['id'];
        }
    }
    
    $ids_excluir = array_merge($ids_en_extras, $ids_en_tablas);
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
        
        if (!empty($ids_excluir)) {
            $placeholders = implode(',', array_fill(0, count($ids_excluir), '?'));
            $sql .= " AND v.id NOT IN ($placeholders)";
            foreach ($ids_excluir as $id) {
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
                        $key_tabla = $empresa_actual . '||' . $nombre_conductor;
                        $tablas_conductores[$idx] = array(
                            'nombre_conductor' => $nombre_conductor,
                            'key' => $key_tabla,
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
                    
                    foreach ($tablas_conductores as &$tabla) {
                        $tabla['total'] = obtenerTotalEfectivo($tabla['key'], $tabla['total_calculado']);
                    }
                    unset($tabla);
                    
                    $total_general_efectivo = 0;
                    foreach ($tablas_conductores as $tabla) {
                        $total_general_efectivo += $tabla['total'];
                    }
                    
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
                    
                    $key_tabla = $empresa_actual . '||_simple_';
                    $total_efectivo = obtenerTotalEfectivo($key_tabla, $acumulado);
                    
                    $datos[$empresa_actual] = array(
                        'tipo' => 'simple',
                        'key' => $key_tabla,
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
                $datos[$empresa_actual] = array(
                    'tipo' => 'simple', 
                    'key' => $empresa_actual . '||_simple_', 
                    'rows' => array(), 
                    'total' => 0, 
                    'total_efectivo' => 0
                );
            }
            $stmt->close();
        }
    }
    
    return array('datos' => $datos, 'alertas' => $alertas);
}

$extras_con_acumulado = calcular_acumulado_extras($_SESSION['extras']);
$total_extras_calculado = array_sum(array_column($_SESSION['extras'], 'costo'));
$total_extras = obtenerTotalEfectivo('__extras__', $total_extras_calculado);

// Calcular totales de tablas personalizadas
$totales_tablas = array();
foreach ($_SESSION['tablas_personalizadas'] as $nombre => $tabla) {
    $total_calculado = array_sum(array_column($tabla['viajes'], 'costo'));
    $key_tabla = 'tabla__' . $nombre;
    $total_efectivo = obtenerTotalEfectivo($key_tabla, $total_calculado);
    $totales_tablas[$nombre] = array(
        'calculado' => $total_calculado,
        'efectivo' => $total_efectivo
    );
}

$resultado = obtenerDatosParaExportar($conn, $fecha_desde, $fecha_hasta, $empresas_seleccionadas, $_SESSION['extras'], $PRESUPUESTO_BASE);
$datos_empresas = $resultado['datos'];
$alertas = $resultado['alertas'];

// Calcular totales para el resumen
$total_puestos = 0;
$resumen_empresas = array();

foreach ($empresas_seleccionadas as $empresa) {
    if (!isset($datos_empresas[$empresa])) continue;
    $data = $datos_empresas[$empresa];
    
    if ($data['tipo'] === 'multiple') {
        $total_empresa = 0;
        foreach ($data['tablas'] as $tabla) {
            $total_empresa += $tabla['total'];
        }
    } else {
        $total_empresa = $data['total_efectivo'];
    }
    
    $resumen_empresas[$empresa] = $total_empresa;
    $total_puestos += $total_empresa;
}

$total_tablas_personalizadas = 0;
foreach ($totales_tablas as $total_tabla) {
    $total_tablas_personalizadas += $total_tabla['efectivo'];
}

$total_general = $total_puestos + $total_extras + $total_tablas_personalizadas;

// ============================================================
// EXPORTACIÓN A WORD (DOCX) CON PHPWORD
// ============================================================
if (isset($_POST['export_word'])) {
    $phpWord = new PhpWord();
    $phpWord->setDefaultFontName('Arial');
    $phpWord->setDefaultFontSize(10);
    
    $section = $phpWord->addSection([
        'marginLeft' => 1134,
        'marginRight' => 1134,
        'marginTop' => 1134,
        'marginBottom' => 1134,
    ]);
    
    // Título principal
    $section->addTitle('Informe de Viajes por Puesto de Salud', 1);
    
    // Información de filtros
    $section->addText(
        'Periodo: ' . ($fecha_desde ? date('d/m/Y', strtotime($fecha_desde)) : 'Todo') . 
        ' - ' . ($fecha_hasta ? date('d/m/Y', strtotime($fecha_hasta)) : 'Todo') .
        ' | Empresas: ' . (!empty($empresas_seleccionadas) ? implode(', ', $empresas_seleccionadas) : 'Ninguna'),
        ['bold' => true, 'size' => 10],
        ['spaceAfter' => 240]
    );
    
    $tableStyle = [
        'borderSize' => 6,
        'borderColor' => '999999',
        'cellMargin' => 60,
        'width' => 100 * 50,
        'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::PERCENT,
    ];
    
    $firstRowStyle = [
        'bgColor' => '1a73e8',
        'bold' => true,
        'color' => 'FFFFFF',
        'size' => 9,
    ];
    
    $totalRowStyle = [
        'bgColor' => 'e8f0fe',
        'bold' => true,
        'size' => 10,
    ];
    
    // Tablas de empresas
    foreach ($datos_empresas as $empresa => $data) {
        if ($data['tipo'] === 'multiple') {
            foreach ($data['tablas'] as $tabla) {
                if (empty($tabla['rows'])) continue;
                
                $section->addTitle($empresa . ' - ' . $tabla['nombre_conductor'], 2);
                
                $table = $section->addTable($tableStyle);
                
                $table->addRow();
                $table->addCell(1500, $firstRowStyle)->addText('Fecha');
                $table->addCell(2500, $firstRowStyle)->addText('Conductor');
                $table->addCell(4000, $firstRowStyle)->addText('Ruta');
                $table->addCell(1500, array_merge($firstRowStyle, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END]))->addText('Valor');
                
                foreach ($tabla['rows'] as $row) {
                    $table->addRow();
                    $table->addCell(1500)->addText(date('d/m/Y', strtotime($row['fecha'])), ['size' => 9]);
                    $table->addCell(2500)->addText($row['nombre'] ?? '-', ['size' => 9]);
                    $table->addCell(4000)->addText($row['ruta'] ?? '-', ['size' => 9]);
                    $table->addCell(1500, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END])->addText(
                        '$ ' . number_format($row['costo'], 0, ',', '.'),
                        ['size' => 9]
                    );
                }
                
                $table->addRow();
                $table->addCell(8000, array_merge($totalRowStyle, ['gridSpan' => 3, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END]))->addText('TOTAL:');
                $table->addCell(1500, array_merge($totalRowStyle, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END]))->addText(
                    '$ ' . number_format($tabla['total'], 0, ',', '.')
                );
            }
        } else {
            if (empty($data['rows'])) continue;
            
            $section->addTitle($empresa, 2);
            
            $table = $section->addTable($tableStyle);
            
            $table->addRow();
            $table->addCell(1500, $firstRowStyle)->addText('Fecha');
            $table->addCell(2500, $firstRowStyle)->addText('Conductor');
            $table->addCell(4000, $firstRowStyle)->addText('Ruta');
            $table->addCell(1500, array_merge($firstRowStyle, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END]))->addText('Valor');
            
            foreach ($data['rows'] as $row) {
                $table->addRow();
                $table->addCell(1500)->addText(date('d/m/Y', strtotime($row['fecha'])), ['size' => 9]);
                $table->addCell(2500)->addText($row['nombre'] ?? '-', ['size' => 9]);
                $table->addCell(4000)->addText($row['ruta'] ?? '-', ['size' => 9]);
                $table->addCell(1500, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END])->addText(
                    '$ ' . number_format($row['costo'], 0, ',', '.'),
                    ['size' => 9]
                );
            }
            
            $table->addRow();
            $table->addCell(8000, array_merge($totalRowStyle, ['gridSpan' => 3, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END]))->addText('TOTAL:');
            $table->addCell(1500, array_merge($totalRowStyle, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END]))->addText(
                '$ ' . number_format($data['total_efectivo'], 0, ',', '.')
            );
        }
    }
    
    // Tablas personalizadas
    foreach ($_SESSION['tablas_personalizadas'] as $nombre => $tabla) {
        if (empty($tabla['viajes'])) continue;
        
        $section->addTitle($nombre, 2);
        
        $table = $section->addTable($tableStyle);
        
        $table->addRow();
        $table->addCell(1500, $firstRowStyle)->addText('Fecha');
        $table->addCell(2500, $firstRowStyle)->addText('Conductor');
        $table->addCell(4000, $firstRowStyle)->addText('Ruta');
        $table->addCell(1500, array_merge($firstRowStyle, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END]))->addText('Valor');
        
        $acum_tabla = 0;
        foreach ($tabla['viajes'] as $viaje) {
            $acum_tabla += $viaje['costo'];
            $table->addRow();
            $table->addCell(1500)->addText(date('d/m/Y', strtotime($viaje['fecha'])), ['size' => 9]);
            $table->addCell(2500)->addText($viaje['nombre'] ?? '-', ['size' => 9]);
            $table->addCell(4000)->addText($viaje['ruta'] ?? '-', ['size' => 9]);
            $table->addCell(1500, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END])->addText(
                '$ ' . number_format($viaje['costo'], 0, ',', '.'),
                ['size' => 9]
            );
        }
        
        $table->addRow();
        $table->addCell(8000, array_merge($totalRowStyle, ['gridSpan' => 3, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END]))->addText('TOTAL ' . $nombre . ':');
        $table->addCell(1500, array_merge($totalRowStyle, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END]))->addText(
            '$ ' . number_format($totales_tablas[$nombre]['efectivo'], 0, ',', '.')
        );
    }
    
    // Extras
    if (!empty($_SESSION['extras'])) {
        $section->addTitle('EXTRAS', 2);
        
        $table = $section->addTable($tableStyle);
        
        $table->addRow();
        $table->addCell(1500, $firstRowStyle)->addText('Fecha');
        $table->addCell(2500, $firstRowStyle)->addText('Conductor');
        $table->addCell(4000, $firstRowStyle)->addText('Ruta');
        $table->addCell(1500, array_merge($firstRowStyle, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END]))->addText('Valor');
        
        foreach ($extras_con_acumulado as $ex) {
            $table->addRow();
            $table->addCell(1500)->addText(date('d/m/Y', strtotime($ex['data']['fecha'])), ['size' => 9]);
            $table->addCell(2500)->addText($ex['data']['nombre'] ?? '-', ['size' => 9]);
            $table->addCell(4000)->addText($ex['data']['ruta'] ?? '-', ['size' => 9]);
            $table->addCell(1500, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END])->addText(
                '$ ' . number_format($ex['data']['costo'], 0, ',', '.'),
                ['size' => 9]
            );
        }
        
        $table->addRow();
        $table->addCell(8000, array_merge($totalRowStyle, ['gridSpan' => 3, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END]))->addText('TOTAL EXTRAS:');
        $table->addCell(1500, array_merge($totalRowStyle, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END]))->addText(
            '$ ' . number_format($total_extras, 0, ',', '.')
        );
    }
    
    // Resumen General
    $section->addTitle('RESUMEN GENERAL', 1);
    
    $table = $section->addTable($tableStyle);
    
    $table->addRow();
    $table->addCell(7500, $firstRowStyle)->addText('Concepto');
    $table->addCell(2000, array_merge($firstRowStyle, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END]))->addText('Total');
    
    foreach ($resumen_empresas as $empresa => $total) {
        $table->addRow();
        $table->addCell(7500)->addText($empresa, ['size' => 9]);
        $table->addCell(2000, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END])->addText(
            '$ ' . number_format($total, 0, ',', '.'),
            ['size' => 9]
        );
    }
    
    $table->addRow();
    $table->addCell(7500, array_merge($totalRowStyle, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END]))->addText('SUBTOTAL PUESTOS');
    $table->addCell(2000, array_merge($totalRowStyle, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END]))->addText(
        '$ ' . number_format($total_puestos, 0, ',', '.')
    );
    
    if (!empty($_SESSION['extras'])) {
        $table->addRow();
        $table->addCell(7500)->addText('EXTRAS', ['size' => 9]);
        $table->addCell(2000, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END])->addText(
            '$ ' . number_format($total_extras, 0, ',', '.'),
            ['size' => 9]
        );
    }
    
    foreach ($totales_tablas as $nombre => $total) {
        $table->addRow();
        $table->addCell(7500)->addText($nombre, ['size' => 9]);
        $table->addCell(2000, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END])->addText(
            '$ ' . number_format($total['efectivo'], 0, ',', '.'),
            ['size' => 9]
        );
    }
    
    $granTotalStyle = [
        'bgColor' => '1a73e8',
        'bold' => true,
        'color' => 'FFFFFF',
        'size' => 11,
    ];
    
    $table->addRow();
    $table->addCell(7500, array_merge($granTotalStyle, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END]))->addText('TOTAL GENERAL');
    $table->addCell(2000, array_merge($granTotalStyle, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END]))->addText(
        '$ ' . number_format($total_general, 0, ',', '.')
    );
    
    // Descargar archivo
    $filename = 'informe_viajes_' . date('Y-m-d') . '.docx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = IOFactory::createWriter($phpWord, 'Word2007');
    $writer->save('php://output');
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
        
        .filtro-group input, .filtro-group select {
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
        
        .crear-tabla-section {
            background: white;
            padding: 20px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 2px solid #9c27b0;
        }
        
        .crear-tabla-section h3 {
            color: #9c27b0;
            margin-bottom: 15px;
        }
        
        .crear-tabla-form {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        
        .crear-tabla-form input {
            padding: 10px 15px;
            border: 1px solid #dadce0;
            border-radius: 8px;
            font-size: 14px;
            min-width: 250px;
        }
        
        .btn-crear-tabla {
            background: #9c27b0;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-crear-tabla:hover { background: #7b1fa2; }
        
        .tabla-personalizada {
            background: white;
            border-radius: 12px;
            margin-bottom: 30px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border: 2px solid #9c27b0;
        }
        
        .tabla-personalizada-header {
            background: #9c27b0;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .tabla-personalizada-header h2 { font-size: 18px; }
        
        .btn-eliminar-tabla {
            background: #ea4335;
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
        }
        .btn-eliminar-tabla:hover { background: #c62828; }
        
        .btn-renombrar-tabla {
            background: #ff9800;
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            margin-right: 10px;
        }
        .btn-renombrar-tabla:hover { background: #e65100; }
        
        .tabla-personalizada-header .acciones-header {
            display: flex;
            gap: 10px;
            align-items: center;
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
        
        .btn-mover-tabla {
            background: #9c27b0;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
        }
        .btn-mover-tabla:disabled { background: #ccc; cursor: not-allowed; }
        
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
        
        .btn-eliminar-viaje {
            background: #ea4335;
            color: white;
            border: none;
            padding: 4px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
        }
        
        .selector-tabla-destino {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #9c27b0;
            font-size: 12px;
        }
        
        select, input, button { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
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
        
        .renombrar-input {
            padding: 6px 12px;
            border: 1px solid white;
            border-radius: 6px;
            font-size: 12px;
            width: 180px;
            margin-right: 5px;
        }
        
        .btn-confirmar-renombrar {
            background: white;
            color: #9c27b0;
            border: none;
            padding: 5px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
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
        
        .total-editable {
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: flex-end;
            padding: 10px 15px;
            background: #f5f5f5;
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
            .crear-tabla-form { flex-direction: column; }
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
                    <button type="submit" class="btn-word">📄 Generar Word (DOCX)</button>
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
        
        <!-- SECCIÓN CREAR TABLA PERSONALIZADA -->
        <div class="crear-tabla-section">
            <h3>📋 Crear Nueva Tabla Personalizada</h3>
            <form method="POST" class="crear-tabla-form">
                <input type="hidden" name="action" value="crear_tabla">
                <div class="filtro-group">
                    <label>Nombre de la tabla</label>
                    <input type="text" name="nombre_tabla" placeholder="Ej: Gastos Urgencias, Viáticos Especiales..." required>
                </div>
                <button type="submit" class="btn-crear-tabla">➕ Crear Tabla</button>
            </form>
        </div>
        
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
                        <a href="?" class="btn-limpiar" style="display:inline-block;text-decoration:none;color:white;">🗑️ Limpiar</a>
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
        
        <!-- TABLAS PERSONALIZADAS -->
        <?php foreach ($_SESSION['tablas_personalizadas'] as $nombre => $tabla): 
            $tabla_id = preg_replace('/[^a-zA-Z0-9]/', '_', $nombre);
            $viajes_tabla = $tabla['viajes'];
            $total_calculado_tabla = array_sum(array_column($viajes_tabla, 'costo'));
            $key_tabla = 'tabla__' . $nombre;
            $total_efectivo_tabla = obtenerTotalEfectivo($key_tabla, $total_calculado_tabla);
            $es_manual_tabla = isset($_SESSION['totales_manuales'][$key_tabla]);
        ?>
        <div class="tabla-personalizada" id="tabla_pers_<?php echo $tabla_id; ?>">
            <div class="tabla-personalizada-header">
                <h2>📋 <?php echo htmlspecialchars($nombre); ?></h2>
                <div class="acciones-header">
                    <button class="btn-renombrar-tabla" onclick="mostrarRenombrar('<?php echo $tabla_id; ?>')">✏️ Renombrar</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="eliminar_tabla">
                        <input type="hidden" name="tabla_nombre" value="<?php echo htmlspecialchars($nombre); ?>">
                        <button type="submit" class="btn-eliminar-tabla" onclick="return confirm('¿Eliminar la tabla <?php echo htmlspecialchars($nombre); ?> y todos sus viajes?')">🗑️ Eliminar Tabla</button>
                    </form>
                    <div id="renombrar_<?php echo $tabla_id; ?>" style="display:none;">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="renombrar_tabla">
                            <input type="hidden" name="tabla_original" value="<?php echo htmlspecialchars($nombre); ?>">
                            <input type="text" name="tabla_nuevo_nombre" class="renombrar-input" placeholder="Nuevo nombre..." required>
                            <button type="submit" class="btn-confirmar-renombrar">✓</button>
                            <button type="button" class="btn-confirmar-renombrar" style="background:#ccc;color:#333;" onclick="ocultarRenombrar('<?php echo $tabla_id; ?>')">✕</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <?php if (empty($viajes_tabla)): ?>
            <div class="sin-datos">📭 No hay viajes en esta tabla. Usa los botones "Mover a..." en las tablas de empresas para agregar viajes aquí.</div>
            <?php else: ?>
            <div style="overflow-x: auto; max-width: 100%;">
                <table style="min-width: 1000px;">
                    <thead>
                        <tr>
                            <th>#</th><th>Fecha</th><th>Conductor</th><th>Cédula</th><th>Ruta</th><th>Tipo</th>
                            <th>Empresa Origen</th><th>Clasificación</th><th>Valor</th><th>Acumulado</th><th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $idx = 0; $acum = 0; foreach ($viajes_tabla as $index => $viaje): $idx++; $acum += $viaje['costo']; ?>
                        <tr>
                            <td style="text-align: center;"><?php echo $idx; ?></td>
                            <td style="white-space: nowrap;"><?php echo $viaje['fecha'] ? date('d/m/Y', strtotime($viaje['fecha'])) : '-'; ?></td>
                            <td><strong><?php echo htmlspecialchars($viaje['nombre'] ?? '-'); ?></strong></td>
                            <td><?php echo htmlspecialchars($viaje['cedula'] ?? '-'); ?></td>
                            <td class="ruta-cell"><?php echo htmlspecialchars($viaje['ruta'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($viaje['tipo_vehiculo'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($viaje['empresa'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($viaje['clasificacion'] ?? '-'); ?></td>
                            <td class="costo">$ <?php echo number_format($viaje['costo'], 0, ',', '.'); ?></td>
                            <td class="acumulado">$ <?php echo number_format($acum, 0, ',', '.'); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="eliminar_viaje_tabla">
                                    <input type="hidden" name="tabla_nombre" value="<?php echo htmlspecialchars($nombre); ?>">
                                    <input type="hidden" name="viaje_index" value="<?php echo $index; ?>">
                                    <button type="submit" class="btn-eliminar-viaje">✖</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="total-editable">
                <strong>TOTAL <?php echo htmlspecialchars($nombre); ?>:</strong>
                <?php if ($es_manual_tabla): ?>
                <span style="font-size:11px;color:#999;text-decoration:line-through;">$ <?php echo number_format($total_calculado_tabla, 0, ',', '.'); ?></span>
                <?php endif; ?>
                <span style="font-weight:700;margin-right:10px;">$ <?php echo number_format($total_efectivo_tabla, 0, ',', '.'); ?></span>
                <form method="POST" style="display: inline-flex; align-items: center; gap: 6px;">
                    <input type="hidden" name="action" value="guardar_total_tabla">
                    <input type="hidden" name="total_key" value="<?php echo htmlspecialchars($key_tabla); ?>">
                    <input type="number" name="total_valor" value="<?php echo $total_efectivo_tabla; ?>" step="1" style="width:110px;padding:4px 8px;border:1px solid #9c27b0;border-radius:6px;text-align:right;font-weight:600;font-size:12px;">
                    <button type="submit" class="btn-guardar-total" style="background:#9c27b0;">💾 Guardar</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        
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
                                    <button type="submit" class="btn-eliminar-viaje">✖</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="total-editable" style="background:#ffe0b2;">
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
                
                $tablas_disponibles = array_keys($_SESSION['tablas_personalizadas']);
                
                if ($data['tipo'] === 'multiple'):
                    $total_general = $data['total_general'];
                    $total_general_efectivo = $data['total_general_efectivo'];
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
                            $key_tabla_empresa = $tabla['key'];
                            $excede = $total_efectivo > $PRESUPUESTO_BASE;
                            $es_manual = isset($_SESSION['totales_manuales'][$key_tabla_empresa]);
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
                                    <?php if (!empty($tablas_disponibles)): ?>
                                    <select class="selector-tabla-destino" id="select_tabla_<?php echo $empresa_id; ?>">
                                        <option value="">Mover a tabla...</option>
                                        <?php foreach ($tablas_disponibles as $td): ?>
                                        <option value="<?php echo htmlspecialchars($td); ?>"><?php echo htmlspecialchars($td); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn-mover-tabla" 
                                            onclick="moverSeleccionadosATabla('<?php echo $empresa_id; ?>')"
                                            id="btn-mover-tabla-<?php echo $empresa_id; ?>" disabled>
                                        ➡️ Mover
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn-mover-extras" 
                                            onclick="moverSeleccionados('<?php echo $empresa_id; ?>')"
                                            id="btn-mover-<?php echo $empresa_id; ?>">
                                        ⭐ Mover a Extras
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
                            
                            <div style="background:#e8f0fe;padding:12px 20px;display:flex;align-items:center;justify-content:flex-end;gap:12px;flex-wrap:wrap;">
                                <strong>TOTAL <?php echo htmlspecialchars($conductor_nombre); ?>:</strong>
                                <?php if ($es_manual): ?>
                                <span style="font-size:11px;color:#999;text-decoration:line-through;">$ <?php echo number_format($total_calculado, 0, ',', '.'); ?></span>
                                <span style="color:#e65100;">➜</span>
                                <?php endif; ?>
                                <span style="font-weight:700;font-size:14px;color:#1a73e8;">$ <?php echo number_format($total_efectivo, 0, ',', '.'); ?></span>
                                <form method="POST" style="display:inline-flex;align-items:center;gap:6px;">
                                    <input type="hidden" name="action" value="guardar_total_manual">
                                    <input type="hidden" name="total_key" value="<?php echo htmlspecialchars($key_tabla_empresa); ?>">
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
                                    actualizarBotones(empresaId);
                                });
                                
                                checkbox.addEventListener('mouseenter', () => {
                                    if (isDragging) {
                                        const currentIndex = Array.from(checkboxes).indexOf(checkbox);
                                        if (currentIndex !== lastToggledIndex) {
                                            checkbox.checked = !checkbox.checked;
                                            updateSelectAllCheckbox(empresaId);
                                            actualizarBotones(empresaId);
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
                                        actualizarBotones(empresaId);
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
                            
                            function actualizarBotones(empId) {
                                const cbs = document.querySelectorAll(`.fila-check-${empId}`);
                                const checkeados = Array.from(cbs).filter(cb => cb.checked);
                                const btnExtras = document.getElementById(`btn-mover-${empId}`);
                                const btnTabla = document.getElementById(`btn-mover-tabla-${empId}`);
                                const selectTabla = document.getElementById(`select_tabla_${empId}`);
                                
                                if (btnExtras) {
                                    btnExtras.disabled = checkeados.length === 0;
                                    btnExtras.innerHTML = checkeados.length > 0 ? 
                                        `⭐ Mover ${checkeados.length} a Extras` : `⭐ Mover a Extras`;
                                }
                                
                                if (btnTabla && selectTabla) {
                                    btnTabla.disabled = checkeados.length === 0 || selectTabla.value === '';
                                    btnTabla.innerHTML = checkeados.length > 0 && selectTabla.value !== '' ? 
                                        `➡️ Mover ${checkeados.length} a "${selectTabla.value}"` : `➡️ Mover`;
                                }
                            }
                            
                            const selectTabla = document.getElementById(`select_tabla_${empresaId}`);
                            if (selectTabla) {
                                selectTabla.addEventListener('change', () => actualizarBotones(empresaId));
                            }
                            
                            window.updateSelectAllCheckbox = updateSelectAllCheckbox;
                            window.actualizarBotones = actualizarBotones;
                            
                            window.toggleSeleccionarTodos = function(checkbox, empId) {
                                const cbs = document.querySelectorAll(`.fila-check-${empId}`);
                                cbs.forEach(cb => cb.checked = checkbox.checked);
                                updateSelectAllCheckbox(empId);
                                actualizarBotones(empId);
                            };
                            
                            window.moverSeleccionadosATabla = function(empId) {
                                const select = document.getElementById(`select_tabla_${empId}`);
                                if (!select || select.value === '') {
                                    alert('Selecciona una tabla de destino');
                                    return;
                                }
                                
                                const cbs = document.querySelectorAll(`.fila-check-${empId}`);
                                const idsSeleccionados = Array.from(cbs).filter(cb => cb.checked).map(cb => cb.value);
                                if (idsSeleccionados.length === 0) return;
                                
                                const form = document.createElement('form');
                                form.method = 'POST';
                                
                                const actionInput = document.createElement('input');
                                actionInput.type = 'hidden';
                                actionInput.name = 'action';
                                actionInput.value = 'mover_a_tabla';
                                form.appendChild(actionInput);
                                
                                const empresaInput = document.createElement('input');
                                empresaInput.type = 'hidden';
                                empresaInput.name = 'empresa_origen';
                                empresaInput.value = '<?php echo htmlspecialchars($empresa_actual); ?>';
                                form.appendChild(empresaInput);
                                
                                const tablaInput = document.createElement('input');
                                tablaInput.type = 'hidden';
                                tablaInput.name = 'tabla_destino';
                                tablaInput.value = select.value;
                                form.appendChild(tablaInput);
                                
                                const idsInput = document.createElement('input');
                                idsInput.type = 'hidden';
                                idsInput.name = 'ids_seleccionados';
                                idsInput.value = idsSeleccionados.join(',');
                                form.appendChild(idsInput);
                                
                                document.body.appendChild(form);
                                form.submit();
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
                                    actualizarBotones(empresaId);
                                });
                            });
                            actualizarBotones(empresaId);
                        })();
                        </script>
                        <?php endforeach; ?>
                        
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
                                if (typeof actualizarBotones === 'function') actualizarBotones(subId);
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
                    $key_tabla_empresa = $data['key'];
                    $es_manual = isset($_SESSION['totales_manuales'][$key_tabla_empresa]);
                    $tablas_disponibles = array_keys($_SESSION['tablas_personalizadas']);
                    
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
                                <?php if (!empty($tablas_disponibles)): ?>
                                <select class="selector-tabla-destino" id="select_tabla_<?php echo $empresa_id; ?>">
                                    <option value="">Mover a tabla...</option>
                                    <?php foreach ($tablas_disponibles as $td): ?>
                                    <option value="<?php echo htmlspecialchars($td); ?>"><?php echo htmlspecialchars($td); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn-mover-tabla" 
                                        onclick="moverSeleccionadosATabla('<?php echo $empresa_id; ?>')"
                                        id="btn-mover-tabla-<?php echo $empresa_id; ?>" disabled>
                                    ➡️ Mover
                                </button>
                                <?php endif; ?>
                                <button type="button" class="btn-mover-extras" 
                                        onclick="moverSeleccionados('<?php echo $empresa_id; ?>')"
                                        id="btn-mover-<?php echo $empresa_id; ?>">
                                    ⭐ Mover a Extras
                                </button>
                            </div>
                        </div>
                    </div>
                    
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
                    
                    <div style="background:#e8f0fe;padding:12px 20px;display:flex;align-items:center;justify-content:flex-end;gap:12px;flex-wrap:wrap;">
                        <strong>TOTAL <?php echo htmlspecialchars($empresa_actual); ?>:</strong>
                        <?php if ($es_manual): ?>
                        <span style="font-size:11px;color:#999;text-decoration:line-through;">$ <?php echo number_format($total_calculado, 0, ',', '.'); ?></span>
                        <span style="color:#e65100;">➜</span>
                        <?php endif; ?>
                        <span style="font-weight:700;font-size:14px;color:#1a73e8;">$ <?php echo number_format($total_efectivo, 0, ',', '.'); ?></span>
                        <form method="POST" style="display:inline-flex;align-items:center;gap:6px;">
                            <input type="hidden" name="action" value="guardar_total_manual">
                            <input type="hidden" name="total_key" value="<?php echo htmlspecialchars($key_tabla_empresa); ?>">
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
                            actualizarBotones(empresaId);
                        });
                        
                        checkbox.addEventListener('mouseenter', () => {
                            if (isDragging) {
                                const currentIndex = Array.from(checkboxes).indexOf(checkbox);
                                if (currentIndex !== lastToggledIndex) {
                                    checkbox.checked = !checkbox.checked;
                                    updateSelectAllCheckbox(empresaId);
                                    actualizarBotones(empresaId);
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
                                actualizarBotones(empresaId);
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
                    
                    function actualizarBotones(empId) {
                        const cbs = document.querySelectorAll(`.fila-check-${empId}`);
                        const checkeados = Array.from(cbs).filter(cb => cb.checked);
                        const btnExtras = document.getElementById(`btn-mover-${empId}`);
                        const btnTabla = document.getElementById(`btn-mover-tabla-${empId}`);
                        const selectTabla = document.getElementById(`select_tabla_${empId}`);
                        
                        if (btnExtras) {
                            btnExtras.disabled = checkeados.length === 0;
                            btnExtras.innerHTML = checkeados.length > 0 ? 
                                `⭐ Mover ${checkeados.length} a Extras` : `⭐ Mover a Extras`;
                        }
                        
                        if (btnTabla && selectTabla) {
                            btnTabla.disabled = checkeados.length === 0 || selectTabla.value === '';
                            btnTabla.innerHTML = checkeados.length > 0 && selectTabla.value !== '' ? 
                                `➡️ Mover ${checkeados.length} a "${selectTabla.value}"` : `➡️ Mover`;
                        }
                    }
                    
                    const selectTabla = document.getElementById(`select_tabla_${empresaId}`);
                    if (selectTabla) {
                        selectTabla.addEventListener('change', () => actualizarBotones(empresaId));
                    }
                    
                    window.updateSelectAllCheckbox = updateSelectAllCheckbox;
                    window.actualizarBotones = actualizarBotones;
                    
                    window.toggleSeleccionarTodos = function(checkbox, empId) {
                        const cbs = document.querySelectorAll(`.fila-check-${empId}`);
                        cbs.forEach(cb => cb.checked = checkbox.checked);
                        updateSelectAllCheckbox(empId);
                        actualizarBotones(empId);
                    };
                    
                    window.moverSeleccionadosATabla = function(empId) {
                        const select = document.getElementById(`select_tabla_${empId}`);
                        if (!select || select.value === '') {
                            alert('Selecciona una tabla de destino');
                            return;
                        }
                        
                        const cbs = document.querySelectorAll(`.fila-check-${empId}`);
                        const idsSeleccionados = Array.from(cbs).filter(cb => cb.checked).map(cb => cb.value);
                        if (idsSeleccionados.length === 0) return;
                        
                        const form = document.createElement('form');
                        form.method = 'POST';
                        
                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'action';
                        actionInput.value = 'mover_a_tabla';
                        form.appendChild(actionInput);
                        
                        const empresaInput = document.createElement('input');
                        empresaInput.type = 'hidden';
                        empresaInput.name = 'empresa_origen';
                        empresaInput.value = '<?php echo htmlspecialchars($empresa_actual); ?>';
                        form.appendChild(empresaInput);
                        
                        const tablaInput = document.createElement('input');
                        tablaInput.type = 'hidden';
                        tablaInput.name = 'tabla_destino';
                        tablaInput.value = select.value;
                        form.appendChild(tablaInput);
                        
                        const idsInput = document.createElement('input');
                        idsInput.type = 'hidden';
                        idsInput.name = 'ids_seleccionados';
                        idsInput.value = idsSeleccionados.join(',');
                        form.appendChild(idsInput);
                        
                        document.body.appendChild(form);
                        form.submit();
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
                            actualizarBotones(empresaId);
                        });
                    });
                    actualizarBotones(empresaId);
                    
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
                        actualizarBotones(empId);
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
        
        <!-- TABLA RESUME GENERAL -->
        <div class="resumen-table">
            <div class="resumen-header">📋 RESUMEN GENERAL</div>
            <div style="overflow-x: auto; max-width: 100%;">
                <table style="min-width: 600px;">
                    <thead>
                        <tr>
                            <th>Concepto</th>
                            <th style="text-align:right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resumen_empresas as $empresa => $total): ?>
                        <tr>
                            <td>🏥 <?php echo htmlspecialchars($empresa); ?></td>
                            <td class="costo">$ <?php echo number_format($total, 0, ',', '.'); ?></td>
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
                        <?php foreach ($totales_tablas as $nombre => $total): ?>
                        <tr>
                            <td>📋 <?php echo htmlspecialchars($nombre); ?></td>
                            <td class="costo">$ <?php echo number_format($total['efectivo'], 0, ',', '.'); ?></td>
                        </tr>
                        <?php endforeach; ?>
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
            let idTabla = 'tabla_' + empresa.replace(/[^a-zA-Z0-9]/g, '_');
            let elemento = document.getElementById(idTabla);
            if (!elemento) {
                const empresaParts = empresa.split(' - ');
                if (empresaParts.length > 1) {
                    const nombreTabla = empresaParts[1].trim();
                    idTabla = 'tabla_pers_' + nombreTabla.replace(/[^a-zA-Z0-9]/g, '_');
                    elemento = document.getElementById(idTabla);
                }
            }
            if (elemento) {
                elemento.scrollIntoView({ behavior: 'smooth', block: 'start' });
                elemento.style.transition = 'box-shadow 0.3s';
                elemento.style.boxShadow = '0 0 0 3px #ff9800, 0 4px 12px rgba(0,0,0,0.15)';
                setTimeout(() => {
                    elemento.style.boxShadow = '';
                }, 2000);
            }
        }
        
        function mostrarRenombrar(tablaId) {
            document.getElementById('renombrar_' + tablaId).style.display = 'inline-block';
        }
        
        
        function ocultarRenombrar(tablaId) {
            document.getElementById('renombrar_' + tablaId).style.display = 'none';
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