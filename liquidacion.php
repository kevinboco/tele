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
   🔹 NUEVAS FUNCIONES PARA PRESUPUESTO SIMPLIFICADO
======================================================= */

// Crear tabla de presupuesto si no existe
function crearTablaPresupuesto($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS conductor_presupuesto (
        id INT PRIMARY KEY AUTO_INCREMENT,
        conductor VARCHAR(100) NOT NULL,
        empresa VARCHAR(100) NOT NULL,
        presupuesto DECIMAL(12,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY idx_conductor_empresa (conductor, empresa)
    )";
    return $conn->query($sql);
}

// Obtener presupuesto de un conductor para una empresa específica
function obtenerPresupuestoConductor($conn, $conductor, $empresa) {
    crearTablaPresupuesto($conn); // Asegurar que la tabla existe
    
    $sql = "SELECT presupuesto FROM conductor_presupuesto 
            WHERE conductor = ? AND empresa = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $conductor, $empresa);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return (float)$row['presupuesto'];
    }
    return 0.00;
}

// Guardar o actualizar presupuesto
function guardarPresupuestoConductor($conn, $conductor, $empresa, $presupuesto) {
    crearTablaPresupuesto($conn); // Asegurar que la tabla existe
    
    $sql = "INSERT INTO conductor_presupuesto (conductor, empresa, presupuesto)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE presupuesto = VALUES(presupuesto)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssd", $conductor, $empresa, $presupuesto);
    return $stmt->execute();
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

    $campo = strtolower($campo);
    $campo = preg_replace('/[^a-z0-9_]/', '_', $campo);

    // Validar que el campo exista en la tabla tarifas
    $columnas_tarifas = obtenerColumnasTarifas($conn);
    
    if (!in_array($campo, $columnas_tarifas)) { 
        if (crearNuevaColumnaTarifa($conn, $campo)) {
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
   🔹 Guardar PRESUPUESTO simplificado (AJAX)
======================================================= */
if (isset($_POST['guardar_presupuesto_simple'])) {
    $conductor = $conn->real_escape_string($_POST['conductor']);
    $empresa = $_GET['empresa'] ?? ""; // Usar la empresa del filtro actual
    $presupuesto = (float)$_POST['presupuesto'];
    
    if ($empresa === "") {
        echo "error: Selecciona una empresa primero";
        exit;
    }
    
    if (guardarPresupuestoConductor($conn, $conductor, $empresa, $presupuesto)) {
        echo "ok";
    } else {
        echo "error: " . $conn->error;
    }
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

        // Generar HTML
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
   🔹 Cálculo y armado de tablas
======================================================= */
$desde = $_GET['desde'];
$hasta = $_GET['hasta'];
$empresaFiltro = $_GET['empresa'] ?? "";

// Obtener datos dinámicos
$columnas_tarifas = obtenerColumnasTarifas($conn);
$clasificaciones_disponibles = obtenerClasificacionesDisponibles($conn);

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

        // Inicializar datos del conductor
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

        // Si tiene clasificación, sumar al contador
        if ($clasifRuta !== '') {
            if (!isset($datos[$nombre][$clasifRuta])) {
                $datos[$nombre][$clasifRuta] = 0;
            }
            $datos[$nombre][$clasifRuta]++;
        }
    }
}

// Cargar presupuestos para cada conductor
foreach ($datos as $conductor => $info) {
    $presupuesto = 0;
    if ($empresaFiltro !== "") {
        $presupuesto = obtenerPresupuestoConductor($conn, $conductor, $empresaFiltro);
    }
    $datos[$conductor]["presupuesto"] = $presupuesto;
    $datos[$conductor]["pagado"] = (int)($pagosConductor[$conductor] ?? 0);
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
  /* Estilos para el estado del presupuesto */
  .presupuesto-verde {
    background-color: #dcfce7 !important;
  }
  
  .presupuesto-amarillo {
    background-color: #fef9c3 !important;
    animation: pulse-amarillo 2s infinite;
  }
  
  @keyframes pulse-amarillo {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
  }
  
  .presupuesto-rojo {
    background-color: #fee2e2 !important;
    animation: pulse-rojo 1.5s infinite;
  }
  
  @keyframes pulse-rojo {
    0%, 100% { 
      background-color: #fee2e2;
      box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
    }
    50% { 
      background-color: #fecaca;
      box-shadow: 0 0 0 4px rgba(239, 68, 68, 0);
    }
  }
  
  /* Estilo para el input de presupuesto */
  .presupuesto-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  }
  
  /* Estilos para el porcentaje */
  .porcentaje-bajo {
    color: #059669;
    background-color: #d1fae5;
  }
  
  .porcentaje-medio {
    color: #d97706;
    background-color: #fef3c7;
  }
  
  .porcentaje-alto {
    color: #dc2626;
    background-color: #fee2e2;
    font-weight: bold;
    animation: pulse-text 1s infinite;
  }
  
  @keyframes pulse-text {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
  }
  
  /* Buscador */
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
  
  /* Estilos existentes */
  ::-webkit-scrollbar{height:10px;width:10px}
  ::-webkit-slider-thumb{background:#d1d5db;border-radius:999px}
  ::-webkit-slider-thumb:hover{background:#9ca3af}
  input[type=number]::-webkit-inner-spin-button,
  input[type=number]::-webkit-outer-spin-button{ -webkit-appearance: none; margin: 0; }
</style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800">

  <!-- Encabezado -->
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
      </div>
    </div>
  </header>

  <!-- Contenido principal -->
  <main class="max-w-[1800px] mx-auto px-3 md:px-4 py-6">
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

        <!-- CONTENEDOR DE TABLA -->
        <div id="tableContainer" class="overflow-x-auto rounded-xl border border-slate-200 max-h-[70vh]">
          <table id="tabla_conductores" class="w-full text-sm">
            <thead class="bg-blue-600 text-white sticky top-0 z-20">
              <tr>
                <th class="px-4 py-3 text-left sticky top-0 bg-blue-600" style="min-width: 220px;">
                  Conductor
                </th>
                <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 120px;">
                  Tipo
                </th>
                <!-- NUEVA COLUMNA: Presupuesto -->
                <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 120px;">
                  Presupuesto
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
                ?>
                <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" 
                    style="min-width: 80px;"
                    title="<?= htmlspecialchars($clasif) ?>">
                  <?= htmlspecialchars($abreviatura) ?>
                </th>
                <?php endforeach; ?>
                
                <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 140px;">
                  Total
                </th>
                <!-- NUEVA COLUMNA: % Usado -->
                <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 80px;">
                  % Usado
                </th>
                <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 120px;">
                  Pagado
                </th>
                <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 100px;">
                  Faltante
                </th>
              </tr>
            </thead>
            <tbody id="tabla_conductores_body" class="divide-y divide-slate-100 bg-white">
            <?php foreach ($datos as $conductor => $info): 
              $presupuesto = $info['presupuesto'] ?? 0;
              $color_vehiculo = obtenerColorVehiculo($info['vehiculo']);
            ?>
              <tr data-conductor="<?= htmlspecialchars($conductor) ?>" 
                  data-presupuesto="<?= $presupuesto ?>"
                  class="hover:bg-blue-50/40 transition-colors">
                
                <!-- Celda de Conductor -->
                <td class="px-4 py-3" style="min-width: 220px;">
                  <button type="button"
                          class="conductor-link text-blue-700 hover:text-blue-900 underline underline-offset-2 transition"
                          title="Ver viajes">
                    <?= htmlspecialchars($conductor) ?>
                  </button>
                </td>
                
                <!-- Celda de Tipo de Vehículo -->
                <td class="px-4 py-3 text-center" style="min-width: 120px;">
                  <span class="inline-block px-3 py-1.5 rounded-lg text-xs font-medium border <?= $color_vehiculo['border'] ?> <?= $color_vehiculo['text'] ?> <?= $color_vehiculo['bg'] ?>">
                    <?= htmlspecialchars($info['vehiculo']) ?>
                  </span>
                </td>
                
                <!-- NUEVA CELDA: Presupuesto (EDITABLE) -->
                <td class="px-4 py-3 text-center" style="min-width: 120px;">
                  <div class="relative">
                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-500">$</span>
                    <input type="number" 
                           step="1000"
                           value="<?= $presupuesto > 0 ? $presupuesto : '' ?>"
                           data-conductor="<?= htmlspecialchars($conductor) ?>"
                           class="presupuesto-input w-full rounded-lg border border-slate-300 px-8 py-2 text-right text-sm outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500 transition"
                           placeholder="0"
                           onchange="guardarPresupuesto(this)">
                  </div>
                  <?php if ($presupuesto > 0): ?>
                    <div class="text-[10px] text-slate-500 mt-1">
                      Presupuesto asignado
                    </div>
                  <?php endif; ?>
                </td>
                
                <!-- Columnas de clasificaciones -->
                <?php foreach ($clasificaciones_disponibles as $clasif): 
                  $estilo = obtenerEstiloClasificacion($clasif);
                  $cantidad = (int)($info[$clasif] ?? 0);
                ?>
                <td class="px-4 py-3 text-center font-medium" 
                    style="min-width: 80px; background-color: <?= str_replace('bg-', '#', $estilo['bg']) ?>10; color: <?= str_replace('text-', '#', $estilo['text']) ?>;">
                  <?= $cantidad ?>
                </td>
                <?php endforeach; ?>

                <!-- Total -->
                <td class="px-4 py-3" style="min-width: 140px;">
                  <input type="text"
                         class="totales w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-slate-50 outline-none whitespace-nowrap tabular-nums"
                         readonly dir="ltr">
                </td>

                <!-- NUEVA CELDA: % Usado -->
                <td class="px-4 py-3 text-center" style="min-width: 80px;">
                  <div class="porcentaje-usado flex flex-col items-center justify-center">
                    <span class="text-sm font-bold">0%</span>
                  </div>
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
  </main>

  <!-- ===== Modal VIAJES ===== -->
  <div id="viajesModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden">
      <div class="p-6 border-b border-slate-200">
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
            <button class="text-slate-600 hover:bg-slate-100 border border-slate-300 px-2 py-1 rounded-lg text-sm" id="viajesCloseBtn" title="Cerrar">
              ✕
            </button>
          </div>
        </div>
      </div>
      <div class="p-6 overflow-auto max-h-[70vh]" id="viajesContent"></div>
    </div>
  </div>

  <script>
    // ===== VARIABLES GLOBALES =====
    const RANGO_DESDE = "<?= htmlspecialchars($desde) ?>";
    const RANGO_HASTA = "<?= htmlspecialchars($hasta) ?>";
    const RANGO_EMP = "<?= htmlspecialchars($empresaFiltro) ?>";
    const EMPRESA_ACTUAL = "<?= htmlspecialchars($empresaFiltro) ?>";

    // ===== FUNCIONES DE PRESUPUESTO SIMPLIFICADO =====
    
    function guardarPresupuesto(input) {
      const conductor = input.dataset.conductor;
      let presupuesto = parseFloat(input.value) || 0;
      
      if (presupuesto < 0) {
        presupuesto = 0;
        input.value = '';
      }
      
      // Validar que haya empresa seleccionada
      if (!EMPRESA_ACTUAL) {
        alert('⚠️ Primero selecciona una empresa en el filtro');
        input.value = '';
        return;
      }
      
      // Guardar en el servidor
      fetch('<?= basename(__FILE__) ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          guardar_presupuesto_simple: 1,
          conductor: conductor,
          presupuesto: presupuesto
        })
      })
      .then(r => r.text())
      .then(respuesta => {
        if (respuesta.trim() === 'ok') {
          // Actualizar el atributo data-presupuesto de la fila
          const fila = input.closest('tr');
          fila.dataset.presupuesto = presupuesto;
          
          // Mostrar mensaje si se asignó presupuesto
          if (presupuesto > 0) {
            mostrarNotificacion(`✅ Presupuesto asignado a ${conductor}: $${formatNumber(presupuesto)}`, 'success');
          } else {
            mostrarNotificacion(`✅ Presupuesto eliminado de ${conductor}`, 'success');
          }
          
          // Recalcular para actualizar porcentajes
          recalcular();
        } else {
          alert('❌ Error: ' + respuesta);
          // Restaurar valor anterior
          const fila = input.closest('tr');
          input.value = fila.dataset.presupuesto > 0 ? fila.dataset.presupuesto : '';
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('❌ Error de conexión');
        const fila = input.closest('tr');
        input.value = fila.dataset.presupuesto > 0 ? fila.dataset.presupuesto : '';
      });
    }
    
    function formatNumber(num) {
      return new Intl.NumberFormat('es-CO').format(num || 0);
    }
    
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

    // ===== CÁLCULOS Y RECALCULAR =====
    
    function getTarifas() {
      // Esta función debería obtener las tarifas de algún lugar
      // Por ahora retornamos un objeto vacío
      return {};
    }
    
    function recalcular() {
      const tarifas = getTarifas();
      const filas = document.querySelectorAll('#tabla_conductores_body tr');
      
      let totalViajes = 0;
      let totalPagado = 0;
      let totalFaltante = 0;
      let totalExcedidos = 0;
      
      filas.forEach(fila => {
        if (fila.style.display === 'none') return;
        
        const conductor = fila.dataset.conductor;
        const presupuesto = parseFloat(fila.dataset.presupuesto) || 0;
        
        // Obtener total de la fila (simulado por ahora)
        // En un sistema real, esto se calcularía con las tarifas
        let totalFila = 0;
        
        // Simular cálculo de total
        const columnasClasif = fila.querySelectorAll('td:nth-child(n+4):not(:last-child):not(:nth-last-child(2)):not(:nth-last-child(3))');
        columnasClasif.forEach(td => {
          const cantidad = parseInt(td.textContent) || 0;
          // Aquí se multiplicaría por la tarifa correspondiente
          totalFila += cantidad * 10000; // Ejemplo: $10,000 por viaje
        });
        
        const pagado = parseInt(fila.querySelector('.pagado').value.replace(/[^0-9]/g, '')) || 0;
        let faltante = totalFila - pagado;
        if (faltante < 0) faltante = 0;
        
        // Actualizar campos
        const inpTotal = fila.querySelector('input.totales');
        if (inpTotal) {
          inpTotal.value = formatNumber(totalFila);
        }
        
        const inpFalt = fila.querySelector('input.faltante');
        if (inpFalt) {
          inpFalt.value = formatNumber(faltante);
        }
        
        // Calcular porcentaje usado
        let porcentaje = 0;
        if (presupuesto > 0) {
          porcentaje = (totalFila / presupuesto) * 100;
        }
        
        // Actualizar celda de porcentaje
        const celdaPorcentaje = fila.querySelector('.porcentaje-usado span');
        if (celdaPorcentaje) {
          celdaPorcentaje.textContent = porcentaje > 0 ? Math.round(porcentaje) + '%' : '0%';
          
          // Aplicar clase según porcentaje
          celdaPorcentaje.className = 'text-sm font-bold ';
          if (porcentaje >= 100) {
            celdaPorcentaje.className += 'porcentaje-alto';
            fila.classList.add('presupuesto-rojo');
            fila.classList.remove('presupuesto-amarillo', 'presupuesto-verde');
            totalExcedidos++;
          } else if (porcentaje >= 80) {
            celdaPorcentaje.className += 'porcentaje-medio';
            fila.classList.add('presupuesto-amarillo');
            fila.classList.remove('presupuesto-rojo', 'presupuesto-verde');
          } else if (porcentaje > 0) {
            celdaPorcentaje.className += 'porcentaje-bajo';
            fila.classList.add('presupuesto-verde');
            fila.classList.remove('presupuesto-rojo', 'presupuesto-amarillo');
          } else {
            fila.classList.remove('presupuesto-rojo', 'presupuesto-amarillo', 'presupuesto-verde');
          }
        }
        
        // Mostrar alerta si se excede por primera vez
        if (porcentaje >= 100 && !fila.dataset.alertaMostrada) {
          mostrarAlertaExcedido(conductor, totalFila, presupuesto, porcentaje);
          fila.dataset.alertaMostrada = 'true';
        }
        
        totalViajes += totalFila;
        totalPagado += pagado;
        totalFaltante += faltante;
      });
      
      // Actualizar contadores
      document.getElementById('total_viajes').textContent = formatNumber(totalViajes);
      document.getElementById('total_general').textContent = formatNumber(totalViajes);
      document.getElementById('total_pagado').textContent = formatNumber(totalPagado);
      document.getElementById('total_faltante').textContent = formatNumber(totalFaltante);
      document.getElementById('total_excedidos').textContent = totalExcedidos;
      document.getElementById('contador-excedidos').textContent = totalExcedidos;
    }
    
    function mostrarAlertaExcedido(conductor, total, presupuesto, porcentaje) {
      const mensaje = `⚠️ **${conductor} ha excedido su presupuesto!**\n\n` +
                     `💰 Total: $${formatNumber(total)}\n` +
                     `🎯 Presupuesto: $${formatNumber(presupuesto)}\n` +
                     `📈 % Usado: ${Math.round(porcentaje)}%\n\n` +
                     `Se seguirán registrando viajes normalmente.`;
      
      // Mostrar alerta emergente
      alert(mensaje);
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
        filas.forEach(fila => { 
          fila.style.display = ''; 
          filasVisibles++; 
        });
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

    // ===== MODAL DE VIAJES =====
    const viajesModal = document.getElementById('viajesModal');
    const viajesContent = document.getElementById('viajesContent');
    const viajesTitle = document.getElementById('viajesTitle');
    const viajesClose = document.getElementById('viajesCloseBtn');
    const viajesSelectConductor = document.getElementById('viajesSelectConductor');
    const viajesRango = document.getElementById('viajesRango');
    const viajesEmpresa = document.getElementById('viajesEmpresa');

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

        fetch('<?= basename(__FILE__) ?>?' + qs.toString())
            .then(r => r.text())
            .then(html => {
                viajesContent.innerHTML = html;
            })
            .catch(() => {
                viajesContent.innerHTML = '<p class="text-center text-rose-600">Error cargando viajes.</p>';
            });
    }

    function abrirModalViajes(nombreInicial){
        viajesRango.textContent = RANGO_DESDE + " → " + RANGO_HASTA;
        viajesEmpresa.textContent = (RANGO_EMP && RANGO_EMP !== "") ? RANGO_EMP : "Todas las empresas";

        initViajesSelect(nombreInicial);
        viajesModal.classList.remove('hidden');
        viajesModal.classList.add('flex');
        loadViajes(nombreInicial);
    }

    function cerrarModalViajes(){
        viajesModal.classList.add('hidden');
        viajesModal.classList.remove('flex');
        viajesContent.innerHTML = '';
        viajesConductorActual = null;
    }

    // Event listeners for modal
    viajesClose.addEventListener('click', cerrarModalViajes);
    viajesModal.addEventListener('click', (e)=>{
        if(e.target === viajesModal) cerrarModalViajes();
    });

    viajesSelectConductor.addEventListener('change', ()=>{
        const nuevo = viajesSelectConductor.value;
        loadViajes(nuevo);
    });

    // ===== INICIALIZACIÓN =====
    document.addEventListener('DOMContentLoaded', function() {
      // Click en conductor → abre modal de viajes
      document.querySelectorAll('.conductor-link').forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          const nombre = btn.textContent.trim();
          abrirModalViajes(nombre);
        });
      });
      
      // Recalcular al cargar
      recalcular();
      
      // Configurar inputs de presupuesto
      document.querySelectorAll('.presupuesto-input').forEach(input => {
        // Guardar valor inicial
        input.dataset.valorOriginal = input.value;
        
        // Permitir limpiar con Esc
        input.addEventListener('keydown', (e) => {
          if (e.key === 'Escape') {
            input.value = input.dataset.valorOriginal;
            input.blur();
          }
        });
      });
      
      // Mostrar alerta si no hay empresa seleccionada
      if (!EMPRESA_ACTUAL) {
        setTimeout(() => {
          alert('⚠️ Para asignar presupuestos, primero selecciona una empresa en el filtro.');
        }, 1000);
      }
    });
  </script>
</body>
</html>
<?php
$conn->close();
?>