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
            // Excluir columnas que no son tarifas
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
    
    // Verificar si la columna ya existe
    $columnas_existentes = obtenerColumnasTarifas($conn);
    if (in_array($nombre_columna, $columnas_existentes)) {
        return true; // Ya existe
    }
    
    // Crear nueva columna
    $sql = "ALTER TABLE tarifas ADD COLUMN `$nombre_columna` DECIMAL(10,2) DEFAULT 0.00";
    return $conn->query($sql);
}

// Obtener clasificaciones disponibles (solo de tarifas)
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
    
    // Si ya existe, devolverlo
    if (isset($estilos[$clasificacion])) {
        return $estilos[$clasificacion];
    }
    
    // Generar estilo dinámico para nuevas clasificaciones
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
   🔹 NUEVAS FUNCIONES PARA PRESUPUESTO
======================================================= */

// Obtener presupuesto de un conductor para una empresa específica
function obtenerPresupuestoConductor($conn, $conductor, $empresa) {
    $sql = "SELECT presupuesto FROM conductor_presupuesto 
            WHERE conductor = ? AND empresa = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $conductor, $empresa);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return (float)$row['presupuesto'];
    }
    return 0.00; // Retorna 0 si no tiene presupuesto asignado
}

// Guardar o actualizar presupuesto
function guardarPresupuestoConductor($conn, $conductor, $empresa, $presupuesto) {
    $sql = "INSERT INTO conductor_presupuesto (conductor, empresa, presupuesto)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE presupuesto = VALUES(presupuesto)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssd", $conductor, $empresa, $presupuesto);
    return $stmt->execute();
}

// Obtener todos los presupuestos (para reporte)
function obtenerTodosPresupuestos($conn) {
    $presupuestos = [];
    $sql = "SELECT conductor, empresa, presupuesto FROM conductor_presupuesto 
            ORDER BY conductor, empresa";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $presupuestos[$row['conductor']][$row['empresa']] = (float)$row['presupuesto'];
        }
    }
    return $presupuestos;
}

// Registrar excedente en historial
function registrarExcedente($conn, $conductor, $empresa, $total, $presupuesto, $excedente, $porcentaje) {
    // Verificar si ya se registró hoy
    $sql_check = "SELECT id FROM historial_excedentes 
                  WHERE conductor = ? AND empresa = ? AND fecha_excedente = CURDATE()";
    $stmt = $conn->prepare($sql_check);
    $stmt->bind_param("ss", $conductor, $empresa);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        // Solo registrar una vez por día
        $sql = "INSERT INTO historial_excedentes 
                (conductor, empresa, total, presupuesto, excedente, porcentaje, fecha_excedente)
                VALUES (?, ?, ?, ?, ?, ?, CURDATE())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdddd", $conductor, $empresa, $total, $presupuesto, $excedente, $porcentaje);
        return $stmt->execute();
    }
    return true;
}

// Obtener historial de excedentes
function obtenerHistorialExcedentes($conn, $limit = 50) {
    $historial = [];
    $sql = "SELECT * FROM historial_excedentes 
            ORDER BY fecha_excedente DESC, created_at DESC 
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $historial[] = $row;
    }
    return $historial;
}

