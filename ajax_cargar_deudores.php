<?php
// ajax_cargar_deudores.php
include("nav.php");

function db(): mysqli {
  $m = @new mysqli('mysql.hostinger.com', 'u648222299_keboco5', 'Bucaramanga3011', 'u648222299_viajes');
  if ($m->connect_errno) exit("Error DB: ".$m->connect_error);
  $m->set_charset('utf8mb4');
  return $m;
}

function mbnorm($s){ return mb_strtolower(trim((string)$s),'UTF-8'); }
function mbtitle($s){ return function_exists('mb_convert_case') ? mb_convert_case((string)$s, MB_CASE_TITLE, 'UTF-8') : ucwords(strtolower((string)$s)); }

header('Content-Type: application/json');

$empresa = $_GET['empresa'] ?? '';
$estado = $_GET['estado'] ?? 'no_pagados';

// Configurar filtro de estado
$whereBase = "1=1";
if ($estado === 'no_pagados') {
    $whereBase = "pagado = 0";
} elseif ($estado === 'pagados') {
    $whereBase = "pagado = 1";
}

$conn = db();

if ($empresa !== '') {
    // Filtrar por empresa específica
    $sql = "SELECT DISTINCT deudor FROM prestamos WHERE LOWER(TRIM(empresa)) = ? AND $whereBase ORDER BY deudor";
    $st = $conn->prepare($sql);
    $st->bind_param("s", $empresa);
    $st->execute();
    $result = $st->get_result();
} else {
    // Todos los deudores
    $sql = "SELECT DISTINCT deudor FROM prestamos WHERE $whereBase ORDER BY deudor";
    $result = $conn->query($sql);
}

$deudores = [];

while ($row = $result->fetch_assoc()) {
    $deudor = $row['deudor'];
    if (trim($deudor) === '') continue;
    
    $norm = mbnorm($deudor);
    $nombre = mbtitle($deudor);
    
    $deudores[] = [
        'norm' => $norm,
        'nombre' => $nombre
    ];
}

echo json_encode($deudores);

if (isset($st)) $st->close();
$conn->close();
?>