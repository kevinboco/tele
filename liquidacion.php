<?php
// ================================================
// 🔧 Conexión
// ================================================
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
$conn->set_charset('utf8mb4');
if ($conn->connect_error) die("Error conexión BD: ".$conn->connect_error);

// ¿Es una llamada AJAX? (para no imprimir nav.php)
$is_ajax = ($_SERVER['REQUEST_METHOD'] === 'POST') || isset($_GET['accion']) || isset($_GET['viajes_conductor']);
if (!$is_ajax) { include "nav.php"; }

// ================================================
// 🔧 Helper JSON limpio
// ================================================
function json_response($arr, $code = 200){
  if (ob_get_length()) ob_clean();
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

// ================================================
// 🔸 Guardar tarifas por empresa (POST)
// body: {accion:"guardar_tarifas", empresa:"...", tarifas:{ "Burbuja":{...}, ...}}
// ================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (is_array($payload) && ($payload['accion'] ?? '') === 'guardar_tarifas') {
      $empresa = trim($payload['empresa'] ?? '');
      $tarifas = $payload['tarifas'] ?? null;
      if ($empresa === '') json_response(['ok'=>false,'msg'=>'Falta empresa'], 400);
      if (!is_array($tarifas)) json_response(['ok'=>false,'msg'=>'Formato de tarifas inválido'], 400);

      $stmt = $conn->prepare("
        INSERT INTO tarifas_empresa (empresa, tipo_vehiculo, completo, medio, extra, carrotanque)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
          completo=VALUES(completo),
          medio=VALUES(medio),
          extra=VALUES(extra),
          carrotanque=VALUES(carrotanque),
          updated_at=CURRENT_TIMESTAMP
      ");

      foreach ($tarifas as $veh => $vals){
        $c = (int)($vals['completo'] ?? 0);
        $m = (int)($vals['medio'] ?? 0);
        $e = (int)($vals['extra'] ?? 0);
        $ca= (int)($vals['carrotanque'] ?? 0);
        $stmt->bind_param('ssiiii', $empresa, $veh, $c, $m, $e, $ca);
        $stmt->execute();
      }
      $stmt->close();
      json_response(['ok'=>true,'msg'=>'Tarifas guardadas correctamente']);
    }
  } catch (Exception $ex){
    json_response(['ok'=>false,'msg'=>'Error SQL: '.$ex->getMessage()], 500);
  }
}

