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

/* ================= CREAR TABLA CUENTAS GUARDADAS ================= */
$conn->query("
CREATE TABLE IF NOT EXISTS cuentas_guardadas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(255) NOT NULL,
    empresas TEXT NOT NULL,
    desde DATE NOT NULL,
    hasta DATE NOT NULL,
    facturado DECIMAL(15,2) NOT NULL,
    porcentaje_ajuste DECIMAL(5,2) NOT NULL,
    pagado TINYINT(1) NOT NULL DEFAULT 0,
    datos_json LONGTEXT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    usuario VARCHAR(100),
    INDEX idx_empresas (empresas(100)),
    INDEX idx_fecha (fecha_creacion),
    INDEX idx_pagado (pagado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

/* ================= AJAX PARA GESTIÓN DE CUENTAS ================= */
if (isset($_GET['obtener_cuentas'])) {
    header('Content-Type: application/json');
    
    $empresa = $conn->real_escape_string($_GET['empresa'] ?? '');
    $incluir_pagado = isset($_GET['incluir_pagado']) ? intval($_GET['incluir_pagado']) : 1;
    
    $sql = "SELECT id, nombre, empresas, desde, hasta, facturado, porcentaje_ajuste, pagado, 
                   datos_json, fecha_creacion, usuario 
            FROM cuentas_guardadas";
    
    if (!empty($empresa)) {
        $sql .= " WHERE empresas LIKE '%$empresa%'";
    }
    
    $sql .= " ORDER BY fecha_creacion DESC";
    
    $result = $conn->query($sql);
    $cuentas = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $datos_json = json_decode($row['datos_json'], true);
            $row['datos_json'] = ($datos_json === null) ? [] : $datos_json;
            $cuentas[] = $row;
        }
    }
    
    echo json_encode($cuentas, JSON_UNESCAPED_UNICODE);
    exit;
}

// AJAX para guardar cuenta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_cuenta') {
    header('Content-Type: application/json');
    
    $nombre = $conn->real_escape_string($_POST['nombre'] ?? '');
    $empresas = $conn->real_escape_string($_POST['empresas'] ?? '');
    $desde = $conn->real_escape_string($_POST['desde'] ?? '');
    $hasta = $conn->real_escape_string($_POST['hasta'] ?? '');
    $facturado = floatval($_POST['facturado'] ?? 0);
    $porcentaje_ajuste = floatval($_POST['porcentaje_ajuste'] ?? 0);
    $pagado = isset($_POST['pagado']) ? intval($_POST['pagado']) : 0;
    $datos_json = $conn->real_escape_string($_POST['datos_json'] ?? '{}');
    $usuario = $conn->real_escape_string($_SESSION['usuario'] ?? 'Sistema');
    
    if (empty($nombre) || empty($empresas)) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios']);
        exit;
    }
    
    $sql = "INSERT INTO cuentas_guardadas (nombre, empresas, desde, hasta, facturado, porcentaje_ajuste, pagado, datos_json, usuario) 
            VALUES ('$nombre', '$empresas', '$desde', '$hasta', $facturado, $porcentaje_ajuste, $pagado, '$datos_json', '$usuario')";
    
    $resultado = $conn->query($sql);
    
    if ($resultado) {
        echo json_encode(['success' => true, 'id' => $conn->insert_id, 'message' => 'Cuenta guardada exitosamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $conn->error]);
    }
    exit;
}

// AJAX para actualizar estado de pagado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'toggle_pagado') {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    $pagado = intval($_POST['pagado']);
    
    $sql = "UPDATE cuentas_guardadas SET pagado = $pagado WHERE id = $id";
    $resultado = $conn->query($sql);
    
    echo json_encode(['success' => $resultado, 'message' => $resultado ? 'Estado actualizado' : 'Error al actualizar']);
    exit;
}

// AJAX para eliminar cuenta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar_cuenta') {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    
    $sql = "DELETE FROM cuentas_guardadas WHERE id = $id";
    $resultado = $conn->query($sql);
    
    echo json_encode(['success' => $resultado, 'message' => $resultado ? 'Cuenta eliminada' : 'Error al eliminar']);
    exit;
}

// AJAX para cargar cuenta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cargar_cuenta') {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    $sql = "SELECT * FROM cuentas_guardadas WHERE id = $id";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $datos_json = json_decode($row['datos_json'], true);
        $row['datos_json'] = ($datos_json === null) ? [] : $datos_json;
        echo json_encode(['success' => true, 'cuenta' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Cuenta no encontrada']);
    }
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

/* ================= OBTENER CLASIFICACIONES ================= */
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

/* ================= Cargar clasificaciones ================= */
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

    $sql = "SELECT fecha, ruta, empresa, tipo_vehiculo
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

            // Determinar color según categoría
            $color_class = '';
            switch($cat) {
                case 'completo': $color_class = 'bg-emerald-100 text-emerald-800 border-emerald-300'; break;
                case 'medio': $color_class = 'bg-amber-100 text-amber-800 border-amber-300'; break;
                case 'extra': $color_class = 'bg-slate-200 text-slate-800 border-slate-300'; break;
                case 'siapana': $color_class = 'bg-fuchsia-100 text-fuchsia-700 border-fuchsia-200'; break;
                case 'carrotanque': $color_class = 'bg-cyan-100 text-cyan-800 border-cyan-200'; break;
                default: $color_class = 'bg-gray-100 text-gray-700 border-gray-200';
            }

            $rowsHTML .= "<tr class='viaje-item cat-$cat'>
                    <td class='px-3 py-2'>".htmlspecialchars($r['fecha'])."</td>
                    <td class='px-3 py-2'>
                        <span class='inline-block px-2 py-1 rounded text-xs font-medium border $color_class'>
                            ".htmlspecialchars($ruta)."
                        </span>
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
        $rowsHTML .= "<tr><td colspan='4' class='px-3 py-4 text-center text-slate-500'>Sin viajes en el rango/empresas seleccionadas.</td></tr>";
    }

    ?>
    <div class='space-y-3'>
        <!-- Leyenda con contadores y filtro -->
        <div class='flex flex-wrap gap-2 text-xs' id="legendFilterBar">
            <?php
            // Mostrar todas las clasificaciones dinámicamente
            $colores_base = [
                'completo'         => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
                'medio'            => 'bg-amber-100 text-amber-800 border border-amber-200',
                'extra'            => 'bg-slate-200 text-slate-800 border border-slate-300',
                'siapana'          => 'bg-fuchsia-100 text-fuchsia-700 border border-fuchsia-200',
                'carrotanque'      => 'bg-cyan-100 text-cyan-800 border border-cyan-200',
            ];
            
            // Generar leyenda dinámica
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

        <!-- Tabla -->
        <div class='overflow-x-auto'>
            <table class='min-w-full text-sm text-left'>
                <thead class='bg-blue-600 text-white'>
                <tr>
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

/* ================= CARGAR TARIFAS DE TODAS LAS EMPRESAS SELECCIONADAS ================= */
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

/* ================= Viajes del rango (TODAS las empresas seleccionadas) ================= */
$sqlV = "SELECT nombre, ruta, empresa, tipo_vehiculo
         FROM viajes
         WHERE fecha BETWEEN '$desde' AND '$hasta'";
if (!empty($empresasSeleccionadasEsc)) {
    $empresasStr = "'" . implode("','", $empresasSeleccionadasEsc) . "'";
    $sqlV .= " AND empresa IN ($empresasStr)";
}
$resV = $conn->query($sqlV);

$viajesPorConductor = [];
$contadores = [];

