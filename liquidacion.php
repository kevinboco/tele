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
   🔹 PROCESAR POST (AJAX)
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
   🔹 Endpoint AJAX: Obtener datos filtrados
======================================================= */
if (isset($_GET['ajax_filtrar'])) {
    $desde = $_GET['desde'];
    $hasta = $_GET['hasta'];
    $empresasSeleccionadas = isset($_GET['empresas']) ? explode(',', $_GET['empresas']) : [];
    
    // Si no hay empresas seleccionadas, obtener todas
    if (empty($empresasSeleccionadas)) {
        $resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
        $empresasSeleccionadas = [];
        if ($resEmp) while ($r = $resEmp->fetch_assoc()) $empresasSeleccionadas[] = $r['empresa'];
    }
    
    // Escapar empresas
    $empresasSeleccionadas = array_map(function($e) use ($conn) {
        return $conn->real_escape_string($e);
    }, $empresasSeleccionadas);
    
    $columnas_tarifas = obtenerColumnasTarifas($conn);
    $clasificaciones_disponibles = obtenerClasificacionesDisponibles($conn);
    
    // Cargar clasificaciones de rutas
    $clasif_rutas = [];
    $resClasif = $conn->query("SELECT ruta, tipo_vehiculo, clasificacion FROM ruta_clasificacion");
    if ($resClasif) {
        while ($r = $resClasif->fetch_assoc()) {
            $key = mb_strtolower(trim($r['ruta'] . '|' . $r['tipo_vehiculo']), 'UTF-8');
            $clasif_rutas[$key] = strtolower($r['clasificacion']);
        }
    }
    
    // Función para procesar datos de una empresa
    function procesarDatosEmpresaAjax($conn, $empresa, $desde, $hasta, $clasificaciones_disponibles, $clasif_rutas) {
        $sql = "SELECT nombre, ruta, empresa, tipo_vehiculo, COALESCE(pago_parcial,0) AS pago_parcial
                FROM viajes
                WHERE fecha BETWEEN '$desde' AND '$hasta'
                  AND empresa = '$empresa'";
        
        $res = $conn->query($sql);
        
        $datos = [];
        $vehiculos = [];
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

        foreach ($datos as $conductor => $info) {
            $datos[$conductor]["pagado"] = (int)($pagosConductor[$conductor] ?? 0);
            $datos[$conductor]["rutas_sin_clasificar"] = count($rutas_sin_clasificar_por_conductor[$conductor] ?? []);
        }

        return [
            'datos' => $datos,
            'vehiculos' => $vehiculos
        ];
    }
    
    // Procesar datos para cada empresa
    $empresasData = [];
    foreach ($empresasSeleccionadas as $empresa) {
        $empresasData[$empresa] = procesarDatosEmpresaAjax($conn, $empresa, $desde, $hasta, $clasificaciones_disponibles, $clasif_rutas);
    }
    
    // Cargar tarifas guardadas
    $tarifas_guardadas = [];
    if (!empty($empresasSeleccionadas)) {
        $empresasList = "'" . implode("','", $empresasSeleccionadas) . "'";
        $resTarifas = $conn->query("SELECT * FROM tarifas WHERE empresa IN ($empresasList)");
        if ($resTarifas) {
            while ($r = $resTarifas->fetch_assoc()) {
                $tarifas_guardadas[$r['empresa']][$r['tipo_vehiculo']] = $r;
            }
        }
    }
    
    // Cargar columnas seleccionadas desde cookie
    $session_key = "columnas_seleccionadas_" . md5(implode(',', $empresasSeleccionadas) . $desde . $hasta);
    $columnas_seleccionadas = [];
    if (isset($_COOKIE[$session_key])) {
        $columnas_seleccionadas = json_decode($_COOKIE[$session_key], true);
    } else {
        $columnas_seleccionadas = $clasificaciones_disponibles;
    }
    
    // Devolver HTML de las tablas
    header('Content-Type: application/json');
    echo json_encode([
        'html' => generarTablasHTML($empresasSeleccionadas, $empresasData, $clasificaciones_disponibles, $columnas_seleccionadas, $tarifas_guardadas),
        'empresas' => $empresasSeleccionadas
    ]);
    exit;
}

