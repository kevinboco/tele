<?php
// =======================================================
// 🔹 VISTA PANELES - COMPLETAMENTE AUTÓNOMA
// =======================================================
// Recibir datos desde liquidacion.php
extract($datos_vistas);

// =======================================================
// 🔹 FUNCIONES PHP QUE SOLO USA ESTA VISTA
// =======================================================
function obtenerColumnasTarifasPanel($conn) {
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

function crearNuevaColumnaTarifaPanel($conn, $nombre_columna) {
    $nombre_columna = preg_replace('/[^a-zA-Z0-9_]/', '_', $nombre_columna);
    $nombre_columna = strtolower($nombre_columna);
    
    $columnas_existentes = obtenerColumnasTarifasPanel($conn);
    if (in_array($nombre_columna, $columnas_existentes)) {
        return true;
    }
    
    $sql = "ALTER TABLE tarifas ADD COLUMN `$nombre_columna` DECIMAL(10,2) DEFAULT 0.00";
    return $conn->query($sql);
}

function obtenerClasificacionesDisponiblesPanel($conn) {
    return obtenerColumnasTarifasPanel($conn);
}

function obtenerEstiloClasificacionPanel($clasificacion) {
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

function obtenerColorVehiculoPanel($vehiculo) {
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

// =======================================================
// 🔹 PROCESAMIENTO DE DATOS PARA ESTA VISTA
// =======================================================
$columnas_tarifas = obtenerColumnasTarifasPanel($conn);
$clasificaciones_disponibles = obtenerClasificacionesDisponiblesPanel($conn);

// Obtener vehículos únicos
$vehiculos = [];
$resVeh = $conn->query("SELECT DISTINCT tipo_vehiculo FROM viajes WHERE tipo_vehiculo IS NOT NULL AND tipo_vehiculo<>''");
if ($resVeh) while ($r = $resVeh->fetch_assoc()) $vehiculos[] = $r['tipo_vehiculo'];

// Obtener rutas únicas para clasificación
$rutasUnicas = [];
$resRutas = $conn->query("SELECT DISTINCT ruta, tipo_vehiculo FROM viajes WHERE ruta IS NOT NULL AND ruta<>''");
if ($resRutas) {
    while ($r = $resRutas->fetch_assoc()) {
        $key = mb_strtolower(trim($r['ruta'] . '|' . $r['tipo_vehiculo']), 'UTF-8');
        $rutasUnicas[$key] = [
            'ruta' => $r['ruta'],
            'vehiculo' => $r['tipo_vehiculo']
        ];
    }
}

// Cargar clasificaciones existentes
$clasif_rutas = [];
$resClasif = $conn->query("SELECT ruta, tipo_vehiculo, clasificacion FROM ruta_clasificacion");
if ($resClasif) {
    while ($r = $resClasif->fetch_assoc()) {
        $key = mb_strtolower(trim($r['ruta'] . '|' . $r['tipo_vehiculo']), 'UTF-8');
        $rutasUnicas[$key]['clasificacion'] = $r['clasificacion'];
    }
}

// Tarifas guardadas
$tarifas_guardadas = [];
if ($empresaFiltro !== "") {
  $resTarifas = $conn->query("SELECT * FROM tarifas WHERE empresa='$empresaFiltro'");
  if ($resTarifas) {
    while ($r = $resTarifas->fetch_assoc()) {
      $tarifas_guardadas[$r['tipo_vehiculo']] = $r;
    }
  }
}

// =======================================================
// 🔹 CSS ESPECÍFICO DE LOS PANELES
// =======================================================
?>
<style>
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
  
  .ball-selector-columnas {
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
  
  /* ===== ESTILOS PARA SELECTOR DE COLUMNAS ===== */
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
</style>

<!-- ======================================================= -->
<!-- 🔹 HTML DE LOS PANELES -->
<!-- ======================================================= -->

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
  
  <!-- Bolita 4: Seleccionar columnas de la tabla -->
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
        $color_vehiculo = obtenerColorVehiculoPanel($veh);
        $t = $tarifas_guardadas[$veh] ?? [];
        $veh_id = preg_replace('/[^a-z0-9]/i', '-', strtolower($veh));
      ?>
      <div class="tarjeta-tarifa-acordeon rounded-xl border <?= $color_vehiculo['border'] ?> overflow-hidden shadow-sm"
           data-vehiculo="<?= htmlspecialchars($veh) ?>"
           id="acordeon-<?= $veh_id ?>"
           style="background-color: <?= str_replace('bg-', '#', $color_vehiculo['dark']) ?>;">
        
        <!-- CABECERA DEL ACORDEÓN -->
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
        
        <!-- CONTENIDO DESPLEGABLE -->
        <div class="acordeon-content px-4 py-3 border-t <?= $color_vehiculo['border'] ?> bg-white" id="content-<?= $veh_id ?>">
          <div class="space-y-3">
            <?php foreach ($columnas_tarifas as $columna): 
              $valor = isset($t[$columna]) ? (float)$t[$columna] : 0;
              $etiqueta = ucfirst($columna);
              
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
              $estilo_clasif = obtenerEstiloClasificacionPanel($columna);
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
                       oninput="recalcularDesdePaneles()">
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
          $estilo = obtenerEstiloClasificacionPanel($clasificacion_actual);
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
                $color_vehiculo = obtenerColorVehiculoPanel($info['vehiculo']);
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
                  $estilo_opcion = obtenerEstiloClasificacionPanel($clasif);
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

<!-- ===== PANEL SELECTOR DE COLUMNAS ===== -->
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
          $estilo = obtenerEstiloClasificacionPanel($clasif);
          // Necesitamos obtener las columnas seleccionadas desde cookie
          $session_key = "columnas_seleccionadas_" . md5($empresaFiltro . $desde . $hasta);
          $columnas_seleccionadas_panel = [];
          if (isset($_COOKIE[$session_key])) {
              $columnas_seleccionadas_panel = json_decode($_COOKIE[$session_key], true);
          } else {
              $columnas_seleccionadas_panel = $clasificaciones_disponibles;
          }
          $seleccionada = in_array($clasif, $columnas_seleccionadas_panel);
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

<!-- ======================================================= -->
<!-- 🔹 JAVASCRIPT ESPECÍFICO DE LOS PANELES -->
<!-- ======================================================= -->
<script>
// ===== SISTEMA DE BOLITAS Y PANELES =====
let activePanel = null;
const panels = ['tarifas', 'crear-clasif', 'clasif-rutas', 'selector-columnas'];

// Inicializar sistema de bolitas
document.addEventListener('DOMContentLoaded', function() {
  panels.forEach(panelId => {
    const ball = document.getElementById(`ball-${panelId}`);
    const panel = document.getElementById(`panel-${panelId}`);
    const closeBtn = panel.querySelector('.side-panel-close');
    const overlay = document.getElementById('sidePanelOverlay');
    
    ball.addEventListener('click', () => togglePanel(panelId));
    closeBtn.addEventListener('click', () => togglePanel(panelId));
    
    overlay.addEventListener('click', () => {
      if (activePanel === panelId) {
        togglePanel(panelId);
      }
    });
  });
  
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && activePanel) {
      togglePanel(activePanel);
    }
  });
  
  colapsarTodosTarifas();
  inicializarColoresClasificacion();
  inicializarSeleccionColumnas();
});

// Función para abrir/cerrar paneles
function togglePanel(panelId) {
  const ball = document.getElementById(`ball-${panelId}`);
  const panel = document.getElementById(`panel-${panelId}`);
  const overlay = document.getElementById('sidePanelOverlay');
  const tableWrapper = document.getElementById('tableContainerWrapper');
  
  if (activePanel === panelId) {
    panel.classList.remove('active');
    ball.classList.remove('ball-active');
    overlay.classList.remove('active');
    if (tableWrapper) tableWrapper.classList.remove('with-panel');
    activePanel = null;
  } else {
    if (activePanel) {
      document.getElementById(`panel-${activePanel}`).classList.remove('active');
      document.getElementById(`ball-${activePanel}`).classList.remove('ball-active');
    }
    
    panel.classList.add('active');
    ball.classList.add('ball-active');
    overlay.classList.add('active');
    if (tableWrapper) tableWrapper.classList.add('with-panel');
    activePanel = panelId;
    
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

// ===== SISTEMA DE SELECCIÓN DE COLUMNAS =====
let columnasSeleccionadasPaneles = <?= json_encode($columnas_seleccionadas) ?>;

function inicializarSeleccionColumnas() {
  columnasSeleccionadasPaneles.forEach(columna => {
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
}

function toggleColumna(columna) {
  const checkbox = document.getElementById('checkbox-' + columna);
  const item = document.querySelector('[data-columna="' + columna + '"]');
  
  if (columnasSeleccionadasPaneles.includes(columna)) {
    columnasSeleccionadasPaneles = columnasSeleccionadasPaneles.filter(c => c !== columna);
    checkbox.classList.remove('checked');
    item.classList.remove('selected');
  } else {
    columnasSeleccionadasPaneles.push(columna);
    checkbox.classList.add('checked');
    item.classList.add('selected');
  }
  
  actualizarContadorColumnas();
  actualizarColumnasTabla();
}

function seleccionarTodasColumnas() {
  const todasColumnas = document.querySelectorAll('.columna-checkbox-item');
  columnasSeleccionadasPaneles = [];
  
  todasColumnas.forEach(item => {
    const columna = item.dataset.columna;
    columnasSeleccionadasPaneles.push(columna);
    
    const checkbox = document.getElementById('checkbox-' + columna);
    checkbox.classList.add('checked');
    item.classList.add('selected');
  });
  
  actualizarContadorColumnas();
  actualizarColumnasTabla();
}

function deseleccionarTodasColumnas() {
  const todasColumnas = document.querySelectorAll('.columna-checkbox-item');
  columnasSeleccionadasPaneles = [];
  
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
  if (contadorSeleccionadas) {
    contadorSeleccionadas.textContent = columnasSeleccionadasPaneles.length;
  }
}

function actualizarColumnasTabla() {
  // Esta función actualiza las columnas en la tabla principal
  // Como la tabla está en otra vista, necesitamos una forma de comunicarnos
  // Por ahora, guardamos en cookie y recargamos
  guardarSeleccionColumnas();
}

// ===== ENDPOINTS AJAX ESPECÍFICOS DE LOS PANELES =====

// Crear nueva clasificación
function crearYAsignarClasificacion() {
  const nombreClasif = document.getElementById('txt_nueva_clasificacion').value.trim();
  const patronRuta = document.getElementById('txt_patron_ruta').value.trim().toLowerCase();
  
  if (!nombreClasif) {
    alert('Escribe el nombre de la nueva clasificación.');
    return;
  }

  fetch('liquidacion.php', {
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
        const filas = document.querySelectorAll('.fila-ruta');
        let contador = 0;
        
        filas.forEach(row => {
          const ruta = row.dataset.ruta.toLowerCase();
          const vehiculo = row.dataset.vehiculo;
          if (ruta.includes(patronRuta)) {
            const sel = row.querySelector('.select-clasif-ruta');
            sel.value = nombreClasif.toLowerCase();
            
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

// Guardar tarifas dinámicamente
function configurarEventosTarifas() {
  document.addEventListener('change', function(e) {
    if (e.target.matches('.tarifa-input')) {
      const input = e.target;
      const card = input.closest('.tarjeta-tarifa-acordeon');
      const tipoVehiculo = card.dataset.vehiculo;
      const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
      const campo = input.dataset.campo.toLowerCase();
      const valor = parseFloat(input.value) || 0;
      
      fetch('liquidacion.php', {
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
          input.defaultValue = input.value;
          recalcularDesdePaneles();
        } else {
          input.value = input.defaultValue;
        }
      })
      .catch(error => {
        input.value = input.defaultValue;
      });
    }
  });
  
  document.querySelectorAll('.tarifa-input').forEach(input => {
    input.defaultValue = input.value;
  });
}

// Guardar clasificación de rutas
function guardarClasificacionRuta(ruta, vehiculo, clasificacion) {
  if (!clasificacion) return;
  fetch('liquidacion.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:new URLSearchParams({
      guardar_clasificacion:1,
      ruta:ruta,
      tipo_vehiculo:vehiculo,
      clasificacion:clasificacion.toLowerCase()
    })
  })
  .then(r=>r.text())
  .then(t=>{
    if (t.trim() !== 'ok') console.error('Error guardando clasificación:', t);
  });
}

// Guardar selección de columnas
function guardarSeleccionColumnas() {
  const desde = "<?= htmlspecialchars($desde) ?>";
  const hasta = "<?= htmlspecialchars($hasta) ?>";
  const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
  
  fetch('liquidacion.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      guardar_columnas_seleccionadas: 1,
      columnas: JSON.stringify(columnasSeleccionadasPaneles),
      desde: desde,
      hasta: hasta,
      empresa: empresa
    })
  })
  .then(r => r.text())
  .then(respuesta => {
    if (respuesta.trim() === 'ok') {
      mostrarNotificacion('✅ Selección de columnas guardada', 'success');
      // Recargar la página para aplicar cambios
      setTimeout(() => {
        window.location.reload();
      }, 1500);
    } else {
      mostrarNotificacion('❌ Error al guardar selección', 'error');
    }
  })
  .catch(error => {
    mostrarNotificacion('❌ Error de conexión', 'error');
  });
}

// Función para recalcular desde paneles
function recalcularDesdePaneles() {
  // Llamar a la función recalcular de la vista_tabla.php
  if (typeof recalcular === 'function') {
    recalcular();
  }
}

// Notificaciones
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
  
  setTimeout(() => {
    notificacion.remove();
  }, 3000);
}

// Función para que otras vistas puedan abrir paneles
function abrirPanelClasificacionRutas() {
  togglePanel('clasif-rutas');
}

// ===== INICIALIZACIÓN DE LOS PANELES =====
document.addEventListener('DOMContentLoaded', function() {
  configurarEventosTarifas();
  
  document.querySelectorAll('.select-clasif-ruta').forEach(sel=>{
    sel.addEventListener('change', function() {
      actualizarColorFila(this);
    });
  });
});
</script>