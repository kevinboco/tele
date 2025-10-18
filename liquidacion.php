<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }

/* =======================================================
   🔹 Guardar tarifas por vehículo y empresa (AJAX)
   (MISMO ENDPOINT, MISMA LÓGICA)
======================================================= */
if (isset($_POST['guardar_tarifa'])) {
  $empresa  = $conn->real_escape_string($_POST['empresa']);
  $vehiculo = $conn->real_escape_string($_POST['tipo_vehiculo']);
  $campo    = $conn->real_escape_string($_POST['campo']);
  $valor    = (int)$_POST['valor'];
  $conn->query("INSERT IGNORE INTO tarifas (empresa, tipo_vehiculo) VALUES ('$empresa', '$vehiculo')");
  $sql = "UPDATE tarifas SET $campo = $valor WHERE empresa='$empresa' AND tipo_vehiculo='$vehiculo'";
  echo $conn->query($sql) ? "ok" : ("error: ".$conn->error);
  exit;
}

/* =======================================================
   🔹 Endpoint AJAX: viajes por conductor (MISMA LÓGICA)
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
                <th style='min-width:108px'>Fecha</th>
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
    echo "<div class='text-center text-muted py-3 mb-0'>Sin viajes en el rango.</div>";
  }
  exit;
}

/* =======================================================
   🔹 Formulario inicial (MISMA LÓGICA)
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
    <title>Liquidación — Nuevo UI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
      :root{ --radius:14px; --ink:#0b1220; --bg:#0b0f1a; --surface:#0e1424; --muted:#8a95ad; --ring:#1b2540; --brand:#6ea8fe; }
      body{font-family:'Inter',system-ui,Segoe UI,Roboto,Arial,sans-serif;background:var(--bg);color:#e8eefc}
      .glass{background:linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.02)); border:1px solid var(--ring); border-radius:var(--radius); box-shadow:0 10px 30px rgba(8,12,20,.55)}
      .label{color:var(--muted);font-size:.8rem}
    </style>
  </head>
  <body class="container py-5">
    <div class="glass p-4 mx-auto" style="max-width:700px">
      <h2 class="h4">🎛️ Liquidación — Selecciona rango</h2>
      <p class="label mb-4">Define periodo y (opcional) empresa</p>
      <form method="get" class="row g-3">
        <div class="col-12 col-sm-6">
          <label class="label">Desde</label>
          <input type="date" name="desde" class="form-control form-control-lg bg-dark text-light" required>
        </div>
        <div class="col-12 col-sm-6">
          <label class="label">Hasta</label>
          <input type="date" name="hasta" class="form-control form-control-lg bg-dark text-light" required>
        </div>
        <div class="col-12">
          <label class="label">Empresa</label>
          <select name="empresa" class="form-select form-select-lg bg-dark text-light">
            <option value="">— Todas —</option>
            <?php foreach($empresas as $e): ?>
              <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 d-grid d-sm-flex gap-2">
          <button class="btn btn-primary px-4" type="submit">Continuar</button>
          <button class="btn btn-outline-light" type="reset">Limpiar</button>
        </div>
      </form>
    </div>
  </body>
  </html>
  <?php exit; }

/* =======================================================
   🔹 Cálculo y armado de datos (MISMA LÓGICA)
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

$empresas = [];
$resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
if ($resEmp) while ($r = $resEmp->fetch_assoc()) $empresas[] = $r['empresa'];

$tarifas_guardadas = [];
$resTarifas = $conn->query("SELECT * FROM tarifas WHERE empresa='$empresaFiltro'");
if ($resTarifas) { while ($r = $resTarifas->fetch_assoc()) { $tarifas_guardadas[$r['tipo_vehiculo']] = $r; } }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Liquidación — NeoUI</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{
      --bg:#0b0f1a; --surface:#0e1424; --surface-2:#111937; --ring:#1b2540; --brand:#6ea8fe; --brand-2:#a6c8ff;
      --text:#e8eefc; --muted:#9fb1d4; --ok:#22c55e; --warn:#fbbf24; --danger:#ef4444; --chip:#0d1229; --radius:16px;
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{margin:0;font-family:'Inter',system-ui,Segoe UI,Roboto,Arial,sans-serif;background:var(--bg);color:var(--text)}

    /* Header ultra-compacto */
    .neo-header{position:sticky;top:0;z-index:1100;background:rgba(14,20,36,.8);backdrop-filter:blur(10px);border-bottom:1px solid var(--ring)}
    .neo-header .inner{display:grid;grid-template-columns:auto 1fr auto;gap:14px;align-items:center;min-height:56px}
    .title{display:flex;align-items:center;gap:10px}
    .title .brand-dot{width:10px;height:10px;border-radius:50%;background:linear-gradient(180deg,var(--brand),var(--brand-2));box-shadow:0 0 0 4px rgba(110,168,254,.15)}
    .sub{color:var(--muted);font-size:.8rem}

    /* Command bar (filtros) */
    .cmdbar{display:flex;flex-wrap:wrap;gap:8px}
    .cmdbar .form-control, .cmdbar .form-select{background:var(--surface-2);border-color:var(--ring);color:var(--text)}
    .cmdbar .btn{border-radius:10px}

    /* KPI cards */
    .kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:12px 0}
    @media(max-width:1200px){.kpis{grid-template-columns:repeat(2,1fr)}}
    .k{background:var(--surface);border:1px solid var(--ring);border-radius:var(--radius);padding:12px}
    .k .label{color:var(--muted);font-size:.78rem}
    .k .val{font-weight:800;font-size:1.2rem}

    /* Layout principal */
    .layout{display:grid;grid-template-columns: 1.1fr 2fr 1.1fr;gap:14px;align-items:start;padding:14px}
    @media(max-width:1200px){.layout{grid-template-columns:1fr}}
    .card-neo{background:var(--surface);border:1px solid var(--ring);border-radius:var(--radius);box-shadow:0 12px 30px rgba(0,0,0,.35)}
    .card-neo .hdr{padding:12px 14px;border-bottom:1px solid var(--ring);display:flex;align-items:center;justify-content:space-between}
    .card-neo .body{padding:12px 14px}

    .table-wrap{overflow:auto;border-radius:12px}
    table thead th{position:sticky;top:0;background:linear-gradient(180deg,#1a2550,#162144);color:#d9e7ff;border:none;text-transform:uppercase;font-size:.75rem}
    table tbody td{border-top:1px solid #1b2548}
    .badge{border-radius:999px}

    /* Panel de viajes fijo */
    #panelViajes{position:sticky;top:70px;max-height:calc(100vh - 90px);overflow:auto}

    /* Editor de tarifas como tarjetas */
    .tarifa-row{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:8px}
    .tarifa-row .input-group{background:var(--surface-2);border:1px solid var(--ring);border-radius:12px;overflow:hidden}
    .veh-pill{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;background:var(--chip);border:1px solid var(--ring)}

    /* FAB */
    .fab{position:fixed;right:18px;bottom:18px;display:flex;flex-direction:column;gap:10px;z-index:1200}
    .fab .btn{border-radius:999px;box-shadow:0 10px 24px rgba(0,0,0,.45)}
  </style>
</head>
<body>
  <!-- HEADER -->
  <header class="neo-header">
    <div class="container-fluid inner">
      <div class="title">
        <span class="brand-dot"></span>
        <div>
          <div class="fw-bold">Liquidación de Conductores</div>
          <div class="sub">Periodo <strong><?= htmlspecialchars($desde) ?></strong> → <strong><?= htmlspecialchars($hasta) ?></strong><?= $empresaFiltro!==''?" • Empresa: <strong>".htmlspecialchars($empresaFiltro)."</strong>":'' ?></div>
        </div>
      </div>
      <div></div>
      <form class="cmdbar" method="get">
        <input type="date" class="form-control form-control-sm" name="desde" value="<?= htmlspecialchars($desde) ?>" required>
        <input type="date" class="form-control form-control-sm" name="hasta" value="<?= htmlspecialchars($hasta) ?>" required>
        <select name="empresa" class="form-select form-select-sm" style="min-width:160px">
          <option value="">— Todas —</option>
          <?php foreach($empresas as $e): ?>
            <option value="<?= htmlspecialchars($e) ?>" <?= $empresaFiltro==$e?'selected':'' ?>><?= htmlspecialchars($e) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary btn-sm" type="submit">Aplicar</button>
        <a class="btn btn-outline-light btn-sm" href="<?= basename(__FILE__) ?>">Reiniciar</a>
      </form>
    </div>
  </header>

  <!-- KPIs -->
  <div class="container-fluid kpis">
    <div class="k"><div class="label">Conductores</div><div id="k_conductores" class="val">0</div></div>
    <div class="k"><div class="label">Viajes (total)</div><div id="k_viajes" class="val">0</div></div>
    <div class="k"><div class="label">Total General</div><div id="k_total" class="val">$ 0</div></div>
    <div class="k"><div class="label">Promedio / Conductor</div><div id="k_prom" class="val">$ 0</div></div>
  </div>

  <!-- LAYOUT 3 COLUMNAS -->
  <main class="layout container-fluid">
    <!-- 1) Tarifas -->
    <section class="card-neo">
      <div class="hdr"><span class="fw-semibold">⚙️ Tarifas por vehículo</span><span class="sub">Autoguarda y recalcula</span></div>
      <div class="body">
        <?php foreach ($vehiculos as $veh): $t = $tarifas_guardadas[$veh] ?? ["completo"=>0,"medio"=>0,"extra"=>0,"carrotanque"=>0]; ?>
          <div class="mb-3">
            <div class="veh-pill mb-2"><strong><?= htmlspecialchars($veh) ?></strong></div>
            <div class="tarifa-row">
              <?php if ($veh === 'Carrotanque'): ?>
                <div class="input-group input-group-sm">
                  <span class="input-group-text">Carrotanque $</span>
                  <input type="number" step="1000" min="0" value="<?= (int)$t['carrotanque'] ?>" class="form-control tarifa-input" oninput="recalcular()">
                </div>
                <div class="form-text text-secondary">Completo/Medio/Extra no aplican</div>
              <?php else: ?>
                <div class="input-group input-group-sm">
                  <span class="input-group-text">Completo $</span>
                  <input type="number" step="1000" min="0" value="<?= (int)$t['completo'] ?>" class="form-control tarifa-input" oninput="recalcular()">
                </div>
                <div class="input-group input-group-sm">
                  <span class="input-group-text">Medio $</span>
                  <input type="number" step="1000" min="0" value="<?= (int)$t['medio'] ?>" class="form-control tarifa-input" oninput="recalcular()">
                </div>
                <div class="input-group input-group-sm">
                  <span class="input-group-text">Extra $</span>
                  <input type="number" step="1000" min="0" value="<?= (int)$t['extra'] ?>" class="form-control tarifa-input" oninput="recalcular()">
                </div>
                <div class="input-group input-group-sm disabled">
                  <span class="input-group-text">Carrotanque</span>
                  <input class="form-control" value="—" disabled>
                </div>
              <?php endif; ?>
            </div>
          </div>
          <hr class="border-secondary-subtle">
        <?php endforeach; ?>
      </div>
    </section>

    <!-- 2) Resumen conductores -->
    <section class="card-neo">
      <div class="hdr"><span class="fw-semibold">🧑‍✈️ Resumen por Conductor</span><span class="sub">Haz clic para ver detalle</span></div>
      <div class="body table-wrap">
        <table id="tabla_conductores" class="table table-sm align-middle mb-0">
          <thead>
            <tr class="text-center">
              <th>Conductor</th>
              <th>Vehículo</th>
              <th>Comp.</th>
              <th>Med.</th>
              <th>Ext.</th>
              <th>Carro.</th>
              <th style="min-width:150px">Total</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($datos as $conductor => $viajes): ?>
              <tr data-vehiculo="<?= htmlspecialchars($viajes['vehiculo']) ?>">
                <td><a class="conductor-link" data-nombre="<?= htmlspecialchars($conductor) ?>"><?= htmlspecialchars($conductor) ?></a></td>
                <td><span class="badge text-bg-secondary"><?= htmlspecialchars($viajes['vehiculo']) ?></span></td>
                <td class="text-center fw-semibold"><?= (int)$viajes['completos'] ?></td>
                <td class="text-center fw-semibold"><?= (int)$viajes['medios'] ?></td>
                <td class="text-center fw-semibold"><?= (int)$viajes['extras'] ?></td>
                <td class="text-center fw-semibold"><?= (int)$viajes['carrotanques'] ?></td>
                <td><input type="text" class="form-control form-control-sm text-end totales" readonly></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- 3) Panel de viajes -->
    <aside class="card-neo" id="panelViajes">
      <div class="hdr"><span class="fw-semibold">🧳 Viajes del conductor</span><span class="sub">Carga bajo demanda</span></div>
      <div class="body" id="contenidoPanel"><div class="text-secondary">Selecciona un conductor…</div></div>
    </aside>
  </main>

  <!-- FAB acciones -->
  <div class="fab">
    <button class="btn btn-primary btn-lg" onclick="window.scrollTo({top:0,behavior:'smooth'})">⬆️</button>
    <button class="btn btn-outline-light btn-lg" onclick="exportarCSV()">⬇️ CSV</button>
  </div>

  <!-- Toasts -->
  <div class="position-fixed bottom-0 end-0 p-3" style="z-index:1300">
    <div id="toastOk" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex"><div class="toast-body">Tarifa guardada.</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>
    </div>
    <div id="toastErr" class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex"><div class="toast-body">Error al guardar tarifa.</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // ===== Helpers
    const money = n => (n||0).toLocaleString('es-CO');
    function getTarifas(){
      const tarifas = {};
      // Recorre tarjetas en orden; cada bloque tiene 4 input-group (según veh)
      document.querySelectorAll('.card-neo .body .veh-pill').forEach(pill=>{
        const cont = pill.parentElement; // bloque del vehículo
        const veh = pill.textContent.trim();
        const inputs = cont.querySelectorAll('.tarifa-row input');
        const vals = Array.from(inputs).map(i=>parseFloat(i.value)||0);
        // Mapeo dependiendo de si es Carrotanque
        if (/Carrotanque/i.test(veh)) {
          tarifas[veh] = {completo:0, medio:0, extra:0, carrotanque:(vals[0]||0)};
        } else {
          tarifas[veh] = {completo:(vals[0]||0), medio:(vals[1]||0), extra:(vals[2]||0), carrotanque:0};
        }
      });
      return tarifas;
    }

    function recalcular(){
      const tarifas = getTarifas();
      let totalGeneral = 0, totalViajes = 0, conductores = 0;
      document.querySelectorAll('#tabla_conductores tbody tr').forEach(row=>{
        conductores++;
        const veh = row.dataset.vehiculo;
        const c = parseInt(row.cells[2].innerText)||0;
        const m = parseInt(row.cells[3].innerText)||0;
        const e = parseInt(row.cells[4].innerText)||0;
        const ca = parseInt(row.cells[5].innerText)||0;
        totalViajes += (c+m+e+ca);
        const t = tarifas[veh] || {completo:0,medio:0,extra:0,carrotanque:0};
        const totalFila = c*t.completo + m*t.medio + e*t.extra + ca*t.carrotanque;
        row.querySelector('input.totales').value = money(totalFila);
        totalGeneral += totalFila;
      });
      // KPIs
      document.getElementById('k_conductores').innerText = conductores;
      document.getElementById('k_viajes').innerText = totalViajes;
      document.getElementById('k_total').innerText = '$ ' + money(totalGeneral);
      document.getElementById('k_prom').innerText = '$ ' + money(conductores? totalGeneral/conductores:0);
    }

    // Guardado inline con feedback
    document.querySelectorAll('.tarifa-input').forEach(input=>{
      input.addEventListener('change',()=>{
        const block = input.closest('.mb-3');
        const tipoVehiculo = block.querySelector('.veh-pill').textContent.trim();
        const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
        // Deducir campo por etiqueta
        const label = input.previousElementSibling?.innerText.toLowerCase() || '';
        let campo = 'completo';
        if (label.includes('medio')) campo = 'medio';
        else if (label.includes('extra')) campo = 'extra';
        else if (label.includes('carrotanque')) campo = 'carrotanque';
        const valor = parseInt(input.value)||0;

        fetch('<?= basename(__FILE__) ?>',{
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:new URLSearchParams({guardar_tarifa:1, empresa, tipo_vehiculo:tipoVehiculo, campo, valor})
        })
        .then(r=>r.text())
        .then(t=>{ if(t.trim()==='ok'){ new bootstrap.Toast(document.getElementById('toastOk')).show(); recalcular(); } else { console.error(t); new bootstrap.Toast(document.getElementById('toastErr')).show(); } })
        .catch(()=> new bootstrap.Toast(document.getElementById('toastErr')).show());
      });
    });

    // Cargar detalle de viajes
    function cargarViajes(nombre){
      const panel = document.getElementById('contenidoPanel');
      panel.innerHTML = '<div class="text-secondary">Cargando…</div>';
      const desde='<?= htmlspecialchars($desde) ?>', hasta='<?= htmlspecialchars($hasta) ?>', empresa='<?= htmlspecialchars($empresaFiltro) ?>';
      fetch('<?= basename(__FILE__) ?>?viajes_conductor='+encodeURIComponent(nombre)+'&desde='+desde+'&hasta='+hasta+'&empresa='+encodeURIComponent(empresa))
        .then(r=>r.text()).then(html=> panel.innerHTML = html)
        .catch(()=> panel.innerHTML = '<div class="text-danger">No se pudo cargar.</div>');
    }
    document.querySelectorAll('.conductor-link').forEach(a=> a.addEventListener('click',()=> cargarViajes(a.dataset.nombre)));

    // Export CSV rápido del resumen
    function exportarCSV(){
      const rows = [['Conductor','Vehículo','Completos','Medios','Extras','Carrotanques','Total']];
      document.querySelectorAll('#tabla_conductores tbody tr').forEach(r=>{
        rows.push([
          r.querySelector('.conductor-link').textContent.trim(),
          r.cells[1].innerText.trim(), r.cells[2].innerText.trim(), r.cells[3].innerText.trim(),
          r.cells[4].innerText.trim(), r.cells[5].innerText.trim(), r.querySelector('input.totales').value
        ]);
      });
      const csv = rows.map(a=>a.map(s=>`"${s.replaceAll('"','""')}"`).join(',')).join('
');
      const blob = new Blob([csv],{type:'text/csv;charset=utf-8;'});
      const url = URL.createObjectURL(blob);
      const a = Object.assign(document.createElement('a'), {href:url, download:'liquidacion_resumen.csv'});
      document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
    }
    window.exportarCSV = exportarCSV;

    // Primera pintura
    recalcular();
  </script>
</body>
</html>
