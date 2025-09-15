<?php
include("conexion.php");

if (!isset($_POST['ids'])) {
    echo "<script>alert('No seleccionaste ningún viaje');window.location='index2.php';</script>";
    exit;
}

$ids = $_POST['ids'];
$accion = $_POST['accion'];

if ($accion == "eliminar") {
    $idsStr = implode(",", array_map('intval', $ids));
    $sql = "DELETE FROM viajes WHERE id IN ($idsStr)";
    if ($conexion->query($sql)) {
        echo "<script>alert('Viajes eliminados correctamente');window.location='index2.php';</script>";
    } else {
        echo "Error: ".$conexion->error;
    }
} elseif ($accion == "editar") {
    // Redirige a editar_multiple enviando IDs por GET
    $idsStr = implode(",", $ids);
    header("Location: editar_multiple.php?ids=".$idsStr);
}
