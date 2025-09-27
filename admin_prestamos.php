<?php
/******************************************************
 *  admin_prestamos.php  —  CRUD simple para "prestamos"
 ******************************************************/

// =================== CONFIG ===================
define('DB_HOST', 'mysql.hostinger.com');
define('DB_USER', 'u648222299_keboco5');
define('DB_PASS', 'Bucaramanga3011');
define('DB_NAME', 'u648222299_viajes');

// Ruta de subidas (carpeta local)
const UPLOAD_DIR = __DIR__ . '/uploads/';
// URL base opcional (si lo sirves en subcarpeta), si no, déjalo vacío:
const BASE_PATH = ''; 

// Tamaño máx imagen 10 MB
const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

// ==============================================

/** Conexión a DB */
function db(): mysqli {
    $m = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($m->connect_errno) {
        http_response_code(500);
        exit("Error DB: " . $m->connect_error);
    }
    $m->set_charset('utf8mb4');
    return $m;
}

/** Escape HTML */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/** Redirección */
function redirect($path) {
    header("Location: " . (BASE_PATH . $path));
    exit;
}

/** Asegura carpeta de subidas */
if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0775, true);

// ---- Router simple por query string ----
$action = $_GET['action'] ?? 'list';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ---- Filtro, orden y paginación (list) ----
$search     = trim($_GET['q'] ?? '');
$order_col  = $_GET['oc'] ?? 'id';           
$order_dir  = (strtolower($_GET['od'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 15;
$offset     = ($page - 1) * $per_page;

// Columnas de orden permitidas
$allowed_cols = ['id','deudor','prestamista','monto','fecha','created_at'];
if (!in_array($order_col, $allowed_cols, true)) $order_col = 'id';

// ============== HANDLERS (CREATE / UPDATE / DELETE) ==============

/** Subida de imagen */
function handle_upload(?array $file): ?string {
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (!empty($file['error']) && $file['error'] !== UPLOAD_ERR_OK) return null;
    if (($file['size'] ?? 0) > MAX_UPLOAD_BYTES) return null;

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        default      => null
    };
    if (!$ext) return null;

    $name = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = UPLOAD_DIR . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return null;
    return $name;
}

/** CREATE */
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $deudor      = trim($_POST['deudor'] ?? '');
    $prestamista = trim($_POST['prestamista'] ?? '');
    $monto       = trim($_POST['monto'] ?? '');
    $fecha       = trim($_POST['fecha'] ?? ''); 
    $imagen_name = handle_upload($_FILES['imagen'] ?? null);

    $errors = [];
    if ($deudor === '')       $errors[] = "El deudor es obligatorio.";
    if ($prestamista === '')  $errors[] = "El prestamista es obligatorio.";
    if ($monto === '' || !is_numeric($monto)) $errors[] = "El monto debe ser numérico.";
    if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)) $errors[] = "La fecha debe tener formato YYYY-MM-DD.";

    if (empty($errors)) {
        $conn = db();
        $stmt = $conn->prepare("INSERT INTO prestamos (deudor, prestamista, monto, fecha, imagen, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssdss", $deudor, $prestamista, $monto, $fecha, $imagen_name);
        $ok = $stmt->execute();
        $stmt->close(); $conn->close();
        if ($ok) redirect('/admin_prestamos.php?action=list&msg=creado');
        $errors[] = "Error al insertar.";
    }
}

/** UPDATE o DELETE desde la vista EDITAR */
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    // Si viene delete, borramos
    if (isset($_POST['delete'])) {
        $conn = db();
        $stmt = $conn->prepare("SELECT imagen FROM prestamos WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute(); $stmt->bind_result($img); $stmt->fetch(); $stmt->close();
        if ($img && is_file(UPLOAD_DIR . $img)) @unlink(UPLOAD_DIR . $img);

        $stmt = $conn->prepare("DELETE FROM prestamos WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute(); $stmt->close(); $conn->close();
        redirect('/admin_prestamos.php?action=list&msg=eliminado');
    }

    // Si viene update, actualizamos
    if (isset($_POST['update'])) {
        $deudor      = trim($_POST['deudor'] ?? '');
        $prestamista = trim($_POST['prestamista'] ?? '');
        $monto       = trim($_POST['monto'] ?? '');
        $fecha       = trim($_POST['fecha'] ?? '');
        $imagen_name = handle_upload($_FILES['imagen'] ?? null);
        $keep_image  = isset($_POST['keep_image']) ? (bool)$_POST['keep_image'] : false;

        $errors = [];
        if ($deudor === '')       $errors[] = "El deudor es obligatorio.";
        if ($prestamista === '')  $errors[] = "El prestamista es obligatorio.";
        if ($monto === '' || !is_numeric($monto)) $errors[] = "El monto debe ser numérico.";
        if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)) $errors[] = "La fecha debe tener formato YYYY-MM-DD.";

        if (empty($errors)) {
            $conn = db();
            if ($imagen_name) {
                $stmt = $conn->prepare("UPDATE prestamos SET deudor=?, prestamista=?, monto=?, fecha=?, imagen=? WHERE id=?");
                $stmt->bind_param("ssdssi", $deudor, $prestamista, $monto, $fecha, $imagen_name, $id);
            } else {
                if ($keep_image) {
                    $stmt = $conn->prepare("UPDATE prestamos SET deudor=?, prestamista=?, monto=?, fecha=? WHERE id=?");
                    $stmt->bind_param("ssdsi", $deudor, $prestamista, $monto, $fecha, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE prestamos SET deudor=?, prestamista=?, monto=?, fecha=?, imagen=NULL WHERE id=?");
                    $stmt->bind_param("ssdsi", $deudor, $prestamista, $monto, $fecha, $id);
                }
            }
            $ok = $stmt->execute();
            $stmt->close(); $conn->close();
            if ($ok) redirect('/admin_prestamos.php?action=list&msg=editado');
            $errors[] = "Error al actualizar.";
        }
    }
}

