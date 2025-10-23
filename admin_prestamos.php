<?php
/*********************************************************
 * admin_prestamos.php — CRUD + Tarjetas + Visual 3-nodos
 * - Normaliza nombres (no distingue mayúsc/minúsc)
 * - Interés 10% desde el día 1 + 10% por mes
 * - Deudor: valor prestado + fecha + interés + total
 * - Selector de deudores (fuera del SVG) + "Préstamo pagado"
 * - En el 3er nodo: Ganancia + Total prestado (pendiente)
 * - Colorear nodo 2 según meses (1, 2, 3+)
 * - Filtro por Deudor + resumen de sumas del deudor
 * - Selección múltiple en Tarjetas + Edición en lote
 *********************************************************/
include("nav.php");

// ======= CONFIG =======
define('DB_HOST', 'mysql.hostinger.com');
define('DB_USER', 'u648222299_keboco5');
define('DB_PASS', 'Bucaramanga3011');
define('DB_NAME', 'u648222299_viajes');
const UPLOAD_DIR = __DIR__ . '/uploads/';
const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0775, true);

// ===== Helpers =====
function db(): mysqli { $m = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); if ($m->connect_errno) exit("Error DB: ".$m->connect_error); $m->set_charset('utf8mb4'); return $m; }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES,'UTF-8'); }
function money($n){ return number_format((float)$n,0,',','.'); }
function go($qs){ header("Location: ".$qs); exit; }
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

  // conservar filtros al regresar
  $redir_view = $_POST['view'] ?? 'cards';
  $redir_q    = $_POST['q'] ?? '';
  $redir_fp   = $_POST['fp'] ?? '';
  $redir_fd   = $_POST['fd'] ?? '';

  if (!$ids) {
    go('?view='.urlencode($redir_view).'&q='.urlencode($redir_q).'&fp='.urlencode($redir_fp).'&fd='.urlencode($redir_fd).'&msg=noselect');
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
    // No hubo campos a actualizar
    go('?view='.urlencode($redir_view).'&q='.urlencode($redir_q).'&fp='.urlencode($redir_fp).'&fd='.urlencode($redir_fd).'&msg=noupdate');
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
  go('?view='.urlencode($redir_view).'&q='.urlencode($redir_q).'&fp='.urlencode($redir_fp).'&fd='.urlencode($redir_fd)."&msg=$msg");
}

/* ===== CRUD ===== */
if ($action==='create' && $_SERVER['REQUEST_METHOD']==='POST'){
  $deudor = trim($_POST['deudor']??'');
  $prestamista = trim($_POST['prestamista']??'');
  $monto = trim($_POST['monto']??'');
  $fecha = trim($_POST['fecha']??'');
  $img = save_image($_FILES['imagen']??null);

  if ($deudor && $prestamista && is_numeric($monto) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)){
    $c=db(); $st=$c->prepare("INSERT INTO prestamos (deudor,prestamista,monto,fecha,imagen,created_at) VALUES (?,?,?,?,?,NOW())");
    $st->bind_param("ssdss",$deudor,$prestamista,$monto,$fecha,$img); $st->execute();
    $st->close(); $c->close(); go('?msg=creado&view='.urlencode($view));
  } else { $err="Completa todos los campos correctamente."; }
}

