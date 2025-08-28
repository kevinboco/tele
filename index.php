<?php
$input = file_get_contents("php://input");
file_put_contents("debug.txt", $input . PHP_EOL, FILE_APPEND);
?>


<?php include("conexion.php"); ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Listado de Viajes</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include("nav.php"); ?>

<div class="container">
  <div class="card shadow mb-4">
    <div class="card-header bg-primary text-white">
      <h3 class="mb-0">Filtros de búsqueda</h3>
    </div>
    <div class="card-body">
      <form method="GET" class="row g-3">
        <div class="col-md-3">
          <label>Nombre</label>
          <input type="text" name="nombre" value="<?= $_GET['nombre'] ?? '' ?>" class="form-control">
        </div>
        <div class="col-md-3">
          <label>Cédula</label>
          <input type="text" name="cedula" value="<?= $_GET['cedula'] ?? '' ?>" class="form-control">
        </div>
        <div class="col-md-3">
          <label>Fecha desde</label>
          <input type="date" name="desde" value="<?= $_GET['desde'] ?? '' ?>" class="form-control">
        </div>
        <div class="col-md-3">
          <label>Fecha hasta</label>
          <input type="date" name="hasta" value="<?= $_GET['hasta'] ?? '' ?>" class="form-control">
        </div>
        <div class="col-md-3">
          <label>Ruta</label>
          <input type="text" name="ruta" value="<?= $_GET['ruta'] ?? '' ?>" class="form-control">
        </div>
        <div class="col-md-3">
          <label>Vehículo</label>
          <input type="text" name="vehiculo" value="<?= $_GET['vehiculo'] ?? '' ?>" class="form-control">
        </div>
        <div class="col-md-3 align-self-end">
          <button type="submit" class="btn btn-success w-100">🔎 Buscar</button>
        </div>
        <div class="col-md-3 align-self-end">
          <a href="index.php" class="btn btn-secondary w-100">❌ Limpiar</a>
        </div>
      </form>
    </div>
  </div>

  <?php
  // Construir la consulta dinámica
  $where = [];
  if (!empty($_GET['nombre'])) {
    $nombre = $conexion->real_escape_string($_GET['nombre']);
    $where[] = "nombre LIKE '%$nombre%'";
  }
  if (!empty($_GET['cedula'])) {
    $cedula = $conexion->real_escape_string($_GET['cedula']);
    $where[] = "cedula LIKE '%$cedula%'";
  }
  if (!empty($_GET['desde']) && !empty($_GET['hasta'])) {
    $desde = $conexion->real_escape_string($_GET['desde']);
    $hasta = $conexion->real_escape_string($_GET['hasta']);
    $where[] = "fecha BETWEEN '$desde' AND '$hasta'";
  }
  if (!empty($_GET['ruta'])) {
    $ruta = $conexion->real_escape_string($_GET['ruta']);
    $where[] = "ruta LIKE '%$ruta%'";
  }
  if (!empty($_GET['vehiculo'])) {
    $vehiculo = $conexion->real_escape_string($_GET['vehiculo']);
    $where[] = "tipo_vehiculo LIKE '%$vehiculo%'";
  }

  $sql = "SELECT * FROM viajes";
  if (count($where) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where);
  }
  $sql .= " ORDER BY id DESC";

  $resultado = $conexion->query($sql);
  ?>

  <?php if (!empty($_GET['nombre'])): ?>
    <?php
      $nombreFiltro = $conexion->real_escape_string($_GET['nombre']);
      $sqlContar = "SELECT COUNT(*) as total FROM viajes WHERE nombre LIKE '%$nombreFiltro%'";
      $resContar = $conexion->query($sqlContar);
      $totalViajes = $resContar->fetch_assoc()['total'];
    ?>
    <div class="alert alert-info">
      <strong><?= htmlspecialchars($_GET['nombre']) ?></strong> ha hecho <b><?= $totalViajes ?></b> viajes.
    </div>
  <?php endif; ?>

  <div class="card shadow">
    <div class="card-header bg-dark text-white">
      <h3 class="mb-0">Listado de Viajes</h3>
    </div>
    <div class="card-body">
      <a href="crear.php" class="btn btn-success mb-3">➕ Nuevo Viaje</a>
      <table class="table table-bordered table-striped">
        <thead class="table-dark">
          <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Cédula</th>
            <th>Fecha</th>
            <th>Ruta</th>
            <th>Vehículo</th>
            <th>Imagen</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php while($row = $resultado->fetch_assoc()){ ?>
          <tr>
            <td><?= $row['id']; ?></td>
            <td><?= $row['nombre']; ?></td>
            <td><?= $row['cedula']; ?></td>
            <td><?= $row['fecha']; ?></td>
            <td><?= $row['ruta']; ?></td>
            <td><?= $row['tipo_vehiculo']; ?></td>
            <td>
              <?php if($row['imagen']){ ?>
                <a href="#" data-bs-toggle="modal" data-bs-target="#imgModal<?= $row['id']; ?>">
                  <img src="uploads/<?= $row['imagen']; ?>" width="70">
                </a>
                <div class="modal fade" id="imgModal<?= $row['id']; ?>" tabindex="-1">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-body text-center">
                        <img src="uploads/<?= $row['imagen']; ?>" class="img-fluid rounded">
                      </div>
                    </div>
                  </div>
                </div>
              <?php } else { echo "—"; } ?>
            </td>
            <td>
              <a href="editar.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm">✏ Editar</a>
              <a href="eliminar.php?id=<?= $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Seguro de eliminar?')">🗑 Eliminar</a>
            </td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
