<?php
// index2.php - Sistema completo de gestión de viajes con INFORME

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
    return (string)$n;
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

        $pago_parcial = normalizarPagoParcial($conexion, $_POST['pago_parcial'] ?? null);

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
            $sql = "INSERT INTO viajes (nombre, cedula, fecha, ruta, tipo_vehiculo, empresa, imagen, pago_parcial) 
                    VALUES ('$nombre', $cedula, '$fecha', '$ruta', '$tipo_vehiculo', $empresa, $imagen_nombre, $pago_parcial)";
            
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

        $pago_parcial = normalizarPagoParcial($conexion, $_POST['pago_parcial'] ?? null);
        
        // Obtener datos ACTUALES del registro para comparar
        $sql_actual = "SELECT nombre, cedula, fecha, ruta, tipo_vehiculo, empresa, pago_parcial FROM viajes WHERE id = $id";
        $res_actual = $conexion->query($sql_actual);
        $datos_actuales = $res_actual->fetch_assoc();
        
        // Detectar si SOLO cambió la cédula
        $solo_cambio_cedula = false;
        $cedula_nueva = isset($_POST['cedula']) ? trim($_POST['cedula']) : '';
        
        if ($datos_actuales) {
            $mismo_nombre = ($nombre == $datos_actuales['nombre']);
            $misma_fecha = ($fecha == $datos_actuales['fecha']);
            $misma_ruta = ($ruta == $datos_actuales['ruta']);
            $mismo_vehiculo = ($tipo_vehiculo == $datos_actuales['tipo_vehiculo']);
            
            $empresa_actual = $datos_actuales['empresa'] ?? '';
            $empresa_nueva = isset($_POST['empresa']) ? trim($_POST['empresa']) : '';
            $misma_empresa = ($empresa_actual == $empresa_nueva);
            
            $pago_actual = $datos_actuales['pago_parcial'] ?? null;
            $mismo_pago = ($pago_parcial === "NULL" && $pago_actual === null) || 
                         ($pago_parcial !== "NULL" && (int)$pago_parcial === (int)$pago_actual);
            
            $cedula_actual = $datos_actuales['cedula'] ?? '';
            $cambio_cedula = ($cedula_actual !== $cedula_nueva);
            
            if ($mismo_nombre && $misma_fecha && $misma_ruta && $mismo_vehiculo && 
                $misma_empresa && $mismo_pago && $cambio_cedula) {
                $solo_cambio_cedula = true;
            }
        }
        
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
                    empresa = $empresa,
                    pago_parcial = $pago_parcial
                    $imagen_campo
                    WHERE id = $id";
            
            if ($conexion->query($sql)) {
                if ($solo_cambio_cedula && !empty($cedula_nueva)) {
                    $nombre_escapado = $conexion->real_escape_string($nombre);
                    $valor_cedula = empty($cedula_nueva) ? "NULL" : "'" . $conexion->real_escape_string($cedula_nueva) . "'";
                    
                    $sql_masivo = "UPDATE viajes SET cedula = $valor_cedula 
                                   WHERE nombre = '$nombre_escapado' 
                                   AND id != $id";
                    
                    if ($conexion->query($sql_masivo)) {
                        $registros_afectados = $conexion->affected_rows;
                        header("Location: ?msg=editado_con_cedula&afectados=$registros_afectados&nombre=" . urlencode($nombre));
                        exit();
                    } else {
                        $_SESSION['error'] = "Error al actualizar cédulas masivas: " . $conexion->error;
                        $accion = 'editar';
                    }
                } else {
                    header("Location: ?msg=editado");
                    exit();
                }
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

        $pago_parcial_general = normalizarPagoParcial($conexion, $_POST['pago_parcial_general'] ?? null);
        $hay_pago_general = (isset($_POST['pago_parcial_general']) && trim((string)$_POST['pago_parcial_general']) !== '');

        foreach ($ids as $id_viaje) {
            $id_viaje = (int)$id_viaje;
            
            $nombre_key   = "nombre_$id_viaje";
            $cedula_key   = "cedula_$id_viaje";
            $fecha_key    = "fecha_$id_viaje";
            $ruta_key     = "ruta_$id_viaje";
            $vehiculo_key = "tipo_vehiculo_$id_viaje";
            $empresa_key  = "empresa_$id_viaje";
            $pago_key     = "pago_parcial_$id_viaje";
            
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

            $pago_parcial = "NULL";
            if (isset($_POST[$pago_key]) && trim((string)$_POST[$pago_key]) !== '') {
                $pago_parcial = normalizarPagoParcial($conexion, $_POST[$pago_key]);
            } elseif ($hay_pago_general) {
                $pago_parcial = $pago_parcial_general;
            }
            
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
                    empresa = $empresa,
                    pago_parcial = IFNULL($pago_parcial, pago_parcial)
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
            header("Location: ?error=no_ids");
            exit();
        }
        
        $ids = $_SESSION['seleccionados'];
        
        if ($_POST['accion_multiple'] == 'eliminar') {
            $ids_str = implode(',', array_map('intval', $ids));
            $sql = "DELETE FROM viajes WHERE id IN ($ids_str)";
            if ($conexion->query($sql)) {
                $_SESSION['seleccionados'] = [];
                header("Location: ?msg=multi_eliminado&count=" . count($ids));
            } else {
                header("Location: ?error=eliminar");
            }
            exit();
        }
        elseif ($_POST['accion_multiple'] == 'editar') {
            header("Location: ?accion=editar_multiple");
            exit();
        }
    }
}

