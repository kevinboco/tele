<?php
/**
 * PRESUPUESTO COMPARACION - VISTA INDEPENDIENTE
 * ==============================================
 * Este archivo funciona por sí solo (acceso directo) 
 * y también puede ser incluido en otras vistas
 * 
 * Uso directo: http://tusitio.com/modules/presupuesto/comparacion.php
 * Uso include: include('ruta/presupuesto_comparacion.php');
 */

// ==============================================
// 1. CONEXIÓN A LA BASE DE DATOS (autónoma)
// ==============================================
$host = "mysql.hostinger.com";
$user = "u648222299_keboco5";    
$pass = "Bucaramanga3011";       
$db   = "u648222299_viajes";  

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("<div style='color: red; padding: 20px; border: 1px solid red; margin: 20px;'>
            <h3>Error de conexión a la base de datos</h3>
            <p>" . $conn->connect_error . "</p>
         </div>");
}

// ==============================================
// 2. CONSULTA PRINCIPAL - Presupuestos vs Viajes
// ==============================================
$anio_actual = date('Y');
$mes_actual = date('m');

// Obtener todas las empresas con sus presupuestos y total de viajes
$query = "
    SELECT 
        ea.nombre AS empresa_nombre,
        COALESCE(pe.presupuesto, 0) AS presupuesto_asignado,
        pe.mes,
        pe.anio,
        COALESCE(SUM(v.total_viaje_calculado), 0) AS total_viajes,
        COUNT(DISTINCT v.id) AS cantidad_viajes
    FROM 
        empresas_admin ea
    LEFT JOIN 
        presupuestos_empresa pe ON ea.nombre = pe.empresa 
        AND pe.anio = $anio_actual
        AND pe.mes = $mes_actual
        AND pe.activo = 1
    LEFT JOIN 
        viajes v ON ea.nombre = v.empresa 
        AND YEAR(v.fecha) = $anio_actual
        AND MONTH(v.fecha) = $mes_actual
    GROUP BY 
        ea.nombre, pe.presupuesto, pe.mes, pe.anio
    ORDER BY 
        ea.nombre ASC
";

$resultado = $conn->query($query);

// Procesar datos
$empresas = [];
$total_presupuesto_global = 0;
$total_viajes_global = 0;

while ($row = $resultado->fetch_assoc()) {
    $presupuesto = floatval($row['presupuesto_asignado']);
    $total_viajes = floatval($row['total_viajes']);
    $diferencia = $presupuesto - $total_viajes;
    
    // Calcular valor del viaje (si no hay total_viaje en la tabla, necesitamos calcularlo)
    // Nota: Asumo que necesitas calcular el valor de cada viaje según la ruta y tipo de vehículo
    // Esto es un placeholder - ajusta según tu lógica de negocio
    $row['total_viajes'] = $total_viajes; // Ya viene de la consulta
    $row['diferencia'] = $diferencia;
    $row['estado'] = $diferencia >= 0 ? 'dentro' : 'excedido';
    $row['porcentaje_uso'] = $presupuesto > 0 ? round(($total_viajes / $presupuesto) * 100, 2) : 0;
    
    $empresas[] = $row;
    $total_presupuesto_global += $presupuesto;
    $total_viajes_global += $total_viajes;
}

$diferencia_global = $total_presupuesto_global - $total_viajes_global;
?>

