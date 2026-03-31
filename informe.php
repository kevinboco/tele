<?php
// ACTIVAR MODO DEPURACIÓN
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// EVITAR cualquier salida previa
if (ob_get_level()) { ob_end_clean(); }
header_remove();

// Conexión BD
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Función para obtener el tipo de vehículo formateado
function obtenerTipoVehiculo($tipo) {
    if (stripos($tipo, 'burbuja') !== false) {
        return 'Camioneta Burbuja 4x4 Doble Cabina';
    }
    if (stripos($tipo, 'carrotanque') !== false) {
        return 'Carrotanque';
    }
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
    
    return 'Maicao - Nazareth - Maicao';
}

// Función para formatear valores monetarios
function formatearMoneda($valor) {
    if ($valor === null || $valor === '') return 'N/A';
    return '$ ' . number_format(floatval($valor), 0, ',', '.');
}

// Función para asignar conductores evitando repeticiones consecutivas (máximo 2 veces seguidas)
function asignarConductorConRegla($conductoresLista, $ultimoConductor, $consecutivos) {
    if ($ultimoConductor === null) {
        return $conductoresLista[array_rand($conductoresLista)];
    }
    
    $opciones = [];
    foreach ($conductoresLista as $conductor) {
        if ($conductor !== $ultimoConductor) {
            $opciones[] = $conductor;
        } else {
            if ($consecutivos < 2) {
                $opciones[] = $conductor;
            }
        }
    }
    
    if (empty($opciones)) {
        return $conductoresLista[array_rand($conductoresLista)];
    }
    
    return $opciones[array_rand($opciones)];
}

// Función para verificar si un conductor es de carrotanque
function esConductorCarrotanque($conn, $nombreConductor) {
    $nombreEscapado = $conn->real_escape_string($nombreConductor);
    $sql = "SELECT tipo_vehiculo FROM viajes WHERE nombre = '$nombreEscapado' AND tipo_vehiculo IS NOT NULL ORDER BY fecha DESC LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        $tipo = strtolower(trim($row['tipo_vehiculo']));
        return strpos($tipo, 'carrotanque') !== false;
    }
    return false;
}

