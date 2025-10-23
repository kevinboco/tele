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

/* ================= AJAX: Viajes por conductor (respeta fecha/empresa) ================= */
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
  if ($res && $res->num_rows > 0) {
    echo "<div class='overflow-x-auto'>
            <table class='min-w-full text-sm'>
              <thead class='bg-blue-600 text-white'>
                <tr>
                  <th class='px-3 py-2 text-left'>Fecha</th>
                  <th class='px-3 py-2 text-left'>Ruta</th>
                  <th class='px-3 py-2 text-left'>Empresa</th>
                  <th class='px-3 py-2 text-left'>Vehículo</th>
                </tr>
              </thead>
              <tbody class='divide-y divide-gray-100 bg-white'>";
    while ($r = $res->fetch_assoc()) {
      echo "<tr class='hover:bg-blue-50 transition-colors'>
              <td class='px-3 py-2'>".htmlspecialchars($r['fecha'])."</td>
              <td class='px-3 py-2'>".htmlspecialchars($r['ruta'])."</td>
              <td class='px-3 py-2'>".htmlspecialchars($r['empresa'])."</td>
              <td class='px-3 py-2'>".htmlspecialchars($r['tipo_vehiculo'])."</td>
            </tr>";
    }
    echo "  </tbody></table></div>";
  } else {
    echo "<p class='text-center text-slate-500 m-0'>Sin viajes en el rango/empresa.</p>";
  }
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

