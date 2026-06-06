<?php
/*********************************************************
 * admin_prestamos.php — CRUD + Tarjetas
 * - Filtro dinámico: deudores según empresa seleccionada
 * - Toggle switch: Modo 8% por días exactos
 * - Desglose por prestamista (solo en modo especial)
 * - Select2 con búsqueda y creación para Deudor/Prestamista
 *********************************************************/
include("nav.php");

// ======= CONFIG =======
define('DB_HOST', 'mysql.hostinger.com');
define('DB_USER', 'u648222299_keboco5');
define('DB_PASS', 'Bucaramanga3011');
define('DB_NAME', 'u648222299_viajes');
const UPLOAD_DIR = __DIR__ . '/uploads/';
const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

// URL absoluta para volver a Tarjetas después del bulk update
const BASE_URL = 'https://asociacion.asociaciondetransportistaszonanorte.io/tele/admin_prestamos.php';
const DEFAULT_OWNER_CHAT_ID = 6133806918; // Owner fijo para nuevos deudores/prestamistas

if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0775, true);

// ===== Helpers =====
function db(): mysqli {
  $m = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  if ($m->connect_errno) exit("Error DB: ".$m->connect_error);
  $m->set_charset('utf8mb4');
  return $m;
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES,'UTF-8'); }
function money($n){ return number_format((float)$n,0,',','.'); }

/* Redirección robusta: si ya se enviaron headers, usa JS + meta */
function go($url){
  if (!headers_sent()){
    header("Location: ".$url, true, 302);
    exit;
  }
  $u = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
  echo "<!doctype html><html><head><meta http-equiv='refresh' content='0;url={$u}'><script>location.replace('{$u}');</script></head><body><a href='{$u}'>Ir</a></body></html>";
  exit;
}

function mbnorm($s){ return mb_strtolower(trim((string)$s),'UTF-8'); }
function mbtitle($s){ return function_exists('mb_convert_case') ? mb_convert_case((string)$s, MB_CASE_TITLE, 'UTF-8') : ucwords(strtolower((string)$s)); }

// ===== PRIMERO: Manejar todas las peticiones AJAX antes que cualquier otra cosa =====

// AJAX para buscar deudores
if (isset($_GET['ajax']) && $_GET['ajax'] === 'deudores' && isset($_GET['q'])) {
  header('Content-Type: application/json');
  $search = trim($_GET['q']);
  $conn = db();
  $results = [];
  
  if ($search !== '') {
    $stmt = $conn->prepare("SELECT id, nombre FROM deudores_admin WHERE owner_chat_id = ? AND LOWER(nombre) LIKE LOWER(?) ORDER BY nombre LIMIT 20");
    $like = "%{$search}%";
    $stmt->bind_param("is", DEFAULT_OWNER_CHAT_ID, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $results[] = ['id' => $row['id'], 'text' => $row['nombre']];
    }
    $stmt->close();
  } else {
    $stmt = $conn->prepare("SELECT id, nombre FROM deudores_admin WHERE owner_chat_id = ? ORDER BY nombre LIMIT 30");
    $stmt->bind_param("i", DEFAULT_OWNER_CHAT_ID);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $results[] = ['id' => $row['id'], 'text' => $row['nombre']];
    }
    $stmt->close();
  }
  $conn->close();
  echo json_encode(['results' => $results]);
  exit;
}

// AJAX para crear nuevo deudor
if (isset($_GET['ajax']) && $_GET['ajax'] === 'crear_deudor' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
  $nombre = trim($_POST['nombre'] ?? '');
  if ($nombre === '') {
    echo json_encode(['success' => false, 'error' => 'Nombre vacío']);
    exit;
  }
  $conn = db();
  // Verificar si ya existe
  $stmt = $conn->prepare("SELECT id FROM deudores_admin WHERE owner_chat_id = ? AND LOWER(nombre) = LOWER(?)");
  $stmt->bind_param("is", DEFAULT_OWNER_CHAT_ID, $nombre);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) {
    echo json_encode(['success' => true, 'id' => $row['id'], 'text' => $nombre]);
    $stmt->close();
    $conn->close();
    exit;
  }
  $stmt->close();
  
  // Insertar nuevo
  $stmt = $conn->prepare("INSERT INTO deudores_admin (owner_chat_id, nombre) VALUES (?, ?)");
  $stmt->bind_param("is", DEFAULT_OWNER_CHAT_ID, $nombre);
  if ($stmt->execute()) {
    $newId = $conn->insert_id;
    echo json_encode(['success' => true, 'id' => $newId, 'text' => $nombre]);
  } else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
  }
  $stmt->close();
  $conn->close();
  exit;
}

// AJAX para buscar prestamistas
if (isset($_GET['ajax']) && $_GET['ajax'] === 'prestamistas' && isset($_GET['q'])) {
  header('Content-Type: application/json');
  $search = trim($_GET['q']);
  $conn = db();
  $results = [];
  
  if ($search !== '') {
    $stmt = $conn->prepare("SELECT id, nombre FROM prestamistas_admin WHERE owner_chat_id = ? AND LOWER(nombre) LIKE LOWER(?) ORDER BY nombre LIMIT 20");
    $like = "%{$search}%";
    $stmt->bind_param("is", DEFAULT_OWNER_CHAT_ID, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $results[] = ['id' => $row['id'], 'text' => $row['nombre']];
    }
    $stmt->close();
  } else {
    $stmt = $conn->prepare("SELECT id, nombre FROM prestamistas_admin WHERE owner_chat_id = ? ORDER BY nombre LIMIT 30");
    $stmt->bind_param("i", DEFAULT_OWNER_CHAT_ID);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $results[] = ['id' => $row['id'], 'text' => $row['nombre']];
    }
    $stmt->close();
  }
  $conn->close();
  echo json_encode(['results' => $results]);
  exit;
}

// AJAX para crear nuevo prestamista
if (isset($_GET['ajax']) && $_GET['ajax'] === 'crear_prestamista' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
  $nombre = trim($_POST['nombre'] ?? '');
  if ($nombre === '') {
    echo json_encode(['success' => false, 'error' => 'Nombre vacío']);
    exit;
  }
  $conn = db();
  // Verificar si ya existe
  $stmt = $conn->prepare("SELECT id FROM prestamistas_admin WHERE owner_chat_id = ? AND LOWER(nombre) = LOWER(?)");
  $stmt->bind_param("is", DEFAULT_OWNER_CHAT_ID, $nombre);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) {
    echo json_encode(['success' => true, 'id' => $row['id'], 'text' => $nombre]);
    $stmt->close();
    $conn->close();
    exit;
  }
  $stmt->close();
  
  // Insertar nuevo
  $stmt = $conn->prepare("INSERT INTO prestamistas_admin (owner_chat_id, nombre) VALUES (?, ?)");
  $stmt->bind_param("is", DEFAULT_OWNER_CHAT_ID, $nombre);
  if ($stmt->execute()) {
    $newId = $conn->insert_id;
    echo json_encode(['success' => true, 'id' => $newId, 'text' => $nombre]);
  } else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
  }
  $stmt->close();
  $conn->close();
  exit;
}

$action = $_GET['action'] ?? 'list';
$view   = 'cards'; // Solo tarjetas
$id = (int)($_GET['id'] ?? 0);

// Modo de cálculo especial (8% por días exactos) - se pasa por URL o por POST
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

/* ===== Acción: Edición en Lote desde TARJETAS ===== */
if ($action==='bulk_update' && $_SERVER['REQUEST_METHOD']==='POST'){
  $ids = $_POST['ids'] ?? [];
  if (!is_array($ids)) $ids = [];
  $ids = array_values(array_unique(array_map(fn($v)=> (int)$v, $ids)));

  if (!$ids) {
    go(BASE_URL.'?view=cards&msg=noselect');
  }

  $new_deudor      = trim($_POST['new_deudor'] ?? '');
  $new_prestamista = trim($_POST['new_prestamista'] ?? '');
  $new_monto_raw   = trim($_POST['new_monto'] ?? '');
  $new_fecha       = trim($_POST['new_fecha'] ?? '');

  $sets   = [];
  $types  = '';
  $values = [];

  if ($new_deudor !== '') {
    $sets[] = "deudor=?";
    $types .= 's';
    $values[] = $new_deudor;
  }
  if ($new_prestamista !== '') {
    $sets[] = "prestamista=?";
    $types .= 's';
    $values[] = $new_prestamista;
  }
  if ($new_monto_raw !== '' && is_numeric($new_monto_raw)) {
    $sets[] = "monto=?";
    $types .= 'd';
    $values[] = (float)$new_monto_raw;
  }
  if ($new_fecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_fecha)) {
    $sets[] = "fecha=?";
    $types .= 's';
    $values[] = $new_fecha;
  }

  if (!$sets) {
    go(BASE_URL.'?view=cards&msg=noupdate');
  }

  $phIds = implode(',', array_fill(0, count($ids), '?'));
  $types .= str_repeat('i', count($ids));
  $values = array_merge($values, $ids);

  $c = db();
  $sql = "UPDATE prestamos SET ".implode(',', $sets)." WHERE id IN ($phIds)";
  $st  = $c->prepare($sql);
  $st->bind_param($types, ...$values);
  $ok = $st->execute();
  $st->close(); $c->close();

  $msg = $ok ? 'bulkok' : 'bulkoops';
  go(BASE_URL.'?view=cards&msg='.$msg.'&modo_especial='.$modo_especial);
}

