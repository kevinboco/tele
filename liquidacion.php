funciona pero me dañaste el codigo  <?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
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

  /* ===== Encabezado con filtro a la izquierda y título centrado ===== */
  .page-title{
    background:#fff;border-radius:var(--box-radius);
    padding:12px 16px;margin-bottom:var(--gap);
    box-shadow:0 2px 8px rgba(0,0,0,.05)
  }
  .header-grid{
    display:grid;
    grid-template-columns: auto 1fr auto; /* izq filtro / centro título / derecha espacio */
    align-items:center;
    gap:12px;
  }
  .header-center{ text-align:center; }
  .header-center h2{ margin:4px 0 2px 0; }
  .header-sub{ font-size:14px; color:#555; }

  /* Filtro compacto en header */
  .filtro-inline .form-control,
  .filtro-inline .form-select{
    height:32px; padding:2px 8px; font-size:14px; min-width:120px;
  }
  .filtro-inline label{ font-weight:600; margin:0 4px 0 0; }
  .filtro-inline .btn{ height:32px; padding:2px 10px; }

  @media (max-width: 992px){
    .header-grid{ grid-template-columns: 1fr; }
    .header-center{ order:-1; } /* el título arriba, filtro abajo */
    .filtro-inline .form-control, .filtro-inline .form-select{ width:100%; min-width:0; }
  }

  /* ===== Layout 3 columnas ===== */
  .layout{ display:grid; grid-template-columns: 1fr 2fr 1.2fr; gap:var(--gap); align-items:start; }
  @media (max-width:1200px){ .layout{grid-template-columns:1fr;} #panelViajes{position:relative;top:auto;} }

  /* Colores cajas */
  .box-left  { background:#e8f0ff; }
  .box-center{ background:#e9f9ee; }
  .box-right { background:#fff9e6; }
  .box{ border-radius:var(--box-radius); box-shadow:0 2px 10px rgba(0,0,0,.06); padding:14px; }

  /* Tablas */
  h3.section-title{ text-align:center; margin:6px 0 12px 0; }
  table{ background:#fff; border-radius:10px; overflow:hidden }
  th{ background:#0d6efd; color:#fff; text-align:center; padding:10px }
  td{ text-align:center; padding:8px; border-bottom:1px solid #eee }
  tr:hover{ background:#f6faff }
  input[type=number], input[readonly]{ width:100%; max-width:160px; padding:6px; border:1px solid #ced4da; border-radius:8px; text-align:right }

  /* Total arriba */
  .total-chip{
    display:inline-block; padding:6px 12px; border-radius:999px;
    background:#e9f2ff; color:#0d6efd; font-weight:700; border:1px solid #d6e6ff;
    margin-bottom:8px; float:right;
  }
  .section-title::after{ content:""; display:block; clear:both; }

  /* Panel derecho */
  #panelViajes{ position:sticky; top:12px; }
  #panelViajes .panel-header{
    display:flex;align-items:center;justify-content:space-between;
    background:#0d6efd;color:#fff;padding:10px 12px;border-radius:10px;
    position:sticky;top:0;z-index:2;
  }
  #panelViajes .panel-body{ padding:10px; max-height:70vh; overflow:auto; }
  .btn-clear{background:transparent;border:none;color:#fff;opacity:.9}
  .btn-clear:hover{opacity:1}
  .conductor-link{cursor:pointer;color:#0d6efd;text-decoration:underline;}
</style>
</head>
<body>

<!-- ===== Encabezado con filtro a la izquierda ===== -->
<div class="page-title">
  <div class="header-grid">
    <!-- Filtro compacto -->
    <form class="filtro-inline" method="get">
      <div class="d-flex flex-wrap align-items-center gap-2">
        <label class="mb-0">Desde:</label>
        <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="form-control form-control-sm w-auto" required>

        <label class="ms-2 mb-0">Hasta:</label>
        <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="form-control form-control-sm w-auto" required>

        <label class="ms-2 mb-0">Empresa:</label>
        <select name="empresa" class="form-select form-select-sm w-auto">
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

    <!-- Centro: Título -->
    <div class="header-center">
      <h2>🪙 Liquidación de Conductores</h2>
      <div class="header-sub">
        Periodo: <strong><?= htmlspecialchars($desde) ?></strong> hasta <strong><?= htmlspecialchars($hasta) ?></strong>
        <?php if ($empresaFiltro !== ""): ?>
          &nbsp;•&nbsp; Empresa: <strong><?= htmlspecialchars($empresaFiltro) ?></strong>
        <?php endif; ?>
      </div>
    </div>

    <!-- Derecha: espacio (para mantener el centrado perfecto) -->
    <div></div>
  </div>
</div>

<!-- ===== 3 columnas ===== -->
<div class="layout">
  <!-- Columna 1 -->
  <section class="box box-left">
    <h3 class="section-title">🚐 Tarifas por Tipo de Vehículo</h3>
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
      <?php foreach ($vehiculos as $veh): ?>
        <tr>
          <td><?= htmlspecialchars($veh) ?></td>
          <?php if ($veh === "Carrotanque"): ?>
            <td>-</td><td>-</td><td>-</td>
            <td><input type="number" step="1000" value="0" oninput="recalcular()"></td>
          <?php else: ?>
            <td><input type="number" step="1000" value="0" oninput="recalcular()"></td>
            <td><input type="number" step="1000" value="0" oninput="recalcular()"></td>
            <td><input type="number" step="1000" value="0" oninput="recalcular()"></td>
            <td>-</td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <!-- Columna 2 -->
  <section class="box box-center">
    <h3 class="section-title">🧑‍✈️ Resumen por Conductor</h3>
    <div class="total-chip">🔢 Total General: <span id="total_general">0</span></div>

    <table id="tabla_conductores" class="table">
      <thead>
        <tr>
          <th>Conductor</th>
          <th>Tipo Vehículo</th>
          <th>Completos</th>
          <th>Medios</th>
          <th>Extras</th>
          <th>Carrotanques</th>
          <th>Total a Pagar</th>
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

  <!-- Columna 3 -->
  <aside id="panelViajes" class="box box-right">
    <div class="panel-header">
      <div id="tituloPanel">🧳 Viajes</div>
      <button class="btn-clear" title="Limpiar" onclick="limpiarPanel()">✕</button>
    </div>
    <div id="contenidoPanel" class="panel-body">
      <p class="text-muted mb-0">Selecciona un conductor en la tabla para ver sus viajes aquí.</p>
    </div>
  </aside>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function getTarifas() {
  const tarifas = {};
  document.querySelectorAll('#tabla_tarifas tbody tr').forEach(row => {
    const vehiculo = row.cells[0].innerText.trim();
    const completo = row.cells[1].querySelector('input') ? parseFloat(row.cells[1].querySelector('input').value)||0 : 0;
    const medio    = row.cells[2].querySelector('input') ? parseFloat(row.cells[2].querySelector('input').value)||0 : 0;
    const extra    = row.cells[3].querySelector('input') ? parseFloat(row.cells[3].querySelector('input').value)||0 : 0;
    const carro    = row.cells[4].querySelector('input') ? parseFloat(row.cells[4].querySelector('input').value)||0 : 0;
    tarifas[vehiculo] = {completo, medio, extra, carrotanque: carro};
  });
  return tarifas;
}
function formatNumber(num){ return (num||0).toLocaleString('es-CO'); }
function recalcular(){
  const tarifas = getTarifas();
  const filas = document.querySelectorAll('#tabla_conductores tbody tr');
  let totalGeneral = 0;
  filas.forEach(fila => {
    const vehiculo = fila.getAttribute('data-vehiculo');
    const completos = parseInt(fila.cells[2].innerText)||0;
    const medios    = parseInt(fila.cells[3].innerText)||0;
    const extras    = parseInt(fila.cells[4].innerText)||0;
    const carro     = parseInt(fila.cells[5].innerText)||0;
    const t = tarifas[vehiculo] || {completo:0,medio:0,extra:0,carrotanque:0};
    const total = (completos*t.completo) + (medios*t.medio) + (extras*t.extra) + (carro*t.carrotanque);
    fila.querySelector('input.totales').value = formatNumber(total);
    totalGeneral += total;
  });
  document.getElementById('total_general').innerText = formatNumber(totalGeneral);
}

function limpiarPanel(){
  document.getElementById('tituloPanel').innerHTML = '🧳 Viajes';
  document.getElementById('contenidoPanel').innerHTML =
    '<p class="text-muted mb-0">Selecciona un conductor en la tabla para ver sus viajes aquí.</p>';
}
document.querySelectorAll('#tabla_conductores .conductor-link').forEach(td => {
  td.addEventListener('click', () => {
    const nombre = td.innerText.trim();
    const desde  = "<?= htmlspecialchars($desde) ?>";
    const hasta  = "<?= htmlspecialchars($hasta) ?>";
    const empresa= "<?= htmlspecialchars($empresaFiltro) ?>";

    document.getElementById('tituloPanel').innerHTML =
      `🚗 Viajes de <b>${nombre}</b> entre ${desde} y ${hasta}`;
    document.getElementById('contenidoPanel').innerHTML =
      "<p class='text-center text-muted mb-0'>Cargando viajes...</p>";

    fetch(`<?= basename(__FILE__) ?>?viajes_conductor=${encodeURIComponent(nombre)}&desde=${desde}&hasta=${hasta}&empresa=${encodeURIComponent(empresa)}`)
      .then(res => res.text())
      .then(html => { document.getElementById('contenidoPanel').innerHTML = html; })
      .catch(()  => { document.getElementById('contenidoPanel').innerHTML = "<p class='text-danger text-center'>Error al cargar los viajes.</p>"; });
  });
});

recalcular();
</script>
</body>
</html> 