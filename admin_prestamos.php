<?php
/*********************************************************
 * admin_prestamos.php — CRUD + Tarjetas
 *********************************************************/
include("nav.php");

// ======= CONFIG =======
define('DB_HOST', 'mysql.hostinger.com');
define('DB_USER', 'u648222299_keboco5');
define('DB_PASS', 'Bucaramanga3011');
define('DB_NAME', 'u648222299_viajes');
const UPLOAD_DIR = __DIR__ . '/uploads/';
const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;
const DEFAULT_OWNER_CHAT_ID = 6133806918;

if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0775, true);

function db(): mysqli {
  $m = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  if ($m->connect_errno) exit("Error DB: ".$m->connect_error);
  $m->set_charset('utf8mb4');
  return $m;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES,'UTF-8'); }
function money($n){ return number_format((float)$n,0,',','.'); }

function go($url){
  if (!headers_sent()){
    header("Location: ".$url, true, 302);
    exit;
  }
  echo "<script>location.replace('{$url}');</script>";
  exit;
}

function mbnorm($s){ return mb_strtolower(trim((string)$s),'UTF-8'); }
function mbtitle($s){ return function_exists('mb_convert_case') ? mb_convert_case((string)$s, MB_CASE_TITLE, 'UTF-8') : ucwords(strtolower((string)$s)); }

// ===== PROCESAR MODALES ANTES QUE NADA =====
// Guardar nuevo deudor desde modal
if (isset($_POST['modal_nuevo_deudor'])) {
  $nombre = trim($_POST['nuevo_deudor_nombre'] ?? '');
  $modo_especial = isset($_POST['modo_especial_actual']) ? (int)$_POST['modo_especial_actual'] : 0;
  
  if ($nombre !== '') {
    $conn = db();
    $stmt = $conn->prepare("INSERT IGNORE INTO deudores_admin (owner_chat_id, nombre) VALUES (?, ?)");
    $stmt->bind_param("is", DEFAULT_OWNER_CHAT_ID, $nombre);
    $stmt->execute();
    $stmt->close();
    $conn->close();
  }
  go("?action=new&view=cards&modo_especial=" . $modo_especial);
  exit;
}

// Guardar nuevo prestamista desde modal
if (isset($_POST['modal_nuevo_prestamista'])) {
  $nombre = trim($_POST['nuevo_prestamista_nombre'] ?? '');
  $modo_especial = isset($_POST['modo_especial_actual']) ? (int)$_POST['modo_especial_actual'] : 0;
  
  if ($nombre !== '') {
    $conn = db();
    $stmt = $conn->prepare("INSERT IGNORE INTO prestamistas_admin (owner_chat_id, nombre) VALUES (?, ?)");
    $stmt->bind_param("is", DEFAULT_OWNER_CHAT_ID, $nombre);
    $stmt->execute();
    $stmt->close();
    $conn->close();
  }
  go("?action=new&view=cards&modo_especial=" . $modo_especial);
  exit;
}

$action = $_GET['action'] ?? 'list';
$view   = 'cards';
$id = (int)($_GET['id'] ?? 0);
$modo_especial = isset($_GET['modo_especial']) ? (int)$_GET['modo_especial'] : (isset($_POST['modo_especial']) ? (int)$_POST['modo_especial'] : 0);

// ===== Upload helper =====
function save_image($file): ?string {
  if (empty($file) || ($file['error']??4) === 4) return null;
  if ($file['error'] !== UPLOAD_ERR_OK) return null;
  if ($file['size'] > MAX_UPLOAD_BYTES) return null;
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->file($file['tmp_name']);
  $ext = match ($mime) {
    'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif',
    default=>null
  };
  if(!$ext) return null;
  $name = time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
  if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR.$name)) return null;
  return $name;
}

