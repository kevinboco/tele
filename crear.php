<?php include("conexion.php"); ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Nuevo Viaje</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include("nav.php"); ?>

<div class="container">
  <div class="card shadow">
    <div class="card-header bg-success text-white">
      <h3>Agregar Viaje</h3>
    </div>
    <div class="card-body">
      <form action="" method="post" enctype="multipart/form-data">
        <div class="mb-3">
          <label>Nombre</label>
          <input type="text" name="nombre" class="form-control" required>
        </div>
        <div class="mb-3">
          <label>Cédula</label>
          <input type="text" name="cedula" class="form-control" required>
        </div>
        <div class="mb-3">
          <label>Fecha</label>
          <input type="date" name="fecha" class="form-control" required>
        </div>
        <div class="mb-3">
          <label>Ruta</label>
          <input type="text" name="ruta" class="form-control" required>
        </div>
        <div class="mb-3">
          <label>Tipo de Vehículo</label>
          <input type="text" name="tipo_vehiculo" class="form-control" required>
        </div>
        <div class="mb-3">
          <label>Imagen</label>
          <input type="file" name="imagen" class="form-control">
        </div>
        <button type="submit" name="guardar" class="btn btn-success">Guardar</button>
      </form>
    </div>
  </div>
</div>

<?php
if(isset($_POST['guardar'])){
    $nombre = $_POST['nombre'];
    $cedula = $_POST['cedula'];
    $fecha = $_POST['fecha'];
    $ruta = $_POST['ruta'];
    $vehiculo = $_POST['tipo_vehiculo'];

    // Manejo de la imagen
    $imagen = null;
    if(!empty($_FILES['imagen']['name'])){
        $imagen = time() . "_" . $_FILES['imagen']['name'];
        move_uploaded_file($_FILES['imagen']['tmp_name'], "uploads/".$imagen);
    }

    $sql = "INSERT INTO viajes (nombre, cedula, fecha, ruta, tipo_vehiculo, imagen) 
            VALUES ('$nombre','$cedula','$fecha','$ruta','$vehiculo','$imagen')";
    if($conexion->query($sql)){
        echo "<script>alert('Viaje guardado correctamente');window.location='index.php';</script>";
    }else{
        echo "Error: ".$conexion->error;
    }
}
?>
</body>
</html>
