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

// ============================================
// 1. CREAR TABLA DE CONFIGURACIÓN SI NO EXISTE
// ============================================
$sql_crear_tabla = "
CREATE TABLE IF NOT EXISTS config_prestamistas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    prestamista VARCHAR(100) UNIQUE NOT NULL,
    interes_prestamista DECIMAL(5,2) DEFAULT 10.00,
    comision_personal DECIMAL(5,2) DEFAULT 5.00,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($sql_crear_tabla);

// ============================================
// 2. PROCESAR GUARDADO DE CONFIGURACIÓN
// ============================================
if (isset($_POST['guardar_config'])) {
    $prestamista = $_POST['prestamista_config'];
    $interes_prestamista = floatval($_POST['interes_prestamista']);
    $comision_personal = floatval($_POST['comision_personal']);
    
    // Insertar o actualizar configuración
    $sql = "INSERT INTO config_prestamistas (prestamista, interes_prestamista, comision_personal) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            interes_prestamista = VALUES(interes_prestamista),
            comision_personal = VALUES(comision_personal),
            fecha_actualizacion = CURRENT_TIMESTAMP";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sdd", $prestamista, $interes_prestamista, $comision_personal);
    
    if ($stmt->execute()) {
        $mensaje_config = "✅ Configuración guardada para <strong>$prestamista</strong>";
        $tipo_mensaje = "success";
    } else {
        $mensaje_config = "❌ Error al guardar configuración";
        $tipo_mensaje = "error";
    }
}

// ============================================
// 3. OBTENER CONFIGURACIÓN ACTUAL DE PRESTAMISTAS
// ============================================
$config_prestamistas = [];
$sql_config = "SELECT * FROM config_prestamistas ORDER BY prestamista";
$result_config = $conn->query($sql_config);
if ($result_config) {
    while ($row = $result_config->fetch_assoc()) {
        $config_prestamistas[$row['prestamista']] = [
            'interes' => $row['interes_prestamista'],
            'comision' => $row['comision_personal']
        ];
    }
}

// ============================================
// 4. FUNCIÓN PARA CALCULAR MESES (SIN CAMBIOS)
// ============================================
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

// Variables para mantener los valores del formulario
$deudores_seleccionados = [];
$prestamista_seleccionado = '';
$fecha_desde = '';
$fecha_hasta = '';
$empresa_seleccionada = '';

// Arreglos para los reportes
$prestamos_por_deudor = [];
$otros_prestamos_por_deudor = [];

// Totales generales
$total_capital_general = 0;
$total_general = 0;
$total_interes_prestamista_general = 0;
$total_comision_personal_general = 0;

$otros_total_capital_general = 0;
$otros_total_general = 0;
$otros_total_interes_prestamista_general = 0;
$otros_total_comision_personal_general = 0;

// Fecha de corte para 10% / 13% (solo para préstamos sin configuración)
$FECHA_CORTE = new DateTime('2025-10-29');

// Obtener empresas desde VIAJES
$sql_empresas = "SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa != '' ORDER BY empresa";
$result_empresas = $conn->query($sql_empresas);

// Obtener lista de prestamistas para la tabla de configuración
$sql_prestamistas_lista = "SELECT DISTINCT prestamista FROM prestamos WHERE prestamista != '' ORDER BY prestamista";
$result_prestamistas_lista = $conn->query($sql_prestamistas_lista);

