<?php
session_start();

$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }
$conn->set_charset('utf8mb4');

/* = Helpers ================= */
function strip_accents($s){
  $t = @iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);
  if ($t !== false) return $t;
  $repl = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N'];
  return strtr($s,$repl);
}
function norm_person($s){
  $s = strip_accents((string)$s);
  $s = mb_strtolower($s,'UTF-8');
  $s = preg_replace('/[^a-z0-9\s]/',' ', $s);
  $s = preg_replace('/\s+/',' ', trim($s));
  return $s;
}

// Crear tabla de comprobantes temporales si no existe
$conn->query("
CREATE TABLE IF NOT EXISTS comprobantes_temporales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    conductor VARCHAR(255) NOT NULL,
    imagen_base64 LONGTEXT NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_conductor (conductor),
    INDEX idx_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Verificar columnas necesarias
$result = $conn->query("SHOW COLUMNS FROM cuentas_guardadas LIKE 'comprobantes_json'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE cuentas_guardadas ADD COLUMN comprobantes_json LONGTEXT NULL AFTER datos_json");
}

$result = $conn->query("SHOW COLUMNS FROM viajes LIKE 'pagado'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE viajes ADD COLUMN pagado TINYINT(1) NOT NULL DEFAULT 0");
    $conn->query("ALTER TABLE viajes ADD INDEX idx_pagado (pagado)");
}

/* ================= CREAR TABLAS CUENTAS GUARDADAS ================= */
$conn->query("
CREATE TABLE IF NOT EXISTS cuentas_guardadas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(255) NOT NULL,
    desde DATE NOT NULL,
    hasta DATE NOT NULL,
    facturado DECIMAL(15,2) NOT NULL,
    porcentaje_ajuste DECIMAL(5,2) NOT NULL,
    pagado TINYINT(1) NOT NULL DEFAULT 0,
    datos_json LONGTEXT NOT NULL,
    comprobantes_json LONGTEXT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    usuario VARCHAR(100),
    INDEX idx_fecha (fecha_creacion),
    INDEX idx_pagado (pagado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$conn->query("
CREATE TABLE IF NOT EXISTS cuentas_guardadas_empresas (
    cuenta_id INT NOT NULL,
    empresa_nombre VARCHAR(100) NOT NULL,
    PRIMARY KEY (cuenta_id, empresa_nombre),
    FOREIGN KEY (cuenta_id) REFERENCES cuentas_guardadas(id) ON DELETE CASCADE,
    INDEX idx_empresa (empresa_nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

/* ================= AJAX HANDLERS ================= */

// Endpoint para marcar viajes como pagados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'pagar_viajes') {
    header('Content-Type: application/json');
    $viaje_ids = isset($_POST['viaje_ids']) ? json_decode($_POST['viaje_ids'], true) : [];
    if (empty($viaje_ids)) {
        echo json_encode(['success' => false, 'message' => 'No se proporcionaron IDs de viajes']);
        exit;
    }
    $ids_esc = array_map('intval', $viaje_ids);
    $ids_str = implode(',', $ids_esc);
    $sql = "UPDATE viajes SET pagado = 1 WHERE id IN ($ids_str)";
    if ($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => "Se marcaron {$conn->affected_rows} viajes como pagados"]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    exit;
}

// Endpoint para desmarcar viajes como pagados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'despagar_viajes') {
    header('Content-Type: application/json');
    $viaje_ids = isset($_POST['viaje_ids']) ? json_decode($_POST['viaje_ids'], true) : [];
    if (empty($viaje_ids)) {
        echo json_encode(['success' => false, 'message' => 'No se proporcionaron IDs de viajes']);
        exit;
    }
    $ids_esc = array_map('intval', $viaje_ids);
    $ids_str = implode(',', $ids_esc);
    $sql = "UPDATE viajes SET pagado = 0 WHERE id IN ($ids_str)";
    if ($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => "Se desmarcaron {$conn->affected_rows} viajes como no pagados"]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    exit;
}

// Endpoint para exportar Excel
if (isset($_GET['exportar_excel'])) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="ajuste_pago_' . $_GET['desde'] . '_a_' . $_GET['hasta'] . '.xls"');
    $filasData = isset($_POST['filas']) ? json_decode($_POST['filas'], true) : [];
    $totales = isset($_POST['totales']) ? json_decode($_POST['totales'], true) : [];
    $empresas = isset($_POST['empresas']) ? $_POST['empresas'] : '';
    $fechas = isset($_POST['fechas']) ? $_POST['fechas'] : '';
    
    echo '<html><head><meta charset="UTF-8"><style>
        th { background-color: #2563eb; color: white; padding: 8px; border: 1px solid #000; }
        td { padding: 6px 8px; border: 1px solid #ccc; }
        .num { text-align: right; }
        .total-row { font-weight: bold; background-color: #f1f5f9; }
    </style></head><body>';
    echo "<h2>Ajuste de Pago</h2><p>Rango: $fechas</p>";
    echo '<table border="1"><thead><tr><th>#</th><th>Conductor</th><th>Base</th><th>Ajuste</th><th>Llegó</th><th>Ret 3.5%</th><th>4×1000</th><th>Aporte 10%</th><th>Seg Social</th><th>Préstamos</th><th>N° Cuenta</th><th>A Pagar</th><th>Estado</th></tr></thead><tbody>';
    $num = 0;
    foreach ($filasData as $fila) {
        $num++;
        echo "<tr>
            <td>$num</td>
            <td>{$fila['conductor']}</td>
            <td class='num'>{$fila['base']}</td>
            <td class='num'>{$fila['ajuste']}</td>
            <td class='num'>{$fila['llego']}</td>
            <td class='num'>{$fila['ret']}</td>
            <td class='num'>{$fila['mil4']}</td>
            <td class='num'>{$fila['apor']}</td>
            <td class='num'>{$fila['ss']}</td>
            <td class='num'>{$fila['prest']}</td>
            <td>{$fila['cuenta']}</td>
            <td class='num'>{$fila['pagar']}</td>
            <td>{$fila['estado']}</td>
        </tr>";
    }
    if (!empty($totales)) {
        echo "<tr class='total-row'><td colspan='4'><strong>TOTALES</strong></td>
            <td class='num'><strong>{$totales['llego']}</strong></td>
            <td class='num'><strong>{$totales['ret']}</strong></td>
            <td class='num'><strong>{$totales['mil4']}</strong></td>
            <td class='num'><strong>{$totales['apor']}</strong></td>
            <td class='num'><strong>{$totales['ss']}</strong></td>
            <td class='num'><strong>{$totales['prest']}</strong></td>
            <td></td>
            <td class='num'><strong>{$totales['pagar']}</strong></td>
            <td></td>
        </tr>";
    }
    echo '</tbody></table></body></html>';
    exit;
}

// Endpoint para subir comprobante temporal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'subir_comprobante') {
    header('Content-Type: application/json');
    $conductor = $conn->real_escape_string($_POST['conductor'] ?? '');
    $imagen_base64 = $_POST['imagen'] ?? '';
    $session_id = session_id();
    if (empty($conductor) || empty($imagen_base64)) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }
    $conn->query("DELETE FROM comprobantes_temporales WHERE conductor = '$conductor' AND session_id = '$session_id'");
    $sql = "INSERT INTO comprobantes_temporales (conductor, imagen_base64, session_id) VALUES ('$conductor', '$imagen_base64', '$session_id')";
    echo json_encode(['success' => $conn->query($sql)]);
    exit;
}

// Endpoint para eliminar comprobante temporal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar_comprobante') {
    header('Content-Type: application/json');
    $conductor = $conn->real_escape_string($_POST['conductor'] ?? '');
    $session_id = session_id();
    $conn->query("DELETE FROM comprobantes_temporales WHERE conductor = '$conductor' AND session_id = '$session_id'");
    echo json_encode(['success' => true]);
    exit;
}

// Endpoint para obtener comprobantes de la sesión actual
if (isset($_GET['obtener_comprobantes'])) {
    header('Content-Type: application/json');
    $session_id = session_id();
    $result = $conn->query("SELECT conductor, imagen_base64 FROM comprobantes_temporales WHERE session_id = '$session_id'");
    $comprobantes = [];
    while ($row = $result->fetch_assoc()) {
        $comprobantes[$row['conductor']] = $row['imagen_base64'];
    }
    echo json_encode($comprobantes);
    exit;
}

// Obtener cuentas guardadas
if (isset($_GET['obtener_cuentas'])) {
    header('Content-Type: application/json');
    $empresa = $conn->real_escape_string($_GET['empresa'] ?? '');
    $estado = $_GET['estado'] ?? '';
    $sql = "SELECT c.*, GROUP_CONCAT(e.empresa_nombre ORDER BY e.empresa_nombre SEPARATOR '||') as empresas_list
            FROM cuentas_guardadas c
            LEFT JOIN cuentas_guardadas_empresas e ON c.id = e.cuenta_id";
    $where = [];
    if (!empty($empresa)) $where[] = "c.id IN (SELECT cuenta_id FROM cuentas_guardadas_empresas WHERE empresa_nombre = '$empresa')";
    if ($estado !== '') $where[] = "c.pagado = " . intval($estado);
    if (!empty($where)) $sql .= " WHERE " . implode(' AND ', $where);
    $sql .= " GROUP BY c.id ORDER BY c.fecha_creacion DESC";
    $result = $conn->query($sql);
    $cuentas = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $row['pagado'] = (int)$row['pagado'];
            $row['datos_json'] = json_decode($row['datos_json'], true) ?: [];
            $row['comprobantes_json'] = json_decode($row['comprobantes_json'], true) ?: [];
            $row['empresas'] = !empty($row['empresas_list']) ? explode('||', $row['empresas_list']) : [];
            unset($row['empresas_list']);
            $cuentas[] = $row;
        }
    }
    echo json_encode($cuentas, JSON_UNESCAPED_UNICODE);
    exit;
}

