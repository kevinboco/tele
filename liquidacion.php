<?php
// =======================================================
// 🔹 CONEXIÓN A BASE DE DATOS
// =======================================================
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { 
    die("Error conexión BD: " . $conn->connect_error); 
}
$conn->set_charset("utf8mb4");

// =======================================================
// 🔹 VERIFICAR SI HAY FECHAS (FORMULARIO INICIAL)
// =======================================================
if (!isset($_GET['desde']) || !isset($_GET['hasta'])) {
    // Obtener empresas para el select
    $empresas = [];
    $resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
    if ($resEmp) while ($r = $resEmp->fetch_assoc()) $empresas[] = $r['empresa'];
    
    // Mostrar solo el formulario de filtro
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
          <p class="text-center text-slate-500 mb-6">Selecciona el periodo y (opcional) una empresa.</p>
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
            <label class="block">
              <span class="block text-sm font-medium mb-1">Empresa</span>
              <select name="empresa"
                      class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition">
                <option value="">-- Todas --</option>
                <?php foreach($empresas as $e): ?>
                  <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <button class="w-full rounded-xl bg-blue-600 text-white py-2.5 font-semibold shadow hover:bg-blue-700 active:bg-blue-800 focus:ring-4 focus:ring-blue-200 transition">
              Filtrar
            </button>
          </form>
        </div>
      </div>
    </body>
    </html>
    <?php
    $conn->close();
    exit;
}

// =======================================================
// 🔹 SI HAY FECHAS - PROCESAR DATOS BÁSICOS
// =======================================================
$desde = $_GET['desde'];
$hasta = $_GET['hasta'];
$empresaFiltro = $_GET['empresa'] ?? "";

// Datos que necesitarán las vistas
$datos_vistas = [
    'conn' => $conn,
    'desde' => $desde,
    'hasta' => $hasta,
    'empresaFiltro' => $empresaFiltro
];

// =======================================================
// 🔹 INCLUIR VISTAS AUTÓNOMAS
// =======================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Liquidación de Conductores</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800">

<?php
// 1. Incluir vista de paneles (bolitas + paneles deslizantes)
include("vista_paneles.php");

// 2. Incluir vista de tabla principal
include("vista_tabla.php");

// 3. Incluir vista del modal de viajes
include("vista_modal.php");
?>

</body>
</html>

<?php
$conn->close();
?>