<?php
/**
 * Importador de Excel a la tabla viajes
 * Permite seleccionar hoja y mapear columnas manualmente
 */

// Iniciar sesión al principio
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
// 3. FUNCIONES PARA LEER EXCEL
// ============================================================

function leerExcel($archivo) {
    // Verificar si existe la librería
    $autoloadPaths = [
        __DIR__ . '/vendor/autoload.php',
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/../../vendor/autoload.php'
    ];
    
    $loaded = false;
    foreach ($autoloadPaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $loaded = true;
            break;
        }
    }
    
    if (!$loaded) {
        throw new Exception("No se encontró PHPSpreadsheet. Instala con: composer require phpoffice/phpspreadsheet");
    }
    
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
        
        if (!empty($datos)) {
            $hojas[$nombreHoja] = [
                'nombre' => $nombreHoja,
                'datos' => $datos,
                'columnas' => $datos[0],
                'total_filas' => count($datos) - 1
            ];
        }
    }
    
    return $hojas;
}

// ============================================================
// 4. PROCESAR IMPORTACIÓN
// ============================================================

function procesarImportacion($archivo, $hoja, $mapeo) {
    // Verificar librería
    $autoloadPaths = [
        __DIR__ . '/vendor/autoload.php',
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/../../vendor/autoload.php'
    ];
    
    $loaded = false;
    foreach ($autoloadPaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $loaded = true;
            break;
        }
    }
    
    if (!$loaded) {
        throw new Exception("No se encontró PHPSpreadsheet.");
    }
    
    $conn = getDBConnection();
    
    // Cargar el Excel
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
                $datos_insert['empresa'] = 'Hospital';
            }
            
            // Detectar tipo de vehículo automáticamente
            if ($detectar_vehiculo) {
                $ruta = $datos_insert['ruta'] ?? '';
                $vehiculo = detectarVehiculo($ruta);
                $datos_insert['tipo_vehiculo'] = $vehiculo;
            } else {
                $datos_insert['tipo_vehiculo'] = 'Burbuja';
            }
            
            // Asignar origen = 'excel'
            $datos_insert['origen'] = 'excel';
            
            // Limpiar valores
            $fecha = !empty($datos_insert['fecha']) ? date('Y-m-d', strtotime($datos_insert['fecha'])) : date('Y-m-d');
            $cedula = $datos_insert['cedula'] ?? null;
            $nombre = $datos_insert['nombre'] ?? null;
            $ruta = $datos_insert['ruta'] ?? null;
            $pago_parcial = !empty($datos_insert['pago_parcial']) ? intval(preg_replace('/[^0-9]/', '', $datos_insert['pago_parcial'])) : null;
            $empresa = $datos_insert['empresa'] ?? 'Hospital';
            $tipo_vehiculo = $datos_insert['tipo_vehiculo'] ?? 'Burbuja';
            $origen = 'excel';
            
            // Insertar en la base de datos
            $sql = "INSERT INTO viajes 
                    (fecha, cedula, nombre, ruta, pago_parcial, empresa, tipo_vehiculo, origen, pagado) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)";
            
            $stmt = $conn->prepare($sql);
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
                $errores[] = "Error: " . $stmt->error;
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $errores[] = "Error: " . $e->getMessage();
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

// ============================================================
// 5. PROCESAR PETICIONES
// ============================================================

$mensaje = '';
$error = '';
$archivo_cargado = false;
$hojas = [];
$hoja_actual = '';

// Procesar carga de archivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cargar') {
    if (isset($_FILES['archivo_excel']) && $_FILES['archivo_excel']['error'] === 0) {
        $archivo = $_FILES['archivo_excel'];
        $nombre_archivo = time() . '_' . basename($archivo['name']);
        $ruta_archivo = UPLOAD_DIR . $nombre_archivo;
        
        if (move_uploaded_file($archivo['tmp_name'], $ruta_archivo)) {
            try {
                $hojas = leerExcel($ruta_archivo);
                $_SESSION['archivo_excel'] = $ruta_archivo;
                $_SESSION['hojas_excel'] = $hojas;
                $archivo_cargado = true;
                $hoja_actual = array_key_first($hojas);
                $mensaje = "✅ Archivo cargado correctamente. Selecciona la hoja y mapea las columnas.";
            } catch (Exception $e) {
                $error = "Error al leer el archivo: " . $e->getMessage();
            }
        } else {
            $error = "Error al mover el archivo.";
        }
    } else {
        $error = "No se seleccionó ningún archivo o hubo un error al subirlo.";
    }
}

