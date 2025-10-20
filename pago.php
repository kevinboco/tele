<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }

/* Helpers */
function mbnorm($s){ return mb_strtolower(trim((string)$s), 'UTF-8'); }

/* ================== Form si faltan fechas ================== */
if (!isset($_GET['desde']) || !isset($_GET['hasta'])) {
  $empresas = [];
  $resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
  if ($resEmp) while ($r = $resEmp->fetch_assoc()) $empresas[] = $r['empresa'];
  ?>
  <!DOCTYPE html><html lang="es"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Ajuste de pago</title><script src="https://cdn.tailwindcss.com"></script>
  </head><body class="min-h-screen bg-slate-100 text-slate-800">
    <div class="max-w-lg mx-auto p-6">
      <div class="bg-white shadow-sm rounded-2xl p-6 border border-slate-200">
        <h2 class="text-2xl font-bold text-center mb-2">📅 Ajuste de pago por rango</h2>
        <p class="text-center text-slate-500 mb-6">Selecciona periodo y (opcional) una empresa.</p>
        <form method="get" class="space-y-4">
          <label class="block"><span class="block text-sm font-medium mb-1">Desde</span>
            <input type="date" name="desde" required class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition"/>
          </label>
          <label class="block"><span class="block text-sm font-medium mb-1">Hasta</span>
            <input type="date" name="hasta" required class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition"/>
          </label>
          <label class="block"><span class="block text-sm font-medium mb-1">Empresa</span>
            <select name="empresa" class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition">
              <option value="">-- Todas --</option>
              <?php foreach($empresas as $e): ?><option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option><?php endforeach; ?>
            </select>
          </label>
          <button class="w-full rounded-xl bg-blue-600 text-white py-2.5 font-semibold shadow hover:bg-blue-700 active:bg-blue-800 focus:ring-4 focus:ring-blue-200 transition">Continuar</button>
        </form>
      </div>
    </div>
  </body></html>
  <?php exit;
}

/* ========== Cargar datos del rango y empresa ========== */
$desde = $_GET['desde']; $hasta = $_GET['hasta']; $empresaFiltro = $_GET['empresa'] ?? "";

/* Viajes del rango (mismo cómputo que Liquidación: completos/medios/extras/siapana/carrotanque) */
$sqlV = "SELECT nombre, ruta, empresa, tipo_vehiculo
         FROM viajes
         WHERE fecha BETWEEN '$desde' AND '$hasta'";
if ($empresaFiltro !== "") {
  $empresaFiltro = $conn->real_escape_string($empresaFiltro);
  $sqlV .= " AND empresa = '$empresaFiltro'";
}
$res = $conn->query($sqlV);

$datos = [];      // por conductor
$vehiculos = [];
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $nombre   = $row['nombre'];
    $ruta     = $row['ruta'];
    $vehiculo = $row['tipo_vehiculo'];
    $guiones  = substr_count($ruta, '-');

    if (!isset($datos[$nombre])) {
      $datos[$nombre] = ["vehiculo"=>$vehiculo,"completos"=>0,"medios"=>0,"extras"=>0,"carrotanques"=>0,"siapana"=>0];
    }
    if (!in_array($vehiculo, $vehiculos, true)) $vehiculos[] = $vehiculo;

    if ($vehiculo === "Carrotanque" && $guiones == 0) {
      $datos[$nombre]["carrotanques"]++;
    } elseif (stripos($ruta, "Siapana") !== false) {
      $datos[$nombre]["siapana"]++;
    } elseif (stripos($ruta, "Maicao") === false) {
      $datos[$nombre]["extras"]++;
    } elseif ($guiones == 2) {
      $datos[$nombre]["completos"]++;
    } elseif ($guiones == 1) {
      $datos[$nombre]["medios"]++;
    }
  }
}

/* Tarifas guardadas por vehículo para la empresa */
$tarifas_guardadas = [];
if ($empresaFiltro !== "") {
  $resT = $conn->query("SELECT * FROM tarifas WHERE empresa='$empresaFiltro'");
  if ($resT) while ($r = $resT->fetch_assoc()) $tarifas_guardadas[$r['tipo_vehiculo']] = $r;
}

