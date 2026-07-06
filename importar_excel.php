<?php
/**
 * Importador de Excel a la tabla viajes
 * Permite seleccionar hoja y mapear columnas manualmente
 */

// ============================================================
// 1. CONFIGURACIÓN
// ============================================================

// Configuración de la Base de Datos
define('DB_HOST', 'mysql.hostinger.com');
define('DB_USER', 'u648222299_keboco5');
define('DB_PASS', 'Bucaramanga3011');
define('DB_NAME', 'u648222299_viajes');

// Directorio para subir archivos temporales
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// Crear directorio si no existe
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// ============================================================
// 2. FUNCIONES DE BASE DE DATOS
// ============================================================

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// ============================================================
// 3. FUNCIONES PARA LEER EXCEL (SIN LIBRERÍAS EXTERNAS)
// ============================================================

/**
 * Lee un archivo Excel (.xlsx) usando SimpleXLSX (librería incluida)
 * NOTA: Necesitas descargar SimpleXLSX.php o usar PHPSpreadsheet
 * 
 * Para este ejemplo, asumimos que usas PHPSpreadsheet
 * Instalación: composer require phpoffice/phpspreadsheet
 */
function leerExcel($archivo) {
    require_once 'vendor/autoload.php';
    
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($archivo);
    $hojas = [];
    
    foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
        $nombreHoja = $worksheet->getTitle();
        $datos = $worksheet->toArray();
        
        // Limpiar filas vacías
        $datos = array_filter($datos, function($fila) {
            return array_filter($fila);
        });
        
        // Reindexar
        $datos = array_values($datos);
        
        $hojas[$nombreHoja] = [
            'nombre' => $nombreHoja,
            'datos' => $datos,
            'columnas' => !empty($datos) ? $datos[0] : [],
            'total_filas' => count($datos) - 1 // Restar la cabecera
        ];
    }
    
    return $hojas;
}

// ============================================================
// 4. PROCESAR IMPORTACIÓN
// ============================================================

