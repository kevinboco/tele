<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Conexión BD
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Obtener parámetros
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';
$empresas_seleccionadas = isset($_GET['empresas']) ? $_GET['empresas'] : array();

// Función para obtener tarifa
function obtener_tarifa($clasificacion, $tipo_vehiculo, $empresa, $conn) {
    if (empty($clasificacion) || empty($empresa)) {
        return 0;
    }
    
    $sql = "SELECT completo, medio, extra, carrotanque, siapana, 
                   riohacha_completo, riohacha_medio, nazareth_siapana_maicao, 
                   nazareth_siapana_flor_de_la_guajira
            FROM tarifas 
            WHERE empresa = ? AND tipo_vehiculo = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param("ss", $empresa, $tipo_vehiculo);
    $stmt->execute();
    $result = $stmt->get_result();
    $tarifa = $result->fetch_assoc();
    $stmt->close();
    
    if (!$tarifa) {
        return 0;
    }
    
    switch($clasificacion) {
        case 'completo': return isset($tarifa['completo']) ? floatval($tarifa['completo']) : 0;
        case 'medio': return isset($tarifa['medio']) ? floatval($tarifa['medio']) : 0;
        case 'extra': return isset($tarifa['extra']) ? floatval($tarifa['extra']) : 0;
        case 'carrotanque': return isset($tarifa['carrotanque']) ? floatval($tarifa['carrotanque']) : 0;
        case 'siapana': return isset($tarifa['siapana']) ? floatval($tarifa['siapana']) : 0;
        case 'riohacha_completo': return isset($tarifa['riohacha_completo']) ? floatval($tarifa['riohacha_completo']) : 0;
        case 'riohacha_medio': return isset($tarifa['riohacha_medio']) ? floatval($tarifa['riohacha_medio']) : 0;
        default: return 0;
    }
}

// Obtener empresas que empiezan con P (punto incluido)
$empresas_disponibles = array();
$sql_emp = "SELECT DISTINCT empresa 
            FROM viajes 
            WHERE empresa IS NOT NULL 
            AND empresa != '' 
            AND empresa LIKE 'P.%' 
            ORDER BY empresa";
