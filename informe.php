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
    if (stripos($tipo, '350') !== false) {
        return 'Camión 350';
    }
    if (stripos($tipo, 'copetrana') !== false) {
        return 'Copetrana';
    }
    return $tipo ?: '-';
}

// Función para obtener la categoría del vehículo (para agrupar tablas)
function obtenerCategoriaVehiculo($tipo) {
    $tipoLower = strtolower(trim($tipo ?: ''));
    
    if (stripos($tipoLower, 'carrotanque') !== false) {
        return 'carrotanque';
    }
    if (stripos($tipoLower, '350') !== false) {
        return 'camion_350';
    }
    if (stripos($tipoLower, 'burbuja') !== false) {
        return 'burbuja';
    }
    if (stripos($tipoLower, 'copetrana') !== false) {
        return 'copetrana';
    }
    return 'otros';
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
if (empty($_POST['desde']) || empty($_POST['hasta']) || !isset($_POST['tipo_informe'])) {
    
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
                padding: 1rem;
            }
            
            .container-custom {
                max-width: 1600px;
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
                padding: 1.2rem;
                text-align: center;
            }
            
            .card-header-custom h1 {
                font-size: 1.6rem;
                margin-bottom: 0.25rem;
                font-weight: 600;
            }
            
            .card-header-custom p {
                margin-bottom: 0;
                opacity: 0.9;
                font-size: 0.85rem;
            }
            
            .card-body-custom {
                padding: 1.2rem;
            }
            
            .top-bar {
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                border-radius: 12px;
                padding: 0.8rem;
                margin-bottom: 1.2rem;
                display: flex;
                gap: 0.8rem;
                align-items: flex-end;
                flex-wrap: wrap;
            }
            
            .date-group {
                flex: 1;
                min-width: 160px;
            }
            
            .date-input {
                background: white;
                border-radius: 8px;
                padding: 0.4rem 0.8rem;
                border: 2px solid #e0e0e0;
                transition: all 0.3s;
            }
            
            .date-input:focus-within {
                border-color: var(--primary-color);
                box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
            }
            
            .date-input label {
                font-size: 0.7rem;
                color: var(--secondary-color);
                margin-bottom: 0;
                display: block;
            }
            
            .date-input input {
                border: none;
                padding: 0;
                font-size: 0.9rem;
                width: 100%;
                outline: none;
            }
            
            .presupuesto-group {
                flex: 1;
                min-width: 180px;
            }
            
            .presupuesto-input {
                background: white;
                border-radius: 8px;
                padding: 0.4rem 0.8rem;
                border: 2px solid #e0e0e0;
                transition: all 0.3s;
            }
            
            .presupuesto-input:focus-within {
                border-color: var(--success-color);
                box-shadow: 0 0 0 3px rgba(25,135,84,0.1);
            }
            
            .presupuesto-input label {
                font-size: 0.7rem;
                color: var(--secondary-color);
                margin-bottom: 0;
                display: block;
            }
            
            .presupuesto-input input {
                border: none;
                padding: 0;
                font-size: 0.9rem;
                width: 100%;
                outline: none;
            }
            
            .btn-group-actions {
                display: flex;
                gap: 0.8rem;
                flex-wrap: wrap;
            }
            
            .btn-generate-top {
                background: linear-gradient(135deg, var(--success-color) 0%, #0f6848 100%);
                color: white;
                padding: 0.5rem 1.5rem;
                border-radius: 8px;
                font-weight: 600;
                border: none;
                transition: all 0.3s;
                white-space: nowrap;
            }
            
            .btn-generate-top:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(25,135,84,0.3);
            }
            
            .btn-generate-real {
                background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
                color: white;
                padding: 0.5rem 1.5rem;
                border-radius: 8px;
                font-weight: 600;
                border: none;
                transition: all 0.3s;
                white-space: nowrap;
            }
            
            .btn-generate-real:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(13,110,253,0.3);
            }
            
            .three-columns {
                display: flex;
                gap: 1rem;
                flex-wrap: wrap;
            }
            
            .col-conductores {
                flex: 2.2;
                min-width: 260px;
            }
            
            .col-resumen {
                flex: 1.2;
                min-width: 220px;
            }
            
            .col-empresas {
                flex: 1.2;
                min-width: 200px;
            }
            
            .section-card {
                background: white;
                border-radius: 12px;
                box-shadow: 0 3px 12px rgba(0,0,0,0.08);
                overflow: hidden;
                height: 100%;
                display: flex;
                flex-direction: column;
            }
            
            .section-header {
                background: linear-gradient(135deg, var(--primary-color) 0%, #0b5ed7 100%);
                color: white;
                padding: 0.6rem 0.8rem;
            }
            
            .section-header h3 {
                margin: 0;
                font-size: 0.9rem;
                font-weight: 600;
            }
            
            .section-header h3 i {
                margin-right: 0.4rem;
                font-size: 0.85rem;
            }
            
            .section-content {
                padding: 0.8rem;
                flex: 1;
                overflow-y: auto;
                max-height: calc(100vh - 250px);
                min-height: 400px;
            }
            
            .search-box {
                position: relative;
                margin-bottom: 0.8rem;
            }
            
            .search-box i {
                position: absolute;
                left: 0.7rem;
                top: 50%;
                transform: translateY(-50%);
                color: var(--secondary-color);
                font-size: 0.8rem;
            }
            
            .search-box input {
                width: 100%;
                padding: 0.4rem 0.6rem 0.4rem 1.8rem;
                border: 1px solid #e0e0e0;
                border-radius: 6px;
                font-size: 0.8rem;
                transition: all 0.3s;
            }
            
            .search-box input:focus {
                border-color: var(--primary-color);
                outline: none;
            }
            
            .result-counter {
                font-size: 0.65rem;
                color: var(--secondary-color);
                margin-top: 0.2rem;
            }
            
            .btn-group-custom {
                display: flex;
                gap: 0.4rem;
                margin-bottom: 0.8rem;
            }
            
            .btn-group-custom .btn-sm {
                padding: 0.2rem 0.5rem;
                font-size: 0.7rem;
            }
            
            .conductor-list {
                max-height: 320px;
                overflow-y: auto;
            }
            
            .conductor-item {
                padding: 0.4rem;
                border: 1px solid #e0e0e0;
                border-radius: 6px;
                margin-bottom: 0.4rem;
                transition: all 0.2s;
                cursor: pointer;
            }
            
            .conductor-item:hover {
                background: var(--light-color);
                border-color: var(--primary-color);
            }
            
            .conductor-item .form-check {
                margin: 0;
            }
            
            .conductor-name {
                font-weight: 600;
                font-size: 0.8rem;
                color: var(--dark-color);
            }
            
            .conductor-cedula {
                font-size: 0.65rem;
                color: var(--secondary-color);
            }
            
            .vehicle-badge {
                display: inline-block;
                padding: 0.1rem 0.4rem;
                border-radius: 12px;
                font-size: 0.6rem;
                font-weight: 600;
                margin-top: 0.15rem;
            }
            
            .badge-carrotanque {
                background: #fff3cd;
                color: #856404;
                border-left: 2px solid var(--warning-color);
            }
            
            .badge-burbuja {
                background: #d1ecf1;
                color: #0c5460;
                border-left: 2px solid var(--info-color);
            }
            
            .resumen-lista {
                max-height: 380px;
                overflow-y: auto;
            }
            
            .conductor-seleccionado-item {
                padding: 0.4rem;
                border-bottom: 1px solid #e0e0e0;
                font-size: 0.75rem;
                display: flex;
                align-items: center;
                gap: 0.4rem;
            }
            
            .conductor-seleccionado-item i {
                font-size: 0.7rem;
                color: var(--success-color);
            }
            
            .conductor-seleccionado-item .nombre {
                flex: 1;
                font-weight: 500;
            }
            
            .conductor-seleccionado-item .tipo-badge {
                font-size: 0.6rem;
                padding: 0.1rem 0.3rem;
                border-radius: 10px;
                background: #e9ecef;
            }
            
            .resumen-vacio {
                text-align: center;
                color: var(--secondary-color);
                font-size: 0.75rem;
                padding: 1rem;
            }
            
            .resumen-total {
                background: var(--light-color);
                padding: 0.5rem;
                border-radius: 6px;
                margin-top: 0.5rem;
                text-align: center;
                font-size: 0.75rem;
                font-weight: bold;
            }
            
            .empresas-list {
                display: flex;
                flex-direction: column;
                gap: 0.3rem;
            }
            
            .empresa-item {
                padding: 0.3rem;
                border-radius: 4px;
                transition: background 0.2s;
            }
            
            .empresa-item:hover {
                background: var(--light-color);
            }
            
            .empresa-item input[type="checkbox"] {
                margin-right: 0.4rem;
                transform: scale(0.85);
            }
            
            .empresa-item label {
                font-size: 0.75rem;
                cursor: pointer;
            }
            
            .select-all-container {
                padding-bottom: 0.4rem;
                border-bottom: 1px solid #e0e0e0;
                margin-bottom: 0.6rem;
            }
            
            .select-all-container .form-check-label {
                font-weight: 600;
                font-size: 0.75rem;
            }
            
            .select-all-container .form-check-input {
                transform: scale(0.85);
            }
            
            .legend {
                background: var(--light-color);
                border-radius: 8px;
                padding: 0.6rem;
                margin-top: 0.8rem;
            }
            
            .legend-item {
                display: flex;
                align-items: center;
                margin-bottom: 0.4rem;
                font-size: 0.65rem;
            }
            
            .legend-color {
                width: 12px;
                height: 12px;
                border-radius: 2px;
                margin-right: 0.4rem;
            }
            
            ::-webkit-scrollbar {
                width: 5px;
                height: 5px;
            }
            
            ::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 10px;
            }
            
            ::-webkit-scrollbar-thumb {
                background: var(--primary-color);
                border-radius: 10px;
            }
            
            @media (max-width: 1000px) {
                .three-columns {
                    flex-direction: column;
                }
                
                .col-conductores, .col-resumen, .col-empresas {
                    flex: auto;
                }
                
                .top-bar {
                    flex-direction: column;
                    align-items: stretch;
                }
                
                .btn-group-actions {
                    flex-direction: column;
                }
                
                .btn-generate-top, .btn-generate-real {
                    width: 100%;
                    text-align: center;
                }
            }
            
            .badge-unic {
                font-size: 0.55rem;
                padding: 0.15rem 0.3rem;
            }
            
            .form-check-input {
                transform: scale(0.85);
                margin-top: 0.2rem;
            }
            
            .form-check-label {
                font-size: 0.8rem;
            }
            
            .info-banner {
                background: #e7f3ff;
                border-left: 4px solid var(--primary-color);
                padding: 0.6rem;
                border-radius: 8px;
                margin-bottom: 1rem;
                font-size: 0.75rem;
            }
            
            .presupuesto-banner {
                background: #fff3cd;
                border-left: 4px solid var(--warning-color);
                padding: 0.6rem;
                border-radius: 8px;
                margin-top: 0.5rem;
                font-size: 0.7rem;
            }
        </style>
    </head>
    <body>
        <div class="container-custom">
            <div class="main-card">
                <div class="card-header-custom">
                    <i class="fas fa-truck fa-2x mb-1"></i>
                    <h1>📊 Generar Informe de Viajes</h1>
                    <p>Asociación de Transportistas Zona Norte Extrema Wuinpumuín</p>
                </div>
                
                <div class="card-body-custom">
                    <div class="info-banner">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Informe Real:</strong> Muestra los conductores tal como están en la base de datos. No requiere seleccionar conductores.
                        <br>
                        <i class="fas fa-random"></i> 
                        <strong>Informe Aleatorio:</strong> Distribuye los viajes entre los conductores seleccionados (máx 2 veces seguidas). Requiere seleccionar al menos un conductor.
                        <br>
                        <i class="fas fa-chart-line"></i>
                        <strong>Presupuesto:</strong> Solo aplica para informe aleatorio. Los viajes MEDIOS de VEHÍCULOS BURBUJA se acumulan hasta alcanzar el valor ingresado. Los demás vehículos (Carrotanque, Camión 350, Copetrana, Otros) se muestran completos sin afectar el presupuesto.
                    </div>
                    
                    <form method="post" id="formInforme">
                        <div class="top-bar">
                            <div class="date-group">
                                <div class="date-input">
                                    <label><i class="far fa-calendar-alt"></i> Desde</label>
                                    <input type="date" name="desde" required id="fecha_desde">
                                </div>
                            </div>
                            <div class="date-group">
                                <div class="date-input">
                                    <label><i class="far fa-calendar-alt"></i> Hasta</label>
                                    <input type="date" name="hasta" required id="fecha_hasta">
                                </div>
                            </div>
                            <div class="presupuesto-group">
                                <div class="presupuesto-input">
                                    <label><i class="fas fa-dollar-sign"></i> Presupuesto (solo para viajes MEDIOS de BURBUJA)</label>
                                    <input type="number" name="presupuesto" id="presupuesto" step="1000" placeholder="Ej: 60000000">
                                </div>
                            </div>
                            <div class="btn-group-actions">
                                <button type="submit" name="tipo_informe" value="aleatorio" class="btn-generate-top" id="btnAleatorio">
                                    <i class="fas fa-random"></i> Generar Informe Aleatorio
                                </button>
                                <button type="submit" name="tipo_informe" value="real" class="btn-generate-real" id="btnReal">
                                    <i class="fas fa-database"></i> Generar Informe Real (Sin Asignación)
                                </button>
                                <a class="btn btn-secondary" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/index2.php" style="background: #6c757d; padding: 0.5rem 1rem; border-radius: 8px; color: white; text-decoration: none; white-space: nowrap; font-size: 0.85rem;">
                                    <i class="fas fa-home"></i> Inicio
                                </a>
                            </div>
                        </div>
                        
                        <div class="three-columns">
                            <!-- COLUMNA 1: CONDUCTORES (SOLO NECESARIO PARA INFORME ALEATORIO) -->
                            <div class="col-conductores">
                                <div class="section-card">
                                    <div class="section-header">
                                        <h3><i class="fas fa-users"></i> 👥 Seleccionar Conductores</h3>
                                    </div>
                                    <div class="section-content">
                                        <div class="search-box">
                                            <i class="fas fa-search"></i>
                                            <input type="text" id="buscadorConductores" placeholder="Buscar conductor por nombre...">
                                            <div class="result-counter" id="resultadoBusqueda"></div>
                                        </div>
                                        
                                        <div class="btn-group-custom">
                                            <button type="button" id="btnSeleccionarTodosCond" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-check-double"></i> Seleccionar todos
                                            </button>
                                            <button type="button" id="btnLimpiarTodosCond" class="btn btn-outline-secondary btn-sm">
                                                <i class="fas fa-trash-alt"></i> Limpiar todos
                                            </button>
                                        </div>
                                        
                                        <div class="conductor-list" id="listaConductores">
                                            <?php foreach($todosConductores as $index => $cond): 
                                                $esCarrotanque = strpos(strtolower($cond['tipo_vehiculo'] ?? ''), 'carrotanque') !== false;
                                                $tipoVehiculo = obtenerTipoVehiculo($cond['tipo_vehiculo']);
                                                $badgeClass = $esCarrotanque ? 'badge-carrotanque' : 'badge-burbuja';
                                                $badgeIcon = $esCarrotanque ? 'fa-truck' : 'fa-car';
                                            ?>
                                            <div class="conductor-item" data-nombre="<?= strtolower(htmlspecialchars($cond['nombre'])) ?>" data-nombre-original="<?= htmlspecialchars($cond['nombre']) ?>" data-es-carrotanque="<?= $esCarrotanque ? 'true' : 'false' ?>">
                                                <div class="form-check">
                                                    <input class="form-check-input conductor-checkbox" type="checkbox" 
                                                           name="conductores_seleccionados[]" value="<?= htmlspecialchars($cond['nombre']) ?>" 
                                                           id="conductor_<?= $index ?>"
                                                           data-nombre="<?= htmlspecialchars($cond['nombre']) ?>"
                                                           data-es-carrotanque="<?= $esCarrotanque ? 'true' : 'false' ?>">
                                                    <label class="form-check-label" for="conductor_<?= $index ?>" style="width: 100%;">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div style="flex: 1;">
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
                                                                <span class="badge bg-warning text-dark badge-unic">
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
                            
                            <!-- COLUMNA 2: RESUMEN CON LISTA DE NOMBRES -->
                            <div class="col-resumen">
                                <div class="section-card">
                                    <div class="section-header">
                                        <h3><i class="fas fa-list"></i> 📋 Conductores Seleccionados</h3>
                                    </div>
                                    <div class="section-content">
                                        <div id="resumenLista" class="resumen-lista">
                                            <div class="resumen-vacio">
                                                <i class="fas fa-info-circle"></i> No hay conductores seleccionados
                                            </div>
                                        </div>
                                        <div id="resumenTotal" class="resumen-total" style="display: none;">
                                            Total: <span id="totalSeleccionados">0</span> conductores
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- COLUMNA 3: EMPRESAS -->
                            <div class="col-empresas">
                                <div class="section-card">
                                    <div class="section-header">
                                        <h3><i class="fas fa-building"></i> 🏢 Empresas</h3>
                                    </div>
                                    <div class="section-content">
                                        <div class="select-all-container">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="seleccionarTodos">
                                                <label class="form-check-label" for="seleccionarTodos">
                                                    <strong><i class="fas fa-check-circle"></i> Seleccionar todas</strong>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="empresas-list">
                                            <?php if (empty($empresas)): ?>
                                                <p class="text-muted mb-0"><i class="fas fa-info-circle"></i> No hay empresas</p>
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
                                        <small class="text-muted mt-2 d-block" style="font-size: 0.65rem;">
                                            <i class="fas fa-info-circle"></i> Si no selecciona ninguna, se incluirán todas
                                        </small>
                                        
                                        <div class="legend">
                                            <div class="legend-item">
                                                <div class="legend-color" style="background: #fff3cd; border-left: 2px solid #ffc107;"></div>
                                                <span><i class="fas fa-truck"></i> Carrotanque: nombre real</span>
                                            </div>
                                            <div class="legend-item">
                                                <div class="legend-color" style="background: #d1ecf1; border-left: 2px solid #0dcaf0;"></div>
                                                <span><i class="fas fa-car"></i> Otros: distribución aleatoria</span>
                                            </div>
                                            <div class="legend-item">
                                                <div class="legend-color" style="background: #f8f9fa; border-left: 2px solid #198754;"></div>
                                                <span><i class="fas fa-random"></i> Máx 2 veces seguidas</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
            const buscador = document.getElementById('buscadorConductores');
            const conductoresItems = document.querySelectorAll('.conductor-item');
            const resultadoBusqueda = document.getElementById('resultadoBusqueda');
            const resumenLista = document.getElementById('resumenLista');
            const resumenTotal = document.getElementById('resumenTotal');
            const totalSeleccionadosSpan = document.getElementById('totalSeleccionados');
            const btnAleatorio = document.getElementById('btnAleatorio');
            const btnReal = document.getElementById('btnReal');
            const formInforme = document.getElementById('formInforme');
            
            function escapeHtml(text) {
                if (!text) return '';
                return text.replace(/[&<>]/g, function(m) {
                    if (m === '&') return '&amp;';
                    if (m === '<') return '&lt;';
                    if (m === '>') return '&gt;';
                    return m;
                });
            }
            
            function actualizarResumen() {
                const checkboxesSeleccionados = document.querySelectorAll('.conductor-checkbox:checked');
                const total = checkboxesSeleccionados.length;
                
                if (total === 0) {
                    resumenLista.innerHTML = `
                        <div class="resumen-vacio">
                            <i class="fas fa-info-circle"></i> No hay conductores seleccionados
                        </div>
                    `;
                    resumenTotal.style.display = 'none';
                    return;
                }
                
                let html = '';
                checkboxesSeleccionados.forEach(checkbox => {
                    const nombre = checkbox.getAttribute('data-nombre');
                    const esCarrotanque = checkbox.getAttribute('data-es-carrotanque') === 'true';
                    const tipoBadge = esCarrotanque ? 
                        '<span class="tipo-badge" style="background:#fff3cd; color:#856404;"><i class="fas fa-truck"></i> Carrotanque</span>' : 
                        '<span class="tipo-badge" style="background:#d1ecf1; color:#0c5460;"><i class="fas fa-car"></i> Otros</span>';
                    
                    html += `
                        <div class="conductor-seleccionado-item">
                            <i class="fas fa-check-circle" style="color: #198754;"></i>
                            <span class="nombre">${escapeHtml(nombre)}</span>
                            ${tipoBadge}
                        </div>
                    `;
                });
                
                resumenLista.innerHTML = html;
                totalSeleccionadosSpan.textContent = total;
                resumenTotal.style.display = 'block';
            }
            
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
                
                const total = conductoresItems.length;
                if (busqueda === '') {
                    resultadoBusqueda.innerHTML = `<i class="fas fa-users"></i> Mostrando ${contadorVisibles} de ${total} conductores`;
                } else {
                    resultadoBusqueda.innerHTML = `<i class="fas fa-search"></i> ${contadorVisibles} conductores que empiezan con "${buscador.value}"`;
                }
            }
            
            buscador.addEventListener('keyup', filtrarConductores);
            buscador.addEventListener('change', filtrarConductores);
            
            document.getElementById('btnSeleccionarTodosCond').addEventListener('click', function() {
                const itemsVisibles = document.querySelectorAll('.conductor-item[style=""]');
                itemsVisibles.forEach(item => {
                    const checkbox = item.querySelector('.conductor-checkbox');
                    if (checkbox) checkbox.checked = true;
                });
                actualizarResumen();
            });
            
            document.getElementById('btnLimpiarTodosCond').addEventListener('click', function() {
                document.querySelectorAll('.conductor-checkbox').forEach(checkbox => {
                    checkbox.checked = false;
                });
                actualizarResumen();
            });
            
            document.querySelectorAll('.conductor-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', actualizarResumen);
            });
            
            const seleccionarTodosEmpresas = document.getElementById('seleccionarTodos');
            const checkboxesEmpresa = document.querySelectorAll('.empresa-item-checkbox');
            
            function actualizarSeleccionarTodosEmpresas() {
                if (!seleccionarTodosEmpresas) return;
                const total = checkboxesEmpresa.length;
                const seleccionados = document.querySelectorAll('.empresa-item-checkbox:checked').length;
                
                if (seleccionados === 0) {
                    seleccionarTodosEmpresas.checked = false;
                    seleccionarTodosEmpresas.indeterminate = false;
                } else if (seleccionados === total) {
                    seleccionarTodosEmpresas.checked = true;
                    seleccionarTodosEmpresas.indeterminate = false;
                } else {
                    seleccionarTodosEmpresas.indeterminate = true;
                }
            }
            
            if (seleccionarTodosEmpresas) {
                seleccionarTodosEmpresas.addEventListener('change', function() {
                    checkboxesEmpresa.forEach(cb => cb.checked = seleccionarTodosEmpresas.checked);
                });
                checkboxesEmpresa.forEach(cb => cb.addEventListener('change', actualizarSeleccionarTodosEmpresas));
                actualizarSeleccionarTodosEmpresas();
            }
            
            // Validación según el tipo de informe
            formInforme.addEventListener('submit', function(e) {
                const tipoInforme = document.activeElement.getAttribute('name') === 'tipo_informe' ? 
                                    document.activeElement.value : null;
                
                // Si es informe aleatorio, validar que haya conductores seleccionados
                if (tipoInforme === 'aleatorio') {
                    const seleccionados = document.querySelectorAll('.conductor-checkbox:checked');
                    if (seleccionados.length === 0) {
                        e.preventDefault();
                        alert('⚠️ Para el INFORME ALEATORIO debe seleccionar al menos un conductor.');
                        return false;
                    }
                }
            });
            
            setTimeout(() => {
                filtrarConductores();
                actualizarResumen();
            }, 100);
            
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
        </script>
    </body>
    </html>
    <?php
    exit;
}

