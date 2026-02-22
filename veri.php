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
   🔧 ACTUALIZADO: Selección múltiple de empresas con checkboxes
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
      <style>
        .empresa-checkbox:checked + span {
            background-color: #3b82f6;
            color: white;
            border-color: #2563eb;
        }
        .grid-empresas {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px;
        }
      </style>
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-800">
      <div class="max-w-lg mx-auto p-6">
        <div class="bg-white shadow-sm rounded-2xl p-6 border border-slate-200">
          <h2 class="text-2xl font-bold text-center mb-2">📅 Filtrar viajes por rango</h2>
          <p class="text-center text-slate-500 mb-6">Selecciona el periodo y las empresas que deseas incluir.</p>
          
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
            
            <div class="block">
              <span class="block text-sm font-medium mb-2">Empresas (selecciona las que necesites)</span>
              
              <!-- Botones de selección rápida -->
              <div class="flex flex-wrap gap-2 mb-3">
                <button type="button" onclick="seleccionarTodas()" 
                        class="text-xs px-3 py-1.5 rounded-full bg-blue-100 text-blue-700 hover:bg-blue-200 transition">
                  ✅ Seleccionar todas
                </button>
                <button type="button" onclick="deseleccionarTodas()" 
                        class="text-xs px-3 py-1.5 rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200 transition">
                  ✕ Limpiar
                </button>
              </div>
              
              <!-- Grid de checkboxes -->
              <div class="grid-empresas grid grid-cols-2 gap-2">
                <?php foreach($empresas as $e): ?>
                <label class="flex items-center gap-2 p-2 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50 transition">
                  <input type="checkbox" name="empresas[]" value="<?= htmlspecialchars($e) ?>" 
                         class="empresa-checkbox w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                  <span class="text-sm truncate"><?= htmlspecialchars($e) ?></span>
                </label>
                <?php endforeach; ?>
              </div>
              
              <p class="text-xs text-slate-500 mt-2">
                ℹ️ Puedes seleccionar una o varias empresas. Los conductores aparecerán con el total combinado.
              </p>
            </div>
            
            <button type="submit" 
                    class="w-full rounded-xl bg-blue-600 text-white py-2.5 font-semibold shadow hover:bg-blue-700 active:bg-blue-800 focus:ring-4 focus:ring-blue-200 transition">
              📊 Ver liquidación
            </button>
          </form>
        </div>
      </div>
      
      <script>
        function seleccionarTodas() {
          document.querySelectorAll('.empresa-checkbox').forEach(cb => cb.checked = true);
        }
        
        function deseleccionarTodas() {
          document.querySelectorAll('.empresa-checkbox').forEach(cb => cb.checked = false);
        }
      </script>
    </body>
    </html>
    <?php
    exit;
}
/* ===== FIN MÓDULO 3 ===== */

/* =======================================================
   🚀 MÓDULO 4: OBTENCIÓN DE DATOS PRINCIPALES (MULTI-EMPRESA)
   ========================================================
   🔧 PROPÓSITO: Procesar los datos con múltiples empresas
   🔧 CARACTERÍSTICA: Suma automática de viajes de todas las empresas seleccionadas
   ======================================================== */
$desde = $_GET['desde'];
$hasta = $_GET['hasta'];
$empresasFiltro = $_GET['empresas'] ?? [];

// Si viene vacío o no es array, lo convertimos a array vacío
if (!is_array($empresasFiltro)) {
    $empresasFiltro = $empresasFiltro ? [$empresasFiltro] : [];
}
$empresasFiltro = array_filter($empresasFiltro); // Quitar vacíos

// Construir condición SQL para múltiples empresas
$sqlCondicionEmpresa = "";
if (!empty($empresasFiltro)) {
    $empresasEscapadas = array_map(function($e) use ($conn) {
        return "'" . $conn->real_escape_string($e) . "'";
    }, $empresasFiltro);
    $sqlCondicionEmpresa = " AND empresa IN (" . implode(",", $empresasEscapadas) . ")";
}

$columnas_tarifas = obtenerColumnasTarifas($conn);
$clasificaciones_disponibles = obtenerClasificacionesDisponibles($conn);

