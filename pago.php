<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }
$conn->set_charset('utf8mb4');

/* =======================================================
   Helpers de normalización / matching
======================================================= */
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
function surname_singular($ap){ return preg_replace('/(es|s)$/u','', $ap); }

/** Devuelve [key1, key2] usando primer nombre + último apellido */
function first_last_keys($name){
  $n = norm_person($name);
  if ($n==='') return ['',''];
  $parts = explode(' ', $n);
  $first = $parts[0];
  $last  = $parts[count($parts)-1];
  return [$first.' '.$last, $first.' '.surname_singular($last)];
}

/** Mejor candidato por similitud, retorna índice; -1 si no cumple umbral */
function best_match_index($needleName, $candidatesKeys){
  [$k1, $k1alt] = first_last_keys($needleName);
  $needle = $k1 ?: $k1alt;

  $bestIdx = -1; $bestScore = PHP_INT_MAX; $bestSim = 0.0;
  foreach ($candidatesKeys as $i => $keys){
    foreach ($keys as $ck){
      if ($ck==='' || $needle==='') continue;

      // match fuerte por substring
      if (strpos($ck,$needle)!==false || strpos($needle,$ck)!==false) return $i;

      // distancia y similitud
      $d = levenshtein($ck, $needle);
      $L = max(strlen($ck), strlen($needle));
      $sim = 1.0 - $d / max(1,$L);

      if ($d < $bestScore || $sim > $bestSim){
        $bestScore = $d; $bestSim = $sim; $bestIdx = $i;
      }
    }
  }
  if ($bestSim >= 0.65 || $bestScore <= 3) return $bestIdx;
  return -1;
}

/* =======================================================
   Si faltan fechas: formulario
======================================================= */
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

/* =======================================================
   Parámetros
======================================================= */
$desde = $_GET['desde'];
$hasta = $_GET['hasta'];
$empresaFiltro = $_GET['empresa'] ?? "";

/* =======================================================
   1) Cargar lista canónica de conductores (conductores_admin)
   -> clave para unificar nombres (pago + préstamos)
======================================================= */
$canon = [];              // [{id, nombre, keys:[k1,k2]}...]
$canonNombre = [];        // índices por orden
$resC = $conn->query("SELECT id, nombre FROM conductores_admin ORDER BY nombre ASC");
if ($resC) {
  while($r = $resC->fetch_assoc()){
    [$k1,$k2] = first_last_keys($r['nombre']);
    $canon[] = ['id'=>(int)$r['id'], 'nombre'=>$r['nombre'], 'keys'=>array_values(array_unique(array_filter([$k1,$k2])))];
    $canonNombre[] = $r['nombre'];
  }
}
/* conductores_admin es la base de referencia. Si no hay registros, igual seguimos.  */
/* (fuente: dump que enviaste) */ /* :contentReference[oaicite:2]{index=2} */

/* =======================================================
   2) Agregar viajes del rango y mapear cada fila a un canónico
======================================================= */
$sqlV = "SELECT nombre, ruta, empresa, tipo_vehiculo
         FROM viajes
         WHERE fecha BETWEEN '$desde' AND '$hasta'";
if ($empresaFiltro !== "") {
  $empresaFiltroEsc = $conn->real_escape_string($empresaFiltro);
  $sqlV .= " AND empresa = '$empresaFiltroEsc'";
}
$resV = $conn->query($sqlV);

$contadores = [];   // por índice canónico: ['nombre','veh_auto','completos','medios','extras','carrotanques','siapana']
$vehiculosVistos = [];

if ($resV) {
  while ($row = $resV->fetch_assoc()) {
    // buscar mejor match en canónicos
    $idx = (!empty($canon))
      ? best_match_index($row['nombre'], array_column($canon,'keys'))
      : -1;

    // si no hay canónico, creamos un slot ad-hoc con su propio key
    if ($idx < 0) {
      [$k1,$k2] = first_last_keys($row['nombre']);
      $canon[] = ['id'=>0, 'nombre'=>$row['nombre'], 'keys'=>array_values(array_unique(array_filter([$k1,$k2])))];
      $canonNombre[] = $row['nombre'];
      $idx = count($canon)-1;
    }

    if (!isset($contadores[$idx])) {
      $contadores[$idx] = [
        'nombre' => $canon[$idx]['nombre'],
        'vehiculo' => $row['tipo_vehiculo'],
        'completos'=>0, 'medios'=>0, 'extras'=>0, 'carrotanques'=>0, 'siapana'=>0
      ];
    }

    $ruta = (string)$row['ruta'];
    $guiones = substr_count($ruta, '-');

    if ($row['tipo_vehiculo']==='Carrotanque' && $guiones==0) {
      $contadores[$idx]['carrotanques']++;
    } elseif (stripos($ruta,'Siapana') !== false) {
      $contadores[$idx]['siapana']++;
    } elseif (stripos($ruta,'Maicao') === false) {
      $contadores[$idx]['extras']++;
    } elseif ($guiones==2) {
      $contadores[$idx]['completos']++;
    } elseif ($guiones==1) {
      $contadores[$idx]['medios']++;
    }

    $vehiculosVistos[$row['tipo_vehiculo']] = true;
  }
}

