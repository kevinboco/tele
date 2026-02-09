<?php
// Extraer variables del array de datos
extract($datos_vista);

// Función para obtener presupuesto de conductor
function obtenerPresupuestoConductor($conn, $conductor, $empresa) {
    $conductor = $conn->real_escape_string(trim($conductor));
    $empresa = $conn->real_escape_string(trim($empresa));
    
    $sql = "SELECT presupuesto FROM conductor_presupuesto 
            WHERE conductor = '$conductor' AND empresa = '$empresa'";
    
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        return (float)$row['presupuesto'];
    }
    return 0.00;
}
?>

<!-- Botón para abrir selector de columnas -->
<div class="mb-4 flex justify-between items-center">
  <div>
    <button id="btnGestionPresupuestos" 
            class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-gradient-to-r from-green-500 to-emerald-500 text-white hover:from-green-600 hover:to-emerald-600 transition shadow-md hover:shadow-lg">
      <span>💰</span>
      <span class="text-sm font-medium">Gestionar Presupuestos</span>
    </button>
  </div>
  
  <div>
    <button onclick="togglePanel('selector-columnas')" 
            class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-gradient-to-r from-purple-500 to-indigo-500 text-white hover:from-purple-600 hover:to-indigo-600 transition shadow-md hover:shadow-lg">
      <span>📊</span>
      <span class="text-sm font-medium">Seleccionar columnas</span>
    </button>
  </div>
</div>

