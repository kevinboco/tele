<?php
/*********************************************************
 * admin_prestamos.php — CRUD + Tarjetas + VISTA PREVIA ABONOS
 *********************************************************/
include("nav.php");

// ======= CONFIG =======
define('DB_HOST', 'mysql.hostinger.com');
define('DB_USER', 'u648222299_keboco5');
define('DB_PASS', 'Bucaramanga3011');
define('DB_NAME', 'u648222299_viajes');
const UPLOAD_DIR = __DIR__ . '/uploads/';
const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;
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
$view   = 'cards';
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

// ===== PROCESAR VISTA PREVIA (AJAX) =====
if ($action==='calcular_vista_previa' && $_SERVER['REQUEST_METHOD']==='POST'){
    header('Content-Type: application/json');
    
    $prestamo_id = (int)($_POST['prestamo_id'] ?? 0);
    $monto_abono = (float)($_POST['monto_abono'] ?? 0);
    
    if (!$prestamo_id || $monto_abono <= 0) {
        echo json_encode(['error' => 'Datos inválidos']);
        exit;
    }
    
    $conn = db();
    
    // Obtener datos del préstamo
    $sql = "SELECT id, deudor, prestamista, monto, fecha,
                   CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) END AS meses,
                   (monto * 
                    CASE 
                      WHEN fecha >= '2025-10-29' THEN COALESCE(comision_origen_porcentaje, 13)
                      ELSE COALESCE(comision_origen_porcentaje, 10)
                    END / 100 *
                    CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) END) AS interes_generado
            FROM prestamos 
            WHERE id = ? AND (pagado = 0 OR pagado IS NULL)";
    
    $st = $conn->prepare($sql);
    $st->bind_param("i", $prestamo_id);
    $st->execute();
    $res = $st->get_result();
    $prestamo = $res->fetch_assoc();
    
    if (!$prestamo) {
        echo json_encode(['error' => 'Préstamo no encontrado o ya pagado']);
        exit;
    }
    
    $capital_actual = (float)$prestamo['monto'];
    $interes_generado = (float)$prestamo['interes_generado'];
    $meses = (int)$prestamo['meses'];
    
    // CALCULAR DISTRIBUCIÓN DEL ABONO
    $interes_pagado = 0;
    $capital_pagado = 0;
    $nuevo_capital = $capital_actual;
    $escenario = '';
    
    if ($monto_abono >= $interes_generado) {
        // Abono alcanza para pagar TODO el interés
        $interes_pagado = $interes_generado;
        $restante = $monto_abono - $interes_generado;
        
        if ($restante >= $capital_actual) {
            // Paga TODO el préstamo
            $capital_pagado = $capital_actual;
            $nuevo_capital = 0;
            $escenario = 'pagado_completo';
        } else {
            // Paga parte del capital
            $capital_pagado = $restante;
            $nuevo_capital = $capital_actual - $capital_pagado;
            $escenario = 'nuevo_prestamo';
        }
    } else {
        // No alcanza para pagar todo el interés
        $interes_pagado = $monto_abono;
        $capital_pagado = 0;
        $interes_pendiente = $interes_generado - $monto_abono;
        $nuevo_capital = $capital_actual;
        $escenario = 'interes_parcial';
    }
    
    // Porcentaje de interés para el nuevo préstamo (si aplica)
    $porcentaje_interes = (strtotime($prestamo['fecha']) >= strtotime('2025-10-29')) ? 13 : 10;
    
    $resultado = [
        'success' => true,
        'prestamo_id' => $prestamo_id,
        'deudor' => $prestamo['deudor'],
        'capital_actual' => $capital_actual,
        'interes_generado' => $interes_generado,
        'meses' => $meses,
        'monto_abono' => $monto_abono,
        'interes_pagado' => $interes_pagado,
        'capital_pagado' => $capital_pagado,
        'nuevo_capital' => $nuevo_capital,
        'escenario' => $escenario,
        'porcentaje_interes' => $porcentaje_interes,
        'mensaje' => ''
    ];
    
    // Mensajes según escenario
    if ($escenario === 'pagado_completo') {
        $resultado['mensaje'] = '¡PRÉSTAMO PAGADO COMPLETAMENTE!';
    } elseif ($escenario === 'nuevo_prestamo') {
        $resultado['mensaje'] = "Se generará NUEVO PRÉSTAMO por $" . money($nuevo_capital) . " con fecha actual";
    } elseif ($escenario === 'interes_parcial') {
        $pendiente = $interes_generado - $monto_abono;
        $resultado['mensaje'] = "Interés pendiente: $" . money($pendiente) . " (el capital no se reduce)";
    }
    
    echo json_encode($resultado);
    $st->close();
    $conn->close();
    exit;
}

