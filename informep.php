<?php
// ==============================================
// INFORME DE VIAJES POR PUESTO DE SALUD
// ==============================================

// Conexión a la base de datos (PROPORCIONADA POR TI)
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// ==============================================
// PARÁMETROS (puedes cambiarlos o pasarlos por GET)
// ==============================================
$fecha_desde = $_GET['fecha_desde'] ?? '2025-01-01';
$fecha_hasta = $_GET['fecha_hasta'] ?? '2026-12-31';
$presupuesto = floatval($_GET['presupuesto'] ?? 13000000);

// ==============================================
// 1. CARGAR TABLAS AUXILIARES A MEMORIA
// ==============================================

// Cargar clasificación de rutas (ruta + tipo_vehiculo → clasificacion)
$clasificacion = [];
$result = $conn->query("SELECT ruta, tipo_vehiculo, clasificacion FROM ruta_clasificacion");
while ($row = $result->fetch_assoc()) {
    $key = $row['ruta'] . '|' . $row['tipo_vehiculo'];
    $clasificacion[$key] = $row['clasificacion'];
}

// Cargar tarifas (empresa + tipo_vehiculo + clasificacion → valor)
$tarifas = [];
$result = $conn->query("SELECT empresa, tipo_vehiculo, completo, medio, extra, carrotanque, siapana, riohacha_completo, riohacha_medio, nazareth_siapana_maicao, nazareth_siapana_flor_de_la_guajira FROM tarifas");
while ($row = $result->fetch_assoc()) {
    $empresa = $row['empresa'];
    $tipo = $row['tipo_vehiculo'];
    if (!isset($tarifas[$empresa])) $tarifas[$empresa] = [];
    if (!isset($tarifas[$empresa][$tipo])) $tarifas[$empresa][$tipo] = [];
    $tarifas[$empresa][$tipo]['completo'] = floatval($row['completo']);
    $tarifas[$empresa][$tipo]['medio'] = floatval($row['medio']);
    $tarifas[$empresa][$tipo]['extra'] = floatval($row['extra']);
    $tarifas[$empresa][$tipo]['carrotanque'] = floatval($row['carrotanque']);
    $tarifas[$empresa][$tipo]['siapana'] = floatval($row['siapana']);
    $tarifas[$empresa][$tipo]['riohacha_completo'] = floatval($row['riohacha_completo']);
    $tarifas[$empresa][$tipo]['riohacha_medio'] = floatval($row['riohacha_medio']);
    $tarifas[$empresa][$tipo]['nazareth_siapana_maicao'] = floatval($row['nazareth_siapana_maicao']);
    $tarifas[$empresa][$tipo]['nazareth_siapana_flor_de_la_guajira'] = floatval($row['nazareth_siapana_flor_de_la_guajira']);
}

// ==============================================
// 2. OBTENER VIAJES (solo empresas que empiezan con "P.")
// ==============================================
$sql = "SELECT id, nombre, cedula, fecha, ruta, tipo_vehiculo, empresa 
        FROM viajes 
        WHERE empresa LIKE 'P.%' 
        AND fecha BETWEEN '$fecha_desde' AND '$fecha_hasta'
        ORDER BY fecha ASC";
$result = $conn->query($sql);
$viajes = [];
while ($row = $result->fetch_assoc()) {
    $viajes[] = $row;
}

// ==============================================
// 3. FUNCIÓN PARA CALULAR COSTO DE UN VIAJE
// ==============================================
function calcularCosto($ruta, $tipo_vehiculo, $empresa, $clasificacion, $tarifas) {
    // Buscar clasificación
    $key = $ruta . '|' . $tipo_vehiculo;
    $clase = $clasificacion[$key] ?? 'N/A';
    
    if ($clase === 'N/A') {
        return ['costo' => 'N/A', 'clasificacion' => 'N/A'];
    }
    
    // Buscar tarifa
    if (!isset($tarifas[$empresa][$tipo_vehiculo][$clase])) {
        return ['costo' => 'N/A', 'clasificacion' => $clase];
    }
    
    $costo = $tarifas[$empresa][$tipo_vehiculo][$clase];
    return ['costo' => $costo, 'clasificacion' => $clase];
}

// ==============================================
// 4. PROCESAR VIAJES: aplicar costo y clasificación
// ==============================================
foreach ($viajes as &$v) {
    $calc = calcularCosto($v['ruta'], $v['tipo_vehiculo'], $v['empresa'], $clasificacion, $tarifas);
    $v['costo'] = $calc['costo'];
    $v['clasificacion'] = $calc['clasificacion'];
    $v['fecha'] = date('d/m/Y', strtotime($v['fecha']));
}

// ==============================================
// 5. AGRUPAR POR PUESTO DE SALUD
// ==============================================
$grupos = [];
foreach ($viajes as $v) {
    $puesto = $v['empresa'];
    if (!isset($grupos[$puesto])) {
        $grupos[$puesto] = [];
    }
    $grupos[$puesto][] = $v;
}

// ==============================================
// 6. APLICAR REGLAS POR PUESTO
// ==============================================
$resultado_final = [];

