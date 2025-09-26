<?php
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
$sql = "SELECT nombre, ruta, empresa FROM viajes 
        WHERE fecha BETWEEN '$desde' AND '$hasta'";
if ($empresaFiltro !== "") {
    $empresaFiltro = $conn->real_escape_string($empresaFiltro);
    $sql .= " AND empresa = '$empresaFiltro'";
}
$res = $conn->query($sql);

// 2. Agrupar por conductor
$datos = [];
while ($row = $res->fetch_assoc()) {
    $nombre = $row['nombre'];
    $ruta   = $row['ruta'];
    $guiones = substr_count($ruta, '-');

    if (!isset($datos[$nombre])) {
        $datos[$nombre] = ["completos" => 0, "medios" => 0, "extras" => 0];
    }

    if (stripos($ruta, "Maicao") === false) {
        // 🚨 No contiene "Maicao" → viaje extra
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

<form method="post">
<table border="1" cellpadding="5" cellspacing="0">
    <tr style="background:#eee;">
        <th>Conductor</th>
        <th>Viajes Completos</th>
        <th>Viajes Medios</th>
        <th>Viajes Extras</th>
        <th>Valor Viaje Completo</th>
        <th>Valor Viaje Medio</th>
        <th>Valor Viaje Extra</th>
        <th>Total a Pagar</th>
    </tr>
    <?php foreach ($datos as $conductor => $viajes): ?>
        <tr>
            <td><?= htmlspecialchars($conductor) ?></td>
            <td><?= $viajes["completos"] ?></td>
            <td><?= $viajes["medios"] ?></td>
            <td><?= $viajes["extras"] ?></td>
            <td>
                <input type="number" step="1000" value="0"
                       oninput="calcularTotal(this, <?= $viajes['completos'] ?>, <?= $viajes['medios'] ?>, <?= $viajes['extras'] ?>)">
            </td>
            <td>
                <input type="number" step="1000" value="0"
                       oninput="calcularTotal(this, <?= $viajes['completos'] ?>, <?= $viajes['medios'] ?>, <?= $viajes['extras'] ?>)">
            </td>
            <td>
                <input type="number" step="1000" value="0"
                       oninput="calcularTotal(this, <?= $viajes['completos'] ?>, <?= $viajes['medios'] ?>, <?= $viajes['extras'] ?>)">
            </td>
            <td>
                <input type="text" class="totales" readonly>
            </td>
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
</form>

<script>
function calcularTotal(input, completos, medios, extras) {
    let fila = input.closest("tr");

    let precioCompleto = parseFloat(fila.cells[4].querySelector("input").value) || 0;
    let precioMedio    = parseFloat(fila.cells[5].querySelector("input").value) || 0;
    let precioExtra    = parseFloat(fila.cells[6].querySelector("input").value) || 0;

    let total = (completos * precioCompleto) +
                (medios * precioMedio) +
                (extras * precioExtra);

    // Mostrar con puntos cada 3 dígitos (formato colombiano)
    fila.cells[7].querySelector("input").value = total.toLocaleString("es-CO");

    calcularTotalGeneral();
}

function calcularTotalGeneral() {
    let sum = 0;
    document.querySelectorAll(".totales").forEach(input => {
        let val = input.value.replace(/\./g, ""); // quitar puntos
        val = parseFloat(val) || 0;
        sum += val;
    });

    document.getElementById("total_general").innerText = sum.toLocaleString("es-CO");
}
</script>
