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

// Variables para mantener los valores del formulario
$deudores_seleccionados = [];
$prestamista_seleccionado = '';
$porcentaje_interes = 10; // Por defecto 10%

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deudores_seleccionados = $_POST['deudores'] ?? [];
    $prestamista_seleccionado = $_POST['prestamista'] ?? '';
    $porcentaje_interes = floatval($_POST['porcentaje_interes']) ?? 10;
    
    if (!empty($deudores_seleccionados) && !empty($prestamista_seleccionado)) {
        // Calcular factor de interés mensual
        $factor_interes_mensual = $porcentaje_interes / 100;
        
        // Consulta para obtener los préstamos con cálculo de meses
        $placeholders = str_repeat('?,', count($deudores_seleccionados) - 1) . '?';
        
        $sql = "SELECT 
                    deudor,
                    prestamista,
                    monto,
                    fecha,
                    DATEDIFF(CURDATE(), fecha) as dias_transcurridos,
                    FLOOR(DATEDIFF(CURDATE(), fecha) / 30) as meses_transcurridos,
                    CASE 
                        WHEN FLOOR(DATEDIFF(CURDATE(), fecha) / 30) < 1 THEN 1
                        ELSE FLOOR(DATEDIFF(CURDATE(), fecha) / 30)
                    END as meses_a_cobrar
                FROM prestamos 
                WHERE deudor IN ($placeholders) 
                AND prestamista = ?
                AND pagado = 0
                ORDER BY deudor, fecha";
        
        $stmt = $conn->prepare($sql);
        $types = str_repeat('s', count($deudores_seleccionados)) . 's';
        $params = array_merge($deudores_seleccionados, [$prestamista_seleccionado]);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result_detalle = $stmt->get_result();
        
        // Procesar los datos para agrupar por deudor
        $prestamos_por_deudor = [];
        
        while($fila = $result_detalle->fetch_assoc()) {
            $deudor = $fila['deudor'];
            $meses = $fila['meses_a_cobrar'];
            $interes_prestamo = $fila['monto'] * $factor_interes_mensual * $meses;
            $total_prestamo = $fila['monto'] + $interes_prestamo;
            
            if (!isset($prestamos_por_deudor[$deudor])) {
                $prestamos_por_deudor[$deudor] = [
                    'total_capital' => 0,
                    'total_interes' => 0,
                    'total_general' => 0,
                    'cantidad_prestamos' => 0,
                    'prestamos_detalle' => []
                ];
            }
            
            $prestamos_por_deudor[$deudor]['total_capital'] += $fila['monto'];
            $prestamos_por_deudor[$deudor]['total_interes'] += $interes_prestamo;
            $prestamos_por_deudor[$deudor]['total_general'] += $total_prestamo;
            $prestamos_por_deudor[$deudor]['cantidad_prestamos']++;
            
            $prestamos_por_deudor[$deudor]['prestamos_detalle'][] = [
                'monto' => $fila['monto'],
                'fecha' => $fila['fecha'],
                'meses' => $meses,
                'interes' => $interes_prestamo,
                'total' => $total_prestamo
            ];
        }
        
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
        .container { max-width: 1400px; margin: 20px auto; padding: 20px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        select, button, input { width: 100%; padding: 10px; margin: 5px 0; }
        select[multiple] { height: 200px; }
        .resultados { margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .totales { background-color: #e8f4fd; font-weight: bold; }
        .moneda { text-align: right; }
        .form-row { display: flex; gap: 20px; }
        .form-col { flex: 1; }
        .detalle-toggle { cursor: pointer; color: #007bff; }
        .detalle-prestamo { display: none; background-color: #f9f9f9; }
        .detalle-prestamo td { padding: 5px 8px; font-size: 0.9em; }
        .meses { text-align: center; }
        .header-deudor { background-color: #e9ecef; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Reporte de Préstamos Consolidados</h1>
        
        <form method="POST">
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="deudores">Seleccionar Deudores (Múltiple):</label>
                        <select name="deudores[]" id="deudores" multiple required>
                            <?php while($deudor = $result_deudores->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($deudor['deudor']) ?>" 
                                    <?= in_array($deudor['deudor'], $deudores_seleccionados) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($deudor['deudor']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <small>Mantén presionado Ctrl para seleccionar múltiples deudores</small>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="prestamista">Seleccionar Prestamista:</label>
                        <select name="prestamista" id="prestamista" required>
                            <option value="">-- Seleccionar Prestamista --</option>
                            <?php 
                            $result_prestamistas->data_seek(0);
                            while($prestamista = $result_prestamistas->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($prestamista['prestamista']) ?>" 
                                    <?= $prestamista_seleccionado == $prestamista['prestamista'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($prestamista['prestamista']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="porcentaje_interes">Interés Mensual (%):</label>
                        <input type="number" name="porcentaje_interes" id="porcentaje_interes" 
                               value="<?= $porcentaje_interes ?>" step="0.1" min="0" max="100" required>
                        <small>Interés mensual (10%, 13%, etc.)</small>
                    </div>
                    
                    <button type="submit">Generar Reporte</button>
                </div>
            </div>
        </form>

        <?php if (isset($prestamos_por_deudor)): ?>
        <div class="resultados">
            <h2>Resultados para: <?= htmlspecialchars($prestamista_seleccionado) ?> (Interés: <?= $porcentaje_interes ?>% mensual)</h2>
            
            <table>
                <thead>
                    <tr>
                        <th>Deudor</th>
                        <th>Préstamos</th>
                        <th>Capital</th>
                        <th>Interés (<?= $porcentaje_interes ?>% mensual)</th>
                        <th>Total a Pagar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($prestamos_por_deudor as $deudor => $datos): ?>
                    <?php 
                        $total_capital_general += $datos['total_capital'];
                        $total_interes_general += $datos['total_interes'];
                        $total_general += $datos['total_general'];
                    ?>
                    <tr class="header-deudor">
                        <td>
                            <span class="detalle-toggle" onclick="toggleDetalle('<?= md5($deudor) ?>')">
                                📊 <?= htmlspecialchars($deudor) ?>
                            </span>
                        </td>
                        <td><?= $datos['cantidad_prestamos'] ?></td>
                        <td class="moneda">$ <?= number_format($datos['total_capital'], 0, ',', '.') ?></td>
                        <td class="moneda">$ <?= number_format($datos['total_interes'], 0, ',', '.') ?></td>
                        <td class="moneda">$ <?= number_format($datos['total_general'], 0, ',', '.') ?></td>
                    </tr>
                    
                    <!-- Detalle de cada préstamo -->
                    <tr class="detalle-prestamo" id="detalle-<?= md5($deudor) ?>">
                        <td colspan="5">
                            <table style="width: 100%; background-color: white;">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Monto</th>
                                        <th>Meses</th>
                                        <th>Interés</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($datos['prestamos_detalle'] as $detalle): ?>
                                    <tr>
                                        <td><?= $detalle['fecha'] ?></td>
                                        <td class="moneda">$ <?= number_format($detalle['monto'], 0, ',', '.') ?></td>
                                        <td class="meses"><?= $detalle['meses'] ?></td>
                                        <td class="moneda">$ <?= number_format($detalle['interes'], 0, ',', '.') ?></td>
                                        <td class="moneda">$ <?= number_format($detalle['total'], 0, ',', '.') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
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
        
        // Función para mostrar/ocultar detalle
        function toggleDetalle(id) {
            const detalle = document.getElementById('detalle-' + id);
            detalle.style.display = detalle.style.display === 'none' ? 'table-row' : 'none';
        }
        
        // Seleccionar todos los deudores con doble click en el label
        document.querySelector('label[for="deudores"]').addEventListener('dblclick', function() {
            const select = document.getElementById('deudores');
            for (let option of select.options) {
                option.selected = true;
            }
        });
    </script>
</body>
</html>

<?php
// Cerrar conexión
$conn->close();
?>