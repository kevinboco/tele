<?php
/*********************************************************
 * admin_prestamos.php — CRUD + Tarjetas + Descarga Word
 * - Selector de grupos (fechas de pago, sin fecha, no pagados)
 * - Descarga en Word formato TARJETA con imágenes
 * - Cálculo de meses desde fecha hasta fecha de pago
 *********************************************************/
include("nav.php");

// ======= CONFIG =======
define('DB_HOST', 'mysql.hostinger.com');
define('DB_USER', 'u648222299_keboco5');
define('DB_PASS', 'Bucaramanga3011');
define('DB_NAME', 'u648222299_viajes');
const UPLOAD_DIR = __DIR__ . '/uploads/';
const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;
const DEFAULT_OWNER_CHAT_ID = 6133806918;

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

// ===== FUNCIÓN PARA CALCULAR MESES =====
function calcularMesesPrestamo($fecha_inicio, $fecha_fin = null) {
    $inicio = new DateTime($fecha_inicio);
    
    if ($fecha_fin) {
        $fin = new DateTime($fecha_fin);
    } else {
        $fin = new DateTime('now');
    }
    
    $diff = $inicio->diff($fin);
    $meses = ($diff->y * 12) + $diff->m + 1;
    
    return $meses;
}

$action = $_GET['action'] ?? 'list';
$view   = 'cards';
$id = (int)($_GET['id'] ?? 0);

// Modo de cálculo especial
$modo_especial = isset($_GET['modo_especial']) ? (int)$_GET['modo_especial'] : (isset($_POST['modo_especial']) ? (int)$_POST['modo_especial'] : 0);

// ===== PROCESAR NUEVO DEUDOR =====
if (isset($_POST['nuevo_deudor_nombre']) && trim($_POST['nuevo_deudor_nombre']) !== '') {
  $nombre = trim($_POST['nuevo_deudor_nombre']);
  $conn = db();
  $stmt = $conn->prepare("INSERT INTO deudores_admin (owner_chat_id, nombre) VALUES (?, ?)");
  $stmt->bind_param("is", DEFAULT_OWNER_CHAT_ID, $nombre);
  $stmt->execute();
  $stmt->close();
  $conn->close();
  go('?action=new&view=cards&modo_especial='.$modo_especial);
}

// ===== PROCESAR NUEVO PRESTAMISTA =====
if (isset($_POST['nuevo_prestamista_nombre']) && trim($_POST['nuevo_prestamista_nombre']) !== '') {
  $nombre = trim($_POST['nuevo_prestamista_nombre']);
  $conn = db();
  $stmt = $conn->prepare("INSERT INTO prestamistas_admin (owner_chat_id, nombre) VALUES (?, ?)");
  $stmt->bind_param("is", DEFAULT_OWNER_CHAT_ID, $nombre);
  $stmt->execute();
  $stmt->close();
  $conn->close();
  go('?action=new&view=cards&modo_especial='.$modo_especial);
}

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

