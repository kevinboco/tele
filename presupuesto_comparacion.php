<?php
// presupuesto_comparacion.php - Vista independiente para comparación de presupuestos vs viajes
// Este archivo NO depende de liquidacion_conductor.php

// Consultar todas las empresas con sus presupuestos y total de viajes
$query = "
    SELECT 
        e.id_empresa,
        e.nombre_empresa,
        e.nombre_contacto,
        e.telefono_empresa,
        pe.presupuesto AS presupuesto_asignado,
        pe.ano_presupuesto,
        COALESCE(SUM(v.total_viaje), 0) AS total_viajes,
        COUNT(DISTINCT v.id_viaje) AS cantidad_viajes
    FROM 
        empresa e
    LEFT JOIN 
        presupuesto_empresa pe ON e.id_empresa = pe.id_empresa
        AND pe.ano_presupuesto = YEAR(CURDATE()) -- Presupuesto del año actual
    LEFT JOIN 
        viaje v ON e.id_empresa = v.id_empresa
        AND YEAR(v.fecha_viaje) = YEAR(CURDATE()) -- Viajes del año actual
    GROUP BY 
        e.id_empresa, e.nombre_empresa, pe.presupuesto, pe.ano_presupuesto
    ORDER BY 
        e.nombre_empresa ASC
";

$resultado = mysqli_query($conn, $query);

// Array para almacenar todas las empresas
$empresas = [];
$total_presupuesto_global = 0;
$total_viajes_global = 0;

while ($row = mysqli_fetch_assoc($resultado)) {
    $presupuesto = floatval($row['presupuesto_asignado'] ?? 0);
    $total_viajes = floatval($row['total_viajes']);
    $diferencia = $presupuesto - $total_viajes;
    
    $row['diferencia'] = $diferencia;
    $row['estado'] = $diferencia >= 0 ? 'dentro' : 'excedido';
    
    $empresas[] = $row;
    
    $total_presupuesto_global += $presupuesto;
    $total_viajes_global += $total_viajes;
}

$diferencia_global = $total_presupuesto_global - $total_viajes_global;
?>

