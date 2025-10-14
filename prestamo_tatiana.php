<?php
/******************************************************
 * prestamo_tatiana.php
 * CRUD + Buscador + Filtro por fecha + campo PAGADO
 ******************************************************/

// ====== CONFIG BD ======
$conexion = new mysqli(
  "mysql.hostinger.com",
  "u648222299_tatiana",
  "Bucaramanga3011",
  "u648222299_tatiana"
);
if ($conexion->connect_errno) {
  die("❌ Error de conexión: " . $conexion->connect_error);
}
$conexion->set_charset("utf8");

// ====== AGREGAR ======
if (isset($_POST['agregar'])) {
  $deudor = trim($_POST['deudor']);
  $celular = trim($_POST['celular']);
  $prestamista = trim($_POST['prestamista']);
  $monto = $_POST['monto'];
  $pagado = $_POST['pagado'] ?? 0;
  $fecha = $_POST['fecha'];

  $stmt = $conexion->prepare("INSERT INTO prestamos (deudor, celular, prestamista, monto, pagado, fecha) VALUES (?, ?, ?, ?, ?, ?)");
  $stmt->bind_param("sssdds", $deudor, $celular, $prestamista, $monto, $pagado, $fecha);
  $stmt->execute();
  $stmt->close();

  header("Location: prestamo_tatiana.php");
  exit;
}

// ====== ELIMINAR ======
if (isset($_GET['eliminar'])) {
  $id = intval($_GET['eliminar']);
  $conexion->query("DELETE FROM prestamos WHERE id = $id");
  header("Location: prestamo_tatiana.php");
  exit;
}

// ====== EDITAR ======
if (isset($_POST['editar'])) {
  $id = $_POST['id'];
  $deudor = trim($_POST['deudor']);
  $celular = trim($_POST['celular']);
  $prestamista = trim($_POST['prestamista']);
  $monto = $_POST['monto'];
  $pagado = $_POST['pagado'];
  $fecha = $_POST['fecha'];

  $stmt = $conexion->prepare("UPDATE prestamos SET deudor=?, celular=?, prestamista=?, monto=?, pagado=?, fecha=? WHERE id=?");
  $stmt->bind_param("sssddsd", $deudor, $celular, $prestamista, $monto, $pagado, $fecha, $id);
  $stmt->execute();
  $stmt->close();

  header("Location: prestamo_tatiana.php");
  exit;
}

/* ====== FILTROS ====== */
$q = $_GET['q'] ?? '';
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';

$where = [];
$params = [];
$types = "";

if ($q !== '') {
  $where[] = "(deudor LIKE ? OR celular LIKE ? OR prestamista LIKE ?)";
  $like = "%$q%";
  $params = array_merge($params, [$like, $like, $like]);
  $types .= "sss";
}
if ($desde !== '') {
  $where[] = "fecha >= ?";
  $params[] = $desde;
  $types .= "s";
}
if ($hasta !== '') {
  $where[] = "fecha <= ?";
  $params[] = $hasta;
  $types .= "s";
}

$sql = "SELECT * FROM prestamos";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY fecha DESC, id DESC";

$stmt = $conexion->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$prestamos = $stmt->get_result();