// Session key basado en TODAS las empresas seleccionadas
$session_key = "columnas_seleccionadas_" . md5(implode('|', $empresasFiltro) . $desde . $hasta);
$columnas_seleccionadas = [];
if (isset($_COOKIE[$session_key])) {
    $columnas_seleccionadas = json_decode($_COOKIE[$session_key], true);
} else {
    $columnas_seleccionadas = $clasificaciones_disponibles;
}

// Cargar clasificaciones de rutas (global, no depende de empresas)
$clasif_rutas = [];
$resClasif = $conn->query("SELECT ruta, tipo_vehiculo, clasificacion FROM ruta_clasificacion");
if ($resClasif) {
    while ($r = $resClasif->fetch_assoc()) {
        $key = mb_strtolower(trim($r['ruta'] . '|' . $r['tipo_vehiculo']), 'UTF-8');
        $clasif_rutas[$key] = strtolower($r['clasificacion']);
    }
}

// 📊 CONSULTA PRINCIPAL - Trae viajes de TODAS las empresas seleccionadas
$sql = "SELECT nombre, ruta, empresa, tipo_vehiculo, COALESCE(pago_parcial,0) AS pago_parcial
        FROM viajes
        WHERE fecha BETWEEN '$desde' AND '$hasta'";
$sql .= $sqlCondicionEmpresa; // Agregar condición multi-empresa

$res = $conn->query($sql);

// Inicializar arrays
$datos = [];
$vehiculos = [];
$rutasUnicas = [];
$pagosConductor = [];
$rutas_sin_clasificar_por_conductor = [];

// Procesar resultados - SE SUMAN AUTOMÁTICAMENTE LOS VIAJES DE TODAS LAS EMPRESAS
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $nombre   = $row['nombre'];
        $ruta     = $row['ruta'];
        $vehiculo = $row['tipo_vehiculo'];
        $pagoParcial = (int)($row['pago_parcial'] ?? 0);
        $empresa  = $row['empresa'];

        // Sumar pagos parciales del conductor (acumula de todas las empresas)
        if (!isset($pagosConductor[$nombre])) $pagosConductor[$nombre] = 0;
        $pagosConductor[$nombre] += $pagoParcial;

        $keyRuta = mb_strtolower(trim($ruta . '|' . $vehiculo), 'UTF-8');

        // Registrar ruta única para el panel de clasificación
        if (!isset($rutasUnicas[$keyRuta])) {
            $rutasUnicas[$keyRuta] = [
                'ruta'          => $ruta,
                'vehiculo'      => $vehiculo,
                'clasificacion' => $clasif_rutas[$keyRuta] ?? ''
            ];
        }

        // Registrar tipo de vehículo único
        if (!in_array($vehiculo, $vehiculos, true)) $vehiculos[] = $vehiculo;

        // Registrar rutas sin clasificar
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

        // Inicializar datos del conductor si no existe
        if (!isset($datos[$nombre])) {
            $datos[$nombre] = ["vehiculo" => $vehiculo, "pagado" => 0];
            foreach ($clasificaciones_disponibles as $clasif) $datos[$nombre][$clasif] = 0;
        }

        // SUMAR el viaje a la clasificación correspondiente
        $clasifRuta = $clasif_rutas[$keyRuta] ?? '';
        if ($clasifRuta !== '') {
            if (!isset($datos[$nombre][$clasifRuta])) $datos[$nombre][$clasifRuta] = 0;
            $datos[$nombre][$clasifRuta]++; // ¡ESTA LÍNEA SUMA LOS VIAJES!
        }
    }
}

// Asignar pagos totales a cada conductor (ya están sumados de todas las empresas)
foreach ($datos as $conductor => $info) {
    $datos[$conductor]["pagado"] = (int)($pagosConductor[$conductor] ?? 0);
    $datos[$conductor]["rutas_sin_clasificar"] = count($rutas_sin_clasificar_por_conductor[$conductor] ?? []);
}

// Obtener empresas para el filtro (lista completa)
$empresas = [];
$resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
if ($resEmp) while ($r = $resEmp->fetch_assoc()) $empresas[] = $r['empresa'];

