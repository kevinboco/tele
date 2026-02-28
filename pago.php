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

/* ================= CREAR TABLAS CUENTAS GUARDADAS ================= */
// Tabla principal de cuentas (igual que antes)
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
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    usuario VARCHAR(100),
    INDEX idx_fecha (fecha_creacion),
    INDEX idx_pagado (pagado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Tabla pivote para la relación muchos-a-muchos con empresas
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
// Obtener lista de cuentas guardadas
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
            $datos_json = json_decode($row['datos_json'], true);
            $row['datos_json'] = ($datos_json === null) ? [] : $datos_json;
            
            // Convertir lista de empresas a array
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

// Guardar nueva cuenta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_cuenta') {
    header('Content-Type: application/json');
    
    $nombre = $conn->real_escape_string($_POST['nombre'] ?? '');
    $desde = $conn->real_escape_string($_POST['desde'] ?? '');
    $hasta = $conn->real_escape_string($_POST['hasta'] ?? '');
    $facturado = floatval($_POST['facturado'] ?? 0);
    $porcentaje_ajuste = floatval($_POST['porcentaje_ajuste'] ?? 0);
    $pagado = intval($_POST['pagado'] ?? 0);
    $datos_json = $conn->real_escape_string($_POST['datos_json'] ?? '{}');
    $empresas = isset($_POST['empresas']) ? json_decode($_POST['empresas'], true) : [];
    $usuario = $conn->real_escape_string($_SESSION['usuario'] ?? 'Sistema');
    
    if (empty($nombre) || empty($empresas)) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios']);
        exit;
    }
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    try {
        // Insertar en tabla principal
        $sql = "INSERT INTO cuentas_guardadas (nombre, desde, hasta, facturado, porcentaje_ajuste, pagado, datos_json, usuario) 
                VALUES ('$nombre', '$desde', '$hasta', $facturado, $porcentaje_ajuste, $pagado, '$datos_json', '$usuario')";
        
        if (!$conn->query($sql)) {
            throw new Exception("Error al guardar cuenta: " . $conn->error);
        }
        
        $cuenta_id = $conn->insert_id;
        
        // Insertar empresas relacionadas
        foreach ($empresas as $empresa) {
            $empresa_esc = $conn->real_escape_string($empresa);
            $sql_emp = "INSERT INTO cuentas_guardadas_empresas (cuenta_id, empresa_nombre) VALUES ($cuenta_id, '$empresa_esc')";
            if (!$conn->query($sql_emp)) {
                throw new Exception("Error al guardar empresa: " . $conn->error);
            }
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'id' => $cuenta_id, 'message' => 'Cuenta guardada exitosamente']);
        
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
    
    // La eliminación en cascada se encarga de borrar las empresas relacionadas
    $sql = "DELETE FROM cuentas_guardadas WHERE id = $id";
    $resultado = $conn->query($sql);
    
    echo json_encode(['success' => $resultado, 'message' => $resultado ? 'Cuenta eliminada' : 'Error al eliminar']);
    exit;
}

// Cargar cuenta específica
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
        $datos_json = json_decode($row['datos_json'], true);
        $row['datos_json'] = ($datos_json === null) ? [] : $datos_json;
        
        // Convertir lista de empresas a array
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

// Actualizar estado de pago
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_pagado') {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    $pagado = intval($_POST['pagado']);
    
    $sql = "UPDATE cuentas_guardadas SET pagado = $pagado WHERE id = $id";
    $resultado = $conn->query($sql);
    
    echo json_encode(['success' => $resultado, 'message' => $resultado ? 'Estado actualizado' : 'Error al actualizar']);
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
            <div class="flex items-center gap-2">
                <button id="btnShowSaveCuenta" class="rounded-lg border border-amber-300 px-3 py-2 text-sm bg-amber-50 hover:bg-amber-100">⭐ Guardar cuenta</button>
                <button id="btnShowGestorCuentas" class="rounded-lg border border-blue-300 px-3 py-2 text-sm bg-blue-50 hover:bg-blue-100">📚 Cuentas guardadas</button>
            </div>
        </div>

        <form method="get" class="mt-4 grid grid-cols-1 md:grid-cols-12 gap-3">
            <div class="md:col-span-2">
                <label class="text-xs font-medium">Desde</label>
                <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="w-full border rounded-xl px-3 py-2">
            </div>
            <div class="md:col-span-2">
                <label class="text-xs font-medium">Hasta</label>
                <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="w-full border rounded-xl px-3 py-2">
            </div>
            <div class="md:col-span-6">
                <label class="text-xs font-medium">Empresas</label>
                <div class="empresas-container">
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
                <button class="w-full bg-blue-600 text-white py-2.5 rounded-xl font-semibold shadow hover:bg-blue-700 transition">Aplicar</button>
            </div>
        </form>
    </div>
</header>

<main class="max-w-[1600px] mx-auto px-3 md:px-4 py-6 space-y-5">
    <!-- Panel de totales -->
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
            <table class="min-w-[1200px] w-full text-sm">
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
                    <th class="px-3 py-2 text-right">Préstamos</th>
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
                    <tr data-conductor="<?= $nombre_normalizado ?>" data-base="<?= $f['total_bruto'] ?>" data-row-index="<?= $contador_filas ?>">
                        <td class="px-3 py-2">
                            <button type="button" class="conductor-link text-blue-600 hover:underline" data-nombre="<?= htmlspecialchars($f['nombre']) ?>" title="Ver viajes">
                                <?= htmlspecialchars($f['nombre']) ?>
                            </button>
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
                        <td class="px-3 py-2 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <span class="num prest">0</span>
                                <button type="button" class="btn-prest text-xs px-2 py-1 rounded border border-slate-300 bg-slate-50 hover:bg-slate-100" data-nombre="<?= htmlspecialchars($f['nombre']) ?>">
                                    Sel
                                </button>
                            </div>
                            <div class="text-[10px] text-slate-500 text-right selected-deudor"></div>
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
                    <td class="px-3 py-2" colspan="2"></td>
                </tr>
                </tfoot>
            </table>
        </div>
    </section>