/* =======================================================
   🔹 Función para generar HTML de tablas
======================================================= */
function generarTablasHTML($empresasSeleccionadas, $empresasData, $clasificaciones_disponibles, $columnas_seleccionadas, $tarifas_guardadas) {
    ob_start();
    
    $hayDatos = false;
    foreach ($empresasSeleccionadas as $empresa) {
        if (!empty($empresasData[$empresa]['datos'])) {
            $hayDatos = true;
            break;
        }
    }
    
    if (!$hayDatos): 
    ?>
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-12 text-center">
        <span class="text-6xl mb-4 block">📭</span>
        <h3 class="text-xl font-bold text-slate-700 mb-2">No hay datos para mostrar</h3>
        <p class="text-slate-500">No se encontraron viajes en el período seleccionado para las empresas elegidas.</p>
    </div>
    <?php else: 
        $empresaIndex = 0;
        foreach ($empresasSeleccionadas as $empresa): 
            $empresaData = $empresasData[$empresa]['datos'] ?? [];
            if (empty($empresaData)) continue;
            $empresaIndex++;
    ?>
    
    <?php if ($empresaIndex > 1): ?>
    <div class="empresa-separator my-8"></div>
    <?php endif; ?>
    
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden mb-8 empresa-table" 
         data-empresa="<?= htmlspecialchars($empresa) ?>"
         id="empresa-table-<?= md5($empresa) ?>">
        
        <div class="p-5 border-b border-slate-200 bg-gradient-to-r from-blue-50 to-white">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold flex items-center gap-2">
                        <span>🏢</span>
                        <?= htmlspecialchars($empresa) ?>
                    </h3>
                    <div class="text-sm text-slate-500 mt-1">
                        Conductores: <?= count($empresaData) ?> • 
                        <span class="empresa-conductores-count" data-empresa="<?= htmlspecialchars($empresa) ?>"><?= count($empresaData) ?></span>
                    </div>
                </div>
                
                <div class="flex gap-4 text-sm">
                    <div class="text-right">
                        <div class="text-slate-500">Viajes</div>
                        <div class="font-bold text-blue-600 empresa-total-viajes" id="empresa-total-viajes-<?= md5($empresa) ?>">0</div>
                    </div>
                    <div class="text-right">
                        <div class="text-slate-500">Pagado</div>
                        <div class="font-bold text-emerald-600 empresa-total-pagado" id="empresa-total-pagado-<?= md5($empresa) ?>">0</div>
                    </div>
                    <div class="text-right">
                        <div class="text-slate-500">Faltante</div>
                        <div class="font-bold text-rose-600 empresa-total-faltante" id="empresa-total-faltante-<?= md5($empresa) ?>">0</div>
                    </div>
                </div>
            </div>
            
            <div class="mt-4 flex gap-3">
                <div class="buscar-container w-64">
                    <input type="text" 
                           placeholder="Buscar conductor en <?= htmlspecialchars($empresa) ?>..." 
                           class="buscador-empresa w-full rounded-xl border border-slate-300 px-4 py-2 pr-10 text-sm"
                           data-empresa="<?= htmlspecialchars($empresa) ?>">
                    <button class="buscar-clear-empresa buscar-clear" data-empresa="<?= htmlspecialchars($empresa) ?>">✕</button>
                </div>
                <span class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-blue-700 text-xs font-medium">
                    <span class="empresa-visibles" data-empresa="<?= htmlspecialchars($empresa) ?>"><?= count($empresaData) ?></span>/<span class="empresa-total" data-empresa="<?= htmlspecialchars($empresa) ?>"><?= count($empresaData) ?></span> conductores
                </span>
            </div>
        </div>

        <div class="p-5">
            <div class="overflow-x-auto rounded-xl border border-slate-200 max-h-[70vh]">
                <table class="w-full text-sm empresa-tabla" data-empresa="<?= htmlspecialchars($empresa) ?>">
                    <thead class="bg-blue-600 text-white sticky top-0 z-20">
                        <tr>
                            <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 70px;">Estado</th>
                            <th class="px-4 py-3 text-left sticky top-0 bg-blue-600" style="min-width: 220px;">Conductor</th>
                            <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 120px;">Tipo</th>
                            
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
                    <tbody class="divide-y divide-slate-100 bg-white empresa-tbody" data-empresa="<?= htmlspecialchars($empresa) ?>">
                    <?php foreach ($empresaData as $conductor => $info): 
                        $esMensual = (stripos($info['vehiculo'], 'mensual') !== false);
                        $claseVehiculo = $esMensual ? 'vehiculo-mensual' : '';
                        $rutasSinClasificar = $info['rutas_sin_clasificar'] ?? 0;
                        $color_vehiculo = obtenerColorVehiculo($info['vehiculo']);
                    ?>
                        <tr data-vehiculo="<?= htmlspecialchars($info['vehiculo']) ?>" 
                            data-conductor="<?= htmlspecialchars($conductor) ?>" 
                            data-conductor-normalizado="<?= htmlspecialchars(mb_strtolower($conductor)) ?>"
                            data-pagado="<?= (int)($info['pagado'] ?? 0) ?>"
                            data-sin-clasificar="<?= $rutasSinClasificar ?>"
                            data-empresa="<?= htmlspecialchars($empresa) ?>"
                            class="hover:bg-blue-50/40 transition-colors <?php echo $rutasSinClasificar > 0 ? 'alerta-sin-clasificar' : ''; ?>">
                            
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
                                        data-empresa="<?= htmlspecialchars($empresa) ?>"
                                        data-conductor="<?= htmlspecialchars($conductor) ?>"
                                        onclick="abrirModalViajes('<?= htmlspecialchars($conductor) ?>', '<?= htmlspecialchars($empresa) ?>')">
                                    <?php if ($rutasSinClasificar > 0): ?>
                                        <span class="text-amber-600">⚠️</span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($conductor) ?>
                                </button>
                            </td>
                            
                            <td class="px-4 py-3 text-center">
                                <span class="inline-block <?= $claseVehiculo ?> px-3 py-1.5 rounded-lg text-xs font-medium border <?= $color_vehiculo['border'] ?> <?= $color_vehiculo['text'] ?> <?= $color_vehiculo['bg'] ?>">
                                    <?= htmlspecialchars($info['vehiculo']) ?>
                                    <?php if ($esMensual): ?>
                                        <span class="ml-1">📅</span>
                                    <?php endif; ?>
                                </span>
                            </td>
                            
                            <?php foreach ($clasificaciones_disponibles as $clasif): 
                                $estilo = obtenerEstiloClasificacion($clasif);
                                $cantidad = (int)($info[$clasif] ?? 0);
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
                                style="min-width: 80px; background-color: <?= $bg_cell_color ?>; color: <?= $text_cell_color ?>;">
                                <?= $cantidad ?>
                            </td>
                            <?php endforeach; ?>

                            <td class="px-4 py-3">
                                <input type="text"
                                       class="totales w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-slate-50 outline-none"
                                       readonly dir="ltr"
                                       data-empresa="<?= htmlspecialchars($empresa) ?>">
                            </td>
                            <td class="px-4 py-3">
                                <input type="text"
                                       class="pagado w-full rounded-xl border border-emerald-200 px-3 py-2 text-right bg-emerald-50 outline-none"
                                       readonly dir="ltr"
                                       data-empresa="<?= htmlspecialchars($empresa) ?>"
                                       value="<?= number_format((int)($info['pagado'] ?? 0), 0, ',', '.') ?>">
                            </td>
                            <td class="px-4 py-3">
                                <input type="text"
                                       class="faltante w-full rounded-xl border border-rose-200 px-3 py-2 text-right bg-rose-50 outline-none"
                                       readonly dir="ltr"
                                       data-empresa="<?= htmlspecialchars($empresa) ?>"
                                       value="0">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php 
        endforeach; 
    endif;
    
    return ob_get_clean();
}

