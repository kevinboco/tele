<?php
/*********************************************************
 * prestamos_visual_interactivo.php — Visual D3 con tarjetas
 * - Mantiene la lógica original (cálculos, no pagados, mark_paid)
 * - UI: panel de prestamistas + árbol D3
 * - Deudores como TARJETA con: nombre, valor, fecha, interés y total
 * - Colores por meses (1, 2, 3+)
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
  $nodes = $_POST['nodes'] ?? []; // array de CSVs de ids
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
$data = [];         // D3: $data[prest_display] = [ {nombre, valor, fecha, interes, total, meses, ids_csv}, ... ]
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

  svg{margin-left:280px}
  .link{fill:none;stroke:#cbd5e1;stroke-width:1.5px}

  /* ===== TARJETAS DE DEUDORES ===== */
  .nodeCard { stroke:#cbd5e1; stroke-width:1.2px; filter: drop-shadow(0 1px 0 rgba(0,0,0,.02)); }
  .nodeCard.m1 { fill:#FFF8DB; } /* 1 mes - amarillo suave */
  .nodeCard.m2 { fill:#FFE9D6; } /* 2 meses - naranja suave */
  .nodeCard.m3 { fill:#FFE1E1; } /* 3+ meses - rojo suave */
  .nodeCard.m0 { fill:#F3F4F6; } /* 0 meses (futuro) */

  .nodeTitle { font-weight:800; fill:#111; font-size:14px }
  .nodeLine  { fill:#6b7280; font-size:13px }
  .nodeAmt   { fill:#111; font-weight:800 }

  /* Selector / acciones */
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

<svg id="chart" width="1300" height="800"></svg>
<div class="selector-wrap"><div id="selector-host"></div></div>

<script>
/* ===== Datos desde PHP ===== */
const DATA = <?php echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK); ?>;
const GANANCIA = <?php echo json_encode($ganPrest, JSON_NUMERIC_CHECK); ?>;
const CAPITAL  = <?php echo json_encode($capPendPrest, JSON_NUMERIC_CHECK); ?>;
const SELECTORS_HTML = <?php echo json_encode($selectors, JSON_UNESCAPED_UNICODE); ?>;

/* ===== D3 Setup ===== */
const svg = d3.select("#chart");
const width = +svg.attr("width");
const height = +svg.attr("height");
const g = svg.append("g").attr("transform", "translate(100,50)");

const chipsHost = document.getElementById("chips");
const selectorHost = document.getElementById("selector-host");

/* ===== Panel prestamistas ===== */
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

/* ===== Dibujo del árbol (con tarjetas) ===== */
function drawTree(prestamista) {
  g.selectAll("*").remove();

  const rows = DATA[prestamista] || [];
  const root = d3.hierarchy({ name: prestamista, children: rows });
  const treeLayout = d3.tree().size([height - 120, width - 420]);
  treeLayout(root);

  // Enlaces
  g.selectAll(".link")
    .data(root.links())
    .join("path")
      .attr("class", "link")
      .attr("d", d3.linkHorizontal()
        .x(d => root.y)
        .y(d => root.x))
      .attr("stroke-opacity", 0)
    .transition()
      .duration(700)
      .attr("stroke-opacity", 1)
      .attr("d", d3.linkHorizontal()
        .x(d => d.y)
        .y(d => d.x));

  // Parámetros de tarjeta
  const cardW = 360, cardH = 96, padX = 12;
  const line1 = 22, line2 = 40, line3 = 58, line4 = 76;

  // Nodos
  const node = g.selectAll(".node")
    .data(root.descendants())
    .join("g")
      .attr("class", "node")
      .attr("transform", d => `translate(${root.y},${d.x})`)
    .transition()
      .duration(800)
      .attr("transform", d => `translate(${d.y},${d.x})`)
    .selection();

  node.each(function(d){
    const sel = d3.select(this);

    if (d.depth === 0) {
      // Prestamista raíz (círculo + texto)
      sel.append("circle")
        .attr("r", 8)
        .attr("fill", "#1976d2")
        .attr("stroke", "#fff")
        .attr("stroke-width", 2);
      sel.append("text")
        .attr("dy", "0.31em")
        .attr("x", -14)
        .attr("text-anchor", "end")
        .text(d.data.name);
      return;
    }

    // Deudor: tarjeta
    const m = +d.data.meses || 0;
    const mcls = (m >= 3) ? "m3" : (m === 2 ? "m2" : (m === 1 ? "m1" : "m0"));

    sel.append("rect")
      .attr("class", `nodeCard ${mcls}`)
      .attr("x", 0)
      .attr("y", -cardH/2)
      .attr("width", cardW)
      .attr("height", cardH)
      .attr("rx", 12).attr("ry", 12);

    sel.append("text")
      .attr("class", "nodeTitle")
      .attr("x", padX)
      .attr("y", -cardH/2 + line1)
      .text(d.data.nombre);

    sel.append("text")
      .attr("class", "nodeLine")
      .attr("x", padX)
      .attr("y", -cardH/2 + line2)
      .text("valor prestado: ")
      .append("tspan")
        .attr("class","nodeAmt")
        .text(() => `\$ ${Number(d.data.valor||0).toLocaleString()}`);

    sel.append("text")
      .attr("class", "nodeLine")
      .attr("x", padX)
      .attr("y", -cardH/2 + line3)
      .text("fecha: ")
      .append("tspan")
        .attr("class","nodeAmt")
        .text(d.data.fecha || "");

    const lineInt = sel.append("text")
      .attr("class", "nodeLine")
      .attr("x", padX)
      .attr("y", -cardH/2 + line4)
      .text("interés: ");
    lineInt.append("tspan").attr("class","nodeAmt")
      .text(() => `\$ ${Number(d.data.interes||0).toLocaleString()}`);
    lineInt.append("tspan").text(" • total ");
    lineInt.append("tspan").attr("class","nodeAmt")
      .text(() => `\$ ${Number(d.data.total||0).toLocaleString()}`);
  });

  renderChips(prestamista);
  renderSelector(prestamista);
}

/* Inicio: selecciona el primero */
if (prestNombres.length){ drawTree(prestNombres[0]); }
</script>
</body>
</html>
