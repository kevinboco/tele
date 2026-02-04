<?php
// =======================================================
// 🔹 CONFIGURACIÓN INICIAL Y SESIÓN
// =======================================================
session_start();

include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

/* =======================================================
   🔹 CONFIGURACIÓN DE COLUMNAS VISIBLES DE CLASIFICACIONES
======================================================= */

// Función para obtener columnas de tarifas dinámicamente
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

// Inicializar configuración de columnas visibles en sesión
if (!isset($_SESSION['columnas_clasificaciones_visibles'])) {
    $_SESSION['columnas_clasificaciones_visibles'] = [];
    
    // Obtener todas las columnas de tarifas
    $columnas_tarifas = obtenerColumnasTarifas($conn);
    
    // Marcar todas como visibles por defecto
    foreach ($columnas_tarifas as $columna) {
        $_SESSION['columnas_clasificaciones_visibles'][$columna] = true;
    }
}

// Procesar cambios en las columnas visibles (POST)
if (isset($_POST['actualizar_columnas_clasificaciones'])) {
    // Recibir las columnas seleccionadas
    $columnas_seleccionadas = $_POST['columnas_clasificaciones'] ?? [];
    
    // Obtener todas las columnas de tarifas
    $columnas_tarifas = obtenerColumnasTarifas($conn);
    
    // Actualizar el estado de cada columna
    foreach ($columnas_tarifas as $columna) {
        $_SESSION['columnas_clasificaciones_visibles'][$columna] = in_array($columna, $columnas_seleccionadas);
    }
    
    // Redirigir para evitar reenvío del formulario
    echo "ok";
    exit();
}

// Restablecer todas las columnas
if (isset($_POST['restablecer_columnas_clasificaciones'])) {
    $columnas_tarifas = obtenerColumnasTarifas($conn);
    foreach ($columnas_tarifas as $columna) {
        $_SESSION['columnas_clasificaciones_visibles'][$columna] = true;
    }
    echo "ok";
    exit();
}

/* =======================================================
   🔹 FUNCIONES DINÁMICAS
======================================================= */