// ================================================
// 🔸 Cargar tarifas por empresa (GET ?accion=cargar_tarifas&empresa=...)
// ================================================
if (isset($_GET['accion']) && $_GET['accion']==='cargar_tarifas') {
  $empresa = trim($_GET['empresa'] ?? '');
  if ($empresa === '') json_response(['ok'=>false,'msg'=>'Falta empresa'], 400);

  $empresaSQL = $conn->real_escape_string($empresa);
  $res = $conn->query("SELECT tipo_vehiculo, completo, medio, extra, carrotanque
                       FROM tarifas_empresa WHERE empresa='$empresaSQL'");
  $out = [];
  if ($res) while ($r = $res->fetch_assoc()){
    $out[$r['tipo_vehiculo']] = [
      'completo'=>(int)$r['completo'],
      'medio'=>(int)$r['medio'],
      'extra'=>(int)$r['extra'],
      'carrotanque'=>(int)$r['carrotanque'],
    ];
  }
  json_response(['ok'=>true,'empresa'=>$empresa,'tarifas'=>$out]);
}

// ================================================
// 🔸 Panel viajes por conductor (GET ?viajes_conductor=...)
// ================================================
if (isset($_GET['viajes_conductor'])) {
  $nombre  = $conn->real_escape_string($_GET['viajes_conductor']);
  $desde   = $_GET['desde'];
  $hasta   = $_GET['hasta'];
  $empresa = $_GET['empresa'] ?? '';

  $sql = "SELECT fecha, ruta, empresa, tipo_vehiculo
          FROM viajes
          WHERE nombre='$nombre'
            AND fecha BETWEEN '$desde' AND '$hasta'";
  if ($empresa !== '') $sql .= " AND empresa='".$conn->real_escape_string($empresa)."'";
  $sql .= " ORDER BY fecha ASC";

  $res = $conn->query($sql);
  if ($res && $res->num_rows){
    echo "<table class='table table-bordered table-striped mb-0'>
            <thead>
              <tr class='table-primary text-center'>
                <th>Fecha</th><th>Ruta</th><th>Empresa</th><th>Vehículo</th>
              </tr>
            </thead><tbody>";
    while ($r = $res->fetch_assoc()){
      echo "<tr>
              <td>".htmlspecialchars($r['fecha'])."</td>
              <td>".htmlspecialchars($r['ruta'])."</td>
              <td>".htmlspecialchars($r['empresa'])."</td>
              <td>".htmlspecialchars($r['tipo_vehiculo'])."</td>
            </tr>";
    }
    echo "</tbody></table>";
  } else {
    echo "<p class='text-center text-muted mb-0'>No se encontraron viajes.</p>";
  }
  exit;
}

// ================================================
// 🔸 Vista principal
// ================================================
if (!isset($_GET['desde']) || !isset($_GET['hasta'])) {
  $empresas = [];
  $re = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa");
  if ($re) while($r=$re->fetch_assoc()) $empresas[] = $r['empresa'];
  ?>
  <style>
    body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;color:#333;padding:40px}
    .card{max-width:460px;margin:0 auto}
  </style>
  <div class="card shadow-sm">
    <div class="card-body">
      <h2 class="text-center mb-3">📅 Filtrar viajes por rango</h2>
      <form method="get" class="vstack gap-3">
        <label>Desde:<input type="date" name="desde" class="form-control" required></label>
        <label>Hasta:<input type="date" name="hasta" class="form-control" required></label>
        <label>Empresa:
          <select name="empresa" class="form-select">
            <option value="">-- Todas --</option>
            <?php foreach($empresas as $e): ?>
              <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button class="btn btn-primary w-100">Filtrar</button>
      </form>
    </div>
  </div>
  <?php
  exit;
}

// --- Datos para el periodo seleccionado ---
$desde = $_GET['desde'];
$hasta = $_GET['hasta'];
$empresaFiltro = $_GET['empresa'] ?? '';

$sql = "SELECT nombre, ruta, empresa, tipo_vehiculo
        FROM viajes WHERE fecha BETWEEN '$desde' AND '$hasta'";
if ($empresaFiltro !== '') $sql .= " AND empresa='".$conn->real_escape_string($empresaFiltro)."'";
$res = $conn->query($sql);

$datos = [];
$vehiculos = [];
while ($row = $res->fetch_assoc()){
  $nombre   = $row['nombre'];
  $ruta     = $row['ruta'];
  $vehiculo = $row['tipo_vehiculo'];
  $guiones  = substr_count($ruta,'-');

  if (!isset($datos[$nombre])) {
    $datos[$nombre] = ["vehiculo"=>$vehiculo,"completos"=>0,"medios"=>0,"extras"=>0,"carrotanques"=>0];
  }
  if (!in_array($vehiculo, $vehiculos, true)) $vehiculos[] = $vehiculo;

  if ($vehiculo==="Carrotanque" && $guiones==0)        $datos[$nombre]["carrotanques"]++;
  elseif (stripos($ruta,"Maicao")===false)             $datos[$nombre]["extras"]++;
  elseif ($guiones==2)                                  $datos[$nombre]["completos"]++;
  elseif ($guiones==1)                                  $datos[$nombre]["medios"]++;
}

// Empresas para el select del header
$empresas = [];
$re = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa");
if ($re) while($r=$re->fetch_assoc()) $empresas[] = $r['empresa'];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Liquidación de Conductores</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  :root{ --gap:18px; --box-radius:14px; }
  body{font-family:'Segoe UI',sans-serif;background:#eef2f6;color:#333;padding:20px}
  .page-title{background:#fff;border-radius:var(--box-radius);padding:12px 16px;margin-bottom:var(--gap);box-shadow:0 2px 8px rgba(0,0,0,.05)}
  .header-grid{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:12px;}
  .header-center{text-align:center;}
  .header-center h2{margin:4px 0 2px}
  .header-sub{font-size:14px;color:#555}

  .filtro-inline .form-control,.filtro-inline .form-select{height:32px;padding:2px 8px;font-size:14px;min-width:120px}
  .filtro-inline .btn{height:32px;padding:2px 10px}

  .layout{display:grid;grid-template-columns:1fr 2fr 1.2fr;gap:var(--gap)}
  @media (max-width:1200px){ .layout{grid-template-columns:1fr;} #panelViajes{position:relative;top:auto} }

  .box{border-radius:var(--box-radius);box-shadow:0 2px 10px rgba(0,0,0,.06);padding:14px}
  .box-left{background:#e8f0ff}
  .box-center{background:#e9f9ee}
  .box-right{background:#fff9e6}

  h3.section-title{text-align:center;margin:6px 0 12px}
  table{background:#fff;border-radius:10px;overflow:hidden}
  th{background:#0d6efd;color:#fff;text-align:center;padding:10px}
  td{text-align:center;padding:8px;border-bottom:1px solid #eee}
  tr:hover{background:#f6faff}

  input[type=number], input[readonly]{width:100%;max-width:160px;padding:6px;border:1px solid #ced4da;border-radius:8px;text-align:right}

  .total-chip{display:inline-block;padding:6px 12px;border-radius:999px;background:#e9f2ff;color:#0d6efd;font-weight:700;border:1px solid #d6e6ff;margin-bottom:8px;float:right}
  .section-title::after{content:"";display:block;clear:both}

  /* Panel derecho sticky y sin tapar navbar */
  #panelViajes{position:sticky;top:12px;z-index:1}
  #panelViajes .panel-header{display:flex;align-items:center;justify-content:space-between;background:#0d6efd;color:#fff;padding:10px 12px;border-radius:10px;position:sticky;top:0;z-index:1}
  .btn-clear{background:transparent;border:none;color:#fff;opacity:.9}
  .btn-clear:hover{opacity:1}
  .conductor-link{cursor:pointer;color:#0d6efd;text-decoration:underline}

  /* 🧭 Asegura que el navbar siempre quede interactivo */
  .navbar, .navbar * { position: relative; z-index: 2000; }
  .page-title, .layout { position: relative; z-index: 0; }
</style>
</head>
<body>

<!-- Encabezado -->
<div class="page-title">
  <div class="header-grid">
    <form class="filtro-inline" method="get" id="formFiltro">
      <div class="d-flex flex-wrap align-items-center gap-2">
        <label class="mb-0">Desde:</label>
        <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="form-control form-control-sm w-auto" required>
        <label class="ms-2 mb-0">Hasta:</label>
        <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="form-control form-control-sm w-auto" required>
        <label class="ms-2 mb-0">Empresa:</label>
        <select name="empresa" id="empresaSelect" class="form-select form-select-sm w-auto">
          <option value="">-- Todas --</option>
          <?php foreach($empresas as $e): ?>
            <option value="<?= htmlspecialchars($e) ?>" <?= $empresaFiltro==$e?'selected':'' ?>><?= htmlspecialchars($e) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary btn-sm ms-2" type="submit">Filtrar</button>
      </div>
    </form>

    <div class="header-center">
      <h2>🪙 Liquidación de Conductores</h2>
      <div class="header-sub">
        Periodo: <strong><?= htmlspecialchars($desde) ?></strong> a <strong><?= htmlspecialchars($hasta) ?></strong>
        <?php if ($empresaFiltro!==''): ?>
          &nbsp;•&nbsp; Empresa: <strong id="empresaActual"><?= htmlspecialchars($empresaFiltro) ?></strong>
        <?php else: ?>
          &nbsp;•&nbsp; Empresa: <strong id="empresaActual">—</strong>
        <?php endif; ?>
      </div>
    </div>

    <div></div>
  </div>
</div>

<!-- 3 columnas -->
<div class="layout">
  <!-- Columna 1: Tarifas -->
  <section class="box box-left">
    <h3 class="section-title">🚐 Tarifas por Tipo de Vehículo</h3>
    <table id="tabla_tarifas" class="table mb-0">
      <thead>
        <tr><th>Tipo de Vehículo</th><th>Viaje Completo</th><th>Viaje Medio</th><th>Viaje Extra</th><th>Carrotanque</th></tr>
      </thead>
      <tbody>
      <?php foreach($vehiculos as $veh): ?>
        <tr>
          <td class="veh-col"><?= htmlspecialchars($veh) ?></td>
          <?php if ($veh === "Carrotanque"): ?>
            <td>-</td><td>-</td><td>-</td>
            <td><input type="number" step="1000" value="0" oninput="recalcular()" class="inp-tarifa carrotanque"></td>
          <?php else: ?>
            <td><input type="number" step="1000" value="0" oninput="recalcular()" class="inp-tarifa completo"></td>
            <td><input type="number" step="1000" value="0" oninput="recalcular()" class="inp-tarifa medio"></td>
            <td><input type="number" step="1000" value="0" oninput="recalcular()" class="inp-tarifa extra"></td>
            <td>-</td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div class="d-flex gap-2 justify-content-end mt-2">
      <button id="btnCargarTarifas" class="btn btn-outline-secondary btn-sm">↻ Cargar tarifas de la empresa</button>
      <button id="btnGuardarTarifas" class="btn btn-success btn-sm">💾 Guardar tarifas de la empresa</button>
    </div>
  </section>

  <!-- Columna 2: Resumen -->
  <section class="box box-center">
    <h3 class="section-title">🧑‍✈️ Resumen por Conductor</h3>
    <div class="total-chip">🔢 Total General: <span id="total_general">0</span></div>

    <table id="tabla_conductores" class="table">
      <thead>
        <tr>
          <th>Conductor</th><th>Tipo Vehículo</th><th>Completos</th><th>Medios</th><th>Extras</th><th>Carrotanques</th><th>Total a Pagar</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($datos as $conductor=>$viajes): ?>
          <tr data-vehiculo="<?= htmlspecialchars($viajes['vehiculo']) ?>">
            <td class="conductor-link"><?= htmlspecialchars($conductor) ?></td>
            <td><?= htmlspecialchars($viajes['vehiculo']) ?></td>
            <td><?= (int)$viajes['completos'] ?></td>
            <td><?= (int)$viajes['medios'] ?></td>
            <td><?= (int)$viajes['extras'] ?></td>
            <td><?= (int)$viajes['carrotanques'] ?></td>
            <td><input type="text" class="totales form-control" readonly></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <!-- Columna 3: Panel -->
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
// ---------- Utilidades ----------
function getEmpresaActual(){ const s = document.getElementById('empresaSelect'); return s ? s.value.trim() : ''; }
function formatNumber(n){ return (n||0).toLocaleString('es-CO'); }

function getTarifas(){
  const t={};
  document.querySelectorAll('#tabla_tarifas tbody tr').forEach(row=>{
    const veh = row.querySelector('.veh-col').innerText.trim();
    const completo = row.querySelector('input.completo') ? +row.querySelector('input.completo').value : 0;
    const medio    = row.querySelector('input.medio') ? +row.querySelector('input.medio').value : 0;
    const extra    = row.querySelector('input.extra') ? +row.querySelector('input.extra').value : 0;
    const carro    = row.querySelector('input.carrotanque') ? +row.querySelector('input.carrotanque').value : 0;
    t[veh] = {completo, medio, extra, carrotanque:carro};
  });
  return t;
}

function applyTarifasToTable(obj){
  document.querySelectorAll('#tabla_tarifas tbody tr').forEach(row=>{
    const veh = row.querySelector('.veh-col').innerText.trim();
    const d = obj[veh] || {completo:0,medio:0,extra:0,carrotanque:0};
    if (row.querySelector('input.completo')) row.querySelector('input.completo').value = d.completo;
    if (row.querySelector('input.medio')) row.querySelector('input.medio').value = d.medio;
    if (row.querySelector('input.extra')) row.querySelector('input.extra').value = d.extra;
    if (row.querySelector('input.carrotanque')) row.querySelector('input.carrotanque').value = d.carrotanque;
  });
  recalcular();
}

function recalcular(){
  const tarifas = getTarifas();
  let totalGeneral = 0;
  document.querySelectorAll('#tabla_conductores tbody tr').forEach(fila=>{
    const vehiculo  = fila.getAttribute('data-vehiculo');
    const completos = +fila.cells[2].innerText || 0;
    const medios    = +fila.cells[3].innerText || 0;
    const extras    = +fila.cells[4].innerText || 0;
    const carro     = +fila.cells[5].innerText || 0;
    const t = tarifas[vehiculo] || {completo:0,medio:0,extra:0,carrotanque:0};
    const total = (completos*t.completo) + (medios*t.medio) + (extras*t.extra) + (carro*t.carrotanque);
    fila.querySelector('input.totales').value = formatNumber(total);
    totalGeneral += total;
  });
  document.getElementById('total_general').innerText = formatNumber(totalGeneral);
}

function limpiarPanel(){
  document.getElementById('tituloPanel').innerHTML = '🧳 Viajes';
  document.getElementById('contenidoPanel').innerHTML = '<p class="text-muted mb-0">Selecciona un conductor en la tabla para ver sus viajes aquí.</p>';
}

// ---------- Cargar/GUARDAR tarifas ----------
function cargarTarifasEmpresa(empresa){
  if (!empresa){ alert('Selecciona una empresa para cargar sus tarifas.'); return; }
  fetch('<?= basename(__FILE__) ?>?accion=cargar_tarifas&empresa='+encodeURIComponent(empresa))
    .then(r=>r.json())
    .then(j=>{
      if (!j.ok) throw new Error(j.msg || 'No se pudieron cargar');
      applyTarifasToTable(j.tarifas || {});
    })
    .catch(err=> alert('Error al cargar: '+err.message));
}

function guardarTarifasEmpresa(empresa){
  if (!empresa){ alert('Selecciona una empresa para guardar sus tarifas.'); return; }
  const payload = {accion:'guardar_tarifas', empresa, tarifas:getTarifas()};
  fetch('<?= basename(__FILE__) ?>', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  })
  .then(async r=>{
    const txt = await r.text(); // leemos como texto por si algo rompe el JSON
    let j; try{ j = JSON.parse(txt); }catch(e){ throw new Error('Respuesta no-JSON:\n'+txt); }
    if (!j.ok) throw new Error(j.msg || 'Fallo al guardar');
    alert('✅ Tarifas guardadas para '+empresa);
  })
  .catch(err=> alert('Error al guardar tarifas:\n'+err.message));
}

// ---------- Panel de viajes por conductor ----------
document.querySelectorAll('#tabla_conductores .conductor-link').forEach(td=>{
  td.addEventListener('click', ()=>{
    const nombre = td.innerText.trim();
    const desde  = "<?= htmlspecialchars($desde) ?>";
    const hasta  = "<?= htmlspecialchars($hasta) ?>";
    const empresa= getEmpresaActual();

    document.getElementById('tituloPanel').innerHTML =
      `🚗 Viajes de <b>${nombre}</b> entre ${desde} y ${hasta}`;
    document.getElementById('contenidoPanel').innerHTML =
      "<p class='text-center text-muted mb-0'>Cargando viajes...</p>";

    fetch('<?= basename(__FILE__) ?>?viajes_conductor='+encodeURIComponent(nombre)+'&desde='+desde+'&hasta='+hasta+'&empresa='+encodeURIComponent(empresa))
      .then(res=>res.text())
      .then(html=>{
        document.getElementById('contenidoPanel').innerHTML = html;
        // 🧹 Evita overlays/backdrops que bloqueen el navbar
        document.querySelectorAll('.modal-backdrop, .offcanvas-backdrop').forEach(el=>el.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
      })
      .catch(()=>{ document.getElementById('contenidoPanel').innerHTML = "<p class='text-danger text-center'>Error al cargar los viajes.</p>"; });
  });
});

// ---------- Eventos UI ----------
document.getElementById('btnCargarTarifas').addEventListener('click', ()=> {
  const emp = getEmpresaActual(); cargarTarifasEmpresa(emp);
});
document.getElementById('btnGuardarTarifas').addEventListener('click', ()=> {
  const emp = getEmpresaActual(); guardarTarifasEmpresa(emp);
});
document.getElementById('empresaSelect').addEventListener('change', (e)=>{
  const emp = e.target.value.trim();
  document.getElementById('empresaActual').innerText = emp || '—';
  if (emp) cargarTarifasEmpresa(emp); else applyTarifasToTable({});
});

// Inicial
window.addEventListener('DOMContentLoaded', ()=>{
  const emp = getEmpresaActual();
  if (emp) cargarTarifasEmpresa(emp);
  recalcular();
});
</script>
</body>
</html>
