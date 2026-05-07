<?php
// Activar logs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$log_file = 'log_conductores.txt';

function escribirLog($mensaje) {
    global $log_file;
    $fecha = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$fecha] $mensaje\n", FILE_APPEND);
}

escribirLog("=== INICIO ===");

// Conexión
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { 
    escribirLog("ERROR CONEXIÓN: " . $conn->connect_error);
    die("Error conexión"); 
}
$conn->set_charset('utf8mb4');
escribirLog("Conexión OK");

// Crear tabla si no existe
$conn->query("CREATE TABLE IF NOT EXISTS conductores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
escribirLog("Tabla base verificada");

// Verificar y agregar columna cedula si no existe
$columna = $conn->query("SHOW COLUMNS FROM conductores LIKE 'cedula'");
if($columna->num_rows == 0) {
    $conn->query("ALTER TABLE conductores ADD COLUMN cedula VARCHAR(20) DEFAULT '' AFTER nombre");
    escribirLog("Columna 'cedula' agregada");
}

// Verificar y agregar columna cuenta_banco si no existe
$columna = $conn->query("SHOW COLUMNS FROM conductores LIKE 'cuenta_banco'");
if($columna->num_rows == 0) {
    $conn->query("ALTER TABLE conductores ADD COLUMN cuenta_banco VARCHAR(50) DEFAULT '' AFTER cedula");
    escribirLog("Columna 'cuenta_banco' agregada");
}

// Insertar nombres desde viajes si está vacía
$check = $conn->query("SELECT COUNT(*) as total FROM conductores");
$total = $check->fetch_assoc()['total'];
escribirLog("Conductores: $total");

if($total == 0) {
    $conn->query("INSERT INTO conductores (nombre) SELECT DISTINCT nombre FROM viajes WHERE nombre IS NOT NULL AND nombre != ''");
    escribirLog("Insertados: " . $conn->affected_rows);
}

// Guardar
$mensaje = '';
if(isset($_POST['guardar'])) {
    escribirLog("=== GUARDAR ===");
    $id = intval($_POST['guardar']);
    $cedula = $conn->real_escape_string($_POST['cedula'][$_POST['guardar']] ?? '');
    $cuenta = $conn->real_escape_string($_POST['cuenta_banco'][$_POST['guardar']] ?? '');
    
    escribirLog("ID: $id | Cédula: $cedula | Cuenta: $cuenta");
    
    $sql = "UPDATE conductores SET cedula='$cedula', cuenta_banco='$cuenta' WHERE id=$id";
    escribirLog("SQL: $sql");
    
    if($conn->query($sql)) {
        $mensaje = "<p style='color:green;background:#d4edda;padding:10px;border-radius:5px;'>✅ Guardado correctamente</p>";
        escribirLog("✅ OK");
    } else {
        $mensaje = "<p style='color:red;background:#f8d7da;padding:10px;border-radius:5px;'>❌ Error: " . $conn->error . "</p>";
        escribirLog("❌ ERROR: " . $conn->error);
    }
}

// Filtro
$filtro = isset($_GET['filtro']) ? $conn->real_escape_string($_GET['filtro']) : '';
$sql = "SELECT * FROM conductores";
if($filtro != '') $sql .= " WHERE nombre LIKE '%$filtro%'";
$sql .= " ORDER BY nombre";
$result = $conn->query($sql);
$num = $result ? $result->num_rows : 0;
escribirLog("Resultados: $num");
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
    </style>
</head>
<body>

<div class="contenedor">
    <h2>🚛 Gestión de Conductores</h2>
    <?php echo $mensaje; ?>
    
    <form method="GET" class="filtro" style="margin-bottom:20px;">
        <input type="text" name="filtro" placeholder="🔍 Buscar conductor..." value="<?php echo htmlspecialchars($filtro); ?>">
        <button type="submit">Buscar</button>
        <a href="?"><button type="button" style="background:#666;">Ver todos</button></a>
    </form>
    
    <form method="POST">
    <table>
        <tr><th>Nombre</th><th>Cédula</th><th>Cuenta de Banco</th><th></th></tr>
        <?php while($c = $result->fetch_assoc()): ?>
        <tr>
            <td><strong><?php echo htmlspecialchars($c['nombre']); ?></strong></td>
            <td><input type="text" name="cedula[<?php echo $c['id']; ?>]" value="<?php echo htmlspecialchars($c['cedula']); ?>" placeholder="Cédula" style="width:140px;"></td>
            <td><input type="text" name="cuenta_banco[<?php echo $c['id']; ?>]" value="<?php echo htmlspecialchars($c['cuenta_banco']); ?>" placeholder="Cuenta bancaria" style="width:200px;"></td>
            <td><button type="submit" name="guardar" value="<?php echo $c['id']; ?>">💾 Guardar</button></td>
        </tr>
        <?php endwhile; ?>
    </table>
    </form>
</div>

</body>
</html>