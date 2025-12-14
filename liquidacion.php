<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

/* =======================================================
   🔹 NUEVO: Guardar configuración de pago parcial
======================================================= */
if (isset($_POST['guardar_pago_parcial'])) {
    $conductor = $conn->real_escape_string($_POST['conductor']);
    $empresa   = $conn->real_escape_string($_POST['empresa']);
    $tipo      = $conn->real_escape_string($_POST['tipo']); // 'completo' o 'parcial'
    $porcentaje = $tipo === 'parcial' ? (float)$_POST['porcentaje'] : 0;
    
    if ($tipo === 'completo') {
        // Eliminar si existe
        $conn->query("DELETE FROM conductores_pago_parcial WHERE conductor='$conductor' AND empresa='$empresa'");
        echo "ok";
    } else {
        // Insertar o actualizar
        $sql = "INSERT INTO conductores_pago_parcial (conductor, empresa, porcentaje, activo)
                VALUES ('$conductor', '$empresa', $porcentaje, TRUE)
                ON DUPLICATE KEY UPDATE 
                porcentaje = VALUES(porcentaje),
                activo = TRUE,
                fecha_registro = CURRENT_TIMESTAMP";
        echo $conn->query($sql) ? "ok" : "error: " . $conn->error;
    }
    exit;
}

/* =======================================================
   🔹 Cargar configuraciones de pago parcial ANTES de mostrar tabla
======================================================= */
$pagoParcialConfig = [];
$resParcial = $conn->query("SELECT conductor, porcentaje FROM conductores_pago_parcial WHERE activo = TRUE");
if ($resParcial) {
    while ($r = $resParcial->fetch_assoc()) {
        $pagoParcialConfig[$r['conductor']] = $r['porcentaje'];
    }
}

