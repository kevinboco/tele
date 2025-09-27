<?php
/*********************************************************
 * admin_prestamos.php  —  CRUD muy claro con TABLA SIEMPRE
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
<title>Prestamos | Admin</title>
<style>
 body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:22px;background:#f6f7fb;color:#222}
 .btn{display:inline-block;padding:8px 12px;border-radius:10px;background:#0b5ed7;color:#fff;text-decoration:none}
 .btn.gray{background:#6c757d}.btn.red{background:#dc3545}
 .card{background:#fff;border-radius:14px;box-shadow:0 6px 20px rgba(0,0,0,.06);padding:16px;margin-bottom:16px}
 table{width:100%;border-collapse:collapse;border-radius:12px;overflow:hidden;background:#fff}
 th,td{padding:10px;border-bottom:1px solid #eee;vertical-align:top}
 th{background:#eef2ff;text-align:left}
 tr:hover{background:#fafcff}
 .fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
 .field{display:flex;flex-direction:column;gap:6px}
 input,select{padding:10px;border:1px solid #ddd;border-radius:10px}
 input[type=file]{border:1px dashed #bbb;background:#fafafa}
 img.thumb{max-height:70px;border-radius:8px;border:1px solid #eee}
 .muted{color:#777}.msg{background:#e8f7ee;color:#196a3b;padding:8px 12px;border-radius:10px;display:inline-block}
 .error{background:#fdecec;color:#b02a37;padding:8px 12px;border-radius:10px;display:inline-block}
 .toolbar{display:flex;gap:10px;align-items:center;margin-bottom:12px}
</style>
</head><body>

<div class="toolbar">
  <a class="btn" href="?">📄 Listado</a>
  <a class="btn gray" href="?action=new">➕ Crear</a>
</div>

<?php if (!empty($_GET['msg'])): ?>
  <div class="msg">
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
    <h2><?= $action==='new'?'Nuevo préstamo':'Editar préstamo #'.h($id) ?></h2>
    <?php if(!empty($err)): ?><div class="error"><?= h($err) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" action="?action=<?= $action==='new'?'create':'edit&id='.$id ?>">
      <div class="fields">
        <div class="field">
          <label>Deudor *</label>
          <input name="deudor" required value="<?= h($row['deudor']) ?>">
        </div>
        <div class="field">
          <label>Prestamista *</label>
          <input name="prestamista" required value="<?= h($row['prestamista']) ?>">
        </div>
        <div class="field">
          <label>Monto *</label>
          <input name="monto" type="number" step="1" min="0" required value="<?= h($row['monto']) ?>">
        </div>
        <div class="field">
          <label>Fecha *</label>
          <input name="fecha" type="date" required value="<?= h($row['fecha']) ?>">
        </div>
        <div class="field" style="grid-column:1/-1">
          <label>Imagen (opcional)</label>
          <?php if ($action==='edit' && $row['imagen']): ?>
            <div style="margin-bottom:6px">
              <img class="thumb" src="uploads/<?= h($row['imagen']) ?>" alt="">
            </div>
            <label><input type="checkbox" name="keep" checked> Mantener imagen actual</label>
          <?php endif; ?>
          <input type="file" name="imagen" accept="image/*">
        </div>
      </div>
      <div style="margin-top:12px">
        <button class="btn" type="submit">💾 Guardar</button>
        <a class="btn gray" href="?">Cancelar</a>
      </div>
    </form>
  </div>
<?php
// ====== LIST ======
else:
  // filtros simples
  $q = trim($_GET['q'] ?? '');
  $conn=db();
  $where = "1";
  $types=""; $params=[];
  if ($q!==''){ $where.=" AND (deudor LIKE CONCAT('%',?,'%') OR prestamista LIKE CONCAT('%',?,'%'))";
    $types="ss"; $params=[$q,$q]; }

  // obtén todo (sin paginación para que VEAS la tabla; si tienes miles, le metemos paginación)
  $sql="SELECT id,deudor,prestamista,monto,fecha,imagen,created_at FROM prestamos WHERE $where ORDER BY id DESC";
  $st=$conn->prepare($sql);
  if($types) $st->bind_param($types, ...$params);
  $st->execute(); $rs=$st->get_result();
?>
  <div class="card">
    <form class="toolbar" method="get">
      <input type="hidden" name="action" value="list">
      <input name="q" placeholder="Buscar (deudor/prestamista)" value="<?= h($q) ?>">
      <button class="btn" type="submit">Filtrar</button>
    </form>

    <div style="overflow:auto">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Deudor</th>
            <th>Prestamista</th>
            <th>Monto</th>
            <th>Fecha</th>
            <th>Imagen</th>
            <th>Creado</th>
            <th style="min-width:170px">Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($rs->num_rows === 0): ?>
          <tr><td colspan="8" class="muted">(sin registros)</td></tr>
        <?php else: while($r=$rs->fetch_assoc()): ?>
          <tr>
            <td><?= h($r['id']) ?></td>
            <td><?= h($r['deudor']) ?></td>
            <td><?= h($r['prestamista']) ?></td>
            <td>$ <?= money($r['monto']) ?></td>
            <td><?= h($r['fecha']) ?></td>
            <td>
              <?php if ($r['imagen']): ?>
                <a href="uploads/<?= h($r['imagen']) ?>" target="_blank">
                  <img class="thumb" src="uploads/<?= h($r['imagen']) ?>" alt="">
                </a>
              <?php else: ?><span class="muted">—</span><?php endif; ?>
            </td>
            <td><?= h($r['created_at']) ?></td>
            <td>
              <a class="btn gray" href="?action=edit&id=<?= $r['id'] ?>">✏️ Editar</a>
              <form style="display:inline" method="post" action="?action=delete&id=<?= $r['id'] ?>" onsubmit="return confirm('¿Eliminar #<?= $r['id'] ?>?')">
                <button class="btn red" type="submit">🗑️ Eliminar</button>
              </form>
            </td>
          </tr>
        <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php
  $st->close(); $conn->close();
endif; // list
?>
</body></html>
