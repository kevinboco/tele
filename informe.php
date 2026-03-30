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
    // Si no hay último conductor o todos tienen ya 2 consecutivos, permitir cualquiera
    if ($ultimoConductor === null) {
        return $conductoresLista[array_rand($conductoresLista)];
    }
    
    // Filtrar conductores que no sean el último o que si son el último pero tienen menos de 2 consecutivos
    $opciones = [];
    foreach ($conductoresLista as $conductor) {
        if ($conductor !== $ultimoConductor) {
            $opciones[] = $conductor;
        } else {
            // Si es el mismo conductor, solo permitirlo si ha aparecido menos de 2 veces seguidas
            if ($consecutivos < 2) {
                $opciones[] = $conductor;
            }
        }
    }
    
    // Si no hay opciones disponibles (todos los conductores son el mismo y ya tiene 2 consecutivos)
    if (empty($opciones)) {
        // Permitir cualquier conductor (reiniciar la regla)
        return $conductoresLista[array_rand($conductoresLista)];
    }
    
    return $opciones[array_rand($opciones)];
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
        <title>Generar Informe</title>
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
            .card-header {
                background-color: #f8f9fa;
                font-weight: bold;
            }
            .conductor-item {
                padding: 8px;
                border-bottom: 1px solid #eee;
                transition: background-color 0.2s;
            }
            .conductor-item:hover {
                background-color: #f5f5f5;
            }
            .buscar-input {
                margin-bottom: 15px;
            }
            .resultado-busqueda {
                font-size: 0.85em;
                color: #6c757d;
                margin-top: 5px;
            }
        </style>
    </head>
    <body class="bg-light p-4">
    <div class="container">
        <h3 class="mb-3">📅 Generar Informe de Viajes</h3>
        <form method="post" class="card p-4 shadow-sm" id="formInforme">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Desde</label>
                    <input type="date" name="desde" class="form-control" required id="fecha_desde">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Hasta</label>
                    <input type="date" name="hasta" class="form-control" required id="fecha_hasta">
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
                
                <div class="col-12">
                    <label class="form-label fw-bold">👥 Seleccionar Conductores para el Informe:</label>
                    <div class="card">
                        <div class="card-header">
                            <div class="row">
                                <div class="col-md-8">
                                    <input type="text" id="buscadorConductores" class="form-control buscar-input" placeholder="🔍 Buscar conductor por nombre o cédula...">
                                    <div class="resultado-busqueda" id="resultadoBusqueda"></div>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button type="button" id="btnSeleccionarTodosCond" class="btn btn-sm btn-outline-primary">Seleccionar todos</button>
                                    <button type="button" id="btnLimpiarTodosCond" class="btn btn-sm btn-outline-secondary">Limpiar todos</button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                            <div id="listaConductores">
                                <?php foreach($todosConductores as $index => $cond): ?>
                                <div class="conductor-item" data-nombre="<?= strtolower(htmlspecialchars($cond['nombre'])) ?>" data-cedula="<?= strtolower(htmlspecialchars($cond['cedula'] ?? '')) ?>" data-nombre-original="<?= htmlspecialchars($cond['nombre']) ?>">
                                    <div class="form-check">
                                        <input class="form-check-input conductor-checkbox" type="checkbox" 
                                               name="conductores_seleccionados[]" value="<?= htmlspecialchars($cond['nombre']) ?>" 
                                               id="conductor_<?= $index ?>"
                                               data-nombre="<?= htmlspecialchars($cond['nombre']) ?>"
                                               data-cedula="<?= htmlspecialchars($cond['cedula'] ?? '') ?>"
                                               data-vehiculo="<?= htmlspecialchars($cond['tipo_vehiculo']) ?>">
                                        <label class="form-check-label" for="conductor_<?= $index ?>">
                                            <strong><?= htmlspecialchars($cond['nombre']) ?></strong>
                                            <?php if(!empty($cond['cedula'])): ?>
                                                <span class="text-muted">(Cédula: <?= htmlspecialchars($cond['cedula']) ?>)</span>
                                            <?php endif; ?>
                                            <br><small class="text-secondary">Vehículo: <?= htmlspecialchars(obtenerTipoVehiculo($cond['tipo_vehiculo'])) ?></small>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <small class="text-muted">Seleccione los conductores que deben aparecer en el informe. Los viajes se distribuirán aleatoriamente entre los seleccionados, evitando que un mismo conductor aparezca más de 2 veces seguidas.</small>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Generar Informe</button>
                <a class="btn btn-secondary" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/index2.php">Ir a Inicio</a>
            </div>
        </form>
    </div>
    
    <script>
        // Buscador de conductores mejorado
        const buscador = document.getElementById('buscadorConductores');
        const conductoresItems = document.querySelectorAll('.conductor-item');
        const resultadoBusqueda = document.getElementById('resultadoBusqueda');
        
        function filtrarConductores() {
            const busqueda = buscador.value.toLowerCase().trim();
            let contadorVisibles = 0;
            
            conductoresItems.forEach(item => {
                const nombre = item.getAttribute('data-nombre') || '';
                const cedula = item.getAttribute('data-cedula') || '';
                
                // Buscar coincidencia en nombre o cédula
                const coincide = busqueda === '' || 
                                nombre.startsWith(busqueda) || 
                                cedula.includes(busqueda);
                
                if (coincide) {
                    item.style.display = '';
                    contadorVisibles++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Actualizar contador de resultados
            if (busqueda === '') {
                resultadoBusqueda.innerHTML = `Mostrando ${contadorVisibles} conductores`;
            } else {
                resultadoBusqueda.innerHTML = `🔍 Se encontraron ${contadorVisibles} conductores que coinciden con "${buscador.value}"`;
            }
        }
        
        // Evento de búsqueda
        buscador.addEventListener('keyup', filtrarConductores);
        buscador.addEventListener('change', filtrarConductores);
        
        // Inicializar contador
        filtrarConductores();
        
        // Seleccionar todos los conductores visibles
        document.getElementById('btnSeleccionarTodosCond').addEventListener('click', function() {
            const itemsVisibles = document.querySelectorAll('.conductor-item[style=""]');
            itemsVisibles.forEach(item => {
                const checkbox = item.querySelector('.conductor-checkbox');
                if (checkbox) checkbox.checked = true;
            });
        });
        
        // Limpiar todos los conductores
        document.getElementById('btnLimpiarTodosCond').addEventListener('click', function() {
            document.querySelectorAll('.conductor-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
        });
        
        // Seleccionar todas las empresas
        const seleccionarTodos = document.getElementById('seleccionarTodos');
        const checkboxesEmpresa = document.querySelectorAll('.empresa-item');
        
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
        // Si no se encuentra información, agregar solo el nombre
        $conductoresInfo[] = ['nombre' => $conductorNombre, 'cedula' => 'N/A', 'tipo_vehiculo' => ''];
    }
}

// ========== CONSULTA PRINCIPAL - OBTENER TODOS LOS VIAJES SIN FILTRAR POR CONDUCTOR ==========
$sqlViajes = "
    SELECT 
        v.fecha,
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

// ========== ASIGNAR ALEATORIAMENTE LOS VIAJES A LOS CONDUCTORES SELECCIONADOS ==========
// Con regla: máximo 2 veces seguidas el mismo conductor
$viajesAsignados = [];
$totalValores = 0;
$ultimoConductor = null;
$consecutivos = 0;

if ($resViajes && $resViajes->num_rows > 0) {
    // Crear un array con los nombres de los conductores seleccionados
    $listaConductores = $conductoresSeleccionados;
    
    // Si solo hay un conductor, no hay problema, aparecerá siempre
    if (count($listaConductores) == 1) {
        $conductorUnico = $listaConductores[0];
        while ($row = $resViajes->fetch_assoc()) {
            $valor = $row['valor_viaje'];
            if ($valor !== null && $valor > 0) {
                $totalValores += floatval($valor);
            }
            
            // Obtener información del conductor
            $conductorInfo = null;
            foreach ($conductoresInfo as $info) {
                if ($info['nombre'] == $conductorUnico) {
                    $conductorInfo = $info;
                    break;
                }
            }
            
            $viajesAsignados[] = [
                'fecha' => $row['fecha'],
                'conductor' => $conductorUnico,
                'cedula' => $conductorInfo ? $conductorInfo['cedula'] : 'N/A',
                'tipo_vehiculo' => $row['tipo_vehiculo'],
                'ruta' => $row['ruta'],
                'valor' => $valor,
                'clasificacion' => $row['clasificacion']
            ];
        }
    } else {
        // Múltiples conductores - aplicar regla de máximo 2 veces seguidas
        while ($row = $resViajes->fetch_assoc()) {
            // Asignar conductor con la regla de no más de 2 veces seguidas
            $nuevoConductor = asignarConductorConRegla($listaConductores, $ultimoConductor, $consecutivos);
            
            // Actualizar contador de consecutivos
            if ($nuevoConductor == $ultimoConductor) {
                $consecutivos++;
            } else {
                $consecutivos = 1;
            }
            $ultimoConductor = $nuevoConductor;
            
            // Obtener información del conductor asignado
            $conductorInfo = null;
            foreach ($conductoresInfo as $info) {
                if ($info['nombre'] == $nuevoConductor) {
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
                'conductor' => $nuevoConductor,
                'cedula' => $conductorInfo ? $conductorInfo['cedula'] : 'N/A',
                'tipo_vehiculo' => $row['tipo_vehiculo'],
                'ruta' => $row['ruta'],
                'valor' => $valor,
                'clasificacion' => $row['clasificacion']
            ];
        }
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

if (!empty($conductoresInfo)) {
    foreach ($conductoresInfo as $conductor) {
        $tableConductores->addRow();
        $tableConductores->addCell(3000)->addText($conductor['nombre'] ?: '-');
        $tableConductores->addCell(2500)->addText($conductor['cedula'] ?: 'N/A');
        
        $tipoVehiculo = obtenerTipoVehiculo($conductor['tipo_vehiculo']);
        $tableConductores->addCell(2500)->addText($tipoVehiculo);
        
        $areaCobertura = obtenerAreaCobertura($conductor['tipo_vehiculo']);
        $tableConductores->addCell(2000)->addText($areaCobertura);
    }
} else {
    $tableConductores->addRow();
    $cell = $tableConductores->addCell(10000, ['gridSpan' => 4]);
    $cell->addText("📭 No hay conductores seleccionados.");
}

$section->addTextBreak(3);

// ========== TABLA 2: DETALLE DE VIAJES CON CONDUCTORES ASIGNADOS ALEATORIAMENTE ==========
$section->addText("DETALLE DE VIAJES POR FECHA", ['bold' => true, 'size' => 12]);
$section->addText("(Los conductores han sido asignados aleatoriamente, evitando repeticiones consecutivas)", ['italic' => true, 'size' => 10]);
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
$tableViajes->addCell(3000)->addText("CONDUCTOR (ASIGNADO)", ['bold' => true]);
$tableViajes->addCell(2500)->addText("VEHÍCULO", ['bold' => true]);
$tableViajes->addCell(3000)->addText("RUTA", ['bold' => true]);
$tableViajes->addCell(2000)->addText("VALOR", ['bold' => true]);

if (!empty($viajesAsignados)) {
    foreach ($viajesAsignados as $viaje) {
        $tableViajes->addRow();
        $tableViajes->addCell(1500)->addText(substr($viaje['fecha'], 0, 10));
        $tableViajes->addCell(3000)->addText($viaje['conductor'] ?: '-');
        
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