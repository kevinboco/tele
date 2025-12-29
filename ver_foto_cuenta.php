<?php
include("nav.php");
// Conexión a la base de datos
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

// Variables
$mensaje = "";
$mensaje_tipo = "";
$editar_id = null;
$datos_edicion = null;

// PROCESAR FORMULARIO - Guardar o Actualizar
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $titulo       = $conn->real_escape_string($_POST['titulo']);
    $empresa      = $conn->real_escape_string($_POST['empresa']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin    = $_POST['fecha_fin'];
    $observaciones = $conn->real_escape_string($_POST['observaciones'] ?? '');
    $editar_id    = isset($_POST['editar_id']) ? intval($_POST['editar_id']) : null;
    
    /* ========== FOTOS PRINCIPALES (MÚLTIPLES) ========== */
    $fotos_paths = [];
    if (isset($_FILES['fotos']) && isset($_FILES['fotos']['name']) && count($_FILES['fotos']['name']) > 0) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        foreach ($_FILES['fotos']['name'] as $key => $name) {
            if ($_FILES['fotos']['error'][$key] == 0 && $name !== '') {
                $file_extension = pathinfo($name, PATHINFO_EXTENSION);
                $file_name = uniqid() . '_' . date('Ymd_His') . '_' . $key . '.' . $file_extension;
                $foto_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['fotos']['tmp_name'][$key], $foto_path)) {
                    $fotos_paths[] = $conn->real_escape_string($foto_path);
                }
            }
        }
    }
    $fotos_string = implode(',', $fotos_paths);

    /* ========== COMPROBANTE BANCOLOMBIA (MÚLTIPLES) ========== */
    $comprobantes_paths = [];
    if (isset($_FILES['comprobante']) && isset($_FILES['comprobante']['name']) && count((array)$_FILES['comprobante']['name']) > 0) {
        $upload_dir_comp = 'uploads/comprobantes/';
        if (!is_dir($upload_dir_comp)) {
            mkdir($upload_dir_comp, 0777, true);
        }

        // Cuando el input es name="comprobante[]" multiple
        if (is_array($_FILES['comprobante']['name'])) {
            foreach ($_FILES['comprobante']['name'] as $k => $name_comp) {
                if ($_FILES['comprobante']['error'][$k] == 0 && $name_comp !== '') {
                    $ext = pathinfo($name_comp, PATHINFO_EXTENSION);
                    $file_name_c = 'comp_' . uniqid() . '_' . date('Ymd_His') . '_' . $k . '.' . $ext;
                    $ruta_comprobante = $upload_dir_comp . $file_name_c;

                    if (move_uploaded_file($_FILES['comprobante']['tmp_name'][$k], $ruta_comprobante)) {
                        $comprobantes_paths[] = $conn->real_escape_string($ruta_comprobante);
                    }
                }
            }
        } else {
            // Por si acaso el navegador lo manda como un solo archivo
            if ($_FILES['comprobante']['error'] == 0 && $_FILES['comprobante']['name'] !== '') {
                $name_comp = $_FILES['comprobante']['name'];
                $ext = pathinfo($name_comp, PATHINFO_EXTENSION);
                $file_name_c = 'comp_' . uniqid() . '_' . date('Ymd_His') . '.' . $ext;
                $ruta_comprobante = $upload_dir_comp . $file_name_c;

                if (move_uploaded_file($_FILES['comprobante']['tmp_name'], $ruta_comprobante)) {
                    $comprobantes_paths[] = $conn->real_escape_string($ruta_comprobante);
                }
            }
        }
    }
    $comprobantes_string = implode(',', $comprobantes_paths);
    
    /* ========== INSERT / UPDATE ========== */
    if ($editar_id) {
        // Obtener fotos y comprobantes existentes
        $sql_existing = "SELECT foto_path, comprobante_path FROM cuentas_cobro WHERE id = $editar_id";
        $result = $conn->query($sql_existing);
        $existing_fotos        = '';
        $existing_comprobantes = '';
        if ($row = $result->fetch_assoc()) {
            $existing_fotos        = $row['foto_path'];
            $existing_comprobantes = $row['comprobante_path'];
        }

        // FOTOS: anexar nuevas si hay, si no mantener
        if (!empty($fotos_string)) {
            $fotos_string = ($existing_fotos ? $existing_fotos . ',' : '') . $fotos_string;
        } else {
            $fotos_string = $existing_fotos;
        }

        // COMPROBANTES: anexar nuevas si hay, si no mantener
        if (!empty($comprobantes_string)) {
            $comprobantes_string = ($existing_comprobantes ? $existing_comprobantes . ',' : '') . $comprobantes_string;
        } else {
            $comprobantes_string = $existing_comprobantes;
        }
        
        $sql = "UPDATE cuentas_cobro 
                SET titulo='$titulo', 
                    empresa='$empresa', 
                    fecha_inicio='$fecha_inicio', 
                    fecha_fin='$fecha_fin', 
                    observaciones='$observaciones',
                    foto_path='$fotos_string',
                    comprobante_path='$comprobantes_string'
                WHERE id=$editar_id";
        $accion = "actualizada";
    } else {
        // INSERTAR nuevo registro
        $sql = "INSERT INTO cuentas_cobro (titulo, empresa, fecha_inicio, fecha_fin, observaciones, foto_path, comprobante_path) 
                VALUES ('$titulo', '$empresa', '$fecha_inicio', '$fecha_fin', '$observaciones', '$fotos_string', '$comprobantes_string')";
        $accion = "guardada";
    }
    
    if ($conn->query($sql) === TRUE) {
        $mensaje = "✅ Cuenta de cobro $accion exitosamente";
        $mensaje_tipo = "success";
        $editar_id = null;
    } else {
        $mensaje = "❌ Error: " . $conn->error;
        $mensaje_tipo = "error";
    }
}

