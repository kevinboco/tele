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
$editar_id = null;
$datos_edicion = null;

// PROCESAR FORMULARIO - Guardar o Actualizar
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $titulo       = $conn->real_escape_string($_POST['titulo']);
    $empresa      = $conn->real_escape_string($_POST['empresa']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin    = $_POST['fecha_fin'];
    $editar_id    = isset($_POST['editar_id']) ? intval($_POST['editar_id']) : null;
    
    // Subir MÚLTIPLES fotos
    $fotos_paths = [];
    if (isset($_FILES['fotos']) && count($_FILES['fotos']['name']) > 0) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        foreach ($_FILES['fotos']['name'] as $key => $name) {
            if ($_FILES['fotos']['error'][$key] == 0) {
                $file_extension = pathinfo($name, PATHINFO_EXTENSION);
                $file_name = uniqid() . '_' . date('Ymd_His') . '_' . $key . '.' . $file_extension;
                $foto_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['fotos']['tmp_name'][$key], $foto_path)) {
                    $fotos_paths[] = $conn->real_escape_string($foto_path);
                }
            }
        }
    }
    
    // Convertir array de fotos a string separado por comas
    $fotos_string = implode(',', $fotos_paths);

    // Subir comprobante Bancolombia (UN SOLO ARCHIVO)
    $comprobante_path = '';
    if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] == 0) {
        $upload_dir_comp = 'uploads/comprobantes/';
        if (!is_dir($upload_dir_comp)) {
            mkdir($upload_dir_comp, 0777, true);
        }

        $name_comp        = $_FILES['comprobante']['name'];
        $file_extension_c = pathinfo($name_comp, PATHINFO_EXTENSION);
        $file_name_c      = 'comp_' . uniqid() . '_' . date('Ymd_His') . '.' . $file_extension_c;
        $ruta_comprobante = $upload_dir_comp . $file_name_c;

        if (move_uploaded_file($_FILES['comprobante']['tmp_name'], $ruta_comprobante)) {
            $comprobante_path = $conn->real_escape_string($ruta_comprobante);
        }
    }
    
    if ($editar_id) {
        // ACTUALIZAR registro existente
        // Obtener fotos y comprobante existentes
        $sql_existing = "SELECT foto_path, comprobante_path FROM cuentas_cobro WHERE id = $editar_id";
        $result = $conn->query($sql_existing);
        $existing_fotos = '';
        $existing_comprobante = '';
        if ($row = $result->fetch_assoc()) {
            $existing_fotos       = $row['foto_path'];
            $existing_comprobante = $row['comprobante_path'];
        }

        // FOTOS: si hay nuevas, las agregamos; si no, mantenemos las existentes
        if (!empty($fotos_string)) {
            $fotos_string = ($existing_fotos ? $existing_fotos . ',' : '') . $fotos_string;
        } else {
            $fotos_string = $existing_fotos;
        }

        // COMPROBANTE:
        // - Si se subió uno nuevo, reemplaza el anterior (y se podría borrar el archivo viejo si quieres)
        // - Si no se subió uno nuevo, se conserva el existente
        if (!empty($comprobante_path)) {
            // Si quieres borrar el anterior del servidor, descomenta esto:
            // if ($existing_comprobante && file_exists($existing_comprobante)) {
            //     unlink($existing_comprobante);
            // }
        } else {
            $comprobante_path = $existing_comprobante;
        }
        
        $sql = "UPDATE cuentas_cobro 
                SET titulo='$titulo', 
                    empresa='$empresa', 
                    fecha_inicio='$fecha_inicio', 
                    fecha_fin='$fecha_fin', 
                    foto_path='$fotos_string',
                    comprobante_path='$comprobante_path'
                WHERE id=$editar_id";
        $accion = "actualizada";
    } else {
        // INSERTAR nuevo registro
        $sql = "INSERT INTO cuentas_cobro (titulo, empresa, fecha_inicio, fecha_fin, foto_path, comprobante_path) 
                VALUES ('$titulo', '$empresa', '$fecha_inicio', '$fecha_fin', '$fotos_string', '$comprobante_path')";
        $accion = "guardada";
    }
    
    if ($conn->query($sql) === TRUE) {
        $mensaje = "✅ Cuenta de cobro $accion exitosamente";
        $editar_id = null;
    } else {
        $mensaje = "❌ Error: " . $conn->error;
    }
}

