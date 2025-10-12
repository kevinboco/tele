<?php
/*********************************************************
 * prestamos_visual_interactivo.php — Responsive + Zoom-to-fit
 * - Lógica original intacta (interés 10%/mes, no pagados, mark_paid)
 * - D3 con tarjetas de deudor + Nodo 3 (ganancia / total pendiente)
 * - SVG responsive (usa 100% del área visible) + auto-encuadre
 *********************************************************/
include("nav.php");

/* ===== Config ===== */
define('DB_HOST', 'mysql.hostinger.com');
define('DB_USER', 'u648222299_keboco5');
define('DB_PASS', 'Bucaramanga3011');
define('DB_NAME', 'u648222299_viajes');

function db(): mysqli { $m = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); if ($m->connect_errno) exit("Error DB: ".$m->connect_error); $m->set_charset('utf8mb4'); return $m; }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n){ return number_format((float)$n,0,',','.'); }
function mbnorm($s){ return mb_strtolower(trim((string)$s),'UTF-8'); }
function mbtitle($s){ return function_exists('mb_convert_case') ? mb_convert_case((string)$s, MB_CASE_TITLE, 'UTF-8') : ucwords(strtolower((string)$s)); }

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
      $sql = "UPDATE prestamos SET pagado=1, pagado_at=NOW() WHERE id IN ($ph) AND (pagado IS NULL OR pagado=0)";
      $st=$c->prepare($sql); $st->bind_param($types, ...$chunk); $st->execute(); $st->close();
    }
    $c->close();
    header("Location: ".$_SERVER['PHP_SELF']."?msg=pagados"); exit;
  } else {
    header("Location: ".$_SERVER['PHP_SELF']."?msg=nada"); exit;
  }
}

/* ===== Filtros ===== */
$q  = trim($_GET['q'] ?? '');
$qNorm = mbnorm($q);

$conn = db();

/* ===== Prestamistas (no pagados) ===== */
$prestMap = [];
$resPL = $conn->query("SELECT prestamista FROM prestamos WHERE (pagado IS NULL OR pagado=0)");
while($rowPL=$resPL->fetch_row()){
  $norm = mbnorm($rowPL[0]);
  if ($norm==='') continue;
  if (!isset($prestMap[$norm])) $prestMap[$norm] = $rowPL[0];
}
ksort($prestMap, SORT_NATURAL);

/* ===== Datos por Prestamista ➜ Deudor ===== */
$where = "(pagado IS NULL OR pagado=0)";
$types=""; $params=[];
if ($q !== ''){
  $where .= " AND (LOWER(deudor) LIKE CONCAT('%',?,'%') OR LOWER(prestamista) LIKE CONCAT('%',?,'%'))";
  $types .= "ss"; $params[]=$qNorm; $params[]=$qNorm;
}

$sql = "
  SELECT LOWER(TRIM(prestamista)) AS prest_key, MIN(prestamista) AS prest_display,
         LOWER(TRIM(deudor)) AS deud_key, MIN(deudor) AS deud_display,
         MIN(fecha) AS fecha_min,
         CASE WHEN CURDATE() < MIN(fecha)
              THEN 0
              ELSE TIMESTAMPDIFF(MONTH, MIN(fecha), CURDATE()) + 1
         END AS meses,
         SUM(monto) AS capital,
         SUM(monto*0.10*CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS interes,
         SUM(monto + monto*0.10*CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS total
  FROM prestamos
  WHERE $where
  GROUP BY prest_key, deud_key
  ORDER BY prest_key ASC, deud_display ASC";
$st=$conn->prepare($sql); if($types) $st->bind_param($types, ...$params); $st->execute(); $rs=$st->get_result();

/* ids por par P-D */
$sqlIds = "
  SELECT LOWER(TRIM(prestamista)) AS prest_key, LOWER(TRIM(deudor)) AS deud_key,
         GROUP_CONCAT(id) AS ids
  FROM prestamos
  WHERE $where
  GROUP BY prest_key, deud_key";
$st2=$conn->prepare($sqlIds); if($types) $st2->bind_param($types, ...$params); $st2->execute(); $rsIds=$st2->get_result();
$idsMap=[];
while($row=$rsIds->fetch_assoc()){
  $p=$row['prest_key']; $d=$row['deud_key'];
  $idsMap[$p][$d] = preg_replace('/[^0-9,]/','', (string)$row['ids']);
}
$st2->close();

/* Estructuras para la vista */
$data = [];  $ganPrest=[]; $capPendPrest=[];
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
    'ids_csv' => $idsMap[$pkey][$dkey] ?? ''
  ];

  $ganPrest[$pdisp]     = ($ganPrest[$pdisp] ?? 0) + (float)$r['interes'];
  $capPendPrest[$pdisp] = ($capPendPrest[$pdisp] ?? 0) + (float)$r['capital'];
}
$st->close();
$conn->close();

