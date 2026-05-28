<?php
// index2.php - Sistema completo de gestión de viajes con INFORME y SELECTORES DINÁMICOS + CAMPO WHATSAPP

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
$columnas_disponibles = [
    'id' => ['nombre' => 'ID', 'visible' => true, 'orden' => 1],
    'nombre' => ['nombre' => 'Nombre', 'visible' => true, 'orden' => 2],
    'cedula' => ['nombre' => 'Cédula', 'visible' => true, 'orden' => 3],
    'fecha' => ['nombre' => 'Fecha', 'visible' => true, 'orden' => 4],
    'ruta' => ['nombre' => 'Ruta', 'visible' => true, 'orden' => 5],
    'tipo_vehiculo' => ['nombre' => 'Vehículo', 'visible' => true, 'orden' => 6],
    'empresa' => ['nombre' => 'Empresa', 'visible' => true, 'orden' => 7],
    'pago_parcial' => ['nombre' => 'Pago Parcial', 'visible' => true, 'orden' => 8],
    'pagado' => ['nombre' => 'Pagado', 'visible' => true, 'orden' => 9],
    'imagen' => ['nombre' => 'Evidencia', 'visible' => true, 'orden' => 10],
    'epicrisis' => ['nombre' => 'Epicrisis', 'visible' => true, 'orden' => 11],
    'whatsapp' => ['nombre' => 'WhatsApp', 'visible' => true, 'orden' => 12]  // NUEVA COLUMNA
];

if (!isset($_SESSION['columnas_visibles'])) {
    $_SESSION['columnas_visibles'] = $columnas_disponibles;
}

if (isset($_POST['actualizar_columnas'])) {
    $columnas_seleccionadas = $_POST['columnas'] ?? [];
    foreach ($columnas_disponibles as $key => $columna) {
        $_SESSION['columnas_visibles'][$key]['visible'] = in_array($key, $columnas_seleccionadas);
    }
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

if (isset($_POST['restablecer_columnas'])) {
    $_SESSION['columnas_visibles'] = $columnas_disponibles;
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// ================== MANEJO DE SELECCIÓN ==================
if (isset($_POST['toggle_seleccion'])) {
    $id_toggle = (int)$_POST['toggle_seleccion'];
    if (in_array($id_toggle, $_SESSION['seleccionados'])) {
        $_SESSION['seleccionados'] = array_diff($_SESSION['seleccionados'], [$id_toggle]);
    } else {
        $_SESSION['seleccionados'][] = $id_toggle;
    }
    $_SESSION['seleccionados'] = array_values(array_unique($_SESSION['seleccionados']));
}

if (isset($_POST['seleccionar_todos']) && isset($_POST['ids_visibles'])) {
    $ids_visibles = [];
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
            foreach ($ids_visibles as $id_visible) {
                if (!in_array($id_visible, $_SESSION['seleccionados'])) {
                    $_SESSION['seleccionados'][] = $id_visible;
                }
            }
        } else {
            $_SESSION['seleccionados'] = array_diff($_SESSION['seleccionados'], $ids_visibles);
        }
        $_SESSION['seleccionados'] = array_values(array_unique($_SESSION['seleccionados']));
    }
}

if (isset($_POST['limpiar_seleccion'])) {
    $_SESSION['seleccionados'] = [];
}

// ================== FUNCIONES AUXILIARES ==================
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

function procesarSubidaArchivo($campo) {
    if (isset($_FILES[$campo]) && $_FILES[$campo]['error'] === UPLOAD_ERR_OK) {
        $nombre = basename($_FILES[$campo]['name']);
        $temp = $_FILES[$campo]['tmp_name'];
        $destino = "uploads/" . $nombre;
        if (!is_dir('uploads')) mkdir('uploads', 0777, true);
        if (move_uploaded_file($temp, $destino)) {
            return $nombre;
        }
    }
    return null;
}

// ================== ENDPOINTS AJAX PARA SELECTORES DINÁMICOS ==================
if (isset($_GET['ajax']) && $_GET['ajax'] == 'buscar') {
    header('Content-Type: application/json');
    $tabla = $_GET['tabla'] ?? '';
    $term = $_GET['term'] ?? '';
    $term = $conexion->real_escape_string($term);
    
    $resultados = [];
    
    switch($tabla) {
        case 'conductores':
            $sql = "SELECT nombre as texto, id FROM conductores_admin WHERE nombre LIKE '%$term%' ORDER BY nombre LIMIT 20";
            $res = $conexion->query($sql);
            if ($res) {
                while($row = $res->fetch_assoc()) {
                    $resultados[] = ['id' => $row['texto'], 'text' => $row['texto']];
                }
            }
            break;
        case 'rutas':
            $sql = "SELECT ruta as texto, id FROM rutas_admin WHERE ruta LIKE '%$term%' ORDER BY ruta LIMIT 20";
            $res = $conexion->query($sql);
            if ($res) {
                while($row = $res->fetch_assoc()) {
                    $resultados[] = ['id' => $row['texto'], 'text' => $row['texto']];
                }
            }
            break;
        case 'empresas':
            $sql = "SELECT nombre as texto, id FROM empresas_admin WHERE nombre LIKE '%$term%' ORDER BY nombre LIMIT 20";
            $res = $conexion->query($sql);
            if ($res) {
                while($row = $res->fetch_assoc()) {
                    $resultados[] = ['id' => $row['texto'], 'text' => $row['texto']];
                }
            }
            break;
    }
    
    echo json_encode(['results' => $resultados]);
    exit();
}

if (isset($_POST['ajax']) && $_POST['ajax'] == 'crear') {
    header('Content-Type: application/json');
    $tabla = $_POST['tabla'] ?? '';
    $valor = trim($_POST['valor'] ?? '');
    $respuesta = ['success' => false, 'mensaje' => '', 'valor' => ''];
    
    if (empty($valor)) {
        $respuesta['mensaje'] = 'Valor vacío';
        echo json_encode($respuesta);
        exit();
    }
    
    $valor = $conexion->real_escape_string($valor);
    
    switch($tabla) {
        case 'conductores':
            $check = $conexion->query("SELECT id FROM conductores_admin WHERE nombre = '$valor'");
            if ($check && $check->num_rows > 0) {
                $respuesta['success'] = true;
                $respuesta['valor'] = $valor;
                $respuesta['mensaje'] = 'Ya existe';
                echo json_encode($respuesta);
                exit();
            }
            $sql = "INSERT INTO conductores_admin (nombre, owner_chat_id) VALUES ('$valor', 0)";
            if ($conexion->query($sql)) {
                $respuesta['success'] = true;
                $respuesta['valor'] = $valor;
                $respuesta['mensaje'] = 'Conductor creado';
            } else {
                $respuesta['mensaje'] = 'Error: ' . $conexion->error;
            }
            break;
        case 'rutas':
            $check = $conexion->query("SELECT id FROM rutas_admin WHERE ruta = '$valor'");
            if ($check && $check->num_rows > 0) {
                $respuesta['success'] = true;
                $respuesta['valor'] = $valor;
                $respuesta['mensaje'] = 'Ya existe';
                echo json_encode($respuesta);
                exit();
            }
            $sql = "INSERT INTO rutas_admin (ruta, owner_chat_id) VALUES ('$valor', 0)";
            if ($conexion->query($sql)) {
                $respuesta['success'] = true;
                $respuesta['valor'] = $valor;
                $respuesta['mensaje'] = 'Ruta creada';
            } else {
                $respuesta['mensaje'] = 'Error: ' . $conexion->error;
            }
            break;
        case 'empresas':
            $check = $conexion->query("SELECT id FROM empresas_admin WHERE nombre = '$valor'");
            if ($check && $check->num_rows > 0) {
                $respuesta['success'] = true;
                $respuesta['valor'] = $valor;
                $respuesta['mensaje'] = 'Ya existe';
                echo json_encode($respuesta);
                exit();
            }
            $sql = "INSERT INTO empresas_admin (nombre, owner_chat_id) VALUES ('$valor', 0)";
            if ($conexion->query($sql)) {
                $respuesta['success'] = true;
                $respuesta['valor'] = $valor;
                $respuesta['mensaje'] = 'Empresa creada';
            } else {
                $respuesta['mensaje'] = 'Error: ' . $conexion->error;
            }
            break;
        default:
            $respuesta['mensaje'] = 'Tabla no válida';
    }
    
    echo json_encode($respuesta);
    exit();
}

