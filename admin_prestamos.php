<?php
/*********************************************************
 * admin_prestamos.php — CRUD + Tarjetas + Pago Inteligente
 *********************************************************/
include("nav.php");

// ======= CONFIG =======
define('DB_HOST', 'mysql.hostinger.com');
define('DB_USER', 'u648222299_keboco5');
define('DB_PASS', 'Bucaramanga3011');
define('DB_NAME', 'u648222299_viajes');
const UPLOAD_DIR = __DIR__ . '/uploads/';
const BASE_URL = 'https://asociacion.asociaciondetransportistaszonanorte.io/tele/admin_prestamos.php';

if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0775, true);

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

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// ===== CRUD =====
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
        go('?msg=creado');
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
        go('?msg=editado');
    }
}

if ($action==='delete' && $_SERVER['REQUEST_METHOD']==='POST' && $id>0){
    $c=db();
    $st=$c->prepare("DELETE FROM prestamos WHERE id=?");
    $st->bind_param("i",$id);
    $st->execute();
    $st->close(); $c->close();
    go('?msg=eliminado');
}

// ===== Procesar pago =====
if ($action==='procesar_pago' && $_SERVER['REQUEST_METHOD']==='POST'){
    $data = json_decode(file_get_contents('php://input'), true);
    $reestructurar = $data['reestructurar'] ?? [];
    
    $conn = db();
    $conn->begin_transaction();
    
    try {
        foreach ($reestructurar as $r) {
            // Crear nuevo préstamo
            $stmt = $conn->prepare("INSERT INTO prestamos 
                (deudor, prestamista, monto, fecha, created_at, empresa, 
                 comision_origen_porcentaje, comision_gestor_porcentaje, nota) 
                VALUES (?, ?, ?, CURDATE(), NOW(), '', ?, ?, 'Reestructuración')");
            $stmt->bind_param("ssddi", 
                $r['deudor'], $r['prestamista'], $r['nuevo_capital'], 
                $r['tasa_origen'], $r['tasa_gestor']
            );
            $stmt->execute();
            $stmt->close();
        }
        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['error' => $e->getMessage()]);
    }
    $conn->close();
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Préstamos</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        *{box-sizing:border-box; margin:0; padding:0;}
        body{font-family:Arial,sans-serif; background:#f5f5f5; padding:20px;}
        .btn{display:inline-block; padding:8px 15px; background:#0d6efd; color:white; text-decoration:none; border-radius:5px; border:none; cursor:pointer;}
        .btn.gray{background:#6c757d;}
        .btn.red{background:#dc3545;}
        .btn.small{padding:4px 8px; font-size:12px;}
        .tabs{margin-bottom:20px;}
        .tabs a{display:inline-block; padding:8px 15px; background:#e9ecef; color:#333; text-decoration:none; border-radius:5px; margin-right:5px;}
        .tabs a.active{background:#0d6efd; color:white;}
        .toolbar{background:white; padding:15px; border-radius:8px; margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap;}
        .toolbar input, .toolbar select{padding:8px; border:1px solid #ddd; border-radius:4px; min-width:200px;}
        .grid-cards{display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:15px;}
        .card{background:white; border-radius:8px; padding:15px; box-shadow:0 2px 5px rgba(0,0,0,0.1);}
        .card.pagado{opacity:0.7; background:#f0fdf4;}
        .card-header{display:flex; justify-content:space-between; margin-bottom:10px;}
        .card-header input{margin-right:10px;}
        .badge{padding:3px 8px; border-radius:12px; font-size:11px; background:#e9ecef;}
        .badge.success{background:#198754; color:white;}
        .pairs{display:grid; grid-template-columns:1fr 1fr; gap:10px; margin:10px 0;}
        .pairs div{background:#f8f9fa; padding:8px; border-radius:4px;}
        .pairs .k{font-size:11px; color:#666;}
        .pairs .v{font-size:16px; font-weight:bold;}
        .modal{display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5);}
        .modal-content{background:white; margin:50px auto; padding:20px; width:90%; max-width:600px; border-radius:8px; max-height:80vh; overflow-y:auto;}
        .close{float:right; font-size:24px; cursor:pointer;}
        .msg{background:#d4edda; color:#155724; padding:10px; border-radius:4px; margin-bottom:15px;}
    </style>
</head>
<body>

<div class="tabs">
    <a href="?view=cards" class="active">📇 Tarjetas</a>
    <a href="?action=new" class="btn gray" style="margin-left:auto;">➕ Crear</a>
</div>

<?php if(isset($_GET['msg'])): ?>
    <div class="msg">
        <?php 
        $msg = $_GET['msg'];
        if($msg=='creado') echo 'Creado correctamente';
        elseif($msg=='editado') echo 'Editado correctamente';
        elseif($msg=='eliminado') echo 'Eliminado correctamente';
        ?>
    </div>
<?php endif; ?>

<!-- MODAL VISTA PREVIA -->
<div id="modalPreview" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('modalPreview').style.display='none'">&times;</span>
        <h2>📋 Vista Previa</h2>
        <div id="previewContent"></div>
        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
            <button class="btn gray" onclick="document.getElementById('modalPreview').style.display='none'">Cancelar</button>
            <button class="btn" id="btnConfirmar">Confirmar</button>
        </div>
    </div>
</div>

<?php
// FORMULARIOS
if($action==='new' || ($action==='edit' && $id>0)):
    $row = ['deudor'=>'','prestamista'=>'','monto'=>'','fecha'=>'','empresa'=>''];
    if($action==='edit'){
        $c=db();
        $st=$c->prepare("SELECT deudor,prestamista,monto,fecha,empresa FROM prestamos WHERE id=?");
        $st->bind_param("i",$id);
        $st->execute();
        $res=$st->get_result();
        $row=$res->fetch_assoc() ?: $row;
        $st->close(); $c->close();
    }
?>
    <div class="card">
        <h3><?= $action==='new'?'Nuevo':'Editar' ?> Préstamo</h3>
        <form method="post">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div><label>Deudor</label><input name="deudor" value="<?=h($row['deudor'])?>" required class="full" style="width:100%; padding:8px;"></div>
                <div><label>Prestamista</label><input name="prestamista" value="<?=h($row['prestamista'])?>" required style="width:100%; padding:8px;"></div>
                <div><label>Monto</label><input name="monto" type="number" value="<?=h($row['monto'])?>" required style="width:100%; padding:8px;"></div>
                <div><label>Fecha</label><input name="fecha" type="date" value="<?=h($row['fecha'])?>" required style="width:100%; padding:8px;"></div>
                <div><label>Empresa</label><input name="empresa" value="<?=h($row['empresa'])?>" style="width:100%; padding:8px;"></div>
            </div>
            <div style="margin-top:15px;">
                <button class="btn" type="submit">Guardar</button>
                <a href="?" class="btn gray">Cancelar</a>
            </div>
        </form>
    </div>
<?php else: 
    // FILTROS
    $q = $_GET['q'] ?? '';
    $fp = $_GET['fp'] ?? '';
    $fd = $_GET['fd'] ?? '';
    $fe = $_GET['fe'] ?? '';
    $estado = $_GET['estado'] ?? 'activos';
    
    $conn = db();
    $where = $estado=='activos' ? 'pagado=0' : ($estado=='pagados' ? 'pagado=1' : '1=1');
    
    if($q) $where .= " AND (deudor LIKE '%$q%' OR prestamista LIKE '%$q%')";
    if($fp) $where .= " AND prestamista='$fp'";
    if($fd) $where .= " AND deudor='$fd'";
    if($fe) $where .= " AND empresa='$fe'";
    
    $sql = "SELECT * FROM prestamos WHERE $where ORDER BY id DESC";
    $res = $conn->query($sql);
    
    // Datos para filtros
    $prestamistas = $conn->query("SELECT DISTINCT prestamista FROM prestamos");
    $empresas = $conn->query("SELECT DISTINCT empresa FROM prestamos WHERE empresa!=''");
    $deudores = $conn->query("SELECT DISTINCT deudor FROM prestamos");
?>
    <!-- FILTROS -->
    <div class="toolbar">
        <form method="get" style="display:flex; gap:10px; flex-wrap:wrap; width:100%;">
            <input type="text" name="q" placeholder="Buscar..." value="<?=h($q)?>" style="flex:1;">
            <select name="fp">
                <option value="">Prestamista</option>
                <?php while($p=$prestamistas->fetch_row()): ?>
                    <option value="<?=h($p[0])?>" <?=$fp==$p[0]?'selected':''?>><?=h($p[0])?></option>
                <?php endwhile; ?>
            </select>
            <select name="fe">
                <option value="">Empresa</option>
                <?php while($e=$empresas->fetch_row()): ?>
                    <option value="<?=h($e[0])?>" <?=$fe==$e[0]?'selected':''?>><?=h($e[0])?></option>
                <?php endwhile; ?>
            </select>
            <select name="fd">
                <option value="">Deudor</option>
                <?php while($d=$deudores->fetch_row()): ?>
                    <option value="<?=h($d[0])?>" <?=$fd==$d[0]?'selected':''?>><?=h($d[0])?></option>
                <?php endwhile; ?>
            </select>
            <select name="estado">
                <option value="activos" <?=$estado=='activos'?'selected':''?>>Activos</option>
                <option value="pagados" <?=$estado=='pagados'?'selected':''?>>Pagados</option>
                <option value="todos" <?=$estado=='todos'?'selected':''?>>Todos</option>
            </select>
            <button class="btn" type="submit">Filtrar</button>
            <a href="?" class="btn gray">Limpiar</a>
        </form>
    </div>

    <!-- PANEL PAGO INTELIGENTE -->
    <div class="card" style="margin-bottom:15px; background:#e3f2fd;">
        <div style="display:flex; gap:15px; align-items:center; flex-wrap:wrap;">
            <h3 style="margin:0;">💰 Pago Inteligente</h3>
            <input type="number" id="montoPago" placeholder="Monto a pagar" style="padding:8px; width:200px;">
            <select id="ordenPago" style="padding:8px;">
                <option value="antiguo">Más antiguos</option>
                <option value="reciente">Más recientes</option>
            </select>
            <button class="btn" onclick="vistaPrevia()">🔍 Vista Previa</button>
            <span>Seleccionados: <span id="contador">0</span></span>
        </div>
    </div>

    <!-- TARJETAS -->
    <div class="grid-cards">
        <?php while($r = $res->fetch_assoc()): 
            $meses = calcularMeses($r['fecha']);
            $tasa = $r['comision_origen_porcentaje'] ?? getTasaInteres($r['fecha']);
            $interes = $r['monto'] * ($tasa/100) * $meses;
            $total = $r['monto'] + $interes;
            $pagado = $r['pagado'] ? true : false;
        ?>
            <div class="card <?= $pagado?'pagado':'' ?>" 
                 data-id="<?= $r['id'] ?>"
                 data-deudor="<?= h($r['deudor']) ?>"
                 data-prestamista="<?= h($r['prestamista']) ?>"
                 data-monto="<?= $r['monto'] ?>"
                 data-fecha="<?= $r['fecha'] ?>"
                 data-tasa="<?= $tasa ?>"
                 data-interes="<?= $interes ?>"
                 data-total="<?= $total ?>"
                 data-pagado="<?= $pagado?'1':'0' ?>">
                
                <div class="card-header">
                    <div>
                        <?php if(!$pagado): ?>
                            <input type="checkbox" class="chkRow" value="<?= $r['id'] ?>" onchange="actualizarContador()">
                        <?php endif; ?>
                        <strong>#<?= $r['id'] ?> - <?= h($r['deudor']) ?></strong>
                    </div>
                    <?php if($pagado): ?>
                        <span class="badge success">Pagado</span>
                    <?php endif; ?>
                </div>
                
                <div style="margin:5px 0; color:#666;">
                    Prestamista: <?= h($r['prestamista']) ?><br>
                    Fecha: <?= $r['fecha'] ?>
                </div>
                
                <div class="pairs">
                    <div><span class="k">Capital</span><span class="v">$<?= money($r['monto']) ?></span></div>
                    <div><span class="k">Meses</span><span class="v"><?= $meses ?></span></div>
                    <div><span class="k">Interés</span><span class="v">$<?= money($interes) ?></span></div>
                    <div><span class="k">Total</span><span class="v">$<?= money($total) ?></span></div>
                </div>
                
                <div style="display:flex; gap:5px; margin-top:10px;">
                    <a href="?action=edit&id=<?= $r['id'] ?>" class="btn small gray">✏️</a>
                    <button onclick="eliminar(<?= $r['id'] ?>)" class="btn small red">🗑️</button>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php 
    $conn->close();
endif; 
?>

<script>
// Variables
let vistaPreviaData = null;

// Contador de seleccionados
function actualizarContador() {
    document.getElementById('contador').textContent = document.querySelectorAll('.chkRow:checked').length;
}

// Calcular meses
function calcularMeses(fecha) {
    let f = new Date(fecha);
    let h = new Date();
    if (h < f) return 0;
    let meses = (h.getFullYear() - f.getFullYear()) * 12;
    meses += h.getMonth() - f.getMonth();
    return meses + 1;
}

// Vista previa
function vistaPrevia() {
    let checks = document.querySelectorAll('.chkRow:checked');
    let monto = parseFloat(document.getElementById('montoPago').value);
    let orden = document.getElementById('ordenPago').value;
    
    if (checks.length === 0) { alert('Selecciona préstamos'); return; }
    if (!monto || monto <= 0) { alert('Ingresa monto válido'); return; }
    
    // Obtener datos
    let prestamos = [];
    checks.forEach(c => {
        let card = c.closest('.card');
        prestamos.push({
            id: card.dataset.id,
            deudor: card.dataset.deudor,
            prestamista: card.dataset.prestamista,
            monto: parseFloat(card.dataset.monto),
            fecha: card.dataset.fecha,
            tasa: parseFloat(card.dataset.tasa),
            interes: parseFloat(card.dataset.interes),
            total: parseFloat(card.dataset.total)
        });
    });
    
    // Ordenar
    if (orden === 'antiguo') {
        prestamos.sort((a,b) => new Date(a.fecha) - new Date(b.fecha));
    } else {
        prestamos.sort((a,b) => new Date(b.fecha) - new Date(a.fecha));
    }
    
    // Procesar pago
    let restante = monto;
    let resultado = [];
    let reestructurar = [];
    
    for (let p of prestamos) {
        if (restante <= 0) break;
        
        if (restante >= p.total) {
            resultado.push({
                id: p.id,
                deudor: p.deudor,
                total: p.total,
                accion: 'completo'
            });
            restante -= p.total;
        } else {
            if (restante >= p.interes) {
                let nuevoCapital = p.monto - (restante - p.interes);
                resultado.push({
                    id: p.id,
                    deudor: p.deudor,
                    interes: p.interes,
                    abono: restante - p.interes,
                    nuevo: nuevoCapital,
                    accion: 'reestructurar'
                });
                reestructurar.push({
                    original_id: p.id,
                    nuevo_capital: nuevoCapital,
                    deudor: p.deudor,
                    prestamista: p.prestamista,
                    tasa_origen: p.tasa,
                    tasa_gestor: 0
                });
            } else {
                resultado.push({
                    id: p.id,
                    deudor: p.deudor,
                    parcial: restante,
                    accion: 'parcial'
                });
            }
            restante = 0;
            break;
        }
    }
    
    vistaPreviaData = { resultado, reestructurar };
    
    // Mostrar modal
    let html = `<p><strong>Monto:</strong> $${monto.toLocaleString('es-CO')}<br>`;
    html += `<strong>Restante:</strong> $${restante.toLocaleString('es-CO')}</p>`;
    
    resultado.forEach(r => {
        html += `<div style="border-left:4px solid #0d6efd; padding:10px; margin:10px 0; background:#f8f9fa;">`;
        html += `<strong>#${r.id} - ${r.deudor}</strong><br>`;
        
        if (r.accion === 'completo') {
            html += `✅ PAGADO COMPLETO - $${r.total.toLocaleString('es-CO')}`;
        } else if (r.accion === 'reestructurar') {
            html += `🔄 REESTRUCTURAR<br>`;
            html += `Interés: $${r.interes.toLocaleString('es-CO')}<br>`;
            html += `Abono: $${r.abono.toLocaleString('es-CO')}<br>`;
            html += `<strong>Nuevo préstamo: $${r.nuevo.toLocaleString('es-CO')}</strong>`;
        } else {
            html += `⏳ Pago parcial: $${r.parcial.toLocaleString('es-CO')}`;
        }
        html += `</div>`;
    });
    
    document.getElementById('previewContent').innerHTML = html;
    document.getElementById('modalPreview').style.display = 'block';
}

// Confirmar pago
document.getElementById('btnConfirmar')?.addEventListener('click', function() {
    if (!vistaPreviaData) return;
    
    fetch('?action=procesar_pago', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(vistaPreviaData)
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            alert('Pago procesado');
            location.reload();
        } else {
            alert('Error: ' + d.error);
        }
    });
});

// Eliminar
function eliminar(id) {
    if (!confirm('¿Eliminar?')) return;
    let f = document.createElement('form');
    f.method = 'post';
    f.action = '?action=delete&id=' + id;
    document.body.appendChild(f);
    f.submit();
}
</script>

</body>
</html>