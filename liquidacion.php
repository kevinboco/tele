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
    
    // Limpiar nombre para columna SQL
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

    // Validar que el campo exista en la tabla tarifas
    $columnas_tarifas = obtenerColumnasTarifas($conn);
    if (!in_array($campo, $columnas_tarifas)) { 
        echo "error: campo no válido"; 
        exit; 
    }

    $conn->query("INSERT IGNORE INTO tarifas (empresa, tipo_vehiculo) VALUES ('$empresa', '$vehiculo')");
    $sql = "UPDATE tarifas SET $campo = $valor WHERE empresa='$empresa' AND tipo_vehiculo='$vehiculo'";
    echo $conn->query($sql) ? "ok" : "error: " . $conn->error;
    exit;
}

/* =======================================================
   🔹 Guardar CLASIFICACIÓN de rutas (AJAX)
======================================================= */
if (isset($_POST['guardar_clasificacion'])) {
    $ruta       = $conn->real_escape_string($_POST['ruta']);
    $vehiculo   = $conn->real_escape_string($_POST['tipo_vehiculo']);
    $clasif     = $conn->real_escape_string($_POST['clasificacion']);

    // Permitir cualquier clasificación
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
        $clasif_rutas[$key] = $r['clasificacion'];
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
  ::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:999px}
  ::-webkit-scrollbar-thumb:hover{background:#9ca3af}
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
</style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800">

  <!-- Encabezado -->
  <header class="max-w-[1600px] mx-auto px-3 md:px-4 pt-6">
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
  <main class="max-w-[1600px] mx-auto px-3 md:px-4 py-6">
    <div class="grid grid-cols-1 xl:grid-cols-[1fr_2.6fr_0.9fr] gap-5 items-start">

      <!-- Columna 1: Tarifas + Filtro + Clasificación de rutas -->
      <section class="space-y-5">

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
                       class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-white outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition"
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
      </section>

      <!-- Columna 2: Resumen por conductor -->
      <section id="container-resumen" class="container-minimized bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
        
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
          <div>
            <h3 class="text-lg font-semibold">🧑‍✈️ Resumen por Conductor</h3>
            <div id="contador-conductores" class="text-xs text-slate-500 mt-1">
              Mostrando <?= count($datos) ?> conductores
            </div>
          </div>
          <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
            <!-- BUSCADOR -->
            <div class="buscar-container w-full md:w-64">
              <input id="buscadorConductores" type="text" 
                     placeholder="Buscar conductor..." 
                     class="w-full rounded-lg border border-slate-300 px-3 py-2 pl-3 pr-10 text-sm">
              <button id="clearBuscar" class="buscar-clear">✕</button>
            </div>

            <button onclick="toggleMinimize('resumen')" 
                    class="minimize-btn text-xs px-3 py-2 rounded-full bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
              ⬇️ Minimizar
            </button>

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

        <div class="mt-4 w-full rounded-xl border border-slate-200 overflow-x-auto">
          <table id="tabla_conductores" class="w-full text-sm table-fixed">
            <colgroup>
              <col class="col-conductor">
              <col class="col-vehiculo">
              <?php foreach ($clasificaciones_disponibles as $clasif): ?>
              <col class="col-clasif">
              <?php endforeach; ?>
              <col class="col-total">
              <col class="col-pagado">
              <col class="col-faltante">
            </colgroup>
            <thead class="bg-blue-600 text-white">
              <tr>
                <th class="px-3 py-2 text-left">Conductor</th>
                <th class="px-3 py-2 text-center">Tipo</th>
                <?php foreach ($clasificaciones_disponibles as $clasif): 
                  $estilo = obtenerEstiloClasificacion($clasif);
                  $abreviatura = strtoupper(substr($clasif, 0, 3));
                  if ($clasif === 'carrotanque') $abreviatura = 'CTK';
                  if ($clasif === 'riohacha') $abreviatura = 'RIO';
                  if ($clasif === 'siapana') $abreviatura = 'SIA';
                ?>
                <th class="px-3 py-2 text-center" title="<?= htmlspecialchars(ucfirst($clasif)) ?>"
                    style="background-color: <?= str_replace('bg-', '#', $estilo['bg']) ?>; color: <?= str_replace('text-', '#', $estilo['text']) ?>;">
                  <?= htmlspecialchars($abreviatura) ?>
                </th>
                <?php endforeach; ?>
                <th class="px-3 py-2 text-center">Total</th>
                <th class="px-3 py-2 text-center">Pagado</th>
                <th class="px-3 py-2 text-center">Faltante</th>
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
                <td class="px-3 py-2">
                  <button type="button"
                          class="conductor-link text-blue-700 hover:text-blue-900 underline underline-offset-2 transition"
                          title="Ver viajes">
                    <?= htmlspecialchars($conductor) ?>
                  </button>
                </td>
                <td class="px-3 py-2 text-center">
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
                ?>
                <td class="px-3 py-2 text-center font-medium" 
                    style="background-color: <?= str_replace('bg-', '#', $estilo['bg']) ?>20;">
                  <?= $cantidad ?>
                </td>
                <?php endforeach; ?>

                <!-- Total -->
                <td class="px-3 py-2">
                  <input type="text"
                         class="totales w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-slate-50 outline-none whitespace-nowrap tabular-nums"
                         readonly dir="ltr">
                </td>

                <!-- Pagado -->
                <td class="px-3 py-2">
                  <input type="text"
                         class="pagado w-full rounded-xl border border-emerald-200 px-3 py-2 text-right bg-emerald-50 outline-none whitespace-nowrap tabular-nums"
                         readonly dir="ltr"
                         value="<?= number_format((int)($info['pagado'] ?? 0), 0, ',', '.') ?>">
                </td>

                <!-- Faltante -->
                <td class="px-3 py-2">
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
      </section>

      <!-- Columna 3: Panel viajes -->
      <aside class="space-y-5">
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
      </aside>

    </div>
  </main>

  <!-- Área para las bolitas flotantes -->
  <div id="floatingBallsArea"></div>

  <script>
    // ===== SISTEMA DE MINIMIZAR CONTENEDORES =====
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
          const campo = input.dataset.campo;
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
      
      // Obtener clasificaciones desde los encabezados
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

      // 1. Crear nueva columna en tarifas
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
          clasificacion:clasificacion
        })
      })
      .then(r=>r.text())
      .then(t=>{
        if (t.trim() !== 'ok') console.error('Error guardando clasificación:', t);
      });
    }

    // ===== INICIALIZACIÓN =====
    document.addEventListener('DOMContentLoaded', function() {
      // Guardar tarifas AJAX
      document.querySelectorAll('.tarjeta-tarifa input').forEach(input=>{
        input.addEventListener('change', ()=>{
          const card = input.closest('.tarjeta-tarifa');
          const tipoVehiculo = card.dataset.vehiculo;
          const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
          const campo = input.dataset.campo;
          const valor = parseFloat(input.value)||0;

          fetch('<?= basename(__FILE__) ?>', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:new URLSearchParams({guardar_tarifa:1, empresa, tipo_vehiculo:tipoVehiculo, campo, valor})
          })
          .then(r=>r.text())
          .then(t=>{
            if (t.trim() !== 'ok') console.error('Error guardando tarifa:', t);
            recalcular();
          });
        });
      });

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
          const clasif = sel.value;
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