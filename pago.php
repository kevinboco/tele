<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }
$conn->set_charset('utf8mb4');

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

/* ================= GUARDAR CUENTA EN BD ================= */
if (isset($_POST['guardar_cuenta'])) {
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $empresa = $conn->real_escape_string($_POST['empresa']);
    $desde = $conn->real_escape_string($_POST['desde']);
    $hasta = $conn->real_escape_string($_POST['hasta']);
    $facturado = (float)$_POST['facturado'];
    $porcentaje = (float)$_POST['porcentaje'];
    
    $sql = "INSERT INTO cuentas_cobro_guardadas (nombre, empresa, desde, hasta, facturado, porcentaje_ajuste) 
            VALUES ('$nombre', '$empresa', '$desde', '$hasta', $facturado, $porcentaje)";
    
    if ($conn->query($sql)) {
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit;
}

/* ================= OBTENER CUENTAS GUARDADAS ================= */
if (isset($_GET['obtener_cuentas'])) {
    header('Content-Type: application/json');
    
    $empresa = $conn->real_escape_string($_GET['empresa'] ?? '');
    
    // Primero verificamos si la tabla existe
    $tableCheck = $conn->query("SHOW TABLES LIKE 'cuentas_cobro_guardadas'");
    if (!$tableCheck || $tableCheck->num_rows == 0) {
        echo json_encode([]);
        exit;
    }
    
    $sql = "SELECT * FROM cuentas_cobro_guardadas";
    if ($empresa !== '') {
        $sql .= " WHERE empresa = '$empresa'";
    }
    $sql .= " ORDER BY desde DESC, fecha_creacion DESC";
    
    $result = $conn->query($sql);
    $cuentas = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $cuentas[] = $row;
        }
    }
    
    echo json_encode($cuentas);
    exit;
}

