<?php
include("nav.php");

$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}

$result = $conn->query("SELECT nombre, prestamista, meses, valor, id FROM prestamos");
$prestamos = [];
while ($row = $result->fetch_assoc()) {
    $prestamos[] = $row;
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Prestamos Visual</title>
<script src="https://d3js.org/d3.v7.min.js"></script>
<style>
body {
    font-family: "Segoe UI", sans-serif;
    background: #f5f5f5;
    overflow: auto;
}
#panel {
    position: fixed;
    top: 70px;
    left: 20px;
    background: white;
    padding: 15px;
    border-radius: 15px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
}
svg {
    width: 100%;
    height: 100vh;
}
.node rect {
    stroke: #999;
    stroke-width: 2;
    rx: 15;
    ry: 15;
}
.node text {
    font-size: 14px;
    pointer-events: none;
}
.link {
    fill: none;
    stroke: #aaa;
    stroke-width: 2;
}
</style>
</head>
<body>

<div id="panel">
    <label>Selecciona un prestamista:</label>
    <select id="prestamistaSelect"></select>
</div>

<svg id="chart"></svg>

<script>
// === Datos desde PHP ===
const prestamos = <?php echo json_encode($prestamos); ?>;

// === Configuración del gráfico ===
const width = window.innerWidth;
const height = window.innerHeight;
const svg = d3.select("#chart");
const g = svg.append("g"); // sin posición fija

// === Colores por antigüedad ===
const colorPorMeses = meses => {
    if (meses <= 3) return "#8BC34A";
    if (meses <= 6) return "#FFC107";
    if (meses <= 9) return "#FF9800";
    return "#F44336";
};

// === Lista de prestamistas ===
const prestamistas = [...new Set(prestamos.map(p => p.prestamista))];
const select = document.getElementById("prestamistaSelect");
prestamistas.forEach(p => {
    const opt = document.createElement("option");
    opt.value = p;
    opt.textContent = p;
    select.appendChild(opt);
});
select.addEventListener("change", e => drawTree(e.target.value));

// === Dibuja el árbol ===
function drawTree(prestamista) {
    g.selectAll("*").remove();

    const rows = prestamos.filter(p => p.prestamista === prestamista);
    const root = d3.hierarchy({ name: prestamista, children: rows });

    const treeLayout = d3.tree().nodeSize([140, 220]);
    treeLayout(root);

    // === Centrar verticalmente ===
    const extentY = d3.extent(root.descendants(), d => d.x);
    const treeHeight = extentY[1] - extentY[0];
    const offsetY = (height - treeHeight) / 2;
    g.attr("transform", `translate(150,${offsetY})`);

    // === Líneas ===
    g.selectAll(".link")
        .data(root.links())
        .enter()
        .append("path")
        .attr("class", "link")
        .attr("d", d3.linkHorizontal()
            .x(d => d.y)
            .y(d => d.x)
        );

    // === Nodos ===
    const node = g.selectAll(".node")
        .data(root.descendants())
        .enter()
        .append("g")
        .attr("class", "node")
        .attr("transform", d => `translate(${d.y},${d.x})`);

    // === Rectángulos ===
    node.append("rect")
        .attr("width", 180)
        .attr("height", 80)
        .attr("x", -90)
        .attr("y", -40)
        .attr("fill", d => d.children ? "#1976D2" : colorPorMeses(d.data.meses))
        .attr("stroke", "#444")
        .attr("stroke-width", 2)
        .attr("opacity", 0)
        .transition()
        .duration(800)
        .attr("opacity", 1);

    // === Texto ===
    node.append("text")
        .attr("text-anchor", "middle")
        .attr("dy", "-10")
        .attr("fill", "#fff")
        .text(d => d.data.name || d.data.nombre);

    node.append("text")
        .attr("text-anchor", "middle")
        .attr("dy", "10")
        .attr("fill", "#fff")
        .text(d => d.data.meses ? `${d.data.meses} meses` : "");

    node.append("text")
        .attr("text-anchor", "middle")
        .attr("dy", "28")
        .attr("fill", "#fff")
        .text(d => d.data.valor ? `$${parseInt(d.data.valor).toLocaleString()}` : "");
}

// === Mostrar el primero automáticamente ===
if (prestamistas.length > 0) drawTree(prestamistas[0]);
</script>

</body>
</html>
