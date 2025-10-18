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
    $campo    = $conn->real_escape_string($_POST['campo']);
    $valor    = (int)$_POST['valor'];

    // Asegura existencia del registro y actualiza el campo
    $conn->query("INSERT IGNORE INTO tarifas (empresa, tipo_vehiculo) VALUES ('$empresa', '$vehiculo')");
    $sql = "UPDATE tarifas SET $campo = $valor WHERE empresa='$empresa' AND tipo_vehiculo='$vehiculo'";
    echo $conn->query($sql) ? "ok" : ("error: " . $conn->error);
    exit;
}

/* =======================================================
   🔹 Endpoint AJAX: viajes por conductor (HTML Tailwind)
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
        echo "<div class='overflow-x-auto'>
                <table class='min-w-full text-sm'>
                  <thead>
                    <tr class='bg-blue-600 text-white text-left'>
                      <th class='py-2 px-3'>Fecha</th>
                      <th class='py-2 px-3'>Ruta</th>
                      <th class='py-2 px-3'>Empresa</th>
                      <th class='py-2 px-3'>Vehículo</th>
                    </tr>
                  </thead>
                  <tbody class='divide-y divide-gray-200'>";
        while ($r = $res->fetch_assoc()) {
            echo "<tr class='hover:bg-gray-50'>
                    <td class='py-2 px-3'>".htmlspecialchars($r['fecha'])."</td>
                    <td class='py-2 px-3'>".htmlspecialchars($r['ruta'])."</td>
                    <td class='py-2 px-3'>".htmlspecialchars($r['empresa'])."</td>
                    <td class='py-2 px-3'>".htmlspecialchars($r['tipo_vehiculo'])."</td>
                  </tr>";
        }
        echo "</tbody></table></div>";
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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Filtrar viajes</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 flex items-center justify-center p-6">
  <div class="w-full max-w-xl bg-white rounded-2xl shadow p-6">
    <h2 class="text-2xl font-semibold text-slate-800 text-center">📅 Filtrar viajes por rango</h2>
    <form method="get" class="mt-6 grid grid-cols-1 gap-4">
      <label class="block">
        <span class="text-sm font-medium text-slate-700">Desde</span>
        <input type="date" name="desde" required class="mt-1 w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500" />
      </label>
      <label class="block">
        <span class="text-sm font-medium text-slate-700">Hasta</span>
        <input type="date" name="hasta" required class="mt-1 w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500" />
      </label>
      <label class="block">
        <span class="text-sm font-medium text-slate-700">Empresa</span>
        <select name="empresa" class="mt-1 w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
          <option value="">-- Todas --</option>
          <?php foreach($empresas as $e): ?>
            <option value="<?= htmlspecialchars($e) ?>"><?php echo htmlspecialchars($e); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="mt-2 h-11 rounded-xl bg-blue-600 text-white font-semibold hover:bg-blue-700 transition" type="submit">Filtrar</button>
    </form>
  </div>
</body>
</html>
<?php
    exit;
}

/* =======================================================
   🔹 Cálculo base (mismo algoritmo) y datos para UI
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

/* Empresas y tarifas (carga anteriores) */
$empresas = [];
$resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
if ($resEmp) while ($r = $resEmp->fetch_assoc()) $empresas[] = $r['empresa'];