<!-- Todo el HTML, CSS y JS de la nueva funcionalidad está aquí -->
<div class="presupuesto-comparacion-container">
    <style>
        /* Estilos exclusivos para esta vista */
        .presupuesto-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .presupuesto-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            padding: 12px;
            text-align: left;
            font-size: 0.9rem;
        }
        
        .presupuesto-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .presupuesto-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .estado-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .estado-dentro {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .estado-excedido {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .resumen-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .resumen-item {
            text-align: center;
        }
        
        .resumen-label {
            font-size: 0.8rem;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .resumen-valor {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .diferencia-positiva {
            color: #4CAF50;
            font-weight: bold;
        }
        
        .diferencia-negativa {
            color: #f44336;
            font-weight: bold;
        }
        
        .filter-container {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            flex: 1;
            min-width: 200px;
        }
        
        .filter-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: white;
        }
        
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .btn-excel {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-excel:hover {
            background-color: #218838;
        }
        
        .btn-print {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-print:hover {
            background-color: #0069d9;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
        
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 40px;
        }
        
        .loading-spinner.active {
            display: block;
        }
        
        .empresa-info {
            display: flex;
            flex-direction: column;
        }
        
        .empresa-contacto {
            font-size: 0.8rem;
            color: #666;
        }
        
        .badge-ano {
            background-color: #17a2b8;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            margin-left: 5px;
        }
    </style>

    <div class="presupuesto-header">
        <h2>📊 Comparación Presupuesto vs Viajes - Año <?php echo date('Y'); ?></h2>
        <p>Análisis detallado de todas las empresas</p>
    </div>

    <!-- Resumen Global -->
    <div class="resumen-card">
        <div class="resumen-item">
            <div class="resumen-label">💰 Presupuesto Total</div>
            <div class="resumen-valor">$<?php echo number_format($total_presupuesto_global, 0, ',', '.'); ?></div>
        </div>
        <div class="resumen-item">
            <div class="resumen-label">🚚 Total Viajes</div>
            <div class="resumen-valor">$<?php echo number_format($total_viajes_global, 0, ',', '.'); ?></div>
        </div>
        <div class="resumen-item">
            <div class="resumen-label">📈 Diferencia Global</div>
            <div class="resumen-valor <?php echo $diferencia_global >= 0 ? 'diferencia-positiva' : 'diferencia-negativa'; ?>">
                $<?php echo number_format(abs($diferencia_global), 0, ',', '.'); ?>
                <?php echo $diferencia_global >= 0 ? '💰 Sobrante' : '⚠️ Déficit'; ?>
            </div>
        </div>
    </div>

    <!-- Filtros y Acciones -->
    <div class="header-actions">
        <div class="filter-container">
            <input type="text" id="buscarEmpresa" class="filter-input" placeholder="🔍 Buscar empresa..." onkeyup="filtrarTabla()">
            <select id="filtroEstado" class="filter-select" onchange="filtrarTabla()">
                <option value="todos">📋 Todos los estados</option>
                <option value="dentro">✅ Dentro del presupuesto</option>
                <option value="excedido">❌ Excedidos</option>
            </select>
        </div>
        <div style="display: flex; gap: 10px;">
            <button class="btn-excel" onclick="exportarExcel()">
                📥 Exportar Excel
            </button>
            <button class="btn-print" onclick="window.print()">
                🖨️ Imprimir
            </button>
        </div>
    </div>

    <!-- Tabla de Comparación -->
    <div style="overflow-x: auto;">
        <table class="presupuesto-table" id="tablaPresupuesto">
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>Contacto</th>
                    <th>💰 Presupuesto</th>
                    <th>🚚 Total Viajes</th>
                    <th># Viajes</th>
                    <th>📊 Diferencia</th>
                    <th>📈 % Uso</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($empresas)): ?>
                    <tr>
                        <td colspan="8" class="no-data">
                            No hay datos disponibles para mostrar
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($empresas as $emp): 
                        $porcentaje_uso = $emp['presupuesto_asignado'] > 0 
                            ? round(($emp['total_viajes'] / $emp['presupuesto_asignado']) * 100, 2) 
                            : 0;
                    ?>
                    <tr data-estado="<?php echo $emp['estado']; ?>" data-empresa="<?php echo strtolower($emp['nombre_empresa']); ?>">
                        <td>
                            <div class="empresa-info">
                                <strong><?php echo htmlspecialchars($emp['nombre_empresa']); ?></strong>
                                <?php if ($emp['ano_presupuesto']): ?>
                                    <span class="badge-ano"><?php echo $emp['ano_presupuesto']; ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($emp['nombre_contacto'] ?? 'N/A'); ?><br>
                            <small><?php echo htmlspecialchars($emp['telefono_empresa'] ?? ''); ?></small>
                        </td>
                        <td style="text-align: right;">
                            $<?php echo number_format($emp['presupuesto_asignado'] ?? 0, 0, ',', '.'); ?>
                        </td>
                        <td style="text-align: right;">
                            $<?php echo number_format($emp['total_viajes'], 0, ',', '.'); ?>
                        </td>
                        <td style="text-align: center;">
                            <?php echo $emp['cantidad_viajes']; ?>
                        </td>
                        <td style="text-align: right; <?php echo $emp['diferencia'] >= 0 ? 'color: #4CAF50;' : 'color: #f44336; font-weight: bold;'; ?>">
                            $<?php echo number_format(abs($emp['diferencia']), 0, ',', '.'); ?>
                            <?php echo $emp['diferencia'] >= 0 ? '💰' : '⚠️'; ?>
                        </td>
                        <td style="text-align: center;">
                            <?php echo $porcentaje_uso; ?>%
                            <div style="width: 50px; height: 5px; background: #e0e0e0; margin-top: 3px; border-radius: 3px; overflow: hidden;">
                                <div style="height: 100%; width: <?php echo min($porcentaje_uso, 100); ?>%; background: <?php echo $porcentaje_uso > 100 ? '#f44336' : '#4CAF50'; ?>;"></div>
                            </div>
                        </td>
                        <td>
                            <span class="estado-badge <?php echo $emp['estado'] == 'dentro' ? 'estado-dentro' : 'estado-excedido'; ?>">
                                <?php echo $emp['estado'] == 'dentro' ? '✅ Dentro' : '❌ Excedido'; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot style="background: #f8f9fa; font-weight: bold;">
                <tr>
                    <td colspan="2" style="text-align: right;"><strong>TOTALES:</strong></td>
                    <td style="text-align: right;"><strong>$<?php echo number_format($total_presupuesto_global, 0, ',', '.'); ?></strong></td>
                    <td style="text-align: right;"><strong>$<?php echo number_format($total_viajes_global, 0, ',', '.'); ?></strong></td>
                    <td></td>
                    <td style="text-align: right; <?php echo $diferencia_global >= 0 ? 'color: #4CAF50;' : 'color: #f44336;'; ?>">
                        <strong>$<?php echo number_format(abs($diferencia_global), 0, ',', '.'); ?></strong>
                    </td>
                    <td></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Loading Spinner -->
    <div id="loading" class="loading-spinner">
        <div>Cargando...</div>
    </div>
</div>

<script>
// Scripts exclusivos para esta funcionalidad
function filtrarTabla() {
    const input = document.getElementById('buscarEmpresa');
    const filtroEstado = document.getElementById('filtroEstado');
    const filter = input.value.toLowerCase();
    const estado = filtroEstado.value;
    const rows = document.querySelectorAll('#tablaPresupuesto tbody tr');
    
    rows.forEach(row => {
        if (row.classList.contains('no-data-row')) return;
        
        const empresa = row.getAttribute('data-empresa') || '';
        const rowEstado = row.getAttribute('data-estado') || '';
        
        const matchesBusqueda = empresa.includes(filter);
        const matchesEstado = estado === 'todos' || rowEstado === estado;
        
        row.style.display = matchesBusqueda && matchesEstado ? '' : 'none';
    });
}

function exportarExcel() {
    // Mostrar loading
    document.getElementById('loading').classList.add('active');
    
    try {
        const tabla = document.getElementById('tablaPresupuesto');
        const wb = XLSX.utils.table_to_book(tabla, {sheet: "Presupuestos vs Viajes"});
        XLSX.writeFile(wb, `presupuesto_vs_viajes_<?php echo date('Y-m-d'); ?>.xlsx`);
    } catch (error) {
        alert('Error al exportar a Excel. Asegúrate de que la librería XLSX esté cargada.');
        console.error(error);
    } finally {
        document.getElementById('loading').classList.remove('active');
    }
}

// Inicializar tooltips o cualquier otra funcionalidad
document.addEventListener('DOMContentLoaded', function() {
    console.log('Vista de comparación de presupuestos cargada correctamente');
    
    // Agregar evento de tecla Enter para búsqueda
    document.getElementById('buscarEmpresa').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            filtrarTabla();
        }
    });
});

// Si usas jQuery, puedes incluir esto también
if (typeof jQuery !== 'undefined') {
    $(document).ready(function() {
        // Cargar datos por AJAX si es necesario
        console.log('jQuery detectado - Funcionalidad adicional disponible');
    });
}
</script>

<!-- Incluir librería para Excel (opcional, mejora la funcionalidad) -->
<script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>