// ELIMINAR CUENTA
if (isset($_GET['eliminar'])) {
    $eliminar_id = intval($_GET['eliminar']);

    // Borrar también archivos (fotos y comprobantes)
    $sql_files = "SELECT foto_path, comprobante_path FROM cuentas_cobro WHERE id = $eliminar_id";
    $res_files = $conn->query($sql_files);
    if ($rowf = $res_files->fetch_assoc()) {
        // borrar fotos
        if (!empty($rowf['foto_path'])) {
            $fotos_borrar = explode(',', $rowf['foto_path']);
            foreach ($fotos_borrar as $fb) {
                if ($fb && file_exists($fb)) {
                    @unlink($fb);
                }
            }
        }
        // borrar comprobantes
        if (!empty($rowf['comprobante_path'])) {
            $comps_borrar = explode(',', $rowf['comprobante_path']);
            foreach ($comps_borrar as $cb) {
                if ($cb && file_exists($cb)) {
                    @unlink($cb);
                }
            }
        }
    }

    $sql = "DELETE FROM cuentas_cobro WHERE id = $eliminar_id";
    if ($conn->query($sql) === TRUE) {
        $mensaje = "✅ Cuenta eliminada exitosamente";
        $mensaje_tipo = "success";
    } else {
        $mensaje = "❌ Error al eliminar: " . $conn->error;
        $mensaje_tipo = "error";
    }
}

// ELIMINAR FOTO INDIVIDUAL
if (isset($_GET['eliminar_foto'])) {
    $cuenta_id     = intval($_GET['cuenta_id']);
    $foto_eliminar = $conn->real_escape_string($_GET['eliminar_foto']);
    
    // Obtener fotos actuales
    $sql   = "SELECT foto_path FROM cuentas_cobro WHERE id = $cuenta_id";
    $result = $conn->query($sql);
    if ($row = $result->fetch_assoc()) {
        $fotos = explode(',', $row['foto_path']);
        $nuevas_fotos = array_filter($fotos, function($foto) use ($foto_eliminar) {
            return $foto !== $foto_eliminar;
        });
        
        // Eliminar archivo físico
        if (file_exists($foto_eliminar)) {
            unlink($foto_eliminar);
        }
        
        // Actualizar base de datos
        $nuevas_fotos_string = implode(',', $nuevas_fotos);
        $sql_update = "UPDATE cuentas_cobro 
                       SET foto_path = '$nuevas_fotos_string' 
                       WHERE id = $cuenta_id";
        if ($conn->query($sql_update)) {
            $mensaje = "✅ Foto eliminada exitosamente";
            $mensaje_tipo = "success";
        }
    }
}

// OBTENER DATOS PARA EDITAR
if (isset($_GET['editar'])) {
    $editar_id = intval($_GET['editar']);
    $sql = "SELECT * FROM cuentas_cobro WHERE id = $editar_id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $datos_edicion = $result->fetch_assoc();
    }
}

// CANCELAR EDICIÓN
if (isset($_GET['cancelar'])) {
    $editar_id    = null;
    $datos_edicion = null;
}

// OBTENER EMPRESAS PARA FILTRO Y DATOSLIST
$sql_empresas = "SELECT DISTINCT empresa FROM cuentas_cobro ORDER BY empresa";
$result_empresas = $conn->query($sql_empresas);
$empresas = [];
while ($row = $result_empresas->fetch_assoc()) {
    $empresas[] = $row['empresa'];
}

