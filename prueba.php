<?php
// Datos de ejemplo (puedes cambiarlos por una consulta SQL luego)
$data = [
    "Alexander Peralta" => [
        ["nombre" => "Juan Pérez", "valor" => 300000, "fecha" => "2025-09-01", "interes" => 50000],
        ["nombre" => "María López", "valor" => 450000, "fecha" => "2025-09-05", "interes" => 60000],
        ["nombre" => "Carlos Gómez", "valor" => 700000, "fecha" => "2025-08-20", "interes" => 90000]
    ],
    "Camila Díaz" => [
        ["nombre" => "Ana Rojas", "valor" => 200000, "fecha" => "2025-09-15", "interes" => 30000],
        ["nombre" => "Luis Herrera", "valor" => 150000, "fecha" => "2025-08-25", "interes" => 20000]
    ],
    "Laura Torres" => [
        ["nombre" => "José Pérez", "valor" => 1000000, "fecha" => "2025-10-01", "interes" => 150000]
    ]
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Préstamos Interactivos</title>
<script src="https://d3js.org/d3.v7.min.js"></script>
<style>
body {
  font-family: "Segoe UI", sans-serif;
  background: #f4f6fa;
  margin: 0;
  overflow-x: hidden;
}
.panel {
  width: 260px;
  background: white;
  position: fixed;
  left: 0;
  top: 0;
  bottom: 0;
  border-right: 1px solid #ddd;
  padding: 15px;
  overflow-y: auto;
  box-shadow: 2px 0 10px rgba(0,0,0,0.05);
}
.prestamista-item {
  padding: 10px;
  margin-bottom: 8px;
  border-radius: 6px;
  background: #e3f2fd;
  cursor: pointer;
  transition: all 0.2s;
}
.prestamista-item:hover {
  background: #bbdefb;
}
svg {
  margin-left: 280px;
}
.link {
  fill: none;
  stroke: #ccc;
  stroke-width: 2px;
}
.node circle {
  fill: #1976d2;
  stroke: #fff;
  stroke-width: 2px;
}
.node text {
  font-size: 14px;
  fill: #333;
}
.tooltip {
  position: absolute;
  background: rgba(0,0,0,0.75);
  color: #fff;
  padding: 6px 10px;
  border-radius: 6px;
  font-size: 13px;
  pointer-events: none;
}
</style>
</head>
<body>

<div class="panel">
  <h3>Prestamistas</h3>
  <div id="prestamistas-list"></div>
</div>

<svg id="chart" width="1300" height="800"></svg>

<div id="tooltip" class="tooltip" style="display:none;"></div>

<script>
const data = <?php echo json_encode($data, JSON_PRETTY_PRINT); ?>;
const svg = d3.select("#chart");
const width = +svg.attr("width");
const height = +svg.attr("height");
const g = svg.append("g").attr("transform", "translate(100,50)");

const tooltip = d3.select("#tooltip");

// Llenar lista lateral
const prestamistasList = d3.select("#prestamistas-list");
Object.keys(data).forEach(prestamista => {
  prestamistasList.append("div")
    .attr("class", "prestamista-item")
    .text(prestamista)
    .on("click", () => drawTree(prestamista));
});

function drawTree(prestamista) {
  g.selectAll("*").remove(); // limpiar

  const root = d3.hierarchy({
    name: prestamista,
    children: data[prestamista]
  });

  const treeLayout = d3.tree().size([height - 100, width - 400]);
  treeLayout(root);

  // Enlaces con animación
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

  // Nodos
  const node = g.selectAll(".node")
    .data(root.descendants())
    .join("g")
    .attr("class", "node")
    .attr("transform", d => `translate(${root.y},${root.x})`)
    .transition()
    .duration(800)
    .attr("transform", d => `translate(${d.y},${d.x})`)
    .selection();

  node.append("circle")
    .attr("r", 8)
    .attr("fill", d => d.depth === 0 ? "#1976d2" : "#f9c74f")
    .on("mouseover", (event, d) => {
      if (d.depth === 0) return;
      tooltip.style("display", "block")
        .html(`
          <b>${d.data.nombre}</b><br>
          Valor: $${d.data.valor.toLocaleString()}<br>
          Interés: $${d.data.interes.toLocaleString()}<br>
          Fecha: ${d.data.fecha}<br>
          Total: $${(d.data.valor + d.data.interes).toLocaleString()}
        `);
    })
    .on("mousemove", (event) => {
      tooltip.style("left", (event.pageX + 15) + "px")
             .style("top", (event.pageY - 20) + "px");
    })
    .on("mouseout", () => tooltip.style("display", "none"));

  node.append("text")
    .attr("dy", "0.31em")
    .attr("x", d => d.depth === 0 ? -15 : 15)
    .attr("text-anchor", d => d.depth === 0 ? "end" : "start")
    .text(d => d.depth === 0 ? d.data.name : d.data.nombre);
}
</script>
</body>
</html>
