<?php
// ====== CONFIGURACIÓN BD ======
$conexion = new mysqli("mysql.hostinger.com", "u648222299_tatiana", "Bucaramanga3011", "u648222299_tatiana");
if ($conexion->connect_errno) {
  die("Error al conectar: " . $conexion->connect_error);
}
$conexion->set_charset("utf8");

// ====== AGREGAR ======
if (isset($_POST['agregar'])) {
  $deudor = $_POST['deudor'];
  $celular = $_POST['celular'];
  $prestamista = $_POST['prestamista'];
  $monto = $_POST['monto'];
  $fecha = $_POST['fecha'];

  $stmt = $conexion->prepare("INSERT INTO prestamos (deudor, celular, prestamista, monto, fecha) VALUES (?, ?, ?, ?, ?)");
  $stmt->bind_param("sssds", $deudor, $celular, $prestamista, $monto, $fecha);
  $stmt->execute();
  $stmt->close();
  header("Location: prestamos.php");
  exit;
}

// ====== ELIMINAR ======
if (isset($_GET['eliminar'])) {
  $id = intval($_GET['eliminar']);
  $conexion->query("DELETE FROM prestamos WHERE id = $id");
  header("Location: prestamos.php");
  exit;
}

// ====== EDITAR ======
if (isset($_POST['editar'])) {
  $id = $_POST['id'];
  $deudor = $_POST['deudor'];
  $celular = $_POST['celular'];
  $prestamista = $_POST['prestamista'];
  $monto = $_POST['monto'];
  $fecha = $_POST['fecha'];

  $stmt = $conexion->prepare("UPDATE prestamos SET deudor=?, celular=?, prestamista=?, monto=?, fecha=? WHERE id=?");
  $stmt->bind_param("sssdsd", $deudor, $celular, $prestamista, $monto, $fecha, $id);
  $stmt->execute();
  $stmt->close();
  header("Location: prestamos.php");
  exit;
}

// ====== CONSULTA ======
$prestamos = $conexion->query("SELECT * FROM prestamos ORDER BY fecha DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestión de Préstamos</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <h2 class="mb-4 text-center">📋 Gestión de Préstamos</h2>

  <!-- FORMULARIO NUEVO -->
  <form method="POST" class="card p-3 shadow-sm mb-4">
    <h5>Nuevo préstamo</h5>
    <div class="row g-3">
      <div class="col-md-3">
        <input type="text" name="deudor" class="form-control" placeholder="Deudor" required>
      </div>
      <div class="col-md-2">
        <input type="text" name="celular" class="form-control" placeholder="Celular">
      </div>
      <div class="col-md-3">
        <input type="text" name="prestamista" class="form-control" placeholder="Prestamista" required>
      </div>
      <div class="col-md-2">
        <input type="number" step="0.01" name="monto" class="form-control" placeholder="Monto" required>
      </div>
      <div class="col-md-2">
        <input type="date" name="fecha" class="form-control" required>
      </div>
    </div>
    <div class="text-end mt-3">
      <button type="submit" name="agregar" class="btn btn-primary">Agregar</button>
    </div>
  </form>

  <!-- TABLA -->
  <table class="table table-striped table-hover shadow-sm align-middle">
    <thead class="table-dark">
      <tr>
        <th>ID</th>
        <th>Deudor</th>
        <th>Celular</th>
        <th>Prestamista</th>
        <th>Monto</th>
        <th>Fecha</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
    <?php while($fila = $prestamos->fetch_assoc()): ?>
      <tr>
        <td><?= $fila['id'] ?></td>
        <td><?= htmlspecialchars($fila['deudor']) ?></td>
        <td><?= htmlspecialchars($fila['celular']) ?></td>
        <td><?= htmlspecialchars($fila['prestamista']) ?></td>
        <td>$<?= number_format($fila['monto'], 0, ',', '.') ?></td>
        <td><?= $fila['fecha'] ?></td>
        <td>
          <!-- Botón Editar -->
          <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#edit<?= $fila['id'] ?>">Editar</button>
          <!-- Botón Eliminar -->
          <a href="?eliminar=<?= $fila['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar este registro?')">Eliminar</a>
        </td>
      </tr>

      <!-- MODAL EDITAR -->
      <div class="modal fade" id="edit<?= $fila['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <form method="POST">
              <div class="modal-header">
                <h5 class="modal-title">Editar préstamo #<?= $fila['id'] ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="id" value="<?= $fila['id'] ?>">
                <div class="mb-3">
                  <label>Deudor</label>
                  <input type="text" name="deudor" class="form-control" value="<?= htmlspecialchars($fila['deudor']) ?>" required>
                </div>
                <div class="mb-3">
                  <label>Celular</label>
                  <input type="text" name="celular" class="form-control" value="<?= htmlspecialchars($fila['celular']) ?>">
                </div>
                <div class="mb-3">
                  <label>Prestamista</label>
                  <input type="text" name="prestamista" class="form-control" value="<?= htmlspecialchars($fila['prestamista']) ?>" required>
                </div>
                <div class="mb-3">
                  <label>Monto</label>
                  <input type="number" step="0.01" name="monto" class="form-control" value="<?= $fila['monto'] ?>" required>
                </div>
                <div class="mb-3">
                  <label>Fecha</label>
                  <input type="date" name="fecha" class="form-control" value="<?= $fila['fecha'] ?>" required>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="submit" name="editar" class="btn btn-primary">Guardar cambios</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
    </tbody>
  </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
