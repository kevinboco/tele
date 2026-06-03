<?php
/*********************************************************
 * admin_prestamos.php — CRUD + Tarjetas + TOAST con copiar
 * - Toast flotante después de crear/editar/eliminar préstamo
 * - Mensaje simple (sin intereses, meses ni totales)
 * - Botón copiar al portapapeles
 *********************************************************/
session_start(); // IMPORTANTE: añadir session_start() al principio
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

// Función para asegurar que un deudor existe en la tabla deudores_admin
function ensure_deudor($nombre, $owner_chat_id = 6133806918) {
    if (empty($nombre)) return false;
    $c = db();
    $norm = mbnorm($nombre);
    $st = $c->prepare("SELECT id FROM deudores_admin WHERE owner_chat_id = ? AND LOWER(TRIM(nombre)) = ?");
    $st->bind_param("is", $owner_chat_id, $norm);
    $st->execute();
    $st->store_result();
    if ($st->num_rows > 0) {
        $st->close();
        $c->close();
        return true;
    }
    $st->close();
    // Insertar si no existe
    $st = $c->prepare("INSERT INTO deudores_admin (owner_chat_id, nombre) VALUES (?, ?)");
    $st->bind_param("is", $owner_chat_id, $nombre);
    $result = $st->execute();
    $st->close();
    $c->close();
    return $result;
}

// Función para asegurar que un prestamista existe en la tabla prestamistas_admin
function ensure_prestamista($nombre, $owner_chat_id = 6133806918) {
    if (empty($nombre)) return false;
    $c = db();
    $norm = mbnorm($nombre);
    $st = $c->prepare("SELECT id FROM prestamistas_admin WHERE owner_chat_id = ? AND LOWER(TRIM(nombre)) = ?");
    $st->bind_param("is", $owner_chat_id, $norm);
    $st->execute();
    $st->store_result();
    if ($st->num_rows > 0) {
        $st->close();
        $c->close();
        return true;
    }
    $st->close();
    // Insertar si no existe
    $st = $c->prepare("INSERT INTO prestamistas_admin (owner_chat_id, nombre) VALUES (?, ?)");
    $st->bind_param("is", $owner_chat_id, $nombre);
    $result = $st->execute();
    $st->close();
    $c->close();
    return $result;
}

$action = $_GET['action'] ?? 'list';
$view   = 'cards'; // Solo tarjetas
$id = (int)($_GET['id'] ?? 0);

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

  // Si se está actualizando deudor o prestamista, asegurar que existan en las tablas admin
  if ($new_deudor !== '') {
    ensure_deudor($new_deudor);
  }
  if ($new_prestamista !== '') {
    ensure_prestamista($new_prestamista);
  }

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
  go(BASE_URL.'?view=cards&msg='.$msg);
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
  go(BASE_URL.'?view=cards&msg='.$msg);
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
  go(BASE_URL.'?view=cards&msg='.$msg);
}

/* ===== CRUD ===== */
if ($action==='create' && $_SERVER['REQUEST_METHOD']==='POST'){
  $deudor = trim($_POST['deudor']??'');
  $prestamista = trim($_POST['prestamista']??'');
  $monto = trim($_POST['monto']??'');
  $fecha = trim($_POST['fecha']??'');
  $empresa = trim($_POST['empresa']??'');
  $img = save_image($_FILES['imagen']??null);

  if ($deudor && $prestamista && is_numeric($monto) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)){
    ensure_deudor($deudor);
    ensure_prestamista($prestamista);
    
    $c=db();
    $st=$c->prepare("INSERT INTO prestamos (deudor,prestamista,monto,fecha,empresa,imagen,created_at) VALUES (?,?,?,?,?,?,NOW())");
    $st->bind_param("ssdsss",$deudor,$prestamista,$monto,$fecha,$empresa,$img);
    $st->execute();
    $nuevo_id = $c->insert_id;
    $st->close(); 
    $c->close();
    
    // Guardar en sesión para el toast (PERSISTENTE)
    $_SESSION['toast_prestamo'] = [
        'tipo' => 'creado',
        'deudor' => $deudor,
        'prestamista' => $prestamista,
        'monto' => $monto,
        'fecha' => $fecha,
        'empresa' => $empresa,
        'imagen' => $img
    ];
    
    go('?view=cards');
  } else {
    $err="Completa todos los campos correctamente.";
  }
}