function procesarImportacion($archivo, $hoja, $mapeo) {
    $conn = getDBConnection();
    
    // Cargar el Excel
    require_once 'vendor/autoload.php';
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($archivo);
    $worksheet = $spreadsheet->getSheetByName($hoja);
    
    if (!$worksheet) {
        throw new Exception("La hoja '$hoja' no existe en el archivo.");
    }
    
    $datos = $worksheet->toArray();
    
    // Limpiar filas vacías
    $datos = array_filter($datos, function($fila) {
        return array_filter($fila);
    });
    $datos = array_values($datos);
    
    if (empty($datos)) {
        throw new Exception("La hoja '$hoja' está vacía.");
    }
    
    // Obtener cabeceras
    $headers = $datos[0];
    $filas = array_slice($datos, 1);
    
    // Mapear índices de columnas
    $indices = [];
    foreach ($mapeo as $campo => $columna_excel) {
        if ($columna_excel && $columna_excel !== 'auto') {
            $indice = array_search($columna_excel, $headers);
            if ($indice !== false) {
                $indices[$campo] = $indice;
            }
        }
    }
    
    // Detectar empresa y tipo de vehículo automáticamente si está configurado
    $detectar_empresa = isset($mapeo['empresa']) && $mapeo['empresa'] === 'auto';
    $detectar_vehiculo = isset($mapeo['tipo_vehiculo']) && $mapeo['tipo_vehiculo'] === 'auto';
    $empresa_defecto = $mapeo['empresa_defecto'] ?? 'Hospital';
    $vehiculo_defecto = $mapeo['tipo_vehiculo_defecto'] ?? 'Burbuja';
    
    $importados = 0;
    $errores = [];
    
    foreach ($filas as $fila) {
        try {
            // Construir datos para insertar
            $datos_insert = [];
            
            foreach ($indices as $campo => $indice) {
                $valor = isset($fila[$indice]) ? trim($fila[$indice]) : '';
                $datos_insert[$campo] = $valor;
            }
            
            // Detectar empresa automáticamente
            if ($detectar_empresa) {
                $ruta = $datos_insert['ruta'] ?? '';
                $empresa = detectarEmpresa($ruta);
                $datos_insert['empresa'] = $empresa;
            } else {
                $datos_insert['empresa'] = $empresa_defecto;
            }
            
            // Detectar tipo de vehículo automáticamente
            if ($detectar_vehiculo) {
                $ruta = $datos_insert['ruta'] ?? '';
                $vehiculo = detectarVehiculo($ruta);
                $datos_insert['tipo_vehiculo'] = $vehiculo;
            } else {
                $datos_insert['tipo_vehiculo'] = $vehiculo_defecto;
            }
            
            // Asignar origen = 'excel'
            $datos_insert['origen'] = 'excel';
            
            // Insertar en la base de datos
            $sql = "INSERT INTO viajes 
                    (fecha, cedula, nombre, ruta, pago_parcial, empresa, tipo_vehiculo, origen, pagado) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)";
            
            $stmt = $conn->prepare($sql);
            
            // Limpiar valores
            $fecha = !empty($datos_insert['fecha']) ? date('Y-m-d', strtotime($datos_insert['fecha'])) : date('Y-m-d');
            $cedula = $datos_insert['cedula'] ?? null;
            $nombre = $datos_insert['nombre'] ?? null;
            $ruta = $datos_insert['ruta'] ?? null;
            $pago_parcial = !empty($datos_insert['pago_parcial']) ? intval($datos_insert['pago_parcial']) : null;
            $empresa = $datos_insert['empresa'] ?? 'Hospital';
            $tipo_vehiculo = $datos_insert['tipo_vehiculo'] ?? 'Burbuja';
            $origen = 'excel';
            
            $stmt->bind_param(
                "ssssssss",
                $fecha,
                $cedula,
                $nombre,
                $ruta,
                $pago_parcial,
                $empresa,
                $tipo_vehiculo,
                $origen
            );
            
            if ($stmt->execute()) {
                $importados++;
            } else {
                $errores[] = "Error en fila: " . $stmt->error;
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $errores[] = "Error en fila: " . $e->getMessage();
        }
    }
    
    $conn->close();
    
    return [
        'importados' => $importados,
        'errores' => $errores,
        'total' => count($filas)
    ];
}

/**
 * Detecta la empresa automáticamente desde la ruta
 */
function detectarEmpresa($ruta) {
    $ruta_lower = strtolower($ruta);
    
    $patrones = [
        '/icbf/i' => 'ICBF',
        '/sunny\s+app/i' => 'Sunny App',
        '/acpm/i' => 'ACPM',
        '/cava/i' => 'Cava',
        '/p\.campaña/i' => 'P.Campaña',
        '/p\.nazareth/i' => 'P.Nazareth',
        '/p\.siapana/i' => 'P.Siapana',
        '/p\.paraiso/i' => 'P.Paraiso',
        '/hospital\s+de\s+campaña/i' => 'Hospital Campaña',
        '/hospital\s+nazareth/i' => 'Hospital Nazareth'
    ];
    
    foreach ($patrones as $patron => $empresa) {
        if (preg_match($patron, $ruta_lower)) {
            return $empresa;
        }
    }
    
    return 'Hospital';
}

/**
 * Detecta el tipo de vehículo automáticamente desde la ruta
 */
