<?php
// =======================================================
// 🔹 VISTA MODAL - COMPLETAMENTE AUTÓNOMA
// =======================================================
// Recibir datos desde liquidacion.php
extract($datos_vistas);

// =======================================================
// 🔹 CSS ESPECÍFICO DEL MODAL
// =======================================================
?>
<style>
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

  /* Colores para viajes */
  .row-viaje:hover { background-color: #f8fafc; }
  .cat-completo { background-color: rgba(209, 250, 229, 0.1); }
  .cat-medio { background-color: rgba(254, 243, 199, 0.1); }
  .cat-extra { background-color: rgba(241, 245, 249, 0.1); }
  .cat-siapana { background-color: rgba(250, 232, 255, 0.1); }
  .cat-carrotanque { background-color: rgba(207, 250, 254, 0.1); }
  .cat-otro { background-color: rgba(243, 244, 246, 0.1); }
</style>

<!-- ======================================================= -->
<!-- 🔹 HTML DEL MODAL -->
<!-- ======================================================= -->
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

<!-- ======================================================= -->
<!-- 🔹 JAVASCRIPT ESPECÍFICO DEL MODAL -->
<!-- ======================================================= -->
<script>
// ===== VARIABLES GLOBALES DEL MODAL =====
const viajesModal            = document.getElementById('viajesModal');
const viajesContent          = document.getElementById('viajesContent');
const viajesTitle            = document.getElementById('viajesTitle');
const viajesClose            = document.getElementById('viajesCloseBtn');
const viajesSelectConductor  = document.getElementById('viajesSelectConductor');
const viajesRango            = document.getElementById('viajesRango');
const viajesEmpresa          = document.getElementById('viajesEmpresa');

let viajesConductorActual = null;

// ===== FUNCIONES PHP QUE SOLO USA EL MODAL =====
<?php
// Función para obtener estilos de clasificación (igual que en tabla)
function obtenerEstiloClasificacionModal($clasificacion) {
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
?>

// ===== FUNCIONES JAVASCRIPT DEL MODAL =====

// Inicializar select de conductores
function initViajesSelect(selectedName) {
    viajesSelectConductor.innerHTML = "";
    const conductores = <?= json_encode(array_keys($datos), JSON_UNESCAPED_UNICODE); ?>;
    
    conductores.forEach(nombre => {
        const opt = document.createElement('option');
        opt.value = nombre;
        opt.textContent = nombre;
        if (nombre === selectedName) opt.selected = true;
        viajesSelectConductor.appendChild(opt);
    });
}

// Cargar viajes del conductor
function loadViajes(nombre) {
    viajesContent.innerHTML = '<p class="text-center m-0 animate-pulse">Cargando…</p>';
    viajesConductorActual = nombre;
    viajesTitle.textContent = nombre;

    const qs = new URLSearchParams({
        viajes_conductor: nombre,
        desde: '<?= $desde ?>',
        hasta: '<?= $hasta ?>',
        empresa: '<?= $empresaFiltro ?>'
    });

    // AJAX específico del modal
    fetch('liquidacion.php?' + qs.toString())
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

// Función pública para abrir el modal (llamada desde tabla)
function abrirModalViajes(nombreInicial){
    viajesRango.textContent   = '<?= $desde ?>' + " → " + '<?= $hasta ?>';
    viajesEmpresa.textContent = ('<?= $empresaFiltro ?>' && '<?= $empresaFiltro ?>' !== "") ? '<?= $empresaFiltro ?>' : "Todas las empresas";

    initViajesSelect(nombreInicial);

    viajesModal.classList.add('show');

    loadViajes(nombreInicial);
}

// Cerrar modal
function cerrarModalViajes(){
    viajesModal.classList.remove('show');
    viajesContent.innerHTML = '';
    viajesConductorActual = null;
}

// ===== ENDPOINT AJAX ESPECÍFICO DEL MODAL =====
<?php
// Este código se ejecuta si hay una petición AJAX para viajes del conductor
if (isset($_GET['viajes_conductor'])) {
    $nombre  = $conn->real_escape_string($_GET['viajes_conductor']);
    $desde   = $_GET['desde'];
    $hasta   = $_GET['hasta'];
    $empresa = $_GET['empresa'] ?? "";

    // Función para obtener clasificaciones disponibles
    function obtenerColumnasTarifasModal($conn) {
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

    $clasificaciones_disponibles = obtenerColumnasTarifasModal($conn);
    $legend = [];
    
    foreach ($clasificaciones_disponibles as $clasif) {
        $estilo = obtenerEstiloClasificacionModal($clasif);
        $legend[$clasif] = [
            'label' => $estilo['label'],
            'badge' => "{$estilo['bg']} {$estilo['text']} border {$estilo['border']}",
            'row' => $estilo['row']
        ];
    }
    
    $legend['otro'] = ['label'=>'Sin clasificar', 'badge'=>'bg-gray-100 text-gray-700 border border-gray-200', 'row'=>'bg-gray-50/20'];

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
                $estilo = obtenerEstiloClasificacionModal($cat);
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
?>

// ===== EVENT LISTENERS DEL MODAL =====
viajesClose.addEventListener('click', cerrarModalViajes);
viajesModal.addEventListener('click', (e)=>{
    if(e.target===viajesModal) cerrarModalViajes();
});

viajesSelectConductor.addEventListener('change', ()=>{
    const nuevo = viajesSelectConductor.value;
    loadViajes(nuevo);
});

// ===== INICIALIZACIÓN DEL MODAL =====
document.addEventListener('DOMContentLoaded', function() {
    // El modal ya está listo para usarse
    console.log('Modal de viajes cargado y listo');
});
</script>