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
<style>
  :root{ --gap:18px; --box-radius:14px; }
  body{font-family:'Segoe UI',sans-serif;background:#eef2f6;color:#333;padding:20px}
  .page-title{background:#fff;border-radius:var(--box-radius);padding:12px 16px;margin-bottom:var(--gap);box-shadow:0 2px 8px rgba(0,0,0,.05);text-align:center;}
  .layout{display:grid;grid-template-columns:1fr 2fr 1.2fr;gap:var(--gap);align-items:start;}
  @media (max-width:1200px){.layout{grid-template-columns:1fr;}}
  .box{border-radius:var(--box-radius);box-shadow:0 2px 10px rgba(0,0,0,.06);padding:14px;background:#fff;}
  table{background:#fff;border-radius:10px;overflow:hidden}
  th{background:#0d6efd;color:#fff;text-align:center;padding:10px}
  td{text-align:center;padding:8px;border-bottom:1px solid #eee}
  input[type=number],input[readonly]{width:100%;max-width:160px;padding:6px;border:1px solid #ced4da;border-radius:8px;text-align:right}
  .conductor-link{cursor:pointer;color:#0d6efd;text-decoration:underline;}
  .total-chip{display:inline-block;padding:6px 12px;border-radius:999px;background:#e9f2ff;color:#0d6efd;font-weight:700;border:1px solid #d6e6ff;margin-bottom:8px;float:right;}
  form .form-label{font-weight:600;color:#333;}
  form input,form select{border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,0.05);}
  form button{border-radius:10px;}

  /* ======== NUEVO: tarjetas de tarifas (estilo imagen) ======== */
  .tarifas-header{display:flex;align-items:center;gap:.6rem;margin-bottom:.8rem}
  .tarifas-header h3{margin:0;font-weight:700}
  .tarifas-grid{display:flex;flex-direction:column;gap:18px}
  .vehiculo-card{border:1px solid #e9edf3;background:#fff;border-radius:16px;padding:18px 20px;box-shadow:0 4px 16px rgba(15,23,42,.06)}
  .vehiculo-title{display:inline-block;font-weight:800;background:#eef2f7;border:1px solid #dde6f1;color:#0f172a;border-radius:12px;padding:6px 14px;margin-bottom:10px;font-size:1.15rem}
  .vehiculo-sub{color:#6b7280;margin-bottom:10px}
  .tarifa-rows{display:grid;grid-template-columns:repeat(3,1fr);gap:28px}
  .tarifa-item{display:flex;flex-direction:column;gap:4px}
  .tarifa-label{font-weight:600;color:#6b7280}
  .tarifa-input{border:none;border-bottom:2px solid #eef2f6;border-radius:0;background:transparent;padding:4px 0;font-size:1.8rem;font-weight:800;color:#0f172a;outline:none;text-align:left}
  .tarifa-input:focus{border-bottom-color:#7c3aed}
  /* Carrotanque: una sola fila */
  .carro-line{display:flex;justify-content:space-between;align-items:center}
  .carro-name{font-size:1.05rem;color:#374151}
  .carro-input{min-width:180px;text-align:right}
  @media (max-width:700px){
    .tarifa-rows{grid-template-columns:1fr}
    .carro-line{flex-direction:column;align-items:flex-start;gap:8px}
    .carro-input{min-width:0;width:100%}
  }
</style>
</head>
<body>

<div class="page-title">
  <h2>🪙 Liquidación de Conductores</h2>
  <div class="header-sub">
    Periodo: <strong><?= htmlspecialchars($desde) ?></strong> hasta <strong><?= htmlspecialchars($hasta) ?></strong>
    <?php if ($empresaFiltro !== ""): ?>
      &nbsp;•&nbsp; Empresa: <strong><?= htmlspecialchars($empresaFiltro) ?></strong>
    <?php endif; ?>
  </div>
</div>

<div class="layout">
  <section class="box">
    <!-- ======= TÍTULO similar a la imagen ======= -->
    <div class="tarifas-header">
      <span style="font-size:1.4rem">🚐</span>
      <h3 class="m-0">Tarifas por vehículo</h3>
    </div>

    <!-- ======= TARJETAS DE TARIFAS (reemplaza la tabla) ======= -->
    <div id="tarifas_cards" class="tarifas-grid">
      <?php foreach ($vehiculos as $veh):
        $t = $tarifas_guardadas[$veh] ?? ["completo"=>0,"medio"=>0,"extra"=>0,"carrotanque"=>0];
        if ($veh === "Carrotanque"): ?>
          <div class="vehiculo-card" data-vehiculo="<?= htmlspecialchars($veh) ?>">
            <span class="vehiculo-title">Carrotanque</span>
            <div class="carro-line">
              <div class="carro-name">Carrotanque</div>
              <input type="number" step="1000" class="tarifa-input carro-input"
                     data-campo="carrotanque" value="<?= (int)$t['carrotanque'] ?>">
            </div>
          </div>
        <?php else: ?>
          <div class="vehiculo-card" data-vehiculo="<?= htmlspecialchars($veh) ?>">
            <span class="vehiculo-title"><?= htmlspecialchars($veh) ?></span>
            <div class="tarifa-rows">
              <div class="tarifa-item">
                <span class="tarifa-label">Completo</span>
                <input type="number" step="1000" class="tarifa-input"
                       data-campo="completo" value="<?= (int)$t['completo'] ?>">
              </div>
              <div class="tarifa-item">
                <span class="tarifa-label">Medio</span>
                <input type="number" step="1000" class="tarifa-input"
                       data-campo="medio" value="<?= (int)$t['medio'] ?>">
              </div>
              <div class="tarifa-item">
                <span class="tarifa-label">Extra</span>
                <input type="number" step="1000" class="tarifa-input"
                       data-campo="extra" value="<?= (int)$t['extra'] ?>">
              </div>
            </div>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <!-- ======= Filtro (se mantiene igual) ======= -->
    <section class="box mt-3">
      <h5 class="text-center mb-3">📅 Filtro de Liquidación</h5>
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
    </section>
  </section>

  <section class="box">
    <h3 class="text-center">🧑‍✈️ Resumen por Conductor
      <span id="total_chip_container" class="total-chip">🔢 Total General: <span id="total_general">0</span></span>
    </h3>

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
/* ======= Adaptado para las TARJETAS ======= */
function getTarifas(){
  const tarifas = {};
  document.querySelectorAll('#tarifas_cards .vehiculo-card').forEach(card=>{
    const veh = card.getAttribute('data-vehiculo').trim();
    const inputs = card.querySelectorAll('input.tarifa-input');
    // inicializa
    tarifas[veh] = {completo:0, medio:0, extra:0, carrotanque:0};
    inputs.forEach(inp=>{
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

/* Guardado AJAX por cambio, manteniendo tu endpoint */
document.querySelectorAll('#tarifas_cards input.tarifa-input').forEach(input=>{
  input.addEventListener('change',()=>{
    const card = input.closest('.vehiculo-card');
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

/* Panel de viajes (igual) */
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
