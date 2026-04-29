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

// ========== PROCESAR GENERACIÓN DE INFORME WORD ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_generar_informe'])) {
    
    // Evitar cualquier salida previa
    if (ob_get_level()) { ob_end_clean(); }
    header_remove();
    
    $fecha_desde = $_POST['fecha_desde'];
    $fecha_hasta = $_POST['fecha_hasta'];
    
    if (empty($fecha_desde) || empty($fecha_hasta)) {
        die("Error: Debe seleccionar ambas fechas.");
    }
    
    $fechaDesdeSql = $conn->real_escape_string($fecha_desde . " 00:00:00");
    $fechaHastaSql = $conn->real_escape_string($fecha_hasta . " 23:59:59");
    
    // Obtener todas las empresas que contienen "P." (puestos de salud)
    $empresasPuestos = [];
    $resEmpresas = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa != '' AND UPPER(empresa) LIKE '%P.%' ORDER BY empresa ASC");
    if ($resEmpresas) {
        while ($r = $resEmpresas->fetch_assoc()) {
            $empresasPuestos[] = $r['empresa'];
        }
    }
    
    if (empty($empresasPuestos)) {
        die("Error: No se encontraron empresas con 'P.' en la base de datos.");
    }
    
    // Generar documento Word
    $phpWord = new PhpWord();
    $section = $phpWord->addSection();
    
    // Configurar márgenes
    $section->getStyle()->setMarginTop(720);
    $section->getStyle()->setMarginBottom(720);
    $section->getStyle()->setMarginLeft(720);
    $section->getStyle()->setMarginRight(720);
    
    // Título principal
    $section->addText("INFORME DE VIAJES POR PUESTO DE SALUD Y CONDUCTOR", ['bold' => true, 'size' => 16, 'color' => '1F4E78'], ['align' => 'center']);
    $section->addTextBreak(0.5);
    
    // Subtítulo
    $section->addText("ASOCIACIÓN DE TRANSPORTISTAS ZONA NORTE EXTREMA WUINPUMUÍN", 
        ['italic' => true, 'size' => 10, 'color' => '666666'], ['align' => 'center']);
    $section->addTextBreak(0.5);
    
    // Información del periodo
    $section->addText("Periodo: " . date('d/m/Y', strtotime($fecha_desde)) . " al " . date('d/m/Y', strtotime($fecha_hasta)), ['bold' => true, 'size' => 10]);
    $section->addTextBreak(1);
    
    // Procesar cada empresa (puesto de salud)
    foreach ($empresasPuestos as $empresa) {
        
        // Obtener conductores únicos que tienen viajes en esta empresa
        $empresaSql = $conn->real_escape_string($empresa);
        $sqlConductores = "
            SELECT DISTINCT v.nombre, v.cedula, v.tipo_vehiculo
            FROM viajes v
            WHERE v.empresa = '$empresaSql'
                AND v.nombre IS NOT NULL 
                AND v.nombre != ''
            ORDER BY v.nombre ASC
        ";
        
        $resConductores = $conn->query($sqlConductores);
        
        if (!$resConductores || $resConductores->num_rows == 0) {
            continue;
        }
        
        // Título de la empresa
        $section->addTextBreak(0.5);
        $section->addText(strtoupper($empresa), ['bold' => true, 'size' => 14, 'color' => '1F4E78']);
        $section->addTextBreak(0.3);
        
        // Procesar cada conductor de esta empresa
        while ($conductor = $resConductores->fetch_assoc()) {
            $nombreConductor = $conductor['nombre'];
            $cedulaConductor = $conductor['cedula'] ?? 'N/A';
            $tipoVehiculoConductor = $conductor['tipo_vehiculo'] ?? '';
            
            // Consultar viajes de este conductor en esta empresa en el rango de fechas
            $conductorSql = $conn->real_escape_string($nombreConductor);
            
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
                    AND v.nombre = '$conductorSql'
                ORDER BY v.fecha ASC, v.id ASC
            ";
            
            $resViajes = $conn->query($sqlViajes);
            
            if (!$resViajes) {
                continue;
            }
            
            $viajes = [];
            $totalValor = 0;
            
            while ($row = $resViajes->fetch_assoc()) {
                $valor = floatval($row['valor_viaje'] ?? 0);
                $totalValor += $valor;
                $viajes[] = [
                    'fecha' => $row['fecha'],
                    'ruta' => $row['ruta'],
                    'tipo_vehiculo' => $row['tipo_vehiculo'],
                    'valor' => $valor,
                    'clasificacion' => $row['clasificacion']
                ];
            }
            
            // Si no hay viajes en el período, omitir este conductor
            if (empty($viajes)) {
                continue;
            }
            
            // Subtítulo del conductor
            $section->addText("Conductor: " . $nombreConductor . " (Cédula: " . $cedulaConductor . ")", ['bold' => true, 'size' => 11]);
            $section->addText("Tipo de vehículo: " . obtenerTipoVehiculoFormateado($tipoVehiculoConductor), ['italic' => true, 'size' => 9]);
            $section->addTextBreak(0.2);
            
            // Crear tabla de viajes
            $table = $section->addTable(['borderSize' => 1, 'borderColor' => 'AAAAAA', 'cellMargin' => 60, 'width' => 100 * 50]);
            
            // Encabezados
            $table->addRow();
            $table->addCell(1500)->addText("FECHA", ['bold' => true, 'size' => 9, 'align' => 'center']);
            $table->addCell(4500)->addText("RUTA", ['bold' => true, 'size' => 9, 'align' => 'center']);
            $table->addCell(2000)->addText("VALOR", ['bold' => true, 'size' => 9, 'align' => 'center']);
            
            // Filas de viajes
            foreach ($viajes as $viaje) {
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
            
            // Fila de total
            $table->addRow();
            $cellTotal = $table->addCell(6000, ['gridSpan' => 2]);
            $cellTotal->addText("TOTAL", ['bold' => true, 'size' => 9, 'align' => 'right']);
            $table->addCell(2000)->addText(formatearMoneda($totalValor), ['bold' => true, 'size' => 9, 'align' => 'right', 'color' => 'CC0000']);
            
            $section->addTextBreak(0.8);
        }
        
        $section->addTextBreak(0.5);
        $section->addText("_______________________________________________________________________________", ['size' => 8]);
        $section->addTextBreak(0.5);
    }
    
    // Firma
    $section->addTextBreak(2);
    date_default_timezone_set('America/Bogota');
    $section->addText("Maicao, " . date('d/m/Y'), ['align' => 'right']);
    $section->addTextBreak(1);
    $section->addText("Cordialmente,", ['align' => 'right']);
    $section->addTextBreak(2);
    $section->addText("NUMAS JOSÉ IGUARÁN IGUARÁN", ['bold' => true, 'align' => 'right']);
    $section->addText("Representante Legal", ['align' => 'right']);
    
    // Generar archivo
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

// Obtener lista de empresas con "P." para mostrar en la interfaz (solo informativo)
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
            --warning-color: #ffc107;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container-custom {
            max-width: 800px;
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
        
        .info-banner {
            background: #e7f3ff;
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        
        .lista-empresas {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1.5rem;
        }
        
        .badge-empresa {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            margin: 0.2rem;
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
                    <strong>¿Cómo funciona?</strong> El sistema genera automáticamente un informe con todos los conductores 
                    y sus viajes, agrupados por puesto de salud (empresas con "P."). Solo debes seleccionar el rango de fechas.
                </div>
                
                <form method="POST" action="" id="formInforme">
                    <input type="hidden" name="accion_generar_informe" value="1">
                    
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
                    
                    <button type="submit" class="btn-generar-informe">
                        <i class="fas fa-file-word"></i> Generar Informe en Word
                    </button>
                </form>
                
                <?php if (!empty($empresasPuestosLista)): ?>
                <div class="lista-empresas">
                    <h6 class="mb-2"><i class="fas fa-building"></i> Puestos de salud encontrados:</h6>
                    <?php foreach ($empresasPuestosLista as $emp): ?>
                        <span class="badge-empresa"><?php echo htmlspecialchars($emp); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Establecer fechas por defecto (últimos 30 días)
        const hoy = new Date();
        const hace30Dias = new Date();
        hace30Dias.setDate(hoy.getDate() - 30);
        
        const fechaDesdeInput = document.getElementById('fecha_desde');
        const fechaHastaInput = document.getElementById('fecha_hasta');
        
        if (fechaDesdeInput && !fechaDesdeInput.value) {
            fechaDesdeInput.value = hace30Dias.toISOString().split('T')[0];
        }
        if (fechaHastaInput && !fechaHastaInput.value) {
            fechaHastaInput.value = hoy.toISOString().split('T')[0];
        }
        
        // Validar fechas antes de enviar
        document.getElementById('formInforme').addEventListener('submit', function(e) {
            const fechaDesde = document.getElementById('fecha_desde').value;
            const fechaHasta = document.getElementById('fecha_hasta').value;
            
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
        });
    </script>
</body>
</html>