// Obtener tarifas guardadas (para la PRIMERA empresa seleccionada - las bolitas funcionan igual)
$tarifas_guardadas = [];
if (!empty($empresasFiltro)) {
    $primeraEmpresa = $empresasFiltro[0];
    $resTarifas = $conn->query("SELECT * FROM tarifas WHERE empresa='$primeraEmpresa'");
    if ($resTarifas) {
        while ($r = $resTarifas->fetch_assoc()) {
            $tarifas_guardadas[$r['tipo_vehiculo']] = $r;
        }
    }
}
/* ===== FIN MÓDULO 4 ===== */

/* =======================================================
   🚀 MÓDULO 5: ENDPOINT VIAJES POR CONDUCTOR (AJAX) - MULTI-EMPRESA
   ========================================================
   🔧 PROPÓSITO: Cargar viajes individuales en el modal
   🔧 ACTUALIZADO: Muestra viajes de TODAS las empresas seleccionadas
   ======================================================== */
if (isset($_GET['viajes_conductor'])) {
    $nombre  = $conn->real_escape_string($_GET['viajes_conductor']);
    $desde   = $_GET['desde'];
    $hasta   = $_GET['hasta'];
    $empresasParam = $_GET['empresas'] ?? [];
    
    // Procesar empresas (puede venir como array o como string JSON)
    if (is_string($empresasParam)) {
        $empresasParam = json_decode($empresasParam, true) ?? [];
    }
    if (!is_array($empresasParam)) {
        $empresasParam = $empresasParam ? [$empresasParam] : [];
    }
    $empresasParam = array_filter($empresasParam);

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

    // Cargar clasificaciones de rutas
    $clasif_rutas = [];
    $resClasif = $conn->query("SELECT ruta, tipo_vehiculo, clasificacion FROM ruta_clasificacion");
    if ($resClasif) {
        while ($r = $resClasif->fetch_assoc()) {
            $key = mb_strtolower(trim($r['ruta'] . '|' . $r['tipo_vehiculo']), 'UTF-8');
            $clasif_rutas[$key] = $r['clasificacion'];
        }
    }

    // 📌 CONSTRUIR CONSULTA - TRAER VIAJES DE TODAS LAS EMPRESAS SELECCIONADAS
    $sql = "SELECT fecha, ruta, empresa, tipo_vehiculo, COALESCE(pago_parcial,0) AS pago_parcial
            FROM viajes
            WHERE nombre = '$nombre'
              AND fecha BETWEEN '$desde' AND '$hasta'";
    
    // Agregar filtro de múltiples empresas si hay
    if (!empty($empresasParam)) {
        $empresasEscapadas = array_map(function($e) use ($conn) {
            return "'" . $conn->real_escape_string($e) . "'";
        }, $empresasParam);
        $sql .= " AND empresa IN (" . implode(",", $empresasEscapadas) . ")";
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
            $empresa = $r['empresa'];
            
            $key = mb_strtolower(trim($ruta . '|' . $vehiculo), 'UTF-8');
            $cat = $clasif_rutas[$key] ?? 'otro';
            $cat = strtolower($cat);
            
            if ($cat === 'otro' || $cat === '') {
                $total_sin_clasificar++;
                $rutas_sin_clasificar[] = [
                    'ruta' => $ruta,
                    'vehiculo' => $vehiculo,
                    'fecha' => $r['fecha'],
                    'empresa' => $empresa
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

            // Agregar columna de empresa en el modal para mejor visibilidad
            $badgeEmpresa = "<span class='inline-block px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 text-[10px] font-medium'>".htmlspecialchars($empresa)."</span>";

            $rowsHTML .= "<tr class='{$rowCls}'>
                    <td class='px-3 py-2 text-center'>".htmlspecialchars($r['fecha'])."</td>
                    <td class='px-3 py-2'>
                      <div class='flex flex-col items-start gap-1'>
                        <div class='flex items-center gap-2'>
                          {$badge}
                          <span>".htmlspecialchars($ruta)."</span>
                        </div>
                        <div class='text-[10px] text-slate-500'>{$badgeEmpresa}</div>
                      </div>
                    </td>
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
                        <span class='text-[10px] bg-amber-200 px-1.5 py-0.5 rounded-full'>".htmlspecialchars($rsc['empresa'])."</span>
                      </div>";
            }
            
            if ($total_sin_clasificar > 5) {
                echo "<p class='text-xs text-amber-600 mt-1'>... y ".($total_sin_clasificar - 5)." más</p>";
            }
            
            echo "</div></div>";
        }
        
        // Leyenda de filtros
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

        // Tabla de viajes
        echo "<div class='overflow-x-auto max-h-[350px]'>
                <table class='min-w-full text-sm text-left'>
                  <thead class='bg-blue-600 text-white sticky top-0 z-10'>
                    <tr>
                      <th class='px-3 py-2 text-center'>Fecha</th>
                      <th class='px-3 py-2 text-center'>Ruta / Empresa</th>
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
        
        // Script de filtrado (igual que antes)
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
   🔧 ACTUALIZADO: Muestra múltiples empresas seleccionadas
   ======================================================== -->
<header class="max-w-[1800px] mx-auto px-3 md:px-4 pt-6">
  <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-5 py-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-3">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between w-full gap-3">
        <div class="flex items-center gap-3 flex-wrap">
          <h2 class="text-xl md:text-2xl font-bold">🪙 Liquidación de Conductores</h2>
          
          <!-- Mostrar todas las empresas seleccionadas como badges -->
          <?php if (!empty($empresasFiltro)): ?>
            <div class="flex flex-wrap gap-1">
              <?php foreach($empresasFiltro as $emp): ?>
                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-sm font-medium">
                  🏢 <?= htmlspecialchars($emp) ?>
                </span>
              <?php endforeach; ?>
            </div>
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
            
            <!-- Selector de empresas en el header (para recargar) -->
            <details class="relative">
              <summary class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm cursor-pointer bg-white hover:bg-slate-50">
                <?= count($empresasFiltro) ?> empresa(s) seleccionada(s) ▼
              </summary>
              <div class="absolute right-0 mt-1 w-64 bg-white border border-slate-200 rounded-lg shadow-lg p-3 z-50 max-h-80 overflow-y-auto">
                <div class="flex justify-between mb-2">
                  <button type="button" onclick="seleccionarTodasHeader()" class="text-xs text-blue-600 hover:underline">Todas</button>
                  <button type="button" onclick="deseleccionarTodasHeader()" class="text-xs text-gray-600 hover:underline">Ninguna</button>
                </div>
                <?php foreach($empresas as $emp): ?>
                  <label class="flex items-center gap-2 py-1">
                    <input type="checkbox" name="empresas[]" value="<?= htmlspecialchars($emp) ?>" 
                           <?= in_array($emp, $empresasFiltro) ? 'checked' : '' ?>
                           class="empresa-checkbox-header w-4 h-4 text-blue-600 rounded">
                    <span class="text-sm truncate"><?= htmlspecialchars($emp) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </details>
            
            <button type="submit" 
                    class="rounded-lg bg-blue-600 text-white px-4 py-1.5 text-sm font-semibold hover:bg-blue-700 active:bg-blue-800 focus:ring-2 focus:ring-blue-200 transition whitespace-nowrap">
              🔄 Aplicar
            </button>
          </div>
        </form>
      </div>
    </div>
    
    <div class="text-sm text-slate-600 flex items-center gap-2 flex-wrap">
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

<script>
function seleccionarTodasHeader() {
  document.querySelectorAll('.empresa-checkbox-header').forEach(cb => cb.checked = true);
}

function deseleccionarTodasHeader() {
  document.querySelectorAll('.empresa-checkbox-header').forEach(cb => cb.checked = false);
}
</script>
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
   🚀 MÓDULO 10: MODAL DE VIAJES (ACTUALIZADO MULTI-EMPRESA)
   ========================================================
   🔧 PROPÓSITO: Mostrar viajes detallados por conductor
   🔧 ACTUALIZADO: Pasa las empresas seleccionadas al modal
   ======================================================== -->
<div id="viajesModal" class="viajes-backdrop">
    <style>
    .viajes-backdrop{ 
        position:fixed; inset:0; background:rgba(0,0,0,.45); display:none; 
        align-items:center; justify-content:center; z-index:10000; 
    }
    .viajes-backdrop.show{ display:flex; }
    .viajes-card{ 
        width:min(820px,94vw); max-height:90vh; overflow:hidden; border-radius:16px; 
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
                        <span id="viajesEmpresas"></span>
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

<script>
// Actualizar la función que abre el modal
function abrirModalViajes(nombreInicial){
    // Mostrar el rango
    document.getElementById('viajesRango').textContent = RANGO_DESDE + " → " + RANGO_HASTA;
    
    // Mostrar las empresas seleccionadas
    const empresasText = (RANGO_EMPS && RANGO_EMPS.length > 0) 
        ? RANGO_EMPS.join(' • ') 
        : "Todas las empresas";
    document.getElementById('viajesEmpresas').textContent = empresasText;
    
    // Inicializar select de conductores
    initViajesSelect(nombreInicial);
    
    // Mostrar modal
    document.getElementById('viajesModal').classList.add('show');
    
    // Cargar viajes
    loadViajes(nombreInicial);
}

// Modificar la función loadViajes para enviar las empresas
function loadViajes(nombre) {
    const content = document.getElementById('viajesContent');
    content.innerHTML = '<p class="text-center m-0 animate-pulse">Cargando…</p>';
    viajesConductorActual = nombre;
    document.getElementById('viajesTitle').textContent = nombre;

    // Crear query string con todas las empresas
    const params = new URLSearchParams();
    params.append('viajes_conductor', nombre);
    params.append('desde', RANGO_DESDE);
    params.append('hasta', RANGO_HASTA);
    
    // Agregar todas las empresas seleccionadas
    if (RANGO_EMPS && RANGO_EMPS.length > 0) {
        params.append('empresas', JSON.stringify(RANGO_EMPS));
    }

    fetch('<?= basename(__FILE__) ?>?' + params.toString())
        .then(r => r.text())
        .then(html => {
            content.innerHTML = html;
        });
}
</script>
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
const RANGO_EMPS   = <?= json_encode($empresasFiltro) ?>; // Cambiar de RANGO_EMP a RANGO_EMPS
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
   🚀 MÓDULO 12: SISTEMA DE ALERTAS - VERSIÓN SIN AJAX
   ========================================================
   🔧 FUNCIONA MEDIANTE POST Y MODAL
   ======================================================== */

// Procesar formulario si se envió
$resultados_presupuestos = [];
$empresas_seleccionadas = [];
$periodo_seleccionado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ver_presupuestos'])) {
    $mes = intval($_POST['mes']);
    $anio = intval($_POST['anio']);
    $empresas_seleccionadas = $_POST['empresas'] ?? [];
    $periodo_seleccionado = nombreMes($mes) . " $anio";
    
    if (!empty($empresas_seleccionadas)) {
        foreach ($empresas_seleccionadas as $empresa) {
            $empresa_esc = $conn->real_escape_string($empresa);
            
            // Obtener presupuesto
            $sql_pres = "SELECT * FROM presupuestos_empresa 
                        WHERE empresa = '$empresa_esc' 
                        AND mes = $mes 
                        AND anio = $anio 
                        AND activo = 1";
            $res_pres = $conn->query($sql_pres);
            
            if ($pres = $res_pres->fetch_assoc()) {
                // Calcular gastos
                $gastos = 0;
                
                $sql_viajes = "SELECT ruta, tipo_vehiculo FROM viajes 
                              WHERE empresa = '$empresa_esc' 
                              AND MONTH(fecha) = $mes 
                              AND YEAR(fecha) = $anio";
                $res_viajes = $conn->query($sql_viajes);
                
                while ($viaje = $res_viajes->fetch_assoc()) {
                    // Obtener clasificación
                    $sql_clasif = "SELECT clasificacion FROM ruta_clasificacion 
                                  WHERE ruta = '" . $conn->real_escape_string($viaje['ruta']) . "' 
                                  AND tipo_vehiculo = '" . $conn->real_escape_string($viaje['tipo_vehiculo']) . "'";
                    $res_clasif = $conn->query($sql_clasif);
                    
                    if ($row_clasif = $res_clasif->fetch_assoc()) {
                        $clasif = $row_clasif['clasificacion'];
                        
                        $sql_tarifa = "SELECT $clasif as tarifa FROM tarifas 
                                      WHERE empresa = '$empresa_esc' 
                                      AND tipo_vehiculo = '" . $conn->real_escape_string($viaje['tipo_vehiculo']) . "'";
                        $res_tarifa = $conn->query($sql_tarifa);
                        
                        if ($row_tarifa = $res_tarifa->fetch_assoc()) {
                            $gastos += floatval($row_tarifa['tarifa'] ?? 0);
                        }
                    }
                }
                
                $resultados_presupuestos[] = [
                    'empresa' => $pres['empresa'],
                    'presupuesto' => floatval($pres['presupuesto']),
                    'gastos' => $gastos,
                    'exceso' => max($gastos - $pres['presupuesto'], 0),
                    'porcentaje' => $pres['presupuesto'] > 0 ? round(($gastos / $pres['presupuesto'] * 100), 1) : 0
                ];
            }
        }
    }
}

function nombreMes($mes) {
    $meses = [1=>"Enero",2=>"Febrero",3=>"Marzo",4=>"Abril",5=>"Mayo",6=>"Junio",7=>"Julio",8=>"Agosto",9=>"Septiembre",10=>"Octubre",11=>"Noviembre",12=>"Diciembre"];
    return $meses[$mes] ?? "Desconocido";
}

// Obtener empresas
$empresas_lista = [];
$res = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa != '' ORDER BY empresa ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $empresas_lista[] = $row['empresa'];
    }
}
?>

