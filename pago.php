<?php
// ==== CONEXIÓN BD ====
$conn = new mysqli(
  "mysql.hostinger.com",
  "u648222299_keboco5",
  "Bucaramanga3011",
  "u648222299_viajes"
);
if ($conn->connect_error) {
  die("Error conexión BD: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

/* ================= Helpers ================= */
function strip_accents($s){
  $t = @iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);
  if ($t !== false) return $t;
  $repl = [
    'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n',
    'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N'
  ];
  return strtr($s,$repl);
}
function norm_person($s){
  $s = strip_accents((string)$s);
  $s = mb_strtolower($s,'UTF-8');
  $s = preg_replace('/[^a-z0-9\s]/',' ', $s);
  $s = preg_replace('/\s+/',' ', trim($s));
  return $s;
}

/* =========================================================
   AJAX 1: VIAJES POR CONDUCTOR (modal) — SOLO JSON/HTML
   ========================================================= */
if (isset($_GET['viajes_conductor'])) {
  $nombre  = $conn->real_escape_string($_GET['viajes_conductor']);
  $desde   = $conn->real_escape_string($_GET['desde'] ?? '');
  $hasta   = $conn->real_escape_string($_GET['hasta'] ?? '');
  $empresa = $conn->real_escape_string($_GET['empresa'] ?? '');

  $sql = "SELECT fecha, ruta, empresa, tipo_vehiculo
          FROM viajes
          WHERE nombre = '$nombre'
            AND fecha BETWEEN '$desde' AND '$hasta'";
  if ($empresa !== '') {
    $sql .= " AND empresa = '$empresa'";
  }
  $sql .= " ORDER BY fecha ASC";

  $legend = [
    'completo'     => ['label'=>'Completo',     'badge'=>'bg-emerald-100 text-emerald-700 border border-emerald-200', 'row'=>'bg-emerald-50/40'],
    'medio'        => ['label'=>'Medio',        'badge'=>'bg-amber-100 text-amber-800 border border-amber-200',       'row'=>'bg-amber-50/40'],
    'extra'        => ['label'=>'Extra',        'badge'=>'bg-slate-200 text-slate-800 border border-slate-300',       'row'=>'bg-slate-50'],
    'siapana'      => ['label'=>'Siapana',      'badge'=>'bg-fuchsia-100 text-fuchsia-700 border border-fuchsia-200', 'row'=>'bg-fuchsia-50/40'],
    'carrotanque'  => ['label'=>'Carrotanque',  'badge'=>'bg-cyan-100 text-cyan-800 border border-cyan-200',          'row'=>'bg-cyan-50/40'],
    'otro'         => ['label'=>'Otro',         'badge'=>'bg-gray-100 text-gray-700 border border-gray-200',          'row'=>'']
  ];

  $res = $conn->query($sql);

  $rowsHTML = "";
  $counts = [
    'completo'=>0,
    'medio'=>0,
    'extra'=>0,
    'siapana'=>0,
    'carrotanque'=>0,
    'otro'=>0
  ];

  if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_assoc()) {
      $ruta    = (string)$r['ruta'];
      $guiones = substr_count($ruta,'-');

      if ($r['tipo_vehiculo']==='Carrotanque' && $guiones==0) {
        $cat = 'carrotanque';
      } elseif (stripos($ruta,'Siapana') !== false) {
        $cat = 'siapana';
      } elseif (stripos($ruta,'Maicao') === false) {
        $cat = 'extra';
      } elseif ($guiones==2) {
        $cat = 'completo';
      } elseif ($guiones==1) {
        $cat = 'medio';
      } else {
        $cat = 'otro';
      }

      if (isset($counts[$cat])) $counts[$cat]++; else $counts[$cat]=1;

      $l = $legend[$cat];
      $badge = "<span class='inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold {$l['badge']}'>".$l['label']."</span>";
      $rowCls = trim("row-viaje hover:bg-blue-50 transition-colors {$l['row']} cat-$cat");

      $rowsHTML .= "<tr class='{$rowCls}'>
          <td class='px-3 py-2'>".htmlspecialchars($r['fecha'])."</td>
          <td class='px-3 py-2'>
            <div class='flex items-center gap-2'>
              {$badge}
              <span>".htmlspecialchars($ruta)."</span>
            </div>
          </td>
          <td class='px-3 py-2'>".htmlspecialchars($r['empresa'])."</td>
          <td class='px-3 py-2'>".htmlspecialchars($r['tipo_vehiculo'])."</td>
        </tr>";
    }
  } else {
    $rowsHTML .= "<tr><td colspan='4' class='px-3 py-4 text-center text-slate-500'>Sin viajes en el rango/empresa.</td></tr>";
  }

  ?>
  <div class='space-y-3'>
    <div class='flex flex-wrap gap-2 text-xs' id="legendFilterBar">
      <?php
      foreach (['completo','medio','extra','siapana','carrotanque'] as $k) {
        $l = $legend[$k];
        $countVal = $counts[$k] ?? 0;
        echo "<button
                class='legend-pill inline-flex items-center gap-2 px-3 py-2 rounded-full {$l['badge']} hover:opacity-90 transition ring-0 outline-none border cursor-pointer select-none'
                data-tipo='{$k}'
              >
                <span class='w-2.5 h-2.5 rounded-full ".str_replace(['bg-','/40'], ['bg-',''], $l['row'])." bg-opacity-100 border border-white/30 shadow-inner'></span>
                <span class='font-semibold text-[13px]'>{$l['label']}</span>
                <span class='text-[11px] font-semibold opacity-80'>({$countVal})</span>
              </button>";
      }
      ?>
    </div>

    <div class='overflow-x-auto'>
      <table class='min-w-full text-sm text-left'>
        <thead class='bg-blue-600 text-white'>
          <tr>
            <th class='px-3 py-2'>Fecha</th>
            <th class='px-3 py-2'>Ruta</th>
            <th class='px-3 py-2'>Empresa</th>
            <th class='px-3 py-2'>Vehículo</th>
          </tr>
        </thead>
        <tbody class='divide-y divide-gray-100 bg-white' id="viajesTableBody">
          <?= $rowsHTML ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php
  exit;
}