$res_emp = $conn->query($sql_emp);
if ($res_emp) {
    while ($row = $res_emp->fetch_assoc()) {
        $empresas_disponibles[] = $row['empresa'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe de Viajes por Puesto de Salud</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Encabezado */
        .header {
            background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 12px 12px 0 0;
            margin-bottom: 20px;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        /* Filtros */
        .filtros-card {
            background: white;
            padding: 20px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .filtros-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-end;
            margin-bottom: 20px;
        }
        
        .filtro-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .filtro-group label {
            font-size: 12px;
            font-weight: 600;
            color: #5f6368;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filtro-group input {
            padding: 10px 15px;
            border: 1px solid #dadce0;
            border-radius: 8px;
            font-size: 14px;
            min-width: 200px;
        }
        
        .filtro-group input:focus {
            outline: none;
            border-color: #1a73e8;
        }
        
        .btn-filtrar {
            background: #1a73e8;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-filtrar:hover {
            background: #1557b0;
        }
        
        .btn-limpiar {
            background: #5f6368;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        
        /* Empresas */
        .empresas-section {
            border-top: 1px solid #e8eaed;
            padding-top: 20px;
        }
        
        .empresas-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .empresas-header h3 {
            font-size: 14px;
            color: #202124;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
        }
        
        .btn-seleccion {
            background: #f1f3f4;
            border: none;
            padding: 6px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }
        
        .btn-seleccion.all {
            background: #34a853;
            color: white;
        }
        
        .btn-seleccion.none {
            background: #ea4335;
            color: white;
        }
        
        .empresas-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .empresa-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8f9fa;
            padding: 6px 14px;
            border-radius: 25px;
            border: 1px solid #dadce0;
            cursor: pointer;
            font-size: 13px;
        }
        
        .empresa-checkbox:hover {
            background: #e8eaed;
        }
        
        /* Tablas por empresa */
        .empresa-table {
            background: white;
            border-radius: 12px;
            margin-bottom: 30px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .table-header {
            background: #1a73e8;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h2 {
            font-size: 18px;
            font-weight: 600;
        }
        
        .total-empresa {
            background: rgba(255,255,255,0.2);
            padding: 6px 15px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 14px 12px;
            text-align: left;
            font-weight: 600;
            color: #5f6368;
            border-bottom: 1px solid #dadce0;
            font-size: 13px;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #e8eaed;
            color: #202124;
            font-size: 13px;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .costo {
            font-weight: 600;
            color: #1a73e8;
        }
        
        .acumulado {
            font-weight: 700;
            color: #34a853;
        }
        
        .sin-datos {
            text-align: center;
            padding: 50px;
            color: #5f6368;
        }
        
        @media (max-width: 768px) {
            .filtros-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filtro-group input {
                min-width: auto;
            }
            
            .table-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Informe de Viajes por Puesto de Salud</h1>
            <p>Reporte detallado con acumulado por empresa</p>
        </div>
        
        <form method="GET" action="" id="filtroForm">
            <div class="filtros-card">
                <div class="filtros-row">
                    <div class="filtro-group">
                        <label>📅 Fecha desde</label>
                        <input type="date" name="fecha_desde" value="<?php echo htmlspecialchars($fecha_desde); ?>">
                    </div>
                    <div class="filtro-group">
                        <label>📅 Fecha hasta</label>
                        <input type="date" name="fecha_hasta" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                    </div>
                    <div class="filtro-group">
                        <button type="submit" class="btn-filtrar">🔍 Filtrar</button>
                    </div>
                    <div class="filtro-group">
                        <a href="?" class="btn-limpiar">🗑️ Limpiar</a>
                    </div>
                </div>
                
                <div class="empresas-section">
                    <div class="empresas-header">
                        <h3>🏥 Puestos de Salud (P.)</h3>
                        <div class="btn-group">
                            <button type="button" class="btn-seleccion all" onclick="seleccionarTodas(true)">✅ Seleccionar todas</button>
                            <button type="button" class="btn-seleccion none" onclick="seleccionarTodas(false)">❌ Quitar todas</button>
                        </div>
                    </div>
                    <div class="empresas-grid" id="empresasGrid">
                        <?php if (empty($empresas_disponibles)): ?>
                            <span style="color: #ea4335;">⚠️ No se encontraron empresas que empiecen con "P."</span>
                        <?php else: ?>
                            <?php foreach ($empresas_disponibles as $empresa): ?>
                            <label class="empresa-checkbox">
                                <input type="checkbox" name="empresas[]" value="<?php echo htmlspecialchars($empresa); ?>" 
                                       <?php echo (in_array($empresa, $empresas_seleccionadas) || (empty($empresas_seleccionadas) && empty($_GET))) ? 'checked' : ''; ?>>
                                <?php echo htmlspecialchars($empresa); ?>
                            </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
        
        <?php
        // Procesar cada empresa seleccionada
        if (!empty($empresas_seleccionadas)) {
            foreach ($empresas_seleccionadas as $empresa_actual) {
                
                // Construir consulta para esta empresa específica
                $sql = "SELECT v.id, v.nombre, v.cedula, v.fecha, v.ruta, v.tipo_vehiculo, v.empresa, rc.clasificacion
                        FROM viajes v
                        LEFT JOIN ruta_clasificacion rc 
                            ON v.ruta = rc.ruta 
                            AND v.tipo_vehiculo = rc.tipo_vehiculo
                        WHERE v.empresa = ?";
                
                $params = array($empresa_actual);
                $types = "s";
                
                if (!empty($fecha_desde)) {
                    $sql .= " AND v.fecha >= ?";
                    $params[] = $fecha_desde;
                    $types .= "s";
                }
                if (!empty($fecha_hasta)) {
                    $sql .= " AND v.fecha <= ?";
                    $params[] = $fecha_hasta;
                    $types .= "s";
                }
                
                $sql .= " ORDER BY v.fecha ASC, v.id ASC";
                
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result && $result->num_rows > 0) {
                        $acumulado = 0;
                        $total_empresa = 0;
                        ?>
                        <div class="empresa-table">
                            <div class="table-header">
                                <h2>🏥 <?php echo htmlspecialchars($empresa_actual); ?></h2>
                                <div class="total-empresa" id="total-<?php echo md5($empresa_actual); ?>">
                                    Cargando...
                                </div>
                            </div>
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Fecha</th>
                                        <th>Conductor</th>
                                        <th>Cédula</th>
                                        <th>Ruta</th>
                                        <th>Tipo</th>
                                        <th>Clasificación</th>
                                        <th>Valor Viaje</th>
                                        <th>Acumulado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $contador = 0;
                                    while ($row = $result->fetch_assoc()):
                                        $contador++;
                                        $clasificacion = $row['clasificacion'];
                                        $costo = obtener_tarifa($clasificacion, $row['tipo_vehiculo'], $row['empresa'], $conn);
                                        $acumulado += $costo;
                                        $total_empresa += $costo;
                                    ?>
                                    <tr>
                                        <td><?php echo $contador; ?></td>
                                        <td><?php echo $row['fecha'] ? date('d/m/Y', strtotime($row['fecha'])) : '-'; ?></td>
                                        <td><strong><?php echo htmlspecialchars($row['nombre'] ?? '-'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['cedula'] ?? '-'); ?></td>
                                        <td style="max-width: 250px; word-break: break-word;"><?php echo htmlspecialchars($row['ruta'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['tipo_vehiculo'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($clasificacion ?? 'Sin clasificar'); ?></td>
                                        <td class="costo">$ <?php echo number_format($costo, 0, ',', '.'); ?></td>
                                        <td class="acumulado">$ <?php echo number_format($acumulado, 0, ',', '.'); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <tr style="background: #e8f0fe; font-weight: bold;">
                                        <td colspan="7" style="text-align: right;">TOTAL <?php echo htmlspecialchars($empresa_actual); ?>:</td>
                                        <td class="costo">$ <?php echo number_format($total_empresa, 0, ',', '.'); ?></td>
                                        <td class="acumulado">$ <?php echo number_format($total_empresa, 0, ',', '.'); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <?php
                    } else {
                        ?>
                        <div class="empresa-table">
                            <div class="table-header">
                                <h2>🏥 <?php echo htmlspecialchars($empresa_actual); ?></h2>
                            </div>
                            <div class="sin-datos">
                                📭 No hay viajes registrados para este período
                            </div>
                        </div>
                        <?php
                    }
                    $stmt->close();
                }
            }
        } else {
            ?>
            <div class="empresa-table">
                <div class="sin-datos">
                    🔍 Selecciona al menos una empresa en los filtros para ver el informe
                </div>
            </div>
            <?php
        }
        
        $conn->close();
        ?>
    </div>
    
    <script>
        function seleccionarTodas(seleccionar) {
            const checkboxes = document.querySelectorAll('#empresasGrid input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = seleccionar;
            });
            document.getElementById('filtroForm').submit();
        }
        
        // Auto-submit al cambiar checkbox
        document.querySelectorAll('#empresasGrid input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                document.getElementById('filtroForm').submit();
            });
        });
    </script>
</body>
</html>