// ========== PROCESAMIENTO DEL INFORME (CUANDO SE ENVÍA EL FORMULARIO) ==========

// Parámetros
$desde = $_POST['desde'];
$hasta = $_POST['hasta'];
$tipoInforme = $_POST['tipo_informe'] ?? 'aleatorio';
$empresasSeleccionadas = $_POST['empresas'] ?? [];
$presupuesto = isset($_POST['presupuesto']) && $_POST['presupuesto'] !== '' ? floatval($_POST['presupuesto']) : null;

// Validaciones básicas
if (empty($desde) || empty($hasta)) {
    die("Error: Fechas no válidas");
}

// Normalizar fechas
$desdeIni = $conn->real_escape_string($desde . " 00:00:00");
$hastaFin = $conn->real_escape_string($hasta . " 23:59:59");

// Condición empresas
$condicionEmpresa = "";
if (!empty($empresasSeleccionadas)) {
    $empresasEscapadas = array_map(function($emp) use ($conn) {
        return "'" . $conn->real_escape_string($emp) . "'";
    }, $empresasSeleccionadas);
    $condicionEmpresa = " AND v.empresa IN (" . implode(",", $empresasEscapadas) . ")";
}

// Consulta de viajes
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
    die("Error en consulta viajes: " . $conn->error);
}

