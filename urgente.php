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

// Función para calcular meses automáticamente - REGLA DEL DÍA 10
function calcularMesesAutomaticos($fecha_prestamo) {
    $hoy = new DateTime();
    $fecha_prestamo_obj = new DateTime($fecha_prestamo);
    
    if ($fecha_prestamo_obj > $hoy) {
        return 1;
    }
    
    $meses = 0;
    $fecha_temp = clone $fecha_prestamo_obj;
    
    while ($fecha_temp <= $hoy) {
        $meses++;
        $fecha_temp->modify('+1 month');
    }
    
    $dia_prestamo = $fecha_prestamo_obj->format('d');
    $dia_hoy = $hoy->format('d');
    
    if ($dia_prestamo < 10 && $dia_hoy >= 10) {
        // Ya pasó el día 10 del mes actual, contar mes completo
    } else {
        $meses = max(1, $meses - 1);
    }
    
    return max(1, $meses);
}

// Función para determinar si aplicar comisión a Alexander (desde 18-11-2025)
function aplicarComisionAlexander($fecha_prestamo) {
    $fecha_limite = new DateTime('2025-11-18');
    $fecha_prestamo_obj = new DateTime($fecha_prestamo);
    
    return ($fecha_prestamo_obj >= $fecha_limite);
}

