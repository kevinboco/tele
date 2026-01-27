<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

/* =======================================================
   🔹 AGREGAR NUEVA TARIFA/CLASIFICACIÓN
======================================================= */
if (isset($_POST['agregar_nueva_tarifa'])) {
    $nombre = trim($conn->real_escape_string($_POST['nombre']));
    
    if (empty($nombre)) {
        echo "error: nombre vacío";
        exit;
    }
    
    // Convertir a nombre de columna válido
    $columna = strtolower(preg_replace('/[^a-z0-9]/', '', $nombre));
    
    if (empty($columna)) {
        echo "error: nombre inválido";
        exit;
    }
    
    // Verificar si ya existe
    $check = $conn->query("SHOW COLUMNS FROM tarifas LIKE '$columna'");
    if ($check && $check->num_rows > 0) {
        echo "error: ya existe";
        exit;
    }
    
    // Agregar columna a la tabla tarifas
    $sql = "ALTER TABLE tarifas ADD COLUMN `$columna` DECIMAL(10,2) DEFAULT 0.00";
    if ($conn->query($sql)) {
        echo "ok:$columna:$nombre";
    } else {
        echo "error: " . $conn->error;
    }
    exit;
}

/* =======================================================
   🔹 Guardar tarifas por vehículo y empresa (AJAX)
======================================================= */
if (isset($_POST['guardar_tarifa'])) {
    $empresa  = $conn->real_escape_string($_POST['empresa']);
    $vehiculo = $conn->real_escape_string($_POST['tipo_vehiculo']);
    $campo    = $conn->real_escape_string($_POST['campo']);
    $valor    = (int)$_POST['valor'];

    $conn->query("INSERT IGNORE INTO tarifas (empresa, tipo_vehiculo) VALUES ('$empresa', '$vehiculo')");
    $sql = "UPDATE tarifas SET `$campo` = $valor WHERE empresa='$empresa' AND tipo_vehiculo='$vehiculo'";
    echo $conn->query($sql) ? "ok" : "error: " . $conn->error;
    exit;
}

/* =======================================================
   🔹 Guardar CLASIFICACIÓN de rutas (manual) - AJAX
   🔴 ESTE ES EL QUE FALLA - LO VAMOS A CORREGIR
======================================================= */
if (isset($_POST['guardar_clasificacion'])) {
    $ruta       = $conn->real_escape_string($_POST['ruta']);
    $vehiculo   = $conn->real_escape_string($_POST['tipo_vehiculo']);
    $clasif     = $conn->real_escape_string($_POST['clasificacion']);
    
    // Debug: ver qué datos llegan
    error_log("Guardando clasificación: ruta=$ruta, vehiculo=$vehiculo, clasif=$clasif");

    $sql = "INSERT INTO ruta_clasificacion (ruta, tipo_vehiculo, clasificacion)
            VALUES ('$ruta', '$vehiculo', '$clasif')
            ON DUPLICATE KEY UPDATE clasificacion = VALUES(clasificacion)";
    
    $result = $conn->query($sql);
    
    if ($result) {
        echo "ok";
        error_log("Clasificación guardada correctamente");
    } else {
        echo "error: " . $conn->error;
        error_log("Error guardando clasificación: " . $conn->error);
    }
    exit;
}

/* =======================================================
   🔹 Obtener TODAS las columnas de tarifas para usar como clasificaciones
======================================================= */
function obtenerColumnasTarifas($conn) {
    $columnas = [];
    $res = $conn->query("SHOW COLUMNS FROM tarifas");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $field = $row['Field'];
            // Solo tomar columnas que no son id, empresa o tipo_vehiculo
            if (!in_array($field, ['id', 'empresa', 'tipo_vehiculo'])) {
                $columnas[] = $field;
            }
        }
    }
    return $columnas;
}

/* =======================================================
   🔹 Resto del código (viajes_conductor, formulario inicial, etc.)
   🔹 MANTENIENDO TU DISEÑO ORIGINAL
======================================================= */

// [TODO EL RESTO DE TU CÓDIGO ORIGINAL AQUÍ - SIN CAMBIAR EL DISEÑO]
// Solo agregaré las modificaciones necesarias para la nueva funcionalidad

// ... [TU CÓDIGO ORIGINAL COMPLETO] ...