// ===== CRUD =====
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $deudor_id = (int)($_POST['deudor_id'] ?? 0);
  $prestamista_id = (int)($_POST['prestamista_id'] ?? 0);
  $monto = trim($_POST['monto'] ?? '');
  $fecha = trim($_POST['fecha'] ?? '');
  $img = save_image($_FILES['imagen'] ?? null);
  
  $conn = db();
  $deudor_nombre = '';
  $stmt = $conn->prepare("SELECT nombre FROM deudores_admin WHERE id = ?");
  $stmt->bind_param("i", $deudor_id);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) $deudor_nombre = $row['nombre'];
  $stmt->close();
  
  $prestamista_nombre = '';
  $stmt = $conn->prepare("SELECT nombre FROM prestamistas_admin WHERE id = ?");
  $stmt->bind_param("i", $prestamista_id);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) $prestamista_nombre = $row['nombre'];
  $stmt->close();
  $conn->close();
  
  if ($deudor_nombre && $prestamista_nombre && is_numeric($monto) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    $c = db();
    $st = $c->prepare("INSERT INTO prestamos (deudor, prestamista, monto, fecha, imagen, created_at) VALUES (?,?,?,?,?,NOW())");
    $st->bind_param("ssdss", $deudor_nombre, $prestamista_nombre, $monto, $fecha, $img);
    $st->execute();
    $st->close();
    $c->close();
    go("?msg=creado&view=cards&modo_especial=" . $modo_especial);
  } else {
    $err = "Complete todos los campos correctamente.";
  }
}

if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
  $deudor_id = (int)($_POST['deudor_id'] ?? 0);
  $prestamista_id = (int)($_POST['prestamista_id'] ?? 0);
  $monto = trim($_POST['monto'] ?? '');
  $fecha = trim($_POST['fecha'] ?? '');
  $keep = isset($_POST['keep']) ? 1 : 0;
  $img = save_image($_FILES['imagen'] ?? null);
  
  $conn = db();
  $deudor_nombre = '';
  $stmt = $conn->prepare("SELECT nombre FROM deudores_admin WHERE id = ?");
  $stmt->bind_param("i", $deudor_id);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) $deudor_nombre = $row['nombre'];
  $stmt->close();
  
  $prestamista_nombre = '';
  $stmt = $conn->prepare("SELECT nombre FROM prestamistas_admin WHERE id = ?");
  $stmt->bind_param("i", $prestamista_id);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) $prestamista_nombre = $row['nombre'];
  $stmt->close();
  $conn->close();
  
  if ($deudor_nombre && $prestamista_nombre && is_numeric($monto) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    $c = db();
    if ($img) {
      $st = $c->prepare("UPDATE prestamos SET deudor=?, prestamista=?, monto=?, fecha=?, imagen=? WHERE id=?");
      $st->bind_param("ssdssi", $deudor_nombre, $prestamista_nombre, $monto, $fecha, $img, $id);
    } else {
      if ($keep) {
        $st = $c->prepare("UPDATE prestamos SET deudor=?, prestamista=?, monto=?, fecha=? WHERE id=?");
        $st->bind_param("ssdsi", $deudor_nombre, $prestamista_nombre, $monto, $fecha, $id);
      } else {
        $st = $c->prepare("UPDATE prestamos SET deudor=?, prestamista=?, monto=?, fecha=?, imagen=NULL WHERE id=?");
        $st->bind_param("ssdsi", $deudor_nombre, $prestamista_nombre, $monto, $fecha, $id);
      }
    }
    $st->execute();
    $st->close();
    $c->close();
    go("?msg=editado&view=cards&modo_especial=" . $modo_especial);
  } else {
    $err = "Complete todos los campos correctamente.";
  }
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
  $c = db();
  $st = $c->prepare("SELECT imagen FROM prestamos WHERE id=?");
  $st->bind_param("i", $id);
  $st->execute();
  $st->bind_result($img);
  $st->fetch();
  $st->close();
  if ($img && is_file(UPLOAD_DIR . $img)) @unlink(UPLOAD_DIR . $img);
  $st = $c->prepare("DELETE FROM prestamos WHERE id=?");
  $st->bind_param("i", $id);
  $st->execute();
  $st->close();
  $c->close();
  go("?msg=eliminado&view=cards&modo_especial=" . $modo_especial);
}

