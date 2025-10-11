<?php
include("nav.php");

$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}

/* =======================================================
   🔹 Valores predeterminados
======================================================= */
$desde_default = '2025-10-01'; // 👉 cámbialo cuando quieras
$hasta_default = date('Y-m-d'); // 👉 siempre será la fecha actual
$empresa_default = 'Hospital';  // 👉 empresa predeterminada

if (!isset($_GET['desde'])) $_GET['desde'] = $desde_default;
if (!isset($_GET['hasta'])) $_GET['hasta'] = $hasta_default;
if (!isset($_GET['empresa'])) $_GET['empresa'] = $empresa_default;

$desde = $_GET['desde'];
$hasta = $_GET['hasta'];
$empresa = $_GET['empresa'];

/* =======================================================
   🔹 Endpoint AJAX: Guardar tarifas
======================================================= */
if (isset($_GET['accion']) && $_GET['accion'] == 'guardar_tarifas') {
    $empresa = $_GET['empresa'];
    $vehiculo = $_GET['vehiculo'];
    $tarifa = $_GET['tarifa'];

    $sql_check = "SELECT * FROM tarifas WHERE empresa='$empresa' AND vehiculo='$vehiculo'";
    $res = $conn->query($sql_check);

    if ($res->num_rows > 0) {
        $sql_update = "UPDATE tarifas SET tarifa='$tarifa' WHERE empresa='$empresa' AND vehiculo='$vehiculo'";
        $conn->query($sql_update);
    } else {
        $sql_insert = "INSERT INTO tarifas (empresa, vehiculo, tarifa) VALUES ('$empresa', '$vehiculo', '$tarifa')";
        $conn->query($sql_insert);
    }

    echo "ok";
    exit;
}

/* =======================================================
   🔹 Endpoint AJAX: Obtener viajes por conductor
======================================================= */
if (isset($_GET['accion']) && $_GET['accion'] == 'viajes_conductor') {
    $empresa = $_GET['empresa'];
    $desde = $_GET['desde'];
    $hasta = $_GET['hasta'];

    $sql = "SELECT conductor, tipo_vehiculo, COUNT(*) AS total_viajes
            FROM viajes
            WHERE empresa='$empresa' AND fecha BETWEEN '$desde' AND '$hasta'
            GROUP BY conductor, tipo_vehiculo
            ORDER BY conductor";

    $res = $conn->query($sql);
    $data = [];
    while ($row = $res->fetch_assoc()) {
        $data[] = $row;
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/* =======================================================
   🔹 Filtros de búsqueda
======================================================= */
?>
<div class="container mt-4">
    <h3 class="mb-3">💰 Liquidación por Conductor</h3>

    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-3">
            <label class="form-label">Desde</label>
            <input type="date" name="desde" class="form-control" value="<?php echo $desde; ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Hasta</label>
            <input type="date" name="hasta" class="form-control" value="<?php echo $hasta; ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Empresa</label>
            <input type="text" name="empresa" class="form-control" value="<?php echo $empresa; ?>">
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Filtrar</button>
        </div>
    </form>

<?php
/* =======================================================
   🔹 Tabla de tarifas por vehículo
======================================================= */
$sql_tarifas = "SELECT * FROM tarifas WHERE empresa='$empresa'";
$res_tarifas = $conn->query($sql_tarifas);

$tarifas = [];
if ($res_tarifas->num_rows > 0) {
    while ($t = $res_tarifas->fetch_assoc()) {
        $tarifas[$t['vehiculo']] = $t['tarifa'];
    }
}

$tipos = ['Moto', 'Carro', 'Camioneta', 'Camión'];

echo "<h5>🚗 Tarifas de la empresa <b>$empresa</b></h5>";
echo "<table class='table table-bordered table-sm align-middle text-center' id='tabla_tarifas'>";
echo "<thead class='table-dark'><tr><th>Tipo Vehículo</th><th>Tarifa ($)</th></tr></thead><tbody>";
foreach ($tipos as $tipo) {
    $tarifa_valor = isset($tarifas[$tipo]) ? $tarifas[$tipo] : 0;
    echo "<tr>
            <td>$tipo</td>
            <td><input type='number' class='form-control text-end tarifa_input' data-vehiculo='$tipo' value='$tarifa_valor'></td>
          </tr>";
}
echo "</tbody></table>";
?>
    <button id="guardar_tarifas" class="btn btn-success">💾 Guardar Tarifas</button>

    <hr>

    <div id="tabla_resultados">
        <h5 class="mt-4">📊 Viajes del <?php echo $desde; ?> al <?php echo $hasta; ?></h5>
        <table class="table table-bordered table-striped text-center">
            <thead class="table-dark">
                <tr>
                    <th>Conductor</th>
                    <th>Tipo Vehículo</th>
                    <th>Total Viajes</th>
                    <th>Tarifa ($)</th>
                    <th>Total ($)</th>
                </tr>
            </thead>
            <tbody id="body_viajes"></tbody>
            <tfoot>
                <tr class="table-secondary">
                    <th colspan="4" class="text-end">💰 Total General:</th>
                    <th id="total_general">$0</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<script>
document.getElementById("guardar_tarifas").addEventListener("click", () => {
    document.querySelectorAll(".tarifa_input").forEach(input => {
        let vehiculo = input.dataset.vehiculo;
        let tarifa = input.value;
        let empresa = "<?php echo $empresa; ?>";

        fetch(`?accion=guardar_tarifas&empresa=${empresa}&vehiculo=${vehiculo}&tarifa=${tarifa}`)
            .then(r => r.text())
            .then(resp => {
                if (resp.trim() === "ok") {
                    input.classList.add("table-success");
                    setTimeout(() => input.classList.remove("table-success"), 1000);
                }
            });
    });
    alert("✅ Tarifas actualizadas correctamente");
});

/* =======================================================
   🔹 Cargar viajes por conductor con AJAX
======================================================= */
function cargarViajes() {
    const params = new URLSearchParams({
        accion: 'viajes_conductor',
        empresa: '<?php echo $empresa; ?>',
        desde: '<?php echo $desde; ?>',
        hasta: '<?php echo $hasta; ?>'
    });
    fetch(`?${params}`)
        .then(res => res.json())
        .then(data => {
            const tbody = document.getElementById("body_viajes");
            tbody.innerHTML = '';
            let totalGeneral = 0;

            data.forEach(row => {
                let tarifa = parseFloat(document.querySelector(`.tarifa_input[data-vehiculo='${row.tipo_vehiculo}']`)?.value || 0);
                let total = tarifa * row.total_viajes;
                totalGeneral += total;

                tbody.innerHTML += `
                    <tr>
                        <td>${row.conductor}</td>
                        <td>${row.tipo_vehiculo}</td>
                        <td>${row.total_viajes}</td>
                        <td>$${tarifa.toLocaleString()}</td>
                        <td>$${total.toLocaleString()}</td>
                    </tr>
                `;
            });

            document.getElementById("total_general").innerText = "$" + totalGeneral.toLocaleString();
        });
}

cargarViajes();
</script>