?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Liquidación de Conductores</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  /* TUS ESTILOS ORIGINALES */
</style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800">

  <!-- ENCABEZADO ORIGINAL -->
  <header class="max-w-[1600px] mx-auto px-3 md:px-4 pt-6">
    <!-- ... TU ENCABEZADO ORIGINAL ... -->
  </header>

  <!-- CONTENIDO - MANTENIENDO TU DISEÑO ORIGINAL -->
  <main class="max-w-[1600px] mx-auto px-3 md:px-4 py-6">
    <div class="grid grid-cols-1 xl:grid-cols-[1fr_2.6fr_0.9fr] gap-5 items-start">

      <!-- Columna 1: Tarifas + Filtro + Clasificación de rutas -->
      <section class="space-y-5">

        <!-- 🔹 TARJETAS DE TARIFAS ORIGINALES CON BOTÓN PARA AGREGAR -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
          <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <span>🚐 Tarifas por Tipo de Vehículo</span>
            <!-- BOTÓN PARA AGREGAR NUEVA TARIFA -->
            <button onclick="mostrarModalNuevaTarifa()"
                    class="ml-auto text-xs px-3 py-1 rounded-full bg-blue-100 text-blue-700 border border-blue-200 hover:bg-blue-200">
              + Nueva tarifa
            </button>
          </h3>

          <!-- MODAL PARA AGREGAR NUEVA TARIFA -->
          <div id="modalNuevaTarifa" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
            <div class="bg-white rounded-2xl p-6 max-w-md w-full">
              <h4 class="text-lg font-semibold mb-4">➕ Agregar nueva tarifa/clasificación</h4>
              <div class="space-y-3">
                <div>
                  <label class="block text-sm font-medium mb-1">Nombre de la tarifa</label>
                  <input type="text" id="nombreNuevaTarifa" 
                         placeholder="Ej: Riohacha, Local, Express..." 
                         class="w-full rounded-xl border border-slate-300 px-3 py-2">
                  <p class="text-xs text-slate-500 mt-1">
                    Esta tarifa aparecerá en todos los vehículos y en las clasificaciones de rutas
                  </p>
                </div>
                <div class="flex gap-2 pt-2">
                  <button onclick="cerrarModalNuevaTarifa()"
                          class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-slate-700">
                    Cancelar
                  </button>
                  <button onclick="agregarNuevaTarifa()"
                          class="flex-1 rounded-xl bg-blue-600 text-white px-3 py-2 font-semibold">
                    Agregar
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- TARJETAS DE TARIFAS ORIGINALES -->
          <div id="tarifas_grid" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php 
            // Obtener todas las columnas/tarifas
            $columnasTarifas = obtenerColumnasTarifas($conn);
            
            foreach ($vehiculos as $veh):
              $t = $tarifas_guardadas[$veh] ?? [];
            ?>
            <div class="tarjeta-tarifa rounded-2xl border border-slate-200 p-4 shadow-sm bg-slate-50"
                 data-vehiculo="<?= htmlspecialchars($veh) ?>">

              <div class="flex items-center justify-between mb-3">
                <div class="text-base font-semibold"><?= htmlspecialchars($veh) ?></div>
                <span class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-700 border border-blue-200">Config</span>
              </div>

              <?php if ($veh === "Carrotanque"): ?>
                <!-- TARIFAS PARA CARROTANQUE -->
                <?php 
                // Solo mostrar carrotanque y siapana para Carrotanque
                $mostrarCarrotanque = array_intersect($columnasTarifas, ['carrotanque', 'siapana']);
                foreach ($mostrarCarrotanque as $columna):
                  $valor = isset($t[$columna]) ? (float)$t[$columna] : 0;
                  $nombreMostrar = ucfirst($columna);
                ?>
                <label class="block mb-3">
                  <span class="block text-sm font-medium mb-1"><?= htmlspecialchars($nombreMostrar) ?></span>
                  <input type="number" step="1000" value="<?= (int)$valor ?>"
                         data-campo="<?= htmlspecialchars($columna) ?>"
                         class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-white outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition"
                         oninput="recalcular()">
                </label>
                <?php endforeach; ?>
              <?php else: ?>
                <!-- TARIFAS PARA OTROS VEHÍCULOS -->
                <?php 
                // Para otros vehículos, mostrar todas excepto 'carrotanque'
                $mostrarOtros = array_filter($columnasTarifas, function($col) {
                  return $col !== 'carrotanque';
                });
                
                foreach ($mostrarOtros as $columna):
                  $valor = isset($t[$columna]) ? (float)$t[$columna] : 0;
                  $nombreMostrar = ucfirst($columna);
                ?>
                <label class="block mb-3">
                  <span class="block text-sm font-medium mb-1"><?= htmlspecialchars($nombreMostrar) ?></span>
                  <input type="number" step="1000" value="<?= (int)$valor ?>"
                         data-campo="<?= htmlspecialchars($columna) ?>"
                         class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-white outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition"
                         oninput="recalcular()">
                </label>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- FILTRO ORIGINAL -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
          <!-- ... TU FILTRO ORIGINAL ... -->
        </div>

        <!-- 🔹 PANEL DE CLASIFICACIÓN DE RUTAS - CORREGIDO -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
          <h5 class="text-base font-semibold mb-3 flex items-center justify-between">
            <span>🧭 Clasificación de Rutas</span>
            <span class="text-xs text-slate-500">Se guarda en BD</span>
          </h5>
          
          <p class="text-xs text-slate-500 mb-3">
            Ajusta qué tipo es cada ruta. Las opciones vienen de las tarifas configuradas.
          </p>

          <div class="flex flex-col gap-2 mb-3 md:flex-row md:items-end">
            <div class="flex-1">
              <label class="block text-xs font-medium mb-1">Texto que debe contener la ruta</label>
              <input id="txt_patron_ruta" type="text"
                     class="w-full rounded-xl border border-slate-300 px-3 py-1.5 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500"
                     placeholder="Ej: Riohacha, Uribia-Nazareth, Siapana...">
            </div>
            <div>
              <label class="block text-xs font-medium mb-1">Clasificación</label>
              <select id="sel_clasif_masiva"
                      class="rounded-xl border border-slate-300 px-3 py-1.5 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500">
                <option value="">-- Selecciona --</option>
                <?php foreach($columnasTarifas as $columna): ?>
                  <option value="<?= htmlspecialchars($columna) ?>"><?= htmlspecialchars(ucfirst($columna)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="button"
                    onclick="aplicarClasificacionMasiva()"
                    class="mt-2 md:mt-0 inline-flex items-center justify-center rounded-xl bg-purple-600 text-white px-4 py-2 text-sm font-semibold hover:bg-purple-700 active:bg-purple-800 focus:ring-4 focus:ring-purple-200">
              ⚙️ Aplicar a coincidentes
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
              <?php foreach($rutasUnicas as $info): ?>
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
                            data-vehiculo="<?= htmlspecialchars($info['vehiculo']) ?>">
                      <option value="">Sin clasificar</option>
                      <?php foreach($columnasTarifas as $columna): ?>
                        <option value="<?= htmlspecialchars($columna) ?>" 
                                <?= $info['clasificacion']===$columna ? 'selected' : '' ?>>
                          <?= htmlspecialchars(ucfirst($columna)) ?>
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
            Después de cambiar clasificaciones, vuelve a darle <strong>Filtrar</strong> para recalcular la tabla de conductores.
          </p>
        </div>
      </section>

      <!-- 🔹 COLUMNAS 2 y 3 ORIGINALES (RESUMEN POR CONDUCTOR Y PANEL VIAJES) -->
      <!-- ... TU CÓDIGO ORIGINAL COMPLETO AQUÍ ... -->

    </div>
  </main>

  <script>
    // ===== FUNCIONES PARA AGREGAR NUEVAS TARIFAS =====
    function mostrarModalNuevaTarifa() {
      document.getElementById('modalNuevaTarifa').classList.remove('hidden');
      document.getElementById('modalNuevaTarifa').classList.add('flex');
      document.getElementById('nombreNuevaTarifa').focus();
    }

    function cerrarModalNuevaTarifa() {
      document.getElementById('modalNuevaTarifa').classList.add('hidden');
      document.getElementById('modalNuevaTarifa').classList.remove('flex');
      document.getElementById('nombreNuevaTarifa').value = '';
    }

    function agregarNuevaTarifa() {
      const nombre = document.getElementById('nombreNuevaTarifa').value.trim();
      
      if (!nombre) {
        alert('Ingresa un nombre para la tarifa');
        return;
      }
      
      fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'agregar_nueva_tarifa=1&nombre=' + encodeURIComponent(nombre)
      })
      .then(r => r.text())
      .then(respuesta => {
        if (respuesta.startsWith('ok:')) {
          const partes = respuesta.split(':');
          const columna = partes[1];
          const nombreDisplay = partes[2] || nombre;
          
          alert('✅ Tarifa "' + nombreDisplay + '" agregada exitosamente. Recarga la página para verla.');
          cerrarModalNuevaTarifa();
          // Recargar la página para ver los cambios
          location.reload();
        } else if (respuesta === 'error: ya existe') {
          alert('⚠️ Esta tarifa ya existe');
        } else {
          alert('❌ Error: ' + respuesta);
        }
      })
      .catch(err => {
        console.error(err);
        alert('❌ Error de conexión');
      });
    }

    // ===== FUNCIÓN CORREGIDA PARA GUARDAR CLASIFICACIÓN =====
    function guardarClasificacionRuta(ruta, vehiculo, clasificacion) {
      if (!clasificacion) return;
      
      console.log('Guardando clasificación:', {ruta, vehiculo, clasificacion});
      
      fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'guardar_clasificacion=1&ruta=' + encodeURIComponent(ruta) + 
              '&tipo_vehiculo=' + encodeURIComponent(vehiculo) + 
              '&clasificacion=' + encodeURIComponent(clasificacion)
      })
      .then(r => r.text())
      .then(t => {
        if (t.trim() === 'ok') {
          console.log('✅ Clasificación guardada correctamente');
        } else {
          console.error('❌ Error guardando clasificación:', t);
          alert('Error al guardar la clasificación');
        }
      })
      .catch(err => {
        console.error('❌ Error de conexión:', err);
        alert('Error de conexión');
      });
    }

    // ===== APLICAR CLASIFICACIÓN MASIVA =====
    function aplicarClasificacionMasiva() {
      const patron = document.getElementById('txt_patron_ruta').value.trim().toLowerCase();
      const clasif = document.getElementById('sel_clasif_masiva').value;

      if (!patron || !clasif) {
        alert('Escribe un texto y elige una clasificación.');
        return;
      }

      const filas = document.querySelectorAll('.fila-ruta');
      let contador = 0;

      filas.forEach(row => {
        const ruta = row.dataset.ruta.toLowerCase();
        const vehiculo = row.dataset.vehiculo;
        if (ruta.includes(patron)) {
          const sel = row.querySelector('.select-clasif-ruta');
          sel.value = clasif;
          // 🔴 AQUÍ ESTÁ LA CORRECCIÓN - GUARDAR INMEDIATAMENTE
          guardarClasificacionRuta(row.dataset.ruta, vehiculo, clasif);
          contador++;
        }
      });

      alert('✅ Se aplicó la clasificación a ' + contador + ' rutas. Vuelve a darle "Filtrar" para recalcular.');
    }

    // ===== FUNCIÓN PARA GUARDAR CUANDO CAMBIA UN SELECT INDIVIDUAL =====
    function configurarSelectsClasificacion() {
      document.querySelectorAll('.select-clasif-ruta').forEach(sel => {
        // Remover event listeners previos para evitar duplicados
        sel.removeEventListener('change', handleSelectChange);
        // Agregar nuevo event listener
        sel.addEventListener('change', handleSelectChange);
      });
    }

    function handleSelectChange(event) {
      const sel = event.target;
      const ruta = sel.dataset.ruta;
      const vehiculo = sel.dataset.vehiculo;
      const clasif = sel.value;
      
      console.log('Cambio en select:', {ruta, vehiculo, clasif});
      
      if (clasif) {
        guardarClasificacionRuta(ruta, vehiculo, clasif);
      }
    }

    // ===== INICIALIZACIÓN =====
    document.addEventListener('DOMContentLoaded', function() {
      // Configurar los selects de clasificación
      configurarSelectsClasificacion();
      
      // Configurar el botón "Filtrar" para que guarde todas las clasificaciones antes de recargar
      const btnFiltrar = document.querySelector('form[method="get"] button[type="submit"]');
      if (btnFiltrar) {
        btnFiltrar.addEventListener('click', function(e) {
          // Guardar todas las clasificaciones antes de filtrar
          guardarTodasClasificaciones();
        });
      }
      
      // Tus otras funciones de inicialización aquí...
    });

    // ===== GUARDAR TODAS LAS CLASIFICACIONES ANTES DE FILTRAR =====
    function guardarTodasClasificaciones() {
      const selects = document.querySelectorAll('.select-clasif-ruta');
      let guardados = 0;
      let total = selects.length;
      
      selects.forEach(sel => {
        const ruta = sel.dataset.ruta;
        const vehiculo = sel.dataset.vehiculo;
        const clasif = sel.value;
        
        if (clasif) {
          guardarClasificacionRuta(ruta, vehiculo, clasif);
          guardados++;
        }
      });
      
      console.log(`Guardadas ${guardados} de ${total} clasificaciones`);
    }

    // ===== TUS OTRAS FUNCIONES ORIGINALES (recalcular, etc.) =====
    // ... [TUS FUNCIONES ORIGINALES AQUÍ] ...

  </script>

</body>
</html>