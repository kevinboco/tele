<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }

// === PARÁMETROS ===
$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$empresaFiltro = $_GET['empresa'] ?? "";

// === CONSULTA DE EMPRESAS ===
$empresas = [];
$resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes ORDER BY empresa ASC");
while ($row = $resEmp->fetch_assoc()) $empresas[] = $row['empresa'];

// === CONSULTA DE TARIFAS ===
$tarifas = [];
$resT = $conn->query("SELECT tipo_vehiculo, viaje_completo, viaje_medio, viaje_extra, carrotanque FROM tarifas");
while ($row = $resT->fetch_assoc()) $tarifas[] = $row;

// === CONSULTA DE VIAJES ===
$sql = "SELECT * FROM viajes WHERE fecha BETWEEN '$desde' AND '$hasta'";
if ($empresaFiltro !== "") $sql .= " AND empresa='$empresaFiltro'";
$res = $conn->query($sql);

// === PROCESAR DATOS ===
$conductores = [];
$totalGeneral = 0;
while ($v = $res->fetch_assoc()) {
    $c = $v['conductor'];
    if (!isset($conductores[$c])) {
        $conductores[$c] = ['tipo_vehiculo'=>$v['vehiculo'], 'completos'=>0, 'medios'=>0, 'extras'=>0, 'carros'=>0, 'total'=>0];
    }
    if ($v['tipo_viaje']=='Completo') $conductores[$c]['completos']++;
    if ($v['tipo_viaje']=='Medio') $conductores[$c]['medios']++;
    if ($v['tipo_viaje']=='Extra') $conductores[$c]['extras']++;
    if ($v['vehiculo']=='Carrotanque') $conductores[$c]['carros']++;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Liquidación de Conductores</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
  background: #f3f5f7;
  font-family: 'Inter', sans-serif;
  color: #222;
}

/* ======== TÍTULO ======== */
.page-title {
  text-align: center;
  margin-bottom: 25px;
}
.page-title h2 {
  font-weight: 700;
  font-size: 1.7rem;
}
.page-title .header-sub {
  color: #555;
}

/* ======== BOX ======== */
.box {
  background: white;
  border-radius: 16px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.05);
  padding: 15px;
}

/* ======== TABLA TARIFAS ======== */
.tarifas-table {
  width: 100%;
  text-align: center;
  border-collapse: separate;
  border-spacing: 0 6px;
}
.tarifas-table th {
  background: #eef1f4;
  padding: 8px;
  font-weight: 600;
  border-radius: 8px 8px 0 0;
}
.tarifas-table td {
  background: #fff;
  padding: 10px 6px;
  border-radius: 8px;
  vertical-align: middle;
  white-space: nowrap;
}
.tarifas-table input {
  width: 100%;
  text-align: right;
  font-weight: 600;
  color: #0d6efd;
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 8px;
  padding: 4px 8px;
  font-size: 0.9rem;
}
.tarifas-table th:first-child, .tarifas-table td:first-child {
  width: 35%;
  text-align: left;
}

/* ======== FILTRO ======== */
form .form-label {
  font-weight: 600;
  color: #333;
  font-size: 0.9rem;
}
form input, form select {
  border-radius: 10px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.05);
}
form button {
  border-radius: 10px;
  font-weight: 600;
  letter-spacing: 0.3px;
}

/* ======== TABLA CONDUCTORES ======== */
.conductores-table input {
  text-align: right;
  background: #f8f9fa;
  border: none;
  width: 100%;
}

/* ======== LAYOUT ======== */
.main-container {
  display: grid;
  grid-template-columns: 1.1fr 2.2fr 1.3fr;
  gap: 20px;
}

