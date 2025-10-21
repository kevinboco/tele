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
$sqlV = "SELECT nombre, ruta, empresa, tipo_vehiculo
         FROM viajes
         WHERE fecha BETWEEN '$desde' AND '$hasta'";
if ($empresaFiltro !== "") {
  $empresaFiltroEsc = $conn->real_escape_string($empresaFiltro);
  $sqlV .= " AND empresa = '$empresaFiltroEsc'";
}
$resV = $conn->query($sqlV);

$contadores = [];   // nombre → contadores y vehiculo tomado del primer registro del rango
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

/* ================= Préstamos (lista multi-select + mapa) =================
   total = SUM(monto + 10% mensual acumulado) para no pagados */
$prestamosList = [];    // [{id,name,key,total}]
$i = 0;
$qPrest = "
  SELECT deudor,
         SUM(monto + monto*0.10*CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS total
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

  $filas[] = [
    'nombre'        => $nombre,
    'total_bruto'   => (int)$total,
  ];
  $total_facturado += (int)$total;
}
/* ordenar por total desc (solo presentación) */
usort($filas, fn($a,$b)=> $b['total_bruto'] <=> $a['total_bruto']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Ajuste de Pago (modal préstamos multiselección)</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  .num { font-variant-numeric: tabular-nums; }
  .table-sticky thead th { position: sticky; top: 0; z-index: 1; }
  .modal-show { display:block }
  .modal-hide { display:none }
  .opt-row { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:8px 12px; border-bottom:1px solid #e5e7eb; }
  .opt-row:hover { background:#f8fafc; }
  .opt-name { flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; padding-right:8px; }
  input[type=number]::-webkit-outer-spin-button,
  input[type=number]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
  .chip { display:inline-flex; align-items:center; gap:6px; padding:2px 8px; border-radius:999px; background:#eef2ff; border:1px solid #e5e7eb; margin-left:6px; font-size:11px; }
</style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">
  <header class="max-w-[1600px] mx-auto px-3 md:px-4 pt-6">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-5 py-4">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <h2 class="text-xl md:text-2xl font-bold">🧾 Ajuste de Pago</h2>
        <div class="text-sm text-slate-600">
          Periodo: <strong><?= htmlspecialchars($desde) ?></strong> &rarr; <strong><?= htmlspecialchars($hasta) ?></strong>
          <?php if ($empresaFiltro !== ""): ?><span class="mx-2">•</span> Empresa: <strong><?= htmlspecialchars($empresaFiltro) ?></strong><?php endif; ?>
        </div>
      </div>

      <!-- filtros -->
      <form class="mt-3 grid grid-cols-1 md:grid-cols-5 gap-3" method="get">
        <label class="block">
          <span class="block text-xs font-medium mb-1">Desde</span>
          <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" required class="w-full rounded-xl border border-slate-300 px-3 py-2">
        </label>
        <label class="block">
          <span class="block text-xs font-medium mb-1">Hasta</span>
          <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" required class="w-full rounded-xl border border-slate-300 px-3 py-2">
        </label>
        <label class="block md:col-span-2">
          <span class="block text-xs font-medium mb-1">Empresa</span>
          <select name="empresa" class="w-full rounded-xl border border-slate-300 px-3 py-2">
            <option value="">-- Todas --</option>
            <?php
              $resEmp2 = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
              if ($resEmp2) while ($e = $resEmp2->fetch_assoc()) {
                $sel = ($empresaFiltro==$e['empresa'])?'selected':'';
                echo "<option value=\"".htmlspecialchars($e['empresa'])."\" $sel>".htmlspecialchars($e['empresa'])."</option>";
              }
            ?>
          </select>
        </label>
        <div class="flex md:items-end">
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
          <span class="block text-xs font-medium mb-1">Valor recibido (llegó)</span>
          <input id="inp_recibido" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right num"
                 value="<?= number_format($total_facturado,0,',','.') ?>">
        </label>
        <div>
          <div class="text-xs text-slate-500 mb-1">Diferencia a repartir</div>
          <div id="lbl_diferencia" class="text-lg font-semibold text-amber-600 num">0</div>
        </div>
      </div>
    </section>

    <!-- Tabla principal -->
    <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
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
            </tr>
          </thead>
          <tbody id="tbody" class="divide-y divide-slate-100 bg-white">
            <?php foreach ($filas as $f): ?>
            <tr>
              <td class="px-3 py-2"><?= htmlspecialchars($f['nombre']) ?></td>
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
    </section>
  </main>

  <!-- ===== Modal de selección de préstamos (multi) ===== -->
  <div id="prestModal" class="modal-hide fixed inset-0 z-50">
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
  // ====== Datos de servidor a JS ======
  // ---- Claves de almacenamiento ----
  // Mantener por empresa (o todas), NO por fechas
  const COMPANY_SCOPE = <?= json_encode(($empresaFiltro ?: '__todas__')) ?>;

  // Persistir cuentas y SS por empresa (sobreviven a cambios de fechas)
  const ACC_KEY   = 'cuentas:'+COMPANY_SCOPE;
  const SS_KEY    = 'seg_social:'+COMPANY_SCOPE;

  // NUEVA clave por empresa para préstamos (v2, sin fechas)
  const PREST_SEL_KEY = 'prestamo_sel_multi:v2:'+COMPANY_SCOPE;

  // Prefijo de claves viejas con fechas (para migración automática)
  const OLD_PREST_PREFIX = 'prestamo_sel_multi:' + (COMPANY_SCOPE + '|');

  const PRESTAMOS_LIST = <?php echo json_encode($prestamosList, JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK); ?>;

  // ====== Helpers ======
  const toInt = (s)=> {
    if (typeof s === 'number') return Math.round(s);
    s = (s||'').toString().replace(/\./g,'').replace(/,/g,'').replace(/[^\d\-]/g,'');
    return parseInt(s||'0',10) || 0;
  };
  const fmt = (n)=> (n||0).toLocaleString('es-CO');
  function normPerson(jsStr){
    let s = (jsStr||'').normalize('NFD').replace(/[\u0300-\u036f]/g,''); // quita tildes
    s = s.toLowerCase().replace(/[^a-z0-9\s]/g,' ').replace(/\s+/g,' ').trim();
    return s;
  }
  function getLS(k){ try{ return JSON.parse(localStorage.getItem(k)||'{}'); }catch{return{};} }
  function setLS(k,v){ localStorage.setItem(k, JSON.stringify(v)); }

  // ====== Persistencia ======
  let accMap   = getLS(ACC_KEY);
  let ssMap    = getLS(SS_KEY);

  // Carga nueva clave sin fechas
  let prestSel = getLS(PREST_SEL_KEY); // baseName → array de {id,name,total}

  // --- Migración automática desde claves antiguas con fechas ---
  if (!prestSel || Object.keys(prestSel).length === 0) {
    try {
      const matches = [];
      for (let i = 0; i < localStorage.length; i++) {
        const k = localStorage.key(i) || '';
        if (k.startsWith(OLD_PREST_PREFIX)) matches.push(k);
      }
      if (matches.length === 1) {
        const oldData = JSON.parse(localStorage.getItem(matches[0]) || '{}');
        if (oldData && typeof oldData === 'object') {
          prestSel = oldData;
          setLS(PREST_SEL_KEY, prestSel); // guardamos ya en la nueva clave sin fechas
        }
      }
    } catch (e) { /* noop */ }
  }

  // Migración de estructura (si alguna fila estuvo guardada como objeto simple)
  if (prestSel && typeof prestSel === 'object') {
    Object.keys(prestSel).forEach(k=>{
      if (!Array.isArray(prestSel[k])) {
        const v = prestSel[k];
        prestSel[k] = v && typeof v==='object' ? [v] : [];
      }
    });
  } else {
    prestSel = {};
  }

  const tbody = document.getElementById('tbody');

  function summarizeNames(arr){
    if (!arr || arr.length===0) return '';
    const names = arr.map(x=>x.name);
    if (names.length <= 2) return names.join(', ');
    return names.slice(0,2).join(', ') + ` +${names.length-2} más`;
  }
  function sumTotals(arr){ return (arr||[]).reduce((a,b)=> a + (toInt(b.total)||0), 0); }

  // ====== Inicialización de filas ======
  Array.from(tbody.querySelectorAll('tr')).forEach(tr=>{
    const cta = tr.querySelector('input.cta');
    const ss  = tr.querySelector('input.ss');
    const baseName = tr.children[0].innerText.trim();
    const prestSpan = tr.querySelector('.prest');
    const selLabel  = tr.querySelector('.selected-deudor');

    // Restaurar cuenta y SS
    if (accMap[baseName]) cta.value = accMap[baseName];
    if (ssMap[baseName])  ss.value  = fmt(toInt(ssMap[baseName]));

    // Restaurar préstamos múltiples
    const chosen = prestSel[baseName] || [];
    prestSpan.textContent = fmt(sumTotals(chosen));
    selLabel.textContent  = summarizeNames(chosen);

    cta.addEventListener('change', ()=>{ accMap[baseName] = cta.value.trim(); setLS(ACC_KEY, accMap); });
    ss.addEventListener('input',   ()=>{ ssMap[baseName]  = toInt(ss.value);  setLS(SS_KEY, ssMap);  recalc(); });
  });

  // ====== Modal multi-selección ======
  const modal = document.getElementById('prestModal');
  const btnAssign = document.getElementById('btnAssign');
  const btnCancel = document.getElementById('btnCancel');
  const btnClose  = document.getElementById('btnCloseModal');
  const btnClear  = document.getElementById('btnClearSel');
  const btnSelectAll = document.getElementById('btnSelectAll');
  const btnUnselectAll = document.getElementById('btnUnselectAll');
  const inputSearch = document.getElementById('prestSearch');
  const listHost = document.getElementById('prestList');
  const selCount = document.getElementById('selCount');
  const selTotal = document.getElementById('selTotal');

  let currentRow = null;       // <tr> activo
  let selectedIds = new Set(); // ids seleccionados temporalmente (en el modal)
  let filteredIdx = [];        // índices visibles en el listado (después del filtro)

  function renderPrestList(filter=''){
    listHost.innerHTML = '';
    const nf = normPerson(filter);
    filteredIdx = [];
    const frag = document.createDocumentFragment();
    PRESTAMOS_LIST.forEach((item, idx)=>{
      if (nf && !item.key.includes(nf)) return;
      filteredIdx.push(idx);

      const row = document.createElement('label');
      row.className = 'opt-row';

      const left = document.createElement('div');
      left.style.display='flex';
      left.style.alignItems='center';
      left.style.gap='10px';

      const cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.dataset.id = item.id;
      cb.checked = selectedIds.has(item.id);

      const name = document.createElement('span');
      name.className = 'opt-name';
      name.textContent = item.name;

      left.appendChild(cb);
      left.appendChild(name);

      const total = document.createElement('span');
      total.className = 'num font-semibold';
      total.textContent = (item.total||0).toLocaleString('es-CO');

      row.appendChild(left);
      row.appendChild(total);

      cb.addEventListener('change', ()=>{
        if (cb.checked) selectedIds.add(item.id); else selectedIds.delete(item.id);
        updateSelSummary();
      });

      frag.appendChild(row);
    });
    listHost.appendChild(frag);
    updateSelSummary();
  }

  function updateSelSummary(){
    const arr = PRESTAMOS_LIST.filter(it=> selectedIds.has(it.id));
    selCount.textContent = arr.length;
    const tot = arr.reduce((a,b)=> a + (b.total||0), 0);
    selTotal.textContent = tot.toLocaleString('es-CO');
  }

  function openModalForRow(tr){
    currentRow = tr;
    selectedIds = new Set();

    // Pre-cargar lo ya seleccionado para esa fila
    const baseName = currentRow.children[0].innerText.trim();
    const chosen = prestSel[baseName] || [];
    chosen.forEach(x=> selectedIds.add(Number(x.id)));

    inputSearch.value = '';
    renderPrestList('');
    modal.classList.remove('modal-hide');
    modal.classList.add('modal-show');

    // 👇 Auto-focus inmediato en el buscador (y seleccionar el texto)
    requestAnimationFrame(() => {
      inputSearch.focus();
      inputSearch.select();
    });
  }
  function closeModal(){
    modal.classList.remove('modal-show');
    modal.classList.add('modal-hide');
    currentRow = null;
    selectedIds = new Set();
    filteredIdx = [];
  }

  btnCancel.addEventListener('click', closeModal);
  btnClose.addEventListener('click', closeModal);

  btnClear.addEventListener('click', ()=>{
    if (!currentRow) return;
    const baseName = currentRow.children[0].innerText.trim();
    currentRow.querySelector('.prest').textContent = '0';
    currentRow.querySelector('.selected-deudor').textContent = '';
    delete prestSel[baseName];
    setLS(PREST_SEL_KEY, prestSel);
    recalc();
    // también limpiar selección temporal
    selectedIds.clear();
    renderPrestList(inputSearch.value);
  });

  btnSelectAll.addEventListener('click', ()=>{
    filteredIdx.forEach(idx=> selectedIds.add(PRESTAMOS_LIST[idx].id));
    renderPrestList(inputSearch.value);
  });
  btnUnselectAll.addEventListener('click', ()=>{
    filteredIdx.forEach(idx=> selectedIds.delete(PRESTAMOS_LIST[idx].id));
    renderPrestList(inputSearch.value);
  });

  btnAssign.addEventListener('click', ()=>{
    if (!currentRow) return;
    const baseName = currentRow.children[0].innerText.trim();

    const chosen = PRESTAMOS_LIST.filter(it=> selectedIds.has(it.id))
      .map(it=> ({ id: it.id, name: it.name, total: it.total }));

    prestSel[baseName] = chosen;
    setLS(PREST_SEL_KEY, prestSel);

    currentRow.querySelector('.prest').textContent = (sumTotals(chosen)).toLocaleString('es-CO');
    currentRow.querySelector('.selected-deudor').textContent = summarizeNames(chosen);

    recalc();
    closeModal();
  });

  inputSearch.addEventListener('input', ()=> renderPrestList(inputSearch.value));

  // Botones "Seleccionar" por fila
  tbody.querySelectorAll('.btn-prest').forEach(btn=>{
    btn.addEventListener('click', ()=> openModalForRow(btn.closest('tr')));
  });

  // ====== Listener global para tipear directo en el buscador del modal ======
  document.addEventListener('keydown', (e) => {
    const isOpen = modal.classList.contains('modal-show');
    if (!isOpen) return;

    const activeTag = (document.activeElement && document.activeElement.tagName) || '';
    const isTextInput = ['INPUT','TEXTAREA'].includes(activeTag);
    const isTypingKey = e.key.length === 1 || e.key === 'Backspace' || e.key === 'Delete';

    // Si el modal está abierto y no estamos en otro input, redirigir teclas al buscador
    if (!isTextInput && isTypingKey) {
      inputSearch.focus();
      if (e.key.length === 1) {
        const v = inputSearch.value || '';
        inputSearch.value = v + e.key;
        const evt = new Event('input', { bubbles: true });
        inputSearch.dispatchEvent(evt);
      } else {
        // Backspace/Delete sin carácter
        const evt = new Event('input', { bubbles: true });
        inputSearch.dispatchEvent(evt);
      }
      e.preventDefault();
    }
  });

  // ====== Distribución igualitaria ======
  function distribIgual(diff, n){
    const arr = new Array(n).fill(0);
    if (n<=0 || diff===0) return arr;
    const s = diff>=0?1:-1;
    let a = Math.abs(diff);
    const base = Math.floor(a/n);
    let resto = a % n;
    for (let i=0;i<n;i++){
      arr[i]= s*base + (resto>0? s:0);
      if (resto>0) resto--;
    }
    return arr;
  }

  // ====== Totales ======
  const inpFact = document.getElementById('inp_facturado');
  const inpRec  = document.getElementById('inp_recibido');
  const lblDif  = document.getElementById('lbl_diferencia');

  const totLleg = document.getElementById('tot_valor_llego');
  const totRet  = document.getElementById('tot_retencion');
  const totMil4 = document.getElementById('tot_4x1000');
  const totAp   = document.getElementById('tot_aporte');
  const totSS   = document.getElementById('tot_ss');
  const totPrest= document.getElementById('tot_prestamos');
  const totPag  = document.getElementById('tot_pagar');

  function recalc(){
    const fact = toInt(inpFact.value);
    const rec  = toInt(inpRec.value);
    const diff = fact - rec;
    lblDif.textContent = fmt(diff);

    const rows = Array.from(tbody.querySelectorAll('tr'));
    const ajustes = distribIgual(diff, rows.length);

    let sumLleg=0,sumRet=0,sumMil4=0,sumAp=0,sumSS=0,sumPrest=0,sumPagar=0;

    rows.forEach((tr,i)=>{
      const base  = toInt(tr.querySelector('.base').textContent);
      const prest = toInt(tr.querySelector('.prest').textContent);
      const aj    = ajustes[i]||0; // positivo: se resta
      const llego = base - aj;

      const ret  = Math.round(llego*0.035);
      const mil4 = Math.round(llego*0.004);
      const ap   = Math.round(llego*0.10);
      const ss   = toInt(tr.querySelector('input.ss').value);

      const pagar = llego - ret - mil4 - ap - ss - prest;

      tr.querySelector('.ajuste').textContent = (aj===0?'0':(aj>0?'-'+fmt(aj):'+'+fmt(Math.abs(aj))));
      tr.querySelector('.llego').textContent  = fmt(llego);
      tr.querySelector('.ret').textContent    = fmt(ret);
      tr.querySelector('.mil4').textContent   = fmt(mil4);
      tr.querySelector('.apor').textContent   = fmt(ap);
      tr.querySelector('.pagar').textContent  = fmt(pagar);

      sumLleg+=llego; sumRet+=ret; sumMil4+=mil4; sumAp+=ap; sumSS+=ss; sumPrest+=prest; sumPagar+=pagar;
    });

    totLleg.textContent = fmt(sumLleg);
    totRet.textContent  = fmt(sumRet);
    totMil4.textContent = fmt(sumMil4);
    totAp.textContent   = fmt(sumAp);
    totSS.textContent   = fmt(sumSS);
    totPrest.textContent= fmt(sumPrest);
    totPag.textContent  = fmt(sumPagar);
  }

  // Inputs de totales principales (con formatter en vivo)
  function numberInputFormatter(el){
    el.addEventListener('input', ()=>{
      const raw = toInt(el.value);
      el.value = fmt(raw);
      recalc();
    });
  }
  numberInputFormatter(document.getElementById('inp_facturado'));
  numberInputFormatter(document.getElementById('inp_recibido'));

  // Primer cálculo
  recalc();
</script>

</body>
</html>