// =================== VISTAS ===================

function header_html($title='Prestamos Admin'){
    echo "<!doctype html><html lang='es'><head><meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <title>".h($title)."</title>
    <style>
        body{font-family:sans-serif;margin:20px;background:#f7f7fb;color:#222}
        a{color:#0b5ed7;text-decoration:none}
        .topbar{display:flex;gap:10px;align-items:center;margin-bottom:16px}
        .btn{display:inline-block;padding:8px 12px;border-radius:8px;background:#0b5ed7;color:#fff;border:none;cursor:pointer}
        .btn.secondary{background:#6c757d}
        .btn.danger{background:#dc3545}
        .card{background:#fff;border-radius:14px;box-shadow:0 4px 20px rgba(0,0,0,.06);padding:16px;margin-bottom:16px}
        table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden}
        th,td{padding:10px;border-bottom:1px solid #eee}
        th{background:#f0f3f8;text-align:left}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .field{display:flex;flex-direction:column;gap:6px}
        input[type=text],input[type=number],input[type=date]{padding:10px;border:1px solid #ddd;border-radius:10px}
        input[type=file]{border:1px dashed #bbb;padding:8px;border-radius:10px;background:#fafafa}
        .muted{color:#666}
        img.thumb{max-height:70px;border-radius:8px;border:1px solid #eee}
    </style>
    </head><body>";
}
function footer_html(){ echo "</body></html>"; }
function nav(){
    echo "<div class='topbar'>
        <a class='btn' href='".BASE_PATH."/admin_prestamos.php?action=list'>📄 Listado</a>
        <a class='btn secondary' href='".BASE_PATH."/admin_prestamos.php?action=create'>➕ Crear</a>
    </div>";
}

// ---- Render listado ----
if ($action === 'list') {
    // aquí dejas tu listado original
}

// ---- Render crear ----
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // aquí dejas tu vista de crear original
}

// ---- Render editar ----
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] !== 'POST' && $id > 0) {
    $conn = db();
    $stmt = $conn->prepare("SELECT id, deudor, prestamista, monto, fecha, imagen FROM prestamos WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute(); $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close(); $conn->close();

    if (!$row) { redirect('/admin_prestamos.php?action=list&msg=error'); }

    header_html('Prestamos | Editar'); nav();

    echo "<div class='card'><h2>Editar préstamo #".h($row['id'])."</h2>
    <form method='post' enctype='multipart/form-data'>
        <input type='hidden' name='update' value='1'>
        <div class='grid'>
            <div class='field'>
                <label>Deudor *</label>
                <input type='text' name='deudor' value='".h($row['deudor'])."' required>
            </div>
            <div class='field'>
                <label>Prestamista *</label>
                <input type='text' name='prestamista' value='".h($row['prestamista'])."' required>
            </div>
            <div class='field'>
                <label>Monto *</label>
                <input type='number' name='monto' value='".h($row['monto'])."' required>
            </div>
            <div class='field'>
                <label>Fecha *</label>
                <input type='date' name='fecha' value='".h($row['fecha'])."' required>
            </div>
            <div class='field' style='grid-column:1 / -1'>
                <label>Imagen actual</label>";
                if ($row['imagen']) {
                    $imgPath = 'uploads/' . rawurlencode($row['imagen']);
                    echo "<div><a href='".h($imgPath)."' target='_blank'>
                        <img class='thumb' src='".h($imgPath)."'></a></div>
                        <label><input type='checkbox' name='keep_image' checked> Mantener imagen actual</label>";
                } else {
                    echo "<div class='muted'>— (sin imagen)</div>
                    <input type='hidden' name='keep_image' value='0'>";
                }
                echo "<div style='margin-top:6px'>
                    <label>Subir nueva (opcional)</label>
                    <input type='file' name='imagen' accept='image/*'>
                </div>
            </div>
        </div>
        <div style='margin-top:12px'>
            <button class='btn' type='submit'>💾 Guardar cambios</button>
            <button class='btn danger' type='submit' name='delete' value='1' 
                    onclick='return confirm(\"¿Seguro que deseas eliminar este registro?\")'>🗑️ Eliminar</button>
            <a class='btn secondary' href='".BASE_PATH."/admin_prestamos.php?action=list'>Cancelar</a>
        </div>
    </form>
    </div>";

    footer_html(); exit;
}

// ---- 404 ----
header_html(); nav();
echo "<div class='card'><h2>Ups</h2><p>Acción no válida.</p></div>";
footer_html();
