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

// Función para obtener el tipo de vehículo formateado
function obtenerTipoVehiculo($tipo) {
    if (stripos($tipo, 'burbuja') !== false) {
        return 'Camioneta Burbuja 4x4 Doble Cabina';
    }
    // Para otros tipos, mantener el nombre original
    return $tipo ?: '-';
}

// Función para obtener el área de cobertura según tipo de vehículo
function obtenerAreaCobertura($tipo) {
    $tipo = strtolower(trim($tipo ?: ''));
    
    if (strpos($tipo, 'burbuja') !== false) {
        return 'Maicao - Nazareth - Maicao';
    } elseif (strpos($tipo, 'camión 350') !== false || strpos($tipo, 'camion 350') !== false) {
        return 'Maicao - Nazareth - Maicao';
    } elseif (strpos($tipo, 'carrotanque') !== false) {
        return 'Nazareth';
    } elseif (strpos($tipo, 'camión 750') !== false || strpos($tipo, 'camion 750') !== false) {
        return 'Maicao - Nazareth - Maicao';
    } elseif (strpos($tipo, 'copetrana') !== false) {
        return 'Maicao - Nazareth - Maicao';
    }
    
    // Para tipos desconocidos, poner un valor por defecto
    return 'Maicao - Nazareth - Maicao';
}

// Si no se han enviado fechas, mostramos formulario sencillo
if (empty($_POST['desde']) || empty($_POST['hasta'])) {
    $empresas = [];
    $resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
    if ($resEmp) while ($r = $resEmp->fetch_assoc()) { $empresas[] = $r['empresa']; }
    ?>
    <!doctype html><html lang="es"><head>
    <meta charset="utf-8"><title>Generar Informe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .empresas-container {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 0.75rem;
            background-color: #f8f9fa;
        }
        .empresa-checkbox {
            margin-bottom: 0.5rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            transition: background-color 0.2s;
        }
        .empresa-checkbox:hover {
            background-color: #e9ecef;
        }
        .empresa-checkbox label {
            margin-left: 0.5rem;
            cursor: pointer;
            flex: 1;
        }
        .select-all-container {
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #dee2e6;
            font-weight: bold;
        }
    </style>
    </head><body class="bg-light p-4">
    <div class="container">
      <h3 class="mb-3">📅 Generar Informe de Viajes</h3>
      <form method="post" class="card p-4 shadow-sm">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Desde</label>
            <input type="date" name="desde" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Hasta</label>
            <input type="date" name="hasta" class="form-control" required>
          </div>
          <div class="col-12">
            <label class="form-label fw-bold">Seleccionar Empresas:</label>
            <div class="empresas-container">
                <div class="select-all-container">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="seleccionarTodos">
                        <label class="form-check-label" for="seleccionarTodos">
                            Seleccionar todas las empresas
                        </label>
                    </div>
                </div>
                <div class="empresas-list">
                    <?php if (empty($empresas)): ?>
                        <p class="text-muted mb-0">No hay empresas disponibles</p>
                    <?php else: ?>
                        <?php foreach($empresas as $index => $e): ?>
                        <div class="empresa-checkbox form-check">
                            <input class="form-check-input empresa-item" type="checkbox" 
                                   name="empresas[]" value="<?= htmlspecialchars($e) ?>" 
                                   id="empresa_<?= $index ?>">
                            <label class="form-check-label" for="empresa_<?= $index ?>">
                                <?= htmlspecialchars($e) ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <small class="text-muted">Seleccione una o más empresas. Si no selecciona ninguna, se incluirán todas.</small>
          </div>
        </div>
        <div class="mt-3">
          <button class="btn btn-primary">Generar Informe</button>
          <a class="btn btn-secondary" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/index2.php">Ir a Inicio</a>
        </div>
      </form>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const seleccionarTodos = document.getElementById('seleccionarTodos');
            const checkboxesEmpresa = document.querySelectorAll('.empresa-item');
            
            // Función para actualizar el estado del checkbox "Seleccionar todos"
            function actualizarSeleccionarTodos() {
                const totalCheckboxes = checkboxesEmpresa.length;
                const checkboxesSeleccionados = document.querySelectorAll('.empresa-item:checked').length;
                
                if (checkboxesSeleccionados === 0) {
                    seleccionarTodos.checked = false;
                    seleccionarTodos.indeterminate = false;
                } else if (checkboxesSeleccionados === totalCheckboxes) {
                    seleccionarTodos.checked = true;
                    seleccionarTodos.indeterminate = false;
                } else {
                    seleccionarTodos.indeterminate = true;
                }
            }
            
            // Evento para "Seleccionar todos"
            seleccionarTodos.addEventListener('change', function() {
                checkboxesEmpresa.forEach(checkbox => {
                    checkbox.checked = seleccionarTodos.checked;
                });
            });
            
            // Evento para cada checkbox individual
            checkboxesEmpresa.forEach(checkbox => {
                checkbox.addEventListener('change', actualizarSeleccionarTodos);
            });
            
            // Inicializar estado
            actualizarSeleccionarTodos();
        });
    </script>
    </body></html>
    <?php
    exit;
}

