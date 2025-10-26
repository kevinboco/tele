<?php
/*********************************************************
 * prestamos_visual_interactivo.php
 * v4.4
 * - Visual D3
 * - Modo global multi-deudor
 * - Selector marcar pagados
 * - Modal con historial individual
 * - Colores de antigüedad por fila en el modal
 * - Leyenda de colores en el modal
 * - Fila TOTAL en el modal
 *********************************************************/
include("nav.php");

/* ===== Config ===== */
define('DB_HOST', 'mysql.hostinger.com');
define('DB_USER', 'u648222299_keboco5');
define('DB_PASS', 'Bucaramanga3011');
define('DB_NAME', 'u648222299_viajes');



function db(): mysqli {
  $m = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  if ($m->connect_errno) exit("Error DB: ".$m->connect_error);
  $m->set_charset('utf8mb4');
  return $m;
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n){ return number_format((float)$n,0,',','.'); }
function mbnorm($s){ return mb_strtolower(trim((string)$s),'UTF-8'); }
function mbtitle($s){
  return function_exists('mb_convert_case')
    ? mb_convert_case((string)$s, MB_CASE_TITLE, 'UTF-8')
    : ucwords(strtolower((string)$s));
}

/* ===== Acción: marcar pagados ===== */
if (($_GET['action'] ?? '') === 'mark_paid' && $_SERVER['REQUEST_METHOD']==='POST'){
  $nodes = $_POST['nodes'] ?? [];
  if (!is_array($nodes)) $nodes = [];
  $all = [];
  foreach($nodes as $csv){
    foreach(explode(',', (string)$csv) as $raw){
      $n=(int)trim($raw);
      if($n>0) $all[$n]=1;
    }
  }
  $ids = array_keys($all);
  if ($ids){
    $c=db();
    foreach(array_chunk($ids,200) as $chunk){
      $ph = implode(',', array_fill(0,count($chunk),'?'));
      $types = str_repeat('i', count($chunk));
      $sql = "UPDATE prestamos
              SET pagado=1, pagado_at=NOW()
              WHERE id IN ($ph)
                AND (pagado IS NULL OR pagado=0)";
      $st=$c->prepare($sql);
      $st->bind_param($types, ...$chunk);
      $st->execute();
      $st->close();
    }
    $c->close();
    header("Location: ".$_SERVER['PHP_SELF']."?msg=pagados"); exit;
  } else {
    header("Location: ".$_SERVER['PHP_SELF']."?msg=nada"); exit;
  }
}

/* ===== Filtro texto (buscador server, aunque el cliente hace el principal) ===== */
$q  = trim($_GET['q'] ?? '');
$qNorm = mbnorm($q);

$conn = db();

/* ===== Prestamistas activos (con deuda pendiente) ===== */
$prestMap = [];
$resPL = $conn->query("SELECT prestamista
                       FROM prestamos
                       WHERE (pagado IS NULL OR pagado=0)");
while($rowPL=$resPL->fetch_row()){
  $norm = mbnorm($rowPL[0]);
  if ($norm==='') continue;
  if (!isset($prestMap[$norm])) $prestMap[$norm] = $rowPL[0];
}
ksort($prestMap, SORT_NATURAL);

/* ===== WHERE base ===== */
$where = "(pagado IS NULL OR pagado=0)";
$types=""; $params=[];
if ($q !== ''){
  $where .= " AND (LOWER(deudor) LIKE CONCAT('%',?,'%')
               OR  LOWER(prestamista) LIKE CONCAT('%',?,'%'))";
  $types .= "ss";
  $params[]=$qNorm;
  $params[]=$qNorm;
}

/* ===== Agregado por (prestamista,deudor) ===== */
$sql = "
  SELECT LOWER(TRIM(prestamista)) AS prest_key,
         MIN(prestamista) AS prest_display,
         LOWER(TRIM(deudor)) AS deud_key,
         MIN(deudor) AS deud_display,
         MIN(fecha) AS fecha_min,
         CASE WHEN CURDATE() < MIN(fecha)
              THEN 0
              ELSE TIMESTAMPDIFF(MONTH, MIN(fecha), CURDATE()) + 1
         END AS meses,
         SUM(monto) AS capital,
         SUM(
           monto*0.10*
           CASE WHEN CURDATE() < fecha
                THEN 0
                ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1
           END
         ) AS interes,
         SUM(
           monto +
           monto*0.10*
           CASE WHEN CURDATE() < fecha
                THEN 0
                ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1
           END
         ) AS total
  FROM prestamos
  WHERE $where
  GROUP BY prest_key, deud_key
  ORDER BY prest_key ASC, deud_display ASC";
$st=$conn->prepare($sql);
if($types) $st->bind_param($types, ...$params);
$st->execute();
$rs=$st->get_result();

/* ===== IDs por (prestamista,deudor) (para marcar pagado en bloque) ===== */
$sqlIds = "
  SELECT LOWER(TRIM(prestamista)) AS prest_key,
         LOWER(TRIM(deudor)) AS deud_key,
         GROUP_CONCAT(id) AS ids
  FROM prestamos
  WHERE $where
  GROUP BY prest_key, deud_key";
$st2=$conn->prepare($sqlIds);
if($types) $st2->bind_param($types, ...$params);
$st2->execute();
$rsIds=$st2->get_result();
$idsMap=[];
while($row=$rsIds->fetch_assoc()){
  $p=$row['prest_key']; $d=$row['deud_key'];
  $idsMap[$p][$d] = preg_replace('/[^0-9,]/','', (string)$row['ids']);
}
$st2->close();

/* ===== Detalle crudo de cada préstamo (para el modal) ===== */
$sqlDet = "
  SELECT
    LOWER(TRIM(prestamista)) AS prest_key,
    prestamista,
    LOWER(TRIM(deudor)) AS deud_key,
    deudor,
    id,
    fecha,
    monto,
    CASE WHEN CURDATE() < fecha
         THEN 0
         ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1
    END AS meses,
    (monto*0.10*
     CASE WHEN CURDATE() < fecha
          THEN 0
          ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1
     END) AS interes,
    (monto +
     monto*0.10*
     CASE WHEN CURDATE() < fecha
          THEN 0
          ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1
     END) AS total,
    IFNULL(pagado,0) AS pagado
  FROM prestamos
  WHERE $where
  ORDER BY prestamista, deudor, fecha ASC, id ASC";