</main>

<!-- Panel flotante de selección -->
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
                <span>Valor que llegó:</span>
                <span id="panelLlego" class="num font-semibold">0</span>
            </div>
            <div class="flex justify-between">
                <span>Retención 3.5%:</span>
                <span id="panelRet" class="num">0</span>
            </div>
            <div class="flex justify-between">
                <span>4×1000:</span>
                <span id="panelMil4" class="num">0</span>
            </div>
            <div class="flex justify-between">
                <span>Aporte 10%:</span>
                <span id="panelApor" class="num">0</span>
            </div>
            <div class="flex justify-between">
                <span>Seg. social:</span>
                <span id="panelSS" class="num">0</span>
            </div>
            <div class="flex justify-between">
                <span>Préstamos:</span>
                <span id="panelPrest" class="num">0</span>
            </div>
        </div>
    </div>
</div>

<!-- Modal Préstamos -->
<div id="prestModal" class="hidden fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/30"></div>
    <div class="relative mx-auto my-8 max-w-2xl bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold">Seleccionar deudores</h3>
            <button id="btnCloseModal" class="p-2 rounded hover:bg-slate-100">✕</button>
        </div>
        <div class="p-4">
            <input id="prestSearch" type="text" placeholder="Buscar deudor..." class="w-full rounded-xl border border-slate-300 px-3 py-2 mb-3">
            <div id="prestList" class="max-h-96 overflow-auto border rounded-xl"></div>
        </div>
        <div class="px-5 py-4 border-t border-slate-200 flex items-center justify-between">
            <div>
                Seleccionados: <span id="selCount" class="font-semibold">0</span><br>
                Total: <span id="selTotal" class="num font-semibold">0</span>
            </div>
            <div class="flex gap-2">
                <button id="btnCancel" class="rounded-lg border border-slate-300 px-4 py-2 bg-white hover:bg-slate-50">Cancelar</button>
                <button id="btnAssign" class="rounded-lg border border-blue-600 px-4 py-2 bg-blue-600 text-white hover:bg-blue-700">Asignar</button>
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
<div id="saveCuentaModal" class="hidden fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/30"></div>
    <div class="relative mx-auto my-10 w-full max-w-lg bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
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
                <div id="cuenta_empresas_container" class="max-h-32 overflow-y-auto border border-slate-200 rounded-xl p-3 bg-slate-50 text-sm">
                    Cargando empresas...
                </div>
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
                <strong>📌 Nota:</strong> Se guardarán todos los datos: conductores, préstamos asignados, seguridad social, cuentas bancarias, estados de pago y filas manuales.
            </div>
        </div>
        <div class="px-5 py-4 border-t border-slate-200 flex items-center justify-end gap-2">
            <button id="btnCancelSaveCuenta" class="rounded-lg border border-slate-300 px-4 py-2 bg-white hover:bg-slate-50">Cancelar</button>
            <button id="btnDoSaveCuenta" class="rounded-lg border border-amber-500 text-white px-4 py-2 bg-amber-500 hover:bg-amber-600">Guardar en BD</button>
        </div>
    </div>
</div>

<!-- Modal Gestor de Cuentas -->
<div id="gestorCuentasModal" class="hidden fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/30"></div>
    <div class="relative mx-auto my-10 w-full max-w-5xl bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
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
                    <input id="buscaCuentaBD" type="text" placeholder="Buscar por nombre o empresa..." 
                           class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    <button id="clearBuscarBD" class="buscar-clear">✕</button>
                </div>
                
                <button id="btnRecargarCuentas" class="rounded-lg border border-blue-300 px-4 py-2 bg-blue-50 hover:bg-blue-100 whitespace-nowrap">
                    🔄 Recargar
                </button>
            </div>
            
            <div class="text-xs text-slate-500" id="contador-cuentas">
                Cargando cuentas desde Base de Datos...
            </div>
            
            <div class="overflow-auto max-h-[60vh] rounded-xl border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-blue-600 text-white">
                    <tr>
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
                        <tr>
                            <td colspan="7" class="px-3 py-8 text-center text-slate-500">
                                <div class="animate-pulse">Cargando cuentas...</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="px-5 py-4 border-t border-slate-200 flex justify-between items-center">
            <div class="text-sm text-slate-600">
                <span id="totalCuentasInfo">0 cuentas</span>
            </div>
            <button id="btnAddDesdeFiltro" class="rounded-lg border border-amber-300 px-4 py-2 text-sm bg-amber-50 hover:bg-amber-100">
                ⭐ Guardar rango actual
            </button>
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
const CONDUCTORES_LIST = <?= json_encode(array_map(fn($f)=>$f['nombre'],$filas), JSON_UNESCAPED_UNICODE); ?>;

// ===== FUNCIONES UTILITARIAS =====
function toInt(s) {
    if (typeof s === 'number') return Math.round(s);
    s = (s || '').toString().replace(/\./g, '').replace(/,/g, '').replace(/[^\d-]/g, '');
    return parseInt(s || '0', 10) || 0;
}

