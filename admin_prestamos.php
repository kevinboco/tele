<?php
/*********************************************************
 * admin_prestamos.php — CRUD + Tarjetas + Visual 3-nodos
 * - Normaliza nombres (no distingue mayúsc/minúsc)
 * - Interés variable según comision_origen_porcentaje + comisión gestor
 * - Deudor: valor prestado + fecha + interés + total
 * - Selector de deudores (fuera del SVG) + "Préstamo pagado"
 * - En el 3er nodo: Ganancia + Total prestado (pendiente)
 * - Colorear nodo 2 según meses (1, 2, 3+)
 * - Filtro por Deudor + resumen de sumas del deudor
 * - Selección múltiple en Tarjetas + Edición en lote
 * 
 * 
 * 
 * - COMISIONES en tarjetas con color azul
 * - Interés 13% para préstamos desde 2025-10-29, 10% para anteriores
 * - FILTRO: No mostrar préstamos con pagado=1 (por defecto)
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
function db(): mysqli { $m = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); if ($m->connect_errno) exit("Error DB: ".$m->connect_error); $m->set_charset('utf8mb4'); return $m; }
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
$view   = $_GET['view']   ?? 'cards'; // 'cards' | 'graph'
$id = (int)($_GET['id'] ?? 0);

// ===== Upload helper =====
function save_image($file): ?string {
  if (empty($file) || ($file['error']??4) === 4) return null;
  if ($file['error'] !== UPLOAD_ERR_OK) return null;
  if ($file['size'] > MAX_UPLOAD_BYTES) return null;
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->file($file['tmp_name']);
  $ext = match ($mime) { 'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif', default=>null };
  if(!$ext) return null;
  $name = time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
  if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR.$name)) return null;
  return $name;
}

/* ===== Acción: marcar pagado SOLO deudores seleccionados (vista graph) ===== */
if ($action==='mark_paid' && $_SERVER['REQUEST_METHOD']==='POST'){
  $nodes = $_POST['nodes'] ?? []; // array de CSVs de ids
  if (!is_array($nodes)) $nodes = [];
  $all = [];
  foreach($nodes as $csv){
    foreach(explode(',', (string)$csv) as $raw){
      $n=(int)trim($raw);
      if($n>0) $all[$n]=1;
    }
  }
  $ids = array_keys($all);
  if ($ids){
    $c=db();
    foreach(array_chunk($ids,200) as $chunk){
      $ph = implode(',', array_fill(0,count($chunk),'?'));
      $types = str_repeat('i', count($chunk));
      $sql = "UPDATE prestamos SET pagado=1, pagado_at=NOW() WHERE id IN ($ph) AND (pagado IS NULL OR pagado=0)";
      $st=$c->prepare($sql); $st->bind_param($types, ...$chunk); $st->execute(); $st->close();
    }
    $c->close();
    go('?view=graph&msg=pagados');
  } else {
    go('?view=graph&msg=nada');
  }
}

/* ===== Acción: Edición en Lote desde TARJETAS ===== */
if ($action==='bulk_update' && $_SERVER['REQUEST_METHOD']==='POST'){
  // ids seleccionados
  $ids = $_POST['ids'] ?? [];
  if (!is_array($ids)) $ids = [];
  $ids = array_values(array_unique(array_map(fn($v)=> (int)$v, $ids)));

  if (!$ids) {
    go(BASE_URL.'?view=cards&msg=noselect');
  }

  // Campos opcionales a aplicar
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

  // Construcción del IN (...)
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
  // Redirigir SIEMPRE a la URL absoluta de Tarjetas
  go(BASE_URL.'?view=cards&msg='.$msg);
}

/* ===== NUEVO: Acción masiva "Préstamo pagado" desde TARJETAS ===== */
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

/* ===== NUEVO: Acción masiva "Marcar como NO pagado" desde TARJETAS ===== */
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

  // NUEVO: campo empresa en el create (si ya lo manejas en el form, lo puedes leer aquí)
  $empresa = trim($_POST['empresa'] ?? '');

  if ($deudor && $prestamista && is_numeric($monto) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)){
    $c=db();
    // Si ya creaste la columna empresa en la tabla, agrégala aquí:
    if ($empresa !== '') {
      $st=$c->prepare("INSERT INTO prestamos (deudor,prestamista,monto,fecha,imagen,empresa,created_at) VALUES (?,?,?,?,?,?,NOW())");
      $st->bind_param("ssdsss",$deudor,$prestamista,$monto,$fecha,$img,$empresa);
    } else {
      // Si prefieres que quede '' cuando no se envía
      $empresa = '';
      $st=$c->prepare("INSERT INTO prestamos (deudor,prestamista,monto,fecha,imagen,empresa,created_at) VALUES (?,?,?,?,?,?,NOW())");
      $st->bind_param("ssdsss",$deudor,$prestamista,$monto,$fecha,$img,$empresa);
    }
    $st->execute();
    $st->close(); $c->close(); go('?msg=creado&view='.urlencode($view));
  } else { $err="Completa todos los campos correctamente."; }
}