// ========== PROCESAR SEGÚN TIPO DE INFORME ==========

if ($tipoInforme === 'real') {
    // INFORME REAL: Mostrar los conductores tal como están en la base de datos
    $viajesPorCategoria = [
        'carrotanque' => [],
        'camion_350' => [],
        'burbuja' => [],
        'copetrana' => [],
        'otros' => []
    ];
    $totalesPorCategoria = [
        'carrotanque' => 0,
        'camion_350' => 0,
        'burbuja' => 0,
        'copetrana' => 0,
        'otros' => 0
    ];
    
    while ($row = $resViajes->fetch_assoc()) {
        $categoria = obtenerCategoriaVehiculo($row['tipo_vehiculo']);
        $valor = $row['valor_viaje'];
        
        if ($valor !== null && $valor > 0) {
            $totalesPorCategoria[$categoria] += floatval($valor);
        }
        
        $viajesPorCategoria[$categoria][] = [
            'fecha' => $row['fecha'],
            'conductor' => $row['conductor_real'],
            'tipo_vehiculo' => $row['tipo_vehiculo'],
            'ruta' => $row['ruta'],
            'valor' => $valor,
            'clasificacion' => $row['clasificacion'],
            'es_carrotanque' => stripos($row['tipo_vehiculo'], 'carrotanque') !== false
        ];
    }
    
    // Obtener cédulas para los conductores reales
    $conductoresInfoReal = [];
    $todosConductoresReales = [];
    foreach ($viajesPorCategoria as $categoria => $viajes) {
        foreach ($viajes as $viaje) {
            $todosConductoresReales[] = $viaje['conductor'];
        }
    }
    $todosConductoresReales = array_unique($todosConductoresReales);
    
    foreach ($todosConductoresReales as $conductorNombre) {
        $nombreEscapado = $conn->real_escape_string($conductorNombre);
        $sqlInfo = "SELECT DISTINCT nombre, cedula, tipo_vehiculo FROM viajes WHERE nombre = '$nombreEscapado' LIMIT 1";
        $resInfo = $conn->query($sqlInfo);
        if ($resInfo && $row = $resInfo->fetch_assoc()) {
            $conductoresInfoReal[$conductorNombre] = $row;
        } else {
            $conductoresInfoReal[$conductorNombre] = ['nombre' => $conductorNombre, 'cedula' => 'N/A', 'tipo_vehiculo' => ''];
        }
    }
    
} else {
    // INFORME ALEATORIO
    $conductoresSeleccionados = $_POST['conductores_seleccionados'] ?? [];
    
    if (empty($conductoresSeleccionados)) {
        die("Error: Para el INFORME ALEATORIO debe seleccionar al menos un conductor.");
    }
    
    // Identificar conductores carrotanque
    $conductoresCarrotanque = [];
    foreach ($conductoresSeleccionados as $conductor) {
        if (esConductorCarrotanque($conn, $conductor)) {
            $conductoresCarrotanque[] = $conductor;
        }
    }
    
    // Obtener información de conductores
    $conductoresInfo = [];
    foreach ($conductoresSeleccionados as $conductorNombre) {
        $nombreEscapado = $conn->real_escape_string($conductorNombre);
        $sqlConductorInfo = "SELECT DISTINCT nombre, cedula, tipo_vehiculo FROM viajes WHERE nombre = '$nombreEscapado' LIMIT 1";
        $resInfo = $conn->query($sqlConductorInfo);
        if ($resInfo && $row = $resInfo->fetch_assoc()) {
            $conductoresInfo[] = $row;
        } else {
            $conductoresInfo[] = ['nombre' => $conductorNombre, 'cedula' => 'N/A', 'tipo_vehiculo' => ''];
        }
    }
    
    // Recolectar todos los viajes
    $todosLosViajes = [];
    while ($row = $resViajes->fetch_assoc()) {
        $todosLosViajes[] = $row;
    }
    
    // Clasificar viajes por tipo de vehículo
    $viajesCarrotanque = [];
    $viajesCamion350 = [];
    $viajesBurbuja = [];
    $viajesCopetrana = [];
    $viajesOtrosVehiculos = [];
    
    // Separar viajes MEDIOS de BURBUJA para el presupuesto
    $viajesMediosBurbuja = [];
    $todosLosViajesParaClasificar = [];
    
    foreach ($todosLosViajes as $viaje) {
        $categoriaVehiculo = obtenerCategoriaVehiculo($viaje['tipo_vehiculo']);
        $clasificacion = strtolower(trim($viaje['clasificacion'] ?? ''));
        $esBurbuja = ($categoriaVehiculo === 'burbuja');
        
        // Guardar el viaje original para referencia
        $todosLosViajesParaClasificar[] = $viaje;
        
        // Agrupar por tipo de vehículo (todos los viajes)
        switch ($categoriaVehiculo) {
            case 'carrotanque':
                $viajesCarrotanque[] = $viaje;
                break;
            case 'camion_350':
                $viajesCamion350[] = $viaje;
                break;
            case 'burbuja':
                $viajesBurbuja[] = $viaje;
                break;
            case 'copetrana':
                $viajesCopetrana[] = $viaje;
                break;
            default:
                $viajesOtrosVehiculos[] = $viaje;
                break;
        }
        
        // Separar SOLO los viajes MEDIOS de BURBUJA para el presupuesto
        if ($esBurbuja && $clasificacion === 'medio') {
            $viajesMediosBurbuja[] = $viaje;
        }
    }
    
    // ========== PROCESAR VIAJES MEDIOS DE BURBUJA CON PRESUPUESTO ==========
    $viajesMediosBurbujaCubiertos = [];   // Viajes medios de burbuja que entran en el presupuesto
    $idsViajesCubiertos = [];              // IDs para evitar duplicados
    $acumuladoMedios = 0;
    $presupuestoUsado = 0;
    $presupuestoIngresado = $presupuesto ?? 0;
    $sobrantePresupuesto = 0;
    $faltaPresupuesto = 0;
    
    if ($presupuestoIngresado > 0 && !empty($viajesMediosBurbuja)) {
        // Crear un array con los viajes medios de burbuja en orden cronológico
        $viajesMediosBurbujaOrdenados = $viajesMediosBurbuja;
        
        foreach ($viajesMediosBurbujaOrdenados as $index => $viaje) {
            $valorViaje = floatval($viaje['valor_viaje'] ?? 0);
            
            if ($acumuladoMedios < $presupuestoIngresado) {
                // Este viaje entra en el presupuesto
                $viajesMediosBurbujaCubiertos[] = $viaje;
                // Guardar un identificador único para este viaje (podemos usar fecha + ruta + valor como clave)
                $idViaje = md5($viaje['fecha'] . $viaje['ruta'] . $viaje['valor_viaje'] . $viaje['tipo_vehiculo']);
                $idsViajesCubiertos[] = $idViaje;
                $acumuladoMedios += $valorViaje;
                $presupuestoUsado += $valorViaje;
            }
        }
        
        // Calcular sobrante o faltante
        if ($acumuladoMedios > $presupuestoIngresado) {
            $sobrantePresupuesto = $acumuladoMedios - $presupuestoIngresado;
        } elseif ($acumuladoMedios < $presupuestoIngresado) {
            $faltaPresupuesto = $presupuestoIngresado - $acumuladoMedios;
        }
    }
    
    // ========== CONSTRUIR TABLA DE BURBUJA EXCLUYENDO LOS VIAJES QUE YA ESTÁN EN EL PRESUPUESTO ==========
    $viajesBurbujaFiltrados = [];
    
    foreach ($viajesBurbuja as $viaje) {
        $clasificacion = strtolower(trim($viaje['clasificacion'] ?? ''));
        $esMedio = ($clasificacion === 'medio');
        
        // Crear identificador único para este viaje
        $idViaje = md5($viaje['fecha'] . $viaje['ruta'] . $viaje['valor_viaje'] . $viaje['tipo_vehiculo']);
        
        // Si es un viaje MEDIO de burbuja y está en la lista de cubiertos, NO lo incluimos en la tabla de burbuja
        if ($esMedio && in_array($idViaje, $idsViajesCubiertos)) {
            continue;
        }
        
        // Para todos los demás casos (completos, extra, siapana, o medios que NO entraron en presupuesto), los incluimos
        $viajesBurbujaFiltrados[] = $viaje;
    }
    
    // ========== ASIGNAR CONDUCTORES ==========
    $ultimoConductor = null;
    $consecutivos = 0;
    $conductoresNoCarrotanque = array_diff($conductoresSeleccionados, $conductoresCarrotanque);
    
    // Función para asignar conductor a un viaje
    function asignarConductorParaViaje($viaje, $conductoresNoCarrotanque, $conductoresCarrotanque, &$ultimoConductor, &$consecutivos, $conductoresSeleccionados) {
        $tipoVehiculo = strtolower(trim($viaje['tipo_vehiculo'] ?? ''));
        $esViajeCarrotanque = strpos($tipoVehiculo, 'carrotanque') !== false;
        
        if ($esViajeCarrotanque) {
            return $viaje['conductor_real'];
        }
        
        if (empty($conductoresNoCarrotanque)) {
            $conductoresParaAsignar = $conductoresSeleccionados;
        } else {
            $conductoresParaAsignar = $conductoresNoCarrotanque;
        }
        
        if (count($conductoresParaAsignar) == 1) {
            return $conductoresParaAsignar[0];
        }
        
        $conductorAsignado = asignarConductorConRegla($conductoresParaAsignar, $ultimoConductor, $consecutivos);
        
        if ($conductorAsignado == $ultimoConductor) {
            $consecutivos++;
        } else {
            $consecutivos = 1;
        }
        $ultimoConductor = $conductorAsignado;
        
        return $conductorAsignado;
    }
    
    function procesarViajesConConductores($viajes, $conductoresNoCarrotanque, $conductoresCarrotanque, &$ultimoConductor, &$consecutivos, $conductoresSeleccionados, $conductoresInfo) {
        $resultado = [];
        foreach ($viajes as $viaje) {
            $conductorAsignado = asignarConductorParaViaje($viaje, $conductoresNoCarrotanque, $conductoresCarrotanque, $ultimoConductor, $consecutivos, $conductoresSeleccionados);
            
            $conductorInfo = null;
            foreach ($conductoresInfo as $info) {
                if ($info['nombre'] == $conductorAsignado) {
                    $conductorInfo = $info;
                    break;
                }
            }
            
            $resultado[] = [
                'fecha' => $viaje['fecha'],
                'conductor' => $conductorAsignado,
                'cedula' => $conductorInfo ? $conductorInfo['cedula'] : 'N/A',
                'tipo_vehiculo' => $viaje['tipo_vehiculo'],
                'ruta' => $viaje['ruta'],
                'valor' => $viaje['valor_viaje'],
                'clasificacion' => $viaje['clasificacion'],
                'es_carrotanque' => stripos($viaje['tipo_vehiculo'], 'carrotanque') !== false,
                'empresa' => $viaje['empresa']
            ];
        }
        return $resultado;
    }
    
    // Procesar cada grupo con asignación de conductores
    $ultimoConductor = null;
    $consecutivos = 0;
    $viajesMediosBurbujaCubiertosAsignados = procesarViajesConConductores($viajesMediosBurbujaCubiertos, $conductoresNoCarrotanque, $conductoresCarrotanque, $ultimoConductor, $consecutivos, $conductoresSeleccionados, $conductoresInfo);
    
    $ultimoConductor = null;
    $consecutivos = 0;
    $viajesCarrotanqueAsignados = procesarViajesConConductores($viajesCarrotanque, $conductoresNoCarrotanque, $conductoresCarrotanque, $ultimoConductor, $consecutivos, $conductoresSeleccionados, $conductoresInfo);
    
    $ultimoConductor = null;
    $consecutivos = 0;
    $viajesCamion350Asignados = procesarViajesConConductores($viajesCamion350, $conductoresNoCarrotanque, $conductoresCarrotanque, $ultimoConductor, $consecutivos, $conductoresSeleccionados, $conductoresInfo);
    
    $ultimoConductor = null;
    $consecutivos = 0;
    $viajesBurbujaFiltradosAsignados = procesarViajesConConductores($viajesBurbujaFiltrados, $conductoresNoCarrotanque, $conductoresCarrotanque, $ultimoConductor, $consecutivos, $conductoresSeleccionados, $conductoresInfo);
    
    $ultimoConductor = null;
    $consecutivos = 0;
    $viajesCopetranaAsignados = procesarViajesConConductores($viajesCopetrana, $conductoresNoCarrotanque, $conductoresCarrotanque, $ultimoConductor, $consecutivos, $conductoresSeleccionados, $conductoresInfo);
    
    $ultimoConductor = null;
    $consecutivos = 0;
    $viajesOtrosAsignados = procesarViajesConConductores($viajesOtrosVehiculos, $conductoresNoCarrotanque, $conductoresCarrotanque, $ultimoConductor, $consecutivos, $conductoresSeleccionados, $conductoresInfo);
    
    // Calcular totales
    $totalCarrotanque = array_sum(array_column($viajesCarrotanqueAsignados, 'valor'));
    $totalCamion350 = array_sum(array_column($viajesCamion350Asignados, 'valor'));
    $totalBurbuja = array_sum(array_column($viajesBurbujaFiltradosAsignados, 'valor'));
    $totalCopetrana = array_sum(array_column($viajesCopetranaAsignados, 'valor'));
    $totalOtros = array_sum(array_column($viajesOtrosAsignados, 'valor'));
    $totalMediosBurbujaCubiertos = array_sum(array_column($viajesMediosBurbujaCubiertosAsignados, 'valor'));
    $totalGeneral = $totalCarrotanque + $totalCamion350 + $totalBurbuja + $totalCopetrana + $totalOtros + $totalMediosBurbujaCubiertos;
}

