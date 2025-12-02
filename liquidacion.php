<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }

/* =======================================================
   🔹 Guardar tarifas por vehículo y empresa (AJAX)
   (ahora soporta el campo 'siapana')
======================================================= */
if (isset($_POST['guardar_tarifa'])) {
    $empresa  = $conn->real_escape_string($_POST['empresa']);
    $vehiculo = $conn->real_escape_string($_POST['tipo_vehiculo']);
    $campo    = $conn->real_escape_string($_POST['campo']); // completo|medio|extra|carrotanque|siapana
    $valor    = (int)$_POST['valor'];

    // ⚠️ Recomendado: validar $campo contra allowlist
    $allow = ['completo','medio','extra','carrotanque','siapana'];
    if (!in_array($campo, $allow, true)) { echo "error: campo inválido"; exit; }

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
            // ahora con 'siapana'
            $datos[$nombre] = ["vehiculo"=>$vehiculo,"completos"=>0,"medios"=>0,"extras"=>0,"carrotanques"=>0,"siapana"=>0];
        }
        if (!in_array($vehiculo, $vehiculos, true)) $vehiculos[] = $vehiculo;

        // 1) Carrotanque "puro"
        if ($vehiculo === "Carrotanque" && $guiones == 0) {
            $datos[$nombre]["carrotanques"]++;
        }
        // 2) Si la ruta menciona Siapana → cuenta como 'siapana' (tarifa especial)
        elseif (stripos($ruta, "Siapana") !== false) {
            $datos[$nombre]["siapana"]++;
        }
        // 3) Resto de reglas previas
        elseif (stripos($ruta, "Maicao") === false) {
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
  ::-webkit-scrollbar{height:10px;width:10px}
  ::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:999px}
  ::-webkit-scrollbar-thumb:hover{background:#9ca3af}
  input[type=number]::-webkit-inner-spin-button,
  input[type=number]::-webkit-outer-spin-button{ -webkit-appearance: none; margin: 0; }
</style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800">

  <!-- Encabezado -->
  <header class="max-w-[1600px] mx-auto px-3 md:px-4 pt-6">
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
  <main class="max-w-[1600px] mx-auto px-3 md:px-4 py-6">
    <div class="grid grid-cols-1 xl:grid-cols-[1fr_2.6fr_0.9fr] gap-5 items-start">

      <!-- Columna 1: Tarifas + Filtro -->
      <section class="space-y-5">

        <!-- Tarjetas de tarifas (con SIAPANA) -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
          <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <span>🚐 Tarifas por Tipo de Vehículo</span>
          </h3>

          <div id="tarifas_grid" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php foreach ($vehiculos as $veh):
              // si no hay registro guardado, ponemos 0; añadimos 'siapana'
              $t = $tarifas_guardadas[$veh] ?? ["completo"=>0,"medio"=>0,"extra"=>0,"carrotanque"=>0,"siapana"=>0];
            ?>
            <div class="tarjeta-tarifa rounded-2xl border border-slate-200 p-4 shadow-sm bg-slate-50"
                 data-vehiculo="<?= htmlspecialchars($veh) ?>">

              <div class="flex items-center justify-between mb-3">
                <div class="text-base font-semibold"><?= htmlspecialchars($veh) ?></div>
                <span class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-700 border border-blue-200">Config</span>
              </div>

              <?php if ($veh === "Carrotanque"): ?>
                <label class="block mb-3">
                  <span class="block text-sm font-medium mb-1">Carrotanque</span>
                  <input type="number" step="1000" value="<?= (int)($t['carrotanque'] ?? 0) ?>"
                         data-campo="carrotanque"
                         class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-white outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition"
                         oninput="recalcular()">
                </label>
                <label class="block">
                  <span class="block text-sm font-medium mb-1">Siapana</span>
                  <input type="number" step="1000" value="<?= (int)($t['siapana'] ?? 0) ?>"
                         data-campo="siapana"
                         class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-white outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition"
                         oninput="recalcular()">
                </label>
              <?php else: ?>
                <label class="block mb-3">
                  <span class="block text-sm font-medium mb-1">Viaje Completo</span>
                  <input type="number" step="1000" value="<?= (int)($t['completo'] ?? 0) ?>"
                         data-campo="completo"
                         class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-white outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition"
                         oninput="recalcular()">
                </label>

                <label class="block mb-3">
                  <span class="block text-sm font-medium mb-1">Viaje Medio</span>
                  <input type="number" step="1000" value="<?= (int)($t['medio'] ?? 0) ?>"
                         data-campo="medio"
                         class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-white outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition"
                         oninput="recalcular()">
                </label>

                <label class="block mb-3">
                  <span class="block text-sm font-medium mb-1">Viaje Extra</span>
                  <input type="number" step="1000" value="<?= (int)($t['extra'] ?? 0) ?>"
                         data-campo="extra"
                         class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-white outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition"
                         oninput="recalcular()">
                </label>

                <label class="block">
                  <span class="block text-sm font-medium mb-1">Siapana</span>
                  <input type="number" step="1000" value="<?= (int)($t['siapana'] ?? 0) ?>"
                         data-campo="siapana"
                         class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-white outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition"
                         oninput="recalcular()">
                </label>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Filtro -->
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

      <!-- Columna 2: Resumen por conductor (ahora con Siapana) -->
      <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
        <div class="flex items-center justify-between gap-3">
          <h3 class="text-lg font-semibold">🧑‍✈️ Resumen por Conductor</h3>
          <div id="total_chip_container" class="inline-flex items-center gap-3">
            <span class="inline-flex items-center gap-2 rounded-full border border-green-200 bg-green-50 px-3 py-1 text-green-700 font-semibold text-sm">
              📅 Mensual: <span id="total_mensual">0</span>
            </span>
            <span class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-blue-700 font-semibold text-sm">
              🔢 Viajes: <span id="total_viajes">0</span>
            </span>
            <span class="inline-flex items-center gap-2 rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-purple-700 font-semibold text-sm">
              💰 Total: <span id="total_general">0</span>
            </span>
          </div>
        </div>

        <div class="mt-4 w-full rounded-xl border border-slate-200">
          <table id="tabla_conductores" class="w-full text-sm table-fixed">
            <colgroup>
              <col style="width:22%">
              <col style="width:12%">
              <col style="width:7%">
              <col style="width:7%">
              <col style="width:7%">
              <col style="width:7%">  <!-- Siapana -->
              <col style="width:8%">
              <col style="width:10%">
              <col style="width:20%">
            </colgroup>
            <thead class="bg-blue-600 text-white">
              <tr>
                <th class="px-3 py-2 text-left">Conductor</th>
                <th class="px-3 py-2 text-center">Tipo</th>
                <th class="px-3 py-2 text-center">C</th>
                <th class="px-3 py-2 text-center">M</th>
                <th class="px-3 py-2 text-center">E</th>
                <th class="px-3 py-2 text-center">S</th>
                <th class="px-3 py-2 text-center">CT</th>
                <th class="px-3 py-2 text-center">Mensual</th>
                <th class="px-3 py-2 text-center">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
            <?php foreach ($datos as $conductor => $viajes): ?>
              <tr data-vehiculo="<?= htmlspecialchars($viajes['vehiculo']) ?>" data-conductor="<?= htmlspecialchars($conductor) ?>" class="hover:bg-blue-50/40 transition-colors">
                <td class="px-3 py-2">
                  <div class="flex items-center gap-2">
                    <button type="button"
                            class="conductor-link text-blue-700 hover:text-blue-900 underline underline-offset-2 transition"
                            title="Ver viajes">
                      <?= htmlspecialchars($conductor) ?>
                    </button>
                    <button type="button" 
                            class="btn-mensual text-xs px-2 py-0.5 rounded-full border border-gray-300 hover:border-blue-500 hover:bg-blue-50 transition"
                            title="Marcar como mensual">
                      📅
                    </button>
                  </div>
                </td>
                <td class="px-3 py-2 text-center"><?= htmlspecialchars($viajes['vehiculo']) ?></td>
                <td class="px-3 py-2 text-center"><?= (int)$viajes["completos"] ?></td>
                <td class="px-3 py-2 text-center"><?= (int)$viajes["medios"] ?></td>
                <td class="px-3 py-2 text-center"><?= (int)$viajes["extras"] ?></td>
                <td class="px-3 py-2 text-center"><?= (int)$viajes["siapana"] ?></td>
                <td class="px-3 py-2 text-center"><?= (int)$viajes["carrotanques"] ?></td>
                <td class="px-3 py-2">
                  <div class="mensual-info hidden flex-col gap-1">
                    <input type="date" 
                           class="fecha-inicio w-full rounded border border-gray-300 px-2 py-1 text-xs"
                           placeholder="Desde...">
                    <input type="number" 
                           class="monto-mensual w-full rounded border border-gray-300 px-2 py-1 text-xs"
                           placeholder="$ Mensual"
                           step="1000"
                           oninput="calcularMensual(this)">
                    <div class="text-xs text-gray-500 dias-calculados"></div>
                  </div>
                  <button type="button" class="btn-agregar-mensual text-xs text-blue-600 hover:text-blue-800">
                    + Agregar
                  </button>
                </td>
                <td class="px-3 py-2">
                  <div class="flex flex-col">
                    <input type="text"
                           class="totales w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-slate-50 outline-none whitespace-nowrap tabular-nums"
                           readonly dir="ltr">
                    <div class="text-xs text-gray-500 text-right mt-1 mensual-detalle hidden"></div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Columna 3: Panel viajes + Conductores Mensuales -->
      <aside class="space-y-5">
        <!-- Panel viajes -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
          <h4 class="text-base font-semibold mb-3">🧳 Viajes del Conductor</h4>
          <div id="contenidoPanel"
               class="min-h-[220px] max-h-[400px] overflow-y-auto rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-600 flex items-center justify-center">
            <p class="m-0 text-center">Selecciona un conductor para ver sus viajes aquí.</p>
          </div>
        </div>

        <!-- Panel Conductores Mensuales -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
          <h4 class="text-base font-semibold mb-3 flex items-center justify-between">
            <span>📅 Conductores Mensuales</span>
            <button type="button" onclick="guardarMensuales()" 
                    class="text-xs bg-green-600 text-white px-3 py-1 rounded-lg hover:bg-green-700 transition">
              💾 Guardar
            </button>
          </h4>
          
          <div class="mb-3">
            <p class="text-xs text-gray-600 mb-2">Conductores activos este periodo:</p>
            <div id="lista-mensuales" class="space-y-2 max-h-[300px] overflow-y-auto p-2 border border-gray-100 rounded-lg">
              <!-- Se llena con JavaScript -->
            </div>
          </div>
          
          <div class="border-t pt-3">
            <div class="grid grid-cols-2 gap-2 text-sm">
              <div class="text-gray-600">Total por viajes:</div>
              <div class="text-right font-semibold" id="resumen_viajes">$0</div>
              
              <div class="text-gray-600">Total por mensuales:</div>
              <div class="text-right font-semibold text-green-600" id="resumen_mensual">$0</div>
              
              <div class="text-gray-600 font-medium border-t pt-1">TOTAL GENERAL:</div>
              <div class="text-right font-bold text-purple-600 border-t pt-1" id="resumen_total">$0</div>
            </div>
          </div>
          
          <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
            <p class="text-xs text-yellow-800 font-medium">💡 Recordatorio:</p>
            <p class="text-xs text-yellow-700">• Los conductores mensuales se guardan automáticamente en tu navegador<br>
            • Puedes tener diferentes configuraciones por empresa<br>
            • La fecha "hasta" siempre es el final del rango seleccionado</p>
          </div>
        </div>
      </aside>

    </div>
  </main>

  <script>
    // Configuración inicial
    const CONFIG_KEY = 'config_mensuales_<?= htmlspecialchars($empresaFiltro) ?>';
    const RANGO_HASTA = '<?= htmlspecialchars($hasta) ?>';
    let configMensuales = JSON.parse(localStorage.getItem(CONFIG_KEY)) || {};

    // Cargar configuración al iniciar
    function cargarConfiguracion() {
      Object.entries(configMensuales).forEach(([conductor, datos]) => {
        const fila = document.querySelector(`tr[data-conductor="${conductor}"]`);
        if (fila) {
          const btnAgregar = fila.querySelector('.btn-agregar-mensual');
          const divInfo = fila.querySelector('.mensual-info');
          const detalle = fila.querySelector('.mensual-detalle');
          
          btnAgregar.classList.add('hidden');
          divInfo.classList.remove('hidden');
          
          const fechaInput = divInfo.querySelector('.fecha-inicio');
          const montoInput = divInfo.querySelector('.monto-mensual');
          const diasSpan = divInfo.querySelector('.dias-calculados');
          
          fechaInput.value = datos.desde;
          montoInput.value = datos.monto;
          
          // Calcular días y monto
          calcularDiasYMonto(fechaInput, montoInput, diasSpan, detalle);
          
          // Marcar botón como activo
          const btnMensual = fila.querySelector('.btn-mensual');
          btnMensual.classList.remove('border-gray-300');
          btnMensual.classList.add('border-green-500', 'bg-green-100');
        }
      });
      actualizarListaMensuales();
      recalcular();
    }

    // Calcular días y monto proporcional
    function calcularDiasYMonto(fechaInput, montoInput, diasSpan, detalle) {
      const fechaDesde = new Date(fechaInput.value);
      const fechaHasta = new Date(RANGO_HASTA);
      
      if (!fechaInput.value || !montoInput.value) return;
      
      // Calcular diferencia en días
      const diffTime = Math.abs(fechaHasta - fechaDesde);
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
      
      // Calcular monto proporcional (asumiendo 30 días por mes)
      const montoDiario = parseFloat(montoInput.value) / 30;
      const montoProporcional = Math.round(montoDiario * diffDays);
      
      diasSpan.textContent = `${diffDays} días`;
      if (detalle) {
        detalle.textContent = `Mensual: $${montoProporcional.toLocaleString('es-CO')}`;
        detalle.classList.remove('hidden');
      }
      
      return montoProporcional;
    }

    function calcularMensual(input) {
      const fila = input.closest('tr');
      const fechaInput = fila.querySelector('.fecha-inicio');
      const montoInput = fila.querySelector('.monto-mensual');
      const diasSpan = fila.querySelector('.dias-calculados');
      const detalle = fila.querySelector('.mensual-detalle');
      
      const montoProporcional = calcularDiasYMonto(fechaInput, montoInput, diasSpan, detalle);
      
      // Guardar en configuración
      const conductor = fila.dataset.conductor;
      if (fechaInput.value && montoInput.value) {
        configMensuales[conductor] = {
          desde: fechaInput.value,
          monto: parseFloat(montoInput.value),
          hasta: RANGO_HASTA
        };
      }
      
      actualizarListaMensuales();
      recalcular();
    }

    function actualizarListaMensuales() {
      const lista = document.getElementById('lista-mensuales');
      lista.innerHTML = '';
      
      let totalMensual = 0;
      
      Object.entries(configMensuales).forEach(([conductor, datos]) => {
        const fila = document.querySelector(`tr[data-conductor="${conductor}"]`);
        if (fila) {
          const fechaInput = fila.querySelector('.fecha-inicio');
          const montoInput = fila.querySelector('.monto-mensual');
          const diasSpan = fila.querySelector('.dias-calculados');
          const detalle = fila.querySelector('.mensual-detalle');
          
          const montoProporcional = calcularDiasYMonto(fechaInput, montoInput, diasSpan, detalle);
          totalMensual += montoProporcional;
          
          // Calcular días
          const fechaDesde = new Date(datos.desde);
          const fechaHasta = new Date(RANGO_HASTA);
          const diffDays = Math.ceil(Math.abs(fechaHasta - fechaDesde) / (1000 * 60 * 60 * 24)) + 1;
          
          const item = document.createElement('div');
          item.className = 'flex justify-between items-center p-2 bg-gray-50 rounded-lg';
          item.innerHTML = `
            <div>
              <div class="font-medium text-sm">${conductor}</div>
              <div class="text-xs text-gray-500">
                ${datos.desde} → ${RANGO_HASTA} (${diffDays} días)
              </div>
            </div>
            <div class="text-right">
              <div class="text-sm font-semibold text-green-600">$${montoProporcional.toLocaleString('es-CO')}</div>
              <div class="text-xs text-gray-500">de $${parseInt(datos.monto).toLocaleString('es-CO')}/mes</div>
            </div>
          `;
          lista.appendChild(item);
        }
      });
      
      if (Object.keys(configMensuales).length === 0) {
        lista.innerHTML = '<p class="text-center text-gray-500 text-sm py-4">No hay conductores mensuales configurados.</p>';
      }
      
      // Actualizar resumen
      document.getElementById('resumen_mensual').textContent = `$${totalMensual.toLocaleString('es-CO')}`;
    }

    function getTarifas(){
      const tarifas = {};
      document.querySelectorAll('.tarjeta-tarifa').forEach(card=>{
        const veh = card.dataset.vehiculo;
        const val = (campo)=>{
          const el = card.querySelector(`input[data-campo="${campo}"]`);
          return el ? (parseFloat(el.value)||0) : 0;
        };
        tarifas[veh] = {
          completo:    val('completo'),
          medio:       val('medio'),
          extra:       val('extra'),
          carrotanque: val('carrotanque'),
          siapana:     val('siapana')
        };
      });
      return tarifas;
    }

    function formatNumber(num){ return (num||0).toLocaleString('es-CO'); }

    function recalcular(){
      const tarifas = getTarifas();
      const filas = document.querySelectorAll('#tabla_conductores tbody tr');
      let totalViajes = 0;
      let totalMensual = 0;
      
      filas.forEach(f=>{
        const veh = f.dataset.vehiculo;
        const conductor = f.dataset.conductor;
        
        // Calcular por viajes
        const c  = parseInt(f.cells[2].innerText)||0;
        const m  = parseInt(f.cells[3].innerText)||0;
        const e  = parseInt(f.cells[4].innerText)||0;
        const s  = parseInt(f.cells[5].innerText)||0;
        const ca = parseInt(f.cells[6].innerText)||0;
        const t  = tarifas[veh] || {completo:0,medio:0,extra:0,carrotanque:0,siapana:0};
        const totalViajesFila = c*t.completo + m*t.medio + e*t.extra + s*t.siapana + ca*t.carrotanque;
        
        // Calcular por mensualidad (si aplica)
        let totalMensualFila = 0;
        if (configMensuales[conductor]) {
          const fechaInput = f.querySelector('.fecha-inicio');
          const montoInput = f.querySelector('.monto-mensual');
          const diasSpan = f.querySelector('.dias-calculados');
          const detalle = f.querySelector('.mensual-detalle');
          
          totalMensualFila = calcularDiasYMonto(fechaInput, montoInput, diasSpan, detalle) || 0;
        }
        
        // Mostrar total combinado
        const totalFila = totalViajesFila + totalMensualFila;
        const inp = f.querySelector('input.totales');
        if (inp) inp.value = formatNumber(totalFila);
        
        totalViajes += totalViajesFila;
        totalMensual += totalMensualFila;
      });
      
      // Actualizar todos los totales
      document.getElementById('total_viajes').innerText = formatNumber(totalViajes);
      document.getElementById('total_mensual').innerText = formatNumber(totalMensual);
      document.getElementById('total_general').innerText = formatNumber(totalViajes + totalMensual);
      
      document.getElementById('resumen_viajes').textContent = `$${formatNumber(totalViajes)}`;
      document.getElementById('resumen_total').textContent = `$${formatNumber(totalViajes + totalMensual)}`;
      
      // Actualizar lista de mensuales
      actualizarListaMensuales();
    }

    function guardarMensuales() {
      localStorage.setItem(CONFIG_KEY, JSON.stringify(configMensuales));
      alert('✅ Configuración de conductores mensuales guardada en tu navegador.');
    }

    // Event Listeners
    document.addEventListener('DOMContentLoaded', function() {
      cargarConfiguracion();
      
      // Click en botón "Marcar como mensual"
      document.querySelectorAll('.btn-mensual').forEach(btn => {
        btn.addEventListener('click', function() {
          const fila = this.closest('tr');
          const conductor = fila.dataset.conductor;
          const btnAgregar = fila.querySelector('.btn-agregar-mensual');
          const divInfo = fila.querySelector('.mensual-info');
          
          if (configMensuales[conductor]) {
            // Quitar de mensuales
            delete configMensuales[conductor];
            btnAgregar.classList.remove('hidden');
            divInfo.classList.add('hidden');
            fila.querySelector('.mensual-detalle').classList.add('hidden');
            this.classList.remove('border-green-500', 'bg-green-100');
            this.classList.add('border-gray-300');
          } else {
            // Agregar a mensuales
            btnAgregar.classList.add('hidden');
            divInfo.classList.remove('hidden');
            this.classList.remove('border-gray-300');
            this.classList.add('border-green-500', 'bg-green-100');
            
            // Establecer fecha por defecto (hoy o rango "desde")
            const fechaInput = divInfo.querySelector('.fecha-inicio');
            if (!fechaInput.value) {
              fechaInput.value = '<?= htmlspecialchars($desde) ?>';
            }
          }
          
          actualizarListaMensuales();
          recalcular();
        });
      });
      
      // Click en "Agregar" mensual
      document.querySelectorAll('.btn-agregar-mensual').forEach(btn => {
        btn.addEventListener('click', function() {
          const fila = this.closest('tr');
          const conductor = fila.dataset.conductor;
          
          this.classList.add('hidden');
          fila.querySelector('.mensual-info').classList.remove('hidden');
          
          // Marcar botón como activo
          fila.querySelector('.btn-mensual').classList.remove('border-gray-300');
          fila.querySelector('.btn-mensual').classList.add('border-green-500', 'bg-green-100');
          
          // Establecer fecha por defecto
          const fechaInput = fila.querySelector('.fecha-inicio');
          if (!fechaInput.value) {
            fechaInput.value = '<?= htmlspecialchars($desde) ?>';
          }
        });
      });
      
      // Cambios en fecha o monto mensual
      document.querySelectorAll('.fecha-inicio, .monto-mensual').forEach(input => {
        input.addEventListener('change', function() {
          calcularMensual(this);
        });
      });
    });

    // Guardar tarifas AJAX (incluye 'siapana')
    document.querySelectorAll('.tarjeta-tarifa input').forEach(input=>{
      input.addEventListener('change', ()=>{
        const card = input.closest('.tarjeta-tarifa');
        const tipoVehiculo = card.dataset.vehiculo;
        const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
        const campo = input.dataset.campo; // completo|medio|extra|carrotanque|siapana
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

    // Click en conductor → carga viajes (AJAX)
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
  </script>

</body>
</html>