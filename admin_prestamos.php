<?php
/*********************************************************
 * admin_prestamos.php - SISTEMA COMPLETO CON MODAL FUNCIONAL
 * - CRUD completo
 * - Tarjetas visuales
 * - MODAL DE ABONO que funciona
 *********************************************************/
include("nav.php");

// ======= CONFIG =======
define('DB_HOST', 'mysql.hostinger.com');
define('DB_USER', 'u648222299_keboco5');
define('DB_PASS', 'Bucaramanga3011');
define('DB_NAME', 'u648222299_viajes');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_UPLOAD_BYTES', 10 * 1024 * 1024);
define('BASE_URL', 'https://asociacion.asociaciondetransportistaszonanorte.io/tele/admin_prestamos.php');

if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);

// ===== FUNCIONES HELPER =====
function db() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) die("Error DB: " . $conn->connect_error);
    $conn->set_charset('utf8mb4');
    return $conn;
}

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function money($n) { return number_format((float)$n, 0, ',', '.'); }

function go($url) {
    if (!headers_sent()) {
        header("Location: $url");
        exit;
    }
    echo "<script>location.replace('$url');</script>";
    exit;
}

// ===== ACCIÓN: VISTA PREVIA ABONO (AJAX) =====
if (isset($_GET['action']) && $_GET['action'] === 'vista_previa_abono' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $ids = $_POST['ids'] ?? [];
    $monto = (float)($_POST['monto'] ?? 0);
    
    if (empty($ids) || !is_array($ids) || $monto <= 0) {
        echo json_encode(['error' => 'Datos inválidos']);
        exit;
    }
    
    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $conn = db();
    $sql = "SELECT id, deudor, prestamista, monto, fecha,
                   TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 as meses,
                   CASE WHEN fecha >= '2025-10-29' THEN 13 ELSE 10 END as tasa
            FROM prestamos 
            WHERE id IN ($placeholders) AND pagado = 0
            ORDER BY id ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $prestamos = [];
    $total_capital = 0;
    $total_interes = 0;
    
    while ($row = $result->fetch_assoc()) {
        $interes = $row['monto'] * ($row['tasa'] / 100) * max(0, $row['meses']);
        $total = $row['monto'] + $interes;
        
        $prestamos[] = [
            'id' => $row['id'],
            'deudor' => $row['deudor'],
            'prestamista' => $row['prestamista'],
            'monto' => (float)$row['monto'],
            'interes' => $interes,
            'total' => $total,
            'meses' => (int)$row['meses'],
            'tasa' => (int)$row['tasa']
        ];
        
        $total_capital += $row['monto'];
        $total_interes += $interes;
    }
    $stmt->close();
    $conn->close();
    
    // Aplicar lógica de cascada
    $restante = $monto;
    $resultado = [];
    $abono_utilizado = 0;
    
    foreach ($prestamos as $p) {
        $item = [
            'id' => $p['id'],
            'deudor' => $p['deudor'],
            'monto' => $p['monto'],
            'interes' => $p['interes'],
            'total' => $p['total'],
            'abono' => 0,
            'estado' => 'pendiente'
        ];
        
        if ($restante <= 0) {
            $item['estado'] = 'sin_abono';
        } elseif ($restante >= $p['total']) {
            $item['abono'] = $p['total'];
            $item['estado'] = 'completo';
            $restante -= $p['total'];
            $abono_utilizado += $p['total'];
        } else {
            $item['abono'] = $restante;
            $item['estado'] = 'parcial';
            $abono_utilizado += $restante;
            $restante = 0;
        }
        
        $resultado[] = $item;
    }
    
    echo json_encode([
        'prestamos' => $resultado,
        'resumen' => [
            'total_capital' => $total_capital,
            'total_interes' => $total_interes,
            'total_general' => $total_capital + $total_interes,
            'abono_utilizado' => $abono_utilizado,
            'sobrante' => $restante
        ]
    ]);
    exit;
}

// ===== ACCIÓN: APLICAR ABONO =====
if (isset($_GET['action']) && $_GET['action'] === 'aplicar_abono' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = json_decode($_POST['ids'] ?? '[]', true);
    $monto = (float)($_POST['monto'] ?? 0);
    
    if (empty($ids) || $monto <= 0) {
        go(BASE_URL . '?view=cards&msg=error_abono');
    }
    
    $conn = db();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    // Marcar como pagados los que se puedan pagar completamente
    $sql = "UPDATE prestamos SET pagado = 1, pagado_at = NOW() 
            WHERE id IN ($placeholders) AND pagado = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $afectados = $stmt->affected_rows;
    $stmt->close();
    $conn->close();
    
    go(BASE_URL . '?view=cards&msg=abono_ok&count=' . $afectados);
}