/* =========================================================
   AJAX 2: CRUD CUENTAS_COBRO_GUARDADAS — SOLO JSON
   ========================================================= */
if (isset($_GET['accion']) || isset($_POST['accion'])) {
  $accion = $_GET['accion'] ?? $_POST['accion'];
  header('Content-Type: application/json; charset=utf-8');

  // LISTAR
  if ($accion === 'listar_cuentas') {
    $empresa = $conn->real_escape_string($_GET['empresa'] ?? '');
    $data = [];
    if ($empresa !== '') {
      $sql = "SELECT id, nombre, empresa, desde, hasta, facturado, porcentaje_ajuste
              FROM cuentas_cobro_guardadas
              WHERE empresa = '$empresa'
              ORDER BY desde DESC, id DESC";
      if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) {
          $data[] = [
            'id' => (int)$r['id'],
            'nombre' => $r['nombre'],
            'empresa' => $r['empresa'],
            'desde' => $r['desde'],
            'hasta' => $r['hasta'],
            'facturado' => (int)$r['facturado'],
            'porcentaje_ajuste' => (float)$r['porcentaje_ajuste'],
          ];
        }
      }
    }
    echo json_encode(['ok'=>true,'items'=>$data], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // GUARDAR (INSERT / UPDATE)
  if ($accion === 'guardar_cuenta') {
    $id        = (int)($_POST['id'] ?? 0);
    $nombre    = $conn->real_escape_string($_POST['nombre'] ?? '');
    $empresa   = $conn->real_escape_string($_POST['empresa'] ?? '');
    $desde     = $conn->real_escape_string($_POST['desde'] ?? '');
    $hasta     = $conn->real_escape_string($_POST['hasta'] ?? '');
    $facturado = (int)($_POST['facturado'] ?? 0);
    $porcentaje = (float)($_POST['porcentaje_ajuste'] ?? 0);

    if ($empresa === '' || $desde === '' || $hasta === '') {
      echo json_encode(['ok'=>false,'msg'=>'Datos incompletos']); exit;
    }

    if ($id > 0) {
      $stmt = $conn->prepare("UPDATE cuentas_cobro_guardadas
                              SET nombre=?, empresa=?, desde=?, hasta=?, facturado=?, porcentaje_ajuste=?
                              WHERE id=?");
      if (!$stmt) {
        echo json_encode(['ok'=>false,'msg'=>$conn->error]); exit;
      }
      $stmt->bind_param("sssiddi", $nombre, $empresa, $desde, $hasta, $facturado, $porcentaje, $id);
      $ok = $stmt->execute();
      $stmt->close();
      echo json_encode(['ok'=>$ok, 'id'=>$id]); exit;
    } else {
      $stmt = $conn->prepare("INSERT INTO cuentas_cobro_guardadas
        (nombre, empresa, desde, hasta, facturado, porcentaje_ajuste, fecha_creacion)
        VALUES (?,?,?,?,?,?,NOW())");
      if (!$stmt) {
        echo json_encode(['ok'=>false,'msg'=>$conn->error]); exit;
      }
      $stmt->bind_param("sssidi", $nombre, $empresa, $desde, $hasta, $facturado, $porcentaje);
      $ok = $stmt->execute();
      $newId = $stmt->insert_id;
      $stmt->close();
      echo json_encode(['ok'=>$ok, 'id'=>$newId]); exit;
    }
  }

  // ELIMINAR
  if ($accion === 'eliminar_cuenta') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }
    $stmt = $conn->prepare("DELETE FROM cuentas_cobro_guardadas WHERE id=?");
    if (!$stmt) { echo json_encode(['ok'=>false,'msg'=>$conn->error]); exit; }
    $stmt->bind_param("i",$id);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['ok'=>$ok]); exit;
  }

  echo json_encode(['ok'=>false,'msg'=>'Acción no reconocida']);
  exit;
}