// ===== Cargar datos para selects =====
$conn = db();
$todos_deudores = [];
$res = $conn->query("SELECT id, nombre FROM deudores_admin WHERE owner_chat_id = " . DEFAULT_OWNER_CHAT_ID . " ORDER BY nombre");
while ($row = $res->fetch_assoc()) {
  $todos_deudores[] = $row;
}

$todos_prestamistas = [];
$res = $conn->query("SELECT id, nombre FROM prestamistas_admin WHERE owner_chat_id = " . DEFAULT_OWNER_CHAT_ID . " ORDER BY nombre");
while ($row = $res->fetch_assoc()) {
  $todos_prestamistas[] = $row;
}
$conn->close();
?>

<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Préstamos | Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; }
    body { font-family: system-ui, sans-serif; background: #f3f4f6; padding: 20px; }
    .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 12px; font-weight: 600; border: none; cursor: pointer; background: #2563eb; color: white; }
    .btn-gray { background: #6b7280; }
    .btn-yellow { background: #f59e0b; }
    .btn-red { background: #dc2626; }
    .btn-small { padding: 6px 12px; font-size: 12px; }
    .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .field { display: flex; flex-direction: column; gap: 6px; }
    input, select { padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 12px; font-size: 14px; }
    .row { display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end; }
    .title { font-size: 20px; font-weight: 700; margin-bottom: 16px; }
    .subtitle { color: #6b7280; font-size: 13px; }
    .msg { background: #dcfce7; color: #166534; padding: 10px 16px; border-radius: 12px; margin-bottom: 16px; }
    .error { background: #fee2e2; color: #991b1b; padding: 10px 16px; border-radius: 12px; margin-bottom: 16px; }
    .grid-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
    .prestamo-card { background: #f9fafb; border-radius: 16px; padding: 16px; border: 1px solid #e5e7eb; }
    .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
    .modal-content { background: white; border-radius: 20px; padding: 24px; width: 90%; max-width: 400px; }
    .tabs { display: flex; gap: 12px; margin-bottom: 20px; }
    .tabs a { background: #e5e7eb; color: #111; padding: 10px 16px; border-radius: 12px; font-weight: 600; text-decoration: none; }
    .tabs a.active { background: #2563eb; color: white; }
    .toolbar { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
    .chip { display: inline-block; background: #eef2ff; padding: 4px 10px; border-radius: 20px; font-size: 12px; }
    .badge { background: #111; color: white; border-radius: 20px; padding: 4px 10px; font-size: 12px; }
    .select2-container .select2-selection { border-radius: 12px !important; padding: 4px !important; height: 42px !important; }
  </style>
</head>
<body>

<div class="tabs">
  <a href="?view=cards" class="<?= $view === 'cards' ? 'active' : '' ?>">📇 Tarjetas</a>
  <a href="?action=new&view=cards&modo_especial=<?= $modo_especial ?>" style="margin-left:auto">➕ Crear Préstamo</a>
</div>

<?php if (isset($_GET['msg'])): ?>
  <div class="msg"><?= match($_GET['msg']) {
    'creado' => '✅ Préstamo creado correctamente',
    'editado' => '✅ Préstamo actualizado',
    'eliminado' => '✅ Préstamo eliminado',
    default => 'Operación realizada'
  } ?></div>
<?php endif; ?>

<?php if ($action === 'new' || ($action === 'edit' && $id > 0)): 
  $row = ['deudor' => '', 'prestamista' => '', 'monto' => '', 'fecha' => '', 'imagen' => null];
  $deudor_seleccionado = 0;
  $prestamista_seleccionado = 0;
  
  if ($action === 'edit') {
    $c = db();
    $st = $c->prepare("SELECT deudor, prestamista, monto, fecha, imagen FROM prestamos WHERE id = ?");
    $st->bind_param("i", $id);
    $st->execute();
    $res = $st->get_result();
    $row = $res->fetch_assoc() ?: $row;
    $st->close();
    
    // Buscar IDs
    $stmt = $c->prepare("SELECT id FROM deudores_admin WHERE owner_chat_id = ? AND nombre = ?");
    $stmt->bind_param("is", DEFAULT_OWNER_CHAT_ID, $row['deudor']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($d = $res->fetch_assoc()) $deudor_seleccionado = $d['id'];
    $stmt->close();
    
    $stmt = $c->prepare("SELECT id FROM prestamistas_admin WHERE owner_chat_id = ? AND nombre = ?");
    $stmt->bind_param("is", DEFAULT_OWNER_CHAT_ID, $row['prestamista']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($p = $res->fetch_assoc()) $prestamista_seleccionado = $p['id'];
    $stmt->close();
    $c->close();
  }
?>
  <!-- Modal Nuevo Deudor -->
  <div id="modalDeudor" class="modal">
    <div class="modal-content">
      <h3 style="margin-bottom: 16px;">Nuevo Deudor</h3>
      <form method="post">
        <input type="hidden" name="modal_nuevo_deudor" value="1">
        <input type="hidden" name="modo_especial_actual" value="<?= $modo_especial ?>">
        <input type="text" name="nuevo_deudor_nombre" placeholder="Nombre del deudor" style="width: 100%; margin-bottom: 16px; padding: 10px; border-radius: 12px; border: 1px solid #ddd;" required>
        <div style="display: flex; gap: 12px;">
          <button type="submit" class="btn">Guardar</button>
          <button type="button" class="btn btn-gray" onclick="document.getElementById('modalDeudor').style.display='none'">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
  
  <!-- Modal Nuevo Prestamista -->
  <div id="modalPrestamista" class="modal">
    <div class="modal-content">
      <h3 style="margin-bottom: 16px;">Nuevo Prestamista</h3>
      <form method="post">
        <input type="hidden" name="modal_nuevo_prestamista" value="1">
        <input type="hidden" name="modo_especial_actual" value="<?= $modo_especial ?>">
        <input type="text" name="nuevo_prestamista_nombre" placeholder="Nombre del prestamista" style="width: 100%; margin-bottom: 16px; padding: 10px; border-radius: 12px; border: 1px solid #ddd;" required>
        <div style="display: flex; gap: 12px;">
          <button type="submit" class="btn">Guardar</button>
          <button type="button" class="btn btn-gray" onclick="document.getElementById('modalPrestamista').style.display='none'">Cancelar</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="title"><?= $action === 'new' ? 'Nuevo Préstamo' : 'Editar Préstamo #' . h($id) ?></div>
    <?php if (!empty($err)): ?>
      <div class="error"><?= h($err) ?></div>
    <?php endif; ?>
    
    <form method="post" enctype="multipart/form-data" action="?action=<?= $action === 'new' ? 'create' : 'edit&id=' . $id ?>&view=cards&modo_especial=<?= $modo_especial ?>">
      <div class="row">
        <div class="field" style="flex: 1;">
          <label>Deudor *</label>
          <div style="display: flex; gap: 8px;">
            <select name="deudor_id" id="deudorSelect2" style="flex: 1;" required>
              <option value="0">-- Seleccionar deudor --</option>
              <?php foreach ($todos_deudores as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $deudor_seleccionado == $d['id'] ? 'selected' : '' ?>><?= h($d['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-yellow" id="btnNuevoDeudor" style="padding: 10px 16px;">➕</button>
          </div>
        </div>
        
        <div class="field" style="flex: 1;">
          <label>Prestamista *</label>
          <div style="display: flex; gap: 8px;">
            <select name="prestamista_id" id="prestamistaSelect2" style="flex: 1;" required>
              <option value="0">-- Seleccionar prestamista --</option>
              <?php foreach ($todos_prestamistas as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $prestamista_seleccionado == $p['id'] ? 'selected' : '' ?>><?= h($p['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-yellow" id="btnNuevoPrestamista" style="padding: 10px 16px;">➕</button>
          </div>
        </div>
        
        <div class="field">
          <label>Monto *</label>
          <input type="number" name="monto" step="1" required value="<?= h($row['monto']) ?>">
        </div>
        
        <div class="field">
          <label>Fecha *</label>
          <input type="date" name="fecha" required value="<?= h($row['fecha']) ?>">
        </div>
        
        <div class="field" style="flex: 1;">
          <label>Imagen (opcional)</label>
          <?php if ($action === 'edit' && $row['imagen']): ?>
            <div><img src="uploads/<?= h($row['imagen']) ?>" style="max-width: 100px; border-radius: 8px;"></div>
            <label><input type="checkbox" name="keep" checked> Mantener imagen actual</label>
          <?php endif; ?>
          <input type="file" name="imagen" accept="image/*">
        </div>
      </div>
      
      <div style="display: flex; gap: 12px; margin-top: 24px;">
        <button type="submit" class="btn">💾 Guardar</button>
        <a href="?view=cards&modo_especial=<?= $modo_especial ?>" class="btn btn-gray">Cancelar</a>
      </div>
    </form>
  </div>

<?php else: 
  // LISTADO DE TARJETAS (simplificado pero funcional)
  $conn = db();
  $result = $conn->query("SELECT id, deudor, prestamista, monto, fecha, imagen, created_at, pagado FROM prestamos ORDER BY id DESC LIMIT 50");
?>
  <div class="card">
    <div class="title">📋 Listado de Préstamos</div>
    <div class="subtitle" style="margin-bottom: 16px;">Total: <?= $result->num_rows ?> préstamos</div>
    
    <div class="grid-cards">
      <?php while ($row = $result->fetch_assoc()): ?>
        <div class="prestamo-card">
          <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
            <strong style="color: #6b7280;">#<?= $row['id'] ?></strong>
            <?php if ($row['pagado']): ?>
              <span style="background: #10b981; color: white; padding: 2px 10px; border-radius: 20px; font-size: 11px;">✅ Pagado</span>
            <?php endif; ?>
          </div>
          
          <?php if ($row['imagen']): ?>
            <img src="uploads/<?= h($row['imagen']) ?>" style="width: 100%; height: 140px; object-fit: cover; border-radius: 12px; margin-bottom: 12px;">
          <?php endif; ?>
          
          <div style="font-weight: 700; font-size: 18px;"><?= h($row['deudor']) ?></div>
          <div style="font-size: 13px; color: #6b7280;">Prestamista: <?= h($row['prestamista']) ?></div>
          
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 12px;">
            <div><span style="font-size: 11px; color: #6b7280;">Monto</span><br><strong>$ <?= money($row['monto']) ?></strong></div>
            <div><span style="font-size: 11px; color: #6b7280;">Fecha</span><br><strong><?= $row['fecha'] ?></strong></div>
          </div>
          
          <div style="display: flex; gap: 8px; margin-top: 12px;">
            <a href="?action=edit&id=<?= $row['id'] ?>&modo_especial=<?= $modo_especial ?>" class="btn btn-gray btn-small" style="background: #6b7280;">✏️ Editar</a>
            <form method="post" action="?action=delete&id=<?= $row['id'] ?>&modo_especial=<?= $modo_especial ?>" onsubmit="return confirm('¿Eliminar este préstamo?')" style="display: inline;">
              <button type="submit" class="btn btn-red btn-small" style="background: #dc2626;">🗑️ Eliminar</button>
            </form>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  </div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
  $('#deudorSelect2').select2({ width: '100%', placeholder: 'Buscar deudor...', allowClear: true });
  $('#prestamistaSelect2').select2({ width: '100%', placeholder: 'Buscar prestamista...', allowClear: true });
  
  $('#btnNuevoDeudor').click(function() { $('#modalDeudor').css('display', 'flex'); });
  $('#btnNuevoPrestamista').click(function() { $('#modalPrestamista').css('display', 'flex'); });
  
  window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
      event.target.style.display = 'none';
    }
  };
});
</script>
</body>
</html>