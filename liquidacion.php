<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}

/* =======================================================
   🔹 Guardar tarifas por vehículo y empresa (AJAX)
======================================================= */
if (isset($_POST['guardar_tarifa'])) {
    $empresa = $conn->real_escape_string($_POST['empresa']);
    $vehiculo = $conn->real_escape_string($_POST['tipo_vehiculo']);
    $campo = $conn->real_escape_string($_POST['campo']);
    $valor = (int)$_POST['valor'];

    // Crear registro si no existe
    $conn->query("INSERT IGNORE INTO tarifas (empresa, tipo_vehiculo) VALUES ('$empresa', '$vehiculo')");

    // Actualizar el valor específico
    $sql = "UPDATE tarifas SET $campo = $valor WHERE empresa='$empresa' AND tipo_vehiculo='$vehiculo'";
    if ($conn->query($sql)) {
        echo "ok";
    } else {
        echo "error: " . $conn->error;
    }
    exit;
}

/* =======================================================
   🔹 Endpoint AJAX: viajes por conductor
======================================================= */
if (isset($_GET['viajes_conductor'])) {
    $nombre  = $conn->real_escape_string($_GET['viajes_conductor']);
    $desde   = $_GET['desde'];
    $hasta   = $_GET['hasta'];
    $empresa = $_GET['empresa'] ?? "";

    $sql = "SELECT fecha, ruta, empresa, tipo_vehiculo
            FROM viajes
            WHERE nombre = '$nombre'
              AND fecha BETWEEN '$desde' AND '$hasta'";
    if ($empresa !== "") {
        $empresa = $conn->real_escape_string($empresa);
        $sql .= " AND empresa = '$empresa'";
    }
    $sql .= " ORDER BY fecha ASC";

    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        echo "<table class='table table-bordered table-striped mb-0'>
                <thead>
                  <tr class='table-primary text-center'>
                    <th>Fecha</th>
                    <th>Ruta</th>
                    <th>Empresa</th>
                    <th>Vehículo</th>
                  </tr>
                </thead>
                <tbody>";
        while ($r = $res->fetch_assoc()) {
            echo "<tr>
                    <td>".htmlspecialchars($r['fecha'])."</td>
                    <td>".htmlspecialchars($r['ruta'])."</td>
                    <td>".htmlspecialchars($r['empresa'])."</td>
                    <td>".htmlspecialchars($r['tipo_vehiculo'])."</td>
                  </tr>";
        }
        echo "  </tbody></table>";
    } else {
        echo "<p class='text-center text-muted mb-0'>No se encontraron viajes para este conductor en ese rango.</p>";
    }
    exit;
}

/* =======================================================
   🔹 Formulario inicial (si faltan fechas)
======================================================= */
if (!isset($_GET['desde']) || !isset($_GET['hasta'])) {
    $empresas = [];
    $resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
    if ($resEmp) while ($r = $resEmp->fetch_assoc()) $empresas[] = $r['empresa'];
    ?>
    <style>
      body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;color:#333;padding:40px}
      .card{max-width:460px;margin:0 auto}
    </style>
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="text-center mb-3">📅 Filtrar viajes por rango de fechas</h2>
        <form method="get" class="vstack gap-3">
          <label class="form-label">Desde:
            <input type="date" name="desde" class="form-control" required>
          </label>
          <label class="form-label">Hasta:
            <input type="date" name="hasta" class="form-control" required>
          </label>
          <label class="form-label">Empresa:
            <select name="empresa" class="form-select">
              <option value="">-- Todas --</option>
              <?php foreach($empresas as $e): ?>
                <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <button class="btn btn-primary w-100" type="submit">Filtrar</button>
        </form>
      </div>
    </div>
    <?php
    exit;
}

/* =======================================================
   🔹 Cálculo y armado de tablas
======================================================= */
$desde = $_GET['desde'];
$hasta = $_GET['hasta'];
$empresaFiltro = $_GET['empresa'] ?? "";

$sql = "SELECT nombre, ruta, empresa, tipo_vehiculo FROM viajes
        WHERE fecha BETWEEN '$desde' AND '$hasta'";
if ($empresaFiltro !== "") {
    $empresaFiltro = $conn->real_escape_string($empresaFiltro);
    $sql .= " AND empresa = '$empresaFiltro'";
}
$res = $conn->query($sql);