// ============================================================
// ===== DESCARGA DE WORD CON TARJETAS =====
// ============================================================
if (isset($_GET['descargar_word']) && $_GET['descargar_word'] == 1) {
    $grupo = $_GET['grupo'] ?? '';
    
    if (empty($grupo)) {
        die('Error: Selecciona un grupo válido');
    }
    
    $conn = db();
    
    // Decodificar el grupo
    $tipo = '';
    $fecha = null;
    
    if (strpos($grupo, 'pagado_fecha_') === 0) {
        $tipo = 'pagado_fecha';
        $fecha = str_replace('pagado_fecha_', '', $grupo);
    } elseif ($grupo == 'pagado_sin_fecha') {
        $tipo = 'pagado_sin_fecha';
    } elseif ($grupo == 'no_pagados') {
        $tipo = 'no_pagados';
    } else {
        die('Error: Grupo no válido');
    }
    
    // Construir consulta
    if ($tipo == 'pagado_fecha') {
        $sql = "SELECT * FROM prestamos WHERE pagado = 1 AND DATE(pagado_at) = ? ORDER BY deudor";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $fecha);
        $stmt->execute();
        $result = $stmt->get_result();
        $titulo = "PAGADOS - " . date('d/m/Y', strtotime($fecha));
        $fecha_texto = date('d/m/Y', strtotime($fecha));
    } elseif ($tipo == 'pagado_sin_fecha') {
        $sql = "SELECT * FROM prestamos WHERE pagado = 1 AND pagado_at IS NULL ORDER BY deudor";
        $result = $conn->query($sql);
        $titulo = "PAGADOS SIN FECHA";
        $fecha_texto = "SIN FECHA DE PAGO";
    } else { // no_pagados
        $sql = "SELECT * FROM prestamos WHERE pagado = 0 ORDER BY deudor";
        $result = $conn->query($sql);
        $titulo = "NO PAGADOS";
        $fecha_texto = "NO PAGADOS";
    }
    
    $prestamos = [];
    $total_capital = 0;
    $total_interes = 0;
    $total_general = 0;
    
    while ($row = $result->fetch_assoc()) {
        // ===== CALCULAR MESES =====
        if (!empty($row['pagado_at'])) {
            // Con fecha de pago: calcular desde fecha hasta pagado_at
            $meses = calcularMesesPrestamo($row['fecha'], $row['pagado_at']);
        } else {
            // Sin fecha de pago: calcular desde fecha hasta hoy
            $meses = calcularMesesPrestamo($row['fecha'], null);
        }
        
        // ===== CALCULAR INTERÉS =====
        $tasa = (strtotime($row['fecha']) >= strtotime('2025-10-29')) ? 13 : 10;
        $interes = ($row['monto'] * $tasa / 100) * $meses;
        $total = $row['monto'] + $interes;
        
        $row['meses'] = $meses;
        $row['interes'] = $interes;
        $row['total'] = $total;
        $row['tasa'] = $tasa;
        
        $prestamos[] = $row;
        $total_capital += $row['monto'];
        $total_interes += $interes;
        $total_general += $total;
    }
    
    $conn->close();
    
    // ===== GENERAR WORD CON PHPWord =====
    require_once 'vendor/autoload.php';
    
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    
    // Estilos
    $phpWord->addTitleStyle(1, ['bold' => true, 'size' => 20, 'color' => '1a3c6e']);
    $phpWord->addTitleStyle(2, ['bold' => true, 'size' => 14, 'color' => '1a3c6e']);
    
    $section = $phpWord->addSection();
    
    // ===== ENCABEZADO =====
    $header = $section->addHeader();
    $header->addText('ASOCIACIÓN DE TRANSPORTISTAS ZONA NORTE', ['bold' => true, 'size' => 16, 'color' => '1a3c6e']);
    $header->addText('REPORTE DE PRÉSTAMOS', ['bold' => true, 'size' => 14]);
    $header->addTextBreak(1);
    
    // ===== TÍTULO PRINCIPAL =====
    $section->addTitle('📋 ' . $titulo, 1);
    $section->addText("Fecha de referencia: " . $fecha_texto);
    $section->addText("Total de préstamos: " . count($prestamos));
    $section->addText("Generado: " . date('d/m/Y H:i:s'));
    $section->addTextBreak(1);
    
    // ===== TARJETAS =====
    $contador = 1;
    foreach ($prestamos as $p) {
        // Línea separadora
        $section->addText('─' . str_repeat('─', 70) . '─', ['size' => 8, 'color' => 'CCCCCC']);
        $section->addTextBreak(0.5);
        
        // Título de la tarjeta
        $section->addText(
            '📌 PRÉSTAMO #' . $p['id'] . ' (' . $contador . '/' . count($prestamos) . ')',
            ['bold' => true, 'size' => 14, 'color' => '1a3c6e'],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
        
        $section->addTextBreak(0.5);
        
        // ===== IMAGEN =====
        if (!empty($p['imagen'])) {
            $imagePath = UPLOAD_DIR . $p['imagen'];
            if (file_exists($imagePath)) {
                try {
                    $section->addImage($imagePath, [
                        'width' => 400,
                        'height' => 300,
                        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
                        'borderSize' => 4,
                        'borderColor' => 'DDDDDD'
                    ]);
                } catch (Exception $e) {
                    $section->addText('[Imagen no disponible]', ['color' => '999999']);
                }
            } else {
                $section->addText('📷 Imagen no encontrada', ['color' => '999999']);
            }
        } else {
            $section->addText('📷 Sin imagen', ['color' => '999999'], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        }
        
        $section->addTextBreak(0.5);
        
        // ===== DATOS DEL PRÉSTAMO EN TARJETA =====
        // Usar tabla de 2 columnas para mejor presentación
        $table = $section->addTable([
            'borderSize' => 0,
            'cellMargin' => 60,
        ]);
        
        // Fila 1: Deudor
        $table->addRow();
        $table->addCell(2500, ['valign' => 'center'])->addText('👤 Deudor:', ['bold' => true]);
        $table->addCell(5000, ['valign' => 'center'])->addText($p['deudor']);
        
        // Fila 2: Prestamista
        $table->addRow();
        $table->addCell(2500)->addText('🏦 Prestamista:', ['bold' => true]);
        $table->addCell(5000)->addText($p['prestamista']);
        
        // Fila 3: Monto
        $table->addRow();
        $table->addCell(2500)->addText('💰 Monto:', ['bold' => true]);
        $table->addCell(5000)->addText('$ ' . number_format($p['monto'], 0, ',', '.'));
        
        // Fila 4: Fecha del préstamo
        $table->addRow();
        $table->addCell(2500)->addText('📅 Fecha Préstamo:', ['bold' => true]);
        $table->addCell(5000)->addText(date('d/m/Y', strtotime($p['fecha'])));
        
        // Fila 5: Fecha de pago (si existe)
        if (!empty($p['pagado_at'])) {
            $table->addRow();
            $table->addCell(2500)->addText('✅ Fecha Pago:', ['bold' => true]);
            $table->addCell(5000)->addText(date('d/m/Y H:i', strtotime($p['pagado_at'])));
        } else {
            $table->addRow();
            $table->addCell(2500)->addText('⏳ Estado:', ['bold' => true]);
            $table->addCell(5000)->addText('PENDIENTE / SIN FECHA DE PAGO', ['color' => 'CC0000', 'bold' => true]);
        }
        
        // Fila 6: Meses
        $table->addRow();
        $table->addCell(2500)->addText('📊 Meses:', ['bold' => true]);
        $table->addCell(5000)->addText($p['meses'] . ' mes(es)');
        
        // Fila 7: Tasa de interés
        $table->addRow();
        $table->addCell(2500)->addText('📈 Tasa:', ['bold' => true]);
        $table->addCell(5000)->addText($p['tasa'] . '% mensual');
        
        // Fila 8: Interés
        $table->addRow();
        $table->addCell(2500)->addText('💹 Interés:', ['bold' => true]);
        $table->addCell(5000)->addText('$ ' . number_format($p['interes'], 0, ',', '.'));
        
        // Fila 9: Total
        $table->addRow();
        $table->addCell(2500)->addText('💵 Total a Pagar:', ['bold' => true]);
        $table->addCell(5000)->addText('$ ' . number_format($p['total'], 0, ',', '.'), ['bold' => true, 'color' => '006600', 'size' => 14]);
        
        // Empresa (si existe)
        if (!empty($p['empresa'])) {
            $table->addRow();
            $table->addCell(2500)->addText('🏢 Empresa:', ['bold' => true]);
            $table->addCell(5000)->addText($p['empresa']);
        }
        
        $section->addTextBreak(0.5);
        $contador++;
    }
    
    // ===== RESUMEN FINAL =====
    $section->addText('─' . str_repeat('─', 70) . '─', ['size' => 8, 'color' => 'CCCCCC']);
    $section->addTextBreak(1);
    
    $section->addTitle('📊 RESUMEN GENERAL', 2);
    $section->addText("• Total préstamos: " . count($prestamos));
    $section->addText("• Total capital: $ " . number_format($total_capital, 0, ',', '.'));
    $section->addText("• Total intereses: $ " . number_format($total_interes, 0, ',', '.'));
    $section->addText("• Total general: $ " . number_format($total_general, 0, ',', '.'));
    $section->addTextBreak(1);
    $section->addText("• Promedio por préstamo: $ " . number_format(count($prestamos) > 0 ? $total_general / count($prestamos) : 0, 0, ',', '.'));
    
    // Pie de página
    $footer = $section->addFooter();
    $footer->addText('Generado el: ' . date('d/m/Y H:i:s'), ['size' => 8, 'color' => '666666']);
    $footer->addText('Asociación de Transportistas Zona Norte - Sistema de Préstamos', ['size' => 8, 'color' => '666666']);
    
    // ===== DESCARGAR =====
    $filename = "prestamos_" . ($tipo == 'pagado_fecha' ? $fecha : $tipo) . ".docx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Cache-Control: max-age=0');
    
    $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save('php://output');
    exit;
}

// ============================================================
// ===== ACCIONES CRUD =====
// ============================================================

// Bulk update
if ($action==='bulk_update' && $_SERVER['REQUEST_METHOD']==='POST'){
  $ids = $_POST['ids'] ?? [];
  if (!is_array($ids)) $ids = [];
  $ids = array_values(array_unique(array_map(fn($v)=> (int)$v, $ids)));

  if (!$ids) {
    go('?view=cards&msg=noselect&modo_especial='.$modo_especial);
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
    go('?view=cards&msg=noupdate&modo_especial='.$modo_especial);
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
  go('?view=cards&msg='.$msg.'&modo_especial='.$modo_especial);
}

// Bulk mark paid
if ($action==='bulk_mark_paid' && $_SERVER['REQUEST_METHOD']==='POST'){
  $ids = $_POST['ids'] ?? [];
  if (!is_array($ids)) $ids = [];
  $ids = array_values(array_unique(array_map(fn($v)=> (int)$v, $ids)));

  if (!$ids) {
    go('?view=cards&msg=noselect&modo_especial='.$modo_especial);
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
  go('?view=cards&msg='.$msg.'&modo_especial='.$modo_especial);
}

// Bulk mark unpaid
if ($action==='bulk_mark_unpaid' && $_SERVER['REQUEST_METHOD']==='POST'){
  $ids = $_POST['ids'] ?? [];
  if (!is_array($ids)) $ids = [];
  $ids = array_values(array_unique(array_map(fn($v)=> (int)$v, $ids)));

  if (!$ids) {
    go('?view=cards&msg=noselect&modo_especial='.$modo_especial);
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
  go('?view=cards&msg='.$msg.'&modo_especial='.$modo_especial);
}

// Create
if ($action==='create' && $_SERVER['REQUEST_METHOD']==='POST'){
  $deudor_id = (int)($_POST['deudor_id'] ?? 0);
  $prestamista_id = (int)($_POST['prestamista_id'] ?? 0);
  $monto = trim($_POST['monto']??'');
  $fecha = trim($_POST['fecha']??'');
  $img = save_image($_FILES['imagen']??null);
  
  $conn = db();
  $deudor_nombre = '';
  $stmt = $conn->prepare("SELECT nombre FROM deudores_admin WHERE id = ?");
  $stmt->bind_param("i", $deudor_id);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) $deudor_nombre = $row['nombre'];
  $stmt->close();
  
  $prestamista_nombre = '';
  $stmt = $conn->prepare("SELECT nombre FROM prestamistas_admin WHERE id = ?");
  $stmt->bind_param("i", $prestamista_id);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) $prestamista_nombre = $row['nombre'];
  $stmt->close();
  $conn->close();

  if ($deudor_nombre && $prestamista_nombre && is_numeric($monto) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)){
    $c=db();
    $st=$c->prepare("INSERT INTO prestamos (deudor,prestamista,monto,fecha,imagen,created_at) VALUES (?,?,?,?,?,NOW())");
    $st->bind_param("ssdss",$deudor_nombre,$prestamista_nombre,$monto,$fecha,$img);
    $st->execute();
    $st->close(); $c->close();
    go('?msg=creado&view='.urlencode($view).'&modo_especial='.$modo_especial);
  } else {
    $err="Completa todos los campos correctamente.";
  }
}

// Edit
if ($action==='edit' && $_SERVER['REQUEST_METHOD']==='POST' && $id>0){
  $deudor_id = (int)($_POST['deudor_id'] ?? 0);
  $prestamista_id = (int)($_POST['prestamista_id'] ?? 0);
  $monto=trim($_POST['monto']??'');
  $fecha=trim($_POST['fecha']??'');
  $keep = isset($_POST['keep']) ? 1:0;
  $img = save_image($_FILES['imagen']??null);
  
  $conn = db();
  $deudor_nombre = '';
  $stmt = $conn->prepare("SELECT nombre FROM deudores_admin WHERE id = ?");
  $stmt->bind_param("i", $deudor_id);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) $deudor_nombre = $row['nombre'];
  $stmt->close();
  
  $prestamista_nombre = '';
  $stmt = $conn->prepare("SELECT nombre FROM prestamistas_admin WHERE id = ?");
  $stmt->bind_param("i", $prestamista_id);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) $prestamista_nombre = $row['nombre'];
  $stmt->close();
  $conn->close();

  if ($deudor_nombre && $prestamista_nombre && is_numeric($monto) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)){
    $c=db();
    if ($img){
      $st=$c->prepare("UPDATE prestamos SET deudor=?,prestamista=?,monto=?,fecha=?,imagen=? WHERE id=?");
      $st->bind_param("ssdssi",$deudor_nombre,$prestamista_nombre,$monto,$fecha,$img,$id);
    } else {
      if ($keep){
        $st=$c->prepare("UPDATE prestamos SET deudor=?,prestamista=?,monto=?,fecha=? WHERE id=?");
        $st->bind_param("ssdsi",$deudor_nombre,$prestamista_nombre,$monto,$fecha,$id);
      } else {
        $st=$c->prepare("UPDATE prestamos SET deudor=?,prestamista=?,monto=?,fecha=?,imagen=NULL WHERE id=?");
        $st->bind_param("ssdsi",$deudor_nombre,$prestamista_nombre,$monto,$fecha,$id);
      }
    }
    $st->execute();
    $st->close(); $c->close();
    go('?msg=editado&view='.urlencode($view).'&modo_especial='.$modo_especial);
  } else {
    $err="Completa todos los campos correctamente.";
  }
}