// ELIMINAR CUENTA
if (isset($_GET['eliminar'])) {
    $eliminar_id = intval($_GET['eliminar']);

    // Borrar también archivos (fotos y comprobante) si quieres
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
        // borrar comprobante
        if (!empty($rowf['comprobante_path']) && file_exists($rowf['comprobante_path'])) {
            @unlink($rowf['comprobante_path']);
        }
    }

    $sql = "DELETE FROM cuentas_cobro WHERE id = $eliminar_id";
    if ($conn->query($sql) === TRUE) {
        $mensaje = "✅ Cuenta eliminada exitosamente";
    } else {
        $mensaje = "❌ Error al eliminar: " . $conn->error;
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
        $conn->query($sql_update);
        
        $mensaje = "✅ Foto eliminada exitosamente";
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
        input[type="text"], input[type="date"], select, input[list] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px; }
        button:hover { background: #0056b3; }
        .btn-cancelar { background: #6c757d; }
        .btn-cancelar:hover { background: #545b62; }
        .btn-editar { background: #28a745; }
        .btn-editar:hover { background: #1e7e34; }
        .btn-eliminar { background: #dc3545; }
        .btn-eliminar:hover { background: #c82333; }
        .btn-ver { background: #17a2b8; }
        .btn-ver:hover { background: #138496; }
        .filtros { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .grid-cuentas { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .cuenta-card { border: 1px solid #ddd; border-radius: 8px; padding: 15px; background: white; }
        .cuenta-fotos { display: flex; gap: 5px; margin-bottom: 10px; flex-wrap: wrap; }
        .foto-miniatura { width: 80px; height: 80px; object-fit: cover; border-radius: 5px; cursor: pointer; border: 2px solid transparent; }
        .foto-miniatura:hover { border-color: #007bff; }
        .cuenta-info { margin-top: 10px; }
        .acciones { margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap; }
        .empresa-badge { background: #007bff; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; }
        
        /* Modal para ver foto en grande */
        .modal { 
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            background-color: rgba(0,0,0,0.9); 
        }
        .modal-content { 
            margin: auto; 
            display: block; 
            width: 90%; 
            max-width: 90%; 
            max-height: 90vh;
            object-fit: contain;
            margin-top: 40px; 
        }
        .close { 
            position: absolute; 
            top: 15px; 
            right: 35px; 
            color: #f1f1f1; 
            font-size: 40px; 
            font-weight: bold; 
            cursor: pointer; 
        }
        .close:hover { color: #bbb; }

        .nav-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            font-size: 50px;
            font-weight: bold;
            color: #f1f1f1;
            cursor: pointer;
            user-select: none;
            padding: 10px;
        }
        .nav-arrow:hover { color: #bbb; }
        .nav-prev { left: 20px; }
        .nav-next { right: 20px; }
        
        /* Estilos para múltiples archivos */
        .file-input-container { position: relative; }
        .file-input-label { display: inline-block; padding: 8px 15px; background: #007bff; color: white; border-radius: 4px; cursor: pointer; }
        .file-input-label:hover { background: #0056b3; }
        .file-list { margin-top: 10px; }
        .file-item { background: #f8f9fa; padding: 5px 10px; margin: 5px 0; border-radius: 4px; border-left: 4px solid #007bff; }
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
                           value="<?php echo $datos_edicion ? htmlspecialchars($datos_edicion['titulo']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="empresa">Empresa:</label>
                    <!-- Input con datalist para buscar empresas existentes o escribir una nueva -->
                    <input list="lista_empresas" id="empresa" name="empresa" required
                           value="<?php echo $datos_edicion ? htmlspecialchars($datos_edicion['empresa']) : ''; ?>">
                    <datalist id="lista_empresas">
                        <?php foreach ($empresas as $emp): ?>
                            <option value="<?php echo htmlspecialchars($emp); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <small>Selecciona una empresa existente o escribe una nueva.</small>
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
                    <label for="fotos">Fotos (Múltiples):</label>
                    <input type="file" id="fotos" name="fotos[]" multiple accept="image/*">
                    <small>Selecciona una o más fotos (Ctrl+click para seleccionar múltiples)</small>
                    
                    <?php if ($datos_edicion && $datos_edicion['foto_path']): ?>
                        <div style="margin-top: 10px;">
                            <strong>Fotos actuales:</strong>
                            <div class="cuenta-fotos">
                                <?php 
                                $fotos = explode(',', $datos_edicion['foto_path']);
                                foreach ($fotos as $idx => $foto): 
                                    if (!empty($foto)):
                                ?>
                                    <div style="text-align: center; margin: 5px;">
                                        <img src="<?php echo $foto; ?>" alt="Foto" class="foto-miniatura" 
                                             onclick="abrirGaleria('<?php echo htmlspecialchars($datos_edicion['foto_path'], ENT_QUOTES); ?>', <?php echo $idx; ?>)">
                                        <br>
                                        <a href="?eliminar_foto=<?php echo urlencode($foto); ?>&cuenta_id=<?php echo $editar_id; ?>" 
                                           onclick="return confirm('¿Eliminar esta foto?')" 
                                           style="color: red; font-size: 12px;">❌ Eliminar</a>
                                    </div>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="comprobante">Comprobante Bancolombia:</label>
                    <input type="file" id="comprobante" name="comprobante" accept="image/*,application/pdf">
                    <small>Puedes subir imagen o PDF del comprobante.</small>
                    <?php if ($datos_edicion && !empty($datos_edicion['comprobante_path'])): ?>
                        <div style="margin-top: 10px;">
                            <a href="<?php echo htmlspecialchars($datos_edicion['comprobante_path']); ?>" target="_blank">
                                🔎 Ver comprobante actual
                            </a>
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
                                <option value="<?php echo htmlspecialchars($emp); ?>" 
                                    <?php echo $filtro_empresa == $emp ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp); ?>
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
                            <!-- Mostrar múltiples fotos -->
                            <?php if ($cuenta['foto_path']): ?>
                                <div class="cuenta-fotos">
                                    <?php 
                                    $fotos = explode(',', $cuenta['foto_path']);
                                    foreach ($fotos as $index => $foto): 
                                        if (!empty($foto)):
                                    ?>
                                        <img src="<?php echo $foto; ?>" alt="Foto" class="foto-miniatura" 
                                             onclick="abrirGaleria('<?php echo htmlspecialchars($cuenta['foto_path'], ENT_QUOTES); ?>', <?php echo $index; ?>)">
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            <?php else: ?>
                                <div style="height: 100px; background: #eee; display: flex; align-items: center; justify-content: center; color: #666; border-radius: 5px;">
                                    📷 Sin imágenes
                                </div>
                            <?php endif; ?>
                            
                            <div class="cuenta-info">
                                <h3><?php echo htmlspecialchars($cuenta['titulo']); ?></h3>
                                <p><strong>Empresa:</strong> <span class="empresa-badge"><?php echo htmlspecialchars($cuenta['empresa']); ?></span></p>
                                <p><strong>Fecha Inicio:</strong> <?php echo date('d/m/Y', strtotime($cuenta['fecha_inicio'])); ?></p>
                                <p><strong>Fecha Fin:</strong> <?php echo date('d/m/Y', strtotime($cuenta['fecha_fin'])); ?></p>
                                <p><small>Creado: <?php echo date('d/m/Y H:i', strtotime($cuenta['created_at'])); ?></small></p>

                                <?php if (!empty($cuenta['comprobante_path'])): ?>
                                    <p>
                                        <strong>Comprobante Bancolombia:</strong><br>
                                        <a href="<?php echo htmlspecialchars($cuenta['comprobante_path']); ?>" target="_blank">
                                            🔗 Ver comprobante Bancolombia
                                        </a>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="acciones">
                                    <a href="?editar=<?php echo $cuenta['id']; ?>">
                                        <button class="btn-editar" type="button">✏️ Editar</button>
                                    </a>
                                    <a href="?eliminar=<?php echo $cuenta['id']; ?>" 
                                       onclick="return confirm('¿Estás seguro de eliminar esta cuenta?')">
                                        <button class="btn-eliminar" type="button">🗑️ Eliminar</button>
                                    </a>
                                    <?php if ($cuenta['foto_path']): ?>
                                        <button class="btn-ver" type="button"
                                            onclick="abrirGaleria('<?php echo htmlspecialchars($cuenta['foto_path'], ENT_QUOTES); ?>', 0)">
                                            👁️ Ver Fotos
                                        </button>
                                    <?php endif; ?>
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

    <!-- Modal para ver fotos en grande -->
    <div id="modalFoto" class="modal">
        <span class="close" onclick="cerrarModal()">&times;</span>
        <span class="nav-arrow nav-prev" onclick="cambiarFoto(-1)">&#10094;</span>
        <img class="modal-content" id="imgModal">
        <span class="nav-arrow nav-next" onclick="cambiarFoto(1)">&#10095;</span>
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
        }

        // Muestra la foto actual en el modal
        function mostrarFotoActual() {
            const imgModal = document.getElementById('imgModal');
            imgModal.src = fotosActuales[indiceFoto];
            document.getElementById('modalFoto').style.display = 'block';
        }

        // Cambia de foto (delta = +1 siguiente, -1 anterior)
        function cambiarFoto(delta) {
            if (!fotosActuales.length) return;
            indiceFoto = (indiceFoto + delta + fotosActuales.length) % fotosActuales.length;
            mostrarFotoActual();
        }

        // Compatibilidad: llamada antigua verFoto(path)
        function verFoto(fotoPath) {
            abrirGaleria(fotoPath, 0);
        }

        // Cerrar el modal
        function cerrarModal() {
            document.getElementById('modalFoto').style.display = 'none';
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
                } else if (event.key === 'ArrowRight') {
                    cambiarFoto(1);
                } else if (event.key === 'ArrowLeft') {
                    cambiarFoto(-1);
                }
            }
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>