<!-- ===== BOTÓN Y PANEL ===== -->
<style>
/* Botón flotante */
.presupuestos-btn {
    position: fixed;
    right: 20px;
    bottom: 20px;
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: linear-gradient(135deg, #f97316, #dc2626);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 8px 20px rgba(0,0,0,0.3);
    border: 3px solid white;
    z-index: 999999;
    font-size: 28px;
    color: white;
    transition: all 0.3s;
}

.presupuestos-btn:hover {
    transform: scale(1.1);
}

/* Panel */
.presupuestos-panel {
    position: fixed;
    right: -500px;
    top: 0;
    width: 480px;
    height: 100vh;
    background: white;
    box-shadow: -4px 0 25px rgba(0,0,0,0.15);
    z-index: 999998;
    transition: right 0.4s;
    overflow-y: auto;
}

.presupuestos-panel.active {
    right: 0;
}

.presupuestos-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.4);
    z-index: 999997;
    display: none;
}

.presupuestos-overlay.active {
    display: block;
}

.presupuestos-header {
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: white;
    position: sticky;
    top: 0;
}

.presupuestos-body {
    padding: 20px;
}

/* Checkboxes */
.empresa-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.empresa-item:hover {
    background: #f8fafc;
}

.empresa-item.selected {
    background: #eff6ff;
    border-color: #3b82f6;
}

