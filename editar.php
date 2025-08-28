<?php 
include("conexion.php"); 
$id = $_GET['id'];
$sql = "SELECT * FROM viajes WHERE id=$id";
$res = $conexion->query($sql);
$row = $res->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Viaje</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include("nav.php"); ?>

<div class="container">
  <div class="card shadow">
    <div class="card-header bg-warning">
      <h3>Editar Viaje</h3>
    </div>
    <div class="card-body">
      <form action="" method="post" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= $row['id']; ?>">
        <div class="mb-3">
          <label>Nombre</label>
          <input type="text" name="nombre" class="form-control" value="<?= $row['nombre']; ?>" required>
        </div>
        <div class="mb-3">
          <label>Cédula</label>
          <input type="text" name="cedula" class="form-control" value="<?= $row['cedula']; ?>" required>
        </div>
        <div class="mb-3">
          <label>Fecha</label>
          <input type="date" name="fecha" class="form-control" value="<?= $row['fecha']; ?>" required>
        </div>
        <div class="mb-3">
          <label>Ruta</label>
          <input type="text" name="ruta" class="form-control" value="<?= $row['ruta']; ?>" required>
        </div>
        <div class="mb-3">
          <label>Tipo de Vehículo</label>
          <input type="text" name="tipo_vehiculo" class="form-control" value="<?= $row['tipo_vehiculo']; ?>" required>
        </div>
        <div class="mb-3">
          <label>Imagen actual:</label><br>
          <?php if($row['imagen']){ ?>
            <img src="uploads/<?= $row['imagen']; ?>" width="100"><br>
          <?php } ?>
          <input type="file" name="imagen" class="form-control mt-2">
        </div>
        <button type="submit" name="actualizar" class="btn btn-warning">Actualizar</button>
      </form>
    </div>
  </div>
</div>

<?php
if(isset($_POST['actualizar'])){
    $nombre = $_POST['nombre'];
    $cedula = $_POST['cedula'];
    $fecha = $_POST['fecha'];
    $ruta = $_POST['ruta'];
    $vehiculo = $_POST['tipo_vehiculo'];
    
    $imagen = $row['imagen'];
    if(!empty($_FILES['imagen']['name'])){
        $imagen = time() . "_" . $_FILES['imagen']['name'];
        move_uploaded_file($_FILES['imagen']['tmp_name'], "uploads/".$imagen);
    }

    $sql = "UPDATE viajes SET 
            nombre='$nombre', cedula='$cedula', fecha='$fecha', 
            ruta='$ruta', tipo_vehiculo='$vehiculo', imagen='$imagen'
            WHERE id=$id";
    if($conexion->query($sql)){
        echo "<script>alert('Viaje actualizado');window.location='index.php';</script>";
    }else{
        echo "Error: ".$conexion->error;
    }
}
?>
</body>
</html>
