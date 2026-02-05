<?php
// =======================================================
// 🔹 VISTA TABLA - COMPLETAMENTE AUTÓNOMA
// =======================================================
// Recibir datos desde liquidacion.php
extract($datos_vistas);

// =======================================================
// 🔹 FUNCIONES PHP QUE SOLO USA ESTA VISTA
// =======================================================
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

function obtenerClasificacionesDisponibles($conn) {
    return obtenerColumnasTarifas($conn);
}

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

// =======================================================
// 🔹 PROCESAMIENTO DE DATOS PARA ESTA VISTA
// =======================================================
$columnas_tarifas = obtenerColumnasTarifas($conn);
$clasificaciones_disponibles = obtenerClasificacionesDisponibles($conn);

// Cargar columnas seleccionadas desde cookie
$session_key = "columnas_seleccionadas_" . md5($empresaFiltro . $desde . $hasta);
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

// Empresas para el filtro
$empresas = [];
$resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
if ($resEmp) while ($r = $resEmp->fetch_assoc()) $empresas[] = $r['empresa'];

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
// 🔹 CSS ESPECÍFICO DE LA TABLA
// =======================================================
?>
<style>
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
  
  /* Estilos para columnas ocultas/visibles */
  .columna-oculta {
    display: none !important;
  }
  
  .columna-visualizada {
    display: table-cell !important;
  }
</style>

<!-- ======================================================= -->
<!-- 🔹 HTML DE LA TABLA -->
<!-- ======================================================= -->
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
      <span class="mx-2">•</span>
      <span class="font-medium">Columnas visibles:</span>
      <span class="bg-slate-100 px-2 py-1 rounded-lg font-semibold">
        <span id="contador-columnas-visibles"><?= count($columnas_seleccionadas) ?></span>/<?= count($clasificaciones_disponibles) ?>
      </span>
    </div>
  </div>
</header>

<main class="max-w-[1800px] mx-auto px-3 md:px-4 py-6">
  <div class="table-container-wrapper" id="tableContainerWrapper">
    
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
      <!-- Encabezado del panel central -->
      <div class="p-5 border-b border-slate-200">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <h3 class="text-xl font-semibold">🧑‍✈️ Resumen por Conductor</h3>
            <div id="contador-conductores" class="text-sm text-slate-500 mt-1">
              Mostrando <?= count($datos) ?> conductores • 
              <span id="contador-columnas-visibles-header"><?= count($columnas_seleccionadas) ?></span> columnas visibles
            </div>
          </div>
          <div class="flex items-center gap-2">
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
                  
                  $visible = in_array($clasif, $columnas_seleccionadas);
                  $clase_visibilidad = $visible ? 'columna-visualizada' : 'columna-oculta';
                  
                  $colorMap = [
                      'bg-emerald-100' => '#d1fae5', 'text-emerald-700' => '#047857', 'border-emerald-200' => '#a7f3d0',
                      'bg-amber-100' => '#fef3c7', 'text-amber-800' => '#92400e', 'border-amber-200' => '#fcd34d',
                      'bg-slate-200' => '#e2e8f0', 'text-slate-800' => '#1e293b', 'border-slate-300' => '#cbd5e1',
                      'bg-fuchsia-100' => '#fae8ff', 'text-fuchsia-700' => '#a21caf', 'border-fuchsia-200' => '#f5d0fe',
                      'bg-cyan-100' => '#cffafe', 'text-cyan-800' => '#155e75', 'border-cyan-200' => '#a5f3fc',
                      'bg-indigo-100' => '#e0e7ff', 'text-indigo-700' => '#4338ca', 'border-indigo-200' => '#c7d2fe',
                      'bg-teal-100' => '#ccfbf1', 'text-teal-700' => '#0f766e', 'border-teal-200' => '#99f6e4',
                      'bg-rose-100' => '#ffe4e6', 'text-rose-700' => '#be123c', 'border-rose-200' => '#fecdd3',
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
              $color_vehiculo = obtenerColorVehiculo($info['vehiculo']);
            ?>
              <tr data-vehiculo="<?= htmlspecialchars($info['vehiculo']) ?>" 
                  data-conductor="<?= htmlspecialchars($conductor) ?>" 
                  data-conductor-normalizado="<?= htmlspecialchars(mb_strtolower($conductor)) ?>"
                  data-pagado="<?= (int)($info['pagado'] ?? 0) ?>"
                  data-sin-clasificar="<?= $rutasSinClasificar ?>"
                  class="hover:bg-blue-50/40 transition-colors <?php echo $rutasSinClasificar > 0 ? 'alerta-sin-clasificar' : ''; ?>">
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
                    style="min-width: 80px; background-color: <?= $bg_cell_color ?>; color: <?= $text_cell_color ?>; border-left: 1px solid <?= str_replace('bg-', '#', $estilo['bg']) ?>30; border-right: 1px solid <?= str_replace('bg-', '#', $estilo['bg']) ?>30;">
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

<!-- ======================================================= -->
<!-- 🔹 JAVASCRIPT ESPECÍFICO DE LA TABLA -->
<!-- ======================================================= -->
<script>
// Variables globales de esta vista
let columnasSeleccionadas = <?= json_encode($columnas_seleccionadas) ?>;
const RANGO_DESDE = <?= json_encode($desde) ?>;
const RANGO_HASTA = <?= json_encode($hasta) ?>;
const RANGO_EMP   = <?= json_encode($empresaFiltro) ?>;
const CONDUCTORES_LIST = <?= json_encode(array_keys($datos), JSON_UNESCAPED_UNICODE); ?>;

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
  // Esta función obtiene las tarifas de los paneles
  // Como los paneles están en otra vista, necesitamos una forma de comunicarnos
  // Por ahora, devolvemos un objeto vacío (se completará cuando haya paneles)
  return {};
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

  filas.forEach(fila => {
    if (fila.style.display === 'none') return;

    const veh = fila.dataset.vehiculo;
    const tarifasVeh = tarifas[veh] || {};
    const todasColumnas = <?= json_encode($clasificaciones_disponibles) ?>;

    let totalFila = 0;
    
    todasColumnas.forEach(columna => {
      const celda = fila.querySelector(`td[data-columna="${columna}"]`);
      const cantidad = parseInt(celda?.textContent || 0);
      const tarifa = tarifasVeh[columna] || 0;
      totalFila += cantidad * tarifa;
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

// ===== FUNCIONES PARA RUTAS SIN CLASIFICAR =====
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
  // Esta función se completará cuando incluyamos vista_paneles.php
  console.log('Función para abrir panel de clasificación de rutas');
}

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', function() {
  // Click en conductor → abre modal de viajes
  document.querySelectorAll('.conductor-link').forEach(btn=>{
    btn.addEventListener('click', (e)=>{
      e.preventDefault();
      const nombre = btn.textContent.trim().replace('⚠️', '').trim();
      // Esta función se definirá en vista_modal.php
      if (typeof abrirModalViajes === 'function') {
        abrirModalViajes(nombre);
      }
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