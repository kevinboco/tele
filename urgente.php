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
$porcentaje_interes = 10; // ya no se usa para el cálculo, pero lo dejamos
$comision_celene = 5;
$interes_celene = 8;
$fecha_desde = '';
$fecha_hasta = '';
$empresa_seleccionada = '';

// Arreglos para los reportes
$prestamos_por_deudor = [];          // CONDUCTORES + otros seleccionados
$otros_prestamos_por_deudor = [];    // OTROS DEUDORES

// Totales generales cuadro 1
$total_capital_general = 0;
$total_general = 0;
$total_interes_celene_general = 0;
$total_comision_general = 0;

// Totales generales cuadro 2
$otros_total_capital_general = 0;
$otros_total_general = 0;
$otros_total_interes_celene_general = 0;
$otros_total_comision_general = 0;

// Fecha de corte para 10% / 13%
$FECHA_CORTE = new DateTime('2025-10-29');

// Obtener empresas desde VIAJES
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

        $es_celene = ($prestamista_seleccionado == 'Celene');

        while ($fila = $result_detalle->fetch_assoc()) {
            $deudor = $fila['deudor'];
            $meses = calcularMesesAutomaticos($fila['fecha']);

            if ($es_celene) {
                // Celene: Capital + Interés Celene (NO incluye tu comisión en el total)
                $interes_celene_monto = $fila['monto'] * ($interes_celene / 100) * $meses;
                $comision_monto = $fila['monto'] * ($comision_celene / 100) * $meses;
                $total_prestamo = $fila['monto'] + $interes_celene_monto;
                $tasa_interes = 0;
            } else {
                // OTROS PRESTAMISTAS: 10% o 13% según fecha del préstamo
                $fecha_prestamo_dt = new DateTime($fila['fecha']);
                $tasa_interes = ($fecha_prestamo_dt >= $FECHA_CORTE) ? 13 : 10;

                $interes_total = $fila['monto'] * ($tasa_interes / 100) * $meses;
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
                'tasa_interes' => $tasa_interes,
                'interes_celene' => $interes_celene_monto ?? 0,
                'comision' => $comision_monto ?? 0,
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

        $es_celene = ($prestamista_seleccionado == 'Celene');

        while ($fila = $result_otros->fetch_assoc()) {
            $deudor = $fila['deudor'];

            // Si ya está en el cuadro 1 (seleccionado), no lo mostramos en cuadro 2
            if (in_array($deudor, $deudores_seleccionados)) {
                continue;
            }

            $meses = calcularMesesAutomaticos($fila['fecha']);

            if ($es_celene) {
                $interes_celene_monto = $fila['monto'] * ($interes_celene / 100) * $meses;
                $comision_monto = $fila['monto'] * ($comision_celene / 100) * $meses;
                $total_prestamo = $fila['monto'] + $interes_celene_monto;
                $interes_total = 0;
            } else {
                // MISMA REGLA 10% / 13%
                $fecha_prestamo_dt = new DateTime($fila['fecha']);
                $tasa_interes = ($fecha_prestamo_dt >= $FECHA_CORTE) ? 13 : 10;

                $interes_total = $fila['monto'] * ($tasa_interes / 100) * $meses;
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
        .page-header-badge span.icon{
            font-size:0.9rem;
        }

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
        .nota-pagados::before{
            content:"✔";
            color:var(--success);
            margin-top:2px;
        }

        .form-card{
            background:var(--bg-card);
            border-radius:18px;
            border:1px solid var(--border-subtle);
            padding:16px 18px 18px;
            margin-bottom:20px;
        }

        .form-card h3{
            margin-top:0;
            margin-bottom:10px;
            font-size:1rem;
            font-weight:600;
            display:flex;
            align-items:center;
            gap:8px;
        }
        .form-card h3::before{
            content:"⚙";
            font-size:1rem;
            opacity:0.9;
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

        select:focus,input:focus{
            border-color:var(--accent);
            box-shadow:0 0 0 1px rgba(56,189,248,0.4);
        }

        button{
            border-radius:999px;
            background:linear-gradient(135deg,var(--accent),var(--accent-strong));
            border:none;
            font-weight:600;
            text-transform:uppercase;
            letter-spacing:0.08em;
            font-size:0.8rem;
            cursor:pointer;
            padding:10px 16px;
            box-shadow:0 10px 18px rgba(56,189,248,0.35);
        }
        button:hover{
            transform:translateY(-1px);
            box-shadow:0 14px 26px rgba(56,189,248,0.5);
            filter:brightness(1.05);
        }
        button:active{
            transform:translateY(0);
            box-shadow:0 8px 14px rgba(56,189,248,0.35);
        }

        select[multiple]{height:200px;}

        .resultados{margin-top:26px;}

        table{
            width:100%;
            border-collapse:collapse;
            margin-top:12px;
            font-size:0.85rem;
            background:var(--bg-card-soft);
            border-radius:16px;
            overflow:hidden;
            border:1px solid var(--border-subtle);
        }

        th,td{
            border-bottom:1px solid rgba(31,41,55,0.9);
            padding:8px 9px;
            text-align:left;
        }

        thead th{
            background:linear-gradient(180deg,var(--table-header),#020617);
            position:sticky;
            top:0;
            z-index:1;
            font-size:0.78rem;
            text-transform:uppercase;
            letter-spacing:0.08em;
            color:var(--text-soft);
        }

        tbody tr:nth-child(even){
            background:#020617;
        }
        tbody tr:nth-child(odd){
            background:#020617;
        }

        tbody tr:hover{
            background:rgba(15,23,42,0.9);
        }

        .totales{
            background:rgba(15,23,42,0.96);
            font-weight:600;
        }

        .moneda{text-align:right;font-variant-numeric:tabular-nums;}

        .form-row{
            display:flex;
            flex-wrap:wrap;
            gap:18px;
        }
        .form-col{flex:1 1 280px;}

        .detalle-toggle{
            cursor:pointer;
            color:var(--accent);
            font-weight:500;
        }
        .detalle-toggle::before{
            content:"▸ ";
            font-size:0.8rem;
            opacity:0.85;
        }

        .detalle-prestamo{
            background:#020617;
        }

        .header-deudor{
            background:#020617;
        }

        .excluido{
            background:rgba(127,29,29,0.45) !important;
            text-decoration:line-through;
            color:#9ca3af;
        }

        .interes-input,.meses-input,.comision-input{
            width:72px;
            padding:4px;
            font-size:0.78rem;
            text-align:center;
            border-radius:8px;
            background:#020617;
        }

        .checkbox-excluir{
            transform:scale(1.2);
            cursor:pointer;
        }

        .acciones{text-align:center;}

        .info-meses{
            background:rgba(251,191,36,0.08);
            border-radius:14px;
            padding:10px 12px;
            margin:10px 0 16px;
            border:1px solid rgba(245,158,11,0.4);
            font-size:0.82rem;
            color:#facc15;
        }

        .config-celene{
            background:rgba(56,189,248,0.06);
            padding:12px 14px;
            border-radius:14px;
            margin:10px 0;
            border:1px solid rgba(56,189,248,0.5);
        }
        .config-celene h4{
            margin:0 0 8px;
            font-size:0.9rem;
            font-weight:600;
            display:flex;
            align-items:center;
            gap:6px;
        }
        .config-celene h4::before{
            content:"💎";
        }

        .buscador-container{
            position:relative;
            margin-bottom:10px;
        }
        .buscador-input{
            width:100%;
            padding:8px 30px 8px 10px;
            border-radius:999px;
            border:1px solid rgba(148,163,184,0.4);
            background:#020617;
            font-size:0.85rem;
        }
        .buscador-icon{
            position:absolute;
            right:10px;
            top:50%;
            transform:translateY(-50%);
            color:#6b7280;
            font-size:0.9rem;
        }

        .contador-deudores{
            font-size:0.78rem;
            color:var(--text-soft);
            margin-top:5px;
        }

        .deudor-item{
            padding:7px 9px;
            cursor:pointer;
            border-bottom:1px solid rgba(31,41,55,0.9);
            font-size:0.85rem;
            display:flex;
            align-items:center;
            justify-content:space-between;
        }

        .deudor-item:hover{
            background:rgba(30,64,175,0.5);
        }

        .deudor-item.selected{
            background:linear-gradient(90deg,#1d4ed8,#22c55e);
            color:white;
            border-bottom-color:transparent;
        }

        .deudores-container{
            border:1px solid var(--border-subtle);
            border-radius:14px;
            max-height:220px;
            overflow-y:auto;
            background:#020617;
        }

        .deudor-pill{
            font-size:0.7rem;
            padding:2px 7px;
            border-radius:999px;
            background:rgba(15,23,42,0.9);
            border:1px solid rgba(148,163,184,0.4);
        }

        .botones-seleccion{
            margin:6px 0 10px;
            display:flex;
            flex-wrap:wrap;
            gap:8px;
        }
        .botones-seleccion button{
            width:auto;
            padding:6px 10px;
            font-size:0.75rem;
            box-shadow:none;
            background:rgba(15,23,42,0.9);
            border:1px solid rgba(148,163,184,0.7);
            text-transform:none;
            letter-spacing:0.03em;
        }
        .botones-seleccion button:hover{
            box-shadow:0 0 0 1px rgba(148,163,184,0.9);
            transform:none;
        }

        .filtro-fechas{
            background:var(--bg-card);
            padding:14px 16px;
            border-radius:16px;
            margin:12px 0 18px;
            border:1px solid var(--border-subtle);
        }

        .filtro-fechas small{
            font-size:0.78rem;
            color:var(--text-soft);
        }

        .fecha-row{
            display:flex;
            flex-wrap:wrap;
            gap:12px;
        }
        .fecha-col{
            flex:1 1 180px;
        }

        .subtitulo-cuadro{
            margin-top:20px;
            font-size:1rem;
            font-weight:600;
            display:flex;
            align-items:center;
            gap:8px;
        }
        .subtitulo-cuadro::before{
            content:"◆";
            font-size:0.75rem;
            color:var(--accent);
        }

        .cuadro-otros{
            background:linear-gradient(145deg,#020617,#020617);
            padding:10px 12px 14px;
            border-radius:16px;
            border:1px solid var(--border-subtle);
            margin-top:8px;
        }

        .checkbox-otro-deudor{
            transform:scale(1.2);
            cursor:pointer;
        }

        @media (max-width: 900px){
            .container{padding:16px;}
            .page-header{
                flex-direction:column;
                align-items:flex-start;
            }
        }

        small{
            font-size:0.77rem;
            color:var(--text-soft);
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
                        
                        <!-- Configuración especial para Celene -->
                        <div id="configCelene" class="config-celene" style="display: <?php echo $prestamista_seleccionado == 'Celene' ? 'block' : 'none'; ?>;">
                            <h4>Configuración para Celene</h4>
                            <div class="form-row">
                                <div class="form-col">
                                    <label for="interes_celene">Interés para Celene (%):</label>
                                    <input type="number" name="interes_celene" id="interes_celene" 
                                           value="<?php echo $interes_celene; ?>" step="0.1" min="0" max="100" required>
                                    <small>Lo que recibe Celene.</small>
                                </div>
                                <div class="form-col">
                                    <label for="comision_celene">Tu Comisión (%):</label>
                                    <input type="number" name="comision_celene" id="comision_celene" 
                                           value="<?php echo $comision_celene; ?>" step="0.1" min="0" max="100" required>
                                    <small>Lo que recibes tú (no se suma al total a pagar).</small>
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
            
            <?php if (!empty($empresa_seleccionada) || (!empty($fecha_desde) && !empty($fecha_hasta))): ?>
            <div class="info-meses">
                <strong>Filtro de conductores aplicado:</strong>
                <?php if (!empty($empresa_seleccionada)): ?>
                    Empresa <strong><?php echo htmlspecialchars($empresa_seleccionada); ?></strong> |
                <?php endif; ?>
                Fechas: <strong><?php echo htmlspecialchars($fecha_desde); ?></strong> al <strong><?php echo htmlspecialchars($fecha_hasta); ?></strong>
            </div>
            <?php endif; ?>
            
            <?php if ($prestamista_seleccionado == 'Celene'): ?>
            <div class="info-meses">
                <strong>Distribución para Celene:</strong><br>
                • Celene recibe: <strong>Capital + <?php echo $interes_celene; ?>% de interés</strong><br>
                • Tú recibes: <strong><?php echo $comision_celene; ?>% de comisión</strong> (se muestra aparte)<br>
                • En la columna “Total a pagar” solo se suma: <strong>Capital + Interés Celene</strong>.
            </div>
            <?php else: ?>
            <div class="info-meses">
                <strong>Cálculo de interés y meses:</strong><br>
                • Los meses se calculan automáticamente según la fecha del préstamo y la fecha actual.<br>
                • Préstamos <strong>anteriores al 29-10-2025</strong>: interés del <strong>10%</strong> mensual.<br>
                • Préstamos <strong>desde el 29-10-2025 en adelante</strong>: interés del <strong>13%</strong> mensual.
            </div>
            <?php endif; ?>

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
                        <?php if ($prestamista_seleccionado == 'Celene'): ?>
                        <th>Interés Celene (<?php echo $interes_celene; ?>%)</th>
                        <th>Tu Comisión (<?php echo $comision_celene; ?>%)</th>
                        <?php else: ?>
                        <th>Interés (10% / 13%)</th>
                        <?php endif; ?>
                        <th>Total a pagar</th>
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
                            <table style="width: 100%; background-color: #020617;">
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
                                            <input type="number" class="interes-input" value="<?php echo $detalle['tasa_interes']; ?>" 
                                                   step="0.1" min="0" max="100" 
                                                   onchange="recalcularPrestamo(this)" 
                                                   data-monto="<?php echo $detalle['monto']; ?>">
                                        </td>
                                        <td class="moneda interes-prestamo">
                                            $ <?php echo number_format($detalle['total'] - $detalle['monto'], 0, ',', '.'); ?>
                                        </td>
                                        <?php endif; ?>
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
                            <?php if ($prestamista_seleccionado == 'Celene'): ?>
                            <th>Interés Celene</th>
                            <th>Tu Comisión</th>
                            <?php else: ?>
                            <th>Interés</th>
                            <?php endif; ?>
                            <th>Total a pagar</th>
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
                            <td class="acciones">
                                <input type="checkbox"
                                       class="checkbox-otro-deudor"
                                       data-deudor="<?php echo htmlspecialchars($deudor); ?>"
                                       onchange="toggleOtroDeudor(this)">
                            </td>
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
                            <td colspan="3"><strong>TOTAL GENERAL OTROS DEUDORES</strong></td>
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
                const total = monto + interesCeleneMonto;
                
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
