<?php
/*********************************************************
 * admin_prestamos.php — CRUD + Tarjetas
 * - Filtro dinámico: deudores según empresa seleccionada
 * - Dropdowns con búsqueda (Select2)
 * - SISTEMA DE ABONOS CON VISTA PREVIA AGREGADO
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

/* ===== ACCIÓN: PROCESAR ABONO (NUEVO) ===== */
if ($action==='procesar_abono' && $_SERVER['REQUEST_METHOD']==='POST'){
    $prestamo_id = (int)($_POST['prestamo_id'] ?? 0);
    $monto_abono = (float)($_POST['monto_abono'] ?? 0);
    
    if (!$prestamo_id || $monto_abono <= 0) {
        go(BASE_URL . '?view=cards&error=datos_invalidos');
    }
    
    $conn = db();
    
    // Obtener datos del préstamo original
    $sql = "SELECT p.*,
                   CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) END AS meses,
                   (p.monto * 
                    CASE 
                      WHEN p.fecha >= '2025-10-29' THEN COALESCE(p.comision_origen_porcentaje, 13)
                      ELSE COALESCE(p.comision_origen_porcentaje, 10)
                    END / 100 *
                    CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, p.fecha, CURDATE()) END) AS interes_generado
            FROM prestamos p
            WHERE p.id = ? AND (p.pagado = 0 OR p.pagado IS NULL)";
    
    $st = $conn->prepare($sql);
    $st->bind_param("i", $prestamo_id);
    $st->execute();
    $res = $st->get_result();
    $prestamo = $res->fetch_assoc();
    
    if (!$prestamo) {
        $conn->close();
        go(BASE_URL . '?view=cards&error=prestamo_no_encontrado');
    }
    
    $capital_actual = (float)$prestamo['monto'];
    $interes_generado = (float)$prestamo['interes_generado'];
    
    // CALCULAR DISTRIBUCIÓN DEL ABONO
    $nuevo_prestamo_id = null;
    
    if ($monto_abono >= $interes_generado) {
        // Paga TODO el interés
        $interes_pagado = $interes_generado;
        $restante = $monto_abono - $interes_generado;
        
        if ($restante >= $capital_actual) {
            // Paga TODO el capital
            $capital_pagado = $capital_actual;
            
            // Marcar préstamo como pagado
            $upd = $conn->prepare("UPDATE prestamos SET pagado = 1, pagado_at = NOW() WHERE id = ?");
            $upd->bind_param("i", $prestamo_id);
            $upd->execute();
            $upd->close();
            
            $mensaje = "✅ Préstamo #{$prestamo_id} PAGADO COMPLETAMENTE";
            
        } else {
            // Paga parte del capital - CREAR NUEVO PRÉSTAMO
            $capital_pagado = $restante;
            $nuevo_capital = $capital_actual - $capital_pagado;
            
            // Marcar original como pagado
            $upd = $conn->prepare("UPDATE prestamos SET pagado = 1, pagado_at = NOW() WHERE id = ?");
            $upd->bind_param("i", $prestamo_id);
            $upd->execute();
            $upd->close();
            
            // Crear NUEVO préstamo con el capital restante
            $sql_new = "INSERT INTO prestamos 
                        (deudor, prestamista, monto, fecha, imagen, created_at, 
                         empresa, comision_gestor_nombre, comision_gestor_porcentaje,
                         comision_base_monto, comision_origen_prestamista, comision_origen_porcentaje)
                        VALUES (?, ?, ?, CURDATE(), ?, NOW(), ?, ?, ?, ?, ?, ?)";
            
            $st_new = $conn->prepare($sql_new);
            
            $deudor = $prestamo['deudor'];
            $prestamista = $prestamo['prestamista'];
            $imagen = $prestamo['imagen'];
            $empresa = $prestamo['empresa'];
            $gestor_nombre = $prestamo['comision_gestor_nombre'];
            $gestor_porcentaje = $prestamo['comision_gestor_porcentaje'];
            $base_monto = $prestamo['comision_base_monto'];
            $origen_prestamista = $prestamo['comision_origen_prestamista'];
            $origen_porcentaje = $prestamo['comision_origen_porcentaje'];
            
            $st_new->bind_param("ssdssssddds", 
                $deudor, $prestamista, $nuevo_capital, $imagen,
                $empresa, $gestor_nombre, $gestor_porcentaje,
                $base_monto, $origen_prestamista, $origen_porcentaje
            );
            
            $st_new->execute();
            $nuevo_prestamo_id = $st_new->insert_id;
            $st_new->close();
            
            $mensaje = "✅ Abono aplicado: Interés: $" . money($interes_pagado) . 
                       ", Capital: $" . money($capital_pagado) . 
                       ". Se creó NUEVO PRÉSTAMO #{$nuevo_prestamo_id} por $" . money($nuevo_capital);
        }
    } else {
        // No alcanza para pagar todo el interés
        $interes_pagado = $monto_abono;
        $capital_pagado = 0;
        
        // Aquí podrías guardar el abono parcial si tuvieras campo para ello
        // Por ahora solo mostramos mensaje
        
        $pendiente = $interes_generado - $monto_abono;
        $mensaje = "⚠️ Abono no cubre intereses. Interés pendiente: $" . money($pendiente);
    }
    
    $conn->close();
    
    // Redirigir con mensaje
    $msg = urlencode($mensaje);
    $url = BASE_URL . "?view=cards&msg_abono={$msg}&highlight={$prestamo_id}";
    if ($nuevo_prestamo_id) {
        $url .= "&nuevo={$nuevo_prestamo_id}";
    }
    go($url);
}

