<?php
/*********************************************************
 * admin_prestamos.php — CRUD con tarjetas (cards) + 10% mensual
 *********************************************************/

// ======= CONFIG =======
define('DB_HOST', 'mysql.hostinger.com');
define('DB_USER', 'u648222299_keboco5');
define('DB_PASS', 'Bucaramanga3011');
define('DB_NAME', 'u648222299_viajes');
const UPLOAD_DIR = __DIR__ . '/uploads/';
const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;
// ======================

if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0775, true);

function db(): mysqli {
  $m = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  if ($m->connect_errno) { exit("Error DB: ".$m->connect_error); }
  $m->set_charset('utf8mb4'); return $m;
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES,'UTF-8'); }
function money($n){ return number_format((float)$n,0,',','.'); }
function go($qs){ header("Location: ".$qs); exit; }

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// ===== helpers subida =====
function save_image($file): ?string {
  if (empty($file) || ($file['error']??4) === 4) return null;
  if ($file['error'] !== UPLOAD_ERR_OK) return null;
  if ($file['size'] > MAX_UPLOAD_BYTES) return null;
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->file($file['tmp_name']);
  $ext = match ($mime) {
    'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif', default=>null
  };
  if(!$ext) return null;
  $name = time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
  if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR.$name)) return null;
  return $name;
}

// ===== operaciones =====
if ($action==='create' && $_SERVER['REQUEST_METHOD']==='POST'){
  $deudor = trim($_POST['deudor']??'');
  $prestamista = trim($_POST['prestamista']??'');
  $monto = trim($_POST['monto']??'');
  $fecha = trim($_POST['fecha']??'');
  $img = save_image($_FILES['imagen']??null);

  if ($deudor && $prestamista && is_numeric($monto) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)){
    $c=db(); $st=$c->prepare("INSERT INTO prestamos (deudor,prestamista,monto,fecha,imagen,created_at) VALUES (?,?,?,?,?,NOW())");
    $st->bind_param("ssdss",$deudor,$prestamista,$monto,$fecha,$img); $st->execute();
    $st->close(); $c->close(); go('?msg=creado');
  } else { $err="Completa todos los campos correctamente."; }
}

if ($action==='edit' && $_SERVER['REQUEST_METHOD']==='POST' && $id>0){
  $deudor=trim($_POST['deudor']??''); $prestamista=trim($_POST['prestamista']??'');
  $monto=trim($_POST['monto']??'');   $fecha=trim($_POST['fecha']??'');
  $keep = isset($_POST['keep']) ? 1:0;
  $img = save_image($_FILES['imagen']??null);

  if ($deudor && $prestamista && is_numeric($monto) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)){
    $c=db();
    if ($img){ // nueva imagen
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
    $st->execute(); $st->close(); $c->close(); go('?msg=editado');
  } else { $err="Completa todos los campos correctamente."; }
}

if ($action==='delete' && $_SERVER['REQUEST_METHOD']==='POST' && $id>0){
  $c=db();
  $st=$c->prepare("SELECT imagen FROM prestamos WHERE id=?"); $st->bind_param("i",$id);
  $st->execute(); $st->bind_result($img); $st->fetch(); $st->close();
  if ($img && is_file(UPLOAD_DIR.$img)) @unlink(UPLOAD_DIR.$img);
  $st=$c->prepare("DELETE FROM prestamos WHERE id=?"); $st->bind_param("i",$id); $st->execute();
  $st->close(); $c->close(); go('?msg=eliminado');
}

