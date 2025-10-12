<?php
/*********************************************************
 * admin_prestamos.php — CRUD + Tarjetas + Visual D3
 * - Mantiene tu lógica original (DB, CRUD, interés, filtros)
 * - Visual "graph": árbol interactivo con D3 (panel lateral)
 * - Nodo 2 coloreado por meses (1, 2, 3+)
 * - Selector para marcar pagados (usa ids CSV por deudor)
 *********************************************************/
include("nav.php");

/* ======= CONFIG ======= */
define('DB_HOST', 'mysql.hostinger.com');
define('DB_USER', 'u648222299_keboco5');
define('DB_PASS', 'Bucaramanga3011');
define('DB_NAME', 'u648222299_viajes');
const UPLOAD_DIR = __DIR__ . '/uploads/';
const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;
if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0775, true);

/* ===== Helpers ===== */
function db(): mysqli { $m = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); if ($m->connect_errno) exit("Error DB: ".$m->connect_error); $m->set_charset('utf8mb4'); return $m; }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES,'UTF-8'); }
function money($n){ return number_format((float)$n,0,',','.'); }
function go($qs){ header("Location: ".$qs); exit; }
function mbnorm($s){ return mb_strtolower(trim((string)$s),'UTF-8'); }
function mbtitle($s){ return function_exists('mb_convert_case') ? mb_convert_case((string)$s, MB_CASE_TITLE, 'UTF-8') : ucwords(strtolower((string)$s)); }

/* ===== Routing ===== */
$action = $_GET['action'] ?? 'list';
$view   = $_GET['view']   ?? 'cards'; // 'cards' | 'graph'
$id     = (int)($_GET['id'] ?? 0);

/* ===== Upload helper ===== */
function save_image($file): ?string {
  if (empty($file) || ($file['error']??4) === 4) return null;
  if ($file['error'] !== UPLOAD_ERR_OK) return null;
  if ($file['size'] > MAX_UPLOAD_BYTES) return null;
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime  = $finfo->file($file['tmp_name']);
  $ext   = match ($mime) {'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif', default=>null};
  if(!$ext) return null;
  $name = time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
  if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR.$name)) return null;
  return $name;
}