/* =======================================================
   🔹 Página principal con filtros siempre visibles
======================================================= */

// Obtener todas las empresas para los checkboxes
$todasEmpresas = [];
$resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
if ($resEmp) while ($r = $resEmp->fetch_assoc()) $todasEmpresas[] = $r['empresa'];

// Obtener empresas seleccionadas de la URL (si existen)
$empresasSeleccionadas = isset($_GET['empresas']) ? explode(',', $_GET['empresas']) : [];

// Fechas por defecto
$desde = isset($_GET['desde']) ? $_GET['desde'] : date('Y-m-01');
$hasta = isset($_GET['hasta']) ? $_GET['hasta'] : date('Y-m-d');

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Liquidación de Conductores - Filtros Siempre Visibles</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  /* Estilos existentes se mantienen igual */
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
  
  /* Separador entre tablas */
  .empresa-separator {
    border-top: 3px solid #3b82f6;
    border-bottom: 1px solid #e2e8f0;
    background: linear-gradient(to right, #eff6ff, white);
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
  
  /* Estilo para los checkboxes de empresas */
  .empresa-checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
    max-height: 200px;
    overflow-y: auto;
    padding: 10px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: white;
  }
  
  .empresa-checkbox-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    border-radius: 8px;
    transition: all 0.2s;
    cursor: pointer;
  }
  
  .empresa-checkbox-item:hover {
    background-color: #f1f5f9;
  }
  
  .empresa-checkbox-item.selected {
    background-color: #eff6ff;
    border: 1px solid #3b82f6;
  }
  
  .empresa-checkbox-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    border-radius: 4px;
    border: 2px solid #cbd5e1;
    cursor: pointer;
  }
  
  .empresa-checkbox-item input[type="checkbox"]:checked {
    background-color: #3b82f6;
    border-color: #3b82f6;
  }
  
  .filtros-header {
    position: sticky;
    top: 0;
    z-index: 30;
    background: white;
    border-bottom: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
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
    
    .empresa-checkbox-grid {
      grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    }
  }
