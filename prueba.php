<?php
/*********************************************************
 * prestamos_visual_interactivo.php
 * v5.0
 * - Visual D3 (vista por prestamista / global multi-deudor)
 * - COMISIONES INTEGRADAS: Cada comisión aparece con su prestamista
 * - Las comisiones se diferencian visualmente
 * - Selector "marcar pagados" 
 * - Modal con historial individual (click en el nodo normal)
 * - Fila TOTAL y leyenda de colores en el modal
 * - Colores por antigüedad (meses) en modal y nodos
 * - Contador de préstamos por deudor en cada nodo
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

/* ===== Filtro texto ===== */
$q  = trim($_GET['q'] ?? '');
$qNorm = mbnorm($q);

$conn = db();

/* ===== Prestamistas activos (deudas pendientes) ===== */
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

/* ===== WHERE base para préstamos vigentes ===== */
$where = "(pagado IS NULL OR pagado=0)";
$types=""; $params=[];
if ($q !== ''){
  $where .= " AND (LOWER(deudor) LIKE CONCAT('%',?,'%')
               OR  LOWER(prestamista) LIKE CONCAT('%',?,'%')
               OR  LOWER(IFNULL(comision_gestor_nombre,'')) LIKE CONCAT('%',?,'%')
               OR  LOWER(IFNULL(comision_origen_prestamista,'')) LIKE CONCAT('%',?,'%')
               )";
  $types .= "ssss";
  $params[]=$qNorm;
  $params[]=$qNorm;
  $params[]=$qNorm;
  $params[]=$qNorm;
}

/* =========================================================
   = NORMAL VIEW (por prestamista y deudor)
   ========================================================= */

$sql = "
  SELECT LOWER(TRIM(prestamista)) AS prest_key,
         MIN(prestamista)        AS prest_display,
         LOWER(TRIM(deudor))     AS deud_key,
         MIN(deudor)             AS deud_display,
         MIN(fecha)              AS fecha_min,
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

/* ===== IDs por prestamista+deudor (para marcar pagado en bloque) ===== */
$sqlIds = "
  SELECT LOWER(TRIM(prestamista)) AS prest_key,
         LOWER(TRIM(deudor))     AS deud_key,
         GROUP_CONCAT(id)        AS ids
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

/* ===== Detalle crudo cada préstamo (para modal y contador) ===== */
$sqlDet = "
  SELECT
    LOWER(TRIM(prestamista)) AS prest_key,
    prestamista,
    LOWER(TRIM(deudor))      AS deud_key,
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
    IFNULL(pagado,0) AS pagado,

    comision_gestor_nombre,
    comision_gestor_porcentaje,
    comision_base_monto,
    comision_origen_prestamista
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

    'comi_nombre'   => $row['comision_gestor_nombre'],
    'comi_pct'      => $row['comision_gestor_porcentaje'],
    'comi_base'     => $row['comision_base_monto'],
    'comi_origen'   => $row['comision_origen_prestamista'],
  ];
}
$st3->close();

/* ===== Armar DATA para vista normal (por prestamista) ===== */
$data = [];
$ganPrest=[];
$capPendPrest=[];
$allDebtors = [];

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
    '__pkey'  => $pkey,
    '__dkey'  => $dkey
  ];

  $ganPrest[$pdisp]     = ($ganPrest[$pdisp] ?? 0) + (float)$r['interes'];
  $capPendPrest[$pdisp] = ($capPendPrest[$pdisp] ?? 0) + (float)$r['capital'];

  $allDebtors[$ddis] = 1;
}
$st->close();

/* =========================================================
   = COMISIONES INTEGRADAS: Agregar comisiones a cada prestamista
   ========================================================= */

// Buscar todas las comisiones pendientes
$comisionesPorPrestamista = [];

foreach ($detalleMap as $pkey => $byDeudor){
  foreach ($byDeudor as $dkey => $lista){
    foreach ($lista as $it){
      // Si hay comisión Y está pendiente
      if (!empty($it['comi_nombre']) && (int)$it['pagado']===0){
        $gestor = $it['comi_nombre'];
        $comisionKey = mbnorm($gestor);
        
        if (!isset($comisionesPorPrestamista[$comisionKey])) {
          $comisionesPorPrestamista[$comisionKey] = [
            'prestamista' => $gestor,
            'comisiones' => []
          ];
        }
        
        $comisionesPorPrestamista[$comisionKey]['comisiones'][] = [
          'deudor'      => $it['deudor'],
          'origen'      => $it['comi_origen'] ?: $it['prestamista'],
          'fecha'       => $it['fecha'],
          'base'        => (float)$it['comi_base'],
          'pct'         => (float)$it['comi_pct'],
          'ganancia'    => (float)$it['comi_base'] * ($it['comi_pct']/100.0),
          'meses'       => $it['meses']
        ];
      }
    }
  }
}