function detectarVehiculo($ruta) {
    $ruta_lower = strtolower($ruta);
    
    $patrones = [
        '/camión\s+750/i' => 'Camión 750',
        '/camión\s+350/i' => 'Camión 350',
        '/carrotanque/i' => 'Carrotanque',
        '/volqueta/i' => 'Volqueta',
        '/camioneta/i' => 'Camioneta',
        '/copetrana/i' => 'Copetrana'
    ];
    
    foreach ($patrones as $patron => $vehiculo) {
        if (preg_match($patron, $ruta_lower)) {
            return $vehiculo;
        }
    }
    
    return 'Burbuja';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importador de Excel - Viajes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card { border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .card-header { background: #2c3e50; color: white; border-radius: 12px 12px 0 0; }
        .btn-primary { background: #2980b9; border-color: #2980b9; }
        .btn-success { background: #27ae60; border-color: #27ae60; }
        .table-preview { font-size: 13px; }
        .table-preview td, .table-preview th { padding: 4px 8px; }
        .badge-excel { background: #217346; }
        .badge-telegram { background: #0088cc; }
    </style>
</head>
<body>
    <div class="container my-5">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">📊 Importador de Excel - Viajes</h4>
                    </div>
                    <div class="card-body">
                        
                        <?php if (isset($_GET['mensaje'])): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <?php echo htmlspecialchars($_GET['mensaje']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_GET['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?php echo htmlspecialchars($_GET['error']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- ============================================================ -->
                        <!-- PASO 1: SUBIR ARCHIVO -->
                        <!-- ============================================================ -->
                        <form method="POST" enctype="multipart/form-data" action="">
                            <input type="hidden" name="action" value="cargar">
                            
                            <div class="row g-3 align-items-end">
                                <div class="col-md-8">
                                    <label class="form-label fw-bold">📁 Seleccionar archivo Excel (.xlsx)</label>
                                    <input type="file" class="form-control" name="archivo_excel" accept=".xlsx,.xls" required>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary w-100">
                                        📤 Cargar archivo
                                    </button>
                                </div>
                            </div>
                        </form>

                        <hr>

                        <?php
                        // ============================================================
                        // PROCESAR CARGA DE ARCHIVO
                        // ============================================================
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
                            
                            if ($_POST['action'] === 'cargar' && isset($_FILES['archivo_excel'])) {
                                $archivo = $_FILES['archivo_excel'];
                                
                                if ($archivo['error'] !== 0) {
                                    echo '<div class="alert alert-danger">Error al subir el archivo: ' . $archivo['error'] . '</div>';
                                } else {
                                    // Mover archivo a uploads
                                    $nombre_archivo = time() . '_' . basename($archivo['name']);
                                    $ruta_archivo = UPLOAD_DIR . $nombre_archivo;
                                    
                                    if (move_uploaded_file($archivo['tmp_name'], $ruta_archivo)) {
                                        try {
                                            // Leer el Excel
                                            $hojas = leerExcel($ruta_archivo);
                                            
                                            if (empty($hojas)) {
                                                echo '<div class="alert alert-warning">No se encontraron hojas en el archivo.</div>';
                                            } else {
                                                // Guardar en sesión el archivo para usarlo después
                                                $_SESSION['archivo_excel'] = $ruta_archivo;
                                                $_SESSION['hojas_excel'] = $hojas;
                                                
                                                // Mostrar selector de hoja
                                                mostrarSelectorHoja($hojas);
                                            }
                                        } catch (Exception $e) {
                                            echo '<div class="alert alert-danger">Error al leer el archivo: ' . $e->getMessage() . '</div>';
                                        }
                                    } else {
                                        echo '<div class="alert alert-danger">Error al mover el archivo.</div>';
                                    }
                                }
                            }
                            
                            // ============================================================
                            // PROCESAR MAPEO Y GUARDAR
                            // ============================================================
                            if ($_POST['action'] === 'guardar') {
                                $hoja_seleccionada = $_POST['hoja'] ?? '';
                                $mapeo = $_POST['mapeo'] ?? [];
                                $archivo = $_SESSION['archivo_excel'] ?? null;
                                
                                if (!$archivo) {
                                    echo '<div class="alert alert-danger">No se encontró el archivo. Por favor, cárgalo de nuevo.</div>';
                                } elseif (!$hoja_seleccionada) {
                                    echo '<div class="alert alert-danger">Por favor, selecciona una hoja.</div>';
                                } else {
                                    try {
                                        $resultado = procesarImportacion($archivo, $hoja_seleccionada, $mapeo);
                                        
                                        if ($resultado['importados'] > 0) {
                                            $mensaje = "✅ Se importaron {$resultado['importados']} registros correctamente.";
                                            if (!empty($resultado['errores'])) {
                                                $mensaje .= " Hubo " . count($resultado['errores']) . " errores.";
                                            }
                                            header("Location: ?mensaje=" . urlencode($mensaje));
                                            exit;
                                        } else {
                                            $error = "❌ No se importó ningún registro.";
                                            if (!empty($resultado['errores'])) {
                                                $error .= " Errores: " . implode(", ", $resultado['errores']);
                                            }
                                            header("Location: ?error=" . urlencode($error));
                                            exit;
                                        }
                                    } catch (Exception $e) {
                                        header("Location: ?error=" . urlencode($e->getMessage()));
                                        exit;
                                    }
                                }
                            }
                        }
                        ?>

                        <!-- ============================================================ -->
                        <!-- MOSTRAR HOJAS Y MAPEO (si hay archivo cargado) -->
                        <!-- ============================================================ -->
                        <?php if (isset($_SESSION['hojas_excel'])): ?>
                            <?php
                            $hojas = $_SESSION['hojas_excel'];
                            $hoja_actual = $_POST['hoja'] ?? array_key_first($hojas);
                            ?>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="guardar">
                                <input type="hidden" name="archivo" value="<?php echo htmlspecialchars($_SESSION['archivo_excel']); ?>">
                                
                                <!-- Selector de hoja -->
                                <div class="row g-3 align-items-end mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">📋 Seleccionar hoja (libro)</label>
                                        <select name="hoja" class="form-select" onchange="this.form.submit()">
                                            <?php foreach ($hojas as $nombre => $info): ?>
                                                <option value="<?php echo htmlspecialchars($nombre); ?>" 
                                                    <?php echo ($nombre === $hoja_actual) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($nombre); ?> 
                                                    (<?php echo $info['total_filas']; ?> registros)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <span class="badge bg-secondary">Total: <?php echo $hojas[$hoja_actual]['total_filas']; ?> registros</span>
                                    </div>
                                </div>
                                
                                <?php
                                // Obtener columnas de la hoja seleccionada
                                $columnas = $hojas[$hoja_actual]['columnas'];
                                $datos_preview = array_slice($hojas[$hoja_actual]['datos'], 0, 4);
                                ?>
                                
                                <!-- Previsualización -->
                                <div class="table-responsive mb-4">
                                    <h6 class="fw-bold">📊 Previsualización (primeros 3 registros)</h6>
                                    <table class="table table-bordered table-sm table-preview">
                                        <thead class="table-light">
                                            <tr>
                                                <?php foreach ($columnas as $col): ?>
                                                    <th><?php echo htmlspecialchars($col); ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php for ($i = 1; $i < min(4, count($datos_preview)); $i++): ?>
                                                <tr>
                                                    <?php foreach ($datos_preview[$i] as $valor): ?>
                                                        <td><?php echo htmlspecialchars($valor); ?></td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endfor; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Mapeo de columnas -->
                                <div class="card bg-light mb-4">
                                    <div class="card-body">
                                        <h6 class="fw-bold">🔗 Mapeo de columnas</h6>
                                        <p class="text-muted small">Selecciona qué columna del Excel corresponde a cada campo de la tabla.</p>
                                        
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">📅 fecha</label>
                                                <select name="mapeo[fecha]" class="form-select form-select-sm">
                                                    <option value="">-- Seleccionar --</option>
                                                    <?php foreach ($columnas as $col): ?>
                                                        <option value="<?php echo htmlspecialchars($col); ?>" 
                                                            <?php echo (strtolower($col) === 'fecha') ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($col); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">🆔 cedula</label>
                                                <select name="mapeo[cedula]" class="form-select form-select-sm">
                                                    <option value="">-- Seleccionar --</option>
                                                    <?php foreach ($columnas as $col): ?>
                                                        <option value="<?php echo htmlspecialchars($col); ?>"
                                                            <?php echo (strtolower($col) === 'documento') ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($col); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">👤 nombre</label>
                                                <select name="mapeo[nombre]" class="form-select form-select-sm">
                                                    <option value="">-- Seleccionar --</option>
                                                    <?php foreach ($columnas as $col): ?>
                                                        <option value="<?php echo htmlspecialchars($col); ?>"
                                                            <?php echo (strtolower($col) === 'conductor') ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($col); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">🗺️ ruta</label>
                                                <select name="mapeo[ruta]" class="form-select form-select-sm">
                                                    <option value="">-- Seleccionar --</option>
                                                    <?php foreach ($columnas as $col): ?>
                                                        <option value="<?php echo htmlspecialchars($col); ?>"
                                                            <?php echo (strtolower($col) === 'ruta') ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($col); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">💰 pago_parcial</label>
                                                <select name="mapeo[pago_parcial]" class="form-select form-select-sm">
                                                    <option value="">-- Seleccionar --</option>
                                                    <?php foreach ($columnas as $col): ?>
                                                        <option value="<?php echo htmlspecialchars($col); ?>"
                                                            <?php echo (strtolower($col) === 'valor') ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($col); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">🏢 empresa</label>
                                                <select name="mapeo[empresa]" class="form-select form-select-sm">
                                                    <option value="auto" selected>⚡ Detectar automáticamente</option>
                                                    <option value="">-- Seleccionar columna --</option>
                                                    <?php foreach ($columnas as $col): ?>
                                                        <option value="<?php echo htmlspecialchars($col); ?>">
                                                            <?php echo htmlspecialchars($col); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">🚙 tipo_vehiculo</label>
                                                <select name="mapeo[tipo_vehiculo]" class="form-select form-select-sm">
                                                    <option value="auto" selected>⚡ Detectar automáticamente</option>
                                                    <option value="">-- Seleccionar columna --</option>
                                                    <?php foreach ($columnas as $col): ?>
                                                        <option value="<?php echo htmlspecialchars($col); ?>">
                                                            <?php echo htmlspecialchars($col); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">📌 origen</label>
                                                <select name="mapeo[origen]" class="form-select form-select-sm" disabled>
                                                    <option value="excel" selected>📁 excel (fijo)</option>
                                                </select>
                                                <input type="hidden" name="mapeo[origen]" value="excel">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Botones de acción -->
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        💾 Guardar en la tabla
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- ============================================================ -->
                <!-- ESTADÍSTICAS DE LA TABLA -->
                <!-- ============================================================ -->
                <?php
                try {
                    $conn = getDBConnection();
                    $result = $conn->query("SELECT COUNT(*) as total, SUM(origen = 'excel') as excel, SUM(origen = 'telegram') as telegram FROM viajes");
                    $stats = $result->fetch_assoc();
                    $conn->close();
                ?>
                <div class="card mt-4">
                    <div class="card-body">
                        <h6 class="fw-bold">📊 Resumen de la tabla</h6>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="border rounded p-2">
                                    <h5 class="mb-0"><?php echo number_format($stats['total'] ?? 0); ?></h5>
                                    <small class="text-muted">Total registros</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-2 bg-success bg-opacity-10">
                                    <h5 class="mb-0 text-success"><?php echo number_format($stats['excel'] ?? 0); ?></h5>
                                    <small class="text-muted">📁 Excel</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-2 bg-primary bg-opacity-10">
                                    <h5 class="mb-0 text-primary"><?php echo number_format($stats['telegram'] ?? 0); ?></h5>
                                    <small class="text-muted">🤖 Telegram</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php } catch (Exception $e) { /* La tabla puede no tener el campo origen aún */ } ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// Iniciar sesión para guardar datos entre pasos
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>k