// GUARDAR cuenta NUEVA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_cuenta') {
    header('Content-Type: application/json');
    $nombre = $conn->real_escape_string($_POST['nombre'] ?? '');
    $desde = $conn->real_escape_string($_POST['desde'] ?? '');
    $hasta = $conn->real_escape_string($_POST['hasta'] ?? '');
    $facturado = floatval($_POST['facturado'] ?? 0);
    $porcentaje_ajuste = floatval($_POST['porcentaje_ajuste'] ?? 0);
    $pagado = intval($_POST['pagado'] ?? 0);
    $datos_json = $conn->real_escape_string($_POST['datos_json'] ?? '{}');
    $comprobantes_json = $conn->real_escape_string($_POST['comprobantes_json'] ?? '{}');
    $empresas = isset($_POST['empresas']) ? json_decode($_POST['empresas'], true) : [];
    $usuario = $conn->real_escape_string($_SESSION['usuario'] ?? 'Sistema');
    
    if (empty($nombre) || empty($empresas)) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios']);
        exit;
    }
    
    $conn->begin_transaction();
    try {
        $sql = "INSERT INTO cuentas_guardadas (nombre, desde, hasta, facturado, porcentaje_ajuste, pagado, datos_json, comprobantes_json, usuario) 
                VALUES ('$nombre', '$desde', '$hasta', $facturado, $porcentaje_ajuste, $pagado, '$datos_json', '$comprobantes_json', '$usuario')";
        if (!$conn->query($sql)) throw new Exception("Error al guardar cuenta: " . $conn->error);
        $cuenta_id = $conn->insert_id;
        foreach ($empresas as $empresa) {
            $empresa_esc = $conn->real_escape_string($empresa);
            $conn->query("INSERT INTO cuentas_guardadas_empresas (cuenta_id, empresa_nombre) VALUES ($cuenta_id, '$empresa_esc')");
        }
        $conn->commit();
        echo json_encode(['success' => true, 'id' => $cuenta_id, 'message' => 'Cuenta guardada exitosamente']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ACTUALIZAR cuenta existente (NUEVO)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_cuenta') {
    header('Content-Type: application/json');
    $id = intval($_POST['id'] ?? 0);
    $nombre = $conn->real_escape_string($_POST['nombre'] ?? '');
    $desde = $conn->real_escape_string($_POST['desde'] ?? '');
    $hasta = $conn->real_escape_string($_POST['hasta'] ?? '');
    $facturado = floatval($_POST['facturado'] ?? 0);
    $porcentaje_ajuste = floatval($_POST['porcentaje_ajuste'] ?? 0);
    $pagado = intval($_POST['pagado'] ?? 0);
    $datos_json = $conn->real_escape_string($_POST['datos_json'] ?? '{}');
    $comprobantes_json = $conn->real_escape_string($_POST['comprobantes_json'] ?? '{}');
    $empresas = isset($_POST['empresas']) ? json_decode($_POST['empresas'], true) : [];
    
    if ($id <= 0 || empty($nombre) || empty($empresas)) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios']);
        exit;
    }
    
    $conn->begin_transaction();
    try {
        $sql = "UPDATE cuentas_guardadas 
                SET nombre = '$nombre', desde = '$desde', hasta = '$hasta', 
                    facturado = $facturado, porcentaje_ajuste = $porcentaje_ajuste, 
                    pagado = $pagado, datos_json = '$datos_json', comprobantes_json = '$comprobantes_json'
                WHERE id = $id";
        if (!$conn->query($sql)) throw new Exception("Error al actualizar cuenta: " . $conn->error);
        
        $conn->query("DELETE FROM cuentas_guardadas_empresas WHERE cuenta_id = $id");
        foreach ($empresas as $empresa) {
            $empresa_esc = $conn->real_escape_string($empresa);
            $conn->query("INSERT INTO cuentas_guardadas_empresas (cuenta_id, empresa_nombre) VALUES ($id, '$empresa_esc')");
        }
        $conn->commit();
        echo json_encode(['success' => true, 'id' => $id, 'message' => 'Cuenta actualizada exitosamente']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Eliminar cuenta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar_cuenta') {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    echo json_encode(['success' => $conn->query("DELETE FROM cuentas_guardadas WHERE id = $id")]);
    exit;
}

// Cargar cuenta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cargar_cuenta') {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    $sql = "SELECT c.*, GROUP_CONCAT(e.empresa_nombre ORDER BY e.empresa_nombre SEPARATOR '||') as empresas_list
            FROM cuentas_guardadas c
            LEFT JOIN cuentas_guardadas_empresas e ON c.id = e.cuenta_id
            WHERE c.id = $id GROUP BY c.id";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $row['pagado'] = (int)$row['pagado'];
        $row['datos_json'] = json_decode($row['datos_json'], true) ?: [];
        $row['comprobantes_json'] = json_decode($row['comprobantes_json'], true) ?: [];
        $row['empresas'] = !empty($row['empresas_list']) ? explode('||', $row['empresas_list']) : [];
        unset($row['empresas_list']);
        echo json_encode(['success' => true, 'cuenta' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Cuenta no encontrada']);
    }
    exit;
}

// Fusionar cuentas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'fusionar_cuentas') {
    header('Content-Type: application/json');
    $ids = isset($_POST['ids']) ? json_decode($_POST['ids'], true) : [];
    if (empty($ids) || count($ids) < 2) {
        echo json_encode(['success' => false, 'message' => 'Se necesitan al menos 2 cuentas para fusionar']);
        exit;
    }
    $ids_str = implode(',', array_map('intval', $ids));
    $result = $conn->query("SELECT c.*, GROUP_CONCAT(e.empresa_nombre ORDER BY e.empresa_nombre SEPARATOR '||') as empresas_list
            FROM cuentas_guardadas c LEFT JOIN cuentas_guardadas_empresas e ON c.id = e.cuenta_id
            WHERE c.id IN ($ids_str) GROUP BY c.id");
    $cuentas = [];
    while ($row = $result->fetch_assoc()) {
        $row['datos_json'] = json_decode($row['datos_json'], true) ?: [];
        $row['empresas'] = !empty($row['empresas_list']) ? explode('||', $row['empresas_list']) : [];
        $cuentas[] = $row;
    }
    
    $fusionado = [
        'nombre' => 'Fusión: ' . implode(' + ', array_slice(array_column($cuentas, 'nombre'), 0, 2)) . (count($cuentas) > 2 ? ' +' . (count($cuentas)-2) . ' más' : ''),
        'desde' => min(array_column($cuentas, 'desde')),
        'hasta' => max(array_column($cuentas, 'hasta')),
        'facturado' => array_sum(array_column($cuentas, 'facturado')),
        'porcentaje_ajuste' => round(array_sum(array_column($cuentas, 'porcentaje_ajuste')) / count($cuentas), 2),
        'pagado' => 0,
        'empresas' => array_values(array_unique(array_merge(...array_column($cuentas, 'empresas')))),
        'datos_json' => ['prestamos' => new stdClass(), 'segSocial' => new stdClass(), 'cuentasBancarias' => new stdClass(), 'estadosPago' => new stdClass(), 'filasManuales' => []],
        'comprobantes_json' => new stdClass()
    ];
    
    $conductores_fusionados = [];
    foreach ($cuentas as $cuenta) {
        $datos = $cuenta['datos_json'];
        if (isset($datos['filasManuales']) && is_array($datos['filasManuales'])) {
            foreach ($datos['filasManuales'] as $fila) {
                $conductor = $fila['conductor'];
                $base = floatval($fila['base'] ?? 0);
                $conductores_fusionados[$conductor] = ($conductores_fusionados[$conductor] ?? 0) + $base;
            }
        }
        if (isset($datos['conductoresBase']) && is_array($datos['conductoresBase'])) {
            foreach ($datos['conductoresBase'] as $conductor => $base) {
                $conductores_fusionados[$conductor] = ($conductores_fusionados[$conductor] ?? 0) + $base;
            }
        }
    }
    foreach ($conductores_fusionados as $conductor => $base_total) {
        if ($base_total > 0) {
            $fusionado['datos_json']['filasManuales'][] = ['conductor' => $conductor, 'base' => $base_total, 'cuenta' => '', 'segSocial' => 0, 'estado' => ''];
        }
    }
    echo json_encode(['success' => true, 'cuenta_fusionada' => $fusionado]);
    exit;
}

// Actualizar estado pagado de cuenta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_pagado_cuenta') {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    $pagado = intval($_POST['pagado']);
    echo json_encode(['success' => $conn->query("UPDATE cuentas_guardadas SET pagado = $pagado WHERE id = $id")]);
    exit;
}

/* ================= TARIFAS DINÁMICAS ================= */
$columnas_tarifas = [];
$resColumns = $conn->query("SHOW COLUMNS FROM tarifas");
if ($resColumns) {
    while ($col = $resColumns->fetch_assoc()) {
        $field = $col['Field'];
        if (!in_array($field, ['id', 'empresa', 'tipo_vehiculo', 'created_at', 'updated_at'])) {
            $columnas_tarifas[] = $field;
        }
    }
}

$todas_clasificaciones = [];
$resClasifAll = $conn->query("SELECT DISTINCT clasificacion FROM ruta_clasificacion");
if ($resClasifAll) {
    while ($r = $resClasifAll->fetch_assoc()) {
        $todas_clasificaciones[] = strtolower($r['clasificacion']);
    }
}
foreach ($columnas_tarifas as $columna) {
    $columna_normalizada = strtolower($columna);
    if (!in_array($columna_normalizada, $todas_clasificaciones)) {
        $todas_clasificaciones[] = $columna_normalizada;
    }
}

$clasificaciones = [];
$resClasif = $conn->query("SELECT ruta, tipo_vehiculo, clasificacion FROM ruta_clasificacion");
if ($resClasif) {
    while ($r = $resClasif->fetch_assoc()) {
        $key = mb_strtolower(trim($r['ruta'] . '|' . $r['tipo_vehiculo']), 'UTF-8');
        $clasificaciones[$key] = strtolower($r['clasificacion']);
    }
}

/* ================= AJAX: Viajes por conductor ================= */
if (isset($_GET['viajes_conductor'])) {
    $nombre = $conn->real_escape_string($_GET['viajes_conductor']);
    $desde = $conn->real_escape_string($_GET['desde'] ?? '');
    $hasta = $conn->real_escape_string($_GET['hasta'] ?? '');
    $empresas = isset($_GET['empresas']) ? json_decode($_GET['empresas'], true) : [];
    
    $sql = "SELECT id, fecha, ruta, empresa, tipo_vehiculo, pagado FROM viajes WHERE nombre = '$nombre' AND fecha BETWEEN '$desde' AND '$hasta'";
    if (!empty($empresas)) {
        $empresas_escapadas = array_map(function($e) use ($conn) { return "'" . $conn->real_escape_string($e) . "'"; }, $empresas);
        $sql .= " AND empresa IN (" . implode(',', $empresas_escapadas) . ")";
    }
    $sql .= " ORDER BY fecha ASC";
    $res = $conn->query($sql);
    
    $rowsHTML = "";
    $counts = ['otro' => 0];
    foreach ($todas_clasificaciones as $clas) $counts[strtolower($clas)] = 0;
    
    if ($res && $res->num_rows > 0) {
        while ($r = $res->fetch_assoc()) {
            $key = mb_strtolower(trim($r['ruta'] . '|' . $r['tipo_vehiculo']), 'UTF-8');
            $cat = isset($clasificaciones[$key]) ? strtolower($clasificaciones[$key]) : 'otro';
            if (!in_array($cat, $todas_clasificaciones)) $cat = 'otro';
            $counts[$cat] = ($counts[$cat] ?? 0) + 1;
            
            $color_class = match($cat) {
                'completo' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                'medio' => 'bg-amber-100 text-amber-800 border-amber-300',
                'extra' => 'bg-slate-200 text-slate-800 border-slate-300',
                'siapana' => 'bg-fuchsia-100 text-fuchsia-700 border-fuchsia-200',
                'carrotanque' => 'bg-cyan-100 text-cyan-800 border-cyan-200',
                default => 'bg-gray-100 text-gray-700 border-gray-200'
            };
            $pagadoBadge = $r['pagado'] ? '<span class="inline-block px-2 py-0.5 rounded text-xs bg-green-100 text-green-700 ml-2">✓ Pagado</span>' : '<span class="inline-block px-2 py-0.5 rounded text-xs bg-red-100 text-red-700 ml-2">○ Pendiente</span>';
            $rowsHTML .= "<tr class='viaje-item cat-$cat' data-viaje-id='{$r['id']}'>
                <td class='px-3 py-2 text-center'><input type='checkbox' class='viaje-checkbox' data-viaje-id='{$r['id']}'></td>
                <td class='px-3 py-2'>{$r['fecha']}</td>
                <td class='px-3 py-2'><span class='inline-block px-2 py-1 rounded text-xs font-medium border $color_class'>{$r['ruta']}</span>$pagadoBadge</td>
                <td class='px-3 py-2'><span class='inline-block px-2 py-1 rounded text-xs bg-blue-50 text-blue-700 border border-blue-200'>{$r['empresa']}</span></td>
                <td class='px-3 py-2'><span class='inline-block px-2 py-1 rounded text-xs bg-slate-100 border border-slate-300'>{$r['tipo_vehiculo']}</span></td>
            </tr>";
        }
    } else {
        $rowsHTML = "<tr><td colspan='5' class='px-3 py-4 text-center text-slate-500'>Sin viajes en el rango/empresas seleccionadas.</td></tr>";
    }
    ?>
    <div class='space-y-3'>
        <div class='flex flex-wrap gap-2 text-xs' id="legendFilterBar">
            <?php
            $colores_base = ['completo'=>'bg-emerald-100 text-emerald-700 border-emerald-200','medio'=>'bg-amber-100 text-amber-800 border-amber-200','extra'=>'bg-slate-200 text-slate-800 border-slate-300','siapana'=>'bg-fuchsia-100 text-fuchsia-700 border-fuchsia-200','carrotanque'=>'bg-cyan-100 text-cyan-800 border-cyan-200'];
            $legend = [];
            foreach ($todas_clasificaciones as $clas) {
                $clas_n = strtolower($clas);
                $legend[$clas_n] = ['label'=>ucwords(str_replace(['_',' medio',' completo'],[' ',' Medio',' Completo'],$clas)), 'badge'=>$colores_base[$clas_n] ?? 'bg-gray-100 text-gray-700 border-gray-200'];
            }
            $legend['otro'] = ['label'=>'Sin clasificar','badge'=>'bg-gray-100 text-gray-700 border-gray-200'];
            foreach ($legend as $k => $l) {
                if (($counts[$k] ?? 0) > 0) echo "<button class='legend-pill inline-flex items-center gap-2 px-3 py-2 rounded-full {$l['badge']} hover:opacity-90' data-tipo='{$k}'><span class='w-2.5 h-2.5 rounded-full'></span><span class='font-semibold text-[13px]'>{$l['label']}</span><span class='text-[11px]'>({$counts[$k]})</span></button>";
            }
            ?>
        </div>
        <div class="flex flex-wrap items-center gap-2 p-2 bg-blue-50 rounded-lg border border-blue-200">
            <span class="text-xs font-medium text-blue-700">⚡ Acciones en viajes:</span>
            <button type="button" class="btn-pagar-viajes-modal px-3 py-1.5 rounded-lg text-xs font-medium bg-green-500 text-white hover:bg-green-600 disabled:opacity-50" disabled>✅ Pagar seleccionados</button>
            <button type="button" class="btn-despagar-viajes-modal px-3 py-1.5 rounded-lg text-xs font-medium bg-amber-500 text-white hover:bg-amber-600 disabled:opacity-50" disabled>↩️ Desmarcar seleccionados</button>
            <button type="button" class="btn-select-all-viajes px-3 py-1.5 rounded-lg text-xs font-medium bg-white border border-slate-300">☑️ Seleccionar todos</button>
            <span class="text-xs text-slate-500 ml-auto viajes-seleccionados-count">0 seleccionados</span>
        </div>
        <div class='overflow-x-auto'>
            <table class='min-w-full text-sm text-left'>
                <thead class='bg-blue-600 text-white'><tr><th class='px-3 py-2 text-center w-10'><input type="checkbox" id="selectAllViajesModal" class="w-4 h-4"></th><th>Fecha</th><th>Ruta</th><th>Empresa</th><th>Vehículo</th></tr></thead>
                <tbody class='divide-y divide-gray-100 bg-white' id="viajesTableBody"><?= $rowsHTML ?></tbody>
            </table>
        </div>
    </div>
    <?php
    exit;
}

/* ================= Form si faltan fechas ================= */
if (!isset($_GET['desde']) || !isset($_GET['hasta'])) {
    $empresas = [];
    $resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
    if ($resEmp) while ($r = $resEmp->fetch_assoc()) $empresas[] = $r['empresa'];
    ?>
    <!DOCTYPE html>
    <html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"/><title>Ajuste de Pago</title><script src="https://cdn.tailwindcss.com"></script></head>
    <body class="min-h-screen bg-slate-100 text-slate-800">
    <div class="max-w-lg mx-auto p-6"><div class="bg-white shadow-sm rounded-2xl p-6 border border-slate-200">
        <h2 class="text-2xl font-bold text-center mb-2">📅 Ajuste de Pago por rango</h2>
        <form method="get" class="space-y-4">
            <label class="block"><span class="block text-sm font-medium mb-1">Desde</span><input type="date" name="desde" required class="w-full rounded-xl border border-slate-300 px-3 py-2"></label>
            <label class="block"><span class="block text-sm font-medium mb-1">Hasta</span><input type="date" name="hasta" required class="w-full rounded-xl border border-slate-300 px-3 py-2"></label>
            <div class="block"><span class="block text-sm font-medium mb-2">Empresas</span><div class="max-h-60 overflow-y-auto border border-slate-300 rounded-xl p-3 space-y-2">
                <?php foreach($empresas as $e): ?><label class="flex items-center gap-2"><input type="checkbox" name="empresas[]" value="<?= htmlspecialchars($e) ?>" class="rounded border-slate-300"><span><?= htmlspecialchars($e) ?></span></label><?php endforeach; ?>
            </div></div>
            <button class="w-full rounded-xl bg-blue-600 text-white py-2.5 font-semibold shadow">Continuar</button>
        </form>
    </div></div></body></html>
    <?php exit;
}
include("nav.php");

/* ================= Parámetros ================= */
$desde = $_GET['desde'];
$hasta = $_GET['hasta'];
$empresasSeleccionadas = isset($_GET['empresas']) ? $_GET['empresas'] : [];
$empresasSeleccionadasEsc = array_map(function($e) use ($conn) { return $conn->real_escape_string($e); }, $empresasSeleccionadas);

/* ================= CARGAR TARIFAS ================= */
$tarifas = [];
if (!empty($empresasSeleccionadasEsc)) {
    $empresasStr = "'" . implode("','", $empresasSeleccionadasEsc) . "'";
    $resT = $conn->query("SELECT * FROM tarifas WHERE empresa IN ($empresasStr)");
    while($r = $resT->fetch_assoc()) {
        $tarifas[$r['empresa']][$r['tipo_vehiculo']] = array_change_key_case($r, CASE_LOWER);
    }
}

/* ================= Viajes del rango ================= */
$sqlV = "SELECT id, nombre, ruta, empresa, tipo_vehiculo FROM viajes WHERE fecha BETWEEN '$desde' AND '$hasta'";
if (!empty($empresasSeleccionadasEsc)) $sqlV .= " AND empresa IN ('" . implode("','", $empresasSeleccionadasEsc) . "')";
$resV = $conn->query($sqlV);

$viajesPorConductor = [];
$viajesIdsPorConductor = [];
$contadores = [];

if ($resV) {
    while ($row = $resV->fetch_assoc()) {
        $nombre = $row['nombre'];
        $viajesPorConductor[$nombre][] = ['id'=>$row['id'], 'empresa'=>$row['empresa'], 'ruta'=>$row['ruta'], 'vehiculo'=>$row['tipo_vehiculo']];
        $viajesIdsPorConductor[$nombre][] = $row['id'];
        if (!isset($contadores[$nombre])) foreach ($todas_clasificaciones as $clas) $contadores[$nombre][strtolower($clas)] = 0;
        $key = mb_strtolower(trim($row['ruta'] . '|' . $row['tipo_vehiculo']), 'UTF-8');
        $clasif = $clasificaciones[$key] ?? '';
        if ($clasif && in_array($clasif, $todas_clasificaciones)) $contadores[$nombre][$clasif]++;
    }
}

/* ================= PRÉSTAMOS ================= */
$prestamosList = [];
$qPrest = "SELECT deudor, prestamista, empresa, monto, fecha, id FROM prestamos WHERE (pagado IS NULL OR pagado = 0) ORDER BY empresa, deudor";
if ($rP = $conn->query($qPrest)) {
    while($r = $rP->fetch_assoc()){
        $monto = (int)$r['monto'];
        if (strpos(strtolower($r['prestamista']), 'asociaci') !== false) {
            $total = $monto;
        } else {
            $fecha_prestamo = new DateTime($r['fecha']);
            $fecha_actual = new DateTime();
            $fecha_limite = new DateTime('2025-10-29');
            $interes = ($fecha_prestamo >= $fecha_limite) ? 0.13 : 0.10;
            $meses = 0;
            if ($fecha_actual > $fecha_prestamo) {
                $diff = $fecha_prestamo->diff($fecha_actual);
                $meses = $diff->m + ($diff->y * 12);
                if ($diff->d > 0) $meses++;
            }
            $total = $monto + ($monto * $interes * $meses);
        }
        $prestamosList[] = ['id'=>$r['id'], 'name'=>$r['deudor'], 'monto_original'=>$monto, 'total'=>(int)round($total), 'empresa'=>$r['empresa'], 'prestamista'=>$r['prestamista'], 'fecha'=>$r['fecha']];
    }
}

/* ================= Filas base ================= */
$filas = [];
$total_facturado = 0;
foreach ($contadores as $nombre => $v) {
    $total = 0;
    foreach ($viajesPorConductor[$nombre] as $viaje) {
        $key = mb_strtolower(trim($viaje['ruta'] . '|' . $viaje['vehiculo']), 'UTF-8');
        $clasif = $clasificaciones[$key] ?? 'otro';
        $precio = 0;
        if (isset($tarifas[$viaje['empresa']][$viaje['vehiculo']])) {
            $t = $tarifas[$viaje['empresa']][$viaje['vehiculo']];
            $precio = (float)($t[$clasif] ?? $t[str_replace(' ', '_', $clasif)] ?? $t[str_replace('_', ' ', $clasif)] ?? 0);
        }
        $total += $precio;
    }
    $filas[] = ['nombre'=>$nombre, 'total_bruto'=>(int)$total];
    $total_facturado += (int)$total;
}
usort($filas, fn($a,$b)=> $b['total_bruto'] <=> $a['total_bruto']);
$CONDUCTORES_LIST = array_column($filas, 'nombre');
$viajesIdsJSON = json_encode($viajesIdsPorConductor);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Ajuste de Pago - Múltiples Empresas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .num { font-variant-numeric: tabular-nums; }
        .table-sticky thead tr { position: sticky; top: 0; z-index: 30; }
        .table-sticky thead th { position: sticky; top: 0; z-index: 31; background-color: #2563eb !important; color: #fff !important; }
        .viajes-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,.45); display:none; align-items:center; justify-content:center; z-index:10000; }
        .viajes-backdrop.show{ display:flex; }
        .viajes-card{ width:min(850px,94vw); max-height:90vh; overflow:hidden; border-radius:16px; background:#fff; }
        .conductor-link{cursor:pointer; color:#0d6efd; text-decoration:underline;}
        .estado-pagado { background-color: #f0fdf4 !important; border-left: 4px solid #22c55e; }
        .estado-pendiente { background-color: #fef2f2 !important; border-left: 4px solid #ef4444; }
        .estado-procesando { background-color: #fffbeb !important; border-left: 4px solid #f59e0b; }
        .estado-parcial { background-color: #eff6ff !important; border-left: 4px solid #3b82f6; }
        .fila-manual { background-color: #f0f9ff !important; border-left: 4px solid #0ea5e9; }
        .buscar-container { position: relative; }
        .buscar-clear { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); display: none; cursor: pointer; }
        #floatingPanel { box-shadow: 0 10px 40px rgba(0,0,0,0.15); z-index: 9999; }
        #panelDragHandle { cursor: move; }
        .fila-seleccionada { background-color: #f0f9ff !important; }
        .empresas-container { max-height: 150px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.75rem; background: white; }
        .empresa-checkbox { display: flex; align-items: center; gap: 0.5rem; padding: 0.25rem 0; }
        .switch-pagado { position: relative; display: inline-block; width: 50px; height: 24px; }
        .switch-pagado input { opacity: 0; width: 0; height: 0; }
        .switch-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ef4444; transition: .3s; border-radius: 34px; }
        .switch-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
        input:checked + .switch-slider { background-color: #22c55e; }
        input:checked + .switch-slider:before { transform: translateX(26px); }
        .bd-badge { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .comprobante-preview { background-size: cover; background-position: center; background-repeat: no-repeat; }
        .modal-comprobante { position: fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.9); z-index:20000; display:flex; align-items:center; justify-content:center; cursor:pointer; }
        .btn-excel { background: linear-gradient(135deg, #217346 0%, #185a2d 100%); color: white; }
        .badge-editando { background: #fef3c7; color: #92400e; font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: 9999px; display: inline-flex; align-items: center; gap: 0.25rem; border: 1px solid #f59e0b; }
        .switch-small { width: 40px; height: 20px; }
        .switch-small .switch-slider:before { height: 14px; width: 14px; left: 3px; bottom: 3px; }
        input:checked + .switch-small .switch-slider:before { transform: translateX(20px); }
        .empresas-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.5rem; max-height: 200px; overflow-y: auto; padding: 0.5rem; background: #f8fafc; border-radius: 0.75rem; border: 1px solid #e2e8f0; }
        .empresa-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; background: white; border-radius: 0.5rem; border: 1px solid #e2e8f0; }
        .badge-empresa { background: #e2e8f0; color: #475569; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 9999px; margin-left: auto; }
        .prest-item { transition: all 0.2s; border-left: 3px solid transparent; }
        .prest-item:hover { background-color: #f0f9ff; border-left-color: #3b82f6; }
        .badge-historico { background: #fef3c7; color: #92400e; font-size: 0.65rem; padding: 0.15rem 0.4rem; border-radius: 9999px; margin-left: 0.25rem; display: inline-block; border: 1px solid #fbbf24; }
        .cuenta-checkbox { width: 18px; height: 18px; cursor: pointer; accent-color: #3b82f6; }
        .fila-cuenta-seleccionada { background-color: #eff6ff !important; border-left: 4px solid #3b82f6; }
        .desglose-prestamistas { margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px dashed #fbbf24; font-size: 0.8rem; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">
<header class="max-w-[1600px] mx-auto px-3 md:px-4 pt-6">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-5 py-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h2 class="text-xl md:text-2xl font-bold">🧾 Ajuste de Pago <span class="bd-badge text-xs px-2 py-1 rounded-full ml-2">Base de Datos</span></h2>
                <div id="editandoIndicator" class="mt-1 hidden"><span class="badge-editando">✏️ Editando cuenta: <span id="editandoNombreCuenta"></span></span></div>
            </div>
            <div class="flex items-center gap-2">
                <button id="btnExportExcel" class="btn-excel rounded-lg px-3 py-2 text-sm font-medium">📥 Descargar Excel</button>
                <button id="btnShowSaveCuenta" class="rounded-lg border border-amber-300 px-3 py-2 text-sm bg-amber-50 hover:bg-amber-100">⭐ Guardar cuenta</button>
                <button id="btnShowGestorCuentas" class="rounded-lg border border-blue-300 px-3 py-2 text-sm bg-blue-50 hover:bg-blue-100">📚 Cuentas guardadas</button>
            </div>
        </div>
        <form method="get" class="mt-4 grid grid-cols-1 md:grid-cols-12 gap-3">
            <div class="md:col-span-2"><label class="text-xs font-medium">Desde</label><input type="date" name="desde" id="filtro_desde" value="<?= htmlspecialchars($desde) ?>" class="w-full border rounded-xl px-3 py-2"></div>
            <div class="md:col-span-2"><label class="text-xs font-medium">Hasta</label><input type="date" name="hasta" id="filtro_hasta" value="<?= htmlspecialchars($hasta) ?>" class="w-full border rounded-xl px-3 py-2"></div>
            <div class="md:col-span-6">
                <label class="text-xs font-medium">Empresas</label>
                <div class="empresas-container" id="empresasContainer">
                    <?php $resEmp2 = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
                    while ($e = $resEmp2->fetch_assoc()) { $checked = in_array($e['empresa'], $empresasSeleccionadas) ? 'checked' : ''; echo "<div class='empresa-checkbox'><input type='checkbox' name='empresas[]' value='" . htmlspecialchars($e['empresa']) . "' $checked><label class='text-sm'>" . htmlspecialchars($e['empresa']) . "</label></div>"; } ?>
                </div>
                <div class="flex gap-2 mt-2"><button type="button" id="btnSeleccionarTodas" class="text-xs px-3 py-1.5 bg-blue-50 rounded-lg border border-blue-200">✓ Todas</button><button type="button" id="btnLimpiarTodas" class="text-xs px-3 py-1.5 bg-slate-50 rounded-lg border border-slate-200">✗ Limpiar</button></div>
            </div>
            <div class="md:col-span-2 flex items-end"><button type="submit" class="w-full bg-blue-600 text-white py-2.5 rounded-xl font-semibold shadow">Aplicar</button></div>
        </form>
    </div>
</header>

<main class="max-w-[1600px] mx-auto px-3 md:px-4 py-6 space-y-5">
    <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
        <div class="grid grid-cols-1 md:grid-cols-7 gap-4">
            <div><div class="text-xs text-slate-500 mb-1">Conductores</div><div class="text-lg font-semibold"><?= count($filas) ?></div></div>
            <label class="block md:col-span-2"><span class="block text-xs font-medium mb-1">Cuenta de cobro</span><input id="inp_facturado" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right num" value="<?= number_format($total_facturado,0,',','.') ?>"></label>
            <label class="block md:col-span-2"><span class="block text-xs font-medium mb-1">Viajes manuales</span><input id="inp_viajes_manuales" type="text" class="w-full rounded-xl border border-green-200 px-3 py-2 text-right num bg-green-50" value="0" readonly></label>
            <label class="block"><span class="block text-xs font-medium mb-1">% Ajuste</span><input id="inp_porcentaje_ajuste" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right num" value="5"></label>
            <div><div class="text-xs text-slate-500 mb-1">Total ajuste</div><div id="lbl_total_ajuste" class="text-lg font-semibold text-amber-600 num">0</div></div>
        </div>
    </section>

    <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
            <div><h3 class="text-lg font-semibold">Conductores</h3><div id="contador-conductores" class="text-xs text-slate-500 mt-1">Mostrando <?= count($filas) ?> de <?= count($filas) ?> conductores</div></div>
            <div class="flex flex-col md:flex-row gap-3">
                <select id="filtroEstado" class="rounded-lg border border-slate-300 px-3 py-2 text-sm min-w-[150px]"><option value="">📊 Todos los estados</option><option value="pagado">✅ Pagado</option><option value="pendiente">❌ Pendiente</option><option value="procesando">🔄 Procesando</option><option value="parcial">⚠️ Parcial</option></select>
                <div class="buscar-container w-full md:w-64"><input id="buscadorConductores" type="text" placeholder="Buscar conductor..." class="w-full rounded-lg border border-slate-300 px-3 py-2 pl-3 pr-10"><button id="clearBuscar" class="buscar-clear">✕</button></div>
                <button id="btnAddManual" class="rounded-lg bg-green-600 text-white px-4 py-2 text-sm hover:bg-green-700">➕ Agregar manual</button>
            </div>
        </div>
        <div class="overflow-auto max-h-[70vh] rounded-xl border border-slate-200 table-sticky">
            <table class="min-w-[1400px] w-full text-sm">
                <thead class="bg-blue-600 text-white"><tr><th class="px-3 py-2 text-left">Conductor</th><th class="px-3 py-2 text-right">Base</th><th class="px-3 py-2 text-right">Ajuste</th><th class="px-3 py-2 text-right">Llegó</th><th class="px-3 py-2 text-right">Ret 3.5%</th><th class="px-3 py-2 text-right">4x1000</th><th class="px-3 py-2 text-right">Aporte 10%</th><th class="px-3 py-2 text-right">Seg social</th><th class="px-3 py-2 text-left">Préstamos</th><th class="px-3 py-2 text-left">N° Cuenta</th><th class="px-3 py-2 text-right">A pagar</th><th class="px-3 py-2 text-center">Estado</th><th class="px-3 py-2 text-center">Comprobante</th><th class="px-3 py-2 text-center"><input type="checkbox" id="selectAllCheckbox"></th></tr></thead>
                <tbody id="tbody">
                <?php foreach ($filas as $f): $tieneViajes = isset($viajesIdsPorConductor[$f['nombre']]); $viajesCount = $tieneViajes ? count($viajesIdsPorConductor[$f['nombre']]) : 0; ?>
                <tr data-conductor="<?= htmlspecialchars(mb_strtolower($f['nombre'])) ?>" data-base="<?= $f['total_bruto'] ?>" data-tiene-viajes="<?= $tieneViajes ? '1' : '0' ?>" data-viajes-count="<?= $viajesCount ?>">
                    <td class="px-3 py-2"><button type="button" class="conductor-link text-blue-600 hover:underline" data-nombre="<?= htmlspecialchars($f['nombre']) ?>"><?= htmlspecialchars($f['nombre']) ?></button><?php if ($tieneViajes): ?><span class="inline-block px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700 ml-1"><?= $viajesCount ?> v</span><?php endif; ?></td>
                    <td class="px-3 py-2 text-right num base"><?= number_format($f['total_bruto'],0,',','.') ?></td>
                    <td class="px-3 py-2 text-right num ajuste">0</td>
                    <td class="px-3 py-2 text-right num llego">0</td>
                    <td class="px-3 py-2 text-right num ret">0</td>
                    <td class="px-3 py-2 text-right num mil4">0</td>
                    <td class="px-3 py-2 text-right num apor">0</td>
                    <td class="px-3 py-2 text-right"><input type="text" class="ss w-full max-w-[100px] rounded-lg border border-slate-300 px-2 py-1 text-right num" value=""></td>
                    <td class="px-3 py-2"><div class="flex items-center gap-1"><span class="num prest text-sm font-medium">0</span><button type="button" class="btn-prest text-xs px-2 py-1 rounded border border-slate-300 bg-slate-50">Sel</button></div><div class="text-[10px] text-slate-500 selected-deudor truncate max-w-[150px]"></div></td>
                    <td class="px-3 py-2"><input type="text" class="cta w-full max-w-[140px] rounded-lg border border-slate-300 px-2 py-1" placeholder="N° cuenta"></td>
                    <td class="px-3 py-2 text-right num pagar">0</td>
                    <td class="px-3 py-2 text-center"><select class="estado-pago w-full max-w-[120px] rounded-lg border border-slate-300 px-2 py-1 text-sm"><option value="">Sin estado</option><option value="pagado">✅ Pagado</option><option value="pendiente">❌ Pendiente</option><option value="procesando">🔄 Procesando</option><option value="parcial">⚠️ Parcial</option></select></td>
                    <td class="px-3 py-2 text-center"><div class="comprobante-container flex flex-col items-center gap-1"><input type="file" class="comprobante-file hidden" accept="image/*" data-conductor="<?= htmlspecialchars($f['nombre']) ?>"><div class="comprobante-preview w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center border border-gray-300 hover:border-blue-500 cursor-pointer transition overflow-hidden" onclick="this.previousElementSibling.click()"><span class="text-gray-400 text-xs">📷</span></div><button type="button" class="btn-eliminar-comprobante hidden text-xs text-red-500 hover:text-red-700" onclick="eliminarComprobante('<?= htmlspecialchars($f['nombre']) ?>')">🗑️</button></div></td>
                    <td class="px-3 py-2 text-center"><input type="checkbox" class="selector-conductor"></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-slate-50 font-semibold"><tr><td class="px-3 py-2" colspan="3">Totales</td><td class="px-3 py-2 text-right num" id="tot_llego">0</td><td class="px-3 py-2 text-right num" id="tot_ret">0</td><td class="px-3 py-2 text-right num" id="tot_mil4">0</td><td class="px-3 py-2 text-right num" id="tot_apor">0</td><td class="px-3 py-2 text-right num" id="tot_ss">0</td><td class="px-3 py-2 text-right num" id="tot_prest">0</td><td class="px-3 py-2"></td><td class="px-3 py-2 text-right num" id="tot_pagar">0</td><td class="px-3 py-2" colspan="3"></td></tr></tfoot>
            </table>
        </div>
    </section>
</main>

<!-- Panel flotante -->
<div id="floatingPanel" class="hidden fixed z-50 bg-white border border-blue-300 rounded-xl shadow-lg" style="top:100px;left:100px;min-width:300px;">
    <div id="panelDragHandle" class="cursor-move bg-blue-600 text-white px-4 py-3 rounded-t-xl flex justify-between"><div class="font-semibold">📊 Sumatoria <span id="selectedCount" class="bg-blue-500 text-white text-xs px-2 py-1 rounded-full ml-2">0</span></div><button id="closePanel" class="text-white">✕</button></div>
    <div class="p-4"><div class="grid grid-cols-2 gap-3"><div class="bg-slate-50 p-3 rounded-lg"><div class="text-xs text-slate-500">Total a pagar</div><div id="panelTotalPagar" class="text-xl font-bold text-emerald-600 num">0</div></div><div class="bg-slate-50 p-3 rounded-lg"><div class="text-xs text-slate-500">Promedio</div><div id="panelPromedio" class="text-lg font-semibold text-blue-600 num">0</div></div></div>
    <div class="text-xs text-slate-500 mt-3 space-y-1"><div class="flex justify-between"><span>Valor que llegó:</span><span id="panelLlego" class="num">0</span></div><div class="flex justify-between"><span>Ret 3.5%:</span><span id="panelRet" class="num">0</span></div><div class="flex justify-between"><span>4×1000:</span><span id="panelMil4" class="num">0</span></div><div class="flex justify-between"><span>Aporte 10%:</span><span id="panelApor" class="num">0</span></div><div class="flex justify-between"><span>Seg. social:</span><span id="panelSS" class="num">0</span></div><div class="flex justify-between"><span>Préstamos:</span><span id="panelPrest" class="num">0</span></div><div class="flex justify-between border-t pt-2 mt-2"><span class="font-semibold">Viajes seleccionados:</span><span id="panelViajesCount" class="num font-bold">0</span></div></div></div>
</div>

<!-- Modal Préstamos -->
<div id="prestModal" class="hidden fixed inset-0 z-50 overflow-y-auto"><div class="absolute inset-0 bg-black/30"></div><div class="relative mx-auto my-8 bg-white rounded-2xl shadow-lg w-[95%] max-w-5xl max-h-[90vh] overflow-hidden"><div class="p-4 border-b bg-blue-50 flex justify-between"><h3 class="font-semibold">💰 Préstamos de: <span id="conductorNombre" class="text-blue-700"></span></h3><button id="btnCloseModal" class="text-xl">✕</button></div><div class="p-4"><div class="flex gap-4 mb-4"><div class="flex-1 bg-blue-50 p-3 rounded"><div class="text-xs">Disponible</div><div id="disponibleConductor" class="text-xl font-bold text-blue-600">$0</div></div><div class="flex-1 bg-amber-50 p-3 rounded"><div class="text-xs">Seleccionados</div><div id="totalSeleccionado" class="text-xl font-bold text-amber-600">$0</div></div></div><div class="mb-3"><label class="text-xs">🏢 Empresas:</label><div class="empresas-grid" id="empresasMultiSelect"></div></div><div class="flex gap-2 mb-3"><input id="prestSearch" type="text" placeholder="Buscar..." class="flex-1 rounded border p-2"><button id="btnDeseleccionarTodos" class="px-3 py-2 bg-amber-100 rounded">✕ Deseleccionar</button><button id="btnLimpiarFiltros" class="px-3 py-2 bg-gray-100 rounded">Limpiar</button></div><div id="prestList" class="max-h-96 overflow-y-auto border rounded"></div><div class="mt-4 flex gap-2"><input id="prestValorManual" type="text" placeholder="Valor manual" class="flex-1 rounded border p-2"><button id="btnCancel" class="px-4 py-2 border rounded">Cancelar</button><button id="btnAssign" class="px-4 py-2 bg-blue-600 text-white rounded">Asignar</button></div></div></div></div>

<!-- Modal Viajes -->
<div id="viajesModal" class="viajes-backdrop"><div class="viajes-card"><div class="p-4 border-b flex justify-between"><h3 class="font-semibold">Viajes de <span id="viajesTitle"></span></h3><button id="viajesCloseBtn" class="border px-3 py-1 rounded">✕</button></div><div id="viajesContent" class="p-4 max-h-[70vh] overflow-auto">Cargando...</div></div></div>

<!-- Modal Guardar/Actualizar Cuenta -->
<div id="saveCuentaModal" class="hidden fixed inset-0 z-50 overflow-y-auto"><div class="absolute inset-0 bg-black/30"></div><div class="relative mx-auto my-8 w-full max-w-lg bg-white rounded-2xl shadow-lg"><div class="p-4 border-b flex justify-between"><h3 id="saveCuentaModalTitle" class="font-semibold">⭐ Guardar cuenta</h3><button id="btnCloseSaveCuenta" class="text-xl">✕</button></div><div class="p-4 space-y-3"><input id="cuenta_nombre" type="text" placeholder="Nombre" class="w-full rounded border p-2"><div><div class="text-xs mb-1">Empresas:</div><div id="cuenta_empresas_container" class="border rounded p-2 text-sm bg-gray-50"></div></div><div class="grid grid-cols-2 gap-2"><input id="cuenta_rango" type="text" readonly class="rounded border p-2 bg-gray-100"><input id="cuenta_facturado" type="text" class="rounded border p-2 text-right"></div><div class="grid grid-cols-2 gap-2"><input id="cuenta_porcentaje" type="text" value="5" class="rounded border p-2 text-right"><div class="flex items-center gap-2"><span id="pagadoLabel" class="text-sm px-2 py-1 rounded-full bg-red-100">NO PAGADO</span><label class="switch-pagado"><input type="checkbox" id="cuenta_pagado"><span class="switch-slider"></span></label></div></div></div><div class="p-4 border-t flex justify-end gap-2"><button id="btnCancelSaveCuenta" class="px-4 py-2 border rounded">Cancelar</button><button id="btnDoSaveCuenta" class="px-4 py-2 bg-amber-500 text-white rounded">Guardar</button><button id="btnDoUpdateCuenta" class="px-4 py-2 bg-blue-500 text-white rounded hidden">🔄 Actualizar</button></div></div></div>

<!-- Modal Gestor de Cuentas -->
<div id="gestorCuentasModal" class="hidden fixed inset-0 z-50 overflow-y-auto"><div class="absolute inset-0 bg-black/30"></div><div class="relative mx-auto my-8 w-full max-w-6xl bg-white rounded-2xl shadow-lg"><div class="p-4 border-b flex justify-between"><h3 class="font-semibold">📚 Cuentas guardadas</h3><button id="btnCloseGestor" class="text-xl">✕</button></div><div class="p-4"><div class="flex gap-2 mb-3"><select id="filtroEmpresaCuentas" class="border rounded p-2"><option value="">Todas</option></select><select id="filtroEstadoPagado" class="border rounded p-2"><option value="">Todos</option><option value="0">No pagadas</option><option value="1">Pagadas</option></select><input id="buscaCuentaBD" type="text" placeholder="Buscar..." class="flex-1 border rounded p-2"><button id="btnRecargarCuentas" class="px-3 py-2 bg-blue-50 rounded">🔄</button></div><div class="bg-amber-50 p-2 rounded mb-3 flex justify-between"><span>🔀 Acciones:</span><button id="btnFusionarSeleccionadas" class="px-3 py-1 bg-amber-500 text-white rounded text-sm disabled:opacity-50" disabled>Fusionar</button><span id="cuentasSeleccionadasCount">0</span></div><div class="overflow-auto max-h-96 border rounded"><table class="min-w-full text-sm"><thead class="bg-blue-600 text-white"><tr><th><input type="checkbox" id="selectAllCuentas"></th><th>Nombre</th><th>Empresas</th><th>Rango</th><th>Facturado</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr></thead><tbody id="tbodyCuentasBD"><tr><td colspan="8" class="text-center p-8">Cargando...</td></tr></tbody></table></div></div><div class="p-4 border-t flex justify-between"><span id="totalCuentasInfo">0 cuentas</span><button id="btnAddDesdeFiltro" class="px-3 py-2 bg-amber-100 rounded">⭐ Guardar rango actual</button></div></div></div>

<script>
// ===== VARIABLES GLOBALES =====
const EMPRESAS_SELECCIONADAS = <?= json_encode($empresasSeleccionadas) ?>;
const COMPANY_SCOPE = EMPRESAS_SELECCIONADAS.length ? EMPRESAS_SELECCIONADAS.join('_') : '__todas__';
const PRESTAMOS_LIST = <?= json_encode($prestamosList) ?>;
const VIAJES_IDS_POR_CONDUCTOR = <?= $viajesIdsJSON ?>;
const CONDUCTORES_LIST = <?= json_encode($CONDUCTORES_LIST) ?>;

let currentEditingCuentaId = null;
let currentEditingCuentaNombre = null;

function toInt(s) { return parseInt(String(s).replace(/[^0-9-]/g, '')) || 0; }
function fmt(n) { return '$' + (n || 0).toLocaleString('es-CO'); }
function getLS(k) { try { return JSON.parse(localStorage.getItem(k) || '{}'); } catch { return {}; } }
function setLS(k, v) { localStorage.setItem(k, JSON.stringify(v)); }

let accMap = getLS('cuentas_temp:'+COMPANY_SCOPE);
let ssMap = getLS('seg_social_temp:'+COMPANY_SCOPE);
let prestSel = getLS('prestamo_sel_multi:v4:'+COMPANY_SCOPE) || {};
let estadoPagoMap = getLS('estado_pago_temp:'+COMPANY_SCOPE) || {};
let manualRows = JSON.parse(localStorage.getItem('filas_manuales_temp:'+COMPANY_SCOPE) || '[]');
let selectedConductors = JSON.parse(localStorage.getItem('conductores_seleccionados_temp:'+COMPANY_SCOPE) || '[]');
let comprobantesMap = {};

// ===== FUNCIONES DE EDICIÓN =====
function resetEditingState() {
    currentEditingCuentaId = null;
    currentEditingCuentaNombre = null;
    document.getElementById('editandoIndicator').classList.add('hidden');
    document.getElementById('saveCuentaModalTitle').textContent = '⭐ Guardar cuenta';
    document.getElementById('btnDoUpdateCuenta').classList.add('hidden');
    document.getElementById('btnDoSaveCuenta').classList.remove('hidden');
}

function setEditingMode(cuentaId, cuentaNombre) {
    currentEditingCuentaId = cuentaId;
    currentEditingCuentaNombre = cuentaNombre;
    document.getElementById('editandoNombreCuenta').textContent = cuentaNombre;
    document.getElementById('editandoIndicator').classList.remove('hidden');
    document.getElementById('saveCuentaModalTitle').textContent = '🔄 Actualizar cuenta';
    document.getElementById('btnDoUpdateCuenta').classList.remove('hidden');
    document.getElementById('btnDoSaveCuenta').classList.add('hidden');
}

// ===== FUNCIONES PRINCIPALES =====
function obtenerNombreConductorDeFila(tr) {
    if (tr.classList.contains('fila-manual')) {
        let sel = tr.querySelector('.conductor-select');
        return sel ? sel.value : '';
    }
    let link = tr.querySelector('.conductor-link');
    return link ? link.textContent.trim() : '';
}

function obtenerViajesIdsDeSeleccionados() {
    let ids = [];
    document.querySelectorAll('#tbody .selector-conductor:checked').forEach(cb => {
        let tr = cb.closest('tr');
        if (tr && tr.style.display !== 'none') {
            let nombre = obtenerNombreConductorDeFila(tr);
            if (nombre && VIAJES_IDS_POR_CONDUCTOR[nombre]) ids.push(...VIAJES_IDS_POR_CONDUCTOR[nombre]);
        }
    });
    return [...new Set(ids)];
}

function recalcularTodo() {
    let porcentaje = parseFloat(document.getElementById('inp_porcentaje_ajuste').value) || 0;
    let totalAutomaticos = <?= $total_facturado ?>;
    let totalManuales = 0, sumLlego = 0, sumRet = 0, sumMil4 = 0, sumApor = 0, sumSS = 0, sumPrest = 0, sumPagar = 0;
    
    document.querySelectorAll('#tbody tr').forEach(tr => {
        if (tr.style.display === 'none') return;
        let base;
        if (tr.classList.contains('fila-manual')) {
            base = toInt(tr.querySelector('.base-manual')?.value);
            totalManuales += base;
        } else {
            base = toInt(tr.querySelector('.base')?.textContent);
        }
        let ajuste = Math.round(base * (porcentaje / 100));
        let llego = base - ajuste;
        let prest = toInt(tr.querySelector('.prest')?.textContent);
        let ret = Math.round(llego * 0.035);
        let mil4 = Math.round(llego * 0.004);
        let apor = Math.round(llego * 0.10);
        let ss = toInt(tr.querySelector('.ss')?.value);
        let pagar = llego - ret - mil4 - apor - ss - prest;
        
        if (tr.querySelector('.ajuste')) tr.querySelector('.ajuste').textContent = fmt(ajuste).replace('$', '');
        if (tr.querySelector('.llego')) tr.querySelector('.llego').textContent = fmt(llego).replace('$', '');
        if (tr.querySelector('.ret')) tr.querySelector('.ret').textContent = fmt(ret).replace('$', '');
        if (tr.querySelector('.mil4')) tr.querySelector('.mil4').textContent = fmt(mil4).replace('$', '');
        if (tr.querySelector('.apor')) tr.querySelector('.apor').textContent = fmt(apor).replace('$', '');
        if (tr.querySelector('.pagar')) tr.querySelector('.pagar').textContent = fmt(pagar).replace('$', '');
        
        sumLlego += llego; sumRet += ret; sumMil4 += mil4; sumApor += apor; sumSS += ss; sumPrest += prest; sumPagar += pagar;
    });
    
    document.getElementById('inp_facturado').value = fmt(totalAutomaticos + totalManuales).replace('$', '');
    document.getElementById('inp_viajes_manuales').value = fmt(totalManuales).replace('$', '');
    document.getElementById('lbl_total_ajuste').textContent = fmt(Math.round((totalAutomaticos + totalManuales) * (porcentaje / 100)));
    document.getElementById('tot_llego').textContent = fmt(sumLlego);
    document.getElementById('tot_ret').textContent = fmt(sumRet);
    document.getElementById('tot_mil4').textContent = fmt(sumMil4);
    document.getElementById('tot_apor').textContent = fmt(sumApor);
    document.getElementById('tot_ss').textContent = fmt(sumSS);
    document.getElementById('tot_prest').textContent = fmt(sumPrest);
    document.getElementById('tot_pagar').textContent = fmt(sumPagar);
}

function asignarPrestamosAFilas() {
    document.querySelectorAll('#tbody tr').forEach(tr => {
        let nombre = obtenerNombreConductorDeFila(tr);
        if (!nombre) return;
        let prestamos = prestSel[nombre] || [];
        if (!prestamos.length) { tr.querySelector('.prest').textContent = '0'; tr.querySelector('.selected-deudor').innerHTML = ''; return; }
        let total = 0, nombres = [];
        prestamos.forEach(p => {
            if (p.esManual) { total += p.valorManual; nombres.push('💰 Manual'); }
            else { total += p.totalActual; nombres.push(p.name?.split(' ')[0] || '?'); }
        });
        tr.querySelector('.prest').textContent = fmt(total).replace('$', '');
        tr.querySelector('.selected-deudor').innerHTML = nombres.slice(0,3).join(', ') + (nombres.length > 3 ? ` +${nombres.length-3}` : '');
    });
    recalcularTodo();
}

function agregarFilaManual(id = null) {
    let manualId = id || 'manual_' + Date.now();
    let html = `<tr class="fila-manual" data-manual-id="${manualId}">
        <td class="px-3 py-2"><select class="conductor-select w-full max-w-[200px] rounded border p-1"><option value="">-- Seleccionar --</option>${CONDUCTORES_LIST.map(c => `<option value="${c}">${c}</option>`).join('')}</select></td>
        <td class="px-3 py-2 text-right"><input type="text" class="base-manual w-full max-w-[100px] rounded border p-1 text-right" value="0"></td>
        <td class="px-3 py-2 text-right ajuste">0</td><td class="px-3 py-2 text-right llego">0</td><td class="px-3 py-2 text-right ret">0</td><td class="px-3 py-2 text-right mil4">0</td><td class="px-3 py-2 text-right apor">0</td>
        <td class="px-3 py-2 text-right"><input type="text" class="ss w-full max-w-[80px] rounded border p-1 text-right"></td>
        <td class="px-3 py-2"><div class="flex items-center gap-1"><span class="prest text-sm">0</span><button class="btn-prest text-xs px-2 py-1 border rounded">Sel</button></div><div class="text-[10px] text-slate-500 selected-deudor"></div></td>
        <td class="px-3 py-2"><input type="text" class="cta w-full max-w-[120px] rounded border p-1" placeholder="N° cuenta"></td>
        <td class="px-3 py-2 text-right pagar">0</td>
        <td class="px-3 py-2 text-center"><select class="estado-pago w-full max-w-[100px] rounded border p-1"><option value="">Sin estado</option><option value="pagado">✅ Pagado</option><option value="pendiente">❌ Pendiente</option><option value="procesando">🔄 Procesando</option><option value="parcial">⚠️ Parcial</option></select></td>
        <td class="px-3 py-2 text-center"><div class="comprobante-container flex flex-col items-center"><input type="file" class="comprobante-file hidden" accept="image/*"><div class="comprobante-preview w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center border cursor-pointer" onclick="this.previousElementSibling.click()"><span class="text-xs">📷</span></div><button class="btn-eliminar-comprobante hidden text-xs text-red-500">🗑️</button></div></td>
        <td class="px-3 py-2 text-center"><div class="flex gap-1"><input type="checkbox" class="selector-conductor"><button class="btn-eliminar-manual text-xs px-2 py-1 bg-rose-50 rounded text-rose-700">🗑️</button></div></td>
    </tr>`;
    document.getElementById('tbody').insertAdjacentHTML('beforeend', html);
    configurarEventosFila(document.querySelector('#tbody tr:last-child'));
    if (!id) { manualRows.push(manualId); localStorage.setItem('filas_manuales_temp:'+COMPANY_SCOPE, JSON.stringify(manualRows)); }
    asignarPrestamosAFilas();
    recalcularTodo();
}

function configurarEventosFila(tr) {
    tr.querySelector('.base-manual')?.addEventListener('input', () => { tr.querySelector('.base-manual').value = fmt(toInt(tr.querySelector('.base-manual').value)).replace('$', ''); recalcularTodo(); });
    tr.querySelector('.ss')?.addEventListener('input', () => { let nombre = obtenerNombreConductorDeFila(tr); if(nombre) ssMap[nombre] = toInt(tr.querySelector('.ss').value); setLS('seg_social_temp:'+COMPANY_SCOPE, ssMap); recalcularTodo(); });
    tr.querySelector('.cta')?.addEventListener('change', () => { let nombre = obtenerNombreConductorDeFila(tr); if(nombre) { accMap[nombre] = tr.querySelector('.cta').value; setLS('cuentas_temp:'+COMPANY_SCOPE, accMap); } });
    tr.querySelector('.estado-pago')?.addEventListener('change', () => { let nombre = obtenerNombreConductorDeFila(tr); if(nombre) { estadoPagoMap[nombre] = tr.querySelector('.estado-pago').value; setLS('estado_pago_temp:'+COMPANY_SCOPE, estadoPagoMap); } });
    tr.querySelector('.btn-prest')?.addEventListener('click', () => abrirModalPrestamos(tr));
    tr.querySelector('.btn-eliminar-manual')?.addEventListener('click', () => { let id = tr.dataset.manualId; manualRows = manualRows.filter(r => r !== id); localStorage.setItem('filas_manuales_temp:'+COMPANY_SCOPE, JSON.stringify(manualRows)); tr.remove(); recalcularTodo(); });
    tr.querySelector('.conductor-select')?.addEventListener('change', () => { let nombre = tr.querySelector('.conductor-select').value; if(nombre) { if(accMap[nombre]) tr.querySelector('.cta').value = accMap[nombre]; if(ssMap[nombre]) tr.querySelector('.ss').value = fmt(ssMap[nombre]).replace('$', ''); if(estadoPagoMap[nombre]) tr.querySelector('.estado-pago').value = estadoPagoMap[nombre]; } asignarPrestamosAFilas(); recalcularTodo(); });
    tr.querySelector('.selector-conductor')?.addEventListener('change', () => actualizarPanelFlotante());
    restaurarSeleccionCheckbox(tr);
}

function restaurarSeleccionCheckbox(tr) {
    let nombre = obtenerNombreConductorDeFila(tr);
    if (nombre && selectedConductors.includes(nombre)) tr.querySelector('.selector-conductor').checked = true;
}

let prestamosCurrentRow = null;
let prestamosSelectedIds = new Set();

function abrirModalPrestamos(tr) {
    prestamosCurrentRow = tr;
    let nombre = obtenerNombreConductorDeFila(tr);
    if (!nombre) { Swal.fire('Error', 'Selecciona un conductor primero', 'warning'); return; }
    document.getElementById('conductorNombre').innerHTML = nombre;
    let disponible = toInt(tr.querySelector('.pagar')?.textContent);
    document.getElementById('disponibleConductor').innerHTML = fmt(disponible);
    prestamosSelectedIds.clear();
    (prestSel[nombre] || []).forEach(p => { if(p.id) prestamosSelectedIds.add(p.id); });
    renderizarListaPrestamos(PRESTAMOS_LIST);
    document.getElementById('prestModal').classList.remove('hidden');
}

function renderizarListaPrestamos(lista) {
    let html = '';
    let empresaActual = '';
    lista.forEach(p => {
        if (empresaActual !== p.empresa) { empresaActual = p.empresa; html += `<div class="p-2 font-bold bg-gray-100">🏢 ${p.empresa}</div>`; }
        html += `<div class="prest-item flex justify-between items-center p-2 border-b"><label class="flex items-center gap-2"><input type="checkbox" class="prest-checkbox" data-id="${p.id}" ${prestamosSelectedIds.has(p.id) ? 'checked' : ''}> <span>${p.name}</span></label><span class="font-bold">${fmt(p.total)}</span></div>`;
    });
    document.getElementById('prestList').innerHTML = html || '<div class="p-4 text-center">No hay préstamos</div>';
    document.querySelectorAll('.prest-checkbox').forEach(cb => cb.addEventListener('change', () => {
        let id = parseInt(cb.dataset.id);
        cb.checked ? prestamosSelectedIds.add(id) : prestamosSelectedIds.delete(id);
        let total = Array.from(prestamosSelectedIds).reduce((s, id) => s + (PRESTAMOS_LIST.find(p => p.id === id)?.total || 0), 0);
        document.getElementById('totalSeleccionado').innerHTML = fmt(total);
    }));
    let total = Array.from(prestamosSelectedIds).reduce((s, id) => s + (PRESTAMOS_LIST.find(p => p.id === id)?.total || 0), 0);
    document.getElementById('totalSeleccionado').innerHTML = fmt(total);
}

function actualizarPanelFlotante() {
    let checkboxes = document.querySelectorAll('#tbody .selector-conductor:checked');
    if (checkboxes.length === 0) { document.getElementById('floatingPanel').classList.add('hidden'); return; }
    document.getElementById('floatingPanel').classList.remove('hidden');
    document.getElementById('selectedCount').innerHTML = checkboxes.length;
    let totalPagar = 0;
    checkboxes.forEach(cb => { let tr = cb.closest('tr'); if(tr) totalPagar += toInt(tr.querySelector('.pagar')?.textContent); });
    document.getElementById('panelTotalPagar').innerHTML = fmt(totalPagar);
    document.getElementById('panelPromedio').innerHTML = fmt(Math.round(totalPagar / checkboxes.length));
}

function exportarExcel() {
    let filas = [];
    document.querySelectorAll('#tbody tr').forEach(tr => {
        filas.push({ conductor: obtenerNombreConductorDeFila(tr), base: tr.querySelector('.base')?.textContent || tr.querySelector('.base-manual')?.value || '0', ajuste: tr.querySelector('.ajuste')?.textContent || '0', llego: tr.querySelector('.llego')?.textContent || '0', ret: tr.querySelector('.ret')?.textContent || '0', mil4: tr.querySelector('.mil4')?.textContent || '0', apor: tr.querySelector('.apor')?.textContent || '0', ss: tr.querySelector('.ss')?.value || '0', prest: tr.querySelector('.prest')?.textContent || '0', cuenta: tr.querySelector('.cta')?.value || '', pagar: tr.querySelector('.pagar')?.textContent || '0', estado: tr.querySelector('.estado-pago')?.value || '' });
    });
    let form = document.createElement('form');
    form.method = 'POST';
    form.action = '?exportar_excel=1&desde=<?= $desde ?>&hasta=<?= $hasta ?>';
    form.target = '_blank';
    let input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'filas';
    input.value = JSON.stringify(filas);
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
    form.remove();
}

function abrirModalViajes(nombre) {
    document.getElementById('viajesTitle').innerHTML = nombre;
    document.getElementById('viajesModal').classList.add('show');
    let params = new URLSearchParams({ viajes_conductor: nombre, desde: '<?= $desde ?>', hasta: '<?= $hasta ?>', empresas: JSON.stringify(EMPRESAS_SELECCIONADAS) });
    fetch('?' + params).then(r => r.text()).then(html => document.getElementById('viajesContent').innerHTML = html);
}

async function guardarCuentaNueva() {
    let nombre = document.getElementById('cuenta_nombre').value.trim();
    if (!nombre) return Swal.fire('Error', 'Ingresa un nombre', 'warning');
    let datos = { prestamos: prestSel, segSocial: ssMap, cuentasBancarias: accMap, estadosPago: estadoPagoMap, filasManuales: [] };
    document.querySelectorAll('#tbody .fila-manual').forEach(tr => { let sel = tr.querySelector('.conductor-select'); if(sel && sel.value) datos.filasManuales.push({ conductor: sel.value, base: toInt(tr.querySelector('.base-manual')?.value), cuenta: tr.querySelector('.cta')?.value || '', segSocial: toInt(tr.querySelector('.ss')?.value), estado: tr.querySelector('.estado-pago')?.value || '' }); });
    let comps = {};
    for (let [c, b64] of Object.entries(comprobantesMap)) if(b64) comps[c] = b64;
    let fd = new FormData();
    fd.append('accion', 'guardar_cuenta'); fd.append('nombre', nombre); fd.append('desde', '<?= $desde ?>'); fd.append('hasta', '<?= $hasta ?>');
    fd.append('facturado', toInt(document.getElementById('cuenta_facturado').value)); fd.append('porcentaje_ajuste', parseFloat(document.getElementById('cuenta_porcentaje').value) || 0);
    fd.append('pagado', document.getElementById('cuenta_pagado').checked ? 1 : 0); fd.append('empresas', JSON.stringify(EMPRESAS_SELECCIONADAS));
    fd.append('datos_json', JSON.stringify(datos)); fd.append('comprobantes_json', JSON.stringify(comps));
    let res = await fetch('', { method: 'POST', body: fd });
    let json = await res.json();
    if(json.success) { Swal.fire('Éxito', 'Cuenta guardada', 'success'); document.getElementById('saveCuentaModal').classList.add('hidden'); resetEditingState(); if(!document.getElementById('gestorCuentasModal').classList.contains('hidden')) renderizarCuentasBD(); }
    else Swal.fire('Error', json.message, 'error');
}

async function actualizarCuentaExistente() {
    if (!currentEditingCuentaId) return Swal.fire('Error', 'No hay cuenta para actualizar', 'warning');
    let nombre = document.getElementById('cuenta_nombre').value.trim();
    if (!nombre) return Swal.fire('Error', 'Ingresa un nombre', 'warning');
    let datos = { prestamos: prestSel, segSocial: ssMap, cuentasBancarias: accMap, estadosPago: estadoPagoMap, filasManuales: [] };
    document.querySelectorAll('#tbody .fila-manual').forEach(tr => { let sel = tr.querySelector('.conductor-select'); if(sel && sel.value) datos.filasManuales.push({ conductor: sel.value, base: toInt(tr.querySelector('.base-manual')?.value), cuenta: tr.querySelector('.cta')?.value || '', segSocial: toInt(tr.querySelector('.ss')?.value), estado: tr.querySelector('.estado-pago')?.value || '' }); });
    let comps = {};
    for (let [c, b64] of Object.entries(comprobantesMap)) if(b64) comps[c] = b64;
    let fd = new FormData();
    fd.append('accion', 'actualizar_cuenta'); fd.append('id', currentEditingCuentaId); fd.append('nombre', nombre);
    fd.append('desde', '<?= $desde ?>'); fd.append('hasta', '<?= $hasta ?>');
    fd.append('facturado', toInt(document.getElementById('cuenta_facturado').value)); fd.append('porcentaje_ajuste', parseFloat(document.getElementById('cuenta_porcentaje').value) || 0);
    fd.append('pagado', document.getElementById('cuenta_pagado').checked ? 1 : 0); fd.append('empresas', JSON.stringify(EMPRESAS_SELECCIONADAS));
    fd.append('datos_json', JSON.stringify(datos)); fd.append('comprobantes_json', JSON.stringify(comps));
    let res = await fetch('', { method: 'POST', body: fd });
    let json = await res.json();
    if(json.success) { Swal.fire('Éxito', 'Cuenta actualizada', 'success'); document.getElementById('saveCuentaModal').classList.add('hidden'); resetEditingState(); if(!document.getElementById('gestorCuentasModal').classList.contains('hidden')) renderizarCuentasBD(); }
    else Swal.fire('Error', json.message, 'error');
}

async function cargarCuenta(id) {
    let res = await fetch('', { method: 'POST', body: new URLSearchParams({ accion: 'cargar_cuenta', id: id }) });
    let json = await res.json();
    if(json.success) {
        let c = json.cuenta;
        setEditingMode(c.id, c.nombre);
        document.getElementById('filtro_desde').value = c.desde;
        document.getElementById('filtro_hasta').value = c.hasta;
        document.querySelectorAll('.empresa-checkbox input').forEach(cb => cb.checked = c.empresas.includes(cb.value));
        prestSel = c.datos_json.prestamos || {}; ssMap = c.datos_json.segSocial || {}; accMap = c.datos_json.cuentasBancarias || {}; estadoPagoMap = c.datos_json.estadosPago || {};
        comprobantesMap = c.comprobantes_json || {};
        setLS('prestamo_sel_multi:v4:'+COMPANY_SCOPE, prestSel); setLS('seg_social_temp:'+COMPANY_SCOPE, ssMap); setLS('cuentas_temp:'+COMPANY_SCOPE, accMap); setLS('estado_pago_temp:'+COMPANY_SCOPE, estadoPagoMap);
        document.querySelectorAll('#tbody .fila-manual').forEach(tr => tr.remove());
        manualRows = [];
        if(c.datos_json.filasManuales?.length) c.datos_json.filasManuales.forEach(f => agregarFilaManual());
        document.querySelectorAll('#tbody tr').forEach(tr => { let nombre = obtenerNombreConductorDeFila(tr); if(nombre) { if(accMap[nombre]) tr.querySelector('.cta').value = accMap[nombre]; if(ssMap[nombre]) tr.querySelector('.ss').value = fmt(ssMap[nombre]).replace('$', ''); if(estadoPagoMap[nombre]) tr.querySelector('.estado-pago').value = estadoPagoMap[nombre]; if(comprobantesMap[nombre]) actualizarPreviewComprobante(nombre, comprobantesMap[nombre]); } });
        asignarPrestamosAFilas();
        document.getElementById('inp_porcentaje_ajuste').value = c.porcentaje_ajuste;
        recalcularTodo();
        document.getElementById('gestorCuentasModal').classList.add('hidden');
        Swal.fire('Cuenta cargada', `"${c.nombre}" - Ahora puedes modificarla y usar "Actualizar cuenta"`, 'success');
    }
}

async function eliminarCuenta(id) {
    if(await Swal.fire({title:'¿Eliminar?',text:'No se puede deshacer',icon:'warning',showCancelButton:true}).then(r=>r.isConfirmed)) {
        let res = await fetch('', { method: 'POST', body: new URLSearchParams({ accion: 'eliminar_cuenta', id: id }) });
        if((await res.json()).success) { Swal.fire('Eliminada', '', 'success'); renderizarCuentasBD(); if(currentEditingCuentaId == id) resetEditingState(); }
    }
}

async function renderizarCuentasBD() {
    let empresa = document.getElementById('filtroEmpresaCuentas').value;
    let estado = document.getElementById('filtroEstadoPagado').value;
    let filtro = document.getElementById('buscaCuentaBD').value.toLowerCase();
    let res = await fetch(`?obtener_cuentas=1&empresa=${encodeURIComponent(empresa)}&estado=${encodeURIComponent(estado)}`);
    let cuentas = await res.json();
    let filtradas = cuentas.filter(c => !filtro || c.nombre.toLowerCase().includes(filtro) || (c.empresas||[]).some(e=>e.toLowerCase().includes(filtro)));
    document.getElementById('totalCuentasInfo').innerHTML = `${filtradas.length} cuentas`;
    let html = '';
    filtradas.forEach(c => {
        html += `<tr><td class="text-center"><input type="checkbox" class="cuenta-seleccion" value="${c.id}"></td>
            <td><div class="font-medium">${c.nombre}</div><div class="text-xs">👤 ${c.usuario || 'Sistema'}</div></td>
            <td class="text-xs">${(c.empresas||[]).slice(0,2).join(', ')}${(c.empresas?.length||0)>2 ? ' +'+(c.empresas.length-2) : ''}</td>
            <td class="text-xs">${c.desde} → ${c.hasta}</td>
            <td class="text-right">${fmt(c.facturado)}</td>
            <td class="text-center"><label class="switch-pagado switch-small"><input type="checkbox" class="switch-estado-cuenta" data-id="${c.id}" ${c.pagado ? 'checked' : ''}><span class="switch-slider" style="background:${c.pagado ? '#22c55e' : '#ef4444'}"></span></label></td>
            <td class="text-center text-xs">${new Date(c.fecha_creacion).toLocaleDateString()}</td>
            <td class="text-right"><button class="btn-cargar-cuenta text-blue-600 mr-2" data-id="${c.id}">📂 Cargar</button><button class="btn-eliminar-cuenta text-red-600" data-id="${c.id}">🗑️</button></td>
        </tr>`;
    });
    document.getElementById('tbodyCuentasBD').innerHTML = html || '<tr><td colspan="8" class="text-center p-4">No hay cuentas</td></tr>';
    document.querySelectorAll('.btn-cargar-cuenta').forEach(btn => btn.addEventListener('click', () => cargarCuenta(btn.dataset.id)));
    document.querySelectorAll('.btn-eliminar-cuenta').forEach(btn => btn.addEventListener('click', () => eliminarCuenta(btn.dataset.id)));
    document.querySelectorAll('.switch-estado-cuenta').forEach(sw => sw.addEventListener('change', async function() { await fetch('', { method: 'POST', body: new URLSearchParams({ accion: 'actualizar_pagado_cuenta', id: this.dataset.id, pagado: this.checked ? 1 : 0 }) }); }));
    document.querySelectorAll('.cuenta-seleccion').forEach(cb => cb.addEventListener('change', () => document.getElementById('btnFusionarSeleccionadas').disabled = document.querySelectorAll('.cuenta-seleccion:checked').length < 2));
}

function actualizarPreviewComprobante(conductor, base64) {
    let tr = Array.from(document.querySelectorAll('#tbody tr')).find(tr => obtenerNombreConductorDeFila(tr) === conductor);
    if(!tr) return;
    let preview = tr.querySelector('.comprobante-preview');
    let btn = tr.querySelector('.btn-eliminar-comprobante');
    if(base64 && base64.startsWith('data:image')) {
        preview.style.backgroundImage = `url(${base64})`;
        preview.style.backgroundSize = 'cover';
        preview.innerHTML = '';
        if(btn) btn.classList.remove('hidden');
    } else {
        preview.style.backgroundImage = '';
        preview.innerHTML = '<span class="text-xs">📷</span>';
        if(btn) btn.classList.add('hidden');
    }
}

async function subirComprobante(conductor, file) {
    return new Promise((resolve, reject) => {
        let reader = new FileReader();
        reader.onload = async e => {
            let fd = new FormData();
            fd.append('accion', 'subir_comprobante'); fd.append('conductor', conductor); fd.append('imagen', e.target.result);
            let res = await fetch('', { method: 'POST', body: fd });
            let json = await res.json();
            if(json.success) { comprobantesMap[conductor] = e.target.result; actualizarPreviewComprobante(conductor, e.target.result); resolve(); }
            else reject(json.message);
        };
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });
}

window.eliminarComprobante = async function(conductor) {
    if(await Swal.fire({title:'Eliminar comprobante?',icon:'warning',showCancelButton:true}).then(r=>r.isConfirmed)) {
        let fd = new FormData();
        fd.append('accion', 'eliminar_comprobante'); fd.append('conductor', conductor);
        await fetch('', { method: 'POST', body: fd });
        delete comprobantesMap[conductor];
        actualizarPreviewComprobante(conductor, null);
        Swal.fire('Eliminado', '', 'success');
    }
};

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('btnExportExcel').addEventListener('click', exportarExcel);
    document.getElementById('btnShowSaveCuenta').addEventListener('click', () => { document.getElementById('cuenta_nombre').value = EMPRESAS_SELECCIONADAS[0] + ' ' + new Date().toLocaleDateString(); document.getElementById('cuenta_facturado').value = document.getElementById('inp_facturado').value; document.getElementById('cuenta_porcentaje').value = document.getElementById('inp_porcentaje_ajuste').value; document.getElementById('saveCuentaModal').classList.remove('hidden'); });
    document.getElementById('btnShowGestorCuentas').addEventListener('click', () => { renderizarCuentasBD(); document.getElementById('gestorCuentasModal').classList.remove('hidden'); });
    document.getElementById('btnCloseSaveCuenta').addEventListener('click', () => document.getElementById('saveCuentaModal').classList.add('hidden'));
    document.getElementById('btnCancelSaveCuenta').addEventListener('click', () => document.getElementById('saveCuentaModal').classList.add('hidden'));
    document.getElementById('btnDoSaveCuenta').addEventListener('click', guardarCuentaNueva);
    document.getElementById('btnDoUpdateCuenta').addEventListener('click', actualizarCuentaExistente);
    document.getElementById('btnCloseGestor').addEventListener('click', () => document.getElementById('gestorCuentasModal').classList.add('hidden'));
    document.getElementById('btnRecargarCuentas').addEventListener('click', renderizarCuentasBD);
    document.getElementById('btnAddDesdeFiltro').addEventListener('click', () => { document.getElementById('gestorCuentasModal').classList.add('hidden'); document.getElementById('btnShowSaveCuenta').click(); });
    document.getElementById('btnFusionarSeleccionadas').addEventListener('click', async () => { let ids = [...document.querySelectorAll('.cuenta-seleccion:checked')].map(cb => parseInt(cb.value)); if(ids.length<2) return; let res = await fetch('', { method: 'POST', body: new URLSearchParams({ accion: 'fusionar_cuentas', ids: JSON.stringify(ids) }) }); let json = await res.json(); if(json.success) Swal.fire('Fusionadas', 'Ahora puedes guardar la fusión', 'info'); });
    document.getElementById('btnSeleccionarTodas').addEventListener('click', () => document.querySelectorAll('.empresa-checkbox input').forEach(cb => cb.checked = true));
    document.getElementById('btnLimpiarTodas').addEventListener('click', () => document.querySelectorAll('.empresa-checkbox input').forEach(cb => cb.checked = false));
    document.getElementById('closePanel').addEventListener('click', () => document.getElementById('floatingPanel').classList.add('hidden'));
    document.getElementById('viajesCloseBtn').addEventListener('click', () => document.getElementById('viajesModal').classList.remove('show'));
    document.getElementById('btnCloseModal').addEventListener('click', () => document.getElementById('prestModal').classList.add('hidden'));
    document.getElementById('btnCancel').addEventListener('click', () => document.getElementById('prestModal').classList.add('hidden'));
    document.getElementById('btnAssign').addEventListener('click', () => {
        if(!prestamosCurrentRow) return;
        let nombre = obtenerNombreConductorDeFila(prestamosCurrentRow);
        if(!nombre) return;
        let manual = toInt(document.getElementById('prestValorManual').value);
        if(manual > 0) prestSel[nombre] = [{ esManual: true, valorManual: manual }];
        else prestSel[nombre] = Array.from(prestamosSelectedIds).map(id => { let p = PRESTAMOS_LIST.find(p => p.id === id); return { id: p.id, name: p.name, totalActual: p.total, empresa: p.empresa, prestamista: p.prestamista }; });
        setLS('prestamo_sel_multi:v4:'+COMPANY_SCOPE, prestSel);
        asignarPrestamosAFilas();
        document.getElementById('prestModal').classList.add('hidden');
    });
    document.getElementById('btnDeseleccionarTodos').addEventListener('click', () => { prestamosSelectedIds.clear(); renderizarListaPrestamos(PRESTAMOS_LIST); });
    document.getElementById('btnLimpiarFiltros').addEventListener('click', () => { document.getElementById('prestSearch').value = ''; renderizarListaPrestamos(PRESTAMOS_LIST); });
    document.getElementById('prestSearch').addEventListener('input', () => { let t = document.getElementById('prestSearch').value.toLowerCase(); renderizarListaPrestamos(PRESTAMOS_LIST.filter(p => p.name.toLowerCase().includes(t))); });
    document.getElementById('inp_porcentaje_ajuste').addEventListener('input', recalcularTodo);
    document.getElementById('filtroEstado').addEventListener('change', () => { let f = document.getElementById('filtroEstado').value; document.querySelectorAll('#tbody tr').forEach(tr => tr.style.display = (!f || tr.querySelector('.estado-pago')?.value === f) ? '' : 'none'); recalcularTodo(); });
    document.getElementById('buscadorConductores').addEventListener('input', () => { let t = document.getElementById('buscadorConductores').value.toLowerCase(); document.querySelectorAll('#tbody tr').forEach(tr => tr.style.display = (!t || obtenerNombreConductorDeFila(tr).toLowerCase().includes(t)) ? '' : 'none'); recalcularTodo(); });
    document.getElementById('clearBuscar').addEventListener('click', () => { document.getElementById('buscadorConductores').value = ''; document.querySelectorAll('#tbody tr').forEach(tr => tr.style.display = ''); recalcularTodo(); });
    document.getElementById('selectAllCheckbox').addEventListener('change', e => document.querySelectorAll('#tbody .selector-conductor').forEach(cb => { cb.checked = e.target.checked; cb.closest('tr')?.classList.toggle('fila-seleccionada', e.target.checked); actualizarPanelFlotante(); }));
    document.getElementById('btnAddManual').addEventListener('click', () => agregarFilaManual());
    document.querySelectorAll('#tbody tr:not(.fila-manual)').forEach(tr => configurarEventosFila(tr));
    manualRows.forEach(id => agregarFilaManual(id));
    asignarPrestamosAFilas();
    recalcularTodo();
    document.getElementById('btnPagarSeleccionados').addEventListener('click', async () => { let ids = obtenerViajesIdsDeSeleccionados(); if(ids.length) await fetch('', { method: 'POST', body: new URLSearchParams({ accion: 'pagar_viajes', viaje_ids: JSON.stringify(ids) }) }).then(() => location.reload()); });
    document.getElementById('btnDespagarSeleccionados').addEventListener('click', async () => { let ids = obtenerViajesIdsDeSeleccionados(); if(ids.length) await fetch('', { method: 'POST', body: new URLSearchParams({ accion: 'despagar_viajes', viaje_ids: JSON.stringify(ids) }) }).then(() => location.reload()); });
    document.querySelectorAll('.conductor-link').forEach(btn => btn.addEventListener('click', () => abrirModalViajes(btn.textContent.trim())));
    document.querySelectorAll('.comprobante-file').forEach(input => input.addEventListener('change', async e => { if(e.target.files[0]) { await subirComprobante(input.dataset.conductor, e.target.files[0]); e.target.value = ''; } }));
});
</script>
</body>
</html>
<?php $conn->close(); ?>