if ($action==='edit' && $_SERVER['REQUEST_METHOD']==='POST' && $id>0){
  $deudor=trim($_POST['deudor']??'');
  $prestamista=trim($_POST['prestamista']??'');
  $monto=trim($_POST['monto']??'');
  $fecha=trim($_POST['fecha']??'');
  $empresa=trim($_POST['empresa']??'');
  $keep = isset($_POST['keep']) ? 1:0;
  $img = save_image($_FILES['imagen']??null);

  if ($deudor && $prestamista && is_numeric($monto) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)){
    ensure_deudor($deudor);
    ensure_prestamista($prestamista);
    
    $c=db();
    if ($img){
      $st=$c->prepare("UPDATE prestamos SET deudor=?,prestamista=?,monto=?,fecha=?,empresa=?,imagen=? WHERE id=?");
      $st->bind_param("ssdsssi",$deudor,$prestamista,$monto,$fecha,$empresa,$img,$id);
    } else {
      if ($keep){
        $st=$c->prepare("UPDATE prestamos SET deudor=?,prestamista=?,monto=?,fecha=?,empresa=? WHERE id=?");
        $st->bind_param("ssdssi",$deudor,$prestamista,$monto,$fecha,$empresa,$id);
      } else {
        $st=$c->prepare("UPDATE prestamos SET deudor=?,prestamista=?,monto=?,fecha=?,empresa=?,imagen=NULL WHERE id=?");
        $st->bind_param("ssdssi",$deudor,$prestamista,$monto,$fecha,$empresa,$id);
      }
    }
    $st->execute();
    $st->close(); 
    $c->close();
    
    // Guardar en sesión para el toast
    $_SESSION['toast_prestamo'] = [
        'tipo' => 'editado',
        'deudor' => $deudor,
        'prestamista' => $prestamista,
        'monto' => $monto,
        'fecha' => $fecha,
        'empresa' => $empresa,
        'imagen' => $img
    ];
    
    go('?view=cards');
  } else {
    $err="Completa todos los campos correctamente.";
  }
}

if ($action==='delete' && $_SERVER['REQUEST_METHOD']==='POST' && $id>0){
  $c=db();
  $st=$c->prepare("SELECT deudor,prestamista,monto,fecha,empresa,imagen FROM prestamos WHERE id=?");
  $st->bind_param("i",$id);
  $st->execute();
  $res=$st->get_result();
  $row_eliminado=$res->fetch_assoc();
  $st->close();
  
  if ($row_eliminado && $row_eliminado['imagen'] && is_file(UPLOAD_DIR.$row_eliminado['imagen'])) 
      @unlink(UPLOAD_DIR.$row_eliminado['imagen']);
  
  $st=$c->prepare("DELETE FROM prestamos WHERE id=?");
  $st->bind_param("i",$id);
  $st->execute();
  $st->close(); 
  $c->close();
  
  // Guardar en sesión para el toast
  if ($row_eliminado) {
      $_SESSION['toast_prestamo'] = [
          'tipo' => 'eliminado',
          'deudor' => $row_eliminado['deudor'],
          'prestamista' => $row_eliminado['prestamista'],
          'monto' => $row_eliminado['monto'],
          'fecha' => $row_eliminado['fecha'],
          'empresa' => $row_eliminado['empresa'] ?? '',
          'imagen' => $row_eliminado['imagen']
      ];
  }
  
  go('?view=cards');
}

// ===== UI =====

