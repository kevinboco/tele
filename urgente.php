<?php
// Conexión a la base de datos
define('DB_HOST', 'mysql.hostinger.com');
define('DB_USER', 'u648222299_keboco5');
define('DB_PASS', 'Bucaramanga3011');
define('DB_NAME', 'u648222299_viajes');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Obtener todos los deudores únicos de la base de datos
$sql_deudores = "SELECT DISTINCT deudor FROM prestamos WHERE deudor != '' ORDER BY deudor";
$result_deudores = $conn->query($sql_deudores);

// Obtener todos los prestamistas únicos de la base de datos
$sql_prestamistas = "SELECT DISTINCT prestamista FROM prestamos WHERE prestamista != '' ORDER BY prestamista";
$result_prestamistas = $conn->query($sql_prestamistas);

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deudores_seleccionados = $_POST['deudores'] ?? [];
    $prestamista_seleccionado = $_POST['prestamista'] ?? '';
    
    if (!empty($deudores_seleccionados) && !empty($prestamista_seleccionado)) {
        // Consulta para obtener los préstamos
        $placeholders = str_repeat('?,', count($deudores_seleccionados) - 1) . '?';
        
        $sql = "SELECT 
                    deudor,
                    prestamista,
                    SUM(monto) as total_capital,
                    SUM(monto) * 0.20 as total_interes,
                    SUM(monto) * 1.20 as total_general,
                    COUNT(*) as cantidad_prestamos
                FROM prestamos 
                WHERE deudor IN ($placeholders) 
                AND prestamista = ?
                AND pagado = 0
                GROUP BY deudor, prestamista
                ORDER BY deudor";
        
        $stmt = $conn->prepare($sql);
        $types = str_repeat('s', count($deudores_seleccionados)) . 's';
        $params = array_merge($deudores_seleccionados, [$prestamista_seleccionado]);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $resultado = $stmt->get_result();
        
        // Calcular totales generales
        $total_capital_general = 0;
        $total_interes_general = 0;
        $total_general = 0;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Préstamos</title>
    <style>
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        select, button { width: 100%; padding: 10px; margin: 5px 0; }
        select[multiple] { height: 200px; }
        .resultados { margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #f2f2f2; }
        .totales { background-color: #e8f4fd; font-weight: bold; }
        .moneda { text-align: right; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Reporte de Préstamos Consolidados</h1>
        
        <form method="POST">
            <div class="form-group">
                <label for="deudores">Seleccionar Deudores (Múltiple):</label>
                <select name="deudores[]" id="deudores" multiple required>
                    <?php while($deudor = $result_deudores->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($deudor['deudor']) ?>">
                            <?= htmlspecialchars($deudor['deudor']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <small>Mantén presionado Ctrl para seleccionar múltiples deudores</small>
            </div>
            
            <div class="form-group">
                <label for="prestamista">Seleccionar Prestamista:</label>
                <select name="prestamista" id="prestamista" required>
                    <option value="">-- Seleccionar Prestamista --</option>
                    <?php while($prestamista = $result_prestamistas->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($prestamista['prestamista']) ?>">
                            <?= htmlspecialchars($prestamista['prestamista']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <button type="submit">Generar Reporte</button>
        </form>

        <?php if (isset($resultado)): ?>
        <div class="resultados">
            <h2>Resultados para: <?= htmlspecialchars($prestamista_seleccionado) ?></h2>
            
            <table>
                <thead>
                    <tr>
                        <th>Deudor</th>
                        <th>Préstamos</th>
                        <th>Capital</th>
                        <th>Interés (20%)</th>
                        <th>Total a Pagar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($fila = $resultado->fetch_assoc()): ?>
                    <?php 
                        $total_capital_general += $fila['total_capital'];
                        $total_interes_general += $fila['total_interes'];
                        $total_general += $fila['total_general'];
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($fila['deudor']) ?></td>
                        <td><?= $fila['cantidad_prestamos'] ?></td>
                        <td class="moneda">$ <?= number_format($fila['total_capital'], 0, ',', '.') ?></td>
                        <td class="moneda">$ <?= number_format($fila['total_interes'], 0, ',', '.') ?></td>
                        <td class="moneda">$ <?= number_format($fila['total_general'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endwhile; ?>
                    
                    <!-- Totales generales -->
                    <tr class="totales">
                        <td colspan="2"><strong>TOTAL GENERAL</strong></td>
                        <td class="moneda"><strong>$ <?= number_format($total_capital_general, 0, ',', '.') ?></strong></td>
                        <td class="moneda"><strong>$ <?= number_format($total_interes_general, 0, ',', '.') ?></strong></td>
                        <td class="moneda"><strong>$ <?= number_format($total_general, 0, ',', '.') ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Para hacer más fácil la selección múltiple
        document.getElementById('deudores').addEventListener('dblclick', function(e) {
            if (e.target.tagName === 'OPTION') {
                e.target.selected = !e.target.selected;
            }
        });
    </script>
</body>
</html>

<?php
// Cerrar conexión
$conn->close();
?>