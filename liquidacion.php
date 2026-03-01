<?php
include("nav.php");

/* =======================================================
   🔹 CONFIGURACIÓN Y CONSTANTES
======================================================= */
define('DB_HOST', 'mysql.hostinger.com');
define('DB_USER', 'u648222299_keboco5');
define('DB_PASS', 'Bucaramanga3011');
define('DB_NAME', 'u648222299_viajes');
define('DEBUG_MODE', false);

/* =======================================================
   🔹 CLASE Database (Singleton)
======================================================= */
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            die(json_encode(['error' => 'Error conexión BD: ' . $this->conn->connect_error]));
        }
        $this->conn->set_charset("utf8mb4");
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function escape($string) {
        return $this->conn->real_escape_string($string);
    }
    
    public function query($sql) {
        if (DEBUG_MODE) error_log("SQL: " . $sql);
        return $this->conn->query($sql);
    }
    
    public function error() {
        return $this->conn->error;
    }
}

$db = Database::getInstance();
$conn = $db->getConnection();

/* =======================================================
   🔹 CLASE TarifaManager
======================================================= */
class TarifaManager {
    private $db;
    private $cacheColumnas = null;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Obtener columnas de tarifas dinámicamente
     */
    public function obtenerColumnas() {
        if ($this->cacheColumnas !== null) {
            return $this->cacheColumnas;
        }
        
        $columnas = [];
        $res = $this->db->query("SHOW COLUMNS FROM tarifas");
        if ($res) {
            $excluir = ['id', 'empresa', 'tipo_vehiculo', 'created_at', 'updated_at'];
            while ($row = $res->fetch_assoc()) {
                if (!in_array($row['Field'], $excluir)) {
                    $columnas[] = $row['Field'];
                }
            }
        }
        $this->cacheColumnas = $columnas;
        return $columnas;
    }
    
    /**
     * Crear nueva columna en tarifas
     */
    public function crearColumna($nombre_columna) {
        $nombre_columna = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($nombre_columna));
        
        if (in_array($nombre_columna, $this->obtenerColumnas())) {
            return true;
        }
        
        $sql = "ALTER TABLE tarifas ADD COLUMN `$nombre_columna` DECIMAL(10,2) DEFAULT 0.00";
        $result = $this->db->query($sql);
        
        if ($result) {
            $this->cacheColumnas = null; // Invalidar caché
        }
        
        return $result;
    }
    
    /**
     * Guardar tarifa
     */
    public function guardarTarifa($empresa, $vehiculo, $campo, $valor) {
        $campo = preg_replace('/[^a-z0-9_]/', '_', strtolower($campo));
        
        if (!in_array($campo, $this->obtenerColumnas())) {
            if (!$this->crearColumna($campo)) {
                return false;
            }
        }
        
        $this->db->query("INSERT IGNORE INTO tarifas (empresa, tipo_vehiculo) VALUES ('$empresa', '$vehiculo')");
        return $this->db->query("UPDATE tarifas SET `$campo` = $valor WHERE empresa='$empresa' AND tipo_vehiculo='$vehiculo'");
    }
    
    /**
     * Obtener tarifas por empresas
     */
    public function obtenerTarifasPorEmpresas($empresas) {
        if (empty($empresas)) return [];
        
        $empresasList = "'" . implode("','", array_map([$this->db, 'escape'], $empresas)) . "'";
        $res = $this->db->query("SELECT * FROM tarifas WHERE empresa IN ($empresasList)");
        
        $tarifas = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $tarifas[$row['empresa']][$row['tipo_vehiculo']] = $row;
            }
        }
        return $tarifas;
    }
}

/* =======================================================
   🔹 CLASE ClasificacionManager
======================================================= */
class ClasificacionManager {
    private $db;
    private $tarifaManager;
    private $cacheClasificaciones = null;
    
    public function __construct($db, $tarifaManager) {
        $this->db = $db;
        $this->tarifaManager = $tarifaManager;
    }
    
    /**
     * Obtener clasificaciones disponibles
     */
    public function obtenerClasificaciones() {
        if ($this->cacheClasificaciones !== null) {
            return $this->cacheClasificaciones;
        }
        $this->cacheClasificaciones = $this->tarifaManager->obtenerColumnas();
        return $this->cacheClasificaciones;
    }
    
    /**
     * Obtener clasificaciones de rutas
     */
    public function obtenerClasificacionesRutas() {
        $clasif_rutas = [];
        $res = $this->db->query("SELECT ruta, tipo_vehiculo, clasificacion FROM ruta_clasificacion");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $key = mb_strtolower(trim($row['ruta'] . '|' . $row['tipo_vehiculo']), 'UTF-8');
                $clasif_rutas[$key] = strtolower($row['clasificacion']);
            }
        }
        return $clasif_rutas;
    }
    
    /**
     * Guardar clasificación de ruta
     */
    public function guardarClasificacionRuta($ruta, $vehiculo, $clasificacion) {
        $clasificacion = strtolower($clasificacion);
        
        if ($clasificacion === '') {
            return $this->db->query("DELETE FROM ruta_clasificacion WHERE ruta = '$ruta' AND tipo_vehiculo = '$vehiculo'");
        } else {
            return $this->db->query("INSERT INTO ruta_clasificacion (ruta, tipo_vehiculo, clasificacion)
                VALUES ('$ruta', '$vehiculo', '$clasificacion')
                ON DUPLICATE KEY UPDATE clasificacion = VALUES(clasificacion)");
        }
    }
    
    /**
     * Obtener estilo para clasificación
     */
    public static function obtenerEstilo($clasificacion) {
        $estilos_predefinidos = [
            'completo'    => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200', 'row' => 'bg-emerald-50/40', 'label' => 'Completo'],
            'medio'       => ['bg' => 'bg-amber-100', 'text' => 'text-amber-800', 'border' => 'border-amber-200', 'row' => 'bg-amber-50/40', 'label' => 'Medio'],
            'extra'       => ['bg' => 'bg-slate-200', 'text' => 'text-slate-800', 'border' => 'border-slate-300', 'row' => 'bg-slate-50', 'label' => 'Extra'],
            'siapana'     => ['bg' => 'bg-fuchsia-100', 'text' => 'text-fuchsia-700', 'border' => 'border-fuchsia-200', 'row' => 'bg-fuchsia-50/40', 'label' => 'Siapana'],
            'carrotanque' => ['bg' => 'bg-cyan-100', 'text' => 'text-cyan-800', 'border' => 'border-cyan-200', 'row' => 'bg-cyan-50/40', 'label' => 'Carrotanque'],
            'riohacha'    => ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-700', 'border' => 'border-indigo-200', 'row' => 'bg-indigo-50/40', 'label' => 'Riohacha'],
            'pru'         => ['bg' => 'bg-teal-100', 'text' => 'text-teal-700', 'border' => 'border-teal-200', 'row' => 'bg-teal-50/40', 'label' => 'Pru'],
            'maco'        => ['bg' => 'bg-rose-100', 'text' => 'text-rose-700', 'border' => 'border-rose-200', 'row' => 'bg-rose-50/40', 'label' => 'Maco']
        ];
        
        if (isset($estilos_predefinidos[$clasificacion])) {
            return $estilos_predefinidos[$clasificacion];
        }
        
        $colors = [
            ['bg' => 'bg-violet-100', 'text' => 'text-violet-700', 'border' => 'border-violet-200'],
            ['bg' => 'bg-orange-100', 'text' => 'text-orange-700', 'border' => 'border-orange-200'],
            ['bg' => 'bg-lime-100', 'text' => 'text-lime-700', 'border' => 'border-lime-200'],
            ['bg' => 'bg-sky-100', 'text' => 'text-sky-700', 'border' => 'border-sky-200'],
            ['bg' => 'bg-pink-100', 'text' => 'text-pink-700', 'border' => 'border-pink-200'],
            ['bg' => 'bg-purple-100', 'text' => 'text-purple-700', 'border' => 'border-purple-200'],
        ];
        
        $hash = abs(crc32($clasificacion)) % count($colors);
        
        return [
            'bg' => $colors[$hash]['bg'],
            'text' => $colors[$hash]['text'],
            'border' => $colors[$hash]['border'],
            'row' => str_replace('bg-', 'bg-', $colors[$hash]['bg']) . '/40',
            'label' => ucfirst($clasificacion)
        ];
    }
}

