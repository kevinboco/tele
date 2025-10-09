<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}

// Si no hay fechas seleccionadas, mostrar formulario
if (!isset($_GET['desde']) || !isset($_GET['hasta'])) {
    // Consultar todas las empresas distintas para el select
    $empresas = [];
    $resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
    while ($r = $resEmp->fetch_assoc()) {
        $empresas[] = $r['empresa'];
    }
    ?>
    <h2>📅 Filtrar viajes por rango de fechas</h2>
    <form method="get">
        <label>Desde: <input type="date" name="desde" required></label><br><br>
        <label>Hasta: <input type="date" name="hasta" required></label><br><br>

        <label>Empresa: 
            <select name="empresa">
                <option value="">-- Todas --</option>
                <?php foreach($empresas as $e): ?>
                    <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <br><br>

        <button type="submit">Filtrar</button>
    </form>
    <?php
    exit;
}

// Fechas recibidas
$desde = $_GET['desde'];
$hasta = $_GET['hasta'];
$empresaFiltro = $_GET['empresa'] ?? "";

// 1. Construir consulta
$sql = "SELECT nombre, ruta, empresa, tipo_vehiculo FROM viajes 
        WHERE fecha BETWEEN '$desde' AND '$hasta'";
if ($empresaFiltro !== "") {
    $empresaFiltro = $conn->real_escape_string($empresaFiltro);
    $sql .= " AND empresa = '$empresaFiltro'";
}
$res = $conn->query($sql);

// 2. Agrupar por conductor + contar viajes
$datos = [];
$vehiculos = [];
while ($row = $res->fetch_assoc()) {
    $nombre   = $row['nombre'];
    $ruta     = $row['ruta'];
    $vehiculo = $row['tipo_vehiculo'];
    $guiones  = substr_count($ruta, '-');

    if (!isset($datos[$nombre])) {
        $datos[$nombre] = [
            "vehiculo"     => $vehiculo, 
            "completos"    => 0, 
            "medios"       => 0, 
            "extras"       => 0,
            "carrotanques" => 0
        ];
    }

    if (!in_array($vehiculo, $vehiculos)) {
        $vehiculos[] = $vehiculo;
    }

    if ($vehiculo === "Carrotanque" && $guiones == 0) {
        // Caso especial: viaje carro tanque en Nazaret
        $datos[$nombre]["carrotanques"]++;
    } elseif (stripos($ruta, "Maicao") === false) {
        $datos[$nombre]["extras"]++;
    } elseif ($guiones == 2) {
        $datos[$nombre]["completos"]++;
    } elseif ($guiones == 1) {
        $datos[$nombre]["medios"]++;
    }
}
?>

<h2>💰 Liquidación de Conductores</h2>
<h3>Periodo: <?= htmlspecialchars($desde) ?> hasta <?= htmlspecialchars($hasta) ?></h3>
<?php if ($empresaFiltro !== ""): ?>
    <h4>Empresa: <?= htmlspecialchars($empresaFiltro) ?></h4>
<?php endif; ?>

<!-- TABLA 1: Tarifas por vehículo -->
<h3>🚐 Tarifas por Tipo de Vehículo</h3>
<table border="1" cellpadding="5" cellspacing="0" id="tabla_tarifas">
    <tr style="background:#eee;">
        <th>Tipo de Vehículo</th>
        <th>Valor Viaje Completo</th>
        <th>Valor Viaje Medio</th>
        <th>Valor Viaje Extra</th>
        <th>Valor Viaje Carrotanque</th>
    </tr>
    <?php foreach ($vehiculos as $veh): ?>
        <tr>
            <td><?= htmlspecialchars($veh) ?></td>
            <?php if ($veh === "Carrotanque"): ?>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td><input type="number" step="1000" value="0" oninput="recalcular()"></td>
            <?php else: ?>
                <td><input type="number" step="1000" value="0" oninput="recalcular()"></td>
                <td><input type="number" step="1000" value="0" oninput="recalcular()"></td>
                <td><input type="number" step="1000" value="0" oninput="recalcular()"></td>
                <td>-</td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
</table>
<br>

<!-- TABLA 2: Conductores -->
<h3>👨‍✈️ Resumen por Conductor</h3>
<table border="1" cellpadding="5" cellspacing="0" id="tabla_conductores">
    <tr style="background:#eee;">
        <th>Conductor</th>
        <th>Tipo Vehículo</th>
        <th>Viajes Completos</th>
        <th>Viajes Medios</th>
        <th>Viajes Extras</th>
        <th>Viajes Carrotanque</th>
        <th>Total a Pagar</th>
    </tr>
    <?php foreach ($datos as $conductor => $viajes): ?>
        <tr data-vehiculo="<?= htmlspecialchars($viajes['vehiculo']) ?>">
            <td><?= htmlspecialchars($conductor) ?></td>
            <td><?= htmlspecialchars($viajes['vehiculo']) ?></td>
            <td><?= $viajes["completos"] ?></td>
            <td><?= $viajes["medios"] ?></td>
            <td><?= $viajes["extras"] ?></td>
            <td><?= $viajes["carrotanques"] ?></td>
            <td><input type="text" class="totales" readonly></td>
        </tr>
    <?php endforeach; ?>
</table>
<br>
<h3>🔢 Total General: <span id="total_general">0</span></h3>
<br>
<a href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/index2.php" 
   style="background:#28a745; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;">
   ➡️ volver a listado de viajes
</a>

<script>
function getTarifas() {
    let tarifas = {};
    let tabla = document.getElementById("tabla_tarifas");
    for (let i = 1; i < tabla.rows.length; i++) {
        let vehiculo = tabla.rows[i].cells[0].innerText.trim();

        let completo = tabla.rows[i].cells[1].querySelector("input") ? 
            parseFloat(tabla.rows[i].cells[1].querySelector("input").value) || 0 : 0;
        let medio = tabla.rows[i].cells[2].querySelector("input") ? 
            parseFloat(tabla.rows[i].cells[2].querySelector("input").value) || 0 : 0;
        let extra = tabla.rows[i].cells[3].querySelector("input") ? 
            parseFloat(tabla.rows[i].cells[3].querySelector("input").value) || 0 : 0;
        let carrotanque = tabla.rows[i].cells[4].querySelector("input") ? 
            parseFloat(tabla.rows[i].cells[4].querySelector("input").value) || 0 : 0;

        tarifas[vehiculo] = {completo, medio, extra, carrotanque};
    }
    return tarifas;
}

function formatNumber(num) {
    return num.toLocaleString('es-CO');
}

function recalcular() {
    let tarifas = getTarifas();
    let tabla = document.getElementById("tabla_conductores");
    let totalGeneral = 0;

    for (let i = 1; i < tabla.rows.length; i++) {
        let fila = tabla.rows[i];
        let vehiculo = fila.getAttribute("data-vehiculo");
        let completos = parseInt(fila.cells[2].innerText) || 0;
        let medios    = parseInt(fila.cells[3].innerText) || 0;
        let extras    = parseInt(fila.cells[4].innerText) || 0;
        let carrotanques = parseInt(fila.cells[5].innerText) || 0;

        if (tarifas[vehiculo]) {
            let total = (completos * tarifas[vehiculo].completo) +
                        (medios * tarifas[vehiculo].medio) +
                        (extras * tarifas[vehiculo].extra) +
                        (carrotanques * tarifas[vehiculo].carrotanque);

            fila.cells[6].querySelector("input").value = formatNumber(total);
            totalGeneral += total;
        }
    }
    document.getElementById("total_general").innerText = formatNumber(totalGeneral);
}
</script>
