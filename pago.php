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

/* ================= Viajes del rango ================= */
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

/* ================= Filas base ================= */
$filas = []; 
$total_facturado = 0;

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
    $total_facturado += (int)$total;
}

usort($filas, fn($a,$b)=> $b['total_bruto'] <=> $a['total_bruto']);
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
        .viajes-card{ width:min(720px,94vw); max-height:90vh; overflow:hidden; border-radius:16px; background:#fff; box-shadow:0 20px 60px rgba(0,0,0,.25); border:1px solid #e5e7eb; }
        .viajes-header{padding:14px 16px;border-bottom:1px solid #eef2f7}
        .viajes-body{padding:14px 16px;overflow:auto; max-height:70vh}
        .viajes-close{padding:6px 10px; border-radius:10px; cursor:pointer;}
        .viajes-close:hover{background:#f3f4f6}
        .conductor-link{cursor:pointer; color:#0d6efd; text-decoration:underline;}
        .estado-pagado { background-color: #f0fdf4 !important; border-left: 4px solid #22c55e; }
        .estado-pendiente { background-color: #fef2f2 !important; border-left: 4px solid #ef4444; }
        .estado-procesando { background-color: #fffbeb !important; border-left: 4px solid #f59e0b; }
        .estado-parcial { background-color: #eff6ff !important; border-left: 4px solid #3b82f6; }
        .fila-manual { background-color: #f0f9ff !important; border-left: 4px solid #0ea5e9; }
        .fila-manual td { background-color: #f0f9ff !important; }
        .buscar-container { position: relative; }
        .buscar-clear { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #64748b; cursor: pointer; display: none; }
        .buscar-clear:hover { color: #475569; }
        #floatingPanel { box-shadow: 0 10px 40px rgba(0,0,0,0.15); z-index: 9999; }
        #panelDragHandle { user-select: none; }
        .checkbox-conductor { width: 18px; height: 18px; cursor: pointer; }
        .fila-seleccionada { background-color: #f0f9ff !important; }
        .bd-badge { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .switch-pagado { position: relative; display: inline-block; width: 50px; height: 24px; }
        .switch-pagado input { opacity: 0; width: 0; height: 0; }
        .switch-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ef4444; transition: .3s; border-radius: 34px; }
        .switch-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
        input:checked + .switch-slider { background-color: #22c55e; }
        input:checked + .switch-slider:before { transform: translateX(26px); }
        .empresas-container { max-height: 150px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.75rem; background: white; }
        .empresa-checkbox { display: flex; align-items: center; gap: 0.5rem; padding: 0.25rem 0; }
        .empresa-checkbox input[type="checkbox"] { width: 1.2rem; height: 1.2rem; border-radius: 0.25rem; border: 2px solid #cbd5e1; cursor: pointer; }
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
                <input id="inp_porcentaje_ajuste" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right num" value="5">
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

    <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
            <div>
                <h3 class="text-lg font-semibold">Conductores</h3>
                <div id="contador-conductores" class="text-xs text-slate-500 mt-1">
                    Mostrando <?= count($filas) ?> de <?= count($filas) ?> conductores
                </div>
            </div>
            <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
                <div class="buscar-container w-full md:w-64">
                    <input id="buscadorConductores" type="text" placeholder="Buscar conductor..." class="w-full rounded-lg border border-slate-300 px-3 py-2 pl-3 pr-10">
                    <button id="clearBuscar" class="buscar-clear">✕</button>
                </div>
                <button id="btnAddManual" type="button" class="rounded-lg bg-green-600 text-white px-4 py-2 text-sm hover:bg-green-700 whitespace-nowrap">➕ Agregar conductor manual</button>
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
                    <th class="px-3 py-2 text-center"><input type="checkbox" id="selectAllCheckbox" class="checkbox-conductor"></th>
                </tr>
                </thead>
                <tbody id="tbody" class="divide-y divide-slate-100 bg-white">
                <?php 
                foreach ($filas as $f): 
                ?>
                    <tr data-conductor="<?= htmlspecialchars(mb_strtolower($f['nombre'])) ?>" data-total-base="<?= $f['total_bruto'] ?>">
                        <td class="px-3 py-2">
                            <button type="button" class="conductor-link" data-nombre="<?= htmlspecialchars($f['nombre']) ?>"><?= htmlspecialchars($f['nombre']) ?></button>
                        </td>
                        <td class="px-3 py-2 text-right num base"><?= number_format($f['total_bruto'],0,',','.') ?></td>
                        <td class="px-3 py-2 text-right num ajuste">0</td>
                        <td class="px-3 py-2 text-right num llego">0</td>
                        <td class="px-3 py-2 text-right num ret">0</td>
                        <td class="px-3 py-2 text-right num mil4">0</td>
                        <td class="px-3 py-2 text-right num apor">0</td>
                        <td class="px-3 py-2 text-right"><input type="text" class="ss w-full max-w-[120px] rounded-lg border border-slate-300 px-2 py-1 text-right num" value=""></td>
                        <td class="px-3 py-2 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <span class="num prest">0</span>
                                <button type="button" class="btn-prest text-xs px-2 py-1 rounded border border-slate-300 bg-slate-50 hover:bg-slate-100" data-nombre="<?= htmlspecialchars($f['nombre']) ?>">Seleccionar</button>
                            </div>
                            <div class="text-[11px] text-slate-500 text-right selected-deudor"></div>
                        </td>
                        <td class="px-3 py-2"><input type="text" class="cta w-full max-w-[180px] rounded-lg border border-slate-300 px-2 py-1" placeholder="N° cuenta"></td>
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
                        <td class="px-3 py-2 text-center"><input type="checkbox" class="checkbox-conductor selector-conductor"></td>
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

<!-- Paneles modales (igual que antes pero omitidos por brevedad - mantener los mismos) -->
<div id="floatingPanel" class="hidden fixed z-50 bg-white border border-blue-300 rounded-xl shadow-lg" style="top: 100px; left: 100px; min-width: 300px;">[contenido igual]</div>
<div id="prestModal" class="hidden fixed inset-0 z-50">[contenido igual]</div>
<div id="viajesModal" class="viajes-backdrop">[contenido igual]</div>
<div id="saveCuentaModal" class="hidden fixed inset-0 z-50">[contenido igual]</div>
<div id="gestorCuentasModal" class="hidden fixed inset-0 z-50">[contenido igual]</div>

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
const CONDUCTORES_LIST = <?= json_encode(array_map(fn($f)=>$f['nombre'],$filas), JSON_UNESCAPED_UNICODE); ?>;

const toInt = (s)=>{ if(typeof s==='number') return Math.round(s); s=(s||'').toString().replace(/\./g,'').replace(/,/g,'').replace(/[^\d\-]/g,''); return parseInt(s||'0',10)||0; };
const fmt = (n)=> (n||0).toLocaleString('es-CO');
const getLS=(k)=>{try{return JSON.parse(localStorage.getItem(k)||'{}')}catch{return{}}};
const setLS=(k,v)=> localStorage.setItem(k, JSON.stringify(v));

let accMap = getLS(ACC_KEY);
let ssMap = getLS(SS_KEY);
let prestSel = getLS(PREST_SEL_KEY) || {};
let estadoPagoMap = getLS(ESTADO_PAGO_KEY) || {};
let manualRows = JSON.parse(localStorage.getItem(MANUAL_ROWS_KEY) || '[]');
let selectedConductors = JSON.parse(localStorage.getItem(SELECTED_CONDUCTORS_KEY) || '[]');

const tbody = document.getElementById('tbody');
const btnAddManual = document.getElementById('btnAddManual');
const floatingPanel = document.getElementById('floatingPanel');
const panelDragHandle = document.getElementById('panelDragHandle');
const closePanel = document.getElementById('closePanel');
const selectAllCheckbox = document.getElementById('selectAllCheckbox');

// ===== FUNCIÓN PRINCIPAL DE CÁLCULO =====
function recalc() {
    console.log('Recalculando...');
    const porcentaje = parseFloat(document.getElementById('inp_porcentaje_ajuste').value) || 0;
    const rows = [...tbody.querySelectorAll('tr')];
    
    let totalAutomaticos = <?= $total_facturado ?>;
    let totalManuales = 0;
    let sumLleg = 0, sumRet = 0, sumMil4 = 0, sumAp = 0, sumSS = 0, sumPrest = 0, sumPagar = 0;
    
    rows.forEach(tr => {
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
        const prest = toInt(tr.querySelector('.prest')?.textContent || '0');
        const ret = Math.round(llego * 0.035);
        const mil4 = Math.round(llego * 0.004);
        const ap = Math.round(llego * 0.10);
        const ssInput = tr.querySelector('input.ss');
        const ssVal = ssInput ? toInt(ssInput.value) : 0;
        const pagar = llego - ret - mil4 - ap - ssVal - prest;
        
        if (tr.querySelector('.ajuste')) tr.querySelector('.ajuste').textContent = fmt(ajuste);
        if (tr.querySelector('.llego')) tr.querySelector('.llego').textContent = fmt(llego);
        if (tr.querySelector('.ret')) tr.querySelector('.ret').textContent = fmt(ret);
        if (tr.querySelector('.mil4')) tr.querySelector('.mil4').textContent = fmt(mil4);
        if (tr.querySelector('.apor')) tr.querySelector('.apor').textContent = fmt(ap);
        if (tr.querySelector('.pagar')) tr.querySelector('.pagar').textContent = fmt(pagar);
        
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
    document.getElementById('lbl_total_ajuste').textContent = fmt(Math.round(totalFacturado * (porcentaje / 100)));
    document.getElementById('tot_valor_llego').textContent = fmt(sumLleg);
    document.getElementById('tot_retencion').textContent = fmt(sumRet);
    document.getElementById('tot_4x1000').textContent = fmt(sumMil4);
    document.getElementById('tot_aporte').textContent = fmt(sumAp);
    document.getElementById('tot_ss').textContent = fmt(sumSS);
    document.getElementById('tot_prestamos').textContent = fmt(sumPrest);
    document.getElementById('tot_pagar').textContent = fmt(sumPagar);
    
    actualizarPanelFlotante();
}

// ===== FUNCIONES AUXILIARES =====
function obtenerNombreConductorDeFila(tr) {
    if (tr.classList.contains('fila-manual')) {
        const select = tr.querySelector('.conductor-select');
        return select ? select.value.trim() : '';
    } else {
        const link = tr.querySelector('.conductor-link');
        return link ? link.textContent.trim() : '';
    }
}

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
            const prestamoActual = PRESTAMOS_LIST.find(p => p.id === prestamoGuardado.id || p.name === prestamoGuardado.name);
            if (prestamoActual) {
                totalMostrar += prestamoGuardado.esManual ? prestamoGuardado.valorManual : prestamoActual.total;
                nombres.push(prestamoActual.name);
            }
        });
        
        const prestSpan = tr.querySelector('.prest');
        const selLabel = tr.querySelector('.selected-deudor');
        if (prestSpan) prestSpan.textContent = fmt(totalMostrar);
        if (selLabel) selLabel.textContent = nombres.length <= 2 ? nombres.join(', ') : nombres.slice(0,2).join(', ') + ' +' + (nombres.length-2) + ' más';
    });
}

function configurarEventosFila(tr) {
    const baseInput = tr.querySelector('.base-manual');
    const cta = tr.querySelector('input.cta');
    const ss = tr.querySelector('input.ss');
    const estadoPago = tr.querySelector('select.estado-pago');
    const btnEliminar = tr.querySelector('.btn-eliminar-manual');
    const btnPrest = tr.querySelector('.btn-prest');
    const conductorSelect = tr.querySelector('.conductor-select');
    const checkbox = tr.querySelector('.selector-conductor');
    
    if (checkbox) {
        checkbox.addEventListener('change', () => {
            if (checkbox.checked) tr.classList.add('fila-seleccionada');
            else tr.classList.remove('fila-seleccionada');
            actualizarPanelFlotante();
        });
    }
    
    if (baseInput) baseInput.addEventListener('input', () => { baseInput.value = fmt(toInt(baseInput.value)); recalc(); });
    if (cta) cta.addEventListener('change', () => { 
        const name = obtenerNombreConductorDeFila(tr);
        if (name) { accMap[name] = cta.value.trim(); setLS(ACC_KEY, accMap); }
    });
    if (ss) ss.addEventListener('input', () => { 
        const name = obtenerNombreConductorDeFila(tr);
        if (name) { ssMap[name] = toInt(ss.value); setLS(SS_KEY, ssMap); recalc(); }
    });
    if (estadoPago) estadoPago.addEventListener('change', () => { 
        const name = obtenerNombreConductorDeFila(tr);
        if (name) { estadoPagoMap[name] = estadoPago.value; setLS(ESTADO_PAGO_KEY, estadoPagoMap); }
    });
    if (btnPrest) btnPrest.addEventListener('click', () => openPrestModalForRow(tr));
    if (btnEliminar) btnEliminar.addEventListener('click', () => {
        manualRows = manualRows.filter(id => id !== tr.dataset.manualId);
        localStorage.setItem(MANUAL_ROWS_KEY, JSON.stringify(manualRows));
        tr.remove();
        recalc();
    });
}

function initializeExistingRows() {
    [...tbody.querySelectorAll('tr')].forEach(tr => {
        if (!tr.classList.contains('fila-manual')) configurarEventosFila(tr);
    });
    asignarPrestamosAFilas();
}

function filtrarConductores() {
    const texto = normalizarTexto(buscadorConductores.value);
    let visibles = 0;
    tbody.querySelectorAll('tr').forEach(tr => {
        const nombre = normalizarTexto(obtenerNombreConductorDeFila(tr));
        if (texto === '' || nombre.includes(texto)) {
            tr.style.display = '';
            visibles++;
        } else tr.style.display = 'none';
    });
    contadorConductores.textContent = `Mostrando ${visibles} de ${tbody.children.length} conductores`;
    clearBuscar.style.display = texto ? 'block' : 'none';
    recalc();
}

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', function() {
    console.log('Inicializando...');
    initializeExistingRows();
    asignarPrestamosAFilas();
    
    setTimeout(() => {
        console.log('Ejecutando recálculo inicial...');
        recalc();
    }, 200);
    
    document.getElementById('inp_porcentaje_ajuste').addEventListener('input', recalc);
    document.getElementById('inp_facturado').addEventListener('input', recalc);
    
    const buscador = document.getElementById('buscadorConductores');
    if (buscador) buscador.addEventListener('input', filtrarConductores);
    
    console.log('Inicialización completa');
});
</script>
</body>
</html>
<?php $conn->close(); ?>