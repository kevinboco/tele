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
$empresa_seleccionada = '';

// Arreglos para los reportes
$prestamos_por_deudor = [];          // CONDUCTORES
$otros_prestamos_por_deudor = [];    // OTROS DEUDORES

// Totales generales cuadro 1 (conductores)
$total_capital_general = 0;
$total_general = 0;
$total_interes_celene_general = 0;
$total_comision_general = 0;

// Totales generales cuadro 2 (otros deudores)
$otros_total_capital_general = 0;
$otros_total_general = 0;
$otros_total_interes_celene_general = 0;
$otros_total_comision_general = 0;

// Obtener empresas únicas de la base de datos - desde la tabla VIAJES
$sql_empresas = "SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa != '' ORDER BY empresa";
$result_empresas = $conn->query($sql_empresas);

// Si es POST procesamos todo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deudores_seleccionados = isset($_POST['deudores']) ? $_POST['deudores'] : [];
    // Si viene como string separado por comas, convertirlo a array
    if (is_string($deudores_seleccionados)) {
        $deudores_seleccionados = $deudores_seleccionados !== '' ? explode(',', $deudores_seleccionados) : [];
    }
    $prestamista_seleccionado = $_POST['prestamista'] ?? '';
    $porcentaje_interes = floatval($_POST['porcentaje_interes'] ?? 10);
    $comision_celene = floatval($_POST['comision_celene'] ?? 5);
    $interes_celene = floatval($_POST['interes_celene'] ?? 8);
    $fecha_desde = $_POST['fecha_desde'] ?? '';
    $fecha_hasta = $_POST['fecha_hasta'] ?? '';
    $empresa_seleccionada = $_POST['empresa'] ?? '';

    // Obtener prestamistas únicos de la base de datos (SOLO NO PAGADOS)
    $sql_prestamistas = "SELECT DISTINCT prestamista FROM prestamos WHERE prestamista != '' AND pagado = 0 ORDER BY prestamista";
    $result_prestamistas = $conn->query($sql_prestamistas);

    // ==========================
    // 1) CONDUCTORES DESDE VIAJES
    // ==========================
    $conductores_filtrados = [];
    if (!empty($fecha_desde) && !empty($fecha_hasta)) {
        if (!empty($empresa_seleccionada)) {
            // Filtrar por fecha Y empresa
            $sql_conductores = "SELECT DISTINCT nombre 
                                FROM viajes 
                                WHERE fecha BETWEEN ? AND ? 
                                  AND empresa = ? 
                                  AND nombre IS NOT NULL 
                                  AND nombre != '' 
                                ORDER BY nombre";
            $stmt = $conn->prepare($sql_conductores);
            $stmt->bind_param("sss", $fecha_desde, $fecha_hasta, $empresa_seleccionada);
        } else {
            // Filtrar solo por fecha (todas las empresas)
            $sql_conductores = "SELECT DISTINCT nombre 
                                FROM viajes 
                                WHERE fecha BETWEEN ? AND ? 
                                  AND nombre IS NOT NULL 
                                  AND nombre != '' 
                                ORDER BY nombre";
            $stmt = $conn->prepare($sql_conductores);
            $stmt->bind_param("ss", $fecha_desde, $fecha_hasta);
        }

        $stmt->execute();
        $result_conductores = $stmt->get_result();

        while ($conductor = $result_conductores->fetch_assoc()) {
            $conductores_filtrados[] = $conductor['nombre'];
        }
    }

    // ==========================
    // 2) PRÉSTAMOS DE CONDUCTORES (CUADRO 1)
    // ==========================

    if (!empty($deudores_seleccionados) && !empty($prestamista_seleccionado)) {
        // Consulta para obtener los préstamos (SOLO NO PAGADOS) de esos deudores
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

        $es_celene = ($prestamista_seleccionado == 'Celene');

        while ($fila = $result_detalle->fetch_assoc()) {
            $deudor = $fila['deudor'];
            $meses = calcularMesesAutomaticos($fila['fecha']);

            if ($es_celene) {
                // Para Celene: Capital + Interés Celene + Comisión
                $interes_celene_monto = $fila['monto'] * ($interes_celene / 100) * $meses;
                $comision_monto = $fila['monto'] * ($comision_celene / 100) * $meses;
                $total_prestamo = $fila['monto'] + $interes_celene_monto + $comision_monto;
            } else {
                // Otros prestamistas: Capital + Interés total
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
            $prestamos_por_deudor[$deudor]['total_interes_celene'] += $interes_celene_monto ?? 0;
            $prestamos_por_deudor[$deudor]['total_comision'] += $comision_monto ?? 0;
            $prestamos_por_deudor[$deudor]['cantidad_prestamos']++;

            $prestamos_por_deudor[$deudor]['prestamos_detalle'][] = [
                'id' => $fila['id'],
                'monto' => $fila['monto'],
                'fecha' => $fila['fecha'],
                'meses' => $meses,
                'interes_celene' => $interes_celene_monto ?? 0,
                'comision' => $comision_monto ?? 0,
                'total' => $total_prestamo,
                'incluido' => true
            ];
        }

        // Totales cuadro 1 se calculan en el HTML al recorrer el arreglo
    }

    // ==========================
    // 3) OTROS DEUDORES (NO CONDUCTORES) - CUADRO 2
    // ==========================

    if (!empty($prestamista_seleccionado)) {
        $sql_otros = "SELECT 
                        id,
                        deudor,
                        prestamista,
                        monto,
                        fecha
                      FROM prestamos
                      WHERE prestamista = ?
                        AND pagado = 0
                        AND deudor IS NOT NULL
                        AND deudor != ''
                      ORDER BY deudor, fecha";
        $stmt_otros = $conn->prepare($sql_otros);
        $stmt_otros->bind_param("s", $prestamista_seleccionado);
        $stmt_otros->execute();
        $result_otros = $stmt_otros->get_result();

        $es_celene = ($prestamista_seleccionado == 'Celene');

        while ($fila = $result_otros->fetch_assoc()) {
            $deudor = $fila['deudor'];

            // Si el deudor está en la lista de conductores, lo ignoramos en este cuadro
            if (in_array($deudor, $conductores_filtrados)) {
                continue;
            }

            $meses = calcularMesesAutomaticos($fila['fecha']);

            if ($es_celene) {
                $interes_celene_monto = $fila['monto'] * ($interes_celene / 100) * $meses;
                $comision_monto = $fila['monto'] * ($comision_celene / 100) * $meses;
                $total_prestamo = $fila['monto'] + $interes_celene_monto + $comision_monto;
                $interes_total = 0;
            } else {
                $interes_total = $fila['monto'] * ($porcentaje_interes / 100) * $meses;
                $total_prestamo = $fila['monto'] + $interes_total;
                $interes_celene_monto = 0;
                $comision_monto = 0;
            }

            if (!isset($otros_prestamos_por_deudor[$deudor])) {
                $otros_prestamos_por_deudor[$deudor] = [
                    'total_capital' => 0,
                    'total_general' => 0,
                    'total_interes_celene' => 0,
                    'total_comision' => 0,
                    'total_interes_normal' => 0,
                    'cantidad_prestamos' => 0
                ];
            }

            $otros_prestamos_por_deudor[$deudor]['total_capital'] += $fila['monto'];
            $otros_prestamos_por_deudor[$deudor]['total_general'] += $total_prestamo;
            $otros_prestamos_por_deudor[$deudor]['total_interes_celene'] += $interes_celene_monto ?? 0;
            $otros_prestamos_por_deudor[$deudor]['total_comision'] += $comision_monto ?? 0;
            $otros_prestamos_por_deudor[$deudor]['total_interes_normal'] += $interes_total ?? 0;
            $otros_prestamos_por_deudor[$deudor]['cantidad_prestamos']++;
        }
    }

} else {
    // Si no es POST, obtener prestamistas para el select
    $sql_prestamistas = "SELECT DISTINCT prestamista FROM prestamos WHERE prestamista != '' AND pagado = 0 ORDER BY prestamista";
    $result_prestamistas = $conn->query($sql_prestamistas);
    $conductores_filtrados = [];
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
        .subtitulo-cuadro { margin-top: 25px; font-size: 1.1em; font-weight: bold; }
        .cuadro-otros { background-color: #f8f9ff; padding: 10px; border-radius: 5px; }
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
            <!-- Filtro de Fechas y Empresa -->
            <div class="filtro-fechas">
                <h3>Filtrar Conductores por Fecha y Empresa (tabla VIAJES)</h3>
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
                    <div class="fecha-col">
                        <label for="empresa">Empresa:</label>
                        <select name="empresa" id="empresa">
                            <option value="">-- Todas las Empresas --</option>
                            <?php 
                            if ($result_empresas && $result_empresas->num_rows > 0) {
                                $result_empresas->data_seek(0);
                                while ($empresa = $result_empresas->fetch_assoc()): 
                                    if (!empty($empresa['empresa'])):
                            ?>
                                    <option value="<?php echo htmlspecialchars($empresa['empresa']); ?>" 
                                        <?php echo $empresa_seleccionada == $empresa['empresa'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($empresa['empresa']); ?>
                                    </option>
                            <?php 
                                    endif;
                                endwhile; 
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <small>Usa el mismo rango de fechas y empresa que en la vista de pago para que salgan los mismos conductores.</small>
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
                                foreach ($conductores_filtrados as $conductor): 
                                    $es_seleccionado = in_array($conductor, $deudores_seleccionados);
                            ?>
                                <div class="deudor-item <?php echo $es_seleccionado ? 'selected' : ''; ?>" 
                                     data-value="<?php echo htmlspecialchars($conductor); ?>">
                                    <?php echo htmlspecialchars($conductor); ?>
                                </div>
                            <?php 
                                endforeach; 
                            } else {
                                echo '<div style="padding: 10px; text-align: center; color: #666;">';
                                if (!empty($fecha_desde) && !empty($fecha_hasta)) {
                                    echo 'No se encontraron conductores para el rango de fechas y empresa seleccionados';
                                } else {
                                    echo 'Selecciona un rango de fechas para ver los conductores';
                                }
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
                                if (!empty($fecha_desde) && !empty($fecha_hasta)) {
                                    echo "No se encontraron conductores";
                                } else {
                                    echo "Selecciona fechas para ver conductores";
                                }
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
                            if (isset($result_prestamistas) && $result_prestamistas->num_rows > 0) {
                                $result_prestamistas->data_seek(0);
                                while ($prestamista = $result_prestamistas->fetch_assoc()): ?>
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

        <?php if (!empty($prestamista_seleccionado)): ?>
        <div class="resultados">
            <h2>Resultados para: <?php echo htmlspecialchars($prestamista_seleccionado); ?></h2>
            
            <?php if (!empty($empresa_seleccionada) || (!empty($fecha_desde) && !empty($fecha_hasta))): ?>
            <div class="info-meses">
                <strong>Filtro de conductores aplicado:</strong>
                <?php if (!empty($empresa_seleccionada)): ?>
                    Empresa <?php echo htmlspecialchars($empresa_seleccionada); ?> |
                <?php endif; ?>
                Fechas: <?php echo htmlspecialchars($fecha_desde); ?> al <?php echo htmlspecialchars($fecha_hasta); ?>
            </div>
            <?php endif; ?>
            
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

            <!-- ===================== -->
            <!-- CUADRO 1: CONDUCTORES -->
            <!-- ===================== -->
            <div class="subtitulo-cuadro">Cuadro 1: Préstamos de Conductores (según viajes)</div>

            <?php if (empty($prestamos_por_deudor)): ?>
                <div style="background-color: #f8d7da; padding: 12px; border-radius: 5px; margin: 15px 0;">
                    <strong>No se encontraron préstamos pendientes</strong> para los conductores seleccionados.
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
                    <?php 
                    $total_capital_general = 0;
                    $total_general = 0;
                    $total_interes_celene_general = 0;
                    $total_comision_general = 0;
                    ?>
                    <?php foreach ($prestamos_por_deudor as $deudor => $datos): ?>
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
                    <tr class="detalle-prestamo" id="detalle-<?php echo md5($deudor); ?>" style="display:none;">
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
                                    <?php foreach ($datos['prestamos_detalle'] as $index => $detalle): ?>
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
                    
                    <!-- Totales generales CONDUCTORES -->
                    <tr class="totales">
                        <td colspan="2"><strong>TOTAL GENERAL CONDUCTORES</strong></td>
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

            <!-- =========================== -->
            <!-- CUADRO 2: OTROS DEUDORES    -->
            <!-- =========================== -->
            <div class="subtitulo-cuadro">Cuadro 2: Otros Deudores (Nómina, Facturas, etc.)</div>
            <div class="cuadro-otros">
            <?php if (empty($otros_prestamos_por_deudor)): ?>
                <div style="background-color: #e2e3e5; padding: 12px; border-radius: 5px; margin: 10px 0;">
                    No hay otros deudores con préstamos pendientes diferentes a los conductores seleccionados.
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Deudor</th>
                            <th>Préstamos</th>
                            <th>Capital</th>
                            <?php if ($prestamista_seleccionado == 'Celene'): ?>
                            <th>Interés Celene</th>
                            <th>Tu Comisión</th>
                            <?php else: ?>
                            <th>Interés</th>
                            <?php endif; ?>
                            <th>Total a Pagar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $otros_total_capital_general = 0;
                        $otros_total_general = 0;
                        $otros_total_interes_celene_general = 0;
                        $otros_total_comision_general = 0;
                        ?>
                        <?php foreach ($otros_prestamos_por_deudor as $deudor => $datos): ?>
                        <?php
                            $otros_total_capital_general += $datos['total_capital'];
                            $otros_total_general += $datos['total_general'];
                            $otros_total_interes_celene_general += $datos['total_interes_celene'];
                            $otros_total_comision_general += $datos['total_comision'];
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($deudor); ?></td>
                            <td><?php echo $datos['cantidad_prestamos']; ?></td>
                            <td class="moneda">$ <?php echo number_format($datos['total_capital'], 0, ',', '.'); ?></td>
                            <?php if ($prestamista_seleccionado == 'Celene'): ?>
                            <td class="moneda">$ <?php echo number_format($datos['total_interes_celene'], 0, ',', '.'); ?></td>
                            <td class="moneda">$ <?php echo number_format($datos['total_comision'], 0, ',', '.'); ?></td>
                            <?php else: ?>
                            <td class="moneda">$ <?php echo number_format($datos['total_interes_normal'], 0, ',', '.'); ?></td>
                            <?php endif; ?>
                            <td class="moneda">$ <?php echo number_format($datos['total_general'], 0, ',', '.'); ?></td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- Totales OTROS DEUDORES -->
                        <tr class="totales">
                            <td colspan="2"><strong>TOTAL GENERAL OTROS DEUDORES</strong></td>
                            <td class="moneda">$ <?php echo number_format($otros_total_capital_general, 0, ',', '.'); ?></td>
                            <?php if ($prestamista_seleccionado == 'Celene'): ?>
                            <td class="moneda">$ <?php echo number_format($otros_total_interes_celene_general, 0, ',', '.'); ?></td>
                            <td class="moneda">$ <?php echo number_format($otros_total_comision_general, 0, ',', '.'); ?></td>
                            <?php else: ?>
                            <td class="moneda">$ <?php echo number_format($otros_total_general - $otros_total_capital_general, 0, ',', '.'); ?></td>
                            <?php endif; ?>
                            <td class="moneda">$ <?php echo number_format($otros_total_general, 0, ',', '.'); ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
            </div>

        </div>
        <?php endif; ?>
    </div>

    <script>
        // ARRAY PARA ALMACENAR DEUDORES SELECCIONADOS
        let deudoresSeleccionados = <?php echo json_encode($deudores_seleccionados); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            actualizarListaDeudores();
            actualizarContador();
        });

        function toggleDeudor(element) {
            const valor = element.getAttribute('data-value');
            const index = deudoresSeleccionados.indexOf(valor);
            
            if (index === -1) {
                deudoresSeleccionados.push(valor);
                element.classList.add('selected');
            } else {
                deudoresSeleccionados.splice(index, 1);
                element.classList.remove('selected');
            }
            
            document.getElementById('deudoresSeleccionados').value = deudoresSeleccionados.join(',');
            actualizarContador();
        }

        function actualizarListaDeudores() {
            const items = document.querySelectorAll('.deudor-item');
            items.forEach(item => {
                const valor = item.getAttribute('data-value');
                if (deudoresSeleccionados.includes(valor)) {
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
                item.addEventListener('click', function() {
                    toggleDeudor(this);
                });
            });
        }

        function actualizarContador() {
            const items = document.querySelectorAll('.deudor-item');
            const total = items.length;
            const seleccionados = deudoresSeleccionados.length;
            
            if (total > 0) {
                document.getElementById('contadorDeudores').textContent = 
                    `Seleccionados: ${seleccionados} de ${total} conductores`;
            }
        }

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

        function deseleccionarTodos() {
            const items = document.querySelectorAll('.deudor-item');
            deudoresSeleccionados = [];
            
            items.forEach(item => {
                item.classList.remove('selected');
            });
            
            document.getElementById('deudoresSeleccionados').value = '';
            actualizarContador();
        }

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
            
            const contadorElement = document.getElementById('contadorDeudores');
            if (filtro === '') {
                actualizarContador();
            } else {
                contadorElement.textContent = `Mostrando ${contador} conductor(es) que coinciden con "${filtro}"`;
            }
        });
        
        function toggleConfigCelene() {
            const prestamista = document.getElementById('prestamista').value;
            const configCelene = document.getElementById('configCelene');
            configCelene.style.display = (prestamista == 'Celene') ? 'block' : 'none';
        }
        
        function toggleDetalle(id) {
            const detalle = document.getElementById('detalle-' + id);
            if (!detalle) return;
            detalle.style.display = detalle.style.display === 'none' || detalle.style.display === '' ? 'table-row' : 'none';
        }
        
        function togglePrestamo(checkbox) {
            const fila = checkbox.closest('.fila-prestamo');
            if (!fila) return;

            if (!checkbox.checked) {
                fila.classList.add('excluido');
            } else {
                fila.classList.remove('excluido');
            }
            
            const deudorId = fila.dataset.deudor;
            actualizarTotalesDeudor(deudorId);
        }
        
        function recalcularPrestamo(input) {
            const fila = input.closest('.fila-prestamo');
            const monto = parseFloat(fila.querySelector('.monto-prestamo').textContent.replace(/[^\d]/g, ''));
            const inputMeses = fila.querySelector('.meses-input');
            const meses = parseInt(inputMeses.value);
            const prestamista = document.getElementById('prestamista').value;
            
            if (prestamista == 'Celene') {
                const interesCelene = parseFloat(document.getElementById('interes_celene').value || 0);
                const comision = parseFloat(document.getElementById('comision_celene').value || 0);
                
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
                const inputInteres = fila.querySelector('.interes-input');
                const porcentajeTotal = parseFloat(inputInteres.value || 0);
                
                const interesTotal = monto * (porcentajeTotal / 100) * meses;
                const total = monto + interesTotal;
                
                const celdaInteres = fila.querySelector('.interes-prestamo');
                const celdaTotal = fila.querySelector('.total-prestamo');
                
                celdaInteres.textContent = '$ ' + formatNumber(interesTotal);
                celdaTotal.textContent = '$ ' + formatNumber(total);
            }
            
            const checkbox = fila.querySelector('.checkbox-excluir');
            if (checkbox && checkbox.checked) {
                const deudorId = fila.dataset.deudor;
                actualizarTotalesDeudor(deudorId);
            }
        }
        
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
                if (checkbox && checkbox.checked && !fila.classList.contains('excluido')) {
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
            
            const filaDeudor = document.getElementById('fila-' + deudorId);
            if (!filaDeudor) return;

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
            
            actualizarTotalesGenerales();
        }
        
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
        
        function formatNumber(num) {
            return Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
    </script>
</body>
</html>

<?php
$conn->close();
?>