// Obtener clasificaciones disponibles (solo de tarifas)
function obtenerClasificacionesDisponibles($conn) {
    return obtenerColumnasTarifas($conn);
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
   🔹 NUEVA FUNCIÓN: Colores únicos por tipo de vehículo
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
            if ($counts[$k] > 0) { // Solo mostrar si hay viajes
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

// Filtrar clasificaciones según configuración de columnas visibles
$clasificaciones_mostrar = [];
foreach ($clasificaciones_disponibles as $clasif) {
    if ($_SESSION['columnas_clasificaciones_visibles'][$clasif] ?? true) {
        $clasificaciones_mostrar[] = $clasif;
    }
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
$sql .= " ORDER BY fecha DESC, id DESC";

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

// Inyectar pago acumulado
foreach ($datos as $conductor => $info) {
    $datos[$conductor]["pagado"] = (int)($pagosConductor[$conductor] ?? 0);
    // Inyectar contador de rutas sin clasificar
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
  
  /* ===== BOLITAS FLOTANTES ===== */
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
  
  /* Colores específicos para cada bolita */
  .ball-tarifas {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
  }
  
  .ball-crear-clasif {
    background: linear-gradient(135deg, #10b981, #059669);
  }
  
  .ball-clasif-rutas {
    background: linear-gradient(135deg, #f59e0b, #d97706);
  }
  
  /* ===== NUEVA BOLITA: Configurar columnas ===== */
  .ball-config-columnas {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
  }
  
  /* ===== PANELES DESLIZANTES ===== */
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
  
  /* ===== TABLA CENTRAL CON ANIMACIÓN ===== */
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

  /* ===== MODAL VIAJES ===== */
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
  
  /* ===== ACORDEÓN PARA TARIFAS ===== */
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
  
  /* ===== COLORES PARA FILAS DE CLASIFICACIÓN DE RUTAS ===== */
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
  
  /* Estilos para checkboxes de columnas */
  .checkbox-columna:checked {
    background-color: #8b5cf6;
    border-color: #8b5cf6;
  }
  
  /* Indicador visual para columnas ocultas */
  .columna-oculta {
    opacity: 0.5;
    filter: grayscale(0.7);
  }
  
  /* Animación para cambios en columnas */
  @keyframes fadeColumn {
    0% { opacity: 0; transform: translateX(-10px); }
    100% { opacity: 1; transform: translateX(0); }
  }
  
  .fade-column-in {
    animation: fadeColumn 0.3s ease-out;
  }
  
  /* Notificación flotante */
  #notificacion-flotante {
    animation: fadeInDown 0.3s ease-out;
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

  <!-- ===== BOLITAS FLOTANTES ===== -->
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
    
    <!-- ===== NUEVA BOLITA 4: Configurar columnas de clasificación ===== -->
    <div class="floating-ball ball-config-columnas" id="ball-config-columnas" data-panel="config-columnas">
      <div class="ball-content">📊</div>
      <div class="ball-tooltip">Configurar columnas visibles</div>
    </div>
  </div>

  <!-- ===== OVERLAY PARA PANELES ===== -->
  <div class="side-panel-overlay" id="sidePanelOverlay"></div>

  <!-- ===== PANEL DE TARIFAS CON ACORDEÓN Y COLORES ===== -->
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

  <!-- ===== PANEL CLASIFICACIÓN RUTAS CON COLORES Y SCROLL ===== -->
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

  <!-- ===== PANEL CONFIGURAR COLUMNAS VISIBLES ===== -->
  <div class="side-panel" id="panel-config-columnas">
    <div class="side-panel-header">
      <h3 class="text-lg font-semibold flex items-center gap-2">
        <span>📊 Configurar Columnas Visibles</span>
        <span class="text-xs text-slate-500">Clasificaciones</span>
      </h3>
      <button class="side-panel-close" data-panel="config-columnas">✕</button>
    </div>
    <div class="side-panel-body">
      <div class="mb-4">
        <p class="text-sm text-slate-600 mb-3">
          Selecciona qué clasificaciones mostrar en la tabla principal. 
          Las columnas ocultas no se mostrarán en los cálculos.
        </p>
        
        <!-- Contador de columnas seleccionadas -->
        <div class="flex items-center justify-between mb-4 p-3 bg-blue-50 rounded-xl">
          <span class="text-sm font-medium text-blue-700">Columnas seleccionadas:</span>
          <span id="contadorColumnasSeleccionadas" class="px-3 py-1 bg-blue-600 text-white text-sm font-bold rounded-full"><?= count($clasificaciones_mostrar) ?></span>
        </div>
      </div>
      
      <!-- Lista de columnas con checkboxes -->
      <div id="listaColumnasClasificaciones" class="space-y-2 max-h-[calc(100vh-300px)] overflow-y-auto border border-slate-200 rounded-xl p-3">
        <?php 
        $seleccionadas = 0;
        foreach ($clasificaciones_disponibles as $clasif): 
          $estaVisible = $_SESSION['columnas_clasificaciones_visibles'][$clasif] ?? true;
          if ($estaVisible) $seleccionadas++;
          
          $estilo = obtenerEstiloClasificacion($clasif);
          
          // Definir etiqueta legible
          $etiquetas = [
              'completo' => 'Viaje Completo',
              'medio' => 'Viaje Medio', 
              'extra' => 'Viaje Extra',
              'carrotanque' => 'Carrotanque',
              'siapana' => 'Siapana',
              'riohacha' => 'Riohacha',
              'pru' => 'PRU',
              'maco' => 'MACO'
          ];
          
          $etiqueta = $etiquetas[$clasif] ?? ucfirst($clasif);
        ?>
        <div class="flex items-center justify-between p-3 rounded-lg border <?= $estilo['border'] ?> hover:bg-slate-50 transition-colors" 
             style="background-color: <?= str_replace('bg-', '#', $estilo['bg']) ?>20;">
          <div class="flex items-center gap-3">
            <input type="checkbox" 
                   id="col_<?= htmlspecialchars($clasif) ?>" 
                   value="<?= htmlspecialchars($clasif) ?>"
                   class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 checkbox-columna"
                   <?= $estaVisible ? 'checked' : '' ?>
                   onchange="actualizarContadorColumnas()">
            <label for="col_<?= htmlspecialchars($clasif) ?>" class="flex items-center gap-2 cursor-pointer">
              <span class="w-3 h-3 rounded-full" style="background-color: <?= str_replace('text-', '#', $estilo['text']) ?>"></span>
              <span class="font-medium <?= $estilo['text'] ?>"><?= htmlspecialchars($etiqueta) ?></span>
              <span class="text-xs text-slate-500">(<?= htmlspecialchars($clasif) ?>)</span>
            </label>
          </div>
          <span class="text-xs px-2 py-1 rounded <?= $estilo['bg'] ?> <?= $estilo['text'] ?> border <?= $estilo['border'] ?>">
            <?= strtoupper(substr($clasif, 0, 3)) ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
      
      <!-- Botones de acción -->
      <div class="mt-6 space-y-3">
        <button onclick="aplicarConfiguracionColumnas()" 
                class="w-full inline-flex items-center justify-center rounded-xl bg-blue-600 text-white px-4 py-3 text-sm font-semibold hover:bg-blue-700 active:bg-blue-800 focus:ring-4 focus:ring-blue-200 transition">
          ✅ Aplicar cambios
        </button>
        
        <button onclick="restablecerColumnasClasificaciones()" 
                class="w-full inline-flex items-center justify-center rounded-xl bg-slate-200 text-slate-700 px-4 py-3 text-sm font-semibold hover:bg-slate-300 active:bg-slate-400 focus:ring-4 focus:ring-slate-100 transition">
          🔄 Restablecer todas
        </button>
      </div>
      
      <p class="text-xs text-slate-500 mt-4">
        Los cambios se aplicarán inmediatamente a la tabla. 
        Las columnas ocultas no afectarán los cálculos totales.
      </p>
    </div>
  </div>

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
        <span class="font-medium">Columnas:</span>
        <span class="bg-slate-100 px-2 py-1 rounded-lg font-semibold">
          <?= count($clasificaciones_mostrar) ?> de <?= count($clasificaciones_disponibles) ?>
        </span>
      </div>
    </div>
  </header>

  <!-- Contenido principal -->
  <main class="max-w-[1800px] mx-auto px-3 md:px-4 py-6">
    <div class="table-container-wrapper" id="tableContainerWrapper">
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <!-- Encabezado del panel central -->
        <div class="p-5 border-b border-slate-200">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <h3 class="text-xl font-semibold">🧑‍✈️ Resumen por Conductor</h3>
              <div id="contador-conductores" class="text-sm text-slate-500 mt-1">
                Mostrando <?= count($datos) ?> conductores
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
                  <!-- NUEVA COLUMNA PARA ALERTAS VISUALES -->
                  <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 70px;">
                    Estado
                  </th>
                  <th class="px-4 py-3 text-left sticky top-0 bg-blue-600" style="min-width: 220px;">
                    Conductor
                  </th>
                  <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 120px;">
                    Tipo
                  </th>
                  
                  <?php foreach ($clasificaciones_mostrar as $index => $clasif): 
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
                    $border_color = $colorMap[$estilo['border']] ?? '#cbd5e1';
                  ?>
                  <th class="px-4 py-3 text-center sticky top-0 fade-column-in" 
                      title="<?= htmlspecialchars($clasif) ?>"
                      style="min-width: 80px; background-color: <?= $bg_color ?>; color: <?= $text_color ?>; border-bottom: 2px solid <?= $border_color ?>; z-index: 19;">
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
                $color_vehiculo = obtenerColorVehiculo($info['vehiculo']);
              ?>
                <tr data-vehiculo="<?= htmlspecialchars($info['vehiculo']) ?>" 
                    data-conductor="<?= htmlspecialchars($conductor) ?>" 
                    data-conductor-normalizado="<?= htmlspecialchars(mb_strtolower($conductor)) ?>"
                    data-pagado="<?= (int)($info['pagado'] ?? 0) ?>"
                    data-sin-clasificar="<?= $rutasSinClasificar ?>"
                    class="hover:bg-blue-50/40 transition-colors <?php echo $rutasSinClasificar > 0 ? 'alerta-sin-clasificar' : ''; ?>">
                  <!-- NUEVA CELDA: Indicador visual de rutas sin clasificar -->
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
                  
                  <?php foreach ($clasificaciones_mostrar as $clasif): 
                    $estilo = obtenerEstiloClasificacion($clasif);
                    $cantidad = (int)($info[$clasif] ?? 0);
                    
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
                    $border_cell_color = str_replace('bg-', '#', $estilo['bg']) . '30';
                  ?>
                  <td class="px-4 py-3 text-center font-medium fade-column-in" 
                      style="min-width: 80px; background-color: <?= $bg_cell_color ?>; color: <?= $text_cell_color ?>; border-left: 1px solid <?= $border_cell_color ?>; border-right: 1px solid <?= $border_cell_color ?>;">
                    <?= $cantidad ?>
                  </td>
                  <?php endforeach; ?>

                  <!-- Total -->
                  <td class="px-4 py-3" style="min-width: 140px;">
                    <input type="text"
                           class="totales w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-slate-50 outline-none whitespace-nowrap tabular-nums"
                           readonly dir="ltr">
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
    const panels = ['tarifas', 'crear-clasif', 'clasif-rutas', 'config-columnas'];
    
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
      
      // Inicializar acordeón de tarifas (todos colapsados inicialmente)
      colapsarTodosTarifas();
      
      // Inicializar colores de las filas de clasificación
      inicializarColoresClasificacion();
      
      // Inicializar contador de columnas
      actualizarContadorColumnas();
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
    
    // ===== FUNCIONES PARA CONFIGURAR COLUMNAS VISIBLES =====
    
    // Actualizar contador de columnas seleccionadas
    function actualizarContadorColumnas() {
      const checkboxes = document.querySelectorAll('#listaColumnasClasificaciones input[type="checkbox"]:checked');
      document.getElementById('contadorColumnasSeleccionadas').textContent = checkboxes.length;
    }
    
    // Aplicar configuración de columnas
    function aplicarConfiguracionColumnas() {
      const checkboxes = document.querySelectorAll('#listaColumnasClasificaciones input[type="checkbox"]');
      const columnasSeleccionadas = [];
      
      checkboxes.forEach(checkbox => {
        if (checkbox.checked) {
          columnasSeleccionadas.push(checkbox.value);
        }
      });
      
      // Enviar configuración al servidor
      fetch('<?= basename(__FILE__) ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          actualizar_columnas_clasificaciones: 1,
          columnas_clasificaciones: columnasSeleccionadas
        })
      })
      .then(r => r.text())
      .then(respuesta => {
        if (respuesta.trim() === 'ok') {
          // Recargar la página para aplicar cambios
          location.reload();
        } else {
          mostrarNotificacion('❌ Error al guardar configuración', 'error');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('❌ Error de conexión', 'error');
      });
    }
    
    // Restablecer todas las columnas
    function restablecerColumnasClasificaciones() {
      if (!confirm('¿Restablecer todas las columnas a visibles?')) return;
      
      fetch('<?= basename(__FILE__) ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          restablecer_columnas_clasificaciones: 1
        })
      })
      .then(r => r.text())
      .then(respuesta => {
        if (respuesta.trim() === 'ok') {
          // Recargar la página para aplicar cambios
          location.reload();
        } else {
          mostrarNotificacion('❌ Error al restablecer', 'error');
        }
      });
    }
    
    // ===== FUNCIONALIDAD PARA RUTAS SIN CLASIFICAR =====
    
    // Mostrar resumen de rutas sin clasificar
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
          const conductor = fila.querySelector('.conductor-link').textContent.replace('⚠️', '').trim();
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
    
    // Ver viajes de un conductor específico
    function verViajesConductor(nombre) {
      // Encontrar el botón del conductor y hacer clic
      const botonesConductor = document.querySelectorAll('.conductor-link');
      botonesConductor.forEach(boton => {
        if (boton.textContent.trim().replace('⚠️', '').trim() === nombre.trim()) {
          boton.click();
        }
      });
      
      // Cerrar el resumen
      document.getElementById('resumenRutasSinClasificar').classList.add('hidden');
    }
    
    // Ir a la sección de clasificación de rutas
    function irAClasificacionRutas() {
      // Abrir el panel de clasificación de rutas
      togglePanel('clasif-rutas');
      
      // Cerrar el resumen
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
          const nombreConductor = fila.querySelector('.conductor-link').textContent.replace('⚠️', '').trim();
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

    // ===== FUNCIONES DE CÁLCULO =====
    function getTarifas(){
      const tarifas = {};
      document.querySelectorAll('.tarjeta-tarifa-acordeon').forEach(card=>{
        const veh = card.dataset.vehiculo;
        tarifas[veh] = {};
        
        // Obtener todas las columnas dinámicas
        card.querySelectorAll('input[data-campo]').forEach(input=>{
          const campo = input.dataset.campo.toLowerCase(); // IMPORTANTE: Normalizar a minúsculas
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
      
      // Obtener clasificaciones desde los encabezados (en minúsculas)
      const clasificaciones = [];
      document.querySelectorAll('#tabla_conductores thead th[title]').forEach(th => {
        clasificaciones.push(th.getAttribute('title').toLowerCase());
      });

      let totalViajes = 0;
      let totalPagado = 0;
      let totalFaltante = 0;

      filas.forEach(fila => {
        if (fila.style.display === 'none') return;

        const veh = fila.dataset.vehiculo;
        const celdas = fila.querySelectorAll('td');
        const tarifasVeh = tarifas[veh] || {};

        let totalFila = 0;
        let columnaIndex = 3; // Empieza después de estado, conductor y tipo
        
        // Calcular por cada clasificación
        clasificaciones.forEach(clasif => {
          const cantidad = parseInt(celdas[columnaIndex]?.textContent || 0);
          const tarifa = tarifasVeh[clasif] || 0;
          totalFila += cantidad * tarifa;
          columnaIndex++;
        });

        const pagado = parseInt(fila.dataset.pagado || '0') || 0;
        let faltante = totalFila - pagado;
        if (faltante < 0) faltante = 0;

        const inpTotal = fila.querySelector('input.totales');
        if (inpTotal) inpTotal.value = formatNumber(totalFila);

        const inpFalt = fila.querySelector('input.faltante');
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
                setTimeout(attachFiltroViajes, 100);
            })
            .catch(() => {
                viajesContent.innerHTML = '<p class="text-center text-rose-600">Error cargando viajes.</p>';
            });
    }

    function attachFiltroViajes(){
        const pills = viajesContent.querySelectorAll('#legendFilterBar .legend-pill');
        const rows  = viajesContent.querySelectorAll('#viajesTableBody .row-viaje');
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

    // ===== FUNCIÓN PARA MOSTRAR NOTIFICACIONES =====
    function mostrarNotificacion(mensaje, tipo = 'info') {
      // Eliminar notificación anterior si existe
      const notifAnterior = document.getElementById('notificacion-flotante');
      if (notifAnterior) notifAnterior.remove();
      
      const colores = {
        'success': 'bg-emerald-500 border-emerald-600',
        'error': 'bg-rose-500 border-rose-600',
        'info': 'bg-blue-500 border-blue-600'
      };
      
      const notificacion = document.createElement('div');
      notificacion.id = 'notificacion-flotante';
      notificacion.className = `fixed top-4 right-4 ${colores[tipo]} text-white px-4 py-3 rounded-xl shadow-lg z-[10000] animate-fade-in-down`;
      notificacion.textContent = mensaje;
      
      document.body.appendChild(notificacion);
      
      // Auto-eliminar después de 3 segundos
      setTimeout(() => {
        if (notificacion.parentNode) {
          notificacion.classList.add('opacity-0', 'transition-opacity', 'duration-300');
          setTimeout(() => {
            if (notificacion.parentNode) notificacion.remove();
          }, 300);
        }
      }, 3000);
    }

    // ===== INICIALIZACIÓN =====
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
    });
  </script>
</body>
</html>
<?php
$conn->close();
?>