if ($resV) {
    while ($row = $resV->fetch_assoc()) {
        $nombre = $row['nombre'];
        $empresa = $row['empresa'];
        $ruta = $row['ruta'];
        $vehiculo = $row['tipo_vehiculo'];
        
        if (!isset($viajesPorConductor[$nombre])) {
            $viajesPorConductor[$nombre] = [];
        }
        $viajesPorConductor[$nombre][] = [
            'empresa' => $empresa,
            'ruta' => $ruta,
            'vehiculo' => $vehiculo
        ];
        
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

/* ================= Préstamos ================= */
$prestamosList = [];
$i = 0;

$qPrest = "
  SELECT deudor,
         SUM(
           monto + 
           monto * 
           CASE 
             WHEN fecha >= '2025-10-29' THEN 0.13
             ELSE 0.10
           END *
           CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END
         ) AS total
  FROM prestamos
  WHERE (pagado IS NULL OR pagado = 0)
";

if (!empty($empresasSeleccionadasEsc)) {
    $empresasStr = "'" . implode("','", $empresasSeleccionadasEsc) . "'";
    $qPrest .= " AND empresa IN ($empresasStr)";
}

$qPrest .= " GROUP BY deudor";

if ($rP = $conn->query($qPrest)) {
    while($r = $rP->fetch_assoc()){
        $name = $r['deudor'];
        $key  = norm_person($name);
        $total = (int)round($r['total']);
        $prestamosList[] = ['id'=>$i++, 'name'=>$name, 'key'=>$key, 'total'=>$total];
    }
}

/* ================= Filas base (una por conductor, sumando todas las empresas) ================= */
$filas = []; 
$total_facturado = 0;

foreach ($contadores as $nombre => $v) {
    $total = 0;
    
    // Obtener todos los viajes de este conductor
    $viajesConductor = $viajesPorConductor[$nombre] ?? [];
    
    // Para cada clasificación, calcular el total usando las tarifas correctas por empresa
    foreach ($viajesConductor as $viaje) {
        $empresa = $viaje['empresa'];
        $ruta = $viaje['ruta'];
        $vehiculo = $viaje['vehiculo'];
        
        // Obtener clasificación para este viaje
        $key = mb_strtolower(trim($ruta . '|' . $vehiculo), 'UTF-8');
        $clasif = isset($clasificaciones[$key]) ? strtolower($clasificaciones[$key]) : 'otro';
        
        // Buscar tarifa para esta empresa y tipo de vehículo
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
    $total_facturado += (int)$total;
}

usort($filas, fn($a,$b)=> $b['total_bruto'] <=> $a['total_bruto']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Ajuste de Pago (Múltiples empresas con checkboxes)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .num { font-variant-numeric: tabular-nums; }
        .table-sticky thead tr { position: sticky; top: 0; z-index: 30; }
        .table-sticky thead th { position: sticky; top: 0; z-index: 31; background-color: #2563eb !important; color: #fff !important; }
        .table-sticky thead { box-shadow: 0 2px 0 rgba(0,0,0,0.06); }

        /* Modal Viajes */
        .viajes-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,.45); display:none; align-items:center; justify-content:center; z-index:10000; }
        .viajes-backdrop.show{ display:flex; }
        .viajes-card{ width:min(720px,94vw); max-height:90vh; overflow:hidden; border-radius:16px; background:#fff;
            box-shadow:0 20px 60px rgba(0,0,0,.25); border:1px solid #e5e7eb; }
        .viajes-header{padding:14px 16px;border-bottom:1px solid #eef2f7}
        .viajes-body{padding:14px 16px;overflow:auto; max-height:70vh}
        .viajes-close{padding:6px 10px; border-radius:10px; cursor:pointer;}
        .viajes-close:hover{background:#f3f4f6}

        .conductor-link{cursor:pointer; color:#0d6efd; text-decoration:underline;}

        /* Estados de pago */
        .estado-pagado { background-color: #f0fdf4 !important; border-left: 4px solid #22c55e; }
        .estado-pendiente { background-color: #fef2f2 !important; border-left: 4px solid #ef4444; }
        .estado-procesando { background-color: #fffbeb !important; border-left: 4px solid #f59e0b; }
        .estado-parcial { background-color: #eff6ff !important; border-left: 4px solid #3b82f6; }

        /* Fila manual */
        .fila-manual { background-color: #f0f9ff !important; border-left: 4px solid #0ea5e9; }
        .fila-manual td { background-color: #f0f9ff !important; }
        
        /* Buscador */
        .buscar-container { position: relative; }
        .buscar-clear { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #64748b; cursor: pointer; display: none; }
        .buscar-clear:hover { color: #475569; }
        
        /* Panel flotante */
        #floatingPanel { box-shadow: 0 10px 40px rgba(0,0,0,0.15); z-index: 9999; }
        #panelDragHandle { user-select: none; }
        .checkbox-conductor { width: 18px; height: 18px; cursor: pointer; }
        .fila-seleccionada { background-color: #f0f9ff !important; }
        
        /* Leyenda */
        .legend-pill { transition: all 0.2s; }
        .legend-pill.active { box-shadow: 0 0 0 2px #3b82f6, 0 0 0 4px rgba(59, 130, 246, 0.2); }
        
        /* Badge base datos */
        .bd-badge { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        
        /* Colores para viajes */
        .row-viaje:hover { background-color: #f8fafc; }
        .cat-completo { background-color: rgba(209, 250, 229, 0.1); }
        .cat-medio { background-color: rgba(254, 243, 199, 0.1); }
        .cat-extra { background-color: rgba(241, 245, 249, 0.1); }
        .cat-siapana { background-color: rgba(250, 232, 255, 0.1); }
        .cat-carrotanque { background-color: rgba(207, 250, 254, 0.1); }
        .cat-otro { background-color: rgba(243, 244, 246, 0.1); }

        /* Switch de pagado */
        .switch-pagado {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        .switch-pagado input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .switch-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ef4444;
            transition: .3s;
            border-radius: 34px;
        }
        .switch-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        input:checked + .switch-slider {
            background-color: #22c55e;
        }
        input:checked + .switch-slider:before {
            transform: translateX(26px);
        }
        .switch-label {
            margin-left: 8px;
            font-size: 11px;
            font-weight: 500;
        }
        .pagado-verde { color: #22c55e; }
        .pagado-rojo { color: #ef4444; }

        /* Checkboxes de empresas */
        .empresas-container {
            max-height: 150px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.75rem;
            background: white;
        }
        .empresa-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0;
        }
        .empresa-checkbox input[type="checkbox"] {
            width: 1.2rem;
            height: 1.2rem;
            border-radius: 0.25rem;
            border: 2px solid #cbd5e1;
            cursor: pointer;
        }
        .empresa-checkbox input[type="checkbox"]:checked {
            background-color: #3b82f6;
            border-color: #3b82f6;
        }
        .empresa-checkbox label {
            cursor: pointer;
            font-size: 0.9rem;
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">
<header class="max-w-[1600px] mx-auto px-3 md:px-4 pt-6">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-5 py-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <h2 class="text-xl md:text-2xl font-bold">🧾 Ajuste de Pago <span class="bd-badge text-xs px-2 py-1 rounded-full ml-2">Múltiples Empresas</span></h2>
            <div class="flex items-center gap-2">
                <button id="btnShowSaveCuenta" class="rounded-lg border border-amber-300 px-3 py-2 text-sm bg-amber-50 hover:bg-amber-100">⭐ Guardar como cuenta</button>
                <button id="btnShowGestorCuentas" class="rounded-lg border border-blue-300 px-3 py-2 text-sm bg-blue-50 hover:bg-blue-100">📚 Cuentas guardadas</button>
            </div>
        </div>

        <!-- filtros con checkboxes -->
        <form id="formFiltros" class="mt-3 grid grid-cols-1 md:grid-cols-12 gap-3" method="get">
            <label class="block md:col-span-2">
                <span class="block text-xs font-medium mb-1">Desde</span>
                <input id="inp_desde" type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" required class="w-full rounded-xl border border-slate-300 px-3 py-2">
            </label>
            <label class="block md:col-span-2">
                <span class="block text-xs font-medium mb-1">Hasta</span>
                <input id="inp_hasta" type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" required class="w-full rounded-xl border border-slate-300 px-3 py-2">
            </label>
            <div class="md:col-span-6">
                <span class="block text-xs font-medium mb-1">Empresas (selecciona las que quieras)</span>
                <div class="empresas-container">
                    <?php
                    $resEmp2 = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
                    if ($resEmp2) {
                        while ($e = $resEmp2->fetch_assoc()) {
                            $checked = in_array($e['empresa'], $empresasSeleccionadas) ? 'checked' : '';
                            echo "<div class='empresa-checkbox'>";
                            echo "<input type='checkbox' name='empresas[]' value='" . htmlspecialchars($e['empresa']) . "' id='emp_" . md5($e['empresa']) . "' $checked>";
                            echo "<label for='emp_" . md5($e['empresa']) . "'>" . htmlspecialchars($e['empresa']) . "</label>";
                            echo "</div>";
                        }
                    }
                    ?>
                </div>
                <div class="flex gap-2 mt-2">
                    <button type="button" id="btnSeleccionarTodas" class="text-xs px-2 py-1 bg-blue-50 text-blue-700 rounded border border-blue-200">✓ Seleccionar todas</button>
                    <button type="button" id="btnLimpiarTodas" class="text-xs px-2 py-1 bg-slate-50 text-slate-700 rounded border border-slate-200">✗ Limpiar</button>
                </div>
            </div>
            <div class="md:col-span-2 flex md:items-end">
                <button class="w-full rounded-xl bg-blue-600 text-white py-2.5 font-semibold shadow">Aplicar filtros</button>
            </div>
        </form>
    </div>
</header>

<main class="max-w-[1600px] mx-auto px-3 md:px-4 py-6 space-y-5">
    <!-- Panel montos -->
    <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
        <div class="grid grid-cols-1 md:grid-cols-7 gap-4">
            <div>
                <div class="text-xs text-slate-500 mb-1">Conductores</div>
                <div class="text-lg font-semibold"><?= count($filas) ?></div>
            </div>
            <label class="block md:col-span-2">
                <span class="block text-xs font-medium mb-1">Cuenta de cobro (facturado)</span>
                <input id="inp_facturado" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right num"
                       value="<?= number_format($total_facturado,0,',','.') ?>">
            </label>
            <label class="block md:col-span-2">
                <span class="block text-xs font-medium mb-1">Viajes manuales agregados</span>
                <input id="inp_viajes_manuales" type="text" class="w-full rounded-xl border border-green-200 px-3 py-2 text-right num bg-green-50" value="0" readonly>
            </label>
            <label class="block">
                <span class="block text-xs font-medium mb-1">Porcentaje de ajuste (%)</span>
                <input id="inp_porcentaje_ajuste" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right num"
                       value="5" placeholder="Ej: 5">
            </label>
            <div>
                <div class="text-xs text-slate-500 mb-1">Total ajuste</div>
                <div id="lbl_total_ajuste" class="text-lg font-semibold text-amber-600 num">0</div>
            </div>
        </div>
        <div class="mt-2 text-xs text-slate-500">
            <span class="font-semibold">Empresas seleccionadas:</span> 
            <?= !empty($empresasSeleccionadas) ? implode(' • ', $empresasSeleccionadas) : 'Todas las empresas' ?>
        </div>
    </section>

    <!-- Tabla principal -->
    <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
            <div>
                <h3 class="text-lg font-semibold">Conductores</h3>
                <div id="contador-conductores" class="text-xs text-slate-500 mt-1">
                    Mostrando <?= count($filas) ?> de <?= count($filas) ?> conductores
                </div>
            </div>
            <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
                <!-- BUSCADOR DE CONDUCTORES -->
                <div class="buscar-container w-full md:w-64">
                    <input id="buscadorConductores" type="text" 
                           placeholder="Buscar conductor..." 
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 pl-3 pr-10">
                    <button id="clearBuscar" class="buscar-clear">✕</button>
                </div>
                <button id="btnAddManual" type="button" class="rounded-lg bg-green-600 text-white px-4 py-2 text-sm hover:bg-green-700 whitespace-nowrap">
                    ➕ Agregar conductor manual
                </button>
            </div>
        </div>

        <div class="overflow-auto max-h-[70vh] rounded-xl border border-slate-200 table-sticky">
            <table class="min-w-[1200px] w-full text-sm">
                <thead class="bg-blue-600 text-white">
                <tr>
                    <th class="px-3 py-2 text-left">Conductor</th>
                    <th class="px-3 py-2 text-right">Total viajes (base)</th>
                    <th class="px-3 py-2 text-right">Ajuste por diferencia</th>
                    <th class="px-3 py-2 text-right">Valor que llegó</th>
                    <th class="px-3 py-2 text-right">Retención 3.5%</th>
                    <th class="px-3 py-2 text-right">4×1000</th>
                    <th class="px-3 py-2 text-right">Aporte 10%</th>
                    <th class="px-3 py-2 text-right">Seg. social</th>
                    <th class="px-3 py-2 text-right">Préstamos (pend.)</th>
                    <th class="px-3 py-2 text-left">N° Cuenta</th>
                    <th class="px-3 py-2 text-right">A pagar</th>
                    <th class="px-3 py-2 text-center">Estado</th>
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
                ?>
                    <tr data-conductor="<?= $nombre_normalizado ?>" data-total-base="<?= $f['total_bruto'] ?>" data-row-index="<?= $contador_filas ?>">
                        <td class="px-3 py-2">
                            <button type="button" class="conductor-link" data-nombre="<?= htmlspecialchars($f['nombre']) ?>" title="Ver viajes"><?= htmlspecialchars($f['nombre']) ?></button>
                        </td>
                        <td class="px-3 py-2 text-right num base"><?= number_format($f['total_bruto'],0,',','.') ?></td>
                        <td class="px-3 py-2 text-right num ajuste">0</td>
                        <td class="px-3 py-2 text-right num llego">0</td>
                        <td class="px-3 py-2 text-right num ret">0</td>
                        <td class="px-3 py-2 text-right num mil4">0</td>
                        <td class="px-3 py-2 text-right num apor">0</td>
                        <td class="px-3 py-2 text-right">
                            <input type="text" class="ss w-full max-w-[120px] rounded-lg border border-slate-300 px-2 py-1 text-right num" value="">
                        </td>
                        <td class="px-3 py-2 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <span class="num prest">0</span>
                                <button type="button" class="btn-prest text-xs px-2 py-1 rounded border border-slate-300 bg-slate-50 hover:bg-slate-100" data-nombre="<?= htmlspecialchars($f['nombre']) ?>">
                                    Seleccionar
                                </button>
                            </div>
                            <div class="text-[11px] text-slate-500 text-right selected-deudor"></div>
                        </td>
                        <td class="px-3 py-2">
                            <input type="text" class="cta w-full max-w-[180px] rounded-lg border border-slate-300 px-2 py-1" value="" placeholder="N° cuenta">
                        </td>
                        <td class="px-3 py-2 text-right num pagar">0</td>
                        <td class="px-3 py-2 text-center">
                            <select class="estado-pago w-full max-w-[140px] rounded-lg border border-slate-300 px-2 py-1 text-sm">
                                <option value="">Sin estado</option>
                                <option value="pagado">✅ Pagado</option>
                                <option value="pendiente">❌ Pendiente</option>
                                <option value="procesando">🔄 Procesando</option>
                                <option value="parcial">⚠️ Parcial</option>
                            </select>
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
                    <td class="px-3 py-2 text-right num" id="tot_valor_llego">0</td>
                    <td class="px-3 py-2 text-right num" id="tot_retencion">0</td>
                    <td class="px-3 py-2 text-right num" id="tot_4x1000">0</td>
                    <td class="px-3 py-2 text-right num" id="tot_aporte">0</td>
                    <td class="px-3 py-2 text-right num" id="tot_ss">0</td>
                    <td class="px-3 py-2 text-right num" id="tot_prestamos">0</td>
                    <td class="px-3 py-2"></td>
                    <td class="px-3 py-2 text-right num" id="tot_pagar">0</td>
                    <td class="px-3 py-2" colspan="2"></td>
                </tr>
                </tfoot>
            </table>
        </div>
    </section>
</main>

<!-- ===== PANEL FLOTANTE DE SELECCIÓN ===== -->
<div id="floatingPanel" class="hidden fixed z-50 bg-white border border-blue-300 rounded-xl shadow-lg" style="top: 100px; left: 100px; min-width: 300px;">
    <div id="panelDragHandle" class="cursor-move bg-blue-600 text-white px-4 py-3 rounded-t-xl flex items-center justify-between">
        <div class="font-semibold flex items-center gap-2">
            <span>📊 Sumatoria Seleccionados</span>
            <span id="selectedCount" class="bg-blue-500 text-white text-xs px-2 py-1 rounded-full">0</span>
        </div>
        <button id="closePanel" class="text-white hover:bg-blue-700 p-1 rounded">✕</button>
    </div>
    
    <div class="p-4">
        <div class="space-y-3">
            <div class="flex justify-between items-center border-b pb-2">
                <span class="text-sm text-slate-600">Conductores seleccionados:</span>
                <span id="panelConductoresCount" class="font-semibold">0</span>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-slate-50 p-3 rounded-lg">
                    <div class="text-xs text-slate-500 mb-1">Total a pagar</div>
                    <div id="panelTotalPagar" class="text-xl font-bold text-emerald-600 num">0</div>
                </div>
                <div class="bg-slate-50 p-3 rounded-lg">
                    <div class="text-xs text-slate-500 mb-1">Promedio por conductor</div>
                    <div id="panelPromedio" class="text-lg font-semibold text-blue-600 num">0</div>
                </div>
            </div>
            
            <div class="text-xs text-slate-500 mt-2">
                <div class="flex justify-between mb-1">
                    <span>Valor que llega:</span>
                    <span id="panelTotalLlego" class="num font-semibold">0</span>
                </div>
                <div class="flex justify-between mb-1">
                    <span>Retención 3.5%:</span>
                    <span id="panelTotalRetencion" class="num">0</span>
                </div>
                <div class="flex justify-between mb-1">
                    <span>4×1000:</span>
                    <span id="panelTotal4x1000" class="num">0</span>
                </div>
                <div class="flex justify-between mb-1">
                    <span>Aporte 10%:</span>
                    <span id="panelTotalAporte" class="num">0</span>
                </div>
                <div class="flex justify-between mb-1">
                    <span>Seg. social:</span>
                    <span id="panelTotalSS" class="num">0</span>
                </div>
                <div class="flex justify-between">
                    <span>Préstamos:</span>
                    <span id="panelTotalPrestamos" class="num">0</span>
                </div>
            </div>
            
            <div class="mt-3 pt-3 border-t">
                <div class="text-xs text-slate-500 mb-2">Conductores:</div>
                <div id="panelNombresConductores" class="text-xs max-h-[100px] overflow-y-auto">
                    <div class="text-slate-400 italic">Ningún conductor seleccionado</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== Modal PRÉSTAMOS ===== -->
<div id="prestModal" class="hidden fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/30"></div>
    <div class="relative mx-auto my-8 max-w-2xl bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold">Seleccionar deudores (puedes marcar varios)</h3>
            <button id="btnCloseModal" class="p-2 rounded hover:bg-slate-100" title="Cerrar">✕</button>
        </div>
        <div class="p-4">
            <div class="flex flex-col md:flex-row md:items-center gap-3 mb-3">
                <input id="prestSearch" type="text" placeholder="Buscar deudor..." class="w-full rounded-xl border border-slate-300 px-3 py-2">
                <div class="flex gap-2">
                    <button id="btnSelectAll" class="rounded-lg border border-slate-300 px-3 py-2 text-sm bg-slate-50 hover:bg-slate-100">Marcar visibles</button>
                    <button id="btnUnselectAll" class="rounded-lg border border-slate-300 px-3 py-2 text-sm bg-slate-50 hover:bg-slate-100">Desmarcar</button>
                    <button id="btnClearSel" class="rounded-lg border border-rose-300 text-rose-700 px-3 py-2 text-sm bg-rose-50 hover:bg-rose-100">Quitar selección</button>
                </div>
            </div>
            <div id="prestList" class="max-h-[50vh] overflow-auto rounded-xl border border-slate-200"></div>
        </div>
        <div class="px-5 py-4 border-t border-slate-200 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div class="text-sm text-slate-600">
                Seleccionados: <span id="selCount" class="font-semibold">0</span><br>
                <span class="text-xs">Total seleccionado: <span id="selTotal" class="num font-semibold">0</span></span>
            </div>

            <div class="flex items-center gap-2">
                <label class="text-sm flex items-center gap-1">
                    <span>Valor a aplicar:</span>
                    <input id="selTotalManual" type="text"
                           class="w-32 rounded-lg border border-slate-300 px-2 py-1 text-right num"
                           value="0">
                </label>
                <button id="btnCancel" class="rounded-lg border border-slate-300 px-4 py-2 bg-white hover:bg-slate-50">Cancelar</button>
                <button id="btnAssign" class="rounded-lg border border-blue-600 px-4 py-2 bg-blue-600 text-white hover:bg-blue-700">Asignar</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== Modal VIAJES ===== -->
<div id="viajesModal" class="viajes-backdrop">
    <div class="viajes-card">
        <div class="viajes-header">
            <div class="flex flex-col gap-2 w-full md:flex-row md:items-center md:justify-between">
                <div class="flex flex-col gap-1">
                    <h3 class="text-lg font-semibold flex items-center gap-2">
                        🧳 Viajes — <span id="viajesTitle" class="font-normal"></span>
                    </h3>
                    <div class="text-[11px] text-slate-500 leading-tight">
                        <span id="viajesRango"></span>
                        <span class="mx-1">•</span>
                        <span id="viajesEmpresa"></span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-xs text-slate-600 whitespace-nowrap">Conductor:</label>
                    <select id="viajesSelectConductor"
                            class="rounded-lg border border-slate-300 px-2 py-1 text-sm min-w-[200px] focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500">
                    </select>
                    <button class="viajes-close text-slate-600 hover:bg-slate-100 border border-slate-300 px-2 py-1 rounded-lg text-sm" id="viajesCloseBtn" title="Cerrar">
                        ✕
                    </button>
                </div>
            </div>
        </div>

        <div class="viajes-body" id="viajesContent"></div>
    </div>
</div>

<!-- ===== Modal GUARDAR CUENTA ===== -->
<div id="saveCuentaModal" class="hidden fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/30"></div>
    <div class="relative mx-auto my-10 w-full max-w-lg bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold">⭐ Guardar cuenta de cobro</h3>
            <button id="btnCloseSaveCuenta" class="p-2 rounded hover:bg-slate-100" title="Cerrar">✕</button>
        </div>
        <div class="p-5 space-y-3">
            <label class="block">
                <span class="block text-xs font-medium mb-1">Nombre</span>
                <input id="cuenta_nombre" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2" placeholder="Ej: Hospital Sep 2025">
            </label>
            <label class="block">
                <span class="block text-xs font-medium mb-1">Empresas</span>
                <input id="cuenta_empresas" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 bg-slate-50" readonly>
            </label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <label class="block">
                    <span class="block text-xs font-medium mb-1">Rango</span>
                    <input id="cuenta_rango" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2" readonly>
                </label>
                <label class="block">
                    <span class="block text-xs font-medium mb-1">Facturado</span>
                    <input id="cuenta_facturado" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right num">
                </label>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <label class="block">
                    <span class="block text-xs font-medium mb-1">Porcentaje ajuste</span>
                    <input id="cuenta_porcentaje" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right num">
                </label>
            </div>
            
            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-200">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium">Estado de pago:</span>
                    <span id="pagadoLabel" class="text-xs px-2 py-1 rounded-full font-semibold bg-red-100 text-red-700">NO PAGADO</span>
                </div>
                <label class="switch-pagado">
                    <input type="checkbox" id="cuenta_pagado">
                    <span class="switch-slider"></span>
                </label>
            </div>
        </div>
        <div class="px-5 py-4 border-t border-slate-200 flex items-center justify-end gap-2">
            <button id="btnCancelSaveCuenta" class="rounded-lg border border-slate-300 px-4 py-2 bg-white hover:bg-slate-50">Cancelar</button>
            <button id="btnDoSaveCuenta" class="rounded-lg border border-amber-500 text-white px-4 py-2 bg-amber-500 hover:bg-amber-600">Guardar en BD</button>
        </div>
    </div>
</div>

<!-- ===== Modal GESTOR DE CUENTAS ===== -->
<div id="gestorCuentasModal" class="hidden fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/30"></div>
    <div class="relative mx-auto my-10 w-full max-w-5xl bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold">📚 Cuentas guardadas <span class="bd-badge text-xs px-2 py-1 rounded-full ml-2">Base de Datos</span></h3>
            <button id="btnCloseGestor" class="p-2 rounded hover:bg-slate-100" title="Cerrar">✕</button>
        </div>
        <div class="p-4 space-y-3">
            <div class="flex flex-col md:flex-row md:items-center gap-3">
                <div class="text-sm">Filtrar por empresa:</div>
                <select id="filtroEmpresaCuentas" class="rounded-xl border border-slate-300 px-3 py-2 min-w-[200px]">
                    <option value="">-- Todas las empresas --</option>
                    <?php
                    $resEmpCuentas = $conn->query("SELECT DISTINCT empresas FROM cuentas_guardadas WHERE empresas IS NOT NULL AND empresas<>''");
                    $empresasUnicas = [];
                    if ($resEmpCuentas) {
                        while ($e = $resEmpCuentas->fetch_assoc()) {
                            $empresasArray = explode(' • ', $e['empresas']);
                            foreach ($empresasArray as $emp) {
                                $emp = trim($emp);
                                if (!empty($emp) && !in_array($emp, $empresasUnicas)) {
                                    $empresasUnicas[] = $emp;
                                }
                            }
                        }
                        sort($empresasUnicas);
                        foreach ($empresasUnicas as $emp) {
                            echo "<option value=\"" . htmlspecialchars($emp) . "\">" . htmlspecialchars($emp) . "</option>";
                        }
                    }
                    ?>
                </select>
                
                <select id="filtroEstadoPagado" class="rounded-xl border border-slate-300 px-3 py-2 min-w-[150px]">
                    <option value="">Todos los estados</option>
                    <option value="0">🔴 No pagadas</option>
                    <option value="1">🟢 Pagadas</option>
                </select>
                
                <div class="flex-1"></div>
                <div class="buscar-container w-full md:w-64">
                    <input id="buscaCuentaBD" type="text" placeholder="Buscar por nombre..." 
                           class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    <button id="clearBuscarBD" class="buscar-clear">✕</button>
                </div>
                <button id="btnRecargarCuentas" class="rounded-lg border border-blue-300 px-3 py-2 bg-blue-50 hover:bg-blue-100">
                    🔄 Recargar
                </button>
            </div>
            
            <div class="text-xs text-slate-500 mt-1" id="contador-cuentas">
                Cargando cuentas desde Base de Datos...
            </div>
            
            <div class="overflow-auto max-h-[60vh] rounded-xl border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-blue-600 text-white">
                    <tr>
                        <th class="px-3 py-2 text-left">Nombre</th>
                        <th class="px-3 py-2 text-left">Empresas</th>
                        <th class="px-3 py-2 text-left">Rango</th>
                        <th class="px-3 py-2 text-right">Facturado</th>
                        <th class="px-3 py-2 text-center">Fecha</th>
                        <th class="px-3 py-2 text-center">Estado</th>
                        <th class="px-3 py-2 text-center" colspan="2">Acciones</th>
                    </tr>
                    </thead>
                    <tbody id="tbodyCuentasBD" class="divide-y divide-slate-100 bg-white">
                        <tr>
                            <td colspan="7" class="px-3 py-8 text-center text-slate-500">
                                <div class="animate-pulse">Cargando cuentas desde Base de Datos...</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="px-5 py-4 border-t border-slate-200 flex justify-between items-center">
            <button id="btnFiltrarPagadas" class="rounded-lg border border-green-300 px-3 py-2 text-sm bg-green-50 hover:bg-green-100">
                ✅ Ver solo pagadas
            </button>
            <button id="btnFiltrarNoPagadas" class="rounded-lg border border-red-300 px-3 py-2 text-sm bg-red-50 hover:bg-red-100">
                ❌ Ver solo no pagadas
            </button>
            <button id="btnAddDesdeFiltro" class="rounded-lg border border-amber-300 px-3 py-2 text-sm bg-amber-50 hover:bg-amber-100">
                ⭐ Guardar rango actual
            </button>
        </div>
    </div>
</div>

<script>
    // ===== Claves de persistencia LOCAL =====
    const EMPRESAS_SELECCIONADAS = <?= json_encode($empresasSeleccionadas) ?>;
    const COMPANY_SCOPE = EMPRESAS_SELECCIONADAS.length > 0 ? EMPRESAS_SELECCIONADAS.join('_') : '__todas__';
    const ACC_KEY   = 'cuentas_temp:'+COMPANY_SCOPE;
    const SS_KEY    = 'seg_social_temp:'+COMPANY_SCOPE;
    const PREST_SEL_KEY = 'prestamo_sel_multi:v4:'+COMPANY_SCOPE;
    const ESTADO_PAGO_KEY = 'estado_pago_temp:'+COMPANY_SCOPE;
    const MANUAL_ROWS_KEY = 'filas_manuales_temp:'+COMPANY_SCOPE;
    const SELECTED_CONDUCTORS_KEY = 'conductores_seleccionados_temp:'+COMPANY_SCOPE;

    const PRESTAMOS_LIST = <?php echo json_encode($prestamosList, JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK); ?>;
    const CONDUCTORES_LIST = <?= json_encode(array_map(fn($f)=>$f['nombre'],$filas), JSON_UNESCAPED_UNICODE); ?>;

    const toInt = (s)=>{ 
        if(typeof s==='number') return Math.round(s); 
        s=(s||'').toString().replace(/\./g,'').replace(/,/g,'').replace(/[^\d\-]/g,''); 
        return parseInt(s||'0',10)||0; 
    };
    
    const fmt = (n)=> (n||0).toLocaleString('es-CO');
    const getLS=(k)=>{try{return JSON.parse(localStorage.getItem(k)||'{}')}catch{return{}}};
    const setLS=(k,v)=> localStorage.setItem(k, JSON.stringify(v));

    // ===== Variables globales =====
    let accMap = getLS(ACC_KEY);
    let ssMap  = getLS(SS_KEY);
    let prestSel = getLS(PREST_SEL_KEY); 
    if(!prestSel || typeof prestSel!=='object') prestSel = {};
    
    let estadoPagoMap = getLS(ESTADO_PAGO_KEY) || {};
    let manualRows = JSON.parse(localStorage.getItem(MANUAL_ROWS_KEY) || '[]');
    let selectedConductors = JSON.parse(localStorage.getItem(SELECTED_CONDUCTORS_KEY) || '[]');

    // ===== Elementos DOM =====
    const tbody = document.getElementById('tbody');
    const btnAddManual = document.getElementById('btnAddManual');
    const floatingPanel = document.getElementById('floatingPanel');
    const panelDragHandle = document.getElementById('panelDragHandle');
    const closePanel = document.getElementById('closePanel');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const btnSeleccionarTodas = document.getElementById('btnSeleccionarTodas');
    const btnLimpiarTodas = document.getElementById('btnLimpiarTodas');

    // ===== FUNCIÓN PARA SELECCIONAR/LIMPIAR EMPRESAS =====
    btnSeleccionarTodas.addEventListener('click', () => {
        document.querySelectorAll('.empresa-checkbox input[type="checkbox"]').forEach(cb => {
            cb.checked = true;
        });
    });
    
    btnLimpiarTodas.addEventListener('click', () => {
        document.querySelectorAll('.empresa-checkbox input[type="checkbox"]').forEach(cb => {
            cb.checked = false;
        });
    });

    // ===== FUNCIÓN PARA OBTENER EL NOMBRE DEL CONDUCTOR =====
    function obtenerNombreConductorDeFila(tr) {
        if (tr.classList.contains('fila-manual')) {
            const select = tr.querySelector('.conductor-select');
            return select ? select.value.trim() : '';
        } else {
            const link = tr.querySelector('.conductor-link');
            return link ? link.textContent.trim() : '';
        }
    }

    // ===== FUNCIÓN PARA ASIGNAR PRÉSTAMOS A FILAS =====
    function asignarPrestamosAFilas() {
        document.querySelectorAll('#tbody tr').forEach(tr => {
            let nombreConductor = obtenerNombreConductorDeFila(tr);
            if (!nombreConductor) return;
            
            const prestamosDeEsteConductor = prestSel[nombreConductor] || [];
            
            if (prestamosDeEsteConductor.length === 0) {
                const prestSpan = tr.querySelector('.prest');
                if (prestSpan) prestSpan.textContent = '0';
                const selLabel = tr.querySelector('.selected-deudor');
                if (selLabel) selLabel.textContent = '';
                return;
            }
            
            let totalMostrar = 0;
            let nombres = [];
            
            prestamosDeEsteConductor.forEach(prestamoGuardado => {
                const prestamoActual = PRESTAMOS_LIST.find(p => 
                    p.id === prestamoGuardado.id || 
                    p.name === prestamoGuardado.name
                );
                
                if (prestamoActual) {
                    if (prestamoGuardado.esManual === true && prestamoGuardado.valorManual !== undefined) {
                        totalMostrar += prestamoGuardado.valorManual;
                    } else {
                        totalMostrar += prestamoActual.total;
                    }
                    
                    nombres.push(prestamoActual.name);
                }
            });
            
            const prestSpan = tr.querySelector('.prest');
            const selLabel = tr.querySelector('.selected-deudor');
            
            if (prestSpan) prestSpan.textContent = fmt(totalMostrar);
            if (selLabel) selLabel.textContent = nombres.length <= 2 
                ? nombres.join(', ') 
                : nombres.slice(0,2).join(', ') + ' +' + (nombres.length-2) + ' más';
        });
    }

    // ===== FUNCIÓN PARA AGREGAR FILA MANUAL =====
    function agregarFilaManual(manualIdFromLS=null) {
        const manualId = manualIdFromLS || ('manual_' + Date.now());
        const nuevaFila = document.createElement('tr');
        nuevaFila.className = 'fila-manual';
        nuevaFila.dataset.manualId = manualId;
        nuevaFila.dataset.conductor = '';
        
        nuevaFila.innerHTML = `
      <td class="px-3 py-2">
        <select class="conductor-select w-full max-w-[200px] rounded-lg border border-slate-300 px-2 py-1">
          <option value="">-- Seleccionar conductor --</option>
          ${CONDUCTORES_LIST.map(c => `<option value="${c}">${c}</option>`).join('')}
        </select>
      </td>
      <td class="px-3 py-2 text-right">
        <input type="text" class="base-manual w-full max-w-[120px] rounded-lg border border-slate-300 px-2 py-1 text-right num" value="0" placeholder="0">
      </td>
      <td class="px-3 py-2 text-right num ajuste">0</td>
      <td class="px-3 py-2 text-right num llego">0</td>
      <td class="px-3 py-2 text-right num ret">0</td>
      <td class="px-3 py-2 text-right num mil4">0</td>
      <td class="px-3 py-2 text-right num apor">0</td>
      <td class="px-3 py-2 text-right">
        <input type="text" class="ss w-full max-w-[120px] rounded-lg border border-slate-300 px-2 py-1 text-right num" value="">
      </td>
      <td class="px-3 py-2 text-right">
        <div class="flex items-center justify-end gap-2">
          <span class="num prest">0</span>
          <button type="button" class="btn-prest text-xs px-2 py-1 rounded border border-slate-300 bg-slate-50 hover:bg-slate-100">
            Seleccionar
          </button>
        </div>
        <div class="text-[11px] text-slate-500 text-right selected-deudor"></div>
      </td>
      <td class="px-3 py-2">
        <input type="text" class="cta w-full max-w-[180px] rounded-lg border border-slate-300 px-2 py-1" value="" placeholder="N° cuenta">
      </td>
      <td class="px-3 py-2 text-right num pagar">0</td>
      <td class="px-3 py-2 text-center">
        <select class="estado-pago w-full max-w-[140px] rounded-lg border border-slate-300 px-2 py-1 text-sm">
          <option value="">Sin estado</option>
          <option value="pagado">✅ Pagado</option>
          <option value="pendiente">❌ Pendiente</option>
          <option value="procesando">🔄 Procesando</option>
          <option value="parcial">⚠️ Parcial</option>
        </select>
      </td>
      <td class="px-3 py-2 text-center">
        <div class="flex items-center justify-center gap-2">
          <input type="checkbox" class="checkbox-conductor selector-conductor">
          <button type="button" class="btn-eliminar-manual text-xs px-2 py-1 rounded border border-rose-300 bg-rose-50 hover:bg-rose-100 text-rose-700">
            🗑️
          </button>
        </div>
      </td>
    `;

        tbody.appendChild(nuevaFila);

        if (!manualIdFromLS) {
            manualRows.push(manualId);
            localStorage.setItem(MANUAL_ROWS_KEY, JSON.stringify(manualRows));
        }

        configurarEventosFila(nuevaFila);
        asignarPrestamosAFilas();
        recalc();
        filtrarConductores();
        restaurarSeleccionCheckbox(nuevaFila);
    }

    // ===== CONFIGURAR EVENTOS PARA FILA =====
    function configurarEventosFila(tr) {
        const baseInput = tr.querySelector('.base-manual');
        const cta = tr.querySelector('input.cta');
        const ss = tr.querySelector('input.ss');
        const estadoPago = tr.querySelector('select.estado-pago');
        const btnEliminar = tr.querySelector('.btn-eliminar-manual');
        const btnPrest = tr.querySelector('.btn-prest');
        const conductorSelect = tr.querySelector('.conductor-select');
        const checkbox = tr.querySelector('.selector-conductor');

        let baseName = '';
        if (conductorSelect) {
            baseName = conductorSelect.value || '';
            conductorSelect.addEventListener('change', () => {
                tr.dataset.conductor = normalizarTexto(conductorSelect.value);
                filtrarConductores();
                asignarPrestamosAFilas();
            });
            tr.dataset.conductor = normalizarTexto(baseName);
        } else {
            baseName = tr.querySelector('.conductor-link').textContent.trim();
        }

        // Checkbox de selección
        if (checkbox) {
            checkbox.addEventListener('change', () => {
                if (checkbox.checked) {
                    tr.classList.add('fila-seleccionada');
                } else {
                    tr.classList.remove('fila-seleccionada');
                }
                actualizarPanelFlotante();
                guardarSeleccionCheckboxes();
            });
        }

        // Base manual
        if (baseInput) {
            baseInput.addEventListener('input', () => {
                baseInput.value = fmt(toInt(baseInput.value));
                recalc();
                actualizarPanelFlotante();
            });
        }

        // Cuenta bancaria
        if (cta) {
            if (baseName && accMap[baseName]) cta.value = accMap[baseName];
            cta.addEventListener('change', () => { 
                const name = conductorSelect ? conductorSelect.value : tr.querySelector('.conductor-link').textContent.trim();
                if (!name) return;
                accMap[name] = cta.value.trim(); 
                setLS(ACC_KEY, accMap); 
            });
        }

        // Seguridad social
        if (ss) {
            if (baseName && ssMap[baseName]) ss.value = fmt(toInt(ssMap[baseName]));
            ss.addEventListener('input', () => { 
                const name = conductorSelect ? conductorSelect.value : tr.querySelector('.conductor-link').textContent.trim();
                if (!name) return;
                ssMap[name] = toInt(ss.value); 
                setLS(SS_KEY, ssMap); 
                recalc(); 
                actualizarPanelFlotante();
            });
        }

        // Estado de pago
        if (estadoPago) {
            if (baseName && estadoPagoMap[baseName]) {
                estadoPago.value = estadoPagoMap[baseName];
                aplicarEstadoFila(tr, estadoPagoMap[baseName]);
            }
            estadoPago.addEventListener('change', () => { 
                const name = conductorSelect ? conductorSelect.value : tr.querySelector('.conductor-link').textContent.trim();
                if (!name) return;
                estadoPagoMap[name] = estadoPago.value; 
                setLS(ESTADO_PAGO_KEY, estadoPagoMap); 
                aplicarEstadoFila(tr, estadoPago.value);
            });
        }

        // Eliminar fila manual
        if (btnEliminar) {
            btnEliminar.addEventListener('click', () => {
                const manualId = tr.dataset.manualId;
                manualRows = manualRows.filter(id => id !== manualId);
                localStorage.setItem(MANUAL_ROWS_KEY, JSON.stringify(manualRows));
                tr.remove();
                recalc();
                actualizarPanelFlotante();
                filtrarConductores();
            });
        }

        // Botón préstamos
        if (btnPrest) {
            btnPrest.addEventListener('click', () => openPrestModalForRow(tr));
        }

        // Cambio de conductor en fila manual
        if (conductorSelect) {
            conductorSelect.addEventListener('change', () => {
                const newBaseName = conductorSelect.value;
                baseName = newBaseName;

                if (cta && accMap[newBaseName]) cta.value = accMap[newBaseName];
                if (ss && ssMap[newBaseName]) ss.value = fmt(toInt(ssMap[newBaseName]));
                if (estadoPago && estadoPagoMap[newBaseName]) {
                    estadoPago.value = estadoPagoMap[newBaseName];
                    aplicarEstadoFila(tr, estadoPagoMap[newBaseName]);
                }
                
                asignarPrestamosAFilas();
                recalc();
                actualizarPanelFlotante();
            });
        }

        asignarPrestamosAFilas();
    }

    // ===== FUNCIÓN PARA APLICAR ESTADO DE FILA =====
    function aplicarEstadoFila(tr, estado) {
        tr.classList.remove('estado-pagado', 'estado-pendiente', 'estado-procesando', 'estado-parcial');
        if (estado) tr.classList.add(`estado-${estado}`);
    }

    // ===== PANEL FLOTANTE =====
    function actualizarPanelFlotante() {
        const checkboxes = document.querySelectorAll('#tbody .selector-conductor:checked');
        const count = checkboxes.length;
        
        if (count === 0) {
            floatingPanel.classList.add('hidden');
            return;
        }
        
        floatingPanel.classList.remove('hidden');
        
        let totalPagar = 0;
        let totalLlego = 0;
        let totalRetencion = 0;
        let total4x1000 = 0;
        let totalAporte = 0;
        let totalSS = 0;
        let totalPrestamos = 0;
        let nombresConductores = [];
        
        checkboxes.forEach(checkbox => {
            const tr = checkbox.closest('tr');
            if (!tr) return;
            
            const pagar = toInt(tr.querySelector('.pagar').textContent || '0');
            const llego = toInt(tr.querySelector('.llego').textContent || '0');
            const ret = toInt(tr.querySelector('.ret').textContent || '0');
            const mil4 = toInt(tr.querySelector('.mil4').textContent || '0');
            const apor = toInt(tr.querySelector('.apor').textContent || '0');
            const prest = toInt(tr.querySelector('.prest').textContent || '0');
            
            let nombreConductor = obtenerNombreConductorDeFila(tr);
            
            totalPagar += pagar;
            totalLlego += llego;
            totalRetencion += ret;
            total4x1000 += mil4;
            totalAporte += apor;
            totalPrestamos += prest;
            nombresConductores.push(nombreConductor);
            
            const ssInput = tr.querySelector('input.ss');
            if (ssInput) {
                totalSS += toInt(ssInput.value);
            }
        });
        
        document.getElementById('selectedCount').textContent = count;
        document.getElementById('panelConductoresCount').textContent = count;
        document.getElementById('panelTotalPagar').textContent = fmt(totalPagar);
        document.getElementById('panelTotalLlego').textContent = fmt(totalLlego);
        document.getElementById('panelTotalRetencion').textContent = fmt(totalRetencion);
        document.getElementById('panelTotal4x1000').textContent = fmt(total4x1000);
        document.getElementById('panelTotalAporte').textContent = fmt(totalAporte);
        document.getElementById('panelTotalSS').textContent = fmt(totalSS);
        document.getElementById('panelTotalPrestamos').textContent = fmt(totalPrestamos);
        
        const promedio = count > 0 ? Math.round(totalPagar / count) : 0;
        document.getElementById('panelPromedio').textContent = fmt(promedio);
        
        const nombresContainer = document.getElementById('panelNombresConductores');
        nombresContainer.innerHTML = '';
        
        if (nombresConductores.length > 0) {
            nombresConductores.forEach(nombre => {
                const div = document.createElement('div');
                div.className = 'py-1 border-b border-slate-100 last:border-0';
                div.textContent = nombre;
                nombresContainer.appendChild(div);
            });
        } else {
            nombresContainer.innerHTML = '<div class="text-slate-400 italic">Ningún conductor seleccionado</div>';
        }
    }

    function guardarSeleccionCheckboxes() {
        const checkboxes = document.querySelectorAll('#tbody .selector-conductor');
        const seleccionados = [];
        
        checkboxes.forEach((checkbox, index) => {
            if (checkbox.checked) {
                const tr = checkbox.closest('tr');
                if (tr) {
                    let nombreConductor = obtenerNombreConductorDeFila(tr);
                    if (nombreConductor) {
                        seleccionados.push(nombreConductor);
                    }
                }
            }
        });
        
        selectedConductors = seleccionados;
        localStorage.setItem(SELECTED_CONDUCTORS_KEY, JSON.stringify(selectedConductors));
    }

    function restaurarSeleccionCheckbox(tr) {
        if (!tr) return;
        
        let nombreConductor = obtenerNombreConductorDeFila(tr);
        
        if (nombreConductor && selectedConductors.includes(nombreConductor)) {
            const checkbox = tr.querySelector('.selector-conductor');
            if (checkbox) {
                checkbox.checked = true;
                tr.classList.add('fila-seleccionada');
            }
        }
    }

    // ===== PANEL ARRASTRABLE =====
    function hacerPanelArrastrable() {
        let isDragging = false;
        let currentX;
        let currentY;
        let initialX;
        let initialY;
        let xOffset = 0;
        let yOffset = 0;

        panelDragHandle.addEventListener('mousedown', dragStart);
        document.addEventListener('mousemove', drag);
        document.addEventListener('mouseup', dragEnd);

        function dragStart(e) {
            initialX = e.clientX - xOffset;
            initialY = e.clientY - yOffset;

            if (e.target === panelDragHandle || panelDragHandle.contains(e.target)) {
                isDragging = true;
            }
        }

        function drag(e) {
            if (isDragging) {
                e.preventDefault();
                currentX = e.clientX - initialX;
                currentY = e.clientY - initialY;

                xOffset = currentX;
                yOffset = currentY;

                setTranslate(currentX, currentY, floatingPanel);
            }
        }

        function dragEnd() {
            initialX = currentX;
            initialY = currentY;
            isDragging = false;
        }

        function setTranslate(xPos, yPos, el) {
            el.style.transform = `translate3d(${xPos}px, ${yPos}px, 0)`;
        }
    }

    // ===== CARGAR FILAS MANUALES =====
    function cargarFilasManuales() {
        manualRows.forEach(manualId => {
            agregarFilaManual(manualId);
        });
    }

    // ===== INICIALIZAR FILAS EXISTENTES =====
    function initializeExistingRows() {
        [...tbody.querySelectorAll('tr')].forEach(tr => {
            if (!tr.classList.contains('fila-manual')) {
                configurarEventosFila(tr);
                restaurarSeleccionCheckbox(tr);
            }
        });
        asignarPrestamosAFilas();
    }

    // ===== NORMALIZAR TEXTO =====
    function normalizarTexto(texto) {
        return texto
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    // ===== BUSCADOR DE CONDUCTORES =====
    const buscadorConductores = document.getElementById('buscadorConductores');
    const clearBuscar = document.getElementById('clearBuscar');
    const contadorConductores = document.getElementById('contador-conductores');

    function filtrarConductores() {
        const textoBusqueda = normalizarTexto(buscadorConductores.value);
        const filas = tbody.querySelectorAll('tr');
        let filasVisibles = 0;
        
        if (textoBusqueda === '') {
            filas.forEach(fila => {
                fila.style.display = '';
                filasVisibles++;
            });
            clearBuscar.style.display = 'none';
        } else {
            filas.forEach(fila => {
                let nombreConductor = obtenerNombreConductorDeFila(fila);
                const nombreNormalizado = normalizarTexto(nombreConductor);
                
                if (nombreNormalizado.includes(textoBusqueda)) {
                    fila.style.display = '';
                    filasVisibles++;
                } else {
                    fila.style.display = 'none';
                }
            });
            clearBuscar.style.display = 'block';
        }
        
        const totalConductores = filas.length;
        contadorConductores.textContent = `Mostrando ${filasVisibles} de ${totalConductores} conductores`;
        
        actualizarPanelFlotante();
    }

    buscadorConductores.addEventListener('input', filtrarConductores);
    clearBuscar.addEventListener('click', () => {
        buscadorConductores.value = '';
        filtrarConductores();
        buscadorConductores.focus();
    });

    // ===== EVENTOS =====
    btnAddManual.addEventListener('click', ()=> agregarFilaManual());
    closePanel.addEventListener('click', () => floatingPanel.classList.add('hidden'));

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', () => {
            const checkboxes = document.querySelectorAll('#tbody .selector-conductor');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
                const tr = checkbox.closest('tr');
                if (tr) {
                    if (selectAllCheckbox.checked) {
                        tr.classList.add('fila-seleccionada');
                    } else {
                        tr.classList.remove('fila-seleccionada');
                    }
                }
            });
            actualizarPanelFlotante();
            guardarSeleccionCheckboxes();
        });
    }

    // ===== Modal PRÉSTAMOS =====
    const prestModal   = document.getElementById('prestModal');
    const btnAssign    = document.getElementById('btnAssign');
    const btnCancel    = document.getElementById('btnCancel');
    const btnClose     = document.getElementById('btnCloseModal');
    const btnSelectAll = document.getElementById('btnSelectAll');
    const btnUnselectAll = document.getElementById('btnUnselectAll');
    const btnClearSel  = document.getElementById('btnClearSel');
    const prestSearch  = document.getElementById('prestSearch');
    const prestList    = document.getElementById('prestList');
    const selCount     = document.getElementById('selCount');
    const selTotal     = document.getElementById('selTotal');
    const selTotalManual = document.getElementById('selTotalManual');

    let currentRow=null, selectedIds=new Set(), filteredIdx=[];

    selTotalManual.addEventListener('input', ()=>{ selTotalManual.dataset.touched = '1'; });

    function renderPrestList(filter=''){
        prestList.innerHTML='';
        const nf=(filter||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
        filteredIdx=[];
        const frag=document.createDocumentFragment();
        PRESTAMOS_LIST.forEach((item,idx)=>{
            if(nf && !item.key.includes(nf)) return;
            filteredIdx.push(idx);

            const row=document.createElement('label');
            row.className='flex items-center justify-between gap-3 px-3 py-2 border-b border-slate-200';
            const left=document.createElement('div'); left.className='flex items-center gap-3';
            const cb=document.createElement('input'); cb.type='checkbox'; cb.checked=selectedIds.has(item.id); cb.dataset.id=item.id;
            const nm=document.createElement('span'); nm.className='truncate max-w-[360px]'; nm.textContent=item.name;
            left.append(cb,nm);
            const val=document.createElement('span'); val.className='num font-semibold'; val.textContent=(item.total||0).toLocaleString('es-CO');
            row.append(left,val);
            cb.addEventListener('change',()=>{ if(cb.checked)selectedIds.add(item.id); else selectedIds.delete(item.id); updateSelSummary(); });
            frag.append(row);
        });
        prestList.append(frag);
        updateSelSummary();
    }

    function updateSelSummary(){
        const arr=PRESTAMOS_LIST.filter(it=>selectedIds.has(it.id));
        const total = arr.reduce((a,b)=>a+(b.total||0),0);
        selCount.textContent=arr.length;
        selTotal.textContent=fmt(total);

        if (!selTotalManual.dataset.touched) {
            selTotalManual.value = fmt(total);
        }
    }

    function openPrestModalForRow(tr){
        currentRow = tr;
        selectedIds = new Set();
        
        let baseName = obtenerNombreConductorDeFila(tr);
        
        if (!baseName) {
            alert('Primero selecciona o ingresa el nombre del conductor antes de elegir préstamos.');
            return;
        }

        const prestamosGuardados = prestSel[baseName] || [];
        
        prestamosGuardados.forEach(prestamo => {
            if (prestamo.id !== undefined) {
                selectedIds.add(Number(prestamo.id));
            }
        });

        prestSearch.value = '';
        delete selTotalManual.dataset.touched;
        
        const currentPrestVal = toInt(tr.querySelector('.prest').textContent || '0');
        selTotalManual.value = fmt(currentPrestVal);
        
        renderPrestList('');

        prestModal.classList.remove('hidden');
        requestAnimationFrame(() => {
            prestSearch.focus();
            prestSearch.select();
        });
    }

    function closePrest(){ 
        prestModal.classList.add('hidden'); 
        currentRow=null; selectedIds=new Set(); filteredIdx=[]; 
        selTotalManual.value='0';
        delete selTotalManual.dataset.touched;
    }

    btnCancel.addEventListener('click',closePrest); 
    btnClose.addEventListener('click',closePrest);
    btnSelectAll.addEventListener('click',()=>{ filteredIdx.forEach(i=>selectedIds.add(PRESTAMOS_LIST[i].id)); renderPrestList(prestSearch.value); });
    btnUnselectAll.addEventListener('click',()=>{ filteredIdx.forEach(i=>selectedIds.delete(PRESTAMOS_LIST[i].id)); renderPrestList(prestSearch.value); });

    btnClearSel.addEventListener('click',()=>{
        if(!currentRow) return;
        let baseName = obtenerNombreConductorDeFila(currentRow);
        
        if (!baseName) return;
        
        currentRow.querySelector('.prest').textContent='0';
        currentRow.querySelector('.selected-deudor').textContent='';
        
        if (baseName) {
            delete prestSel[baseName]; 
            setLS(PREST_SEL_KEY, prestSel); 
        }
        
        recalc();
        actualizarPanelFlotante();
        selectedIds.clear(); 
        delete selTotalManual.dataset.touched;
        selTotalManual.value='0';
        renderPrestList(prestSearch.value);
    });

    // ===== ASIGNAR PRÉSTAMOS =====
    btnAssign.addEventListener('click', () => {
        if (!currentRow) return;
        
        let baseName = obtenerNombreConductorDeFila(currentRow);

        if (!baseName) {
            alert('Primero selecciona o ingresa el nombre del conductor.');
            return;
        }

        const fueEditadoManual = selTotalManual.dataset.touched === '1';
        let valorManual = fueEditadoManual ? toInt(selTotalManual.value) : 0;
        
        const prestamosSeleccionados = PRESTAMOS_LIST.filter(it => selectedIds.has(it.id));
        
        const prestamosAGuardar = prestamosSeleccionados.map(it => {
            const prestamoGuardado = {
                id: it.id,
                name: it.name,
                totalActual: it.total,
                esManual: false,
                valorManual: null
            };
            
            if (fueEditadoManual && selectedIds.size === 1) {
                prestamoGuardado.esManual = true;
                prestamoGuardado.valorManual = valorManual;
            }
            
            return prestamoGuardado;
        });

        prestSel[baseName] = prestamosAGuardar;
        setLS(PREST_SEL_KEY, prestSel);

        asignarPrestamosAFilas();
        recalc();
        actualizarPanelFlotante();
        closePrest();
    });

    prestSearch.addEventListener('input',()=>renderPrestList(prestSearch.value));

    // ===== MODAL DE VIAJES =====
    const RANGO_DESDE = <?= json_encode($desde) ?>;
    const RANGO_HASTA = <?= json_encode($hasta) ?>;
    const EMPRESAS_SEL = <?= json_encode($empresasSeleccionadas) ?>;

    const viajesModal            = document.getElementById('viajesModal');
    const viajesContent          = document.getElementById('viajesContent');
    const viajesTitle            = document.getElementById('viajesTitle');
    const viajesClose            = document.getElementById('viajesCloseBtn');
    const viajesSelectConductor  = document.getElementById('viajesSelectConductor');
    const viajesRango            = document.getElementById('viajesRango');
    const viajesEmpresa          = document.getElementById('viajesEmpresa');

    let viajesConductorActual = null;

    function initViajesSelect(selectedName) {
        viajesSelectConductor.innerHTML = "";
        CONDUCTORES_LIST.forEach(nombre => {
            const opt = document.createElement('option');
            opt.value = nombre;
            opt.textContent = nombre;
            if (nombre === selectedName) opt.selected = true;
            viajesSelectConductor.appendChild(opt);
        });
    }

    function attachFiltroViajes(){
        const pills = viajesContent.querySelectorAll('#legendFilterBar .legend-pill');
        const rows  = viajesContent.querySelectorAll('#viajesTableBody .viaje-item');
        if (!pills.length || !rows.length) return;

        let activeCat = null;

        function applyFilter(cat){
            if (cat === activeCat) {
                activeCat = null;
            } else {
                activeCat = cat;
            }

            pills.forEach(p => {
                const pcat = p.getAttribute('data-tipo');
                if (activeCat && pcat === activeCat) {
                    p.classList.add('ring-2','ring-blue-500','ring-offset-1','ring-offset-white');
                } else {
                    p.classList.remove('ring-2','ring-blue-500','ring-offset-1','ring-offset-white');
                }
            });

            rows.forEach(r => {
                if (!activeCat) {
                    r.style.display = '';
                } else {
                    if (r.classList.contains('cat-' + activeCat)) {
                        r.style.display = '';
                    } else {
                        r.style.display = 'none';
                    }
                }
            });
        }

        pills.forEach(p => {
            p.addEventListener('click', ()=>{
                const cat = p.getAttribute('data-tipo');
                applyFilter(cat);
            });
        });
    }

    function loadViajes(nombre) {
        viajesContent.innerHTML = '<p class="text-center m-0 animate-pulse">Cargando…</p>';
        viajesConductorActual = nombre;
        viajesTitle.textContent = nombre;

        const qs = new URLSearchParams({
            viajes_conductor: nombre,
            desde: RANGO_DESDE,
            hasta: RANGO_HASTA,
            empresas: JSON.stringify(EMPRESAS_SEL)
        });

        fetch('<?= basename(__FILE__) ?>?' + qs.toString())
            .then(r => r.text())
            .then(html => {
                viajesContent.innerHTML = html;
                attachFiltroViajes();
            })
            .catch(() => {
                viajesContent.innerHTML = '<p class="text-center text-rose-600">Error cargando viajes.</p>';
            });
    }

    function abrirModalViajes(nombreInicial){
        viajesRango.textContent   = RANGO_DESDE + " → " + RANGO_HASTA;
        viajesEmpresa.textContent = EMPRESAS_SEL.length > 0 ? EMPRESAS_SEL.join(' • ') : "Todas las empresas";

        initViajesSelect(nombreInicial);

        viajesModal.classList.add('show');

        loadViajes(nombreInicial);
    }

    function cerrarModalViajes(){
        viajesModal.classList.remove('show');
        viajesContent.innerHTML = '';
        viajesConductorActual = null;
    }

    viajesClose.addEventListener('click', cerrarModalViajes);
    viajesModal.addEventListener('click', (e)=>{
        if(e.target===viajesModal) cerrarModalViajes();
    });

    viajesSelectConductor.addEventListener('change', ()=>{
        const nuevo = viajesSelectConductor.value;
        loadViajes(nuevo);
    });

    // Conectar botones de conductor al modal
    document.querySelectorAll('#tbody .conductor-link').forEach(btn=>{
        btn.addEventListener('click', ()=>{
            abrirModalViajes(btn.textContent.trim());
        });
    });

    // ===== CÁLCULOS PRINCIPALES =====
    function recalc(){
        const porcentaje = parseFloat(document.getElementById('inp_porcentaje_ajuste').value) || 0;
        const rows = [...tbody.querySelectorAll('tr')];
        
        let totalAutomaticos = <?= $total_facturado ?>;
        let totalManuales = 0;
        
        let sumLleg = 0, sumRet = 0, sumMil4 = 0, sumAp = 0, sumSS = 0, sumPrest = 0, sumPagar = 0;
        
        rows.forEach((tr) => {
            if (tr.style.display === 'none') return;
            
            let base;
            
            if (tr.classList.contains('fila-manual')) {
                const baseInput = tr.querySelector('.base-manual');
                base = baseInput ? toInt(baseInput.value) : 0;
                totalManuales += base;
            } else {
                const baseEl = tr.querySelector('.base');
                if (!baseEl) return;
                base = toInt(baseEl.textContent);
            }
            
            const ajuste = Math.round(base * (porcentaje / 100));
            const llego = base - ajuste;
            const prest = toInt(tr.querySelector('.prest').textContent || '0');
            const ret = Math.round(llego * 0.035);
            const mil4 = Math.round(llego * 0.004);
            const ap = Math.round(llego * 0.10);
            const ssInput = tr.querySelector('input.ss');
            const ssVal = ssInput ? toInt(ssInput.value) : 0;
            const pagar = llego - ret - mil4 - ap - ssVal - prest;
            
            tr.querySelector('.ajuste').textContent = fmt(ajuste);
            tr.querySelector('.llego').textContent = fmt(llego);
            tr.querySelector('.ret').textContent = fmt(ret);
            tr.querySelector('.mil4').textContent = fmt(mil4);
            tr.querySelector('.apor').textContent = fmt(ap);
            tr.querySelector('.pagar').textContent = fmt(pagar);
            
            sumLleg += llego;
            sumRet += ret;
            sumMil4 += mil4;
            sumAp += ap;
            sumSS += ssVal;
            sumPrest += prest;
            sumPagar += pagar;
        });
        
        const totalFacturado = totalAutomaticos + totalManuales;
        document.getElementById('inp_facturado').value = fmt(totalFacturado);
        document.getElementById('inp_viajes_manuales').value = fmt(totalManuales);
        
        const ajusteTotal = Math.round(totalFacturado * (porcentaje / 100));
        document.getElementById('lbl_total_ajuste').textContent = fmt(ajusteTotal);
        
        document.getElementById('tot_valor_llego').textContent = fmt(sumLleg);
        document.getElementById('tot_retencion').textContent = fmt(sumRet);
        document.getElementById('tot_4x1000').textContent = fmt(sumMil4);
        document.getElementById('tot_aporte').textContent = fmt(sumAp);
        document.getElementById('tot_ss').textContent = fmt(sumSS);
        document.getElementById('tot_prestamos').textContent = fmt(sumPrest);
        document.getElementById('tot_pagar').textContent = fmt(sumPagar);
        
        actualizarPanelFlotante();
    }

    // ===== GESTIÓN DE CUENTAS GUARDADAS EN BD =====
    const formFiltros = document.getElementById('formFiltros');
    const inpDesde = document.getElementById('inp_desde');
    const inpHasta = document.getElementById('inp_hasta');
    const inpFact = document.getElementById('inp_facturado');
    const inpPorcentaje = document.getElementById('inp_porcentaje_ajuste');

    const saveCuentaModal = document.getElementById('saveCuentaModal');
    const btnShowSaveCuenta = document.getElementById('btnShowSaveCuenta');
    const btnCloseSaveCuenta = document.getElementById('btnCloseSaveCuenta');
    const btnCancelSaveCuenta = document.getElementById('btnCancelSaveCuenta');
    const btnDoSaveCuenta = document.getElementById('btnDoSaveCuenta');
    const cuentaPagadoCheckbox = document.getElementById('cuenta_pagado');
    const pagadoLabel = document.getElementById('pagadoLabel');

    const iNombre = document.getElementById('cuenta_nombre');
    const iEmpresas = document.getElementById('cuenta_empresas');
    const iRango = document.getElementById('cuenta_rango');
    const iCFact = document.getElementById('cuenta_facturado');
    const iCPorcentaje  = document.getElementById('cuenta_porcentaje');

    // Actualizar label del switch
    cuentaPagadoCheckbox.addEventListener('change', function() {
        if (this.checked) {
            pagadoLabel.textContent = 'PAGADA';
            pagadoLabel.className = 'text-xs px-2 py-1 rounded-full font-semibold bg-green-100 text-green-700';
        } else {
            pagadoLabel.textContent = 'NO PAGADA';
            pagadoLabel.className = 'text-xs px-2 py-1 rounded-full font-semibold bg-red-100 text-red-700';
        }
    });

    // ===== GUARDAR CUENTA EN BASE DE DATOS =====
    async function openSaveCuenta(){
        const empresasSeleccionadas = Array.from(document.querySelectorAll('.empresa-checkbox input[type="checkbox"]:checked'))
            .map(cb => cb.value);
            
        if(empresasSeleccionadas.length === 0){ 
            Swal.fire({
                title: '⚠️ Empresas requeridas',
                text: 'Selecciona al menos una EMPRESA antes de guardar la cuenta.',
                icon: 'warning'
            });
            return; 
        }
        
        const d = inpDesde.value; const h = inpHasta.value;

        iEmpresas.value = empresasSeleccionadas.join(' • ');
        iRango.value = `${d} → ${h}`;
        iNombre.value = `${empresasSeleccionadas[0]} ${d} a ${h}${empresasSeleccionadas.length > 1 ? ' y más' : ''}`;
        iCFact.value = fmt(toInt(inpFact.value));
        iCPorcentaje.value = parseFloat(inpPorcentaje.value) || 0;
        
        cuentaPagadoCheckbox.checked = false;
        pagadoLabel.textContent = 'NO PAGADA';
        pagadoLabel.className = 'text-xs px-2 py-1 rounded-full font-semibold bg-red-100 text-red-700';

        saveCuentaModal.classList.remove('hidden');
        setTimeout(()=> iNombre.focus(), 0);
    }
    
    function closeSaveCuenta(){ saveCuentaModal.classList.add('hidden'); }

    btnShowSaveCuenta.addEventListener('click', openSaveCuenta);
    btnCloseSaveCuenta.addEventListener('click', closeSaveCuenta);
    btnCancelSaveCuenta.addEventListener('click', closeSaveCuenta);

    btnDoSaveCuenta.addEventListener('click', async ()=>{
        const empresas = iEmpresas.value.trim();
        const [d1, d2raw] = iRango.value.split('→');
        const desde = (d1||'').trim();
        const hasta = (d2raw||'').trim();
        const nombre = iNombre.value.trim() || `${empresas} ${desde} a ${hasta}`;
        const facturado = toInt(iCFact.value);
        const porcentaje  = parseFloat(iCPorcentaje.value) || 0;
        const pagado = cuentaPagadoCheckbox.checked ? 1 : 0;

        const datosParaGuardar = {
            prestamos: { ...prestSel },
            segSocial: { ...ssMap },
            cuentasBancarias: { ...accMap },
            estadosPago: { ...estadoPagoMap },
            filasManuales: []
        };
        
        document.querySelectorAll('#tbody tr.fila-manual').forEach(tr => {
            const conductor = tr.querySelector('.conductor-select')?.value || '';
            const base = toInt(tr.querySelector('.base-manual')?.value || '0');
            const cuenta = tr.querySelector('input.cta')?.value || '';
            const segSocial = toInt(tr.querySelector('input.ss')?.value || '0');
            const estado = tr.querySelector('select.estado-pago')?.value || '';
            
            if (conductor) {
                datosParaGuardar.filasManuales.push({
                    conductor,
                    base,
                    cuenta,
                    segSocial,
                    estado
                });
            }
        });

        const formData = new FormData();
        formData.append('accion', 'guardar_cuenta');
        formData.append('nombre', nombre);
        formData.append('empresas', empresas);
        formData.append('desde', desde);
        formData.append('hasta', hasta);
        formData.append('facturado', facturado);
        formData.append('porcentaje_ajuste', porcentaje);
        formData.append('pagado', pagado);
        formData.append('datos_json', JSON.stringify(datosParaGuardar));

        try {
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            const resultado = await response.json();
            
            if (resultado.success) {
                Swal.fire({
                    title: '✅ Cuenta guardada',
                    text: 'La cuenta se guardó exitosamente en la base de datos.',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
                
                closeSaveCuenta();
                
                if (!gestorModal.classList.contains('hidden')) {
                    await renderCuentasBD();
                }
            } else {
                throw new Error(resultado.message);
            }
        } catch (error) {
            Swal.fire({
                title: '❌ Error',
                text: 'No se pudo guardar la cuenta: ' + error.message,
                icon: 'error'
            });
        }
    });

    // ===== GESTOR DE CUENTAS DESDE BASE DE DATOS =====
    const gestorModal = document.getElementById('gestorCuentasModal');
    const btnShowGestor = document.getElementById('btnShowGestorCuentas');
    const btnCloseGestor = document.getElementById('btnCloseGestor');
    const btnAddDesdeFiltro = document.getElementById('btnAddDesdeFiltro');
    const btnRecargarCuentas = document.getElementById('btnRecargarCuentas');
    const btnFiltrarPagadas = document.getElementById('btnFiltrarPagadas');
    const btnFiltrarNoPagadas = document.getElementById('btnFiltrarNoPagadas');
    const filtroEmpresaCuentas = document.getElementById('filtroEmpresaCuentas');
    const filtroEstadoPagado = document.getElementById('filtroEstadoPagado');
    const buscaCuentaBD = document.getElementById('buscaCuentaBD');
    const clearBuscarBD = document.getElementById('clearBuscarBD');
    const tbodyCuentasBD = document.getElementById('tbodyCuentasBD');
    const contadorCuentas = document.getElementById('contador-cuentas');

    function formatFecha(fechaStr) {
        if (!fechaStr) return '';
        const fecha = new Date(fechaStr);
        return fecha.toLocaleDateString('es-CO', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    async function togglePagadoCuenta(id, nuevoEstado) {
        try {
            const formData = new FormData();
            formData.append('accion', 'toggle_pagado');
            formData.append('id', id);
            formData.append('pagado', nuevoEstado ? 1 : 0);
            
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            const resultado = await response.json();
            
            if (!resultado.success) {
                throw new Error(resultado.message);
            }
            
            return true;
        } catch (error) {
            Swal.fire({
                title: '❌ Error',
                text: error.message,
                icon: 'error',
                timer: 2000
            });
            return false;
        }
    }

    async function renderCuentasBD() {
        const empresa = filtroEmpresaCuentas.value;
        const filtroEstado = filtroEstadoPagado.value;
        const filtroTexto = (buscaCuentaBD.value || '').toLowerCase();
        
        try {
            const response = await fetch(`?obtener_cuentas=1&empresa=${encodeURIComponent(empresa)}`);
            let cuentas = await response.json();
            
            if (filtroEstado !== '') {
                cuentas = cuentas.filter(c => c.pagado == filtroEstado);
            }
            
            if (filtroTexto) {
                cuentas = cuentas.filter(cuenta => 
                    cuenta.nombre.toLowerCase().includes(filtroTexto) ||
                    cuenta.usuario?.toLowerCase().includes(filtroTexto)
                );
            }
            
            contadorCuentas.textContent = `Mostrando ${cuentas.length} cuentas`;
            
            if (cuentas.length === 0) {
                tbodyCuentasBD.innerHTML = `
                    <tr>
                        <td colspan="7" class="px-3 py-8 text-center text-slate-500">
                            <div class="flex flex-col items-center gap-2">
                                <div class="text-3xl">📭</div>
                                <div>No hay cuentas guardadas</div>
                            </div>
                        </td>
                    </tr>`;
                return;
            }
            
            let html = '';
            cuentas.forEach(cuenta => {
                const pagadoClass = cuenta.pagado == 1 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                const pagadoText = cuenta.pagado == 1 ? 'Pagada' : 'No pagada';
                
                const empresasMostrar = cuenta.empresas.length > 40 ? cuenta.empresas.substring(0, 40) + '...' : cuenta.empresas;
                
                html += `
                <tr class="hover:bg-slate-50">
                    <td class="px-3 py-3">
                        <div class="font-medium">${cuenta.nombre}</div>
                        <div class="text-xs text-slate-500">${cuenta.usuario || 'Sistema'}</div>
                    </td>
                    <td class="px-3 py-3">
                        <div class="text-xs" title="${cuenta.empresas}">${empresasMostrar}</div>
                    </td>
                    <td class="px-3 py-3">
                        <div class="text-sm">${cuenta.desde} → ${cuenta.hasta}</div>
                    </td>
                    <td class="px-3 py-3 text-right num font-semibold">${fmt(cuenta.facturado || 0)}</td>
                    <td class="px-3 py-3 text-center text-xs text-slate-500">
                        ${formatFecha(cuenta.fecha_creacion)}
                    </td>
                    <td class="px-3 py-3 text-center">
                        <label class="switch-pagado inline-block align-middle">
                            <input type="checkbox" class="toggle-pagado" data-id="${cuenta.id}" ${cuenta.pagado == 1 ? 'checked' : ''}>
                            <span class="switch-slider"></span>
                        </label>
                        <span class="switch-label ${pagadoClass}">${pagadoText}</span>
                    </td>
                    <td class="px-3 py-3 text-right">
                        <div class="inline-flex gap-2">
                            <button class="btnCargarCuenta border px-3 py-2 rounded bg-blue-50 hover:bg-blue-100 text-xs text-blue-700" 
                                    data-id="${cuenta.id}"
                                    title="Cargar esta cuenta">
                                📂 Cargar
                            </button>
                            <button class="btnEliminarCuenta border px-3 py-2 rounded bg-rose-50 hover:bg-rose-100 text-xs text-rose-700" 
                                    data-id="${cuenta.id}"
                                    title="Eliminar esta cuenta">
                                🗑️
                            </button>
                        </div>
                    </td>
                </tr>`;
            });
            
            tbodyCuentasBD.innerHTML = html;
            
            document.querySelectorAll('.btnCargarCuenta').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.dataset.id;
                    await cargarCuentaCompletaBD(id);
                });
            });
            
            document.querySelectorAll('.btnEliminarCuenta').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.dataset.id;
                    await eliminarCuentaBD(id);
                });
            });
            
            document.querySelectorAll('.toggle-pagado').forEach(checkbox => {
                checkbox.addEventListener('change', async function() {
                    const id = this.dataset.id;
                    const nuevoEstado = this.checked ? 1 : 0;
                    const tr = this.closest('tr');
                    const labelSpan = tr.querySelector('.switch-label');
                    
                    if (nuevoEstado) {
                        labelSpan.textContent = 'Pagada';
                        labelSpan.className = 'switch-label bg-green-100 text-green-700 px-2 py-1 rounded-full text-xs';
                    } else {
                        labelSpan.textContent = 'No pagada';
                        labelSpan.className = 'switch-label bg-red-100 text-red-700 px-2 py-1 rounded-full text-xs';
                    }
                    
                    const exito = await togglePagadoCuenta(id, nuevoEstado);
                    
                    if (!exito) {
                        this.checked = !this.checked;
                        if (this.checked) {
                            labelSpan.textContent = 'Pagada';
                            labelSpan.className = 'switch-label bg-green-100 text-green-700 px-2 py-1 rounded-full text-xs';
                        } else {
                            labelSpan.textContent = 'No pagada';
                            labelSpan.className = 'switch-label bg-red-100 text-red-700 px-2 py-1 rounded-full text-xs';
                        }
                    }
                });
            });
            
        } catch (error) {
            console.error('Error al cargar cuentas:', error);
            tbodyCuentasBD.innerHTML = `
                <tr>
                    <td colspan="7" class="px-3 py-8 text-center text-rose-600">
                        <div class="flex flex-col items-center gap-2">
                            <div class="text-3xl">❌</div>
                            <div>Error al cargar cuentas</div>
                        </div>
                    </td>
                </tr>`;
        }
    }

    async function cargarCuentaCompletaBD(id) {
        const confirmacion = await Swal.fire({
            title: '¿Cargar esta cuenta?',
            text: 'Se restaurarán todos los datos guardados',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, cargar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#3b82f6'
        });
        
        if (!confirmacion.isConfirmed) return;
        
        try {
            const formData = new FormData();
            formData.append('accion', 'cargar_cuenta');
            formData.append('id', id);
            
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            const resultado = await response.json();
            
            if (resultado.success) {
                const cuenta = resultado.cuenta;
                
                Swal.fire({
                    title: '✅ Cuenta cargada',
                    html: `Se cargaron los datos de "${cuenta.nombre}".<br>
                           <span class="text-sm text-slate-600">Empresas: ${cuenta.empresas}</span><br>
                           <span class="text-xs text-amber-600">Debes seleccionar manualmente las empresas en los checkboxes para ver los viajes.</span>`,
                    icon: 'success'
                });
                
                inpDesde.value = cuenta.desde;
                inpHasta.value = cuenta.hasta;
                inpFact.value = fmt(cuenta.facturado || 0);
                inpPorcentaje.value = cuenta.porcentaje_ajuste || 0;
                
                const datos = cuenta.datos_json || {};
                
                if (datos.prestamos) {
                    prestSel = datos.prestamos;
                    setLS(PREST_SEL_KEY, prestSel);
                }
                
                if (datos.segSocial) {
                    ssMap = datos.segSocial;
                    setLS(SS_KEY, ssMap);
                }
                
                if (datos.cuentasBancarias) {
                    accMap = datos.cuentasBancarias;
                    setLS(ACC_KEY, accMap);
                }
                
                if (datos.estadosPago) {
                    estadoPagoMap = datos.estadosPago;
                    setLS(ESTADO_PAGO_KEY, estadoPagoMap);
                }
                
                document.querySelectorAll('#tbody tr.fila-manual').forEach(tr => tr.remove());
                manualRows = [];
                
                if (datos.filasManuales && datos.filasManuales.length > 0) {
                    datos.filasManuales.forEach(filaManual => {
                        agregarFilaManual();
                        
                        const ultimaFila = tbody.querySelector('tr.fila-manual:last-child');
                        if (ultimaFila) {
                            const select = ultimaFila.querySelector('.conductor-select');
                            const baseInput = ultimaFila.querySelector('.base-manual');
                            const ctaInput = ultimaFila.querySelector('input.cta');
                            const ssInput = ultimaFila.querySelector('input.ss');
                            const estadoSelect = ultimaFila.querySelector('select.estado-pago');
                            
                            if (select) select.value = filaManual.conductor;
                            if (baseInput) baseInput.value = fmt(filaManual.base);
                            if (ctaInput) ctaInput.value = filaManual.cuenta;
                            if (ssInput) ssInput.value = fmt(filaManual.segSocial);
                            if (estadoSelect) estadoSelect.value = filaManual.estado;
                            
                            const nombreConductor = filaManual.conductor;
                            if (nombreConductor) {
                                if (filaManual.cuenta) accMap[nombreConductor] = filaManual.cuenta;
                                if (filaManual.segSocial) ssMap[nombreConductor] = filaManual.segSocial;
                                if (filaManual.estado) estadoPagoMap[nombreConductor] = filaManual.estado;
                            }
                        }
                    });
                    
                    localStorage.setItem(MANUAL_ROWS_KEY, JSON.stringify(manualRows));
                }
                
                setTimeout(() => {
                    document.querySelectorAll('#tbody tr').forEach(tr => {
                        const nombre = obtenerNombreConductorDeFila(tr);
                        if (nombre && accMap[nombre]) {
                            const ctaInput = tr.querySelector('input.cta');
                            if (ctaInput) ctaInput.value = accMap[nombre];
                        }
                        
                        if (nombre && ssMap[nombre]) {
                            const ssInput = tr.querySelector('input.ss');
                            if (ssInput) ssInput.value = fmt(ssMap[nombre]);
                        }
                        
                        if (nombre && estadoPagoMap[nombre]) {
                            const estadoSelect = tr.querySelector('select.estado-pago');
                            if (estadoSelect) {
                                estadoSelect.value = estadoPagoMap[nombre];
                                aplicarEstadoFila(tr, estadoPagoMap[nombre]);
                            }
                        }
                    });
                    
                    asignarPrestamosAFilas();
                    recalc();
                    closeGestor();
                }, 100);
            } else {
                throw new Error(resultado.message);
            }
        } catch (error) {
            Swal.fire({
                title: '❌ Error',
                text: error.message,
                icon: 'error'
            });
        }
    }

    async function eliminarCuentaBD(id) {
        const confirmacion = await Swal.fire({
            title: '¿Eliminar esta cuenta?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ef4444'
        });
        
        if (!confirmacion.isConfirmed) return;
        
        try {
            const formData = new FormData();
            formData.append('accion', 'eliminar_cuenta');
            formData.append('id', id);
            
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            const resultado = await response.json();
            
            if (resultado.success) {
                await renderCuentasBD();
                Swal.fire({
                    title: '✅ Eliminada',
                    text: 'Cuenta eliminada correctamente',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                throw new Error(resultado.message);
            }
        } catch (error) {
            Swal.fire({
                title: '❌ Error',
                text: error.message,
                icon: 'error'
            });
        }
    }

    function openGestor(){
        renderCuentasBD();
        gestorModal.classList.remove('hidden');
        setTimeout(()=> buscaCuentaBD.focus(), 0);
    }
    
    function closeGestor(){ 
        gestorModal.classList.add('hidden'); 
        buscaCuentaBD.value = '';
        filtroEstadoPagado.value = '';
    }

    btnShowGestor.addEventListener('click', openGestor);
    btnCloseGestor.addEventListener('click', closeGestor);
    btnRecargarCuentas.addEventListener('click', renderCuentasBD);
    filtroEmpresaCuentas.addEventListener('change', renderCuentasBD);
    filtroEstadoPagado.addEventListener('change', renderCuentasBD);
    buscaCuentaBD.addEventListener('input', renderCuentasBD);
    clearBuscarBD.addEventListener('click', () => {
        buscaCuentaBD.value = '';
        renderCuentasBD();
        buscaCuentaBD.focus();
    });
    
    btnFiltrarPagadas.addEventListener('click', () => {
        filtroEstadoPagado.value = '1';
        renderCuentasBD();
    });
    
    btnFiltrarNoPagadas.addEventListener('click', () => {
        filtroEstadoPagado.value = '0';
        renderCuentasBD();
    });
    
    btnAddDesdeFiltro.addEventListener('click', ()=>{ 
        closeGestor(); 
        setTimeout(() => openSaveCuenta(), 300);
    });

    // ===== INICIALIZACIÓN =====
    document.addEventListener('DOMContentLoaded', function() {
        initializeExistingRows();
        cargarFilasManuales();
        hacerPanelArrastrable();
        asignarPrestamosAFilas();
        recalc();
        if (selectedConductors.length > 0) {
            actualizarPanelFlotante();
        }
        
        document.getElementById('inp_porcentaje_ajuste').addEventListener('input', recalc);
        document.getElementById('inp_facturado').addEventListener('input', recalc);
    });
</script>
</body>
</html>
<?php
$conn->close();
?>