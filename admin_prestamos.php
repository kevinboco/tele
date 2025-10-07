<?php
/*********************************************************
 * admin_prestamos.php — CRUD + Tarjetas + Visual 3-nodos
 * Visual:
 *   [Prestamista] --> [Deudores (valor prestado)] --> [Ganancia (interés)]
 * Interés simple 10% mensual por MESES COMPLETOS
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
$view   = $_GET['view']   ?? 'cards'; // 'cards' | 'graph'
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
 .pairs .k{font-size:12px;color:var(--muted)}
 .pairs .v{font-size:16px;font-weight:700}
 .row{display:flex;justify-content:space-between;gap:10px;align-items:center}
 .title{font-size:18px;font-weight:800}
 .chip{display:inline-block;background:var(--chip);padding:4px 8px;border-radius:999px;font-size:12px;font-weight:600}

 /* VISUAL 3-nodos en un único lienzo por prestamista */
 .group{background:#fff;border-radius:16px;box-shadow:0 6px 20px rgba(0,0,0,.06);padding:12px;margin-bottom:18px}
 .svgwrap{width:100%;overflow:auto;border:1px dashed #e5e7eb;border-radius:12px;background:#fafafa}
 .nodeRect{fill:#ffffff;stroke:#cbd5e1;stroke-width:1.2}
 .txt{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;font-size:13px;fill:#111}
 .mut{fill:#6b7280}
 .amt{font-weight:800}
 @media (max-width:760px){ .pairs{grid-template-columns:1fr} }
</style>
</head><body>

<div class="tabs">
  <a class="<?= $view==='cards'?'active':'' ?>" href="?view=cards">📇 Tarjetas</a>
  <a class="<?= $view==='graph'?'active':'' ?>" href="?view=graph">🕸️ Visual</a>
  <a class="btn gray" href="?action=new" style="margin-left:auto">➕ Crear</a>
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
      <div class="row" style="gap:12px;flex-wrap:wrap">
        <div class="field" style="min-width:220px;flex:1"><label>Deudor *</label><input name="deudor" required value="<?= h($row['deudor']) ?>"></div>
        <div class="field" style="min-width:220px;flex:1"><label>Prestamista *</label><input name="prestamista" required value="<?= h($row['prestamista']) ?>"></div>
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

  // ==== filtros y listas ====
  $q = trim($_GET['q'] ?? '');
  $fp = trim($_GET['fp'] ?? '');
  $conn=db();

  // Para dropdown de prestamistas
  $prestList = [];
  $resPL = $conn->query("SELECT DISTINCT prestamista FROM prestamos ORDER BY prestamista ASC");
  while($rowPL=$resPL->fetch_row()) $prestList[] = $rowPL[0];

  if ($view==='cards'){
    // -------- TARJETAS --------
    $where = "1"; $types=""; $params=[];
    if ($q!==''){ $where.=" AND (deudor LIKE CONCAT('%',?,'%') OR prestamista LIKE CONCAT('%',?,'%'))"; $types.="ss"; $params[]=$q; $params[]=$q; }
    if ($fp!==''){ $where.=" AND prestamista = ?"; $types.="s"; $params[]=$fp; }

    $sql = "
      SELECT 
        id,deudor,prestamista,monto,fecha,imagen,created_at,
        GREATEST(TIMESTAMPDIFF(MONTH, fecha, CURDATE()),0) AS meses,
        (monto*0.10*GREATEST(TIMESTAMPDIFF(MONTH, fecha, CURDATE()),0)) AS interes,
        (monto + (monto*0.10*GREATEST(TIMESTAMPDIFF(MONTH, fecha, CURDATE()),0))) AS total
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
        <input type="hidden" name="view" value="cards">
        <input name="q" placeholder="🔎 Buscar (deudor / prestamista)" value="<?= h($q) ?>" style="flex:1;min-width:220px">
        <select name="fp">
          <option value="">Todos los prestamistas</option>
          <?php foreach($prestList as $p): ?>
            <option value="<?= h($p) ?>" <?= $fp===$p?'selected':'' ?>><?= h($p) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Filtrar</button>
        <?php if ($q!=='' || $fp!==''): ?><a class="btn gray" href="?view=cards">Quitar filtro</a><?php endif; ?>
      </form>
      <div class="subtitle">Interés simple 10% mensual por meses completos desde la fecha.</div>
    </div>

    <?php if ($rs->num_rows === 0): ?>
      <div class="card"><span class="subtitle">(sin registros)</span></div>
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
              <div class="item"><div class="k">Monto</div><div class="v">$ <?= money($r['monto']) ?></div></div>
              <div class="item"><div class="k">Meses</div><div class="v"><?= h($r['meses']) ?></div></div>
              <div class="item"><div class="k">Interés</div><div class="v">$ <?= money($r['interes']) ?></div></div>
              <div class="item"><div class="k">Total</div><div class="v">$ <?= money($r['total']) ?></div></div>
            </div>
            <div class="row" style="margin-top:12px">
              <div class="subtitle">Creado: <?= h($r['created_at']) ?></div>
              <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a class="btn gray small" href="?action=edit&id=<?= $r['id'] ?>&view=cards">✏️ Editar</a>
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
    $st->close();

  } else {
    // -------- VISUAL 3-NODOS --------
    // Agregar por prestamista y deudor: capital prestado (suma de montos) y ganancia total (suma de intereses)
    $where = "1"; $types=""; $params=[];
    if ($q!==''){ $where.=" AND (deudor LIKE CONCAT('%',?,'%') OR prestamista LIKE CONCAT('%',?,'%'))"; $types.="ss"; $params[]=$q; $params[]=$q; }
    if ($fp!==''){ $where.=" AND prestamista = ?"; $types.="s"; $params[]=$fp; }

    $sql = "
      SELECT 
        prestamista,
        deudor,
        SUM(monto) AS capital,
        SUM(monto*0.10*GREATEST(TIMESTAMPDIFF(MONTH, fecha, CURDATE()),0)) AS interes
      FROM prestamos
      WHERE $where
      GROUP BY prestamista, deudor
      ORDER BY prestamista ASC, deudor ASC
    ";
    $st=$conn->prepare($sql);
    if($types) $st->bind_param($types, ...$params);
    $st->execute(); $rs=$st->get_result();

    // Estructura por prestamista
    $groups=[]; $ganPrest=[];
    while($r=$rs->fetch_assoc()){
      $p=$r['prestamista'];
      if(!isset($groups[$p])) $groups[$p]=[];
      $groups[$p][]=$r;
      $ganPrest[$p] = ($ganPrest[$p] ?? 0) + (float)$r['interes'];
    }
?>
    <div class="card" style="margin-bottom:16px">
      <form class="toolbar" method="get">
        <input type="hidden" name="view" value="graph">
        <input name="q" placeholder="🔎 Buscar (deudor / prestamista)" value="<?= h($q) ?>" style="flex:1;min-width:220px">
        <select name="fp">
          <option value="">Todos los prestamistas</option>
          <?php foreach($prestList as $p): ?>
            <option value="<?= h($p) ?>" <?= $fp===$p?'selected':'' ?>><?= h($p) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Filtrar</button>
        <?php if ($q!=='' || $fp!==''): ?><a class="btn gray" href="?view=graph">Quitar filtro</a><?php endif; ?>
      </form>
      <div class="subtitle">Diagrama de 3 nodos: <strong>Prestamista ➜ Deudores (valor prestado) ➜ Ganancia</strong> (suma de intereses 10%/mes).</div>
    </div>

    <?php if (empty($groups)): ?>
      <div class="card"><span class="subtitle">(sin registros)</span></div>
    <?php else: foreach($groups as $prest => $rows):
        $n = count($rows);
        $height = max(160, 80*$n + 60);
        // posiciones X (3 columnas)
        $xL = 120;           // prestamista
        $xC = 520;           // deudores
        $xR = 900;           // ganancia
        $topPad = 30;
    ?>
      <div class="group">
        <div class="title" style="margin:6px 10px 10px">Prestamista: <?= h($prest) ?></div>
        <div class="svgwrap">
          <svg width="1080" height="<?= $height ?>" viewBox="0 0 1080 <?= $height ?>" xmlns="http://www.w3.org/2000/svg">
            <!-- Nodo Prestamista (izquierda) -->
            <rect class="nodeRect" x="<?= $xL-90 ?>" y="<?= $topPad ?>" rx="12" ry="12" width="180" height="52"/>
            <text class="txt" x="<?= $xL-80 ?>" y="<?= $topPad+20 ?>">Prestamista</text>
            <text class="txt" x="<?= $xL-80 ?>" y="<?= $topPad+40 ?>"><tspan font-weight="800"><?= h($prest) ?></tspan></text>

            <!-- Nodo Ganancia (derecha) -->
            <rect class="nodeRect" x="<?= $xR-110 ?>" y="<?= $topPad ?>" rx="12" ry="12" width="220" height="52" fill="#111" stroke="#111"/>
            <text class="txt" x="<?= $xR-95 ?>" y="<?= $topPad+20 ?>" fill="#fff">Ganancia (interés)</text>
            <text class="txt" x="<?= $xR-95 ?>" y="<?= $topPad+40 ?>" fill="#fff">$ <?= money($ganPrest[$prest] ?? 0) ?></text>

            <?php
              $i=0;
              foreach($rows as $r):
                $y = $topPad + 30 + ($i*80) + 30; // centro y de cada deudor
                $boxY = $y-30;
                $cap = '$ '.money($r['capital']);
            ?>
              <!-- líneas desde prestamista al deudor -->
              <line x1="<?= $xL+90 ?>" y1="<?= $topPad+26 ?>" x2="<?= $xC-10 ?>" y2="<?= $y ?>" stroke="#9ca3af" stroke-width="1.5" />
              <!-- líneas desde deudor a ganancia -->
              <line x1="<?= $xC+220 ?>" y1="<?= $y ?>" x2="<?= $xR-110 ?>" y2="<?= $topPad+26 ?>" stroke="#9ca3af" stroke-width="1.2" />

              <!-- Nodo Deudor (centro) -->
              <rect class="nodeRect" x="<?= $xC-10 ?>" y="<?= $boxY ?>" rx="12" ry="12" width="230" height="60"/>
              <text class="txt" x="<?= $xC ?>" y="<?= $boxY+20 ?>"><tspan font-weight="800"><?= h($r['deudor']) ?></tspan></text>
              <text class="txt mut" x="<?= $xC ?>" y="<?= $boxY+38 ?>">valor prestado: <tspan class="amt" fill="#111"><?= $cap ?></tspan></text>
            <?php $i++; endforeach; ?>
          </svg>
        </div>
        <div class="subtitle" style="margin-top:6px">Consejo: usa el filtro para ver uno o varios prestamistas.</div>
      </div>
    <?php endforeach; endif; ?>

<?php
    $st->close();
  }

  $conn->close();
endif; // list / graph
?>
</body></html>