// FILTRAR por empresa
$filtro_empresa = isset($_GET['empresa']) ? $conn->real_escape_string($_GET['empresa']) : '';
$where = $filtro_empresa ? "WHERE empresa = '$filtro_empresa'" : "";

// OBTENER CUENTAS DE COBRO
$sql_cuentas = "SELECT * FROM cuentas_cobro $where ORDER BY created_at DESC";
$result_cuentas = $conn->query($sql_cuentas);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Cuentas de Cobro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --accent-color: #7209b7;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-color: #6c757d;
            --border-radius: 12px;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }
        
        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 20px;
            min-height: 100vh;
            color: var(--dark-color);
        }
        
        .container { 
            max-width: 1400px; 
            margin: 0 auto; 
            background: white; 
            padding: 30px; 
            border-radius: var(--border-radius); 
            box-shadow: var(--box-shadow);
            position: relative;
            overflow: hidden;
        }
        
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
        }
        
        h1 { 
            color: var(--secondary-color); 
            margin-bottom: 25px; 
            text-align: center;
            font-size: 2.5rem;
            position: relative;
            padding-bottom: 15px;
        }
        
        h1::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
            border-radius: 2px;
        }
        
        .mensaje { 
            padding: 15px; 
            margin: 20px 0; 
            border-radius: var(--border-radius); 
            text-align: center;
            font-weight: 600;
            animation: slideIn 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .success { 
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); 
            color: #155724; 
            border-left: 5px solid #28a745;
        }
        
        .error { 
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); 
            color: #721c24; 
            border-left: 5px solid #dc3545;
        }
        
        .form-section, .list-section { 
            margin: 40px 0; 
            padding: 30px; 
            border-radius: var(--border-radius); 
            background: var(--light-color);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .form-section h2, .list-section h2 {
            color: var(--primary-color);
            margin-bottom: 25px;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }
        
        .form-group { 
            margin-bottom: 20px; 
        }
        
        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            color: var(--secondary-color);
            font-size: 0.95rem;
        }
        
        input[type="text"], 
        input[type="date"], 
        select, 
        input[list],
        textarea { 
            width: 100%; 
            padding: 12px 15px; 
            border: 2px solid #e1e5eb; 
            border-radius: 8px; 
            font-size: 1rem;
            transition: var(--transition);
            background: white;
        }
        
        input[type="text"]:focus, 
        input[type="date"]:focus, 
        select:focus, 
        input[list]:focus,
        textarea:focus { 
            outline: none; 
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
            font-family: inherit;
        }
        
        .form-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 25px;
        }
        
        .btn { 
            padding: 12px 25px; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: var(--transition);
            font-size: 1rem;
        }
        
        .btn-primary { 
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); 
            color: white; 
        }
        
        .btn-primary:hover { 
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color)); 
            transform: translateY(-2px);
            box-shadow: 0 7px 15px rgba(67, 97, 238, 0.3);
        }
        
        .btn-secondary { 
            background: var(--gray-color); 
            color: white; 
        }
        
        .btn-secondary:hover { 
            background: #5a6268; 
            transform: translateY(-2px);
            box-shadow: 0 7px 15px rgba(108, 117, 125, 0.3);
        }
        
        .btn-success { 
            background: linear-gradient(135deg, #28a745, #20c997); 
            color: white; 
        }
        
        .btn-success:hover { 
            background: linear-gradient(135deg, #218838, #1e9c7a); 
            transform: translateY(-2px);
            box-shadow: 0 7px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-danger { 
            background: linear-gradient(135deg, #dc3545, #e83e8c); 
            color: white; 
        }
        
        .btn-danger:hover { 
            background: linear-gradient(135deg, #c82333, #d63384); 
            transform: translateY(-2px);
            box-shadow: 0 7px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-info { 
            background: linear-gradient(135deg, #17a2b8, #138496); 
            color: white; 
        }
        
        .btn-info:hover { 
            background: linear-gradient(135deg, #138496, #117a8b); 
            transform: translateY(-2px);
            box-shadow: 0 7px 15px rgba(23, 162, 184, 0.3);
        }
        
        .filtros { 
            background: white; 
            padding: 20px; 
            border-radius: var(--border-radius); 
            margin-bottom: 30px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .grid-cuentas { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); 
            gap: 25px; 
            margin-top: 20px; 
        }
        
        .cuenta-card { 
            border-radius: var(--border-radius); 
            padding: 0;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .cuenta-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .cuenta-header {
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
            color: white;
            padding: 20px;
            position: relative;
        }
        
        .cuenta-header h3 {
            font-size: 1.4rem;
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .cuenta-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(5px);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .cuenta-fotos { 
            display: flex; 
            gap: 8px; 
            margin: 15px;
            flex-wrap: wrap; 
            justify-content: center;
        }
        
        .foto-miniatura { 
            width: 70px; 
            height: 70px; 
            object-fit: cover; 
            border-radius: 8px; 
            cursor: pointer; 
            border: 2px solid transparent;
            transition: var(--transition);
        }
        
        .foto-miniatura:hover { 
            border-color: var(--primary-color);
            transform: scale(1.05);
        }
        
        .cuenta-body {
            padding: 0 20px 20px;
        }
        
        .cuenta-info { 
            margin-top: 15px; 
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--gray-color);
        }
        
        .info-value {
            color: var(--dark-color);
            text-align: right;
            max-width: 60%;
        }
        
        .observaciones {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }
        
        .observaciones h4 {
            color: var(--primary-color);
            margin-bottom: 8px;
            font-size: 1rem;
        }
        
        .observaciones p {
            color: var(--dark-color);
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        .comprobantes {
            margin-top: 15px;
        }
        
        .comprobantes h4 {
            color: var(--primary-color);
            margin-bottom: 10px;
            font-size: 1rem;
        }
        
        .file-list { 
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .file-item { 
            background: #f8f9fa; 
            padding: 10px 15px; 
            border-radius: 8px; 
            border-left: 4px solid var(--success-color);
            display: flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
        }
        
        .file-item:hover {
            background: #e9ecef;
        }
        
        .file-icon {
            font-size: 1.2rem;
        }
        
        .file-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            flex-grow: 1;
        }
        
        .file-link:hover {
            text-decoration: underline;
        }
        
        .cuenta-footer {
            padding: 20px;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .acciones { 
            display: flex; 
            gap: 10px; 
            flex-wrap: wrap;
        }
        
        .btn-small {
            padding: 8px 15px;
            font-size: 0.85rem;
        }
        
        .no-data {
            grid-column: 1 / -1; 
            text-align: center; 
            padding: 60px 20px; 
            color: var(--gray-color);
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .no-data i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #dee2e6;
        }
        
        .no-data h3 {
            color: var(--gray-color);
            margin-bottom: 10px;
        }
        
        /* Modal para ver foto en grande */
        .modal { 
            display: none; 
            position: fixed; 
            z-index: 10000; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            background-color: rgba(0,0,0,0.95); 
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content { 
            margin: auto; 
            display: block; 
            width: auto; 
            max-width: 90%; 
            max-height: 85vh;
            object-fit: contain;
            border-radius: 5px;
            animation: zoomIn 0.3s ease;
        }
        
        @keyframes zoomIn {
            from { transform: scale(0.8); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        
        .close { 
            position: absolute; 
            top: 20px; 
            right: 30px; 
            color: #f1f1f1; 
            font-size: 40px; 
            font-weight: bold; 
            cursor: pointer; 
            transition: var(--transition);
            z-index: 10001;
            background: rgba(0,0,0,0.5);
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .close:hover { 
            color: #fff; 
            background: rgba(0,0,0,0.8);
            transform: rotate(90deg);
        }
        
        .nav-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            font-size: 60px;
            font-weight: bold;
            color: rgba(255,255,255,0.8);
            cursor: pointer;
            user-select: none;
            padding: 20px 15px;
            transition: var(--transition);
            background: rgba(0,0,0,0.3);
            border-radius: 5px;
            z-index: 10001;
        }
        
        .nav-arrow:hover { 
            color: white; 
            background: rgba(0,0,0,0.7);
        }
        
        .nav-prev { left: 20px; }
        .nav-next { right: 20px; }
        
        .file-input-container {
            position: relative;
            margin-bottom: 10px;
        }
        
        .file-input-container input[type="file"] {
            padding: 10px;
            border: 2px dashed #ced4da;
            border-radius: 8px;
            background: #f8f9fa;
            transition: var(--transition);
        }
        
        .file-input-container input[type="file"]:hover {
            border-color: var(--primary-color);
            background: #e9ecef;
        }
        
        .current-files {
            margin-top: 15px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .file-counter {
            display: inline-block;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            text-align: center;
            line-height: 25px;
            font-size: 0.8rem;
            margin-left: 5px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            
            .form-section, .list-section {
                padding: 20px;
            }
            
            .grid-cuentas {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .cuenta-footer {
                flex-direction: column;
            }
            
            .acciones {
                justify-content: center;
            }
            
            h1 {
                font-size: 2rem;
            }
        }
        
        .toggle-form {
            display: none;
        }
        
        .toggle-btn {
            display: block;
            margin: 20px auto;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .toggle-btn:hover {
            background: var(--secondary-color);
        }
        
        #form-toggle:checked ~ .form-section {
            display: block !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-file-invoice-dollar"></i> Sistema de Cuentas de Cobro</h1>
        
        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $mensaje_tipo === 'success' ? 'success' : 'error'; ?>">
                <?php echo $mensaje_tipo === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-exclamation-circle"></i>'; ?>
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <!-- Toggle para mostrar/ocultar formulario en móviles -->
        <input type="checkbox" id="form-toggle" class="toggle-form">
        <label for="form-toggle" class="toggle-btn">
            <i class="fas fa-plus-circle"></i> <?php echo $editar_id ? '✏️ Editar Cuenta' : '➕ Nueva Cuenta de Cobro'; ?>
        </label>

        <!-- SECCIÓN DEL FORMULARIO -->
        <div class="form-section" style="<?php echo isset($_GET['editar']) ? 'display:block;' : 'display:none;'; ?>">
            <h2><i class="<?php echo $editar_id ? 'fas fa-edit' : 'fas fa-plus-circle'; ?>"></i> <?php echo $editar_id ? 'Editar Cuenta' : 'Nueva Cuenta de Cobro'; ?></h2>
            <form method="POST" enctype="multipart/form-data" id="cuentaForm">
                <?php if ($editar_id): ?>
                    <input type="hidden" name="editar_id" value="<?php echo $editar_id; ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="titulo"><i class="fas fa-heading"></i> Título Cuenta de Cobro:</label>
                        <input type="text" id="titulo" name="titulo" required 
                               value="<?php echo $datos_edicion ? htmlspecialchars($datos_edicion['titulo']) : ''; ?>"
                               placeholder="Ej: Viaje a Medellín - Marzo 2023">
                    </div>
                    
                    <div class="form-group">
                        <label for="empresa"><i class="fas fa-building"></i> Empresa:</label>
                        <input list="lista_empresas" id="empresa" name="empresa" required
                               value="<?php echo $datos_edicion ? htmlspecialchars($datos_edicion['empresa']) : ''; ?>"
                               placeholder="Selecciona o escribe una empresa">
                        <datalist id="lista_empresas">
                            <?php foreach ($empresas as $emp): ?>
                                <option value="<?php echo htmlspecialchars($emp); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <small>Selecciona una empresa existente o escribe una nueva.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="fecha_inicio"><i class="fas fa-calendar-alt"></i> Fecha Inicio:</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" required 
                               value="<?php echo $datos_edicion ? $datos_edicion['fecha_inicio'] : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="fecha_fin"><i class="fas fa-calendar-check"></i> Fecha Final:</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" required 
                               value="<?php echo $datos_edicion ? $datos_edicion['fecha_fin'] : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="observaciones"><i class="fas fa-sticky-note"></i> Observaciones:</label>
                    <textarea id="observaciones" name="observaciones" 
                              placeholder="Agrega observaciones, detalles o notas importantes sobre esta cuenta de cobro..."><?php echo $datos_edicion ? htmlspecialchars($datos_edicion['observaciones']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="fotos"><i class="fas fa-images"></i> Fotos (Múltiples): <span class="file-counter" id="foto-counter">0</span></label>
                    <div class="file-input-container">
                        <input type="file" id="fotos" name="fotos[]" multiple accept="image/*" onchange="updateFileCounter('fotos', 'foto-counter')">
                    </div>
                    <small>Selecciona una o más fotos (Ctrl+click para seleccionar múltiples)</small>
                    
                    <?php if ($datos_edicion && $datos_edicion['foto_path']): ?>
                        <div class="current-files">
                            <strong><i class="fas fa-image"></i> Fotos actuales:</strong>
                            <div class="cuenta-fotos">
                                <?php 
                                $fotos = explode(',', $datos_edicion['foto_path']);
                                $foto_count = 0;
                                foreach ($fotos as $idx => $foto): 
                                    if (!empty($foto)):
                                        $foto_count++;
                                ?>
                                    <div style="text-align: center; margin: 5px;">
                                        <img src="<?php echo $foto; ?>" alt="Foto" class="foto-miniatura" 
                                             onclick="abrirGaleria('<?php echo htmlspecialchars($datos_edicion['foto_path'], ENT_QUOTES); ?>', <?php echo $idx; ?>)">
                                        <br>
                                        <a href="?eliminar_foto=<?php echo urlencode($foto); ?>&cuenta_id=<?php echo $editar_id; ?>" 
                                           onclick="return confirm('¿Eliminar esta foto?')" 
                                           style="color: var(--danger-color); font-size: 12px; text-decoration: none;">
                                           <i class="fas fa-trash"></i> Eliminar
                                        </a>
                                    </div>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                            <p><small>Total: <?php echo $foto_count; ?> foto(s)</small></p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="comprobante"><i class="fas fa-receipt"></i> Comprobante Bancolombia: <span class="file-counter" id="comp-counter">0</span></label>
                    <div class="file-input-container">
                        <input type="file" id="comprobante" name="comprobante[]" multiple accept="image/*,application/pdf" onchange="updateFileCounter('comprobante', 'comp-counter')">
                    </div>
                    <small>Puedes subir uno o varios archivos (imágenes o PDFs)</small>
                    <?php if ($datos_edicion && !empty($datos_edicion['comprobante_path'])): ?>
                        <div class="current-files">
                            <strong><i class="fas fa-file-alt"></i> Comprobantes actuales:</strong>
                            <div class="file-list">
                                <?php
                                    $comps = explode(',', $datos_edicion['comprobante_path']);
                                    $comp_count = 0;
                                    foreach ($comps as $cp):
                                        if (!$cp) continue;
                                        $comp_count++;
                                        $ext = strtolower(pathinfo($cp, PATHINFO_EXTENSION));
                                ?>
                                    <div class="file-item">
                                        <?php if (in_array($ext, ['jpg','jpeg','png','gif','webp'])): ?>
                                            <i class="fas fa-image file-icon" style="color: #4cc9f0;"></i>
                                        <?php else: ?>
                                            <i class="fas fa-file-pdf file-icon" style="color: #f72585;"></i>
                                        <?php endif; ?>
                                        <a href="<?php echo htmlspecialchars($cp); ?>" target="_blank" class="file-link">Ver comprobante <?php echo $comp_count; ?></a>
                                        <span style="color: var(--gray-color); font-size: 0.85rem;"><?php echo strtoupper($ext); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <p><small>Total: <?php echo $comp_count; ?> comprobante(s)</small></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="<?php echo $editar_id ? 'fas fa-save' : 'fas fa-plus-circle'; ?>"></i> 
                        <?php echo $editar_id ? 'Actualizar Cuenta' : 'Crear Cuenta'; ?>
                    </button>
                    
                    <?php if ($editar_id): ?>
                        <a href="?cancelar=1" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar Edición
                        </a>
                    <?php endif; ?>
                    
                    <button type="button" class="btn btn-success" onclick="clearForm()">
                        <i class="fas fa-broom"></i> Limpiar Formulario
                    </button>
                </div>
            </form>
        </div>

        <!-- SECCIÓN DE FILTROS Y LISTA -->
        <div class="list-section">
            <h2><i class="fas fa-list-ul"></i> Cuentas de Cobro Registradas</h2>
            
            <div class="filtros">
                <form method="GET" id="filtroForm">
                    <div class="form-group">
                        <label for="filtro_empresa"><i class="fas fa-filter"></i> Filtrar por Empresa:</label>
                        <select id="filtro_empresa" name="empresa" onchange="document.getElementById('filtroForm').submit()">
                            <option value="">Todas las empresas</option>
                            <?php foreach ($empresas as $emp): ?>
                                <option value="<?php echo htmlspecialchars($emp); ?>" 
                                    <?php echo $filtro_empresa == $emp ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Mostrando <?php echo $filtro_empresa ? 'cuentas de "' . htmlspecialchars($filtro_empresa) . '"' : 'todas las cuentas'; ?></small>
                    </div>
                </form>
            </div>

            <div class="grid-cuentas">
                <?php if ($result_cuentas->num_rows > 0): 
                    $total_cuentas = $result_cuentas->num_rows;
                ?>
                    <?php while ($cuenta = $result_cuentas->fetch_assoc()): ?>
                        <div class="cuenta-card">
                            <div class="cuenta-header">
                                <h3><?php echo htmlspecialchars($cuenta['titulo']); ?></h3>
                                <div class="cuenta-badge"><?php echo htmlspecialchars($cuenta['empresa']); ?></div>
                            </div>
                            
                            <!-- Mostrar múltiples fotos -->
                            <div class="cuenta-fotos">
                                <?php 
                                if ($cuenta['foto_path']):
                                    $fotos = explode(',', $cuenta['foto_path']);
                                    $foto_count = 0;
                                    foreach ($fotos as $index => $foto): 
                                        if (!empty($foto)):
                                            $foto_count++;
                                            if ($index < 4): // Mostrar máximo 4 miniaturas
                                ?>
                                    <img src="<?php echo $foto; ?>" alt="Foto" class="foto-miniatura" 
                                         onclick="abrirGaleria('<?php echo htmlspecialchars($cuenta['foto_path'], ENT_QUOTES); ?>', <?php echo $index; ?>)">
                                <?php 
                                            endif;
                                        endif;
                                    endforeach; 
                                    
                                    if ($foto_count > 4): // Mostrar indicador de más fotos
                                ?>
                                    <div style="width:70px; height:70px; background:var(--primary-color); color:white; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:bold; cursor:pointer;"
                                         onclick="abrirGaleria('<?php echo htmlspecialchars($cuenta['foto_path'], ENT_QUOTES); ?>', 0)">
                                        +<?php echo $foto_count - 4; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php else: ?>
                                    <div style="width:100%; height:80px; background:#e9ecef; display:flex; align-items:center; justify-content:center; color:#6c757d; border-radius:5px; margin:10px;">
                                        <i class="fas fa-image" style="font-size: 2rem; margin-right: 10px;"></i>
                                        <span>Sin imágenes</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="cuenta-body">
                                <div class="cuenta-info">
                                    <div class="info-item">
                                        <span class="info-label">Fecha Inicio:</span>
                                        <span class="info-value"><?php echo date('d/m/Y', strtotime($cuenta['fecha_inicio'])); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Fecha Fin:</span>
                                        <span class="info-value"><?php echo date('d/m/Y', strtotime($cuenta['fecha_fin'])); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Creado:</span>
                                        <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($cuenta['created_at'])); ?></span>
                                    </div>
                                </div>

                                <?php if (!empty($cuenta['observaciones'])): ?>
                                    <div class="observaciones">
                                        <h4><i class="fas fa-sticky-note"></i> Observaciones</h4>
                                        <p><?php echo nl2br(htmlspecialchars($cuenta['observaciones'])); ?></p>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($cuenta['comprobante_path'])): ?>
                                    <div class="comprobantes">
                                        <h4><i class="fas fa-receipt"></i> Comprobantes Bancolombia</h4>
                                        <div class="file-list">
                                            <?php 
                                                $comps = explode(',', $cuenta['comprobante_path']);
                                                $comp_count = 0;
                                                foreach ($comps as $cp):
                                                    if (!$cp) continue;
                                                    $comp_count++;
                                                    $ext = strtolower(pathinfo($cp, PATHINFO_EXTENSION));
                                            ?>
                                                <div class="file-item">
                                                    <?php if (in_array($ext, ['jpg','jpeg','png','gif','webp'])): ?>
                                                        <i class="fas fa-image file-icon" style="color: #4cc9f0;"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-file-pdf file-icon" style="color: #f72585;"></i>
                                                    <?php endif; ?>
                                                    <a href="<?php echo htmlspecialchars($cp); ?>" target="_blank" class="file-link">Comprobante <?php echo $comp_count; ?></a>
                                                    <span style="color: var(--gray-color); font-size: 0.85rem;"><?php echo strtoupper($ext); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="cuenta-footer">
                                <div class="acciones">
                                    <a href="?editar=<?php echo $cuenta['id']; ?>">
                                        <button class="btn btn-success btn-small">
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                    </a>
                                    <a href="?eliminar=<?php echo $cuenta['id']; ?>" 
                                       onclick="return confirm('¿Estás seguro de eliminar esta cuenta? Esta acción no se puede deshacer.')">
                                        <button class="btn btn-danger btn-small">
                                            <i class="fas fa-trash"></i> Eliminar
                                        </button>
                                    </a>
                                    <?php if ($cuenta['foto_path']): ?>
                                        <button class="btn btn-info btn-small" type="button"
                                            onclick="abrirGaleria('<?php echo htmlspecialchars($cuenta['foto_path'], ENT_QUOTES); ?>', 0)">
                                            <i class="fas fa-images"></i> Ver Fotos
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div style="color: var(--gray-color); font-size: 0.8rem; align-self: center;">
                                    ID: <?php echo $cuenta['id']; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <h3><?php echo $filtro_empresa ? 'No hay cuentas para esta empresa' : 'No hay cuentas de cobro registradas'; ?></h3>
                        <p><?php echo $filtro_empresa ? 'Intenta con otro filtro o crea una nueva cuenta.' : 'Comienza creando tu primera cuenta de cobro.'; ?></p>
                        <?php if ($filtro_empresa): ?>
                            <a href="?" class="btn btn-primary" style="margin-top: 15px;">
                                <i class="fas fa-times"></i> Quitar Filtro
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($result_cuentas->num_rows > 0): ?>
            <div style="margin-top: 30px; text-align: center; color: var(--gray-color); font-size: 0.9rem;">
                <i class="fas fa-info-circle"></i> Mostrando <strong><?php echo $total_cuentas; ?></strong> cuenta(s) de cobro
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para ver fotos en grande -->
    <div id="modalFoto" class="modal">
        <span class="close" onclick="cerrarModal()">&times;</span>
        <span class="nav-arrow nav-prev" onclick="cambiarFoto(-1)">&#10094;</span>
        <img class="modal-content" id="imgModal">
        <span class="nav-arrow nav-next" onclick="cambiarFoto(1)">&#10095;</span>
        <div id="modalInfo" style="position: absolute; bottom: 20px; left: 0; width: 100%; text-align: center; color: white; font-size: 1rem;"></div>
    </div>

    <script>
        // Variables globales para la galería
        let fotosActuales = [];
        let indiceFoto = 0;

        // Abre la galería con un conjunto de fotos y una posición inicial
        function abrirGaleria(fotosString, indexInicial = 0) {
            fotosActuales = fotosString.split(',').filter(f => f.trim() !== '');
            if (!fotosActuales.length) return;
            indiceFoto = indexInicial;
            mostrarFotoActual();
            document.getElementById('modalFoto').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        // Muestra la foto actual en el modal
        function mostrarFotoActual() {
            const imgModal = document.getElementById('imgModal');
            const modalInfo = document.getElementById('modalInfo');
            imgModal.src = fotosActuales[indiceFoto];
            modalInfo.textContent = `Foto ${indiceFoto + 1} de ${fotosActuales.length}`;
        }

        // Cambia de foto (delta = +1 siguiente, -1 anterior)
        function cambiarFoto(delta) {
            if (!fotosActuales.length) return;
            indiceFoto = (indiceFoto + delta + fotosActuales.length) % fotosActuales.length;
            mostrarFotoActual();
        }

        // Cerrar el modal
        function cerrarModal() {
            document.getElementById('modalFoto').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Cerrar modal al hacer click fuera de la imagen
        window.onclick = function(event) {
            const modal = document.getElementById('modalFoto');
            if (event.target === modal) {
                cerrarModal();
            }
        }
        
        // Navegación con teclado
        document.addEventListener('keydown', function(event) {
            const modal = document.getElementById('modalFoto');
            if (modal.style.display === 'block') {
                if (event.key === 'Escape') {
                    cerrarModal();
                } else if (event.key === 'ArrowRight' || event.key === ' ') {
                    cambiarFoto(1);
                } else if (event.key === 'ArrowLeft') {
                    cambiarFoto(-1);
                }
            }
        });

        // Actualizar contador de archivos seleccionados
        function updateFileCounter(inputId, counterId) {
            const input = document.getElementById(inputId);
            const counter = document.getElementById(counterId);
            if (input.files.length > 0) {
                counter.textContent = input.files.length;
                counter.style.backgroundColor = 'var(--success-color)';
            } else {
                counter.textContent = '0';
                counter.style.backgroundColor = 'var(--primary-color)';
            }
        }

        // Limpiar formulario
        function clearForm() {
            if (confirm('¿Estás seguro de que quieres limpiar el formulario? Se perderán los datos no guardados.')) {
                document.getElementById('cuentaForm').reset();
                document.getElementById('foto-counter').textContent = '0';
                document.getElementById('comp-counter').textContent = '0';
            }
        }

        // Mostrar automáticamente el formulario si estamos editando
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_GET['editar'])): ?>
                document.querySelector('.form-section').style.display = 'block';
                document.getElementById('form-toggle').checked = true;
                
                // Desplazar suavemente hacia el formulario
                setTimeout(() => {
                    document.querySelector('.form-section').scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'start' 
                    });
                }, 300);
            <?php endif; ?>
            
            // Inicializar contadores de archivos
            updateFileCounter('fotos', 'foto-counter');
            updateFileCounter('comprobante', 'comp-counter');
        });

        // Validación de fechas
        document.getElementById('fecha_inicio').addEventListener('change', function() {
            const fechaInicio = new Date(this.value);
            const fechaFin = document.getElementById('fecha_fin');
            const fechaFinValue = new Date(fechaFin.value);
            
            if (fechaFin.value && fechaInicio > fechaFinValue) {
                alert('La fecha de inicio no puede ser posterior a la fecha final.');
                this.value = '';
            }
        });

        document.getElementById('fecha_fin').addEventListener('change', function() {
            const fechaFin = new Date(this.value);
            const fechaInicio = document.getElementById('fecha_inicio');
            const fechaInicioValue = new Date(fechaInicio.value);
            
            if (fechaInicio.value && fechaFin < fechaInicioValue) {
                alert('La fecha final no puede ser anterior a la fecha de inicio.');
                this.value = '';
            }
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>