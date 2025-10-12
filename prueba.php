<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}

// Obtener todos los préstamos
$sql = "SELECT prestamista, deudor, monto, fecha, interes FROM prestamos";
$result = $conn->query($sql);

$prestamistas = [];
$deudas = [];

while ($row = $result->fetch_assoc()) {
    $prestamista = $row['prestamista'];
    $deudor = $row['deudor'];
    $monto = $row['monto'];
    $fecha = $row['fecha'];
    $interes = $row['interes'];

    if (!isset($prestamistas[$prestamista])) {
        $prestamistas[$prestamista] = [];
    }

    $prestamistas[$prestamista][] = [
        'deudor' => $deudor,
        'monto' => $monto,
        'fecha' => $fecha,
        'interes' => $interes
    ];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Préstamos Interactivos</title>
<script src="https://d3js.org/d3.v7.min.js"></script>
<style>
body {
  font-family: 'Poppins', sans-serif;
  background: #f5f6fa;
  margin: 0;
  overflow: hidden;
}
#chart {
  width: 100vw;
  height: 90vh;
}
.node circle {
  cursor: pointer;
  stroke: #333;
  stroke-width: 1.5px;
}
.node text {
  font-size: 14px;
  pointer-events: none;
}
.tooltip {
  position: absolute;
  background: rgba(0,0,0,0.8);
  color: #fff;
  padding: 6px 10px;
  border-radius: 8px;
  font-size: 13px;
  pointer-events: none;
  opacity: 0;
  transition: opacity 0.2s;
}
</style>
</head>
<body>

<h2 style="text-align:center;margin-top:15px;">💰 Préstamos Interactivos</h2>
<div id="chart"></div>
<div class="tooltip" id="tooltip"></div>

<script>
// === Datos desde PHP ===
const data = <?php echo json_encode($prestamistas, JSON_UNESCAPED_UNICODE); ?>;

// === Inicializar SVG ===
const width = window.innerWidth, height = window.innerHeight * 0.85;
const svg = d3.select("#chart").append("svg")
    .attr("width", width)
    .attr("height", height);

const tooltip = d3.select("#tooltip");

let nodes = [];
let links = [];

// Crear nodos base (prestamistas)
Object.keys(data).forEach((prestamista, i) => {
  nodes.push({
    id: prestamista,
    type: "prestamista",
    expanded: false,
    x: -200, // fuera de pantalla para animación
    y: height / 2 + (i * 60 - Object.keys(data).length * 30)
  });
});

// === Simulación D3 ===
const simulation = d3.forceSimulation(nodes)
  .force("link", d3.forceLink().id(d => d.id).distance(120))
  .force("charge", d3.forceManyBody().strength(-400))
  .force("center", d3.forceCenter(width / 2, height / 2))
  .on("tick", ticked);

// === Dibujar elementos ===
const link = svg.append("g").attr("stroke", "#999").selectAll("line");
const node = svg.append("g").selectAll(".node");

// === Render ===
function update() {
  const uLinks = link.data(links);
  uLinks.join(
    enter => enter.append("line").attr("stroke-width", 1.5),
    update => update,
    exit => exit.remove()
  );

  const uNodes = node.data(nodes, d => d.id);
  const nodeEnter = uNodes.enter().append("g").attr("class", "node")
    .on("click", clicked)
    .on("mouseover", showTooltip)
    .on("mouseout", hideTooltip);

  nodeEnter.append("circle")
    .attr("r", d => d.type === "prestamista" ? 25 : 15)
    .attr("fill", d => d.type === "prestamista" ? "#007bff" : "#00b894")
    .attr("opacity", 0)
    .transition()
    .duration(800)
    .attr("opacity", 1)
    .attr("cx", 0).attr("cy", 0);

  nodeEnter.append("text")
    .attr("dy", 4)
    .attr("x", d => d.type === "prestamista" ? 35 : 20)
    .text(d => d.id);

  uNodes.exit().remove();
  node.merge(nodeEnter);
  simulation.nodes(nodes);
  simulation.force("link").links(links);
  simulation.alpha(1).restart();
}

function ticked() {
  svg.selectAll("line")
    .attr("x1", d => d.source.x)
    .attr("y1", d => d.source.y)
    .attr("x2", d => d.target.x)
    .attr("y2", d => d.target.y);

  svg.selectAll(".node")
    .attr("transform", d => `translate(${d.x},${d.y})`);
}

// === Acciones ===
function clicked(event, d) {
  if (d.type !== "prestamista") return;

  if (d.expanded) {
    // Colapsar
    nodes = nodes.filter(n => n.type === "prestamista" || n.parent !== d.id);
    links = links.filter(l => l.source.id !== d.id);
    d.expanded = false;
  } else {
    // Expandir
    const deudores = data[d.id];
    deudores.forEach(dd => {
      const nodoHijo = { id: dd.deudor, type: "deudor", parent: d.id, monto: dd.monto, fecha: dd.fecha, interes: dd.interes };
      nodes.push(nodoHijo);
      links.push({ source: d.id, target: dd.deudor });
    });
    d.expanded = true;
  }
  update();
}

function showTooltip(event, d) {
  if (d.type === "deudor") {
    tooltip.style("opacity", 1)
      .html(`
        <strong>${d.id}</strong><br>
        💵 Monto: ${d.monto}<br>
        📅 Fecha: ${d.fecha}<br>
        💰 Interés: ${d.interes}%
      `)
      .style("left", (event.pageX + 10) + "px")
      .style("top", (event.pageY - 40) + "px");
  }
}
function hideTooltip() {
  tooltip.style("opacity", 0);
}

// Animación de entrada desde la izquierda
svg.selectAll("circle")
  .transition()
  .delay((d, i) => i * 200)
  .duration(1000)
  .attr("cx", (d, i) => width / 2)
  .ease(d3.easeBounceOut);

update();
</script>

</body>
</html>