// ================== ELIMINAR INDIVIDUAL ==================
if ($accion == 'eliminar' && $id > 0) {
    $sql = "DELETE FROM viajes WHERE id = " . (int)$id;
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

// ================== PROCESAR INFORME ==================
if ($accion == 'informe') {
    // Reconstruir los filtros exactamente como en el listado
    $where = [];
    $desc_filtros = [];

    // Nombre (array)
    if (!empty($_GET['nombre']) && is_array($_GET['nombre'])) {
        $nombres = array_map([$conexion, 'real_escape_string'], $_GET['nombre']);
        $nombres = array_filter($nombres, function($val) { return trim($val) !== ''; });
        if (!empty($nombres)) {
            $where[] = "nombre IN ('" . implode("','", $nombres) . "')";
            $desc_filtros[] = "Nombres: " . implode(', ', array_slice($nombres, 0, 3)) . (count($nombres) > 3 ? '...' : '');
        }
    }

    // Cédula (array)
    if (!empty($_GET['cedula']) && is_array($_GET['cedula'])) {
        $cedulas = array_map([$conexion, 'real_escape_string'], $_GET['cedula']);
        $cedulas = array_filter($cedulas, function($val) { return trim($val) !== ''; });
        if (!empty($cedulas)) {
            $where[] = "cedula IN ('" . implode("','", $cedulas) . "')";
            $desc_filtros[] = "Cédulas: " . implode(', ', array_slice($cedulas, 0, 3)) . (count($cedulas) > 3 ? '...' : '');
        }
    }

    // Fechas
    if (!empty($_GET['desde']) && !empty($_GET['hasta'])) {
        $desde = $conexion->real_escape_string($_GET['desde']);
        $hasta = $conexion->real_escape_string($_GET['hasta']);
        $where[] = "fecha BETWEEN '$desde' AND '$hasta'";
        $desc_filtros[] = "Fechas: $desde a $hasta";
    } elseif (!empty($_GET['desde'])) {
        $desde = $conexion->real_escape_string($_GET['desde']);
        $where[] = "fecha >= '$desde'";
        $desc_filtros[] = "Desde: $desde";
    } elseif (!empty($_GET['hasta'])) {
        $hasta = $conexion->real_escape_string($_GET['hasta']);
        $where[] = "fecha <= '$hasta'";
        $desc_filtros[] = "Hasta: $hasta";
    }

    // Ruta (array)
    if (!empty($_GET['ruta']) && is_array($_GET['ruta'])) {
        $rutas = array_map([$conexion, 'real_escape_string'], $_GET['ruta']);
        $rutas = array_filter($rutas, function($val) { return trim($val) !== ''; });
        if (!empty($rutas)) {
            $where[] = "ruta IN ('" . implode("','", $rutas) . "')";
            $desc_filtros[] = "Rutas: " . implode(', ', array_slice($rutas, 0, 3)) . (count($rutas) > 3 ? '...' : '');
        }
    }

    // Vehículo (array)
    if (!empty($_GET['vehiculo']) && is_array($_GET['vehiculo'])) {
        $vehiculos = array_map([$conexion, 'real_escape_string'], $_GET['vehiculo']);
        $vehiculos = array_filter($vehiculos, function($val) { return trim($val) !== ''; });
        if (!empty($vehiculos)) {
            $where[] = "tipo_vehiculo IN ('" . implode("','", $vehiculos) . "')";
            $desc_filtros[] = "Vehículos: " . implode(', ', array_slice($vehiculos, 0, 3)) . (count($vehiculos) > 3 ? '...' : '');
        }
    }

    // Empresa (array)
    if (!empty($_GET['empresa']) && is_array($_GET['empresa'])) {
        $empresas = array_map([$conexion, 'real_escape_string'], $_GET['empresa']);
        $empresas = array_filter($empresas, function($val) { return trim($val) !== ''; });
        if (!empty($empresas)) {
            $where[] = "empresa IN ('" . implode("','", $empresas) . "')";
            $desc_filtros[] = "Empresas: " . implode(', ', array_slice($empresas, 0, 3)) . (count($empresas) > 3 ? '...' : '');
        }
    }

    // Construir consulta principal
    $sql = "SELECT * FROM viajes";
    if (count($where) > 0) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY fecha DESC, id DESC";
    
    $resultado = $conexion->query($sql);
    
    // Obtener columnas visibles actuales
    $columnas_visibles = $_SESSION['columnas_visibles'];
    uasort($columnas_visibles, function($a, $b) {
        return $a['orden'] <=> $b['orden'];
    });
    
    // Generar informe HTML
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Informe de Viajes</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                margin: 30px;
                background: white;
            }
            h1 { 
                color: #333; 
                border-bottom: 2px solid #333;
                padding-bottom: 10px;
            }
            .info-filtros {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 20px;
                border-left: 4px solid #0d6efd;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-top: 20px;
                font-size: 14px;
            }
            th { 
                background: #343a40; 
                color: white; 
                padding: 12px 8px; 
                text-align: left;
                font-weight: bold;
            }
            td { 
                border: 1px solid #dee2e6; 
                padding: 8px; 
                vertical-align: top;
            }
            tr:nth-child(even) { 
                background: #f8f9fa; 
            }
            .fecha-generacion {
                color: #666;
                font-size: 12px;
                margin-top: 20px;
                text-align: right;
                border-top: 1px solid #dee2e6;
                padding-top: 15px;
            }
            .badge-pago {
                background: #0dcaf0;
                color: #000;
                padding: 3px 8px;
                border-radius: 4px;
                font-weight: bold;
            }
            .img-informe {
                max-width: 60px;
                max-height: 60px;
                border-radius: 4px;
            }
            .no-imagen {
                color: #999;
                font-style: italic;
            }
            .total-registros {
                background: #e9ecef;
                padding: 10px;
                border-radius: 4px;
                font-weight: bold;
            }
            @media print {
                .no-print { display: none; }
                body { margin: 15px; }
                th { background: #333 !important; color: white !important; }
                .badge-pago { background: #0dcaf0 !important; color: black !important; }
            }
            .btn-print {
                background: #0d6efd;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
                margin-bottom: 20px;
            }
            .btn-print:hover {
                background: #0b5ed7;
            }
            .btn-cerrar {
                background: #6c757d;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
                margin-bottom: 20px;
            }
            .btn-cerrar:hover {
                background: #5a6268;
            }
        </style>
    </head>
    <body>
        <div class="no-print" style="margin-bottom: 20px; display: flex; gap: 10px;">
            <button onclick="window.print()" class="btn-print">
                🖨️ Imprimir / Guardar PDF
            </button>
            <button onclick="window.close()" class="btn-cerrar">
                ❌ Cerrar
            </button>
        </div>
        
        <h1>🚗 Informe de Viajes</h1>
        
        <div class="info-filtros">
            <strong>📊 Columnas mostradas:</strong> 
            <?php 
            $nombres_columnas = [];
            foreach($columnas_visibles as $col) {
                if ($col['visible']) {
                    $nombres_columnas[] = $col['nombre'];
                }
            }
            echo implode(' · ', $nombres_columnas);
            ?><br>
            
            <strong>🔍 Filtros aplicados:</strong> 
            <?php if (!empty($desc_filtros)): ?>
                <?= htmlspecialchars(implode(' | ', $desc_filtros)) ?>
            <?php else: ?>
                Sin filtros (todos los registros)
            <?php endif; ?>
        </div>
        
        <?php if ($resultado && $resultado->num_rows > 0): ?>
            <div class="total-registros">
                📋 Total de registros: <?= $resultado->num_rows ?>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <?php foreach($columnas_visibles as $key => $columna): ?>
                            <?php if ($columna['visible']): ?>
                                <th><?= htmlspecialchars($columna['nombre']) ?></th>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $resultado->fetch_assoc()): ?>
                        <tr>
                            <?php foreach($columnas_visibles as $key => $columna): ?>
                                <?php if (!$columna['visible']) continue; ?>
                                
                                <?php switch($key): 
                                    case 'id': ?>
                                        <td><?= (int)$row['id'] ?></td>
                                        <?php break; ?>
                                    
                                    <?php case 'nombre': ?>
                                        <td><?= htmlspecialchars($row['nombre']) ?></td>
                                        <?php break; ?>
                                    
                                    <?php case 'cedula': ?>
                                        <td><?= !empty($row['cedula']) ? htmlspecialchars($row['cedula']) : '—' ?></td>
                                        <?php break; ?>
                                    
                                    <?php case 'fecha': ?>
                                        <td><?= date('d/m/Y', strtotime($row['fecha'])) ?></td>
                                        <?php break; ?>
                                    
                                    <?php case 'ruta': ?>
                                        <td><?= htmlspecialchars($row['ruta']) ?></td>
                                        <?php break; ?>
                                    
                                    <?php case 'tipo_vehiculo': ?>
                                        <td><?= htmlspecialchars($row['tipo_vehiculo']) ?></td>
                                        <?php break; ?>
                                    
                                    <?php case 'empresa': ?>
                                        <td><?= !empty($row['empresa']) ? htmlspecialchars($row['empresa']) : '—' ?></td>
                                        <?php break; ?>
                                    
                                    <?php case 'pago_parcial': ?>
                                        <td>
                                            <?php if ($row['pago_parcial'] !== null && $row['pago_parcial'] !== ''): ?>
                                                <span class="badge-pago">$<?= number_format((int)$row['pago_parcial'], 0, ',', '.') ?></span>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <?php break; ?>
                                    
                                    <?php case 'imagen': ?>
                                        <td>
                                            <?php if(!empty($row['imagen'])): ?>
                                                <img src="uploads/<?= htmlspecialchars($row['imagen']) ?>" 
                                                     class="img-informe" 
                                                     alt="Imagen"
                                                     onerror="this.style.display='none'">
                                            <?php else: ?>
                                                <span class="no-imagen">Sin imagen</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php break; ?>
                                    
                                <?php endswitch; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            
            <!-- Totales de pagos parciales (solo si la columna está visible) -->
            <?php 
            $columna_pago_visible = false;
            foreach($columnas_visibles as $key => $col) {
                if ($key == 'pago_parcial' && $col['visible']) {
                    $columna_pago_visible = true;
                    break;
                }
            }
            
            if ($columna_pago_visible):
                $sql_suma = "SELECT SUM(pago_parcial) as total FROM viajes";
                if (count($where) > 0) {
                    $sql_suma .= " WHERE " . implode(" AND ", $where);
                }
                $res_suma = $conexion->query($sql_suma);
                $total_pagos = $res_suma ? (int)$res_suma->fetch_assoc()['total'] : 0;
            ?>
                <div style="margin-top: 20px; padding: 15px; background: #e8f4ff; border-radius: 5px; text-align: right; font-size: 16px;">
                    <strong>💰 TOTAL PAGOS PARCIALES:</strong> 
                    <span style="background: #0d6efd; color: white; padding: 5px 15px; border-radius: 20px; margin-left: 10px;">
                        $<?= number_format($total_pagos, 0, ',', '.') ?>
                    </span>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div style="text-align: center; padding: 50px; background: #f8f9fa; border-radius: 5px;">
                <h3 style="color: #666;">📭 No hay registros para mostrar</h3>
                <p>No se encontraron viajes con los filtros seleccionados.</p>
            </div>
        <?php endif; ?>
        
        <div class="fecha-generacion">
            Informe generado desde Sistema de Gestión de Viajes<br>
            <?= date('d/m/Y H:i:s') ?>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// ================== OBTENER DATOS PARA EDICIÓN ==================
$viaje = null;
if ($accion == 'editar' && $id > 0) {
    $res = $conexion->query("SELECT * FROM viajes WHERE id = " . (int)$id);
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
        .columnas-dropdown { position: absolute; top: 100%; right: 0; z-index: 1000; background: white; border: 1px solid #dee2e6; border-radius: 0.375rem; box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15); min-width: 300px; padding: 1rem; display: none; }
        .columnas-dropdown.show { display: block; }
        .columna-checkbox { display: block; margin-bottom: 0.5rem; }
        .columna-checkbox input { margin-right: 0.5rem; }
        .badge-columna { background-color: #6c757d; cursor: pointer; }
        .badge-columna:hover { background-color: #5a6268; }
        .btn-informe {
            background-color: #198754;
            color: white;
        }
        .btn-informe:hover {
            background-color: #157347;
            color: white;
        }
    </style>
</head>
<body class="bg-light">

<?php include("nav.php"); ?>

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
                                <input type="hidden" name="id" value="<?= (int)$id ?>">
                                <input type="hidden" name="editar" value="1">
                            <?php else: ?>
                                <input type="hidden" name="crear" value="1">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label class="form-label required">Nombre</label>
                                <input type="text" name="nombre" class="form-control" 
                                       value="<?= htmlspecialchars($viaje['nombre'] ?? '') ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Cédula</label>
                                <input type="text" name="cedula" class="form-control" 
                                       value="<?= htmlspecialchars($viaje['cedula'] ?? '') ?>"
                                       placeholder="Opcional - puede estar vacío">
                                <small class="text-muted">
                                    <?php if ($accion == 'editar'): ?>
                                        <strong>NOTA:</strong> Si solo modifica la cédula y los demás campos quedan igual, 
                                        esta cédula se asignará automáticamente a <strong>TODOS</strong> los registros con el mismo nombre.
                                    <?php endif; ?>
                                </small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Pago parcial</label>
                                <input type="number" min="0" step="1" name="pago_parcial" class="form-control"
                                       value="<?= htmlspecialchars($viaje['pago_parcial'] ?? '') ?>"
                                       placeholder="Opcional - dejar vacío si no aplica">
                                <small class="text-muted">Monto entregado como anticipo / pago parcial (si aplica).</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label required">Fecha</label>
                                <input type="date" name="fecha" class="form-control" 
                                       value="<?= htmlspecialchars($viaje['fecha'] ?? '') ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label required">Ruta</label>
                                <select name="ruta" class="form-select select2-single" required>
                                    <option value="">-- Seleccionar --</option>
                                    <?php foreach($listas['rutas'] as $rutaItem): ?>
                                        <option value="<?= htmlspecialchars($rutaItem) ?>"
                                            <?= (isset($viaje['ruta']) && $rutaItem == $viaje['ruta']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($rutaItem) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label required">Tipo de Vehículo</label>
                                <select name="tipo_vehiculo" class="form-select select2-single" required>
                                    <option value="">-- Seleccionar --</option>
                                    <?php foreach($listas['vehiculos'] as $vehItem): ?>
                                        <option value="<?= htmlspecialchars($vehItem) ?>"
                                            <?= (isset($viaje['tipo_vehiculo']) && $vehItem == $viaje['tipo_vehiculo']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($vehItem) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Empresa</label>
                                <select name="empresa" class="form-select select2-single">
                                    <option value="">-- Ninguna --</option>
                                    <?php foreach($listas['empresas'] as $empItem): ?>
                                        <option value="<?= htmlspecialchars($empItem) ?>"
                                            <?= (isset($viaje['empresa']) && $empItem == $viaje['empresa']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($empItem) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
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
                            
                            <div class="mb-3">
                                <label class="form-label">
                                    <?= $accion == 'crear' ? 'Imagen (opcional)' : 'Nueva imagen (opcional)' ?>
                                </label>
                                <input type="file" name="imagen" class="form-control" accept="image/*">
                                <small class="text-muted">
                                    <?= $accion == 'editar' ? 'Dejar en blanco para mantener la imagen actual' : '' ?>
                                </small>
                            </div>
                            
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

    <!-- ================== EDITAR MÚLTIPLES VIAJES ================== -->
    <?php elseif ($accion == 'editar_multiple' && !empty($_SESSION['seleccionados'])): ?>
        <?php $total_seleccionados = count($_SESSION['seleccionados']); ?>
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark">
                        <h3 class="mb-0">✏ Editar Múltiples Viajes (<?= (int)$total_seleccionados ?> seleccionados)</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="formEditarMultiple">
                            <input type="hidden" name="editar_multiple_completo" value="1">
                            
                            <div class="alert alert-info">
                                <strong>Instrucciones:</strong> 
                                <ul class="mb-0">
                                    <li>Puedes editar campos individuales para cada registro</li>
                                    <li>También puedes usar los campos "Aplicar a todos" para cambiar un campo en todos los registros</li>
                                    <li>Los campos con <span class="required"></span> son obligatorios</li>
                                    <li>Dejar un campo en blanco mantiene su valor actual</li>
                                </ul>
                            </div>
                            
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0">🔧 Campos generales (aplicar a todos)</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Nombre (general)</label>
                                            <input type="text" name="nombre_general" class="form-control form-control-sm" 
                                                   placeholder="Dejar vacío para no cambiar">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Cédula (general)</label>
                                            <input type="text" name="cedula_general" class="form-control form-control-sm" 
                                                   placeholder="Dejar vacío para no cambiar">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Fecha (general)</label>
                                            <input type="date" name="fecha_general" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Pago parcial (general)</label>
                                            <input type="number" min="0" step="1" name="pago_parcial_general"
                                                   class="form-control form-control-sm"
                                                   placeholder="Dejar vacío para no cambiar">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Ruta (general)</label>
                                            <select name="ruta_general" class="form-select form-select-sm select2-single">
                                                <option value="">-- No cambiar --</option>
                                                <?php foreach($listas['rutas'] as $rutaItem): ?>
                                                    <option value="<?= htmlspecialchars($rutaItem) ?>">
                                                        <?= htmlspecialchars($rutaItem) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Vehículo (general)</label>
                                            <select name="tipo_vehiculo_general" class="form-select form-select-sm select2-single">
                                                <option value="">-- No cambiar --</option>
                                                <?php foreach($listas['vehiculos'] as $vehItem): ?>
                                                    <option value="<?= htmlspecialchars($vehItem) ?>">
                                                        <?= htmlspecialchars($vehItem) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Empresa (general)</label>
                                            <select name="empresa_general" class="form-select form-select-sm select2-single">
                                                <option value="">-- No cambiar --</option>
                                                <?php foreach($listas['empresas'] as $empItem): ?>
                                                    <option value="<?= htmlspecialchars($empItem) ?>">
                                                        <?= htmlspecialchars($empItem) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="table-container mb-4">
                                <table class="table table-bordered table-striped table-sm align-middle">
                                    <thead class="table-dark sticky-top" style="top: 0;">
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre</th>
                                            <th>Cédula</th>
                                            <th>Fecha</th>
                                            <th>Ruta</th>
                                            <th>Vehículo</th>
                                            <th>Empresa</th>
                                            <th>Pago parcial</th>
                                            <th>Imagen</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($viajes_seleccionados as $viaje_multi): 
                                            $id_multi = (int)$viaje_multi['id'];
                                        ?>
                                            <tr>
                                                <td class="fw-bold"><?= $id_multi ?></td>
                                                <td>
                                                    <input type="text" name="nombre_<?= $id_multi ?>" 
                                                           class="form-control form-control-sm" 
                                                           value="<?= htmlspecialchars($viaje_multi['nombre']) ?>">
                                                </td>
                                                <td>
                                                    <input type="text" name="cedula_<?= $id_multi ?>" 
                                                           class="form-control form-control-sm" 
                                                           value="<?= htmlspecialchars($viaje_multi['cedula'] ?? '') ?>">
                                                </td>
                                                <td>
                                                    <input type="date" name="fecha_<?= $id_multi ?>" 
                                                           class="form-control form-control-sm" 
                                                           value="<?= htmlspecialchars($viaje_multi['fecha']) ?>">
                                                </td>
                                                <td>
                                                    <select name="ruta_<?= $id_multi ?>" class="form-select form-select-sm select2-single">
                                                        <option value="">-- Seleccionar --</option>
                                                        <?php foreach($listas['rutas'] as $rutaItem): ?>
                                                            <option value="<?= htmlspecialchars($rutaItem) ?>"
                                                                <?= ($rutaItem == $viaje_multi['ruta']) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($rutaItem) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <select name="tipo_vehiculo_<?= $id_multi ?>" class="form-select form-select-sm select2-single">
                                                        <option value="">-- Seleccionar --</option>
                                                        <?php foreach($listas['vehiculos'] as $vehItem): ?>
                                                            <option value="<?= htmlspecialchars($vehItem) ?>"
                                                                <?= ($vehItem == $viaje_multi['tipo_vehiculo']) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($vehItem) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <select name="empresa_<?= $id_multi ?>" class="form-select form-select-sm select2-single">
                                                        <option value="">-- Ninguna --</option>
                                                        <?php foreach($listas['empresas'] as $empItem): ?>
                                                            <option value="<?= htmlspecialchars($empItem) ?>"
                                                                <?= (isset($viaje_multi['empresa']) && $empItem == $viaje_multi['empresa']) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($empItem) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="number" min="0" step="1"
                                                           name="pago_parcial_<?= $id_multi ?>"
                                                           class="form-control form-control-sm"
                                                           value="<?= htmlspecialchars($viaje_multi['pago_parcial'] ?? '') ?>"
                                                           placeholder="(vacío = no cambia)">
                                                </td>
                                                <td class="text-center">
                                                    <?php if(!empty($viaje_multi['imagen'])): ?>
                                                        <img src="uploads/<?= htmlspecialchars($viaje_multi['imagen']) ?>" 
                                                             width="50" class="rounded img-thumb"
                                                             data-bs-toggle="tooltip" title="<?= htmlspecialchars($viaje_multi['imagen']) ?>">
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="?" class="btn btn-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-warning">
                                    Guardar Cambios en <?= (int)$total_seleccionados ?> Registros
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    <!-- ================== LISTADO PRINCIPAL ================== -->
    <?php else: ?>
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

        <!-- BOTONES DE INFORME Y CONFIGURACIÓN DE COLUMNAS -->
        <div class="d-flex justify-content-end mb-3 gap-2">
            <!-- BOTÓN GENERAR INFORME (NUEVO) -->
            <form method="GET" action="" target="_blank">
                <?php
                // Preservar todos los filtros actuales
                foreach($_GET as $key => $value) {
                    if ($key != 'accion') {
                        if (is_array($value)) {
                            foreach($value as $v) {
                                echo '<input type="hidden" name="' . htmlspecialchars($key) . '[]" value="' . htmlspecialchars($v) . '">';
                            }
                        } else {
                            echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
                        }
                    }
                }
                ?>
                <input type="hidden" name="accion" value="informe">
                <button type="submit" class="btn btn-success btn-informe">
                    📄 Generar Informe
                </button>
            </form>
            
            <!-- Botón Configuración de Columnas -->
            <div class="columnas-config position-relative">
                <button type="button" class="btn btn-outline-primary" id="btnConfigColumnas">
                    📊 Configurar Columnas
                </button>
                <div class="columnas-dropdown" id="dropdownColumnas">
                    <h6 class="mb-3">Seleccionar columnas a mostrar:</h6>
                    <form method="POST" id="formColumnas">
                        <?php foreach($_SESSION['columnas_visibles'] as $key => $columna): ?>
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

        <!-- FILTROS -->
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">🔍 Filtros de búsqueda (multiselect)</h3>
                <small class="text-light">Presiona Ctrl+Click o arrastra para seleccionar múltiples opciones</small>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3" id="filtrosForm">
                    <div class="col-md-3">
                        <label class="form-label">Nombre</label>
                        <select name="nombre[]" class="form-select select2-multiple" multiple data-placeholder="Todos los nombres">
                            <?php
                            $nombresSeleccionados = $_GET['nombre'] ?? [];
                            if (!is_array($nombresSeleccionados)) {
                                $nombresSeleccionados = [];
                            }
                            foreach($listas['nombres'] as $nom):
                                $sel = in_array($nom, $nombresSeleccionados) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($nom) ?>" <?= $sel ?>>
                                    <?= htmlspecialchars($nom) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Cédula</label>
                        <select name="cedula[]" class="form-select select2-multiple" multiple data-placeholder="Todas las cédulas">
                            <?php
                            $cedulasSeleccionadas = $_GET['cedula'] ?? [];
                            if (!is_array($cedulasSeleccionadas)) {
                                $cedulasSeleccionadas = [];
                            }
                            foreach($listas['cedulas'] as $ced):
                                $sel = in_array($ced, $cedulasSeleccionadas) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($ced) ?>" <?= $sel ?>>
                                    <?= htmlspecialchars($ced) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Fecha desde</label>
                        <input type="date" name="desde" value="<?= htmlspecialchars($_GET['desde'] ?? '') ?>" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Fecha hasta</label>
                        <input type="date" name="hasta" value="<?= htmlspecialchars($_GET['hasta'] ?? '') ?>" class="form-control">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Ruta</label>
                        <select name="ruta[]" class="form-select select2-multiple" multiple data-placeholder="Todas las rutas">
                            <?php
                            $rutasSeleccionadas = $_GET['ruta'] ?? [];
                            if (!is_array($rutasSeleccionadas)) {
                                $rutasSeleccionadas = [];
                            }
                            foreach($listas['rutas'] as $ruta):
                                $sel = in_array($ruta, $rutasSeleccionadas) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($ruta) ?>" <?= $sel ?>>
                                    <?= htmlspecialchars($ruta) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Vehículo</label>
                        <select name="vehiculo[]" class="form-select select2-multiple" multiple data-placeholder="Todos los vehículos">
                            <?php
                            $vehiculosSeleccionados = $_GET['vehiculo'] ?? [];
                            if (!is_array($vehiculosSeleccionados)) {
                                $vehiculosSeleccionados = [];
                            }
                            foreach($listas['vehiculos'] as $veh):
                                $sel = in_array($veh, $vehiculosSeleccionados) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($veh) ?>" <?= $sel ?>>
                                    <?= htmlspecialchars($veh) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Empresa</label>
                        <select name="empresa[]" class="form-select select2-multiple" multiple data-placeholder="Todas las empresas">
                            <?php
                            $empresasSeleccionadas = $_GET['empresa'] ?? [];
                            if (!is_array($empresasSeleccionadas)) {
                                $empresasSeleccionadas = [];
                            }
                            foreach($listas['empresas'] as $emp):
                                $sel = in_array($emp, $empresasSeleccionadas) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($emp) ?>" <?= $sel ?>>
                                    <?= htmlspecialchars($emp) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

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
        $where        = [];
        $ids_visibles = [];

        if (!empty($_GET['nombre']) && is_array($_GET['nombre'])) {
            $nombres = array_map([$conexion, 'real_escape_string'], $_GET['nombre']);
            $nombres = array_filter($nombres, function($val) { return trim($val) !== ''; });
            if (!empty($nombres)) {
                $where[] = "nombre IN ('" . implode("','", $nombres) . "')";
            }
        }

        if (!empty($_GET['cedula']) && is_array($_GET['cedula'])) {
            $cedulas = array_map([$conexion, 'real_escape_string'], $_GET['cedula']);
            $cedulas = array_filter($cedulas, function($val) { return trim($val) !== ''; });
            if (!empty($cedulas)) {
                $where[] = "cedula IN ('" . implode("','", $cedulas) . "')";
            }
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

        if (!empty($_GET['ruta']) && is_array($_GET['ruta'])) {
            $rutas = array_map([$conexion, 'real_escape_string'], $_GET['ruta']);
            $rutas = array_filter($rutas, function($val) { return trim($val) !== ''; });
            if (!empty($rutas)) {
                $where[] = "ruta IN ('" . implode("','", $rutas) . "')";
            }
        }

        if (!empty($_GET['vehiculo']) && is_array($_GET['vehiculo'])) {
            $vehiculos = array_map([$conexion, 'real_escape_string'], $_GET['vehiculo']);
            $vehiculos = array_filter($vehiculos, function($val) { return trim($val) !== ''; });
            if (!empty($vehiculos)) {
                $where[] = "tipo_vehiculo IN ('" . implode("','", $vehiculos) . "')";
            }
        }

        if (!empty($_GET['empresa']) && is_array($_GET['empresa'])) {
            $empresas = array_map([$conexion, 'real_escape_string'], $_GET['empresa']);
            $empresas = array_filter($empresas, function($val) { return trim($val) !== ''; });
            if (!empty($empresas)) {
                $where[] = "empresa IN ('" . implode("','", $empresas) . "')";
            }
        }

        $sql = "SELECT * FROM viajes";
        if (count($where) > 0) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY fecha DESC, id DESC";
        
        $resultado = $conexion->query($sql);

        if (!empty($_GET['nombre']) && is_array($_GET['nombre']) && count($_GET['nombre']) > 0):
            $nombresFiltro = array_map([$conexion, 'real_escape_string'], $_GET['nombre']);
            $totalViajes = 0;
            ?>
            <div class="alert alert-info">
                <strong>Filtro activo:</strong> 
                <?php 
                foreach($nombresFiltro as $nombreFiltro):
                    $sqlContar = "SELECT COUNT(*) AS total FROM viajes WHERE nombre = '$nombreFiltro'";
                    $resContar = $conexion->query($sqlContar);
                    $count = $resContar ? (int)$resContar->fetch_assoc()['total'] : 0;
                    $totalViajes += $count;
                ?>
                    <span class="badge bg-primary me-2"><?= htmlspecialchars($nombreFiltro) ?> (<?= $count ?>)</span>
                <?php endforeach; ?>
                <br><strong>Total viajes:</strong> <?= $totalViajes ?>
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
                </div>

                <div class="table-container">
                    <table class="table table-bordered table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:32px;">Sel.</th>
                                
                                <?php 
                                $columnas_ordenadas = $_SESSION['columnas_visibles'];
                                uasort($columnas_ordenadas, function($a, $b) {
                                    return $a['orden'] <=> $b['orden'];
                                });
                                
                                foreach($columnas_ordenadas as $key => $columna):
                                    if (!$columna['visible']) continue;
                                    
                                    $width = '';
                                    switch($key) {
                                        case 'id': $width = 'width: 60px;'; break;
                                        case 'imagen': $width = 'width: 100px;'; break;
                                    }
                                ?>
                                    <th style="<?= $width ?>"><?= htmlspecialchars($columna['nombre']) ?></th>
                                <?php endforeach; ?>
                                
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
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="toggle_seleccion" value="<?= $id_registro ?>">
                                            <input type="checkbox" 
                                                   class="form-check-input checkbox-seleccion" 
                                                   onchange="this.form.submit()"
                                                   <?= $esta_seleccionado ? 'checked' : '' ?>>
                                        </form>
                                    </td>
                                    
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
                                    
                                    <td>
                                        <a href="?accion=editar&id=<?= $id_registro; ?>" class="btn btn-warning btn-sm">✏ Editar</a>
                                        <a href="?accion=eliminar&id=<?= $id_registro; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Seguro de eliminar?')">🗑 Eliminar</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <?php 
                            $columnas_visibles_count = count(array_filter($columnas_ordenadas, fn($c) => $c['visible']));
                            $total_columnas = $columnas_visibles_count + 2;
                            ?>
                            <tr>
                                <td colspan="<?= $total_columnas ?>" class="text-center py-4">No se encontraron resultados.</td>
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
document.addEventListener('DOMContentLoaded', function() {
    const idsVisiblesArray = <?= json_encode($ids_visibles ?? []) ?>;
    
    const input1 = document.getElementById('idsVisibles');
    const input2 = document.getElementById('idsVisibles2');
    if (input1) input1.value = idsVisiblesArray.join(',');
    if (input2) input2.value = idsVisiblesArray.join(',');
    
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    $('.select2-multiple').select2({
        width: '100%',
        placeholder: function() {
            return $(this).data('placeholder');
        },
        allowClear: true,
        language: 'es'
    });
    
    $('.select2-single').select2({
        width: '100%',
        placeholder: '-- Seleccionar --',
        allowClear: true,
        language: 'es'
    });
    
    const btnConfig = document.getElementById('btnConfigColumnas');
    const dropdown = document.getElementById('dropdownColumnas');
    
    if (btnConfig && dropdown) {
        btnConfig.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });
        
        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target) && !btnConfig.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });
        
        dropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
});

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