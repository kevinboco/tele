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

// Procesar acción de mover a extras
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'mover_extras' && isset($_POST['ids_seleccionados'])) {
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
}

// Obtener parámetros
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';
$empresas_seleccionadas = isset($_GET['empresas']) ? $_GET['empresas'] : array();

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

function calcular_acumulado_extras($extras) {
    $acumulado = 0;
    $resultados = array();
    foreach ($extras as $index => $extra) {
        $acumulado += $extra['costo'];
        $resultados[] = array('index' => $index, 'data' => $extra, 'acumulado' => $acumulado);
    }
    return $resultados;
}

$extras_con_acumulado = calcular_acumulado_extras($_SESSION['extras']);
$total_extras = array_sum(array_column($_SESSION['extras'], 'costo'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe de Viajes - Selección por deslizamiento</title>
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
        }
        
        .header h1 { font-size: 24px; margin-bottom: 5px; }
        .header p { opacity: 0.9; font-size: 14px; }
        
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
        
        /* Tabla de Extras */
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
        
        /* Tabla de empresa */
        .empresa-table {
            background: white;
            border-radius: 12px;
            margin-bottom: 30px;
            overflow-x: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
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
        
        .acciones-header { display: flex; gap: 10px; }
        
        .btn-mover-extras {
            background: #ff9800;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-mover-extras:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .btn-eliminar-extra {
            background: #ea4335;
            color: white;
            border: none;
            padding: 4px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
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
        
        /* Anchos fijos para columnas */
        th:nth-child(1), td:nth-child(1) { width: 30px; text-align: center; }  /* checkbox */
        th:nth-child(2), td:nth-child(2) { width: 40px; text-align: center; }  /* # */
        th:nth-child(3), td:nth-child(3) { width: 100px; }  /* Fecha */
        th:nth-child(4), td:nth-child(4) { width: 180px; }  /* Conductor */
        th:nth-child(5), td:nth-child(5) { width: 100px; }  /* Cédula */
        th:nth-child(6), td:nth-child(6) { min-width: 200px; max-width: 250px; }  /* Ruta */
        th:nth-child(7), td:nth-child(7) { width: 90px; }  /* Tipo */
        th:nth-child(8), td:nth-child(8) { width: 110px; }  /* Clasificación */
        th:nth-child(9), td:nth-child(9) { width: 120px; text-align: right; }  /* Valor */
        th:nth-child(10), td:nth-child(10) { width: 120px; text-align: right; }  /* Acumulado */
        
        tr.seleccionado { background: #e3f2fd; }
        tr:hover { background: #f8f9fa; }
        
        .costo { font-weight: 600; color: #1a73e8; text-align: right !important; }
        .acumulado { font-weight: 700; color: #34a853; text-align: right !important; }
        
        .checkbox-col { text-align: center; }
        .checkbox-col input { width: 16px; height: 16px; cursor: pointer; }
        
        .sin-datos { text-align: center; padding: 40px; color: #5f6368; }
        
        .drag-instruction {
            font-size: 11px;
            background: rgba(255,255,255,0.2);
            padding: 5px 12px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .ruta-cell {
            max-width: 250px;
            white-space: normal;
            word-break: break-word;
            line-height: 1.4;
        }
        
        @media (max-width: 1200px) {
            .table-header { flex-direction: column; text-align: center; }
            .acciones-header { justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Informe de Viajes por Puesto de Salud</h1>
            <p>✨ Arrastra el mouse sobre los checkboxes para seleccionar múltiples filas | Shift + Click para seleccionar rango</p>
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
                <form method="POST" style="display: inline;" id="formLimpiarExtras">
                    <input type="hidden" name="action" value="limpiar_extras">
                    <button type="submit" class="btn-limpiar-extras">🗑️ Limpiar todas</button>
                </form>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Conductor</th>
                        <th>Cédula</th>
                        <th>Ruta</th>
                        <th>Tipo</th>
                        <th>Empresa Origen</th>
                        <th>Clasificación</th>
                        <th>Valor</th>
                        <th>Acumulado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $idx_extra = 0;
                    foreach ($extras_con_acumulado as $extra):
                        $row = $extra['data'];
                        $acum = $extra['acumulado'];
                    ?>
                    <tr>
                        <td><?php echo $idx_extra + 1; ?></td>
                        <td style="white-space: nowrap;"><?php echo $row['fecha'] ? date('d/m/Y', strtotime($row['fecha'])) : '-'; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['nombre'] ?? '-'); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['cedula'] ?? '-'); ?></td>
                        <td class="ruta-cell"><?php echo htmlspecialchars($row['ruta'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['tipo_vehiculo'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['empresa'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['clasificacion'] ?? '-'); ?></td>
                        <td class="costo">$ <?php echo number_format($row['costo'], 0, ',', '.'); ?></td>
                        <td class="acumulado">$ <?php echo number_format($acum, 0, ',', '.'); ?></td>
                        <td>
                            <form method="POST" style="display: inline;" class="form-eliminar-extra">
                                <input type="hidden" name="action" value="eliminar_extra">
                                <input type="hidden" name="extra_index" value="<?php echo $extra['index']; ?>">
                                <button type="submit" class="btn-eliminar-extra">✖</button>
                            </form>
                        </td>
                    </tr>
                    <?php 
                    $idx_extra++;
                    endforeach; 
                    ?>
                    <tr style="background: #ffe0b2; font-weight: bold;">
                        <td colspan="9" style="text-align: right;">TOTAL EXTRAS:</td>
                        <td class="costo">$ <?php echo number_format($total_extras, 0, ',', '.'); ?></td>
                        <td class="acumulado">$ <?php echo number_format($total_extras, 0, ',', '.'); ?></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- TABLAS POR EMPRESA -->
        <?php
        if (!empty($empresas_seleccionadas)) {
            foreach ($empresas_seleccionadas as $empresa_actual) {
                $empresa_id = 'emp_' . preg_replace('/[^a-zA-Z0-9]/', '_', $empresa_actual);
                
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
                
                $ids_en_extras = array_column($_SESSION['extras'], 'id');
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
                    
                    if ($result && $result->num_rows > 0):
                        $rows_data = array();
                        $acumulado = 0;
                        while ($row = $result->fetch_assoc()) {
                            $clasificacion = $row['clasificacion'];
                            $costo = obtener_tarifa($clasificacion, $row['tipo_vehiculo'], $row['empresa'], $conn);
                            $acumulado += $costo;
                            $rows_data[] = array(
                                'id' => $row['id'], 
                                'fecha' => $row['fecha'], 
                                'nombre' => $row['nombre'],
                                'cedula' => $row['cedula'], 
                                'ruta' => $row['ruta'], 
                                'tipo_vehiculo' => $row['tipo_vehiculo'],
                                'empresa' => $row['empresa'], 
                                'clasificacion' => $clasificacion,
                                'costo' => $costo, 
                                'acumulado' => $acumulado
                            );
                        }
                        $total_empresa = $acumulado;
                        ?>
                        <div class="empresa-table">
                            <div class="table-header">
                                <h2>🏥 <?php echo htmlspecialchars($empresa_actual); ?></h2>
                                <div style="display: flex; gap: 15px; align-items: center;">
                                    <div class="drag-instruction">
                                        🖱️ Arrastra sobre los checkboxes
                                    </div>
                                    <div class="acciones-header">
                                        <button type="button" class="btn-mover-extras" 
                                                onclick="moverSeleccionados('<?php echo $empresa_id; ?>')"
                                                id="btn-mover-<?php echo $empresa_id; ?>">
                                            ➡️ Mover seleccionados a EXTRAS
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <form method="POST" id="form-<?php echo $empresa_id; ?>">
                                <input type="hidden" name="action" value="mover_extras">
                                <input type="hidden" name="empresa_origen" value="<?php echo htmlspecialchars($empresa_actual); ?>">
                                <input type="hidden" name="ids_seleccionados" id="ids-<?php echo $empresa_id; ?>">
                                <table id="table-<?php echo $empresa_id; ?>">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" id="select-all-<?php echo $empresa_id; ?>" onchange="toggleSeleccionarTodos(this, '<?php echo $empresa_id; ?>')"></th>
                                            <th>#</th>
                                            <th>Fecha</th>
                                            <th>Conductor</th>
                                            <th>Cédula</th>
                                            <th>Ruta</th>
                                            <th>Tipo</th>
                                            <th>Clasificación</th>
                                            <th>Valor Viaje</th>
                                            <th>Acumulado</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-<?php echo $empresa_id; ?>">
                                        <?php $contador = 0; foreach ($rows_data as $row): $contador++; ?>
                                        <tr>
                                            <td class="checkbox-col">
                                                <input type="checkbox" class="fila-check-<?php echo $empresa_id; ?>" value="<?php echo $row['id']; ?>">
                                            </td>
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
                                        <tr style="background: #e8f0fe; font-weight: bold;">
                                            <td colspan="8" style="text-align: right;">TOTAL <?php echo htmlspecialchars($empresa_actual); ?>:</td>
                                            <td class="costo">$ <?php echo number_format($total_empresa, 0, ',', '.'); ?></td>
                                            <td class="acumulado">$ <?php echo number_format($total_empresa, 0, ',', '.'); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </form>
                        </div>
                        
                        <script>
                        (function() {
                            const empresaId = '<?php echo $empresa_id; ?>';
                            const checkboxes = document.querySelectorAll(`.fila-check-${empresaId}`);
                            
                            // Drag to Select
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
                            
                            // Shift + Click
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
                        <?php
                    else:
                        ?>
                        <div class="empresa-table">
                            <div class="table-header">
                                <h2>🏥 <?php echo htmlspecialchars($empresa_actual); ?></h2>
                            </div>
                            <div class="sin-datos">📭 No hay viajes registrados para este período</div>
                        </div>
                        <?php
                    endif;
                    $stmt->close();
                }
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
    </div>
    
    <script>
        function seleccionarTodas(seleccionar) {
            const checkboxes = document.querySelectorAll('#empresasGrid input[type="checkbox"]');
            checkboxes.forEach(cb => cb.checked = seleccionar);
            document.getElementById('filtroForm').submit();
        }
        
        document.querySelectorAll('#empresasGrid input[type="checkbox"]').forEach(cb => {
            cb.addEventListener('change', () => document.getElementById('filtroForm').submit());
        });
    </script>
</body>
</html>