// Si no se han enviado parámetros, mostramos formulario
if (empty($_POST['desde']) || empty($_POST['hasta']) || !isset($_POST['conductores_seleccionados'])) {
    
    // Obtener lista de empresas
    $empresas = [];
    $resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
    if ($resEmp) while ($r = $resEmp->fetch_assoc()) { $empresas[] = $r['empresa']; }
    
    // Obtener lista de todos los conductores para el buscador
    $todosConductores = [];
    $resCond = $conn->query("SELECT DISTINCT nombre, cedula, tipo_vehiculo FROM viajes WHERE nombre IS NOT NULL AND nombre <> '' ORDER BY nombre ASC");
    if ($resCond) {
        while ($r = $resCond->fetch_assoc()) {
            $todosConductores[] = $r;
        }
    }
    
    ?>
    <!doctype html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Generar Informe de Viajes | Sistema de Transporte</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            :root {
                --primary-color: #0d6efd;
                --secondary-color: #6c757d;
                --success-color: #198754;
                --warning-color: #ffc107;
                --danger-color: #dc3545;
                --info-color: #0dcaf0;
                --dark-color: #212529;
                --light-color: #f8f9fa;
            }
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                min-height: 100vh;
                padding: 2rem;
            }
            
            .container-custom {
                max-width: 1600px;
                margin: 0 auto;
            }
            
            /* Tarjeta principal */
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
            
            /* Encabezado */
            .card-header-custom {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 2rem;
                text-align: center;
            }
            
            .card-header-custom h1 {
                font-size: 2rem;
                margin-bottom: 0.5rem;
                font-weight: 600;
            }
            
            .card-header-custom p {
                margin-bottom: 0;
                opacity: 0.9;
            }
            
            /* Cuerpo */
            .card-body-custom {
                padding: 2rem;
            }
            
            /* Sección de fechas */
            .date-section {
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                border-radius: 15px;
                padding: 1.5rem;
                margin-bottom: 2rem;
            }
            
            .date-input {
                background: white;
                border-radius: 10px;
                padding: 0.5rem;
                border: 2px solid #e0e0e0;
                transition: all 0.3s;
            }
            
            .date-input:focus-within {
                border-color: var(--primary-color);
                box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
            }
            
            .date-input label {
                font-size: 0.85rem;
                color: var(--secondary-color);
                margin-bottom: 0.25rem;
            }
            
            .date-input input {
                border: none;
                padding: 0;
                font-size: 1rem;
                width: 100%;
                outline: none;
            }
            
            /* Tarjetas de sección */
            .section-card {
                background: white;
                border-radius: 15px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.08);
                overflow: hidden;
                margin-bottom: 2rem;
                transition: transform 0.3s, box-shadow 0.3s;
            }
            
            .section-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 30px rgba(0,0,0,0.12);
            }
            
            .section-header {
                background: linear-gradient(135deg, var(--primary-color) 0%, #0b5ed7 100%);
                color: white;
                padding: 1rem 1.5rem;
            }
            
            .section-header h3 {
                margin: 0;
                font-size: 1.2rem;
                font-weight: 600;
            }
            
            .section-header i {
                font-size: 1.3rem;
            }
            
            .section-content {
                padding: 1.5rem;
                max-height: 500px;
                overflow-y: auto;
            }
            
            /* Estilo para el resumen de selección */
            .summary-content {
                max-height: none;
                overflow-y: visible;
            }
            
            .selected-drivers-list {
                max-height: 350px;
                overflow-y: auto;
            }
            
            .selected-driver-item {
                padding: 0.75rem;
                margin-bottom: 0.75rem;
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                border-radius: 10px;
                border-left: 4px solid var(--success-color);
                transition: all 0.3s;
                animation: slideIn 0.3s ease;
            }
            
            .selected-driver-item:hover {
                transform: translateX(5px);
                background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            
            .selected-driver-name {
                font-weight: 600;
                color: var(--dark-color);
                font-size: 1rem;
            }
            
            .selected-driver-badge {
                font-size: 0.7rem;
                padding: 0.2rem 0.6rem;
                border-radius: 20px;
                margin-left: 0.5rem;
                display: inline-block;
            }
            
            .badge-carrotanque-selected {
                background: #fff3cd;
                color: #856404;
                border: 1px solid #ffc107;
            }
            
            .badge-regular-selected {
                background: #d1ecf1;
                color: #0c5460;
                border: 1px solid #0dcaf0;
            }
            
            .empty-selection {
                text-align: center;
                padding: 2rem;
                color: var(--secondary-color);
                background: var(--light-color);
                border-radius: 10px;
            }
            
            /* Lista de empresas */
            .empresas-list {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 0.5rem;
            }
            
            .empresa-item {
                padding: 0.5rem;
                border-radius: 8px;
                transition: background 0.2s;
            }
            
            .empresa-item:hover {
                background: var(--light-color);
            }
            
            .empresa-item input[type="checkbox"] {
                margin-right: 0.5rem;
            }
            
            /* Lista de conductores */
            .search-box {
                position: relative;
                margin-bottom: 1.5rem;
            }
            
            .search-box i {
                position: absolute;
                left: 1rem;
                top: 50%;
                transform: translateY(-50%);
                color: var(--secondary-color);
            }
            
            .search-box input {
                width: 100%;
                padding: 0.75rem 1rem 0.75rem 2.5rem;
                border: 2px solid #e0e0e0;
                border-radius: 10px;
                font-size: 0.95rem;
                transition: all 0.3s;
            }
            
            .search-box input:focus {
                border-color: var(--primary-color);
                outline: none;
                box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
            }
            
            .result-counter {
                font-size: 0.85rem;
                color: var(--secondary-color);
                margin-top: 0.5rem;
            }
            
            .btn-group-custom {
                display: flex;
                gap: 0.5rem;
                margin-bottom: 1rem;
            }
            
            .conductor-list {
                max-height: 400px;
                overflow-y: auto;
            }
            
            .conductor-item {
                padding: 0.75rem;
                border: 1px solid #e0e0e0;
                border-radius: 10px;
                margin-bottom: 0.5rem;
                transition: all 0.2s;
                cursor: pointer;
            }
            
            .conductor-item:hover {
                background: var(--light-color);
                border-color: var(--primary-color);
                transform: translateX(5px);
            }
            
            .conductor-item .form-check {
                margin: 0;
            }
            
            .conductor-name {
                font-weight: 600;
                color: var(--dark-color);
            }
            
            .conductor-cedula {
                font-size: 0.8rem;
                color: var(--secondary-color);
            }
            
            .vehicle-badge {
                display: inline-block;
                padding: 0.2rem 0.6rem;
                border-radius: 20px;
                font-size: 0.7rem;
                font-weight: 600;
                margin-top: 0.25rem;
            }
            
            .badge-carrotanque {
                background: #fff3cd;
                color: #856404;
                border-left: 3px solid var(--warning-color);
            }
            
            .badge-burbuja {
                background: #d1ecf1;
                color: #0c5460;
                border-left: 3px solid var(--info-color);
            }
            
            /* Leyenda */
            .legend {
                background: var(--light-color);
                border-radius: 10px;
                padding: 1rem;
            }
            
            .legend-item {
                display: flex;
                align-items: center;
                margin-bottom: 0.75rem;
            }
            
            .legend-color {
                width: 24px;
                height: 24px;
                border-radius: 4px;
                margin-right: 0.75rem;
            }
            
            /* Botones de acción */
            .action-buttons {
                display: flex;
                gap: 1rem;
                justify-content: center;
                margin-top: 2rem;
                padding-top: 1.5rem;
                border-top: 2px solid #e0e0e0;
            }
            
            .btn-generate {
                background: linear-gradient(135deg, var(--success-color) 0%, #0f6848 100%);
                color: white;
                padding: 0.75rem 2rem;
                border-radius: 10px;
                font-weight: 600;
                border: none;
                transition: all 0.3s;
            }
            
            .btn-generate:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(25,135,84,0.3);
                color: white;
            }
            
            .btn-home {
                background: linear-gradient(135deg, var(--secondary-color) 0%, #495057 100%);
                color: white;
                padding: 0.75rem 2rem;
                border-radius: 10px;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.3s;
            }
            
            .btn-home:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(108,117,125,0.3);
                color: white;
            }
            
            /* Scroll personalizado */
            ::-webkit-scrollbar {
                width: 8px;
                height: 8px;
            }
            
            ::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 10px;
            }
            
            ::-webkit-scrollbar-thumb {
                background: var(--primary-color);
                border-radius: 10px;
            }
            
            ::-webkit-scrollbar-thumb:hover {
                background: #0b5ed7;
            }
            
            /* Responsive */
            @media (max-width: 992px) {
                body {
                    padding: 1rem;
                }
                
                .card-header-custom h1 {
                    font-size: 1.5rem;
                }
                
                .action-buttons {
                    flex-direction: column;
                }
                
                .btn-generate, .btn-home {
                    width: 100%;
                    text-align: center;
                }
            }
            
            /* Animaciones */
            @keyframes pulse {
                0%, 100% {
                    transform: scale(1);
                }
                50% {
                    transform: scale(1.05);
                }
            }
            
            .selected-count {
                animation: pulse 0.3s ease;
            }
            
            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateX(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
            
            /* Mejoras adicionales */
            .progress {
                height: 10px;
                border-radius: 10px;
                background-color: #e9ecef;
            }
            
            .progress-bar {
                background: linear-gradient(90deg, var(--primary-color), #0b5ed7);
                border-radius: 10px;
                transition: width 0.3s ease;
            }
            
            .badge {
                font-weight: 500;
            }
            
            .alert-info {
                background: linear-gradient(135deg, #cff4fc 0%, #b6effb 100%);
                border: none;
                border-left: 4px solid var(--info-color);
            }
            
            .form-check-input:checked {
                background-color: var(--primary-color);
                border-color: var(--primary-color);
            }
        </style>
    </head>
    <body>
        <div class="container-custom">
            <div class="main-card">
                <!-- Encabezado -->
                <div class="card-header-custom">
                    <i class="fas fa-truck fa-3x mb-3"></i>
                    <h1>📊 Generar Informe de Viajes</h1>
                    <p>Sistema de gestión de transporte - Asociación de Transportistas Zona Norte</p>
                </div>
                
                <!-- Cuerpo -->
                <div class="card-body-custom">
                    <form method="post" id="formInforme">
                        <!-- Sección de fechas -->
                        <div class="date-section">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="date-input">
                                        <label><i class="far fa-calendar-alt"></i> Desde</label>
                                        <input type="date" name="desde" required id="fecha_desde">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="date-input">
                                        <label><i class="far fa-calendar-alt"></i> Hasta</label>
                                        <input type="date" name="hasta" required id="fecha_hasta">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row g-4">
                            <!-- Columna izquierda: Selección de conductores -->
                            <div class="col-lg-8">
                                <div class="section-card">
                                    <div class="section-header">
                                        <h3><i class="fas fa-users"></i> 👥 Seleccionar Conductores</h3>
                                    </div>
                                    <div class="section-content">
                                        <!-- Buscador -->
                                        <div class="search-box">
                                            <i class="fas fa-search"></i>
                                            <input type="text" id="buscadorConductores" placeholder="Buscar conductor por nombre...">
                                            <div class="result-counter" id="resultadoBusqueda">
                                                <i class="fas fa-chart-line"></i> Cargando conductores...
                                            </div>
                                        </div>
                                        
                                        <!-- Botones de selección -->
                                        <div class="btn-group-custom">
                                            <button type="button" id="btnSeleccionarTodosCond" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-check-double"></i> Seleccionar todos
                                            </button>
                                            <button type="button" id="btnLimpiarTodosCond" class="btn btn-outline-secondary btn-sm">
                                                <i class="fas fa-trash-alt"></i> Limpiar todos
                                            </button>
                                        </div>
                                        
                                        <!-- Lista de conductores -->
                                        <div class="conductor-list" id="listaConductores">
                                            <?php foreach($todosConductores as $index => $cond): 
                                                $esCarrotanque = strpos(strtolower($cond['tipo_vehiculo'] ?? ''), 'carrotanque') !== false;
                                                $tipoVehiculo = obtenerTipoVehiculo($cond['tipo_vehiculo']);
                                                $badgeClass = $esCarrotanque ? 'badge-carrotanque' : 'badge-burbuja';
                                                $badgeIcon = $esCarrotanque ? 'fa-truck' : 'fa-car';
                                            ?>
                                            <div class="conductor-item" data-nombre="<?= strtolower(htmlspecialchars($cond['nombre'])) ?>" data-nombre-original="<?= htmlspecialchars($cond['nombre']) ?>">
                                                <div class="form-check">
                                                    <input class="form-check-input conductor-checkbox" type="checkbox" 
                                                           name="conductores_seleccionados[]" value="<?= htmlspecialchars($cond['nombre']) ?>" 
                                                           id="conductor_<?= $index ?>"
                                                           data-nombre="<?= htmlspecialchars($cond['nombre']) ?>"
                                                           data-cedula="<?= htmlspecialchars($cond['cedula'] ?? '') ?>"
                                                           data-vehiculo="<?= htmlspecialchars($cond['tipo_vehiculo'] ?? '') ?>"
                                                           data-es-carrotanque="<?= $esCarrotanque ? 'true' : 'false' ?>">
                                                    <label class="form-check-label" for="conductor_<?= $index ?>" style="width: 100%; cursor: pointer;">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div>
                                                                <span class="conductor-name"><?= htmlspecialchars($cond['nombre']) ?></span>
                                                                <?php if(!empty($cond['cedula'])): ?>
                                                                    <span class="conductor-cedula">(Cédula: <?= htmlspecialchars($cond['cedula']) ?>)</span>
                                                                <?php endif; ?>
                                                                <div>
                                                                    <span class="vehicle-badge <?= $badgeClass ?>">
                                                                        <i class="fas <?= $badgeIcon ?>"></i> <?= htmlspecialchars($tipoVehiculo) ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <?php if($esCarrotanque): ?>
                                                                <span class="badge bg-warning text-dark">
                                                                    <i class="fas fa-exclamation-triangle"></i> Único
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Columna derecha: Resumen de selección y Empresas -->
                            <div class="col-lg-4">
                                <!-- Resumen de selección - CON LISTA DE NOMBRES -->
                                <div class="section-card" id="summaryCard">
                                    <div class="section-header">
                                        <h3><i class="fas fa-chart-bar"></i> 📋 Resumen de selección</h3>
                                    </div>
                                    <div class="section-content summary-content">
                                        <div class="text-center mb-3">
                                            <h2 id="selectedCount" class="display-4 text-primary">0</h2>
                                            <p class="text-muted">conductores seleccionados</p>
                                            <div class="progress mb-3">
                                                <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <h6 class="fw-bold mb-3">
                                                <i class="fas fa-list"></i> Conductores seleccionados:
                                            </h6>
                                            <div id="selectedDriversList" class="selected-drivers-list">
                                                <div class="empty-selection">
                                                    <i class="fas fa-info-circle fa-2x mb-2"></i>
                                                    <p>No hay conductores seleccionados</p>
                                                    <small>Seleccione uno o más conductores de la lista</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Selección de empresas -->
                                <div class="section-card">
                                    <div class="section-header">
                                        <h3><i class="fas fa-building"></i> 🏢 Seleccionar Empresas</h3>
                                    </div>
                                    <div class="section-content">
                                        <div class="select-all-container mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="seleccionarTodos">
                                                <label class="form-check-label" for="seleccionarTodos">
                                                    <strong><i class="fas fa-check-circle"></i> Seleccionar todas las empresas</strong>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="empresas-list">
                                            <?php if (empty($empresas)): ?>
                                                <p class="text-muted mb-0"><i class="fas fa-info-circle"></i> No hay empresas disponibles</p>
                                            <?php else: ?>
                                                <?php foreach($empresas as $index => $e): ?>
                                                <div class="empresa-item">
                                                    <input class="form-check-input empresa-item-checkbox" type="checkbox" 
                                                           name="empresas[]" value="<?= htmlspecialchars($e) ?>" 
                                                           id="empresa_<?= $index ?>">
                                                    <label class="form-check-label" for="empresa_<?= $index ?>">
                                                        <?= htmlspecialchars($e) ?>
                                                    </label>
                                                </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted mt-2 d-block">
                                            <i class="fas fa-info-circle"></i> Si no selecciona ninguna, se incluirán todas las empresas
                                        </small>
                                    </div>
                                </div>
                                
                                <!-- Leyenda informativa -->
                                <div class="section-card">
                                    <div class="section-header">
                                        <h3><i class="fas fa-info-circle"></i> ℹ️ Información importante</h3>
                                    </div>
                                    <div class="section-content">
                                        <div class="legend">
                                            <div class="legend-item">
                                                <div class="legend-color" style="background: #fff3cd; border-left: 3px solid #ffc107;"></div>
                                                <span><i class="fas fa-truck"></i> <strong>Carrotanque:</strong> Mantiene su nombre real</span>
                                            </div>
                                            <div class="legend-item">
                                                <div class="legend-color" style="background: #d1ecf1; border-left: 3px solid #0dcaf0;"></div>
                                                <span><i class="fas fa-car"></i> <strong>Demás vehículos:</strong> Distribución aleatoria</span>
                                            </div>
                                            <div class="legend-item">
                                                <div class="legend-color" style="background: #f8f9fa; border-left: 3px solid #198754;"></div>
                                                <span><i class="fas fa-random"></i> <strong>Regla especial:</strong> Máximo 2 veces seguidas mismo conductor</span>
                                            </div>
                                        </div>
                                        <div class="alert alert-info mt-3 mb-0">
                                            <i class="fas fa-lightbulb"></i>
                                            <strong>¿Cómo funciona?</strong><br>
                                            Los conductores de carrotanque conservan su nombre real en los viajes que realizaron. 
                                            Los demás conductores se distribuyen aleatoriamente entre los viajes, evitando que un mismo conductor aparezca más de 2 veces seguidas.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Botones de acción -->
                        <div class="action-buttons">
                            <button type="submit" class="btn-generate">
                                <i class="fas fa-file-alt"></i> Generar Informe
                            </button>
                            <a class="btn-home" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/index2.php">
                                <i class="fas fa-home"></i> Ir a Inicio
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
            // Variables globales
            const buscador = document.getElementById('buscadorConductores');
            const conductoresItems = document.querySelectorAll('.conductor-item');
            const resultadoBusqueda = document.getElementById('resultadoBusqueda');
            const selectedCountSpan = document.getElementById('selectedCount');
            const progressBar = document.getElementById('progressBar');
            const selectedDriversList = document.getElementById('selectedDriversList');
            
            // Función para escapar HTML
            function escapeHtml(text) {
                if (!text) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            // Función para obtener el tipo de vehículo formateado
            function getTipoVehiculoFormateado(vehiculo, esCarrotanque) {
                if (esCarrotanque) return 'Carrotanque';
                if (vehiculo && vehiculo.toLowerCase().includes('burbuja')) return 'Camioneta Burbuja';
                return 'Vehículo estándar';
            }
            
            // Función para actualizar la lista de conductores seleccionados
            function actualizarListaSeleccionados() {
                const checkboxes = document.querySelectorAll('.conductor-checkbox:checked');
                const total = checkboxes.length;
                
                if (total === 0) {
                    selectedDriversList.innerHTML = `
                        <div class="empty-selection">
                            <i class="fas fa-user-slash fa-2x mb-2"></i>
                            <p>No hay conductores seleccionados</p>
                            <small>Seleccione uno o más conductores de la lista para continuar</small>
                        </div>
                    `;
                    return;
                }
                
                let html = '';
                checkboxes.forEach((checkbox, index) => {
                    const nombre = checkbox.getAttribute('data-nombre');
                    const cedula = checkbox.getAttribute('data-cedula');
                    const vehiculo = checkbox.getAttribute('data-vehiculo') || '';
                    const esCarrotanque = checkbox.getAttribute('data-es-carrotanque') === 'true';
                    const tipoVehiculo = getTipoVehiculoFormateado(vehiculo, esCarrotanque);
                    
                    html += `
                        <div class="selected-driver-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div style="flex: 1;">
                                    <div>
                                        <span class="selected-driver-name">${escapeHtml(nombre)}</span>
                                        ${cedula ? `<small class="text-muted ms-2">(Cédula: ${escapeHtml(cedula)})</small>` : ''}
                                    </div>
                                    <div class="mt-1">
                                        <span class="badge ${esCarrotanque ? 'badge-carrotanque-selected' : 'badge-regular-selected'}">
                                            <i class="fas ${esCarrotanque ? 'fa-truck' : 'fa-car'}"></i> ${escapeHtml(tipoVehiculo)}
                                        </span>
                                    </div>
                                </div>
                                <i class="fas fa-check-circle text-success" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                    `;
                });
                
                selectedDriversList.innerHTML = html;
            }
            
            // Función para actualizar contador de seleccionados
            function actualizarContadorSeleccionados() {
                const checkboxes = document.querySelectorAll('.conductor-checkbox');
                const total = checkboxes.length;
                const seleccionados = Array.from(checkboxes).filter(cb => cb.checked).length;
                
                if (selectedCountSpan) {
                    selectedCountSpan.textContent = seleccionados;
                    const porcentaje = total > 0 ? (seleccionados / total) * 100 : 0;
                    if (progressBar) {
                        progressBar.style.width = porcentaje + '%';
                        progressBar.setAttribute('aria-valuenow', porcentaje);
                    }
                    
                    if (seleccionados > 0) {
                        selectedCountSpan.classList.add('selected-count');
                        setTimeout(() => selectedCountSpan.classList.remove('selected-count'), 300);
                    }
                }
                
                // Actualizar la lista de nombres
                actualizarListaSeleccionados();
            }
            
            // Buscador de conductores - FILTRO DESDE EL INICIO (startsWith)
            function filtrarConductores() {
                const busqueda = buscador.value.toLowerCase().trim();
                let contadorVisibles = 0;
                
                conductoresItems.forEach(item => {
                    const nombre = item.getAttribute('data-nombre') || '';
                    const coincide = busqueda === '' || nombre.startsWith(busqueda);
                    
                    if (coincide) {
                        item.style.display = '';
                        contadorVisibles++;
                    } else {
                        item.style.display = 'none';
                    }
                });
                
                // Actualizar contador de resultados
                if (busqueda === '') {
                    resultadoBusqueda.innerHTML = `<i class="fas fa-users"></i> Mostrando ${contadorVisibles} conductores de ${conductoresItems.length}`;
                } else {
                    resultadoBusqueda.innerHTML = `<i class="fas fa-search"></i> Se encontraron ${contadorVisibles} conductores que empiezan con "${escapeHtml(busqueda)}"`;
                }
                
                actualizarContadorSeleccionados();
            }
            
            // Evento de búsqueda
            if (buscador) {
                buscador.addEventListener('keyup', filtrarConductores);
                buscador.addEventListener('change', filtrarConductores);
            }
            
            // Seleccionar todos los conductores visibles
            const btnSeleccionarTodos = document.getElementById('btnSeleccionarTodosCond');
            if (btnSeleccionarTodos) {
                btnSeleccionarTodos.addEventListener('click', function() {
                    const itemsVisibles = document.querySelectorAll('.conductor-item[style=""]');
                    itemsVisibles.forEach(item => {
                        const checkbox = item.querySelector('.conductor-checkbox');
                        if (checkbox) checkbox.checked = true;
                    });
                    actualizarContadorSeleccionados();
                });
            }
            
            // Limpiar todos los conductores
            const btnLimpiarTodos = document.getElementById('btnLimpiarTodosCond');
            if (btnLimpiarTodos) {
                btnLimpiarTodos.addEventListener('click', function() {
                    document.querySelectorAll('.conductor-checkbox').forEach(checkbox => {
                        checkbox.checked = false;
                    });
                    actualizarContadorSeleccionados();
                });
            }
            
            // Actualizar contador cuando se marcan/desmarcan checkboxes
            document.querySelectorAll('.conductor-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', actualizarContadorSeleccionados);
            });
            
            // Seleccionar todas las empresas
            const seleccionarTodos = document.getElementById('seleccionarTodos');
            const checkboxesEmpresa = document.querySelectorAll('.empresa-item-checkbox');
            
            function actualizarSeleccionarTodos() {
                const totalCheckboxes = checkboxesEmpresa.length;
                const checkboxesSeleccionados = document.querySelectorAll('.empresa-item-checkbox:checked').length;
                
                if (seleccionarTodos) {
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
            }
            
            if (seleccionarTodos) {
                seleccionarTodos.addEventListener('change', function() {
                    checkboxesEmpresa.forEach(checkbox => {
                        checkbox.checked = seleccionarTodos.checked;
                    });
                });
                
                checkboxesEmpresa.forEach(checkbox => {
                    checkbox.addEventListener('change', actualizarSeleccionarTodos);
                });
                
                actualizarSeleccionarTodos();
            }
            
            // Validar que se haya seleccionado al menos un conductor antes de enviar
            const formInforme = document.getElementById('formInforme');
            if (formInforme) {
                formInforme.addEventListener('submit', function(e) {
                    const seleccionados = document.querySelectorAll('.conductor-checkbox:checked');
                    if (seleccionados.length === 0) {
                        e.preventDefault();
                        alert('⚠️ Por favor, seleccione al menos un conductor para generar el informe.');
                        return false;
                    }
                });
            }
            
            // Inicializar contador
            setTimeout(() => {
                filtrarConductores();
                actualizarContadorSeleccionados();
            }, 100);
            
            // Establecer fechas por defecto (últimos 30 días)
            const hoy = new Date();
            const hace30Dias = new Date();
            hace30Dias.setDate(hoy.getDate() - 30);
            
            const fechaDesde = document.getElementById('fecha_desde');
            const fechaHasta = document.getElementById('fecha_hasta');
            
            if (fechaDesde && !fechaDesde.value) {
                fechaDesde.value = hace30Dias.toISOString().split('T')[0];
            }
            if (fechaHasta && !fechaHasta.value) {
                fechaHasta.value = hoy.toISOString().split('T')[0];
            }
            
            // Hacer click en los items de conductores para seleccionar
            document.querySelectorAll('.conductor-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    // Evitar que el click en el checkbox se duplique
                    if (e.target.type !== 'checkbox') {
                        const checkbox = this.querySelector('.conductor-checkbox');
                        if (checkbox) {
                            checkbox.checked = !checkbox.checked;
                            actualizarContadorSeleccionados();
                        }
                    }
                });
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// ========== PROCESAMIENTO DEL INFORME ==========

// Parámetros
$desde = $_POST['desde'];
$hasta = $_POST['hasta'];
$empresasSeleccionadas = $_POST['empresas'] ?? [];
$conductoresSeleccionados = $_POST['conductores_seleccionados'] ?? [];

// Validar que las fechas no estén vacías
if (empty($desde) || empty($hasta)) {
    die("Error: Fechas no válidas");
}

// Validar que se hayan seleccionado conductores
if (empty($conductoresSeleccionados)) {
    die("Error: Debe seleccionar al menos un conductor para generar el informe.");
}

// Normaliza a todo el día
$desdeIni = $conn->real_escape_string($desde . " 00:00:00");
$hastaFin = $conn->real_escape_string($hasta . " 23:59:59");

// Construir condición para múltiples empresas
$condicionEmpresa = "";
if (!empty($empresasSeleccionadas)) {
    $empresasEscapadas = array_map(function($emp) use ($conn) {
        return "'" . $conn->real_escape_string($emp) . "'";
    }, $empresasSeleccionadas);
    $condicionEmpresa = " AND v.empresa IN (" . implode(",", $empresasEscapadas) . ")";
}

// ========== IDENTIFICAR CONDUCTORES DE CARROTANQUE ==========
$conductoresCarrotanque = [];
foreach ($conductoresSeleccionados as $conductor) {
    if (esConductorCarrotanque($conn, $conductor)) {
        $conductoresCarrotanque[] = $conductor;
    }
}

// ========== OBTENER DATOS DE LOS CONDUCTORES SELECCIONADOS ==========
$conductoresInfo = [];
foreach ($conductoresSeleccionados as $conductorNombre) {
    $nombreEscapado = $conn->real_escape_string($conductorNombre);
    $sqlConductorInfo = "
        SELECT DISTINCT nombre, cedula, tipo_vehiculo 
        FROM viajes 
        WHERE nombre = '$nombreEscapado' 
        LIMIT 1
    ";
    $resInfo = $conn->query($sqlConductorInfo);
    if ($resInfo && $row = $resInfo->fetch_assoc()) {
        $conductoresInfo[] = $row;
    } else {
        $conductoresInfo[] = ['nombre' => $conductorNombre, 'cedula' => 'N/A', 'tipo_vehiculo' => ''];
    }
}

// ========== CONSULTA PRINCIPAL - OBTENER TODOS LOS VIAJES ==========
$sqlViajes = "
    SELECT 
        v.fecha,
        v.nombre as conductor_real,
        v.ruta,
        v.tipo_vehiculo,
        v.empresa,
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
    WHERE v.fecha >= '$desdeIni' 
      AND v.fecha <= '$hastaFin'
      $condicionEmpresa
    ORDER BY v.fecha ASC, v.id ASC
";

$resViajes = $conn->query($sqlViajes);
if (!$resViajes) {
    die("Error en consulta viajes: " . $conn->error . "<br>SQL: " . $sqlViajes);
}

// ========== ASIGNAR CONDUCTORES CON REGLAS ESPECIALES ==========
$viajesAsignados = [];
$totalValores = 0;
$ultimoConductor = null;
$consecutivos = 0;

// Lista de conductores NO carrotanque para asignación aleatoria
$conductoresNoCarrotanque = array_diff($conductoresSeleccionados, $conductoresCarrotanque);

if ($resViajes && $resViajes->num_rows > 0) {
    while ($row = $resViajes->fetch_assoc()) {
        $tipoVehiculo = strtolower(trim($row['tipo_vehiculo'] ?? ''));
        $esViajeCarrotanque = strpos($tipoVehiculo, 'carrotanque') !== false;
        
        $conductorAsignado = '';
        
        if ($esViajeCarrotanque) {
            // VIAJE DE CARROTANQUE: conservar el conductor real
            $conductorAsignado = $row['conductor_real'];
        } else {
            // VIAJE DE OTRO TIPO: asignación aleatoria
            if (empty($conductoresNoCarrotanque)) {
                $conductoresParaAsignar = $conductoresSeleccionados;
            } else {
                $conductoresParaAsignar = $conductoresNoCarrotanque;
            }
            
            if (count($conductoresParaAsignar) == 1) {
                $conductorAsignado = $conductoresParaAsignar[0];
            } else {
                $conductorAsignado = asignarConductorConRegla($conductoresParaAsignar, $ultimoConductor, $consecutivos);
                
                if ($conductorAsignado == $ultimoConductor) {
                    $consecutivos++;
                } else {
                    $consecutivos = 1;
                }
                $ultimoConductor = $conductorAsignado;
            }
        }
        
        // Obtener información del conductor asignado
        $conductorInfo = null;
        foreach ($conductoresInfo as $info) {
            if ($info['nombre'] == $conductorAsignado) {
                $conductorInfo = $info;
                break;
            }
        }
        
        $valor = $row['valor_viaje'];
        if ($valor !== null && $valor > 0) {
            $totalValores += floatval($valor);
        }
        
        $viajesAsignados[] = [
            'fecha' => $row['fecha'],
            'conductor' => $conductorAsignado,
            'cedula' => $conductorInfo ? $conductorInfo['cedula'] : 'N/A',
            'tipo_vehiculo' => $row['tipo_vehiculo'],
            'ruta' => $row['ruta'],
            'valor' => $valor,
            'clasificacion' => $row['clasificacion'],
            'es_carrotanque' => $esViajeCarrotanque
        ];
    }
}

// ========== GENERAR DOCUMENTO WORD ==========
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
$section->addText("Conductores en informe: " . implode(", ", $conductoresSeleccionados), ['italic' => true]);
if (!empty($conductoresCarrotanque)) {
    $section->addText("⚠️ Conductores de Carrotanque (respetan su nombre real): " . implode(", ", $conductoresCarrotanque), ['italic' => true, 'color' => 'FF0000']);
}
$section->addTextBreak(2);

// ========== TABLA 1: LISTA DE CONDUCTORES SELECCIONADOS ==========
$section->addText("LISTA DE CONDUCTORES (INCLUIDOS EN INFORME)", ['bold' => true, 'size' => 12]);
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
$tableConductores->addCell(1500)->addText("TIPO", ['bold' => true]);

if (!empty($conductoresInfo)) {
    foreach ($conductoresInfo as $conductor) {
        $esCarrotanque = in_array($conductor['nombre'], $conductoresCarrotanque);
        $tableConductores->addRow();
        $tableConductores->addCell(3000)->addText($conductor['nombre'] ?: '-');
        $tableConductores->addCell(2500)->addText($conductor['cedula'] ?: 'N/A');
        
        $tipoVehiculo = obtenerTipoVehiculo($conductor['tipo_vehiculo']);
        $tableConductores->addCell(2500)->addText($tipoVehiculo);
        
        $areaCobertura = obtenerAreaCobertura($conductor['tipo_vehiculo']);
        $tableConductores->addCell(2000)->addText($areaCobertura);
        
        $tipoTexto = $esCarrotanque ? "🚛 Carrotanque (Fijo)" : "📋 Distribución Aleatoria";
        $tableConductores->addCell(1500)->addText($tipoTexto);
    }
} else {
    $tableConductores->addRow();
    $cell = $tableConductores->addCell(11500, ['gridSpan' => 5]);
    $cell->addText("📭 No hay conductores seleccionados.");
}

$section->addTextBreak(3);

// ========== TABLA 2: DETALLE DE VIAJES ==========
$section->addText("DETALLE DE VIAJES POR FECHA", ['bold' => true, 'size' => 12]);
$section->addTextBreak(1);
$section->addText("Nota: Los viajes de carrotanque conservan el conductor real. Los demás viajes se distribuyen aleatoriamente.", ['italic' => true, 'size' => 10]);
$section->addTextBreak(1);

$tableViajes = $section->addTable([
    'borderSize' => 6, 
    'borderColor' => '000000', 
    'cellMargin' => 80,
    'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER
]);

// Encabezado tabla viajes
$tableViajes->addRow();
$tableViajes->addCell(1500)->addText("FECHA", ['bold' => true]);
$tableViajes->addCell(3000)->addText("CONDUCTOR", ['bold' => true]);
$tableViajes->addCell(2500)->addText("VEHÍCULO", ['bold' => true]);
$tableViajes->addCell(3000)->addText("RUTA", ['bold' => true]);
$tableViajes->addCell(2000)->addText("VALOR", ['bold' => true]);

if (!empty($viajesAsignados)) {
    foreach ($viajesAsignados as $viaje) {
        $tableViajes->addRow();
        $tableViajes->addCell(1500)->addText(substr($viaje['fecha'], 0, 10));
        
        $textoConductor = $viaje['conductor'] ?: '-';
        if ($viaje['es_carrotanque']) {
            $textoConductor .= " 🚛";
        }
        $tableViajes->addCell(3000)->addText($textoConductor);
        
        $tipoVehiculo = obtenerTipoVehiculo($viaje['tipo_vehiculo']);
        $tableViajes->addCell(2500)->addText($tipoVehiculo);
        
        $tableViajes->addCell(3000)->addText($viaje['ruta'] ?: '-');
        
        $valor = $viaje['valor'];
        if ($valor !== null && $valor > 0) {
            $tableViajes->addCell(2000)->addText(formatearMoneda($valor));
        } else {
            $textoValor = "N/A";
            if (!empty($viaje['clasificacion'])) {
                $textoValor = "Sin tarifa (" . $viaje['clasificacion'] . ")";
            } else {
                $textoValor = "Sin clasificar";
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