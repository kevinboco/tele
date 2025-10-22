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
        <h2 class="text-2xl font-bold text-center mb-2">📅 Ajuste de pago por rango</h2>
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

/* ================= Viajes del rango ================= */
$sqlV = "SELECT nombre, ruta, empresa, tipo_vehiculo FROM viajes WHERE fecha BETWEEN '$desde' AND '$hasta'";
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

/* ================= Tarifas por vehículo ================= */
$tarifas = [];
if ($empresaFiltro !== "") {
  $resT = $conn->query("SELECT * FROM tarifas WHERE empresa='".$conn->real_escape_string($empresaFiltro)."'");
  if ($resT) while($r=$resT->fetch_assoc()) $tarifas[$r['tipo_vehiculo']] = $r;
}

/* ================= Préstamos ================= */
$prestamosList = []; $i=0;
$qPrest = "
  SELECT deudor,
         SUM(monto + monto*0.10*CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS total
  FROM prestamos
  WHERE (pagado IS NULL OR pagado=0)
  GROUP BY deudor
";
if ($rP = $conn->query($qPrest)) {
  while($r = $rP->fetch_assoc()){
    $prestamosList[] = ['id'=>$i++, 'name'=>$r['deudor'], 'key'=>norm_person($r['deudor']), 'total'=>(int)$r['total']];
  }
}

/* ================= Filas base ================= */
$filas = []; $total_facturado = 0;
foreach ($contadores as $nombre => $v) {
  $veh = $v['vehiculo'];
  $t = $tarifas[$veh] ?? ["completo"=>0,"medio"=>0,"extra"=>0,"carrotanque"=>0,"siapana"=>0];
  $total = $v['completos']*$t['completo'] + $v['medios']*$t['medio'] + $v['extras']*$t['extra'] + $v['carrotanques']*$t['carrotanque'] + $v['siapana']*$t['siapana'];
  $filas[] = ['nombre'=>$nombre,'total_bruto'=>(int)$total];
  $total_facturado += (int)$total;
}
usort($filas, fn($a,$b)=> $b['total_bruto'] <=> $a['total_bruto']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Ajuste de Pago</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  .num{font-variant-numeric:tabular-nums}
  /* Sticky header tabla */
  .table-sticky thead th{
    position:sticky;top:0;z-index:20;
    background-color:#2563eb;color:white;
  }
  /* Modal visible/oculto */
  .modal-show{display:block}
  .modal-hide{display:none}
  .opt-row{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 12px;border-bottom:1px solid #e5e7eb}
  .opt-row:hover{background:#f8fafc}
  .opt-name{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding-right:8px}
</style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">
<header class="max-w-[1600px] mx-auto px-3 md:px-4 pt-6">
  <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-5 py-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <h2 class="text-xl md:text-2xl font-bold">🧾 Ajuste de Pago</h2>
      <div class="text-sm text-slate-600">
        Periodo: <strong><?= htmlspecialchars($desde) ?></strong> &rarr; <strong><?= htmlspecialchars($hasta) ?></strong>
        <?php if ($empresaFiltro): ?><span class="mx-2">•</span> Empresa: <strong><?= htmlspecialchars($empresaFiltro) ?></strong><?php endif; ?>
      </div>
    </div>
  </div>
</header>

<main class="max-w-[1600px] mx-auto px-3 md:px-4 py-6 space-y-5">
  <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
    <div class="overflow-auto max-h-[70vh] relative rounded-xl border border-slate-200">
      <table class="min-w-[1200px] w-full text-sm table-sticky">
        <thead class="bg-blue-600 text-white">
          <tr>
            <th class="px-3 py-2 text-left">Conductor</th>
            <th class="px-3 py-2 text-right">Total viajes</th>
            <th class="px-3 py-2 text-right">Ajuste</th>
            <th class="px-3 py-2 text-right">Valor llegó</th>
            <th class="px-3 py-2 text-right">Retención 3.5%</th>
            <th class="px-3 py-2 text-right">4x1000</th>
            <th class="px-3 py-2 text-right">Aporte 10%</th>
            <th class="px-3 py-2 text-right">Seg. social</th>
            <th class="px-3 py-2 text-right">Préstamos</th>
            <th class="px-3 py-2 text-left">N° Cuenta</th>
            <th class="px-3 py-2 text-right">A pagar</th>
          </tr>
        </thead>
        <tbody id="tbody">
          <?php foreach($filas as $f): ?>
          <tr>
            <td class="px-3 py-2"><?= htmlspecialchars($f['nombre']) ?></td>
            <td class="px-3 py-2 text-right num base"><?= number_format($f['total_bruto'],0,',','.') ?></td>
            <td class="px-3 py-2 text-right num ajuste">0</td>
            <td class="px-3 py-2 text-right num llego">0</td>
            <td class="px-3 py-2 text-right num ret">0</td>
            <td class="px-3 py-2 text-right num mil4">0</td>
            <td class="px-3 py-2 text-right num apor">0</td>
            <td class="px-3 py-2 text-right"><input type="text" class="ss w-full rounded border border-slate-300 px-2 py-1 text-right num"></td>
            <td class="px-3 py-2 text-right">
              <div class="flex items-center justify-end gap-2">
                <span class="num prest">0</span>
                <button type="button" class="btn-prest text-xs px-2 py-1 rounded border border-slate-300 bg-slate-50 hover:bg-slate-100">Seleccionar</button>
              </div>
              <div class="text-[11px] text-slate-500 text-right selected-deudor"></div>
            </td>
            <td class="px-3 py-2"><input type="text" class="cta w-full rounded border border-slate-300 px-2 py-1" placeholder="N° cuenta"></td>
            <td class="px-3 py-2 text-right num pagar">0</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<!-- Modal -->
<div id="prestModal" class="modal-hide fixed inset-0 z-50">
  <div class="absolute inset-0 bg-black/30"></div>
  <div class="relative mx-auto my-8 max-w-2xl bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
      <h3 class="text-lg font-semibold">Seleccionar deudores (puedes marcar varios)</h3>
      <button id="btnCloseModal" class="p-2 rounded hover:bg-slate-100">✕</button>
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
    <div class="px-5 py-4 border-t border-slate-200 flex items-center justify-between gap-2">
      <div class="text-sm text-slate-600">Seleccionados: <span id="selCount" class="font-semibold">0</span></div>
      <div class="flex items-center gap-2">
        <div class="text-sm">Total seleccionado: <span id="selTotal" class="num font-semibold">0</span></div>
        <button id="btnCancel" class="rounded-lg border border-slate-300 px-4 py-2 bg-white hover:bg-slate-50">Cancelar</button>
        <button id="btnAssign" class="rounded-lg border border-blue-600 px-4 py-2 bg-blue-600 text-white hover:bg-blue-700">Asignar</button>
      </div>
    </div>
  </div>
</div>

<script>
const modal=document.getElementById('prestModal');
modal.classList.remove('modal-show');modal.classList.add('modal-hide');
const tbody=document.getElementById('tbody');
const btnClose=document.getElementById('btnCloseModal');
const btnCancel=document.getElementById('btnCancel');
btnClose.onclick=()=>modal.classList.replace('modal-show','modal-hide');
btnCancel.onclick=()=>modal.classList.replace('modal-show','modal-hide');
tbody.querySelectorAll('.btn-prest').forEach(b=>b.onclick=()=>modal.classList.replace('modal-hide','modal-show'));
</script>
</body>
</html>
