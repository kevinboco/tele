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

// Si no se han enviado fechas, mostramos formulario sencillo (SIN nav.php)
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

// Consulta
$sql = "SELECT fecha, nombre, ruta, empresa 
        FROM viajes 
        WHERE fecha >= '$desdeIni' AND fecha <= '$hastaFin'";
if ($empresaFiltro !== "") {
    $empresaFiltro = $conn->real_escape_string($empresaFiltro);
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
$section->addTextBreak(1);

// Tabla
$table = $section->addTable(['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 80]);
$table->addRow();
$table->addCell(2000)->addText("FECHA", ['bold' => true]);
$table->addCell(4000)->addText("CONDUCTOR", ['bold' => true]);
$table->addCell(4000)->addText("RUTA", ['bold' => true]);

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $table->addRow();
        $table->addCell(2000)->addText(substr($row['fecha'], 0, 10));
        $table->addCell(4000)->addText($row['nombre'] ?: '-');
        $table->addCell(4000)->addText($row['ruta'] ?: '-');
    }
} else {
    $table->addRow();
    // celda que ocupa 3 columnas
    $table->addCell(10000, ['gridSpan' => 3])->addText("📭 No hay viajes en este rango de fechas.");
}

// Pie
$section->addTextBreak(2);
date_default_timezone_set('America/Bogota');
$section->addText("Maicao, " . date('d/m/Y'), [], ['align' => 'right']);
$section->addText("Cordialmente,");
$section->addTextBreak(2);
$section->addText("NUMAS JOSÉ IGUARÁN IGUARÁN", ['bold' => true]);
$section->addText("Representante Legal");

// Envío directo al navegador (sin crear archivo temporal)
$filename = "informe_viajes_{$desde}_a_{$hasta}.docx";
header("Content-Description: File Transfer");
header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: public");

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save('php://output');
exit;