/* ===== ACCIÓN: OBTENER PRÉSTAMOS DEL DEUDOR (AJAX) ===== */
if ($action==='get_prestamos_deudor' && $_SERVER['REQUEST_METHOD']==='GET'){
    header('Content-Type: application/json');
    
    $deudor_norm = $_GET['deudor'] ?? '';
    
    if (!$deudor_norm) {
        echo json_encode([]);
        exit;
    }
    
    $conn = db();
    
    $sql = "SELECT id, monto, fecha,
                   CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) END AS meses,
                   (monto * 
                    CASE 
                      WHEN fecha >= '2025-10-29' THEN COALESCE(comision_origen_porcentaje, 13)
                      ELSE COALESCE(comision_origen_porcentaje, 10)
                    END / 100 *
                    CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) END) AS interes_generado
            FROM prestamos 
            WHERE LOWER(TRIM(deudor)) = ? AND (pagado = 0 OR pagado IS NULL)
            ORDER BY id DESC";
    
    $st = $conn->prepare($sql);
    $st->bind_param("s", $deudor_norm);
    $st->execute();
    $res = $st->get_result();
    
    $prestamos = [];
    while ($row = $res->fetch_assoc()) {
        $prestamos[] = [
            'id' => $row['id'],
            'monto' => $row['monto'],
            'interes' => $row['interes_generado'],
            'meses' => $row['meses'],
            'texto' => "#{$row['id']} - $" . money($row['monto']) . 
                       " (Interés: $" . money($row['interes_generado']) . ", {$row['meses']} meses)"
        ];
    }
    
    echo json_encode($prestamos);
    
    $st->close();
    $conn->close();
    exit;
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
  $img = save_image($_FILES['imagen']??null);

  if ($deudor && $prestamista && is_numeric($monto) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)){
    $c=db();
    $st=$c->prepare("INSERT INTO prestamos (deudor,prestamista,monto,fecha,imagen,created_at) VALUES (?,?,?,?,?,NOW())");
    $st->bind_param("ssdss",$deudor,$prestamista,$monto,$fecha,$img);
    $st->execute();
    $st->close(); $c->close();
    go('?msg=creado&view='.urlencode($view));
  } else {
    $err="Completa todos los campos correctamente.";
  }
}