// ... (el resto de tu código PHP igual hasta donde se muestra la tabla) ...
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Liquidación de Conductores</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  /* Tus estilos existentes */
  ::-webkit-scrollbar{height:10px;width:10px}
  ::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:999px}
  ::-webkit-scrollbar-thumb:hover{background:#9ca3af}
  input[type=number]::-webkit-inner-spin-button,
  input[type=number]::-webkit-outer-spin-button{ -webkit-appearance: none; margin: 0; }
  .alert-cobro { 
    animation: pulse 2s infinite;
    border-left: 4px solid #f59e0b;
  }
  @keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
  }
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
  
  /* NUEVOS ESTILOS PARA PAGOS PARCIALES */
  .tipo-pago-select { 
    width: 100px; 
    font-size: 11px;
    padding: 2px 4px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
  }
  .porcentaje-input { 
    width: 50px; 
    font-size: 11px;
    padding: 2px 4px;
    text-align: center;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
  }
  .btn-guardar-pago { 
    font-size: 10px;
    padding: 1px 6px;
    margin-top: 2px;
  }
  .badge-pago {
    font-size: 10px;
    padding: 1px 6px;
    border-radius: 10px;
    margin-left: 4px;
  }
  .badge-completo { background-color: #d1fae5; color: #065f46; }
  .badge-parcial { background-color: #fef3c7; color: #92400e; }
  .columna-pagado { background-color: #eff6ff; border-color: #3b82f6; }
  .columna-pendiente { background-color: #fff7ed; border-color: #f97316; }
</style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800">

  <!-- Encabezado (igual que antes) -->
  <header class="max-w-[1600px] mx-auto px-3 md:px-4 pt-6">
    <!-- ... tu header existente ... -->
  </header>

  <!-- Contenido principal -->
  <main class="max-w-[1600px] mx-auto px-3 md:px-4 py-6">
    <div class="grid grid-cols-1 xl:grid-cols-[1fr_2.6fr_0.9fr] gap-5 items-start">
      
      <!-- Columna 1: Tarifas + Filtro + Clasificación (igual que antes) -->
      <section class="space-y-5">
        <!-- ... contenido existente ... -->
      </section>

      <!-- Columna 2: TABLA DE CONDUCTORES MODIFICADA -->
      <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
          <div>
            <h3 class="text-lg font-semibold">🧑‍✈️ Resumen por Conductor</h3>
            <div id="contador-conductores" class="text-xs text-slate-500 mt-1">
              Mostrando <?= count($datos) ?> de <?= count($datos) ?> conductores
            </div>
          </div>
          <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
            <!-- Buscador existente -->
            <div class="buscar-container w-full md:w-64">
              <input id="buscadorConductores" type="text" 
                     placeholder="Buscar conductor..." 
                     class="w-full rounded-lg border border-slate-300 px-3 py-2 pl-3 pr-10 text-sm">
              <button id="clearBuscar" class="buscar-clear">✕</button>
            </div>
            <!-- Totales existentes -->
            <div id="total_chip_container" class="flex items-center gap-3">
              <span class="inline-flex items-center gap-2 rounded-full border border-green-200 bg-green-50 px-3 py-1 text-green-700 font-semibold text-sm">
                📅 Mensual: <span id="total_mensual">0</span>
              </span>
              <span class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-blue-700 font-semibold text-sm">
                🔢 Viajes: <span id="total_viajes">0</span>
              </span>
              <span class="inline-flex items-center gap-2 rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-purple-700 font-semibold text-sm">
                💰 Total: <span id="total_general">0</span>
              </span>
            </div>
          </div>
        </div>

        <!-- TABLA MODIFICADA CON PAGOS PARCIALES -->
        <div class="mt-4 w-full rounded-xl border border-slate-200 overflow-x-auto">
          <table id="tabla_conductores" class="w-full text-sm min-w-[1200px]">
            <thead class="bg-blue-600 text-white">
              <tr>
                <th class="px-3 py-2 text-left">Conductor</th>
                <th class="px-3 py-2 text-center">Tipo</th>
                <th class="px-3 py-2 text-center">C</th>
                <th class="px-3 py-2 text-center">M</th>
                <th class="px-3 py-2 text-center">E</th>
                <th class="px-3 py-2 text-center">S</th>
                <th class="px-3 py-2 text-center">CT</th>
                <!-- NUEVAS COLUMNAS -->
                <th class="px-3 py-2 text-center">Tipo Pago</th>
                <th class="px-3 py-2 text-center">%</th>
                <!-- COLUMNAS EXISTENTES -->
                <th class="px-3 py-2 text-center">Mensualidad</th>
                <!-- NUEVAS COLUMNAS DE MONTO -->
                <th class="px-3 py-2 text-center">Pagado</th>
                <th class="px-3 py-2 text-center">Pendiente</th>
              </tr>
            </thead>
            <tbody id="tabla_conductores_body" class="divide-y divide-slate-100 bg-white">
            <?php foreach ($datos as $conductor => $viajes): 
                $esPagoParcial = isset($pagoParcialConfig[$conductor]);
                $porcentaje = $esPagoParcial ? $pagoParcialConfig[$conductor] : 30;
            ?>
              <tr data-vehiculo="<?= htmlspecialchars($viajes['vehiculo']) ?>" 
                  data-conductor="<?= htmlspecialchars($conductor) ?>" 
                  data-conductor-normalizado="<?= htmlspecialchars(mb_strtolower($conductor)) ?>"
                  class="hover:bg-blue-50/40 transition-colors">
                
                <!-- Columna Conductor -->
                <td class="px-3 py-2">
                  <div class="flex items-center gap-2">
                    <button type="button"
                            class="conductor-link text-blue-700 hover:text-blue-900 underline underline-offset-2 transition text-left truncate max-w-[150px]"
                            title="Ver viajes">
                      <?= htmlspecialchars($conductor) ?>
                    </button>
                    <span class="badge-pago <?= $esPagoParcial ? 'badge-parcial' : 'badge-completo' ?>">
                      <?= $esPagoParcial ? 'Parcial' : 'Completo' ?>
                    </span>
                    <button type="button" 
                            class="btn-mensual text-xs px-2 py-0.5 rounded-full border border-gray-300 hover:border-blue-500 hover:bg-blue-50 transition"
                            title="Marcar como mensual">
                      📅
                    </button>
                  </div>
                </td>
                
                <!-- Columnas existentes de viajes -->
                <td class="px-3 py-2 text-center"><?= htmlspecialchars($viajes['vehiculo']) ?></td>
                <td class="px-3 py-2 text-center"><?= (int)$viajes["completos"] ?></td>
                <td class="px-3 py-2 text-center"><?= (int)$viajes["medios"] ?></td>
                <td class="px-3 py-2 text-center"><?= (int)$viajes["extras"] ?></td>
                <td class="px-3 py-2 text-center"><?= (int)$viajes["siapana"] ?></td>
                <td class="px-3 py-2 text-center"><?= (int)$viajes["carrotanques"] ?></td>
                
                <!-- NUEVA: Tipo Pago -->
                <td class="px-3 py-2 text-center">
                  <select class="tipo-pago-select" data-conductor="<?= htmlspecialchars($conductor) ?>">
                    <option value="completo" <?= !$esPagoParcial ? 'selected' : '' ?>>Completo</option>
                    <option value="parcial" <?= $esPagoParcial ? 'selected' : '' ?>>Parcial</option>
                  </select>
                </td>
                
                <!-- NUEVA: Porcentaje -->
                <td class="px-3 py-2 text-center">
                  <input type="number" 
                         class="porcentaje-input <?= $esPagoParcial ? '' : 'hidden' ?>"
                         value="<?= $porcentaje ?>"
                         min="1" max="100"
                         data-conductor="<?= htmlspecialchars($conductor) ?>">
                  <span class="<?= $esPagoParcial ? 'hidden' : '' ?> text-gray-400">-</span>
                  <button type="button" 
                          class="btn-guardar-pago hidden text-xs bg-green-600 text-white px-2 py-0.5 rounded hover:bg-green-700 transition mt-1"
                          data-conductor="<?= htmlspecialchars($conductor) ?>">
                    💾
                  </button>
                </td>
                
                <!-- Columna Mensualidad (existente) -->
                <td class="px-3 py-2">
                  <div class="mensual-info hidden flex-col gap-1">
                    <!-- ... tu código existente de mensualidad ... -->
                  </div>
                  <button type="button" class="btn-agregar-mensual text-xs text-blue-600 hover:text-blue-800">
                    + Agregar
                  </button>
                </td>
                
                <!-- NUEVA: Pagado Ahora -->
                <td class="px-3 py-2">
                  <input type="text"
                         class="pagado-ahora w-full rounded border border-blue-300 px-2 py-1 text-right columna-pagado font-medium text-sm"
                         readonly
                         value="$0">
                </td>
                
                <!-- NUEVA: Pendiente -->
                <td class="px-3 py-2">
                  <input type="text"
                         class="pendiente w-full rounded border border-orange-300 px-2 py-1 text-right columna-pendiente font-medium text-sm"
                         readonly
                         value="$0">
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Columna 3: Panel viajes + Conductores Mensuales (igual que antes) -->
      <aside class="space-y-5">
        <!-- ... contenido existente ... -->
      </aside>

    </div>
  </main>

  <!-- JavaScript para pagos parciales -->
  <script>
// ===== CONFIGURACIÓN DE PAGOS PARCIALES =====
let pagosParciales = <?= json_encode($pagoParcialConfig) ?>;

// Guardar configuración de pago en BD
function guardarConfigPago(conductor) {
    const fila = document.querySelector(`tr[data-conductor="${conductor}"]`);
    const selectTipo = fila.querySelector('.tipo-pago-select');
    const inputPorcentaje = fila.querySelector('.porcentaje-input');
    const btnGuardar = fila.querySelector('.btn-guardar-pago');
    const badge = fila.querySelector('.badge-pago');
    
    const tipo = selectTipo.value;
    const porcentaje = tipo === 'parcial' ? (parseFloat(inputPorcentaje.value) || 30) : 0;
    const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
    
    // Mostrar loading
    const textoOriginal = btnGuardar.innerHTML;
    btnGuardar.innerHTML = '⏳';
    btnGuardar.disabled = true;
    
    fetch('<?= basename(__FILE__) ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            guardar_pago_parcial: 1,
            conductor: conductor,
            empresa: empresa,
            tipo: tipo,
            porcentaje: porcentaje
        })
    })
    .then(r => r.text())
    .then(respuesta => {
        if (respuesta.trim() === 'ok') {
            // Actualizar estado local
            if (tipo === 'parcial') {
                pagosParciales[conductor] = porcentaje;
                badge.textContent = 'Parcial';
                badge.className = 'badge-pago badge-parcial';
                inputPorcentaje.classList.remove('hidden');
                fila.querySelector('.porcentaje-input + span').classList.add('hidden');
            } else {
                delete pagosParciales[conductor];
                badge.textContent = 'Completo';
                badge.className = 'badge-pago badge-completo';
                inputPorcentaje.classList.add('hidden');
                fila.querySelector('.porcentaje-input + span').classList.remove('hidden');
            }
            
            btnGuardar.classList.add('hidden');
            recalcular();
            
            // Feedback visual
            btnGuardar.innerHTML = '✅';
            setTimeout(() => {
                btnGuardar.innerHTML = textoOriginal;
                btnGuardar.disabled = false;
            }, 1000);
        } else {
            alert('Error guardando: ' + respuesta);
            btnGuardar.innerHTML = textoOriginal;
            btnGuardar.disabled = false;
        }
    });
}

// Modificar función recalcular() para incluir pagos parciales
function recalcular() {
    const tarifas = getTarifas();
    const filas = document.querySelectorAll('#tabla_conductores_body tr');
    let totalViajes = 0;
    let totalMensual = 0;
    let totalPagadoAhora = 0;
    let totalPendiente = 0;
    
    filas.forEach(f => {
        if (f.style.display === 'none') return;
        
        const veh = f.dataset.vehiculo;
        const conductor = f.dataset.conductor;
        
        // Calcular total por viajes
        const c  = parseInt(f.cells[2].innerText) || 0;
        const m  = parseInt(f.cells[3].innerText) || 0;
        const e  = parseInt(f.cells[4].innerText) || 0;
        const s  = parseInt(f.cells[5].innerText) || 0;
        const ca = parseInt(f.cells[6].innerText) || 0;
        const t  = tarifas[veh] || {completo:0, medio:0, extra:0, carrotanque:0, siapana:0};
        
        const totalViajesFila = c * t.completo + m * t.medio + e * t.extra + s * t.siapana + ca * t.carrotanque;
        
        // Calcular mensualidad
        let totalMensualFila = 0;
        if (configMensuales[conductor]) {
            const fechaDesdeInput = f.querySelector('.fecha-desde');
            const fechaHastaInput = f.querySelector('.fecha-hasta');
            const montoInput = f.querySelector('.monto-mensual');
            const diasSpan = f.querySelector('.dias-calculados');
            const detalle = f.querySelector('.mensual-detalle');
            
            totalMensualFila = calcularDiasYMonto(fechaDesdeInput, fechaHastaInput, montoInput, diasSpan, detalle) || 0;
        }
        
        // TOTAL COMPLETO
        const totalCompletoFila = totalViajesFila + totalMensualFila;
        
        // Verificar si es pago parcial
        const porcentaje = pagosParciales[conductor] || 0;
        let pagadoAhora = totalCompletoFila;
        let pendiente = 0;
        
        if (porcentaje > 0 && porcentaje < 100) {
            pagadoAhora = Math.round(totalCompletoFila * (porcentaje / 100));
            pendiente = totalCompletoFila - pagadoAhora;
        }
        
        // Actualizar campos
        const inpPagado = f.querySelector('.pagado-ahora');
        const inpPendiente = f.querySelector('.pendiente');
        
        if (inpPagado) inpPagado.value = '$' + formatNumber(pagadoAhora);
        if (inpPendiente) inpPendiente.value = pendiente > 0 ? '$' + formatNumber(pendiente) : '$0';
        
        // Sumar a totales
        totalViajes += totalViajesFila;
        totalMensual += totalMensualFila;
        totalPagadoAhora += pagadoAhora;
        totalPendiente += pendiente;
    });
    
    // Actualizar totales generales
    document.getElementById('total_viajes').innerText = formatNumber(totalViajes);
    document.getElementById('total_mensual').innerText = formatNumber(totalMensual);
    document.getElementById('total_general').innerText = formatNumber(totalViajes + totalMensual);
    
    // Actualizar resumen en panel lateral
    document.getElementById('resumen_viajes').textContent = `$${formatNumber(totalViajes)}`;
    document.getElementById('resumen_mensual').textContent = `$${formatNumber(totalMensual)}`;
    document.getElementById('resumen_total').textContent = `$${formatNumber(totalViajes + totalMensual)}`;
    
    // Mostrar alerta si hay pendientes
    if (totalPendiente > 0) {
        console.log(`💡 Hay $${formatNumber(totalPendiente)} pendientes de pago`);
    }
    
    actualizarListaMensuales();
}

// Event listeners para pagos parciales
document.addEventListener('DOMContentLoaded', function() {
    // Cuando cambia el tipo de pago
    document.querySelectorAll('.tipo-pago-select').forEach(select => {
        select.addEventListener('change', function() {
            const conductor = this.dataset.conductor;
            const fila = this.closest('tr');
            const inputPorcentaje = fila.querySelector('.porcentaje-input');
            const btnGuardar = fila.querySelector('.btn-guardar-pago');
            const spanGuion = fila.querySelector('.porcentaje-input + span');
            
            if (this.value === 'parcial') {
                inputPorcentaje.classList.remove('hidden');
                if (spanGuion) spanGuion.classList.add('hidden');
                btnGuardar.classList.remove('hidden');
                // Si no tiene valor, poner 30 por defecto
                if (!inputPorcentaje.value) inputPorcentaje.value = 30;
            } else {
                inputPorcentaje.classList.add('hidden');
                if (spanGuion) spanGuion.classList.remove('hidden');
                btnGuardar.classList.remove('hidden');
            }
            recalcular();
        });
    });
    
    // Cuando cambia el porcentaje
    document.querySelectorAll('.porcentaje-input').forEach(input => {
        input.addEventListener('change', function() {
            const fila = this.closest('tr');
            const btnGuardar = fila.querySelector('.btn-guardar-pago');
            if (btnGuardar) btnGuardar.classList.remove('hidden');
            recalcular();
        });
        input.addEventListener('input', function() {
            const fila = this.closest('tr');
            const btnGuardar = fila.querySelector('.btn-guardar-pago');
            if (btnGuardar) btnGuardar.classList.remove('hidden');
            recalcular();
        });
    });
    
    // Botón guardar
    document.querySelectorAll('.btn-guardar-pago').forEach(btn => {
        btn.addEventListener('click', function() {
            const conductor = this.dataset.conductor;
            guardarConfigPago(conductor);
        });
    });
    
    // Llamar a recalcular al cargar la página
    setTimeout(() => {
        recalcular();
    }, 500);
});
  </script>

  <!-- Tu JavaScript existente -->
  <script>
    // Tus funciones existentes (getTarifas, formatNumber, etc.)
    function getTarifas(){
      const tarifas = {};
      document.querySelectorAll('.tarjeta-tarifa').forEach(card=>{
        const veh = card.dataset.vehiculo;
        const val = (campo)=>{
          const el = card.querySelector(`input[data-campo="${campo}"]`);
          return el ? (parseFloat(el.value)||0) : 0;
        };
        tarifas[veh] = {
          completo:    val('completo'),
          medio:       val('medio'),
          extra:       val('extra'),
          carrotanque: val('carrotanque'),
          siapana:     val('siapana')
        };
      });
      return tarifas;
    }

    function formatNumber(num){ 
        return (num||0).toLocaleString('es-CO'); 
    }

    // El resto de tu JavaScript existente (buscador, mensualidades, etc.)
    // ... (todo tu código JavaScript existente que ya funciona) ...
  </script>

</body>
</html>