<!-- Resumen de Presupuestos -->
<div id="resumenPresupuestos" class="hidden mb-6 bg-gradient-to-r from-green-50 to-emerald-50 border border-emerald-200 rounded-2xl p-4">
  <div class="flex items-center justify-between mb-4">
    <div class="flex items-center gap-3">
      <span class="text-2xl">💰</span>
      <div>
        <h4 class="font-bold text-emerald-900">Resumen de Presupuestos</h4>
        <p class="text-sm text-emerald-700/80">Estado actual vs presupuestos asignados</p>
      </div>
    </div>
    <button onclick="document.getElementById('resumenPresupuestos').classList.add('hidden')" 
            class="text-emerald-600 hover:text-emerald-800">
      ✕
    </button>
  </div>
  
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
    <div class="bg-white p-4 rounded-xl border border-emerald-100">
      <div class="text-sm text-emerald-600 mb-1">Conductores con Presupuesto</div>
      <div id="contadorConPresupuesto" class="text-2xl font-bold text-emerald-700">0</div>
    </div>
    
    <div class="bg-white p-4 rounded-xl border border-green-100">
      <div class="text-sm text-green-600 mb-1">Dentro del Presupuesto</div>
      <div id="contadorDentroPresupuesto" class="text-2xl font-bold text-green-700">0</div>
    </div>
    
    <div class="bg-white p-4 rounded-xl border border-amber-100">
      <div class="text-sm text-amber-600 mb-1">Cerca del Límite (80-100%)</div>
      <div id="contadorCercaLimite" class="text-2xl font-bold text-amber-700">0</div>
    </div>
    
    <div class="bg-white p-4 rounded-xl border border-red-100">
      <div class="text-sm text-red-600 mb-1">Excedidos</div>
      <div id="contadorExcedidos" class="text-2xl font-bold text-red-700">0</div>
    </div>
  </div>
  
  <div class="mt-4 text-xs text-emerald-600">
    <span id="totalExcedidoMonto">$0</span> excedidos en total • 
    <span id="totalPresupuestoAsignado">$0</span> presupuesto asignado
  </div>
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
          <span id="chipPresupuesto" class="hidden inline-flex items-center gap-2 rounded-full border border-green-200 bg-green-50 px-4 py-2 text-green-700 font-semibold text-sm">
            💰 Presupuesto: <span id="total_presupuesto">$0</span>
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
            <!-- COLUMNAS DE ESTADO Y PRESUPUESTO -->
            <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 70px;">
              Estado
            </th>
            <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 150px;">
              Presupuesto
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
          
          // Calcular total liquidado
          $total_conductor = 0;
          foreach ($clasificaciones_disponibles as $clasif) {
              $cantidad = (int)($info[$clasif] ?? 0);
              
              if (isset($tarifas_guardadas[$info['vehiculo']])) {
                  $tarifa = $tarifas_guardadas[$info['vehiculo']];
                  $tarifa_valor = isset($tarifa[$clasif]) ? (float)$tarifa[$clasif] : 0;
                  $total_conductor += $cantidad * $tarifa_valor;
              }
          }
          $total_conductor += (int)($info['pagado'] ?? 0);
          
          // Obtener presupuesto
          $presupuesto = obtenerPresupuestoConductor($conn, $conductor, $empresaFiltro);
          $porcentaje = $presupuesto > 0 ? ($total_conductor / $presupuesto) * 100 : 0;
          $diferencia = $total_conductor - $presupuesto;
          
          // Determinar estado
          if ($presupuesto > 0) {
              if ($porcentaje >= 100) {
                  $estado_presupuesto = 'excedido';
                  $icono_estado = '🔴';
                  $clase_estado = 'bg-red-100 text-red-700 border-red-200';
                  $clase_barra = 'bg-red-500';
                  $titulo_estado = 'PRESUPUESTO EXCEDIDO';
              } elseif ($porcentaje >= 80) {
                  $estado_presupuesto = 'proximo';
                  $icono_estado = '🟡';
                  $clase_estado = 'bg-amber-100 text-amber-700 border-amber-200';
                  $clase_barra = 'bg-amber-500';
                  $titulo_estado = 'CERCA DEL LÍMITE';
              } else {
                  $estado_presupuesto = 'normal';
                  $icono_estado = '🟢';
                  $clase_estado = 'bg-emerald-100 text-emerald-700 border-emerald-200';
                  $clase_barra = 'bg-emerald-500';
                  $titulo_estado = 'DENTRO DEL PRESUPUESTO';
              }
          } else {
              $estado_presupuesto = 'sin_presupuesto';
              $icono_estado = '⚪';
              $clase_estado = 'bg-gray-100 text-gray-700 border-gray-200';
              $clase_barra = 'bg-gray-300';
              $titulo_estado = 'SIN PRESUPUESTO ASIGNADO';
          }
        ?>
          <tr data-vehiculo="<?= htmlspecialchars($info['vehiculo']) ?>" 
              data-conductor="<?= htmlspecialchars($conductor) ?>" 
              data-conductor-normalizado="<?= htmlspecialchars(mb_strtolower($conductor)) ?>"
              data-pagado="<?= (int)($info['pagado'] ?? 0) ?>"
              data-sin-clasificar="<?= $rutasSinClasificar ?>"
              data-presupuesto="<?= $presupuesto ?>"
              data-total="<?= $total_conductor ?>"
              data-porcentaje="<?= $porcentaje ?>"
              class="hover:bg-blue-50/40 transition-colors <?php echo $rutasSinClasificar > 0 ? 'alerta-sin-clasificar' : ''; ?> <?php echo $estado_presupuesto == 'excedido' ? 'bg-red-50/20' : ''; ?>">
            
            <!-- CELDA DE ESTADO DEL PRESUPUESTO -->
            <td class="px-4 py-3 text-center" style="min-width: 70px;">
              <div class="flex flex-col items-center justify-center gap-1" 
                   title="<?= $titulo_estado ?><?= $presupuesto > 0 ? "\nLiquidado: $" . number_format($total_conductor, 0) . "\nPresupuesto: $" . number_format($presupuesto, 0) . "\n" . number_format($porcentaje, 1) . "%" : '' ?>">
                <span class="text-xl"><?= $icono_estado ?></span>
                <?php if ($rutasSinClasificar > 0): ?>
                  <span class="text-xs bg-amber-100 text-amber-800 px-1 py-0.5 rounded font-bold">
                    ⚠️<?= $rutasSinClasificar ?>
                  </span>
                <?php endif; ?>
              </div>
            </td>
            
            <!-- CELDA DE PRESUPUESTO CON BARRA DE PROGRESO -->
            <td class="px-4 py-3" style="min-width: 150px;">
              <div class="flex flex-col gap-1 cursor-pointer hover:opacity-90 transition"
                   onclick="editarPresupuesto('<?= htmlspecialchars($conductor) ?>', '<?= htmlspecialchars($empresaFiltro) ?>', <?= $presupuesto ?>)"
                   title="Click para editar presupuesto">
                
                <?php if ($presupuesto > 0): ?>
                  <!-- Barra de progreso -->
                  <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                    <div class="h-full <?= $clase_barra ?> rounded-full transition-all duration-500" 
                         style="width: <?= min($porcentaje, 100) ?>%"></div>
                  </div>
                  
                  <!-- Información numérica -->
                  <div class="flex justify-between text-xs mt-1">
                    <span class="font-medium <?= $porcentaje >= 100 ? 'text-red-600' : ($porcentaje >= 80 ? 'text-amber-600' : 'text-emerald-600') ?>">
                      <?= number_format($porcentaje, 1) ?>%
                    </span>
                    <span class="text-gray-600">
                      $<?= number_format($total_conductor, 0) ?>/<?= number_format($presupuesto, 0) ?>
                    </span>
                  </div>
                  
                  <?php if ($diferencia > 0): ?>
                    <div class="text-[10px] text-red-600 font-semibold mt-0.5 text-center">
                      +$<?= number_format($diferencia, 0) ?>
                    </div>
                  <?php endif; ?>
                  
                <?php else: ?>
                  <!-- Sin presupuesto asignado -->
                  <div class="text-center py-1">
                    <span class="text-xs text-gray-500 italic">Sin presupuesto</span>
                    <div class="text-[10px] text-gray-400 mt-0.5">Click para asignar</div>
                  </div>
                <?php endif; ?>
              </div>
            </td>
            
            <!-- NOMBRE DEL CONDUCTOR -->
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
            
            <!-- TIPO DE VEHÍCULO -->
            <td class="px-4 py-3 text-center" style="min-width: 120px;">
              <span class="inline-block <?= $claseVehiculo ?> px-3 py-1.5 rounded-lg text-xs font-medium border <?= $color_vehiculo['border'] ?> <?= $color_vehiculo['text'] ?> <?= $color_vehiculo['bg'] ?>">
                <?= htmlspecialchars($info['vehiculo']) ?>
                <?php if ($esMensual): ?>
                  <span class="ml-1">📅</span>
                <?php endif; ?>
              </span>
            </td>
            
            <!-- COLUMNAS DE CLASIFICACIONES -->
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
                     readonly dir="ltr"
                     value="$<?= number_format($total_conductor, 0) ?>">
            </td>

            <!-- Pagado -->
            <td class="px-4 py-3" style="min-width: 120px;">
              <input type="text"
                     class="pagado w-full rounded-xl border border-emerald-200 px-3 py-2 text-right bg-emerald-50 outline-none whitespace-nowrap tabular-nums"
                     readonly dir="ltr"
                     value="$<?= number_format((int)($info['pagado'] ?? 0), 0, ',', '.') ?>">
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