/* ================= ELIMINAR CUENTA ================= */
if (isset($_POST['eliminar_cuenta'])) {
    $id = (int)$_POST['id'];
    $sql = "DELETE FROM cuentas_cobro_guardadas WHERE id = $id";
    
    if ($conn->query($sql)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit;
}

/* ================= ACTUALIZAR CUENTA ================= */
if (isset($_POST['actualizar_cuenta'])) {
    $id = (int)$_POST['id'];
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $facturado = (float)$_POST['facturado'];
    $porcentaje = (float)$_POST['porcentaje'];
    
    $sql = "UPDATE cuentas_cobro_guardadas SET 
            nombre = '$nombre', 
            facturado = $facturado, 
            porcentaje_ajuste = $porcentaje 
            WHERE id = $id";
    
    if ($conn->query($sql)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit;
}

/* ================= AJAX: Viajes por conductor (leyenda con contadores y soporte de filtro) ================= */
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

  $legend = [
    'completo'     => ['label'=>'Completo',     'badge'=>'bg-emerald-100 text-emerald-700 border border-emerald-200', 'row'=>'bg-emerald-50/40'],
    'medio'        => ['label'=>'Medio',        'badge'=>'bg-amber-100 text-amber-800 border border-amber-200',       'row'=>'bg-amber-50/40'],
    'extra'        => ['label'=>'Extra',        'badge'=>'bg-slate-200 text-slate-800 border border-slate-300',       'row'=>'bg-slate-50'],
    'siapana'      => ['label'=>'Siapana',      'badge'=>'bg-fuchsia-100 text-fuchsia-700 border border-fuchsia-200', 'row'=>'bg-fuchsia-50/40'],
    'carrotanque'  => ['label'=>'Carrotanque',  'badge'=>'bg-cyan-100 text-cyan-800 border border-cyan-200',          'row'=>'bg-cyan-50/40'],
    'otro'         => ['label'=>'Otro',         'badge'=>'bg-gray-100 text-gray-700 border border-gray-200',          'row'=>'']
  ];

  $res = $conn->query($sql);

  $rowsHTML = "";
  $counts = [
    'completo'=>0,
    'medio'=>0,
    'extra'=>0,
    'siapana'=>0,
    'carrotanque'=>0,
    'otro'=>0
  ];

  if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_assoc()) {
      $ruta = (string)$r['ruta'];
      $guiones = substr_count($ruta,'-');

      // Clasificación
      if ($r['tipo_vehiculo']==='Carrotanque' && $guiones==0) {
        $cat = 'carrotanque';
      } elseif (stripos($ruta,'Siapana') !== false) {
        $cat = 'siapana';
      } elseif (stripos($ruta,'Maicao') === false) {
        $cat = 'extra';
      } elseif ($guiones==2) {
        $cat = 'completo';
      } elseif ($guiones==1) {
        $cat = 'medio';
      } else {
        $cat = 'otro';
      }

      if (isset($counts[$cat])) {
        $counts[$cat]++;
      } else {
        $counts[$cat] = 1;
      }

      $l = $legend[$cat];
      $badge = "<span class='inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold {$l['badge']}'>".$l['label']."</span>";
      $rowCls = trim("row-viaje hover:bg-blue-50 transition-colors {$l['row']} cat-$cat");

      $rowsHTML .= "<tr class='{$rowCls}'>
              <td class='px-3 py-2'>".htmlspecialchars($r['fecha'])."</td>
              <td class='px-3 py-2'>
                <div class='flex items-center gap-2'>
                  {$badge}
                  <span>".htmlspecialchars($ruta)."</span>
                </div>
              </td>
              <td class='px-3 py-2'>".htmlspecialchars($r['empresa'])."</td>
              <td class='px-3 py-2'>".htmlspecialchars($r['tipo_vehiculo'])."</td>
            </tr>";
    }
  } else {
    $rowsHTML .= "<tr><td colspan='4' class='px-3 py-4 text-center text-slate-500'>Sin viajes en el rango/empresa.</td></tr>";
  }

  ?>
  <div class='space-y-3'>
    <div class='flex flex-wrap gap-2 text-xs' id="legendFilterBar">
      <?php
      foreach (['completo','medio','extra','siapana','carrotanque'] as $k) {
        $l = $legend[$k];
        $countVal = $counts[$k] ?? 0;
        echo "<button
                class='legend-pill inline-flex items-center gap-2 px-3 py-2 rounded-full {$l['badge']} hover:opacity-90 transition ring-0 outline-none border cursor-pointer select-none'
                data-tipo='{$k}'
              >
                <span class='w-2.5 h-2.5 rounded-full ".str_replace(['bg-','/40'], ['bg-',''], $l['row'])." bg-opacity-100 border border-white/30 shadow-inner'></span>
                <span class='font-semibold text-[13px]'>{$l['label']}</span>
                <span class='text-[11px] font-semibold opacity-80'>({$countVal})</span>
              </button>";
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

// Si llegamos hasta aquí, mostramos la página normal (no es una petición AJAX)
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

/* ================= Viajes del rango (para totales) ================= */
$sqlV = "SELECT nombre, ruta, empresa, tipo_vehiculo
         FROM viajes
         WHERE fecha BETWEEN '$desde' AND '$hasta'";
if ($empresaFiltro !== "") {
  $empresaFiltroEsc = $conn->real_escape_string($empresaFiltro);
  $sqlV .= " AND empresa = '$empresaFiltroEsc'";
}
$resV = $conn->query($sqlV);

$contadores = [];
if ($resV) {
  while ($row = $resV->fetch_assoc()) {
    $nombre = $row['nombre'];
    if (!isset($contadores[$nombre])) {
      $contadores[$nombre] = [
        'vehiculo' => $row['tipo_vehiculo'],
        'completos'=>0, 'medios'=>0, 'extras'=>0, 'carrotanques'=>0, 'siapana'=>0
      ];
    }
    $ruta = (string)$row['ruta'];
    $guiones = substr_count($ruta, '-');
    if ($row['tipo_vehiculo']==='Carrotanque' && $guiones==0) {
      $contadores[$nombre]['carrotanques']++;
    } elseif (stripos($ruta,'Siapana') !== false) {
      $contadores[$nombre]['siapana']++;
    } elseif (stripos($ruta,'Maicao') === false) {
      $contadores[$nombre]['extras']++;
    } elseif ($guiones==2) {
      $contadores[$nombre]['completos']++;
    } elseif ($guiones==1) {
      $contadores[$nombre]['medios']++;
    }
  }
}

/* ================= Tarifas ================= */
$tarifas = [];
if ($empresaFiltro !== "") {
  $resT = $conn->query("SELECT * FROM tarifas WHERE empresa='".$conn->real_escape_string($empresaFiltro)."'");
  if ($resT) while($r=$resT->fetch_assoc()) $tarifas[$r['tipo_vehiculo']] = $r;
}

/* ================= Préstamos: listado multiselección ================= */
$prestamosList = [];
$i = 0;

// CONSULTA ACTUALIZADA: 13% desde 29-oct-2025, 10% para préstamos anteriores Y SOLO PRÉSTAMOS NO PAGADOS
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
  GROUP BY deudor
";
if ($rP = $conn->query($qPrest)) {
  while($r = $rP->fetch_assoc()){
    $name = $r['deudor'];
    $key  = norm_person($name);
    $total = (int)round($r['total']);
    $prestamosList[] = ['id'=>$i++, 'name'=>$name, 'key'=>$key, 'total'=>$total];
  }
}

/* ================= Filas base (viajes) ================= */
$filas = []; $total_facturado = 0;
foreach ($contadores as $nombre => $v) {
  $veh = $v['vehiculo'];
  $t = $tarifas[$veh] ?? ["completo"=>0,"medio"=>0,"extra"=>0,"carrotanque"=>0,"siapana"=>0];

  $total = $v['completos']   * (int)($t['completo']    ?? 0)
         + $v['medios']      * (int)($t['medio']       ?? 0)
         + $v['extras']      * (int)($t['extra']       ?? 0)
         + $v['carrotanques']* (int)($t['carrotanque'] ?? 0)
         + $v['siapana']     * (int)($t['siapana']     ?? 0);

  $filas[] = ['nombre'=>$nombre, 'total_bruto'=>(int)$total];
  $total_facturado += (int)$total;
}
usort($filas, fn($a,$b)=> $b['total_bruto'] <=> $a['total_bruto']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Ajuste de Pago (con gestor de cuentas de cobro)</title>
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
</style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">
  <header class="max-w-[1600px] mx-auto px-3 md:px-4 pt-6">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-5 py-4">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <h2 class="text-xl md:text-2xl font-bold">🧾 Ajuste de Pago</h2>
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
      <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <div>
          <div class="text-xs text-slate-500 mb-1">Conductores en rango</div>
          <div class="text-lg font-semibold"><?= count($filas) ?></div>
        </div>
        <label class="block md:col-span-2">
          <span class="block text-xs font-medium mb-1">Cuenta de cobro (facturado)</span>
          <input id="inp_facturado" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right num"
                 value="<?= number_format($total_facturado,0,',','.') ?>">
        </label>
        <label class="block md:col-span-2">
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
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold">Conductores</h3>
        <button id="btnAddManual" type="button" class="rounded-lg bg-green-600 text-white px-4 py-2 text-sm hover:bg-green-700">
          ➕ Agregar conductor manual
        </button>
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
              <th class="px-3 py-2 text-center">Acciones</th>
            </tr>
          </thead>
          <tbody id="tbody" class="divide-y divide-slate-100 bg-white">
            <?php foreach ($filas as $f): ?>
            <tr>
              <td class="px-3 py-2">
                <button type="button" class="conductor-link" title="Ver viajes"><?= htmlspecialchars($f['nombre']) ?></button>
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
                <!-- Solo para filas manuales -->
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

  <!-- ===== Modal PRÉSTAMOS (multi) ===== -->
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
            <input id="cuenta_facturado" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right num">
          </label>
          <label class="block">
            <span class="block text-xs font-medium mb-1">Porcentaje ajuste</span>
            <input id="cuenta_porcentaje" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right num">
          </label>
        </div>
      </div>
      <div class="px-5 py-4 border-t border-slate-200 flex items-center justify-end gap-2">
        <button id="btnCancelSaveCuenta" class="rounded-lg border border-slate-300 px-4 py-2 bg-white hover:bg-slate-50">Cancelar</button>
        <button id="btnDoSaveCuenta" class="rounded-lg border border-amber-500 text-white px-4 py-2 bg-amber-500 hover:bg-amber-600">Guardar</button>
      </div>
    </div>
  </div>

  <!-- ===== Modal GESTOR DE CUENTAS ===== -->
  <div id="gestorCuentasModal" class="hidden fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/30"></div>
    <div class="relative mx-auto my-10 w-full max-w-3xl bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
        <h3 class="text-lg font-semibold">📚 Cuentas guardadas</h3>
        <button id="btnCloseGestor" class="p-2 rounded hover:bg-slate-100" title="Cerrar">✕</button>
      </div>
      <div class="p-4 space-y-3">
        <div class="flex flex-col md:flex-row md:items-center gap-3">
          <div class="text-sm">Empresa actual: <strong id="lblEmpresaActual"></strong></div>
          <input id="buscaCuenta" type="text" placeholder="Buscar por nombre…" class="w-full rounded-xl border border-slate-300 px-3 py-2">
        </div>
        <div class="overflow-auto max-h-[60vh] rounded-xl border border-slate-200">
          <table class="min-w-full text-sm">
            <thead class="bg-blue-600 text-white">
              <tr>
                <th class="px-3 py-2 text-left">Nombre</th>
                <th class="px-3 py-2 text-left">Rango</th>
                <th class="px-3 py-2 text-right">Facturado</th>
                <th class="px-3 py-2 text-right">% Ajuste</th>
                <th class="px-3 py-2 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody id="tbodyCuentas" class="divide-y divide-slate-100 bg-white"></tbody>
          </table>
        </div>
      </div>
      <div class="px-5 py-4 border-t border-slate-200 text-right">
        <button id="btnAddDesdeFiltro" class="rounded-lg border border-amber-300 px-3 py-2 text-sm bg-amber-50 hover:bg-amber-100">⭐ Guardar rango actual</button>
      </div>
    </div>
  </div>

<script>
  // ===== Claves de persistencia =====
  const COMPANY_SCOPE = <?= json_encode(($empresaFiltro ?: '__todas__')) ?>;
  const ACC_KEY   = 'cuentas:'+COMPANY_SCOPE;
  const SS_KEY    = 'seg_social:'+COMPANY_SCOPE;
  const PREST_SEL_KEY = 'prestamo_sel_multi:v2:'+COMPANY_SCOPE;
  const ESTADO_PAGO_KEY = 'estado_pago:'+COMPANY_SCOPE;
  const MANUAL_ROWS_KEY = 'filas_manuales:'+COMPANY_SCOPE;

  const PRESTAMOS_LIST = <?php echo json_encode($prestamosList, JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK); ?>;
  const CONDUCTORES_LIST = <?= json_encode(array_map(fn($f)=>$f['nombre'],$filas), JSON_UNESCAPED_UNICODE); ?>;

  const toInt = (s)=>{ if(typeof s==='number') return Math.round(s); s=(s||'').toString().replace(/\./g,'').replace(/,/g,'').replace(/[^\d\-]/g,''); return parseInt(s||'0',10)||0; };
  const fmt = (n)=> (n||0).toLocaleString('es-CO');
  const getLS=(k)=>{try{return JSON.parse(localStorage.getItem(k)||'{}')}catch{return{}}}
  const setLS=(k,v)=> localStorage.setItem(k, JSON.stringify(v));

  let accMap = getLS(ACC_KEY);
  let ssMap  = getLS(SS_KEY);
  let prestSel = getLS(PREST_SEL_KEY); if(!prestSel || typeof prestSel!=='object') prestSel = {};
  let estadoPagoMap = getLS(ESTADO_PAGO_KEY) || {};
  let manualRows = JSON.parse(localStorage.getItem(MANUAL_ROWS_KEY) || '[]');

  const tbody = document.getElementById('tbody');
  const btnAddManual = document.getElementById('btnAddManual');

  // ===== FUNCIÓN PARA AGREGAR FILA MANUAL =====
  function agregarFilaManual(manualIdFromLS=null) {
    const manualId = manualIdFromLS || ('manual_' + Date.now());
    const nuevaFila = document.createElement('tr');
    nuevaFila.className = 'fila-manual';
    nuevaFila.dataset.manualId = manualId;
    
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
        <button type="button" class="btn-eliminar-manual text-xs px-2 py-1 rounded border border-rose-300 bg-rose-50 hover:bg-rose-100 text-rose-700">
          🗑️
        </button>
      </td>
    `;

    tbody.appendChild(nuevaFila);

    if (!manualIdFromLS) {
      manualRows.push(manualId);
      localStorage.setItem(MANUAL_ROWS_KEY, JSON.stringify(manualRows));
    }

    configurarEventosFila(nuevaFila);
    recalc();
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

    let baseName = '';
    if (conductorSelect) {
      baseName = conductorSelect.value || '';
    } else {
      baseName = tr.children[0].innerText.trim();
    }

    // Base manual
    if (baseInput) {
      baseInput.addEventListener('input', () => {
        baseInput.value = fmt(toInt(baseInput.value));
        recalc();
      });
    }

    // Cuenta bancaria
    if (cta) {
      if (baseName && accMap[baseName]) cta.value = accMap[baseName];
      cta.addEventListener('change', () => { 
        const name = conductorSelect ? conductorSelect.value : tr.children[0].innerText.trim();
        if (!name) return;
        accMap[name] = cta.value.trim(); 
        setLS(ACC_KEY, accMap); 
      });
    }

    // Seguridad social
    if (ss) {
      if (baseName && ssMap[baseName]) ss.value = fmt(toInt(ssMap[baseName]));
      ss.addEventListener('input', () => { 
        const name = conductorSelect ? conductorSelect.value : tr.children[0].innerText.trim();
        if (!name) return;
        ssMap[name] = toInt(ss.value); 
        setLS(SS_KEY, ssMap); 
        recalc(); 
      });
    }

    // Estado de pago
    if (estadoPago) {
      if (baseName && estadoPagoMap[baseName]) {
        estadoPago.value = estadoPagoMap[baseName];
        aplicarEstadoFila(tr, estadoPagoMap[baseName]);
      }
      estadoPago.addEventListener('change', () => { 
        const name = conductorSelect ? conductorSelect.value : tr.children[0].innerText.trim();
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
        
        const prestSpan = tr.querySelector('.prest');
        const selLabel = tr.querySelector('.selected-deudor');
        const chosen = prestSel[newBaseName] || [];
        prestSpan.textContent = fmt(sumTotals(chosen));
        selLabel.textContent = summarizeNames(chosen);
        
        recalc();
      });
    }

    // Configurar préstamos existentes
    const prestSpan = tr.querySelector('.prest');
    const selLabel = tr.querySelector('.selected-deudor');
    const chosen = prestSel[baseName] || [];
    if (prestSpan) prestSpan.textContent = fmt(sumTotals(chosen));
    if (selLabel) selLabel.textContent = summarizeNames(chosen);
  }

  // ===== CARGAR FILAS MANUALES EXISTENTES (de localStorage) =====
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
      }
    });
  }

  // ===== FUNCIONES AUXILIARES =====
  function summarizeNames(arr){ 
    if(!arr||arr.length===0) return ''; 
    const n=arr.map(x=>x.name); 
    return n.length<=2 ? n.join(', ') : n.slice(0,2).join(', ')+' +'+(n.length-2)+' más'; 
  }
  
  function sumTotals(arr){ 
    return (arr||[]).reduce((a,b)=> a+(toInt(b.total)||0),0); 
  }

  function aplicarEstadoFila(tr, estado) {
    tr.classList.remove('estado-pagado', 'estado-pendiente', 'estado-procesando', 'estado-parcial');
    if (estado) tr.classList.add(`estado-${estado}`);
  }

  // ===== EVENTO BOTÓN AGREGAR MANUAL =====
  btnAddManual.addEventListener('click', ()=> agregarFilaManual());

  // ===== Modal préstamos =====
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
    let baseName;

    // Determinar el nombre base según si es fila manual o normal
    if (tr.classList.contains('fila-manual')) {
      const select = tr.querySelector('.conductor-select');
      if (!select || !select.value.trim()) {
        alert('Primero selecciona el conductor en la fila antes de elegir préstamos.');
        return;
      }
      baseName = select.value.trim();
    } else {
      baseName = tr.children[0].innerText.trim();
    }

    (prestSel[baseName] || []).forEach(x => selectedIds.add(Number(x.id)));

    prestSearch.value = '';
    delete selTotalManual.dataset.touched;
    renderPrestList('');

    const currentPrestVal = toInt(tr.querySelector('.prest').textContent || '0');
    const totalSeleccionado = PRESTAMOS_LIST
      .filter(it => selectedIds.has(it.id))
      .reduce((a, b) => a + (b.total || 0), 0);

    const baseValor = currentPrestVal > 0 ? currentPrestVal : totalSeleccionado;
    selTotalManual.value = fmt(baseValor);

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
    let baseName;
    
    if (currentRow.classList.contains('fila-manual')) {
      const select = currentRow.querySelector('.conductor-select');
      baseName = select ? select.value.trim() : '';
    } else {
      baseName = currentRow.children[0].innerText.trim();
    }
    
    currentRow.querySelector('.prest').textContent='0';
    currentRow.querySelector('.selected-deudor').textContent='';
    if (baseName) {
      delete prestSel[baseName]; 
      setLS(PREST_SEL_KEY, prestSel); 
    }
    recalc();
    selectedIds.clear(); 
    delete selTotalManual.dataset.touched;
    selTotalManual.value='0';
    renderPrestList(prestSearch.value);
  });
  
  btnAssign.addEventListener('click', () => {
    if (!currentRow) return;
    let baseName;

    if (currentRow.classList.contains('fila-manual')) {
      const select = currentRow.querySelector('.conductor-select');
      baseName = select ? select.value.trim() : '';
    } else {
      baseName = currentRow.children[0].innerText.trim();
    }

    const chosen = PRESTAMOS_LIST
      .filter(it => selectedIds.has(it.id))
      .map(it => ({ id: it.id, name: it.name, total: it.total }));

    // Guardar en localStorage solo si tenemos nombre
    if (baseName) {
      prestSel[baseName] = chosen;
      setLS(PREST_SEL_KEY, prestSel);
    }

    const totalReal = sumTotals(chosen);
    let manualVal = toInt(selTotalManual.value);

    if (manualVal < 0) manualVal = 0;

    if (totalReal > 0) {
      // Si hay préstamos seleccionados, no dejamos pasar del total real
      if (manualVal === 0) manualVal = totalReal;
      if (manualVal > totalReal) manualVal = totalReal;
    }
    // Si totalReal == 0, se respeta manualVal como está

    currentRow.querySelector('.prest').textContent = fmt(manualVal);
    currentRow.querySelector('.selected-deudor').textContent = summarizeNames(chosen);
    recalc();
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

  function attachFiltroViajes(){
    const pills = viajesContent.querySelectorAll('#legendFilterBar .legend-pill');
    const rows  = viajesContent.querySelectorAll('#viajesTableBody .row-viaje');
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
      empresa: RANGO_EMP
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

  // ===== CÁLCULOS =====
  function recalc(){
    const porcentaje = parseFloat(document.getElementById('inp_porcentaje_ajuste').value) || 0;
    const rows=[...tbody.querySelectorAll('tr')];

    let sumAjuste=0, sumLleg=0, sumRet=0, sumMil4=0, sumAp=0, sumSS=0, sumPrest=0, sumPagar=0;
    
    rows.forEach((tr)=>{
      let base;
      
      // Determinar si es fila manual o normal
      if (tr.classList.contains('fila-manual')) {
        const baseInput = tr.querySelector('.base-manual');
        base = baseInput ? toInt(baseInput.value) : 0;
      } else {
        const baseEl = tr.querySelector('.base');
        if (!baseEl) return;
        base = toInt(baseEl.textContent);
      }
      
      const prest=toInt(tr.querySelector('.prest').textContent || '0');
      
      // Calcular ajuste como porcentaje del base
      const ajuste = Math.round(base * (porcentaje / 100));
      const llego = base - ajuste;
      const ret=Math.round(llego*0.035);
      const mil4=Math.round(llego*0.004);
      const ap=Math.round(llego*0.10);
      const ssInput = tr.querySelector('input.ss');
      const ssVal = ssInput ? toInt(ssInput.value) : 0;
      const pagar = llego - ret - mil4 - ap - ssVal - prest;

      tr.querySelector('.ajuste').textContent=fmt(ajuste);
      tr.querySelector('.llego').textContent=fmt(llego);
      tr.querySelector('.ret').textContent=fmt(ret);
      tr.querySelector('.mil4').textContent=fmt(mil4);
      tr.querySelector('.apor').textContent=fmt(ap);
      tr.querySelector('.pagar').textContent=fmt(pagar);

      sumAjuste += ajuste;
      sumLleg+=llego; sumRet+=ret; sumMil4+=mil4; sumAp+=ap; sumSS+=ssVal; sumPrest+=prest; sumPagar+=pagar;
    });

    document.getElementById('lbl_total_ajuste').textContent=fmt(sumAjuste);
    document.getElementById('tot_valor_llego').textContent=fmt(sumLleg);
    document.getElementById('tot_retencion').textContent=fmt(sumRet);
    document.getElementById('tot_4x1000').textContent=fmt(sumMil4);
    document.getElementById('tot_aporte').textContent=fmt(sumAp);
    document.getElementById('tot_ss').textContent=fmt(sumSS);
    document.getElementById('tot_prestamos').textContent=fmt(sumPrest);
    document.getElementById('tot_pagar').textContent=fmt(sumPagar);
  }

  const fmtInput=(el)=> el && el.addEventListener('input',()=>{ 
    const raw=parseFloat(el.value) || 0; 
    el.value = raw; 
    recalc(); 
  });
  
  fmtInput(document.getElementById('inp_facturado'));
  fmtInput(document.getElementById('inp_porcentaje_ajuste'));

  // ===== INICIALIZACIÓN =====
  document.addEventListener('DOMContentLoaded', function() {
    initializeExistingRows();
    cargarFilasManuales();
    recalc();
  });

  // ===== Gestor de cuentas (BASE DE DATOS) =====
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
  const lblEmpresaActual = document.getElementById('lblEmpresaActual');
  const buscaCuenta = document.getElementById('buscaCuenta');
  const tbodyCuentas = document.getElementById('tbodyCuentas');

  // ===== FUNCIONES PARA BD =====
  async function guardarCuentaBD(cuenta) {
    try {
      const formData = new FormData();
      formData.append('guardar_cuenta', '1');
      formData.append('nombre', cuenta.nombre);
      formData.append('empresa', cuenta.empresa);
      formData.append('desde', cuenta.desde);
      formData.append('hasta', cuenta.hasta);
      formData.append('facturado', cuenta.facturado);
      formData.append('porcentaje', cuenta.porcentaje);

      const response = await fetch('', {
          method: 'POST',
          body: formData
      });
      return await response.json();
    } catch (error) {
      console.error('Error guardando cuenta:', error);
      return { success: false, error: 'Error de conexión' };
    }
  }

  async function obtenerCuentasBD(empresa) {
    try {
      const params = new URLSearchParams({
          obtener_cuentas: '1'
      });
      if (empresa) {
        params.append('empresa', empresa);
      }
      
      const response = await fetch('?' + params.toString());
      if (!response.ok) {
        throw new Error('Error en la respuesta del servidor: ' + response.status);
      }
      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        throw new Error('La respuesta no es JSON: ' + contentType);
      }
      const data = await response.json();
      console.log('Cuentas obtenidas:', data);
      return data;
    } catch (error) {
      console.error('Error obteniendo cuentas:', error);
      return [];
    }
  }

  async function eliminarCuentaBD(id) {
    try {
      const formData = new FormData();
      formData.append('eliminar_cuenta', '1');
      formData.append('id', id);

      const response = await fetch('', {
          method: 'POST',
          body: formData
      });
      return await response.json();
    } catch (error) {
      console.error('Error eliminando cuenta:', error);
      return { success: false, error: 'Error de conexión' };
    }
  }

  async function actualizarCuentaBD(id, cuenta) {
    try {
      const formData = new FormData();
      formData.append('actualizar_cuenta', '1');
      formData.append('id', id);
      formData.append('nombre', cuenta.nombre);
      formData.append('facturado', cuenta.facturado);
      formData.append('porcentaje', cuenta.porcentaje);

      const response = await fetch('', {
          method: 'POST',
          body: formData
      });
      return await response.json();
    } catch (error) {
      console.error('Error actualizando cuenta:', error);
      return { success: false, error: 'Error de conexión' };
    }
  }

  // ===== FUNCIONES PRINCIPALES =====
  function openSaveCuenta(){
    const emp = selEmpresa.value.trim();
    if(!emp){ alert('Selecciona una EMPRESA antes de guardar la cuenta.'); return; }
    const d = inpDesde.value; const h = inpHasta.value;

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
    btnDoSaveCuenta.onclick = null; // Resetear el evento
  }

  async function renderCuentas(){
    const emp = selEmpresa.value.trim();
    const filtro = (buscaCuenta.value||'').toLowerCase();
    lblEmpresaActual.textContent = emp || '(todas)';

    try {
      tbodyCuentas.innerHTML = '<tr><td colspan="5" class="px-3 py-4 text-center text-slate-500">Cargando...</td></tr>';
      
      const cuentas = await obtenerCuentasBD(emp);
      console.log('Cuentas a renderizar:', cuentas);
      
      tbodyCuentas.innerHTML = '';
      
      if(!cuentas || cuentas.length === 0){
        tbodyCuentas.innerHTML = "<tr><td colspan='5' class='px-3 py-4 text-center text-slate-500'>No hay cuentas guardadas para esta empresa.</td></tr>";
        return;
      }
      
      const frag = document.createDocumentFragment();
      cuentas.forEach(item=>{
        if(filtro && !item.nombre.toLowerCase().includes(filtro)) return;
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td class="px-3 py-2">${item.nombre}</td>
          <td class="px-3 py-2">${item.desde} &rarr; ${item.hasta}</td>
          <td class="px-3 py-2 text-right num">${fmt(item.facturado||0)}</td>
          <td class="px-3 py-2 text-right num">${item.porcentaje_ajuste||0}%</td>
          <td class="px-3 py-2 text-right">
            <div class="inline-flex gap-2">
              <button class="btnUsar border px-2 py-1 rounded bg-slate-50 hover:bg-slate-100 text-xs">Usar</button>
              <button class="btnUsarAplicar border px-2 py-1 rounded bg-blue-50 hover:bg-blue-100 text-xs">Usar y aplicar</button>
              <button class="btnEditar border px-2 py-1 rounded bg-amber-50 hover:bg-amber-100 text-xs">Editar</button>
              <button class="btnEliminar border px-2 py-1 rounded bg-rose-50 hover:bg-rose-100 text-xs text-rose-700">Eliminar</button>
            </div>
          </td>`;
        tr.querySelector('.btnUsar').addEventListener('click', ()=> usarCuenta(item,false));
        tr.querySelector('.btnUsarAplicar').addEventListener('click', ()=> usarCuenta(item,true));
        tr.querySelector('.btnEditar').addEventListener('click', ()=> editarCuenta(item));
        tr.querySelector('.btnEliminar').addEventListener('click', ()=> eliminarCuenta(item));
        frag.appendChild(tr);
      });
      tbodyCuentas.appendChild(frag);
    } catch (error) {
      console.error('Error cargando cuentas:', error);
      tbodyCuentas.innerHTML = "<tr><td colspan='5' class='px-3 py-4 text-center text-rose-600'>Error cargando cuentas: " + error.message + "</td></tr>";
    }
  }

  function usarCuenta(item, aplicar){
    selEmpresa.value = item.empresa;
    inpDesde.value = item.desde;
    inpHasta.value = item.hasta;
    if(item.facturado) document.getElementById('inp_facturado').value = fmt(item.facturado);
    if(item.porcentaje_ajuste) document.getElementById('inp_porcentaje_ajuste').value = item.porcentaje_ajuste;
    recalc();
    if(aplicar) {
      setTimeout(() => {
        formFiltros.submit();
      }, 100);
    }
  }

  async function editarCuenta(item){
    saveCuentaModal.classList.remove('hidden');
    iEmpresa.value = item.empresa;
    iRango.value = `${item.desde} → ${item.hasta}`;
    iNombre.value = item.nombre;
    iCFact.value = fmt(item.facturado||0);
    iCPorcentaje.value = item.porcentaje_ajuste || 0;
    
    // Guardar referencia al item actual
    const currentItem = item;
    
    btnDoSaveCuenta.onclick = async ()=>{
      const cuentaActualizada = {
        nombre: iNombre.value.trim() || currentItem.nombre,
        facturado: toInt(iCFact.value),
        porcentaje: parseFloat(iCPorcentaje.value) || 0
      };
      
      const result = await actualizarCuentaBD(currentItem.id, cuentaActualizada);
      if (result.success) {
        closeSaveCuenta(); 
        await renderCuentas();
        alert('Cuenta actualizada ✔');
      } else {
        alert('Error actualizando cuenta: ' + result.error);
      }
    };
  }

  async function eliminarCuenta(item){
    if(!confirm('¿Eliminar esta cuenta?')) return;
    
    const result = await eliminarCuentaBD(item.id);
    if (result.success) {
      await renderCuentas();
      alert('Cuenta eliminada ✔');
    } else {
      alert('Error eliminando cuenta: ' + result.error);
    }
  }

  async function openGestor(){
    gestorModal.classList.remove('hidden');
    await renderCuentas();
    setTimeout(()=> buscaCuenta.focus(), 0);
  }

  function closeGestor(){ gestorModal.classList.add('hidden'); }

  // ===== EVENT LISTENERS =====
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

    const cuenta = { nombre, empresa: emp, desde, hasta, facturado, porcentaje };
    
    const result = await guardarCuentaBD(cuenta);
    if (result.success) {
      closeSaveCuenta();
      alert('Cuenta guardada ✔');
    } else {
      alert('Error guardando cuenta: ' + result.error);
    }
  });

  btnShowGestor.addEventListener('click', openGestor);
  btnCloseGestor.addEventListener('click', closeGestor);
  buscaCuenta.addEventListener('input', renderCuentas);
  btnAddDesdeFiltro.addEventListener('click', ()=>{ closeGestor(); openSaveCuenta(); });

  const nf1 = el => el && el.addEventListener('input', ()=>{ el.value = fmt(toInt(el.value)); });
  nf1(document.getElementById('cuenta_facturado'));
</script>
</body>
</html>