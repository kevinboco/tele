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

        $rowsHTML = "";
        
        while ($r = $res->fetch_assoc()) {
            $ruta = (string)$r['ruta'];
            $vehiculo = $r['tipo_vehiculo'];
            
            // Determinar clasificación
            $key = mb_strtolower(trim($ruta . '|' . $vehiculo), 'UTF-8');
            $cat = $clasif_rutas[$key] ?? 'otro';
            
            // Normalizar a minúsculas
            $cat = strtolower($cat);
            
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
  .table-fixed { table-layout: fixed; }
  .col-conductor { width: 25%; }
  .col-vehiculo { width: 12%; }
  .col-clasif { width: 7%; }
  .col-total { width: 15%; }
  .col-pagado { width: 12%; }
  .col-faltante { width: 10%; }
  
  /* Estilos para las bolitas flotantes */
  .container-minimized {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }
  
  .floating-ball {
    position: fixed !important;
    z-index: 9999;
    width: 60px !important;
    height: 60px !important;
    border-radius: 50% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    cursor: move !important;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2) !important;
    transition: transform 0.2s, box-shadow 0.2s !important;
    user-select: none !important;
    overflow: hidden !important;
    border: 2px solid white !important;
  }
  
  .floating-ball:hover {
    transform: scale(1.1);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
  }
  
  .floating-ball:active {
    cursor: grabbing !important;
  }
  
  .floating-ball.minimized {
    animation: shrinkToBall 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
  }
  
  .floating-ball.restored {
    animation: expandFromBall 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
  }
  
  @keyframes shrinkToBall {
    0% {
      border-radius: 12px;
      width: var(--original-width);
      height: var(--original-height);
      left: var(--original-left);
      top: var(--original-top);
    }
    50% {
      border-radius: 30px;
      transform: scale(0.7);
    }
    100% {
      border-radius: 50%;
      width: 60px !important;
      height: 60px !important;
      transform: scale(1);
      left: var(--ball-left, 20px) !important;
      top: var(--ball-top, 20px) !important;
    }
  }
  
  @keyframes expandFromBall {
    0% {
      border-radius: 50%;
      width: 60px !important;
      height: 60px !important;
      left: var(--ball-left, 20px) !important;
      top: var(--ball-top, 20px) !important;
    }
    50% {
      border-radius: 30px;
      transform: scale(1.2);
    }
    100% {
      border-radius: 12px;
      width: var(--original-width) !important;
      height: var(--original-height) !important;
      left: var(--original-left) !important;
      top: var(--original-top) !important;
      transform: scale(1);
    }
  }
  
  .ball-content {
    font-size: 12px;
    font-weight: bold;
    text-align: center;
    color: white;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 90%;
  }
  
  /* ===== SISTEMA DE PANELES EXPANDIBLES ===== */
  :root {
    --left-panel-width: 400px;
    --center-panel-width: 1100px;
    --right-panel-width: 350px;
  }
  
  .main-layout {
    display: grid;
    grid-template-columns: 
      minmax(300px, var(--left-panel-width)) 
      minmax(800px, var(--center-panel-width)) 
      minmax(250px, var(--right-panel-width));
    gap: 1rem;
    transition: grid-template-columns 0.3s ease;
    position: relative;
    overflow: hidden !important;
  }
  
  .left-panel {
    position: relative;
    transition: all 0.3s ease;
    background: white;
    border-radius: 1rem;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    min-width: 0;
  }
  
  .center-panel {
    position: relative;
    min-width: 0;
    overflow: visible !important;
    transition: all 0.3s ease;
  }
  
  .right-panel {
    position: relative;
    transition: all 0.3s ease;
    background: white;
    border-radius: 1rem;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    min-width: 0;
  }
  
  /* Resize handles para TODOS los paneles */
  .resize-handle {
    position: absolute;
    top: 0;
    width: 24px;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: col-resize;
    z-index: 100;
    opacity: 0;
    transition: opacity 0.2s;
    user-select: none;
  }
  
  .resize-handle:hover {
    opacity: 1;
  }
  
  .resize-handle.left {
    right: -12px;
  }
  
  .resize-handle.center-left {
    left: -12px;
  }
  
  .resize-handle.center-right {
    right: -12px;
  }
  
  .resize-handle.right {
    left: -12px;
  }
  
  .resize-dot {
    width: 4px;
    height: 30px;
    background: #94a3b8;
    border-radius: 2px;
  }
  
  .panel-toggle-btn {
    position: absolute;
    top: 10px;
    z-index: 50;
    background: white;
    border: 1px solid #cbd5e1;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.2s;
    color: #475569;
  }
  
  .panel-toggle-btn:hover {
    background: #f1f5f9;
    transform: scale(1.1);
    color: #1e293b;
  }
  
  .panel-toggle-btn.left {
    right: -16px;
  }
  
  .panel-toggle-btn.center-left {
    left: -16px;
  }
  
  .panel-toggle-btn.center-right {
    right: -16px;
  }
  
  .panel-toggle-btn.right {
    left: -16px;
  }
  
  .panel-collapsed {
    width: 60px !important;
    min-width: 60px !important;
    max-width: 60px !important;
    overflow: hidden !important;
  }
  
  .panel-collapsed .panel-content {
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.2s, visibility 0.2s;
  }
  
  .panel-collapsed .panel-toggle-btn .expand-icon {
    display: block;
  }
  
  .panel-collapsed .panel-toggle-btn .collapse-icon {
    display: none;
  }
  
  .panel-expanded .panel-toggle-btn .expand-icon {
    display: none;
  }
  
  .panel-expanded .panel-toggle-btn .collapse-icon {
    display: block;
  }
  
  .panel-header {
    position: sticky;
    top: 0;
    z-index: 40;
    background: white;
    padding: 1rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  
  .panel-body {
    padding: 1rem;
    overflow-y: auto;
    max-height: calc(100vh - 120px);
  }
  
  .panel-collapsed .panel-header,
  .panel-collapsed .panel-body {
    padding: 0.5rem;
    text-align: center;
  }
  
  .panel-title-collapsed {
    writing-mode: vertical-rl;
    text-orientation: mixed;
    transform: rotate(180deg);
    font-size: 0.8rem;
    font-weight: 600;
    color: #475569;
    margin: auto;
  }
  
  /* Controles de tamaño para tabla central */
  .table-controls {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 40;
    display: flex;
    gap: 4px;
  }
  
  .table-control-btn {
    background: white;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 12px;
    color: #475569;
    transition: all 0.2s;
  }
  
  .table-control-btn:hover {
    background: #f1f5f9;
    color: #1e293b;
  }
  
  /* Tabla expansible */
  .table-container {
    overflow-x: auto;
    border-radius: 0.75rem;
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
    position: relative;
  }
  
  .table-container.resizable {
    min-width: 800px;
    max-width: 1600px;
    resize: horizontal;
    overflow: auto;
  }
  
  .table-container .table-wrapper {
    min-width: 100%;
  }
  
  /* Columnas ajustables */
  .col-resizable {
    position: relative;
  }
  
  .col-resize-handle {
    position: absolute;
    top: 0;
    right: 0;
    width: 8px;
    height: 100%;
    cursor: col-resize;
    z-index: 10;
    opacity: 0;
    transition: opacity 0.2s;
  }
  
  .col-resize-handle:hover {
    opacity: 1;
    background: rgba(59, 130, 246, 0.1);
  }
  
  /* Ancho dinámico para columnas de la tabla */
  .dynamic-table {
    width: 100%;
    min-width: fit-content;
  }
  
  .dynamic-table th,
  .dynamic-table td {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  
  /* Indicador visual de redimensionamiento */
  .resize-indicator {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 10000;
    display: none;
  }
  
  .resize-indicator.active {
    display: block;
  }
  
  .resize-line {
    position: absolute;
    top: 0;
    width: 2px;
    height: 100%;
    background: #3b82f6;
    opacity: 0.7;
  }
  
  /* NUEVO: Estilos para minimización completa del panel */
  .completely-hidden {
    display: none !important;
    width: 0 !important;
    min-width: 0 !important;
    max-width: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
    border: none !important;
    opacity: 0 !important;
    visibility: hidden !important;
    transition: all 0.3s ease !important;
  }
  
  /* Bolita especial para panel completo */
  .entire-panel-ball {
    width: 70px !important;
    height: 70px !important;
    z-index: 10000 !important;
    box-shadow: 0 15px 35px rgba(59, 130, 246, 0.3) !important;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8) !important;
  }
  
  .entire-panel-ball:hover {
    transform: scale(1.15);
    box-shadow: 0 20px 40px rgba(59, 130, 246, 0.4) !important;
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
</style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800">

  <!-- Encabezado -->
  <header class="max-w-[1800px] mx-auto px-3 md:px-4 pt-6">
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
  <main class="max-w-[1800px] mx-auto px-3 md:px-4 py-6">
    <div class="main-layout">
      
      <!-- PANEL IZQUIERDO (Colapsable) -->
      <div id="leftPanel" class="left-panel panel-expanded">
        <div class="resize-handle left" data-panel="left">
          <div class="resize-dot"></div>
        </div>
        
        <button class="panel-toggle-btn left" onclick="togglePanel('left')">
          <span class="collapse-icon">←</span>
          <span class="expand-icon">→</span>
        </button>
        
        <!-- NUEVO: Botón para minimizar todo el panel izquierdo -->
        <div class="panel-header">
          <div class="flex items-center justify-between w-full">
            <h3 class="text-lg font-semibold">Panel Izquierdo</h3>
            <button onclick="minimizarTodoPanelIzquierdo()" 
                    class="text-xs px-3 py-1.5 rounded-lg bg-gradient-to-r from-blue-500 to-indigo-500 text-white hover:from-blue-600 hover:to-indigo-600 transition flex items-center gap-1 shadow-md hover:shadow-lg">
              ⬇️ Minimizar Todo
            </button>
          </div>
        </div>
        
        <div class="panel-body space-y-5">
          
          <!-- Tarjetas de tarifas DINÁMICAS -->
          <div id="container-tarifas" class="container-minimized bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-semibold flex items-center gap-2">
                <span>🚐 Tarifas por Tipo de Vehículo</span>
                <span class="text-xs text-slate-500">(<?= count($columnas_tarifas) ?> tipos de tarifas)</span>
              </h3>
              <button onclick="toggleMinimize('tarifas')" 
                      class="minimize-btn text-xs px-3 py-1 rounded-full bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
                ⬇️ Minimizar
              </button>
            </div>

            <div id="tarifas_grid" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <?php foreach ($vehiculos as $veh):
                $t = $tarifas_guardadas[$veh] ?? [];
              ?>
              <div class="tarjeta-tarifa rounded-2xl border border-slate-200 p-4 shadow-sm bg-slate-50"
                   data-vehiculo="<?= htmlspecialchars($veh) ?>">

                <div class="flex items-center justify-between mb-3">
                  <div class="text-base font-semibold"><?= htmlspecialchars($veh) ?></div>
                  <span class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-700 border border-blue-200">Config</span>
                </div>

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
                ?>
                <label class="block mb-3">
                  <span class="block text-sm font-medium mb-1"><?= htmlspecialchars($etiqueta_final) ?></span>
                  <input type="number" step="1000" value="<?= $valor ?>"
                         data-campo="<?= htmlspecialchars($columna) ?>"
                         class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-white outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition tarifa-input"
                         oninput="recalcular()">
                </label>
                <?php endforeach; ?>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Filtro -->
          <div id="container-filtro" class="container-minimized bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
              <h5 class="text-base font-semibold text-center">📅 Filtro de Liquidación</h5>
              <button onclick="toggleMinimize('filtro')" 
                      class="minimize-btn text-xs px-3 py-1 rounded-full bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
                ⬇️ Minimizar
              </button>
            </div>
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

          <!-- 🔹 Panel de CREACIÓN de NUEVAS CLASIFICACIONES -->
          <div id="container-crear-clasif" class="container-minimized bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
              <h5 class="text-base font-semibold flex items-center justify-between">
                <span>➕ Crear Nueva Clasificación</span>
                <span class="text-xs text-slate-500">Dinámico</span>
              </h5>
              <button onclick="toggleMinimize('crear-clasif')" 
                      class="minimize-btn text-xs px-3 py-1 rounded-full bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
                ⬇️ Minimizar
              </button>
            </div>
            
            <p class="text-xs text-slate-500 mb-3">
              Crea una nueva clasificación. Se agregará a la tabla tarifas.
            </p>

            <div class="flex flex-col gap-2 mb-3">
              <div>
                <label class="block text-xs font-medium mb-1">Nombre de la nueva clasificación</label>
                <input id="txt_nueva_clasificacion" type="text"
                       class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500"
                       placeholder="Ej: Premium, Nocturno, Express...">
              </div>
              <div>
                <label class="block text-xs font-medium mb-1">Texto que deben contener las rutas</label>
                <input id="txt_patron_ruta" type="text"
                       class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500"
                       placeholder="Dejar vacío para solo crear la clasificación">
              </div>
              <button type="button"
                      onclick="crearYAsignarClasificacion()"
                      class="mt-2 inline-flex items-center justify-center rounded-xl bg-green-600 text-white px-4 py-2 text-sm font-semibold hover:bg-green-700 active:bg-green-800 focus:ring-4 focus:ring-green-200">
                ⚙️ Crear y Aplicar
              </button>
            </div>

            <p class="text-[11px] text-slate-500 mt-2">
              La nueva clasificación se creará en la tabla tarifas. Vuelve a dar <strong>Filtrar</strong> para ver los cambios.
            </p>
          </div>

          <!-- 🔹 Panel de CLASIFICACIÓN de RUTAS -->
          <div id="container-clasif-rutas" class="container-minimized bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
              <h5 class="text-base font-semibold flex items-center justify-between">
                <span>🧭 Clasificar Rutas Existentes</span>
                <span class="text-xs text-slate-500">Usa clasificaciones creadas</span>
              </h5>
              <button onclick="toggleMinimize('clasif-rutas')" 
                      class="minimize-btn text-xs px-3 py-1 rounded-full bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
                ⬇️ Minimizar
              </button>
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
                <?php foreach($rutasUnicas as $info): 
                  $estilo = obtenerEstiloClasificacion($info['clasificacion'] ?? '');
                ?>
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
                              data-vehiculo="<?= htmlspecialchars($info['vehiculo']) ?>"
                              style="<?php if($info['clasificacion']): ?>background-color: <?= str_replace('bg-', '#', $estilo['bg']) ?>20; color: <?= str_replace('text-', '#', $estilo['text']) ?>; border-color: <?= str_replace('border-', '#', $estilo['border']) ?>;<?php endif; ?>">
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

            <p class="text-[11px] text-slate-500 mt-2">
              Selecciona una clasificación para cada ruta. Los cambios se guardan automáticamente.
            </p>
          </div>
        </div>
      </div>

      <!-- PANEL CENTRAL (Expansible/Retraible) -->
      <div id="centerPanel" class="center-panel panel-expanded">
        <!-- Resize handles para ambos lados -->
        <div class="resize-handle center-left" data-panel="center" data-side="left">
          <div class="resize-dot"></div>
        </div>
        
        <div class="resize-handle center-right" data-panel="center" data-side="right">
          <div class="resize-dot"></div>
        </div>
        
        <!-- Controles de expansión -->
        <div class="table-controls">
          <button class="table-control-btn" onclick="expandTableWidth(100)" title="Expandir">
            <span>+</span>
          </button>
          <button class="table-control-btn" onclick="shrinkTableWidth(100)" title="Contraer">
            <span>-</span>
          </button>
          <button class="table-control-btn" onclick="resetTableWidth()" title="Restaurar tamaño">
            <span>⟲</span>
          </button>
        </div>
        
        <div class="panel-header">
          <div class="flex items-center justify-between w-full">
            <div>
              <h3 class="text-lg font-semibold">🧑‍✈️ Resumen por Conductor</h3>
              <div id="contador-conductores" class="text-xs text-slate-500 mt-1">
                Mostrando <?= count($datos) ?> conductores
              </div>
            </div>
            <div class="flex items-center gap-2">
              <button onclick="toggleMinimize('resumen')" 
                      class="minimize-btn text-xs px-3 py-1 rounded-full bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
                ⬇️ Minimizar
              </button>
            </div>
          </div>
        </div>
        
        <div class="panel-body">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
            <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
              <!-- BUSCADOR -->
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

          <!-- CONTENEDOR DE TABLA EXPANSIBLE -->
          <div id="tableContainer" class="table-container resizable" style="width: 1100px;">
            <div class="table-wrapper">
              <table id="tabla_conductores" class="dynamic-table w-full text-sm">
                <thead class="bg-blue-600 text-white">
                  <tr>
                    <th class="px-3 py-2 text-left col-resizable" style="min-width: 200px; width: 25%;">
                      Conductor
                      <div class="col-resize-handle" data-col="0"></div>
                    </th>
                    <th class="px-3 py-2 text-center col-resizable" style="min-width: 100px; width: 12%;">
                      Tipo
                      <div class="col-resize-handle" data-col="1"></div>
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
                    <th class="px-3 py-2 text-center col-resizable" 
                        title="<?= htmlspecialchars($clasif) ?>"
                        style="min-width: 70px; width: 7%; background-color: <?= $bg_color ?>; color: <?= $text_color ?>; border-bottom: 2px solid <?= $colorMap[$estilo['border']] ?? '#cbd5e1' ?>;">
                      <?= htmlspecialchars($abreviatura) ?>
                      <div class="col-resize-handle" data-col="<?= $index + 2 ?>"></div>
                    </th>
                    <?php endforeach; ?>
                    <th class="px-3 py-2 text-center col-resizable" style="min-width: 120px; width: 15%;">
                      Total
                      <div class="col-resize-handle" data-col="<?= count($clasificaciones_disponibles) + 2 ?>"></div>
                    </th>
                    <th class="px-3 py-2 text-center col-resizable" style="min-width: 100px; width: 12%;">
                      Pagado
                      <div class="col-resize-handle" data-col="<?= count($clasificaciones_disponibles) + 3 ?>"></div>
                    </th>
                    <th class="px-3 py-2 text-center col-resizable" style="min-width: 80px; width: 10%;">
                      Faltante
                      <div class="col-resize-handle" data-col="<?= count($clasificaciones_disponibles) + 4 ?>"></div>
                    </th>
                  </tr>
                </thead>
                <tbody id="tabla_conductores_body" class="divide-y divide-slate-100 bg-white">
                <?php foreach ($datos as $conductor => $info): 
                  $esMensual = (stripos($info['vehiculo'], 'mensual') !== false);
                  $claseVehiculo = $esMensual ? 'vehiculo-mensual' : '';
                ?>
                  <tr data-vehiculo="<?= htmlspecialchars($info['vehiculo']) ?>" 
                      data-conductor="<?= htmlspecialchars($conductor) ?>" 
                      data-conductor-normalizado="<?= htmlspecialchars(mb_strtolower($conductor)) ?>"
                      data-pagado="<?= (int)($info['pagado'] ?? 0) ?>"
                      class="hover:bg-blue-50/40 transition-colors">
                    <td class="px-3 py-2" style="min-width: 200px;">
                      <button type="button"
                              class="conductor-link text-blue-700 hover:text-blue-900 underline underline-offset-2 transition"
                              title="Ver viajes">
                        <?= htmlspecialchars($conductor) ?>
                      </button>
                    </td>
                    <td class="px-3 py-2 text-center" style="min-width: 100px;">
                      <span class="inline-block <?= $claseVehiculo ?> px-2 py-1 rounded-lg text-xs font-medium">
                        <?= htmlspecialchars($info['vehiculo']) ?>
                        <?php if ($esMensual): ?>
                          <span class="ml-1">📅</span>
                        <?php endif; ?>
                      </span>
                    </td>
                    
                    <?php foreach ($clasificaciones_disponibles as $clasif): 
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
                    ?>
                    <td class="px-3 py-2 text-center font-medium" 
                        style="min-width: 70px; background-color: <?= $bg_cell_color ?>; color: <?= $text_cell_color ?>; border-left: 1px solid <?= str_replace('bg-', '#', $estilo['bg']) ?>30; border-right: 1px solid <?= str_replace('bg-', '#', $estilo['bg']) ?>30;">
                      <?= $cantidad ?>
                    </td>
                    <?php endforeach; ?>

                    <!-- Total -->
                    <td class="px-3 py-2" style="min-width: 120px;">
                      <input type="text"
                             class="totales w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-slate-50 outline-none whitespace-nowrap tabular-nums"
                             readonly dir="ltr">
                    </td>

                    <!-- Pagado -->
                    <td class="px-3 py-2" style="min-width: 100px;">
                      <input type="text"
                             class="pagado w-full rounded-xl border border-emerald-200 px-3 py-2 text-right bg-emerald-50 outline-none whitespace-nowrap tabular-nums"
                             readonly dir="ltr"
                             value="<?= number_format((int)($info['pagado'] ?? 0), 0, ',', '.') ?>">
                    </td>

                    <!-- Faltante -->
                    <td class="px-3 py-2" style="min-width: 80px;">
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

      <!-- PANEL DERECHO (Colapsable) -->
      <div id="rightPanel" class="right-panel panel-expanded">
        <div class="resize-handle right" data-panel="right">
          <div class="resize-dot"></div>
        </div>
        
        <button class="panel-toggle-btn right" onclick="togglePanel('right')">
          <span class="collapse-icon">→</span>
          <span class="expand-icon">←</span>
        </button>
        
        <div class="panel-header">
          <h3 class="text-lg font-semibold">Panel Derecho</h3>
        </div>
        
        <div class="panel-body space-y-5">
          <!-- Panel viajes -->
          <div id="container-panel-viajes" class="container-minimized bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
              <h4 class="text-base font-semibold">🧳 Viajes del Conductor</h4>
              <button onclick="toggleMinimize('panel-viajes')" 
                      class="minimize-btn text-xs px-3 py-1 rounded-full bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
                ⬇️ Minimizar
              </button>
            </div>
            <div id="contenidoPanel"
                 class="min-h-[220px] max-h-[400px] overflow-y-auto rounded-xl border border-slate-200 p-4 text-sm text-slate-600">
              <div class="flex flex-col items-center justify-center h-full text-center">
                <div class="text-slate-400 mb-2">
                  <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                  </svg>
                </div>
                <p class="m-0 font-medium text-slate-500">Selecciona un conductor para ver sus viajes</p>
                <p class="m-0 text-xs text-slate-400 mt-1">Clasificaciones dinámicas con colores</p>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </main>

  <!-- Área para las bolitas flotantes -->
  <div id="floatingBallsArea"></div>

  <!-- Indicador visual de redimensionamiento -->
  <div id="resizeIndicator" class="resize-indicator">
    <div id="resizeLine" class="resize-line"></div>
  </div>

  <script>
    // ===== FUNCIÓN NUEVA: Minimizar TODO el panel izquierdo =====
    function minimizarTodoPanelIzquierdo() {
      const leftPanel = document.getElementById('leftPanel');
      const mainLayout = document.querySelector('.main-layout');
      const ballArea = document.getElementById('floatingBallsArea');
      
      // Verificar si ya está minimizado
      if (leftPanel.classList.contains('completely-hidden')) {
        return; // Ya está minimizado
      }
      
      // Guardar estado original del panel izquierdo
      const rect = leftPanel.getBoundingClientRect();
      leftPanel.dataset.originalState = JSON.stringify({
        gridColumn: window.getComputedStyle(leftPanel).gridColumn,
        width: rect.width,
        height: rect.height,
        left: rect.left + window.scrollX,
        top: rect.top + window.scrollY
      });
      
      // Crear bolita flotante para TODO el panel
      const ball = document.createElement('div');
      ball.id = 'ball-entire-left-panel';
      ball.className = 'floating-ball entire-panel-ball';
      
      // Contenido de la bolita
      ball.innerHTML = `
        <div class="ball-content">
          <div class="text-2xl">📊</div>
          <div class="text-[10px] mt-1">Panel Izquierdo</div>
        </div>
        <button class="close-ball absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center hover:bg-red-600">×</button>
      `;
      
      // Posicionar bolita en la esquina superior derecha
      ball.style.left = '20px';
      ball.style.top = '100px';
      
      // Hacer la bolita arrastrable
      makeBallDraggable(ball, 'entire-left-panel');
      
      // Evento para restaurar el panel
      ball.addEventListener('click', function(e) {
        if (!e.target.closest('.close-ball')) {
          restaurarPanelIzquierdo();
        }
      });
      
      // Evento para cerrar permanentemente (solo elimina la bolita)
      ball.querySelector('.close-ball').addEventListener('click', function(e) {
        e.stopPropagation();
        ball.remove();
      });
      
      // Ocultar completamente el panel izquierdo
      leftPanel.classList.add('completely-hidden', 'hidden');
      
      // Cambiar el layout para redistribuir espacio
      // El panel izquierdo desaparece, centro y derecho se expanden
      mainLayout.style.gridTemplateColumns = '0px minmax(800px, 1400px) minmax(250px, 450px)';
      
      // Ajustar tamaño del contenedor de tabla
      const tableContainer = document.getElementById('tableContainer');
      if (tableContainer) {
        tableContainer.style.width = '1400px';
        document.documentElement.style.setProperty('--center-panel-width', '1400px');
      }
      
      // Agregar bolita al área de bolitas
      ballArea.appendChild(ball);
      
      // Mostrar notificación
      showNotification('Panel izquierdo minimizado a bola flotante', 'info');
    }

    // ===== FUNCIÓN PARA RESTAURAR EL PANEL IZQUIERDO =====
    function restaurarPanelIzquierdo() {
      const leftPanel = document.getElementById('leftPanel');
      const mainLayout = document.querySelector('.main-layout');
      const ball = document.getElementById('ball-entire-left-panel');
      
      // Remover bolita
      if (ball) {
        ball.remove();
      }
      
      // Restaurar estado original
      leftPanel.classList.remove('completely-hidden', 'hidden');
      
      // Restaurar layout original
      const root = document.documentElement;
      mainLayout.style.gridTemplateColumns = '';
      
      // Restaurar tamaño del contenedor de tabla
      const tableContainer = document.getElementById('tableContainer');
      if (tableContainer) {
        tableContainer.style.width = '';
        root.style.setProperty('--center-panel-width', '1100px');
      }
      
      showNotification('Panel izquierdo restaurado', 'success');
    }

    // Función auxiliar para mostrar notificaciones
    function showNotification(message, type = 'info') {
      // Crear elemento de notificación
      const notification = document.createElement('div');
      notification.className = `fixed top-4 right-4 z-50 px-4 py-2 rounded-lg shadow-lg text-white text-sm font-medium animate-fade-in-down ${
        type === 'info' ? 'bg-blue-500' : 
        type === 'success' ? 'bg-green-500' : 
        type === 'warning' ? 'bg-amber-500' : 
        'bg-red-500'
      }`;
      notification.textContent = message;
      
      // Agregar al documento
      document.body.appendChild(notification);
      
      // Remover después de 3 segundos
      setTimeout(() => {
        notification.classList.add('opacity-0', 'transition-opacity', 'duration-300');
        setTimeout(() => {
          if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
          }
        }, 300);
      }, 3000);
    }

    // ===== SISTEMA DE REDIMENSIONAMIENTO COMPLETO =====
    let isResizing = false;
    let currentPanel = null;
    let currentSide = null;
    let startX, startWidth;
    let startCenterWidth, startLeftWidth, startRightWidth;
    const originalCenterWidth = 1100;
    const originalLeftWidth = 400;
    const originalRightWidth = 350;
    const minCenterWidth = 800;
    const maxCenterWidth = 1600;
    const minPanelWidth = 250;
    const maxPanelWidth = 600;
    
    // Configuración inicial de CSS
    document.documentElement.style.setProperty('--center-panel-width', originalCenterWidth + 'px');
    document.documentElement.style.setProperty('--left-panel-width', originalLeftWidth + 'px');
    document.documentElement.style.setProperty('--right-panel-width', originalRightWidth + 'px');
    
    // Sistema de redimensionamiento para TODOS los paneles
    document.querySelectorAll('.resize-handle').forEach(handle => {
      handle.addEventListener('mousedown', initResize);
    });
    
    function initResize(e) {
      const handle = e.target.closest('.resize-handle');
      currentPanel = handle.dataset.panel;
      currentSide = handle.dataset.side || 'right'; // Por defecto
      
      const panel = document.getElementById(currentPanel + 'Panel');
      const tableContainer = document.getElementById('tableContainer');
      const mainLayout = document.querySelector('.main-layout');
      
      isResizing = true;
      startX = e.clientX;
      
      // Guardar dimensiones iniciales
      if (currentPanel === 'center') {
        startCenterWidth = parseInt(getComputedStyle(tableContainer).width, 10);
        startLeftWidth = parseInt(getComputedStyle(document.getElementById('leftPanel')).width, 10);
        startRightWidth = parseInt(getComputedStyle(document.getElementById('rightPanel')).width, 10);
      } else {
        startWidth = parseInt(getComputedStyle(panel).width, 10);
      }
      
      // Mostrar indicador visual
      document.getElementById('resizeIndicator').classList.add('active');
      
      document.addEventListener('mousemove', resize);
      document.addEventListener('mouseup', stopResize);
      
      e.preventDefault();
    }
    
    function resize(e) {
      if (!isResizing) return;
      
      const dx = e.clientX - startX;
      const tableContainer = document.getElementById('tableContainer');
      const mainLayout = document.querySelector('.main-layout');
      const root = document.documentElement;
      
      // Actualizar línea visual
      const resizeLine = document.getElementById('resizeLine');
      resizeLine.style.left = e.clientX + 'px';
      
      if (currentPanel === 'center') {
        // Redimensionar panel CENTRAL
        let newCenterWidth;
        
        if (currentSide === 'left') {
          // Redimensionar desde el lado izquierdo
          newCenterWidth = startCenterWidth - dx;
          const newLeftWidth = startLeftWidth + dx;
          
          // Aplicar límites
          newCenterWidth = Math.max(minCenterWidth, Math.min(newCenterWidth, maxCenterWidth));
          const clampedLeftWidth = Math.max(minPanelWidth, Math.min(newLeftWidth, maxPanelWidth));
          
          // Ajustar diferencial
          const leftDiff = newLeftWidth - clampedLeftWidth;
          if (leftDiff !== 0) {
            newCenterWidth += leftDiff;
          }
          
          // Aplicar cambios
          tableContainer.style.width = newCenterWidth + 'px';
          root.style.setProperty('--center-panel-width', newCenterWidth + 'px');
          root.style.setProperty('--left-panel-width', clampedLeftWidth + 'px');
          
        } else {
          // Redimensionar desde el lado derecho
          newCenterWidth = startCenterWidth + dx;
          const newRightWidth = startRightWidth - dx;
          
          // Aplicar límites
          newCenterWidth = Math.max(minCenterWidth, Math.min(newCenterWidth, maxCenterWidth));
          const clampedRightWidth = Math.max(minPanelWidth, Math.min(newRightWidth, maxPanelWidth));
          
          // Ajustar diferencial
          const rightDiff = newRightWidth - clampedRightWidth;
          if (rightDiff !== 0) {
            newCenterWidth -= rightDiff;
          }
          
          // Aplicar cambios
          tableContainer.style.width = newCenterWidth + 'px';
          root.style.setProperty('--center-panel-width', newCenterWidth + 'px');
          root.style.setProperty('--right-panel-width', clampedRightWidth + 'px');
        }
        
      } else {
        // Redimensionar paneles LATERALES
        const panel = document.getElementById(currentPanel + 'Panel');
        let newWidth;
        
        if (currentPanel === 'left') {
          newWidth = startWidth + dx;
        } else {
          newWidth = startWidth - dx;
        }
        
        // Aplicar límites
        newWidth = Math.max(minPanelWidth, Math.min(newWidth, maxPanelWidth));
        
        // Aplicar nuevo ancho
        panel.style.width = newWidth + 'px';
        root.style.setProperty(`--${currentPanel}-panel-width`, newWidth + 'px');
      }
      
      // Prevenir selección de texto durante el resize
      document.body.style.userSelect = 'none';
      document.body.style.cursor = 'col-resize';
    }
    
    function stopResize() {
      isResizing = false;
      document.removeEventListener('mousemove', resize);
      document.removeEventListener('mouseup', stopResize);
      
      // Ocultar indicador visual
      document.getElementById('resizeIndicator').classList.remove('active');
      
      document.body.style.userSelect = '';
      document.body.style.cursor = '';
    }
    
    // ===== CONTROLES PARA TABLA CENTRAL =====
    function expandTableWidth(amount = 100) {
      const tableContainer = document.getElementById('tableContainer');
      const root = document.documentElement;
      const currentWidth = parseInt(getComputedStyle(tableContainer).width, 10);
      const newWidth = Math.min(currentWidth + amount, maxCenterWidth);
      
      tableContainer.style.width = newWidth + 'px';
      root.style.setProperty('--center-panel-width', newWidth + 'px');
    }
    
    function shrinkTableWidth(amount = 100) {
      const tableContainer = document.getElementById('tableContainer');
      const root = document.documentElement;
      const currentWidth = parseInt(getComputedStyle(tableContainer).width, 10);
      const newWidth = Math.max(currentWidth - amount, minCenterWidth);
      
      tableContainer.style.width = newWidth + 'px';
      root.style.setProperty('--center-panel-width', newWidth + 'px');
    }
    
    function resetTableWidth() {
      const tableContainer = document.getElementById('tableContainer');
      const root = document.documentElement;
      
      tableContainer.style.width = originalCenterWidth + 'px';
      root.style.setProperty('--center-panel-width', originalCenterWidth + 'px');
      root.style.setProperty('--left-panel-width', originalLeftWidth + 'px');
      root.style.setProperty('--right-panel-width', originalRightWidth + 'px');
      
      // Restaurar también los paneles laterales
      document.getElementById('leftPanel').style.width = '';
      document.getElementById('rightPanel').style.width = '';
    }
    
    // ===== REDIMENSIONAMIENTO DE COLUMNAS =====
    let isResizingColumn = false;
    let currentColIndex = null;
    let startColX, startColWidth;
    
    document.querySelectorAll('.col-resize-handle').forEach(handle => {
      handle.addEventListener('mousedown', initColResize);
    });
    
    function initColResize(e) {
      currentColIndex = parseInt(e.target.dataset.col);
      isResizingColumn = true;
      startColX = e.clientX;
      
      const table = document.getElementById('tabla_conductores');
      const col = table.querySelectorAll('th, td').nth(currentColIndex);
      startColWidth = col.getBoundingClientRect().width;
      
      // Mostrar indicador visual
      document.getElementById('resizeIndicator').classList.add('active');
      const resizeLine = document.getElementById('resizeLine');
      resizeLine.style.left = e.clientX + 'px';
      
      document.addEventListener('mousemove', resizeColumn);
      document.addEventListener('mouseup', stopColResize);
      
      e.preventDefault();
      e.stopPropagation();
    }
    
    function resizeColumn(e) {
      if (!isResizingColumn) return;
      
      const dx = e.clientX - startColX;
      const newWidth = Math.max(50, startColWidth + dx);
      
      // Actualizar línea visual
      const resizeLine = document.getElementById('resizeLine');
      resizeLine.style.left = e.clientX + 'px';
      
      // Aplicar nuevo ancho a todas las celdas de esta columna
      const table = document.getElementById('tabla_conductores');
      const allRows = table.querySelectorAll('tr');
      
      allRows.forEach(row => {
        const cell = row.children[currentColIndex];
        if (cell) {
          cell.style.width = newWidth + 'px';
          cell.style.minWidth = newWidth + 'px';
        }
      });
      
      document.body.style.userSelect = 'none';
      document.body.style.cursor = 'col-resize';
    }
    
    function stopColResize() {
      isResizingColumn = false;
      document.removeEventListener('mousemove', resizeColumn);
      document.removeEventListener('mouseup', stopColResize);
      
      // Ocultar indicador visual
      document.getElementById('resizeIndicator').classList.remove('active');
      
      document.body.style.userSelect = '';
      document.body.style.cursor = '';
    }
    
    // ===== SISTEMA DE PANELES COLAPSABLES =====
    function togglePanel(panelSide) {
      const panel = document.getElementById(panelSide + 'Panel');
      
      if (panel.classList.contains('panel-collapsed')) {
        // Expandir panel
        panel.classList.remove('panel-collapsed');
        panel.classList.add('panel-expanded');
      } else {
        // Colapsar panel
        panel.classList.remove('panel-expanded');
        panel.classList.add('panel-collapsed');
      }
    }
    
    // ===== SISTEMA DE MINIMIZAR CONTENEDORES INDIVIDUALES =====
    const containerStates = {};
    const ballColors = {
      'tarifas': 'bg-gradient-to-br from-blue-500 to-cyan-500',
      'filtro': 'bg-gradient-to-br from-green-500 to-emerald-500',
      'crear-clasif': 'bg-gradient-to-br from-purple-500 to-pink-500',
      'clasif-rutas': 'bg-gradient-to-br from-orange-500 to-amber-500',
      'resumen': 'bg-gradient-to-br from-indigo-500 to-purple-500',
      'panel-viajes': 'bg-gradient-to-br from-teal-500 to-cyan-500'
    };
    
    const ballIcons = {
      'tarifas': '🚐',
      'filtro': '📅',
      'crear-clasif': '➕',
      'clasif-rutas': '🧭',
      'resumen': '🧑‍✈️',
      'panel-viajes': '🧳'
    };
    
    const ballTitles = {
      'tarifas': 'Tarifas',
      'filtro': 'Filtro',
      'crear-clasif': 'Crear Clasif',
      'clasif-rutas': 'Clasif Rutas',
      'resumen': 'Resumen',
      'panel-viajes': 'Viajes'
    };

    function toggleMinimize(containerId) {
      const container = document.getElementById(`container-${containerId}`);
      const ball = document.getElementById(`ball-${containerId}`);
      
      if (container.classList.contains('hidden')) {
        // Restaurar desde la bolita
        restoreContainer(containerId);
      } else {
        // Minimizar a bolita
        minimizeToBall(containerId);
      }
    }

    function minimizeToBall(containerId) {
      const container = document.getElementById(`container-${containerId}`);
      const ballArea = document.getElementById('floatingBallsArea');
      
      // Guardar posición original
      const rect = container.getBoundingClientRect();
      containerStates[containerId] = {
        originalLeft: rect.left + window.scrollX,
        originalTop: rect.top + window.scrollY,
        originalWidth: rect.width,
        originalHeight: rect.height,
        container: container
      };
      
      // Crear bolita flotante
      const ball = document.createElement('div');
      ball.id = `ball-${containerId}`;
      ball.className = `floating-ball minimized ${ballColors[containerId]}`;
      ball.style.setProperty('--original-width', rect.width + 'px');
      ball.style.setProperty('--original-height', rect.height + 'px');
      ball.style.setProperty('--original-left', rect.left + window.scrollX + 'px');
      ball.style.setProperty('--original-top', rect.top + window.scrollY + 'px');
      
      // Posición aleatoria inicial
      const randomLeft = Math.random() * (window.innerWidth - 100) + 20;
      const randomTop = Math.random() * (window.innerHeight - 100) + 20;
      ball.style.setProperty('--ball-left', randomLeft + 'px');
      ball.style.setProperty('--ball-top', randomTop + 'px');
      
      // Contenido de la bolita
      ball.innerHTML = `
        <div class="ball-content">
          <div class="text-2xl">${ballIcons[containerId]}</div>
          <div class="text-[10px] mt-1">${ballTitles[containerId]}</div>
        </div>
      `;
      
      // Hacer la bolita arrastrable
      makeBallDraggable(ball, containerId);
      
      // Agregar evento de clic para restaurar
      ball.addEventListener('click', function(e) {
        if (!e.target.closest('.close-ball')) {
          restoreContainer(containerId);
        }
      });
      
      // Botón para cerrar la bolita
      const closeBtn = document.createElement('button');
      closeBtn.className = 'close-ball absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center hover:bg-red-600';
      closeBtn.innerHTML = '×';
      closeBtn.onclick = function(e) {
        e.stopPropagation();
        removeBall(containerId);
      };
      ball.appendChild(closeBtn);
      
      // Ocultar contenedor y mostrar bolita
      container.classList.add('hidden');
      ballArea.appendChild(ball);
      
      // Animar la transformación a bolita
      setTimeout(() => {
        ball.classList.remove('minimized');
        ball.style.left = randomLeft + 'px';
        ball.style.top = randomTop + 'px';
        ball.style.width = '60px';
        ball.style.height = '60px';
      }, 50);
    }

    function restoreContainer(containerId) {
      const container = containerStates[containerId].container;
      const ball = document.getElementById(`ball-${containerId}`);
      
      if (!ball || !container) return;
      
      // Animar restauración
      ball.classList.add('restored');
      
      setTimeout(() => {
        // Mostrar contenedor
        container.classList.remove('hidden');
        
        // Eliminar bolita
        if (ball.parentNode) {
          ball.parentNode.removeChild(ball);
        }
        
        // Limpiar animación
        ball.classList.remove('restored');
      }, 500);
    }

    function removeBall(containerId) {
      const ball = document.getElementById(`ball-${containerId}`);
      if (ball && ball.parentNode) {
        ball.parentNode.removeChild(ball);
      }
    }

    function makeBallDraggable(ball, containerId) {
      let isDragging = false;
      let currentX;
      let currentY;
      let initialX;
      let initialY;
      let xOffset = 0;
      let yOffset = 0;
      
      ball.addEventListener('mousedown', dragStart);
      ball.addEventListener('touchstart', dragStart);
      
      function dragStart(e) {
        if (e.type === "touchstart") {
          initialX = e.touches[0].clientX - xOffset;
          initialY = e.touches[0].clientY - yOffset;
        } else {
          initialX = e.clientX - xOffset;
          initialY = e.clientY - yOffset;
        }
        
        if (e.target === ball || e.target.closest('.ball-content')) {
          isDragging = true;
          ball.style.cursor = 'grabbing';
          ball.style.zIndex = '10000';
        }
      }
      
      function dragEnd(e) {
        initialX = currentX;
        initialY = currentY;
        isDragging = false;
        ball.style.cursor = 'move';
        ball.style.zIndex = '9999';
        
        // Guardar posición final
        ball.style.setProperty('--ball-left', ball.style.left);
        ball.style.setProperty('--ball-top', ball.style.top);
      }
      
      function drag(e) {
        if (isDragging) {
          e.preventDefault();
          
          if (e.type === "touchmove") {
            currentX = e.touches[0].clientX - initialX;
            currentY = e.touches[0].clientY - initialY;
          } else {
            currentX = e.clientX - initialX;
            currentY = e.clientY - initialY;
          }
          
          xOffset = currentX;
          yOffset = currentY;
          
          ball.style.left = currentX + "px";
          ball.style.top = currentY + "px";
        }
      }
      
      document.addEventListener('mousemove', drag);
      document.addEventListener('touchmove', drag, { passive: false });
      document.addEventListener('mouseup', dragEnd);
      document.addEventListener('touchend', dragEnd);
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

    // ===== FUNCIONES DE CÁLCULO =====
    function getTarifas(){
      const tarifas = {};
      document.querySelectorAll('.tarjeta-tarifa').forEach(card=>{
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
        let columnaIndex = 2; // Empieza después de conductor y tipo
        
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
                
                // Guardar clasificación
                fetch('<?= basename(__FILE__) ?>', {
                  method:'POST',
                  headers:{'Content-Type':'application/x-www-form-urlencoded'},
                  body:new URLSearchParams({
                    guardar_clasificacion:1,
                    ruta:row.dataset.ruta,
                    tipo_vehiculo:vehiculo,
                    clasificacion:nombreClasif.toLowerCase()
                  })
                });
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
                const card = input.closest('.tarjeta-tarifa');
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

    // ===== INICIALIZACIÓN =====
    document.addEventListener('DOMContentLoaded', function() {
      // Agregar animación CSS para notificaciones (ya está en el CSS)
      
      // Configurar eventos de tarifas
      configurarEventosTarifas();
      
      // Click en conductor → carga viajes
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

      // Cambio clasificación ruta individual
      document.querySelectorAll('.select-clasif-ruta').forEach(sel=>{
        sel.addEventListener('change', ()=>{
          const ruta = sel.dataset.ruta;
          const vehiculo = sel.dataset.vehiculo;
          const clasif = sel.value.toLowerCase(); // NORMALIZAR A MINÚSCULAS
          if (clasif) guardarClasificacionRuta(ruta, vehiculo, clasif);
        });
      });

      // Permitir escribir nuevas clasificaciones en select
      document.querySelectorAll('.select-clasif-ruta').forEach(sel=>{
        sel.addEventListener('keydown', function(e) {
          if (e.key === 'Enter') {
            const nuevaClasif = this.value.trim().toLowerCase();
            if (nuevaClasif) {
              const ruta = this.dataset.ruta;
              const vehiculo = this.dataset.vehiculo;
              guardarClasificacionRuta(ruta, vehiculo, nuevaClasif);
              this.blur();
            }
          }
        });
      });

      // Recalcular al cargar
      recalcular();
    });
  </script>
</body>
</html>