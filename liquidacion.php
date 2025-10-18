<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }

/* =======================================================
   🔹 Guardar tarifas por vehículo y empresa (ENDPOINT SIN CAMBIOS)
======================================================= */
if (isset($_POST['guardar_tarifa'])) {
  $empresa  = $conn->real_escape_string($_POST['empresa']);
  $vehiculo = $conn->real_escape_string($_POST['tipo_vehiculo']);
  $campo    = $conn->real_escape_string($_POST['campo']);
  $valor    = (int)$_POST['valor'];
  $conn->query("INSERT IGNORE INTO tarifas (empresa, tipo_vehiculo) VALUES ('$empresa', '$vehiculo')");
  $sql = "UPDATE tarifas SET $campo = $valor WHERE empresa='$empresa' AND tipo_vehiculo='$vehiculo'";
  echo $conn->query($sql) ? "ok" : ("error: ".$conn->error);
  exit;
}

/* =======================================================
   🔹 Endpoint AJAX: viajes por conductor (ENDPOINT SIN CAMBIOS)
======================================================= */
if (isset($_GET['viajes_conductor'])) {
  $nombre  = $conn->real_escape_string($_GET['viajes_conductor']);
  $desde   = $_GET['desde'];
  $hasta   = $_GET['hasta'];
  $empresa = $_GET['empresa'] ?? "";

  $sql = "SELECT fecha, ruta, empresa, tipo_vehiculo
          FROM viajes
          WHERE nombre = '$nombre'
            AND fecha BETWEEN '$desde' AND '$hasta'";
  if ($empresa !== "") {
    $empresa = $conn->real_escape_string($empresa);
    $sql .= " AND empresa = '$empresa'";
  }
  $sql .= " ORDER BY fecha ASC";

  $res = $conn->query($sql);
  if ($res && $res->num_rows > 0) {
    echo "<div class='overflow-auto max-h-[70vh]'>
            <table class='min-w-full text-sm'><thead class='sticky top-0 bg-slate-800 text-slate-100'>
              <tr>
                <th class='px-3 py-2 text-left'>Fecha</th>
                <th class='px-3 py-2 text-left'>Ruta</th>
                <th class='px-3 py-2 text-left'>Empresa</th>
                <th class='px-3 py-2 text-left'>Vehículo</th>
              </tr></thead><tbody class='divide-y divide-slate-700'>";
    while ($r = $res->fetch_assoc()) {
      echo "<tr class='hover:bg-slate-800/50'>
              <td class='px-3 py-2'>".htmlspecialchars($r['fecha'])."</td>
              <td class='px-3 py-2'>".htmlspecialchars($r['ruta'])."</td>
              <td class='px-3 py-2'>".htmlspecialchars($r['empresa'])."</td>
              <td class='px-3 py-2'><span class='inline-flex items-center rounded-full bg-slate-700 px-2 py-0.5 text-xs'>".htmlspecialchars($r['tipo_vehiculo'])."</span></td>
            </tr>";
    }
    echo "</tbody></table></div>";
  } else {
    echo "<p class='text-center text-slate-400'>No se encontraron viajes en el rango.</p>";
  }
  exit;
}