if ($action==='edit' && $_SERVER['REQUEST_METHOD']==='POST' && $id>0){
  $deudor=trim($_POST['deudor']??''); 
  $prestamista=trim($_POST['prestamista']??'');
  $monto=trim($_POST['monto']??'');   
  $fecha=trim($_POST['fecha']??'');
  $empresa = trim($_POST['empresa'] ?? '');
  $keep = isset($_POST['keep']) ? 1:0;
  $img = save_image($_FILES['imagen']??null);

  if ($deudor && $prestamista && is_numeric($monto) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)){
    $c=db();
    if ($img){
      $st=$c->prepare("UPDATE prestamos SET deudor=?,prestamista=?,monto=?,fecha=?,imagen=?,empresa=? WHERE id=?");
      $st->bind_param("ssdsssi",$deudor,$prestamista,$monto,$fecha,$img,$empresa,$id);
    } else {
      if ($keep){
        $st=$c->prepare("UPDATE prestamos SET deudor=?,prestamista=?,monto=?,fecha=?,empresa=? WHERE id=?");
        $st->bind_param("ssds si",$deudor,$prestamista,$monto,$fecha,$empresa,$id);
      } else {
        $st=$c->prepare("UPDATE prestamos SET deudor=?,prestamista=?,monto=?,fecha=?,imagen=NULL,empresa=? WHERE id=?");
        $st->bind_param("ssdssi",$deudor,$prestamista,$monto,$fecha,$empresa,$id);
      }
    }
    $st->execute(); $st->close(); $c->close(); go('?msg=editado&view='.urlencode($view));
  } else { $err="Completa todos los campos correctamente."; }
}

if ($action==='delete' && $_SERVER['REQUEST_METHOD']==='POST' && $id>0){
  $c=db();
  $st=$c->prepare("SELECT imagen FROM prestamos WHERE id=?"); $st->bind_param("i",$id);
  $st->execute(); $st->bind_result($img); $st->fetch(); $st->close();
  if ($img && is_file(UPLOAD_DIR.$img)) @unlink(UPLOAD_DIR.$img);
  $st=$c->prepare("DELETE FROM prestamos WHERE id=?"); $st->bind_param("i",$id); $st->execute();
  $st->close(); $c->close(); go('?msg=eliminado&view='.urlencode($view));
}

