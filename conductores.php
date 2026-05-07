<?php
// Conexión
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión: " . $conn->connect_error); }
$conn->set_charset('utf8mb4');

// Crear tabla conductores
$conn->query("CREATE TABLE IF NOT EXISTS conductores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100),
    cedula VARCHAR(20) DEFAULT '',
    cuenta_banco VARCHAR(50) DEFAULT ''
)");

// Insertar nombres desde viajes si está vacía
$check = $conn->query("SELECT COUNT(*) as total FROM conductores");
if($check->fetch_assoc()['total'] == 0) {
    $conn->query("INSERT INTO conductores (nombre) SELECT DISTINCT nombre FROM viajes WHERE nombre IS NOT NULL AND nombre != ''");
}

// Guardar
$mensaje = '';
if(isset($_POST['guardar'])) {
    $id = intval($_POST['guardar']);
    $cedula = $conn->real_escape_string($_POST['cedula'][$id]);
    $cuenta = $conn->real_escape_string($_POST['cuenta_banco'][$id]);
    
    if($conn->query("UPDATE conductores SET cedula='$cedula', cuenta_banco='$cuenta' WHERE id=$id")) {
        $mensaje = "<p style='color:green;background:#d4edda;padding:10px;border-radius:5px;'>✅ Guardado correctamente</p>";
    } else {
        $mensaje = "<p style='color:red;background:#f8d7da;padding:10px;border-radius:5px;'>❌ Error: " . $conn->error . "</p>";
    }
}

// Filtro
$filtro = isset($_GET['filtro']) ? $conn->real_escape_string($_GET['filtro']) : '';
$sql = "SELECT * FROM conductores";
if($filtro != '') {
    $sql .= " WHERE nombre LIKE '%$filtro%'";
}
$sql .= " ORDER BY nombre";
$result = $conn->query($sql);
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
    <form method="POST">
    <table>
        <tr>
            <th>Nombre</th>
            <th>Cédula</th>
            <th>Cuenta de Banco</th>
            <th></th>
        </tr>
        <?php while($c = $result->fetch_assoc()): ?>
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
        <?php endwhile; ?>
    </table>
    </form>
</div>

</body>
</html>