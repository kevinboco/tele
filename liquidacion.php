<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

/* =======================================================
   🔹 Agregar NUEVA CLASIFICACIÓN/TARIFA a la tabla tarifas
======================================================= */
if (isset($_POST['agregar_clasificacion_tarifa'])) {
    $nombre_clasif = $conn->real_escape_string($_POST['nombre_clasif']);
    
    if (empty($nombre_clasif)) {
        echo "error: nombre vacío";
        exit;
    }
    
    // Convertir a formato de columna (sin espacios, minúsculas, sin caracteres especiales)
    $columna = preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace(' ', '_', $nombre_clasif)));
    
    if (empty($columna)) {
        echo "error: nombre inválido";
        exit;
    }
    
    // 1. Verificar si la columna ya existe en la tabla tarifas
    $check = $conn->query("SHOW COLUMNS FROM tarifas LIKE '$columna'");
    if ($check && $check->num_rows > 0) {
        echo "error: ya existe";
        exit;
    }
    
    // 2. Agregar la nueva columna a la tabla tarifas como DECIMAL
    $sql = "ALTER TABLE tarifas ADD COLUMN `$columna` DECIMAL(10,2) DEFAULT 0.00";
    if ($conn->query($sql)) {
        // 3. Crear tabla para trackear las clasificaciones personalizadas si no existe
        $conn->query("CREATE TABLE IF NOT EXISTS clasificaciones_personalizadas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) UNIQUE NOT NULL,
            columna VARCHAR(100) UNIQUE NOT NULL,
            creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // 4. Guardar en la tabla de clasificaciones personalizadas
        $conn->query("INSERT IGNORE INTO clasificaciones_personalizadas (nombre, columna) VALUES ('$nombre_clasif', '$columna')");
        
        echo "ok:$columna";
    } else {
        echo "error: " . $conn->error;
    }
    exit;
}

/* =======================================================
   🔹 Guardar tarifas por vehículo y empresa (AJAX)
   (ahora soporta campos dinámicos)
======================================================= */
if (isset($_POST['guardar_tarifa'])) {
    $empresa  = $conn->real_escape_string($_POST['empresa']);
    $vehiculo = $conn->real_escape_string($_POST['tipo_vehiculo']);
    $campo    = $conn->real_escape_string($_POST['campo']);
    $valor    = (int)$_POST['valor'];

    // ⚠️ Validar que el campo exista en la tabla tarifas
    $check = $conn->query("SHOW COLUMNS FROM tarifas LIKE '$campo'");
    if (!$check || $check->num_rows === 0) {
        echo "error: campo no existe";
        exit;
    }

    $conn->query("INSERT IGNORE INTO tarifas (empresa, tipo_vehiculo) VALUES ('$empresa', '$vehiculo')");
    $sql = "UPDATE tarifas SET `$campo` = $valor WHERE empresa='$empresa' AND tipo_vehiculo='$vehiculo'";
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

    // ⚠️ PERMITE CUALQUIER CLASIFICACIÓN
    $sql = "INSERT INTO ruta_clasificacion (ruta, tipo_vehiculo, clasificacion)
            VALUES ('$ruta', '$vehiculo', '$clasif')
            ON DUPLICATE KEY UPDATE clasificacion = VALUES(clasificacion)";
    echo $conn->query($sql) ? "ok" : ("error: " . $conn->error);
    exit;
}