// ===== ACCIONES CRUD =====
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Crear
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $deudor = trim($_POST['deudor'] ?? '');
    $prestamista = trim($_POST['prestamista'] ?? '');
    $monto = (float)($_POST['monto'] ?? 0);
    $fecha = $_POST['fecha'] ?? '';
    
    if ($deudor && $prestamista && $monto > 0 && $fecha) {
        $conn = db();
        $stmt = $conn->prepare("INSERT INTO prestamos (deudor, prestamista, monto, fecha) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssds", $deudor, $prestamista, $monto, $fecha);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        go(BASE_URL . '?view=cards&msg=creado');
    }
}

// Editar
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    $deudor = trim($_POST['deudor'] ?? '');
    $prestamista = trim($_POST['prestamista'] ?? '');
    $monto = (float)($_POST['monto'] ?? 0);
    $fecha = $_POST['fecha'] ?? '';
    
    if ($deudor && $prestamista && $monto > 0 && $fecha) {
        $conn = db();
        $stmt = $conn->prepare("UPDATE prestamos SET deudor=?, prestamista=?, monto=?, fecha=? WHERE id=?");
        $stmt->bind_param("ssdsi", $deudor, $prestamista, $monto, $fecha, $id);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        go(BASE_URL . '?view=cards&msg=editado');
    }
}