/* =========================================================
   DESDE AQUÍ ES SOLO LA PÁGINA NORMAL (INCLUYE nav.php)
   ========================================================= */
include("nav.php");

/* ====== SI FALTAN FECHAS: FORMULARIO SIMPLE ====== */
if (!isset($_GET['desde']) || !isset($_GET['hasta'])) {
  $empresas = [];
  $resEmp = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
  if ($resEmp) while ($r = $resEmp->fetch_assoc()) $empresas[] = $r['empresa'];
  ?>
  <!DOCTYPE html>
  <html lang="es"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Ajuste de Pago</title><script src="https://cdn.tailwindcss.com"></script>
  </head>
  <body class="min-h-screen bg-slate-100 text-slate-800">
    <div class="max-w-lg mx-auto p-6">
      <div class="bg-white shadow-sm rounded-2xl p-6 border border-slate-200">
        <h2 class="text-2xl font-bold text-center mb-2">📅 Ajuste de Pago por rango</h2>
        <form method="get" class="space-y-4">
          <label class="block"><span class="block text-sm font-medium mb-1">Desde</span>
            <input type="date" name="desde" required class="w-full rounded-xl border border-slate-300 px-3 py-2">
          </label>
          <label class="block"><span class="block text-sm font-medium mb-1">Hasta</span>
            <input type="date" name="hasta" required class="w-full rounded-xl border border-slate-300 px-3 py-2">
          </label>
          <label class="block"><span class="block text-sm font-medium mb-1">Empresa</span>
            <select name="empresa" class="w-full rounded-xl border border-slate-300 px-3 py-2">
              <option value="">-- Todas --</option>
              <?php foreach($empresas as $e): ?>
                <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <button class="w-full rounded-xl bg-blue-600 text-white py-2.5 font-semibold shadow">Continuar</button>
        </form>
      </div>
    </div>
  </body></html>
  <?php exit;
}

/* ================= Parámetros ================= */
$desde = $_GET['desde'];
$hasta = $_GET['hasta'];
$empresaFiltro = $_GET['empresa'] ?? "";

/* ================= Viajes del rango (para totales) ================= */
$sqlV = "SELECT nombre, ruta, empresa, tipo_vehiculo
         FROM viajes
         WHERE fecha BETWEEN '$desde' AND '$hasta'";
if ($empresaFiltro !== "") {
  $empresaFiltroEsc = $conn->real_escape_string($empresaFiltro);
  $sqlV .= " AND empresa = '$empresaFiltroEsc'";
}
$resV = $conn->query($sqlV);

$contadores = [];
if ($resV) {
  while ($row = $resV->fetch_assoc()) {
    $nombre = $row['nombre'];
    if (!isset($contadores[$nombre])) {
      $contadores[$nombre] = [
        'vehiculo' => $row['tipo_vehiculo'],
        'completos'=>0, 'medios'=>0, 'extras'=>0, 'carrotanques'=>0, 'siapana'=>0
      ];
    }
    $ruta = (string)$row['ruta'];
    $guiones = substr_count($ruta, '-');
    if ($row['tipo_vehiculo']==='Carrotanque' && $guiones==0) {
      $contadores[$nombre]['carrotanques']++;
    } elseif (stripos($ruta,'Siapana') !== false) {
      $contadores[$nombre]['siapana']++;
    } elseif (stripos($ruta,'Maicao') === false) {
      $contadores[$nombre]['extras']++;
    } elseif ($guiones==2) {
      $contadores[$nombre]['completos']++;
    } elseif ($guiones==1) {
      $contadores[$nombre]['medios']++;
    }
  }
}

