<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

/* =======================================================
   🔹 FUNCIONES DINÁMICAS
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

/* =======================================================
   🔹 NUEVA FUNCIÓN: Colores únicos por tipo de vehículo
======================================================= */
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
   🔹 Crear nueva clasificación (AJAX)
======================================================= */
if (isset($_POST['crear_clasificacion'])) {
    $nombre_clasificacion = trim($conn->real_escape_string($_POST['nombre_clasificacion']));
    
    if (empty($nombre_clasificacion)) {
        echo "error: nombre vacío";
        exit;
    }
    
    $nombre_columna = preg_replace('/[^a-zA-Z0-9_]/', '_', $nombre_clasificacion);
    $nombre_columna = strtolower($nombre_columna);
    
    if (crearNuevaColumnaTarifa($conn, $nombre_columna)) {
        echo "ok";
    } else {
        echo "error: " . $conn->error;
    }
    exit;
}

/* =======================================================
   🔹 Guardar tarifas dinámicamente (AJAX)
======================================================= */
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
    
    if ($conn->query($sql)) {
        echo "ok";
    } else {
        echo "error: " . $conn->error;
    }
    exit;
}

/* =======================================================
   🔹 Guardar CLASIFICACIÓN de rutas (AJAX)
======================================================= */
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

/* =======================================================
   🔹 Guardar columnas seleccionadas (AJAX)
======================================================= */
if (isset($_POST['guardar_columnas_seleccionadas'])) {
    $columnas = $_POST['columnas'] ?? [];
    $empresa = $_GET['empresa'] ?? "";
    $desde = $_GET['desde'] ?? "";
    $hasta = $_GET['hasta'] ?? "";
    
    $session_key = "columnas_seleccionadas_" . md5($empresa . $desde . $hasta);
    setcookie($session_key, json_encode($columnas), time() + (86400 * 7), "/");
    
    echo "ok";
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
   🔹 Cargar datos para la vista
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
   🔹 Obtener datos para pasar a la vista
======================================================= */
$desde = $_GET['desde'];
$hasta = $_GET['hasta'];
$empresaFiltro = $_GET['empresa'] ?? "";

// Obtener datos dinámicos
$columnas_tarifas = obtenerColumnasTarifas($conn);
$clasificaciones_disponibles = obtenerClasificacionesDisponibles($conn);

// Cargar columnas seleccionadas desde cookie
$session_key = "columnas_seleccionadas_" . md5($empresaFiltro . $desde . $hasta);
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

// Traer viajes
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
$rutas_sin_clasificar_por_conductor = [];

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

        $clasificacion_ruta = $clasif_rutas[$keyRuta] ?? '';
        if ($clasificacion_ruta === '' || $clasificacion_ruta === 'otro') {
            if (!isset($rutas_sin_clasificar_por_conductor[$nombre])) {
                $rutas_sin_clasificar_por_conductor[$nombre] = [];
            }
            $ruta_key = $ruta . '|' . $vehiculo;
            if (!in_array($ruta_key, $rutas_sin_clasificar_por_conductor[$nombre])) {
                $rutas_sin_clasificar_por_conductor[$nombre][] = $ruta_key;
            }
        }

        if (!isset($datos[$nombre])) {
            $datos[$nombre] = [
                "vehiculo" => $vehiculo,
                "pagado"   => 0
            ];
            foreach ($clasificaciones_disponibles as $clasif) {
                $datos[$nombre][$clasif] = 0;
            }
        }

        $clasifRuta = $clasif_rutas[$keyRuta] ?? '';
        if ($clasifRuta !== '') {
            if (!isset($datos[$nombre][$clasifRuta])) {
                $datos[$nombre][$clasifRuta] = 0;
            }
            $datos[$nombre][$clasifRuta]++;
        }
    }
}

// Inyectar pago acumulado y rutas sin clasificar
foreach ($datos as $conductor => $info) {
    $datos[$conductor]["pagado"] = (int)($pagosConductor[$conductor] ?? 0);
    $datos[$conductor]["rutas_sin_clasificar"] = count($rutas_sin_clasificar_por_conductor[$conductor] ?? []);
}

// Empresas y tarifas
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

