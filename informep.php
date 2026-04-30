<?php
// Conexión BD
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Procesar filtros
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';
$empresas_seleccionadas = isset($_GET['empresas']) ? $_GET['empresas'] : array();

// Obtener empresas que empiezan con 'p' (mayúscula o minúscula)
$sql_empresas = "SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa != '' AND LOWER(empresa) LIKE 'p%' ORDER BY empresa";
$result_empresas = $conn->query($sql_empresas);
$empresas_disponibles = array();
while ($row = $result_empresas->fetch_assoc()) {
    $empresas_disponibles[] = $row['empresa'];
}

// Construir consulta principal
$sql = "SELECT v.*, rc.clasificacion 
        FROM viajes v
        LEFT JOIN ruta_clasificacion rc ON v.ruta = rc.ruta AND v.tipo_vehiculo = rc.tipo_vehiculo
        WHERE 1=1";

$params = array();
$types = "";

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
if (!empty($empresas_seleccionadas)) {
    $placeholders = implode(',', array_fill(0, count($empresas_seleccionadas), '?'));
    $sql .= " AND v.empresa IN ($placeholders)";
    foreach ($empresas_seleccionadas as $emp) {
        $params[] = $emp;
        $types .= "s";
    }
}

$sql .= " ORDER BY v.fecha DESC, v.id DESC";

