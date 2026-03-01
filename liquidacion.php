<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

/* =======================================================
   🔹 FUNCIONES DINÁMICAS (sin cambios
======================================================= */

// Obtener columnas de tarifas dinámicamente
function obtenerColumnasTarifas($conn) {
    $columnas = [];
    $res = $conn->query("SHOW COLUMNS FROM tarifas");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $field = $row['Field'];
            $excluir = ['id', 'empresa', 'tipo_vehiculo', 'created_at', 'updated_at'];
            if (!in_array($field, $excluir)) {
                $columnas[] = $field;
            }
        }
    }
    return $columnas;
}

// Crear nueva columna en tarifas
function crearNuevaColumnaTarifa($conn, $nombre_columna) {
    $nombre_columna = preg_replace('/[^a-zA-Z0-9_]/', '_', $nombre_columna);
    $nombre_columna = strtolower($nombre_columna);
    
    $columnas_existentes = obtenerColumnasTarifas($conn);
    if (in_array($nombre_columna, $columnas_existentes)) {
        return true;
    }
    
    $sql = "ALTER TABLE tarifas ADD COLUMN `$nombre_columna` DECIMAL(10,2) DEFAULT 0.00";
    return $conn->query($sql);
}

// Obtener clasificaciones disponibles
function obtenerClasificacionesDisponibles($conn) {
    return obtenerColumnasTarifas($conn);
}

// Mapeo de colores para clasificaciones
function obtenerEstiloClasificacion($clasificacion) {
    $estilos = [
        'completo'    => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200', 'row' => 'bg-emerald-50/40', 'label' => 'Completo'],
        'medio'       => ['bg' => 'bg-amber-100', 'text' => 'text-amber-800', 'border' => 'border-amber-200', 'row' => 'bg-amber-50/40', 'label' => 'Medio'],
        'extra'       => ['bg' => 'bg-slate-200', 'text' => 'text-slate-800', 'border' => 'border-slate-300', 'row' => 'bg-slate-50', 'label' => 'Extra'],
        'siapana'     => ['bg' => 'bg-fuchsia-100', 'text' => 'text-fuchsia-700', 'border' => 'border-fuchsia-200', 'row' => 'bg-fuchsia-50/40', 'label' => 'Siapana'],
        'carrotanque' => ['bg' => 'bg-cyan-100', 'text' => 'text-cyan-800', 'border' => 'border-cyan-200', 'row' => 'bg-cyan-50/40', 'label' => 'Carrotanque'],
        'riohacha'    => ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-700', 'border' => 'border-indigo-200', 'row' => 'bg-indigo-50/40', 'label' => 'Riohacha'],
        'pru'         => ['bg' => 'bg-teal-100', 'text' => 'text-teal-700', 'border' => 'border-teal-200', 'row' => 'bg-teal-50/40', 'label' => 'Pru'],
        'maco'        => ['bg' => 'bg-rose-100', 'text' => 'text-rose-700', 'border' => 'border-rose-200', 'row' => 'bg-rose-50/40', 'label' => 'Maco']
    ];
    
    if (isset($estilos[$clasificacion])) {
        return $estilos[$clasificacion];
    }
    
    $colors = [
        ['bg' => 'bg-violet-100', 'text' => 'text-violet-700', 'border' => 'border-violet-200'],
        ['bg' => 'bg-orange-100', 'text' => 'text-orange-700', 'border' => 'border-orange-200'],
        ['bg' => 'bg-lime-100', 'text' => 'text-lime-700', 'border' => 'border-lime-200'],
        ['bg' => 'bg-sky-100', 'text' => 'text-sky-700', 'border' => 'border-sky-200'],
        ['bg' => 'bg-pink-100', 'text' => 'text-pink-700', 'border' => 'border-pink-200'],
        ['bg' => 'bg-purple-100', 'text' => 'text-purple-700', 'border' => 'border-purple-200'],
        ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-700', 'border' => 'border-yellow-200'],
        ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'border' => 'border-red-200'],
    ];
    
    $hash = crc32($clasificacion);
    $color_index = abs($hash) % count($colors);
    
    return [
        'bg' => $colors[$color_index]['bg'],
        'text' => $colors[$color_index]['text'],
        'border' => $colors[$color_index]['border'],
        'row' => str_replace('bg-', 'bg-', $colors[$color_index]['bg']) . '/40',
        'label' => ucfirst($clasificacion)
    ];
}

// Colores únicos por tipo de vehículo
function obtenerColorVehiculo($vehiculo) {
    $colores_vehiculos = [
        'camioneta' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'border' => 'border-blue-200', 'dark' => 'bg-blue-50'],
        'turbo' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'border' => 'border-green-200', 'dark' => 'bg-green-50'],
        'mensual' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-700', 'border' => 'border-orange-200', 'dark' => 'bg-orange-50'],
        'camión' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-700', 'border' => 'border-purple-200', 'dark' => 'bg-purple-50'],
        'buseta' => ['bg' => 'bg-pink-100', 'text' => 'text-pink-700', 'border' => 'border-pink-200', 'dark' => 'bg-pink-50'],
        'minivan' => ['bg' => 'bg-teal-100', 'text' => 'text-teal-700', 'border' => 'border-teal-200', 'dark' => 'bg-teal-50'],
        'automóvil' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'border' => 'border-red-200', 'dark' => 'bg-red-50'],
        'moto' => ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-700', 'border' => 'border-indigo-200', 'dark' => 'bg-indigo-50'],
        'bicicleta' => ['bg' => 'bg-lime-100', 'text' => 'text-lime-700', 'border' => 'border-lime-200', 'dark' => 'bg-lime-50'],
        'furgoneta' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'border' => 'border-amber-200', 'dark' => 'bg-amber-50'],
    ];
    
    $vehiculo_lower = strtolower($vehiculo);
    
    if (isset($colores_vehiculos[$vehiculo_lower])) {
        return $colores_vehiculos[$vehiculo_lower];
    }
    
    foreach ($colores_vehiculos as $key => $color) {
        if (strpos($vehiculo_lower, $key) !== false) {
            return $color;
        }
    }
    
    $colores_genericos = [
        ['bg' => 'bg-violet-100', 'text' => 'text-violet-700', 'border' => 'border-violet-200', 'dark' => 'bg-violet-50'],
        ['bg' => 'bg-cyan-100', 'text' => 'text-cyan-700', 'border' => 'border-cyan-200', 'dark' => 'bg-cyan-50'],
        ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200', 'dark' => 'bg-emerald-50'],
        ['bg' => 'bg-fuchsia-100', 'text' => 'text-fuchsia-700', 'border' => 'border-fuchsia-200', 'dark' => 'bg-fuchsia-50'],
        ['bg' => 'bg-rose-100', 'text' => 'text-rose-700', 'border' => 'border-rose-200', 'dark' => 'bg-rose-50'],
        ['bg' => 'bg-sky-100', 'text' => 'text-sky-700', 'border' => 'border-sky-200', 'dark' => 'bg-sky-50'],
    ];
    
    $hash = crc32($vehiculo);
    $color_index = abs($hash) % count($colores_genericos);
    
    return $colores_genericos[$color_index];
}

/* =======================================================
   🔹 PROCESAR POST (sin cambios)
======================================================= */
if (isset($_POST['crear_clasificacion'])) {
    $nombre_clasificacion = trim($conn->real_escape_string($_POST['nombre_clasificacion']));
    if (empty($nombre_clasificacion)) { echo "error: nombre vacío"; exit; }
    $nombre_columna = preg_replace('/[^a-zA-Z0-9_]/', '_', $nombre_clasificacion);
    $nombre_columna = strtolower($nombre_columna);
    if (crearNuevaColumnaTarifa($conn, $nombre_columna)) { echo "ok"; } else { echo "error: " . $conn->error; }
    exit;
}

if (isset($_POST['guardar_tarifa'])) {
    $empresa  = $conn->real_escape_string($_POST['empresa']);
    $vehiculo = $conn->real_escape_string($_POST['tipo_vehiculo']);
    $campo    = $conn->real_escape_string($_POST['campo']);
    $valor    = (float)$_POST['valor'];
    $campo = strtolower($campo);
    $campo = preg_replace('/[^a-z0-9_]/', '_', $campo);
    $columnas_tarifas = obtenerColumnasTarifas($conn);
    if (!in_array($campo, $columnas_tarifas)) { 
        if (crearNuevaColumnaTarifa($conn, $campo)) {
            $columnas_tarifas = obtenerColumnasTarifas($conn);
        } else {
            echo "error: no se pudo crear el campo '$campo'";
            exit;
        }
    }
    $conn->query("INSERT IGNORE INTO tarifas (empresa, tipo_vehiculo) VALUES ('$empresa', '$vehiculo')");
    $sql = "UPDATE tarifas SET `$campo` = $valor WHERE empresa='$empresa' AND tipo_vehiculo='$vehiculo'";
    echo $conn->query($sql) ? "ok" : "error: " . $conn->error;
    exit;
}

if (isset($_POST['guardar_clasificacion'])) {
    $ruta       = $conn->real_escape_string($_POST['ruta']);
    $vehiculo   = $conn->real_escape_string($_POST['tipo_vehiculo']);
    $clasif     = $conn->real_escape_string($_POST['clasificacion']);
    $clasif = strtolower($clasif);
    if ($clasif === '') {
        $sql = "DELETE FROM ruta_clasificacion WHERE ruta = '$ruta' AND tipo_vehiculo = '$vehiculo'";
    } else {
        $sql = "INSERT INTO ruta_clasificacion (ruta, tipo_vehiculo, clasificacion)
                VALUES ('$ruta', '$vehiculo', '$clasif')
                ON DUPLICATE KEY UPDATE clasificacion = VALUES(clasificacion)";
    }
    echo $conn->query($sql) ? "ok" : ("error: " . $conn->error);
    exit;
}

if (isset($_POST['guardar_columnas_seleccionadas'])) {
    $columnas = $_POST['columnas'] ?? [];
    $empresas = $_POST['empresas'] ?? "";
    $desde = $_GET['desde'] ?? "";
    $hasta = $_GET['hasta'] ?? "";
    $session_key = "columnas_seleccionadas_" . md5($empresas . $desde . $hasta);
    setcookie($session_key, json_encode($columnas), time() + (86400 * 7), "/");
    echo "ok";
    exit;
}

