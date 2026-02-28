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
        .conductor-link{cursor:pointer; color:#0d6efd; text-decoration:underline;}
        .estado-pagado { background-color: #f0fdf4 !important; border-left: 4px solid #22c55e; }
        .estado-pendiente { background-color: #fef2f2 !important; border-left: 4px solid #ef4444; }
        .estado-procesando { background-color: #fffbeb !important; border-left: 4px solid #f59e0b; }
        .estado-parcial { background-color: #eff6ff !important; border-left: 4px solid #3b82f6; }
        .fila-manual { background-color: #f0f9ff !important; border-left: 4px solid #0ea5e9; }
        .buscar-container { position: relative; }
        .buscar-clear { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); display: none; }
        #floatingPanel { box-shadow: 0 10px 40px rgba(0,0,0,0.15); z-index: 9999; }
        .fila-seleccionada { background-color: #f0f9ff !important; }
        .empresas-container { max-height: 150px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.75rem; background: white; }
        .empresa-checkbox { display: flex; align-items: center; gap: 0.5rem; padding: 0.25rem 0; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">
<header class="max-w-[1600px] mx-auto px-3 md:px-4 pt-6">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-5 py-4">
        <div class="flex justify-between items-center">
            <h2 class="text-xl font-bold">🧾 Ajuste de Pago <span class="bg-purple-600 text-white text-xs px-2 py-1 rounded-full">Múltiples Empresas</span></h2>
            <div class="flex gap-2">
                <button id="btnShowSaveCuenta" class="border border-amber-300 px-3 py-2 text-sm bg-amber-50 rounded">⭐ Guardar cuenta</button>
                <button id="btnShowGestorCuentas" class="border border-blue-300 px-3 py-2 text-sm bg-blue-50 rounded">📚 Cuentas</button>
            </div>
        </div>

        <form method="get" class="mt-4 grid grid-cols-12 gap-3">
            <div class="col-span-2">
                <label class="text-xs">Desde</label>
                <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="w-full border rounded-xl px-3 py-2">
            </div>
            <div class="col-span-2">
                <label class="text-xs">Hasta</label>
                <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="w-full border rounded-xl px-3 py-2">
            </div>
            <div class="col-span-6">
                <label class="text-xs">Empresas</label>
                <div class="empresas-container">
                    <?php
                    $resEmp2 = $conn->query("SELECT DISTINCT empresa FROM viajes ORDER BY empresa");
                    while ($e = $resEmp2->fetch_assoc()) {
                        $checked = in_array($e['empresa'], $empresasSeleccionadas) ? 'checked' : '';
                        echo "<div class='empresa-checkbox'>";
                        echo "<input type='checkbox' name='empresas[]' value='" . htmlspecialchars($e['empresa']) . "' $checked>";
                        echo "<label>" . htmlspecialchars($e['empresa']) . "</label>";
                        echo "</div>";
                    }
                    ?>
                </div>
                <div class="flex gap-2 mt-2">
                    <button type="button" id="btnSeleccionarTodas" class="text-xs px-2 py-1 bg-blue-50 rounded border">✓ Todas</button>
                    <button type="button" id="btnLimpiarTodas" class="text-xs px-2 py-1 bg-slate-50 rounded border">✗ Limpiar</button>
                </div>
            </div>
            <div class="col-span-2 flex items-end">
                <button class="w-full bg-blue-600 text-white py-2.5 rounded-xl">Aplicar</button>
            </div>
        </form>
    </div>
</header>

<main class="max-w-[1600px] mx-auto px-3 py-6">
    <!-- Panel de totales -->
    <div class="bg-white rounded-2xl border p-5 mb-5">
        <div class="grid grid-cols-7 gap-4">
            <div>
                <div class="text-xs text-slate-500">Conductores</div>
                <div class="text-lg font-semibold"><?= count($filas) ?></div>
            </div>
            <div class="col-span-2">
                <label class="text-xs">Cuenta de cobro</label>
                <input id="inp_facturado" type="text" class="w-full border rounded-xl px-3 py-2 text-right num" value="<?= number_format($total_facturado,0,',','.') ?>">
            </div>
            <div class="col-span-2">
                <label class="text-xs">Viajes manuales</label>
                <input id="inp_viajes_manuales" type="text" class="w-full border rounded-xl px-3 py-2 text-right bg-green-50" value="0" readonly>
            </div>
            <div>
                <label class="text-xs">% Ajuste</label>
                <input id="inp_porcentaje_ajuste" type="text" class="w-full border rounded-xl px-3 py-2 text-right" value="5">
            </div>
            <div>
                <div class="text-xs">Total ajuste</div>
                <div id="lbl_total_ajuste" class="text-lg font-semibold text-amber-600 num">0</div>
            </div>
        </div>
        <div class="mt-2 text-xs">
            <span class="font-semibold">Empresas:</span> <?= implode(' • ', $empresasSeleccionadas) ?: 'Todas' ?>
        </div>
    </div>

    <!-- Tabla principal -->
    <div class="bg-white rounded-2xl border p-5">
        <div class="flex justify-between mb-4">
            <div>
                <h3 class="text-lg font-semibold">Conductores</h3>
                <div id="contador-conductores" class="text-xs">Mostrando <?= count($filas) ?> conductores</div>
            </div>
            <div class="flex gap-3">
                <div class="buscar-container w-64">
                    <input id="buscadorConductores" type="text" placeholder="Buscar conductor..." class="w-full border rounded-lg px-3 py-2">
                    <button id="clearBuscar" class="buscar-clear">✕</button>
                </div>
                <button id="btnAddManual" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm">➕ Agregar manual</button>
            </div>
        </div>

        <div class="overflow-auto max-h-[70vh] border rounded-xl">
            <table class="min-w-[1200px] w-full text-sm">
                <thead class="bg-blue-600 text-white sticky top-0">
                <tr>
                    <th class="px-3 py-2 text-left">Conductor</th>
                    <th class="px-3 py-2 text-right">Base</th>
                    <th class="px-3 py-2 text-right">Ajuste</th>
                    <th class="px-3 py-2 text-right">Llegó</th>
                    <th class="px-3 py-2 text-right">Ret 3.5%</th>
                    <th class="px-3 py-2 text-right">4x1000</th>
                    <th class="px-3 py-2 text-right">Aporte</th>
                    <th class="px-3 py-2 text-right">Seg social</th>
                    <th class="px-3 py-2 text-right">Préstamos</th>
                    <th class="px-3 py-2 text-left">N° Cuenta</th>
                    <th class="px-3 py-2 text-right">A pagar</th>
                    <th class="px-3 py-2 text-center">Estado</th>
                    <th class="px-3 py-2 text-center"><input type="checkbox" id="selectAllCheckbox"></th>
                </tr>
                </thead>
                <tbody id="tbody">
                <?php foreach ($filas as $f): ?>
                    <tr data-conductor="<?= htmlspecialchars(mb_strtolower($f['nombre'])) ?>" data-base="<?= $f['total_bruto'] ?>">
                        <td class="px-3 py-2"><button class="conductor-link text-blue-600" data-nombre="<?= htmlspecialchars($f['nombre']) ?>"><?= htmlspecialchars($f['nombre']) ?></button></td>
                        <td class="px-3 py-2 text-right base"><?= number_format($f['total_bruto'],0,',','.') ?></td>
                        <td class="px-3 py-2 text-right ajuste">0</td>
                        <td class="px-3 py-2 text-right llego">0</td>
                        <td class="px-3 py-2 text-right ret">0</td>
                        <td class="px-3 py-2 text-right mil4">0</td>
                        <td class="px-3 py-2 text-right apor">0</td>
                        <td class="px-3 py-2 text-right"><input type="text" class="ss w-24 border rounded px-2 py-1 text-right" value=""></td>
                        <td class="px-3 py-2 text-right">
                            <span class="prest">0</span>
                            <button class="btn-prest text-xs border px-2 py-1 rounded ml-1">Sel</button>
                            <div class="text-[10px] selected-deudor"></div>
                        </td>
                        <td class="px-3 py-2"><input type="text" class="cta w-32 border rounded px-2 py-1" placeholder="N° cuenta"></td>
                        <td class="px-3 py-2 text-right pagar">0</td>
                        <td class="px-3 py-2 text-center">
                            <select class="estado-pago text-xs border rounded px-2 py-1">
                                <option value="">Sin estado</option>
                                <option value="pagado">✅ Pagado</option>
                                <option value="pendiente">❌ Pendiente</option>
                                <option value="procesando">🔄 Procesando</option>
                                <option value="parcial">⚠️ Parcial</option>
                            </select>
                        </td>
                        <td class="px-3 py-2 text-center"><input type="checkbox" class="selector-conductor"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-slate-50 font-semibold">
                <tr>
                    <td colspan="3">Totales</td>
                    <td class="text-right" id="tot_llego">0</td>
                    <td class="text-right" id="tot_ret">0</td>
                    <td class="text-right" id="tot_mil4">0</td>
                    <td class="text-right" id="tot_apor">0</td>
                    <td class="text-right" id="tot_ss">0</td>
                    <td class="text-right" id="tot_prest">0</td>
                    <td></td>
                    <td class="text-right" id="tot_pagar">0</td>
                    <td colspan="2"></td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
</main>

<!-- Panel flotante -->
<div id="floatingPanel" class="hidden fixed bg-white border rounded-xl shadow-lg" style="top:100px;left:100px;min-width:300px;">
    <div id="panelDragHandle" class="bg-blue-600 text-white px-4 py-3 rounded-t-xl cursor-move flex justify-between">
        <span>📊 Sumatoria <span id="selectedCount">0</span></span>
        <button id="closePanel">✕</button>
    </div>
    <div class="p-4">
        <div class="grid grid-cols-2 gap-3 mb-3">
            <div class="bg-slate-50 p-2 rounded"><span class="text-xs">Total pagar</span><div id="panelTotalPagar" class="font-bold">0</div></div>
            <div class="bg-slate-50 p-2 rounded"><span class="text-xs">Promedio</span><div id="panelPromedio" class="font-bold">0</div></div>
        </div>
        <div class="text-xs space-y-1">
            <div class="flex justify-between"><span>Llegó:</span><span id="panelLlego">0</span></div>
            <div class="flex justify-between"><span>Retención:</span><span id="panelRet">0</span></div>
            <div class="flex justify-between"><span>4x1000:</span><span id="panelMil4">0</span></div>
            <div class="flex justify-between"><span>Aporte:</span><span id="panelApor">0</span></div>
            <div class="flex justify-between"><span>SS:</span><span id="panelSS">0</span></div>
            <div class="flex justify-between"><span>Préstamos:</span><span id="panelPrest">0</span></div>
        </div>
    </div>
</div>

<!-- Modal Préstamos -->
<div id="prestModal" class="hidden fixed inset-0 bg-black/30 z-50">
    <div class="relative mx-auto my-8 max-w-2xl bg-white rounded-2xl p-4">
        <div class="flex justify-between border-b pb-2 mb-4">
            <h3 class="text-lg font-semibold">Seleccionar préstamos</h3>
            <button id="btnCloseModal" class="p-2">✕</button>
        </div>
        <input id="prestSearch" type="text" placeholder="Buscar..." class="w-full border rounded-xl px-3 py-2 mb-3">
        <div id="prestList" class="max-h-96 overflow-auto border rounded-xl"></div>
        <div class="flex justify-between mt-4 pt-4 border-t">
            <div>Seleccionados: <span id="selCount">0</span> - Total: <span id="selTotal">0</span></div>
            <div class="flex gap-2">
                <button id="btnCancel" class="border px-4 py-2 rounded-lg">Cancelar</button>
                <button id="btnAssign" class="bg-blue-600 text-white px-4 py-2 rounded-lg">Asignar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Viajes -->
<div id="viajesModal" class="viajes-backdrop">
    <div class="viajes-card">
        <div class="viajes-header flex justify-between">
            <h3 class="text-lg">Viajes de <span id="viajesTitle"></span></h3>
            <button id="viajesCloseBtn" class="border px-3 py-1 rounded">✕</button>
        </div>
        <div class="viajes-body" id="viajesContent">Cargando...</div>
    </div>
</div>

<script>
// ===== FUNCIONES SIMPLIFICADAS =====
function toInt(s) {
    if (typeof s === 'number') return Math.round(s);
    s = (s || '').toString().replace(/\./g, '').replace(/,/g, '').replace(/[^\d-]/g, '');
    return parseInt(s || '0', 10) || 0;
}

function fmt(n) {
    return (n || 0).toLocaleString('es-CO');
}

// ===== CÁLCULO PRINCIPAL =====
function recalcularTodo() {
    console.log('Recalculando...');
    const porcentaje = parseFloat(document.getElementById('inp_porcentaje_ajuste').value) || 0;
    const filas = document.querySelectorAll('#tbody tr');
    
    let totalManuales = 0;
    let sumLlego = 0, sumRet = 0, sumMil4 = 0, sumApor = 0, sumSS = 0, sumPrest = 0, sumPagar = 0;
    
    filas.forEach(tr => {
        if (tr.style.display === 'none') return;
        
        let base;
        if (tr.classList.contains('fila-manual')) {
            base = toInt(tr.querySelector('.base-manual')?.value);
            totalManuales += base;
        } else {
            base = toInt(tr.querySelector('.base')?.textContent);
        }
        
        const ajuste = Math.round(base * (porcentaje / 100));
        const llego = base - ajuste;
        const prest = toInt(tr.querySelector('.prest')?.textContent);
        const ret = Math.round(llego * 0.035);
        const mil4 = Math.round(llego * 0.004);
        const apor = Math.round(llego * 0.10);
        const ss = toInt(tr.querySelector('.ss')?.value);
        const pagar = llego - ret - mil4 - apor - ss - prest;
        
        if (tr.querySelector('.ajuste')) tr.querySelector('.ajuste').textContent = fmt(ajuste);
        if (tr.querySelector('.llego')) tr.querySelector('.llego').textContent = fmt(llego);
        if (tr.querySelector('.ret')) tr.querySelector('.ret').textContent = fmt(ret);
        if (tr.querySelector('.mil4')) tr.querySelector('.mil4').textContent = fmt(mil4);
        if (tr.querySelector('.apor')) tr.querySelector('.apor').textContent = fmt(apor);
        if (tr.querySelector('.pagar')) tr.querySelector('.pagar').textContent = fmt(pagar);
        
        sumLlego += llego;
        sumRet += ret;
        sumMil4 += mil4;
        sumApor += apor;
        sumSS += ss;
        sumPrest += prest;
        sumPagar += pagar;
    });
    
    document.getElementById('inp_viajes_manuales').value = fmt(totalManuales);
    document.getElementById('lbl_total_ajuste').textContent = fmt(Math.round((toInt(document.getElementById('inp_facturado').value) + totalManuales) * (porcentaje / 100)));
    document.getElementById('tot_llego').textContent = fmt(sumLlego);
    document.getElementById('tot_ret').textContent = fmt(sumRet);
    document.getElementById('tot_mil4').textContent = fmt(sumMil4);
    document.getElementById('tot_apor').textContent = fmt(sumApor);
    document.getElementById('tot_ss').textContent = fmt(sumSS);
    document.getElementById('tot_prest').textContent = fmt(sumPrest);
    document.getElementById('tot_pagar').textContent = fmt(sumPagar);
}

// ===== EVENTOS =====
document.addEventListener('DOMContentLoaded', function() {
    console.log('Inicializando...');
    
    // Eventos de cálculo
    document.getElementById('inp_porcentaje_ajuste').addEventListener('input', recalcularTodo);
    document.getElementById('inp_facturado').addEventListener('input', recalcularTodo);
    
    // Botones de empresas
    document.getElementById('btnSeleccionarTodas').addEventListener('click', function() {
        document.querySelectorAll('.empresa-checkbox input').forEach(cb => cb.checked = true);
    });
    
    document.getElementById('btnLimpiarTodas').addEventListener('click', function() {
        document.querySelectorAll('.empresa-checkbox input').forEach(cb => cb.checked = false);
    });
    
    // Eventos de fila
    document.querySelectorAll('#tbody tr').forEach(tr => {
        // Inputs que requieren recálculo
        const ss = tr.querySelector('.ss');
        if (ss) ss.addEventListener('input', recalcularTodo);
        
        // Botones de préstamos
        const btnPrest = tr.querySelector('.btn-prest');
        if (btnPrest) {
            btnPrest.addEventListener('click', function() {
                alert('Modal de préstamos - implementar después');
            });
        }
        
        // Links de viajes
        const link = tr.querySelector('.conductor-link');
        if (link) {
            link.addEventListener('click', function() {
                alert('Modal de viajes - implementar después');
            });
        }
    });
    
    // Primer cálculo
    setTimeout(recalcularTodo, 100);
    console.log('Listo');
});
</script>
</body>
</html>
<?php $conn->close(); ?>