// Suma total de lo prestado y pagado
$sqlSum = "SELECT SUM(monto) total, SUM(pagado) pagado FROM prestamos";
if ($where) $sqlSum .= " WHERE " . implode(" AND ", $where);
$stmtSum = $conexion->prepare($sqlSum);
if ($params) $stmtSum->bind_param($types, ...$params);
$stmtSum->execute();
$suma = $stmtSum->get_result()->fetch_assoc();
$totalPrestado = $suma['total'] ?? 0;
$totalPagado = $suma['pagado'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestión de Préstamos - Tatiana</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <h2 class="mb-4 text-center">📋 Gestión de Préstamos - Tatiana</h2>

  <!-- FORM NUEVO -->
  <form method="POST" class="card p-3 shadow-sm mb-4">
    <h5 class="mb-3">Nuevo préstamo</h5>
    <div class="row g-3">
      <div class="col-md-3"><input type="text" name="deudor" class="form-control" placeholder="Deudor" required></div>
      <div class="col-md-2"><input type="text" name="celular" class="form-control" placeholder="Celular"></div>
      <div class="col-md-3"><input type="text" name="prestamista" class="form-control" placeholder="Prestamista" required></div>
      <div class="col-md-2"><input type="number" step="0.01" name="monto" class="form-control" placeholder="Monto" required></div>
      <div class="col-md-2"><input type="number" step="0.01" name="pagado" class="form-control" placeholder="Pagado" value="0" required></div>
    </div>
    <div class="row mt-3">
      <div class="col-md-3"><input type="date" name="fecha" class="form-control" required></div>
      <div class="col-md-9 text-end"><button type="submit" name="agregar" class="btn btn-primary">Agregar</button></div>
    </div>
  </form>

  <!-- FILTROS -->
  <form method="GET" class="card p-3 shadow-sm mb-3">
    <h6 class="mb-3">Filtrar</h6>
    <div class="row g-2">
      <div class="col-md-4"><input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="Buscar deudor, celular o prestamista"></div>
      <div class="col-md-3"><input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="form-control"></div>
      <div class="col-md-3"><input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="form-control"></div>
      <div class="col-md-2 d-grid"><button class="btn btn-dark">Filtrar</button></div>
    </div>
    <div class="text-end mt-2"><a class="btn btn-outline-secondary btn-sm" href="prestamo_tatiana.php">Limpiar filtros</a></div>
  </form>

  <div class="alert alert-info">
    <strong>Total Prestado:</strong> $<?= number_format($totalPrestado, 0, ',', '.') ?> |
    <strong>Total Pagado:</strong> $<?= number_format($totalPagado, 0, ',', '.') ?> |
    <strong>Pendiente:</strong> $<?= number_format($totalPrestado - $totalPagado, 0, ',', '.') ?>
  </div>

  <!-- TABLA -->
  <div class="table-responsive">
    <table class="table table-striped table-hover shadow-sm align-middle">
      <thead class="table-dark">
        <tr>
          <th>ID</th><th>Deudor</th><th>Celular</th><th>Prestamista</th>
          <th>Monto</th><th>Pagado</th><th>Pendiente</th><th>Fecha</th><th>Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($prestamos->num_rows === 0): ?>
        <tr><td colspan="9" class="text-center text-muted">No hay registros</td></tr>
      <?php endif; ?>
      <?php while($fila = $prestamos->fetch_assoc()): 
        $pendiente = $fila['monto'] - $fila['pagado'];
      ?>
        <tr>
          <td><?= $fila['id'] ?></td>
          <td><?= htmlspecialchars($fila['deudor']) ?></td>
          <td><?= htmlspecialchars($fila['celular']) ?></td>
          <td><?= htmlspecialchars($fila['prestamista']) ?></td>
          <td>$<?= number_format($fila['monto'], 0, ',', '.') ?></td>
          <td>$<?= number_format($fila['pagado'], 0, ',', '.') ?></td>
          <td class="<?= $pendiente > 0 ? 'text-danger fw-bold' : 'text-success' ?>">$<?= number_format($pendiente, 0, ',', '.') ?></td>
          <td><?= $fila['fecha'] ?></td>
          <td>
            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#edit<?= $fila['id'] ?>">Editar</button>
            <a href="?eliminar=<?= $fila['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar este registro?')">Eliminar</a>
          </td>
        </tr>

        <!-- MODAL EDITAR -->
        <div class="modal fade" id="edit<?= $fila['id'] ?>" tabindex="-1">
          <div class="modal-dialog">
            <div class="modal-content">
              <form method="POST">
                <div class="modal-header"><h5 class="modal-title">Editar préstamo #<?= $fila['id'] ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                  <input type="hidden" name="id" value="<?= $fila['id'] ?>">
                  <div class="mb-2"><label>Deudor</label><input type="text" name="deudor" class="form-control" value="<?= htmlspecialchars($fila['deudor']) ?>" required></div>
                  <div class="mb-2"><label>Celular</label><input type="text" name="celular" class="form-control" value="<?= htmlspecialchars($fila['celular']) ?>"></div>
                  <div class="mb-2"><label>Prestamista</label><input type="text" name="prestamista" class="form-control" value="<?= htmlspecialchars($fila['prestamista']) ?>" required></div>
                  <div class="mb-2"><label>Monto</label><input type="number" step="0.01" name="monto" class="form-control" value="<?= $fila['monto'] ?>" required></div>
                  <div class="mb-2"><label>Pagado</label><input type="number" step="0.01" name="pagado" class="form-control" value="<?= $fila['pagado'] ?>" required></div>
                  <div class="mb-2"><label>Fecha</label><input type="date" name="fecha" class="form-control" value="<?= $fila['fecha'] ?>" required></div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                  <button type="submit" name="editar" class="btn btn-primary">Guardar</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
