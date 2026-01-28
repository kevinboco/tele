<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

/* =======================================================
   🔹 PASO 1: Obtener TODAS las clasificaciones/tarifas existentes
======================================================= */
function obtenerClasificacionesDisponibles($conn) {
    $clasificaciones = [];
    
    // 1. Obtener de columnas de tarifas (excepto campos no tarifas)
    $res = $conn->query("SHOW COLUMNS FROM tarifas");
    if ($res) {
        while ($col = $res->fetch_assoc()) {
            $colName = $col['Field'];
            // Solo columnas que son tarifas
            if (!in_array($colName, ['id', 'empresa', 'tipo_vehiculo', 'riohacha', 'pru'])) {
                $clasificaciones[$colName] = ucfirst($colName);
            }
        }
    }
    
    // 2. Obtener de valores únicos ya usados en ruta_clasificacion
    $res2 = $conn->query("SELECT DISTINCT clasificacion FROM ruta_clasificacion WHERE clasificacion != ''");
    if ($res2) {
        while ($row = $res2->fetch_assoc()) {
            $clasif = $row['clasificacion'];
            if (!isset($clasificaciones[$clasif])) {
                $clasificaciones[$clasif] = ucfirst($clasif);
            }
        }
    }
    
    // Ordenar alfabéticamente
    ksort($clasificaciones);
    
    return $clasificaciones;
}

$todas_clasificaciones = obtenerClasificacionesDisponibles($conn);

/* =======================================================
   🔹 Guardar tarifas por vehículo y empresa (AJAX)
======================================================= */
if (isset($_POST['guardar_tarifa'])) {
    $empresa  = $conn->real_escape_string($_POST['empresa']);
    $vehiculo = $conn->real_escape_string($_POST['tipo_vehiculo']);
    $campo    = $conn->real_escape_string($_POST['campo']);
    $valor    = (int)$_POST['valor'];

    // Validar campo
    if (!preg_match('/^[a-z_]+$/', $campo)) {
        echo "error: campo inválido";
        exit;
    }

    // Verificar si el campo existe, si no, crearlo
    $check = $conn->query("SHOW COLUMNS FROM tarifas LIKE '$campo'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE tarifas ADD COLUMN `$campo` DECIMAL(10,2) DEFAULT 0.00");
    }

    $conn->query("INSERT IGNORE INTO tarifas (empresa, tipo_vehiculo) VALUES ('$empresa', '$vehiculo')");
    $sql = "UPDATE tarifas SET $campo = $valor WHERE empresa='$empresa' AND tipo_vehiculo='$vehiculo'";
    echo $conn->query($sql) ? "ok" : "error: " . $conn->error;
    exit;
}

/* =======================================================
   🔹 Guardar CLASIFICACIÓN de rutas (manual) - AJAX
======================================================= */
if (isset($_POST['guardar_clasificacion'])) {
    $ruta       = $conn->real_escape_string($_POST['ruta']);
    $vehiculo   = $conn->real_escape_string($_POST['tipo_vehiculo']);
    $clasif     = $conn->real_escape_string($_POST['clasificacion']);

    // Validar que no esté vacío
    if (empty($clasif)) {
        echo "error: clasificación vacía";
        exit;
    }

    // Verificar si la clasificación ya existe como columna en tarifas
    $check = $conn->query("SHOW COLUMNS FROM tarifas LIKE '$clasif'");
    if ($check->num_rows == 0) {
        // Crear nueva columna en tarifas
        $conn->query("ALTER TABLE tarifas ADD COLUMN `$clasif` DECIMAL(10,2) DEFAULT 0.00");
    }

    $sql = "INSERT INTO ruta_clasificacion (ruta, tipo_vehiculo, clasificacion)
            VALUES ('$ruta', '$vehiculo', '$clasif')
            ON DUPLICATE KEY UPDATE clasificacion = VALUES(clasificacion)";
    echo $conn->query($sql) ? "ok" : ("error: " . $conn->error);
    exit;
}