$datos = [];
$vehiculos = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $nombre   = $row['nombre'];
        $ruta     = $row['ruta'];
        $vehiculo = $row['tipo_vehiculo'];
        $guiones  = substr_count($ruta, '-');

        if (!isset($datos[$nombre])) {
            $datos[$nombre] = ["vehiculo"=>$vehiculo,"completos"=>0,"medios"=>0,"extras"=>0,"carrotanques"=>0];
        }
        if (!in_array($vehiculo, $vehiculos, true)) $vehiculos[] = $vehiculo;

        if ($vehiculo === "Carrotanque" && $guiones == 0) {
            $datos[$nombre]["carrotanques"]++;
        } elseif (stripos($ruta, "Maicao") === false) {
            $datos[$nombre]["extras"]++;
        } elseif ($guiones == 2) {
            $datos[$nombre]["completos"]++;
        } elseif ($guiones == 1) {
            $datos[$nombre]["medios"]++;
        }
    }
}

/* Empresas para el SELECT del header */
$empresas = [];
$resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
if ($resEmp) while ($r = $resEmp->fetch_assoc()) $empresas[] = $r['empresa'];

/* Tarifa guardada en la BD */
$tarifas_guardadas = [];
$resTarifas = $conn->query("SELECT * FROM tarifas WHERE empresa='$empresaFiltro'");
if ($resTarifas) {
  while ($r = $resTarifas->fetch_assoc()) {
    $tarifas_guardadas[$r['tipo_vehiculo']] = $r;
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Liquidación de Conductores</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  :root{ --gap:18px; --box-radius:14px; }
  body{font-family:'Segoe UI',sans-serif;background:#eef2f6;color:#333;padding:20px}
  .page-title{background:#fff;border-radius:var(--box-radius);padding:12px 16px;margin-bottom:var(--gap);box-shadow:0 2px 8px rgba(0,0,0,.05)}
  .header-grid{display:grid;grid-template-columns: auto 1fr auto;align-items:center;gap:12px;}
  .header-center{ text-align:center; }
  .header-center h2{ margin:4px 0 2px 0; }
  .header-sub{ font-size:14px; color:#555; }
  .layout{ display:grid; grid-template-columns: 1fr 2fr 1.2fr; gap:var(--gap); align-items:start; }
  .box{ border-radius:var(--box-radius); box-shadow:0 2px 10px rgba(0,0,0,.06); padding:14px; }
  table{ background:#fff; border-radius:10px; overflow:hidden }
  th{ background:#0d6efd; color:#fff; text-align:center; padding:10px }
  td{ text-align:center; padding:8px; border-bottom:1px solid #eee }
  input[type=number], input[readonly]{ width:100%; max-width:160px; padding:6px; border:1px solid #ced4da; border-radius:8px; text-align:right }
  .conductor-link{cursor:pointer;color:#0d6efd;text-decoration:underline;}
</style>
</head>
<body>

<div class="page-title">
  <div class="header-grid">
    <form class="filtro-inline" method="get">
      <div class="d-flex flex-wrap align-items-center gap-2">
        <label>Desde:</label>
        <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" required>
        <label>Hasta:</label>
        <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" required>
        <label>Empresa:</label>
        <select name="empresa">
          <option value="">-- Todas --</option>
          <?php foreach($empresas as $e): ?>
            <option value="<?= htmlspecialchars($e) ?>" <?= $empresaFiltro==$e?'selected':'' ?>>
              <?= htmlspecialchars($e) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary btn-sm ms-2" type="submit">Filtrar</button>
      </div>
    </form>

    <div class="header-center">
      <h2>🪙 Liquidación de Conductores</h2>
      <div class="header-sub">
        Periodo: <strong><?= htmlspecialchars($desde) ?></strong> hasta <strong><?= htmlspecialchars($hasta) ?></strong>
        <?php if ($empresaFiltro !== ""): ?>
          &nbsp;•&nbsp; Empresa: <strong><?= htmlspecialchars($empresaFiltro) ?></strong>
        <?php endif; ?>
      </div>
    </div>
    <div></div>
  </div>
</div>

<div class="layout">
  <section class="box">
    <h3 class="text-center">🚐 Tarifas por Tipo de Vehículo</h3>
    <table id="tabla_tarifas" class="table mb-0">
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
      <?php foreach ($vehiculos as $veh): 
        $t = $tarifas_guardadas[$veh] ?? ["completo"=>0,"medio"=>0,"extra"=>0,"carrotanque"=>0];
      ?>
        <tr>
          <td><?= htmlspecialchars($veh) ?></td>
          <?php if ($veh === "Carrotanque"): ?>
            <td>-</td><td>-</td><td>-</td>
            <td><input type="number" step="1000" value="<?= $t['carrotanque'] ?>" oninput="recalcular()"></td>
          <?php else: ?>
            <td><input type="number" step="1000" value="<?= $t['completo'] ?>" oninput="recalcular()"></td>
            <td><input type="number" step="1000" value="<?= $t['medio'] ?>" oninput="recalcular()"></td>
            <td><input type="number" step="1000" value="<?= $t['extra'] ?>" oninput="recalcular()"></td>
            <td>-</td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="box">
    <h3 class="text-center">🧑‍✈️ Resumen por Conductor</h3>
    <table id="tabla_conductores" class="table">
      <thead>
        <tr>
          <th>Conductor</th><th>Tipo Vehículo</th><th>Completos</th><th>Medios</th><th>Extras</th><th>Carrotanques</th><th>Total</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($datos as $conductor => $viajes): ?>
        <tr data-vehiculo="<?= htmlspecialchars($viajes['vehiculo']) ?>">
          <td class="conductor-link"><?= htmlspecialchars($conductor) ?></td>
          <td><?= htmlspecialchars($viajes['vehiculo']) ?></td>
          <td><?= (int)$viajes["completos"] ?></td>
          <td><?= (int)$viajes["medios"] ?></td>
          <td><?= (int)$viajes["extras"] ?></td>
          <td><?= (int)$viajes["carrotanques"] ?></td>
          <td><input type="text" class="totales form-control" readonly></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <aside class="box" id="panelViajes">
    <h4>🧳 Viajes</h4>
    <div id="contenidoPanel"><p class="text-muted mb-0">Selecciona un conductor para ver sus viajes aquí.</p></div>
  </aside>
</div>

<script>
function getTarifas(){
  const tarifas = {};
  document.querySelectorAll('#tabla_tarifas tbody tr').forEach(row=>{
    const veh = row.cells[0].innerText.trim();
    const completo = row.cells[1].querySelector('input')?parseFloat(row.cells[1].querySelector('input').value)||0:0;
    const medio = row.cells[2].querySelector('input')?parseFloat(row.cells[2].querySelector('input').value)||0:0;
    const extra = row.cells[3].querySelector('input')?parseFloat(row.cells[3].querySelector('input').value)||0:0;
    const carro = row.cells[4].querySelector('input')?parseFloat(row.cells[4].querySelector('input').value)||0:0;
    tarifas[veh] = {completo, medio, extra, carrotanque:carro};
  });
  return tarifas;
}
function formatNumber(num){ return (num||0).toLocaleString('es-CO'); }
function recalcular(){
  const tarifas = getTarifas();
  const filas = document.querySelectorAll('#tabla_conductores tbody tr');
  let total = 0;
  filas.forEach(f=>{
    const veh = f.dataset.vehiculo;
    const c = parseInt(f.cells[2].innerText)||0;
    const m = parseInt(f.cells[3].innerText)||0;
    const e = parseInt(f.cells[4].innerText)||0;
    const ca = parseInt(f.cells[5].innerText)||0;
    const t = tarifas[veh] || {completo:0,medio:0,extra:0,carrotanque:0};
    const totalFila = c*t.completo + m*t.medio + e*t.extra + ca*t.carrotanque;
    f.querySelector('input.totales').value = formatNumber(totalFila);
    total += totalFila;
  });
}

document.querySelectorAll('#tabla_tarifas input').forEach(input=>{
  input.addEventListener('change',()=>{
    const fila = input.closest('tr');
    const tipoVehiculo = fila.cells[0].innerText.trim();
    const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
    const campoIndex = Array.from(fila.cells).findIndex(c=>c.contains(input));
    const campos = ['completo','medio','extra','carrotanque'];
    const campo = campos[campoIndex-1] || campos[campoIndex];
    const valor = parseInt(input.value)||0;

    fetch(`<?= basename(__FILE__) ?>`,{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:new URLSearchParams({
        guardar_tarifa:1,
        empresa,
        tipo_vehiculo:tipoVehiculo,
        campo,
        valor
      })
    }).then(r=>r.text()).then(t=>{
      if(t.trim()!=='ok') console.error('Error guardando tarifa:',t);
      recalcular();
    });
  });
});

document.querySelectorAll('.conductor-link').forEach(td=>{
  td.addEventListener('click',()=>{
    const nombre=td.innerText.trim();
    const desde="<?= htmlspecialchars($desde) ?>";
    const hasta="<?= htmlspecialchars($hasta) ?>";
    const empresa="<?= htmlspecialchars($empresaFiltro) ?>";
    document.getElementById('contenidoPanel').innerHTML="<p class='text-center'>Cargando...</p>";
    fetch(`<?= basename(__FILE__) ?>?viajes_conductor=${encodeURIComponent(nombre)}&desde=${desde}&hasta=${hasta}&empresa=${encodeURIComponent(empresa)}`)
    .then(r=>r.text()).then(html=>{document.getElementById('contenidoPanel').innerHTML=html;});
  });
});

recalcular();
</script>
</body>
</html>