/* Selectores (checkboxes) por prestamista */
$selectors = [];
foreach($data as $prest => $rows){
  ob_start(); ?>
  <form class="selector-form" method="post" action="?action=mark_paid" data-prest="<?= h($prest) ?>" style="display:none">
    <div class="selhead">
      <div class="subtitle">Selecciona deudores de <strong><?= h(mbtitle($prest)) ?></strong> para marcarlos como pagados:</div>
      <label class="subtitle" style="display:flex;gap:8px;align-items:center">
        <input type="checkbox" onclick="(function(ch){const f=ch.closest('form');f.querySelectorAll('input[name=nodes\\[\\]]').forEach(i=>i.checked=ch.checked);})(this)"> Seleccionar todo
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
              prestado: $ <?= money($r['valor']) ?> • interés: $ <?= money($r['interes']) ?> • total: $ <?= money($r['total']) ?> • fecha: <?= h($r['fecha']) ?>
            </div>
          </div>
        </label>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;justify-content:flex-end;margin-top:8px">
      <button class="btn small" type="submit" onclick="return confirm('¿Marcar como pagados los seleccionados?')">✔ Préstamo pagado</button>
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
  :root{ --muted:#6b7280; }
  *{box-sizing:border-box}
  body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:0;background:#f4f6fa;color:#111;overflow:hidden}

  .panel{width:260px;position:fixed;left:0;top:0;bottom:0;background:#fff;border-right:1px solid #e5e7eb;padding:14px;box-shadow:2px 0 10px rgba(0,0,0,.05);overflow:auto}
  .panel h3{margin:6px 0 10px}
  .prestamista-item{padding:10px;margin-bottom:8px;border-radius:10px;background:#e3f2fd;cursor:pointer;user-select:none;font-weight:600}
  .prestamista-item:hover{background:#bbdefb}
  .prestamista-item.active{background:#90caf9}

  .topbar{position:fixed;left:260px;right:0;top:0;height:56px;display:flex;align-items:center;gap:10px;padding:10px 16px;background:transparent;z-index:5}
  .chips{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .chip{background:#eef2ff;border:1px solid #e5e7eb;border-radius:999px;padding:4px 10px;font-size:12px}

  /* El lienzo ocupa TODO el viewport que queda (sin scroll vertical del body) */
  #canvasWrap{position:fixed;left:260px;right:0;top:56px;bottom:0;overflow:hidden;background:transparent}
  svg{width:100%;height:100%}

  .link{fill:none;stroke:#cbd5e1;stroke-width:1.5px}

  /* TARJETAS */
  .nodeCard{ stroke:#cbd5e1; stroke-width:1.2px; filter: drop-shadow(0 1px 0 rgba(0,0,0,.02)); }
  .nodeCard.m1{ fill:#FFF8DB }  .nodeCard.m2{ fill:#FFE9D6 }  .nodeCard.m3{ fill:#FFE1E1 }  .nodeCard.m0{ fill:#F3F4F6 }
  .nodeTitle{ font-weight:800; fill:#111; font-size:14px }
  .nodeLine{ fill:#6b7280; font-size:13px }
  .nodeAmt{ fill:#111; font-weight:800 }

  .summaryRect{ fill:#fff; stroke:#e5e7eb; stroke-width:1.2px; }
  .summaryTitle{ font-size:13px; fill:#6b7280 }
  .summaryVal{ font-size:14px; font-weight:800; fill:#111 }

  /* Selector */
  .selector-wrap{position:fixed;left:260px;right:0;bottom:0;background:rgba(244,246,250,.85);backdrop-filter:saturate(1.2) blur(2px);padding:8px 16px;max-height:34vh;overflow:auto;border-top:1px solid #e5e7eb}
  .selector{margin-top:4px}
  .selhead{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
  .selgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:8px}
  .selitem{display:flex;gap:8px;align-items:flex-start;background:#fafbff;border:1px solid #eef2ff;border-radius:12px;padding:8px}
  .selitem .meta{font-size:12px;color:#555}
  .btn{display:inline-flex;align-items:center;gap:8px;padding:9px 12px;border-radius:12px;background:#0b5ed7;color:#fff;font-weight:700;border:0;cursor:pointer}
  .btn.small{padding:7px 10px;border-radius:10px}
</style>
</head>
<body>

<div class="panel">
  <h3>Prestamistas</h3>
  <div id="prestamistas-list"></div>
</div>

<div class="topbar">
  <?php if ($msg): ?>
    <span class="chip"><?= $msg==='pagados' ? 'Marcados como pagados.' : ($msg==='nada' ? 'No seleccionaste deudores.' : 'Operación realizada.') ?></span>
  <?php endif; ?>
  <div class="chips" id="chips"></div>
</div>

<div id="canvasWrap">
  <svg id="chart" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid meet"></svg>
</div>

<div class="selector-wrap"><div id="selector-host"></div></div>

<script>
/* ===== Datos desde PHP ===== */
const DATA = <?php echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK); ?>;
const GANANCIA = <?php echo json_encode($ganPrest, JSON_NUMERIC_CHECK); ?>;
const CAPITAL  = <?php echo json_encode($capPendPrest, JSON_NUMERIC_CHECK); ?>;
const SELECTORS_HTML = <?php echo json_encode($selectors, JSON_UNESCAPED_UNICODE); ?>;

/* ===== D3 Setup (zoom/pan + auto-fit) ===== */
const svg = d3.select("#chart");
const g   = svg.append("g");      // capa pan/zoom
const linksLayer = g.append("g");
const nodesLayer = g.append("g");
const linesToSum = g.append("g");
const summaryLayer = g.append("g");

const zoom = d3.zoom()
  .scaleExtent([0.5, 3])
  .on("zoom", (ev) => g.attr("transform", ev.transform));
svg.call(zoom).on("dblclick.zoom", null); // sin zoom con doble click

function getCanvasSize(){
  const wrap = document.getElementById("canvasWrap");
  return { w: wrap.clientWidth, h: wrap.clientHeight };
}
function fitToContent(pad=20){
  const box = g.node().getBBox();
  const {w, h} = getCanvasSize();
  if (box.width === 0 || box.height === 0) return;
  const scale = Math.min((w - pad*2)/box.width, (h - pad*2)/box.height);
  const tx = (w - box.width*scale)/2 - box.x*scale;
  const ty = (h - box.height*scale)/2 - box.y*scale;
  svg.transition().duration(500).call(zoom.transform, d3.zoomIdentity.translate(tx,ty).scale(scale));
}

/* ===== UI chips y selector ===== */
const chipsHost = document.getElementById("chips");
const selectorHost = document.getElementById("selector-host");
function renderChips(prest){
  chipsHost.innerHTML = "";
  const mk = (t,bg)=>{ const s=document.createElement("span"); s.className="chip"; s.textContent=t; if(bg) s.style.background=bg; return s; };
  chipsHost.append(
    mk(`Ganancia (interés): $ ${Number(GANANCIA[prest]||0).toLocaleString()}`),
    mk(`Total prestado (pend.): $ ${Number(CAPITAL[prest]||0).toLocaleString()}`),
    mk("1 mes","#FFF8DB"), mk("2 meses","#FFE9D6"), mk("3+ meses","#FFE1E1")
  );
}
function renderSelector(prest){
  selectorHost.innerHTML = "";
  const wrap = document.createElement("div"); wrap.className="selector";
  wrap.innerHTML = SELECTORS_HTML[prest] || '<div class="chip">Sin deudores pendientes</div>';
  selectorHost.appendChild(wrap);
  const form = selectorHost.querySelector(".selector-form");
  if (form) form.style.display = "block";
}

/* ===== Lista de prestamistas panel izquierdo ===== */
const prestamistasList = d3.select("#prestamistas-list");
const prestNombres = Object.keys(DATA);
prestNombres.forEach((p,i) => {
  prestamistasList.append("div")
    .attr("class", "prestamista-item" + (i===0 ? " active":""))
    .text(p)
    .on("click", function(){
      d3.selectAll(".prestamista-item").classed("active", false);
      d3.select(this).classed("active", true);
      drawTree(p);
    });
});

/* ===== Dibujo ===== */
function drawTree(prestamista){
  linksLayer.selectAll("*").remove();
  nodesLayer.selectAll("*").remove();
  linesToSum.selectAll("*").remove();
  summaryLayer.selectAll("*").remove();

  const rows = DATA[prestamista] || [];
  const root = d3.hierarchy({ name: prestamista, children: rows });

  // Geometría de tarjetas
  const cardW = 360, cardH = 96, vGap = 18, hGap = 340;

  // Distribución: evita montajes usando nodeSize
  const treeLayout = d3.tree().nodeSize([cardH + vGap, hGap]);
  treeLayout(root);   // pos: d.x vertical, d.y horizontal

  // Enlaces root -> deudores
  linksLayer.selectAll("path")
    .data(root.links())
    .join("path")
      .attr("class","link")
      .attr("d", d3.linkHorizontal().x(d => d.y).y(d => d.x));

  // Nodos — Prestamista
  const rootNode = nodesLayer.append("g").attr("transform",`translate(${root.y},${root.x})`);
  rootNode.append("circle").attr("r",8).attr("fill","#1976d2").attr("stroke","#fff").attr("stroke-width",2);
  rootNode.append("text").attr("dy","0.31em").attr("x",-14).attr("text-anchor","end").text(root.data.name);

  // Nodos — Deudores (tarjetas)
  const deudores = nodesLayer.selectAll(".n")
    .data(root.descendants().filter(d => d.depth===1))
    .join("g")
      .attr("class","n")
      .attr("transform", d => `translate(${d.y},${d.x})`);

  const padX = 12;
  const line1 = 22, line2 = 40, line3 = 58, line4 = 76;

  deudores.each(function(d){
    const sel = d3.select(this);
    const m = +d.data.meses || 0;
    const mcls = (m >= 3) ? "m3" : (m === 2 ? "m2" : (m === 1 ? "m1" : "m0"));
    sel.append("rect").attr("class",`nodeCard ${mcls}`)
      .attr("x",0).attr("y",-cardH/2).attr("width",cardW).attr("height",cardH).attr("rx",12).attr("ry",12);
    sel.append("text").attr("class","nodeTitle").attr("x",padX).attr("y",-cardH/2+line1).text(d.data.nombre);
    sel.append("text").attr("class","nodeLine").attr("x",padX).attr("y",-cardH/2+line2)
      .text("valor prestado: ").append("tspan").attr("class","nodeAmt").text(`$ ${Number(d.data.valor||0).toLocaleString()}`);
    sel.append("text").attr("class","nodeLine").attr("x",padX).attr("y",-cardH/2+line3)
      .text("fecha: ").append("tspan").attr("class","nodeAmt").text(d.data.fecha||"");
    const ln = sel.append("text").attr("class","nodeLine").attr("x",padX).attr("y",-cardH/2+line4).text("interés: ");
    ln.append("tspan").attr("class","nodeAmt").text(`$ ${Number(d.data.interes||0).toLocaleString()}`);
    ln.append("tspan").text(" • total ");
    ln.append("tspan").attr("class","nodeAmt").text(`$ ${Number(d.data.total||0).toLocaleString()}`);
  });

  // Nodo 3 (resumen) – posición a la derecha del último deudor
  const lastY = d3.max(root.descendants(), d => d.y) + cardW + 120;
  const minX  = d3.min(root.descendants(), d => d.x);
  const maxX  = d3.max(root.descendants(), d => d.x);
  const centerY = (minX + maxX) / 2;

  // Conexiones de cada tarjeta -> nodo resumen
  linesToSum.selectAll("line")
    .data(root.descendants().filter(d => d.depth===1))
    .join("line")
      .attr("x1", d => d.y + cardW)
      .attr("y1", d => d.x)
      .attr("x2", lastY)
      .attr("y2", centerY)
      .attr("stroke", "#cbd5e1")
      .attr("stroke-width", 1.2);

  const sumW=240, sumH=48, pad=12;
  // Ganancia
  summaryLayer.append("rect").attr("class","summaryRect")
    .attr("x", lastY).attr("y", centerY - sumH - 6).attr("width",sumW).attr("height",sumH).attr("rx",12).attr("ry",12);
  summaryLayer.append("text").attr("class","summaryTitle").attr("x",lastY+pad).attr("y",centerY - sumH + 16).text("Ganancia (interés)");
  summaryLayer.append("text").attr("class","summaryVal").attr("x",lastY+pad).attr("y",centerY - sumH + 34).text(`$ ${Number(GANANCIA[prestamista]||0).toLocaleString()}`);
  // Total prestado
  summaryLayer.append("rect").attr("class","summaryRect")
    .attr("x", lastY).attr("y", centerY + 6).attr("width",sumW).attr("height",sumH).attr("rx",12).attr("ry",12);
  summaryLayer.append("text").attr("class","summaryTitle").attr("x",lastY+pad).attr("y",centerY + 6 + 16).text("Total prestado (pend.)");
  summaryLayer.append("text").attr("class","summaryVal").attr("x",lastY+pad).attr("y",centerY + 6 + 34).text(`$ ${Number(CAPITAL[prestamista]||0).toLocaleString()}`);

  // Chips + selector
  renderChips(prestamista);
  renderSelector(prestamista);

  // Auto-encuadre para ver TODO sin scroll
  fitToContent(20);
}

/* Inicio + resize */
if (prestNombres.length){ drawTree(prestNombres[0]); }
let resizeTO=null;
window.addEventListener("resize", () => { clearTimeout(resizeTO); resizeTO=setTimeout(()=>fitToContent(20), 150); });
</script>
</body>
</html>
