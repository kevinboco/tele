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
    if (!headers_sent()){ header("Location: ".$url); exit; }
    echo "<meta http-equiv='refresh' content='0;url=$url'><script>location.replace('$url');</script>"; exit;
}
function mbnorm($s){ return mb_strtolower(trim((string)$s),'UTF-8'); }
function mbtitle($s){ return mb_convert_case((string)$s, MB_CASE_TITLE, 'UTF-8'); }

// ===== Funciones para pago inteligente =====
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

// ===== ACCIÓN AJAX: Vista previa de pago (CORREGIDA) =====
if ($action === 'vista_previa' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    ob_clean();
    
    try {
        // Obtener datos
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if ($data) {
            $ids = $data['ids'] ?? [];
            $montoPago = (float)($data['monto_pago'] ?? 0);
            $orden = $data['orden'] ?? 'antiguo';
        } else {
            $idsRaw = $_POST['ids'] ?? '[]';
            $ids = json_decode($idsRaw, true);
            $montoPago = (float)($_POST['monto_pago'] ?? 0);
            $orden = $_POST['orden'] ?? 'antiguo';
        }
        
        if (!is_array($ids)) {
            $ids = explode(',', str_replace(['[', ']', '"'], '', $idsRaw));
        }
        
        // Validar
        if (empty($ids) || $montoPago <= 0) {
            throw new Exception('Selecciona préstamos y un monto válido');
        }
        
        $ids = array_filter(array_map('intval', $ids), fn($id) => $id > 0);
        if (empty($ids)) {
            throw new Exception('IDs inválidos');
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
        if (!$stmt) {
            throw new Exception('Error en la consulta');
        }
        
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
        
        if (empty($prestamos)) {
            throw new Exception('No se encontraron préstamos activos');
        }
        
        // Ordenar
        if ($orden === 'antiguo') {
            usort($prestamos, fn($a, $b) => strtotime($a['fecha']) - strtotime($b['fecha']));
        } elseif ($orden === 'reciente') {
            usort($prestamos, fn($a, $b) => strtotime($b['fecha']) - strtotime($a['fecha']));
        } elseif ($orden === 'mayor_interes') {
            usort($prestamos, fn($a, $b) => $b['interes_total'] - $a['interes_total']);
        }
        
        // Procesar pago
        $restante = $montoPago;
        $resultado = [];
        $reestructurar = [];
        
        foreach ($prestamos as $p) {
            if ($restante <= 0) {
                $resultado[] = [
                    'id' => $p['id'],
                    'deudor' => $p['deudor'],
                    'monto_original' => $p['monto'],
                    'accion' => 'no_tocado',
                    'mensaje' => 'No alcanzó para este préstamo'
                ];
                continue;
            }
            
            if ($restante >= $p['total']) {
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
                        'mensaje' => "Se pagan intereses ($" . number_format($interesPagado) . ") y se abonan $" . number_format($abonoCapital) . " a capital"
                    ];
                    $reestructurar[] = [
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
                        'mensaje' => "Pago parcial de intereses: $" . number_format($restante)
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
            'prestamos_reestructurar' => $reestructurar,
            'total_procesado' => $montoPago - $restante
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// ===== ACCIÓN AJAX: Confirmar pago =====
if ($action === 'confirmar_pago' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    ob_clean();
    
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            throw new Exception('Datos inválidos');
        }
        
        $resultado = $data['resultado'] ?? [];
        $reestructurar = $data['reestructurar'] ?? [];
        
        if (empty($resultado)) {
            throw new Exception('No hay datos para procesar');
        }
        
        $conn = db();
        $conn->begin_transaction();
        
        // Marcar pagados
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
            // Marcar original
            $stmt = $conn->prepare("UPDATE prestamos SET pagado = 1, pagado_at = NOW(), nota = CONCAT('Reestructurado - Nuevo préstamo #', ?) WHERE id = ?");
            $nuevoId = 0;
            $stmt->bind_param("ii", $nuevoId, $r['original_id']);
            $stmt->execute();
            $stmt->close();
            
            // Crear nuevo
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
            
            // Actualizar nota
            $stmt = $conn->prepare("UPDATE prestamos SET nota = CONCAT('Reestructurado - Nuevo préstamo #', ?) WHERE id = ?");
            $stmt->bind_param("ii", $nuevoId, $r['original_id']);
            $stmt->execute();
            $stmt->close();
        }
        
        $conn->commit();
        $conn->close();
        
        echo json_encode(['success' => true, 'message' => 'Pago procesado correctamente']);
        exit;
        
    } catch (Exception $e) {
        if (isset($conn)) $conn->rollback();
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

/* ===== Acciones masivas ===== */
if ($action==='bulk_update' && $_SERVER['REQUEST_METHOD']==='POST'){
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) $ids = [];
    $ids = array_values(array_unique(array_map(fn($v)=> (int)$v, $ids)));
    if (!$ids) go(BASE_URL.'?view=cards&msg=noselect');
    
    $new_deudor = trim($_POST['new_deudor'] ?? '');
    $new_prestamista = trim($_POST['new_prestamista'] ?? '');
    $new_monto_raw = trim($_POST['new_monto'] ?? '');
    $new_fecha = trim($_POST['new_fecha'] ?? '');
    
    $sets = []; $types = ''; $values = [];
    if ($new_deudor !== '') { $sets[] = "deudor=?"; $types .= 's'; $values[] = $new_deudor; }
    if ($new_prestamista !== '') { $sets[] = "prestamista=?"; $types .= 's'; $values[] = $new_prestamista; }
    if ($new_monto_raw !== '' && is_numeric($new_monto_raw)) { $sets[] = "monto=?"; $types .= 'd'; $values[] = (float)$new_monto_raw; }
    if ($new_fecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_fecha)) { $sets[] = "fecha=?"; $types .= 's'; $values[] = $new_fecha; }
    
    if (!$sets) go(BASE_URL.'?view=cards&msg=noupdate');
    
    $phIds = implode(',', array_fill(0, count($ids), '?'));
    $types .= str_repeat('i', count($ids));
    $values = array_merge($values, $ids);
    
    $c = db();
    $sql = "UPDATE prestamos SET ".implode(',', $sets)." WHERE id IN ($phIds)";
    $st = $c->prepare($sql);
    $st->bind_param($types, ...$values);
    $ok = $st->execute();
    $st->close(); $c->close();
    
    go(BASE_URL.'?view=cards&msg='.($ok ? 'bulkok' : 'bulkoops'));
}

if ($action==='bulk_mark_paid' && $_SERVER['REQUEST_METHOD']==='POST'){
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) $ids = [];
    $ids = array_values(array_unique(array_map(fn($v)=> (int)$v, $ids)));
    if (!$ids) go(BASE_URL.'?view=cards&msg=noselect');
    
    $c = db();
    $ok = true;
    foreach (array_chunk($ids, 200) as $chunk) {
        $ph = implode(',', array_fill(0, count($chunk), '?'));
        $types = str_repeat('i', count($chunk));
        $sql = "UPDATE prestamos SET pagado = 1, pagado_at = NOW() WHERE id IN ($ph) AND (pagado IS NULL OR pagado = 0)";
        $st = $c->prepare($sql);
        if (!$st) { $ok = false; break; }
        $st->bind_param($types, ...$chunk);
        if (!$st->execute()) { $ok = false; }
        $st->close();
        if (!$ok) break;
    }
    $c->close();
    go(BASE_URL.'?view=cards&msg='.($ok ? 'bulkpaid' : 'bulkpaidoops'));
}

if ($action==='bulk_mark_unpaid' && $_SERVER['REQUEST_METHOD']==='POST'){
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) $ids = [];
    $ids = array_values(array_unique(array_map(fn($v)=> (int)$v, $ids)));
    if (!$ids) go(BASE_URL.'?view=cards&msg=noselect');
    
    $c = db();
    $ok = true;
    foreach (array_chunk($ids, 200) as $chunk) {
        $ph = implode(',', array_fill(0, count($chunk), '?'));
        $types = str_repeat('i', count($chunk));
        $sql = "UPDATE prestamos SET pagado = 0, pagado_at = NULL WHERE id IN ($ph) AND pagado = 1";
        $st = $c->prepare($sql);
        if (!$st) { $ok = false; break; }
        $st->bind_param($types, ...$chunk);
        if (!$st->execute()) { $ok = false; }
        $st->close();
        if (!$ok) break;
    }
    $c->close();
    go(BASE_URL.'?view=cards&msg='.($ok ? 'bulkunpaid' : 'bulkunpaidoops'));
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
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Préstamos | Admin</title>
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
        .error{background:#fdecec;color:#b02a37;padding:8px 12px;border-radius:10px}
        .card{background:var(--card);border-radius:16px;box-shadow:0 6px 20px rgba(0,0,0,.06);padding:16px}
        .subtitle{font-size:13px;color:var(--muted)}
        .grid-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}
        .field{display:flex;flex-direction:column;gap:6px}
        input,select,textarea{padding:11px;border:1px solid #e5e7eb;border-radius:12px;background:#fff}
        .thumb{width:100%;max-height:180px;object-fit:cover;border-radius:12px;border:1px solid #eee}
        .pairs{display:grid;grid-template-columns:1fr 1fr;gap:10px}
        .pairs .item{background:#fafbff;border:1px solid #eef2ff;border-radius:12px;padding:10px}
        .pairs .k{font-size:12px;color:var(--muted)} .pairs .v{font-size:16px;font-weight:700}
        .row{display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap}
        .title{font-size:18px;font-weight:800}
        .chip{display:inline-block;background:var(--chip);padding:4px 8px;border-radius:999px;font-size:12px;font-weight:600}
        .bulkbar{display:flex;gap:10px;align-items:center;margin:8px 0;flex-wrap:wrap}
        .badge{background:#111;color:#fff;border-radius:999px;padding:2px 8px;font-size:12px;font-weight:700}
        .cardSel{display:flex;align-items:center;gap:8px;margin-bottom:6px}
        .sticky-actions{position:sticky; top:10px; align-self:flex-start}
        .card-comision { border-left: 4px solid #0b5ed7; background: #F0F9FF !important; }
        .comision-badge { background: #0b5ed7 !important; color: white !important; }
        .card-pagado { border-left: 4px solid #10b981; background: #f0fdf4 !important; opacity: 0.8; }
        .pagado-badge { background: #10b981 !important; color: white !important; }
        .select2-container { width: 100% !important; }
        
        /* Modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: #fff; margin: 30px auto; padding: 20px; border-radius: 16px; max-width: 800px; max-height: 80vh; overflow-y: auto; }
        .close { float: right; font-size: 28px; cursor: pointer; }
        .preview-item { background: #f8fafc; border-left: 4px solid var(--primary); padding: 12px; margin-bottom: 12px; border-radius: 8px; }
        .preview-item.pagado { border-left-color: #10b981; background: #f0fdf4; }
        .preview-item.reestructurar { border-left-color: #f59e0b; background: #fffbeb; }
        .badge-pill { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .badge-pill.success { background: #10b981; color: white; }
        .badge-pill.warning { background: #f59e0b; color: white; }
        .badge-pill.info { background: #6c757d; color: white; }
    </style>
</head>
<body>

<div class="tabs">
    <a class="active" href="?view=cards">📇 Tarjetas</a>
    <a class="btn gray" href="?action=new&view=cards" style="margin-left:auto">➕ Crear</a>
</div>

<?php if (!empty($_GET['msg'])): ?>
    <div class="msg" style="margin-bottom:14px">
        <?php echo match($_GET['msg']){
            'creado'=>'Registro creado correctamente.',
            'editado'=>'Cambios guardados.',
            'eliminado'=>'Registro eliminado.',
            'bulkok'=>'Actualización en lote aplicada.',
            'bulkpaid'=>'Préstamos marcados como pagados.',
            'bulkunpaid'=>'Préstamos marcados como NO pagados.',
            default=>'Operación realizada.'
        }; ?>
    </div>
<?php endif; ?>

<!-- MODAL -->
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
// ====== FORMULARIOS NUEVO/EDITAR ======
if ($action==='new' || ($action==='edit' && $id>0)):
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
        <div class="title"><?= $action==='new'?'Nuevo préstamo':'Editar préstamo #'.h($id) ?></div>
        <?php if(!empty($err)): ?><div class="error"><?= h($err) ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" action="?action=<?= $action==='new'?'create':'edit&id='.$id ?>&view=cards">
            <div class="row" style="gap:12px">
                <div class="field"><label>Deudor *</label><input name="deudor" required value="<?= h($row['deudor']) ?>"></div>
                <div class="field"><label>Prestamista *</label><input name="prestamista" required value="<?= h($row['prestamista']) ?>"></div>
                <div class="field"><label>Monto *</label><input name="monto" type="number" required value="<?= h($row['monto']) ?>"></div>
                <div class="field"><label>Fecha *</label><input name="fecha" type="date" required value="<?= h($row['fecha']) ?>"></div>
                <div class="field">
                    <label>Imagen</label>
                    <?php if ($action==='edit' && $row['imagen']): ?>
                        <img class="thumb" src="uploads/<?= h($row['imagen']) ?>">
                        <label><input type="checkbox" name="keep" checked> Mantener imagen</label>
                    <?php endif; ?>
                    <input type="file" name="imagen" accept="image/*">
                </div>
            </div>
            <div class="row"><button class="btn" type="submit">💾 Guardar</button> <a class="btn gray" href="?view=cards">Cancelar</a></div>
        </form>
    </div>
<?php
// ====== LISTADO ======
else:
    // Filtros
    $q = trim($_GET['q'] ?? '');
    $fp = trim($_GET['fp'] ?? '');
    $fd = trim($_GET['fd'] ?? '');
    $fe = trim($_GET['fe'] ?? '');
    $fecha_desde = trim($_GET['fecha_desde'] ?? '');
    $fecha_hasta = trim($_GET['fecha_hasta'] ?? '');
    $estado_pago = $_GET['estado_pago'] ?? 'no_pagados';
    
    $qNorm = mbnorm($q);
    $fpNorm = mbnorm($fp);
    $fdNorm = mbnorm($fd);
    $feNorm = mbnorm($fe);
    
    $conn = db();
    
    $whereBase = match($estado_pago) {
        'pagados' => "pagado = 1",
        'todos' => "1=1",
        default => "pagado = 0"
    };
    
    // Obtener prestamistas
    $prestMap = [];
    $res = $conn->query("SELECT DISTINCT prestamista FROM prestamos WHERE $whereBase ORDER BY prestamista");
    while($row = $res->fetch_row()) { $norm = mbnorm($row[0]); if($norm) $prestMap[$norm] = $row[0]; }
    
    // Obtener empresas
    $empMap = [];
    $res = $conn->query("SELECT DISTINCT empresa FROM prestamos WHERE $whereBase AND empresa != '' ORDER BY empresa");
    while($row = $res->fetch_row()) { $norm = mbnorm($row[0]); if($norm) $empMap[$norm] = $row[0]; }
    
    // Obtener deudores
    $deudMap = [];
    $res = $conn->query("SELECT DISTINCT deudor FROM prestamos WHERE $whereBase ORDER BY deudor");
    while($row = $res->fetch_row()) { $norm = mbnorm($row[0]); if($norm) $deudMap[$norm] = $row[0]; }
    
    // Construir WHERE
    $where = $whereBase;
    $types = ""; $params = [];
    if ($q !== '') { $where .= " AND (LOWER(deudor) LIKE CONCAT('%',?,'%') OR LOWER(prestamista) LIKE CONCAT('%',?,'%'))"; $types .= "ss"; $params[] = $qNorm; $params[] = $qNorm; }
    if ($fpNorm !== '') { $where .= " AND LOWER(TRIM(prestamista)) = ?"; $types .= "s"; $params[] = $fpNorm; }
    if ($fdNorm !== '') { $where .= " AND LOWER(TRIM(deudor)) = ?"; $types .= "s"; $params[] = $fdNorm; }
    if ($feNorm !== '') { $where .= " AND LOWER(TRIM(empresa)) = ?"; $types .= "s"; $params[] = $feNorm; }
    if ($fecha_desde !== '') { $where .= " AND fecha >= ?"; $types .= "s"; $params[] = $fecha_desde; }
    if ($fecha_hasta !== '') { $where .= " AND fecha <= ?"; $types .= "s"; $params[] = $fecha_hasta; }
    
    // Consulta principal
    $sql = "SELECT id, deudor, prestamista, monto, fecha, imagen, created_at, pagado, pagado_at, empresa,
                   comision_gestor_nombre, comision_gestor_porcentaje, comision_base_monto,
                   comision_origen_prestamista, comision_origen_porcentaje
            FROM prestamos WHERE $where ORDER BY pagado ASC, id DESC";
    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rs = $stmt->get_result();
?>
    <!-- FILTROS -->
    <div class="card">
        <form class="toolbar" method="get">
            <input type="hidden" name="view" value="cards">
            <input name="q" placeholder="🔎 Buscar" value="<?= h($q) ?>">
            
            <select name="fp" class="select2-filter">
                <option value="">Prestamista</option>
                <?php foreach($prestMap as $norm=>$label): ?>
                    <option value="<?= h($norm) ?>" <?= $fpNorm===$norm?'selected':'' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
            
            <select name="fe" class="select2-filter">
                <option value="">Empresa</option>
                <?php foreach($empMap as $norm=>$label): ?>
                    <option value="<?= h($norm) ?>" <?= $feNorm===$norm?'selected':'' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
            
            <select name="fd" class="select2-filter">
                <option value="">Deudor</option>
                <?php foreach($deudMap as $norm=>$label): ?>
                    <option value="<?= h($norm) ?>" <?= $fdNorm===$norm?'selected':'' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
            
            <input name="fecha_desde" type="date" value="<?= h($fecha_desde) ?>" placeholder="Desde">
            <input name="fecha_hasta" type="date" value="<?= h($fecha_hasta) ?>" placeholder="Hasta">
            
            <div style="display:flex; gap:5px; background:#f8f9fa; padding:5px; border-radius:8px;">
                <label><input type="radio" name="estado_pago" value="no_pagados" <?= $estado_pago==='no_pagados'?'checked':''?> onchange="this.form.submit()"> No pagados</label>
                <label><input type="radio" name="estado_pago" value="pagados" <?= $estado_pago==='pagados'?'checked':''?> onchange="this.form.submit()"> Pagados</label>
                <label><input type="radio" name="estado_pago" value="todos" <?= $estado_pago==='todos'?'checked':''?> onchange="this.form.submit()"> Todos</label>
            </div>
            
            <button class="btn" type="submit">Filtrar</button>
            <?php if ($q||$fpNorm||$fdNorm||$feNorm||$fecha_desde||$fecha_hasta||$estado_pago!='no_pagados'): ?>
                <a class="btn gray" href="?view=cards">Quitar filtro</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- PANEL PAGO INTELIGENTE -->
    <div class="card" style="background:#f0f9ff; border:1px solid #bae6fd;">
        <div class="title">💰 Pago Inteligente</div>
        <div class="row">
            <div class="field"><label>Monto a pagar</label><input type="number" id="montoPago" placeholder="Ej: 4000000"></div>
            <div class="field"><label>Orden</label>
                <select id="ordenPago">
                    <option value="antiguo">Más antiguos</option>
                    <option value="reciente">Más recientes</option>
                    <option value="mayor_interes">Mayor interés</option>
                </select>
            </div>
            <button class="btn" onclick="vistaPreviaPago()">🔍 Vista Previa</button>
        </div>
        <div class="subtitle"><span id="selectedCount">0</span> préstamos seleccionados</div>
    </div>

    <?php if ($rs->num_rows === 0): ?>
        <div class="card">(sin registros)</div>
    <?php else: ?>
        <!-- TARJETAS -->
        <form id="bulkForm" method="post">
            <div class="row">
                <div class="title">Selecciona tarjetas</div>
                <div style="display:flex; gap:8px">
                    <label><input id="chkAll" type="checkbox"> Todo</label>
                    <button type="button" class="btn gray small" id="btnToggleBulk">✏️ Editar</button>
                    <button type="submit" class="btn small" formaction="?action=bulk_mark_paid" onclick="return confirm('¿Marcar como pagados?')">✔ Pagados</button>
                    <button type="submit" class="btn gray small" formaction="?action=bulk_mark_unpaid" onclick="return confirm('¿Marcar como NO pagados?')">↩ NO pagados</button>
                    <span class="badge" id="selCount">0</span>
                </div>
            </div>
            
            <div class="grid-cards">
                <?php while($r = $rs->fetch_assoc()):
                    $calc = calcularTotalPrestamo(
                        $r['monto'], $r['fecha'], 
                        $r['comision_origen_porcentaje'],
                        $r['comision_gestor_porcentaje'] ?? 0,
                        $r['comision_base_monto']
                    );
                    $esComision = !empty($r['comision_gestor_nombre']);
                    $esPagado = (bool)($r['pagado'] ?? false);
                    $cardClass = $esComision ? 'card-comision' : ($esPagado ? 'card-pagado' : '');
                ?>
                    <div class="card <?= $cardClass ?>">
                        <div class="cardSel">
                            <?php if (!$esPagado): ?>
                                <input class="chkRow" type="checkbox" name="ids[]" value="<?= $r['id'] ?>" onchange="actualizarContador()">
                            <?php else: ?>
                                <span style="width:20px"></span>
                            <?php endif; ?>
                            <span class="subtitle">#<?= $r['id'] ?></span>
                            <?php if($esComision): ?><span class="chip comision-badge">💰 Comisión</span><?php endif; ?>
                            <?php if($esPagado): ?><span class="chip pagado-badge">✅ Pagado</span><?php endif; ?>
                        </div>
                        
                        <?php if($r['imagen']): ?>
                            <img class="thumb" src="uploads/<?= h($r['imagen']) ?>">
                        <?php endif; ?>
                        
                        <div><span class="title"><?= h($r['deudor']) ?></span> <span class="chip"><?= $r['fecha'] ?></span></div>
                        <div class="subtitle">Prestamista: <?= h($r['prestamista']) ?></div>
                        <?php if($r['empresa']): ?><div class="subtitle">Empresa: <?= h($r['empresa']) ?></div><?php endif; ?>
                        
                        <div class="pairs">
                            <div class="item"><div class="k">Monto</div><div class="v">$<?= money($r['monto']) ?></div></div>
                            <div class="item"><div class="k">Meses</div><div class="v"><?= $calc['meses'] ?></div></div>
                            <div class="item"><div class="k">Interés</div><div class="v">$<?= money($calc['interes_total']) ?></div></div>
                            <div class="item"><div class="k">Total</div><div class="v">$<?= money($calc['total']) ?></div></div>
                        </div>
                        
                        <div class="row">
                            <span class="subtitle"><?= $r['created_at'] ?></span>
                            <div>
                                <a class="btn gray small" href="?action=edit&id=<?= $r['id'] ?>">✏️</a>
                                <button class="btn red small" type="button" onclick="eliminar(<?= $r['id'] ?>)">🗑️</button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <!-- Panel bulk edit -->
            <div class="bulkpanel" id="bulkPanel">
                <div class="row">
                    <input name="new_deudor" placeholder="Nuevo deudor">
                    <input name="new_prestamista" placeholder="Nuevo prestamista">
                    <input name="new_monto" type="number" placeholder="Nuevo monto">
                    <input name="new_fecha" type="date" placeholder="Nueva fecha">
                </div>
                <div class="row">
                    <button class="btn" type="submit" formaction="?action=bulk_update">Aplicar</button>
                    <button class="btn gray" type="button" id="btnCloseBulk">Cerrar</button>
                </div>
            </div>
        </form>
    <?php endif; $conn->close(); ?>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
let ultimaVistaPrevia = null;

$(document).ready(function() {
    $('.select2-filter').select2({ width: '100%' });
});

// Contador selección
function actualizarContador() {
    document.getElementById('selectedCount').textContent = document.querySelectorAll('.chkRow:checked').length;
}

// Bulk panel
const chkAll = document.getElementById('chkAll');
if(chkAll) {
    chkAll.addEventListener('change', function() {
        document.querySelectorAll('.chkRow').forEach(c => c.checked = this.checked);
        actualizarContador();
    });
}

document.querySelectorAll('.chkRow').forEach(c => c.addEventListener('change', actualizarContador));

document.getElementById('btnToggleBulk')?.addEventListener('click', function() {
    if(document.querySelectorAll('.chkRow:checked').length === 0) {
        alert('Selecciona al menos una tarjeta');
        return;
    }
    document.getElementById('bulkPanel').style.display = 'block';
});

document.getElementById('btnCloseBulk')?.addEventListener('click', function() {
    document.getElementById('bulkPanel').style.display = 'none';
});

// Vista previa - VERSIÓN SIMPLIFICADA
function vistaPreviaPago() {
    const checks = document.querySelectorAll('.chkRow:checked');
    const ids = Array.from(checks).map(c => c.value);
    const monto = document.getElementById('montoPago').value;
    const orden = document.getElementById('ordenPago').value;
    
    if(ids.length === 0) { alert('Selecciona préstamos'); return; }
    if(!monto || monto <= 0) { alert('Monto inválido'); return; }
    
    document.getElementById('previewContent').innerHTML = '<p>Cargando...</p>';
    document.getElementById('modalPreview').style.display = 'block';
    
    // Enviar como JSON
    fetch('?action=vista_previa', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids: ids, monto_pago: monto, orden: orden })
    })
    .then(response => response.json())
    .then(data => {
        if(data.error) {
            document.getElementById('previewContent').innerHTML = '<p class="error">' + data.error + '</p>';
            return;
        }
        
        ultimaVistaPrevia = data;
        
        let html = `<div style="background:#e8f7ee; padding:12px; border-radius:8px; margin-bottom:16px;">
            <strong>💰 Monto:</strong> $${new Intl.NumberFormat('es-CO').format(data.monto_pago)}<br>
            <strong>💵 Procesado:</strong> $${new Intl.NumberFormat('es-CO').format(data.total_procesado)}<br>
            <strong>🔄 Restante:</strong> $${new Intl.NumberFormat('es-CO').format(data.restante)}
        </div>`;
        
        data.resultado.forEach(item => {
            let clase = 'preview-item';
            let badge = '';
            
            if(item.accion === 'pagado_completo') {
                clase += ' pagado';
                badge = '<span class="badge-pill success">✅ PAGADO</span>';
            } else if(item.accion === 'reestructurar') {
                clase += ' reestructurar';
                badge = '<span class="badge-pill warning">🔄 REESTRUCTURAR</span>';
            } else {
                badge = '<span class="badge-pill info">⏳ NO TOCADO</span>';
            }
            
            html += `<div class="${clase}">`;
            html += `<div style="display:flex; justify-content:space-between"><strong>#${item.id} - ${item.deudor}</strong> ${badge}</div>`;
            
            if(item.accion === 'pagado_completo') {
                html += `<div>Capital: $${new Intl.NumberFormat('es-CO').format(item.monto_original)}</div>`;
                html += `<div>Interés: $${new Intl.NumberFormat('es-CO').format(item.interes_pagado)}</div>`;
                html += `<div>Total: $${new Intl.NumberFormat('es-CO').format(item.total_pagado)}</div>`;
            } else if(item.accion === 'reestructurar') {
                html += `<div>Capital original: $${new Intl.NumberFormat('es-CO').format(item.monto_original)}</div>`;
                html += `<div>Interés pagado: $${new Intl.NumberFormat('es-CO').format(item.interes_pagado)}</div>`;
                html += `<div>Abono capital: $${new Intl.NumberFormat('es-CO').format(item.abono_capital)}</div>`;
                html += `<div style="background:#f59e0b20; padding:8px; border-radius:6px; margin-top:8px;">`;
                html += `<strong>🆕 NUEVO PRÉSTAMO:</strong> $${new Intl.NumberFormat('es-CO').format(item.nuevo_capital)} (hoy)`;
                html += `</div>`;
            } else {
                html += `<div>No se modificó</div>`;
            }
            
            html += `<div class="subtitle">${item.mensaje}</div>`;
            html += `</div>`;
        });
        
        document.getElementById('previewContent').innerHTML = html;
    })
    .catch(error => {
        document.getElementById('previewContent').innerHTML = '<p class="error">Error: ' + error.message + '</p>';
    });
}

// Confirmar pago
document.getElementById('btnConfirmarPago')?.addEventListener('click', function() {
    if(!ultimaVistaPrevia) { alert('No hay vista previa'); return; }
    if(!confirm('¿Confirmar el pago?')) return;
    
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
        if(data.error) alert('Error: ' + data.error);
        else { alert('✅ Pago procesado'); location.reload(); }
    })
    .catch(error => alert('Error: ' + error));
});

// Eliminar
function eliminar(id) {
    if(confirm('¿Eliminar #'+id+'?')) {
        const f = document.createElement('form');
        f.method = 'post';
        f.action = '?action=delete&id='+id;
        document.body.appendChild(f);
        f.submit();
    }
}

// Cerrar modal con ESC
document.addEventListener('keydown', e => {
    if(e.key === 'Escape') document.getElementById('modalPreview').style.display = 'none';
});
</script>

</body>
</html>