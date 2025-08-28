<?php
include("conexion.php");
$id = $_GET['id'];

$sql = "DELETE FROM viajes WHERE id=$id";
if($conexion->query($sql)){
    echo "<script>alert('Viaje eliminado');window.location='index.php';</script>";
}else{
    echo "Error: ".$conexion->error;
}
?>
