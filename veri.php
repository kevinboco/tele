<?php
/* =======================================================
   🚀 SISTEMA DE LIQUIDACIÓN DE CONDUCTORES - VERSIÓN MODULAR
   ========================================================
   INSTRUCCIONES: 
   - Cada módulo es COMPLETAMENTE INDEPENDIENTE
   - Puedes modificar, agregar o eliminar módulos sin afectar los demás
   - Si necesitas cambiar algo, busca el MÓDULO correspondiente
   ======================================================== */

include("nav.php");

/* =======================================================
   🚀 MÓDULO 1: CONFIGURACIÓN INICIAL Y BASE DE DATOS
   ========================================================
   🔧 PROPÓSITO: Conexión BD y funciones COMPARTIDAS entre módulos
   🔧 SI MODIFICAS: Afecta a TODOS los módulos
   ======================================================== */
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

// Funciones GLOBALES (compartidas por todos los módulos)
function obtenerColumnasTarifas($conn) {
    $columnas = [];
    $res = $conn->query("SHOW COLUMNS FROM tarifas");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $field = $row['Field'];
            $excluir = ['id', 'empresa', 'tipo_vehiculo', 'created_at', 'updated_at'];
            if (!in_array($field, $excluir)) $columnas[] = $field;
        }
    }
    return $columnas;
}

function obtenerClasificacionesDisponibles($conn) {
    return obtenerColumnasTarifas($conn);
}

function crearNuevaColumnaTarifa($conn, $nombre_columna) {
    $nombre_columna = preg_replace('/[^a-zA-Z0-9_]/', '_', $nombre_columna);
    $nombre_columna = strtolower($nombre_columna);
    $columnas_existentes = obtenerColumnasTarifas($conn);
    if (in_array($nombre_columna, $columnas_existentes)) return true;
    $sql = "ALTER TABLE tarifas ADD COLUMN `$nombre_columna` DECIMAL(10,2) DEFAULT 0.00";
    return $conn->query($sql);
}

function obtenerEstiloClasificacion($clasificacion) {
    $estilos_predefinidos = [
        'completo'    => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200', 'row' => 'bg-emerald-50/40', 'label' => 'Completo'],
        'medio'       => ['bg' => 'bg-amber-100', 'text' => 'text-amber-800', 'border' => 'border-amber-200', 'row' => 'bg-amber-50/40', 'label' => 'Medio'],
        'extra'       => ['bg' => 'bg-slate-200', 'text' => 'text-slate-800', 'border' => 'border-slate-300', 'row' => 'bg-slate-50', 'label' => 'Extra'],
        'siapana'     => ['bg' => 'bg-fuchsia-100', 'text' => 'text-fuchsia-700', 'border' => 'border-fuchsia-200', 'row' => 'bg-fuchsia-50/40', 'label' => 'Siapana'],
        'carrotanque' => ['bg' => 'bg-cyan-100', 'text' => 'text-cyan-800', 'border' => 'border-cyan-200', 'row' => 'bg-cyan-50/40', 'label' => 'Carrotanque'],
        'riohacha'    => ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-700', 'border' => 'border-indigo-200', 'row' => 'bg-indigo-50/40', 'label' => 'Riohacha'],
        'pru'         => ['bg' => 'bg-teal-100', 'text' => 'text-teal-700', 'border' => 'border-teal-200', 'row' => 'bg-teal-50/40', 'label' => 'Pru'],
        'maco'        => ['bg' => 'bg-rose-100', 'text' => 'text-rose-700', 'border' => 'border-rose-200', 'row' => 'bg-rose-50/40', 'label' => 'Maco']
    ];
    if (isset($estilos_predefinidos[$clasificacion])) return $estilos_predefinidos[$clasificacion];
    
    $colores_genericos = [
        ['bg' => 'bg-violet-100', 'text' => 'text-violet-700', 'border' => 'border-violet-200'],
        ['bg' => 'bg-orange-100', 'text' => 'text-orange-700', 'border' => 'border-orange-200']
    ];
    $hash = crc32($clasificacion);
    $color_index = abs($hash) % count($colores_genericos);
    
    return [
        'bg' => $colores_genericos[$color_index]['bg'],
        'text' => $colores_genericos[$color_index]['text'],
        'border' => $colores_genericos[$color_index]['border'],
        'row' => str_replace('bg-', 'bg-', $colores_genericos[$color_index]['bg']) . '/40',
        'label' => ucfirst($clasificacion)
    ];
}

function obtenerColorVehiculo($vehiculo) {
    $colores_vehiculos = [
        'camioneta' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'border' => 'border-blue-200', 'dark' => 'bg-blue-50'],
        'turbo' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'border' => 'border-green-200', 'dark' => 'bg-green-50'],
        'mensual' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-700', 'border' => 'border-orange-200', 'dark' => 'bg-orange-50'],
        'camión' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-700', 'border' => 'border-purple-200', 'dark' => 'bg-purple-50']
    ];
    $vehiculo_lower = strtolower($vehiculo);
    if (isset($colores_vehiculos[$vehiculo_lower])) return $colores_vehiculos[$vehiculo_lower];
    
    $colores_genericos = [
        ['bg' => 'bg-violet-100', 'text' => 'text-violet-700', 'border' => 'border-violet-200', 'dark' => 'bg-violet-50'],
        ['bg' => 'bg-cyan-100', 'text' => 'text-cyan-700', 'border' => 'border-cyan-200', 'dark' => 'bg-cyan-50']
    ];
    $hash = crc32($vehiculo);
    $color_index = abs($hash) % count($colores_genericos);
    return $colores_genericos[$color_index];
}
/* ===== FIN MÓDULO 1 ===== */

/* =======================================================
   🚀 MÓDULO 2: PROCESAMIENTO DE ENDPOINTS AJAX
   ========================================================
   🔧 PROPÓSITO: Todas las peticiones POST/GET del sistema
   🔧 SI MODIFICAS: Afectas la comunicación con el backend
   ======================================================== */

// ENDPOINT: Crear nueva clasificación
if (isset($_POST['crear_clasificacion'])) {
    $nombre_clasificacion = trim($conn->real_escape_string($_POST['nombre_clasificacion']));
    if (empty($nombre_clasificacion)) { echo "error: nombre vacío"; exit; }
    
    $nombre_columna = preg_replace('/[^a-zA-Z0-9_]/', '_', $nombre_clasificacion);
    $nombre_columna = strtolower($nombre_columna);
    
    if (crearNuevaColumnaTarifa($conn, $nombre_columna)) {
        echo "ok";
    } else {
        echo "error: " . $conn->error;
    }
    exit;
}

// ENDPOINT: Guardar tarifa
if (isset($_POST['guardar_tarifa'])) {
    $empresa  = $conn->real_escape_string($_POST['empresa']);
    $vehiculo = $conn->real_escape_string($_POST['tipo_vehiculo']);
    $campo    = strtolower($conn->real_escape_string($_POST['campo']));
    $valor    = (float)$_POST['valor'];

    $campo = preg_replace('/[^a-z0-9_]/', '_', $campo);
    $columnas_tarifas = obtenerColumnasTarifas($conn);
    
    if (!in_array($campo, $columnas_tarifas)) { 
        if (!crearNuevaColumnaTarifa($conn, $campo)) {
            echo "error: no se pudo crear el campo '$campo'";
            exit;
        }
    }

    $conn->query("INSERT IGNORE INTO tarifas (empresa, tipo_vehiculo) VALUES ('$empresa', '$vehiculo')");
    $sql = "UPDATE tarifas SET `$campo` = $valor WHERE empresa='$empresa' AND tipo_vehiculo='$vehiculo'";
    
    echo $conn->query($sql) ? "ok" : "error: " . $conn->error;
    exit;
}

// ENDPOINT: Guardar clasificación de ruta
if (isset($_POST['guardar_clasificacion'])) {
    $ruta       = $conn->real_escape_string($_POST['ruta']);
    $vehiculo   = $conn->real_escape_string($_POST['tipo_vehiculo']);
    $clasif     = strtolower($conn->real_escape_string($_POST['clasificacion']));

    if ($clasif === '') {
        $sql = "DELETE FROM ruta_clasificacion WHERE ruta = '$ruta' AND tipo_vehiculo = '$vehiculo'";
    } else {
        $sql = "INSERT INTO ruta_clasificacion (ruta, tipo_vehiculo, clasificacion)
                VALUES ('$ruta', '$vehiculo', '$clasif')
                ON DUPLICATE KEY UPDATE clasificacion = VALUES(clasificacion)";
    }
    
    echo $conn->query($sql) ? "ok" : "error: " . $conn->error;
    exit;
}

// ENDPOINT: Guardar columnas seleccionadas
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
/* ===== FIN MÓDULO 2 ===== */

/* =======================================================
   🚀 MÓDULO 3: FILTRO INICIAL (PANTALLA DE SELECCIÓN)
   ========================================================
   🔧 PROPÓSITO: Mostrar formulario cuando no hay fechas
   🔧 SI MODIFICAS: Cambias la pantalla de entrada
   ======================================================== */
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
/* ===== FIN MÓDULO 3 ===== */

/* =======================================================
   🚀 MÓDULO 4: OBTENCIÓN DE DATOS PRINCIPALES
   ========================================================
   🔧 PROPÓSITO: Procesar los datos de la consulta principal
   🔧 SI MODIFICAS: Cambias la lógica de negocio
   ======================================================== */
$desde = $_GET['desde'];
$hasta = $_GET['hasta'];
$empresaFiltro = $_GET['empresa'] ?? "";

$columnas_tarifas = obtenerColumnasTarifas($conn);
$clasificaciones_disponibles = obtenerClasificacionesDisponibles($conn);

$session_key = "columnas_seleccionadas_" . md5($empresaFiltro . $desde . $hasta);
$columnas_seleccionadas = [];
if (isset($_COOKIE[$session_key])) {
    $columnas_seleccionadas = json_decode($_COOKIE[$session_key], true);
} else {
    $columnas_seleccionadas = $clasificaciones_disponibles;
}

// Cargar clasificaciones de rutas
$clasif_rutas = [];
$resClasif = $conn->query("SELECT ruta, tipo_vehiculo, clasificacion FROM ruta_clasificacion");
if ($resClasif) {
    while ($r = $resClasif->fetch_assoc()) {
        $key = mb_strtolower(trim($r['ruta'] . '|' . $r['tipo_vehiculo']), 'UTF-8');
        $clasif_rutas[$key] = strtolower($r['clasificacion']);
    }
}