/* =======================================================
   🔹 CLASE VehiculoManager
======================================================= */
class VehiculoManager {
    /**
     * Obtener color para tipo de vehículo
     */
    public static function obtenerColor($vehiculo) {
        $colores_vehiculos = [
            'camioneta' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'border' => 'border-blue-200', 'dark' => 'bg-blue-50'],
            'turbo' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'border' => 'border-green-200', 'dark' => 'bg-green-50'],
            'mensual' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-700', 'border' => 'border-orange-200', 'dark' => 'bg-orange-50'],
            'camión' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-700', 'border' => 'border-purple-200', 'dark' => 'bg-purple-50'],
            'buseta' => ['bg' => 'bg-pink-100', 'text' => 'text-pink-700', 'border' => 'border-pink-200', 'dark' => 'bg-pink-50'],
            'minivan' => ['bg' => 'bg-teal-100', 'text' => 'text-teal-700', 'border' => 'border-teal-200', 'dark' => 'bg-teal-50'],
            'automóvil' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'border' => 'border-red-200', 'dark' => 'bg-red-50'],
            'moto' => ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-700', 'border' => 'border-indigo-200', 'dark' => 'bg-indigo-50'],
        ];
        
        $vehiculo_lower = strtolower($vehiculo);
        
        if (isset($colores_vehiculos[$vehiculo_lower])) {
            return $colores_vehiculos[$vehiculo_lower];
        }
        
        foreach ($colores_vehiculos as $key => $color) {
            if (strpos($vehiculo_lower, $key) !== false) {
                return $color;
            }
        }
        
        $colores_genericos = [
            ['bg' => 'bg-violet-100', 'text' => 'text-violet-700', 'border' => 'border-violet-200', 'dark' => 'bg-violet-50'],
            ['bg' => 'bg-cyan-100', 'text' => 'text-cyan-700', 'border' => 'border-cyan-200', 'dark' => 'bg-cyan-50'],
            ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200', 'dark' => 'bg-emerald-50'],
        ];
        
        $hash = abs(crc32($vehiculo)) % count($colores_genericos);
        return $colores_genericos[$hash];
    }
}

/* =======================================================
   🔹 CLASE ViajeManager
======================================================= */
class ViajeManager {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Obtener todas las empresas
     */
    public function obtenerEmpresas() {
        $empresas = [];
        $res = $this->db->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $empresas[] = $row['empresa'];
            }
        }
        return $empresas;
    }
    
    /**
     * Obtener vehículos por empresa en rango de fechas
     */
    public function obtenerVehiculosPorEmpresa($empresa, $desde, $hasta) {
        $vehiculos = [];
        $sql = "SELECT DISTINCT tipo_vehiculo FROM viajes 
                WHERE empresa = '$empresa' AND fecha BETWEEN '$desde' AND '$hasta'";
        $res = $this->db->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $vehiculos[] = $row['tipo_vehiculo'];
            }
        }
        return $vehiculos;
    }
    
    /**
     * Obtener datos consolidados de viajes
     */
    public function obtenerDatosConsolidados($empresas, $desde, $hasta, $clasif_rutas) {
        $datosConsolidados = [];
        $todosLosVehiculos = [];
        $rutas_sin_clasificar_por_conductor = [];
        
        foreach ($empresas as $empresa) {
            $empresa = $this->db->escape($empresa);
            
            $sql = "SELECT nombre, ruta, empresa, tipo_vehiculo, COALESCE(pago_parcial,0) AS pago_parcial
                    FROM viajes
                    WHERE fecha BETWEEN '$desde' AND '$hasta'
                      AND empresa = '$empresa'";
            
            $res = $this->db->query($sql);
            
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $nombre = $row['nombre'];
                    $ruta = $row['ruta'];
                    $vehiculo = $row['tipo_vehiculo'];
                    $pagoParcial = (int)($row['pago_parcial'] ?? 0);
                    $empresaActual = $row['empresa'];
                    
                    if (!in_array($vehiculo, $todosLosVehiculos)) {
                        $todosLosVehiculos[] = $vehiculo;
                    }
                    
                    if (!isset($datosConsolidados[$nombre])) {
                        $datosConsolidados[$nombre] = [
                            "vehiculos" => [],
                            "pagos_por_empresa" => [],
                            "viajes_por_clasificacion" => []
                        ];
                    }
                    
                    if (!in_array($vehiculo, $datosConsolidados[$nombre]["vehiculos"])) {
                        $datosConsolidados[$nombre]["vehiculos"][] = $vehiculo;
                    }
                    
                    if (!isset($datosConsolidados[$nombre]["pagos_por_empresa"][$empresaActual])) {
                        $datosConsolidados[$nombre]["pagos_por_empresa"][$empresaActual] = 0;
                    }
                    $datosConsolidados[$nombre]["pagos_por_empresa"][$empresaActual] += $pagoParcial;
                    
                    $keyRuta = mb_strtolower(trim($ruta . '|' . $vehiculo), 'UTF-8');
                    $clasificacion_ruta = $clasif_rutas[$keyRuta] ?? '';
                    
                    if ($clasificacion_ruta === '' || $clasificacion_ruta === 'otro') {
                        if (!isset($rutas_sin_clasificar_por_conductor[$nombre])) {
                            $rutas_sin_clasificar_por_conductor[$nombre] = [];
                        }
                        $ruta_key = $ruta . '|' . $vehiculo . '|' . $empresaActual;
                        if (!in_array($ruta_key, $rutas_sin_clasificar_por_conductor[$nombre])) {
                            $rutas_sin_clasificar_por_conductor[$nombre][] = $ruta_key;
                        }
                    }
                    
                    if ($clasificacion_ruta !== '') {
                        if (!isset($datosConsolidados[$nombre]["viajes_por_clasificacion"][$clasificacion_ruta])) {
                            $datosConsolidados[$nombre]["viajes_por_clasificacion"][$clasificacion_ruta] = [];
                        }
                        if (!isset($datosConsolidados[$nombre]["viajes_por_clasificacion"][$clasificacion_ruta][$empresaActual])) {
                            $datosConsolidados[$nombre]["viajes_por_clasificacion"][$clasificacion_ruta][$empresaActual] = 0;
                        }
                        $datosConsolidados[$nombre]["viajes_por_clasificacion"][$clasificacion_ruta][$empresaActual]++;
                    }
                }
            }
        }
        
        return [
            'datos' => $datosConsolidados,
            'vehiculos' => $todosLosVehiculos,
            'rutas_sin_clasificar' => $rutas_sin_clasificar_por_conductor
        ];
    }
    
    /**
     * Obtener rutas únicas
     */
    public function obtenerRutasUnicas($empresas, $desde, $hasta, $clasif_rutas) {
        $rutasUnicas = [];
        
        foreach ($empresas as $empresa) {
            $sql = "SELECT DISTINCT ruta, tipo_vehiculo FROM viajes 
                    WHERE empresa = '$empresa' AND fecha BETWEEN '$desde' AND '$hasta'";
            $res = $this->db->query($sql);
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $key = $row['ruta'] . '|' . $row['tipo_vehiculo'];
                    $clasificacion = $clasif_rutas[mb_strtolower(trim($row['ruta'] . '|' . $row['tipo_vehiculo']), 'UTF-8')] ?? '';
                    $rutasUnicas[$key] = [
                        'ruta' => $row['ruta'],
                        'vehiculo' => $row['tipo_vehiculo'],
                        'clasificacion' => $clasificacion
                    ];
                }
            }
        }
        
        return $rutasUnicas;
    }
}

/* =======================================================
   🔹 CLASE SessionManager
======================================================= */
class SessionManager {
    /**
     * Obtener columnas seleccionadas de cookie
     */
    public static function getColumnasSeleccionadas($empresas, $desde, $hasta, $default) {
        $session_key = "columnas_seleccionadas_" . md5(implode(',', $empresas) . $desde . $hasta);
        
        if (isset($_COOKIE[$session_key])) {
            $columnas = json_decode($_COOKIE[$session_key], true);
            if (is_array($columnas)) {
                return $columnas;
            }
        }
        
        return $default;
    }
    
    /**
     * Guardar columnas seleccionadas en cookie
     */
    public static function guardarColumnasSeleccionadas($columnas, $empresas, $desde, $hasta) {
        $session_key = "columnas_seleccionadas_" . md5(implode(',', $empresas) . $desde . $hasta);
        setcookie($session_key, json_encode($columnas), time() + (86400 * 7), "/");
        return true;
    }
}

/* =======================================================
   🔹 INICIALIZAR MANAGERS
======================================================= */
$tarifaManager = new TarifaManager($db);
$clasificacionManager = new ClasificacionManager($db, $tarifaManager);
$viajeManager = new ViajeManager($db);
$columnas_tarifas = $tarifaManager->obtenerColumnas();
$clasificaciones_disponibles = $clasificacionManager->obtenerClasificaciones();

/* =======================================================
   🔹 PROCESAR POST
======================================================= */
if (isset($_POST['crear_clasificacion'])) {
    $nombre_clasificacion = trim($db->escape($_POST['nombre_clasificacion']));
    if (empty($nombre_clasificacion)) { echo "error: nombre vacío"; exit; }
    
    $result = $tarifaManager->crearColumna($nombre_clasificacion);
    echo $result ? "ok" : "error: " . $db->error();
    exit;
}

if (isset($_POST['guardar_tarifa'])) {
    $empresa  = $db->escape($_POST['empresa']);
    $vehiculo = $db->escape($_POST['tipo_vehiculo']);
    $campo    = $db->escape($_POST['campo']);
    $valor    = (float)$_POST['valor'];
    
    $result = $tarifaManager->guardarTarifa($empresa, $vehiculo, $campo, $valor);
    echo $result ? "ok" : "error: " . $db->error();
    exit;
}

