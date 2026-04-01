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

// Función para obtener la clasificación base del viaje
function obtenerClasificacionBase($clasificacion) {
    $clasificacion = strtolower(trim($clasificacion ?: ''));
    
    if (strpos($clasificacion, 'completo') !== false) {
        return 'completo';
    } elseif (strpos($clasificacion, 'medio') !== false) {
        return 'medio';
    } elseif (strpos($clasificacion, 'extra') !== false) {
        return 'extra';
    } elseif (strpos($clasificacion, 'carrotanque') !== false) {
        return 'carrotanque';
    } else {
        return 'otros';
    }
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
            
            .four-columns {
                display: flex;
                gap: 1rem;
                flex-wrap: wrap;
            }
            
            .col-conductores {
                flex: 2;
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
            
            .col-presupuesto {
                flex: 1;
                min-width: 220px;
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
            
            .presupuesto-item {
                margin-bottom: 1rem;
                padding: 0.5rem;
                background: var(--light-color);
                border-radius: 8px;
            }
            
            .presupuesto-item label {
                font-size: 0.7rem;
                font-weight: 600;
                margin-bottom: 0.25rem;
                display: block;
            }
            
            .presupuesto-input {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }
            
            .input-group-text {
                font-size: 0.7rem;
                padding: 0.25rem 0.5rem;
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
                .four-columns {
                    flex-direction: column;
                }
                
                .col-conductores, .col-resumen, .col-empresas, .col-presupuesto {
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
            
            .presupuesto-note {
                font-size: 0.65rem;
                color: var(--secondary-color);
                margin-top: 0.5rem;
                text-align: center;
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
                        <strong>Informe Real:</strong> Muestra los conductores tal como están en la base de datos.
                        <br>
                        <i class="fas fa-random"></i> 
                        <strong>Informe Aleatorio:</strong> Distribuye los viajes entre los conductores seleccionados (máx 2 veces seguidas).
                        <br>
                        <i class="fas fa-chart-line"></i>
                        <strong>Presupuesto:</strong> Si ingresas un presupuesto para HOSPITAL o VE CAMPA, se generarán tablas separadas hasta alcanzar el valor.
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
                        
                        <div class="four-columns">
                            <!-- COLUMNA 1: CONDUCTORES -->
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
                            
                            <!-- COLUMNA 2: RESUMEN CONDUCTORES -->
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
                                    </div>
                                </div>
                            </div>
                            
                            <!-- COLUMNA 4: PRESUPUESTO POR EMPRESA -->
                            <div class="col-presupuesto">
                                <div class="section-card">
                                    <div class="section-header">
                                        <h3><i class="fas fa-chart-line"></i> 💰 Presupuesto por Empresa</h3>
                                    </div>
                                    <div class="section-content">
                                        <div class="presupuesto-item" data-empresa="hospital">
                                            <label><i class="fas fa-hospital"></i> HOSPITAL SAN JOSÉ DE MAICAO E.S.E</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control presupuesto-input" 
                                                       name="presupuesto_hospital" id="presupuesto_hospital"
                                                       placeholder="Ingrese presupuesto" step="1000" value="0">
                                            </div>
                                        </div>
                                        <div class="presupuesto-item" data-empresa="ve_campa">
                                            <label><i class="fas fa-tractor"></i> VE CAMPA MYCOW S.A.S</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control presupuesto-input" 
                                                       name="presupuesto_ve_campa" id="presupuesto_ve_campa"
                                                       placeholder="Ingrese presupuesto" step="1000" value="0">
                                            </div>
                                        </div>
                                        <div class="presupuesto-note">
                                            <i class="fas fa-info-circle"></i> 
                                            Si ingresa un presupuesto, los viajes se ordenarán por fecha y se generarán tablas hasta alcanzar el valor.
                                            <br>
                                            <strong>Los viajes que excedan el presupuesto irán a una tabla de "Sobrantes".</strong>
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

// Obtener presupuestos
$presupuestoHospital = isset($_POST['presupuesto_hospital']) ? floatval($_POST['presupuesto_hospital']) : 0;
$presupuestoVeCampa = isset($_POST['presupuesto_ve_campa']) ? floatval($_POST['presupuesto_ve_campa']) : 0;

// Mapeo de empresas a sus presupuestos
$presupuestosPorEmpresa = [
    'HOSPITAL SAN JOSÉ DE MAICAO E.S.E' => $presupuestoHospital,
    'VE CAMPA MYCOW S.A.S' => $presupuestoVeCampa
];

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

// Función para procesar viajes por empresa con presupuesto
function procesarViajesPorEmpresa($viajes, $presupuesto, $conn) {
    $resultado = [
        'dentro_presupuesto' => [], // Viajes que entran dentro del presupuesto
        'sobrantes' => [], // Viajes que exceden el presupuesto
        'total_dentro' => 0,
        'total_sobrantes' => 0,
        'presupuesto_original' => $presupuesto
    ];
    
    $acumulado = 0;
    $presupuestoRestante = $presupuesto;
    
    foreach ($viajes as $viaje) {
        $valor = $viaje['valor_viaje'] ? floatval($viaje['valor_viaje']) : 0;
        
        if ($presupuestoRestante > 0 && $valor > 0) {
            if ($acumulado + $valor <= $presupuesto) {
                // Viaje cabe completo dentro del presupuesto
                $viaje['acumulado_parcial'] = $acumulado + $valor;
                $resultado['dentro_presupuesto'][] = $viaje;
                $acumulado += $valor;
                $presupuestoRestante -= $valor;
                $resultado['total_dentro'] += $valor;
            } else {
                // Este viaje excede el presupuesto - no se incluye ninguno más
                $resultado['sobrantes'][] = $viaje;
                $resultado['total_sobrantes'] += $valor;
                $presupuestoRestante = 0;
            }
        } else {
            // No hay presupuesto o valor cero
            if ($presupuestoRestante <= 0 && $valor > 0) {
                $resultado['sobrantes'][] = $viaje;
                $resultado['total_sobrantes'] += $valor;
            }
        }
    }
    
    return $resultado;
}

// Obtener todos los viajes
$todosViajes = [];
while ($row = $resViajes->fetch_assoc()) {
    $todosViajes[] = $row;
}

// Agrupar viajes por empresa
$viajesPorEmpresa = [];
foreach ($todosViajes as $viaje) {
    $empresa = $viaje['empresa'];
    if (!isset($viajesPorEmpresa[$empresa])) {
        $viajesPorEmpresa[$empresa] = [];
    }
    $viajesPorEmpresa[$empresa][] = $viaje;
}

// ========== PROCESAR SEGÚN TIPO DE INFORME ==========

$viajesAsignados = [];

if ($tipoInforme === 'real') {
    // INFORME REAL: Mostrar los conductores tal como están en la base de datos
    
    foreach ($todosViajes as $row) {
        $valor = $row['valor_viaje'];
        
        $viajesAsignados[] = [
            'fecha' => $row['fecha'],
            'conductor' => $row['conductor_real'],
            'cedula' => '',
            'tipo_vehiculo' => $row['tipo_vehiculo'],
            'ruta' => $row['ruta'],
            'valor' => $valor,
            'clasificacion' => $row['clasificacion'],
            'clasificacion_base' => obtenerClasificacionBase($row['clasificacion']),
            'es_carrotanque' => stripos($row['tipo_vehiculo'], 'carrotanque') !== false,
            'empresa' => $row['empresa']
        ];
    }
    
    // Obtener cédulas para los conductores reales
    $conductoresReales = array_unique(array_column($viajesAsignados, 'conductor'));
    $conductoresInfoReal = [];
    foreach ($conductoresReales as $conductorNombre) {
        $nombreEscapado = $conn->real_escape_string($conductorNombre);
        $sqlInfo = "SELECT DISTINCT nombre, cedula, tipo_vehiculo FROM viajes WHERE nombre = '$nombreEscapado' LIMIT 1";
        $resInfo = $conn->query($sqlInfo);
        if ($resInfo && $row = $resInfo->fetch_assoc()) {
            $conductoresInfoReal[$conductorNombre] = $row;
        } else {
            $conductoresInfoReal[$conductorNombre] = ['nombre' => $conductorNombre, 'cedula' => 'N/A', 'tipo_vehiculo' => ''];
        }
    }
    
    foreach ($viajesAsignados as &$viaje) {
        if (isset($conductoresInfoReal[$viaje['conductor']])) {
            $viaje['cedula'] = $conductoresInfoReal[$viaje['conductor']]['cedula'] ?? 'N/A';
        } else {
            $viaje['cedula'] = 'N/A';
        }
    }
    
} else {
    // INFORME ALEATORIO: Lógica de asignación
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
    
    $ultimoConductor = null;
    $consecutivos = 0;
    $conductoresNoCarrotanque = array_diff($conductoresSeleccionados, $conductoresCarrotanque);
    
    foreach ($todosViajes as $row) {
        $tipoVehiculo = strtolower(trim($row['tipo_vehiculo'] ?? ''));
        $esViajeCarrotanque = strpos($tipoVehiculo, 'carrotanque') !== false;
        
        $conductorAsignado = '';
        
        if ($esViajeCarrotanque) {
            $conductorAsignado = $row['conductor_real'];
        } else {
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
        
        $conductorInfo = null;
        foreach ($conductoresInfo as $info) {
            if ($info['nombre'] == $conductorAsignado) {
                $conductorInfo = $info;
                break;
            }
        }
        
        $valor = $row['valor_viaje'];
        
        $viajesAsignados[] = [
            'fecha' => $row['fecha'],
            'conductor' => $conductorAsignado,
            'cedula' => $conductorInfo ? $conductorInfo['cedula'] : 'N/A',
            'tipo_vehiculo' => $row['tipo_vehiculo'],
            'ruta' => $row['ruta'],
            'valor' => $valor,
            'clasificacion' => $row['clasificacion'],
            'clasificacion_base' => obtenerClasificacionBase($row['clasificacion']),
            'es_carrotanque' => $esViajeCarrotanque,
            'empresa' => $row['empresa']
        ];
    }
    
    $conductoresInfoMostrar = $conductoresInfo;
}

// ========== GENERAR DOCUMENTO WORD CON PRESUPUESTO ==========

$phpWord = new PhpWord();
$section = $phpWord->addSection();

// Título
$section->addText("INFORME DE VIAJES CON PRESUPUESTO", ['bold' => true, 'size' => 14], ['align' => 'center']);
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

if ($tipoInforme === 'aleatorio') {
    $section->addText("Conductores en informe: " . implode(", ", $conductoresSeleccionados), ['italic' => true]);
    if (!empty($conductoresCarrotanque)) {
        $section->addText("⚠️ Conductores de Carrotanque (respetan su nombre real): " . implode(", ", $conductoresCarrotanque), ['italic' => true, 'color' => 'FF0000']);
    }
    $section->addText("Tipo de informe: DISTRIBUCIÓN ALEATORIA (máx 2 veces seguidas)", ['italic' => true, 'bold' => true]);
} else {
    $section->addText("Tipo de informe: DATOS REALES (sin asignación)", ['italic' => true, 'bold' => true, 'color' => '0000FF']);
}
$section->addTextBreak(2);

// Tabla de conductores (solo para informe aleatorio)
if ($tipoInforme === 'aleatorio' && isset($conductoresInfoMostrar)) {
    $section->addText("LISTA DE CONDUCTORES (INCLUIDOS EN INFORME)", ['bold' => true, 'size' => 12]);
    $section->addTextBreak(1);
    
    $tableConductores = $section->addTable(['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 80]);
    $tableConductores->addRow();
    $tableConductores->addCell(3000)->addText("CONDUCTOR", ['bold' => true]);
    $tableConductores->addCell(2500)->addText("CÉDULA", ['bold' => true]);
    $tableConductores->addCell(2500)->addText("TIPO DE VEHÍCULO", ['bold' => true]);
    $tableConductores->addCell(2000)->addText("ÁREA DE COBERTURA", ['bold' => true]);
    $tableConductores->addCell(1500)->addText("TIPO", ['bold' => true]);
    
    foreach ($conductoresInfoMostrar as $conductor) {
        $esCarrotanque = in_array($conductor['nombre'], $conductoresCarrotanque);
        $tableConductores->addRow();
        $tableConductores->addCell(3000)->addText($conductor['nombre'] ?: '-');
        $tableConductores->addCell(2500)->addText($conductor['cedula'] ?: 'N/A');
        $tableConductores->addCell(2500)->addText(obtenerTipoVehiculo($conductor['tipo_vehiculo']));
        $tableConductores->addCell(2000)->addText(obtenerAreaCobertura($conductor['tipo_vehiculo']));
        $tipoTexto = $esCarrotanque ? "🚛 Carrotanque (Fijo)" : "📋 Distribución Aleatoria";
        $tableConductores->addCell(1500)->addText($tipoTexto);
    }
    
    $section->addTextBreak(3);
}

// ========== FUNCIÓN PARA GENERAR TABLAS POR CLASIFICACIÓN ==========

function generarTablasPorClasificacion($section, $viajes, $titulo, $mostrarTotal = true) {
    if (empty($viajes)) {
        return 0;
    }
    
    // Agrupar por clasificación base
    $agrupados = [
        'completo' => [],
        'medio' => [],
        'extra' => [],
        'carrotanque' => [],
        'otros' => []
    ];
    
    foreach ($viajes as $viaje) {
        $clasificacion = $viaje['clasificacion_base'];
        if (isset($agrupados[$clasificacion])) {
            $agrupados[$clasificacion][] = $viaje;
        } else {
            $agrupados['otros'][] = $viaje;
        }
    }
    
    $nombresClasificacion = [
        'completo' => 'VIAJES COMPLETOS',
        'medio' => 'VIAJES MEDIOS',
        'extra' => 'VIAJES EXTRA',
        'carrotanque' => 'VIAJES CARROTANQUE',
        'otros' => 'OTROS VIAJES'
    ];
    
    $totalGeneral = 0;
    
    foreach ($agrupados as $clave => $viajesGrupo) {
        if (empty($viajesGrupo)) continue;
        
        $section->addText("{$titulo} - {$nombresClasificacion[$clave]}", ['bold' => true, 'size' => 11]);
        $section->addTextBreak(1);
        
        $tabla = $section->addTable(['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 80]);
        $tabla->addRow();
        $tabla->addCell(1500)->addText("FECHA", ['bold' => true]);
        $tabla->addCell(3000)->addText("CONDUCTOR", ['bold' => true]);
        $tabla->addCell(2500)->addText("VEHÍCULO", ['bold' => true]);
        $tabla->addCell(3000)->addText("RUTA", ['bold' => true]);
        $tabla->addCell(2000)->addText("VALOR", ['bold' => true]);
        
        $totalGrupo = 0;
        foreach ($viajesGrupo as $viaje) {
            $tabla->addRow();
            $tabla->addCell(1500)->addText(substr($viaje['fecha'], 0, 10));
            $textoConductor = $viaje['conductor'] ?: '-';
            if ($viaje['es_carrotanque']) $textoConductor .= " 🚛";
            $tabla->addCell(3000)->addText($textoConductor);
            $tabla->addCell(2500)->addText(obtenerTipoVehiculo($viaje['tipo_vehiculo']));
            $tabla->addCell(3000)->addText($viaje['ruta'] ?: '-');
            
            $valor = $viaje['valor'];
            if ($valor !== null && $valor > 0) {
                $tabla->addCell(2000)->addText(formatearMoneda($valor));
                $totalGrupo += floatval($valor);
            } else {
                $tabla->addCell(2000)->addText("N/A");
            }
        }
        
        $tabla->addRow();
        $cellTotal = $tabla->addCell(10000, ['gridSpan' => 4]);
        $cellTotal->addText("SUBTOTAL {$nombresClasificacion[$clave]}", ['bold' => true]);
        $tabla->addCell(2000)->addText(formatearMoneda($totalGrupo), ['bold' => true]);
        
        $totalGeneral += $totalGrupo;
        $section->addTextBreak(1);
    }
    
    if ($mostrarTotal) {
        $section->addText("TOTAL {$titulo}: " . formatearMoneda($totalGeneral), ['bold' => true, 'size' => 12]);
        $section->addTextBreak(2);
    }
    
    return $totalGeneral;
}

// ========== PROCESAR POR EMPRESA CON PRESUPUESTO ==========

// Primero, agrupar viajes asignados por empresa
$viajesPorEmpresaAsignados = [];
foreach ($viajesAsignados as $viaje) {
    $empresa = $viaje['empresa'];
    if (!isset($viajesPorEmpresaAsignados[$empresa])) {
        $viajesPorEmpresaAsignados[$empresa] = [];
    }
    $viajesPorEmpresaAsignados[$empresa][] = $viaje;
}

// Procesar cada empresa que tiene presupuesto
foreach ($presupuestosPorEmpresa as $empresa => $presupuesto) {
    if ($presupuesto > 0 && isset($viajesPorEmpresaAsignados[$empresa])) {
        $viajesEmpresa = $viajesPorEmpresaAsignados[$empresa];
        $resultado = procesarViajesPorEmpresa($viajesEmpresa, $presupuesto, $conn);
        
        $section->addText("========================================", ['bold' => true]);
        $section->addText("EMPRESA: {$empresa}", ['bold' => true, 'size' => 13]);
        $section->addText("PRESUPUESTO CONTRATO: " . formatearMoneda($presupuesto), ['bold' => true, 'size' => 12]);
        $section->addTextBreak(1);
        
        // Tablas dentro del presupuesto
        if (!empty($resultado['dentro_presupuesto'])) {
            $section->addText("✅ VIAJES DENTRO DEL PRESUPUESTO (Hasta: " . formatearMoneda($resultado['total_dentro']) . ")", ['bold' => true, 'color' => '008000']);
            $section->addTextBreak(1);
            generarTablasPorClasificacion($section, $resultado['dentro_presupuesto'], "VIAJES CONTRATO", false);
            $section->addText("TOTAL ACUMULADO: " . formatearMoneda($resultado['total_dentro']), ['bold' => true]);
        } else {
            $section->addText("⚠️ No hay viajes dentro del presupuesto", ['italic' => true]);
        }
        
        $section->addTextBreak(2);
        
        // Tablas sobrantes
        if (!empty($resultado['sobrantes'])) {
            $section->addText("⚠️ VIAJES SOBRANTES (Exceden el presupuesto)", ['bold' => true, 'color' => 'FF0000']);
            $section->addText("Valor de viajes sobrantes: " . formatearMoneda($resultado['total_sobrantes']), ['italic' => true]);
            $section->addTextBreak(1);
            generarTablasPorClasificacion($section, $resultado['sobrantes'], "VIAJES SOBRANTES", true);
        }
        
        $section->addTextBreak(2);
        
        // Eliminar esta empresa del array para no procesarla nuevamente
        unset($viajesPorEmpresaAsignados[$empresa]);
    }
}

// Empresas sin presupuesto (mostrar todos los viajes)
foreach ($viajesPorEmpresaAsignados as $empresa => $viajesEmpresa) {
    if (!empty($viajesEmpresa)) {
        $section->addText("========================================", ['bold' => true]);
        $section->addText("EMPRESA: {$empresa}", ['bold' => true, 'size' => 13]);
        $section->addText("(Sin presupuesto definido - Todos los viajes)", ['italic' => true]);
        $section->addTextBreak(1);
        generarTablasPorClasificacion($section, $viajesEmpresa, "VIAJES COMPLETOS", true);
        $section->addTextBreak(2);
    }
}

// Total general de todos los viajes (opcional)
$totalGeneralTodos = array_sum(array_column($viajesAsignados, 'valor'));
$section->addText("========================================", ['bold' => true]);
$section->addText("RESUMEN GENERAL", ['bold' => true, 'size' => 13]);
$section->addText("Total de todos los viajes en el período: " . formatearMoneda($totalGeneralTodos), ['bold' => true]);
$section->addTextBreak(2);

// Firma
date_default_timezone_set('America/Bogota');
$section->addText("Maicao, " . date('d/m/Y'), [], ['align' => 'right']);
$section->addText("Cordialmente,");
$section->addTextBreak(2);
$section->addText("NUMAS JOSÉ IGUARÁN IGUARÁN", ['bold' => true]);
$section->addText("Representante Legal");

// Enviar archivo
$sufijo = ($tipoInforme === 'real') ? 'real' : 'aleatorio';
$filename = "informe_viajes_presupuesto_{$sufijo}_{$desde}_a_{$hasta}.docx";
header("Content-Description: File Transfer");
header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: public");

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save('php://output');
exit;
?>