// Consulta principal de viajes
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

        if (!in_array($vehiculo, $vehiculos, true)) $vehiculos[] = $vehiculo;

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
            $datos[$nombre] = ["vehiculo" => $vehiculo, "pagado" => 0];
            foreach ($clasificaciones_disponibles as $clasif) $datos[$nombre][$clasif] = 0;
        }

        $clasifRuta = $clasif_rutas[$keyRuta] ?? '';
        if ($clasifRuta !== '') {
            if (!isset($datos[$nombre][$clasifRuta])) $datos[$nombre][$clasifRuta] = 0;
            $datos[$nombre][$clasifRuta]++;
        }
    }
}

foreach ($datos as $conductor => $info) {
    $datos[$conductor]["pagado"] = (int)($pagosConductor[$conductor] ?? 0);
    $datos[$conductor]["rutas_sin_clasificar"] = count($rutas_sin_clasificar_por_conductor[$conductor] ?? []);
}

// Obtener empresas para el filtro
$empresas = [];
$resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
if ($resEmp) while ($r = $resEmp->fetch_assoc()) $empresas[] = $r['empresa'];

// Obtener tarifas guardadas
$tarifas_guardadas = [];
if ($empresaFiltro !== "") {
  $resTarifas = $conn->query("SELECT * FROM tarifas WHERE empresa='$empresaFiltro'");
  if ($resTarifas) {
    while ($r = $resTarifas->fetch_assoc()) {
      $tarifas_guardadas[$r['tipo_vehiculo']] = $r;
    }
  }
}
/* ===== FIN MÓDULO 4 ===== */

/* =======================================================
   🚀 MÓDULO 5: ENDPOINT VIAJES POR CONDUCTOR (AJAX)
   ========================================================
   🔧 PROPÓSITO: Cargar viajes individuales en el modal
   🔧 SI MODIFICAS: Cambias la visualización de viajes
   ======================================================== */
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

    // Cargar clasificaciones
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
            
            echo "</div></div>";
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
/* ===== FIN MÓDULO 5 ===== */
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Liquidación de Conductores</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800">

<!-- =======================================================
   🚀 MÓDULO 6: ENCABEZADO PRINCIPAL Y FILTROS
   ========================================================
   🔧 PROPÓSITO: Barra superior con filtros y título
   🔧 SI MODIFICAS: Cambias la navegación principal
   ======================================================== -->
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
<!-- ===== FIN MÓDULO 6 ===== -->

<!-- =======================================================
   🚀 MÓDULO 7: BOLITAS FLOTANTES (NAVEGACIÓN RÁPIDA)
   ========================================================
   🔧 PROPÓSITO: Acceso rápido a paneles laterales
   🔧 SI MODIFICAS: Cambias la navegación del sistema
   🔧 PARA AGREGAR UNA NUEVA BOLITA: Copia este bloque completo
   ======================================================== -->