// ===== UI =====
?><!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Préstamos | Admin</title>
<style>
 :root{
   --bg:#f6f7fb; --fg:#222; --card:#fff; --muted:#6b7280;
   --primary:#0b5ed7; --gray:#6c757d; --red:#dc3545; --chip:#eef2ff;
 }
 *{box-sizing:border-box}
 body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:22px;background:var(--bg);color:var(--fg)}
 a{text-decoration:none}
 .btn{display:inline-flex;align-items:center;gap:8px;padding:9px 12px;border-radius:12px;background:var(--primary);color:#fff;font-weight:600;border:0}
 .btn.gray{background:var(--gray)} .btn.red{background:var(--red)}
 .btn.small{padding:7px 10px;font-weight:600;border-radius:10px}
 .toolbar{display:flex;gap:10px;align-items:center;margin-bottom:14px;flex-wrap:wrap}
 .msg{background:#e8f7ee;color:#196a3b;padding:8px 12px;border-radius:10px;display:inline-block}
 .error{background:#fdecec;color:#b02a37;padding:8px 12px;border-radius:10px;display:inline-block}
 .card{background:var(--card);border-radius:16px;box-shadow:0 6px 20px rgba(0,0,0,.06);padding:16px}
 .section{margin-bottom:16px}
 .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:16px}
 .grid-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}
 .field{display:flex;flex-direction:column;gap:6px}
 input,select{padding:11px;border:1px solid #e5e7eb;border-radius:12px;background:#fff}
 input[type=file]{border:1px dashed #cbd5e1;background:#fafafa}
 .muted{color:var(--muted)}
 .chip{display:inline-block;background:var(--chip);padding:4px 8px;border-radius:999px;font-size:12px;font-weight:600}
 .row{display:flex;justify-content:space-between;gap:10px;align-items:center}
 .title{font-size:18px;font-weight:800}
 .subtitle{font-size:13px;color:var(--muted)}
 .money{font-weight:800}
 .thumb{width:100%;max-height:180px;object-fit:cover;border-radius:12px;border:1px solid #eee}
 .pairs{display:grid;grid-template-columns:1fr 1fr;gap:10px}
 .pairs .item{background:#fafbff;border:1px solid #eef2ff;border-radius:12px;padding:10px}
 .pairs .k{font-size:12px;color:var(--muted)}
 .pairs .v{font-size:16px;font-weight:700}
 .actions{display:flex;gap:8px;flex-wrap:wrap}
 @media (max-width:760px){
   .pairs{grid-template-columns:1fr}
 }
</style>
</head><body>

<div class="toolbar">
  <a class="btn" href="?">📇 Tarjetas</a>
  <a class="btn gray" href="?action=new">➕ Crear</a>
</div>

<?php if (!empty($_GET['msg'])): ?>
  <div class="msg" style="margin-bottom:14px">
    <?php
      echo match($_GET['msg']){
        'creado'=>'Registro creado correctamente.',
        'editado'=>'Cambios guardados.',
        'eliminado'=>'Registro eliminado.',
        default=>'Operación realizada.'
      };
    ?>
  </div>
<?php endif; ?>

<?php
// ====== NEW / EDIT FORMS ======
if ($action==='new' || ($action==='edit' && $id>0 && $_SERVER['REQUEST_METHOD']!=='POST')):
  $row = ['deudor'=>'','prestamista'=>'','monto'=>'','fecha'=>'','imagen'=>null];
  if ($action==='edit'){
    $c=db(); $st=$c->prepare("SELECT deudor,prestamista,monto,fecha,imagen FROM prestamos WHERE id=?");
    $st->bind_param("i",$id); $st->execute(); $res=$st->get_result(); $row=$res->fetch_assoc() ?: $row;
    $st->close(); $c->close();
  }
?>
  <div class="card">
    <div class="row" style="margin-bottom:10px">
      <div class="title"><?= $action==='new'?'Nuevo préstamo':'Editar préstamo #'.h($id) ?></div>
    </div>
    <?php if(!empty($err)): ?><div class="error" style="margin-bottom:10px"><?= h($err) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" action="?action=<?= $action==='new'?'create':'edit&id='.$id ?>">
      <div class="grid" style="margin-bottom:12px">
        <div class="field" style="grid-column:span 6">
          <label>Deudor *</label>
          <input name="deudor" required value="<?= h($row['deudor']) ?>">
        </div>
        <div class="field" style="grid-column:span 6">
          <label>Prestamista *</label>
          <input name="prestamista" required value="<?= h($row['prestamista']) ?>">
        </div>
        <div class="field" style="grid-column:span 4">
          <label>Monto *</label>
          <input name="monto" type="number" step="1" min="0" required value="<?= h($row['monto']) ?>">
        </div>
        <div class="field" style="grid-column:span 4">
          <label>Fecha *</label>
          <input name="fecha" type="date" required value="<?= h($row['fecha']) ?>">
        </div>
        <div class="field" style="grid-column:span 4">
          <label>Imagen (opcional)</label>
          <?php if ($action==='edit' && $row['imagen']): ?>
            <div style="margin-bottom:6px">
              <img class="thumb" src="uploads/<?= h($row['imagen']) ?>" alt="">
            </div>
            <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="keep" checked> Mantener imagen actual</label>
          <?php endif; ?>
          <input type="file" name="imagen" accept="image/*">
        </div>
      </div>
      <div class="actions">
        <button class="btn" type="submit">💾 Guardar</button>
        <a class="btn gray" href="?">Cancelar</a>
      </div>
    </form>
  </div>
<?php
// ====== LIST (CARDS) ======
else:
  // filtros
  $q = trim($_GET['q'] ?? '');
  $conn=db();
  $where = "1";
  $types=""; $params=[];
  if ($q!==''){
    $where.=" AND (deudor LIKE CONCAT('%',?,'%') OR prestamista LIKE CONCAT('%',?,'%'))";
    $types="ss"; $params=[$q,$q];
  }

  // SELECT con cálculos de meses, interés y total
  $sql = "
    SELECT 
      id,
      deudor,
      prestamista,
      monto,
      fecha,
      imagen,
      created_at,
      GREATEST(TIMESTAMPDIFF(MONTH, fecha, CURDATE()), 0) AS meses,
      (monto * 0.10 * GREATEST(TIMESTAMPDIFF(MONTH, fecha, CURDATE()), 0)) AS interes,
      (monto + (monto * 0.10 * GREATEST(TIMESTAMPDIFF(MONTH, fecha, CURDATE()), 0))) AS total
    FROM prestamos
    WHERE $where
    ORDER BY id DESC
  ";
  $st=$conn->prepare($sql);
  if($types) $st->bind_param($types, ...$params);
  $st->execute(); $rs=$st->get_result();
?>
  <div class="card" style="margin-bottom:16px">
    <form class="toolbar" method="get">
      <input type="hidden" name="action" value="list">
      <input name="q" placeholder="🔎 Buscar (deudor / prestamista)" value="<?= h($q) ?>" style="flex:1;min-width:220px">
      <button class="btn" type="submit">Filtrar</button>
      <?php if ($q!==''): ?><a class="btn gray" href="?">Quitar filtro</a><?php endif; ?>
    </form>
    <div class="subtitle">Interés simple al <strong>10% mensual</strong> por <strong>meses completos</strong> desde la fecha del préstamo.</div>
  </div>

  <?php if ($rs->num_rows === 0): ?>
    <div class="card"><span class="muted">(sin registros)</span></div>
  <?php else: ?>
    <div class="grid-cards">
      <?php while($r=$rs->fetch_assoc()): ?>
        <div class="card">
          <?php if (!empty($r['imagen'])): ?>
            <a href="uploads/<?= h($r['imagen']) ?>" target="_blank" title="Ver comprobante">
              <img class="thumb" src="uploads/<?= h($r['imagen']) ?>" alt="imagen">
            </a>
          <?php endif; ?>

          <div class="row" style="margin-top:8px">
            <div>
              <div class="title">#<?= h($r['id']) ?> • <?= h($r['deudor']) ?></div>
              <div class="subtitle">Prestamista: <strong><?= h($r['prestamista']) ?></strong></div>
            </div>
            <span class="chip"><?= h($r['fecha']) ?></span>
          </div>

          <div class="pairs" style="margin-top:12px">
            <div class="item">
              <div class="k">Monto</div>
              <div class="v">$ <?= money($r['monto']) ?></div>
            </div>
            <div class="item">
              <div class="k">Meses transcurridos</div>
              <div class="v"><?= h($r['meses']) ?></div>
            </div>
            <div class="item">
              <div class="k">Interés acumulado (10% x mes)</div>
              <div class="v">$ <?= money($r['interes']) ?></div>
            </div>
            <div class="item">
              <div class="k">Total a la fecha</div>
              <div class="v money">$ <?= money($r['total']) ?></div>
            </div>
          </div>

          <div class="row" style="margin-top:12px">
            <div class="subtitle">Creado: <?= h($r['created_at']) ?></div>
            <div class="actions">
              <a class="btn gray small" href="?action=edit&id=<?= $r['id'] ?>">✏️ Editar</a>
              <form style="display:inline" method="post" action="?action=delete&id=<?= $r['id'] ?>" onsubmit="return confirm('¿Eliminar #<?= $r['id'] ?>?')">
                <button class="btn red small" type="submit">🗑️ Eliminar</button>
              </form>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  <?php endif; ?>

<?php
  $st->close(); $conn->close();
endif; // list
?>
</body></html>