<!-- ============================================== -->
<!-- 3. HTML COMPLETO - Todo lo necesario está aquí -->
<!-- ============================================== -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comparación Presupuestos vs Viajes</title>
    <!-- Estilos Bootstrap (opcional, para mejor apariencia) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* ===== ESTILOS EXCLUSIVOS DE ESTA VISTA ===== */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            padding: 20px;
        }
        
        .presupuesto-container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 25px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 24px;
            margin: 0;
        }
        
        .badge-mes {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        /* Tarjetas de resumen */
        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .resumen-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        
        .resumen-card:hover {
            transform: translateY(-2px);
        }
        
        .resumen-card.presupuesto { border-left-color: #3498db; }
        .resumen-card.viajes { border-left-color: #e74c3c; }
        .resumen-card.diferencia { border-left-color: #2ecc71; }
        .resumen-card.estado { border-left-color: #f39c12; }
        
        .resumen-label {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        
        .resumen-valor {
            font-size: 28px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .resumen-sub {
            font-size: 13px;
            color: #95a5a6;
            margin-top: 5px;
        }
        
        /* Filtros */
        .filtros {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .filtro-input {
            flex: 1;
            min-width: 200px;
            padding: 10px 15px;
            border: 1px solid #dde0e3;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .filtro-select {
            padding: 10px 15px;
            border: 1px solid #dde0e3;
            border-radius: 5px;
            background: white;
            min-width: 150px;
        }
        
        /* Botones de acción */
        .acciones {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .btn-action {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .btn-excel {
            background: #27ae60;
            color: white;
        }
        
        .btn-excel:hover {
            background: #219a52;
        }
        
        .btn-pdf {
            background: #e74c3c;
            color: white;
        }
        
        .btn-pdf:hover {
            background: #c0392b;
        }
        
        .btn-print {
            background: #3498db;
            color: white;
        }
        
        .btn-print:hover {
            background: #2980b9;
        }
        
        /* Tabla */
        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #e0e4e8;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            padding: 15px 12px;
            font-size: 14px;
            text-align: left;
            white-space: nowrap;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            font-size: 14px;
        }
        
        tr:hover td {
            background-color: #f5f7fa;
        }
        
        .estado-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .estado-dentro {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .estado-excedido {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .progress-bar {
            width: 60px;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 3px;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 3px;
        }
        
        .text-success { color: #28a745 !important; }
        .text-danger { color: #dc3545 !important; }
        .text-warning { color: #ffc107 !important; }
        .fw-bold { font-weight: 600; }
        
        /* Footer */
        .table-footer {
            background: #f8f9fa;
            padding: 15px;
            border-top: 2px solid #dee2e6;
            font-weight: 600;
        }
        
        /* Loading */
        .loading {
            display: none;
            text-align: center;
            padding: 40px;
        }
        
        .loading.active {
            display: block;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .presupuesto-container {
                padding: 15px;
            }
            
            .resumen-valor {
                font-size: 22px;
            }
            
            .filtros {
                flex-direction: column;
            }
            
            .acciones {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
<div class="presupuesto-container">
    <!-- ===== HEADER ===== -->
    <div class="header">
        <div>
            <h1><i class="fas fa-chart-pie"></i> Comparación Presupuestos vs Viajes</h1>
            <p class="text-muted">Análisis detallado por empresa - <span class="badge-mes"><?php echo ucfirst(strftime('%B')) . ' ' . date('Y'); ?></span></p>
        </div>
        <div class="acciones">
            <button class="btn-action btn-excel" onclick="exportarExcel()">
                <i class="fas fa-file-excel"></i> Exportar Excel
            </button>
            <button class="btn-action btn-pdf" onclick="exportarPDF()">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <button class="btn-action btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Imprimir
            </button>
        </div>
    </div>
    
    <!-- ===== TARJETAS DE RESUMEN ===== -->
    <div class="resumen-grid">
        <div class="resumen-card presupuesto">
            <div class="resumen-label"><i class="fas fa-coins"></i> Presupuesto Total</div>
            <div class="resumen-valor">$<?php echo number_format($total_presupuesto_global, 0, ',', '.'); ?></div>
            <div class="resumen-sub"><?php echo count($empresas); ?> empresas con presupuesto</div>
        </div>
        
        <div class="resumen-card viajes">
            <div class="resumen-label"><i class="fas fa-truck"></i> Total Viajes</div>
            <div class="resumen-valor">$<?php echo number_format($total_viajes_global, 0, ',', '.'); ?></div>
            <div class="resumen-sub"><?php echo array_sum(array_column($empresas, 'cantidad_viajes')); ?> viajes realizados</div>
        </div>
        
        <div class="resumen-card diferencia">
            <div class="resumen-label"><i class="fas fa-balance-scale"></i> Diferencia Global</div>
            <div class="resumen-valor <?php echo $diferencia_global >= 0 ? 'text-success' : 'text-danger'; ?>">
                $<?php echo number_format(abs($diferencia_global), 0, ',', '.'); ?>
            </div>
            <div class="resumen-sub">
                <?php echo $diferencia_global >= 0 ? '💰 Sobrante' : '⚠️ Déficit'; ?>
            </div>
        </div>
        
        <div class="resumen-card estado">
            <div class="resumen-label"><i class="fas fa-chart-line"></i> Empresas</div>
            <div class="resumen-valor">
                <?php 
                $excedidas = count(array_filter($empresas, fn($e) => $e['estado'] == 'excedido'));
                $dentro = count($empresas) - $excedidas;
                ?>
                <span class="text-success"><?php echo $dentro; ?> dentro</span> | 
                <span class="text-danger"><?php echo $excedidas; ?> excedidas</span>
            </div>
        </div>
    </div>
    
    <!-- ===== FILTROS ===== -->
    <div class="filtros">
        <input type="text" id="buscarEmpresa" class="filtro-input" placeholder="🔍 Buscar empresa..." onkeyup="filtrarTabla()">
        <select id="filtroEstado" class="filtro-select" onchange="filtrarTabla()">
            <option value="todos">📋 Todos los estados</option>
            <option value="dentro">✅ Dentro del presupuesto</option>
            <option value="excedido">❌ Excedidos</option>
        </select>
        <select id="filtroPresupuesto" class="filtro-select" onchange="filtrarTabla()">
            <option value="todos">💰 Todos los presupuestos</option>
            <option value="con">Con presupuesto asignado</option>
            <option value="sin">Sin presupuesto</option>
        </select>
    </div>
    
    <!-- ===== TABLA PRINCIPAL ===== -->
    <div class="table-container">
        <table id="tablaPresupuestos">
            <thead>
                <tr>
                    <th>Empresa</th>
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
                        <td colspan="7" style="text-align: center; padding: 40px; color: #666;">
                            <i class="fas fa-database" style="font-size: 48px; opacity: 0.3; margin-bottom: 10px;"></i>
                            <br>No hay datos disponibles para mostrar
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($empresas as $emp): ?>
                    <tr data-estado="<?php echo $emp['estado']; ?>" 
                        data-empresa="<?php echo strtolower($emp['empresa_nombre']); ?>"
                        data-tiene-presupuesto="<?php echo $emp['presupuesto_asignado'] > 0 ? 'con' : 'sin'; ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($emp['empresa_nombre']); ?></strong>
                            <?php if ($emp['presupuesto_asignado'] == 0): ?>
                                <br><small class="text-warning"><i class="fas fa-exclamation-triangle"></i> Sin presupuesto</small>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <?php if ($emp['presupuesto_asignado'] > 0): ?>
                                <strong>$<?php echo number_format($emp['presupuesto_asignado'], 0, ',', '.'); ?></strong>
                                <br><small>Mes <?php echo $emp['mes']; ?></small>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <strong>$<?php echo number_format($emp['total_viajes'], 0, ',', '.'); ?></strong>
                        </td>
                        <td style="text-align: center;">
                            <?php echo $emp['cantidad_viajes']; ?>
                        </td>
                        <td style="text-align: right; <?php echo $emp['diferencia'] >= 0 ? 'color: #28a745;' : 'color: #dc3545; font-weight: bold;'; ?>">
                            <?php if ($emp['presupuesto_asignado'] > 0): ?>
                                <strong>$<?php echo number_format(abs($emp['diferencia']), 0, ',', '.'); ?></strong>
                                <br><small><?php echo $emp['diferencia'] >= 0 ? '💰 Sobrante' : '⚠️ Excedido'; ?></small>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($emp['presupuesto_asignado'] > 0): ?>
                                <div><?php echo $emp['porcentaje_uso']; ?>%</div>
                                <div class="progress-bar">
                                    <div class="progress-fill" 
                                         style="width: <?php echo min($emp['porcentaje_uso'], 100); ?>%; 
                                                background: <?php echo $emp['porcentaje_uso'] > 100 ? '#dc3545' : ($emp['porcentaje_uso'] > 90 ? '#ffc107' : '#28a745'); ?>;">
                                    </div>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($emp['presupuesto_asignado'] > 0): ?>
                                <span class="estado-badge <?php echo $emp['estado'] == 'dentro' ? 'estado-dentro' : 'estado-excedido'; ?>">
                                    <?php echo $emp['estado'] == 'dentro' ? '✅ Dentro' : '❌ Excedido'; ?>
                                </span>
                            <?php else: ?>
                                <span class="estado-badge" style="background: #e0e0e0; color: #666;">
                                    ⚠️ Sin datos
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="table-footer">
                <tr>
                    <td><strong>TOTALES</strong></td>
                    <td style="text-align: right;"><strong>$<?php echo number_format($total_presupuesto_global, 0, ',', '.'); ?></strong></td>
                    <td style="text-align: right;"><strong>$<?php echo number_format($total_viajes_global, 0, ',', '.'); ?></strong></td>
                    <td style="text-align: center;"><strong><?php echo array_sum(array_column($empresas, 'cantidad_viajes')); ?></strong></td>
                    <td style="text-align: right; <?php echo $diferencia_global >= 0 ? 'color: #28a745;' : 'color: #dc3545;'; ?>">
                        <strong>$<?php echo number_format(abs($diferencia_global), 0, ',', '.'); ?></strong>
                    </td>
                    <td></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <!-- Loading Spinner -->
    <div id="loading" class="loading">
        <div class="spinner"></div>
        <p style="margin-top: 10px; color: #666;">Procesando...</p>
    </div>
</div>

<!-- ===== SCRIPTS ===== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

<script>
// ===== FUNCIONES EXCLUSIVAS DE ESTA VISTA =====
function filtrarTabla() {
    const input = document.getElementById('buscarEmpresa');
    const filtroEstado = document.getElementById('filtroEstado');
    const filtroPresupuesto = document.getElementById('filtroPresupuesto');
    
    const textoBusqueda = input.value.toLowerCase().trim();
    const estadoSeleccionado = filtroEstado.value;
    const presupuestoSeleccionado = filtroPresupuesto.value;
    
    const filas = document.querySelectorAll('#tablaPresupuestos tbody tr');
    
    filas.forEach(fila => {
        if (fila.cells.length === 1 && fila.cells[0].colSpan === 7) return; // Ignorar fila de "no datos"
        
        const empresa = fila.getAttribute('data-empresa') || '';
        const estado = fila.getAttribute('data-estado') || '';
        const tienePresupuesto = fila.getAttribute('data-tiene-presupuesto') || 'sin';
        
        const coincideBusqueda = textoBusqueda === '' || empresa.includes(textoBusqueda);
        const coincideEstado = estadoSeleccionado === 'todos' || estado === estadoSeleccionado;
        const coincidePresupuesto = presupuestoSeleccionado === 'todos' || tienePresupuesto === presupuestoSeleccionado;
        
        fila.style.display = coincideBusqueda && coincideEstado && coincidePresupuesto ? '' : 'none';
    });
}

function exportarExcel() {
    document.getElementById('loading').classList.add('active');
    
    try {
        // Crear datos para Excel
        const datos = [];
        
        // Encabezados
        datos.push(['EMPRESA', 'PRESUPUESTO', 'TOTAL VIAJES', 'CANT. VIAJES', 'DIFERENCIA', '% USO', 'ESTADO']);
        
        // Datos de la tabla
        <?php foreach ($empresas as $emp): ?>
        datos.push([
            '<?php echo $emp['empresa_nombre']; ?>',
            <?php echo $emp['presupuesto_asignado']; ?>,
            <?php echo $emp['total_viajes']; ?>,
            <?php echo $emp['cantidad_viajes']; ?>,
            <?php echo $emp['diferencia']; ?>,
            <?php echo $emp['porcentaje_uso']; ?>,
            '<?php echo $emp['estado'] == 'dentro' ? 'DENTRO' : 'EXCEDIDO'; ?>'
        ]);
        <?php endforeach; ?>
        
        // Totales
        datos.push([]);
        datos.push([
            'TOTALES',
            <?php echo $total_presupuesto_global; ?>,
            <?php echo $total_viajes_global; ?>,
            <?php echo array_sum(array_column($empresas, 'cantidad_viajes')); ?>,
            <?php echo $diferencia_global; ?>,
            '',
            ''
        ]);
        
        // Crear y descargar Excel
        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.aoa_to_sheet(datos);
        
        // Formato de moneda
        const rango = XLSX.utils.decode_range(ws['!ref'] || 'A1:A1');
        for (let row = rango.s.r + 1; row <= rango.e.r; row++) {
            for (let col of [1, 2, 4]) { // Columnas B, C, E
                const celdaRef = XLSX.utils.encode_cell({r: row, c: col});
                if (ws[celdaRef]) {
                    ws[celdaRef].z = '"$"#,##0'; // Formato de moneda
                }
            }
        }
        
        XLSX.utils.book_append_sheet(wb, ws, "Presupuestos vs Viajes");
        XLSX.writeFile(wb, `presupuestos_vs_viajes_${new Date().toISOString().split('T')[0]}.xlsx`);
    } catch (error) {
        console.error('Error al exportar Excel:', error);
        alert('Error al exportar a Excel. Por favor intenta de nuevo.');
    } finally {
        document.getElementById('loading').classList.remove('active');
    }
}

function exportarPDF() {
    document.getElementById('loading').classList.add('active');
    
    try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('landscape');
        
        doc.setFontSize(18);
        doc.text('Comparación Presupuestos vs Viajes', 14, 22);
        doc.setFontSize(11);
        doc.text(`Periodo: ${new Date().toLocaleDateString('es-ES', { month: 'long', year: 'numeric' })}`, 14, 30);
        
        // Datos para la tabla
        const datos = [];
        <?php foreach ($empresas as $emp): ?>
        datos.push([
            '<?php echo $emp['empresa_nombre']; ?>',
            '$<?php echo number_format($emp['presupuesto_asignado'], 0, ',', '.'); ?>',
            '$<?php echo number_format($emp['total_viajes'], 0, ',', '.'); ?>',
            '<?php echo $emp['cantidad_viajes']; ?>',
            '$<?php echo number_format(abs($emp['diferencia']), 0, ',', '.'); ?>',
            '<?php echo $emp['porcentaje_uso']; ?>%',
            '<?php echo $emp['estado'] == 'dentro' ? 'DENTRO' : 'EXCEDIDO'; ?>'
        ]);
        <?php endforeach; ?>
        
        // Totales
        datos.push([
            'TOTALES',
            '$<?php echo number_format($total_presupuesto_global, 0, ',', '.'); ?>',
            '$<?php echo number_format($total_viajes_global, 0, ',', '.'); ?>',
            '<?php echo array_sum(array_column($empresas, 'cantidad_viajes')); ?>',
            '$<?php echo number_format(abs($diferencia_global), 0, ',', '.'); ?>',
            '',
            '<?php echo $diferencia_global >= 0 ? 'SOBRANTE' : 'DÉFICIT'; ?>'
        ]);
        
        doc.autoTable({
            head: [['Empresa', 'Presupuesto', 'Total Viajes', '# Viajes', 'Diferencia', '% Uso', 'Estado']],
            body: datos,
            startY: 35,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [102, 126, 234], textColor: 255 },
            alternateRowStyles: { fillColor: [245, 245, 245] },
            columnStyles: {
                0: { cellWidth: 40 },
                1: { cellWidth: 30, halign: 'right' },
                2: { cellWidth: 30, halign: 'right' },
                3: { cellWidth: 15, halign: 'center' },
                4: { cellWidth: 30, halign: 'right' },
                5: { cellWidth: 15, halign: 'center' },
                6: { cellWidth: 20, halign: 'center' }
            }
        });
        
        doc.save(`presupuestos_vs_viajes_${new Date().toISOString().split('T')[0]}.pdf`);
    } catch (error) {
        console.error('Error al exportar PDF:', error);
        alert('Error al exportar a PDF. Por favor intenta de nuevo.');
    } finally {
        document.getElementById('loading').classList.remove('active');
    }
}

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    console.log('Vista de presupuestos cargada correctamente');
    
    // Agregar atajo de teclado para búsqueda (Ctrl+F)
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            document.getElementById('buscarEmpresa').focus();
        }
    });
});
</script>
</body>
</html>
<?php 
// Cerrar conexión
$conn->close(); 
?>