/* ================= Tarifas ================= */
$tarifas = [];
if ($empresaFiltro !== "") {
  $resT = $conn->query("SELECT * FROM tarifas WHERE empresa='".$conn->real_escape_string($empresaFiltro)."'");
  if ($resT) while($r=$resT->fetch_assoc()) $tarifas[$r['tipo_vehiculo']] = $r;
}

/* ================= Préstamos: listado ================= */
$prestamosList = [];
$i = 0;
$qPrest = "
  SELECT deudor,
         SUM(
           monto + 
           monto * 
           CASE 
             WHEN fecha >= '2025-10-29' THEN 0.13
             ELSE 0.10
           END *
           CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END
         ) AS total
  FROM prestamos
  WHERE (pagado IS NULL OR pagado = 0)
  GROUP BY deudor
";
if ($rP = $conn->query($qPrest)) {
  while($r = $rP->fetch_assoc()){
    $name = $r['deudor'];
    $key  = norm_person($name);
    $total = (int)round($r['total']);
    $prestamosList[] = ['id'=>$i++, 'name'=>$name, 'key'=>$key, 'total'=>$total];
  }
}

/* ================= Filas base (viajes) ================= */
$filas = []; $total_facturado = 0;
foreach ($contadores as $nombre => $v) {
  $veh = $v['vehiculo'];
  $t = $tarifas[$veh] ?? ["completo"=>0,"medio"=>0,"extra"=>0,"carrotanque"=>0,"siapana"=>0];

  $total = $v['completos']   * (int)($t['completo']    ?? 0)
         + $v['medios']      * (int)($t['medio']       ?? 0)
         + $v['extras']      * (int)($t['extra']       ?? 0)
         + $v['carrotanques']* (int)($t['carrotanque'] ?? 0)
         + $v['siapana']     * (int)($t['siapana']     ?? 0);

  $filas[] = ['nombre'=>$nombre, 'total_bruto'=>(int)$total];
  $total_facturado += (int)$total;
}
usort($filas, fn($a,$b)=> $b['total_bruto'] <=> $a['total_bruto']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Ajuste de Pago (con gestor de cuentas de cobro)</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  .num { font-variant-numeric: tabular-nums; }
  .table-sticky thead tr { position: sticky; top: 0; z-index: 30; }
  .table-sticky thead th { position: sticky; top: 0; z-index: 31; background-color: #2563eb !important; color: #fff !important; }
  .table-sticky thead { box-shadow: 0 2px 0 rgba(0,0,0,0.06); }

  .viajes-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,.45); display:none; align-items:center; justify-content:center; z-index:10000; }
  .viajes-backdrop.show{ display:flex; }
  .viajes-card{ width:min(720px,94vw); max-height:90vh; overflow:hidden; border-radius:16px; background:#fff;
                box-shadow:0 20px 60px rgba(0,0,0,.25); border:1px solid #e5e7eb; }
  .viajes-header{padding:14px 16px;border-bottom:1px solid #eef2f7}
  .viajes-body{padding:14px 16px;overflow:auto; max-height:70vh}
  .viajes-close{padding:6px 10px; border-radius:10px; cursor:pointer;}
  .viajes-close:hover{background:#f3f4f6}

  .conductor-link{cursor:pointer; color:#0d6efd; text-decoration:underline;}

  .estado-pagado { background-color: #f0fdf4 !important; border-left: 4px solid #22c55e; }
  .estado-pendiente { background-color: #fef2f2 !important; border-left: 4px solid #ef4444; }
  .estado-procesando { background-color: #fffbeb !important; border-left: 4px solid #f59e0b; }
  .estado-parcial { background-color: #eff6ff !important; border-left: 4px solid #3b82f6; }

  .fila-manual { background-color: #f0f9ff !important; border-left: 4px solid #0ea5e9; }
  .fila-manual td { background-color: #f0f9ff !important; }
</style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">
  <!-- … TODO el RESTO del HTML + JS ES EL MISMO QUE TE ENVIÉ ANTES … -->
  <!-- Para no pasarnos de caracteres lo dejo igual, solo cambió la parte de arriba (PHP/AJAX) -->

<?php
/* aquí pega TODO el HTML + <script> que ya te envié en el mensaje anterior,
   SIN CAMBIAR NADA, desde <header> hasta </body></html> */
?>
