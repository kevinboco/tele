<?php
// index2.php - Sistema completo de gestión de viajes
session_start();
include("conexion.php");

// ================== CONFIGURACIÓN INICIAL ==================
$accion = $_GET['accion'] ?? 'listar';
$id = $_GET['id'] ?? 0;
$mensaje = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Inicializar array de selección si no existe
if (!isset($_SESSION['seleccionados'])) {
    $_SESSION['seleccionados'] = [];
}

// ================== MANEJO DE SELECCIÓN ==================
// Agregar/eliminar IDs de la selección
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
    $ids_visibles = $_POST['ids_visibles'];
    
    if ($_POST['seleccionar_todos'] == '1') {
        // Agregar todos los visibles a la selección
        foreach ($ids_visibles as $id_visible) {
            $id_visible = (int)$id_visible;
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

// Limpiar selección
if (isset($_POST['limpiar_seleccion'])) {
    $_SESSION['seleccionados'] = [];
}

// ================== FUNCIONES AUXILIARES ==================
function obtenerListas($conexion) {
    $listas = [
        'nombres' => [],
        'cedulas' => [],
        'rutas' => [],
        'vehiculos' => [],
        'empresas' => []
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

// ================== PROCESAR ACCIONES ==================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CREAR NUEVO VIAJE
    if (isset($_POST['crear'])) {
        $nombre = $conexion->real_escape_string($_POST['nombre'] ?? '');
        $cedula = isset($_POST['cedula']) && trim($_POST['cedula']) !== '' 
            ? "'" . $conexion->real_escape_string($_POST['cedula']) . "'" 
            : "NULL";
        $fecha = $conexion->real_escape_string($_POST['fecha'] ?? '');
        $ruta = $conexion->real_escape_string($_POST['ruta'] ?? '');
        $tipo_vehiculo = $conexion->real_escape_string($_POST['tipo_vehiculo'] ?? '');
        $empresa = isset($_POST['empresa']) && trim($_POST['empresa']) !== '' 
            ? "'" . $conexion->real_escape_string($_POST['empresa']) . "'" 
            : "NULL";
        
        // Manejo de imagen
        $imagen_nombre = "NULL";
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $imagen_nombre = basename($_FILES['imagen']['name']);
            $imagen_temp = $_FILES['imagen']['tmp_name'];
            $ruta_destino = "uploads/" . $imagen_nombre;
            
            if (!is_dir('uploads')) mkdir('uploads', 0777, true);
            
            if (move_uploaded_file($imagen_temp, $ruta_destino)) {
                $imagen_nombre = "'" . $conexion->real_escape_string($imagen_nombre) . "'";
            } else {
                $imagen_nombre = "NULL";
            }
        }
        
        if (empty($nombre) || empty($fecha) || empty($ruta) || empty($tipo_vehiculo)) {
            $_SESSION['error'] = "Los campos Nombre, Fecha, Ruta y Vehículo son obligatorios.";
            $accion = 'crear';
        } else {
            $sql = "INSERT INTO viajes (nombre, cedula, fecha, ruta, tipo_vehiculo, empresa, imagen) 
                    VALUES ('$nombre', $cedula, '$fecha', '$ruta', '$tipo_vehiculo', $empresa, $imagen_nombre)";
            
            if ($conexion->query($sql)) {
                header("Location: ?msg=creado");
                exit();
            } else {
                $_SESSION['error'] = "Error al crear: " . $conexion->error;
                $accion = 'crear';
            }
        }
    }
    
    // EDITAR VIAJE
    elseif (isset($_POST['editar'])) {
        $id = (int)$_POST['id'];
        $nombre = $conexion->real_escape_string($_POST['nombre'] ?? '');
        $cedula = isset($_POST['cedula']) && trim($_POST['cedula']) !== '' 
            ? "'" . $conexion->real_escape_string($_POST['cedula']) . "'" 
            : "NULL";
        $fecha = $conexion->real_escape_string($_POST['fecha'] ?? '');
        $ruta = $conexion->real_escape_string($_POST['ruta'] ?? '');
        $tipo_vehiculo = $conexion->real_escape_string($_POST['tipo_vehiculo'] ?? '');
        $empresa = isset($_POST['empresa']) && trim($_POST['empresa']) !== '' 
            ? "'" . $conexion->real_escape_string($_POST['empresa']) . "'" 
            : "NULL";
        
        // Manejo de imagen
        $imagen_campo = '';
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $imagen_nombre = basename($_FILES['imagen']['name']);
            $imagen_temp = $_FILES['imagen']['tmp_name'];
            $ruta_destino = "uploads/" . $imagen_nombre;
            
            if (!is_dir('uploads')) mkdir('uploads', 0777, true);
            
            if (move_uploaded_file($imagen_temp, $ruta_destino)) {
                $imagen_campo = ", imagen = '" . $conexion->real_escape_string($imagen_nombre) . "'";
            }
        } elseif (isset($_POST['eliminar_imagen']) && $_POST['eliminar_imagen'] == '1') {
            $imagen_campo = ", imagen = NULL";
        }
        
        if (empty($nombre) || empty($fecha) || empty($ruta) || empty($tipo_vehiculo)) {
            $_SESSION['error'] = "Los campos Nombre, Fecha, Ruta y Vehículo son obligatorios.";
            $accion = 'editar';
        } else {
            $sql = "UPDATE viajes SET 
                    nombre = '$nombre',
                    cedula = $cedula,
                    fecha = '$fecha',
                    ruta = '$ruta',
                    tipo_vehiculo = '$tipo_vehiculo',
                    empresa = $empresa
                    $imagen_campo
                    WHERE id = $id";
            
            if ($conexion->query($sql)) {
                header("Location: ?msg=editado");
                exit();
            } else {
                $_SESSION['error'] = "Error al actualizar: " . $conexion->error;
                $accion = 'editar';
            }
        }
    }
    
    // ACCIONES MÚLTIPLES
    elseif (isset($_POST['accion_multiple'])) {
        if (empty($_SESSION['seleccionados'])) {
            header("Location: ?error=no_ids");
            exit();
        }
        
        $ids = $_SESSION['seleccionados'];
        $ids_str = implode(',', array_map('intval', $ids));
        
        if ($_POST['accion_multiple'] == 'eliminar') {
            // Eliminar múltiples registros
            $sql = "DELETE FROM viajes WHERE id IN ($ids_str)";
            if ($conexion->query($sql)) {
                // Limpiar selección después de eliminar
                $_SESSION['seleccionados'] = [];
                header("Location: ?msg=multi_eliminado&count=" . count($ids));
            } else {
                header("Location: ?error=eliminar");
            }
            exit();
        }
        elseif ($_POST['accion_multiple'] == 'editar_empresa') {
            // Redirigir a edición de empresa múltiple
            header("Location: ?accion=editar_multi");
            exit();
        }
    }
    
    // EDITAR EMPRESA MÚLTIPLE
    elseif (isset($_POST['editar_multi_empresa'])) {
        if (empty($_SESSION['seleccionados'])) {
            header("Location: ?error=no_ids");
            exit();
        }
        
        $ids = $_SESSION['seleccionados'];
        $empresa = isset($_POST['empresa']) && trim($_POST['empresa']) !== '' 
            ? "'" . $conexion->real_escape_string($_POST['empresa']) . "'" 
            : "NULL";
        
        $ids_str = implode(',', array_map('intval', $ids));
        $sql = "UPDATE viajes SET empresa = $empresa WHERE id IN ($ids_str)";
        
        if ($conexion->query($sql)) {
            // Limpiar selección después de editar
            $_SESSION['seleccionados'] = [];
            header("Location: ?msg=multi_editado&count=" . count($ids));
            exit();
        } else {
            $_SESSION['error'] = "Error al actualizar: " . $conexion->error;
            $accion = 'editar_multi';
        }
    }
}

// ================== ELIMINAR INDIVIDUAL ==================
if ($accion == 'eliminar' && $id > 0) {
    $sql = "DELETE FROM viajes WHERE id = $id";
    if ($conexion->query($sql)) {
        // Remover de la selección si estaba seleccionado
        if (($key = array_search($id, $_SESSION['seleccionados'])) !== false) {
            unset($_SESSION['seleccionados'][$key]);
            $_SESSION['seleccionados'] = array_values($_SESSION['seleccionados']);
        }
        header("Location: ?msg=eliminado");
        exit();
    } else {
        header("Location: ?error=eliminar");
        exit();
    }
}

// ================== OBTENER DATOS PARA EDICIÓN ==================
$viaje = null;
if ($accion == 'editar' && $id > 0) {
    $res = $conexion->query("SELECT * FROM viajes WHERE id = $id");
    if ($res && $res->num_rows > 0) {
        $viaje = $res->fetch_assoc();
    } else {
        $accion = 'listar';
    }
}

// ================== OBTENER LISTAS PARA FILTROS ==================
$listas = obtenerListas($conexion);
$error_msg = $_SESSION['error'] ?? null;
if (isset($_SESSION['error'])) unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Viajes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table-hover tbody tr:hover { background-color: rgba(0,0,0,.025); }
        .img-thumb { max-width: 70px; height: auto; }
        .required:after { content: " *"; color: red; }
        .seleccionado { background-color: rgba(25, 135, 84, 0.1) !important; }
        .checkbox-seleccion { cursor: pointer; }
    </style>
</head>
<body class="bg-light">
<!-- NAVEGACIÓN -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
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
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header <?= $accion == 'crear' ? 'bg-success' : 'bg-warning' ?> text-white">
                        <h3 class="mb-0">
                            <?= $accion == 'crear' ? '➕ Nuevo Viaje' : '✏ Editar Viaje' ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <?php if ($accion == 'editar'): ?>
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <input type="hidden" name="editar" value="1">
                            <?php else: ?>
                                <input type="hidden" name="crear" value="1">
                            <?php endif; ?>
                            
                            <!-- Nombre -->
                            <div class="mb-3">
                                <label class="form-label required">Nombre</label>
                                <input type="text" name="nombre" class="form-control" 
                                       value="<?= $viaje['nombre'] ?? '' ?>" required>
                            </div>
                            
                            <!-- Cédula (opcional) -->
                            <div class="mb-3">
                                <label class="form-label">Cédula</label>
                                <input type="text" name="cedula" class="form-control" 
                                       value="<?= $viaje['cedula'] ?? '' ?>"
                                       placeholder="Opcional - puede estar vacío">
                            </div>
                            
                            <!-- Fecha -->
                            <div class="mb-3">
                                <label class="form-label required">Fecha</label>
                                <input type="date" name="fecha" class="form-control" 
                                       value="<?= $viaje['fecha'] ?? '' ?>" required>
                            </div>
                            
                            <!-- Ruta -->
                            <div class="mb-3">
                                <label class="form-label required">Ruta</label>
                                <select name="ruta" class="form-select" required>
                                    <option value="">-- Seleccionar --</option>
                                    <?php foreach($listas['rutas'] as $rutaItem): ?>
                                        <option value="<?= htmlspecialchars($rutaItem) ?>"
                                            <?= (isset($viaje['ruta']) && $rutaItem == $viaje['ruta']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($rutaItem) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Tipo de Vehículo -->
                            <div class="mb-3">
                                <label class="form-label required">Tipo de Vehículo</label>
                                <select name="tipo_vehiculo" class="form-select" required>
                                    <option value="">-- Seleccionar --</option>
                                    <?php foreach($listas['vehiculos'] as $vehItem): ?>
                                        <option value="<?= htmlspecialchars($vehItem) ?>"
                                            <?= (isset($viaje['tipo_vehiculo']) && $vehItem == $viaje['tipo_vehiculo']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($vehItem) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Empresa -->
                            <div class="mb-3">
                                <label class="form-label">Empresa</label>
                                <select name="empresa" class="form-select">
                                    <option value="">-- Ninguna --</option>
                                    <?php foreach($listas['empresas'] as $empItem): ?>
                                        <option value="<?= htmlspecialchars($empItem) ?>"
                                            <?= (isset($viaje['empresa']) && $empItem == $viaje['empresa']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($empItem) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Imagen actual (solo en edición) -->
                            <?php if ($accion == 'editar' && isset($viaje['imagen']) && !empty($viaje['imagen'])): ?>
                                <div class="mb-3">
                                    <label class="form-label">Imagen actual</label>
                                    <div class="mb-2">
                                        <img src="uploads/<?= htmlspecialchars($viaje['imagen']) ?>" 
                                             class="img-thumbnail img-thumb">
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="eliminar_imagen" value="1" id="eliminarImg">
                                            <label class="form-check-label" for="eliminarImg">
                                                Eliminar imagen actual
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Nueva imagen -->
                            <div class="mb-3">
                                <label class="form-label">
                                    <?= $accion == 'crear' ? 'Imagen (opcional)' : 'Nueva imagen (opcional)' ?>
                                </label>
                                <input type="file" name="imagen" class="form-control" accept="image/*">
                                <small class="text-muted">
                                    <?= $accion == 'editar' ? 'Dejar en blanco para mantener la imagen actual' : '' ?>
                                </small>
                            </div>
                            
                            <!-- Botones -->
                            <div class="d-flex justify-content-between">
                                <a href="?" class="btn btn-secondary">Cancelar</a>
                                <button type="submit" class="btn <?= $accion == 'crear' ? 'btn-success' : 'btn-warning' ?>">
                                    <?= $accion == 'crear' ? 'Crear Viaje' : 'Guardar Cambios' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    <!-- ================== EDITAR MÚLTIPLES EMPRESAS ================== -->
    <?php elseif ($accion == 'editar_multi' && !empty($_SESSION['seleccionados'])): ?>
        <?php $ids = $_SESSION['seleccionados']; ?>
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark">
                        <h3 class="mb-0">✏ Editar Empresa para Múltiples Viajes (<?= count($ids) ?> seleccionados)</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="editar_multi_empresa" value="1">
                            
                            <div class="alert alert-info">
                                <strong>Nota:</strong> Solo puedes editar el campo empresa para múltiples registros a la vez.
                                Para editar otros campos, edita cada registro individualmente.
                            </div>
                            
                            <!-- Empresa -->
                            <div class="mb-4">
                                <label class="form-label"><strong>Empresa</strong></label>
                                <select name="empresa" class="form-select">
                                    <option value="">-- Mantener valor actual --</option>
                                    <?php foreach($listas['empresas'] as $empItem): ?>
                                        <option value="<?= htmlspecialchars($empItem) ?>">
                                            <?= htmlspecialchars($empItem) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Selecciona una empresa para asignarla a todos los registros seleccionados</small>
                            </div>
                            
                            <!-- Botones -->
                            <div class="d-flex justify-content-between">
                                <a href="?" class="btn btn-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-warning">Actualizar <?= count($ids) ?> Registros</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    <!-- ================== LISTADO PRINCIPAL ================== -->
    <?php else: ?>
        <!-- CONTADOR DE SELECCIONADOS -->
        <?php if (!empty($_SESSION['seleccionados'])): ?>
            <div class="alert alert-info alert-dismissible fade show">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>✅ Seleccionados:</strong> <?= count($_SESSION['seleccionados']) ?> viaje(s)
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

        <!-- FILTROS -->
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">🔍 Filtros de búsqueda</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3" id="filtrosForm">
                    <!-- NOMBRE -->
                    <div class="col-md-2">
                        <label class="form-label">Nombre</label>
                        <select name="nombre" class="form-select">
                            <option value="">-- Todos --</option>
                            <?php
                            $nombreSel = $_GET['nombre'] ?? '';
                            foreach($listas['nombres'] as $nom):
                                $sel = ($nombreSel === $nom) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($nom) ?>" <?= $sel ?>>
                                    <?= htmlspecialchars($nom) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- CÉDULA -->
                    <div class="col-md-2">
                        <label class="form-label">Cédula</label>
                        <select name="cedula" class="form-select">
                            <option value="">-- Todas --</option>
                            <?php
                            $cedSel = $_GET['cedula'] ?? '';
                            foreach($listas['cedulas'] as $ced):
                                $sel = ($cedSel === $ced) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($ced) ?>" <?= $sel ?>>
                                    <?= htmlspecialchars($ced) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- FECHA DESDE/HASTA -->
                    <div class="col-md-2">
                        <label class="form-label">Fecha desde</label>
                        <input type="date" name="desde" value="<?= $_GET['desde'] ?? '' ?>" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Fecha hasta</label>
                        <input type="date" name="hasta" value="<?= $_GET['hasta'] ?? '' ?>" class="form-control">
                    </div>

                    <!-- RUTA -->
                    <div class="col-md-2">
                        <label class="form-label">Ruta</label>
                        <select name="ruta" class="form-select">
                            <option value="">-- Todas --</option>
                            <?php
                            $rutaSel = $_GET['ruta'] ?? '';
                            foreach($listas['rutas'] as $ruta):
                                $sel = ($rutaSel === $ruta) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($ruta) ?>" <?= $sel ?>>
                                    <?= htmlspecialchars($ruta) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- VEHÍCULO -->
                    <div class="col-md-2">
                        <label class="form-label">Vehículo</label>
                        <select name="vehiculo" class="form-select">
                            <option value="">-- Todos --</option>
                            <?php
                            $vehSel = $_GET['vehiculo'] ?? '';
                            foreach($listas['vehiculos'] as $veh):
                                $sel = ($vehSel === $veh) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($veh) ?>" <?= $sel ?>>
                                    <?= htmlspecialchars($veh) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- EMPRESA -->
                    <div class="col-md-2">
                        <label class="form-label">Empresa</label>
                        <select name="empresa" class="form-select">
                            <option value="">-- Todas --</option>
                            <?php
                            $empSel = $_GET['empresa'] ?? '';
                            foreach($listas['empresas'] as $emp):
                                $sel = ($empSel === $emp) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($emp) ?>" <?= $sel ?>>
                                    <?= htmlspecialchars($emp) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- BOTONES -->
                    <div class="col-md-2 align-self-end">
                        <button type="submit" class="btn btn-success w-100">🔎 Buscar</button>
                    </div>
                    <div class="col-md-2 align-self-end">
                        <a href="?" class="btn btn-secondary w-100">❌ Limpiar filtros</a>
                    </div>
                </form>
            </div>
        </div>

        <?php
        // ================== CONSTRUIR CONSULTA CON FILTROS ==================
        $where = [];
        $ids_visibles = []; // Para guardar los IDs visibles actualmente

        if (!empty($_GET['nombre'])) {
            $nombre = $conexion->real_escape_string($_GET['nombre']);
            $where[] = "nombre = '$nombre'";
        }
        if (!empty($_GET['cedula'])) {
            $cedula = $conexion->real_escape_string($_GET['cedula']);
            $where[] = "cedula = '$cedula'";
        }
        if (!empty($_GET['desde']) && !empty($_GET['hasta'])) {
            $desde = $conexion->real_escape_string($_GET['desde']);
            $hasta = $conexion->real_escape_string($_GET['hasta']);
            $where[] = "fecha BETWEEN '$desde' AND '$hasta'";
        } elseif (!empty($_GET['desde'])) {
            $desde = $conexion->real_escape_string($_GET['desde']);
            $where[] = "fecha >= '$desde'";
        } elseif (!empty($_GET['hasta'])) {
            $hasta = $conexion->real_escape_string($_GET['hasta']);
            $where[] = "fecha <= '$hasta'";
        }
        if (!empty($_GET['ruta'])) {
            $ruta = $conexion->real_escape_string($_GET['ruta']);
            $where[] = "ruta = '$ruta'";
        }
        if (!empty($_GET['vehiculo'])) {
            $vehiculo = $conexion->real_escape_string($_GET['vehiculo']);
            $where[] = "tipo_vehiculo = '$vehiculo'";
        }
        if (!empty($_GET['empresa'])) {
            $empresa = $conexion->real_escape_string($_GET['empresa']);
            $where[] = "empresa = '$empresa'";
        }

        $sql = "SELECT * FROM viajes";
        if (count($where) > 0) $sql .= " WHERE " . implode(" AND ", $where);
        $sql .= " ORDER BY fecha DESC, id DESC";
        
        $resultado = $conexion->query($sql);

        // Contar viajes por nombre si hay filtro
        if (!empty($_GET['nombre'])):
            $nombreFiltro = $conexion->real_escape_string($_GET['nombre']);
            $sqlContar = "SELECT COUNT(*) AS total FROM viajes WHERE nombre = '$nombreFiltro'";
            $resContar = $conexion->query($sqlContar);
            $totalViajes = $resContar ? (int)$resContar->fetch_assoc()['total'] : 0;
        ?>
            <div class="alert alert-info">
                <strong><?= htmlspecialchars($_GET['nombre']) ?></strong> ha hecho <b><?= $totalViajes ?></b> viajes.
            </div>
        <?php endif; ?>

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
                    <?php if (!empty($_SESSION['seleccionados'])): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="limpiar_seleccion" value="1">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                🗑 Limpiar toda la selección
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:32px;">Sel.</th>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Cédula</th>
                                <th>Fecha</th>
                                <th>Ruta</th>
                                <th>Vehículo</th>
                                <th>Empresa</th>
                                <th>Imagen</th>
                                <th style="width:160px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($resultado && $resultado->num_rows > 0): ?>
                            <?php while($row = $resultado->fetch_assoc()): 
                                $id_registro = (int)$row['id'];
                                $ids_visibles[] = $id_registro;
                                $esta_seleccionado = in_array($id_registro, $_SESSION['seleccionados']);
                            ?>
                                <tr id="fila_<?= $id_registro ?>" class="<?= $esta_seleccionado ? 'seleccionado' : '' ?>">
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="toggle_seleccion" value="<?= $id_registro ?>">
                                            <input type="checkbox" 
                                                   class="form-check-input checkbox-seleccion" 
                                                   onchange="this.form.submit()"
                                                   <?= $esta_seleccionado ? 'checked' : '' ?>>
                                        </form>
                                    </td>
                                    <td><?= $id_registro; ?></td>
                                    <td><?= htmlspecialchars($row['nombre']); ?></td>
                                    <td><?= !empty($row['cedula']) ? htmlspecialchars($row['cedula']) : '<span class="text-muted">—</span>'; ?></td>
                                    <td><?= htmlspecialchars($row['fecha']); ?></td>
                                    <td><?= htmlspecialchars($row['ruta']); ?></td>
                                    <td><?= htmlspecialchars($row['tipo_vehiculo']); ?></td>
                                    <td><?= !empty($row['empresa']) ? htmlspecialchars($row['empresa']) : '<span class="text-muted">—</span>'; ?></td>
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
                                    <td>
                                        <a href="?accion=editar&id=<?= $id_registro; ?>" class="btn btn-warning btn-sm">✏ Editar</a>
                                        <a href="?accion=eliminar&id=<?= $id_registro; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Seguro de eliminar?')">🗑 Eliminar</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center py-4">No se encontraron resultados.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- ACCIONES MÚLTIPLES -->
                <?php if (!empty($_SESSION['seleccionados'])): ?>
                    <div class="mt-3 p-3 border rounded bg-light">
                        <h5>📋 Acciones para los <?= count($_SESSION['seleccionados']) ?> viajes seleccionados:</h5>
                        <div class="d-flex gap-2 mt-2">
                            <form method="POST">
                                <button type="submit" name="accion_multiple" value="editar_empresa" class="btn btn-warning">
                                    ✏ Editar Empresa de Seleccionados
                                </button>
                            </form>
                            <form method="POST">
                                <button type="submit" name="accion_multiple" value="eliminar" class="btn btn-danger"
                                        onclick="return confirm('¿Eliminar los <?= count($_SESSION['seleccionados']) ?> registros seleccionados?')">
                                    🗑 Eliminar Seleccionados
                                </button>
                            </form>
                        </div>
                        <small class="text-muted d-block mt-2">
                            IDs seleccionados: <?= implode(', ', $_SESSION['seleccionados']) ?>
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Pasar los IDs visibles a los formularios de selección
document.addEventListener('DOMContentLoaded', function() {
    const idsVisibles = <?= json_encode($ids_visibles) ?>;
    
    // Actualizar los campos ocultos con los IDs visibles
    document.getElementById('idsVisibles').value = idsVisibles.join(',');
    document.getElementById('idsVisibles2').value = idsVisibles.join(',');
    
    // Actualizar contador en tiempo real
    function actualizarContador() {
        const seleccionados = document.querySelectorAll('.checkbox-seleccion:checked').length;
        const total = document.querySelectorAll('.checkbox-seleccion').length;
        
        // Actualizar badge si existe
        const badge = document.querySelector('.badge.bg-success');
        if (badge) {
            badge.textContent = `✅ ${seleccionados} seleccionado(s)`;
        }
    }
    
    // Escuchar cambios en checkboxes
    document.querySelectorAll('.checkbox-seleccion').forEach(checkbox => {
        checkbox.addEventListener('change', actualizarContador);
    });
    
    actualizarContador();
});

// Confirmación para acciones múltiples
document.addEventListener('submit', function(e) {
    if (e.target && e.target.querySelector('button[name="accion_multiple"]')) {
        const seleccionados = <?= count($_SESSION['seleccionados']) ?>;
        if (seleccionados === 0) {
            e.preventDefault();
            alert('Por favor, selecciona al menos un registro.');
            return false;
        }
    }
});
</script>
</body>
</html>