// Preparar y ejecutar consulta
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Función para obtener tarifa según clasificación y empresa
function obtener_tarifa($clasificacion, $tipo_vehiculo, $empresa, $conn) {
    if (empty($clasificacion) || empty($empresa)) {
        return 0;
    }
    
    $sql_tarifa = "SELECT completo, medio, extra, carrotanque, siapana, 
                   riohacha_completo, riohacha_medio, nazareth_siapana_maicao, 
                   nazareth_siapana_flor_de_la_guajira
                   FROM tarifas 
                   WHERE empresa = ? AND tipo_vehiculo = ?";
    $stmt_tar = $conn->prepare($sql_tarifa);
    $stmt_tar->bind_param("ss", $empresa, $tipo_vehiculo);
    $stmt_tar->execute();
    $result_tar = $stmt_tar->get_result();
    $tarifa = $result_tar->fetch_assoc();
    $stmt_tar->close();
    
    if (!$tarifa) {
        return 0;
    }
    
    switch($clasificacion) {
        case 'completo':
            return isset($tarifa['completo']) ? floatval($tarifa['completo']) : 0;
        case 'medio':
            return isset($tarifa['medio']) ? floatval($tarifa['medio']) : 0;
        case 'extra':
            return isset($tarifa['extra']) ? floatval($tarifa['extra']) : 0;
        case 'carrotanque':
            return isset($tarifa['carrotanque']) ? floatval($tarifa['carrotanque']) : 0;
        case 'siapana':
            return isset($tarifa['siapana']) ? floatval($tarifa['siapana']) : 0;
        case 'riohacha_completo':
            return isset($tarifa['riohacha_completo']) ? floatval($tarifa['riohacha_completo']) : 0;
        case 'riohacha_medio':
            return isset($tarifa['riohacha_medio']) ? floatval($tarifa['riohacha_medio']) : 0;
        default:
            return 0;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vista de Viajes</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        /* Encabezado */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
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
        .filtros {
            background: white;
            padding: 20px 30px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-end;
        }
        
        .filtro-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filtro-group label {
            font-size: 12px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filtro-group input, .filtro-group select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            min-width: 200px;
            transition: all 0.3s;
        }
        
        .filtro-group input:focus, .filtro-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        .btn-filtrar {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-filtrar:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }
        
        .btn-limpiar {
            background: #e2e8f0;
            color: #4a5568;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-limpiar:hover {
            background: #cbd5e0;
        }
        
        /* Empresas */
        .empresas-section {
            background: #f8f9fa;
            padding: 15px 30px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .empresas-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .empresas-header h3 {
            font-size: 14px;
            color: #4a5568;
            font-weight: 600;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
        }
        
        .btn-seleccion {
            background: white;
            border: 1px solid #cbd5e0;
            padding: 5px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-seleccion:hover {
            background: #e2e8f0;
        }
        
        .btn-seleccion.all {
            background: #48bb78;
            border-color: #48bb78;
            color: white;
        }
        
        .btn-seleccion.all:hover {
            background: #38a169;
        }
        
        .btn-seleccion.none {
            background: #f56565;
            border-color: #f56565;
            color: white;
        }
        
        .btn-seleccion.none:hover {
            background: #e53e3e;
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
            background: white;
            padding: 6px 14px;
            border-radius: 20px;
            border: 1px solid #cbd5e0;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 13px;
        }
        
        .empresa-checkbox:hover {
            background: #edf2f7;
            border-color: #a0aec0;
        }
        
        .empresa-checkbox input {
            cursor: pointer;
            width: 16px;
            height: 16px;
        }
        
        .empresa-checkbox.selected {
            background: #e0e7ff;
            border-color: #667eea;
        }
        
        /* Tabla */
        .tabla-container {
            overflow-x: auto;
            padding: 0 30px 30px 30px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        th {
            background: #f7fafc;
            padding: 14px 12px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
            position: sticky;
            top: 0;
            white-space: nowrap;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            color: #2d3748;
        }
        
        tr:hover {
            background: #f7fafc;
        }
        
        .costo {
            font-weight: 600;
            color: #2b6cb0;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-completo { background: #c6f6d5; color: #22543d; }
        .badge-medio { background: #feebc8; color: #7c2d12; }
        .badge-extra { background: #fed7d7; color: #742a2a; }
        .badge-carrotanque { background: #e9d8fd; color: #44337a; }
        .badge-siapana { background: #bee3f8; color: #2c5282; }
        .badge-default { background: #e2e8f0; color: #4a5568; }
        
        .total-row {
            background: #edf2f7;
            font-weight: 600;
        }
        
        .total-row td {
            border-top: 2px solid #cbd5e0;
            padding: 15px 12px;
        }
        
        .sin-datos {
            text-align: center;
            padding: 50px;
            color: #a0aec0;
        }
        
        @media (max-width: 768px) {
            .filtros {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filtro-group input, .filtro-group select {
                min-width: auto;
            }
            
            .empresas-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header, .filtros, .empresas-section, .tabla-container {
                padding-left: 15px;
                padding-right: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Gestión de Viajes</h1>
            <p>Consulta y filtrado de viajes con cálculo automático de costos</p>
        </div>
        
        <form method="GET" action="">
            <div class="filtros">
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
                    <a href="?" class="btn-limpiar">🗑️ Limpiar filtros</a>
                </div>
            </div>
            
            <div class="empresas-section">
                <div class="empresas-header">
                    <h3>🏢 Empresas (que empiezan con "P")</h3>
                    <div class="btn-group">
                        <button type="button" class="btn-seleccion all" onclick="seleccionarTodas(true)">✅ Seleccionar todas</button>
                        <button type="button" class="btn-seleccion none" onclick="seleccionarTodas(false)">❌ Quitar todas</button>
                    </div>
                </div>
                <div class="empresas-grid" id="empresasGrid">
                    <?php foreach ($empresas_disponibles as $empresa): ?>
                    <label class="empresa-checkbox">
                        <input type="checkbox" name="empresas[]" value="<?php echo htmlspecialchars($empresa); ?>" 
                               <?php echo (in_array($empresa, $empresas_seleccionadas) || (empty($empresas_seleccionadas) && empty($_GET))) ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($empresa); ?>
                    </label>
                    <?php endforeach; ?>
                    <?php if (empty($empresas_disponibles)): ?>
                        <span style="color: #a0aec0; font-size: 13px;">No hay empresas que empiecen con "P"</span>
                    <?php endif; ?>
                </div>
            </div>
        </form>
        
        <div class="tabla-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Cédula</th>
                        <th>Fecha</th>
                        <th>Ruta</th>
                        <th>Tipo Vehículo</th>
                        <th>Empresa</th>
                        <th>Clasificación</th>
                        <th>Costo Viaje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_general = 0;
                    $contador = 0;
                    
                    if ($result && $result->num_rows > 0):
                        while ($row = $result->fetch_assoc()):
                            $clasificacion = $row['clasificacion'];
                            $costo = obtener_tarifa($clasificacion, $row['tipo_vehiculo'], $row['empresa'], $conn);
                            $total_general += $costo;
                            $contador++;
                            
                            // Clase CSS para badge según clasificación
                            $badge_class = 'badge-default';
                            if (strpos($clasificacion, 'completo') !== false) $badge_class = 'badge-completo';
                            elseif (strpos($clasificacion, 'medio') !== false) $badge_class = 'badge-medio';
                            elseif (strpos($clasificacion, 'extra') !== false) $badge_class = 'badge-extra';
                            elseif (strpos($clasificacion, 'carrotanque') !== false) $badge_class = 'badge-carrotanque';
                            elseif (strpos($clasificacion, 'siapana') !== false) $badge_class = 'badge-siapana';
                            ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['nombre'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['cedula'] ?? ''); ?></td>
                                <td><?php echo $row['fecha'] ? date('d/m/Y', strtotime($row['fecha'])) : ''; ?></td>
                                <td><?php echo htmlspecialchars($row['ruta'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['tipo_vehiculo'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['empresa'] ?? ''); ?></td>
                                <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($clasificacion ?? 'Sin clasificar'); ?></span></td>
                                <td class="costo"><?php echo $costo > 0 ? '$ ' . number_format($costo, 0, ',', '.') : '-'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <tr class="total-row">
                            <td colspan="8" style="text-align: right; font-weight: 600;">TOTAL GENERAL:</td>
                            <td class="costo" style="font-size: 16px;">$ <?php echo number_format($total_general, 0, ',', '.'); ?></td>
                        </tr>
                        <tr>
                            <td colspan="9" style="background: #f7fafc; text-align: center; font-size: 12px; color: #718096;">
                                📊 Mostrando <?php echo $contador; ?> viajes | Total recaudado: $ <?php echo number_format($total_general, 0, ',', '.'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="sin-datos">
                                🚫 No se encontraron viajes con los filtros seleccionados
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        function seleccionarTodas(seleccionar) {
            const checkboxes = document.querySelectorAll('#empresasGrid input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = seleccionar;
            });
            // Enviar el formulario automáticamente después de seleccionar
            document.querySelector('form').submit();
        }
        
        // Enviar formulario cuando se cambia un checkbox
        document.querySelectorAll('#empresasGrid input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                document.querySelector('form').submit();
            });
        });
    </script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>