// Agregar las comisiones a cada prestamista correspondiente
foreach ($comisionesPorPrestamista as $comisionData) {
  $prestamista = $comisionData['prestamista'];
  
  if (!isset($data[$prestamista])) {
    $data[$prestamista] = [];
  }
  
  foreach ($comisionData['comisiones'] as $comision) {
    $deudorComision = $comision['deudor'] . ' 💼 Comisión ' . $comision['pct'] . '%';
    
    $data[$prestamista][] = [
      'nombre'      => $deudorComision,
      'valor'       => $comision['ganancia'],
      'fecha'       => $comision['fecha'],
      'interes'     => 0,
      'total'       => $comision['ganancia'],
      'meses'       => $comision['meses'],
      'ids_csv'     => '', // Las comisiones no se marcan como pagadas aquí
      '__pkey'      => mbnorm($prestamista),
      '__dkey'      => mbnorm($deudorComision),
      'es_comision' => true, // Flag para identificar que es comisión
      'origen'      => $comision['origen'],
      'base'        => $comision['base'],
      'pct_comision'=> $comision['pct']
    ];
    
    // Actualizar totales del prestamista
    $ganPrest[$prestamista] = ($ganPrest[$prestamista] ?? 0) + $comision['ganancia'];
    $capPendPrest[$prestamista] = ($capPendPrest[$prestamista] ?? 0) + $comision['ganancia'];
    
    $allDebtors[$deudorComision] = 1;
  }
}

/* ===== cerrar conn ===== */
$conn->close();

/* ===== Normalizamos lista de deudores para el filtro ===== */
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
        // No mostrar comisiones en el selector de pagados
        if (($r['ids_csv'] ?? '') === '' || ($r['es_comision'] ?? false)) continue; ?>
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
  /* ESTILOS EXISTENTES... */

  /* Nuevos estilos para comisiones */
  .nodeCard.comision {
    fill: #F0F9FF;
    stroke: #BAE6FD;
    stroke-width: 1.5px;
  }
  .nodeTitle.comision {
    fill: #0369A1;
  }
  .nodeAmt.comision {
    fill: #0369A1;
  }

  .swatch-comision {
    background: #F0F9FF;
    border: 1px solid #BAE6FD;
  }

  .chip-comision {
    background: #F0F9FF;
    border: 1px solid #BAE6FD;
    color: #0369A1;
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

<!-- MODAL préstamos normales -->
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
      <!-- Leyenda de colores -->
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
        <div class="legend-item">
          <span class="legend-swatch swatch-comision"></span>
          <span>Comisiones</span>
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

  const chipCom = document.createElement("span");
  chipCom.className="chip chip-comision";
  chipCom.textContent="Comisiones";

  chipsHost.append(chip1, chip2, chipL1, chipL2, chipL3, chipCom);
}

/* ===== Selector "marcar pagados" ===== */
function renderSelector(prest){
  selectorHost.innerHTML = "";
  const wrap = document.createElement("div");
  wrap.className = "selector";
  wrap.innerHTML = SELECTORS_HTML[prest] || '<div class="chip">Sin deudores pendientes</div>';
  selectorHost.appendChild(wrap);

  const form = selectorHost.querySelector(".selector-form");
  if (form) form.style.display = "block";
}

// ... (el resto del código JavaScript se mantiene igual hasta la función drawTree)

