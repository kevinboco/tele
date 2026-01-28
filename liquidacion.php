<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

/* =======================================================
   🔹 PASO 1: Obtener TODAS las clasificaciones/tarifas existentes
   De la tabla tarifas (columnas) y de la tabla ruta_clasificacion
======================================================= */
function obtenerClasificacionesDisponibles($conn) {
    $clasificaciones = [];
    
    // 1. Obtener de columnas de tarifas (excepto campos no tarifas)
    $res = $conn->query("SHOW COLUMNS FROM tarifas");
    if ($res) {
        while ($col = $res->fetch_assoc()) {
            $colName = $col['Field'];
            // Solo columnas que son tarifas (no id, empresa, tipo_vehiculo, etc)
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
    
    return $clasificaciones;
}

$todas_clasificaciones = obtenerClasificacionesDisponibles($conn);

/* =======================================================
   🔹 Guardar tarifas por vehículo y empresa (AJAX)
   (ahora soporta CUALQUIER campo dinámico)
======================================================= */
if (isset($_POST['guardar_tarifa'])) {
    $empresa  = $conn->real_escape_string($_POST['empresa']);
    $vehiculo = $conn->real_escape_string($_POST['tipo_vehiculo']);
    $campo    = $conn->real_escape_string($_POST['campo']);
    $valor    = (int)$_POST['valor'];

    // Validar que el campo sea seguro (solo letras y underscore)
    if (!preg_match('/^[a-z_]+$/', $campo)) {
        echo "error: campo inválido";
        exit;
    }

    // Verificar si el campo existe en la tabla, si no, crearlo
    $check = $conn->query("SHOW COLUMNS FROM tarifas LIKE '$campo'");
    if ($check->num_rows == 0) {
        // Crear nueva columna para la tarifa
        $conn->query("ALTER TABLE tarifas ADD COLUMN `$campo` DECIMAL(10,2) DEFAULT 0.00");
    }

    $conn->query("INSERT IGNORE INTO tarifas (empresa, tipo_vehiculo) VALUES ('$empresa', '$vehiculo')");
    $sql = "UPDATE tarifas SET $campo = $valor WHERE empresa='$empresa' AND tipo_vehiculo='$vehiculo'";
    echo $conn->query($sql) ? "ok" : "error: " . $conn->error;
    exit;
}

/* =======================================================
   🔹 Guardar CLASIFICACIÓN de rutas (manual) - AJAX
   (ahora acepta CUALQUIER clasificación de las disponibles)
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
    // Si no existe, la creamos automáticamente
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
   🔹 Endpoint AJAX: viajes por conductor (CON CLASIFICACIONES Y COLORES)
======================================================= */
if (isset($_GET['viajes_conductor'])) {
    $nombre  = $conn->real_escape_string($_GET['viajes_conductor']);
    $desde   = $_GET['desde'];
    $hasta   = $_GET['hasta'];
    $empresa = $_GET['empresa'] ?? "";

    // Obtener todas las clasificaciones disponibles para colores
    $todas_clasificaciones = obtenerClasificacionesDisponibles($conn);
    
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

    // Definir colores base y estilos
    $colores_base = [
        'completo'    => ['bg-emerald-100', 'text-emerald-700', 'border-emerald-200'],
        'medio'       => ['bg-amber-100', 'text-amber-800', 'border-amber-200'],
        'extra'       => ['bg-slate-200', 'text-slate-800', 'border-slate-300'],
        'siapana'     => ['bg-fuchsia-100', 'text-fuchsia-700', 'border-fuchsia-200'],
        'carrotanque' => ['bg-cyan-100', 'text-cyan-800', 'border-cyan-200'],
    ];
    
    // Generar colores dinámicos para nuevas clasificaciones
    $colores_dinamicos = [
        'bg-blue-100', 'text-blue-700', 'border-blue-200',
        'bg-purple-100', 'text-purple-700', 'border-purple-200',
        'bg-pink-100', 'text-pink-700', 'border-pink-200',
        'bg-indigo-100', 'text-indigo-700', 'border-indigo-200',
        'bg-teal-100', 'text-teal-700', 'border-teal-200',
        'bg-orange-100', 'text-orange-700', 'border-orange-200',
        'bg-lime-100', 'text-lime-700', 'border-lime-200',
        'bg-rose-100', 'text-rose-700', 'border-rose-200',
    ];
    
    $color_index = 0;
    $legend = [];
    
    // Asignar colores a todas las clasificaciones
    foreach ($todas_clasificaciones as $clave => $nombre_clasif) {
        if (isset($colores_base[$clave])) {
            $legend[$clave] = [
                'label' => ucfirst($clave),
                'badge' => $colores_base[$clave][0] . ' ' . $colores_base[$clave][1] . ' border ' . $colores_base[$clave][2],
                'row'   => str_replace('bg-', 'bg-', $colores_base[$clave][0]) . '/40'
            ];
        } else {
            // Asignar color dinámico
            $color_class = $colores_dinamicos[$color_index % count($colores_dinamicos)];
            $color_index++;
            
            $legend[$clave] = [
                'label' => ucfirst($clave),
                'badge' => $color_class . ' border',
                'row'   => str_replace('bg-', 'bg-', explode(' ', $color_class)[0]) . '/40'
            ];
        }
    }
    
    // Agregar "otro" para clasificaciones no encontradas
    $legend['otro'] = [
        'label' => 'Sin clasificar',
        'badge' => 'bg-gray-100 text-gray-700 border border-gray-200',
        'row'   => 'bg-gray-50/20'
    ];

    if ($res && $res->num_rows > 0) {
        // Contadores para cada clasificación
        $counts = [];
        foreach ($todas_clasificaciones as $clave => $nombre) {
            $counts[$clave] = 0;
        }
        $counts['otro'] = 0;

        $rowsHTML = "";
        
        while ($r = $res->fetch_assoc()) {
            $ruta = (string)$r['ruta'];
            $vehiculo = $r['tipo_vehiculo'];
            
            // 🔹 Determinar clasificación desde la tabla ruta_clasificacion
            $key = mb_strtolower(trim($ruta . '|' . $vehiculo), 'UTF-8');
            $cat = $clasif_rutas[$key] ?? 'otro';
            
            // Validar que sea una clasificación válida
            if (!isset($legend[$cat])) {
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
        foreach ($legend as $k => $l) {
            $countVal = $counts[$k] ?? 0;
            if ($countVal > 0 || $k === 'otro') {
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
   AHORA usando CLASIFICACIÓN MANUAL DE RUTAS
   + SUMA pago_parcial POR CONDUCTOR
======================================================= */
$desde = $_GET['desde'];
$hasta = $_GET['hasta'];
$empresaFiltro = $_GET['empresa'] ?? "";

/* --- Obtener todas las clasificaciones disponibles --- */
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

// Inicializar contadores para todas las clasificaciones
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
            $datos[$nombre] = ['vehiculo' => $vehiculo];
            // Inicializar contadores para todas las clasificaciones
            foreach ($todas_clasificaciones as $clave => $nombre_clasif) {
                $datos[$nombre][$clave] = 0;
            }
            $datos[$nombre]['pagado'] = 0;
        }

        // 🔹 Clasificación MANUAL de la ruta
        $clasifRuta = $clasif_rutas[$keyRuta] ?? '';

        // Si la ruta tiene clasificación, sumar al contador
        if ($clasifRuta !== '' && isset($datos[$nombre][$clasifRuta])) {
            $datos[$nombre][$clasifRuta]++;
            $contadores_clasificaciones[$clasifRuta]++;
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
  .campo-tarifa-dinamico {
    border-left: 3px solid #8b5cf6;
    background-color: #faf5ff;
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

        <!-- Tarjetas de tarifas (con TODAS las clasificaciones) -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
          <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <span>🚐 Tarifas por Tipo de Vehículo</span>
            <span class="text-xs text-slate-500">(<?= count($todas_clasificaciones) ?> tipos)</span>
          </h3>

          <div id="tarifas_grid" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php foreach ($vehiculos as $veh):
              $t = $tarifas_guardadas[$veh] ?? [];
              // Asegurar que todas las clasificaciones existan en el array
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

              <!-- Campos de tarifas DINÁMICOS -->
              <div id="campos-tarifas-<?= preg_replace('/[^a-z0-9]/', '-', strtolower($veh)) ?>" class="space-y-3">
                <?php foreach ($todas_clasificaciones as $clave => $nombre): 
                  $esDinamico = !in_array($clave, ['completo', 'medio', 'extra', 'siapana', 'carrotanque']);
                  $claseExtra = $esDinamico ? 'campo-tarifa-dinamico' : '';
                ?>
                <label class="block <?= $claseExtra ?>">
                  <span class="block text-sm font-medium mb-1"><?= htmlspecialchars(ucfirst($clave)) ?></span>
                  <input type="number" step="1000" value="<?= (int)($t[$clave] ?? 0) ?>"
                         data-campo="<?= htmlspecialchars($clave) ?>"
                         class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-white outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition"
                         oninput="recalcular()"
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
                         placeholder="Nueva clasificación (ej: corto, express)">
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
                     placeholder="Nombre de nueva clasificación (ej: corto, express, nocturno)">
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
          <table id="tabla_conductores" class="w-full text-sm table-fixed min-w-[1100px]">
            <thead class="bg-blue-600 text-white">
              <tr>
                <th class="px-3 py-2 text-left" style="width:20%">Conductor</th>
                <th class="px-3 py-2 text-center" style="width:12%">Tipo</th>
                <?php 
                $ancho_col = min(5, floor(68 / count($todas_clasificaciones)));
                foreach ($todas_clasificaciones as $clave => $nombre): 
                ?>
                <th class="px-3 py-2 text-center" style="width:<?= $ancho_col ?>%" 
                    title="<?= htmlspecialchars(ucfirst($clave)) ?>">
                  <?= htmlspecialchars(substr(ucfirst($clave), 0, 3)) ?>
                </th>
                <?php endforeach; ?>
                <th class="px-3 py-2 text-center" style="width:15%">Total</th>
                <th class="px-3 py-2 text-center" style="width:12%">Pagado</th>
                <th class="px-3 py-2 text-center" style="width:10%">Faltante</th>
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
                
                <?php foreach ($todas_clasificaciones as $clave => $nombre): ?>
                <td class="px-3 py-2 text-center"><?= (int)($viajes[$clave] ?? 0) ?></td>
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

      <!-- Columna 3: Panel viajes CON CLASIFICACIONES DE COLORES -->
      <aside class="space-y-5">
        <!-- Panel viajes -->
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
              
              <!-- Mini leyenda de colores -->
              <div class="mt-4 flex flex-wrap gap-1.5 justify-center" id="mini-leyenda">
                <!-- Se llenará dinámicamente -->
              </div>
            </div>
          </div>
        </div>
      </aside>

    </div>
  </main>

  <script>
    // ===== FUNCIONES PARA CLASIFICACIONES DINÁMICAS =====
    
    // Agregar nueva clasificación desde tarifas específicas
    function agregarClasificacionTarifa(vehiculo) {
      const inputId = 'nueva-clasif-' + vehiculo.toLowerCase().replace(/[^a-z0-9]/g, '-');
      const input = document.getElementById(inputId);
      const nombreClasif = input.value.trim().toLowerCase();
      
      if (!nombreClasif) {
        alert('Escribe un nombre para la nueva clasificación');
        return;
      }
      
      // Validar formato
      if (!/^[a-z_]+$/.test(nombreClasif)) {
        alert('Solo letras minúsculas y guiones bajos (_)');
        return;
      }
      
      // Crear nuevo campo en la tarjeta
      const tarjeta = document.querySelector(`[data-vehiculo="${vehiculo}"]`);
      const contenedor = tarjeta.querySelector('.space-y-3');
      
      // Verificar si ya existe
      const existe = tarjeta.querySelector(`input[data-campo="${nombreClasif}"]`);
      if (existe) {
        alert('Esta clasificación ya existe');
        input.value = '';
        return;
      }
      
      // Crear nuevo campo
      const nuevoCampo = `
        <label class="block campo-tarifa-dinamico">
          <span class="block text-sm font-medium mb-1">${nombreClasif.charAt(0).toUpperCase() + nombreClasif.slice(1)}</span>
          <input type="number" step="1000" value="0"
                 data-campo="${nombreClasif}"
                 class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-white outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition"
                 oninput="recalcular()"
                 placeholder="0">
        </label>
      `;
      
      contenedor.insertAdjacentHTML('beforeend', nuevoCampo);
      
      // Guardar en BD
      guardarNuevaClasificacion(nombreClasif);
      
      // Actualizar selects de clasificación
      actualizarSelectsClasificacion();
      
      // Limpiar input
      input.value = '';
      
      alert(`✅ Clasificación "${nombreClasif}" añadida`);
    }
    
    // Agregar nueva clasificación global
    function agregarClasificacionGlobal() {
      const input = document.getElementById('nueva_clasificacion_global');
      const nombreClasif = input.value.trim().toLowerCase();
      
      if (!nombreClasif) {
        alert('Escribe un nombre para la nueva clasificación');
        return;
      }
      
      // Validar formato
      if (!/^[a-z_]+$/.test(nombreClasif)) {
        alert('Solo letras minúsculas y guiones bajos (_)');
        return;
      }
      
      // Guardar en BD
      guardarNuevaClasificacion(nombreClasif);
      
      // Actualizar todos los selects
      actualizarSelectsClasificacion();
      
      // Limpiar input
      input.value = '';
      
      alert(`✅ Clasificación "${nombreClasif}" creada y disponible en todo el sistema`);
    }
    
    // Guardar nueva clasificación en BD
    function guardarNuevaClasificacion(nombreClasif) {
      // Crear columna en tabla tarifas si no existe
      fetch('<?= basename(__FILE__) ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          guardar_tarifa: 1,
          empresa: '<?= htmlspecialchars($empresaFiltro) ?>',
          tipo_vehiculo: 'Burbuja', // Vehículo por defecto
          campo: nombreClasif,
          valor: 0
        })
      })
      .then(r => r.text())
      .then(t => {
        if (t.trim() !== 'ok') console.error('Error creando clasificación:', t);
      });
    }
    
    // Actualizar todos los selects de clasificación
    function actualizarSelectsClasificacion() {
      // Recargar la página para ver los cambios
      setTimeout(() => {
        window.location.reload();
      }, 500);
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
        tarifas[veh] = {};
        
        // Obtener TODAS las tarifas (campos dinámicos)
        const inputs = card.querySelectorAll('input[data-campo]');
        inputs.forEach(input => {
          const campo = input.dataset.campo;
          const valor = parseFloat(input.value) || 0;
          tarifas[veh][campo] = valor;
        });
      });
      return tarifas;
    }

    function formatNumber(num){ 
      return new Intl.NumberFormat('es-CO').format(num || 0);
    }

    function recalcular(){
      const tarifas = getTarifas();
      const filas = document.querySelectorAll('#tabla_conductores_body tr');
      
      // Obtener todas las clasificaciones disponibles
      const clasificaciones = [];
      const primeraFila = filas[0];
      if (primeraFila) {
        const celdas = primeraFila.querySelectorAll('td');
        // Las clasificaciones empiezan desde la celda 2 (después de conductor y tipo)
        for (let i = 2; i < celdas.length - 3; i++) { // -3 para excluir total, pagado, faltante
          clasificaciones.push(i);
        }
      }

      let totalViajes = 0;
      let totalPagado = 0;
      let totalFaltante = 0;

      filas.forEach(f => {
        if (f.style.display === 'none') return;

        const veh = f.dataset.vehiculo;
        const conductor = f.dataset.conductor;
        const t = tarifas[veh] || {};

        let totalFila = 0;
        
        // Calcular por cada clasificación
        clasificaciones.forEach((colIndex, index) => {
          const cantidad = parseInt(f.cells[colIndex].innerText) || 0;
          // Obtener el nombre de la clasificación desde el encabezado
          const headerCell = document.querySelector(`#tabla_conductores thead th:nth-child(${colIndex + 1})`);
          let nombreClasif = '';
          if (headerCell) {
            nombreClasif = headerCell.getAttribute('title') || headerCell.innerText.toLowerCase();
          }
          
          if (nombreClasif && t[nombreClasif]) {
            totalFila += cantidad * t[nombreClasif];
          }
        });

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

      alert('✅ Se aplicó la clasificación a ' + contador + ' rutas. Vuelve a darle "Filtrar" para recalcular la liquidación.');
    }

    document.addEventListener('DOMContentLoaded', function() {
      // Inicializar mini leyenda
      inicializarMiniLeyenda();
      
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

      // Click en conductor → carga viajes (AJAX) CON CLASIFICACIONES DE COLORES
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
              // Agregar título del conductor
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

      recalcular();
    });
    
    function inicializarMiniLeyenda() {
      const leyendaContainer = document.getElementById('mini-leyenda');
      if (!leyendaContainer) return;
      
      // Colores para la mini leyenda
      const colores = [
        ['Completo', 'bg-emerald-100', 'text-emerald-700', 'border-emerald-200'],
        ['Medio', 'bg-amber-100', 'text-amber-700', 'border-amber-200'],
        ['Extra', 'bg-slate-200', 'text-slate-700', 'border-slate-300'],
        ['Siapana', 'bg-fuchsia-100', 'text-fuchsia-700', 'border-fuchsia-200'],
        ['Carrotanque', 'bg-cyan-100', 'text-cyan-700', 'border-cyan-200'],
        ['Corto', 'bg-blue-100', 'text-blue-700', 'border-blue-200'],
        ['Express', 'bg-purple-100', 'text-purple-700', 'border-purple-200'],
        ['Nocturno', 'bg-indigo-100', 'text-indigo-700', 'border-indigo-200']
      ];
      
      leyendaContainer.innerHTML = '';
      
      // Mostrar solo los primeros 8 colores como ejemplo
      colores.slice(0, 8).forEach(item => {
        const [nombre, bg, text, border] = item;
        const colorClass = bg.replace('bg-', '').replace('-100', '');
        
        leyendaContainer.innerHTML += `
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium ${bg} ${text} border ${border}">
            <span class="w-1.5 h-1.5 rounded-full bg-${colorClass}-500"></span> ${nombre}
          </span>
        `;
      });
      
      // Agregar indicador de más colores
      leyendaContainer.innerHTML += `
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-600 border border-gray-200">
          <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> +más
        </span>
      `;
    }
  </script>
</body>
</html>