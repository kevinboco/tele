[file name]: liquidacion.php
[file content begin]
<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

/* =======================================================
   🔹 OBTENER COLUMNAS DINÁMICAS DE LA TABLA TARIFAS
======================================================= */
function obtenerColumnasTarifas($conn) {
    $columnas = [];
    $res = $conn->query("SHOW COLUMNS FROM tarifas");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $field = $row['Field'];
            // Excluir columnas que no son tarifas
            if (!in_array($field, ['id', 'empresa', 'tipo_vehiculo'])) {
                $columnas[] = $field;
            }
        }
    }
    return $columnas;
}

$columnasTarifas = obtenerColumnasTarifas($conn);
$columnasPermitidas = array_merge(['completo','medio','extra','carrotanque','siapana'], $columnasTarifas);
$columnasPermitidas = array_unique($columnasPermitidas);

/* =======================================================
   🔹 Guardar tarifas por vehículo y empresa (AJAX)
======================================================= */
if (isset($_POST['guardar_tarifa'])) {
    $empresa  = $conn->real_escape_string($_POST['empresa']);
    $vehiculo = $conn->real_escape_string($_POST['tipo_vehiculo']);
    $campo    = $conn->real_escape_string($_POST['campo']);
    $valor    = (int)$_POST['valor'];

    // Validar que el campo exista en la tabla
    $columnasReales = obtenerColumnasTarifas($conn);
    $allow = array_merge(['completo','medio','extra','carrotanque','siapana'], $columnasReales);
    $allow = array_unique($allow);
    
    if (!in_array($campo, $allow, true)) { 
        echo "error: campo no válido"; 
        exit; 
    }

    // Insertar o actualizar
    $conn->query("INSERT INTO tarifas (empresa, tipo_vehiculo) VALUES ('$empresa', '$vehiculo') ON DUPLICATE KEY UPDATE empresa=empresa");
    $sql = "UPDATE tarifas SET $campo = $valor WHERE empresa='$empresa' AND tipo_vehiculo='$vehiculo'";
    echo $conn->query($sql) ? "ok" : "error";
    exit;
}