// Delete
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
  go('?msg=eliminado&view='.urlencode($view).'&modo_especial='.$modo_especial);
}

// ===== Cargar datos para los selects =====
$conn = db();
$todos_deudores = [];
$res = $conn->query("SELECT id, nombre FROM deudores_admin WHERE owner_chat_id = " . DEFAULT_OWNER_CHAT_ID . " ORDER BY nombre");
while ($row = $res->fetch_assoc()) {
  $todos_deudores[] = $row;
}

$todos_prestamistas = [];
$res = $conn->query("SELECT id, nombre FROM prestamistas_admin WHERE owner_chat_id = " . DEFAULT_OWNER_CHAT_ID . " ORDER BY nombre");
while ($row = $res->fetch_assoc()) {
  $todos_prestamistas[] = $row;
}

// ===== OBTENER FECHAS PARA EL SELECTOR DE GRUPOS =====
// 1. Fechas con pagado_at (solo los que tienen fecha)
$sqlFechas = "
    SELECT DISTINCT DATE(pagado_at) as fecha, COUNT(*) as total
    FROM prestamos 
    WHERE pagado = 1 AND pagado_at IS NOT NULL
    GROUP BY DATE(pagado_at)
    ORDER BY fecha DESC
";
$resultFechas = $conn->query($sqlFechas);
$fechas_con_pago = [];
while ($row = $resultFechas->fetch_assoc()) {
    $fechas_con_pago[] = $row;
}