// ========== GENERAR DOCUMENTO WORD ==========
$phpWord = new PhpWord();
$section = $phpWord->addSection();

// Configurar márgenes
$section->getStyle()->setMarginTop(720);
$section->getStyle()->setMarginBottom(720);
$section->getStyle()->setMarginLeft(720);
$section->getStyle()->setMarginRight(720);

// Título principal
$section->addText("INFORME DE VIAJES POR TIPO DE VEHÍCULO", ['bold' => true, 'size' => 16, 'color' => '1F4E78'], ['align' => 'center']);
$section->addTextBreak(0.5);

// Subtítulo
$section->addText("SEGÚN ACTA DE INICIO AL CONTRATO DE PRESTACIÓN DE SERVICIOS NO. 1313-2025 SUSCRITO POR LA E.S.E. HOSPITAL SAN JOSÉ DE MAICAO Y LA ASOCIACIÓN DE TRANSPORTISTAS ZONA NORTE EXTREMA WUINPUMUÍN.", 
    ['italic' => true, 'size' => 9, 'color' => '666666'], ['align' => 'center']);
$section->addText("OBJETO: TRASLADO DE PERSONAL ASISTENCIAL – SEDE NAZARETH.", 
    ['italic' => true, 'size' => 9, 'color' => '666666'], ['align' => 'center']);