/* =======================================================
   🔹 Obtener TODAS las clasificaciones/tarifas disponibles
======================================================= */
function obtenerTodasClasificacionesTarifas($conn) {
    $clasificaciones = [];
    
    // 1. Obtener columnas de la tabla tarifas (excepto id, empresa, tipo_vehiculo)
    $res = $conn->query("SHOW COLUMNS FROM tarifas");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $field = $row['Field'];
            // Excluir campos que no son tarifas
            if (!in_array($field, ['id', 'empresa', 'tipo_vehiculo'])) {
                // Convertir nombre de columna a nombre legible
                $nombre = ucfirst(str_replace('_', ' ', $field));
                $clasificaciones[$field] = $nombre;
            }
        }
    }
    
    // 2. También obtener de la tabla clasificaciones_personalizadas para mantener nombres originales
    $res2 = $conn->query("SELECT columna, nombre FROM clasificaciones_personalizadas");
    if ($res2) {
        while ($row = $res2->fetch_assoc()) {
            if (isset($clasificaciones[$row['columna']])) {
                $clasificaciones[$row['columna']] = $row['nombre'];
            }
        }
    }
    
    // Ordenar alfabéticamente por nombre
    asort($clasificaciones);
    
    return $clasificaciones;
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

    // Definir colores y estilos
    $legend = [
        'completo'     => ['label'=>'Completo',     'badge'=>'bg-emerald-100 text-emerald-700 border border-emerald-200', 'row'=>'bg-emerald-50/40'],
        'medio'        => ['label'=>'Medio',        'badge'=>'bg-amber-100 text-amber-800 border border-amber-200',       'row'=>'bg-amber-50/40'],
        'extra'        => ['label'=>'Extra',        'badge'=>'bg-slate-200 text-slate-800 border border-slate-300',       'row'=>'bg-slate-50'],
        'siapana'      => ['label'=>'Siapana',      'badge'=>'bg-fuchsia-100 text-fuchsia-700 border border-fuchsia-200', 'row'=>'bg-fuchsia-50/40'],
        'carrotanque'  => ['label'=>'Carrotanque',  'badge'=>'bg-cyan-100 text-cyan-800 border border-cyan-200',          'row'=>'bg-cyan-50/40'],
        'otro'         => ['label'=>'Sin clasificar','badge'=>'bg-gray-100 text-gray-700 border border-gray-200',         'row'=>'bg-gray-50/20']
    ];

    if ($res && $res->num_rows > 0) {
        // Contadores para cada clasificación
        $counts = [
            'completo' => 0,
            'medio' => 0,
            'extra' => 0,
            'siapana' => 0,
            'carrotanque' => 0,
            'otro' => 0
        ];

        $rowsHTML = "";
        
        while ($r = $res->fetch_assoc()) {
            $ruta = (string)$r['ruta'];
            $vehiculo = $r['tipo_vehiculo'];
            
            // 🔹 Determinar clasificación desde la tabla ruta_clasificacion
            $key = mb_strtolower(trim($ruta . '|' . $vehiculo), 'UTF-8');
            $cat = $clasif_rutas[$key] ?? 'otro';
            
            // Validar que sea una clasificación válida
            if (!in_array($cat, ['completo','medio','extra','siapana','carrotanque'])) {
                $cat = 'otro';
            }

            // Incrementar contador
            if (isset($counts[$cat])) {
                $counts[$cat]++;
            } else {
                $counts[$cat] = 1;
            }

            $l = $legend[$cat];
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

        // Generar HTML con filtros y tabla
        echo "<div class='space-y-3'>";
        
        // Leyenda con contadores y filtro
        echo "<div class='flex flex-wrap gap-2 text-xs' id='legendFilterBar'>";
        foreach (['completo','medio','extra','siapana','carrotanque','otro'] as $k) {
            $l = $legend[$k];
            $countVal = $counts[$k] ?? 0;
            $badgeClass = str_replace(['bg-','/40'], ['bg-',''], $l['row']);
            echo "<button
                    class='legend-pill inline-flex items-center gap-2 px-3 py-2 rounded-full {$l['badge']} hover:opacity-90 transition ring-0 outline-none border cursor-pointer select-none'
                    data-tipo='{$k}'
                  >
                    <span class='w-2.5 h-2.5 rounded-full {$badgeClass} bg-opacity-100 border border-white/30 shadow-inner'></span>
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
                
                // Ejecutar filtros después de cargar
                setTimeout(attachFiltroViajes, 100);
              </script>";

    } else {
        echo "<p class='text-center text-gray-500 py-4'>No se encontraron viajes para este conductor en ese rango.</p>";
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

/* --- Obtener TODAS las clasificaciones/tarifas disponibles --- */
$todasClasificaciones = obtenerTodasClasificacionesTarifas($conn);

/* --- Cargar clasificaciones de rutas desde BD --- */
$clasif_rutas = [];
$resClasif = $conn->query("SELECT ruta, tipo_vehiculo, clasificacion FROM ruta_clasificacion");
if ($resClasif) {
    while ($r = $resClasif->fetch_assoc()) {
        $key = mb_strtolower(trim($r['ruta'] . '|' . $r['tipo_vehiculo']), 'UTF-8');
        $clasif_rutas[$key] = $r['clasificacion'];
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
$pagosConductor = [];      // suma pago_parcial por conductor

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
                "vehiculo" => $vehiculo,
                "pagado"   => 0,
            ];
            
            // Inicializar contadores para todas las clasificaciones
            foreach ($todasClasificaciones as $columna => $nombreClasif) {
                $datos[$nombre][$columna] = 0;
            }
        }

        // 🔹 Clasificación MANUAL de la ruta
        $clasifRuta = $clasif_rutas[$keyRuta] ?? '';

        // Si la ruta tiene clasificación y existe en nuestras tarifas, contarla
        if ($clasifRuta !== '' && isset($datos[$nombre][$clasifRuta])) {
            $datos[$nombre][$clasifRuta]++;
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
  .fila-viaje-sin-clasificar {
    opacity: 0.7;
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

        <!-- 🔹 TARJETAS DE TARIFAS DINÁMICAS -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
          <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <span>🚐 Tarifas por Tipo de Vehículo</span>
            <!-- Botón para agregar nueva tarifa -->
            <button onclick="mostrarModalAgregarTarifa()"
                    class="ml-auto text-xs px-3 py-1 rounded-full bg-purple-100 text-purple-700 border border-purple-200 hover:bg-purple-200">
              + Nueva tarifa
            </button>
          </h3>

          <!-- Modal para agregar nueva tarifa (oculto inicialmente) -->
          <div id="modalAgregarTarifa" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
            <div class="bg-white rounded-2xl p-6 max-w-md w-full">
              <h4 class="text-lg font-semibold mb-4">➕ Agregar nueva tarifa/clasificación</h4>
              <div class="space-y-3">
                <div>
                  <label class="block text-sm font-medium mb-1">Nombre de la tarifa</label>
                  <input type="text" id="nombre_nueva_tarifa" 
                         placeholder="Ej: Riohacha, Local, Express..." 
                         class="w-full rounded-xl border border-slate-300 px-3 py-2">
                  <p class="text-xs text-slate-500 mt-1">
                    Esta tarifa aparecerá en todos los vehículos (excepto Carrotanque)
                  </p>
                </div>
                <div class="flex gap-2 pt-2">
                  <button onclick="cerrarModalAgregarTarifa()"
                          class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-slate-700">
                    Cancelar
                  </button>
                  <button onclick="agregarNuevaTarifa()"
                          class="flex-1 rounded-xl bg-purple-600 text-white px-3 py-2 font-semibold">
                    Agregar
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div id="tarifas_grid" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php foreach ($vehiculos as $veh):
              $t = $tarifas_guardadas[$veh] ?? [];
              $esCarrotanque = ($veh === "Carrotanque");
            ?>
            <div class="tarjeta-tarifa rounded-2xl border border-slate-200 p-4 shadow-sm bg-slate-50"
                 data-vehiculo="<?= htmlspecialchars($veh) ?>">

              <div class="flex items-center justify-between mb-3">
                <div class="text-base font-semibold"><?= htmlspecialchars($veh) ?></div>
                <span class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-700 border border-blue-200">Config</span>
              </div>

              <div class="space-y-3">
                <?php foreach ($todasClasificaciones as $columna => $nombreClasif): 
                  // Para Carrotanque, solo mostrar carrotanque y siapana
                  if ($esCarrotanque && !in_array($columna, ['carrotanque', 'siapana'])) {
                    continue;
                  }
                  
                  // Para otros vehículos, NO mostrar carrotanque
                  if (!$esCarrotanque && $columna === 'carrotanque') {
                    continue;
                  }
                  
                  $valor = isset($t[$columna]) ? (float)$t[$columna] : 0;
                ?>
                <label class="block">
                  <span class="block text-sm font-medium mb-1"><?= htmlspecialchars($nombreClasif) ?></span>
                  <input type="number" step="1000" value="<?= (int)$valor ?>"
                         data-campo="<?= htmlspecialchars($columna) ?>"
                         class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-white outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition tarifa-input"
                         oninput="recalcular()">
                </label>
                <?php endforeach; ?>
              </div>
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
            Ajusta qué tipo es cada ruta. Las opciones vienen de las tarifas configuradas.
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
                <?php foreach($todasClasificaciones as $columna => $nombreClasif): ?>
                  <option value="<?= htmlspecialchars($columna) ?>"><?= htmlspecialchars($nombreClasif) ?></option>
                <?php endforeach; ?>
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
                      <?php foreach($todasClasificaciones as $columna => $nombreClasif): ?>
                        <option value="<?= htmlspecialchars($columna) ?>" 
                                <?= $info['clasificacion']===$columna ? 'selected' : '' ?>>
                          <?= htmlspecialchars($nombreClasif) ?>
                        </option>
                      <?php endforeach; ?>
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

      <!-- Columna 2: Resumen por conductor -->
      <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
        
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
          <table id="tabla_conductores" class="w-full text-sm min-w-[1000px]">
            <thead class="bg-blue-600 text-white">
              <tr>
                <th class="px-3 py-2 text-left">Conductor</th>
                <th class="px-3 py-2 text-center">Tipo</th>
                <?php foreach ($todasClasificaciones as $columna => $nombreClasif): 
                  // Determinar abreviatura para la columna
                  $abrev = strtoupper(substr(str_replace('_', ' ', $columna), 0, 2));
                  if ($columna === 'carrotanque') $abrev = 'CT';
                  if ($columna === 'siapana') $abrev = 'S';
                  if ($columna === 'riohacha') $abrev = 'R';
                  if ($columna === 'completo') $abrev = 'C';
                  if ($columna === 'medio') $abrev = 'M';
                  if ($columna === 'extra') $abrev = 'E';
                ?>
                <th class="px-3 py-2 text-center" title="<?= htmlspecialchars($nombreClasif) ?>"><?= $abrev ?></th>
                <?php endforeach; ?>
                <th class="px-3 py-2 text-center">Total</th>
                <th class="px-3 py-2 text-center">Pagado</th>
                <th class="px-3 py-2 text-center">Faltante</th>
              </tr>
            </thead>
            <tbody id="tabla_conductores_body" class="divide-y divide-slate-100 bg-white">
            <?php foreach ($datos as $conductor => $viajes): 
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
                
                <?php foreach ($todasClasificaciones as $columna => $nombreClasif): ?>
                  <td class="px-3 py-2 text-center"><?= (int)($viajes[$columna] ?? 0) ?></td>
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
      recalcular();
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

    // ===== FUNCIONES PARA AGREGAR NUEVAS TARIFAS =====
    function mostrarModalAgregarTarifa() {
      document.getElementById('modalAgregarTarifa').classList.remove('hidden');
      document.getElementById('modalAgregarTarifa').classList.add('flex');
      document.getElementById('nombre_nueva_tarifa').focus();
    }

    function cerrarModalAgregarTarifa() {
      document.getElementById('modalAgregarTarifa').classList.add('hidden');
      document.getElementById('modalAgregarTarifa').classList.remove('flex');
      document.getElementById('nombre_nueva_tarifa').value = '';
    }

    function agregarNuevaTarifa() {
      const nombre = document.getElementById('nombre_nueva_tarifa').value.trim();
      
      if (!nombre) {
        alert('Ingresa un nombre para la nueva tarifa');
        return;
      }
      
      fetch('<?= basename(__FILE__) ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          agregar_clasificacion_tarifa: 1,
          nombre_clasif: nombre
        })
      })
      .then(r => r.text())
      .then(t => {
        if (t.startsWith('ok:')) {
          const columna = t.split(':')[1];
          const nombreDisplay = nombre.charAt(0).toUpperCase() + nombre.slice(1);
          
          // 1. Agregar al select de clasificación masiva
          const selectMasivo = document.getElementById('sel_clasif_masiva');
          const nuevaOpcion = document.createElement('option');
          nuevaOpcion.value = columna;
          nuevaOpcion.textContent = nombreDisplay;
          selectMasivo.appendChild(nuevaOpcion);
          
          // 2. Agregar a todos los selects de clasificación de rutas
          document.querySelectorAll('.select-clasif-ruta').forEach(select => {
            const option = document.createElement('option');
            option.value = columna;
            option.textContent = nombreDisplay;
            select.appendChild(option);
          });
          
          // 3. Agregar campo de tarifa a todos los vehículos (excepto Carrotanque para ciertas tarifas)
          document.querySelectorAll('.tarjeta-tarifa').forEach(card => {
            const vehiculo = card.dataset.vehiculo;
            const esCarrotanque = (vehiculo === "Carrotanque");
            
            // Para Carrotanque, solo agregar si no es "carrotanque" (ya tiene) y es "siapana" o similar
            // Para otros vehículos, agregar todas las tarifas
            if (!esCarrotanque || (esCarrotanque && ['siapana', nombre.toLowerCase()].includes(nombre.toLowerCase()))) {
              const nuevoInput = document.createElement('label');
              nuevoInput.className = 'block';
              nuevoInput.innerHTML = `
                <span class="block text-sm font-medium mb-1">${nombreDisplay}</span>
                <input type="number" step="1000" value="0"
                       data-campo="${columna}"
                       class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-white outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition tarifa-input"
                       oninput="recalcular()">
              `;
              card.querySelector('.space-y-3').appendChild(nuevoInput);
            }
          });
          
          // 4. Agregar columna a la tabla de conductores
          const thead = document.querySelector('#tabla_conductores thead tr');
          const tbodyRows = document.querySelectorAll('#tabla_conductores_body tr');
          
          // Agregar th
          const nuevoTh = document.createElement('th');
          nuevoTh.className = 'px-3 py-2 text-center';
          nuevoTh.title = nombreDisplay;
          nuevoTh.textContent = nombreDisplay.substring(0, 2).toUpperCase();
          // Insertar antes de Total (penúltima columna)
          thead.insertBefore(nuevoTh, thead.children[thead.children.length - 3]);
          
          // Agregar td a cada fila
          tbodyRows.forEach(row => {
            const nuevoTd = document.createElement('td');
            nuevoTd.className = 'px-3 py-2 text-center';
            nuevoTd.textContent = '0';
            // Insertar antes de Total (penúltima columna)
            row.insertBefore(nuevoTd, row.children[row.children.length - 3]);
          });
          
          // Cerrar modal y limpiar
          cerrarModalAgregarTarifa();
          alert(`✅ Tarifa "${nombre}" agregada exitosamente a todos los vehículos.`);
          
          // Recalcular
          setTimeout(recalcular, 100);
        } else if (t === 'error: ya existe') {
          alert('Esta tarifa ya existe');
        } else {
          alert('Error al agregar la tarifa: ' + t);
        }
      })
      .catch(err => {
        console.error('Error:', err);
        alert('Error al agregar la tarifa');
      });
    }

    // ===== FUNCIONES DE CÁLCULO =====
    function getTarifas(){
      const tarifas = {};
      document.querySelectorAll('.tarjeta-tarifa').forEach(card=>{
        const veh = card.dataset.vehiculo;
        tarifas[veh] = {};
        
        // Obtener todos los inputs de tarifa
        card.querySelectorAll('.tarifa-input').forEach(input=>{
          const campo = input.dataset.campo;
          const valor = parseFloat(input.value) || 0;
          tarifas[veh][campo] = valor;
        });
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
        const tarifasVeh = tarifas[veh] || {};
        
        let totalFila = 0;
        
        // Calcular sumando todas las columnas (excepto las últimas 3: Total, Pagado, Faltante)
        for (let i = 2; i < f.cells.length - 3; i++) {
          const cantidad = parseInt(f.cells[i].innerText) || 0;
          // Encontrar el nombre de la columna (basado en el índice)
          const thead = document.querySelector('#tabla_conductores thead th:nth-child(' + (i + 1) + ')');
          if (thead) {
            const columna = thead.title.toLowerCase().replace(' ', '_');
            const tarifa = tarifasVeh[columna] || 0;
            totalFila += cantidad * tarifa;
          }
        }

        const pagado = parseInt(f.dataset.pagado || '0') || 0;
        let faltante = totalFila - pagado;
        if (faltante < 0) faltante = 0;

        const inpTotal = f.querySelector('input.totales');
        if (inpTotal) inpTotal.value = formatNumber(totalFila);

        const inpFalt = f.querySelector('input.faltante');
        if (inpFalt) inpFalt.value = formatNumber(faltante);

        totalViajes += totalFila;
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

      alert('✅ Se aplicó la clasificación a ' + contador + ' rutas. Vuelve a darle "Filtrar" para recalcular.');
    }

    document.addEventListener('DOMContentLoaded', function() {
      // Guardar tarifas AJAX
      document.querySelectorAll('.tarifa-input').forEach(input=>{
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

      // Click en conductor → carga viajes
      document.querySelectorAll('.conductor-link').forEach(btn=>{
        btn.addEventListener('click', ()=>{
          const nombre = btn.textContent.trim();
          const desde  = "<?= htmlspecialchars($desde) ?>";
          const hasta  = "<?= htmlspecialchars($hasta) ?>";
          const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
          const panel = document.getElementById('contenidoPanel');
          panel.innerHTML = "<div class='flex items-center justify-center h-full'><div class='text-center'><div class='animate-pulse text-blue-500 mb-2'>⏳</div><p class='text-sm text-slate-500'>Cargando viajes...</p></div></div>";

          fetch('<?= basename(__FILE__) ?>?viajes_conductor='+encodeURIComponent(nombre)+'&desde='+desde+'&hasta='+hasta+'&empresa='+encodeURIComponent(empresa))
            .then(r=>r.text())
            .then(html=>{ 
              panel.innerHTML = html;
              const titulo = `<div class="mb-3 pb-2 border-b border-slate-200">
                                <h5 class="font-semibold text-blue-700">Viajes de: <span class="text-blue-900">${nombre}</span></h5>
                                <p class="text-xs text-slate-500">${desde} a ${hasta}</p>
                              </div>`;
              panel.innerHTML = titulo + panel.innerHTML;
            })
            .catch(() => {
              panel.innerHTML = "<p class='text-center text-rose-600 py-4'>Error cargando viajes.</p>";
            });
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

      // Permitir agregar tarifa con Enter
      document.getElementById('nombre_nueva_tarifa').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          agregarNuevaTarifa();
        }
      });

      // Cerrar modal con Escape
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('modalAgregarTarifa').classList.contains('flex')) {
          cerrarModalAgregarTarifa();
        }
      });

      recalcular();
    });
  </script>

</body>
</html>