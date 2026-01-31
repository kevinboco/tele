<?php
include("nav.php");
session_start();

$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }
$conn->set_charset('utf8mb4');

// ================= AJAX PARA MANEJAR CUENTAS =================
// Esto debe estar AL INICIO del archivo, ANTES de cualquier salida HTML

// Verificar si es una solicitud AJAX específica para cuentas
if (isset($_GET['ajax_cuentas']) || (isset($_GET['obtener_cuentas']) && $_GET['obtener_cuentas'] == '1')) {
    header('Content-Type: application/json');
    
    // Crear tabla si no existe primero
    $conn->query("
    CREATE TABLE IF NOT EXISTS cuentas_guardadas (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nombre VARCHAR(255) NOT NULL,
        empresa VARCHAR(100) NOT NULL,
        desde DATE NOT NULL,
        hasta DATE NOT NULL,
        facturado DECIMAL(15,2) NOT NULL,
        porcentaje_ajuste DECIMAL(5,2) NOT NULL,
        datos_json LONGTEXT NOT NULL,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        usuario VARCHAR(100),
        INDEX idx_empresa (empresa),
        INDEX idx_fecha (fecha_creacion)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    // Obtener empresa del parámetro
    $empresa = $_GET['empresa'] ?? '';
    
    // Preparar consulta
    $sql = "SELECT id, nombre, empresa, desde, hasta, facturado, porcentaje_ajuste, 
                   datos_json, fecha_creacion, usuario 
            FROM cuentas_guardadas";
    
    if (!empty($empresa)) {
        $empresa_esc = $conn->real_escape_string($empresa);
        $sql .= " WHERE empresa = '$empresa_esc'";
    } else {
        // Si no se especifica empresa, mostrar todas las cuentas
        $sql .= " WHERE 1=1";
    }
    
    $sql .= " ORDER BY fecha_creacion DESC";
    
    $result = $conn->query($sql);
    $cuentas = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Decodificar JSON
            $datos_json = json_decode($row['datos_json'], true);
            if ($datos_json === null) {
                $row['datos_json'] = [];
            } else {
                $row['datos_json'] = $datos_json;
            }
            $cuentas[] = $row;
        }
    }
    
    echo json_encode($cuentas, JSON_UNESCAPED_UNICODE);
    exit;
}

// Procesar acciones POST (para AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    
    if ($accion === 'guardar_cuenta') {
        header('Content-Type: application/json');
        $datos = json_decode($_POST['datos'], true);
        
        $cuenta_completa = [
            'nombre' => $datos['nombre'],
            'empresa' => $datos['empresa'],
            'desde' => $datos['desde'],
            'hasta' => $datos['hasta'],
            'facturado' => $datos['facturado'],
            'porcentaje' => $datos['porcentaje'],
            'datos_json' => $datos['datos_json']
        ];
        
        // Primero crear tabla si no existe
        $conn->query("
        CREATE TABLE IF NOT EXISTS cuentas_guardadas (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nombre VARCHAR(255) NOT NULL,
            empresa VARCHAR(100) NOT NULL,
            desde DATE NOT NULL,
            hasta DATE NOT NULL,
            facturado DECIMAL(15,2) NOT NULL,
            porcentaje_ajuste DECIMAL(5,2) NOT NULL,
            datos_json LONGTEXT NOT NULL,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            usuario VARCHAR(100),
            INDEX idx_empresa (empresa),
            INDEX idx_fecha (fecha_creacion)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        
        // Guardar en BD
        $nombre = $conn->real_escape_string($datos['nombre']);
        $empresa = $conn->real_escape_string($datos['empresa']);
        $desde = $conn->real_escape_string($datos['desde']);
        $hasta = $conn->real_escape_string($datos['hasta']);
        $facturado = floatval($datos['facturado']);
        $porcentaje = floatval($datos['porcentaje']);
        $datos_json = $conn->real_escape_string(json_encode($datos['datos_json'], JSON_UNESCAPED_UNICODE));
        
        // Puedes agregar usuario si tienes sistema de login
        $usuario = isset($_SESSION['usuario']) ? $conn->real_escape_string($_SESSION['usuario']) : 'anonimo';
        
        $sql = "INSERT INTO cuentas_guardadas (nombre, empresa, desde, hasta, facturado, porcentaje_ajuste, datos_json, usuario) 
                VALUES ('$nombre', '$empresa', '$desde', '$hasta', $facturado, $porcentaje, '$datos_json', '$usuario')";
        
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'id' => $conn->insert_id, 'message' => 'Cuenta guardada en base de datos']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar en base de datos: ' . $conn->error]);
        }
        exit;
    }
    
    if ($accion === 'eliminar_cuenta') {
        header('Content-Type: application/json');
        $id = intval($_POST['id']);
        
        $sql = "DELETE FROM cuentas_guardadas WHERE id = $id";
        $resultado = $conn->query($sql);
        
        echo json_encode(['success' => $resultado, 'message' => $resultado ? 'Cuenta eliminada' : 'Error al eliminar']);
        exit;
    }
    
    if ($accion === 'cargar_cuenta') {
        header('Content-Type: application/json');
        $id = intval($_POST['id']);
        $sql = "SELECT * FROM cuentas_guardadas WHERE id = $id";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $datos_json = json_decode($row['datos_json'], true);
            if ($datos_json === null) {
                $row['datos_json'] = [];
            } else {
                $row['datos_json'] = $datos_json;
            }
            echo json_encode(['success' => true, 'cuenta' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Cuenta no encontrada']);
        }
        exit;
    }
}

