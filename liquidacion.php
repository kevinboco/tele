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
   🔹 Endpoint AJAX: viajes por conductor (AHORA incluye pago_parcial)
======================================================= */
if (isset($_GET['viajes_conductor'])) {
    $nombre  = $conn->real_escape_string($_GET['viajes_conductor']);
    $desde   = $_GET['desde'];
    $hasta   = $_GET['hasta'];
    $empresa = $_GET['empresa'] ?? "";

    $sql = "SELECT fecha, ruta, empresa, tipo_vehiculo, COALESCE(pago_parcial,0) AS pago_parcial
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
                      <th class='px-3 py-2 text-center'>Pago parcial</th>
                    </tr>
                  </thead>
                  <tbody class='divide-y divide-gray-100 bg-white'>";
        while ($r = $res->fetch_assoc()) {
            $pp = (int)($r['pago_parcial'] ?? 0);
            echo "<tr class='hover:bg-blue-50 transition-colors'>
                    <td class='px-3 py-2 text-center'>".htmlspecialchars($r['fecha'])."</td>
                    <td class='px-3 py-2 text-center'>".htmlspecialchars($r['ruta'])."</td>
                    <td class='px-3 py-2 text-center'>".htmlspecialchars($r['empresa'])."</td>
                    <td class='px-3 py-2 text-center'>".htmlspecialchars($r['tipo_vehiculo'])."</td>
                    <td class='px-3 py-2 text-center'>".($pp>0 ? ('$'.number_format($pp,0,',','.')) : "<span class='text-slate-400'>—</span>")."</td>
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
   + SUMA pago_parcial POR CONDUCTOR
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

/* --- Traer viajes (incluye pago_parcial) --- */
$sql = "SELECT nombre, ruta, empresa, tipo_vehiculo, COALESCE(pago_parcial,0) AS pago_parcial
        FROM viajes
        WHERE fecha BETWEEN '$desde' AND '$hasta'";
if ($empresaFiltro !== "") {
    $empresaFiltro = $conn->real_escape_string($empresaFiltro);
    $sql .= " AND empresa = '$empresaFiltro'";
}
$res = $conn->query($sql);

$datos = [];
$vehiculos = [];
$rutasUnicas = [];         // para mostrar todas las rutas y clasificarlas
$pagosConductor = [];      // NUEVO: suma pago_parcial por conductor

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $nombre   = $row['nombre'];
        $ruta     = $row['ruta'];
        $vehiculo = $row['tipo_vehiculo'];
        $pagoParcial = (int)($row['pago_parcial'] ?? 0);

        // acumular pago parcial por conductor
        if (!isset($pagosConductor[$nombre])) $pagosConductor[$nombre] = 0;
        $pagosConductor[$nombre] += $pagoParcial;

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
                "siapana"      => 0,
                "pagado"       => 0,   // NUEVO
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

// Inyectar pago acumulado a $datos
foreach ($datos as $conductor => $info) {
    $datos[$conductor]["pagado"] = (int)($pagosConductor[$conductor] ?? 0);
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
  .vehiculo-mensual {
    background-color: #fef3c7 !important;
    border: 1px solid #f59e0b !important;
    color: #92400e !important;
    font-weight: 600;
  }
  
  /* Estilos para el arrastre */
  .draggable-container {
    position: relative;
    cursor: grab;
    transition: all 0.2s ease;
    user-select: none;
    margin-bottom: 1.25rem; /* 20px */
  }
  .draggable-container:last-child {
    margin-bottom: 0;
  }
  .draggable-container:active {
    cursor: grabbing;
  }
  .dragging {
    opacity: 0.6;
    transform: scale(0.98);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  }
  .drop-target {
    border: 2px dashed #3b82f6 !important;
    background-color: rgba(59, 130, 246, 0.05) !important;
  }
  .drag-handle {
    position: absolute;
    top: 10px;
    right: 10px;
    cursor: grab;
    color: #64748b;
    z-index: 10;
    padding: 4px 8px;
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid #e2e8f0;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 4px;
  }
  .drag-handle:active {
    cursor: grabbing;
  }
  .drag-handle:hover {
    color: #3b82f6;
    background: rgba(59, 130, 246, 0.1);
    border-color: #3b82f6;
  }
  
  /* Grid principal personalizable */
  #main-grid {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 1.25rem; /* 20px */
  }
  
  /* Áreas iniciales por defecto */
  .container-tarifas {
    grid-column: span 4;
  }
  .container-resumen {
    grid-column: span 5;
  }
  .container-panel {
    grid-column: span 3;
  }
  .container-filtro {
    grid-column: span 4;
  }
  .container-clasificacion {
    grid-column: span 4;
  }
  
  /* Responsive */
  @media (max-width: 1280px) {
    #main-grid {
      grid-template-columns: 1fr;
    }
    .container-tarifas,
    .container-resumen,
    .container-panel,
    .container-filtro,
    .container-clasificacion {
      grid-column: 1 / -1;
    }
  }
  
  /* Indicador visual para contenedores que se pueden reordenar */
  .reorder-hint {
    position: absolute;
    top: 10px;
    left: 10px;
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 4px;
    border: 1px solid rgba(59, 130, 246, 0.2);
  }
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
        <button onclick="resetLayout()" class="text-sm px-3 py-1 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg transition">
          🔄 Restablecer diseño
        </button>
      </div>
    </div>
  </header>

  <!-- Contenido principal - GRID PERSONALIZABLE -->
  <main class="max-w-[1600px] mx-auto px-3 md:px-4 py-6">
    <div id="main-grid">
      
      <!-- 🔹 TARIFAS POR TIPO DE VEHÍCULO (contenedor independiente) -->
      <section class="draggable-container container-tarifas" draggable="true" id="container-tarifas">
        <div class="reorder-hint">Arrastrable</div>
        <div class="drag-handle" title="Arrastrar para reordenar">↕️ Mover</div>
        
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 h-full">
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
      </section>

      <!-- 🔹 FILTRO DE LIQUIDACIÓN (contenedor independiente) -->
      <section class="draggable-container container-filtro" draggable="true" id="container-filtro">
        <div class="reorder-hint">Arrastrable</div>
        <div class="drag-handle" title="Arrastrar para reordenar">↕️ Mover</div>
        
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 h-full">
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

      <!-- 🔹 CLASIFICACIÓN DE RUTAS (contenedor independiente) -->
      <section class="draggable-container container-clasificacion" draggable="true" id="container-clasificacion">
        <div class="reorder-hint">Arrastrable</div>
        <div class="drag-handle" title="Arrastrar para reordenar">↕️ Mover</div>
        
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 h-full">
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

      <!-- 🔹 RESUMEN POR CONDUCTOR (contenedor independiente) -->
      <section class="draggable-container container-resumen" draggable="true" id="container-resumen">
        <div class="reorder-hint">Arrastrable</div>
        <div class="drag-handle" title="Arrastrar para reordenar">↕️ Mover</div>
        
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 h-full">
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

              <div id="total_chip_container" class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-blue-700 font-semibold text-sm">
                  🔢 Viajes: <span id="total_viajes">0</span>
                </span>
                <span class="inline-flex items-center gap-2 rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-purple-700 font-semibold text-sm">
                  💰 Total: <span id="total_general">0</span>
                </span>
                <span class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-emerald-700 font-semibold text-sm">
                  ✅ Pagado: <span id="total_pagado">0</span>
                </span>
                <span class="inline-flex items-center gap-2 rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-rose-700 font-semibold text-sm">
                  ⏳ Faltante: <span id="total_faltante">0</span>
                </span>
              </div>
            </div>
          </div>

          <div class="mt-4 w-full rounded-xl border border-slate-200 overflow-x-auto">
            <table id="tabla_conductores" class="w-full text-sm table-fixed min-w-[900px]">
              <colgroup>
                <col style="width:25%">
                <col style="width:12%">
                <col style="width:7%">
                <col style="width:7%">
                <col style="width:7%">
                <col style="width:7%">  <!-- Siapana -->
                <col style="width:8%">  <!-- Carrotanque -->
                <col style="width:15%"> <!-- Total -->
                <col style="width:12%"> <!-- Pagado -->
                <col style="width:10%"> <!-- Faltante -->
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
                  <th class="px-3 py-2 text-center">Total</th>
                  <th class="px-3 py-2 text-center">Pagado</th>
                  <th class="px-3 py-2 text-center">Faltante</th>
                </tr>
              </thead>
              <tbody id="tabla_conductores_body" class="divide-y divide-slate-100 bg-white">
              <?php foreach ($datos as $conductor => $viajes): 
                // Detectar si el vehículo es "Mensual" (case insensitive)
                $esMensual = (stripos($viajes['vehiculo'], 'mensual') !== false);
                $claseVehiculo = $esMensual ? 'vehiculo-mensual' : '';
              ?>
                <tr data-vehiculo="<?= htmlspecialchars($viajes['vehiculo']) ?>" 
                    data-conductor="<?= htmlspecialchars($conductor) ?>" 
                    data-conductor-normalizado="<?= htmlspecialchars(mb_strtolower($conductor)) ?>"
                    data-pagado="<?= (int)($viajes['pagado'] ?? 0) ?>"
                    class="hover:bg-blue-50/40 transition-colors">
                  <td class="px-3 py-2">
                    <button type="button"
                            class="conductor-link text-blue-700 hover:text-blue-900 underline underline-offset-2 transition"
                            title="Ver viajes">
                      <?= htmlspecialchars($conductor) ?>
                    </button>
                  </td>
                  <td class="px-3 py-2 text-center">
                    <span class="inline-block <?= $claseVehiculo ?> px-2 py-1 rounded-lg text-xs font-medium">
                      <?= htmlspecialchars($viajes['vehiculo']) ?>
                      <?php if ($esMensual): ?>
                        <span class="ml-1">📅</span>
                      <?php endif; ?>
                    </span>
                  </td>
                  <td class="px-3 py-2 text-center"><?= (int)$viajes["completos"] ?></td>
                  <td class="px-3 py-2 text-center"><?= (int)$viajes["medios"] ?></td>
                  <td class="px-3 py-2 text-center"><?= (int)$viajes["extras"] ?></td>
                  <td class="px-3 py-2 text-center"><?= (int)$viajes["siapana"] ?></td>
                  <td class="px-3 py-2 text-center"><?= (int)$viajes["carrotanques"] ?></td>

                  <!-- Total -->
                  <td class="px-3 py-2">
                    <input type="text"
                           class="totales w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-slate-50 outline-none whitespace-nowrap tabular-nums"
                           readonly dir="ltr">
                  </td>

                  <!-- Pagado -->
                  <td class="px-3 py-2">
                    <input type="text"
                           class="pagado w-full rounded-xl border border-emerald-200 px-3 py-2 text-right bg-emerald-50 outline-none whitespace-nowrap tabular-nums"
                           readonly dir="ltr"
                           value="<?= number_format((int)($viajes['pagado'] ?? 0), 0, ',', '.') ?>">
                  </td>

                  <!-- Faltante -->
                  <td class="px-3 py-2">
                    <input type="text"
                           class="faltante w-full rounded-xl border border-rose-200 px-3 py-2 text-right bg-rose-50 outline-none whitespace-nowrap tabular-nums"
                           readonly dir="ltr"
                           value="0">
                  </td>

                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- 🔹 PANEL DE VIAJES (contenedor independiente) -->
      <aside class="draggable-container container-panel" draggable="true" id="container-panel">
        <div class="reorder-hint">Arrastrable</div>
        <div class="drag-handle" title="Arrastrar para reordenar">↕️ Mover</div>
        
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 h-full">
          <h4 class="text-base font-semibold mb-3">🧳 Viajes del Conductor</h4>
          <div id="contenidoPanel"
               class="min-h-[220px] max-h-[400px] overflow-y-auto rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-600 flex items-center justify-center">
            <p class="m-0 text-center">Selecciona un conductor para ver sus viajes aquí.</p>
          </div>
        </div>
      </aside>

    </div>
  </main>

  <script>
    // ===== FUNCIONALIDAD DE ARRASTRAR Y SOLTAR MEJORADA =====
    let draggedElement = null;
    let dragHandle = null;
    let dragOffsetX = 0;
    let dragOffsetY = 0;
    let isDragging = false;
    let placeholder = null;
    let allContainers = [];
    let gridAreas = {};

    // Configuración inicial del layout
    const defaultLayout = {
      'container-tarifas': { col: 'span 4', row: 'auto', order: 0 },
      'container-filtro': { col: 'span 4', row: 'auto', order: 1 },
      'container-clasificacion': { col: 'span 4', row: 'auto', order: 2 },
      'container-resumen': { col: 'span 5', row: 'auto', order: 3 },
      'container-panel': { col: 'span 3', row: 'auto', order: 4 }
    };

    // Inicializar eventos de arrastre
    function initDragAndDrop() {
      allContainers = Array.from(document.querySelectorAll('.draggable-container'));
      
      // Cargar layout guardado o usar el default
      loadLayout();
      
      allContainers.forEach(container => {
        // Eliminar eventos anteriores para evitar duplicados
        container.removeEventListener('dragstart', handleDragStart);
        container.removeEventListener('dragend', handleDragEnd);
        container.removeEventListener('dragover', handleDragOver);
        container.removeEventListener('dragenter', handleDragEnter);
        container.removeEventListener('dragleave', handleDragLeave);
        container.removeEventListener('drop', handleDrop);
        
        // Añadir nuevos eventos
        container.addEventListener('dragstart', handleDragStart);
        container.addEventListener('dragend', handleDragEnd);
        container.addEventListener('dragover', handleDragOver);
        container.addEventListener('dragenter', handleDragEnter);
        container.addEventListener('dragleave', handleDragLeave);
        container.addEventListener('drop', handleDrop);
        
        // Configurar manejador del asa de arrastre
        const handle = container.querySelector('.drag-handle');
        if (handle) {
          handle.removeEventListener('mousedown', startDragFromHandle);
          handle.removeEventListener('touchstart', startDragFromHandle);
          
          handle.addEventListener('mousedown', startDragFromHandle);
          handle.addEventListener('touchstart', startDragFromHandle);
        }
      });
    }

    function handleDragStart(e) {
      // Solo permitir arrastre desde el asa
      const isFromHandle = e.target.classList.contains('drag-handle') || 
                          e.target.closest('.drag-handle');
      
      if (!isFromHandle && !e.target.classList.contains('draggable-container')) {
        e.preventDefault();
        return;
      }
      
      draggedElement = this;
      dragHandle = isFromHandle ? (e.target.classList.contains('drag-handle') ? e.target : e.target.closest('.drag-handle')) : null;
      
      // Añadir clase de arrastre
      this.classList.add('dragging');
      
      // Crear placeholder para mantener el espacio
      placeholder = document.createElement('div');
      placeholder.className = 'placeholder';
      placeholder.style.height = this.offsetHeight + 'px';
      placeholder.style.marginBottom = '1.25rem';
      placeholder.style.border = '2px dashed #cbd5e1';
      placeholder.style.borderRadius = '1rem';
      placeholder.style.backgroundColor = 'rgba(203, 213, 225, 0.1)';
      
      // Insertar placeholder
      this.parentNode.insertBefore(placeholder, this.nextSibling);
      
      // Establecer datos para el arrastre
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', this.id);
      
      // Para mejor compatibilidad
      setTimeout(() => {
        this.style.display = 'none';
      }, 0);
    }

    function handleDragEnd(e) {
      // Remover clases y restablecer
      if (draggedElement) {
        draggedElement.classList.remove('dragging');
        draggedElement.style.display = '';
      }
      
      // Remover placeholder
      if (placeholder && placeholder.parentNode) {
        placeholder.parentNode.removeChild(placeholder);
      }
      
      // Remover clases de drop target
      allContainers.forEach(container => {
        container.classList.remove('drop-target');
      });
      
      draggedElement = null;
      dragHandle = null;
      placeholder = null;
      
      // Guardar el nuevo layout
      saveLayout();
    }

    function handleDragOver(e) {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
    }

    function handleDragEnter(e) {
      e.preventDefault();
      if (this !== draggedElement && this !== placeholder) {
        this.classList.add('drop-target');
        
        // Calcular posición para insertar
        const rect = this.getBoundingClientRect();
        const mouseY = e.clientY;
        const shouldInsertBefore = mouseY < rect.top + rect.height / 2;
        
        if (shouldInsertBefore) {
          this.parentNode.insertBefore(placeholder, this);
        } else {
          this.parentNode.insertBefore(placeholder, this.nextSibling);
        }
      }
    }

    function handleDragLeave(e) {
      // Solo remover la clase si no estamos sobre el elemento o el placeholder
      if (!this.contains(e.relatedTarget) && e.relatedTarget !== placeholder) {
        this.classList.remove('drop-target');
      }
    }

    function handleDrop(e) {
      e.preventDefault();
      e.stopPropagation();
      
      if (draggedElement && draggedElement !== this) {
        // Insertar el elemento arrastrado donde está el placeholder
        if (placeholder && placeholder.parentNode) {
          if (placeholder.nextSibling === this) {
            this.parentNode.insertBefore(draggedElement, this);
          } else {
            placeholder.parentNode.insertBefore(draggedElement, placeholder);
          }
        }
        
        // Actualizar el orden visual
        updateContainerOrder();
      }
      
      this.classList.remove('drop-target');
    }

    function startDragFromHandle(e) {
      // Para dispositivos táctiles, necesitamos prevenir el comportamiento por defecto
      if (e.type === 'touchstart') {
        e.preventDefault();
      }
      
      const container = this.closest('.draggable-container');
      if (container) {
        // Simular evento dragstart
        const dragStartEvent = new DragEvent('dragstart', {
          bubbles: true,
          cancelable: true,
          dataTransfer: new DataTransfer()
        });
        container.dispatchEvent(dragStartEvent);
      }
    }

    function updateContainerOrder() {
      // Actualizar el orden visual basado en la posición en el DOM
      const mainGrid = document.getElementById('main-grid');
      const currentContainers = Array.from(mainGrid.querySelectorAll('.draggable-container'));
      
      // Guardar el nuevo orden
      saveLayout();
    }

    function saveLayout() {
      const mainGrid = document.getElementById('main-grid');
      const containers = Array.from(mainGrid.querySelectorAll('.draggable-container'));
      
      const layout = {};
      containers.forEach((container, index) => {
        const id = container.id;
        // Obtener las clases de grid actuales
        const classList = Array.from(container.classList);
        const gridClass = classList.find(cls => cls.startsWith('container-'));
        
        layout[id] = {
          gridClass: gridClass || container.className.match(/container-\w+/)?.[0],
          order: index,
          col: container.style.gridColumn || getComputedStyle(container).gridColumn
        };
      });
      
      localStorage.setItem('liquidacionAdvancedLayout', JSON.stringify(layout));
    }

    function loadLayout() {
      const savedLayout = localStorage.getItem('liquidacionAdvancedLayout');
      const mainGrid = document.getElementById('main-grid');
      
      if (savedLayout) {
        try {
          const layout = JSON.parse(savedLayout);
          const containerIds = Object.keys(layout);
          
          // Ordenar contenedores según el layout guardado
          containerIds.sort((a, b) => layout[a].order - layout[b].order);
          
          // Reinsertar en el orden correcto
          containerIds.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
              // Aplicar clases guardadas
              if (layout[id].gridClass) {
                element.className = element.className.replace(/container-\w+/, layout[id].gridClass);
              }
              // Aplicar estilo de grid
              if (layout[id].col) {
                element.style.gridColumn = layout[id].col;
              }
              
              mainGrid.appendChild(element);
            }
          });
        } catch (e) {
          console.error('Error al cargar el layout:', e);
          setDefaultLayout();
        }
      } else {
        setDefaultLayout();
      }
    }

    function setDefaultLayout() {
      const mainGrid = document.getElementById('main-grid');
      
      // Orden por defecto
      const defaultOrder = [
        'container-tarifas',
        'container-filtro',
        'container-clasificacion',
        'container-resumen',
        'container-panel'
      ];
      
      defaultOrder.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
          // Aplicar clases por defecto
          element.className = element.className.replace(/container-\w+/, id);
          element.style.gridColumn = '';
          
          mainGrid.appendChild(element);
        }
      });
    }

    function resetLayout() {
      if (confirm('¿Restablecer el diseño a la configuración por defecto?')) {
        localStorage.removeItem('liquidacionAdvancedLayout');
        setDefaultLayout();
        initDragAndDrop();
        alert('Diseño restablecido correctamente.');
      }
    }

    // ===== BUSCADOR DE CONDUCTORES =====
    const buscadorConductores = document.getElementById('buscadorConductores');
    const clearBuscar = document.getElementById('clearBuscar');
    const contadorConductores = document.getElementById('contador-conductores');
    const tablaConductoresBody = document.getElementById('tabla_conductores_body');

    function normalizarTexto(texto) {
      return texto
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
    }

    function filtrarConductores() {
      const textoBusqueda = normalizarTexto(buscadorConductores.value);
      const filas = tablaConductoresBody.querySelectorAll('tr');
      let filasVisibles = 0;
      
      if (textoBusqueda === '') {
        filas.forEach(fila => { fila.style.display = ''; filasVisibles++; });
        clearBuscar.style.display = 'none';
      } else {
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
      
      const totalConductores = filas.length;
      contadorConductores.textContent = `Mostrando ${filasVisibles} de ${totalConductores} conductores`;
      recalcular(); // importantísimo: recalcular totales solo visibles
    }

    buscadorConductores.addEventListener('input', filtrarConductores);
    clearBuscar.addEventListener('click', () => {
      buscadorConductores.value = '';
      filtrarConductores();
      buscadorConductores.focus();
    });
    buscadorConductores.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        buscadorConductores.value = '';
        filtrarConductores();
      }
    });

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
      const filas = document.querySelectorAll('#tabla_conductores_body tr');

      let totalViajes = 0;
      let totalPagado = 0;
      let totalFaltante = 0;

      filas.forEach(f=>{
        if (f.style.display === 'none') return;

        const veh = f.dataset.vehiculo;
        const conductor = f.dataset.conductor;

        const c  = parseInt(f.cells[2].innerText)||0;
        const m  = parseInt(f.cells[3].innerText)||0;
        const e  = parseInt(f.cells[4].innerText)||0;
        const s  = parseInt(f.cells[5].innerText)||0;
        const ca = parseInt(f.cells[6].innerText)||0;

        const t  = tarifas[veh] || {completo:0,medio:0,extra:0,carrotanque:0,siapana:0};
        const totalViajesFila = c*t.completo + m*t.medio + e*t.extra + s*t.siapana + ca*t.carrotanque;

        const totalFila = totalViajesFila;

        const pagado = parseInt(f.dataset.pagado || '0') || 0;
        let faltante = totalFila - pagado;
        if (faltante < 0) faltante = 0; // no mostrar negativo

        const inpTotal = f.querySelector('input.totales');
        if (inpTotal) inpTotal.value = formatNumber(totalFila);

        const inpFalt = f.querySelector('input.faltante');
        if (inpFalt) inpFalt.value = formatNumber(faltante);

        totalViajes += totalViajesFila;
        totalPagado += pagado;
        totalFaltante += faltante;
      });

      document.getElementById('total_viajes').innerText = formatNumber(totalViajes);
      document.getElementById('total_general').innerText = formatNumber(totalViajes);

      document.getElementById('total_pagado').innerText = formatNumber(totalPagado);
      document.getElementById('total_faltante').innerText = formatNumber(totalFaltante);
    }

    function guardarClasificacionRuta(ruta, vehiculo, clasificacion) {
      if (!clasificacion) return;
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
        if (t.trim() !== 'ok') console.error('Error guardando clasificación:', t);
      });
    }

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

    document.addEventListener('DOMContentLoaded', function() {
      // Inicializar arrastre y soltar
      initDragAndDrop();

      // Guardar tarifas AJAX
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

      // Cambio clasificación ruta
      document.querySelectorAll('.select-clasif-ruta').forEach(sel=>{
        sel.addEventListener('change', ()=>{
          const ruta = sel.dataset.ruta;
          const vehiculo = sel.dataset.vehiculo;
          const clasif = sel.value;
          if (clasif) guardarClasificacionRuta(ruta, vehiculo, clasif);
        });
      });

      recalcular();
    });

    // También guardar el layout cuando se cierre la página
    window.addEventListener('beforeunload', saveLayout);
  </script>

</body>
</html>