<?php
include("nav.php");

/* ================== Conexión BD ================== */
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }
$conn->set_charset('utf8mb4');

/* ================== Helpers (PHP) ================== */
function strip_accents_php($s){
  $t = @iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);
  if ($t !== false) return $t;
  $repl = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N'];
  return strtr($s,$repl);
}
function norm_person_php($s){
  $s = strip_accents_php((string)$s);
  $s = mb_strtolower($s,'UTF-8');
  $s = preg_replace('/[^a-z0-9\s]/',' ', $s);
  $s = preg_replace('/\s+/',' ', trim($s));
  return $s;
}

/* ================== Form inicial ================== */
if (!isset($_GET['desde']) || !isset($_GET['hasta'])) {
  $empresas = [];
  $resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
  if ($resEmp) while ($r = $resEmp->fetch_assoc()) $empresas[] = $r['empresa'];
  ?>
  <!DOCTYPE html>
  <html lang="es"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Ajuste de Pago</title>
    <script src="https://cdn.tailwindcss.com"></script>
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

/* ================== Parámetros ================== */
$desde = $_GET['desde'];
$hasta = $_GET['hasta'];
$empresaFiltro = $_GET['empresa'] ?? "";

/* ================== Viajes del rango ================== */
$sqlV = "SELECT nombre, ruta, empresa, tipo_vehiculo
         FROM viajes
         WHERE fecha BETWEEN '$desde' AND '$hasta'";
if ($empresaFiltro !== "") {
  $empresaFiltroEsc = $conn->real_escape_string($empresaFiltro);
  $sqlV .= " AND empresa = '$empresaFiltroEsc'";
}
$resV = $conn->query($sqlV);

$contadores = [];   // nombre => contadores
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

/* ================== Tarifas por vehículo ================== */
$tarifas = [];
if ($empresaFiltro !== "") {
  $resT = $conn->query("SELECT * FROM tarifas WHERE empresa='".$conn->real_escape_string($empresaFiltro)."'");
  if ($resT) while($r=$resT->fetch_assoc()) $tarifas[$r['tipo_vehiculo']] = $r;
}

/* ================== PRESTAMOS para el modal ==================
   - Agrupados por deudor EXACTO (como aparece en la tabla préstamos)
   - Cada opción tiene: id incremental, name (deudor), key normalizada, total pendiente (con interés)
============================================================== */
$prestamosList = [];  // [{id,name,key,total}]
$i = 1;
$qPrest = "
  SELECT deudor,
         SUM(monto + monto*0.10*CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS total
  FROM prestamos
  WHERE (pagado IS NULL OR pagado=0)
  GROUP BY deudor
  ORDER BY deudor ASC
";
if ($rP = $conn->query($qPrest)) {
  while($r = $rP->fetch_assoc()){
    $prestamosList[] = [
      'id'    => $i++,
      'name'  => $r['deudor'],
      'key'   => norm_person_php($r['deudor']),
      'total' => (int)round($r['total'])
    ];
  }
}

/* ================== Filas a mostrar ================== */
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
<title>Ajuste de Pago</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  .num { font-variant-numeric: tabular-nums; }
  .table-sticky thead th { position: sticky; top: 0; z-index: 1; }
  input[type=number]::-webkit-outer-spin-button,
  input[type=number]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

  /* ===== Modal ===== */
  .modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center}
  .modal-card{width:min(860px,94vw);max-height:90vh;overflow:hidden;border-radius:16px;background:#fff;box-shadow:0 20px 60px rgba(0,0,0,.25)}
  .modal-header{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #eef2f7}
  .modal-body{padding:14px 16px;overflow:auto}
  .modal-footer{display:flex;gap:10px;justify-content:flex-end;padding:12px 16px;border-top:1px solid #eef2f7}
  .modal-hide{display:none}
  .modal-show{display:flex}
  .opt-row{display:flex;align-items:center;justify-content:space-between;border:1px solid #edf2ff;background:#fbfdff;padding:8px 10px;border-radius:10px;margin-bottom:8px}
  .opt-name{font-weight:600}
  .pill{display:inline-flex;align-items:center;gap:6px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:999px;padding:2px 8px;font-size:12px}
</style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">

  <!-- Encabezado -->
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
                $sel = ($empresaFiltro==$e['empresa'])?'selected':''; ?>
                <option value="<?= htmlspecialchars($e['empresa']) ?>" <?= $sel ?>><?= htmlspecialchars($e['empresa']) ?></option>
            <?php } ?>
          </select>
        </label>
        <div class="flex md:items-end">
          <button class="w-full rounded-xl bg-blue-600 text-white py-2.5 font-semibold shadow">Aplicar</button>
        </div>
      </form>
    </div>
  </header>

  <!-- Panel montos -->
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
              <td class="px-3 py-2">
                <div class="flex items-center gap-2">
                  <span class="font-medium"><?= htmlspecialchars($f['nombre']) ?></span>
                </div>
              </td>
              <td class="px-3 py-2 text-right num base"><?= number_format($f['total_bruto'],0,',','.') ?></td>
              <td class="px-3 py-2 text-right num ajuste">0</td>
              <td class="px-3 py-2 text-right num llego">0</td>
              <td class="px-3 py-2 text-right num ret">0</td>
              <td class="px-3 py-2 text-right num mil4">0</td>
              <td class="px-3 py-2 text-right num apor">0</td>
              <td class="px-3 py-2 text-right"><input type="text" class="ss w-full max-w-[120px] rounded-lg border border-slate-300 px-2 py-1 text-right num" value=""></td>
              <td class="px-3 py-2 text-right">
                <div class="flex items-center gap-2 justify-end">
                  <span class="prest num min-w-[90px] text-right inline-block">0</span>
                  <button type="button" class="btn-prest text-xs px-2 py-1 rounded bg-blue-600 text-white">Seleccionar</button>
                </div>
                <div class="text-[11px] text-slate-500 mt-1 selected-deudor"></div>
              </td>
              <td class="px-3 py-2"><input type="text" class="cta w-full max-w-[180px] rounded-lg border border-slate-300 px-2 py-1" placeholder="N° cuenta"></td>
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

  <!-- ===== Modal préstamos multi-selección ===== -->
  <div id="prestModal" class="modal-backdrop modal-hide">
    <div class="modal-card">
      <div class="modal-header">
        <h3 class="font-bold">Préstamos pendientes</h3>
        <button id="btnCloseModal" class="px-2 py-1 rounded border border-slate-300 text-slate-700">✕</button>
      </div>
      <div class="modal-body">
        <div class="flex flex-wrap items-center gap-2 mb-3">
          <input id="prestSearch" type="text" placeholder="Buscar deudor…" class="w-full md:w-80 rounded-lg border border-slate-300 px-3 py-2">
          <span class="pill">Seleccionados: <span id="selCount">0</span></span>
          <span class="pill">Total: <span id="selTotal" class="num">0</span></span>
          <div class="ml-auto flex gap-2">
            <button id="btnSelectAll" class="px-3 py-1 rounded bg-slate-100 border">Seleccionar visibles</button>
            <button id="btnUnselectAll" class="px-3 py-1 rounded bg-slate-100 border">Quitar visibles</button>
            <button id="btnClearSel" class="px-3 py-1 rounded bg-rose-50 border border-rose-200 text-rose-700">Limpiar</button>
          </div>
        </div>
        <div id="prestList"></div>
      </div>
      <div class="modal-footer">
        <button id="btnCancel" class="px-3 py-2 rounded bg-slate-100 border">Cancelar</button>
        <button id="btnAssign" class="px-3 py-2 rounded bg-blue-600 text-white">Asignar a la fila</button>
      </div>
    </div>
  </div>

<script>
  /* ===== Helpers JS ===== */
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

  // ===== Datos del servidor =====
  const PRESTAMOS_LIST = <?php echo json_encode($prestamosList, JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK); ?>;

  // ===== Persistencia GLOBAL (independiente de fecha/empresa) =====
  const ACC_KEY_GLOBAL      = 'cuentas_global_v1';            // nKey -> nro cuenta
  const SS_KEY_GLOBAL       = 'seg_social_global_v1';         // nKey -> seg social
  const PREST_SEL_GLOBAL    = 'prestamo_sel_multi_global_v1'; // nKey -> [{id,name,total},...]

  // (Compat) migra desde llaves antiguas por rango si existen
  const KEY_SCOPE_OLD = <?= json_encode(($empresaFiltro?:'__todas__').'|'.$desde.'|'.$hasta) ?>;
  const ACC_KEY_OLD   = 'cuentas:'+KEY_SCOPE_OLD;
  const SS_KEY_OLD    = 'seg_social:'+KEY_SCOPE_OLD;
  const PREST_SEL_OLD = 'prestamo_sel_multi:'+KEY_SCOPE_OLD;

  let accGlobal   = getLS(ACC_KEY_GLOBAL);
  let ssGlobal    = getLS(SS_KEY_GLOBAL);
  let prestGlobal = getLS(PREST_SEL_GLOBAL);

  (function migrateScopedToGlobal(){
    const accOld   = getLS(ACC_KEY_OLD);
    const ssOld    = getLS(SS_KEY_OLD);
    let prestOld   = getLS(PREST_SEL_OLD);
    Object.keys(prestOld||{}).forEach(k=>{
      if (!Array.isArray(prestOld[k])) {
        const v = prestOld[k];
        prestOld[k] = v && typeof v==='object' ? [v] : [];
      }
    });
    for (const baseName in accOld) {
      const nk = normPerson(baseName);
      if (!accGlobal[nk]) accGlobal[nk] = accOld[baseName];
    }
    for (const baseName in ssOld) {
      const nk = normPerson(baseName);
      if (!ssGlobal[nk]) ssGlobal[nk] = ssOld[baseName];
    }
    for (const baseName in prestOld) {
      const nk = normPerson(baseName);
      if (!prestGlobal[nk]) prestGlobal[nk] = prestOld[baseName];
    }
    setLS(ACC_KEY_GLOBAL, accGlobal);
    setLS(SS_KEY_GLOBAL, ssGlobal);
    setLS(PREST_SEL_GLOBAL, prestGlobal);
    // localStorage.removeItem(ACC_KEY_OLD);
    // localStorage.removeItem(SS_KEY_OLD);
    // localStorage.removeItem(PREST_SEL_OLD);
  })();

  // ===== Inicialización de filas =====
  const tbody = document.getElementById('tbody');

  Array.from(tbody.querySelectorAll('tr')).forEach(tr=>{
    const nameText = tr.children[0].innerText.trim();
    const nKey     = normPerson(nameText);
    const cta      = tr.querySelector('input.cta');
    const ss       = tr.querySelector('input.ss');
    const prestSpan= tr.querySelector('.prest');
    const selLabel = tr.querySelector('.selected-deudor');

    if (accGlobal[nKey]) cta.value = accGlobal[nKey];
    if (ssGlobal[nKey])  ss.value  = fmt(toInt(ssGlobal[nKey]));
    const chosen = prestGlobal[nKey] || [];
    prestSpan.textContent = fmt(sumTotals(chosen));
    selLabel.textContent  = summarizeNames(chosen);

    cta.addEventListener('change', ()=>{
      accGlobal[nKey] = cta.value.trim(); setLS(ACC_KEY_GLOBAL, accGlobal);
    });
    ss.addEventListener('input', ()=>{
      ssGlobal[nKey] = toInt(ss.value); setLS(SS_KEY_GLOBAL, ssGlobal); recalc();
    });
  });

  // ===== Funciones cálculo =====
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

  function sumTotals(arr){ return (arr||[]).reduce((a,b)=> a + (toInt(b.total)||0), 0); }
  function summarizeNames(arr){
    if (!arr || arr.length===0) return '';
    const names = arr.map(x=>x.name);
    if (names.length <= 2) return names.join(', ');
    return names.slice(0,2).join(', ') + ` +${names.length-2} más`;
    }

  function recalc(){
    const fact = toInt(inpFact.value);
    const rec  = toInt(inpRec.value);
    const diff = fact - rec;
    lblDif.textContent = fmt(diff);

    const rows = Array.from(tbody.querySelectorAll('tr'));
    const ajustes = distribIgual(diff, rows.length);

    let sumLleg=0,sumRet=0,sumMil4=0,sumAp=0,sumSS=0,sumPrest=0,sumPagar=0;

    rows.forEach((tr,i)=>{
      const nameText = tr.children[0].innerText.trim();
      const nKey     = normPerson(nameText);

      const base  = toInt(tr.querySelector('.base').textContent);
      const prest = sumTotals(prestGlobal[nKey] || []);
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
      tr.querySelector('.prest').textContent  = fmt(prest);
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

  inpFact.addEventListener('input', recalc);
  inpRec.addEventListener('input', recalc);

  /* ===== Modal de préstamos (multi-selección) ===== */
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

  let currentRow = null;
  let currentKey = null;
  let selectedIds = new Set();
  let filteredIdx = [];

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

      left.appendChild(cb); left.appendChild(name);

      const total = document.createElement('span');
      total.className = 'num font-semibold';
      total.textContent = (item.total||0).toLocaleString('es-CO');

      row.appendChild(left); row.appendChild(total);

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
    const nameText = tr.children[0].innerText.trim();
    currentKey = normPerson(nameText);

    selectedIds = new Set();
    (prestGlobal[currentKey] || []).forEach(x=> selectedIds.add(Number(x.id)));

    inputSearch.value = '';
    renderPrestList('');
    modal.classList.remove('modal-hide');
    modal.classList.add('modal-show');
  }
  function closeModal(){
    modal.classList.remove('modal-show');
    modal.classList.add('modal-hide');
    currentRow = null;
    currentKey = null;
    selectedIds = new Set();
    filteredIdx = [];
  }

  btnCancel.addEventListener('click', closeModal);
  btnClose.addEventListener('click', closeModal);

  btnClear.addEventListener('click', ()=>{
    if (!currentRow || !currentKey) return;
    currentRow.querySelector('.prest').textContent = '0';
    currentRow.querySelector('.selected-deudor').textContent = '';
    delete prestGlobal[currentKey];
    setLS(PREST_SEL_GLOBAL, prestGlobal);
    recalc();
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
    if (!currentRow || !currentKey) return;
    const chosen = PRESTAMOS_LIST.filter(it=> selectedIds.has(it.id))
      .map(it=> ({ id: it.id, name: it.name, total: it.total }));

    prestGlobal[currentKey] = chosen;
    setLS(PREST_SEL_GLOBAL, prestGlobal);

    currentRow.querySelector('.prest').textContent = fmt(sumTotals(chosen));
    currentRow.querySelector('.selected-deudor').textContent = summarizeNames(chosen);
    recalc();
    closeModal();
  });

  inputSearch.addEventListener('input', ()=> renderPrestList(inputSearch.value));
  tbody.querySelectorAll('.btn-prest').forEach(btn=>{
    btn.addEventListener('click', ()=> openModalForRow(btn.closest('tr')));
  });

  // Primer cálculo
  recalc();
</script>
</body>
</html>