/* =======================================================
   🔹 Colores únicos por tipo de vehículo
======================================================= */
function obtenerColorVehiculo($vehiculo) {
    // Paleta de colores vibrantes para vehículos
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
    
    // Normalizar nombre del vehículo
    $vehiculo_lower = strtolower($vehiculo);
    
    // Buscar coincidencia exacta
    if (isset($colores_vehiculos[$vehiculo_lower])) {
        return $colores_vehiculos[$vehiculo_lower];
    }
    
    // Buscar coincidencias parciales
    foreach ($colores_vehiculos as $key => $color) {
        if (strpos($vehiculo_lower, $key) !== false) {
            return $color;
        }
    }
    
    // Generar color dinámico si no se encuentra
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
    
    // Limpiar nombre para columna SQL (siempre minúsculas)
    $nombre_columna = preg_replace('/[^a-zA-Z0-9_]/', '_', $nombre_clasificacion);
    $nombre_columna = strtolower($nombre_columna);
    
    // Crear nueva columna
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

    // IMPORTANTE: Normalizar el nombre del campo a minúsculas
    $campo = strtolower($campo);
    $campo = preg_replace('/[^a-z0-9_]/', '_', $campo);

    // Validar que el campo exista en la tabla tarifas
    $columnas_tarifas = obtenerColumnasTarifas($conn);
    
    // Si el campo no existe, intentar crearlo
    if (!in_array($campo, $columnas_tarifas)) { 
        if (crearNuevaColumnaTarifa($conn, $campo)) {
            // Actualizar lista de columnas después de crearla
            $columnas_tarifas = obtenerColumnasTarifas($conn);
        } else {
            echo "error: no se pudo crear el campo '$campo'";
            exit;
        }
    }

    // Insertar o actualizar
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

    // Normalizar clasificación a minúsculas
    $clasif = strtolower($clasif);

    if ($clasif === '') {
        // Eliminar clasificación si está vacía
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
    
    // Crear clave única para esta sesión/filtro
    $session_key = "columnas_seleccionadas_" . md5($empresa . $desde . $hasta);
    
    // Guardar en sesión
    setcookie($session_key, json_encode($columnas), time() + (86400 * 7), "/");
    
    echo "ok";
    exit;
}

/* =======================================================
   🔹 Guardar PRESUPUESTO (AJAX)
======================================================= */
if (isset($_POST['guardar_presupuesto'])) {
    $conductor = $conn->real_escape_string($_POST['conductor']);
    $empresa = $conn->real_escape_string($_POST['empresa']);
    $presupuesto = (float)$_POST['presupuesto'];
    
    if (guardarPresupuestoConductor($conn, $conductor, $empresa, $presupuesto)) {
        echo "ok";
    } else {
        echo "error: " . $conn->error;
    }
    exit;
}

/* =======================================================
   🔹 Obtener presupuestos (AJAX)
======================================================= */
if (isset($_GET['obtener_presupuestos'])) {
    $presupuestos = obtenerTodosPresupuestos($conn);
    echo json_encode($presupuestos);
    exit;
}

/* =======================================================
   🔹 Obtener historial de excedentes (AJAX)
======================================================= */
if (isset($_GET['obtener_historial_excedentes'])) {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $historial = obtenerHistorialExcedentes($conn, $limit);
    echo json_encode($historial);
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

    // Obtener clasificaciones disponibles
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
    
    // Agregar "otro" para no clasificados
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
        // Contadores dinámicos
        $counts = array_fill_keys(array_keys($legend), 0);
        
        // Contador de rutas sin clasificar para este conductor
        $rutas_sin_clasificar = [];
        $total_sin_clasificar = 0;

        $rowsHTML = "";
        
        while ($r = $res->fetch_assoc()) {
            $ruta = (string)$r['ruta'];
            $vehiculo = $r['tipo_vehiculo'];
            
            // Determinar clasificación
            $key = mb_strtolower(trim($ruta . '|' . $vehiculo), 'UTF-8');
            $cat = $clasif_rutas[$key] ?? 'otro';
            
            // Normalizar a minúsculas
            $cat = strtolower($cat);
            
            // Contar rutas sin clasificar
            if ($cat === 'otro' || $cat === '') {
                $total_sin_clasificar++;
                $rutas_sin_clasificar[] = [
                    'ruta' => $ruta,
                    'vehiculo' => $vehiculo,
                    'fecha' => $r['fecha']
                ];
            }
            
            // Si es nueva clasificación, agregar a legend
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

        // Generar HTML
        echo "<div class='space-y-3'>";
        
        // Mostrar alerta de rutas sin clasificar para este conductor
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
        
        // Leyenda dinámica con filtro
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
        echo "<p class='text-center text-gray-500 py-4'>No se encontraron viajes para este conductor en ese rango.</p>";
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
   🔹 Cálculo y armado de tablas DINÁMICO
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
    // Por defecto, mostrar todas las columnas
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

// Contador global de rutas sin clasificar por conductor
$rutas_sin_clasificar_por_conductor = [];

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $nombre   = $row['nombre'];
        $ruta     = $row['ruta'];
        $vehiculo = $row['tipo_vehiculo'];
        $pagoParcial = (int)($row['pago_parcial'] ?? 0);

        // Acumular pago parcial
        if (!isset($pagosConductor[$nombre])) $pagosConductor[$nombre] = 0;
        $pagosConductor[$nombre] += $pagoParcial;

        $keyRuta = mb_strtolower(trim($ruta . '|' . $vehiculo), 'UTF-8');

        // Guardar lista de rutas únicas
        if (!isset($rutasUnicas[$keyRuta])) {
            $rutasUnicas[$keyRuta] = [
                'ruta'          => $ruta,
                'vehiculo'      => $vehiculo,
                'clasificacion' => $clasif_rutas[$keyRuta] ?? ''
            ];
        }

        // Lista de tipos de vehículo
        if (!in_array($vehiculo, $vehiculos, true)) {
            $vehiculos[] = $vehiculo;
        }

        // Verificar si la ruta tiene clasificación
        $clasificacion_ruta = $clasif_rutas[$keyRuta] ?? '';
        if ($clasificacion_ruta === '' || $clasificacion_ruta === 'otro') {
            if (!isset($rutas_sin_clasificar_por_conductor[$nombre])) {
                $rutas_sin_clasificar_por_conductor[$nombre] = [];
            }
            // Evitar duplicados
            $ruta_key = $ruta . '|' . $vehiculo;
            if (!in_array($ruta_key, $rutas_sin_clasificar_por_conductor[$nombre])) {
                $rutas_sin_clasificar_por_conductor[$nombre][] = $ruta_key;
            }
        }

        // Inicializar datos del conductor (dinámicamente)
        if (!isset($datos[$nombre])) {
            $datos[$nombre] = [
                "vehiculo" => $vehiculo,
                "pagado"   => 0
            ];
            // Inicializar contadores para cada clasificación disponible
            foreach ($clasificaciones_disponibles as $clasif) {
                $datos[$nombre][$clasif] = 0;
            }
        }

        // Clasificación MANUAL de la ruta
        $clasifRuta = $clasif_rutas[$keyRuta] ?? '';

        // Si tiene clasificación, sumar al contador (crear campo si no existe)
        if ($clasifRuta !== '') {
            if (!isset($datos[$nombre][$clasifRuta])) {
                $datos[$nombre][$clasifRuta] = 0;
            }
            $datos[$nombre][$clasifRuta]++;
        }
    }
}

// Cargar presupuestos para todos los conductores
$presupuestos = obtenerTodosPresupuestos($conn);

// Inyectar pago acumulado y presupuesto
foreach ($datos as $conductor => $info) {
    $datos[$conductor]["pagado"] = (int)($pagosConductor[$conductor] ?? 0);
    
    // Inyectar contador de rutas sin clasificar
    $datos[$conductor]["rutas_sin_clasificar"] = count($rutas_sin_clasificar_por_conductor[$conductor] ?? []);
    
    // Inyectar presupuesto para esta empresa (si existe)
    $presupuesto_actual = 0;
    if ($empresaFiltro !== "" && isset($presupuestos[$conductor][$empresaFiltro])) {
        $presupuesto_actual = $presupuestos[$conductor][$empresaFiltro];
    }
    $datos[$conductor]["presupuesto"] = $presupuesto_actual;
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
  
  /* BUSCADOR */
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
  
  /* Estilos para alertas visuales */
  .alerta-sin-clasificar {
    animation: pulse-alerta 2s infinite;
  }
  
  @keyframes pulse-alerta {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
  }
  
  /* ===== BOLITAS FLOTANTES ORIGINALES ===== */
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
  
  /* Colores específicos para cada bolita (ORIGINALES) */
  .ball-tarifas {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
  }
  
  .ball-crear-clasif {
    background: linear-gradient(135deg, #10b981, #059669);
  }
  
  .ball-clasif-rutas {
    background: linear-gradient(135deg, #f59e0b, #d97706);
  }
  
  /* NUEVA BOLITA PARA PRESUPUESTOS */
  .ball-presupuestos {
    background: linear-gradient(135deg, #ec4899, #db2777);
  }
  
  .ball-selector-columnas {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
  }
  
  /* ===== PANELES DESLIZANTES ORIGINALES ===== */
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
  
  /* ===== TABLA CENTRAL CON ANIMACIÓN ORIGINAL ===== */
  .table-container-wrapper {
    transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    margin-left: 0;
  }
  
  .table-container-wrapper.with-panel {
    margin-left: 420px;
  }
  
  /* Indicador de panel activo */
  .ball-active {
    animation: pulse-ball 2s infinite;
    box-shadow: 0 0 0 8px rgba(59, 130, 246, 0.2);
  }
  
  @keyframes pulse-ball {
    0%, 100% { box-shadow: 0 8px 20px rgba(0,0,0,0.2), 0 0 0 0 rgba(59, 130, 246, 0.4); }
    50% { box-shadow: 0 8px 20px rgba(0,0,0,0.2), 0 0 0 12px rgba(59, 130, 246, 0); }
  }
  
  /* Animación de notificaciones */
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

  /* Colores para viajes */
  .row-viaje:hover { background-color: #f8fafc; }
  .cat-completo { background-color: rgba(209, 250, 229, 0.1); }
  .cat-medio { background-color: rgba(254, 243, 199, 0.1); }
  .cat-extra { background-color: rgba(241, 245, 249, 0.1); }
  .cat-siapana { background-color: rgba(250, 232, 255, 0.1); }
  .cat-carrotanque { background-color: rgba(207, 250, 254, 0.1); }
  .cat-otro { background-color: rgba(243, 244, 246, 0.1); }
  
  /* ===== ACORDEÓN PARA TARIFAS ORIGINAL ===== */
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
  
  /* ===== COLORES PARA FILAS DE CLASIFICACIÓN DE RUTAS ORIGINALES ===== */
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
  
  /* ===== NUEVOS ESTILOS PARA SELECTOR DE COLUMNAS ===== */
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
  
  /* Estilo para columnas ocultas en la tabla */
  .columna-oculta {
    display: none !important;
  }
  
  .columna-visualizada {
    display: table-cell !important;
  }
  
  /* ===== ESTILOS PARA CONTROL DE PRESUPUESTO ===== */
  .estado-presupuesto-verde {
    background-color: #dcfce7 !important;
    border-color: #86efac !important;
    color: #166534 !important;
  }
  
  .estado-presupuesto-amarillo {
    background-color: #fef9c3 !important;
    border-color: #fde047 !important;
    color: #854d0e !important;
    animation: pulse-amarillo 2s infinite;
  }
  
  @keyframes pulse-amarillo {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
  }
  
  .estado-presupuesto-rojo {
    background-color: #fee2e2 !important;
    border-color: #fca5a5 !important;
    color: #991b1b !important;
    animation: pulse-rojo 2s infinite;
  }
  
  @keyframes pulse-rojo {
    0%, 100% { 
      background-color: #fee2e2;
      box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
    }
    50% { 
      background-color: #fecaca;
      box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
    }
  }
  
  .badge-presupuesto {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
  }
  
  /* Modal de reporte de excedentes */
  .modal-reporte {
    max-width: 900px;
    width: 95%;
    max-height: 85vh;
  }
  
  /* Responsive */
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

  <!-- Encabezado CON FILTRO INTEGRADO -->
  <header class="max-w-[1800px] mx-auto px-3 md:px-4 pt-6">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-5 py-4">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-3">
        <!-- Título y filtro en la misma línea -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between w-full gap-3">
          <div class="flex items-center gap-3">
            <h2 class="text-xl md:text-2xl font-bold">🪙 Liquidación de Conductores</h2>
            <?php if ($empresaFiltro !== ""): ?>
              <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-sm font-medium">
                🏢 <?= htmlspecialchars($empresaFiltro) ?>
              </span>
            <?php endif; ?>
          </div>
          
          <!-- FILTRO DE FECHA - AHORA EN EL ENCABEZADO -->
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
        <span class="font-medium">Presupuestos activos:</span>
        <span class="bg-slate-100 px-2 py-1 rounded-lg font-semibold">
          <span id="contador-presupuestos-activos"><?= count($presupuestos) ?></span>
        </span>
      </div>
    </div>
  </header>

  <!-- ===== BOLITAS FLOTANTES (AHORA 5 BOLITAS) ===== -->
  <div class="floating-balls-container">
    <!-- Bolita 1: Tarifas por tipo de vehículo -->
    <div class="floating-ball ball-tarifas" id="ball-tarifas" data-panel="tarifas">
      <div class="ball-content">🚐</div>
      <div class="ball-tooltip">Tarifas por tipo de vehículo</div>
    </div>
    
    <!-- Bolita 2: Crear nueva clasificación -->
    <div class="floating-ball ball-crear-clasif" id="ball-crear-clasif" data-panel="crear-clasif">
      <div class="ball-content">➕</div>
      <div class="ball-tooltip">Crear nueva clasificación</div>
    </div>
    
    <!-- Bolita 3: Clasificar rutas existentes -->
    <div class="floating-ball ball-clasif-rutas" id="ball-clasif-rutas" data-panel="clasif-rutas">
      <div class="ball-content">🧭</div>
      <div class="ball-tooltip">Clasificar rutas existentes</div>
    </div>
    
    <!-- Bolita 4: NUEVA - Presupuestos por conductor -->
    <div class="floating-ball ball-presupuestos" id="ball-presupuestos" data-panel="presupuestos">
      <div class="ball-content">💰</div>
      <div class="ball-tooltip">Presupuestos por conductor</div>
    </div>
    
    <!-- Bolita 5: Seleccionar columnas de la tabla -->
    <div class="floating-ball ball-selector-columnas" id="ball-selector-columnas" data-panel="selector-columnas">
      <div class="ball-content">📊</div>
      <div class="ball-tooltip">Seleccionar columnas</div>
    </div>
  </div>

  <!-- ===== PANEL DE TARIFAS ===== -->
  <div class="side-panel" id="panel-tarifas">
    <div class="side-panel-header">
      <h3 class="text-lg font-semibold flex items-center gap-2">
        <span>🚐 Tarifas por Tipo de Vehículo</span>
        <span class="text-xs text-slate-500">(<?= count($columnas_tarifas) ?> tipos de tarifas)</span>
      </h3>
      <button class="side-panel-close" data-panel="tarifas">✕</button>
    </div>
    <div class="side-panel-body">
      <!-- Botones para expandir/colapsar todos -->
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
             data-vehiculo="<?= htmlspecialchars($veh) ?>"
             id="acordeon-<?= $veh_id ?>"
             style="background-color: <?= str_replace('bg-', '#', $color_vehiculo['dark']) ?>;">
          
          <!-- CABECERA DEL ACORDEÓN (siempre visible) -->
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
                  <?= count($columnas_tarifas) ?> tipos de tarifas configurados
                </div>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-xs px-2 py-1 rounded-full <?= $color_vehiculo['text'] ?> border <?= $color_vehiculo['border'] ?> bg-white/80">
                Configurar
              </span>
            </div>
          </div>
          
          <!-- CONTENIDO DESPLEGABLE (oculto inicialmente) -->
          <div class="acordeon-content px-4 py-3 border-t <?= $color_vehiculo['border'] ?> bg-white" id="content-<?= $veh_id ?>">
            <div class="space-y-3">
              <?php foreach ($columnas_tarifas as $columna): 
                $valor = isset($t[$columna]) ? (float)$t[$columna] : 0;
                $etiqueta = ucfirst($columna);
                
                // Etiquetas especiales
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
                
                // Obtener color para esta clasificación
                $estilo_clasif = obtenerEstiloClasificacion($columna);
              ?>
              <label class="block">
                <span class="block text-sm font-medium mb-1 <?= $estilo_clasif['text'] ?>">
                  <?= htmlspecialchars($etiqueta_final) ?>
                </span>
                <div class="relative">
                  <input type="number" step="1000" value="<?= $valor ?>"
                         data-campo="<?= htmlspecialchars($columna) ?>"
                         class="w-full rounded-xl border <?= $estilo_clasif['border'] ?> px-3 py-2 pr-10 text-right bg-white outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition tarifa-input"
                         style="border-color: <?= str_replace('border-', '#', $estilo_clasif['border']) ?>;"
                         oninput="recalcular()">
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

  <!-- ===== PANEL CREAR CLASIFICACIÓN ===== -->
  <div class="side-panel" id="panel-crear-clasif">
    <div class="side-panel-header">
      <h3 class="text-lg font-semibold flex items-center gap-2">
        <span>➕ Crear Nueva Clasificación</span>
        <span class="text-xs text-slate-500">Dinámico</span>
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

  <!-- ===== PANEL CLASIFICACIÓN RUTAS ===== -->
  <div class="side-panel" id="panel-clasif-rutas">
    <div class="side-panel-header">
      <h3 class="text-lg font-semibold flex items-center gap-2">
        <span>🧭 Clasificar Rutas Existentes</span>
        <span class="text-xs text-slate-500"><?= count($rutasUnicas) ?> rutas</span>
      </h3>
      <button class="side-panel-close" data-panel="clasif-rutas">✕</button>
    </div>
    <div class="side-panel-body">
      <div class="max-h-[calc(100vh-180px)] overflow-y-auto border border-slate-200 rounded-xl">
        <table class="w-full text-sm">
          <thead class="bg-slate-100 text-slate-600 sticky top-0 z-10">
            <tr>
              <th class="px-3 py-2 text-left sticky top-0 bg-slate-100">Ruta</th>
              <th class="px-3 py-2 text-center sticky top-0 bg-slate-100">Vehículo</th>
              <th class="px-3 py-2 text-center sticky top-0 bg-slate-100">Clasificación</th>
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
                <select class="select-clasif-ruta rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-100 w-full transition-all duration-300"
                        data-ruta="<?= htmlspecialchars($info['ruta']) ?>"
                        data-vehiculo="<?= htmlspecialchars($info['vehiculo']) ?>"
                        onchange="actualizarColorFila(this)">
                  <option value="">Sin clasificar</option>
                  <?php foreach ($clasificaciones_disponibles as $clasif): 
                    $estilo_opcion = obtenerEstiloClasificacion($clasif);
                  ?>
                  <option value="<?= htmlspecialchars($clasif) ?>" 
                          <?= $info['clasificacion']===$clasif ? 'selected' : '' ?>
                          style="background-color: <?= str_replace('bg-', '#', $estilo_opcion['bg']) ?>20; color: <?= str_replace('text-', '#', $estilo_opcion['text']) ?>; font-weight: 600;">
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
        Selecciona una clasificación para cada ruta. Los cambios se guardan automáticamente y la fila cambiará de color.
      </p>
    </div>
  </div>

  <!-- ===== NUEVO PANEL: PRESUPUESTOS POR CONDUCTOR ===== -->
  <div class="side-panel" id="panel-presupuestos">
    <div class="side-panel-header">
      <h3 class="text-lg font-semibold flex items-center gap-2">
        <span>💰 Presupuestos por Conductor</span>
        <span class="text-xs text-slate-500">Control de límites</span>
      </h3>
      <button class="side-panel-close" data-panel="presupuestos">✕</button>
    </div>
    <div class="side-panel-body">
      <div class="flex flex-col gap-4">
        <div>
          <p class="text-sm text-slate-600 mb-3">
            Asigna presupuestos por conductor y empresa. El sistema alertará cuando se alcance o supere el límite.
          </p>
        </div>
        
        <!-- Filtros para buscar conductores -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium mb-1 text-slate-600">Conductor</label>
            <input type="text" id="filtroConductorPresupuesto" 
                   placeholder="Buscar conductor..."
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500">
          </div>
          <div>
            <label class="block text-xs font-medium mb-1 text-slate-600">Empresa</label>
            <select id="filtroEmpresaPresupuesto" 
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500">
              <option value="">Todas las empresas</option>
              <?php foreach($empresas as $e): ?>
                <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        
        <!-- Lista de presupuestos -->
        <div class="max-h-[60vh] overflow-y-auto border border-slate-200 rounded-lg p-2" id="listaPresupuestos">
          <!-- Se cargará dinámicamente -->
          <div class="text-center py-4 text-slate-400">
            Cargando presupuestos...
          </div>
        </div>
        
        <!-- Reporte de excedentes -->
        <div class="mt-4 pt-4 border-t border-slate-200">
          <div class="flex flex-col gap-2">
            <button onclick="mostrarReporteExcedentes()" 
                    class="w-full py-2.5 bg-gradient-to-r from-rose-500 to-pink-500 text-white rounded-lg text-sm font-medium hover:from-rose-600 hover:to-pink-600 transition flex items-center justify-center gap-2">
              📊 Ver reporte de excedentes
            </button>
            <button onclick="exportarPresupuestos()" 
                    class="w-full py-2.5 bg-gradient-to-r from-emerald-500 to-green-500 text-white rounded-lg text-sm font-medium hover:from-emerald-600 hover:to-green-600 transition flex items-center justify-center gap-2">
              📁 Exportar presupuestos
            </button>
          </div>
        </div>
        
        <p class="text-xs text-slate-500 mt-2">
          <strong>Indicadores:</strong> 
          <span class="inline-flex items-center gap-1 ml-2"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Por debajo del 80%</span>
          <span class="inline-flex items-center gap-1 ml-2"><span class="w-2 h-2 rounded-full bg-amber-500"></span> 80-100%</span>
          <span class="inline-flex items-center gap-1 ml-2"><span class="w-2 h-2 rounded-full bg-rose-500"></span> Excedido (≥100%)</span>
        </p>
      </div>
    </div>
  </div>

  <!-- ===== PANEL: SELECTOR DE COLUMNAS ===== -->
  <div class="side-panel" id="panel-selector-columnas">
    <div class="side-panel-header">
      <h3 class="text-lg font-semibold flex items-center gap-2">
        <span>📊 Seleccionar Columnas</span>
        <span class="text-xs text-slate-500">Personalizar tabla</span>
      </h3>
      <button class="side-panel-close" data-panel="selector-columnas">✕</button>
    </div>
    <div class="side-panel-body">
      <div class="flex flex-col gap-4">
        <div>
          <p class="text-sm text-slate-600 mb-3">
            Marca/desmarca las columnas que quieres ver en la tabla principal.
            <span id="contador-seleccionadas-panel" class="font-semibold text-blue-600"><?= count($columnas_seleccionadas) ?></span> de 
            <?= count($clasificaciones_disponibles) ?> seleccionadas
          </p>
        </div>
        
        <!-- Botones de acción -->
        <div class="flex flex-wrap gap-2">
          <button onclick="seleccionarTodasColumnas()" 
                  class="text-xs px-3 py-1.5 rounded-lg border border-green-300 bg-green-50 text-green-700 hover:bg-green-100 transition whitespace-nowrap">
            ✅ Seleccionar todas
          </button>
          <button onclick="deseleccionarTodasColumnas()" 
                  class="text-xs px-3 py-1.5 rounded-lg border border-rose-300 bg-rose-50 text-rose-700 hover:bg-rose-100 transition whitespace-nowrap">
            ❌ Deseleccionar todas
          </button>
          <button onclick="guardarSeleccionColumnas()" 
                  class="text-xs px-3 py-1.5 rounded-lg border border-blue-300 bg-blue-50 text-blue-700 hover:bg-blue-100 transition whitespace-nowrap">
            💾 Guardar selección
          </button>
        </div>
        
        <!-- Lista de columnas -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-[60vh] overflow-y-auto p-2 border border-slate-200 rounded-lg">
          <?php foreach ($clasificaciones_disponibles as $clasif): 
            $estilo = obtenerEstiloClasificacion($clasif);
            $seleccionada = in_array($clasif, $columnas_seleccionadas);
          ?>
          <div class="columna-checkbox-item flex items-center gap-2 p-3 border border-slate-200 rounded-lg cursor-pointer transition <?= $seleccionada ? 'selected' : '' ?>"
               data-columna="<?= htmlspecialchars($clasif) ?>"
               onclick="toggleColumna('<?= htmlspecialchars($clasif) ?>')"
               title="<?= htmlspecialchars(ucfirst($clasif)) ?>">
            <div class="checkbox-columna <?= $seleccionada ? 'checked' : '' ?>" 
                 id="checkbox-<?= htmlspecialchars($clasif) ?>"></div>
            <div class="flex-1 flex flex-col">
              <span class="text-sm font-medium whitespace-nowrap <?= $estilo['text'] ?>">
                <?php 
                  // Nombre completo para mejor legibilidad
                  $nombres = [
                      'completo' => 'Viaje Completo',
                      'medio' => 'Viaje Medio', 
                      'extra' => 'Viaje Extra',
                      'carrotanque' => 'Carrotanque',
                      'siapana' => 'Siapana',
                      'riohacha' => 'Riohacha',
                      'pru' => 'Pru',
                      'maco' => 'Maco'
                  ];
                  echo $nombres[$clasif] ?? ucfirst($clasif);
                ?>
              </span>
              <span class="text-xs text-slate-500 mt-0.5">Columna: <?= htmlspecialchars($clasif) ?></span>
            </div>
            <div class="w-3 h-3 rounded-full" style="background-color: <?= str_replace('bg-', '#', $estilo['bg']) ?>;"></div>
          </div>
          <?php endforeach; ?>
        </div>
        
        <p class="text-xs text-slate-500 mt-2">
          La selección se aplica inmediatamente. Usa "Guardar selección" para recordarla en futuras visitas.
        </p>
      </div>
    </div>
  </div>

  <!-- ===== OVERLAY PARA PANELES ===== -->
  <div class="side-panel-overlay" id="sidePanelOverlay"></div>

  <!-- ===== MODAL PARA REPORTE DE EXCEDENTES ===== -->
  <div id="modalReporteExcedentes" class="viajes-backdrop">
    <div class="viajes-card modal-reporte">
      <div class="viajes-header">
        <div class="flex flex-col gap-2 w-full">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold flex items-center gap-2">
              ⚠️ Reporte de Excedentes de Presupuesto
            </h3>
            <button class="viajes-close text-slate-600 hover:bg-slate-100 border border-slate-300 px-2 py-1 rounded-lg text-sm" 
                    onclick="cerrarModalReporte()" title="Cerrar">
              ✕
            </button>
          </div>
          <div class="text-sm text-slate-500">
            Historial de conductores que han excedido su presupuesto
          </div>
        </div>
      </div>
      <div class="viajes-body" id="contenidoReporteExcedentes">
        <div class="text-center py-8">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto mb-2"></div>
          <p class="text-slate-500">Cargando reporte...</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Contenido principal -->
  <main class="max-w-[1800px] mx-auto px-3 md:px-4 py-6">
    <div class="table-container-wrapper" id="tableContainerWrapper">
      
      <!-- Botón para ver reporte de excedentes -->
      <div class="mb-4 flex flex-wrap gap-2">
        <button onclick="togglePanel('presupuestos')" 
                class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-gradient-to-r from-pink-500 to-rose-500 text-white hover:from-pink-600 hover:to-rose-600 transition shadow-md hover:shadow-lg">
          <span>💰</span>
          <span class="text-sm font-medium">Gestionar presupuestos</span>
        </button>
        
        <button onclick="mostrarAlertasExcedentes()" 
                class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-gradient-to-r from-amber-500 to-orange-500 text-white hover:from-amber-600 hover:to-orange-600 transition shadow-md hover:shadow-lg">
          <span>⚠️</span>
          <span class="text-sm font-medium" id="contadorAlertasExcedentes">0 alertas</span>
        </button>
        
        <button onclick="togglePanel('selector-columnas')" 
                class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-gradient-to-r from-purple-500 to-indigo-500 text-white hover:from-purple-600 hover:to-indigo-600 transition shadow-md hover:shadow-lg">
          <span>📊</span>
          <span class="text-sm font-medium">Seleccionar columnas</span>
        </button>
      </div>

      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <!-- Encabezado del panel central -->
        <div class="p-5 border-b border-slate-200">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <h3 class="text-xl font-semibold">🧑‍✈️ Resumen por Conductor</h3>
              <div id="contador-conductores" class="text-sm text-slate-500 mt-1">
                Mostrando <?= count($datos) ?> conductores • 
                <span id="contador-excedidos">0</span> excedieron presupuesto
              </div>
            </div>
            <div class="flex items-center gap-2">
              <!-- Botón para mostrar resumen de rutas sin clasificar -->
              <button onclick="mostrarResumenRutasSinClasificar()" 
                      class="text-sm px-4 py-2 rounded-full bg-gradient-to-r from-amber-500 to-orange-500 text-white hover:from-amber-600 hover:to-orange-600 transition flex items-center gap-2 shadow-md hover:shadow-lg">
                ⚠️ Ver rutas sin clasificar
              </button>
            </div>
          </div>
        </div>

        <!-- Contenido del panel central -->
        <div class="p-5">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div class="flex flex-col md:flex-row gap-4 w-full md:w-auto">
              <!-- BUSCADOR -->
              <div class="buscar-container w-full md:w-64">
                <input id="buscadorConductores" type="text" 
                       placeholder="Buscar conductor..." 
                       class="w-full rounded-xl border border-slate-300 px-4 py-3 pl-4 pr-10 text-sm">
                <button id="clearBuscar" class="buscar-clear">✕</button>
              </div>

              <div id="total_chip_container" class="flex flex-wrap items-center gap-3">
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
                <span class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-amber-700 font-semibold text-sm">
                  ⚠️ Excedidos: <span id="total_excedidos">0</span>
                </span>
              </div>
            </div>
          </div>

          <!-- Resumen de rutas sin clasificar -->
          <div id="resumenRutasSinClasificar" class="hidden mb-6">
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
              <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                  <span class="text-amber-600 font-bold text-lg">⚠️</span>
                  <h4 class="font-semibold text-amber-800">Rutas sin clasificar encontradas</h4>
                </div>
                <span id="contadorRutasSinClasificarGlobal" class="px-3 py-1 bg-amber-500 text-white text-sm font-bold rounded-full">0</span>
              </div>
              
              <div id="listaRutasSinClasificarGlobal" class="space-y-2 max-h-60 overflow-y-auto">
                <!-- Aquí se cargarán las rutas dinámicamente -->
              </div>
              
              <div class="mt-4 pt-4 border-t border-amber-100">
                <button onclick="irAClasificacionRutas()" 
                        class="w-full py-3 bg-amber-100 text-amber-700 hover:bg-amber-200 rounded-xl text-sm font-medium transition flex items-center justify-center gap-2">
                  🧭 Ir a clasificar rutas
                </button>
              </div>
            </div>
          </div>

          <!-- CONTENEDOR DE TABLA CON ENCABEZADOS FIJOS -->
          <div id="tableContainer" class="overflow-x-auto rounded-xl border border-slate-200 max-h-[70vh]">
            <table id="tabla_conductores" class="w-full text-sm">
              <thead class="bg-blue-600 text-white sticky top-0 z-20">
                <tr>
                  <!-- COLUMNA PARA ESTADO DE PRESUPUESTO -->
                  <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 90px;">
                    Presupuesto
                  </th>
                  
                  <!-- COLUMNA PARA ALERTAS VISUALES -->
                  <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 70px;">
                    Estado
                  </th>
                  <th class="px-4 py-3 text-left sticky top-0 bg-blue-600" style="min-width: 220px;">
                    Conductor
                  </th>
                  <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 120px;">
                    Tipo
                  </th>
                  
                  <?php foreach ($clasificaciones_disponibles as $index => $clasif): 
                    $estilo = obtenerEstiloClasificacion($clasif);
                    // Definir abreviaturas
                    $abreviaturas = [
                        'completo' => 'COM',
                        'medio' => 'MED', 
                        'extra' => 'EXT',
                        'carrotanque' => 'CTK',
                        'siapana' => 'SIA',
                        'riohacha' => 'RIO',
                        'pru' => 'PRU',
                        'maco' => 'MAC'
                    ];
                    $abreviatura = $abreviaturas[$clasif] ?? strtoupper(substr($clasif, 0, 3));
                    
                    // Determinar si la columna está visible
                    $visible = in_array($clasif, $columnas_seleccionadas);
                    $clase_visibilidad = $visible ? 'columna-visualizada' : 'columna-oculta';
                    
                    // Mapear colores Tailwind a colores HEX para CSS inline
                    $colorMap = [
                        'bg-emerald-100' => '#d1fae5', 'text-emerald-700' => '#047857', 'border-emerald-200' => '#a7f3d0',
                        'bg-amber-100' => '#fef3c7', 'text-amber-800' => '#92400e', 'border-amber-200' => '#fcd34d',
                        'bg-slate-200' => '#e2e8f0', 'text-slate-800' => '#1e293b', 'border-slate-300' => '#cbd5e1',
                        'bg-fuchsia-100' => '#fae8ff', 'text-fuchsia-700' => '#a21caf', 'border-fuchsia-200' => '#f5d0fe',
                        'bg-cyan-100' => '#cffafe', 'text-cyan-800' => '#155e75', 'border-cyan-200' => '#a5f3fc',
                        'bg-indigo-100' => '#e0e7ff', 'text-indigo-700' => '#4338ca', 'border-indigo-200' => '#c7d2fe',
                        'bg-teal-100' => '#ccfbf1', 'text-teal-700' => '#0f766e', 'border-teal-200' => '#99f6e4',
                        'bg-rose-100' => '#ffe4e6', 'text-rose-700' => '#be123c', 'border-rose-200' => '#fecdd3',
                        'bg-violet-100' => '#ede9fe', 'text-violet-700' => '#6d28d9', 'border-violet-200' => '#ddd6fe',
                        'bg-orange-100' => '#ffedd5', 'text-orange-700' => '#c2410c', 'border-orange-200' => '#fdba74',
                        'bg-lime-100' => '#ecfccb', 'text-lime-700' => '#4d7c0f', 'border-lime-200' => '#d9f99d',
                        'bg-sky-100' => '#e0f2fe', 'text-sky-700' => '#0369a1', 'border-sky-200' => '#bae6fd',
                        'bg-pink-100' => '#fce7f3', 'text-pink-700' => '#be185d', 'border-pink-200' => '#fbcfe8',
                        'bg-purple-100' => '#f3e8ff', 'text-purple-700' => '#7e22ce', 'border-purple-200' => '#e9d5ff',
                        'bg-yellow-100' => '#fef9c3', 'text-yellow-700' => '#a16207', 'border-yellow-200' => '#fde68a',
                        'bg-red-100' => '#fee2e2', 'text-red-700' => '#b91c1c', 'border-red-200' => '#fecaca'
                    ];
                    
                    $bg_color = $colorMap[$estilo['bg']] ?? '#f1f5f9';
                    $text_color = $colorMap[$estilo['text']] ?? '#1e293b';
                  ?>
                  <th class="px-4 py-3 text-center sticky top-0 <?= $clase_visibilidad ?> columna-tabla" 
                      data-columna="<?= htmlspecialchars($clasif) ?>"
                      title="<?= htmlspecialchars($clasif) ?>"
                      style="min-width: 80px; background-color: <?= $bg_color ?>; color: <?= $text_color ?>; border-bottom: 2px solid <?= $colorMap[$estilo['border']] ?? '#cbd5e1' ?>; z-index: 19;">
                    <?= htmlspecialchars($abreviatura) ?>
                  </th>
                  <?php endforeach; ?>
                  <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 140px; z-index: 20;">
                    Total
                  </th>
                  <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 120px; z-index: 20;">
                    Pagado
                  </th>
                  <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 100px; z-index: 20;">
                    Faltante
                  </th>
                </tr>
              </thead>
              <tbody id="tabla_conductores_body" class="divide-y divide-slate-100 bg-white">
              <?php foreach ($datos as $conductor => $info): 
                $esMensual = (stripos($info['vehiculo'], 'mensual') !== false);
                $claseVehiculo = $esMensual ? 'vehiculo-mensual' : '';
                $rutasSinClasificar = $info['rutas_sin_clasificar'] ?? 0;
                $presupuesto = $info['presupuesto'] ?? 0;
                $color_vehiculo = obtenerColorVehiculo($info['vehiculo']);
                
                // Determinar clase CSS según el estado del presupuesto
                $clasePresupuesto = '';
                if ($presupuesto > 0) {
                  $clasePresupuesto = 'tiene-presupuesto';
                }
              ?>
                <tr data-vehiculo="<?= htmlspecialchars($info['vehiculo']) ?>" 
                    data-conductor="<?= htmlspecialchars($conductor) ?>" 
                    data-conductor-normalizado="<?= htmlspecialchars(mb_strtolower($conductor)) ?>"
                    data-pagado="<?= (int)($info['pagado'] ?? 0) ?>"
                    data-sin-clasificar="<?= $rutasSinClasificar ?>"
                    data-presupuesto="<?= $presupuesto ?>"
                    class="hover:bg-blue-50/40 transition-colors <?= $clasePresupuesto ?> <?= $rutasSinClasificar > 0 ? 'alerta-sin-clasificar' : '' ?>">
                  
                  <!-- CELDA: Estado de presupuesto -->
                  <td class="px-4 py-3 text-center" style="min-width: 90px;">
                    <?php if ($presupuesto > 0): ?>
                      <div class="flex flex-col items-center justify-center gap-1" title="Presupuesto: $<?= number_format($presupuesto, 0, ',', '.') ?>">
                        <div class="text-xs font-semibold bg-gradient-to-r from-cyan-500 to-blue-500 text-white px-2 py-1 rounded-full">
                          $<?= number_format($presupuesto, 0, ',', '.') ?>
                        </div>
                        <div class="text-[10px] text-slate-500">
                          Asignado
                        </div>
                      </div>
                    <?php else: ?>
                      <div class="flex flex-col items-center justify-center gap-1" title="Sin presupuesto asignado">
                        <div class="text-xs font-medium bg-slate-100 text-slate-500 px-2 py-1 rounded-full">
                          Sin límite
                        </div>
                        <button onclick="asignarPresupuestoRapido('<?= htmlspecialchars($conductor) ?>')" 
                                class="text-[10px] text-blue-600 hover:text-blue-800 hover:underline">
                          Asignar
                        </button>
                      </div>
                    <?php endif; ?>
                  </td>
                  
                  <!-- CELDA: Indicador visual de rutas sin clasificar -->
                  <td class="px-4 py-3 text-center" style="min-width: 70px;">
                    <?php if ($rutasSinClasificar > 0): ?>
                      <div class="flex flex-col items-center justify-center gap-1" title="<?= $rutasSinClasificar ?> ruta(s) sin clasificar">
                        <span class="text-amber-600 font-bold animate-pulse">⚠️</span>
                        <span class="text-xs bg-amber-100 text-amber-800 px-2 py-1 rounded-full font-bold">
                          <?= $rutasSinClasificar ?>
                        </span>
                      </div>
                    <?php else: ?>
                      <div class="flex flex-col items-center justify-center gap-1" title="Todas las rutas están clasificadas">
                        <span class="text-emerald-600">✅</span>
                        <span class="text-xs bg-emerald-100 text-emerald-800 px-2 py-1 rounded-full font-bold">
                          0
                        </span>
                      </div>
                    <?php endif; ?>
                  </td>
                  
                  <td class="px-4 py-3" style="min-width: 220px;">
                    <button type="button"
                            class="conductor-link text-blue-700 hover:text-blue-900 underline underline-offset-2 transition flex items-center gap-2"
                            title="Ver viajes">
                      <?php if ($rutasSinClasificar > 0): ?>
                        <span class="text-amber-600">⚠️</span>
                      <?php endif; ?>
                      <?= htmlspecialchars($conductor) ?>
                    </button>
                  </td>
                  <td class="px-4 py-3 text-center" style="min-width: 120px;">
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
                    
                    // Determinar si la columna está visible
                    $visible = in_array($clasif, $columnas_seleccionadas);
                    $clase_visibilidad = $visible ? 'columna-visualizada' : 'columna-oculta';
                    
                    // Mapear colores para fondo de celdas
                    $colorMap = [
                        'bg-emerald-100' => '#f0fdf4', 'text-emerald-700' => '#047857',
                        'bg-amber-100' => '#fffbeb', 'text-amber-800' => '#92400e',
                        'bg-slate-200' => '#f8fafc', 'text-slate-800' => '#1e293b',
                        'bg-fuchsia-100' => '#fdf4ff', 'text-fuchsia-700' => '#a21caf',
                        'bg-cyan-100' => '#ecfeff', 'text-cyan-800' => '#155e75',
                        'bg-indigo-100' => '#eef2ff', 'text-indigo-700' => '#4338ca',
                        'bg-teal-100' => '#f0fdfa', 'text-teal-700' => '#0f766e',
                        'bg-rose-100' => '#fff1f2', 'text-rose-700' => '#be123c',
                        'bg-violet-100' => '#f5f3ff', 'text-violet-700' => '#6d28d9',
                        'bg-orange-100' => '#fff7ed', 'text-orange-700' => '#c2410c',
                        'bg-lime-100' => '#f7fee7', 'text-lime-700' => '#4d7c0f',
                        'bg-sky-100' => '#f0f9ff', 'text-sky-700' => '#0369a1',
                        'bg-pink-100' => '#fdf2f8', 'text-pink-700' => '#be185d',
                        'bg-purple-100' => '#faf5ff', 'text-purple-700' => '#7e22ce',
                        'bg-yellow-100' => '#fefce8', 'text-yellow-700' => '#a16207',
                        'bg-red-100' => '#fef2f2', 'text-red-700' => '#b91c1c'
                    ];
                    
                    $bg_cell_color = $colorMap[$estilo['bg']] ?? '#f8fafc';
                    $text_cell_color = $colorMap[$estilo['text']] ?? '#1e293b';
                  ?>
                  <td class="px-4 py-3 text-center font-medium <?= $clase_visibilidad ?> columna-tabla" 
                      data-columna="<?= htmlspecialchars($clasif) ?>"
                      style="min-width: 80px; background-color: <?= $bg_cell_color ?>; color: <?= $text_cell_color ?>; border-left: 1px solid <?= str_replace('bg-', '#', $estilo['bg']) ?>30; border-right: 1px solid <?= str_replace('bg-', '#', $estilo['bg']) ?>30;">
                    <?= $cantidad ?>
                  </td>
                  <?php endforeach; ?>

                  <!-- Total -->
                  <td class="px-4 py-3" style="min-width: 140px;">
                    <input type="text"
                           class="totales w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-slate-50 outline-none whitespace-nowrap tabular-nums"
                           readonly dir="ltr"
                           data-presupuesto="<?= $presupuesto ?>">
                  </td>

                  <!-- Pagado -->
                  <td class="px-4 py-3" style="min-width: 120px;">
                    <input type="text"
                           class="pagado w-full rounded-xl border border-emerald-200 px-3 py-2 text-right bg-emerald-50 outline-none whitespace-nowrap tabular-nums"
                           readonly dir="ltr"
                           value="<?= number_format((int)($info['pagado'] ?? 0), 0, ',', '.') ?>">
                  </td>

                  <!-- Faltante -->
                  <td class="px-4 py-3" style="min-width: 100px;">
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
        </div>
      </div>
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
    // ===== SISTEMA DE BOLITAS Y PANELES =====
    let activePanel = null;
    const panels = ['tarifas', 'crear-clasif', 'clasif-rutas', 'presupuestos', 'selector-columnas'];
    
    // Datos globales
    let presupuestosGlobales = <?= json_encode($presupuestos) ?>;
    let empresaFiltroActual = "<?= htmlspecialchars($empresaFiltro) ?>";
    
    // Inicializar sistema de bolitas
    document.addEventListener('DOMContentLoaded', function() {
      // Configurar eventos para cada bolita
      panels.forEach(panelId => {
        const ball = document.getElementById(`ball-${panelId}`);
        const panel = document.getElementById(`panel-${panelId}`);
        const closeBtn = panel.querySelector('.side-panel-close');
        const overlay = document.getElementById('sidePanelOverlay');
        const tableWrapper = document.getElementById('tableContainerWrapper');
        
        // Abrir panel al hacer clic en la bolita
        ball.addEventListener('click', () => togglePanel(panelId));
        
        // Cerrar panel con el botón X
        closeBtn.addEventListener('click', () => togglePanel(panelId));
        
        // Cerrar panel al hacer clic en el overlay
        overlay.addEventListener('click', () => {
          if (activePanel === panelId) {
            togglePanel(panelId);
          }
        });
      });
      
      // Cerrar panel con tecla ESC
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && activePanel) {
          togglePanel(activePanel);
        }
      });
      
      // Inicializar acordeón de tarifas
      colapsarTodosTarifas();
      
      // Inicializar colores de las filas de clasificación
      inicializarColoresClasificacion();
      
      // Inicializar sistema de selección de columnas
      inicializarSeleccionColumnas();
      
      // Cargar presupuestos en el panel
      cargarPresupuestos();
      
      // Configurar eventos para el filtro de presupuestos
      document.getElementById('filtroConductorPresupuesto').addEventListener('input', filtrarPresupuestos);
      document.getElementById('filtroEmpresaPresupuesto').addEventListener('change', filtrarPresupuestos);
      
      // Recalcular al cargar (incluye control de presupuestos)
      recalcular();
    });
    
    // Función para abrir/cerrar paneles
    function togglePanel(panelId) {
      const ball = document.getElementById(`ball-${panelId}`);
      const panel = document.getElementById(`panel-${panelId}`);
      const overlay = document.getElementById('sidePanelOverlay');
      const tableWrapper = document.getElementById('tableContainerWrapper');
      
      if (activePanel === panelId) {
        // Cerrar panel actual
        panel.classList.remove('active');
        ball.classList.remove('ball-active');
        overlay.classList.remove('active');
        tableWrapper.classList.remove('with-panel');
        activePanel = null;
      } else {
        // Cerrar panel anterior si hay uno abierto
        if (activePanel) {
          document.getElementById(`panel-${activePanel}`).classList.remove('active');
          document.getElementById(`ball-${activePanel}`).classList.remove('ball-active');
        }
        
        // Abrir nuevo panel
        panel.classList.add('active');
        ball.classList.add('ball-active');
        overlay.classList.add('active');
        tableWrapper.classList.add('with-panel');
        activePanel = panelId;
        
        // Si es el panel de presupuestos, recargar datos
        if (panelId === 'presupuestos') {
          cargarPresupuestos();
        }
        
        // Asegurar que el panel esté visible
        setTimeout(() => {
          panel.scrollTop = 0;
        }, 100);
      }
    }
    
    // ===== FUNCIONES PARA EL ACORDEÓN DE TARIFAS =====
    
    function toggleAcordeon(vehiculoId) {
      const content = document.getElementById('content-' + vehiculoId);
      const icon = document.getElementById('icon-' + vehiculoId);
      
      if (content.classList.contains('expanded')) {
        // Colapsar
        content.classList.remove('expanded');
        icon.classList.remove('expanded');
        content.style.maxHeight = '0';
      } else {
        // Expandir
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
    
    // ===== FUNCIONES PARA COLORES DE CLASIFICACIÓN DE RUTAS =====
    
    function inicializarColoresClasificacion() {
      const filas = document.querySelectorAll('.fila-ruta');
      filas.forEach(fila => {
        const select = fila.querySelector('.select-clasif-ruta');
        actualizarColorFila(select);
      });
    }
    
    function actualizarColorFila(selectElement) {
      const fila = selectElement.closest('tr');
      const clasificacion = selectElement.value.toLowerCase();
      const ruta = fila.dataset.ruta;
      const vehiculo = fila.dataset.vehiculo;
      
      // Limpiar clases de color anteriores
      fila.classList.forEach(className => {
        if (className.startsWith('fila-clasificada-')) {
          fila.classList.remove(className);
        }
      });
      
      // Actualizar dataset
      fila.dataset.clasificacion = clasificacion;
      
      // Aplicar nueva clase de color si hay clasificación
      if (clasificacion) {
        fila.classList.add('fila-clasificada-' + clasificacion);
      }
      
      // Guardar la clasificación en la base de datos
      guardarClasificacionRuta(ruta, vehiculo, clasificacion);
    }
    
    // ===== SISTEMA DE PRESUPUESTOS =====
    
    function cargarPresupuestos() {
      const listaPresupuestos = document.getElementById('listaPresupuestos');
      
      // Mostrar loading
      listaPresupuestos.innerHTML = `
        <div class="text-center py-8">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto mb-2"></div>
          <p class="text-slate-500">Cargando presupuestos...</p>
        </div>
      `;
      
      // Obtener datos actualizados del servidor
      fetch('<?= basename(__FILE__) ?>?obtener_presupuestos=1')
        .then(r => r.json())
        .then(data => {
          presupuestosGlobales = data;
          actualizarListaPresupuestos(data);
        })
        .catch(error => {
          console.error('Error cargando presupuestos:', error);
          listaPresupuestos.innerHTML = `
            <div class="text-center py-8 text-rose-600">
              ❌ Error cargando presupuestos
            </div>
          `;
        });
    }
    
    function actualizarListaPresupuestos(presupuestos) {
      const listaPresupuestos = document.getElementById('listaPresupuestos');
      const empresaFiltro = document.getElementById('filtroEmpresaPresupuesto').value;
      const conductorFiltro = document.getElementById('filtroConductorPresupuesto').value.toLowerCase();
      
      let html = '';
      let contador = 0;
      
      // Recorrer todos los presupuestos
      Object.keys(presupuestos).sort().forEach(conductor => {
        Object.keys(presupuestos[conductor]).sort().forEach(empresa => {
          const presupuesto = presupuestos[conductor][empresa];
          
          // Aplicar filtros
          if (empresaFiltro && empresa !== empresaFiltro) return;
          if (conductorFiltro && !conductor.toLowerCase().includes(conductorFiltro)) return;
          
          // Obtener estado actual del presupuesto
          const estado = obtenerEstadoPresupuesto(conductor, empresa, presupuesto);
          const porcentaje = estado.porcentaje;
          const excedente = estado.excedente;
          
          // Determinar clase CSS según el porcentaje
          let claseEstado = '';
          if (porcentaje >= 100) {
            claseEstado = 'estado-presupuesto-rojo';
          } else if (porcentaje >= 80) {
            claseEstado = 'estado-presupuesto-amarillo';
          } else {
            claseEstado = 'estado-presupuesto-verde';
          }
          
          html += `
            <div class="mb-3 p-3 border border-slate-200 rounded-lg hover:bg-slate-50 transition ${claseEstado}">
              <div class="flex items-center justify-between mb-2">
                <div class="flex-1">
                  <div class="flex items-center gap-2">
                    <span class="font-semibold text-slate-800">${conductor}</span>
                    <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded">${empresa}</span>
                  </div>
                  <div class="text-sm text-slate-600 mt-1">
                    Presupuesto: <span class="font-semibold">$${formatNumber(presupuesto)}</span>
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-lg font-bold ${porcentaje >= 100 ? 'text-rose-600' : porcentaje >= 80 ? 'text-amber-600' : 'text-emerald-600'}">
                    ${porcentaje.toFixed(1)}%
                  </div>
                  <div class="text-xs text-slate-500">
                    ${porcentaje >= 100 ? 'EXCEDIDO' : 'Utilizado'}
                  </div>
                </div>
              </div>
              
              <div class="flex items-center gap-3 mt-2">
                <div class="flex-1">
                  <input type="number" 
                         value="${presupuesto}"
                         data-conductor="${conductor}"
                         data-empresa="${empresa}"
                         class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500 transition"
                         onchange="actualizarPresupuesto(this)">
                </div>
                <button onclick="eliminarPresupuesto('${conductor}', '${empresa}')" 
                        class="px-3 py-1.5 rounded-lg bg-rose-100 text-rose-700 hover:bg-rose-200 text-sm transition">
                  ❌
                </button>
              </div>
              
              ${porcentaje >= 100 ? `
                <div class="mt-2 text-xs text-rose-600 font-medium">
                  ⚠️ Excedente: $${formatNumber(excedente)}
                </div>
              ` : ''}
            </div>
          `;
          
          contador++;
        });
      });
      
      if (contador === 0) {
        html = `
          <div class="text-center py-8 text-slate-400">
            ${conductorFiltro || empresaFiltro ? 'No se encontraron presupuestos con esos filtros' : 'No hay presupuestos asignados'}
          </div>
        `;
      }
      
      listaPresupuestos.innerHTML = html;
      document.getElementById('contador-presupuestos-activos').textContent = Object.keys(presupuestos).length;
    }
    
    function filtrarPresupuestos() {
      actualizarListaPresupuestos(presupuestosGlobales);
    }
    
    function obtenerEstadoPresupuesto(conductor, empresa, presupuesto) {
      // Buscar el conductor en la tabla
      const fila = document.querySelector(`tr[data-conductor="${conductor}"]`);
      if (!fila) {
        return { porcentaje: 0, excedente: 0, total: 0 };
      }
      
      // Obtener el total actual del conductor
      const inputTotal = fila.querySelector('input.totales');
      const totalTexto = inputTotal ? inputTotal.value.replace(/[^0-9]/g, '') : '0';
      const total = parseInt(totalTexto) || 0;
      
      // Calcular porcentaje y excedente
      const porcentaje = presupuesto > 0 ? (total / presupuesto) * 100 : 0;
      const excedente = total > presupuesto ? total - presupuesto : 0;
      
      return { porcentaje, excedente, total };
    }
    
    function actualizarPresupuesto(input) {
      const conductor = input.dataset.conductor;
      const empresa = input.dataset.empresa;
      const presupuesto = parseFloat(input.value) || 0;
      
      if (presupuesto < 0) {
        input.value = 0;
        mostrarNotificacion('❌ El presupuesto no puede ser negativo', 'error');
        return;
      }
      
      // Guardar en el servidor
      fetch('<?= basename(__FILE__) ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          guardar_presupuesto: 1,
          conductor: conductor,
          empresa: empresa,
          presupuesto: presupuesto
        })
      })
      .then(r => r.text())
      .then(respuesta => {
        if (respuesta.trim() === 'ok') {
          // Actualizar datos locales
          if (!presupuestosGlobales[conductor]) {
            presupuestosGlobales[conductor] = {};
          }
          presupuestosGlobales[conductor][empresa] = presupuesto;
          
          // Actualizar la tabla principal
          const fila = document.querySelector(`tr[data-conductor="${conductor}"]`);
          if (fila) {
            fila.dataset.presupuesto = presupuesto;
            
            // Actualizar la celda de presupuesto
            const celdaPresupuesto = fila.querySelector('td:nth-child(1)');
            if (celdaPresupuesto && presupuesto > 0) {
              celdaPresupuesto.innerHTML = `
                <div class="flex flex-col items-center justify-center gap-1" title="Presupuesto: $${formatNumber(presupuesto)}">
                  <div class="text-xs font-semibold bg-gradient-to-r from-cyan-500 to-blue-500 text-white px-2 py-1 rounded-full">
                    $${formatNumber(presupuesto)}
                  </div>
                  <div class="text-[10px] text-slate-500">
                    Asignado
                  </div>
                </div>
              `;
            }
          }
          
          mostrarNotificacion('✅ Presupuesto actualizado', 'success');
          recalcular(); // Recalcular con nuevos datos
          
          // Si estamos en el panel de presupuestos, actualizar la lista
          if (activePanel === 'presupuestos') {
            setTimeout(() => {
              actualizarListaPresupuestos(presupuestosGlobales);
            }, 100);
          }
        } else {
          mostrarNotificacion('❌ Error actualizando presupuesto', 'error');
          // Restaurar valor anterior
          input.value = presupuestosGlobales[conductor]?.[empresa] || 0;
        }
      })
      .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('❌ Error de conexión', 'error');
      });
    }
    
    function eliminarPresupuesto(conductor, empresa) {
      if (!confirm(`¿Eliminar presupuesto de ${conductor} para ${empresa}?`)) return;
      
      // Establecer presupuesto a 0 (equivalente a eliminarlo)
      fetch('<?= basename(__FILE__) ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          guardar_presupuesto: 1,
          conductor: conductor,
          empresa: empresa,
          presupuesto: 0
        })
      })
      .then(r => r.text())
      .then(respuesta => {
        if (respuesta.trim() === 'ok') {
          // Eliminar de datos locales
          if (presupuestosGlobales[conductor]) {
            delete presupuestosGlobales[conductor][empresa];
            // Si no quedan más presupuestos para este conductor, eliminar la entrada
            if (Object.keys(presupuestosGlobales[conductor]).length === 0) {
              delete presupuestosGlobales[conductor];
            }
          }
          
          // Actualizar la tabla principal
          const fila = document.querySelector(`tr[data-conductor="${conductor}"]`);
          if (fila) {
            fila.dataset.presupuesto = 0;
            
            // Actualizar la celda de presupuesto
            const celdaPresupuesto = fila.querySelector('td:nth-child(1)');
            if (celdaPresupuesto) {
              celdaPresupuesto.innerHTML = `
                <div class="flex flex-col items-center justify-center gap-1" title="Sin presupuesto asignado">
                  <div class="text-xs font-medium bg-slate-100 text-slate-500 px-2 py-1 rounded-full">
                    Sin límite
                  </div>
                  <button onclick="asignarPresupuestoRapido('${conductor}')" 
                          class="text-[10px] text-blue-600 hover:text-blue-800 hover:underline">
                    Asignar
                  </button>
                </div>
              `;
            }
          }
          
          mostrarNotificacion('✅ Presupuesto eliminado', 'success');
          recalcular();
          
          // Actualizar lista en panel
          if (activePanel === 'presupuestos') {
            actualizarListaPresupuestos(presupuestosGlobales);
          }
        } else {
          mostrarNotificacion('❌ Error eliminando presupuesto', 'error');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('❌ Error de conexión', 'error');
      });
    }
    
    function asignarPresupuestoRapido(conductor) {
      // Abrir panel de presupuestos
      togglePanel('presupuestos');
      
      // Establecer filtro por conductor
      setTimeout(() => {
        document.getElementById('filtroConductorPresupuesto').value = conductor;
        if (empresaFiltroActual) {
          document.getElementById('filtroEmpresaPresupuesto').value = empresaFiltroActual;
        }
        filtrarPresupuestos();
        
        // Hacer scroll al primer elemento
        const lista = document.getElementById('listaPresupuestos');
        if (lista.firstChild) {
          lista.firstChild.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      }, 300);
    }
    
    function mostrarAlertasExcedentes() {
      const conductoresExcedidos = [];
      const filas = document.querySelectorAll('#tabla_conductores_body tr');
      
      filas.forEach(fila => {
        const conductor = fila.dataset.conductor;
        const presupuesto = parseFloat(fila.dataset.presupuesto) || 0;
        
        if (presupuesto > 0) {
          const inputTotal = fila.querySelector('input.totales');
          const totalTexto = inputTotal ? inputTotal.value.replace(/[^0-9]/g, '') : '0';
          const total = parseInt(totalTexto) || 0;
          
          if (total >= presupuesto) {
            const porcentaje = Math.round((total / presupuesto) * 100);
            const excedente = total - presupuesto;
            
            conductoresExcedidos.push({
              conductor,
              total: formatNumber(total),
              presupuesto: formatNumber(presupuesto),
              porcentaje,
              excedente: formatNumber(excedente)
            });
          }
        }
      });
      
      if (conductoresExcedidos.length > 0) {
        let mensaje = `⚠️ **${conductoresExcedidos.length} conductor(es) excedieron su presupuesto:**\n\n`;
        
        conductoresExcedidos.forEach(c => {
          mensaje += `• **${c.conductor}**: $${c.total} / $${c.presupuesto} (${c.porcentaje}%)\n`;
          mensaje += `  Excedente: $${c.excedente}\n\n`;
        });
        
        alert(mensaje);
      } else {
        alert('🎉 ¡Excelente! Ningún conductor ha excedido su presupuesto.');
      }
    }
    
    function mostrarReporteExcedentes() {
      const modal = document.getElementById('modalReporteExcedentes');
      const contenido = document.getElementById('contenidoReporteExcedentes');
      
      modal.classList.add('show');
      
      // Cargar historial del servidor
      fetch('<?= basename(__FILE__) ?>?obtener_historial_excedentes=1&limit=100')
        .then(r => r.json())
        .then(historial => {
          if (historial.length === 0) {
            contenido.innerHTML = `
              <div class="text-center py-8 text-emerald-600">
                <div class="text-4xl mb-4">🎉</div>
                <p class="text-lg font-semibold">¡Excelente!</p>
                <p class="text-slate-600">No hay registros de excedentes en el historial.</p>
              </div>
            `;
            return;
          }
          
          let html = `
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="bg-slate-100 text-slate-600">
                  <tr>
                    <th class="px-4 py-2 text-left">Fecha</th>
                    <th class="px-4 py-2 text-left">Conductor</th>
                    <th class="px-4 py-2 text-left">Empresa</th>
                    <th class="px-4 py-2 text-right">Total</th>
                    <th class="px-4 py-2 text-right">Presupuesto</th>
                    <th class="px-4 py-2 text-right">Excedente</th>
                    <th class="px-4 py-2 text-right">%</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
          `;
          
          historial.forEach(registro => {
            const fecha = new Date(registro.fecha_excedente);
            const fechaFormateada = fecha.toLocaleDateString('es-ES');
            
            let claseFila = '';
            if (registro.porcentaje >= 150) {
              claseFila = 'bg-rose-50';
            } else if (registro.porcentaje >= 120) {
              claseFila = 'bg-amber-50';
            } else {
              claseFila = 'bg-orange-50';
            }
            
            html += `
              <tr class="${claseFila} hover:bg-opacity-80 transition">
                <td class="px-4 py-2 whitespace-nowrap">${fechaFormateada}</td>
                <td class="px-4 py-2 font-medium">${registro.conductor}</td>
                <td class="px-4 py-2">${registro.empresa}</td>
                <td class="px-4 py-2 text-right font-semibold">$${formatNumber(registro.total)}</td>
                <td class="px-4 py-2 text-right">$${formatNumber(registro.presupuesto)}</td>
                <td class="px-4 py-2 text-right font-bold text-rose-600">$${formatNumber(registro.excedente)}</td>
                <td class="px-4 py-2 text-right">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold 
                         ${registro.porcentaje >= 150 ? 'bg-rose-100 text-rose-800' : 
                           registro.porcentaje >= 120 ? 'bg-amber-100 text-amber-800' : 
                           'bg-orange-100 text-orange-800'}">
                    ${parseFloat(registro.porcentaje).toFixed(1)}%
                  </span>
                </td>
              </tr>
            `;
          });
          
          html += `
                </tbody>
              </table>
            </div>
            
            <div class="mt-4 pt-4 border-t border-slate-200">
              <div class="text-xs text-slate-500">
                <p><strong>Total registros:</strong> ${historial.length}</p>
                <p class="mt-1">Este reporte muestra el historial de cuando los conductores superaron su presupuesto por primera vez cada día.</p>
              </div>
            </div>
          `;
          
          contenido.innerHTML = html;
        })
        .catch(error => {
          console.error('Error:', error);
          contenido.innerHTML = `
            <div class="text-center py-8 text-rose-600">
              <div class="text-4xl mb-4">❌</div>
              <p class="text-lg font-semibold">Error cargando el reporte</p>
              <p class="text-slate-600">${error.message}</p>
            </div>
          `;
        });
    }
    
    function cerrarModalReporte() {
      document.getElementById('modalReporteExcedentes').classList.remove('show');
    }
    
    function exportarPresupuestos() {
      // Crear contenido CSV
      let csv = 'Conductor,Empresa,Presupuesto,Total Actual,Porcentaje,Estado\n';
      
      Object.keys(presupuestosGlobales).forEach(conductor => {
        Object.keys(presupuestosGlobales[conductor]).forEach(empresa => {
          const presupuesto = presupuestosGlobales[conductor][empresa];
          const estado = obtenerEstadoPresupuesto(conductor, empresa, presupuesto);
          
          let estadoTexto = '';
          if (estado.porcentaje >= 100) {
            estadoTexto = 'EXCEDIDO';
          } else if (estado.porcentaje >= 80) {
            estadoTexto = 'CERCANO AL LÍMITE';
          } else {
            estadoTexto = 'DENTRO DEL LÍMITE';
          }
          
          csv += `"${conductor}","${empresa}",${presupuesto},${estado.total},${estado.porcentaje.toFixed(2)}%,${estadoTexto}\n`;
        });
      });
      
      // Crear y descargar archivo
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      const url = URL.createObjectURL(blob);
      
      link.setAttribute('href', url);
      link.setAttribute('download', `presupuestos_conductores_${new Date().toISOString().slice(0,10)}.csv`);
      link.style.visibility = 'hidden';
      
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      
      mostrarNotificacion('✅ Reporte exportado', 'success');
    }
    
    // ===== SISTEMA DE SELECCIÓN DE COLUMNAS =====
    
    let columnasSeleccionadas = <?= json_encode($columnas_seleccionadas) ?>;
    
    function inicializarSeleccionColumnas() {
      // Actualizar checkboxes iniciales
      columnasSeleccionadas.forEach(columna => {
        const checkbox = document.getElementById('checkbox-' + columna);
        if (checkbox) {
          checkbox.classList.add('checked');
        }
        const item = document.querySelector('[data-columna="' + columna + '"]');
        if (item) {
          item.classList.add('selected');
        }
      });
      
      actualizarContadorColumnas();
      actualizarColumnasTabla();
    }
    
    function toggleColumna(columna) {
      const checkbox = document.getElementById('checkbox-' + columna);
      const item = document.querySelector('[data-columna="' + columna + '"]');
      
      if (columnasSeleccionadas.includes(columna)) {
        // Deseleccionar
        columnasSeleccionadas = columnasSeleccionadas.filter(c => c !== columna);
        checkbox.classList.remove('checked');
        item.classList.remove('selected');
      } else {
        // Seleccionar
        columnasSeleccionadas.push(columna);
        checkbox.classList.add('checked');
        item.classList.add('selected');
      }
      
      actualizarContadorColumnas();
      actualizarColumnasTabla();
    }
    
    function seleccionarTodasColumnas() {
      const todasColumnas = document.querySelectorAll('.columna-checkbox-item');
      columnasSeleccionadas = [];
      
      todasColumnas.forEach(item => {
        const columna = item.dataset.columna;
        columnasSeleccionadas.push(columna);
        
        const checkbox = document.getElementById('checkbox-' + columna);
        checkbox.classList.add('checked');
        item.classList.add('selected');
      });
      
      actualizarContadorColumnas();
      actualizarColumnasTabla();
    }
    
    function deseleccionarTodasColumnas() {
      const todasColumnas = document.querySelectorAll('.columna-checkbox-item');
      columnasSeleccionadas = [];
      
      todasColumnas.forEach(item => {
        const columna = item.dataset.columna;
        
        const checkbox = document.getElementById('checkbox-' + columna);
        checkbox.classList.remove('checked');
        item.classList.remove('selected');
      });
      
      actualizarContadorColumnas();
      actualizarColumnasTabla();
    }
    
    function actualizarContadorColumnas() {
      const contadorSeleccionadas = document.getElementById('contador-seleccionadas-panel');
      const contadorVisibles = document.getElementById('contador-columnas-visibles');
      const contadorHeader = document.getElementById('contador-columnas-visibles-header');
      
      if (contadorSeleccionadas) {
        contadorSeleccionadas.textContent = columnasSeleccionadas.length;
      }
      
      if (contadorVisibles) {
        contadorVisibles.textContent = columnasSeleccionadas.length;
      }
      
      if (contadorHeader) {
        contadorHeader.textContent = columnasSeleccionadas.length;
      }
    }
    
    function actualizarColumnasTabla() {
      // Ocultar/mostrar columnas en el encabezado
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
    
    // ===== FUNCIONALIDAD PARA RUTAS SIN CLASIFICAR =====
    
    function mostrarResumenRutasSinClasificar() {
      const resumenDiv = document.getElementById('resumenRutasSinClasificar');
      const listaDiv = document.getElementById('listaRutasSinClasificarGlobal');
      const contadorSpan = document.getElementById('contadorRutasSinClasificarGlobal');
      
      // Obtener conductores con rutas sin clasificar
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
                <button onclick="verViajesConductor('${conductor}')" 
                        class="text-xs text-amber-600 hover:text-amber-800 hover:underline">
                  Ver viajes
                </button>
              </div>
            </div>
          `;
        }
      });
      
      if (totalRutasSinClasificar > 0) {
        contadorSpan.textContent = totalRutasSinClasificar;
        listaDiv.innerHTML = contenidoHTML;
        resumenDiv.classList.remove('hidden');
        
        // Scroll al resumen
        resumenDiv.scrollIntoView({ behavior: 'smooth' });
      } else {
        listaDiv.innerHTML = '<div class="text-center py-4 text-amber-600">🎉 ¡Excelente! Todas las rutas están clasificadas.</div>';
        contadorSpan.textContent = '0';
        resumenDiv.classList.remove('hidden');
      }
    }
    
    function verViajesConductor(nombre) {
      const botonesConductor = document.querySelectorAll('.conductor-link');
      botonesConductor.forEach(boton => {
        if (boton.textContent.trim() === nombre.trim()) {
          boton.click();
        }
      });
      
      document.getElementById('resumenRutasSinClasificar').classList.add('hidden');
    }
    
    function irAClasificacionRutas() {
      // Abrir el panel de clasificación de rutas
      togglePanel('clasif-rutas');
      
      document.getElementById('resumenRutasSinClasificar').classList.add('hidden');
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
      recalcular();
    }

    buscadorConductores.addEventListener('input', filtrarConductores);
    clearBuscar.addEventListener('click', () => {
      buscadorConductores.value = '';
      filtrarConductores();
      buscadorConductores.focus();
    });

    // ===== FUNCIONES DE CÁLCULO CON CONTROL DE PRESUPUESTO =====
    
    function getTarifas(){
      const tarifas = {};
      document.querySelectorAll('.tarjeta-tarifa-acordeon').forEach(card=>{
        const veh = card.dataset.vehiculo;
        tarifas[veh] = {};
        
        // Obtener TODAS las tarifas, sin filtrar por columnas seleccionadas
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
      
      let totalViajes = 0;
      let totalPagado = 0;
      let totalFaltante = 0;
      let totalExcedidos = 0;
      let conductoresExcedidos = [];

      filas.forEach(fila => {
        if (fila.style.display === 'none') return;

        const veh = fila.dataset.vehiculo;
        const conductor = fila.dataset.conductor;
        const tarifasVeh = tarifas[veh] || {};
        const todasColumnas = <?= json_encode($clasificaciones_disponibles) ?>;
        const presupuesto = parseFloat(fila.dataset.presupuesto) || 0;

        let totalFila = 0;
        
        // Calcular con TODAS las columnas, no solo las visibles
        todasColumnas.forEach(columna => {
          // Obtener la cantidad desde la celda correspondiente
          const celda = fila.querySelector(`td[data-columna="${columna}"]`);
          const cantidad = parseInt(celda?.textContent || 0);
          const tarifa = tarifasVeh[columna] || 0;
          totalFila += cantidad * tarifa;
        });

        const pagado = parseInt(fila.dataset.pagado || '0') || 0;
        let faltante = totalFila - pagado;
        if (faltante < 0) faltante = 0;

        const inpTotal = fila.querySelector('input.totales');
        if (inpTotal) {
          inpTotal.value = formatNumber(totalFila);
          inpTotal.dataset.presupuesto = presupuesto;
        }

        const inpFalt = fila.querySelector('input.faltante');
        if (inpFalt) inpFalt.value = formatNumber(faltante);

        // Aplicar estilo según presupuesto
        aplicarEstiloPresupuesto(fila, totalFila, presupuesto);

        // Contar excedidos
        if (presupuesto > 0 && totalFila >= presupuesto) {
          totalExcedidos++;
          conductoresExcedidos.push(conductor);
          
          // Registrar excedente en historial (solo una vez por cálculo)
          if (presupuesto > 0) {
            const porcentaje = (totalFila / presupuesto) * 100;
            const excedente = totalFila - presupuesto;
            
            // Registrar en servidor
            if (porcentaje >= 100 && empresaFiltroActual) {
              registrarExcedenteServidor(conductor, empresaFiltroActual, totalFila, presupuesto, excedente, porcentaje);
            }
          }
        }

        totalViajes += totalFila;
        totalPagado += pagado;
        totalFaltante += faltante;
      });

      // Actualizar contadores
      document.getElementById('total_viajes').innerText = formatNumber(totalViajes);
      document.getElementById('total_general').innerText = formatNumber(totalViajes);
      document.getElementById('total_pagado').innerText = formatNumber(totalPagado);
      document.getElementById('total_faltante').innerText = formatNumber(totalFaltante);
      document.getElementById('total_excedidos').innerText = totalExcedidos;
      document.getElementById('contador-excedidos').innerText = totalExcedidos;
      
      // Actualizar contador de alertas
      const contadorAlertas = document.getElementById('contadorAlertasExcedentes');
      if (contadorAlertas) {
        contadorAlertas.textContent = `${totalExcedidos} alerta${totalExcedidos !== 1 ? 's' : ''}`;
      }
      
      // Mostrar alerta si hay excedidos
      if (totalExcedidos > 0 && !localStorage.getItem('alertaExcedentesMostrada')) {
        mostrarAlertaExcedentes(conductoresExcedidos);
      }
    }
    
    function aplicarEstiloPresupuesto(fila, total, presupuesto) {
      // Limpiar estilos anteriores
      fila.classList.remove('estado-presupuesto-verde', 'estado-presupuesto-amarillo', 'estado-presupuesto-rojo');
      
      // Si no hay presupuesto, no aplicar estilos
      if (presupuesto <= 0) return;
      
      const porcentaje = (total / presupuesto) * 100;
      
      // Aplicar estilo según porcentaje
      if (porcentaje >= 100) {
        fila.classList.add('estado-presupuesto-rojo');
        
        // Agregar badge de excedente
        const excedente = total - presupuesto;
        const celdaPresupuesto = fila.querySelector('td:nth-child(1)');
        if (celdaPresupuesto) {
          const badgeExistente = celdaPresupuesto.querySelector('.badge-excedente');
          if (!badgeExistente) {
            const badge = document.createElement('div');
            badge.className = 'badge-presupuesto bg-rose-100 text-rose-700 border border-rose-200 mt-1';
            badge.innerHTML = `⚠️ +$${formatNumber(excedente)}`;
            badge.title = `Excedente: $${formatNumber(excedente)}`;
            celdaPresupuesto.appendChild(badge);
          }
        }
      } else if (porcentaje >= 80) {
        fila.classList.add('estado-presupuesto-amarillo');
      } else {
        fila.classList.add('estado-presupuesto-verde');
      }
    }
    
    function registrarExcedenteServidor(conductor, empresa, total, presupuesto, excedente, porcentaje) {
      // Solo registrar si es significativo (>= 100%)
      if (porcentaje >= 100) {
        fetch('<?= basename(__FILE__) ?>', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            registrar_excedente: 1,
            conductor: conductor,
            empresa: empresa,
            total: total,
            presupuesto: presupuesto,
            excedente: excedente,
            porcentaje: porcentaje
          })
        })
        .then(r => r.text())
        .then(respuesta => {
          console.log('Excedente registrado:', respuesta);
        })
        .catch(error => {
          console.error('Error registrando excedente:', error);
        });
      }
    }
    
    function mostrarAlertaExcedentes(conductores) {
      if (conductores.length === 0) return;
      
      const mensaje = `⚠️ **${conductores.length} conductor(es) han excedido su presupuesto:**\n\n${conductores.map(c => `• ${c}`).join('\n')}\n\n¿Deseas ver el reporte detallado?`;
      
      if (confirm(mensaje)) {
        mostrarReporteExcedentes();
      }
      
      // Marcar como mostrada para no volver a mostrar en esta sesión
      localStorage.setItem('alertaExcedentesMostrada', 'true');
      
      // Limpiar después de 1 hora
      setTimeout(() => {
        localStorage.removeItem('alertaExcedentesMostrada');
      }, 3600000);
    }

    // ===== CREAR NUEVA CLASIFICACIÓN =====
    function crearYAsignarClasificacion() {
      const nombreClasif = document.getElementById('txt_nueva_clasificacion').value.trim();
      const patronRuta = document.getElementById('txt_patron_ruta').value.trim().toLowerCase();
      
      if (!nombreClasif) {
        alert('Escribe el nombre de la nueva clasificación.');
        return;
      }

      // 1. Crear nueva columna en tarifas (normalizada a minúsculas)
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
          
          // 2. Si hay patrón, asignar a rutas
          if (patronRuta) {
            const filas = document.querySelectorAll('.fila-ruta');
            let contador = 0;
            
            filas.forEach(row => {
              const ruta = row.dataset.ruta.toLowerCase();
              const vehiculo = row.dataset.vehiculo;
              if (ruta.includes(patronRuta)) {
                const sel = row.querySelector('.select-clasif-ruta');
                sel.value = nombreClasif.toLowerCase();
                
                // Actualizar color de la fila
                actualizarColorFila(sel);
                contador++;
              }
            });
            
            if (contador > 0) {
              alert('✅ Se creó "' + nombreClasif + '" y se aplicó a ' + contador + ' rutas. Recarga la página para ver los cambios.');
            } else {
              alert('✅ Se creó "' + nombreClasif + '". No se encontraron rutas con "' + patronRuta + '". Recarga la página.');
            }
          } else {
            alert('✅ Se creó la clasificación "' + nombreClasif + '". Recarga la página para verla en los selectores.');
          }
          
          // Limpiar campos
          document.getElementById('txt_nueva_clasificacion').value = '';
          document.getElementById('txt_patron_ruta').value = '';
          
        } else {
          alert('❌ Error: ' + respuesta);
        }
      })
      .catch(error=>{
        alert('❌ Error de conexión: ' + error);
      });
    }

    // ===== GUARDAR TARIFAS DINÁMICAMENTE =====
    function configurarEventosTarifas() {
        // Usar delegación de eventos para manejar inputs dinámicos
        document.addEventListener('change', function(e) {
            if (e.target.matches('.tarifa-input')) {
                const input = e.target;
                const card = input.closest('.tarjeta-tarifa-acordeon');
                const tipoVehiculo = card.dataset.vehiculo;
                const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
                const campo = input.dataset.campo.toLowerCase(); // NORMALIZAR A MINÚSCULAS
                const valor = parseFloat(input.value) || 0;
                
                console.log('Guardando tarifa:', { empresa, tipoVehiculo, campo, valor });
                
                // Guardar via AJAX
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
                    const respuesta = t.trim();
                    if (respuesta === 'ok') {
                        console.log('Tarifa guardada exitosamente');
                        // Guardar el valor como el nuevo default
                        input.defaultValue = input.value;
                        // Recalcular sin afectar los cálculos
                        recalcular();
                    } else {
                        console.error('Error guardando tarifa:', respuesta);
                        // Restaurar el valor anterior
                        input.value = input.defaultValue;
                    }
                })
                .catch(error => {
                    console.error('Error de conexión:', error);
                    // Restaurar el valor anterior
                    input.value = input.defaultValue;
                });
            }
        });
        
        // Configurar valor por defecto para todos los inputs
        document.querySelectorAll('.tarifa-input').forEach(input => {
            input.defaultValue = input.value;
        });
    }

    // ===== CLASIFICACIONES INDIVIDUALES =====
    function guardarClasificacionRuta(ruta, vehiculo, clasificacion) {
      if (!clasificacion) return;
      fetch('<?= basename(__FILE__) ?>', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({
          guardar_clasificacion:1,
          ruta:ruta,
          tipo_vehiculo:vehiculo,
          clasificacion:clasificacion.toLowerCase() // NORMALIZAR A MINÚSCULAS
        })
      })
      .then(r=>r.text())
      .then(t=>{
        if (t.trim() !== 'ok') console.error('Error guardando clasificación:', t);
      });
    }

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

    // Lista de conductores para el select
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

        fetch('<?= basename(__FILE__) ?>?' + qs.toString())
            .then(r => r.text())
            .then(html => {
                viajesContent.innerHTML = html;
                // Attach filter functionality after content loads
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

    // Event listeners for modal
    viajesClose.addEventListener('click', cerrarModalViajes);
    viajesModal.addEventListener('click', (e)=>{
        if(e.target===viajesModal) cerrarModalViajes();
    });

    viajesSelectConductor.addEventListener('change', ()=>{
        const nuevo = viajesSelectConductor.value;
        loadViajes(nuevo);
    });

    // ===== NOTIFICACIONES =====
    function mostrarNotificacion(mensaje, tipo) {
      // Crear elemento de notificación
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
      
      // Remover después de 3 segundos
      setTimeout(() => {
        notificacion.remove();
      }, 3000);
    }

    // ===== INICIALIZACIÓN COMPLETA ====
    document.addEventListener('DOMContentLoaded', function() {
      // Configurar eventos de tarifas
      configurarEventosTarifas();
      
      // Click en conductor → abre modal de viajes
      document.querySelectorAll('.conductor-link').forEach(btn=>{
        btn.addEventListener('click', (e)=>{
          e.preventDefault();
          const nombre = btn.textContent.trim().replace('⚠️', '').trim();
          abrirModalViajes(nombre);
        });
      });

      // Cambio clasificación ruta individual
      document.querySelectorAll('.select-clasif-ruta').forEach(sel=>{
        sel.addEventListener('change', function() {
          actualizarColorFila(this);
        });
      });

      // Recalcular al cargar
      recalcular();
      
      // Mostrar automáticamente el resumen si hay rutas sin clasificar
      const totalRutasSinClasificar = <?= array_sum(array_column($datos, 'rutas_sin_clasificar')) ?>;
      if (totalRutasSinClasificar > 0) {
        mostrarResumenRutasSinClasificar();
      }
      
      // Mostrar alerta inicial si hay excedidos
      setTimeout(() => {
        const totalExcedidos = parseInt(document.getElementById('total_excedidos').textContent);
        if (totalExcedidos > 0) {
          const conductoresExcedidos = [];
          document.querySelectorAll('#tabla_conductores_body tr.estado-presupuesto-rojo').forEach(fila => {
            const conductor = fila.dataset.conductor;
            conductoresExcedidos.push(conductor);
          });
          
          if (conductoresExcedidos.length > 0 && !localStorage.getItem('alertaInicialMostrada')) {
            mostrarAlertaExcedentes(conductoresExcedidos);
            localStorage.setItem('alertaInicialMostrada', 'true');
          }
        }
      }, 1000);
    });
  </script>
</body>
</html>
<?php
$conn->close();
?>