// ================= CREAR TABLA SI NO EXISTE =================
$conn->query("
CREATE TABLE IF NOT EXISTS cuentas_guardadas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(255) NOT NULL,
    empresa VARCHAR(100) NOT NULL,
    desde DATE NOT NULL,
    hasta DATE NOT NULL,
    facturado DECIMAL(15,2) NOT NULL,
    porcentaje_ajuste DECIMAL(5,2) NOT NULL,
    datos_json LONGTEXT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    usuario VARCHAR(100),
    INDEX idx_empresa (empresa),
    INDEX idx_fecha (fecha_creacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

/* ================= Helpers ================= */
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
    $empresa = $conn->real_escape_string($_GET['empresa'] ?? '');

    $sql = "SELECT fecha, ruta, empresa, tipo_vehiculo
            FROM viajes
            WHERE nombre = '$nombre'
              AND fecha BETWEEN '$desde' AND '$hasta'";
    if ($empresa !== '') {
        $sql .= " AND empresa = '$empresa'";
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

            $rowsHTML .= "<tr class='row-viaje hover:bg-blue-50 transition-colors cat-$cat'>
                    <td class='px-3 py-2'>".htmlspecialchars($r['fecha'])."</td>
                    <td class='px-3 py-2'>".htmlspecialchars($ruta)."</td>
                    <td class='px-3 py-2'>".htmlspecialchars($r['empresa'])."</td>
                    <td class='px-3 py-2'>".htmlspecialchars($vehiculo)."</td>
                  </tr>";
        }
    } else {
        $rowsHTML .= "<tr><td colspan='4' class='px-3 py-4 text-center text-slate-500'>Sin viajes en el rango/empresa.</td></tr>";
    }

    echo $rowsHTML;
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
                <label class="block"><span class="block text-sm font-medium mb-1">Empresa</span>
                    <select name="empresa" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                        <option value="">-- Todas --</option>
                        <?php foreach($empresas as $e): ?>
                            <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="w-full rounded-xl bg-blue-600 text-white py-2.5 font-semibold shadow">Continuar</button>
            </form>
        </div>
    </div>
    </body></html>
    <?php exit;
}

/* ================= Parámetros ================= */
$desde = $_GET['desde'];
$hasta = $_GET['hasta'];
$empresaFiltro = $_GET['empresa'] ?? "";
$empresaFiltroEsc = $conn->real_escape_string($empresaFiltro);

/* ================= CARGAR TARIFAS ================= */
$tarifas = [];
if ($empresaFiltro !== "") {
    $resT = $conn->query("SELECT * FROM tarifas WHERE empresa='".$empresaFiltroEsc."'");
    if ($resT) {
        while($r = $resT->fetch_assoc()) {
            $tarifa_normalizada = [];
            foreach ($r as $key => $value) {
                $tarifa_normalizada[strtolower($key)] = $value;
            }
            $tarifas[$r['tipo_vehiculo']] = $tarifa_normalizada;
        }
    }
}

/* ================= Viajes del rango ================= */
$sqlV = "SELECT nombre, ruta, empresa, tipo_vehiculo
         FROM viajes
         WHERE fecha BETWEEN '$desde' AND '$hasta'";
if ($empresaFiltro !== "") {
    $sqlV .= " AND empresa = '$empresaFiltroEsc'";
}
$resV = $conn->query($sqlV);

