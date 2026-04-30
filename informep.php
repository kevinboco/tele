<?php
// ==============================================
// INFORME DE VIAJES POR PUESTO DE SALUD
// CON SELECCIÓN MÚLTIPLE DE PUESTOS
// CORREGIDO: Detecta p.nazareth en mayúscula/minúscula
// EXTRAS SEPARADOS CORRECTAMENTE
// ==============================================

// Conexión a la base de datos
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// ==============================================
// PROCESAR FORMULARIO (si se envió)
// ==============================================
$generar_informe = isset($_POST['generar']);

if ($generar_informe) {
    $fecha_desde = $_POST['fecha_desde'];
    $fecha_hasta = $_POST['fecha_hasta'];
    $empresas_seleccionadas = $_POST['empresas'] ?? [];
    $presupuesto = floatval($_POST['presupuesto'] ?? 13000000);
    
    if (empty($empresas_seleccionadas)) {
        die("Debe seleccionar al menos un puesto de salud.");
    }
    
    // ==============================================
    // 1. CARGAR TABLAS AUXILIARES A MEMORIA
    // ==============================================
    
    // Cargar clasificación de rutas
    $clasificacion = [];
    $result = $conn->query("SELECT ruta, tipo_vehiculo, clasificacion FROM ruta_clasificacion");
    while ($row = $result->fetch_assoc()) {
        $key = $row['ruta'] . '|' . $row['tipo_vehiculo'];
        $clasificacion[$key] = $row['clasificacion'];
    }
    
    // Cargar tarifas
    $tarifas = [];
    $result = $conn->query("SELECT empresa, tipo_vehiculo, completo, medio, extra, carrotanque, siapana, riohacha_completo, riohacha_medio, nazareth_siapana_maicao, nazareth_siapana_flor_de_la_guajira FROM tarifas");
    while ($row = $result->fetch_assoc()) {
        $emp = $row['empresa'];
        $tipo = $row['tipo_vehiculo'];
        if (!isset($tarifas[$emp])) $tarifas[$emp] = [];
        if (!isset($tarifas[$emp][$tipo])) $tarifas[$emp][$tipo] = [];
        $tarifas[$emp][$tipo]['completo'] = floatval($row['completo']);
        $tarifas[$emp][$tipo]['medio'] = floatval($row['medio']);
        $tarifas[$emp][$tipo]['extra'] = floatval($row['extra']);
        $tarifas[$emp][$tipo]['carrotanque'] = floatval($row['carrotanque']);
        $tarifas[$emp][$tipo]['siapana'] = floatval($row['siapana']);
        $tarifas[$emp][$tipo]['riohacha_completo'] = floatval($row['riohacha_completo']);
        $tarifas[$emp][$tipo]['riohacha_medio'] = floatval($row['riohacha_medio']);
        $tarifas[$emp][$tipo]['nazareth_siapana_maicao'] = floatval($row['nazareth_siapana_maicao']);
        $tarifas[$emp][$tipo]['nazareth_siapana_flor_de_la_guajira'] = floatval($row['nazareth_siapana_flor_de_la_guajira']);
    }
    
    // ==============================================
    // 2. FUNCIÓN PARA CALCULAR COSTO
    // ==============================================
    function calcularCosto($ruta, $tipo_vehiculo, $empresa, $clasificacion, $tarifas) {
        $key = $ruta . '|' . $tipo_vehiculo;
        $clase = $clasificacion[$key] ?? 'N/A';
        
        if ($clase === 'N/A') {
            return ['costo' => 'N/A', 'clasificacion' => 'N/A'];
        }
        
        if (!isset($tarifas[$empresa][$tipo_vehiculo][$clase])) {
            return ['costo' => 'N/A', 'clasificacion' => $clase];
        }
        
        $costo = $tarifas[$empresa][$tipo_vehiculo][$clase];
        return ['costo' => $costo, 'clasificacion' => $clase];
    }
    
    // ==============================================
    // 3. PROCESAR CADA EMPRESA SELECCIONADA
    // ==============================================
    $empresas_con_viajes = [];
    $todas_las_empresas_con_datos = [];
    
    foreach ($empresas_seleccionadas as $empresa) {
        $sql = "SELECT id, nombre, cedula, fecha, ruta, tipo_vehiculo, empresa 
                FROM viajes 
                WHERE empresa = '$empresa'
                AND fecha BETWEEN '$fecha_desde' AND '$fecha_hasta'
                ORDER BY fecha ASC";
        $result = $conn->query($sql);
        $viajes = [];
        while ($row = $result->fetch_assoc()) {
            $viajes[] = $row;
        }
        
        if (count($viajes) == 0) {
            continue;
        }
        
        // Calcular costo de cada viaje
        foreach ($viajes as &$v) {
            $calc = calcularCosto($v['ruta'], $v['tipo_vehiculo'], $v['empresa'], $clasificacion, $tarifas);
            $v['costo'] = $calc['costo'];
            $v['clasificacion'] = $calc['clasificacion'];
            $v['fecha'] = date('d/m/Y', strtotime($v['fecha']));
        }
        
        // === REGLA ESPECIAL PARA P.NAZARETH (cualquier variación mayúscula/minúscula) ===
        if (strtolower($empresa) === 'p.nazareth') {
            $normales = [];
            $extras = [];
            
            foreach ($viajes as $v) {
                // Convertir ruta a minúsculas para comparar sin importar mayúsculas
                $ruta_lower = strtolower($v['ruta']);
                
                // Si contiene "maicao" o "riohacha" -> va a EXTRAS
                if (strpos($ruta_lower, 'maicao') !== false || strpos($ruta_lower, 'riohacha') !== false) {
                    $extras[] = $v;
                } else {
                    // Sino -> va a NORMALES
                    $normales[] = $v;
                }
            }
            
            $empresas_con_viajes[$empresa] = [
                'tipo' => 'estrella',
                'normales' => $normales,
                'extras' => $extras
            ];
        } else {
            // Puestos normales con presupuesto
            $acumulado = 0;
            $viajes_con_color = [];
            foreach ($viajes as $v) {
                if ($v['costo'] !== 'N/A') {
                    $acumulado += $v['costo'];
                }
                $v['acumulado'] = $acumulado;
                $v['color'] = ($acumulado <= $presupuesto && $v['costo'] !== 'N/A') ? 'Verde' : 'Rojo';
                $viajes_con_color[] = $v;
            }
            $empresas_con_viajes[$empresa] = [
                'tipo' => 'normal',
                'viajes' => $viajes_con_color,
                'total_general' => $acumulado
            ];
        }
        
        $todas_las_empresas_con_datos[] = $empresa;
    }
    
    if (empty($empresas_con_viajes)) {
        die("No hay viajes para los puestos seleccionados en el período indicado.");
    }
    
    // ==============================================
    // 4. GENERAR ARCHIVO WORD (.doc)
    // ==============================================
    $nombre_archivo = 'informe_viajes_' . date('Ymd_His') . '.doc';
    header("Content-Type: application/msword");
    header("Content-Disposition: attachment; filename=$nombre_archivo");
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
            .extra-tabla { margin-top: 10px; }
         </style>';
    echo '</head>';
    echo '<body>';
    
    echo "<h1>📋 INFORME DE VIAJES</h1>";
    echo "<p><strong>Período:</strong> $fecha_desde al $fecha_hasta</p>";
    echo "<p><strong>Puestos seleccionados:</strong> " . implode(', ', $todas_las_empresas_con_datos) . "</p>";
    
    if ($presupuesto > 0) {
        echo "<p><strong>Presupuesto aplicado (solo puestos NO estrella):</strong> $" . number_format($presupuesto, 0, ',', '.') . "</p>";
    }
    
    foreach ($empresas_con_viajes as $puesto => $data) {
        echo "<h2>$puesto</h2>";
        
        if ($data['tipo'] === 'estrella') {
            // ==========================================
            // P.NAZARETH - TABLA DE VIAJES NORMALES
            // ==========================================
            echo "<h3>📌 VIAJES NORMALES</h3>";
            if (count($data['normales']) > 0) {
                echo "<table>";
                echo "<tr><th>Fecha</th><th>Nombre</th><th>Cédula</th><th>Ruta</th><th>Tipo Vehículo</th><th>Valor</th></tr>";
                foreach ($data['normales'] as $v) {
                    $valor = ($v['costo'] === 'N/A') ? 'N/A' : '$ ' . number_format($v['costo'], 0, ',', '.');
                    echo "<tr>";
                    echo "<td>{$v['fecha']}</td>";
                    echo "<td>{$v['nombre']}</td>";
                    echo "<td>{$v['cedula']}</td>";
                    echo "<td>{$v['ruta']}</td>";
                    echo "<td>{$v['tipo_vehiculo']}</td>";
                    echo "<td>$valor</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No hay viajes normales</p>";
            }
            
            // ==========================================
            // P.NAZARETH - TABLA DE EXTRAS (Maicao o Riohacha)
            // ==========================================
            echo "<h3>⭐ EXTRAS (Rutas con Maicao o Riohacha)</h3>";
            if (count($data['extras']) > 0) {
                echo "<table class='extra-tabla'>";
                echo "<tr><th>Fecha</th><th>Nombre</th><th>Cédula</th><th>Ruta</th><th>Tipo Vehículo</th><th>Valor</th></tr>";
                foreach ($data['extras'] as $v) {
                    $valor = ($v['costo'] === 'N/A') ? 'N/A' : '$ ' . number_format($v['costo'], 0, ',', '.');
                    echo "<tr>";
                    echo "<td>{$v['fecha']}</td>";
                    echo "<td>{$v['nombre']}</td>";
                    echo "<td>{$v['cedula']}</td>";
                    echo "<td>{$v['ruta']}</td>";
                    echo "<td>{$v['tipo_vehiculo']}</td>";
                    echo "<td>$valor</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No hay viajes extras</p>";
            }
        } else {
            // ==========================================
            // DEMÁS PUESTOS (con presupuesto)
            // ==========================================
            echo "<div class='presupuesto'>💰 Presupuesto para este puesto: $" . number_format($presupuesto, 0, ',', '.') . "</div>";
            echo "<table>";
            echo "<tr><th>Fecha</th><th>Nombre</th><th>Cédula</th><th>Ruta</th><th>Tipo Vehículo</th><th>Valor</th><th>Acumulado</th></tr>";
            foreach ($data['viajes'] as $v) {
                $valor = ($v['costo'] === 'N/A') ? 'N/A' : '$ ' . number_format($v['costo'], 0, ',', '.');
                $acum = ($v['acumulado'] === 'N/A') ? 'N/A' : '$ ' . number_format($v['acumulado'], 0, ',', '.');
                $clase = ($v['color'] === 'Verde') ? 'verde' : 'rojo';
                echo "<tr class='$clase'>";
                echo "<td>{$v['fecha']}</td>";
                echo "<td>{$v['nombre']}</td>";
                echo "<td>{$v['cedula']}</td>";
                echo "<td>{$v['ruta']}</td>";
                echo "<td>{$v['tipo_vehiculo']}</td>";
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
    exit;
}

// ==============================================
// MOSTRAR FORMULARIO (si no se envió nada)
// ==============================================

// Obtener lista de empresas que empiezan con "P."
$empresas = [];
$result = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa LIKE 'P.%' ORDER BY empresa");
while ($row = $result->fetch_assoc()) {
    $empresas[] = $row['empresa'];
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Generador de Informe de Viajes</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        label {
            display: block;
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 5px;
            color: #555;
        }
        input[type="date"], input[type="number"], select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .checkbox-group {
            border: 1px solid #ccc;
            border-radius: 5px;
            padding: 15px;
            max-height: 250px;
            overflow-y: auto;
        }
        .checkbox-item {
            margin: 8px 0;
        }
        .checkbox-item input {
            margin-right: 10px;
            transform: scale(1.1);
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            margin-bottom: 15px;
        }
        .btn-group button {
            flex: 1;
            padding: 8px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-group button:hover {
            background: #5a6268;
        }
        button[type="submit"] {
            width: 100%;
            margin-top: 25px;
            padding: 12px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 18px;
            cursor: pointer;
        }
        button[type="submit"]:hover {
            background: #218838;
        }
        .info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 14px;
            color: #0066cc;
        }
    </style>
    <script>
        function seleccionarTodos() {
            var checkboxes = document.querySelectorAll('input[name="empresas[]"]');
            checkboxes.forEach(function(cb) {
                cb.checked = true;
            });
        }
        
        function deseleccionarTodos() {
            var checkboxes = document.querySelectorAll('input[name="empresas[]"]');
            checkboxes.forEach(function(cb) {
                cb.checked = false;
            });
        }
    </script>
</head>
<body>
    <div class="container">
        <h1>📋 Generador de Informe de Viajes</h1>
        
        <form method="POST">
            <label>📅 Fecha desde:</label>
            <input type="date" name="fecha_desde" required value="<?php echo date('Y-m-01'); ?>">
            
            <label>📅 Fecha hasta:</label>
            <input type="date" name="fecha_hasta" required value="<?php echo date('Y-m-t'); ?>">
            
            <label>🏥 Puestos de salud (puede seleccionar varios):</label>
            <div class="btn-group">
                <button type="button" onclick="seleccionarTodos()">✅ Seleccionar todos</button>
                <button type="button" onclick="deseleccionarTodos()">❌ Deseleccionar todos</button>
            </div>
            <div class="checkbox-group">
                <?php foreach ($empresas as $e): ?>
                    <div class="checkbox-item">
                        <input type="checkbox" name="empresas[]" value="<?php echo htmlspecialchars($e); ?>" id="chk_<?php echo md5($e); ?>">
                        <label for="chk_<?php echo md5($e); ?>" style="display: inline; font-weight: normal;"><?php echo htmlspecialchars($e); ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <label>💰 Presupuesto (solo para puestos que NO son P.nazareth):</label>
            <input type="number" name="presupuesto" step="1000" value="13000000" style="font-family: monospace;">
            
            <button type="submit" name="generar">📄 Generar Informe Word</button>
        </form>
        
        <div class="info">
            <strong>ℹ️ Información:</strong><br>
            • <strong>p.nazareth</strong> (cualquier variación mayúscula/minúscula): las rutas que contengan "Maicao" o "Riohacha" aparecen SOLO en la tabla llamada <strong>"EXTRAS"</strong>.<br>
            • Las rutas que NO contengan "Maicao" ni "Riohacha" aparecen SOLO en la tabla <strong>"VIAJES NORMALES"</strong>.<br>
            • <strong>Los demás puestos</strong>: se aplica el presupuesto. Los viajes se colorean en VERDE (acumulado ≤ presupuesto) o ROJO (acumulado > presupuesto).<br>
            • Si una ruta no tiene clasificación o tarifa, el valor se muestra como <strong>N/A</strong>.<br>
            • Puede seleccionar <strong>varios puestos</strong> a la vez y se generará un solo informe con todos.
        </div>
    </div>
</body>
</html>