if ($action==='edit' && $_SERVER['REQUEST_METHOD']==='POST' && $id>0){
  $deudor=trim($_POST['deudor']??''); $prestamista=trim($_POST['prestamista']??'');
  $monto=trim($_POST['monto']??'');   $fecha=trim($_POST['fecha']??'');
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
// ====== LIST ======
else:

  // ==== filtros ====
  $q   = trim($_GET['q']  ?? '');
  $fp  = trim($_GET['fp'] ?? ''); // prestamista (normalizado)
  $fd  = trim($_GET['fd'] ?? ''); // deudor (normalizado)

  $qNorm  = mbnorm($q);
  $fpNorm = mbnorm($fp);
  $fdNorm = mbnorm($fd);

  $conn=db();

  // Combo prestamistas
  $prestMap = [];
  $resPL = ($view==='graph')
    ? $conn->query("SELECT prestamista FROM prestamos WHERE (pagado IS NULL OR pagado=0)")
    : $conn->query("SELECT prestamista FROM prestamos");
  while($rowPL=$resPL->fetch_row()){
    $norm = mbnorm($rowPL[0]);
    if ($norm==='') continue;
    if (!isset($prestMap[$norm])) $prestMap[$norm] = $rowPL[0];
  }
  ksort($prestMap, SORT_NATURAL);

  // Combo deudores
  $deudMap = [];
  $resDL = ($view==='graph')
    ? $conn->query("SELECT deudor FROM prestamos WHERE (pagado IS NULL OR pagado=0)")
    : $conn->query("SELECT deudor FROM prestamos");
  while($rowDL=$resDL->fetch_row()){
    $norm = mbnorm($rowDL[0]);
    if ($norm==='') continue;
    if (!isset($deudMap[$norm])) $deudMap[$norm] = $rowDL[0];
  }
  ksort($deudMap, SORT_NATURAL);

  if ($view==='cards'){
    // -------- TARJETAS --------
    $where = "1"; $types=""; $params=[];
    if ($q!==''){ $where.=" AND (LOWER(deudor) LIKE CONCAT('%',?,'%') OR LOWER(prestamista) LIKE CONCAT('%',?,'%'))"; $types.="ss"; $params[]=$qNorm; $params[]=$qNorm; }
    if ($fpNorm!==''){ $where.=" AND LOWER(TRIM(prestamista)) = ?"; $types.="s"; $params[]=$fpNorm; }
    if ($fdNorm!==''){ $where.=" AND LOWER(TRIM(deudor)) = ?"; $types.="s"; $params[]=$fdNorm; }

    $sql = "
      SELECT id,deudor,prestamista,monto,fecha,imagen,created_at,
             CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END AS meses,
             (monto*0.10*CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS interes,
             (monto + (monto*0.10*CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END)) AS total
      FROM prestamos
      WHERE $where
      ORDER BY id DESC";
    $st=$conn->prepare($sql); if($types) $st->bind_param($types, ...$params); $st->execute(); $rs=$st->get_result();
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
        <button class="btn" type="submit">Filtrar</button>
        <?php if ($q!=='' || $fpNorm!=='' || $fdNorm!==''): ?>
          <a class="btn gray" href="?view=cards">Quitar filtro</a>
        <?php endif; ?>
      </form>
      <div class="subtitle">Interés 10% desde el día 1 y luego 10% por mes.</div>
    </div>

    <?php
      // Resumen de deudor si aplica
      if ($fdNorm!=='') {
        $typesAgg = "s"; $paramsAgg = [$fdNorm];
        $sqlAgg = "
          SELECT COUNT(*) AS n,
                 SUM(monto) AS capital,
                 SUM(monto*0.10*CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS interes,
                 SUM(monto + (monto*0.10*CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END)) AS total
          FROM prestamos
          WHERE LOWER(TRIM(deudor)) = ?";
        $stAgg=$conn->prepare($sqlAgg); $stAgg->bind_param($typesAgg, ...$paramsAgg); $stAgg->execute(); $sum=$stAgg->get_result()->fetch_assoc();
        $stAgg->close();
        $deudorLabel = isset($deudMap[$fdNorm]) ? mbtitle($deudMap[$fdNorm]) : '(deudor)';
    ?>
      <div class="card" style="margin-bottom:16px">
        <div class="row">
          <div class="title">Resumen de <?= h($deudorLabel) ?></div>
          <span class="chip"><?= (int)($sum['n']??0) ?> préstamo(s)</span>
        </div>
        <div class="pairs" style="margin-top:10px">
          <div class="item"><div class="k">Capital</div><div class="v">$ <?= money($sum['capital']??0) ?></div></div>
          <div class="item"><div class="k">Interés</div><div class="v">$ <?= money($sum['interes']??0) ?></div></div>
          <div class="item"><div class="k">Total</div><div class="v">$ <?= money($sum['total']??0) ?></div></div>
        </div>
      </div>
    <?php } ?>

    <?php if ($rs->num_rows === 0): ?>
      <div class="card"><span class="subtitle">(sin registros)</span></div>
    <?php else: ?>
      <!-- FORM para selección múltiple + edición en lote (NO forms anidados dentro) -->
      <form id="bulkForm" class="card" method="post" action="?action=bulk_update">
        <!-- conservar filtros -->
        <input type="hidden" name="view" value="cards">
        <input type="hidden" name="q"  value="<?= h($q) ?>">
        <input type="hidden" name="fp" value="<?= h($fpNorm) ?>">
        <input type="hidden" name="fd" value="<?= h($fdNorm) ?>">

        <div class="row" style="margin-bottom:8px">
          <div class="title">Selecciona tarjetas</div>
          <div class="sticky-actions" style="display:flex;gap:8px;align-items:center">
            <label class="subtitle" style="display:flex;gap:8px;align-items:center">
              <input id="chkAll" type="checkbox"> Seleccionar todo (página)
            </label>
            <button type="button" class="btn gray small" id="btnToggleBulk">✏️ Editar selección</button>
            <span class="badge" id="selCount">0 seleccionadas</span>
          </div>
        </div>

        <div class="grid-cards">
          <?php while($r=$rs->fetch_assoc()): ?>
            <div class="card">
              <div class="cardSel">
                <input class="chkRow" type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>">
                <div class="subtitle">#<?= h($r['id']) ?></div>
              </div>

              <?php if (!empty($r['imagen'])): ?>
                <a href="uploads/<?= h($r['imagen']) ?>" target="_blank"><img class="thumb" src="uploads/<?= h($r['imagen']) ?>" alt=""></a>
              <?php endif; ?>

              <div class="row" style="margin-top:8px">
                <div>
                  <div class="title"><?= h($r['deudor']) ?></div>
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

                  <!-- IMPORTANTE: NO usar form interno (evita forms anidados).
                       Usamos un botón que crea un form temporal por JS para mantener
                       exactamente la misma lógica de eliminación del backend. -->
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
    // -------- VISUAL 3-NODOS (SOLO NO PAGADOS) --------
    $where = "(pagado IS NULL OR pagado=0)"; $types=""; $params=[];
    if ($q!==''){ $where.=" AND (LOWER(deudor) LIKE CONCAT('%',?,'%') OR LOWER(prestamista) LIKE CONCAT('%',?,'%'))"; $types.="ss"; $params[]=$qNorm; $params[]=$qNorm; }
    if ($fpNorm!==''){ $where.=" AND LOWER(TRIM(prestamista)) = ?"; $types.="s"; $params[]=$fpNorm; }

    // Totales por prestamista+deudor (sin IDs) — con 'meses' para colorear
    $sql = "
      SELECT LOWER(TRIM(prestamista)) AS prest_key, MIN(prestamista) AS prest_display,
             LOWER(TRIM(deudor)) AS deud_key, MIN(deudor) AS deud_display,
             MIN(fecha) AS fecha_min,
             CASE WHEN CURDATE() < MIN(fecha)
                  THEN 0
                  ELSE TIMESTAMPDIFF(MONTH, MIN(fecha), CURDATE()) + 1
             END AS meses,
             SUM(monto) AS capital,
             SUM(monto*0.10*CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS interes,
             SUM(monto + monto*0.10*CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS total
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
        <input name="q" placeholder="🔎 Buscar (deudor / prestamista)" value="<?= h($q) ?>" style="flex:1;min-width:220px">
        <select name="fp"><option value="">Todos los prestamistas</option>
          <?php foreach($prestMap as $norm=>$label): ?><option value="<?= h($norm) ?>" <?= $fpNorm===$norm?'selected':'' ?>><?= h(mbtitle($label)) ?></option><?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Filtrar</button>
        <?php if ($q!=='' || $fpNorm!==''): ?><a class="btn gray" href="?view=graph">Quitar filtro</a><?php endif; ?>
      </form>
      <div class="subtitle">
        Diagrama: <strong>Prestamista ➜ Deudores (valor, fecha, interés, total) ➜ Ganancia</strong>.
        Debajo hay un selector para marcar <strong>Préstamo pagado</strong>.
        El recuadro adicional muestra <strong>Total prestado (pendiente)</strong>.
      </div>
    </div>

    <?php if (empty($groups)): ?>
      <div class="card"><span class="subtitle">(sin registros)</span></div>
    <?php else: foreach($groups as $pkey => $ginfo):
        $rows = $ginfo['rows']; $prestLabel = mbtitle($ginfo['label']); $n = count($rows);
        // Geometría
        $rowGap=100; $nodeH=100; $nodeW=320; $headH=52; $topPad=30;
        $firstY=$topPad+80; $lastY=$firstY+max(0,($n-1)*$rowGap); $centerY=($firstY+$lastY)/2; $height=max(250,(int)($lastY+110));
        $xL=140; $xC=560; $xR=1080; $prestY=(int)($centerY-$headH/2); $gainY=$prestY;
        $capPend = $capPendPrest[$pkey] ?? 0.0; // capital no pagado de ese prestamista
    ?>
      <form class="group" method="post" action="?action=mark_paid">
        <input type="hidden" name="view" value="graph">
        <input type="hidden" name="q" value="<?= h($q) ?>">
        <input type="hidden" name="fp" value="<?= h($fpNorm) ?>">

        <div class="title" style="margin:6px 10px 10px">Prestamista: <?= h($prestLabel) ?></div>

        <div class="svgwrap">
          <!-- Leyenda -->
          <div class="subtitle" style="margin:6px 0 8px; padding:0 10px">
            <span class="chip" style="background:#FFF8DB">1 mes</span>
            <span class="chip" style="background:#FFE9D6">2 meses</span>
            <span class="chip" style="background:#FFE1E1">3+ meses</span>
          </div>

          <svg width="1320" height="<?= $height ?>" viewBox="0 0 1320 <?= $height ?>" xmlns="http://www.w3.org/2000/svg">
            <!-- Prestamista -->
            <rect class="nodeRect" x="<?= $xL-90 ?>" y="<?= $prestY ?>" rx="12" ry="12" width="180" height="<?= $headH ?>"/>
            <text class="txt" x="<?= $xL-80 ?>" y="<?= $prestY+20 ?>">Prestamista</text>
            <text class="txt" x="<?= $xL-80 ?>" y="<?= $prestY+40 ?>"><tspan font-weight="800"><?= h($prestLabel) ?></tspan></text>

            <!-- Ganancia -->
            <rect class="nodeRect" x="<?= $xR-120 ?>" y="<?= $gainY ?>" rx="12" ry="12" width="240" height="<?= $headH ?>"/>
            <text class="txt" x="<?= $xR-105 ?>" y="<?= $gainY+20 ?>">Ganancia (interés)</text>
            <text class="txt" x="<?= $xR-105 ?>" y="<?= $gainY+40 ?>">$ <?= money($ganPrest[$pkey] ?? 0) ?></text>

            <!-- Total prestado (pendiente) -->
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

        <!-- Selector de deudores (fuera del SVG) -->
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
      // foco al primer campo del panel
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