/* ===== Acción: marcar pagados (ids CSV por deudor) ===== */
if ($action==='mark_paid' && $_SERVER['REQUEST_METHOD']==='POST'){
  $nodes = $_POST['nodes'] ?? [];
  if (!is_array($nodes)) $nodes = [];
  $all = [];
  foreach($nodes as $csv){ foreach(explode(',',(string)$csv) as $raw){ $n=(int)trim($raw); if($n>0) $all[$n]=1; } }
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

/* ===== CRUD básico ===== */
if ($action==='create' && $_SERVER['REQUEST_METHOD']==='POST'){
  $deudor = trim($_POST['deudor']??''); $prestamista=trim($_POST['prestamista']??'');
  $monto  = trim($_POST['monto']??'');  $fecha = trim($_POST['fecha']??'');
  $img = save_image($_FILES['imagen']??null);
  if ($deudor && $prestamista && is_numeric($monto) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)){
    $c=db(); $st=$c->prepare("INSERT INTO prestamos (deudor,prestamista,monto,fecha,imagen,created_at) VALUES (?,?,?,?,?,NOW())");
    $st->bind_param("ssdss",$deudor,$prestamista,$monto,$fecha,$img); $st->execute(); $st->close(); $c->close();
    go('?msg=creado&view='.urlencode($view));
  } else { $err="Completa todos los campos correctamente."; }
}
if ($action==='edit' && $_SERVER['REQUEST_METHOD']==='POST' && $id>0){
  $deudor=trim($_POST['deudor']??''); $prestamista=trim($_POST['prestamista']??'');
  $monto=trim($_POST['monto']??'');   $fecha=trim($_POST['fecha']??'');
  $keep = isset($_POST['keep']) ? 1:0; $img = save_image($_FILES['imagen']??null);
  if ($deudor && $prestamista && is_numeric($monto) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)){
    $c=db();
    if ($img){
      $st=$c->prepare("UPDATE prestamos SET deudor=?,prestamista=?,monto=?,fecha=?,imagen=? WHERE id=?");
      $st->bind_param("ssdssi",$deudor,$prestamista,$monto,$fecha,$img,$id);
    } else {
      if ($keep){ $st=$c->prepare("UPDATE prestamos SET deudor=?,prestamista=?,monto=?,fecha=? WHERE id=?");
                  $st->bind_param("ssdsi",$deudor,$prestamista,$monto,$fecha,$id);
      } else {    $st=$c->prepare("UPDATE prestamos SET deudor=?,prestamista=?,monto=?,fecha=?,imagen=NULL WHERE id=?");
                  $st->bind_param("ssdsi",$deudor,$prestamista,$monto,$fecha,$id);
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

?><!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Préstamos | Admin</title>
<script src="https://d3js.org/d3.v7.min.js"></script>
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

 /* === Visual con D3 === */
 .panel {
   width: 260px; background: #fff; position: fixed; left: 0; top: 0; bottom: 0;
   border-right: 1px solid #e5e7eb; padding: 15px; overflow-y: auto; box-shadow: 2px 0 10px rgba(0,0,0,0.05)
 }
 .prestamista-item{ padding:10px; margin-bottom:8px; border-radius:8px; background:#e3f2fd; cursor:pointer; transition:.2s }
 .prestamista-item:hover{ background:#bbdefb }
 #chart{ margin-left: 280px; background: #f4f6fa; border:1px dashed #e5e7eb; border-radius:12px }
 .link{ fill:none; stroke:#cbd5e1; stroke-width:1.6px }
 .node circle{ stroke:#fff; stroke-width:2px }
 .tooltip{ position:absolute; background:rgba(0,0,0,.78); color:#fff; padding:6px 10px; border-radius:6px; font-size:13px; pointer-events:none }
 .legend .chip{margin-right:6px}
 /* colores por meses en nodo deudor */
 .c-m1{ fill:#ffd54f }   /* 1 mes */
 .c-m2{ fill:#ffb74d }   /* 2 meses */
 .c-m3{ fill:#ef9a9a }   /* 3+ meses */
 .c-root{ fill:#1976d2 }
 .selector{margin-top:12px;border-top:1px dashed #e5e7eb;padding-top:10px}
 .selgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:8px}
 .selitem{display:flex;gap:8px;align-items:flex-start;background:#fafbff;border:1px solid #eef2ff;border-radius:12px;padding:8px}
 .selitem .meta{font-size:12px;color:#555}
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
        default=>'Operación realizada.'
      };
    ?>
  </div>
<?php endif; ?>

<?php
/* ===== Formularios New/Edit ===== */
if ($action==='new' || ($action==='edit' && $id>0 && $_SERVER['REQUEST_METHOD']!=='POST')):
  $row = ['deudor'=>'','prestamista'=>'','monto'=>'','fecha'=>'','imagen'=>null];
  if ($action==='edit'){
    $c=db(); $st=$c->prepare("SELECT deudor,prestamista,monto,fecha,imagen FROM prestamos WHERE id=?");
    $st->bind_param("i",$id); $st->execute(); $res=$st->get_result(); $row=$res->fetch_assoc() ?: $row; $st->close(); $c->close();
  }
?>
  <div class="card">
    <div class="row" style="margin-bottom:10px"><div class="title"><?= $action==='new'?'Nuevo préstamo':'Editar préstamo #'.h($id) ?></div></div>
    <?php if(!empty($err)): ?><div class="error" style="margin-bottom:10px"><?= h($err) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" action="?action=<?= $action==='new'?'create':'edit&id='.$id ?>&view=<?= h($view) ?>">
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
/* ===== Listados ===== */
else:

  $q  = trim($_GET['q'] ?? '');
  $fp = trim($_GET['fp'] ?? '');
  $qNorm  = mbnorm($q);
  $fpNorm = mbnorm($fp);
  $conn = db();

  /* Combo prestamistas (según vista) */
  $prestMap = [];
  $resPL = ($view==='graph')
    ? $conn->query("SELECT prestamista FROM prestamos WHERE (pagado IS NULL OR pagado=0)")
    : $conn->query("SELECT prestamista FROM prestamos");
  while($rowPL=$resPL->fetch_row()){
    $norm = mbnorm($rowPL[0]); if ($norm==='') continue;
    if (!isset($prestMap[$norm])) $prestMap[$norm] = $rowPL[0];
  }
  ksort($prestMap, SORT_NATURAL);

  if ($view==='cards'){
    /* ---------- TARJETAS ---------- */
    $where="1"; $types=""; $params=[];
    if ($q!==''){ $where.=" AND (LOWER(deudor) LIKE CONCAT('%',?,'%') OR LOWER(prestamista) LIKE CONCAT('%',?,'%'))"; $types.="ss"; $params[]=$qNorm; $params[]=$qNorm; }
    if ($fpNorm!==''){ $where.=" AND LOWER(TRIM(prestamista)) = ?"; $types.="s"; $params[]=$fpNorm; }
    $sql="
      SELECT id,deudor,prestamista,monto,fecha,imagen,created_at,
             CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END AS meses,
             (monto*0.10*CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS interes,
             (monto + (monto*0.10*CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END)) AS total
      FROM prestamos
      WHERE $where
      ORDER BY id DESC";
    $st=$conn->prepare($sql); if($types) $st->bind_param($types, ...$params); $st->execute(); $rs=$st->get_result();
?>
    <div class="card" style="margin-bottom:16px">
      <form class="toolbar" method="get">
        <input type="hidden" name="view" value="cards">
        <input name="q" placeholder="🔎 Buscar (deudor / prestamista)" value="<?= h($q) ?>" style="flex:1;min-width:220px">
        <select name="fp"><option value="">Todos los prestamistas</option>
          <?php foreach($prestMap as $norm=>$label): ?><option value="<?= h($norm) ?>" <?= $fpNorm===$norm?'selected':'' ?>><?= h(mbtitle($label)) ?></option><?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Filtrar</button>
        <?php if ($q!=='' || $fpNorm!==''): ?><a class="btn gray" href="?view=cards">Quitar filtro</a><?php endif; ?>
      </form>
      <div class="subtitle">Interés 10% desde el día 1 y luego 10% por mes.</div>
    </div>

    <?php if ($rs->num_rows===0): ?>
      <div class="card"><span class="subtitle">(sin registros)</span></div>
    <?php else: ?>
      <div class="grid-cards">
        <?php while($r=$rs->fetch_assoc()): ?>
          <div class="card">
            <?php if (!empty($r['imagen'])): ?><a href="uploads/<?= h($r['imagen']) ?>" target="_blank"><img class="thumb" src="uploads/<?= h($r['imagen']) ?>" alt=""></a><?php endif; ?>
            <div class="row" style="margin-top:8px">
              <div><div class="title">#<?= h($r['id']) ?> • <?= h($r['deudor']) ?></div><div class="subtitle">Prestamista: <strong><?= h($r['prestamista']) ?></strong></div></div>
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
    /* ---------- VISUAL D3 (SOLO NO PAGADOS) ---------- */
    $where="(pagado IS NULL OR pagado=0)"; $types=""; $params=[];
    if ($q!==''){ $where.=" AND (LOWER(deudor) LIKE CONCAT('%',?,'%') OR LOWER(prestamista) LIKE CONCAT('%',?,'%'))"; $types.="ss"; $params[]=$qNorm; $params[]=$qNorm; }
    if ($fpNorm!==''){ $where.=" AND LOWER(TRIM(prestamista)) = ?"; $types.="s"; $params[]=$fpNorm; }

    /* Totales por prestamista+deudor */
    $sql = "
      SELECT LOWER(TRIM(prestamista)) AS prest_key, MIN(prestamista) AS prest_display,
             LOWER(TRIM(deudor)) AS deud_key, MIN(deudor) AS deud_display,
             MIN(fecha) AS fecha_min,
             CASE WHEN CURDATE() < MIN(fecha)
                  THEN 0 ELSE TIMESTAMPDIFF(MONTH, MIN(fecha), CURDATE()) + 1 END AS meses,
             SUM(monto) AS capital,
             SUM(monto*0.10*CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS interes,
             SUM(monto + monto*0.10*CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS total
      FROM prestamos
      WHERE $where
      GROUP BY prest_key, deud_key
      ORDER BY prest_key ASC, deud_display ASC";
    $st=$conn->prepare($sql); if($types) $st->bind_param($types, ...$params); $st->execute(); $rs=$st->get_result();

    /* IDs por prestamista+deudor para el selector */
    $sqlIds = "
      SELECT LOWER(TRIM(prestamista)) AS prest_key, LOWER(TRIM(deudor)) AS deud_key,
             GROUP_CONCAT(id) AS ids
      FROM prestamos
      WHERE $where
      GROUP BY prest_key, deud_key";
    $st2=$conn->prepare($sqlIds); if($types) $st2->bind_param($types, ...$params); $st2->execute(); $rsIds=$st2->get_result();
    $idsMap=[]; while($row=$rsIds->fetch_assoc()){ $p=$row['prest_key']; $d=$row['deud_key']; $idsMap[$p][$d]=preg_replace('/[^0-9,]/','',(string)$row['ids']); }
    $st2->close();

    /* Armar estructuras para JS */
    $dataByPrest = []; $ganPrest=[]; $capPendPrest=[];
    while($r=$rs->fetch_assoc()){
      $pkey=$r['prest_key']; $pdisp=$r['prest_display']; $dname=$r['deud_display'];
      if(!isset($dataByPrest[$pkey])) $dataByPrest[$pkey]=['label'=>$pdisp,'children'=>[]];
      $dataByPrest[$pkey]['children'][]=[
        'nombre'=>$dname,
        'valor'=>(float)$r['capital'],
        'interes'=>(float)$r['interes'],
        'total'  =>(float)$r['total'],
        'fecha'  =>$r['fecha_min'],
        'meses'  =>(int)$r['meses'],
        'deud_key'=>$r['deud_key']
      ];
      $ganPrest[$pkey]     = ($ganPrest[$pkey] ?? 0) + (float)$r['interes'];
      $capPendPrest[$pkey] = ($capPendPrest[$pkey] ?? 0) + (float)$r['capital'];
    }
    $prestOrder = array_keys($dataByPrest); // para listar en panel
?>
    <div class="card" style="margin-bottom:16px">
      <form class="toolbar" method="get">
        <input type="hidden" name="view" value="graph">
        <input name="q" placeholder="🔎 Buscar (deudor / prestamista)" value="<?= h($q) ?>" style="flex:1;min-width:220px">
        <select name="fp"><option value="">Todos los prestamistas</option>
          <?php foreach($prestMap as $norm=>$label): ?><option value="<?= h($norm) ?>" <?= $fpNorm===$norm?'selected':'' ?>><?= h(mbtitle($label)) ?></option><?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Filtrar</button>
        <?php if ($q!=='' || $fpNorm!==''): ?><a class="btn gray" href="?view=graph">Quitar filtro</a><?php endif; ?>
      </form>
      <div class="subtitle legend">
        <span class="chip" style="background:#FFF8DB">1 mes</span>
        <span class="chip" style="background:#FFE9D6">2 meses</span>
        <span class="chip" style="background:#FFE1E1">3+ meses</span>
      </div>
    </div>

    <?php if (empty($dataByPrest)): ?>
      <div class="card"><span class="subtitle">(sin registros)</span></div>
    <?php else: ?>
      <!-- Panel de prestamistas + Canvas D3 -->
      <div class="panel">
        <h3 style="margin:6px 0 10px">Prestamistas</h3>
        <div id="prestamistas-list">
          <?php foreach($prestOrder as $pkey): ?>
            <div class="prestamista-item" data-prest="<?= h($pkey) ?>"><?= h(mbtitle($dataByPrest[$pkey]['label'])) ?></div>
          <?php endforeach; ?>
        </div>
      </div>

      <svg id="chart" width="1300" height="760"></svg>
      <div id="tooltip" class="tooltip" style="display:none;"></div>

      <!-- Selector y botón de pagado -->
      <form id="formPaid" class="card selector" style="margin-left:280px" method="post" action="?action=mark_paid">
        <input type="hidden" name="view" value="graph">
        <input type="hidden" name="q" value="<?= h($q) ?>">
        <input type="hidden" name="fp" value="<?= h($fpNorm) ?>">
        <div class="row" style="margin-bottom:8px">
          <div class="subtitle"><strong id="selTitle">Selecciona deudores</strong></div>
          <label class="subtitle" style="display:flex;gap:8px;align-items:center">
            <input type="checkbox" id="checkAll"> Seleccionar todo
          </label>
        </div>
        <div id="selGrid" class="selgrid"></div>
        <div style="display:flex;justify-content:flex-end;margin-top:8px">
          <button class="btn small" type="submit" onclick="return confirm('¿Marcar como pagados los seleccionados?')">✔ Préstamo pagado</button>
        </div>
      </form>

      <script>
      // ====== Datos desde PHP ======
      const DATA_BY_PREST = <?= json_encode($dataByPrest, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
      const IDS_MAP        = <?= json_encode($idsMap, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
      const GAN_PREST      = <?= json_encode($ganPrest, JSON_UNESCAPED_UNICODE) ?>;
      const CAP_PEND_PREST = <?= json_encode($capPendPrest, JSON_UNESCAPED_UNICODE) ?>;

      const svg = d3.select("#chart");
      const W = +svg.attr("width"), H = +svg.attr("height");
      const g = svg.append("g").attr("transform","translate(100,40)");
      const tooltip = d3.select("#tooltip");

      // Llenar lista lateral (ya está en HTML) y enganchar eventos:
      d3.selectAll(".prestamista-item").on("click", function(){
        const pkey = this.getAttribute("data-prest");
        drawTree(pkey);
        buildSelector(pkey);
      });

      // Dibuja árbol para un prestamista
      function drawTree(pkey){
        g.selectAll("*").remove();
        const info = DATA_BY_PREST[pkey];
        if(!info){ return; }

        const root = d3.hierarchy({
          name: info.label,
          children: info.children
        });

        const treeLayout = d3.tree().size([H-120, W-480]);
        treeLayout(root);

        // Enlaces con animación
        g.selectAll(".link")
          .data(root.links())
          .join("path")
          .attr("class","link")
          .attr("d", d3.linkHorizontal().x(d=>root.y).y(d=>root.x))
          .attr("stroke-opacity",0)
          .transition().duration(700)
          .attr("stroke-opacity",1)
          .attr("d", d3.linkHorizontal().x(d=>d.y).y(d=>d.x));

        // Nodos
        const node = g.selectAll(".node")
          .data(root.descendants())
          .join("g")
          .attr("class","node")
          .attr("transform", d=>`translate(${root.y},${root.x})`)
          .transition().duration(800)
          .attr("transform", d=>`translate(${d.y},${d.x})`)
          .selection();

        node.append("circle")
          .attr("r", 8)
          .attr("class", d=>{
            if(d.depth===0) return "c-root";
            const m = +d.data.meses||0;
            return m>=3 ? "c-m3" : (m===2 ? "c-m2" : "c-m1");
          })
          .on("mouseover", (event,d)=>{
            if(d.depth===0) return;
            const total = (Number(d.data.valor||0)+Number(d.data.interes||0));
            tooltip.style("display","block").html(`
              <b>${d.data.nombre}</b><br>
              Valor: $${Number(d.data.valor).toLocaleString()}<br>
              Interés: $${Number(d.data.interes).toLocaleString()}<br>
              Fecha: ${d.data.fecha}<br>
              Total: $${total.toLocaleString()}
            `);
          })
          .on("mousemove", (event)=>{
            tooltip.style("left",(event.pageX+15)+"px").style("top",(event.pageY-20)+"px");
          })
          .on("mouseout", ()=> tooltip.style("display","none"));

        node.append("text")
          .attr("dy","0.31em")
          .attr("x", d=> d.depth===0 ? -15 : 15)
          .attr("text-anchor", d=> d.depth===0 ? "end" : "start")
          .text(d=> d.depth===0 ? d.data.name : d.data.nombre);

        // Cabeceras extra: ganancia y capital pendiente (derecha)
        const gain = Number(GAN_PREST[pkey]||0);
        const capPend = Number(CAP_PEND_PREST[pkey]||0);
        const headY = root.x;
        const xR = W-240;

        // cuadros
        g.append("rect").attr("x",xR-10).attr("y",headY-26).attr("rx",12).attr("ry",12).attr("width",220).attr("height",52)
          .attr("fill","#fff").attr("stroke","#cbd5e1");
        g.append("text").attr("x",xR+6).attr("y",headY-6).attr("class","txt").text("Ganancia (interés)");
        g.append("text").attr("x",xR+6).attr("y",headY+14).attr("class","txt").text(`$ ${gain.toLocaleString()}`);

        g.append("rect").attr("x",xR-10).attr("y",headY+34).attr("rx",12).attr("ry",12).attr("width",220).attr("height",52)
          .attr("fill","#fff").attr("stroke","#cbd5e1");
        g.append("text").attr("x",xR+6).attr("y",headY+54).text("Total prestado (pend.)");
        g.append("text").attr("x",xR+6).attr("y",headY+74).text(`$ ${capPend.toLocaleString()}`);
      }

      // Construye selector de deudores para marcar pagado
      function buildSelector(pkey){
        const grid = document.getElementById('selGrid');
        const title = document.getElementById('selTitle');
        grid.innerHTML = '';
        const info = DATA_BY_PREST[pkey]; if(!info){ title.innerText='Selecciona deudores'; return; }
        title.innerHTML = 'Selecciona deudores de <b>'+info.label+'</b>';
        info.children.forEach(ch=>{
          const dkey = ch.deud_key;
          const ids  = (IDS_MAP[pkey] && IDS_MAP[pkey][dkey]) ? IDS_MAP[pkey][dkey] : '';
          if(!ids) return;
          const label = document.createElement('label');
          label.className='selitem';
          label.innerHTML =
            `<input class="cb" type="checkbox" name="nodes[]" value="${ids}">
             <div>
               <div><strong>${ch.nombre}</strong></div>
               <div class="meta">prestado: $ ${Number(ch.valor).toLocaleString()} • interés: $ ${Number(ch.interes).toLocaleString()} • total: $ ${Number(ch.total).toLocaleString()} • fecha: ${ch.fecha}</div>
             </div>`;
          grid.appendChild(label);
        });

        // seleccionar todo
        const checkAll = document.getElementById('checkAll');
        checkAll.checked = false;
        checkAll.onchange = () => {
          grid.querySelectorAll('input.cb').forEach(i=>{ i.checked = checkAll.checked; });
        };
      }

      // Selección inicial: primer prestamista
      const first = document.querySelector('.prestamista-item');
      if(first){ first.click(); }
      </script>
    <?php endif; ?>

<?php
    $st->close();
  }

  $conn->close();
endif; // list / graph
?>
</body></html>
