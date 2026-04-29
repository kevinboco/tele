<?php
// ACTIVAR MODO DEPURACIÓN
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// Conexión BD
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Función para formatear valores monetarios
function formatearMoneda($valor) {
    if ($valor === null || $valor === '' || $valor <= 0) return 'N/A';
    return '$ ' . number_format(floatval($valor), 0, ',', '.');
}

// Función para obtener el tipo de vehículo formateado
function obtenerTipoVehiculoFormateado($tipo) {
    if (stripos($tipo, 'burbuja') !== false) {
        return 'Camioneta Burbuja 4x4 Doble Cabina';
    }
    if (stripos($tipo, 'carrotanque') !== false) {
        return 'Carrotanque';
    }
    if (stripos($tipo, '350') !== false) {
        return 'Camión 350';
    }
    if (stripos($tipo, 'copetrana') !== false) {
        return 'Copetrana';
    }
    return $tipo ?: '-';
}

// Función para verificar si una ruta contiene Maicao o Riohacha
function esRutaMaicaoRiohacha($ruta) {
    $rutaLower = strtolower($ruta);
    return (strpos($rutaLower, 'maicao') !== false || strpos($rutaLower, 'riohacha') !== false);
}

// Obtener lista de todos los conductores para autocompletado
$todosConductores = [];
$resCond = $conn->query("SELECT DISTINCT nombre, cedula, tipo_vehiculo FROM viajes WHERE nombre IS NOT NULL AND nombre != '' ORDER BY nombre ASC");
if ($resCond) {
    while ($r = $resCond->fetch_assoc()) {
        $todosConductores[] = $r;
    }
}

