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
    $empresa  = $conn->real_escape_string($_POST['empresa']);
    $vehiculo = $conn->real_escape_string($_POST['tipo_vehiculo']);
    $campo    = $conn->real_escape_string($_POST['campo']);
    $valor    = (int)$_POST['valor'];

    $conn->query("INSERT IGNORE INTO tarifas (empresa, tipo_vehiculo) VALUES ('$empresa', '$vehiculo')");
    $sql = "UPDATE tarifas SET $campo = $valor WHERE empresa='$empresa' AND tipo_vehiculo='$vehiculo'";
    echo $conn->query($sql) ? "ok" : "error: " . $conn->error;
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
        echo "</tbody></table>";
    } else {
        echo "<p class='text-center text-muted mb-0'>No se encontraron viajes para este conductor en ese rango.</p>";
    }
    exit;
}

/* =======================================================
   🔹 Formulario inicial
======================================================= */
if (!isset($_GET['desde']) || !isset($_GET['hasta'])) {
    $empresas = [];
    $resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
    if ($resEmp) while ($r = $resEmp->fetch_assoc()) $empresas[] = $r['empresa'];
    ?>
    <style>
      body{font-family:'Inter','Segoe UI',sans-serif;background:#f8fafc;color:#0f172a;padding:40px}
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

/* Empresas y tarifas */
$empresas = [];
$resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
if ($resEmp) while ($r = $resEmp->fetch_assoc()) $empresas[] = $r['empresa'];

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
<script src="https://cdn.tailwindcss.com"></script>
<style>
  :root{ --gap:18px; --box-radius:16px; }
  body{font-family:'Inter','Segoe UI',sans-serif;background:#f1f5f9;color:#0f172a;padding:22px}
  .layout{display:grid;grid-template-columns:1fr 2fr 1.2fr;gap:var(--gap);align-items:start;}
  @media (max-width:1200px){.layout{grid-template-columns:1fr;}}
  .box{border-radius:var(--box-radius);background:#fff;border:1px solid #e5e7eb}
  table{background:#fff;border-radius:12px;overflow:hidden}
  th{background:#0d6efd;color:#fff;text-align:center;padding:10px}
  td{text-align:center;padding:8px;border-bottom:1px solid #eef2f6}
  input[readonly]{text-align:right}
  .conductor-link{cursor:pointer;color:#0d6efd;text-decoration:underline;}
  .total-chip{display:inline-block;padding:6px 12px;border-radius:999px;background:#eef2ff;color:#1d4ed8;font-weight:700;border:1px solid #dbe7ff;margin-bottom:8px;float:right;}
</style>
</head>
<body>

<!-- Header -->
<div class="box p-4 mb-4 shadow-sm">
  <h2 class="text-center m-0 font-extrabold text-slate-900">🪙 Liquidación de Conductores</h2>
  <p class="text-center text-slate-600 mt-1">
    Periodo: <strong><?= htmlspecialchars($desde) ?></strong> — <strong><?= htmlspecialchars($hasta) ?></strong>
    <?php if ($empresaFiltro !== ""): ?> • Empresa: <strong><?= htmlspecialchars($empresaFiltro) ?></strong><?php endif; ?>
  </p>
</div>

<div class="layout">
  <!-- ========== PRIMERA COLUMNA: TARIFAS (Tailwind) ========== -->
  <section class="box p-5 shadow-md">
    <!-- Título -->
    <div class="flex items-center gap-2 mb-4">
      <span class="text-2xl">🚐</span>
      <h3 class="text-xl md:text-2xl font-extrabold text-slate-900 m-0">Tarifas por vehículo</h3>
    </div>

    <!-- Tarjetas -->
    <div id="tarifas_cards" class="flex flex-col gap-5">
      <?php foreach ($vehiculos as $veh):
        $t = $tarifas_guardadas[$veh] ?? ["completo"=>0,"medio"=>0,"extra"=>0,"carrotanque"=>0];
        if ($veh === "Carrotanque"): ?>
          <!-- Carrotanque -->
          <div class="rounded-2xl ring-1 ring-slate-200 bg-white shadow-md p-6" data-vehiculo="<?= htmlspecialchars($veh) ?>">
            <span class="inline-flex items-center rounded-2xl bg-slate-100 ring-1 ring-white/70 text-slate-900 font-extrabold px-4 py-2 mb-4 shadow-inner">
              Carrotanque
            </span>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
              <div class="text-slate-600 self-center">Carrotanque</div>
              <input type="number" step="1000"
                     class="tw-tarifa w-full text-right font-extrabold text-slate-900 text-2xl md:text-3xl rounded-xl border border-slate-200 bg-white px-4 py-3 focus:outline-none focus:ring-4 focus:ring-violet-500/20 focus:border-violet-500"
                     data-campo="carrotanque" value="<?= (int)$t['carrotanque'] ?>">
            </div>
          </div>
        <?php else: ?>
          <!-- Otros vehículos -->
          <div class="rounded-2xl ring-1 ring-slate-200 bg-white shadow-md p-6" data-vehiculo="<?= htmlspecialchars($veh) ?>">
            <span class="inline-flex items-center rounded-2xl bg-slate-100 ring-1 ring-white/70 text-slate-900 font-extrabold px-4 py-2 mb-4 shadow-inner">
              <?= htmlspecialchars($veh) ?>
            </span>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
              <div>
                <div class="text-slate-600 font-semibold mb-1">Completo</div>
                <input type="number" step="1000"
                       class="tw-tarifa w-full text-right font-extrabold text-slate-900 text-2xl rounded-xl border border-slate-200 bg-white px-4 py-3 focus:outline-none focus:ring-4 focus:ring-violet-500/20 focus:border-violet-500"
                       data-campo="completo" value="<?= (int)$t['completo'] ?>">
              </div>
              <div>
                <div class="text-slate-600 font-semibold mb-1">Medio</div>
                <input type="number" step="1000"
                       class="tw-tarifa w-full text-right font-extrabold text-slate-900 text-2xl rounded-xl border border-slate-200 bg-white px-4 py-3 focus:outline-none focus:ring-4 focus:ring-violet-500/20 focus:border-violet-500"
                       data-campo="medio" value="<?= (int)$t['medio'] ?>">
              </div>
              <div>
                <div class="text-slate-600 font-semibold mb-1">Extra</div>
                <input type="number" step="1000"
                       class="tw-tarifa w-full text-right font-extrabold text-slate-900 text-2xl rounded-xl border border-slate-200 bg-white px-4 py-3 focus:outline-none focus:ring-4 focus:ring-violet-500/20 focus:border-violet-500"
                       data-campo="extra" value="<?= (int)$t['extra'] ?>">
              </div>
            </div>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <!-- Filtro (se mantiene con Bootstrap, pero le di un encabezado Tailwind para look) -->
    <div class="rounded-2xl ring-1 ring-slate-200 bg-white shadow-md p-5 mt-6">
      <div class="flex items-center gap-2 mb-3">
        <span>🗓️</span><h5 class="m-0 text-lg md:text-xl font-extrabold text-slate-900">Filtro de Liquidación</h5>
      </div>
      <form class="row g-3 justify-content-center" method="get">
        <div class="col-md-3">
          <label class="form-label mb-1">Desde:</label>
          <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="form-control" required>
        </div>
        <div class="col-md-3">
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
    </div>
  </section>

  <!-- ========== SEGUNDA COLUMNA: RESUMEN ========== -->
  <section class="box p-4 shadow-sm">
    <h3 class="text-center m-0">🧑‍✈️ Resumen por Conductor
      <span id="total_chip_container" class="total-chip">🔢 Total General: <span id="total_general">0</span></span>
    </h3>

    <table id="tabla_conductores" class="table mt-3">
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

  <!-- ========== TERCERA COLUMNA: PANEL ========== -->
  <aside class="box p-4 shadow-sm" id="panelViajes">
    <h4 class="m-0">🧳 Viajes</h4>
    <div id="contenidoPanel" class="mt-2"><p class="text-muted mb-0">Selecciona un conductor para ver sus viajes aquí.</p></div>
  </aside>
</div>

<script>
/* ======= Lee tarifas desde las TARJETAS Tailwind ======= */
function getTarifas(){
  const tarifas = {};
  document.querySelectorAll('#tarifas_cards [data-vehiculo]').forEach(card=>{
    const veh = card.getAttribute('data-vehiculo').trim();
    tarifas[veh] = {completo:0, medio:0, extra:0, carrotanque:0};
    card.querySelectorAll('input.tw-tarifa').forEach(inp=>{
      const campo = inp.dataset.campo;
      const val = parseFloat(inp.value)||0;
      tarifas[veh][campo] = val;
    });
  });
  return tarifas;
}
function formatNumber(num){return (num||0).toLocaleString('es-CO');}
function recalcular(){
  const tarifas = getTarifas();
  const filas = document.querySelectorAll('#tabla_conductores tbody tr');
  let totalGeneral = 0;
  filas.forEach(f=>{
    const veh=f.dataset.vehiculo;
    const c=parseInt(f.cells[2].innerText)||0;
    const m=parseInt(f.cells[3].innerText)||0;
    const e=parseInt(f.cells[4].innerText)||0;
    const ca=parseInt(f.cells[5].innerText)||0;
    const t=tarifas[veh]||{completo:0,medio:0,extra:0,carrotanque:0};
    const totalFila=c*t.completo+m*t.medio+e*t.extra+ca*t.carrotanque;
    const inputTotal=f.querySelector('input.totales');
    if(inputTotal) inputTotal.value=formatNumber(totalFila);
    totalGeneral+=totalFila;
  });
  document.getElementById('total_general').innerText=formatNumber(totalGeneral);
}

/* Guardado AJAX (sin cambios de lógica) */
document.querySelectorAll('#tarifas_cards input.tw-tarifa').forEach(input=>{
  input.addEventListener('change',()=>{
    const card = input.closest('[data-vehiculo]');
    const tipoVehiculo = card.getAttribute('data-vehiculo').trim();
    const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
    const campo = input.dataset.campo;
    const valor = parseInt(input.value)||0;

    fetch(`<?= basename(__FILE__) ?>`,{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:new URLSearchParams({guardar_tarifa:1,empresa,tipo_vehiculo:tipoVehiculo,campo,valor})
    })
    .then(r=>r.text())
    .then(t=>{
      if(t.trim()!=='ok') console.error('Error guardando tarifa:',t);
      recalcular();
    });
  });
});

/* Panel de viajes */
document.querySelectorAll('.conductor-link').forEach(td=>{
  td.addEventListener('click',()=>{
    const nombre=td.innerText.trim();
    const desde="<?= htmlspecialchars($desde) ?>";
    const hasta="<?= htmlspecialchars($hasta) ?>";
    const empresa="<?= htmlspecialchars($empresaFiltro) ?>";
    document.getElementById('contenidoPanel').innerHTML="<p class='text-center'>Cargando...</p>";
    fetch(`<?= basename(__FILE__) ?>?viajes_conductor=${encodeURIComponent(nombre)}&desde=${desde}&hasta=${hasta}&empresa=${encodeURIComponent(empresa)}`)
      .then(r=>r.text())
      .then(html=>{document.getElementById('contenidoPanel').innerHTML=html;});
  });
});

recalcular();
</script>
</body>
</html>