/* ===== Dibujo principal ===== */
function drawTree(prestamista) {
  g.selectAll("*").remove();

  const global = isGlobalMode();

  // PREPARAMOS ROWS A MOSTRAR
  let allRows = global ? collectRowsForSelected() : (DATA[prestamista] || []);

  // FILTROS (buscador / multi)
  const rows = allRows.filter(r => {
    if (global && SELECTED_DEUDORES.size > 0 && !SELECTED_DEUDORES.has(r.nombre)) return false;
    if (!matches(r)) return false;
    return true;
  });

  // LAYOUT / TREE
  const cardW = 480;
  const padX=12, padY=10, lineGap=18;

  const svgWidth = document.getElementById("stage").clientWidth;
  svg.attr("width", svgWidth);
  const svgH = +svg.attr("height");

  const extraLines = (global ? 2 : 1);
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

  // enlaces raíz -> hijos
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

  // nodos
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

    if (d.depth === 0) {
      // Nodo raíz (igual que antes)
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

    // ---- tarjeta hijo ----
    const isComision = d.data.es_comision || false;

    // calcular alto de tarjeta:
    const temp = sel.append("text")
      .attr("class", isComision ? "nodeTitle comision" : "nodeTitle")
      .attr("x", padX)
      .attr("y", 0)
      .style("opacity",0)
      .text(d.data.nombre);

    wrapText(temp, cardW - padX*2);
    const titleRows = temp.selectAll("tspan").nodes().length || 1;
    temp.remove();

    const rowsCount = titleRows + (global ? 2 : 1);
    const cardH = padY*2 + lineGap*rowsCount;

    // color: comisión vs préstamo normal
    let cardClass = "nodeCard";
    if (isComision) {
      cardClass += " comision";
    } else {
      const m = +d.data.meses || 0;
      const mcls = (m >= 3) ? "m3" : (m === 2 ? "m2" : (m === 1 ? "m1" : "m0"));
      cardClass += " " + mcls;
    }

    // calcular contador de préstamos si es modo normal
    let loanCount = 0;
    if (!isComision){
      const prestKeyForCount = (
        global
          ? (d.data.__prest || '').toLowerCase().trim()
          : (prestamista || '').toLowerCase().trim()
      );
      const deudKeyForCount = (d.data.__dkey || '').toLowerCase().trim();

      if (
        DETALLE[prestKeyForCount] &&
        DETALLE[prestKeyForCount][deudKeyForCount]
      ) {
        loanCount = DETALLE[prestKeyForCount][deudKeyForCount].length;
      }
    }

    // tarjeta
    const rect = sel.append("rect")
      .attr("class", cardClass)
      .attr("x", 0)
      .attr("y", -cardH/2)
      .attr("width", cardW)
      .attr("height", cardH)
      .attr("rx", 12)
      .attr("ry", 12)
      .attr("transform", "scale(0.98)");

    // Solo los préstamos normales abren modal
    if (!isComision){
      rect
        .attr("data-prest-key",  global ? (d.data.__prest||'').toLowerCase().trim() : (prestamista||'').toLowerCase().trim())
        .attr("data-deud-key",   (d.data.__dkey||'').toLowerCase().trim())
        .attr("data-prest-name", global ? (d.data.__prest||'') : prestamista)
        .attr("data-deud-name",  d.data.nombre)
        .on("click", function(){
          const pk = this.getAttribute('data-prest-key');
          const dk = this.getAttribute('data-deud-key');
          const pn = this.getAttribute('data-prest-name');
          const dn = this.getAttribute('data-deud-name');
          fillAndOpenModal(pk, dk, pn, dn);
        });
    } else {
      rect.attr("style","cursor:default");
    }

    rect.transition()
      .delay(250)
      .duration(400)
      .ease(d3.easeCubicOut)
      .attr("transform", "scale(1)");

    let y = -cardH/2 + padY + 12;

    // Título
    let titleText = "";
    if (isComision){
      titleText = d.data.nombre;
    } else {
      titleText =
        loanCount === 1
          ? `${d.data.nombre} (1 préstamo)`
          : `${d.data.nombre} (${loanCount} préstamos)`;
    }

    const t = sel.append("text")
      .attr("class", isComision ? "nodeTitle comision" : "nodeTitle")
      .attr("x", padX)
      .attr("y", y)
      .text(titleText);
    wrapText(t, cardW - padX*2);

    const titleBox = t.node().getBBox();
    y = titleBox.y + titleBox.height + 2;

    if (isComision){
      // Información específica de comisión
      const l0 = sel.append("text")
        .attr("class","nodeLine")
        .attr("x", padX)
        .attr("y", y + lineGap/1.2);

      l0.text("Comisión del ");
      l0.append("tspan").attr("class","nodeAmt comision")
        .text(`${d.data.pct_comision}%`);
      l0.append("tspan").text(" sobre base de ");
      l0.append("tspan").attr("class","nodeAmt comision")
        .text(`$ ${Number(d.data.base||0).toLocaleString()}`);
      y += lineGap;

      const l1 = sel.append("text")
        .attr("class","nodeLine")
        .attr("x", padX)
        .attr("y", y + lineGap/1.2)
        .style("opacity", 0);

      l1.text("Origen: ");
      l1.append("tspan").attr("class","nodeAmt comision")
        .text(d.data.origen || "");
      l1.append("tspan").text(" • Tu ganancia: ");
      l1.append("tspan").attr("class","nodeAmt comision")
        .text(`$ ${Number(d.data.valor||0).toLocaleString()}`);

      wrapText(l1, cardW - padX*2);

      l1.transition()
        .delay(260)
        .duration(400)
        .style("opacity", 1);

    } else {
      // modo normal / global
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
    }
  });

  // ... (el resto del código de dibujo se mantiene igual)

  // CHIPS ARRIBA + SELECTOR ABAJO
  renderChips(prestamista, rows);
  renderSelector(prestamista);
}

// ... (el resto del código JavaScript se mantiene igual)
</script>
</body>
</html>