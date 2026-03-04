<?php
/*********************************************************
 * admin_prestamos.php — CRUD + Tarjetas
 * - Filtro dinámico: deudores según empresa seleccionada
 * - Dropdowns con búsqueda (Select2)
 * - PAGO INTELIGENTE: Vista previa + reestructuración
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

// ===== NUEVAS FUNCIONES PARA PAGO INTELIGENTE =====
function getTasaInteres($fecha) {
    return strtotime($fecha) >= strtotime('2025-10-29') ? 13 : 10;
}

function calcularMeses($fecha) {
    $fechaObj = new DateTime($fecha);
    $hoy = new DateTime();
    if ($hoy < $fechaObj) return 0;
    $diff = $fechaObj->diff($hoy);
    return ($diff->y * 12) + $diff->m + 1;
}

function calcularTotalPrestamo($monto, $fecha, $comision_origen_porcentaje = null, $comision_gestor_porcentaje = 0, $comision_base_monto = null) {
    $tasaOrigen = $comision_origen_porcentaje ?? getTasaInteres($fecha);
    $meses = calcularMeses($fecha);
    $interesOrigen = $monto * ($tasaOrigen / 100) * $meses;
    $baseComision = $comision_base_monto ?? $monto;
    $interesGestor = $baseComision * ($comision_gestor_porcentaje / 100) * $meses;
    return [
        'meses' => $meses,
        'interes_origen' => $interesOrigen,
        'interes_gestor' => $interesGestor,
        'interes_total' => $interesOrigen + $interesGestor,
        'total' => $monto + $interesOrigen + $interesGestor,
        'tasa_origen' => $tasaOrigen,
        'tasa_gestor' => $comision_gestor_porcentaje
    ];
}

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

// ===== NUEVA ACCIÓN AJAX: Vista previa de pago =====
if ($action === 'vista_previa' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $ids = json_decode($_POST['ids'] ?? '[]', true);
    $montoPago = (float)($_POST['monto_pago'] ?? 0);
    $orden = $_POST['orden'] ?? 'antiguo';
    
    if (empty($ids) || $montoPago <= 0) {
        echo json_encode(['error' => 'Selecciona préstamos y un monto válido']);
        exit;
    }
    
    $conn = db();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $sql = "SELECT id, deudor, prestamista, monto, fecha, 
                   comision_origen_porcentaje, comision_gestor_porcentaje, comision_base_monto,
                   pagado
            FROM prestamos 
            WHERE id IN ($placeholders) AND pagado = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $prestamos = [];
    while ($row = $result->fetch_assoc()) {
        $calc = calcularTotalPrestamo(
            $row['monto'], 
            $row['fecha'], 
            $row['comision_origen_porcentaje'],
            $row['comision_gestor_porcentaje'] ?? 0,
            $row['comision_base_monto']
        );
        $row['meses'] = $calc['meses'];
        $row['interes_origen'] = $calc['interes_origen'];
        $row['interes_gestor'] = $calc['interes_gestor'];
        $row['interes_total'] = $calc['interes_total'];
        $row['total'] = $calc['total'];
        $row['tasa_origen'] = $calc['tasa_origen'];
        $row['tasa_gestor'] = $calc['tasa_gestor'];
        $prestamos[] = $row;
    }
    $stmt->close();
    
    // Ordenar según criterio
    if ($orden === 'antiguo') {
        usort($prestamos, function($a, $b) { return strtotime($a['fecha']) - strtotime($b['fecha']); });
    } elseif ($orden === 'reciente') {
        usort($prestamos, function($a, $b) { return strtotime($b['fecha']) - strtotime($a['fecha']); });
    } elseif ($orden === 'mayor_interes') {
        usort($prestamos, function($a, $b) { return $b['interes_total'] - $a['interes_total']; });
    }
    
    // Procesar pago
    $restante = $montoPago;
    $resultado = [];
    $prestamosAReestructurar = [];
    
    foreach ($prestamos as $p) {
        if ($restante <= 0) {
            $resultado[] = [
                'id' => $p['id'],
                'deudor' => $p['deudor'],
                'monto_original' => $p['monto'],
                'total_original' => $p['total'],
                'accion' => 'no_tocado',
                'mensaje' => 'No alcanzó para este préstamo'
            ];
            continue;
        }
        
        if ($restante >= $p['total']) {
            // Pagar completo
            $resultado[] = [
                'id' => $p['id'],
                'deudor' => $p['deudor'],
                'monto_original' => $p['monto'],
                'interes_pagado' => $p['interes_total'],
                'abono_capital' => $p['monto'],
                'total_pagado' => $p['total'],
                'accion' => 'pagado_completo',
                'mensaje' => 'Préstamo pagado completamente'
            ];
            $restante -= $p['total'];
        } else {
            // Pago parcial - reestructurar
            if ($restante >= $p['interes_total']) {
                $interesPagado = $p['interes_total'];
                $abonoCapital = $restante - $interesPagado;
                $nuevoCapital = $p['monto'] - $abonoCapital;
                
                $resultado[] = [
                    'id' => $p['id'],
                    'deudor' => $p['deudor'],
                    'monto_original' => $p['monto'],
                    'interes_pagado' => $interesPagado,
                    'abono_capital' => $abonoCapital,
                    'nuevo_capital' => $nuevoCapital,
                    'accion' => 'reestructurar',
                    'tasa_origen' => $p['tasa_origen'],
                    'tasa_gestor' => $p['tasa_gestor'],
                    'prestamista' => $p['prestamista'],
                    'mensaje' => "Se pagan intereses ($" . money($interesPagado) . ") y se abonan $" . money($abonoCapital) . " a capital"
                ];
                $prestamosAReestructurar[] = [
                    'original_id' => $p['id'],
                    'nuevo_capital' => $nuevoCapital,
                    'deudor' => $p['deudor'],
                    'prestamista' => $p['prestamista'],
                    'tasa_origen' => $p['tasa_origen'],
                    'tasa_gestor' => $p['tasa_gestor']
                ];
            } else {
                $resultado[] = [
                    'id' => $p['id'],
                    'deudor' => $p['deudor'],
                    'monto_original' => $p['monto'],
                    'interes_pagado' => $restante,
                    'abono_capital' => 0,
                    'accion' => 'pago_parcial_intereses',
                    'mensaje' => "Pago parcial de intereses: $" . money($restante)
                ];
            }
            $restante = 0;
            break;
        }
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'monto_pago' => $montoPago,
        'restante' => $restante,
        'resultado' => $resultado,
        'prestamos_reestructurar' => $prestamosAReestructurar,
        'total_procesado' => $montoPago - $restante
    ]);
    exit;
}

// ===== NUEVA ACCIÓN AJAX: Confirmar pago =====
if ($action === 'confirmar_pago' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $resultado = $data['resultado'] ?? [];
    $reestructurar = $data['reestructurar'] ?? [];
    
    if (empty($resultado)) {
        echo json_encode(['error' => 'No hay datos para procesar']);
        exit;
    }
    
    $conn = db();
    $conn->begin_transaction();
    
    try {
        // Marcar como pagados los préstamos completos
        foreach ($resultado as $item) {
            if ($item['accion'] === 'pagado_completo') {
                $stmt = $conn->prepare("UPDATE prestamos SET pagado = 1, pagado_at = NOW() WHERE id = ?");
                $stmt->bind_param("i", $item['id']);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        // Procesar reestructuraciones
        foreach ($reestructurar as $r) {
            // Marcar original como reestructurado
            $stmt = $conn->prepare("UPDATE prestamos SET pagado = 1, pagado_at = NOW(), nota = CONCAT('Reestructurado - Nuevo préstamo #', ?) WHERE id = ?");
            $nuevoId = 0;
            $stmt->bind_param("ii", $nuevoId, $r['original_id']);
            $stmt->execute();
            $stmt->close();
            
            // Crear nuevo préstamo
            $stmt = $conn->prepare("INSERT INTO prestamos 
                (deudor, prestamista, monto, fecha, created_at, empresa, 
                 comision_origen_porcentaje, comision_gestor_porcentaje, nota, prestamo_origen_id) 
                VALUES (?, ?, ?, CURDATE(), NOW(), '', ?, ?, CONCAT('Reestructuración del préstamo #', ?), ?)");
            $stmt->bind_param("ssddii", 
                $r['deudor'], 
                $r['prestamista'], 
                $r['nuevo_capital'], 
                $r['tasa_origen'], 
                $r['tasa_gestor'], 
                $r['original_id'],
                $r['original_id']
            );
            $stmt->execute();
            $nuevoId = $stmt->insert_id;
            $stmt->close();
            
            // Actualizar nota del original
            $stmt = $conn->prepare("UPDATE prestamos SET nota = CONCAT('Reestructurado - Nuevo préstamo #', ?) WHERE id = ?");
            $stmt->bind_param("ii", $nuevoId, $r['original_id']);
            $stmt->execute();
            $stmt->close();
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Pago procesado correctamente']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['error' => 'Error al procesar el pago: ' . $e->getMessage()]);
    }
    
    $conn->close();
    exit;
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
 
 /* NUEVO: Estilos para modal de vista previa */
 .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
 .modal-content { background-color: #fff; margin: 30px auto; padding: 20px; border-radius: 16px; max-width: 800px; max-height: 80vh; overflow-y: auto; }
 .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
 .close:hover { color: #000; }
 .preview-item { background: #f8fafc; border-left: 4px solid var(--primary); padding: 12px; margin-bottom: 12px; border-radius: 8px; }
 .preview-item.pagado { border-left-color: #10b981; background: #f0fdf4; }
 .preview-item.reestructurar { border-left-color: #f59e0b; background: #fffbeb; }
 .badge-pill { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
 .badge-pill.success { background: #10b981; color: white; }
 .badge-pill.warning { background: #f59e0b; color: white; }
 .badge-pill.info { background: #6c757d; color: white; }
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

<!-- NUEVO MODAL DE VISTA PREVIA -->
<div id="modalPreview" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('modalPreview').style.display='none'">&times;</span>
        <h2>📋 Vista Previa del Pago</h2>
        <div id="previewContent"></div>
        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
            <button class="btn gray" onclick="document.getElementById('modalPreview').style.display='none'">Cancelar</button>
            <button class="btn" id="btnConfirmarPago">✅ Confirmar Pago</button>
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
    $sqlDeud = "SELECT DISTINCT deudor FROM prestamos WHERE LOWER(TRIM(empresa)) = ? AND $whereBase ORDER BY deudor";
    $stDeud = $conn->prepare($sqlDeud);
    $stDeud->bind_param("s", $feNorm);
    $stDeud->execute();
    $resDeud = $stDeud->get_result();
  } else {
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

  $sql = "
      SELECT id,deudor,prestamista,monto,fecha,imagen,created_at,pagado,pagado_at,
             empresa,
             comision_gestor_nombre, comision_gestor_porcentaje, comision_base_monto, 
             comision_origen_prestamista, comision_origen_porcentaje
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

    <!-- NUEVO: Panel de Pago Inteligente -->
    <div class="card" style="margin-bottom:16px; background:#f0f9ff; border:1px solid #bae6fd;">
        <div class="row">
            <div class="title">💰 Pago Inteligente</div>
            <div class="subtitle">Selecciona préstamos y aplica pago con reestructuración automática</div>
        </div>
        <div class="row" style="gap:12px; margin-top:10px;">
            <div class="field" style="min-width:200px;">
                <label>Monto a pagar</label>
                <input type="number" id="montoPago" placeholder="Ej: 4000000" min="0" step="1000" value="">
            </div>
            <div class="field" style="min-width:150px;">
                <label>Orden de aplicación</label>
                <select id="ordenPago">
                    <option value="antiguo">Más antiguos primero</option>
                    <option value="reciente">Más recientes primero</option>
                    <option value="mayor_interes">Mayor interés primero</option>
                </select>
            </div>
            <div style="align-self:flex-end;">
                <button class="btn" type="button" id="btnVistaPrevia" onclick="vistaPreviaPago()">🔍 Vista Previa</button>
            </div>
        </div>
        <div class="subtitle" style="margin-top:8px;">
            <span id="selectedCount">0</span> préstamos seleccionados
        </div>
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
            // Calcular totales para mostrar en tarjeta
            $calc = calcularTotalPrestamo(
                $r['monto'], 
                $r['fecha'], 
                $r['comision_origen_porcentaje'],
                $r['comision_gestor_porcentaje'] ?? 0,
                $r['comision_base_monto']
            );
            
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
                <?php if (!$esPagado): ?>
                    <input class="chkRow" type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>" onchange="actualizarContadorSeleccionados()">
                <?php else: ?>
                    <span style="width:20px; display:inline-block;"></span>
                <?php endif; ?>
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
                  <div class="v"><?= $calc['meses'] ?></div>
                </div>
                <div class="item">
                  <div class="k">Interés</div>
                  <div class="v">$ <?= money($calc['interes_total']) ?></div>
                </div>
                <div class="item">
                  <div class="k">Total</div>
                  <div class="v">$ <?= money($calc['total']) ?></div>
                </div>
              </div>

              <!-- Desglose interés si hay comisión -->
              <?php if ($esComision): ?>
                <div class="pairs" style="margin-top:8px; font-size:12px;">
                  <div class="item">
                    <div class="k">Interés Prestamista</div>
                    <div class="v">$ <?= money($calc['interes_origen']) ?></div>
                  </div>
                  <div class="item">
                    <div class="k">Comisión Gestor</div>
                    <div class="v">$ <?= money($calc['interes_gestor']) ?></div>
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
// Variables globales
let ultimaVistaPrevia = null;

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
  
  // Guardar referencia a los selects
  const empresaSelect = $('#empresaSelect');
  const deudorSelect = $('#deudorSelect');
  const estadoPagoRadios = document.querySelectorAll('input[name="estado_pago"]');
  
  // Variable para guardar el deudor seleccionado antes de cambiar
  let deudorSeleccionado = deudorSelect.val();
  
  // Función para cargar deudores según empresa
  function cargarDeudoresPorEmpresa(empresaNormalizada, estadoPago) {
    if (!empresaNormalizada) {
      cargarTodosDeudores(estadoPago);
      return;
    }
    
    deudorSelect.html('<option value="">Cargando deudores...</option>');
    
    fetch(`ajax_cargar_deudores.php?empresa=${encodeURIComponent(empresaNormalizada)}&estado=${estadoPago}`)
      .then(response => {
        if (!response.ok) throw new Error('Error en la respuesta');
        return response.json();
      })
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
        console.error('Error cargando deudores:', error);
        deudorSelect.html('<option value="">Error cargando deudores</option>');
      });
  }
  
  function cargarTodosDeudores(estadoPago) {
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
  
  empresaSelect.on('change', function() {
    const empresaNormalizada = $(this).val();
    const estadoPago = document.querySelector('input[name="estado_pago"]:checked')?.value || 'no_pagados';
    deudorSeleccionado = deudorSelect.val();
    cargarDeudoresPorEmpresa(empresaNormalizada, estadoPago);
  });
  
  estadoPagoRadios.forEach(radio => {
    radio.addEventListener('change', function() {
      const empresaNormalizada = empresaSelect.val();
      const estadoPago = this.value;
      deudorSeleccionado = deudorSelect.val();
      
      if (empresaNormalizada) {
        cargarDeudoresPorEmpresa(empresaNormalizada, estadoPago);
      } else {
        cargarTodosDeudores(estadoPago);
      }
    });
  });
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

// NUEVAS FUNCIONES PARA PAGO INTELIGENTE
function actualizarContadorSeleccionados() {
    const checks = document.querySelectorAll('.chkRow:checked');
    document.getElementById('selectedCount').textContent = checks.length;
}

function vistaPreviaPago() {
    const checks = document.querySelectorAll('.chkRow:checked');
    const ids = Array.from(checks).map(c => c.value);
    const monto = document.getElementById('montoPago').value;
    const orden = document.getElementById('ordenPago').value;
    
    if (ids.length === 0) {
        alert('Selecciona al menos un préstamo');
        return;
    }
    if (!monto || monto <= 0) {
        alert('Ingresa un monto válido');
        return;
    }
    
    document.getElementById('previewContent').innerHTML = '<p>Cargando vista previa...</p>';
    document.getElementById('modalPreview').style.display = 'block';
    
    fetch('?action=vista_previa', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ids=' + JSON.stringify(ids) + '&monto_pago=' + monto + '&orden=' + orden
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            document.getElementById('previewContent').innerHTML = '<p class="error">' + data.error + '</p>';
            return;
        }
        
        ultimaVistaPrevia = data;
        
        let html = `
            <div style="margin-bottom:16px; background:#e8f7ee; padding:12px; border-radius:8px;">
                <strong>💰 Monto a pagar:</strong> $${new Intl.NumberFormat('es-CO').format(data.monto_pago)}<br>
                <strong>💵 Total a procesar:</strong> $${new Intl.NumberFormat('es-CO').format(data.total_procesado)}<br>
                <strong>🔄 Restante:</strong> $${new Intl.NumberFormat('es-CO').format(data.restante)}
            </div>
        `;
        
        data.resultado.forEach(item => {
            let clase = 'preview-item';
            let badge = '';
            
            if (item.accion === 'pagado_completo') {
                clase += ' pagado';
                badge = '<span class="badge-pill success">✅ PAGADO COMPLETO</span>';
            } else if (item.accion === 'reestructurar') {
                clase += ' reestructurar';
                badge = '<span class="badge-pill warning">🔄 REESTRUCTURAR</span>';
            } else if (item.accion === 'no_tocado') {
                badge = '<span class="badge-pill info">⏳ NO TOCADO</span>';
            } else {
                badge = '<span class="badge-pill info">⏳ PARCIAL</span>';
            }
            
            html += `<div class="${clase}">`;
            html += `<div style="display:flex; justify-content:space-between; margin-bottom:8px;">`;
            html += `<strong>#${item.id} - ${item.deudor}</strong> ${badge}`;
            html += `</div>`;
            
            if (item.accion === 'pagado_completo') {
                html += `<div>💰 Capital: $${new Intl.NumberFormat('es-CO').format(item.monto_original)}</div>`;
                html += `<div>💵 Interés pagado: $${new Intl.NumberFormat('es-CO').format(item.interes_pagado)}</div>`;
                html += `<div>✅ Total pagado: $${new Intl.NumberFormat('es-CO').format(item.total_pagado)}</div>`;
            } else if (item.accion === 'reestructurar') {
                html += `<div>💰 Capital original: $${new Intl.NumberFormat('es-CO').format(item.monto_original)}</div>`;
                html += `<div>💵 Interés pagado: $${new Intl.NumberFormat('es-CO').format(item.interes_pagado)}</div>`;
                html += `<div>📉 Abono a capital: $${new Intl.NumberFormat('es-CO').format(item.abono_capital)}</div>`;
                html += `<div style="background:#f59e0b20; padding:8px; border-radius:6px; margin-top:8px;">`;
                html += `<strong>🆕 NUEVO PRÉSTAMO:</strong> $${new Intl.NumberFormat('es-CO').format(item.nuevo_capital)} (hoy)`;
                html += `</div>`;
            } else {
                html += `<div>⏳ Este préstamo no se modificó</div>`;
            }
            
            html += `<div class="subtitle" style="margin-top:8px;">${item.mensaje}</div>`;
            html += `</div>`;
        });
        
        document.getElementById('previewContent').innerHTML = html;
    })
    .catch(error => {
        document.getElementById('previewContent').innerHTML = '<p class="error">Error: ' + error + '</p>';
    });
}

// Confirmar pago
document.getElementById('btnConfirmarPago')?.addEventListener('click', function() {
    if (!ultimaVistaPrevia) {
        alert('No hay vista previa para confirmar');
        return;
    }
    
    if (!confirm('¿Confirmar el pago? Esta acción no se puede deshacer.')) {
        return;
    }
    
    fetch('?action=confirmar_pago', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            resultado: ultimaVistaPrevia.resultado,
            reestructurar: ultimaVistaPrevia.prestamos_reestructurar
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            alert('Error: ' + data.error);
        } else {
            alert('✅ Pago procesado correctamente');
            location.reload();
        }
    })
    .catch(error => {
        alert('Error: ' + error);
    });
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

// Cerrar modal con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('modalPreview').style.display = 'none';
    }
});
</script>

</body></html>