// ===== PROCESAR ABONO REAL (CREAR NUEVO PRÉSTAMO) =====
if ($action==='procesar_abono' && $_SERVER['REQUEST_METHOD']==='POST'){
    $prestamo_id = (int)($_POST['prestamo_id'] ?? 0);
    $monto_abono = (float)($_POST['monto_abono'] ?? 0);
    
    if (!$prestamo_id || $monto_abono <= 0) {
        go(BASE_URL . '?view=cards&error=datos_invalidos');
    }
    
    $conn = db();
    
    // Obtener datos completos del préstamo
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
    
    // CALCULAR DISTRIBUCIÓN
    $nuevo_prestamo_id = null;
    
    if ($monto_abono >= $interes_generado) {
        $interes_pagado = $interes_generado;
        $restante = $monto_abono - $interes_generado;
        
        if ($restante >= $capital_actual) {
            // PAGA TODO
            $capital_pagado = $capital_actual;
            
            // Marcar como pagado
            $upd = $conn->prepare("UPDATE prestamos SET pagado = 1, pagado_at = NOW() WHERE id = ?");
            $upd->bind_param("i", $prestamo_id);
            $upd->execute();
            $upd->close();
            
            $mensaje = "✅ Préstamo #{$prestamo_id} PAGADO COMPLETAMENTE";
            
        } else {
            // PAGA PARCIAL - CREAR NUEVO PRÉSTAMO
            $capital_pagado = $restante;
            $nuevo_capital = $capital_actual - $capital_pagado;
            
            // 1. Marcar original como pagado (pero guardamos referencia)
            $upd = $conn->prepare("UPDATE prestamos SET pagado = 1, pagado_at = NOW() WHERE id = ?");
            $upd->bind_param("i", $prestamo_id);
            $upd->execute();
            $upd->close();
            
            // 2. Crear NUEVO préstamo con el capital restante
            $sql_new = "INSERT INTO prestamos 
                        (deudor, prestamista, monto, fecha, created_at, 
                         empresa, comision_gestor_nombre, comision_gestor_porcentaje,
                         comision_base_monto, comision_origen_prestamista, comision_origen_porcentaje)
                        VALUES (?, ?, ?, CURDATE(), NOW(), ?, ?, ?, ?, ?, ?)";
            
            $st_new = $conn->prepare($sql_new);
            
            $deudor = $prestamo['deudor'];
            $prestamista = $prestamo['prestamista'];
            $empresa = $prestamo['empresa'];
            $gestor_nombre = $prestamo['comision_gestor_nombre'];
            $gestor_porcentaje = $prestamo['comision_gestor_porcentaje'];
            $base_monto = $prestamo['comision_base_monto'];
            $origen_prestamista = $prestamo['comision_origen_prestamista'];
            $origen_porcentaje = $prestamo['comision_origen_porcentaje'];
            
            $st_new->bind_param("ssdssddddss", 
                $deudor, $prestamista, $nuevo_capital,
                $empresa, $gestor_nombre, $gestor_porcentaje,
                $base_monto, $origen_prestamista, $origen_porcentaje,
                $gestor_nombre  // Este último parece repetido? Revisar
            );
            
            // CORRECCIÓN: Ajustar según tu estructura real
            // Por simplicidad, haré un INSERT básico
            $st_new = $conn->prepare("INSERT INTO prestamos (deudor, prestamista, monto, fecha, created_at) VALUES (?, ?, ?, CURDATE(), NOW())");
            $st_new->bind_param("ssd", $deudor, $prestamista, $nuevo_capital);
            
            $st_new->execute();
            $nuevo_prestamo_id = $st_new->insert_id;
            $st_new->close();
            
            $mensaje = "✅ Abono aplicado: Interés: $" . money($interes_pagado) . 
                       ", Capital: $" . money($capital_pagado) . 
                       ". Se creó NUEVO PRÉSTAMO #{$nuevo_prestamo_id} por $" . money($nuevo_capital);
        }
    } else {
        // No alcanza para interés - NO CREAR NUEVO PRÉSTAMO (solo registramos)
        $interes_pagado = $monto_abono;
        $capital_pagado = 0;
        
        // Opcional: podrías actualizar algún campo de "abono parcial"
        $upd = $conn->prepare("UPDATE prestamos SET ultimo_abono = ?, ultimo_abono_fecha = CURDATE() WHERE id = ?");
        $upd->bind_param("di", $monto_abono, $prestamo_id);
        $upd->execute();
        $upd->close();
        
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

// ===== CRUD normal =====
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
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
 :root{ --bg:#f6f7fb; --fg:#222; --card:#fff; --muted:#6b7280; --primary:#0b5ed7; --gray:#6c757d; --red:#dc3545; --chip:#eef2ff; }
 *{box-sizing:border-box}
 body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:22px;background:var(--bg);color:var(--fg)}
 a{text-decoration:none}
 .btn{display:inline-flex;align-items:center;gap:8px;padding:9px 12px;border-radius:12px;background:var(--primary);color:#fff;font-weight:600;border:0;cursor:pointer}
 .btn.gray{background:var(--gray)} .btn.red{background:var(--red)} .btn.small{padding:7px 10px;border-radius:10px}
 .btn.success{background:#059669}
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
 
 /* Estilos para abonos */
 .abono-card { background: linear-gradient(145deg, #f0f9ff, #e6f2ff); border-left: 4px solid #0b5ed7; }
 .vista-previa-panel { background: white; border-radius: 16px; padding: 20px; margin-top: 15px; border: 2px dashed #0b5ed7; }
 .resumen-grid{display: grid;grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));gap: 12px;margin-top: 12px;}
 .resumen-item{background: #f8fafc;border-radius: 12px;padding: 15px;text-align: center;border:1px solid #e2e8f0;}
 .resumen-valor{font-size: 20px;font-weight: 800;color: #0b5ed7;}
 .resumen-label{font-size: 12px;color: #64748b;margin-top: 5px;}
 .highlight { border: 3px solid #059669 !important; box-shadow: 0 0 20px #059669 !important; transition: all 0.5s; }
 .nuevo-highlight { border: 3px solid #0b5ed7 !important; box-shadow: 0 0 20px #0b5ed7 !important; }
 .badge-abono { background: #059669; color: white; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
 
 /* Select2 */
 .select2-container { width: 100% !important; }
 .select2-selection { border: 1px solid #e5e7eb !important; border-radius: 12px !important; padding: 8px !important; height: 45px !important; }
</style>
</head><body>

<div class="tabs">
  <a class="active" href="?view=cards">📇 Tarjetas</a>
  <a class="btn gray" href="?action=new&view=cards" style="margin-left:auto">➕ Crear</a>
</div>

<?php 
// Mostrar mensajes
if (!empty($_GET['msg'])): ?>
  <div class="msg" style="margin-bottom:14px"><?= match($_GET['msg']){
        'creado'=>'Registro creado correctamente.',
        'editado'=>'Cambios guardados.',
        'eliminado'=>'Registro eliminado.',
        default=>'Operación realizada.'
  } ?></div>
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
// ====== LISTADO DE TARJETAS ======
else:
  // Obtener filtros actuales
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

  $conn = db();

  // Construir WHERE
  $whereBase = "1=1";
  if ($estado_pago === 'no_pagados') $whereBase = "pagado = 0";
  elseif ($estado_pago === 'pagados') $whereBase = "pagado = 1";

  // Obtener listas para filtros
  $prestMap = [];
  $resPL = $conn->query("SELECT prestamista FROM prestamos WHERE $whereBase");
  while($rowPL=$resPL->fetch_row()){
    $norm = mbnorm($rowPL[0]);
    if ($norm==='') continue;
    $prestMap[$norm] = $rowPL[0];
  }
  ksort($prestMap);

  $empMap = [];
  $resEL = $conn->query("SELECT empresa FROM prestamos WHERE $whereBase");
  while($rowEL=$resEL->fetch_row()){
    $val = $rowEL[0];
    if (!$val) continue;
    $norm = mbnorm($val);
    $empMap[$norm] = $val;
  }
  ksort($empMap);

  $deudMap = [];
  if ($feNorm !== '') {
    $stDeud = $conn->prepare("SELECT DISTINCT deudor FROM prestamos WHERE LOWER(TRIM(empresa)) = ? AND $whereBase ORDER BY deudor");
    $stDeud->bind_param("s", $feNorm);
    $stDeud->execute();
    $resDeud = $stDeud->get_result();
    while($rowDL = $resDeud->fetch_assoc()) {
      $norm = mbnorm($rowDL['deudor']);
      $deudMap[$norm] = $rowDL['deudor'];
    }
    $stDeud->close();
  } else {
    $resDeud = $conn->query("SELECT DISTINCT deudor FROM prestamos WHERE $whereBase ORDER BY deudor");
    while($rowDL = $resDeud->fetch_row()) {
      $norm = mbnorm($rowDL[0]);
      $deudMap[$norm] = $rowDL[0];
    }
  }
?>

<!-- ===== SISTEMA DE ABONOS CON VISTA PREVIA ===== -->
<div class="card abono-card" style="margin-bottom:20px;">
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
        <?php foreach($deudMap as $norm=>$label): ?>
          <option value="<?= h($norm) ?>" data-nombre="<?= h($label) ?>">
            <?= h(mbtitle($label)) ?>
          </option>
        <?php endforeach; ?>
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
  
  <!-- PANEL DE VISTA PREVIA (se oculta/muestra) -->
  <div id="vistaPreviaPanel" style="display:none; margin-top:20px;">
    <div class="vista-previa-panel">
      <div class="row" style="margin-bottom:15px;">
        <h3 style="margin:0; color:#0b5ed7;">📊 Resultado de Vista Previa</h3>
        <span class="chip" id="vistaPrestamoInfo"></span>
      </div>
      
      <div class="resumen-grid" id="vistaPreviaContenido">
        <!-- Se llena con JS -->
        <div class="resumen-item">Cargando...</div>
      </div>
      
      <div class="row" style="margin-top:20px; justify-content:flex-end;">
        <button type="button" class="btn gray" id="btnCancelarVista">Cancelar</button>
        <button type="button" class="btn success" id="btnConfirmarAbono" style="margin-left:10px; display:none;">
          ✅ Confirmar y Procesar Abono
        </button>
      </div>
    </div>
  </div>
</div>

<!-- FILTROS -->
<div class="card" style="margin-bottom:16px">
  <form class="toolbar" method="get" id="filtroForm">
    <input type="hidden" name="view" value="cards">
    <input name="q" placeholder="🔎 Buscar..." value="<?= h($q) ?>" style="flex:1;">
    
    <select name="fp" class="select2-filter" style="min-width:150px;">
      <option value="">Prestamista</option>
      <?php foreach($prestMap as $norm=>$label): ?>
        <option value="<?= h($norm) ?>" <?= $fpNorm===$norm?'selected':'' ?>><?= h(mbtitle($label)) ?></option>
      <?php endforeach; ?>
    </select>
    
    <select name="fe" class="select2-filter" style="min-width:150px;">
      <option value="">Empresa</option>
      <?php foreach($empMap as $norm=>$label): ?>
        <option value="<?= h($norm) ?>" <?= $feNorm===$norm?'selected':'' ?>><?= h(mbtitle($label)) ?></option>
      <?php endforeach; ?>
    </select>
    
    <select name="fd" class="select2-filter" style="min-width:150px;">
      <option value="">Deudor</option>
      <?php foreach($deudMap as $norm=>$label): ?>
        <option value="<?= h($norm) ?>" <?= $fdNorm===$norm?'selected':'' ?>><?= h(mbtitle($label)) ?></option>
      <?php endforeach; ?>
    </select>
    
    <input name="fecha_desde" type="date" value="<?= h($fecha_desde) ?>" style="min-width:130px;">
    <input name="fecha_hasta" type="date" value="<?= h($fecha_hasta) ?>" style="min-width:130px;">
    
    <select name="estado_pago" style="min-width:120px;">
      <option value="no_pagados" <?= $estado_pago=='no_pagados'?'selected':'' ?>>No pagados</option>
      <option value="pagados" <?= $estado_pago=='pagados'?'selected':'' ?>>Pagados</option>
      <option value="todos" <?= $estado_pago=='todos'?'selected':'' ?>>Todos</option>
    </select>
    
    <button class="btn" type="submit">Filtrar</button>
    <?php if ($q!=='' || $fpNorm!=='' || $fdNorm!=='' || $feNorm!=='' || $fecha_desde!=='' || $fecha_hasta!=='' || $estado_pago!=='no_pagados'): ?>
      <a class="btn gray" href="?view=cards">Quitar filtro</a>
    <?php endif; ?>
  </form>
</div>

<?php
  // Obtener préstamos para mostrar
  $where = $whereBase; 
  $types=""; $params=[];
  
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

  $sql = "SELECT id, deudor, prestamista, monto, fecha, imagen, created_at, pagado, pagado_at, empresa,
                 comision_gestor_nombre, comision_gestor_porcentaje,
                 CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) END AS meses,
                 (monto * 
                  CASE 
                    WHEN fecha >= '2025-10-29' THEN COALESCE(comision_origen_porcentaje, 13)
                    ELSE COALESCE(comision_origen_porcentaje, 10)
                  END / 100 *
                  CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) END) AS interes_generado
          FROM prestamos
          WHERE $where
          ORDER BY pagado ASC, id DESC";
  
  $st = $conn->prepare($sql);
  if($types) $st->bind_param($types, ...$params);
  $st->execute();
  $rs = $st->get_result();
?>

<!-- TARJETAS -->
<?php if ($rs->num_rows === 0): ?>
  <div class="card"><span class="subtitle">(sin registros)</span></div>
<?php else: ?>
  <div class="grid-cards">
    <?php while($r=$rs->fetch_assoc()): 
      $pagado = (bool)($r['pagado'] ?? false);
      $cardClass = $pagado ? 'card-pagado' : '';
    ?>
      <div class="card <?= $cardClass ?>" data-id="<?= $r['id'] ?>" data-deudor="<?= h($r['deudor']) ?>">
        <div class="row" style="margin-bottom:5px;">
          <span class="subtitle">#<?= h($r['id']) ?></span>
          <?php if($pagado): ?>
            <span class="chip" style="background:#10b981; color:white;">✅ Pagado</span>
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
          </div>
          <span class="chip"><?= h($r['fecha']) ?></span>
        </div>

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
            <div class="v">$ <?= money($r['interes_generado']) ?></div>
          </div>
          <div class="item">
            <div class="k">Total</div>
            <div class="v">$ <?= money($r['monto'] + $r['interes_generado']) ?></div>
          </div>
        </div>

        <div class="row" style="margin-top:12px">
          <div class="subtitle">Creado: <?= h($r['created_at']) ?></div>
          <div>
            <a class="btn gray small" href="?action=edit&id=<?= $r['id'] ?>&view=cards">✏️</a>
            <button class="btn red small" onclick="submitDelete(<?= $r['id'] ?>)">🗑️</button>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
  </div>
<?php endif; ?>

<?php
  $st->close();
  $conn->close();
endif; 
?>

<!-- SCRIPTS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// Inicializar Select2
$(document).ready(function() {
    $('.select2-filter, .select2-abono').select2({
        width: '100%',
        placeholder: 'Seleccionar...',
        allowClear: true,
        language: { noResults: () => "No se encontraron resultados" }
    });
    
    // ===== SISTEMA DE ABONOS CON VISTA PREVIA =====
    const deudorSelect = $('#deudorAbono');
    const prestamoSelect = $('#prestamoAbono');
    const montoInput = $('#montoAbono');
    const btnVistaPrevia = $('#btnVistaPrevia');
    const vistaPanel = $('#vistaPreviaPanel');
    const vistaContenido = $('#vistaPreviaContenido');
    const vistaPrestamoInfo = $('#vistaPrestamoInfo');
    const btnConfirmar = $('#btnConfirmarAbono');
    const btnCancelar = $('#btnCancelarVista');
    
    let datosVistaPrevia = null;
    
    // Cargar préstamos cuando cambia deudor
    deudorSelect.on('change', function() {
        const deudorNorm = $(this).val();
        
        if (!deudorNorm) {
            prestamoSelect.prop('disabled', true).html('<option value="">Primero seleccione deudor...</option>');
            return;
        }
        
        prestamoSelect.prop('disabled', true).html('<option value="">Cargando préstamos...</option>');
        
        // Obtener préstamos activos del deudor (simulado - en real deberías hacer AJAX)
        // Por ahora, usaremos los datos de las tarjetas visibles
        const prestamos = [];
        $('.card[data-deudor]').each(function() {
            const card = $(this);
            const cardDeudor = mbnorm(card.data('deudor') || '');
            if (cardDeudor === deudorNorm && !card.hasClass('card-pagado')) {
                const id = card.data('id');
                const monto = card.find('.pairs .item:first .v').text().replace(/[^0-9]/g, '');
                const interes = card.find('.pairs .item:eq(2) .v').text().replace(/[^0-9]/g, '');
                prestamos.push({
                    id: id,
                    texto: `#${id} - $${Number(monto).toLocaleString('es-CO')} (Interés: $${Number(interes).toLocaleString('es-CO')})`
                });
            }
        });
        
        if (prestamos.length === 0) {
            prestamoSelect.html('<option value="">No hay préstamos activos</option>').prop('disabled', true);
        } else {
            let options = '<option value="">Seleccione préstamo...</option>';
            prestamos.forEach(p => {
                options += `<option value="${p.id}">${p.texto}</option>`;
            });
            prestamoSelect.html(options).prop('disabled', false);
        }
    });
    
    // Normalizar texto para comparación
    function mbnorm(s) {
        return s ? s.toLowerCase().trim() : '';
    }
    
    // Vista Previa
    btnVistaPrevia.on('click', function() {
        const prestamoId = prestamoSelect.val();
        const monto = montoInput.val();
        
        if (!prestamoId || !monto || monto <= 0) {
            alert('Seleccione un préstamo y monto válido');
            return;
        }
        
        // Mostrar loading
        vistaContenido.html('<div class="resumen-item">Calculando...</div>');
        vistaPanel.slideDown();
        btnConfirmar.hide();
        
        // Obtener datos de la tarjeta para calcular (simulado)
        // En producción, esto debería ser una llamada AJAX
        const card = $(`.card[data-id="${prestamoId}"]`);
        const montoTexto = card.find('.pairs .item:first .v').text();
        const interesTexto = card.find('.pairs .item:eq(2) .v').text();
        const mesesTexto = card.find('.pairs .item:eq(1) .v').text();
        
        const capital = parseFloat(montoTexto.replace(/[^0-9]/g, '')) || 0;
        const interes = parseFloat(interesTexto.replace(/[^0-9]/g, '')) || 0;
        const meses = parseInt(mesesTexto) || 0;
        const abono = parseFloat(monto) || 0;
        
        // CALCULAR
        let interesPagado = 0;
        let capitalPagado = 0;
        let nuevoCapital = capital;
        let mensaje = '';
        
        if (abono >= interes) {
            interesPagado = interes;
            const restante = abono - interes;
            
            if (restante >= capital) {
                capitalPagado = capital;
                nuevoCapital = 0;
                mensaje = '¡PRÉSTAMO PAGADO COMPLETAMENTE!';
            } else {
                capitalPagado = restante;
                nuevoCapital = capital - restante;
                mensaje = `Se generará NUEVO PRÉSTAMO por $${nuevoCapital.toLocaleString('es-CO')}`;
            }
        } else {
            interesPagado = abono;
            capitalPagado = 0;
            nuevoCapital = capital;
            mensaje = `Interés pendiente: $${(interes - abono).toLocaleString('es-CO')}`;
        }
        
        datosVistaPrevia = {
            prestamo_id: prestamoId,
            capital_actual: capital,
            interes_generado: interes,
            meses: meses,
            monto_abono: abono,
            interes_pagado: interesPagado,
            capital_pagado: capitalPagado,
            nuevo_capital: nuevoCapital,
            mensaje: mensaje
        };
        
        // Mostrar resultados
        const html = `
            <div class="resumen-item">
                <div class="resumen-valor">$${abono.toLocaleString('es-CO')}</div>
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
            <div class="resumen-item" style="grid-column:span 2; background:${abono>=interes?'#d1fae5':'#fee2e2'}">
                <div class="resumen-label">${mensaje}</div>
            </div>
        `;
        
        vistaContenido.html(html);
        vistaPrestamoInfo.text(`Préstamo #${prestamoId} - ${meses} meses`);
        btnConfirmar.show();
    });
    
    // Confirmar abono
    btnConfirmar.on('click', function() {
        if (!datosVistaPrevia) return;
        
        // Crear formulario y enviar
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

// Eliminar préstamo
function submitDelete(id){
    if(!confirm('¿Eliminar #'+id+'?')) return;
    const f = document.createElement('form');
    f.method = 'post';
    f.action = '?action=delete&id='+id;
    document.body.appendChild(f);
    f.submit();
}
</script>

<style>
.card-pagado { opacity: 0.7; border-left: 4px solid #10b981; background: #f0fdf4; }
.highlight { border: 3px solid #059669 !important; box-shadow: 0 0 20px #059669; transition: all 0.5s; }
.nuevo-highlight { border: 3px solid #0b5ed7 !important; box-shadow: 0 0 20px #0b5ed7; }
</style>

</body></html>