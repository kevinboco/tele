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

// Cargar todos los conductores para el JavaScript
$todos = $conn->query("SELECT * FROM conductores ORDER BY nombre");
$conductores_json = [];
while($c = $todos->fetch_assoc()) {
    $conductores_json[] = $c;
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
            padding: 12px;
            width: 100%;
            max-width: 500px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            outline: none;
        }
        .search-box input:focus {
            border-color: #007bff;
        }
        .btn-save {
            background: #28a745;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-save:hover {
            background: #218838;
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
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 90%;
        }
        td input[type="text"]:focus {
            border-color: #007bff;
            outline: none;
        }
        .no-results {
            text-align: center;
            padding: 20px;
            color: #999;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>🚛 Gestión de Conductores</h2>
    
    <!-- Buscador -->
    <div class="search-box">
        <input type="text" id="buscador" placeholder="🔍 Escribe para filtrar conductores..." autocomplete="off">
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
        <tbody id="tabla-body">
            <!-- Se llena con JavaScript -->
        </tbody>
    </table>
</div>

<script>
// Datos de conductores desde PHP
const conductores = <?php echo json_encode($conductores_json, JSON_UNESCAPED_UNICODE); ?>;

// Función para mostrar conductores
function mostrarConductores(filtro = '') {
    const tbody = document.getElementById('tabla-body');
    const filtroLower = filtro.toLowerCase();
    
    // Filtrar conductores
    const filtrados = conductores.filter(c => 
        c.nombre.toLowerCase().includes(filtroLower) ||
        (c.cedula && c.cedula.toLowerCase().includes(filtroLower)) ||
        (c.cuenta_banco && c.cuenta_banco.toLowerCase().includes(filtroLower))
    );
    
    // Generar HTML
    if (filtrados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="no-results">No se encontraron conductores</td></tr>';
        return;
    }
    
    tbody.innerHTML = filtrados.map(c => `
        <tr>
            <form method="POST" action="">
                <td>${c.nombre}</td>
                <td>
                    <input type="text" name="cedula" value="${c.cedula || ''}" placeholder="Cédula">
                </td>
                <td>
                    <input type="text" name="cuenta_banco" value="${c.cuenta_banco || ''}" placeholder="Cuenta bancaria">
                </td>
                <td>
                    <input type="hidden" name="id" value="${c.id}">
                    <button type="submit" name="guardar" class="btn-save">💾 Guardar</button>
                </td>
            </form>
        </tr>
    `).join('');
}

// Escuchar el input del buscador
document.getElementById('buscador').addEventListener('input', function() {
    mostrarConductores(this.value);
});

// Mostrar todos al cargar
mostrarConductores();
</script>

</body>
</html>