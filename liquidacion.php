<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}

// ==========================================
// 🔹 Endpoint AJAX: viajes por conductor
// ==========================================
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
                    <th>Fecha</th><th>Ruta</th><th>Empresa</th><th>Vehículo</th>
                  </tr>
                </thead><tbody>";
        while ($r = $res->fetch_assoc()) {
            echo "<tr>
                    <td>{$r['fecha']}</td>
                    <td>{$r['ruta']}</td>
                    <td>{$r['empresa']}</td>
                    <td>{$r['tipo_vehiculo']}</td>
                  </tr>";
        }
        echo "</tbody></table>";
    } else echo "<p class='text-center text-muted mb-0'>No se encontraron viajes.</p>";
    exit;
}

// ==========================================
// 🔹 Carga de datos principal
// ==========================================
$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
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
    while ($r = $res->fetch_assoc()) {
        $nombre = $r['nombre'];
        $vehiculo = $r['tipo_vehiculo'];
        $guiones = substr_count($r['ruta'], '-');
        if (!isset($datos[$nombre]))
            $datos[$nombre] = ["vehiculo"=>$vehiculo,"completos"=>0,"medios"=>0,"extras"=>0,"carrotanques"=>0];
        if (!in_array($vehiculo,$vehiculos)) $vehiculos[]=$vehiculo;

        if ($vehiculo==="Carrotanque" && $guiones==0) $datos[$nombre]["carrotanques"]++;
        elseif (stripos($r['ruta'],"Maicao")===false) $datos[$nombre]["extras"]++;
        elseif ($guiones==2) $datos[$nombre]["completos"]++;
        elseif ($guiones==1) $datos[$nombre]["medios"]++;
    }
}
$empresas = [];
$re = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa<>'' ORDER BY empresa");
while($r=$re->fetch_assoc()) $empresas[]=$r['empresa'];
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
  .layout{display:grid;grid-template-columns:1fr 2fr 1.2fr;gap:var(--gap);}
  @media(max-width:1200px){.layout{grid-template-columns:1fr;}}

  /* 🔹 Colores */
  .box-left{background:#e8f0ff;}
  .box-center{background:#e9f9ee;}
  .box-right{background:#fff9e6;}
  .box{border-radius:var(--box-radius);box-shadow:0 2px 10px rgba(0,0,0,.06);padding:14px;}

  /* 🔹 Total */
  .total-chip{float:right;background:#e9f2ff;border:1px solid #c8ddff;border-radius:999px;padding:6px 12px;font-weight:600;color:#0d6efd;margin-bottom:8px;}
  .section-title::after{content:"";display:block;clear:both;}

  /* Panel lateral */
  #panelViajes{position:sticky;top:12px;}
  #panelViajes .panel-header{background:#0d6efd;color:#fff;padding:10px;border-radius:10px;display:flex;justify-content:space-between;align-items:center;}
  #panelViajes .panel-body{padding:10px;max-height:70vh;overflow:auto;}
  .btn-clear{background:transparent;border:none;color:#fff;}

  th{background:#0d6efd;color:#fff;text-align:center;}
  td{text-align:center;}
  tr:hover{background:#f6faff}
  .conductor-link{cursor:pointer;color:#0d6efd;text-decoration:underline;}

  /* 🔹 Filtro compacto */
  .filtro-inline .form-control,
  .filtro-inline .form-select{
    height:32px;
    padding:2px 8px;
    font-size:14px;
    min-width:120px;
  }
  .filtro-inline label{font-weight:600;}
  .filtro-form{margin-bottom:8px;}
  .box-left{padding-top:10px;padding-bottom:12px;}
</style>
</head>
<body>

<div class="layout">
  <!-- ===== Columna 1 ===== -->
  <section class="box box-left">
    <!-- 🔎 Filtro compacto -->
    <form class="filtro-form filtro-inline mb-2" method="get">
      <div class="d-flex flex-wrap align-items-center gap-2">
        <label class="me-1 mb-0">Desde:</label>
        <input type="date" name="desde" value="<?= $desde ?>" class="form-control form-control-sm w-auto" required>

        <label class="ms-2 me-1 mb-0">Hasta:</label>
        <input type="date" name="hasta" value="<?= $hasta ?>" class="form-control form-control-sm w-auto" required>

        <label class="ms-2 me-1 mb-0">Empresa:</label>
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

    <!-- 🔹 Tabla de tarifas -->
    <h3 class="section-title mt-2">🚐 Tarifas por Tipo de Vehículo</h3>
    <table id="tabla_tarifas" class="table mb-0">
      <thead>
        <tr><th>Vehículo</th><th>Completo</th><th>Medio</th><th>Extra</th><th>Carrotanque</th></tr>
      </thead>
      <tbody>
        <?php foreach($vehiculos as $v): ?>
        <tr>
          <td><?= $v ?></td>
          <?php if($v==="Carrotanque"): ?>
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

  <!-- ===== Columna 2 ===== -->
  <section class="box box-center">
    <h3 class="section-title">🧑‍✈️ Resumen por Conductor</h3>
    <div class="total-chip">🔢 Total General: <span id="total_general">0</span></div>
    <table id="tabla_conductores" class="table">
      <thead><tr>
        <th>Conductor</th><th>Vehículo</th><th>Completos</th><th>Medios</th><th>Extras</th><th>Carrotanques</th><th>Total</th>
      </tr></thead>
      <tbody>
      <?php foreach($datos as $c=>$v): ?>
        <tr data-vehiculo="<?= $v['vehiculo'] ?>">
          <td class="conductor-link"><?= $c ?></td>
          <td><?= $v['vehiculo'] ?></td>
          <td><?= $v['completos'] ?></td>
          <td><?= $v['medios'] ?></td>
          <td><?= $v['extras'] ?></td>
          <td><?= $v['carrotanques'] ?></td>
          <td><input type="text" class="totales form-control" readonly></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <!-- ===== Columna 3 ===== -->
  <aside id="panelViajes" class="box box-right">
    <div class="panel-header">
      <div id="tituloPanel">🧳 Viajes</div>
      <button class="btn-clear" onclick="limpiarPanel()">✕</button>
    </div>
    <div id="contenidoPanel" class="panel-body">
      <p class="text-muted mb-0">Selecciona un conductor para ver sus viajes.</p>
    </div>
  </aside>
</div>

<script>
function getTarifas(){
  const t={};
  document.querySelectorAll('#tabla_tarifas tbody tr').forEach(r=>{
    const v=r.cells[0].innerText.trim();
    const c=r.cells[1].querySelector('input')?parseFloat(r.cells[1].querySelector('input').value)||0:0;
    const m=r.cells[2].querySelector('input')?parseFloat(r.cells[2].querySelector('input').value)||0:0;
    const e=r.cells[3].querySelector('input')?parseFloat(r.cells[3].querySelector('input').value)||0:0;
    const ca=r.cells[4].querySelector('input')?parseFloat(r.cells[4].querySelector('input').value)||0:0;
    t[v]={completo:c,medio:m,extra:e,carrotanque:ca};
  });return t;
}
function formatNumber(n){return (n||0).toLocaleString('es-CO');}
function recalcular(){
  const tarifas=getTarifas();
  let total=0;
  document.querySelectorAll('#tabla_conductores tbody tr').forEach(f=>{
    const v=f.dataset.vehiculo;
    const c=parseInt(f.cells[2].innerText)||0,m=parseInt(f.cells[3].innerText)||0,e=parseInt(f.cells[4].innerText)||0,ca=parseInt(f.cells[5].innerText)||0;
    const t=tarifas[v]||{completo:0,medio:0,extra:0,carrotanque:0};
    const tot=(c*t.completo)+(m*t.medio)+(e*t.extra)+(ca*t.carrotanque);
    f.querySelector('input').value=formatNumber(tot);
    total+=tot;
  });
  document.getElementById('total_general').innerText=formatNumber(total);
}
function limpiarPanel(){
  document.getElementById('tituloPanel').innerHTML='🧳 Viajes';
  document.getElementById('contenidoPanel').innerHTML='<p class="text-muted mb-0">Selecciona un conductor.</p>';
}
document.querySelectorAll('.conductor-link').forEach(td=>{
  td.onclick=()=>{
    const nombre=td.innerText.trim();
    const desde='<?= $desde ?>',hasta='<?= $hasta ?>',empresa='<?= $empresaFiltro ?>';
    document.getElementById('tituloPanel').innerHTML=`🚗 Viajes de <b>${nombre}</b>`;
    document.getElementById('contenidoPanel').innerHTML='<p class="text-center text-muted">Cargando...</p>';
    fetch(`<?= basename(__FILE__) ?>?viajes_conductor=${encodeURIComponent(nombre)}&desde=${desde}&hasta=${hasta}&empresa=${encodeURIComponent(empresa)}`)
    .then(r=>r.text()).then(h=>document.getElementById('contenidoPanel').innerHTML=h)
    .catch(()=>document.getElementById('contenidoPanel').innerHTML='<p class="text-danger">Error al cargar.</p>');
  };
});
recalcular();
</script>
</body>
</html>
