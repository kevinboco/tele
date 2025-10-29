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
if ($debugResult && $debugResult->num_rows > 0) {
    echo "<!-- DEBUG HÉCTOR IGUARAN -->";
    while ($debug = $debugResult->fetch_assoc()) {
        $totalHectorDebug += $debug['total_prestamo'];
        echo "<!-- Préstamo ID: {$debug['id']} | Monto: {$debug['monto']} | Fecha: {$debug['fecha']} | Intereses: {$debug['intereses']} | Total: {$debug['total_prestamo']} -->";
    }
    echo "<!-- Total Héctor Iguaran (debug): " . number_format($totalHectorDebug, 0, ',', '.') . " -->";
}

if ($rP = $conn->query($qPrest)) {
  while($r = $rP->fetch_assoc()){
    $name = $r['deudor'];
    $key  = norm_person($name);
    $total = (int)round($r['total']);
    $prestamosList[] = ['id'=>$i++, 'name'=>$name, 'key'=>$key, 'total'=>$total];
    
    // DEBUG para Héctor Iguaran
    if ($name === 'Héctor Iguaran') {
        echo "<!-- Héctor Iguaran - Total en consulta principal: " . number_format($total, 0, ',', '.') . " -->";
    }
  }
}