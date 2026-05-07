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

// Guardar datos vía AJAX
if(isset($_POST['guardar_ajax'])) {
    $id = (int)$_POST['id'];
    $cedula = $conn->real_escape_string($_POST['cedula']);
    $cuenta = $conn->real_escape_string($_POST['cuenta_banco']);
    $conn->query("UPDATE conductores SET cedula='$cedula', cuenta_banco='$cuenta' WHERE id=$id");
    echo "ok";
    exit;
}

// Cargar todos los conductores
$result = $conn->query("SELECT * FROM conductores ORDER BY nombre");
$conductores = [];
while($c = $result->fetch_assoc()) {
    $conductores[] = $c;
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
        h2 { margin-top: 0; }
        .mensaje {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            display: none;
        }
        .mensaje.success {
            background: #d4edda;
            color: #155724;
            display: block;
        }
        .search-box { margin-bottom: 20px; }
        .search-box input {
            padding: 12px;
            width: 100%;
            max-width: 500px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            outline: none;
        }
        .search-box input:focus { border-color: #007bff; }
        .btn-save {
            background: #28a745;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-save:hover { background: #218838; }
        .btn-save:disabled { background: #6c757d; cursor: not-allowed; }
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
        tr:hover { background: #f8f9fa; }
        td input[type="text"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 90%;
        }
        .guardado-ok {
            background: #d4edda !important;
            transition: background 0.3s;
        }
    </style>
</head>
<body>

<?php include("nav.php"); ?>

<div class="container">
    <h2>🚛 Gestión de Conductores</h2>
    
    <div id="mensaje" class="mensaje"></div>
    
    <!-- Buscador -->
    <div class="search-box">
        <input type="text" id="buscador" placeholder="🔍 Escribe para filtrar..." autocomplete="off">
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
            <?php foreach($conductores as $c): ?>
            <tr class="fila-conductor" data-id="<?php echo $c['id']; ?>" data-nombre="<?php echo strtolower(htmlspecialchars($c['nombre'])); ?>">
                <td class="nombre-celda"><?php echo htmlspecialchars($c['nombre']); ?></td>
                <td>
                    <input type="text" class="input-cedula" value="<?php echo htmlspecialchars($c['cedula']); ?>" placeholder="Cédula">
                </td>
                <td>
                    <input type="text" class="input-cuenta" value="<?php echo htmlspecialchars($c['cuenta_banco']); ?>" placeholder="Cuenta bancaria">
                </td>
                <td>
                    <button type="button" class="btn-save btn-guardar" onclick="guardarConductor(this)">💾 Guardar</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
// Filtrar mientras escribes
document.getElementById('buscador').addEventListener('input', function() {
    const filtro = this.value.toLowerCase();
    const filas = document.querySelectorAll('.fila-conductor');
    
    filas.forEach(function(fila) {
        const nombre = fila.getAttribute('data-nombre');
        if (nombre.includes(filtro)) {
            fila.style.display = '';
        } else {
            fila.style.display = 'none';
        }
    });
});

// Guardar sin recargar
function guardarConductor(boton) {
    const fila = boton.closest('tr');
    const id = fila.getAttribute('data-id');
    const cedula = fila.querySelector('.input-cedula').value;
    const cuenta = fila.querySelector('.input-cuenta').value;
    const mensajeDiv = document.getElementById('mensaje');
    
    // Deshabilitar botón mientras guarda
    boton.disabled = true;
    boton.textContent = 'Guardando...';
    
    // Enviar datos
    const formData = new FormData();
    formData.append('guardar_ajax', '1');
    formData.append('id', id);
    formData.append('cedula', cedula);
    formData.append('cuenta_banco', cuenta);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        if(data === 'ok') {
            // Mostrar mensaje
            mensajeDiv.textContent = '✅ Datos guardados correctamente';
            mensajeDiv.className = 'mensaje success';
            
            // Efecto visual en la fila
            fila.classList.add('guardado-ok');
            setTimeout(() => {
                fila.classList.remove('guardado-ok');
                mensajeDiv.style.display = 'none';
            }, 2000);
        } else {
            mensajeDiv.textContent = '❌ Error al guardar';
            mensajeDiv.className = 'mensaje success';
            mensajeDiv.style.background = '#f8d7da';
            mensajeDiv.style.color = '#721c24';
        }
        
        // Restaurar botón
        boton.disabled = false;
        boton.textContent = '💾 Guardar';
    })
    .catch(error => {
        mensajeDiv.textContent = '❌ Error de conexión';
        mensajeDiv.className = 'mensaje success';
        boton.disabled = false;
        boton.textContent = '💾 Guardar';
    });
}
</script>

</body>
</html>