// ========== PROCESAR GENERACIÓN DE INFORME WORD ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_generar_informe'])) {
    
    if (ob_get_level()) { ob_end_clean(); }
    header_remove();
    
    $fecha_desde = $_POST['fecha_desde'];
    $fecha_hasta = $_POST['fecha_hasta'];
    $empresas_seleccionadas = isset($_POST['empresas']) ? $_POST['empresas'] : [];
    $asignacionesJSON = isset($_POST['asignaciones']) ? $_POST['asignaciones'] : '{}';
    $asignaciones = json_decode($asignacionesJSON, true);
    
    if (empty($fecha_desde) || empty($fecha_hasta)) {
        die("Error: Debe seleccionar ambas fechas.");
    }
    
    if (empty($empresas_seleccionadas)) {
        die("Error: Debe seleccionar al menos un puesto de salud.");
    }
    
    $fechaDesdeSql = $conn->real_escape_string($fecha_desde . " 00:00:00");
    $fechaHastaSql = $conn->real_escape_string($fecha_hasta . " 23:59:59");
    
    $phpWord = new PhpWord();
    $section = $phpWord->addSection();
    
    $section->getStyle()->setMarginTop(720);
    $section->getStyle()->setMarginBottom(720);
    $section->getStyle()->setMarginLeft(720);
    $section->getStyle()->setMarginRight(720);
    
    $section->addText("INFORME DE VIAJES POR PUESTO DE SALUD Y CONDUCTOR", ['bold' => true, 'size' => 16, 'color' => '1F4E78'], ['align' => 'center']);
    $section->addTextBreak(0.5);
    
    $section->addText("ASOCIACIÓN DE TRANSPORTISTAS ZONA NORTE EXTREMA WUINPUMUÍN", 
        ['italic' => true, 'size' => 10, 'color' => '666666'], ['align' => 'center']);
    $section->addTextBreak(0.5);
    
    $section->addText("Periodo: " . date('d/m/Y', strtotime($fecha_desde)) . " al " . date('d/m/Y', strtotime($fecha_hasta)), ['bold' => true, 'size' => 10]);
    $section->addTextBreak(1);
    
    // Array para almacenar viajes extra de p.nazareth
    $viajesExtra = [];
    
    // Procesar cada empresa seleccionada
    foreach ($empresas_seleccionadas as $empresa) {
        
        $empresaSql = $conn->real_escape_string($empresa);
        $esPuestoNazareth = (strtolower($empresa) === 'p.nazareth');
        
        // Verificar si hay asignación manual para esta empresa
        $conductoresAsignados = [];
        $asignacionManual = false;
        
        if (isset($asignaciones[$empresa]) && !empty($asignaciones[$empresa])) {
            $conductoresAsignados = $asignaciones[$empresa];
            $asignacionManual = true;
        }
        
        // Obtener los nombres reales de los conductores de esta empresa (para filtrar viajes)
        $conductoresReales = [];
        $sqlConductoresReales = "
            SELECT DISTINCT v.nombre, v.cedula, v.tipo_vehiculo
            FROM viajes v
            WHERE v.empresa = '$empresaSql'
                AND v.nombre IS NOT NULL 
                AND v.nombre != ''
            ORDER BY v.nombre ASC
        ";
        $resReales = $conn->query($sqlConductoresReales);
        if ($resReales) {
            while ($c = $resReales->fetch_assoc()) {
                $conductoresReales[$c['nombre']] = $c;
            }
        }
        
        // Determinar qué conductores mostrar y a qué nombre real corresponde cada uno
        $conductoresAMostrar = [];
        
        if ($asignacionManual) {
            // Para asignación manual: cada conductor asignado tomará los viajes de un conductor real
            // Por simplicidad, asignamos el primer conductor real disponible al primer asignado, etc.
            $realesKeys = array_keys($conductoresReales);
            foreach ($conductoresAsignados as $idx => $conAsignado) {
                if (!empty($conAsignado['nombre']) && isset($realesKeys[$idx])) {
                    $nombreReal = $realesKeys[$idx];
                    $conductoresAMostrar[] = [
                        'nombre_mostrar' => $conAsignado['nombre'],
                        'cedula_mostrar' => $conAsignado['cedula'] ?? 'ASIGNADO',
                        'nombre_real' => $nombreReal,
                        'cedula_real' => $conductoresReales[$nombreReal]['cedula'] ?? '',
                        'tipo_vehiculo' => $conductoresReales[$nombreReal]['tipo_vehiculo'] ?? ''
                    ];
                } elseif (!empty($conAsignado['nombre'])) {
                    // Si no hay conductor real correspondiente, mostrar sin viajes
                    $conductoresAMostrar[] = [
                        'nombre_mostrar' => $conAsignado['nombre'],
                        'cedula_mostrar' => $conAsignado['cedula'] ?? 'ASIGNADO',
                        'nombre_real' => null,
                        'cedula_real' => '',
                        'tipo_vehiculo' => ''
                    ];
                }
            }
        } else {
            // Sin asignación manual: usar los nombres reales
            foreach ($conductoresReales as $nombreReal => $datos) {
                $conductoresAMostrar[] = [
                    'nombre_mostrar' => $nombreReal,
                    'cedula_mostrar' => $datos['cedula'] ?? 'N/A',
                    'nombre_real' => $nombreReal,
                    'cedula_real' => $datos['cedula'] ?? '',
                    'tipo_vehiculo' => $datos['tipo_vehiculo'] ?? ''
                ];
            }
        }
        
        if (empty($conductoresAMostrar)) {
            continue;
        }
        
        // Título de la empresa
        $section->addTextBreak(0.5);
        $section->addText(strtoupper($empresa), ['bold' => true, 'size' => 14, 'color' => '1F4E78']);
        if ($asignacionManual) {
            $section->addText("(* Conductores asignados manualmente *)", ['italic' => true, 'size' => 8, 'color' => 'CC6600']);
        }
        $section->addTextBreak(0.3);
        
        // Procesar cada conductor y SUS propios viajes
        foreach ($conductoresAMostrar as $conductorInfo) {
            $nombreMostrar = $conductorInfo['nombre_mostrar'];
            $cedulaMostrar = $conductorInfo['cedula_mostrar'];
            $nombreReal = $conductorInfo['nombre_real'];
            $tipoVehiculo = $conductorInfo['tipo_vehiculo'];
            
            // Si no hay nombre real, mostrar mensaje de que no tiene viajes
            if (empty($nombreReal)) {
                $section->addText("Conductor: " . $nombreMostrar . " (Cédula: " . $cedulaMostrar . ")", ['bold' => true, 'size' => 11]);
                $section->addText("No tiene viajes registrados en este período.", ['italic' => true, 'size' => 9, 'color' => '999999']);
                $section->addTextBreak(0.5);
                continue;
            }
            
            $nombreRealSql = $conn->real_escape_string($nombreReal);
            
            // CONSULTA CORREGIDA: Filtrar POR CONDUCTOR REAL
            $sqlViajes = "
                SELECT 
                    v.fecha,
                    v.ruta,
                    v.tipo_vehiculo,
                    rc.clasificacion,
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
                    ON v.ruta COLLATE utf8mb4_general_ci = rc.ruta COLLATE utf8mb4_general_ci
                    AND v.tipo_vehiculo COLLATE utf8mb4_general_ci = rc.tipo_vehiculo COLLATE utf8mb4_general_ci
                LEFT JOIN tarifas t 
                    ON v.empresa COLLATE utf8mb4_general_ci = t.empresa COLLATE utf8mb4_general_ci
                    AND v.tipo_vehiculo COLLATE utf8mb4_general_ci = t.tipo_vehiculo COLLATE utf8mb4_general_ci
                WHERE v.fecha >= '$fechaDesdeSql' 
                    AND v.fecha <= '$fechaHastaSql'
                    AND v.empresa = '$empresaSql'
                    AND v.nombre = '$nombreRealSql'
                ORDER BY v.fecha ASC, v.id ASC
            ";
            
            $resViajes = $conn->query($sqlViajes);
            
            if (!$resViajes) {
                continue;
            }
            
            $viajesNormales = [];
            $totalNormal = 0;
            
            while ($row = $resViajes->fetch_assoc()) {
                $valor = floatval($row['valor_viaje'] ?? 0);
                $viajeData = [
                    'fecha' => $row['fecha'],
                    'ruta' => $row['ruta'],
                    'tipo_vehiculo' => $row['tipo_vehiculo'],
                    'valor' => $valor,
                    'clasificacion' => $row['clasificacion'],
                    'conductor' => $nombreMostrar,
                    'cedula' => $cedulaMostrar,
                    'empresa' => $empresa,
                    'nombre_real' => $nombreReal
                ];
                
                // Clasificar viajes para p.nazareth
                if ($esPuestoNazareth && esRutaMaicaoRiohacha($row['ruta'])) {
                    $viajesExtra[] = $viajeData;
                } else {
                    $viajesNormales[] = $viajeData;
                    $totalNormal += $valor;
                }
            }
            
            // Mostrar conductor SOLO si tiene viajes normales
            if (!empty($viajesNormales)) {
                $section->addText("Conductor: " . $nombreMostrar . " (Cédula: " . $cedulaMostrar . ")", ['bold' => true, 'size' => 11]);
                if (!empty($tipoVehiculo)) {
                    $section->addText("Tipo de vehículo: " . obtenerTipoVehiculoFormateado($tipoVehiculo), ['italic' => true, 'size' => 9]);
                }
                $section->addTextBreak(0.2);
                
                $table = $section->addTable(['borderSize' => 1, 'borderColor' => 'AAAAAA', 'cellMargin' => 60, 'width' => 100 * 50]);
                
                $table->addRow();
                $table->addCell(1500)->addText("FECHA", ['bold' => true, 'size' => 9, 'align' => 'center']);
                $table->addCell(4500)->addText("RUTA", ['bold' => true, 'size' => 9, 'align' => 'center']);
                $table->addCell(2000)->addText("VALOR", ['bold' => true, 'size' => 9, 'align' => 'center']);
                
                foreach ($viajesNormales as $viaje) {
                    $table->addRow();
                    $table->addCell(1500)->addText(date('d/m/Y', strtotime($viaje['fecha'])), ['size' => 9]);
                    $table->addCell(4500)->addText($viaje['ruta'] ?: '-', ['size' => 9]);
                    
                    if ($viaje['valor'] > 0) {
                        $table->addCell(2000)->addText(formatearMoneda($viaje['valor']), ['size' => 9, 'align' => 'right']);
                    } else {
                        $textoValor = "N/A";
                        if (!empty($viaje['clasificacion'])) {
                            $textoValor = "Sin tarifa (" . $viaje['clasificacion'] . ")";
                        }
                        $table->addCell(2000)->addText($textoValor, ['size' => 9, 'align' => 'right']);
                    }
                }
                
                $table->addRow();
                $cellTotal = $table->addCell(6000, ['gridSpan' => 2]);
                $cellTotal->addText("TOTAL", ['bold' => true, 'size' => 9, 'align' => 'right']);
                $table->addCell(2000)->addText(formatearMoneda($totalNormal), ['bold' => true, 'size' => 9, 'align' => 'right', 'color' => 'CC0000']);
                
                $section->addTextBreak(0.8);
            }
        }
        
        $section->addTextBreak(0.5);
        $section->addText("_______________________________________________________________________________", ['size' => 8]);
        $section->addTextBreak(0.5);
    }
    
    // ========== SECCIÓN EXTRA ==========
    if (!empty($viajesExtra)) {
        $section->addTextBreak(1);
        $section->addText("═══════════════════════════════════════════════════════════════════════════════", ['size' => 8]);
        $section->addText("SECCIÓN EXTRA - VIAJES A MAICAO / RIOHACHA (provenientes de p.nazareth)", ['bold' => true, 'size' => 14, 'color' => 'CC6600'], ['align' => 'center']);
        $section->addText("═══════════════════════════════════════════════════════════════════════════════", ['size' => 8]);
        $section->addTextBreak(0.3);
        
        usort($viajesExtra, function($a, $b) {
            return strtotime($a['fecha']) - strtotime($b['fecha']);
        });
        
        $tablaExtra = $section->addTable(['borderSize' => 1, 'borderColor' => 'CC6600', 'cellMargin' => 60, 'width' => 100 * 50]);
        
        $tablaExtra->addRow();
        $tablaExtra->addCell(1500)->addText("FECHA", ['bold' => true, 'size' => 9, 'align' => 'center']);
        $tablaExtra->addCell(3500)->addText("RUTA", ['bold' => true, 'size' => 9, 'align' => 'center']);
        $tablaExtra->addCell(2500)->addText("CONDUCTOR", ['bold' => true, 'size' => 9, 'align' => 'center']);
        $tablaExtra->addCell(2000)->addText("VALOR", ['bold' => true, 'size' => 9, 'align' => 'center']);
        
        $totalExtraGeneral = 0;
        
        foreach ($viajesExtra as $viaje) {
            $tablaExtra->addRow();
            $tablaExtra->addCell(1500)->addText(date('d/m/Y', strtotime($viaje['fecha'])), ['size' => 9]);
            $tablaExtra->addCell(3500)->addText($viaje['ruta'] ?: '-', ['size' => 9]);
            $tablaExtra->addCell(2500)->addText($viaje['conductor'] ?: '-', ['size' => 9]);
            
            if ($viaje['valor'] > 0) {
                $tablaExtra->addCell(2000)->addText(formatearMoneda($viaje['valor']), ['size' => 9, 'align' => 'right']);
                $totalExtraGeneral += $viaje['valor'];
            } else {
                $textoValor = "N/A";
                if (!empty($viaje['clasificacion'])) {
                    $textoValor = "Sin tarifa (" . $viaje['clasificacion'] . ")";
                }
                $tablaExtra->addCell(2000)->addText($textoValor, ['size' => 9, 'align' => 'right']);
            }
        }
        
        $tablaExtra->addRow();
        $celdaTotalExtra = $tablaExtra->addCell(7500, ['gridSpan' => 3]);
        $celdaTotalExtra->addText("TOTAL VIAJES EXTRA (Maicao/Riohacha)", ['bold' => true, 'size' => 9, 'align' => 'right']);
        $tablaExtra->addCell(2000)->addText(formatearMoneda($totalExtraGeneral), ['bold' => true, 'size' => 9, 'align' => 'right', 'color' => 'CC6600']);
    }
    
    $section->addTextBreak(2);
    date_default_timezone_set('America/Bogota');
    $section->addText("Maicao, " . date('d/m/Y'), ['align' => 'right']);
    $section->addTextBreak(1);
    $section->addText("Cordialmente,", ['align' => 'right']);
    $section->addTextBreak(2);
    $section->addText("NUMAS JOSÉ IGUARÁN IGUARÁN", ['bold' => true, 'align' => 'right']);
    $section->addText("Representante Legal", ['align' => 'right']);
    
    $filename = "informe_conductores_por_puesto_" . date('Ymd_His') . ".docx";
    header("Content-Description: File Transfer");
    header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: public");
    
    $writer = IOFactory::createWriter($phpWord, 'Word2007');
    $writer->save('php://output');
    exit;
}

