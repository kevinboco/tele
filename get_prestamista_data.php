<?php
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die(json_encode(["error" => $conn->connect_error]));
}

$prestamista = $conn->real_escape_string($_GET['prestamista']);

// Obtener todos los préstamos del prestamista
$query = $conn->query("SELECT deudor, meses FROM prestamos WHERE prestamista = '$prestamista'");

$nodes = [];
$links = [];
$nodes[] = ["id" => $prestamista, "tipo" => "prestamista"];

while ($row = $query->fetch_assoc()) {
    $nodes[] = [
        "id" => $row['deudor'],
        "tipo" => "deudor",
        "meses" => (int)$row['meses']
    ];
    $links[] = ["source" => $prestamista, "target" => $row['deudor']];
}

echo json_encode(["nodes" => $nodes, "links" => $links]);
?>
