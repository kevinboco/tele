<?php
include("conexion.php");

// ================== LISTAS PARA SELECTS ==================
$listaNombres  = [];
$listaCedulas  = [];
$listaRutas    = [];
$listaVehiculos= [];
$listaEmpresas = [];

// Nombres
$res = $conexion->query("SELECT DISTINCT nombre FROM viajes WHERE nombre <> '' ORDER BY nombre ASC");
if ($res) {
  while($r = $res->fetch_assoc()){
    $listaNombres[] = $r['nombre'];
  }
  $res->free();
}

// Cédulas
$res = $conexion->query("SELECT DISTINCT cedula FROM viajes WHERE cedula <> '' ORDER BY cedula ASC");
if ($res) {
  while($r = $res->fetch_assoc()){
    $listaCedulas[] = $r['cedula'];
  }
  $res->free();
}

// Rutas
$res = $conexion->query("SELECT DISTINCT ruta FROM viajes WHERE ruta <> '' ORDER BY ruta ASC");
if ($res) {
  while($r = $res->fetch_assoc()){
    $listaRutas[] = $r['ruta'];
  }
  $res->free();
}

// Vehículos
$res = $conexion->query("SELECT DISTINCT tipo_vehiculo FROM viajes WHERE tipo_vehiculo <> '' ORDER BY tipo_vehiculo ASC");
if ($res) {
  while($r = $res->fetch_assoc()){
    $listaVehiculos[] = $r['tipo_vehiculo'];
  }
  $res->free();
}

