<?php
include("conexion.php");

if (!isset($_GET['ids'])) {
    echo "<script>alert('No seleccionaste ningún viaje');window.location='index2.php';</script>";
    exit;
}

$ids = explode(",", $_GET['ids']);
$ids = array_map('intval', $ids);
$idsStr = implode(",", $ids);

$sql = "SELECT * FROM viajes WHERE id IN ($idsStr)";
$resultado = $conexion->query($sql);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['viajes'] as $id => $datos) {
        $nombre = $conexion->real_escape_string($datos['nombre']);
        $cedula = $conexion->real_escape_string($datos['cedula']);
        $fecha = $conexion->real_escape_string($datos['fecha']);
        $ruta = $conexion->real_escape_string($datos['ruta']);
        $vehiculo = $conexion->real_escape_string($datos['tipo_vehiculo']);
        $sqlUpdate = "UPDATE viajes SET nombre='$nombre', cedula='$cedula', fecha='$fecha', ruta='$ruta', tipo_vehiculo='$vehiculo' WHERE id=$id";
        $conexion->query($sqlUpdate);
    }
    echo "<script>alert('Viajes actualizados correctamente');window.location='index2.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Viajes Múltiples</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container">
  <div class="card shadow">
    <div class="card-header bg-warning">
      <h3>Editar Viajes Seleccionados</h3>
    </div>
    <div class="card-body">
      <form method="post">
        <?php while ($row = $resultado->fetch_assoc()) { ?>
          <div class="border rounded p-3 mb-3 bg-white">
            <h5>Viaje #<?= $row['id']; ?></h5>
            <div class="row">
              <div class="col-md-4">
                <label>Nombre</label>
                <input type="text" name="viajes[<?= $row['id']; ?>][nombre]" class="form-control" value="<?= $row['nombre']; ?>">
              </div>
              <div class="col-md-4">
                <label>Cédula</label>
                <input type="text" name="viajes[<?= $row['id']; ?>][cedula]" class="form-control" value="<?= $row['cedula']; ?>">
              </div>
              <div class="col-md-4">
                <label>Fecha</label>
                <input type="date" name="viajes[<?= $row['id']; ?>][fecha]" class="form-control" value="<?= $row['fecha']; ?>">
              </div>
              <div class="col-md-6 mt-2">
                <label>Ruta</label>
                <input type="text" name="viajes[<?= $row['id']; ?>][ruta]" class="form-control" value="<?= $row['ruta']; ?>">
              </div>
              <div class="col-md-6 mt-2">
                <label>Tipo Vehículo</label>
                <input type="text" name="viajes[<?= $row['id']; ?>][tipo_vehiculo]" class="form-control" value="<?= $row['tipo_vehiculo']; ?>">
              </div>
            </div>
          </div>
        <?php } ?>
        <button type="submit" class="btn btn-warning">Actualizar Todos</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
