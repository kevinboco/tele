<?php
// index2.php - Sistema completo de gestión de viajes

// SIEMPRE primero la sesión, sin imprimir nada antes
session_start();
include("conexion.php");

// ================== CONFIGURACIÓN INICIAL ==================
$accion  = $_GET['accion'] ?? 'listar';
$id      = $_GET['id'] ?? 0;
$mensaje = $_GET['msg'] ?? '';
$error   = $_GET['error'] ?? '';

// Inicializar array de selección si no existe
if (!isset($_SESSION['seleccionados'])) {
    $_SESSION['seleccionados'] = [];
}

// ================== CONFIGURACIÓN DE COLUMNAS VISIBLES ==================
// Definir todas las columnas disponibles
$columnas_disponibles = [
    'id' => ['nombre' => 'ID', 'visible' => true, 'orden' => 1],
    'nombre' => ['nombre' => 'Nombre', 'visible' => true, 'orden' => 2],
    'cedula' => ['nombre' => 'Cédula', 'visible' => true, 'orden' => 3],
    'fecha' => ['nombre' => 'Fecha', 'visible' => true, 'orden' => 4],
    'ruta' => ['nombre' => 'Ruta', 'visible' => true, 'orden' => 5],
    'tipo_vehiculo' => ['nombre' => 'Vehículo', 'visible' => true, 'orden' => 6],
    'empresa' => ['nombre' => 'Empresa', 'visible' => true, 'orden' => 7],
    'pago_parcial' => ['nombre' => 'Pago Parcial', 'visible' => true, 'orden' => 8],
    'imagen' => ['nombre' => 'Imagen', 'visible' => true, 'orden' => 9]
];

// Inicializar configuración de columnas en sesión
if (!isset($_SESSION['columnas_visibles'])) {
    $_SESSION['columnas_visibles'] = $columnas_disponibles;
}

