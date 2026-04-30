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
            // Obtener datos del viaje
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
                // Agregar a extras
                $_SESSION['extras'][] = $row;
            }
            $stmt->close();
        }
        
        // Redirigir para evitar reenvío del POST
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

// Función para obtener tarifa
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
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param("ss", $empresa, $tipo_vehiculo);
    $stmt->execute();
    $result = $stmt->get_result();
    $tarifa = $result->fetch_assoc();
    $stmt->close();
    
    if (!$tarifa) {
        return 0;
    }
    
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
$sql_emp = "SELECT DISTINCT empresa 
            FROM viajes 
            WHERE empresa IS NOT NULL 
            AND empresa != '' 
            AND empresa LIKE 'P.%' 
            ORDER BY empresa";
$res_emp = $conn->query($sql_emp);
if ($res_emp) {
    while ($row = $res_emp->fetch_assoc()) {
        $empresas_disponibles[] = $row['empresa'];
    }
}

// Procesar extras para cálculo de acumulado
function calcular_acumulado_extras($extras) {
    $acumulado = 0;
    $resultados = array();
    foreach ($extras as $index => $extra) {
        $acumulado += $extra['costo'];
        $resultados[] = array(
            'index' => $index,
            'data' => $extra,
            'acumulado' => $acumulado
        );
    }
    return $resultados;
}

$extras_con_acumulado = calcular_acumulado_extras($_SESSION['extras']);
$total_extras = array_sum(array_column($_SESSION['extras'], 'costo'));

