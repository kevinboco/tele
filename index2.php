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
// ... (TODO EL RESTO DEL CÓDIGO DE MANEJO DE SELECCIÓN SE MANTIENE IGUAL)
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
    // ... (TODO EL RESTO DEL CÓDIGO DE PROCESAMIENTO POST SE MANTIENE IGUAL)
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

        // NUEVO: Pago parcial (opcional)
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
    
    // EDITAR VIAJE INDIVIDUAL - CON LA MODIFICACIÓN QUE SOLICITAS
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

        // NUEVO: Pago parcial (opcional)
        $pago_parcial = normalizarPagoParcial($conexion, $_POST['pago_parcial'] ?? null);
        
        // Obtener datos ACTUALES del registro para comparar
        $sql_actual = "SELECT nombre, cedula, fecha, ruta, tipo_vehiculo, empresa, pago_parcial FROM viajes WHERE id = $id";
        $res_actual = $conexion->query($sql_actual);
        $datos_actuales = $res_actual->fetch_assoc();
        
        // Detectar si SOLO cambió la cédula (y los otros campos obligatorios siguen igual)
        $solo_cambio_cedula = false;
        $cedula_nueva = isset($_POST['cedula']) ? trim($_POST['cedula']) : '';
        
        if ($datos_actuales) {
            // Comparar los campos principales (excepto cédula)
            $mismo_nombre = ($nombre == $datos_actuales['nombre']);
            $misma_fecha = ($fecha == $datos_actuales['fecha']);
            $misma_ruta = ($ruta == $datos_actuales['ruta']);
            $mismo_vehiculo = ($tipo_vehiculo == $datos_actuales['tipo_vehiculo']);
            
            // Verificar si la empresa es la misma (manejando NULLs)
            $empresa_actual = $datos_actuales['empresa'] ?? '';
            $empresa_nueva = isset($_POST['empresa']) ? trim($_POST['empresa']) : '';
            $misma_empresa = ($empresa_actual == $empresa_nueva);
            
            // Verificar si pago_parcial es el mismo
            $pago_actual = $datos_actuales['pago_parcial'] ?? null;
            $mismo_pago = ($pago_parcial === "NULL" && $pago_actual === null) || 
                         ($pago_parcial !== "NULL" && (int)$pago_parcial === (int)$pago_actual);
            
            // Verificar si la cédula cambió
            $cedula_actual = $datos_actuales['cedula'] ?? '';
            $cambio_cedula = ($cedula_actual !== $cedula_nueva);
            
            // Si todos los otros campos son iguales PERO la cédula cambió
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
            // PRIMERO: Actualizar el registro individual
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
                // SEGUNDO: Si solo cambió la cédula, actualizar TODOS los registros con el mismo nombre
                if ($solo_cambio_cedula && !empty($cedula_nueva)) {
                    $nombre_escapado = $conexion->real_escape_string($nombre);
                    
                    // Si la cédula nueva está vacía, ponerla como NULL
                    $valor_cedula = empty($cedula_nueva) ? "NULL" : "'" . $conexion->real_escape_string($cedula_nueva) . "'";
                    
                    $sql_masivo = "UPDATE viajes SET cedula = $valor_cedula 
                                   WHERE nombre = '$nombre_escapado' 
                                   AND id != $id"; // No incluir el registro que ya actualizamos
                    
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

        // General para todos (opcional)
        $pago_parcial_general = normalizarPagoParcial($conexion, $_POST['pago_parcial_general'] ?? null);
        $hay_pago_general = (isset($_POST['pago_parcial_general']) && trim((string)$_POST['pago_parcial_general']) !== '');

        foreach ($ids as $id_viaje) {
            $id_viaje = (int)$id_viaje;
            
            // Obtener los datos específicos para este ID si existen
            $nombre_key   = "nombre_$id_viaje";
            $cedula_key   = "cedula_$id_viaje";
            $fecha_key    = "fecha_$id_viaje";
            $ruta_key     = "ruta_$id_viaje";
            $vehiculo_key = "tipo_vehiculo_$id_viaje";
            $empresa_key  = "empresa_$id_viaje";
            $pago_key     = "pago_parcial_$id_viaje";
            
            // Usar valores específicos o los generales
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

            // NUEVO: pago parcial (por registro o general)
            $pago_parcial = "NULL";
            if (isset($_POST[$pago_key]) && trim((string)$_POST[$pago_key]) !== '') {
                $pago_parcial = normalizarPagoParcial($conexion, $_POST[$pago_key]);
            } elseif ($hay_pago_general) {
                $pago_parcial = $pago_parcial_general;
            } // si no hay ninguno, queda NULL (no cambia el valor actual en BD) => lo manejamos con IFNULL
            
            // Si algún campo obligatorio está vacío, saltar este registro
            if (!$nombre || !$fecha || !$ruta || !$tipo_vehiculo) {
                continue;
            }
            
            // Remover comillas para NULL
            $nombre        = ($nombre === NULL) ? "NULL" : $nombre;
            $fecha         = ($fecha === NULL) ? "NULL" : $fecha;
            $ruta          = ($ruta === NULL) ? "NULL" : $ruta;
            $tipo_vehiculo = ($tipo_vehiculo === NULL) ? "NULL" : $tipo_vehiculo;

            // Si pago_parcial viene NULL desde el formulario, NO pisar el dato actual:
            // Usamos: pago_parcial = IFNULL($pago_parcial, pago_parcial)
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
        
        // Limpiar selección después de editar
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
            // Eliminar múltiples registros
            $ids_str = implode(',', array_map('intval', $ids));
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
        elseif ($_POST['accion_multiple'] == 'editar') {
            // Redirigir a edición múltiple completa
            header("Location: ?accion=editar_multiple");
            exit();
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