/* =======================================================
   3) Tarifas por vehículo para la empresa
======================================================= */
$tarifas = []; // por tipo_vehiculo
if ($empresaFiltro !== "") {
  $resT = $conn->query("SELECT * FROM tarifas WHERE empresa='".$conn->real_escape_string($empresaFiltro)."'");
  if ($resT) while($r=$resT->fetch_assoc()) $tarifas[$r['tipo_vehiculo']] = $r;
}

/* =======================================================
   4) Préstamos no pagados, asignados por matching difuso
======================================================= */
/* prestamos: capital + 10% mensual acumulado desde 'fecha' hasta hoy, pagado = 0  */
/* (estructura tomada del dump que enviaste) */ /* :contentReference[oaicite:3]{index=3} */

$prestamos = []; // total por índice canónico
foreach ($canon as $i=>$c) $prestamos[$i]=0;

$qPrest = "
  SELECT deudor,
         SUM(monto + monto*0.10*CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS total
  FROM prestamos
  WHERE (pagado IS NULL OR pagado=0)
  GROUP BY deudor
";
if ($rP = $conn->query($qPrest)) {
  while($r = $rP->fetch_assoc()){
    $iBest = best_match_index($r['deudor'], array_column($canon,'keys'));
    if ($iBest >= 0) $prestamos[$iBest] += (int)round($r['total']);
  }
}

/* =======================================================
   5) Construcción de filas (total viajes base + préstamos)
======================================================= */
$filas = []; $total_facturado = 0;

ksort($contadores); // orden estable por índice
foreach ($contadores as $i => $v) {
  $veh = $v['vehiculo'];
  $t = $tarifas[$veh] ?? ["completo"=>0,"medio"=>0,"extra"=>0,"carrotanque"=>0,"siapana"=>0];

  $total = $v['completos']   * (int)($t['completo']    ?? 0)
         + $v['medios']      * (int)($t['medio']       ?? 0)
         + $v['extras']      * (int)($t['extra']       ?? 0)
         + $v['carrotanques']* (int)($t['carrotanque'] ?? 0)
         + $v['siapana']     * (int)($t['siapana']     ?? 0);

  $filas[] = [
    'nombre'        => $v['nombre'],
    'total_bruto'   => (int)$total,
    'prest_pend'    => (int)$prestamos[$i],
  ];
  $total_facturado += (int)$total;
}

/* ordenar por total desc */
usort($filas, fn($a,$b)=> $b['total_bruto'] <=> $a['total_bruto']);

/* =======================================================
   6) Render
======================================================= */
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Ajuste de Pago por Rango</title>
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
    <!-- Panel de montos -->
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
              <td class="px-3 py-2 text-right num prest"><?= number_format($f['prest_pend'],0,',','.') ?></td>
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
  const KEY_SCOPE = <?= json_encode(($empresaFiltro?:'__todas__').'|'.$desde.'|'.$hasta) ?>;
  const ACC_KEY = 'cuentas:'+KEY_SCOPE;
  const SS_KEY  = 'seg_social:'+KEY_SCOPE;

  const tbody = document.getElementById('tbody');
  const toInt = (s)=> {
    if (typeof s === 'number') return Math.round(s);
    s = (s||'').toString().replace(/\./g,'').replace(/,/g,'').replace(/[^\d\-]/g,'');
    return parseInt(s||'0',10) || 0;
  };
  const fmt = (n)=> (n||0).toLocaleString('es-CO');

  // Cargar LS previos
  function getLS(k){ try{ return JSON.parse(localStorage.getItem(k)||'{}'); }catch{return{};} }
  function setLS(k,v){ localStorage.setItem(k, JSON.stringify(v)); }
  let accMap = getLS(ACC_KEY);
  let ssMap  = getLS(SS_KEY);

  // Pintar valores persistidos
  Array.from(tbody.querySelectorAll('tr')).forEach(tr=>{
    const nombre = tr.children[0].innerText.trim();
    const cta = tr.querySelector('input.cta');
    const ss  = tr.querySelector('input.ss');
    if (accMap[nombre]) cta.value = accMap[nombre];
    if (ssMap[nombre])  ss.value  = fmt(toInt(ssMap[nombre]));
  });

  // Listeners persistencia
  tbody.querySelectorAll('input.cta').forEach(inp=>{
    const nombre = inp.closest('tr').children[0].innerText.trim();
    inp.addEventListener('change', ()=>{ accMap[nombre] = inp.value.trim(); setLS(ACC_KEY, accMap); });
  });
  tbody.querySelectorAll('input.ss').forEach(inp=>{
    const nombre = inp.closest('tr').children[0].innerText.trim();
    inp.addEventListener('input', ()=>{ ssMap[nombre] = toInt(inp.value); setLS(SS_KEY, ssMap); recalc(); });
  });

  // Distribución igualitaria de diferencia
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

  // Totales refs
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
      const aj    = ajustes[i]||0; // positivo: se resta del base
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
