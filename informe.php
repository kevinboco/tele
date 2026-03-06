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

// Función para formatear valores monetarios
function formatearMoneda($valor) {
    if ($valor === null || $valor === '') return 'N/A';
    return '$ ' . number_format(floatval($valor), 0, ',', '.');
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
    
    $condicionEmpresa = " AND v.empresa IN (" . implode(",", $empresasEscapadas) . ")";
}

// ========== CONSULTA CORREGIDA PARA LISTA DE CONDUCTORES ==========
$sqlConductores = "
    SELECT 
        v_periodo.nombre,
        (
            SELECT v2.cedula 
            FROM viajes v2 
            WHERE v2.nombre = v_periodo.nombre 
              AND v2.cedula IS NOT NULL 
              AND v2.cedula != ''
            ORDER BY v2.fecha DESC 
            LIMIT 1
        ) as cedula,
        (
            SELECT v3.tipo_vehiculo 
            FROM viajes v3 
            WHERE v3.nombre = v_periodo.nombre 
              AND v3.tipo_vehiculo IS NOT NULL
            ORDER BY v3.fecha DESC 
            LIMIT 1
        ) as tipo_vehiculo
    FROM (
        SELECT DISTINCT nombre 
        FROM viajes 
        WHERE fecha >= '$desdeIni' 
          AND fecha <= '$hastaFin'
          AND nombre IS NOT NULL 
          AND nombre <> ''
    ) v_periodo
    ORDER BY v_periodo.nombre ASC
";
$resConductores = $conn->query($sqlConductores);

// ========== CONSULTA PRINCIPAL - Todos los viajes con VALOR ==========
$sqlViajes = "
    SELECT 
        v.fecha,
        v.nombre,
        v.ruta,
        v.tipo_vehiculo,
        v.empresa,
        -- Obtener la clasificación para esta ruta y tipo de vehículo
        rc.clasificacion,
        -- Obtener el valor según la clasificación
        CASE 
            WHEN rc.clasificacion = 'completo' THEN t.completo
            WHEN rc.clasificacion = 'medio' THEN t.medio
            WHEN rc.clasificacion = 'extra' THEN t.extra
            WHEN rc.clasificacion = 'carrotanque' THEN t.carrotanque
            WHEN rc.clasificacion = 'siapana' THEN t.siapana
            WHEN rc.clasificacion = 'prueba' THEN t.prueba
            WHEN rc.clasificacion = 'riohacha_completo' THEN t.riohacha_completo
            WHEN rc.clasificacion = 'riohacha_medio' THEN t.riohacha_medio
            WHEN rc.clasificacion = 'nazareth_siapana_maicao' THEN t.nazareth_siapana_maicao
            WHEN rc.clasificacion = 'nazareth_siapana_flor_de_la_guajira' THEN t.nazareth_siapana_flor_de_la_guajira
            ELSE NULL
        END as valor_viaje
    FROM viajes v
    LEFT JOIN ruta_clasificacion rc 
        ON LOWER(TRIM(v.ruta)) = LOWER(TRIM(rc.ruta)) 
        AND LOWER(TRIM(v.tipo_vehiculo)) = LOWER(TRIM(rc.tipo_vehiculo))
    LEFT JOIN tarifas t 
        ON LOWER(TRIM(v.empresa)) = LOWER(TRIM(t.empresa)) 
        AND LOWER(TRIM(v.tipo_vehiculo)) = LOWER(TRIM(t.tipo_vehiculo))
    WHERE v.fecha >= '$desdeIni' 
      AND v.fecha <= '$hastaFin'
      $condicionEmpresa
    ORDER BY v.fecha ASC, v.id ASC
";
$resViajes = $conn->query($sqlViajes);

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

// ========== TABLA 1: LISTA DE CONDUCTORES ==========
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

// ========== TABLA 2: DETALLE DE VIAJES CON VEHÍCULO Y VALOR ==========
$section->addText("DETALLE DE VIAJES POR FECHA", ['bold' => true, 'size' => 12]);
$section->addTextBreak(1);

$tableViajes = $section->addTable([
    'borderSize' => 6, 
    'borderColor' => '000000', 
    'cellMargin' => 80,
    'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER
]);

// Encabezado tabla viajes (AHORA CON 5 COLUMNAS)
$tableViajes->addRow();
$tableViajes->addCell(1500)->addText("FECHA", ['bold' => true]);
$tableViajes->addCell(3000)->addText("CONDUCTOR", ['bold' => true]);
$tableViajes->addCell(2500)->addText("VEHÍCULO", ['bold' => true]); // NUEVA COLUMNA
$tableViajes->addCell(3000)->addText("RUTA", ['bold' => true]);
$tableViajes->addCell(2000)->addText("VALOR", ['bold' => true]); // NUEVA COLUMNA

$totalValores = 0;

if ($resViajes && $resViajes->num_rows > 0) {
    while ($row = $resViajes->fetch_assoc()) {
        $tableViajes->addRow();
        $tableViajes->addCell(1500)->addText(substr($row['fecha'], 0, 10));
        $tableViajes->addCell(3000)->addText($row['nombre'] ?: '-');
        
        // COLUMNA VEHÍCULO: tipo de vehículo formateado
        $tipoVehiculo = obtenerTipoVehiculo($row['tipo_vehiculo']);
        $tableViajes->addCell(2500)->addText($tipoVehiculo);
        
        // COLUMNA RUTA
        $tableViajes->addCell(3000)->addText($row['ruta'] ?: '-');
        
        // COLUMNA VALOR: calcular y mostrar
        $valor = $row['valor_viaje'];
        if ($valor !== null && $valor > 0) {
            $totalValores += floatval($valor);
            $tableViajes->addCell(2000)->addText(formatearMoneda($valor));
        } else {
            // Si no hay valor, mostrar mensaje con la clasificación si existe
            $textoValor = "N/A";
            if (!empty($row['clasificacion'])) {
                $textoValor = "Sin tarifa (" . $row['clasificacion'] . ")";
            } else {
                $textoValor = "Sin clasificación";
            }
            $tableViajes->addCell(2000)->addText($textoValor);
        }
    }
    
    // Agregar fila de TOTAL
    $tableViajes->addRow();
    $cellTotal = $tableViajes->addCell(10000, ['gridSpan' => 4]);
    $cellTotal->addText("TOTAL", ['bold' => true]);
    $tableViajes->addCell(2000)->addText(formatearMoneda($totalValores), ['bold' => true]);
    
} else {
    $tableViajes->addRow();
    $cell = $tableViajes->addCell(12000, ['gridSpan' => 5]);
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

// Envío directo al navegado
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