<?php

require 'vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// EVITAR cualquier salida previa
if (ob_get_level()) { ob_end_clean(); }
header_remove(); // limpia headers previos por si acaso

// Conexión BD
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    http_response_code(500);
    die("Error conexión BD");
}
$conn->set_charset('utf8mb4');

// Si no se han enviado fechas, mostramos formulario sencillo
if (empty($_POST['desde']) || empty($_POST['hasta'])) {
    $empresas = [];
    $resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
    if ($resEmp) while ($r = $resEmp->fetch_assoc()) { $empresas[] = $r['empresa']; }
    ?>
    <!doctype html><html lang="es"><head>
    <meta charset="utf-8"><title>Generar Informe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    </head><body class="bg-light p-4">
    <div class="container">
      <h3 class="mb-3">📅 Generar Informe de Viajes</h3>
      <form method="post" class="card p-4 shadow-sm">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Desde</label>
            <input type="date" name="desde" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Hasta</label>
            <input type="date" name="hasta" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Empresa</label>
            <select name="empresa" class="form-select">
              <option value="">-- Todas --</option>
              <?php foreach($empresas as $e): ?>
                <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mt-3">
          <button class="btn btn-primary">Generar Informe</button>
          <a class="btn btn-secondary" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/index2.php">Ir a Inicio</a>
        </div>
      </form>
    </div>
    </body></html>
    <?php
    exit;
}

// Parámetros
$desde = $_POST['desde'];
$hasta = $_POST['hasta'];
$empresaFiltro = $_POST['empresa'] ?? "";

// Normaliza a todo el día por si `fecha` es DATETIME
$desdeIni = $conn->real_escape_string($desde . " 00:00:00");
$hastaFin = $conn->real_escape_string($hasta . " 23:59:59");

// CONSULTA PARA LISTA DE CONDUCTORES - Únicos con sus datos (RESPETANDO FILTROS)
$sqlConductores = "SELECT DISTINCT nombre, cedula, tipo_vehiculo 
                   FROM viajes 
                   WHERE fecha >= '$desdeIni' AND fecha <= '$hastaFin' 
                   AND nombre IS NOT NULL AND nombre <> ''";
if ($empresaFiltro !== "") {
    $empresaFiltro = $conn->real_escape_string($empresaFiltro);
    $sqlConductores .= " AND empresa = '$empresaFiltro'";
}
$sqlConductores .= " ORDER BY nombre ASC";
$resConductores = $conn->query($sqlConductores);

// CONSULTA PRINCIPAL - Todos los viajes (RESPETANDO FILTROS)
$sql = "SELECT fecha, nombre, ruta, empresa 
        FROM viajes 
        WHERE fecha >= '$desdeIni' AND fecha <= '$hastaFin'";
if ($empresaFiltro !== "") {
    $sql .= " AND empresa = '$empresaFiltro'";
}
$sql .= " ORDER BY fecha ASC, id ASC";
$res = $conn->query($sql);

// Documento
$phpWord = new PhpWord();
$section = $phpWord->addSection();

// Encabezado
$section->addText("INFORME DE FICHAS TÉCNICAS DE CONDUCTOR - VEHÍCULOS", ['bold' => true, 'size' => 14], ['align' => 'center']);
$section->addTextBreak(1);
$section->addText("SEGÚN ACTA DE INICIO AL CONTRATO DE PRESTACIÓN DE SERVICIOS NO. 1313-2025 SUSCRITO POR LA E.S.E. HOSPITAL SAN JOSÉ DE MAICAO Y LA ASOCIACIÓN DE TRANSPORTISTAS ZONA NORTE EXTREMA WUINPUMUIN.");
$section->addText("OBJETO: TRASLADO DE PERSONAL ASISTENCIAL – SEDE NAZARETH.");
$section->addTextBreak(1);
$section->addText("Periodo: desde $desde hasta $hasta", ['italic' => true]);
if (!empty($empresaFiltro)) {
    $section->addText("Empresa: $empresaFiltro", ['italic' => true]);
}
$section->addTextBreak(2);

// ========== TABLA 1: LISTA DE CONDUCTORES CON CÉDULA Y TIPO DE VEHÍCULO ==========
$section->addText("LISTA DE CONDUCTORES", ['bold' => true, 'size' => 12]);
$section->addTextBreak(1);

$tableConductores = $section->addTable([
    'borderSize' => 6, 
    'borderColor' => '000000', 
    'cellMargin' => 80,
    'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER
]);

// Encabezado tabla conductores
$tableConductores->addRow();
$tableConductores->addCell(4000)->addText("CONDUCTOR", ['bold' => true]);
$tableConductores->addCell(3000)->addText("CÉDULA", ['bold' => true]);
$tableConductores->addCell(3000)->addText("TIPO DE VEHÍCULO", ['bold' => true]);

if ($resConductores && $resConductores->num_rows > 0) {
    while ($row = $resConductores->fetch_assoc()) {
        $tableConductores->addRow();
        $tableConductores->addCell(4000)->addText($row['nombre'] ?: '-');
        $tableConductores->addCell(3000)->addText($row['cedula'] ?: 'N/A');
        $tableConductores->addCell(3000)->addText($row['tipo_vehiculo'] ?: '-');
    }
} else {
    $tableConductores->addRow();
    $cell = $tableConductores->addCell(10000, ['gridSpan' => 3]);
    $cell->addText("📭 No hay conductores en este rango de fechas.");
}

$section->addTextBreak(3);

// ========== TABLA 2: DETALLE DE VIAJES (TABLA ORIGINAL) ==========
$section->addText("DETALLE DE VIAJES POR FECHA", ['bold' => true, 'size' => 12]);
$section->addTextBreak(1);

$tableViajes = $section->addTable([
    'borderSize' => 6, 
    'borderColor' => '000000', 
    'cellMargin' => 80,
    'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER
]);

// Encabezado tabla viajes (ORIGINAL - solo 3 columnas)
$tableViajes->addRow();
$tableViajes->addCell(2000)->addText("FECHA", ['bold' => true]);
$tableViajes->addCell(4000)->addText("CONDUCTOR", ['bold' => true]);
$tableViajes->addCell(4000)->addText("RUTA", ['bold' => true]);

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $tableViajes->addRow();
        $tableViajes->addCell(2000)->addText(substr($row['fecha'], 0, 10));
        $tableViajes->addCell(4000)->addText($row['nombre'] ?: '-');
        $tableViajes->addCell(4000)->addText($row['ruta'] ?: '-');
    }
} else {
    $tableViajes->addRow();
    $cell = $tableViajes->addCell(10000, ['gridSpan' => 3]);
    $cell->addText("📭 No hay viajes en este rango de fechas.");
}

// Pie
$section->addTextBreak(2);
date_default_timezone_set('America/Bogota');
$section->addText("Maicao, " . date('d/m/Y'), [], ['align' => 'right']);
$section->addText("Cordialmente,");
$section->addTextBreak(2);
$section->addText("NUMAS JOSÉ IGUARÁN IGUARÁN", ['bold' => true]);
$section->addText("Representante Legal");

// Envío directo al navegador
$filename = "informe_viajes_{$desde}_a_{$hasta}.docx";
header("Content-Description: File Transfer");
header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: public");

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save('php://output');
exit;