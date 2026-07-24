<?php
/*********************************************************
 * reporte_completo.php
 * VISTA ÚNICA - Reporte de préstamos pagados
 * - Muestra estadísticas
 * - Botón para generar Word con imágenes
 *********************************************************/
include("nav.php");

// ======= CONFIGURACIÓN =======
define('DB_HOST', 'mysql.hostinger.com');
define('DB_USER', 'u648222299_keboco5');
define('DB_PASS', 'Bucaramanga3011');
define('DB_NAME', 'u648222299_viajes');
const UPLOAD_DIR = __DIR__ . '/uploads/';

function db(): mysqli {
    $m = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($m->connect_errno) exit("Error DB: ".$m->connect_error);
    $m->set_charset('utf8mb4');
    return $m;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES,'UTF-8'); }
function money($n){ return number_format((float)$n,0,',','.'); }

// ============================================
// OBTENER ESTADÍSTICAS
// ============================================
$conn = db();

// Contar préstamos pagados
$result = $conn->query("SELECT COUNT(*) as total FROM prestamos WHERE pagado = 1");
$totalPagados = $result->fetch_assoc()['total'] ?? 0;

// Sumar montos
$result = $conn->query("SELECT SUM(monto) as total FROM prestamos WHERE pagado = 1");
$totalCapital = $result->fetch_assoc()['total'] ?? 0;

// Fechas
$result = $conn->query("SELECT MIN(fecha) as primero, MAX(fecha) as ultimo FROM prestamos WHERE pagado = 1");
$fechas = $result->fetch_assoc();

// Contar prestamistas y deudores
$result = $conn->query("SELECT COUNT(DISTINCT prestamista) as total FROM prestamos WHERE pagado = 1");
$totalPrestamistas = $result->fetch_assoc()['total'] ?? 0;

$result = $conn->query("SELECT COUNT(DISTINCT deudor) as total FROM prestamos WHERE pagado = 1");
$totalDeudores = $result->fetch_assoc()['total'] ?? 0;