// Cambiar hoja seleccionada
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cambiar_hoja') {
    $hoja_actual = $_POST['hoja'] ?? '';
    if (isset($_SESSION['hojas_excel']) && isset($_SESSION['hojas_excel'][$hoja_actual])) {
        $archivo_cargado = true;
        $hojas = $_SESSION['hojas_excel'];
    }
}

// Procesar guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar') {
    $hoja_seleccionada = $_POST['hoja'] ?? '';
    $mapeo = $_POST['mapeo'] ?? [];
    $archivo = $_SESSION['archivo_excel'] ?? null;
    
    if (!$archivo) {
        $error = "No se encontró el archivo. Por favor, cárgalo de nuevo.";
    } elseif (!$hoja_seleccionada) {
        $error = "Por favor, selecciona una hoja.";
    } else {
        try {
            $resultado = procesarImportacion($archivo, $hoja_seleccionada, $mapeo);
            
            if ($resultado['importados'] > 0) {
                $mensaje = "✅ Se importaron {$resultado['importados']} registros correctamente.";
                if (!empty($resultado['errores'])) {
                    $mensaje .= " Hubo " . count($resultado['errores']) . " errores.";
                }
            } else {
                $error = "❌ No se importó ningún registro.";
                if (!empty($resultado['errores'])) {
                    $error .= " Errores: " . implode(", ", $resultado['errores']);
                }
            }
        } catch (Exception $e) {
            $error = "Error al importar: " . $e->getMessage();
        }
    }
}

// Si hay datos en sesión, mostrarlos
if (isset($_SESSION['hojas_excel']) && !$archivo_cargado) {
    $hojas = $_SESSION['hojas_excel'];
    $archivo_cargado = true;
    $hoja_actual = $_POST['hoja'] ?? array_key_first($hojas);
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
        .selected-row { background: #e8f0fe; }
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
                        
                        <?php if ($mensaje): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <?php echo htmlspecialchars($mensaje); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- ============================================================ -->
                        <!-- PASO 1: SUBIR ARCHIVO -->
                        <!-- ============================================================ -->
                        <form method="POST" enctype="multipart/form-data">
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

                        <!-- ============================================================ -->
                        <!-- MOSTRAR HOJAS Y MAPEO -->
                        <!-- ============================================================ -->
                        <?php if ($archivo_cargado && !empty($hojas)): ?>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="guardar">
                                
                                <!-- Selector de hoja -->
                                <div class="row g-3 align-items-end mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">📋 Seleccionar hoja (libro)</label>
                                        <select name="hoja" class="form-select" onchange="this.form.action='?'; this.querySelector('input[name=action]').value='cambiar_hoja'; this.form.submit();">
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
                                        <span class="badge bg-secondary">Total: <?php echo isset($hojas[$hoja_actual]) ? $hojas[$hoja_actual]['total_filas'] : 0; ?> registros</span>
                                    </div>
                                </div>
                                
                                <?php if (isset($hojas[$hoja_actual])): 
                                    $columnas = $hojas[$hoja_actual]['columnas'];
                                    $datos_preview = $hojas[$hoja_actual]['datos'];
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
                                                <select class="form-select form-select-sm" disabled>
                                                    <option selected>📁 excel (fijo)</option>
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
                                
                                <?php endif; ?>
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
    <script>
        // Mantener el formulario visible al cambiar de hoja
        document.querySelectorAll('select[name="hoja"]').forEach(function(select) {
            select.addEventListener('change', function() {
                var form = this.closest('form');
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'action';
                input.value = 'cambiar_hoja';
                form.appendChild(input);
                form.submit();
            });
        });
    </script>
</body>
</html>