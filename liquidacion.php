<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}

/* =======================================================
   🔹 Guardar tarifas por vehículo y empresa (AJAX)
======================================================= */
if (isset($_POST['guardar_tarifa'])) {
    $empresa  = $conn->real_escape_string($_POST['empresa']);
    $vehiculo = $conn->real_escape_string($_POST['tipo_vehiculo']);
    $campo    = $conn->real_escape_string($_POST['campo']); // completo|medio|extra|carrotanque
    $valor    = (int)$_POST['valor'];

    $conn->query("INSERT IGNORE INTO tarifas (empresa, tipo_vehiculo) VALUES ('$empresa', '$vehiculo')");
    $sql = "UPDATE tarifas SET $campo = $valor WHERE empresa='$empresa' AND tipo_vehiculo='$vehiculo'";
    echo $conn->query($sql) ? "ok" : "error: " . $conn->error;
    exit;
}

/* =======================================================
   🔹 Endpoint AJAX: viajes por conductor
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
        // Tabla Tailwind
        echo "<div class='overflow-x-auto'>
                <table class='min-w-full text-sm text-left'>
                  <thead class='bg-blue-600 text-white'>
                    <tr>
                      <th class='px-3 py-2 text-center'>Fecha</th>
                      <th class='px-3 py-2 text-center'>Ruta</th>
                      <th class='px-3 py-2 text-center'>Empresa</th>
                      <th class='px-3 py-2 text-center'>Vehículo</th>
                    </tr>
                  </thead>
                  <tbody class='divide-y divide-gray-100 bg-white'>";
        while ($r = $res->fetch_assoc()) {
            echo "<tr class='hover:bg-blue-50 transition-colors'>
                    <td class='px-3 py-2 text-center'>".htmlspecialchars($r['fecha'])."</td>
                    <td class='px-3 py-2 text-center'>".htmlspecialchars($r['ruta'])."</td>
                    <td class='px-3 py-2 text-center'>".htmlspecialchars($r['empresa'])."</td>
                    <td class='px-3 py-2 text-center'>".htmlspecialchars($r['tipo_vehiculo'])."</td>
                  </tr>";
        }
        echo "  </tbody>
               </table>
              </div>";
    } else {
        echo "<p class='text-center text-gray-500'>No se encontraron viajes para este conductor en ese rango.</p>";
    }
    exit;
}

/* =======================================================
   🔹 Formulario inicial (si no hay rango)
======================================================= */
if (!isset($_GET['desde']) || !isset($_GET['hasta'])) {
    $empresas = [];
    $resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
    if ($resEmp) while ($r = $resEmp->fetch_assoc()) $empresas[] = $r['empresa'];
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
      <title>Filtrar viajes</title>
      <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-800">
      <div class="max-w-lg mx-auto p-6">
        <div class="bg-white shadow-sm rounded-2xl p-6 border border-slate-200">
          <h2 class="text-2xl font-bold text-center mb-2">📅 Filtrar viajes por rango</h2>
          <p class="text-center text-slate-500 mb-6">Selecciona el periodo y (opcional) una empresa.</p>
          <form method="get" class="space-y-4">
            <label class="block">
              <span class="block text-sm font-medium mb-1">Desde</span>
              <input type="date" name="desde" required
                     class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition"/>
            </label>
            <label class="block">
              <span class="block text-sm font-medium mb-1">Hasta</span>
              <input type="date" name="hasta" required
                     class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition"/>
            </label>
            <label class="block">
              <span class="block text-sm font-medium mb-1">Empresa</span>
              <select name="empresa"
                      class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition">
                <option value="">-- Todas --</option>
                <?php foreach($empresas as $e): ?>
                  <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <button class="w-full rounded-xl bg-blue-600 text-white py-2.5 font-semibold shadow hover:bg-blue-700 active:bg-blue-800 focus:ring-4 focus:ring-blue-200 transition">
              Filtrar
            </button>
          </form>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

/* =======================================================
   🔹 Cálculo y armado de tablas
======================================================= */
$desde = $_GET['desde'];
$hasta = $_GET['hasta'];
$empresaFiltro = $_GET['empresa'] ?? "";

$sql = "SELECT nombre, ruta, empresa, tipo_vehiculo FROM viajes
        WHERE fecha BETWEEN '$desde' AND '$hasta'";
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
            $datos[$nombre] = ["vehiculo"=>$vehiculo,"completos"=>0,"medios"=>0,"extras"=>0,"carrotanques"=>0];
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

/* Empresas y tarifas */
$empresas = [];
$resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
if ($resEmp) while ($r = $resEmp->fetch_assoc()) $empresas[] = $r['empresa'];

$tarifas_guardadas = [];
if ($empresaFiltro !== "") {
  $resTarifas = $conn->query("SELECT * FROM tarifas WHERE empresa='$empresaFiltro'");
  if ($resTarifas) {
    while ($r = $resTarifas->fetch_assoc()) {
      $tarifas_guardadas[$r['tipo_vehiculo']] = $r;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Liquidación de Conductores</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  /* scroll sutil */
  ::-webkit-scrollbar{height:10px;width:10px}
  ::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:999px}
  ::-webkit-scrollbar-thumb:hover{background:#9ca3af}
  /* inputs num sin flechas (webkit) */
  input[type=number]::-webkit-inner-spin-button,
  input[type=number]::-webkit-outer-spin-button{ -webkit-appearance: none; margin: 0; }
</style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">

  <!-- Encabezado -->
  <header class="max-w-7xl mx-auto px-4 pt-6">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-5 py-4">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <h2 class="text-xl md:text-2xl font-bold">🪙 Liquidación de Conductores</h2>
        <div class="text-sm text-slate-600">
          Periodo:
          <strong><?= htmlspecialchars($desde) ?></strong> &rarr;
          <strong><?= htmlspecialchars($hasta) ?></strong>
          <?php if ($empresaFiltro !== ""): ?>
            <span class="mx-2">•</span> Empresa: <strong><?= htmlspecialchars($empresaFiltro) ?></strong>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </header>

  <!-- Contenido -->
  <main class="max-w-7xl mx-auto px-4 py-6">
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 items-start">

      <!-- Columna 1: Tarifas + Filtro -->
      <section class="space-y-5">
        <!-- Tarifas -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
          <h3 class="text-lg font-semibold text-center mb-3">🚐 Tarifas por Tipo de Vehículo</h3>
          <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table id="tabla_tarifas" class="min-w-full text-sm">
              <thead class="bg-blue-600 text-white">
                <tr>
                  <th class="px-3 py-2 text-left">Tipo de Vehículo</th>
                  <th class="px-3 py-2 text-center">Viaje Completo</th>
                  <th class="px-3 py-2 text-center">Viaje Medio</th>
                  <th class="px-3 py-2 text-center">Viaje Extra</th>
                  <th class="px-3 py-2 text-center">Carrotanque</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 bg-white">
              <?php foreach ($vehiculos as $veh):
                $t = $tarifas_guardadas[$veh] ?? ["completo"=>0,"medio"=>0,"extra"=>0,"carrotanque"=>0];
              ?>
                <tr class="hover:bg-blue-50/40 transition-colors">
                  <td class="px-3 py-2 font-medium"><?= htmlspecialchars($veh) ?></td>
                  <?php if ($veh === "Carrotanque"): ?>
                    <td class="px-3 py-2 text-center text-slate-400">—</td>
                    <td class="px-3 py-2 text-center text-slate-400">—</td>
                    <td class="px-3 py-2 text-center text-slate-400">—</td>
                    <td class="px-3 py-2">
                      <input type="number" step="1000" value="<?= (int)$t['carrotanque'] ?>"
                             class="w-full max-w-[160px] ml-auto rounded-xl border border-slate-300 px-3 py-2 text-right outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition"
                             oninput="recalcular()">
                    </td>
                  <?php else: ?>
                    <td class="px-3 py-2">
                      <input type="number" step="1000" value="<?= (int)$t['completo'] ?>"
                             class="w-full max-w-[160px] ml-auto rounded-xl border border-slate-300 px-3 py-2 text-right outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition"
                             oninput="recalcular()">
                    </td>
                    <td class="px-3 py-2">
                      <input type="number" step="1000" value="<?= (int)$t['medio'] ?>"
                             class="w-full max-w-[160px] ml-auto rounded-xl border border-slate-300 px-3 py-2 text-right outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition"
                             oninput="recalcular()">
                    </td>
                    <td class="px-3 py-2">
                      <input type="number" step="1000" value="<?= (int)$t['extra'] ?>"
                             class="w-full max-w-[160px] ml-auto rounded-xl border border-slate-300 px-3 py-2 text-right outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition"
                             oninput="recalcular()">
                    </td>
                    <td class="px-3 py-2 text-center text-slate-400">—</td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Filtro (re-filtrar) -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
          <h5 class="text-base font-semibold text-center mb-4">📅 Filtro de Liquidación</h5>
          <form class="grid grid-cols-1 md:grid-cols-4 gap-3" method="get">
            <label class="block md:col-span-1">
              <span class="block text-sm font-medium mb-1">Desde</span>
              <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" required
                     class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition">
            </label>
            <label class="block md:col-span-1">
              <span class="block text-sm font-medium mb-1">Hasta</span>
              <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" required
                     class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition">
            </label>
            <label class="block md:col-span-1">
              <span class="block text-sm font-medium mb-1">Empresa</span>
              <select name="empresa"
                      class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition">
                <option value="">-- Todas --</option>
                <?php foreach($empresas as $e): ?>
                  <option value="<?= htmlspecialchars($e) ?>" <?= $empresaFiltro==$e?'selected':'' ?>>
                    <?= htmlspecialchars($e) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <div class="md:col-span-1 flex items-end">
              <button class="w-full rounded-xl bg-blue-600 text-white py-2.5 font-semibold shadow hover:bg-blue-700 active:bg-blue-800 focus:ring-4 focus:ring-blue-200 transition">
                Filtrar
              </button>
            </div>
          </form>
        </div>
      </section>

      <!-- Columna 2: Resumen por conductor -->
      <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
        <div class="flex items-center justify-between gap-3">
          <h3 class="text-lg font-semibold">🧑‍✈️ Resumen por Conductor</h3>
          <span id="total_chip_container"
                class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-blue-700 font-semibold text-sm">
            🔢 Total General: <span id="total_general">0</span>
          </span>
        </div>

        <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
          <table id="tabla_conductores" class="min-w-full text-sm">
            <thead class="bg-blue-600 text-white">
              <tr>
                <th class="px-3 py-2 text-left">Conductor</th>
                <th class="px-3 py-2 text-center">Tipo Vehículo</th>
                <th class="px-3 py-2 text-center">Completos</th>
                <th class="px-3 py-2 text-center">Medios</th>
                <th class="px-3 py-2 text-center">Extras</th>
                <th class="px-3 py-2 text-center">Carrotanques</th>
                <th class="px-3 py-2 text-center">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
            <?php foreach ($datos as $conductor => $viajes): ?>
              <tr data-vehiculo="<?= htmlspecialchars($viajes['vehiculo']) ?>" class="hover:bg-blue-50/40 transition-colors">
                <td class="px-3 py-2">
                  <button type="button"
                          class="conductor-link text-blue-700 hover:text-blue-900 underline underline-offset-2 transition"
                          title="Ver viajes">
                    <?= htmlspecialchars($conductor) ?>
                  </button>
                </td>
                <td class="px-3 py-2 text-center"><?= htmlspecialchars($viajes['vehiculo']) ?></td>
                <td class="px-3 py-2 text-center"><?= (int)$viajes["completos"] ?></td>
                <td class="px-3 py-2 text-center"><?= (int)$viajes["medios"] ?></td>
                <td class="px-3 py-2 text-center"><?= (int)$viajes["extras"] ?></td>
                <td class="px-3 py-2 text-center"><?= (int)$viajes["carrotanques"] ?></td>
                <td class="px-3 py-2">
                  <input type="text" class="totales w-full max-w-[180px] mx-auto rounded-xl border border-slate-300 px-3 py-2 text-right bg-slate-50 outline-none"
                         readonly>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Columna 3: Panel viajes -->
      <aside class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
        <h4 class="text-base font-semibold mb-3">🧳 Viajes</h4>
        <div id="contenidoPanel"
             class="min-h-[220px] rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-600 flex items-center justify-center">
          <p class="m-0 text-center">Selecciona un conductor para ver sus viajes aquí.</p>
        </div>
      </aside>

    </div>
  </main>

  <script>
    function getTarifas(){
      const tarifas = {};
      document.querySelectorAll('#tabla_tarifas tbody tr').forEach(row=>{
        const veh = row.cells[0].innerText.trim();
        const completo = row.cells[1]?.querySelector('input') ? parseFloat(row.cells[1].querySelector('input').value)||0 : 0;
        const medio    = row.cells[2]?.querySelector('input') ? parseFloat(row.cells[2].querySelector('input').value)||0 : 0;
        const extra    = row.cells[3]?.querySelector('input') ? parseFloat(row.cells[3].querySelector('input').value)||0 : 0;
        const carro    = row.cells[4]?.querySelector('input') ? parseFloat(row.cells[4].querySelector('input').value)||0 : 0;
        tarifas[veh] = {completo, medio, extra, carrotanque: carro};
      });
      return tarifas;
    }

    function formatNumber(num){ return (num||0).toLocaleString('es-CO'); }

    function recalcular(){
      const tarifas = getTarifas();
      const filas = document.querySelectorAll('#tabla_conductores tbody tr');
      let totalGeneral = 0;
      filas.forEach(f=>{
        const veh = f.dataset.vehiculo;
        const c = parseInt(f.cells[2].innerText)||0;
        const m = parseInt(f.cells[3].innerText)||0;
        const e = parseInt(f.cells[4].innerText)||0;
        const ca = parseInt(f.cells[5].innerText)||0;
        const t = tarifas[veh] || {completo:0,medio:0,extra:0,carrotanque:0};
        const totalFila = c*t.completo + m*t.medio + e*t.extra + ca*t.carrotanque;
        const inputTotal = f.querySelector('input.totales');
        if (inputTotal) inputTotal.value = formatNumber(totalFila);
        totalGeneral += totalFila;
      });
      document.getElementById('total_general').innerText = formatNumber(totalGeneral);
    }

    // Guardar tarifas al cambiar inputs (AJAX)
    document.querySelectorAll('#tabla_tarifas input').forEach(input=>{
      input.addEventListener('change', ()=>{
        const fila = input.closest('tr');
        const tipoVehiculo = fila.cells[0].innerText.trim();
        const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
        // mapear campo según columna
        const idx = Array.from(fila.cells).findIndex(c=>c.contains(input));
        const campos = ['completo','medio','extra','carrotanque'];
        // Si la fila es Carrotanque, el input está en la columna 4 (idx=4) -> 'carrotanque'
        const campo = (tipoVehiculo === 'Carrotanque') ? 'carrotanque' : campos[idx-1];
        const valor = parseInt(input.value)||0;

        fetch(`<?= basename(__FILE__) ?>`, {
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:new URLSearchParams({guardar_tarifa:1, empresa, tipo_vehiculo:tipoVehiculo, campo, valor})
        })
        .then(r=>r.text())
        .then(t=>{
          if (t.trim() !== 'ok') console.error('Error guardando tarifa:', t);
          recalcular();
        });
      });
    });

    // Click en conductor → carga viajes en panel (AJAX)
    document.querySelectorAll('.conductor-link').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const nombre = btn.textContent.trim();
        const desde  = "<?= htmlspecialchars($desde) ?>";
        const hasta  = "<?= htmlspecialchars($hasta) ?>";
        const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
        const panel = document.getElementById('contenidoPanel');
        panel.innerHTML = "<p class='text-center animate-pulse'>Cargando…</p>";

        fetch(`<?= basename(__FILE__) ?>?viajes_conductor=${encodeURIComponent(nombre)}&desde=${desde}&hasta=${hasta}&empresa=${encodeURIComponent(empresa)}`)
          .then(r=>r.text())
          .then(html=>{ panel.innerHTML = html; });
      });
    });

    // Primer cálculo
    recalcular();
  </script>

</body>
</html>
