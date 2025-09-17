<?php
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}

// 1. Consultar viajes
$res = $conn->query("SELECT nombre, ruta FROM viajes");

// 2. Agrupar por conductor
$datos = [];
while ($row = $res->fetch_assoc()) {
    $nombre = $row['nombre'];
    $guiones = substr_count($row['ruta'], '-');

    if (!isset($datos[$nombre])) {
        $datos[$nombre] = ["completos" => 0, "medios" => 0];
    }

    if ($guiones == 2) {
        $datos[$nombre]["completos"]++;
    } elseif ($guiones == 1) {
        $datos[$nombre]["medios"]++;
    }
}
?>

<h2>💰 Liquidación de Conductores</h2>
<form method="post">
<table border="1" cellpadding="5" cellspacing="0">
    <tr style="background:#eee;">
        <th>Conductor</th>
        <th>Viajes Completos</th>
        <th>Viajes Medios</th>
        <th>Valor Viaje Completo</th>
        <th>Valor Viaje Medio</th>
        <th>Total a Pagar</th>
    </tr>
    <?php foreach ($datos as $conductor => $viajes): ?>
        <tr>
            <td><?= $conductor ?></td>
            <td><?= $viajes["completos"] ?></td>
            <td><?= $viajes["medios"] ?></td>
            <td>
                <input type="number" name="precio_completo[<?= $conductor ?>]" step="1000" value="0" oninput="calcularTotal(this)">
            </td>
            <td>
                <input type="number" name="precio_medio[<?= $conductor ?>]" step="1000" value="0" oninput="calcularTotal(this)">
            </td>
            <td>
                <input type="text" id="total_<?= md5($conductor) ?>" class="totales" readonly>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<br>
<h3>🔢 Total General: <span id="total_general">0</span></h3>
<br>
<a href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/index2.php" 
   style="background:#28a745; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;">
   ➡️ Generar Cuenta de Cobro
</a>
</form>

<script>
function calcularTotal(input) {
    // Buscar la fila actual
    let fila = input.closest("tr");
    let completos = parseInt(fila.cells[1].innerText);
    let medios = parseInt(fila.cells[2].innerText);

    let precioCompleto = parseFloat(fila.cells[3].querySelector("input").value) || 0;
    let precioMedio = parseFloat(fila.cells[4].querySelector("input").value) || 0;

    let total = (completos * precioCompleto) + (medios * precioMedio);

    // Mostrar total por conductor
    fila.cells[5].querySelector("input").value = total.toLocaleString();

    // Recalcular el total general
    calcularTotalGeneral();
}

function calcularTotalGeneral() {
    let sum = 0;
    document.querySelectorAll(".totales").forEach(input => {
        let val = parseFloat(input.value.replace(/,/g, "")) || 0;
        sum += val;
    });
    document.getElementById("total_general").innerText = sum.toLocaleString();
}
</script>