// Procesar cambios en las columnas visibles
if (isset($_POST['actualizar_columnas'])) {
    // Recibir las columnas seleccionadas
    $columnas_seleccionadas = $_POST['columnas'] ?? [];
    
    // Actualizar el estado de cada columna
    foreach ($columnas_disponibles as $key => $columna) {
        $_SESSION['columnas_visibles'][$key]['visible'] = in_array($key, $columnas_seleccionadas);
    }
    
    // Redirigir para evitar reenvío del formulario
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// Restablecer todas las columnas
if (isset($_POST['restablecer_columnas'])) {
    $_SESSION['columnas_visibles'] = $columnas_disponibles;
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// ================== MANEJO DE SELECCIÓN ==================
// ... (el resto del código de manejo de selección se mantiene igual)
// Agregar/eliminar IDs de la selección (checkbox individual)
if (isset($_POST['toggle_seleccion'])) {
    $id_toggle = (int)$_POST['toggle_seleccion'];

    if (in_array($id_toggle, $_SESSION['seleccionados'])) {
        // Eliminar de la selección
        $_SESSION['seleccionados'] = array_diff($_SESSION['seleccionados'], [$id_toggle]);
    } else {
        // Agregar a la selección
        $_SESSION['seleccionados'][] = $id_toggle;
    }

    $_SESSION['seleccionados'] = array_values(array_unique($_SESSION['seleccionados']));
}

// Seleccionar/deseleccionar todos los visibles
if (isset($_POST['seleccionar_todos']) && isset($_POST['ids_visibles'])) {
    $ids_visibles = [];

    // Puede venir como string "1,2,3" o como array
    if (is_array($_POST['ids_visibles'])) {
        $ids_visibles = array_map('intval', $_POST['ids_visibles']);
    } else {
        $raw = trim($_POST['ids_visibles']);
        if ($raw !== '') {
            $ids_visibles = array_map('intval', explode(',', $raw));
        }
    }

    if (!empty($ids_visibles)) {
        if ($_POST['seleccionar_todos'] == '1') {
            // Agregar todos los visibles a la selección
            foreach ($ids_visibles as $id_visible) {
                if (!in_array($id_visible, $_SESSION['seleccionados'])) {
                    $_SESSION['seleccionados'][] = $id_visible;
                }
            }
        } else {
            // Quitar todos los visibles de la selección
            $_SESSION['seleccionados'] = array_diff($_SESSION['seleccionados'], $ids_visibles);
        }

        $_SESSION['seleccionados'] = array_values(array_unique($_SESSION['seleccionados']));
    }
}

// Limpiar selección
if (isset($_POST['limpiar_seleccion'])) {
    $_SESSION['seleccionados'] = [];
}

// ================== FUNCIONES AUXILIARES ==================
function obtenerListas($conexion) {
    $listas = [
        'nombres'   => [],
        'cedulas'   => [],
        'rutas'     => [],
        'vehiculos' => [],
        'empresas'  => []
    ];
    
    // Nombres
    $res = $conexion->query("SELECT DISTINCT nombre FROM viajes WHERE nombre <> '' ORDER BY nombre ASC");
    if ($res) {
        while($r = $res->fetch_assoc()){
            $listas['nombres'][] = $r['nombre'];
        }
    }
    
    // Cédulas
    $res = $conexion->query("SELECT DISTINCT cedula FROM viajes WHERE cedula IS NOT NULL AND cedula <> '' ORDER BY cedula ASC");
    if ($res) {
        while($r = $res->fetch_assoc()){
            $listas['cedulas'][] = $r['cedula'];
        }
    }
    
    // Rutas
    $res = $conexion->query("SELECT DISTINCT ruta FROM viajes WHERE ruta <> '' ORDER BY ruta ASC");
    if ($res) {
        while($r = $res->fetch_assoc()){
            $listas['rutas'][] = $r['ruta'];
        }
    }
    
    // Vehículos
    $res = $conexion->query("SELECT DISTINCT tipo_vehiculo FROM viajes WHERE tipo_vehiculo <> '' ORDER BY tipo_vehiculo ASC");
    if ($res) {
        while($r = $res->fetch_assoc()){
            $listas['vehiculos'][] = $r['tipo_vehiculo'];
        }
    }
    
    // Empresas
    $res = $conexion->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa <> '' ORDER BY empresa ASC");
    if ($res) {
        while($r = $res->fetch_assoc()){
            $listas['empresas'][] = $r['empresa'];
        }
    }
    
    return $listas;
}

// Helper: normaliza pago_parcial
function normalizarPagoParcial($conexion, $valorRaw) {
    if (!isset($valorRaw)) return "NULL";
    $v = trim((string)$valorRaw);
    if ($v === '') return "NULL";
    $v = str_replace([',', ' '], '', $v);
    if (!is_numeric($v)) return "NULL";
    $n = (int)$v;
    if ($n < 0) $n = 0;
    return (string)$n; // int sin comillas
}

// ================== PROCESAR ACCIONES ==================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // ... (todo el resto del código de procesamiento POST se mantiene igual)
    // CREAR NUEVO VIAJE
    if (isset($_POST['crear'])) {
        // ... (código existente)
    }
    
    // EDITAR VIAJE INDIVIDUAL
    elseif (isset($_POST['editar'])) {
        // ... (código existente)
    }
    
    // EDITAR MÚLTIPLES VIAJES (COMPLETO)
    elseif (isset($_POST['editar_multiple_completo'])) {
        // ... (código existente)
    }
    
    // ACCIONES MÚLTIPLES DESDE LISTADO
    elseif (isset($_POST['accion_multiple'])) {
        // ... (código existente)
    }
}

// ... (el resto del código PHP se mantiene igual hasta la sección de HTML)
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Viajes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet">
    <style>
        .table-hover tbody tr:hover { background-color: rgba(0,0,0,.025); }
        .img-thumb { max-width: 70px; height: auto; }
        .required:after { content: " *"; color: red; }
        .seleccionado { background-color: rgba(25, 135, 84, 0.1) !important; }
        .checkbox-seleccion { cursor: pointer; }
        .sticky-actions { position: sticky; top: 0; z-index: 1000; background: white; padding: 15px; margin: -15px -15px 15px -15px; border-bottom: 1px solid #dee2e6; box-shadow: 0 2px 4px rgba(0,0,0,.1); }
        .table-container { max-height: 600px; overflow-y: auto; }
        .form-control-sm { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
        .select2-container--default .select2-selection--multiple { min-height: 38px; }
        .select2-container .select2-selection--single { height: 38px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }
        .columnas-config { position: relative; }
        .columnas-dropdown { position: absolute; top: 100%; left: 0; z-index: 1000; background: white; border: 1px solid #dee2e6; border-radius: 0.375rem; box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15); min-width: 300px; padding: 1rem; display: none; }
        .columnas-dropdown.show { display: block; }
        .columna-checkbox { display: block; margin-bottom: 0.5rem; }
        .columna-checkbox input { margin-right: 0.5rem; }
        .badge-columna { background-color: #6c757d; cursor: pointer; }
        .badge-columna:hover { background-color: #5a6268; }
    </style>
</head>
<body class="bg-light">

<?php
// AHORA sí incluimos el menú lateral / nav personalizado
include("nav.php");
?>

<!-- NAVEGACIÓN SUPERIOR -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand" href="?">🚗 Sistema de Viajes</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $accion == 'listar' ? 'active' : '' ?>" href="?">📋 Listado</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $accion == 'crear' ? 'active' : '' ?>" href="?accion=crear">➕ Nuevo Viaje</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container py-4">
    <!-- MENSAJES -->
    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php
            switch($mensaje) {
                case 'creado': echo "✅ Viaje creado exitosamente."; break;
                case 'editado': echo "✏ Viaje editado exitosamente."; break;
                case 'editado_con_cedula': 
                    $afectados = $_GET['afectados'] ?? 0;
                    $nombre = $_GET['nombre'] ?? '';
                    echo "✏ Viaje editado exitosamente. <br>✅ La cédula se actualizó en <b>$afectados</b> registros adicionales de <b>" . htmlspecialchars($nombre) . "</b>."; 
                    break;
                case 'eliminado': echo "🗑 Viaje eliminado exitosamente."; break;
                case 'multi_eliminado': 
                    $count = $_GET['count'] ?? 0;
                    echo "🗑 $count viaje(s) eliminado(s) exitosamente."; 
                    break;
                case 'multi_editado': 
                    $count = $_GET['count'] ?? 0;
                    echo "✏ $count viaje(s) editado(s) exitosamente."; 
                    break;
                default: echo htmlspecialchars($mensaje);
            }
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php
            switch($error) {
                case 'no_ids': echo "⚠ No se seleccionaron registros."; break;
                case 'eliminar': echo "❌ Error al eliminar el registro."; break;
                default: echo htmlspecialchars($error);
            }
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ================== FORMULARIO CREAR/EDITAR ================== -->
    <?php if ($accion == 'crear' || ($accion == 'editar' && $viaje)): ?>
        <!-- ... (código del formulario de crear/editar se mantiene igual) -->
        
    <!-- ================== EDITAR MÚLTIPLES VIAJES (COMPLETO) ================== -->
    <?php elseif ($accion == 'editar_multiple' && !empty($_SESSION['seleccionados'])): ?>
        <!-- ... (código de edición múltiple se mantiene igual) -->

    <!-- ================== LISTADO PRINCIPAL ================== -->
    <?php else: ?>
        <!-- CONTADOR DE SELECCIONADOS -->
        <?php if (!empty($_SESSION['seleccionados'])): ?>
            <div class="alert alert-info alert-dismissible fade show">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>✅ Seleccionados:</strong> <?= count($_SESSION['seleccionados']) ?> viaje(s)
                        <span class="ms-3">IDs: <?= implode(', ', $_SESSION['seleccionados']) ?></span>
                    </div>
                    <div class="d-flex gap-2">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="limpiar_seleccion" value="1">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Limpiar selección</button>
                        </form>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- BOTÓN DE CONFIGURACIÓN DE COLUMNAS -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div></div>
            <div class="columnas-config position-relative">
                <button type="button" class="btn btn-outline-primary" id="btnConfigColumnas">
                    📊 Configurar Columnas
                </button>
                <div class="columnas-dropdown" id="dropdownColumnas">
                    <h6 class="mb-3">Seleccionar columnas a mostrar:</h6>
                    <form method="POST" id="formColumnas">
                        <?php foreach($_SESSION['columnas_visibles'] as $key => $columna): 
                            // Omitir columna de selección (checkbox)
                            if ($key === 'seleccion') continue;
                        ?>
                            <div class="form-check columna-checkbox">
                                <input class="form-check-input" type="checkbox" 
                                       name="columnas[]" value="<?= htmlspecialchars($key) ?>" 
                                       id="col_<?= htmlspecialchars($key) ?>"
                                       <?= $columna['visible'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="col_<?= htmlspecialchars($key) ?>">
                                    <?= htmlspecialchars($columna['nombre']) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="mt-3 d-flex justify-content-between">
                            <button type="submit" name="actualizar_columnas" class="btn btn-sm btn-primary">
                                Aplicar cambios
                            </button>
                            <button type="submit" name="restablecer_columnas" class="btn btn-sm btn-secondary">
                                Restablecer todas
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- FILTROS (MULTISELECT) -->
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">🔍 Filtros de búsqueda (multiselect)</h3>
                <small class="text-light">Presiona Ctrl+Click o arrastra para seleccionar múltiples opciones</small>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3" id="filtrosForm">
                    <!-- ... (filtros existentes se mantienen igual) -->
                </form>
            </div>
        </div>

        <?php
        // ================== CONSTRUIR CONSULTA CON FILTROS ==================
        $where        = [];
        $ids_visibles = []; // Para guardar los IDs visibles actualmente

        // ... (código de construcción de consulta se mantiene igual)
        
        $sql = "SELECT * FROM viajes";
        if (count($where) > 0) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY fecha DESC, id DESC";
        
        $resultado = $conexion->query($sql);

        // ... (conteo de resultados se mantiene igual)
        ?>

        <!-- LISTADO -->
        <div class="card shadow">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-0">📋 Listado de Viajes</h3>
                    <?php if ($resultado): ?>
                        <small class="text-light">Mostrando <?= $resultado->num_rows ?> resultado(s)</small>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <?php if (!empty($_SESSION['seleccionados'])): ?>
                        <span class="badge bg-success align-self-center">
                            ✅ <?= count($_SESSION['seleccionados']) ?> seleccionado(s)
                        </span>
                    <?php endif; ?>
                    <a href="?accion=crear" class="btn btn-success">➕ Nuevo Viaje</a>
                </div>
            </div>
            
            <!-- ACCIONES MÚLTIPLES STICKY (ARRIBA) -->
            <?php if (!empty($_SESSION['seleccionados'])): ?>
                <div class="sticky-actions">
                    <h5>📋 Acciones para los <?= count($_SESSION['seleccionados']) ?> viajes seleccionados:</h5>
                    <div class="d-flex gap-2 mt-2">
                        <form method="POST">
                            <button type="submit" name="accion_multiple" value="editar" class="btn btn-warning">
                                ✏ Editar Seleccionados (Completo)
                            </button>
                        </form>
                        <form method="POST">
                            <button type="submit" name="accion_multiple" value="eliminar" class="btn btn-danger"
                                    onclick="return confirm('¿Eliminar los <?= count($_SESSION['seleccionados']) ?> registros seleccionados?')">
                                🗑 Eliminar Seleccionados
                            </button>
                        </form>
                        <form method="POST" class="ms-auto">
                            <input type="hidden" name="limpiar_seleccion" value="1">
                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                Limpiar selección
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="card-body">
                <!-- CONTROLES DE SELECCIÓN -->
                <div class="mb-3 d-flex justify-content-between align-items-center bg-light p-3 rounded">
                    <div>
                        <strong>Selección múltiple:</strong>
                        <form method="POST" class="d-inline ms-2">
                            <input type="hidden" name="seleccionar_todos" value="1">
                            <input type="hidden" name="ids_visibles" id="idsVisibles" value="">
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                ✅ Seleccionar todos los visibles
                            </button>
                        </form>
                        <form method="POST" class="d-inline ms-2">
                            <input type="hidden" name="seleccionar_todos" value="0">
                            <input type="hidden" name="ids_visibles" id="idsVisibles2" value="">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                ❌ Deseleccionar todos los visibles
                            </button>
                        </form>
                    </div>
                    
                    <!-- ETIQUETAS DE COLUMNAS VISIBLES -->
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach($_SESSION['columnas_visibles'] as $key => $columna): 
                            if ($columna['visible'] && $key !== 'seleccion'):
                        ?>
                            <span class="badge bg-info text-dark"><?= htmlspecialchars($columna['nombre']) ?></span>
                        <?php endif; endforeach; ?>
                    </div>
                </div>

                <div class="table-container">
                    <table class="table table-bordered table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <!-- Columna de selección (siempre visible) -->
                                <th style="width:32px;">Sel.</th>
                                
                                <!-- Renderizar columnas dinámicamente según configuración -->
                                <?php 
                                // Ordenar columnas por el campo 'orden'
                                $columnas_ordenadas = $_SESSION['columnas_visibles'];
                                uasort($columnas_ordenadas, function($a, $b) {
                                    return $a['orden'] <=> $b['orden'];
                                });
                                
                                foreach($columnas_ordenadas as $key => $columna):
                                    // Solo mostrar si está marcada como visible
                                    if (!$columna['visible']) continue;
                                    
                                    // Definir anchos específicos para algunas columnas
                                    $width = '';
                                    switch($key) {
                                        case 'id': $width = 'width: 60px;'; break;
                                        case 'imagen': $width = 'width: 100px;'; break;
                                        case 'acciones': $width = 'width: 160px;'; break;
                                    }
                                ?>
                                    <th style="<?= $width ?>"><?= htmlspecialchars($columna['nombre']) ?></th>
                                <?php endforeach; ?>
                                
                                <!-- Columna de acciones (siempre visible) -->
                                <th style="width:160px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($resultado && $resultado->num_rows > 0): ?>
                            <?php while($row = $resultado->fetch_assoc()): 
                                $id_registro       = (int)$row['id'];
                                $ids_visibles[]    = $id_registro;
                                $esta_seleccionado = in_array($id_registro, $_SESSION['seleccionados']);
                                $pagoParcial = $row['pago_parcial'];
                            ?>
                                <tr id="fila_<?= $id_registro ?>" class="<?= $esta_seleccionado ? 'seleccionado' : '' ?>">
                                    <!-- Columna de selección (siempre visible) -->
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="toggle_seleccion" value="<?= $id_registro ?>">
                                            <input type="checkbox" 
                                                   class="form-check-input checkbox-seleccion" 
                                                   onchange="this.form.submit()"
                                                   <?= $esta_seleccionado ? 'checked' : '' ?>>
                                        </form>
                                    </td>
                                    
                                    <!-- Renderizar celdas dinámicamente según columnas visibles -->
                                    <?php foreach($columnas_ordenadas as $key => $columna): 
                                        if (!$columna['visible']) continue;
                                        
                                        switch($key):
                                            case 'id': ?>
                                                <td><?= $id_registro; ?></td>
                                                <?php break;
                                                
                                            case 'nombre': ?>
                                                <td><?= htmlspecialchars($row['nombre']); ?></td>
                                                <?php break;
                                                
                                            case 'cedula': ?>
                                                <td><?= !empty($row['cedula']) ? htmlspecialchars($row['cedula']) : '<span class="text-muted">—</span>'; ?></td>
                                                <?php break;
                                                
                                            case 'fecha': ?>
                                                <td><?= htmlspecialchars($row['fecha']); ?></td>
                                                <?php break;
                                                
                                            case 'ruta': ?>
                                                <td><?= htmlspecialchars($row['ruta']); ?></td>
                                                <?php break;
                                                
                                            case 'tipo_vehiculo': ?>
                                                <td><?= htmlspecialchars($row['tipo_vehiculo']); ?></td>
                                                <?php break;
                                                
                                            case 'empresa': ?>
                                                <td><?= !empty($row['empresa']) ? htmlspecialchars($row['empresa']) : '<span class="text-muted">—</span>'; ?></td>
                                                <?php break;
                                                
                                            case 'pago_parcial': ?>
                                                <td>
                                                    <?php if ($pagoParcial !== null && $pagoParcial !== ''): ?>
                                                        <span class="badge bg-info text-dark">
                                                            $<?= number_format((int)$pagoParcial, 0, ',', '.') ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php break;
                                                
                                            case 'imagen': ?>
                                                <td>
                                                    <?php if(!empty($row['imagen'])): ?>
                                                        <a href="#" data-bs-toggle="modal" data-bs-target="#imgModal<?= $id_registro; ?>">
                                                            <img src="uploads/<?= htmlspecialchars($row['imagen']); ?>" width="70" class="rounded img-thumb">
                                                        </a>
                                                        <div class="modal fade" id="imgModal<?= $id_registro; ?>" tabindex="-1">
                                                            <div class="modal-dialog modal-dialog-centered">
                                                                <div class="modal-content">
                                                                    <div class="modal-body text-center">
                                                                        <img src="uploads/<?= htmlspecialchars($row['imagen']); ?>" class="img-fluid rounded">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php break;
                                        endswitch;
                                    endforeach; ?>
                                    
                                    <!-- Columna de acciones (siempre visible) -->
                                    <td>
                                        <a href="?accion=editar&id=<?= $id_registro; ?>" class="btn btn-warning btn-sm">✏ Editar</a>
                                        <a href="?accion=eliminar&id=<?= $id_registro; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Seguro de eliminar?')">🗑 Eliminar</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= count(array_filter($_SESSION['columnas_visibles'], fn($c) => $c['visible'])) + 2 ?>" class="text-center py-4">
                                    No se encontraron resultados.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/i18n/es.min.js"></script>
<script>
// Pasar los IDs visibles a los formularios de selección
document.addEventListener('DOMContentLoaded', function() {
    const idsVisiblesArray = <?= json_encode($ids_visibles ?? []) ?>;
    
    const input1 = document.getElementById('idsVisibles');
    const input2 = document.getElementById('idsVisibles2');
    if (input1) input1.value = idsVisiblesArray.join(',');
    if (input2) input2.value = idsVisiblesArray.join(',');
    
    // Inicializar tooltips de Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Inicializar Select2 para multiselect
    $('.select2-multiple').select2({
        width: '100%',
        placeholder: function() {
            return $(this).data('placeholder');
        },
        allowClear: true,
        language: 'es'
    });
    
    // Inicializar Select2 para select simples
    $('.select2-single').select2({
        width: '100%',
        placeholder: '-- Seleccionar --',
        allowClear: true,
        language: 'es'
    });
    
    // Toggle del dropdown de columnas
    const btnConfig = document.getElementById('btnConfigColumnas');
    const dropdown = document.getElementById('dropdownColumnas');
    
    if (btnConfig && dropdown) {
        btnConfig.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });
        
        // Cerrar dropdown al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target) && !btnConfig.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });
        
        // Evitar que se cierre al hacer clic dentro
        dropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
});

// Validación para edición múltiple
document.getElementById('formEditarMultiple')?.addEventListener('submit', function(e) {
    const totalRegistros = <?= count($viajes_seleccionados ?? []) ?>;
    if (totalRegistros === 0) {
        e.preventDefault();
        alert('No hay registros para editar.');
        return false;
    }
    
    if (!confirm(`¿Estás segura de editar ${totalRegistros} registros?`)) {
        e.preventDefault();
        return false;
    }
});
</script>
</body>
</html>