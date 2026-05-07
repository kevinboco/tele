<?php
// ============================================
// CONEXIÓN A LA BASE DE DATOS
// ============================================
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// ============================================
// CREAR TABLA conductores SI NO EXISTE
// ============================================
$sql_crear_tabla = "CREATE TABLE IF NOT EXISTS `conductores` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(100) DEFAULT NULL,
    `cedula` VARCHAR(20) DEFAULT NULL,
    `cuenta_banco` VARCHAR(50) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

if (!$conn->query($sql_crear_tabla)) {
    die("Error al crear tabla conductores: " . $conn->error);
}

// ============================================
// ALIMENTAR TABLA conductores CON NOMBRES ÚNICOS DE viajes
// ============================================
$sql_alimentar = "INSERT IGNORE INTO conductores (nombre)
                  SELECT DISTINCT nombre 
                  FROM viajes 
                  WHERE nombre IS NOT NULL AND nombre != ''";

$conn->query($sql_alimentar);

// ============================================
// PROCESAR FORMULARIOS
// ============================================
$mensaje = '';
$tipo_mensaje = '';

// Guardar/Actualizar conductor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar') {
    $id_conductor = intval($_POST['id_conductor'] ?? 0);
    $cedula = $conn->real_escape_string($_POST['cedula'] ?? '');
    $cuenta_banco = $conn->real_escape_string($_POST['cuenta_banco'] ?? '');
    
    if ($id_conductor > 0) {
        $sql_update = "UPDATE conductores 
                       SET cedula = '$cedula', cuenta_banco = '$cuenta_banco' 
                       WHERE id = $id_conductor";
        
        if ($conn->query($sql_update)) {
            $mensaje = 'Conductor actualizado exitosamente.';
            $tipo_mensaje = 'success';
        } else {
            $mensaje = 'Error al actualizar: ' . $conn->error;
            $tipo_mensaje = 'error';
        }
    }
}

// ============================================
// OBTENER CONDUCTOR PARA EDITAR
// ============================================
$conductor_editar = null;
if (isset($_GET['editar']) && intval($_GET['editar']) > 0) {
    $id_editar = intval($_GET['editar']);
    $result_editar = $conn->query("SELECT * FROM conductores WHERE id = $id_editar");
    if ($result_editar && $result_editar->num_rows > 0) {
        $conductor_editar = $result_editar->fetch_assoc();
    }
}

// ============================================
// BÚSQUEDA DE CONDUCTORES
// ============================================
$busqueda = $_GET['busqueda'] ?? '';
$conductores = [];

if (!empty($busqueda)) {
    $busqueda_escapada = $conn->real_escape_string($busqueda);
    $sql_buscar = "SELECT * FROM conductores 
                   WHERE nombre LIKE '%$busqueda_escapada%' 
                   OR cedula LIKE '%$busqueda_escapada%' 
                   OR cuenta_banco LIKE '%$busqueda_escapada%'
                   ORDER BY nombre ASC";
} else {
    $sql_buscar = "SELECT * FROM conductores ORDER BY nombre ASC";
}

$result_conductores = $conn->query($sql_buscar);
if ($result_conductores) {
    while ($fila = $result_conductores->fetch_assoc()) {
        $conductores[] = $fila;
    }
}

