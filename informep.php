<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Obtener parámetros
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';
$empresas_seleccionadas = isset($_GET['empresas']) ? $_GET['empresas'] : array();

// Obtener empresas que empiezan con P
$empresas_disponibles = array();
$sql_emp = "SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa != '' AND empresa LIKE 'P%' ORDER BY empresa";
$res_emp = $conn->query($sql_emp);
if ($res_emp) {
    while ($row = $res_emp->fetch_assoc()) {
        $empresas_disponibles[] = $row['empresa'];
    }
}

// Construir WHERE
$where = "1=1";
$params = array();
$types = "";

if ($fecha_desde) {
    $where .= " AND fecha >= ?";
    $params[] = $fecha_desde;
    $types .= "s";
}
if ($fecha_hasta) {
    $where .= " AND fecha <= ?";
    $params[] = $fecha_hasta;
    $types .= "s";
}
if (!empty($empresas_seleccionadas)) {
    $placeholders = implode(',', array_fill(0, count($empresas_seleccionadas), '?'));
    $where .= " AND empresa IN ($placeholders)";
    foreach ($empresas_seleccionadas as $emp) {
        $params[] = $emp;
        $types .= "s";
    }
}

// Consulta principal
$sql = "SELECT v.*, rc.clasificacion 
        FROM viajes v
        LEFT JOIN ruta_clasificacion rc ON v.ruta = rc.ruta AND v.tipo_vehiculo = rc.tipo_vehiculo
        WHERE $where 
        ORDER BY v.fecha DESC, v.id DESC";

// Ejecutar consulta
$stmt = $conn->prepare($sql);
if ($stmt && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
if ($stmt && $stmt->execute()) {
    $result = $stmt->get_result();
} else {
    $result = false;
    echo "Error en consulta: " . ($stmt ? $stmt->error : $conn->error);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Vista de Viajes</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .filtros { margin-bottom: 20px; padding: 15px; background: #f0f0f0; border-radius: 5px; }
        .filtro-group { display: inline-block; margin-right: 15px; }
        .empresas-grid { margin: 10px 0; display: flex; flex-wrap: wrap; gap: 10px; }
        .empresa-checkbox { display: inline-flex; align-items: center; gap: 5px; margin-right: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f2f2f2; }
        .total-row { background: #e8e8e8; font-weight: bold; }
        .btn { padding: 8px 15px; margin: 5px; cursor: pointer; }
        .btn-primary { background: #007bff; color: white; border: none; border-radius: 4px; }
        .btn-secondary { background: #6c757d; color: white; border: none; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>📋 Gestión de Viajes</h1>
    
    <form method="GET" action="">
        <div class="filtros">
            <div class="filtro-group">
                <label>Fecha desde:</label>
                <input type="date" name="fecha_desde" value="<?php echo htmlspecialchars($fecha_desde); ?>">
            </div>
            <div class="filtro-group">
                <label>Fecha hasta:</label>
                <input type="date" name="fecha_hasta" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
            </div>
            <div class="filtro-group">
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </div>
            <div class="filtro-group">
                <a href="?" class="btn btn-secondary">Limpiar</a>
            </div>
        </div>
        
        <div>
            <h3>Empresas (con P):</h3>
            <div class="empresas-grid">
                <label class="empresa-checkbox">
                    <input type="checkbox" id="selectAll"> Seleccionar todas
                </label>
                <?php foreach ($empresas_disponibles as $empresa): ?>
                <label class="empresa-checkbox">
                    <input type="checkbox" name="empresas[]" value="<?php echo htmlspecialchars($empresa); ?>" 
                           <?php echo (in_array($empresa, $empresas_seleccionadas) || (empty($empresas_seleccionadas) && empty($_GET))) ? 'checked' : ''; ?>>
                    <?php echo htmlspecialchars($empresa); ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </form>
    
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr><th>ID</th><th>Nombre</th><th>Cédula</th><th>Fecha</th><th>Ruta</th><th>Tipo</th><th>Empresa</th><th>Clasificación</th></tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['nombre'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['cedula'] ?? ''); ?></td>
                        <td><?php echo $row['fecha']; ?></td>
                        <td><?php echo htmlspecialchars($row['ruta'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['tipo_vehiculo'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['empresa'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['clasificacion'] ?? 'Sin clasificar'); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align:center;">No hay datos</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <script>
        document.getElementById('selectAll')?.addEventListener('change', function(e) {
            document.querySelectorAll('input[name="empresas[]"]').forEach(cb => cb.checked = e.target.checked);
            document.querySelector('form').submit();
        });
        document.querySelectorAll('input[name="empresas[]"]').forEach(cb => {
            cb.addEventListener('change', () => document.querySelector('form').submit());
        });
    </script>
</body>
</html>
<?php
if ($stmt) $stmt->close();
$conn->close();
?>