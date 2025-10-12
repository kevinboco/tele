<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}

// Obtener todos los prestamistas con cantidad de préstamos
$prestamistas = $conn->query("
    SELECT prestamista, COUNT(*) as cantidad 
    FROM prestamos 
    GROUP BY prestamista 
    ORDER BY prestamista ASC
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Visual - Prestamistas</title>
<script src="https://d3js.org/d3.v7.min.js"></script>
<style>
body {
    font-family: 'Poppins', sans-serif;
    margin: 0;
    background: #f5f6fa;
    display: flex;
    height: 100vh;
}

/* Panel izquierdo */
#sidebar {
    width: 250px;
    background: white;
    border-right: 1px solid #ddd;
    padding: 15px;
    overflow-y: auto;
}
#sidebar h3 {
    text-align: center;
    color: #333;
    margin-bottom: 10px;
}
.prestamista-btn {
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 10px;
    margin-bottom: 8px;
    width: 100%;
    text-align: left;
    cursor: pointer;
    transition: 0.2s;
}
.prestamista-btn:hover {
    background: #007bff;
    color: white;
}

/* Área de visualización */
#graph-container {
    flex: 1;
    position: relative;
    overflow: hidden;
}
svg {
    width: 100%;
    height: 100%;
}
.node text {
    font-size: 12px;
    pointer-events: none;
}
.link {
    stroke: #ccc;
    stroke-width: 1.5px;
}
.tooltip {
    position: absolute;
    background: #fff;
    padding: 8px;
    border-radius: 6px;
    box-shadow: 0px 0px 6px rgba(0,0,0,0.15);
    pointer-events: none;
    font-size: 13px;
}
</style>
</head>
<body>

<div id="sidebar">
    <h3>Prestamistas</h3>
    <p style="font-size: 13px; color: #777;">Haz clic para ver su árbol de deudores:</p>
    <?php while($row = $prestamistas->fetch_assoc()): ?>
        <button class="prestamista-btn" onclick="mostrarPrestamista('<?php echo addslashes($row['prestamista']); ?>')">
            <?php echo htmlspecialchars($row['prestamista']); ?> (<?php echo $row['cantidad']; ?>)
        </button>
    <?php endwhile; ?>
</div>

<div id="graph-container">
    <svg></svg>
    <div class="tooltip" style="opacity:0;"></div>
</div>

<script>
async function mostrarPrestamista(nombre) {
    const svg = d3.select("svg");
    svg.selectAll("*").remove(); // limpiar el gráfico anterior

    const tooltip = d3.select(".tooltip");
    const width = window.innerWidth - 250;
    const height = window.innerHeight;

    // Obtener datos desde PHP vía AJAX
    const response = await fetch(`get_prestamista_data.php?prestamista=${encodeURIComponent(nombre)}`);
    const data = await response.json();

    // Escala de colores según meses
    const colorScale = d3.scaleSequential()
        .domain([0, 12]) // 0 a 12 meses
        .interpolator(d3.interpolateYlOrRd);

    const simulation = d3.forceSimulation(data.nodes)
        .force("link", d3.forceLink(data.links).id(d => d.id).distance(120))
        .force("charge", d3.forceManyBody().strength(-300))
        .force("center", d3.forceCenter(width / 2, height / 2));

    const link = svg.append("g")
        .attr("class", "links")
        .selectAll("line")
        .data(data.links)
        .join("line")
        .attr("stroke", "#ccc");

    const node = svg.append("g")
        .attr("class", "nodes")
        .selectAll("circle")
        .data(data.nodes)
        .join("circle")
        .attr("r", d => d.tipo === "prestamista" ? 22 : 14)
        .attr("fill", d => d.tipo === "prestamista" ? "#007bff" : colorScale(d.meses || 0))
        .attr("stroke", "#fff")
        .attr("stroke-width", 1.5)
        .on("mouseover", (event, d) => {
            tooltip.transition().duration(200).style("opacity", .9);
            tooltip.html(`<strong>${d.id}</strong><br>${d.tipo === 'prestamista' ? '' : 'Meses: ' + d.meses}`)
                   .style("left", (event.pageX + 10) + "px")
                   .style("top", (event.pageY - 28) + "px");
        })
        .on("mouseout", () => tooltip.transition().duration(500).style("opacity", 0));

    const label = svg.append("g")
        .selectAll("text")
        .data(data.nodes)
        .join("text")
        .text(d => d.id)
        .attr("font-size", 11)
        .attr("dy", 4);

    simulation.on("tick", () => {
        link
            .attr("x1", d => d.source.x)
            .attr("y1", d => d.source.y)
            .attr("x2", d => d.target.x)
            .attr("y2", d => d.target.y);

        node
            .attr("cx", d => d.x)
            .attr("cy", d => d.y);

        label
            .attr("x", d => d.x + 20)
            .attr("y", d => d.y + 5);
    });
}
</script>

</body>
</html>