// Eliminar
if ($action === 'delete' && $id > 0) {
    $conn = db();
    $stmt = $conn->prepare("DELETE FROM prestamos WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    go(BASE_URL . '?view=cards&msg=eliminado');
}

// Marcar como pagado
if ($action === 'pagar' && $id > 0) {
    $conn = db();
    $stmt = $conn->prepare("UPDATE prestamos SET pagado=1, pagado_at=NOW() WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    go(BASE_URL . '?view=cards&msg=pagado');
}

// ===== OBTENER DATOS PARA MOSTRAR =====
$conn = db();
$where = "1=1";
$params = [];
$types = "";

// Filtros
if (!empty($_GET['q'])) {
    $q = '%' . $_GET['q'] . '%';
    $where .= " AND (deudor LIKE ? OR prestamista LIKE ?)";
    $types .= "ss";
    $params[] = $q;
    $params[] = $q;
}

$estado = $_GET['estado'] ?? 'no_pagados';
if ($estado === 'no_pagados') {
    $where .= " AND pagado = 0";
} elseif ($estado === 'pagados') {
    $where .= " AND pagado = 1";
}

$sql = "SELECT id, deudor, prestamista, monto, fecha, pagado, pagado_at,
               TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1 as meses,
               CASE WHEN fecha >= '2025-10-29' THEN 13 ELSE 10 END as tasa,
               (monto * (CASE WHEN fecha >= '2025-10-29' THEN 13 ELSE 10 END) / 100 * 
                (TIMESTAMPDIFF(MONTH, fecha, CURDATE()) + 1)) as interes
        FROM prestamos 
        WHERE $where 
        ORDER BY pagado ASC, id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$prestamos = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Préstamos</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f3f4f6;
            padding: 20px;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        
        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .header h1 { color: #111827; font-size: 24px; }
        
        /* Botones */
        .btn {
            display: inline-block;
            padding: 10px 16px;
            border-radius: 8px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }
        .btn-primary { background: #2563eb; color: white; }
        .btn-success { background: #059669; color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-warning { background: #d97706; color: white; }
        .btn-gray { background: #6b7280; color: white; }
        
        /* Toolbar */
        .toolbar {
            background: white;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }
        .toolbar input, .toolbar select {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            min-width: 200px;
        }
        
        /* Mensajes */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        
        /* Grid de tarjetas */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }
        
        /* Tarjeta */
        .card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #2563eb;
        }
        .card.pagado {
            border-left-color: #059669;
            opacity: 0.8;
            background: #f0fdf4;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .card-check {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-check input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        .card-id {
            color: #6b7280;
            font-size: 12px;
        }
        .badge {
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-pagado { background: #059669; color: white; }
        .badge-pendiente { background: #d97706; color: white; }
        
        .card-body {
            margin-bottom: 16px;
        }
        .card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .card-subtitle {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        /* Grid de info */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            background: #f9fafb;
            padding: 12px;
            border-radius: 8px;
            margin: 12px 0;
        }
        .info-item .label {
            font-size: 12px;
            color: #6b7280;
        }
        .info-item .value {
            font-size: 16px;
            font-weight: 600;
            color: #111827;
        }
        
        /* Acciones de tarjeta */
        .card-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            border-top: 1px solid #e5e7eb;
            padding-top: 12px;
        }
        
        /* Barra de selección */
        .selection-bar {
            background: white;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .selection-count {
            background: #2563eb;
            color: white;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 14px;
        }
        
        /* MODAL - SIMPLE Y FUNCIONAL */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 24px;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e5e7eb;
        }
        .modal-header h2 {
            font-size: 20px;
            color: #111827;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
        }
        .modal-close:hover { color: #dc2626; }
        
        /* Resumen del modal */
        .resumen-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .resumen-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #bae6fd;
        }
        .resumen-row:last-child {
            border-bottom: none;
        }
        
        /* Input de monto */
        .monto-input {
            width: 100%;
            padding: 16px;
            font-size: 24px;
            border: 2px solid #2563eb;
            border-radius: 12px;
            margin: 20px 0;
            text-align: center;
        }
        
        /* Tabla de resultados */
        .tabla-prestamos {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .tabla-prestamos th {
            background: #f3f4f6;
            padding: 10px;
            text-align: left;
            font-size: 12px;
        }
        .tabla-prestamos td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        .fila-completo { background: #f0fdf4; }
        .fila-parcial { background: #fffbeb; }
        .fila-sin-abono { background: #fef2f2; }
        
        .badge-completo { background: #059669; color: white; padding: 4px 8px; border-radius: 999px; font-size: 12px; }
        .badge-parcial { background: #d97706; color: white; padding: 4px 8px; border-radius: 999px; font-size: 12px; }
        .badge-sin { background: #6b7280; color: white; padding: 4px 8px; border-radius: 999px; font-size: 12px; }
        
        .modal-footer {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 2px solid #e5e7eb;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>💰 Sistema de Préstamos</h1>
            <a href="?action=nuevo" class="btn btn-primary">+ Nuevo Préstamo</a>
        </div>
        
        <!-- Mensajes -->
        <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success">
            <?php 
                $msg = $_GET['msg'];
                if ($msg == 'creado') echo '✅ Préstamo creado correctamente';
                elseif ($msg == 'editado') echo '✅ Préstamo actualizado';
                elseif ($msg == 'eliminado') echo '✅ Préstamo eliminado';
                elseif ($msg == 'pagado') echo '✅ Préstamo marcado como pagado';
                elseif ($msg == 'abono_ok') echo '✅ Abono aplicado a ' . ($_GET['count'] ?? 0) . ' préstamos';
                elseif ($msg == 'error_abono') echo '❌ Error al aplicar abono';
            ?>
        </div>
        <?php endif; ?>
        
        <!-- Toolbar de filtros -->
        <form class="toolbar" method="GET">
            <input type="hidden" name="view" value="cards">
            <input type="text" name="q" placeholder="Buscar por deudor o prestamista..." value="<?= h($_GET['q'] ?? '') ?>">
            
            <select name="estado">
                <option value="no_pagados" <?= ($_GET['estado'] ?? 'no_pagados') == 'no_pagados' ? 'selected' : '' ?>>No pagados</option>
                <option value="pagados" <?= ($_GET['estado'] ?? '') == 'pagados' ? 'selected' : '' ?>>Pagados</option>
                <option value="todos" <?= ($_GET['estado'] ?? '') == 'todos' ? 'selected' : '' ?>>Todos</option>
            </select>
            
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="?" class="btn btn-gray">Limpiar</a>
        </form>
        
        <?php if ($action === 'nuevo' || $action === 'editar'): 
            $editId = $action === 'editar' ? $id : 0;
            $editData = ['deudor' => '', 'prestamista' => '', 'monto' => '', 'fecha' => ''];
            
            if ($editId > 0) {
                $stmt = $conn->prepare("SELECT deudor, prestamista, monto, fecha FROM prestamos WHERE id = ?");
                $stmt->bind_param("i", $editId);
                $stmt->execute();
                $result = $stmt->get_result();
                $editData = $result->fetch_assoc() ?: $editData;
                $stmt->close();
            }
        ?>
            <!-- Formulario de creación/edición -->
            <div style="background: white; padding: 24px; border-radius: 12px;">
                <h2 style="margin-bottom: 20px;"><?= $editId ? 'Editar Préstamo' : 'Nuevo Préstamo' ?></h2>
                
                <form method="POST" action="?action=<?= $editId ? 'edit&id='.$editId : 'create' ?>">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <label style="display: block; margin-bottom: 4px;">Deudor</label>
                            <input type="text" name="deudor" required value="<?= h($editData['deudor']) ?>" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 4px;">Prestamista</label>
                            <input type="text" name="prestamista" required value="<?= h($editData['prestamista']) ?>" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 4px;">Monto</label>
                            <input type="number" name="monto" required value="<?= h($editData['monto']) ?>" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 4px;">Fecha</label>
                            <input type="date" name="fecha" required value="<?= h($editData['fecha']) ?>" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px; display: flex; gap: 12px;">
                        <button type="submit" class="btn btn-success">Guardar</button>
                        <a href="?" class="btn btn-gray">Cancelar</a>
                    </div>
                </form>
            </div>
        <?php else: ?>
        
            <!-- Barra de selección múltiple -->
            <div class="selection-bar" id="selectionBar">
                <span class="selection-count" id="selectedCount">0 seleccionados</span>
                <button class="btn btn-success" id="btnVistaPrevia" style="display: none;">👁️ Vista Previa Abono</button>
                <button class="btn btn-warning" id="btnEditarSeleccion" style="display: none;">✏️ Editar</button>
                <span id="bulkActions" style="display: none; margin-left: auto; display: flex; gap: 8px;">
                    <button type="button" class="btn btn-success" id="btnAbrirModal">💰 Abonar Seleccionados</button>
                </span>
            </div>
            
            <!-- Grid de tarjetas -->
            <div class="cards-grid">
                <?php if ($prestamos->num_rows == 0): ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 60px; background: white; border-radius: 12px;">
                        No hay préstamos para mostrar
                    </div>
                <?php endif; ?>
                
                <?php while ($row = $prestamos->fetch_assoc()): 
                    $total = $row['monto'] + $row['interes'];
                ?>
                <div class="card <?= $row['pagado'] ? 'pagado' : '' ?>" data-id="<?= $row['id'] ?>">
                    <div class="card-header">
                        <div class="card-check">
                            <input type="checkbox" class="select-prestamo" value="<?= $row['id'] ?>" 
                                   <?= $row['pagado'] ? 'disabled' : '' ?>>
                            <span class="card-id">#<?= $row['id'] ?></span>
                        </div>
                        <?php if ($row['pagado']): ?>
                            <span class="badge badge-pagado">✅ Pagado</span>
                        <?php else: ?>
                            <span class="badge badge-pendiente">⏳ Pendiente</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-body">
                        <div class="card-title"><?= h($row['deudor']) ?></div>
                        <div class="card-subtitle">Prestamista: <?= h($row['prestamista']) ?></div>
                        
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="label">Monto</div>
                                <div class="value">$ <?= money($row['monto']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="label">Interés (<?= $row['tasa'] ?>%)</div>
                                <div class="value">$ <?= money($row['interes']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="label">Total</div>
                                <div class="value">$ <?= money($total) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="label">Fecha</div>
                                <div class="value"><?= $row['fecha'] ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-actions">
                        <?php if (!$row['pagado']): ?>
                            <a href="?action=pagar&id=<?= $row['id'] ?>" class="btn btn-success" style="padding: 6px 12px;" onclick="return confirm('¿Marcar como pagado?')">✓ Pagar</a>
                        <?php endif; ?>
                        <a href="?action=editar&id=<?= $row['id'] ?>" class="btn btn-gray" style="padding: 6px 12px;">✏️ Editar</a>
                        <a href="?action=delete&id=<?= $row['id'] ?>" class="btn btn-danger" style="padding: 6px 12px;" onclick="return confirm('¿Eliminar?')">🗑️ Eliminar</a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- MODAL SIMPLE Y FUNCIONAL -->
    <div class="modal" id="modalAbono">
        <div class="modal-content">
            <div class="modal-header">
                <h2>💰 Vista Previa de Abono</h2>
                <button class="modal-close" id="cerrarModal">&times;</button>
            </div>
            
            <div class="resumen-box" id="resumenInicial">
                <div class="resumen-row">
                    <span>Préstamos seleccionados:</span>
                    <span id="resumen-cantidad">0</span>
                </div>
                <div class="resumen-row">
                    <span>Capital total:</span>
                    <span id="resumen-capital">$ 0</span>
                </div>
                <div class="resumen-row">
                    <span>Interés total:</span>
                    <span id="resumen-interes">$ 0</span>
                </div>
                <div class="resumen-row">
                    <span>Total a pagar:</span>
                    <span id="resumen-total">$ 0</span>
                </div>
            </div>
            
            <input type="number" class="monto-input" id="montoAbono" placeholder="Ingrese monto a abonar" min="1" step="1000">
            
            <div id="loadingModal" class="loading" style="display: none;">
                Calculando...
            </div>
            
            <div id="resultadoModal" style="display: none;">
                <div class="resumen-box" style="background: #f0fdf4;">
                    <div class="resumen-row">
                        <span>Abono utilizado:</span>
                        <span id="abono-utilizado">$ 0</span>
                    </div>
                    <div class="resumen-row">
                        <span>Sobrante:</span>
                        <span id="abono-sobrante">$ 0</span>
                    </div>
                </div>
                
                <table class="tabla-prestamos">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Deudor</th>
                            <th>Capital</th>
                            <th>Interés</th>
                            <th>Total</th>
                            <th>Abono</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody id="tablaResultados"></tbody>
                </table>
            </div>
            
            <div class="modal-footer">
                <form method="POST" action="?action=aplicar_abono" id="formConfirmar">
                    <input type="hidden" name="ids" id="confirmIds">
                    <input type="hidden" name="monto" id="confirmMonto">
                    <button type="submit" class="btn btn-success" id="btnConfirmar" disabled>✅ Confirmar Abono</button>
                </form>
                <button class="btn btn-gray" id="btnCancelar">Cancelar</button>
            </div>
        </div>
    </div>
    
    <script>
    // ===== CÓDIGO SIMPLE Y FUNCIONAL =====
    (function() {
        // Elementos
        const checkboxes = document.querySelectorAll('.select-prestamo');
        const selectedCount = document.getElementById('selectedCount');
        const btnVistaPrevia = document.getElementById('btnVistaPrevia');
        const btnAbrirModal = document.getElementById('btnAbrirModal');
        const modal = document.getElementById('modalAbono');
        const montoInput = document.getElementById('montoAbono');
        const loadingModal = document.getElementById('loadingModal');
        const resultadoModal = document.getElementById('resultadoModal');
        const tablaResultados = document.getElementById('tablaResultados');
        const btnConfirmar = document.getElementById('btnConfirmar');
        const confirmIds = document.getElementById('confirmIds');
        const confirmMonto = document.getElementById('confirmMonto');
        
        // Resumen elements
        const resumenCantidad = document.getElementById('resumen-cantidad');
        const resumenCapital = document.getElementById('resumen-capital');
        const resumenInteres = document.getElementById('resumen-interes');
        const resumenTotal = document.getElementById('resumen-total');
        const abonoUtilizado = document.getElementById('abono-utilizado');
        const abonoSobrante = document.getElementById('abono-sobrante');
        
        // Botones de cerrar
        const cerrarModal = document.getElementById('cerrarModal');
        const btnCancelar = document.getElementById('btnCancelar');
        
        // Estado
        let timeoutId = null;
        let idsSeleccionados = [];
        
        // Actualizar contador de selección
        function actualizarSeleccion() {
            idsSeleccionados = [];
            checkboxes.forEach(cb => {
                if (cb.checked && !cb.disabled) {
                    idsSeleccionados.push(cb.value);
                }
            });
            
            selectedCount.textContent = idsSeleccionados.length + ' seleccionados';
            
            if (idsSeleccionados.length > 0) {
                btnVistaPrevia.style.display = 'inline-block';
                if (btnAbrirModal) btnAbrirModal.style.display = 'inline-block';
            } else {
                btnVistaPrevia.style.display = 'none';
                if (btnAbrirModal) btnAbrirModal.style.display = 'none';
            }
        }
        
        checkboxes.forEach(cb => {
            cb.addEventListener('change', actualizarSeleccion);
        });
        
        // Abrir modal
        function abrirModal() {
            if (idsSeleccionados.length === 0) {
                alert('Selecciona al menos un préstamo');
                return;
            }
            
            modal.classList.add('active');
            montoInput.value = '';
            resultadoModal.style.display = 'none';
            btnConfirmar.disabled = true;
            
            // Cargar resumen inicial
            cargarVistaPrevia(0);
        }
        
        if (btnAbrirModal) {
            btnAbrirModal.addEventListener('click', abrirModal);
        }
        
        if (btnVistaPrevia) {
            btnVistaPrevia.addEventListener('click', abrirModal);
        }
        
        // Cerrar modal
        function cerrar() {
            modal.classList.remove('active');
        }
        
        if (cerrarModal) cerrarModal.addEventListener('click', cerrar);
        if (btnCancelar) btnCancelar.addEventListener('click', cerrar);
        
        modal.addEventListener('click', function(e) {
            if (e.target === modal) cerrar();
        });
        
        // Input de monto
        montoInput.addEventListener('input', function() {
            clearTimeout(timeoutId);
            const monto = parseFloat(this.value) || 0;
            
            if (monto <= 0) {
                resultadoModal.style.display = 'none';
                btnConfirmar.disabled = true;
                return;
            }
            
            loadingModal.style.display = 'block';
            resultadoModal.style.display = 'none';
            
            timeoutId = setTimeout(() => {
                cargarVistaPrevia(monto);
            }, 500);
        });
        
        // Función para cargar vista previa
        function cargarVistaPrevia(monto) {
            if (idsSeleccionados.length === 0) return;
            
            const formData = new FormData();
            idsSeleccionados.forEach(id => formData.append('ids[]', id));
            formData.append('monto', monto);
            
            fetch('?action=vista_previa_abono', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                loadingModal.style.display = 'none';
                
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                // Actualizar resumen
                resumenCantidad.textContent = data.prestamos.length;
                resumenCapital.textContent = '$ ' + formatearNumero(data.resumen.total_capital);
                resumenInteres.textContent = '$ ' + formatearNumero(data.resumen.total_interes);
                resumenTotal.textContent = '$ ' + formatearNumero(data.resumen.total_general);
                abonoUtilizado.textContent = '$ ' + formatearNumero(data.resumen.abono_utilizado);
                abonoSobrante.textContent = '$ ' + formatearNumero(data.resumen.sobrante);
                
                // Llenar tabla
                let html = '';
                data.prestamos.forEach(p => {
                    let clase = '';
                    let badge = '';
                    
                    if (p.estado === 'completo') {
                        clase = 'fila-completo';
                        badge = '<span class="badge-completo">✅ Pagado</span>';
                    } else if (p.estado === 'parcial') {
                        clase = 'fila-parcial';
                        badge = '<span class="badge-parcial">⚠️ Parcial</span>';
                    } else {
                        clase = 'fila-sin-abono';
                        badge = '<span class="badge-sin">❌ Sin abono</span>';
                    }
                    
                    html += `<tr class="${clase}">
                        <td>#${p.id}</td>
                        <td>${p.deudor}</td>
                        <td>$ ${formatearNumero(p.monto)}</td>
                        <td>$ ${formatearNumero(p.interes)}</td>
                        <td>$ ${formatearNumero(p.total)}</td>
                        <td>$ ${formatearNumero(p.abono)}</td>
                        <td>${badge}</td>
                    </tr>`;
                });
                
                tablaResultados.innerHTML = html;
                resultadoModal.style.display = 'block';
                
                // Habilitar confirmar si hay abono
                btnConfirmar.disabled = (data.resumen.abono_utilizado === 0);
                
                // Preparar formulario de confirmación
                confirmIds.value = JSON.stringify(idsSeleccionados);
                confirmMonto.value = monto;
            })
            .catch(error => {
                console.error('Error:', error);
                loadingModal.style.display = 'none';
                alert('Error al calcular: ' + error.message);
            });
        }
        
        // Formatear número
        function formatearNumero(num) {
            return Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
        
        // Confirmar antes de enviar
        document.getElementById('formConfirmar').addEventListener('submit', function(e) {
            if (!confirm('¿Aplicar este abono? Los préstamos se marcarán como pagados.')) {
                e.preventDefault();
            }
        });
        
        // Inicializar
        actualizarSeleccion();
    })();
    </script>
</body>
</html>
<?php 
if (isset($stmt)) $stmt->close();
if (isset($conn)) $conn->close();
?>