/* =======================================================
   🔹 Guardar CLASIFICACIÓN de rutas (manual) - AJAX
======================================================= */
if (isset($_POST['guardar_clasificacion'])) {
    $ruta       = $conn->real_escape_string($_POST['ruta']);
    $vehiculo   = $conn->real_escape_string($_POST['tipo_vehiculo']);
    $clasif     = $conn->real_escape_string($_POST['clasificacion']);

    $allowClasif = $columnasPermitidas;
    if (!in_array($clasif, $allowClasif, true)) {
        echo "error: clasificación no válida";
        exit;
    }

    $sql = "INSERT INTO ruta_clasificacion (ruta, tipo_vehiculo, clasificacion)
            VALUES ('$ruta', '$vehiculo', '$clasif')
            ON DUPLICATE KEY UPDATE clasificacion = '$clasif'";
    echo $conn->query($sql) ? "ok" : "error";
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

    // Cargar clasificaciones de rutas
    $clasif_rutas = [];
    $resClasif = $conn->query("SELECT ruta, tipo_vehiculo, clasificacion FROM ruta_clasificacion");
    if ($resClasif) {
        while ($r = $resClasif->fetch_assoc()) {
            $key = mb_strtolower(trim($r['ruta'] . '|' . $r['tipo_vehiculo']), 'UTF-8');
            $clasif_rutas[$key] = $r['clasificacion'];
        }
    }

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

    // Definir colores
    $coloresBase = [
        'completo'     => ['bg-emerald-100', 'text-emerald-700', 'border-emerald-200', 'bg-emerald-50/40'],
        'medio'        => ['bg-amber-100', 'text-amber-800', 'border-amber-200', 'bg-amber-50/40'],
        'extra'        => ['bg-slate-200', 'text-slate-800', 'border-slate-300', 'bg-slate-50'],
        'siapana'      => ['bg-fuchsia-100', 'text-fuchsia-700', 'border-fuchsia-200', 'bg-fuchsia-50/40'],
        'carrotanque'  => ['bg-cyan-100', 'text-cyan-800', 'border-cyan-200', 'bg-cyan-50/40'],
        'riohacha'     => ['bg-indigo-100', 'text-indigo-700', 'border-indigo-200', 'bg-indigo-50/40'],
        'pru'          => ['bg-violet-100', 'text-violet-700', 'border-violet-200', 'bg-violet-50/40']
    ];
    
    $legend = [];
    foreach ($columnasPermitidas as $col) {
        if (isset($coloresBase[$col])) {
            $colors = $coloresBase[$col];
            $legend[$col] = [
                'label' => ucfirst($col),
                'badge' => $colors[0] . ' ' . $colors[1] . ' border ' . $colors[2],
                'row' => $colors[3]
            ];
        } else {
            $legend[$col] = [
                'label' => ucfirst($col),
                'badge' => 'bg-gray-100 text-gray-700 border border-gray-200',
                'row' => 'bg-gray-50/40'
            ];
        }
    }
    $legend['otro'] = ['label'=>'Sin clasificar','badge'=>'bg-gray-100 text-gray-700 border border-gray-200','row'=>'bg-gray-50/20'];

    if ($res && $res->num_rows > 0) {
        $counts = array_fill_keys($columnasPermitidas, 0);
        $counts['otro'] = 0;

        $rowsHTML = "";
        
        while ($r = $res->fetch_assoc()) {
            $ruta = (string)$r['ruta'];
            $vehiculo = $r['tipo_vehiculo'];
            
            $key = mb_strtolower(trim($ruta . '|' . $vehiculo), 'UTF-8');
            $cat = $clasif_rutas[$key] ?? 'otro';
            
            if (!in_array($cat, $columnasPermitidas)) {
                $cat = 'otro';
            }

            $counts[$cat] = ($counts[$cat] ?? 0) + 1;

            $l = $legend[$cat] ?? $legend['otro'];
            $badge = "<span class='inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold {$l['badge']}'>".$l['label']."</span>";
            $rowCls = trim("row-viaje hover:bg-blue-50 transition-colors {$l['row']} cat-$cat");

            $pp = (int)($r['pago_parcial'] ?? 0);
            $pagoParcialHTML = $pp > 0 ? '$'.number_format($pp,0,',','.') : "<span class='text-slate-400'>—</span>";

            $rowsHTML .= "<tr class='{$rowCls}'>
                    <td class='px-3 py-2 text-center'>".htmlspecialchars($r['fecha'])."</td>
                    <td class='px-3 py-2'>
                      <div class='flex items-center justify-center gap-2'>
                        {$badge}
                        <span>".htmlspecialchars($ruta)."</span>
                      </div>
                    </td>
                    <td class='px-3 py-2 text-center'>".htmlspecialchars($r['empresa'])."</td>
                    <td class='px-3 py-2 text-center'>".htmlspecialchars($vehiculo)."</td>
                    <td class='px-3 py-2 text-center'>{$pagoParcialHTML}</td>
                  </tr>";
        }

        // Generar HTML
        echo "<div class='space-y-3'>";
        
        // Leyenda
        echo "<div class='flex flex-wrap gap-2 text-xs' id='legendFilterBar'>";
        foreach (array_merge($columnasPermitidas, ['otro']) as $k) {
            $l = $legend[$k] ?? $legend['otro'];
            $countVal = $counts[$k] ?? 0;
            echo "<button
                    class='legend-pill inline-flex items-center gap-2 px-3 py-2 rounded-full {$l['badge']} hover:opacity-90 transition ring-0 outline-none border cursor-pointer select-none'
                    data-tipo='{$k}'
                  >
                    <span class='w-2.5 h-2.5 rounded-full {$l['row']} bg-opacity-100 border border-white/30 shadow-inner'></span>
                    <span class='font-semibold text-[13px]'>{$l['label']}</span>
                    <span class='text-[11px] font-semibold opacity-80'>({$countVal})</span>
                  </button>";
        }
        echo "</div>";

        // Tabla
        echo "<div class='overflow-x-auto max-h-[350px]'>
                <table class='min-w-full text-sm text-left'>
                  <thead class='bg-blue-600 text-white sticky top-0 z-10'>
                    <tr>
                      <th class='px-3 py-2 text-center'>Fecha</th>
                      <th class='px-3 py-2 text-center'>Ruta</th>
                      <th class='px-3 py-2 text-center'>Empresa</th>
                      <th class='px-3 py-2 text-center'>Vehículo</th>
                      <th class='px-3 py-2 text-center'>Pago parcial</th>
                    </tr>
                  </thead>
                  <tbody id='viajesTableBody' class='divide-y divide-gray-100'>
                    {$rowsHTML}
                  </tbody>
                </table>
              </div>";
        
        echo "</div>";
        
        // Script para filtros
        echo "<script>
                function attachFiltroViajes(){
                    const pills = document.querySelectorAll('#legendFilterBar .legend-pill');
                    const rows  = document.querySelectorAll('#viajesTableBody .row-viaje');
                    if (!pills.length || !rows.length) return;

                    let activeCat = null;

                    function applyFilter(cat){
                        if (cat === activeCat) {
                            activeCat = null;
                        } else {
                            activeCat = cat;
                        }

                        pills.forEach(p => {
                            const pcat = p.getAttribute('data-tipo');
                            if (activeCat && pcat === activeCat) {
                                p.classList.add('ring-2','ring-blue-500','ring-offset-1','ring-offset-white');
                            } else {
                                p.classList.remove('ring-2','ring-blue-500','ring-offset-1','ring-offset-white');
                            }
                        });

                        rows.forEach(r => {
                            if (!activeCat) {
                                r.style.display = '';
                            } else {
                                if (r.classList.contains('cat-' + activeCat)) {
                                    r.style.display = '';
                                } else {
                                    r.style.display = 'none';
                                }
                            }
                        });
                    }

                    pills.forEach(p => {
                        p.addEventListener('click', ()=>{
                            const cat = p.getAttribute('data-tipo');
                            applyFilter(cat);
                        });
                    });
                }
                
                setTimeout(attachFiltroViajes, 100);
              </script>";

    } else {
        echo "<p class='text-center text-gray-500 py-4'>No se encontraron viajes.</p>";
    }
    exit;
}

