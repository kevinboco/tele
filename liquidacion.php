<?php
include("nav.php");

// =========================================
// CONEXIÓN BD (igual que en otras vistas)
// =========================================
$conn = new mysqli(
    "mysql.hostinger.com",
    "u648222299_keboco5",
    "Bucaramanga3011",
    "u648222299_viajes"
);
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

/* =======================================================
   Guardar tarifas por vehículo y empresa (AJAX)
   (ahora soporta el campo 'siapana')
======================================================= */
if (isset($_POST['guardar_tarifa'])) {
    $empresa  = $conn->real_escape_string($_POST['empresa']);
    $vehiculo = $conn->real_escape_string($_POST['tipo_vehiculo']);
    $campo    = $conn->real_escape_string($_POST['campo']); // completo|medio|extra|carrotanque|siapana
    $valor    = (int)$_POST['valor'];

    // ⚠️ Validar campo
    $allow = ['completo','medio','extra','carrotanque','siapana'];
    if (!in_array($campo, $allow, true)) {
        echo "error: campo inválido";
        exit;
    }

    // Asegurar que exista el registro
    $conn->query("INSERT IGNORE INTO tarifas (empresa, tipo_vehiculo) 
                  VALUES ('$empresa', '$vehiculo')");

    // Actualizar el campo seleccionado
    $sql = "UPDATE tarifas 
            SET $campo = $valor 
            WHERE empresa='$empresa' AND tipo_vehiculo='$vehiculo'";

    echo $conn->query($sql) ? "ok" : "error: " . $conn->error;
    exit;
}

/* =======================================================
   Guardar CLASIFICACIÓN de rutas (manual) - AJAX
   (completo/medio/extra/siapana/carrotanque)
======================================================= */
if (isset($_POST['guardar_clasificacion'])) {
    $ruta     = $conn->real_escape_string($_POST['ruta']);
    $vehiculo = $conn->real_escape_string($_POST['tipo_vehiculo']);
    $clasif   = $conn->real_escape_string($_POST['clasificacion']);

    $allowClasif = ['completo','medio','extra','siapana','carrotanque'];
    if (!in_array($clasif, $allowClasif, true)) {
        echo "error: clasificación inválida";
        exit;
    }

    $sql = "INSERT INTO ruta_clasificacion (ruta, tipo_vehiculo, clasificacion)
            VALUES ('$ruta', '$vehiculo', '$clasif')
            ON DUPLICATE KEY UPDATE clasificacion = VALUES(clasificacion)";

    echo $conn->query($sql) ? "ok" : ("error: " . $conn->error);
    exit;
}

/* =======================================================
   Endpoint AJAX: viajes por conductor
======================================================= */
if (isset($_GET['viajes_conductor'])) {
    $nombre  = $conn->real_escape_string($_GET['viajes_conductor']);
    $desde   = $_GET['desde'];
    $hasta   = $_GET['hasta'];
    $empresa = $_GET['empresa'] ?? "";

    $sql = "SELECT fecha, ruta, empresa, tipo_vehiculo
            FROM viajes
            WHERE nombre = '$nombre'
              AND fecha BETWEEN '$desde' AND '$hasta'";

    if ($empresa !== "") {
        $empresa = $conn->real_escape_string($empresa);
        $sql .= " AND empresa = '$empresa'";
    }

    $sql .= " ORDER BY fecha ASC";
    $res = $conn->query($sql);

    if ($res && $res->num_rows > 0) {
        echo "<table class='table table-sm table-striped'>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Ruta</th>
                        <th>Empresa</th>
                        <th>Vehículo</th>
                    </tr>
                </thead>
                <tbody>";
        while ($r = $res->fetch_assoc()) {
            echo "<tr>
                    <td>" . htmlspecialchars($r['fecha']) . "</td>
                    <td>" . htmlspecialchars($r['ruta']) . "</td>
                    <td>" . htmlspecialchars($r['empresa']) . "</td>
                    <td>" . htmlspecialchars($r['tipo_vehiculo']) . "</td>
                 </tr>";
        }
        echo "  </tbody>
              </table>";
    } else {
        echo "<p>No se encontraron viajes para este conductor en ese rango.</p>";
    }
    exit;
}

/* =======================================================
   FORMULARIO INICIAL (si no hay rango seleccionado)
======================================================= */

if (!isset($_GET['desde']) || !isset($_GET['hasta'])) {
    // Traer empresas existentes
    $empresas = [];
    $resEmp = $conn->query("
        SELECT DISTINCT empresa 
        FROM viajes 
        WHERE empresa IS NOT NULL AND empresa <> '' 
        ORDER BY empresa ASC
    ");
    if ($resEmp) {
        while ($r = $resEmp->fetch_assoc()) {
            $empresas[] = $r['empresa'];
        }
    }
    ?>
    <div class="container mt-4">
        <h2>Filtrar viajes</h2>
        <h4 class="mt-3">Filtrar viajes por rango</h4>
        <p>Selecciona el periodo y (opcional) una empresa.</p>

        <form method="get" class="row g-3">
            <div class="col-md-3">
                <label for="desde" class="form-label">Desde</label>
                <input type="date" name="desde" id="desde" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label for="hasta" class="form-label">Hasta</label>
                <input type="date" name="hasta" id="hasta" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label for="empresa" class="form-label">Empresa</label>
                <select name="empresa" id="empresa" class="form-select">
                    <option value="">-- Todas --</option>
                    <?php foreach ($empresas as $e): ?>
                        <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
            </div>
        </form>
    </div>
    <?php
    exit;
}

/* =======================================================
   CUANDO YA HAY RANGO SELECCIONADO
======================================================= */

$desde         = $_GET['desde'];
$hasta         = $_GET['hasta'];
$empresaFiltro = $_GET['empresa'] ?? "";

// --- Cargar clasificaciones de rutas ---
$clasif_rutas = [];
$resClasif = $conn->query("SELECT ruta, tipo_vehiculo, clasificacion FROM ruta_clasificacion");
if ($resClasif) {
    while ($r = $resClasif->fetch_assoc()) {
        $key = mb_strtolower(trim($r['ruta'] . '|' . $r['tipo_vehiculo']), 'UTF-8');
        $clasif_rutas[$key] = $r['clasificacion']; // completo|medio|extra|siapana|carrotanque
    }
}

// --- Traer viajes del rango ---
$sql = "SELECT nombre, ruta, empresa, tipo_vehiculo
        FROM viajes
        WHERE fecha BETWEEN '$desde' AND '$hasta'";

if ($empresaFiltro !== "") {
    $empresaFiltroEsc = $conn->real_escape_string($empresaFiltro);
    $sql .= " AND empresa = '$empresaFiltroEsc'";
}

$res = $conn->query($sql);

$datos       = [];  // Conteo por conductor
$vehiculos   = [];  // Tipos de vehículo encontrados
$rutasUnicas = [];  // Rutas únicas para panel de clasificación

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $nombre   = $row['nombre'];
        $ruta     = $row['ruta'];
        $vehiculo = $row['tipo_vehiculo'];

        // clave normalizada ruta+vehículo
        $keyRuta = mb_strtolower(trim($ruta . '|' . $vehiculo), 'UTF-8');

        // Guardar lista de rutas únicas (para el panel de clasificación)
        if (!isset($rutasUnicas[$keyRuta])) {
            $rutasUnicas[$keyRuta] = [
                'ruta'          => $ruta,
                'vehiculo'      => $vehiculo,
                'clasificacion' => $clasif_rutas[$keyRuta] ?? ''
            ];
        }

        // Lista de tipos de vehículo (para las tarjetas de tarifas)
        if (!in_array($vehiculo, $vehiculos, true)) {
            $vehiculos[] = $vehiculo;
        }

        // Inicializar datos del conductor
        if (!isset($datos[$nombre])) {
            $datos[$nombre] = [
                "vehiculo"      => $vehiculo,
                "completos"     => 0,
                "medios"        => 0,
                "extras"        => 0,
                "carrotanques"  => 0,
                "siapana"       => 0,
            ];
        }

        // Clasificación MANUAL de la ruta
        $clasifRuta = $clasif_rutas[$keyRuta] ?? '';

        // Si la ruta todavía no tiene clasificación, NO se suma a ninguna columna
        if ($clasifRuta === '') {
            continue;
        }

        switch ($clasifRuta) {
            case 'completo':
                $datos[$nombre]["completos"]++;
                break;
            case 'medio':
                $datos[$nombre]["medios"]++;
                break;
            case 'extra':
                $datos[$nombre]["extras"]++;
                break;
            case 'siapana':
                $datos[$nombre]["siapana"]++;
                break;
            case 'carrotanque':
                $datos[$nombre]["carrotanques"]++;
                break;
        }
    }
}