$tarifas_guardadas = [];
$resTarifas = $conn->query("SELECT * FROM tarifas WHERE empresa='$empresaFiltro'");
if ($resTarifas) {
  while ($r = $resTarifas->fetch_assoc()) {
    $tarifas_guardadas[$r['tipo_vehiculo']] = $r;
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Liquidación de Conductores</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    // Config rápida Tailwind (opcional):
    tailwind.config = { theme: { extend: { boxShadow: { soft:'0 6px 24px rgba(15,23,42,.06)'} } } }
  </script>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">
  <!-- Encabezado -->
  <header class="sticky top-0 z-30 bg-white/80 backdrop-blur border-b border-slate-200">
    <div class="max-w-[1200px] mx-auto px-4 py-3 flex items-center gap-3">
      <h1 class="text-xl md:text-2xl font-bold">🧮 Liquidación de Conductores</h1>
      <span class="ml-auto inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-sm font-semibold text-blue-700">
        <svg xmlns='http://www.w3.org/2000/svg' class='h-4 w-4' viewBox='0 0 24 24' fill='currentColor'><path d='M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20Zm1-11h4v2h-6V6h2v5Z'/></svg>
        Periodo: <strong class="ml-1"><?= htmlspecialchars($desde) ?></strong>–<strong><?= htmlspecialchars($hasta) ?></strong>
        <?php if ($empresaFiltro !== ""): ?>
        <span class="ml-2">• Empresa: <strong><?= htmlspecialchars($empresaFiltro) ?></strong></span>
        <?php endif; ?>
      </span>
    </div>
  </header>

  <main class="max-w-[1200px] mx-auto p-4 grid grid-cols-1 lg:grid-cols-12 gap-4">
    <!-- IZQUIERDA: Tarifas + Filtro -->
    <section class="lg:col-span-4 space-y-4">
      <!-- Tarjetas de tarifas -->
      <div class="bg-white rounded-2xl shadow-soft p-4">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-lg font-semibold">🚐 Tarifas por tipo de vehículo</h2>
        </div>
        <div id="tarifasGrid" class="grid grid-cols-1 gap-4">
          <?php foreach ($vehiculos as $veh):
            $t = $tarifas_guardadas[$veh] ?? ["completo"=>0,"medio"=>0,"extra"=>0,"carrotanque"=>0];
          ?>
          <div class="rounded-xl border border-slate-200 p-3">
            <div class="flex items-center justify-between">
              <span class="text-sm font-bold px-2 py-1 rounded-lg bg-slate-100"><?= htmlspecialchars($veh) ?></span>
            </div>
            <div class="mt-3 grid grid-cols-3 gap-3 items-end">
              <?php if ($veh === 'Carrotanque'): ?>
                <div class="col-span-3">
                  <label class="text-xs font-medium text-slate-600">Carrotanque</label>
                  <input type="number" step="1000" value="<?= (int)$t['carrotanque'] ?>" data-vehiculo="<?= htmlspecialchars($veh) ?>" data-campo="carrotanque" class="w-full h-10 rounded-xl border-slate-300 text-right px-3 focus:border-blue-500 focus:ring-blue-500 tarifa-input" oninput="recalcular()">
                </div>
              <?php else: ?>
                <div>
                  <label class="text-xs font-medium text-slate-600">Completo</label>
                  <input type="number" step="1000" value="<?= (int)$t['completo'] ?>" data-vehiculo="<?= htmlspecialchars($veh) ?>" data-campo="completo" class="w-full h-10 rounded-xl border-slate-300 text-right px-3 focus:border-blue-500 focus:ring-blue-500 tarifa-input" oninput="recalcular()">
                </div>
                <div>
                  <label class="text-xs font-medium text-slate-600">Medio</label>
                  <input type="number" step="1000" value="<?= (int)$t['medio'] ?>" data-vehiculo="<?= htmlspecialchars($veh) ?>" data-campo="medio" class="w-full h-10 rounded-xl border-slate-300 text-right px-3 focus:border-blue-500 focus:ring-blue-500 tarifa-input" oninput="recalcular()">
                </div>
                <div>
                  <label class="text-xs font-medium text-slate-600">Extra</label>
                  <input type="number" step="1000" value="<?= (int)$t['extra'] ?>" data-vehiculo="<?= htmlspecialchars($veh) ?>" data-campo="extra" class="w-full h-10 rounded-xl border-slate-300 text-right px-3 focus:border-blue-500 focus:ring-blue-500 tarifa-input" oninput="recalcular()">
                </div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Filtro de liquidación -->
      <div class="bg-white rounded-2xl shadow-soft p-4">
        <h3 class="text-lg font-semibold mb-3">📎 Filtro de liquidación</h3>
        <form class="grid grid-cols-1 md:grid-cols-4 gap-3" method="get">
          <div class="md:col-span-1">
            <label class="text-xs font-medium text-slate-600">Desde</label>
            <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" required class="w-full h-10 rounded-xl border-slate-300 px-3 focus:border-blue-500 focus:ring-blue-500">
          </div>
          <div class="md:col-span-1">
            <label class="text-xs font-medium text-slate-600">Hasta</label>
            <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" required class="w-full h-10 rounded-xl border-slate-300 px-3 focus:border-blue-500 focus:ring-blue-500">
          </div>
          <div class="md:col-span-1">
            <label class="text-xs font-medium text-slate-600">Empresa</label>
            <select name="empresa" class="w-full h-10 rounded-xl border-slate-300 px-3 focus:border-blue-500 focus:ring-blue-500">
              <option value="">-- Todas --</option>
              <?php foreach($empresas as $e): ?>
                <option value="<?= htmlspecialchars($e) ?>" <?= $empresaFiltro==$e?'selected':'' ?>><?= htmlspecialchars($e) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="md:col-span-1 flex items-end">
            <button class="w-full h-10 rounded-xl bg-blue-600 text-white font-semibold hover:bg-blue-700 transition" type="submit">Filtrar</button>
          </div>
        </form>
      </div>
    </section>

    <!-- CENTRO: Resumen por conductor (cards) -->
    <section class="lg:col-span-5 space-y-4">
      <div class="bg-white rounded-2xl shadow-soft p-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold">🧑‍✈️ Resumen por conductor</h2>
          <div class="text-sm font-bold text-blue-700 bg-blue-50 border border-blue-200 px-3 py-1 rounded-full">Total General: <span id="total_general">0</span></div>
        </div>

        <div id="listaConductores" class="mt-4 grid sm:grid-cols-2 gap-3">
          <?php foreach ($datos as $conductor => $viajes): ?>
          <article class="group rounded-xl border border-slate-200 p-3 hover:shadow-soft transition" data-vehiculo="<?= htmlspecialchars($viajes['vehiculo']) ?>">
            <header class="flex items-center justify-between">
              <button class="conductor-link text-left font-semibold text-slate-800 hover:text-blue-700"><?= htmlspecialchars($conductor) ?></button>
              <span class="text-[11px] font-semibold px-2 py-1 rounded-md bg-slate-100 text-slate-700"><?= htmlspecialchars($viajes['vehiculo']) ?></span>
            </header>
            <div class="mt-3 grid grid-cols-4 gap-2 text-center text-xs">
              <div class="rounded-lg bg-green-50 border border-green-200 p-2">
                <div class="font-semibold">Comp</div>
                <div class="count-completos font-bold text-green-700"><?= (int)$viajes['completos'] ?></div>
              </div>
              <div class="rounded-lg bg-amber-50 border border-amber-200 p-2">
                <div class="font-semibold">Med</div>
                <div class="count-medios font-bold text-amber-700"><?= (int)$viajes['medios'] ?></div>
              </div>
              <div class="rounded-lg bg-indigo-50 border border-indigo-200 p-2">
                <div class="font-semibold">Ext</div>
                <div class="count-extras font-bold text-indigo-700"><?= (int)$viajes['extras'] ?></div>
              </div>
              <div class="rounded-lg bg-sky-50 border border-sky-200 p-2">
                <div class="font-semibold">Carr</div>
                <div class="count-carro font-bold text-sky-700"><?= (int)$viajes['carrotanques'] ?></div>
              </div>
            </div>
            <footer class="mt-3">
              <label class="text-[11px] text-slate-500">Total</label>
              <input type="text" class="totales w-full h-10 text-right px-3 rounded-xl border-slate-300 bg-slate-50" readonly>
            </footer>
          </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- DERECHA: Panel de viajes -->
    <aside class="lg:col-span-3">
      <div class="bg-white rounded-2xl shadow-soft p-4 sticky top-[76px]">
        <h3 class="text-lg font-semibold mb-3">🧳 Viajes</h3>
        <div id="contenidoPanel" class="text-sm text-slate-600">
          <p class="mb-0">Selecciona un conductor para ver sus viajes aquí.</p>
        </div>
      </div>
    </aside>
  </main>

  <script>
    function getTarifas(){
      const t = {};
      document.querySelectorAll('.tarifa-input').forEach(inp=>{
        const veh = inp.dataset.vehiculo;
        const campo = inp.dataset.campo;
        const val = parseFloat(inp.value)||0;
        if(!t[veh]) t[veh] = {completo:0,medio:0,extra:0,carrotanque:0};
        t[veh][campo] = val;
      });
      return t;
    }
    function formatNumber(num){ return (num||0).toLocaleString('es-CO'); }

    function recalcular(){
      const tarifas = getTarifas();
      let totalGeneral = 0;
      document.querySelectorAll('#listaConductores article').forEach(card=>{
        const veh = card.dataset.vehiculo;
        const c  = parseInt(card.querySelector('.count-completos')?.textContent)||0;
        const m  = parseInt(card.querySelector('.count-medios')?.textContent)||0;
        const e  = parseInt(card.querySelector('.count-extras')?.textContent)||0;
        const ca = parseInt(card.querySelector('.count-carro')?.textContent)||0;
        const t  = tarifas[veh] || {completo:0,medio:0,extra:0,carrotanque:0};
        const total = c*t.completo + m*t.medio + e*t.extra + ca*t.carrotanque;
        const input = card.querySelector('input.totales');
        if (input) input.value = formatNumber(total);
        totalGeneral += total;
      });
      document.getElementById('total_general').textContent = formatNumber(totalGeneral);
    }

    // Guardar tarifa en caliente
    document.querySelectorAll('.tarifa-input').forEach(inp=>{
      inp.addEventListener('change', ()=>{
        const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
        const tipoVehiculo = inp.dataset.vehiculo;
        const campo = inp.dataset.campo;
        const valor = parseInt(inp.value)||0;
        fetch("<?= basename(__FILE__) ?>",{
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:new URLSearchParams({guardar_tarifa:1, empresa:empresa, tipo_vehiculo:tipoVehiculo, campo:campo, valor:valor})
        }).then(r=>r.text()).then(txt=>{
          if(txt.trim()!=='ok') console.error('Error guardando tarifa:',txt);
          recalcular();
        });
      })
    })

    // Click en conductor → carga viajes al panel
    document.querySelectorAll('.conductor-link').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const nombre = btn.textContent.trim();
        const desde  = "<?= htmlspecialchars($desde) ?>";
        const hasta  = "<?= htmlspecialchars($hasta) ?>";
        const empresa= "<?= htmlspecialchars($empresaFiltro) ?>";
        const panel  = document.getElementById('contenidoPanel');
        panel.innerHTML = "<div class='py-6 text-center text-slate-500'>Cargando…</div>";
        fetch(`<?= basename(__FILE__) ?>?viajes_conductor=${encodeURIComponent(nombre)}&desde=${desde}&hasta=${hasta}&empresa=${encodeURIComponent(empresa)}`)
          .then(r=>r.text())
          .then(html=>{ panel.innerHTML = html; });
      })
    })

    // Calcula al iniciar
    recalcular();
  </script>
</body>
</html>