<!-- Modal para editar presupuesto -->
<div id="modalEditarPresupuesto" class="viajes-backdrop">
  <div class="viajes-card" style="width: min(500px, 94vw);">
    <div class="viajes-header">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold flex items-center gap-2">
          💰 Editar Presupuesto
        </h3>
        <button class="viajes-close text-slate-600" onclick="cerrarModalPresupuesto()" title="Cerrar">
          ✕
        </button>
      </div>
    </div>
    
    <div class="viajes-body p-4">
      <div class="mb-4">
        <div class="flex items-center gap-2 mb-2">
          <span class="text-sm font-medium">Conductor:</span>
          <span id="modalConductorNombre" class="font-semibold text-blue-700"></span>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-sm font-medium">Empresa:</span>
          <span id="modalEmpresaNombre" class="font-semibold text-blue-700"></span>
        </div>
      </div>
      
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">Presupuesto ($)</label>
          <input type="number" id="inputNuevoPresupuesto" min="0" step="1000" 
                 class="w-full rounded-xl border border-slate-300 px-3 py-3 text-lg outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition text-right font-semibold"
                 placeholder="Ej: 500000">
        </div>
        
        <div class="bg-slate-50 p-4 rounded-lg">
          <div class="text-sm text-slate-600 mb-3">Información actual:</div>
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div class="text-slate-500">Total liquidado:</div>
            <div id="modalTotalLiquidado" class="font-semibold text-right">$0</div>
            
            <div class="text-slate-500">Presupuesto actual:</div>
            <div id="modalPresupuestoActual" class="font-semibold text-right">$0</div>
            
            <div class="text-slate-500">Diferencia:</div>
            <div id="modalDiferencia" class="font-semibold text-right">$0</div>
            
            <div class="text-slate-500">Porcentaje usado:</div>
            <div id="modalPorcentaje" class="font-semibold text-right">0%</div>
          </div>
        </div>
        
        <div id="modalAlert" class="hidden p-3 rounded-lg text-sm"></div>
        
        <div class="flex gap-2 pt-2">
          <button onclick="guardarPresupuesto()" 
                  class="flex-1 rounded-xl bg-blue-600 text-white py-3 font-semibold shadow hover:bg-blue-700 active:bg-blue-800 focus:ring-4 focus:ring-blue-200 transition">
            💾 Guardar Presupuesto
          </button>
          <button onclick="eliminarPresupuesto()" 
                  class="rounded-xl bg-slate-200 text-slate-700 px-4 py-3 font-semibold hover:bg-slate-300 active:bg-slate-400 transition">
            🗑️ Eliminar
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// ===== SISTEMA DE PRESUPUESTOS =====

