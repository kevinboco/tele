<?php
// index2.php - Sistema completo de gestión de viajes
session_start();
include("conexion.php");

// ================== CONFIGURACIÓN INICIAL ==================
$accion = $_GET['accion'] ?? 'listar';
$id = $_GET['id'] ?? 0;
$mensaje = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

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
        if (!isset($_POST['ids']) || empty($_POST['ids'])) {
            header("Location: ?error=no_ids");
            exit();
        }
        
        $ids = array_map('intval', $_POST['ids']);
        $ids_str = implode(',', $ids);
        
        if ($_POST['accion_multiple'] == 'eliminar') {
            // Eliminar múltiples registros
            $sql = "DELETE FROM viajes WHERE id IN ($ids_str)";
            if ($conexion->query($sql)) {
                header("Location: ?msg=multi_eliminado&count=" . count($ids));
            } else {
                header("Location: ?error=eliminar");
            }
            exit();
        }
        elseif ($_POST['accion_multiple'] == 'editar_empresa') {
            // Redirigir a edición de empresa múltiple
            $_SESSION['editar_multi_ids'] = $ids;
            header("Location: ?accion=editar_multi");
            exit();
        }
    }
    
    // EDITAR EMPRESA MÚLTIPLE
    elseif (isset($_POST['editar_multi_empresa'])) {
        if (!isset($_SESSION['editar_multi_ids']) || empty($_SESSION['editar_multi_ids'])) {
            header("Location: ?error=no_ids");
            exit();
        }
        
        $ids = $_SESSION['editar_multi_ids'];
        $empresa = isset($_POST['empresa']) && trim($_POST['empresa']) !== '' 
            ? "'" . $conexion->real_escape_string($_POST['empresa']) . "'" 
            : "NULL";
        
        $ids_str = implode(',', array_map('intval', $ids));
        $sql = "UPDATE viajes SET empresa = $empresa WHERE id IN ($ids_str)";
        
        if ($conexion->query($sql)) {
            unset($_SESSION['editar_multi_ids']);
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
    <?php elseif ($accion == 'editar_multi' && isset($_SESSION['editar_multi_ids'])): ?>
        <?php $ids = $_SESSION['editar_multi_ids']; ?>
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
        <!-- FILTROS -->
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">🔍 Filtros de búsqueda</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
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
                        <a href="?" class="btn btn-secondary w-100">❌ Limpiar</a>
                    </div>
                </form>
            </div>
        </div>

        <?php
        // ================== CONSTRUIR CONSULTA CON FILTROS ==================
        $where = [];

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
                <h3 class="mb-0">📋 Listado de Viajes</h3>
                <a href="?accion=crear" class="btn btn-success">➕ Nuevo Viaje</a>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width:32px;"><input type="checkbox" id="selectAll"></th>
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
                                <?php while($row = $resultado->fetch_assoc()): ?>
                                    <tr>
                                        <td><input type="checkbox" name="ids[]" value="<?= (int)$row['id']; ?>"></td>
                                        <td><?= (int)$row['id']; ?></td>
                                        <td><?= htmlspecialchars($row['nombre']); ?></td>
                                        <td><?= !empty($row['cedula']) ? htmlspecialchars($row['cedula']) : '<span class="text-muted">—</span>'; ?></td>
                                        <td><?= htmlspecialchars($row['fecha']); ?></td>
                                        <td><?= htmlspecialchars($row['ruta']); ?></td>
                                        <td><?= htmlspecialchars($row['tipo_vehiculo']); ?></td>
                                        <td><?= !empty($row['empresa']) ? htmlspecialchars($row['empresa']) : '<span class="text-muted">—</span>'; ?></td>
                                        <td>
                                            <?php if(!empty($row['imagen'])): ?>
                                                <a href="#" data-bs-toggle="modal" data-bs-target="#imgModal<?= (int)$row['id']; ?>">
                                                    <img src="uploads/<?= htmlspecialchars($row['imagen']); ?>" width="70" class="rounded img-thumb">
                                                </a>
                                                <div class="modal fade" id="imgModal<?= (int)$row['id']; ?>" tabindex="-1">
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
                                            <a href="?accion=editar&id=<?= (int)$row['id']; ?>" class="btn btn-warning btn-sm">✏ Editar</a>
                                            <a href="?accion=eliminar&id=<?= (int)$row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Seguro de eliminar?')">🗑 Eliminar</a>
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
                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" name="accion_multiple" value="eliminar" class="btn btn-danger"
                                onclick="return confirm('¿Eliminar los registros seleccionados?')">
                            🗑 Eliminar Seleccionados
                        </button>
                        <button type="submit" name="accion_multiple" value="editar_empresa" class="btn btn-warning">
                            ✏ Editar Empresa
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Seleccionar/deseleccionar todos los checkboxes
document.getElementById("selectAll").onclick = function() {
    const checkboxes = document.getElementsByName("ids[]");
    for (const checkbox of checkboxes) {
        checkbox.checked = this.checked;
    }
};

// Validar que al menos un checkbox esté seleccionado para acciones múltiples
document.querySelector('form').addEventListener('submit', function(e) {
    const accionMultiple = e.submitter && e.submitter.name === 'accion_multiple';
    if (accionMultiple) {
        const checkboxes = document.getElementsByName("ids[]");
        let seleccionado = false;
        for (const checkbox of checkboxes) {
            if (checkbox.checked) {
                seleccionado = true;
                break;
            }
        }
        if (!seleccionado) {
            e.preventDefault();
            alert('Por favor, selecciona al menos un registro.');
        }
    }
});
</script>
</body>
</html>