// ============================================
// API JSON PARA SUGERENCIAS (AJAX)
// ============================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'sugerencias') {
    header('Content-Type: application/json');
    $termino = $conn->real_escape_string($_GET['termino'] ?? '');
    
    $sql_sug = "SELECT id, nombre, cedula, cuenta_banco 
                FROM conductores 
                WHERE nombre LIKE '%$termino%' 
                ORDER BY nombre ASC 
                LIMIT 10";
    
    $result_sug = $conn->query($sql_sug);
    $sugerencias = [];
    
    if ($result_sug) {
        while ($fila = $result_sug->fetch_assoc()) {
            $sugerencias[] = $fila;
        }
    }
    
    echo json_encode($sugerencias);
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Conductores</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .header h1 {
            color: #333;
            font-size: 2.5em;
            margin-bottom: 5px;
        }
        
        .header p {
            color: #666;
            font-size: 1.1em;
        }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }
        
        /* Mensajes */
        .mensaje {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .mensaje.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .mensaje.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        /* Buscador con sugerencias */
        .search-container {
            position: relative;
            margin-bottom: 25px;
        }
        
        .search-container input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .search-container input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .sugerencias-lista {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #667eea;
            border-top: none;
            border-radius: 0 0 10px 10px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
        }
        
        .sugerencias-lista.active {
            display: block;
        }
        
        .sugerencia-item {
            padding: 12px 20px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s ease;
        }
        
        .sugerencia-item:hover {
            background: #f8f9ff;
        }
        
        .sugerencia-item:last-child {
            border-bottom: none;
        }
        
        .sugerencia-nombre {
            font-weight: 600;
            color: #333;
        }
        
        .sugerencia-info {
            font-size: 0.9em;
            color: #888;
            margin-top: 3px;
        }
        
        /* Formulario de edición */
        .form-editar {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: #f8f9ff;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }
        
        .form-editar.active {
            display: block;
            animation: slideDown 0.3s ease;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group input:disabled {
            background: #f5f5f5;
            color: #888;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-guardar {
            background: #667eea;
            color: white;
        }
        
        .btn-guardar:hover {
            background: #5a67d8;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-cancelar {
            background: #e0e0e0;
            color: #333;
            margin-left: 10px;
        }
        
        .btn-cancelar:hover {
            background: #d0d0d0;
        }
        
        /* Tabla de conductores */
        .tabla-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        table tbody tr {
            transition: background 0.2s ease;
        }
        
        table tbody tr:hover {
            background: #f8f9ff;
        }
        
        .btn-editar {
            background: #667eea;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .btn-editar:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }
        
        .sin-datos {
            color: #999;
            font-style: italic;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }
        
        .badge-completo {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-incompleto {
            background: #fff3cd;
            color: #856404;
        }
        
        /* Estadísticas */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-numero {
            font-size: 2.5em;
            font-weight: 700;
        }
        
        .stat-etiqueta {
            font-size: 0.9em;
            opacity: 0.9;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🚛 Gestión de Conductores</h1>
            <p>Asigna cédula y cuenta bancaria a los conductores para facilitar los pagos</p>
        </div>

        <!-- Estadísticas -->
        <?php
        $total_conductores = count($conductores);
        $total_completos = 0;
        foreach ($conductores as $c) {
            if (!empty($c['cedula']) && !empty($c['cuenta_banco'])) {
                $total_completos++;
            }
        }
        ?>
        <div class="stats">
            <div class="stat-card">
                <div class="stat-numero"><?php echo $total_conductores; ?></div>
                <div class="stat-etiqueta">Total Conductores</div>
            </div>
            <div class="stat-card">
                <div class="stat-numero"><?php echo $total_completos; ?></div>
                <div class="stat-etiqueta">Con Datos Completos</div>
            </div>
            <div class="stat-card">
                <div class="stat-numero"><?php echo $total_conductores - $total_completos; ?></div>
                <div class="stat-etiqueta">Pendientes por Completar</div>
            </div>
        </div>

        <!-- Mensajes -->
        <?php if (!empty($mensaje)): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Buscador -->
        <div class="card">
            <h2 style="margin-bottom: 20px; color: #333;">🔍 Buscar Conductor</h2>
            <div class="search-container">
                <input 
                    type="text" 
                    id="busqueda" 
                    placeholder="Escribe el nombre del conductor..." 
                    value="<?php echo htmlspecialchars($busqueda); ?>"
                    autocomplete="off"
                >
                <div class="sugerencias-lista" id="sugerencias"></div>
            </div>

            <!-- Formulario para editar -->
            <div class="form-editar" id="formEditar">
                <h3 style="margin-bottom: 15px; color: #667eea;">✏️ Editar Información del Conductor</h3>
                <form method="POST" id="formConductor">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id_conductor" id="id_conductor" value="">
                    
                    <div class="form-group">
                        <label>Nombre del Conductor</label>
                        <input type="text" id="nombre_conductor" disabled placeholder="Selecciona un conductor...">
                    </div>
                    
                    <div class="form-group">
                        <label>Cédula</label>
                        <input type="text" name="cedula" id="cedula" placeholder="Ingresa la cédula...">
                    </div>
                    
                    <div class="form-group">
                        <label>Cuenta de Banco</label>
                        <input type="text" name="cuenta_banco" id="cuenta_banco" placeholder="Ingresa la cuenta bancaria...">
                    </div>
                    
                    <button type="submit" class="btn btn-guardar">💾 Guardar Datos</button>
                    <button type="button" class="btn btn-cancelar" onclick="cerrarFormulario()">Cancelar</button>
                </form>
            </div>
        </div>

        <!-- Tabla de conductores -->
        <div class="card">
            <h2 style="margin-bottom: 20px; color: #333;">📋 Lista de Conductores</h2>
            <div class="tabla-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Cédula</th>
                            <th>Cuenta de Banco</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($conductores)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 30px; color: #999;">
                                    No se encontraron conductores
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($conductores as $conductor): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($conductor['nombre']); ?></strong></td>
                                    <td>
                                        <?php if (!empty($conductor['cedula'])): ?>
                                            <?php echo htmlspecialchars($conductor['cedula']); ?>
                                        <?php else: ?>
                                            <span class="sin-datos">Sin cédula</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($conductor['cuenta_banco'])): ?>
                                            <?php echo htmlspecialchars($conductor['cuenta_banco']); ?>
                                        <?php else: ?>
                                            <span class="sin-datos">Sin cuenta</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($conductor['cedula']) && !empty($conductor['cuenta_banco'])): ?>
                                            <span class="badge badge-completo">✅ Completo</span>
                                        <?php else: ?>
                                            <span class="badge badge-incompleto">⚠️ Pendiente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button 
                                            class="btn-editar" 
                                            onclick="editarConductor(<?php echo $conductor['id']; ?>, '<?php echo htmlspecialchars($conductor['nombre'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($conductor['cedula'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($conductor['cuenta_banco'] ?? '', ENT_QUOTES); ?>')"
                                        >
                                            ✏️ Editar
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Variables
        const inputBusqueda = document.getElementById('busqueda');
        const sugerenciasDiv = document.getElementById('sugerencias');
        const formEditar = document.getElementById('formEditar');
        let timeoutId;

        // Función para buscar sugerencias
        inputBusqueda.addEventListener('input', function() {
            clearTimeout(timeoutId);
            const termino = this.value.trim();
            
            if (termino.length < 1) {
                sugerenciasDiv.classList.remove('active');
                return;
            }
            
            timeoutId = setTimeout(() => {
                fetch(`?ajax=sugerencias&termino=${encodeURIComponent(termino)}`)
                    .then(response => response.json())
                    .then(data => {
                        mostrarSugerencias(data);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            }, 300);
        });

        // Mostrar sugerencias
        function mostrarSugerencias(sugerencias) {
            sugerenciasDiv.innerHTML = '';
            
            if (sugerencias.length === 0) {
                sugerenciasDiv.innerHTML = '<div class="sugerencia-item" style="color: #999;">No se encontraron conductores</div>';
            } else {
                sugerencias.forEach(conductor => {
                    const div = document.createElement('div');
                    div.className = 'sugerencia-item';
                    div.innerHTML = `
                        <div class="sugerencia-nombre">${conductor.nombre}</div>
                        <div class="sugerencia-info">
                            ${conductor.cedula ? 'Cédula: ' + conductor.cedula : 'Sin cédula'} | 
                            ${conductor.cuenta_banco ? 'Cuenta: ' + conductor.cuenta_banco : 'Sin cuenta'}
                        </div>
                    `;
                    div.onclick = () => editarConductor(
                        conductor.id, 
                        conductor.nombre, 
                        conductor.cedula || '', 
                        conductor.cuenta_banco || ''
                    );
                    sugerenciasDiv.appendChild(div);
                });
            }
            
            sugerenciasDiv.classList.add('active');
        }

        // Editar conductor
        function editarConductor(id, nombre, cedula, cuentaBanco) {
            document.getElementById('id_conductor').value = id;
            document.getElementById('nombre_conductor').value = nombre;
            document.getElementById('cedula').value = cedula;
            document.getElementById('cuenta_banco').value = cuentaBanco;
            formEditar.classList.add('active');
            
            // Scroll al formulario
            formEditar.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Cerrar sugerencias
            sugerenciasDiv.classList.remove('active');
            
            // Poner el nombre en el buscador
            inputBusqueda.value = nombre;
        }

        // Cerrar formulario
        function cerrarFormulario() {
            formEditar.classList.remove('active');
            document.getElementById('formConductor').reset();
            document.getElementById('nombre_conductor').value = '';
        }

        // Cerrar sugerencias al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!sugerenciasDiv.contains(e.target) && e.target !== inputBusqueda) {
                sugerenciasDiv.classList.remove('active');
            }
        });

        // Navegación con teclado en sugerencias
        let selectedIndex = -1;
        
        inputBusqueda.addEventListener('keydown', function(e) {
            const items = sugerenciasDiv.querySelectorAll('.sugerencia-item');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                updateSelection(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, 0);
                updateSelection(items);
            } else if (e.key === 'Enter' && selectedIndex >= 0) {
                e.preventDefault();
                items[selectedIndex].click();
            } else if (e.key === 'Escape') {
                sugerenciasDiv.classList.remove('active');
                selectedIndex = -1;
            }
        });

        function updateSelection(items) {
            items.forEach((item, index) => {
                if (index === selectedIndex) {
                    item.style.background = '#f0f0ff';
                } else {
                    item.style.background = '';
                }
            });
        }
    </script>
</body>
</html>