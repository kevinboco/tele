<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}

/* =======================================================
   🔹 Endpoint AJAX: guardar tarifas
======================================================= */
if (isset($_POST['guardar_tarifa'])) {
    $empresa = $conn->real_escape_string($_POST['empresa']);
    $vehiculo = $conn->real_escape_string($_POST['vehiculo']);
    $campo = $conn->real_escape_string($_POST['campo']);
    $valor = floatval($_POST['valor']);

    $check = $conn->query("SELECT id FROM tarifas WHERE empresa='$empresa' AND tipo_vehiculo='$vehiculo'");
    if ($check && $check->num_rows > 0) {
        $conn->query("UPDATE tarifas SET $campo='$valor' WHERE empresa='$empresa' AND tipo_vehiculo='$vehiculo'");
    } else {
        $conn->query("INSERT INTO tarifas (empresa, tipo_vehiculo, $campo) VALUES ('$empresa','$vehiculo','$valor')");
    }
    echo "ok";
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
   🔹 Crear tabla tarifas si no existe
======================================================= */
$conn->query("CREATE TABLE IF NOT EXISTS tarifas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  empresa VARCHAR(100) NOT NULL,
  tipo_vehiculo VARCHAR(100) NOT NULL,
  completo DECIMAL(10,2) DEFAULT 0,
  medio DECIMAL(10,2) DEFAULT 0,
  extra DECIMAL(10,2) DEFAULT 0,
  carrotanque DECIMAL(10,2) DEFAULT 0,
  UNIQUE KEY (empresa, tipo_vehiculo)
)");

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

/* Cargar tarifas guardadas */
$tarifasGuardadas = [];
$sqlT = "SELECT * FROM tarifas";
$resT = $conn->query($sqlT);
if ($resT) {
    while ($t = $resT->fetch_assoc()) {
        $tarifasGuardadas[$t['empresa']][$t['tipo_vehiculo']] = $t;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Liquidación de Conductores</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>/* tu css intacto */</style>
</head>
<body>

<!-- ===== Encabezado y tu layout original ===== -->
<?php /* (todo tu HTML se mantiene exactamente igual) */ ?>

<!-- Solo reemplazo la parte donde generas la tabla de tarifas -->
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
          <td><input type="number" step="1000"
              value="<?= isset($tarifasGuardadas[$empresaFiltro][$veh]) ? $tarifasGuardadas[$empresaFiltro][$veh]['carrotanque'] : 0 ?>"
              oninput="guardarTarifa('<?= $veh ?>','carrotanque',this.value)" data-tipo="carrotanque"></td>
        <?php else: ?>
          <td><input type="number" step="1000"
              value="<?= isset($tarifasGuardadas[$empresaFiltro][$veh]) ? $tarifasGuardadas[$empresaFiltro][$veh]['completo'] : 0 ?>"
              oninput="guardarTarifa('<?= $veh ?>','completo',this.value)" data-tipo="completo"></td>
          <td><input type="number" step="1000"
              value="<?= isset($tarifasGuardadas[$empresaFiltro][$veh]) ? $tarifasGuardadas[$empresaFiltro][$veh]['medio'] : 0 ?>"
              oninput="guardarTarifa('<?= $veh ?>','medio',this.value)" data-tipo="medio"></td>
          <td><input type="number" step="1000"
              value="<?= isset($tarifasGuardadas[$empresaFiltro][$veh]) ? $tarifasGuardadas[$empresaFiltro][$veh]['extra'] : 0 ?>"
              oninput="guardarTarifa('<?= $veh ?>','extra',this.value)" data-tipo="extra"></td>
          <td>-</td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>

<script>
function guardarTarifa(vehiculo, campo, valor){
  const empresa = "<?= $empresaFiltro ?>";
  if (!empresa) return;
  fetch(location.href, {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
      guardar_tarifa: 1,
      empresa: empresa,
      vehiculo: vehiculo,
      campo: campo,
      valor: valor
    })
  });
  recalcular();
}
</script>

</body>
</html>
