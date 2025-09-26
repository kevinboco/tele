<?php
$conn = new mysqli("localhost", "root", "", "viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}

// === Valores iniciales de referencia ===
$valor_completo = 600000;
$valor_medio    = 300000;
$valor_extra    = 400000;

// Consultar viajes agrupados por conductor
$sql = "SELECT nombre,
        SUM(CASE 
            WHEN ruta LIKE '%-%-%' AND ruta LIKE '%Maicao%' THEN 1 ELSE 0 END) AS completos,
        SUM(CASE 
            WHEN ruta LIKE '%-%' AND ruta NOT LIKE '%-%-%' AND ruta LIKE '%Maicao%' THEN 1 ELSE 0 END) AS medios,
        SUM(CASE 
            WHEN ruta NOT LIKE '%Maicao%' THEN 1 ELSE 0 END) AS extras
        FROM viajes
        GROUP BY nombre";
$res = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Liquidación de Viajes</title>
    <style>
        table { border-collapse: collapse; width: 80%; margin: 20px auto; }
        th, td { border: 1px solid #333; padding: 8px; text-align: center; }
        th { background: #f2f2f2; }
        input { width: 100px; text-align: right; }
    </style>
</head>
<body>
    <h2 style="text-align:center;">Liquidación de Viajes</h2>
    <table>
        <thead>
            <tr>
                <th>Conductor</th>
                <th>Completos</th>
                <th>Medios</th>
                <th>Extras</th>
                <th>Valor Completo</th>
                <th>Valor Medio</th>
                <th>Valor Extra</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
        <?php while($row = $res->fetch_assoc()): ?>
            <tr>
                <td><?= $row['nombre'] ?></td>
                <td><?= $row['completos'] ?></td>
                <td><?= $row['medios'] ?></td>
                <td><?= $row['extras'] ?></td>
                <td><input type="text" name="valor_completo" value="<?= number_format($valor_completo, 0, '', '.') ?>"></td>
                <td><input type="text" name="valor_medio" value="<?= number_format($valor_medio, 0, '', '.') ?>"></td>
                <td><input type="text" name="valor_extra" value="<?= number_format($valor_extra, 0, '', '.') ?>"></td>
                <td class="total">0</td>
            </tr>
        <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="7">TOTAL GENERAL</th>
                <th id="totalGeneral">0</th>
            </tr>
        </tfoot>
    </table>

<script>
function calcularTotales() {
    let totalGeneral = 0;

    document.querySelectorAll("tbody tr").forEach(row => {
        let viajesCompletos = parseInt(row.cells[1].innerText) || 0;
        let viajesMedios    = parseInt(row.cells[2].innerText) || 0;
        let viajesExtras    = parseInt(row.cells[3].innerText) || 0;

        let valorCompleto = parseInt(row.querySelector("input[name='valor_completo']").value.replace(/\./g, "")) || 0;
        let valorMedio    = parseInt(row.querySelector("input[name='valor_medio']").value.replace(/\./g, "")) || 0;
        let valorExtra    = parseInt(row.querySelector("input[name='valor_extra']").value.replace(/\./g, "")) || 0;

        let total = (viajesCompletos * valorCompleto) +
                    (viajesMedios * valorMedio) +
                    (viajesExtras * valorExtra);

        row.querySelector(".total").innerText = total.toLocaleString("es-CO");
        totalGeneral += total;
    });

    document.getElementById("totalGeneral").innerText = totalGeneral.toLocaleString("es-CO");
}

function formatearInput(input) {
    let valor = input.value.replace(/\D/g, "");
    if (valor) {
        input.value = parseInt(valor).toLocaleString("es-CO");
    } else {
        input.value = "";
    }
    calcularTotales();
}

document.querySelectorAll("input").forEach(input => {
    input.addEventListener("input", function() {
        formatearInput(this);
    });
});

// Formatear al cargar
window.onload = function() {
    document.querySelectorAll("input").forEach(input => formatearInput(input));
    calcularTotales();
}
</script>
</body>
</html>