// Parámetros
$desde = $_POST['desde'];
$hasta = $_POST['hasta'];
$empresasSeleccionadas = $_POST['empresas'] ?? [];

// Normaliza a todo el día por si `fecha` es DATETIME
$desdeIni = $conn->real_escape_string($desde . " 00:00:00");
$hastaFin = $conn->real_escape_string($hasta . " 23:59:59");

// Construir condición para múltiples empresas
$condicionEmpresa = "";
if (!empty($empresasSeleccionadas)) {
    // Escapar cada valor para evitar inyección SQL
    $empresasEscapadas = array_map(function($emp) use ($conn) {
        return "'" . $conn->real_escape_string($emp) . "'";
    }, $empresasSeleccionadas);
    
    $condicionEmpresa = " AND empresa IN (" . implode(",", $empresasEscapadas) . ")";
}

// CONSULTA PARA LISTA DE CONDUCTORES - Únicos con sus datos (RESPETANDO FILTROS)
$sqlConductores = "SELECT DISTINCT nombre, cedula, tipo_vehiculo 
                   FROM viajes 
                   WHERE fecha >= '$desdeIni' AND fecha <= '$hastaFin' 
                   AND nombre IS NOT NULL AND nombre <> ''";
$sqlConductores .= $condicionEmpresa;
$sqlConductores .= " ORDER BY nombre ASC";
$resConductores = $conn->query($sqlConductores);

// CONSULTA PRINCIPAL - Todos los viajes (RESPETANDO FILTROS)
$sql = "SELECT fecha, nombre, ruta, empresa 
        FROM viajes 
        WHERE fecha >= '$desdeIni' AND fecha <= '$hastaFin'";
$sql .= $condicionEmpresa;
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
if (!empty($empresasSeleccionadas)) {
    $section->addText("Empresas seleccionadas: " . implode(", ", $empresasSeleccionadas), ['italic' => true]);
} else {
    $section->addText("Empresas: TODAS", ['italic' => true]);
}
$section->addTextBreak(2);

// ========== TABLA 1: LISTA DE CONDUCTORES CON CÉDULA, TIPO DE VEHÍCULO Y ÁREA DE COBERTURA ==========
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
$tableConductores->addCell(3000)->addText("CONDUCTOR", ['bold' => true]);
$tableConductores->addCell(2500)->addText("CÉDULA", ['bold' => true]);
$tableConductores->addCell(2500)->addText("TIPO DE VEHÍCULO", ['bold' => true]);
$tableConductores->addCell(2000)->addText("ÁREA DE COBERTURA", ['bold' => true]);

if ($resConductores && $resConductores->num_rows > 0) {
    while ($row = $resConductores->fetch_assoc()) {
        $tableConductores->addRow();
        $tableConductores->addCell(3000)->addText($row['nombre'] ?: '-');
        $tableConductores->addCell(2500)->addText($row['cedula'] ?: 'N/A');
        
        // Tipo de vehículo formateado
        $tipoVehiculo = obtenerTipoVehiculo($row['tipo_vehiculo']);
        $tableConductores->addCell(2500)->addText($tipoVehiculo);
        
        // Área de cobertura según tipo de vehículo
        $areaCobertura = obtenerAreaCobertura($row['tipo_vehiculo']);
        $tableConductores->addCell(2000)->addText($areaCobertura);
    }
} else {
    $tableConductores->addRow();
    $cell = $tableConductores->addCell(10000, ['gridSpan' => 4]);
    $cell->addText("📭 No hay conductores en este rango de fechas.");
}

$section->addTextBreak(3);

// ========== TABLA 2: DETALLE DE VIAJES ==========
$section->addText("DETALLE DE VIAJES POR FECHA", ['bold' => true, 'size' => 12]);
$section->addTextBreak(1);

$tableViajes = $section->addTable([
    'borderSize' => 6, 
    'borderColor' => '000000', 
    'cellMargin' => 80,
    'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER
]);

// Encabezado tabla viajes
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
?>