// Obtener toast de sesión y limpiarlo
$toast_data = $_SESSION['toast_prestamo'] ?? null;
if (isset($_SESSION['toast_prestamo'])) unset($_SESSION['toast_prestamo']);
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Préstamos | Admin</title>
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

 .bulkbar{display:flex;gap:10px;align-items:center;margin:8px 0 0;flex-wrap:wrap}
 .bulkpanel{display:none;margin-top:10px;border:1px dashed #e5e7eb;border-radius:12px;padding:12px;background:#fafafa}
 .badge{background:#111;color:#fff;border-radius:999px;padding:2px 8px;font-size:12px;font-weight:700}
 .cardSel{display:flex;align-items:center;gap:8px;margin-bottom:6px}
 .sticky-actions{position:sticky; top:10px; align-self:flex-start}

 .card-comision { border-left: 4px solid #0b5ed7; background: #F0F9FF !important; }
 .comision-badge { background: #0b5ed7 !important; color: white !important; }
 .comision-info { background: #EAF5FF !important; border: 1px solid #BAE6FD !important; }
 .comision-text { color: #0369A1 !important; font-weight: 600; }

 .resumen-filtro { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 16px; margin-bottom: 16px; }
 .resumen-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-top: 12px; }
 .resumen-item { background: white; border-radius: 8px; padding: 12px; text-align: center; }
 .resumen-valor { font-size: 18px; font-weight: 800; color: #0369a1; }
 .resumen-label { font-size: 12px; color: #6b7280; margin-top: 4px; }

 .switch-container { display: flex; align-items: center; gap: 8px; background: #f8f9fa; padding: 8px 12px; border-radius: 12px; border: 1px solid #e5e7eb; }
 .switch-label { font-size: 14px; font-weight: 600; color: #374151; }
 .switch-group { display:flex; gap:6px; }
 .switch-pill { display:flex; align-items:center; }
 .switch-pill input { display:none; }
 .switch-pill span { font-size:12px; padding:4px 10px; border-radius:999px; border:1px solid #e5e7eb; background:#fff; cursor:pointer; }
 .switch-pill input:checked + span { background:#0b5ed7; color:#fff; border-color:#0b5ed7; }

 .card-pagado { border-left: 4px solid #10b981; background: #f0fdf4 !important; opacity: 0.8; }
 .pagado-badge { background: #10b981 !important; color: white !important; }
 .text-pagado { color: #065f46 !important; font-weight: 600; }

 .select2-container { width: 100% !important; }
 .select2-selection { border: 1px solid #e5e7eb !important; border-radius: 12px !important; padding: 8px !important; height: 45px !important; }
 .select2-selection__arrow { height: 43px !important; }
 .select2-search__field { border-radius: 8px !important; padding: 6px !important; }
 .select2-container--default .select2-search--dropdown .select2-search__field:focus { outline: none; border-color: var(--primary); }

 /* TOAST flotante */
 .toast-prestamo {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1100;
    min-width: 320px;
    max-width: 450px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2);
    border-left: 5px solid #0b5ed7;
    animation: slideInRight 0.3s ease-out;
 }
 @keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
 }
 @keyframes slideOutRight {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
 }
 .toast-prestamo .toast-header {
    background: #0b5ed7;
    color: white;
    border-radius: 8px 8px 0 0;
    padding: 12px 15px;
 }
 .toast-prestamo .toast-body {
    padding: 15px;
    font-size: 13px;
    max-height: 400px;
    overflow-y: auto;
 }
 .toast-prestamo .mensaje-copiado {
    position: absolute;
    bottom: 10px;
    right: 10px;
    background: #0b5ed7;
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    opacity: 0;
    transition: opacity 0.3s;
 }
 .toast-prestamo .mensaje-copiado.show {
    opacity: 1;
 }
 .btn-copiar-mensaje {
    background: #0b5ed7;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    color: white;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.2s;
    width: 100%;
 }
 .btn-copiar-mensaje:hover {
    background: #0a58ca;
 }
 pre.mensaje-formateado {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 8px;
    font-family: monospace;
    font-size: 12px;
    white-space: pre-wrap;
    word-break: break-word;
    border: 1px solid #e9ecef;
    margin-bottom: 12px;
 }
</style>
</head><body>

<div class="tabs">
  <a class="active" href="?view=cards">📇 Tarjetas</a>
  <a class="btn gray" href="?action=new&view=cards" style="margin-left:auto">➕ Crear</a>
</div>

<!-- ================== TOAST DE PRÉSTAMO ================== -->
<?php if ($toast_data): 
    $titulo = match($toast_data['tipo']) {
        'creado' => '✅ Préstamo Creado',
        'editado' => '✏️ Préstamo Editado',
        'eliminado' => '🗑️ Préstamo Eliminado',
        default => 'Préstamo'
    };
    $color_borde = match($toast_data['tipo']) {
        'creado' => '#0b5ed7',
        'editado' => '#ffc107',
        'eliminado' => '#dc3545',
        default => '#0b5ed7'
    };
    $color_header = match($toast_data['tipo']) {
        'creado' => '#0b5ed7',
        'editado' => '#ffc107',
        'eliminado' => '#dc3545',
        default => '#0b5ed7'
    };
    
    $mensaje_formateado = "";
    if ($toast_data['tipo'] === 'eliminado') {
        $mensaje_formateado = "🗑️ *Préstamo eliminado correctamente*\n\n";
    } else {
        $mensaje_formateado = "✅ *Préstamo registrado exitosamente*\n\n";
    }
    $mensaje_formateado .= "👤 *Deudor:* " . h($toast_data['deudor']) . "\n";
    $mensaje_formateado .= "🏦 *Prestamista:* " . h($toast_data['prestamista']) . "\n";
    $mensaje_formateado .= "💰 *Monto:* $" . money($toast_data['monto']) . "\n";
    $mensaje_formateado .= "📅 *Fecha:* " . h($toast_data['fecha']) . "\n";
    if ($toast_data['tipo'] !== 'eliminado') {
        $mensaje_formateado .= "📸 *Evidencia:* " . (!empty($toast_data['imagen']) ? '✅ Adjuntada' : '❌ No adjuntada') . "\n";
    }
    if (!empty($toast_data['empresa'])) {
        $mensaje_formateado .= "🏢 *Empresa:* " . h($toast_data['empresa']) . "\n";
    }
    if ($toast_data['tipo'] !== 'eliminado') {
        $mensaje_formateado .= "\nAtajos rápidos: /prestamos /pagar";
    }
?>
<div class="toast-prestamo" id="toastPrestamo" style="border-left-color: <?= $color_borde ?>;">
    <div class="toast-header" style="background: <?= $color_header ?>; <?= $toast_data['tipo'] === 'editado' ? 'color: #000;' : 'color: white;' ?>">
        <strong><?= $titulo ?></strong>
        <button type="button" class="btn-close <?= $toast_data['tipo'] === 'editado' ? '' : 'btn-close-white' ?> ms-auto" onclick="cerrarToast()" aria-label="Cerrar"></button>
    </div>
    <div class="toast-body">
        <pre class="mensaje-formateado" id="mensajeParaCopiar"><?= htmlspecialchars($mensaje_formateado) ?></pre>
        <button class="btn-copiar-mensaje" onclick="copiarMensaje()">📋 Copiar mensaje</button>
        <div class="mensaje-copiado" id="mensajeCopiado">¡Copiado!</div>
    </div>
</div>
<?php endif; ?>

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

  $conn = db();
  
  $deudores_opts = [];
  $res_deudores = $conn->query("SELECT nombre FROM deudores_admin WHERE owner_chat_id IN (6133806918, 6674396003) ORDER BY nombre");
  while($row = $res_deudores->fetch_assoc()) {
    $deudores_opts[] = $row['nombre'];
  }
  
  $prestamistas_opts = [];
  $res_prestamistas = $conn->query("SELECT nombre FROM prestamistas_admin WHERE owner_chat_id IN (6133806918, 6674396003) ORDER BY nombre");
  while($row = $res_prestamistas->fetch_assoc()) {
    $prestamistas_opts[] = $row['nombre'];
  }
  
  $empresas_opts = [];
  $res_empresas = $conn->query("SELECT DISTINCT empresa FROM prestamos WHERE empresa IS NOT NULL AND empresa != '' ORDER BY empresa");
  while($row = $res_empresas->fetch_assoc()) {
    $empresas_opts[] = $row['empresa'];
  }
  $conn->close();

  $row = ['deudor'=>'','prestamista'=>'','monto'=>'','fecha'=>'','empresa'=>'','imagen'=>null];
  if ($action==='edit'){
    $c=db();
    $st=$c->prepare("SELECT deudor,prestamista,monto,fecha,empresa,imagen FROM prestamos WHERE id=?");
    $st->bind_param("i",$id);
    $st->execute();
    $res=$st->get_result();
    $row=$res->fetch_assoc() ?: $row;
    $st->close(); $c->close();
  }
?>
  <div class="card">
    <div class="row" style="margin-bottom:10px">
      <div class="title"><?= $action==='new'?'Nuevo préstamo':'Editar préstamo #'.h($id) ?></div>
    </div>
    <?php if(!empty($err)): ?>
      <div class="error" style="margin-bottom:10px"><?= h($err) ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" action="?action=<?= $action==='new'?'create':'edit&id='.$id ?>&view=cards">
      <div class="row" style="gap:12px;flex-wrap:wrap">
        <div class="field" style="min-width:220px;flex:1">
          <label>Deudor *</label>
          <select name="deudor" id="deudorSelect2" required style="width:100%">
            <option value="">Selecciona o escribe un deudor...</option>
            <?php foreach($deudores_opts as $opt): ?>
              <option value="<?= h($opt) ?>" <?= $row['deudor'] === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="min-width:220px;flex:1">
          <label>Prestamista *</label>
          <select name="prestamista" id="prestamistaSelect2" required style="width:100%">
            <option value="">Selecciona o escribe un prestamista...</option>
            <?php foreach($prestamistas_opts as $opt): ?>
              <option value="<?= h($opt) ?>" <?= $row['prestamista'] === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="min-width:160px">
          <label>Monto *</label>
          <input name="monto" type="number" step="1" min="0" required value="<?= h($row['monto']) ?>">
        </div>
        <div class="field" style="min-width:160px">
          <label>Fecha *</label>
          <input name="fecha" type="date" required value="<?= h($row['fecha']) ?>">
        </div>
        <div class="field" style="min-width:200px;flex:1">
          <label>Empresa (opcional)</label>
          <select name="empresa" id="empresaSelect2" style="width:100%">
            <option value="">-- Sin empresa --</option>
            <?php foreach($empresas_opts as $opt): ?>
              <option value="<?= h($opt) ?>" <?= ($row['empresa'] ?? '') === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
            <?php endforeach; ?>
          </select>
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
        <a class="btn gray" href="?view=cards">Cancelar</a>
      </div>
    </form>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script>
  $(document).ready(function() {
      $('#deudorSelect2').select2({
          width: '100%',
          placeholder: 'Selecciona o escribe un deudor...',
          allowClear: true,
          tags: true,
          createTag: function(params) {
              var term = $.trim(params.term);
              if (term === '') return null;
              return { id: term, text: term, newOption: true };
          },
          language: { noResults: function() { return "Escribe para agregar un nuevo deudor"; } }
      });
      
      $('#prestamistaSelect2').select2({
          width: '100%',
          placeholder: 'Selecciona o escribe un prestamista...',
          allowClear: true,
          tags: true,
          createTag: function(params) {
              var term = $.trim(params.term);
              if (term === '') return null;
              return { id: term, text: term, newOption: true };
          },
          language: { noResults: function() { return "Escribe para agregar un nuevo prestamista"; } }
      });
      
      $('#empresaSelect2').select2({
          width: '100%',
          placeholder: 'Selecciona o escribe una empresa...',
          allowClear: true,
          tags: true,
          createTag: function(params) {
              var term = $.trim(params.term);
              if (term === '') return null;
              return { id: term, text: term, newOption: true };
          }
      });
  });
  </script>
<?php
// ====== LIST (SOLO TARJETAS) ======
else:

  $q   = trim($_GET['q']  ?? '');
  $fp  = trim($_GET['fp'] ?? '');
  $fd  = trim($_GET['fd'] ?? '');
  $fe  = trim($_GET['fe'] ?? '');
  $fecha_desde = trim($_GET['fecha_desde'] ?? '');
  $fecha_hasta = trim($_GET['fecha_hasta'] ?? '');
  $estado_pago = $_GET['estado_pago'] ?? 'no_pagados';

  $qNorm  = mbnorm($q);
  $fpNorm = mbnorm($fp);
  $fdNorm = mbnorm($fd);
  $feNorm = mbnorm($fe);

  $conn=db();

  $whereBase = "1=1";
  if ($estado_pago === 'no_pagados') {
    $whereBase = "pagado = 0";
  } elseif ($estado_pago === 'pagados') {
    $whereBase = "pagado = 1";
  }

  $prestMap = [];
  $resPL = $conn->query("SELECT prestamista FROM prestamos WHERE $whereBase");
  while($rowPL=$resPL->fetch_row()){
    $norm = mbnorm($rowPL[0]);
    if ($norm==='') continue;
    if (!isset($prestMap[$norm])) $prestMap[$norm] = $rowPL[0];
  }
  ksort($prestMap, SORT_NATURAL);

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

  $deudMap = [];
  if ($feNorm !== '') {
    $sqlDeud = "SELECT DISTINCT deudor FROM prestamos WHERE LOWER(TRIM(empresa)) = ? AND $whereBase ORDER BY deudor";
    $stDeud = $conn->prepare($sqlDeud);
    $stDeud->bind_param("s", $feNorm);
    $stDeud->execute();
    $resDeud = $stDeud->get_result();
  } else {
    $resDeud = $conn->query("SELECT DISTINCT deudor FROM prestamos WHERE $whereBase ORDER BY deudor");
  }
  
  while($rowDL = ($feNorm !== '' ? $resDeud->fetch_assoc() : $resDeud->fetch_row())) {
    $deudorValor = $feNorm !== '' ? $rowDL['deudor'] : $rowDL[0];
    $norm = mbnorm($deudorValor);
    if ($norm === '') continue;
    if (!isset($deudMap[$norm])) $deudMap[$norm] = $deudorValor;
  }
  if ($feNorm !== '') $stDeud->close();

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

  $sql = "
      SELECT id,deudor,prestamista,monto,fecha,imagen,created_at,pagado,pagado_at,
             empresa,
             comision_gestor_nombre, comision_gestor_porcentaje, comision_base_monto, 
             comision_origen_prestamista, comision_origen_porcentaje
      FROM prestamos
      WHERE $where
      ORDER BY pagado ASC, id DESC";
  $st=$conn->prepare($sql);
  if($types) $st->bind_param($types, ...$params);
  $st->execute();
  $rs=$st->get_result();

  $sumas = ['capital' => 0, 'count' => 0];
  if ($fecha_desde !== '' || $fecha_hasta !== '' || $fdNorm !== '' || $fpNorm !== '' || $feNorm!=='' || $estado_pago !== 'no_pagados') {
    $sqlSumas = "SELECT COUNT(*) AS n, SUM(monto) AS capital FROM prestamos WHERE $where";
    $stSumas=$conn->prepare($sqlSumas);
    if($types) $stSumas->bind_param($types, ...$params);
    $stSumas->execute();
    $sumas = $stSumas->get_result()->fetch_assoc() ?: $sumas;
    $stSumas->close();
  }
?>
    <div class="card" style="margin-bottom:16px">
      <form class="toolbar" method="get" id="filtroForm">
        <input type="hidden" name="view" value="cards">
        <input name="q" placeholder="🔎 Buscar (deudor / prestamista)" value="<?= h($q) ?>" style="flex:1;min-width:220px">
        
        <div class="field" style="min-width:200px;flex:1">
          <label>Prestamista</label>
          <select name="fp" id="prestamistaSelect" class="select2-filter">
            <option value="">Todos los prestamistas</option>
            <?php foreach($prestMap as $norm=>$label): ?>
              <option value="<?= h($norm) ?>" <?= $fpNorm===$norm?'selected':'' ?>><?= h(mbtitle($label)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field" style="min-width:200px;flex:1">
          <label>Empresa</label>
          <select name="fe" id="empresaSelect" class="select2-filter">
            <option value="">Todas las empresas</option>
            <?php foreach($empMap as $norm=>$label): ?>
              <option value="<?= h($norm) ?>" <?= $feNorm===$norm?'selected':'' ?>><?= h(mbtitle($label)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field" style="min-width:200px;flex:1">
          <label>Deudor</label>
          <select name="fd" id="deudorSelect" class="select2-filter">
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
              <input type="radio" name="estado_pago" value="no_pagados" <?= $estado_pago === 'no_pagados' ? 'checked' : '' ?> onchange="this.form.submit()">
              <span>No pagados</span>
            </label>
            <label class="switch-pill">
              <input type="radio" name="estado_pago" value="pagados" <?= $estado_pago === 'pagados' ? 'checked' : '' ?> onchange="this.form.submit()">
              <span>Pagados</span>
            </label>
            <label class="switch-pill">
              <input type="radio" name="estado_pago" value="todos" <?= $estado_pago === 'todos' ? 'checked' : '' ?> onchange="this.form.submit()">
              <span>Todos</span>
            </label>
          </div>
        </div>

        <button class="btn" type="submit">Filtrar</button>
        <?php if ($q!=='' || $fpNorm!=='' || $fdNorm!=='' || $feNorm!=='' || $fecha_desde!=='' || $fecha_hasta!=='' || $estado_pago !== 'no_pagados'): ?>
          <a class="btn gray" href="?view=cards">Quitar filtro</a>
        <?php endif; ?>
      </form>
    </div>

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
            echo implode(' • ', $filtros);
          ?>
        </div>
        <div class="resumen-grid">
          <div class="resumen-item">
            <div class="resumen-valor"><?= (int)($sumas['n'] ?? 0) ?></div>
            <div class="resumen-label">Préstamos</div>
          </div>
          <div class="resumen-item">
            <div class="resumen-valor">$ <?= money($sumas['capital'] ?? 0) ?></div>
            <div class="resumen-label">Capital Total</div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($rs->num_rows === 0): ?>
      <div class="card"><span class="subtitle">(sin registros)</span></div>
    <?php else: ?>
      <form id="bulkForm" class="card" method="post" action="?action=bulk_update">
        <input type="hidden" name="view" value="cards">

        <div class="row" style="margin-bottom:8px">
          <div class="title">Selecciona tarjetas</div>
          <div class="sticky-actions" style="display:flex;gap:8px;align-items:center">
            <label class="subtitle" style="display:flex;gap:8px;align-items:center">
              <input id="chkAll" type="checkbox"> Seleccionar todo (página)
            </label>
            <button type="button" class="btn gray small" id="btnToggleBulk">✏️ Editar selección</button>
            <button type="submit" class="btn small" formaction="?action=bulk_mark_paid" onclick="return confirm('¿Marcar como pagados los préstamos seleccionados?')">✔ Préstamo pagado</button>
            <button type="submit" class="btn gray small" formaction="?action=bulk_mark_unpaid" onclick="return confirm('¿Marcar como NO pagados los préstamos seleccionados?')">↩ NO pagado</button>
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
          ?>
            <div class="card <?= $cardClass ?>">
              <div class="cardSel">
                <input class="chkRow" type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>">
                <div class="subtitle">#<?= h($r['id']) ?></div>
                <?php if ($esComision): ?>
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
                </div>
                <span class="chip"><?= h($r['fecha']) ?></span>
              </div>

              <?php if ($esComision): ?>
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
                    <div class="k comision-text">Origen</div>
                    <div class="v comision-text"><?= h($r['comision_origen_prestamista']) ?></div>
                  </div>
                  <div class="item">
                    <div class="k comision-text">% Origen</div>
                    <div class="v comision-text"><?= h($r['comision_origen_porcentaje']) ?>%</div>
                  </div>
                </div>
              <?php endif; ?>

              <div class="pairs" style="margin-top:12px">
                <div class="item">
                  <div class="k">Monto</div>
                  <div class="v">$ <?= money($r['monto']) ?></div>
                </div>
              </div>

              <div class="row" style="margin-top:12px">
                <div class="subtitle">Creado: <?= h($r['created_at']) ?></div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                  <a class="btn gray small" href="?action=edit&id=<?= $r['id'] ?>&view=cards">✏️ Editar</a>
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
            <button class="btn" type="submit" onclick="return confirm('¿Aplicar cambios a la selección?')">💾 Aplicar a seleccionadas</button>
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
  $('.select2-filter').select2({
    width: '100%',
    placeholder: 'Seleccionar...',
    allowClear: true,
    language: { noResults: function() { return "No se encontraron resultados"; } }
  });
  
  const empresaSelect = $('#empresaSelect');
  const deudorSelect = $('#deudorSelect');
  const estadoPagoRadios = document.querySelectorAll('input[name="estado_pago"]');
  let deudorSeleccionado = deudorSelect.val();
  
  function cargarDeudoresPorEmpresa(empresaNormalizada, estadoPago) {
    if (!empresaNormalizada) {
      cargarTodosDeudores(estadoPago);
      return;
    }
    deudorSelect.html('<option value="">Cargando deudores...</option>');
    fetch(`ajax_cargar_deudores.php?empresa=${encodeURIComponent(empresaNormalizada)}&estado=${estadoPago}`)
      .then(response => response.json())
      .then(data => {
        let options = '<option value="">Todos los deudores</option>';
        data.forEach(deudor => {
          const selected = (deudor.norm === deudorSeleccionado) ? 'selected' : '';
          options += `<option value="${deudor.norm}" ${selected}>${deudor.nombre}</option>`;
        });
        deudorSelect.html(options);
        deudorSelect.select2({ width: '100%', placeholder: 'Seleccionar deudor...', allowClear: true });
        if (deudorSeleccionado) deudorSelect.val(deudorSeleccionado).trigger('change');
      })
      .catch(error => { console.error('Error:', error); deudorSelect.html('<option value="">Error</option>'); });
  }
  
  function cargarTodosDeudores(estadoPago) {
    deudorSelect.html('<option value="">Cargando...</option>');
    fetch(`ajax_cargar_deudores.php?empresa=&estado=${estadoPago}`)
      .then(response => response.json())
      .then(data => {
        let options = '<option value="">Todos los deudores</option>';
        data.forEach(deudor => {
          const selected = (deudor.norm === deudorSeleccionado) ? 'selected' : '';
          options += `<option value="${deudor.norm}" ${selected}>${deudor.nombre}</option>`;
        });
        deudorSelect.html(options);
        deudorSelect.select2({ width: '100%', placeholder: 'Seleccionar deudor...', allowClear: true });
        if (deudorSeleccionado) deudorSelect.val(deudorSeleccionado).trigger('change');
      })
      .catch(error => { console.error('Error:', error); });
  }
  
  empresaSelect.on('change', function() {
    deudorSeleccionado = deudorSelect.val();
    const estadoPago = document.querySelector('input[name="estado_pago"]:checked')?.value || 'no_pagados';
    cargarDeudoresPorEmpresa($(this).val(), estadoPago);
  });
  
  estadoPagoRadios.forEach(radio => {
    radio.addEventListener('change', function() {
      deudorSeleccionado = deudorSelect.val();
      const empresa = empresaSelect.val();
      if (empresa) cargarDeudoresPorEmpresa(empresa, this.value);
      else cargarTodosDeudores(this.value);
    });
  });
});

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
    if(chkAll) chkAll.checked = n === chkRows.length;
  }
  if (chkAll) chkAll.addEventListener('change', () => { chkRows.forEach(c => { c.checked = chkAll.checked; }); updateCount(); });
  chkRows.forEach(c => c.addEventListener('change', updateCount));
  updateCount();
  if (btnTog) btnTog.addEventListener('click', () => { 
    const any = chkRows.some(c=>c.checked);
    if (!any) { alert('Selecciona al menos una tarjeta para editar.'); return; }
    panel.style.display = (panel.style.display==='none'||panel.style.display==='')?'block':'none';
  });
  if (btnClose) btnClose.addEventListener('click', () => { panel.style.display = 'none'; });
})();

function submitDelete(id){
  if(!confirm('¿Eliminar #'+id+'?')) return;
  const f = document.createElement('form');
  f.method = 'post';
  f.action = '?action=delete&id='+id;
  document.body.appendChild(f);
  f.submit();
}

function cerrarToast() {
  const toast = document.getElementById('toastPrestamo');
  if (toast) {
    toast.style.animation = 'slideOutRight 0.3s ease-out forwards';
    setTimeout(() => { toast.remove(); }, 300);
  }
}

function copiarMensaje() {
  const mensaje = document.getElementById('mensajeParaCopiar');
  const texto = mensaje.innerText || mensaje.textContent;
  navigator.clipboard.writeText(texto).then(() => {
    const copiado = document.getElementById('mensajeCopiado');
    copiado.classList.add('show');
    setTimeout(() => { copiado.classList.remove('show'); }, 2000);
  }).catch(err => { alert('No se pudo copiar el mensaje.'); });
}

setTimeout(() => { const toast = document.getElementById('toastPrestamo'); if (toast) cerrarToast(); }, 10000);
</script>
</body></html>