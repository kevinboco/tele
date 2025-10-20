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

/* ================= Préstamos (mapa exacto por nombre normalizado) =================
   total = SUM(monto + 10% mensual acumulado) para no pagados */
$prestamosMap = [];     // key = norm_person(deudor), val = total pendiente
$prestamosNombres = []; // para el datalist
$qPrest = "
  SELECT deudor,
         SUM(monto + monto*0.10*CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS total
  FROM prestamos
  WHERE (pagado IS NULL OR pagado=0)
  GROUP BY deudor
";
if ($rP = $conn->query($qPrest)) {
  while($r = $rP->fetch_assoc()){
    $k = norm_person($r['deudor']);
    $prestamosMap[$k] = (int)round($r['total']);
    $prestamosNombres[] = $r['deudor'];
  }
}

/* (Opcional) Nombres base de conductores_admin para sugerencias */
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
    'nombre'        => $nombre,          // editable en UI
    'total_bruto'   => (int)$total,
    // prestamo se obtendrá en UI a partir del nombre editable
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
<title>Ajuste de Pago (conductor editable)</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  .num { font-variant-numeric: tabular-nums; }
  .table-sticky thead th { position: sticky; top: 0; z-index: 1; }
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

    <!-- Datalist para autocompletar nombres -->
    <datalist id="listaDeudores">
      <?php
        // prestamos primero
        foreach(array_unique($prestamosNombres) as $n){ echo "<option value=\"".htmlspecialchars($n)."\"></option>"; }
        // y también conductores_admin (por si quieres forzar ese nombre)
        foreach(array_unique($adminNombres) as $n){ echo "<option value=\"".htmlspecialchars($n)."\"></option>"; }
      ?>
    </datalist>

    <!-- Tabla principal -->
    <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
      <div class="overflow-auto max-h-[70vh] rounded-xl border border-slate-200 table-sticky">
        <table class="min-w-[1200px] w-full text-sm">
          <thead class="bg-blue-600 text-white">
            <tr>
              <th class="px-3 py-2 text-left">Conductor (editable)</th>
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
                  <input type="text" class="conductor-input w-full rounded-lg border border-slate-300 px-2 py-1"
                         list="listaDeudores" value="<?= htmlspecialchars($f['nombre']) ?>"
                         placeholder="Escribe el nombre tal como está en PRESTAMOS">
                  <button type="button" class="reset-name text-xs px-2 py-1 rounded bg-slate-100 border border-slate-300">↺</button>
                </div>
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
              <td class="px-3 py-2 text-right num prest">0</td>
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

<script>
  // ====== Datos de servidor a JS ======
  const KEY_SCOPE = <?= json_encode(($empresaFiltro?:'__todas__').'|'.$desde.'|'.$hasta) ?>;
  const ACC_KEY   = 'cuentas:'+KEY_SCOPE;
  const SS_KEY    = 'seg_social:'+KEY_SCOPE;
  const NAME_KEY  = 'nombre_override:'+KEY_SCOPE;

  const PRESTAMOS_MAP = <?php echo json_encode($prestamosMap, JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK); ?>;

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

  // ====== Cargar persistencia ======
  let accMap  = getLS(ACC_KEY);
  let ssMap   = getLS(SS_KEY);
  let nameMap = getLS(NAME_KEY); // override por conductor original (texto mostrado al cargar)

  const tbody = document.getElementById('tbody');

  // ====== Inicializa UI por fila ======
  Array.from(tbody.querySelectorAll('tr')).forEach(tr=>{
    const inputName = tr.querySelector('.conductor-input');
    const resetBtn  = tr.querySelector('.reset-name');
    const cta = tr.querySelector('input.cta');
    const ss  = tr.querySelector('input.ss');
    const baseName = inputName.value; // nombre que vino de viajes (para clave LS por fila)

    // Restaura overrides previos
    if (nameMap[baseName]) inputName.value = nameMap[baseName];
    // Aplica prestamos según nombre actual
    const k = normPerson(inputName.value);
    tr.querySelector('.prest').textContent = fmt(PRESTAMOS_MAP[k] || 0);

    // Restaura cuenta/SS si existían
    if (accMap[baseName]) cta.value = accMap[baseName];
    if (ssMap[baseName])  ss.value  = fmt(toInt(ssMap[baseName]));

    // Eventos
    inputName.addEventListener('input', ()=>{
      const key = normPerson(inputName.value);
      const pend = PRESTAMOS_MAP[key] || 0;
      tr.querySelector('.prest').textContent = fmt(pend);
      nameMap[baseName] = inputName.value;
      setLS(NAME_KEY, nameMap);
      recalc();
    });
    resetBtn.addEventListener('click', ()=>{
      inputName.value = baseName;
      const key = normPerson(inputName.value);
      tr.querySelector('.prest').textContent = fmt(PRESTAMOS_MAP[key] || 0);
      delete nameMap[baseName];
      setLS(NAME_KEY, nameMap);
      recalc();
    });

    cta.addEventListener('change', ()=>{ accMap[baseName] = cta.value.trim(); setLS(ACC_KEY, accMap); });
    ss.addEventListener('input', ()=>{ ssMap[baseName] = toInt(ss.value); setLS(SS_KEY, ssMap); recalc(); });
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

  inpFact.addEventListener('input', recalc);
  inpRec.addEventListener('input', recalc);
  recalc();
</script>

</body>
</html>