<div class="floating-balls-container">
    <style>
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
    
    .ball-content {
        font-size: 24px;
        font-weight: bold;
        color: white;
        text-shadow: 0 2px 4px rgba(0,0,0,0.3);
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
    
    .ball-active {
        animation: pulse-ball 2s infinite;
        box-shadow: 0 0 0 8px rgba(59, 130, 246, 0.2);
    }
    
    @keyframes pulse-ball {
        0%, 100% { box-shadow: 0 8px 20px rgba(0,0,0,0.2), 0 0 0 0 rgba(59, 130, 246, 0.4); }
        50% { box-shadow: 0 8px 20px rgba(0,0,0,0.2), 0 0 0 12px rgba(59, 130, 246, 0); }
    }
    
    .ball-tarifas { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
    .ball-crear-clasif { background: linear-gradient(135deg, #10b981, #059669); }
    .ball-clasif-rutas { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .ball-selector-columnas { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
    
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
        .ball-tooltip { display: none; }
    }
    </style>

    <!-- Bolita 1: Tarifas -->
    <div class="floating-ball ball-tarifas" id="ball-tarifas" data-panel="tarifas">
        <div class="ball-content">🚐</div>
        <div class="ball-tooltip">Tarifas por tipo de vehículo</div>
    </div>
    
    <!-- Bolita 2: Crear clasificación -->
    <div class="floating-ball ball-crear-clasif" id="ball-crear-clasif" data-panel="crear-clasif">
        <div class="ball-content">➕</div>
        <div class="ball-tooltip">Crear nueva clasificación</div>
    </div>
    
    <!-- Bolita 3: Clasificar rutas -->
    <div class="floating-ball ball-clasif-rutas" id="ball-clasif-rutas" data-panel="clasif-rutas">
        <div class="ball-content">🧭</div>
        <div class="ball-tooltip">Clasificar rutas existentes</div>
    </div>
    
    <!-- Bolita 4: Selector de columnas -->
    <div class="floating-ball ball-selector-columnas" id="ball-selector-columnas" data-panel="selector-columnas">
        <div class="ball-content">📊</div>
        <div class="ball-tooltip">Seleccionar columnas</div>
    </div>
    
    <!-- 🆕 PARA AGREGAR UNA NUEVA BOLITA: 
         1. Copia este bloque completo
         2. Cambia el ID, color y tooltip
         3. Pégalo aquí mismo
    -->
</div>
<!-- ===== FIN MÓDULO 7 ===== -->

<!-- =======================================================
   🚀 MÓDULO 8: PANELES LATERALES (TARIFAS, CLASIFICACIONES, ETC)
   ========================================================
   🔧 PROPÓSITO: Contenedores para las funcionalidades secundarias
   🔧 SI MODIFICAS: Cambias la interfaz de los paneles
   ======================================================== -->
   
<!-- Panel 1: Tarifas -->
<div class="side-panel" id="panel-tarifas">
    <style>
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
    
    .tarjeta-tarifa-acordeon { transition: all 0.3s ease; }
    .tarjeta-tarifa-acordeon:hover { box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); }
    .acordeon-header { transition: background-color 0.2s ease; }
    .acordeon-content { transition: all 0.3s ease; max-height: 0; overflow: hidden; }
    .acordeon-content.expanded { max-height: 2000px; }
    .acordeon-icon { transition: transform 0.3s ease; }
    .acordeon-icon.expanded { transform: rotate(90deg); }
    </style>
    
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
        
        <div id="tarifas_grid" class="grid grid-cols-1 gap-3">
            <?php foreach ($vehiculos as $index => $veh):
                $color_vehiculo = obtenerColorVehiculo($veh);
                $t = $tarifas_guardadas[$veh] ?? [];
                $veh_id = preg_replace('/[^a-z0-9]/i', '-', strtolower($veh));
            ?>
            <div class="tarjeta-tarifa-acordeon rounded-xl border <?= $color_vehiculo['border'] ?> overflow-hidden shadow-sm"
                 data-vehiculo="<?= htmlspecialchars($veh) ?>" id="acordeon-<?= $veh_id ?>">
                
                <div class="acordeon-header flex items-center justify-between px-4 py-3.5 cursor-pointer transition <?= $color_vehiculo['bg'] ?> hover:opacity-90"
                     onclick="toggleAcordeon('<?= $veh_id ?>')">
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
                </div>
                
                <div class="acordeon-content px-4 py-3 border-t <?= $color_vehiculo['border'] ?> bg-white" id="content-<?= $veh_id ?>">
                    <div class="space-y-3">
                        <?php foreach ($columnas_tarifas as $columna): 
                            $valor = isset($t[$columna]) ? (float)$t[$columna] : 0;
                            $etiquetas_especiales = [
                                'completo' => 'Viaje Completo', 'medio' => 'Viaje Medio', 'extra' => 'Viaje Extra',
                                'carrotanque' => 'Carrotanque', 'siapana' => 'Siapana', 'riohacha' => 'Riohacha',
                                'pru' => 'Pru', 'maco' => 'Maco'
                            ];
                            $etiqueta_final = $etiquetas_especiales[$columna] ?? ucfirst($columna);
                            $estilo_clasif = obtenerEstiloClasificacion($columna);
                        ?>
                        <label class="block">
                            <span class="block text-sm font-medium mb-1 <?= $estilo_clasif['text'] ?>">
                                <?= htmlspecialchars($etiqueta_final) ?>
                            </span>
                            <div class="relative">
                                <input type="number" step="1000" value="<?= $valor ?>"
                                       data-campo="<?= htmlspecialchars($columna) ?>"
                                       class="w-full rounded-xl border <?= $estilo_clasif['border'] ?> px-3 py-2 pr-10 text-right bg-white outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition tarifa-input">
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
        
        <p class="text-xs text-slate-500 mt-4">
            Los cambios se guardan automáticamente al modificar cualquier valor.
        </p>
    </div>
</div>

<!-- Panel 2: Crear clasificación -->
<div class="side-panel" id="panel-crear-clasif">
    <div class="side-panel-header">
        <h3 class="text-lg font-semibold flex items-center gap-2">
            <span>➕ Crear Nueva Clasificación</span>
        </h3>
        <button class="side-panel-close" data-panel="crear-clasif">✕</button>
    </div>
    <div class="side-panel-body">
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-2">Nombre de la nueva clasificación</label>
                <input id="txt_nueva_clasificacion" type="text"
                       class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:ring-4 focus:ring-blue-100"
                       placeholder="Ej: Premium, Nocturno, Express...">
            </div>
            <div>
                <label class="block text-sm font-medium mb-2">Texto que deben contener las rutas (opcional)</label>
                <input id="txt_patron_ruta" type="text"
                       class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:ring-4 focus:ring-blue-100"
                       placeholder="Dejar vacío para solo crear la clasificación">
            </div>
            <button type="button"
                    onclick="crearYAsignarClasificacion()"
                    class="w-full inline-flex items-center justify-center rounded-xl bg-green-600 text-white px-4 py-3 text-sm font-semibold hover:bg-green-700 active:bg-green-800 focus:ring-4 focus:ring-green-200 transition">
                ⚙️ Crear y Aplicar
            </button>
        </div>
    </div>
</div>

<!-- Panel 3: Clasificar rutas -->
<div class="side-panel" id="panel-clasif-rutas">
    <style>
    .fila-clasificada-completo { background-color: rgba(209, 250, 229, 0.3) !important; border-left: 4px solid #10b981 !important; }
    .fila-clasificada-medio { background-color: rgba(254, 243, 199, 0.3) !important; border-left: 4px solid #f59e0b !important; }
    .fila-clasificada-extra { background-color: rgba(241, 245, 249, 0.3) !important; border-left: 4px solid #64748b !important; }
    .fila-clasificada-siapana { background-color: rgba(250, 232, 255, 0.3) !important; border-left: 4px solid #d946ef !important; }
    .fila-clasificada-carrotanque { background-color: rgba(207, 250, 254, 0.3) !important; border-left: 4px solid #06b6d4 !important; }
    .fila-clasificada-riohacha { background-color: rgba(224, 231, 255, 0.3) !important; border-left: 4px solid #4f46e5 !important; }
    .fila-clasificada-pru { background-color: rgba(204, 251, 241, 0.3) !important; border-left: 4px solid #14b8a6 !important; }
    .fila-clasificada-maco { background-color: rgba(255, 228, 230, 0.3) !important; border-left: 4px solid #f43f5e !important; }
    </style>
    
    <div class="side-panel-header">
        <h3 class="text-lg font-semibold flex items-center gap-2">
            <span>🧭 Clasificar Rutas</span>
            <span class="text-xs text-slate-500"><?= count($rutasUnicas) ?> rutas</span>
        </h3>
        <button class="side-panel-close" data-panel="clasif-rutas">✕</button>
    </div>
    <div class="side-panel-body">
        <div class="max-h-[calc(100vh-180px)] overflow-y-auto border border-slate-200 rounded-xl">
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
                            <?php $color_vehiculo = obtenerColorVehiculo($info['vehiculo']); ?>
                            <span class="inline-block px-2 py-1 rounded-md text-xs font-medium <?= $color_vehiculo['bg'] ?> <?= $color_vehiculo['text'] ?> border <?= $color_vehiculo['border'] ?>">
                                <?= htmlspecialchars($info['vehiculo']) ?>
                            </span>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <select class="select-clasif-ruta rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-100 w-full transition-all duration-300"
                                    data-ruta="<?= htmlspecialchars($info['ruta']) ?>"
                                    data-vehiculo="<?= htmlspecialchars($info['vehiculo']) ?>"
                                    onchange="actualizarColorFila(this)">
                                <option value="">Sin clasificar</option>
                                <?php foreach ($clasificaciones_disponibles as $clasif): 
                                    $estilo_opcion = obtenerEstiloClasificacion($clasif);
                                ?>
                                <option value="<?= htmlspecialchars($clasif) ?>" 
                                        <?= $info['clasificacion']===$clasif ? 'selected' : '' ?>>
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
    </div>
</div>

<!-- Panel 4: Selector de columnas -->
<div class="side-panel" id="panel-selector-columnas">
    <style>
    .columna-checkbox-item { transition: all 0.2s ease; }
    .columna-checkbox-item:hover { background-color: #f8fafc; }
    .columna-checkbox-item.selected { background-color: #eff6ff; border-color: #3b82f6; }
    .checkbox-columna {
        width: 18px; height: 18px; border-radius: 4px; border: 2px solid #cbd5e1;
        cursor: pointer; display: flex; align-items: center; justify-content: center;
        transition: all 0.2s;
    }
    .checkbox-columna.checked { background-color: #3b82f6; border-color: #3b82f6; }
    .checkbox-columna.checked::after { content: "✓"; color: white; font-size: 12px; font-weight: bold; }
    .columna-oculta { display: none !important; }
    .columna-visualizada { display: table-cell !important; }
    </style>
    
    <div class="side-panel-header">
        <h3 class="text-lg font-semibold flex items-center gap-2">
            <span>📊 Seleccionar Columnas</span>
        </h3>
        <button class="side-panel-close" data-panel="selector-columnas">✕</button>
    </div>
    <div class="side-panel-body">
        <div class="flex flex-col gap-4">
            <p class="text-sm text-slate-600 mb-3">
                <span id="contador-seleccionadas-panel" class="font-semibold text-blue-600"><?= count($columnas_seleccionadas) ?></span> de 
                <?= count($clasificaciones_disponibles) ?> seleccionadas
            </p>
            
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
                    💾 Guardar
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
                        <span class="text-sm font-medium whitespace-nowrap <?= $estilo['text'] ?>">
                            <?php 
                                $nombres = [
                                    'completo' => 'Viaje Completo', 'medio' => 'Viaje Medio', 'extra' => 'Viaje Extra',
                                    'carrotanque' => 'Carrotanque', 'siapana' => 'Siapana', 'riohacha' => 'Riohacha',
                                    'pru' => 'Pru', 'maco' => 'Maco'
                                ];
                                echo $nombres[$clasif] ?? ucfirst($clasif);
                            ?>
                        </span>
                        <span class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($clasif) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Overlay para paneles -->
<div class="side-panel-overlay" id="sidePanelOverlay"></div>
<!-- ===== FIN MÓDULO 8 ===== -->

<!-- =======================================================
   🚀 MÓDULO 9: CONTENIDO PRINCIPAL (TABLA DE CONDUCTORES)
   ========================================================
   🔧 PROPÓSITO: Tabla principal con liquidación
   🔧 CARACTERÍSTICA: Cada fila tiene color según tipo de vehículo
   ======================================================== -->
<main class="max-w-[1800px] mx-auto px-3 md:px-4 py-6">
    <div class="table-container-wrapper" id="tableContainerWrapper">
        
        <div class="mb-4 flex justify-end">
            <button onclick="togglePanel('selector-columnas')" 
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-gradient-to-r from-purple-500 to-indigo-500 text-white hover:from-purple-600 hover:to-indigo-600 transition shadow-md">
                <span>📊</span>
                <span class="text-sm font-medium">Seleccionar columnas</span>
            </button>
        </div>
        
        <style>
        ::-webkit-scrollbar{height:10px;width:10px}
        .buscar-container { position: relative; }
        .buscar-clear { 
            position: absolute; right: 10px; top: 50%; transform: translateY(-50%); 
            background: none; border: none; color: #64748b; cursor: pointer; display: none; 
        }
        
        /* ===== ESTILOS PARA FILAS POR TIPO DE VEHÍCULO (MÁS VISIBLES) ===== */
        .fila-vehiculo-mensual { 
            background-color: #ffedd5 !important;  /* naranja más fuerte */
        }
        .fila-vehiculo-mensual:hover { 
            background-color: #fed7aa !important;  /* naranja más oscuro al hover */
        }
        
        .fila-vehiculo-camioneta { 
            background-color: #dbeafe !important;  /* azul más fuerte */
        }
        .fila-vehiculo-camioneta:hover { 
            background-color: #bfdbfe !important;
        }
        
        .fila-vehiculo-turbo { 
            background-color: #d1fae5 !important;  /* verde más fuerte */
        }
        .fila-vehiculo-turbo:hover { 
            background-color: #a7f3d0 !important;
        }
        
        .fila-vehiculo-camión { 
            background-color: #ede9fe !important;  /* morado más fuerte */
        }
        .fila-vehiculo-camión:hover { 
            background-color: #ddd6fe !important;
        }
        
        .fila-vehiculo-buseta { 
            background-color: #fee2e2 !important;  /* rojo más fuerte */
        }
        .fila-vehiculo-buseta:hover { 
            background-color: #fecaca !important;
        }
        
        .fila-vehiculo-minivan { 
            background-color: #ccfbf1 !important;  /* teal más fuerte */
        }
        .fila-vehiculo-minivan:hover { 
            background-color: #99f6e4 !important;
        }
        
        .fila-vehiculo-automóvil { 
            background-color: #ffe4e6 !important;  /* rosa más fuerte */
        }
        .fila-vehiculo-automóvil:hover { 
            background-color: #fecdd3 !important;
        }
        
        .fila-vehiculo-moto { 
            background-color: #e0e7ff !important;  /* indigo más fuerte */
        }
        .fila-vehiculo-moto:hover { 
            background-color: #c7d2fe !important;
        }
        
        .fila-vehiculo-furgoneta { 
            background-color: #fef3c7 !important;  /* amarillo más fuerte */
        }
        .fila-vehiculo-furgoneta:hover { 
            background-color: #fde68a !important;
        }
        
        /* Clase por defecto para tipos no definidos */
        .fila-vehiculo-default { 
            background-color: #f1f5f9 !important;  /* gris más fuerte */
        }
        .fila-vehiculo-default:hover { 
            background-color: #e2e8f0 !important;
        }
        
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
        .conductor-link{ 
            cursor:pointer; 
            color:#0d6efd; 
            text-decoration:underline; 
        }
        
        /* Asegurar que las celdas también hereden el color de fondo */
        #tabla_conductores tbody tr td {
            background-color: inherit !important;
        }
        </style>

        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-200">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-xl font-semibold">🧑‍✈️ Resumen por Conductor</h3>
                        <div id="contador-conductores" class="text-sm text-slate-500 mt-1">
                            Mostrando <?= count($datos) ?> conductores
                        </div>
                    </div>
                    <button onclick="mostrarResumenRutasSinClasificar()" 
                            class="text-sm px-4 py-2 rounded-full bg-gradient-to-r from-amber-500 to-orange-500 text-white hover:from-amber-600 hover:to-orange-600 transition flex items-center gap-2 shadow-md">
                        ⚠️ Ver rutas sin clasificar
                    </button>
                </div>
            </div>

            <div class="p-5">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                    <div class="flex flex-col md:flex-row gap-4 w-full md:w-auto">
                        <div class="buscar-container w-full md:w-64">
                            <input id="buscadorConductores" type="text" 
                                   placeholder="Buscar conductor..." 
                                   class="w-full rounded-xl border border-slate-300 px-4 py-3 pl-4 pr-10 text-sm">
                            <button id="clearBuscar" class="buscar-clear">✕</button>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-4 py-2 text-blue-700 font-semibold text-sm">
                                🔢 Viajes: <span id="total_viajes">0</span>
                            </span>
                            <span class="inline-flex items-center gap-2 rounded-full border border-purple-200 bg-purple-50 px-4 py-2 text-purple-700 font-semibold text-sm">
                                💰 Total: <span id="total_general">0</span>
                            </span>
                            <span class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-emerald-700 font-semibold text-sm">
                                ✅ Pagado: <span id="total_pagado">0</span>
                            </span>
                            <span class="inline-flex items-center gap-2 rounded-full border border-rose-200 bg-rose-50 px-4 py-2 text-rose-700 font-semibold text-sm">
                                ⏳ Faltante: <span id="total_faltante">0</span>
                            </span>
                        </div>
                    </div>
                </div>

                <div id="resumenRutasSinClasificar" class="hidden mb-6">
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <span class="text-amber-600 font-bold text-lg">⚠️</span>
                                <h4 class="font-semibold text-amber-800">Rutas sin clasificar</h4>
                            </div>
                            <span id="contadorRutasSinClasificarGlobal" class="px-3 py-1 bg-amber-500 text-white text-sm font-bold rounded-full">0</span>
                        </div>
                        <div id="listaRutasSinClasificarGlobal" class="space-y-2 max-h-60 overflow-y-auto"></div>
                        <div class="mt-4 pt-4 border-t border-amber-100">
                            <button onclick="irAClasificacionRutas()" 
                                    class="w-full py-3 bg-amber-100 text-amber-700 hover:bg-amber-200 rounded-xl text-sm font-medium transition flex items-center justify-center gap-2">
                                🧭 Ir a clasificar rutas
                            </button>
                        </div>
                    </div>
                </div>

                <div id="tableContainer" class="overflow-x-auto rounded-xl border border-slate-200 max-h-[70vh]">
                    <table id="tabla_conductores" class="w-full text-sm">
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
                                ?>
                                <th class="px-4 py-3 text-center sticky top-0 <?= $clase_visibilidad ?> columna-tabla" 
                                    data-columna="<?= htmlspecialchars($clasif) ?>"
                                    style="min-width: 80px;">
                                    <?= htmlspecialchars($abreviatura) ?>
                                </th>
                                <?php endforeach; ?>
                                
                                <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 140px;">Total</th>
                                <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 120px;">Pagado</th>
                                <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 100px;">Faltante</th>
                            </tr>
                        </thead>
                        <tbody id="tabla_conductores_body" class="divide-y divide-slate-100">
                        <?php foreach ($datos as $conductor => $info): 
                            $vehiculo = $info['vehiculo'];
                            $esMensual = (stripos($vehiculo, 'mensual') !== false);
                            $claseVehiculoBadge = $esMensual ? 'vehiculo-mensual' : '';
                            $rutasSinClasificar = $info['rutas_sin_clasificar'] ?? 0;
                            $color_vehiculo = obtenerColorVehiculo($vehiculo);
                            
                            // Determinar la clase CSS para la fila según el tipo de vehículo
                            $vehiculo_lower = strtolower($vehiculo);
                            $clase_fila = 'fila-vehiculo-default'; // Por defecto
                            
                            // Mapear tipos de vehículo a clases específicas
                            $mapa_clases = [
                                'mensual' => 'fila-vehiculo-mensual',
                                'camioneta' => 'fila-vehiculo-camioneta',
                                'turbo' => 'fila-vehiculo-turbo',
                                'camión' => 'fila-vehiculo-camión',
                                'buseta' => 'fila-vehiculo-buseta',
                                'minivan' => 'fila-vehiculo-minivan',
                                'automóvil' => 'fila-vehiculo-automóvil',
                                'moto' => 'fila-vehiculo-moto',
                                'furgoneta' => 'fila-vehiculo-furgoneta'
                            ];
                            
                            foreach ($mapa_clases as $tipo => $clase) {
                                if (strpos($vehiculo_lower, $tipo) !== false) {
                                    $clase_fila = $clase;
                                    break;
                                }
                            }
                        ?>
                            <tr data-vehiculo="<?= htmlspecialchars($info['vehiculo']) ?>" 
                                data-conductor="<?= htmlspecialchars($conductor) ?>" 
                                data-conductor-normalizado="<?= htmlspecialchars(mb_strtolower($conductor)) ?>"
                                data-pagado="<?= (int)($info['pagado'] ?? 0) ?>"
                                data-sin-clasificar="<?= $rutasSinClasificar ?>"
                                class="<?= $clase_fila ?> hover:bg-opacity-80 transition-colors <?php echo $rutasSinClasificar > 0 ? 'alerta-sin-clasificar' : ''; ?>">
                                
                                <td class="px-4 py-3 text-center">
                                    <?php if ($rutasSinClasificar > 0): ?>
                                        <div class="flex flex-col items-center justify-center gap-1">
                                            <span class="text-amber-600 font-bold animate-pulse">⚠️</span>
                                            <span class="text-xs bg-amber-100 text-amber-800 px-2 py-1 rounded-full font-bold">
                                                <?= $rutasSinClasificar ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex flex-col items-center justify-center gap-1">
                                            <span class="text-emerald-600">✅</span>
                                            <span class="text-xs bg-emerald-100 text-emerald-800 px-2 py-1 rounded-full font-bold">0</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-4 py-3">
                                    <button type="button"
                                            class="conductor-link text-blue-700 hover:text-blue-900 underline underline-offset-2 transition flex items-center gap-2">
                                        <?php if ($rutasSinClasificar > 0): ?>
                                            <span class="text-amber-600">⚠️</span>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($conductor) ?>
                                    </button>
                                </td>
                                
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-block <?= $claseVehiculoBadge ?> px-3 py-1.5 rounded-lg text-xs font-medium border <?= $color_vehiculo['border'] ?> <?= $color_vehiculo['text'] ?> <?= $color_vehiculo['bg'] ?>">
                                        <?= htmlspecialchars($info['vehiculo']) ?>
                                    </span>
                                </td>
                                
                                <?php foreach ($clasificaciones_disponibles as $clasif): 
                                    $cantidad = (int)($info[$clasif] ?? 0);
                                    $visible = in_array($clasif, $columnas_seleccionadas);
                                    $clase_visibilidad = $visible ? 'columna-visualizada' : 'columna-oculta';
                                    $estilo = obtenerEstiloClasificacion($clasif);
                                ?>
                                <td class="px-4 py-3 text-center font-medium <?= $clase_visibilidad ?> columna-tabla" 
                                    data-columna="<?= htmlspecialchars($clasif) ?>"
                                    style="min-width: 80px;">
                                    <?= $cantidad ?>
                                </td>
                                <?php endforeach; ?>

                                <td class="px-4 py-3">
                                    <input type="text"
                                           class="totales w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-white bg-opacity-50 outline-none whitespace-nowrap tabular-nums"
                                           readonly>
                                </td>

                                <td class="px-4 py-3">
                                    <input type="text"
                                           class="pagado w-full rounded-xl border border-emerald-200 px-3 py-2 text-right bg-emerald-50 bg-opacity-50 outline-none whitespace-nowrap tabular-nums"
                                           readonly
                                           value="<?= number_format((int)($info['pagado'] ?? 0), 0, ',', '.') ?>">
                                </td>

                                <td class="px-4 py-3">
                                    <input type="text"
                                           class="faltante w-full rounded-xl border border-rose-200 px-3 py-2 text-right bg-rose-50 bg-opacity-50 outline-none whitespace-nowrap tabular-nums"
                                           readonly>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
<!-- ===== FIN MÓDULO 9 ===== -->

<!-- =======================================================
   🚀 MÓDULO 10: MODAL DE VIAJES
   ========================================================
   🔧 PROPÓSITO: Mostrar viajes detallados por conductor
   🔧 SI MODIFICAS: Cambias la vista de viajes individuales
   ======================================================== -->
<div id="viajesModal" class="viajes-backdrop">
    <style>
    .viajes-backdrop{ 
        position:fixed; inset:0; background:rgba(0,0,0,.45); display:none; 
        align-items:center; justify-content:center; z-index:10000; 
    }
    .viajes-backdrop.show{ display:flex; }
    .viajes-card{ 
        width:min(720px,94vw); max-height:90vh; overflow:hidden; border-radius:16px; 
        background:#fff; box-shadow:0 20px 60px rgba(0,0,0,.25); border:1px solid #e5e7eb; 
    }
    .viajes-header{ padding:14px 16px; border-bottom:1px solid #eef2f7; }
    .viajes-body{ padding:14px 16px; overflow:auto; max-height:70vh; }
    .viajes-close{ padding:6px 10px; border-radius:10px; cursor:pointer; }
    .viajes-close:hover{ background:#f3f4f6; }
    </style>
    
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
                            class="rounded-lg border border-slate-300 px-2 py-1 text-sm min-w-[200px] focus:outline-none focus:ring-2 focus:ring-blue-500/40">
                    </select>
                    <button class="viajes-close text-slate-600 hover:bg-slate-100 border border-slate-300 px-2 py-1 rounded-lg text-sm" id="viajesCloseBtn">
                        ✕
                    </button>
                </div>
            </div>
        </div>

        <div class="viajes-body" id="viajesContent"></div>
    </div>
</div>
<!-- ===== FIN MÓDULO 10 ===== -->

<!-- =======================================================
   🚀 MÓDULO 11: JAVASCRIPT PRINCIPAL (TODAS LAS FUNCIONES)
   ========================================================
   🔧 PROPÓSITO: Lógica de negocio del sistema
   🔧 SI MODIFICAS: Cambias el comportamiento de la app
   ======================================================== -->
<script>
// ===== VARIABLES GLOBALES =====
let activePanel = null;
const RANGO_DESDE = <?= json_encode($desde) ?>;
const RANGO_HASTA = <?= json_encode($hasta) ?>;
const RANGO_EMP   = <?= json_encode($empresaFiltro) ?>;
const CONDUCTORES_LIST = <?= json_encode(array_keys($datos), JSON_UNESCAPED_UNICODE); ?>;
let columnasSeleccionadas = <?= json_encode($columnas_seleccionadas) ?>;

// ===== SISTEMA DE PANELES =====
function togglePanel(panelId) {
    const ball = document.getElementById(`ball-${panelId}`);
    const panel = document.getElementById(`panel-${panelId}`);
    const overlay = document.getElementById('sidePanelOverlay');
    const tableWrapper = document.getElementById('tableContainerWrapper');
    
    if (activePanel === panelId) {
        panel?.classList.remove('active');
        ball?.classList.remove('ball-active');
        overlay?.classList.remove('active');
        tableWrapper?.classList.remove('with-panel');
        activePanel = null;
    } else {
        if (activePanel) {
            document.getElementById(`panel-${activePanel}`)?.classList.remove('active');
            document.getElementById(`ball-${activePanel}`)?.classList.remove('ball-active');
        }
        
        panel?.classList.add('active');
        ball?.classList.add('ball-active');
        overlay?.classList.add('active');
        tableWrapper?.classList.add('with-panel');
        activePanel = panelId;
    }
}

// ===== FUNCIONES DE TARIFAS =====
function toggleAcordeon(vehiculoId) {
    const content = document.getElementById('content-' + vehiculoId);
    const icon = document.getElementById('icon-' + vehiculoId);
    
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

function expandirTodosTarifas() {
    document.querySelectorAll('.acordeon-content').forEach(content => {
        if (!content.classList.contains('expanded')) {
            content.classList.add('expanded');
            content.style.maxHeight = content.scrollHeight + 'px';
            const icon = document.getElementById('icon-' + content.id.replace('content-', ''));
            if (icon) icon.classList.add('expanded');
        }
    });
}

function colapsarTodosTarifas() {
    document.querySelectorAll('.acordeon-content').forEach(content => {
        if (content.classList.contains('expanded')) {
            content.classList.remove('expanded');
            content.style.maxHeight = '0';
            const icon = document.getElementById('icon-' + content.id.replace('content-', ''));
            if (icon) icon.classList.remove('expanded');
        }
    });
}

function getTarifas(){
    const tarifas = {};
    document.querySelectorAll('.tarjeta-tarifa-acordeon').forEach(card=>{
        const veh = card.dataset.vehiculo;
        tarifas[veh] = {};
        card.querySelectorAll('input[data-campo]').forEach(input=>{
            const campo = input.dataset.campo.toLowerCase();
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
    const todasColumnas = <?= json_encode($clasificaciones_disponibles) ?>;
    
    let totalViajes = 0, totalPagado = 0, totalFaltante = 0;

    filas.forEach(fila => {
        if (fila.style.display === 'none') return;

        const veh = fila.dataset.vehiculo;
        const tarifasVeh = tarifas[veh] || {};
        let totalFila = 0;
        
        todasColumnas.forEach(columna => {
            const celda = fila.querySelector(`td[data-columna="${columna}"]`);
            const cantidad = parseInt(celda?.textContent || 0);
            const tarifa = tarifasVeh[columna] || 0;
            totalFila += cantidad * tarifa;
        });

        const pagado = parseInt(fila.dataset.pagado || '0') || 0;
        let faltante = Math.max(totalFila - pagado, 0);

        fila.querySelector('input.totales').value = formatNumber(totalFila);
        fila.querySelector('input.faltante').value = formatNumber(faltante);

        totalViajes += totalFila;
        totalPagado += pagado;
        totalFaltante += faltante;
    });

    document.getElementById('total_viajes').innerText = formatNumber(totalViajes);
    document.getElementById('total_general').innerText = formatNumber(totalViajes);
    document.getElementById('total_pagado').innerText = formatNumber(totalPagado);
    document.getElementById('total_faltante').innerText = formatNumber(totalFaltante);
}

// ===== CLASIFICACIÓN DE RUTAS =====
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
    if (!clasificacion) return;
    fetch('<?= basename(__FILE__) ?>', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({
            guardar_clasificacion:1,
            ruta:ruta,
            tipo_vehiculo:vehiculo,
            clasificacion:clasificacion.toLowerCase()
        })
    });
}

// ===== SELECCIÓN DE COLUMNAS =====
function toggleColumna(columna) {
    const checkbox = document.getElementById('checkbox-' + columna);
    const item = document.querySelector('[data-columna="' + columna + '"]');
    
    if (columnasSeleccionadas.includes(columna)) {
        columnasSeleccionadas = columnasSeleccionadas.filter(c => c !== columna);
        checkbox?.classList.remove('checked');
        item?.classList.remove('selected');
    } else {
        columnasSeleccionadas.push(columna);
        checkbox?.classList.add('checked');
        item?.classList.add('selected');
    }
    
    actualizarContadorColumnas();
    actualizarColumnasTabla();
}

function seleccionarTodasColumnas() {
    document.querySelectorAll('.columna-checkbox-item').forEach(item => {
        const columna = item.dataset.columna;
        if (!columnasSeleccionadas.includes(columna)) {
            columnasSeleccionadas.push(columna);
            document.getElementById('checkbox-' + columna)?.classList.add('checked');
            item.classList.add('selected');
        }
    });
    actualizarContadorColumnas();
    actualizarColumnasTabla();
}

function deseleccionarTodasColumnas() {
    columnasSeleccionadas = [];
    document.querySelectorAll('.columna-checkbox-item').forEach(item => {
        const columna = item.dataset.columna;
        document.getElementById('checkbox-' + columna)?.classList.remove('checked');
        item.classList.remove('selected');
    });
    actualizarContadorColumnas();
    actualizarColumnasTabla();
}

function actualizarContadorColumnas() {
    document.getElementById('contador-seleccionadas-panel').textContent = columnasSeleccionadas.length;
    document.getElementById('contador-columnas-visibles').textContent = columnasSeleccionadas.length;
    if (document.getElementById('contador-columnas-visibles-header')) {
        document.getElementById('contador-columnas-visibles-header').textContent = columnasSeleccionadas.length;
    }
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
    const desde = "<?= htmlspecialchars($desde) ?>";
    const hasta = "<?= htmlspecialchars($hasta) ?>";
    const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
    
    fetch('<?= basename(__FILE__) ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            guardar_columnas_seleccionadas: 1,
            columnas: JSON.stringify(columnasSeleccionadas),
            desde: desde,
            hasta: hasta,
            empresa: empresa
        })
    })
    .then(r => r.text())
    .then(respuesta => {
        if (respuesta.trim() === 'ok') {
            mostrarNotificacion('✅ Selección guardada', 'success');
        }
    });
}

// ===== FUNCIONES DE UTILIDAD =====
function mostrarNotificacion(mensaje, tipo) {
    const notificacion = document.createElement('div');
    notificacion.className = `fixed top-4 right-4 px-4 py-3 rounded-lg shadow-lg z-[10001] animate-fade-in-down ${
        tipo === 'success' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 
        'bg-rose-100 text-rose-800 border border-rose-200'
    }`;
    notificacion.innerHTML = `<div class="flex items-center gap-2"><span class="text-lg">${tipo === 'success' ? '✅' : '❌'}</span><span class="font-medium">${mensaje}</span></div>`;
    document.body.appendChild(notificacion);
    setTimeout(() => notificacion.remove(), 3000);
}

function normalizarTexto(texto) {
    return texto.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
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
    
    document.getElementById('contador-conductores').textContent = `Mostrando ${filasVisibles} de ${filas.length} conductores`;
    recalcular();
}

// ===== FUNCIONES DEL MODAL DE VIAJES =====
let viajesConductorActual = null;

function initViajesSelect(selectedName) {
    const select = document.getElementById('viajesSelectConductor');
    select.innerHTML = "";
    CONDUCTORES_LIST.forEach(nombre => {
        const opt = document.createElement('option');
        opt.value = nombre;
        opt.textContent = nombre;
        if (nombre === selectedName) opt.selected = true;
        select.appendChild(opt);
    });
}

function loadViajes(nombre) {
    const content = document.getElementById('viajesContent');
    content.innerHTML = '<p class="text-center m-0 animate-pulse">Cargando…</p>';
    viajesConductorActual = nombre;
    document.getElementById('viajesTitle').textContent = nombre;

    const qs = new URLSearchParams({
        viajes_conductor: nombre,
        desde: RANGO_DESDE,
        hasta: RANGO_HASTA,
        empresa: RANGO_EMP
    });

    fetch('<?= basename(__FILE__) ?>?' + qs.toString())
        .then(r => r.text())
        .then(html => {
            content.innerHTML = html;
        });
}

function abrirModalViajes(nombreInicial){
    document.getElementById('viajesRango').textContent = RANGO_DESDE + " → " + RANGO_HASTA;
    document.getElementById('viajesEmpresa').textContent = (RANGO_EMP && RANGO_EMP !== "") ? RANGO_EMP : "Todas las empresas";
    initViajesSelect(nombreInicial);
    document.getElementById('viajesModal').classList.add('show');
    loadViajes(nombreInicial);
}

function cerrarModalViajes(){
    document.getElementById('viajesModal').classList.remove('show');
    document.getElementById('viajesContent').innerHTML = '';
    viajesConductorActual = null;
}

// ===== RUTAS SIN CLASIFICAR =====
function mostrarResumenRutasSinClasificar() {
    const resumenDiv = document.getElementById('resumenRutasSinClasificar');
    const listaDiv = document.getElementById('listaRutasSinClasificarGlobal');
    const contadorSpan = document.getElementById('contadorRutasSinClasificarGlobal');
    
    const filas = document.querySelectorAll('#tabla_conductores_body tr');
    let totalRutasSinClasificar = 0;
    let contenidoHTML = '';
    
    filas.forEach(fila => {
        const sinClasificar = parseInt(fila.dataset.sinClasificar || '0');
        if (sinClasificar > 0) {
            totalRutasSinClasificar += sinClasificar;
            const conductor = fila.querySelector('.conductor-link').textContent;
            contenidoHTML += `
                <div class="flex items-center justify-between p-3 bg-amber-50 rounded-lg border border-amber-100 hover:bg-amber-100 transition">
                    <div class="flex items-center gap-2">
                        <span class="text-amber-600">⚠️</span>
                        <span class="font-medium text-amber-800">${conductor}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs bg-amber-500 text-white px-2 py-1 rounded-full">${sinClasificar}</span>
                        <button onclick="verViajesConductor('${conductor}')" class="text-xs text-amber-600 hover:text-amber-800 hover:underline">Ver viajes</button>
                    </div>
                </div>
            `;
        }
    });
    
    if (totalRutasSinClasificar > 0) {
        contadorSpan.textContent = totalRutasSinClasificar;
        listaDiv.innerHTML = contenidoHTML;
        resumenDiv.classList.remove('hidden');
        resumenDiv.scrollIntoView({ behavior: 'smooth' });
    } else {
        listaDiv.innerHTML = '<div class="text-center py-4 text-amber-600">🎉 ¡Excelente! Todas las rutas están clasificadas.</div>';
        contadorSpan.textContent = '0';
        resumenDiv.classList.remove('hidden');
    }
}

function verViajesConductor(nombre) {
    document.querySelectorAll('.conductor-link').forEach(boton => {
        if (boton.textContent.trim() === nombre.trim()) {
            boton.click();
        }
    });
    document.getElementById('resumenRutasSinClasificar').classList.add('hidden');
}

function irAClasificacionRutas() {
    togglePanel('clasif-rutas');
    document.getElementById('resumenRutasSinClasificar').classList.add('hidden');
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
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({
            crear_clasificacion:1,
            nombre_clasificacion:nombreClasif
        })
    })
    .then(r=>r.text())
    .then(respuesta=>{
        if (respuesta.trim() === 'ok') {
            if (patronRuta) {
                document.querySelectorAll('.fila-ruta').forEach(row => {
                    const ruta = row.dataset.ruta.toLowerCase();
                    if (ruta.includes(patronRuta)) {
                        const sel = row.querySelector('.select-clasif-ruta');
                        sel.value = nombreClasif.toLowerCase();
                        actualizarColorFila(sel);
                    }
                });
                alert('✅ Creado y aplicado a rutas. Recarga la página.');
            } else {
                alert('✅ Clasificación creada. Recarga la página.');
            }
            document.getElementById('txt_nueva_clasificacion').value = '';
            document.getElementById('txt_patron_ruta').value = '';
        } else {
            alert('❌ Error: ' + respuesta);
        }
    });
}

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', function() {
    // Configurar eventos de tarifas
    document.addEventListener('change', function(e) {
        if (e.target.matches('.tarifa-input')) {
            const input = e.target;
            const card = input.closest('.tarjeta-tarifa-acordeon');
            const tipoVehiculo = card.dataset.vehiculo;
            const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
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
                    recalcular();
                } else {
                    input.value = input.defaultValue;
                }
            })
            .catch(() => input.value = input.defaultValue);
        }
    });
    
    // Click en conductor
    document.querySelectorAll('.conductor-link').forEach(btn=>{
        btn.addEventListener('click', (e)=>{
            e.preventDefault();
            abrirModalViajes(btn.textContent.trim().replace('⚠️', '').trim());
        });
    });
    
    // Cambio en clasificación de rutas
    document.querySelectorAll('.select-clasif-ruta').forEach(sel=>{
        sel.addEventListener('change', function() {
            actualizarColorFila(this);
        });
    });
    
    // Buscador
    const buscadorConductores = document.getElementById('buscadorConductores');
    const clearBuscar = document.getElementById('clearBuscar');
    const tablaConductoresBody = document.getElementById('tabla_conductores_body');
    
    buscadorConductores?.addEventListener('input', filtrarConductores);
    clearBuscar?.addEventListener('click', () => {
        buscadorConductores.value = '';
        filtrarConductores();
        buscadorConductores.focus();
    });
    
    // Cerrar modal
    document.getElementById('viajesCloseBtn')?.addEventListener('click', cerrarModalViajes);
    document.getElementById('viajesModal')?.addEventListener('click', (e)=>{
        if(e.target === document.getElementById('viajesModal')) cerrarModalViajes();
    });
    
    // Cambio de conductor en modal
    document.getElementById('viajesSelectConductor')?.addEventListener('change', ()=>{
        loadViajes(document.getElementById('viajesSelectConductor').value);
    });
    
    // Inicializar columnas seleccionadas
    columnasSeleccionadas.forEach(columna => {
        document.getElementById('checkbox-' + columna)?.classList.add('checked');
        document.querySelector('[data-columna="' + columna + '"]')?.classList.add('selected');
    });
    actualizarContadorColumnas();
    actualizarColumnasTabla();
    
    // Recalcular
    recalcular();
    
    // Mostrar resumen si hay rutas sin clasificar
    const totalSinClasificar = <?= array_sum(array_column($datos, 'rutas_sin_clasificar')) ?>;
    if (totalSinClasificar > 0) mostrarResumenRutasSinClasificar();
    
    // Configurar paneles
    document.querySelectorAll('.floating-ball').forEach(ball => {
        ball.addEventListener('click', () => togglePanel(ball.dataset.panel));
    });
    
    document.querySelectorAll('.side-panel-close').forEach(btn => {
        btn.addEventListener('click', () => togglePanel(btn.dataset.panel));
    });
    
    document.getElementById('sidePanelOverlay')?.addEventListener('click', () => {
        if (activePanel) togglePanel(activePanel);
    });
});
</script>
<!-- ===== FIN MÓDULO 11 ===== -->
<?php
/* =======================================================
   🚀 MÓDULO 12: SISTEMA DE ALERTAS Y PRESUPUESTOS
   ========================================================
   🔧 PROPÓSITO: Gestión de presupuestos por empresa y alertas
   🔧 CARACTERÍSTICAS: 
        - Ver presupuestos con filtro múltiple
        - Configurar presupuestos por empresa/mes
        - Reportes comparativos
        - Alertas automáticas
   ======================================================== */

// ===== FUNCIONES DE BASE DE DATOS =====
function obtenerEmpresasConViajes($conn) {
    $sql = "SELECT DISTINCT empresa FROM viajes 
            WHERE empresa IS NOT NULL AND empresa != ''
            ORDER BY empresa ASC";
    $result = $conn->query($sql);
    $empresas = [];
    while ($row = $result->fetch_assoc()) {
        $empresas[] = $row['empresa'];
    }
    return $empresas;
}

function obtenerPresupuestosEmpresa($conn, $mes = null, $anio = null, $empresas_filtro = []) {
    if ($mes === null) $mes = date('n');
    if ($anio === null) $anio = date('Y');
    
    $sql = "SELECT * FROM presupuestos_empresa 
            WHERE mes = ? AND anio = ? AND activo = 1";
    
    $params = [$mes, $anio];
    $types = "ii";
    
    if (!empty($empresas_filtro)) {
        $placeholders = implode(',', array_fill(0, count($empresas_filtro), '?'));
        $sql .= " AND empresa IN ($placeholders)";
        $params = array_merge($params, $empresas_filtro);
        $types .= str_repeat('s', count($empresas_filtro));
    }
    
    $sql .= " ORDER BY empresa ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $presupuestos = [];
    while ($row = $result->fetch_assoc()) {
        $presupuestos[] = $row;
    }
    $stmt->close();
    return $presupuestos;
}

function guardarPresupuestoEmpresa($conn, $empresa, $presupuesto, $mes, $anio) {
    // Verificar si ya existe
    $sql = "SELECT id FROM presupuestos_empresa 
            WHERE empresa = ? AND mes = ? AND anio = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $empresa, $mes, $anio);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Actualizar
        $sql = "UPDATE presupuestos_empresa SET presupuesto = ?, activo = 1, notificado = 0 
                WHERE empresa = ? AND mes = ? AND anio = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("dsii", $presupuesto, $empresa, $mes, $anio);
    } else {
        // Insertar nuevo
        $sql = "INSERT INTO presupuestos_empresa (empresa, presupuesto, mes, anio, activo, notificado) 
                VALUES (?, ?, ?, ?, 1, 0)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sdii", $empresa, $presupuesto, $mes, $anio);
    }
    
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function calcularGastosEmpresa($conn, $empresa, $mes, $anio) {
    $gastos_totales = 0;
    
    $sql = "SELECT v.ruta, v.tipo_vehiculo, v.fecha 
            FROM viajes v 
            WHERE v.empresa = ? 
            AND MONTH(v.fecha) = ? 
            AND YEAR(v.fecha) = ?
            AND v.ruta IS NOT NULL 
            AND v.tipo_vehiculo IS NOT NULL";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $empresa, $mes, $anio);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($viaje = $result->fetch_assoc()) {
        $clasificacion = obtenerClasificacionRuta($conn, $viaje['ruta'], $viaje['tipo_vehiculo']);
        
        if ($clasificacion) {
            $tarifa = obtenerTarifa($conn, $empresa, $viaje['tipo_vehiculo'], $clasificacion);
            if ($tarifa > 0) {
                $gastos_totales += $tarifa;
            }
        }
    }
    
    $stmt->close();
    return $gastos_totales;
}

function obtenerClasificacionRuta($conn, $ruta, $tipo_vehiculo) {
    $sql = "SELECT clasificacion FROM ruta_clasificacion 
            WHERE ruta = ? AND tipo_vehiculo = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $ruta, $tipo_vehiculo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $clasificacion = $row['clasificacion'];
    } else {
        // Si no encuentra exacto, buscar solo por ruta
        $sql = "SELECT clasificacion FROM ruta_clasificacion 
                WHERE ruta = ? LIMIT 1";
        $stmt2 = $conn->prepare($sql);
        $stmt2->bind_param("s", $ruta);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        if ($row2 = $result2->fetch_assoc()) {
            $clasificacion = $row2['clasificacion'];
        } else {
            $clasificacion = null;
        }
        $stmt2->close();
    }
    
    $stmt->close();
    return $clasificacion;
}

function obtenerTarifa($conn, $empresa, $tipo_vehiculo, $clasificacion) {
    // Primero intentar con empresa exacta
    $sql = "SELECT $clasificacion as tarifa FROM tarifas 
            WHERE empresa = ? AND tipo_vehiculo = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $empresa, $tipo_vehiculo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $tarifa = $row['tarifa'] ?? 0;
    } else {
        $tarifa = 0;
    }
    
    $stmt->close();
    return floatval($tarifa);
}

function obtenerMesesConPresupuestos($conn) {
    $sql = "SELECT DISTINCT mes, anio FROM presupuestos_empresa 
            WHERE activo = 1 ORDER BY anio DESC, mes DESC";
    $result = $conn->query($sql);
    $periodos = [];
    while ($row = $result->fetch_assoc()) {
        $periodos[] = $row;
    }
    return $periodos;
}

function nombreMes($mes) {
    $meses = [
        1 => "Enero", 2 => "Febrero", 3 => "Marzo", 4 => "Abril",
        5 => "Mayo", 6 => "Junio", 7 => "Julio", 8 => "Agosto",
        9 => "Septiembre", 10 => "Octubre", 11 => "Noviembre", 12 => "Diciembre"
    ];
    return $meses[$mes] ?? "Desconocido";
}

// ===== PROCESAR PETICIONES =====
if (isset($_POST['guardar_presupuesto'])) {
    $empresa = $conn->real_escape_string($_POST['empresa']);
    $presupuesto = floatval($_POST['presupuesto']);
    $mes = intval($_POST['mes']);
    $anio = intval($_POST['anio']);
    
    if (guardarPresupuestoEmpresa($conn, $empresa, $presupuesto, $mes, $anio)) {
        echo json_encode(['success' => true, 'message' => 'Presupuesto guardado']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar']);
    }
    exit;
}

if (isset($_POST['eliminar_presupuesto'])) {
    $id = intval($_POST['id']);
    $sql = "UPDATE presupuestos_empresa SET activo = 0 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $success = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => $success]);
    exit;
}

// ===== INTERFAZ DE USUARIO =====
?>
<!-- Panel 5: Sistema de Alertas y Presupuestos -->
<div class="side-panel" id="panel-presupuestos">
    <div class="side-panel-header">
        <h3 class="text-lg font-semibold flex items-center gap-2">
            <span>🚨 Sistema de Alertas por Presupuesto</span>
        </h3>
        <button class="side-panel-close" data-panel="presupuestos">✕</button>
    </div>
    
    <div class="side-panel-body">
        <!-- Pestañas de navegación -->
        <div class="flex border-b border-slate-200 mb-4">
            <button class="tab-btn active px-4 py-2 text-sm font-medium text-blue-600 border-b-2 border-blue-600" data-tab="ver">
                📊 Ver presupuestos
            </button>
            <button class="tab-btn px-4 py-2 text-sm font-medium text-slate-600 hover:text-blue-600" data-tab="configurar">
                ⚙️ Configurar
            </button>
            <button class="tab-btn px-4 py-2 text-sm font-medium text-slate-600 hover:text-blue-600" data-tab="reporte">
                📈 Reporte
            </button>
        </div>
        
        <!-- Pestaña: Ver presupuestos -->
        <div class="tab-content active" id="tab-ver">
            <div class="space-y-4">
                <!-- Filtro de período -->
                <div class="bg-slate-50 p-3 rounded-lg">
                    <label class="block text-xs font-medium text-slate-600 mb-2">Período:</label>
                    <select id="filtro-periodo" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <?php
                        $periodos = obtenerMesesConPresupuestos($conn);
                        $mes_actual = date('n');
                        $anio_actual = date('Y');
                        $hay_periodo_actual = false;
                        
                        foreach ($periodos as $p) {
                            $selected = ($p['mes'] == $mes_actual && $p['anio'] == $anio_actual) ? 'selected' : '';
                            if ($selected) $hay_periodo_actual = true;
                            echo "<option value='{$p['mes']}-{$p['anio']}' $selected>" . nombreMes($p['mes']) . " {$p['anio']}</option>";
                        }
                        
                        if (!$hay_periodo_actual) {
                            echo "<option value='$mes_actual-$anio_actual' selected>" . nombreMes($mes_actual) . " $anio_actual (Actual)</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <!-- Filtro de empresas -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-medium text-slate-600">Empresas:</label>
                        <div class="flex gap-2">
                            <button id="seleccionar-todas-p" class="text-xs px-2 py-1 rounded bg-purple-100 text-purple-700 hover:bg-purple-200">
                                🔘 Seleccionar P.
                            </button>
                            <button id="seleccionar-todas" class="text-xs px-2 py-1 rounded bg-blue-100 text-blue-700 hover:bg-blue-200">
                                ✅ Todas
                            </button>
                            <button id="limpiar-seleccion" class="text-xs px-2 py-1 rounded bg-slate-100 text-slate-700 hover:bg-slate-200">
                                ✕ Limpiar
                            </button>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-2 max-h-60 overflow-y-auto p-2 border border-slate-200 rounded-lg">
                        <?php
                        $empresas = obtenerEmpresasConViajes($conn);
                        foreach ($empresas as $empresa):
                        ?>
                        <label class="flex items-center gap-2 p-2 border border-slate-100 rounded-lg hover:bg-slate-50 cursor-pointer empresa-checkbox-item">
                            <input type="checkbox" class="empresa-checkbox rounded border-slate-300" value="<?= htmlspecialchars($empresa) ?>">
                            <span class="text-sm truncate" title="<?= htmlspecialchars($empresa) ?>"><?= htmlspecialchars($empresa) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <button id="btn-ver-presupuestos" class="w-full py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                    🔍 Ver presupuestos seleccionados
                </button>
                
                <!-- Resultados -->
                <div id="resultado-presupuestos" class="mt-4 space-y-3"></div>
            </div>
        </div>
        
        <!-- Pestaña: Configurar presupuesto -->
        <div class="tab-content" id="tab-configurar">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-2">Empresa:</label>
                    <select id="config-empresa" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Seleccionar empresa...</option>
                        <?php foreach ($empresas as $empresa): ?>
                        <option value="<?= htmlspecialchars($empresa) ?>"><?= htmlspecialchars($empresa) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-2">Período:</label>
                    <div class="flex gap-2">
                        <select id="config-mes" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m == date('n') ? 'selected' : '' ?>><?= nombreMes($m) ?></option>
                            <?php endfor; ?>
                        </select>
                        <select id="config-anio" class="w-24 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <?php for ($a = date('Y'); $a >= date('Y')-2; $a--): ?>
                            <option value="<?= $a ?>" <?= $a == date('Y') ? 'selected' : '' ?>><?= $a ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-2">Presupuesto ($):</label>
                    <input type="number" id="config-presupuesto" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Ej: 10000000" min="0" step="1000">
                </div>
                
                <button id="btn-guardar-presupuesto" class="w-full py-2.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 font-medium">
                    💾 Guardar presupuesto
                </button>
                
                <div id="mensaje-config" class="text-sm"></div>
                
                <!-- Lista de presupuestos existentes -->
                <div class="mt-6">
                    <h4 class="font-medium text-sm mb-3">Presupuestos existentes</h4>
                    <div id="lista-presupuestos-existente" class="space-y-2 max-h-60 overflow-y-auto"></div>
                </div>
            </div>
        </div>
        
        <!-- Pestaña: Reporte -->
        <div class="tab-content" id="tab-reporte">
            <div class="space-y-4">
                <div class="bg-slate-50 p-3 rounded-lg">
                    <select id="reporte-periodo" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <?php
                        foreach ($periodos as $p) {
                            $selected = ($p['mes'] == $mes_actual && $p['anio'] == $anio_actual) ? 'selected' : '';
                            echo "<option value='{$p['mes']}-{$p['anio']}' $selected>" . nombreMes($p['mes']) . " {$p['anio']}</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <button id="btn-generar-reporte" class="w-full py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">
                    📊 Generar reporte completo
                </button>
                
                <div id="resultado-reporte" class="mt-4"></div>
            </div>
        </div>
    </div>
</div>

<!-- Bolita para el panel de presupuestos (nueva) -->
<div class="floating-ball ball-presupuestos" id="ball-presupuestos" data-panel="presupuestos" style="background: linear-gradient(135deg, #f97316, #dc2626);">
    <div class="ball-content">🚨</div>
    <div class="ball-tooltip">Alertas por presupuesto</div>
</div>

<style>
/* Estilos adicionales para el panel de presupuestos */
.ball-presupuestos {
    background: linear-gradient(135deg, #f97316, #dc2626);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.tab-btn {
    transition: all 0.2s;
}

/* Estilos para las tarjetas de presupuesto */
.presupuesto-card {
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    padding: 1rem;
    transition: all 0.2s;
}

.presupuesto-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.presupuesto-card.excedido {
    border-left: 4px solid #dc2626;
    background-color: #fef2f2;
}

.presupuesto-card.normal {
    border-left: 4px solid #10b981;
}

.porcentaje-bar {
    height: 6px;
    background-color: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
}

.porcentaje-fill {
    height: 100%;
    transition: width 0.3s;
}

.empresa-checkbox-item.selected {
    background-color: #eff6ff;
    border-color: #3b82f6;
}
</style>

<script>
// ===== SISTEMA DE PRESUPUESTOS =====
document.addEventListener('DOMContentLoaded', function() {
    // Variables
    let empresasSeleccionadas = [];
    
    // Cambio de pestañas
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('active', 'text-blue-600', 'border-b-2', 'border-blue-600');
                b.classList.add('text-slate-600');
            });
            
            this.classList.add('active', 'text-blue-600', 'border-b-2', 'border-blue-600');
            this.classList.remove('text-slate-600');
            
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            document.getElementById(`tab-${tabId}`).classList.add('active');
            
            // Cargar datos según pestaña
            if (tabId === 'ver') {
                cargarPresupuestosPorPeriodo();
            } else if (tabId === 'configurar') {
                cargarListaPresupuestosExistentes();
            }
        });
    });
    
    // Checkbox de empresas
    document.querySelectorAll('.empresa-checkbox').forEach(cb => {
        cb.addEventListener('change', function() {
            const label = this.closest('.empresa-checkbox-item');
            if (this.checked) {
                label.classList.add('selected');
                if (!empresasSeleccionadas.includes(this.value)) {
                    empresasSeleccionadas.push(this.value);
                }
            } else {
                label.classList.remove('selected');
                empresasSeleccionadas = empresasSeleccionadas.filter(e => e !== this.value);
            }
        });
    });
    
    // Botón seleccionar todas
    document.getElementById('seleccionar-todas')?.addEventListener('click', function() {
        document.querySelectorAll('.empresa-checkbox').forEach(cb => {
            cb.checked = true;
            cb.closest('.empresa-checkbox-item').classList.add('selected');
            if (!empresasSeleccionadas.includes(cb.value)) {
                empresasSeleccionadas.push(cb.value);
            }
        });
    });
    
    // Botón seleccionar P.
    document.getElementById('seleccionar-todas-p')?.addEventListener('click', function() {
        document.querySelectorAll('.empresa-checkbox').forEach(cb => {
            if (cb.value.toLowerCase().startsWith('p.')) {
                cb.checked = true;
                cb.closest('.empresa-checkbox-item').classList.add('selected');
                if (!empresasSeleccionadas.includes(cb.value)) {
                    empresasSeleccionadas.push(cb.value);
                }
            }
        });
    });
    
    // Botón limpiar
    document.getElementById('limpiar-seleccion')?.addEventListener('click', function() {
        document.querySelectorAll('.empresa-checkbox').forEach(cb => {
            cb.checked = false;
            cb.closest('.empresa-checkbox-item').classList.remove('selected');
        });
        empresasSeleccionadas = [];
    });
    
    // Ver presupuestos
    document.getElementById('btn-ver-presupuestos')?.addEventListener('click', function() {
        const periodo = document.getElementById('filtro-periodo').value.split('-');
        const mes = periodo[0];
        const anio = periodo[1];
        
        if (empresasSeleccionadas.length === 0) {
            alert('Selecciona al menos una empresa');
            return;
        }
        
        cargarPresupuestos(mes, anio, empresasSeleccionadas);
    });
    
    // Cambio de período
    document.getElementById('filtro-periodo')?.addEventListener('change', function() {
        if (empresasSeleccionadas.length > 0) {
            const periodo = this.value.split('-');
            cargarPresupuestos(periodo[0], periodo[1], empresasSeleccionadas);
        }
    });
    
    // Guardar presupuesto
    document.getElementById('btn-guardar-presupuesto')?.addEventListener('click', function() {
        const empresa = document.getElementById('config-empresa').value;
        const mes = document.getElementById('config-mes').value;
        const anio = document.getElementById('config-anio').value;
        const presupuesto = document.getElementById('config-presupuesto').value;
        
        if (!empresa) {
            mostrarMensaje('Selecciona una empresa', 'error');
            return;
        }
        
        if (!presupuesto || presupuesto <= 0) {
            mostrarMensaje('Ingresa un presupuesto válido', 'error');
            return;
        }
        
        guardarPresupuesto(empresa, presupuesto, mes, anio);
    });
    
    // Generar reporte
    document.getElementById('btn-generar-reporte')?.addEventListener('click', function() {
        const periodo = document.getElementById('reporte-periodo').value.split('-');
        generarReporte(periodo[0], periodo[1]);
    });
    
    // Cargar presupuestos iniciales
    setTimeout(() => {
        if (document.getElementById('tab-ver').classList.contains('active')) {
            cargarPresupuestosPorPeriodo();
        }
    }, 500);
});

// ===== FUNCIONES AJAX =====
function cargarPresupuestos(mes, anio, empresas) {
    const resultado = document.getElementById('resultado-presupuestos');
    resultado.innerHTML = '<div class="text-center py-4"><div class="animate-spin inline-block w-6 h-6 border-2 border-blue-600 border-t-transparent rounded-full"></div><p class="text-sm text-slate-500 mt-2">Cargando presupuestos...</p></div>';
    
    const params = new URLSearchParams({
        ajax: 'get_presupuestos',
        mes: mes,
        anio: anio,
        empresas: JSON.stringify(empresas)
    });
    
    fetch('<?= basename(__FILE__) ?>?' + params.toString())
        .then(r => r.json())
        .then(data => {
            if (data.length === 0) {
                resultado.innerHTML = '<div class="text-center py-6 text-slate-500">📭 No hay presupuestos para las empresas seleccionadas</div>';
                return;
            }
            
            let html = '';
            data.forEach(p => {
                const excedido = p.gastos > p.presupuesto;
                const porcentaje = (p.gastos / p.presupuesto * 100).toFixed(1);
                
                html += `
                <div class="presupuesto-card ${excedido ? 'excedido' : 'normal'}">
                    <div class="flex items-start justify-between mb-2">
                        <div>
                            <div class="font-semibold text-base">${p.empresa}</div>
                            <div class="text-xs text-slate-500">${p.mes_nombre} ${p.anio}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs text-slate-500">Presupuesto</div>
                            <div class="font-bold">$${formatNumber(p.presupuesto)}</div>
                        </div>
                    </div>
                    
                    <div class="space-y-2 mt-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-600">Gastado:</span>
                            <span class="font-medium">$${formatNumber(p.gastos)}</span>
                        </div>
                        
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-600">Porcentaje:</span>
                            <span class="font-medium ${porcentaje > 100 ? 'text-red-600 font-bold' : ''}">${porcentaje}%</span>
                        </div>
                        
                        <div class="porcentaje-bar">
                            <div class="porcentaje-fill ${porcentaje > 100 ? 'bg-red-500' : 'bg-green-500'}" style="width: ${Math.min(porcentaje, 100)}%"></div>
                        </div>
                        
                        ${excedido ? `
                        <div class="flex justify-between text-sm mt-2 pt-2 border-t border-red-100">
                            <span class="text-red-600 font-medium">Exceso:</span>
                            <span class="text-red-600 font-bold">$${formatNumber(p.exceso)}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
                `;
            });
            
            resultado.innerHTML = html;
        });
}

function cargarPresupuestosPorPeriodo() {
    const periodo = document.getElementById('filtro-periodo').value.split('-');
    const mes = periodo[0];
    const anio = periodo[1];
    
    // Obtener empresas seleccionadas
    const empresas = [];
    document.querySelectorAll('.empresa-checkbox:checked').forEach(cb => {
        empresas.push(cb.value);
    });
    
    if (empresas.length > 0) {
        cargarPresupuestos(mes, anio, empresas);
    }
}

function guardarPresupuesto(empresa, presupuesto, mes, anio) {
    const btn = document.getElementById('btn-guardar-presupuesto');
    btn.disabled = true;
    btn.innerHTML = '<span class="animate-spin inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full mr-2"></span>Guardando...';
    
    fetch('<?= basename(__FILE__) ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            guardar_presupuesto: 1,
            empresa: empresa,
            presupuesto: presupuesto,
            mes: mes,
            anio: anio
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            mostrarMensaje('✅ Presupuesto guardado correctamente', 'success');
            document.getElementById('config-presupuesto').value = '';
            
            // Actualizar selector de período
            actualizarSelectorPeriodos();
            
            // Recargar lista
            cargarListaPresupuestosExistentes();
        } else {
            mostrarMensaje('❌ Error: ' + data.message, 'error');
        }
    })
    .catch(() => {
        mostrarMensaje('❌ Error de conexión', 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '💾 Guardar presupuesto';
    });
}

function cargarListaPresupuestosExistentes() {
    const lista = document.getElementById('lista-presupuestos-existente');
    if (!lista) return;
    
    lista.innerHTML = '<div class="text-center py-2 text-sm text-slate-500">Cargando...</div>';
    
    const params = new URLSearchParams({
        ajax: 'lista_presupuestos'
    });
    
    fetch('<?= basename(__FILE__) ?>?' + params.toString())
        .then(r => r.json())
        .then(data => {
            if (data.length === 0) {
                lista.innerHTML = '<div class="text-center py-4 text-sm text-slate-500">📭 No hay presupuestos configurados</div>';
                return;
            }
            
            let html = '';
            data.forEach(p => {
                html += `
                <div class="flex items-center justify-between p-2 border border-slate-100 rounded-lg hover:bg-slate-50">
                    <div>
                        <div class="font-medium text-sm">${p.empresa}</div>
                        <div class="text-xs text-slate-500">${p.mes_nombre} ${p.anio}</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold">$${formatNumber(p.presupuesto)}</span>
                        <button onclick="eliminarPresupuesto(${p.id})" class="text-xs text-red-500 hover:text-red-700 px-2 py-1">
                            ✕
                        </button>
                    </div>
                </div>
                `;
            });
            
            lista.innerHTML = html;
        });
}

function eliminarPresupuesto(id) {
    if (!confirm('¿Eliminar este presupuesto?')) return;
    
    fetch('<?= basename(__FILE__) ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            eliminar_presupuesto: 1,
            id: id
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            cargarListaPresupuestosExistentes();
            actualizarSelectorPeriodos();
        }
    });
}

function generarReporte(mes, anio) {
    const resultado = document.getElementById('resultado-reporte');
    resultado.innerHTML = '<div class="text-center py-4"><div class="animate-spin inline-block w-6 h-6 border-2 border-indigo-600 border-t-transparent rounded-full"></div></div>';
    
    const params = new URLSearchParams({
        ajax: 'reporte_presupuestos',
        mes: mes,
        anio: anio
    });
    
    fetch('<?= basename(__FILE__) ?>?' + params.toString())
        .then(r => r.json())
        .then(data => {
            if (data.length === 0) {
                resultado.innerHTML = '<div class="text-center py-6 text-slate-500">📭 No hay presupuestos para este período</div>';
                return;
            }
            
            let totalPresupuesto = 0;
            let totalGastado = 0;
            let html = '<div class="space-y-3">';
            
            data.forEach(p => {
                totalPresupuesto += p.presupuesto;
                totalGastado += p.gastos;
                
                const porcentaje = (p.gastos / p.presupuesto * 100).toFixed(1);
                const emoji = porcentaje <= 80 ? '🟢' : (porcentaje <= 100 ? '🟡' : '🔴');
                
                html += `
                <div class="flex items-center justify-between p-3 border border-slate-100 rounded-lg">
                    <div class="flex-1">
                        <div class="font-medium">${emoji} ${p.empresa}</div>
                        <div class="text-xs text-slate-500">$${formatNumber(p.gastos)} de $${formatNumber(p.presupuesto)}</div>
                    </div>
                    <div class="text-right">
                        <span class="text-sm font-bold ${porcentaje > 100 ? 'text-red-600' : ''}">${porcentaje}%</span>
                    </div>
                </div>
                `;
            });
            
            const porcentajeTotal = (totalGastado / totalPresupuesto * 100).toFixed(1);
            
            html += `
                <div class="mt-4 pt-4 border-t border-slate-200">
                    <div class="flex justify-between font-semibold">
                        <span>TOTAL:</span>
                        <span>$${formatNumber(totalGastado)} de $${formatNumber(totalPresupuesto)}</span>
                    </div>
                    <div class="flex justify-between text-sm mt-1">
                        <span>Porcentaje global:</span>
                        <span class="font-bold ${porcentajeTotal > 100 ? 'text-red-600' : ''}">${porcentajeTotal}%</span>
                    </div>
                </div>
            </div>`;
            
            resultado.innerHTML = html;
        });
}

function actualizarSelectorPeriodos() {
    fetch('<?= basename(__FILE__) ?>?ajax=periodos_presupuestos')
        .then(r => r.json())
        .then(data => {
            const selector = document.getElementById('filtro-periodo');
            if (!selector) return;
            
            const valorActual = selector.value;
            let options = '';
            
            data.forEach(p => {
                const value = p.mes + '-' + p.anio;
                const selected = (value === valorActual) ? 'selected' : '';
                options += `<option value="${value}" ${selected}>${nombreMes(p.mes)} ${p.anio}</option>`;
            });
            
            selector.innerHTML = options;
            
            // Actualizar también el selector de reporte
            const reporteSelector = document.getElementById('reporte-periodo');
            if (reporteSelector) {
                reporteSelector.innerHTML = options;
            }
        });
}

function mostrarMensaje(texto, tipo) {
    const div = document.getElementById('mensaje-config');
    if (!div) return;
    
    div.innerHTML = `<div class="p-2 rounded-lg ${tipo === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'} text-sm">${texto}</div>`;
    setTimeout(() => { div.innerHTML = ''; }, 3000);
}

function formatNumber(num) {
    return new Intl.NumberFormat('es-CO').format(num || 0);
}

function nombreMes(num) {
    const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    return meses[num-1] || '';
}
</script>
<?php
// ===== MANEJO DE PETICIONES AJAX =====
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'get_presupuestos') {
        $mes = intval($_GET['mes']);
        $anio = intval($_GET['anio']);
        $empresas = json_decode($_GET['empresas'], true);
        
        $presupuestos = obtenerPresupuestosEmpresa($conn, $mes, $anio, $empresas);
        $resultado = [];
        
        foreach ($presupuestos as $p) {
            $gastos = calcularGastosEmpresa($conn, $p['empresa'], $mes, $anio);
            $resultado[] = [
                'id' => $p['id'],
                'empresa' => $p['empresa'],
                'presupuesto' => floatval($p['presupuesto']),
                'gastos' => $gastos,
                'exceso' => max($gastos - $p['presupuesto'], 0),
                'mes' => $p['mes'],
                'anio' => $p['anio'],
                'mes_nombre' => nombreMes($p['mes'])
            ];
        }
        
        echo json_encode($resultado);
        exit;
    }
    
    if ($_GET['ajax'] === 'lista_presupuestos') {
        $sql = "SELECT * FROM presupuestos_empresa WHERE activo = 1 ORDER BY anio DESC, mes DESC, empresa ASC";
        $result = $conn->query($sql);
        $presupuestos = [];
        while ($row = $result->fetch_assoc()) {
            $row['mes_nombre'] = nombreMes($row['mes']);
            $presupuestos[] = $row;
        }
        echo json_encode($presupuestos);
        exit;
    }
    
    if ($_GET['ajax'] === 'periodos_presupuestos') {
        $periodos = obtenerMesesConPresupuestos($conn);
        echo json_encode($periodos);
        exit;
    }
    
    if ($_GET['ajax'] === 'reporte_presupuestos') {
        $mes = intval($_GET['mes']);
        $anio = intval($_GET['anio']);
        
        $presupuestos = obtenerPresupuestosEmpresa($conn, $mes, $anio);
        $resultado = [];
        
        foreach ($presupuestos as $p) {
            $gastos = calcularGastosEmpresa($conn, $p['empresa'], $mes, $anio);
            $resultado[] = [
                'empresa' => $p['empresa'],
                'presupuesto' => floatval($p['presupuesto']),
                'gastos' => $gastos
            ];
        }
        
        echo json_encode($resultado);
        exit;
    }
}
?>
</body>
</html>
<?php $conn->close(); ?>