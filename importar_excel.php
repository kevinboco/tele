<?php
/**
 * Importador de Excel a la tabla viajes
 * Permite seleccionar hoja y mapear columnas manualmente
 * Soporte para crear nuevas columnas y eliminar registros importados
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

/**
 * Obtiene todas las columnas de la tabla viajes
 */
function obtenerColumnasTabla() {
    $conn = getDBConnection();
    $sql = "SHOW COLUMNS FROM viajes";
    $result = $conn->query($sql);
    $columnas = [];
    while ($row = $result->fetch_assoc()) {
        $columnas[] = $row['Field'];
    }
    $conn->close();
    return $columnas;
}

/**
 * Agrega una nueva columna a la tabla viajes
 */
function agregarColumnaTabla($nombre, $tipo) {
    $conn = getDBConnection();
    
    // Validar nombre de columna (solo letras, números y guión bajo)
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $nombre)) {
        throw new Exception("Nombre de columna inválido. Solo letras, números y guión bajo.");
    }
    
    // Verificar que no exista
    $check = $conn->query("SHOW COLUMNS FROM viajes LIKE '$nombre'");
    if ($check->num_rows > 0) {
        throw new Exception("La columna '$nombre' ya existe.");
    }
    
    $sql = "ALTER TABLE viajes ADD COLUMN `$nombre` $tipo NULL";
    
    if ($conn->query($sql)) {
        $conn->close();
        return true;
    } else {
        $error = $conn->error;
        $conn->close();
        throw new Exception("Error al crear columna: $error");
    }
}

/**
 * Elimina todos los registros con origen = 'excel'
 */