// Obtener lista de empresas con "P."
$empresasPuestosLista = [];
$resEmpresas = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa <> '' AND UPPER(empresa) LIKE '%P.%' ORDER BY empresa ASC");
if ($resEmpresas) {
    while ($r = $resEmpresas->fetch_assoc()) {
        $empresasPuestosLista[] = $r['empresa'];
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Informe de Viajes por Puesto de Salud | Sistema de Transporte</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary-color: #0d6efd;
            --success-color: #198754;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container-custom {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .main-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .card-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .card-header-custom h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .card-body-custom {
            padding: 2rem;
        }
        
        .btn-generar-informe {
            background: linear-gradient(135deg, var(--success-color) 0%, #0f6848 100%);
            color: white;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1.1rem;
            border: none;
            transition: all 0.3s;
            width: 100%;
        }
        
        .btn-generar-informe:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(25,135,84,0.3);
        }
        
        .btn-asignaciones {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            border: none;
            transition: all 0.3s;
            margin-bottom: 1.5rem;
            width: 100%;
        }
        
        .btn-asignaciones:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(238,90,36,0.3);
        }
        
        .lista-empresas {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .empresa-checkbox {
            display: inline-flex;
            align-items: center;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 25px;
            padding: 0.4rem 1rem;
            margin: 0.3rem;
            transition: all 0.2s;
        }
        
        .empresa-checkbox:hover {
            background: #e9ecef;
        }
        
        .empresa-checkbox input {
            margin-right: 0.5rem;
        }
        
        .empresa-checkbox label {
            margin: 0;
            font-size: 0.85rem;
            cursor: pointer;
        }
        
        .badge-asignada {
            background: #ff6b6b;
            color: white;
            font-size: 0.7rem;
            border-radius: 12px;
            padding: 0.2rem 0.5rem;
            margin-left: 0.5rem;
        }
        
        .info-banner {
            background: #e7f3ff;
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        
        .nota-especial {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            font-size: 0.85rem;
        }
        
        .asignacion-item {
            background: #f0f4ff;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .asignacion-empresa {
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: #1F4E78;
        }
        
        .contador-seleccion {
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 0.75rem;
        }
        
        .autocomplete-container {
            position: relative;
        }
        
        .autocomplete-items {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .autocomplete-items.show {
            display: block;
        }
        
        .autocomplete-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        
        .autocomplete-item:hover {
            background: #e7f3ff;
        }
        
        .autocomplete-item strong {
            color: #1F4E78;
        }
        
        .autocomplete-item small {
            display: block;
            font-size: 0.7rem;
            color: #666;
        }
        
        .modal-asignaciones .modal-body {
            max-height: 70vh;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container-custom">
        <div class="main-card">
            <div class="card-header-custom">
                <i class="fas fa-file-alt fa-3x mb-2"></i>
                <h1>📋 Informe de Viajes</h1>
                <p>Asociación de Transportistas Zona Norte Extrema Wuinpumuín</p>
            </div>
            
            <div class="card-body-custom">
                <div class="info-banner">
                    <i class="fas fa-info-circle"></i> 
                    <strong>¿Cómo funciona?</strong> Selecciona los puestos de salud, elige fechas y genera el informe.
                    Puedes asignar conductores personalizados (1 o 2) a cualquier puesto de salud.
                </div>
                
                <button type="button" class="btn-asignaciones" data-bs-toggle="modal" data-bs-target="#modalAsignaciones">
                    <i class="fas fa-users-cog"></i> 📝 Asignar conductores personalizados (opcional)
                </button>
                
                <form method="POST" action="" id="formInforme">
                    <input type="hidden" name="accion_generar_informe" value="1">
                    <input type="hidden" name="asignaciones" id="hiddenAsignaciones" value="{}">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-calendar-alt"></i> Fecha Desde
                        </label>
                        <input type="date" name="fecha_desde" id="fecha_desde" class="form-control form-control-lg" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-calendar-alt"></i> Fecha Hasta
                        </label>
                        <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control form-control-lg" required>
                    </div>
                    
                    <div class="lista-empresas">
                        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                            <h6 class="mb-0"><i class="fas fa-building"></i> Puestos de salud disponibles:</h6>
                            <div>
                                <button type="button" class="btn btn-sm btn-primary" id="btnSeleccionarTodos">Seleccionar todos</button>
                                <button type="button" class="btn btn-sm btn-secondary" id="btnDeseleccionarTodos">Deseleccionar todos</button>
                            </div>
                        </div>
                        <div id="contadorSeleccion" class="contador-seleccion"></div>
                        <div id="listaEmpresas">
                            <?php foreach ($empresasPuestosLista as $emp): ?>
                            <div class="empresa-checkbox">
                                <input type="checkbox" name="empresas[]" value="<?php echo htmlspecialchars($emp); ?>" class="empresa-check" id="emp_<?php echo md5($emp); ?>">
                                <label for="emp_<?php echo md5($emp); ?>"><?php echo htmlspecialchars($emp); ?></label>
                                <span class="badge-asignada" style="display: none;" id="badge_<?php echo md5($emp); ?>">✏️ Asignado</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-generar-informe mt-3" id="btnGenerar">
                        <i class="fas fa-file-word"></i> Generar Informe en Word
                    </button>
                </form>
                
                <div class="nota-especial">
                    <i class="fas fa-star-of-life" style="color: #cc6600;"></i> 
                    <strong>Nota:</strong> Los viajes de <strong>p.nazareth</strong> con rutas que contengan "Maicao" o "Riohacha" 
                    se muestran en una sección EXTRA al final.
                </div>
            </div>
        </div>
    </div>
    
    <!-- MODAL DE ASIGNACIONES -->
    <div class="modal fade modal-asignaciones" id="modalAsignaciones" data-bs-backdrop="static" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-users-cog"></i> Asignar conductores personalizados
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Opcional:</strong> Asigna 1 o 2 conductores a cada puesto. Escribe el nombre y selecciona de la lista.
                        Si no asignas, se usarán los nombres reales de la BD.
                    </div>
                    
                    <div id="asignacionesContainer"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-danger" id="btnLimpiarTodas">
                        <i class="fas fa-trash-alt"></i> Limpiar todas
                    </button>
                    <button type="button" class="btn btn-primary" id="btnGuardarAsignaciones">
                        <i class="fas fa-save"></i> Guardar asignaciones
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const empresasPuestos = <?php echo json_encode($empresasPuestosLista); ?>;
        const todosConductores = <?php echo json_encode($todosConductores); ?>;
        let asignaciones = {};
        
        function cargarAsignaciones() {
            const guardadas = localStorage.getItem('asignacionesConductores');
            if (guardadas) {
                try {
                    asignaciones = JSON.parse(guardadas);
                    actualizarBadges();
                } catch(e) {}
            }
        }
        
        function guardarAsignaciones() {
            localStorage.setItem('asignacionesConductores', JSON.stringify(asignaciones));
            actualizarBadges();
        }
        
        function actualizarBadges() {
            empresasPuestos.forEach(empresa => {
                const badge = document.getElementById(`badge_${md5(empresa)}`);
                if (badge) {
                    badge.style.display = (asignaciones[empresa]?.length > 0) ? 'inline-block' : 'none';
                }
            });
        }
        
        function md5(str) {
            return str.split('').reduce((a, b) => {
                a = ((a << 5) - a) + b.charCodeAt(0);
                return a & a;
            }, 0).toString(16);
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function setupAutocomplete(input, cedulaInput, conductoresList) {
            if (!input) return;
            let container = input.parentElement;
            let autocompleteDiv = container.querySelector('.autocomplete-items');
            if (!autocompleteDiv) {
                autocompleteDiv = document.createElement('div');
                autocompleteDiv.className = 'autocomplete-items';
                container.appendChild(autocompleteDiv);
            }
            
            input.addEventListener('input', function() {
                const valor = this.value.toLowerCase().trim();
                if (valor.length === 0) {
                    autocompleteDiv.innerHTML = '';
                    autocompleteDiv.classList.remove('show');
                    return;
                }
                const filtrados = conductoresList.filter(c => c.nombre.toLowerCase().includes(valor)).slice(0, 10);
                if (filtrados.length === 0) {
                    autocompleteDiv.innerHTML = '<div class="autocomplete-item">No hay resultados</div>';
                } else {
                    autocompleteDiv.innerHTML = filtrados.map(c => `
                        <div class="autocomplete-item" data-nombre="${escapeHtml(c.nombre)}" data-cedula="${escapeHtml(c.cedula || '')}">
                            <strong>${escapeHtml(c.nombre)}</strong>
                            <small>Cédula: ${escapeHtml(c.cedula || 'N/A')} | Tipo: ${escapeHtml(c.tipo_vehiculo || 'N/A')}</small>
                        </div>
                    `).join('');
                }
                autocompleteDiv.classList.add('show');
            });
            
            autocompleteDiv.addEventListener('click', function(e) {
                const item = e.target.closest('.autocomplete-item');
                if (item) {
                    input.value = item.getAttribute('data-nombre');
                    if (cedulaInput) cedulaInput.value = item.getAttribute('data-cedula');
                    autocompleteDiv.classList.remove('show');
                }
            });
            
            document.addEventListener('click', function(e) {
                if (!container.contains(e.target)) autocompleteDiv.classList.remove('show');
            });
        }
        
        function renderizarModal() {
            const container = document.getElementById('asignacionesContainer');
            if (!container) return;
            if (empresasPuestos.length === 0) {
                container.innerHTML = '<div class="alert alert-warning">No hay puestos de salud disponibles.</div>';
                return;
            }
            
            let html = '';
            empresasPuestos.forEach(empresa => {
                const asignacionActual = asignaciones[empresa] || [];
                const conductor1 = asignacionActual[0] || { nombre: '', cedula: '' };
                const conductor2 = asignacionActual[1] || { nombre: '', cedula: '' };
                html += `
                    <div class="asignacion-item" data-empresa="${empresa.replace(/"/g, '&quot;')}">
                        <div class="asignacion-empresa"><i class="fas fa-building"></i> ${escapeHtml(empresa)}</div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold">Conductor 1</label>
                                <div class="autocomplete-container">
                                    <input type="text" class="form-control form-control-sm autocomplete-input conductor1-nombre" 
                                           placeholder="Escribe para buscar..." value="${escapeHtml(conductor1.nombre)}"
                                           data-empresa="${empresa}" data-conductor="1">
                                </div>
                                <input type="text" class="form-control form-control-sm mt-1 conductor1-cedula" 
                                       placeholder="Cédula" value="${escapeHtml(conductor1.cedula)}"
                                       data-empresa="${empresa}" data-conductor="1-cedula" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold">Conductor 2 (opcional)</label>
                                <div class="autocomplete-container">
                                    <input type="text" class="form-control form-control-sm autocomplete-input conductor2-nombre" 
                                           placeholder="Escribe para buscar..." value="${escapeHtml(conductor2.nombre)}"
                                           data-empresa="${empresa}" data-conductor="2">
                                </div>
                                <input type="text" class="form-control form-control-sm mt-1 conductor2-cedula" 
                                       placeholder="Cédula" value="${escapeHtml(conductor2.cedula)}"
                                       data-empresa="${empresa}" data-conductor="2-cedula" readonly>
                            </div>
                        </div>
                        <div class="text-end mt-1">
                            <button type="button" class="btn btn-sm btn-outline-danger limpiar-asignacion" data-empresa="${empresa}">
                                <i class="fas fa-eraser"></i> Limpiar
                            </button>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
            
            empresasPuestos.forEach(empresa => {
                const input1 = container.querySelector(`.conductor1-nombre[data-empresa="${empresa}"]`);
                const cedula1 = container.querySelector(`.conductor1-cedula[data-empresa="${empresa}"]`);
                const input2 = container.querySelector(`.conductor2-nombre[data-empresa="${empresa}"]`);
                const cedula2 = container.querySelector(`.conductor2-cedula[data-empresa="${empresa}"]`);
                if (input1) setupAutocomplete(input1, cedula1, todosConductores);
                if (input2) setupAutocomplete(input2, cedula2, todosConductores);
            });
            
            document.querySelectorAll('.limpiar-asignacion').forEach(btn => {
                btn.addEventListener('click', function() {
                    delete asignaciones[this.getAttribute('data-empresa')];
                    renderizarModal();
                });
            });
        }
        
        function recogerAsignacionesDesdeModal() {
            const nuevas = {};
            document.querySelectorAll('.asignacion-item').forEach(item => {
                const empresa = item.getAttribute('data-empresa');
                const conductores = [];
                const nombre1 = item.querySelector('.conductor1-nombre')?.value.trim();
                const cedula1 = item.querySelector('.conductor1-cedula')?.value.trim();
                const nombre2 = item.querySelector('.conductor2-nombre')?.value.trim();
                const cedula2 = item.querySelector('.conductor2-cedula')?.value.trim();
                if (nombre1) conductores.push({ nombre: nombre1, cedula: cedula1 || '' });
                if (nombre2) conductores.push({ nombre: nombre2, cedula: cedula2 || '' });
                if (conductores.length > 0) nuevas[empresa] = conductores;
            });
            return nuevas;
        }
        
        document.getElementById('btnGuardarAsignaciones')?.addEventListener('click', function() {
            asignaciones = recogerAsignacionesDesdeModal();
            guardarAsignaciones();
            bootstrap.Modal.getInstance(document.getElementById('modalAsignaciones')).hide();
            alert('✅ Asignaciones guardadas correctamente.');
        });
        
        document.getElementById('btnLimpiarTodas')?.addEventListener('click', function() {
            if (confirm('¿Eliminar todas las asignaciones?')) {
                asignaciones = {};
                guardarAsignaciones();
                renderizarModal();
            }
        });
        
        document.getElementById('modalAsignaciones')?.addEventListener('show.bs.modal', () => renderizarModal());
        
        function actualizarContador() {
            const checkboxes = document.querySelectorAll('.empresa-check');
            const seleccionados = Array.from(checkboxes).filter(cb => cb.checked).length;
            const total = checkboxes.length;
            const div = document.getElementById('contadorSeleccion');
            if (div) {
                div.innerHTML = `<i class="fas fa-check-circle"></i> ${seleccionados} de ${total} puestos seleccionados`;
                div.style.color = seleccionados === 0 ? '#dc3545' : '#198754';
            }
        }
        
        document.getElementById('btnSeleccionarTodos')?.addEventListener('click', () => {
            document.querySelectorAll('.empresa-check').forEach(cb => cb.checked = true);
            actualizarContador();
        });
        
        document.getElementById('btnDeseleccionarTodos')?.addEventListener('click', () => {
            document.querySelectorAll('.empresa-check').forEach(cb => cb.checked = false);
            actualizarContador();
        });
        
        document.querySelectorAll('.empresa-check').forEach(cb => cb.addEventListener('change', actualizarContador));
        
        document.getElementById('formInforme')?.addEventListener('submit', function(e) {
            const fechaDesde = document.getElementById('fecha_desde').value;
            const fechaHasta = document.getElementById('fecha_hasta').value;
            const seleccionados = document.querySelectorAll('.empresa-check:checked');
            if (!fechaDesde || !fechaHasta) {
                e.preventDefault();
                alert('⚠️ Debes seleccionar ambas fechas.');
                return;
            }
            if (fechaDesde > fechaHasta) {
                e.preventDefault();
                alert('⚠️ La fecha "Desde" no puede ser mayor que la fecha "Hasta".');
                return;
            }
            if (seleccionados.length === 0) {
                e.preventDefault();
                alert('⚠️ Debes seleccionar al menos un puesto de salud.');
                return;
            }
            document.getElementById('hiddenAsignaciones').value = JSON.stringify(asignaciones);
        });
        
        const hoy = new Date();
        const hace30Dias = new Date();
        hace30Dias.setDate(hoy.getDate() - 30);
        const fechaDesde = document.getElementById('fecha_desde');
        const fechaHasta = document.getElementById('fecha_hasta');
        if (fechaDesde && !fechaDesde.value) fechaDesde.value = hace30Dias.toISOString().split('T')[0];
        if (fechaHasta && !fechaHasta.value) fechaHasta.value = hoy.toISOString().split('T')[0];
        
        cargarAsignaciones();
        actualizarContador();
    </script>
</body>
</html>