function fmt(n) {
    return (n || 0).toLocaleString('es-CO');
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

// ===== VARIABLES GLOBALES =====
let accMap = getLS(ACC_KEY);
let ssMap = getLS(SS_KEY);
let prestSel = getLS(PREST_SEL_KEY) || {};
let estadoPagoMap = getLS(ESTADO_PAGO_KEY) || {};
let manualRows = JSON.parse(localStorage.getItem(MANUAL_ROWS_KEY) || '[]');
let selectedConductors = JSON.parse(localStorage.getItem(SELECTED_CONDUCTORS_KEY) || '[]');

// ===== REFERENCIAS DOM =====
const tbody = document.getElementById('tbody');
const btnAddManual = document.getElementById('btnAddManual');
const floatingPanel = document.getElementById('floatingPanel');
const panelDragHandle = document.getElementById('panelDragHandle');
const closePanel = document.getElementById('closePanel');
const selectAllCheckbox = document.getElementById('selectAllCheckbox');
const buscadorConductores = document.getElementById('buscadorConductores');
const clearBuscar = document.getElementById('clearBuscar');
const contadorConductores = document.getElementById('contador-conductores');

// ===== FUNCIÓN PARA OBTENER NOMBRE DE CONDUCTOR =====
function obtenerNombreConductorDeFila(tr) {
    if (tr.classList.contains('fila-manual')) {
        const select = tr.querySelector('.conductor-select');
        return select ? select.value.trim() : '';
    } else {
        const link = tr.querySelector('.conductor-link');
        return link ? link.textContent.trim() : '';
    }
}

// ===== FUNCIÓN PARA APLICAR ESTADO DE PAGO =====
function aplicarEstadoFila(tr, estado) {
    tr.classList.remove('estado-pagado', 'estado-pendiente', 'estado-procesando', 'estado-parcial');
    if (estado) tr.classList.add(`estado-${estado}`);
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

// ===== FUNCIÓN PARA AGREGAR FILA MANUAL =====
function agregarFilaManual(manualIdFromLS = null) {
    const manualId = manualIdFromLS || ('manual_' + Date.now());
    const nuevaFila = document.createElement('tr');
    nuevaFila.className = 'fila-manual';
    nuevaFila.dataset.manualId = manualId;
    nuevaFila.dataset.conductor = '';
    
    nuevaFila.innerHTML = `
        <td class="px-3 py-2">
            <select class="conductor-select w-full max-w-[200px] rounded-lg border border-slate-300 px-2 py-1">
                <option value="">-- Seleccionar --</option>
                ${CONDUCTORES_LIST.map(c => `<option value="${c}">${c}</option>`).join('')}
            </select>
        </td>
        <td class="px-3 py-2 text-right">
            <input type="text" class="base-manual w-full max-w-[100px] rounded-lg border border-slate-300 px-2 py-1 text-right num" value="0">
        </td>
        <td class="px-3 py-2 text-right num ajuste">0</td>
        <td class="px-3 py-2 text-right num llego">0</td>
        <td class="px-3 py-2 text-right num ret">0</td>
        <td class="px-3 py-2 text-right num mil4">0</td>
        <td class="px-3 py-2 text-right num apor">0</td>
        <td class="px-3 py-2 text-right">
            <input type="text" class="ss w-full max-w-[80px] rounded-lg border border-slate-300 px-2 py-1 text-right num" value="">
        </td>
        <td class="px-3 py-2 text-right">
            <div class="flex items-center justify-end gap-2">
                <span class="num prest">0</span>
                <button type="button" class="btn-prest text-xs px-2 py-1 rounded border border-slate-300 bg-slate-50 hover:bg-slate-100">Sel</button>
            </div>
            <div class="text-[10px] text-slate-500 text-right selected-deudor"></div>
        </td>
        <td class="px-3 py-2">
            <input type="text" class="cta w-full max-w-[120px] rounded-lg border border-slate-300 px-2 py-1" placeholder="N° cuenta">
        </td>
        <td class="px-3 py-2 text-right num pagar">0</td>
        <td class="px-3 py-2 text-center">
            <select class="estado-pago w-full max-w-[100px] rounded-lg border border-slate-300 px-2 py-1 text-xs">
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
                <button type="button" class="btn-eliminar-manual text-xs px-2 py-1 rounded border border-rose-300 bg-rose-50 hover:bg-rose-100 text-rose-700">🗑️</button>
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
    recalcularTodo();
    filtrarConductores();
}

// ===== CONFIGURAR EVENTOS PARA FILA =====
function configurarEventosFila(tr) {
    const baseInput = tr.querySelector('.base-manual');
    const cta = tr.querySelector('.cta');
    const ss = tr.querySelector('.ss');
    const estadoPago = tr.querySelector('.estado-pago');
    const btnEliminar = tr.querySelector('.btn-eliminar-manual');
    const btnPrest = tr.querySelector('.btn-prest');
    const conductorSelect = tr.querySelector('.conductor-select');
    const checkbox = tr.querySelector('.selector-conductor');

    if (checkbox) {
        checkbox.addEventListener('change', () => {
            if (checkbox.checked) tr.classList.add('fila-seleccionada');
            else tr.classList.remove('fila-seleccionada');
            actualizarPanelFlotante();
            guardarSeleccionCheckboxes();
        });
    }

    if (baseInput) {
        baseInput.addEventListener('input', () => {
            baseInput.value = fmt(toInt(baseInput.value));
            recalcularTodo();
        });
    }

    if (cta) {
        const nombreConductor = conductorSelect ? conductorSelect.value : tr.querySelector('.conductor-link')?.textContent.trim();
        if (nombreConductor && accMap[nombreConductor]) {
            cta.value = accMap[nombreConductor];
        }
        
        cta.addEventListener('change', () => {
            const name = conductorSelect ? conductorSelect.value : tr.querySelector('.conductor-link')?.textContent.trim();
            if (name) {
                accMap[name] = cta.value.trim();
                setLS(ACC_KEY, accMap);
            }
        });
    }

    if (ss) {
        const nombreConductor = conductorSelect ? conductorSelect.value : tr.querySelector('.conductor-link')?.textContent.trim();
        if (nombreConductor && ssMap[nombreConductor]) {
            ss.value = fmt(ssMap[nombreConductor]);
        }
        
        ss.addEventListener('input', () => {
            const name = conductorSelect ? conductorSelect.value : tr.querySelector('.conductor-link')?.textContent.trim();
            if (name) {
                ssMap[name] = toInt(ss.value);
                setLS(SS_KEY, ssMap);
                recalcularTodo();
            }
        });
    }

    if (estadoPago) {
        const nombreConductor = conductorSelect ? conductorSelect.value : tr.querySelector('.conductor-link')?.textContent.trim();
        if (nombreConductor && estadoPagoMap[nombreConductor]) {
            estadoPago.value = estadoPagoMap[nombreConductor];
            aplicarEstadoFila(tr, estadoPagoMap[nombreConductor]);
        }
        
        estadoPago.addEventListener('change', () => {
            const name = conductorSelect ? conductorSelect.value : tr.querySelector('.conductor-link')?.textContent.trim();
            if (name) {
                estadoPagoMap[name] = estadoPago.value;
                setLS(ESTADO_PAGO_KEY, estadoPagoMap);
                aplicarEstadoFila(tr, estadoPago.value);
            }
        });
    }

    if (btnEliminar) {
        btnEliminar.addEventListener('click', () => {
            const manualId = tr.dataset.manualId;
            manualRows = manualRows.filter(id => id !== manualId);
            localStorage.setItem(MANUAL_ROWS_KEY, JSON.stringify(manualRows));
            tr.remove();
            recalcularTodo();
            actualizarPanelFlotante();
            filtrarConductores();
        });
    }

    if (btnPrest) {
        btnPrest.addEventListener('click', () => openPrestModalForRow(tr));
    }

    if (conductorSelect) {
        conductorSelect.addEventListener('change', () => {
            const newBaseName = conductorSelect.value;
            tr.dataset.conductor = normalizarTexto(newBaseName);
            
            if (cta && accMap[newBaseName]) cta.value = accMap[newBaseName];
            if (ss && ssMap[newBaseName]) ss.value = fmt(ssMap[newBaseName]);
            if (estadoPago && estadoPagoMap[newBaseName]) {
                estadoPago.value = estadoPagoMap[newBaseName];
                aplicarEstadoFila(tr, estadoPagoMap[newBaseName]);
            }
            
            asignarPrestamosAFilas();
            recalcularTodo();
            filtrarConductores();
        });
    }

    if (!conductorSelect) {
        const nombreConductor = tr.querySelector('.conductor-link')?.textContent.trim();
        if (nombreConductor) {
            if (cta && accMap[nombreConductor]) cta.value = accMap[nombreConductor];
            if (ss && ssMap[nombreConductor]) ss.value = fmt(ssMap[nombreConductor]);
        }
    }

    restaurarSeleccionCheckbox(tr);
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
    
    let totalPagar = 0, totalLlego = 0, totalRet = 0, totalMil4 = 0, totalApor = 0, totalSS = 0, totalPrest = 0;
    
    checkboxes.forEach(cb => {
        const tr = cb.closest('tr');
        if (!tr) return;
        
        totalPagar += toInt(tr.querySelector('.pagar')?.textContent || '0');
        totalLlego += toInt(tr.querySelector('.llego')?.textContent || '0');
        totalRet += toInt(tr.querySelector('.ret')?.textContent || '0');
        totalMil4 += toInt(tr.querySelector('.mil4')?.textContent || '0');
        totalApor += toInt(tr.querySelector('.apor')?.textContent || '0');
        totalPrest += toInt(tr.querySelector('.prest')?.textContent || '0');
        
        const ssInput = tr.querySelector('.ss');
        if (ssInput) totalSS += toInt(ssInput.value);
    });
    
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('panelTotalPagar').textContent = fmt(totalPagar);
    document.getElementById('panelPromedio').textContent = fmt(count > 0 ? Math.round(totalPagar / count) : 0);
    document.getElementById('panelLlego').textContent = fmt(totalLlego);
    document.getElementById('panelRet').textContent = fmt(totalRet);
    document.getElementById('panelMil4').textContent = fmt(totalMil4);
    document.getElementById('panelApor').textContent = fmt(totalApor);
    document.getElementById('panelSS').textContent = fmt(totalSS);
    document.getElementById('panelPrest').textContent = fmt(totalPrest);
}

function guardarSeleccionCheckboxes() {
    const seleccionados = [];
    document.querySelectorAll('#tbody .selector-conductor:checked').forEach(cb => {
        const tr = cb.closest('tr');
        const nombre = obtenerNombreConductorDeFila(tr);
        if (nombre) seleccionados.push(nombre);
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

// ===== FILTRO DE CONDUCTORES =====
function filtrarConductores() {
    const texto = normalizarTexto(buscadorConductores.value);
    let visibles = 0;
    
    tbody.querySelectorAll('tr').forEach(tr => {
        const nombre = normalizarTexto(obtenerNombreConductorDeFila(tr));
        if (texto === '' || nombre.includes(texto)) {
            tr.style.display = '';
            visibles++;
        } else {
            tr.style.display = 'none';
        }
    });
    
    contadorConductores.textContent = `Mostrando ${visibles} de ${tbody.children.length} conductores`;
    clearBuscar.style.display = texto ? 'block' : 'none';
    actualizarPanelFlotante();
}

// ===== CÁLCULO PRINCIPAL =====
function recalcularTodo() {
    const porcentaje = parseFloat(document.getElementById('inp_porcentaje_ajuste').value) || 0;
    const rows = [...tbody.querySelectorAll('tr')];
    
    let totalAutomaticos = <?= $total_facturado ?>;
    let totalManuales = 0;
    let sumLlego = 0, sumRet = 0, sumMil4 = 0, sumApor = 0, sumSS = 0, sumPrest = 0, sumPagar = 0;
    
    rows.forEach(tr => {
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
        const prest = toInt(tr.querySelector('.prest')?.textContent || '0');
        const ret = Math.round(llego * 0.035);
        const mil4 = Math.round(llego * 0.004);
        const apor = Math.round(llego * 0.10);
        const ss = toInt(tr.querySelector('.ss')?.value || '0');
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
    
    const totalFacturado = totalAutomaticos + totalManuales;
    document.getElementById('inp_facturado').value = fmt(totalFacturado);
    document.getElementById('inp_viajes_manuales').value = fmt(totalManuales);
    document.getElementById('lbl_total_ajuste').textContent = fmt(Math.round(totalFacturado * (porcentaje / 100)));
    document.getElementById('tot_llego').textContent = fmt(sumLlego);
    document.getElementById('tot_ret').textContent = fmt(sumRet);
    document.getElementById('tot_mil4').textContent = fmt(sumMil4);
    document.getElementById('tot_apor').textContent = fmt(sumApor);
    document.getElementById('tot_ss').textContent = fmt(sumSS);
    document.getElementById('tot_prest').textContent = fmt(sumPrest);
    document.getElementById('tot_pagar').textContent = fmt(sumPagar);
    
    actualizarPanelFlotante();
}

// ===== PANEL ARRASTRABLE =====
function hacerPanelArrastrable() {
    let isDragging = false, currentX, currentY, initialX, initialY, xOffset = 0, yOffset = 0;
    
    panelDragHandle.addEventListener('mousedown', (e) => {
        initialX = e.clientX - xOffset;
        initialY = e.clientY - yOffset;
        if (e.target === panelDragHandle || panelDragHandle.contains(e.target)) isDragging = true;
    });
    
    document.addEventListener('mousemove', (e) => {
        if (isDragging) {
            e.preventDefault();
            currentX = e.clientX - initialX;
            currentY = e.clientY - initialY;
            xOffset = currentX;
            yOffset = currentY;
            floatingPanel.style.transform = `translate3d(${currentX}px, ${currentY}px, 0)`;
        }
    });
    
    document.addEventListener('mouseup', () => isDragging = false);
}

// ===== MODAL PRÉSTAMOS =====
let currentRow = null, selectedIds = new Set(), filteredIdx = [];

function renderPrestList(filter = '') {
    const list = document.getElementById('prestList');
    list.innerHTML = '';
    const nf = normalizarTexto(filter);
    filteredIdx = [];
    
    PRESTAMOS_LIST.forEach((item, idx) => {
        if (nf && !normalizarTexto(item.name).includes(nf)) return;
        filteredIdx.push(idx);
        
        const row = document.createElement('div');
        row.className = 'flex justify-between items-center p-3 border-b hover:bg-slate-50';
        row.innerHTML = `
            <div class="flex items-center gap-3">
                <input type="checkbox" class="prest-checkbox rounded" data-id="${item.id}" ${selectedIds.has(item.id) ? 'checked' : ''}>
                <span class="text-sm">${item.name}</span>
            </div>
            <span class="num text-sm font-semibold">${fmt(item.total)}</span>
        `;
        
        const cb = row.querySelector('input');
        cb.addEventListener('change', () => {
            if (cb.checked) selectedIds.add(item.id);
            else selectedIds.delete(item.id);
            updateSelSummary();
        });
        
        list.appendChild(row);
    });
    
    updateSelSummary();
}

function updateSelSummary() {
    const arr = PRESTAMOS_LIST.filter(it => selectedIds.has(it.id));
    const total = arr.reduce((a, b) => a + (b.total || 0), 0);
    document.getElementById('selCount').textContent = arr.length;
    document.getElementById('selTotal').textContent = fmt(total);
}

function openPrestModalForRow(tr) {
    currentRow = tr;
    selectedIds = new Set();
    
    let baseName = obtenerNombreConductorDeFila(tr);
    if (!baseName) {
        Swal.fire({
            title: '⚠️ Selecciona un conductor',
            text: 'Primero debes seleccionar o ingresar el nombre del conductor',
            icon: 'warning',
            timer: 2000
        });
        return;
    }
    
    (prestSel[baseName] || []).forEach(p => {
        if (p.id !== undefined) selectedIds.add(Number(p.id));
    });
    
    document.getElementById('prestSearch').value = '';
    renderPrestList('');
    document.getElementById('prestModal').classList.remove('hidden');
}

function closePrestModal() {
    document.getElementById('prestModal').classList.add('hidden');
    currentRow = null;
    selectedIds.clear();
}

// ===== MODAL VIAJES =====
function abrirModalViajes(nombre) {
    document.getElementById('viajesTitle').textContent = nombre;
    document.getElementById('viajesModal').classList.add('show');
    
    const qs = new URLSearchParams({
        viajes_conductor: nombre,
        desde: '<?= $desde ?>',
        hasta: '<?= $hasta ?>',
        empresas: JSON.stringify(EMPRESAS_SELECCIONADAS)
    });
    
    fetch('<?= basename(__FILE__) ?>?' + qs.toString())
        .then(r => r.text())
        .then(html => document.getElementById('viajesContent').innerHTML = html)
        .catch(() => document.getElementById('viajesContent').innerHTML = '<p class="text-center text-red-600">Error cargando viajes</p>');
}

// ===== GESTIÓN DE CUENTAS GUARDADAS EN BD =====
const saveCuentaModal = document.getElementById('saveCuentaModal');
const btnShowSaveCuenta = document.getElementById('btnShowSaveCuenta');
const btnCloseSaveCuenta = document.getElementById('btnCloseSaveCuenta');
const btnCancelSaveCuenta = document.getElementById('btnCancelSaveCuenta');
const btnDoSaveCuenta = document.getElementById('btnDoSaveCuenta');
const gestorModal = document.getElementById('gestorCuentasModal');
const btnShowGestor = document.getElementById('btnShowGestorCuentas');
const btnCloseGestor = document.getElementById('btnCloseGestor');
const btnRecargarCuentas = document.getElementById('btnRecargarCuentas');
const btnAddDesdeFiltro = document.getElementById('btnAddDesdeFiltro');
const filtroEmpresaCuentas = document.getElementById('filtroEmpresaCuentas');
const filtroEstadoPagado = document.getElementById('filtroEstadoPagado');
const buscaCuentaBD = document.getElementById('buscaCuentaBD');
const clearBuscarBD = document.getElementById('clearBuscarBD');
const tbodyCuentasBD = document.getElementById('tbodyCuentasBD');
const contadorCuentas = document.getElementById('contador-cuentas');
const totalCuentasInfo = document.getElementById('totalCuentasInfo');

// Elementos del modal guardar
const iNombre = document.getElementById('cuenta_nombre');
const iRango = document.getElementById('cuenta_rango');
const iFacturado = document.getElementById('cuenta_facturado');
const iPorcentaje = document.getElementById('cuenta_porcentaje');
const iPagado = document.getElementById('cuenta_pagado');
const pagadoLabel = document.getElementById('pagadoLabel');
const empresasContainer = document.getElementById('cuenta_empresas_container');

// Actualizar label del switch
iPagado?.addEventListener('change', () => {
    if (pagadoLabel) {
        pagadoLabel.textContent = iPagado.checked ? 'PAGADO' : 'NO PAGADO';
        pagadoLabel.className = iPagado.checked 
            ? 'text-sm px-2 py-1 rounded-full bg-green-100 text-green-700' 
            : 'text-sm px-2 py-1 rounded-full bg-red-100 text-red-700';
    }
});

function openSaveCuenta() {
    const empresas = <?= json_encode($empresasSeleccionadas) ?>;
    
    if (empresas.length === 0) {
        Swal.fire({
            title: '⚠️ Selecciona empresas',
            text: 'Debes seleccionar al menos una empresa para guardar la cuenta',
            icon: 'warning'
        });
        return;
    }
    
    // Mostrar empresas seleccionadas
    empresasContainer.innerHTML = empresas.map(emp => 
        `<div class="py-1 px-2 bg-white rounded border border-slate-200 mb-1 text-sm">✓ ${emp}</div>`
    ).join('');
    
    iRango.value = '<?= $desde ?> → <?= $hasta ?>';
    iNombre.value = `${empresas[0]} ${iRango.value}`;
    iFacturado.value = document.getElementById('inp_facturado').value;
    iPorcentaje.value = document.getElementById('inp_porcentaje_ajuste').value;
    iPagado.checked = false;
    pagadoLabel.textContent = 'NO PAGADO';
    pagadoLabel.className = 'text-sm px-2 py-1 rounded-full bg-red-100 text-red-700';
    
    saveCuentaModal.classList.remove('hidden');
    setTimeout(() => iNombre.focus(), 100);
}

function closeSaveCuenta() {
    saveCuentaModal.classList.add('hidden');
}

btnShowSaveCuenta.addEventListener('click', openSaveCuenta);
btnCloseSaveCuenta.addEventListener('click', closeSaveCuenta);
btnCancelSaveCuenta.addEventListener('click', closeSaveCuenta);

btnDoSaveCuenta.addEventListener('click', async () => {
    const nombre = iNombre.value.trim();
    if (!nombre) {
        Swal.fire('⚠️ Nombre requerido', 'Debes ingresar un nombre para la cuenta', 'warning');
        return;
    }
    
    const empresas = <?= json_encode($empresasSeleccionadas) ?>;
    const desde = '<?= $desde ?>';
    const hasta = '<?= $hasta ?>';
    const facturado = toInt(iFacturado.value);
    const porcentaje = parseFloat(iPorcentaje.value) || 0;
    const pagado = iPagado.checked ? 1 : 0;
    
    // Obtener datos actuales
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
    
    const formData = new FormData();
    formData.append('accion', 'guardar_cuenta');
    formData.append('nombre', nombre);
    formData.append('desde', desde);
    formData.append('hasta', hasta);
    formData.append('facturado', facturado);
    formData.append('porcentaje_ajuste', porcentaje);
    formData.append('pagado', pagado);
    formData.append('empresas', JSON.stringify(empresas));
    formData.append('datos_json', JSON.stringify(datosParaGuardar));
    
    try {
        const response = await fetch('', { method: 'POST', body: formData });
        const resultado = await response.json();
        
        if (resultado.success) {
            Swal.fire({
                title: '✅ Cuenta guardada',
                text: 'La cuenta se guardó exitosamente en la base de datos',
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
        Swal.fire('❌ Error', error.message, 'error');
    }
});

// Renderizar cuentas en el gestor
async function renderCuentasBD() {
    const empresa = filtroEmpresaCuentas.value;
    const estado = filtroEstadoPagado.value;
    const filtro = (buscaCuentaBD.value || '').toLowerCase();
    
    try {
        const response = await fetch(`?obtener_cuentas=1&empresa=${encodeURIComponent(empresa)}&estado=${encodeURIComponent(estado)}`);
        const cuentas = await response.json();
        
        // Filtrar por búsqueda
        const cuentasFiltradas = cuentas.filter(c => 
            !filtro || 
            c.nombre.toLowerCase().includes(filtro) ||
            c.usuario?.toLowerCase().includes(filtro) ||
            (c.empresas || []).some(e => e.toLowerCase().includes(filtro))
        );
        
        contadorCuentas.textContent = `Mostrando ${cuentasFiltradas.length} de ${cuentas.length} cuentas`;
        totalCuentasInfo.textContent = `${cuentasFiltradas.length} cuentas`;
        
        if (cuentasFiltradas.length === 0) {
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
        cuentasFiltradas.forEach(cuenta => {
            const empresasStr = (cuenta.empresas || []).slice(0, 3).join(', ') + 
                ((cuenta.empresas || []).length > 3 ? ` +${(cuenta.empresas.length - 3)} más` : '');
            
            const totalPrestamos = cuenta.datos_json?.prestamos 
                ? Object.values(cuenta.datos_json.prestamos).reduce((a, b) => a + b.length, 0) 
                : 0;
            
            const totalManuales = cuenta.datos_json?.filasManuales?.length || 0;
            
            html += `
            <tr class="hover:bg-slate-50">
                <td class="px-3 py-3">
                    <div class="font-medium">${cuenta.nombre}</div>
                    <div class="text-xs text-slate-500">👤 ${cuenta.usuario || 'Sistema'}</div>
                </td>
                <td class="px-3 py-3">
                    <div class="text-xs max-w-[200px] truncate" title="${(cuenta.empresas || []).join(', ')}">
                        ${empresasStr || '—'}
                    </div>
                </td>
                <td class="px-3 py-3 text-xs">
                    ${cuenta.desde} → ${cuenta.hasta}
                </td>
                <td class="px-3 py-3 text-right num font-semibold">${fmt(cuenta.facturado || 0)}</td>
                <td class="px-3 py-3 text-center">
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs ${cuenta.pagado ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}">
                        ${cuenta.pagado ? '🟢 Pagado' : '🔴 No pagado'}
                    </span>
                    ${totalPrestamos > 0 ? `<span class="ml-1 text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full">${totalPrestamos} préstamos</span>` : ''}
                    ${totalManuales > 0 ? `<span class="ml-1 text-xs bg-amber-100 text-amber-700 px-2 py-1 rounded-full">${totalManuales} manuales</span>` : ''}
                </td>
                <td class="px-3 py-3 text-center text-xs text-slate-500">
                    ${new Date(cuenta.fecha_creacion).toLocaleDateString('es-CO')}
                </td>
                <td class="px-3 py-3 text-right">
                    <div class="inline-flex gap-2">
                        <button class="btnCargarCuenta border px-3 py-2 rounded bg-blue-50 hover:bg-blue-100 text-xs text-blue-700" 
                                data-id="${cuenta.id}" title="Cargar esta cuenta">
                            📂 Cargar
                        </button>
                        <button class="btnEliminarCuenta border px-3 py-2 rounded bg-rose-50 hover:bg-rose-100 text-xs text-rose-700" 
                                data-id="${cuenta.id}" title="Eliminar">
                            🗑️
                        </button>
                    </div>
                </td>
            </tr>`;
        });
        
        tbodyCuentasBD.innerHTML = html;
        
        // Eventos de los botones
        document.querySelectorAll('.btnCargarCuenta').forEach(btn => {
            btn.addEventListener('click', () => cargarCuentaCompletaBD(btn.dataset.id));
        });
        
        document.querySelectorAll('.btnEliminarCuenta').forEach(btn => {
            btn.addEventListener('click', () => eliminarCuentaBD(btn.dataset.id));
        });
        
    } catch (error) {
        console.error('Error:', error);
        tbodyCuentasBD.innerHTML = `
            <tr>
                <td colspan="7" class="px-3 py-8 text-center text-rose-600">
                    <div>❌ Error al cargar cuentas: ${error.message}</div>
                </td>
            </tr>`;
    }
}

// Cargar cuenta completa desde BD
async function cargarCuentaCompletaBD(id) {
    const confirmacion = await Swal.fire({
        title: '¿Cargar esta cuenta?',
        text: 'Se restaurarán todos los datos guardados',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, cargar',
        cancelButtonText: 'Cancelar'
    });
    
    if (!confirmacion.isConfirmed) return;
    
    try {
        const formData = new FormData();
        formData.append('accion', 'cargar_cuenta');
        formData.append('id', id);
        
        const response = await fetch('', { method: 'POST', body: formData });
        const resultado = await response.json();
        
        if (resultado.success) {
            const cuenta = resultado.cuenta;
            
            // Cargar datos básicos
            const datos = cuenta.datos_json || {};
            
            // Restaurar mapas
            prestSel = datos.prestamos || {};
            ssMap = datos.segSocial || {};
            accMap = datos.cuentasBancarias || {};
            estadoPagoMap = datos.estadosPago || {};
            
            setLS(PREST_SEL_KEY, prestSel);
            setLS(SS_KEY, ssMap);
            setLS(ACC_KEY, accMap);
            setLS(ESTADO_PAGO_KEY, estadoPagoMap);
            
            // Limpiar filas manuales existentes
            document.querySelectorAll('#tbody tr.fila-manual').forEach(tr => tr.remove());
            manualRows = [];
            
            // Cargar filas manuales
            if (datos.filasManuales && datos.filasManuales.length > 0) {
                datos.filasManuales.forEach(fila => {
                    agregarFilaManual();
                    const ultimaFila = tbody.querySelector('tr.fila-manual:last-child');
                    if (ultimaFila) {
                        const select = ultimaFila.querySelector('.conductor-select');
                        const baseInput = ultimaFila.querySelector('.base-manual');
                        const ctaInput = ultimaFila.querySelector('.cta');
                        const ssInput = ultimaFila.querySelector('.ss');
                        const estadoSelect = ultimaFila.querySelector('.estado-pago');
                        
                        if (select) select.value = fila.conductor;
                        if (baseInput) baseInput.value = fmt(fila.base);
                        if (ctaInput) ctaInput.value = fila.cuenta;
                        if (ssInput) ssInput.value = fmt(fila.segSocial);
                        if (estadoSelect) estadoSelect.value = fila.estado;
                        
                        if (fila.conductor) {
                            if (fila.cuenta) accMap[fila.conductor] = fila.cuenta;
                            if (fila.segSocial) ssMap[fila.conductor] = fila.segSocial;
                            if (fila.estado) estadoPagoMap[fila.conductor] = fila.estado;
                        }
                    }
                });
                localStorage.setItem(MANUAL_ROWS_KEY, JSON.stringify(manualRows));
            }
            
            // Aplicar a todas las filas
            setTimeout(() => {
                document.querySelectorAll('#tbody tr').forEach(tr => {
                    const nombre = obtenerNombreConductorDeFila(tr);
                    if (nombre) {
                        if (accMap[nombre]) {
                            const cta = tr.querySelector('.cta');
                            if (cta) cta.value = accMap[nombre];
                        }
                        if (ssMap[nombre]) {
                            const ss = tr.querySelector('.ss');
                            if (ss) ss.value = fmt(ssMap[nombre]);
                        }
                        if (estadoPagoMap[nombre]) {
                            const estado = tr.querySelector('.estado-pago');
                            if (estado) {
                                estado.value = estadoPagoMap[nombre];
                                aplicarEstadoFila(tr, estadoPagoMap[nombre]);
                            }
                        }
                    }
                });
                
                asignarPrestamosAFilas();
                recalcularTodo();
                closeGestor();
                
                Swal.fire({
                    title: '✅ Cuenta cargada',
                    text: `"${cuenta.nombre}" cargada exitosamente`,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
            }, 200);
        } else {
            throw new Error(resultado.message);
        }
    } catch (error) {
        Swal.fire('❌ Error', error.message, 'error');
    }
}

// Eliminar cuenta
async function eliminarCuentaBD(id) {
    const confirmacion = await Swal.fire({
        title: '¿Eliminar cuenta?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        confirmButtonColor: '#ef4444'
    });
    
    if (!confirmacion.isConfirmed) return;
    
    try {
        const formData = new FormData();
        formData.append('accion', 'eliminar_cuenta');
        formData.append('id', id);
        
        const response = await fetch('', { method: 'POST', body: formData });
        const resultado = await response.json();
        
        if (resultado.success) {
            await renderCuentasBD();
            Swal.fire('✅ Eliminada', 'Cuenta eliminada correctamente', 'success');
        } else {
            throw new Error(resultado.message);
        }
    } catch (error) {
        Swal.fire('❌ Error', error.message, 'error');
    }
}

// Gestor de cuentas
function openGestor() {
    // Cargar opciones de empresas para el filtro
    fetch('?obtener_cuentas=1')
        .then(r => r.json())
        .then(cuentas => {
            const empresasUnicas = new Set();
            cuentas.forEach(c => (c.empresas || []).forEach(e => empresasUnicas.add(e)));
            
            filtroEmpresaCuentas.innerHTML = '<option value="">Todas las empresas</option>';
            [...empresasUnicas].sort().forEach(emp => {
                filtroEmpresaCuentas.innerHTML += `<option value="${emp}">${emp}</option>`;
            });
        });
    
    renderCuentasBD();
    gestorModal.classList.remove('hidden');
    setTimeout(() => buscaCuentaBD.focus(), 100);
}

function closeGestor() {
    gestorModal.classList.add('hidden');
    buscaCuentaBD.value = '';
    filtroEmpresaCuentas.value = '';
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

btnAddDesdeFiltro.addEventListener('click', () => {
    closeGestor();
    setTimeout(() => openSaveCuenta(), 300);
});

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', function() {
    // Seleccionar/deseleccionar todas las empresas
    document.getElementById('btnSeleccionarTodas')?.addEventListener('click', () => {
        document.querySelectorAll('.empresa-checkbox input').forEach(cb => cb.checked = true);
    });
    
    document.getElementById('btnLimpiarTodas')?.addEventListener('click', () => {
        document.querySelectorAll('.empresa-checkbox input').forEach(cb => cb.checked = false);
    });
    
    // Configurar filas existentes
    document.querySelectorAll('#tbody tr').forEach(tr => {
        if (!tr.classList.contains('fila-manual')) {
            configurarEventosFila(tr);
        }
    });
    
    // Cargar filas manuales guardadas
    manualRows.forEach(id => agregarFilaManual(id));
    
    // Asignar préstamos guardados
    asignarPrestamosAFilas();
    
    // Panel arrastrable
    hacerPanelArrastrable();
    
    // Eventos de búsqueda
    buscadorConductores.addEventListener('input', filtrarConductores);
    clearBuscar.addEventListener('click', () => {
        buscadorConductores.value = '';
        filtrarConductores();
        buscadorConductores.focus();
    });
    
    // Botón agregar manual
    btnAddManual.addEventListener('click', () => agregarFilaManual());
    
    // Cerrar panel flotante
    closePanel.addEventListener('click', () => floatingPanel.classList.add('hidden'));
    
    // Select all checkbox
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', () => {
            document.querySelectorAll('#tbody .selector-conductor').forEach(cb => {
                cb.checked = selectAllCheckbox.checked;
                const tr = cb.closest('tr');
                if (tr) tr.classList.toggle('fila-seleccionada', selectAllCheckbox.checked);
            });
            actualizarPanelFlotante();
            guardarSeleccionCheckboxes();
        });
    }
    
    // Eventos de recálculo
    document.getElementById('inp_porcentaje_ajuste').addEventListener('input', recalcularTodo);
    
    // Modal préstamos
    document.getElementById('btnCloseModal').addEventListener('click', closePrestModal);
    document.getElementById('btnCancel').addEventListener('click', closePrestModal);
    
    document.getElementById('btnAssign').addEventListener('click', () => {
        if (!currentRow) return;
        
        let baseName = obtenerNombreConductorDeFila(currentRow);
        if (!baseName) return;
        
        const prestamosSeleccionados = PRESTAMOS_LIST.filter(it => selectedIds.has(it.id));
        const prestamosAGuardar = prestamosSeleccionados.map(it => ({
            id: it.id,
            name: it.name,
            totalActual: it.total,
            esManual: false,
            valorManual: null
        }));
        
        prestSel[baseName] = prestamosAGuardar;
        setLS(PREST_SEL_KEY, prestSel);
        
        asignarPrestamosAFilas();
        recalcularTodo();
        closePrestModal();
    });
    
    document.getElementById('prestSearch').addEventListener('input', (e) => {
        renderPrestList(e.target.value);
    });
    
    // Modal viajes
    document.querySelectorAll('#tbody .conductor-link').forEach(btn => {
        btn.addEventListener('click', () => abrirModalViajes(btn.textContent.trim()));
    });
    
    document.getElementById('viajesCloseBtn').addEventListener('click', () => {
        document.getElementById('viajesModal').classList.remove('show');
    });
    
    // Primer cálculo
    setTimeout(recalcularTodo, 100);
});
</script>
</body>
</html>
<?php $conn->close(); ?>