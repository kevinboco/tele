<?php
/*********************************************************
 * admin_prestamos.php — CRUD + Tarjetas + ABONOS MULTIPLES
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
    
    $ids = $_POST['ids'] ?? [];
    $monto_abono = (float)($_POST['monto_abono'] ?? 0);
    
    if (!is_array($ids) || empty($ids) || $monto_abono <= 0) {
        echo json_encode(['error' => 'Seleccione préstamos y monto válido']);
        exit;
    }
    
    $ids = array_map('intval', $ids);
    $conn = db();
    
    // Obtener todos los préstamos seleccionados (NO pagados)
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT id, deudor, prestamista, monto, fecha,
                   CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) END AS meses,
                   (monto * 
                    CASE 
                      WHEN fecha >= '2025-10-29' THEN COALESCE(comision_origen_porcentaje, 13)
                      ELSE COALESCE(comision_origen_porcentaje, 10)
                    END / 100 *
                    CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) END) AS interes_generado
            FROM prestamos 
            WHERE id IN ($placeholders) AND (pagado = 0 OR pagado IS NULL)
            ORDER BY fecha ASC, id ASC"; // Los más antiguos primero
    
    $st = $conn->prepare($sql);
    $types = str_repeat('i', count($ids));
    $st->bind_param($types, ...$ids);
    $st->execute();
    $res = $st->get_result();
    
    $prestamos = [];
    while ($row = $res->fetch_assoc()) {
        $prestamos[] = [
            'id' => $row['id'],
            'deudor' => $row['deudor'],
            'capital' => (float)$row['monto'],
            'interes' => (float)$row['interes_generado'],
            'meses' => (int)$row['meses'],
            'total' => (float)$row['monto'] + (float)$row['interes_generado']
        ];
    }
    $st->close();
    
    if (empty($prestamos)) {
        echo json_encode(['error' => 'No se encontraron préstamos válidos']);
        exit;
    }
    
    // DISTRIBUIR EL ABONO ENTRE LOS PRÉSTAMOS SELECCIONADOS
    $abono_restante = $monto_abono;
    $resultados = [];
    $nuevos_prestamos = [];
    $total_capital_pagado = 0;
    $total_interes_pagado = 0;
    $prestamos_pagados_completos = 0;
    
    foreach ($prestamos as $p) {
        if ($abono_restante <= 0) {
            // No queda más abono, este préstamo no se afecta
            $resultados[] = [
                'id' => $p['id'],
                'deudor' => $p['deudor'],
                'estado' => 'sin_abono',
                'capital_original' => $p['capital'],
                'interes_original' => $p['interes'],
                'mensaje' => 'Sin abono (fondos insuficientes)'
            ];
            continue;
        }
        
        $interes = $p['interes'];
        $capital = $p['capital'];
        
        if ($abono_restante >= $interes) {
            // Puede pagar al menos todo el interés
            $interes_pagado = $interes;
            $restante = $abono_restante - $interes;
            
            if ($restante >= $capital) {
                // PAGA COMPLETO
                $capital_pagado = $capital;
                $abono_restante -= ($interes + $capital);
                $total_interes_pagado += $interes;
                $total_capital_pagado += $capital;
                $prestamos_pagados_completos++;
                
                $resultados[] = [
                    'id' => $p['id'],
                    'deudor' => $p['deudor'],
                    'estado' => 'pagado_completo',
                    'interes_pagado' => $interes,
                    'capital_pagado' => $capital,
                    'total_pagado' => $interes + $capital,
                    'mensaje' => '✓ PAGADO COMPLETO'
                ];
            } else {
                // PAGA INTERES COMPLETO + PARTE DEL CAPITAL
                $capital_pagado = $restante;
                $nuevo_capital = $capital - $capital_pagado;
                $abono_restante = 0;
                $total_interes_pagado += $interes;
                $total_capital_pagado += $capital_pagado;
                
                // Este préstamo se reemplaza por uno nuevo
                $nuevos_prestamos[] = [
                    'id_original' => $p['id'],
                    'deudor' => $p['deudor'],
                    'nuevo_capital' => $nuevo_capital,
                    'interes_pagado' => $interes,
                    'capital_pagado' => $capital_pagado
                ];
                
                $resultados[] = [
                    'id' => $p['id'],
                    'deudor' => $p['deudor'],
                    'estado' => 'nuevo_prestamo',
                    'interes_pagado' => $interes,
                    'capital_pagado' => $capital_pagado,
                    'nuevo_capital' => $nuevo_capital,
                    'mensaje' => "🔄 NUEVO PRÉSTAMO por $" . money($nuevo_capital)
                ];
            }
        } else {
            // NO ALCANZA PARA PAGAR TODO EL INTERES
            $interes_pagado = $abono_restante;
            $interes_pendiente = $interes - $abono_restante;
            $abono_restante = 0;
            $total_interes_pagado += $interes_pagado;
            
            $resultados[] = [
                'id' => $p['id'],
                'deudor' => $p['deudor'],
                'estado' => 'interes_parcial',
                'interes_pagado' => $interes_pagado,
                'interes_pendiente' => $interes_pendiente,
                'capital' => $capital,
                'mensaje' => "⚠️ Interés pendiente: $" . money($interes_pendiente)
            ];
        }
    }
    
    $respuesta = [
        'success' => true,
        'monto_abono' => $monto_abono,
        'prestamos_seleccionados' => count($prestamos),
        'prestamos_procesados' => count($resultados),
        'prestamos_pagados_completos' => $prestamos_pagados_completos,
        'nuevos_prestamos_requeridos' => count($nuevos_prestamos),
        'total_interes_pagado' => $total_interes_pagado,
        'total_capital_pagado' => $total_capital_pagado,
        'abono_restante' => $abono_restante,
        'detalles' => $resultados,
        'nuevos_prestamos' => $nuevos_prestamos
    ];
    
    echo json_encode($respuesta);
    $conn->close();
    exit;
}

// ===== PROCESAR ABONO REAL (CREAR NUEVOS PRÉSTAMOS) =====
if ($action==='procesar_abono' && $_SERVER['REQUEST_METHOD']==='POST'){
    $ids = $_POST['ids'] ?? [];
    $monto_abono = (float)($_POST['monto_abono'] ?? 0);
    $confirmacion = $_POST['confirmacion'] ?? '';
    
    if (!is_array($ids) || empty($ids) || $monto_abono <= 0 || $confirmacion !== 'si') {
        go(BASE_URL . '?view=cards&error=datos_invalidos');
    }
    
    $ids = array_map('intval', $ids);
    $conn = db();
    
    // Obtener todos los préstamos seleccionados
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT id, deudor, prestamista, monto, fecha, empresa,
                   comision_gestor_nombre, comision_gestor_porcentaje,
                   comision_base_monto, comision_origen_prestamista, comision_origen_porcentaje,
                   CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) END AS meses,
                   (monto * 
                    CASE 
                      WHEN fecha >= '2025-10-29' THEN COALESCE(comision_origen_porcentaje, 13)
                      ELSE COALESCE(comision_origen_porcentaje, 10)
                    END / 100 *
                    CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) END) AS interes_generado
            FROM prestamos 
            WHERE id IN ($placeholders) AND (pagado = 0 OR pagado IS NULL)
            ORDER BY fecha ASC, id ASC";
    
    $st = $conn->prepare($sql);
    $types = str_repeat('i', count($ids));
    $st->bind_param($types, ...$ids);
    $st->execute();
    $res = $st->get_result();
    
    $prestamos = [];
    while ($row = $res->fetch_assoc()) {
        $prestamos[] = $row;
    }
    $st->close();
    
    if (empty($prestamos)) {
        $conn->close();
        go(BASE_URL . '?view=cards&error=prestamos_no_encontrados');
    }
    
    // DISTRIBUIR EL ABONO
    $abono_restante = $monto_abono;
    $nuevos_ids = [];
    $mensajes = [];
    
    foreach ($prestamos as $p) {
        if ($abono_restante <= 0) break;
        
        $interes = (float)$p['interes_generado'];
        $capital = (float)$p['monto'];
        $id_original = $p['id'];
        
        if ($abono_restante >= $interes) {
            $interes_pagado = $interes;
            $restante = $abono_restante - $interes;
            
            if ($restante >= $capital) {
                // PAGA COMPLETO
                $upd = $conn->prepare("UPDATE prestamos SET pagado = 1, pagado_at = NOW() WHERE id = ?");
                $upd->bind_param("i", $id_original);
                $upd->execute();
                $upd->close();
                
                $abono_restante -= ($interes + $capital);
                $mensajes[] = "Préstamo #{$id_original} PAGADO COMPLETO";
                
            } else {
                // PAGA INTERES + PARTE DEL CAPITAL -> NUEVO PRÉSTAMO
                $capital_pagado = $restante;
                $nuevo_capital = $capital - $capital_pagado;
                
                // 1. Marcar original como pagado
                $upd = $conn->prepare("UPDATE prestamos SET pagado = 1, pagado_at = NOW() WHERE id = ?");
                $upd->bind_param("i", $id_original);
                $upd->execute();
                $upd->close();
                
                // 2. Crear nuevo préstamo
                $sql_new = "INSERT INTO prestamos 
                            (deudor, prestamista, monto, fecha, created_at, empresa,
                             comision_gestor_nombre, comision_gestor_porcentaje,
                             comision_base_monto, comision_origen_prestamista, comision_origen_porcentaje)
                            VALUES (?, ?, ?, CURDATE(), NOW(), ?, ?, ?, ?, ?, ?)";
                
                $st_new = $conn->prepare($sql_new);
                $st_new->bind_param("ssdssddddss", 
                    $p['deudor'], $p['prestamista'], $nuevo_capital,
                    $p['empresa'], $p['comision_gestor_nombre'], $p['comision_gestor_porcentaje'],
                    $p['comision_base_monto'], $p['comision_origen_prestamista'], $p['comision_origen_porcentaje'],
                    $p['comision_gestor_nombre'] // Ajustar según tu estructura
                );
                
                // Versión simplificada si hay problemas:
                // $st_new = $conn->prepare("INSERT INTO prestamos (deudor, prestamista, monto, fecha, created_at) VALUES (?, ?, ?, CURDATE(), NOW())");
                // $st_new->bind_param("ssd", $p['deudor'], $p['prestamista'], $nuevo_capital);
                
                $st_new->execute();
                $nuevo_id = $st_new->insert_id;
                $st_new->close();
                
                $nuevos_ids[] = $nuevo_id;
                $abono_restante = 0;
                $mensajes[] = "Préstamo #{$id_original} → NUEVO #{$nuevo_id} por $" . money($nuevo_capital);
            }
        } else {
            // NO ALCANZA PARA INTERES
            $interes_pagado = $abono_restante;
            
            // Opcional: guardar abono parcial en algún campo
            $upd = $conn->prepare("UPDATE prestamos SET ultimo_abono = ?, ultimo_abono_fecha = CURDATE() WHERE id = ?");
            $upd->bind_param("di", $abono_restante, $id_original);
            $upd->execute();
            $upd->close();
            
            $abono_restante = 0;
            $mensajes[] = "Préstamo #{$id_original}: Abono parcial a intereses $" . money($interes_pagado);
        }
    }
    
    $conn->close();
    
    // Redirigir con mensajes
    $msg = urlencode(implode(" | ", $mensajes));
    $url = BASE_URL . "?view=cards&msg_abono={$msg}";
    if (!empty($nuevos_ids)) {
        $url .= "&nuevos=" . implode(',', $nuevos_ids);
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
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
 :root{ --bg:#f6f7fb; --fg:#222; --card:#fff; --muted:#6b7280; --primary:#0b5ed7; --gray:#6c757d; --red:#dc3545; --chip:#eef2ff; --success:#059669; }
 *{box-sizing:border-box}
 body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:22px;background:var(--bg);color:var(--fg)}
 a{text-decoration:none}
 .btn{display:inline-flex;align-items:center;gap:8px;padding:9px 12px;border-radius:12px;background:var(--primary);color:#fff;font-weight:600;border:0;cursor:pointer}
 .btn.gray{background:var(--gray)} .btn.red{background:var(--red)} .btn.small{padding:7px 10px;border-radius:10px}
 .btn.success{background:var(--success)}
 .tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
 .tabs a{background:#e5e7eb;color:#111;padding:8px 12px;border-radius:10px;font-weight:700}
 .tabs a.active{background:var(--primary);color:#fff}
 .toolbar{display:flex;gap:10px;align-items:center;margin-bottom:14px;flex-wrap:wrap}
 .msg{background:#e8f7ee;color:#196a3b;padding:8px 12px;border-radius:10px;display:inline-block}
 .error{background:#fdecec;color:#b02a37;padding:8px 12px;border-radius:10px;display:inline-block}
 .card{background:var(--card);border-radius:16px;box-shadow:0 6px 20px rgba(0,0,0,.06);padding:16px;transition:all 0.2s}
 .card:hover{box-shadow:0 10px 30px rgba(0,0,0,.1)}
 .card.selected{outline:3px solid var(--primary);background:#f0f7ff}
 .subtitle{font-size:13px;color:var(--muted)}
 .grid-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px}
 .field{display:flex;flex-direction:column;gap:6px}
 input,select{padding:11px;border:1px solid #e5e7eb;border-radius:12px;background:#fff}
 .thumb{width:100%;max-height:180px;object-fit:cover;border-radius:12px;border:1px solid #eee}
 .pairs{display:grid;grid-template-columns:1fr 1fr;gap:10px}
 .pairs .item{background:#fafbff;border:1px solid #eef2ff;border-radius:12px;padding:10px}
 .pairs .k{font-size:12px;color:var(--muted)} .pairs .v{font-size:16px;font-weight:700}
 .row{display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap}
 .title{font-size:18px;font-weight:800}
 .chip{display:inline-block;background:var(--chip);padding:4px 8px;border-radius:999px;font-size:12px;font-weight:600}
 
 /* Estilos para abonos */
 .abono-card { background: linear-gradient(145deg, #f0f9ff, #e6f2ff); border-left: 4px solid var(--primary); margin-bottom:20px; }
 .vista-previa-panel { background: white; border-radius: 16px; padding: 20px; margin-top: 15px; border: 2px dashed var(--primary); }
 .resumen-grid{display: grid;grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));gap: 12px;margin-top: 12px;}
 .resumen-item{background: #f8fafc;border-radius: 12px;padding: 15px;text-align: center;border:1px solid #e2e8f0;}
 .resumen-valor{font-size: 20px;font-weight: 800;color: var(--primary);}
 .resumen-label{font-size: 12px;color: #64748b;margin-top: 5px;}
 .highlight-pagado { border: 3px solid var(--success) !important; box-shadow: 0 0 20px var(--success) !important; }
 .highlight-nuevo { border: 3px solid var(--primary) !important; box-shadow: 0 0 20px var(--primary) !important; }
 .badge-abono { background: var(--success); color: white; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
 .detalle-prestamo { background: #f1f5f9; border-radius: 8px; padding: 8px; margin-bottom: 5px; font-size: 13px; }
 .text-success { color: var(--success); font-weight:600; }
 .text-primary { color: var(--primary); font-weight:600; }
 .text-warning { color: #b45309; font-weight:600; }
 
 /* Bulk actions */
 .bulkbar{display:flex;gap:10px;align-items:center;margin:8px 0;flex-wrap:wrap}
 .badge{background:#111;color:#fff;border-radius:999px;padding:2px 8px;font-size:12px;font-weight:700}
 .cardSel{display:flex;align-items:center;gap:8px;margin-bottom:6px}
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
        'creado'=>'✅ Registro creado correctamente.',
        'editado'=>'✅ Cambios guardados.',
        'eliminado'=>'✅ Registro eliminado.',
        default=>'Operación realizada.'
  } ?></div>
<?php endif; ?>

<?php if (!empty($_GET['msg_abono'])): ?>
  <div class="msg" style="margin-bottom:14px; background:#d1fae5; color:#065f46;">
    ✅ <?= h(urldecode($_GET['msg_abono'])) ?>
  </div>
<?php endif; ?>

<?php if (!empty($_GET['error'])): ?>
  <div class="error" style="margin-bottom:14px">
    ❌ <?= match($_GET['error']){
        'datos_invalidos' => 'Datos inválidos para el abono',
        'prestamos_no_encontrados' => 'Préstamos no encontrados o ya pagados',
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
  <!-- Formulario de crear/editar (igual que antes) -->
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
  if ($estado_pago === 'no_pagados') $whereBase = "pagado = 0 OR pagado IS NULL";
  elseif ($estado_pago === 'pagados') $whereBase = "pagado = 1";

  // Obtener listas para filtros
  $prestMap = [];
  $resPL = $conn->query("SELECT prestamista FROM prestamos WHERE 1=1 GROUP BY prestamista");
  while($rowPL=$resPL->fetch_row()){
    $norm = mbnorm($rowPL[0]);
    if ($norm==='') continue;
    $prestMap[$norm] = $rowPL[0];
  }

  $empMap = [];
  $resEL = $conn->query("SELECT empresa FROM prestamos WHERE empresa IS NOT NULL AND empresa != '' GROUP BY empresa");
  while($rowEL=$resEL->fetch_row()){
    $val = $rowEL[0];
    $norm = mbnorm($val);
    $empMap[$norm] = $val;
  }

  $deudMap = [];
  $resDeud = $conn->query("SELECT DISTINCT deudor FROM prestamos ORDER BY deudor");
  while($rowDL = $resDeud->fetch_row()) {
    $norm = mbnorm($rowDL[0]);
    $deudMap[$norm] = $rowDL[0];
  }
?>

<!-- ===== SISTEMA DE ABONOS CON SELECCIÓN MÚLTIPLE ===== -->
<div class="card abono-card">
  <div class="row" style="margin-bottom:15px;">
    <div class="title">💰 Abono a Préstamos Seleccionados</div>
    <span class="badge-abono" id="selectedCountDisplay">0 seleccionados</span>
  </div>
  
  <div class="row" style="gap:15px; flex-wrap:wrap; align-items:flex-end;">
    <div class="field" style="min-width:300px; flex:3;">
      <label>💵 Monto total a abonar</label>
      <input type="number" id="montoAbonoGlobal" step="1000" min="1" 
             placeholder="Ej: 10000000" style="font-weight:600; font-size:16px;">
    </div>
    
    <div class="field" style="flex:0;">
      <button type="button" class="btn" id="btnVistaPreviaGlobal" style="padding:12px 25px;">
        👁️ Vista Previa del Abono
      </button>
    </div>
  </div>
  
  <!-- PANEL DE VISTA PREVIA -->
  <div id="vistaPreviaGlobalPanel" style="display:none; margin-top:20px;">
    <div class="vista-previa-panel">
      <div class="row" style="margin-bottom:15px;">
        <h3 style="margin:0; color:var(--primary);">📊 Vista Previa del Abono</h3>
        <span class="chip" id="vistaResumenInfo"></span>
      </div>
      
      <div id="vistaPreviaDetalles">
        <!-- Se llena con JS -->
        <div class="resumen-item">Cargando...</div>
      </div>
      
      <div class="row" style="margin-top:20px; justify-content:flex-end;">
        <button type="button" class="btn gray" id="btnCancelarVistaGlobal">Cancelar</button>
        <button type="button" class="btn success" id="btnConfirmarAbonoGlobal" style="margin-left:10px; display:none;">
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
    
    <select name="fp" style="min-width:150px; padding:11px;">
      <option value="">Prestamista</option>
      <?php foreach($prestMap as $norm=>$label): ?>
        <option value="<?= h($norm) ?>" <?= $fpNorm===$norm?'selected':'' ?>><?= h(mbtitle($label)) ?></option>
      <?php endforeach; ?>
    </select>
    
    <select name="fe" style="min-width:150px; padding:11px;">
      <option value="">Empresa</option>
      <?php foreach($empMap as $norm=>$label): ?>
        <option value="<?= h($norm) ?>" <?= $feNorm===$norm?'selected':'' ?>><?= h(mbtitle($label)) ?></option>
      <?php endforeach; ?>
    </select>
    
    <select name="fd" style="min-width:150px; padding:11px;">
      <option value="">Deudor</option>
      <?php foreach($deudMap as $norm=>$label): ?>
        <option value="<?= h($norm) ?>" <?= $fdNorm===$norm?'selected':'' ?>><?= h(mbtitle($label)) ?></option>
      <?php endforeach; ?>
    </select>
    
    <input name="fecha_desde" type="date" value="<?= h($fecha_desde) ?>" style="min-width:130px;">
    <input name="fecha_hasta" type="date" value="<?= h($fecha_hasta) ?>" style="min-width:130px;">
    
    <select name="estado_pago" style="min-width:120px; padding:11px;">
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

<!-- FORM para selección múltiple -->
<form id="bulkForm">
  <div class="bulkbar">
    <label style="display:flex;gap:8px;align-items:center">
      <input type="checkbox" id="chkAll"> Seleccionar todo (página)
    </label>
    <span class="badge" id="selCount">0 seleccionados</span>
  </div>

  <!-- TARJETAS -->
  <?php if ($rs->num_rows === 0): ?>
    <div class="card"><span class="subtitle">(sin registros)</span></div>
  <?php else: ?>
    <div class="grid-cards">
      <?php while($r=$rs->fetch_assoc()): 
        $pagado = (bool)($r['pagado'] ?? false);
        $cardClass = $pagado ? 'card-pagado' : '';
        $total = $r['monto'] + $r['interes_generado'];
      ?>
        <div class="card <?= $cardClass ?>" data-id="<?= $r['id'] ?>" data-deudor="<?= h($r['deudor']) ?>" data-monto="<?= $r['monto'] ?>" data-interes="<?= $r['interes_generado'] ?>">
          <div class="cardSel">
            <input class="chkRow" type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>" 
                   <?= $pagado ? 'disabled' : '' ?>>
            <div class="subtitle">#<?= h($r['id']) ?></div>
            <?php if($pagado): ?>
              <span class="chip" style="background:#10b981; color:white; margin-left:auto;">✅ Pagado</span>
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
              <div class="k">Capital</div>
              <div class="v">$ <?= money($r['monto']) ?></div>
            </div>
            <div class="item">
              <div class="k">Interés</div>
              <div class="v">$ <?= money($r['interes_generado']) ?></div>
            </div>
            <div class="item">
              <div class="k">Total</div>
              <div class="v">$ <?= money($total) ?></div>
            </div>
            <div class="item">
              <div class="k">Meses</div>
              <div class="v"><?= h($r['meses']) ?></div>
            </div>
          </div>

          <div class="row" style="margin-top:12px">
            <div class="subtitle">Creado: <?= h($r['created_at']) ?></div>
            <div>
              <a class="btn gray small" href="?action=edit&id=<?= $r['id'] ?>&view=cards">✏️</a>
              <button class="btn red small" type="button" onclick="submitDelete(<?= $r['id'] ?>)">🗑️</button>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  <?php endif; ?>
</form>

<?php
  $st->close();
  $conn->close();
endif; 
?>

<!-- SCRIPTS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
// Variables globales
let datosVistaPreviaGlobal = null;

// Normalizar texto
function mbnorm(s) {
    return s ? s.toLowerCase().trim() : '';
}

// Actualizar contador de seleccionados
function updateSelectedCount() {
    const checked = document.querySelectorAll('.chkRow:checked').length;
    document.getElementById('selCount').textContent = checked + ' seleccionados';
    document.getElementById('selectedCountDisplay').textContent = checked + ' seleccionados';
    
    // Resaltar tarjetas seleccionadas
    document.querySelectorAll('.card').forEach(c => c.classList.remove('selected'));
    document.querySelectorAll('.chkRow:checked').forEach(cb => {
        cb.closest('.card').classList.add('selected');
    });
}

// Selector todo
document.getElementById('chkAll')?.addEventListener('change', function(e) {
    document.querySelectorAll('.chkRow:not([disabled])').forEach(cb => {
        cb.checked = e.target.checked;
    });
    updateSelectedCount();
});

// Eventos en checkboxes individuales
document.querySelectorAll('.chkRow').forEach(cb => {
    cb.addEventListener('change', updateSelectedCount);
});

// Vista Previa Global
document.getElementById('btnVistaPreviaGlobal').addEventListener('click', function() {
    const selected = Array.from(document.querySelectorAll('.chkRow:checked')).map(cb => cb.value);
    const monto = document.getElementById('montoAbonoGlobal').value;
    
    if (selected.length === 0) {
        alert('Seleccione al menos un préstamo');
        return;
    }
    
    if (!monto || monto <= 0) {
        alert('Ingrese un monto válido');
        return;
    }
    
    // Mostrar panel de carga
    document.getElementById('vistaPreviaGlobalPanel').style.display = 'block';
    document.getElementById('vistaPreviaDetalles').innerHTML = '<div class="resumen-item">Calculando distribución...</div>';
    document.getElementById('btnConfirmarAbonoGlobal').hide();
    
    // Llamada AJAX
    fetch('?action=calcular_vista_previa', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ids=' + encodeURIComponent(JSON.stringify(selected)) + '&monto_abono=' + encodeURIComponent(monto)
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            document.getElementById('vistaPreviaDetalles').innerHTML = `<div class="error">${data.error}</div>`;
            return;
        }
        
        datosVistaPreviaGlobal = data;
        
        // Construir HTML de resultados
        let html = `
            <div class="resumen-grid">
                <div class="resumen-item">
                    <div class="resumen-valor">$${data.monto_abono.toLocaleString('es-CO')}</div>
                    <div class="resumen-label">Monto Abonado</div>
                </div>
                <div class="resumen-item">
                    <div class="resumen-valor">$${data.total_interes_pagado.toLocaleString('es-CO')}</div>
                    <div class="resumen-label">Total Interés Pagado</div>
                </div>
                <div class="resumen-item">
                    <div class="resumen-valor">$${data.total_capital_pagado.toLocaleString('es-CO')}</div>
                    <div class="resumen-label">Total Capital Pagado</div>
                </div>
                <div class="resumen-item">
                    <div class="resumen-valor">${data.prestamos_pagados_completos}</div>
                    <div class="resumen-label">Préstamos Pagados</div>
                </div>
            </div>
            <div style="margin-top:15px;">
                <h4>Detalle por préstamo:</h4>
        `;
        
        data.detalles.forEach(d => {
            let colorClass = '';
            if (d.estado === 'pagado_completo') colorClass = 'text-success';
            else if (d.estado === 'nuevo_prestamo') colorClass = 'text-primary';
            else if (d.estado === 'interes_parcial') colorClass = 'text-warning';
            
            html += `
                <div class="detalle-prestamo ${colorClass}">
                    <strong>#${d.id} - ${d.deudor}</strong><br>
                    ${d.mensaje}
                </div>
            `;
        });
        
        if (data.nuevos_prestamos_requeridos > 0) {
            html += `<p class="text-primary" style="margin-top:10px;">🔄 Se crearán ${data.nuevos_prestamos_requeridos} nuevo(s) préstamo(s)</p>`;
        }
        
        if (data.abono_restante > 0) {
            html += `<p class="text-warning">💰 Sobrante: $${data.abono_restante.toLocaleString('es-CO')}</p>`;
        }
        
        document.getElementById('vistaPreviaDetalles').innerHTML = html;
        document.getElementById('vistaResumenInfo').textContent = `${data.prestamos_seleccionados} préstamos seleccionados`;
        document.getElementById('btnConfirmarAbonoGlobal').show();
    })
    .catch(error => {
        document.getElementById('vistaPreviaDetalles').innerHTML = `<div class="error">Error: ${error}</div>`;
    });
});

// Confirmar Abono Global
document.getElementById('btnConfirmarAbonoGlobal').addEventListener('click', function() {
    if (!datosVistaPreviaGlobal) return;
    
    const selected = Array.from(document.querySelectorAll('.chkRow:checked')).map(cb => cb.value);
    const monto = document.getElementById('montoAbonoGlobal').value;
    
    if (!confirm('¿Confirmar el abono de $' + Number(monto).toLocaleString('es-CO') + ' a ' + selected.length + ' préstamos?')) {
        return;
    }
    
    // Crear formulario y enviar
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '?action=procesar_abono';
    
    selected.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = id;
        form.appendChild(input);
    });
    
    const inputMonto = document.createElement('input');
    inputMonto.type = 'hidden';
    inputMonto.name = 'monto_abono';
    inputMonto.value = monto;
    form.appendChild(inputMonto);
    
    const inputConf = document.createElement('input');
    inputConf.type = 'hidden';
    inputConf.name = 'confirmacion';
    inputConf.value = 'si';
    form.appendChild(inputConf);
    
    document.body.appendChild(form);
    form.submit();
});

// Cancelar Vista
document.getElementById('btnCancelarVistaGlobal').addEventListener('click', function() {
    document.getElementById('vistaPreviaGlobalPanel').style.display = 'none';
    document.getElementById('btnConfirmarAbonoGlobal').hide();
    datosVistaPreviaGlobal = null;
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

// Resaltar préstamos si vienen de un abono
<?php if (isset($_GET['nuevos'])): 
    $nuevos = explode(',', $_GET['nuevos']);
    foreach($nuevos as $nuevo): ?>
        document.querySelectorAll(`.card[data-id="<?= (int)$nuevo ?>"]`).forEach(el => {
            el.classList.add('highlight-nuevo');
            setTimeout(() => el.scrollIntoView({behavior:'smooth', block:'center'}), 500);
        });
<?php endforeach; endif; ?>

// Inicializar contador
updateSelectedCount();
</script>

<style>
.card-pagado { opacity: 0.7; border-left: 4px solid #10b981; background: #f0fdf4; }
.card-pagado .chkRow { display: none; } /* Ocultar checkbox en préstamos pagados */
.highlight-nuevo { border: 3px solid #0b5ed7 !important; box-shadow: 0 0 20px #0b5ed7; animation: pulse 1.5s infinite; }
@keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(11, 94, 215, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(11, 94, 215, 0); } 100% { box-shadow: 0 0 0 0 rgba(11, 94, 215, 0); } }
</style>

</body></html>