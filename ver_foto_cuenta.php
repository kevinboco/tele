<?php
// Conexión a la base de datos
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Variables
$mensaje = "";
$editar_id = null;
$datos_edicion = null;

// PROCESAR FORMULARIO - Guardar o Actualizar
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $titulo = $conn->real_escape_string($_POST['titulo']);
    $empresa = $conn->real_escape_string($_POST['empresa']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $editar_id = isset($_POST['editar_id']) ? intval($_POST['editar_id']) : null;
    
    // Subir foto
    $foto_path = '';
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '_' . date('Ymd') . '.' . $file_extension;
        $foto_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $foto_path)) {
            $foto_path = $conn->real_escape_string($foto_path);
        } else {
            $foto_path = '';
        }
    }
    
    if ($editar_id) {
        // ACTUALIZAR registro existente
        if ($foto_path) {
            $sql = "UPDATE cuentas_cobro SET titulo='$titulo', empresa='$empresa', 
                    fecha_inicio='$fecha_inicio', fecha_fin='$fecha_fin', foto_path='$foto_path' 
                    WHERE id=$editar_id";
        } else {
            $sql = "UPDATE cuentas_cobro SET titulo='$titulo', empresa='$empresa', 
                    fecha_inicio='$fecha_inicio', fecha_fin='$fecha_fin' 
                    WHERE id=$editar_id";
        }
        $accion = "actualizada";
    } else {
        // INSERTAR nuevo registro
        $sql = "INSERT INTO cuentas_cobro (titulo, empresa, fecha_inicio, fecha_fin, foto_path) 
                VALUES ('$titulo', '$empresa', '$fecha_inicio', '$fecha_fin', '$foto_path')";
        $accion = "guardada";
    }
    
    if ($conn->query($sql) === TRUE) {
        $mensaje = "✅ Cuenta de cobro $accion exitosamente";
        $editar_id = null; // Limpiar edición
    } else {
        $mensaje = "❌ Error: " . $conn->error;
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
    $editar_id = null;
    $datos_edicion = null;
}

// OBTENER EMPRESAS PARA FILTRO
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
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 20px; text-align: center; }
        .mensaje { padding: 10px; margin: 10px 0; border-radius: 5px; text-align: center; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .form-section, .list-section { margin: 30px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        input[type="text"], input[type="date"], select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px; }
        button:hover { background: #0056b3; }
        .btn-cancelar { background: #6c757d; }
        .btn-cancelar:hover { background: #545b62; }
        .btn-editar { background: #28a745; }
        .btn-editar:hover { background: #1e7e34; }
        .filtros { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .grid-cuentas { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .cuenta-card { border: 1px solid #ddd; border-radius: 8px; padding: 15px; background: white; }
        .cuenta-card img { max-width: 100%; height: 200px; object-fit: cover; border-radius: 5px; }
        .cuenta-info { margin-top: 10px; }
        .acciones { margin-top: 10px; display: flex; gap: 10px; }
        .empresa-badge { background: #007bff; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Sistema de Cuentas de Cobro</h1>
        
        <?php if ($mensaje): ?>
            <div class="mensaje success"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <!-- SECCIÓN DEL FORMULARIO -->
        <div class="form-section">
            <h2><?php echo $editar_id ? '✏️ Editar Cuenta' : '➕ Nueva Cuenta de Cobro'; ?></h2>
            <form method="POST" enctype="multipart/form-data">
                <?php if ($editar_id): ?>
                    <input type="hidden" name="editar_id" value="<?php echo $editar_id; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="titulo">Título Cuenta de Cobro:</label>
                    <input type="text" id="titulo" name="titulo" required 
                           value="<?php echo $datos_edicion ? $datos_edicion['titulo'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="empresa">Empresa:</label>
                    <input type="text" id="empresa" name="empresa" required 
                           value="<?php echo $datos_edicion ? $datos_edicion['empresa'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="fecha_inicio">Fecha Inicio:</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio" required 
                           value="<?php echo $datos_edicion ? $datos_edicion['fecha_inicio'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="fecha_fin">Fecha Final:</label>
                    <input type="date" id="fecha_fin" name="fecha_fin" required 
                           value="<?php echo $datos_edicion ? $datos_edicion['fecha_fin'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="foto">Foto:</label>
                    <input type="file" id="foto" name="foto" accept="image/*">
                    <?php if ($datos_edicion && $datos_edicion['foto_path']): ?>
                        <div style="margin-top: 10px;">
                            <img src="<?php echo $datos_edicion['foto_path']; ?>" alt="Foto actual" style="max-width: 200px;">
                        </div>
                    <?php endif; ?>
                </div>
                
                <button type="submit"><?php echo $editar_id ? '💾 Actualizar' : '💾 Guardar Cuenta'; ?></button>
                
                <?php if ($editar_id): ?>
                    <a href="?cancelar=1" class="btn-cancelar" style="text-decoration: none; display: inline-block;">
                        <button type="button">❌ Cancelar</button>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- SECCIÓN DE FILTROS Y LISTA -->
        <div class="list-section">
            <h2>👀 Ver Cuentas de Cobro</h2>
            
            <div class="filtros">
                <form method="GET">
                    <div class="form-group">
                        <label for="filtro_empresa">Filtrar por Empresa:</label>
                        <select id="filtro_empresa" name="empresa" onchange="this.form.submit()">
                            <option value="">Todas las empresas</option>
                            <?php foreach ($empresas as $emp): ?>
                                <option value="<?php echo $emp; ?>" 
                                    <?php echo $filtro_empresa == $emp ? 'selected' : ''; ?>>
                                    <?php echo $emp; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <div class="grid-cuentas">
                <?php if ($result_cuentas->num_rows > 0): ?>
                    <?php while ($cuenta = $result_cuentas->fetch_assoc()): ?>
                        <div class="cuenta-card">
                            <?php if ($cuenta['foto_path']): ?>
                                <img src="<?php echo $cuenta['foto_path']; ?>" alt="Foto cuenta de cobro">
                            <?php else: ?>
                                <div style="height: 200px; background: #eee; display: flex; align-items: center; justify-content: center; color: #666;">
                                    📷 Sin imagen
                                </div>
                            <?php endif; ?>
                            
                            <div class="cuenta-info">
                                <h3><?php echo htmlspecialchars($cuenta['titulo']); ?></h3>
                                <p><strong>Empresa:</strong> <span class="empresa-badge"><?php echo htmlspecialchars($cuenta['empresa']); ?></span></p>
                                <p><strong>Fecha Inicio:</strong> <?php echo date('d/m/Y', strtotime($cuenta['fecha_inicio'])); ?></p>
                                <p><strong>Fecha Fin:</strong> <?php echo date('d/m/Y', strtotime($cuenta['fecha_fin'])); ?></p>
                                <p><small>Creado: <?php echo date('d/m/Y H:i', strtotime($cuenta['created_at'])); ?></small></p>
                                
                                <div class="acciones">
                                    <a href="?editar=<?php echo $cuenta['id']; ?>">
                                        <button class="btn-editar">✏️ Editar</button>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #666;">
                        <?php echo $filtro_empresa ? 'No hay cuentas para esta empresa' : 'No hay cuentas de cobro registradas'; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

<?php $conn->close(); ?>