// ===== UI =====
?><!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Préstamos | Admin</title>
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

 /* VISUAL */
 .group{background:#fff;border-radius:16px;box-shadow:0 6px 20px rgba(0,0,0,.06);padding:12px;margin-bottom:18px}
 .svgwrap{width:100%;overflow:auto;border:1px dashed #e5e7eb;border-radius:12px;background:#fafafa}
 .nodeRect{fill:#ffffff;stroke:#cbd5e1;stroke-width:1.2}
 .txt{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;font-size:13px;fill:#111}
 .mut{fill:#6b7280} .amt{font-weight:800}

 /* Selector de deudores */
 .selector{margin-top:10px;border-top:1px dashed #e5e7eb;padding-top:10px}
 .selhead{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
 .selgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:8px}
 .selitem{display:flex;gap:8px;align-items:flex-start;background:#fafbff;border:1px solid #eef2ff;border-radius:12px;padding:8px}
 .selitem .meta{font-size:12px;color:#555}
 @media (max-width:760px){ .pairs{grid-template-columns:1fr} }

 /* Colores por meses */
 .nodeRect.m1{ fill:#FFF8DB; }  /* 1 mes - amarillo suave */
 .nodeRect.m2{ fill:#FFE9D6; }  /* 2 meses - naranja suave */
 .nodeRect.m3{ fill:#FFE1E1; }  /* 3+ meses - rojo suave */

 /* NUEVO: controles de selección múltiple en tarjetas */
 .bulkbar{display:flex;gap:10px;align-items:center;margin:8px 0 0;flex-wrap:wrap}
 .bulkpanel{display:none;margin-top:10px;border:1px dashed #e5e7eb;border-radius:12px;padding:12px;background:#fafafa}
 .badge{background:#111;color:#fff;border-radius:999px;padding:2px 8px;font-size:12px;font-weight:700}
 .cardSel{display:flex;align-items:center;gap:8px;margin-bottom:6px}
 .sticky-actions{position:sticky; top:10px; align-self:flex-start}

 /* NUEVO: Estilos para comisiones en tarjetas */
 .card-comision { border-left: 4px solid #0b5ed7; background: #F0F9FF !important; }
 .comision-badge { background: #0b5ed7 !important; color: white !important; }
 .comision-info { background: #EAF5FF !important; border: 1px solid #BAE6FD !important; }
 .comision-text { color: #0369A1 !important; font-weight: 600; }

 /* NUEVO: Estilos para resumen de filtros */
 .resumen-filtro { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 16px; margin-bottom: 16px; }
 .resumen-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-top: 12px; }
 .resumen-item { background: white; border-radius: 8px; padding: 12px; text-align: center; }
 .resumen-valor { font-size: 18px; font-weight: 800; color: #0369a1; }
 .resumen-label { font-size: 12px; color: #6b7280; margin-top: 4px; }

 /* NUEVO: Estilos para switch de estado de pago (3 estados) */
 .switch-container { display: flex; align-items: center; gap: 8px; background: #f8f9fa; padding: 8px 12px; border-radius: 12px; border: 1px solid #e5e7eb; }
 .switch-label { font-size: 14px; font-weight: 600; color: #374151; }
 .switch-group { display:flex; gap:6px; }
 .switch-pill { display:flex; align-items:center; }
 .switch-pill input { display:none; }
 .switch-pill span { font-size:12px; padding:4px 10px; border-radius:999px; border:1px solid #e5e7eb; background:#fff; cursor:pointer; }
 .switch-pill input:checked + span { background:#0b5ed7; color:#fff; border-color:#0b5ed7; }

 /* NUEVO: Estilos para préstamos pagados */
 .card-pagado { border-left: 4px solid #10b981; background: #f0fdf4 !important; opacity: 0.8; }
 .pagado-badge { background: #10b981 !important; color: white !important; }
 .text-pagado { color: #065f46 !important; font-weight: 600; }
</style>
</head><body>

<div class="tabs">
  <a class="<?= $view==='cards'?'active':'' ?>" href="?view=cards">📇 Tarjetas</a>
  <a class="<?= $view==='graph'?'active':'' ?>" href="?view=graph">🕸️ Visual</a>
  <a class="btn gray" href="?action=new&view=<?= h($view) ?>" style="margin-left:auto">➕ Crear</a>
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
  $row = ['deudor'=>'','prestamista'=>'','monto'=>'','fecha'=>'','imagen'=>null,'empresa'=>''];
  if ($action==='edit'){
    $c=db(); 
    $st=$c->prepare("SELECT deudor,prestamista,monto,fecha,imagen,empresa FROM prestamos WHERE id=?");
    $st->bind_param("i",$id); 
    $st->execute(); 
    $res=$st->get_result(); 
    $row=$res->fetch_assoc() ?: $row;
    $st->close(); $c->close();
  }
?>
  <div class="card">
    <div class="row" style="margin-bottom:10px"><div class="title"><?= $action==='new'?'Nuevo préstamo':'Editar préstamo #'.h($id) ?></div></div>
    <?php if(!empty($err)): ?><div class="error" style="margin-bottom:10px"><?= h($err) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" action="?action=<?= $action==='new'?'create':'edit&id='.$id ?>&view=<?= h($view) ?>">
      <div class="row" style="gap:12px;flex-wrap:wrap">
        <div class="field" style="min-width:220px;flex:1"><label>Deudor *</label><input name="deudor" required value="<?= h($row['deudor']) ?>"></div>
        <div class="field" style="min-width:220px;flex:1"><label>Prestamista *</label><input name="prestamista" required value="<?= h($row['prestamista']) ?>"></div>
        <div class="field" style="min-width:200px;flex:1"><label>Empresa</label><input name="empresa" value="<?= h($row['empresa']) ?>" placeholder="Ej: Hospital"></div>
        <div class="field" style="min-width:160px"><label>Monto *</label><input name="monto" type="number" step="1" min="0" required value="<?= h($row['monto']) ?>"></div>
        <div class="field" style="min-width:160px"><label>Fecha *</label><input name="fecha" type="date" required value="<?= h($row['fecha']) ?>"></div>
        <div class="field" style="min-width:240px;flex:1">
          <label>Imagen (opcional)</label>
          <?php if ($action==='edit' && $row['imagen']): ?>
            <div style="margin-bottom:6px"><img class="thumb" src="uploads/<?= h($row['imagen']) ?>" alt=""></div>
            <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="keep" checked> Mantener imagen actual</label>
          <?php endif; ?>
          <input type="file" name="imagen" accept="image/*">
        </div>
      </div>
      <div class="row" style="margin-top:12px">
        <button class="btn" type="submit">💾 Guardar</button>
        <a class="btn gray" href="?view=<?= h($view) ?>">Cancelar</a>
      </div>
    </form>
  </div>
<?php
// ====== LIST ======
else:

  // ==== filtros ====
  $q   = trim($_GET['q']  ?? '');
  $fp  = trim($_GET['fp'] ?? ''); // prestamista (normalizado)
  $fd  = trim($_GET['fd'] ?? ''); // deudor (normalizado)
  $fe  = trim($_GET['fe'] ?? ''); // empresa (normalizado)
  $fecha_desde = trim($_GET['fecha_desde'] ?? '');
  $fecha_hasta = trim($_GET['fecha_hasta'] ?? '');
  // NUEVO: Filtro de estado de pago
  $estado_pago = $_GET['estado_pago'] ?? 'no_pagados'; // 'no_pagados', 'pagados', 'todos'

  $qNorm  = mbnorm($q);
  $fpNorm = mbnorm($fp);
  $fdNorm = mbnorm($fd);
  $feNorm = mbnorm($fe);

  $conn=db();

  // MODIFICADO: Filtrar según el estado de pago seleccionado
  $whereBase = "1=1";
  if ($estado_pago === 'no_pagados') {
    $whereBase = "pagado = 0";
  } elseif ($estado_pago === 'pagados') {
    $whereBase = "pagado = 1";
  }
  // Si es 'todos', no se aplica filtro por pagado

  // Filtro simple por empresa para combos de prestamistas y deudores
  $empresaCond = '';
  if ($feNorm !== '') {
    $esc = $conn->real_escape_string($feNorm);
    $empresaCond = " AND LOWER(TRIM(empresa)) = '".$esc."'";
  }

  // Combo empresas
  $empMap = [];
  $resEmp = $conn->query("SELECT empresa FROM prestamos WHERE $whereBase GROUP BY empresa");
  if ($resEmp) {
    while($rowEmp=$resEmp->fetch_row()){
      $emp = trim((string)$rowEmp[0]);
      if ($emp === '') continue;
      $norm = mbnorm($emp);
      if (!isset($empMap[$norm])) $empMap[$norm] = $emp;
    }
    $resEmp->free();
  }
  ksort($empMap, SORT_NATURAL);

  // Combo prestamistas
  $prestMap = [];
  $resPL = $conn->query("SELECT prestamista FROM prestamos WHERE $whereBase".$empresaCond);
  while($rowPL=$resPL->fetch_row()){
    $norm = mbnorm($rowPL[0]);
    if ($norm==='') continue;
    if (!isset($prestMap[$norm])) $prestMap[$norm] = $rowPL[0];
  }
  $resPL->free();
  ksort($prestMap, SORT_NATURAL);

  // Combo deudores
  $deudMap = [];
  $resDL = $conn->query("SELECT deudor FROM prestamos WHERE $whereBase".$empresaCond);
  while($rowDL=$resDL->fetch_row()){
    $norm = mbnorm($rowDL[0]);
    if ($norm==='') continue;
    if (!isset($deudMap[$norm])) $deudMap[$norm] = $rowDL[0];
  }
  $resDL->free();
  ksort($deudMap, SORT_NATURAL);

  if ($view==='cards'){
    // -------- TARJETAS --------
    $where = $whereBase; $types=""; $params=[];
    if ($q!==''){ $where.=" AND (LOWER(deudor) LIKE CONCAT('%',?,'%') OR LOWER(prestamista) LIKE CONCAT('%',?,'%'))"; $types.="ss"; $params[]=$qNorm; $params[]=$qNorm; }
    if ($fpNorm!==''){ $where.=" AND LOWER(TRIM(prestamista)) = ?"; $types.="s"; $params[]=$fpNorm; }
    if ($fdNorm!==''){ $where.=" AND LOWER(TRIM(deudor)) = ?"; $types.="s"; $params[]=$fdNorm; }
    if ($feNorm!==''){ $where.=" AND LOWER(TRIM(empresa)) = ?"; $types.="s"; $params[]=$feNorm; }
    if ($fecha_desde!==''){ $where.=" AND fecha >= ?"; $types.="s"; $params[]=$fecha_desde; }
    if ($fecha_hasta!==''){ $where.=" AND fecha <= ?"; $types.="s"; $params[]=$fecha_hasta; }

    // MODIFICADO: Incluir campo empresa y cálculos
    $sql = "
      SELECT id,deudor,prestamista,empresa,monto,fecha,imagen,created_at,pagado,pagado_at,
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
    $st=$conn->prepare($sql); if($types) $st->bind_param($types, ...$params); $st->execute(); $rs=$st->get_result();

    // NUEVO: Calcular sumas para el rango de fechas seleccionado
    $sumas = ['capital' => 0, 'interes' => 0, 'total' => 0, 'count' => 0];
    if ($fecha_desde !== '' || $fecha_hasta !== '' || $fdNorm !== '' || $fpNorm !== '' || $feNorm !== '' || $estado_pago !== 'todos') {
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
      $stSumas=$conn->prepare($sqlSumas); if($types) $stSumas->bind_param($types, ...$params); $stSumas->execute(); 
      $sumas = $stSumas->get_result()->fetch_assoc() ?: $sumas;
      $stSumas->close();
    }
?>
    <!-- Toolbar de filtros -->
    <div class="card" style="margin-bottom:16px">
      <form class="toolbar" method="get">
        <input type="hidden" name="view" value="cards">
        <input name="q" placeholder="🔎 Buscar (deudor / prestamista)" value="<?= h($q) ?>" style="flex:1;min-width:220px">
        <select name="fp" title="Prestamista">
          <option value="">Todos los prestamistas</option>
          <?php foreach($prestMap as $norm=>$label): ?>
            <option value="<?= h($norm) ?>" <?= $fpNorm===$norm?'selected':'' ?>><?= h(mbtitle($label)) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="fd" title="Deudor">
          <option value="">Todos los deudores</option>
          <?php foreach($deudMap as $norm=>$label): ?>
            <option value="<?= h($norm) ?>" <?= $fdNorm===$norm?'selected':'' ?>><?= h(mbtitle($label)) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="fe" title="Empresa">
          <option value="">Todas las empresas</option>
          <?php foreach($empMap as $norm=>$label): ?>
            <option value="<?= h($norm) ?>" <?= $feNorm===$norm?'selected':'' ?>><?= h(mbtitle($label)) ?></option>
          <?php endforeach; ?>
        </select>
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

    <!-- NUEVO: Mostrar resumen cuando hay filtros de fecha o persona/empresa -->
    <?php if ($fecha_desde !== '' || $fecha_hasta !== '' || $fdNorm !== '' || $fpNorm !== '' || $feNorm !== '' || $estado_pago !== 'no_pagados'): ?>
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
      <!-- FORM para selección múltiple + edición en lote (NO forms anidados dentro) -->
      <form id="bulkForm" class="card" method="post" action="?action=bulk_update">
        <!-- conservar filtros (no necesarios para redirección fija, pero no molestan) -->
        <input type="hidden" name="view" value="cards">

        <div class="row" style="margin-bottom:8px">
          <div class="title">Selecciona tarjetas</div>
          <div class="sticky-actions" style="display:flex;gap:8px;align-items:center">
            <label class="subtitle" style="display:flex;gap:8px;align-items:center">
              <input id="chkAll" type="checkbox"> Seleccionar todo (página)
            </label>
            <button type="button" class="btn gray small" id="btnToggleBulk">✏️ Editar selección</button>
            <!-- NUEVO BOTÓN: marcar como pagados los seleccionados -->
            <button 
              type="submit" 
              class="btn small" 
              formaction="?action=bulk_mark_paid"
              onclick="return confirm('¿Marcar como pagados los préstamos seleccionados?')">
              ✔ Préstamo pagado
            </button>
            <!-- NUEVO BOTÓN: marcar como NO pagados los seleccionados -->
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
                <a href="uploads/<?= h($r['imagen']) ?>" target="_blank"><img class="thumb" src="uploads/<?= h($r['imagen']) ?>" alt=""></a>
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

              <!-- NUEVO: Mostrar información de comisión si existe -->
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
                <div class="item"><div class="k">Monto</div><div class="v">$ <?= money($r['monto']) ?></div></div>
                <div class="item"><div class="k">Meses</div><div class="v"><?= h($r['meses']) ?></div></div>
                <div class="item"><div class="k">Interés</div><div class="v">$ <?= money($r['interes_total']) ?></div></div>
                <div class="item"><div class="k">Total</div><div class="v">$ <?= money($r['total']) ?></div></div>
              </div>

              <!-- Mostrar desglose del interés si hay comisión -->
              <?php if ($esComision): ?>
                <div class="pairs" style="margin-top:8px; font-size:12px;">
                  <div class="item"><div class="k">Interés Prestamista</div><div class="v">$ <?= money($r['interes_prestamista']) ?></div></div>
                  <div class="item"><div class="k">Comisión Gestor</div><div class="v">$ <?= money($r['comision_gestor']) ?></div></div>
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
          <div class="subtitle" style="margin-bottom:8px">Aplica solo a las tarjetas seleccionadas. Deja en blanco lo que no quieras cambiar.</div>
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

  } else {
    // -------- VISUAL 3-NODOS (CON FILTRO DE ESTADO y EMPRESA) --------
    $where = $whereBase; $types=""; $params=[];
    $qNormGraph = mbnorm($_GET['q'] ?? '');
    $fpNormGraph = mbnorm($_GET['fp'] ?? '');
    $feNormGraph = $feNorm; // mismo valor normalizado

    if ($qNormGraph!==''){ 
      $where.=" AND (LOWER(deudor) LIKE CONCAT('%',?,'%') OR LOWER(prestamista) LIKE CONCAT('%',?,'%'))"; 
      $types.="ss"; 
      $params[]=$qNormGraph; 
      $params[]=$qNormGraph; 
    }
    if ($fpNormGraph!==''){ 
      $where.=" AND LOWER(TRIM(prestamista)) = ?"; 
      $types.="s"; 
      $params[]=$fpNormGraph; 
    }
    if ($feNormGraph!==''){ 
      $where.=" AND LOWER(TRIM(empresa)) = ?"; 
      $types.="s"; 
      $params[]=$feNormGraph; 
    }

    // MODIFICADO: Cálculo correcto de intereses con comisiones y tasa variable
    $sql = "
      SELECT LOWER(TRIM(prestamista)) AS prest_key, MIN(prestamista) AS prest_display,
             LOWER(TRIM(deudor)) AS deud_key, MIN(deudor) AS deud_display,
             MIN(fecha) AS fecha_min,
             CASE WHEN CURDATE() < MIN(fecha)
                  THEN 0
                  ELSE TIMESTAMPDIFF(MONTH, MIN(fecha), CURDATE()) + 1
             END AS meses,
             SUM(monto) AS capital,
             
             /* Interés total (prestamista + gestor) - TASA VARIABLE */
             SUM(((monto * 
                  CASE 
                    WHEN fecha >= '2025-10-29' THEN COALESCE(comision_origen_porcentaje, 13)
                    ELSE COALESCE(comision_origen_porcentaje, 10)
                  END / 100) + 
                 (COALESCE(comision_base_monto, monto) * COALESCE(comision_gestor_porcentaje, 0) / 100)) *
                 CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS interes,
             
             /* Total a pagar */
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
      GROUP BY prest_key, deud_key
      ORDER BY prest_key ASC, deud_display ASC";
    $st=$conn->prepare($sql); if($types) $st->bind_param($types, ...$params); $st->execute(); $rs=$st->get_result();

    // Mapa de IDs por prestamista+deudor (para el selector)
    $sqlIds = "
      SELECT LOWER(TRIM(prestamista)) AS prest_key, LOWER(TRIM(deudor)) AS deud_key,
             GROUP_CONCAT(id) AS ids
      FROM prestamos
      WHERE $where
      GROUP BY prest_key, deud_key";
    $st2=$conn->prepare($sqlIds); if($types) $st2->bind_param($types, ...$params); $st2->execute(); $rsIds=$st2->get_result();
    $idsMap=[];
    while($row=$rsIds->fetch_assoc()){
      $p=$row['prest_key']; $d=$row['deud_key'];
      $idsMap[$p][$d] = preg_replace('/[^0-9,]/','', (string)$row['ids']);
    }
    $st2->close();

    // Estructura por prestamista + cálculo de ganancia y capital pendiente por prestamista
    $groups=[]; $ganPrest=[]; $capPendPrest=[];
    while($r=$rs->fetch_assoc()){
      $pkey=$r['prest_key']; $pdisp=$r['prest_display'];
      if(!isset($groups[$pkey])) $groups[$pkey]=['label'=>$pdisp,'rows'=>[]];
      $groups[$pkey]['rows'][]=$r;
      $ganPrest[$pkey]  = ($ganPrest[$pkey]  ?? 0) + (float)$r['interes'];
      $capPendPrest[$pkey] = ($capPendPrest[$pkey] ?? 0) + (float)$r['capital']; // solo capital NO pagado
    }
?>
    <div class="card" style="margin-bottom:16px">
      <form class="toolbar" method="get">
        <input type="hidden" name="view" value="graph">
        <input name="q" placeholder="🔎 Buscar (deudor / prestamista)" value="<?= h($_GET['q'] ?? '') ?>" style="flex:1;min-width:220px">
        <select name="fp"><option value="">Todos los prestamistas</option>
          <?php foreach($prestMap as $norm=>$label): ?><option value="<?= h($norm) ?>" <?= $fpNorm===$norm?'selected':'' ?>><?= h(mbtitle($label)) ?></option><?php endforeach; ?>
        </select>
        <select name="fe" title="Empresa">
          <option value="">Todas las empresas</option>
          <?php foreach($empMap as $norm=>$label): ?>
            <option value="<?= h($norm) ?>" <?= $feNorm===$norm?'selected':'' ?>><?= h(mbtitle($label)) ?></option>
          <?php endforeach; ?>
        </select>
        
        <!-- SWITCH 3 ESTADOS: No pagados / Pagados / Todos (vista gráfica) -->
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
        <?php if (!empty($_GET['q']) || $fpNorm!=='' || $feNorm!=='' || $estado_pago !== 'no_pagados'): ?><a class="btn gray" href="?view=graph">Quitar filtro</a><?php endif; ?>
      </form>
      <div class="subtitle">
        Diagrama: <strong>Prestamista ➜ Deudores (valor, fecha, interés, total) ➜ Ganancia</strong>.
        Debajo hay un selector para marcarlos como <strong>Préstamo pagado</strong>.
        El recuadro adicional muestra <strong>Total prestado (pendiente)</strong>.
        Interés: <strong>13% desde 2025-10-29, 10% para préstamos anteriores</strong>.
        <strong>NOTA: Mostrando préstamos: <?= $estado_pago === 'todos' ? 'TODOS' : ($estado_pago === 'pagados' ? 'PAGADOS' : 'NO PAGADOS') ?></strong>.
      </div>
    </div>

    <?php if ($rs->num_rows===0): ?>
      <div class="card"><span class="subtitle">(sin registros)</span></div>
    <?php else: foreach($groups as $pkey => $ginfo):
        $rows = $ginfo['rows']; $prestLabel = mbtitle($ginfo['label']); $n = count($rows);
        // Geometría
        $rowGap=100; $nodeH=100; $nodeW=320; $headH=52; $topPad=30;
        $firstY=$topPad+80; $lastY=$firstY+max(0,($n-1)*$rowGap); $centerY=($firstY+$lastY)/2; $height=max(250,(int)($lastY+110));
        $xL=140; $xC=560; $xR=1080; $prestY=(int)($centerY-$headH/2); $gainY=$prestY;
        $capPend = $capPendPrest[$pkey] ?? 0.0;
    ?>
      <form class="group" method="post" action="?action=mark_paid">
        <input type="hidden" name="view" value="graph">
        <input type="hidden" name="q" value="<?= h($_GET['q'] ?? '') ?>">
        <input type="hidden" name="fp" value="<?= h($fpNorm) ?>">
        <input type="hidden" name="fe" value="<?= h($feNorm) ?>">
        <input type="hidden" name="estado_pago" value="<?= h($estado_pago) ?>">

        <div class="title" style="margin:6px 10px 10px">Prestamista: <?= h($prestLabel) ?></div>

        <div class="svgwrap">
          <div class="subtitle" style="margin:6px 0 8px; padding:0 10px">
            <span class="chip" style="background:#FFF8DB">1 mes</span>
            <span class="chip" style="background:#FFE9D6">2 meses</span>
            <span class="chip" style="background:#FFE1E1">3+ meses</span>
          </div>

          <svg width="1320" height="<?= $height ?>" viewBox="0 0 1320 <?= $height ?>" xmlns="http://www.w3.org/2000/svg">
            <rect class="nodeRect" x="<?= $xL-90 ?>" y="<?= $prestY ?>" rx="12" ry="12" width="180" height="<?= $headH ?>"/>
            <text class="txt" x="<?= $xL-80 ?>" y="<?= $prestY+20 ?>">Prestamista</text>
            <text class="txt" x="<?= $xL-80 ?>" y="<?= $prestY+40 ?>"><tspan font-weight="800"><?= h($prestLabel) ?></tspan></text>

            <rect class="nodeRect" x="<?= $xR-120 ?>" y="<?= $gainY ?>" rx="12" ry="12" width="240" height="<?= $headH ?>"/>
            <text class="txt" x="<?= $xR-105 ?>" y="<?= $gainY+20 ?>">Ganancia (interés)</text>
            <text class="txt" x="<?= $xR-105 ?>" y="<?= $gainY+40 ?>">$ <?= money($ganPrest[$pkey] ?? 0) ?></text>

            <rect class="nodeRect" x="<?= $xR-120 ?>" y="<?= $gainY + $headH + 12 ?>" rx="12" ry="12" width="240" height="<?= $headH ?>"/>
            <text class="txt" x="<?= $xR-105 ?>" y="<?= $gainY + $headH + 12 + 20 ?>">Total prestado (pend.)</text>
            <text class="txt" x="<?= $xR-105 ?>" y="<?= $gainY + $headH + 12 + 40 ?>">$ <?= money($capPend) ?></text>

            <?php $i=0; foreach($rows as $r):
              $y=$firstY+($i*$rowGap); $boxY=$y-($nodeH/2);
              $cap='$ '.money($r['capital']); $int='$ '.money($r['interes']); $tot='$ '.money($r['total']); $date=h($r['fecha_min']); $deudLbl=mbtitle($r['deud_display']);
              $meses = (int)($r['meses'] ?? 0);
              $mcls  = ($meses >= 3) ? 'm3' : (($meses === 2) ? 'm2' : (($meses === 1) ? 'm1' : ''));
            ?>
              <line x1="<?= $xL+90 ?>" y1="<?= $prestY+$headH/2 ?>" x2="<?= $xC-10 ?>" y2="<?= $y ?>" stroke="#9ca3af" stroke-width="1.5" />
              <line x1="<?= $xC+$nodeW ?>" y1="<?= $y ?>" x2="<?= $xR-120 ?>" y2="<?= $gainY+$headH/2 ?>" stroke="#9ca3af" stroke-width="1.2" />

              <rect class="nodeRect <?= $mcls ?>" x="<?= $xC-10 ?>" y="<?= $boxY ?>" rx="12" ry="12" width="<?= $nodeW ?>" height="<?= $nodeH ?>"/>
              <text class="txt" x="<?= $xC ?>" y="<?= $boxY+22 ?>"><tspan font-weight="800"><?= h($deudLbl) ?></tspan></text>
              <text class="txt mut" x="<?= $xC ?>" y="<?= $boxY+40 ?>">valor prestado: <tspan class="amt" fill="#111"><?= $cap ?></tspan></text>
              <text class="txt mut" x="<?= $xC ?>" y="<?= $boxY+58 ?>">fecha: <tspan class="amt" fill="#111"><?= $date ?></tspan></text>
              <text class="txt mut" x="<?= $xC ?>" y="<?= $boxY+76 ?>">interés: <tspan class="amt" fill="#111"><?= $int ?></tspan> • total <tspan class="amt" fill="#111"><?= $tot ?></tspan></text>
            <?php $i++; endforeach; ?>
          </svg>
        </div>

        <?php if ($estado_pago !== 'pagados'): ?>
        <div class="selector">
          <div class="selhead">
            <div class="subtitle">Selecciona deudores para marcarlos como pagados:</div>
            <label class="subtitle" style="display:flex;gap:8px;align-items:center">
              <input type="checkbox" onclick="(function(ch){const f=ch.closest('form');f.querySelectorAll('input[name=\'nodes[]\']').forEach(i=>i.checked=ch.checked);})(this)"> Seleccionar todo
            </label>
          </div>
          <div class="selgrid">
            <?php foreach($rows as $r):
              $p = $r['prest_key']; $d = $r['deud_key'];
              $idsCsv = $idsMap[$p][$d] ?? '';
              if ($idsCsv==='') continue;
            ?>
              <label class="selitem">
                <input class="cb" type="checkbox" name="nodes[]" value="<?= h($idsCsv) ?>">
                <div>
                  <div><strong><?= h(mbtitle($r['deud_display'])) ?></strong></div>
                  <div class="meta">prestado: $ <?= money($r['capital']) ?> • interés: $ <?= money($r['interes']) ?> • total: $ <?= money($r['total']) ?> • fecha: <?= h($r['fecha_min']) ?></div>
                </div>
              </label>
            <?php endforeach; ?>
          </div>
          <div style="display:flex;justify-content:flex-end;margin-top:8px">
            <button class="btn small" type="submit" onclick="return confirm('¿Marcar como pagados los seleccionados?')">✔ Préstamo pagado</button>
          </div>
        </div>
        <?php endif; ?>
      </form>
    <?php endforeach; endif; ?>

<?php
    $st->close();
  }

  $conn->close();
endif; // list / graph
?>

<!-- JS: selección múltiple + eliminar sin anidar formularios -->
<script>
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
