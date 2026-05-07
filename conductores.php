<?php
// Activar logs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Archivo de log
$log_file = 'log_conductores.txt';

function escribirLog($mensaje) {
    global $log_file;
    $fecha = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$fecha] $mensaje\n", FILE_APPEND);
}

escribirLog("=== INICIO DE EJECUCIÓN ===");

// Conexión
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { 
    escribirLog("ERROR CONEXIÓN: " . $conn->connect_error);
    die("Error conexión: " . $conn->connect_error); 
}
$conn->set_charset('utf8mb4');
escribirLog("Conexión exitosa");

// Crear tabla conductores
$sql_crear = "CREATE TABLE IF NOT EXISTS conductores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100),
    cedula VARCHAR(20) DEFAULT '',
    cuenta_banco VARCHAR(50) DEFAULT ''
)";

if($conn->query($sql_crear)) {
    escribirLog("Tabla 'conductores' verificada/creada");
} else {
    escribirLog("ERROR al crear tabla: " . $conn->error);
}

// Insertar nombres desde viajes si está vacía
$check = $conn->query("SELECT COUNT(*) as total FROM conductores");
$total = $check->fetch_assoc()['total'];
escribirLog("Conductores existentes: $total");

if($total == 0) {
    $sql_insert = "INSERT INTO conductores (nombre) SELECT DISTINCT nombre FROM viajes WHERE nombre IS NOT NULL AND nombre != ''";
    if($conn->query($sql_insert)) {
        $insertados = $conn->affected_rows;
        escribirLog("Insertados $insertados conductores desde viajes");
    } else {
        escribirLog("ERROR al insertar conductores: " . $conn->error);
    }
}

// Guardar
$mensaje = '';
if(isset($_POST['guardar'])) {
    escribirLog("=== INTENTO DE GUARDAR ===");
    
    $id_guardar = $_POST['guardar'];
    escribirLog("ID recibido: " . $id_guardar);
    escribirLog("POST completo: " . print_r($_POST, true));
    
    $id = intval($id_guardar);
    
    if(isset($_POST['cedula'][$id_guardar])) {
        $cedula = $conn->real_escape_string($_POST['cedula'][$id_guardar]);
        escribirLog("Cédula: '$cedula'");
    } else {
        $cedula = '';
        escribirLog("ERROR: No se recibió cédula para ID $id_guardar");
    }
    
    if(isset($_POST['cuenta_banco'][$id_guardar])) {
        $cuenta = $conn->real_escape_string($_POST['cuenta_banco'][$id_guardar]);
        escribirLog("Cuenta: '$cuenta'");
    } else {
        $cuenta = '';
        escribirLog("ERROR: No se recibió cuenta para ID $id_guardar");
    }
    
    $sql_update = "UPDATE conductores SET cedula='$cedula', cuenta_banco='$cuenta' WHERE id=$id";
    escribirLog("SQL UPDATE: $sql_update");
    
    if($conn->query($sql_update)) {
        escribirLog("✅ UPDATE exitoso. Filas afectadas: " . $conn->affected_rows);
        $mensaje = "<p style='color:green;background:#d4edda;padding:10px;border-radius:5px;'>✅ Guardado correctamente (ID: $id)</p>";
    } else {
        escribirLog("❌ ERROR UPDATE: " . $conn->error);
        $mensaje = "<p style='color:red;background:#f8d7da;padding:10px;border-radius:5px;'>❌ Error SQL: " . $conn->error . "</p>";
    }
}

// Filtro
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : '';
escribirLog("Filtro: '$filtro'");

$sql = "SELECT * FROM conductores";
if($filtro != '') {
    $filtro_esc = $conn->real_escape_string($filtro);
    $sql .= " WHERE nombre LIKE '%$filtro_esc%'";
}
$sql .= " ORDER BY nombre";
escribirLog("SQL SELECT: $sql");

$result = $conn->query($sql);
if(!$result) {
    escribirLog("ERROR SELECT: " . $conn->error);
}
$num_resultados = $result ? $result->num_rows : 0;
escribirLog("Resultados encontrados: $num_resultados");

escribirLog("=== FIN DE EJECUCIÓN ===\n");
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Conductores</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .contenedor { max-width: 1000px; margin: auto; background: white; padding: 25px; border-radius: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: #333; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        input[type=text] { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 8px 18px; background: #0066cc; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0052a3; }
        .filtro input { width: 250px; padding: 10px; }
        .sin-dato { color: #999; font-style: italic; }
        .debug { background: #fff3cd; padding: 10px; margin-top: 20px; border-radius: 5px; font-size: 12px; }
    </style>
</head>
<body>

<div class="contenedor">
    <h2>🚛 Gestión de Conductores</h2>
    
    <?php echo $mensaje; ?>
    
    <!-- Buscador -->
    <form method="GET" class="filtro" style="margin-bottom:20px;">
        <input type="text" name="filtro" placeholder="🔍 Buscar conductor..." value="<?php echo htmlspecialchars($filtro); ?>">
        <button type="submit">Buscar</button>
        <a href="?"><button type="button" style="background:#666;">Ver todos</button></a>
    </form>
    
    <!-- Tabla -->
    <form method="POST" id="formPrincipal">
    <table>
        <tr>
            <th>Nombre</th>
            <th>Cédula</th>
            <th>Cuenta de Banco</th>
            <th></th>
        </tr>
        <?php 
        if($result && $result->num_rows > 0) {
            while($c = $result->fetch_assoc()): 
        ?>
        <tr>
            <td><strong><?php echo htmlspecialchars($c['nombre']); ?></strong></td>
            <td>
                <input type="text" name="cedula[<?php echo $c['id']; ?>]" 
                       value="<?php echo htmlspecialchars($c['cedula']); ?>" 
                       placeholder="Ingresar cédula" style="width:140px;">
            </td>
            <td>
                <input type="text" name="cuenta_banco[<?php echo $c['id']; ?>]" 
                       value="<?php echo htmlspecialchars($c['cuenta_banco']); ?>" 
                       placeholder="Ingresar cuenta" style="width:200px;">
            </td>
            <td>
                <button type="submit" name="guardar" value="<?php echo $c['id']; ?>">💾 Guardar</button>
            </td>
        </tr>
        <?php 
            endwhile;
        } else {
            echo "<tr><td colspan='4' style='text-align:center;padding:20px;'>No se encontraron conductores</td></tr>";
        }
        ?>
    </table>
    </form>
    
    <!-- DEBUG -->
    <div class="debug">
        <strong>📝 Log de ejecución:</strong><br>
        <small>Revisa el archivo <code>log_conductores.txt</code> en el servidor para ver los detalles.</small><br>
        <small>Total conductores: <?php echo $num_resultados; ?> | Filtro: "<?php echo htmlspecialchars($filtro); ?>"</small>
    </div>
</div>

</body>
</html>