$st3=$conn->prepare($sqlDet);
if($types) $st3->bind_param($types, ...$params);
$st3->execute();
$rsDet=$st3->get_result();

$detalleMap = [];
while($row=$rsDet->fetch_assoc()){
  $pkey = $row['prest_key'];
  $dkey = $row['deud_key'];
  if (!isset($detalleMap[$pkey])) $detalleMap[$pkey]=[];
  if (!isset($detalleMap[$pkey][$dkey])) $detalleMap[$pkey][$dkey]=[];
  $detalleMap[$pkey][$dkey][] = [
    'id'         => (int)$row['id'],
    'fecha'      => $row['fecha'],
    'monto'      => (float)$row['monto'],
    'meses'      => (int)$row['meses'],
    'interes'    => (float)$row['interes'],
    'total'      => (float)$row['total'],
    'pagado'     => (int)$row['pagado'],
    'prestamista'=> $row['prestamista'],
    'deudor'     => $row['deudor'],
  ];
}
$st3->close();

/* ===== Armar estructuras para el front ===== */
$data = [];            // DATA[prestamista] = [rows...]
$ganPrest=[];          // total interés por prestamista
$capPendPrest=[];      // total capital pendiente por prestamista
$allDebtors = [];      // listado global de deudores únicos

while($r=$rs->fetch_assoc()){
  $pkey=$r['prest_key']; $pdisp=$r['prest_display'];
  $dkey=$r['deud_key'];  $ddis=$r['deud_display'];

  if (!isset($data[$pdisp])) $data[$pdisp]=[];
  $data[$pdisp][] = [
    'nombre'  => $ddis,
    'valor'   => (float)$r['capital'],
    'fecha'   => $r['fecha_min'],
    'interes' => (float)$r['interes'],
    'total'   => (float)$r['total'],
    'meses'   => (int)$r['meses'],
    'ids_csv' => $idsMap[$pkey][$dkey] ?? '',
    '__pkey'  => $pkey, // clave normalizada del prestamista
    '__dkey'  => $dkey  // clave normalizada del deudor
  ];

  $ganPrest[$pdisp]     = ($ganPrest[$pdisp] ?? 0) + (float)$r['interes'];
  $capPendPrest[$pdisp] = ($capPendPrest[$pdisp] ?? 0) + (float)$r['capital'];

  $allDebtors[$ddis] = 1;
}
$st->close();
$conn->close();

$allDebtors = array_keys($allDebtors);
natcasesort($allDebtors);

/* ===== Formularios de marcar pagados por prestamista ===== */
$selectors = [];
foreach($data as $prest => $rows){
  ob_start(); ?>
  <form class="selector-form" method="post" action="?action=mark_paid" data-prest="<?= h($prest) ?>" style="display:none">
    <div class="selhead">
      <div class="subtitle">
        Selecciona deudores de <strong><?= h(mbtitle($prest)) ?></strong> para marcarlos como pagados:
      </div>
      <label class="subtitle" style="display:flex;gap:8px;align-items:center">
        <input type="checkbox"
               onclick="(function(ch){
                 const f=ch.closest('form');
                 f.querySelectorAll('input[name=nodes\\[\\]]').forEach(i=>i.checked=ch.checked);
               })(this)">
        Seleccionar todo
      </label>
    </div>
    <div class="selgrid">
      <?php foreach($rows as $r):
        if (($r['ids_csv'] ?? '') === '') continue; ?>
        <label class="selitem">
          <input class="cb" type="checkbox" name="nodes[]" value="<?= h($r['ids_csv']) ?>">
          <div>
            <div><strong><?= h(mbtitle($r['nombre'])) ?></strong></div>
            <div class="meta">
              prestado: $ <?= money($r['valor']) ?>
              • interés: $ <?= money($r['interes']) ?>
              • total: $ <?= money($r['total']) ?>
              • fecha: <?= h($r['fecha']) ?>
            </div>
          </div>
        </label>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;justify-content:flex-end;margin-top:8px">
      <button class="btn small" type="submit"
              onclick="return confirm('¿Marcar como pagados los seleccionados?')">
        ✔ Préstamo pagado
      </button>
    </div>
  </form>
  <?php
  $selectors[$prest] = ob_get_clean();
}