$contadores = [];
if ($resV) {
    while ($row = $resV->fetch_assoc()) {
        $nombre = $row['nombre'];
        $ruta = $row['ruta'];
        $vehiculo = $row['tipo_vehiculo'];
        
        if (!isset($contadores[$nombre])) {
            $contadores[$nombre] = ['vehiculo' => $vehiculo];
            foreach ($todas_clasificaciones as $clas) {
                $clas_normalizada = strtolower($clas);
                $contadores[$nombre][$clas_normalizada] = 0;
            }
        }
        
        $key = mb_strtolower(trim($ruta . '|' . $vehiculo), 'UTF-8');
        $clasif = isset($clasificaciones[$key]) ? strtolower($clasificaciones[$key]) : '';
        
        if ($clasif !== '' && in_array($clasif, $todas_clasificaciones)) {
            if (isset($contadores[$nombre][$clasif])) {
                $contadores[$nombre][$clasif]++;
            }
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

if ($empresaFiltro !== "") {
    $qPrest .= " AND empresa = '".$empresaFiltroEsc."'";
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
    $veh = $v['vehiculo'];
    $t = $tarifas[$veh] ?? [];
    
    $total = 0;
    
    foreach ($todas_clasificaciones as $clas) {
        $clas_normalizada = strtolower($clas);
        $cantidad = $v[$clas_normalizada] ?? 0;
        
        if ($cantidad > 0) {
            $precio = 0;
            
            if (isset($t[$clas_normalizada])) {
                $precio = (float)$t[$clas_normalizada];
            } else {
                $clas_con_guion = str_replace(' ', '_', $clas_normalizada);
                if (isset($t[$clas_con_guion])) {
                    $precio = (float)$t[$clas_con_guion];
                } else {
                    $clas_con_espacio = str_replace('_', ' ', $clas_normalizada);
                    if (isset($t[$clas_con_espacio])) {
                        $precio = (float)$t[$clas_con_espacio];
                    }
                }
            }
            
            $subtotal = $cantidad * $precio;
            $total += $subtotal;
        }
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
    <title>Ajuste de Pago (Base de Datos)</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
        
        /* Badge BD */
        .bd-badge { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">
<header class="max-w-[1600px] mx-auto px-3 md:px-4 pt-6">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-5 py-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <h2 class="text-xl md:text-2xl font-bold">🧾 Ajuste de Pago <span class="bd-badge text-xs px-2 py-1 rounded-full ml-2">Base de Datos</span></h2>
            <div class="flex items-center gap-2">
                <button id="btnShowSaveCuenta" class="rounded-lg border border-amber-300 px-3 py-2 text-sm bg-amber-50 hover:bg-amber-100">⭐ Guardar como cuenta</button>
                <button id="btnShowGestorCuentas" class="rounded-lg border border-blue-300 px-3 py-2 text-sm bg-blue-50 hover:bg-blue-100">📚 Cuentas guardadas</button>
            </div>
        </div>

        <!-- filtros -->
        <form id="formFiltros" class="mt-3 grid grid-cols-1 md:grid-cols-6 gap-3" method="get">
            <label class="block md:col-span-1">
                <span class="block text-xs font-medium mb-1">Desde</span>
                <input id="inp_desde" type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" required class="w-full rounded-xl border border-slate-300 px-3 py-2">
            </label>
            <label class="block md:col-span-1">
                <span class="block text-xs font-medium mb-1">Hasta</span>
                <input id="inp_hasta" type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" required class="w-full rounded-xl border border-slate-300 px-3 py-2">
            </label>
            <label class="block md:col-span-2">
                <span class="block text-xs font-medium mb-1">Empresa</span>
                <select id="sel_empresa" name="empresa" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    <option value="">-- Todas --</option>
                    <?php
                    $resEmp2 = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
                    if ($resEmp2) while ($e = $resEmp2->fetch_assoc()) {
                        $sel = ($empresaFiltro==$e['empresa'])?'selected':''; ?>
                        <option value="<?= htmlspecialchars($e['empresa']) ?>" <?= $sel ?>><?= htmlspecialchars($e['empresa']) ?></option>
                    <?php } ?>
                </select>
            </label>
            <div class="md:col-span-2 flex md:items-end">
                <button class="w-full rounded-xl bg-blue-600 text-white py-2.5 font-semibold shadow">Aplicar</button>
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
            <h3 class="text-lg font-semibold">⭐ Guardar cuenta de cobro en Base de Datos</h3>
            <button id="btnCloseSaveCuenta" class="p-2 rounded hover:bg-slate-100" title="Cerrar">✕</button>
        </div>
        <div class="p-5 space-y-3">
            <label class="block">
                <span class="block text-xs font-medium mb-1">Nombre de la cuenta *</span>
                <input id="cuenta_nombre" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2" placeholder="Ej: Hospital Sep 2025" required>
            </label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <label class="block">
                    <span class="block text-xs font-medium mb-1">Empresa</span>
                    <input id="cuenta_empresa" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2" readonly>
                </label>
                <label class="block">
                    <span class="block text-xs font-medium mb-1">Rango</span>
                    <input id="cuenta_rango" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2" readonly>
                </label>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <label class="block">
                    <span class="block text-xs font-medium mb-1">Facturado</span>
                    <input id="cuenta_facturado" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right num" required>
                </label>
                <label class="block">
                    <span class="block text-xs font-medium mb-1">Porcentaje ajuste</span>
                    <input id="cuenta_porcentaje" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right num" required>
                </label>
            </div>
            <div class="text-xs text-slate-500 p-3 bg-blue-50 rounded-lg border border-blue-100">
                <strong>💾 Se guardará en Base de Datos:</strong>
                <ul class="mt-1 space-y-1">
                    <li>✓ Préstamos asignados a cada conductor</li>
                    <li>✓ Seguridad social de cada conductor</li>
                    <li>✓ Cuentas bancarias</li>
                    <li>✓ Estados de pago</li>
                    <li>✓ Filas manuales agregadas</li>
                </ul>
                <div class="mt-2 text-blue-600">
                    <i class="mr-1">📡</i> Accesible desde cualquier dispositivo
                </div>
            </div>
        </div>
        <div class="px-5 py-4 border-t border-slate-200 flex items-center justify-end gap-2">
            <button id="btnCancelSaveCuenta" class="rounded-lg border border-slate-300 px-4 py-2 bg-white hover:bg-slate-50">Cancelar</button>
            <button id="btnDoSaveCuenta" class="rounded-lg border border-amber-500 text-white px-4 py-2 bg-amber-500 hover:bg-amber-600">💾 Guardar en BD</button>
        </div>
    </div>
</div>

<!-- ===== Modal GESTOR DE CUENTAS ===== -->
<div id="gestorCuentasModal" class="hidden fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/30"></div>
    <div class="relative mx-auto my-10 w-full max-w-4xl bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold">📚 Cuentas guardadas en Base de Datos</h3>
            <button id="btnCloseGestor" class="p-2 rounded hover:bg-slate-100" title="Cerrar">✕</button>
        </div>
        <div class="p-4 space-y-3">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div class="text-sm">Empresa actual: <strong id="lblEmpresaActual" class="text-blue-600"></strong></div>
                <div class="flex gap-2">
                    <input id="buscaCuenta" type="text" placeholder="Buscar por nombre…" class="w-full md:w-64 rounded-xl border border-slate-300 px-3 py-2">
                    <button id="btnRecargarCuentas" class="rounded-lg border border-slate-300 px-3 py-2 text-sm bg-slate-50 hover:bg-slate-100" title="Recargar">
                        🔄
                    </button>
                </div>
            </div>
            <div class="overflow-auto max-h-[60vh] rounded-xl border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-blue-600 text-white">
                    <tr>
                        <th class="px-3 py-2 text-left">Nombre</th>
                        <th class="px-3 py-2 text-left">Rango</th>
                        <th class="px-3 py-2 text-right">Facturado</th>
                        <th class="px-3 py-2 text-right">% Ajuste</th>
                        <th class="px-3 py-2 text-center">Datos</th>
                        <th class="px-3 py-2 text-center">Fecha</th>
                        <th class="px-3 py-2 text-right">Acciones</th>
                    </tr>
                    </thead>
                    <tbody id="tbodyCuentas" class="divide-y divide-slate-100 bg-white">
                        <tr>
                            <td colspan="7" class="px-3 py-4 text-center text-slate-500">
                                <div class="animate-pulse">Cargando cuentas desde Base de Datos...</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="px-5 py-4 border-t border-slate-200 text-right">
            <button id="btnAddDesdeFiltro" class="rounded-lg border border-amber-300 px-3 py-2 text-sm bg-amber-50 hover:bg-amber-100">⭐ Guardar rango actual</button>
        </div>
    </div>
</div>

<script>
    // ===== Variables globales =====
    const PRESTAMOS_LIST = <?php echo json_encode($prestamosList, JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK); ?>;
    const CONDUCTORES_LIST = <?= json_encode(array_map(fn($f)=>$f['nombre'],$filas), JSON_UNESCAPED_UNICODE); ?>;
    
    // Claves de persistencia local (para datos temporales durante la sesión)
    const COMPANY_SCOPE = <?= json_encode(($empresaFiltro ?: '__todas__')) ?>;
    const ACC_KEY   = 'cuentas:'+COMPANY_SCOPE;
    const SS_KEY    = 'seg_social:'+COMPANY_SCOPE;
    const PREST_SEL_KEY = 'prestamo_sel_multi:v4:'+COMPANY_SCOPE;
    const ESTADO_PAGO_KEY = 'estado_pago:'+COMPANY_SCOPE;
    const MANUAL_ROWS_KEY = 'filas_manuales:'+COMPANY_SCOPE;
    const SELECTED_CONDUCTORS_KEY = 'conductores_seleccionados:'+COMPANY_SCOPE;

    const toInt = (s)=>{ 
        if(typeof s==='number') return Math.round(s); 
        s=(s||'').toString().replace(/\./g,'').replace(/,/g,'').replace(/[^\d\-]/g,''); 
        return parseInt(s||'0',10)||0; 
    };
    
    const fmt = (n)=> (n||0).toLocaleString('es-CO');
    const getLS=(k)=>{try{return JSON.parse(localStorage.getItem(k)||'{}')}catch{return{}}};
    const setLS=(k,v)=> localStorage.setItem(k, JSON.stringify(v));

    // ===== Variables de estado =====
    let accMap = getLS(ACC_KEY);
    let ssMap  = getLS(SS_KEY);
    let prestSel = getLS(PREST_SEL_KEY); 
    if(!prestSel || typeof prestSel!=='object') prestSel = {};
    
    let estadoPagoMap = getLS(ESTADO_PAGO_KEY) || {};
    let manualRows = JSON.parse(localStorage.getItem(MANUAL_ROWS_KEY) || '[]');
    let selectedConductors = JSON.parse(localStorage.getItem(SELECTED_CONDUCTORS_KEY) || '[]');
    let cuentasBD = []; // Para almacenar cuentas cargadas desde BD

    // ===== Elementos DOM =====
    const tbody = document.getElementById('tbody');
    const btnAddManual = document.getElementById('btnAddManual');
    const floatingPanel = document.getElementById('floatingPanel');
    const panelDragHandle = document.getElementById('panelDragHandle');
    const closePanel = document.getElementById('closePanel');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');

    // ===== FUNCIONES PARA INTERACCIÓN CON BASE DE DATOS =====

    /**
     * Obtener cuentas desde la base de datos
     */
    async function obtenerCuentasDesdeBD() {
        const empresa = document.getElementById('sel_empresa').value;
        
        try {
            // Usar el endpoint correcto para AJAX
            const response = await fetch(`?ajax_cuentas=1&obtener_cuentas=1&empresa=${encodeURIComponent(empresa)}`);
            
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }
            
            const cuentas = await response.json();
            cuentasBD = cuentas;
            console.log('Cuentas obtenidas desde BD:', cuentas); // Para depuración
            return cuentas;
        } catch (error) {
            console.error('Error al obtener cuentas desde BD:', error);
            return [];
        }
    }

    /**
     * Guardar cuenta en base de datos
     */
    async function guardarCuentaEnBD(datosCuenta) {
        try {
            const formData = new FormData();
            formData.append('accion', 'guardar_cuenta');
            formData.append('datos', JSON.stringify(datosCuenta));
            
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            const resultado = await response.json();
            return resultado;
        } catch (error) {
            console.error('Error al guardar cuenta en BD:', error);
            return { success: false, message: 'Error de conexión' };
        }
    }

    /**
     * Cargar cuenta específica desde BD
     */
    async function cargarCuentaDesdeBD(id) {
        try {
            const formData = new FormData();
            formData.append('accion', 'cargar_cuenta');
            formData.append('id', id);
            
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            const resultado = await response.json();
            return resultado;
        } catch (error) {
            console.error('Error al cargar cuenta desde BD:', error);
            return { success: false, message: 'Error de conexión' };
        }
    }

    /**
     * Eliminar cuenta de la BD
     */
    async function eliminarCuentaDeBD(id) {
        try {
            const formData = new FormData();
            formData.append('accion', 'eliminar_cuenta');
            formData.append('id', id);
            
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            const resultado = await response.json();
            return resultado;
        } catch (error) {
            console.error('Error al eliminar cuenta de BD:', error);
            return { success: false, message: 'Error de conexión' };
        }
    }

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

    // ===== Datos para el modal de viajes =====
    const RANGO_DESDE = <?= json_encode($desde) ?>;
    const RANGO_HASTA = <?= json_encode($hasta) ?>;
    const RANGO_EMP   = <?= json_encode($empresaFiltro) ?>;

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

    function abrirModalViajes(nombreInicial){
        viajesRango.textContent   = RANGO_DESDE + " → " + RANGO_HASTA;
        viajesEmpresa.textContent = (RANGO_EMP && RANGO_EMP !== "") ? RANGO_EMP : "Todas las empresas";

        initViajesSelect(nombreInicial);

        viajesModal.classList.add('show');

        loadViajes(nombreInicial);
    }

    function cerrarModalViajes(){
        viajesModal.classList.remove('show');
        viajesContent.innerHTML = '';
        viajesConductorActual = null;
    }

    function loadViajes(nombre) {
        viajesContent.innerHTML = '<p class="text-center m-0 animate-pulse">Cargando…</p>';
        viajesConductorActual = nombre;
        viajesTitle.textContent = nombre;

        const qs = new URLSearchParams({
            viajes_conductor: nombre,
            desde: RANGO_DESDE,
            hasta: RANGO_HASTA,
            empresa: RANGO_EMP
        });

        fetch('<?= basename(__FILE__) ?>?' + qs.toString())
            .then(r => r.text())
            .then(html => {
                viajesContent.innerHTML = html;
            })
            .catch(() => {
                viajesContent.innerHTML = '<p class="text-center text-rose-600">Error cargando viajes.</p>';
            });
    }

    viajesClose.addEventListener('click', cerrarModalViajes);
    viajesModal.addEventListener('click', (e)=>{
        if(e.target===viajesModal) cerrarModalViajes();
    });

    viajesSelectConductor.addEventListener('change', ()=>{
        const nuevo = viajesSelectConductor.value;
        loadViajes(nuevo);
    });

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

    // ===== GESTIÓN DE CUENTAS GUARDADAS EN BASE DE DATOS =====
    const formFiltros = document.getElementById('formFiltros');
    const inpDesde = document.getElementById('inp_desde');
    const inpHasta = document.getElementById('inp_hasta');
    const selEmpresa = document.getElementById('sel_empresa');
    const inpFact = document.getElementById('inp_facturado');
    const inpPorcentaje = document.getElementById('inp_porcentaje_ajuste');

    const saveCuentaModal = document.getElementById('saveCuentaModal');
    const btnShowSaveCuenta = document.getElementById('btnShowSaveCuenta');
    const btnCloseSaveCuenta = document.getElementById('btnCloseSaveCuenta');
    const btnCancelSaveCuenta = document.getElementById('btnCancelSaveCuenta');
    const btnDoSaveCuenta = document.getElementById('btnDoSaveCuenta');

    const iNombre = document.getElementById('cuenta_nombre');
    const iEmpresa = document.getElementById('cuenta_empresa');
    const iRango = document.getElementById('cuenta_rango');
    const iCFact = document.getElementById('cuenta_facturado');
    const iCPorcentaje  = document.getElementById('cuenta_porcentaje');

    const gestorModal = document.getElementById('gestorCuentasModal');
    const btnShowGestor = document.getElementById('btnShowGestorCuentas');
    const btnCloseGestor = document.getElementById('btnCloseGestor');
    const btnAddDesdeFiltro = document.getElementById('btnAddDesdeFiltro');
    const btnRecargarCuentas = document.getElementById('btnRecargarCuentas');
    const lblEmpresaActual = document.getElementById('lblEmpresaActual');
    const buscaCuenta = document.getElementById('buscaCuenta');
    const tbodyCuentas = document.getElementById('tbodyCuentas');

    // ===== FUNCIONES PARA CUENTAS =====

    /**
     * Contar préstamos en una cuenta
     */
    function contarPrestamosEnCuenta(cuenta) {
        if (!cuenta.datos_json || !cuenta.datos_json.prestamos) return 0;
        let total = 0;
        Object.values(cuenta.datos_json.prestamos).forEach(prestamosArray => {
            total += prestamosArray.length;
        });
        return total;
    }

    /**
     * Contar filas manuales en una cuenta
     */
    function contarFilasManuales(cuenta) {
        if (!cuenta.datos_json || !cuenta.datos_json.filasManuales) return 0;
        return cuenta.datos_json.filasManuales.length;
    }

    /**
     * Formatear fecha para mostrar
     */
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

    /**
     * Renderizar lista de cuentas
     */
    async function renderCuentas(){
        const empresa = selEmpresa.value.trim();
        const filtro = (buscaCuenta.value||'').toLowerCase();
        lblEmpresaActual.textContent = empresa || '(todas)';

        // Obtener cuentas desde BD
        cuentasBD = await obtenerCuentasDesdeBD();
        
        tbodyCuentas.innerHTML = '';
        
        if(cuentasBD.length === 0){
            tbodyCuentas.innerHTML = `
                <tr>
                    <td colspan="7" class="px-3 py-8 text-center text-slate-500">
                        <div class="flex flex-col items-center gap-2">
                            <div class="text-3xl">📭</div>
                            <div>No hay cuentas guardadas para esta empresa.</div>
                            <div class="text-xs text-slate-400">Guarda tu primera cuenta usando el botón "⭐ Guardar como cuenta"</div>
                        </div>
                    </td>
                </tr>`;
            return;
        }
        
        // Filtrar por búsqueda
        const cuentasFiltradas = cuentasBD.filter(cuenta => 
            !filtro || 
            cuenta.nombre.toLowerCase().includes(filtro) ||
            cuenta.usuario?.toLowerCase().includes(filtro)
        );
        
        if (cuentasFiltradas.length === 0) {
            tbodyCuentas.innerHTML = `
                <tr>
                    <td colspan="7" class="px-3 py-4 text-center text-slate-500">
                        No se encontraron cuentas con ese filtro.
                    </td>
                </tr>`;
            return;
        }
        
        const frag = document.createDocumentFragment();
        
        cuentasFiltradas.forEach(cuenta => {
            const totalPrestamos = contarPrestamosEnCuenta(cuenta);
            const totalFilasManuales = contarFilasManuales(cuenta);
            
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50';
            tr.innerHTML = `
        <td class="px-3 py-3">
          <div class="font-medium">${cuenta.nombre}</div>
          <div class="text-xs text-slate-500">${cuenta.usuario || 'Sistema'}</div>
        </td>
        <td class="px-3 py-3">
          <div class="text-sm">${cuenta.desde} → ${cuenta.hasta}</div>
        </td>
        <td class="px-3 py-3 text-right num font-semibold">${fmt(cuenta.facturado||0)}</td>
        <td class="px-3 py-3 text-right num">${cuenta.porcentaje_ajuste||0}%</td>
        <td class="px-3 py-3 text-center">
          <div class="flex flex-col gap-1 items-center">
            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs ${totalPrestamos > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}">
              ${totalPrestamos} préstamos
            </span>
            ${totalFilasManuales > 0 ? 
              `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] bg-blue-100 text-blue-700">
                ${totalFilasManuales} manuales
              </span>` : ''}
          </div>
        </td>
        <td class="px-3 py-3 text-center text-xs text-slate-500">
          ${formatFecha(cuenta.fecha_creacion)}
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
        </td>`;
            
            // Evento para cargar cuenta
            tr.querySelector('.btnCargarCuenta').addEventListener('click', async () => {
                await cargarCuentaCompleta(cuenta);
            });
            
            // Evento para eliminar cuenta
            tr.querySelector('.btnEliminarCuenta').addEventListener('click', async () => {
                await eliminarCuenta(cuenta);
            });
            
            frag.appendChild(tr);
        });
        
        tbodyCuentas.appendChild(frag);
    }

    /**
     * Cargar cuenta completa desde BD
     */
    async function cargarCuentaCompleta(cuenta) {
        // Confirmación
        const confirmacion = await Swal.fire({
            title: `¿Cargar cuenta "${cuenta.nombre}"?`,
            html: `
                <div class="text-left text-sm text-slate-600">
                    <p class="mb-2">Se restaurarán todos los datos:</p>
                    <ul class="list-disc pl-4 mb-3 space-y-1">
                        <li>Préstamos asignados a cada conductor</li>
                        <li>Seguridad social de cada conductor</li>
                        <li>Cuentas bancarias</li>
                        <li>Estados de pago</li>
                        <li>Filas manuales agregadas</li>
                    </ul>
                    <p class="text-xs text-amber-600">⚠️ Se perderán los datos actuales no guardados</p>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, cargar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#6b7280'
        });
        
        if (!confirmacion.isConfirmed) return;
        
        try {
            // Mostrar loading
            Swal.fire({
                title: 'Cargando cuenta...',
                text: 'Restaurando datos desde base de datos',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Cargar datos desde BD
            const resultado = await cargarCuentaDesdeBD(cuenta.id);
            
            if (!resultado.success) {
                throw new Error(resultado.message || 'Error al cargar cuenta');
            }
            
            const cuentaCompleta = resultado.cuenta;
            const datos = cuentaCompleta.datos_json;
            
            // Cargar datos básicos
            selEmpresa.value = cuentaCompleta.empresa;
            inpDesde.value = cuentaCompleta.desde;
            inpHasta.value = cuentaCompleta.hasta;
            inpFact.value = fmt(cuentaCompleta.facturado || 0);
            inpPorcentaje.value = cuentaCompleta.porcentaje_ajuste || 0;
            
            // Cargar datos asociados a conductores
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
            
            if (datos.conductoresSeleccionados) {
                selectedConductors = datos.conductoresSeleccionados;
                setLS(SELECTED_CONDUCTORS_KEY, selectedConductors);
            }
            
            // Limpiar filas manuales existentes
            document.querySelectorAll('#tbody tr.fila-manual').forEach(tr => tr.remove());
            manualRows = [];
            
            // Cargar filas manuales
            if (datos.filasManuales && datos.filasManuales.length > 0) {
                datos.filasManuales.forEach(filaManual => {
                    agregarFilaManual();
                    
                    // Obtener la última fila agregada
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
                        
                        // Actualizar datos del conductor
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
            
            // Aplicar cambios a todas las filas existentes
            setTimeout(() => {
                // Aplicar cuentas bancarias
                document.querySelectorAll('#tbody tr').forEach(tr => {
                    const nombre = obtenerNombreConductorDeFila(tr);
                    if (nombre && accMap[nombre]) {
                        const ctaInput = tr.querySelector('input.cta');
                        if (ctaInput) ctaInput.value = accMap[nombre];
                    }
                    
                    // Aplicar seguridad social
                    if (nombre && ssMap[nombre]) {
                        const ssInput = tr.querySelector('input.ss');
                        if (ssInput) ssInput.value = fmt(ssMap[nombre]);
                    }
                    
                    // Aplicar estado de pago
                    if (nombre && estadoPagoMap[nombre]) {
                        const estadoSelect = tr.querySelector('select.estado-pago');
                        if (estadoSelect) {
                            estadoSelect.value = estadoPagoMap[nombre];
                            aplicarEstadoFila(tr, estadoPagoMap[nombre]);
                        }
                    }
                });
                
                // Asignar préstamos
                asignarPrestamosAFilas();
                
                // Recalcular todo
                recalc();
                
                // Cerrar modal
                closeGestor();
                
                // Mostrar éxito
                Swal.fire({
                    title: '✅ Cuenta cargada',
                    html: `
                        <div class="text-sm text-slate-600">
                            <p>Cuenta "<strong>${cuentaCompleta.nombre}</strong>" cargada exitosamente.</p>
                            <p class="mt-2 text-xs">Se restauraron datos para <strong>${Object.keys(datos.prestamos || {}).length}</strong> conductores.</p>
                        </div>
                    `,
                    icon: 'success',
                    timer: 3000,
                    showConfirmButton: false
                });
            }, 100);
            
        } catch (error) {
            console.error('Error al cargar cuenta:', error);
            Swal.fire({
                title: '❌ Error',
                text: error.message || 'Error al cargar la cuenta desde base de datos',
                icon: 'error'
            });
        }
    }

    /**
     * Eliminar cuenta de BD
     */
    async function eliminarCuenta(cuenta) {
        const confirmacion = await Swal.fire({
            title: `¿Eliminar cuenta "${cuenta.nombre}"?`,
            text: 'Esta acción no se puede deshacer. La cuenta se eliminará permanentemente de la base de datos.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280'
        });
        
        if (!confirmacion.isConfirmed) return;
        
        try {
            const resultado = await eliminarCuentaDeBD(cuenta.id);
            
            if (resultado.success) {
                // Actualizar lista
                await renderCuentas();
                
                Swal.fire({
                    title: '✅ Eliminada',
                    text: 'Cuenta eliminada de la base de datos',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                throw new Error(resultado.message || 'Error al eliminar');
            }
        } catch (error) {
            console.error('Error al eliminar cuenta:', error);
            Swal.fire({
                title: '❌ Error',
                text: error.message || 'Error al eliminar la cuenta',
                icon: 'error'
            });
        }
    }

    // ===== GUARDAR CUENTA EN BASE DE DATOS =====
    async function openSaveCuenta(){
        const emp = selEmpresa.value.trim();
        if(!emp){ 
            Swal.fire({
                title: '⚠️ Empresa requerida',
                text: 'Selecciona una EMPRESA antes de guardar la cuenta.',
                icon: 'warning'
            });
            return; 
        }
        
        const d = inpDesde.value; 
        const h = inpHasta.value;

        iEmpresa.value = emp;
        iRango.value = `${d} → ${h}`;
        iNombre.value = `${emp} ${d} a ${h}`;
        iCFact.value = fmt(toInt(inpFact.value));
        iCPorcentaje.value = parseFloat(inpPorcentaje.value) || 0;

        saveCuentaModal.classList.remove('hidden');
        setTimeout(()=> iNombre.focus(), 0);
    }
    
    function closeSaveCuenta(){ 
        saveCuentaModal.classList.add('hidden'); 
        iNombre.value = '';
    }

    btnShowSaveCuenta.addEventListener('click', openSaveCuenta);
    btnCloseSaveCuenta.addEventListener('click', closeSaveCuenta);
    btnCancelSaveCuenta.addEventListener('click', closeSaveCuenta);

    btnDoSaveCuenta.addEventListener('click', async ()=>{
        const emp = iEmpresa.value.trim();
        const [d1, d2raw] = iRango.value.split('→');
        const desde = (d1||'').trim();
        const hasta = (d2raw||'').trim();
        const nombre = iNombre.value.trim() || `${emp} ${desde} a ${hasta}`;
        const facturado = toInt(iCFact.value);
        const porcentaje  = parseFloat(iCPorcentaje.value) || 0;

        // Validaciones
        if (!nombre) {
            Swal.fire({
                title: '⚠️ Nombre requerido',
                text: 'Ingresa un nombre para la cuenta.',
                icon: 'warning'
            });
            iNombre.focus();
            return;
        }

        if (facturado <= 0) {
            Swal.fire({
                title: '⚠️ Facturado inválido',
                text: 'El valor facturado debe ser mayor a 0.',
                icon: 'warning'
            });
            return;
        }

        // OBTENER TODOS LOS DATOS ACTUALES
        const prestamosActuales = { ...prestSel };
        const segSocialActual = { ...ssMap };
        const cuentasActual = { ...accMap };
        const estadosActual = { ...estadoPagoMap };
        const conductoresSeleccionadosActual = [...selectedConductors];
        
        // Guardar también los valores de las filas manuales
        const filasManualesData = [];
        document.querySelectorAll('#tbody tr.fila-manual').forEach(tr => {
            const conductor = tr.querySelector('.conductor-select')?.value || '';
            const base = toInt(tr.querySelector('.base-manual')?.value || '0');
            const cuenta = tr.querySelector('input.cta')?.value || '';
            const segSocial = toInt(tr.querySelector('input.ss')?.value || '0');
            const estado = tr.querySelector('select.estado-pago')?.value || '';
            
            if (conductor) {
                filasManualesData.push({
                    conductor,
                    base,
                    cuenta,
                    segSocial,
                    estado
                });
            }
        });

        // Crear objeto de datos JSON para BD
        const datosJSON = {
            prestamos: prestamosActuales,
            segSocial: segSocialActual,
            cuentasBancarias: cuentasActual,
            estadosPago: estadosActual,
            filasManuales: filasManualesData,
            conductoresSeleccionados: conductoresSeleccionadosActual
        };

        // Crear objeto de cuenta completa para BD
        const cuentaCompleta = {
            nombre,
            empresa: emp,
            desde,
            hasta,
            facturado,
            porcentaje,
            datos_json: datosJSON
        };

        try {
            // Mostrar loading
            Swal.fire({
                title: 'Guardando en Base de Datos...',
                text: 'Por favor espera',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Guardar en BD
            const resultado = await guardarCuentaEnBD(cuentaCompleta);
            
            if (resultado.success) {
                // Cerrar modal
                closeSaveCuenta();
                
                // Mostrar éxito
                Swal.fire({
                    title: '✅ Guardado exitoso',
                    html: `
                        <div class="text-sm text-slate-600">
                            <p>Cuenta "<strong>${nombre}</strong>" guardada en Base de Datos.</p>
                            <p class="mt-2 text-xs">ID: <strong>${resultado.id}</strong></p>
                            <div class="mt-3 text-left text-xs border-t pt-2">
                                <p class="font-semibold">Datos guardados:</p>
                                <ul class="list-disc pl-4 mt-1 space-y-1">
                                    <li>Préstamos: ${Object.keys(prestamosActuales).length} conductores</li>
                                    <li>Cuentas bancarias: ${Object.keys(cuentasActual).length}</li>
                                    <li>Filas manuales: ${filasManualesData.length}</li>
                                </ul>
                            </div>
                        </div>
                    `,
                    icon: 'success',
                    confirmButtonText: 'Aceptar'
                });
                
                // Actualizar lista en gestor si está abierto
                if (!gestorModal.classList.contains('hidden')) {
                    await renderCuentas();
                }
            } else {
                throw new Error(resultado.message || 'Error al guardar');
            }
        } catch (error) {
            console.error('Error al guardar cuenta:', error);
            Swal.fire({
                title: '❌ Error al guardar',
                text: error.message || 'Error al guardar en base de datos. Verifica tu conexión.',
                icon: 'error'
            });
        }
    });

    // ===== GESTOR DE CUENTAS =====
    async function openGestor(){
        // Actualizar etiqueta de empresa
        lblEmpresaActual.textContent = selEmpresa.value.trim() || '(todas)';
        
        // Mostrar modal
        gestorModal.classList.remove('hidden');
        
        // Cargar cuentas
        await renderCuentas();
        
        setTimeout(()=> buscaCuenta.focus(), 0);
    }
    
    function closeGestor(){ 
        gestorModal.classList.add('hidden'); 
        buscaCuenta.value = '';
    }

    btnShowGestor.addEventListener('click', openGestor);
    btnCloseGestor.addEventListener('click', closeGestor);
    btnRecargarCuentas.addEventListener('click', async () => {
        await renderCuentas();
        Swal.fire({
            title: '🔄 Lista actualizada',
            text: 'Cuentas recargadas desde Base de Datos',
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
        });
    });
    
    buscaCuenta.addEventListener('input', renderCuentas);
    btnAddDesdeFiltro.addEventListener('click', ()=>{ closeGestor(); openSaveCuenta(); });

    // ===== INICIALIZACIÓN =====
    document.addEventListener('DOMContentLoaded', async function() {
        // Cargar SweetAlert si no está cargado
        if (typeof Swal === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
            document.head.appendChild(script);
        }

        initializeExistingRows();
        cargarFilasManuales();
        hacerPanelArrastrable();
        asignarPrestamosAFilas();
        recalc();
        
        if (selectedConductors.length > 0) {
            actualizarPanelFlotante();
        }
        
        // Configurar eventos de entrada para recalcular
        document.getElementById('inp_porcentaje_ajuste').addEventListener('input', recalc);
        document.getElementById('inp_facturado').addEventListener('input', recalc);
        
        // Verificar si hay mensaje de éxito en URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('guardado')) {
            Swal.fire({
                title: '✅ Guardado exitoso',
                text: 'Cuenta guardada en Base de Datos',
                icon: 'success',
                timer: 3000,
                showConfirmButton: false
            });
        }
    });
</script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</body>
</html>
<?php
$conn->close();
?>