/* =======================================================
   🔹 Endpoint AJAX: viajes por conductor (sin cambios)
======================================================= */
if (isset($_GET['viajes_conductor'])) {
    $nombre  = $conn->real_escape_string($_GET['viajes_conductor']);
    $desde   = $_GET['desde'];
    $hasta   = $_GET['hasta'];
    $empresa = $_GET['empresa'] ?? "";

    $clasificaciones_disponibles = obtenerClasificacionesDisponibles($conn);
    $legend = [];
    foreach ($clasificaciones_disponibles as $clasif) {
        $estilo = obtenerEstiloClasificacion($clasif);
        $legend[$clasif] = [
            'label' => $estilo['label'],
            'badge' => "{$estilo['bg']} {$estilo['text']} border {$estilo['border']}",
            'row' => $estilo['row']
        ];
    }
    $legend['otro'] = ['label'=>'Sin clasificar', 'badge'=>'bg-gray-100 text-gray-700 border border-gray-200', 'row'=>'bg-gray-50/20'];

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

    if ($res && $res->num_rows > 0) {
        $counts = array_fill_keys(array_keys($legend), 0);
        $rutas_sin_clasificar = [];
        $total_sin_clasificar = 0;
        $rowsHTML = "";
        
        while ($r = $res->fetch_assoc()) {
            $ruta = (string)$r['ruta'];
            $vehiculo = $r['tipo_vehiculo'];
            
            $key = mb_strtolower(trim($ruta . '|' . $vehiculo), 'UTF-8');
            $cat = $clasif_rutas[$key] ?? 'otro';
            $cat = strtolower($cat);
            
            if ($cat === 'otro' || $cat === '') {
                $total_sin_clasificar++;
                $rutas_sin_clasificar[] = [
                    'ruta' => $ruta,
                    'vehiculo' => $vehiculo,
                    'fecha' => $r['fecha']
                ];
            }
            
            if ($cat !== 'otro' && !isset($legend[$cat])) {
                $estilo = obtenerEstiloClasificacion($cat);
                $legend[$cat] = [
                    'label' => $estilo['label'],
                    'badge' => "{$estilo['bg']} {$estilo['text']} border {$estilo['border']}",
                    'row' => $estilo['row']
                ];
                $counts[$cat] = 0;
            }

            $counts[$cat]++;

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

        echo "<div class='space-y-3'>";
        
        if ($total_sin_clasificar > 0) {
            echo "<div class='bg-amber-50 border border-amber-200 rounded-xl p-4 mb-3'>
                    <div class='flex items-center gap-2 mb-2'>
                        <span class='text-amber-600 font-bold text-lg'>⚠️</span>
                        <span class='font-semibold text-amber-800'>Este conductor tiene $total_sin_clasificar viaje(s) sin clasificar</span>
                    </div>
                    <div class='text-sm text-amber-700'>
                        <p class='mb-2'>Rutas sin clasificación:</p>";
            
            foreach (array_slice($rutas_sin_clasificar, 0, 5) as $rsc) {
                echo "<div class='flex items-center gap-2 mb-1'>
                        <span class='text-xs'>•</span>
                        <span>".htmlspecialchars($rsc['ruta'])." (".htmlspecialchars($rsc['vehiculo']).")</span>
                        <span class='text-xs text-amber-500'>".$rsc['fecha']."</span>
                      </div>";
            }
            
            if ($total_sin_clasificar > 5) {
                echo "<p class='text-xs text-amber-600 mt-1'>... y ".($total_sin_clasificar - 5)." más</p>";
            }
            
            echo "</div>
                  </div>";
        }
        
        echo "<div class='flex flex-wrap gap-2 text-xs' id='legendFilterBar'>";
        foreach (array_keys($legend) as $k) {
            if ($counts[$k] > 0) {
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
        }
        echo "</div>";

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
        echo "<p class='text-center text-gray-500 py-4'>No se encontraron viajes para este conductor en ese rango.</p>";
    }
    exit;
}

/* =======================================================
   🔹 Formulario inicial (con CHECKBOXES)
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
          <p class="text-center text-slate-500 mb-6">Selecciona el periodo y una o varias empresas.</p>
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
            
            <!-- EMPRESAS CON CHECKBOX (MÚLTIPLE SELECCIÓN) -->
            <div class="block">
              <span class="block text-sm font-medium mb-2">Empresas (selecciona una o varias)</span>
              <div class="space-y-2 max-h-60 overflow-y-auto border border-slate-200 rounded-xl p-3">
                <label class="flex items-center gap-2 p-2 hover:bg-slate-50 rounded-lg">
                  <input type="checkbox" name="empresas[]" value="" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                  <span class="text-sm font-medium">-- Todas --</span>
                </label>
                <?php foreach($empresas as $e): ?>
                  <label class="flex items-center gap-2 p-2 hover:bg-slate-50 rounded-lg">
                    <input type="checkbox" name="empresas[]" value="<?= htmlspecialchars($e) ?>" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm font-medium"><?= htmlspecialchars($e) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
              <p class="text-xs text-slate-500 mt-1">Puedes seleccionar múltiples empresas. Los datos se consolidarán en una sola tabla.</p>
            </div>
            
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
   🔹 Cálculo y armado de tablas DINÁMICO - AHORA CONSOLIDADO EN UNA SOLA TABLA
======================================================= */
$desde = $_GET['desde'];
$hasta = $_GET['hasta'];
$empresasSeleccionadas = $_GET['empresas'] ?? [];

// Si "Todas" está seleccionado o no hay selección, obtener todas las empresas
if (empty($empresasSeleccionadas) || in_array("", $empresasSeleccionadas)) {
    $resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
    $empresasSeleccionadas = [];
    if ($resEmp) while ($r = $resEmp->fetch_assoc()) $empresasSeleccionadas[] = $r['empresa'];
}

// Obtener datos dinámicos
$columnas_tarifas = obtenerColumnasTarifas($conn);
$clasificaciones_disponibles = obtenerClasificacionesDisponibles($conn);

// Cargar columnas seleccionadas desde cookie
$session_key = "columnas_seleccionadas_" . md5(implode(',', $empresasSeleccionadas) . $desde . $hasta);
$columnas_seleccionadas = [];

if (isset($_COOKIE[$session_key])) {
    $columnas_seleccionadas = json_decode($_COOKIE[$session_key], true);
} else {
    $columnas_seleccionadas = $clasificaciones_disponibles;
}

// Cargar clasificaciones de rutas desde BD
$clasif_rutas = [];
$resClasif = $conn->query("SELECT ruta, tipo_vehiculo, clasificacion FROM ruta_clasificacion");
if ($resClasif) {
    while ($r = $resClasif->fetch_assoc()) {
        $key = mb_strtolower(trim($r['ruta'] . '|' . $r['tipo_vehiculo']), 'UTF-8');
        $clasif_rutas[$key] = strtolower($r['clasificacion']);
    }
}

/* =======================================================
   🔹 CONSOLIDAR DATOS DE TODAS LAS EMPRESAS EN UNA SOLA ESTRUCTURA
   ======================================================= */
$datosConsolidados = []; // clave: nombre_conductor
$todosLosVehiculos = []; // para saber qué vehículos aparecen
$rutas_sin_clasificar_por_conductor = [];

foreach ($empresasSeleccionadas as $empresa) {
    $empresa = $conn->real_escape_string($empresa);
    
    $sql = "SELECT nombre, ruta, empresa, tipo_vehiculo, COALESCE(pago_parcial,0) AS pago_parcial
            FROM viajes
            WHERE fecha BETWEEN '$desde' AND '$hasta'
              AND empresa = '$empresa'";
    
    $res = $conn->query($sql);
    
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $nombre = $row['nombre'];
            $ruta = $row['ruta'];
            $vehiculo = $row['tipo_vehiculo'];
            $pagoParcial = (int)($row['pago_parcial'] ?? 0);
            $empresaActual = $row['empresa'];
            
            // Registrar vehículo
            if (!in_array($vehiculo, $todosLosVehiculos)) {
                $todosLosVehiculos[] = $vehiculo;
            }
            
            // Inicializar conductor si no existe
            if (!isset($datosConsolidados[$nombre])) {
                $datosConsolidados[$nombre] = [
                    "vehiculos" => [], // Para saber qué vehículos usa este conductor
                    "pagos_por_empresa" => [], // Para calcular pagado correctamente respetando empresas
                    "viajes_por_clasificacion" => [] // [clasificacion][empresa] = cantidad
                ];
            }
            
            // Registrar el vehículo del conductor (para mostrar en la tabla, usaremos el más común o el primero)
            if (!in_array($vehiculo, $datosConsolidados[$nombre]["vehiculos"])) {
                $datosConsolidados[$nombre]["vehiculos"][] = $vehiculo;
            }
            
            // Acumular pago parcial por empresa
            if (!isset($datosConsolidados[$nombre]["pagos_por_empresa"][$empresaActual])) {
                $datosConsolidados[$nombre]["pagos_por_empresa"][$empresaActual] = 0;
            }
            $datosConsolidados[$nombre]["pagos_por_empresa"][$empresaActual] += $pagoParcial;
            
            // Determinar clasificación de la ruta
            $keyRuta = mb_strtolower(trim($ruta . '|' . $vehiculo), 'UTF-8');
            $clasificacion_ruta = $clasif_rutas[$keyRuta] ?? '';
            
            // Registrar rutas sin clasificar
            if ($clasificacion_ruta === '' || $clasificacion_ruta === 'otro') {
                if (!isset($rutas_sin_clasificar_por_conductor[$nombre])) {
                    $rutas_sin_clasificar_por_conductor[$nombre] = [];
                }
                $ruta_key = $ruta . '|' . $vehiculo . '|' . $empresaActual;
                if (!in_array($ruta_key, $rutas_sin_clasificar_por_conductor[$nombre])) {
                    $rutas_sin_clasificar_por_conductor[$nombre][] = $ruta_key;
                }
            }
            
            // Acumular viajes por clasificación y por empresa
            if ($clasificacion_ruta !== '') {
                if (!isset($datosConsolidados[$nombre]["viajes_por_clasificacion"][$clasificacion_ruta])) {
                    $datosConsolidados[$nombre]["viajes_por_clasificacion"][$clasificacion_ruta] = [];
                }
                if (!isset($datosConsolidados[$nombre]["viajes_por_clasificacion"][$clasificacion_ruta][$empresaActual])) {
                    $datosConsolidados[$nombre]["viajes_por_clasificacion"][$clasificacion_ruta][$empresaActual] = 0;
                }
                $datosConsolidados[$nombre]["viajes_por_clasificacion"][$clasificacion_ruta][$empresaActual]++;
            }
        }
    }
}

