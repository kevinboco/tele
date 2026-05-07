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

// Insertar conductores desde viajes si la tabla está vacía
$check = $conn->query("SELECT COUNT(*) as total FROM conductores");
$row = $check->fetch_assoc();
if($row['total'] == 0) {
    $conn->query("INSERT INTO conductores (nombre) SELECT DISTINCT nombre FROM viajes WHERE nombre IS NOT NULL AND nombre != ''");
}

// Guardar datos
if(isset($_POST['guardar'])) {
    $id = (int)$_POST['id'];
    $cedula = $conn->real_escape_string($_POST['cedula']);
    $cuenta = $conn->real_escape_string($_POST['cuenta_banco']);
    $conn->query("UPDATE conductores SET cedula='$cedula', cuenta_banco='$cuenta' WHERE id=$id");
    echo "<p style='color:green; font-weight:bold;'>✅ Datos guardados correctamente</p>";
}

// Filtrar búsqueda
$filtro = isset($_GET['filtro']) ? $conn->real_escape_string($_GET['filtro']) : '';
if($filtro != '') {
    $result = $conn->query("SELECT * FROM conductores WHERE nombre LIKE '%$filtro%' ORDER BY nombre");
} else {
    $result = $conn->query("SELECT * FROM conductores ORDER BY nombre");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conductores</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
        }
        h2 {
            margin-top: 0;
        }
        .search-box {
            margin-bottom: 20px;
        }
        .search-box input {
            padding: 10px;
            width: 300px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .search-box button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }
        .btn-search {
            background: #007bff;
            color: white;
        }
        .btn-all {
            background: #6c757d;
            color: white;
        }
        .btn-save {
            background: #28a745;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background: #343a40;
            color: white;
            padding: 12px;
            text-align: left;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        tr:hover {
            background: #f8f9fa;
        }
        td input[type="text"] {
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 3px;
            width: 90%;
        }
        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>🚛 Gestión de Conductores</h2>
    
    <!-- Buscador -->
    <div class="search-box">
        <form method="GET" action="">
            <input type="text" name="filtro" placeholder="Buscar conductor..." value="<?php echo htmlspecialchars($filtro); ?>">
            <button type="submit" class="btn-search">🔍 Buscar</button>
            <a href="?"><button type="button" class="btn-all">Ver todos</button></a>
        </form>
    </div>

    <!-- Tabla -->
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Cédula</th>
                <th>Cuenta de Banco</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php while($c = $result->fetch_assoc()): ?>
            <tr>
                <form method="POST" action="">
                    <td><?php echo htmlspecialchars($c['nombre']); ?></td>
                    <td>
                        <input type="text" name="cedula" value="<?php echo htmlspecialchars($c['cedula']); ?>" placeholder="Cédula">
                    </td>
                    <td>
                        <input type="text" name="cuenta_banco" value="<?php echo htmlspecialchars($c['cuenta_banco']); ?>" placeholder="Cuenta bancaria">
                    </td>
                    <td>
                        <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                        <button type="submit" name="guardar" class="btn-save">💾 Guardar</button>
                    </td>
                </form>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

</body>
</html>