/* =======================================================
   Empresas y tarifas guardadas para la empresa filtrada
======================================================= */
$empresas = [];
$resEmp = $conn->query("
    SELECT DISTINCT empresa 
    FROM viajes 
    WHERE empresa IS NOT NULL AND empresa<>'' 
    ORDER BY empresa ASC
");
if ($resEmp) {
    while ($r = $resEmp->fetch_assoc()) {
        $empresas[] = $r['empresa'];
    }
}

$tarifas_guardadas = [];
if ($empresaFiltro !== "") {
    $empresaFiltroEsc = $conn->real_escape_string($empresaFiltro);
    $resTarifas = $conn->query("SELECT * FROM tarifas WHERE empresa='$empresaFiltroEsc'");
    if ($resTarifas) {
        while ($r = $resTarifas->fetch_assoc()) {
            $tarifas_guardadas[$r['tipo_vehiculo']] = $r;
        }
    }
}

// =======================================================
//  CÁLCULO DE TOTALES + ANTICIPO 30% Y SALDO 70%
// =======================================================
function nf($n) {
    return number_format((float)$n, 0, ',', '.');
}

$total_general_viajes   = 0;
$total_general_mensual  = 0; // por si luego manejas mensualidad fija
$resumen_conductores    = [];

foreach ($datos as $nombre => $info) {
    $vehiculo = $info['vehiculo'];

    // Tarifas para el tipo de vehículo (si no hay, todo 0)
    $t = $tarifas_guardadas[$vehiculo] ?? [
        'completo'    => 0,
        'medio'       => 0,
        'extra'       => 0,
        'carrotanque' => 0,
        'siapana'     => 0,
    ];

    $tarifa_completo    = $t['completo']    ?? 0;
    $tarifa_medio       = $t['medio']       ?? 0;
    $tarifa_extra       = $t['extra']       ?? 0;
    $tarifa_carrotanque = $t['carrotanque'] ?? 0;
    $tarifa_siapana     = $t['siapana']     ?? 0;

    // Total por viajes (según cantidad * tarifa)
    $total_viajes = 
        $info['completos']    * $tarifa_completo    +
        $info['medios']       * $tarifa_medio       +
        $info['extras']       * $tarifa_extra       +
        $info['carrotanques'] * $tarifa_carrotanque +
        $info['siapana']      * $tarifa_siapana;

    // Aquí entra tu necesidad:
    // 💸 ANTICIPO 30% (lo que le das al conductor si la empresa solo paga el 30%)
    // 📉 SALDO 70% (lo que queda pendiente)
    $anticipo_30 = round($total_viajes * 0.30);
    $saldo_70    = $total_viajes - $anticipo_30;

    $total_general_viajes += $total_viajes;

    $resumen_conductores[] = [
        'nombre'        => $nombre,
        'vehiculo'      => $vehiculo,
        'completos'     => $info['completos'],
        'medios'        => $info['medios'],
        'extras'        => $info['extras'],
        'carrotanques'  => $info['carrotanques'],
        'siapana'       => $info['siapana'],
        'total_viajes'  => $total_viajes,
        'anticipo_30'   => $anticipo_30,
        'saldo_70'      => $saldo_70,
    ];
}

?>
<div class="container mt-4">

    <h2>Liquidación de Conductores</h2>
    <p>
        <strong>Periodo:</strong>
        <?= htmlspecialchars($desde) ?> → <?= htmlspecialchars($hasta) ?>
        <?php if ($empresaFiltro !== ""): ?>
            • <strong>Empresa:</strong> <?= htmlspecialchars($empresaFiltro) ?>
        <?php else: ?>
            • <strong>Empresa:</strong> Todas
        <?php endif; ?>
    </p>

    <!-- ==========================
         TARIFAS POR TIPO VEHÍCULO
    =========================== -->
    <h3>Tarifas por Tipo de Vehículo</h3>
    <p class="text-muted">
        Configura aquí los valores de viaje completo, medio, extra, carrotanque y siapana 
        para la empresa seleccionada.
    </p>

    <form method="get" class="row g-3 mb-3">
        <input type="hidden" name="desde" value="<?= htmlspecialchars($desde) ?>">
        <input type="hidden" name="hasta" value="<?= htmlspecialchars($hasta) ?>">

        <div class="col-md-4">
            <label for="empresa2" class="form-label">Empresa</label>
            <select name="empresa" id="empresa2" class="form-select" onchange="this.form.submit()">
                <option value="">-- Todas --</option>
                <?php foreach ($empresas as $e): ?>
                    <option value="<?= htmlspecialchars($e) ?>"
                        <?= $e === $empresaFiltro ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-secondary w-100">Filtrar</button>
        </div>
    </form>

    <?php if ($empresaFiltro !== ""): ?>
        <div class="table-responsive mb-4">
            <table class="table table-bordered table-sm align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Tipo de Vehículo</th>
                        <th>Carrotanque</th>
                        <th>Siapana</th>
                        <th>Viaje Completo</th>
                        <th>Viaje Medio</th>
                        <th>Viaje Extra</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($vehiculos as $v): 
                    $tar = $tarifas_guardadas[$v] ?? [
                        'carrotanque' => 0,
                        'siapana'     => 0,
                        'completo'    => 0,
                        'medio'       => 0,
                        'extra'       => 0,
                    ];
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($v) ?></strong></td>
                        <?php foreach (['carrotanque','siapana','completo','medio','extra'] as $campo): ?>
                            <td style="min-width:120px;">
                                <form method="post" class="d-flex gap-1 align-items-center">
                                    <input type="hidden" name="guardar_tarifa" value="1">
                                    <input type="hidden" name="empresa" value="<?= htmlspecialchars($empresaFiltro) ?>">
                                    <input type="hidden" name="tipo_vehiculo" value="<?= htmlspecialchars($v) ?>">
                                    <input type="hidden" name="campo" value="<?= $campo ?>">
                                    <input type="number"
                                           name="valor"
                                           class="form-control form-control-sm"
                                           value="<?= (int)$tar[$campo] ?>"
                                           min="0" step="1000">
                                    <button class="btn btn-sm btn-outline-primary">Guardar</button>
                                </form>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">
            Selecciona una empresa para configurar tarifas específicas.
        </p>
    <?php endif; ?>

    <!-- ==========================
         CLASIFICACIÓN DE RUTAS
    =========================== -->
    <h4 class="mt-4">Clasificación de Rutas <small class="text-muted">Se guarda en BD</small></h4>
    <p class="text-muted">
        Ajusta qué tipo es cada ruta. Si aparece una ruta nueva, la verás aquí y la clasificas una vez.
    </p>

    <div class="table-responsive mb-4">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Ruta</th>
                    <th>Vehículo</th>
                    <th>Clasificación</th>
                    <th>Guardar</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rutasUnicas)): ?>
                <tr><td colspan="4" class="text-center text-muted">No hay rutas en este rango.</td></tr>
            <?php else: ?>
                <?php foreach ($rutasUnicas as $keyRuta => $infoRuta): ?>
                    <tr>
                        <td><?= htmlspecialchars($infoRuta['ruta']) ?></td>
                        <td><?= htmlspecialchars($infoRuta['vehiculo']) ?></td>
                        <td>
                            <form method="post" class="d-flex gap-2 align-items-center">
                                <input type="hidden" name="guardar_clasificacion" value="1">
                                <input type="hidden" name="ruta" value="<?= htmlspecialchars($infoRuta['ruta']) ?>">
                                <input type="hidden" name="tipo_vehiculo" value="<?= htmlspecialchars($infoRuta['vehiculo']) ?>">
                                <select name="clasificacion" class="form-select form-select-sm">
                                    <option value="">Sin clasificar</option>
                                    <?php
                                    $opts = [
                                        'completo'    => 'Completo',
                                        'medio'       => 'Medio',
                                        'extra'       => 'Extra',
                                        'siapana'     => 'Siapana',
                                        'carrotanque' => 'Carrotanque',
                                    ];
                                    foreach ($opts as $val => $txt):
                                        $sel = ($infoRuta['clasificacion'] === $val) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $val ?>" <?= $sel ?>><?= $txt ?></option>
                                    <?php endforeach; ?>
                                </select>
                        </td>
                        <td>
                                <button class="btn btn-sm btn-outline-success">Guardar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ==========================
         RESUMEN POR CONDUCTOR
    =========================== -->
    <h3 class="mt-4">✈️ Resumen por Conductor</h3>

    <?php if (empty($resumen_conductores)): ?>
        <p class="text-muted">No hay viajes clasificados en este rango y empresa.</p>
    <?php else: ?>
        <div class="table-responsive mb-4">
            <table class="table table-striped table-bordered table-sm align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Conductor</th>
                        <th>Tipo</th>
                        <th>C</th>
                        <th>M</th>
                        <th>E</th>
                        <th>S</th>
                        <th>CT</th>
                        <th>Total Viajes</th>
                        <th>Anticipo 30%</th>
                        <th>Saldo 70%</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($resumen_conductores as $rc): ?>
                    <tr>
                        <td>
                            <a href="#"
                               onclick="cargarViajesConductor('<?= htmlspecialchars($rc['nombre']) ?>'); return false;">
                                <?= htmlspecialchars($rc['nombre']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($rc['vehiculo']) ?></td>
                        <td><?= (int)$rc['completos'] ?></td>
                        <td><?= (int)$rc['medios'] ?></td>
                        <td><?= (int)$rc['extras'] ?></td>
                        <td><?= (int)$rc['siapana'] ?></td>
                        <td><?= (int)$rc['carrotanques'] ?></td>
                        <td>$<?= nf($rc['total_viajes']) ?></td>
                        <td class="text-primary fw-bold">$<?= nf($rc['anticipo_30']) ?></td>
                        <td class="text-danger fw-bold">$<?= nf($rc['saldo_70']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="7" class="text-end">TOTAL GENERAL VIAJES:</th>
                        <th colspan="3">$<?= nf($total_general_viajes) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>

    <!-- ==========================
         VIAJES DEL CONDUCTOR (AJAX)
    =========================== -->
    <h4>Viajes del Conductor</h4>
    <p class="text-muted">Selecciona un conductor en la tabla para ver sus viajes aquí.</p>
    <div id="viajes_conductor_box" class="mb-5"></div>

</div>

<script>
function cargarViajesConductor(nombre) {
    const params = new URLSearchParams({
        viajes_conductor: nombre,
        desde: "<?= htmlspecialchars($desde) ?>",
        hasta: "<?= htmlspecialchars($hasta) ?>",
        empresa: "<?= htmlspecialchars($empresaFiltro) ?>"
    });

    fetch("liquidacion.php?" + params.toString())
        .then(r => r.text())
        .then(html => {
            document.getElementById('viajes_conductor_box').innerHTML = html;
        })
        .catch(err => {
            console.error(err);
            alert("Error cargando viajes del conductor");
        });
}
</script>