// Variables para mantener los valores del formulario
$deudores_seleccionados = [];
$prestamista_seleccionado = '';
$porcentaje_interes = 10;
$comision_celene = 5; // Tu comisión por defecto para Celene
$interes_celene = 8; // Interés para Celene por defecto
$comision_alexander = 3; // Tu comisión por defecto para Alexander
$interes_alexander = 10; // Interés para Alexander por defecto

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deudores_seleccionados = $_POST['deudores'] ?? [];
    $prestamista_seleccionado = $_POST['prestamista'] ?? '';
    $porcentaje_interes = floatval($_POST['porcentaje_interes']) ?? 10;
    $comision_celene = floatval($_POST['comision_celene']) ?? 5;
    $interes_celene = floatval($_POST['interes_celene']) ?? 8;
    $comision_alexander = floatval($_POST['comision_alexander']) ?? 3;
    $interes_alexander = floatval($_POST['interes_alexander']) ?? 10;
    
    if (!empty($deudores_seleccionados) && !empty($prestamista_seleccionado)) {
        // Consulta para obtener los préstamos
        $placeholders = str_repeat('?,', count($deudores_seleccionados) - 1) . '?';
        
        $sql = "SELECT 
                    id,
                    deudor,
                    prestamista,
                    monto,
                    fecha
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
        $es_celene = ($prestamista_seleccionado == 'Celene');
        $es_alexander = ($prestamista_seleccionado == 'Alexander Peralta');
        $es_prestamista_especial = ($es_celene || $es_alexander);
        
        while($fila = $result_detalle->fetch_assoc()) {
            $deudor = $fila['deudor'];
            $meses = calcularMesesAutomaticos($fila['fecha']);
            $aplica_comision_alexander = ($es_alexander && aplicarComisionAlexander($fila['fecha']));
            
            if ($es_celene) {
                // Para Celene: Capital + Interés Celene + Comisión
                $interes_celene_monto = $fila['monto'] * ($interes_celene / 100) * $meses;
                $comision_monto = $fila['monto'] * ($comision_celene / 100) * $meses;
                $total_prestamo = $fila['monto'] + $interes_celene_monto + $comision_monto;
                $interes_alexander_monto = 0;
            } elseif ($es_alexander) {
                if ($aplica_comision_alexander) {
                    // Para Alexander CON comisión (desde 18-11-2025): Capital + Interés Alexander + Comisión
                    $interes_alexander_monto = $fila['monto'] * ($interes_alexander / 100) * $meses;
                    $comision_monto = $fila['monto'] * ($comision_alexander / 100) * $meses;
                    $total_prestamo = $fila['monto'] + $interes_alexander_monto + $comision_monto;
                } else {
                    // Para Alexander SIN comisión (antes de 18-11-2025): Capital + Interés Total normal AL 10%
                    $interes_total = $fila['monto'] * ($porcentaje_interes / 100) * $meses;
                    $interes_alexander_monto = $interes_total; // Alexander recibe todo el interés
                    $comision_monto = 0;
                    $total_prestamo = $fila['monto'] + $interes_total;
                }
                $interes_celene_monto = 0;
            } else {
                // Para otros prestamistas: cálculo normal
                $interes_total = $fila['monto'] * ($porcentaje_interes / 100) * $meses;
                $interes_celene_monto = 0;
                $interes_alexander_monto = 0;
                $comision_monto = 0;
                $total_prestamo = $fila['monto'] + $interes_total;
            }
            
            if (!isset($prestamos_por_deudor[$deudor])) {
                $prestamos_por_deudor[$deudor] = [
                    'total_capital' => 0,
                    'total_general' => 0,
                    'total_interes_celene' => 0,
                    'total_interes_alexander' => 0,
                    'total_comision' => 0,
                    'cantidad_prestamos' => 0,
                    'prestamos_detalle' => []
                ];
            }
            
            $prestamos_por_deudor[$deudor]['total_capital'] += $fila['monto'];
            $prestamos_por_deudor[$deudor]['total_general'] += $total_prestamo;
            $prestamos_por_deudor[$deudor]['total_interes_celene'] += $interes_celene_monto;
            $prestamos_por_deudor[$deudor]['total_interes_alexander'] += $interes_alexander_monto;
            $prestamos_por_deudor[$deudor]['total_comision'] += $comision_monto;
            $prestamos_por_deudor[$deudor]['cantidad_prestamos']++;
            
            $prestamos_por_deudor[$deudor]['prestamos_detalle'][] = [
                'id' => $fila['id'],
                'monto' => $fila['monto'],
                'fecha' => $fila['fecha'],
                'meses' => $meses,
                'interes_celene' => $interes_celene_monto,
                'interes_alexander' => $interes_alexander_monto,
                'comision' => $comision_monto,
                'total' => $total_prestamo,
                'aplica_comision' => $aplica_comision_alexander,
                'incluido' => true
            ];
        }
        
        // Calcular totales generales
        $total_capital_general = 0;
        $total_general = 0;
        $total_interes_celene_general = 0;
        $total_interes_alexander_general = 0;
        $total_comision_general = 0;
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
        .container { max-width: 1600px; margin: 20px auto; padding: 20px; }
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
        .excluido { background-color: #ffe6e6; text-decoration: line-through; color: #999; }
        .interes-input, .meses-input, .comision-input { width: 70px; padding: 4px; text-align: center; }
        .checkbox-excluir { transform: scale(1.2); }
        .acciones { text-align: center; }
        .info-meses { background-color: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .config-especial { background-color: #e7f3ff; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #007bff; }
        .comision-especial { background-color: #d4edda; }
        .interes-especial { background-color: #fff3cd; }
        .sin-comision { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Reporte de Préstamos Consolidados</h1>
        
        <form method="POST" id="formPrincipal">
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
                        <select name="prestamista" id="prestamista" required onchange="toggleConfigEspecial()">
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
                    
                    <?php if ($prestamista_seleccionado != 'Celene' && $prestamista_seleccionado != 'Alexander Peralta'): ?>
                    <div class="form-group">
                        <label for="porcentaje_interes">Interés Total (%):</label>
                        <input type="number" name="porcentaje_interes" id="porcentaje_interes" 
                               value="<?= $porcentaje_interes ?>" step="0.1" min="0" max="100" required>
                        <small>Interés total que paga el deudor</small>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Configuración especial para Celene -->
                    <div id="configCelene" class="config-especial" style="display: <?= $prestamista_seleccionado == 'Celene' ? 'block' : 'none' ?>;">
                        <h4>💰 Configuración para Celene</h4>
                        <div class="form-row">
                            <div class="form-col">
                                <label for="interes_celene">Interés para Celene (%):</label>
                                <input type="number" name="interes_celene" id="interes_celene" 
                                       value="<?= $interes_celene ?>" step="0.1" min="0" max="100" required>
                                <small>Lo que recibe Celene</small>
                            </div>
                            <div class="form-col">
                                <label for="comision_celene">Tu Comisión (%):</label>
                                <input type="number" name="comision_celene" id="comision_celene" 
                                       value="<?= $comision_celene ?>" step="0.1" min="0" max="100" required>
                                <small>Lo que recibes tú</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Configuración especial para Alexander Peralta -->
                    <div id="configAlexander" class="config-especial" style="display: <?= $prestamista_seleccionado == 'Alexander Peralta' ? 'block' : 'none' ?>;">
                        <h4>💰 Configuración para Alexander Peralta</h4>
                        <div class="form-row">
                            <div class="form-col">
                                <label for="interes_alexander">Interés para Alexander (%):</label>
                                <input type="number" name="interes_alexander" id="interes_alexander" 
                                       value="<?= $interes_alexander ?>" step="0.1" min="0" max="100" required>
                                <small>Lo que recibe Alexander</small>
                            </div>
                            <div class="form-col">
                                <label for="comision_alexander">Tu Comisión (%):</label>
                                <input type="number" name="comision_alexander" id="comision_alexander" 
                                       value="<?= $comision_alexander ?>" step="0.1" min="0" max="100" required>
                                <small>Lo que recibes tú (aplica desde 18-11-2025)</small>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit">Generar Reporte</button>
                </div>
            </div>
        </form>

        <?php if (isset($prestamos_por_deudor)): ?>
        <div class="resultados">
            <h2>Resultados para: <?= htmlspecialchars($prestamista_seleccionado) ?></h2>
            
            <?php if ($prestamista_seleccionado == 'Celene'): ?>
            <div class="info-meses">
                <strong>💰 Distribución para Celene:</strong><br>
                - <strong>Celene recibe:</strong> Capital + <?= $interes_celene ?>% interés<br>
                - <strong>Tú recibes:</strong> <?= $comision_celene ?>% de comisión<br>
                - <strong>Total a pagar:</strong> Capital + Interés Celene + Tu Comisión
            </div>
            <?php elseif ($prestamista_seleccionado == 'Alexander Peralta'): ?>
            <div class="info-meses">
                <strong>💰 Distribución para Alexander Peralta:</strong><br>
                - <strong>Préstamos ANTES del 18-11-2025:</strong> Alexander recibe Capital + <?= $porcentaje_interes ?>% interés total<br>
                - <strong>Préstamos DESPUÉS del 18-11-2025:</strong> Alexander recibe Capital + <?= $interes_alexander ?>% interés + Tú recibes <?= $comision_alexander ?>% comisión<br>
                - <strong>Total a pagar:</strong> Capital + Interés correspondiente
            </div>
            <?php else: ?>
            <div class="info-meses">
                <strong>📅 Cálculo automático de meses:</strong> 
                Los meses se calculan automáticamente basado en la fecha del préstamo y la fecha actual. 
                Puedes ajustarlos manualmente si es necesario.
            </div>
            <?php endif; ?>
            
            <table id="tablaReporte">
                <thead>
                    <tr>
                        <th>Deudor</th>
                        <th>Préstamos</th>
                        <th>Capital</th>
                        <?php if ($prestamista_seleccionado == 'Celene'): ?>
                        <th>Interés Celene (<?= $interes_celene ?>%)</th>
                        <th>Tu Comisión (<?= $comision_celene ?>%)</th>
                        <?php elseif ($prestamista_seleccionado == 'Alexander Peralta'): ?>
                        <th>Interés Alexander</th>
                        <th>Tu Comisión (<?= $comision_alexander ?>%)</th>
                        <?php else: ?>
                        <th>Interés (<?= $porcentaje_interes ?>%)</th>
                        <?php endif; ?>
                        <th>Total a Pagar</th>
                    </tr>
                </thead>
                <tbody id="cuerpoReporte">
                    <?php foreach($prestamos_por_deudor as $deudor => $datos): ?>
                    <?php 
                        $total_capital_general += $datos['total_capital'];
                        $total_general += $datos['total_general'];
                        if ($prestamista_seleccionado == 'Celene') {
                            $total_interes_celene_general += $datos['total_interes_celene'];
                            $total_comision_general += $datos['total_comision'];
                        } elseif ($prestamista_seleccionado == 'Alexander Peralta') {
                            $total_interes_alexander_general += $datos['total_interes_alexander'];
                            $total_comision_general += $datos['total_comision'];
                        }
                    ?>
                    <tr class="header-deudor" id="fila-<?= md5($deudor) ?>">
                        <td>
                            <span class="detalle-toggle" onclick="toggleDetalle('<?= md5($deudor) ?>')">
                                📊 <?= htmlspecialchars($deudor) ?>
                            </span>
                        </td>
                        <td><?= $datos['cantidad_prestamos'] ?></td>
                        <td class="moneda capital-deudor">$ <?= number_format($datos['total_capital'], 0, ',', '.') ?></td>
                        <?php if ($prestamista_seleccionado == 'Celene'): ?>
                        <td class="moneda interes-celene-deudor">$ <?= number_format($datos['total_interes_celene'], 0, ',', '.') ?></td>
                        <td class="moneda comision-deudor">$ <?= number_format($datos['total_comision'], 0, ',', '.') ?></td>
                        <?php elseif ($prestamista_seleccionado == 'Alexander Peralta'): ?>
                        <td class="moneda interes-alexander-deudor">$ <?= number_format($datos['total_interes_alexander'], 0, ',', '.') ?></td>
                        <td class="moneda comision-deudor">$ <?= number_format($datos['total_comision'], 0, ',', '.') ?></td>
                        <?php else: ?>
                        <td class="moneda interes-deudor">$ <?= number_format($datos['total_general'] - $datos['total_capital'], 0, ',', '.') ?></td>
                        <?php endif; ?>
                        <td class="moneda total-deudor">$ <?= number_format($datos['total_general'], 0, ',', '.') ?></td>
                    </tr>
                    
                    <!-- Detalle de cada préstamo -->
                    <tr class="detalle-prestamo" id="detalle-<?= md5($deudor) ?>">
                        <td colspan="<?= ($prestamista_seleccionado == 'Celene' || $prestamista_seleccionado == 'Alexander Peralta') ? '6' : '5' ?>">
                            <table style="width: 100%; background-color: white;">
                                <thead>
                                    <tr>
                                        <th>Incluir</th>
                                        <th>Fecha</th>
                                        <th>Monto</th>
                                        <th>Meses</th>
                                        <?php if ($prestamista_seleccionado == 'Celene'): ?>
                                        <th>Int. Celene $</th>
                                        <th>Comisión $</th>
                                        <?php elseif ($prestamista_seleccionado == 'Alexander Peralta'): ?>
                                        <th>Int. Alexander $</th>
                                        <th>Comisión $</th>
                                        <?php else: ?>
                                        <th>Interés %</th>
                                        <th>Interés $</th>
                                        <?php endif; ?>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($datos['prestamos_detalle'] as $index => $detalle): ?>
                                    <tr class="fila-prestamo <?= ($prestamista_seleccionado == 'Alexander Peralta' && !$detalle['aplica_comision']) ? 'sin-comision' : '' ?>" 
                                        data-deudor="<?= md5($deudor) ?>" data-id="<?= $detalle['id'] ?>">
                                        <td class="acciones">
                                            <input type="checkbox" class="checkbox-excluir" checked 
                                                   onchange="togglePrestamo(this)">
                                        </td>
                                        <td><?= $detalle['fecha'] ?></td>
                                        <td class="moneda monto-prestamo">$ <?= number_format($detalle['monto'], 0, ',', '.') ?></td>
                                        <td class="acciones">
                                            <input type="number" class="meses-input" value="<?= $detalle['meses'] ?>" 
                                                   min="1" max="36" onchange="recalcularPrestamo(this)"
                                                   data-monto="<?= $detalle['monto'] ?>">
                                        </td>
                                        <?php if ($prestamista_seleccionado == 'Celene'): ?>
                                        <td class="moneda interes-celene-prestamo">$ <?= number_format($detalle['interes_celene'], 0, ',', '.') ?></td>
                                        <td class="moneda comision-prestamo">$ <?= number_format($detalle['comision'], 0, ',', '.') ?></td>
                                        <?php elseif ($prestamista_seleccionado == 'Alexander Peralta'): ?>
                                        <td class="moneda interes-alexander-prestamo">$ <?= number_format($detalle['interes_alexander'], 0, ',', '.') ?></td>
                                        <td class="moneda comision-prestamo">$ <?= number_format($detalle['comision'], 0, ',', '.') ?></td>
                                        <?php else: ?>
                                        <td class="acciones">
                                            <input type="number" class="interes-input" value="<?= $porcentaje_interes ?>" 
                                                   step="0.1" min="0" max="100" 
                                                   onchange="recalcularPrestamo(this)" 
                                                   data-monto="<?= $detalle['monto'] ?>">
                                        </td>
                                        <td class="moneda interes-prestamo">$ <?= number_format($detalle['total'] - $detalle['monto'], 0, ',', '.') ?></td>
                                        <?php endif; ?>
                                        <td class="moneda total-prestamo">$ <?= number_format($detalle['total'], 0, ',', '.') ?></td>
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
                        <td class="moneda" id="total-capital-general">$ <?= number_format($total_capital_general, 0, ',', '.') ?></td>
                        <?php if ($prestamista_seleccionado == 'Celene'): ?>
                        <td class="moneda interes-especial" id="total-interes-celene-general">$ <?= number_format($total_interes_celene_general, 0, ',', '.') ?></td>
                        <td class="moneda comision-especial" id="total-comision-general">$ <?= number_format($total_comision_general, 0, ',', '.') ?></td>
                        <?php elseif ($prestamista_seleccionado == 'Alexander Peralta'): ?>
                        <td class="moneda interes-especial" id="total-interes-alexander-general">$ <?= number_format($total_interes_alexander_general, 0, ',', '.') ?></td>
                        <td class="moneda comision-especial" id="total-comision-general">$ <?= number_format($total_comision_general, 0, ',', '.') ?></td>
                        <?php else: ?>
                        <td class="moneda" id="total-interes-general">$ <?= number_format($total_general - $total_capital_general, 0, ',', '.') ?></td>
                        <?php endif; ?>
                        <td class="moneda" id="total-general">$ <?= number_format($total_general, 0, ',', '.') ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Mostrar/ocultar configuración especial
        function toggleConfigEspecial() {
            const prestamista = document.getElementById('prestamista').value;
            const configCelene = document.getElementById('configCelene');
            const configAlexander = document.getElementById('configAlexander');
            const interesTotalDiv = document.getElementById('interes-total-div');
            
            configCelene.style.display = (prestamista == 'Celene') ? 'block' : 'none';
            configAlexander.style.display = (prestamista == 'Alexander Peralta') ? 'block' : 'none';
        }
        
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
        
        // Función para excluir/incluir préstamos
        function togglePrestamo(checkbox) {
            const fila = checkbox.closest('.fila-prestamo');
            
            if (!checkbox.checked) {
                fila.classList.add('excluido');
            } else {
                fila.classList.remove('excluido');
            }
            
            // Recalcular totales del deudor
            const deudorId = fila.dataset.deudor;
            actualizarTotalesDeudor(deudorId);
        }
        
        // Función para recalcular cuando se modifican meses o interés
        function recalcularPrestamo(input) {
            const fila = input.closest('.fila-prestamo');
            const monto = parseFloat(fila.querySelector('.monto-prestamo').textContent.replace(/[^\d]/g, ''));
            const inputMeses = fila.querySelector('.meses-input');
            
            const meses = parseInt(inputMeses.value);
            const prestamista = document.getElementById('prestamista').value;
            
            if (prestamista == 'Celene') {
                // Para Celene: Capital + Interés Celene + Comisión
                const interesCelene = document.getElementById('interes_celene').value;
                const comision = document.getElementById('comision_celene').value;
                
                const interesCeleneMonto = monto * (interesCelene / 100) * meses;
                const comisionMonto = monto * (comision / 100) * meses;
                const total = monto + interesCeleneMonto + comisionMonto;
                
                const celdaInteresCelene = fila.querySelector('.interes-celene-prestamo');
                const celdaComision = fila.querySelector('.comision-prestamo');
                const celdaTotal = fila.querySelector('.total-prestamo');
                
                celdaInteresCelene.textContent = '$ ' + formatNumber(interesCeleneMonto);
                celdaComision.textContent = '$ ' + formatNumber(comisionMonto);
                celdaTotal.textContent = '$ ' + formatNumber(total);
            } else if (prestamista == 'Alexander Peralta') {
                // Para Alexander: verificar si aplica comisión
                const fechaPrestamo = fila.querySelector('td:nth-child(2)').textContent;
                const fechaLimite = new Date('2025-11-18');
                const fechaPrestamoObj = new Date(fechaPrestamo);
                const aplicaComision = (fechaPrestamoObj >= fechaLimite);
                
                if (aplicaComision) {
                    // CON comisión: Capital + Interés Alexander + Comisión
                    const interesAlexander = document.getElementById('interes_alexander').value;
                    const comision = document.getElementById('comision_alexander').value;
                    
                    const interesAlexanderMonto = monto * (interesAlexander / 100) * meses;
                    const comisionMonto = monto * (comision / 100) * meses;
                    const total = monto + interesAlexanderMonto + comisionMonto;
                    
                    const celdaInteresAlexander = fila.querySelector('.interes-alexander-prestamo');
                    const celdaComision = fila.querySelector('.comision-prestamo');
                    const celdaTotal = fila.querySelector('.total-prestamo');
                    
                    celdaInteresAlexander.textContent = '$ ' + formatNumber(interesAlexanderMonto);
                    celdaComision.textContent = '$ ' + formatNumber(comisionMonto);
                    celdaTotal.textContent = '$ ' + formatNumber(total);
                    
                    fila.classList.remove('sin-comision');
                } else {
                    // SIN comisión: Capital + Interés Total normal AL 10%
                    const interesTotal = parseFloat(document.getElementById('porcentaje_interes').value);
                    const interesMonto = monto * (interesTotal / 100) * meses;
                    const total = monto + interesMonto;
                    
                    const celdaInteresAlexander = fila.querySelector('.interes-alexander-prestamo');
                    const celdaComision = fila.querySelector('.comision-prestamo');
                    const celdaTotal = fila.querySelector('.total-prestamo');
                    
                    celdaInteresAlexander.textContent = '$ ' + formatNumber(interesMonto);
                    celdaComision.textContent = '$ ' + formatNumber(0);
                    celdaTotal.textContent = '$ ' + formatNumber(total);
                    
                    fila.classList.add('sin-comision');
                }
            } else {
                // Para otros prestamistas: Capital + Interés Total
                const inputInteres = fila.querySelector('.interes-input');
                const porcentajeTotal = parseFloat(inputInteres.value);
                
                const interesTotal = monto * (porcentajeTotal / 100) * meses;
                const total = monto + interesTotal;
                
                const celdaInteres = fila.querySelector('.interes-prestamo');
                const celdaTotal = fila.querySelector('.total-prestamo');
                
                celdaInteres.textContent = '$ ' + formatNumber(interesTotal);
                celdaTotal.textContent = '$ ' + formatNumber(total);
            }
            
            // Si el préstamo está incluido, actualizar totales
            const checkbox = fila.querySelector('.checkbox-excluir');
            if (checkbox.checked) {
                const deudorId = fila.dataset.deudor;
                actualizarTotalesDeudor(deudorId);
            }
        }
        
        // Función para actualizar totales por deudor
        function actualizarTotalesDeudor(deudorId) {
            const filasPrestamos = document.querySelectorAll('.fila-prestamo[data-deudor="' + deudorId + '"]');
            let totalCapital = 0;
            let totalGeneral = 0;
            let totalInteresCelene = 0;
            let totalInteresAlexander = 0;
            let totalComision = 0;
            let prestamosIncluidos = 0;
            
            const prestamista = document.getElementById('prestamista').value;
            const esCelene = (prestamista == 'Celene');
            const esAlexander = (prestamista == 'Alexander Peralta');
            
            filasPrestamos.forEach(fila => {
                const checkbox = fila.querySelector('.checkbox-excluir');
                if (checkbox.checked && !fila.classList.contains('excluido')) {
                    const monto = parseFloat(fila.querySelector('.monto-prestamo').textContent.replace(/[^\d]/g, ''));
                    const total = parseFloat(fila.querySelector('.total-prestamo').textContent.replace(/[^\d]/g, ''));
                    
                    totalCapital += monto;
                    totalGeneral += total;
                    
                    if (esCelene) {
                        const interesCelene = parseFloat(fila.querySelector('.interes-celene-prestamo').textContent.replace(/[^\d]/g, ''));
                        const comision = parseFloat(fila.querySelector('.comision-prestamo').textContent.replace(/[^\d]/g, ''));
                        totalInteresCelene += interesCelene;
                        totalComision += comision;
                    } else if (esAlexander) {
                        const interesAlexander = parseFloat(fila.querySelector('.interes-alexander-prestamo').textContent.replace(/[^\d]/g, ''));
                        const comision = parseFloat(fila.querySelector('.comision-prestamo').textContent.replace(/[^\d]/g, ''));
                        totalInteresAlexander += interesAlexander;
                        totalComision += comision;
                    }
                    
                    prestamosIncluidos++;
                }
            });
            
            // Actualizar fila del deudor
            const filaDeudor = document.getElementById('fila-' + deudorId);
            filaDeudor.querySelector('.capital-deudor').textContent = '$ ' + formatNumber(totalCapital);
            filaDeudor.querySelector('.total-deudor').textContent = '$ ' + formatNumber(totalGeneral);
            filaDeudor.querySelector('td:nth-child(2)').textContent = prestamosIncluidos;
            
            if (esCelene) {
                filaDeudor.querySelector('.interes-celene-deudor').textContent = '$ ' + formatNumber(totalInteresCelene);
                filaDeudor.querySelector('.comision-deudor').textContent = '$ ' + formatNumber(totalComision);
            } else if (esAlexander) {
                filaDeudor.querySelector('.interes-alexander-deudor').textContent = '$ ' + formatNumber(totalInteresAlexander);
                filaDeudor.querySelector('.comision-deudor').textContent = '$ ' + formatNumber(totalComision);
            } else {
                const interesTotal = totalGeneral - totalCapital;
                filaDeudor.querySelector('.interes-deudor').textContent = '$ ' + formatNumber(interesTotal);
            }
            
            // Actualizar totales generales
            actualizarTotalesGenerales();
        }
        
        // Función para actualizar totales generales
        function actualizarTotalesGenerales() {
            let totalCapital = 0;
            let totalGeneral = 0;
            let totalInteresCelene = 0;
            let totalInteresAlexander = 0;
            let totalComision = 0;
            
            const prestamista = document.getElementById('prestamista').value;
            const esCelene = (prestamista == 'Celene');
            const esAlexander = (prestamista == 'Alexander Peralta');
            
            document.querySelectorAll('.header-deudor').forEach(fila => {
                totalCapital += parseFloat(fila.querySelector('.capital-deudor').textContent.replace(/[^\d]/g, ''));
                totalGeneral += parseFloat(fila.querySelector('.total-deudor').textContent.replace(/[^\d]/g, ''));
                
                if (esCelene) {
                    totalInteresCelene += parseFloat(fila.querySelector('.interes-celene-deudor').textContent.replace(/[^\d]/g, ''));
                    totalComision += parseFloat(fila.querySelector('.comision-deudor').textContent.replace(/[^\d]/g, ''));
                } else if (esAlexander) {
                    totalInteresAlexander += parseFloat(fila.querySelector('.interes-alexander-deudor').textContent.replace(/[^\d]/g, ''));
                    totalComision += parseFloat(fila.querySelector('.comision-deudor').textContent.replace(/[^\d]/g, ''));
                }
            });
            
            document.getElementById('total-capital-general').textContent = '$ ' + formatNumber(totalCapital);
            document.getElementById('total-general').textContent = '$ ' + formatNumber(totalGeneral);
            
            if (esCelene) {
                document.getElementById('total-interes-celene-general').textContent = '$ ' + formatNumber(totalInteresCelene);
                document.getElementById('total-comision-general').textContent = '$ ' + formatNumber(totalComision);
            } else if (esAlexander) {
                document.getElementById('total-interes-alexander-general').textContent = '$ ' + formatNumber(totalInteresAlexander);
                document.getElementById('total-comision-general').textContent = '$ ' + formatNumber(totalComision);
            } else {
                const interesTotal = totalGeneral - totalCapital;
                document.getElementById('total-interes-general').textContent = '$ ' + formatNumber(interesTotal);
            }
        }
        
        // Función para formatear números
        function formatNumber(num) {
            return Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
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