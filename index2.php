<?php
// index2.php - Sistema completo de gestión de viajes

// 1) SIEMPRE primero la sesión y NADA de HTML antes
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

// ================== MANEJO DE SELECCIÓN ==================
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
    
    // EDITAR VIAJE INDIVIDUAL
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
    
    // EDITAR MÚLTIPLES VIAJES (COMPLETO)
    elseif (isset($_POST['editar_multiple_completo'])) {
        if (empty($_SESSION['seleccionados'])) {
            header("Location: ?error=no_ids");
            exit();
        }
        
        $ids = $_SESSION['seleccionados'];
        $actualizados = 0;
        
        foreach ($ids as $id_viaje) {
            $id_viaje = (int)$id_viaje;
            
            $nombre_key   = "nombre_$id_viaje";
            $cedula_key   = "cedula_$id_viaje";
            $fecha_key    = "fecha_$id_viaje";
            $ruta_key     = "ruta_$id_viaje";
            $vehiculo_key = "tipo_vehiculo_$id_viaje";
            $empresa_key  = "empresa_$id_viaje";
            
            $nombre = isset($_POST[$nombre_key]) && trim($_POST[$nombre_key]) !== '' 
                ? "'" . $conexion->real_escape_string($_POST[$nombre_key]) . "'"
                : (isset($_POST['nombre_general']) && trim($_POST['nombre_general']) !== ''
                    ? "'" . $conexion->real_escape_string($_POST['nombre_general']) . "'"
                    : NULL);
            
            $cedula = isset($_POST[$cedula_key]) && trim($_POST[$cedula_key]) !== '' 
                ? "'" . $conexion->real_escape_string($_POST[$cedula_key]) . "'"
                : (isset($_POST['cedula_general']) && trim($_POST['cedula_general']) !== ''
                    ? "'" . $conexion->real_escape_string($_POST['cedula_general']) . "'"
                    : "NULL");
            
            $fecha = isset($_POST[$fecha_key]) && trim($_POST[$fecha_key]) !== '' 
                ? "'" . $conexion->real_escape_string($_POST[$fecha_key]) . "'"
                : (isset($_POST['fecha_general']) && trim($_POST['fecha_general']) !== ''
                    ? "'" . $conexion->real_escape_string($_POST['fecha_general']) . "'"
                    : NULL);
            
            $ruta = isset($_POST[$ruta_key]) && trim($_POST[$ruta_key]) !== '' 
                ? "'" . $conexion->real_escape_string($_POST[$ruta_key]) . "'"
                : (isset($_POST['ruta_general']) && trim($_POST['ruta_general']) !== ''
                    ? "'" . $conexion->real_escape_string($_POST['ruta_general']) . "'"
                    : NULL);
            
            $tipo_vehiculo = isset($_POST[$vehiculo_key]) && trim($_POST[$vehiculo_key]) !== '' 
                ? "'" . $conexion->real_escape_string($_POST[$vehiculo_key]) . "'"
                : (isset($_POST['tipo_vehiculo_general']) && trim($_POST['tipo_vehiculo_general']) !== ''
                    ? "'" . $conexion->real_escape_string($_POST['tipo_vehiculo_general']) . "'"
                    : NULL);
            
            $empresa = isset($_POST[$empresa_key]) && trim($_POST[$empresa_key]) !== '' 
                ? "'" . $conexion->real_escape_string($_POST[$empresa_key]) . "'"
                : (isset($_POST['empresa_general']) && trim($_POST['empresa_general']) !== ''
                    ? "'" . $conexion->real_escape_string($_POST['empresa_general']) . "'"
                    : "NULL");
            
            if (!$nombre || !$fecha || !$ruta || !$tipo_vehiculo) {
                continue;
            }
            
            $nombre        = ($nombre === NULL) ? "NULL" : $nombre;
            $fecha         = ($fecha === NULL) ? "NULL" : $fecha;
            $ruta          = ($ruta === NULL) ? "NULL" : $ruta;
            $tipo_vehiculo = ($tipo_vehiculo === NULL) ? "NULL" : $tipo_vehiculo;
            
            $sql = "UPDATE viajes SET 
                    nombre = $nombre,
                    cedula = $cedula,
                    fecha = $fecha,
                    ruta = $ruta,
                    tipo_vehiculo = $tipo_vehiculo,
                    empresa = $empresa
                    WHERE id = $id_viaje";
            
            if ($conexion->query($sql)) {
                $actualizados++;
            }
        }
        
        $_SESSION['seleccionados'] = [];
        header("Location: ?msg=multi_editado&count=$actualizados");
        exit();
    }
    
    // ACCIONES MÚLTIPLES DESDE LISTADO
    elseif (isset($_POST['accion_multiple'])) {

        if (empty($_SESSION['seleccionados'])) {
            $error = 'no_ids';
        } else {
            $ids = $_SESSION['seleccionados'];

            if ($_POST['accion_multiple'] == 'eliminar') {
                $ids_str = implode(',', array_map('intval', $ids));
                $sql = "DELETE FROM viajes WHERE id IN ($ids_str)";
                if ($conexion->query($sql)) {
                    $_SESSION['seleccionados'] = [];
                    header("Location: ?msg=multi_eliminado&count=" . count($ids));
                    exit();
                } else {
                    header("Location: ?error=eliminar");
                    exit();
                }
            }
            elseif ($_POST['accion_multiple'] == 'editar') {
                // 👇 En lugar de redirigir, solo cambiamos la acción
                $accion = 'editar_multiple';
            }
        }
    }
}

