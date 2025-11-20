<?php
include("nav.php");
// Conexión a la base de datos
define('DB_HOST', 'mysql.hostinger.com');
define('DB_USER', 'u648222299_keboco5');
define('DB_PASS', 'Bucaramanga3011');
define('DB_NAME', 'u648222299_viajes');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Función para calcular meses automáticamente - CÁLCULO CORRECTO DE MESES
function calcularMesesAutomaticos($fecha_prestamo) {
    $hoy = new DateTime();
    $fecha_prestamo_obj = new DateTime($fecha_prestamo);
    
    // Si la fecha del préstamo es futura, retornar 1 mes mínimo
    if ($fecha_prestamo_obj > $hoy) {
        return 1;
    }
    
    // Calcular diferencia exacta en meses
    $diferencia = $fecha_prestamo_obj->diff($hoy);
    $meses = $diferencia->y * 12 + $diferencia->m;
    
    // Si hay días restantes, sumar un mes adicional
    if ($diferencia->d > 0 || $meses == 0) {
        $meses++;
    }
    
    return max(1, $meses);
}

// Variables para mantener los valores del formulario
$deudores_seleccionados = [];
$prestamista_seleccionado = '';
$porcentaje_interes = 10;
$comision_celene = 5;
$interes_celene = 8;
$fecha_desde = '';
$fecha_hasta = '';

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deudores_seleccionados = isset($_POST['deudores']) ? $_POST['deudores'] : [];
    // Si viene como string separado por comas, convertirlo a array
    if (is_string($deudores_seleccionados)) {
        $deudores_seleccionados = explode(',', $deudores_seleccionados);
    }
    $prestamista_seleccionado = $_POST['prestamista'] ?? '';
    $porcentaje_interes = floatval($_POST['porcentaje_interes'] ?? 10);
    $comision_celene = floatval($_POST['comision_celene'] ?? 5);
    $interes_celene = floatval($_POST['interes_celene'] ?? 8);
    $fecha_desde = $_POST['fecha_desde'] ?? '';
    $fecha_hasta = $_POST['fecha_hasta'] ?? '';
    
    // Obtener prestamistas únicos de la base de datos (SOLO NO PAGADOS)
    $sql_prestamistas = "SELECT DISTINCT prestamista FROM prestamos WHERE prestamista != '' AND pagado = 0 ORDER BY prestamista";
    $result_prestamistas = $conn->query($sql_prestamistas);
    
    // Obtener conductores basado en el filtro de fechas
    $conductores_filtrados = [];
    if (!empty($fecha_desde) && !empty($fecha_hasta)) {
        $sql_conductores = "SELECT DISTINCT nombre FROM viajes WHERE fecha BETWEEN ? AND ? AND nombre IS NOT NULL AND nombre != '' ORDER BY nombre";
        $stmt = $conn->prepare($sql_conductores);
        $stmt->bind_param("ss", $fecha_desde, $fecha_hasta);
        $stmt->execute();
        $result_conductores = $stmt->get_result();
        
        while($conductor = $result_conductores->fetch_assoc()) {
            $conductores_filtrados[] = $conductor['nombre'];
        }
    }
    
    if (!empty($deudores_seleccionados) && !empty($prestamista_seleccionado)) {
        // Consulta para obtener los préstamos (SOLO NO PAGADOS)
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
                AND pagado = 0  -- SOLO PRÉSTAMOS NO PAGADOS
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
        
        while($fila = $result_detalle->fetch_assoc()) {
            $deudor = $fila['deudor'];
            $meses = calcularMesesAutomaticos($fila['fecha']);
            
            if ($es_celene) {
                // Para Celene: cálculo separado SIN interés total
                $interes_celene_monto = $fila['monto'] * ($interes_celene / 100) * $meses;
                $comision_monto = $fila['monto'] * ($comision_celene / 100) * $meses;
                $total_prestamo = $fila['monto'] + $interes_celene_monto + $comision_monto;
            } else {
                // Para otros prestamistas: cálculo normal
                $interes_total = $fila['monto'] * ($porcentaje_interes / 100) * $meses;
                $interes_celene_monto = 0;
                $comision_monto = 0;
                $total_prestamo = $fila['monto'] + $interes_total;
            }
            
            if (!isset($prestamos_por_deudor[$deudor])) {
                $prestamos_por_deudor[$deudor] = [
                    'total_capital' => 0,
                    'total_general' => 0,
                    'total_interes_celene' => 0,
                    'total_comision' => 0,
                    'cantidad_prestamos' => 0,
                    'prestamos_detalle' => []
                ];
            }
            
            $prestamos_por_deudor[$deudor]['total_capital'] += $fila['monto'];
            $prestamos_por_deudor[$deudor]['total_general'] += $total_prestamo;
            $prestamos_por_deudor[$deudor]['total_interes_celene'] += $interes_celene_monto;
            $prestamos_por_deudor[$deudor]['total_comision'] += $comision_monto;
            $prestamos_por_deudor[$deudor]['cantidad_prestamos']++;
            
            $prestamos_por_deudor[$deudor]['prestamos_detalle'][] = [
                'id' => $fila['id'],
                'monto' => $fila['monto'],
                'fecha' => $fila['fecha'],
                'meses' => $meses,
                'interes_celene' => $interes_celene_monto,
                'comision' => $comision_monto,
                'total' => $total_prestamo,
                'incluido' => true
            ];
        }
        
        // Calcular totales generales
        $total_capital_general = 0;
        $total_general = 0;
        $total_interes_celene_general = 0;
        $total_comision_general = 0;
    }
} else {
    // Si no es POST, obtener prestamistas para el select
    $sql_prestamistas = "SELECT DISTINCT prestamista FROM prestamos WHERE prestamista != '' AND pagado = 0 ORDER BY prestamista";
    $result_prestamistas = $conn->query($sql_prestamistas);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Préstamos - Pendientes de Pago</title>
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
        .config-celene { background-color: #e7f3ff; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #007bff; }
        .comision-celene { background-color: #d4edda; }
        .interes-celene { background-color: #fff3cd; }
        .buscador-container { position: relative; margin-bottom: 10px; }
        .buscador-input { width: 100%; padding: 8px 30px 8px 10px; border: 1px solid #ddd; border-radius: 4px; }
        .buscador-icon { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #666; }
        .contador-deudores { font-size: 0.9em; color: #666; margin-top: 5px; }
        .nota-pagados { background-color: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #28a745; }
        .deudor-item { padding: 8px; cursor: pointer; border-bottom: 1px solid #eee; }
        .deudor-item:hover { background-color: #f0f0f0; }
        .deudor-item.selected { background-color: #007bff; color: white; }
        .deudores-container { border: 1px solid #ddd; border-radius: 4px; max-height: 200px; overflow-y: auto; }
        .botones-seleccion { margin: 10px 0; }
        .botones-seleccion button { width: auto; padding: 5px 10px; margin-right: 5px; }
        .filtro-fechas { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #6c757d; }
        .fecha-row { display: flex; gap: 15px; }
        .fecha-col { flex: 1; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Reporte de Préstamos Consolidados - Pendientes de Pago</h1>
        
        <div class="nota-pagados">
            <strong>Nota:</strong> Esta vista solo muestra préstamos que están <strong>pendientes de pago</strong> (pagado = 0). 
            Los préstamos ya pagados no aparecen en esta lista.
        </div>
        
        <form method="POST" id="formPrincipal">
            <!-- Filtro de Fechas -->
            <div class="filtro-fechas">
                <h3>Filtrar Conductores por Fecha de Viajes</h3>
                <div class="fecha-row">
                    <div class="fecha-col">
                        <label for="fecha_desde">Fecha Desde:</label>
                        <input type="date" name="fecha_desde" id="fecha_desde" 
                               value="<?php echo htmlspecialchars($fecha_desde); ?>" required>
                    </div>
                    <div class="fecha-col">
                        <label for="fecha_hasta">Fecha Hasta:</label>
                        <input type="date" name="fecha_hasta" id="fecha_hasta" 
                               value="<?php echo htmlspecialchars($fecha_hasta); ?>" required>
                    </div>
                </div>
                <small>Selecciona el mismo rango de fechas que usas en la vista de Pago para obtener los mismos conductores</small>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="deudores">Seleccionar Conductores (Haz clic para seleccionar):</label>
                        
                        <!-- Buscador para conductores -->
                        <div class="buscador-container">
                            <input type="text" id="buscadorDeudores" class="buscador-input" 
                                   placeholder="Buscar conductor...">
                            <span class="buscador-icon">🔍</span>
                        </div>

                        <!-- Botones de selección rápida -->
                        <div class="botones-seleccion">
                            <button type="button" onclick="seleccionarTodos()">Seleccionar Todos</button>
                            <button type="button" onclick="deseleccionarTodos()">Deseleccionar Todos</button>
                        </div>
                        
                        <!-- Lista personalizada de conductores -->
                        <div class="deudores-container" id="listaDeudores">
                            <?php 
                            if (!empty($conductores_filtrados)) {
                                // Mostrar conductores filtrados por fecha
                                foreach($conductores_filtrados as $conductor): 
                                    $es_seleccionado = in_array($conductor, $deudores_seleccionados);
                            ?>
                                <div class="deudor-item <?php echo $es_seleccionado ? 'selected' : ''; ?>" 
                                     data-value="<?php echo htmlspecialchars($conductor); ?>">
                                    <?php echo htmlspecialchars($conductor); ?>
                                </div>
                            <?php endforeach; 
                            } else {
                                echo '<div style="padding: 10px; text-align: center; color: #666;">';
                                echo 'Selecciona un rango de fechas para ver los conductores';
                                echo '</div>';
                            }
                            ?>
                        </div>
                        
                        <!-- Campo oculto para almacenar los valores seleccionados -->
                        <input type="hidden" name="deudores" id="deudoresSeleccionados" 
                               value="<?php echo htmlspecialchars(implode(',', $deudores_seleccionados)); ?>">
                        
                        <div class="contador-deudores" id="contadorDeudores">
                            <?php 
                            if (!empty($conductores_filtrados)) {
                                $total_conductores = count($conductores_filtrados);
                                $seleccionados = count($deudores_seleccionados);
                                echo "Seleccionados: $seleccionados de $total_conductores conductores";
                            } else {
                                echo "Selecciona fechas para ver conductores";
                            }
                            ?>
                        </div>
                        <small>Haz clic en cada conductor para seleccionarlo/deseleccionarlo</small>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label for="prestamista">Seleccionar Prestamista:</label>
                        <select name="prestamista" id="prestamista" required onchange="toggleConfigCelene()">
                            <option value="">-- Seleccionar Prestamista --</option>
                            <?php 
                            if (isset($result_prestamistas)) {
                                $result_prestamistas->data_seek(0);
                                while($prestamista = $result_prestamistas->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($prestamista['prestamista']); ?>" 
                                        <?php echo $prestamista_seleccionado == $prestamista['prestamista'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($prestamista['prestamista']); ?>
                                    </option>
                                <?php endwhile; 
                            }
                            ?>
                        </select>
                    </div>
                    
                    <?php if ($prestamista_seleccionado != 'Celene'): ?>
                    <div class="form-group">
                        <label for="porcentaje_interes">Interés Total (%):</label>
                        <input type="number" name="porcentaje_interes" id="porcentaje_interes" 
                               value="<?php echo $porcentaje_interes; ?>" step="0.1" min="0" max="100" required>
                        <small>Interés total que paga el deudor</small>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Configuración especial para Celene -->
                    <div id="configCelene" class="config-celene" style="display: <?php echo $prestamista_seleccionado == 'Celene' ? 'block' : 'none'; ?>;">
                        <h4>Configuración para Celene</h4>
                        <div class="form-row">
                            <div class="form-col">
                                <label for="interes_celene">Interés para Celene (%):</label>
                                <input type="number" name="interes_celene" id="interes_celene" 
                                       value="<?php echo $interes_celene; ?>" step="0.1" min="0" max="100" required>
                                <small>Lo que recibe Celene</small>
                            </div>
                            <div class="form-col">
                                <label for="comision_celene">Tu Comisión (%):</label>
                                <input type="number" name="comision_celene" id="comision_celene" 
                                       value="<?php echo $comision_celene; ?>" step="0.1" min="0" max="100" required>
                                <small>Lo que recibes tú</small>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit">Generar Reporte</button>
                </div>
            </div>
        </form>

        <?php if (isset($prestamos_por_deudor)): ?>
        <div class="resultados">
            <h2>Resultados para: <?php echo htmlspecialchars($prestamista_seleccionado); ?></h2>
            
            <?php if ($prestamista_seleccionado == 'Celene'): ?>
            <div class="info-meses">
                <strong>Distribución para Celene:</strong><br>
                - <strong>Celene recibe:</strong> Capital + <?php echo $interes_celene; ?>% interés<br>
                - <strong>Tú recibes:</strong> <?php echo $comision_celene; ?>% de comisión<br>
                - <strong>Total a pagar:</strong> Capital + Interés Celene + Tu Comisión
            </div>
            <?php else: ?>
            <div class="info-meses">
                <strong>Cálculo automático de meses:</strong> 
                Los meses se calculan automáticamente basado en la fecha del préstamo y la fecha actual. 
                Se cuenta un mes completo por cada mes calendario transcurrido.
            </div>
            <?php endif; ?>
            
            <?php if (empty($prestamos_por_deudor)): ?>
                <div style="background-color: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <strong>No se encontraron préstamos pendientes</strong> para los criterios seleccionados.
                </div>
            <?php else: ?>
            <table id="tablaReporte">
                <thead>
                    <tr>
                        <th>Deudor</th>
                        <th>Préstamos</th>
                        <th>Capital</th>
                        <?php if ($prestamista_seleccionado == 'Celene'): ?>
                        <th>Interés Celene (<?php echo $interes_celene; ?>%)</th>
                        <th>Tu Comisión (<?php echo $comision_celene; ?>%)</th>
                        <?php else: ?>
                        <th>Interés (<?php echo $porcentaje_interes; ?>%)</th>
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
                        }
                    ?>
                    <tr class="header-deudor" id="fila-<?php echo md5($deudor); ?>">
                        <td>
                            <span class="detalle-toggle" onclick="toggleDetalle('<?php echo md5($deudor); ?>')">
                                <?php echo htmlspecialchars($deudor); ?>
                            </span>
                        </td>
                        <td><?php echo $datos['cantidad_prestamos']; ?></td>
                        <td class="moneda capital-deudor">$ <?php echo number_format($datos['total_capital'], 0, ',', '.'); ?></td>
                        <?php if ($prestamista_seleccionado == 'Celene'): ?>
                        <td class="moneda interes-celene-deudor">$ <?php echo number_format($datos['total_interes_celene'], 0, ',', '.'); ?></td>
                        <td class="moneda comision-deudor">$ <?php echo number_format($datos['total_comision'], 0, ',', '.'); ?></td>
                        <?php else: ?>
                        <td class="moneda interes-deudor">$ <?php echo number_format($datos['total_general'] - $datos['total_capital'], 0, ',', '.'); ?></td>
                        <?php endif; ?>
                        <td class="moneda total-deudor">$ <?php echo number_format($datos['total_general'], 0, ',', '.'); ?></td>
                    </tr>
                    
                    <!-- Detalle de cada préstamo -->
                    <tr class="detalle-prestamo" id="detalle-<?php echo md5($deudor); ?>">
                        <td colspan="<?php echo $prestamista_seleccionado == 'Celene' ? '6' : '5'; ?>">
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
                                        <?php else: ?>
                                        <th>Interés %</th>
                                        <th>Interés $</th>
                                        <?php endif; ?>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($datos['prestamos_detalle'] as $index => $detalle): ?>
                                    <tr class="fila-prestamo" data-deudor="<?php echo md5($deudor); ?>" data-id="<?php echo $detalle['id']; ?>">
                                        <td class="acciones">
                                            <input type="checkbox" class="checkbox-excluir" checked 
                                                   onchange="togglePrestamo(this)">
                                        </td>
                                        <td><?php echo $detalle['fecha']; ?></td>
                                        <td class="moneda monto-prestamo">$ <?php echo number_format($detalle['monto'], 0, ',', '.'); ?></td>
                                        <td class="acciones">
                                            <input type="number" class="meses-input" value="<?php echo $detalle['meses']; ?>" 
                                                   min="1" max="36" onchange="recalcularPrestamo(this)"
                                                   data-monto="<?php echo $detalle['monto']; ?>">
                                        </td>
                                        <?php if ($prestamista_seleccionado == 'Celene'): ?>
                                        <td class="moneda interes-celene-prestamo">$ <?php echo number_format($detalle['interes_celene'], 0, ',', '.'); ?></td>
                                        <td class="moneda comision-prestamo">$ <?php echo number_format($detalle['comision'], 0, ',', '.'); ?></td>
                                        <?php else: ?>
                                        <td class="acciones">
                                            <input type="number" class="interes-input" value="<?php echo $porcentaje_interes; ?>" 
                                                   step="0.1" min="0" max="100" 
                                                   onchange="recalcularPrestamo(this)" 
                                                   data-monto="<?php echo $detalle['monto']; ?>">
                                        </td>
                                        <td class="moneda interes-prestamo">$ <?php echo number_format($detalle['total'] - $detalle['monto'], 0, ',', '.'); ?></td>
                                        <?php endif; ?>
                                        <td class="moneda total-prestamo">$ <?php echo number_format($detalle['total'], 0, ',', '.'); ?></td>
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
                        <td class="moneda" id="total-capital-general">$ <?php echo number_format($total_capital_general, 0, ',', '.'); ?></td>
                        <?php if ($prestamista_seleccionado == 'Celene'): ?>
                        <td class="moneda interes-celene" id="total-interes-celene-general">$ <?php echo number_format($total_interes_celene_general, 0, ',', '.'); ?></td>
                        <td class="moneda comision-celene" id="total-comision-general">$ <?php echo number_format($total_comision_general, 0, ',', '.'); ?></td>
                        <?php else: ?>
                        <td class="moneda" id="total-interes-general">$ <?php echo number_format($total_general - $total_capital_general, 0, ',', '.'); ?></td>
                        <?php endif; ?>
                        <td class="moneda" id="total-general">$ <?php echo number_format($total_general, 0, ',', '.'); ?></td>
                    </tr>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // ARRAY PARA ALMACENAR DEUDORES SELECCIONADOS
        let deudoresSeleccionados = <?php echo json_encode($deudores_seleccionados); ?>;

        // INICIALIZAR LA LISTA DE DEUDORES
        document.addEventListener('DOMContentLoaded', function() {
            actualizarListaDeudores();
            actualizarContador();
        });

        // FUNCIÓN PARA MANEJAR CLIC EN DEUDOR
        function toggleDeudor(element) {
            const valor = element.getAttribute('data-value');
            const index = deudoresSeleccionados.indexOf(valor);
            
            if (index === -1) {
                // Agregar a seleccionados
                deudoresSeleccionados.push(valor);
                element.classList.add('selected');
            } else {
                // Quitar de seleccionados
                deudoresSeleccionados.splice(index, 1);
                element.classList.remove('selected');
            }
            
            // Actualizar campo oculto y contador
            document.getElementById('deudoresSeleccionados').value = deudoresSeleccionados.join(',');
            actualizarContador();
        }

        // FUNCIÓN PARA ACTUALIZAR LA LISTA VISUAL
        function actualizarListaDeudores() {
            const items = document.querySelectorAll('.deudor-item');
            items.forEach(item => {
                const valor = item.getAttribute('data-value');
                if (deudoresSeleccionados.includes(valor)) {
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
                
                // Agregar evento click
                item.addEventListener('click', function() {
                    toggleDeudor(this);
                });
            });
        }

        // FUNCIÓN PARA ACTUALIZAR CONTADOR
        function actualizarContador() {
            const items = document.querySelectorAll('.deudor-item');
            const total = items.length;
            const seleccionados = deudoresSeleccionados.length;
            
            if (total > 0) {
                document.getElementById('contadorDeudores').textContent = 
                    `Seleccionados: ${seleccionados} de ${total} conductores`;
            }
        }

        // FUNCIÓN PARA SELECCIONAR TODOS
        function seleccionarTodos() {
            const items = document.querySelectorAll('.deudor-item');
            deudoresSeleccionados = [];
            
            items.forEach(item => {
                const valor = item.getAttribute('data-value');
                deudoresSeleccionados.push(valor);
                item.classList.add('selected');
            });
            
            document.getElementById('deudoresSeleccionados').value = deudoresSeleccionados.join(',');
            actualizarContador();
        }

        // FUNCIÓN PARA DESELECCIONAR TODOS
        function deseleccionarTodos() {
            const items = document.querySelectorAll('.deudor-item');
            deudoresSeleccionados = [];
            
            items.forEach(item => {
                item.classList.remove('selected');
            });
            
            document.getElementById('deudoresSeleccionados').value = '';
            actualizarContador();
        }

        // BUSCADOR DE DEUDORES
        document.getElementById('buscadorDeudores').addEventListener('input', function(e) {
            const filtro = e.target.value.toLowerCase();
            const items = document.querySelectorAll('.deudor-item');
            let contador = 0;
            
            items.forEach(item => {
                const texto = item.textContent.toLowerCase();
                if (texto.includes(filtro)) {
                    item.style.display = '';
                    contador++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Actualizar contador
            const contadorElement = document.getElementById('contadorDeudores');
            if (filtro === '') {
                actualizarContador();
            } else {
                contadorElement.textContent = `Mostrando ${contador} conductor(es) que coinciden con "${filtro}"`;
            }
        });
        
        // Mostrar/ocultar configuración de Celene
        function toggleConfigCelene() {
            const prestamista = document.getElementById('prestamista').value;
            const configCelene = document.getElementById('configCelene');
            
            configCelene.style.display = (prestamista == 'Celene') ? 'block' : 'none';
        }
        
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
            let totalComision = 0;
            let prestamosIncluidos = 0;
            
            const prestamista = document.getElementById('prestamista').value;
            const esCelene = (prestamista == 'Celene');
            
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
            let totalComision = 0;
            
            const prestamista = document.getElementById('prestamista').value;
            const esCelene = (prestamista == 'Celene');
            
            document.querySelectorAll('.header-deudor').forEach(fila => {
                totalCapital += parseFloat(fila.querySelector('.capital-deudor').textContent.replace(/[^\d]/g, ''));
                totalGeneral += parseFloat(fila.querySelector('.total-deudor').textContent.replace(/[^\d]/g, ''));
                
                if (esCelene) {
                    totalInteresCelene += parseFloat(fila.querySelector('.interes-celene-deudor').textContent.replace(/[^\d]/g, ''));
                    totalComision += parseFloat(fila.querySelector('.comision-deudor').textContent.replace(/[^\d]/g, ''));
                }
            });
            
            document.getElementById('total-capital-general').textContent = '$ ' + formatNumber(totalCapital);
            document.getElementById('total-general').textContent = '$ ' + formatNumber(totalGeneral);
            
            if (esCelene) {
                document.getElementById('total-interes-celene-general').textContent = '$ ' + formatNumber(totalInteresCelene);
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
    </script>
</body>
</html>

<?php
// Cerrar conexión
$conn->close();
?>