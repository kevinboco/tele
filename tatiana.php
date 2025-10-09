<?php
// === DATOS DE MELAN ===
$melan = [
    1  => 160000,
    2  => 80000,
    4  => 320000,
    15 => 136000,
    20 => 120000,
    23 => 104000,
    29 => 160000,
    30 => 160000,
    7  => 240000
];

// === DATOS DE MARIBEL ===
$maribel = [
    30 => 560000,
    5  => 240000,
    8  => 160000,
    20 => 400000,
    25 => 160000
];

// Configura cuántos días tiene el mes
$daysInMonth = 30;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $desde = intval($_POST["desde"]);
    $hasta = intval($_POST["hasta"]);

    if ($desde < 1 || $desde > $daysInMonth || $hasta < 1 || $hasta > $daysInMonth) {
        $error = "Ingresa días entre 1 y $daysInMonth.";
    } else {
        $dias_incluidos = [];

        // Rango normal o cruzado (ejemplo: 30 → 7)
        if ($desde <= $hasta) {
            for ($i = $desde; $i <= $hasta; $i++) $dias_incluidos[] = $i;
        } else {
            for ($i = $desde; $i <= $daysInMonth; $i++) $dias_incluidos[] = $i;
            for ($i = 1; $i <= $hasta; $i++) $dias_incluidos[] = $i;
        }

        $total_melan = 0;
        $total_maribel = 0;
        $detalles = [];

        foreach ($dias_incluidos as $dia) {
            $valor_melan = $melan[$dia] ?? 0;
            $valor_maribel = $maribel[$dia] ?? 0;

            $total_melan += $valor_melan;
            $total_maribel += $valor_maribel;

            $detalles[] = [
                'dia' => $dia,
                'melan' => $valor_melan,
                'maribel' => $valor_maribel
            ];
        }

        $daysCount = count($dias_incluidos);
        $total_general = $total_melan + $total_maribel;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Totales por persona</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #fafafa; }
        input, button { margin: 5px; padding: 5px; }
        table { border-collapse: collapse; margin-top: 10px; width: 100%; background: #fff; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: center; }
        th { background: #eee; }
        .resultado { background: #fff; padding: 10px; margin-top: 10px; border-radius: 5px; border: 1px solid #ddd; }
        .error { color: red; }
        .total-general { background: #dff0d8; padding: 10px; border-radius: 5px; margin-top: 10px; font-weight: bold; }
    </style>
</head>
<body>

<h2>📅 Calcular total recibido (Melan y Maribel)</h2>

<form method="post">
    Desde el día: <input type="number" name="desde" required>
    Hasta el día: <input type="number" name="hasta" required>
    <button type="submit">Calcular</button>
</form>

<?php if (!empty($error)): ?>
    <p class="error"><?= $error ?></p>
<?php endif; ?>

<?php if (isset($total_melan)): ?>
<div class="resultado">
    <h3>Resultados del día <?= $desde ?> al <?= $hasta ?> (<?= $daysCount ?> días):</h3>

    <table>
        <tr>
            <th>Día</th>
            <th>Melan</th>
            <th>Maribel</th>
        </tr>
        <?php foreach ($detalles as $d): ?>
        <tr>
            <td><?= $d['dia'] ?></td>
            <td><?= $d['melan'] ? '$'.number_format($d['melan'], 0, ',', '.') : '-' ?></td>
            <td><?= $d['maribel'] ? '$'.number_format($d['maribel'], 0, ',', '.') : '-' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h4>Totales individuales:</h4>
    <p><strong>Melan:</strong> $<?= number_format($total_melan, 0, ',', '.') ?></p>
    <p><strong>Maribel:</strong> $<?= number_format($total_maribel, 0, ',', '.') ?></p>

    <div class="total-general">
        💰 <strong>Total general (Melan + Maribel):</strong> $<?= number_format($total_general, 0, ',', '.') ?>
    </div>
</div>
<?php endif; ?>

</body>
</html>