if ($action==='edit' && $_SERVER['REQUEST_METHOD']==='POST' && $id>0){
  $deudor=trim($_POST['deudor']??'');
  $prestamista=trim($_POST['prestamista']??'');
  $monto=trim($_POST['monto']??'');
  $fecha=trim($_POST['fecha']??'');
  $keep = isset($_POST['keep']) ? 1:0;
  $img = save_image($_FILES['imagen']??null);

  if ($deudor && $prestamista && is_numeric($monto) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)){
    $c=db();
    if ($img){
      $st=$c->prepare("UPDATE prestamos SET deudor=?,prestamista=?,monto=?,fecha=?,imagen=? WHERE id=?");
      $st->bind_param("ssdssi",$deudor,$prestamista,$monto,$fecha,$img,$id);
    } else {
      if ($keep){
        $st=$c->prepare("UPDATE prestamos SET deudor=?,prestamista=?,monto=?,fecha=? WHERE id=?");
        $st->bind_param("ssdsi",$deudor,$prestamista,$monto,$fecha,$id);
      } else {
        $st=$c->prepare("UPDATE prestamos SET deudor=?,prestamista=?,monto=?,fecha=?,imagen=NULL WHERE id=?");
        $st->bind_param("ssdsi",$deudor,$prestamista,$monto,$fecha,$id);
      }
    }
    $st->execute();
    $st->close(); $c->close();
    go('?msg=editado&view='.urlencode($view));
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
  go('?msg=eliminado&view='.urlencode($view));
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
 
 /* NUEVOS ESTILOS PARA SISTEMA DE ABONOS */
 .abono-card { background: linear-gradient(145deg, #f0f9ff, #e6f2ff); border-left: 4px solid #0b5ed7; margin-bottom: 20px; }
 .vista-previa-panel { background: white; border-radius: 16px; padding: 20px; margin-top: 15px; border: 2px dashed #0b5ed7; }
 .badge-abono { background: #059669; color: white; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
 .highlight { border: 3px solid #059669 !important; box-shadow: 0 0 20px #059669 !important; transition: all 0.5s; }
 .nuevo-highlight { border: 3px solid #0b5ed7 !important; box-shadow: 0 0 20px #0b5ed7 !important; }
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

<?php if (!empty($_GET['msg_abono'])): ?>
  <div class="msg" style="margin-bottom:14px; background:#d1fae5; color:#065f46;">
    <?= h(urldecode($_GET['msg_abono'])) ?>
  </div>
<?php endif; ?>

<?php if (!empty($_GET['error'])): ?>
  <div class="error" style="margin-bottom:14px">
    <?= match($_GET['error']){
        'datos_invalidos' => 'Datos inválidos para el abono',
        'prestamo_no_encontrado' => 'Préstamo no encontrado o ya pagado',
        default => 'Error procesando abono'
    } ?>
  </div>
<?php endif; ?>

<!-- ===== NUEVO SISTEMA DE ABONOS CON VISTA PREVIA ===== -->
<div class="card abono-card">
  <div class="row" style="margin-bottom:15px;">
    <div class="title">💰 Registrar Abono</div>
    <span class="badge-abono">Paga intereses primero</span>
  </div>
  
  <div class="row" style="gap:15px; flex-wrap:wrap; align-items:flex-end;">
    <!-- Deudor -->
    <div class="field" style="min-width:250px; flex:2;">
      <label>👤 Deudor</label>
      <select id="deudorAbono" class="select2-abono">
        <option value="">Seleccionar deudor...</option>
        <?php
        // Este código se ejecutará cuando estemos en modo lista
        // Por ahora lo dejamos preparado, se llenará con JS después
        ?>
      </select>
    </div>
    
    <!-- Préstamo -->
    <div class="field" style="min-width:300px; flex:3;">
      <label>📋 Préstamo</label>
      <select id="prestamoAbono" class="select2-abono" disabled>
        <option value="">Primero seleccione deudor...</option>
      </select>
    </div>
    
    <!-- Monto -->
    <div class="field" style="min-width:200px; flex:1;">
      <label>💵 Monto a abonar</label>
      <input type="number" id="montoAbono" step="1000" min="1" placeholder="Ej: 4000000" style="font-weight:600;">
    </div>
    
    <!-- Botón Vista Previa -->
    <div class="field" style="flex:0;">
      <button type="button" class="btn" id="btnVistaPrevia" style="padding:12px 25px;">👁️ Vista Previa</button>
    </div>
  </div>
  
  <!-- PANEL DE VISTA PREVIA -->
  <div id="vistaPreviaPanel" style="display:none; margin-top:20px;">
    <div class="vista-previa-panel">
      <div class="row" style="margin-bottom:15px;">
        <h3 style="margin:0; color:#0b5ed7;">📊 Resultado de Vista Previa</h3>
        <span class="chip" id="vistaPrestamoInfo"></span>
      </div>
      
      <div class="resumen-grid" id="vistaPreviaContenido">
        <div class="resumen-item">Seleccione un préstamo y monto</div>
      </div>
      
      <div class="row" style="margin-top:20px; justify-content:flex-end;">
        <button type="button" class="btn gray" id="btnCancelarVista">Cancelar</button>
        <button type="button" class="btn" id="btnConfirmarAbono" style="margin-left:10px; background:#059669; display:none;">
          ✅ Confirmar y Procesar Abono
        </button>
      </div>
    </div>
  </div>
</div>

<?php
// ====== NEW / EDIT FORMS ======
if ($action==='new' || ($action==='edit' && $id>0 && $_SERVER['REQUEST_METHOD']!=='POST')):
  $row = ['deudor'=>'','prestamista'=>'','monto'=>'','fecha'=>'','imagen'=>null];
  if ($action==='edit'){
    $c=db();
    $st=$c->prepare("SELECT deudor,prestamista,monto,fecha,imagen FROM prestamos WHERE id=?");
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
          <input name="deudor" required value="<?= h($row['deudor']) ?>">
        </div>
        <div class="field" style="min-width:220px;flex:1">
          <label>Prestamista *</label>
          <input name="prestamista" required value="<?= h($row['prestamista']) ?>">
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
        <a class="btn gray" href="?view=cards">Cancelar</a>
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

  // Incluir campo pagado y calcular interés correctamente con tasa variable
  $sql = "
      SELECT id,deudor,prestamista,monto,fecha,imagen,created_at,pagado,pagado_at,
             empresa,
             comision_gestor_nombre, comision_gestor_porcentaje, comision_base_monto, 
             comision_origen_prestamista, comision_origen_porcentaje,
             CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END AS meses,
             
             /* Interés del prestamista (dueño del capital) - TASA VARIABLE */
             (monto * 
              CASE 
                WHEN fecha >= '2025-10-29' THEN COALESCE(comision_origen_porcentaje, 13)
                ELSE COALESCE(comision_origen_porcentaje, 10)
              END / 100 *
              CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS interes_prestamista,
             
             /* Comisión del gestor */
             (COALESCE(comision_base_monto, monto) * COALESCE(comision_gestor_porcentaje, 0) / 100 *
              CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS comision_gestor,
             
             /* Interés total (prestamista + gestor) */
             ((monto * 
               CASE 
                 WHEN fecha >= '2025-10-29' THEN COALESCE(comision_origen_porcentaje, 13)
                 ELSE COALESCE(comision_origen_porcentaje, 10)
               END / 100) + 
              (COALESCE(comision_base_monto, monto) * COALESCE(comision_gestor_porcentaje, 0) / 100)) *
              CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END AS interes_total,
             
             /* Total a pagar (monto + interés total) */
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
      ORDER BY pagado ASC, id DESC";
  $st=$conn->prepare($sql);
  if($types) $st->bind_param($types, ...$params);
  $st->execute();
  $rs=$st->get_result();

  // Calcular sumas para el rango de fechas / filtros seleccionado
  $sumas = ['capital' => 0, 'interes' => 0, 'total' => 0, 'count' => 0];
  if ($fecha_desde !== '' || $fecha_hasta !== '' || $fdNorm !== '' || $fpNorm !== '' || $feNorm !== '' || $estado_pago !== 'no_pagados') {
    $sqlSumas = "
        SELECT COUNT(*) AS n,
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
        WHERE $where";
    $stSumas=$conn->prepare($sqlSumas);
    if($types) $stSumas->bind_param($types, ...$params);
    $stSumas->execute();
    $sumas = $stSumas->get_result()->fetch_assoc() ?: $sumas;
    $stSumas->close();
  }
?>
    <!-- Toolbar de filtros -->
    <div class="card" style="margin-bottom:16px">
      <form class="toolbar" method="get" id="filtroForm">
        <input type="hidden" name="view" value="cards">
        <input name="q" placeholder="🔎 Buscar (deudor / prestamista)" value="<?= h($q) ?>" style="flex:1;min-width:220px">
        
        <!-- Prestamista con Select2 -->
        <div class="field" style="min-width:200px;flex:1">
          <label>Prestamista</label>
          <select name="fp" id="prestamistaSelect" title="Prestamista" class="select2-filter">
            <option value="">Todos los prestamistas</option>
            <?php foreach($prestMap as $norm=>$label): ?>
              <option value="<?= h($norm) ?>" <?= $fpNorm===$norm?'selected':'' ?>><?= h(mbtitle($label)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Empresa con Select2 -->
        <div class="field" style="min-width:200px;flex:1">
          <label>Empresa</label>
          <select name="fe" id="empresaSelect" title="Empresa" class="select2-filter">
            <option value="">Todas las empresas</option>
            <?php foreach($empMap as $norm=>$label): ?>
              <option value="<?= h($norm) ?>" <?= $feNorm===$norm?'selected':'' ?>><?= h(mbtitle($label)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Deudor con Select2 (se actualiza dinámicamente) -->
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
        
        <!-- SWITCH 3 ESTADOS: No pagados / Pagados / Todos -->
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
          <a class="btn gray" href="?view=cards">Quitar filtro</a>
        <?php endif; ?>
      </form>
      <div class="subtitle">Interés variable: 13% desde 2025-10-29, 10% para préstamos anteriores.</div>
    </div>

    <!-- Resumen cuando hay filtros -->
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
            <div class="resumen-valor"><?= (int)($sumas['n'] ?? $sumas['count'] ?? 0) ?></div>
            <div class="resumen-label">Préstamos</div>
          </div>
          <div class="resumen-item">
            <div class="resumen-valor">$ <?= money($sumas['capital'] ?? 0) ?></div>
            <div class="resumen-label">Capital</div>
          </div>
          <div class="resumen-item">
            <div class="resumen-valor">$ <?= money($sumas['interes'] ?? 0) ?></div>
            <div class="resumen-label">Interés</div>
          </div>
          <div class="resumen-item">
            <div class="resumen-valor">$ <?= money($sumas['total'] ?? 0) ?></div>
            <div class="resumen-label">Total</div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($rs->num_rows === 0): ?>
      <div class="card"><span class="subtitle">(sin registros)</span></div>
    <?php else: ?>
      <!-- FORM para selección múltiple + edición en lote -->
      <form id="bulkForm" class="card" method="post" action="?action=bulk_update">
        <input type="hidden" name="view" value="cards">

        <div class="row" style="margin-bottom:8px">
          <div class="title">Selecciona tarjetas</div>
          <div class="sticky-actions" style="display:flex;gap:8px;align-items:center">
            <label class="subtitle" style="display:flex;gap:8px;align-items:center">
              <input id="chkAll" type="checkbox"> Seleccionar todo (página)
            </label>
            <button type="button" class="btn gray small" id="btnToggleBulk">✏️ Editar selección</button>
            <!-- Botón: marcar como pagados los seleccionados -->
            <button 
              type="submit" 
              class="btn small" 
              formaction="?action=bulk_mark_paid"
              onclick="return confirm('¿Marcar como pagados los préstamos seleccionados?')">
              ✔ Préstamo pagado
            </button>
            <!-- Botón: marcar como NO pagados los seleccionados -->
            <button 
              type="submit" 
              class="btn gray small" 
              formaction="?action=bulk_mark_unpaid"
              onclick="return confirm('¿Marcar como NO pagados los préstamos seleccionados?')">
              ↩ NO pagado
            </button>
            <span class="badge" id="selCount">0 seleccionadas</span>
          </div>
        </div>

        <div class="grid-cards">
          <?php while($r=$rs->fetch_assoc()):
            // Determinar si es una comisión
            $esComision = !empty($r['comision_gestor_nombre']);
            $esPagado = (bool)($r['pagado'] ?? false);
            
            // Determinar clases CSS según estado
            $cardClass = '';
            $badgeClass = 'chip';
            
            if ($esComision) {
              $cardClass = 'card-comision';
              $badgeClass = 'comision-badge';
            } elseif ($esPagado) {
              $cardClass = 'card-pagado';
              $badgeClass = 'pagado-badge';
            }
            
            // Calcular porcentaje total
            $porcentajeTotal = (float)($r['comision_origen_porcentaje'] ?? 
              (strtotime($r['fecha']) >= strtotime('2025-10-29') ? 13 : 10)) + 
              (float)($r['comision_gestor_porcentaje'] ?? 0);
          ?>
            <div class="card <?= $cardClass ?>" data-id="<?= (int)$r['id'] ?>" data-deudor="<?= h($r['deudor']) ?>" data-monto="<?= $r['monto'] ?>" data-interes="<?= $r['interes_total'] ?>">
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

              <!-- Información de comisión si existe -->
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
                <div class="item">
                  <div class="k">Meses</div>
                  <div class="v"><?= h($r['meses']) ?></div>
                </div>
                <div class="item">
                  <div class="k">Interés</div>
                  <div class="v">$ <?= money($r['interes_total']) ?></div>
                </div>
                <div class="item">
                  <div class="k">Total</div>
                  <div class="v">$ <?= money($r['total']) ?></div>
                </div>
              </div>

              <!-- Desglose interés si hay comisión -->
              <?php if ($esComision): ?>
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
              <?php endif; ?>

              <div class="row" style="margin-top:12px">
                <div class="subtitle">Creado: <?= h($r['created_at']) ?></div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                  <a class="btn gray small" href="?action=edit&id=<?= $r['id'] ?>&view=cards">✏️ Editar</a>
                  <!-- Botón Eliminar sin formulario anidado -->
                  <button class="btn red small" type="button" onclick="submitDelete(<?= (int)$r['id'] ?>)">🗑️ Eliminar</button>
                </div>
              </div>
            </div>
          <?php endwhile; ?>
        </div>

        <!-- Panel de edición en lote -->
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
endif; // forms / list
?>

<!-- Incluir jQuery y Select2 JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// Inicializar Select2 en los dropdowns de filtro
$(document).ready(function() {
  // Aplicar Select2 a los filtros
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
  
  // Inicializar Select2 para abonos
  $('.select2-abono').select2({
    width: '100%',
    placeholder: 'Seleccionar...',
    allowClear: true,
    language: {
      noResults: function() {
        return "No se encontraron resultados";
      }
    }
  });
  
  // Guardar referencia a los selects
  const empresaSelect = $('#empresaSelect');
  const deudorSelect = $('#deudorSelect');
  const estadoPagoRadios = document.querySelectorAll('input[name="estado_pago"]');
  
  // Variable para guardar el deudor seleccionado antes de cambiar
  let deudorSeleccionado = deudorSelect.val();
  
  // Función para cargar deudores según empresa
  function cargarDeudoresPorEmpresa(empresaNormalizada, estadoPago) {
    if (!empresaNormalizada) {
      // Si no hay empresa, cargar todos los deudores
      cargarTodosDeudores(estadoPago);
      return;
    }
    
    // Mostrar loading
    deudorSelect.html('<option value="">Cargando deudores...</option>');
    
    // Hacer petición AJAX
    fetch(`ajax_cargar_deudores.php?empresa=${encodeURIComponent(empresaNormalizada)}&estado=${estadoPago}`)
      .then(response => {
        if (!response.ok) throw new Error('Error en la respuesta');
        return response.json();
      })
      .then(data => {
        // Reconstruir el dropdown
        let options = '<option value="">Todos los deudores</option>';
        
        data.forEach(deudor => {
          const selected = (deudor.norm === deudorSeleccionado) ? 'selected' : '';
          options += `<option value="${deudor.norm}" ${selected}>${deudor.nombre}</option>`;
        });
        
        deudorSelect.html(options);
        
        // Re-aplicar Select2
        deudorSelect.select2({
          width: '100%',
          placeholder: 'Seleccionar deudor...',
          allowClear: true
        });
        
        // Restaurar selección si existe
        if (deudorSeleccionado) {
          deudorSelect.val(deudorSeleccionado).trigger('change');
        }
      })
      .catch(error => {
        console.error('Error cargando deudores:', error);
        deudorSelect.html('<option value="">Error cargando deudores</option>');
      });
  }
  
  // Función para cargar todos los deudores
  function cargarTodosDeudores(estadoPago) {
    // Mostrar loading
    deudorSelect.html('<option value="">Cargando todos los deudores...</option>');
    
    fetch(`ajax_cargar_deudores.php?empresa=&estado=${estadoPago}`)
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
  
  // Evento cuando cambia la empresa
  empresaSelect.on('change', function() {
    const empresaNormalizada = $(this).val();
    const estadoPago = document.querySelector('input[name="estado_pago"]:checked')?.value || 'no_pagados';
    
    // Guardar el deudor seleccionado antes de cambiar
    deudorSeleccionado = deudorSelect.val();
    
    // Cargar deudores según la empresa seleccionada
    cargarDeudoresPorEmpresa(empresaNormalizada, estadoPago);
  });
  
  // Evento cuando cambia el estado de pago
  estadoPagoRadios.forEach(radio => {
    radio.addEventListener('change', function() {
      const empresaNormalizada = empresaSelect.val();
      const estadoPago = this.value;
      
      // Guardar el deudor seleccionado
      deudorSeleccionado = deudorSelect.val();
      
      // Recargar deudores con el nuevo estado
      if (empresaNormalizada) {
        cargarDeudoresPorEmpresa(empresaNormalizada, estadoPago);
      } else {
        cargarTodosDeudores(estadoPago);
      }
    });
  });

  // ===== NUEVO: SISTEMA DE ABONOS CON VISTA PREVIA =====
  const deudorAbono = $('#deudorAbono');
  const prestamoAbono = $('#prestamoAbono');
  const montoAbono = $('#montoAbono');
  const btnVistaPrevia = $('#btnVistaPrevia');
  const vistaPanel = $('#vistaPreviaPanel');
  const vistaContenido = $('#vistaPreviaContenido');
  const vistaPrestamoInfo = $('#vistaPrestamoInfo');
  const btnConfirmar = $('#btnConfirmarAbono');
  const btnCancelar = $('#btnCancelarVista');
  
  let datosVistaPrevia = null;
  
  // Llenar select de deudores para abonos con los mismos datos
  function llenarDeudoresAbono() {
    const options = '<option value="">Seleccionar deudor...</option>' + 
                    Array.from(document.querySelectorAll('#deudorSelect option')).map(opt => {
                      if (opt.value) {
                        return `<option value="${opt.value}">${opt.text}</option>`;
                      }
                      return '';
                    }).join('');
    deudorAbono.html(options);
  }
  llenarDeudoresAbono();
  
  // Cargar préstamos cuando cambia deudor
  deudorAbono.on('change', function() {
    const deudor = $(this).val();
    
    if (!deudor) {
      prestamoAbono.prop('disabled', true).html('<option value="">Primero seleccione deudor...</option>');
      return;
    }
    
    prestamoAbono.prop('disabled', true).html('<option value="">Cargando préstamos...</option>');
    
    fetch(`?action=get_prestamos_deudor&deudor=${encodeURIComponent(deudor)}`)
      .then(response => response.json())
      .then(data => {
        if (data.length === 0) {
          prestamoAbono.html('<option value="">No hay préstamos activos</option>').prop('disabled', true);
        } else {
          let options = '<option value="">Seleccione préstamo...</option>';
          data.forEach(p => {
            options += `<option value="${p.id}" data-monto="${p.monto}" data-interes="${p.interes}">${p.texto}</option>`;
          });
          prestamoAbono.html(options).prop('disabled', false);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        prestamoAbono.html('<option value="">Error cargando préstamos</option>').prop('disabled', true);
      });
  });
  
  // Vista Previa
  btnVistaPrevia.on('click', function() {
    const prestamoId = prestamoAbono.val();
    const monto = parseFloat(montoAbono.val());
    
    if (!prestamoId || !monto || monto <= 0) {
      alert('Seleccione un préstamo y monto válido');
      return;
    }
    
    const selectedOption = prestamoAbono.find('option:selected');
    const capital = parseFloat(selectedOption.data('monto')) || 0;
    const interes = parseFloat(selectedOption.data('interes')) || 0;
    
    // CALCULAR
    let interesPagado = 0;
    let capitalPagado = 0;
    let nuevoCapital = capital;
    let mensaje = '';
    let escenario = '';
    
    if (monto >= interes) {
      interesPagado = interes;
      const restante = monto - interes;
      
      if (restante >= capital) {
        capitalPagado = capital;
        nuevoCapital = 0;
        mensaje = '¡PRÉSTAMO PAGADO COMPLETAMENTE!';
        escenario = 'pagado';
      } else {
        capitalPagado = restante;
        nuevoCapital = capital - restante;
        mensaje = `Se creará NUEVO PRÉSTAMO por $${nuevoCapital.toLocaleString('es-CO')}`;
        escenario = 'nuevo';
      }
    } else {
      interesPagado = monto;
      capitalPagado = 0;
      nuevoCapital = capital;
      mensaje = `Interés pendiente: $${(interes - monto).toLocaleString('es-CO')}`;
      escenario = 'parcial';
    }
    
    datosVistaPrevia = {
      prestamo_id: prestamoId,
      monto_abono: monto,
      capital_actual: capital,
      interes_generado: interes,
      interes_pagado: interesPagado,
      capital_pagado: capitalPagado,
      nuevo_capital: nuevoCapital,
      mensaje: mensaje,
      escenario: escenario
    };
    
    // Mostrar resultados
    const html = `
      <div class="resumen-item">
        <div class="resumen-valor">$${monto.toLocaleString('es-CO')}</div>
        <div class="resumen-label">Monto Abonado</div>
      </div>
      <div class="resumen-item">
        <div class="resumen-valor">$${interesPagado.toLocaleString('es-CO')}</div>
        <div class="resumen-label">Interés Pagado</div>
      </div>
      <div class="resumen-item">
        <div class="resumen-valor">$${capitalPagado.toLocaleString('es-CO')}</div>
        <div class="resumen-label">Capital Pagado</div>
      </div>
      <div class="resumen-item">
        <div class="resumen-valor">$${nuevoCapital.toLocaleString('es-CO')}</div>
        <div class="resumen-label">Capital Restante</div>
      </div>
      <div class="resumen-item" style="grid-column:span 2; background:${escenario==='pagado'?'#d1fae5':escenario==='nuevo'?'#fef9c3':'#fee2e2'}">
        <div class="resumen-label">${mensaje}</div>
      </div>
    `;
    
    vistaContenido.html(html);
    vistaPrestamoInfo.text(`Préstamo #${prestamoId}`);
    vistaPanel.slideDown();
    btnConfirmar.show();
  });
  
  // Confirmar abono
  btnConfirmar.on('click', function() {
    if (!datosVistaPrevia) return;
    
    const form = $('<form method="post" action="?action=procesar_abono"></form>');
    form.append(`<input type="hidden" name="prestamo_id" value="${datosVistaPrevia.prestamo_id}">`);
    form.append(`<input type="hidden" name="monto_abono" value="${datosVistaPrevia.monto_abono}">`);
    $('body').append(form);
    form.submit();
  });
  
  // Cancelar
  btnCancelar.on('click', function() {
    vistaPanel.slideUp();
    btnConfirmar.hide();
    datosVistaPrevia = null;
  });
  
  // Resaltar préstamos si vienen de un abono
  <?php if (isset($_GET['highlight'])): ?>
    $(`.card[data-id="<?= (int)$_GET['highlight'] ?>"]`).addClass('highlight').get(0).scrollIntoView({behavior:'smooth'});
  <?php endif; ?>
  
  <?php if (isset($_GET['nuevo'])): ?>
    $(`.card[data-id="<?= (int)$_GET['nuevo'] ?>"]`).addClass('nuevo-highlight');
  <?php endif; ?>
});

// JS: selección múltiple + eliminar sin anidar formularios
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

/* Eliminar: crea un form temporal POST para no anidar formularios */
function submitDelete(id){
  if(!confirm('¿Eliminar #'+id+'?')) return;
  const f = document.createElement('form');
  f.method = 'post';
  f.action = '?action=delete&id='+id;
  document.body.appendChild(f);
  f.submit();
}
</script>

</body></html>