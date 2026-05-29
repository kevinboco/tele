<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

/* =======================================================
   🔹 FUNCIONES DINÁMICAS (sin cambios)
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
    $desde = $_POST['desde'] ?? "";
    $hasta = $_POST['hasta'] ?? "";
    $session_key = "columnas_seleccionadas_" . md5($empresas . $desde . $hasta);
    setcookie($session_key, json_encode($columnas), time() + (86400 * 7), "/");
    echo "ok";
    exit;
}

/* =======================================================
   🔹 Endpoint AJAX: viajes por conductor (con filtro de empresas)
======================================================= */
if (isset($_GET['viajes_conductor'])) {
    $nombre  = $conn->real_escape_string($_GET['viajes_conductor']);
    $desde   = $_GET['desde'];
    $hasta   = $_GET['hasta'];
    $empresasFiltro = $_GET['empresas'] ?? "";

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
    
    if (!empty($empresasFiltro)) {
        $empresasArray = explode(',', $empresasFiltro);
        $empresasEscapadas = array_map(function($e) use ($conn) {
            return "'" . $conn->real_escape_string($e) . "'";
        }, $empresasArray);
        $sql .= " AND empresa IN (" . implode(',', $empresasEscapadas) . ")";
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
   🔹 SELECCIONAR EMPRESAS Y RANGO DE FECHAS (FORMULARIO INICIAL)
   ======================================================= */
$empresas = [];
$resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
if ($resEmp) while ($r = $resEmp->fetch_assoc()) $empresas[] = $r['empresa'];

// Verificar si tenemos fechas seleccionadas (en GET o desde la sesión)
$tieneFechas = isset($_GET['desde']) && isset($_GET['hasta']) && !empty($_GET['desde']) && !empty($_GET['hasta']);

if (!$tieneFechas) {
    // Mostrar formulario inicial CON selector de rango de fechas estilo calendario
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
      <title>Filtrar viajes - Rango de Fechas</title>
      <script src="https://cdn.tailwindcss.com"></script>
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
      <style>
        .fecha-rango-wrapper {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .fecha-input-group {
            flex: 1;
            min-width: 200px;
        }
        .fecha-rango-botones {
            display: flex;
            gap: 5px;
            align-items: flex-end;
        }
        .btn-fecha-rapida {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
            border-radius: 0.75rem;
            transition: all 0.2s;
        }
        .btn-fecha-rapida:hover {
            transform: translateY(-1px);
        }
        .flatpickr-calendar.rangeMode .flatpickr-day.selected.startRange,
        .flatpickr-calendar.rangeMode .flatpickr-day.selected.endRange {
            background: #3b82f6;
            border-color: #3b82f6;
        }
        .flatpickr-calendar.rangeMode .flatpickr-day.inRange {
            background: rgba(59, 130, 246, 0.2);
            border-color: transparent;
        }
      </style>
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-800">
      <div class="max-w-2xl mx-auto p-6">
        <div class="bg-white shadow-sm rounded-2xl p-6 border border-slate-200">
          <h2 class="text-2xl font-bold text-center mb-2">📅 Liquidación de Conductores</h2>
          <p class="text-center text-slate-500 mb-6">Selecciona el rango de fechas y las empresas</p>
          
          <form method="get" class="space-y-5" id="filtrosForm">
            <!-- SELECTOR DE RANGO DE FECHAS ESTILO CALENDARIO -->
            <div>
              <label class="block text-sm font-medium mb-2">📅 Rango de Fechas</label>
              <div class="fecha-rango-wrapper">
                <div class="fecha-input-group">
                  <input type="text" id="rangoFechas" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition"
                         placeholder="Selecciona rango de fechas">
                  <input type="hidden" name="desde" id="desdeInput" value="">
                  <input type="hidden" name="hasta" id="hastaInput" value="">
                </div>
                <div class="fecha-rango-botones">
                  <button type="button" class="btn-fecha-rapida bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300" data-rango="hoy">📅 Hoy</button>
                  <button type="button" class="btn-fecha-rapida bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300" data-rango="semana">📆 Esta semana</button>
                  <button type="button" class="btn-fecha-rapida bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300" data-rango="mes">📅 Este mes</button>
                  <button type="button" class="btn-fecha-rapida bg-rose-100 hover:bg-rose-200 text-rose-700 border border-rose-300" data-rango="limpiar">✕ Limpiar</button>
                </div>
              </div>
              <p class="text-xs text-slate-500 mt-2">
                💡 Haz clic en el calendario: primera fecha = desde, segunda fecha = hasta (se ordenan automáticamente)
              </p>
            </div>
            
            <!-- EMPRESAS CON CHECKBOX (MÚLTIPLE SELECCIÓN) -->
            <div>
              <span class="block text-sm font-medium mb-2">🏢 Empresas (selecciona una o varias)</span>
              <div class="space-y-2 max-h-60 overflow-y-auto border border-slate-200 rounded-xl p-3">
                <label class="flex items-center gap-2 p-2 hover:bg-slate-50 rounded-lg cursor-pointer">
                  <input type="checkbox" name="empresas[]" value="" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                  <span class="text-sm font-medium">🌐 -- Todas las empresas --</span>
                </label>
                <?php foreach($empresas as $e): ?>
                  <label class="flex items-center gap-2 p-2 hover:bg-slate-50 rounded-lg cursor-pointer">
                    <input type="checkbox" name="empresas[]" value="<?= htmlspecialchars($e) ?>" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                    <span class="text-sm font-medium">🏢 <?= htmlspecialchars($e) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
              <p class="text-xs text-slate-500 mt-2">
                💡 Puedes seleccionar múltiples empresas. Los datos se consolidarán en una sola tabla.
              </p>
            </div>
            
            <button type="submit" 
                    class="w-full rounded-xl bg-blue-600 text-white py-3 font-semibold shadow hover:bg-blue-700 active:bg-blue-800 focus:ring-4 focus:ring-blue-200 transition">
              🔍 Filtrar viajes
            </button>
          </form>
        </div>
      </div>
      
      <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
      <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
      <script>
        const desdeInput = document.getElementById('desdeInput');
        const hastaInput = document.getElementById('hastaInput');
        const rangoFechasInput = document.getElementById('rangoFechas');
        const filtrosForm = document.getElementById('filtrosForm');
        
        if (rangoFechasInput && desdeInput && hastaInput) {
          const dateRangePicker = flatpickr(rangoFechasInput, {
            mode: "range",
            dateFormat: "Y-m-d",
            locale: "es",
            altInput: true,
            altFormat: "d/m/Y",
            allowInput: true,
            onClose: function(selectedDates, dateStr, instance) {
              if (selectedDates.length === 2) {
                let date1 = selectedDates[0];
                let date2 = selectedDates[1];
                let fechaInicio = date1 < date2 ? date1 : date2;
                let fechaFin = date1 < date2 ? date2 : date1;
                desdeInput.value = fechaInicio.toISOString().split('T')[0];
                hastaInput.value = fechaFin.toISOString().split('T')[0];
                instance.setDate([fechaInicio, fechaFin], false);
              } else if (selectedDates.length === 1) {
                let fechaUnica = selectedDates[0];
                desdeInput.value = fechaUnica.toISOString().split('T')[0];
                hastaInput.value = '';
              } else if (selectedDates.length === 0) {
                desdeInput.value = '';
                hastaInput.value = '';
              }
            }
          });
          
          document.querySelectorAll('.btn-fecha-rapida').forEach(btn => {
            btn.addEventListener('click', function(e) {
              const rango = this.getAttribute('data-rango');
              const hoy = new Date();
              let fechaInicio = null;
              let fechaFin = null;
              
              switch(rango) {
                case 'hoy':
                  fechaInicio = hoy;
                  fechaFin = hoy;
                  break;
                case 'semana':
                  const diaSemana = hoy.getDay();
                  const diffLunes = diaSemana === 0 ? 6 : diaSemana - 1;
                  fechaInicio = new Date(hoy);
                  fechaInicio.setDate(hoy.getDate() - diffLunes);
                  fechaFin = new Date(fechaInicio);
                  fechaFin.setDate(fechaInicio.getDate() + 6);
                  break;
                case 'mes':
                  fechaInicio = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
                  fechaFin = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0);
                  break;
                case 'limpiar':
                  desdeInput.value = '';
                  hastaInput.value = '';
                  dateRangePicker.clear();
                  return;
              }
              
              if (fechaInicio && fechaFin) {
                desdeInput.value = fechaInicio.toISOString().split('T')[0];
                hastaInput.value = fechaFin.toISOString().split('T')[0];
                dateRangePicker.setDate([fechaInicio, fechaFin], false);
              }
            });
          });
        }
        
        // Prevenir envío si no hay fechas
        filtrosForm.addEventListener('submit', function(e) {
          if (!desdeInput.value || !hastaInput.value) {
            e.preventDefault();
            alert('❌ Por favor selecciona un rango de fechas válido (desde y hasta)');
          }
        });
      </script>
    </body>
    </html>
    <?php
    exit;
}

/* =======================================================
   🔹 Cálculo y armado de tablas DINÁMICO - CONSOLIDADO EN UNA SOLA TABLA
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

// Guardar empresas seleccionadas como string para pasar al modal
$empresasFiltroString = implode(',', $empresasSeleccionadas);

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
$datosConsolidados = [];
$todosLosVehiculos = [];
$rutas_sin_clasificar_detalle = [];

foreach ($empresasSeleccionadas as $empresa) {
    $empresa = $conn->real_escape_string($empresa);
    
    $sql = "SELECT nombre, ruta, empresa, tipo_vehiculo, fecha, COALESCE(pago_parcial,0) AS pago_parcial
            FROM viajes
            WHERE fecha BETWEEN '$desde' AND '$hasta'
              AND empresa = '$empresa'";
    
    $res = $conn->query($sql);
    
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $nombre = $row['nombre'];
            $ruta = $row['ruta'];
            $vehiculo = $row['tipo_vehiculo'];
            $fecha = $row['fecha'];
            $pagoParcial = (int)($row['pago_parcial'] ?? 0);
            $empresaActual = $row['empresa'];
            
            if (!in_array($vehiculo, $todosLosVehiculos)) {
                $todosLosVehiculos[] = $vehiculo;
            }
            
            if (!isset($datosConsolidados[$nombre])) {
                $datosConsolidados[$nombre] = [
                    "vehiculos" => [],
                    "pagos_por_empresa" => [],
                    "viajes_por_clasificacion" => []
                ];
            }
            
            if (!in_array($vehiculo, $datosConsolidados[$nombre]["vehiculos"])) {
                $datosConsolidados[$nombre]["vehiculos"][] = $vehiculo;
            }
            
            if (!isset($datosConsolidados[$nombre]["pagos_por_empresa"][$empresaActual])) {
                $datosConsolidados[$nombre]["pagos_por_empresa"][$empresaActual] = 0;
            }
            $datosConsolidados[$nombre]["pagos_por_empresa"][$empresaActual] += $pagoParcial;
            
            $keyRuta = mb_strtolower(trim($ruta . '|' . $vehiculo), 'UTF-8');
            $clasificacion_ruta = $clasif_rutas[$keyRuta] ?? '';
            
            if ($clasificacion_ruta === '' || $clasificacion_ruta === 'otro') {
                if (!isset($rutas_sin_clasificar_detalle[$nombre])) {
                    $rutas_sin_clasificar_detalle[$nombre] = [];
                }
                
                $detalle_ruta = [
                    'ruta' => $ruta,
                    'vehiculo' => $vehiculo,
                    'fecha' => $fecha,
                    'empresa' => $empresaActual
                ];
                
                $key_detalle = $ruta . '|' . $vehiculo . '|' . $fecha . '|' . $empresaActual;
                $existe = false;
                foreach ($rutas_sin_clasificar_detalle[$nombre] as $existente) {
                    $key_existente = $existente['ruta'] . '|' . $existente['vehiculo'] . '|' . $existente['fecha'] . '|' . $existente['empresa'];
                    if ($key_existente === $key_detalle) {
                        $existe = true;
                        break;
                    }
                }
                
                if (!$existe) {
                    $rutas_sin_clasificar_detalle[$nombre][] = $detalle_ruta;
                }
            }
            
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

$vehiculoPrincipal = [];
foreach ($datosConsolidados as $nombre => $info) {
    $vehiculoPrincipal[$nombre] = !empty($info["vehiculos"]) ? $info["vehiculos"][0] : 'Desconocido';
}

$totalPagadoPorConductor = [];
foreach ($datosConsolidados as $nombre => $info) {
    $totalPagadoPorConductor[$nombre] = array_sum($info["pagos_por_empresa"] ?? []);
}

$rutasSinClasificarCount = [];
foreach ($rutas_sin_clasificar_detalle as $nombre => $rutas) {
    $rutasSinClasificarCount[$nombre] = count($rutas);
}

$todasEmpresas = [];
$resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
if ($resEmp) while ($r = $resEmp->fetch_assoc()) $todasEmpresas[] = $r['empresa'];

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

// Detectar alertas
$alertas_sin_clasificar = $rutas_sin_clasificar_detalle;
$alertas_sin_tarifa = [];

foreach ($datosConsolidados as $conductor => $info) {
    foreach ($info["viajes_por_clasificacion"] as $clasif => $porEmpresa) {
        foreach ($porEmpresa as $empresa => $cantidad) {
            $vehiculo = $vehiculoPrincipal[$conductor] ?? 'Desconocido';
            $tarifa_valor = 0;
            
            if (isset($tarifas_guardadas[$empresa][$vehiculo][$clasif])) {
                $tarifa_valor = (float)$tarifas_guardadas[$empresa][$vehiculo][$clasif];
            }
            
            if ($tarifa_valor == 0) {
                $alertas_sin_tarifa[] = [
                    'conductor' => $conductor,
                    'empresa' => $empresa,
                    'vehiculo' => $vehiculo,
                    'clasificacion' => $clasif,
                    'cantidad_viajes' => $cantidad
                ];
            }
        }
    }
}

$alertas_sin_tarifa_unicas = [];
$visto = [];
foreach ($alertas_sin_tarifa as $alerta) {
    $key = $alerta['conductor'] . '|' . $alerta['empresa'] . '|' . $alerta['vehiculo'] . '|' . $alerta['clasificacion'];
    if (!in_array($key, $visto)) {
        $visto[] = $key;
        $alertas_sin_tarifa_unicas[] = $alerta;
    }
}
$alertas_sin_tarifa = $alertas_sin_tarifa_unicas;

// Obtener rutas únicas para el panel de clasificación
$rutasUnicas = [];
foreach ($empresasSeleccionadas as $empresa) {
    $sqlRutas = "SELECT DISTINCT ruta, tipo_vehiculo, empresa FROM viajes 
                 WHERE empresa = '$empresa' AND fecha BETWEEN '$desde' AND '$hasta'";
    $resRutas = $conn->query($sqlRutas);
    if ($resRutas) {
        while ($r = $resRutas->fetch_assoc()) {
            $key = $r['ruta'] . '|' . $r['tipo_vehiculo'] . '|' . $r['empresa'];
            $clasificacion = $clasif_rutas[mb_strtolower(trim($r['ruta'] . '|' . $r['tipo_vehiculo']), 'UTF-8')] ?? '';
            $rutasUnicas[$key] = [
                'ruta' => $r['ruta'],
                'vehiculo' => $r['tipo_vehiculo'],
                'empresa' => $r['empresa'],
                'clasificacion' => $clasificacion
            ];
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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
  ::-webkit-scrollbar { height: 10px; width: 10px; background: #f1f1f1; }
  ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 999px; border: 2px solid #f1f1f1; }
  ::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
  * { scrollbar-width: thin; scrollbar-color: #d1d5db #f1f1f1; }
  
  input[type=number]::-webkit-inner-spin-button,
  input[type=number]::-webkit-outer-spin-button{ -webkit-appearance: none; margin: 0; }
  
  .buscar-container { position: relative; }
  .buscar-clear { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #64748b; cursor: pointer; display: none; }
  .buscar-clear:hover { color: #475569; }
  
  .vehiculo-mensual { background-color: #fef3c7 !important; border: 1px solid #f59e0b !important; color: #92400e !important; font-weight: 600; }
  .alerta-sin-clasificar { animation: pulse-alerta 2s infinite; }
  @keyframes pulse-alerta { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
  
  .floating-balls-container { position: fixed; left: 50%; transform: translateX(-50%); bottom: 20px; display: flex; flex-direction: row; gap: 20px; z-index: 9998; }
  .floating-ball { width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: 3px solid white; position: relative; z-index: 9999; overflow: visible; user-select: none; }
  .floating-ball:hover { transform: scale(1.15) translateY(-5px); box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3); }
  .ball-content { font-size: 28px; font-weight: bold; color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.3); }
  .ball-tooltip { position: absolute; bottom: 80px; left: 50%; transform: translateX(-50%); background: #1e293b; color: white; padding: 8px 16px; border-radius: 30px; font-size: 14px; font-weight: 600; white-space: nowrap; box-shadow: 0 6px 16px rgba(0,0,0,0.2); border: 1px solid #334155; opacity: 0; visibility: hidden; transition: all 0.2s ease; pointer-events: none; z-index: 10000; }
  .floating-ball:hover .ball-tooltip { opacity: 1; visibility: visible; bottom: 90px; }
  .ball-tooltip::after { content: ''; position: absolute; top: 100%; left: 50%; transform: translateX(-50%); border-width: 8px; border-style: solid; border-color: #1e293b transparent transparent transparent; }
  .ball-tarifas { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
  .ball-crear-clasif { background: linear-gradient(135deg, #10b981, #059669); }
  .ball-clasif-rutas { background: linear-gradient(135deg, #f59e0b, #d97706); }
  .ball-selector-columnas { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
  
  .side-panel-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); z-index: 9997; opacity: 0; visibility: hidden; transition: all 0.3s; }
  .side-panel-overlay.active { opacity: 1; visibility: visible; }
  
  .side-panel { position: fixed; left: -100%; top: 0; width: auto; min-width: 500px; max-width: 90vw; height: 100vh; background: white; box-shadow: 4px 0 25px rgba(0, 0, 0, 0.15); z-index: 9998; transition: left 0.4s cubic-bezier(0.4, 0, 0.2, 1); overflow-y: auto; display: flex; flex-direction: column; }
  .side-panel.active { left: 0; }
  .side-panel-header { position: sticky; top: 0; background: white; border-bottom: 1px solid #e2e8f0; padding: 1.25rem; z-index: 10; flex-shrink: 0; }
  .side-panel-body { padding: 1.25rem; overflow-y: auto; flex: 1; }
  
  .table-container-wrapper { transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1); margin-left: 0; }
  .table-container-wrapper.with-panel { margin-left: min(550px, 90vw); }
  
  .ball-active { animation: pulse-ball 2s infinite; box-shadow: 0 0 0 8px rgba(59, 130, 246, 0.2); }
  @keyframes pulse-ball { 0%, 100% { box-shadow: 0 8px 20px rgba(0,0,0,0.2), 0 0 0 0 rgba(59, 130, 246, 0.4); } 50% { box-shadow: 0 8px 20px rgba(0,0,0,0.2), 0 0 0 12px rgba(59, 130, 246, 0); } }
  
  .viajes-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,.45); display:none; align-items:center; justify-content:center; z-index:10000; }
  .viajes-backdrop.show{ display:flex; }
  .viajes-card{ width:min(720px,94vw); max-height:90vh; overflow:hidden; border-radius:16px; background:#fff; box-shadow:0 20px 60px rgba(0,0,0,.25); border:1px solid #e5e7eb; }
  
  .empresa-mini-card { display: inline-flex; align-items: center; gap: 6px; background: linear-gradient(135deg, #f8fafc, #f1f5f9); border: 1px solid #cbd5e1; border-radius: 30px; padding: 6px 14px; font-size: 0.85rem; font-weight: 500; color: #1e293b; white-space: nowrap; }
  .select-clasif-ruta { border-radius: 30px; border: 1px solid #cbd5e1; padding: 8px 16px; font-size: 0.85rem; background-color: white; cursor: pointer; min-width: 150px; }
  
  .notificaciones-container { margin-bottom: 24px; display: flex; flex-direction: column; gap: 16px; }
  .notificacion { border-radius: 16px; padding: 20px; box-shadow: 0 8px 20px rgba(0,0,0,0.08); animation: slideDown 0.3s ease-out; }
  .notificacion-roja { background: linear-gradient(135deg, #fee2e2, #fecaca); border-left: 8px solid #dc2626; border: 1px solid #fca5a5; }
  .notificacion-amarilla { background: linear-gradient(135deg, #fef3c7, #fde68a); border-left: 8px solid #d97706; border: 1px solid #fcd34d; }
  @keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
  
  .totales-generales { background: linear-gradient(135deg, #1e293b, #0f172a); color: white; border-radius: 16px; padding: 20px; margin-bottom: 24px; }
  .totales-generales .stat { background: rgba(255,255,255,0.1); backdrop-filter: blur(5px); border-radius: 12px; padding: 12px 20px; }
  
  .fila-clasificada-completo { background-color: rgba(209, 250, 229, 0.3) !important; border-left: 4px solid #10b981 !important; }
  .fila-clasificada-medio { background-color: rgba(254, 243, 199, 0.3) !important; border-left: 4px solid #f59e0b !important; }
  .columna-oculta { display: none !important; }
  .columna-visualizada { display: table-cell !important; }
  
  @media (max-width: 768px) {
    .floating-ball { width: 55px; height: 55px; }
    .ball-content { font-size: 22px; }
    .side-panel { min-width: 95vw; }
    .table-container-wrapper.with-panel { margin-left: 0; }
  }
</style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800">

<div class="floating-balls-container">
  <div class="floating-ball ball-tarifas" id="ball-tarifas" data-panel="tarifas"><div class="ball-content">🚐</div><div class="ball-tooltip">Tarifas por tipo de vehículo</div></div>
  <div class="floating-ball ball-crear-clasif" id="ball-crear-clasif" data-panel="crear-clasif"><div class="ball-content">➕</div><div class="ball-tooltip">Crear nueva clasificación</div></div>
  <div class="floating-ball ball-clasif-rutas" id="ball-clasif-rutas" data-panel="clasif-rutas"><div class="ball-content">🧭</div><div class="ball-tooltip">Clasificar rutas existentes</div></div>
  <div class="floating-ball ball-selector-columnas" id="ball-selector-columnas" data-panel="selector-columnas"><div class="ball-content">📊</div><div class="ball-tooltip">Seleccionar columnas</div></div>
</div>

<div class="side-panel-overlay" id="sidePanelOverlay"></div>

<header class="max-w-[1800px] mx-auto px-3 md:px-4 pt-6">
  <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-5 py-4">
    <div class="flex flex-col gap-4">
      <div class="flex items-center justify-between">
        <h2 class="text-xl md:text-2xl font-bold">🪙 Liquidación de Conductores - CONSOLIDADO</h2>
        <span class="text-sm bg-blue-100 text-blue-700 px-3 py-1 rounded-full font-medium">
          📊 <?= count($empresasSeleccionadas) ?> empresa(s) · <?= count($datosConsolidados) ?> conductor(es)
        </span>
      </div>
      
      <form method="get" class="space-y-4">
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
        
        <div>
          <span class="block text-sm font-medium mb-2">Empresas (selecciona una o varias)</span>
          <div class="space-y-2 max-h-60 overflow-y-auto border border-slate-200 rounded-xl p-3 bg-white">
            <label class="flex items-center gap-2 p-2 hover:bg-slate-50 rounded-lg cursor-pointer">
              <input type="checkbox" name="empresas[]" value="" <?= in_array("", $empresasSeleccionadas) ? 'checked' : '' ?>
                     class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
              <span class="text-sm font-medium">🌐 -- Todas las empresas --</span>
            </label>
            <?php foreach($todasEmpresas as $emp): ?>
              <label class="flex items-center gap-2 p-2 hover:bg-slate-50 rounded-lg cursor-pointer">
                <input type="checkbox" name="empresas[]" value="<?= htmlspecialchars($emp) ?>"
                       <?= in_array($emp, $empresasSeleccionadas) ? 'checked' : '' ?>
                       class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                <span class="text-sm font-medium">🏢 <?= htmlspecialchars($emp) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        
        <div class="flex justify-end">
          <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 text-white px-6 py-2.5 text-sm font-semibold hover:bg-blue-700 transition">
            🔄 Aplicar filtros
          </button>
        </div>
      </form>
      
      <div class="flex flex-wrap items-center gap-2 mt-2 pt-3 border-t border-slate-100">
        <span class="text-sm font-medium text-slate-600">Empresas seleccionadas:</span>
        <?php if (in_array("", $empresasSeleccionadas)): ?>
          <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-purple-100 text-purple-700 text-xs font-medium">🌐 TODAS LAS EMPRESAS</span>
        <?php else: ?>
          <?php foreach ($empresasSeleccionadas as $emp): ?>
            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-medium">🏢 <?= htmlspecialchars($emp) ?></span>
          <?php endforeach; ?>
        <?php endif; ?>
        <span class="text-sm text-slate-500 ml-auto">📅 <?= htmlspecialchars($desde) ?> → <?= htmlspecialchars($hasta) ?></span>
      </div>
    </div>
  </div>
</header>

<main class="max-w-[1800px] mx-auto px-3 md:px-4 py-6">
  <div class="table-container-wrapper" id="tableContainerWrapper">
    
    <?php if (!empty($alertas_sin_clasificar) || !empty($alertas_sin_tarifa)): ?>
    <div class="notificaciones-container">
      <?php if (!empty($alertas_sin_clasificar)): ?>
      <div class="notificacion notificacion-roja">
        <div class="notificacion-titulo rojo flex items-center gap-2 font-bold text-red-800 mb-3">
          <span>🔴</span><span><?= count($alertas_sin_clasificar) ?> conductor(es) con rutas sin clasificar</span>
        </div>
        <?php foreach ($alertas_sin_clasificar as $conductor => $rutas): ?>
          <div class="bg-white/50 rounded-xl p-3 mb-2">
            <div class="font-semibold text-red-800">👤 <?= htmlspecialchars($conductor) ?> (<?= count($rutas) ?> rutas)</div>
            <div class="ml-4 mt-2 space-y-1">
              <?php foreach (array_slice($rutas, 0, 5) as $ruta): ?>
                <div class="text-sm">• <?= htmlspecialchars($ruta['ruta']) ?> (<?= htmlspecialchars($ruta['vehiculo']) ?>) - <?= htmlspecialchars($ruta['fecha']) ?> - 🏢 <?= htmlspecialchars($ruta['empresa']) ?></div>
              <?php endforeach; ?>
              <?php if (count($rutas) > 5): ?>
                <div class="text-xs text-red-600">... y <?= (count($rutas) - 5) ?> más</div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      
      <?php if (!empty($alertas_sin_tarifa)): ?>
      <div class="notificacion notificacion-amarilla">
        <div class="flex items-center gap-2 font-bold text-amber-800 mb-3">
          <span>🟡</span><span><?= count($alertas_sin_tarifa) ?> clasificaciones sin tarifa asignada (valor $0)</span>
        </div>
        <?php 
        $alertas_por_conductor = [];
        foreach ($alertas_sin_tarifa as $alerta) { $alertas_por_conductor[$alerta['conductor']][] = $alerta; }
        foreach ($alertas_por_conductor as $conductor => $alertas): ?>
          <div class="bg-white/50 rounded-xl p-3 mb-2">
            <div class="font-semibold text-amber-800">👤 <?= htmlspecialchars($conductor) ?></div>
            <div class="ml-4 mt-2 space-y-1">
              <?php foreach ($alertas as $alerta): ?>
                <div class="text-sm">• <?= htmlspecialchars(ucfirst($alerta['clasificacion'])) ?> - 🏢 <?= htmlspecialchars($alerta['empresa']) ?> - 🚐 <?= htmlspecialchars($alerta['vehiculo']) ?> (<?= $alerta['cantidad_viajes'] ?> viajes)</div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="totales-generales mb-6">
      <div class="flex items-center gap-3 mb-4">
        <span class="text-2xl">📊</span>
        <h3 class="text-xl font-bold">TOTALES CONSOLIDADOS</h3>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="stat"><div class="text-sm opacity-80">Total a pagar</div><div class="text-2xl font-bold" id="total_general_general">0</div></div>
        <div class="stat"><div class="text-sm opacity-80">Pagado</div><div class="text-2xl font-bold" id="total_pagado_general">0</div></div>
        <div class="stat"><div class="text-sm opacity-80">Faltante</div><div class="text-2xl font-bold" id="total_faltante_general">0</div></div>
      </div>
    </div>

    <div class="mb-4 flex justify-between items-center gap-3 flex-wrap">
      <div class="buscar-container w-96">
        <input type="text" placeholder="Buscar conductor..." class="buscador-global w-full rounded-xl border border-slate-300 px-4 py-3 pr-10 text-sm" id="buscadorGlobal">
        <button class="buscar-clear-global buscar-clear" id="clearBuscador">✕</button>
      </div>
      <div class="flex gap-2">
        <button onclick="togglePanel('selector-columnas')" class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-gradient-to-r from-purple-500 to-indigo-500 text-white hover:from-purple-600 hover:to-indigo-600 transition shadow-md text-sm">
          📊 Seleccionar columnas
        </button>
        <span class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-3 py-2 text-blue-700 text-sm font-medium">
          <span id="conductoresVisibles"><?= count($datosConsolidados) ?></span>/<span id="conductoresTotales"><?= count($datosConsolidados) ?></span>
        </span>
      </div>
    </div>

    <?php if (!empty($datosConsolidados)): ?>
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
      <div class="overflow-x-auto rounded-xl border border-slate-200 max-h-[70vh]">
        <table class="w-full text-sm" id="tablaConsolidada">
          <thead class="bg-blue-600 text-white sticky top-0 z-20">
            <tr>
              <th class="px-4 py-3 text-center sticky top-0 bg-blue-600 w-20">Estado</th>
              <th class="px-4 py-3 text-left sticky top-0 bg-blue-600">Conductor</th>
              <th class="px-4 py-3 text-center sticky top-0 bg-blue-600">Tipo Vehículo</th>
              <?php foreach ($clasificaciones_disponibles as $clasif): 
                $estilo = obtenerEstiloClasificacion($clasif);
                $visible = in_array($clasif, $columnas_seleccionadas);
                $clase_visibilidad = $visible ? 'columna-visualizada' : 'columna-oculta';
              ?>
                <th class="px-4 py-3 text-center sticky top-0 <?= $clase_visibilidad ?> columna-tabla <?= $estilo['bg'] ?> <?= $estilo['text'] ?>" data-columna="<?= htmlspecialchars($clasif) ?>">
                  <?= strtoupper(substr($clasif, 0, 3)) ?>
                </th>
              <?php endforeach; ?>
              <th class="px-4 py-3 text-center sticky top-0 bg-blue-600">Total</th>
              <th class="px-4 py-3 text-center sticky top-0 bg-blue-600">Pagado</th>
              <th class="px-4 py-3 text-center sticky top-0 bg-blue-600">Faltante</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white" id="tbodyConsolidado">
          <?php foreach ($datosConsolidados as $conductor => $info): 
            $vehiculo = $vehiculoPrincipal[$conductor] ?? 'Desconocido';
            $esMensual = (stripos($vehiculo, 'mensual') !== false);
            $claseVehiculo = $esMensual ? 'vehiculo-mensual' : '';
            $rutasSinClasificar = $rutasSinClasificarCount[$conductor] ?? 0;
            $color_vehiculo = obtenerColorVehiculo($vehiculo);
            $totalPagado = $totalPagadoPorConductor[$conductor] ?? 0;
            
            $viajesData = [];
            foreach ($info["viajes_por_clasificacion"] as $clasif => $porEmpresa) {
                foreach ($porEmpresa as $emp => $cantidad) {
                    $viajesData[] = $clasif . '|' . $emp . '|' . $cantidad;
                }
            }
            $viajesDataStr = implode(',', $viajesData);
            $totalesPorClasificacion = [];
            foreach ($info["viajes_por_clasificacion"] as $clasif => $porEmpresa) {
                $totalesPorClasificacion[$clasif] = array_sum($porEmpresa);
            }
          ?>
            <tr data-conductor="<?= htmlspecialchars($conductor) ?>" 
                data-conductor-normalizado="<?= htmlspecialchars(mb_strtolower($conductor)) ?>"
                data-pagado="<?= $totalPagado ?>"
                data-viajes-data="<?= htmlspecialchars($viajesDataStr) ?>"
                data-vehiculo="<?= htmlspecialchars($vehiculo) ?>"
                class="hover:bg-blue-50/40 transition-colors <?php echo $rutasSinClasificar > 0 ? 'alerta-sin-clasificar' : ''; ?> fila-conductor">
              
              <td class="px-4 py-3 text-center">
                <?php if ($rutasSinClasificar > 0): ?>
                  <div class="flex flex-col items-center" title="<?= $rutasSinClasificar ?> ruta(s) sin clasificar">
                    <span class="text-amber-600 font-bold animate-pulse text-xl">⚠️</span>
                    <span class="text-xs bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full font-bold"><?= $rutasSinClasificar ?></span>
                  </div>
                <?php else: ?>
                  <span class="text-emerald-600 text-xl">✅</span>
                <?php endif; ?>
              </td>
              
              <td class="px-4 py-3">
                <button type="button"
                        class="conductor-link text-blue-700 hover:text-blue-900 underline underline-offset-2 transition"
                        onclick="abrirModalViajes('<?= htmlspecialchars($conductor) ?>')">
                  <?= htmlspecialchars($conductor) ?>
                </button>
                <?php if (count($info["pagos_por_empresa"]) > 1): ?>
                  <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full ml-2" title="Trabajó en <?= count($info["pagos_por_empresa"]) ?> empresas">+<?= count($info["pagos_por_empresa"]) ?></span>
                <?php endif; ?>
              </td>
              
              <td class="px-4 py-3 text-center">
                <span class="inline-block <?= $claseVehiculo ?> px-3 py-1.5 rounded-lg text-xs font-medium border <?= $color_vehiculo['border'] ?> <?= $color_vehiculo['text'] ?> <?= $color_vehiculo['bg'] ?>">
                  <?= htmlspecialchars($vehiculo) ?>
                </span>
              </td>
              
              <?php foreach ($clasificaciones_disponibles as $clasif): 
                $cantidad = $totalesPorClasificacion[$clasif] ?? 0;
                $visible = in_array($clasif, $columnas_seleccionadas);
                $clase_visibilidad = $visible ? 'columna-visualizada' : 'columna-oculta';
                $estilo = obtenerEstiloClasificacion($clasif);
              ?>
                <td class="px-4 py-3 text-center font-medium <?= $clase_visibilidad ?> columna-tabla <?= $estilo['bg'] ?> <?= $estilo['text'] ?>" data-columna="<?= htmlspecialchars($clasif) ?>">
                  <?= $cantidad ?>
                </td>
              <?php endforeach; ?>

              <td class="px-4 py-3">
                <input type="text" class="totales w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-slate-50 outline-none" readonly value="0">
              </td>
              <td class="px-4 py-3">
                <input type="text" class="pagado w-full rounded-xl border border-emerald-200 px-3 py-2 text-right bg-emerald-50 outline-none" readonly value="<?= number_format($totalPagado, 0, ',', '.') ?>">
              </td>
              <td class="px-4 py-3">
                <input type="text" class="faltante w-full rounded-xl border border-rose-200 px-3 py-2 text-right bg-rose-50 outline-none" readonly value="0">
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

<div id="viajesModal" class="viajes-backdrop">
  <div class="viajes-card">
    <div class="flex justify-between items-center p-4 border-b">
      <h3 class="text-lg font-semibold">🧳 Viajes — <span id="viajesTitle"></span></h3>
      <button class="text-slate-500 hover:text-slate-700 text-xl" id="viajesCloseBtn">&times;</button>
    </div>
    <div class="p-4 max-h-[70vh] overflow-auto" id="viajesContent"></div>
  </div>
</div>

<script>
const RANGO_DESDE = <?= json_encode($desde) ?>;
const RANGO_HASTA = <?= json_encode($hasta) ?>;
const EMPRESAS_FILTRO_STRING = <?= json_encode($empresasFiltroString) ?>;
const CLASIFICACIONES_DISPONIBLES = <?= json_encode($clasificaciones_disponibles) ?>;

let activePanel = null;
const panels = ['tarifas', 'crear-clasif', 'clasif-rutas', 'selector-columnas'];

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
      const id = content.id.replace('content-', '');
      const icon = document.getElementById('icon-' + id);
      if (icon) icon.classList.add('expanded');
    }
  });
}

function colapsarTodosTarifas() {
  document.querySelectorAll('.acordeon-content').forEach(content => {
    if (content.classList.contains('expanded')) {
      content.classList.remove('expanded');
      content.style.maxHeight = '0';
      const id = content.id.replace('content-', '');
      const icon = document.getElementById('icon-' + id);
      if (icon) icon.classList.remove('expanded');
    }
  });
}

function actualizarColorFila(selectElement) {
  const fila = selectElement.closest('tr');
  const clasificacion = selectElement.value.toLowerCase();
  const ruta = fila.dataset.ruta;
  const vehiculo = fila.dataset.vehiculo;
  fila.classList.forEach(c => { if (c.startsWith('fila-clasificada-')) fila.classList.remove(c); });
  if (clasificacion) fila.classList.add('fila-clasificada-' + clasificacion);
  fetch('<?= basename(__FILE__) ?>', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ guardar_clasificacion: 1, ruta: ruta, tipo_vehiculo: vehiculo, clasificacion: clasificacion })
  });
}

let columnasSeleccionadas = <?= json_encode($columnas_seleccionadas) ?>;

function inicializarSeleccionColumnas() {
  columnasSeleccionadas.forEach(col => {
    const cb = document.getElementById('checkbox-' + col);
    if (cb) cb.classList.add('checked');
    const item = document.querySelector('[data-columna="' + col + '"]');
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
    const col = item.dataset.columna;
    columnasSeleccionadas.push(col);
    const cb = document.getElementById('checkbox-' + col);
    if (cb) cb.classList.add('checked');
    item.classList.add('selected');
  });
  actualizarContadorColumnas();
  actualizarColumnasTabla();
}

function deseleccionarTodasColumnas() {
  columnasSeleccionadas = [];
  document.querySelectorAll('.columna-checkbox-item').forEach(item => {
    const col = item.dataset.columna;
    const cb = document.getElementById('checkbox-' + col);
    if (cb) cb.classList.remove('checked');
    item.classList.remove('selected');
  });
  actualizarContadorColumnas();
  actualizarColumnasTabla();
}

function actualizarContadorColumnas() {
  const contador = document.getElementById('contador-seleccionadas-panel');
  if (contador) contador.textContent = columnasSeleccionadas.length;
}

function actualizarColumnasTabla() {
  document.querySelectorAll('.columna-tabla').forEach(col => {
    const nombre = col.dataset.columna;
    if (columnasSeleccionadas.includes(nombre)) {
      col.classList.remove('columna-oculta');
      col.classList.add('columna-visualizada');
    } else {
      col.classList.remove('columna-visualizada');
      col.classList.add('columna-oculta');
    }
  });
}

function guardarSeleccionColumnas() {
  fetch('<?= basename(__FILE__) ?>', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      guardar_columnas_seleccionadas: 1,
      columnas: JSON.stringify(columnasSeleccionadas),
      empresas: <?= json_encode(implode(',', $empresasSeleccionadas)) ?>,
      desde: RANGO_DESDE,
      hasta: RANGO_HASTA
    })
  }).then(r => r.text()).then(resp => { if (resp.trim() === 'ok') alert('✅ Selección guardada'); else alert('❌ Error'); });
}

function formatearNumeroMiles(valor) {
  let numeros = valor.replace(/\D/g, '');
  if (numeros === '') return '';
  return parseInt(numeros, 10).toLocaleString('es-CO').replace(/,/g, '.');
}

function configurarFormatoTarifas() {
  document.querySelectorAll('.tarifa-input').forEach(input => {
    let valorNumerico = input.value.replace(/\D/g, '');
    input.dataset.valorReal = valorNumerico || '0';
    if (valorNumerico && valorNumerico !== '0') input.value = formatearNumeroMiles(valorNumerico);
    input.addEventListener('input', function(e) {
      let soloNumeros = this.value.replace(/\D/g, '');
      this.dataset.valorReal = soloNumeros || '0';
      this.value = soloNumeros ? formatearNumeroMiles(soloNumeros) : '';
    });
    input.addEventListener('blur', function() {
      let real = this.dataset.valorReal || '0';
      this.value = real !== '0' ? formatearNumeroMiles(real) : '';
    });
  });
}

function getTarifas() {
  const tarifas = {};
  document.querySelectorAll('.tarjeta-tarifa-acordeon').forEach(card => {
    const empresa = card.dataset.empresa;
    const vehiculo = card.dataset.vehiculo;
    if (!tarifas[empresa]) tarifas[empresa] = {};
    if (!tarifas[empresa][vehiculo]) tarifas[empresa][vehiculo] = {};
    card.querySelectorAll('input[data-campo]').forEach(input => {
      const campo = input.dataset.campo.toLowerCase();
      const valor = parseInt(input.dataset.valorReal || '0', 10);
      tarifas[empresa][vehiculo][campo] = valor;
    });
  });
  return tarifas;
}

function recalcularTodo() {
  const tarifas = getTarifas();
  const filas = document.querySelectorAll('#tbodyConsolidado tr');
  let totalGeneral = 0, totalPagadoGlobal = 0, totalFaltanteGlobal = 0;
  filas.forEach(fila => {
    if (fila.style.display === 'none') return;
    const vehiculo = fila.dataset.vehiculo;
    const pagado = parseInt(fila.dataset.pagado || '0');
    const viajesData = fila.dataset.viajesData || '';
    const viajes = viajesData.split(',').filter(item => item);
    let totalFila = 0;
    viajes.forEach(item => {
      const partes = item.split('|');
      if (partes.length === 3) {
        const clasif = partes[0];
        const empresa = partes[1];
        const cantidad = parseInt(partes[2]) || 0;
        const tarifa = tarifas[empresa]?.[vehiculo]?.[clasif] || 0;
        totalFila += cantidad * tarifa;
      }
    });
    let faltante = totalFila - pagado;
    if (faltante < 0) faltante = 0;
    fila.querySelector('input.totales').value = totalFila.toLocaleString('es-CO');
    fila.querySelector('input.faltante').value = faltante.toLocaleString('es-CO');
    totalGeneral += totalFila;
    totalPagadoGlobal += pagado;
    totalFaltanteGlobal += faltante;
  });
  document.getElementById('total_general_general').textContent = totalGeneral.toLocaleString('es-CO');
  document.getElementById('total_pagado_general').textContent = totalPagadoGlobal.toLocaleString('es-CO');
  document.getElementById('total_faltante_general').textContent = totalFaltanteGlobal.toLocaleString('es-CO');
}

function filtrarGlobal() {
  const input = document.getElementById('buscadorGlobal');
  const texto = input ? input.value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '') : '';
  const filas = document.querySelectorAll('#tbodyConsolidado tr');
  let visibles = 0;
  filas.forEach(fila => {
    const nombre = fila.dataset.conductorNormalizado || '';
    if (texto === '' || nombre.includes(texto)) {
      fila.style.display = '';
      visibles++;
    } else {
      fila.style.display = 'none';
    }
  });
  document.getElementById('conductoresVisibles').textContent = visibles;
  const clearBtn = document.getElementById('clearBuscador');
  if (clearBtn) clearBtn.style.display = texto === '' ? 'none' : 'block';
  recalcularTodo();
}

function abrirModalViajes(nombreConductor) {
  const qs = new URLSearchParams({ viajes_conductor: nombreConductor, desde: RANGO_DESDE, hasta: RANGO_HASTA, empresas: EMPRESAS_FILTRO_STRING });
  document.getElementById('viajesTitle').textContent = nombreConductor;
  document.getElementById('viajesContent').innerHTML = '<p class="text-center py-4">Cargando...</p>';
  document.getElementById('viajesModal').classList.add('show');
  fetch('<?= basename(__FILE__) ?>?' + qs.toString())
    .then(r => r.text())
    .then(html => document.getElementById('viajesContent').innerHTML = html)
    .catch(() => document.getElementById('viajesContent').innerHTML = '<p class="text-center text-red-600">Error cargando viajes</p>');
}

function cerrarModalViajes() {
  document.getElementById('viajesModal').classList.remove('show');
}

function crearYAsignarClasificacion() {
  const nombre = document.getElementById('txt_nueva_clasificacion').value.trim();
  if (!nombre) { alert('Escribe el nombre de la clasificación'); return; }
  fetch('<?= basename(__FILE__) ?>', {
    method: 'POST',
    body: new URLSearchParams({ crear_clasificacion: 1, nombre_clasificacion: nombre })
  }).then(r => r.text()).then(resp => {
    if (resp.trim() === 'ok') {
      alert('✅ Clasificación "' + nombre + '" creada. Recarga la página.');
      document.getElementById('txt_nueva_clasificacion').value = '';
    } else alert('❌ Error: ' + resp);
  });
}

document.addEventListener('DOMContentLoaded', function() {
  panels.forEach(pid => {
    const ball = document.getElementById(`ball-${pid}`);
    const panel = document.getElementById(`panel-${pid}`);
    if (ball && panel) ball.addEventListener('click', () => togglePanel(pid));
    if (panel) panel.querySelector('.side-panel-close')?.addEventListener('click', () => togglePanel(pid));
  });
  document.getElementById('sidePanelOverlay')?.addEventListener('click', () => { if (activePanel) togglePanel(activePanel); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && activePanel) togglePanel(activePanel); });
  
  inicializarSeleccionColumnas();
  configurarFormatoTarifas();
  
  document.querySelectorAll('.tarifa-input').forEach(input => {
    input.addEventListener('change', function() {
      const empresa = this.dataset.empresa;
      const vehiculo = this.dataset.vehiculo;
      const campo = this.dataset.campo;
      const valor = parseInt(this.dataset.valorReal || '0', 10);
      fetch('<?= basename(__FILE__) ?>', {
        method: 'POST',
        body: new URLSearchParams({ guardar_tarifa: 1, empresa: empresa, tipo_vehiculo: vehiculo, campo: campo, valor: valor })
      }).then(() => recalcularTodo());
    });
  });
  
  const buscador = document.getElementById('buscadorGlobal');
  if (buscador) buscador.addEventListener('input', filtrarGlobal);
  document.getElementById('clearBuscador')?.addEventListener('click', () => {
    if (buscador) { buscador.value = ''; filtrarGlobal(); }
  });
  document.getElementById('viajesCloseBtn')?.addEventListener('click', cerrarModalViajes);
  document.getElementById('viajesModal')?.addEventListener('click', (e) => { if (e.target === document.getElementById('viajesModal')) cerrarModalViajes(); });
  
  recalcularTodo();
});
</script>

<!-- PANELES LATERALES -->
<div class="side-panel" id="panel-tarifas">
  <div class="side-panel-header flex justify-between items-center">
    <h3 class="text-lg font-semibold">🚐 Tarifas por Tipo de Vehículo</h3>
    <button class="side-panel-close text-slate-500 hover:text-slate-700 text-xl">&times;</button>
  </div>
  <div class="side-panel-body">
    <div class="flex justify-end gap-2 mb-4">
      <button onclick="expandirTodosTarifas()" class="text-xs px-3 py-1.5 rounded-lg border border-green-300 hover:bg-green-50">Expandir todos</button>
      <button onclick="colapsarTodosTarifas()" class="text-xs px-3 py-1.5 rounded-lg border border-amber-300 hover:bg-amber-50">Colapsar todos</button>
    </div>
    <?php foreach ($empresasSeleccionadas as $empresa):
      $vehiculosEmpresa = [];
      $sqlVeh = "SELECT DISTINCT tipo_vehiculo FROM viajes WHERE empresa = '$empresa' AND fecha BETWEEN '$desde' AND '$hasta'";
      $resVeh = $conn->query($sqlVeh);
      if ($resVeh) while ($r = $resVeh->fetch_assoc()) $vehiculosEmpresa[] = $r['tipo_vehiculo'];
      if (empty($vehiculosEmpresa)) continue;
    ?>
      <div class="mb-6">
        <h4 class="text-md font-bold mb-3 border-b pb-2">🏢 <?= htmlspecialchars($empresa) ?></h4>
        <?php foreach ($vehiculosEmpresa as $veh):
          $color_vehiculo = obtenerColorVehiculo($veh);
          $t = $tarifas_guardadas[$empresa][$veh] ?? [];
          $veh_id = preg_replace('/[^a-z0-9]/i', '-', strtolower($veh . '-' . $empresa));
        ?>
          <div class="tarjeta-tarifa-acordeon rounded-xl border <?= $color_vehiculo['border'] ?> overflow-hidden shadow-sm mb-3" data-vehiculo="<?= htmlspecialchars($veh) ?>" data-empresa="<?= htmlspecialchars($empresa) ?>">
            <div class="acordeon-header flex items-center justify-between px-4 py-3 cursor-pointer <?= $color_vehiculo['bg'] ?> hover:opacity-90" onclick="toggleAcordeon('<?= $veh_id ?>')">
              <div class="flex items-center gap-3">
                <span class="acordeon-icon text-lg transition-transform" id="icon-<?= $veh_id ?>">▶️</span>
                <span class="font-semibold <?= $color_vehiculo['text'] ?>"><?= htmlspecialchars($veh) ?></span>
              </div>
            </div>
            <div class="acordeon-content px-4 py-3 border-t <?= $color_vehiculo['border'] ?> bg-white" id="content-<?= $veh_id ?>" style="max-height:0; overflow:hidden; transition:all 0.3s ease">
              <div class="space-y-3">
                <?php foreach ($columnas_tarifas as $columna): 
                  $valor = isset($t[$columna]) ? (float)$t[$columna] : 0;
                  $estilo_clasif = obtenerEstiloClasificacion($columna);
                ?>
                  <label class="block">
                    <span class="block text-sm font-medium mb-1 <?= $estilo_clasif['text'] ?>"><?= ucfirst($columna) ?></span>
                    <input type="text" value="<?= number_format($valor, 0, ',', '.') ?>"
                           data-campo="<?= htmlspecialchars($columna) ?>"
                           data-empresa="<?= htmlspecialchars($empresa) ?>"
                           data-vehiculo="<?= htmlspecialchars($veh) ?>"
                           class="w-full rounded-xl border <?= $estilo_clasif['border'] ?> px-3 py-2 text-right bg-white outline-none tarifa-input">
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="side-panel" id="panel-crear-clasif">
  <div class="side-panel-header flex justify-between items-center">
    <h3 class="text-lg font-semibold">➕ Crear Nueva Clasificación</h3>
    <button class="side-panel-close text-slate-500 hover:text-slate-700 text-xl">&times;</button>
  </div>
  <div class="side-panel-body">
    <div class="space-y-4">
      <input id="txt_nueva_clasificacion" type="text" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" placeholder="Ej: Premium, Nocturno...">
      <button onclick="crearYAsignarClasificacion()" class="w-full rounded-xl bg-green-600 text-white px-4 py-3 font-semibold hover:bg-green-700">Crear Clasificación</button>
      <p class="text-xs text-slate-500">La nueva clasificación se creará en la tabla tarifas. Recarga la página para ver los cambios.</p>
    </div>
  </div>
</div>

<div class="side-panel" id="panel-clasif-rutas">
  <div class="side-panel-header flex justify-between items-center">
    <h3 class="text-lg font-semibold">🧭 Clasificar Rutas Existentes</h3>
    <button class="side-panel-close text-slate-500 hover:text-slate-700 text-xl">&times;</button>
  </div>
  <div class="side-panel-body">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-100">
          <tr><th class="px-4 py-2 text-left">Ruta</th><th class="px-4 py-2">Vehículo</th><th class="px-4 py-2">Empresa</th><th class="px-4 py-2">Clasificación</th></tr>
        </thead>
        <tbody>
        <?php foreach($rutasUnicas as $info): ?>
          <tr class="fila-ruta hover:bg-slate-50" data-ruta="<?= htmlspecialchars($info['ruta']) ?>" data-vehiculo="<?= htmlspecialchars($info['vehiculo']) ?>">
            <td class="px-4 py-2"><?= htmlspecialchars($info['ruta']) ?></td>
            <td class="px-4 py-2 text-center"><?= htmlspecialchars($info['vehiculo']) ?></td>
            <td class="px-4 py-2 text-center"><span class="empresa-mini-card">🏢 <?= htmlspecialchars($info['empresa']) ?></span></td>
            <td class="px-4 py-2 text-center">
              <select class="select-clasif-ruta" onchange="actualizarColorFila(this)">
                <option value="">Sin clasificar</option>
                <?php foreach ($clasificaciones_disponibles as $clasif): ?>
                  <option value="<?= htmlspecialchars($clasif) ?>" <?= $info['clasificacion']===$clasif ? 'selected' : '' ?>><?= ucfirst($clasif) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="side-panel" id="panel-selector-columnas">
  <div class="side-panel-header flex justify-between items-center">
    <h3 class="text-lg font-semibold">📊 Seleccionar Columnas</h3>
    <button class="side-panel-close text-slate-500 hover:text-slate-700 text-xl">&times;</button>
  </div>
  <div class="side-panel-body">
    <div class="flex flex-wrap gap-2 mb-4">
      <button onclick="seleccionarTodasColumnas()" class="text-xs px-3 py-1 rounded-lg bg-green-100 text-green-700">✅ Todas</button>
      <button onclick="deseleccionarTodasColumnas()" class="text-xs px-3 py-1 rounded-lg bg-rose-100 text-rose-700">❌ Ninguna</button>
      <button onclick="guardarSeleccionColumnas()" class="text-xs px-3 py-1 rounded-lg bg-blue-100 text-blue-700">💾 Guardar</button>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-[60vh] overflow-auto">
      <?php foreach ($clasificaciones_disponibles as $clasif): 
        $estilo = obtenerEstiloClasificacion($clasif);
        $seleccionada = in_array($clasif, $columnas_seleccionadas);
      ?>
        <div class="columna-checkbox-item flex items-center gap-2 p-3 border rounded-lg cursor-pointer <?= $seleccionada ? 'selected' : '' ?>" data-columna="<?= htmlspecialchars($clasif) ?>" onclick="toggleColumna('<?= htmlspecialchars($clasif) ?>')">
          <div class="checkbox-columna w-5 h-5 border-2 rounded flex items-center justify-center <?= $seleccionada ? 'bg-blue-600 border-blue-600' : 'border-slate-300' ?>">
            <?php if ($seleccionada): ?><span class="text-white text-xs">✓</span><?php endif; ?>
          </div>
          <span class="<?= $estilo['text'] ?>"><?= ucfirst($clasif) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="text-xs text-slate-500 mt-4">Seleccionadas: <span id="contador-seleccionadas-panel"><?= count($columnas_seleccionadas) ?></span></p>
  </div>
</div>

</body>
</html>
<?php
$conn->close();
?>