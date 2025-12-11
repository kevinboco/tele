<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

/* =======================================================
   🔹 Guardar tarifas por vehículo y empresa (AJAX)
   (ahora soporta el campo 'siapana')
======================================================= */
if (isset($_POST['guardar_tarifa'])) {
    $empresa  = $conn->real_escape_string($_POST['empresa']);
    $vehiculo = $conn->real_escape_string($_POST['tipo_vehiculo']);
    $campo    = $conn->real_escape_string($_POST['campo']); // completo|medio|extra|carrotanque|siapana
    $valor    = (int)$_POST['valor'];

    // ⚠️ Validar campo
    $allow = ['completo','medio','extra','carrotanque','siapana'];
    if (!in_array($campo, $allow, true)) { echo "error: campo inválido"; exit; }

    $conn->query("INSERT IGNORE INTO tarifas (empresa, tipo_vehiculo) VALUES ('$empresa', '$vehiculo')");
    $sql = "UPDATE tarifas SET $campo = $valor WHERE empresa='$empresa' AND tipo_vehiculo='$vehiculo'";
    echo $conn->query($sql) ? "ok" : "error: " . $conn->error;
    exit;
}

/* =======================================================
   🔹 Guardar CLASIFICACIÓN de rutas (manual) - AJAX
   (completo/medio/extra/siapana/carrotanque)
======================================================= */
if (isset($_POST['guardar_clasificacion'])) {
    $ruta       = $conn->real_escape_string($_POST['ruta']);
    $vehiculo   = $conn->real_escape_string($_POST['tipo_vehiculo']);
    $clasif     = $conn->real_escape_string($_POST['clasificacion']);

    $allowClasif = ['completo','medio','extra','siapana','carrotanque'];
    if (!in_array($clasif, $allowClasif, true)) {
        echo "error: clasificación inválida";
        exit;
    }

    $sql = "INSERT INTO ruta_clasificacion (ruta, tipo_vehiculo, clasificacion)
            VALUES ('$ruta', '$vehiculo', '$clasif')
            ON DUPLICATE KEY UPDATE clasificacion = VALUES(clasificacion)";
    echo $conn->query($sql) ? "ok" : ("error: " . $conn->error);
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
   AHORA usando CLASIFICACIÓN MANUAL DE RUTAS
======================================================= */
$desde = $_GET['desde'];
$hasta = $_GET['hasta'];
$empresaFiltro = $_GET['empresa'] ?? "";

/* --- Cargar clasificaciones de rutas desde BD --- */
$clasif_rutas = [];
$resClasif = $conn->query("SELECT ruta, tipo_vehiculo, clasificacion FROM ruta_clasificacion");
if ($resClasif) {
    while ($r = $resClasif->fetch_assoc()) {
        $key = mb_strtolower(trim($r['ruta'] . '|' . $r['tipo_vehiculo']), 'UTF-8');
        $clasif_rutas[$key] = $r['clasificacion']; // completo|medio|extra|siapana|carrotanque
    }
}

/* --- Traer viajes --- */
$sql = "SELECT nombre, ruta, empresa, tipo_vehiculo FROM viajes
        WHERE fecha BETWEEN '$desde' AND '$hasta'";
if ($empresaFiltro !== "") {
    $empresaFiltro = $conn->real_escape_string($empresaFiltro);
    $sql .= " AND empresa = '$empresaFiltro'";
}
$res = $conn->query($sql);

$datos = [];
$vehiculos = [];
$rutasUnicas = []; // para mostrar todas las rutas y clasificarlas

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $nombre   = $row['nombre'];
        $ruta     = $row['ruta'];
        $vehiculo = $row['tipo_vehiculo'];

        // clave normalizada ruta+vehículo
        $keyRuta = mb_strtolower(trim($ruta . '|' . $vehiculo), 'UTF-8');

        // Guardar lista de rutas únicas (para el panel de clasificación)
        if (!isset($rutasUnicas[$keyRuta])) {
            $rutasUnicas[$keyRuta] = [
                'ruta'          => $ruta,
                'vehiculo'      => $vehiculo,
                'clasificacion' => $clasif_rutas[$keyRuta] ?? ''
            ];
        }

        // Lista de tipos de vehículo (para las tarjetas de tarifas)
        if (!in_array($vehiculo, $vehiculos, true)) {
            $vehiculos[] = $vehiculo;
        }

        // Inicializar datos del conductor
        if (!isset($datos[$nombre])) {
            $datos[$nombre] = [
                "vehiculo"     => $vehiculo,
                "completos"    => 0,
                "medios"       => 0,
                "extras"       => 0,
                "carrotanques" => 0,
                "siapana"      => 0
            ];
        }

        // 🔹 Clasificación MANUAL de la ruta
        $clasifRuta = $clasif_rutas[$keyRuta] ?? '';

        // Si la ruta todavía no tiene clasificación, NO se suma a ninguna columna
        if ($clasifRuta === '') {
            continue;
        }

        switch ($clasifRuta) {
            case 'completo':
                $datos[$nombre]["completos"]++;
                break;
            case 'medio':
                $datos[$nombre]["medios"]++;
                break;
            case 'extra':
                $datos[$nombre]["extras"]++;
                break;
            case 'siapana':
                $datos[$nombre]["siapana"]++;
                break;
            case 'carrotanque':
                $datos[$nombre]["carrotanques"]++;
                break;
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
  .alert-cobro { 
    animation: pulse 2s infinite;
    border-left: 4px solid #f59e0b;
  }
  @keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
  }
  .buscar-container { position: relative; }
  .buscar-clear { 
    position: absolute; 
    right: 10px; 
    top: 50%; 
    transform: translateY(-50%); 
    background: none; 
    border: none; 
    color: #64748b; 
    cursor: pointer; 
    display: none; 
  }
  .buscar-clear:hover { color: #475569; }
  .fila-anticipo { background-color: #fef3c7 !important; }
  .select-anticipo option[value="100"] { background-color: #d1fae5; }
  .select-anticipo option[value="80"] { background-color: #fef3c7; }
  .select-anticipo option[value="70"] { background-color: #fde68a; }
  .select-anticipo option[value="50"] { background-color: #fed7aa; }
  .select-anticipo option[value="30"] { background-color: #fecaca; }
  .select-anticipo option[value="0"] { background-color: #f3f4f6; }
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

      <!-- Columna 1: Tarifas + Filtro + Clasificación de rutas -->
      <section class="space-y-5">

        <!-- Tarjetas de tarifas (con SIAPANA) -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
          <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <span>🚐 Tarifas por Tipo de Vehículo</span>
          </h3>

          <div id="tarifas_grid" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php foreach ($vehiculos as $veh):
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

        <!-- 🔹 Panel de CLASIFICACIÓN de RUTAS -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
          <h5 class="text-base font-semibold mb-3 flex items-center justify-between">
            <span>🧭 Clasificación de Rutas</span>
            <span class="text-xs text-slate-500">Se guarda en BD</span>
          </h5>
          <p class="text-xs text-slate-500 mb-3">
            Ajusta qué tipo es cada ruta. Si aparece una ruta nueva, la verás aquí y la clasificas una vez.
          </p>

          <div class="flex flex-col gap-2 mb-3 md:flex-row md:items-end">
            <div class="flex-1">
              <label class="block text-xs font-medium mb-1">Texto que debe contener la ruta</label>
              <input id="txt_patron_ruta" type="text"
                     class="w-full rounded-xl border border-slate-300 px-3 py-1.5 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500"
                     placeholder="Ej: Riohacha, Uribia-Nazareth, Siapana...">
            </div>
            <div>
              <label class="block text-xs font-medium mb-1">Clasificación</label>
              <select id="sel_clasif_masiva"
                      class="rounded-xl border border-slate-300 px-3 py-1.5 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500">
                <option value="">-- Selecciona --</option>
                <option value="completo">Completo</option>
                <option value="medio">Medio</option>
                <option value="extra">Extra</option>
                <option value="siapana">Siapana</option>
                <option value="carrotanque">Carrotanque</option>
              </select>
            </div>
            <button type="button"
                    onclick="aplicarClasificacionMasiva()"
                    class="mt-2 md:mt-0 inline-flex items-center justify-center rounded-xl bg-purple-600 text-white px-4 py-2 text-sm font-semibold hover:bg-purple-700 active:bg-purple-800 focus:ring-4 focus:ring-purple-200">
              ⚙️ Aplicar a coincidentes
            </button>
          </div>

          <div class="max-h-[260px] overflow-y-auto border border-slate-200 rounded-xl">
            <table class="w-full text-xs">
              <thead class="bg-slate-100 text-slate-600">
                <tr>
                  <th class="px-2 py-1 text-left">Ruta</th>
                  <th class="px-2 py-1 text-center">Vehículo</th>
                  <th class="px-2 py-1 text-center">Clasificación</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
              <?php foreach($rutasUnicas as $info): ?>
                <tr class="fila-ruta hover:bg-slate-50"
                    data-ruta="<?= htmlspecialchars($info['ruta']) ?>"
                    data-vehiculo="<?= htmlspecialchars($info['vehiculo']) ?>">
                  <td class="px-2 py-1 whitespace-nowrap text-left">
                    <?= htmlspecialchars($info['ruta']) ?>
                  </td>
                  <td class="px-2 py-1 text-center">
                    <?= htmlspecialchars($info['vehiculo']) ?>
                  </td>
                  <td class="px-2 py-1 text-center">
                    <select class="select-clasif-ruta rounded-lg border border-slate-300 px-2 py-1 text-xs outline-none focus:ring-2 focus:ring-blue-100"
                            data-ruta="<?= htmlspecialchars($info['ruta']) ?>"
                            data-vehiculo="<?= htmlspecialchars($info['vehiculo']) ?>">
                      <option value="">Sin clasificar</option>
                      <option value="completo"    <?= $info['clasificacion']==='completo'    ? 'selected' : '' ?>>Completo</option>
                      <option value="medio"       <?= $info['clasificacion']==='medio'       ? 'selected' : '' ?>>Medio</option>
                      <option value="extra"       <?= $info['clasificacion']==='extra'       ? 'selected' : '' ?>>Extra</option>
                      <option value="siapana"     <?= $info['clasificacion']==='siapana'     ? 'selected' : '' ?>>Siapana</option>
                      <option value="carrotanque" <?= $info['clasificacion']==='carrotanque' ? 'selected' : '' ?>>Carrotanque</option>
                    </select>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <p class="text-[11px] text-slate-500 mt-2">
            Después de cambiar clasificaciones, vuelve a darle <strong>Filtrar</strong> para recalcular la tabla de conductores.
          </p>
        </div>
      </section>

      <!-- Columna 2: Resumen por conductor (ahora con Siapana) -->
      <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
        <!-- HEADER CON BUSCADOR -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
          <div>
            <h3 class="text-lg font-semibold">🧑‍✈️ Resumen por Conductor</h3>
            <div id="contador-conductores" class="text-xs text-slate-500 mt-1">
              Mostrando <?= count($datos) ?> de <?= count($datos) ?> conductores
            </div>
          </div>
          <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
            <!-- BUSCADOR DE CONDUCTORES -->
            <div class="buscar-container w-full md:w-64">
              <input id="buscadorConductores" type="text" 
                     placeholder="Buscar conductor..." 
                     class="w-full rounded-lg border border-slate-300 px-3 py-2 pl-3 pr-10 text-sm">
              <button id="clearBuscar" class="buscar-clear">✕</button>
            </div>
            <div id="total_chip_container" class="flex items-center gap-3">
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
        </div>

        <!-- RESUMEN DE ANTICIPOS -->
        <div id="resumen_anticipos" class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg hidden">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-amber-800">📋 Resumen de Anticipos</p>
              <p class="text-xs text-amber-600" id="texto_resumen_anticipos"></p>
            </div>
            <button type="button" onclick="guardarAnticipos()" 
                    class="text-xs bg-amber-600 text-white px-3 py-1 rounded-lg hover:bg-amber-700 transition">
              💾 Guardar
            </button>
          </div>
        </div>

        <div class="mt-4 w-full rounded-xl border border-slate-200">
          <table id="tabla_conductores" class="w-full text-sm table-fixed">
            <colgroup>
              <col style="width:20%">
              <col style="width:9%">
              <col style="width:5%">
              <col style="width:5%">
              <col style="width:5%">
              <col style="width:5%">  <!-- Siapana -->
              <col style="width:6%">
              <col style="width:12%"> <!-- Anticipo -->
              <col style="width:20%"> <!-- Mensualidad -->
              <col style="width:13%"> <!-- Total -->
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
                <th class="px-3 py-2 text-center">Anticipo</th>
                <th class="px-3 py-2 text-center">Mensualidad</th>
                <th class="px-3 py-2 text-center">Total</th>
              </tr>
            </thead>
            <tbody id="tabla_conductores_body" class="divide-y divide-slate-100 bg-white">
            <?php foreach ($datos as $conductor => $viajes): ?>
              <tr data-vehiculo="<?= htmlspecialchars($viajes['vehiculo']) ?>" 
                  data-conductor="<?= htmlspecialchars($conductor) ?>" 
                  data-conductor-normalizado="<?= htmlspecialchars(mb_strtolower($conductor)) ?>"
                  class="hover:bg-blue-50/40 transition-colors">
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
                
                <!-- COLUMNA ANTICIPO -->
                <td class="px-3 py-2 text-center">
                  <select class="select-anticipo w-full max-w-[120px] rounded-lg border border-slate-300 px-2 py-1 text-sm outline-none focus:ring-2 focus:ring-blue-100"
                          data-conductor="<?= htmlspecialchars($conductor) ?>">
                    <option value="100">100% (Completo)</option>
                    <option value="90">90%</option>
                    <option value="80">80%</option>
                    <option value="70">70%</option>
                    <option value="60">60%</option>
                    <option value="50">50%</option>
                    <option value="40">40%</option>
                    <option value="30">30%</option>
                    <option value="20">20%</option>
                    <option value="10">10%</option>
                    <option value="0">0% (Pendiente)</option>
                  </select>
                  <div class="text-xs text-gray-500 mt-1">
                    <span class="pagado text-green-600"></span><br>
                    <span class="falta text-red-600"></span>
                  </div>
                </td>
                
                <td class="px-3 py-2">
                  <div class="mensual-info hidden flex-col gap-1">
                    <div class="grid grid-cols-2 gap-1">
                      <div>
                        <label class="text-xs">Desde:</label>
                        <input type="date" 
                               class="fecha-desde w-full rounded border border-gray-300 px-2 py-1 text-xs"
                               placeholder="Inicio">
                      </div>
                      <div>
                        <label class="text-xs">Hasta:</label>
                        <input type="date" 
                               class="fecha-hasta w-full rounded border border-gray-300 px-2 py-1 text-xs"
                               placeholder="Fin">
                      </div>
                    </div>
                    <input type="number" 
                           class="monto-mensual w-full rounded border border-gray-300 px-2 py-1 text-xs"
                           placeholder="$ Mensual"
                           step="1000"
                           oninput="calcularMensual(this)">
                    <div class="text-xs text-gray-500 dias-calculados"></div>
                    <button type="button" 
                            class="btn-registrar-cobro text-xs bg-green-600 text-white px-2 py-1 rounded hover:bg-green-700 mt-1"
                            onclick="registrarCobro(this)">
                      ✅ Registrar Cobro
                    </button>
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
                    <div class="text-xs text-amber-600 text-right mt-1 anticipo-detalle hidden"></div>
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
            <div class="flex gap-2">
              <button type="button" onclick="mostrarAlertasCobro()" 
                      class="text-xs bg-yellow-600 text-white px-3 py-1 rounded-lg hover:bg-yellow-700 transition">
                ⚠️ Alertas
              </button>
              <button type="button" onclick="guardarMensuales()" 
                      class="text-xs bg-green-600 text-white px-3 py-1 rounded-lg hover:bg-green-700 transition">
                💾 Guardar
              </button>
            </div>
          </h4>
          
          <!-- Alertas de cobro -->
          <div id="alertas-cobro" class="hidden mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg alert-cobro">
            <p class="text-sm font-medium text-yellow-800 mb-2">⚠️ Recordatorios de Cobro:</p>
            <div id="lista-alertas" class="space-y-1">
              <!-- Se llena con JavaScript -->
            </div>
          </div>
          
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
              
              <div class="text-gray-600">Total anticipos:</div>
              <div class="text-right font-semibold text-amber-600" id="resumen_anticipo_total">$0</div>
              
              <div class="text-gray-600 font-medium border-t pt-1">TOTAL GENERAL:</div>
              <div class="text-right font-bold text-purple-600 border-t pt-1" id="resumen_total">$0</div>
            </div>
          </div>
          
          <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
            <p class="text-xs text-blue-800 font-medium">💡 Instrucciones:</p>
            <p class="text-xs text-blue-700">1. Configura fecha DESDE y HASTA para cada conductor mensual<br>
            2. Haz click en "✅ Registrar Cobro" cuando termines<br>
            3. En la próxima liquidación, el sistema usará automáticamente la última fecha cobrada</p>
          </div>
        </div>
      </aside>

    </div>
  </main>

  <script>
    // ===== CONFIGURACIÓN DE ANTICIPOS =====
    const ANTICIPOS_KEY = 'anticipos_<?= htmlspecialchars($empresaFiltro) ?>_<?= htmlspecialchars($desde) ?>_<?= htmlspecialchars($hasta) ?>';
    let anticiposConfig = JSON.parse(localStorage.getItem(ANTICIPOS_KEY)) || {};

    // ===== BUSCADOR DE CONDUCTORES =====
    const buscadorConductores = document.getElementById('buscadorConductores');
    const clearBuscar = document.getElementById('clearBuscar');
    const contadorConductores = document.getElementById('contador-conductores');
    const tablaConductoresBody = document.getElementById('tabla_conductores_body');
    const resumenAnticiposDiv = document.getElementById('resumen_anticipos');
    const textoResumenAnticipos = document.getElementById('texto_resumen_anticipos');

    // Función para normalizar texto para búsqueda (ignorar acentos)
    function normalizarTexto(texto) {
      return texto
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '') // Eliminar acentos
        .trim();
    }

    // Función para filtrar conductores
    function filtrarConductores() {
      const textoBusqueda = normalizarTexto(buscadorConductores.value);
      const filas = tablaConductoresBody.querySelectorAll('tr');
      let filasVisibles = 0;
      
      if (textoBusqueda === '') {
        // Mostrar todas las filas
        filas.forEach(fila => {
          fila.style.display = '';
          filasVisibles++;
        });
        clearBuscar.style.display = 'none';
      } else {
        // Filtrar por nombre
        filas.forEach(fila => {
          const nombreConductor = fila.querySelector('.conductor-link').textContent;
          const nombreNormalizado = normalizarTexto(nombreConductor);
          
          if (nombreNormalizado.includes(textoBusqueda)) {
            fila.style.display = '';
            filasVisibles++;
          } else {
            fila.style.display = 'none';
          }
        });
        clearBuscar.style.display = 'block';
      }
      
      // Actualizar contador
      const totalConductores = filas.length;
      contadorConductores.textContent = `Mostrando ${filasVisibles} de ${totalConductores} conductores`;
      
      // Resaltar texto coincidente
      resaltarTextoCoincidente(textoBusqueda);
    }

    // Función para resaltar texto coincidente
    function resaltarTextoCoincidente(textoBusqueda) {
      const enlaces = tablaConductoresBody.querySelectorAll('.conductor-link');
      
      enlaces.forEach(enlace => {
        const textoOriginal = enlace.textContent;
        const textoNormalizado = normalizarTexto(textoOriginal);
        const indice = textoNormalizado.indexOf(textoBusqueda);
        
        if (textoBusqueda && indice !== -1) {
          // Crear HTML con resaltado
          const antes = textoOriginal.substring(0, indice);
          const coincidencia = textoOriginal.substring(indice, indice + textoBusqueda.length);
          const despues = textoOriginal.substring(indice + textoBusqueda.length);
          
          enlace.innerHTML = `${antes}<span class="bg-yellow-200 px-0.5 rounded">${coincidencia}</span>${despues}`;
        } else {
          enlace.innerHTML = textoOriginal;
        }
      });
    }

    // Función para cargar configuración de anticipos
    function cargarAnticipos() {
      const selects = document.querySelectorAll('.select-anticipo');
      selects.forEach(select => {
        const conductor = select.dataset.conductor;
        if (anticiposConfig[conductor]) {
          select.value = anticiposConfig[conductor].porcentaje;
          
          // Aplicar estilo visual si no es 100%
          if (select.value !== '100') {
            const fila = select.closest('tr');
            fila.classList.add('fila-anticipo');
          }
        }
      });
      actualizarResumenAnticipos();
    }

    // Función para actualizar cálculo de anticipos por fila
    function actualizarAnticipoPorFila(fila) {
      const selectAnticipo = fila.querySelector('.select-anticipo');
      const porcentaje = parseInt(selectAnticipo.value);
      const conductor = fila.dataset.conductor;
      
      // Obtener total de viajes de esta fila
      const totalViajesFila = parseFloat(fila.querySelector('.totales').value.replace(/\./g, '').replace(',', '.') || 0);
      
      // Calcular montos
      const montoPagado = totalViajesFila * (porcentaje / 100);
      const montoFalta = totalViajesFila - montoPagado;
      
      // Actualizar texto en la columna de anticipo
      const pagadoSpan = fila.querySelector('.pagado');
      const faltaSpan = fila.querySelector('.falta');
      
      if (pagadoSpan) {
        pagadoSpan.textContent = `Pagado: $${formatNumber(Math.round(montoPagado))}`;
      }
      
      if (faltaSpan) {
        if (porcentaje < 100) {
          faltaSpan.textContent = `Falta: $${formatNumber(Math.round(montoFalta))}`;
        } else {
          faltaSpan.textContent = 'Completo';
        }
      }
      
      // Actualizar detalle en columna total
      const detalleAnticipo = fila.querySelector('.anticipo-detalle');
      if (detalleAnticipo) {
        if (porcentaje < 100) {
          detalleAnticipo.textContent = `Anticipo ${porcentaje}% = $${formatNumber(Math.round(montoPagado))}`;
          detalleAnticipo.classList.remove('hidden');
        } else {
          detalleAnticipo.classList.add('hidden');
        }
      }
      
      // Guardar configuración
      anticiposConfig[conductor] = {
        porcentaje: porcentaje,
        montoPagado: montoPagado,
        montoFalta: montoFalta,
        fecha: new Date().toISOString().split('T')[0]
      };
      
      // Aplicar estilo visual
      if (porcentaje < 100) {
        fila.classList.add('fila-anticipo');
      } else {
        fila.classList.remove('fila-anticipo');
      }
      
      return { montoPagado, montoFalta };
    }

    // Función para actualizar resumen de anticipos
    function actualizarResumenAnticipos() {
      let totalPagado = 0;
      let totalFalta = 0;
      let conductoresConAnticipo = 0;
      
      const filas = tablaConductoresBody.querySelectorAll('tr');
      filas.forEach(fila => {
        const selectAnticipo = fila.querySelector('.select-anticipo');
        if (selectAnticipo && selectAnticipo.value !== '100') {
          const resultado = actualizarAnticipoPorFila(fila);
          totalPagado += resultado.montoPagado;
          totalFalta += resultado.montoFalta;
          conductoresConAnticipo++;
        }
      });
      
      // Mostrar/ocultar resumen
      if (conductoresConAnticipo > 0) {
        resumenAnticiposDiv.classList.remove('hidden');
        textoResumenAnticipos.textContent = 
          `${conductoresConAnticipo} conductores con anticipo | ` +
          `Pagado: $${formatNumber(Math.round(totalPagado))} | ` +
          `Falta: $${formatNumber(Math.round(totalFalta))}`;
        
        document.getElementById('resumen_anticipo_total').textContent = 
          `$${formatNumber(Math.round(totalPagado))}`;
      } else {
        resumenAnticiposDiv.classList.add('hidden');
        document.getElementById('resumen_anticipo_total').textContent = '$0';
      }
      
      recalcular();
    }

    // Función para guardar anticipos en localStorage
    function guardarAnticipos() {
      localStorage.setItem(ANTICIPOS_KEY, JSON.stringify(anticiposConfig));
      alert('✅ Configuración de anticipos guardada.');
    }

    // Event listeners para el buscador
    buscadorConductores.addEventListener('input', filtrarConductores);
    
    clearBuscar.addEventListener('click', () => {
      buscadorConductores.value = '';
      filtrarConductores();
      buscadorConductores.focus();
    });
    
    // Limpiar búsqueda al presionar Escape
    buscadorConductores.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        buscadorConductores.value = '';
        filtrarConductores();
      }
    });

    // Configuración inicial
    const CONFIG_KEY = 'config_mensuales_<?= htmlspecialchars($empresaFiltro) ?>';
    const COBROS_KEY = 'historial_cobros_<?= htmlspecialchars($empresaFiltro) ?>';
    const RANGO_DESDE = '<?= htmlspecialchars($desde) ?>';
    const RANGO_HASTA = '<?= htmlspecialchars($hasta) ?>';
    
    let configMensuales = JSON.parse(localStorage.getItem(CONFIG_KEY)) || {};
    let historialCobros = JSON.parse(localStorage.getItem(COBROS_KEY)) || {};

    // 👉 Helper: calcular número de meses COMPLETOS
    function calcularMeses(desdeStr, hastaStr) {
      if (!desdeStr || !hastaStr) return 0;

      let inicio = new Date(desdeStr + 'T00:00:00');
      const fin  = new Date(hastaStr + 'T00:00:00');

      if (isNaN(inicio) || isNaN(fin) || inicio > fin) return 0;

      let meses = 0;
      while (true) {
        const siguiente = new Date(inicio.getTime());
        siguiente.setMonth(siguiente.getMonth() + 1);

        if (siguiente <= fin) {
          meses++;
          inicio = siguiente;
        } else {
          break;
        }
      }
      return meses;
    }

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
          
          const fechaDesdeInput = divInfo.querySelector('.fecha-desde');
          const fechaHastaInput = divInfo.querySelector('.fecha-hasta');
          const montoInput = divInfo.querySelector('.monto-mensual');
          const diasSpan = divInfo.querySelector('.dias-calculados');
          
          if (historialCobros[conductor]) {
            const ultimoCobro = new Date(historialCobros[conductor]);
            ultimoCobro.setDate(ultimoCobro.getDate() + 1);
            fechaDesdeInput.value = ultimoCobro.toISOString().split('T')[0];
          } else {
            fechaDesdeInput.value = datos.desde || RANGO_DESDE;
          }
          
          fechaHastaInput.value = datos.hasta || RANGO_HASTA;
          montoInput.value = datos.monto;
          
          calcularDiasYMonto(fechaDesdeInput, fechaHastaInput, montoInput, diasSpan, detalle);
          
          const btnMensual = fila.querySelector('.btn-mensual');
          btnMensual.classList.remove('border-gray-300');
          btnMensual.classList.add('border-green-500', 'bg-green-100');
        }
      });
      
      // Cargar anticipos
      cargarAnticipos();
      
      actualizarListaMensuales();
      mostrarAlertasCobro();
      recalcular();
    }

    // Calcular MESES y monto
    function calcularDiasYMonto(fechaDesdeInput, fechaHastaInput, montoInput, diasSpan, detalle) {
      if (!fechaDesdeInput.value || !fechaHastaInput.value || !montoInput.value) return 0;

      const fechaDesde = fechaDesdeInput.value;
      const fechaHasta = fechaHastaInput.value;
      const montoMensual = parseFloat(montoInput.value) || 0;

      const dDesde = new Date(fechaDesde + 'T00:00:00');
      const dHasta = new Date(fechaHasta + 'T00:00:00');
      if (dDesde > dHasta) {
        if (diasSpan) diasSpan.textContent = "⚠️ Fecha inválida";
        if (detalle) {
          detalle.textContent = "Error: Fecha desde > hasta";
          detalle.classList.remove('hidden');
        }
        return 0;
      }

      const meses = calcularMeses(fechaDesde, fechaHasta);
      const montoTotal = Math.round(montoMensual * meses);

      if (diasSpan) {
        diasSpan.textContent = meses === 1 ? "1 mes" : `${meses} meses`;
      }

      if (detalle) {
        const textoPeriodo = meses === 1 ? "1 mes" : `${meses} meses`;
        detalle.textContent = `Periodo: ${textoPeriodo} = $${montoTotal.toLocaleString('es-CO')}`;
        detalle.classList.remove('hidden');
      }

      return montoTotal;
    }

    function calcularMensual(input) {
      const fila = input.closest('tr');
      const fechaDesdeInput = fila.querySelector('.fecha-desde');
      const fechaHastaInput = fila.querySelector('.fecha-hasta');
      const montoInput = fila.querySelector('.monto-mensual');
      const diasSpan = fila.querySelector('.dias-calculados');
      const detalle = fila.querySelector('.mensual-detalle');
      
      const montoProporcional = calcularDiasYMonto(fechaDesdeInput, fechaHastaInput, montoInput, diasSpan, detalle);
      
      const conductor = fila.dataset.conductor;
      if (fechaDesdeInput.value && fechaHastaInput.value && montoInput.value) {
        configMensuales[conductor] = {
          desde: fechaDesdeInput.value,
          hasta: fechaHastaInput.value,
          monto: parseFloat(montoInput.value)
        };
      }
      
      actualizarListaMensuales();
      recalcular();
    }

    function registrarCobro(btn) {
      const fila = btn.closest('tr');
      const conductor = fila.dataset.conductor;
      const fechaHastaInput = fila.querySelector('.fecha-hasta');
      
      if (!fechaHastaInput.value) {
        alert('❌ Debes especificar la fecha HASTA para registrar el cobro');
        return;
      }
      
      historialCobros[conductor] = fechaHastaInput.value;
      localStorage.setItem(COBROS_KEY, JSON.stringify(historialCobros));
      
      btn.innerHTML = '✅ Cobro Registrado';
      btn.classList.remove('bg-green-600');
      btn.classList.add('bg-gray-600');
      
      setTimeout(() => {
        btn.innerHTML = '✅ Registrar Cobro';
        btn.classList.remove('bg-gray-600');
        btn.classList.add('bg-green-600');
      }, 2000);
      
      mostrarAlertasCobro();
    }

    function mostrarAlertasCobro() {
      const alertasDiv = document.getElementById('alertas-cobro');
      const listaAlertas = document.getElementById('lista-alertas');
      listaAlertas.innerHTML = '';
      
      let hayAlertas = false;
      
      Object.entries(configMensuales).forEach(([conductor, datos]) => {
        let mensaje = '';
        let tipo = 'info';
        
        if (historialCobros[conductor]) {
          const ultimoCobro = new Date(historialCobros[conductor] + 'T00:00:00');
          const nuevoDesde = new Date(datos.desde + 'T00:00:00');
          
          if (ultimoCobro >= nuevoDesde) {
            mensaje = `✅ ${conductor}: Ya cobrado hasta ${historialCobros[conductor]}`;
            tipo = 'success';
          } else {
            const diffTime = Math.abs(nuevoDesde - ultimoCobro);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            if (diffDays > 1) {
              mensaje = `⚠️ ${conductor}: ${diffDays} días sin cobrar desde ${historialCobros[conductor]}`;
              tipo = 'warning';
              hayAlertas = true;
            }
          }
        } else {
          mensaje = `ℹ️ ${conductor}: Primer cobro registrado`;
          tipo = 'info';
        }
        
        if (mensaje) {
          const item = document.createElement('div');
          item.className = `text-xs ${tipo === 'warning' ? 'text-yellow-700 font-medium' : tipo === 'success' ? 'text-green-700' : 'text-blue-700'}`;
          item.textContent = mensaje;
          listaAlertas.appendChild(item);
        }
      });
      
      if (hayAlertas) {
        alertasDiv.classList.remove('hidden');
      } else {
        alertasDiv.classList.add('hidden');
      }
    }

    function actualizarListaMensuales() {
      const lista = document.getElementById('lista-mensuales');
      lista.innerHTML = '';
      
      let totalMensual = 0;
      
      Object.entries(configMensuales).forEach(([conductor, datos]) => {
        const fila = document.querySelector(`tr[data-conductor="${conductor}"]`);
        if (fila) {
          const fechaDesdeInput = fila.querySelector('.fecha-desde');
          const fechaHastaInput = fila.querySelector('.fecha-hasta');
          const montoInput = fila.querySelector('.monto-mensual');
          const diasSpan = fila.querySelector('.dias-calculados');
          const detalle = fila.querySelector('.mensual-detalle');
          
          const montoProporcional = calcularDiasYMonto(fechaDesdeInput, fechaHastaInput, montoInput, diasSpan, detalle);
          totalMensual += montoProporcional;
          
          const meses = calcularMeses(datos.desde, datos.hasta);
          const textoMeses = meses === 1 ? '1 mes' : `${meses} meses`;
          
          const yaCobrado = historialCobros[conductor] ? 
            ` (✅ Cobrado hasta: ${historialCobros[conductor]})` : 
            ' (⚠️ Pendiente de cobro)';
          
          const item = document.createElement('div');
          item.className = 'flex justify-between items-center p-2 bg-gray-50 rounded-lg';
          item.innerHTML = `
            <div>
              <div class="font-medium text-sm">${conductor}</div>
              <div class="text-xs text-gray-500">
                ${datos.desde} → ${datos.hasta} (${textoMeses})
                ${yaCobrado}
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

    function formatNumber(num){ 
      return (num||0).toLocaleString('es-CO', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
      }); 
    }

    function recalcular(){
      const tarifas = getTarifas();
      const filas = document.querySelectorAll('#tabla_conductores_body tr');
      let totalViajesBruto = 0;
      let totalMensual = 0;
      let totalAnticiposPagado = 0;
      let totalAnticiposFalta = 0;
      let totalGeneral = 0;
      
      filas.forEach(f=>{
        if (f.style.display === 'none') return; // Saltar filas ocultas por el buscador
        
        const veh = f.dataset.vehiculo;
        const conductor = f.dataset.conductor;
        
        // Calcular total bruto de viajes (100%)
        const c  = parseInt(f.cells[2].innerText)||0;
        const m  = parseInt(f.cells[3].innerText)||0;
        const e  = parseInt(f.cells[4].innerText)||0;
        const s  = parseInt(f.cells[5].innerText)||0;
        const ca = parseInt(f.cells[6].innerText)||0;
        const t  = tarifas[veh] || {completo:0,medio:0,extra:0,carrotanque:0,siapana:0};
        const totalViajesBrutoFila = c*t.completo + m*t.medio + e*t.extra + s*t.siapana + ca*t.carrotanque;
        totalViajesBruto += totalViajesBrutoFila;
        
        // Calcular anticipo
        const selectAnticipo = f.querySelector('.select-anticipo');
        const porcentajeAnticipo = selectAnticipo ? parseInt(selectAnticipo.value) : 100;
        const montoAnticipoPagado = totalViajesBrutoFila * (porcentajeAnticipo / 100);
        const montoAnticipoFalta = totalViajesBrutoFila - montoAnticipoPagado;
        
        // Calcular mensualidad
        let totalMensualFila = 0;
        if (configMensuales[conductor]) {
          const fechaDesdeInput = f.querySelector('.fecha-desde');
          const fechaHastaInput = f.querySelector('.fecha-hasta');
          const montoInput = f.querySelector('.monto-mensual');
          const diasSpan = f.querySelector('.dias-calculados');
          const detalle = f.querySelector('.mensual-detalle');
          
          totalMensualFila = calcularDiasYMonto(fechaDesdeInput, fechaHastaInput, montoInput, diasSpan, detalle) || 0;
        }
        
        // Calcular total real (anticipo + mensualidad)
        const totalRealFila = montoAnticipoPagado + totalMensualFila;
        totalGeneral += totalRealFila;
        
        // Actualizar totales por fila
        const inp = f.querySelector('input.totales');
        if (inp) inp.value = formatNumber(Math.round(totalRealFila));
        
        // Acumular totales globales
        totalMensual += totalMensualFila;
        totalAnticiposPagado += montoAnticipoPagado;
        totalAnticiposFalta += montoAnticipoFalta;
        
        // Actualizar detalles de anticipo
        if (porcentajeAnticipo < 100) {
          const detalleAnticipo = f.querySelector('.anticipo-detalle');
          if (detalleAnticipo) {
            detalleAnticipo.textContent = `Anticipo ${porcentajeAnticipo}% = $${formatNumber(Math.round(montoAnticipoPagado))}`;
            detalleAnticipo.classList.remove('hidden');
          }
        }
      });
      
      // Actualizar panel superior
      document.getElementById('total_viajes').innerText = formatNumber(Math.round(totalViajesBruto));
      document.getElementById('total_mensual').innerText = formatNumber(Math.round(totalMensual));
      document.getElementById('total_general').innerText = formatNumber(Math.round(totalGeneral));
      
      // Actualizar panel lateral
      document.getElementById('resumen_viajes').textContent = `$${formatNumber(Math.round(totalViajesBruto))}`;
      document.getElementById('resumen_total').textContent = `$${formatNumber(Math.round(totalGeneral))}`;
      
      actualizarListaMensuales();
      actualizarResumenAnticipos();
    }

    function guardarMensuales() {
      localStorage.setItem(CONFIG_KEY, JSON.stringify(configMensuales));
      alert('✅ Configuración de conductores mensuales guardada en tu navegador.');
    }

    // 🔹 Guardar CLASIFICACIÓN de una ruta (AJAX)
    function guardarClasificacionRuta(ruta, vehiculo, clasificacion) {
      if (!clasificacion) return; // si la deja "sin clasificar" no guardamos nada nuevo
      fetch('<?= basename(__FILE__) ?>', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({
          guardar_clasificacion:1,
          ruta:ruta,
          tipo_vehiculo:vehiculo,
          clasificacion:clasificacion
        })
      })
      .then(r=>r.text())
      .then(t=>{
        if (t.trim() !== 'ok') {
          console.error('Error guardando clasificación:', t);
        }
      });
    }

    // 🔹 Aplicar clasificación masiva según texto
    function aplicarClasificacionMasiva() {
      const patron = document.getElementById('txt_patron_ruta').value.trim().toLowerCase();
      const clasif = document.getElementById('sel_clasif_masiva').value;

      if (!patron || !clasif) {
        alert('Escribe un texto y elige una clasificación.');
        return;
      }

      const filas = document.querySelectorAll('.fila-ruta');
      let contador = 0;

      filas.forEach(row => {
        const ruta = row.dataset.ruta.toLowerCase();
        const vehiculo = row.dataset.vehiculo;
        if (ruta.includes(patron)) {
          const sel = row.querySelector('.select-clasif-ruta');
          sel.value = clasif;
          guardarClasificacionRuta(row.dataset.ruta, vehiculo, clasif);
          contador++;
        }
      });

      alert('✅ Se aplicó la clasificación a ' + contador + ' rutas. Vuelve a darle "Filtrar" para recalcular la liquidación.');
    }

    // Event Listeners
    document.addEventListener('DOMContentLoaded', function() {
      cargarConfiguracion();
      
      // Cambio en porcentaje de anticipo
      document.querySelectorAll('.select-anticipo').forEach(select => {
        select.addEventListener('change', function() {
          const fila = this.closest('tr');
          actualizarAnticipoPorFila(fila);
          actualizarResumenAnticipos();
          recalcular();
        });
      });
      
      // Click en botón "Marcar como mensual"
      document.querySelectorAll('.btn-mensual').forEach(btn => {
        btn.addEventListener('click', function() {
          const fila = this.closest('tr');
          const conductor = fila.dataset.conductor;
          const btnAgregar = fila.querySelector('.btn-agregar-mensual');
          const divInfo = fila.querySelector('.mensual-info');
          
          if (configMensuales[conductor]) {
            delete configMensuales[conductor];
            btnAgregar.classList.remove('hidden');
            divInfo.classList.add('hidden');
            fila.querySelector('.mensual-detalle').classList.add('hidden');
            this.classList.remove('border-green-500', 'bg-green-100');
            this.classList.add('border-gray-300');
          } else {
            btnAgregar.classList.add('hidden');
            divInfo.classList.remove('hidden');
            this.classList.remove('border-gray-300');
            this.classList.add('border-green-500', 'bg-green-100');
            
            const fechaDesdeInput = fila.querySelector('.fecha-desde');
            const fechaHastaInput = fila.querySelector('.fecha-hasta');
            
            if (!fechaDesdeInput.value) {
              if (historialCobros[conductor]) {
                const ultimoCobro = new Date(historialCobros[conductor]);
                ultimoCobro.setDate(ultimoCobro.getDate() + 1);
                fechaDesdeInput.value = ultimoCobro.toISOString().split('T')[0];
              } else {
                fechaDesdeInput.value = RANGO_DESDE;
              }
            }
            
            if (!fechaHastaInput.value) {
              fechaHastaInput.value = RANGO_HASTA;
            }
          }
          
          actualizarListaMensuales();
          mostrarAlertasCobro();
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
          
          fila.querySelector('.btn-mensual').classList.remove('border-gray-300');
          fila.querySelector('.btn-mensual').classList.add('border-green-500', 'bg-green-100');
          
          const fechaDesdeInput = fila.querySelector('.fecha-desde');
          const fechaHastaInput = fila.querySelector('.fecha-hasta');
          
          if (!fechaDesdeInput.value) {
            if (historialCobros[conductor]) {
              const ultimoCobro = new Date(historialCobros[conductor]);
              ultimoCobro.setDate(ultimoCobro.getDate() + 1);
              fechaDesdeInput.value = ultimoCobro.toISOString().split('T')[0];
            } else {
              fechaDesdeInput.value = RANGO_DESDE;
            }
          }
          
          if (!fechaHastaInput.value) {
            fechaHastaInput.value = RANGO_HASTA;
          }
        });
      });
      
      // Cambios en fecha o monto mensual
      document.querySelectorAll('.fecha-desde, .fecha-hasta, .monto-mensual').forEach(input => {
        input.addEventListener('change', function() {
          calcularMensual(this);
        });
      });

      // Guardar tarifas AJAX (incluye 'siapana')
      document.querySelectorAll('.tarjeta-tarifa input').forEach(input=>{
        input.addEventListener('change', ()=>{
          const card = input.closest('.tarjeta-tarifa');
          const tipoVehiculo = card.dataset.vehiculo;
          const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
          const campo = input.dataset.campo;
          const valor = parseInt(input.value)||0;

          fetch('<?= basename(__FILE__) ?>', {
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

          fetch('<?= basename(__FILE__) ?>?viajes_conductor='+encodeURIComponent(nombre)+'&desde='+desde+'&hasta='+hasta+'&empresa='+encodeURIComponent(empresa))
            .then(r=>r.text())
            .then(html=>{ panel.innerHTML = html; });
        });
      });

      // Cambio
      document.querySelectorAll('.select-clasif-ruta').forEach(sel=>{
        sel.addEventListener('change', ()=>{
          const ruta = sel.dataset.ruta;
          const vehiculo = sel.dataset.vehiculo;
          const clasif = sel.value;
          if (clasif) {
            guardarClasificacionRuta(ruta, vehiculo, clasif);
          }
        });
      });
    });
  </script>

</body>
</html>