// Variables globales
let conductorActualPresupuesto = null;
let empresaActualPresupuesto = null;

// Función para abrir modal de edición
function editarPresupuesto(conductor, empresa, presupuestoActual) {
  conductorActualPresupuesto = conductor;
  empresaActualPresupuesto = empresa;
  
  // Buscar datos del conductor en la tabla
  const fila = document.querySelector(`tr[data-conductor="${conductor}"]`);
  const totalLiquidado = parseFloat(fila.dataset.total) || 0;
  const porcentajeActual = parseFloat(fila.dataset.porcentaje) || 0;
  
  // Actualizar modal
  document.getElementById('modalConductorNombre').textContent = conductor;
  document.getElementById('modalEmpresaNombre').textContent = empresa || 'Todas';
  document.getElementById('inputNuevoPresupuesto').value = presupuestoActual > 0 ? presupuestoActual : '';
  document.getElementById('modalTotalLiquidado').textContent = formatNumber(totalLiquidado);
  document.getElementById('modalPresupuestoActual').textContent = formatNumber(presupuestoActual);
  document.getElementById('modalDiferencia').textContent = formatNumber(totalLiquidado - presupuestoActual);
  document.getElementById('modalPorcentaje').textContent = porcentajeActual.toFixed(1) + '%';
  
  // Actualizar alerta
  const alertDiv = document.getElementById('modalAlert');
  alertDiv.className = 'hidden p-3 rounded-lg text-sm';
  
  if (porcentajeActual >= 100) {
    alertDiv.className = 'bg-red-100 text-red-700 border border-red-200';
    alertDiv.textContent = `⚠️ Este conductor ya excedió el presupuesto en $${formatNumber(totalLiquidado - presupuestoActual)}`;
    alertDiv.classList.remove('hidden');
  } else if (porcentajeActual >= 80) {
    alertDiv.className = 'bg-amber-100 text-amber-700 border border-amber-200';
    alertDiv.textContent = `⚠️ Cerca del límite (${porcentajeActual.toFixed(1)}% usado)`;
    alertDiv.classList.remove('hidden');
  } else if (presupuestoActual > 0) {
    alertDiv.className = 'bg-emerald-100 text-emerald-700 border border-emerald-200';
    alertDiv.textContent = `✅ Dentro del presupuesto (${porcentajeActual.toFixed(1)}% usado)`;
    alertDiv.classList.remove('hidden');
  }
  
  // Mostrar modal
  document.getElementById('modalEditarPresupuesto').classList.add('show');
  document.getElementById('inputNuevoPresupuesto').focus();
  document.getElementById('inputNuevoPresupuesto').select();
}

// Función para cerrar modal
function cerrarModalPresupuesto() {
  document.getElementById('modalEditarPresupuesto').classList.remove('show');
  conductorActualPresupuesto = null;
  empresaActualPresupuesto = null;
}

// Función para guardar presupuesto
function guardarPresupuesto() {
  const nuevoPresupuesto = parseFloat(document.getElementById('inputNuevoPresupuesto').value) || 0;
  
  if (nuevoPresupuesto < 0) {
    alert('El presupuesto no puede ser negativo');
    return;
  }
  
  // Enviar al servidor
  fetch(window.location.pathname, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `guardar_presupuesto_conductor=1&conductor=${encodeURIComponent(conductorActualPresupuesto)}&empresa=${encodeURIComponent(empresaActualPresupuesto)}&presupuesto=${nuevoPresupuesto}`
  })
  .then(response => response.text())
  .then(result => {
    if (result === 'ok') {
      // Recargar la página para actualizar datos
      window.location.reload();
    } else {
      alert('Error al guardar: ' + result);
    }
  })
  .catch(error => {
    alert('Error de conexión: ' + error);
  });
}

// Función para eliminar presupuesto
function eliminarPresupuesto() {
  if (confirm('¿Estás seguro de eliminar el presupuesto de ' + conductorActualPresupuesto + '?')) {
    fetch(window.location.pathname, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `guardar_presupuesto_conductor=1&conductor=${encodeURIComponent(conductorActualPresupuesto)}&empresa=${encodeURIComponent(empresaActualPresupuesto)}&presupuesto=0`
    })
    .then(response => response.text())
    .then(result => {
      if (result === 'ok') {
        window.location.reload();
      } else {
        alert('Error al eliminar: ' + result);
      }
    });
  }
}

