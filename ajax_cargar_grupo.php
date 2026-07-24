<?php
/*********************************************************
 * ajax_cargar_grupo.php - Carga los préstamos de un grupo
 * para mostrarlos en el selector de grupos
 *********************************************************/
header('Content-Type: application/json');

include("nav.php");

// ======= CONFIG =======
define('DB_HOST', 'mysql.hostinger.com');
define('DB_USER', 'u648222299_keboco5');
define('DB_PASS', 'Bucaramanga3011');
define('DB_NAME', 'u648222299_viajes');

function db(): mysqli {
  $m = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  if ($m->connect_errno) exit(json_encode(['error' => 'Error DB: '.$m->connect_error]));
  $m->set_charset('utf8mb4');
  return $m;
}

function calcularMesesPrestamo($fecha_inicio, $fecha_fin = null) {
    $inicio = new DateTime($fecha_inicio);
    
    if ($fecha_fin) {
        $fin = new DateTime($fecha_fin);
    } else {
        $fin = new DateTime('now');
    }
    
    $diff = $inicio->diff($fin);
    $meses = ($diff->y * 12) + $diff->m + 1;
    
    return $meses;
}

$grupo = $_POST['grupo'] ?? '';

if (empty($grupo)) {
    echo json_encode(['error' => 'No se seleccionó ningún grupo']);
    exit;
}

$conn = db();

// Decodificar el grupo
$tipo = '';
$fecha = null;

if (strpos($grupo, 'pagado_fecha_') === 0) {
    $tipo = 'pagado_fecha';
    $fecha = str_replace('pagado_fecha_', '', $grupo);
} elseif ($grupo == 'pagado_sin_fecha') {
    $tipo = 'pagado_sin_fecha';
} elseif ($grupo == 'no_pagados') {
    $tipo = 'no_pagados';
} else {
    echo json_encode(['error' => 'Grupo no válido']);
    exit;
}

// Construir consulta
if ($tipo == 'pagado_fecha') {
    $sql = "SELECT * FROM prestamos WHERE pagado = 1 AND DATE(pagado_at) = ? ORDER BY deudor";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $fecha);
    $stmt->execute();
    $result = $stmt->get_result();
    $titulo = "📅 PAGADOS - " . date('d/m/Y', strtotime($fecha));
} elseif ($tipo == 'pagado_sin_fecha') {
    $sql = "SELECT * FROM prestamos WHERE pagado = 1 AND pagado_at IS NULL ORDER BY deudor";
    $result = $conn->query($sql);
    $titulo = "📌 PAGADOS SIN FECHA";
} else { // no_pagados
    $sql = "SELECT * FROM prestamos WHERE pagado = 0 ORDER BY deudor";
    $result = $conn->query($sql);
    $titulo = "⏳ NO PAGADOS";
}

$prestamos = [];
$total_capital = 0;
$total_interes = 0;
$total_general = 0;

while ($row = $result->fetch_assoc()) {
    // ===== CALCULAR MESES =====
    if (!empty($row['pagado_at'])) {
        $meses = calcularMesesPrestamo($row['fecha'], $row['pagado_at']);
    } else {
        $meses = calcularMesesPrestamo($row['fecha'], null);
    }
    
    // ===== CALCULAR INTERÉS =====
    $tasa = (strtotime($row['fecha']) >= strtotime('2025-10-29')) ? 13 : 10;
    $interes = ($row['monto'] * $tasa / 100) * $meses;
    $total = $row['monto'] + $interes;
    
    $row['meses'] = $meses;
    $row['interes'] = $interes;
    $row['total'] = $total;
    $row['tasa'] = $tasa;
    $row['monto_formateado'] = number_format($row['monto'], 0, ',', '.');
    $row['interes_formateado'] = number_format($interes, 0, ',', '.');
    $row['total_formateado'] = number_format($total, 0, ',', '.');
    
    $prestamos[] = $row;
    $total_capital += $row['monto'];
    $total_interes += $interes;
    $total_general += $total;
}

$conn->close();

echo json_encode([
    'titulo' => $titulo,
    'total' => count($prestamos),
    'prestamos' => $prestamos,
    'resumen' => [
        'capital' => number_format($total_capital, 0, ',', '.'),
        'interes' => number_format($total_interes, 0, ',', '.'),
        'total' => number_format($total_general, 0, ',', '.')
    ]
]);
?>