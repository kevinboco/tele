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

/* ================= Préstamos (lista para modal y mapa normalizado) =================
   total = SUM(monto + 10% mensual acumulado) para no pagados */
$prestamosMap = [];     // key = norm_person(deudor) → total
$prestamosList = [];    // [{name, key, total}]
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
    $prestamosMap[$key] = $total;
    $prestamosList[] = ['name'=>$name, 'key'=>$key, 'total'=>$total];
  }
}

/* (Opcional) Nombres base de conductores_admin para sugerencias del input conductor (lo dejamos por si luego vuelves a usarlo) */
$adminNombres = [];
$ra = $conn->query("SELECT nombre FROM conductores_admin ORDER BY nombre ASC");
if ($ra) while($rr=$ra->fetch_assoc()) $adminNombres[] = $rr['nombre'];

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
<title>Ajuste de Pago (modal de préstamos)</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  .num { font-variant-numeric: tabular-nums; }
  .table-sticky thead th { position: sticky; top: 0; z-index: 1; }
  .modal-show { display:block }
  .modal-hide { display:none }
  .opt-active { background:#e5f0ff; border-color:#cfe0ff }
  input[type=number]::-webkit-outer-spin-button,
  input[type=number]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
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

  <!-- ===== Modal de selección de préstamos ===== -->
  <div id="prestModal" class="modal-hide fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/30"></div>
    <div class="relative mx-auto my-8 max-w-2xl bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
        <h3 class="text-lg font-semibold">Seleccionar deudor (préstamo)</h3>
        <button id="btnCloseModal" class="p-2 rounded hover:bg-slate-100" title="Cerrar">✕</button>
      </div>
      <div class="p-4">
        <div class="flex flex-col md:flex-row md:items-center gap-3 mb-3">
          <input id="prestSearch" type="text" placeholder="Buscar deudor..." class="w-full rounded-xl border border-slate-300 px-3 py-2">
          <button id="btnClearSel" class="rounded-lg border border-slate-300 px-3 py-2 text-sm bg-slate-50 hover:bg-slate-100">Quitar selección</button>
        </div>
        <div id="prestList" class="max-h-[50vh] overflow-auto rounded-xl border border-slate-200"></div>
      </div>
      <div class="px-5 py-4 border-t border-slate-200 flex items-center justify-end gap-2">
        <button id="btnCancel" class="rounded-lg border border-slate-300 px-4 py-2 bg-white hover:bg-slate-50">Cancelar</button>
        <button id="btnAssign" class="rounded-lg border border-blue-600 px-4 py-2 bg-blue-600 text-white hover:bg-blue-700">Asignar</button>
      </div>
    </div>
  </div>

<script>
  // ====== Datos de servidor a JS ======
  const KEY_SCOPE = <?= json_encode(($empresaFiltro?:'__todas__').'|'.$desde.'|'.$hasta) ?>;
  const ACC_KEY   = 'cuentas:'+KEY_SCOPE;
  const SS_KEY    = 'seg_social:'+KEY_SCOPE;
  const PREST_SEL_KEY = 'prestamo_sel:'+KEY_SCOPE; // asignación del deudor a cada fila (baseName)

  const PRESTAMOS_MAP  = <?php echo json_encode($prestamosMap,  JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK); ?>;
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
  let prestSel = getLS(PREST_SEL_KEY); // baseName → {key,name,total}

  const tbody = document.getElementById('tbody');

  // ====== Inicialización de filas ======
  Array.from(tbody.querySelectorAll('tr')).forEach(tr=>{
    const cta = tr.querySelector('input.cta');
    const ss  = tr.querySelector('input.ss');
    const baseName = tr.children[0].innerText.trim();
    const prestSpan = tr.querySelector('.prest');
    const selLabel  = tr.querySelector('.selected-deudor');

    // Restaurar pguarda: cuenta y ss
    if (accMap[baseName]) cta.value = accMap[baseName];
    if (ssMap[baseName])  ss.value  = fmt(toInt(ssMap[baseName]));

    // Restaurar selección de préstamo si existía
    if (prestSel[baseName]) {
      prestSpan.textContent = fmt(toInt(prestSel[baseName].total));
      selLabel.textContent  = prestSel[baseName].name || '';
    }

    // Persistir cambios
    cta.addEventListener('change', ()=>{ accMap[baseName] = cta.value.trim(); setLS(ACC_KEY, accMap); });
    ss.addEventListener('input',   ()=>{ ssMap[baseName]  = toInt(ss.value);  setLS(SS_KEY, ssMap);  recalc(); });
  });

  // ====== Modal de préstamos ======
  const modal = document.getElementById('prestModal');
  const btnAssign = document.getElementById('btnAssign');
  const btnCancel = document.getElementById('btnCancel');
  const btnClose  = document.getElementById('btnCloseModal');
  const btnClear  = document.getElementById('btnClearSel');
  const inputSearch = document.getElementById('prestSearch');
  const listHost = document.getElementById('prestList');

  let currentRow = null;    // <tr> activo
  let selectedKey = null;   // key del deudor elegido temporalmente (en el modal)

  // Render listado con filtro
  function renderPrestList(filter=''){
    listHost.innerHTML = '';
    const normFilter = normPerson(filter);
    const frag = document.createDocumentFragment();
    PRESTAMOS_LIST.forEach(item=>{
      if (normFilter && !item.key.includes(normFilter)) return;
      const row = document.createElement('button');
      row.type = 'button';
      row.className = 'w-full text-left px-3 py-2 border-b border-slate-100 hover:bg-slate-50 flex items-center justify-between';
      row.dataset.key = item.key;
      row.dataset.total = item.total;
      row.innerHTML = `
        <span class="truncate pr-3">${item.name}</span>
        <span class="num font-semibold">${(item.total||0).toLocaleString('es-CO')}</span>
      `;
      row.addEventListener('click', ()=>{
        selectedKey = item.key;
        Array.from(listHost.children).forEach(el=>el.classList.remove('opt-active'));
        row.classList.add('opt-active');
      });
      frag.appendChild(row);
    });
    listHost.appendChild(frag);
  }
  renderPrestList();

  inputSearch.addEventListener('input', ()=> renderPrestList(inputSearch.value));

  function openModalForRow(tr){
    currentRow = tr;
    selectedKey = null;
    inputSearch.value = '';
    renderPrestList('');
    modal.classList.remove('modal-hide');
    modal.classList.add('modal-show');
  }
  function closeModal(){
    modal.classList.remove('modal-show');
    modal.classList.add('modal-hide');
    currentRow = null;
    selectedKey = null;
  }

  btnCancel.addEventListener('click', closeModal);
  btnClose.addEventListener('click', closeModal);

  // Quitar selección (pone 0 en la fila)
  btnClear.addEventListener('click', ()=>{
    if (!currentRow) return;
    const baseName = currentRow.children[0].innerText.trim();
    currentRow.querySelector('.prest').textContent = '0';
    currentRow.querySelector('.selected-deudor').textContent = '';
    delete prestSel[baseName];
    setLS(PREST_SEL_KEY, prestSel);
    recalc();
  });

  // Asignar seleccionado
  btnAssign.addEventListener('click', ()=>{
    if (!currentRow) return;
    if (!selectedKey) { closeModal(); return; } // si no seleccionó nada, solo cierra

    const item = PRESTAMOS_LIST.find(i=>i.key===selectedKey);
    if (!item) { closeModal(); return; }

    const baseName = currentRow.children[0].innerText.trim();
    currentRow.querySelector('.prest').textContent = (item.total||0).toLocaleString('es-CO');
    currentRow.querySelector('.selected-deudor').textContent = item.name || '';

    prestSel[baseName] = { key:item.key, name:item.name, total:item.total };
    setLS(PREST_SEL_KEY, prestSel);

    recalc();
    closeModal();
  });

  // Botones "Seleccionar" por fila
  tbody.querySelectorAll('.btn-prest').forEach(btn=>{
    btn.addEventListener('click', ()=> openModalForRow(btn.closest('tr')));
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

  // Inputs de totales principales
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
