<?php
// ==================== CONFIGURACIÓN DE CONEXIÓN ====================
$conn = new mysqli("localhost", "root", "", "tu_basedatos");
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Si se recibe una solicitud AJAX para viajes del conductor
if (isset($_GET['viajes_conductor'])) {
    $nombre = $_GET['viajes_conductor'];
    $desde = $_GET['desde'];
    $hasta = $_GET['hasta'];

    $sql = "SELECT fecha, ruta, empresa, vehiculo 
            FROM viajes 
            WHERE conductor = ? 
              AND fecha BETWEEN ? AND ? 
            ORDER BY fecha ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $nombre, $desde, $hasta);
    $stmt->execute();
    $result = $stmt->get_result();

    echo "<h3>🚗 Viajes de <b>$nombre</b> entre $desde y $hasta</h3>";
    echo "<table class='tabla-viajes'>
            <tr>
                <th>Fecha</th>
                <th>Ruta</th>
                <th>Empresa</th>
                <th>Vehículo</th>
            </tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>{$row['fecha']}</td>
                <td>{$row['ruta']}</td>
                <td>{$row['empresa']}</td>
                <td>{$row['vehiculo']}</td>
              </tr>";
    }
    echo "</table>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Resumen por Conductor</title>
<style>
/* ======== ESTILOS GENERALES ======== */
body {
    font-family: 'Segoe UI', sans-serif;
    background: #f7f9fb;
    margin: 0;
    padding: 20px;
}

table {
    border-collapse: collapse;
    width: 80%;
    margin: 20px auto;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    border-radius: 10px;
    overflow: hidden;
}

th, td {
    padding: 10px 15px;
    text-align: center;
    border-bottom: 1px solid #ddd;
}

th {
    background-color: #007bff;
    color: white;
}

tr:hover { background-color: #f1f1f1; }

/* ======== MODAL ======== */
.modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    background: white;
    border-radius: 10px;
    width: 600px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.3);
    cursor: default;
}

.modal-header {
    padding: 10px 15px;
    background: #007bff;
    color: white;
    font-weight: bold;
    cursor: move; /* 👈 se puede arrastrar desde aquí */
    border-radius: 10px 10px 0 0;
}

.modal-content {
    padding: 20px;
    max-height: 400px;
    overflow-y: auto;
}

.modal-close {
    float: right;
    cursor: pointer;
    color: white;
    font-size: 18px;
}

.modal-close:hover {
    color: #ffdddd;
}

/* Tabla dentro del modal */
.tabla-viajes {
    width: 100%;
    border-collapse: collapse;
}

.tabla-viajes th {
    background-color: #007bff;
    color: white;
}

.tabla-viajes td, .tabla-viajes th {
    padding: 8px;
    border-bottom: 1px solid #ddd;
}
</style>
</head>
<body>

<h2 style="text-align:center">🧑‍✈️ Resumen por Conductor</h2>

<!-- ======== TABLA PRINCIPAL ======== -->
<table>
    <tr>
        <th>Conductor</th>
        <th>Tipo Vehículo</th>
        <th>Completos</th>
        <th>Medios</th>
        <th>Extras</th>
        <th>Total a Pagar</th>
    </tr>
    <tr>
        <td><a href="#" class="verViajes" data-nombre="Luis Hernández Polanco">Luis Hernández Polanco</a></td>
        <td>Burbuja</td>
        <td>0</td>
        <td>1</td>
        <td>1</td>
        <td>-</td>
    </tr>
    <tr>
        <td><a href="#" class="verViajes" data-nombre="Miguel Echeto">Miguel Echeto</a></td>
        <td>Burbuja</td>
        <td>4</td>
        <td>1</td>
        <td>1</td>
        <td>-</td>
    </tr>
</table>

<!-- ======== MODAL ======== -->
<div class="modal" id="modalViajes">
    <div class="modal-header" id="modalHeader">
        Viajes del Conductor
        <span class="modal-close" id="cerrarModal">&times;</span>
    </div>
    <div class="modal-content" id="contenidoModal">
        Cargando viajes...
    </div>
</div>

<script>
const modal = document.getElementById("modalViajes");
const contenidoModal = document.getElementById("contenidoModal");
const cerrar = document.getElementById("cerrarModal");

// Fechas seleccionadas (puedes reemplazar por tus variables PHP reales)
const fechaDesde = "2025-10-01";
const fechaHasta = "2025-10-09";

// Mostrar modal al hacer clic en el nombre del conductor
document.querySelectorAll(".verViajes").forEach(link => {
    link.addEventListener("click", e => {
        e.preventDefault();
        const nombre = link.dataset.nombre;
        modal.style.display = "block";
        contenidoModal.innerHTML = "Cargando viajes...";

        // Petición AJAX para obtener los viajes
        fetch(`?viajes_conductor=${encodeURIComponent(nombre)}&desde=${fechaDesde}&hasta=${fechaHasta}`)
            .then(res => res.text())
            .then(html => contenidoModal.innerHTML = html)
            .catch(err => contenidoModal.innerHTML = "Error al cargar los viajes.");
    });
});

// Cerrar modal
cerrar.onclick = () => modal.style.display = "none";
window.onclick = e => { if (e.target == modal) modal.style.display = "none"; };

// ======== HACER MODAL ARRASTRABLE ========
dragElement(modal);

function dragElement(elmnt) {
  const header = document.getElementById("modalHeader");
  let pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;

  if (header) header.onmousedown = dragMouseDown;
  else elmnt.onmousedown = dragMouseDown;

  function dragMouseDown(e) {
    e = e || window.event;
    e.preventDefault();
    // obtener posición inicial
    pos3 = e.clientX;
    pos4 = e.clientY;
    document.onmouseup = closeDragElement;
    document.onmousemove = elementDrag;
  }

  function elementDrag(e) {
    e = e || window.event;
    e.preventDefault();
    // calcular nueva posición
    pos1 = pos3 - e.clientX;
    pos2 = pos4 - e.clientY;
    pos3 = e.clientX;
    pos4 = e.clientY;
    // mover elemento
    elmnt.style.top = (elmnt.offsetTop - pos2) + "px";
    elmnt.style.left = (elmnt.offsetLeft - pos1) + "px";
  }

  function closeDragElement() {
    document.onmouseup = null;
    document.onmousemove = null;
  }
}
</script>
</body>
</html>