/* Mensaje flash */
$msg = $_GET['msg'] ?? '';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Préstamos Interactivos</title>
<script src="https://d3js.org/d3.v7.min.js"></script>
<style>
  :root{ --line:#d1d5db; --primary:#1976d2; }
  *{box-sizing:border-box}
  body{
    font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;
    margin:0;background:#f4f6fa;color:#111;overflow-x:hidden
  }

  .topbar{
    padding:10px 16px;
    display:flex;gap:10px;align-items:center;flex-wrap:wrap
  }
  .msg{
    background:#e8f7ee;color:#196a3b;
    padding:8px 12px;border-radius:10px;
    display:inline-flex;align-items:center;gap:8px
  }
  .chips{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .chip{
    background:#eef2ff;border:1px solid #e5e7eb;
    border-radius:999px;padding:4px 10px;
    font-size:12px
  }

  .search{
    margin-left:auto;display:flex;
    align-items:center;gap:6px;flex-wrap:wrap
  }
  .search input{
    height:34px;padding:6px 10px 6px 30px;
    border-radius:999px;border:1px solid #e5e7eb;
    background:#fff
      url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="%239aa1b2" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001l3.85 3.85a1 1 0 0 0 1.415-1.415l-3.85-3.85zm-5.242.656a5 5 0 1 1 0-10 5 5 0 0 1 0 10z"/></svg>')
      no-repeat 10px center;
    outline:0;width:260px;
  }
  .search button{
    height:34px;padding:6px 10px;
    border-radius:999px;border:1px solid #e5e7eb;
    background:#fff;cursor:pointer;
  }

  .filter-wrap{ display:flex; align-items:center; gap:8px; }
  .multiselect { position: relative; min-width: 280px; }
  .multiselect > button {
    height:34px;width:100%;text-align:left;
    border:1px solid #e5e7eb;background:#fff;
    border-radius:10px;padding:6px 10px;cursor:pointer;
  }
  .multiselect .panel {
    position:absolute; top:110%; left:0; right:0;
    z-index:30;background:#fff;border:1px solid #e5e7eb;
    border-radius:12px;box-shadow:0 10px 20px rgba(0,0,0,.06);
    max-height:280px;overflow:auto;padding:6px;
  }
  .multiselect .opt {
    display:flex;align-items:center;gap:8px;
    padding:6px 8px;border-radius:8px;
  }
  .multiselect .opt:hover { background:#f8fafc; }

  .toolbar {
    position:absolute;left:80px;top:60px;z-index:5;
    display:flex;gap:8px;flex-wrap:wrap;align-items:center;
    background:rgba(255,255,255,.8);
    backdrop-filter:saturate(1.2) blur(2px);
    padding:6px 8px;border-radius:10px;
    border:1px solid #e5e7eb;
  }
  .prest-chip{
    padding:6px 10px;border-radius:999px;cursor:pointer;user-select:none;
    background:#e3f2fd;font-weight:600;border:1px solid #dbeafe;
  }
  .prest-chip:hover{ background:#dbeafe }
  .prest-chip.active{
    background:#90caf9;border-color:#90caf9
  }

  #stage { position:relative }
  svg{ display:block; width:100%; }
  .link{fill:none;stroke:#cbd5e1;stroke-width:1.5px}
  .link2{fill:none;stroke:#9ca3af;stroke-width:1.4px;opacity:.9}

  .nodeCard{
    stroke:#cbd5e1;stroke-width:1.2px;
    filter: drop-shadow(0 1px 0 rgba(0,0,0,.02));
    cursor:pointer;
  }
  .nodeCard.m1 { fill:#FFF8DB; }
  .nodeCard.m2 { fill:#FFE9D6; }
  .nodeCard.m3 { fill:#FFE1E1; }
  .nodeCard.m0 { fill:#F3F4F6; }

  .nodeTitle{
    font-weight:800;fill:#111;font-size:13px;
    pointer-events:none
  }
  .nodeLine{
    fill:#6b7280;font-size:12px;
    pointer-events:none
  }
  .nodeAmt{ fill:#111;font-weight:800;pointer-events:none }

  .summaryCard{
    fill:#EAF5FF;stroke:#cfe8ff;stroke-width:1.2px;
  }
  .summaryTitle{
    font-weight:800;fill:#0b5ed7;font-size:14px
  }
  .summaryLine{
    fill:#374151;font-size:13px
  }
  .summaryAmt{
    fill:#0b5ed7;font-weight:800
  }

  .selector-wrap{padding:0 16px 20px}
  .selector{
    margin-top:10px;border-top:1px dashed #e5e7eb;
    padding-top:10px
  }
  .selhead{
    display:flex;justify-content:space-between;
    align-items:center;margin-bottom:8px
  }
  .selgrid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
    gap:8px
  }
  .selitem{
    display:flex;gap:8px;align-items:flex-start;
    background:#fafbff;border:1px solid #eef2ff;
    border-radius:12px;padding:8px
  }
  .selitem .meta{
    font-size:12px;color:#555
  }
  .btn{
    display:inline-flex;align-items:center;gap:8px;
    padding:9px 12px;border-radius:12px;
    background:#0b5ed7;color:#fff;font-weight:700;
    border:0;cursor:pointer
  }
  .btn.small{padding:7px 10px;border-radius:10px}

  /* ===== MODAL ===== */
  .modal-backdrop{
    position:fixed;inset:0;background:rgba(0,0,0,.4);
    display:none;align-items:center;justify-content:center;
    z-index:9999;
  }
  .modal-card{
    background:#fff;border-radius:16px;border:1px solid #e5e7eb;
    box-shadow:0 30px 60px rgba(0,0,0,.3);
    width:90%;max-width:700px;max-height:80vh;
    display:flex;flex-direction:column;
    overflow:hidden;
    animation:pop .18s cubic-bezier(.2,.8,.4,1) both;
  }
  @keyframes pop{
    0%{transform:scale(.9) translateY(20px);opacity:0}
    100%{transform:scale(1) translateY(0);opacity:1}
  }
  .modal-head{
    padding:16px 20px;border-bottom:1px solid #eef2ff;
    background:#f8fafc;
    display:flex;justify-content:space-between;
    align-items:flex-start;gap:12px
  }
  .modal-title{
    font-size:15px;font-weight:700;color:#0b5ed7;line-height:1.3
  }
  .modal-sub{
    font-size:13px;color:#4b5563;line-height:1.3;margin-top:2px
  }
  .close-btn{
    background:#fff;border:1px solid #e5e7eb;
    border-radius:10px;padding:6px 10px;
    font-size:13px;font-weight:600;
    color:#374151;cursor:pointer;
  }
  .close-btn:hover{background:#f3f4f6}
  .modal-body{
    padding:16px 20px;overflow:auto
  }

  /* leyenda de colores dentro del modal */
  .modal-legend {
    display:flex;
    flex-wrap:wrap;
    gap:8px 12px;
    font-size:12px;
    line-height:1.3;
    color:#374151;
    margin-bottom:12px;
  }
  .legend-item {
    display:flex;
    align-items:center;
    gap:6px;
  }
  .legend-swatch {
    width:14px;
    height:14px;
    border-radius:4px;
    border:1px solid #9ca3af;
  }
  .swatch-m0 { background:#F3F4F6; }
  .swatch-m1 { background:#FFF8DB; }
  .swatch-m2 { background:#FFE9D6; }
  .swatch-m3 { background:#FFE1E1; }

  /* tabla del modal */
  table.detalle-table{
    width:100%;border-collapse:collapse;
    font-size:13px;line-height:1.4;color:#1f2937;
    min-width:620px
  }
  .detalle-table thead th{
    position:sticky;top:0;background:#eaf5ff;color:#0b5ed7;
    font-weight:700;text-align:left;
    padding:8px;border-bottom:1px solid #cfe8ff;
    font-size:12px;
  }
  .detalle-table tbody td{
    padding:8px;border-bottom:1px solid #e5e7eb;
    font-size:12px;vertical-align:top;
    color:#1f2937;
  }
  .badge-ok{
    background:#e8f7ee;color:#196a3b;
    border:1px solid #bbf7d0;border-radius:8px;
    padding:2px 6px;font-size:11px;
    font-weight:600;display:inline-block;
  }
  .badge-pend{
    background:#fff7ed;color:#b45309;
    border:1px solid #fdba74;border-radius:8px;
    padding:2px 6px;font-size:11px;
    font-weight:600;display:inline-block;
  }

  /* colores de antigüedad por fila modal */
  .age-m0 td {
    background:#F3F4F6;
  }
  .age-m1 td {
    background:#FFF8DB;
  }
  .age-m2 td {
    background:#FFE9D6;
  }
  .age-m3 td {
    background:#FFE1E1;
  }
  .detalle-table tbody tr.age-m0 td,
  .detalle-table tbody tr.age-m1 td,
  .detalle-table tbody tr.age-m2 td,
  .detalle-table tbody tr.age-m3 td {
    border-bottom:1px solid #d1d5db;
    color:#1f2937;
  }

  /* fila total al final */
  .detalle-total-row td {
    background:#f8fafc !important;
    font-weight:700;
    border-top:2px solid #cfe8ff;
    border-bottom:2px solid #cfe8ff;
    color:#0b5ed7;
  }
  .detalle-total-row td.label-cell {
    text-align:right;
    color:#0b5ed7;
  }
</style>
</head>
<body>

<div class="topbar">
  <?php if ($msg): ?>
    <div class="msg">
      <?php
        echo ($msg==='pagados') ? 'Marcados como pagados.' :
             (($msg==='nada') ? 'No seleccionaste deudores.' : 'Operación realizada.');
      ?>
    </div>
  <?php endif; ?>

  <div class="chips" id="chips"></div>

  <!-- Filtro deudores (multiselección) -->
  <div class="filter-wrap">
    <div class="multiselect" id="ms-deudores">
      <button type="button" id="ms-toggle">Filtrar deudores (0 seleccionados)</button>
      <div class="panel" id="ms-panel" style="display:none">
        <div class="opt" style="justify-content:space-between; padding:6px 8px; border-bottom:1px solid #eef2ff;">
          <div style="font-weight:600">Deudores</div>
          <div style="display:flex; gap:6px">
            <button type="button" id="ms-all" class="btn small" style="background:#eef2ff;color:#0b5ed7;border:0">Todos</button>
            <button type="button" id="ms-none" class="btn small" style="background:#fff;color:#374151;border:1px solid #e5e7eb">Ninguno</button>
          </div>
        </div>
        <div id="ms-list"></div>
      </div>
    </div>
  </div>

  <!-- Buscador -->
  <div class="search">
    <input id="searchInput" type="text" placeholder="Buscar..." autocomplete="off">
    <button id="clearSearch" title="Limpiar">✕</button>
  </div>
</div>

<div id="stage">
  <div id="toolbar" class="toolbar"></div>
  <svg id="chart" height="800"></svg>
</div>

<div class="selector-wrap">
  <div id="selector-host"></div>
</div>

<!-- MODAL -->
<div class="modal-backdrop" id="loanModal">
  <div class="modal-card">
    <div class="modal-head">
      <div>
        <div class="modal-title" id="modalTitle">Detalle de préstamos</div>
        <div class="modal-sub" id="modalSub"></div>
      </div>
      <button class="close-btn" id="modalClose">Cerrar</button>
    </div>
    <div class="modal-body">

      <!-- Leyenda de colores (antigüedad en meses) -->
      <div class="modal-legend">
        <div class="legend-item">
          <span class="legend-swatch swatch-m0"></span>
          <span>0 meses (recién)</span>
        </div>
        <div class="legend-item">
          <span class="legend-swatch swatch-m1"></span>
          <span>1 mes</span>
        </div>
        <div class="legend-item">
          <span class="legend-swatch swatch-m2"></span>
          <span>2 meses</span>
        </div>
        <div class="legend-item">
          <span class="legend-swatch swatch-m3"></span>
          <span>3+ meses</span>
        </div>
      </div>

      <div style="overflow-x:auto;">
        <table class="detalle-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Fecha</th>
              <th>Meses</th>
              <th>Monto</th>
              <th>Interés</th>
              <th>Total</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody id="modalRows"><!-- dinámico --></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
/* ===== Datos desde PHP ===== */
const DATA = <?php echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK); ?>;
const GANANCIA = <?php echo json_encode($ganPrest, JSON_NUMERIC_CHECK); ?>;
const CAPITAL  = <?php echo json_encode($capPendPrest, JSON_NUMERIC_CHECK); ?>;
const SELECTORS_HTML = <?php echo json_encode($selectors, JSON_UNESCAPED_UNICODE); ?>;
const ALL_DEBTORS = <?php echo json_encode(array_values($allDebtors), JSON_UNESCAPED_UNICODE); ?>;
const DETALLE = <?php echo json_encode($detalleMap, JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK); ?>;

/* ===== D3 setup ===== */
const svg = d3.select("#chart");
const ROOT_TX = 80, ROOT_TY = 120;
const rootG = svg.append("g").attr("transform", `translate(${ROOT_TX},${ROOT_TY})`);
const g = rootG.append("g");
const chipsHost = document.getElementById("chips");
const selectorHost = document.getElementById("selector-host");

/* ===== Toolbar prestamistas ===== */
const toolbar = document.getElementById("toolbar");
const prestNombres = Object.keys(DATA);
let currentPrest = prestNombres[0] || null;

function renderToolbar(active){
  toolbar.innerHTML = "";
  prestNombres.forEach((p)=>{
    const b = document.createElement("button");
    b.type = "button";
    b.className = "prest-chip" + (p===active ? " active" : "");
    b.textContent = p;
    b.onclick = ()=>{
      currentPrest = p;
      document.querySelectorAll(".prest-chip").forEach(x=>x.classList.remove("active"));
      b.classList.add("active");
      drawTree(p);
    };
    toolbar.appendChild(b);
  });
}
renderToolbar(currentPrest);

/* ===== Chips resumen arriba ===== */
function renderChips(prest, visibleRows=null){
  chipsHost.innerHTML = "";

  let interes, capital;
  if (isGlobalMode()) {
    const all = visibleRows || collectRowsForSelected();
    interes = all.reduce((a,r)=>a+Number(r.interes||0),0);
    capital = all.reduce((a,r)=>a+Number(r.valor||0),0);

    const chipM = document.createElement("span");
    chipM.className="chip";
    chipM.textContent = "Modo global (todos los prestamistas)";

    const chipF = document.createElement("span");
    chipF.className="chip";
    chipF.textContent = `Filtro: ${SELECTED_DEUDORES.size} deudor(es)`;

    chipsHost.append(chipM, chipF);
  } else {
    interes = Number(GANANCIA[prest]||0);
    capital = Number(CAPITAL[prest]||0);
    if (Array.isArray(visibleRows)) {
      interes = visibleRows.reduce((a,r)=>a+Number(r.interes||0),0);
      capital = visibleRows.reduce((a,r)=>a+Number(r.valor||0),0);
    }
  }

  const chip1 = document.createElement("span");
  chip1.className = "chip";
  chip1.textContent = `Ganancia (interés): $ ${interes.toLocaleString()}`;

  const chip2 = document.createElement("span");
  chip2.className = "chip";
  chip2.textContent = `Total prestado (pend.): $ ${capital.toLocaleString()}`;

  const chipL1 = document.createElement("span");
  chipL1.className="chip";
  chipL1.textContent="1 mes";
  chipL1.style.background="#FFF8DB";

  const chipL2 = document.createElement("span");
  chipL2.className="chip";
  chipL2.textContent="2 meses";
  chipL2.style.background="#FFE9D6";

  const chipL3 = document.createElement("span");
  chipL3.className="chip";
  chipL3.textContent="3+ meses";
  chipL3.style.background="#FFE1E1";

  chipsHost.append(chip1, chip2, chipL1, chipL2, chipL3);
}

/* ===== Selector "marcar pagados" abajo ===== */
function renderSelector(prest){
  selectorHost.innerHTML = "";
  const wrap = document.createElement("div");
  wrap.className = "selector";
  wrap.innerHTML = SELECTORS_HTML[prest] || '<div class="chip">Sin deudores pendientes</div>';
  selectorHost.appendChild(wrap);

  const form = selectorHost.querySelector(".selector-form");
  if (form) form.style.display = "block";
}

/* ===== Buscador (cliente) ===== */
const searchInput = document.getElementById('searchInput');
const clearBtn = document.getElementById('clearSearch');
let searchTerm = '';

function norm(s){ return (s||'').toString().toLocaleLowerCase(); }

function matches(row){
  if (!searchTerm) return true;
  const q = norm(searchTerm);
  return norm(row.nombre).includes(q)
    || norm(row.fecha).includes(q)
    || norm(String(row.valor)).includes(q)
    || norm(String(row.total)).includes(q)
    || norm(String(row.__prest||'')).includes(q);
}

function debounce(fn, ms){
  let t;
  return (...a)=>{
    clearTimeout(t);
    t=setTimeout(()=>fn(...a), ms);
  };
}

searchInput.addEventListener('input', debounce(e=>{
  searchTerm = e.target.value || '';
  if (currentPrest) drawTree(currentPrest);
}, 160));

clearBtn.addEventListener('click', ()=>{
  searchTerm = '';
  searchInput.value = '';
  if (currentPrest) drawTree(currentPrest);
});

/* ===== Filtro multi-deudor (modo global) ===== */
const ms = document.getElementById('ms-deudores');
const msToggle = document.getElementById('ms-toggle');
const msPanel  = document.getElementById('ms-panel');
const msList   = document.getElementById('ms-list');
const msAll    = document.getElementById('ms-all');
const msNone   = document.getElementById('ms-none');

const SELECTED_DEUDORES = new Set(); // nombres exactos

function isGlobalMode(){ return SELECTED_DEUDORES.size > 0; }

function updateMsLabel(){
  msToggle.textContent = `Filtrar deudores (${SELECTED_DEUDORES.size} seleccionados)`;
}

function renderMsList(){
  msList.innerHTML = '';
  const frag = document.createDocumentFragment();
  ALL_DEBTORS.forEach(name=>{
    const row = document.createElement('label');
    row.className = 'opt';

    const cb = document.createElement('input');
    cb.type='checkbox';
    cb.checked = SELECTED_DEUDORES.has(name);

    const sp = document.createElement('span');
    sp.textContent = name;

    row.appendChild(cb);
    row.appendChild(sp);

    cb.addEventListener('change', ()=>{
      if(cb.checked) SELECTED_DEUDORES.add(name);
      else SELECTED_DEUDORES.delete(name);
      updateMsLabel();
      drawTree(currentPrest);
    });

    frag.appendChild(row);
  });
  msList.appendChild(frag);
}

msToggle.addEventListener('click', ()=>{
  msPanel.style.display = (msPanel.style.display==='none' || !msPanel.style.display) ? 'block':'none';
});

msAll.addEventListener('click', ()=>{
  ALL_DEBTORS.forEach(n=>SELECTED_DEUDORES.add(n));
  updateMsLabel();
  renderMsList();
  drawTree(currentPrest);
});

msNone.addEventListener('click', ()=>{
  SELECTED_DEUDORES.clear();
  updateMsLabel();
  renderMsList();
  drawTree(currentPrest);
});

document.addEventListener('click', (e)=>{
  if (!ms.contains(e.target)) msPanel.style.display='none';
});

renderMsList();
updateMsLabel();

/* ===== wrapText para SVG ===== */
function wrapText(textSel, width){
  textSel.each(function(){
    const text = d3.select(this);
    const words = text.text().split(/\s+/).filter(Boolean);
    let line = [];
    let tspan = text.text(null)
                    .append("tspan")
                    .attr("x", text.attr("x"))
                    .attr("dy", 0);
    let lineNumber = 0;
    const lineHeight = 14;
    words.forEach((w)=>{
      line.push(w);
      tspan.text(line.join(" "));
      if (tspan.node().getComputedTextLength() > width){
        line.pop();
        tspan.text(line.join(" "));
        line = [w];
        tspan = text.append("tspan")
          .attr("x", text.attr("x"))
          .attr("dy", ++lineNumber * lineHeight)
          .text(w);
      }
    });
  });
}

/* ===== helpers globales ===== */
function collectRowsForSelected(){
  const rows = [];
  if (SELECTED_DEUDORES.size === 0) return rows;
  for (const prest of Object.keys(DATA)) {
    const list = DATA[prest] || [];
    list.forEach(r => {
      if (SELECTED_DEUDORES.has(r.nombre)) {
        rows.push({ ...r, __prest: prest });
      }
    });
  }
  return rows;
}

/* ===== MODAL logic ===== */
const loanModal  = document.getElementById('loanModal');
const modalClose = document.getElementById('modalClose');
const modalTitle = document.getElementById('modalTitle');
const modalSub   = document.getElementById('modalSub');
const modalRows  = document.getElementById('modalRows');

modalClose.addEventListener('click', ()=>{
  loanModal.style.display='none';
});
loanModal.addEventListener('click', (e)=>{
  if (e.target === loanModal) loanModal.style.display='none';
});

// Capitaliza estilo título "Juan Perez"
function mbTitleJs(str){
  const s = (str||'').toString().toLowerCase();
  return s.replace(/\b([a-záéíóúñ])/gi, (m) => m.toUpperCase());
}

/**
 * fillAndOpenModal(prestKey,deudKey,prestName,deudName)
 * Busca DETALLE[prestKey][deudKey] y renderiza filas con totales
 */
function fillAndOpenModal(prestKey,deudKey,prestName,deudName){
  modalRows.innerHTML = '';

  const lista = (DETALLE[prestKey] && DETALLE[prestKey][deudKey])
    ? DETALLE[prestKey][deudKey]
    : [];

  if (!lista.length){
    modalRows.innerHTML =
      '<tr><td colspan="7" style="text-align:center;color:#6b7280;padding:16px;">No hay registros.</td></tr>';
  } else {

    // acumuladores para total
    let sumMonto = 0;
    let sumInteres = 0;
    let sumTotal = 0;

    lista.forEach(item=>{
      const tr = document.createElement('tr');

      // ===== color por antigüedad =====
      const mesesNum = Number(item.meses || 0);
      let ageClass = 'age-m0';
      if (mesesNum >= 3) {
        ageClass = 'age-m3';
      } else if (mesesNum === 2) {
        ageClass = 'age-m2';
      } else if (mesesNum === 1) {
        ageClass = 'age-m1';
      } // else queda m0
      tr.className = ageClass;

      // columnas
      const tdId = document.createElement('td');
      tdId.textContent = item.id;

      const tdFecha = document.createElement('td');
      tdFecha.textContent = item.fecha;

      const tdMeses = document.createElement('td');
      tdMeses.textContent = mesesNum + " mes(es)";

      const tdMonto = document.createElement('td');
      tdMonto.textContent = "$ " + Number(item.monto||0).toLocaleString();

      const tdInt = document.createElement('td');
      tdInt.textContent = "$ " + Number(item.interes||0).toLocaleString();

      const tdTot = document.createElement('td');
      tdTot.textContent = "$ " + Number(item.total||0).toLocaleString();

      const tdEstado = document.createElement('td');
      const badge = document.createElement('span');
      if (item.pagado && Number(item.pagado)===1){
        badge.className = 'badge-ok';
        badge.textContent = 'Pagado';
      } else {
        badge.className = 'badge-pend';
        badge.textContent = 'Pendiente';
      }
      tdEstado.appendChild(badge);

      tr.appendChild(tdId);
      tr.appendChild(tdFecha);
      tr.appendChild(tdMeses);
      tr.appendChild(tdMonto);
      tr.appendChild(tdInt);
      tr.appendChild(tdTot);
      tr.appendChild(tdEstado);

      modalRows.appendChild(tr);

      // acumular totales
      sumMonto   += Number(item.monto||0);
      sumInteres += Number(item.interes||0);
      sumTotal   += Number(item.total||0);
    });

    // ===== fila resumen TOTAL =====
    const trTotal = document.createElement('tr');
    trTotal.className = 'detalle-total-row';

    const tdEmpty1 = document.createElement('td');
    tdEmpty1.textContent = ''; // ID vacío

    const tdEmpty2 = document.createElement('td');
    tdEmpty2.textContent = ''; // Fecha vacío

    const tdEmpty3 = document.createElement('td');
    tdEmpty3.className = 'label-cell';
    tdEmpty3.textContent = 'TOTAL:';

    const tdMontoTotal = document.createElement('td');
    tdMontoTotal.textContent = "$ " + sumMonto.toLocaleString();

    const tdInteresTotal = document.createElement('td');
    tdInteresTotal.textContent = "$ " + sumInteres.toLocaleString();

    const tdGranTotal = document.createElement('td');
    tdGranTotal.textContent = "$ " + sumTotal.toLocaleString();

    const tdEstadoTotal = document.createElement('td');
    tdEstadoTotal.textContent = ''; // estado vacío

    trTotal.appendChild(tdEmpty1);
    trTotal.appendChild(tdEmpty2);
    trTotal.appendChild(tdEmpty3);
    trTotal.appendChild(tdMontoTotal);
    trTotal.appendChild(tdInteresTotal);
    trTotal.appendChild(tdGranTotal);
    trTotal.appendChild(tdEstadoTotal);

    modalRows.appendChild(trTotal);
  }

  modalTitle.textContent = 'Detalle de préstamos';
  modalSub.textContent   = mbTitleJs(deudName) + ' ↔ ' + mbTitleJs(prestName);

  loanModal.style.display='flex';
}

/* ===== Dibujo del árbol principal ===== */
function drawTree(prestamista) {
  g.selectAll("*").remove();

  const global = isGlobalMode();

  // 1) Conjunto base de filas a mostrar
  let allRows;
  if (global) {
    // modo global: todos los prestamistas pero solo los deudores seleccionados
    allRows = collectRowsForSelected();
  } else {
    // modo normal: solo el prestamista activo
    allRows = DATA[prestamista] || [];
  }

  // 2) Filtro buscador + filtro deudor
  const rows = allRows.filter(r => {
    if (SELECTED_DEUDORES.size > 0 && !SELECTED_DEUDORES.has(r.nombre)) return false;
    if (!matches(r)) return false;
    return true;
  });

  // 3) Layout
  const cardW = 480, padX=12, padY=10, lineGap=18;
  const svgWidth = document.getElementById("stage").clientWidth;
  svg.attr("width", svgWidth);
  const svgH = +svg.attr("height");

  const extraLines = global ? 2 : 1; // si es global mostramos "Prestamista"
  const approxCardH = padY*2 + lineGap*(2 + extraLines);
  const treeLayout = d3.tree()
    .nodeSize([ approxCardH + 24, cardW + 240 ])
    .separation((a,b)=> (a.parent===b.parent? 1.2 : 1.5));

  const rootName = global ? "Todos los prestamistas" : prestamista;
  const root = d3.hierarchy({ name: rootName, children: rows });

  treeLayout.size([svgH - 200, 1]);
  treeLayout(root);

  const usableW = svgWidth - ROOT_TX - 40;
  const centerX = Math.max(cardW/2 + 40, (usableW - cardW) / 2);

  root.each(d => {
    if (d.depth === 0) d.y = 0;
    if (d.depth === 1) d.y = centerX;
  });

  // enlaces raíz → deudores
  const linkPath = d3.linkHorizontal().x(d=>d.y).y(d=>d.x);
  const links = g.selectAll(".link")
    .data(root.links())
    .join("path")
    .attr("class", "link")
    .attr("d", linkPath)
    .attr("stroke-dasharray", function(){ return this.getTotalLength(); })
    .attr("stroke-dashoffset", function(){ return this.getTotalLength(); });

  links.transition()
    .delay((d,i)=>120+i*30)
    .duration(700)
    .ease(d3.easeCubicOut)
    .attr("stroke-dashoffset",0);

  // nodos (prestamista raíz + tarjetas deudor)
  const nodes = g.selectAll(".node")
    .data(root.descendants())
    .join("g")
    .attr("class","node")
    .attr("transform", d => `translate(${d.y - 160},${d.x})`)
    .style("opacity",0);

  nodes.transition()
    .delay((d,i)=> d.depth===0?0:150+i*40)
    .duration(600)
    .ease(d3.easeCubicOut)
    .attr("transform", d => `translate(${d.y},${d.x})`)
    .style("opacity",1);

  nodes.each(function(d){
    const sel = d3.select(this);

    // nodo raíz (título del árbol)
    if (d.depth === 0) {
      sel.append("circle")
        .attr("r", 8)
        .attr("fill", "#1976d2")
        .attr("stroke","#fff")
        .attr("stroke-width",2);

      sel.append("text")
        .attr("dy","0.31em")
        .attr("x",-14)
        .attr("text-anchor","end")
        .text(d.data.name);
      return;
    }

    // calcular alto de la tarjeta en base al título
    const temp = sel.append("text")
      .attr("class","nodeTitle")
      .attr("x", padX)
      .attr("y", 0)
      .style("opacity",0)
      .text(d.data.nombre);

    wrapText(temp, cardW - padX*2);
    const titleRows = temp.selectAll("tspan").nodes().length || 1;
    temp.remove();

    const rowsCount = titleRows + (global ? 2 : 1);
    const cardH = padY*2 + lineGap*rowsCount;

    const m = +d.data.meses || 0;
    const mcls = (m >= 3) ? "m3" : (m === 2 ? "m2" : (m === 1 ? "m1" : "m0"));

    // rectángulo clickable
    sel.append("rect")
      .attr("class", `nodeCard ${mcls}`)
      .attr("x", 0)
      .attr("y", -cardH/2)
      .attr("width", cardW)
      .attr("height", cardH)
      .attr("rx", 12)
      .attr("ry", 12)
      .attr("data-prest-key",
        global
          ? (d.data.__prest||'').toLowerCase().trim()
          : (prestamista||'').toLowerCase().trim()
      )
      .attr("data-deud-key",
        (d.data.__dkey || '').toLowerCase().trim()
      )
      .attr("data-prest-name",
        global ? (d.data.__prest||'') : prestamista
      )
      .attr("data-deud-name",
        d.data.nombre
      )
      .attr("transform", "scale(0.98)")
      .on("click", function(){
        const pk = this.getAttribute('data-prest-key');
        const dk = this.getAttribute('data-deud-key');
        const pn = this.getAttribute('data-prest-name');
        const dn = this.getAttribute('data-deud-name');
        fillAndOpenModal(pk, dk, pn, dn);
      })
      .transition()
      .delay(250)
      .duration(400)
      .ease(d3.easeCubicOut)
      .attr("transform", "scale(1)");

    // contenido de la tarjeta
    let y = -cardH/2 + padY + 12;

    // título (deudor)
    const t = sel.append("text")
      .attr("class","nodeTitle")
      .attr("x", padX)
      .attr("y", y)
      .text(d.data.nombre);
    wrapText(t, cardW - padX*2);

    const titleBox = t.node().getBBox();
    y = titleBox.y + titleBox.height + 2;

    // si es global, mostramos el prestamista
    if (global) {
      const l0 = sel.append("text")
        .attr("class","nodeLine")
        .attr("x", padX)
        .attr("y", y + lineGap/1.2);

      l0.text("Prestamista: ");
      l0.append("tspan")
        .attr("class","nodeAmt")
        .text(d.data.__prest || "");
      y += lineGap;
    }

    // línea con montos, intereses, fecha
    const l1 = sel.append("text")
      .attr("class","nodeLine")
      .attr("x", padX)
      .attr("y", y + lineGap)
      .style("opacity", 0)
      .text("valor prestado: ");
    l1.append("tspan")
      .attr("class","nodeAmt")
      .text(`$ ${Number(d.data.valor||0).toLocaleString()}`);
    l1.append("tspan").text(" • interés: ");
    l1.append("tspan")
      .attr("class","nodeAmt")
      .text(`$ ${Number(d.data.interes||0).toLocaleString()}`);
    l1.append("tspan").text(" • total ");
    l1.append("tspan")
      .attr("class","nodeAmt")
      .text(`$ ${Number(d.data.total||0).toLocaleString()}`);
    l1.append("tspan").text(" • fecha: ");
    l1.append("tspan")
      .attr("class","nodeAmt")
      .text(d.data.fecha || "");

    wrapText(l1, cardW - padX*2);

    l1.transition()
      .delay(260)
      .duration(400)
      .style("opacity", 1);
  });

  // ===== Resumen lateral =====
  const visibleRows = rows;
  const totalInteres = visibleRows.reduce((a,r)=>a+Number(r.interes||0),0);
  const totalCapital = visibleRows.reduce((a,r)=>a+Number(r.valor||0),0);

  const deudores = root.descendants().filter(d=>d.depth===1);
  const midY = d3.mean(deudores, d=>d.x) || 0;

  const summaryX = centerX + cardW + 280;
  const sumW = 380, sumH = 140, sumPadX = 14, sumLine = 24;

  const summaryG = g.append("g")
    .attr("class","summary")
    .attr("transform", `translate(${summaryX - 220},${midY})`)
    .style("opacity", 0);

  summaryG.transition()
    .delay(220)
    .duration(600)
    .ease(d3.easeCubicOut)
    .attr("transform", `translate(${summaryX},${midY})`)
    .style("opacity", 1);

  summaryG.append("rect")
    .attr("class","summaryCard")
    .attr("x", 0)
    .attr("y", -sumH/2)
    .attr("width", sumW)
    .attr("height", sumH)
    .attr("rx", 14)
    .attr("ry", 14);

  summaryG.append("text")
    .attr("class","summaryTitle")
    .attr("x", sumPadX)
    .attr("y", -sumH/2 + sumLine)
    .text(global ? "Resumen (todos los prestamistas)" : "Resumen del prestamista");

  let sy = -sumH/2 + sumLine + 20;
  const s1 = summaryG.append("text")
    .attr("class","summaryLine")
    .attr("x", sumPadX)
    .attr("y", sy)
    .text("Ganancia (interés): ");
  s1.append("tspan")
    .attr("class","summaryAmt")
    .text(`$ ${totalInteres.toLocaleString()}`);
  sy += 22;
  const s2 = summaryG.append("text")
    .attr("class","summaryLine")
    .attr("x", sumPadX)
    .attr("y", sy)
    .text("Total prestado (pend.): ");
  s2.append("tspan")
    .attr("class","summaryAmt")
    .text(`$ ${totalCapital.toLocaleString()}`);

  // enlaces deudor -> resumen global
  const link2 = d3.linkHorizontal().x(d=>d.y).y(d=>d.x);
  g.selectAll(".link2")
    .data(
      deudores.map(d => ({
        source:{x:d.x, y:d.y + cardW},
        target:{x:midY, y:summaryX}
      }))
    )
    .join("path")
    .attr("class","link2")
    .attr("d", link2)
    .attr("stroke-dasharray", function(){ return this.getTotalLength(); })
    .attr("stroke-dashoffset", function(){ return this.getTotalLength(); })
    .transition()
    .delay((d,i)=>200+i*25)
    .duration(650)
    .ease(d3.easeCubicOut)
    .attr("stroke-dashoffset", 0);

  // ===== Tarjetas resumen extra por prestamista en modo global =====
  if (global) {
    const perPrest = {};
    visibleRows.forEach(r=>{
      const p = r.__prest || 'Desconocido';
      if (!perPrest[p]) perPrest[p] = { interes:0, capital:0 };
      perPrest[p].interes += Number(r.interes||0);
      perPrest[p].capital += Number(r.valor||0);
    });

    const names = Object.keys(perPrest).sort((a,b)=> a.localeCompare(b));
    const cardHres = 92, gap = 10;
    let startY = midY + sumH/2 + 24;

    names.forEach((p,i)=>{
      const gP = g.append("g")
        .attr("class","summary")
        .attr("transform", `translate(${summaryX},${startY + i*(cardHres+gap)})`)
        .style("opacity", 0);

      gP.transition()
        .delay(250 + i*80)
        .duration(500)
        .style("opacity", 1);

      gP.append("rect")
        .attr("class","summaryCard")
        .attr("x", 0)
        .attr("y", -cardHres/2)
        .attr("width", sumW)
        .attr("height", cardHres)
        .attr("rx", 12)
        .attr("ry", 12);

      gP.append("text")
        .attr("class","summaryTitle")
        .attr("x", sumPadX)
        .attr("y", -cardHres/2 + 22)
        .text(`Prestamista: ${p}`);

      const sy1 = -cardHres/2 + 46;
      const t1 = gP.append("text")
        .attr("class","summaryLine")
        .attr("x", sumPadX)
        .attr("y", sy1)
        .text("Interés: ");
      t1.append("tspan")
        .attr("class","summaryAmt")
        .text(`$ ${perPrest[p].interes.toLocaleString()}`);

      const t2 = gP.append("text")
        .attr("class","summaryLine")
        .attr("x", sumPadX)
        .attr("y", sy1+20)
        .text("Capital: ");
      t2.append("tspan")
        .attr("class","summaryAmt")
        .text(`$ ${perPrest[p].capital.toLocaleString()}`);
    });
  }

  // chips arriba
  renderChips(prestamista, visibleRows);

  // abajo: selector de "pagados" SOLO si no es global
  if (!global) renderSelector(prestamista);
}

/* ===== resize ===== */
window.addEventListener('resize', ()=>{
  if (currentPrest) drawTree(currentPrest);
});

/* ===== inicio ===== */
if (currentPrest){
  drawTree(currentPrest);
}
</script>
</body>
</html>
