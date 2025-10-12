<?php
/*********************************************************
 * admin_prestamos.php — CRUD + Tarjetas + Visual 3-nodos
 * Integración D3: prestamistas -> click -> desplegar deudores
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

/* ===== Acción: marcar pagado SOLO deudores seleccionados ===== */
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

// ===== CRUD (create/edit/delete) - dejo tal cual tu lógica =====
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

// ===== UI START =====
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
 .group{background:#fff;border-radius:16px;box-shadow:0 6px 20px rgba(0,0,0,.06);padding:12px;margin-bottom:18px}
 .svgwrap{width:100%;overflow:auto;border:1px dashed #e5e7eb;border-radius:12px;background:#fafafa}
 .nodeRect{fill:#ffffff;stroke:#cbd5e1;stroke-width:1.2}
 .txt{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;font-size:13px;fill:#111}
 .mut{fill:#6b7280} .amt{font-weight:800}
 .selector{margin-top:10px;border-top:1px dashed #e5e7eb;padding-top:10px}
 .selhead{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
 .selgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:8px}
 .selitem{display:flex;gap:8px;align-items:flex-start;background:#fafbff;border:1px solid #eef2ff;border-radius:12px;padding:8px}
 .selitem .meta{font-size:12px;color:#555}
 @media (max-width:760px){ .pairs{grid-template-columns:1fr} }
 /* graph styles */
 .panel-left{ position:fixed; left:22px; top:86px; width:260px; bottom:22px; overflow:auto; background:#fff; border-radius:12px; padding:12px; box-shadow:0 6px 20px rgba(0,0,0,.04); }
 .panel-left .item{ padding:10px; border-radius:8px; margin-bottom:8px; cursor:pointer; background:#eef6ff; }
 .panel-left .item.active{ background:#cfe5ff; }
 .svg-container{ margin-left:310px; padding:12px; }
 .tooltip{ position:fixed; pointer-events:none; background:rgba(0,0,0,.8); color:#fff; padding:8px 10px; border-radius:8px; font-size:13px; display:none; z-index:999; }
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
// ====== LIST (cards) ======
else:

  // filtros
  $q  = trim($_GET['q'] ?? '');
  $fp = trim($_GET['fp'] ?? '');
  $qNorm  = mbnorm($q);
  $fpNorm = mbnorm($fp);

  $conn=db();

  // Combo prestamistas
  $prestMap = [];
  $resPL = ($view==='graph') ? $conn->query("SELECT prestamista FROM prestamos WHERE (pagado IS NULL OR pagado=0)") : $conn->query("SELECT prestamista FROM prestamos");
  while($rowPL=$resPL->fetch_row()){
    $norm = mbnorm($rowPL[0]);
    if ($norm==='') continue;
    if (!isset($prestMap[$norm])) $prestMap[$norm] = $rowPL[0];
  }
  ksort($prestMap, SORT_NATURAL);

  if ($view==='cards'){
    // Tu vista de tarjetas original
    $where = "1"; $types=""; $params=[];
    if ($q!==''){ $where.=" AND (LOWER(deudor) LIKE CONCAT('%',?,'%') OR LOWER(prestamista) LIKE CONCAT('%',?,'%'))"; $types.="ss"; $params[]=$qNorm; $params[]=$qNorm; }
    if ($fpNorm!==''){ $where.=" AND LOWER(TRIM(prestamista)) = ?"; $types.="s"; $params[]=$fpNorm; }

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

    <?php if ($rs->num_rows === 0): ?><div class="card"><span class="subtitle">(sin registros)</span></div>
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
    <?php endif;
    $st->close();

  } else {
    // ====== GRAPH VIEW: construir estructura JS desde PHP ======
    $where = "(pagado IS NULL OR pagado=0)"; $types=""; $params=[];
    if ($q!==''){ $where.=" AND (LOWER(deudor) LIKE CONCAT('%',?,'%') OR LOWER(prestamista) LIKE CONCAT('%',?,'%'))"; $types.="ss"; $params[]=$qNorm; $params[]=$qNorm; }
    if ($fpNorm!==''){ $where.=" AND LOWER(TRIM(prestamista)) = ?"; $types.="s"; $params[]=$fpNorm; }

    // obtenemos filas agrupadas por prestamista->deudor (sin IDs por ahora)
    $sql = "
      SELECT prestamista, deudor, monto, fecha,
             CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END AS meses,
             (monto*0.10*CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS interes,
             id
      FROM prestamos
      WHERE $where
      ORDER BY prestamista ASC, deudor ASC";
    $st = $conn->prepare($sql);
    if($types) $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();

    $tree = []; // prestamista => array of deudores (rows)
    $idsMap = []; // prest->deud->csv ids
    while($r = $res->fetch_assoc()){
      $p = $r['prestamista'];
      $d = $r['deudor'];
      if (!isset($tree[$p])) $tree[$p] = [];
      $tree[$p][] = [
        'deudor'=>$d,
        'monto'=>(float)$r['monto'],
        'fecha'=>$r['fecha'],
        'meses'=> (int)$r['meses'],
        'interes'=> (float)$r['interes'],
        'id'=>(int)$r['id']
      ];
      $idsMap[$p][$d][] = (int)$r['id'];
    }
    $st->close();

    // generar estructura JSON segura para JS
    $jsonTree = json_encode($tree, JSON_UNESCAPED_UNICODE);
    $jsonIdsMap = json_encode($idsMap, JSON_UNESCAPED_UNICODE);
?>
    <div class="group" style="position:relative;">
      <div style="display:flex; gap:18px;">
        <div class="panel-left" id="panel-left">
          <div style="font-weight:800;margin-bottom:8px">Prestamistas</div>
          <div style="font-size:13px;color:#666;margin-bottom:10px">Haz click en un prestamista para desplegar sus deudores.</div>
          <!-- lista se llenará por JS -->
        </div>

        <div class="svg-container" style="flex:1">
          <div style="height:20px" class="subtitle">Interacción: arrastra nodos • clic en prestamista = expandir/colapsar</div>
          <div id="svg-area" style="background:transparent; border-radius:8px; padding-top:8px;"></div>
        </div>
      </div>

      <form method="post" action="?action=mark_paid" style="margin-top:12px">
        <input type="hidden" name="view" value="graph">
        <div style="display:flex;justify-content:flex-end;gap:8px">
          <button class="btn small" type="submit" onclick="return confirm('¿Marcar como pagados los seleccionados?')">✔ Préstamo pagado</button>
        </div>
      </form>

      <div class="tooltip" id="tooltip"></div>
    </div>

    <script src="https://d3js.org/d3.v7.min.js"></script>
    <script>
    (function(){
      const tree = <?php echo $jsonTree; ?>;      // { prestamista: [ {deudor,monto,fecha,meses,interes,id}, ... ] }
      const idsMap = <?php echo $jsonIdsMap; ?>;  // for checkboxes if needed

      // Panel left: lista de prestamistas
      const panel = document.getElementById('panel-left');
      const prestKeys = Object.keys(tree);
      prestKeys.forEach((p, idx) => {
        const div = document.createElement('div');
        div.className = 'item';
        div.textContent = p + ' (' + tree[p].length + ')';
        div.dataset.prest = p;
        div.addEventListener('click', () => togglePrest(div.dataset.prest, div));
        panel.appendChild(div);
      });

      // SVG setup
      const area = document.getElementById('svg-area');
      const w = Math.max(window.innerWidth - 360, 800);
      const h = Math.max(window.innerHeight - 220, 480);
      const svg = d3.select(area).append('svg').attr('width', w).attr('height', h);
      const linkGroup = svg.append('g').attr('class','links');
      const nodeGroup = svg.append('g').attr('class','nodes');
      const tooltip = document.getElementById('tooltip');

      // Data model for D3
      let nodes = []; // objects { id, type ('prestamista'|'deudor'), label, ... }
      let links = []; // { source: id, target: id }
      const nodeIndex = {}; // id -> node object reference

      // create prestamista nodes (initially only prestamistas)
      prestKeys.forEach((p,i) => {
        const id = 'P::'+p;
        const node = { id:id, label:p, type:'prestamista', expanded:false, fx: 60, x: -200, y: 80 + i*60 };
        nodes.push(node);
        nodeIndex[id] = node;
      });

      // D3 force simulation
      const simulation = d3.forceSimulation(nodes)
        .force('link', d3.forceLink(links).id(d=>d.id).distance(d=> d.source.type==='prestamista' && d.target.type==='deudor' ? 160 : 120))
        .force('charge', d3.forceManyBody().strength(-600))
        .force('collide', d3.forceCollide().radius(d => d.type==='prestamista'?36:22).strength(0.9))
        .force('center', d3.forceCenter(w/2, h/2))
        .on('tick', ticked);

      // drag handlers
      const drag = d3.drag()
        .on('start', (event,d) => {
          if (!event.active) simulation.alphaTarget(0.2).restart();
          d.fx = d.x; d.fy = d.y;
        })
        .on('drag', (event,d) => { d.fx = event.x; d.fy = event.y; })
        .on('end', (event,d) => {
          if (!event.active) simulation.alphaTarget(0);
          d.fx = null; d.fy = null;
        });

      // render function
      function update() {
        // LINKS
        const linksSel = linkGroup.selectAll('line').data(links, d=>d.source.id + '|' + d.target.id);
        linksSel.join(
          enter => enter.append('line').attr('stroke','#bfc8d3').attr('stroke-width',1.4).attr('opacity',0).transition().duration(500).attr('opacity',1),
          update => update,
          exit => exit.transition().duration(300).attr('opacity',0).remove()
        );

        // NODES
        const nodesSel = nodeGroup.selectAll('g.node').data(nodes, d=>d.id);
        const nodesEnter = nodesSel.enter().append('g').attr('class','node').call(drag)
          .on('mouseover', (event,d)=> showTooltip(event,d))
          .on('mousemove', (event)=> moveTooltip(event))
          .on('mouseout', hideTooltip)
          .on('click', (event,d)=> {
             // clicking a prestamista in the svg also toggles expansion
             if (d.type==='prestamista') {
               const label = d.label.replace(/^P::/,'');
               const matching = Array.from(document.querySelectorAll('.panel-left .item')).find(it=>it.dataset.prest===label);
               togglePrest(label, matching);
             }
          });

        nodesEnter.append('circle')
          .attr('r', d=> d.type==='prestamista' ? 28 : 18)
          .attr('fill', d=> d.type==='prestamista' ? '#0b5ed7' : (d.meses>=3 ? '#ffb4b4' : (d.meses===2 ? '#ffd6b3' : '#fff5bf')))
          .attr('stroke', '#cbd5e1')
          .attr('stroke-width', 1.2)
          .attr('opacity',0)
          .transition().duration(600).attr('opacity',1);

        nodesEnter.append('text').attr('font-size',12).attr('dx', d=> d.type==='prestamista'? -32 : 22).attr('dy',4)
          .text(d=> d.type==='prestamista' ? d.label.replace(/^P::/,'') : (d.label.length>20? d.label.slice(0,20)+'…':d.label));

        nodesSel.exit().transition().duration(300).attr('opacity',0).remove();

        simulation.nodes(nodes);
        simulation.force('link').links(links);
        simulation.alpha(0.8).restart();
      }

      function ticked() {
        linkGroup.selectAll('line')
          .attr('x1', d=>d.source.x).attr('y1', d=>d.source.y)
          .attr('x2', d=>d.target.x).attr('y2', d=>d.target.y);

        nodeGroup.selectAll('g.node')
          .attr('transform', d=>'translate('+ (d.x) +','+ (d.y) +')');
      }

      // Toggle expand/collapse prestamista
      function togglePrest(prest, uiDiv) {
        // highlight in panel
        document.querySelectorAll('.panel-left .item').forEach(it=>it.classList.remove('active'));
        if (uiDiv) uiDiv.classList.add('active');

        const pId = 'P::' + prest;
        const baseNode = nodeIndex[pId];
        if (!baseNode) return;

        if (baseNode.expanded) {
          // collapse children
          const children = nodes.filter(n => n.parent === pId);
          children.forEach(c => {
            // remove corresponding links
            links = links.filter(l => !(l.source.id === pId && l.target.id === c.id));
            // remove nodeIndex entry
            delete nodeIndex[c.id];
          });
          // remove children from nodes array
          nodes = nodes.filter(n => n.parent !== pId);
          baseNode.expanded = false;
        } else {
          // expand: add children nodes and links
          const items = tree[prest] || [];
          // avoid duplicated deudor nodes: check existing ids
          items.forEach(it => {
            const deudId = 'D::' + prest + '::' + it.deudor;
            if (nodeIndex[deudId]) return; // ya existe
            const newNode = {
              id: deudId,
              label: it.deudor,
              type: 'deudor',
              parent: pId,
              monto: it.monto,
              fecha: it.fecha,
              meses: parseInt(it.meses||0),
              interes: it.interes
            };
            nodes.push(newNode);
            nodeIndex[deudId] = newNode;
            links.push({ source: baseNode, target: newNode });
          });
          baseNode.expanded = true;
        }
        update();
      }

      // Tooltip helpers
      function showTooltip(event,d){
        if (d.type !== 'deudor') return;
        tooltip.style.display = 'block';
        tooltip.innerHTML = `<strong>${d.label}</strong><br>💵 ${Number(d.monto).toLocaleString()}<br>📅 ${d.fecha}<br>⚖️ interés: ${d.interes}`;
        moveTooltip(event);
      }
      function moveTooltip(event){
        tooltip.style.left = (event.pageX + 14) + 'px';
        tooltip.style.top  = (event.pageY - 26) + 'px';
      }
      function hideTooltip(){
        tooltip.style.display = 'none';
      }

      // initial render
      update();

      // responsive
      window.addEventListener('resize', ()=> {
        const W = Math.max(window.innerWidth - 360, 800);
        const H = Math.max(window.innerHeight - 220, 480);
        svg.attr('width', W).attr('height', H);
        simulation.force('center', d3.forceCenter(W/2, H/2));
        simulation.alpha(0.6).restart();
      });

    })();
    </script>

<?php
  } // end graph/cards branch

  $conn->close();
endif; // list / graph
?>
</body></html>
