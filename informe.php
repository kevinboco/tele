<?php
require 'vendor/autoload.php';
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// Desactivar cualquier salida previa
ob_clean();
ob_start();

// Conexión BD
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}

// Si no se han enviado fechas, mostramos formulario
if (!isset($_POST['desde']) || !isset($_POST['hasta'])) {
    $empresas = [];
    $resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
    while ($r = $resEmp->fetch_assoc()) {
        $empresas[] = $r['empresa'];
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Generar Informe</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light p-4">
        <div class="container">
            <h2 class="mb-4 text-primary">📅 Generar Informe de Viajes</h2>
            <form method="post" class="card p-4 shadow">
                <div class="mb-3">
                    <label class="form-label">Desde:</label>
                    <input type="date" name="desde" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Hasta:</label>
                    <input type="date" name="hasta" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Empresa:</label>
                    <select name="empresa" class="form-select">
                        <option value="">-- Todas --</option>
                        <?php foreach($empresas as $e): ?>
                            <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Generar Informe</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Fechas recibidas
$desde = $_POST['desde'];
$hasta = $_POST['hasta'];
$empresaFiltro = $_POST['empresa'] ?? "";

// Construir SQL con filtro opcional
$sql = "SELECT fecha, nombre, ruta, empresa FROM viajes 
        WHERE fecha BETWEEN '$desde' AND '$hasta'";
if ($empresaFiltro !== "") {
    $empresaFiltro = $conn->real_escape_string($empresaFiltro);
    $sql .= " AND empresa = '$empresaFiltro'";
}
$sql .= " ORDER BY fecha ASC, id ASC";

$res = $conn->query($sql);

// Crear documento Word
$phpWord = new PhpWord();
$section = $phpWord->addSection();

// === Encabezado ===
$section->addText("INFORME DE FICHAS TECNICAS DE CONDUCTOR VEHICULOS", ['bold' => true, 'size' => 14], ['align' => 'center']);
$section->addTextBreak(1);
$section->addText("SEGÚN ACTA DE INICIO AL CONTRATO DE PRESTACIÓN DE SERVICIOS NO. 1313-2025 SUSCRITO LA ESE HOSPITAL SAN JOSE DE MAICAO CON LA ASOCIACION DE TRANSPORTISTA ZONA NORTE EXTREMA WUINPUMUIN.");
$section->addText("OBJETO: TRASLADO DE PERSONAL ASISTENCIAL DE LA ESE HOSPITAL SAN JOSE DE MAICAO EN INTERVENCION – SEDE NAZARETH.");
$section->addTextBreak(1);
$section->addText("Periodo: desde $desde hasta $hasta", ['italic' => true]);
if ($empresaFiltro !== "") {
    $section->addText("Empresa: $empresaFiltro", ['italic' => true]);
}
$section->addTextBreak(1);

// === Tabla ===
$table = $section->addTable(['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 50]);
$table->addRow();
$table->addCell(2000)->addText("FECHA", ['bold' => true]);
$table->addCell(4000)->addText("CONDUCTOR", ['bold' => true]);
$table->addCell(4000)->addText("RUTA", ['bold' => true]);

if ($res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $table->addRow();
        $table->addCell(2000)->addText($row['fecha']);
        $table->addCell(4000)->addText($row['nombre']);
        $table->addCell(4000)->addText($row['ruta']);
    }
} else {
    $table->addRow();
    $table->addCell(10000, ['gridSpan' => 3])->addText("📭 No hay viajes en este rango de fechas.");
}

// === Pie ===
$section->addTextBreak(2);
setlocale(LC_TIME, "es_ES.UTF-8");
$fechaHoy = strftime("%d de %B de %Y");
$section->addText("Maicao, " . $fechaHoy, [], ['align' => 'right']);
$section->addText("Cordialmente,");
$section->addTextBreak(2);
$section->addText("NUMAS JOSÉ IGUARÁN IGUARÁN", ['bold' => true]);
$section->addText("Representante Legal");

// === Guardar y forzar descarga ===
$file = tempnam(sys_get_temp_dir(), 'informe_') . '.docx';
$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save($file);

header("Content-Description: File Transfer");
header("Content-Disposition: attachment; filename=informe_viajes.docx");
header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
header("Content-Length: " . filesize($file));
readfile($file);

// Limpiar buffers
ob_end_clean();
exit;
?>
