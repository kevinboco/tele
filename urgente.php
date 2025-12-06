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

// Función para calcular meses automáticamente
function calcularMesesAutomaticos($fecha_prestamo) {
    $hoy = new DateTime();
    $fecha_prestamo_obj = new DateTime($fecha_prestamo);
    
    if ($fecha_prestamo_obj > $hoy) {
        return 1;
    }
    
    $diferencia = $fecha_prestamo_obj->diff($hoy);
    $meses = $diferencia->y * 12 + $diferencia->m;
    
    if ($diferencia->d > 0 || $meses == 0) {
        $meses++;
    }
    
    return max(1, $meses);
}

// Variables
$deudores_seleccionados = [];
$prestamista_seleccionado = '';
$cobrar_comision = isset($_POST['cobrar_comision']) ? 1 : 0; // Nuevo: activar/desactivar comisión
$porcentaje_prestamista = 10; // % que recibe el prestamista
$mi_comision = 5; // % que recibes tú
$fecha_desde = '';
$fecha_hasta = '';
$empresa_seleccionada = '';

// Fecha de corte para 10% / 13%
$FECHA_CORTE = new DateTime('2025-10-29');

// Si es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deudores_seleccionados = isset($_POST['deudores']) ? $_POST['deudores'] : [];
    if (is_string($deudores_seleccionados)) {
        $deudores_seleccionados = $deudores_seleccionados !== '' ? explode(',', $deudores_seleccionados) : [];
    }
    $prestamista_seleccionado = $_POST['prestamista'] ?? '';
    $cobrar_comision = isset($_POST['cobrar_comision']) ? 1 : 0;
    $porcentaje_prestamista = floatval($_POST['porcentaje_prestamista'] ?? 10);
    $mi_comision = floatval($_POST['mi_comision'] ?? 5);
    $fecha_desde = $_POST['fecha_desde'] ?? '';
    $fecha_hasta = $_POST['fecha_hasta'] ?? '';
    $empresa_seleccionada = $_POST['empresa'] ?? '';
    
    // Obtener prestamistas únicos
    $sql_prestamistas = "SELECT DISTINCT prestamista FROM prestamos WHERE prestamista != '' AND pagado = 0 ORDER BY prestamista";
    $result_prestamistas = $conn->query($sql_prestamistas);
    
    // Obtener conductores filtrados
    $conductores_filtrados = [];
    if (!empty($fecha_desde) && !empty($fecha_hasta)) {
        if (!empty($empresa_seleccionada)) {
            $sql_conductores = "SELECT DISTINCT nombre FROM viajes 
                                WHERE fecha BETWEEN ? AND ? 
                                AND empresa = ? 
                                AND nombre IS NOT NULL 
                                AND nombre != '' 
                                ORDER BY nombre";
            $stmt = $conn->prepare($sql_conductores);
            $stmt->bind_param("sss", $fecha_desde, $fecha_hasta, $empresa_seleccionada);
        } else {
            $sql_conductores = "SELECT DISTINCT nombre FROM viajes 
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
    // PRÉSTAMOS DE DEUDORES SELECCIONADOS (CUADRO 1)
    // ==========================
    if (!empty($deudores_seleccionados) && !empty($prestamista_seleccionado)) {
        $placeholders = str_repeat('?,', count($deudores_seleccionados) - 1) . '?';
        
        $sql = "SELECT id, deudor, prestamista, monto, fecha
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
        
        while ($fila = $result_detalle->fetch_assoc()) {
            $deudor = $fila['deudor'];
            $meses = calcularMesesAutomaticos($fila['fecha']);
            
            if ($cobrar_comision) {
                // MODO COMISIÓN ACTIVADA
                // El deudor paga: Capital + (porcentaje_prestamista + mi_comision)%
                // Pero al prestamista solo le pagas su porcentaje
                $fecha_prestamo_dt = new DateTime($fila['fecha']);
                
                // Total que paga el deudor (incluye tu comisión)
                $interes_total_deudor = $fila['monto'] * (($porcentaje_prestamista + $mi_comision) / 100) * $meses;
                $total_que_paga_deudor = $fila['monto'] + $interes_total_deudor;
                
                // Lo que recibe el prestamista
                $interes_prestamista = $fila['monto'] * ($porcentaje_prestamista / 100) * $meses;
                $total_que_recibe_prestamista = $fila['monto'] + $interes_prestamista;
                
                // Tu comisión
                $mi_comision_monto = $fila['monto'] * ($mi_comision / 100) * $meses;
                
                $tasa_interes = ($fecha_prestamo_dt >= $FECHA_CORTE) ? 13 : 10;
            } else {
                // MODO NORMAL (sin tu comisión)
                $fecha_prestamo_dt = new DateTime($fila['fecha']);
                $tasa_interes = ($fecha_prestamo_dt >= $FECHA_CORTE) ? 13 : 10;
                
                $interes_total = $fila['monto'] * ($tasa_interes / 100) * $meses;
                $total_que_paga_deudor = $fila['monto'] + $interes_total;
                $total_que_recibe_prestamista = $total_que_paga_deudor; // Sin tu comisión, todo va al prestamista
                $mi_comision_monto = 0;
                $interes_prestamista = $interes_total;
            }
            
            if (!isset($prestamos_por_deudor[$deudor])) {
                $prestamos_por_deudor[$deudor] = [
                    'total_capital' => 0,
                    'total_que_paga_deudor' => 0,
                    'total_que_recibe_prestamista' => 0,
                    'total_mi_comision' => 0,
                    'total_interes_prestamista' => 0,
                    'cantidad_prestamos' => 0,
                    'prestamos_detalle' => []
                ];
            }
            
            $prestamos_por_deudor[$deudor]['total_capital'] += $fila['monto'];
            $prestamos_por_deudor[$deudor]['total_que_paga_deudor'] += $total_que_paga_deudor;
            $prestamos_por_deudor[$deudor]['total_que_recibe_prestamista'] += $total_que_recibe_prestamista;
            $prestamos_por_deudor[$deudor]['total_mi_comision'] += $mi_comision_monto;
            $prestamos_por_deudor[$deudor]['total_interes_prestamista'] += $interes_prestamista;
            $prestamos_por_deudor[$deudor]['cantidad_prestamos']++;
            
            $prestamos_por_deudor[$deudor]['prestamos_detalle'][] = [
                'id' => $fila['id'],
                'monto' => $fila['monto'],
                'fecha' => $fila['fecha'],
                'meses' => $meses,
                'tasa_interes' => $tasa_interes,
                'interes_prestamista' => $interes_prestamista,
                'mi_comision' => $mi_comision_monto,
                'total_que_paga_deudor' => $total_que_paga_deudor,
                'total_que_recibe_prestamista' => $total_que_recibe_prestamista
            ];
        }
    }
    
    // ==========================
    // OTROS DEUDORES (NO SELECCIONADOS) - CUADRO 2
    // ==========================
    if (!empty($prestamista_seleccionado)) {
        $sql_otros = "SELECT id, deudor, prestamista, monto, fecha
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
        
        while ($fila = $result_otros->fetch_assoc()) {
            $deudor = $fila['deudor'];
            
            if (in_array($deudor, $deudores_seleccionados)) {
                continue;
            }
            
            $meses = calcularMesesAutomaticos($fila['fecha']);
            
            if ($cobrar_comision) {
                $fecha_prestamo_dt = new DateTime($fila['fecha']);
                
                $interes_total_deudor = $fila['monto'] * (($porcentaje_prestamista + $mi_comision) / 100) * $meses;
                $total_que_paga_deudor = $fila['monto'] + $interes_total_deudor;
                
                $interes_prestamista = $fila['monto'] * ($porcentaje_prestamista / 100) * $meses;
                $total_que_recibe_prestamista = $fila['monto'] + $interes_prestamista;
                
                $mi_comision_monto = $fila['monto'] * ($mi_comision / 100) * $meses;
            } else {
                $fecha_prestamo_dt = new DateTime($fila['fecha']);
                $tasa_interes = ($fecha_prestamo_dt >= $FECHA_CORTE) ? 13 : 10;
                
                $interes_total = $fila['monto'] * ($tasa_interes / 100) * $meses;
                $total_que_paga_deudor = $fila['monto'] + $interes_total;
                $total_que_recibe_prestamista = $total_que_paga_deudor;
                $mi_comision_monto = 0;
                $interes_prestamista = $interes_total;
            }
            
            if (!isset($otros_prestamos_por_deudor[$deudor])) {
                $otros_prestamos_por_deudor[$deudor] = [
                    'total_capital' => 0,
                    'total_que_paga_deudor' => 0,
                    'total_que_recibe_prestamista' => 0,
                    'total_mi_comision' => 0,
                    'total_interes_prestamista' => 0,
                    'cantidad_prestamos' => 0
                ];
            }
            
            $otros_prestamos_por_deudor[$deudor]['total_capital'] += $fila['monto'];
            $otros_prestamos_por_deudor[$deudor]['total_que_paga_deudor'] += $total_que_paga_deudor;
            $otros_prestamos_por_deudor[$deudor]['total_que_recibe_prestamista'] += $total_que_recibe_prestamista;
            $otros_prestamos_por_deudor[$deudor]['total_mi_comision'] += $mi_comision_monto;
            $otros_prestamos_por_deudor[$deudor]['total_interes_prestamista'] += $interes_prestamista;
            $otros_prestamos_por_deudor[$deudor]['cantidad_prestamos']++;
        }
    }
    
} else {
    // GET: solo prestamistas
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Mantén todos los estilos CSS que ya tienes */
        /* Solo agregaré unos estilos nuevos para el botón de comisión */
        
        .comision-toggle {
            margin: 15px 0;
            padding: 12px;
            background: rgba(56, 189, 248, 0.08);
            border-radius: 14px;
            border: 1px solid rgba(56, 189, 248, 0.4);
        }
        
        .toggle-switch {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            cursor: pointer;
        }
        
        .toggle-checkbox {
            display: none;
        }
        
        .toggle-slider {
            width: 50px;
            height: 26px;
            background: #1f2937;
            border-radius: 34px;
            position: relative;
            transition: .3s;
        }
        
        .toggle-slider:before {
            content: "";
            position: absolute;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            left: 3px;
            top: 3px;
            background: #9ca3af;
            transition: .3s;
        }
        
        .toggle-checkbox:checked + .toggle-slider {
            background: linear-gradient(135deg, #38bdf8, #0ea5e9);
        }
        
        .toggle-checkbox:checked + .toggle-slider:before {
            transform: translateX(24px);
            background: white;
        }
        
        .toggle-label {
            font-weight: 600;
            color: var(--text-main);
            font-size: 0.9rem;
        }
        
        .config-comision {
            margin-top: 12px;
            padding: 12px;
            background: rgba(34, 197, 94, 0.08);
            border-radius: 12px;
            border: 1px solid rgba(34, 197, 94, 0.3);
            display: none;
        }
        
        .config-comision.active {
            display: block;
        }
        
        .config-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 8px;
        }
        
        .config-col {
            flex: 1 1 200px;
        }
        
        .resumen-pago {
            background: rgba(168, 85, 247, 0.08);
            border-radius: 14px;
            padding: 12px 14px;
            margin: 15px 0;
            border: 1px solid rgba(168, 85, 247, 0.4);
            font-size: 0.85rem;
        }
        
        .resumen-item {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
        }
        
        .resumen-total {
            font-weight: 700;
            border-top: 1px solid rgba(168, 85, 247, 0.4);
            padding-top: 8px;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div>
                <div class="page-header-badge">
                    <span class="icon">💰</span>
                    <span>Panel de pago a prestamistas</span>
                </div>
                <h1>Reporte para Pagar a Prestamistas</h1>
            </div>
        </div>
        
        <div class="nota-pagados">
            <strong>Nota:</strong> Esta vista es <strong>SOLO para calcular cuánto pagarle al prestamista</strong>. El sistema para cobrar a los conductores es aparte.
        </div>
        
        <form method="POST" id="formPrincipal">
            <!-- Filtro de Fechas y Empresa (mantén igual) -->
            <div class="filtro-fechas form-card">
                <h3>Filtro de conductores (tabla VIAJES)</h3>
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
                    <div class="form-card">
                        <div class="form-group">
                            <label for="deudores">Seleccionar Conductores (haz clic para seleccionar):</label>
                            
                            <div class="buscador-container">
                                <input type="text" id="buscadorDeudores" class="buscador-input" 
                                       placeholder="Buscar conductor...">
                                <span class="buscador-icon">🔍</span>
                            </div>

                            <div class="botones-seleccion">
                                <button type="button" onclick="seleccionarTodos()">Seleccionar todos</button>
                                <button type="button" onclick="deseleccionarTodos()">Deseleccionar todos</button>
                            </div>
                            
                            <div class="deudores-container" id="listaDeudores">
                                <?php 
                                if (!empty($conductores_filtrados)) {
                                    foreach ($conductores_filtrados as $conductor): 
                                        $es_seleccionado = in_array($conductor, $deudores_seleccionados);
                                ?>
                                    <div class="deudor-item <?php echo $es_seleccionado ? 'selected' : ''; ?>" 
                                         data-value="<?php echo htmlspecialchars($conductor); ?>">
                                        <span><?php echo htmlspecialchars($conductor); ?></span>
                                        <span class="deudor-pill">Conductor</span>
                                    </div>
                                <?php 
                                    endforeach; 
                                } else {
                                    echo '<div style="padding: 10px; text-align: center; color: #9ca3af; font-size:0.85rem;">';
                                    if (!empty($fecha_desde) && !empty($fecha_hasta)) {
                                        echo 'No se encontraron conductores para el rango de fechas y empresa seleccionados';
                                    } else {
                                        echo 'Selecciona un rango de fechas para ver los conductores';
                                    }
                                    echo '</div>';
                                }
                                ?>
                            </div>
                            
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
                            <small>Haz clic en cada conductor para seleccionarlo o quitarlo del reporte.</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-card">
                        <div class="form-group">
                            <label for="prestamista">Seleccionar Prestamista:</label>
                            <select name="prestamista" id="prestamista" required>
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
                        
                        <!-- NUEVO: BOTÓN PARA ACTIVAR/INACTIVAR COMISIÓN -->
                        <div class="comision-toggle">
                            <div class="toggle-switch" onclick="toggleComision()">
                                <input type="checkbox" id="cobrarComision" name="cobrar_comision" 
                                       class="toggle-checkbox" <?php echo $cobrar_comision ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                                <span class="toggle-label">Cobrar comisión adicional</span>
                            </div>
                            <small>Activa esto si el prestamista ya cobra un porcentaje y tú agregas tu comisión aparte.</small>
                            
                            <div id="configComision" class="config-comision <?php echo $cobrar_comision ? 'active' : ''; ?>">
                                <div class="config-row">
                                    <div class="config-col">
                                        <label for="porcentaje_prestamista">% que recibe el prestamista:</label>
                                        <input type="number" name="porcentaje_prestamista" id="porcentaje_prestamista" 
                                               value="<?php echo $porcentaje_prestamista; ?>" step="0.1" min="0" max="100" required>
                                        <small>Ej: Celene 8%, Alexander 10%</small>
                                    </div>
                                    <div class="config-col">
                                        <label for="mi_comision">% que recibes tú (tu comisión):</label>
                                        <input type="number" name="mi_comision" id="mi_comision" 
                                               value="<?php echo $mi_comision; ?>" step="0.1" min="0" max="100" required>
                                        <small>Ej: Celene 5%, Alexander 3%</small>
                                    </div>
                                </div>
                                <div class="resumen-pago">
                                    <div class="resumen-item">
                                        <span>Total que paga el deudor:</span>
                                        <span><strong>Capital + (<?php echo $porcentaje_prestamista + $mi_comision; ?>%)</strong></span>
                                    </div>
                                    <div class="resumen-item">
                                        <span>Lo que recibe el prestamista:</span>
                                        <span><strong>Capital + <?php echo $porcentaje_prestamista; ?>%</strong></span>
                                    </div>
                                    <div class="resumen-item resumen-total">
                                        <span>Tu ganancia:</span>
                                        <span><strong><?php echo $mi_comision; ?>% sobre el capital</strong></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit">Generar reporte</button>
                    </div>
                </div>
            </div>
        </form>

        <?php if (!empty($prestamista_seleccionado)): ?>
        <div class="resultados">
            <h2 style="font-size:1.1rem;font-weight:600;margin-bottom:6px;">
                Resultados para: <span style="color:var(--accent);"><?php echo htmlspecialchars($prestamista_seleccionado); ?></span>
            </h2>
            
            <!-- Resumen del modo actual -->
            <div class="info-meses">
                <strong>Modo actual:</strong>
                <?php if ($cobrar_comision): ?>
                    <span style="color:#22c55e;">✅ COMISIÓN ACTIVADA</span><br>
                    • Prestamista recibe: <strong><?php echo $porcentaje_prestamista; ?>%</strong><br>
                    • Tú recibes: <strong><?php echo $mi_comision; ?>%</strong><br>
                    • Total interés para deudor: <strong><?php echo $porcentaje_prestamista + $mi_comision; ?>%</strong>
                <?php else: ?>
                    <span style="color:#9ca3af;">❌ COMISIÓN DESACTIVADA</span><br>
                    • Interés normal según fecha (10% antes del 29-10-2025, 13% después)<br>
                    • El prestamista recibe todo el interés
                <?php endif; ?>
            </div>

            <!-- ===================== -->
            <!-- CUADRO 1 -->
            <!-- ===================== -->
            <div class="subtitulo-cuadro">Cuadro 1: Préstamos de conductores seleccionados</div>

            <?php if (empty($prestamos_por_deudor)): ?>
                <div style="background-color: rgba(239,68,68,0.12); border:1px solid rgba(239,68,68,0.4); padding: 12px; border-radius: 10px; margin: 15px 0; font-size:0.85rem;">
                    <strong>No se encontraron préstamos pendientes</strong> para los deudores seleccionados.
                </div>
            <?php else: ?>
            <table id="tablaReporte">
                <thead>
                    <tr>
                        <th>Deudor</th>
                        <th>Préstamos</th>
                        <th>Capital</th>
                        <?php if ($cobrar_comision): ?>
                        <th>Int. Prestamista (<?php echo $porcentaje_prestamista; ?>%)</th>
                        <th>Tu Comisión (<?php echo $mi_comision; ?>%)</th>
                        <th>Total Int. (<?php echo $porcentaje_prestamista + $mi_comision; ?>%)</th>
                        <?php else: ?>
                        <th>Interés (10%/13%)</th>
                        <?php endif; ?>
                        <th>Total que paga deudor</th>
                        <th>Total que recibe prestamista</th>
                    </tr>
                </thead>
                <tbody id="cuerpoReporte">
                    <?php 
                    $total_capital_general = 0;
                    $total_interes_prestamista_general = 0;
                    $total_mi_comision_general = 0;
                    $total_que_paga_deudor_general = 0;
                    $total_que_recibe_prestamista_general = 0;
                    ?>
                    <?php foreach ($prestamos_por_deudor as $deudor => $datos): ?>
                    <?php 
                        $total_capital_general += $datos['total_capital'];
                        $total_interes_prestamista_general += $datos['total_interes_prestamista'];
                        $total_mi_comision_general += $datos['total_mi_comision'];
                        $total_que_paga_deudor_general += $datos['total_que_paga_deudor'];
                        $total_que_recibe_prestamista_general += $datos['total_que_recibe_prestamista'];
                    ?>
                    <tr class="header-deudor" id="fila-<?php echo md5($deudor); ?>">
                        <td>
                            <span class="detalle-toggle" onclick="toggleDetalle('<?php echo md5($deudor); ?>')">
                                <?php echo htmlspecialchars($deudor); ?>
                            </span>
                        </td>
                        <td><?php echo $datos['cantidad_prestamos']; ?></td>
                        <td class="moneda capital-deudor">$ <?php echo number_format($datos['total_capital'], 0, ',', '.'); ?></td>
                        
                        <?php if ($cobrar_comision): ?>
                        <td class="moneda interes-prestamista-deudor">$ <?php echo number_format($datos['total_interes_prestamista'], 0, ',', '.'); ?></td>
                        <td class="moneda mi-comision-deudor">$ <?php echo number_format($datos['total_mi_comision'], 0, ',', '.'); ?></td>
                        <td class="moneda total-interes-deudor">$ <?php echo number_format($datos['total_interes_prestamista'] + $datos['total_mi_comision'], 0, ',', '.'); ?></td>
                        <?php else: ?>
                        <td class="moneda interes-deudor">$ <?php echo number_format($datos['total_interes_prestamista'], 0, ',', '.'); ?></td>
                        <?php endif; ?>
                        
                        <td class="moneda total-paga-deudor">$ <?php echo number_format($datos['total_que_paga_deudor'], 0, ',', '.'); ?></td>
                        <td class="moneda total-recibe-prestamista" style="background:rgba(34,197,94,0.1);font-weight:600;">
                            $ <?php echo number_format($datos['total_que_recibe_prestamista'], 0, ',', '.'); ?>
                        </td>
                    </tr>
                    
                    <!-- Detalle de cada préstamo (se mantiene similar) -->
                    <tr class="detalle-prestamo" id="detalle-<?php echo md5($deudor); ?>" style="display:none;">
                        <td colspan="<?php echo $cobrar_comision ? '8' : '7'; ?>">
                            <table style="width: 100%; background-color: #020617;">
                                <thead>
                                    <tr>
                                        <th>Incluir</th>
                                        <th>Fecha</th>
                                        <th>Monto</th>
                                        <th>Meses</th>
                                        <?php if ($cobrar_comision): ?>
                                        <th>Int. Prest.</th>
                                        <th>Tu Comisión</th>
                                        <th>Total Int.</th>
                                        <?php else: ?>
                                        <th>Interés %</th>
                                        <th>Interés $</th>
                                        <?php endif; ?>
                                        <th>Total paga deudor</th>
                                        <th>Total recibe prestamista</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($datos['prestamos_detalle'] as $detalle): ?>
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
                                        <?php if ($cobrar_comision): ?>
                                        <td class="moneda interes-prestamista-prestamo">$ <?php echo number_format($detalle['interes_prestamista'], 0, ',', '.'); ?></td>
                                        <td class="moneda mi-comision-prestamo">$ <?php echo number_format($detalle['mi_comision'], 0, ',', '.'); ?></td>
                                        <td class="moneda total-interes-prestamo">$ <?php echo number_format($detalle['interes_prestamista'] + $detalle['mi_comision'], 0, ',', '.'); ?></td>
                                        <?php else: ?>
                                        <td class="acciones">
                                            <input type="number" class="interes-input" value="<?php echo $detalle['tasa_interes']; ?>" 
                                                   step="0.1" min="0" max="100" 
                                                   onchange="recalcularPrestamo(this)" 
                                                   data-monto="<?php echo $detalle['monto']; ?>">
                                        </td>
                                        <td class="moneda interes-prestamo">
                                            $ <?php echo number_format($detalle['interes_prestamista'], 0, ',', '.'); ?>
                                        </td>
                                        <?php endif; ?>
                                        <td class="moneda total-paga-prestamo">$ <?php echo number_format($detalle['total_que_paga_deudor'], 0, ',', '.'); ?></td>
                                        <td class="moneda total-recibe-prestamo" style="background:rgba(34,197,94,0.05);">
                                            $ <?php echo number_format($detalle['total_que_recibe_prestamista'], 0, ',', '.'); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Totales generales CUADRO 1 -->
                    <tr class="totales">
                        <td colspan="2"><strong>TOTAL GENERAL</strong></td>
                        <td class="moneda" id="total-capital-general">$ <?php echo number_format($total_capital_general, 0, ',', '.'); ?></td>
                        
                        <?php if ($cobrar_comision): ?>
                        <td class="moneda" id="total-interes-prestamista-general">$ <?php echo number_format($total_interes_prestamista_general, 0, ',', '.'); ?></td>
                        <td class="moneda" id="total-mi-comision-general">$ <?php echo number_format($total_mi_comision_general, 0, ',', '.'); ?></td>
                        <td class="moneda" id="total-interes-total-general">$ <?php echo number_format($total_interes_prestamista_general + $total_mi_comision_general, 0, ',', '.'); ?></td>
                        <?php else: ?>
                        <td class="moneda" id="total-interes-general">$ <?php echo number_format($total_interes_prestamista_general, 0, ',', '.'); ?></td>
                        <?php endif; ?>
                        
                        <td class="moneda" id="total-paga-deudor-general">$ <?php echo number_format($total_que_paga_deudor_general, 0, ',', '.'); ?></td>
                        <td class="moneda" id="total-recibe-prestamista-general" style="background:rgba(34,197,94,0.2);font-weight:700;">
                            $ <?php echo number_format($total_que_recibe_prestamista_general, 0, ',', '.'); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php endif; ?>
            
            <!-- Resumen final para pago -->
            <div class="resumen-pago" style="margin-top: 20px;">
                <h4 style="margin-top:0;margin-bottom:10px;">📋 Resumen para pago al prestamista</h4>
                <div class="resumen-item">
                    <span>Capital total:</span>
                    <span>$ <?php echo number_format($total_capital_general, 0, ',', '.'); ?></span>
                </div>
                <div class="resumen-item">
                    <span>Interés del prestamista:</span>
                    <span>$ <?php echo number_format($total_interes_prestamista_general, 0, ',', '.'); ?></span>
                </div>
                <div class="resumen-item resumen-total">
                    <span>🎯 TOTAL A PAGAR AL PRESTAMISTA:</span>
                    <span style="color:#22c55e;font-size:1.1em;">
                        $ <?php echo number_format($total_que_recibe_prestamista_general, 0, ',', '.'); ?>
                    </span>
                </div>
                <?php if ($cobrar_comision): ?>
                <div class="resumen-item" style="border-top:1px solid rgba(168,85,247,0.4);padding-top:8px;">
                    <span>💰 TU GANANCIA (comisión):</span>
                    <span style="color:#a855f7;font-weight:700;">
                        $ <?php echo number_format($total_mi_comision_general, 0, ',', '.'); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>

        </div>
        <?php endif; ?>
    </div>

    <script>
        // Función para activar/desactivar la configuración de comisión
        function toggleComision() {
            const checkbox = document.getElementById('cobrarComision');
            checkbox.checked = !checkbox.checked;
            
            const configDiv = document.getElementById('configComision');
            if (checkbox.checked) {
                configDiv.classList.add('active');
                // Asegurar que los campos sean requeridos cuando estén visibles
                document.getElementById('porcentaje_prestamista').required = true;
                document.getElementById('mi_comision').required = true;
            } else {
                configDiv.classList.remove('active');
                // Quitar required cuando estén ocultos
                document.getElementById('porcentaje_prestamista').required = false;
                document.getElementById('mi_comision').required = false;
            }
        }
        
        // Actualizar el resumen cuando cambien los porcentajes
        document.getElementById('porcentaje_prestamista').addEventListener('input', actualizarResumen);
        document.getElementById('mi_comision').addEventListener('input', actualizarResumen);
        
        function actualizarResumen() {
            const porcentajePrestamista = parseFloat(document.getElementById('porcentaje_prestamista').value) || 0;
            const miComision = parseFloat(document.getElementById('mi_comision').value) || 0;
            const totalPorcentaje = porcentajePrestamista + miComision;
            
            // Actualizar textos en el resumen
            const resumenItems = document.querySelectorAll('.resumen-pago .resumen-item');
            if (resumenItems.length >= 3) {
                resumenItems[0].querySelector('strong').textContent = 'Capital + (' + totalPorcentaje + '%)';
                resumenItems[1].querySelector('strong').textContent = 'Capital + ' + porcentajePrestamista + '%';
                resumenItems[2].querySelector('strong').textContent = miComision + '% sobre el capital';
            }
        }
        
        // Mantén todas las otras funciones JavaScript que ya tenías
        // (toggleDeudor, actualizarListaDeudores, actualizarContador, seleccionarTodos, etc.)
        
        // Inicializar el resumen
        document.addEventListener('DOMContentLoaded', function() {
            actualizarResumen();
            // Tu código existente para deudores
            actualizarListaDeudores();
            actualizarContador();
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>