// Determinar el vehículo principal de cada conductor (el más usado, o el primero)
$vehiculoPrincipal = [];
foreach ($datosConsolidados as $nombre => $info) {
    // Por ahora, tomamos el primer vehículo de la lista
    // En una versión más avanzada, podríamos contar cuál es el más frecuente
    $vehiculoPrincipal[$nombre] = !empty($info["vehiculos"]) ? $info["vehiculos"][0] : 'Desconocido';
}

// Calcular el total pagado por conductor (sumando todas las empresas)
$totalPagadoPorConductor = [];
foreach ($datosConsolidados as $nombre => $info) {
    $totalPagadoPorConductor[$nombre] = array_sum($info["pagos_por_empresa"] ?? []);
}

// Calcular rutas sin clasificar por conductor
$rutasSinClasificarCount = [];
foreach ($rutas_sin_clasificar_por_conductor as $nombre => $rutas) {
    $rutasSinClasificarCount[$nombre] = count($rutas);
}

// Obtener todas las empresas para el listado
$todasEmpresas = [];
$resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
if ($resEmp) while ($r = $resEmp->fetch_assoc()) $todasEmpresas[] = $r['empresa'];

// Tarifas guardadas (necesitamos todas las empresas seleccionadas)
$tarifas_guardadas = [];
if (!empty($empresasSeleccionadas)) {
    $empresasList = "'" . implode("','", array_map([$conn, 'real_escape_string'], $empresasSeleccionadas)) . "'";
    $resTarifas = $conn->query("SELECT * FROM tarifas WHERE empresa IN ($empresasList)");
    if ($resTarifas) {
        while ($r = $resTarifas->fetch_assoc()) {
            $tarifas_guardadas[$r['empresa']][$r['tipo_vehiculo']] = $r;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Liquidación de Conductores - Consolidado</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  /* ===== ESTILOS ORIGINALES (sin cambios) ===== */
  ::-webkit-scrollbar{height:10px;width:10px}
  ::-webkit-slider-thumb{background:#d1d5db;border-radius:999px}
  ::-webkit-slider-thumb:hover{background:#9ca3af}
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
  
  .alerta-sin-clasificar {
    animation: pulse-alerta 2s infinite;
  }
  
  @keyframes pulse-alerta {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
  }
  
  /* Bolitas flotantes */
  .floating-balls-container {
    position: fixed;
    left: 20px;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    flex-direction: column;
    gap: 15px;
    z-index: 9998;
  }
  
  .floating-ball {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 3px solid white;
    position: relative;
    z-index: 9999;
    overflow: hidden;
    user-select: none;
  }
  
  .floating-ball:hover {
    transform: scale(1.15) translateY(-2px);
    box-shadow: 0 12px 25px rgba(0, 0, 0, 0.3);
  }
  
  .floating-ball:active {
    transform: scale(0.95);
  }
  
  .ball-content {
    font-size: 24px;
    font-weight: bold;
    color: white;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    display: flex;
    align-items: center;
    justify-content: center;
  }
  
  .ball-tooltip {
    position: absolute;
    left: 70px;
    top: 50%;
    transform: translateY(-50%);
    background: white;
    color: #1e293b;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border: 1px solid #e2e8f0;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s;
    pointer-events: none;
    z-index: 10000;
  }
  
  .floating-ball:hover .ball-tooltip {
    opacity: 1;
    visibility: visible;
    left: 75px;
  }
  
  .ball-tarifas { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
  .ball-crear-clasif { background: linear-gradient(135deg, #10b981, #059669); }
  .ball-clasif-rutas { background: linear-gradient(135deg, #f59e0b, #d97706); }
  .ball-selector-columnas { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
  
  .side-panel-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.4);
    z-index: 9997;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s;
  }
  
  .side-panel-overlay.active {
    opacity: 1;
    visibility: visible;
  }
  
  .side-panel {
    position: fixed;
    left: -450px;
    top: 0;
    width: 420px;
    height: 100vh;
    background: white;
    box-shadow: 4px 0 25px rgba(0, 0, 0, 0.15);
    z-index: 9998;
    transition: left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    overflow-y: auto;
    overflow-x: hidden;
  }
  
  .side-panel.active {
    left: 0;
  }
  
  .side-panel-header {
    position: sticky;
    top: 0;
    background: white;
    border-bottom: 1px solid #e2e8f0;
    padding: 1.25rem;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  
  .side-panel-body {
    padding: 1.25rem;
    padding-bottom: 2rem;
  }
  
  .side-panel-close {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    color: #64748b;
  }
  
  .side-panel-close:hover {
    background: #e2e8f0;
    color: #1e293b;
  }
  
  .table-container-wrapper {
    transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    margin-left: 0;
  }
  
  .table-container-wrapper.with-panel {
    margin-left: 420px;
  }
  
  .ball-active {
    animation: pulse-ball 2s infinite;
    box-shadow: 0 0 0 8px rgba(59, 130, 246, 0.2);
  }
  
  @keyframes pulse-ball {
    0%, 100% { box-shadow: 0 8px 20px rgba(0,0,0,0.2), 0 0 0 0 rgba(59, 130, 246, 0.4); }
    50% { box-shadow: 0 8px 20px rgba(0,0,0,0.2), 0 0 0 12px rgba(59, 130, 246, 0); }
  }
  
  @keyframes fadeInDown {
    from {
      opacity: 0;
      transform: translateY(-20px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
  
  .animate-fade-in-down {
    animation: fadeInDown 0.3s ease-out;
  }

  .viajes-backdrop{ 
    position:fixed; 
    inset:0; 
    background:rgba(0,0,0,.45); 
    display:none; 
    align-items:center; 
    justify-content:center; 
    z-index:10000; 
  }
  .viajes-backdrop.show{ display:flex; }
  .viajes-card{ 
    width:min(720px,94vw); 
    max-height:90vh; 
    overflow:hidden; 
    border-radius:16px; 
    background:#fff;
    box-shadow:0 20px 60px rgba(0,0,0,.25); 
    border:1px solid #e5e7eb; 
  }
  .viajes-header{
    padding:14px 16px;
    border-bottom:1px solid #eef2f7
  }
  .viajes-body{
    padding:14px 16px;
    overflow:auto; 
    max-height:70vh
  }
  .viajes-close{
    padding:6px 10px; 
    border-radius:10px; 
    cursor:pointer;
  }
  .viajes-close:hover{
    background:#f3f4f6
  }

  .conductor-link{
    cursor:pointer; 
    color:#0d6efd; 
    text-decoration:underline;
  }

  .row-viaje:hover { background-color: #f8fafc; }
  .cat-completo { background-color: rgba(209, 250, 229, 0.1); }
  .cat-medio { background-color: rgba(254, 243, 199, 0.1); }
  .cat-extra { background-color: rgba(241, 245, 249, 0.1); }
  .cat-siapana { background-color: rgba(250, 232, 255, 0.1); }
  .cat-carrotanque { background-color: rgba(207, 250, 254, 0.1); }
  .cat-otro { background-color: rgba(243, 244, 246, 0.1); }
  
  .tarjeta-tarifa-acordeon {
    transition: all 0.3s ease;
  }
  
  .tarjeta-tarifa-acordeon:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  }
  
  .acordeon-header {
    transition: background-color 0.2s ease;
  }
  
  .acordeon-content {
    transition: all 0.3s ease;
    max-height: 0;
    overflow: hidden;
  }
  
  .acordeon-content.expanded {
    max-height: 2000px;
  }
  
  .acordeon-icon {
    transition: transform 0.3s ease;
  }
  
  .acordeon-icon.expanded {
    transform: rotate(90deg);
  }
  
  .fila-clasificada-completo {
    background-color: rgba(209, 250, 229, 0.3) !important;
    border-left: 4px solid #10b981 !important;
  }
  
  .fila-clasificada-medio {
    background-color: rgba(254, 243, 199, 0.3) !important;
    border-left: 4px solid #f59e0b !important;
  }
  
  .fila-clasificada-extra {
    background-color: rgba(241, 245, 249, 0.3) !important;
    border-left: 4px solid #64748b !important;
  }
  
  .fila-clasificada-siapana {
    background-color: rgba(250, 232, 255, 0.3) !important;
    border-left: 4px solid #d946ef !important;
  }
  
  .fila-clasificada-carrotanque {
    background-color: rgba(207, 250, 254, 0.3) !important;
    border-left: 4px solid #06b6d4 !important;
  }
  
  .fila-clasificada-riohacha {
    background-color: rgba(224, 231, 255, 0.3) !important;
    border-left: 4px solid #4f46e5 !important;
  }
  
  .fila-clasificada-pru {
    background-color: rgba(204, 251, 241, 0.3) !important;
    border-left: 4px solid #14b8a6 !important;
  }
  
  .fila-clasificada-maco {
    background-color: rgba(255, 228, 230, 0.3) !important;
    border-left: 4px solid #f43f5e !important;
  }
  
  .columna-checkbox-item {
    transition: all 0.2s ease;
  }
  
  .columna-checkbox-item:hover {
    background-color: #f8fafc;
  }
  
  .columna-checkbox-item.selected {
    background-color: #eff6ff;
    border-color: #3b82f6;
  }
  
  .checkbox-columna {
    width: 18px;
    height: 18px;
    border-radius: 4px;
    border: 2px solid #cbd5e1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
  }
  
  .checkbox-columna.checked {
    background-color: #3b82f6;
    border-color: #3b82f6;
  }
  
  .checkbox-columna.checked::after {
    content: "✓";
    color: white;
    font-size: 12px;
    font-weight: bold;
  }
  
  .columna-oculta {
    display: none !important;
  }
  
  .columna-visualizada {
    display: table-cell !important;
  }
  
  /* Totales generales destacados */
  .totales-generales {
    background: linear-gradient(135deg, #1e293b, #0f172a);
    color: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    margin-bottom: 24px;
  }
  
  .totales-generales .stat {
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(5px);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 12px;
    padding: 12px 20px;
  }
  
  /* Indicador de múltiples empresas en el vehículo */
  .multi-empresa-badge {
    font-size: 10px;
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
    border-radius: 12px;
    padding: 2px 6px;
    margin-left: 4px;
  }
  
  @media (max-width: 768px) {
    .floating-balls-container {
      bottom: 20px;
      top: auto;
      left: 50%;
      transform: translateX(-50%);
      flex-direction: row;
      gap: 10px;
    }
    
    .floating-ball {
      width: 50px;
      height: 50px;
    }
    
    .ball-content {
      font-size: 20px;
    }
    
    .side-panel {
      width: 90%;
      max-width: 400px;
      left: -100%;
    }
    
    .table-container-wrapper.with-panel {
      margin-left: 0;
    }
    
    .ball-tooltip {
      display: none;
    }
  }
</style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800">

  <!-- BOLITAS FLOTANTES (sin cambios) -->
  <div class="floating-balls-container">
    <div class="floating-ball ball-tarifas" id="ball-tarifas" data-panel="tarifas">
      <div class="ball-content">🚐</div>
      <div class="ball-tooltip">Tarifas por tipo de vehículo</div>
    </div>
    <div class="floating-ball ball-crear-clasif" id="ball-crear-clasif" data-panel="crear-clasif">
      <div class="ball-content">➕</div>
      <div class="ball-tooltip">Crear nueva clasificación</div>
    </div>
    <div class="floating-ball ball-clasif-rutas" id="ball-clasif-rutas" data-panel="clasif-rutas">
      <div class="ball-content">🧭</div>
      <div class="ball-tooltip">Clasificar rutas existentes</div>
    </div>
    <div class="floating-ball ball-selector-columnas" id="ball-selector-columnas" data-panel="selector-columnas">
      <div class="ball-content">📊</div>
      <div class="ball-tooltip">Seleccionar columnas</div>
    </div>
  </div>

  <!-- ===== HEADER MEJORADO CON SELECTOR DE EMPRESAS SIEMPRE VISIBLE ===== -->
  <header class="max-w-[1800px] mx-auto px-3 md:px-4 pt-6">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-5 py-4">
      
      <!-- Fila superior: Título y selector de empresas -->
      <div class="flex flex-col gap-4">
        
        <!-- Título y contador -->
        <div class="flex items-center justify-between">
          <h2 class="text-xl md:text-2xl font-bold">🪙 Liquidación de Conductores - CONSOLIDADO</h2>
          <span class="text-sm bg-blue-100 text-blue-700 px-3 py-1 rounded-full font-medium">
            📊 <?= count($empresasSeleccionadas) ?> empresa(s) · <?= count($datosConsolidados) ?> conductor(es)
          </span>
        </div>
        
        <!-- FORMULARIO COMPLETO (Fechas + Empresas con Checkboxes) - SIEMPRE VISIBLE -->
        <form method="get" class="space-y-4">
          
          <!-- Fila de fechas -->
          <div class="flex flex-col md:flex-row gap-3">
            <label class="flex-1">
              <span class="block text-xs font-medium text-slate-600 mb-1">Desde</span>
              <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" required
                     class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition">
            </label>
            
            <label class="flex-1">
              <span class="block text-xs font-medium text-slate-600 mb-1">Hasta</span>
              <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" required
                     class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition">
            </label>
          </div>
          
          <!-- SECCIÓN DE EMPRESAS -->
          <div class="block">
            <span class="block text-sm font-medium mb-2">Empresas (selecciona una o varias)</span>
            
            <div class="space-y-2 max-h-60 overflow-y-auto border border-slate-200 rounded-xl p-3 bg-white">
              <?php
              // Obtener todas las empresas (ya las tenemos en $todasEmpresas)
              ?>
              
              <!-- Opción "Todas" -->
              <label class="flex items-center gap-2 p-2 hover:bg-slate-50 rounded-lg cursor-pointer">
                <input type="checkbox" name="empresas[]" value="" 
                       <?= in_array("", $empresasSeleccionadas) ? 'checked' : '' ?>
                       class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                <span class="text-sm font-medium">🌐 -- Todas las empresas --</span>
              </label>
              
              <!-- Lista de empresas individuales -->
              <?php foreach($todasEmpresas as $emp): ?>
                <label class="flex items-center gap-2 p-2 hover:bg-slate-50 rounded-lg cursor-pointer">
                  <input type="checkbox" name="empresas[]" value="<?= htmlspecialchars($emp) ?>"
                         <?= in_array($emp, $empresasSeleccionadas) ? 'checked' : '' ?>
                         class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                  <span class="text-sm font-medium">🏢 <?= htmlspecialchars($emp) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
            
            <p class="text-xs text-slate-500 mt-2">
              💡 Los datos de todas las empresas seleccionadas se consolidan en UNA SOLA TABLA.
            </p>
          </div>
          
          <!-- Botón de aplicar filtros -->
          <div class="flex justify-end">
            <button type="submit" 
                    class="inline-flex items-center gap-2 rounded-xl bg-blue-600 text-white px-6 py-2.5 text-sm font-semibold hover:bg-blue-700 active:bg-blue-800 focus:ring-4 focus:ring-blue-200 transition">
              🔄 Aplicar filtros
            </button>
          </div>
        </form>
        
        <!-- Resumen de empresas seleccionadas (feedback visual) -->
        <div class="flex flex-wrap items-center gap-2 mt-2 pt-3 border-t border-slate-100">
          <span class="text-sm font-medium text-slate-600">Empresas seleccionadas:</span>
          <?php if (in_array("", $empresasSeleccionadas)): ?>
            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-purple-100 text-purple-700 text-xs font-medium border border-purple-200">
              🌐 TODAS LAS EMPRESAS
            </span>
          <?php else: ?>
            <?php foreach ($empresasSeleccionadas as $emp): ?>
              <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-medium border border-blue-200">
                🏢 <?= htmlspecialchars($emp) ?>
              </span>
            <?php endforeach; ?>
          <?php endif; ?>
          
          <span class="text-sm text-slate-500 ml-auto">
            📅 <?= htmlspecialchars($desde) ?> → <?= htmlspecialchars($hasta) ?>
          </span>
        </div>
        
      </div>
    </div>
  </header>

  <!-- Overlay -->
  <div class="side-panel-overlay" id="sidePanelOverlay"></div>

  <!-- Contenido principal -->
  <main class="max-w-[1800px] mx-auto px-3 md:px-4 py-6">
    <div class="table-container-wrapper" id="tableContainerWrapper">
      
      <!-- Totales generales -->
      <div class="totales-generales mb-6">
        <div class="flex items-center gap-3 mb-4">
          <span class="text-2xl">📊</span>
          <h3 class="text-xl font-bold">TOTALES CONSOLIDADOS</h3>
          <span class="bg-white/20 px-3 py-1 rounded-full text-sm"><?= count($empresasSeleccionadas) ?> empresas · <?= count($datosConsolidados) ?> conductores</span>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="stat">
            <div class="text-sm opacity-80">Viajes totales</div>
            <div class="text-2xl font-bold" id="total_viajes_general">0</div>
          </div>
          <div class="stat">
            <div class="text-sm opacity-80">Total a pagar</div>
            <div class="text-2xl font-bold" id="total_general_general">0</div>
          </div>
          <div class="stat">
            <div class="text-sm opacity-80">Pagado</div>
            <div class="text-2xl font-bold" id="total_pagado_general">0</div>
          </div>
          <div class="stat">
            <div class="text-sm opacity-80">Faltante</div>
            <div class="text-2xl font-bold" id="total_faltante_general">0</div>
          </div>
        </div>
      </div>

      <!-- Botón para selector de columnas -->
      <div class="mb-4 flex justify-end">
        <button onclick="togglePanel('selector-columnas')" 
                class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-gradient-to-r from-purple-500 to-indigo-500 text-white hover:from-purple-600 hover:to-indigo-600 transition shadow-md">
          <span>📊</span>
          <span class="text-sm font-medium">Seleccionar columnas</span>
        </button>
      </div>

      <!-- Buscador global -->
      <div class="mb-4 flex gap-3">
        <div class="buscar-container w-96">
          <input type="text" 
                 placeholder="Buscar conductor en todas las empresas..." 
                 class="buscador-global w-full rounded-xl border border-slate-300 px-4 py-3 pr-10 text-sm"
                 id="buscadorGlobal">
          <button class="buscar-clear-global buscar-clear" id="clearBuscador">✕</button>
        </div>
        <span class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3 py-2 text-blue-700 text-sm font-medium">
          <span id="conductoresVisibles"><?= count($datosConsolidados) ?></span>/<span id="conductoresTotales"><?= count($datosConsolidados) ?></span> conductores
        </span>
      </div>

      <!-- TABLA CONSOLIDADA ÚNICA -->
      <?php if (!empty($datosConsolidados)): ?>
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto rounded-xl border border-slate-200 max-h-[70vh]">
          <table class="w-full text-sm" id="tablaConsolidada">
            <thead class="bg-blue-600 text-white sticky top-0 z-20">
              <tr>
                <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 70px;">Estado</th>
                <th class="px-4 py-3 text-left sticky top-0 bg-blue-600" style="min-width: 220px;">Conductor</th>
                <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 150px;">Tipo Vehículo</th>
                
                <?php foreach ($clasificaciones_disponibles as $clasif): 
                  $estilo = obtenerEstiloClasificacion($clasif);
                  $abreviaturas = [
                      'completo' => 'COM', 'medio' => 'MED', 'extra' => 'EXT',
                      'carrotanque' => 'CTK', 'siapana' => 'SIA', 'riohacha' => 'RIO',
                      'pru' => 'PRU', 'maco' => 'MAC'
                  ];
                  $abreviatura = $abreviaturas[$clasif] ?? strtoupper(substr($clasif, 0, 3));
                  
                  $visible = in_array($clasif, $columnas_seleccionadas);
                  $clase_visibilidad = $visible ? 'columna-visualizada' : 'columna-oculta';
                  
                  $colorMap = [
                      'bg-emerald-100' => '#d1fae5', 'text-emerald-700' => '#047857',
                      'bg-amber-100' => '#fef3c7', 'text-amber-800' => '#92400e',
                      'bg-slate-200' => '#e2e8f0', 'text-slate-800' => '#1e293b',
                      'bg-fuchsia-100' => '#fae8ff', 'text-fuchsia-700' => '#a21caf',
                      'bg-cyan-100' => '#cffafe', 'text-cyan-800' => '#155e75',
                      'bg-indigo-100' => '#e0e7ff', 'text-indigo-700' => '#4338ca',
                      'bg-teal-100' => '#ccfbf1', 'text-teal-700' => '#0f766e',
                      'bg-rose-100' => '#ffe4e6', 'text-rose-700' => '#be123c',
                  ];
                  
                  $bg_color = $colorMap[$estilo['bg']] ?? '#f1f5f9';
                  $text_color = $colorMap[$estilo['text']] ?? '#1e293b';
                ?>
                <th class="px-4 py-3 text-center sticky top-0 <?= $clase_visibilidad ?> columna-tabla" 
                    data-columna="<?= htmlspecialchars($clasif) ?>"
                    style="min-width: 80px; background-color: <?= $bg_color ?>; color: <?= $text_color ?>; z-index: 19;">
                  <?= htmlspecialchars($abreviatura) ?>
                </th>
                <?php endforeach; ?>
                <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 140px;">Total</th>
                <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 120px;">Pagado</th>
                <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 100px;">Faltante</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white" id="tbodyConsolidado">
            <?php foreach ($datosConsolidados as $conductor => $info): 
              $vehiculo = $vehiculoPrincipal[$conductor] ?? 'Desconocido';
              $esMensual = (stripos($vehiculo, 'mensual') !== false);
              $claseVehiculo = $esMensual ? 'vehiculo-mensual' : '';
              $rutasSinClasificar = $rutasSinClasificarCount[$conductor] ?? 0;
              $color_vehiculo = obtenerColorVehiculo($vehiculo);
              
              // Calcular total pagado (suma de todas las empresas)
              $totalPagado = $totalPagadoPorConductor[$conductor] ?? 0;
              
              // Preparar data-atributos con los viajes por clasificación y empresa para el cálculo en JS
              $viajesData = [];
              foreach ($info["viajes_por_clasificacion"] as $clasif => $porEmpresa) {
                  foreach ($porEmpresa as $emp => $cantidad) {
                      $viajesData[] = $clasif . '|' . $emp . '|' . $cantidad;
                  }
              }
              $viajesDataStr = implode(',', $viajesData);
              
              // Calcular cantidad total por clasificación (para mostrar en la tabla)
              $totalesPorClasificacion = [];
              foreach ($info["viajes_por_clasificacion"] as $clasif => $porEmpresa) {
                  $totalesPorClasificacion[$clasif] = array_sum($porEmpresa);
              }
            ?>
              <tr data-conductor="<?= htmlspecialchars($conductor) ?>" 
                  data-conductor-normalizado="<?= htmlspecialchars(mb_strtolower($conductor)) ?>"
                  data-pagado="<?= $totalPagado ?>"
                  data-sin-clasificar="<?= $rutasSinClasificar ?>"
                  data-viajes-data="<?= htmlspecialchars($viajesDataStr) ?>"
                  data-vehiculo="<?= htmlspecialchars($vehiculo) ?>"
                  class="hover:bg-blue-50/40 transition-colors <?php echo $rutasSinClasificar > 0 ? 'alerta-sin-clasificar' : ''; ?> fila-conductor">
                
                <td class="px-4 py-3 text-center">
                  <?php if ($rutasSinClasificar > 0): ?>
                    <div class="flex flex-col items-center justify-center gap-1" title="<?= $rutasSinClasificar ?> ruta(s) sin clasificar">
                      <span class="text-amber-600 font-bold animate-pulse">⚠️</span>
                      <span class="text-xs bg-amber-100 text-amber-800 px-2 py-1 rounded-full font-bold">
                        <?= $rutasSinClasificar ?>
                      </span>
                    </div>
                  <?php else: ?>
                    <div class="flex flex-col items-center justify-center gap-1" title="Todas las rutas clasificadas">
                      <span class="text-emerald-600">✅</span>
                      <span class="text-xs bg-emerald-100 text-emerald-800 px-2 py-1 rounded-full font-bold">0</span>
                    </div>
                  <?php endif; ?>
                </td>
                
                <td class="px-4 py-3">
                  <button type="button"
                          class="conductor-link text-blue-700 hover:text-blue-900 underline underline-offset-2 transition flex items-center gap-2"
                          data-conductor="<?= htmlspecialchars($conductor) ?>"
                          onclick="abrirModalViajes('<?= htmlspecialchars($conductor) ?>', '')">
                    <?php if ($rutasSinClasificar > 0): ?>
                      <span class="text-amber-600">⚠️</span>
                    <?php endif; ?>
                    <?= htmlspecialchars($conductor) ?>
                  </button>
                  
                  <!-- Mostrar badge si trabajó en múltiples empresas -->
                  <?php if (count($info["pagos_por_empresa"]) > 1): ?>
                  <span class="multi-empresa-badge" title="Trabajó en <?= count($info["pagos_por_empresa"]) ?> empresas">
                    +<?= count($info["pagos_por_empresa"]) ?>
                  </span>
                  <?php endif; ?>
                </td>
                
                <td class="px-4 py-3 text-center">
                  <span class="inline-block <?= $claseVehiculo ?> px-3 py-1.5 rounded-lg text-xs font-medium border <?= $color_vehiculo['border'] ?> <?= $color_vehiculo['text'] ?> <?= $color_vehiculo['bg'] ?>">
                    <?= htmlspecialchars($vehiculo) ?>
                    <?php if ($esMensual): ?>
                      <span class="ml-1">📅</span>
                    <?php endif; ?>
                  </span>
                </td>
                
                <?php foreach ($clasificaciones_disponibles as $clasif): 
                  $estilo = obtenerEstiloClasificacion($clasif);
                  $cantidad = $totalesPorClasificacion[$clasif] ?? 0;
                  $visible = in_array($clasif, $columnas_seleccionadas);
                  $clase_visibilidad = $visible ? 'columna-visualizada' : 'columna-oculta';
                  
                  $colorMap = [
                      'bg-emerald-100' => '#f0fdf4', 'text-emerald-700' => '#047857',
                      'bg-amber-100' => '#fffbeb', 'text-amber-800' => '#92400e',
                      'bg-slate-200' => '#f8fafc', 'text-slate-800' => '#1e293b',
                      'bg-fuchsia-100' => '#fdf4ff', 'text-fuchsia-700' => '#a21caf',
                      'bg-cyan-100' => '#ecfeff', 'text-cyan-800' => '#155e75',
                      'bg-indigo-100' => '#eef2ff', 'text-indigo-700' => '#4338ca',
                      'bg-teal-100' => '#f0fdfa', 'text-teal-700' => '#0f766e',
                      'bg-rose-100' => '#fff1f2', 'text-rose-700' => '#be123c',
                  ];
                  
                  $bg_cell_color = $colorMap[$estilo['bg']] ?? '#f8fafc';
                  $text_cell_color = $colorMap[$estilo['text']] ?? '#1e293b';
                ?>
                <td class="px-4 py-3 text-center font-medium <?= $clase_visibilidad ?> columna-tabla" 
                    data-columna="<?= htmlspecialchars($clasif) ?>"
                    data-cantidad="<?= $cantidad ?>"
                    data-clasificacion="<?= htmlspecialchars($clasif) ?>"
                    style="min-width: 80px; background-color: <?= $bg_cell_color ?>; color: <?= $text_cell_color ?>;">
                  <?= $cantidad ?>
                </td>
                <?php endforeach; ?>

                <td class="px-4 py-3">
                  <input type="text"
                         class="totales w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-slate-50 outline-none"
                         readonly dir="ltr"
                         value="0">
                </td>
                <td class="px-4 py-3">
                  <input type="text"
                         class="pagado w-full rounded-xl border border-emerald-200 px-3 py-2 text-right bg-emerald-50 outline-none"
                         readonly dir="ltr"
                         value="<?= number_format($totalPagado, 0, ',', '.') ?>">
                </td>
                <td class="px-4 py-3">
                  <input type="text"
                         class="faltante w-full rounded-xl border border-rose-200 px-3 py-2 text-right bg-rose-50 outline-none"
                         readonly dir="ltr"
                         value="0">
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php else: ?>
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-12 text-center">
        <span class="text-6xl mb-4 block">📭</span>
        <h3 class="text-xl font-bold text-slate-700 mb-2">No hay datos para mostrar</h3>
        <p class="text-slate-500">No se encontraron viajes en el período seleccionado para las empresas elegidas.</p>
      </div>
      <?php endif; ?>
    </div>
  </main>

  <!-- Modal VIAJES -->
  <div id="viajesModal" class="viajes-backdrop">
    <div class="viajes-card">
      <div class="viajes-header">
        <div class="flex flex-col gap-2 w-full">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold flex items-center gap-2">
              🧳 Viajes — <span id="viajesTitle" class="font-normal"></span>
            </h3>
            <button class="viajes-close text-slate-600 hover:bg-slate-100 border border-slate-300 px-2 py-1 rounded-lg text-sm" id="viajesCloseBtn">
              ✕ Cerrar
            </button>
          </div>
          <div class="flex items-center gap-4 text-xs text-slate-500">
            <span id="viajesRango"></span>
            <span>•</span>
            <span id="viajesEmpresa">Todas las empresas</span>
          </div>
        </div>
      </div>
      <div class="viajes-body" id="viajesContent"></div>
    </div>
  </div>

  <script>
    // ===== VARIABLES GLOBALES =====
    const RANGO_DESDE = <?= json_encode($desde) ?>;
    const RANGO_HASTA = <?= json_encode($hasta) ?>;
    const EMPRESAS_SELECCIONADAS = <?= json_encode($empresasSeleccionadas) ?>;
    const CLASIFICACIONES_DISPONIBLES = <?= json_encode($clasificaciones_disponibles) ?>;
    
    // ===== SISTEMA DE BOLITAS Y PANELES (sin cambios) =====
    let activePanel = null;
    const panels = ['tarifas', 'crear-clasif', 'clasif-rutas', 'selector-columnas'];
    
    document.addEventListener('DOMContentLoaded', function() {
      panels.forEach(panelId => {
        const ball = document.getElementById(`ball-${panelId}`);
        const panel = document.getElementById(`panel-${panelId}`);
        const closeBtn = panel?.querySelector('.side-panel-close');
        const overlay = document.getElementById('sidePanelOverlay');
        const tableWrapper = document.getElementById('tableContainerWrapper');
        
        if (ball && panel) {
          ball.addEventListener('click', () => togglePanel(panelId));
        }
        
        if (closeBtn) {
          closeBtn.addEventListener('click', () => togglePanel(panelId));
        }
      });
      
      if (document.getElementById('sidePanelOverlay')) {
        document.getElementById('sidePanelOverlay').addEventListener('click', () => {
          if (activePanel) togglePanel(activePanel);
        });
      }
      
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && activePanel) togglePanel(activePanel);
      });
      
      colapsarTodosTarifas();
      inicializarColoresClasificacion();
      inicializarSeleccionColumnas();
      configurarEventosTarifas();
      configurarBuscadorGlobal();
      
      // Recalcular todo
      setTimeout(recalcularTodo, 100);
    });
    
    function togglePanel(panelId) {
      const ball = document.getElementById(`ball-${panelId}`);
      const panel = document.getElementById(`panel-${panelId}`);
      const overlay = document.getElementById('sidePanelOverlay');
      const tableWrapper = document.getElementById('tableContainerWrapper');
      
      if (!panel || !ball) return;
      
      if (activePanel === panelId) {
        panel.classList.remove('active');
        ball.classList.remove('ball-active');
        overlay?.classList.remove('active');
        tableWrapper?.classList.remove('with-panel');
        activePanel = null;
      } else {
        if (activePanel) {
          document.getElementById(`panel-${activePanel}`)?.classList.remove('active');
          document.getElementById(`ball-${activePanel}`)?.classList.remove('ball-active');
        }
        
        panel.classList.add('active');
        ball.classList.add('ball-active');
        overlay?.classList.add('active');
        tableWrapper?.classList.add('with-panel');
        activePanel = panelId;
      }
    }
    
    // ===== FUNCIONES DE ACORDEÓN =====
    function toggleAcordeon(vehiculoId) {
      const content = document.getElementById('content-' + vehiculoId);
      const icon = document.getElementById('icon-' + vehiculoId);
      if (content && icon) {
        if (content.classList.contains('expanded')) {
          content.classList.remove('expanded');
          icon.classList.remove('expanded');
          content.style.maxHeight = '0';
        } else {
          content.classList.add('expanded');
          icon.classList.add('expanded');
          content.style.maxHeight = content.scrollHeight + 'px';
        }
      }
    }
    
    function expandirTodosTarifas() {
      document.querySelectorAll('.acordeon-content').forEach(content => {
        if (!content.classList.contains('expanded')) {
          content.classList.add('expanded');
          content.style.maxHeight = content.scrollHeight + 'px';
          const vehiculoId = content.id.replace('content-', '');
          const icon = document.getElementById('icon-' + vehiculoId);
          if (icon) icon.classList.add('expanded');
        }
      });
    }
    
    function colapsarTodosTarifas() {
      document.querySelectorAll('.acordeon-content').forEach(content => {
        if (content.classList.contains('expanded')) {
          content.classList.remove('expanded');
          content.style.maxHeight = '0';
          const vehiculoId = content.id.replace('content-', '');
          const icon = document.getElementById('icon-' + vehiculoId);
          if (icon) icon.classList.remove('expanded');
        }
      });
    }
    
    // ===== CLASIFICACIÓN DE RUTAS =====
    function inicializarColoresClasificacion() {
      document.querySelectorAll('.fila-ruta').forEach(fila => {
        const select = fila.querySelector('.select-clasif-ruta');
        if (select) actualizarColorFila(select);
      });
    }
    
    function actualizarColorFila(selectElement) {
      const fila = selectElement.closest('tr');
      const clasificacion = selectElement.value.toLowerCase();
      const ruta = fila.dataset.ruta;
      const vehiculo = fila.dataset.vehiculo;
      
      fila.classList.forEach(className => {
        if (className.startsWith('fila-clasificada-')) {
          fila.classList.remove(className);
        }
      });
      
      fila.dataset.clasificacion = clasificacion;
      
      if (clasificacion) {
        fila.classList.add('fila-clasificada-' + clasificacion);
      }
      
      guardarClasificacionRuta(ruta, vehiculo, clasificacion);
    }
    
    function guardarClasificacionRuta(ruta, vehiculo, clasificacion) {
      fetch('<?= basename(__FILE__) ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          guardar_clasificacion: 1,
          ruta: ruta,
          tipo_vehiculo: vehiculo,
          clasificacion: clasificacion
        })
      })
      .then(r => r.text())
      .then(t => {
        if (t.trim() !== 'ok') console.error('Error guardando clasificación:', t);
      });
    }
    
    // ===== SELECCIÓN DE COLUMNAS =====
    let columnasSeleccionadas = <?= json_encode($columnas_seleccionadas) ?>;
    
    function inicializarSeleccionColumnas() {
      columnasSeleccionadas.forEach(columna => {
        const checkbox = document.getElementById('checkbox-' + columna);
        if (checkbox) checkbox.classList.add('checked');
        const item = document.querySelector('[data-columna="' + columna + '"]');
        if (item) item.classList.add('selected');
      });
      actualizarContadorColumnas();
      actualizarColumnasTabla();
    }
    
    function toggleColumna(columna) {
      const checkbox = document.getElementById('checkbox-' + columna);
      const item = document.querySelector('[data-columna="' + columna + '"]');
      
      if (columnasSeleccionadas.includes(columna)) {
        columnasSeleccionadas = columnasSeleccionadas.filter(c => c !== columna);
        if (checkbox) checkbox.classList.remove('checked');
        if (item) item.classList.remove('selected');
      } else {
        columnasSeleccionadas.push(columna);
        if (checkbox) checkbox.classList.add('checked');
        if (item) item.classList.add('selected');
      }
      
      actualizarContadorColumnas();
      actualizarColumnasTabla();
    }
    
    function seleccionarTodasColumnas() {
      columnasSeleccionadas = [];
      document.querySelectorAll('.columna-checkbox-item').forEach(item => {
        const columna = item.dataset.columna;
        columnasSeleccionadas.push(columna);
        const checkbox = document.getElementById('checkbox-' + columna);
        if (checkbox) checkbox.classList.add('checked');
        item.classList.add('selected');
      });
      actualizarContadorColumnas();
      actualizarColumnasTabla();
    }
    
    function deseleccionarTodasColumnas() {
      columnasSeleccionadas = [];
      document.querySelectorAll('.columna-checkbox-item').forEach(item => {
        const columna = item.dataset.columna;
        const checkbox = document.getElementById('checkbox-' + columna);
        if (checkbox) checkbox.classList.remove('checked');
        item.classList.remove('selected');
      });
      actualizarContadorColumnas();
      actualizarColumnasTabla();
    }
    
    function actualizarContadorColumnas() {
      const contadorPanel = document.getElementById('contador-seleccionadas-panel');
      if (contadorPanel) contadorPanel.textContent = columnasSeleccionadas.length;
    }
    
    function actualizarColumnasTabla() {
      document.querySelectorAll('.columna-tabla').forEach(columna => {
        const nombreColumna = columna.dataset.columna;
        if (columnasSeleccionadas.includes(nombreColumna)) {
          columna.classList.remove('columna-oculta');
          columna.classList.add('columna-visualizada');
        } else {
          columna.classList.remove('columna-visualizada');
          columna.classList.add('columna-oculta');
        }
      });
    }
    
    function guardarSeleccionColumnas() {
      const desde = RANGO_DESDE;
      const hasta = RANGO_HASTA;
      const empresas = EMPRESAS_SELECCIONADAS.join(',');
      
      fetch('<?= basename(__FILE__) ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          guardar_columnas_seleccionadas: 1,
          columnas: JSON.stringify(columnasSeleccionadas),
          empresas: empresas,
          desde: desde,
          hasta: hasta
        })
      })
      .then(r => r.text())
      .then(respuesta => {
        if (respuesta.trim() === 'ok') {
          mostrarNotificacion('✅ Selección de columnas guardada', 'success');
        } else {
          mostrarNotificacion('❌ Error al guardar selección', 'error');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('❌ Error de conexión', 'error');
      });
    }
    
    function mostrarNotificacion(mensaje, tipo) {
      const notificacion = document.createElement('div');
      notificacion.className = `fixed top-4 right-4 px-4 py-3 rounded-lg shadow-lg z-[10001] animate-fade-in-down ${
        tipo === 'success' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 
        'bg-rose-100 text-rose-800 border border-rose-200'
      }`;
      notificacion.innerHTML = `
        <div class="flex items-center gap-2">
          <span class="text-lg">${tipo === 'success' ? '✅' : '❌'}</span>
          <span class="font-medium">${mensaje}</span>
        </div>
      `;
      document.body.appendChild(notificacion);
      setTimeout(() => notificacion.remove(), 3000);
    }
    
    // ===== FUNCIONES DE TARIFAS =====
    function configurarEventosTarifas() {
      document.addEventListener('change', function(e) {
        if (e.target.matches('.tarifa-input')) {
          const input = e.target;
          const empresa = input.dataset.empresa;
          const tipoVehiculo = input.dataset.vehiculo;
          const campo = input.dataset.campo.toLowerCase();
          const valor = parseFloat(input.value) || 0;
          
          fetch('<?= basename(__FILE__) ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
              guardar_tarifa: 1,
              empresa: empresa,
              tipo_vehiculo: tipoVehiculo,
              campo: campo,
              valor: valor
            })
          })
          .then(r => r.text())
          .then(t => {
            if (t.trim() === 'ok') {
              input.defaultValue = input.value;
              recalcularTodo();
            } else {
              console.error('Error guardando tarifa:', t);
              input.value = input.defaultValue;
            }
          })
          .catch(error => {
            console.error('Error de conexión:', error);
            input.value = input.defaultValue;
          });
        }
      });
      
      document.querySelectorAll('.tarifa-input').forEach(input => {
        input.defaultValue = input.value;
      });
    }
    
    // ===== BUSCADOR GLOBAL =====
    function configurarBuscadorGlobal() {
      const input = document.getElementById('buscadorGlobal');
      const clearBtn = document.getElementById('clearBuscador');
      
      if (input) {
        input.addEventListener('input', filtrarGlobal);
      }
      
      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          input.value = '';
          filtrarGlobal();
          input.focus();
        });
      }
    }
    
    function filtrarGlobal() {
      const input = document.getElementById('buscadorGlobal');
      const textoBusqueda = input ? normalizarTexto(input.value) : '';
      const filas = document.querySelectorAll('#tbodyConsolidado tr');
      const clearBtn = document.getElementById('clearBuscador');
      
      let visibles = 0;
      
      filas.forEach(fila => {
        const nombreConductor = fila.querySelector('.conductor-link')?.textContent?.replace('⚠️', '').trim() || '';
        const nombreNormalizado = normalizarTexto(nombreConductor);
        
        if (textoBusqueda === '' || nombreNormalizado.includes(textoBusqueda)) {
          fila.style.display = '';
          visibles++;
        } else {
          fila.style.display = 'none';
        }
      });
      
      document.getElementById('conductoresVisibles').textContent = visibles;
      if (clearBtn) clearBtn.style.display = textoBusqueda === '' ? 'none' : 'block';
      
      recalcularTodo();
    }
    
    function normalizarTexto(texto) {
      return texto.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
    }
    
    // ===== FUNCIONES DE CÁLCULO =====
    function getTarifas() {
      const tarifas = {};
      document.querySelectorAll('.tarjeta-tarifa-acordeon').forEach(card => {
        const empresa = card.dataset.empresa;
        const vehiculo = card.dataset.vehiculo;
        
        if (!tarifas[empresa]) tarifas[empresa] = {};
        if (!tarifas[empresa][vehiculo]) tarifas[empresa][vehiculo] = {};
        
        card.querySelectorAll('input[data-campo]').forEach(input => {
          const campo = input.dataset.campo.toLowerCase();
          const valor = parseFloat(input.value) || 0;
          tarifas[empresa][vehiculo][campo] = valor;
        });
      });
      return tarifas;
    }
    
    function formatNumber(num) {
      return new Intl.NumberFormat('es-CO').format(num || 0);
    }
    
    function recalcularTodo() {
      const tarifas = getTarifas();
      const filas = document.querySelectorAll('#tbodyConsolidado tr');
      
      let totalViajesGlobal = 0;
      let totalPagadoGlobal = 0;
      let totalFaltanteGlobal = 0;
      
      filas.forEach(fila => {
        if (fila.style.display === 'none') return;
        
        const vehiculo = fila.dataset.vehiculo;
        const pagado = parseInt(fila.dataset.pagado || '0') || 0;
        const viajesData = fila.dataset.viajesData || '';
        
        // Parsear viajesData: formato "clasif|empresa|cantidad,clasif|empresa|cantidad,..."
        const viajes = viajesData.split(',').filter(item => item);
        
        let totalFila = 0;
        
        // Calcular total respetando empresa y clasificación
        viajes.forEach(item => {
          const partes = item.split('|');
          if (partes.length === 3) {
            const clasif = partes[0];
            const empresa = partes[1];
            const cantidad = parseInt(partes[2]) || 0;
            
            const tarifaEmpresa = tarifas[empresa]?.[vehiculo]?.[clasif] || 0;
            totalFila += cantidad * tarifaEmpresa;
          }
        });
        
        let faltante = totalFila - pagado;
        if (faltante < 0) faltante = 0;
        
        // Actualizar celdas
        const totalInput = fila.querySelector('input.totales');
        if (totalInput) totalInput.value = formatNumber(totalFila);
        
        const faltanteInput = fila.querySelector('input.faltante');
        if (faltanteInput) faltanteInput.value = formatNumber(faltante);
        
        totalViajesGlobal += totalFila;
        totalPagadoGlobal += pagado;
        totalFaltanteGlobal += faltante;
      });
      
      // Actualizar totales generales
      document.getElementById('total_viajes_general').textContent = formatNumber(totalViajesGlobal);
      document.getElementById('total_general_general').textContent = formatNumber(totalViajesGlobal);
      document.getElementById('total_pagado_general').textContent = formatNumber(totalPagadoGlobal);
      document.getElementById('total_faltante_general').textContent = formatNumber(totalFaltanteGlobal);
    }
    
    // ===== CREAR NUEVA CLASIFICACIÓN =====
    function crearYAsignarClasificacion() {
      const nombreClasif = document.getElementById('txt_nueva_clasificacion').value.trim();
      const patronRuta = document.getElementById('txt_patron_ruta').value.trim().toLowerCase();
      
      if (!nombreClasif) {
        alert('Escribe el nombre de la nueva clasificación.');
        return;
      }
      
      fetch('<?= basename(__FILE__) ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          crear_clasificacion: 1,
          nombre_clasificacion: nombreClasif
        })
      })
      .then(r => r.text())
      .then(respuesta => {
        if (respuesta.trim() === 'ok') {
          if (patronRuta) {
            const filas = document.querySelectorAll('.fila-ruta');
            let contador = 0;
            
            filas.forEach(row => {
              const ruta = row.dataset.ruta.toLowerCase();
              if (ruta.includes(patronRuta)) {
                const sel = row.querySelector('.select-clasif-ruta');
                sel.value = nombreClasif.toLowerCase();
                actualizarColorFila(sel);
                contador++;
              }
            });
            
            alert(`✅ Se creó "${nombreClasif}" y se aplicó a ${contador} rutas. Recarga la página.`);
          } else {
            alert(`✅ Se creó la clasificación "${nombreClasif}". Recarga la página.`);
          }
          
          document.getElementById('txt_nueva_clasificacion').value = '';
          document.getElementById('txt_patron_ruta').value = '';
        } else {
          alert('❌ Error: ' + respuesta);
        }
      })
      .catch(error => alert('❌ Error de conexión: ' + error));
    }
    
    // ===== MODAL DE VIAJES =====
    const viajesModal = document.getElementById('viajesModal');
    const viajesContent = document.getElementById('viajesContent');
    const viajesTitle = document.getElementById('viajesTitle');
    const viajesClose = document.getElementById('viajesCloseBtn');
    const viajesRango = document.getElementById('viajesRango');
    const viajesEmpresa = document.getElementById('viajesEmpresa');
    
    function abrirModalViajes(nombreConductor, empresa) {
      viajesRango.textContent = RANGO_DESDE + " → " + RANGO_HASTA;
      viajesEmpresa.textContent = empresa || "Todas las empresas";
      viajesTitle.textContent = nombreConductor;
      
      const qs = new URLSearchParams({
        viajes_conductor: nombreConductor,
        desde: RANGO_DESDE,
        hasta: RANGO_HASTA,
        empresa: ""  // Vacío para traer de todas las empresas seleccionadas
      });
      
      viajesContent.innerHTML = '<p class="text-center py-4 animate-pulse">Cargando viajes...</p>';
      viajesModal.classList.add('show');
      
      fetch('<?= basename(__FILE__) ?>?' + qs.toString())
        .then(r => r.text())
        .then(html => {
          viajesContent.innerHTML = html;
        })
        .catch(() => {
          viajesContent.innerHTML = '<p class="text-center text-rose-600">Error cargando viajes.</p>';
        });
    }
    
    function cerrarModalViajes() {
      viajesModal.classList.remove('show');
      viajesContent.innerHTML = '';
    }
    
    if (viajesClose) viajesClose.addEventListener('click', cerrarModalViajes);
    if (viajesModal) {
      viajesModal.addEventListener('click', (e) => {
        if (e.target === viajesModal) cerrarModalViajes();
      });
    }
    
    // Recalcular al cambiar algo
    document.addEventListener('input', function(e) {
      if (e.target.matches('.tarifa-input')) {
        recalcularTodo();
      }
    });
  </script>