/* =======================================================
   🔹 Formulario inicial (SI FALTAN FECHAS)
======================================================= */
if (!isset($_GET['desde']) || !isset($_GET['hasta'])) {
  $empresas = [];
  $resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
  if ($resEmp) while ($r = $resEmp->fetch_assoc()) $empresas[] = $r['empresa'];
  ?>
  <!DOCTYPE html>
  <html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Liquidación — Tailwind UI</title>
    <script src="https://cdn.tailwindcss.com"></script>
  </head>
  <body class="min-h-screen bg-slate-900 text-slate-100">
    <div class="max-w-3xl mx-auto p-6">
      <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-6 shadow-xl">
        <h1 class="text-xl font-semibold">📅 Filtrar rango</h1>
        <p class="text-slate-400 text-sm mb-4">Selecciona periodo y (opcional) empresa.</p>
        <form method="get" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <label class="text-sm">Desde
            <input type="date" name="desde" class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-2" required>
          </label>
          <label class="text-sm">Hasta
            <input type="date" name="hasta" class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-2" required>
          </label>
          <label class="md:col-span-2 text-sm">Empresa
            <select name="empresa" class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-2">
              <option value="">— Todas —</option>
              <?php foreach($empresas as $e): ?>
                <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <div class="md:col-span-2 flex gap-3">
            <button type="submit" class="rounded-xl bg-blue-500 px-4 py-2 font-medium hover:bg-blue-400">Aplicar</button>
            <button type="reset" class="rounded-xl border border-slate-600 px-4 py-2 font-medium hover:bg-slate-800">Limpiar</button>
          </div>
        </form>
      </div>
    </div>
  </body>
  </html>
  <?php
  exit;
}

/* =======================================================
   🔹 Cálculo (MISMA LÓGICA DEL USUARIO)
======================================================= */
$desde = $_GET['desde'];
$hasta = $_GET['hasta'];
$empresaFiltro = $_GET['empresa'] ?? "";

$sql = "SELECT nombre, ruta, empresa, tipo_vehiculo FROM viajes WHERE fecha BETWEEN '$desde' AND '$hasta'";
if ($empresaFiltro !== "") {
  $empresaFiltro = $conn->real_escape_string($empresaFiltro);
  $sql .= " AND empresa = '$empresaFiltro'";
}
$res = $conn->query($sql);
$datos = [];
$vehiculos = [];
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $nombre   = $row['nombre'];
    $ruta     = $row['ruta'];
    $vehiculo = $row['tipo_vehiculo'];
    $guiones  = substr_count($ruta, '-');

    if (!isset($datos[$nombre])) {
      $datos[$nombre] = ["vehiculo"=>$vehiculo, "completos"=>0, "medios"=>0, "extras"=>0, "carrotanques"=>0];
    }
    if (!in_array($vehiculo, $vehiculos, true)) $vehiculos[] = $vehiculo;

    if ($vehiculo === "Carrotanque" && $guiones == 0) {
      $datos[$nombre]["carrotanques"]++;
    } elseif (stripos($ruta, "Maicao") === false) {
      $datos[$nombre]["extras"]++;
    } elseif ($guiones == 2) {
      $datos[$nombre]["completos"]++;
    } elseif ($guiones == 1) {
      $datos[$nombre]["medios"]++;
    }
  }
}

$empresas = [];
$resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
if ($resEmp) while ($r = $resEmp->fetch_assoc()) $empresas[] = $r['empresa'];