if (isset($_POST['guardar_clasificacion'])) {
    $ruta       = $db->escape($_POST['ruta']);
    $vehiculo   = $db->escape($_POST['tipo_vehiculo']);
    $clasif     = $db->escape($_POST['clasificacion']);
    
    $result = $clasificacionManager->guardarClasificacionRuta($ruta, $vehiculo, $clasif);
    echo $result ? "ok" : "error: " . $db->error();
    exit;
}

if (isset($_POST['guardar_columnas_seleccionadas'])) {
    $columnas = $_POST['columnas'] ?? [];
    $empresas = $_POST['empresas'] ?? "";
    $desde = $_GET['desde'] ?? "";
    $hasta = $_GET['hasta'] ?? "";
    
    SessionManager::guardarColumnasSeleccionadas($columnas, explode(',', $empresas), $desde, $hasta);
    echo "ok";
    exit;
}

if (isset($_POST['exportar_excel'])) {
    // Nueva funcionalidad: Exportar a Excel
    $datos = json_decode($_POST['datos'], true);
    $nombre_archivo = 'liquidacion_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
    
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // BOM para UTF-8
    
    // Escribir encabezados
    fputcsv($output, $datos['headers'], ';');
    
    // Escribir datos
    foreach ($datos['rows'] as $row) {
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    exit;
}

/* =======================================================
   🔹 Endpoint AJAX: viajes por conductor
======================================================= */
if (isset($_GET['viajes_conductor'])) {
    $nombre  = $db->escape($_GET['viajes_conductor']);
    $desde   = $_GET['desde'];
    $hasta   = $_GET['hasta'];
    $empresa = $_GET['empresa'] ?? "";
    
    $clasif_rutas = $clasificacionManager->obtenerClasificacionesRutas();
    
    $sql = "SELECT fecha, ruta, empresa, tipo_vehiculo, COALESCE(pago_parcial,0) AS pago_parcial
            FROM viajes
            WHERE nombre = '$nombre'
              AND fecha BETWEEN '$desde' AND '$hasta'";
    
    if ($empresa !== "") {
        $empresa = $db->escape($empresa);
        $sql .= " AND empresa = '$empresa'";
    }
    $sql .= " ORDER BY fecha ASC";
    
    $res = $db->query($sql);
    
    if ($res && $res->num_rows > 0) {
        $rowsHTML = "";
        $sin_clasificar = [];
        
        while ($row = $res->fetch_assoc()) {
            $ruta = (string)$row['ruta'];
            $vehiculo = $row['tipo_vehiculo'];
            
            $key = mb_strtolower(trim($ruta . '|' . $vehiculo), 'UTF-8');
            $clasif = $clasif_rutas[$key] ?? 'otro';
            
            if ($clasif === 'otro' || $clasif === '') {
                $sin_clasificar[] = [
                    'ruta' => $ruta,
                    'vehiculo' => $vehiculo,
                    'fecha' => $row['fecha']
                ];
            }
            
            $estilo = ClasificacionManager::obtenerEstilo($clasif);
            $badge = "<span class='inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold {$estilo['bg']} {$estilo['text']} border {$estilo['border']}'>{$estilo['label']}</span>";
            
            $pp = (int)($row['pago_parcial'] ?? 0);
            $pagoParcialHTML = $pp > 0 ? '$'.number_format($pp,0,',','.') : "<span class='text-slate-400'>—</span>";
            
            $rowsHTML .= "<tr class='hover:bg-blue-50 transition-colors'>
                    <td class='px-3 py-2 text-center'>".htmlspecialchars($row['fecha'])."</td>
                    <td class='px-3 py-2'>
                      <div class='flex items-center justify-center gap-2'>
                        {$badge}
                        <span>".htmlspecialchars($ruta)."</span>
                      </div>
                    </td>
                    <td class='px-3 py-2 text-center'>".htmlspecialchars($row['empresa'])."</td>
                    <td class='px-3 py-2 text-center'>".htmlspecialchars($vehiculo)."</td>
                    <td class='px-3 py-2 text-center'>{$pagoParcialHTML}</td>
                  </tr>";
        }
        
        echo json_encode([
            'success' => true,
            'html' => $rowsHTML,
            'sin_clasificar' => $sin_clasificar
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No se encontraron viajes'
        ]);
    }
    exit;
}

/* =======================================================
   🔹 Formulario inicial
======================================================= */
if (!isset($_GET['desde']) || !isset($_GET['hasta'])) {
    $empresas = $viajeManager->obtenerEmpresas();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
        <title>Filtrar viajes</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-800">
        <div class="max-w-lg mx-auto p-6">
            <div class="bg-white shadow-sm rounded-2xl p-6 border border-slate-200">
                <h2 class="text-2xl font-bold text-center mb-2">📅 Filtrar viajes por rango</h2>
                <p class="text-center text-slate-500 mb-6">Selecciona el periodo y una o varias empresas.</p>
                <form method="get" class="space-y-4">
                    <label class="block">
                        <span class="block text-sm font-medium mb-1">Desde</span>
                        <input type="date" name="desde" required
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition"/>
                    </label>
                    <label class="block">
                        <span class="block text-sm font-medium mb-1">Hasta</span>
                        <input type="date" name="hasta" required
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition"/>
                    </label>
                    
                    <div class="block">
                        <span class="block text-sm font-medium mb-2">Empresas (selecciona una o varias)</span>
                        <div class="space-y-2 max-h-60 overflow-y-auto border border-slate-200 rounded-xl p-3">
                            <label class="flex items-center gap-2 p-2 hover:bg-slate-50 rounded-lg">
                                <input type="checkbox" name="empresas[]" value="" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                <span class="text-sm font-medium">-- Todas --</span>
                            </label>
                            <?php foreach($empresas as $e): ?>
                                <label class="flex items-center gap-2 p-2 hover:bg-slate-50 rounded-lg">
                                    <input type="checkbox" name="empresas[]" value="<?= htmlspecialchars($e) ?>" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                    <span class="text-sm font-medium"><?= htmlspecialchars($e) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">Puedes seleccionar múltiples empresas.</p>
                    </div>
                    
                    <button class="w-full rounded-xl bg-blue-600 text-white py-2.5 font-semibold shadow hover:bg-blue-700 active:bg-blue-800 focus:ring-4 focus:ring-blue-200 transition">
                        Filtrar
                    </button>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/* =======================================================
   🔹 OBTENER DATOS PRINCIPALES
======================================================= */
$desde = $_GET['desde'];
$hasta = $_GET['hasta'];
$empresasSeleccionadas = $_GET['empresas'] ?? [];

if (empty($empresasSeleccionadas) || in_array("", $empresasSeleccionadas)) {
    $empresasSeleccionadas = $viajeManager->obtenerEmpresas();
}

$clasif_rutas = $clasificacionManager->obtenerClasificacionesRutas();
$consolidado = $viajeManager->obtenerDatosConsolidados($empresasSeleccionadas, $desde, $hasta, $clasif_rutas);

$datosConsolidados = $consolidado['datos'];
$todosLosVehiculos = $consolidado['vehiculos'];
$rutas_sin_clasificar_por_conductor = $consolidado['rutas_sin_clasificar'];

$columnas_seleccionadas = SessionManager::getColumnasSeleccionadas(
    $empresasSeleccionadas, 
    $desde, 
    $hasta, 
    $clasificaciones_disponibles
);

$todasEmpresas = $viajeManager->obtenerEmpresas();
$tarifas_guardadas = $tarifaManager->obtenerTarifasPorEmpresas($empresasSeleccionadas);

$vehiculosPorEmpresa = [];
foreach ($empresasSeleccionadas as $empresa) {
    $vehiculosPorEmpresa[$empresa] = $viajeManager->obtenerVehiculosPorEmpresa($empresa, $desde, $hasta);
}

$rutasUnicas = $viajeManager->obtenerRutasUnicas($empresasSeleccionadas, $desde, $hasta, $clasif_rutas);

// Calcular datos para exportar
$datosExportar = [];
foreach ($datosConsolidados as $conductor => $info) {
    $vehiculo = !empty($info["vehiculos"]) ? $info["vehiculos"][0] : 'Desconocido';
    $totalPagado = array_sum($info["pagos_por_empresa"] ?? []);
    $rutasSinClasificar = count($rutas_sin_clasificar_por_conductor[$conductor] ?? []);
    
    $datosExportar[] = [
        'conductor' => $conductor,
        'vehiculo' => $vehiculo,
        'sin_clasificar' => $rutasSinClasificar,
        'pagado' => $totalPagado
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Liquidación de Conductores - Consolidado</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* ===== ESTILOS MEJORADOS ===== */
        ::-webkit-scrollbar { height: 10px; width: 10px; background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 999px; border: 2px solid #f1f1f1; }
        ::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
        * { scrollbar-width: thin; scrollbar-color: #d1d5db #f1f1f1; }
        
        .floating-balls-container {
            position: fixed; left: 20px; top: 50%; transform: translateY(-50%);
            display: flex; flex-direction: column; gap: 15px; z-index: 9998;
        }
        
        .floating-ball {
            width: 60px; height: 60px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 3px solid white; position: relative; z-index: 9999;
        }
        
        .floating-ball:hover { transform: scale(1.15) translateY(-2px); box-shadow: 0 12px 25px rgba(0, 0, 0, 0.3); }
        .ball-tarifas { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .ball-crear-clasif { background: linear-gradient(135deg, #10b981, #059669); }
        .ball-clasif-rutas { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .ball-selector-columnas { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .ball-exportar { background: linear-gradient(135deg, #ef4444, #b91c1c); }
        
        .ball-tooltip {
            position: absolute; left: 70px; top: 50%; transform: translateY(-50%);
            background: white; color: #1e293b; padding: 6px 12px; border-radius: 8px;
            font-size: 12px; font-weight: 600; white-space: nowrap;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); border: 1px solid #e2e8f0;
            opacity: 0; visibility: hidden; transition: all 0.3s;
            pointer-events: none; z-index: 10000;
        }
        
        .floating-ball:hover .ball-tooltip {
            opacity: 1; visibility: visible; left: 75px;
        }
        
        .side-panel-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.4); z-index: 9997;
            opacity: 0; visibility: hidden; transition: all 0.3s;
        }
        .side-panel-overlay.active { opacity: 1; visibility: visible; }
        
        .side-panel {
            position: fixed; left: -450px; top: 0; width: 420px; height: 100vh;
            background: white; box-shadow: 4px 0 25px rgba(0, 0, 0, 0.15);
            z-index: 9998; transition: left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto; overflow-x: hidden; display: flex; flex-direction: column;
        }
        .side-panel.active { left: 0; }
        
        .table-container-wrapper { transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1); margin-left: 0; }
        .table-container-wrapper.with-panel { margin-left: 420px; }
        
        .conductor-link { cursor: pointer; color: #2563eb; text-decoration: underline; }
        .conductor-link:hover { color: #1e40af; }
        
        .alerta-sin-clasificar { animation: pulse-alerta 2s infinite; }
        @keyframes pulse-alerta { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
        
        .acordeon-content {
            transition: all 0.3s ease; max-height: 0; overflow: hidden;
        }
        .acordeon-content.expanded { max-height: 2000px; overflow-y: auto; }
        .acordeon-icon { transition: transform 0.3s ease; }
        .acordeon-icon.expanded { transform: rotate(90deg); }
        
        .viajes-backdrop { 
            position:fixed; inset:0; background:rgba(0,0,0,.45); 
            display:none; align-items:center; justify-content:center; z-index:10000; 
        }
        .viajes-backdrop.show{ display:flex; }
        .viajes-card { 
            width:min(720px,94vw); max-height:90vh; overflow:hidden; 
            border-radius:16px; background:#fff;
            box-shadow:0 20px 60px rgba(0,0,0,.25); border:1px solid #e5e7eb; 
        }
        
        .columna-oculta { display: none !important; }
        .columna-visualizada { display: table-cell !important; }
        
        .toast-notification {
            position: fixed; top: 20px; right: 20px; z-index: 10001;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800">

    <!-- BOLITAS FLOTANTES -->
    <div class="floating-balls-container">
        <div class="floating-ball ball-tarifas" id="ball-tarifas" data-panel="tarifas">
            <div class="ball-content">🚐</div>
            <div class="ball-tooltip">Tarifas por tipo de vehículo</div>
        </div>
        <div class="floating-ball ball-crear-clasif" id="ball-crear-clasif" data-panel="crear-clasif">
            <div class="ball-content">➕</div>
            <div class="ball-tooltip">Crear nueva clasificación</div>
        </div>
        <div class="floating-ball ball-clasif-rutas" id="ball-clasif-rutas" data-panel="clasif-rutas">
            <div class="ball-content">🧭</div>
            <div class="ball-tooltip">Clasificar rutas existentes</div>
        </div>
        <div class="floating-ball ball-selector-columnas" id="ball-selector-columnas" data-panel="selector-columnas">
            <div class="ball-content">📊</div>
            <div class="ball-tooltip">Seleccionar columnas</div>
        </div>
        <div class="floating-ball ball-exportar" id="ball-exportar" data-panel="exportar">
            <div class="ball-content">📥</div>
            <div class="ball-tooltip">Exportar a Excel</div>
        </div>
    </div>

    <!-- Overlay -->
    <div class="side-panel-overlay" id="sidePanelOverlay"></div>

    <!-- HEADER -->
    <header class="max-w-[1800px] mx-auto px-3 md:px-4 pt-6">
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-5 py-4">
            <div class="flex flex-col gap-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl md:text-2xl font-bold">🪙 Liquidación de Conductores - CONSOLIDADO</h2>
                    <span class="text-sm bg-blue-100 text-blue-700 px-3 py-1 rounded-full font-medium">
                        📊 <?= count($empresasSeleccionadas) ?> empresa(s) · <?= count($datosConsolidados) ?> conductor(es)
                    </span>
                </div>
                
                <form method="get" class="space-y-4" id="filtroForm">
                    <div class="flex flex-col md:flex-row gap-3">
                        <label class="flex-1">
                            <span class="block text-xs font-medium text-slate-600 mb-1">Desde</span>
                            <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" required
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition">
                        </label>
                        
                        <label class="flex-1">
                            <span class="block text-xs font-medium text-slate-600 mb-1">Hasta</span>
                            <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" required
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition">
                        </label>
                    </div>
                    
                    <div class="block">
                        <span class="block text-sm font-medium mb-2">Empresas</span>
                        <div class="space-y-2 max-h-60 overflow-y-auto border border-slate-200 rounded-xl p-3 bg-white">
                            <label class="flex items-center gap-2 p-2 hover:bg-slate-50 rounded-lg cursor-pointer">
                                <input type="checkbox" name="empresas[]" value="" 
                                       <?= in_array("", $empresasSeleccionadas) ? 'checked' : '' ?>
                                       class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                                <span class="text-sm font-medium">🌐 -- Todas las empresas --</span>
                            </label>
                            
                            <?php foreach($todasEmpresas as $emp): ?>
                                <label class="flex items-center gap-2 p-2 hover:bg-slate-50 rounded-lg cursor-pointer">
                                    <input type="checkbox" name="empresas[]" value="<?= htmlspecialchars($emp) ?>"
                                           <?= in_array($emp, $empresasSeleccionadas) ? 'checked' : '' ?>
                                           class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                                    <span class="text-sm font-medium">🏢 <?= htmlspecialchars($emp) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="exportarExcel()" 
                                class="inline-flex items-center gap-2 rounded-xl bg-green-600 text-white px-6 py-2.5 text-sm font-semibold hover:bg-green-700 transition">
                            📥 Exportar Excel
                        </button>
                        <button type="submit" 
                                class="inline-flex items-center gap-2 rounded-xl bg-blue-600 text-white px-6 py-2.5 text-sm font-semibold hover:bg-blue-700 transition">
                            🔄 Aplicar filtros
                        </button>
                    </div>
                </form>
                
                <div class="flex flex-wrap items-center gap-2 mt-2 pt-3 border-t border-slate-100">
                    <span class="text-sm font-medium text-slate-600">Empresas seleccionadas:</span>
                    <?php if (in_array("", $empresasSeleccionadas)): ?>
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-purple-100 text-purple-700 text-xs font-medium border border-purple-200">
                            🌐 TODAS
                        </span>
                    <?php else: ?>
                        <?php foreach ($empresasSeleccionadas as $emp): ?>
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-medium border border-blue-200">
                                🏢 <?= htmlspecialchars($emp) ?>
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <span class="text-sm text-slate-500 ml-auto">
                        📅 <?= htmlspecialchars($desde) ?> → <?= htmlspecialchars($hasta) ?>
                    </span>
                </div>
            </div>
        </div>
    </header>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="max-w-[1800px] mx-auto px-3 md:px-4 py-6">
        <div class="table-container-wrapper" id="tableContainerWrapper">
            
            <!-- Totales generales -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-2xl p-6 mb-6 shadow-lg">
                <div class="flex items-center gap-3 mb-4">
                    <span class="text-3xl">📊</span>
                    <h3 class="text-xl font-bold">TOTALES CONSOLIDADOS</h3>
                    <span class="bg-white/20 px-3 py-1 rounded-full text-sm"><?= count($empresasSeleccionadas) ?> empresas</span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4 border border-white/20">
                        <div class="text-sm opacity-80">Viajes totales</div>
                        <div class="text-3xl font-bold" id="total_viajes_general">0</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4 border border-white/20">
                        <div class="text-sm opacity-80">Total a pagar</div>
                        <div class="text-3xl font-bold" id="total_general_general">0</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4 border border-white/20">
                        <div class="text-sm opacity-80">Pagado</div>
                        <div class="text-3xl font-bold" id="total_pagado_general">0</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4 border border-white/20">
                        <div class="text-sm opacity-80">Faltante</div>
                        <div class="text-3xl font-bold" id="total_faltante_general">0</div>
                    </div>
                </div>
            </div>

            <!-- Buscador y controles -->
            <div class="mb-4 flex flex-wrap gap-3 items-center justify-between">
                <div class="flex gap-3 flex-1">
                    <div class="relative flex-1 max-w-md">
                        <input type="text" 
                               placeholder="Buscar conductor..." 
                               class="buscador-global w-full rounded-xl border border-slate-300 px-4 py-3 pr-10 text-sm"
                               id="buscadorGlobal">
                        <button class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400 hover:text-slate-600" 
                                id="clearBuscador" style="display: none;">✕</button>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-4 py-2 text-blue-700 text-sm font-medium">
                        <span id="conductoresVisibles"><?= count($datosConsolidados) ?></span>/<span id="conductoresTotales"><?= count($datosConsolidados) ?></span>
                    </span>
                </div>
                
                <button onclick="togglePanel('selector-columnas')" 
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-gradient-to-r from-purple-500 to-indigo-500 text-white hover:from-purple-600 hover:to-indigo-600 transition shadow-md">
                    <span>📊</span>
                    <span class="text-sm font-medium">Seleccionar columnas</span>
                </button>
            </div>

            <!-- TABLA CONSOLIDADA -->
            <?php if (!empty($datosConsolidados)): ?>
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto rounded-xl border border-slate-200 max-h-[70vh]">
                    <table class="w-full text-sm" id="tablaConsolidada">
                        <thead class="bg-blue-600 text-white sticky top-0 z-20">
                            <tr>
                                <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 70px;">Estado</th>
                                <th class="px-4 py-3 text-left sticky top-0 bg-blue-600" style="min-width: 220px;">Conductor</th>
                                <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 150px;">Tipo Vehículo</th>
                                
                                <?php foreach ($clasificaciones_disponibles as $clasif): 
                                    $estilo = ClasificacionManager::obtenerEstilo($clasif);
                                    $visible = in_array($clasif, $columnas_seleccionadas);
                                    $clase_visibilidad = $visible ? 'columna-visualizada' : 'columna-oculta';
                                ?>
                                <th class="px-4 py-3 text-center sticky top-0 <?= $clase_visibilidad ?> columna-tabla" 
                                    data-columna="<?= htmlspecialchars($clasif) ?>"
                                    style="min-width: 80px; background-color: <?= str_replace('bg-', '#', $estilo['bg']) ?>; color: <?= str_replace('text-', '#', $estilo['text']) ?>;">
                                    <?= strtoupper(substr($clasif, 0, 3)) ?>
                                </th>
                                <?php endforeach; ?>
                                
                                <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 140px;">Total</th>
                                <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 120px;">Pagado</th>
                                <th class="px-4 py-3 text-center sticky top-0 bg-blue-600" style="min-width: 100px;">Faltante</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white" id="tbodyConsolidado">
                        <?php foreach ($datosConsolidados as $conductor => $info): 
                            $vehiculo = !empty($info["vehiculos"]) ? $info["vehiculos"][0] : 'Desconocido';
                            $esMensual = (stripos($vehiculo, 'mensual') !== false);
                            $rutasSinClasificar = count($rutas_sin_clasificar_por_conductor[$conductor] ?? 0);
                            $color_vehiculo = VehiculoManager::obtenerColor($vehiculo);
                            
                            $totalPagado = array_sum($info["pagos_por_empresa"] ?? []);
                            
                            $viajesData = [];
                            foreach ($info["viajes_por_clasificacion"] as $clasif => $porEmpresa) {
                                foreach ($porEmpresa as $emp => $cantidad) {
                                    $viajesData[] = $clasif . '|' . $emp . '|' . $cantidad;
                                }
                            }
                            $viajesDataStr = implode(',', $viajesData);
                            
                            $totalesPorClasificacion = [];
                            foreach ($info["viajes_por_clasificacion"] as $clasif => $porEmpresa) {
                                $totalesPorClasificacion[$clasif] = array_sum($porEmpresa);
                            }
                        ?>
                            <tr data-conductor="<?= htmlspecialchars($conductor) ?>" 
                                data-conductor-normalizado="<?= htmlspecialchars(mb_strtolower($conductor)) ?>"
                                data-pagado="<?= $totalPagado ?>"
                                data-sin-clasificar="<?= $rutasSinClasificar ?>"
                                data-viajes-data="<?= htmlspecialchars($viajesDataStr) ?>"
                                data-vehiculo="<?= htmlspecialchars($vehiculo) ?>"
                                class="hover:bg-blue-50/40 transition-colors <?= $rutasSinClasificar > 0 ? 'alerta-sin-clasificar' : '' ?> fila-conductor">
                                
                                <td class="px-4 py-3 text-center">
                                    <?php if ($rutasSinClasificar > 0): ?>
                                        <div class="flex flex-col items-center justify-center gap-1" title="<?= $rutasSinClasificar ?> ruta(s) sin clasificar">
                                            <span class="text-amber-600 font-bold animate-pulse">⚠️</span>
                                            <span class="text-xs bg-amber-100 text-amber-800 px-2 py-1 rounded-full font-bold">
                                                <?= $rutasSinClasificar ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex flex-col items-center justify-center gap-1" title="Todas las rutas clasificadas">
                                            <span class="text-emerald-600">✅</span>
                                            <span class="text-xs bg-emerald-100 text-emerald-800 px-2 py-1 rounded-full font-bold">0</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-4 py-3">
                                    <button type="button"
                                            class="conductor-link text-blue-700 hover:text-blue-900 underline underline-offset-2 transition flex items-center gap-2"
                                            onclick="abrirModalViajes('<?= htmlspecialchars($conductor) ?>')">
                                        <?php if ($rutasSinClasificar > 0): ?>
                                            <span class="text-amber-600">⚠️</span>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($conductor) ?>
                                    </button>
                                    
                                    <?php if (count($info["pagos_por_empresa"]) > 1): ?>
                                        <span class="inline-flex text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full ml-2">
                                            +<?= count($info["pagos_por_empresa"]) ?> empresas
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-block px-3 py-1.5 rounded-lg text-xs font-medium border <?= $color_vehiculo['border'] ?> <?= $color_vehiculo['text'] ?> <?= $color_vehiculo['bg'] ?>">
                                        <?= htmlspecialchars($vehiculo) ?>
                                    </span>
                                </td>
                                
                                <?php foreach ($clasificaciones_disponibles as $clasif): 
                                    $estilo = ClasificacionManager::obtenerEstilo($clasif);
                                    $cantidad = $totalesPorClasificacion[$clasif] ?? 0;
                                    $visible = in_array($clasif, $columnas_seleccionadas);
                                    $clase_visibilidad = $visible ? 'columna-visualizada' : 'columna-oculta';
                                ?>
                                <td class="px-4 py-3 text-center font-medium <?= $clase_visibilidad ?> columna-tabla" 
                                    data-columna="<?= htmlspecialchars($clasif) ?>"
                                    data-cantidad="<?= $cantidad ?>"
                                    style="background-color: <?= str_replace('bg-', '#', $estilo['bg']) ?>; color: <?= str_replace('text-', '#', $estilo['text']) ?>;">
                                    <?= $cantidad ?>
                                </td>
                                <?php endforeach; ?>

                                <td class="px-4 py-3">
                                    <input type="text"
                                           class="totales w-full rounded-xl border border-slate-300 px-3 py-2 text-right bg-slate-50 outline-none"
                                           readonly dir="ltr" value="0">
                                </td>
                                <td class="px-4 py-3">
                                    <input type="text"
                                           class="pagado w-full rounded-xl border border-emerald-200 px-3 py-2 text-right bg-emerald-50 outline-none"
                                           readonly dir="ltr" value="<?= number_format($totalPagado, 0, ',', '.') ?>">
                                </td>
                                <td class="px-4 py-3">
                                    <input type="text"
                                           class="faltante w-full rounded-xl border border-rose-200 px-3 py-2 text-right bg-rose-50 outline-none"
                                           readonly dir="ltr" value="0">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-12 text-center">
                <span class="text-6xl mb-4 block">📭</span>
                <h3 class="text-xl font-bold text-slate-700 mb-2">No hay datos para mostrar</h3>
                <p class="text-slate-500">No se encontraron viajes en el período seleccionado.</p>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal VIAJES -->
    <div id="viajesModal" class="viajes-backdrop">
        <div class="viajes-card">
            <div class="viajes-header p-4 border-b border-slate-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center gap-2">
                    🧳 Viajes — <span id="viajesTitle" class="font-normal"></span>
                </h3>
                <button class="text-slate-600 hover:bg-slate-100 border border-slate-300 px-3 py-1.5 rounded-lg text-sm" id="viajesCloseBtn">
                    ✕ Cerrar
                </button>
            </div>
            <div class="viajes-body p-4 overflow-auto max-h-[70vh]" id="viajesContent">
                <p class="text-center py-4">Cargando...</p>
            </div>
        </div>
    </div>

    <!-- PANELES LATERALES -->
    
    <!-- Panel tarifas -->
    <div class="side-panel" id="panel-tarifas">
        <div class="side-panel-header p-4 border-b border-slate-200 flex justify-between items-center sticky top-0 bg-white">
            <h3 class="text-lg font-semibold">🚐 Tarifas por Tipo de Vehículo</h3>
            <button class="side-panel-close w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center">✕</button>
        </div>
        <div class="side-panel-body p-4">
            <div class="flex justify-end gap-2 mb-4">
                <button onclick="expandirTodosTarifas()" class="text-xs px-3 py-1.5 rounded-lg border border-green-300 hover:bg-green-50 text-green-600">
                    Expandir todos
                </button>
                <button onclick="colapsarTodosTarifas()" class="text-xs px-3 py-1.5 rounded-lg border border-amber-300 hover:bg-amber-50 text-amber-600">
                    Colapsar todos
                </button>
            </div>
            
            <?php foreach ($empresasSeleccionadas as $empresa): 
                $vehiculosEmpresa = $vehiculosPorEmpresa[$empresa] ?? [];
                if (empty($vehiculosEmpresa)) continue;
            ?>
            <div class="mb-6">
                <h4 class="text-md font-bold mb-3 flex items-center gap-2 border-b pb-2">
                    <span>🏢 <?= htmlspecialchars($empresa) ?></span>
                </h4>
                <div class="grid grid-cols-1 gap-3">
                    <?php foreach ($vehiculosEmpresa as $veh):
                        $color_vehiculo = VehiculoManager::obtenerColor($veh);
                        $t = $tarifas_guardadas[$empresa][$veh] ?? [];
                        $veh_id = preg_replace('/[^a-z0-9]/i', '-', strtolower($veh . '-' . $empresa));
                    ?>
                    <div class="rounded-xl border <?= $color_vehiculo['border'] ?> overflow-hidden shadow-sm"
                         data-vehiculo="<?= htmlspecialchars($veh) ?>" data-empresa="<?= htmlspecialchars($empresa) ?>"
                         id="acordeon-<?= $veh_id ?>">
                        
                        <div class="flex items-center justify-between px-4 py-3 cursor-pointer <?= $color_vehiculo['bg'] ?>"
                             onclick="toggleAcordeon('<?= $veh_id ?>')">
                            <div class="flex items-center gap-3">
                                <span class="acordeon-icon text-lg transition-transform <?= $color_vehiculo['text'] ?>" id="icon-<?= $veh_id ?>">▶️</span>
                                <span class="font-semibold <?= $color_vehiculo['text'] ?>"><?= htmlspecialchars($veh) ?></span>
                            </div>
                        </div>
                        
                        <div class="acordeon-content px-4 py-3 border-t <?= $color_vehiculo['border'] ?> bg-white" id="content-<?= $veh_id ?>">
                            <div class="space-y-3">
                                <?php foreach ($columnas_tarifas as $columna): 
                                    $valor = isset($t[$columna]) ? (float)$t[$columna] : 0;
                                    $estilo_clasif = ClasificacionManager::obtenerEstilo($columna);
                                ?>
                                <label class="block">
                                    <span class="block text-sm font-medium mb-1 <?= $estilo_clasif['text'] ?>">
                                        <?= ucfirst($columna) ?>
                                    </span>
                                    <input type="text" value="<?= $valor ?>"
                                           data-campo="<?= htmlspecialchars($columna) ?>"
                                           data-empresa="<?= htmlspecialchars($empresa) ?>"
                                           data-vehiculo="<?= htmlspecialchars($veh) ?>"
                                           class="w-full rounded-xl border <?= $estilo_clasif['border'] ?> px-3 py-2 text-right bg-white tarifa-input"
                                           data-valor-real="<?= $valor ?>">
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Panel crear clasificación -->
    <div class="side-panel" id="panel-crear-clasif">
        <div class="side-panel-header p-4 border-b border-slate-200 flex justify-between items-center">
            <h3 class="text-lg font-semibold">➕ Crear Nueva Clasificación</h3>
            <button class="side-panel-close w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200">✕</button>
        </div>
        <div class="side-panel-body p-4">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Nombre de la clasificación</label>
                    <input id="txt_nueva_clasificacion" type="text"
                           class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                           placeholder="Ej: Premium, Nocturno, Express...">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Aplicar a rutas que contengan (opcional)</label>
                    <input id="txt_patron_ruta" type="text"
                           class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                           placeholder="Texto en la ruta">
                </div>
                <button onclick="crearYAsignarClasificacion()"
                        class="w-full rounded-xl bg-green-600 text-white px-4 py-3 font-semibold hover:bg-green-700">
                    ⚙️ Crear y Aplicar
                </button>
            </div>
        </div>
    </div>

    <!-- Panel clasificación rutas -->
    <div class="side-panel" id="panel-clasif-rutas">
        <div class="side-panel-header p-4 border-b border-slate-200 flex justify-between items-center">
            <h3 class="text-lg font-semibold">🧭 Clasificar Rutas</h3>
            <button class="side-panel-close w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200">✕</button>
        </div>
        <div class="side-panel-body p-4">
            <div class="max-h-[calc(100vh-180px)] overflow-y-auto border border-slate-200 rounded-xl">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 sticky top-0">
                        <tr>
                            <th class="px-3 py-2 text-left">Ruta</th>
                            <th class="px-3 py-2 text-center">Vehículo</th>
                            <th class="px-3 py-2 text-center">Clasificación</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php foreach($rutasUnicas as $info): 
                        $clasificacion_actual = $info['clasificacion'] ?? '';
                        $estilo = ClasificacionManager::obtenerEstilo($clasificacion_actual);
                    ?>
                        <tr class="hover:bg-slate-50"
                            data-ruta="<?= htmlspecialchars($info['ruta']) ?>"
                            data-vehiculo="<?= htmlspecialchars($info['vehiculo']) ?>">
                            <td class="px-3 py-2"><?= htmlspecialchars($info['ruta']) ?></td>
                            <td class="px-3 py-2 text-center">
                                <?php $color = VehiculoManager::obtenerColor($info['vehiculo']); ?>
                                <span class="px-2 py-1 rounded-md text-xs <?= $color['bg'] ?> <?= $color['text'] ?>">
                                    <?= htmlspecialchars($info['vehiculo']) ?>
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <select class="select-clasif-ruta rounded-lg border border-slate-300 px-3 py-2 w-full"
                                        onchange="guardarClasificacionRuta(this)">
                                    <option value="">Sin clasificar</option>
                                    <?php foreach ($clasificaciones_disponibles as $clasif): ?>
                                    <option value="<?= htmlspecialchars($clasif) ?>" 
                                            <?= $info['clasificacion']===$clasif ? 'selected' : '' ?>>
                                        <?= ucfirst($clasif) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Panel selector columnas -->
    <div class="side-panel" id="panel-selector-columnas">
        <div class="side-panel-header p-4 border-b border-slate-200 flex justify-between items-center">
            <h3 class="text-lg font-semibold">📊 Seleccionar Columnas</h3>
            <button class="side-panel-close w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200">✕</button>
        </div>
        <div class="side-panel-body p-4">
            <div class="flex gap-2 mb-4">
                <button onclick="seleccionarTodasColumnas()" class="text-xs px-3 py-1.5 rounded-lg border border-green-300 bg-green-50 text-green-700">
                    ✅ Todas
                </button>
                <button onclick="deseleccionarTodasColumnas()" class="text-xs px-3 py-1.5 rounded-lg border border-rose-300 bg-rose-50 text-rose-700">
                    ❌ Ninguna
                </button>
                <button onclick="guardarSeleccionColumnas()" class="text-xs px-3 py-1.5 rounded-lg border border-blue-300 bg-blue-50 text-blue-700">
                    💾 Guardar
                </button>
            </div>
            
            <div class="grid grid-cols-1 gap-2 max-h-[60vh] overflow-y-auto">
                <?php foreach ($clasificaciones_disponibles as $clasif): 
                    $seleccionada = in_array($clasif, $columnas_seleccionadas);
                ?>
                <div class="flex items-center gap-2 p-3 border rounded-lg cursor-pointer hover:bg-slate-50"
                     onclick="toggleColumna('<?= htmlspecialchars($clasif) ?>')">
                    <div class="w-5 h-5 rounded border-2 <?= $seleccionada ? 'bg-blue-600 border-blue-600' : 'border-slate-300' ?>">
                        <?php if ($seleccionada): ?>
                            <span class="text-white flex items-center justify-center">✓</span>
                        <?php endif; ?>
                    </div>
                    <span><?= ucfirst($clasif) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Panel exportar -->
    <div class="side-panel" id="panel-exportar">
        <div class="side-panel-header p-4 border-b border-slate-200 flex justify-between items-center">
            <h3 class="text-lg font-semibold">📥 Exportar Datos</h3>
            <button class="side-panel-close w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200">✕</button>
        </div>
        <div class="side-panel-body p-4">
            <div class="space-y-4">
                <p class="text-sm text-slate-600">Exportar los datos actuales a diferentes formatos.</p>
                
                <button onclick="exportarExcel()" class="w-full rounded-xl bg-green-600 text-white px-4 py-3 font-semibold hover:bg-green-700">
                    📊 Exportar a Excel (CSV)
                </button>
                
                <button onclick="exportarPDF()" class="w-full rounded-xl bg-red-600 text-white px-4 py-3 font-semibold hover:bg-red-700">
                    📄 Exportar a PDF
                </button>
                
                <button onclick="exportarJSON()" class="w-full rounded-xl bg-blue-600 text-white px-4 py-3 font-semibold hover:bg-blue-700">
                    🔧 Exportar a JSON
                </button>
            </div>
        </div>
    </div>

    <script>
        // ===== VARIABLES GLOBALES =====
        const RANGO_DESDE = <?= json_encode($desde) ?>;
        const RANGO_HASTA = <?= json_encode($hasta) ?>;
        const EMPRESAS_SELECCIONADAS = <?= json_encode($empresasSeleccionadas) ?>;
        
        // ===== SISTEMA DE PANELES =====
        let activePanel = null;
        const panels = ['tarifas', 'crear-clasif', 'clasif-rutas', 'selector-columnas', 'exportar'];
        
        document.addEventListener('DOMContentLoaded', function() {
            panels.forEach(panelId => {
                const ball = document.getElementById(`ball-${panelId}`);
                const panel = document.getElementById(`panel-${panelId}`);
                const closeBtn = panel?.querySelector('.side-panel-close');
                
                if (ball && panel) {
                    ball.addEventListener('click', () => togglePanel(panelId));
                }
                
                if (closeBtn) {
                    closeBtn.addEventListener('click', () => togglePanel(panelId));
                }
            });
            
            document.getElementById('sidePanelOverlay').addEventListener('click', () => {
                if (activePanel) togglePanel(activePanel);
            });
            
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && activePanel) togglePanel(activePanel);
            });
            
            configurarBuscador();
            configurarTarifas();
            recalcularTodo();
        });
        
        function togglePanel(panelId) {
            const ball = document.getElementById(`ball-${panelId}`);
            const panel = document.getElementById(`panel-${panelId}`);
            const overlay = document.getElementById('sidePanelOverlay');
            const tableWrapper = document.getElementById('tableContainerWrapper');
            
            if (!panel || !ball) return;
            
            if (activePanel === panelId) {
                panel.classList.remove('active');
                ball.classList.remove('ring-4', 'ring-blue-400');
                overlay?.classList.remove('active');
                tableWrapper?.classList.remove('with-panel');
                activePanel = null;
            } else {
                if (activePanel) {
                    document.getElementById(`panel-${activePanel}`)?.classList.remove('active');
                    document.getElementById(`ball-${activePanel}`)?.classList.remove('ring-4', 'ring-blue-400');
                }
                
                panel.classList.add('active');
                ball.classList.add('ring-4', 'ring-blue-400');
                overlay?.classList.add('active');
                tableWrapper?.classList.add('with-panel');
                activePanel = panelId;
            }
        }
        
        // ===== ACORDEÓN TARIFAS =====
        function toggleAcordeon(id) {
            const content = document.getElementById('content-' + id);
            const icon = document.getElementById('icon-' + id);
            if (content && icon) {
                content.classList.toggle('expanded');
                icon.classList.toggle('expanded');
            }
        }
        
        function expandirTodosTarifas() {
            document.querySelectorAll('.acordeon-content').forEach(c => c.classList.add('expanded'));
            document.querySelectorAll('.acordeon-icon').forEach(i => i.classList.add('expanded'));
        }
        
        function colapsarTodosTarifas() {
            document.querySelectorAll('.acordeon-content').forEach(c => c.classList.remove('expanded'));
            document.querySelectorAll('.acordeon-icon').forEach(i => i.classList.remove('expanded'));
        }
        
        // ===== TARIFAS =====
        function configurarTarifas() {
            document.querySelectorAll('.tarifa-input').forEach(input => {
                input.dataset.valorReal = input.value.replace(/\D/g, '') || '0';
                input.value = formatearNumero(input.dataset.valorReal);
                
                input.addEventListener('input', function(e) {
                    let valor = this.value.replace(/\D/g, '');
                    this.dataset.valorReal = valor || '0';
                    this.value = formatearNumero(valor);
                });
                
                input.addEventListener('blur', function() {
                    guardarTarifa(this);
                });
            });
        }
        
        function formatearNumero(valor) {
            if (!valor) return '';
            return parseInt(valor, 10).toLocaleString('es-CO').replace(/,/g, '.');
        }
        
        function guardarTarifa(input) {
            const empresa = input.dataset.empresa;
            const vehiculo = input.dataset.vehiculo;
            const campo = input.dataset.campo;
            const valor = parseInt(input.dataset.valorReal || '0', 10);
            
            fetch('<?= basename(__FILE__) ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    guardar_tarifa: 1,
                    empresa: empresa,
                    tipo_vehiculo: vehiculo,
                    campo: campo,
                    valor: valor
                })
            })
            .then(r => r.text())
            .then(resp => {
                if (resp.trim() === 'ok') {
                    mostrarNotificacion('✅ Tarifa guardada', 'success');
                    recalcularTodo();
                } else {
                    mostrarNotificacion('❌ Error al guardar', 'error');
                }
            });
        }
        
        // ===== CLASIFICACIÓN RUTAS =====
        function guardarClasificacionRuta(select) {
            const row = select.closest('tr');
            const ruta = row.dataset.ruta;
            const vehiculo = row.dataset.vehiculo;
            const clasificacion = select.value;
            
            fetch('<?= basename(__FILE__) ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    guardar_clasificacion: 1,
                    ruta: ruta,
                    tipo_vehiculo: vehiculo,
                    clasificacion: clasificacion
                })
            })
            .then(r => r.text())
            .then(resp => {
                if (resp.trim() === 'ok') {
                    mostrarNotificacion('✅ Clasificación guardada', 'success');
                }
            });
        }
        
        function crearYAsignarClasificacion() {
            const nombre = document.getElementById('txt_nueva_clasificacion').value.trim();
            const patron = document.getElementById('txt_patron_ruta').value.trim().toLowerCase();
            
            if (!nombre) {
                alert('Escribe el nombre de la clasificación');
                return;
            }
            
            fetch('<?= basename(__FILE__) ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    crear_clasificacion: 1,
                    nombre_clasificacion: nombre
                })
            })
            .then(r => r.text())
            .then(resp => {
                if (resp.trim() === 'ok') {
                    if (patron) {
                        document.querySelectorAll('.select-clasif-ruta').forEach(select => {
                            const rutaText = select.closest('tr').querySelector('td:first-child').textContent.toLowerCase();
                            if (rutaText.includes(patron)) {
                                select.value = nombre.toLowerCase();
                                guardarClasificacionRuta(select);
                            }
                        });
                    }
                    mostrarNotificacion('✅ Clasificación creada', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    mostrarNotificacion('❌ Error: ' + resp, 'error');
                }
            });
        }
        
        // ===== SELECCIÓN COLUMNAS =====
        let columnasSeleccionadas = <?= json_encode($columnas_seleccionadas) ?>;
        
        function toggleColumna(columna) {
            if (columnasSeleccionadas.includes(columna)) {
                columnasSeleccionadas = columnasSeleccionadas.filter(c => c !== columna);
            } else {
                columnasSeleccionadas.push(columna);
            }
            actualizarColumnasTabla();
        }
        
        function seleccionarTodasColumnas() {
            columnasSeleccionadas = [...<?= json_encode($clasificaciones_disponibles) ?>];
            actualizarColumnasTabla();
        }
        
        function deseleccionarTodasColumnas() {
            columnasSeleccionadas = [];
            actualizarColumnasTabla();
        }
        
        function actualizarColumnasTabla() {
            document.querySelectorAll('.columna-tabla').forEach(col => {
                const nombre = col.dataset.columna;
                if (columnasSeleccionadas.includes(nombre)) {
                    col.classList.remove('columna-oculta');
                    col.classList.add('columna-visualizada');
                } else {
                    col.classList.remove('columna-visualizada');
                    col.classList.add('columna-oculta');
                }
            });
            
            // Actualizar UI del panel
            document.querySelectorAll('#panel-selector-columnas [onclick^="toggleColumna"]').forEach(item => {
                const columna = item.querySelector('span:last-child').textContent.toLowerCase();
                const checkbox = item.querySelector('div:first-child');
                if (columnasSeleccionadas.includes(columna)) {
                    checkbox.classList.add('bg-blue-600', 'border-blue-600');
                    checkbox.innerHTML = '<span class="text-white">✓</span>';
                } else {
                    checkbox.classList.remove('bg-blue-600', 'border-blue-600');
                    checkbox.classList.add('border-slate-300');
                    checkbox.innerHTML = '';
                }
            });
        }
        
        function guardarSeleccionColumnas() {
            const desde = RANGO_DESDE;
            const hasta = RANGO_HASTA;
            const empresas = EMPRESAS_SELECCIONADAS.join(',');
            
            fetch('<?= basename(__FILE__) ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    guardar_columnas_seleccionadas: 1,
                    columnas: JSON.stringify(columnasSeleccionadas),
                    empresas: empresas,
                    desde: desde,
                    hasta: hasta
                })
            })
            .then(r => r.text())
            .then(resp => {
                if (resp.trim() === 'ok') {
                    mostrarNotificacion('✅ Selección guardada', 'success');
                }
            });
        }
        
        // ===== BUSCADOR =====
        function configurarBuscador() {
            const input = document.getElementById('buscadorGlobal');
            const clearBtn = document.getElementById('clearBuscador');
            
            if (input) {
                input.addEventListener('input', function() {
                    const texto = this.value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                    let visibles = 0;
                    
                    document.querySelectorAll('#tbodyConsolidado tr').forEach(row => {
                        const nombre = row.querySelector('.conductor-link')?.textContent?.toLowerCase() || '';
                        const nombreNorm = nombre.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                        
                        if (texto === '' || nombreNorm.includes(texto)) {
                            row.style.display = '';
                            visibles++;
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    
                    document.getElementById('conductoresVisibles').textContent = visibles;
                    clearBtn.style.display = texto === '' ? 'none' : 'block';
                    recalcularTodo();
                });
            }
            
            if (clearBtn) {
                clearBtn.addEventListener('click', () => {
                    input.value = '';
                    input.dispatchEvent(new Event('input'));
                    input.focus();
                });
            }
        }
        
        // ===== CÁLCULOS =====
        function getTarifas() {
            const tarifas = {};
            document.querySelectorAll('[data-vehiculo][data-empresa]').forEach(card => {
                const empresa = card.dataset.empresa;
                const vehiculo = card.dataset.vehiculo;
                
                if (!tarifas[empresa]) tarifas[empresa] = {};
                if (!tarifas[empresa][vehiculo]) tarifas[empresa][vehiculo] = {};
                
                card.querySelectorAll('.tarifa-input').forEach(input => {
                    const campo = input.dataset.campo;
                    const valor = parseInt(input.dataset.valorReal || '0', 10);
                    tarifas[empresa][vehiculo][campo] = valor;
                });
            });
            return tarifas;
        }
        
        function recalcularTodo() {
            const tarifas = getTarifas();
            let totalViajes = 0, totalPagado = 0, totalFaltante = 0;
            
            document.querySelectorAll('#tbodyConsolidado tr').forEach(row => {
                if (row.style.display === 'none') return;
                
                const vehiculo = row.dataset.vehiculo;
                const pagado = parseInt(row.dataset.pagado || '0', 10);
                const viajesData = row.dataset.viajesData || '';
                
                let totalFila = 0;
                
                viajesData.split(',').filter(Boolean).forEach(item => {
                    const [clasif, empresa, cantidad] = item.split('|');
                    if (clasif && empresa && cantidad) {
                        const tarifa = tarifas[empresa]?.[vehiculo]?.[clasif] || 0;
                        totalFila += parseInt(cantidad, 10) * tarifa;
                    }
                });
                
                const faltante = Math.max(0, totalFila - pagado);
                
                row.querySelector('.totales').value = formatearNumero(totalFila.toString());
                row.querySelector('.faltante').value = formatearNumero(faltante.toString());
                
                totalViajes += totalFila;
                totalPagado += pagado;
                totalFaltante += faltante;
            });
            
            document.getElementById('total_viajes_general').textContent = formatearNumero(totalViajes.toString());
            document.getElementById('total_general_general').textContent = formatearNumero(totalViajes.toString());
            document.getElementById('total_pagado_general').textContent = formatearNumero(totalPagado.toString());
            document.getElementById('total_faltante_general').textContent = formatearNumero(totalFaltante.toString());
        }
        
        // ===== MODAL VIAJES =====
        function abrirModalViajes(conductor) {
            const modal = document.getElementById('viajesModal');
            const content = document.getElementById('viajesContent');
            const title = document.getElementById('viajesTitle');
            
            title.textContent = conductor;
            modal.classList.add('show');
            content.innerHTML = '<p class="text-center py-4">Cargando viajes...</p>';
            
            fetch(`<?= basename(__FILE__) ?>?viajes_conductor=${encodeURIComponent(conductor)}&desde=${RANGO_DESDE}&hasta=${RANGO_HASTA}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        let html = '<table class="w-full text-sm"><thead class="bg-slate-100"><tr>';
                        html += '<th class="p-2">Fecha</th><th class="p-2">Ruta</th><th class="p-2">Empresa</th><th class="p-2">Vehículo</th><th class="p-2">Pago</th>';
                        html += '</tr></thead><tbody>' + data.html + '</tbody></table>';
                        
                        if (data.sin_clasificar?.length > 0) {
                            html = '<div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-3">' +
                                   '⚠️ ' + data.sin_clasificar.length + ' viajes sin clasificar' +
                                   '</div>' + html;
                        }
                        
                        content.innerHTML = html;
                    } else {
                        content.innerHTML = '<p class="text-center text-slate-500 py-4">No se encontraron viajes</p>';
                    }
                })
                .catch(() => {
                    content.innerHTML = '<p class="text-center text-rose-600 py-4">Error cargando viajes</p>';
                });
        }
        
        document.getElementById('viajesCloseBtn')?.addEventListener('click', () => {
            document.getElementById('viajesModal').classList.remove('show');
        });
        
        document.getElementById('viajesModal')?.addEventListener('click', (e) => {
            if (e.target === document.getElementById('viajesModal')) {
                document.getElementById('viajesModal').classList.remove('show');
            }
        });
        
        // ===== EXPORTACIÓN =====
        function exportarExcel() {
            const filas = [];
            const headers = ['Conductor', 'Vehículo', 'Sin Clasificar', 'Pagado'];
            
            document.querySelectorAll('#tbodyConsolidado tr').forEach(row => {
                if (row.style.display === 'none') return;
                
                const conductor = row.querySelector('.conductor-link')?.textContent?.replace('⚠️', '').trim() || '';
                const vehiculo = row.dataset.vehiculo || '';
                const sinClasificar = row.dataset.sinClasificar || '0';
                const pagado = row.dataset.pagado || '0';
                
                filas.push([conductor, vehiculo, sinClasificar, pagado]);
            });
            
            const datos = {
                headers: headers,
                rows: filas
            };
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?= basename(__FILE__) ?>';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'datos';
            input.value = JSON.stringify(datos);
            
            const input2 = document.createElement('input');
            input2.type = 'hidden';
            input2.name = 'exportar_excel';
            input2.value = '1';
            
            form.appendChild(input);
            form.appendChild(input2);
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        
        function exportarPDF() {
            mostrarNotificacion('🔧 Funcionalidad en desarrollo', 'info');
        }
        
        function exportarJSON() {
            const datos = {
                fecha_generacion: new Date().toISOString(),
                desde: RANGO_DESDE,
                hasta: RANGO_HASTA,
                empresas: EMPRESAS_SELECCIONADAS,
                conductores: []
            };
            
            document.querySelectorAll('#tbodyConsolidado tr').forEach(row => {
                if (row.style.display === 'none') return;
                
                datos.conductores.push({
                    nombre: row.querySelector('.conductor-link')?.textContent?.replace('⚠️', '').trim() || '',
                    vehiculo: row.dataset.vehiculo || '',
                    sin_clasificar: parseInt(row.dataset.sinClasificar || '0', 10),
                    pagado: parseInt(row.dataset.pagado || '0', 10),
                    total: parseInt(row.querySelector('.totales')?.value?.replace(/\./g, '') || '0', 10)
                });
            });
            
            const blob = new Blob([JSON.stringify(datos, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'liquidacion_' + new Date().toISOString().slice(0,10) + '.json';
            a.click();
            URL.revokeObjectURL(url);
        }
        
        // ===== NOTIFICACIONES =====
        function mostrarNotificacion(mensaje, tipo) {
            const toast = document.createElement('div');
            toast.className = `toast-notification px-4 py-3 rounded-lg shadow-lg ${
                tipo === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
                tipo === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
                'bg-blue-100 text-blue-800 border border-blue-200'
            }`;
            toast.innerHTML = mensaje;
            document.body.appendChild(toast);
            
            setTimeout(() => toast.remove(), 3000);
        }
    </script>
</body>
</html>
<?php
$conn->close();
?>