<?php
/*********************************************************
 * prestamos_visual_interactivo.php
 * v3.4: deudores centrados + Nodo 3 (Resumen) + animaciones
 *       + Auto-fit vertical (sin scroll)
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
$data = [];
$ganPrest=[]; $capPendPrest=[];
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
  :root{ --panel:#fff; --line:#d1d5db; --muted:#6b7280; --primary:#1976d2; }
  *{box-sizing:border-box}
  body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:0;background:#f4f6fa;color:#111;overflow-x:hidden}
  .panel{width:260px;position:fixed;left:0;top:0;bottom:0;background:#fff;border-right:1px solid #e5e7eb;padding:14px 14px 10px;box-shadow:2px 0 10px rgba(0,0,0,.05);overflow:auto}
  .panel h3{margin:6px 0 10px}
  .prestamista-item{padding:10px;margin-bottom:8px;border-radius:10px;background:#e3f2fd;cursor:pointer;user-select:none;font-weight:600}
  .prestamista-item:hover{background:#bbdefb}
  .prestamista-item.active{background:#90caf9}
  .topbar{margin-left:260px;padding:10px 16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .msg{background:#e8f7ee;color:#196a3b;padding:8px 12px;border-radius:10px;display:inline-flex;align-items:center;gap:8px}
  .chips{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .chip{background:#eef2ff;border:1px solid #e5e7eb;border-radius:999px;padding:4px 10px;font-size:12px}

  /* Más ancho para tener nodo 3 y espacio de curvas */
  svg{margin-left:280px; display:block}
  .link{fill:none;stroke:#cbd5e1;stroke-width:1.5px}
  .link2{fill:none;stroke:#9ca3af;stroke-width:1.4px;opacity:.9}

  /* ===== TARJETAS ===== */
  .nodeCard { stroke:#cbd5e1; stroke-width:1.2px; filter: drop-shadow(0 1px 0 rgba(0,0,0,.02)); }
  .nodeCard.m1 { fill:#FFF8DB; }
  .nodeCard.m2 { fill:#FFE9D6; }
  .nodeCard.m3 { fill:#FFE1E1; }
  .nodeCard.m0 { fill:#F3F4F6; }

  .nodeTitle { font-weight:800; fill:#111; font-size:13px }
  .nodeLine  { fill:#6b7280; font-size:12px }
  .nodeAmt   { fill:#111; font-weight:800 }

  /* Resumen (Nodo 3) */
  .summaryCard { fill:#EAF5FF; stroke:#cfe8ff; stroke-width:1.2px; }
  .summaryTitle { font-weight:800; fill:#0b5ed7; font-size:14px }
  .summaryLine  { fill:#374151; font-size:13px }
  .summaryAmt   { fill:#0b5ed7; font-weight:800 }

  .selector-wrap{margin-left:280px;padding:0 16px 20px}
  .selector{margin-top:10px;border-top:1px dashed #e5e7eb;padding-top:10px}
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
    <div class="msg">
      <?php
        echo ($msg==='pagados') ? 'Marcados como pagados.' :
             (($msg==='nada') ? 'No seleccionaste deudores.' : 'Operación realizada.');
      ?>
    </div>
  <?php endif; ?>
  <div class="chips" id="chips"></div>
</div>

<!-- Más ancho base para nodo 3 y curvas -->
<svg id="chart" width="1600" height="800"></svg>
<div class="selector-wrap"><div id="selector-host"></div></div>

<script>
/* ===== Datos desde PHP ===== */
const DATA = <?php echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK); ?>;
const GANANCIA = <?php echo json_encode($ganPrest, JSON_NUMERIC_CHECK); ?>;
const CAPITAL  = <?php echo json_encode($capPendPrest, JSON_NUMERIC_CHECK); ?>;
const SELECTORS_HTML = <?php echo json_encode($selectors, JSON_UNESCAPED_UNICODE); ?>;

/* ===== D3 Setup ===== */
const svg = d3.select("#chart");
const rootG = svg.append("g").attr("transform", "translate(120,50)");
const scene = rootG.append("g").attr("class","scene"); // << grupo que escalamos
const chipsHost = document.getElementById("chips");
const selectorHost = document.getElementById("selector-host");

/* ===== Panel prestamistas ===== */
const prestamistasList = d3.select("#prestamistas-list");
const prestNombres = Object.keys(DATA);
let currentPrest = prestNombres[0] || null;

prestNombres.forEach((p,i) => {
  prestamistasList.append("div")
    .attr("class", "prestamista-item" + (i===0 ? " active":""))
    .text(p)
    .on("click", function(){
      d3.selectAll(".prestamista-item").classed("active", false);
      d3.select(this).classed("active", true);
      currentPrest = p;
      drawTree(p);
    });
});

/* ===== Chips ===== */
function renderChips(prest){
  chipsHost.innerHTML = "";
  const chip1 = document.createElement("span");
  chip1.className = "chip"; chip1.textContent = `Ganancia (interés): $ ${Number(GANANCIA[prest]||0).toLocaleString()}`;
  const chip2 = document.createElement("span");
  chip2.className = "chip"; chip2.textContent = `Total prestado (pend.): $ ${Number(CAPITAL[prest]||0).toLocaleString()}`;
  const chipL1 = document.createElement("span"); chipL1.className="chip"; chipL1.textContent="1 mes"; chipL1.style.background="#FFF8DB";
  const chipL2 = document.createElement("span"); chipL2.className="chip"; chipL2.textContent="2 meses"; chipL2.style.background="#FFE9D6";
  const chipL3 = document.createElement("span"); chipL3.className="chip"; chipL3.textContent="3+ meses"; chipL3.style.background="#FFE1E1";
  chipsHost.append(chip1, chip2, chipL1, chipL2, chipL3);
}

/* ===== Selector ===== */
function renderSelector(prest){
  selectorHost.innerHTML = "";
  const wrap = document.createElement("div");
  wrap.className = "selector";
  wrap.innerHTML = SELECTORS_HTML[prest] || '<div class="chip">Sin deudores pendientes</div>';
  selectorHost.appendChild(wrap);
  const form = selectorHost.querySelector(".selector-form");
  if (form) form.style.display = "block";
}

/* ===== Helper: wrap de texto en SVG ===== */
function wrapText(textSel, width){
  textSel.each(function(){
    const text = d3.select(this);
    const words = text.text().split(/\s+/).filter(Boolean);
    let line = [];
    let tspan = text.text(null).append("tspan").attr("x", text.attr("x")).attr("dy", 0);
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

/* ===== Fit-to-viewport (escala vertical automática) ===== */
function fitToViewport(animated=true){
  // reset antes de medir
  scene.attr("transform", "translate(0,0) scale(1)");
  const svgH = +svg.attr("height");
  const bbox = scene.node().getBBox();
  // margen vertical interno
  const padTop = 10, padBot = 20;
  const available = svgH - (padTop + padBot);
  const k = Math.min(1, available / bbox.height); // <=1 para reducir si no cabe
  const offsetY = Math.max(padTop, (svgH - bbox.height * k) / 2);

  const t = `translate(0,${offsetY}) scale(${k})`;
  if (animated){
    scene.transition()
      .duration(500)
      .ease(d3.easeCubicOut)
      .attr("transform", t);
  } else {
    scene.attr("transform", t);
  }
}

/* ===== Dibujo del árbol con Nodo 3 ===== */
function drawTree(prestamista) {
  scene.selectAll("*").remove();

  const rows = DATA[prestamista] || [];

  // Parámetros de tarjeta
  const cardW = 400;
  const padX  = 12;
  const padY  = 10;
  const lineGap = 18;

  // Alto base del SVG (puedes variarlo sin romper el fit)
  const approxCardH = padY*2 + lineGap*4 + 6;
  const svgH = +svg.attr("height");
  // Layout base (luego recentramos manualmente los hijos)
  const treeLayout = d3.tree()
    .nodeSize([ approxCardH + 30, cardW + 240 ])
    .separation((a,b)=> (a.parent===b.parent? 1.3 : 1.6));

  const root = d3.hierarchy({ name: prestamista, children: rows });
  treeLayout.size([svgH - 140, 1]);
  treeLayout(root);

  // === Recentrar deudores al centro visible ===
  const svgW = +svg.attr("width");
  const leftMargin = 120;              // translate de rootG
  const usableW = svgW - leftMargin - 40;
  const centerX = Math.max(cardW/2 + 40, (usableW - cardW) / 2);
  root.each(d => { if (d.depth === 0) d.y = 0; if (d.depth === 1) d.y = centerX; });

  // ===== Enlaces raíz -> deudores con animación de trazo =====
  const linkPath = d3.linkHorizontal().x(d=>d.y).y(d=>d.x);
  const links = scene.selectAll(".link")
    .data(root.links())
    .join("path")
      .attr("class", "link")
      .attr("d", linkPath)
      .attr("stroke-dasharray", function(){ return this.getTotalLength(); })
      .attr("stroke-dashoffset", function(){ return this.getTotalLength(); });

  links.transition()
      .delay((d,i)=> 120 + i*30)
      .duration(700)
      .ease(d3.easeCubicOut)
      .attr("stroke-dashoffset", 0);

  // ===== Nodos (slide-in) =====
  const nodes = scene.selectAll(".node")
    .data(root.descendants())
    .join("g")
      .attr("class", "node")
      .attr("transform", d => `translate(${d.y - 180},${d.x})`)
      .style("opacity", 0);

  nodes.transition()
      .delay((d,i)=> d.depth===0 ? 0 : 150 + i*40)
      .duration(600)
      .ease(d3.easeCubicOut)
      .attr("transform", d => `translate(${d.y},${d.x})`)
      .style("opacity", 1);

  // ----- Contenido de los nodos -----
  nodes.each(function(d){
    const sel = d3.select(this);

    if (d.depth === 0) {
      sel.append("circle").attr("r", 8).attr("fill", "#1976d2").attr("stroke","#fff").attr("stroke-width",2);
      sel.append("text").attr("dy","0.31em").attr("x",-14).attr("text-anchor","end").text(d.data.name);
      return;
    }

    // Medición previa del título envuelto para altura dinámica
    const temp = sel.append("text").attr("class","nodeTitle").attr("x", padX).attr("y", 0).style("opacity",0).text(d.data.nombre);
    wrapText(temp, cardW - padX*2);
    const titleRows = temp.selectAll("tspan").nodes().length || 1;
    temp.remove();

    const rowsCount = titleRows + 3;
    const cardH = padY*2 + lineGap*rowsCount;

    const m = +d.data.meses || 0;
    const mcls = (m >= 3) ? "m3" : (m === 2 ? "m2" : (m === 1 ? "m1" : "m0"));

    // Fondo tarjeta
    sel.append("rect")
      .attr("class", `nodeCard ${mcls}`)
      .attr("x", 0)
      .attr("y", -cardH/2)
      .attr("width", cardW)
      .attr("height", cardH)
      .attr("rx", 12).attr("ry", 12)
      .attr("transform", "scale(0.98)")
      .transition()
        .delay(250)
        .duration(400)
        .ease(d3.easeCubicOut)
        .attr("transform", "scale(1)");

    // Título
    let y = -cardH/2 + padY + 12;
    const t = sel.append("text").attr("class","nodeTitle").attr("x", padX).attr("y", y).text(d.data.nombre);
    wrapText(t, cardW - padX*2);
    const titleBox = t.node().getBBox();
    y = titleBox.y + titleBox.height + 4;

    // Resto de líneas
    const meta = [
      {label:"valor prestado: ", value:`$ ${Number(d.data.valor||0).toLocaleString()}`},
      {label:"fecha: ", value: d.data.fecha || ""},
      {label:"interés: ", value:`$ ${Number(d.data.interes||0).toLocaleString()} • total $ ${Number(d.data.total||0).toLocaleString()}`}
    ];
    meta.forEach((ln,i)=>{
      const line = sel.append("text").attr("class","nodeLine").attr("x", padX).attr("y", y + i*lineGap)
        .style("opacity", 0)
        .text(ln.label);
      line.append("tspan").attr("class","nodeAmt").text(ln.value);
      line.transition()
        .delay(220 + i*90)
        .duration(400)
        .style("opacity", 1);
    });
  });

  // ====== NODO 3 (RESUMEN) ======
  const totalInteres = Number(<?php echo json_encode($ganPrest); ?>[prestamista] || 0);
  const totalCapital = Number(<?php echo json_encode($capPendPrest); ?>[prestamista] || 0);

  const deudores = root.descendants().filter(d=>d.depth===1);
  const midY = d3.mean(deudores, d=>d.x) || 0;
  const summaryX = centerX + cardW + 320;      // a la derecha de las tarjetas

  const sumW = 340, sumH = 130, sumPadX = 14, sumLine = 24;
  const summaryG = scene.append("g")
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
    .attr("x", 0).attr("y", -sumH/2)
    .attr("width", sumW).attr("height", sumH)
    .attr("rx", 14).attr("ry", 14);

  summaryG.append("text")
    .attr("class","summaryTitle")
    .attr("x", sumPadX).attr("y", -sumH/2 + sumLine)
    .text("Resumen del prestamista");

  let sy = -sumH/2 + sumLine + 20;
  const s1 = summaryG.append("text").attr("class","summaryLine").attr("x", sumPadX).attr("y", sy).text("Ganancia (interés): ");
  s1.append("tspan").attr("class","summaryAmt").text(`$ ${totalInteres.toLocaleString()}`);
  sy += 22;
  const s2 = summaryG.append("text").attr("class","summaryLine").attr("x", sumPadX).attr("y", sy).text("Total prestado (pend.): ");
  s2.append("tspan").attr("class","summaryAmt").text(`$ ${totalCapital.toLocaleString()}`);

  // Líneas Deudores -> Resumen
  const link2 = d3.linkHorizontal().x(d=>d.y).y(d=>d.x);
  const links2 = scene.selectAll(".link2")
    .data(deudores.map(d => ({
      source: { x: d.x, y: d.y + cardW },
      target: { x: midY, y: summaryX }
    })))
    .join("path")
      .attr("class","link2")
      .attr("d", link2)
      .attr("stroke-dasharray", function(){ return this.getTotalLength(); })
      .attr("stroke-dashoffset", function(){ return this.getTotalLength(); });

  links2.transition()
      .delay((d,i)=> 200 + i*25)
      .duration(650)
      .ease(d3.easeCubicOut)
      .attr("stroke-dashoffset", 0);

  renderChips(prestamista);
  renderSelector(prestamista);

  // === Ajuste a viewport (sin scroll) ===
  fitToViewport(true);
}

/* Redibujar en resize para mantener el fit */
window.addEventListener('resize', () => {
  // opcional: mantener mismo alto; si quieres, podrías adaptar svg.height aquí
  if (currentPrest) drawTree(currentPrest);
});

/* Inicio: selecciona el primero */
if (currentPrest){ drawTree(currentPrest); }
</script>
</body>
</html>
