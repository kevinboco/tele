<?php
/*********************************************************
 * admin_prestamos.php — CRUD + Tarjetas + Pago Inteligente
 * - Vista previa de pagos con reestructuración automática (SIN AJAX)
 * - Selección múltiple de préstamos a pagar
 * - Interés variable: 13% desde 2025-10-29, 10% antes
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
    if (!headers_sent()){ header("Location: ".$url, true, 302); exit; }
    $u = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    echo "<!doctype html><html><head><meta http-equiv='refresh' content='0;url={$u}'><script>location.replace('{$u}');</script></head><body><a href='{$u}'>Ir</a></body></html>";
    exit;
}
function mbnorm($s){ return mb_strtolower(trim((string)$s),'UTF-8'); }
function mbtitle($s){ return function_exists('mb_convert_case') ? mb_convert_case((string)$s, MB_CASE_TITLE, 'UTF-8') : ucwords(strtolower((string)$s)); }

// ===== Obtener tasa de interés según fecha =====
function getTasaInteres($fecha) {
    return strtotime($fecha) >= strtotime('2025-10-29') ? 13 : 10;
}

// ===== Calcular meses transcurridos =====
function calcularMeses($fecha) {
    $fechaObj = new DateTime($fecha);
    $hoy = new DateTime();
    if ($hoy < $fechaObj) return 0;
    $diff = $fechaObj->diff($hoy);
    return ($diff->y * 12) + $diff->m + 1; // +1 porque el primer mes cuenta
}

$action = $_GET['action'] ?? 'list';
$view   = 'cards';
$id = (int)($_GET['id'] ?? 0);

// ===== CRUD básico =====
if ($action==='create' && $_SERVER['REQUEST_METHOD']==='POST'){
    $deudor = trim($_POST['deudor']??'');
    $prestamista = trim($_POST['prestamista']??'');
    $monto = trim($_POST['monto']??'');
    $fecha = trim($_POST['fecha']??'');
    $empresa = trim($_POST['empresa']??'');
    
    if ($deudor && $prestamista && is_numeric($monto) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)){
        $c=db();
        $st=$c->prepare("INSERT INTO prestamos (deudor,prestamista,monto,fecha,empresa,created_at) VALUES (?,?,?,?,?,NOW())");
        $st->bind_param("ssdss",$deudor,$prestamista,$monto,$fecha,$empresa);
        $st->execute();
        $st->close(); $c->close();
        go('?msg=creado&view=cards');
    }
}

if ($action==='edit' && $_SERVER['REQUEST_METHOD']==='POST' && $id>0){
    $deudor=trim($_POST['deudor']??'');
    $prestamista=trim($_POST['prestamista']??'');
    $monto=trim($_POST['monto']??'');
    $fecha=trim($_POST['fecha']??'');
    $empresa=trim($_POST['empresa']??'');

    if ($deudor && $prestamista && is_numeric($monto) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)){
        $c=db();
        $st=$c->prepare("UPDATE prestamos SET deudor=?, prestamista=?, monto=?, fecha=?, empresa=? WHERE id=?");
        $st->bind_param("ssdssi",$deudor,$prestamista,$monto,$fecha,$empresa,$id);
        $st->execute();
        $st->close(); $c->close();
        go('?msg=editado&view=cards');
    }
}

if ($action==='delete' && $_SERVER['REQUEST_METHOD']==='POST' && $id>0){
    $c=db();
    $st=$c->prepare("DELETE FROM prestamos WHERE id=?");
    $st->bind_param("i",$id);
    $st->execute();
    $st->close(); $c->close();
    go('?msg=eliminado&view=cards');
}

// ===== Procesar pago (POST desde el modal) =====
if ($action==='procesar_pago' && $_SERVER['REQUEST_METHOD']==='POST'){
    $data = json_decode(file_get_contents('php://input'), true);
    $resultado = $data['resultado'] ?? [];
    $reestructurar = $data['reestructurar'] ?? [];
    
    if (empty($resultado)) {
        http_response_code(400);
        echo json_encode(['error' => 'No hay datos para procesar']);
        exit;
    }
    
    $conn = db();
    $conn->begin_transaction();
    
    try {
        // 1. Marcar como pagados los préstamos completos
        foreach ($resultado as $item) {
            if ($item['accion'] === 'pagado_completo') {
                $stmt = $conn->prepare("UPDATE prestamos SET pagado = 1, pagado_at = NOW() WHERE id = ?");
                $stmt->bind_param("i", $item['id']);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        // 2. Marcar como reestructurados los originales y crear nuevos
        foreach ($reestructurar as $r) {
            // Crear nuevo préstamo primero para obtener el ID
            $stmt = $conn->prepare("INSERT INTO prestamos 
                (deudor, prestamista, monto, fecha, created_at, empresa, 
                 comision_origen_porcentaje, comision_gestor_porcentaje, nota) 
                VALUES (?, ?, ?, CURDATE(), NOW(), '', ?, ?, CONCAT('Reestructuración del préstamo #', ?))");
            $stmt->bind_param("ssddii", 
                $r['deudor'], 
                $r['prestamista'], 
                $r['nuevo_capital'], 
                $r['tasa_origen'], 
                $r['tasa_gestor'], 
                $r['original_id']
            );
            $stmt->execute();
            $nuevoId = $stmt->insert_id;
            $stmt->close();
            
            // Marcar original como reestructurado
            $stmt = $conn->prepare("UPDATE prestamos SET pagado = 1, pagado_at = NOW(), nota = CONCAT('Reestructurado - Nuevo préstamo #', ?) WHERE id = ?");
            $stmt->bind_param("ii", $nuevoId, $r['original_id']);
            $stmt->execute();
            $stmt->close();
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Pago procesado correctamente']);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Error al procesar el pago: ' . $e->getMessage()]);
    }
    
    $conn->close();
    exit;
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
        .cardSel{display:flex;align-items:center;gap:8px;margin-bottom:6px}
        .card-comision { border-left: 4px solid #0b5ed7; background: #F0F9FF !important; }
        .comision-badge { background: #0b5ed7 !important; color: white !important; }
        .card-pagado { border-left: 4px solid #10b981; background: #f0fdf4 !important; opacity: 0.8; }
        .pagado-badge { background: #10b981 !important; color: white !important; }
        .select2-container { width: 100% !important; }
        
        /* Modal de vista previa */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: #fff; margin: 30px auto; padding: 20px; border-radius: 16px; max-width: 800px; max-height: 80vh; overflow-y: auto; }
        .close { float: right; font-size: 28px; cursor: pointer; }
        .preview-item { background: #f8fafc; border-left: 4px solid var(--primary); padding: 12px; margin-bottom: 12px; border-radius: 8px; }
        .preview-item.pagado { border-left-color: #10b981; background: #f0fdf4; }
        .preview-item.reestructurar { border-left-color: #f59e0b; background: #fffbeb; }
        .badge-pill { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .badge-pill.success { background: #10b981; color: white; }
        .badge-pill.warning { background: #f59e0b; color: white; }
        .badge-pill.info { background: #0b5ed7; color: white; }
    </style>
</head>
<body>

<div class="tabs">
    <a class="active" href="?view=cards">📇 Tarjetas</a>
    <a class="btn gray" href="?action=new&view=cards" style="margin-left:auto">➕ Crear</a>
</div>

<?php if (!empty($_GET['msg'])): ?>
    <div class="msg" style="margin-bottom:14px">
        <?php 
        $msg = $_GET['msg'];
        if ($msg === 'creado') echo 'Registro creado correctamente.';
        elseif ($msg === 'editado') echo 'Cambios guardados.';
        elseif ($msg === 'eliminado') echo 'Registro eliminado.';
        else echo 'Operación realizada.';
        ?>
    </div>
<?php endif; ?>

<!-- MODAL DE VISTA PREVIA -->
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
    $row = ['deudor'=>'','prestamista'=>'','monto'=>'','fecha'=>'','empresa'=>''];
    if ($action==='edit'){
        $c=db();
        $st=$c->prepare("SELECT deudor,prestamista,monto,fecha,empresa,imagen FROM prestamos WHERE id=?");
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
                <div class="field" style="min-width:200px">
                    <label>Empresa</label>
                    <input name="empresa" value="<?= h($row['empresa'] ?? '') ?>">
                </div>
            </div>
            <div class="row" style="margin-top:12px">
                <button class="btn" type="submit">💾 Guardar</button>
                <a class="btn gray" href="?view=cards">Cancelar</a>
            </div>
        </form>
    </div>
<?php
// ====== LIST (TARJETAS) ======
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
    
    // Where base
    $whereBase = match($estado_pago) {
        'pagados' => "pagado = 1",
        'todos' => "1=1",
        default => "pagado = 0"
    };
    
    // Construir WHERE
    $where = $whereBase;
    $types = "";
    $params = [];
    
    if ($q !== '') {
        $where .= " AND (LOWER(deudor) LIKE CONCAT('%',?,'%') OR LOWER(prestamista) LIKE CONCAT('%',?,'%'))";
        $types .= "ss";
        $params[] = $qNorm;
        $params[] = $qNorm;
    }
    if ($fpNorm !== '') {
        $where .= " AND LOWER(TRIM(prestamista)) = ?";
        $types .= "s";
        $params[] = $fpNorm;
    }
    if ($fdNorm !== '') {
        $where .= " AND LOWER(TRIM(deudor)) = ?";
        $types .= "s";
        $params[] = $fdNorm;
    }
    if ($feNorm !== '') {
        $where .= " AND LOWER(TRIM(empresa)) = ?";
        $types .= "s";
        $params[] = $feNorm;
    }
    if ($fecha_desde !== '') {
        $where .= " AND fecha >= ?";
        $types .= "s";
        $params[] = $fecha_desde;
    }
    if ($fecha_hasta !== '') {
        $where .= " AND fecha <= ?";
        $types .= "s";
        $params[] = $fecha_hasta;
    }
    
    // Obtener prestamistas para filtro
    $prestMap = [];
    $resPL = $conn->query("SELECT DISTINCT prestamista FROM prestamos WHERE $whereBase ORDER BY prestamista");
    while($rowPL = $resPL->fetch_row()) {
        $norm = mbnorm($rowPL[0]);
        if ($norm === '') continue;
        $prestMap[$norm] = $rowPL[0];
    }
    
    // Obtener empresas para filtro
    $empMap = [];
    $resEL = $conn->query("SELECT DISTINCT empresa FROM prestamos WHERE $whereBase AND empresa != '' ORDER BY empresa");
    while($rowEL = $resEL->fetch_row()) {
        $norm = mbnorm($rowEL[0]);
        if ($norm === '') continue;
        $empMap[$norm] = $rowEL[0];
    }
    
    // Obtener deudores para filtro
    $deudMap = [];
    $sqlDeud = "SELECT DISTINCT deudor FROM prestamos WHERE $whereBase ORDER BY deudor";
    $resDeud = $conn->query($sqlDeud);
    while($rowDL = $resDeud->fetch_row()) {
        $norm = mbnorm($rowDL[0]);
        if ($norm === '') continue;
        $deudMap[$norm] = $rowDL[0];
    }
    
    // Consulta principal - OBTENER TODOS LOS DATOS PARA CÁLCULOS EN JAVASCRIPT
    $sql = "SELECT id, deudor, prestamista, monto, fecha, imagen, created_at, pagado, pagado_at, empresa,
                   comision_gestor_nombre, comision_gestor_porcentaje, comision_base_monto,
                   comision_origen_prestamista, comision_origen_porcentaje
            FROM prestamos 
            WHERE $where 
            ORDER BY pagado ASC, id DESC";
    
    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rs = $stmt->get_result();
    
    // Guardar todos los préstamos en un array para JavaScript
    $prestamosData = [];
    while ($row = $rs->fetch_assoc()) {
        $prestamosData[] = $row;
    }
    
    // Resetear el puntero para mostrarlos
    $rs = $stmt->get_result(); // Esto no funciona, mejor guardar los datos y mostrar desde el array
    // Solución: Guardar los datos y luego mostrarlos desde el array
    $rs = $stmt->get_result(); // Esto no funciona después de fetch_assoc, mejor usar el array
    
    // Cerrar y reabrir para mostrar
    $stmt->close();
    
    // Volver a ejecutar para mostrar
    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rs = $stmt->get_result();
?>
    <!-- Toolbar de filtros -->
    <div class="card" style="margin-bottom:16px">
        <form class="toolbar" method="get" id="filtroForm">
            <input type="hidden" name="view" value="cards">
            <input name="q" placeholder="🔎 Buscar (deudor / prestamista)" value="<?= h($q) ?>" style="flex:1;min-width:220px">
            
            <div class="field" style="min-width:200px;flex:1">
                <label>Prestamista</label>
                <select name="fp" class="select2-filter">
                    <option value="">Todos</option>
                    <?php foreach($prestMap as $norm=>$label): ?>
                        <option value="<?= h($norm) ?>" <?= $fpNorm===$norm?'selected':'' ?>><?= h(mbtitle($label)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="field" style="min-width:200px;flex:1">
                <label>Empresa</label>
                <select name="fe" class="select2-filter">
                    <option value="">Todas</option>
                    <?php foreach($empMap as $norm=>$label): ?>
                        <option value="<?= h($norm) ?>" <?= $feNorm===$norm?'selected':'' ?>><?= h(mbtitle($label)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="field" style="min-width:200px;flex:1">
                <label>Deudor</label>
                <select name="fd" class="select2-filter">
                    <option value="">Todos</option>
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
            
            <div class="switch-container" style="display:flex; gap:8px; align-items:center; background:#f8f9fa; padding:8px; border-radius:12px;">
                <span>Estado:</span>
                <label><input type="radio" name="estado_pago" value="no_pagados" <?= $estado_pago==='no_pagados'?'checked':'' ?> onchange="this.form.submit()"> No pagados</label>
                <label><input type="radio" name="estado_pago" value="pagados" <?= $estado_pago==='pagados'?'checked':'' ?> onchange="this.form.submit()"> Pagados</label>
                <label><input type="radio" name="estado_pago" value="todos" <?= $estado_pago==='todos'?'checked':'' ?> onchange="this.form.submit()"> Todos</label>
            </div>
            
            <button class="btn" type="submit">Filtrar</button>
            <?php if ($q!=='' || $fpNorm!=='' || $fdNorm!=='' || $feNorm!=='' || $fecha_desde!=='' || $fecha_hasta!=='' || $estado_pago!=='no_pagados'): ?>
                <a class="btn gray" href="?view=cards">Quitar filtro</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($rs->num_rows === 0): ?>
        <div class="card"><span class="subtitle">(sin registros)</span></div>
    <?php else: ?>
        <!-- Panel de pago inteligente -->
        <div class="card" style="margin-bottom:16px; background:#f0f9ff; border:1px solid #bae6fd;">
            <div class="row">
                <div class="title">💰 Pago Inteligente</div>
                <div class="subtitle">Selecciona préstamos y aplica pago con reestructuración automática</div>
            </div>
            <div class="row" style="gap:12px; margin-top:10px;">
                <div class="field" style="min-width:200px;">
                    <label>Monto a pagar</label>
                    <input type="number" id="montoPago" placeholder="Ej: 4000000" min="0" step="1000">
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
                    <button class="btn" id="btnVistaPrevia" onclick="vistaPreviaPago()">🔍 Vista Previa</button>
                </div>
            </div>
            <div class="subtitle" style="margin-top:8px;">
                <span id="selectedCount">0</span> préstamos seleccionados
            </div>
        </div>

        <!-- Listado de tarjetas -->
        <div class="grid-cards">
            <?php while($r = $rs->fetch_assoc()): 
                // Calcular meses e interés para mostrar
                $meses = calcularMeses($r['fecha']);
                $tasaOrigen = $r['comision_origen_porcentaje'] ?? getTasaInteres($r['fecha']);
                $interesOrigen = $r['monto'] * ($tasaOrigen / 100) * $meses;
                $baseComision = $r['comision_base_monto'] ?? $r['monto'];
                $interesGestor = $baseComision * (($r['comision_gestor_porcentaje'] ?? 0) / 100) * $meses;
                $interesTotal = $interesOrigen + $interesGestor;
                $total = $r['monto'] + $interesTotal;
                
                $esComision = !empty($r['comision_gestor_nombre']);
                $esPagado = (bool)($r['pagado'] ?? false);
                
                $cardClass = '';
                if ($esComision) $cardClass = 'card-comision';
                elseif ($esPagado) $cardClass = 'card-pagado';
            ?>
                <div class="card <?= $cardClass ?>" data-id="<?= $r['id'] ?>" data-monto="<?= $r['monto'] ?>" data-fecha="<?= $r['fecha'] ?>" data-tasaorigen="<?= $tasaOrigen ?>" data-tasagestor="<?= $r['comision_gestor_porcentaje'] ?? 0 ?>" data-basemonto="<?= $r['comision_base_monto'] ?? $r['monto'] ?>" data-deudor="<?= h($r['deudor']) ?>" data-prestamista="<?= h($r['prestamista']) ?>" data-pagado="<?= $esPagado ? '1' : '0' ?>">
                    <div class="cardSel">
                        <?php if (!$esPagado): ?>
                            <input class="chkRow" type="checkbox" value="<?= (int)$r['id'] ?>" onchange="actualizarContadorSeleccionados()">
                        <?php else: ?>
                            <span style="width:20px;"></span>
                        <?php endif; ?>
                        <div class="subtitle">#<?= h($r['id']) ?></div>
                        <?php if ($esComision): ?>
                            <span class="chip comision-badge" style="margin-left:auto">💰 Comisión</span>
                        <?php elseif ($esPagado): ?>
                            <span class="chip pagado-badge" style="margin-left:auto">✅ Pagado</span>
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
                                <div class="subtitle" style="color:#065f46;">Pagado: <?= h($r['pagado_at']) ?></div>
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
                            <div class="k">Meses</div>
                            <div class="v"><?= $meses ?></div>
                        </div>
                        <div class="item">
                            <div class="k">Interés</div>
                            <div class="v">$ <?= money($interesTotal) ?></div>
                        </div>
                        <div class="item">
                            <div class="k">Total</div>
                            <div class="v">$ <?= money($total) ?></div>
                        </div>
                    </div>

                    <div class="row" style="margin-top:12px">
                        <div class="subtitle"><?= h($r['created_at']) ?></div>
                        <div style="display:flex;gap:8px;">
                            <a class="btn gray small" href="?action=edit&id=<?= $r['id'] ?>&view=cards">✏️</a>
                            <button class="btn red small" type="button" onclick="submitDelete(<?= (int)$r['id'] ?>)">🗑️</button>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
<?php
    $stmt->close();
    $conn->close();
endif;
?>

<!-- jQuery y Select2 -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// Inicializar Select2
$(document).ready(function() {
    $('.select2-filter').select2({ width: '100%', placeholder: 'Seleccionar...', allowClear: true });
});

// Variables globales
let ultimaVistaPrevia = null;

// Función para calcular meses entre dos fechas
function calcularMeses(fechaString) {
    const fecha = new Date(fechaString);
    const hoy = new Date();
    if (hoy < fecha) return 0;
    
    let meses = (hoy.getFullYear() - fecha.getFullYear()) * 12;
    meses += hoy.getMonth() - fecha.getMonth();
    
    // Si el día actual es menor que el día de la fecha, restamos un mes
    if (hoy.getDate() < fecha.getDate()) {
        meses--;
    }
    
    return meses + 1; // +1 porque el primer mes cuenta
}

// Función para calcular total de un préstamo
function calcularTotalPrestamo(monto, fecha, tasaOrigen, tasaGestor, baseMonto) {
    const meses = calcularMeses(fecha);
    const interesOrigen = monto * (tasaOrigen / 100) * meses;
    const baseComision = baseMonto || monto;
    const interesGestor = baseComision * (tasaGestor / 100) * meses;
    const interesTotal = interesOrigen + interesGestor;
    const total = monto + interesTotal;
    
    return {
        meses: meses,
        interesOrigen: interesOrigen,
        interesGestor: interesGestor,
        interesTotal: interesTotal,
        total: total
    };
}

// Actualizar contador de seleccionados
function actualizarContadorSeleccionados() {
    const checks = document.querySelectorAll('.chkRow:checked');
    document.getElementById('selectedCount').textContent = checks.length;
}

// Vista previa de pago (SIN AJAX)
function vistaPreviaPago() {
    const checks = document.querySelectorAll('.chkRow:checked');
    const montoPago = parseFloat(document.getElementById('montoPago').value);
    const orden = document.getElementById('ordenPago').value;
    
    if (checks.length === 0) {
        alert('Selecciona al menos un préstamo');
        return;
    }
    if (!montoPago || montoPago <= 0) {
        alert('Ingresa un monto válido');
        return;
    }
    
    // Obtener datos de los préstamos seleccionados
    const prestamos = [];
    checks.forEach(check => {
        const card = check.closest('.card');
        prestamos.push({
            id: parseInt(card.dataset.id),
            deudor: card.dataset.deudor,
            prestamista: card.dataset.prestamista,
            monto: parseFloat(card.dataset.monto),
            fecha: card.dataset.fecha,
            tasaOrigen: parseFloat(card.dataset.tasaorigen),
            tasaGestor: parseFloat(card.dataset.tasagestor),
            baseMonto: parseFloat(card.dataset.basemonto)
        });
    });
    
    // Calcular totales para cada préstamo
    prestamos.forEach(p => {
        const calc = calcularTotalPrestamo(p.monto, p.fecha, p.tasaOrigen, p.tasaGestor, p.baseMonto);
        p.meses = calc.meses;
        p.interesTotal = calc.interesTotal;
        p.total = calc.total;
    });
    
    // Ordenar según criterio
    if (orden === 'antiguo') {
        prestamos.sort((a, b) => new Date(a.fecha) - new Date(b.fecha));
    } else if (orden === 'reciente') {
        prestamos.sort((a, b) => new Date(b.fecha) - new Date(a.fecha));
    } else if (orden === 'mayor_interes') {
        prestamos.sort((a, b) => b.interesTotal - a.interesTotal);
    }
    
    // Procesar pago
    let restante = montoPago;
    const resultado = [];
    const reestructurar = [];
    
    for (let p of prestamos) {
        if (restante <= 0) {
            resultado.push({
                id: p.id,
                deudor: p.deudor,
                monto_original: p.monto,
                total_original: p.total,
                accion: 'no_tocado',
                mensaje: 'No alcanzó para este préstamo'
            });
            continue;
        }
        
        if (restante >= p.total) {
            // Pagar completo
            resultado.push({
                id: p.id,
                deudor: p.deudor,
                monto_original: p.monto,
                interes_pagado: p.interesTotal,
                abono_capital: p.monto,
                total_pagado: p.total,
                accion: 'pagado_completo',
                mensaje: 'Préstamo pagado completamente'
            });
            restante -= p.total;
        } else {
            // Pago parcial - reestructurar
            if (restante >= p.interesTotal) {
                // Alcanza para pagar todos los intereses
                const interesPagado = p.interesTotal;
                const abonoCapital = restante - interesPagado;
                const nuevoCapital = p.monto - abonoCapital;
                
                resultado.push({
                    id: p.id,
                    deudor: p.deudor,
                    monto_original: p.monto,
                    interes_pagado: interesPagado,
                    abono_capital: abonoCapital,
                    nuevo_capital: nuevoCapital,
                    accion: 'reestructurar',
                    mensaje: `Se pagan intereses ($${formatMoney(interesPagado)}) y se abonan $${formatMoney(abonoCapital)} a capital. Nuevo préstamo: $${formatMoney(nuevoCapital)}`
                });
                
                reestructurar.push({
                    original_id: p.id,
                    nuevo_capital: nuevoCapital,
                    deudor: p.deudor,
                    prestamista: p.prestamista,
                    tasa_origen: p.tasaOrigen,
                    tasa_gestor: p.tasaGestor
                });
            } else {
                // No alcanza ni para intereses
                resultado.push({
                    id: p.id,
                    deudor: p.deudor,
                    monto_original: p.monto,
                    interes_pagado: restante,
                    abono_capital: 0,
                    accion: 'pago_parcial_intereses',
                    mensaje: `Pago parcial de intereses: $${formatMoney(restante)}`
                });
            }
            restante = 0;
            break;
        }
    }
    
    ultimaVistaPrevia = {
        monto_pago: montoPago,
        restante: restante,
        resultado: resultado,
        reestructurar: reestructurar,
        total_procesado: montoPago - restante
    };
    
    // Mostrar vista previa
    mostrarVistaPrevia(ultimaVistaPrevia);
}

// Formatear moneda
function formatMoney(n) {
    return new Intl.NumberFormat('es-CO').format(Math.round(n));
}

// Mostrar vista previa en el modal
function mostrarVistaPrevia(data) {
    let html = `
        <div style="margin-bottom:16px; background:#e8f7ee; padding:12px; border-radius:8px;">
            <strong>💰 Monto a pagar:</strong> $${formatMoney(data.monto_pago)}<br>
            <strong>💵 Total a procesar:</strong> $${formatMoney(data.total_procesado)}<br>
            <strong>🔄 Restante:</strong> $${formatMoney(data.restante)}
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
        } else {
            badge = '<span class="badge-pill info">⏳ NO TOCADO</span>';
        }
        
        html += `<div class="${clase}">`;
        html += `<div style="display:flex; justify-content:space-between; margin-bottom:8px;">`;
        html += `<strong>#${item.id} - ${item.deudor}</strong> ${badge}`;
        html += `</div>`;
        
        if (item.accion === 'pagado_completo') {
            html += `<div>💰 Capital: $${formatMoney(item.monto_original)}</div>`;
            html += `<div>💵 Interés pagado: $${formatMoney(item.interes_pagado)}</div>`;
            html += `<div>✅ Total pagado: $${formatMoney(item.total_pagado)}</div>`;
        } else if (item.accion === 'reestructurar') {
            html += `<div>💰 Capital original: $${formatMoney(item.monto_original)}</div>`;
            html += `<div>💵 Interés pagado: $${formatMoney(item.interes_pagado)}</div>`;
            html += `<div>📉 Abono a capital: $${formatMoney(item.abono_capital)}</div>`;
            html += `<div style="background:#f59e0b20; padding:8px; border-radius:6px; margin-top:8px;">`;
            html += `<strong>🆕 NUEVO PRÉSTAMO:</strong> $${formatMoney(item.nuevo_capital)} (hoy)`;
            html += `</div>`;
        } else {
            html += `<div>⏳ Este préstamo no se modificó</div>`;
        }
        
        html += `<div class="subtitle" style="margin-top:8px;">${item.mensaje}</div>`;
        html += `</div>`;
    });
    
    document.getElementById('previewContent').innerHTML = html;
    document.getElementById('modalPreview').style.display = 'block';
}

// Confirmar pago (enviar al servidor)
document.getElementById('btnConfirmarPago')?.addEventListener('click', function() {
    if (!ultimaVistaPrevia) {
        alert('No hay vista previa para confirmar');
        return;
    }
    
    if (!confirm('¿Confirmar el pago? Esta acción no se puede deshacer.')) {
        return;
    }
    
    fetch('?action=procesar_pago', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            resultado: ultimaVistaPrevia.resultado,
            reestructurar: ultimaVistaPrevia.reestructurar
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
function submitDelete(id) {
    if (!confirm('¿Eliminar préstamo #' + id + '?')) return;
    const f = document.createElement('form');
    f.method = 'post';
    f.action = '?action=delete&id=' + id;
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

</body>
</html>