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
   🔹 Cargar configuraciones de pago parcial
======================================================= */
$pagoParcialConfig = [];
$resParcial = $conn->query("SELECT conductor, porcentaje FROM conductores_pago_parcial WHERE activo = TRUE");
if ($resParcial) {
    while ($r = $resParcial->fetch_assoc()) {
        $pagoParcialConfig[$r['conductor']] = $r['porcentaje'];
    }
}
?>

<!-- En el HEAD, agregar estilos -->
<style>
    .tipo-pago { width: 90px; }
    .porcentaje-adelanto { width: 60px; }
    .pagado-ahora { border-color: #3b82f6; background-color: #eff6ff; }
    .pendiente { border-color: #f97316; background-color: #fff7ed; }
    .btn-guardar-pago { 
        padding: 2px 8px; 
        font-size: 11px;
        margin-top: 2px;
    }
    .estado-pago {
        font-size: 10px;
        padding: 1px 5px;
        border-radius: 10px;
        margin-left: 5px;
    }
    .estado-completo { background-color: #d1fae5; color: #065f46; }
    .estado-parcial { background-color: #fef3c7; color: #92400e; }
</style>

<!-- MODIFICAR LA TABLA DE CONDUCTORES -->
<table id="tabla_conductores" class="w-full text-sm table-fixed">
    <colgroup>
        <col style="width:18%">  <!-- Conductor -->
        <col style="width:9%">   <!-- Tipo -->
        <col style="width:5%">   <!-- C -->
        <col style="width:5%">   <!-- M -->
        <col style="width:5%">   <!-- E -->
        <col style="width:5%">   <!-- S -->
        <col style="width:6%">   <!-- CT -->
        <col style="width:5%">   <!-- Tipo Pago -->
        <col style="width:5%">   <!-- % Adelanto -->
        <col style="width:15%">  <!-- Mensualidad -->
        <col style="width:11%">  <!-- Pagado Ahora -->
        <col style="width:11%">  <!-- Pendiente -->
    </colgroup>
    <thead class="bg-blue-600 text-white">
        <tr>
            <th class="px-3 py-2 text-left">Conductor</th>
            <th class="px-3 py-2 text-center">Tipo</th>
            <th class="px-3 py-2 text-center">C</th>
            <th class="px-3 py-2 text-center">M</th>
            <th class="px-3 py-2 text-center">E</th>
            <th class="px-3 py-2 text-center">S</th>
            <th class="px-3 py-2 text-center">CT</th>
            <th class="px-3 py-2 text-center">Tipo Pago</th>
            <th class="px-3 py-2 text-center">% Adelanto</th>
            <th class="px-3 py-2 text-center">Mensualidad</th>
            <th class="px-3 py-2 text-center">Pagado Ahora</th>
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
            class="hover:bg-blue-50/40 transition-colors">
            
            <!-- Columna Conductor (igual que antes) -->
            <td class="px-3 py-2">
                <div class="flex items-center gap-2">
                    <button type="button" class="conductor-link text-blue-700 hover:text-blue-900 underline underline-offset-2 transition">
                        <?= htmlspecialchars($conductor) ?>
                    </button>
                    <span class="estado-pago <?= $esPagoParcial ? 'estado-parcial' : 'estado-completo' ?>">
                        <?= $esPagoParcial ? 'Parcial' : 'Completo' ?>
                    </span>
                </div>
            </td>
            
            <!-- Columnas de viajes (igual que antes) -->
            <td class="px-3 py-2 text-center"><?= htmlspecialchars($viajes['vehiculo']) ?></td>
            <td class="px-3 py-2 text-center"><?= (int)$viajes["completos"] ?></td>
            <td class="px-3 py-2 text-center"><?= (int)$viajes["medios"] ?></td>
            <td class="px-3 py-2 text-center"><?= (int)$viajes["extras"] ?></td>
            <td class="px-3 py-2 text-center"><?= (int)$viajes["siapana"] ?></td>
            <td class="px-3 py-2 text-center"><?= (int)$viajes["carrotanques"] ?></td>
            
            <!-- NUEVO: Tipo Pago -->
            <td class="px-3 py-2 text-center">
                <select class="tipo-pago rounded-lg border border-slate-300 px-2 py-1 text-xs" 
                        data-conductor="<?= htmlspecialchars($conductor) ?>">
                    <option value="completo" <?= !$esPagoParcial ? 'selected' : '' ?>>Completo</option>
                    <option value="parcial" <?= $esPagoParcial ? 'selected' : '' ?>>Parcial</option>
                </select>
            </td>
            
            <!-- NUEVO: % Adelanto -->
            <td class="px-3 py-2 text-center">
                <input type="number" 
                       class="porcentaje-adelanto w-16 rounded-lg border border-slate-300 px-2 py-1 text-xs text-center <?= $esPagoParcial ? '' : 'hidden' ?>"
                       value="<?= $porcentaje ?>"
                       min="1" max="100"
                       data-conductor="<?= htmlspecialchars($conductor) ?>">
                <span class="<?= $esPagoParcial ? 'hidden' : '' ?> text-gray-400">-</span>
                <button type="button" 
                        class="btn-guardar-pago hidden text-xs bg-green-600 text-white px-2 py-0.5 rounded hover:bg-green-700 transition"
                        data-conductor="<?= htmlspecialchars($conductor) ?>">
                    💾 Guardar
                </button>
            </td>
            
            <!-- Columna Mensualidad (igual que antes) -->
            <td class="px-3 py-2">
                <div class="mensual-info hidden flex-col gap-1">
                    <!-- ... código existente de mensualidad ... -->
                </div>
                <button type="button" class="btn-agregar-mensual text-xs text-blue-600 hover:text-blue-800">
                    + Agregar
                </button>
            </td>
            
            <!-- NUEVO: Pagado Ahora -->
            <td class="px-3 py-2">
                <input type="text"
                       class="pagado-ahora w-full rounded-xl border border-blue-300 px-3 py-2 text-right bg-blue-50 font-semibold outline-none whitespace-nowrap tabular-nums"
                       readonly dir="ltr"
                       value="$0">
            </td>
            
            <!-- NUEVO: Pendiente -->
            <td class="px-3 py-2">
                <input type="text"
                       class="pendiente w-full rounded-xl border border-orange-300 px-3 py-2 text-right bg-orange-50 font-semibold outline-none whitespace-nowrap tabular-nums"
                       readonly dir="ltr"
                       value="$0">
                <div class="text-[10px] text-orange-600 text-right mt-1 pendiente-detalle"></div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- JavaScript para manejar pagos parciales -->
<script>
// ===== PAGOS PARCIALES =====
let pagosParciales = <?= json_encode($pagoParcialConfig) ?>;

// Función para guardar configuración de pago
function guardarConfigPago(conductor) {
    const fila = document.querySelector(`tr[data-conductor="${conductor}"]`);
    const selectTipo = fila.querySelector('.tipo-pago');
    const inputPorcentaje = fila.querySelector('.porcentaje-adelanto');
    const btnGuardar = fila.querySelector('.btn-guardar-pago');
    const estadoSpan = fila.querySelector('.estado-pago');
    
    const tipo = selectTipo.value;
    const porcentaje = tipo === 'parcial' ? (parseFloat(inputPorcentaje.value) || 30) : 0;
    const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
    
    // Mostrar loading
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
                estadoSpan.textContent = 'Parcial';
                estadoSpan.className = 'estado-pago estado-parcial';
                inputPorcentaje.classList.remove('hidden');
                fila.querySelector('.porcentaje-adelanto + span').classList.add('hidden');
            } else {
                delete pagosParciales[conductor];
                estadoSpan.textContent = 'Completo';
                estadoSpan.className = 'estado-pago estado-completo';
                inputPorcentaje.classList.add('hidden');
                fila.querySelector('.porcentaje-adelanto + span').classList.remove('hidden');
            }
            
            btnGuardar.classList.add('hidden');
            recalcular();
            
            // Feedback visual
            btnGuardar.innerHTML = '✅ Guardado';
            setTimeout(() => {
                btnGuardar.innerHTML = '💾 Guardar';
                btnGuardar.disabled = false;
            }, 1500);
        } else {
            alert('Error: ' + respuesta);
            btnGuardar.innerHTML = '💾 Guardar';
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
        const detallePendiente = f.querySelector('.pendiente-detalle');
        
        if (inpPagado) inpPagado.value = formatNumber(pagadoAhora);
        if (inpPendiente) {
            inpPendiente.value = formatNumber(pendiente);
            if (pendiente > 0) {
                detallePendiente.textContent = `${porcentaje}% pagado`;
                detallePendiente.classList.remove('hidden');
            } else {
                detallePendiente.classList.add('hidden');
            }
        }
        
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
    
    // NUEVO: Mostrar resumen de pagos parciales
    document.getElementById('resumen_viajes').textContent = `$${formatNumber(totalViajes)}`;
    document.getElementById('resumen_total').textContent = `$${formatNumber(totalViajes + totalMensual)}`;
    
    // Podemos agregar un nuevo resumen para pagos parciales
    if (totalPendiente > 0) {
        // Mostrar en algún lugar los totales de pagos parciales
        console.log(`Pagado ahora: $${formatNumber(totalPagadoAhora)} | Pendiente: $${formatNumber(totalPendiente)}`);
    }
    
    actualizarListaMensuales();
}

// Event listeners para pagos parciales
document.addEventListener('DOMContentLoaded', function() {
    // Cuando cambia el tipo de pago
    document.querySelectorAll('.tipo-pago').forEach(select => {
        select.addEventListener('change', function() {
            const conductor = this.dataset.conductor;
            const fila = this.closest('tr');
            const inputPorcentaje = fila.querySelector('.porcentaje-adelanto');
            const btnGuardar = fila.querySelector('.btn-guardar-pago');
            
            if (this.value === 'parcial') {
                inputPorcentaje.classList.remove('hidden');
                fila.querySelector('.porcentaje-adelanto + span').classList.add('hidden');
                btnGuardar.classList.remove('hidden');
            } else {
                inputPorcentaje.classList.add('hidden');
                fila.querySelector('.porcentaje-adelanto + span').classList.remove('hidden');
                btnGuardar.classList.remove('hidden');
            }
        });
    });
    
    // Cuando cambia el porcentaje
    document.querySelectorAll('.porcentaje-adelanto').forEach(input => {
        input.addEventListener('change', function() {
            const fila = this.closest('tr');
            const btnGuardar = fila.querySelector('.btn-guardar-pago');
            btnGuardar.classList.remove('hidden');
            recalcular();
        });
        input.addEventListener('input', function() {
            const fila = this.closest('tr');
            const btnGuardar = fila.querySelector('.btn-guardar-pago');
            btnGuardar.classList.remove('hidden');
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
});
</script>