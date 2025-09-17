<?php
require 'vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// Conexión BD
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}

// Consultar viajes
$res = $conn->query("SELECT fecha, nombre, ruta FROM viajes ORDER BY fecha ASC");

$phpWord = new PhpWord();
$section = $phpWord->addSection();

// === Encabezado ===
$section->addText("INFORME DE FICHAS TECNICAS DE CONDUCTOR VEHICULOS", ['bold' => true, 'size' => 14], ['align' => 'center']);
$section->addTextBreak(1);

$section->addText("SEGÚN ACTA DE INICIO AL CONTRATO DE PRESTACIÓN DE SERVICIOS NO. 1313-2025 SUSCRITO LA ESE HOSPITAL SAN JOSE DE MAICAO CON LA ASOCIACION DE TRANSPORTISTA ZONA NORTE EXTREMA WUINPUMUIN.");
$section->addText("OBJETO: TRASLADO DE PERSONAL ASISTENCIAL DE LA ESE HOSPITAL SAN JOSE DE MAICAO EN INTERVENCION – SEDE NAZARETH.");
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

// Filas con los datos
while ($row = $res->fetch_assoc()) {
    $table->addRow();
    $table->addCell(2000)->addText($row['fecha']);
    $table->addCell(4000)->addText($row['nombre']);
    $table->addCell(4000)->addText($row['ruta']);
}

// === Pie de página ===
$section->addTextBreak(2);
$section->addText("Maicao, " . date("d \d\e F \d\e Y"), [], ['align' => 'right']);
$section->addText("Cordialmente,", [], ['align' => 'left']);
$section->addTextBreak(2);
$section->addText("NUMA IGUARAN IGUARAN", ['bold' => true]);
$section->addText("Representante Legal");

// Guardar Word
$file = "informe_viajes.docx";
$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save($file);

echo "✅ Informe generado: <a href='$file'>Descargar aquí</a>";
?>
