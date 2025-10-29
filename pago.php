<?php
$prestamosList = [];
$i = 0;
$qPrest = "
  SELECT deudor,
         SUM(monto + monto*0.10*CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS total
  FROM prestamos
  WHERE (pagado IS NULL OR pagado=0)
  GROUP BY deudor
";

// DEBUG: Ver préstamos individuales de Héctor Iguaran
$debugHector = "
  SELECT id, monto, fecha, 
         (monto * 0.10 * CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS intereses,
         (monto + monto*0.10*CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS total_prestamo
  FROM prestamos 
  WHERE deudor = 'Héctor Iguaran' AND (pagado IS NULL OR pagado=0)
";
$debugResult = $conn->query($debugHector);
$totalHectorDebug = 0;

echo "<div style='background: #ffeb3b; padding: 10px; margin: 10px; border: 2px solid red;'>";
echo "<h3>DEBUG HÉCTOR IGUARAN</h3>";
if ($debugResult && $debugResult->num_rows > 0) {
    while ($debug = $debugResult->fetch_assoc()) {
        $totalHectorDebug += $debug['total_prestamo'];
        echo "Préstamo ID: {$debug['id']} | Monto: " . number_format($debug['monto'], 0, ',', '.') . " | Fecha: {$debug['fecha']} | Intereses: " . number_format($debug['intereses'], 0, ',', '.') . " | Total: " . number_format($debug['total_prestamo'], 0, ',', '.') . "<br>";
    }
    echo "<strong>Total Héctor Iguaran (debug): " . number_format($totalHectorDebug, 0, ',', '.') . "</strong>";
} else {
    echo "No se encontraron préstamos para Héctor Iguaran";
}
echo "</div>";

if ($rP = $conn->query($qPrest)) {
  while($r = $rP->fetch_assoc()){
    $name = $r['deudor'];
    $key  = norm_person($name);
    $total = (int)round($r['total']);
    $prestamosList[] = ['id'=>$i++, 'name'=>$name, 'key'=>$key, 'total'=>$total];
    
    // DEBUG para Héctor Iguaran
    if ($name === 'Héctor Iguaran') {
        echo "<div style='background: #4caf50; padding: 10px; margin: 10px; border: 2px solid blue; color: white;'>";
        echo "<strong>Héctor Iguaran - Total en consulta principal: " . number_format($total, 0, ',', '.') . "</strong>";
        echo "</div>";
    }
  }
}