<?php
// ===== PANELES (deben ir después del header pero antes del cierre de body) =====
?>

  <!-- Panel tarifas -->
  <div class="side-panel" id="panel-tarifas">
    <div class="side-panel-header">
      <h3 class="text-lg font-semibold flex items-center gap-2">
        <span>🚐 Tarifas por Tipo de Vehículo</span>
        <span class="text-xs text-slate-500">(<?= count($columnas_tarifas) ?> tipos)</span>
      </h3>
      <button class="side-panel-close" data-panel="tarifas">✕</button>
    </div>
    <div class="side-panel-body">
      <div class="flex justify-end gap-2 mb-4">
        <button onclick="expandirTodosTarifas()" 
                class="text-xs px-3 py-1.5 rounded-lg border border-green-300 hover:bg-green-50 transition text-green-600">
          Expandir todos
        </button>
        <button onclick="colapsarTodosTarifas()" 
                class="text-xs px-3 py-1.5 rounded-lg border border-amber-300 hover:bg-amber-50 transition text-amber-600">
          Colapsar todos
        </button>
      </div>
      
      <!-- Tarifas agrupadas por empresa -->
      <?php 
      // Obtener todos los vehículos únicos de todas las empresas seleccionadas
      $vehiculosPorEmpresa = [];
      foreach ($empresasSeleccionadas as $empresa) {
          $sqlVeh = "SELECT DISTINCT tipo_vehiculo FROM viajes WHERE empresa = '$empresa' AND fecha BETWEEN '$desde' AND '$hasta'";
          $resVeh = $conn->query($sqlVeh);
          if ($resVeh) {
              while ($r = $resVeh->fetch_assoc()) {
                  $vehiculosPorEmpresa[$empresa][] = $r['tipo_vehiculo'];
              }
          }
      }
      
      foreach ($empresasSeleccionadas as $empresa): 
        $vehiculosEmpresa = $vehiculosPorEmpresa[$empresa] ?? [];
        if (empty($vehiculosEmpresa)) continue;
      ?>
      <div class="mb-6">
        <h4 class="text-md font-bold mb-3 flex items-center gap-2 border-b pb-2">
          <span>🏢 <?= htmlspecialchars($empresa) ?></span>
        </h4>
        <div id="tarifas_grid_<?= md5($empresa) ?>" class="grid grid-cols-1 gap-3">
          <?php foreach ($vehiculosEmpresa as $veh):
            $color_vehiculo = obtenerColorVehiculo($veh);
            $t = $tarifas_guardadas[$empresa][$veh] ?? [];
            $veh_id = preg_replace('/[^a-z0-9]/i', '-', strtolower($veh . '-' . $empresa));
          ?>
          <div class="tarjeta-tarifa-acordeon rounded-xl border <?= $color_vehiculo['border'] ?> overflow-hidden shadow-sm"
               data-vehiculo="<?= htmlspecialchars($veh) ?>"
               data-empresa="<?= htmlspecialchars($empresa) ?>"
               id="acordeon-<?= $veh_id ?>"
               style="background-color: <?= str_replace('bg-', '#', $color_vehiculo['dark']) ?>;">
            
            <div class="acordeon-header flex items-center justify-between px-4 py-3.5 cursor-pointer transition <?= $color_vehiculo['bg'] ?> hover:opacity-90"
                 onclick="toggleAcordeon('<?= $veh_id ?>')"
                 style="background-color: <?= str_replace('bg-', '#', $color_vehiculo['bg']) ?>;">
              <div class="flex items-center gap-3">
                <span class="acordeon-icon text-lg transition-transform duration-300 <?= $color_vehiculo['text'] ?>" id="icon-<?= $veh_id ?>">▶️</span>
                <div>
                  <div class="text-base font-semibold <?= $color_vehiculo['text'] ?>">
                    <?= htmlspecialchars($veh) ?>
                  </div>
                  <div class="text-xs text-slate-500 mt-0.5">
                    <?= count($columnas_tarifas) ?> tipos de tarifas
                  </div>
                </div>
              </div>
              <span class="text-xs px-2 py-1 rounded-full <?= $color_vehiculo['text'] ?> border <?= $color_vehiculo['border'] ?> bg-white/80">
                Configurar
              </span>
            </div>
            
            <div class="acordeon-content px-4 py-3 border-t <?= $color_vehiculo['border'] ?> bg-white" id="content-<?= $veh_id ?>">
              <div class="space-y-3">
                <?php foreach ($columnas_tarifas as $columna): 
                  $valor = isset($t[$columna]) ? (float)$t[$columna] : 0;
                  $etiqueta = ucfirst($columna);
                  
                  $etiquetas_especiales = [
                      'completo' => 'Viaje Completo',
                      'medio' => 'Viaje Medio',
                      'extra' => 'Viaje Extra',
                      'carrotanque' => 'Carrotanque',
                      'siapana' => 'Siapana',
                      'riohacha' => 'Riohacha',
                      'pru' => 'Pru',
                      'maco' => 'Maco'
                  ];
                  
                  $etiqueta_final = $etiquetas_especiales[$columna] ?? $etiqueta;
                  $estilo_clasif = obtenerEstiloClasificacion($columna);
                ?>
                <label class="block">
                  <span class="block text-sm font-medium mb-1 <?= $estilo_clasif['text'] ?>">
                    <?= htmlspecialchars($etiqueta_final) ?>
                  </span>
                  <div class="relative">
                    <input type="number" step="1000" value="<?= $valor ?>"
                           data-campo="<?= htmlspecialchars($columna) ?>"
                           data-empresa="<?= htmlspecialchars($empresa) ?>"
                           data-vehiculo="<?= htmlspecialchars($veh) ?>"
                           class="w-full rounded-xl border <?= $estilo_clasif['border'] ?> px-3 py-2 pr-10 text-right bg-white outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition tarifa-input"
                           style="border-color: <?= str_replace('border-', '#', $estilo_clasif['border']) ?>;">
                    <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-sm font-semibold <?= $estilo_clasif['text'] ?>">
                      $
                    </span>
                  </div>
                </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
      
      <p class="text-xs text-slate-500 mt-4">
        Los cambios se guardan automáticamente al modificar cualquier valor.
      </p>
    </div>
  </div>
  
  <!-- Panel crear clasificación -->
  <div class="side-panel" id="panel-crear-clasif">
    <div class="side-panel-header">
      <h3 class="text-lg font-semibold flex items-center gap-2">
        <span>➕ Crear Nueva Clasificación</span>
      </h3>
      <button class="side-panel-close" data-panel="crear-clasif">✕</button>
    </div>
    <div class="side-panel-body">
      <p class="text-sm text-slate-600 mb-4">
        Crea una nueva clasificación. Se agregará a la tabla tarifas.
      </p>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-2">Nombre de la nueva clasificación</label>
          <input id="txt_nueva_clasificacion" type="text"
                 class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500"
                 placeholder="Ej: Premium, Nocturno, Express...">
        </div>
        <div>
          <label class="block text-sm font-medium mb-2">Texto que deben contener las rutas (opcional)</label>
          <input id="txt_patron_ruta" type="text"
                 class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500"
                 placeholder="Dejar vacío para solo crear la clasificación">
        </div>
        <button type="button"
                onclick="crearYAsignarClasificacion()"
                class="w-full inline-flex items-center justify-center rounded-xl bg-green-600 text-white px-4 py-3 text-sm font-semibold hover:bg-green-700 active:bg-green-800 focus:ring-4 focus:ring-green-200 transition">
          ⚙️ Crear y Aplicar
        </button>
      </div>
      <p class="text-xs text-slate-500 mt-4">
        La nueva clasificación se creará en la tabla tarifas. Vuelve a dar <strong>Filtrar</strong> para ver los cambios.
      </p>
    </div>
  </div>

  <!-- Panel clasificación rutas -->
  <div class="side-panel" id="panel-clasif-rutas">
    <div class="side-panel-header">
      <h3 class="text-lg font-semibold flex items-center gap-2">
        <span>🧭 Clasificar Rutas Existentes</span>
        <span class="text-xs text-slate-500"><?= count($rutasUnicas ?? []) ?> rutas</span>
      </h3>
      <button class="side-panel-close" data-panel="clasif-rutas">✕</button>
    </div>
    <div class="side-panel-body">
      <div class="max-h-[calc(100vh-180px)] overflow-y-auto border border-slate-200 rounded-xl">
        <?php
        // Obtener rutas únicas de todas las empresas seleccionadas
        $rutasUnicas = [];
        foreach ($empresasSeleccionadas as $empresa) {
            $sqlRutas = "SELECT DISTINCT ruta, tipo_vehiculo FROM viajes 
                         WHERE empresa = '$empresa' AND fecha BETWEEN '$desde' AND '$hasta'";
            $resRutas = $conn->query($sqlRutas);
            if ($resRutas) {
                while ($r = $resRutas->fetch_assoc()) {
                    $key = $r['ruta'] . '|' . $r['tipo_vehiculo'];
                    $clasificacion = $clasif_rutas[mb_strtolower(trim($r['ruta'] . '|' . $r['tipo_vehiculo']), 'UTF-8')] ?? '';
                    $rutasUnicas[$key] = [
                        'ruta' => $r['ruta'],
                        'vehiculo' => $r['tipo_vehiculo'],
                        'clasificacion' => $clasificacion
                    ];
                }
            }
        }
        ?>
        <table class="w-full text-sm">
          <thead class="bg-slate-100 text-slate-600 sticky top-0 z-10">
            <tr>
              <th class="px-3 py-2 text-left">Ruta</th>
              <th class="px-3 py-2 text-center">Vehículo</th>
              <th class="px-3 py-2 text-center">Clasificación</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100" id="tablaClasificacionRutas">
          <?php foreach($rutasUnicas as $info): 
            $clasificacion_actual = $info['clasificacion'] ?? '';
            $estilo = obtenerEstiloClasificacion($clasificacion_actual);
            $clase_fila = $clasificacion_actual ? 'fila-clasificada-' . $clasificacion_actual : '';
          ?>
            <tr class="fila-ruta hover:bg-slate-50 <?= $clase_fila ?>"
                data-ruta="<?= htmlspecialchars($info['ruta']) ?>"
                data-vehiculo="<?= htmlspecialchars($info['vehiculo']) ?>"
                data-clasificacion="<?= htmlspecialchars($clasificacion_actual) ?>">
              <td class="px-3 py-2 whitespace-nowrap text-left font-medium">
                <?= htmlspecialchars($info['ruta']) ?>
              </td>
              <td class="px-3 py-2 text-center">
                <?php 
                  $color_vehiculo = obtenerColorVehiculo($info['vehiculo']);
                ?>
                <span class="inline-block px-2 py-1 rounded-md text-xs font-medium <?= $color_vehiculo['bg'] ?> <?= $color_vehiculo['text'] ?> border <?= $color_vehiculo['border'] ?>">
                  <?= htmlspecialchars($info['vehiculo']) ?>
                </span>
              </td>
              <td class="px-3 py-2 text-center">
                <select class="select-clasif-ruta rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-100 w-full"
                        data-ruta="<?= htmlspecialchars($info['ruta']) ?>"
                        data-vehiculo="<?= htmlspecialchars($info['vehiculo']) ?>"
                        onchange="actualizarColorFila(this)">
                  <option value="">Sin clasificar</option>
                  <?php foreach ($clasificaciones_disponibles as $clasif): 
                    $estilo_opcion = obtenerEstiloClasificacion($clasif);
                  ?>
                  <option value="<?= htmlspecialchars($clasif) ?>" 
                          <?= $info['clasificacion']===$clasif ? 'selected' : '' ?>
                          style="background-color: <?= str_replace('bg-', '#', $estilo_opcion['bg']) ?>20; color: <?= str_replace('text-', '#', $estilo_opcion['text']) ?>;">
                    <?= htmlspecialchars(ucfirst($clasif)) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="text-xs text-slate-500 mt-4">
        Selecciona una clasificación para cada ruta. Los cambios se guardan automáticamente.
      </p>
    </div>
  </div>

  <!-- Panel selector columna -->
  <div class="side-panel" id="panel-selector-columnas">
    <div class="side-panel-header">
      <h3 class="text-lg font-semibold flex items-center gap-2">
        <span>📊 Seleccionar Columnas</span>
      </h3>
      <button class="side-panel-close" data-panel="selector-columnas">✕</button>
    </div>
    <div class="side-panel-body">
      <div class="flex flex-col gap-4">
        <div>
          <p class="text-sm text-slate-600 mb-3">
            Marca/desmarca las columnas que quieres ver en las tablas.
            <span id="contador-seleccionadas-panel" class="font-semibold text-blue-600"><?= count($columnas_seleccionadas) ?></span> de 
            <?= count($clasificaciones_disponibles) ?> seleccionadas
          </p>
        </div>
        
        <div class="flex flex-wrap gap-2">
          <button onclick="seleccionarTodasColumnas()" 
                  class="text-xs px-3 py-1.5 rounded-lg border border-green-300 bg-green-50 text-green-700 hover:bg-green-100 transition">
            ✅ Seleccionar todas
          </button>
          <button onclick="deseleccionarTodasColumnas()" 
                  class="text-xs px-3 py-1.5 rounded-lg border border-rose-300 bg-rose-50 text-rose-700 hover:bg-rose-100 transition">
            ❌ Deseleccionar todas
          </button>
          <button onclick="guardarSeleccionColumnas()" 
                  class="text-xs px-3 py-1.5 rounded-lg border border-blue-300 bg-blue-50 text-blue-700 hover:bg-blue-100 transition">
            💾 Guardar selección
          </button>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-[60vh] overflow-y-auto p-2 border border-slate-200 rounded-lg">
          <?php foreach ($clasificaciones_disponibles as $clasif): 
            $estilo = obtenerEstiloClasificacion($clasif);
            $seleccionada = in_array($clasif, $columnas_seleccionadas);
          ?>
          <div class="columna-checkbox-item flex items-center gap-2 p-3 border border-slate-200 rounded-lg cursor-pointer transition <?= $seleccionada ? 'selected' : '' ?>"
               data-columna="<?= htmlspecialchars($clasif) ?>"
               onclick="toggleColumna('<?= htmlspecialchars($clasif) ?>')">
            <div class="checkbox-columna <?= $seleccionada ? 'checked' : '' ?>" 
                 id="checkbox-<?= htmlspecialchars($clasif) ?>"></div>
            <div class="flex-1 flex flex-col">
              <span class="text-sm font-medium <?= $estilo['text'] ?>">
                <?= ucfirst($clasif) ?>
              </span>
              <span class="text-xs text-slate-500">Columna: <?= htmlspecialchars($clasif) ?></span>
            </div>
            <div class="w-3 h-3 rounded-full" style="background-color: <?= str_replace('bg-', '#', $estilo['bg']) ?>;"></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

</body>
</html>
<?php
$conn->close();
?>