/* =======================================================
   🔹 NUEVO: Guardar TODAS las clasificaciones de una vez
======================================================= */
if (isset($_POST['guardar_todas_clasificaciones'])) {
    $datos = json_decode($_POST['datos'], true);
    $guardados = 0;
    $errores = 0;
    
    foreach ($datos as $item) {
        $ruta = $conn->real_escape_string($item['ruta']);
        $vehiculo = $conn->real_escape_string($item['vehiculo']);
        $clasif = $conn->real_escape_string($item['clasificacion']);
        
        if (!empty($clasif)) {
            // Verificar si la clasificación ya existe como columna
            $check = $conn->query("SHOW COLUMNS FROM tarifas LIKE '$clasif'");
            if ($check->num_rows == 0) {
                $conn->query("ALTER TABLE tarifas ADD COLUMN `$clasif` DECIMAL(10,2) DEFAULT 0.00");
            }
            
            $sql = "INSERT INTO ruta_clasificacion (ruta, tipo_vehiculo, clasificacion)
                    VALUES ('$ruta', '$vehiculo', '$clasif')
                    ON DUPLICATE KEY UPDATE clasificacion = VALUES(clasificacion)";
            
            if ($conn->query($sql)) {
                $guardados++;
            } else {
                $errores++;
            }
        }
    }
    
    echo json_encode(['guardados' => $guardados, 'errores' => $errores]);
    exit;
}

/* =======================================================
   🔹 Endpoint AJAX: viajes por conductor
======================================================= */
if (isset($_GET['viajes_conductor'])) {
    // ... (mantener el mismo código de viajes_conductor que ya tienes)
    // ... [TODO EL CÓDIGO EXISTENTE DE viajes_conductor]
    exit;
}

/* =======================================================
   🔹 Formulario inicial (si no hay rango)
======================================================= */
if (!isset($_GET['desde']) || !isset($_GET['hasta'])) {
    // ... (mantener el mismo código del formulario inicial)
    // ... [TODO EL CÓDIGO EXISTENTE DEL FORMULARIO]
    exit;
}

/* =======================================================
   🔹 Cálculo y armado de tablas
======================================================= */
$desde = $_GET['desde'];
$hasta = $_GET['hasta'];
$empresaFiltro = $_GET['empresa'] ?? "";

$todas_clasificaciones = obtenerClasificacionesDisponibles($conn);

/* --- Cargar clasificaciones de rutas desde BD --- */
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