/* ===== Acción masiva "Préstamo pagado" ===== */
if ($action==='bulk_mark_paid' && $_SERVER['REQUEST_METHOD']==='POST'){
  $ids = $_POST['ids'] ?? [];
  if (!is_array($ids)) $ids = [];
  $ids = array_values(array_unique(array_map(fn($v)=> (int)$v, $ids)));

  if (!$ids) {
    go(BASE_URL.'?view=cards&msg=noselect');
  }

  $c = db();
  $ok = true;

  foreach (array_chunk($ids, 200) as $chunk) {
    $ph    = implode(',', array_fill(0, count($chunk), '?'));
    $types = str_repeat('i', count($chunk));
    $sql   = "UPDATE prestamos 
              SET pagado = 1, pagado_at = NOW() 
              WHERE id IN ($ph) AND (pagado IS NULL OR pagado = 0)";
    $st = $c->prepare($sql);
    if (!$st) { $ok = false; break; }
    $st->bind_param($types, ...$chunk);
    if (!$st->execute()) { $ok = false; }
    $st->close();
    if (!$ok) break;
  }

  $c->close();
  $msg = $ok ? 'bulkpaid' : 'bulkpaidoops';
  go(BASE_URL.'?view=cards&msg='.$msg.'&modo_especial='.$modo_especial);
}

/* ===== Acción masiva "Marcar como NO pagado" ===== */
if ($action==='bulk_mark_unpaid' && $_SERVER['REQUEST_METHOD']==='POST'){
  $ids = $_POST['ids'] ?? [];
  if (!is_array($ids)) $ids = [];
  $ids = array_values(array_unique(array_map(fn($v)=> (int)$v, $ids)));

  if (!$ids) {
    go(BASE_URL.'?view=cards&msg=noselect');
  }

  $c = db();
  $ok = true;

  foreach (array_chunk($ids, 200) as $chunk) {
    $ph    = implode(',', array_fill(0, count($chunk), '?'));
    $types = str_repeat('i', count($chunk));
    $sql   = "UPDATE prestamos 
              SET pagado = 0, pagado_at = NULL 
              WHERE id IN ($ph) AND pagado = 1";
    $st = $c->prepare($sql);
    if (!$st) { $ok = false; break; }
    $st->bind_param($types, ...$chunk);
    if (!$st->execute()) { $ok = false; }
    $st->close();
    if (!$ok) break;
  }

  $c->close();
  $msg = $ok ? 'bulkunpaid' : 'bulkunpaidoops';
  go(BASE_URL.'?view=cards&msg='.$msg.'&modo_especial='.$modo_especial);
}

/* ===== CRUD ===== */
if ($action==='create' && $_SERVER['REQUEST_METHOD']==='POST'){
  // Obtener el nombre real del deudor y prestamista (no el ID del select2)
  $deudor_id = (int)($_POST['deudor_id'] ?? 0);
  $prestamista_id = (int)($_POST['prestamista_id'] ?? 0);
  $deudor_nombre = trim($_POST['deudor_nombre'] ?? '');
  $prestamista_nombre = trim($_POST['prestamista_nombre'] ?? '');
  
  // Si viene ID, buscar el nombre en la tabla correspondiente
  $conn = db();
  if ($deudor_id > 0) {
    $stmt = $conn->prepare("SELECT nombre FROM deudores_admin WHERE id = ?");
    $stmt->bind_param("i", $deudor_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
      $deudor_nombre = $row['nombre'];
    }
    $stmt->close();
  }
  
  if ($prestamista_id > 0) {
    $stmt = $conn->prepare("SELECT nombre FROM prestamistas_admin WHERE id = ?");
    $stmt->bind_param("i", $prestamista_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
      $prestamista_nombre = $row['nombre'];
    }
    $stmt->close();
  }
  $conn->close();
  
  $monto = trim($_POST['monto']??'');
  $fecha = trim($_POST['fecha']??'');
  $img = save_image($_FILES['imagen']??null);

  if ($deudor_nombre && $prestamista_nombre && is_numeric($monto) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)){
    $c=db();
    $st=$c->prepare("INSERT INTO prestamos (deudor,prestamista,monto,fecha,imagen,created_at) VALUES (?,?,?,?,?,NOW())");
    $st->bind_param("ssdss",$deudor_nombre,$prestamista_nombre,$monto,$fecha,$img);
    $st->execute();
    $st->close(); $c->close();
    go('?msg=creado&view='.urlencode($view).'&modo_especial='.$modo_especial);
  } else {
    $err="Completa todos los campos correctamente.";
  }
}

if ($action==='edit' && $_SERVER['REQUEST_METHOD']==='POST' && $id>0){
  $deudor_id = (int)($_POST['deudor_id'] ?? 0);
  $prestamista_id = (int)($_POST['prestamista_id'] ?? 0);
  $deudor_nombre = trim($_POST['deudor_nombre'] ?? '');
  $prestamista_nombre = trim($_POST['prestamista_nombre'] ?? '');
  
  $conn = db();
  if ($deudor_id > 0) {
    $stmt = $conn->prepare("SELECT nombre FROM deudores_admin WHERE id = ?");
    $stmt->bind_param("i", $deudor_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
      $deudor_nombre = $row['nombre'];
    }
    $stmt->close();
  }
  
  if ($prestamista_id > 0) {
    $stmt = $conn->prepare("SELECT nombre FROM prestamistas_admin WHERE id = ?");
    $stmt->bind_param("i", $prestamista_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
      $prestamista_nombre = $row['nombre'];
    }
    $stmt->close();
  }
  $conn->close();
  
  $monto=trim($_POST['monto']??'');
  $fecha=trim($_POST['fecha']??'');
  $keep = isset($_POST['keep']) ? 1:0;
  $img = save_image($_FILES['imagen']??null);

  if ($deudor_nombre && $prestamista_nombre && is_numeric($monto) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)){
    $c=db();
    if ($img){
      $st=$c->prepare("UPDATE prestamos SET deudor=?,prestamista=?,monto=?,fecha=?,imagen=? WHERE id=?");
      $st->bind_param("ssdssi",$deudor_nombre,$prestamista_nombre,$monto,$fecha,$img,$id);
    } else {
      if ($keep){
        $st=$c->prepare("UPDATE prestamos SET deudor=?,prestamista=?,monto=?,fecha=? WHERE id=?");
        $st->bind_param("ssdsi",$deudor_nombre,$prestamista_nombre,$monto,$fecha,$id);
      } else {
        $st=$c->prepare("UPDATE prestamos SET deudor=?,prestamista=?,monto=?,fecha=?,imagen=NULL WHERE id=?");
        $st->bind_param("ssdsi",$deudor_nombre,$prestamista_nombre,$monto,$fecha,$id);
      }
    }
    $st->execute();
    $st->close(); $c->close();
    go('?msg=editado&view='.urlencode($view).'&modo_especial='.$modo_especial);
  } else {
    $err="Completa todos los campos correctamente.";
  }
}

if ($action==='delete' && $_SERVER['REQUEST_METHOD']==='POST' && $id>0){
  $c=db();
  $st=$c->prepare("SELECT imagen FROM prestamos WHERE id=?");
  $st->bind_param("i",$id);
  $st->execute();
  $st->bind_result($img);
  $st->fetch();
  $st->close();
  if ($img && is_file(UPLOAD_DIR.$img)) @unlink(UPLOAD_DIR.$img);
  $st=$c->prepare("DELETE FROM prestamos WHERE id=?");
  $st->bind_param("i",$id);
  $st->execute();
  $st->close(); $c->close();
  go('?msg=eliminado&view='.urlencode($view).'&modo_especial='.$modo_especial);
}