/* =======================================================
   🔹 Formulario inicial
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

/* --- Cargar clasificaciones de rutas --- */
$clasif_rutas = [];
$resClasif = $conn->query("SELECT ruta, tipo_vehiculo, clasificacion FROM ruta_clasificacion");
if ($resClasif) {
    while ($r = $resClasif->fetch_assoc()) {
        $key = mb_strtolower(trim($r['ruta'] . '|' . $r['tipo_vehiculo']), 'UTF-8');
        $clasif_rutas[$key] = $r['clasificacion'];
    }
}

/* --- Traer viajes --- */
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
$rutasUnicas = [];
$pagosConductor = [];

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $nombre   = $row['nombre'];
        $ruta     = $row['ruta'];
        $vehiculo = $row['tipo_vehiculo'];
        $pagoParcial = (int)($row['pago_parcial'] ?? 0);

        // acumular pago parcial
        if (!isset($pagosConductor[$nombre])) $pagosConductor[$nombre] = 0;
        $pagosConductor[$nombre] += $pagoParcial;

        // clave normalizada
        $keyRuta = mb_strtolower(trim($ruta . '|' . $vehiculo), 'UTF-8');

        // Guardar rutas únicas
        if (!isset($rutasUnicas[$keyRuta])) {
            $rutasUnicas[$keyRuta] = [
                'ruta'          => $ruta,
                'vehiculo'      => $vehiculo,
                'clasificacion' => $clasif_rutas[$keyRuta] ?? ''
            ];
        }

        // Lista de vehículos
        if (!in_array($vehiculo, $vehiculos, true)) {
            $vehiculos[] = $vehiculo;
        }

        // Inicializar datos del conductor
        if (!isset($datos[$nombre])) {
            $datos[$nombre] = array_fill_keys($columnasPermitidas, 0);
            $datos[$nombre]['vehiculo'] = $vehiculo;
            $datos[$nombre]['pagado'] = 0;
        }

        // 🔹 Clasificación MANUAL de la ruta
        $clasifRuta = $clasif_rutas[$keyRuta] ?? '';

        if ($clasifRuta === '') {
            continue;
        }

        if (in_array($clasifRuta, $columnasPermitidas)) {
            $datos[$nombre][$clasifRuta]++;
        }
    }
}

