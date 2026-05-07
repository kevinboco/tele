<?php
// Conexión
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
$conn->set_charset('utf8mb4');

// Crear tabla conductores si no existe
$conn->query("CREATE TABLE IF NOT EXISTS conductores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100),
    cedula VARCHAR(20),
    cuenta_banco VARCHAR(50)
)");

// Guardar datos cuando se envía el formulario
if(isset($_POST['guardar'])) {
    $id = $_POST['id'];
    $cedula = $_POST['cedula'];
    $cuenta = $_POST['cuenta_banco'];
    $conn->query("UPDATE conductores SET cedula='$cedula', cuenta_banco='$cuenta' WHERE id=$id");
    echo "<p style='color:green'>✅ Guardado</p>";
}

// Buscar conductores según filtro
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : '';
if($filtro != '') {
    $result = $conn->query("SELECT * FROM conductores WHERE nombre LIKE '%$filtro%' ORDER BY nombre");
} else {
    $result = $conn->query("SELECT * FROM conductores ORDER BY nombre");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Conductores</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #333; color: white; padding: 10px; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        input, button { padding: 10px; margin: 5px; }
        .btn { background: blue; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>

<h2>Gestión de Conductores</h2>

<!-- BUSCADOR SIMPLE -->
<form method="GET">
    <input type="text" name="filtro" placeholder="Buscar conductor..." value="<?php echo $filtro; ?>" style="width:300px;">
    <button type="submit" class="btn">🔍 Buscar</button>
    <a href="?"><button type="button">Ver todos</button></a>
</form>

<!-- TABLA DE CONDUCTORES -->
<table>
    <tr>
        <th>Nombre</th>
        <th>Cédula</th>
        <th>Cuenta Banco</th>
        <th>Acción</th>
    </tr>
    <?php 
    // Si no hay conductores, insertar desde viajes
    $check = $conn->query("SELECT COUNT(*) as total FROM conductores");
    $row = $check->fetch_assoc();
    if($row['total'] == 0) {
        $conn->query("INSERT INTO conductores (nombre) SELECT DISTINCT nombre FROM viajes WHERE nombre IS NOT NULL AND nombre != ''");
        $result = $conn->query("SELECT * FROM conductores ORDER BY nombre");
    }
    
    while($conductor = $result->fetch_assoc()): 
    ?>
    <tr>
        <form method="POST">
            <td><?php echo $conductor['nombre']; ?></td>
            <td>
                <input type="text" name="cedula" value="<?php echo $conductor['cedula']; ?>" style="width:120px;">
            </td>
            <td>
                <input type="text" name="cuenta_banco" value="<?php echo $conductor['cuenta_banco']; ?>" style="width:180px;">
            </td>
            <td>
                <input type="hidden" name="id" value="<?php echo $conductor['id']; ?>">
                <button type="submit" name="guardar" class="btn">💾 Guardar</button>
            </td>
        </form>
    </tr>
    <?php endwhile; ?>
</table>

</body>
</html>