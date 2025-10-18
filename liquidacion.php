<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }

/* =======================================================
   🔹 Guardar tarifas por vehículo y empresa (AJAX)
======================================================= */
if (isset($_POST['guardar_tarifa'])) {
  $empresa  = $conn->real_escape_string($_POST['empresa']);
  $vehiculo = $conn->real_escape_string($_POST['tipo_vehiculo']);
  $campo    = $conn->real_escape_string($_POST['campo']);
  $valor    = (int)$_POST['valor'];

  // Crear registro si no existe
  $conn->query("INSERT IGNORE INTO tarifas (empresa, tipo_vehiculo) VALUES ('$empresa', '$vehiculo')");

  // Actualizar el valor específico
  $sql = "UPDATE tarifas SET $campo = $valor WHERE empresa='$empresa' AND tipo_vehiculo='$vehiculo'";
  if ($conn->query($sql)) { echo "ok"; } else { echo "error: " . $conn->error; }
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
    echo "<div class='table-responsive'><table class='table table-sm table-hover align-middle mb-0'>
            <thead class='table-light sticky-top'>
              <tr class='text-center small text-uppercase'>
                <th style='min-width:110px'>Fecha</th>
                <th>Ruta</th>
                <th>Empresa</th>
                <th>Vehículo</th>
              </tr>
            </thead>
            <tbody>";
    while ($r = $res->fetch_assoc()) {
      echo "<tr>
              <td class='text-center'>".htmlspecialchars($r['fecha'])."</td>
              <td>".htmlspecialchars($r['ruta'])."</td>
              <td class='text-center'>".htmlspecialchars($r['empresa'])."</td>
              <td class='text-center'><span class='badge text-bg-secondary'>".htmlspecialchars($r['tipo_vehiculo'])."</span></td>
            </tr>";
    }
    echo "</tbody></table></div>";
  } else {
    echo "<div class='text-center text-muted py-3 mb-0'>No se encontraron viajes para este conductor en ese rango.</div>";
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
  <!DOCTYPE html>
  <html lang="es">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Liquidación de Conductores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
      :root{ --radius:14px; }
      body{font-family:'Segoe UI',sans-serif;background:#f6f7fb}
      .glass{background:rgba(255,255,255,.9);backdrop-filter:blur(6px);border:1px solid #eef1f6;border-radius:var(--radius);box-shadow:0 10px 30px rgba(16,24,40,.06)}
    </style>
  </head>
  <body class="container py-5">
    <div class="glass p-4 mx-auto" style="max-width:680px">
      <div class="d-flex align-items-center justify-content-between">
        <h2 class="h4 mb-0">📅 Filtrar viajes por rango</h2>
        <span class="badge text-bg-primary">ATZN Wuinpumuin</span>
      </div>
      <hr class="my-3">
      <form method="get" class="row g-3">
        <div class="col-12 col-sm-6">
          <label class="form-label small">Desde</label>
          <input type="date" name="desde" class="form-control" required>
        </div>
        <div class="col-12 col-sm-6">
          <label class="form-label small">Hasta</label>
          <input type="date" name="hasta" class="form-control" required>
        </div>
        <div class="col-12">
          <label class="form-label small">Empresa</label>
          <select name="empresa" class="form-select">
            <option value="">— Todas —</option>
            <?php foreach($empresas as $e): ?>
              <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 d-grid d-sm-flex gap-2">
          <button class="btn btn-primary px-4" type="submit">Aplicar filtros</button>
          <button class="btn btn-outline-secondary" type="reset">Limpiar</button>
        </div>
      </form>
    </div>
  </body>
  </html>
  <?php
  exit;
}

/* =======================================================
   🔹 Cálculo y armado de tablas (misma lógica)
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
  while ($r = $resTarifas->fetch_assoc()) { $tarifas_guardadas[$r['tipo_vehiculo']] = $r; }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Liquidación de Conductores</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{
      --gap:18px; --box-radius:14px; --card: #ffffff; --bg:#f5f7fb; --ink:#1f2937;
      --muted:#6b7280; --ring:#e5e7eb; --brand:#0d6efd; --chip:#e9f2ff;
    }
    *{box-sizing:border-box}
    body{font-family:'Segoe UI',system-ui,-apple-system,Roboto,Arial,sans-serif;background:var(--bg);color:var(--ink)}
    .page{padding:18px}
    .toolbar{position:sticky;top:0;z-index:1020;background:rgba(245,247,251,.85);backdrop-filter:blur(6px);border-bottom:1px solid var(--ring)}
    .toolbar .inner{display:grid;grid-template-columns:1fr auto;gap:12px;align-items:center}
    .toolbar-title small{color:var(--muted)}
    .layout{display:grid;grid-template-columns: 1.2fr 2fr 1.2fr;gap:var(--gap);align-items:start;margin-top:16px}
    @media (max-width:1200px){ .layout{grid-template-columns:1fr} }

    .box{background:var(--card);border:1px solid var(--ring);border-radius:var(--box-radius);box-shadow:0 8px 24px rgba(16,24,40,.06)}
    .box-header{padding:14px 16px;border-bottom:1px solid var(--ring);display:flex;align-items:center;justify-content:space-between}
    .box-body{padding:12px 16px}

    /* Tabla de tarifas */
    #tabla_tarifas thead th{position:sticky;top:0;background:#0d6efd;color:#fff;text-transform:uppercase;font-size:.78rem;letter-spacing:.02em}
    #tabla_tarifas tbody td{vertical-align:middle}
    .tarifa-input{max-width:150px;text-align:right}
    .help small{color:var(--muted)}

    /* Resumen conductores */
    #tabla_conductores thead th{position:sticky;top:0;background:#0d6efd;color:#fff;text-transform:uppercase;font-size:.78rem}
    #tabla_conductores td, #tabla_conductores th{white-space:nowrap}
    .conductor-link{cursor:pointer;color:var(--brand);text-decoration:none}
    .conductor-link:hover{text-decoration:underline}
    .chip-total{display:inline-flex;gap:8px;align-items:center;padding:6px 12px;border-radius:999px;background:var(--chip);border:1px solid #d6e6ff;font-weight:700}

    /* Panel de viajes */
    #panelViajes{position:sticky;top:78px;max-height:calc(100vh - 100px);overflow:auto}
    .skeleton{background:linear-gradient(90deg,#f2f4f7 25%,#eaecf0 37%,#f2f4f7 63%);animation:sheen 1.2s infinite;min-height:120px;border-radius:12px}
    @keyframes sheen {0%{background-position:-200px 0} 100%{background-position:calc(200px + 100%) 0}}

    /* Utilidades */
    .shadow-soft{box-shadow:0 10px 24px rgba(0,0,0,.06)}
    .table-wrap{overflow:auto;border-radius:12px}
    .table thead th{border:none}
    .table td{border-top:1px solid #f1f3f6}
  </style>
</head>
<body>
  <div class="page">
    <!-- Toolbar superior -->
    <div class="toolbar py-2 px-3">
      <div class="inner container-fluid">
        <div class="toolbar-title">
          <div class="d-flex align-items-center gap-2">
            <h1 class="h5 mb-0">🪙 Liquidación de Conductores</h1>
            <span class="badge text-bg-secondary">ATZN Wuinpumuin</span>
          </div>
          <small>Periodo: <strong><?= htmlspecialchars($desde) ?></strong> a <strong><?= htmlspecialchars($hasta) ?></strong> <?= $empresaFiltro!==""?" • Empresa: <strong>".htmlspecialchars($empresaFiltro)."</strong>":"" ?></small>
        </div>
        <form class="d-flex flex-wrap gap-2 align-items-center" method="get">
          <input type="date" class="form-control form-control-sm" name="desde" value="<?= htmlspecialchars($desde) ?>" required>
          <input type="date" class="form-control form-control-sm" name="hasta" value="<?= htmlspecialchars($hasta) ?>" required>
          <select name="empresa" class="form-select form-select-sm" style="min-width:180px">
            <option value="">— Todas —</option>
            <?php foreach($empresas as $e): ?>
              <option value="<?= htmlspecialchars($e) ?>" <?= $empresaFiltro==$e?'selected':'' ?>><?= htmlspecialchars($e) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
          <a class="btn btn-outline-secondary btn-sm" href="<?= basename(__FILE__) ?>">Reiniciar</a>
        </form>
      </div>
    </div>

    <!-- Layout 3 columnas -->
    <div class="layout container-fluid mt-3">
      <!-- Col 1: Tarifas -->
      <section class="box shadow-soft">
        <div class="box-header">
          <h2 class="h6 mb-0">🚐 Tarifas por tipo de vehículo</h2>
          <div class="help"><small>Los cambios se guardan y recalculan al instante</small></div>
        </div>
        <div class="box-body table-wrap">
          <table id="tabla_tarifas" class="table table-sm align-middle mb-0">
            <thead>
              <tr class="text-center">
                <th>Tipo de Vehículo</th>
                <th>Completo</th>
                <th>Medio</th>
                <th>Extra</th>
                <th>Carrotanque</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($vehiculos as $veh): $t = $tarifas_guardadas[$veh] ?? ["completo"=>0,"medio"=>0,"extra"=>0,"carrotanque"=>0]; ?>
                <tr>
                  <td class="fw-semibold"><?= htmlspecialchars($veh) ?></td>
                  <?php if ($veh === "Carrotanque"): ?>
                    <td class="text-center text-muted">—</td>
                    <td class="text-center text-muted">—</td>
                    <td class="text-center text-muted">—</td>
                    <td>
                      <div class="input-group input-group-sm">
                        <span class="input-group-text">$</span>
                        <input type="number" step="1000" min="0" value="<?= (int)$t['carrotanque'] ?>" class="form-control tarifa-input" inputmode="numeric" oninput="recalcular()">
                      </div>
                    </td>
                  <?php else: ?>
                    <?php $valC = (int)$t['completo']; $valM=(int)$t['medio']; $valE=(int)$t['extra']; ?>
                    <td>
                      <div class="input-group input-group-sm">
                        <span class="input-group-text">$</span>
                        <input type="number" step="1000" min="0" value="<?= $valC ?>" class="form-control tarifa-input" oninput="recalcular()">
                      </div>
                    </td>
                    <td>
                      <div class="input-group input-group-sm">
                        <span class="input-group-text">$</span>
                        <input type="number" step="1000" min="0" value="<?= $valM ?>" class="form-control tarifa-input" oninput="recalcular()">
                      </div>
                    </td>
                    <td>
                      <div class="input-group input-group-sm">
                        <span class="input-group-text">$</span>
                        <input type="number" step="1000" min="0" value="<?= $valE ?>" class="form-control tarifa-input" oninput="recalcular()">
                      </div>
                    </td>
                    <td class="text-center text-muted">—</td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Col 2: Resumen Conductores -->
      <section class="box shadow-soft">
        <div class="box-header">
          <h2 class="h6 mb-0">🧑‍✈️ Resumen por Conductor</h2>
          <div class="chip-total small">🔢 Total General: <span id="total_general" class="ms-1">0</span></div>
        </div>
        <div class="box-body table-wrap">
          <table id="tabla_conductores" class="table table-sm align-middle mb-0">
            <thead>
              <tr class="text-center">
                <th>Conductor</th>
                <th>Vehículo</th>
                <th title="Viajes completos">Comp.</th>
                <th title="Viajes medios">Med.</th>
                <th title="Viajes extra">Ext.</th>
                <th>Carro.</th>
                <th style="min-width:140px">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($datos as $conductor => $viajes): ?>
                <tr data-vehiculo="<?= htmlspecialchars($viajes['vehiculo']) ?>">
                  <td>
                    <a class="conductor-link" data-nombre="<?= htmlspecialchars($conductor) ?>"><?= htmlspecialchars($conductor) ?></a>
                  </td>
                  <td><span class="badge text-bg-secondary"><?= htmlspecialchars($viajes['vehiculo']) ?></span></td>
                  <td class="text-center fw-semibold"><?= (int)$viajes['completos'] ?></td>
                  <td class="text-center fw-semibold"><?= (int)$viajes['medios'] ?></td>
                  <td class="text-center fw-semibold"><?= (int)$viajes['extras'] ?></td>
                  <td class="text-center fw-semibold"><?= (int)$viajes['carrotanques'] ?></td>
                  <td>
                    <input type="text" class="form-control form-control-sm text-end totales" readonly>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Col 3: Panel de viajes -->
      <aside class="box shadow-soft" id="panelViajes">
        <div class="box-header">
          <h2 class="h6 mb-0">🧳 Viajes del conductor</h2>
          <span class="text-muted small">Haz clic en un nombre</span>
        </div>
        <div class="box-body" id="contenidoPanel">
          <div class="skeleton w-100"></div>
        </div>
      </aside>
    </div>
  </div>

  <!-- Toasts -->
  <div class="position-fixed bottom-0 end-0 p-3" style="z-index:1080">
    <div id="toastOk" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body">Tarifa guardada y totales actualizados.</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>
    <div id="toastErr" class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body">No se pudo guardar la tarifa. Revisa la conexión.</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // ===== Helpers =====
    function formatNumber(num){ return (num||0).toLocaleString('es-CO'); }

    function getTarifas(){
      const tarifas = {};
      document.querySelectorAll('#tabla_tarifas tbody tr').forEach(row=>{
        const veh = row.cells[0].innerText.trim();
        const completo = row.cells[1]?.querySelector('input') ? parseFloat(row.cells[1].querySelector('input').value)||0 : 0;
        const medio    = row.cells[2]?.querySelector('input') ? parseFloat(row.cells[2].querySelector('input').value)||0 : 0;
        const extra    = row.cells[3]?.querySelector('input') ? parseFloat(row.cells[3].querySelector('input').value)||0 : 0;
        const carro    = row.cells[4]?.querySelector('input') ? parseFloat(row.cells[4].querySelector('input').value)||0 : 0;
        tarifas[veh] = {completo, medio, extra, carrotanque: carro};
      });
      return tarifas;
    }

    function recalcular(){
      const tarifas = getTarifas();
      const filas = document.querySelectorAll('#tabla_conductores tbody tr');
      let totalGeneral = 0;
      filas.forEach(f=>{
        const veh = f.dataset.vehiculo;
        const c = parseInt(f.cells[2].innerText)||0;
        const m = parseInt(f.cells[3].innerText)||0;
        const e = parseInt(f.cells[4].innerText)||0;
        const ca = parseInt(f.cells[5].innerText)||0;
        const t = tarifas[veh] || {completo:0,medio:0,extra:0,carrotanque:0};
        const totalFila = c*t.completo + m*t.medio + e*t.extra + ca*t.carrotanque;
        const inputTotal = f.querySelector('input.totales');
        if (inputTotal) inputTotal.value = formatNumber(totalFila);
        totalGeneral += totalFila;
      });
      document.getElementById('total_general').innerText = formatNumber(totalGeneral);
    }

    // ===== Guardar tarifa on-change con feedback =====
    document.querySelectorAll('#tabla_tarifas input').forEach(input=>{
      input.addEventListener('change',()=>{
        const fila = input.closest('tr');
        const tipoVehiculo = fila.cells[0].innerText.trim();
        const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
        const idx = Array.from(fila.cells).findIndex(c=>c.contains(input));
        const campos = ['completo','medio','extra','carrotanque'];
        const campo = (idx===1? 'completo' : idx===2? 'medio' : idx===3? 'extra' : 'carrotanque');
        const valor = parseInt(input.value)||0;

        fetch('<?= basename(__FILE__) ?>',{
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:new URLSearchParams({guardar_tarifa:1, empresa, tipo_vehiculo:tipoVehiculo, campo, valor})
        })
        .then(r=>r.text())
        .then(t=>{
          if (t.trim()==='ok') { new bootstrap.Toast(document.getElementById('toastOk')).show(); recalcular(); }
          else { console.error('Error guardando tarifa:', t); new bootstrap.Toast(document.getElementById('toastErr')).show(); }
        })
        .catch(()=> new bootstrap.Toast(document.getElementById('toastErr')).show());
      });
    });

    // ===== Cargar viajes en el panel lateral =====
    function cargarViajes(nombre){
      const panel = document.getElementById('contenidoPanel');
      panel.innerHTML = '<div class="skeleton w-100"></div>';
      const desde = '<?= htmlspecialchars($desde) ?>';
      const hasta = '<?= htmlspecialchars($hasta) ?>';
      const empresa = '<?= htmlspecialchars($empresaFiltro) ?>';
      fetch('<?= basename(__FILE__) ?>?viajes_conductor='+encodeURIComponent(nombre)+'&desde='+desde+'&hasta='+hasta+'&empresa='+encodeURIComponent(empresa))
        .then(r=>r.text())
        .then(html=>{ panel.innerHTML = html; })
        .catch(()=>{ panel.innerHTML = '<div class="text-danger">No se pudieron cargar los viajes.</div>'; });
    }

    document.querySelectorAll('.conductor-link').forEach(a=>{
      a.addEventListener('click',()=> cargarViajes(a.dataset.nombre));
    });

    // Primera carga: pinta esqueleto y calcula totales
    document.getElementById('contenidoPanel').innerHTML = '<div class="text-muted">Selecciona un conductor para ver sus viajes aquí.</div>';
    recalcular();
  </script>
</body>
</html>