function eliminarRegistrosExcel() {
    $conn = getDBConnection();
    
    // Verificar si existe la columna origen
    $check = $conn->query("SHOW COLUMNS FROM viajes LIKE 'origen'");
    if ($check->num_rows === 0) {
        $conn->close();
        throw new Exception("La columna 'origen' no existe. No se pueden identificar registros importados.");
    }
    
    // Contar cuántos registros se van a eliminar
    $count = $conn->query("SELECT COUNT(*) as total FROM viajes WHERE origen = 'excel'");
    $total = $count->fetch_assoc()['total'];
    
    if ($total == 0) {
        $conn->close();
        return ['eliminados' => 0, 'mensaje' => 'No hay registros importados para eliminar.'];
    }
    
    // Eliminar
    $conn->query("DELETE FROM viajes WHERE origen = 'excel'");
    $eliminados = $conn->affected_rows;
    $conn->close();
    
    return ['eliminados' => $eliminados, 'mensaje' => "Se eliminaron $eliminados registros importados."];
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
    
    // Obtener columnas reales de la tabla
    $columnas_tabla = obtenerColumnasTabla();
    
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
    
    // Construir mapeo de índices
    $indices = [];
    foreach ($mapeo as $campo => $config) {
        // Saltar campos que no existen en la tabla
        if (!in_array($campo, $columnas_tabla) && $campo !== 'origen') {
            continue;
        }
        
        if (is_array($config)) {
            // Para campos con múltiples columnas (ej: nombre)
            $indices[$campo] = [];
            foreach ($config as $columna_excel) {
                if ($columna_excel && $columna_excel !== 'auto') {
                    $indice = array_search($columna_excel, $headers);
                    if ($indice !== false) {
                        $indices[$campo][] = $indice;
                    }
                }
            }
        } else {
            // Para campos con una sola columna
            if ($config && $config !== 'auto') {
                $indice = array_search($config, $headers);
                if ($indice !== false) {
                    $indices[$campo] = $indice;
                }
            }
        }
    }
    
    $importados = 0;
    $errores = [];
    
    foreach ($filas as $fila) {
        try {
            $datos_insert = [];
            
            // Procesar cada campo mapeado
            foreach ($indices as $campo => $config) {
                if (is_array($config)) {
                    // Múltiples columnas (nombre completo)
                    $valores = [];
                    foreach ($config as $indice) {
                        if (isset($fila[$indice])) {
                            $valores[] = trim($fila[$indice]);
                        }
                    }
                    $valores = array_filter($valores);
                    $datos_insert[$campo] = !empty($valores) ? implode(' ', $valores) : null;
                } else {
                    // Una sola columna
                    $valor = isset($fila[$config]) ? trim($fila[$config]) : '';
                    $datos_insert[$campo] = !empty($valor) ? $valor : null;
                }
            }
            
            // Detectar empresa automáticamente
            if (isset($mapeo['empresa']) && $mapeo['empresa'] === 'auto') {
                $ruta = $datos_insert['ruta'] ?? '';
                $datos_insert['empresa'] = detectarEmpresa($ruta);
            } elseif (!isset($datos_insert['empresa']) || empty($datos_insert['empresa'])) {
                $datos_insert['empresa'] = 'Hospital';
            }
            
            // Detectar tipo de vehículo automáticamente
            if (isset($mapeo['tipo_vehiculo']) && $mapeo['tipo_vehiculo'] === 'auto') {
                $ruta = $datos_insert['ruta'] ?? '';
                $datos_insert['tipo_vehiculo'] = detectarVehiculo($ruta);
            } elseif (!isset($datos_insert['tipo_vehiculo']) || empty($datos_insert['tipo_vehiculo'])) {
                $datos_insert['tipo_vehiculo'] = 'Burbuja';
            }
            
            // Asignar origen = 'excel'
            $datos_insert['origen'] = 'excel';
            
            // Limpiar valores para INSERT
            $fecha = !empty($datos_insert['fecha']) ? date('Y-m-d', strtotime($datos_insert['fecha'])) : date('Y-m-d');
            $cedula = $datos_insert['cedula'] ?? null;
            $nombre = $datos_insert['nombre'] ?? null;
            $ruta = $datos_insert['ruta'] ?? null;
            $pago_parcial = !empty($datos_insert['pago_parcial']) ? intval(preg_replace('/[^0-9]/', '', $datos_insert['pago_parcial'])) : null;
            $empresa = $datos_insert['empresa'] ?? 'Hospital';
            $tipo_vehiculo = $datos_insert['tipo_vehiculo'] ?? 'Burbuja';
            $origen = 'excel';
            
            // Construir SQL dinámicamente con las columnas que existen
            $campos = ['fecha', 'cedula', 'nombre', 'ruta', 'pago_parcial', 'empresa', 'tipo_vehiculo', 'origen'];
            $valores = [$fecha, $cedula, $nombre, $ruta, $pago_parcial, $empresa, $tipo_vehiculo, $origen];
            $tipos = 'ssssssss';
            
            // Agregar campos opcionales si existen en la tabla y tienen valor
            $campos_opcionales = ['imagen', 'epicrisis', 'whatsapp', 'pagado', 'color_fila'];
            foreach ($campos_opcionales as $campo_opcional) {
                if (in_array($campo_opcional, $columnas_tabla) && isset($datos_insert[$campo_opcional]) && $datos_insert[$campo_opcional] !== null) {
                    $campos[] = $campo_opcional;
                    if ($campo_opcional === 'pagado') {
                        $valores[] = intval($datos_insert[$campo_opcional]);
                        $tipos .= 'i';
                    } else {
                        $valores[] = $datos_insert[$campo_opcional];
                        $tipos .= 's';
                    }
                }
            }
            
            $sql = "INSERT INTO viajes (" . implode(', ', $campos) . ") VALUES (" . implode(', ', array_fill(0, count($campos), '?')) . ")";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($tipos, ...$valores);
            
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

// Procesar eliminación de registros importados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar_excel') {
    try {
        $resultado = eliminarRegistrosExcel();
        if ($resultado['eliminados'] > 0) {
            $mensaje = "🗑️ " . $resultado['mensaje'];
        } else {
            $mensaje = $resultado['mensaje'];
        }
    } catch (Exception $e) {
        $error = "Error al eliminar: " . $e->getMessage();
    }
}

// Procesar creación de nueva columna
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear_columna') {
    $nombre_columna = trim($_POST['nombre_columna'] ?? '');
    $tipo_columna = trim($_POST['tipo_columna'] ?? 'VARCHAR(255)');
    
    if (empty($nombre_columna)) {
        $error = "Por favor, ingresa un nombre para la columna.";
    } else {
        try {
            if (agregarColumnaTabla($nombre_columna, $tipo_columna)) {
                $mensaje = "✅ Columna '$nombre_columna' creada exitosamente.";
                // Actualizar la lista de columnas en sesión
                $_SESSION['columnas_tabla'] = obtenerColumnasTabla();
            }
        } catch (Exception $e) {
            $error = "Error al crear columna: " . $e->getMessage();
        }
    }
}

// Si hay datos en sesión, mostrarlos
if (isset($_SESSION['hojas_excel']) && !$archivo_cargado) {
    $hojas = $_SESSION['hojas_excel'];
    $archivo_cargado = true;
    $hoja_actual = $_POST['hoja'] ?? array_key_first($hojas);
}

// Obtener columnas de la tabla
$columnas_tabla = $_SESSION['columnas_tabla'] ?? obtenerColumnasTabla();

// Campos de la tabla para el mapeo (solo los que existen)
$campos_tabla = [];
foreach ($columnas_tabla as $columna) {
    // Saltar campos que no deben ser mapeados
    if (in_array($columna, ['id', 'origen'])) {
        continue;
    }
    
    $tipo = 'single';
    $label = $columna;
    $defecto = '';
    
    if ($columna === 'nombre') {
        $tipo = 'multiple';
        $label = '👤 nombre (completo)';
        $defecto = 'auto';
    } elseif ($columna === 'empresa' || $columna === 'tipo_vehiculo') {
        $defecto = 'auto';
    } elseif ($columna === 'pagado') {
        $defecto = '0';
    }
    
    $campos_tabla[$columna] = [
        'label' => $label,
        'tipo' => $tipo,
        'defecto' => $defecto
    ];
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
        .btn-danger { background: #c0392b; border-color: #c0392b; }
        .btn-warning { background: #f39c12; border-color: #f39c12; color: white; }
        .table-preview { font-size: 13px; }
        .table-preview td, .table-preview th { padding: 4px 8px; }
        .campo-multiple { background: #f8f9fa; padding: 8px; border-radius: 6px; border-left: 4px solid #2980b9; }
        .badge-excel { background: #217346; }
        .badge-telegram { background: #0088cc; }
        .columna-existente { font-size: 12px; }
    </style>
</head>
<body>
    <div class="container my-5">
        <div class="row">
            <div class="col-lg-12">
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
                                
                                <!-- ============================================================ -->
                                <!-- MAPEO DE COLUMNAS -->
                                <!-- ============================================================ -->
                                <div class="card bg-light mb-4">
                                    <div class="card-body">
                                        <h6 class="fw-bold">🔗 Mapeo de columnas</h6>
                                        <p class="text-muted small">
                                            Selecciona qué columna del Excel corresponde a cada campo de la tabla.
                                            <br>
                                            ⚡ <strong>Auto</strong>: El campo se detectará automáticamente.
                                            <br>
                                            📌 Los campos sin seleccionar se guardarán como NULL (vacío).
                                        </p>
                                        
                                        <?php foreach ($campos_tabla as $campo => $config): ?>
                                            <div class="row g-3 mb-3 align-items-center">
                                                <div class="col-md-3">
                                                    <label class="form-label fw-bold mb-0"><?php echo $config['label']; ?></label>
                                                    <br><small class="text-muted"><?php echo $campo; ?></small>
                                                </div>
                                                <div class="col-md-9">
                                                    <?php if ($config['tipo'] === 'multiple'): ?>
                                                        <!-- Campo MÚLTIPLE (nombre completo) -->
                                                        <div class="campo-multiple">
                                                            <div class="row g-2">
                                                                <?php
                                                                $sugeridas = ['PRIMER NOMBRE', 'SEGUNDO NOMBRE', 'PRIMER APELLIDO', 'SEGUNDO APELLIDO', 'CONDUCTOR'];
                                                                foreach ($sugeridas as $sugerida):
                                                                ?>
                                                                <div class="col-md-6 col-lg-3">
                                                                    <select name="mapeo[<?php echo $campo; ?>][]" class="form-select form-select-sm">
                                                                        <option value="">-- Columna --</option>
                                                                        <?php foreach ($columnas as $col): ?>
                                                                            <option value="<?php echo htmlspecialchars($col); ?>" 
                                                                                <?php echo (strtoupper($col) === $sugerida) ? 'selected' : ''; ?>>
                                                                                <?php echo htmlspecialchars($col); ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <small class="text-muted">💡 Selecciona las columnas que forman el nombre completo</small>
                                                        </div>
                                                    <?php else: ?>
                                                        <!-- Campo SINGLE -->
                                                        <select name="mapeo[<?php echo $campo; ?>]" class="form-select form-select-sm">
                                                            <option value="">-- Seleccionar --</option>
                                                            <?php if ($config['defecto'] === 'auto'): ?>
                                                                <option value="auto" selected>⚡ Detectar automáticamente</option>
                                                            <?php elseif ($config['defecto'] !== ''): ?>
                                                                <option value="default_<?php echo $config['defecto']; ?>">🔹 Usar valor por defecto: <?php echo $config['defecto']; ?></option>
                                                            <?php endif; ?>
                                                            <?php foreach ($columnas as $col): ?>
                                                                <option value="<?php echo htmlspecialchars($col); ?>"
                                                                    <?php echo (strtoupper($col) === strtoupper($campo) || strtoupper($col) === strtoupper($config['defecto'])) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($col); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <!-- Origen (fijo) -->
                                        <div class="row g-3 mb-3 align-items-center">
                                            <div class="col-md-3">
                                                <label class="form-label fw-bold mb-0">📌 origen</label>
                                            </div>
                                            <div class="col-md-9">
                                                <select class="form-select form-select-sm" disabled>
                                                    <option selected>📁 excel (fijo)</option>
                                                </select>
                                                <input type="hidden" name="mapeo[origen]" value="excel">
                                                <small class="text-muted">🔒 Este campo siempre será "excel"</small>
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
                <!-- GESTIÓN DE COLUMNAS -->
                <!-- ============================================================ -->
                <div class="card mt-4">
                    <div class="card-header bg-warning text-white">
                        <h6 class="mb-0">🔧 Gestión de columnas de la tabla</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h6 class="fw-bold">📋 Columnas actuales</h6>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($columnas_tabla as $col): ?>
                                        <span class="badge bg-secondary columna-existente p-2">
                                            <?php echo htmlspecialchars($col); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <form method="POST" class="mt-2 mt-md-0" onsubmit="return confirm('¿Estás seguro de crear esta nueva columna?');">
                                    <input type="hidden" name="action" value="crear_columna">
                                    <h6 class="fw-bold">➕ Agregar nueva columna</h6>
                                    <div class="row g-2">
                                        <div class="col-7">
                                            <input type="text" class="form-control form-control-sm" name="nombre_columna" 
                                                   placeholder="nombre_columna" required>
                                        </div>
                                        <div class="col-3">
                                            <select name="tipo_columna" class="form-select form-select-sm">
                                                <option value="VARCHAR(255)">VARCHAR</option>
                                                <option value="TEXT">TEXT</option>
                                                <option value="INT">INT</option>
                                                <option value="DECIMAL(10,2)">DECIMAL</option>
                                                <option value="DATE">DATE</option>
                                                <option value="TINYINT(1)">TINYINT</option>
                                            </select>
                                        </div>
                                        <div class="col-2">
                                            <button type="submit" class="btn btn-warning btn-sm w-100">➕</button>
                                        </div>
                                    </div>
                                    <small class="text-muted">Solo letras, números y guión bajo. Ej: paciente_nombre</small>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- ESTADÍSTICAS DE LA TABLA + BOTÓN ELIMINAR -->
                <!-- ============================================================ -->
                <?php
                try {
                    $conn = getDBConnection();
                    
                    // Verificar si existe la columna origen
                    $check = $conn->query("SHOW COLUMNS FROM viajes LIKE 'origen'");
                    $tiene_origen = $check->num_rows > 0;
                    
                    if ($tiene_origen) {
                        $result = $conn->query("SELECT COUNT(*) as total, SUM(origen = 'excel') as excel, SUM(origen = 'telegram') as telegram FROM viajes");
                        $stats = $result->fetch_assoc();
                    } else {
                        $result = $conn->query("SELECT COUNT(*) as total FROM viajes");
                        $stats = $result->fetch_assoc();
                        $stats['excel'] = 0;
                        $stats['telegram'] = 0;
                    }
                    $conn->close();
                ?>
                <div class="card mt-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
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
                            <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                                <?php if ($tiene_origen && ($stats['excel'] ?? 0) > 0): ?>
                                    <form method="POST" style="display:inline;" 
                                          onsubmit="return confirm('⚠️ ¿Estás seguro de eliminar TODOS los registros importados desde Excel? Esta acción no se puede deshacer.');">
                                        <input type="hidden" name="action" value="eliminar_excel">
                                        <button type="submit" class="btn btn-danger">
                                            🗑️ Eliminar <?php echo number_format($stats['excel']); ?> registros importados
                                        </button>
                                    </form>
                                <?php elseif ($tiene_origen): ?>
                                    <button class="btn btn-secondary" disabled>
                                        ✅ No hay registros importados
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-secondary" disabled>
                                        ⚠️ Columna "origen" no existe
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php } catch (Exception $e) { /* Error al consultar */ } ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>