// ===== UI =====
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Préstamos | Admin</title>
<!-- Incluir Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
 :root{ --bg:#f6f7fb; --fg:#222; --card:#fff; --muted:#6b7280; --primary:#0b5ed7; --gray:#6c757d; --red:#dc3545; --chip:#eef2ff; }
 *{box-sizing:border-box}
 body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:22px;background:var(--bg);color:var(--fg)}
 a{text-decoration:none}
 .btn{display:inline-flex;align-items:center;gap:8px;padding:9px 12px;border-radius:12px;background:var(--primary);color:#fff;font-weight:600;border:0;cursor:pointer}
 .btn.gray{background:var(--gray)} .btn.red{background:var(--red)} .btn.small{padding:7px 10px;border-radius:10px}
 .tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
 .tabs a{background:#e5e7eb;color:#111;padding:8px 12px;border-radius:10px;font-weight:700}
 .tabs a.active{background:var(--primary);color:#fff}
 .toolbar{display:flex;gap:10px;align-items:center;margin-bottom:14px;flex-wrap:wrap}
 .msg{background:#e8f7ee;color:#196a3b;padding:8px 12px;border-radius:10px;display:inline-block}
 .error{background:#fdecec;color:#b02a37;padding:8px 12px;border-radius:10px;display:inline-block}
 .card{background:var(--card);border-radius:16px;box-shadow:0 6px 20px rgba(0,0,0,.06);padding:16px}
 .subtitle{font-size:13px;color:var(--muted)}
 .grid-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}
 .field{display:flex;flex-direction:column;gap:6px}
 input,select{padding:11px;border:1px solid #e5e7eb;border-radius:12px;background:#fff}
 input[type=file]{border:1px dashed #cbd5e1;background:#fafafa}
 .thumb{width:100%;max-height:180px;object-fit:cover;border-radius:12px;border:1px solid #eee}
 .pairs{display:grid;grid-template-columns:1fr 1fr;gap:10px}
 .pairs .item{background:#fafbff;border:1px solid #eef2ff;border-radius:12px;padding:10px}
 .pairs .k{font-size:12px;color:var(--muted)} .pairs .v{font-size:16px;font-weight:700}
 .row{display:flex;justify-content:space-between;gap:10px;align-items:center}
 .title{font-size:18px;font-weight:800}
 .chip{display:inline-block;background:var(--chip);padding:4px 8px;border-radius:999px;font-size:12px;font-weight:600}
 @media (max-width:760px){ .pairs{grid-template-columns:1fr} }

 /* NUEVO: controles de selección múltiple en tarjetas */
 .bulkbar{display:flex;gap:10px;align-items:center;margin:8px 0 0;flex-wrap:wrap}
 .bulkpanel{display:none;margin-top:10px;border:1px dashed #e5e7eb;border-radius:12px;padding:12px;background:#fafafa}
 .badge{background:#111;color:#fff;border-radius:999px;padding:2px 8px;font-size:12px;font-weight:700}
 .cardSel{display:flex;align-items:center;gap:8px;margin-bottom:6px}
 .sticky-actions{position:sticky; top:10px; align-self:flex-start}

 /* Estilos para comisiones en tarjetas */
 .card-comision { border-left: 4px solid #0b5ed7; background: #F0F9FF !important; }
 .comision-badge { background: #0b5ed7 !important; color: white !important; }
 .comision-info { background: #EAF5FF !important; border: 1px solid #BAE6FD !important; }
 .comision-text { color: #0369A1 !important; font-weight: 600; }

 /* Estilos para resumen de filtros */
 .resumen-filtro { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 16px; margin-bottom: 16px; }
 .resumen-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-top: 12px; }
 .resumen-item { background: white; border-radius: 8px; padding: 12px; text-align: center; }
 .resumen-valor { font-size: 18px; font-weight: 800; color: #0369a1; }
 .resumen-label { font-size: 12px; color: #6b7280; margin-top: 4px; }

 /* Estilos para switch de estado de pago (3 estados) */
 .switch-container { display: flex; align-items: center; gap: 8px; background: #f8f9fa; padding: 8px 12px; border-radius: 12px; border: 1px solid #e5e7eb; }
 .switch-label { font-size: 14px; font-weight: 600; color: #374151; }
 .switch-group { display:flex; gap:6px; }
 .switch-pill { display:flex; align-items:center; }
 .switch-pill input { display:none; }
 .switch-pill span { font-size:12px; padding:4px 10px; border-radius:999px; border:1px solid #e5e7eb; background:#fff; cursor:pointer; }
 .switch-pill input:checked + span { background:#0b5ed7; color:#fff; border-color:#0b5ed7; }

 /* Estilos para préstamos pagados */
 .card-pagado { border-left: 4px solid #10b981; background: #f0fdf4 !important; opacity: 0.8; }
 .pagado-badge { background: #10b981 !important; color: white !important; }
 .text-pagado { color: #065f46 !important; font-weight: 600; }

 /* Select2 personalizado */
 .select2-container { width: 100% !important; }
 .select2-selection { border: 1px solid #e5e7eb !important; border-radius: 12px !important; padding: 8px !important; height: 45px !important; }
 .select2-selection__arrow { height: 43px !important; }
 .select2-search__field { border-radius: 8px !important; padding: 6px !important; }

 /* Toggle switch para modo especial 8% por días */
 .modo-especial-container { display: flex; align-items: center; gap: 12px; background: #fef3c7; padding: 6px 16px; border-radius: 40px; border: 1px solid #fde68a; }
 .modo-especial-label { font-size: 13px; font-weight: 700; color: #92400e; }
 .toggle-switch { position: relative; display: inline-block; width: 52px; height: 26px; }
 .toggle-switch input { opacity: 0; width: 0; height: 0; }
 .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: 0.3s; border-radius: 26px; }
 .toggle-slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: 0.3s; border-radius: 50%; }
 input:checked + .toggle-slider { background-color: #f59e0b; }
 input:checked + .toggle-slider:before { transform: translateX(26px); }
 .modo-activo-badge { background: #f59e0b; color: #fff; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }

 /* Tabla desglose por prestamista */
 .desglose-prestamistas { margin-top: 16px; border-top: 1px solid #e5e7eb; padding-top: 12px; }
 .desglose-tabla { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 13px; }
 .desglose-tabla th, .desglose-tabla td { padding: 10px 8px; text-align: left; border-bottom: 1px solid #e5e7eb; }
 .desglose-tabla th { background: #f8fafc; font-weight: 700; color: #1e293b; }
 .desglose-tabla tr:hover { background: #fef3c7; }
 .desglose-tabla .total-row { background: #fef3c7; font-weight: 700; border-top: 2px solid #fde68a; }
 .desglose-titulo { font-size: 14px; font-weight: 700; color: #92400e; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
</style>
</head><body>

<div class="tabs">
  <a class="active" href="?view=cards">📇 Tarjetas</a>
  <a class="btn gray" href="?action=new&view=cards" style="margin-left:auto">➕ Crear</a>
</div>

<?php if (!empty($_GET['msg'])): ?>
  <div class="msg" style="margin-bottom:14px">
    <?php
      echo match($_GET['msg']){
        'creado'=>'Registro creado correctamente.',
        'editado'=>'Cambios guardados.',
        'eliminado'=>'Registro eliminado.',
        'pagados'=>'Marcados como pagados.',
        'nada'=>'No seleccionaste deudores.',
        'noselect'=>'No seleccionaste tarjetas.',
        'noupdate'=>'No indicaste ningún campo para editar.',
        'bulkok'=>'Actualización en lote aplicada.',
        'bulkoops'=>'Hubo un error al actualizar en lote.',
        'bulkpaid'=>'Préstamos seleccionados marcados como pagados.',
        'bulkpaidoops'=>'Hubo un error al marcar como pagados.',
        'bulkunpaid'=>'Préstamos seleccionados marcados como NO pagados.',
        'bulkunpaidoops'=>'Hubo un error al marcar como NO pagados.',
        default=>'Operación realizada.'
      };
    ?>
  </div>
<?php endif; ?>

<?php
// ====== NEW / EDIT FORMS ======
if ($action==='new' || ($action==='edit' && $id>0 && $_SERVER['REQUEST_METHOD']!=='POST')):
  $row = ['deudor'=>'','prestamista'=>'','monto'=>'','fecha'=>'','imagen'=>null];
  $deudor_id = 0;
  $prestamista_id = 0;
  
  if ($action==='edit'){
    $c=db();
    $st=$c->prepare("SELECT deudor,prestamista,monto,fecha,imagen FROM prestamos WHERE id=?");
    $st->bind_param("i",$id);
    $st->execute();
    $res=$st->get_result();
    $row=$res->fetch_assoc() ?: $row;
    $st->close();
    
    // Buscar si el deudor existe en deudores_admin
    $stmt = $c->prepare("SELECT id FROM deudores_admin WHERE owner_chat_id = ? AND LOWER(nombre) = LOWER(?)");
    $stmt->bind_param("is", DEFAULT_OWNER_CHAT_ID, $row['deudor']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($drow = $res->fetch_assoc()) {
      $deudor_id = $drow['id'];
    }
    $stmt->close();
    
    // Buscar si el prestamista existe en prestamistas_admin
    $stmt = $c->prepare("SELECT id FROM prestamistas_admin WHERE owner_chat_id = ? AND LOWER(nombre) = LOWER(?)");
    $stmt->bind_param("is", DEFAULT_OWNER_CHAT_ID, $row['prestamista']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($prow = $res->fetch_assoc()) {
      $prestamista_id = $prow['id'];
    }
    $stmt->close();
    $c->close();
  }
?>
  <div class="card">
    <div class="row" style="margin-bottom:10px">
      <div class="title"><?= $action==='new'?'Nuevo préstamo':'Editar préstamo #'.h($id) ?></div>
    </div>
    <?php if(!empty($err)): ?>
      <div class="error" style="margin-bottom:10px"><?= h($err) ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" action="?action=<?= $action==='new'?'create':'edit&id='.$id ?>&view=cards&modo_especial=<?= $modo_especial ?>">
      <div class="row" style="gap:12px;flex-wrap:wrap">
        <div class="field" style="min-width:220px;flex:1">
          <label>Deudor *</label>
          <select name="deudor_id" id="deudorSelect2" class="select2-persona" style="width:100%">
            <?php if ($deudor_id > 0): ?>
              <option value="<?= $deudor_id ?>" selected><?= h($row['deudor']) ?></option>
            <?php elseif ($row['deudor'] !== ''): ?>
              <option value="<?= h($row['deudor']) ?>" selected><?= h($row['deudor']) ?></option>
            <?php endif; ?>
          </select>
          <input type="hidden" name="deudor_nombre" id="deudor_nombre" value="<?= h($row['deudor']) ?>">
        </div>
        <div class="field" style="min-width:220px;flex:1">
          <label>Prestamista *</label>
          <select name="prestamista_id" id="prestamistaSelect2" class="select2-persona" style="width:100%">
            <?php if ($prestamista_id > 0): ?>
              <option value="<?= $prestamista_id ?>" selected><?= h($row['prestamista']) ?></option>
            <?php elseif ($row['prestamista'] !== ''): ?>
              <option value="<?= h($row['prestamista']) ?>" selected><?= h($row['prestamista']) ?></option>
            <?php endif; ?>
          </select>
          <input type="hidden" name="prestamista_nombre" id="prestamista_nombre" value="<?= h($row['prestamista']) ?>">
        </div>
        <div class="field" style="min-width:160px">
          <label>Monto *</label>
          <input name="monto" type="number" step="1" min="0" required value="<?= h($row['monto']) ?>">
        </div>
        <div class="field" style="min-width:160px">
          <label>Fecha *</label>
          <input name="fecha" type="date" required value="<?= h($row['fecha']) ?>">
        </div>
        <div class="field" style="min-width:240px;flex:1">
          <label>Imagen (opcional)</label>
          <?php if ($action==='edit' && $row['imagen']): ?>
            <div style="margin-bottom:6px">
              <img class="thumb" src="uploads/<?= h($row['imagen']) ?>" alt="">
            </div>
            <label style="display:flex;gap:8px;align-items:center">
              <input type="checkbox" name="keep" checked> Mantener imagen actual
            </label>
          <?php endif; ?>
          <input type="file" name="imagen" accept="image/*">
        </div>
      </div>
      <div class="row" style="margin-top:12px">
        <button class="btn" type="submit">💾 Guardar</button>
        <a class="btn gray" href="?view=cards&modo_especial=<?= $modo_especial ?>">Cancelar</a>
      </div>
    </form>
  </div>
<?php
// ====== LIST (SOLO TARJETAS) ======
else:

  // ==== filtros ====
  $q   = trim($_GET['q']  ?? '');
  $fp  = trim($_GET['fp'] ?? ''); // prestamista (normalizado)
  $fd  = trim($_GET['fd'] ?? ''); // deudor (normalizado)
  $fe  = trim($_GET['fe'] ?? ''); // empresa (normalizado)
  $fecha_desde = trim($_GET['fecha_desde'] ?? '');
  $fecha_hasta = trim($_GET['fecha_hasta'] ?? '');
  // Filtro de estado de pago
  $estado_pago = $_GET['estado_pago'] ?? 'no_pagados'; // 'no_pagados', 'pagados', 'todos'

  $qNorm  = mbnorm($q);
  $fpNorm = mbnorm($fp);
  $fdNorm = mbnorm($fd);
  $feNorm = mbnorm($fe);

  $conn=db();

  // Filtrar según el estado de pago seleccionado
  $whereBase = "1=1";
  if ($estado_pago === 'no_pagados') {
    $whereBase = "pagado = 0";
  } elseif ($estado_pago === 'pagados') {
    $whereBase = "pagado = 1";
  }

  // ==== COMBO prestamistas (siempre todos) ====
  $prestMap = [];
  $resPL = $conn->query("SELECT prestamista FROM prestamos WHERE $whereBase");
  while($rowPL=$resPL->fetch_row()){
    $norm = mbnorm($rowPL[0]);
    if ($norm==='') continue;
    if (!isset($prestMap[$norm])) $prestMap[$norm] = $rowPL[0];
  }
  ksort($prestMap, SORT_NATURAL);

  // ==== COMBO empresas (siempre todos) ====
  $empMap = [];
  $resEL = $conn->query("SELECT empresa FROM prestamos WHERE $whereBase");
  if ($resEL) {
    while($rowEL=$resEL->fetch_row()){
      $val = $rowEL[0];
      $norm = mbnorm($val);
      if ($norm==='') continue;
      if (!isset($empMap[$norm])) $empMap[$norm] = $val;
    }
    ksort($empMap, SORT_NATURAL);
  }

  // ==== COMBO deudores (filtrado dinámicamente) ====
  $deudMap = [];
  if ($feNorm !== '') {
    // Si hay empresa seleccionada, cargar solo deudores de esa empresa
    $sqlDeud = "SELECT DISTINCT deudor FROM prestamos WHERE LOWER(TRIM(empresa)) = ? AND $whereBase ORDER BY deudor";
    $stDeud = $conn->prepare($sqlDeud);
    $stDeud->bind_param("s", $feNorm);
    $stDeud->execute();
    $resDeud = $stDeud->get_result();
  } else {
    // Si no hay empresa seleccionada, cargar todos los deudores
    $resDeud = $conn->query("SELECT DISTINCT deudor FROM prestamos WHERE $whereBase ORDER BY deudor");
  }
  
  while($rowDL = ($feNorm !== '' ? $resDeud->fetch_assoc() : $resDeud->fetch_row())) {
    $deudorValor = $feNorm !== '' ? $rowDL['deudor'] : $rowDL[0];
    $norm = mbnorm($deudorValor);
    if ($norm === '') continue;
    if (!isset($deudMap[$norm])) {
      $deudMap[$norm] = $deudorValor;
    }
  }
  if ($feNorm !== '') $stDeud->close();

  // -------- TARJETAS --------
  $where = $whereBase; $types=""; $params=[];
  if ($q!==''){
    $where.=" AND (LOWER(deudor) LIKE CONCAT('%',?,'%') OR LOWER(prestamista) LIKE CONCAT('%',?,'%'))";
    $types.="ss"; $params[]=$qNorm; $params[]=$qNorm;
  }
  if ($fpNorm!==''){
    $where.=" AND LOWER(TRIM(prestamista)) = ?";
    $types.="s"; $params[]=$fpNorm;
  }
  if ($fdNorm!==''){
    $where.=" AND LOWER(TRIM(deudor)) = ?";
    $types.="s"; $params[]=$fdNorm;
  }
  if ($feNorm!==''){
    $where.=" AND LOWER(TRIM(empresa)) = ?";
    $types.="s"; $params[]=$feNorm;
  }
  if ($fecha_desde!==''){
    $where.=" AND fecha >= ?";
    $types.="s"; $params[]=$fecha_desde;
  }
  if ($fecha_hasta!==''){
    $where.=" AND fecha <= ?";
    $types.="s"; $params[]=$fecha_hasta;
  }

  // ============================================================
  // CONSTRUCCIÓN DE LA CONSULTA SEGÚN MODO ESPECIAL O NORMAL
  // ============================================================
  
  if ($modo_especial == 1) {
    // ===== MODO ESPECIAL: 8% mensual por DÍAS EXACTOS =====
    $sql = "
      SELECT 
        id, deudor, prestamista, monto, fecha, imagen, created_at, pagado, pagado_at,
        empresa,
        comision_gestor_nombre, comision_gestor_porcentaje, comision_base_monto, 
        comision_origen_prestamista, comision_origen_porcentaje,
        
        GREATEST(0, DATEDIFF(CURDATE(), fecha)) AS dias,
        
        (monto * 0.08 / 30 * GREATEST(0, DATEDIFF(CURDATE(), fecha))) AS interes_total,
        
        (monto * 0.08 / 30 * GREATEST(0, DATEDIFF(CURDATE(), fecha))) * 
          (COALESCE(comision_origen_porcentaje, 
            CASE WHEN fecha >= '2025-10-29' THEN 13 ELSE 10 END) / 100) AS interes_prestamista,
        
        (monto * 0.08 / 30 * GREATEST(0, DATEDIFF(CURDATE(), fecha))) * 
          (COALESCE(comision_gestor_porcentaje, 0) / 100) AS comision_gestor,
        
        (monto + (monto * 0.08 / 30 * GREATEST(0, DATEDIFF(CURDATE(), fecha)))) AS total
        
      FROM prestamos
      WHERE $where
      ORDER BY pagado ASC, id DESC
    ";
  } else {
    // ===== MODO NORMAL =====
    $sql = "
      SELECT 
        id, deudor, prestamista, monto, fecha, imagen, created_at, pagado, pagado_at,
        empresa,
        comision_gestor_nombre, comision_gestor_porcentaje, comision_base_monto, 
        comision_origen_prestamista, comision_origen_porcentaje,
        
        CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END AS meses,
        
        (monto * 
          CASE 
            WHEN fecha >= '2025-10-29' THEN COALESCE(comision_origen_porcentaje, 13)
            ELSE COALESCE(comision_origen_porcentaje, 10)
          END / 100 *
          CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS interes_prestamista,
        
        (COALESCE(comision_base_monto, monto) * COALESCE(comision_gestor_porcentaje, 0) / 100 *
          CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS comision_gestor,
        
        ((monto * 
            CASE 
              WHEN fecha >= '2025-10-29' THEN COALESCE(comision_origen_porcentaje, 13)
              ELSE COALESCE(comision_origen_porcentaje, 10)
            END / 100) + 
          (COALESCE(comision_base_monto, monto) * COALESCE(comision_gestor_porcentaje, 0) / 100)) *
          CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END AS interes_total,
        
        (monto + 
          (((monto * 
              CASE 
                WHEN fecha >= '2025-10-29' THEN COALESCE(comision_origen_porcentaje, 13)
                ELSE COALESCE(comision_origen_porcentaje, 10)
              END / 100) + 
            (COALESCE(comision_base_monto, monto) * COALESCE(comision_gestor_porcentaje, 0) / 100)) *
            CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END)) AS total
            
      FROM prestamos
      WHERE $where
      ORDER BY pagado ASC, id DESC
    ";
  }
  
  $st=$conn->prepare($sql);
  if($types) $st->bind_param($types, ...$params);
  $st->execute();
  $rs=$st->get_result();

  // ============================================================
  // DESGLOSE POR PRESTAMISTA (SOLO MODO ESPECIAL)
  // ============================================================
  $desglose_prestamistas = [];
  $resumen_general = ['capital' => 0, 'interes' => 0, 'total' => 0, 'count' => 0];
  
  if ($modo_especial == 1) {
    $sqlDesglose = "
      SELECT 
        prestamista,
        COUNT(*) AS num_prestamos,
        SUM(monto) AS capital_total,
        SUM(monto * 0.08 / 30 * GREATEST(0, DATEDIFF(CURDATE(), fecha))) AS interes_total,
        SUM(monto + (monto * 0.08 / 30 * GREATEST(0, DATEDIFF(CURDATE(), fecha)))) AS total_a_pagar
      FROM prestamos
      WHERE $where
      GROUP BY prestamista
      ORDER BY prestamista
    ";
    $stDesglose = $conn->prepare($sqlDesglose);
    if($types) $stDesglose->bind_param($types, ...$params);
    $stDesglose->execute();
    $rsDesglose = $stDesglose->get_result();
    
    while($rowDesg = $rsDesglose->fetch_assoc()) {
      $desglose_prestamistas[] = $rowDesg;
      $resumen_general['capital'] += $rowDesg['capital_total'];
      $resumen_general['interes'] += $rowDesg['interes_total'];
      $resumen_general['total'] += $rowDesg['total_a_pagar'];
      $resumen_general['count'] += $rowDesg['num_prestamos'];
    }
    $stDesglose->close();
  } else {
    if ($fecha_desde !== '' || $fecha_hasta !== '' || $fdNorm !== '' || $fpNorm !== '' || $feNorm !== '' || $estado_pago !== 'no_pagados') {
      $sqlSumas = "
        SELECT 
          COUNT(*) AS n,
          SUM(monto) AS capital,
          SUM(((monto * 
                CASE 
                  WHEN fecha >= '2025-10-29' THEN COALESCE(comision_origen_porcentaje, 13)
                  ELSE COALESCE(comision_origen_porcentaje, 10)
                END / 100) + 
              (COALESCE(comision_base_monto, monto) * COALESCE(comision_gestor_porcentaje, 0) / 100)) *
              CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS interes,
          SUM(monto + 
              (((monto * 
                  CASE 
                    WHEN fecha >= '2025-10-29' THEN COALESCE(comision_origen_porcentaje, 13)
                    ELSE COALESCE(comision_origen_porcentaje, 10)
                  END / 100) + 
                (COALESCE(comision_base_monto, monto) * COALESCE(comision_gestor_porcentaje, 0) / 100)) *
                CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END)) AS total
        FROM prestamos
        WHERE $where
      ";
      $stSumas = $conn->prepare($sqlSumas);
      if($types) $stSumas->bind_param($types, ...$params);
      $stSumas->execute();
      $sumasNorm = $stSumas->get_result()->fetch_assoc();
      if($sumasNorm) {
        $resumen_general = [
          'count' => $sumasNorm['n'],
          'capital' => $sumasNorm['capital'],
          'interes' => $sumasNorm['interes'],
          'total' => $sumasNorm['total']
        ];
      }
      $stSumas->close();
    }
  }
?>
    <!-- Toolbar de filtros -->
    <div class="card" style="margin-bottom:16px">
      <form class="toolbar" method="get" id="filtroForm">
        <input type="hidden" name="view" value="cards">
        
        <div class="modo-especial-container">
          <span class="modo-especial-label">⚡ Modo 8% por días</span>
          <label class="toggle-switch">
            <input type="checkbox" name="modo_especial" value="1" id="modoEspecialToggle" onchange="this.form.submit()" <?= $modo_especial == 1 ? 'checked' : '' ?>>
            <span class="toggle-slider"></span>
          </label>
          <?php if ($modo_especial == 1): ?>
            <span class="modo-activo-badge">ACTIVO • Cálculo por días exactos al 8% mensual</span>
          <?php else: ?>
            <span class="subtitle" style="font-size:11px">Modo normal • meses completos</span>
          <?php endif; ?>
        </div>
        
        <input name="q" placeholder="🔎 Buscar (deudor / prestamista)" value="<?= h($q) ?>" style="flex:1;min-width:220px">
        
        <div class="field" style="min-width:200px;flex:1">
          <label>Prestamista</label>
          <select name="fp" id="prestamistaSelect" title="Prestamista" class="select2-filter">
            <option value="">Todos los prestamistas</option>
            <?php foreach($prestMap as $norm=>$label): ?>
              <option value="<?= h($norm) ?>" <?= $fpNorm===$norm?'selected':'' ?>><?= h(mbtitle($label)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field" style="min-width:200px;flex:1">
          <label>Empresa</label>
          <select name="fe" id="empresaSelect" title="Empresa" class="select2-filter">
            <option value="">Todas las empresas</option>
            <?php foreach($empMap as $norm=>$label): ?>
              <option value="<?= h($norm) ?>" <?= $feNorm===$norm?'selected':'' ?>><?= h(mbtitle($label)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field" style="min-width:200px;flex:1">
          <label>Deudor</label>
          <select name="fd" id="deudorSelect" title="Deudor" class="select2-filter">
            <option value="">Todos los deudores</option>
            <?php foreach($deudMap as $norm=>$label): ?>
              <option value="<?= h($norm) ?>" <?= $fdNorm===$norm?'selected':'' ?>><?= h(mbtitle($label)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field" style="min-width:150px">
          <label>Desde</label>
          <input name="fecha_desde" type="date" value="<?= h($fecha_desde) ?>">
        </div>
        <div class="field" style="min-width:150px">
          <label>Hasta</label>
          <input name="fecha_hasta" type="date" value="<?= h($fecha_hasta) ?>">
        </div>
        
        <div class="switch-container">
          <span class="switch-label">Estado:</span>
          <div class="switch-group">
            <label class="switch-pill">
              <input type="radio" name="estado_pago" value="no_pagados"
                     <?= $estado_pago === 'no_pagados' ? 'checked' : '' ?>
                     onchange="this.form.submit()">
              <span>No pagados</span>
            </label>
            <label class="switch-pill">
              <input type="radio" name="estado_pago" value="pagados"
                     <?= $estado_pago === 'pagados' ? 'checked' : '' ?>
                     onchange="this.form.submit()">
              <span>Pagados</span>
            </label>
            <label class="switch-pill">
              <input type="radio" name="estado_pago" value="todos"
                     <?= $estado_pago === 'todos' ? 'checked' : '' ?>
                     onchange="this.form.submit()">
              <span>Todos</span>
            </label>
          </div>
        </div>

        <button class="btn" type="submit">Filtrar</button>
        <?php if ($q!=='' || $fpNorm!=='' || $fdNorm!=='' || $feNorm!=='' || $fecha_desde!=='' || $fecha_hasta!=='' || $estado_pago !== 'no_pagados'): ?>
          <a class="btn gray" href="?view=cards&modo_especial=<?= $modo_especial ?>">Quitar filtro</a>
        <?php endif; ?>
      </form>
      
      <?php if ($modo_especial == 1): ?>
        <div class="subtitle" style="margin-top:8px; background:#fef3c7; padding:6px 12px; border-radius:12px;">
          📐 <strong>Modo especial activado:</strong> Interés fijo del 8% mensual calculado por días exactos. 
          Fórmula: Capital × (0.08/30) × días transcurridos.
        </div>
      <?php else: ?>
        <div class="subtitle">Interés variable: 13% desde 2025-10-29, 10% para préstamos anteriores. Cálculo por meses completos.</div>
      <?php endif; ?>
    </div>

    <!-- Resumen del filtro -->
    <?php if ($fecha_desde !== '' || $fecha_hasta !== '' || $fdNorm !== '' || $fpNorm !== '' || $feNorm!=='' || $estado_pago !== 'no_pagados'): ?>
      <div class="resumen-filtro">
        <div class="title">Resumen del Filtro</div>
        <div class="subtitle">
          <?php
            $filtros = [];
            if ($fecha_desde !== '') $filtros[] = "Desde: " . h($fecha_desde);
            if ($fecha_hasta !== '') $filtros[] = "Hasta: " . h($fecha_hasta);
            if ($fdNorm !== '') $filtros[] = "Deudor: " . h(mbtitle($deudMap[$fdNorm] ?? $fdNorm));
            if ($fpNorm !== '') $filtros[] = "Prestamista: " . h(mbtitle($prestMap[$fpNorm] ?? $fpNorm));
            if ($feNorm !== '') $filtros[] = "Empresa: " . h(mbtitle($empMap[$feNorm] ?? $feNorm));
            $filtros[] = "Estado: " . ($estado_pago === 'todos' ? 'Todos' : ($estado_pago === 'pagados' ? 'Pagados' : 'No pagados'));
            if ($modo_especial == 1) $filtros[] = "🔘 MODO ESPECIAL (8% por días)";
            echo implode(' • ', $filtros);
          ?>
        </div>
        
        <div class="resumen-grid">
          <div class="resumen-item">
            <div class="resumen-valor"><?= (int)($resumen_general['count'] ?? 0) ?></div>
            <div class="resumen-label">Préstamos</div>
          </div>
          <div class="resumen-item">
            <div class="resumen-valor">$ <?= money($resumen_general['capital'] ?? 0) ?></div>
            <div class="resumen-label">Capital</div>
          </div>
          <div class="resumen-item">
            <div class="resumen-valor">$ <?= money($resumen_general['interes'] ?? 0) ?></div>
            <div class="resumen-label">Interés</div>
          </div>
          <div class="resumen-item">
            <div class="resumen-valor">$ <?= money($resumen_general['total'] ?? 0) ?></div>
            <div class="resumen-label">Total</div>
          </div>
        </div>
        
        <?php if ($modo_especial == 1 && !empty($desglose_prestamistas)): ?>
          <div class="desglose-prestamistas">
            <div class="desglose-titulo">
              <span>💰 Desglose por Prestamista</span>
              <span class="subtitle">(Modo 8% por días activo)</span>
            </div>
            <table class="desglose-tabla">
              <thead>
                <tr>
                  <th>Prestamista</th>
                  <th>Préstamos</th>
                  <th>Capital Total</th>
                  <th>Interés (8% por días)</th>
                  <th>Total a Pagar</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($desglose_prestamistas as $dp): ?>
                <tr>
                  <td><strong><?= h(mbtitle($dp['prestamista'])) ?></strong></td>
                  <td><?= (int)$dp['num_prestamos'] ?></td>
                  <td>$ <?= money($dp['capital_total']) ?></td>
                  <td>$ <?= money($dp['interes_total']) ?></td>
                  <td><strong>$ <?= money($dp['total_a_pagar']) ?></strong></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr class="total-row">
                  <td><strong>TOTAL GENERAL</strong></td>
                  <td><strong><?= (int)($resumen_general['count'] ?? 0) ?></strong></td>
                  <td><strong>$ <?= money($resumen_general['capital'] ?? 0) ?></strong></td>
                  <td><strong>$ <?= money($resumen_general['interes'] ?? 0) ?></strong></td>
                  <td><strong>$ <?= money($resumen_general['total'] ?? 0) ?></strong></td>
                </tr>
              </tfoot>
            </table>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($rs->num_rows === 0): ?>
      <div class="card"><span class="subtitle">(sin registros)</span></div>
    <?php else: ?>
      <form id="bulkForm" class="card" method="post" action="?action=bulk_update&modo_especial=<?= $modo_especial ?>">
        <input type="hidden" name="view" value="cards">

        <div class="row" style="margin-bottom:8px">
          <div class="title">Selecciona tarjetas</div>
          <div class="sticky-actions" style="display:flex;gap:8px;align-items:center">
            <label class="subtitle" style="display:flex;gap:8px;align-items:center">
              <input id="chkAll" type="checkbox"> Seleccionar todo (página)
            </label>
            <button type="button" class="btn gray small" id="btnToggleBulk">✏️ Editar selección</button>
            <button 
              type="submit" 
              class="btn small" 
              formaction="?action=bulk_mark_paid&modo_especial=<?= $modo_especial ?>"
              onclick="return confirm('¿Marcar como pagados los préstamos seleccionados?')">
              ✔ Préstamo pagado
            </button>
            <button 
              type="submit" 
              class="btn gray small" 
              formaction="?action=bulk_mark_unpaid&modo_especial=<?= $modo_especial ?>"
              onclick="return confirm('¿Marcar como NO pagados los préstamos seleccionados?')">
              ↩ NO pagado
            </button>
            <span class="badge" id="selCount">0 seleccionadas</span>
          </div>
        </div>

        <div class="grid-cards">
          <?php while($r=$rs->fetch_assoc()):
            $esComision = !empty($r['comision_gestor_nombre']);
            $esPagado = (bool)($r['pagado'] ?? false);
            
            $cardClass = '';
            $badgeClass = 'chip';
            
            if ($esComision) {
              $cardClass = 'card-comision';
              $badgeClass = 'comision-badge';
            } elseif ($esPagado) {
              $cardClass = 'card-pagado';
              $badgeClass = 'pagado-badge';
            }
            
            $porcentajeTotal = 0;
            if ($modo_especial == 0) {
              $porcentajeTotal = (float)($r['comision_origen_porcentaje'] ?? 
                (strtotime($r['fecha']) >= strtotime('2025-10-29') ? 13 : 10)) + 
                (float)($r['comision_gestor_porcentaje'] ?? 0);
            }
            
            $diasMostrar = $modo_especial == 1 ? ($r['dias'] ?? 0) : 0;
          ?>
            <div class="card <?= $cardClass ?>">
              <div class="cardSel">
                <input class="chkRow" type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>">
                <div class="subtitle">#<?= h($r['id']) ?></div>
                <?php if ($modo_especial == 1): ?>
                  <span class="chip" style="background:#fef3c7; color:#92400e; margin-left:auto">📆 <?= $diasMostrar ?> días</span>
                <?php elseif ($esComision): ?>
                  <span class="<?= $badgeClass ?>" style="margin-left:auto">💰 Comisión</span>
                <?php elseif ($esPagado): ?>
                  <span class="<?= $badgeClass ?>" style="margin-left:auto">✅ Pagado</span>
                <?php endif; ?>
              </div>

              <?php if (!empty($r['imagen'])): ?>
                <a href="uploads/<?= h($r['imagen']) ?>" target="_blank">
                  <img class="thumb" src="uploads/<?= h($r['imagen']) ?>" alt="">
                </a>
              <?php endif; ?>

              <div class="row" style="margin-top:8px">
                <div>
                  <div class="title"><?= h($r['deudor']) ?></div>
                  <div class="subtitle">Prestamista: <strong><?= h($r['prestamista']) ?></strong></div>
                  <?php if (!empty($r['empresa'])): ?>
                    <div class="subtitle">Empresa: <strong><?= h($r['empresa']) ?></strong></div>
                  <?php endif; ?>
                  <?php if ($esPagado && !empty($r['pagado_at'])): ?>
                    <div class="subtitle text-pagado">Pagado el: <?= h($r['pagado_at']) ?></div>
                  <?php endif; ?>
                  <?php if ($modo_especial == 1): ?>
                    <div class="subtitle" style="color:#92400e">📅 Desde: <?= h($r['fecha']) ?> • <?= $diasMostrar ?> días</div>
                  <?php endif; ?>
                </div>
                <span class="chip"><?= h($r['fecha']) ?></span>
              </div>

              <?php if ($esComision && $modo_especial == 0): ?>
                <div class="pairs comision-info" style="margin-top:8px; padding:8px; border-radius:8px;">
                  <div class="item">
                    <div class="k comision-text">Gestor Comisión</div>
                    <div class="v comision-text"><?= h($r['comision_gestor_nombre']) ?></div>
                  </div>
                  <div class="item">
                    <div class="k comision-text">% Comisión</div>
                    <div class="v comision-text"><?= h($r['comision_gestor_porcentaje']) ?>%</div>
                  </div>
                  <div class="item">
                    <div class="k comision-text">Base Comisión</div>
                    <div class="v comision-text">$ <?= money($r['comision_base_monto']) ?></div>
                  </div>
                  <div class="item">
                    <div class="k comision-text">Origen</div>
                    <div class="v comision-text"><?= h($r['comision_origen_prestamista']) ?></div>
                  </div>
                  <div class="item">
                    <div class="k comision-text">% Origen</div>
                    <div class="v comision-text"><?= h($r['comision_origen_porcentaje']) ?>%</div>
                  </div>
                  <div class="item">
                    <div class="k comision-text">% Total</div>
                    <div class="v comision-text"><?= $porcentajeTotal ?>%</div>
                  </div>
                </div>
              <?php endif; ?>

              <div class="pairs" style="margin-top:12px">
                <div class="item">
                  <div class="k">Monto</div>
                  <div class="v">$ <?= money($r['monto']) ?></div>
                </div>
                <?php if ($modo_especial == 1): ?>
                  <div class="item">
                    <div class="k">Días / Tasa</div>
                    <div class="v"><?= $diasMostrar ?> días • 8%</div>
                  </div>
                <?php else: ?>
                  <div class="item">
                    <div class="k">Meses</div>
                    <div class="v"><?= h($r['meses'] ?? 0) ?></div>
                  </div>
                <?php endif; ?>
                <div class="item">
                  <div class="k">Interés</div>
                  <div class="v">$ <?= money($r['interes_total']) ?></div>
                </div>
                <div class="item">
                  <div class="k">Total</div>
                  <div class="v">$ <?= money($r['total']) ?></div>
                </div>
              </div>

              <?php if ($esComision && $modo_especial == 0): ?>
                <div class="pairs" style="margin-top:8px; font-size:12px;">
                  <div class="item">
                    <div class="k">Interés Prestamista</div>
                    <div class="v">$ <?= money($r['interes_prestamista']) ?></div>
                  </div>
                  <div class="item">
                    <div class="k">Comisión Gestor</div>
                    <div class="v">$ <?= money($r['comision_gestor']) ?></div>
                  </div>
                </div>
              <?php elseif ($modo_especial == 1 && (!empty($r['comision_gestor_nombre']) || !empty($r['comision_origen_prestamista']))): ?>
                <div class="pairs" style="margin-top:8px; font-size:12px; background:#fef3c7; border-radius:8px; padding:8px;">
                  <div class="item">
                    <div class="k">Interés Prestamista (<?= h($r['comision_origen_porcentaje'] ?? '?') ?>%)</div>
                    <div class="v">$ <?= money($r['interes_prestamista'] ?? 0) ?></div>
                  </div>
                  <div class="item">
                    <div class="k">Comisión Gestor (<?= h($r['comision_gestor_porcentaje'] ?? '0') ?>%)</div>
                    <div class="v">$ <?= money($r['comision_gestor'] ?? 0) ?></div>
                  </div>
                </div>
              <?php endif; ?>

              <div class="row" style="margin-top:12px">
                <div class="subtitle">Creado: <?= h($r['created_at']) ?></div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                  <a class="btn gray small" href="?action=edit&id=<?= $r['id'] ?>&view=cards&modo_especial=<?= $modo_especial ?>">✏️ Editar</a>
                  <button class="btn red small" type="button" onclick="submitDelete(<?= (int)$r['id'] ?>)">🗑️ Eliminar</button>
                </div>
              </div>
            </div>
          <?php endwhile; ?>
        </div>

        <div class="bulkpanel" id="bulkPanel">
          <div class="subtitle" style="margin-bottom:8px">
            Aplica solo a las tarjetas seleccionadas. Deja en blanco lo que no quieras cambiar.
          </div>
          <div class="row" style="gap:12px;flex-wrap:wrap">
            <div class="field" style="min-width:220px;flex:1">
              <label>Nuevo Deudor (opcional)</label>
              <input name="new_deudor" placeholder="Ej: Juan Pérez">
            </div>
            <div class="field" style="min-width:220px;flex:1">
              <label>Nuevo Prestamista (opcional)</label>
              <input name="new_prestamista" placeholder="Ej: ATZN">
            </div>
            <div class="field" style="min-width:160px">
              <label>Nuevo Monto (opcional)</label>
              <input name="new_monto" type="number" step="1" min="0" placeholder="Ej: 1200000">
            </div>
            <div class="field" style="min-width:160px">
              <label>Nueva Fecha (opcional)</label>
              <input name="new_fecha" type="date">
            </div>
          </div>
          <div class="row" style="margin-top:10px">
            <button class="btn" type="submit" onclick="return confirm('¿Aplicar cambios a la selección?')">
              💾 Aplicar a seleccionadas
            </button>
            <button class="btn gray" type="button" id="btnCloseBulk">Cerrar</button>
          </div>
        </div>
      </form>
    <?php endif; ?>
<?php
  $st->close();
  $conn->close();
endif;
?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
  // Select2 para filtros
  $('.select2-filter').select2({
    width: '100%',
    placeholder: 'Seleccionar...',
    allowClear: true,
    language: {
      noResults: function() {
        return "No se encontraron resultados";
      }
    }
  });
  
  // ========== SELECT2 PARA DEUDOR (con creación) ==========
  $('#deudorSelect2').select2({
    width: '100%',
    placeholder: 'Buscar o agregar deudor...',
    allowClear: true,
    ajax: {
      url: '?ajax=deudores',
      dataType: 'json',
      delay: 300,
      data: function(params) {
        return { q: params.term };
      },
      processResults: function(data) {
        return { results: data.results };
      },
      cache: true
    },
    createTag: function(params) {
      var term = $.trim(params.term);
      if (term === '') return null;
      return {
        id: term,
        text: '➕ Agregar "' + term + '" como nuevo deudor',
        newTag: true,
        originalText: term
      };
    },
    templateResult: function(data) {
      if (data.newTag) {
        return $('<span style="color:#f59e0b;">' + data.text + '</span>');
      }
      return data.text;
    },
    templateSelection: function(data) {
      if (data.newTag) {
        return data.originalText || data.text.replace('➕ Agregar "', '').replace('" como nuevo deudor', '');
      }
      return data.text;
    }
  });
  
  $('#deudorSelect2').on('select2:select', function(e) {
    var data = e.params.data;
    if (data.newTag) {
      var nuevoNombre = data.originalText || data.id;
      $.ajax({
        url: '?ajax=crear_deudor',
        type: 'POST',
        data: { nombre: nuevoNombre },
        dataType: 'json',
        success: function(res) {
          if (res.success) {
            var newOption = new Option(res.text, res.id, true, true);
            $('#deudorSelect2').append(newOption).trigger('change');
            $('#deudor_nombre').val(res.text);
          } else {
            alert('Error al crear deudor: ' + (res.error || 'desconocido'));
          }
        },
        error: function(xhr, status, error) {
          console.error('Error:', error);
          alert('Error de conexión al crear deudor');
        }
      });
    } else {
      // Si es un ID existente, buscar el texto
      var text = data.text;
      $('#deudor_nombre').val(text);
    }
  });
  
  // ========== SELECT2 PARA PRESTAMISTA (con creación) ==========
  $('#prestamistaSelect2').select2({
    width: '100%',
    placeholder: 'Buscar o agregar prestamista...',
    allowClear: true,
    ajax: {
      url: '?ajax=prestamistas',
      dataType: 'json',
      delay: 300,
      data: function(params) {
        return { q: params.term };
      },
      processResults: function(data) {
        return { results: data.results };
      },
      cache: true
    },
    createTag: function(params) {
      var term = $.trim(params.term);
      if (term === '') return null;
      return {
        id: term,
        text: '➕ Agregar "' + term + '" como nuevo prestamista',
        newTag: true,
        originalText: term
      };
    },
    templateResult: function(data) {
      if (data.newTag) {
        return $('<span style="color:#f59e0b;">' + data.text + '</span>');
      }
      return data.text;
    },
    templateSelection: function(data) {
      if (data.newTag) {
        return data.originalText || data.text.replace('➕ Agregar "', '').replace('" como nuevo prestamista', '');
      }
      return data.text;
    }
  });
  
  $('#prestamistaSelect2').on('select2:select', function(e) {
    var data = e.params.data;
    if (data.newTag) {
      var nuevoNombre = data.originalText || data.id;
      $.ajax({
        url: '?ajax=crear_prestamista',
        type: 'POST',
        data: { nombre: nuevoNombre },
        dataType: 'json',
        success: function(res) {
          if (res.success) {
            var newOption = new Option(res.text, res.id, true, true);
            $('#prestamistaSelect2').append(newOption).trigger('change');
            $('#prestamista_nombre').val(res.text);
          } else {
            alert('Error al crear prestamista: ' + (res.error || 'desconocido'));
          }
        },
        error: function(xhr, status, error) {
          console.error('Error:', error);
          alert('Error de conexión al crear prestamista');
        }
      });
    } else {
      var text = data.text;
      $('#prestamista_nombre').val(text);
    }
  });
  
  // Guardar referencia a los selects
  const empresaSelect = $('#empresaSelect');
  const deudorSelect = $('#deudorSelect');
  const estadoPagoRadios = document.querySelectorAll('input[name="estado_pago"]');
  const modoEspecialCheckbox = document.getElementById('modoEspecialToggle');
  
  let deudorSeleccionado = deudorSelect.val();
  
  function cargarDeudoresPorEmpresa(empresaNormalizada, estadoPago, modoEspecial) {
    if (!empresaNormalizada) {
      cargarTodosDeudores(estadoPago, modoEspecial);
      return;
    }
    
    deudorSelect.html('<option value="">Cargando deudores...</option>');
    
    fetch(`ajax_cargar_deudores.php?empresa=${encodeURIComponent(empresaNormalizada)}&estado=${estadoPago}&modo_especial=${modoEspecial}`)
      .then(response => response.json())
      .then(data => {
        let options = '<option value="">Todos los deudores</option>';
        data.forEach(deudor => {
          const selected = (deudor.norm === deudorSeleccionado) ? 'selected' : '';
          options += `<option value="${deudor.norm}" ${selected}>${deudor.nombre}</option>`;
        });
        deudorSelect.html(options);
        deudorSelect.select2({
          width: '100%',
          placeholder: 'Seleccionar deudor...',
          allowClear: true
        });
        if (deudorSeleccionado) {
          deudorSelect.val(deudorSeleccionado).trigger('change');
        }
      })
      .catch(error => {
        console.error('Error cargando deudores:', error);
        deudorSelect.html('<option value="">Error cargando deudores</option>');
      });
  }
  
  function cargarTodosDeudores(estadoPago, modoEspecial) {
    deudorSelect.html('<option value="">Cargando todos los deudores...</option>');
    fetch(`ajax_cargar_deudores.php?empresa=&estado=${estadoPago}&modo_especial=${modoEspecial}`)
      .then(response => response.json())
      .then(data => {
        let options = '<option value="">Todos los deudores</option>';
        data.forEach(deudor => {
          const selected = (deudor.norm === deudorSeleccionado) ? 'selected' : '';
          options += `<option value="${deudor.norm}" ${selected}>${deudor.nombre}</option>`;
        });
        deudorSelect.html(options);
        deudorSelect.select2({
          width: '100%',
          placeholder: 'Seleccionar deudor...',
          allowClear: true
        });
        if (deudorSeleccionado) {
          deudorSelect.val(deudorSeleccionado).trigger('change');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        deudorSelect.html('<option value="">Error cargando deudores</option>');
      });
  }
  
  empresaSelect.on('change', function() {
    const empresaNormalizada = $(this).val();
    const estadoPago = document.querySelector('input[name="estado_pago"]:checked')?.value || 'no_pagados';
    const modoEspecial = modoEspecialCheckbox?.checked ? 1 : 0;
    deudorSeleccionado = deudorSelect.val();
    cargarDeudoresPorEmpresa(empresaNormalizada, estadoPago, modoEspecial);
  });
  
  estadoPagoRadios.forEach(radio => {
    radio.addEventListener('change', function() {
      const empresaNormalizada = empresaSelect.val();
      const estadoPago = this.value;
      const modoEspecial = modoEspecialCheckbox?.checked ? 1 : 0;
      deudorSeleccionado = deudorSelect.val();
      if (empresaNormalizada) {
        cargarDeudoresPorEmpresa(empresaNormalizada, estadoPago, modoEspecial);
      } else {
        cargarTodosDeudores(estadoPago, modoEspecial);
      }
    });
  });
});

// Selección múltiple + eliminar
(function(){
  const form = document.getElementById('bulkForm');
  if(!form) return;

  const chkAll   = document.getElementById('chkAll');
  const chkRows  = Array.from(form.querySelectorAll('.chkRow'));
  const selCount = document.getElementById('selCount');
  const panel    = document.getElementById('bulkPanel');
  const btnTog   = document.getElementById('btnToggleBulk');
  const btnClose = document.getElementById('btnCloseBulk');

  function updateCount(){
    const n = chkRows.filter(c=>c.checked).length;
    selCount.textContent = n + ' seleccionadas';
  }

  if (chkAll){
    chkAll.addEventListener('change', () => {
      chkRows.forEach(c => { c.checked = chkAll.checked; });
      updateCount();
    });
  }

  chkRows.forEach(c => c.addEventListener('change', updateCount));
  updateCount();

  if (btnTog){
    btnTog.addEventListener('click', () => {
      const any = chkRows.some(c=>c.checked);
      if (!any) { alert('Selecciona al menos una tarjeta para editar.'); return; }
      panel.style.display = (panel.style.display==='none' || panel.style.display==='') ? 'block' : 'none';
      const first = panel.querySelector('input[name="new_deudor"]');
      if (first) first.focus();
    });
  }

  if (btnClose){
    btnClose.addEventListener('click', () => { panel.style.display = 'none'; });
  }
})();

function submitDelete(id){
  if(!confirm('¿Eliminar #'+id+'?')) return;
  const f = document.createElement('form');
  f.method = 'post';
  f.action = '?action=delete&id='+id+'&modo_especial=<?= $modo_especial ?>';
  document.body.appendChild(f);
  f.submit();
}
</script>

</body></html>