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

/* ================= AJAX CUENTAS DE COBRO ================= */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'cuentas') {
  header('Content-Type: application/json; charset=utf-8');
  $accion = $_GET['accion'] ?? '';

  // LISTAR
  if ($accion === 'listar') {
    $empresa = $conn->real_escape_string($_GET['empresa'] ?? '');
    $data = [];
    if ($empresa !== '') {
      $sql = "SELECT id, nombre, empresa, desde, hasta, facturado, porcentaje_ajuste
              FROM cuentas_cobro_guardadas
              WHERE empresa = '$empresa'
              ORDER BY fecha_creacion DESC, id DESC";
      if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) {
          $hasta = $r['hasta'];
          if ($hasta === '0000-00-00' || $hasta === null) $hasta = '';

          $data[] = [
            'id' => (int)$r['id'],
            'nombre' => $r['nombre'],
            'empresa' => $r['empresa'],
            'desde' => $r['desde'],
            'hasta' => $hasta,
            'facturado' => (int)$r['facturado'],
            'porcentaje_ajuste' => (float)$r['porcentaje_ajuste'],
          ];
        }
      }
    }
    echo json_encode(['ok'=>true,'items'=>$data], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // GUARDAR / ACTUALIZAR (POST JSON)
  $raw = file_get_contents('php://input');
  $body = json_decode($raw, true) ?: [];

  if ($accion === 'guardar') {
    $nombre = $conn->real_escape_string($body['nombre'] ?? '');
    $empresa = $conn->real_escape_string($body['empresa'] ?? '');
    $desde = $conn->real_escape_string($body['desde'] ?? '');
    $hasta = $conn->real_escape_string($body['hasta'] ?? '');
    $facturado = (int)($body['facturado'] ?? 0);
    $porcentaje = (float)($body['porcentaje_ajuste'] ?? 0);

    if ($empresa === '' || $desde === '' || $hasta === '') {
      echo json_encode(['ok'=>false,'msg'=>'Faltan datos obligatorios.']);
      exit;
    }

    $stmt = $conn->prepare(
      "INSERT INTO cuentas_cobro_guardadas (nombre, empresa, desde, hasta, facturado, porcentaje_ajuste, fecha_creacion)
       VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );
    if (!$stmt) {
      echo json_encode(['ok'=>false,'msg'=>'Error stmt: '.$conn->error]);
      exit;
    }
    $stmt->bind_param('ssssii', $nombre, $empresa, $desde, $hasta, $facturado, $porcentaje);
    $ok = $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    if (!$ok) {
      echo json_encode(['ok'=>false,'msg'=>'Error insert: '.$conn->error]);
      exit;
    }

    echo json_encode([
      'ok'=>true,
      'item'=>[
        'id'=>$id,
        'nombre'=>$nombre,
        'empresa'=>$empresa,
        'desde'=>$desde,
        'hasta'=>$hasta,
        'facturado'=>$facturado,
        'porcentaje_ajuste'=>$porcentaje,
      ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($accion === 'actualizar') {
    $id = (int)($body['id'] ?? 0);
    $nombre = $conn->real_escape_string($body['nombre'] ?? '');
    $desde = $conn->real_escape_string($body['desde'] ?? '');
    $hasta = $conn->real_escape_string($body['hasta'] ?? '');
    $facturado = (int)($body['facturado'] ?? 0);
    $porcentaje = (float)($body['porcentaje_ajuste'] ?? 0);

    if ($id <= 0) {
      echo json_encode(['ok'=>false,'msg'=>'ID inválido']);
      exit;
    }

    $stmt = $conn->prepare(
      "UPDATE cuentas_cobro_guardadas
       SET nombre=?, desde=?, hasta=?, facturado=?, porcentaje_ajuste=?
       WHERE id=?"
    );
    if (!$stmt) {
      echo json_encode(['ok'=>false,'msg'=>'Error stmt: '.$conn->error]);
      exit;
    }
    $stmt->bind_param('sssiii', $nombre, $desde, $hasta, $facturado, $porcentaje, $id);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
      echo json_encode(['ok'=>false,'msg'=>'Error update: '.$conn->error]);
      exit;
    }
    echo json_encode(['ok'=>true]);
    exit;
  }

  if ($accion === 'eliminar') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
      echo json_encode(['ok'=>false,'msg'=>'ID inválido']);
      exit;
    }
    $stmt = $conn->prepare("DELETE FROM cuentas_cobro_guardadas WHERE id=?");
    if (!$stmt) {
      echo json_encode(['ok'=>false,'msg'=>'Error stmt: '.$conn->error]);
      exit;
    }
    $stmt->bind_param('i',$id);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
      echo json_encode(['ok'=>false,'msg'=>'Error delete: '.$conn->error]);
      exit;
    }
    echo json_encode(['ok'=>true]);
    exit;
  }

  echo json_encode(['ok'=>false,'msg'=>'Acción no reconocida']);
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

      if (isset($counts[$cat])) $counts[$cat]++; else $counts[$cat] = 1;

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

  .viajes-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,.45); display:none; align-items:center; justify-content:center; z-index:10000; }
  .viajes-backdrop.show{ display:flex; }
  .viajes-card{ width:min(720px,94vw); max-height:90vh; overflow:hidden; border-radius:16px; background:#fff;
                box-shadow:0 20px 60px rgba(0,0,0,.25); border:1px solid #e5e7eb; }
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
              <td class="px-3 py-2 text-center"></td>
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

  <!-- MODALES: préstamos, viajes, guardar cuenta, gestor de cuentas -->
  <!-- (todo igual que lo tenías, solo te dejo la parte JS del gestor diferente) -->

  <!-- ... aquí siguen tus modales de préstamos y viajes (no los repito para no hacer esto interminable) ... -->

  <!-- Modal GUARDAR CUENTA -->
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
            <span class="block text-xs font-medium mb-1">% Ajuste</span>
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

  <!-- Modal GESTOR DE CUENTAS -->
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
  /* ===== util ===== */
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

  /* ===== aquí iría todo tu JS de filas manuales, préstamos, viajes, recalc, etc
     (igual que ya lo tenías, no lo reescribo para no hacer esto infinito)
     SOLO cambio la parte de gestor de cuentas, que viene al final
  ===== */

  /* =======================================================================
     GESTOR DE CUENTAS (BD)
  ======================================================================= */
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

  const CUENTAS_API = '<?= basename(__FILE__) ?>?ajax=cuentas';

  let cuentasCache = [];

  function openSaveCuenta(item=null){
    const emp = selEmpresa.value.trim();
    if(!item && !emp){
      alert('Selecciona una EMPRESA antes de guardar la cuenta.');
      return;
    }

    if (item){
      iEmpresa.value = item.empresa;
      iRango.value   = `${item.desde} → ${item.hasta || ''}`;
      iNombre.value  = item.nombre;
      iCFact.value   = fmt(item.facturado || 0);
      iCPorcentaje.value = item.porcentaje_ajuste || 0;
      btnDoSaveCuenta.dataset.editId = item.id;
    } else {
      const d = inpDesde.value;
      const h = inpHasta.value;
      if (!d || !h) {
        alert('Debes tener llenas las fechas Desde y Hasta.');
        return;
      }
      iEmpresa.value = emp;
      iRango.value   = `${d} → ${h}`;
      iNombre.value  = `${emp} ${d} a ${h}`;
      iCFact.value   = fmt(toInt(inpFact.value));
      iCPorcentaje.value = parseFloat(inpPorcentaje.value) || 0;
      delete btnDoSaveCuenta.dataset.editId;
    }

    saveCuentaModal.classList.remove('hidden');
    setTimeout(()=> iNombre.focus(), 0);
  }
  function closeSaveCuenta(){ saveCuentaModal.classList.add('hidden'); }

  btnShowSaveCuenta.addEventListener('click', ()=> openSaveCuenta(null));
  btnCloseSaveCuenta.addEventListener('click', closeSaveCuenta);
  btnCancelSaveCuenta.addEventListener('click', closeSaveCuenta);

  btnDoSaveCuenta.addEventListener('click', async ()=>{
    const emp = iEmpresa.value.trim();
    const rango = iRango.value.split('→');
    const desde = (rango[0]||'').trim();
    const hasta = (rango[1]||'').trim();
    const nombre = iNombre.value.trim() || `${emp} ${desde} a ${hasta}`;
    const facturado = toInt(iCFact.value);
    const porcentaje = parseFloat(iCPorcentaje.value) || 0;

    if (!emp || !desde || !hasta){
      alert('Empresa, rango y fechas son obligatorios.');
      return;
    }

    const editId = btnDoSaveCuenta.dataset.editId || null;
    const accion = editId ? 'actualizar' : 'guardar';

    try{
      const res = await fetch(`${CUENTAS_API}&accion=${accion}`, {
        method: 'POST',
        headers: { 'Content-Type':'application/json' },
        body: JSON.stringify({
          id: editId ? parseInt(editId) : undefined,
          nombre, empresa:emp, desde, hasta,
          facturado, porcentaje_ajuste:porcentaje
        })
      });
      const js = await res.json();
      if(!js.ok){
        alert(js.msg || 'Error al guardar.');
        return;
      }
      closeSaveCuenta();
      loadCuentas(); // recargar lista
    }catch(e){
      console.error(e);
      alert('Error de red al guardar.');
    }
  });

  async function loadCuentas(){
    const emp = selEmpresa.value.trim();
    lblEmpresaActual.textContent = emp || '(selecciona empresa)';
    tbodyCuentas.innerHTML = '<tr><td colspan="5" class="px-3 py-3 text-center text-slate-500">Cargando…</td></tr>';
    if (!emp){
      tbodyCuentas.innerHTML = '<tr><td colspan="5" class="px-3 py-3 text-center text-slate-500">Selecciona una empresa en el filtro principal.</td></tr>';
      return;
    }
    try{
      const res = await fetch(`${CUENTAS_API}&accion=listar&empresa=`+encodeURIComponent(emp));
      const js = await res.json();
      if(!js.ok){
        tbodyCuentas.innerHTML = '<tr><td colspan="5" class="px-3 py-3 text-center text-rose-500">Error al cargar.</td></tr>';
        return;
      }
      cuentasCache = js.items || [];
      renderCuentas();
    }catch(e){
      console.error(e);
      tbodyCuentas.innerHTML = '<tr><td colspan="5" class="px-3 py-3 text-center text-rose-500">Error de red.</td></tr>';
    }
  }

  function renderCuentas(){
    const filtro = (buscaCuenta.value || '').toLowerCase();
    tbodyCuentas.innerHTML = '';
    if (!cuentasCache.length){
      tbodyCuentas.innerHTML = '<tr><td colspan="5" class="px-3 py-3 text-center text-slate-500">No hay cuentas guardadas.</td></tr>';
      return;
    }
    const frag = document.createDocumentFragment();
    cuentasCache.forEach(item=>{
      if (filtro && !item.nombre.toLowerCase().includes(filtro)) return;
      const tr = document.createElement('tr');
      const rangoTxt = `${item.desde} → ${item.hasta || ''}`;
      tr.innerHTML = `
        <td class="px-3 py-2">${item.nombre}</td>
        <td class="px-3 py-2">${rangoTxt}</td>
        <td class="px-3 py-2 text-right num">${fmt(item.facturado || 0)}</td>
        <td class="px-3 py-2 text-right num">${item.porcentaje_ajuste || 0}%</td>
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
      tr.querySelector('.btnEditar').addEventListener('click', ()=> openSaveCuenta(item));
      tr.querySelector('.btnEliminar').addEventListener('click', ()=> eliminarCuenta(item));
      frag.appendChild(tr);
    });
    tbodyCuentas.appendChild(frag);
  }

  function usarCuenta(item, aplicar){
    selEmpresa.value = item.empresa;
    inpDesde.value = item.desde;

    // AQUÍ estaba tu problema: aseguramos que SIEMPRE llenamos HASTA
    inpHasta.value = item.hasta || '';

    if (item.facturado) {
      inpFact.value = fmt(item.facturado);
    }
    if (item.porcentaje_ajuste !== null && item.porcentaje_ajuste !== undefined) {
      inpPorcentaje.value = item.porcentaje_ajuste;
    }

    // Recalcular montos (función recalc que ya tienes definida)
    if (typeof recalc === 'function') recalc();

    if (aplicar) {
      gestorModal.classList.add('hidden');
      formFiltros.submit();
    }
  }

  async function eliminarCuenta(item){
    if (!confirm('¿Eliminar la cuenta "'+item.nombre+'"?')) return;
    try{
      const res = await fetch(`${CUENTAS_API}&accion=eliminar&id=${item.id}`);
      const js = await res.json();
      if(!js.ok){
        alert(js.msg || 'Error al eliminar.');
        return;
      }
      cuentasCache = cuentasCache.filter(x=> x.id !== item.id);
      renderCuentas();
    }catch(e){
      console.error(e);
      alert('Error de red al eliminar.');
    }
  }

  function openGestor(){
    gestorModal.classList.remove('hidden');
    loadCuentas();
  }
  function closeGestor(){ gestorModal.classList.add('hidden'); }

  btnShowGestor.addEventListener('click', openGestor);
  btnCloseGestor.addEventListener('click', closeGestor);
  buscaCuenta.addEventListener('input', renderCuentas);
  btnAddDesdeFiltro.addEventListener('click', ()=>{ closeGestor(); openSaveCuenta(null); });

</script>
</body>
</html>