// Preparar datos para pasar a la vista
$datos_vista = [
    'datos' => $datos,
    'vehiculos' => $vehiculos,
    'rutasUnicas' => $rutasUnicas,
    'empresas' => $empresas,
    'empresaFiltro' => $empresaFiltro,
    'desde' => $desde,
    'hasta' => $hasta,
    'columnas_tarifas' => $columnas_tarifas,
    'clasificaciones_disponibles' => $clasificaciones_disponibles,
    'columnas_seleccionadas' => $columnas_seleccionadas,
    'tarifas_guardadas' => $tarifas_guardadas,
    'conn' => $conn
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Liquidación de Conductores</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  /* ===== ESTILOS ORIGINALES DE LAS BOLITAS Y PANELES ===== */
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
  
  /* Colores para viajes */
  .row-viaje:hover { background-color: #f8fafc; }
  .cat-completo { background-color: rgba(209, 250, 229, 0.1); }
  .cat-medio { background-color: rgba(254, 243, 199, 0.1); }
  .cat-extra { background-color: rgba(241, 245, 249, 0.1); }
  .cat-siapana { background-color: rgba(250, 232, 255, 0.1); }
  .cat-carrotanque { background-color: rgba(207, 250, 254, 0.1); }
  .cat-otro { background-color: rgba(243, 244, 246, 0.1); }
  
  /* ===== MODAL VIAJES ORIGINAL ===== */
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
</style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800">

  <!-- Encabezado CON FILTRO INTEGRADO -->
  <header class="max-w-[1800px] mx-auto px-3 md:px-4 pt-6">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-5 py-4">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-3">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between w-full gap-3">
          <div class="flex items-center gap-3">
            <h2 class="text-xl md:text-2xl font-bold">🪙 Liquidación de Conductores</h2>
            <?php if ($empresaFiltro !== ""): ?>
              <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-sm font-medium">
                🏢 <?= htmlspecialchars($empresaFiltro) ?>
              </span>
            <?php endif; ?>
          </div>
          
          <!-- FILTRO DE FECHA -->
          <form id="headerFilterForm" class="flex flex-col md:flex-row md:items-center gap-2" method="get">
            <div class="flex flex-col md:flex-row md:items-center gap-2">
              <label class="flex items-center gap-1">
                <span class="text-xs font-medium text-slate-600 whitespace-nowrap">Desde:</span>
                <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" required
                       class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500 transition">
              </label>
              <label class="flex items-center gap-1">
                <span class="text-xs font-medium text-slate-600 whitespace-nowrap">Hasta:</span>
                <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" required
                       class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500 transition">
              </label>
              <label class="flex items-center gap-1">
                <span class="text-xs font-medium text-slate-600 whitespace-nowrap">Empresa:</span>
                <select name="empresa"
                        class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500 transition min-w-[120px]">
                  <option value="">-- Todas --</option>
                  <?php foreach($empresas as $e): ?>
                    <option value="<?= htmlspecialchars($e) ?>" <?= $empresaFiltro==$e?'selected':'' ?>>
                      <?= htmlspecialchars($e) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <button type="submit" 
                      class="rounded-lg bg-blue-600 text-white px-4 py-1.5 text-sm font-semibold hover:bg-blue-700 active:bg-blue-800 focus:ring-2 focus:ring-blue-200 transition whitespace-nowrap">
                🔄 Aplicar
              </button>
            </div>
          </form>
        </div>
      </div>
      
      <!-- Información del periodo actual -->
      <div class="text-sm text-slate-600 flex items-center gap-2">
        <span class="font-medium">Periodo actual:</span>
        <span class="bg-slate-100 px-2 py-1 rounded-lg font-semibold">
          <?= htmlspecialchars($desde) ?> → <?= htmlspecialchars($hasta) ?>
        </span>
        <span class="mx-2">•</span>
        <span class="font-medium">Conductores:</span>
        <span class="bg-slate-100 px-2 py-1 rounded-lg font-semibold">
          <?= count($datos) ?>
        </span>
        <span class="mx-2">•</span>
        <span class="font-medium">Columnas visibles:</span>
        <span class="bg-slate-100 px-2 py-1 rounded-lg font-semibold">
          <span id="contador-columnas-visibles"><?= count($columnas_seleccionadas) ?></span>/<?= count($clasificaciones_disponibles) ?>
        </span>
      </div>
    </div>
  </header>

  <!-- ===== INCLUIR EL SISTEMA DE BOLITAS COMPLETO ===== -->
  <?php include 'bolitas_paneles.php'; ?>

  <!-- Contenido principal -->
  <main class="max-w-[1800px] mx-auto px-3 md:px-4 py-6">
    <div class="table-container-wrapper" id="tableContainerWrapper">
      
      <!-- ===== INCLUIR LA TABLA DE CONDUCTORES ===== -->
      <?php include 'tabla_conductores.php'; ?>
      
    </div>
  </main>

  <!-- ===== Modal VIAJES ===== -->
  <div id="viajesModal" class="viajes-backdrop">
    <div class="viajes-card">
      <div class="viajes-header">
        <div class="flex flex-col gap-2 w-full md:flex-row md:items-center md:justify-between">
          <div class="flex flex-col gap-1">
            <h3 class="text-lg font-semibold flex items-center gap-2">
              🧳 Viajes — <span id="viajesTitle" class="font-normal"></span>
            </h3>
            <div class="text-[11px] text-slate-500 leading-tight">
              <span id="viajesRango"></span>
              <span class="mx-1">•</span>
              <span id="viajesEmpresa"></span>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <label class="text-xs text-slate-600 whitespace-nowrap">Conductor:</label>
            <select id="viajesSelectConductor"
                    class="rounded-lg border border-slate-300 px-2 py-1 text-sm min-w-[200px] focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500">
            </select>
            <button class="viajes-close text-slate-600 hover:bg-slate-100 border border-slate-300 px-2 py-1 rounded-lg text-sm" id="viajesCloseBtn" title="Cerrar">
              ✕
            </button>
          </div>
        </div>
      </div>

      <div class="viajes-body" id="viajesContent"></div>
    </div>
  </div>

  <script>
    // ===== MODAL DE VIAJES =====
    const RANGO_DESDE = <?= json_encode($desde) ?>;
    const RANGO_HASTA = <?= json_encode($hasta) ?>;
    const RANGO_EMP   = <?= json_encode($empresaFiltro) ?>;

    const viajesModal            = document.getElementById('viajesModal');
    const viajesContent          = document.getElementById('viajesContent');
    const viajesTitle            = document.getElementById('viajesTitle');
    const viajesClose            = document.getElementById('viajesCloseBtn');
    const viajesSelectConductor  = document.getElementById('viajesSelectConductor');
    const viajesRango            = document.getElementById('viajesRango');
    const viajesEmpresa          = document.getElementById('viajesEmpresa');

    let viajesConductorActual = null;

    const CONDUCTORES_LIST = <?= json_encode(array_keys($datos), JSON_UNESCAPED_UNICODE); ?>;

    function initViajesSelect(selectedName) {
        viajesSelectConductor.innerHTML = "";
        CONDUCTORES_LIST.forEach(nombre => {
            const opt = document.createElement('option');
            opt.value = nombre;
            opt.textContent = nombre;
            if (nombre === selectedName) opt.selected = true;
            viajesSelectConductor.appendChild(opt);
        });
    }

    function loadViajes(nombre) {
        viajesContent.innerHTML = '<p class="text-center m-0 animate-pulse">Cargando…</p>';
        viajesConductorActual = nombre;
        viajesTitle.textContent = nombre;

        const qs = new URLSearchParams({
            viajes_conductor: nombre,
            desde: RANGO_DESDE,
            hasta: RANGO_HASTA,
            empresa: RANGO_EMP
        });

        fetch(window.location.pathname + '?' + qs.toString())
            .then(r => r.text())
            .then(html => {
                viajesContent.innerHTML = html;
                setTimeout(() => {
                  const pills = viajesContent.querySelectorAll('#legendFilterBar .legend-pill');
                  const rows  = viajesContent.querySelectorAll('#viajesTableBody .row-viaje');
                  
                  if (pills.length && rows.length) {
                    let activeCat = null;
                    
                    pills.forEach(p => {
                      p.addEventListener('click', () => {
                        const cat = p.getAttribute('data-tipo');
                        if (cat === activeCat) {
                          activeCat = null;
                        } else {
                          activeCat = cat;
                        }
                        
                        pills.forEach(p2 => {
                          const pcat2 = p2.getAttribute('data-tipo');
                          if (activeCat && pcat2 === activeCat) {
                            p2.classList.add('ring-2','ring-blue-500','ring-offset-1','ring-offset-white');
                          } else {
                            p2.classList.remove('ring-2','ring-blue-500','ring-offset-1','ring-offset-white');
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
                      });
                    });
                  }
                }, 100);
            })
            .catch(() => {
                viajesContent.innerHTML = '<p class="text-center text-rose-600">Error cargando viajes.</p>';
            });
    }

    function abrirModalViajes(nombreInicial){
        viajesRango.textContent   = RANGO_DESDE + " → " + RANGO_HASTA;
        viajesEmpresa.textContent = (RANGO_EMP && RANGO_EMP !== "") ? RANGO_EMP : "Todas las empresas";

        initViajesSelect(nombreInicial);

        viajesModal.classList.add('show');

        loadViajes(nombreInicial);
    }

    function cerrarModalViajes(){
        viajesModal.classList.remove('show');
        viajesContent.innerHTML = '';
        viajesConductorActual = null;
    }

    viajesClose.addEventListener('click', cerrarModalViajes);
    viajesModal.addEventListener('click', (e)=>{
        if(e.target===viajesModal) cerrarModalViajes();
    });

    viajesSelectConductor.addEventListener('change', ()=>{
        const nuevo = viajesSelectConductor.value;
        loadViajes(nuevo);
    });

    // ===== INICIALIZACIÓN COMPLETA ====
    
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('.conductor-link').forEach(btn=>{
        btn.addEventListener('click', (e)=>{
          e.preventDefault();
          const nombre = btn.textContent.trim().replace('⚠️', '').trim();
          abrirModalViajes(nombre);
        });
      });

      // Llamar a recalcular si existe
      if (typeof recalcular === 'function') {
        recalcular();
      }
      
      const totalRutasSinClasificar = <?= array_sum(array_column($datos, 'rutas_sin_clasificar')) ?>;
      if (totalRutasSinClasificar > 0) {
        // Verificar si la función existe antes de llamarla
        if (typeof mostrarResumenRutasSinClasificar === 'function') {
          mostrarResumenRutasSinClasificar();
        }
      }
    });
  </script>
</body>
</html>
<?php
$conn->close();
?>