// Inyectar pago acumulado
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
</style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800">

  <!-- Encabezado -->
  <header class="max-w-[1600px] mx-auto px-3 md:px-4 pt-6">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-5 py-4">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <h2 class="text-xl md:text-2xl font-bold">🪙 Liquidación de Conductores</h2>
        <div class="text-sm text-slate-600">
          Periodo: <strong><?= htmlspecialchars($desde) ?></strong> → <strong><?= htmlspecialchars($hasta) ?></strong>
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

      <!-- Columna 1: Tarifas + Filtro + Clasificación -->
      <section class="space-y-5">

        <!-- Tarjetas de tarifas -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
          <h3 class="text-lg font-semibold mb-4">🚐 Tarifas por Tipo de Vehículo</h3>
          <div id="tarifas_grid" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php foreach ($vehiculos as $veh):
              $t = $tarifas_guardadas[$veh] ?? array_fill_keys($columnasTarifas, 0);
            ?>
            <div class="tarjeta-tarifa rounded-2xl border border-slate-200 p-4 shadow-sm bg-slate-50"
                 data-vehiculo="<?= htmlspecialchars($veh) ?>">
              <div class="flex items-center justify-between mb-3">
                <div class="text-base font-semibold"><?= htmlspecialchars($veh) ?></div>
                <span class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-700 border border-blue-200">Config</span>
              </div>
              <?php foreach ($columnasTarifas as $columna): ?>
                <label class="block mb-3">
                  <span class="block text-sm font-medium mb-1">
                    <?= ucfirst(str_replace('_', ' ', $columna)) ?>
                  </span>
                  <input type="number" step="1000" value="<?= (int)($t[$columna] ?? 0) ?>"
                         data-campo="<?= htmlspecialchars($columna) ?>"
                         class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-white outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition tarifa-input">
                </label>
              <?php endforeach; ?>
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
          <h5 class="text-base font-semibold mb-3">🧭 Clasificación de Rutas</h5>
          <p class="text-xs text-slate-500 mb-3">
            Ajusta qué tipo es cada ruta. Se guarda automáticamente.
          </p>

          <div class="flex flex-col gap-2 mb-3 md:flex-row md:items-end">
            <div class="flex-1">
              <label class="block text-xs font-medium mb-1">Texto que debe contener la ruta</label>
              <input id="txt_patron_ruta" type="text"
                     class="w-full rounded-xl border border-slate-300 px-3 py-1.5 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500"
                     placeholder="Ej: Riohacha, Nazareth, Siapana...">
            </div>
            <div>
              <label class="block text-xs font-medium mb-1">Clasificación</label>
              <select id="sel_clasif_masiva"
                      class="rounded-xl border border-slate-300 px-3 py-1.5 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500">
                <option value="">-- Selecciona --</option>
                <?php foreach ($columnasPermitidas as $col): ?>
                  <option value="<?= htmlspecialchars($col) ?>">
                    <?= ucfirst(str_replace('_', ' ', $col)) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="button"
                    onclick="aplicarClasificacionMasiva()"
                    class="mt-2 md:mt-0 inline-flex items-center justify-center rounded-xl bg-purple-600 text-white px-4 py-2 text-sm font-semibold hover:bg-purple-700 active:bg-purple-800 focus:ring-4 focus:ring-purple-200">
              ⚙️ Aplicar
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
                      <?php foreach ($columnasPermitidas as $col): ?>
                        <option value="<?= htmlspecialchars($col) ?>" 
                          <?= $info['clasificacion']===$col ? 'selected' : '' ?>>
                          <?= ucfirst(str_replace('_', ' ', $col)) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- Columna 2: Resumen por conductor -->
      <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
        
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
          <div>
            <h3 class="text-lg font-semibold">🧑‍✈️ Resumen por Conductor</h3>
            <div id="contador-conductores" class="text-xs text-slate-500 mt-1">
              Mostrando <?= count($datos) ?> conductores
            </div>
          </div>
          <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
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
          <?php
          $numColumnas = count($columnasPermitidas);
          $anchoColumna = floor(100 / ($numColumnas + 5));
          ?>
          <table id="tabla_conductores" class="w-full text-sm table-fixed min-w-[900px]">
            <colgroup>
              <col style="width:25%">
              <col style="width:12%">
              <?php foreach ($columnasPermitidas as $col): ?>
                <col style="width:<?= $anchoColumna ?>%">
              <?php endforeach; ?>
              <col style="width:15%">
              <col style="width:12%">
              <col style="width:10%">
            </colgroup>
            <thead class="bg-blue-600 text-white">
              <tr>
                <th class="px-3 py-2 text-left">Conductor</th>
                <th class="px-3 py-2 text-center">Tipo</th>
                <?php foreach ($columnasPermitidas as $col): ?>
                  <th class="px-3 py-2 text-center" title="<?= ucfirst(str_replace('_', ' ', $col)) ?>">
                    <?= substr(ucfirst(str_replace('_', ' ', $col)), 0, 3) ?>
                  </th>
                <?php endforeach; ?>
                <th class="px-3 py-2 text-center">Total</th>
                <th class="px-3 py-2 text-center">Pagado</th>
                <th class="px-3 py-2 text-center">Faltante</th>
              </tr>
            </thead>
            <tbody id="tabla_conductores_body" class="divide-y divide-slate-100 bg-white">
            <?php foreach ($datos as $conductor => $info): 
              $esMensual = (stripos($info['vehiculo'], 'mensual') !== false);
              $claseVehiculo = $esMensual ? 'vehiculo-mensual' : '';
            ?>
              <tr data-vehiculo="<?= htmlspecialchars($info['vehiculo']) ?>" 
                  data-conductor="<?= htmlspecialchars($conductor) ?>" 
                  data-pagado="<?= (int)($info['pagado'] ?? 0) ?>"
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
                    <?= htmlspecialchars($info['vehiculo']) ?>
                    <?php if ($esMensual): ?>
                      <span class="ml-1">📅</span>
                    <?php endif; ?>
                  </span>
                </td>
                <?php foreach ($columnasPermitidas as $col): ?>
                  <td class="px-3 py-2 text-center"><?= (int)($info[$col] ?? 0) ?></td>
                <?php endforeach; ?>
                
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
                         value="<?= number_format((int)($info['pagado'] ?? 0), 0, ',', '.') ?>">
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
      </section>

      <!-- Columna 3: Panel viajes -->
      <aside class="space-y-5">
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
          <h4 class="text-base font-semibold mb-3">🧳 Viajes del Conductor</h4>
          <div id="contenidoPanel"
               class="min-h-[220px] max-h-[400px] overflow-y-auto rounded-xl border border-slate-200 p-4 text-sm text-slate-600">
            <div class="flex flex-col items-center justify-center h-full text-center">
              <div class="text-slate-400 mb-2">
                <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
              </div>
              <p class="m-0 font-medium text-slate-500">Selecciona un conductor para ver sus viajes</p>
              <p class="m-0 text-xs text-slate-400 mt-1">Se mostrarán con clasificaciones de colores</p>
            </div>
          </div>
        </div>
      </aside>

    </div>
  </main>

  <script>
    // ===== VARIABLES =====
    const columnasTarifas = <?= json_encode($columnasTarifas) ?>;
    const columnasPermitidas = <?= json_encode($columnasPermitidas) ?>;

    // ===== BUSCADOR DE CONDUCTORES =====
    const buscadorConductores = document.getElementById('buscadorConductores');
    const clearBuscar = document.getElementById('clearBuscar');
    const contadorConductores = document.getElementById('contador-conductores');

    function normalizarTexto(texto) {
      return texto.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
    }

    function filtrarConductores() {
      const textoBusqueda = normalizarTexto(buscadorConductores.value);
      const filas = document.querySelectorAll('#tabla_conductores_body tr');
      let filasVisibles = 0;
      
      filas.forEach(fila => {
        const nombre = fila.querySelector('.conductor-link').textContent;
        if (textoBusqueda === '' || normalizarTexto(nombre).includes(textoBusqueda)) {
          fila.style.display = '';
          filasVisibles++;
        } else {
          fila.style.display = 'none';
        }
      });
      
      clearBuscar.style.display = textoBusqueda ? 'block' : 'none';
      contadorConductores.textContent = `Mostrando ${filasVisibles} de ${filas.length} conductores`;
      recalcular();
    }

    buscadorConductores.addEventListener('input', filtrarConductores);
    clearBuscar.addEventListener('click', () => {
      buscadorConductores.value = '';
      filtrarConductores();
    });

    // ===== OBTENER TARIFAS =====
    function getTarifas(){
      const tarifas = {};
      document.querySelectorAll('.tarjeta-tarifa').forEach(card=>{
        const veh = card.dataset.vehiculo;
        const tarifaVeh = {};
        columnasTarifas.forEach(col => {
          const el = card.querySelector(`input[data-campo="${col}"]`);
          tarifaVeh[col] = el ? (parseFloat(el.value)||0) : 0;
        });
        tarifas[veh] = tarifaVeh;
      });
      return tarifas;
    }

    function formatNumber(num){ 
      return new Intl.NumberFormat('es-CO').format(num || 0); 
    }

    // ===== RECALCULAR TOTALES =====
    function recalcular(){
      const tarifas = getTarifas();
      const filas = document.querySelectorAll('#tabla_conductores_body tr');

      let totalViajes = 0;
      let totalPagado = 0;
      let totalFaltante = 0;

      filas.forEach(f=>{
        if (f.style.display === 'none') return;

        const veh = f.dataset.vehiculo;
        const pagado = parseInt(f.dataset.pagado) || 0;
        
        // Obtener cantidades
        const cantidades = {};
        columnasPermitidas.forEach((col, index) => {
          const cellIndex = 2 + index;
          cantidades[col] = parseInt(f.cells[cellIndex]?.textContent) || 0;
        });

        // Calcular total
        const t = tarifas[veh] || {};
        let totalFila = 0;
        columnasPermitidas.forEach(col => {
          totalFila += (cantidades[col] || 0) * (t[col] || 0);
        });

        // Calcular faltante
        let faltante = totalFila - pagado;
        if (faltante < 0) faltante = 0;

        // Actualizar celdas
        const inpTotal = f.querySelector('input.totales');
        if (inpTotal) inpTotal.value = formatNumber(totalFila);

        const inpFalt = f.querySelector('input.faltante');
        if (inpFalt) inpFalt.value = formatNumber(faltante);

        totalViajes += totalFila;
        totalPagado += pagado;
        totalFaltante += faltante;
      });

      document.getElementById('total_viajes').textContent = formatNumber(totalViajes);
      document.getElementById('total_general').textContent = formatNumber(totalViajes);
      document.getElementById('total_pagado').textContent = formatNumber(totalPagado);
      document.getElementById('total_faltante').textContent = formatNumber(totalFaltante);
    }

    // ===== GUARDAR CLASIFICACIÓN =====
    function guardarClasificacionRuta(ruta, vehiculo, clasificacion) {
      if (!clasificacion) return;
      
      fetch('<?= basename(__FILE__) ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          guardar_clasificacion: 1,
          ruta: ruta,
          tipo_vehiculo: vehiculo,
          clasificacion: clasificacion
        })
      })
      .then(r => r.text())
      .then(t => {
        if (t.trim() !== 'ok') {
          console.error('Error:', t);
        }
      });
    }

    // ===== APLICAR CLASIFICACIÓN MASIVA =====
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
        if (ruta.includes(patron)) {
          const select = row.querySelector('.select-clasif-ruta');
          select.value = clasif;
          guardarClasificacionRuta(row.dataset.ruta, row.dataset.vehiculo, clasif);
          contador++;
        }
      });

      if (contador > 0) {
        alert(`Se aplicó a ${contador} rutas. Recarga la página para ver cambios en la liquidación.`);
      } else {
        alert('No se encontraron rutas con ese texto.');
      }
    }

    // ===== GUARDAR TARIFA =====
    function guardarTarifa(campo, valor, vehiculo) {
      const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
      
      fetch('<?= basename(__FILE__) ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          guardar_tarifa: 1,
          empresa: empresa,
          tipo_vehiculo: vehiculo,
          campo: campo,
          valor: valor
        })
      })
      .then(r => r.text())
      .then(t => {
        if (t.trim() !== 'ok') {
          console.error('Error guardando tarifa:', t);
        } else {
          recalcular();
        }
      });
    }

    // ===== INICIALIZACIÓN =====
    document.addEventListener('DOMContentLoaded', function() {
      // Eventos para tarifas
      document.querySelectorAll('.tarifa-input').forEach(input => {
        let timeout;
        input.addEventListener('input', function() {
          clearTimeout(timeout);
          timeout = setTimeout(() => {
            const card = this.closest('.tarjeta-tarifa');
            guardarTarifa(this.dataset.campo, this.value, card.dataset.vehiculo);
          }, 500);
        });
      });

      // Eventos para clasificación de rutas - ESTO ES LO MÁS IMPORTANTE
      document.addEventListener('change', function(event) {
        if (event.target.classList.contains('select-clasif-ruta')) {
          const select = event.target;
          const ruta = select.getAttribute('data-ruta');
          const vehiculo = select.getAttribute('data-vehiculo');
          const clasificacion = select.value;
          
          if (clasificacion) {
            guardarClasificacionRuta(ruta, vehiculo, clasificacion);
          }
        }
      });

      // Eventos para ver viajes
      document.querySelectorAll('.conductor-link').forEach(btn => {
        btn.addEventListener('click', function() {
          const nombre = this.textContent.trim();
          const desde = "<?= htmlspecialchars($desde) ?>";
          const hasta = "<?= htmlspecialchars($hasta) ?>";
          const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
          
          const panel = document.getElementById('contenidoPanel');
          panel.innerHTML = '<div class="text-center py-8"><div class="animate-pulse text-blue-500">Cargando...</div></div>';
          
          fetch(`<?= basename(__FILE__) ?>?viajes_conductor=${encodeURIComponent(nombre)}&desde=${desde}&hasta=${hasta}&empresa=${encodeURIComponent(empresa)}`)
            .then(r => r.text())
            .then(html => {
              panel.innerHTML = `<div class="mb-3"><h5 class="font-semibold text-blue-700">Viajes de: ${nombre}</h5></div>` + html;
            });
        });
      });

      // Calcular al cargar
      recalcular();
    });
  </script>
</body>
</html>