// ================== ELIMINAR INDIVIDUAL ==================
if ($accion == 'eliminar' && $id > 0) {
    $sql = "DELETE FROM viajes WHERE id = $id";
    if ($conexion->query($sql)) {
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

// ================== OBTENER DATOS PARA EDICIÓN MÚLTIPLE ==================
$viajes_seleccionados = [];
if ($accion == 'editar_multiple' && !empty($_SESSION['seleccionados'])) {
    $ids_str = implode(',', array_map('intval', $_SESSION['seleccionados']));
    $res = $conexion->query("SELECT * FROM viajes WHERE id IN ($ids_str) ORDER BY id ASC");
    if ($res) {
        while($row = $res->fetch_assoc()) {
            $viajes_seleccionados[] = $row;
        }
    }
}

// ================== OBTENER LISTAS PARA FILTROS ==================
$listas    = obtenerListas($conexion);
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
        .sticky-actions { position: sticky; top: 0; z-index: 1000; background: white; padding: 15px; margin: -15px -15px 15px -15px; border-bottom: 1px solid #dee2e6; box-shadow: 0 2px 4px rgba(0,0,0,.1); }
        .table-container { max-height: 600px; overflow-y: auto; }
        .form-control-sm { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
    </style>
</head>
<body class="bg-light">

<?php
// nav lateral / menú
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
        <!-- ... (tu formulario crear/editar se mantiene igual que lo tenías) ... -->
        <?php
        /* Para no hacer la respuesta infinita:
           Copia aquí exactamente el formulario que ya tenías de crear/editar,
           porque esa parte no afecta al problema de selección ni a editar múltiple.
        */
        ?>
    
    <!-- ================== EDITAR MÚLTIPLES VIAJES (COMPLETO) ================== -->
    <?php elseif ($accion == 'editar_multiple' && !empty($_SESSION['seleccionados'])): ?>
        <?php $total_seleccionados = count($_SESSION['seleccionados']); ?>
        <!-- aquí va exactamente la tabla de edición múltiple que ya tenías -->
        <!-- (la parte de HTML de editar_multiple no necesita cambios en la lógica) -->

    <!-- ================== LISTADO PRINCIPAL ================== -->
    <?php else: ?>
        <?php
        // aquí baja todo tu código del listado y filtros EXACTAMENTE igual,
        // solo cuidando que $ids_visibles esté definido
        ?>

        <?php
        // CONSTRUIR CONSULTA CON FILTROS  (igual que ya lo tenías)
        $where        = [];
        $ids_visibles = [];

        /* ... resto de tu código de filtros + listado ... */
        ?>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Pasar los IDs visibles a los formularios de selección
document.addEventListener('DOMContentLoaded', function() {
    const idsVisibles = <?= isset($ids_visibles) ? json_encode($ids_visibles) : '[]' ?>;
    
    const input1 = document.getElementById('idsVisibles');
    const input2 = document.getElementById('idsVisibles2');
    if (input1) input1.value = idsVisibles.join(',');
    if (input2) input2.value = idsVisibles.join(',');
    
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Validación para edición múltiple
document.getElementById('formEditarMultiple')?.addEventListener('submit', function(e) {
    const totalRegistros = <?= isset($viajes_seleccionados) ? count($viajes_seleccionados) : 0 ?>;
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