foreach ($grupos as $puesto => $viajes_puesto) {
    if ($puesto === 'P.nazareth') {
        // Separar normales y extras
        $normales = [];
        $extras = [];
        foreach ($viajes_puesto as $v) {
            $ruta_lower = strtolower($v['ruta']);
            if (strpos($ruta_lower, 'maicao') !== false || strpos($ruta_lower, 'riohacha') !== false) {
                $extras[] = $v;
            } else {
                $normales[] = $v;
            }
        }
        $resultado_final[$puesto] = [
            'tipo' => 'estrella',
            'normales' => $normales,
            'extras' => $extras
        ];
    } else {
        // Aplicar presupuesto y colores
        $acumulado = 0;
        $viajes_con_color = [];
        foreach ($viajes_puesto as $v) {
            if ($v['costo'] !== 'N/A') {
                $acumulado += $v['costo'];
            }
            $v['acumulado'] = $acumulado;
            $v['color'] = ($acumulado <= $presupuesto && $v['costo'] !== 'N/A') ? 'Verde' : 'Rojo';
            $viajes_con_color[] = $v;
        }
        $resultado_final[$puesto] = [
            'tipo' => 'normal',
            'viajes' => $viajes_con_color,
            'total_general' => $acumulado
        ];
    }
}

// ==============================================
// 7. GENERAR HTML (que se guardará como .doc)
// ==============================================
header("Content-Type: application/msword");
header("Content-Disposition: attachment; filename=informe_viajes.doc");
header("Cache-Control: no-cache, no-store, must-revalidate");

echo '<!DOCTYPE html>';
echo '<html>';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<title>Informe de Viajes</title>';
echo '<style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; border-bottom: 2px solid #333; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; background: #f0f0f0; padding: 10px; }
        h3 { color: #777; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #333; color: white; }
        .verde { background-color: #d4edda; }
        .rojo { background-color: #f8d7da; }
        .total { font-weight: bold; margin-top: 10px; }
        .presupuesto { font-weight: bold; margin: 10px 0; }
     </style>';
echo '</head>';
echo '<body>';

echo "<h1>INFORME DE VIAJES</h1>";
echo "<p><strong>Período:</strong> $fecha_desde al $fecha_hasta</p>";
echo "<p><strong>Presupuesto:</strong> $" . number_format($presupuesto, 0, ',', '.') . "</p>";

foreach ($resultado_final as $puesto => $data) {
    echo "<h2>$puesto</h2>";
    
    if ($data['tipo'] === 'estrella') {
        // P.nazareth - Viajes normales
        echo "<h3>📌 Viajes Normales</h3>";
        if (count($data['normales']) > 0) {
            echo "<table>";
            echo "<tr><th>Fecha</th><th>Nombre</th><th>Ruta</th><th>Valor</th></tr>";
            foreach ($data['normales'] as $v) {
                $valor = ($v['costo'] === 'N/A') ? 'N/A' : '$ ' . number_format($v['costo'], 0, ',', '.');
                echo "<tr>";
                echo "<td>{$v['fecha']}</td>";
                echo "<td>{$v['nombre']}</td>";
                echo "<td>{$v['ruta']}</td>";
                echo "<td>$valor</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No hay viajes normales</p>";
        }
        
        // P.nazareth - Viajes extras
        echo "<h3>⭐ Viajes Extras (Maicao o Riohacha)</h3>";
        if (count($data['extras']) > 0) {
            echo "<table>";
            echo "<tr><th>Fecha</th><th>Nombre</th><th>Ruta</th><th>Valor</th></tr>";
            foreach ($data['extras'] as $v) {
                $valor = ($v['costo'] === 'N/A') ? 'N/A' : '$ ' . number_format($v['costo'], 0, ',', '.');
                echo "<tr>";
                echo "<td>{$v['fecha']}</td>";
                echo "<td>{$v['nombre']}</td>";
                echo "<td>{$v['ruta']}</td>";
                echo "<td>$valor</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No hay viajes extras</p>";
        }
    } else {
        // Demás puestos con presupuesto
        echo "<div class='presupuesto'>💰 Presupuesto para este puesto: $" . number_format($presupuesto, 0, ',', '.') . "</div>";
        echo "<table>";
        echo "<tr><th>Fecha</th><th>Nombre</th><th>Ruta</th><th>Valor</th><th>Acumulado</th></tr>";
        foreach ($data['viajes'] as $v) {
            $valor = ($v['costo'] === 'N/A') ? 'N/A' : '$ ' . number_format($v['costo'], 0, ',', '.');
            $acum = ($v['acumulado'] === 'N/A') ? 'N/A' : '$ ' . number_format($v['acumulado'], 0, ',', '.');
            $clase = ($v['color'] === 'Verde') ? 'verde' : 'rojo';
            echo "<tr class='$clase'>";
            echo "<td>{$v['fecha']}</td>";
            echo "<td>{$v['nombre']}</td>";
            echo "<td>{$v['ruta']}</td>";
            echo "<td>$valor</td>";
            echo "<td>$acum</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<div class='total'>💰 Total acumulado: $" . number_format($data['total_general'], 0, ',', '.') . "</div>";
    }
    echo "<br>";
}

echo '</body>';
echo '</html>';

$conn->close();
?>