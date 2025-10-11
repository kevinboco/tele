<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}

/* =======================================================
   🔹 Endpoint AJAX: guardar tarifa
======================================================= */
if (isset($_POST['guardar_tarifa'])) {
    $empresa  = $conn->real_escape_string($_POST['empresa']);
    $vehiculo = $conn->real_escape_string($_POST['vehiculo']);
    $campo    = $conn->real_escape_string($_POST['campo']);
    $valor    = floatval($_POST['valor']);

    // Verificar si ya existe una fila para esa empresa y vehículo
    $check = $conn->query("SELECT id FROM tarifas WHERE empresa='$empresa' AND tipo_vehiculo='$vehiculo'");
    if ($check && $check->num_rows > 0) {
        $conn->query("UPDATE tarifas SET $campo=$valor WHERE empresa='$empresa' AND tipo_vehiculo='$vehiculo'");
    } else {
        $conn->query("INSERT INTO tarifas (empresa, tipo_vehiculo, $campo) VALUES ('$empresa', '$vehiculo', $valor)");
    }
    exit("ok");
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

/* === Cargar viajes === */
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

/* === Cargar tarifas guardadas === */
$tarifasGuardadas = [];
$resT = $conn->query("SELECT * FROM tarifas");
if ($resT) while ($t = $resT->fetch_assoc()) {
    $tarifasGuardadas[$t['empresa']][$t['tipo_vehiculo']] = $t;
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
  body{font-family:'Segoe UI',sans-serif;background:#eef2f6;color:#333;padding:20px}
  .page-title{background:#fff;border-radius:14px;padding:12px 16px;margin-bottom:18px;box-shadow:0 2px 8px rgba(0,0,0,.05)}
  .layout{display:grid;grid-template-columns:1fr 2fr 1.2fr;gap:18px}
  @media (max-width:1200px){.layout{grid-template-columns:1fr;}}
  .box{border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.06);padding:14px;background:#fff}
  th{background:#0d6efd;color:#fff;text-align:center}
  td{text-align:center}
  input[type=number]{width:100%;text-align:right;border-radius:8px}
  .conductor-link{cursor:pointer;color:#0d6efd;text-decoration:underline;}
</style>
</head>
<body>

<div class="page-title">
  <form class="d-flex flex-wrap align-items-center gap-2" method="get">
    <label>Desde:</label>
    <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" required>
    <label>Hasta:</label>
    <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" required>
    <label>Empresa:</label>
    <select name="empresa">
      <option value="">-- Todas --</option>
      <?php foreach($empresas as $e): ?>
        <option value="<?= htmlspecialchars($e) ?>" <?= $empresaFiltro==$e?'selected':'' ?>><?= htmlspecialchars($e) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
  </form>
</div>

<div class="layout">
  <!-- Columna 1 -->
  <section class="box">
    <h3 class="text-center">🚐 Tarifas por Tipo de Vehículo</h3>
    <table id="tabla_tarifas" class="table">
      <thead><tr>
        <th>Vehículo</th><th>Completo</th><th>Medio</th><th>Extra</th><th>Carrotanque</th>
      </tr></thead>
      <tbody>
      <?php foreach ($vehiculos as $veh): ?>
        <tr>
          <td><?= htmlspecialchars($veh) ?></td>
          <?php if ($veh === "Carrotanque"): ?>
            <td>-</td><td>-</td><td>-</td>
            <td><input type="number" value="<?= isset($tarifasGuardadas[$empresaFiltro][$veh]) ? $tarifasGuardadas[$empresaFiltro][$veh]['carrotanque'] : 0 ?>"
              oninput="guardarTarifa('<?= $veh ?>','carrotanque',this.value)"></td>
          <?php else: ?>
            <td><input type="number" value="<?= isset($tarifasGuardadas[$empresaFiltro][$veh]) ? $tarifasGuardadas[$empresaFiltro][$veh]['completo'] : 0 ?>"
              oninput="guardarTarifa('<?= $veh ?>','completo',this.value)"></td>
            <td><input type="number" value="<?= isset($tarifasGuardadas[$empresaFiltro][$veh]) ? $tarifasGuardadas[$empresaFiltro][$veh]['medio'] : 0 ?>"
              oninput="guardarTarifa('<?= $veh ?>','medio',this.value)"></td>
            <td><input type="number" value="<?= isset($tarifasGuardadas[$empresaFiltro][$veh]) ? $tarifasGuardadas[$empresaFiltro][$veh]['extra'] : 0 ?>"
              oninput="guardarTarifa('<?= $veh ?>','extra',this.value)"></td>
            <td>-</td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <!-- Columna 2 -->
  <section class="box">
    <h3 class="text-center">🧑‍✈️ Resumen por Conductor</h3>
    <table id="tabla_conductores" class="table">
      <thead>
        <tr><th>Conductor</th><th>Vehículo</th><th>Completos</th><th>Medios</th><th>Extras</th><th>Carrotanques</th></tr>
      </thead>
      <tbody>
      <?php foreach ($datos as $c => $v): ?>
        <tr data-vehiculo="<?= htmlspecialchars($v['vehiculo']) ?>">
          <td class="conductor-link"><?= htmlspecialchars($c) ?></td>
          <td><?= htmlspecialchars($v['vehiculo']) ?></td>
          <td><?= $v['completos'] ?></td>
          <td><?= $v['medios'] ?></td>
          <td><?= $v['extras'] ?></td>
          <td><?= $v['carrotanques'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <!-- Columna 3 -->
  <aside class="box" id="panelViajes">
    <h4 id="tituloPanel">🧳 Viajes</h4>
    <div id="contenidoPanel"><p class="text-muted mb-0">Selecciona un conductor en la tabla.</p></div>
  </aside>
</div>

<script>
function guardarTarifa(vehiculo, campo, valor){
  const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
  fetch("<?= basename(__FILE__) ?>", {
    method: "POST",
    headers: {"Content-Type": "application/x-www-form-urlencoded"},
    body: new URLSearchParams({guardar_tarifa:1, empresa, vehiculo, campo, valor})
  }).then(r=>r.text()).then(()=>console.log("Tarifa guardada"))
  .catch(()=>alert("Error guardando tarifa"));
}

document.querySelectorAll('#tabla_conductores .conductor-link').forEach(td=>{
  td.addEventListener('click',()=>{
    const nombre = td.innerText.trim();
    const desde  = "<?= htmlspecialchars($desde) ?>";
    const hasta  = "<?= htmlspecialchars($hasta) ?>";
    const empresa= "<?= htmlspecialchars($empresaFiltro) ?>";
    document.getElementById('tituloPanel').innerHTML = `🚗 Viajes de <b>${nombre}</b>`;
    document.getElementById('contenidoPanel').innerHTML = "Cargando...";
    fetch(`<?= basename(__FILE__) ?>?viajes_conductor=${encodeURIComponent(nombre)}&desde=${desde}&hasta=${hasta}&empresa=${encodeURIComponent(empresa)}`)
      .then(r=>r.text()).then(h=>document.getElementById('contenidoPanel').innerHTML=h)
      .catch(()=>document.getElementById('contenidoPanel').innerHTML="Error al cargar");
  });
});
</script>
</body>
</html>