// Empresas
$res = $conexion->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa <> '' ORDER BY empresa ASC");
if ($res) {
  while($r = $res->fetch_assoc()){
    $listaEmpresas[] = $r['empresa'];
  }
  $res->free();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Listado de Viajes</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include("nav.php"); ?>

<div class="container py-3">

  <!-- Filtros -->
  <div class="card shadow mb-4">
    <div class="card-header bg-primary text-white">
      <h3 class="mb-0">Filtros de búsqueda</h3>
    </div>
    <div class="card-body">
      <form method="GET" class="row g-3">
        <!-- NOMBRE -->
        <div class="col-md-3">
          <label class="form-label">Nombre</label>
          <select name="nombre" class="form-select">
            <option value="">-- Todos --</option>
            <?php
            $nombreSel = isset($_GET['nombre']) ? $_GET['nombre'] : '';
            foreach($listaNombres as $nom):
              $sel = ($nombreSel === $nom) ? 'selected' : '';
            ?>
              <option value="<?= htmlspecialchars($nom) ?>" <?= $sel ?>>
                <?= htmlspecialchars($nom) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- CÉDULA -->
        <div class="col-md-3">
          <label class="form-label">Cédula</label>
          <select name="cedula" class="form-select">
            <option value="">-- Todas --</option>
            <?php
            $cedSel = isset($_GET['cedula']) ? $_GET['cedula'] : '';
            foreach($listaCedulas as $ced):
              $sel = ($cedSel === $ced) ? 'selected' : '';
            ?>
              <option value="<?= htmlspecialchars($ced) ?>" <?= $sel ?>>
                <?= htmlspecialchars($ced) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- FECHA DESDE -->
        <div class="col-md-3">
          <label class="form-label">Fecha desde</label>
          <input type="date" name="desde" value="<?= isset($_GET['desde']) ? htmlspecialchars($_GET['desde']) : '' ?>" class="form-control">
        </div>

        <!-- FECHA HASTA -->
        <div class="col-md-3">
          <label class="form-label">Fecha hasta</label>
          <input type="date" name="hasta" value="<?= isset($_GET['hasta']) ? htmlspecialchars($_GET['hasta']) : '' ?>" class="form-control">
        </div>

        <!-- RUTA -->
        <div class="col-md-3">
          <label class="form-label">Ruta</label>
          <select name="ruta" class="form-select">
            <option value="">-- Todas --</option>
            <?php
            $rutaSel = isset($_GET['ruta']) ? $_GET['ruta'] : '';
            foreach($listaRutas as $ruta):
              $sel = ($rutaSel === $ruta) ? 'selected' : '';
            ?>
              <option value="<?= htmlspecialchars($ruta) ?>" <?= $sel ?>>
                <?= htmlspecialchars($ruta) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- VEHÍCULO -->
        <div class="col-md-3">
          <label class="form-label">Vehículo</label>
          <select name="vehiculo" class="form-select">
            <option value="">-- Todos --</option>
            <?php
            $vehSel = isset($_GET['vehiculo']) ? $_GET['vehiculo'] : '';
            foreach($listaVehiculos as $veh):
              $sel = ($vehSel === $veh) ? 'selected' : '';
            ?>
              <option value="<?= htmlspecialchars($veh) ?>" <?= $sel ?>>
                <?= htmlspecialchars($veh) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- EMPRESA -->
        <div class="col-md-3">
          <label class="form-label">Empresa</label>
          <select name="empresa" class="form-select">
            <option value="">-- Todas --</option>
            <?php
            $empSel = isset($_GET['empresa']) ? $_GET['empresa'] : '';
            foreach($listaEmpresas as $emp):
              $sel = ($empSel === $emp) ? 'selected' : '';
            ?>
              <option value="<?= htmlspecialchars($emp) ?>" <?= $sel ?>>
                <?= htmlspecialchars($emp) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- BOTONES -->
        <div class="col-md-3 align-self-end">
          <button type="submit" class="btn btn-success w-100">🔎 Buscar</button>
        </div>
        <div class="col-md-3 align-self-end">
          <a href="index2.php" class="btn btn-secondary w-100">❌ Limpiar</a>
        </div>
      </form>
    </div>
  </div>

  <?php
  // ================== CONSTRUIR CONSULTA DINÁMICA ==================
  $where = [];

  if (!empty($_GET['nombre'])) {
    $nombre = $conexion->real_escape_string($_GET['nombre']);
    $where[] = "nombre = '$nombre'";
  }
  if (!empty($_GET['cedula'])) {
    $cedula = $conexion->real_escape_string($_GET['cedula']);
    $where[] = "cedula = '$cedula'";
  }
  if (!empty($_GET['desde']) && !empty($_GET['hasta'])) {
    $desde = $conexion->real_escape_string($_GET['desde']);
    $hasta = $conexion->real_escape_string($_GET['hasta']);
    $where[] = "fecha BETWEEN '$desde' AND '$hasta'";
  } elseif (!empty($_GET['desde'])) {
    $desde = $conexion->real_escape_string($_GET['desde']);
    $where[] = "fecha >= '$desde'";
  } elseif (!empty($_GET['hasta'])) {
    $hasta = $conexion->real_escape_string($_GET['hasta']);
    $where[] = "fecha <= '$hasta'";
  }
  if (!empty($_GET['ruta'])) {
    $ruta = $conexion->real_escape_string($_GET['ruta']);
    $where[] = "ruta = '$ruta'";
  }
  if (!empty($_GET['vehiculo'])) {
    $vehiculo = $conexion->real_escape_string($_GET['vehiculo']);
    $where[] = "tipo_vehiculo = '$vehiculo'";
  }
  if (!empty($_GET['empresa'])) {
    $empresa = $conexion->real_escape_string($_GET['empresa']);
    $where[] = "empresa = '$empresa'";
  }

  // Consulta principal ORDENADA DEL MÁS RECIENTE AL MÁS ANTIGUO
  $sql = "SELECT * FROM viajes";
  if (count($where) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where);
  }
  $sql .= " ORDER BY fecha DESC, id DESC";

  $resultado = $conexion->query($sql);

  // Aviso de cantidad de viajes por nombre (si se filtró por nombre)
  if (!empty($_GET['nombre'])):
      $nombreFiltro = $conexion->real_escape_string($_GET['nombre']);
      $sqlContar = "SELECT COUNT(*) AS total FROM viajes WHERE nombre = '$nombreFiltro'";
      $resContar = $conexion->query($sqlContar);
      $totalViajes = $resContar ? (int)$resContar->fetch_assoc()['total'] : 0;
  ?>
    <div class="alert alert-info">
      <strong><?= htmlspecialchars($_GET['nombre']) ?></strong> ha hecho <b><?= $totalViajes ?></b> viajes.
    </div>
  <?php endif; ?>

  <!-- Listado -->
  <div class="card shadow">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
      <h3 class="mb-0">Listado de Viajes</h3>
      <a href="crear.php" class="btn btn-success">➕ Nuevo Viaje</a>
    </div>
    <div class="card-body">
      <form method="post" action="acciones_multiple.php">
        <div class="table-responsive">
          <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
              <tr>
                <th style="width:32px;"><input type="checkbox" id="selectAll"></th>
                <th>ID</th>
                <th>Nombre</th>
                <th>Cédula</th>
                <th>Fecha</th>
                <th>Ruta</th>
                <th>Vehículo</th>
                <th>Empresa</th>
                <th>Imagen</th>
                <th style="width:160px;">Acciones</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($resultado && $resultado->num_rows > 0): ?>
              <?php while($row = $resultado->fetch_assoc()): ?>
                <tr>
                  <td><input type="checkbox" name="ids[]" value="<?= (int)$row['id']; ?>"></td>
                  <td><?= (int)$row['id']; ?></td>
                  <td><?= htmlspecialchars($row['nombre']); ?></td>
                  <td><?= htmlspecialchars($row['cedula']); ?></td>
                  <td><?= htmlspecialchars($row['fecha']); ?></td>
                  <td><?= htmlspecialchars($row['ruta']); ?></td>
                  <td><?= htmlspecialchars($row['tipo_vehiculo']); ?></td>
                  <td><?= htmlspecialchars($row['empresa'] ?? '—'); ?></td>
                  <td>
                    <?php if(!empty($row['imagen'])): ?>
                      <a href="#" data-bs-toggle="modal" data-bs-target="#imgModal<?= (int)$row['id']; ?>">
                        <img src="uploads/<?= htmlspecialchars($row['imagen']); ?>" width="70" class="rounded">
                      </a>
                      <div class="modal fade" id="imgModal<?= (int)$row['id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                          <div class="modal-content">
                            <div class="modal-body text-center">
                              <img src="uploads/<?= htmlspecialchars($row['imagen']); ?>" class="img-fluid rounded">
                            </div>
                          </div>
                        </div>
                      </div>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </td>
                  <td>
                    <a href="editar.php?id=<?= (int)$row['id']; ?>" class="btn btn-warning btn-sm">✏ Editar</a>
                    <a href="eliminar.php?id=<?= (int)$row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Seguro de eliminar?')">🗑 Eliminar</a>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="10" class="text-center py-4">No se encontraron resultados.</td>
              </tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="mt-3 d-flex gap-2">
          <button type="submit" name="accion" value="eliminar" class="btn btn-danger">🗑 Eliminar Seleccionados</button>
          <button type="submit" name="accion" value="editar" class="btn btn-warning">✏ Editar Seleccionados</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById("selectAll").onclick = function() {
  const checkboxes = document.getElementsByName("ids[]");
  for (const checkbox of checkboxes) {
    checkbox.checked = this.checked;
  }
};
</script>
</body>
</html>