/* ======== SCROLL ======== */
::-webkit-scrollbar { height: 8px; width: 8px; }
::-webkit-scrollbar-thumb { background: #ccc; border-radius: 10px; }

@media(max-width: 992px) {
  .main-container { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<div class="container-fluid px-4 mt-3">

  <div class="page-title text-center">
    <h2>🧾 Liquidación de Conductores</h2>
    <div class="header-sub">
      Periodo: <strong><?= htmlspecialchars($desde) ?></strong> hasta <strong><?= htmlspecialchars($hasta) ?></strong>
      <?php if ($empresaFiltro !== ""): ?>
        • Empresa: <strong><?= htmlspecialchars($empresaFiltro) ?></strong>
      <?php endif; ?>
    </div>
  </div>

  <div class="main-container">
    <!-- ====== TARIFAS ====== -->
    <section class="box">
      <h5 class="mb-3">🚐 Tarifas por Tipo de Vehículo</h5>
      <table class="tarifas-table">
        <thead>
          <tr>
            <th>Tipo de Vehículo</th>
            <th>Viaje Completo</th>
            <th>Viaje Medio</th>
            <th>Viaje Extra</th>
            <th>Carrotanque</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($tarifas as $t): ?>
          <tr>
            <td><?= htmlspecialchars($t['tipo_vehiculo']) ?></td>
            <td><input type="text" value="<?= number_format($t['viaje_completo'],0,',','.') ?>"></td>
            <td><input type="text" value="<?= number_format($t['viaje_medio'],0,',','.') ?>"></td>
            <td><input type="text" value="<?= number_format($t['viaje_extra'],0,',','.') ?>"></td>
            <td><input type="text" value="<?= number_format($t['carrotanque'],0,',','.') ?>"></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <hr class="my-3">

      <h6 class="text-center mb-3">📅 Filtro de Liquidación</h6>
      <form class="row g-3 justify-content-center" method="get">
        <div class="col-md-4">
          <label class="form-label mb-1">Desde:</label>
          <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="form-control" required>
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1">Hasta:</label>
          <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="form-control" required>
        </div>
        <div class="col-md-3">
          <label class="form-label mb-1">Empresa:</label>
          <select name="empresa" class="form-select">
            <option value="">-- Todas --</option>
            <?php foreach($empresas as $e): ?>
              <option value="<?= htmlspecialchars($e) ?>" <?= $empresaFiltro==$e?'selected':'' ?>>
                <?= htmlspecialchars($e) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 align-self-end">
          <button class="btn btn-primary w-100" type="submit">Filtrar</button>
        </div>
      </form>
    </section>

    <!-- ====== CONDUCTORES ====== -->
    <section class="box">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5>👨‍✈️✈️ Resumen por Conductor</h5>
        <div class="badge bg-primary fs-6 px-3 py-2 shadow-sm">
          💰 Total General: <?= number_format($totalGeneral, 0, ',', '.') ?>
        </div>
      </div>
      <table class="table table-sm table-hover conductores-table align-middle">
        <thead class="table-light">
          <tr>
            <th>Conductor</th><th>Tipo Vehículo</th>
            <th>Completos</th><th>Medios</th><th>Extras</th><th>Carrotanques</th><th>Total</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($conductores as $nombre => $d): ?>
          <tr>
            <td><?= htmlspecialchars($nombre) ?></td>
            <td><?= htmlspecialchars($d['tipo_vehiculo']) ?></td>
            <td><?= $d['completos'] ?></td>
            <td><?= $d['medios'] ?></td>
            <td><?= $d['extras'] ?></td>
            <td><?= $d['carros'] ?></td>
            <td><input type="text" value="<?= number_format($d['total'],0,',','.') ?>"></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>

    <!-- ====== VIAJES ====== -->
    <section class="box">
      <h5>🧳 Viajes</h5>
      <table class="table table-sm table-hover align-middle">
        <thead class="table-light">
          <tr><th>Fecha</th><th>Ruta</th><th>Empresa</th><th>Vehículo</th></tr>
        </thead>
        <tbody>
        <?php $res->data_seek(0); while ($v = $res->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($v['fecha']) ?></td>
            <td><?= htmlspecialchars($v['ruta']) ?></td>
            <td><?= htmlspecialchars($v['empresa']) ?></td>
            <td><?= htmlspecialchars($v['vehiculo']) ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </section>
  </div>
</div>
</body>
</html>
