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

// Verificar si la columna comprobantes_json existe, si no, crearla
$result = $conn->query("SHOW COLUMNS FROM cuentas_guardadas LIKE 'comprobantes_json'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE cuentas_guardadas ADD COLUMN comprobantes_json LONGTEXT NULL AFTER datos_json");
}

// Verificar si la columna pagado existe en viajes, si no, crearla
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
    empresa VARCHAR(100) NOT NULL,
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

// Endpoint para ACTUALIZAR cuenta existente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_cuenta') {
    header('Content-Type: application/json');
    
    $cuenta_id = intval($_POST['cuenta_id'] ?? 0);
    $nombre = $conn->real_escape_string($_POST['nombre'] ?? '');
    $desde = $conn->real_escape_string($_POST['desde'] ?? '');
    $hasta = $conn->real_escape_string($_POST['hasta'] ?? '');
    $facturado = floatval($_POST['facturado'] ?? 0);
    $porcentaje_ajuste = floatval($_POST['porcentaje_ajuste'] ?? 0);
    $pagado = intval($_POST['pagado'] ?? 0);
    $datos_json = $conn->real_escape_string($_POST['datos_json'] ?? '{}');
    $comprobantes_json = $conn->real_escape_string($_POST['comprobantes_json'] ?? '{}');
    $empresas = isset($_POST['empresas']) ? json_decode($_POST['empresas'], true) : [];
    
    if ($cuenta_id <= 0 || empty($nombre) || empty($empresas)) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios']);
        exit;
    }
    
    // Verificar que la cuenta existe
    $check = $conn->query("SELECT id FROM cuentas_guardadas WHERE id = $cuenta_id");
    if (!$check || $check->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'La cuenta no existe']);
        exit;
    }
    
    $conn->begin_transaction();
    
    try {
        $sql = "UPDATE cuentas_guardadas SET 
                nombre = '$nombre',
                desde = '$desde',
                hasta = '$hasta',
                facturado = $facturado,
                porcentaje_ajuste = $porcentaje_ajuste,
                pagado = $pagado,
                datos_json = '$datos_json',
                comprobantes_json = '$comprobantes_json'
                WHERE id = $cuenta_id";
        
        if (!$conn->query($sql)) {
            throw new Exception("Error al actualizar cuenta: " . $conn->error);
        }
        
        // Eliminar empresas anteriores y volver a insertar
        $conn->query("DELETE FROM cuentas_guardadas_empresas WHERE cuenta_id = $cuenta_id");
        
        foreach ($empresas as $empresa) {
            $empresa_esc = $conn->real_escape_string($empresa);
            $sql_emp = "INSERT INTO cuentas_guardadas_empresas (cuenta_id, empresa_nombre) VALUES ($cuenta_id, '$empresa_esc')";
            if (!$conn->query($sql_emp)) {
                throw new Exception("Error al actualizar empresa: " . $conn->error);
            }
        }
        
        $session_id = session_id();
        $conn->query("DELETE FROM comprobantes_temporales WHERE session_id = '$session_id'");
        
        $conn->commit();
        echo json_encode(['success' => true, 'id' => $cuenta_id, 'message' => 'Cuenta actualizada exitosamente']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

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
        $afectados = $conn->affected_rows;
        echo json_encode([
            'success' => true, 
            'message' => "Se marcaron $afectados viajes como pagados",
            'afectados' => $afectados
        ]);
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
        $afectados = $conn->affected_rows;
        echo json_encode([
            'success' => true, 
            'message' => "Se desmarcaron $afectados viajes como no pagados",
            'afectados' => $afectados
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    exit;
}

// Endpoint para exportar Excel
if (isset($_GET['exportar_excel'])) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="ajuste_pago_' . $_GET['desde'] . '_a_' . $_GET['hasta'] . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $filasData = isset($_POST['filas']) ? json_decode($_POST['filas'], true) : [];
    $totales = isset($_POST['totales']) ? json_decode($_POST['totales'], true) : [];
    $empresas = isset($_POST['empresas']) ? $_POST['empresas'] : '';
    $fechas = isset($_POST['fechas']) ? $_POST['fechas'] : '';
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8">';
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
    echo '<x:Name>Ajuste de Pago</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet>';
    echo '</x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo '<style>
        table { border-collapse: collapse; }
        th { background-color: #2563eb; color: white; font-weight: bold; padding: 8px; border: 1px solid #000; text-align: center; }
        td { padding: 6px 8px; border: 1px solid #ccc; }
        .num { text-align: right; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .total-row { font-weight: bold; background-color: #f1f5f9; }
        .header-info { font-size: 14px; margin-bottom: 10px; }
        .estado-pagado { background-color: #f0fdf4; }
        .estado-pendiente { background-color: #fef2f2; }
        .estado-procesando { background-color: #fffbeb; }
        .estado-parcial { background-color: #eff6ff; }
    </style>';
    echo '</head><body>';
    
    echo '<div class="header-info">';
    echo '<h2>Ajuste de Pago</h2>';
    echo '<p><strong>Rango:</strong> ' . htmlspecialchars($fechas) . '</p>';
    if (!empty($empresas)) {
        echo '<p><strong>Empresas:</strong> ' . htmlspecialchars(implode(', ', json_decode($empresas, true) ?: [])) . '</p>';
    }
    echo '<p><strong>Fecha de exportación:</strong> ' . date('d/m/Y H:i:s') . '</p>';
    echo '</div>';
    
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>#</th>';
    echo '<th>Conductor</th>';
    echo '<th>Base</th>';
    echo '<th>Ajuste</th>';
    echo '<th>Llegó</th>';
    echo '<th>Ret 3.5%</th>';
    echo '<th>4×1000</th>';
    echo '<th>Aporte 10%</th>';
    echo '<th>Seg Social</th>';
    echo '<th>Préstamos</th>';
    echo '<th>N° Cuenta</th>';
    echo '<th>A Pagar</th>';
    echo '<th>Estado</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    $num = 0;
    foreach ($filasData as $fila) {
        $num++;
        $estadoClass = '';
        switch($fila['estado']) {
            case 'pagado': $estadoClass = 'estado-pagado'; break;
            case 'pendiente': $estadoClass = 'estado-pendiente'; break;
            case 'procesando': $estadoClass = 'estado-procesando'; break;
            case 'parcial': $estadoClass = 'estado-parcial'; break;
        }
        
        echo '<tr class="' . $estadoClass . '">';
        echo '<td class="text-center">' . $num . '</td>';
        echo '<td class="text-left">' . htmlspecialchars($fila['conductor']) . '</td>';
        echo '<td class="num">' . htmlspecialchars($fila['base']) . '</td>';
        echo '<td class="num">' . htmlspecialchars($fila['ajuste']) . '</td>';
        echo '<td class="num">' . htmlspecialchars($fila['llego']) . '</td>';
        echo '<td class="num">' . htmlspecialchars($fila['ret']) . '</td>';
        echo '<td class="num">' . htmlspecialchars($fila['mil4']) . '</td>';
        echo '<td class="num">' . htmlspecialchars($fila['apor']) . '</td>';
        echo '<td class="num">' . htmlspecialchars($fila['ss']) . '</td>';
        echo '<td class="num">' . htmlspecialchars($fila['prest']) . '</td>';
        echo '<td class="text-left">' . htmlspecialchars($fila['cuenta']) . '</td>';
        echo '<td class="num">' . htmlspecialchars($fila['pagar']) . '</td>';
        echo '<td class="text-center">' . htmlspecialchars($fila['estado']) . '</td>';
        echo '</tr>';
    }
    
    if (!empty($totales)) {
        echo '<tr class="total-row">';
        echo '<td class="text-center" colspan="2"><strong>TOTALES</strong></td>';
        echo '<td class="num"><strong>' . htmlspecialchars($totales['llego'] ?? '0') . '</strong></td>';
        echo '<td></td>';
        echo '<td class="num"><strong>' . htmlspecialchars($totales['llego'] ?? '0') . '</strong></td>';
        echo '<td class="num"><strong>' . htmlspecialchars($totales['ret'] ?? '0') . '</strong></td>';
        echo '<td class="num"><strong>' . htmlspecialchars($totales['mil4'] ?? '0') . '</strong></td>';
        echo '<td class="num"><strong>' . htmlspecialchars($totales['apor'] ?? '0') . '</strong></td>';
        echo '<td class="num"><strong>' . htmlspecialchars($totales['ss'] ?? '0') . '</strong></td>';
        echo '<td class="num"><strong>' . htmlspecialchars($totales['prest'] ?? '0') . '</strong></td>';
        echo '<td></td>';
        echo '<td class="num"><strong>' . htmlspecialchars($totales['pagar'] ?? '0') . '</strong></td>';
        echo '<td></td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</body></html>';
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
    
    $sql = "INSERT INTO comprobantes_temporales (conductor, imagen_base64, session_id) 
            VALUES ('$conductor', '$imagen_base64', '$session_id')";
    
    if ($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'Comprobante guardado']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
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

if (isset($_GET['obtener_cuentas'])) {
    header('Content-Type: application/json');
    
    $empresa = $conn->real_escape_string($_GET['empresa'] ?? '');
    $estado = $_GET['estado'] ?? '';
    
    $sql = "SELECT c.*, 
                   GROUP_CONCAT(e.empresa_nombre ORDER BY e.empresa_nombre SEPARATOR '||') as empresas_list
            FROM cuentas_guardadas c
            LEFT JOIN cuentas_guardadas_empresas e ON c.id = e.cuenta_id";
    
    $where = [];
    if (!empty($empresa)) {
        $where[] = "c.id IN (SELECT cuenta_id FROM cuentas_guardadas_empresas WHERE empresa_nombre = '$empresa')";
    }
    if ($estado !== '') {
        $estado_int = intval($estado);
        $where[] = "c.pagado = $estado_int";
    }
    
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    
    $sql .= " GROUP BY c.id ORDER BY c.fecha_creacion DESC";
    
    $result = $conn->query($sql);
    $cuentas = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $row['pagado'] = (int)$row['pagado'];
            
            $datos_json = json_decode($row['datos_json'], true);
            $row['datos_json'] = ($datos_json === null) ? [] : $datos_json;
            
            $comprobantes_json = json_decode($row['comprobantes_json'], true);
            $row['comprobantes_json'] = ($comprobantes_json === null) ? [] : $comprobantes_json;
            
            $row['empresas'] = !empty($row['empresas_list']) 
                ? explode('||', $row['empresas_list']) 
                : [];
            unset($row['empresas_list']);
            
            $cuentas[] = $row;
        }
    }
    
    echo json_encode($cuentas, JSON_UNESCAPED_UNICODE);
    exit;
}

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
        
        if (!$conn->query($sql)) {
            throw new Exception("Error al guardar cuenta: " . $conn->error);
        }
        
        $cuenta_id = $conn->insert_id;
        
        foreach ($empresas as $empresa) {
            $empresa_esc = $conn->real_escape_string($empresa);
            $sql_emp = "INSERT INTO cuentas_guardadas_empresas (cuenta_id, empresa_nombre) VALUES ($cuenta_id, '$empresa_esc')";
            if (!$conn->query($sql_emp)) {
                throw new Exception("Error al guardar empresa: " . $conn->error);
            }
        }
        
        $session_id = session_id();
        $conn->query("DELETE FROM comprobantes_temporales WHERE session_id = '$session_id'");
        
        $conn->commit();
        echo json_encode(['success' => true, 'id' => $cuenta_id, 'message' => 'Cuenta guardada exitosamente']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar_cuenta') {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    
    $sql = "DELETE FROM cuentas_guardadas WHERE id = $id";
    $resultado = $conn->query($sql);
    
    echo json_encode(['success' => $resultado, 'message' => $resultado ? 'Cuenta eliminada' : 'Error al eliminar']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cargar_cuenta') {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    
    $sql = "SELECT c.*, GROUP_CONCAT(e.empresa_nombre ORDER BY e.empresa_nombre SEPARATOR '||') as empresas_list
            FROM cuentas_guardadas c
            LEFT JOIN cuentas_guardadas_empresas e ON c.id = e.cuenta_id
            WHERE c.id = $id
            GROUP BY c.id";
            
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $row['pagado'] = (int)$row['pagado'];
        
        $datos_json = json_decode($row['datos_json'], true);
        $row['datos_json'] = ($datos_json === null) ? [] : $datos_json;
        
        $comprobantes_json = json_decode($row['comprobantes_json'], true);
        $row['comprobantes_json'] = ($comprobantes_json === null) ? [] : $comprobantes_json;
        
        $row['empresas'] = !empty($row['empresas_list']) 
            ? explode('||', $row['empresas_list']) 
            : [];
        unset($row['empresas_list']);
        
        echo json_encode(['success' => true, 'cuenta' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Cuenta no encontrada']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'fusionar_cuentas') {
    header('Content-Type: application/json');
    $ids = isset($_POST['ids']) ? json_decode($_POST['ids'], true) : [];
    
    if (empty($ids) || count($ids) < 2) {
        echo json_encode(['success' => false, 'message' => 'Se necesitan al menos 2 cuentas para fusionar']);
        exit;
    }
    
    $ids_esc = array_map('intval', $ids);
    $ids_str = implode(',', $ids_esc);
    
    $sql = "SELECT c.*, 
                   GROUP_CONCAT(e.empresa_nombre ORDER BY e.empresa_nombre SEPARATOR '||') as empresas_list
            FROM cuentas_guardadas c
            LEFT JOIN cuentas_guardadas_empresas e ON c.id = e.cuenta_id
            WHERE c.id IN ($ids_str)
            GROUP BY c.id";
            
    $result = $conn->query($sql);
    $cuentas = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $datos_json = json_decode($row['datos_json'], true);
            $row['datos_json'] = ($datos_json === null) ? [] : $datos_json;
            $row['empresas'] = !empty($row['empresas_list']) ? explode('||', $row['empresas_list']) : [];
            $cuentas[] = $row;
        }
    }
    
    $fusionado = [
        'nombre' => 'Fusión: ' . implode(' + ', array_slice(array_column($cuentas, 'nombre'), 0, 2)) . (count($cuentas) > 2 ? ' +' . (count($cuentas)-2) . ' más' : ''),
        'desde' => min(array_column($cuentas, 'desde')),
        'hasta' => max(array_column($cuentas, 'hasta')),
        'facturado' => array_sum(array_column($cuentas, 'facturado')),
        'porcentaje_ajuste' => round(array_sum(array_column($cuentas, 'porcentaje_ajuste')) / count($cuentas), 2),
        'pagado' => 0,
        'empresas' => array_values(array_unique(array_merge(...array_column($cuentas, 'empresas')))),
        'datos_json' => [
            'prestamos' => new stdClass(),
            'segSocial' => new stdClass(),
            'cuentasBancarias' => new stdClass(),
            'estadosPago' => new stdClass(),
            'filasManuales' => []
        ],
        'comprobantes_json' => new stdClass()
    ];
    
    $conductores_fusionados = [];
    
    foreach ($cuentas as $cuenta) {
        $datos = $cuenta['datos_json'];
        
        if (isset($datos['filasManuales']) && is_array($datos['filasManuales'])) {
            foreach ($datos['filasManuales'] as $fila) {
                $conductor = $fila['conductor'];
                $base = floatval($fila['base'] ?? 0);
                
                if (!isset($conductores_fusionados[$conductor])) {
                    $conductores_fusionados[$conductor] = 0;
                }
                $conductores_fusionados[$conductor] += $base;
            }
        }
        
        if (isset($datos['conductoresBase']) && is_array($datos['conductoresBase'])) {
            foreach ($datos['conductoresBase'] as $conductor => $base) {
                if (!isset($conductores_fusionados[$conductor])) {
                    $conductores_fusionados[$conductor] = 0;
                }
                $conductores_fusionados[$conductor] += $base;
            }
        }
    }
    
    foreach ($conductores_fusionados as $conductor => $base_total) {
        if ($base_total > 0) {
            $fusionado['datos_json']['filasManuales'][] = [
                'conductor' => $conductor,
                'base' => $base_total,
                'cuenta' => '',
                'segSocial' => 0,
                'estado' => ''
            ];
        }
    }
    
    echo json_encode(['success' => true, 'cuenta_fusionada' => $fusionado]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_pagado_cuenta') {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    $pagado = intval($_POST['pagado']);
    
    $sql = "UPDATE cuentas_guardadas SET pagado = $pagado WHERE id = $id";
    $resultado = $conn->query($sql);
    
    echo json_encode([
        'success' => $resultado, 
        'message' => $resultado ? 'Estado actualizado correctamente' : 'Error al actualizar el estado'
    ]);
    exit;
}

/* ================= TARIFAS DINÁMICAS ================= */
$columnas_tarifas = [];
$tarifas = [];

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
    $nombre  = $conn->real_escape_string($_GET['viajes_conductor']);
    $desde   = $conn->real_escape_string($_GET['desde'] ?? '');
    $hasta   = $conn->real_escape_string($_GET['hasta'] ?? '');
    $empresas = isset($_GET['empresas']) ? json_decode($_GET['empresas'], true) : [];

    $sql = "SELECT id, fecha, ruta, empresa, tipo_vehiculo, pagado
            FROM viajes
            WHERE nombre = '$nombre'
              AND fecha BETWEEN '$desde' AND '$hasta'";
    
    if (!empty($empresas)) {
        $empresas_escapadas = array_map(function($e) use ($conn) {
            return "'" . $conn->real_escape_string($e) . "'";
        }, $empresas);
        $sql .= " AND empresa IN (" . implode(',', $empresas_escapadas) . ")";
    }
    
    $sql .= " ORDER BY fecha ASC";

    $res = $conn->query($sql);

    $rowsHTML = "";
    $counts = ['otro' => 0];
    foreach ($todas_clasificaciones as $clas) {
        $counts[strtolower($clas)] = 0;
    }

    if ($res && $res->num_rows > 0) {
        while ($r = $res->fetch_assoc()) {
            $ruta = (string)$r['ruta'];
            $vehiculo = $r['tipo_vehiculo'];
            
            $key = mb_strtolower(trim($ruta . '|' . $vehiculo), 'UTF-8');
            $cat = isset($clasificaciones[$key]) ? strtolower($clasificaciones[$key]) : 'otro';
            
            if (!in_array($cat, $todas_clasificaciones)) {
                $cat = 'otro';
            }

            if (isset($counts[$cat])) {
                $counts[$cat]++;
            } else {
                $counts[$cat] = 1;
            }

            $color_class = '';
            switch($cat) {
                case 'completo': $color_class = 'bg-emerald-100 text-emerald-800 border-emerald-300'; break;
                case 'medio': $color_class = 'bg-amber-100 text-amber-800 border-amber-300'; break;
                case 'extra': $color_class = 'bg-slate-200 text-slate-800 border-slate-300'; break;
                case 'siapana': $color_class = 'bg-fuchsia-100 text-fuchsia-700 border-fuchsia-200'; break;
                case 'carrotanque': $color_class = 'bg-cyan-100 text-cyan-800 border-cyan-200'; break;
                default: $color_class = 'bg-gray-100 text-gray-700 border-gray-200';
            }

            $pagadoBadge = $r['pagado'] ? 
                '<span class="inline-block px-2 py-0.5 rounded text-xs bg-green-100 text-green-700 border border-green-300 ml-2">✓ Pagado</span>' : 
                '<span class="inline-block px-2 py-0.5 rounded text-xs bg-red-100 text-red-700 border border-red-300 ml-2">○ Pendiente</span>';

            $rowClass = $r['pagado'] ? 'viaje-pagado bg-green-50' : '';

            $rowsHTML .= "<tr class='viaje-item cat-$cat $rowClass' data-viaje-id='{$r['id']}'>
                    <td class='px-3 py-2 text-center'>
                        <input type='checkbox' class='viaje-checkbox w-4 h-4 rounded border-slate-300 text-blue-600' data-viaje-id='{$r['id']}'>
                    </td>
                    <td class='px-3 py-2'>".htmlspecialchars($r['fecha'])."</td>
                    <td class='px-3 py-2'>
                        <span class='inline-block px-2 py-1 rounded text-xs font-medium border $color_class'>
                            ".htmlspecialchars($ruta)."
                        </span>
                        $pagadoBadge
                    </td>
                    <td class='px-3 py-2'>
                        <span class='inline-block px-2 py-1 rounded text-xs bg-blue-50 text-blue-700 border border-blue-200'>
                            ".htmlspecialchars($r['empresa'])."
                        </span>
                    </td>
                    <td class='px-3 py-2'>
                        <span class='inline-block px-2 py-1 rounded text-xs bg-slate-100 border border-slate-300'>
                            ".htmlspecialchars($vehiculo)."
                        </span>
                    </td>
                 </tr>";
        }
    } else {
        $rowsHTML .= "<tr><td colspan='5' class='px-3 py-4 text-center text-slate-500'>Sin viajes en el rango/empresas seleccionadas.</td></tr>";
    }

    ?>
    <div class='space-y-3'>
        <div class='flex flex-wrap gap-2 text-xs' id="legendFilterBar">
            <?php
            $colores_base = [
                'completo'         => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
                'medio'            => 'bg-amber-100 text-amber-800 border border-amber-200',
                'extra'            => 'bg-slate-200 text-slate-800 border border-slate-300',
                'siapana'          => 'bg-fuchsia-100 text-fuchsia-700 border border-fuchsia-200',
                'carrotanque'      => 'bg-cyan-100 text-cyan-800 border border-cyan-200',
            ];
            
            $legend = [];
            foreach ($todas_clasificaciones as $clas) {
                $clas_normalizada = strtolower($clas);
                
                if (isset($colores_base[$clas_normalizada])) {
                    $legend[$clas_normalizada] = [
                        'label' => ucwords(str_replace(['_', ' medio', ' completo'], [' ', ' Medio', ' Completo'], $clas)),
                        'badge' => $colores_base[$clas_normalizada]
                    ];
                } else {
                    $legend[$clas_normalizada] = [
                        'label' => ucwords(str_replace('_', ' ', $clas)),
                        'badge' => 'bg-gray-100 text-gray-700 border border-gray-200'
                    ];
                }
            }
            $legend['otro'] = ['label'=>'Sin clasificar','badge'=>'bg-gray-100 text-gray-700 border border-gray-200'];

            foreach ($legend as $k => $l) {
                $countVal = $counts[$k] ?? 0;
                if ($countVal > 0) {
                    echo "<button
                            class='legend-pill inline-flex items-center gap-2 px-3 py-2 rounded-full {$l['badge']} hover:opacity-90 transition ring-0 outline-none border cursor-pointer select-none'
                            data-tipo='{$k}'
                          >
                            <span class='w-2.5 h-2.5 rounded-full bg-opacity-100 border border-white/30 shadow-inner'></span>
                            <span class='font-semibold text-[13px]'>{$l['label']}</span>
                            <span class='text-[11px] font-semibold opacity-80'>({$countVal})</span>
                          </button>";
                }
            }
            ?>
        </div>

        <div class="flex flex-wrap items-center gap-2 p-2 bg-blue-50 rounded-lg border border-blue-200">
            <span class="text-xs font-medium text-blue-700">⚡ Acciones en viajes:</span>
            <button type="button" class="btn-pagar-viajes-modal px-3 py-1.5 rounded-lg text-xs font-medium bg-green-500 text-white hover:bg-green-600 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                ✅ Pagar seleccionados
            </button>
            <button type="button" class="btn-despagar-viajes-modal px-3 py-1.5 rounded-lg text-xs font-medium bg-amber-500 text-white hover:bg-amber-600 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                ↩️ Desmarcar seleccionados
            </button>
            <button type="button" class="btn-select-all-viajes px-3 py-1.5 rounded-lg text-xs font-medium bg-white border border-slate-300 hover:bg-slate-50">
                ☑️ Seleccionar todos
            </button>
            <span class="text-xs text-slate-500 ml-auto viajes-seleccionados-count">0 seleccionados</span>
        </div>

        <div class='overflow-x-auto'>
            <table class='min-w-full text-sm text-left' id="viajesModalTable">
                <thead class='bg-blue-600 text-white'>
                    <tr>
                        <th class='px-3 py-2 text-center w-10'>
                            <input type="checkbox" id="selectAllViajesModal" class="w-4 h-4 rounded border-white">
                        </th>
                        <th class='px-3 py-2'>Fecha</th>
                        <th class='px-3 py-2'>Ruta</th>
                        <th class='px-3 py-2'>Empresa</th>
                        <th class='px-3 py-2'>Vehículo</th>
                    </tr>
                </thead>
                <tbody class='divide-y divide-gray-100 bg-white' id="viajesTableBody">
                <?= $rowsHTML ?>
                </tbody>
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
    <html lang="es"><head>
        <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
        <title>Ajuste de Pago</title><script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-800">
    <div class="max-w-lg mx-auto p-6">
        <div class="bg-white shadow-sm rounded-2xl p-6 border border-slate-200">
            <h2 class="text-2xl font-bold text-center mb-2">📅 Ajuste de Pago por rango</h2>
            <form method="get" class="space-y-4">
                <label class="block"><span class="block text-sm font-medium mb-1">Desde</span>
                    <input type="date" name="desde" required class="w-full rounded-xl border border-slate-300 px-3 py-2">
                </label>
                <label class="block"><span class="block text-sm font-medium mb-1">Hasta</span>
                    <input type="date" name="hasta" required class="w-full rounded-xl border border-slate-300 px-3 py-2">
                </label>
                <div class="block">
                    <span class="block text-sm font-medium mb-2">Empresas (puedes seleccionar varias)</span>
                    <div class="max-h-60 overflow-y-auto border border-slate-300 rounded-xl p-3 space-y-2">
                        <?php foreach($empresas as $e): ?>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="empresas[]" value="<?= htmlspecialchars($e) ?>" class="rounded border-slate-300">
                            <span><?= htmlspecialchars($e) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button class="w-full rounded-xl bg-blue-600 text-white py-2.5 font-semibold shadow">Continuar</button>
            </form>
        </div>
    </div>
    </body></html>
    <?php exit;
}
include("nav.php");

/* ================= Parámetros ================= */
$desde = $_GET['desde'];
$hasta = $_GET['hasta'];
$empresasSeleccionadas = isset($_GET['empresas']) ? $_GET['empresas'] : [];
$empresasSeleccionadasEsc = array_map(function($e) use ($conn) {
    return $conn->real_escape_string($e);
}, $empresasSeleccionadas);

/* ================= CARGAR TARIFAS ================= */
$tarifas = [];
if (!empty($empresasSeleccionadasEsc)) {
    $empresasStr = "'" . implode("','", $empresasSeleccionadasEsc) . "'";
    $resT = $conn->query("SELECT * FROM tarifas WHERE empresa IN ($empresasStr)");
    if ($resT) {
        while($r = $resT->fetch_assoc()) {
            $empresa = $r['empresa'];
            $tipo_vehiculo = $r['tipo_vehiculo'];
            
            if (!isset($tarifas[$empresa])) {
                $tarifas[$empresa] = [];
            }
            
            $tarifa_normalizada = [];
            foreach ($r as $key => $value) {
                $tarifa_normalizada[strtolower($key)] = $value;
            }
            
            $tarifas[$empresa][$tipo_vehiculo] = $tarifa_normalizada;
        }
    }
}

/* ================= Viajes del rango (CON IDs) ================= */
$sqlV = "SELECT id, nombre, ruta, empresa, tipo_vehiculo
         FROM viajes
         WHERE fecha BETWEEN '$desde' AND '$hasta'";
if (!empty($empresasSeleccionadasEsc)) {
    $empresasStr = "'" . implode("','", $empresasSeleccionadasEsc) . "'";
    $sqlV .= " AND empresa IN ($empresasStr)";
}
$resV = $conn->query($sqlV);

$viajesPorConductor = [];
$viajesIdsPorConductor = [];
$contadores = [];

if ($resV) {
    while ($row = $resV->fetch_assoc()) {
        $id = $row['id'];
        $nombre = $row['nombre'];
        $empresa = $row['empresa'];
        $ruta = $row['ruta'];
        $vehiculo = $row['tipo_vehiculo'];
        
        if (!isset($viajesPorConductor[$nombre])) {
            $viajesPorConductor[$nombre] = [];
            $viajesIdsPorConductor[$nombre] = [];
        }
        $viajesPorConductor[$nombre][] = [
            'id' => $id,
            'empresa' => $empresa,
            'ruta' => $ruta,
            'vehiculo' => $vehiculo
        ];
        $viajesIdsPorConductor[$nombre][] = $id;
        
        if (!isset($contadores[$nombre])) {
            $contadores[$nombre] = [];
            foreach ($todas_clasificaciones as $clas) {
                $clas_normalizada = strtolower($clas);
                $contadores[$nombre][$clas_normalizada] = 0;
            }
        }
        
        $key = mb_strtolower(trim($ruta . '|' . $vehiculo), 'UTF-8');
        $clasif = isset($clasificaciones[$key]) ? strtolower($clasificaciones[$key]) : '';
        
        if ($clasif !== '' && in_array($clasif, $todas_clasificaciones)) {
            $contadores[$nombre][$clasif]++;
        }
    }
}

/* ================= PRÉSTAMOS ================= */
$prestamosList = [];

$qPrest = "
  SELECT deudor,
         prestamista,
         empresa,
         monto,
         fecha,
         id
  FROM prestamos
  WHERE (pagado IS NULL OR pagado = 0)
  ORDER BY empresa, deudor";

if ($rP = $conn->query($qPrest)) {
    while($r = $rP->fetch_assoc()){
        $name = $r['deudor'];
        $key  = norm_person($name);
        $monto = (int)$r['monto'];
        
        if (strpos(strtolower($r['prestamista']), 'asociaci') !== false) {
            $total = $monto;
        } else {
            $fecha_prestamo = new DateTime($r['fecha']);
            $fecha_actual = new DateTime();
            $fecha_limite = new DateTime('2025-10-29');
            
            $interes = 0.10;
            if ($fecha_prestamo >= $fecha_limite) {
                $interes = 0.13;
            }
            
            $meses = 0;
            if ($fecha_actual > $fecha_prestamo) {
                $diff = $fecha_prestamo->diff($fecha_actual);
                $meses = $diff->m + ($diff->y * 12);
                if ($diff->d > 0) $meses++;
            }
            
            $total = $monto;
            if ($meses > 0) {
                $total = $monto + ($monto * $interes * $meses);
            }
        }
        
        $prestamosList[] = [
            'id' => $r['id'],
            'name' => $name,
            'key' => $key,
            'monto_original' => $monto,
            'total' => (int)round($total),
            'empresa' => $r['empresa'],
            'prestamista' => $r['prestamista'],
            'fecha' => $r['fecha']
        ];
    }
}

/* ================= Filas base ================= */
$filas = []; 
$total_facturado = 0;
$conductoresBaseMap = [];

foreach ($contadores as $nombre => $v) {
    $total = 0;
    
    $viajesConductor = $viajesPorConductor[$nombre] ?? [];
    
    foreach ($viajesConductor as $viaje) {
        $empresa = $viaje['empresa'];
        $ruta = $viaje['ruta'];
        $vehiculo = $viaje['vehiculo'];
        
        $key = mb_strtolower(trim($ruta . '|' . $vehiculo), 'UTF-8');
        $clasif = isset($clasificaciones[$key]) ? strtolower($clasificaciones[$key]) : 'otro';
        
        $precio = 0;
        if (isset($tarifas[$empresa][$vehiculo])) {
            $t = $tarifas[$empresa][$vehiculo];
            
            if (isset($t[$clasif])) {
                $precio = (float)$t[$clasif];
            } else {
                $clasif_guion = str_replace(' ', '_', $clasif);
                if (isset($t[$clasif_guion])) {
                    $precio = (float)$t[$clasif_guion];
                } else {
                    $clasif_espacio = str_replace('_', ' ', $clasif);
                    if (isset($t[$clasif_espacio])) {
                        $precio = (float)$t[$clasif_espacio];
                    }
                }
            }
        }
        
        $total += $precio;
    }

    $filas[] = ['nombre'=>$nombre, 'total_bruto'=>(int)$total];
    $conductoresBaseMap[$nombre] = (int)$total;
    $total_facturado += (int)$total;
}

usort($filas, fn($a,$b)=> $b['total_bruto'] <=> $a['total_bruto']);

$CONDUCTORES_LIST = array_column($filas, 'nombre');

// Pasar los IDs de viajes a JavaScript
$viajesIdsJSON = json_encode($viajesIdsPorConductor, JSON_UNESCAPED_UNICODE);
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
        .viajes-card{ width:min(850px,94vw); max-height:90vh; overflow:hidden; border-radius:16px; background:#fff; box-shadow:0 20px 60px rgba(0,0,0,.25); border:1px solid #e5e7eb; }
        .viajes-header{padding:14px 16px;border-bottom:1px solid #eef2f7}
        .viajes-body{padding:14px 16px;overflow:auto; max-height:70vh}
        .conductor-link{cursor:pointer; color:#0d6efd; text-decoration:underline;}
        .estado-pagado { background-color: #f0fdf4 !important; border-left: 4px solid #22c55e; }
        .estado-pendiente { background-color: #fef2f2 !important; border-left: 4px solid #ef4444; }
        .estado-procesando { background-color: #fffbeb !important; border-left: 4px solid #f59e0b; }
        .estado-parcial { background-color: #eff6ff !important; border-left: 4px solid #3b82f6; }
        .fila-manual { background-color: #f0f9ff !important; border-left: 4px solid #0ea5e9; }
        .buscar-container { position: relative; }
        .buscar-clear { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); display: none; }
        #floatingPanel { box-shadow: 0 10px 40px rgba(0,0,0,0.15); z-index: 9999; }
        #panelDragHandle { cursor: move; }
        .fila-seleccionada { background-color: #f0f9ff !important; }
        .fila-pagada { background-color: #f0fdf4 !important; border-left: 4px solid #22c55e !important; }
        .viaje-pagado { background-color: #f0fdf4 !important; }
        .empresas-container { max-height: 150px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.75rem; background: white; }
        .empresa-checkbox { display: flex; align-items: center; gap: 0.5rem; padding: 0.25rem 0; }
        .switch-pagado { position: relative; display: inline-block; width: 50px; height: 24px; }
        .switch-pagado input { opacity: 0; width: 0; height: 0; }
        .switch-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ef4444; transition: .3s; border-radius: 34px; }
        .switch-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
        input:checked + .switch-slider { background-color: #22c55e; }
        input:checked + .switch-slider:before { transform: translateX(26px); }
        .bd-badge { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .switch-small { width: 40px; height: 20px; }
        .switch-small .switch-slider:before { height: 14px; width: 14px; left: 3px; bottom: 3px; }
        input:checked + .switch-small .switch-slider:before { transform: translateX(20px); }
        
        .empresas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.5rem;
            max-height: 200px;
            overflow-y: auto;
            padding: 0.5rem;
            background: #f8fafc;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
        }
        .empresa-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: white;
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }
        .empresa-item:hover { background: #eff6ff; border-color: #3b82f6; }
        .badge-empresa {
            background: #e2e8f0; color: #475569; font-size: 0.75rem;
            padding: 0.25rem 0.5rem; border-radius: 9999px; margin-left: auto;
        }
        .separador-empresa {
            background: linear-gradient(to right, #f1f5f9, transparent);
            padding: 0.5rem 1rem; font-weight: 600; color: #334155;
            border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 10;
        }
        .prest-item { transition: all 0.2s; border-left: 3px solid transparent; }
        .prest-item:hover { background-color: #f0f9ff; border-left-color: #3b82f6; }
        .badge-historico {
            background: #fef3c7; color: #92400e; font-size: 0.65rem;
            padding: 0.15rem 0.4rem; border-radius: 9999px; margin-left: 0.25rem;
            display: inline-block; border: 1px solid #fbbf24;
        }
        .badge-pagado-conductor {
            background: #dcfce7; color: #166534; font-size: 0.65rem;
            padding: 0.15rem 0.5rem; border-radius: 9999px; margin-left: 0.25rem;
            display: inline-block; border: 1px solid #86efac;
        }
        .disponible-positivo { color: #059669; font-weight: 600; }
        .disponible-negativo { color: #dc2626; font-weight: 600; }
        
        .btn-pagar {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            border: 1px solid #15803d; color: white; transition: all 0.2s; font-weight: 600;
        }
        .btn-pagar:hover { background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .btn-despagar {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            border: 1px solid #b45309; color: white; transition: all 0.2s; font-weight: 600;
        }
        .btn-despagar:hover { background: linear-gradient(135deg, #d97706 0%, #b45309 100%); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        
        .btn-actualizar {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            border: 1px solid #6d28d9; color: white; transition: all 0.2s; font-weight: 600;
        }
        .btn-actualizar:hover { background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        
        .cuenta-checkbox { width: 18px; height: 18px; cursor: pointer; accent-color: #3b82f6; }
        .fila-cuenta-seleccionada { background-color: #eff6ff !important; border-left: 4px solid #3b82f6; }
        
        .desglose-prestamistas { margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px dashed #fbbf24; font-size: 0.8rem; }
        .prestamista-linea { display: flex; justify-content: space-between; align-items: center; padding: 0.15rem 0; color: #92400e; }
        .prestamista-nombre { font-weight: 500; }
        .prestamista-monto { font-weight: 600; }
        
        .comprobante-preview { background-size: cover; background-position: center; background-repeat: no-repeat; }
        .modal-comprobante {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.9); z-index: 20000;
            display: flex; align-items: center; justify-content: center; cursor: pointer;
        }
        .modal-comprobante img { max-width: 90vw; max-height: 90vh; object-fit: contain; }
        
        .btn-excel {
            background: linear-gradient(135deg, #217346 0%, #185a2d 100%);
            border: 1px solid #166534; color: white; transition: all 0.2s;
        }
        .btn-excel:hover { background: linear-gradient(135deg, #1e6e3e 0%, #144d25 100%); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        
        .acciones-bar { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.75rem 1rem; }
        
        .cuenta-cargada-info {
            background: #ede9fe; border: 1px solid #c4b5fd; border-radius: 0.75rem; padding: 0.5rem 0.75rem;
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">
<header class="max-w-[1600px] mx-auto px-3 md:px-4 pt-6">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-5 py-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <h2 class="text-xl md:text-2xl font-bold">
                🧾 Ajuste de Pago 
                <span class="bd-badge text-xs px-2 py-1 rounded-full ml-2">Base de Datos</span>
                <span class="bg-purple-600 text-white text-xs px-2 py-1 rounded-full ml-1">Múltiples Empresas</span>
            </h2>
            <div class="flex items-center gap-2 flex-wrap">
                <button id="btnExportExcel" class="btn-excel rounded-lg px-3 py-2 text-sm font-medium flex items-center gap-2">
                    <span>📥</span> Descargar Excel
                </button>
                <button id="btnShowSaveCuenta" class="rounded-lg border border-amber-300 px-3 py-2 text-sm bg-amber-50 hover:bg-amber-100">⭐ Guardar cuenta</button>
                <button id="btnActualizarCuenta" class="btn-actualizar rounded-lg px-3 py-2 text-sm flex items-center gap-2 hidden">
                    <span>🔄</span> Actualizar cuenta
                </button>
                <button id="btnShowGestorCuentas" class="rounded-lg border border-blue-300 px-3 py-2 text-sm bg-blue-50 hover:bg-blue-100">📚 Cuentas guardadas</button>
            </div>
        </div>

        <!-- Info de cuenta cargada -->
        <div id="cuentaCargadaInfo" class="cuenta-cargada-info mt-3 hidden">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 text-sm">
                    <span class="font-semibold text-violet-700">📌 Cuenta cargada:</span>
                    <span id="cuentaCargadaNombre" class="text-violet-900 font-medium"></span>
                    <span class="text-xs text-violet-500">(ID: <span id="cuentaCargadaId"></span>)</span>
                </div>
                <button id="btnCerrarCuentaCargada" class="text-xs text-violet-500 hover:text-violet-700 underline">✕ Cerrar</button>
            </div>
        </div>

        <form method="get" class="mt-4 grid grid-cols-1 md:grid-cols-12 gap-3">
            <div class="md:col-span-2">
                <label class="text-xs font-medium">Desde</label>
                <input type="date" name="desde" id="filtro_desde" value="<?= htmlspecialchars($desde) ?>" class="w-full border rounded-xl px-3 py-2">
            </div>
            <div class="md:col-span-2">
                <label class="text-xs font-medium">Hasta</label>
                <input type="date" name="hasta" id="filtro_hasta" value="<?= htmlspecialchars($hasta) ?>" class="w-full border rounded-xl px-3 py-2">
            </div>
            <div class="md:col-span-6">
                <label class="text-xs font-medium">Empresas</label>
                <div class="empresas-container" id="empresasContainer">
                    <?php
                    $resEmp2 = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
                    while ($e = $resEmp2->fetch_assoc()) {
                        $checked = in_array($e['empresa'], $empresasSeleccionadas) ? 'checked' : '';
                        echo "<div class='empresa-checkbox'>";
                        echo "<input type='checkbox' name='empresas[]' value='" . htmlspecialchars($e['empresa']) . "' $checked>";
                        echo "<label class='text-sm'>" . htmlspecialchars($e['empresa']) . "</label>";
                        echo "</div>";
                    }
                    ?>
                </div>
                <div class="flex gap-2 mt-2">
                    <button type="button" id="btnSeleccionarTodas" class="text-xs px-3 py-1.5 bg-blue-50 rounded-lg border border-blue-200 hover:bg-blue-100">✓ Todas</button>
                    <button type="button" id="btnLimpiarTodas" class="text-xs px-3 py-1.5 bg-slate-50 rounded-lg border border-slate-200 hover:bg-slate-100">✗ Limpiar</button>
                </div>
            </div>
            <div class="md:col-span-2 flex items-end">
                <button type="submit" class="w-full bg-blue-600 text-white py-2.5 rounded-xl font-semibold shadow hover:bg-blue-700 transition">Aplicar</button>
            </div>
        </form>
    </div>
</header>

<main class="max-w-[1600px] mx-auto px-3 md:px-4 py-6 space-y-5">
    <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
        <div class="grid grid-cols-1 md:grid-cols-7 gap-4">
            <div>
                <div class="text-xs text-slate-500 mb-1">Conductores</div>
                <div class="text-lg font-semibold"><?= count($filas) ?></div>
            </div>
            <label class="block md:col-span-2">
                <span class="block text-xs font-medium mb-1">Cuenta de cobro</span>
                <input id="inp_facturado" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right num"
                       value="<?= number_format($total_facturado,0,',','.') ?>">
            </label>
            <label class="block md:col-span-2">
                <span class="block text-xs font-medium mb-1">Viajes manuales</span>
                <input id="inp_viajes_manuales" type="text" class="w-full rounded-xl border border-green-200 px-3 py-2 text-right num bg-green-50" value="0" readonly>
            </label>
            <label class="block">
                <span class="block text-xs font-medium mb-1">% Ajuste</span>
                <input id="inp_porcentaje_ajuste" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right num"
                       value="5" placeholder="Ej: 5">
            </label>
            <div>
                <div class="text-xs text-slate-500 mb-1">Total ajuste</div>
                <div id="lbl_total_ajuste" class="text-lg font-semibold text-amber-600 num">0</div>
            </div>
        </div>
        <div class="mt-2 text-xs text-slate-600">
            <span class="font-semibold">Empresas seleccionadas:</span> 
            <?= !empty($empresasSeleccionadas) ? implode(' • ', array_map('htmlspecialchars', $empresasSeleccionadas)) : 'Todas' ?>
        </div>
    </section>

    <!-- BARRA DE ACCIONES -->
    <section class="acciones-bar flex flex-wrap items-center gap-3">
        <span class="text-sm font-semibold text-slate-700">⚡ Acciones rápidas:</span>
        <button id="btnPagarSeleccionados" class="btn-pagar rounded-lg px-4 py-2 text-sm flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
            <span>✅</span> Marcar como PAGADOS
        </button>
        <button id="btnDespagarSeleccionados" class="btn-despagar rounded-lg px-4 py-2 text-sm flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
            <span>↩️</span> Desmarcar (No pagados)
        </button>
        <span id="viajesCountInfo" class="text-xs text-slate-500 ml-auto"></span>
    </section>

    <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
            <div>
                <h3 class="text-lg font-semibold">Conductores</h3>
                <div id="contador-conductores" class="text-xs text-slate-500 mt-1">
                    Mostrando <?= count($filas) ?> de <?= count($filas) ?> conductores
                </div>
            </div>
            <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
                <select id="filtroEstado" class="rounded-lg border border-slate-300 px-3 py-2 text-sm min-w-[150px] bg-white">
                    <option value="">📊 Todos los estados</option>
                    <option value="pagado">✅ Pagado</option>
                    <option value="pendiente">❌ Pendiente</option>
                    <option value="procesando">🔄 Procesando</option>
                    <option value="parcial">⚠️ Parcial</option>
                </select>
                
                <div class="buscar-container w-full md:w-64">
                    <input id="buscadorConductores" type="text" 
                           placeholder="Buscar conductor..." 
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 pl-3 pr-10">
                    <button id="clearBuscar" class="buscar-clear">✕</button>
                </div>
                <button id="btnAddManual" class="rounded-lg bg-green-600 text-white px-4 py-2 text-sm hover:bg-green-700 whitespace-nowrap">
                    ➕ Agregar manual
                </button>
            </div>
        </div>

        <div class="overflow-auto max-h-[70vh] rounded-xl border border-slate-200 table-sticky">
            <table class="min-w-[1400px] w-full text-sm" id="tablaPrincipal">
                <thead class="bg-blue-600 text-white">
                    <tr>
                        <th class="px-3 py-2 text-left">Conductor</th>
                        <th class="px-3 py-2 text-right">Base</th>
                        <th class="px-3 py-2 text-right">Ajuste</th>
                        <th class="px-3 py-2 text-right">Llegó</th>
                        <th class="px-3 py-2 text-right">Ret 3.5%</th>
                        <th class="px-3 py-2 text-right">4x1000</th>
                        <th class="px-3 py-2 text-right">Aporte 10%</th>
                        <th class="px-3 py-2 text-right">Seg social</th>
                        <th class="px-3 py-2 text-left">Préstamos</th>
                        <th class="px-3 py-2 text-left">N° Cuenta</th>
                        <th class="px-3 py-2 text-right">A pagar</th>
                        <th class="px-3 py-2 text-center">Estado</th>
                        <th class="px-3 py-2 text-center">Comprobante</th>
                        <th class="px-3 py-2 text-center">
                            <input type="checkbox" id="selectAllCheckbox" class="checkbox-conductor" title="Seleccionar todos">
                        </th>
                    </tr>
                </thead>
                <tbody id="tbody" class="divide-y divide-slate-100 bg-white">
                <?php 
                $contador_filas = 0;
                foreach ($filas as $f): 
                    $contador_filas++;
                    $nombre_normalizado = htmlspecialchars(mb_strtolower($f['nombre']));
                    $tieneViajes = isset($viajesIdsPorConductor[$f['nombre']]) && count($viajesIdsPorConductor[$f['nombre']]) > 0;
                    $viajesCount = $tieneViajes ? count($viajesIdsPorConductor[$f['nombre']]) : 0;
                ?>
                    <tr data-conductor="<?= $nombre_normalizado ?>" 
                        data-base="<?= $f['total_bruto'] ?>" 
                        data-row-index="<?= $contador_filas ?>"
                        data-tiene-viajes="<?= $tieneViajes ? '1' : '0' ?>"
                        data-viajes-count="<?= $viajesCount ?>">
                        <td class="px-3 py-2">
                            <button type="button" class="conductor-link text-blue-600 hover:underline" data-nombre="<?= htmlspecialchars($f['nombre']) ?>" title="Ver viajes">
                                <?= htmlspecialchars($f['nombre']) ?>
                            </button>
                            <?php if ($tieneViajes): ?>
                            <span class="badge-pagado-conductor" title="<?= $viajesCount ?> viajes"><?= $viajesCount ?> viajes</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 text-right num base"><?= number_format($f['total_bruto'],0,',','.') ?></td>
                        <td class="px-3 py-2 text-right num ajuste">0</td>
                        <td class="px-3 py-2 text-right num llego">0</td>
                        <td class="px-3 py-2 text-right num ret">0</td>
                        <td class="px-3 py-2 text-right num mil4">0</td>
                        <td class="px-3 py-2 text-right num apor">0</td>
                        <td class="px-3 py-2 text-right">
                            <input type="text" class="ss w-full max-w-[100px] rounded-lg border border-slate-300 px-2 py-1 text-right num" value="">
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-1">
                                <span class="num prest text-sm font-medium">0</span>
                                <button type="button" class="btn-prest text-xs px-2 py-1 rounded border border-slate-300 bg-slate-50 hover:bg-slate-100">
                                    Sel
                                </button>
                            </div>
                            <div class="text-[10px] text-slate-500 selected-deudor truncate max-w-[150px]"></div>
                        </td>
                        <td class="px-3 py-2">
                            <input type="text" class="cta w-full max-w-[140px] rounded-lg border border-slate-300 px-2 py-1" value="" placeholder="N° cuenta">
                        </td>
                        <td class="px-3 py-2 text-right num pagar">0</td>
                        <td class="px-3 py-2 text-center">
                            <select class="estado-pago w-full max-w-[120px] rounded-lg border border-slate-300 px-2 py-1 text-sm">
                                <option value="">Sin estado</option>
                                <option value="pagado">✅ Pagado</option>
                                <option value="pendiente">❌ Pendiente</option>
                                <option value="procesando">🔄 Procesando</option>
                                <option value="parcial">⚠️ Parcial</option>
                            </select>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <div class="comprobante-container flex flex-col items-center gap-1">
                                <input type="file" 
                                       class="comprobante-file hidden" 
                                       accept="image/*"
                                       data-conductor="<?= htmlspecialchars($f['nombre']) ?>">
                                <div class="comprobante-preview w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center border border-gray-300 hover:border-blue-500 cursor-pointer transition overflow-hidden"
                                     onclick="this.previousElementSibling.click()">
                                    <span class="text-gray-400 text-xs">📷</span>
                                </div>
                                <button type="button" 
                                        class="btn-eliminar-comprobante hidden text-xs text-red-500 hover:text-red-700"
                                        onclick="eliminarComprobante('<?= htmlspecialchars($f['nombre']) ?>')">
                                    🗑️
                                </button>
                            </div>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <input type="checkbox" class="checkbox-conductor selector-conductor">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-slate-50 font-semibold">
                    <tr>
                        <td class="px-3 py-2" colspan="3">Totales</td>
                        <td class="px-3 py-2 text-right num" id="tot_llego">0</td>
                        <td class="px-3 py-2 text-right num" id="tot_ret">0</td>
                        <td class="px-3 py-2 text-right num" id="tot_mil4">0</td>
                        <td class="px-3 py-2 text-right num" id="tot_apor">0</td>
                        <td class="px-3 py-2 text-right num" id="tot_ss">0</td>
                        <td class="px-3 py-2 text-right num" id="tot_prest">0</td>
                        <td class="px-3 py-2"></td>
                        <td class="px-3 py-2 text-right num" id="tot_pagar">0</td>
                        <td class="px-3 py-2" colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
</main>

<!-- Panel flotante -->
<div id="floatingPanel" class="hidden fixed z-50 bg-white border border-blue-300 rounded-xl shadow-lg" style="top: 100px; left: 100px; min-width: 300px;">
    <div id="panelDragHandle" class="cursor-move bg-blue-600 text-white px-4 py-3 rounded-t-xl flex items-center justify-between">
        <div class="font-semibold flex items-center gap-2">
            <span>📊 Sumatoria</span>
            <span id="selectedCount" class="bg-blue-500 text-white text-xs px-2 py-1 rounded-full">0</span>
        </div>
        <button id="closePanel" class="text-white hover:bg-blue-700 p-1 rounded">✕</button>
    </div>
    
    <div class="p-4">
        <div class="grid grid-cols-2 gap-3">
            <div class="bg-slate-50 p-3 rounded-lg">
                <div class="text-xs text-slate-500 mb-1">Total a pagar</div>
                <div id="panelTotalPagar" class="text-xl font-bold text-emerald-600 num">0</div>
            </div>
            <div class="bg-slate-50 p-3 rounded-lg">
                <div class="text-xs text-slate-500 mb-1">Promedio</div>
                <div id="panelPromedio" class="text-lg font-semibold text-blue-600 num">0</div>
            </div>
        </div>
        
        <div class="text-xs text-slate-500 mt-3 space-y-1">
            <div class="flex justify-between">
                <span>Valor que llegó:</span><span id="panelLlego" class="num font-semibold">0</span>
            </div>
            <div class="flex justify-between">
                <span>Retención 3.5%:</span><span id="panelRet" class="num">0</span>
            </div>
            <div class="flex justify-between">
                <span>4×1000:</span><span id="panelMil4" class="num">0</span>
            </div>
            <div class="flex justify-between">
                <span>Aporte 10%:</span><span id="panelApor" class="num">0</span>
            </div>
            <div class="flex justify-between">
                <span>Seg. social:</span><span id="panelSS" class="num">0</span>
            </div>
            <div class="flex justify-between">
                <span>Préstamos:</span><span id="panelPrest" class="num">0</span>
            </div>
            <div class="flex justify-between border-t border-slate-200 pt-2 mt-2">
                <span class="font-semibold">Viajes seleccionados:</span>
                <span id="panelViajesCount" class="num font-bold text-blue-600">0</span>
            </div>
        </div>
    </div>
</div>

<!-- Modal Préstamos -->
<div id="prestModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="absolute inset-0 bg-black/30"></div>
    <div class="relative mx-auto my-4 md:my-8 prest-modal-content bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden flex flex-col" style="width: 95%; max-width: 1200px; max-height: 98vh;">
        
        <div class="px-4 md:px-6 py-4 border-b border-slate-200 bg-gradient-to-r from-blue-50 to-indigo-50 flex-none">
            <div class="flex items-center justify-between">
                <h3 class="text-base md:text-lg font-semibold flex items-center gap-2">
                    <span class="text-xl">💰</span>
                    <span>Préstamos de: <span id="conductorNombre" class="text-blue-700 font-bold"></span></span>
                </h3>
                <button id="btnCloseModal" class="p-2 rounded hover:bg-white/50 text-xl">✕</button>
            </div>
            
            <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <div class="bg-white p-3 rounded-lg border border-blue-200">
                    <div class="text-xs text-slate-500 mb-1">💵 Disponible para préstamos</div>
                    <div id="disponibleConductor" class="text-lg md:text-xl font-bold text-blue-600 num">$0</div>
                </div>
                <div class="bg-white p-3 rounded-lg border border-amber-200">
                    <div class="text-xs text-slate-500 mb-1">📋 Préstamos seleccionados</div>
                    <div id="totalSeleccionado" class="text-lg md:text-xl font-bold text-amber-600 num">$0</div>
                    <div id="desglosePrestamistas" class="desglose-prestamistas"></div>
                    <div id="diferenciaDisponible" class="text-xs mt-2 font-medium"></div>
                </div>
            </div>
        </div>
        
        <div class="px-4 md:px-6 py-3 border-b border-slate-200 bg-slate-50 flex-none">
            <div class="mb-3">
                <label class="block text-xs font-medium text-slate-700 mb-2">🏢 Filtrar por empresas:</label>
                <div class="empresas-grid" id="empresasMultiSelect"></div>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-2">
                <div class="flex-1">
                    <input id="prestSearch" type="text" placeholder="🔍 Buscar deudor..." class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
                </div>
                <div class="flex gap-2 flex-none">
                    <button id="btnDeseleccionarTodos" class="px-4 py-2.5 rounded-xl border border-amber-300 bg-amber-50 hover:bg-amber-100 text-sm font-medium text-amber-700 whitespace-nowrap">✕ Deseleccionar todos</button>
                    <button id="btnLimpiarFiltros" class="px-4 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-sm whitespace-nowrap">Limpiar filtros</button>
                </div>
            </div>
        </div>
        
        <div id="prestList" class="flex-1 overflow-y-auto p-4 md:p-6 bg-slate-50 prest-list-container" style="min-height: 300px;">
            <div class="p-8 text-center text-slate-500"><div class="text-5xl mb-3">📭</div><div class="text-lg font-medium">Cargando préstamos...</div></div>
        </div>
        
        <div class="flex-none border-t border-slate-200 bg-white p-4 md:p-6">
            <div class="mb-4 text-sm">
                <div class="flex flex-wrap justify-between items-center gap-2">
                    <div><span class="font-medium">Préstamos seleccionados:</span><span id="selCount" class="ml-1 font-bold text-blue-600">0</span></div>
                    <div><span class="font-medium">Total:</span><span id="selTotal" class="ml-1 font-bold text-emerald-600 num">$0</span></div>
                </div>
                <div id="detalleEmpresas" class="text-xs text-slate-500 mt-1 truncate">Ninguna selección</div>
            </div>
            
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <span class="text-sm text-slate-600 whitespace-nowrap">💰 Valor manual:</span>
                    <input id="prestValorManual" type="text" class="flex-1 sm:w-40 rounded-lg border border-amber-300 px-3 py-2.5 text-right num" placeholder="0">
                </div>
                <div class="flex gap-2 w-full sm:w-auto">
                    <button id="btnCancel" class="flex-1 sm:flex-none rounded-lg border border-slate-300 px-5 py-2.5 bg-white hover:bg-slate-50 font-medium">Cancelar</button>
                    <button id="btnAssign" class="flex-1 sm:flex-none rounded-lg border border-blue-600 px-6 py-2.5 bg-blue-600 text-white hover:bg-blue-700 font-medium shadow-lg">✅ Asignar selección</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Viajes -->
<div id="viajesModal" class="viajes-backdrop">
    <div class="viajes-card">
        <div class="viajes-header flex items-center justify-between">
            <h3 class="text-lg font-semibold">Viajes de <span id="viajesTitle"></span></h3>
            <button id="viajesCloseBtn" class="border px-3 py-1 rounded hover:bg-slate-100">✕</button>
        </div>
        <div class="viajes-body" id="viajesContent">Cargando...</div>
    </div>
</div>

<!-- Modal Guardar Cuenta -->
<div id="saveCuentaModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="absolute inset-0 bg-black/30"></div>
    <div class="relative mx-auto my-8 w-full max-w-lg bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold">⭐ Guardar cuenta de cobro</h3>
            <button id="btnCloseSaveCuenta" class="p-2 rounded hover:bg-slate-100">✕</button>
        </div>
        <div class="p-5 space-y-3">
            <label class="block">
                <span class="block text-xs font-medium mb-1">Nombre de la cuenta</span>
                <input id="cuenta_nombre" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2" placeholder="Ej: Hospital Sep 2025">
            </label>
            
            <div class="block">
                <span class="block text-xs font-medium mb-2">Empresas seleccionadas</span>
                <div id="cuenta_empresas_container" class="max-h-32 overflow-y-auto border border-slate-200 rounded-xl p-3 bg-slate-50 text-sm">Cargando empresas...</div>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <label class="block">
                    <span class="block text-xs font-medium mb-1">Rango</span>
                    <input id="cuenta_rango" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 bg-slate-50" readonly>
                </label>
                <label class="block">
                    <span class="block text-xs font-medium mb-1">Facturado</span>
                    <input id="cuenta_facturado" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right num">
                </label>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <label class="block">
                    <span class="block text-xs font-medium mb-1">% Ajuste</span>
                    <input id="cuenta_porcentaje" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right num" value="5">
                </label>
                <div class="block">
                    <span class="block text-xs font-medium mb-1">Estado</span>
                    <div class="flex items-center gap-3 p-2 border border-slate-200 rounded-xl">
                        <span id="pagadoLabel" class="text-sm px-2 py-1 rounded-full bg-red-100 text-red-700">NO PAGADO</span>
                        <label class="switch-pagado ml-auto">
                            <input type="checkbox" id="cuenta_pagado">
                            <span class="switch-slider"></span>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="text-xs text-slate-500 mt-2 p-3 bg-blue-50 rounded-xl">
                <strong>📌 Nota:</strong> Se guardarán todos los datos: conductores, préstamos asignados, seguridad social, cuentas bancarias, estados de pago, comprobantes y filas manuales.
            </div>
        </div>
        <div class="px-5 py-4 border-t border-slate-200 flex items-center justify-end gap-2">
            <button id="btnCancelSaveCuenta" class="rounded-lg border border-slate-300 px-4 py-2 bg-white hover:bg-slate-50">Cancelar</button>
            <button id="btnDoSaveCuenta" class="rounded-lg border border-amber-500 text-white px-4 py-2 bg-amber-500 hover:bg-amber-600">Guardar en BD</button>
        </div>
    </div>
</div>

<!-- Modal Gestor de Cuentas -->
<div id="gestorCuentasModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="absolute inset-0 bg-black/30"></div>
    <div class="relative mx-auto my-8 w-full max-w-6xl bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold">📚 Cuentas guardadas <span class="bd-badge text-xs px-2 py-1 rounded-full ml-2">Base de Datos</span></h3>
            <button id="btnCloseGestor" class="p-2 rounded hover:bg-slate-100">✕</button>
        </div>
        
        <div class="p-4 space-y-3">
            <div class="flex flex-col md:flex-row gap-3">
                <select id="filtroEmpresaCuentas" class="rounded-xl border border-slate-300 px-3 py-2 min-w-[200px]">
                    <option value="">Todas las empresas</option>
                </select>
                <select id="filtroEstadoPagado" class="rounded-xl border border-slate-300 px-3 py-2 min-w-[150px]">
                    <option value="">Todos los estados</option>
                    <option value="0">🔴 No pagadas</option>
                    <option value="1">🟢 Pagadas</option>
                </select>
                <div class="buscar-container flex-1">
                    <input id="buscaCuentaBD" type="text" placeholder="Buscar por nombre o empresa..." class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    <button id="clearBuscarBD" class="buscar-clear">✕</button>
                </div>
                <button id="btnRecargarCuentas" class="rounded-lg border border-blue-300 px-4 py-2 bg-blue-50 hover:bg-blue-100 whitespace-nowrap">🔄 Recargar</button>
            </div>
            
            <div class="flex items-center justify-between bg-amber-50 p-3 rounded-xl border border-amber-200">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-amber-800">🔀 Acciones con seleccionadas:</span>
                    <button id="btnFusionarSeleccionadas" class="px-4 py-2 rounded-lg bg-amber-500 text-white hover:bg-amber-600 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed" disabled>🔗 Fusionar seleccionadas</button>
                </div>
                <div class="text-xs text-amber-700"><span id="cuentasSeleccionadasCount">0</span> cuentas seleccionadas</div>
            </div>
            
            <div class="text-xs text-slate-500" id="contador-cuentas">Cargando cuentas desde Base de Datos...</div>
            
            <div class="overflow-auto max-h-[50vh] rounded-xl border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-blue-600 text-white">
                        <tr>
                            <th class="px-3 py-2 text-center w-10"><input type="checkbox" id="selectAllCuentas" class="cuenta-checkbox" title="Seleccionar todas"></th>
                            <th class="px-3 py-2 text-left">Nombre / Usuario</th>
                            <th class="px-3 py-2 text-left">Empresas</th>
                            <th class="px-3 py-2 text-left">Rango</th>
                            <th class="px-3 py-2 text-right">Facturado</th>
                            <th class="px-3 py-2 text-center">Estado</th>
                            <th class="px-3 py-2 text-center">Fecha</th>
                            <th class="px-3 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyCuentasBD" class="divide-y divide-slate-100 bg-white">
                        <tr><td colspan="8" class="px-3 py-8 text-center text-slate-500"><div class="animate-pulse">Cargando cuentas...</div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="px-5 py-4 border-t border-slate-200 flex justify-between items-center">
            <div class="text-sm text-slate-600"><span id="totalCuentasInfo">0 cuentas</span></div>
            <button id="btnAddDesdeFiltro" class="rounded-lg border border-amber-300 px-4 py-2 text-sm bg-amber-50 hover:bg-amber-100">⭐ Guardar rango actual</button>
        </div>
    </div>
</div>

<script>
// ===== CONSTANTES Y VARIABLES GLOBALES =====
const EMPRESAS_SELECCIONADAS = <?= json_encode($empresasSeleccionadas) ?>;
const COMPANY_SCOPE = EMPRESAS_SELECCIONADAS.length > 0 ? EMPRESAS_SELECCIONADAS.join('_') : '__todas__';
const ACC_KEY = 'cuentas_temp:'+COMPANY_SCOPE;
const SS_KEY = 'seg_social_temp:'+COMPANY_SCOPE;
const PREST_SEL_KEY = 'prestamo_sel_multi:v4:'+COMPANY_SCOPE;
const ESTADO_PAGO_KEY = 'estado_pago_temp:'+COMPANY_SCOPE;
const MANUAL_ROWS_KEY = 'filas_manuales_temp:'+COMPANY_SCOPE;
const SELECTED_CONDUCTORS_KEY = 'conductores_seleccionados_temp:'+COMPANY_SCOPE;
const PRESTAMOS_LIST = <?php echo json_encode($prestamosList, JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK); ?>;
const VIAJES_IDS_POR_CONDUCTOR = <?= $viajesIdsJSON ?>;

// NUEVO: ID de la cuenta actualmente cargada
let cuentaCargadaId = null;
let cuentaCargadaNombre = '';

let modoHistoricoActivo = false;

function toInt(s) {
    if (typeof s === 'number') return Math.round(s);
    s = (s || '').toString().replace(/\./g, '').replace(/,/g, '').replace(/[^\d-]/g, '');
    return parseInt(s || '0', 10) || 0;
}

function fmt(n) {
    return '$' + (n || 0).toLocaleString('es-CO');
}

function getLS(k) {
    try { return JSON.parse(localStorage.getItem(k) || '{}'); } catch { return {}; }
}

function setLS(k, v) {
    localStorage.setItem(k, JSON.stringify(v));
}

function normalizarTexto(texto) {
    return texto.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
}

let accMap = getLS(ACC_KEY);
let ssMap = getLS(SS_KEY);
let prestSel = getLS(PREST_SEL_KEY) || {};
let estadoPagoMap = getLS(ESTADO_PAGO_KEY) || {};
let manualRows = JSON.parse(localStorage.getItem(MANUAL_ROWS_KEY) || '[]');
let selectedConductors = JSON.parse(localStorage.getItem(SELECTED_CONDUCTORS_KEY) || '[]');
let comprobantesMap = {};

// ===== FUNCIONES DE CUENTA CARGADA =====
function mostrarInfoCuentaCargada(id, nombre) {
    cuentaCargadaId = id;
    cuentaCargadaNombre = nombre;
    document.getElementById('cuentaCargadaInfo').classList.remove('hidden');
    document.getElementById('cuentaCargadaNombre').textContent = nombre;
    document.getElementById('cuentaCargadaId').textContent = id;
    document.getElementById('btnActualizarCuenta').classList.remove('hidden');
}

function ocultarInfoCuentaCargada() {
    cuentaCargadaId = null;
    cuentaCargadaNombre = '';
    document.getElementById('cuentaCargadaInfo').classList.add('hidden');
    document.getElementById('btnActualizarCuenta').classList.add('hidden');
}

async function actualizarCuentaCargada() {
    if (!cuentaCargadaId) {
        Swal.fire({
            title: '⚠️ Sin cuenta cargada',
            text: 'Primero debes cargar una cuenta desde el gestor de cuentas',
            icon: 'warning'
        });
        return;
    }
    
    const confirmacion = await Swal.fire({
        title: '🔄 ¿Actualizar cuenta?',
        html: `
            <p>Se actualizará la cuenta:</p>
            <p class="font-semibold text-violet-700 mt-2">"${cuentaCargadaNombre}" (ID: ${cuentaCargadaId})</p>
            <p class="text-sm text-slate-600 mt-2">Se sobrescribirán todos los datos con los valores actuales de la tabla.</p>
            <p class="text-xs text-slate-500 mt-1">✅ Préstamos, seguridad social, cuentas, estados, comprobantes y filas manuales</p>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '✅ Sí, actualizar',
        confirmButtonColor: '#8b5cf6',
        cancelButtonText: 'Cancelar'
    });
    
    if (!confirmacion.isConfirmed) return;
    
    try {
        Swal.fire({
            title: 'Actualizando cuenta...',
            text: 'Por favor espera',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });
        
        const empresas = <?= json_encode($empresasSeleccionadas) ?>;
        const desde = '<?= $desde ?>';
        const hasta = '<?= $hasta ?>';
        const facturado = toInt(document.getElementById('inp_facturado').value);
        const porcentaje = parseFloat(document.getElementById('inp_porcentaje_ajuste').value) || 0;
        
        const datosParaGuardar = {
            prestamos: prestSel,
            segSocial: ssMap,
            cuentasBancarias: accMap,
            estadosPago: estadoPagoMap,
            filasManuales: []
        };
        
        document.querySelectorAll('#tbody tr.fila-manual').forEach(tr => {
            const conductor = tr.querySelector('.conductor-select')?.value || '';
            const base = toInt(tr.querySelector('.base-manual')?.value || '0');
            const cuenta = tr.querySelector('.cta')?.value || '';
            const segSocial = toInt(tr.querySelector('.ss')?.value || '0');
            const estado = tr.querySelector('.estado-pago')?.value || '';
            
            if (conductor) {
                datosParaGuardar.filasManuales.push({ conductor, base, cuenta, segSocial, estado });
            }
        });
        
        const comprobantesParaGuardar = {};
        for (const [conductor, base64] of Object.entries(comprobantesMap)) {
            if (base64) {
                comprobantesParaGuardar[conductor] = base64;
            }
        }
        
        const formData = new FormData();
        formData.append('accion', 'actualizar_cuenta');
        formData.append('cuenta_id', cuentaCargadaId);
        formData.append('nombre', cuentaCargadaNombre);
        formData.append('desde', desde);
        formData.append('hasta', hasta);
        formData.append('facturado', facturado);
        formData.append('porcentaje_ajuste', porcentaje);
        formData.append('pagado', 0);
        formData.append('empresas', JSON.stringify(empresas));
        formData.append('datos_json', JSON.stringify(datosParaGuardar));
        formData.append('comprobantes_json', JSON.stringify(comprobantesParaGuardar));
        
        const response = await fetch('', { method: 'POST', body: formData });
        const resultado = await response.json();
        
        Swal.close();
        
        if (resultado.success) {
            Swal.fire({
                title: '✅ Cuenta actualizada',
                html: `<p>La cuenta <strong>"${cuentaCargadaNombre}"</strong> fue actualizada exitosamente</p>
                       <p class="text-sm text-slate-500 mt-1">${Object.keys(comprobantesParaGuardar).length} comprobantes guardados</p>`,
                icon: 'success',
                timer: 3000,
                showConfirmButton: true,
                confirmButtonText: 'Continuar'
            });
        } else {
            throw new Error(resultado.message);
        }
    } catch (error) {
        Swal.fire('❌ Error', error.message, 'error');
    }
}

// ... (resto del JavaScript se mantiene igual que en la versión anterior, 
// solo se agregan las nuevas funciones y eventos al final del DOMContentLoaded)

const tbody = document.getElementById('tbody');
const btnAddManual = document.getElementById('btnAddManual');
const floatingPanel = document.getElementById('floatingPanel');
const panelDragHandle = document.getElementById('panelDragHandle');
const closePanel = document.getElementById('closePanel');
const selectAllCheckbox = document.getElementById('selectAllCheckbox');
const buscadorConductores = document.getElementById('buscadorConductores');
const clearBuscar = document.getElementById('clearBuscar');
const contadorConductores = document.getElementById('contador-conductores');
const filtroEstado = document.getElementById('filtroEstado');
const btnPagarSeleccionados = document.getElementById('btnPagarSeleccionados');
const btnDespagarSeleccionados = document.getElementById('btnDespagarSeleccionados');
const viajesCountInfo = document.getElementById('viajesCountInfo');

// ... (todas las funciones existentes se mantienen igual: obtenerViajesIdsDeSeleccionados, 
// pagarViajes, despagarViajes, exportarAExcel, etc.)

// SOLO SE MUESTRAN LAS PARTES NUEVAS/MODIFICADAS:

document.addEventListener('DOMContentLoaded', function() {
    // Eventos de botones de cuenta
    document.getElementById('btnActualizarCuenta')?.addEventListener('click', actualizarCuentaCargada);
    document.getElementById('btnCerrarCuentaCargada')?.addEventListener('click', ocultarInfoCuentaCargada);
    
    // ... (resto de eventos existentes)
    
    // MODIFICAR cargarCuentaCompletaBD para guardar el ID
    async function cargarCuentaCompletaBD(id) {
        // ... (código existente igual)
        if (resultado.success) {
            const cuenta = resultado.cuenta;
            // NUEVO: Mostrar info de cuenta cargada
            mostrarInfoCuentaCargada(cuenta.id, cuenta.nombre);
            // ... (resto del código existente)
        }
    }
    
    // ... (resto del DOMContentLoaded)
});
</script>
</body>
</html>
<?php $conn->close(); ?>