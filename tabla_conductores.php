<?php
// Extraer variables del array de datos
extract($datos_vista);
?>

<!-- Botón para abrir selector de columnas -->
<div class="mb-4 flex justify-end">
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

<script>
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

// Inicializar recalculo cuando se carga la vista
document.addEventListener('DOMContentLoaded', function() {
  recalcular();
});
</script>