/* === NUEVO: préstamos pendientes por deudor (capital + interés 10% mensual acumulado) === */
$prestamosPend = [];  // key = deudor normalizado, value = total pendiente (capital+interés)
$qPrest = "
  SELECT LOWER(TRIM(deudor)) AS k,
         SUM(monto + monto*0.10*CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS total
  FROM prestamos
  WHERE (pagado IS NULL OR pagado=0)
  GROUP BY k";
if ($rP = $conn->query($qPrest)) {
  while($r = $rP->fetch_assoc()){
    $prestamosPend[$r['k']] = (float)$r['total'];
  }
}

/* Construir arreglo de conductores */
$conductores = []; $total_facturado = 0;
foreach ($datos as $nombre => $v) {
  $veh = $v['vehiculo'];
  $t = $tarifas_guardadas[$veh] ?? ["completo"=>0,"medio"=>0,"extra"=>0,"carrotanque"=>0,"siapana"=>0];
  $total = $v['completos']*$t['completo'] + $v['medios']*$t['medio'] + $v['extras']*$t['extra'] + $v['carrotanques']*$t['carrotanque'] + $v['siapana']*$t['siapana'];

  $prestPend = $prestamosPend[mbnorm($nombre)] ?? 0.0;

  $conductores[] = [
    "nombre"=>$nombre,
    "total_bruto" => (int)$total,
    "prestamo_pend" => (int)round($prestPend)
  ];
  $total_facturado += (int)$total;
}

// Orden por total descendente (opcional)
usort($conductores, fn($a,$b)=> $b['total_bruto'] <=> $a['total_bruto']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Ajuste de Pago por Rango</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  .num { font-variant-numeric: tabular-nums; }
  input[type=number]::-webkit-outer-spin-button,
  input[type=number]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
  .table-sticky thead th { position: sticky; top: 0; z-index: 1; }
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
      <form class="mt-3 grid grid-cols-1 md:grid-cols-5 gap-3" method="get">
        <input type="hidden" name="empresa" value="<?= htmlspecialchars($_GET['empresa'] ?? '') ?>">
        <label class="block"><span class="block text-xs font-medium mb-1">Desde</span>
          <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" required class="w-full rounded-xl border border-slate-300 px-3 py-2">
        </label>
        <label class="block"><span class="block text-xs font-medium mb-1">Hasta</span>
          <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" required class="w-full rounded-xl border border-slate-300 px-3 py-2">
        </label>
        <label class="block md:col-span-2"><span class="block text-xs font-medium mb-1">Empresa</span>
          <select name="empresa" class="w-full rounded-xl border border-slate-300 px-3 py-2">
            <option value="">-- Todas --</option>
            <?php
              $resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
              if ($resEmp) while ($e = $resEmp->fetch_assoc()) {
                $sel = ($empresaFiltro==$e['empresa'])?'selected':'';
                echo "<option value=\"".htmlspecialchars($e['empresa'])."\" $sel>".htmlspecialchars($e['empresa'])."</option>";
              }
            ?>
          </select>
        </label>
        <div class="flex md:items-end"><button class="w-full rounded-xl bg-blue-600 text-white py-2.5 font-semibold shadow hover:bg-blue-700 active:bg-blue-800">Aplicar</button></div>
      </form>
    </div>
  </header>

  <main class="max-w-[1600px] mx-auto px-3 md:px-4 py-6 space-y-5">
    <!-- Panel de monto facturado vs recibido -->
    <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
      <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <div>
          <div class="text-xs text-slate-500 mb-1">Conductores en rango</div>
          <div class="text-lg font-semibold"><?= count($conductores) ?></div>
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

    <!-- Tabla principal (sin “Vehículo” y con “Préstamos (pend.)”) -->
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
          <tbody id="tbody" class="divide-y divide-slate-100 bg-white"></tbody>
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
    const CONDUCTORES = <?php echo json_encode($conductores, JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK); ?>;
    const KEY_SCOPE = <?php echo json_encode($empresaFiltro.'|'.$desde.'|'.$hasta); ?>;

    // ==== Helpers numéricos ====
    const toInt = (s)=> {
      if (typeof s === 'number') return Math.round(s);
      s = (s||'').toString().replace(/\./g,'').replace(/,/g,'').replace(/[^\d\-]/g,'');
      return parseInt(s||'0',10) || 0;
    };
    const fmt = (n)=> (n||0).toLocaleString('es-CO');

    // ==== LocalStorage: cuenta y SS por conductor ====
    function getLS(key){ try{ return JSON.parse(localStorage.getItem(key)||'{}'); }catch{ return {}; } }
    function setLS(key, obj){ localStorage.setItem(key, JSON.stringify(obj)); }

    const ACC_KEY = 'cuentas:'+KEY_SCOPE;
    const SS_KEY  = 'seg_social:'+KEY_SCOPE;
    let accMap = getLS(ACC_KEY);
    let ssMap  = getLS(SS_KEY);

    // ==== Render filas ====
    const tbody = document.getElementById('tbody');

    function renderRows() {
      tbody.innerHTML = '';
      CONDUCTORES.forEach((c) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td class="px-3 py-2">${c.nombre}</td>
          <td class="px-3 py-2 text-right num base">${fmt(c.total_bruto)}</td>
          <td class="px-3 py-2 text-right num ajuste">0</td>
          <td class="px-3 py-2 text-right num llego">0</td>
          <td class="px-3 py-2 text-right num ret">0</td>
          <td class="px-3 py-2 text-right num mil4">0</td>
          <td class="px-3 py-2 text-right num apor">0</td>
          <td class="px-3 py-2 text-right">
            <input type="text" class="ss w-full max-w-[120px] rounded-lg border border-slate-300 px-2 py-1 text-right num"
                   value="${fmt(toInt(ssMap[c.nombre]||0))}" placeholder="">
          </td>
          <td class="px-3 py-2 text-right num prest">${fmt(c.prestamo_pend||0)}</td>
          <td class="px-3 py-2">
            <input type="text" class="cta w-full max-w-[180px] rounded-lg border border-slate-300 px-2 py-1"
                   value="${(accMap[c.nombre]||'')}" placeholder="N° cuenta">
          </td>
          <td class="px-3 py-2 text-right num pagar">0</td>
        `;
        tbody.appendChild(tr);
      });

      // Listeners de SS y Cuenta
      tbody.querySelectorAll('input.ss').forEach((inp, i)=>{
        const nombre = CONDUCTORES[i].nombre;
        inp.addEventListener('input', ()=> {
          ssMap[nombre] = toInt(inp.value);
          setLS(SS_KEY, ssMap);
          recalc();
        });
      });
      tbody.querySelectorAll('input.cta').forEach((inp, i)=>{
        const nombre = CONDUCTORES[i].nombre;
        inp.addEventListener('change', ()=> {
          accMap[nombre] = inp.value.trim();
          setLS(ACC_KEY, accMap);
        });
      });
    }

    // ==== Distribución de diferencia (igualitaria) ====
    function distribucionIgualitaria(diffTotal, n) {
      const ajustes = new Array(n).fill(0);
      if (n<=0 || diffTotal === 0) return ajustes;
      const signo = diffTotal >= 0 ? 1 : -1;
      let abs = Math.abs(diffTotal);
      const base = Math.floor(abs / n);
      let resto = abs % n;
      for (let i=0; i<n; i++) {
        ajustes[i] = signo * base + (resto>0 ? signo*1 : 0);
        if (resto>0) resto--;
      }
      return ajustes;
    }

    // ==== Recalc principal ====
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

    function recalc() {
      const fact = toInt(inpFact.value);
      const rec  = toInt(inpRec.value);
      const diff = fact - rec; // positivo: falta; se reparte para restarlo
      lblDif.textContent = fmt(diff);

      const n = CONDUCTORES.length;
      const ajustes = distribucionIgualitaria(diff, n);

      let sumLleg=0, sumRet=0, sumMil4=0, sumAp=0, sumSS=0, sumPrest=0, sumPagar=0;

      tbody.querySelectorAll('tr').forEach((tr, i)=>{
        const base = CONDUCTORES[i].total_bruto;
        const prest = CONDUCTORES[i].prestamo_pend || 0;
        const aj   = ajustes[i] || 0; // positivo = se resta
        const llego = base - aj;

        // Descuentos sobre "valor que llegó"
        const ret = Math.round(llego * 0.035);
        const mil4 = Math.round(llego * 0.004);
        const ap  = Math.round(llego * 0.10);
        const ss  = toInt(tr.querySelector('input.ss').value);

        const pagar = llego - ret - mil4 - ap - ss - prest;

        tr.querySelector('.ajuste').textContent = (aj===0? '0' : (aj>0? '-'+fmt(aj) : '+'+fmt(Math.abs(aj))));
        tr.querySelector('.llego').textContent  = fmt(llego);
        tr.querySelector('.ret').textContent    = fmt(ret);
        tr.querySelector('.mil4').textContent   = fmt(mil4);
        tr.querySelector('.apor').textContent   = fmt(ap);
        tr.querySelector('.pagar').textContent  = fmt(pagar);

        sumLleg += llego; sumRet += ret; sumMil4 += mil4; sumAp += ap; sumSS += ss; sumPrest += prest; sumPagar += pagar;
      });

      totLleg.textContent = fmt(sumLleg);
      totRet.textContent  = fmt(sumRet);
      totMil4.textContent = fmt(sumMil4);
      totAp.textContent   = fmt(sumAp);
      totSS.textContent   = fmt(sumSS);
      totPrest.textContent= fmt(sumPrest);
      totPag.textContent  = fmt(sumPagar);
    }

    // Eventos
    inpFact.addEventListener('input', recalc);
    inpRec.addEventListener('input', recalc);

    // Init
    renderRows();
    recalc();
  </script>
</body>
</html>