// Obtener ÚLTIMOS 5 préstamos pagados para vista previa
$preview = [];
$result = $conn->query("
    SELECT id, deudor, prestamista, monto, fecha, pagado_at, imagen 
    FROM prestamos 
    WHERE pagado = 1 
    ORDER BY pagado_at DESC 
    LIMIT 5
");
while ($row = $result->fetch_assoc()) {
    $preview[] = $row;
}

$conn->close();

// ============================================
// PROCESAR GENERACIÓN DE REPORTE WORD
// ============================================
$generar_reporte = isset($_GET['generar']) && $_GET['generar'] == 'word';

if ($generar_reporte && $totalPagados > 0) {
    // Requerir PHPWord
    require_once 'vendor/autoload.php';
    
    use PhpOffice\PhpWord\PhpWord;
    use PhpOffice\PhpWord\IOFactory;
    
    // Obtener todos los préstamos pagados
    $conn = db();
    $sql = "
        SELECT 
            id, deudor, prestamista, monto, fecha, imagen, pagado_at,
            empresa,
            CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END AS meses,
            (monto * 
                CASE 
                    WHEN fecha >= '2025-10-29' THEN COALESCE(comision_origen_porcentaje, 13)
                    ELSE COALESCE(comision_origen_porcentaje, 10)
                END / 100 *
                CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS interes_total,
            (monto + 
                (((monto * 
                    CASE 
                        WHEN fecha >= '2025-10-29' THEN COALESCE(comision_origen_porcentaje, 13)
                        ELSE COALESCE(comision_origen_porcentaje, 10)
                    END / 100) + 
                (COALESCE(comision_base_monto, monto) * COALESCE(comision_gestor_porcentaje, 0) / 100)) *
                CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END)) AS total
        FROM prestamos
        WHERE pagado = 1
        ORDER BY pagado_at DESC
    ";
    $result = $conn->query($sql);
    
    // Crear documento Word
    $phpWord = new PhpWord();
    $phpWord->getSettings()->setThemeFontLang(new \PhpOffice\PhpWord\Shared\Converter('es'));
    
    // Estilos
    $phpWord->addTitleStyle(1, ['size' => 18, 'bold' => true, 'color' => '1a237e']);
    $phpWord->addTitleStyle(2, ['size' => 14, 'bold' => true, 'color' => '0d47a1']);
    
    $tableStyle = [
        'borderSize' => 6,
        'borderColor' => '999999',
        'cellMargin' => 80,
        'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER
    ];
    
    $headerStyle = [
        'borderSize' => 6,
        'borderColor' => '0d47a1',
        'bgColor' => 'e3f2fd',
        'valign' => 'center',
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
        'bold' => true
    ];
    
    $cellStyle = [
        'borderSize' => 6,
        'borderColor' => '999999',
        'valign' => 'center',
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER
    ];
    
    $cellStyleLeft = [
        'borderSize' => 6,
        'borderColor' => '999999',
        'valign' => 'center',
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT
    ];
    
    // SECCIÓN: PORTADA
    $section = $phpWord->addSection();
    $section->addTitle('REPORTE DE PRÉSTAMOS PAGADOS', 1);
    $section->addTextBreak(1);
    $section->addText("Fecha de generación: " . date('d/m/Y H:i:s'), ['size' => 12]);
    $section->addTextBreak(1);
    $section->addTitle('RESUMEN GENERAL', 2);
    $section->addText("Total de préstamos pagados: " . $result->num_rows);
    $section->addText("Capital total: $" . money($totalCapital));
    $section->addTextBreak(1);
    
    // SECCIÓN: TABLA DE PRÉSTAMOS
    $section->addTitle('DETALLE DE PRÉSTAMOS', 2);
    $section->addTextBreak(1);
    
    $table = $section->addTable($tableStyle);
    
    // Encabezados
    $headers = ['#', 'Deudor', 'Prestamista', 'Empresa', 'Fecha Préstamo', 'Fecha Pago', 'Capital', 'Interés', 'Total', 'Imagen'];
    $table->addRow();
    foreach ($headers as $header) {
        $table->addCell(800, $headerStyle)->addText($header);
    }
    
    $counter = 1;
    while ($row = $result->fetch_assoc()) {
        $table->addRow();
        $table->addCell(400, $cellStyle)->addText($counter);
        $table->addCell(2000, $cellStyleLeft)->addText(h($row['deudor']));
        $table->addCell(2000, $cellStyleLeft)->addText(h($row['prestamista']));
        $table->addCell(1800, $cellStyleLeft)->addText(!empty($row['empresa']) ? h($row['empresa']) : '-');
        $table->addCell(1200, $cellStyle)->addText(h($row['fecha']));
        $table->addCell(1500, $cellStyle)->addText(!empty($row['pagado_at']) ? h($row['pagado_at']) : '-');
        $table->addCell(1200, $cellStyle)->addText('$ ' . money($row['monto']));
        $table->addCell(1200, $cellStyle)->addText('$ ' . money($row['interes_total']));
        $table->addCell(1200, $cellStyle)->addText('$ ' . money($row['total']));
        
        $cellImagen = $table->addCell(1500, $cellStyle);
        if (!empty($row['imagen']) && file_exists(UPLOAD_DIR . $row['imagen'])) {
            try {
                $imagePath = UPLOAD_DIR . $row['imagen'];
                $imageStyle = ['width' => 80, 'height' => 80, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER];
                $cellImagen->addImage($imagePath, $imageStyle);
            } catch (Exception $e) {
                $cellImagen->addText('(Imagen no disponible)');
            }
        } else {
            $cellImagen->addText('(Sin imagen)');
        }
        $counter++;
    }
    
    $section->addTextBreak(2);
    $section->addText('--- Fin del reporte ---', ['size' => 10, 'color' => '666666']);
    $section->addText("Reporte generado automáticamente el " . date('d/m/Y H:i:s'), ['size' => 9, 'color' => '666666']);
    
    // Descargar
    $filename = 'reporte_prestamos_pagados_' . date('Y-m-d_H-i-s') . '.docx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save('php://output');
    $conn->close();
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Préstamos Pagados</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            width: 100%;
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.08);
            padding: 48px;
            margin: 20px auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 3px solid #1a237e;
            padding-bottom: 24px;
        }
        
        .header h1 {
            font-size: 32px;
            font-weight: 800;
            color: #1a237e;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .header .subtitle {
            color: #6b7280;
            font-size: 16px;
            margin-top: 8px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px solid #e5e7eb;
            transition: all 0.2s;
        }
        
        .stat-card:hover {
            background: #f1f5f9;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.05);
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: 800;
            color: #1a237e;
            line-height: 1.2;
        }
        
        .stat-card .label {
            font-size: 14px;
            color: #6b7280;
            margin-top: 4px;
            font-weight: 500;
        }
        
        .stat-card .icon {
            font-size: 28px;
            display: block;
            margin-bottom: 8px;
        }
        
        .btn-generar {
            display: block;
            width: 100%;
            padding: 24px;
            background: linear-gradient(135deg, #1a237e, #283593);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 20px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
            margin-top: 30px;
        }
        
        .btn-generar:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(26, 35, 126, 0.3);
            background: linear-gradient(135deg, #0d1442, #1a237e);
        }
        
        .btn-generar:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .btn-generar .icon {
            font-size: 32px;
            display: block;
            margin-bottom: 8px;
        }
        
        .btn-generar .small {
            font-size: 14px;
            font-weight: 400;
            opacity: 0.8;
            display: block;
            margin-top: 4px;
        }
        
        .preview-section {
            margin-top: 40px;
            border-top: 1px solid #e5e7eb;
            padding-top: 24px;
        }
        
        .preview-section h3 {
            font-size: 18px;
            color: #1a237e;
            margin-bottom: 16px;
        }
        
        .preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }
        
        .preview-item {
            background: #f8fafc;
            border-radius: 12px;
            padding: 12px;
            border: 1px solid #e5e7eb;
            font-size: 13px;
        }
        
        .preview-item .nombre {
            font-weight: 600;
            color: #1a237e;
        }
        
        .preview-item .monto {
            color: #0d47a1;
            font-weight: 600;
        }
        
        .preview-item .fecha {
            color: #6b7280;
            font-size: 12px;
        }
        
        .preview-item .imagen-mini {
            max-width: 100%;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            margin-top: 6px;
        }
        
        .info-extra {
            margin-top: 20px;
            padding: 16px 20px;
            background: #fef3c7;
            border-radius: 12px;
            border-left: 4px solid #f59e0b;
            font-size: 14px;
            color: #92400e;
        }
        
        .info-extra strong {
            display: block;
            margin-bottom: 4px;
        }
        
        .badge {
            display: inline-block;
            background: #e5e7eb;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            color: #374151;
        }
        
        .footer {
            margin-top: 20px;
            text-align: center;
            color: #9ca3af;
            font-size: 13px;
        }
        
        /* Loading */
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            gap: 20px;
        }
        
        .loading.active {
            display: flex;
        }
        
        .spinner {
            width: 60px;
            height: 60px;
            border: 4px solid #e5e7eb;
            border-top: 4px solid #1a237e;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .loading-text {
            font-size: 18px;
            color: #1a237e;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .container { padding: 24px; }
            .header h1 { font-size: 24px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .preview-grid { grid-template-columns: 1fr 1fr; }
        }
        
        @media (max-width: 480px) {
            .container { padding: 16px; }
            .stats-grid { grid-template-columns: 1fr; }
            .preview-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- Overlay de carga -->
<div class="loading" id="loadingOverlay">
    <div class="spinner"></div>
    <div class="loading-text">Generando reporte Word...</div>
    <p style="color: #6b7280; font-size: 14px;">Esto puede tomar unos segundos</p>
</div>

<div class="container">
    <!-- Header -->
    <div class="header">
        <h1>
            📄 Reporte de Préstamos Pagados
        </h1>
        <p class="subtitle">
            Genera un documento Word con todos los préstamos pagados, sus imágenes y detalles completos
        </p>
    </div>
    
    <!-- Estadísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="icon">✅</span>
            <div class="number"><?= number_format($totalPagados) ?></div>
            <div class="label">Préstamos Pagados</div>
        </div>
        <div class="stat-card">
            <span class="icon">💰</span>
            <div class="number">$ <?= money($totalCapital) ?></div>
            <div class="label">Capital Total</div>
        </div>
        <div class="stat-card">
            <span class="icon">🏦</span>
            <div class="number"><?= number_format($totalPrestamistas) ?></div>
            <div class="label">Prestamistas</div>
        </div>
        <div class="stat-card">
            <span class="icon">👤</span>
            <div class="number"><?= number_format($totalDeudores) ?></div>
            <div class="label">Deudores</div>
        </div>
    </div>
    
    <!-- Información adicional -->
    <div class="info-extra">
        <strong>📋 Información del reporte:</strong>
        <?php if ($totalPagados > 0): ?>
            Rango de fechas: 
            <strong><?= h($fechas['primero'] ?? 'N/A') ?></strong> 
            hasta 
            <strong><?= h($fechas['ultimo'] ?? 'N/A') ?></strong>
            <span class="badge" style="margin-left: 8px;"><?= number_format($totalPagados) ?> registros</span>
            <br>
            <span style="font-size: 13px; margin-top: 4px; display: inline-block;">
                ⚡ El documento incluirá: Deudor • Prestamista • Fecha • Capital • Interés • Total • Imagen
            </span>
        <?php else: ?>
            ⚠️ No hay préstamos pagados en el sistema para generar el reporte.
        <?php endif; ?>
    </div>
    
    <!-- Botón principal -->
    <a href="?generar=word" class="btn-generar" id="btnGenerar" onclick="mostrarCarga(event)" <?= $totalPagados == 0 ? 'disabled' : '' ?>>
        <span class="icon">📄</span>
        Generar Reporte Word
        <span class="small">
            <?= $totalPagados > 0 ? "{$totalPagados} préstamos • Incluye todas las imágenes" : "No hay préstamos pagados" ?>
        </span>
    </a>
    
    <!-- Vista previa de los últimos 5 -->
    <?php if (!empty($preview)): ?>
    <div class="preview-section">
        <h3>🔄 Últimos préstamos pagados</h3>
        <div class="preview-grid">
            <?php foreach ($preview as $item): ?>
            <div class="preview-item">
                <div class="nombre"><?= h($item['deudor']) ?></div>
                <div style="font-size: 12px; color: #6b7280;"><?= h($item['prestamista']) ?></div>
                <div class="monto">$ <?= money($item['monto']) ?></div>
                <div class="fecha">📅 <?= h($item['fecha']) ?> | Pagado: <?= h($item['pagado_at']) ?></div>
                <?php if (!empty($item['imagen']) && file_exists(UPLOAD_DIR . $item['imagen'])): ?>
                    <img class="imagen-mini" src="uploads/<?= h($item['imagen']) ?>" alt="Imagen">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="footer">
        <p>Sistema de Administración de Préstamos &bull; <?= date('Y') ?></p>
    </div>
</div>

<script>
function mostrarCarga(event) {
    <?php if ($totalPagados == 0): ?>
        event.preventDefault();
        alert('⚠️ No hay préstamos pagados en el sistema.\n\nNo se puede generar el reporte.');
        return false;
    <?php endif; ?>
    
    // Mostrar overlay de carga
    document.getElementById('loadingOverlay').classList.add('active');
    
    // Si después de 30 segundos no se ha descargado, ocultar overlay
    setTimeout(function() {
        document.getElementById('loadingOverlay').classList.remove('active');
    }, 30000);
}
</script>

</body>
</html>