$tarifas_guardadas = [];
$resTarifas = $conn->query("SELECT * FROM tarifas WHERE empresa='$empresaFiltro'");
if ($resTarifas) { while ($r = $resTarifas->fetch_assoc()) { $tarifas_guardadas[$r['tipo_vehiculo']] = $r; } }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Liquidación — Tailwind UI</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
  <!-- HEADER COMPACTO -->
  <header class="sticky top-0 z-40 border-b border-slate-800/80 bg-slate-900/80 backdrop-blur">
    <div class="mx-auto max-w-7xl px-4 py-3 grid grid-cols-1 md:grid-cols-[1fr_auto] gap-3 items-center">
      <div>
        <h1 class="text-lg font-semibold leading-tight">Liquidación de Conductores</h1>
        <p class="text-xs text-slate-400">Periodo <strong><?= htmlspecialchars($desde) ?></strong> → <strong><?= htmlspecialchars($hasta) ?></strong><?= $empresaFiltro!==''?" • Empresa: <strong>".htmlspecialchars($empresaFiltro)."</strong>":'' ?></p>
      </div>
      <form method="get" class="flex flex-wrap gap-2 justify-start md:justify-end">
        <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm" required>
        <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm" required>
        <select name="empresa" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm min-w-[180px]">
          <option value="">— Todas —</option>
          <?php foreach($empresas as $e): ?>
            <option value="<?= htmlspecialchars($e) ?>" <?= $empresaFiltro==$e?'selected':'' ?>><?= htmlspecialchars($e) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="rounded-lg bg-blue-500 px-4 py-2 text-sm font-medium hover:bg-blue-400" type="submit">Aplicar</button>
        <a class="rounded-lg border border-slate-600 px-4 py-2 text-sm hover:bg-slate-800" href="<?= basename(__FILE__) ?>">Reiniciar</a>
      </form>
    </div>
  </header>

  <!-- KPIs -->
  <section class="mx-auto max-w-7xl px-4 py-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
    <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
      <p class="text-xs text-slate-400">Conductores</p>
      <p id="k_conductores" class="text-2xl font-extrabold">0</p>
    </div>
    <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
      <p class="text-xs text-slate-400">Viajes (total)</p>
      <p id="k_viajes" class="text-2xl font-extrabold">0</p>
    </div>
    <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
      <p class="text-xs text-slate-400">Total General</p>
      <p id="k_total" class="text-2xl font-extrabold">$ 0</p>
    </div>
    <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
      <p class="text-xs text-slate-400">Promedio / Conductor</p>
      <p id="k_prom" class="text-2xl font-extrabold">$ 0</p>
    </div>
  </section>

  <!-- LAYOUT PRINCIPAL -->
  <main class="mx-auto max-w-7xl px-4 pb-8 grid grid-cols-1 xl:grid-cols-[1.1fr_2fr_1.1fr] gap-4 items-start">
    <!-- TARIFAS -->
    <section class="rounded-2xl border border-slate-800 bg-slate-900/60">
      <div class="flex items-center justify-between px-4 py-3 border-b border-slate-800">
        <h2 class="font-semibold">⚙️ Tarifas por vehículo</h2>
        <span class="text-xs text-slate-400">Autoguarda y recalcula</span>
      </div>
      <div class="p-4 space-y-5">
        <?php foreach ($vehiculos as $veh): $t = $tarifas_guardadas[$veh] ?? ["completo"=>0,"medio"=>0,"extra"=>0,"carrotanque"=>0]; ?>
          <div class="rounded-xl border border-slate-800 bg-slate-950/50 p-4">
            <div class="mb-2 inline-flex items-center gap-2 rounded-full border border-slate-700 bg-slate-800 px-3 py-1 text-sm"><span class="font-semibold"><?= htmlspecialchars($veh) ?></span></div>
            <?php if ($veh === 'Carrotanque'): ?>
              <div class="grid grid-cols-1 gap-3">
                <label class="text-sm">Carrotanque $
                  <input type="number" step="1000" min="0" value="<?= (int)$t['carrotanque'] ?>" class="tarifa-input mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2" oninput="recalcular()">
                </label>
                <p class="text-xs text-slate-500">Completo/Medio/Extra no aplican</p>
              </div>
            <?php else: ?>
              <div class="grid grid-cols-3 gap-3">
                <label class="text-sm">Completo $
                  <input type="number" step="1000" min="0" value="<?= (int)$t['completo'] ?>" class="tarifa-input mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2" oninput="recalcular()">
                </label>
                <label class="text-sm">Medio $
                  <input type="number" step="1000" min="0" value="<?= (int)$t['medio'] ?>" class="tarifa-input mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2" oninput="recalcular()">
                </label>
                <label class="text-sm">Extra $
                  <input type="number" step="1000" min="0" value="<?= (int)$t['extra'] ?>" class="tarifa-input mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2" oninput="recalcular()">
                </label>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- RESUMEN CONDUCTORES -->
    <section class="rounded-2xl border border-slate-800 bg-slate-900/60">
      <div class="flex items-center justify-between px-4 py-3 border-b border-slate-800">
        <h2 class="font-semibold">🧑‍✈️ Resumen por Conductor</h2>
        <span class="text-xs text-slate-400">Haz clic para ver detalle</span>
      </div>
      <div class="p-0 overflow-auto">
        <table id="tabla_conductores" class="min-w-full text-sm">
          <thead class="sticky top-0 bg-slate-800 text-slate-100">
            <tr>
              <th class="px-3 py-2 text-left">Conductor</th>
              <th class="px-3 py-2 text-left">Vehículo</th>
              <th class="px-3 py-2 text-right">Comp.</th>
              <th class="px-3 py-2 text-right">Med.</th>
              <th class="px-3 py-2 text-right">Ext.</th>
              <th class="px-3 py-2 text-right">Carro.</th>
              <th class="px-3 py-2 text-right">Total</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/80">
            <?php foreach ($datos as $conductor => $viajes): ?>
              <tr class="hover:bg-slate-800/50" data-vehiculo="<?= htmlspecialchars($viajes['vehiculo']) ?>">
                <td class="px-3 py-2"><button type="button" class="conductor-link text-blue-300 underline underline-offset-2" data-nombre="<?= htmlspecialchars($conductor) ?>"><?= htmlspecialchars($conductor) ?></button></td>
                <td class="px-3 py-2"><span class="inline-flex items-center rounded-full bg-slate-700 px-2 py-0.5 text-xs"><?= htmlspecialchars($viajes['vehiculo']) ?></span></td>
                <td class="px-3 py-2 text-right"><?= (int)$viajes['completos'] ?></td>
                <td class="px-3 py-2 text-right"><?= (int)$viajes['medios'] ?></td>
                <td class="px-3 py-2 text-right"><?= (int)$viajes['extras'] ?></td>
                <td class="px-3 py-2 text-right"><?= (int)$viajes['carrotanques'] ?></td>
                <td class="px-3 py-2 text-right"><input type="text" class="totales w-full rounded-lg border border-slate-700 bg-slate-900 px-2 py-1 text-right" readonly></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- PANEL VIAJES -->
    <aside class="rounded-2xl border border-slate-800 bg-slate-900/60 sticky top-[88px] max-h-[calc(100vh-110px)] overflow-auto" id="panelViajes">
      <div class="flex items-center justify-between px-4 py-3 border-b border-slate-800">
        <h2 class="font-semibold">🧳 Viajes del conductor</h2>
        <span class="text-xs text-slate-400">Carga bajo demanda</span>
      </div>
      <div class="p-4" id="contenidoPanel"><p class="text-slate-400">Selecciona un conductor…</p></div>
    </aside>
  </main>

  <!-- FAB -->
  <div class="fixed bottom-4 right-4 flex flex-col gap-2 z-50">
    <button onclick="window.scrollTo({top:0,behavior:'smooth'})" class="rounded-full bg-blue-500 p-3 shadow-xl hover:bg-blue-400">⬆️</button>
    <button onclick="exportarCSV()" class="rounded-full border border-slate-700 bg-slate-900 p-3 shadow-xl hover:bg-slate-800">📄 CSV</button>
  </div>

  <script>
    // ===== Helpers
    const money = n => (n||0).toLocaleString('es-CO');

    function getTarifas(){
      const map = {};
      document.querySelectorAll('section input.tarifa-input').forEach(input=>{
        const card = input.closest('.rounded-xl');
        const veh = card.querySelector('.inline-flex span').innerText.trim();
        map[veh] ||= {completo:0, medio:0, extra:0, carrotanque:0};
        const labelTxt = input.parentElement.firstChild.textContent.toLowerCase();
        if (labelTxt.includes('completo')) map[veh].completo = parseFloat(input.value)||0;
        else if (labelTxt.includes('medio')) map[veh].medio = parseFloat(input.value)||0;
        else if (labelTxt.includes('extra')) map[veh].extra = parseFloat(input.value)||0;
        else if (labelTxt.includes('carrotanque')) map[veh].carrotanque = parseFloat(input.value)||0;
      });
      return map;
    }

    function recalcular(){
      const tarifas = getTarifas();
      let totalGeneral = 0, totalViajes = 0, conductores = 0;
      document.querySelectorAll('#tabla_conductores tbody tr').forEach(row=>{
        conductores++;
        const veh = row.dataset.vehiculo;
        const c = parseInt(row.cells[2].innerText)||0;
        const m = parseInt(row.cells[3].innerText)||0;
        const e = parseInt(row.cells[4].innerText)||0;
        const ca = parseInt(row.cells[5].innerText)||0;
        totalViajes += c+m+e+ca;
        const t = tarifas[veh] || {completo:0,medio:0,extra:0,carrotanque:0};
        const totalFila = c*t.completo + m*t.medio + e*t.extra + ca*t.carrotanque;
        row.querySelector('input.totales').value = money(totalFila);
        totalGeneral += totalFila;
      });
      // KPIs
      document.getElementById('k_conductores').innerText = conductores;
      document.getElementById('k_viajes').innerText = totalViajes;
      document.getElementById('k_total').innerText = '$ ' + money(totalGeneral);
      document.getElementById('k_prom').innerText = '$ ' + money(conductores? totalGeneral/conductores:0);
    }

    // Guardado de tarifas (mismo endpoint PHP)
    document.querySelectorAll('input.tarifa-input').forEach(input=>{
      input.addEventListener('change',()=>{
        const card = input.closest('.rounded-xl');
        const tipoVehiculo = card.querySelector('.inline-flex span').innerText.trim();
        const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
        const labelTxt = input.parentElement.firstChild.textContent.toLowerCase();
        let campo = 'completo';
        if (labelTxt.includes('medio')) campo = 'medio';
        else if (labelTxt.includes('extra')) campo = 'extra';
        else if (labelTxt.includes('carrotanque')) campo = 'carrotanque';
        const valor = parseInt(input.value)||0;

        fetch('<?= basename(__FILE__) ?>',{
          method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:new URLSearchParams({guardar_tarifa:1, empresa, tipo_vehiculo:tipoVehiculo, campo, valor})
        }).then(r=>r.text()).then(t=>{ if(t.trim()==='ok'){recalcular();} else {console.error(t);} });
      });
    });

    // Cargar viajes
    function cargarViajes(nombre){
      const panel = document.getElementById('contenidoPanel');
      panel.innerHTML = '<p class="text-slate-400">Cargando…</p>';
      const desde='<?= htmlspecialchars($desde) ?>', hasta='<?= htmlspecialchars($hasta) ?>', empresa='<?= htmlspecialchars($empresaFiltro) ?>';
      fetch('<?= basename(__FILE__) ?>?viajes_conductor='+encodeURIComponent(nombre)+'&desde='+desde+'&hasta='+hasta+'&empresa='+encodeURIComponent(empresa))
        .then(r=>r.text()).then(html=> panel.innerHTML = html)
        .catch(()=> panel.innerHTML = '<p class="text-red-400">No se pudo cargar.</p>');
    }
    document.querySelectorAll('.conductor-link').forEach(btn=> btn.addEventListener('click',()=> cargarViajes(btn.dataset.nombre)));

    // Export CSV
    function exportarCSV(){
      const rows = [['Conductor','Vehículo','Completos','Medios','Extras','Carrotanques','Total']];
      document.querySelectorAll('#tabla_conductores tbody tr').forEach(r=>{
        rows.push([
          r.querySelector('.conductor-link').textContent.trim(),
          r.cells[1].innerText.trim(), r.cells[2].innerText.trim(), r.cells[3].innerText.trim(), r.cells[4].innerText.trim(), r.cells[5].innerText.trim(), r.querySelector('input.totales').value
        ]);
      });
      const csv = rows.map(a=>a.map(s=>`"${s.replaceAll('"','""')}"`).join(',')).join('
');
      const blob = new Blob([csv],{type:'text/csv;charset=utf-8;'});
      const url = URL.createObjectURL(blob);
      const a = Object.assign(document.createElement('a'), {href:url, download:'liquidacion_resumen.csv'});
      document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
    }
    window.exportarCSV = exportarCSV;

    // Primera pintura
    recalcular();
  </script>
</body>
</html>