// Generar mapa de IDs para JavaScript
$empresa_ids_map = array();
foreach ($empresas_seleccionadas as $emp) {
    $empresa_ids_map[$emp] = 'emp_' . preg_replace('/[^a-zA-Z0-9]/', '_', $emp);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe de Viajes con Extras</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
        }
        
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
        
        .btn-filtrar {
            background: #1a73e8;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-limpiar {
            background: #5f6368;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
        }
        
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
        
        .btn-group {
            display: flex;
            gap: 10px;
        }
        
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
            overflow: hidden;
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
        
        .extras-header h2 {
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            overflow: hidden;
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
        
        .acciones-header {
            display: flex;
            gap: 10px;
        }
        
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
        }
        
        th {
            background: #f8f9fa;
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
            color: #5f6368;
            border-bottom: 1px solid #dadce0;
            font-size: 12px;
        }
        
        td {
            padding: 10px;
            border-bottom: 1px solid #e8eaed;
            color: #202124;
            font-size: 12px;
        }
        
        tr.seleccionado {
            background: #e3f2fd;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .costo { font-weight: 600; color: #1a73e8; }
        .acumulado { font-weight: 700; color: #34a853; }
        
        .checkbox-col {
            width: 30px;
            text-align: center;
        }
        
        .checkbox-col input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .sin-datos {
            text-align: center;
            padding: 40px;
            color: #5f6368;
        }
        
        @media (max-width: 768px) {
            .filtros-row { flex-direction: column; align-items: stretch; }
            .filtro-group input { min-width: auto; }
            .table-header { flex-direction: column; text-align: center; }
            .acciones-header { justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Informe de Viajes por Puesto de Salud</h1>
            <p>Selecciona filas y muévelas a la tabla EXTRAS</p>
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
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="limpiar_extras">
                    <button type="submit" class="btn-limpiar-extras" onclick="return confirm('¿Limpiar todas las extras?')">🗑️ Limpiar todas</button>
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
                        <td><?php echo $row['fecha'] ? date('d/m/Y', strtotime($row['fecha'])) : '-'; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['nombre'] ?? '-'); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['cedula'] ?? '-'); ?></td>
                        <td style="max-width: 250px; word-break: break-word;"><?php echo htmlspecialchars($row['ruta'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['tipo_vehiculo'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['empresa'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['clasificacion'] ?? '-'); ?></td>
                        <td class="costo">$ <?php echo number_format($row['costo'], 0, ',', '.'); ?></td>
                        <td class="acumulado">$ <?php echo number_format($acum, 0, ',', '.'); ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="eliminar_extra">
                                <input type="hidden" name="extra_index" value="<?php echo $extra['index']; ?>">
                                <button type="submit" class="btn-eliminar-extra" onclick="return confirm('¿Eliminar este registro de extras?')">✖</button>
                            </form>
                        </td>
                    </tr>
                    <?php 
                    $idx_extra++;
                    endforeach; 
                    ?>
                    <tr style="background: #ffe0b2; font-weight: bold;">
                        <td colspan="8" style="text-align: right;">TOTAL EXTRAS:</td>
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
                // Generar ID único para esta empresa
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
                
                // Excluir IDs que ya están en extras
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
                        <div class="empresa-table" data-empresa="<?php echo htmlspecialchars($empresa_actual); ?>">
                            <div class="table-header">
                                <h2>🏥 <?php echo htmlspecialchars($empresa_actual); ?></h2>
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
                                </table>
                                    <thead>
                                        <tr>
                                            <th class="checkbox-col"><input type="checkbox" id="select-all-<?php echo $empresa_id; ?>" onchange="toggleSeleccionarTodos(this, '<?php echo $empresa_id; ?>')"></th>
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
                                    <tbody>
                                        <?php 
                                        $contador = 0;
                                        foreach ($rows_data as $row): 
                                            $contador++;
                                        ?>
                                        <tr>
                                            <td class="checkbox-col">
                                                <input type="checkbox" class="fila-check-<?php echo $empresa_id; ?>" value="<?php echo $row['id']; ?>">
                                            </td>
                                            <td><?php echo $contador; ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($row['fecha'])); ?></td>
                                            <td><strong><?php echo htmlspecialchars($row['nombre'] ?? '-'); ?></strong></td>
                                            <td><?php echo htmlspecialchars($row['cedula'] ?? '-'); ?></td>
                                            <td style="max-width: 250px; word-break: break-word;"><?php echo htmlspecialchars($row['ruta'] ?? '-'); ?></td>
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
        
        function toggleSeleccionarTodos(checkbox, empresaId) {
            const checkboxes = document.querySelectorAll(`.fila-check-${empresaId}`);
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
            actualizarBotonMover(empresaId);
        }
        
        function actualizarBotonMover(empresaId) {
            const checkboxes = document.querySelectorAll(`.fila-check-${empresaId}`);
            const checkeados = Array.from(checkboxes).filter(cb => cb.checked);
            const btn = document.getElementById(`btn-mover-${empresaId}`);
            if (btn) {
                btn.disabled = checkeados.length === 0;
                if (checkeados.length > 0) {
                    btn.innerHTML = `➡️ Mover ${checkeados.length} seleccionado(s) a EXTRAS`;
                } else {
                    btn.innerHTML = `➡️ Mover seleccionados a EXTRAS`;
                }
            }
        }
        
        function moverSeleccionados(empresaId) {
            const checkboxes = document.querySelectorAll(`.fila-check-${empresaId}`);
            const idsSeleccionados = Array.from(checkboxes)
                .filter(cb => cb.checked)
                .map(cb => cb.value);
            
            if (idsSeleccionados.length === 0) {
                alert('Selecciona al menos una fila para mover a EXTRAS');
                return;
            }
            
            if (confirm(`¿Mover ${idsSeleccionados.length} registro(s) a la tabla EXTRAS?`)) {
                document.getElementById(`ids-${empresaId}`).value = idsSeleccionados.join(',');
                document.getElementById(`form-${empresaId}`).submit();
            }
        }
        
        // Inicializar listeners para cada empresa
        <?php foreach ($empresas_seleccionadas as $emp): 
            $emp_id = 'emp_' . preg_replace('/[^a-zA-Z0-9]/', '_', $emp);
        ?>
        (function() {
            const empId = '<?php echo $emp_id; ?>';
            const checkboxes = document.querySelectorAll(`.fila-check-${empId}`);
            checkboxes.forEach(cb => {
                cb.addEventListener('change', () => actualizarBotonMover(empId));
            });
            actualizarBotonMover(empId);
        })();
        <?php endforeach; ?>
    </script>
</body>
</html>