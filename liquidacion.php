<?php
include("nav.php"); // ✅ El menú solo se carga aquí, no en las respuestas AJAX
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}

/* =======================================================
   🔹 Si viene un parámetro GET de viajes_conductor (AJAX)
   ======================================================= */
if (isset($_GET['viajes_conductor'])) {
    $nombre = $_GET['viajes_conductor'];
    $desde = $_GET['desde'];
    $hasta = $_GET['hasta'];
    $empresa = $_GET['empresa'];

    $q = "SELECT fecha, ruta, empresa, vehiculo 
          FROM viajes 
          WHERE conductor='$nombre' 
          AND fecha BETWEEN '$desde' AND '$hasta' 
          AND empresa='$empresa' 
          ORDER BY fecha DESC";
    $r = $conn->query($q);

    echo '<table class="table table-sm table-bordered mb-0">
            <thead class="table-light">
              <tr>
                <th>Fecha</th>
                <th>Ruta</th>
                <th>Empresa</th>
                <th>Vehículo</th>
              </tr>
            </thead>
            <tbody>';
    while ($v = $r->fetch_assoc()) {
        echo "<tr>
                <td>{$v['fecha']}</td>
                <td>{$v['ruta']}</td>
                <td>{$v['empresa']}</td>
                <td>{$v['vehiculo']}</td>
              </tr>";
    }
    echo '</tbody></table>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>💰 Liquidación de Conductores</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
  background: #f3f6fa;
  font-family: 'Segoe UI', sans-serif;
}

.container-fluid {
  padding: 1.5rem;
}

/* Animación del panel lateral */
#contenidoPanel {
  opacity: 0;
  transform: translateX(20px);
  transition: opacity 0.4s ease, transform 0.4s ease;
}

#contenidoPanel.fade-in {
  opacity: 1;
  transform: translateX(0);
}

/* Estilos suaves de las tarjetas */
.card {
  border-radius: 1rem;
  box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}

/* Mejor visibilidad del total */
#totalGeneral {
  font-size: 1.4rem;
  color: #007bff;
  font-weight: 600;
}

/* Ajustes de encabezados */
h4, h5 {
  font-weight: 600;
  color: #333;
}
</style>
</head>

<body>
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4>💰 Liquidación de Conductores</h4>
    <div>
      <label>Desde: <input type="date" id="desde" value="<?= $_GET['desde'] ?? '' ?>"></label>
      <label>Hasta: <input type="date" id="hasta" value="<?= $_GET['hasta'] ?? '' ?>"></label>
      <label>Empresa:
        <select id="empresa">
          <option>Hospital</option>
          <option>JC</option>
          <option>Salud Total</option>
        </select>
      </label>
      <button class="btn btn-primary btn-sm" onclick="filtrar()">Filtrar</button>
    </div>
  </div>

  <div class="row g-3">
    <!-- 🔹 Columna 1: Tarifas -->
    <div class="col-md-3">
      <div class="card p-3">
        <h5>🚚 Tarifas por Tipo de Vehículo</h5>
        <table class="table table-sm">
          <thead><tr><th>Tipo</th><th>Completo</th><th>Medio</th><th>Extra</th><th>Carrotanque</th></tr></thead>
          <tbody>
            <tr><td>Carrotanque</td><td>-</td><td>-</td><td>-</td><td><input type="text" class="form-control form-control-sm" value="450000"></td></tr>
            <tr><td>Burbuja</td><td><input type="text" class="form-control form-control-sm" value="350000"></td><td>175</td><td>60</td><td>-</td></tr>
            <tr><td>Camión 350</td><td><input type="text" class="form-control form-control-sm" value="350000"></td><td>35</td><td>0</td><td>-</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 🔹 Columna 2: Conductores -->
    <div class="col-md-6">
      <div class="card p-3">
        <h5>👨‍✈️ Resumen por Conductor</h5>
        <div class="d-flex justify-content-end mb-2">
          <span id="totalGeneral">Total General: $0</span>
        </div>
        <table class="table table-hover table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>Conductor</th><th>Vehículo</th><th>Completos</th><th>Medios</th><th>Extras</th><th>Carrotanques</th><th>Total</th>
            </tr>
          </thead>
          <tbody id="tablaConductores">
            <tr data-nombre="Adalberto"><td>Adalberto</td><td>Carrotanque</td><td>0</td><td>0</td><td>0</td><td>6</td><td>$2.700.000</td></tr>
            <tr data-nombre="Yulver kuas"><td>Yulver kuas</td><td>Burbuja</td><td>1</td><td>0</td><td>0</td><td>0</td><td>$3.500.000</td></tr>
            <tr data-nombre="Orlando Iguaran"><td>Orlando Iguaran</td><td>Burbuja</td><td>1</td><td>0</td><td>0</td><td>0</td><td>$1.750.000</td></tr>
            <!-- puedes agregar más -->
          </tbody>
        </table>
      </div>
    </div>

    <!-- 🔹 Columna 3: Panel de viajes -->
    <div class="col-md-3">
      <div class="card p-3">
        <h5>🧾 Viajes</h5>
        <div id="contenidoPanel" class="fade-in">
          <p class="text-muted mb-0">Selecciona un conductor para ver sus viajes.</p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function filtrar(){
  const desde = document.getElementById('desde').value;
  const hasta = document.getElementById('hasta').value;
  const empresa = document.getElementById('empresa').value;
  location.href = `liquidacion.php?desde=${desde}&hasta=${hasta}&empresa=${empresa}`;
}

document.querySelectorAll('#tablaConductores tr').forEach(fila=>{
  fila.addEventListener('click', ()=>{
    const nombre = fila.dataset.nombre;
    const desde = document.getElementById('desde').value;
    const hasta = document.getElementById('hasta').value;
    const empresa = document.getElementById('empresa').value;
    const panel = document.getElementById('contenidoPanel');

    panel.classList.remove('fade-in');
    panel.innerHTML = '<div class="text-center py-3 text-muted">Cargando viajes...</div>';

    fetch(`liquidacion.php?viajes_conductor=${encodeURIComponent(nombre)}&desde=${desde}&hasta=${hasta}&empresa=${encodeURIComponent(empresa)}`)
      .then(r => r.text())
      .then(html => {
        panel.innerHTML = html;
        void panel.offsetWidth;
        panel.classList.add('fade-in');
      });
  });
});
</script>
</body>
</html>