// Inicializar contadores
$contadores_clasificaciones = [];
foreach ($todas_clasificaciones as $clave => $nombre) {
    $contadores_clasificaciones[$clave] = 0;
}

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $nombre   = $row['nombre'];
        $ruta     = $row['ruta'];
        $vehiculo = $row['tipo_vehiculo'];
        $pagoParcial = (int)($row['pago_parcial'] ?? 0);

        if (!isset($pagosConductor[$nombre])) $pagosConductor[$nombre] = 0;
        $pagosConductor[$nombre] += $pagoParcial;

        $keyRuta = mb_strtolower(trim($ruta . '|' . $vehiculo), 'UTF-8');

        if (!isset($rutasUnicas[$keyRuta])) {
            $rutasUnicas[$keyRuta] = [
                'ruta'          => $ruta,
                'vehiculo'      => $vehiculo,
                'clasificacion' => $clasif_rutas[$keyRuta] ?? ''
            ];
        }

        if (!in_array($vehiculo, $vehiculos, true)) {
            $vehiculos[] = $vehiculo;
        }

        if (!isset($datos[$nombre])) {
            $datos[$nombre] = ['vehiculo' => $vehiculo];
            foreach ($todas_clasificaciones as $clave => $nombre_clasif) {
                $datos[$nombre][$clave] = 0;
            }
            $datos[$nombre]['pagado'] = 0;
        }

        $clasifRuta = $clasif_rutas[$keyRuta] ?? '';
        if ($clasifRuta !== '' && isset($datos[$nombre][$clasifRuta])) {
            $datos[$nombre][$clasifRuta]++;
            $contadores_clasificaciones[$clasifRuta]++;
        }
    }
}

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
  .fila-viaje-sin-clasificar {
    opacity: 0.7;
  }
  .campo-tarifa-dinamico {
    border-left: 3px solid #8b5cf6;
    background-color: #faf5ff;
  }
  .guardando {
    opacity: 0.7;
    cursor: wait !important;
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
      </div>
    </div>
  </header>

  <!-- Contenido -->
  <main class="max-w-[1600px] mx-auto px-3 md:px-4 py-6">
    <div class="grid grid-cols-1 xl:grid-cols-[1fr_2.6fr_0.9fr] gap-5 items-start">

      <!-- Columna 1: Tarifas + Filtro + Clasificación de rutas -->
      <section class="space-y-5">

        <!-- Tarjetas de tarifas -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
          <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <span>🚐 Tarifas por Tipo de Vehículo</span>
            <span class="text-xs text-slate-500">(<?= count($todas_clasificaciones) ?> tipos)</span>
          </h3>

          <div id="tarifas_grid" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php foreach ($vehiculos as $veh):
              $t = $tarifas_guardadas[$veh] ?? [];
              foreach ($todas_clasificaciones as $clave => $nombre) {
                if (!isset($t[$clave])) {
                  $t[$clave] = 0;
                }
              }
            ?>
            <div class="tarjeta-tarifa rounded-2xl border border-slate-200 p-4 shadow-sm bg-slate-50"
                 data-vehiculo="<?= htmlspecialchars($veh) ?>">

              <div class="flex items-center justify-between mb-3">
                <div class="text-base font-semibold"><?= htmlspecialchars($veh) ?></div>
                <span class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-700 border border-blue-200">Config</span>
              </div>

              <!-- Campos de tarifas -->
              <div id="campos-tarifas-<?= preg_replace('/[^a-z0-9]/', '-', strtolower($veh)) ?>" class="space-y-3">
                <?php foreach ($todas_clasificaciones as $clave => $nombre): 
                  $esDinamico = !in_array($clave, ['completo', 'medio', 'extra', 'siapana', 'carrotanque']);
                  $claseExtra = $esDinamico ? 'campo-tarifa-dinamico' : '';
                ?>
                <label class="block <?= $claseExtra ?>">
                  <span class="block text-sm font-medium mb-1"><?= htmlspecialchars(ucfirst($clave)) ?></span>
                  <input type="number" step="1000" value="<?= (int)($t[$clave] ?? 0) ?>"
                         data-campo="<?= htmlspecialchars($clave) ?>"
                         class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-white outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition tarifa-input"
                         placeholder="0">
                </label>
                <?php endforeach; ?>
              </div>

              <!-- Botón para añadir nueva clasificación -->
              <div class="mt-4 pt-3 border-t border-slate-200">
                <div class="flex gap-2">
                  <input type="text" 
                         id="nueva-clasif-<?= preg_replace('/[^a-z0-9]/', '-', strtolower($veh)) ?>" 
                         class="flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-blue-100"
                         placeholder="Nueva clasificación">
                  <button type="button" 
                          onclick="agregarClasificacionTarifa('<?= htmlspecialchars($veh) ?>')"
                          class="rounded-lg bg-green-600 text-white px-3 py-1.5 text-sm font-semibold hover:bg-green-700">
                    + Añadir
                  </button>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Filtro -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
          <h5 class="text-base font-semibold text-center mb-4">📅 Filtro de Liquidación</h5>
          <form id="formFiltro" class="grid grid-cols-1 md:grid-cols-4 gap-3" method="get">
            <input type="hidden" name="desde" value="<?= htmlspecialchars($desde) ?>">
            <input type="hidden" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
            <input type="hidden" name="empresa" value="<?= htmlspecialchars($empresaFiltro) ?>">
            
            <label class="block md:col-span-1">
              <span class="block text-sm font-medium mb-1">Desde</span>
              <input type="date" id="inputDesde" value="<?= htmlspecialchars($desde) ?>" required
                     class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition">
            </label>
            <label class="block md:col-span-1">
              <span class="block text-sm font-medium mb-1">Hasta</span>
              <input type="date" id="inputHasta" value="<?= htmlspecialchars($hasta) ?>" required
                     class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition">
            </label>
            <label class="block md:col-span-1">
              <span class="block text-sm font-medium mb-1">Empresa</span>
              <select id="selectEmpresa"
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
              <button type="button" id="btnFiltrar"
                      class="w-full rounded-xl bg-blue-600 text-white py-2.5 font-semibold shadow hover:bg-blue-700 active:bg-blue-800 focus:ring-4 focus:ring-blue-200 transition">
                Filtrar
              </button>
            </div>
          </form>
          
          <!-- Botón adicional para guardar clasificaciones -->
          <div class="mt-4 text-center">
            <button type="button" onclick="guardarYFiltrar()"
                    class="rounded-xl bg-purple-600 text-white py-2.5 px-4 font-semibold shadow hover:bg-purple-700 active:bg-purple-800 focus:ring-4 focus:ring-purple-200 transition">
              💾 Guardar clasificaciones y Filtrar
            </button>
            <p class="text-xs text-slate-500 mt-2">Usa este botón para guardar cambios antes de filtrar</p>
          </div>
        </div>

        <!-- 🔹 Panel de CLASIFICACIÓN de RUTAS -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
          <h5 class="text-base font-semibold mb-3 flex items-center justify-between">
            <span>🧭 Clasificación de Rutas</span>
            <span class="text-xs text-slate-500">Se guarda en BD</span>
          </h5>
          <p class="text-xs text-slate-500 mb-3">
            Ajusta qué tipo es cada ruta. <strong>Recuerda guardar antes de filtrar</strong>.
          </p>

          <!-- Select para aplicar clasificación masiva -->
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
                <?php foreach ($todas_clasificaciones as $clave => $nombre): ?>
                  <option value="<?= htmlspecialchars($clave) ?>"><?= htmlspecialchars(ucfirst($clave)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="button"
                    onclick="aplicarClasificacionMasiva()"
                    class="mt-2 md:mt-0 inline-flex items-center justify-center rounded-xl bg-purple-600 text-white px-4 py-2 text-sm font-semibold hover:bg-purple-700 active:bg-purple-800 focus:ring-4 focus:ring-purple-200">
              ⚙️ Aplicar a coincidentes
            </button>
          </div>

          <!-- Campo para añadir nueva clasificación -->
          <div class="mb-3 p-3 bg-blue-50 rounded-xl border border-blue-200">
            <label class="block text-xs font-medium mb-1 text-blue-700">
              <span class="flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Añadir nueva clasificación
              </span>
            </label>
            <div class="flex gap-2">
              <input type="text" 
                     id="nueva_clasificacion_global" 
                     class="flex-1 rounded-lg border border-blue-300 px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-blue-100"
                     placeholder="Nombre de nueva clasificación">
              <button type="button" 
                      onclick="agregarClasificacionGlobal()"
                      class="rounded-lg bg-blue-600 text-white px-3 py-1.5 text-sm font-semibold hover:bg-blue-700">
                Crear
              </button>
            </div>
            <p class="text-xs text-blue-600 mt-1">Esta clasificación estará disponible en tarifas y rutas</p>
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
              <tbody class="divide-y divide-slate-100" id="tablaClasificaciones">
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
                            data-vehiculo="<?= htmlspecialchars($info['vehiculo']) ?>"
                            onchange="guardarClasificacionIndividual(this)">
                      <option value="">Sin clasificar</option>
                      <?php foreach ($todas_clasificaciones as $clave => $nombre): ?>
                      <option value="<?= htmlspecialchars($clave) ?>" <?= $info['clasificacion']===$clave ? 'selected' : '' ?>>
                        <?= htmlspecialchars(ucfirst($clave)) ?>
                      </option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- Botón para guardar todas las clasificaciones -->
          <div class="mt-3 text-center">
            <button type="button" onclick="guardarTodasClasificaciones()"
                    class="rounded-lg bg-green-600 text-white py-2 px-4 text-sm font-semibold hover:bg-green-700">
              💾 Guardar TODAS las clasificaciones
            </button>
            <p class="text-xs text-slate-500 mt-1">Haz clic aquí antes de usar el botón "Filtrar"</p>
          </div>
        </div>
      </section>

      <!-- Columna 2: Resumen por conductor -->
      <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
        <!-- ... (mantener el mismo código de la tabla de conductores) ... -->
      </section>

      <!-- Columna 3: Panel viajes -->
      <aside class="space-y-5">
        <!-- ... (mantener el mismo código del panel de viajes) ... -->
      </aside>

    </div>
  </main>

  <script>
    // ===== FUNCIONES PARA GUARDAR CLASIFICACIONES =====
    
    // Guardar clasificación individual
    function guardarClasificacionIndividual(select) {
      const ruta = select.dataset.ruta;
      const vehiculo = select.dataset.vehiculo;
      const clasif = select.value;
      
      if (!clasif) return;
      
      // Mostrar indicador de guardando
      select.classList.add('guardando');
      
      fetch('<?= basename(__FILE__) ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          guardar_clasificacion: 1,
          ruta: ruta,
          tipo_vehiculo: vehiculo,
          clasificacion: clasif
        })
      })
      .then(r => r.text())
      .then(t => {
        select.classList.remove('guardando');
        if (t.trim() === 'ok') {
          // Mostrar feedback visual
          select.style.borderColor = '#10b981';
          setTimeout(() => {
            select.style.borderColor = '';
          }, 1000);
        } else {
          alert('Error al guardar: ' + t);
        }
      })
      .catch(() => {
        select.classList.remove('guardando');
        alert('Error de conexión al guardar');
      });
    }
    
    // Guardar TODAS las clasificaciones de una vez
    function guardarTodasClasificaciones() {
      const selects = document.querySelectorAll('.select-clasif-ruta');
      const datos = [];
      
      selects.forEach(select => {
        const ruta = select.dataset.ruta;
        const vehiculo = select.dataset.vehiculo;
        const clasif = select.value;
        
        if (clasif) {
          datos.push({
            ruta: ruta,
            vehiculo: vehiculo,
            clasificacion: clasif
          });
        }
      });
      
      if (datos.length === 0) {
        alert('No hay clasificaciones para guardar');
        return;
      }
      
      // Mostrar mensaje de guardando
      const boton = event.target;
      const textoOriginal = boton.innerHTML;
      boton.innerHTML = '💾 Guardando...';
      boton.disabled = true;
      
      fetch('<?= basename(__FILE__) ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          guardar_todas_clasificaciones: 1,
          datos: JSON.stringify(datos)
        })
      })
      .then(r => r.json())
      .then(resultado => {
        boton.innerHTML = textoOriginal;
        boton.disabled = false;
        
        alert(`✅ Guardadas: ${resultado.guardados} clasificaciones\n❌ Errores: ${resultado.errores}`);
        
        // Recargar la página para ver cambios
        setTimeout(() => {
          location.reload();
        }, 1000);
      })
      .catch(() => {
        boton.innerHTML = textoOriginal;
        boton.disabled = false;
        alert('Error al guardar');
      });
    }
    
    // Función combinada: guardar y luego filtrar
    function guardarYFiltrar() {
      guardarTodasClasificaciones();
      
      // Después de guardar, ejecutar el filtro
      setTimeout(() => {
        ejecutarFiltro();
      }, 1500);
    }
    
    // Ejecutar filtro después de guardar
    function ejecutarFiltro() {
      const desde = document.getElementById('inputDesde').value;
      const hasta = document.getElementById('inputHasta').value;
      const empresa = document.getElementById('selectEmpresa').value;
      
      // Actualizar formulario oculto
      document.querySelector('input[name="desde"]').value = desde;
      document.querySelector('input[name="hasta"]').value = hasta;
      document.querySelector('input[name="empresa"]').value = empresa;
      
      // Enviar formulario
      document.getElementById('formFiltro').submit();
    }
    
    // Configurar botón Filtrar
    document.getElementById('btnFiltrar').addEventListener('click', function(e) {
      e.preventDefault();
      
      // Preguntar si quiere guardar primero
      if (confirm('¿Deseas guardar las clasificaciones antes de filtrar?\n\nRecomendado: Haz clic en "Guardar TODAS las clasificaciones" primero.')) {
        // Mostrar opciones
        const opcion = confirm('¿Quieres:\n\n1. Solo guardar (Cancelar)\n2. Guardar y filtrar (Aceptar)');
        
        if (opcion) {
          guardarYFiltrar();
        } else {
          guardarTodasClasificaciones();
        }
      } else {
        // Filtrar sin guardar (puede perder cambios)
        ejecutarFiltro();
      }
    });
    
    // ===== FUNCIONES PARA CLASIFICACIONES DINÁMICAS =====
    
    function agregarClasificacionTarifa(vehiculo) {
      const inputId = 'nueva-clasif-' + vehiculo.toLowerCase().replace(/[^a-z0-9]/g, '-');
      const input = document.getElementById(inputId);
      const nombreClasif = input.value.trim().toLowerCase();
      
      if (!nombreClasif) {
        alert('Escribe un nombre para la nueva clasificación');
        return;
      }
      
      if (!/^[a-z_]+$/.test(nombreClasif)) {
        alert('Solo letras minúsculas y guiones bajos (_)');
        return;
      }
      
      const tarjeta = document.querySelector(`[data-vehiculo="${vehiculo}"]`);
      const existe = tarjeta.querySelector(`input[data-campo="${nombreClasif}"]`);
      if (existe) {
        alert('Esta clasificación ya existe');
        input.value = '';
        return;
      }
      
      const nuevoCampo = `
        <label class="block campo-tarifa-dinamico">
          <span class="block text-sm font-medium mb-1">${nombreClasif.charAt(0).toUpperCase() + nombreClasif.slice(1)}</span>
          <input type="number" step="1000" value="0"
                 data-campo="${nombreClasif}"
                 class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-white outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition tarifa-input"
                 placeholder="0">
        </label>
      `;
      
      const contenedor = tarjeta.querySelector('.space-y-3');
      contenedor.insertAdjacentHTML('beforeend', nuevoCampo);
      
      // Guardar en BD
      guardarNuevaClasificacion(nombreClasif);
      
      input.value = '';
      alert(`✅ Clasificación "${nombreClasif}" añadida`);
    }
    
    function agregarClasificacionGlobal() {
      const input = document.getElementById('nueva_clasificacion_global');
      const nombreClasif = input.value.trim().toLowerCase();
      
      if (!nombreClasif) {
        alert('Escribe un nombre para la nueva clasificación');
        return;
      }
      
      if (!/^[a-z_]+$/.test(nombreClasif)) {
        alert('Solo letras minúsculas y guiones bajos (_)');
        return;
      }
      
      guardarNuevaClasificacion(nombreClasif);
      
      input.value = '';
      alert(`✅ Clasificación "${nombreClasif}" creada. Recarga la página para verla en los selects.`);
      setTimeout(() => {
        location.reload();
      }, 1000);
    }
    
    function guardarNuevaClasificacion(nombreClasif) {
      fetch('<?= basename(__FILE__) ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          guardar_tarifa: 1,
          empresa: '<?= htmlspecialchars($empresaFiltro) ?>',
          tipo_vehiculo: 'Burbuja',
          campo: nombreClasif,
          valor: 0
        })
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
          guardarClasificacionIndividual(sel);
          contador++;
        }
      });
      
      alert('✅ Se aplicó la clasificación a ' + contador + ' rutas.');
    }
    
    // ===== FUNCIONES EXISTENTES (recalcular, buscador, etc) =====
    // ... (mantener todas las funciones existentes de recalcular, buscador, etc) ...
    
    document.addEventListener('DOMContentLoaded', function() {
      // Configurar eventos para inputs de tarifas
      document.querySelectorAll('.tarifa-input').forEach(input => {
        input.addEventListener('change', function() {
          const card = this.closest('.tarjeta-tarifa');
          const tipoVehiculo = card.dataset.vehiculo;
          const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
          const campo = this.dataset.campo;
          const valor = parseInt(this.value) || 0;
          
          fetch('<?= basename(__FILE__) ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
              guardar_tarifa: 1,
              empresa: empresa,
              tipo_vehiculo: tipoVehiculo,
              campo: campo,
              valor: valor
            })
          });
        });
      });
      
      // Inicializar cálculos
      if (typeof recalcular === 'function') {
        recalcular();
      }
    });
  </script>
</body>
</html>