// Si es POST procesamos el reporte
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['guardar_config'])) {
    $deudores_seleccionados = isset($_POST['deudores']) ? $_POST['deudores'] : [];
    if (is_string($deudores_seleccionados)) {
        $deudores_seleccionados = $deudores_seleccionados !== '' ? explode(',', $deudores_seleccionados) : [];
    }
    $prestamista_seleccionado = $_POST['prestamista'] ?? '';
    $fecha_desde = $_POST['fecha_desde'] ?? '';
    $fecha_hasta = $_POST['fecha_hasta'] ?? '';
    $empresa_seleccionada = $_POST['empresa'] ?? '';

    // Prestamistas únicos (NO PAGADOS)
    $sql_prestamistas = "SELECT DISTINCT prestamista FROM prestamos WHERE prestamista != '' AND pagado = 0 ORDER BY prestamista";
    $result_prestamistas = $conn->query($sql_prestamistas);

    // ==========================
    // 1) CONDUCTORES DESDE VIAJES
    // ==========================
    $conductores_filtrados = [];
    if (!empty($fecha_desde) && !empty($fecha_hasta)) {
        if (!empty($empresa_seleccionada)) {
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
    // 2) PRÉSTAMOS DE DEUDORES SELECCIONADOS (CUADRO 1)
    // ==========================
    if (!empty($deudores_seleccionados) && !empty($prestamista_seleccionado)) {
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

        // Obtener configuración para este prestamista
        $config_actual = isset($config_prestamistas[$prestamista_seleccionado]) 
            ? $config_prestamistas[$prestamista_seleccionado] 
            : ['interes' => 10.00, 'comision' => 5.00]; // Valores por defecto

        while ($fila = $result_detalle->fetch_assoc()) {
            $deudor = $fila['deudor'];
            $meses = calcularMesesAutomaticos($fila['fecha']);

            // USAR CONFIGURACIÓN DEL PRESTAMISTA
            $interes_prestamista_monto = $fila['monto'] * ($config_actual['interes'] / 100) * $meses;
            $comision_personal_monto = $fila['monto'] * ($config_actual['comision'] / 100) * $meses;
            $total_prestamo = $fila['monto'] + $interes_prestamista_monto;
            
            // Para mostrar en la tabla (solo referencia)
            $tasa_interes = $config_actual['interes'];

            if (!isset($prestamos_por_deudor[$deudor])) {
                $prestamos_por_deudor[$deudor] = [
                    'total_capital' => 0,
                    'total_general' => 0,
                    'total_interes_prestamista' => 0,
                    'total_comision_personal' => 0,
                    'cantidad_prestamos' => 0,
                    'prestamos_detalle' => []
                ];
            }

            $prestamos_por_deudor[$deudor]['total_capital'] += $fila['monto'];
            $prestamos_por_deudor[$deudor]['total_general'] += $total_prestamo;
            $prestamos_por_deudor[$deudor]['total_interes_prestamista'] += $interes_prestamista_monto;
            $prestamos_por_deudor[$deudor]['total_comision_personal'] += $comision_personal_monto;
            $prestamos_por_deudor[$deudor]['cantidad_prestamos']++;

            $prestamos_por_deudor[$deudor]['prestamos_detalle'][] = [
                'id' => $fila['id'],
                'monto' => $fila['monto'],
                'fecha' => $fila['fecha'],
                'meses' => $meses,
                'tasa_interes' => $tasa_interes,
                'interes_prestamista' => $interes_prestamista_monto,
                'comision_personal' => $comision_personal_monto,
                'total' => $total_prestamo,
                'incluido' => true
            ];
        }
    }

    // ==========================
    // 3) OTROS DEUDORES (NO SELECCIONADOS) - CUADRO 2
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

        // Obtener configuración para este prestamista
        $config_actual = isset($config_prestamistas[$prestamista_seleccionado]) 
            ? $config_prestamistas[$prestamista_seleccionado] 
            : ['interes' => 10.00, 'comision' => 5.00];

        while ($fila = $result_otros->fetch_assoc()) {
            $deudor = $fila['deudor'];

            if (in_array($deudor, $deudores_seleccionados)) {
                continue;
            }

            $meses = calcularMesesAutomaticos($fila['fecha']);

            // USAR CONFIGURACIÓN DEL PRESTAMISTA
            $interes_prestamista_monto = $fila['monto'] * ($config_actual['interes'] / 100) * $meses;
            $comision_personal_monto = $fila['monto'] * ($config_actual['comision'] / 100) * $meses;
            $total_prestamo = $fila['monto'] + $interes_prestamista_monto;

            if (!isset($otros_prestamos_por_deudor[$deudor])) {
                $otros_prestamos_por_deudor[$deudor] = [
                    'total_capital' => 0,
                    'total_general' => 0,
                    'total_interes_prestamista' => 0,
                    'total_comision_personal' => 0,
                    'cantidad_prestamos' => 0
                ];
            }

            $otros_prestamos_por_deudor[$deudor]['total_capital'] += $fila['monto'];
            $otros_prestamos_por_deudor[$deudor]['total_general'] += $total_prestamo;
            $otros_prestamos_por_deudor[$deudor]['total_interes_prestamista'] += $interes_prestamista_monto;
            $otros_prestamos_por_deudor[$deudor]['total_comision_personal'] += $comision_personal_monto;
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
    <!-- Fuente bonita -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{
            --bg-main: #020617;
            --bg-card: #0b1220;
            --bg-card-soft: #020617;
            --accent: #38bdf8;
            --accent-soft: rgba(56,189,248,0.15);
            --accent-strong: #0ea5e9;
            --danger: #ef4444;
            --success: #22c55e;
            --text-main: #e5e7eb;
            --text-soft: #9ca3af;
            --border-subtle: #1f2937;
            --table-header: #020617;
        }

        *{
            box-sizing:border-box;
        }

        body{
            margin:0;
            padding:0;
            font-family:'Inter',system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
            background: radial-gradient(circle at top, #0f172a 0, #020617 40%, #000 100%);
            color:var(--text-main);
        }

        .container{
            max-width:1600px;
            margin:20px auto 40px;
            padding:24px;
            background:linear-gradient(135deg,rgba(15,23,42,0.95),rgba(2,6,23,0.98));
            border-radius:24px;
            border:1px solid rgba(148,163,184,0.25);
            box-shadow:
                0 20px 45px rgba(15,23,42,0.9),
                0 0 0 1px rgba(15,23,42,0.8);
            backdrop-filter:blur(18px);
        }

        h1{
            margin-top:0;
            font-size:1.9rem;
            font-weight:700;
            letter-spacing:0.03em;
            background:linear-gradient(90deg,#38bdf8,#a855f7,#22c55e);
            -webkit-background-clip:text;
            color:transparent;
        }

        .page-header{
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:16px;
            margin-bottom:18px;
        }

        .page-header-badge{
            display:inline-flex;
            align-items:center;
            gap:6px;
            padding:4px 12px;
            border-radius:999px;
            background:rgba(56,189,248,0.12);
            border:1px solid rgba(56,189,248,0.4);
            font-size:0.75rem;
            text-transform:uppercase;
            letter-spacing:0.12em;
            color:var(--accent);
        }

        /* NUEVO: Estilo para tabla de configuración */
        .config-section {
            background: var(--bg-card);
            border-radius: 18px;
            border: 1px solid var(--border-subtle);
            padding: 18px 20px;
            margin-bottom: 24px;
        }

        .config-section h3 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--accent);
        }

        .config-section h3::before {
            content: "⚙";
            font-size: 1.2rem;
        }

        .config-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 0.85rem;
            background: var(--bg-card-soft);
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid var(--border-subtle);
        }

        .config-table th {
            background: linear-gradient(180deg, var(--table-header), #020617);
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-soft);
        }

        .config-table td {
            padding: 8px 12px;
            border-bottom: 1px solid rgba(31,41,55,0.9);
        }

        .config-table tr:last-child td {
            border-bottom: none;
        }

        .config-table tr:hover {
            background: rgba(15,23,42,0.9);
        }

        .config-input {
            width: 100%;
            padding: 6px 8px;
            border-radius: 8px;
            border: 1px solid rgba(148,163,184,0.35);
            background: #020617;
            color: var(--text-main);
            font-size: 0.85rem;
            text-align: center;
        }

        .config-input:focus {
            border-color: var(--accent);
            outline: none;
        }

        .btn-guardar {
            width: auto;
            padding: 6px 16px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-guardar:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.4);
        }

        .mensaje-config {
            padding: 10px 14px;
            border-radius: 10px;
            margin: 10px 0;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .mensaje-success {
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.4);
            color: #22c55e;
        }

        .mensaje-error {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #ef4444;
        }

        /* Resto de estilos (igual que antes) */
        .nota-pagados{
            background:rgba(34,197,94,0.11);
            border-radius:14px;
            padding:12px 14px;
            border:1px solid rgba(34,197,94,0.4);
            font-size:0.85rem;
            display:flex;
            align-items:flex-start;
            gap:8px;
            margin-bottom:18px;
        }

        .form-card{
            background:var(--bg-card);
            border-radius:18px;
            border:1px solid var(--border-subtle);
            padding:16px 18px 18px;
            margin-bottom:20px;
        }

        .form-group{
            margin-bottom:16px;
        }
        label{
            display:block;
            margin-bottom:6px;
            font-weight:500;
            font-size:0.85rem;
            color:var(--text-soft);
        }

        select,button,input{
            width:100%;
            padding:9px 11px;
            margin:4px 0;
            border-radius:10px;
            border:1px solid rgba(148,163,184,0.35);
            background:#020617;
            color:var(--text-main);
            font-size:0.9rem;
            outline:none;
            transition:all .18s ease;
        }

        /* ... resto de estilos igual que antes ... */

        /* Añadir al final del CSS existente */
        .info-porcentajes {
            background: rgba(56, 189, 248, 0.08);
            border-radius: 14px;
            padding: 12px 14px;
            margin: 10px 0 16px;
            border: 1px solid rgba(56, 189, 248, 0.4);
            font-size: 0.85rem;
        }

        .info-porcentajes strong {
            color: var(--accent);
        }

    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div>
                <div class="page-header-badge">
                    <span class="icon">📊</span>
                    <span>Panel de préstamos pendientes</span>
                </div>
                <h1>Reporte de Préstamos Consolidados</h1>
            </div>
        </div>
        
        <!-- NUEVA SECCIÓN: Configuración de Porcentajes por Prestamista -->
        <div class="config-section">
            <h3>⚙ Configurar Porcentajes por Prestamista</h3>
            
            <?php if (isset($mensaje_config)): ?>
                <div class="mensaje-config <?php echo $tipo_mensaje == 'success' ? 'mensaje-success' : 'mensaje-error'; ?>">
                    <?php echo $mensaje_config; ?>
                </div>
            <?php endif; ?>
            
            <table class="config-table">
                <thead>
                    <tr>
                        <th>Prestamista</th>
                        <th>Interés del Prestamista (%)</th>
                        <th>Tu Comisión (%)</th>
                        <th>Total para Deudor (%)</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_prestamistas_lista && $result_prestamistas_lista->num_rows > 0): ?>
                        <?php $result_prestamistas_lista->data_seek(0); ?>
                        <?php while ($prest = $result_prestamistas_lista->fetch_assoc()): ?>
                            <?php 
                            $prestamista_nombre = $prest['prestamista'];
                            $config = isset($config_prestamistas[$prestamista_nombre]) 
                                ? $config_prestamistas[$prestamista_nombre] 
                                : ['interes' => 10.00, 'comision' => 5.00];
                            $total_porcentaje = $config['interes'] + $config['comision'];
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($prestamista_nombre); ?></strong></td>
                                <td>
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="prestamista_config" value="<?php echo htmlspecialchars($prestamista_nombre); ?>">
                                        <input type="number" name="interes_prestamista" 
                                               class="config-input" 
                                               value="<?php echo number_format($config['interes'], 2, '.', ''); ?>"
                                               step="0.1" min="0" max="100" required>
                                </td>
                                <td>
                                        <input type="number" name="comision_personal" 
                                               class="config-input" 
                                               value="<?php echo number_format($config['comision'], 2, '.', ''); ?>"
                                               step="0.1" min="0" max="100" required>
                                </td>
                                <td style="text-align: center; font-weight: 600; color: var(--accent);">
                                    <?php echo number_format($total_porcentaje, 2); ?>%
                                </td>
                                <td>
                                        <button type="submit" name="guardar_config" class="btn-guardar">
                                            💾 Guardar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 20px; color: var(--text-soft);">
                                No hay prestamistas registrados
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="info-porcentajes">
                <strong>💡 ¿Cómo funciona?</strong><br>
                1. <strong>Interés del Prestamista</strong>: Lo que recibe quien te presta el dinero.<br>
                2. <strong>Tu Comisión</strong>: Lo que ganas tú por intermediar.<br>
                3. <strong>Total para Deudor</strong>: Suma de ambos (lo que paga el deudor).<br>
                Ejemplo: Si pones 8% y 5%, el deudor pagará 13% de interés mensual.
            </div>
        </div>
        
        <div class="nota-pagados">
            <strong>Nota:</strong> Esta vista solo muestra préstamos que están <strong>pendientes de pago</strong> (pagado = 0). Los préstamos ya pagados no aparecen en esta lista.
        </div>
        
        <form method="POST" id="formPrincipal">
            <!-- Filtro de Fechas y Empresa -->
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
                            
                            <!-- Buscador para conductores -->
                            <div class="buscador-container">
                                <input type="text" id="buscadorDeudores" class="buscador-input" 
                                       placeholder="Buscar conductor...">
                                <span class="buscador-icon">🔍</span>
                            </div>

                            <!-- Botones de selección rápida -->
                            <div class="botones-seleccion">
                                <button type="button" onclick="seleccionarTodos()">Seleccionar todos</button>
                                <button type="button" onclick="deseleccionarTodos()">Deseleccionar todos</button>
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
                                    while ($prestamista = $result_prestamistas->fetch_assoc()): 
                                        $config_p = isset($config_prestamistas[$prestamista['prestamista']]) 
                                            ? $config_prestamistas[$prestamista['prestamista']] 
                                            : ['interes' => 10.00, 'comision' => 5.00];
                                        $total_p = $config_p['interes'] + $config_p['comision'];
                                ?>
                                        <option value="<?php echo htmlspecialchars($prestamista['prestamista']); ?>" 
                                            <?php echo $prestamista_seleccionado == $prestamista['prestamista'] ? 'selected' : ''; ?>
                                            data-interes="<?php echo $config_p['interes']; ?>"
                                            data-comision="<?php echo $config_p['comision']; ?>"
                                            data-total="<?php echo $total_p; ?>">
                                            <?php echo htmlspecialchars($prestamista['prestamista']); ?> 
                                            (<?php echo number_format($config_p['interes'], 1); ?>% + <?php echo number_format($config_p['comision'], 1); ?>% = <?php echo number_format($total_p, 1); ?>%)
                                        </option>
                                <?php endwhile; 
                                }
                                ?>
                            </select>
                            <small>Los porcentajes entre paréntesis son los configurados arriba.</small>
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
                <?php 
                $config_actual = isset($config_prestamistas[$prestamista_seleccionado]) 
                    ? $config_prestamistas[$prestamista_seleccionado] 
                    : ['interes' => 10.00, 'comision' => 5.00];
                ?>
                <span style="font-size:0.9rem; color:var(--text-soft); margin-left:10px;">
                    (Configuración: <?php echo number_format($config_actual['interes'], 1); ?>% interés + <?php echo number_format($config_actual['comision'], 1); ?>% comisión)
                </span>
            </h2>
            
            <?php if (!empty($empresa_seleccionada) || (!empty($fecha_desde) && !empty($fecha_hasta))): ?>
            <div class="info-meses">
                <strong>Filtro de conductores aplicado:</strong>
                <?php if (!empty($empresa_seleccionada)): ?>
                    Empresa <strong><?php echo htmlspecialchars($empresa_seleccionada); ?></strong> |
                <?php endif; ?>
                Fechas: <strong><?php echo htmlspecialchars($fecha_desde); ?></strong> al <strong><?php echo htmlspecialchars($fecha_hasta); ?></strong>
            </div>
            <?php endif; ?>
            
            <div class="info-meses">
                <strong>Cálculo de interés y meses:</strong><br>
                • Los meses se calculan automáticamente según la fecha del préstamo y la fecha actual.<br>
                • <strong>Interés del prestamista</strong>: <?php echo number_format($config_actual['interes'], 1); ?>% mensual (lo que recibe <?php echo htmlspecialchars($prestamista_seleccionado); ?>).<br>
                • <strong>Tu comisión</strong>: <?php echo number_format($config_actual['comision'], 1); ?>% mensual (lo que ganas tú).<br>
                • <strong>Total interés para el deudor</strong>: <?php echo number_format($config_actual['interes'] + $config_actual['comision'], 1); ?>% mensual.
            </div>

            <!-- ===================== -->
            <!-- CUADRO 1 -->
            <!-- ===================== -->
            <div class="subtitulo-cuadro">Cuadro 1: Préstamos de conductores y otros deudores seleccionados</div>

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
                        <th>Interés Prestamista (<?php echo number_format($config_actual['interes'], 1); ?>%)</th>
                        <th>Tu Comisión (<?php echo number_format($config_actual['comision'], 1); ?>%)</th>
                        <th>Total a pagar</th>
                    </tr>
                </thead>
                <tbody id="cuerpoReporte">
                    <?php 
                    $total_capital_general = 0;
                    $total_general = 0;
                    $total_interes_prestamista_general = 0;
                    $total_comision_personal_general = 0;
                    ?>
                    <?php foreach ($prestamos_por_deudor as $deudor => $datos): ?>
                    <?php 
                        $total_capital_general += $datos['total_capital'];
                        $total_general += $datos['total_general'];
                        $total_interes_prestamista_general += $datos['total_interes_prestamista'];
                        $total_comision_personal_general += $datos['total_comision_personal'];
                    ?>
                    <tr class="header-deudor" id="fila-<?php echo md5($deudor); ?>">
                        <td>
                            <span class="detalle-toggle" onclick="toggleDetalle('<?php echo md5($deudor); ?>')">
                                <?php echo htmlspecialchars($deudor); ?>
                            </span>
                        </td>
                        <td><?php echo $datos['cantidad_prestamos']; ?></td>
                        <td class="moneda capital-deudor">$ <?php echo number_format($datos['total_capital'], 0, ',', '.'); ?></td>
                        <td class="moneda interes-prestamista-deudor">$ <?php echo number_format($datos['total_interes_prestamista'], 0, ',', '.'); ?></td>
                        <td class="moneda comision-deudor">$ <?php echo number_format($datos['total_comision_personal'], 0, ',', '.'); ?></td>
                        <td class="moneda total-deudor">$ <?php echo number_format($datos['total_general'], 0, ',', '.'); ?></td>
                    </tr>
                    
                    <!-- Detalle de cada préstamo -->
                    <tr class="detalle-prestamo" id="detalle-<?php echo md5($deudor); ?>" style="display:none;">
                        <td colspan="6">
                            <table style="width: 100%; background-color: #020617;">
                                <thead>
                                    <tr>
                                        <th>Incluir</th>
                                        <th>Fecha</th>
                                        <th>Monto</th>
                                        <th>Meses</th>
                                        <th>Int. Prestamista $</th>
                                        <th>Tu Comisión $</th>
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
                                        <td class="moneda interes-prestamista-prestamo">$ <?php echo number_format($detalle['interes_prestamista'], 0, ',', '.'); ?></td>
                                        <td class="moneda comision-prestamo">$ <?php echo number_format($detalle['comision_personal'], 0, ',', '.'); ?></td>
                                        <td class="moneda total-prestamo">$ <?php echo number_format($detalle['total'], 0, ',', '.'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Totales generales CUADRO 1 -->
                    <tr class="totales">
                        <td colspan="2"><strong>TOTAL GENERAL CONDUCTORES / DEUDORES SELECCIONADOS</strong></td>
                        <td class="moneda" id="total-capital-general">$ <?php echo number_format($total_capital_general, 0, ',', '.'); ?></td>
                        <td class="moneda" id="total-interes-prestamista-general">$ <?php echo number_format($total_interes_prestamista_general, 0, ',', '.'); ?></td>
                        <td class="moneda" id="total-comision-general">$ <?php echo number_format($total_comision_personal_general, 0, ',', '.'); ?></td>
                        <td class="moneda" id="total-general">$ <?php echo number_format($total_general, 0, ',', '.'); ?></td>
                    </tr>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- =========================== -->
            <!-- CUADRO 2: OTROS DEUDORES    -->
            <!-- =========================== -->
            <div class="subtitulo-cuadro">Cuadro 2: Otros deudores (nómina, facturas, etc.)</div>
            <div class="cuadro-otros">
            <?php if (empty($otros_prestamos_por_deudor)): ?>
                <div style="background-color: rgba(148,163,184,0.06); border:1px solid rgba(148,163,184,0.3); padding: 10px; border-radius: 10px; margin: 10px 0; font-size:0.85rem;">
                    No hay otros deudores con préstamos pendientes diferentes a los ya seleccionados.
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Incluir en Cuadro 1</th>
                            <th>Deudor</th>
                            <th>Préstamos</th>
                            <th>Capital</th>
                            <th>Interés Prestamista</th>
                            <th>Tu Comisión</th>
                            <th>Total a pagar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $otros_total_capital_general = 0;
                        $otros_total_general = 0;
                        $otros_total_interes_prestamista_general = 0;
                        $otros_total_comision_personal_general = 0;
                        ?>
                        <?php foreach ($otros_prestamos_por_deudor as $deudor => $datos): ?>
                        <?php
                            $otros_total_capital_general += $datos['total_capital'];
                            $otros_total_general += $datos['total_general'];
                            $otros_total_interes_prestamista_general += $datos['total_interes_prestamista'];
                            $otros_total_comision_personal_general += $datos['total_comision_personal'];
                        ?>
                        <tr>
                            <td class="acciones">
                                <input type="checkbox"
                                       class="checkbox-otro-deudor"
                                       data-deudor="<?php echo htmlspecialchars($deudor); ?>"
                                       onchange="toggleOtroDeudor(this)">
                            </td>
                            <td><?php echo htmlspecialchars($deudor); ?></td>
                            <td><?php echo $datos['cantidad_prestamos']; ?></td>
                            <td class="moneda">$ <?php echo number_format($datos['total_capital'], 0, ',', '.'); ?></td>
                            <td class="moneda">$ <?php echo number_format($datos['total_interes_prestamista'], 0, ',', '.'); ?></td>
                            <td class="moneda">$ <?php echo number_format($datos['total_comision_personal'], 0, ',', '.'); ?></td>
                            <td class="moneda">$ <?php echo number_format($datos['total_general'], 0, ',', '.'); ?></td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- Totales OTROS DEUDORES -->
                        <tr class="totales">
                            <td colspan="3"><strong>TOTAL GENERAL OTROS DEUDORES</strong></td>
                            <td class="moneda">$ <?php echo number_format($otros_total_capital_general, 0, ',', '.'); ?></td>
                            <td class="moneda">$ <?php echo number_format($otros_total_interes_prestamista_general, 0, ',', '.'); ?></td>
                            <td class="moneda">$ <?php echo number_format($otros_total_comision_personal_general, 0, ',', '.'); ?></td>
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
            
            // Actualizar información al cambiar prestamista
            document.getElementById('prestamista').addEventListener('change', function() {
                const option = this.options[this.selectedIndex];
                if (option) {
                    const interes = option.getAttribute('data-interes');
                    const comision = option.getAttribute('data-comision');
                    const total = option.getAttribute('data-total');
                    console.log(`Prestamista seleccionado: Interés ${interes}%, Comisión ${comision}%, Total ${total}%`);
                }
            });
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
            
            // Obtener configuración del prestamista seleccionado
            const prestamistaSelect = document.getElementById('prestamista');
            const selectedOption = prestamistaSelect.options[prestamistaSelect.selectedIndex];
            const interesPrestamista = parseFloat(selectedOption.getAttribute('data-interes') || 0);
            const comisionPersonal = parseFloat(selectedOption.getAttribute('data-comision') || 0);
            
            const interesPrestamistaMonto = monto * (interesPrestamista / 100) * meses;
            const comisionPersonalMonto = monto * (comisionPersonal / 100) * meses;
            const total = monto + interesPrestamistaMonto;
            
            const celdaInteresPrestamista = fila.querySelector('.interes-prestamista-prestamo');
            const celdaComision = fila.querySelector('.comision-prestamo');
            const celdaTotal = fila.querySelector('.total-prestamo');
            
            celdaInteresPrestamista.textContent = '$ ' + formatNumber(interesPrestamistaMonto);
            celdaComision.textContent = '$ ' + formatNumber(comisionPersonalMonto);
            celdaTotal.textContent = '$ ' + formatNumber(total);
            
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
            let totalInteresPrestamista = 0;
            let totalComisionPersonal = 0;
            let prestamosIncluidos = 0;
            
            filasPrestamos.forEach(fila => {
                const checkbox = fila.querySelector('.checkbox-excluir');
                if (checkbox && checkbox.checked && !fila.classList.contains('excluido')) {
                    const monto = parseFloat(fila.querySelector('.monto-prestamo').textContent.replace(/[^\d]/g, ''));
                    const total = parseFloat(fila.querySelector('.total-prestamo').textContent.replace(/[^\d]/g, ''));
                    const interesPrestamista = parseFloat(fila.querySelector('.interes-prestamista-prestamo').textContent.replace(/[^\d]/g, ''));
                    const comision = parseFloat(fila.querySelector('.comision-prestamo').textContent.replace(/[^\d]/g, ''));
                    
                    totalCapital += monto;
                    totalGeneral += total;
                    totalInteresPrestamista += interesPrestamista;
                    totalComisionPersonal += comision;
                    prestamosIncluidos++;
                }
            });
            
            const filaDeudor = document.getElementById('fila-' + deudorId);
            if (!filaDeudor) return;

            filaDeudor.querySelector('.capital-deudor').textContent = '$ ' + formatNumber(totalCapital);
            filaDeudor.querySelector('.total-deudor').textContent = '$ ' + formatNumber(totalGeneral);
            filaDeudor.querySelector('.interes-prestamista-deudor').textContent = '$ ' + formatNumber(totalInteresPrestamista);
            filaDeudor.querySelector('.comision-deudor').textContent = '$ ' + formatNumber(totalComisionPersonal);
            filaDeudor.querySelector('td:nth-child(2)').textContent = prestamosIncluidos;
            
            actualizarTotalesGenerales();
        }
        
        function actualizarTotalesGenerales() {
            let totalCapital = 0;
            let totalGeneral = 0;
            let totalInteresPrestamista = 0;
            let totalComisionPersonal = 0;
            
            document.querySelectorAll('.header-deudor').forEach(fila => {
                totalCapital += parseFloat(fila.querySelector('.capital-deudor').textContent.replace(/[^\d]/g, ''));
                totalGeneral += parseFloat(fila.querySelector('.total-deudor').textContent.replace(/[^\d]/g, ''));
                totalInteresPrestamista += parseFloat(fila.querySelector('.interes-prestamista-deudor').textContent.replace(/[^\d]/g, ''));
                totalComisionPersonal += parseFloat(fila.querySelector('.comision-deudor').textContent.replace(/[^\d]/g, ''));
            });
            
            document.getElementById('total-capital-general').textContent = '$ ' + formatNumber(totalCapital);
            document.getElementById('total-general').textContent = '$ ' + formatNumber(totalGeneral);
            document.getElementById('total-interes-prestamista-general').textContent = '$ ' + formatNumber(totalInteresPrestamista);
            document.getElementById('total-comision-general').textContent = '$ ' + formatNumber(totalComisionPersonal);
        }

        // Seleccionar otros deudores para pasarlos al cuadro 1
        function toggleOtroDeudor(checkbox) {
            const deudor = checkbox.getAttribute('data-deudor');
            const index = deudoresSeleccionados.indexOf(deudor);

            if (checkbox.checked) {
                if (index === -1) {
                    deudoresSeleccionados.push(deudor);
                }
            } else {
                if (index !== -1) {
                    deudoresSeleccionados.splice(index, 1);
                }
            }

            document.getElementById('deudoresSeleccionados').value = deudoresSeleccionados.join(',');
            document.getElementById('formPrincipal').submit();
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