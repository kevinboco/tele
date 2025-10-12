<?php
/*********************************************************
 * admin_prestamos.php — CRUD + Tarjetas + Visual 3-nodos
 * Versión: D3 visual mejorada (colores por meses, animación)
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

// ===== CRUD (create/edit/delete) - uso tu código tal cual =====
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
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap">
<style>
:root{ --bg:#f6f7fb; --fg:#111; --card:#fff; --muted:#6b7280; --primary:#2563eb; --accent:#f59e0b; --danger:#ef4444; }
*{box-sizing:border-box}
body{font-family:Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:18px;background:var(--bg);color:var(--fg)}
a{text-decoration:none}
.btn{display:inline-flex;align-items:center;gap:8px;padding:9px 12px;border-radius:12px;background:var(--primary);color:#fff;font-weight:600;border:0;cursor:pointer}
.btn.gray{background:#94a3b8} .btn.red{background:var(--danger)} .btn.small{padding:7px 10px;border-radius:10px}
.tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
.tabs a{background:#e6eefc;color:#111;padding:8px 12px;border-radius:10px;font-weight:700}
.tabs a.active{background:var(--primary);color:#fff}
.card{background:var(--card);border-radius:12px;box-shadow:0 8px 28px rgba(14,20,30,0.04);padding:14px}
.subtitle{font-size:13px;color:var(--muted)}
/* graph layout */
.panel-left{ position:fixed; left:22px; top:86px; width:260px; bottom:22px; overflow:auto; background:#fff; border-radius:12px; padding:12px; box-shadow:0 8px 28px rgba(14,20,30,0.04); }
.panel-left .item{ padding:10px; border-radius:8px; margin-bottom:8px; cursor:pointer; background:#eef6ff; color:#0b3b6f; font-weight:600; display:flex; justify-content:space-between; align-items:center; }
.panel-left .item:hover{ transform:translateX(4px); box-shadow:0 6px 18px rgba(16,24,40,0.04); }
.panel-left .item.active{ background:#dbeeff; box-shadow:0 6px 18px rgba(16,24,40,0.06); }
.svg-container{ margin-left:310px; padding:12px; height:calc(100vh - 140px); background:linear-gradient(180deg,#ffffff00,#ffffff00); border-radius:12px; overflow:hidden; position:relative; }
/* tooltip */
.tooltip{ position:fixed; pointer-events:none; background:rgba(0,0,0,.78); color:#fff; padding:8px 10px; border-radius:8px; font-size:13px; display:none; z-index:9999; box-shadow:0 6px 20px rgba(2,6,23,0.3); }
/* node glow */
.node-shadow { filter: drop-shadow(0 6px 18px rgba(37,99,235,0.12)); }
/* meses colors */
.deudor.m1 circle{ fill:#FFF8DB; stroke:#e6d88c; }
.deudor.m2 circle{ fill:#FFE9D6; stroke:#f0b48a; }
.deudor.m3 circle{ fill:#FFE1E1; stroke:#f3a1a1; }
.prestamista circle{ fill:#0b5ed7; stroke:#0a4cc0; }
/* link */
.link-path{ fill:none; stroke:#9aa4b2; stroke-width:1.2; opacity:0.85; }
/* dim layer */
.dim{ position:absolute; inset:0; background:rgba(255,255,255,0.7); pointer-events:none; transition:opacity .25s; display:none; }
.dim.show{ display:block; opacity:1; }
@media (max-width:900px){ .panel-left{ display:none } .svg-container{ margin-left:24px } }
</style>
</head><body>

<div class="tabs">
  <a class="<?= $view==='cards'?'active':'' ?>" href="?view=cards">📇 Tarjetas</a>
  <a class="<?= $view==='graph'?'active':'' ?>" href="?view=graph">🕸️ Visual</a>
  <a class="btn gray" href="?action=new&view=<?= h($view) ?>" style="margin-left:auto">➕ Crear</a>
</div>

<?php if (!empty($_GET['msg'])): ?>
  <div class="card" style="margin-bottom:14px">
    <div class="subtitle">
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
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
      <div class="title"><?= $action==='new'?'Nuevo préstamo':'Editar préstamo #'.h($id) ?></div>
    </div>
    <?php if(!empty($err)): ?><div style="margin-bottom:10px;color:var(--danger)"><?= h($err) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" action="?action=<?= $action==='new'?'create':'edit&id='.$id ?>&view=<?= h($view) ?>">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
        <div><label>Deudor *</label><input name="deudor" required value="<?= h($row['deudor']) ?>" style="width:100%;padding:10px;border-radius:8px;border:1px solid #e6eefc"></div>
        <div><label>Prestamista *</label><input name="prestamista" required value="<?= h($row['prestamista']) ?>" style="width:100%;padding:10px;border-radius:8px;border:1px solid #e6eefc"></div>
        <div><label>Monto *</label><input name="monto" type="number" step="1" min="0" required value="<?= h($row['monto']) ?>" style="width:100%;padding:10px;border-radius:8px;border:1px solid #e6eefc"></div>
        <div><label>Fecha *</label><input name="fecha" type="date" required value="<?= h($row['fecha']) ?>" style="width:100%;padding:10px;border-radius:8px;border:1px solid #e6eefc"></div>
        <div style="grid-column:1/-1">
          <label>Imagen (opcional)</label>
          <?php if ($action==='edit' && $row['imagen']): ?>
            <div style="margin:8px 0"><img src="uploads/<?= h($row['imagen']) ?>" alt="" style="max-width:220px;border-radius:8px"></div>
            <label><input type="checkbox" name="keep" checked> Mantener imagen actual</label>
          <?php endif; ?>
          <input type="file" name="imagen" accept="image/*">
        </div>
      </div>
      <div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end">
        <button class="btn" type="submit">💾 Guardar</button>
        <a class="btn gray" href="?view=<?= h($view) ?>">Cancelar</a>
      </div>
    </form>
  </div>

<?php
// ====== LIST or GRAPH ======
else:
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
    // -------- TARJETAS (igual que antes) --------
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
      <form class="toolbar" method="get" style="display:flex;gap:10px;align-items:center;">
        <input type="hidden" name="view" value="cards">
        <input name="q" placeholder="🔎 Buscar (deudor / prestamista)" value="<?= h($q) ?>" style="flex:1;min-width:220px;padding:8px;border-radius:8px;border:1px solid #e6eefc">
        <select name="fp" style="padding:8px;border-radius:8px;border:1px solid #e6eefc"><option value="">Todos los prestamistas</option>
          <?php foreach($prestMap as $norm=>$label): ?><option value="<?= h($norm) ?>" <?= $fpNorm===$norm?'selected':'' ?>><?= h(mbtitle($label)) ?></option><?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Filtrar</button>
        <?php if ($q!=='' || $fpNorm!==''): ?><a class="btn gray" href="?view=cards">Quitar filtro</a><?php endif; ?>
      </form>
      <div class="subtitle" style="margin-top:8px">Interés 10% desde el día 1 y luego 10% por mes.</div>
    </div>

    <?php if ($rs->num_rows === 0): ?><div class="card"><span class="subtitle">(sin registros)</span></div>
    <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px">
        <?php while($r=$rs->fetch_assoc()): ?>
          <div class="card">
            <?php if (!empty($r['imagen'])): ?><a href="uploads/<?= h($r['imagen']) ?>" target="_blank"><img src="uploads/<?= h($r['imagen']) ?>" alt="" style="width:100%;border-radius:8px;margin-bottom:8px"></a><?php endif; ?>
            <div style="display:flex;justify-content:space-between;align-items:center">
              <div><div style="font-weight:800">#<?= h($r['id']) ?> • <?= h($r['deudor']) ?></div><div class="subtitle">Prestamista: <strong><?= h($r['prestamista']) ?></strong></div></div>
              <div style="background:#f1f5f9;padding:6px 10px;border-radius:999px"><?= h($r['fecha']) ?></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px">
              <div style="background:#fff9f0;border-radius:8px;padding:10px"><div class="subtitle">Monto</div><div style="font-weight:800">$ <?= money($r['monto']) ?></div></div>
              <div style="background:#f0f9ff;border-radius:8px;padding:10px"><div class="subtitle">Meses</div><div style="font-weight:800"><?= h($r['meses']) ?></div></div>
              <div style="background:#fff;border-radius:8px;padding:10px"><div class="subtitle">Interés</div><div style="font-weight:800">$ <?= money($r['interes']) ?></div></div>
              <div style="background:#fff;border-radius:8px;padding:10px"><div class="subtitle">Total</div><div style="font-weight:800">$ <?= money($r['total']) ?></div></div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px">
              <div class="subtitle">Creado: <?= h($r['created_at']) ?></div>
              <div style="display:flex;gap:8px">
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
    // ====== GRAPH VIEW: construimos datos desde BD (mismos cálculos que tenías) ======
    $where = "(pagado IS NULL OR pagado=0)"; $types=""; $params=[];
    if ($q!==''){ $where.=" AND (LOWER(deudor) LIKE CONCAT('%',?,'%') OR LOWER(prestamista) LIKE CONCAT('%',?,'%'))"; $types.="ss"; $params[]=$qNorm; $params[]=$qNorm; }
    if ($fpNorm!==''){ $where.=" AND LOWER(TRIM(prestamista)) = ?"; $types.="s"; $params[]=$fpNorm; }

    $sql = "
      SELECT prestamista, deudor, MIN(fecha) AS fecha_min,
             CASE WHEN CURDATE() < MIN(fecha)
                  THEN 0
                  ELSE TIMESTAMPDIFF(MONTH, MIN(fecha), CURDATE()) + 1
             END AS meses,
             SUM(monto) AS capital,
             SUM(monto*0.10*CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS interes,
             GROUP_CONCAT(id) AS ids
      FROM prestamos
      WHERE $where
      GROUP BY prestamista, deudor
      ORDER BY prestamista ASC, deudor ASC";
    $st = $conn->prepare($sql);
    if($types) $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();

    $tree = []; // prestamista => array of deudores
    while($r = $res->fetch_assoc()){
      $p = $r['prestamista'];
      $d = $r['deudor'];
      if (!isset($tree[$p])) $tree[$p] = [];
      $tree[$p][] = [
        'deudor'=>$d,
        'monto'=> (float)$r['capital'],
        'fecha'=> $r['fecha_min'],
        'meses'=> (int)$r['meses'],
        'interes'=> (float)$r['interes'],
        'ids' => $r['ids']
      ];
    }
    $st->close();

    $jsonTree = json_encode($tree, JSON_UNESCAPED_UNICODE);
?>
    <div style="display:flex;gap:18px;">
      <div class="panel-left" id="panel-left">
        <div style="font-weight:800;margin-bottom:8px">Prestamistas</div>
        <div style="font-size:13px;color:var(--muted);margin-bottom:10px">Haz click en un prestamista para desplegar sus deudores.</div>
        <!-- items agregados por JS -->
      </div>

      <div class="svg-container" id="svg-container">
        <div style="padding:8px 12px" class="subtitle">Interacción: arrastra nodos • clic en prestamista = expandir/colapsar</div>
        <div id="graph-area" style="width:100%;height:calc(100% - 44px)"></div>
        <div class="dim" id="dim-layer"></div>
      </div>
    </div>

    <form method="post" action="?action=mark_paid" style="margin-top:12px">
      <input type="hidden" name="view" value="graph">
      <div style="display:flex;justify-content:flex-end;gap:8px">
        <button class="btn small" type="submit" onclick="return confirm('¿Marcar como pagados los seleccionados?')">✔ Préstamo pagado</button>
      </div>
    </form>

    <div class="tooltip" id="tooltip"></div>

    <script src="https://d3js.org/d3.v7.min.js"></script>
    <script>
    (function(){
      const treeData = <?php echo $jsonTree; ?>; // { prest: [ {deudor,monto,fecha,meses,interes,ids}, ... ] }
      const prestKeys = Object.keys(treeData);

      // PANEL: crear items
      const panel = document.getElementById('panel-left');
      prestKeys.forEach(p => {
        const el = document.createElement('div');
        el.className = 'item';
        el.dataset.prest = p;
        el.innerHTML = `<span>${p}</span><small style="opacity:.7">${treeData[p].length}</small>`;
        el.addEventListener('click', ()=> togglePrest(p, el));
        panel.appendChild(el);
      });

      // SVG setup
      const container = document.getElementById('graph-area');
      const width = container.clientWidth || Math.max(window.innerWidth - 400, 900);
      const height = container.clientHeight || Math.max(window.innerHeight - 240, 600);
      const svg = d3.select(container).append('svg').attr('width', width).attr('height', height).style('overflow','visible');

      // defs for gradients and shadow
      const defs = svg.append('defs');
      defs.append('filter').attr('id','softShadow').append('feDropShadow')
        .attr('dx',0).attr('dy',8).attr('stdDeviation',12).attr('flood-opacity',0.12);

      // groups
      const linkG = svg.append('g').attr('class','links');
      const nodeG = svg.append('g').attr('class','nodes');

      const tooltip = document.getElementById('tooltip');
      const dimLayer = document.getElementById('dim-layer');

      // data model
      let nodes = []; // { id, label, type, meses, monto, fecha, ids, fx? }
      let links = []; // { sourceId, targetId }
      const idx = {};  // id -> node

      // create prestamista nodes (left entry positions)
      prestKeys.forEach((p,i)=>{
        const id = 'P::'+p;
        const n = { id:id, label:p, type:'prestamista', expanded:false, x:-200, y: 80 + i*62, fx: 120 };
        nodes.push(n); idx[id]=n;
      });

      // simulation
      const sim = d3.forceSimulation(nodes)
        .force('link', d3.forceLink().id(d=>d.id).distance(d => d.source.type==='prestamista' && d.target.type==='deudor' ? 180 : 120 ).strength(0.9))
        .force('charge', d3.forceManyBody().strength(-700))
        .force('collide', d3.forceCollide().radius(d=> d.type==='prestamista'?36:22).strength(0.9))
        .force('center', d3.forceCenter(width/2, height/2))
        .alphaTarget(0)
        .on('tick', ticked);

      // render/update
      function update(){
        // links
        const linkSel = linkG.selectAll('path.link-path').data(links, d=>d.source.id+'|'+d.target.id);
        linkSel.join(
          enter => enter.append('path').attr('class','link-path').attr('fill','none').attr('stroke-width',1.3).attr('opacity',0).transition().duration(450).attr('opacity',1),
          update => update,
          exit => exit.transition().duration(300).attr('opacity',0).remove()
        );

        // nodes
        const nodeSel = nodeG.selectAll('g.node').data(nodes, d=>d.id);
        const nodeEnter = nodeSel.enter().append('g').attr('class', d => 'node '+ (d.type==='deudor'?('deudor m'+(Math.min(d.meses,3))):'prestamista'))
          .call(d3.drag()
            .on('start', dragStarted)
            .on('drag', dragged)
            .on('end', dragEnded)
          )
          .on('mouseover', (e,d)=> showTooltip(e,d) )
          .on('mousemove', (e)=> moveTooltip(e) )
          .on('mouseout', hideTooltip)
          .on('click', (e,d)=> {
            if (d.type==='prestamista') {
              const label = d.label;
              const panelItem = Array.from(document.querySelectorAll('.panel-left .item')).find(it=>it.dataset.prest===label);
              togglePrest(label, panelItem);
            }
          });

        nodeEnter.append('circle')
          .attr('r', d=> d.type==='prestamista'?28:18)
          .attr('filter','url(#softShadow)')
          .attr('stroke-width',1.2)
          .attr('opacity',0)
          .transition().duration(600).attr('opacity',1);

        nodeEnter.append('text').attr('dy',4).attr('font-size',12).attr('x', d=> d.type==='prestamista' ? -36 : 22)
          .attr('text-anchor', d=> d.type==='prestamista' ? 'end' : 'start')
          .text(d=> d.type==='prestamista' ? d.label : (d.label.length>20? d.label.slice(0,20)+'…' : d.label));

        nodeSel.exit().transition().duration(300).attr('opacity',0).remove();

        sim.nodes(nodes);
        sim.force('link').links(links);
        sim.alpha(0.9).restart();
      }

      function ticked(){
        // curved links (quadratic Bezier)
        linkG.selectAll('path.link-path').attr('d', function(d){
          const sx = d.source.x, sy = d.source.y;
          const tx = d.target.x, ty = d.target.y;
          const mx = (sx + tx) / 2;
          const my = (sy + ty) / 2;
          // control point offset
          const dx = tx - sx;
          const dy = ty - sy;
          const nx = mx - dy*0.12;
          const ny = my + dx*0.12;
          return `M ${sx},${sy} Q ${nx},${ny} ${tx},${ty}`;
        });

        nodeG.selectAll('g.node')
          .attr('transform', d => `translate(${d.x},${d.y})`);
      }

      // toggle expand/collapse for prestamista
      function togglePrest(prest, uiDiv){
        // highlight panel
        document.querySelectorAll('.panel-left .item').forEach(it=>it.classList.remove('active'));
        if (uiDiv) uiDiv.classList.add('active');

        const pId = 'P::' + prest;
        const base = idx[pId];
        if (!base) return;

        // dim others for focus
        const isExpanding = !base.expanded;
        if (isExpanding) dimLayer.classList.add('show'); else dimLayer.classList.remove('show');

        if (base.expanded){
          // collapse: remove children and related links
          const kids = nodes.filter(n => n.parent === pId);
          kids.forEach(k => {
            // remove links with that kid
            links = links.filter(l => !(l.source.id === base.id && l.target.id === k.id));
            delete idx[k.id];
          });
          nodes = nodes.filter(n => n.parent !== pId);
          base.expanded = false;
        } else {
          // expand: add children from treeData
          const items = treeData[prest] || [];
          // position new children to the right near the base
          items.forEach((it, i) => {
            const kidId = 'D::' + prest + '::' + it.deudor;
            if (idx[kidId]) return; // ya existe
            const newNode = {
              id: kidId,
              label: it.deudor,
              type: 'deudor',
              parent: pId,
              meses: parseInt(it.meses||0),
              monto: it.monto,
              fecha: it.fecha,
              interes: it.interes,
              ids: it.ids,
              x: base.x + 240 + (i%3)*24,
              y: base.y + (i*44) - (items.length*22)
            };
            nodes.push(newNode);
            idx[kidId] = newNode;
            links.push({ source: base, target: newNode });
          });
          base.expanded = true;
        }
        // fade other prestamistas when expanded
        if (isExpanding) {
          nodeG.selectAll('g.node').filter(d=>d.type==='prestamista' && d.id!==base.id)
            .transition().duration(300).style('opacity',0.25);
          linkG.selectAll('path.link-path').transition().duration(300).style('opacity',0.15);
        } else {
          nodeG.selectAll('g.node').transition().duration(300).style('opacity',1);
          linkG.selectAll('path.link-path').transition().duration(300).style('opacity',1);
        }
        update();
      }

      // tooltip functions
      function showTooltip(ev,d){
        if (d.type !== 'deudor') return;
        tooltip.style.display = 'block';
        tooltip.innerHTML = `<strong>${d.label}</strong><br>💵 $${Number(d.monto).toLocaleString()}<br>📅 ${d.fecha}<br>💰 ${Number(d.interes).toLocaleString()}`;
        moveTooltip(ev);
      }
      function moveTooltip(ev){
        tooltip.style.left = (ev.pageX + 12) + 'px';
        tooltip.style.top  = (ev.pageY - 30) + 'px';
      }
      function hideTooltip(){ tooltip.style.display = 'none'; }

      // drag handlers
      function dragStarted(event,d){
        if (!event.active) sim.alphaTarget(0.2).restart();
        d.fx = d.x; d.fy = d.y;
      }
      function dragged(event,d){
        d.fx = event.x; d.fy = event.y;
      }
      function dragEnded(event,d){
        if (!event.active) sim.alphaTarget(0);
        d.fx = null; d.fy = null;
      }

      // initial entry animation: slide prestamistas from left
      // after a small delay update to force positions
      update();
      nodeG.selectAll('g.node').attr('transform', d=>`translate(${ -220 },${ d.y })`);
      nodeG.selectAll('g.node').transition().duration(900).delay((d,i)=> i*40 ).attr('transform', d=>`translate(${ d.x },${ d.y })`);

      // responsive
      window.addEventListener('resize', ()=> {
        const W = container.clientWidth || Math.max(window.innerWidth - 400, 900);
        const H = container.clientHeight || Math.max(window.innerHeight - 240, 600);
        svg.attr('width', W).attr('height', H);
        sim.force('center', d3.forceCenter(W/2, H/2));
        sim.alpha(0.6).restart();
      });

    })();
    </script>

<?php
  } // end graph/cards
  $conn->close();
endif; // list / graph
?>
</body></html>