// ================== PROCESAR ACCIONES ==================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

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
        $pagado = isset($_POST['pagado']) ? 1 : 0;
        $whatsapp = isset($_POST['whatsapp']) && trim($_POST['whatsapp']) !== ''
            ? "'" . $conexion->real_escape_string($_POST['whatsapp']) . "'"
            : "NULL";
        $imagen_nombre = procesarSubidaArchivo('imagen');
        $imagen_valor = $imagen_nombre ? "'" . $conexion->real_escape_string($imagen_nombre) . "'" : "NULL";
        $epicrisis_nombre = procesarSubidaArchivo('epicrisis');
        $epicrisis_valor = $epicrisis_nombre ? "'" . $conexion->real_escape_string($epicrisis_nombre) . "'" : "NULL";
        
        if (empty($nombre) || empty($fecha) || empty($ruta) || empty($tipo_vehiculo)) {
            $_SESSION['error'] = "Los campos Nombre, Fecha, Ruta y Vehículo son obligatorios.";
            $accion = 'crear';
        } else {
            $sql = "INSERT INTO viajes (nombre, cedula, fecha, ruta, tipo_vehiculo, empresa, imagen, epicrisis, whatsapp, pago_parcial, pagado) 
                    VALUES ('$nombre', $cedula, '$fecha', '$ruta', '$tipo_vehiculo', $empresa, $imagen_valor, $epicrisis_valor, $whatsapp, $pago_parcial, $pagado)";
            
            if ($conexion->query($sql)) {
                header("Location: ?msg=creado");
                exit();
            } else {
                $_SESSION['error'] = "Error al crear: " . $conexion->error;
                $accion = 'crear';
            }
        }
    }
    
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
        $pagado = isset($_POST['pagado']) ? 1 : 0;
        $whatsapp = isset($_POST['whatsapp']) && trim($_POST['whatsapp']) !== ''
            ? "'" . $conexion->real_escape_string($_POST['whatsapp']) . "'"
            : "NULL";
        
        $sql_actual = "SELECT nombre, cedula, fecha, ruta, tipo_vehiculo, empresa, pago_parcial FROM viajes WHERE id = $id";
        $res_actual = $conexion->query($sql_actual);
        $datos_actuales = $res_actual->fetch_assoc();
        
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
        
        $imagen_campo = '';
        $imagen_subida = procesarSubidaArchivo('imagen');
        if ($imagen_subida) {
            $imagen_campo = ", imagen = '" . $conexion->real_escape_string($imagen_subida) . "'";
        } elseif (isset($_POST['eliminar_imagen']) && $_POST['eliminar_imagen'] == '1') {
            $imagen_campo = ", imagen = NULL";
        }
        
        $epicrisis_campo = '';
        $epicrisis_subida = procesarSubidaArchivo('epicrisis');
        if ($epicrisis_subida) {
            $epicrisis_campo = ", epicrisis = '" . $conexion->real_escape_string($epicrisis_subida) . "'";
        } elseif (isset($_POST['eliminar_epicrisis']) && $_POST['eliminar_epicrisis'] == '1') {
            $epicrisis_campo = ", epicrisis = NULL";
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
                    pago_parcial = $pago_parcial,
                    pagado = $pagado,
                    whatsapp = $whatsapp
                    $imagen_campo
                    $epicrisis_campo
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
                    }
                }
                header("Location: ?msg=editado");
                exit();
            } else {
                $_SESSION['error'] = "Error al actualizar: " . $conexion->error;
                $accion = 'editar';
            }
        }
    }
    
    elseif (isset($_POST['editar_multiple_completo'])) {
        if (empty($_SESSION['seleccionados'])) {
            header("Location: ?error=no_ids");
            exit();
        }
        
        $ids = $_SESSION['seleccionados'];
        $actualizados = 0;
        $pago_parcial_general = normalizarPagoParcial($conexion, $_POST['pago_parcial_general'] ?? null);
        $hay_pago_general = (isset($_POST['pago_parcial_general']) && trim((string)$_POST['pago_parcial_general']) !== '');
        $pagado_general = isset($_POST['pagado_general']) ? (int)$_POST['pagado_general'] : null;

        foreach ($ids as $id_viaje) {
            $id_viaje = (int)$id_viaje;
            $nombre_key   = "nombre_$id_viaje";
            $cedula_key   = "cedula_$id_viaje";
            $fecha_key    = "fecha_$id_viaje";
            $ruta_key     = "ruta_$id_viaje";
            $vehiculo_key = "tipo_vehiculo_$id_viaje";
            $empresa_key  = "empresa_$id_viaje";
            $pago_key     = "pago_parcial_$id_viaje";
            $pagado_key   = "pagado_$id_viaje";
            $whatsapp_key = "whatsapp_$id_viaje";
            
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
            
            $pagado_valor = null;
            if (isset($_POST[$pagado_key])) {
                $pagado_valor = 1;
            } elseif ($pagado_general !== null) {
                $pagado_valor = $pagado_general;
            }
            
            $whatsapp = "NULL";
            if (isset($_POST[$whatsapp_key]) && trim($_POST[$whatsapp_key]) !== '') {
                $whatsapp = "'" . $conexion->real_escape_string($_POST[$whatsapp_key]) . "'";
            } elseif (isset($_POST['whatsapp_general']) && trim($_POST['whatsapp_general']) !== '') {
                $whatsapp = "'" . $conexion->real_escape_string($_POST['whatsapp_general']) . "'";
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
                    pago_parcial = IFNULL($pago_parcial, pago_parcial),
                    whatsapp = IFNULL($whatsapp, whatsapp)";
            
            if ($pagado_valor !== null) {
                $sql .= ", pagado = $pagado_valor";
            }
            
            $sql .= " WHERE id = $id_viaje";
            
            if ($conexion->query($sql)) {
                $actualizados++;
            }
        }
        
        $_SESSION['seleccionados'] = [];
        header("Location: ?msg=multi_editado&count=$actualizados");
        exit();
    }
    
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
    $where = [];
    $desc_filtros = [];

    if (!empty($_GET['nombre']) && is_array($_GET['nombre'])) {
        $nombres = array_map([$conexion, 'real_escape_string'], $_GET['nombre']);
        $nombres = array_filter($nombres, function($val) { return trim($val) !== ''; });
        if (!empty($nombres)) {
            $where[] = "nombre IN ('" . implode("','", $nombres) . "')";
            $desc_filtros[] = "Nombres: " . implode(', ', array_slice($nombres, 0, 3)) . (count($nombres) > 3 ? '...' : '');
        }
    }

    if (!empty($_GET['cedula']) && is_array($_GET['cedula'])) {
        $cedulas = array_map([$conexion, 'real_escape_string'], $_GET['cedula']);
        $cedulas = array_filter($cedulas, function($val) { return trim($val) !== ''; });
        if (!empty($cedulas)) {
            $where[] = "cedula IN ('" . implode("','", $cedulas) . "')";
            $desc_filtros[] = "Cédulas: " . implode(', ', array_slice($cedulas, 0, 3)) . (count($cedulas) > 3 ? '...' : '');
        }
    }

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

    if (!empty($_GET['ruta']) && is_array($_GET['ruta'])) {
        $rutas = array_map([$conexion, 'real_escape_string'], $_GET['ruta']);
        $rutas = array_filter($rutas, function($val) { return trim($val) !== ''; });
        if (!empty($rutas)) {
            $where[] = "ruta IN ('" . implode("','", $rutas) . "')";
            $desc_filtros[] = "Rutas: " . implode(', ', array_slice($rutas, 0, 3)) . (count($rutas) > 3 ? '...' : '');
        }
    }

    if (!empty($_GET['vehiculo']) && is_array($_GET['vehiculo'])) {
        $vehiculos = array_map([$conexion, 'real_escape_string'], $_GET['vehiculo']);
        $vehiculos = array_filter($vehiculos, function($val) { return trim($val) !== ''; });
        if (!empty($vehiculos)) {
            $where[] = "tipo_vehiculo IN ('" . implode("','", $vehiculos) . "')";
            $desc_filtros[] = "Vehículos: " . implode(', ', array_slice($vehiculos, 0, 3)) . (count($vehiculos) > 3 ? '...' : '');
        }
    }

    if (!empty($_GET['empresa']) && is_array($_GET['empresa'])) {
        $empresas = array_map([$conexion, 'real_escape_string'], $_GET['empresa']);
        $empresas = array_filter($empresas, function($val) { return trim($val) !== ''; });
        if (!empty($empresas)) {
            $where[] = "empresa IN ('" . implode("','", $empresas) . "')";
            $desc_filtros[] = "Empresas: " . implode(', ', array_slice($empresas, 0, 3)) . (count($empresas) > 3 ? '...' : '');
        }
    }

    if (isset($_GET['pagado']) && $_GET['pagado'] !== '') {
        $pagado_val = (int)$_GET['pagado'];
        $where[] = "pagado = $pagado_val";
        $desc_filtros[] = "Estado: " . ($pagado_val ? 'Pagado' : 'Pendiente');
    }

    $sql = "SELECT * FROM viajes";
    if (count($where) > 0) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY fecha DESC, id DESC";
    
    $resultado = $conexion->query($sql);
    
    $columnas_visibles = $_SESSION['columnas_visibles'];
    uasort($columnas_visibles, function($a, $b) {
        return $a['orden'] <=> $b['orden'];
    });
    
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Informe de Viajes</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 30px; background: white; }
            h1 { color: #333; border-bottom: 2px solid #333; padding-bottom: 10px; }
            .info-filtros { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #0d6efd; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
            th { background: #343a40; color: white; padding: 12px 8px; text-align: left; font-weight: bold; }
            td { border: 1px solid #dee2e6; padding: 8px; vertical-align: top; white-space: pre-wrap; }
            tr:nth-child(even) { background: #f8f9fa; }
            tr.pagado { background-color: #d4edda !important; }
            tr.pendiente { background-color: #f8d7da !important; }
            tr.pagado:nth-child(even) { background-color: #c3e6cb !important; }
            tr.pendiente:nth-child(even) { background-color: #f5c6cb !important; }
            .fecha-generacion { color: #666; font-size: 12px; margin-top: 20px; text-align: right; border-top: 1px solid #dee2e6; padding-top: 15px; }
            .badge-pagado { background: #28a745; color: white; padding: 3px 8px; border-radius: 4px; font-weight: bold; }
            .badge-pendiente { background: #dc3545; color: white; padding: 3px 8px; border-radius: 4px; font-weight: bold; }
            .img-informe { max-width: 60px; max-height: 60px; border-radius: 4px; }
            .total-registros { background: #e9ecef; padding: 10px; border-radius: 4px; font-weight: bold; }
            @media print { .no-print { display: none; } }
            .btn-print { background: #0d6efd; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-bottom: 20px; }
            .btn-cerrar { background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class="no-print" style="margin-bottom: 20px; display: flex; gap: 10px;">
            <button onclick="window.print()" class="btn-print">🖨️ Imprimir / Guardar PDF</button>
            <button onclick="window.close()" class="btn-cerrar">❌ Cerrar</button>
        </div>
        <h1>🚗 Informe de Viajes</h1>
        <div class="info-filtros">
            <strong>📊 Columnas mostradas:</strong> 
            <?php 
            $nombres_columnas = [];
            foreach($columnas_visibles as $col) {
                if ($col['visible']) $nombres_columnas[] = $col['nombre'];
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
            <div class="total-registros">📋 Total de registros: <?= $resultado->num_rows ?></div>
            <table>
                <thead>
                    <tr>
                        <?php foreach($columnas_visibles as $key => $columna): if ($columna['visible']): ?>
                            <th><?= htmlspecialchars($columna['nombre']) ?></th>
                        <?php endif; endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $resultado->fetch_assoc()): 
                        $clase_fila = $row['pagado'] ? 'pagado' : 'pendiente';
                    ?>
                        <tr class="<?= $clase_fila ?>">
                            <?php foreach($columnas_visibles as $key => $columna): if (!$columna['visible']) continue; ?>
                                <?php switch($key): 
                                    case 'id': ?> <td><?= (int)$row['id'] ?></td> <?php break;
                                    case 'nombre': ?> <td><?= htmlspecialchars($row['nombre']) ?></td> <?php break;
                                    case 'cedula': ?> <td><?= !empty($row['cedula']) ? htmlspecialchars($row['cedula']) : '—' ?></td> <?php break;
                                    case 'fecha': ?> <td><?= date('d/m/Y', strtotime($row['fecha'])) ?></td> <?php break;
                                    case 'ruta': ?> <td><?= htmlspecialchars($row['ruta']) ?></td> <?php break;
                                    case 'tipo_vehiculo': ?> <td><?= htmlspecialchars($row['tipo_vehiculo']) ?></td> <?php break;
                                    case 'empresa': ?> <td><?= !empty($row['empresa']) ? htmlspecialchars($row['empresa']) : '—' ?></td> <?php break;
                                    case 'pago_parcial': ?> <td><?php if ($row['pago_parcial'] !== null && $row['pago_parcial'] !== ''): ?>$<?= number_format((int)$row['pago_parcial'], 0, ',', '.') ?><?php else: ?>—<?php endif; ?></td> <?php break;
                                    case 'pagado': ?> <td><?php if ($row['pagado'] == 1): ?><span class="badge-pagado">✅ Pagado</span><?php else: ?><span class="badge-pendiente">❌ Pendiente</span><?php endif; ?></td> <?php break;
                                    case 'imagen': ?> <td><?php if(!empty($row['imagen'])): ?><img src="uploads/<?= htmlspecialchars($row['imagen']) ?>" class="img-informe" onerror="this.style.display='none'"><?php else: ?>—<?php endif; ?></td> <?php break;
                                    case 'epicrisis': ?> <td><?php if(!empty($row['epicrisis'])): ?><img src="uploads/<?= htmlspecialchars($row['epicrisis']) ?>" class="img-informe" onerror="this.style.display='none'"><?php else: ?>—<?php endif; ?></td> <?php break;
                                    case 'whatsapp': ?> <td><?php if(!empty($row['whatsapp'])): ?><div style="white-space: pre-wrap; word-break: break-word;"><?= nl2br(htmlspecialchars($row['whatsapp'])) ?></div><?php else: ?>—<?php endif; ?></td> <?php break;
                                endswitch; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="text-align: center; padding: 50px; background: #f8f9fa; border-radius: 5px;">
                <h3 style="color: #666;">📭 No hay registros para mostrar</h3>
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
$listas = [];
$res = $conexion->query("SELECT DISTINCT nombre FROM viajes WHERE nombre <> '' ORDER BY nombre ASC");
if ($res) while($r = $res->fetch_assoc()) $listas['nombres'][] = $r['nombre'];
$res = $conexion->query("SELECT DISTINCT cedula FROM viajes WHERE cedula IS NOT NULL AND cedula <> '' ORDER BY cedula ASC");
if ($res) while($r = $res->fetch_assoc()) $listas['cedulas'][] = $r['cedula'];
$res = $conexion->query("SELECT DISTINCT ruta FROM viajes WHERE ruta <> '' ORDER BY ruta ASC");
if ($res) while($r = $res->fetch_assoc()) $listas['rutas'][] = $r['ruta'];
$res = $conexion->query("SELECT DISTINCT tipo_vehiculo FROM viajes WHERE tipo_vehiculo <> '' ORDER BY tipo_vehiculo ASC");
if ($res) while($r = $res->fetch_assoc()) $listas['vehiculos'][] = $r['tipo_vehiculo'];
$res = $conexion->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa <> '' ORDER BY empresa ASC");
if ($res) while($r = $res->fetch_assoc()) $listas['empresas'][] = $r['empresa'];

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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <style>
        .table-hover tbody tr:hover { background-color: rgba(0,0,0,.025); }
        .img-thumb { max-width: 70px; height: auto; cursor: pointer; }
        .required:after { content: " *"; color: red; }
        .seleccionado { background-color: rgba(25, 135, 84, 0.1) !important; }
        .checkbox-seleccion { cursor: pointer; }
        .sticky-actions { position: sticky; top: 0; z-index: 1000; background: white; padding: 15px; margin: -15px -15px 15px -15px; border-bottom: 1px solid #dee2e6; box-shadow: 0 2px 4px rgba(0,0,0,.1); }
        .table-container { max-height: 600px; overflow-y: auto; }
        .form-control-sm { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
        .btn-informe { background-color: #198754; color: white; }
        .btn-informe:hover { background-color: #157347; color: white; }
        tr.pagado { background-color: #d4edda !important; }
        tr.pendiente { background-color: #f8d7da !important; }
        .badge-pagado { background-color: #28a745; color: white; padding: 5px 10px; border-radius: 4px; }
        .badge-pendiente { background-color: #dc3545; color: white; padding: 5px 10px; border-radius: 4px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px; }
        .select2-container--default .select2-selection--single { height: 38px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }
        .columnas-dropdown { position: absolute; top: 100%; right: 0; z-index: 1000; background: white; border: 1px solid #dee2e6; border-radius: 0.375rem; box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15); min-width: 300px; padding: 1rem; display: none; }
        .columnas-dropdown.show { display: block; }
        td { white-space: pre-wrap; word-break: break-word; vertical-align: top; }
        .whatsapp-cell { white-space: pre-wrap; word-break: break-word; max-width: 400px; min-width: 250px; }
    </style>
</head>
<body class="bg-light">

<?php include("nav.php"); ?>

<div class="container py-4">
    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php
            switch($mensaje) {
                case 'creado': echo "✅ Viaje creado exitosamente."; break;
                case 'editado': echo "✏️ Viaje editado exitosamente."; break;
                case 'editado_con_cedula': 
                    $afectados = $_GET['afectados'] ?? 0;
                    $nombre = $_GET['nombre'] ?? '';
                    echo "✏️ Viaje editado exitosamente. <br>✅ La cédula se actualizó en <b>$afectados</b> registros adicionales de <b>" . htmlspecialchars($nombre) . "</b>."; 
                    break;
                case 'eliminado': echo "🗑️ Viaje eliminado exitosamente."; break;
                case 'multi_eliminado': 
                    $count = $_GET['count'] ?? 0;
                    echo "🗑️ $count viaje(s) eliminado(s) exitosamente."; 
                    break;
                case 'multi_editado': 
                    $count = $_GET['count'] ?? 0;
                    echo "✏️ $count viaje(s) editado(s) exitosamente."; 
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
                case 'no_ids': echo "⚠️ No se seleccionaron registros."; break;
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
                            <?= $accion == 'crear' ? '➕ Nuevo Viaje' : '✏️ Editar Viaje' ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="formViaje">
                            <?php if ($accion == 'editar'): ?>
                                <input type="hidden" name="id" value="<?= (int)$id ?>">
                                <input type="hidden" name="editar" value="1">
                            <?php else: ?>
                                <input type="hidden" name="crear" value="1">
                            <?php endif; ?>
                            
                            <!-- CAMPO NOMBRE - Selector dinámico -->
                            <div class="mb-3">
                                <label class="form-label required">Nombre (Conductor)</label>
                                <select name="nombre" id="nombreSelect" class="form-select" style="width: 100%;" required>
                                    <option value="">-- Buscar o escribir nuevo nombre --</option>
                                    <?php
                                    $res = $conexion->query("SELECT nombre FROM conductores_admin ORDER BY nombre ASC");
                                    if ($res) {
                                        while($row = $res->fetch_assoc()) {
                                            $selected = (isset($viaje['nombre']) && $viaje['nombre'] == $row['nombre']) ? 'selected' : '';
                                            echo '<option value="' . htmlspecialchars($row['nombre']) . '" ' . $selected . '>' . htmlspecialchars($row['nombre']) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Cédula</label>
                                <input type="text" name="cedula" class="form-control" 
                                       value="<?= htmlspecialchars($viaje['cedula'] ?? '') ?>"
                                       placeholder="Opcional - puede estar vacío">
                                <?php if ($accion == 'editar'): ?>
                                    <small class="text-muted">Si solo modifica la cédula, se actualizará en todos los registros con el mismo nombre.</small>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Pago parcial</label>
                                <input type="number" min="0" step="1" name="pago_parcial" class="form-control"
                                       value="<?= htmlspecialchars($viaje['pago_parcial'] ?? '') ?>"
                                       placeholder="Opcional - dejar vacío si no aplica">
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="pagado" id="pagadoCheck" value="1"
                                           <?= (isset($viaje['pagado']) && $viaje['pagado'] == 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="pagadoCheck">
                                        <strong>✅ Viaje Pagado</strong>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label required">Fecha</label>
                                <input type="date" name="fecha" class="form-control" 
                                       value="<?= htmlspecialchars($viaje['fecha'] ?? '') ?>" required>
                            </div>
                            
                            <!-- CAMPO RUTA - Selector dinámico -->
                            <div class="mb-3">
                                <label class="form-label required">Ruta</label>
                                <select name="ruta" id="rutaSelect" class="form-select" style="width: 100%;" required>
                                    <option value="">-- Buscar o escribir nueva ruta --</option>
                                    <?php
                                    $res = $conexion->query("SELECT ruta FROM rutas_admin ORDER BY ruta ASC");
                                    if ($res) {
                                        while($row = $res->fetch_assoc()) {
                                            $selected = (isset($viaje['ruta']) && $viaje['ruta'] == $row['ruta']) ? 'selected' : '';
                                            echo '<option value="' . htmlspecialchars($row['ruta']) . '" ' . $selected . '>' . htmlspecialchars($row['ruta']) . '</option>';
                                        }
                                    }
                                    ?>
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
                                <small class="text-muted">Para vehículos nuevos, agrégalos directamente en la lista de vehículos.</small>
                            </div>
                            
                            <!-- CAMPO EMPRESA - Selector dinámico -->
                            <div class="mb-3">
                                <label class="form-label">Empresa</label>
                                <select name="empresa" id="empresaSelect" class="form-select" style="width: 100%;">
                                    <option value="">-- Ninguna / Buscar o escribir nueva empresa --</option>
                                    <?php
                                    $res = $conexion->query("SELECT nombre FROM empresas_admin ORDER BY nombre ASC");
                                    if ($res) {
                                        while($row = $res->fetch_assoc()) {
                                            $selected = (isset($viaje['empresa']) && $viaje['empresa'] == $row['nombre']) ? 'selected' : '';
                                            echo '<option value="' . htmlspecialchars($row['nombre']) . '" ' . $selected . '>' . htmlspecialchars($row['nombre']) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <!-- IMAGEN EVIDENCIA -->
                            <?php if ($accion == 'editar' && isset($viaje['imagen']) && !empty($viaje['imagen'])): ?>
                                <div class="mb-3">
                                    <label class="form-label">📸 Evidencia actual</label>
                                    <div>
                                        <img src="uploads/<?= htmlspecialchars($viaje['imagen']) ?>" class="img-thumbnail" style="max-width: 150px;">
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" name="eliminar_imagen" value="1" id="eliminarImg">
                                            <label class="form-check-label" for="eliminarImg">Eliminar evidencia actual</label>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label class="form-label">📸 <?= $accion == 'crear' ? 'Evidencia (opcional)' : 'Nueva evidencia (opcional)' ?></label>
                                <input type="file" name="imagen" class="form-control" accept="image/*">
                            </div>
                            
                            <!-- EPICRISIS -->
                            <?php if ($accion == 'editar' && isset($viaje['epicrisis']) && !empty($viaje['epicrisis'])): ?>
                                <div class="mb-3">
                                    <label class="form-label">📋 Epicrisis actual</label>
                                    <div>
                                        <img src="uploads/<?= htmlspecialchars($viaje['epicrisis']) ?>" class="img-thumbnail" style="max-width: 150px;">
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" name="eliminar_epicrisis" value="1" id="eliminarEpicrisis">
                                            <label class="form-check-label" for="eliminarEpicrisis">Eliminar epicrisis actual</label>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label class="form-label">📋 <?= $accion == 'crear' ? 'Epicrisis (opcional)' : 'Nueva epicrisis (opcional)' ?></label>
                                <input type="file" name="epicrisis" class="form-control" accept="image/*">
                            </div>
                            
                            <!-- NUEVO CAMPO WHATSAPP -->
                            <div class="mb-3">
                                <label class="form-label">💬 Mensaje original de WhatsApp</label>
                                <textarea name="whatsapp" class="form-control" rows="5" 
                                          placeholder="Pega aquí el mensaje original copiado del grupo de WhatsApp...&#10;Ejemplo:&#10;Buenos días, necesito un viaje para Juan Pérez&#10;Cédula: 12345678&#10;Ruta: Nazareth&#10;Vehículo: camión&#10;Empresa: Transportes Unidos&#10;Pago: $80,000"><?= htmlspecialchars($viaje['whatsapp'] ?? '') ?></textarea>
                                <small class="text-muted">Guarda el mensaje original sin formato para poder comparar después si se transcribió correctamente.</small>
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
                        <h3 class="mb-0">✏️ Editar Múltiples Viajes (<?= (int)$total_seleccionados ?> seleccionados)</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="formEditarMultiple">
                            <input type="hidden" name="editar_multiple_completo" value="1">
                            
                            <div class="alert alert-info">
                                <strong>Instrucciones:</strong> 
                                <ul class="mb-0">
                                    <li>Puedes editar campos individuales para cada registro</li>
                                    <li>También puedes usar los campos "Aplicar a todos" para cambiar un campo en todos los registros</li>
                                    <li>Los campos obligatorios deben ser completados</li>
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
                                            <select name="nombre_general" class="form-select select2-general">
                                                <option value="">-- No cambiar --</option>
                                                <?php
                                                $res = $conexion->query("SELECT nombre FROM conductores_admin ORDER BY nombre ASC");
                                                if ($res) {
                                                    while($row = $res->fetch_assoc()) {
                                                        echo '<option value="' . htmlspecialchars($row['nombre']) . '">' . htmlspecialchars($row['nombre']) . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Cédula (general)</label>
                                            <input type="text" name="cedula_general" class="form-control form-control-sm" placeholder="Dejar vacío para no cambiar">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Fecha (general)</label>
                                            <input type="date" name="fecha_general" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Pago parcial (general)</label>
                                            <input type="number" min="0" step="1" name="pago_parcial_general" class="form-control form-control-sm" placeholder="Dejar vacío para no cambiar">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Estado de pago (general)</label>
                                            <select name="pagado_general" class="form-select form-select-sm">
                                                <option value="">-- No cambiar --</option>
                                                <option value="1">✅ Pagado</option>
                                                <option value="0">❌ Pendiente</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Ruta (general)</label>
                                            <select name="ruta_general" class="form-select select2-general">
                                                <option value="">-- No cambiar --</option>
                                                <?php
                                                $res = $conexion->query("SELECT ruta FROM rutas_admin ORDER BY ruta ASC");
                                                if ($res) {
                                                    while($row = $res->fetch_assoc()) {
                                                        echo '<option value="' . htmlspecialchars($row['ruta']) . '">' . htmlspecialchars($row['ruta']) . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Vehículo (general)</label>
                                            <select name="tipo_vehiculo_general" class="form-select form-select-sm select2-single">
                                                <option value="">-- No cambiar --</option>
                                                <?php foreach($listas['vehiculos'] as $vehItem): ?>
                                                    <option value="<?= htmlspecialchars($vehItem) ?>"><?= htmlspecialchars($vehItem) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Empresa (general)</label>
                                            <select name="empresa_general" class="form-select select2-general">
                                                <option value="">-- No cambiar --</option>
                                                <?php
                                                $res = $conexion->query("SELECT nombre FROM empresas_admin ORDER BY nombre ASC");
                                                if ($res) {
                                                    while($row = $res->fetch_assoc()) {
                                                        echo '<option value="' . htmlspecialchars($row['nombre']) . '">' . htmlspecialchars($row['nombre']) . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label">WhatsApp (general)</label>
                                            <textarea name="whatsapp_general" class="form-control" rows="3" placeholder="Pega aquí el mensaje de WhatsApp para aplicar a todos los registros seleccionados..."></textarea>
                                            <small class="text-muted">Si se deja vacío, no se modificará este campo en los registros.</small>
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
                                            <th>Pagado</th>
                                            <th>Evidencia</th>
                                            <th>Epicrisis</th>
                                            <th>WhatsApp</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($viajes_seleccionados as $viaje_multi): 
                                            $id_multi = (int)$viaje_multi['id'];
                                        ?>
                                            <tr class="<?= $viaje_multi['pagado'] ? 'pagado' : 'pendiente' ?>">
                                                <td class="fw-bold"><?= $id_multi ?></td>
                                                <td>
                                                    <select name="nombre_<?= $id_multi ?>" class="form-select form-select-sm select2-fila">
                                                        <option value="<?= htmlspecialchars($viaje_multi['nombre']) ?>"><?= htmlspecialchars($viaje_multi['nombre']) ?></option>
                                                        <?php
                                                        $res = $conexion->query("SELECT nombre FROM conductores_admin ORDER BY nombre ASC");
                                                        if ($res) {
                                                            while($row = $res->fetch_assoc()) {
                                                                if ($row['nombre'] != $viaje_multi['nombre']) {
                                                                    echo '<option value="' . htmlspecialchars($row['nombre']) . '">' . htmlspecialchars($row['nombre']) . '</option>';
                                                                }
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="text" name="cedula_<?= $id_multi ?>" class="form-control form-control-sm" value="<?= htmlspecialchars($viaje_multi['cedula'] ?? '') ?>">
                                                </td>
                                                <td>
                                                    <input type="date" name="fecha_<?= $id_multi ?>" class="form-control form-control-sm" value="<?= htmlspecialchars($viaje_multi['fecha']) ?>">
                                                </td>
                                                <td>
                                                    <select name="ruta_<?= $id_multi ?>" class="form-select form-select-sm select2-fila">
                                                        <option value="<?= htmlspecialchars($viaje_multi['ruta']) ?>"><?= htmlspecialchars($viaje_multi['ruta']) ?></option>
                                                        <?php
                                                        $res = $conexion->query("SELECT ruta FROM rutas_admin ORDER BY ruta ASC");
                                                        if ($res) {
                                                            while($row = $res->fetch_assoc()) {
                                                                if ($row['ruta'] != $viaje_multi['ruta']) {
                                                                    echo '<option value="' . htmlspecialchars($row['ruta']) . '">' . htmlspecialchars($row['ruta']) . '</option>';
                                                                }
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <select name="tipo_vehiculo_<?= $id_multi ?>" class="form-select form-select-sm select2-single">
                                                        <option value="">-- Seleccionar --</option>
                                                        <?php foreach($listas['vehiculos'] as $vehItem): ?>
                                                            <option value="<?= htmlspecialchars($vehItem) ?>" <?= ($vehItem == $viaje_multi['tipo_vehiculo']) ? 'selected' : '' ?>><?= htmlspecialchars($vehItem) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <select name="empresa_<?= $id_multi ?>" class="form-select form-select-sm select2-fila">
                                                        <option value="">-- Ninguna --</option>
                                                        <option value="<?= htmlspecialchars($viaje_multi['empresa'] ?? '') ?>" selected><?= htmlspecialchars($viaje_multi['empresa'] ?? '') ?></option>
                                                        <?php
                                                        $res = $conexion->query("SELECT nombre FROM empresas_admin ORDER BY nombre ASC");
                                                        if ($res) {
                                                            while($row = $res->fetch_assoc()) {
                                                                if (($viaje_multi['empresa'] ?? '') != $row['nombre']) {
                                                                    echo '<option value="' . htmlspecialchars($row['nombre']) . '">' . htmlspecialchars($row['nombre']) . '</option>';
                                                                }
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="number" min="0" step="1" name="pago_parcial_<?= $id_multi ?>" class="form-control form-control-sm" value="<?= htmlspecialchars($viaje_multi['pago_parcial'] ?? '') ?>" placeholder="(vacío = no cambia)">
                                                </td>
                                                <td class="text-center">
                                                    <input type="checkbox" name="pagado_<?= $id_multi ?>" value="1" <?= $viaje_multi['pagado'] ? 'checked' : '' ?>>
                                                </td>
                                                <td class="text-center">
                                                    <?php if(!empty($viaje_multi['imagen'])): ?>
                                                        <img src="uploads/<?= htmlspecialchars($viaje_multi['imagen']) ?>" width="50" class="rounded img-thumb" data-bs-toggle="modal" data-bs-target="#imgModal<?= $id_multi ?>">
                                                        <div class="modal fade" id="imgModal<?= $id_multi ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-body text-center"><img src="uploads/<?= htmlspecialchars($viaje_multi['imagen']) ?>" class="img-fluid rounded"></div></div></div></div>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if(!empty($viaje_multi['epicrisis'])): ?>
                                                        <img src="uploads/<?= htmlspecialchars($viaje_multi['epicrisis']) ?>" width="50" class="rounded img-thumb" data-bs-toggle="modal" data-bs-target="#epiModal<?= $id_multi ?>">
                                                        <div class="modal fade" id="epiModal<?= $id_multi ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-body text-center"><img src="uploads/<?= htmlspecialchars($viaje_multi['epicrisis']) ?>" class="img-fluid rounded"></div></div></div></div>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <textarea name="whatsapp_<?= $id_multi ?>" class="form-control form-control-sm" rows="3" placeholder="Mensaje original de WhatsApp..."><?= htmlspecialchars($viaje_multi['whatsapp'] ?? '') ?></textarea>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="?" class="btn btn-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-warning">Guardar Cambios en <?= (int)$total_seleccionados ?> Registros</button>
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

        <div class="d-flex justify-content-end mb-3 gap-2">
            <form method="GET" action="" target="_blank">
                <?php foreach($_GET as $key => $value) {
                    if ($key != 'accion') {
                        if (is_array($value)) {
                            foreach($value as $v) echo '<input type="hidden" name="' . htmlspecialchars($key) . '[]" value="' . htmlspecialchars($v) . '">';
                        } else {
                            echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
                        }
                    }
                } ?>
                <input type="hidden" name="accion" value="informe">
                <button type="submit" class="btn btn-success btn-informe">📄 Generar Informe</button>
            </form>
            
            <div class="position-relative">
                <button type="button" class="btn btn-outline-primary" id="btnConfigColumnas">📊 Configurar Columnas</button>
                <div class="columnas-dropdown" id="dropdownColumnas">
                    <h6 class="mb-3">Seleccionar columnas a mostrar:</h6>
                    <form method="POST" id="formColumnas">
                        <?php foreach($_SESSION['columnas_visibles'] as $key => $columna): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="columnas[]" value="<?= htmlspecialchars($key) ?>" id="col_<?= htmlspecialchars($key) ?>" <?= $columna['visible'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="col_<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($columna['nombre']) ?></label>
                            </div>
                        <?php endforeach; ?>
                        <div class="mt-3 d-flex justify-content-between">
                            <button type="submit" name="actualizar_columnas" class="btn btn-sm btn-primary">Aplicar cambios</button>
                            <button type="submit" name="restablecer_columnas" class="btn btn-sm btn-secondary">Restablecer todas</button>
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
                            if (!is_array($nombresSeleccionados)) $nombresSeleccionados = [];
                            foreach($listas['nombres'] as $nom):
                                $sel = in_array($nom, $nombresSeleccionados) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($nom) ?>" <?= $sel ?>><?= htmlspecialchars($nom) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Cédula</label>
                        <select name="cedula[]" class="form-select select2-multiple" multiple data-placeholder="Todas las cédulas">
                            <?php
                            $cedulasSeleccionadas = $_GET['cedula'] ?? [];
                            if (!is_array($cedulasSeleccionadas)) $cedulasSeleccionadas = [];
                            foreach($listas['cedulas'] as $ced):
                                $sel = in_array($ced, $cedulasSeleccionadas) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($ced) ?>" <?= $sel ?>><?= htmlspecialchars($ced) ?></option>
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

                    <div class="col-md-2">
                        <label class="form-label">Estado de pago</label>
                        <select name="pagado" class="form-select">
                            <option value="">-- Todos --</option>
                            <option value="1" <?= (isset($_GET['pagado']) && $_GET['pagado'] === '1') ? 'selected' : '' ?>>✅ Pagado</option>
                            <option value="0" <?= (isset($_GET['pagado']) && $_GET['pagado'] === '0') ? 'selected' : '' ?>>❌ Pendiente</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Ruta</label>
                        <select name="ruta[]" class="form-select select2-multiple" multiple data-placeholder="Todas las rutas">
                            <?php
                            $rutasSeleccionadas = $_GET['ruta'] ?? [];
                            if (!is_array($rutasSeleccionadas)) $rutasSeleccionadas = [];
                            foreach($listas['rutas'] as $ruta):
                                $sel = in_array($ruta, $rutasSeleccionadas) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($ruta) ?>" <?= $sel ?>><?= htmlspecialchars($ruta) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Vehículo</label>
                        <select name="vehiculo[]" class="form-select select2-multiple" multiple data-placeholder="Todos los vehículos">
                            <?php
                            $vehiculosSeleccionados = $_GET['vehiculo'] ?? [];
                            if (!is_array($vehiculosSeleccionados)) $vehiculosSeleccionados = [];
                            foreach($listas['vehiculos'] as $veh):
                                $sel = in_array($veh, $vehiculosSeleccionados) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($veh) ?>" <?= $sel ?>><?= htmlspecialchars($veh) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Empresa</label>
                        <select name="empresa[]" class="form-select select2-multiple" multiple data-placeholder="Todas las empresas">
                            <?php
                            $empresasSeleccionadas = $_GET['empresa'] ?? [];
                            if (!is_array($empresasSeleccionadas)) $empresasSeleccionadas = [];
                            foreach($listas['empresas'] as $emp):
                                $sel = in_array($emp, $empresasSeleccionadas) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($emp) ?>" <?= $sel ?>><?= htmlspecialchars($emp) ?></option>
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
        // CONSTRUIR CONSULTA PARA EL LISTADO
        $where = [];
        $ids_visibles = [];

        if (!empty($_GET['nombre']) && is_array($_GET['nombre'])) {
            $nombres = array_map([$conexion, 'real_escape_string'], $_GET['nombre']);
            $nombres = array_filter($nombres, function($val) { return trim($val) !== ''; });
            if (!empty($nombres)) $where[] = "nombre IN ('" . implode("','", $nombres) . "')";
        }

        if (!empty($_GET['cedula']) && is_array($_GET['cedula'])) {
            $cedulas = array_map([$conexion, 'real_escape_string'], $_GET['cedula']);
            $cedulas = array_filter($cedulas, function($val) { return trim($val) !== ''; });
            if (!empty($cedulas)) $where[] = "cedula IN ('" . implode("','", $cedulas) . "')";
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

        if (isset($_GET['pagado']) && $_GET['pagado'] !== '') {
            $pagado_val = (int)$_GET['pagado'];
            $where[] = "pagado = $pagado_val";
        }

        if (!empty($_GET['ruta']) && is_array($_GET['ruta'])) {
            $rutas = array_map([$conexion, 'real_escape_string'], $_GET['ruta']);
            $rutas = array_filter($rutas, function($val) { return trim($val) !== ''; });
            if (!empty($rutas)) $where[] = "ruta IN ('" . implode("','", $rutas) . "')";
        }

        if (!empty($_GET['vehiculo']) && is_array($_GET['vehiculo'])) {
            $vehiculos = array_map([$conexion, 'real_escape_string'], $_GET['vehiculo']);
            $vehiculos = array_filter($vehiculos, function($val) { return trim($val) !== ''; });
            if (!empty($vehiculos)) $where[] = "tipo_vehiculo IN ('" . implode("','", $vehiculos) . "')";
        }

        if (!empty($_GET['empresa']) && is_array($_GET['empresa'])) {
            $empresas = array_map([$conexion, 'real_escape_string'], $_GET['empresa']);
            $empresas = array_filter($empresas, function($val) { return trim($val) !== ''; });
            if (!empty($empresas)) $where[] = "empresa IN ('" . implode("','", $empresas) . "')";
        }

        $sql = "SELECT * FROM viajes";
        if (count($where) > 0) $sql .= " WHERE " . implode(" AND ", $where);
        $sql .= " ORDER BY fecha DESC, id DESC";
        $resultado = $conexion->query($sql);
        ?>

        <!-- TABLA DE VIAJES -->
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
                        <span class="badge bg-success align-self-center">✅ <?= count($_SESSION['seleccionados']) ?> seleccionado(s)</span>
                    <?php endif; ?>
                    <a href="?accion=crear" class="btn btn-success">➕ Nuevo Viaje</a>
                </div>
            </div>
            
            <?php if (!empty($_SESSION['seleccionados'])): ?>
                <div class="sticky-actions">
                    <h5>📋 Acciones para los <?= count($_SESSION['seleccionados']) ?> viajes seleccionados:</h5>
                    <div class="d-flex gap-2 mt-2">
                        <form method="POST">
                            <button type="submit" name="accion_multiple" value="editar" class="btn btn-warning">✏️ Editar Seleccionados (Completo)</button>
                        </form>
                        <form method="POST">
                            <button type="submit" name="accion_multiple" value="eliminar" class="btn btn-danger" onclick="return confirm('¿Eliminar los <?= count($_SESSION['seleccionados']) ?> registros seleccionados?')">🗑️ Eliminar Seleccionados</button>
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
                            <button type="submit" class="btn btn-sm btn-outline-primary">✅ Seleccionar todos los visibles</button>
                        </form>
                        <form method="POST" class="d-inline ms-2">
                            <input type="hidden" name="seleccionar_todos" value="0">
                            <input type="hidden" name="ids_visibles" id="idsVisibles2" value="">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">❌ Deseleccionar todos los visibles</button>
                        </form>
                    </div>
                </div>

                <div class="table-container">
                    <table class="table table-bordered table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="seleccionarTodosCheckbox" style="transform: scale(1.2);">
                                </th>
                                <?php 
                                $columnas_ordenadas = $_SESSION['columnas_visibles'];
                                uasort($columnas_ordenadas, function($a, $b) { return $a['orden'] <=> $b['orden']; });
                                foreach($columnas_ordenadas as $key => $columna):
                                    if (!$columna['visible']) continue;
                                    $width = '';
                                    if ($key == 'id') $width = 'style="width: 70px;"';
                                    if ($key == 'imagen' || $key == 'epicrisis') $width = 'style="width: 100px;"';
                                    if ($key == 'whatsapp') $width = 'style="min-width: 300px; max-width: 500px;"';
                                ?>
                                    <th <?= $width ?>><?= htmlspecialchars($columna['nombre']) ?></th>
                                <?php endforeach; ?>
                                <th style="width: 130px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($resultado && $resultado->num_rows > 0): ?>
                            <?php while($row = $resultado->fetch_assoc()): 
                                $id_registro = (int)$row['id'];
                                $ids_visibles[] = $id_registro;
                                $esta_seleccionado = in_array($id_registro, $_SESSION['seleccionados']);
                                $clase_fila = $row['pagado'] ? 'pagado' : 'pendiente';
                            ?>
                                <tr class="<?= $esta_seleccionado ? 'seleccionado' : '' ?> <?= $clase_fila ?>">
                                    <td class="text-center">
                                        <form method="POST" class="d-inline toggle-form">
                                            <input type="hidden" name="toggle_seleccion" value="<?= $id_registro ?>">
                                            <input type="checkbox" class="form-check-input row-selector" 
                                                   onchange="this.form.submit()" <?= $esta_seleccionado ? 'checked' : '' ?>>
                                        </form>
                                    </td>
                                    
                                    <?php foreach($columnas_ordenadas as $key => $columna): 
                                        if (!$columna['visible']) continue;
                                        
                                        switch($key):
                                            case 'id': ?>
                                                <td class="fw-bold"><?= $id_registro ?></td>
                                                <?php break;
                                            case 'nombre': ?>
                                                <td><?= htmlspecialchars($row['nombre']) ?></td>
                                                <?php break;
                                            case 'cedula': ?>
                                                <td><?= !empty($row['cedula']) ? htmlspecialchars($row['cedula']) : '<span class="text-muted">—</span>' ?></td>
                                                <?php break;
                                            case 'fecha': ?>
                                                <td><?= htmlspecialchars($row['fecha']) ?></td>
                                                <?php break;
                                            case 'ruta': ?>
                                                <td><?= htmlspecialchars($row['ruta']) ?></td>
                                                <?php break;
                                            case 'tipo_vehiculo': ?>
                                                <td><?= htmlspecialchars($row['tipo_vehiculo']) ?></td>
                                                <?php break;
                                            case 'empresa': ?>
                                                <td><?= !empty($row['empresa']) ? htmlspecialchars($row['empresa']) : '<span class="text-muted">—</span>' ?></td>
                                                <?php break;
                                            case 'pago_parcial': ?>
                                                <td>
                                                    <?php if ($row['pago_parcial'] !== null && $row['pago_parcial'] !== ''): ?>
                                                        <span class="badge bg-info text-dark">$<?= number_format((int)$row['pago_parcial'], 0, ',', '.') ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php break;
                                            case 'pagado': ?>
                                                <td>
                                                    <?php if ($row['pagado'] == 1): ?>
                                                        <span class="badge-pagado">✅ Pagado</span>
                                                    <?php else: ?>
                                                        <span class="badge-pendiente">❌ Pendiente</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php break;
                                            case 'imagen': ?>
                                                <td class="text-center">
                                                    <?php if(!empty($row['imagen'])): ?>
                                                        <img src="uploads/<?= htmlspecialchars($row['imagen']) ?>" width="50" class="rounded img-thumb" 
                                                             data-bs-toggle="modal" data-bs-target="#imgModal<?= $id_registro ?>" style="cursor: pointer;">
                                                        <div class="modal fade" id="imgModal<?= $id_registro ?>" tabindex="-1">
                                                            <div class="modal-dialog modal-dialog-centered">
                                                                <div class="modal-content">
                                                                    <div class="modal-body text-center">
                                                                        <img src="uploads/<?= htmlspecialchars($row['imagen']) ?>" class="img-fluid rounded">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php break;
                                            case 'epicrisis': ?>
                                                <td class="text-center">
                                                    <?php if(!empty($row['epicrisis'])): ?>
                                                        <img src="uploads/<?= htmlspecialchars($row['epicrisis']) ?>" width="50" class="rounded img-thumb" 
                                                             data-bs-toggle="modal" data-bs-target="#epiModal<?= $id_registro ?>" style="cursor: pointer;">
                                                        <div class="modal fade" id="epiModal<?= $id_registro ?>" tabindex="-1">
                                                            <div class="modal-dialog modal-dialog-centered">
                                                                <div class="modal-content">
                                                                    <div class="modal-body text-center">
                                                                        <img src="uploads/<?= htmlspecialchars($row['epicrisis']) ?>" class="img-fluid rounded">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php break;
                                            case 'whatsapp': ?>
                                                <td class="whatsapp-cell">
                                                    <?php if(!empty($row['whatsapp'])): ?>
                                                        <div style="white-space: pre-wrap; word-break: break-word; font-family: monospace; font-size: 12px; background: #f8f9fa; padding: 8px; border-radius: 5px; max-height: 200px; overflow-y: auto;">
                                                            <?= nl2br(htmlspecialchars($row['whatsapp'])) ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php break;
                                        endswitch;
                                    endforeach; ?>
                                    
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="?accion=editar&id=<?= $id_registro ?>" class="btn btn-warning" title="Editar">✏️</a>
                                            <a href="?accion=eliminar&id=<?= $id_registro ?>" class="btn btn-danger" title="Eliminar" onclick="return confirm('¿Seguro de eliminar este viaje?')">🗑️</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <?php 
                            $total_columnas = 1; // columna de selección
                            foreach($columnas_ordenadas as $col) if ($col['visible']) $total_columnas++;
                            $total_columnas++; // columna de acciones
                            ?>
                            <tr>
                                <td colspan="<?= $total_columnas ?>" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                        <p class="mt-2">No se encontraron viajes con los filtros seleccionados.</p>
                                        <a href="?accion=crear" class="btn btn-success btn-sm">➕ Crear primer viaje</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Script para actualizar los inputs de IDs visibles -->
        <script>
            const idsVisiblesArray = <?= json_encode($ids_visibles) ?>;
            document.getElementById('idsVisibles') && (document.getElementById('idsVisibles').value = idsVisiblesArray.join(','));
            document.getElementById('idsVisibles2') && (document.getElementById('idsVisibles2').value = idsVisiblesArray.join(','));
            
            // Checkbox "seleccionar todos" en el encabezado
            const selectAllCheckbox = document.getElementById('seleccionarTodosCheckbox');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('.row-selector');
                    checkboxes.forEach(cb => {
                        if (cb.checked !== this.checked) {
                            cb.checked = this.checked;
                            // Disparar submit del formulario padre
                            const form = cb.closest('form');
                            if (form) form.submit();
                        }
                    });
                });
            }
        </script>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/i18n/es.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl); });
    
    // Select2 para selects múltiples (filtros)
    $('.select2-multiple').select2({ 
        width: '100%', 
        placeholder: function() { return $(this).data('placeholder'); }, 
        allowClear: true, 
        language: 'es' 
    });
    
    // Select2 para selects simples
    $('.select2-single').select2({ 
        width: '100%', 
        placeholder: '-- Seleccionar --', 
        allowClear: true, 
        language: 'es' 
    });
    
    // Función para configurar Select2 con creación de nuevos elementos
    function setupCreatableSelect2(selector, tabla, valorActual = null) {
        $(selector).select2({
            width: '100%',
            placeholder: '-- Buscar o escribir nuevo --',
            allowClear: true,
            language: 'es',
            tags: true,
            createTag: function(params) {
                var term = $.trim(params.term);
                if (term === '') return null;
                return {
                    id: term,
                    text: term + ' (➕ Crear nuevo)',
                    newOption: true
                };
            },
            templateResult: function(data) {
                if (data.newOption) {
                    return $('<span style="color: #0d6efd; font-weight: bold;">➕ ' + data.text.replace(' (➕ Crear nuevo)', '') + '</span>');
                }
                return data.text;
            },
            templateSelection: function(data) {
                if (data.newOption) {
                    return data.text.replace(' (➕ Crear nuevo)', '');
                }
                return data.text;
            },
            ajax: {
                url: window.location.href,
                dataType: 'json',
                delay: 300,
                data: function(params) {
                    return {
                        ajax: 'buscar',
                        tabla: tabla,
                        term: params.term || ''
                    };
                },
                processResults: function(data) {
                    return { results: data.results || [] };
                },
                cache: true
            },
            minimumInputLength: 1
        });
        
        // Manejar creación de nuevo elemento
        $(selector).on('select2:select', function(e) {
            var data = e.params.data;
            if (data.newOption) {
                var nuevoValor = data.text.replace(' (➕ Crear nuevo)', '');
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        ajax: 'crear',
                        tabla: tabla,
                        valor: nuevoValor
                    },
                    success: function(response) {
                        if (response.success) {
                            var newOption = new Option(response.valor, response.valor, true, true);
                            $(selector).append(newOption).trigger('change');
                            // Mostrar toast de éxito
                            var toastHtml = '<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050"><div class="toast show" role="alert" data-bs-autohide="true" data-bs-delay="3000"><div class="toast-header bg-success text-white"><strong class="me-auto">✅ Éxito</strong><button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button></div><div class="toast-body">' + response.mensaje + ': ' + response.valor + '</div></div></div>';
                            $('body').append(toastHtml);
                            var toast = new bootstrap.Toast($('.toast').last()[0]);
                            toast.show();
                            setTimeout(function() { $('.position-fixed').last().remove(); }, 3500);
                        } else {
                            alert('Error: ' + response.mensaje);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Error al crear: ' + error);
                    }
                });
            }
        });
        
        if (valorActual) {
            $(selector).val(valorActual).trigger('change');
        }
    }
    
    // Inicializar selectores dinámicos en formulario crear/editar
    <?php if ($accion == 'crear' || ($accion == 'editar' && $viaje)): ?>
        setupCreatableSelect2('#nombreSelect', 'conductores', <?= json_encode($viaje['nombre'] ?? null) ?>);
        setupCreatableSelect2('#rutaSelect', 'rutas', <?= json_encode($viaje['ruta'] ?? null) ?>);
        setupCreatableSelect2('#empresaSelect', 'empresas', <?= json_encode($viaje['empresa'] ?? null) ?>);
    <?php endif; ?>
    
    // Inicializar selectores dinámicos en edición múltiple
    <?php if ($accion == 'editar_multiple'): ?>
        $('.select2-general').each(function() {
            var tabla = '';
            if ($(this).attr('name') === 'nombre_general') tabla = 'conductores';
            if ($(this).attr('name') === 'ruta_general') tabla = 'rutas';
            if ($(this).attr('name') === 'empresa_general') tabla = 'empresas';
            if (tabla) {
                setupCreatableSelect2(this, tabla);
            } else {
                $(this).select2({ width: '100%', placeholder: '-- Seleccionar --', allowClear: true, language: 'es' });
            }
        });
        
        $('.select2-fila').each(function() {
            var tabla = '';
            if ($(this).attr('name') && $(this).attr('name').startsWith('nombre_')) tabla = 'conductores';
            if ($(this).attr('name') && $(this).attr('name').startsWith('ruta_')) tabla = 'rutas';
            if ($(this).attr('name') && $(this).attr('name').startsWith('empresa_')) tabla = 'empresas';
            if (tabla) {
                setupCreatableSelect2(this, tabla);
            } else {
                $(this).select2({ width: '100%', placeholder: '-- Seleccionar --', allowClear: true, language: 'es' });
            }
        });
    <?php endif; ?>
    
    // Configuración de columnas dropdown
    const btnConfig = document.getElementById('btnConfigColumnas');
    const dropdown = document.getElementById('dropdownColumnas');
    if (btnConfig && dropdown) {
        btnConfig.addEventListener('click', function(e) { 
            e.stopPropagation(); 
            dropdown.classList.toggle('show'); 
        });
        document.addEventListener('click', function(e) { 
            if (dropdown && !dropdown.contains(e.target) && !btnConfig.contains(e.target)) 
                dropdown.classList.remove('show'); 
        });
        if (dropdown) dropdown.addEventListener('click', function(e) { e.stopPropagation(); });
    }
});

// Confirmación para edición múltiple
document.getElementById('formEditarMultiple')?.addEventListener('submit', function(e) {
    const totalRegistros = <?= count($viajes_seleccionados ?? []) ?>;
    if (totalRegistros === 0) { 
        e.preventDefault(); 
        alert('No hay registros para editar.'); 
        return false; 
    }
    if (!confirm(`¿Estás seguro de editar ${totalRegistros} registros?`)) { 
        e.preventDefault(); 
        return false; 
    }
});
</script>
</body>
</html>