$section->addTextBreak(1);

// Información del periodo
$section->addText("Período: " . date('d/m/Y', strtotime($desde)) . " al " . date('d/m/Y', strtotime($hasta)), ['bold' => true, 'size' => 10]);
if (!empty($empresasSeleccionadas)) {
    $section->addText("Empresas seleccionadas: " . implode(", ", $empresasSeleccionadas), ['size' => 10]);
} else {
    $section->addText("Empresas: TODAS", ['size' => 10]);
}

if ($tipoInforme === 'aleatorio') {
    $section->addText("Conductores en informe: " . implode(", ", $conductoresSeleccionados), ['size' => 10]);
    if (!empty($conductoresCarrotanque)) {
        $section->addText("⚠️ Conductores de Carrotanque (respetan su nombre real): " . implode(", ", $conductoresCarrotanque), ['italic' => true, 'size' => 9, 'color' => 'CC6600']);
    }
    $section->addText("Tipo de informe: DISTRIBUCIÓN ALEATORIA (máx 2 veces seguidas)", ['bold' => true, 'size' => 10, 'color' => '008000']);
    
    if ($presupuestoIngresado > 0) {
        $section->addText("PRESUPUESTO ASIGNADO EXCLUSIVAMENTE PARA VIAJES MEDIOS DE BURBUJA: " . formatearMoneda($presupuestoIngresado), ['bold' => true, 'size' => 10, 'color' => 'CC6600']);
        $section->addText("Total acumulado en viajes MEDIOS de BURBUJA que entran en presupuesto: " . formatearMoneda($presupuestoUsado), ['size' => 10]);
        if ($sobrantePresupuesto > 0) {
            $section->addText("⚠️ Sobrante después del último viaje MEDIO de BURBUJA incluido: " . formatearMoneda($sobrantePresupuesto), ['italic' => true, 'size' => 9, 'color' => '0066CC']);
        } elseif ($faltaPresupuesto > 0) {
            $section->addText("⚠️ Faltante para alcanzar el presupuesto con viajes MEDIOS de BURBUJA: " . formatearMoneda($faltaPresupuesto), ['italic' => true, 'size' => 9, 'color' => 'CC0000']);
        }
        $section->addText("NOTA: Los demás vehículos (Carrotanque, Camión 350, Copetrana, Otros) se muestran COMPLETOS sin afectar el presupuesto. Los viajes de BURBUJA que NO son medios o que son medios pero NO entraron en presupuesto se muestran en su tabla correspondiente.", ['italic' => true, 'size' => 9, 'color' => '666666']);
    }
} else {
    $section->addText("Tipo de informe: DATOS REALES (sin asignación)", ['bold' => true, 'size' => 10, 'color' => '0000FF']);
    $section->addText("Los conductores mostrados son los que realmente realizaron cada viaje según la base de datos.", ['italic' => true, 'size' => 9]);
}
$section->addTextBreak(1);