// Botón de gestión de presupuestos
document.getElementById('btnGestionPresupuestos').addEventListener('click', function() {
  // Mostrar resumen y calcular estadísticas
  const resumen = document.getElementById('resumenPresupuestos');
  const filas = document.querySelectorAll('#tabla_conductores_body tr');
  
  let conPresupuesto = 0;
  let dentroPresupuesto = 0;
  let cercaLimite = 0;
  let excedidos = 0;
  let totalPresupuesto = 0;
  let totalExcedido = 0;
  
  filas.forEach(fila => {
    const presupuesto = parseFloat(fila.dataset.presupuesto) || 0;
    const porcentaje = parseFloat(fila.dataset.porcentaje) || 0;
    const total = parseFloat(fila.dataset.total) || 0;
    
    if (presupuesto > 0) {
      conPresupuesto++;
      totalPresupuesto += presupuesto;
      
      if (porcentaje >= 100) {
        excedidos++;
        totalExcedido += (total - presupuesto);
      } else if (porcentaje >= 80) {
        cercaLimite++;
      } else {
        dentroPresupuesto++;
      }
    }
  });
  
  // Actualizar contadores
  document.getElementById('contadorConPresupuesto').textContent = conPresupuesto;
  document.getElementById('contadorDentroPresupuesto').textContent = dentroPresupuesto;
  document.getElementById('contadorCercaLimite').textContent = cercaLimite;
  document.getElementById('contadorExcedidos').textContent = excedidos;
  document.getElementById('totalPresupuestoAsignado').textContent = '$' + formatNumber(totalPresupuesto);
  document.getElementById('totalExcedidoMonto').textContent = '$' + formatNumber(totalExcedido);
  
  // Mostrar/ocultar chip de presupuesto
  const chipPresupuesto = document.getElementById('chipPresupuesto');
  if (conPresupuesto > 0) {
    chipPresupuesto.classList.remove('hidden');
    document.getElementById('total_presupuesto').textContent = '$' + formatNumber(totalPresupuesto);
  } else {
    chipPresupuesto.classList.add('hidden');
  }
  
  // Mostrar resumen
  resumen.classList.remove('hidden');
});

// Función para formatear números
function formatNumber(num) {
  return new Intl.NumberFormat('es-CO').format(Math.round(num || 0));
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

function recalcular(){
  const tarifas = getTarifas();
  const filas = document.querySelectorAll('#tabla_conductores_body tr');
  
  let totalViajes = 0;
  let totalPagado = 0;
  let totalFaltante = 0;
  let totalPresupuesto = 0;

  filas.forEach(fila => {
    if (fila.style.display === 'none') return;

    const veh = fila.dataset.vehiculo;
    const tarifasVeh = tarifas[veh] || {};
    const todasColumnas = <?= json_encode($clasificaciones_disponibles) ?>;
    
    const presupuesto = parseFloat(fila.dataset.presupuesto) || 0;
    totalPresupuesto += presupuesto;

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
    if (inpTotal) inpTotal.value = '$' + formatNumber(totalFila);

    const inpFalt = fila.querySelector('input.faltante');
    if (inpFalt) inpFalt.value = formatNumber(faltante);

    totalViajes += totalFila;
    totalPagado += pagado;
    totalFaltante += faltante;
  });

  document.getElementById('total_viajes').innerText = formatNumber(totalViajes);
  document.getElementById('total_general').innerText = '$' + formatNumber(totalViajes);
  document.getElementById('total_pagado').innerText = '$' + formatNumber(totalPagado);
  document.getElementById('total_faltante').innerText = formatNumber(totalFaltante);
  
  // Actualizar chip de presupuesto si existe
  const chipPresupuesto = document.getElementById('chipPresupuesto');
  if (chipPresupuesto && !chipPresupuesto.classList.contains('hidden')) {
    document.getElementById('total_presupuesto').textContent = '$' + formatNumber(totalPresupuesto);
  }
}

// Inicializar recalculo cuando se carga la vista
document.addEventListener('DOMContentLoaded', function() {
  recalcular();
  
  // Cerrar modal al hacer clic fuera
  document.getElementById('modalEditarPresupuesto').addEventListener('click', function(e) {
    if (e.target === this) {
      cerrarModalPresupuesto();
    }
  });
});
</script>