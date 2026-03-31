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
                max-width: 1400px;
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
                cursor: pointer;
                transition: background 0.3s;
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
                max-height: 350px;
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
                margin-top: 1rem;
            }
            
            .legend-item {
                display: inline-flex;
                align-items: center;
                margin-right: 1.5rem;
                margin-bottom: 0.5rem;
            }
            
            .legend-color {
                width: 20px;
                height: 20px;
                border-radius: 4px;
                margin-right: 0.5rem;
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
                transition: all 0.3s;
            }
            
            .btn-generate:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(25,135,84,0.3);
            }
            
            .btn-home {
                background: linear-gradient(135deg, var(--secondary-color) 0%, #495057 100%);
                color: white;
                padding: 0.75rem 2rem;
                border-radius: 10px;
                font-weight: 600;
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
            @media (max-width: 768px) {
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
                }
                
                .empresas-list {
                    grid-template-columns: 1fr;
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
                            <!-- Columna izquierda: Conductores -->
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
                                                           data-vehiculo="<?= htmlspecialchars($cond['tipo_vehiculo']) ?>">
                                                    <label class="form-check-label" for="conductor_<?= $index ?>" style="width: 100%;">
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
                            
                            <!-- Columna derecha: Empresas e información -->
                            <div class="col-lg-4">
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
                                            <i class="fas fa-info-circle"></i> Si no selecciona ninguna, se incluirán todas
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
                                                <span><i class="fas fa-truck"></i> Carrotanque: Mantiene su nombre real</span>
                                            </div>
                                            <div class="legend-item">
                                                <div class="legend-color" style="background: #d1ecf1; border-left: 3px solid #0dcaf0;"></div>
                                                <span><i class="fas fa-car"></i> Demás vehículos: Distribución aleatoria</span>
                                            </div>
                                            <div class="legend-item">
                                                <div class="legend-color" style="background: #f8f9fa; border-left: 3px solid #198754;"></div>
                                                <span><i class="fas fa-random"></i> Máximo 2 veces seguidas mismo conductor</span>
                                            </div>
                                        </div>
                                        <div class="alert alert-info mt-3 mb-0">
                                            <i class="fas fa-lightbulb"></i>
                                            <strong>¿Cómo funciona?</strong><br>
                                            Los conductores de carrotanque conservan su nombre real en los viajes. 
                                            Los demás conductores se distribuyen aleatoriamente, evitando repeticiones consecutivas más de 2 veces.
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Contador de selección -->
                                <div class="section-card" id="counterCard" style="display: none;">
                                    <div class="section-header">
                                        <h3><i class="fas fa-chart-bar"></i> Resumen de selección</h3>
                                    </div>
                                    <div class="section-content">
                                        <div class="text-center">
                                            <h2 id="selectedCount" class="display-4 text-primary">0</h2>
                                            <p class="text-muted">conductores seleccionados</p>
                                            <div class="progress">
                                                <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Botones de acción -->
                        <div class="action-buttons">
                            <button type="submit" class="btn btn-generate">
                                <i class="fas fa-file-alt"></i> Generar Informe
                            </button>
                            <a class="btn btn-home" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/index2.php">
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
            const counterCard = document.getElementById('counterCard');
            const selectedCountSpan = document.getElementById('selectedCount');
            const progressBar = document.getElementById('progressBar');
            
            // Función para actualizar contador de seleccionados
            function actualizarContadorSeleccionados() {
                const checkboxes = document.querySelectorAll('.conductor-checkbox');
                const total = checkboxes.length;
                const seleccionados = Array.from(checkboxes).filter(cb => cb.checked).length;
                
                if (selectedCountSpan) {
                    selectedCountSpan.textContent = seleccionados;
                    const porcentaje = (seleccionados / total) * 100;
                    if (progressBar) {
                        progressBar.style.width = porcentaje + '%';
                        progressBar.setAttribute('aria-valuenow', porcentaje);
                    }
                    
                    if (seleccionados > 0) {
                        counterCard.style.display = 'block';
                        selectedCountSpan.classList.add('selected-count');
                        setTimeout(() => selectedCountSpan.classList.remove('selected-count'), 300);
                    } else {
                        counterCard.style.display = 'none';
                    }
                }
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
                    resultadoBusqueda.innerHTML = `<i class="fas fa-search"></i> Se encontraron ${contadorVisibles} conductores que empiezan con "${buscador.value}"`;
                }
                
                actualizarContadorSeleccionados();
            }
            
            // Evento de búsqueda
            buscador.addEventListener('keyup', filtrarConductores);
            buscador.addEventListener('change', filtrarConductores);
            
            // Seleccionar todos los conductores visibles
            document.getElementById('btnSeleccionarTodosCond').addEventListener('click', function() {
                const itemsVisibles = document.querySelectorAll('.conductor-item[style=""]');
                itemsVisibles.forEach(item => {
                    const checkbox = item.querySelector('.conductor-checkbox');
                    if (checkbox) checkbox.checked = true;
                });
                actualizarContadorSeleccionados();
            });
            
            // Limpiar todos los conductores
            document.getElementById('btnLimpiarTodosCond').addEventListener('click', function() {
                document.querySelectorAll('.conductor-checkbox').forEach(checkbox => {
                    checkbox.checked = false;
                });
                actualizarContadorSeleccionados();
            });
            
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
            document.getElementById('formInforme').addEventListener('submit', function(e) {
                const seleccionados = document.querySelectorAll('.conductor-checkbox:checked');
                if (seleccionados.length === 0) {
                    e.preventDefault();
                    alert('⚠️ Por favor, seleccione al menos un conductor para generar el informe.');
                    return false;
                }
            });
            
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
        </script>
    </body>
    </html>
    <?php
    exit;
}

// ========== PROCESAMIENTO DEL INFORME ==========
// ... (el resto del código de procesamiento se mantiene igual)
// [Aquí va el código de procesamiento del informe que ya tenías]
?>