// ========== FUNCIÓN PARA CREAR TABLA DE VIAJES ==========
function crearTablaViajes($section, $titulo, $viajes, $subtotal, $mostrarConductor = true, $mostrarCedula = false) {
    if (empty($viajes)) {
        return;
    }
    
    $section->addText($titulo, ['bold' => true, 'size' => 12, 'color' => '1F4E78']);
    $section->addTextBreak(0.5);
    
    $table = $section->addTable(['borderSize' => 1, 'borderColor' => 'AAAAAA', 'cellMargin' => 60, 'width' => 100 * 50]);
    
    // Encabezados
    $table->addRow();
    $table->addCell(1200)->addText("FECHA", ['bold' => true, 'size' => 9, 'align' => 'center']);
    if ($mostrarConductor) {
        $table->addCell(2500)->addText("CONDUCTOR", ['bold' => true, 'size' => 9, 'align' => 'center']);
    }
    if ($mostrarCedula) {
        $table->addCell(2000)->addText("CÉDULA", ['bold' => true, 'size' => 9, 'align' => 'center']);
    }
    $table->addCell(2500)->addText("VEHÍCULO", ['bold' => true, 'size' => 9, 'align' => 'center']);
    $table->addCell(3000)->addText("RUTA", ['bold' => true, 'size' => 9, 'align' => 'center']);
    $table->addCell(2000)->addText("VALOR", ['bold' => true, 'size' => 9, 'align' => 'center']);
    
    foreach ($viajes as $viaje) {
        $valor = floatval($viaje['valor'] ?? 0);
        
        $table->addRow();
        $table->addCell(1200)->addText(date('d/m/Y', strtotime($viaje['fecha'])), ['size' => 9]);
        if ($mostrarConductor) {
            $textoConductor = $viaje['conductor'] ?: '-';
            if (!empty($viaje['es_carrotanque'])) {
                $textoConductor .= " 🚛";
            }
            $table->addCell(2500)->addText($textoConductor, ['size' => 9]);
        }
        if ($mostrarCedula) {
            $table->addCell(2000)->addText($viaje['cedula'] ?? 'N/A', ['size' => 9]);
        }
        $table->addCell(2500)->addText(obtenerTipoVehiculo($viaje['tipo_vehiculo']), ['size' => 9]);
        $table->addCell(3000)->addText($viaje['ruta'] ?: '-', ['size' => 9]);
        
        if ($valor > 0) {
            $table->addCell(2000)->addText(formatearMoneda($valor), ['size' => 9, 'align' => 'right']);
        } else {
            $textoValor = "N/A";
            if (!empty($viaje['clasificacion'])) {
                $textoValor = "Sin tarifa (" . $viaje['clasificacion'] . ")";
            }
            $table->addCell(2000)->addText($textoValor, ['size' => 9, 'align' => 'right']);
        }
    }
    
    // Fila de subtotal
    $table->addRow();
    $colspan = 3 + ($mostrarConductor ? 1 : 0) + ($mostrarCedula ? 1 : 0);
    $cellSubtotal = $table->addCell(($colspan * 1000), ['gridSpan' => $colspan]);
    $cellSubtotal->addText("SUBTOTAL", ['bold' => true, 'size' => 9, 'align' => 'right']);
    $table->addCell(2000)->addText(formatearMoneda($subtotal), ['bold' => true, 'size' => 9, 'align' => 'right']);
    
    $section->addTextBreak(1);
}