.empresa-checkbox {
    width: 16px;
    height: 16px;
    cursor: pointer;
}

/* Botones */
.btn {
    padding: 8px 16px;
    border-radius: 8px;
    border: none;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
}

.btn-primary {
    background: #2563eb;
    color: white;
}

.btn-primary:hover {
    background: #1d4ed8;
}

.btn-small {
    padding: 4px 8px;
    font-size: 12px;
    border-radius: 4px;
}

/* Modal de resultados */
.resultados-modal {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 600px;
    max-width: 90%;
    max-height: 80vh;
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    z-index: 1000000;
    display: none;
    overflow: hidden;
}

.resultados-modal.active {
    display: block;
}

.resultados-modal-header {
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8fafc;
}

.resultados-modal-body {
    padding: 20px;
    overflow-y: auto;
    max-height: calc(80vh - 80px);
}

.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 999999;
    display: none;
}

.modal-overlay.active {
    display: block;
}

/* Tarjetas de presupuesto */
.presupuesto-card {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 12px;
}

.presupuesto-card.excedido {
    border-left: 4px solid #dc2626;
    background: #fef2f2;
}

.presupuesto-card.normal {
    border-left: 4px solid #10b981;
}

.porcentaje-bar {
    height: 8px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
    margin: 12px 0;
}