// 2. Contar PAGADOS SIN FECHA (pagado=1, pagado_at=NULL)
$sqlPagadosSinFecha = "
    SELECT COUNT(*) as total
    FROM prestamos 
    WHERE pagado = 1 AND pagado_at IS NULL
";
$resultSinFecha = $conn->query($sqlPagadosSinFecha);
$pagados_sin_fecha = $resultSinFecha->fetch_assoc()['total'];

// 3. Contar NO PAGADOS (pagado=0)
$sqlNoPagados = "
    SELECT COUNT(*) as total
    FROM prestamos 
    WHERE pagado = 0
";
$resultNoPagados = $conn->query($sqlNoPagados);
$no_pagados = $resultNoPagados->fetch_assoc()['total'];

$conn->close();

// ============================================================
// ===== UI =====
// ============================================================
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Préstamos | Admin</title>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
 :root{ --bg:#f6f7fb; --fg:#222; --card:#fff; --muted:#6b7280; --primary:#0b5ed7; --gray:#6c757d; --red:#dc3545; --chip:#eef2ff; }
 *{box-sizing:border-box}
 body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:22px;background:var(--bg);color:var(--fg)}
 a{text-decoration:none}
 .btn{display:inline-flex;align-items:center;gap:8px;padding:9px 12px;border-radius:12px;background:var(--primary);color:#fff;font-weight:600;border:0;cursor:pointer}
 .btn.gray{background:var(--gray)} .btn.red{background:var(--red)} .btn.small{padding:7px 10px;border-radius:10px}
 .btn.yellow{background:#f59e0b;color:#fff}
 .btn.green{background:#10b981;color:#fff}
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

 .bulkbar{display:flex;gap:10px;align-items:center;margin:8px 0 0;flex-wrap:wrap}
 .bulkpanel{display:none;margin-top:10px;border:1px dashed #e5e7eb;border-radius:12px;padding:12px;background:#fafafa}
 .badge{background:#111;color:#fff;border-radius:999px;padding:2px 8px;font-size:12px;font-weight:700}
 .cardSel{display:flex;align-items:center;gap:8px;margin-bottom:6px}
 .sticky-actions{position:sticky; top:10px; align-self:flex-start}

 .card-comision { border-left: 4px solid #0b5ed7; background: #F0F9FF !important; }
 .comision-badge { background: #0b5ed7 !important; color: white !important; }
 .comision-info { background: #EAF5FF !important; border: 1px solid #BAE6FD !important; }
 .comision-text { color: #0369A1 !important; font-weight: 600; }

 .resumen-filtro { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 16px; margin-bottom: 16px; }
 .resumen-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-top: 12px; }
 .resumen-item { background: white; border-radius: 8px; padding: 12px; text-align: center; }
 .resumen-valor { font-size: 18px; font-weight: 800; color: #0369a1; }
 .resumen-label { font-size: 12px; color: #6b7280; margin-top: 4px; }

 .switch-container { display: flex; align-items: center; gap: 8px; background: #f8f9fa; padding: 8px 12px; border-radius: 12px; border: 1px solid #e5e7eb; }
 .switch-label { font-size: 14px; font-weight: 600; color: #374151; }
 .switch-group { display:flex; gap:6px; }
 .switch-pill { display:flex; align-items:center; }
 .switch-pill input { display:none; }
 .switch-pill span { font-size:12px; padding:4px 10px; border-radius:999px; border:1px solid #e5e7eb; background:#fff; cursor:pointer; }
 .switch-pill input:checked + span { background:#0b5ed7; color:#fff; border-color:#0b5ed7; }

 .card-pagado { border-left: 4px solid #10b981; background: #f0fdf4 !important; opacity: 0.8; }
 .pagado-badge { background: #10b981 !important; color: white !important; }
 .text-pagado { color: #065f46 !important; font-weight: 600; }

 .select2-container { width: 100% !important; }
 .select2-selection { border: 1px solid #e5e7eb !important; border-radius: 12px !important; padding: 8px !important; height: 45px !important; }
 .select2-selection__arrow { height: 43px !important; }
 .select2-search__field { border-radius: 8px !important; padding: 6px !important; }

 .modo-especial-container { display: flex; align-items: center; gap: 12px; background: #fef3c7; padding: 6px 16px; border-radius: 40px; border: 1px solid #fde68a; }
 .modo-especial-label { font-size: 13px; font-weight: 700; color: #92400e; }
 .toggle-switch { position: relative; display: inline-block; width: 52px; height: 26px; }
 .toggle-switch input { opacity: 0; width: 0; height: 0; }
 .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: 0.3s; border-radius: 26px; }
 .toggle-slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: 0.3s; border-radius: 50%; }
 input:checked + .toggle-slider { background-color: #f59e0b; }
 input:checked + .toggle-slider:before { transform: translateX(26px); }
 .modo-activo-badge { background: #f59e0b; color: #fff; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }

 /* Estilos para el selector de grupos */
 .selector-grupo { background: white; padding: 16px; border-radius: 12px; margin-bottom: 16px; border: 2px solid #e5e7eb; }
 .selector-grupo .select2-container { min-width: 300px; }
 .grupo-option-pagado { background: #f0fdf4; }
 .grupo-option-sin-fecha { background: #fef3c7; font-weight: 700; }
 .grupo-option-no-pagado { background: #fee2e2; font-weight: 700; }

 .resultados-grupo { margin-top: 16px; }
 .resultados-tabla { width: 100%; border-collapse: collapse; font-size: 13px; }
 .resultados-tabla th { background: #1a3c6e; color: white; padding: 10px 8px; text-align: left; position: sticky; top: 0; }
 .resultados-tabla td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
 .resultados-tabla tr:hover { background: #f8fafc; }
 .resultados-tabla .total-row { background: #fef3c7; font-weight: 700; }
 .resultados-tabla .total-row td { border-top: 2px solid #f59e0b; }

 .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
 .modal-content { background: white; border-radius: 16px; padding: 24px; width: 90%; max-width: 400px; }
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

<?php
// ====== NEW / EDIT FORMS ======
if ($action==='new' || ($action==='edit' && $id>0 && $_SERVER['REQUEST_METHOD']!=='POST')):
  $row = ['deudor'=>'','prestamista'=>'','monto'=>'','fecha'=>'','imagen'=>null];
  $deudor_seleccionado = 0;
  $prestamista_seleccionado = 0;
  
  if ($action==='edit'){
    $c=db();
    $st=$c->prepare("SELECT deudor,prestamista,monto,fecha,imagen FROM prestamos WHERE id=?");
    $st->bind_param("i",$id);
    $st->execute();
    $res=$st->get_result();
    $row=$res->fetch_assoc() ?: $row;
    $st->close();
    
    $stmt = $c->prepare("SELECT id FROM deudores_admin WHERE owner_chat_id = ? AND nombre = ?");
    $stmt->bind_param("is", DEFAULT_OWNER_CHAT_ID, $row['deudor']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($drow = $res->fetch_assoc()) {
      $deudor_seleccionado = $drow['id'];
    }
    $stmt->close();
    
    $stmt = $c->prepare("SELECT id FROM prestamistas_admin WHERE owner_chat_id = ? AND nombre = ?");
    $stmt->bind_param("is", DEFAULT_OWNER_CHAT_ID, $row['prestamista']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($prow = $res->fetch_assoc()) {
      $prestamista_seleccionado = $prow['id'];
    }
    $stmt->close();
    $c->close();
  }
?>
  <div id="modalDeudor" class="modal">
    <div class="modal-content">
      <h3 style="margin-bottom: 16px;">Nuevo Deudor</h3>
      <form method="post">
        <input type="text" name="nuevo_deudor_nombre" placeholder="Nombre del deudor" style="width: 100%; margin-bottom: 16px; padding: 10px; border-radius: 12px; border: 1px solid #ddd;" required>
        <div style="display: flex; gap: 12px;">
          <button type="submit" class="btn">Guardar</button>
          <button type="button" class="btn gray" onclick="document.getElementById('modalDeudor').style.display='none'">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
  
  <div id="modalPrestamista" class="modal">
    <div class="modal-content">
      <h3 style="margin-bottom: 16px;">Nuevo Prestamista</h3>
      <form method="post">
        <input type="text" name="nuevo_prestamista_nombre" placeholder="Nombre del prestamista" style="width: 100%; margin-bottom: 16px; padding: 10px; border-radius: 12px; border: 1px solid #ddd;" required>
        <div style="display: flex; gap: 12px;">
          <button type="submit" class="btn">Guardar</button>
          <button type="button" class="btn gray" onclick="document.getElementById('modalPrestamista').style.display='none'">Cancelar</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="row" style="margin-bottom:10px">
      <div class="title"><?= $action==='new'?'Nuevo préstamo':'Editar préstamo #'.h($id) ?></div>
    </div>
    <?php if(!empty($err)): ?>
      <div class="error" style="margin-bottom:10px"><?= h($err) ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" action="?action=<?= $action==='new'?'create':'edit&id='.$id ?>&view=cards&modo_especial=<?= $modo_especial ?>">
      <div class="row" style="gap:12px;flex-wrap:wrap">
        <div class="field" style="min-width:220px;flex:1">
          <label>Deudor *</label>
          <div style="display: flex; gap: 8px;">
            <select name="deudor_id" id="deudorSelect2" style="flex: 1;" required>
              <option value="0">-- Seleccionar deudor --</option>
              <?php foreach($todos_deudores as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $deudor_seleccionado == $d['id'] ? 'selected' : '' ?>><?= h($d['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" class="btn yellow" id="btnNuevoDeudor" style="padding: 9px 12px;">➕</button>
          </div>
        </div>
        <div class="field" style="min-width:220px;flex:1">
          <label>Prestamista *</label>
          <div style="display: flex; gap: 8px;">
            <select name="prestamista_id" id="prestamistaSelect2" style="flex: 1;" required>
              <option value="0">-- Seleccionar prestamista --</option>
              <?php foreach($todos_prestamistas as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $prestamista_seleccionado == $p['id'] ? 'selected' : '' ?>><?= h($p['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" class="btn yellow" id="btnNuevoPrestamista" style="padding: 9px 12px;">➕</button>
          </div>
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
        <a class="btn gray" href="?view=cards&modo_especial=<?= $modo_especial ?>">Cancelar</a>
      </div>
    </form>
  </div>
<?php
// ====== LIST (SOLO TARJETAS) ======
else:
?>
    <!-- ============================================================
         SELECTOR DE GRUPOS PARA DESCARGAR EN WORD
         ============================================================ -->
    <div class="selector-grupo">
        <div class="row" style="gap: 12px; flex-wrap: wrap;">
            <div class="field" style="min-width: 350px; flex: 2;">
                <label style="font-weight: 700;">📂 Seleccionar grupo de préstamos</label>
                <select id="selectGrupo" class="select2" style="width: 100%;">
                    <option value="">-- Seleccionar grupo --</option>
                    
                    <?php if(!empty($fechas_con_pago)): ?>
                        <optgroup label="📅 PAGADOS CON FECHA">
                        <?php foreach($fechas_con_pago as $f): ?>
                            <option value="pagado_fecha_<?= $f['fecha'] ?>" class="grupo-option-pagado">
                                📅 <?= date('d/m/Y', strtotime($f['fecha'])) ?> (<?= $f['total'] ?> préstamos)
                            </option>
                        <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                    
                    <?php if($pagados_sin_fecha > 0): ?>
                        <option value="pagado_sin_fecha" class="grupo-option-sin-fecha" style="background: #fef3c7; font-weight: 700;">
                            📌 PAGADOS SIN FECHA (<?= $pagados_sin_fecha ?> préstamos)
                        </option>
                    <?php endif; ?>
                    
                    <?php if($no_pagados > 0): ?>
                        <option value="no_pagados" class="grupo-option-no-pagado" style="background: #fee2e2; font-weight: 700;">
                            ⏳ NO PAGADOS (<?= $no_pagados ?> préstamos)
                        </option>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="field" style="align-self: flex-end;">
                <button class="btn green" id="btnDescargarWord" onclick="descargarWord()" style="padding: 12px 24px; font-size: 15px;">
                    📥 Descargar Word
                </button>
            </div>
        </div>
        
        <!-- Resultados del grupo seleccionado -->
        <div id="resultadosGrupo" class="resultados-grupo">
            <div class="subtitle">Selecciona un grupo para ver los préstamos</div>
        </div>
    </div>

    <!-- Toolbar de filtros -->
    <div class="card" style="margin-bottom:16px">
      <form class="toolbar" method="get" id="filtroForm">
        <input type="hidden" name="view" value="cards">
        
        <div class="modo-especial-container">
          <span class="modo-especial-label">⚡ Modo 8% por días</span>
          <label class="toggle-switch">
            <input type="checkbox" name="modo_especial" value="1" id="modoEspecialToggle" onchange="this.form.submit()" <?= $modo_especial == 1 ? 'checked' : '' ?>>
            <span class="toggle-slider"></span>
          </label>
          <?php if ($modo_especial == 1): ?>
            <span class="modo-activo-badge">ACTIVO • Cálculo por días exactos al 8% mensual</span>
          <?php else: ?>
            <span class="subtitle" style="font-size:11px">Modo normal • meses completos</span>
          <?php endif; ?>
        </div>
        
        <input name="q" placeholder="🔎 Buscar (deudor / prestamista)" value="<?= h($_GET['q'] ?? '') ?>" style="flex:1;min-width:220px">
        
        <div class="field" style="min-width:200px;flex:1">
          <label>Prestamista</label>
          <select name="fp" id="prestamistaSelect" class="select2-filter">
            <option value="">Todos los prestamistas</option>
            <?php
            $prestMap = [];
            $conn = db();
            $resPL = $conn->query("SELECT DISTINCT prestamista FROM prestamos ORDER BY prestamista");
            while($rowPL=$resPL->fetch_row()){
              $norm = mbnorm($rowPL[0]);
              if ($norm==='') continue;
              if (!isset($prestMap[$norm])) $prestMap[$norm] = $rowPL[0];
            }
            ksort($prestMap, SORT_NATURAL);
            $conn->close();
            foreach($prestMap as $norm=>$label): ?>
              <option value="<?= h($norm) ?>" <?= (mbnorm($_GET['fp']??'')===$norm)?'selected':'' ?>><?= h(mbtitle($label)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field" style="min-width:200px;flex:1">
          <label>Empresa</label>
          <select name="fe" id="empresaSelect" class="select2-filter">
            <option value="">Todas las empresas</option>
            <?php
            $empMap = [];
            $conn = db();
            $resEL = $conn->query("SELECT DISTINCT empresa FROM prestamos ORDER BY empresa");
            while($rowEL=$resEL->fetch_row()){
              $val = $rowEL[0];
              $norm = mbnorm($val);
              if ($norm==='') continue;
              if (!isset($empMap[$norm])) $empMap[$norm] = $val;
            }
            ksort($empMap, SORT_NATURAL);
            $conn->close();
            foreach($empMap as $norm=>$label): ?>
              <option value="<?= h($norm) ?>" <?= (mbnorm($_GET['fe']??'')===$norm)?'selected':'' ?>><?= h(mbtitle($label)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field" style="min-width:200px;flex:1">
          <label>Deudor</label>
          <select name="fd" id="deudorSelect" class="select2-filter">
            <option value="">Todos los deudores</option>
            <?php
            $deudMap = [];
            $conn = db();
            $resDL = $conn->query("SELECT DISTINCT deudor FROM prestamos ORDER BY deudor");
            while($rowDL=$resDL->fetch_row()){
              $norm = mbnorm($rowDL[0]);
              if ($norm==='') continue;
              if (!isset($deudMap[$norm])) $deudMap[$norm] = $rowDL[0];
            }
            ksort($deudMap, SORT_NATURAL);
            $conn->close();
            foreach($deudMap as $norm=>$label): ?>
              <option value="<?= h($norm) ?>" <?= (mbnorm($_GET['fd']??'')===$norm)?'selected':'' ?>><?= h(mbtitle($label)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field" style="min-width:150px">
          <label>Desde</label>
          <input name="fecha_desde" type="date" value="<?= h($_GET['fecha_desde'] ?? '') ?>">
        </div>
        <div class="field" style="min-width:150px">
          <label>Hasta</label>
          <input name="fecha_hasta" type="date" value="<?= h($_GET['fecha_hasta'] ?? '') ?>">
        </div>
        
        <div class="switch-container">
          <span class="switch-label">Estado:</span>
          <div class="switch-group">
            <label class="switch-pill">
              <input type="radio" name="estado_pago" value="no_pagados"
                     <?= ($_GET['estado_pago'] ?? 'no_pagados') === 'no_pagados' ? 'checked' : '' ?>
                     onchange="this.form.submit()">
              <span>No pagados</span>
            </label>
            <label class="switch-pill">
              <input type="radio" name="estado_pago" value="pagados"
                     <?= ($_GET['estado_pago'] ?? '') === 'pagados' ? 'checked' : '' ?>
                     onchange="this.form.submit()">
              <span>Pagados</span>
            </label>
            <label class="switch-pill">
              <input type="radio" name="estado_pago" value="todos"
                     <?= ($_GET['estado_pago'] ?? '') === 'todos' ? 'checked' : '' ?>
                     onchange="this.form.submit()">
              <span>Todos</span>
            </label>
          </div>
        </div>

        <button class="btn" type="submit">Filtrar</button>
        <?php 
        $q = $_GET['q'] ?? '';
        $fpNorm = mbnorm($_GET['fp'] ?? '');
        $fdNorm = mbnorm($_GET['fd'] ?? '');
        $feNorm = mbnorm($_GET['fe'] ?? '');
        $fecha_desde = $_GET['fecha_desde'] ?? '';
        $fecha_hasta = $_GET['fecha_hasta'] ?? '';
        $estado_pago = $_GET['estado_pago'] ?? 'no_pagados';
        if ($q!=='' || $fpNorm!=='' || $fdNorm!=='' || $feNorm!=='' || $fecha_desde!=='' || $fecha_hasta!=='' || $estado_pago !== 'no_pagados'): ?>
          <a class="btn gray" href="?view=cards&modo_especial=<?= $modo_especial ?>">Quitar filtro</a>
        <?php endif; ?>
      </form>
    </div>

    <?php
    // ===== CONSULTA PRINCIPAL PARA TARJETAS =====
    $conn = db();
    
    $whereBase = "1=1";
    if ($estado_pago === 'no_pagados') {
        $whereBase = "pagado = 0";
    } elseif ($estado_pago === 'pagados') {
        $whereBase = "pagado = 1";
    }
    
    $where = $whereBase; $types=""; $params=[];
    if ($q!==''){
        $qNorm = mbnorm($q);
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
      SELECT 
        id, deudor, prestamista, monto, fecha, imagen, created_at, pagado, pagado_at,
        empresa,
        comision_gestor_nombre, comision_gestor_porcentaje, comision_base_monto, 
        comision_origen_prestamista, comision_origen_porcentaje,
        
        CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END AS meses,
        
        (monto * 
          CASE 
            WHEN fecha >= '2025-10-29' THEN COALESCE(comision_origen_porcentaje, 13)
            ELSE COALESCE(comision_origen_porcentaje, 10)
          END / 100 *
          CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS interes_prestamista,
        
        (COALESCE(comision_base_monto, monto) * COALESCE(comision_gestor_porcentaje, 0) / 100 *
          CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END) AS comision_gestor,
        
        ((monto * 
            CASE 
              WHEN fecha >= '2025-10-29' THEN COALESCE(comision_origen_porcentaje, 13)
              ELSE COALESCE(comision_origen_porcentaje, 10)
            END / 100) + 
          (COALESCE(comision_base_monto, monto) * COALESCE(comision_gestor_porcentaje, 0) / 100)) *
          CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END AS interes_total,
        
        (monto + 
          (((monto * 
              CASE 
                WHEN fecha >= '2025-10-29' THEN COALESCE(comision_origen_porcentaje, 13)
                ELSE COALESCE(comision_origen_porcentaje, 10)
              END / 100) + 
            (COALESCE(comision_base_monto, monto) * COALESCE(comision_gestor_porcentaje, 0) / 100)) *
            CASE WHEN CURDATE() < fecha THEN 0 ELSE TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 END)) AS total
            
      FROM prestamos
      WHERE $where
      ORDER BY pagado ASC, id DESC
    ";
    
    $st=$conn->prepare($sql);
    if($types) $st->bind_param($types, ...$params);
    $st->execute();
    $rs=$st->get_result();
    ?>
    
    <?php if ($rs->num_rows === 0): ?>
      <div class="card"><span class="subtitle">(sin registros)</span></div>
    <?php else: ?>
      <form id="bulkForm" class="card" method="post" action="?action=bulk_update&modo_especial=<?= $modo_especial ?>">
        <input type="hidden" name="view" value="cards">

        <div class="row" style="margin-bottom:8px">
          <div class="title">Selecciona tarjetas</div>
          <div class="sticky-actions" style="display:flex;gap:8px;align-items:center">
            <label class="subtitle" style="display:flex;gap:8px;align-items:center">
              <input id="chkAll" type="checkbox"> Seleccionar todo (página)
            </label>
            <button type="button" class="btn gray small" id="btnToggleBulk">✏️ Editar selección</button>
            <button type="submit" class="btn small" formaction="?action=bulk_mark_paid&modo_especial=<?= $modo_especial ?>" onclick="return confirm('¿Marcar como pagados los préstamos seleccionados?')">
              ✔ Préstamo pagado
            </button>
            <button type="submit" class="btn gray small" formaction="?action=bulk_mark_unpaid&modo_especial=<?= $modo_especial ?>" onclick="return confirm('¿Marcar como NO pagados los préstamos seleccionados?')">
              ↩ NO pagado
            </button>
            <span class="badge" id="selCount">0 seleccionadas</span>
          </div>
        </div>

        <div class="grid-cards">
          <?php while($r=$rs->fetch_assoc()):
            $esComision = !empty($r['comision_gestor_nombre']);
            $esPagado = (bool)($r['pagado'] ?? false);
            
            $cardClass = '';
            $badgeClass = 'chip';
            
            if ($esComision) {
              $cardClass = 'card-comision';
              $badgeClass = 'comision-badge';
            } elseif ($esPagado) {
              $cardClass = 'card-pagado';
              $badgeClass = 'pagado-badge';
            }
          ?>
            <div class="card <?= $cardClass ?>">
              <div class="cardSel">
                <input class="chkRow" type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>">
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
                </div>
              <?php endif; ?>

              <div class="pairs" style="margin-top:12px">
                <div class="item">
                  <div class="k">Monto</div>
                  <div class="v">$ <?= money($r['monto']) ?></div>
                </div>
                <div class="item">
                  <div class="k">Meses</div>
                  <div class="v"><?= h($r['meses'] ?? 0) ?></div>
                </div>
                <div class="item">
                  <div class="k">Interés</div>
                  <div class="v">$ <?= money($r['interes_total']) ?></div>
                </div>
                <div class="item">
                  <div class="k">Total</div>
                  <div class="v">$ <?= money($r['total']) ?></div>
                </div>
              </div>

              <?php if ($esComision): ?>
                <div class="pairs" style="margin-top:8px; font-size:12px;">
                  <div class="item">
                    <div class="k">Interés Prestamista</div>
                    <div class="v">$ <?= money($r['interes_prestamista']) ?></div>
                  </div>
                  <div class="item">
                    <div class="k">Comisión Gestor</div>
                    <div class="v">$ <?= money($r['comision_gestor']) ?></div>
                  </div>
                </div>
              <?php endif; ?>

              <div class="row" style="margin-top:12px">
                <div class="subtitle">Creado: <?= h($r['created_at']) ?></div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                  <a class="btn gray small" href="?action=edit&id=<?= $r['id'] ?>&view=cards&modo_especial=<?= $modo_especial ?>">✏️ Editar</a>
                  <button class="btn red small" type="button" onclick="submitDelete(<?= (int)$r['id'] ?>)">🗑️ Eliminar</button>
                </div>
              </div>
            </div>
          <?php endwhile; ?>
        </div>

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

<!-- ============================================================
     SCRIPTS
     ============================================================ -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// ===== SELECTOR DE GRUPOS =====
$(document).ready(function() {
    // Inicializar Select2
    $('#selectGrupo').select2({
        placeholder: 'Seleccionar grupo...',
        allowClear: true
    });
    
    // Al cambiar el select, cargar los préstamos
    $('#selectGrupo').on('change', function() {
        const valor = $(this).val();
        if (!valor) {
            $('#resultadosGrupo').html('<div class="subtitle">Selecciona un grupo para ver los préstamos</div>');
            return;
        }
        
        // Mostrar loading
        $('#resultadosGrupo').html('<div class="subtitle">Cargando préstamos...</div>');
        
        // Llamar AJAX
        $.ajax({
            url: 'ajax_cargar_grupo.php',
            method: 'POST',
            data: { grupo: valor },
            dataType: 'json',
            success: function(response) {
                if (response.error) {
                    $('#resultadosGrupo').html('<div class="error">' + response.error + '</div>');
                    return;
                }
                
                let html = '';
                
                // Encabezado del grupo
                html += '<div style="background: #f8fafc; padding: 12px; border-radius: 12px; margin-bottom: 12px;">';
                html += '<div class="row">';
                html += '<div>';
                html += '<div class="title" style="font-size: 16px;">📊 ' + response.titulo + '</div>';
                html += '<div class="subtitle">' + response.total + ' préstamos en este grupo</div>';
                html += '</div>';
                html += '<div style="text-align: right;">';
                html += '<div style="font-size: 13px; color: #6b7280;">Capital: <strong>$' + response.resumen.capital + '</strong></div>';
                html += '<div style="font-size: 13px; color: #6b7280;">Interés: <strong>$' + response.resumen.interes + '</strong></div>';
                html += '<div style="font-size: 15px; color: #065f46; font-weight: 700;">Total: $' + response.resumen.total + '</div>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
                
                // Tabla de préstamos
                if (response.prestamos && response.prestamos.length > 0) {
                    html += '<div style="max-height: 500px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 12px;">';
                    html += '<table class="resultados-tabla">';
                    html += '<thead>';
                    html += '<tr>';
                    html += '<th>#</th>';
                    html += '<th>Deudor</th>';
                    html += '<th>Prestamista</th>';
                    html += '<th>Monto</th>';
                    html += '<th>Fecha</th>';
                    html += '<th>Meses</th>';
                    html += '<th>Interés</th>';
                    html += '<th>Total</th>';
                    html += '</tr>';
                    html += '</thead>';
                    html += '<tbody>';
                    
                    let i = 1;
                    let totalCapital = 0;
                    let totalInteres = 0;
                    let totalGeneral = 0;
                    
                    response.prestamos.forEach(function(p) {
                        totalCapital += parseFloat(p.monto);
                        totalInteres += parseFloat(p.interes);
                        totalGeneral += parseFloat(p.total);
                        
                        html += '<tr>';
                        html += '<td>' + i + '</td>';
                        html += '<td><strong>' + p.deudor + '</strong></td>';
                        html += '<td>' + p.prestamista + '</td>';
                        html += '<td style="text-align: right;">$' + p.monto_formateado + '</td>';
                        html += '<td>' + p.fecha + '</td>';
                        html += '<td style="text-align: center;">' + p.meses + '</td>';
                        html += '<td style="text-align: right;">$' + p.interes_formateado + '</td>';
                        html += '<td style="text-align: right; font-weight: 700; color: #065f46;">$' + p.total_formateado + '</td>';
                        html += '</tr>';
                        i++;
                    });
                    
                    // Totales
                    html += '<tr class="total-row">';
                    html += '<td colspan="2"><strong>TOTALES</strong></td>';
                    html += '<td></td>';
                    html += '<td style="text-align: right;">$' + numberFormat(totalCapital) + '</td>';
                    html += '<td></td>';
                    html += '<td style="text-align: center;"></td>';
                    html += '<td style="text-align: right;">$' + numberFormat(totalInteres) + '</td>';
                    html += '<td style="text-align: right;">$' + numberFormat(totalGeneral) + '</td>';
                    html += '</tr>';
                    
                    html += '</tbody>';
                    html += '</table>';
                    html += '</div>';
                } else {
                    html += '<div class="subtitle">No hay préstamos en este grupo</div>';
                }
                
                $('#resultadosGrupo').html(html);
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                $('#resultadosGrupo').html('<div class="error">Error al cargar los préstamos: ' + error + '</div>');
            }
        });
    });
});

// ===== FUNCIÓN PARA DESCARGAR WORD =====
function descargarWord() {
    const valor = $('#selectGrupo').val();
    if (!valor) {
        alert('⚠️ Selecciona un grupo primero');
        return;
    }
    
    // Redirigir a la página de descarga
    window.location.href = '?descargar_word=1&grupo=' + encodeURIComponent(valor);
}

// ===== FUNCIÓN PARA FORMATEAR NÚMEROS =====
function numberFormat(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// ===== INICIALIZAR SELECT2 EN FILTROS =====
$(document).ready(function() {
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
    
    $('#deudorSelect2').select2({
        width: '100%',
        placeholder: 'Buscar deudor...',
        allowClear: true
    });
    
    $('#prestamistaSelect2').select2({
        width: '100%',
        placeholder: 'Buscar prestamista...',
        allowClear: true
    });
    
    $('#btnNuevoDeudor').click(function() {
        $('#modalDeudor').css('display', 'flex');
    });
    
    $('#btnNuevoPrestamista').click(function() {
        $('#modalPrestamista').css('display', 'flex');
    });
    
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    };
});

// ===== SELECCIÓN MÚLTIPLE =====
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

function submitDelete(id){
    if(!confirm('¿Eliminar #'+id+'?')) return;
    const f = document.createElement('form');
    f.method = 'post';
    f.action = '?action=delete&id='+id+'&modo_especial=<?= $modo_especial ?>';
    document.body.appendChild(f);
    f.submit();
}
</script>

</body></html>