// ========== GENERAR TABLAS SEGÚN TIPO DE INFORME ==========

if ($tipoInforme === 'real') {
    // INFORME REAL: Mostrar tablas por categoría de vehículo
    $categorias = [
        'carrotanque' => ['titulo' => '🚛 VEHÍCULOS TIPO CARROTANQUE', 'icono' => '🚛'],
        'camion_350' => ['titulo' => '🚚 VEHÍCULOS TIPO CAMIÓN 350', 'icono' => '🚚'],
        'burbuja' => ['titulo' => '🚙 VEHÍCULOS TIPO BURBUJA', 'icono' => '🚙'],
        'copetrana' => ['titulo' => '🚐 VEHÍCULOS TIPO COPETRANA', 'icono' => '🚐'],
        'otros' => ['titulo' => '🔧 OTROS VEHÍCULOS', 'icono' => '🔧']
    ];
    
    foreach ($categorias as $categoria => $info) {
        if (!empty($viajesPorCategoria[$categoria])) {
            $viajesConCedula = [];
            foreach ($viajesPorCategoria[$categoria] as $viaje) {
                $cedula = isset($conductoresInfoReal[$viaje['conductor']]) ? $conductoresInfoReal[$viaje['conductor']]['cedula'] : 'N/A';
                $viajesConCedula[] = [
                    'fecha' => $viaje['fecha'],
                    'conductor' => $viaje['conductor'],
                    'cedula' => $cedula,
                    'tipo_vehiculo' => $viaje['tipo_vehiculo'],
                    'ruta' => $viaje['ruta'],
                    'valor' => $viaje['valor'],
                    'clasificacion' => $viaje['clasificacion'],
                    'es_carrotanque' => $viaje['es_carrotanque']
                ];
            }
            crearTablaViajes($section, $info['titulo'], $viajesConCedula, $totalesPorCategoria[$categoria], true, true);
        }
    }
    
    // Total general
    $section->addTextBreak(0.5);
    $section->addText("RESUMEN GENERAL POR TIPO DE VEHÍCULO", ['bold' => true, 'size' => 12, 'color' => '1F4E78']);
    $section->addTextBreak(0.5);
    
    $tableTotal = $section->addTable(['borderSize' => 1, 'borderColor' => 'AAAAAA', 'cellMargin' => 60]);
    $tableTotal->addRow();
    $tableTotal->addCell(4000)->addText("TIPO DE VEHÍCULO", ['bold' => true]);
    $tableTotal->addCell(2500)->addText("TOTAL", ['bold' => true, 'align' => 'right']);
    
    $totalGeneral = 0;
    foreach ($categorias as $categoria => $info) {
        if ($totalesPorCategoria[$categoria] > 0) {
            $tableTotal->addRow();
            $tableTotal->addCell(4000)->addText($info['titulo']);
            $tableTotal->addCell(2500)->addText(formatearMoneda($totalesPorCategoria[$categoria]), ['align' => 'right']);
            $totalGeneral += $totalesPorCategoria[$categoria];
        }
    }
    
    $tableTotal->addRow();
    $cellTotal = $tableTotal->addCell(4000);
    $cellTotal->addText("TOTAL GENERAL", ['bold' => true]);
    $tableTotal->addCell(2500)->addText(formatearMoneda($totalGeneral), ['bold' => true, 'align' => 'right', 'color' => 'CC0000']);
    
} else {
    // INFORME ALEATORIO: Mostrar todas las tablas
    
    // Tabla 1: Viajes medios de BURBUJA que entran en el presupuesto
    if (!empty($viajesMediosBurbujaCubiertosAsignados)) {
        $titulo = "📊 VIAJES MEDIOS DE BURBUJA QUE ENTRAN EN EL PRESUPUESTO";
        if ($presupuestoIngresado > 0) {
            $titulo .= " (Acumulado: " . formatearMoneda($acumuladoMedios) . ")";
            if ($sobrantePresupuesto > 0) {
                $titulo .= " - Sobrante: " . formatearMoneda($sobrantePresupuesto);
            }
        }
        crearTablaViajes($section, $titulo, $viajesMediosBurbujaCubiertosAsignados, $totalMediosBurbujaCubiertos, true, true);
    } elseif ($presupuestoIngresado > 0 && empty($viajesMediosBurbujaCubiertosAsignados) && !empty($viajesMediosBurbuja)) {
        $section->addText("📊 VIAJES MEDIOS DE BURBUJA", ['bold' => true, 'size' => 12, 'color' => '1F4E78']);
        $section->addText("No hay viajes medios de burbuja disponibles en el rango de fechas seleccionado.", ['italic' => true, 'size' => 10, 'color' => 'CC0000']);
        $section->addTextBreak(1);
    }
    
    // Tabla 2: Carrotanque - TODOS sus viajes
    if (!empty($viajesCarrotanqueAsignados)) {
        crearTablaViajes($section, "🚛 VEHÍCULOS TIPO CARROTANQUE (TODOS LOS VIAJES)", $viajesCarrotanqueAsignados, $totalCarrotanque, true, true);
    }
    
    // Tabla 3: Camión 350 - TODOS sus viajes
    if (!empty($viajesCamion350Asignados)) {
        crearTablaViajes($section, "🚚 VEHÍCULOS TIPO CAMIÓN 350 (TODOS LOS VIAJES)", $viajesCamion350Asignados, $totalCamion350, true, true);
    }
    
    // Tabla 4: Burbuja - Viajes que NO están en el presupuesto (completos, extra, siapana, y medios sobrantes)
    if (!empty($viajesBurbujaFiltradosAsignados)) {
        $titulo = "🚙 VEHÍCULOS TIPO BURBUJA";
        if ($presupuestoIngresado > 0) {
            $titulo .= " (Viajes que NO están en el presupuesto: completos, extra, siapana, y medios sobrantes)";
        }
        crearTablaViajes($section, $titulo, $viajesBurbujaFiltradosAsignados, $totalBurbuja, true, true);
    }
    
    // Tabla 5: Copetrana - TODOS sus viajes
    if (!empty($viajesCopetranaAsignados)) {
        crearTablaViajes($section, "🚐 VEHÍCULOS TIPO COPETRANA (TODOS LOS VIAJES)", $viajesCopetranaAsignados, $totalCopetrana, true, true);
    }
    
    // Tabla 6: Otros vehículos - TODOS sus viajes
    if (!empty($viajesOtrosAsignados)) {
        crearTablaViajes($section, "🔧 OTROS VEHÍCULOS (TODOS LOS VIAJES)", $viajesOtrosAsignados, $totalOtros, true, true);
    }
    
    // Resumen General con Totales
    $section->addTextBreak(0.5);
    $section->addText("RESUMEN GENERAL DE TOTALES", ['bold' => true, 'size' => 12, 'color' => '1F4E78']);
    $section->addTextBreak(0.5);
    
    $tableResumen = $section->addTable(['borderSize' => 1, 'borderColor' => 'AAAAAA', 'cellMargin' => 60]);
    $tableResumen->addRow();
    $tableResumen->addCell(5000)->addText("CONCEPTO", ['bold' => true]);
    $tableResumen->addCell(2500)->addText("TOTAL", ['bold' => true, 'align' => 'right']);
    
    if ($totalMediosBurbujaCubiertos > 0) {
        $tableResumen->addRow();
        $tableResumen->addCell(5000)->addText("Viajes Medios de Burbuja (Entran en Presupuesto)");
        $tableResumen->addCell(2500)->addText(formatearMoneda($totalMediosBurbujaCubiertos), ['align' => 'right']);
    }
    
    if ($totalCarrotanque > 0) {
        $tableResumen->addRow();
        $tableResumen->addCell(5000)->addText("Carrotanque (Todos los viajes)");
        $tableResumen->addCell(2500)->addText(formatearMoneda($totalCarrotanque), ['align' => 'right']);
    }
    
    if ($totalCamion350 > 0) {
        $tableResumen->addRow();
        $tableResumen->addCell(5000)->addText("Camión 350 (Todos los viajes)");
        $tableResumen->addCell(2500)->addText(formatearMoneda($totalCamion350), ['align' => 'right']);
    }
    
    if ($totalBurbuja > 0) {
        $tableResumen->addRow();
        $tableResumen->addCell(5000)->addText("Burbuja (Viajes NO incluidos en presupuesto)");
        $tableResumen->addCell(2500)->addText(formatearMoneda($totalBurbuja), ['align' => 'right']);
    }
    
    if ($totalCopetrana > 0) {
        $tableResumen->addRow();
        $tableResumen->addCell(5000)->addText("Copetrana (Todos los viajes)");
        $tableResumen->addCell(2500)->addText(formatearMoneda($totalCopetrana), ['align' => 'right']);
    }
    
    if ($totalOtros > 0) {
        $tableResumen->addRow();
        $tableResumen->addCell(5000)->addText("Otros Vehículos (Todos los viajes)");
        $tableResumen->addCell(2500)->addText(formatearMoneda($totalOtros), ['align' => 'right']);
    }
    
    $tableResumen->addRow();
    $cellTotal = $tableResumen->addCell(5000);
    $cellTotal->addText("TOTAL GENERAL", ['bold' => true]);
    $tableResumen->addCell(2500)->addText(formatearMoneda($totalGeneral), ['bold' => true, 'align' => 'right', 'color' => 'CC0000']);
    
    if ($presupuestoIngresado > 0) {
        $section->addTextBreak(0.5);
        $section->addText("NOTA DE PRESUPUESTO (SOLO PARA VIAJES MEDIOS DE BURBUJA):", ['bold' => true, 'size' => 10, 'color' => 'CC6600']);
        $section->addText("• Presupuesto ingresado para viajes MEDIOS de BURBUJA: " . formatearMoneda($presupuestoIngresado), ['size' => 9]);
        $section->addText("• Total utilizado en viajes MEDIOS de BURBUJA que entran: " . formatearMoneda($presupuestoUsado), ['size' => 9]);
        if ($sobrantePresupuesto > 0) {
            $section->addText("• Sobrante después del último viaje MEDIO de BURBUJA incluido: " . formatearMoneda($sobrantePresupuesto), ['size' => 9, 'color' => '0066CC']);
            $section->addText("• NOTA: El presupuesto se superó con el último viaje MEDIO de BURBUJA incluido.", ['italic' => true, 'size' => 9, 'color' => '0066CC']);
        } elseif ($faltaPresupuesto > 0) {
            $section->addText("• Faltante para alcanzar el presupuesto: " . formatearMoneda($faltaPresupuesto), ['size' => 9, 'color' => 'CC0000']);
            $section->addText("• NOTA: Los viajes MEDIOS de BURBUJA disponibles NO alcanzaron para cubrir el presupuesto.", ['italic' => true, 'size' => 9, 'color' => 'CC0000']);
        }
        $section->addText("• Los demás vehículos (Carrotanque, Camión 350, Copetrana, Otros) se muestran COMPLETOS y se cobran en su totalidad.", ['italic' => true, 'size' => 9, 'color' => '666666']);
        $section->addText("• Los viajes de BURBUJA que NO son medios, o que son medios pero NO entraron en presupuesto, se muestran en la tabla de Burbuja.", ['italic' => true, 'size' => 9, 'color' => '666666']);
    }
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

// Enviar archivo
$sufijo = ($tipoInforme === 'real') ? 'real' : 'aleatorio';
$filename = "informe_viajes_{$sufijo}_{$desde}_a_{$hasta}.docx";
header("Content-Description: File Transfer");
header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: public");

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save('php://output');
exit;
?>