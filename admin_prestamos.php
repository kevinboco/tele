<?php
include("nav.php");
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}

// Obtener los datos agrupados por prestamista
$sql = "
    SELECT p.id, p.nombre AS prestamista, 
           d.nombre AS deudor, d.valor_prestado, d.fecha, d.interes
    FROM prestamos p
    JOIN deudores d ON p.id = d.prestamista_id
    ORDER BY p.nombre, d.nombre
";
$result = $conn->query($sql);

$data = [];
while ($row = $result->fetch_assoc()) {
    $prestamista = $row['prestamista'];
    if (!isset($data[$prestamista])) {
        $data[$prestamista] = [];
    }
    $data[$prestamista][] = [
        "nombre" => $row['deudor'],
        "valor" => (int)$row['valor_prestado'],
        "fecha" => $row['fecha'],
        "interes" => (int)$row['interes'],
        "total" => (int)$row['valor_prestado'] + (int)$row['interes']
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
            font-family: 'Segoe UI', sans-serif;
            background: #f9f9f9;
            margin: 0;
            overflow-x: hidden;
        }

        .prestamista {
            cursor: pointer;
            fill: #1976D2;
        }

        .prestamista:hover {
            fill: #1565C0;
        }

        .deudor {
            fill: #f9c74f;
            cursor: pointer;
        }

        .link {
            fill: none;
            stroke: #ccc;
            stroke-width: 2px;
        }

        text {
            font-size: 14px;
            fill: #333;
        }

        .panel {
            width: 280px;
            background: white;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            border-right: 1px solid #ddd;
            padding: 15px;
            overflow-y: auto;
        }

        .prestamista-item {
            padding: 10px;
            background: #e3f2fd;
            margin-bottom: 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.2s;
        }

        .prestamista-item:hover {
            background: #bbdefb;
        }

        svg {
            margin-left: 300px;
        }
    </style>
</head>
<body>

<div class="panel">
    <h3>Prestamistas</h3>
    <div id="prestamistas-list"></div>
</div>

<svg id="chart" width="1500" height="900"></svg>

<script>
const data = <?php echo json_encode($data, JSON_PRETTY_PRINT); ?>;
const svg = d3.select("#chart");
const width = +svg.attr("width");
const height = +svg.attr("height");

// Panel lateral con nombres de prestamistas
const prestamistasList = d3.select("#prestamistas-list");

Object.keys(data).forEach(prestamista => {
    prestamistasList.append("div")
        .attr("class", "prestamista-item")
        .text(prestamista)
        .on("click", () => drawTree(prestamista));
});

// Grupo principal para dibujar los árboles
const g = svg.append("g")
    .attr("transform", "translate(200, 50)");

function drawTree(prestamista) {
    g.selectAll("*").remove(); // limpiar gráfico anterior

    const root = d3.hierarchy({
        name: prestamista,
        children: data[prestamista]
    });

    const treeLayout = d3.tree().size([height - 100, width - 500]);
    treeLayout(root);

    // Enlaces con animación
    g.selectAll(".link")
        .data(root.links())
        .join("path")
        .attr("class", "link")
        .attr("d", d3.linkHorizontal()
            .x(d => d.y)
            .y(d => d.x))
        .attr("stroke-opacity", 0)
        .transition()
        .duration(800)
        .attr("stroke-opacity", 1);

    // Nodos
    const node = g.selectAll(".node")
        .data(root.descendants())
        .join("g")
        .attr("class", "node")
        .attr("transform", d => `translate(${root.y0 || 0},${root.x0 || 0})`)
        .transition()
        .duration(800)
        .attr("transform", d => `translate(${d.y},${d.x})`)
        .selection();

    node.append("circle")
        .attr("r", 6)
        .attr("fill", d => d.depth === 0 ? "#1976D2" : "#f9c74f");

    node.append("text")
        .attr("dy", "0.31em")
        .attr("x", d => d.depth === 0 ? -15 : 15)
        .attr("text-anchor", d => d.depth === 0 ? "end" : "start")
        .text(d => d.data.name || `${d.data.nombre} ($${d.data.valor.toLocaleString()})`);
}
</script>

</body>
</html>
