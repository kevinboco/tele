<?php
require 'vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// Conexión BD
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}

// Si no se han enviado fechas, mostramos formulario
if (!isset($_POST['desde']) || !isset($_POST['hasta'])) {
    // Consultar empresas distintas
    $empresas = [];
    $resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
    while ($r = $resEmp->fetch_assoc()) {
        $empresas[] = $r['empresa'];
    }
    ?>
    <form method="post">
        <h2>📅 Generar Informe de Viajes</h2>
        <label>Desde: <input type="date" name="desde" required></label><br><br>
        <label>Hasta: <input type="date" name="hasta" required></label><br><br>

        <label>Empresa: 
            <select name="empresa">
                <option value="">-- Todas --</option>
                <?php foreach($empresas as $e): ?>
                    <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <br><br>

        <button type="submit">Generar Informe</button>
    </form>
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

// === Tabla de viajes ===
$table = $section->addTable([
    'borderSize' => 6,
    'borderColor' => '000000',
    'cellMargin' => 50
]);

// Encabezados
$table->addRow();
$table->addCell(2000)->addText("FECHA DEL VIAJE", ['bold' => true]);
$table->addCell(4000)->addText("CONDUCTOR", ['bold' => true]);
$table->addCell(4000)->addText("RUTA", ['bold' => true]);
$table->addCell(3000)->addText("EMPRESA", ['bold' => true]);

// Filas con los datos
if ($res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $table->addRow();
        $table->addCell(2000)->addText($row['fecha']);
        $table->addCell(4000)->addText($row['nombre']);
        $table->addCell(4000)->addText($row['ruta']);
        $table->addCell(3000)->addText($row['empresa']);
    }
} else {
    $table->addRow();
    $table->addCell(13000, ['gridSpan' => 4])->addText("📭 No hay viajes en este rango de fechas.");
}

// === Pie de página ===
$section->addTextBreak(2);
setlocale(LC_TIME, "es_ES.UTF-8");
$fechaHoy = strftime("%d de %B de %Y");
$section->addText("Maicao, " . $fechaHoy, [], ['align' => 'right']);
$section->addText("Cordialmente,", [], ['align' => 'left']);
$section->addTextBreak(2);
$section->addText("NUMA IGUARAN IGUARAN", ['bold' => true]);
$section->addText("Representante Legal");

// Guardar temporalmente
$file = "informe_viajes.docx";
$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save($file);

// Forzar descarga al navegador
header("Content-Description: File Transfer");
header("Content-Disposition: attachment; filename=$file");
header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
header("Content-Transfer-Encoding: binary");
header("Cache-Control: must-revalidate");
header("Pragma: public");
readfile($file);
exit;
?>