</style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800">

  <!-- BOLITAS FLOTANTES -->
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

  <!-- PANELES -->
  <div class="side-panel" id="panel-tarifas">
    <div class="side-panel-header">
      <h3 class="text-lg font-semibold flex items-center gap-2">
        <span>🚐 Tarifas por Tipo de Vehículo</span>
        <span class="text-xs text-slate-500">(<?= count($columnas_tarifas ?? obtenerColumnasTarifas($conn)) ?> tipos)</span>
      </h3>
      <button class="side-panel-close" data-panel="tarifas">✕</button>
    </div>
    <div class="side-panel-body">
      <div id="tarifas-panel-content">
        <p class="text-center text-slate-500 py-4">Selecciona filtros para cargar tarifas</p>
      </div>
    </div>
  </div>
  
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

  <div class="side-panel" id="panel-clasif-rutas">
    <div class="side-panel-header">
      <h3 class="text-lg font-semibold flex items-center gap-2">
        <span>🧭 Clasificar Rutas Existentes</span>
      </h3>
      <button class="side-panel-close" data-panel="clasif-rutas">✕</button>
    </div>
    <div class="side-panel-body">
      <div id="clasif-rutas-content">
        <p class="text-center text-slate-500 py-4">Selecciona filtros para cargar rutas</p>
      </div>
    </div>
  </div>

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
            <span id="contador-seleccionadas-panel" class="font-semibold text-blue-600">0</span> de 
            <?= count(obtenerClasificacionesDisponibles($conn)) ?> seleccionadas
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
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-[60vh] overflow-y-auto p-2 border border-slate-200 rounded-lg" id="columnas-panel">
          <?php 
          $clasificaciones = obtenerClasificacionesDisponibles($conn);
          foreach ($clasificaciones as $clasif): 
            $estilo = obtenerEstiloClasificacion($clasif);
          ?>
          <div class="columna-checkbox-item flex items-center gap-2 p-3 border border-slate-200 rounded-lg cursor-pointer transition"
               data-columna="<?= htmlspecialchars($clasif) ?>"
               onclick="toggleColumna('<?= htmlspecialchars($clasif) ?>')">
            <div class="checkbox-columna" id="checkbox-<?= htmlspecialchars($clasif) ?>"></div>
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

  <!-- Overlay -->
  <div class="side-panel-overlay" id="sidePanelOverlay"></div>

  <!-- HEADER CON FILTROS SIEMPRE VISIBLES -->
  <header class="filtros-header">
    <div class="max-w-[1800px] mx-auto px-3 md:px-4 py-4">
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
        <h2 class="text-xl md:text-2xl font-bold mb-4 flex items-center gap-2">
          🪙 Liquidación de Conductores
          <span class="text-sm font-normal text-slate-500 ml-2">Filtros siempre visibles</span>
        </h2>
        
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
          <!-- Fechas -->
          <div class="lg:col-span-3">
            <label class="block text-xs font-medium text-slate-600 mb-1">📅 Desde</label>
            <input type="date" id="filtro_desde" value="<?= htmlspecialchars($desde) ?>" 
                   class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition">
          </div>
          
          <div class="lg:col-span-3">
            <label class="block text-xs font-medium text-slate-600 mb-1">📅 Hasta</label>
            <input type="date" id="filtro_hasta" value="<?= htmlspecialchars($hasta) ?>" 
                   class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition">
          </div>
          
          <div class="lg:col-span-5">
            <label class="block text-xs font-medium text-slate-600 mb-1">🏢 Empresas (selecciona las que quieras ver)</label>
            <div class="empresa-checkbox-grid" id="empresasCheckboxContainer">
              <?php foreach ($todasEmpresas as $empresa): 
                $checked = in_array($empresa, $empresasSeleccionadas) ? 'checked' : '';
              ?>
              <label class="empresa-checkbox-item <?= $checked ? 'selected' : '' ?>">
                <input type="checkbox" class="empresa-checkbox" value="<?= htmlspecialchars($empresa) ?>" <?= $checked ?>>
                <span class="text-sm truncate"><?= htmlspecialchars($empresa) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
          
          <div class="lg:col-span-1 flex items-end">
            <button onclick="aplicarFiltros()" 
                    class="w-full rounded-xl bg-blue-600 text-white px-4 py-2.5 text-sm font-semibold hover:bg-blue-700 active:bg-blue-800 focus:ring-4 focus:ring-blue-200 transition flex items-center justify-center gap-2">
              <span>🔍</span> Filtrar
            </button>
          </div>
        </div>
        
        <div class="mt-3 text-sm text-slate-600 flex items-center gap-2">
          <span class="font-medium">Empresas seleccionadas:</span>
          <span id="contadorEmpresasSeleccionadas" class="bg-blue-100 text-blue-700 px-2 py-1 rounded-lg text-xs font-semibold">
            <?= count($empresasSeleccionadas) ?>
          </span>
          <span class="text-slate-400">|</span>
          <span class="text-xs text-slate-500">Los cambios se aplican al hacer clic en Filtrar</span>
        </div>
      </div>
    </div>
  </header>

  <!-- CONTENIDO PRINCIPAL (TABLAS) -->
  <main class="max-w-[1800px] mx-auto px-3 md:px-4 py-6">
    <div class="table-container-wrapper" id="tableContainerWrapper">
      
      <!-- Totales generales (se actualizan vía JS) -->
      <div class="totales-generales mb-6">
        <div class="flex items-center gap-3 mb-4">
          <span class="text-2xl">📊</span>
          <h3 class="text-xl font-bold">TOTALES GENERALES</h3>
          <span class="bg-white/20 px-3 py-1 rounded-full text-sm" id="totalEmpresasCount"><?= count($empresasSeleccionadas) ?></span>
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

      <!-- Contenedor de tablas (se actualiza vía AJAX) -->
      <div id="tablasContainer">
        <?php
        // Procesar datos para las empresas seleccionadas (carga inicial)
        $clasificaciones_disponibles = obtenerClasificacionesDisponibles($conn);
        
        // Cargar clasificaciones de rutas
        $clasif_rutas = [];
        $resClasif = $conn->query("SELECT ruta, tipo_vehiculo, clasificacion FROM ruta_clasificacion");
        if ($resClasif) {
            while ($r = $resClasif->fetch_assoc()) {
                $key = mb_strtolower(trim($r['ruta'] . '|' . $r['tipo_vehiculo']), 'UTF-8');
                $clasif_rutas[$key] = strtolower($r['clasificacion']);
            }
        }
        
        // Procesar datos para cada empresa
        $empresasData = [];
        foreach ($empresasSeleccionadas as $empresa) {
            $empresa = $conn->real_escape_string($empresa);
            $empresasData[$empresa] = procesarDatosEmpresaAjax($conn, $empresa, $desde, $hasta, $clasificaciones_disponibles, $clasif_rutas);
        }
        
        // Cargar tarifas guardadas
        $tarifas_guardadas = [];
        if (!empty($empresasSeleccionadas)) {
            $empresasList = "'" . implode("','", array_map(function($e) use ($conn) {
                return $conn->real_escape_string($e);
            }, $empresasSeleccionadas)) . "'";
            $resTarifas = $conn->query("SELECT * FROM tarifas WHERE empresa IN ($empresasList)");
            if ($resTarifas) {
                while ($r = $resTarifas->fetch_assoc()) {
                    $tarifas_guardadas[$r['empresa']][$r['tipo_vehiculo']] = $r;
                }
            }
        }
        
        // Cargar columnas seleccionadas desde cookie
        $session_key = "columnas_seleccionadas_" . md5(implode(',', $empresasSeleccionadas) . $desde . $hasta);
        $columnas_seleccionadas = [];
        if (isset($_COOKIE[$session_key])) {
            $columnas_seleccionadas = json_decode($_COOKIE[$session_key], true);
        } else {
            $columnas_seleccionadas = $clasificaciones_disponibles;
        }
        
        echo generarTablasHTML($empresasSeleccionadas, $empresasData, $clasificaciones_disponibles, $columnas_seleccionadas, $tarifas_guardadas);
        ?>
      </div>
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
            <span id="viajesEmpresa"></span>
          </div>
        </div>
      </div>
      <div class="viajes-body" id="viajesContent"></div>
    </div>
  </div>

  <script>
    // ===== VARIABLES GLOBALES =====
    const CLASIFICACIONES_DISPONIBLES = <?= json_encode(obtenerClasificacionesDisponibles($conn)) ?>;
    let columnasSeleccionadas = <?= json_encode($columnas_seleccionadas ?? obtenerClasificacionesDisponibles($conn)) ?>;
    
    // ===== SISTEMA DE BOLITAS Y PANELES =====
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
      
      // Inicializar eventos
      inicializarEventosCheckboxesEmpresa();
      inicializarSeleccionColumnas();
      
      // Cargar datos iniciales
      setTimeout(() => {
        if (document.querySelector('.empresa-tbody')) {
          configurarEventosTarifas();
          configurarBuscadoresPorEmpresa();
          colapsarTodosTarifas();
          recalcularTodo();
        }
      }, 500);
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
        
        // Cargar contenido según el panel
        if (panelId === 'tarifas') {
          cargarPanelTarifas();
        } else if (panelId === 'clasif-rutas') {
          cargarPanelClasifRutas();
        }
      }
    }
    
    // ===== FUNCIONES PARA CARGAR PANELES =====
    function cargarPanelTarifas() {
      const desde = document.getElementById('filtro_desde').value;
      const hasta = document.getElementById('filtro_hasta').value;
      const empresas = obtenerEmpresasSeleccionadas();
      
      if (empresas.length === 0) {
        document.getElementById('panel-tarifas').querySelector('.side-panel-body').innerHTML = 
          '<p class="text-center text-amber-600 py-4">Selecciona al menos una empresa en los filtros</p>';
        return;
      }
      
      // Aquí cargarías las tarifas via AJAX
      // Por ahora mostramos un mensaje
      document.getElementById('panel-tarifas').querySelector('.side-panel-body').innerHTML = 
        '<p class="text-center text-slate-500 py-4">Cargando tarifas... (implementar AJAX)</p>';
    }
    
    function cargarPanelClasifRutas() {
      const desde = document.getElementById('filtro_desde').value;
      const hasta = document.getElementById('filtro_hasta').value;
      const empresas = obtenerEmpresasSeleccionadas();
      
      if (empresas.length === 0) {
        document.getElementById('panel-clasif-rutas').querySelector('.side-panel-body').innerHTML = 
          '<p class="text-center text-amber-600 py-4">Selecciona al menos una empresa en los filtros</p>';
        return;
      }
      
      // Aquí cargarías las rutas via AJAX
      // Por ahora mostramos un mensaje
      document.getElementById('panel-clasif-rutas').querySelector('.side-panel-body').innerHTML = 
        '<p class="text-center text-slate-500 py-4">Cargando rutas... (implementar AJAX)</p>';
    }
    
    // ===== FUNCIONES DE FILTROS =====
    function inicializarEventosCheckboxesEmpresa() {
      document.querySelectorAll('.empresa-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
          const label = this.closest('.empresa-checkbox-item');
          if (this.checked) {
            label.classList.add('selected');
          } else {
            label.classList.remove('selected');
          }
          actualizarContadorEmpresas();
        });
      });
    }
    
    function obtenerEmpresasSeleccionadas() {
      const empresas = [];
      document.querySelectorAll('.empresa-checkbox:checked').forEach(cb => {
        empresas.push(cb.value);
      });
      return empresas;
    }
    
    function actualizarContadorEmpresas() {
      const count = document.querySelectorAll('.empresa-checkbox:checked').length;
      document.getElementById('contadorEmpresasSeleccionadas').textContent = count;
      document.getElementById('totalEmpresasCount').textContent = count;
    }
    
    function aplicarFiltros() {
      const desde = document.getElementById('filtro_desde').value;
      const hasta = document.getElementById('filtro_hasta').value;
      const empresas = obtenerEmpresasSeleccionadas();
      
      if (!desde || !hasta) {
        alert('Selecciona fecha desde y hasta');
        return;
      }
      
      // Mostrar loading
      document.getElementById('tablasContainer').innerHTML = 
        '<div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-12 text-center"><div class="animate-pulse">Cargando datos...</div></div>';
      
      // Construir URL
      const params = new URLSearchParams({
        ajax_filtrar: 1,
        desde: desde,
        hasta: hasta,
        empresas: empresas.join(',')
      });
      
      fetch('<?= basename(__FILE__) ?>?' + params.toString())
        .then(r => r.json())
        .then(data => {
          document.getElementById('tablasContainer').innerHTML = data.html;
          
          // Actualizar URL sin recargar
          const url = new URL(window.location);
          url.searchParams.set('desde', desde);
          url.searchParams.set('hasta', hasta);
          url.searchParams.set('empresas', empresas.join(','));
          window.history.pushState({}, '', url);
          
          // Reinicializar eventos
          setTimeout(() => {
            configurarEventosTarifas();
            configurarBuscadoresPorEmpresa();
            colapsarTodosTarifas();
            inicializarSeleccionColumnas();
            recalcularTodo();
          }, 200);
        })
        .catch(error => {
          console.error('Error:', error);
          document.getElementById('tablasContainer').innerHTML = 
            '<div class="bg-white border border-rose-200 rounded-2xl shadow-sm p-12 text-center text-rose-600">Error al cargar datos</div>';
        });
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
    
    // ===== SELECCIÓN DE COLUMNAS =====
    function inicializarSeleccionColumnas() {
      // Marcar columnas seleccionadas en el panel
      columnasSeleccionadas.forEach(columna => {
        const checkbox = document.getElementById('checkbox-' + columna);
        if (checkbox) checkbox.classList.add('checked');
        const item = document.querySelector(`.columna-checkbox-item[data-columna="${columna}"]`);
        if (item) item.classList.add('selected');
      });
      actualizarContadorColumnas();
      actualizarColumnasTabla();
    }
    
    function toggleColumna(columna) {
      const checkbox = document.getElementById('checkbox-' + columna);
      const item = document.querySelector(`.columna-checkbox-item[data-columna="${columna}"]`);
      
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
      const desde = document.getElementById('filtro_desde').value;
      const hasta = document.getElementById('filtro_hasta').value;
      const empresas = obtenerEmpresasSeleccionadas().join(',');
      
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
    
    // ===== BUSCADORES POR EMPRESA =====
    function configurarBuscadoresPorEmpresa() {
      document.querySelectorAll('.buscador-empresa').forEach(input => {
        const empresa = input.dataset.empresa;
        const clearBtn = document.querySelector(`.buscar-clear-empresa[data-empresa="${empresa}"]`);
        
        // Remover event listeners anteriores
        const nuevoInput = input.cloneNode(true);
        input.parentNode.replaceChild(nuevoInput, input);
        
        nuevoInput.addEventListener('input', () => filtrarEmpresa(empresa));
        if (clearBtn) {
          clearBtn.addEventListener('click', () => {
            nuevoInput.value = '';
            filtrarEmpresa(empresa);
            nuevoInput.focus();
          });
        }
      });
    }
    
    function filtrarEmpresa(empresa) {
      const input = document.querySelector(`.buscador-empresa[data-empresa="${empresa}"]`);
      const textoBusqueda = input ? normalizarTexto(input.value) : '';
      const tbody = document.querySelector(`.empresa-tbody[data-empresa="${empresa}"]`);
      const filas = tbody ? tbody.querySelectorAll('tr') : [];
      const clearBtn = document.querySelector(`.buscar-clear-empresa[data-empresa="${empresa}"]`);
      
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
      
      const visiblesSpan = document.querySelector(`.empresa-visibles[data-empresa="${empresa}"]`);
      const totalSpan = document.querySelector(`.empresa-total[data-empresa="${empresa}"]`);
      
      if (visiblesSpan) visiblesSpan.textContent = visibles;
      if (totalSpan) totalSpan.textContent = filas.length;
      if (clearBtn) clearBtn.style.display = textoBusqueda === '' ? 'none' : 'block';
      
      recalcularEmpresa(empresa);
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
    
    function recalcularEmpresa(empresa) {
      const tarifas = getTarifas();
      const tbody = document.querySelector(`.empresa-tbody[data-empresa="${empresa}"]`);
      if (!tbody) return;
      
      const filas = tbody.querySelectorAll('tr');
      let totalViajesEmpresa = 0;
      let totalPagadoEmpresa = 0;
      let totalFaltanteEmpresa = 0;
      
      filas.forEach(fila => {
        if (fila.style.display === 'none') return;
        
        const vehiculo = fila.dataset.vehiculo;
        const tarifasVehiculo = tarifas[empresa]?.[vehiculo] || {};
        
        let totalFila = 0;
        
        CLASIFICACIONES_DISPONIBLES.forEach(columna => {
          const celda = fila.querySelector(`td[data-columna="${columna}"]`);
          const cantidad = parseInt(celda?.textContent || 0);
          const tarifa = tarifasVehiculo[columna] || 0;
          totalFila += cantidad * tarifa;
        });
        
        const pagado = parseInt(fila.dataset.pagado || '0') || 0;
        let faltante = totalFila - pagado;
        if (faltante < 0) faltante = 0;
        
        const totalInput = fila.querySelector('input.totales');
        if (totalInput) totalInput.value = formatNumber(totalFila);
        
        const faltanteInput = fila.querySelector('input.faltante');
        if (faltanteInput) faltanteInput.value = formatNumber(faltante);
        
        totalViajesEmpresa += totalFila;
        totalPagadoEmpresa += pagado;
        totalFaltanteEmpresa += faltante;
      });
      
      // Actualizar totales de la empresa
      const empresaId = 'empresa-total-viajes-' + md5(empresa);
      const empresaViajes = document.getElementById(empresaId);
      if (empresaViajes) empresaViajes.textContent = formatNumber(totalViajesEmpresa);
      
      const empresaPagado = document.getElementById('empresa-total-pagado-' + md5(empresa));
      if (empresaPagado) empresaPagado.textContent = formatNumber(totalPagadoEmpresa);
      
      const empresaFaltante = document.getElementById('empresa-total-faltante-' + md5(empresa));
      if (empresaFaltante) empresaFaltante.textContent = formatNumber(totalFaltanteEmpresa);
      
      return {
        viajes: totalViajesEmpresa,
        pagado: totalPagadoEmpresa,
        faltante: totalFaltanteEmpresa
      };
    }
    
    function recalcularTodo() {
      const tarifas = getTarifas();
      const empresas = obtenerEmpresasSeleccionadas();
      let totalViajesGlobal = 0;
      let totalPagadoGlobal = 0;
      let totalFaltanteGlobal = 0;
      
      empresas.forEach(empresa => {
        const resultado = recalcularEmpresa(empresa);
        if (resultado) {
          totalViajesGlobal += resultado.viajes;
          totalPagadoGlobal += resultado.pagado;
          totalFaltanteGlobal += resultado.faltante;
        }
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
            // Aquí implementarías la asignación automática
            alert(`✅ Se creó "${nombreClasif}". Ahora puedes asignarlo manualmente en el panel de rutas.`);
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
      const desde = document.getElementById('filtro_desde').value;
      const hasta = document.getElementById('filtro_hasta').value;
      
      viajesRango.textContent = desde + " → " + hasta;
      viajesEmpresa.textContent = empresa || "Todas las empresas";
      viajesTitle.textContent = nombreConductor;
      
      const qs = new URLSearchParams({
        viajes_conductor: nombreConductor,
        desde: desde,
        hasta: hasta,
        empresa: empresa
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
    
    // ===== UTILIDADES =====
    function md5(string) {
      // Implementación simple para IDs (no criptográfica)
      return string.split('').reduce((a, b) => {
        a = ((a << 5) - a) + b.charCodeAt(0);
        return a & a;
      }, 0).toString(36);
    }
    
    // Recalcular al cambiar algo
    document.addEventListener('input', function(e) {
      if (e.target.matches('.tarifa-input')) {
        recalcularTodo();
      }
    });
    
    // Cargar datos si hay parámetros en la URL
    window.addEventListener('load', function() {
      actualizarContadorEmpresas();
    });
  </script>
</body>
</html>
<?php
$conn->close();
?>