$contadores = [];   // nombre → contadores y vehiculo del primer registro del rango
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
<title>Ajuste de Pago (con modal de Viajes)</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  .num { font-variant-numeric: tabular-nums; }
  .table-sticky thead tr { position: sticky; top: 0; z-index: 30; }
  .table-sticky thead th { position: sticky; top: 0; z-index: 31; background-color: #2563eb !important; color: #fff !important; }
  .table-sticky thead { box-shadow: 0 2px 0 rgba(0,0,0,0.06); }
  input[type=number]::-webkit-outer-spin-button,
  input[type=number]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

  /* Modal Viajes */
  .viajes-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,.45); display:none; align-items:center; justify-content:center; z-index:10000; }
  .viajes-backdrop.show{ display:flex; }
  .viajes-card{ width:min(720px,94vw); max-height:90vh; overflow:hidden; border-radius:16px; background:#fff;
                box-shadow:0 20px 60px rgba(0,0,0,.25); border:1px solid #e5e7eb; }
  .viajes-header{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #eef2f7}
  .viajes-body{padding:14px 16px;overflow:auto; max-height:70vh}
  .viajes-close{padding:6px 10px; border-radius:10px}
  .viajes-close:hover{background:#f3f4f6}
  .conductor-link{cursor:pointer; color:#0d6efd; text-decoration:underline;}
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
              <td class="px-3 py-2">
                <button type="button" class="conductor-link"><?= htmlspecialchars($f['nombre']) ?></button>
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
                  <!-- Si tienes tu modal de préstamos aquí puedes dejar el botón “Seleccionar” -->
                  <button type="button" class="text-xs px-2 py-1 rounded border border-slate-300 bg-slate-50 hover:bg-slate-100">
                    Seleccionar
                  </button>
                </div>
                <div class="text-[11px] text-slate-500 text-right selected-deudor"></div>
              </td>
              <td class="px-3 py-2">
                <input type="text" class="cta w-full max-w-[180px] rounded-lg border border-slate-300 px-2 py-1" placeholder="N° cuenta">
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

  <!-- Modal VIAJES -->
  <div id="viajesModal" class="viajes-backdrop">
    <div class="viajes-card">
      <div class="viajes-header">
        <h3 id="viajesTitle" class="text-lg font-semibold flex items-center gap-2">🧳 Viajes</h3>
        <button class="viajes-close" id="viajesCloseBtn" title="Cerrar">✕</button>
      </div>
      <div class="viajes-body" id="viajesContent">
        <!-- contenido AJAX -->
      </div>
    </div>
  </div>

<script>
  // Helpers numéricos
  const toInt = (s)=>{ if(typeof s==='number') return Math.round(s);
    s=(s||'').toString().replace(/\./g,'').replace(/,/g,'').replace(/[^\d\-]/g,''); return parseInt(s||'0',10)||0; };
  const fmt = (n)=> (n||0).toLocaleString('es-CO');

  // Totales principales
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

  const tbody = document.getElementById('tbody');

  function distribIgual(diff,n){
    const arr=new Array(n).fill(0);
    if(n<=0||diff===0) return arr;
    const s=diff>=0?1:-1; let a=Math.abs(diff);
    const base=Math.floor(a/n); let resto=a%n;
    for(let i=0;i<n;i++){ arr[i]=s*base + (resto>0?s:0); if(resto>0) resto--; }
    return arr;
  }

  function recalc(){
    const fact=toInt(inpFact.value), rec=toInt(inpRec.value), diff=fact-rec;
    lblDif.textContent=fmt(diff);
    const rows=[...tbody.querySelectorAll('tr')];
    const ajustes=distribIgual(diff, rows.length);

    let sumLleg=0,sumRet=0,sumMil4=0,sumAp=0,sumSS=0,sumPrest=0,sumPagar=0;

    rows.forEach((tr,i)=>{
      const base  = toInt(tr.querySelector('.base').textContent);
      const prest = toInt(tr.querySelector('.prest')?.textContent || 0);
      const aj    = ajustes[i]||0;
      const llego = base - aj;

      const ret  = Math.round(llego*0.035);
      const mil4 = Math.round(llego*0.004);
      const ap   = Math.round(llego*0.10);
      const ss   = toInt(tr.querySelector('input.ss')?.value || 0);

      const pagar = llego - ret - mil4 - ap - ss - prest;

      tr.querySelector('.ajuste').textContent = (aj===0?'0':(aj>0?'-'+fmt(aj):'+'+fmt(Math.abs(aj))));
      tr.querySelector('.llego').textContent  = fmt(llego);
      tr.querySelector('.ret').textContent    = fmt(ret);
      tr.querySelector('.mil4').textContent   = fmt(mil4);
      tr.querySelector('.apor').textContent   = fmt(ap);
      tr.querySelector('.pagar').textContent  = fmt(pagar);

      sumLleg+=llego; sumRet+=ret; sumMil4+=mil4; sumAp+=ap; sumSS+=ss; sumPrest+=prest; sumPagar+=pagar;
    });

    totLleg.textContent=fmt(sumLleg);
    totRet.textContent=fmt(sumRet);
    totMil4.textContent=fmt(sumMil4);
    totAp.textContent=fmt(sumAp);
    totSS.textContent=fmt(sumSS);
    totPrest.textContent=fmt(sumPrest);
    totPag.textContent=fmt(sumPagar);
  }
  function numberInputFormatter(el){
    el.addEventListener('input', ()=>{ const raw=toInt(el.value); el.value=fmt(raw); recalc(); });
  }
  numberInputFormatter(inpFact);
  numberInputFormatter(inpRec);

  // ===== Modal VIAJES =====
  const RANGO_DESDE = <?= json_encode($desde) ?>;
  const RANGO_HASTA = <?= json_encode($hasta) ?>;
  const RANGO_EMP   = <?= json_encode($empresaFiltro) ?>;

  const viajesModal   = document.getElementById('viajesModal');
  const viajesContent = document.getElementById('viajesContent');
  const viajesTitle   = document.getElementById('viajesTitle');
  const viajesClose   = document.getElementById('viajesCloseBtn');

  function abrirModalViajes(nombre){
    viajesTitle.innerHTML = '🧳 Viajes — <span class="font-normal">'+nombre+'</span>';
    viajesContent.innerHTML = '<p class="text-center m-0 animate-pulse">Cargando…</p>';
    viajesModal.classList.add('show');

    const qs = new URLSearchParams({
      viajes_conductor: nombre,
      desde: RANGO_DESDE,
      hasta: RANGO_HASTA,
      empresa: RANGO_EMP
    });

    fetch('<?= basename(__FILE__) ?>?' + qs.toString())
      .then(r => r.text())
      .then(html => { viajesContent.innerHTML = html; })
      .catch(() => { viajesContent.innerHTML = '<p class="text-center text-rose-600">Error cargando viajes.</p>'; });
  }
  function cerrarModalViajes(){ viajesModal.classList.remove('show'); viajesContent.innerHTML=''; }

  viajesClose.addEventListener('click', cerrarModalViajes);
  viajesModal.addEventListener('click', (e)=>{ if(e.target===viajesModal) cerrarModalViajes(); });
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape' && viajesModal.classList.contains('show')) cerrarModalViajes(); });

  // Click en nombre del conductor
  document.querySelectorAll('#tbody .conductor-link').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const nombre = btn.textContent.trim();
      abrirModalViajes(nombre);
    });
  });

  // Primer cálculo
  recalc();
</script>
</body>
</html>