.porcentaje-fill {
    height: 100%;
    transition: width 0.3s;
}
</style>

<!-- Botón flotante -->
<div class="presupuestos-btn" id="presupuestosBtn">
    <span>🚨</span>
</div>

<!-- Overlay del panel -->
<div class="presupuestos-overlay" id="presupuestosOverlay"></div>

<!-- Panel lateral -->
<div class="presupuestos-panel" id="presupuestosPanel">
    <div class="presupuestos-header">
        <h3 style="margin:0; font-size:18px; font-weight:600;">🚨 Alertas por Presupuesto</h3>
        <button id="presupuestosCloseBtn" style="background:#f1f5f9; border:none; width:32px; height:32px; border-radius:50%; cursor:pointer; font-size:16px;">✕</button>
    </div>
    
    <div class="presupuestos-body">
        <form method="POST" id="formPresupuestos">
            <!-- Período -->
            <div style="background:#f8fafc; padding:12px; border-radius:8px; margin-bottom:16px;">
                <label style="display:block; font-size:12px; color:#475569; margin-bottom:4px;">Período:</label>
                <select name="mes" style="width:70%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
                    <?php for($m=1; $m<=12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m==date('n') ? 'selected' : '' ?>><?= nombreMes($m) ?></option>
                    <?php endfor; ?>
                </select>
                <select name="anio" style="width:28%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
                    <?php for($a=date('Y'); $a>=date('Y')-2; $a--): ?>
                    <option value="<?= $a ?>" <?= $a==date('Y') ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <!-- Botones de selección rápida -->
            <div style="display:flex; gap:8px; margin-bottom:12px;">
                <button type="button" id="btnSeleccionarP" class="btn btn-small" style="background:#f3e8ff; color:#9333ea; border:1px solid #e9d5ff;">🔘 P.</button>
                <button type="button" id="btnSeleccionarTodas" class="btn btn-small" style="background:#dbeafe; color:#2563eb; border:1px solid #bfdbfe;">✅ Todas</button>
                <button type="button" id="btnLimpiar" class="btn btn-small" style="background:#f1f5f9; color:#475569; border:1px solid #e2e8f0;">✕ Limpiar</button>
            </div>
            
            <!-- Grid de empresas -->
            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:12px; color:#475569; margin-bottom:8px;">Empresas:</label>
                <div id="empresasGrid" style="display:grid; grid-template-columns:repeat(2, 1fr); gap:8px; max-height:300px; overflow-y:auto; padding:4px;">
                    <?php foreach ($empresas_lista as $emp): ?>
                    <label class="empresa-item">
                        <input type="checkbox" name="empresas[]" class="empresa-checkbox" value="<?= htmlspecialchars($emp) ?>">
                        <span style="font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($emp) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Botón Ver presupuestos -->
            <button type="submit" name="ver_presupuestos" class="btn btn-primary" style="width:100%; padding:12px;">
                📊 Ver presupuestos seleccionados
            </button>
        </form>
    </div>
</div>

<!-- Modal de resultados -->
<div class="modal-overlay" id="modalOverlay"></div>
<div class="resultados-modal" id="resultadosModal">
    <div class="resultados-modal-header">
        <h3 style="margin:0; font-size:18px; font-weight:600;">
            📊 Resultados - <?= $periodo_seleccionado ?>
        </h3>
        <button id="cerrarModalBtn" style="background:#f1f5f9; border:none; width:32px; height:32px; border-radius:50%; cursor:pointer; font-size:16px;">✕</button>
    </div>
    <div class="resultados-modal-body" id="resultadosModalBody">
        <?php if (!empty($resultados_presupuestos)): ?>
            <?php foreach ($resultados_presupuestos as $p): 
                $excedido = $p['gastos'] > $p['presupuesto'];
            ?>
                <div class="presupuesto-card <?= $excedido ? 'excedido' : 'normal' ?>">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                        <div>
                            <div style="font-weight:600; font-size:16px;"><?= htmlspecialchars($p['empresa']) ?></div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:11px; color:#64748b;">Presupuesto</div>
                            <div style="font-weight:700;">$<?= number_format($p['presupuesto'], 0, ',', '.') ?></div>
                        </div>
                    </div>
                    
                    <div style="margin-top:12px;">
                        <div style="display:flex; justify-content:space-between; font-size:14px; margin-bottom:4px;">
                            <span>Gastado:</span>
                            <span style="font-weight:500;">$<?= number_format($p['gastos'], 0, ',', '.') ?></span>
                        </div>
                        
                        <div class="porcentaje-bar">
                            <div class="porcentaje-fill" style="width: <?= min($p['porcentaje'], 100) ?>%; background-color: <?= $excedido ? '#dc2626' : '#10b981' ?>;"></div>
                        </div>
                        
                        <div style="display:flex; justify-content:space-between; font-size:14px;">
                            <span>Porcentaje:</span>
                            <span style="font-weight:600; <?= $excedido ? 'color:#dc2626;' : '' ?>"><?= $p['porcentaje'] ?>%</span>
                        </div>
                        
                        <?php if ($excedido): ?>
                            <div style="display:flex; justify-content:space-between; font-size:14px; margin-top:8px; padding-top:8px; border-top:1px solid #fee2e2;">
                                <span style="color:#dc2626;">🚨 Exceso:</span>
                                <span style="color:#dc2626; font-weight:700;">$<?= number_format($p['exceso'], 0, ',', '.') ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div style="text-align:center; padding:30px; color:#64748b;">
                📭 No hay presupuestos configurados para las empresas seleccionadas
            </div>
        <?php else: ?>
            <div style="text-align:center; padding:30px; color:#64748b;">
                👈 Selecciona empresas y período en el panel lateral
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// ===== VARIABLES =====
let panelActivo = false;

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Módulo de presupuestos iniciado');
    
    // Elementos del panel
    const btn = document.getElementById('presupuestosBtn');
    const panel = document.getElementById('presupuestosPanel');
    const overlay = document.getElementById('presupuestosOverlay');
    const closeBtn = document.getElementById('presupuestosCloseBtn');
    
    // Elementos del modal
    const modal = document.getElementById('resultadosModal');
    const modalOverlay = document.getElementById('modalOverlay');
    const cerrarModal = document.getElementById('cerrarModalBtn');
    
    // Abrir panel
    if (btn) {
        btn.addEventListener('click', function() {
            panel.classList.add('active');
            overlay.classList.add('active');
        });
    }
    
    // Cerrar panel
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            panel.classList.remove('active');
            overlay.classList.remove('active');
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', function() {
            panel.classList.remove('active');
            overlay.classList.remove('active');
        });
    }
    
    // Cerrar modal
    if (cerrarModal) {
        cerrarModal.addEventListener('click', function() {
            modal.classList.remove('active');
            modalOverlay.classList.remove('active');
        });
    }
    
    if (modalOverlay) {
        modalOverlay.addEventListener('click', function() {
            modal.classList.remove('active');
            modalOverlay.classList.remove('active');
        });
    }
    
    // Mostrar modal si hay resultados
    <?php if (!empty($resultados_presupuestos)): ?>
    setTimeout(function() {
        modal.classList.add('active');
        modalOverlay.classList.add('active');
        // Cerrar panel si está abierto
        panel.classList.remove('active');
        overlay.classList.remove('active');
    }, 500);
    <?php endif; ?>
    
    // Checkboxes
    document.querySelectorAll('.empresa-checkbox').forEach(cb => {
        cb.addEventListener('change', function() {
            const item = this.closest('.empresa-item');
            if (this.checked) {
                item.classList.add('selected');
            } else {
                item.classList.remove('selected');
            }
        });
    });
    
    // Botón seleccionar todas
    document.getElementById('btnSeleccionarTodas').addEventListener('click', function() {
        document.querySelectorAll('.empresa-checkbox').forEach(cb => {
            cb.checked = true;
            cb.closest('.empresa-item').classList.add('selected');
        });
    });
    
    // Botón seleccionar P.
    document.getElementById('btnSeleccionarP').addEventListener('click', function() {
        document.querySelectorAll('.empresa-checkbox').forEach(cb => {
            if (cb.value.toLowerCase().startsWith('p.')) {
                cb.checked = true;
                cb.closest('.empresa-item').classList.add('selected');
            }
        });
    });
    
    // Botón limpiar
    document.getElementById('btnLimpiar').addEventListener('click', function() {
        document.querySelectorAll('.empresa-checkbox').forEach(cb => {
            cb.checked = false;
            cb.closest('.empresa-item').classList.remove('selected');
        });
    });
    
    // Validar formulario antes de enviar
    document.getElementById('formPresupuestos').addEventListener('submit', function(e) {
        const checkboxes = document.querySelectorAll('.empresa-checkbox:checked');
        if (checkboxes.length === 0) {
            e.preventDefault();
            alert('⚠️ Selecciona al menos una empresa');
        }
    });
});

// Cerrar modal con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('resultadosModal');
        const modalOverlay = document.getElementById('modalOverlay');
        modal.classList.remove('active');
        modalOverlay.classList.remove('active');
    }
});
</script>
</body>
</html>
<?php $conn->close(); ?>