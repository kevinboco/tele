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

/* ================= AJAX: Viajes por conductor ================= */
if (isset($_GET['viajes_conductor'])) {
  // ... (tu código AJAX existente se mantiene igual)
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
  WHERE (pagado IS NULL OR pagado=0)
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

// Calcular totales para el dashboard
$total_conductores = count($filas);
$total_viajes = array_sum(array_map(fn($v) => 
  $v['completos'] + $v['medios'] + $v['extras'] + $v['carrotanques'] + $v['siapana'], 
  $contadores
));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Ajuste de Pago - Dashboard Moderno</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  .num { font-variant-numeric: tabular-nums; }
  .glass-effect {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
  }
  .conductor-card {
    transition: all 0.3s ease;
    border-left: 4px solid #3b82f6;
  }
  .conductor-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.1);
  }
  .progress-bar {
    transition: width 0.5s ease-in-out;
  }
  .tab-active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 4px 15px 0 rgba(116, 75, 162, 0.3);
  }
  .stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    position: relative;
    overflow: hidden;
  }
  .stat-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.1);
    transform: rotate(30deg);
  }
  .table-sticky thead tr { position: sticky; top: 0; z-index: 30; }
  .table-sticky thead th { position: sticky; top: 0; z-index: 31; background-color: #2563eb !important; color: #fff !important; }
  .viajes-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,.45); display:none; align-items:center; justify-content:center; z-index:10000; }
  .viajes-backdrop.show{ display:flex; }
  .viajes-card{ width:min(720px,94vw); max-height:90vh; overflow:hidden; border-radius:16px; background:#fff; box-shadow:0 20px 60px rgba(0,0,0,.25); border:1px solid #e5e7eb; }
  .conductor-link{cursor:pointer; color:#0d6efd; text-decoration:underline;}
</style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen text-slate-800">
  
  <!-- Header Principal -->
  <header class="bg-white/80 backdrop-blur-lg border-b border-slate-200 sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center py-4">
        <div class="flex items-center space-x-3">
          <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-xl flex items-center justify-center">
            <span class="text-white font-bold text-lg">💰</span>
          </div>
          <div>
            <h1 class="text-xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
              Ajuste de Pago
            </h1>
            <p class="text-sm text-slate-500">Sistema de gestión de pagos</p>
          </div>
        </div>
        
        <div class="flex items-center space-x-3">
          <button id="btnShowSaveCuenta" class="bg-gradient-to-r from-amber-500 to-orange-500 text-white px-4 py-2 rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all flex items-center space-x-2">
            <span>⭐</span>
            <span>Guardar Cuenta</span>
          </button>
          <button id="btnShowGestorCuentas" class="bg-gradient-to-r from-blue-500 to-cyan-500 text-white px-4 py-2 rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all flex items-center space-x-2">
            <span>📚</span>
            <span>Cuentas Guardadas</span>
          </button>
        </div>
      </div>
    </div>
  </header>

  <!-- Filtros Mejorados -->
  <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="glass-effect rounded-2xl p-6 mb-8">
      <h2 class="text-lg font-semibold text-slate-800 mb-4 flex items-center space-x-2">
        <span>🎛️</span>
        <span>Filtros del Reporte</span>
      </h2>
      <form id="formFiltros" method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Fecha Desde</label>
          <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" required 
                 class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Fecha Hasta</label>
          <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" required 
                 class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Empresa</label>
          <select name="empresa" class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
            <option value="">-- Todas --</option>
            <?php
              $resEmp2 = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
              if ($resEmp2) while ($e = $resEmp2->fetch_assoc()) {
                $sel = ($empresaFiltro==$e['empresa'])?'selected':''; ?>
                <option value="<?= htmlspecialchars($e['empresa']) ?>" <?= $sel ?>><?= htmlspecialchars($e['empresa']) ?></option>
            <?php } ?>
          </select>
        </div>
        <div class="flex items-end">
          <button class="w-full bg-gradient-to-r from-green-500 to-emerald-600 text-white py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all">
            Aplicar Filtros
          </button>
        </div>
      </form>
    </div>
  </section>

  <!-- Dashboard de Métricas -->
  <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      <!-- Tarjeta 1 -->
      <div class="stat-card rounded-2xl p-6 text-white relative overflow-hidden">
        <div class="relative z-10">
          <div class="flex items-center justify-between mb-4">
            <div class="text-3xl">👥</div>
            <div class="text-2xl font-bold"><?= $total_conductores ?></div>
          </div>
          <h3 class="text-lg font-semibold mb-1">Conductores Activos</h3>
          <p class="text-blue-100 text-sm">En el periodo seleccionado</p>
        </div>
      </div>

      <!-- Tarjeta 2 -->
      <div class="stat-card rounded-2xl p-6 text-white relative overflow-hidden">
        <div class="relative z-10">
          <div class="flex items-center justify-between mb-4">
            <div class="text-3xl">💰</div>
            <div class="text-2xl font-bold">$<?= number_format($total_facturado, 0, ',', '.') ?></div>
          </div>
          <h3 class="text-lg font-semibold mb-1">Total Facturado</h3>
          <p class="text-blue-100 text-sm">Base para cálculos</p>
        </div>
      </div>

      <!-- Tarjeta 3 -->
      <div class="stat-card rounded-2xl p-6 text-white relative overflow-hidden">
        <div class="relative z-10">
          <div class="flex items-center justify-between mb-4">
            <div class="text-3xl">🚛</div>
            <div class="text-2xl font-bold"><?= $total_viajes ?></div>
          </div>
          <h3 class="text-lg font-semibold mb-1">Total Viajes</h3>
          <p class="text-blue-100 text-sm">Todos los tipos</p>
        </div>
      </div>

      <!-- Tarjeta 4 -->
      <div class="stat-card rounded-2xl p-6 text-white relative overflow-hidden">
        <div class="relative z-10">
          <div class="flex items-center justify-between mb-4">
            <div class="text-3xl">📅</div>
            <div class="text-2xl font-bold"><?= $desde ?></div>
          </div>
          <h3 class="text-lg font-semibold mb-1">Periodo</h3>
          <p class="text-blue-100 text-sm"><?= $desde ?> a <?= $hasta ?></p>
        </div>
      </div>
    </div>
  </section>

  <!-- Panel de Montos Principales -->
  <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="bg-white rounded-2xl shadow-lg border border-slate-200 p-6 mb-8">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="text-center">
          <div class="text-xs text-slate-500 mb-1">Conductores en rango</div>
          <div class="text-2xl font-bold text-blue-600"><?= count($filas) ?></div>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Cuenta de cobro (facturado)</label>
          <input id="inp_facturado" type="text" 
                 value="<?= number_format($total_facturado,0,',','.') ?>"
                 class="w-full rounded-xl border border-slate-300 px-4 py-3 text-right num text-lg font-semibold">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Valor recibido (llegó)</label>
          <input id="inp_recibido" type="text" 
                 value="<?= number_format($total_facturado,0,',','.') ?>"
                 class="w-full rounded-xl border border-slate-300 px-4 py-3 text-right num text-lg font-semibold">
        </div>
        <div class="text-center">
          <div class="text-xs text-slate-500 mb-1">Diferencia a repartir</div>
          <div id="lbl_diferencia" class="text-2xl font-bold text-amber-600 num">0</div>
        </div>
      </div>
    </div>
  </section>

  <!-- Sistema de Pestañas -->
  <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden mb-8">
      <!-- Navegación de Pestañas -->
      <div class="flex overflow-x-auto border-b border-slate-200">
        <button class="tab-active px-8 py-4 font-semibold text-sm whitespace-nowrap transition-all" data-tab="cards">
          📋 Vista de Tarjetas
        </button>
        <button class="px-8 py-4 font-semibold text-slate-600 hover:text-slate-800 whitespace-nowrap transition-all" data-tab="table">
          📊 Vista de Tabla
        </button>
      </div>

      <!-- Contenido de Pestaña Activa -->
      <div class="p-6">
        <!-- Vista de Tarjetas -->
        <div id="tab-cards" class="tab-content active">
          <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            <?php foreach ($filas as $f): ?>
            <div class="conductor-card bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
              <div class="flex items-center justify-between mb-4">
                <div class="flex items-center space-x-3">
                  <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-full flex items-center justify-center">
                    <span class="text-white font-bold text-lg"><?= substr($f['nombre'], 0, 1) ?></span>
                  </div>
                  <div>
                    <h3 class="font-semibold text-slate-800"><?= htmlspecialchars($f['nombre']) ?></h3>
                    <p class="text-slate-500 text-sm">Base: $<?= number_format($f['total_bruto'],0,',','.') ?></p>
                  </div>
                </div>
                <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm font-semibold">
                  $<?= number_format($f['total_bruto'],0,',','.') ?>
                </span>
              </div>

              <!-- Información de Viajes -->
              <?php 
              $contador = $contadores[$f['nombre']] ?? ['completos'=>0, 'medios'=>0, 'extras'=>0, 'carrotanques'=>0, 'siapana'=>0];
              $total_viajes_conductor = array_sum($contador);
              ?>
              <div class="space-y-3 mb-4">
                <div class="flex justify-between items-center">
                  <span class="text-slate-600 text-sm">Viajes Completos:</span>
                  <span class="font-semibold"><?= $contador['completos'] ?></span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-slate-600 text-sm">Viajes Medios:</span>
                  <span class="font-semibold"><?= $contador['medios'] ?></span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-slate-600 text-sm">Viajes Extra:</span>
                  <span class="font-semibold"><?= $contador['extras'] ?></span>
                </div>
              </div>

              <!-- Acciones -->
              <div class="flex space-x-2">
                <button class="flex-1 bg-blue-500 text-white py-2 rounded-lg text-sm font-semibold hover:bg-blue-600 transition conductor-link"
                        data-nombre="<?= htmlspecialchars($f['nombre']) ?>">
                  Ver Viajes
                </button>
                <button class="flex-1 bg-slate-100 text-slate-700 py-2 rounded-lg text-sm font-semibold hover:bg-slate-200 transition">
                  Editar
                </button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Vista de Tabla (oculta inicialmente) -->
        <div id="tab-table" class="tab-content hidden">
          <div class="overflow-auto rounded-xl border border-slate-200 table-sticky">
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
                </tr>
              </thead>
              <tbody id="tbody" class="divide-y divide-slate-100 bg-white">
                <?php foreach ($filas as $f): ?>
                <tr>
                  <td class="px-3 py-2">
                    <button type="button" class="conductor-link" title="Ver viajes" data-nombre="<?= htmlspecialchars($f['nombre']) ?>">
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
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Los modales se mantienen igual que en tu código original -->
  <!-- ===== Modal PRÉSTAMOS ===== -->
  <div id="prestModal" class="hidden fixed inset-0 z-50">
    <!-- ... (tu código de modal préstamos) ... -->
  </div>

  <!-- ===== Modal VIAJES ===== -->
  <div id="viajesModal" class="viajes-backdrop">
    <!-- ... (tu código de modal viajes) ... -->
  </div>

  <!-- ===== Modal GUARDAR CUENTA ===== -->
  <div id="saveCuentaModal" class="hidden fixed inset-0 z-50">
    <!-- ... (tu código de modal guardar cuenta) ... -->
  </div>

  <!-- ===== Modal GESTOR DE CUENTAS ===== -->
  <div id="gestorCuentasModal" class="hidden fixed inset-0 z-50">
    <!-- ... (tu código de modal gestor de cuentas) ... -->
  </div>

<script>
  // ===== Sistema de Pestañas =====
  document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('[data-tab]');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabs.forEach(tab => {
      tab.addEventListener('click', function() {
        const targetTab = this.getAttribute('data-tab');
        
        // Actualizar pestañas activas
        tabs.forEach(t => {
          if (t === this) {
            t.classList.add('tab-active');
            t.classList.remove('text-slate-600');
          } else {
            t.classList.remove('tab-active');
            t.classList.add('text-slate-600');
          }
        });
        
        // Mostrar contenido correspondiente
        tabContents.forEach(content => {
          if (content.id === 'tab-' + targetTab) {
            content.classList.remove('hidden');
            content.classList.add('active');
          } else {
            content.classList.add('hidden');
            content.classList.remove('active');
          }
        });
      });
    });

    // ===== Sistema de Navegación por Conductores =====
    document.querySelectorAll('.conductor-link').forEach(link => {
      link.addEventListener('click', function() {
        const nombre = this.getAttribute('data-nombre') || this.textContent.trim();
        abrirModalViajes(nombre);
      });
    });

    // Efecto hover en tarjetas
    const cards = document.querySelectorAll('.conductor-card');
    cards.forEach(card => {
      card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-4px)';
      });
      card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
      });
    });
  });

  // ===== Resto de tu JavaScript original =====
  const COMPANY_SCOPE = <?= json_encode(($empresaFiltro ?: '__todas__')) ?>;
  const ACC_KEY   = 'cuentas:'+COMPANY_SCOPE;
  const SS_KEY    = 'seg_social:'+COMPANY_SCOPE;
  const PREST_SEL_KEY = 'prestamo_sel_multi:v2:'+COMPANY_SCOPE;
  const PERIODOS_KEY  = 'cuentas_cobro_periodos:v1';

  const PRESTAMOS_LIST = <?php echo json_encode($prestamosList, JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK); ?>;

  const toInt = (s)=>{ if(typeof s==='number') return Math.round(s); s=(s||'').toString().replace(/\./g,'').replace(/,/g,'').replace(/[^\d\-]/g,''); return parseInt(s||'0',10)||0; };
  const fmt = (n)=> (n||0).toLocaleString('es-CO');
  const getLS=(k)=>{try{return JSON.parse(localStorage.getItem(k)||'{}')}catch{return{}}}
  const setLS=(k,v)=> localStorage.setItem(k, JSON.stringify(v));

  let accMap = getLS(ACC_KEY);
  let ssMap  = getLS(SS_KEY);
  let prestSel = getLS(PREST_SEL_KEY); if(!prestSel || typeof prestSel!=='object') prestSel = {};

  const tbody = document.getElementById('tbody');

  function summarizeNames(arr){ if(!arr||arr.length===0)return''; const n=arr.map(x=>x.name); return n.length<=2?n.join(', '): n.slice(0,2).join(', ')+' +'+(n.length-2)+' más'; }
  function sumTotals(arr){ return (arr||[]).reduce((a,b)=> a+(toInt(b.total)||0),0); }

  // ... (el resto de tu JavaScript original se mantiene igual)

  // Inicializar cálculos
  const fmtInput=(el)=> el.addEventListener('input',()=>{ const raw=toInt(el.value); el.value=fmt(raw); recalc(); });
  fmtInput(document.getElementById('inp_facturado'));
  fmtInput(document.getElementById('inp_recibido'));
  recalc();
</script>

</body>
</html>