<?php
/******************************************************
 *  admin_prestamos.php  —  CRUD simple para "prestamos"
 *  Requisitos:
 *   - PHP 8+ recomendado
 *   - Extensión mysqli
 *   - Carpeta /uploads con permisos de escritura
 ******************************************************/

// =================== CONFIG ===================
define('DB_HOST', 'mysql.hostinger.com');
define('DB_USER', 'u648222299_keboco5');
define('DB_PASS', 'Bucaramanga3011');
define('DB_NAME', 'u648222299_viajes');

// Ruta de subidas (carpeta local)
const UPLOAD_DIR = __DIR__ . '/uploads/';
// URL base opcional (si lo sirves en subcarpeta), si no, déjalo vacío:
const BASE_PATH = ''; // p.ej: '/admin'

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
$order_col  = $_GET['oc'] ?? 'id';           // columnas: id,deudor,prestamista,monto,fecha,created_at
$order_dir  = (strtolower($_GET['od'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 15;
$offset     = ($page - 1) * $per_page;

// Columnas de orden permitidas
$allowed_cols = ['id','deudor','prestamista','monto','fecha','created_at'];
if (!in_array($order_col, $allowed_cols, true)) $order_col = 'id';

// ============== HANDLERS (CREATE / UPDATE / DELETE) ==============

/** Subida de imagen (opcional). Retorna nombre de archivo o null */
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
    $fecha       = trim($_POST['fecha'] ?? ''); // YYYY-MM-DD
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
    // Si hay errores, se cae al render del form con datos
}

/** UPDATE */
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
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

        // Si no se sube imagen nueva pero no se marcó keep_image, entonces imagen = NULL
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

/** DELETE */
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    $conn = db();
    // Opcional: eliminar archivo asociado (imagen)
    $stmt = $conn->prepare("SELECT imagen FROM prestamos WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute(); $stmt->bind_result($img); $stmt->fetch(); $stmt->close();
    if ($img && is_file(UPLOAD_DIR . $img)) @unlink(UPLOAD_DIR . $img);

    $stmt = $conn->prepare("DELETE FROM prestamos WHERE id=?");
    $stmt->bind_param("i", $id);
    $ok = $stmt->execute();
    $stmt->close(); $conn->close();
    redirect('/admin_prestamos.php?action=list&msg=' . ($ok ? 'eliminado' : 'error'));
}

// =================== VISTAS ===================

function header_html($title='Prestamos Admin'){
    echo "<!doctype html><html lang='es'><head><meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <title>".h($title)."</title>
    <style>
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;margin:20px;background:#f7f7fb;color:#222}
        a{color:#0b5ed7;text-decoration:none}
        .topbar{display:flex;gap:10px;align-items:center;margin-bottom:16px}
        .btn{display:inline-block;padding:8px 12px;border-radius:8px;background:#0b5ed7;color:#fff}
        .btn.secondary{background:#6c757d}
        .btn.danger{background:#dc3545}
        .card{background:#fff;border-radius:14px;box-shadow:0 4px 20px rgba(0,0,0,.06);padding:16px;margin-bottom:16px}
        table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden}
        th,td{padding:10px;border-bottom:1px solid #eee;vertical-align:top}
        th{background:#f0f3f8;text-align:left}
        tr:hover{background:#fafbfd}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .field{display:flex;flex-direction:column;gap:6px}
        input[type=text],input[type=number],input[type=date]{padding:10px;border:1px solid #ddd;border-radius:10px}
        input[type=file]{border:1px dashed #bbb;padding:8px;border-radius:10px;background:#fafafa}
        .muted{color:#666}
        .pagination{display:flex;gap:6px;align-items:center;margin-top:12px}
        .pill{padding:6px 10px;border-radius:9999px;background:#eef4ff;color:#0b5ed7}
        .msg{padding:8px 12px;border-radius:10px;background:#e8f7ee;color:#196a3b;display:inline-block}
        .error{padding:8px 12px;border-radius:10px;background:#fdecec;color:#b02a37;display:inline-block}
        img.thumb{max-height:70px;border-radius:8px;border:1px solid #eee}
        .right{float:right}
        .nowrap{white-space:nowrap}
    </style>
    </head><body>";
}

function footer_html(){
    echo "</body></html>";
}

function nav(){
    echo "<div class='topbar'>
        <a class='btn' href='".BASE_PATH."/admin_prestamos.php?action=list'>📄 Listado</a>
        <a class='btn secondary' href='".BASE_PATH."/admin_prestamos.php?action=create'>➕ Crear</a>
    </div>";
}

// ---- Render listado ----
if ($action === 'list') {
    header_html('Prestamos | Listado'); nav();

    $msg = $_GET['msg'] ?? '';
    if ($msg) {
        $text = [
            'creado'   => 'Registro creado correctamente.',
            'editado'  => 'Registro editado correctamente.',
            'eliminado'=> 'Registro eliminado.',
            'error'    => 'Ocurrió un error.',
        ][$msg] ?? 'OK';
        echo "<div class='msg'>".h($text)."</div>";
    }

    echo "<div class='card'>
    <form method='get' style='display:flex;gap:8px;align-items:center;flex-wrap:wrap'>
        <input type='hidden' name='action' value='list'>
        <input type='text' name='q' value='".h($search)."' placeholder='Buscar (deudor/prestamista)' />
        <select name='oc'>
            <option value='id' ".($order_col==='id'?'selected':'').">ID</option>
            <option value='deudor' ".($order_col==='deudor'?'selected':'').">Deudor</option>
            <option value='prestamista' ".($order_col==='prestamista'?'selected':'').">Prestamista</option>
            <option value='monto' ".($order_col==='monto'?'selected':'').">Monto</option>
            <option value='fecha' ".($order_col==='fecha'?'selected':'').">Fecha</option>
            <option value='created_at' ".($order_col==='created_at'?'selected':'').">Creado</option>
        </select>
        <select name='od'>
            <option value='desc' ".($order_dir==='DESC'?'selected':'').">Desc</option>
            <option value='asc'  ".($order_dir==='ASC'?'selected':'').">Asc</option>
        </select>
        <button class='btn' type='submit'>Filtrar</button>
    </form>
    </div>";

    $conn = db();

    // Conteo total + suma
    $where = "1";
    $params = [];
    $types = "";
    if ($search !== '') {
        $where .= " AND (deudor LIKE CONCAT('%', ?, '%') OR prestamista LIKE CONCAT('%', ?, '%'))";
        $params[] = $search; $params[] = $search;
        $types .= "ss";
    }

    // total rows
    $sql_count = "SELECT COUNT(*), COALESCE(SUM(monto),0) FROM prestamos WHERE $where";
    $stmt = $conn->prepare($sql_count);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute(); $stmt->bind_result($total_rows,$sum_total); $stmt->fetch(); $stmt->close();

    // datos paginados
    $sql = "SELECT id, deudor, prestamista, monto, fecha, imagen, created_at
            FROM prestamos
            WHERE $where
            ORDER BY $order_col $order_dir
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    if ($types) {
        $types2 = $types . "ii";
        $params2 = array_merge($params, [$per_page, $offset]);
        $stmt->bind_param($types2, ...$params2);
    } else {
        $stmt->bind_param("ii", $per_page, $offset);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    echo "<div class='card'>";
    echo "<div class='right pill'>Total registros: ".h($total_rows)." — Suma: $ ".number_format((float)$sum_total,0,',','.')."</div>";
    echo "<h2>Préstamos</h2>";
    echo "<div style='overflow:auto'><table><thead><tr>
            <th class='nowrap'>ID</th>
            <th>Deudor</th>
            <th>Prestamista</th>
            <th class='nowrap'>Monto</th>
            <th class='nowrap'>Fecha</th>
            <th>Imagen</th>
            <th class='nowrap'>Creado</th>
            <th>Acciones</th>
        </tr></thead><tbody>";
    while($row = $res->fetch_assoc()){
        echo "<tr>";
        echo "<td class='nowrap'>".h($row['id'])."</td>";
        echo "<td>".h($row['deudor'])."</td>";
        echo "<td>".h($row['prestamista'])."</td>";
        echo "<td class='nowrap'>$ ".number_format((float)$row['monto'],0,',','.')."</td>";
        echo "<td class='nowrap'>".h($row['fecha'])."</td>";
        echo "<td>";
        if ($row['imagen']) {
            $imgPath = 'uploads/' . rawurlencode($row['imagen']);
            echo "<a href='".h($imgPath)."' target='_blank'><img class='thumb' src='".h($imgPath)."' alt='captura'></a>";
        } else {
            echo "<span class='muted'>—</span>";
        }
        echo "</td>";
        echo "<td class='nowrap'>".h($row['created_at'])."</td>";
        echo "<td class='nowrap'>
                <a class='btn secondary' href='".BASE_PATH."/admin_prestamos.php?action=edit&id=".$row['id']."'>✏️ Editar</a>
                <form method='post' action='".BASE_PATH."/admin_prestamos.php?action=delete&id=".$row['id']."' style='display:inline' onsubmit='return confirm(\"¿Eliminar registro #".$row['id']."?\")'>
                    <button class='btn danger' type='submit'>🗑️ Eliminar</button>
                </form>
              </td>";
        echo "</tr>";
    }
    echo "</tbody></table></div>";

    // paginación
    $total_pages = max(1, (int)ceil($total_rows / $per_page));
    echo "<div class='pagination'>";
    if ($page > 1) {
        $qs = http_build_query(['action'=>'list','q'=>$search,'oc'=>$order_col,'od'=>strtolower($order_dir),'page'=>$page-1]);
        echo "<a class='btn secondary' href='".BASE_PATH."/admin_prestamos.php?$qs'>⬅️ Anterior</a>";
    }
    echo "<span class='muted'>Página $page de $total_pages</span>";
    if ($page < $total_pages) {
        $qs = http_build_query(['action'=>'list','q'=>$search,'oc'=>$order_col,'od'=>strtolower($order_dir),'page'=>$page+1]);
        echo "<a class='btn secondary' href='".BASE_PATH."/admin_prestamos.php?$qs'>Siguiente ➡️</a>";
    }
    echo "</div>";

    echo "</div>"; // card
    $stmt->close(); $conn->close();

    footer_html(); exit;
}

// ---- Render crear ----
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header_html('Prestamos | Crear'); nav();
    $errors = $errors ?? [];
    if ($errors) echo "<div class='error'>".h(implode(' ', $errors))."</div>";

    echo "<div class='card'><h2>Nuevo préstamo</h2>
    <form method='post' enctype='multipart/form-data'>
        <div class='grid'>
            <div class='field'>
                <label>Deudor *</label>
                <input type='text' name='deudor' required>
            </div>
            <div class='field'>
                <label>Prestamista *</label>
                <input type='text' name='prestamista' required>
            </div>
            <div class='field'>
                <label>Monto (número) *</label>
                <input type='number' name='monto' step='1' min='0' required>
            </div>
            <div class='field'>
                <label>Fecha *</label>
                <input type='date' name='fecha' required>
            </div>
            <div class='field' style='grid-column:1 / -1'>
                <label>Imagen (opcional)</label>
                <input type='file' name='imagen' accept='image/*'>
                <span class='muted'>Formatos: jpg, png, webp, gif. Máx 10MB.</span>
            </div>
        </div>
        <div style='margin-top:12px'>
            <button class='btn' type='submit'>Guardar</button>
            <a class='btn secondary' href='".BASE_PATH."/admin_prestamos.php?action=list'>Cancelar</a>
        </div>
    </form></div>";
    footer_html(); exit;
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
    $errors = $errors ?? [];
    if ($errors) echo "<div class='error'>".h(implode(' ', $errors))."</div>";

    echo "<div class='card'><h2>Editar préstamo #".h($row['id'])."</h2>
    <form method='post' enctype='multipart/form-data'>
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
                <label>Monto (número) *</label>
                <input type='number' name='monto' step='1' min='0' value='".h($row['monto'])."' required>
            </div>
            <div class='field'>
                <label>Fecha *</label>
                <input type='date' name='fecha' value='".h($row['fecha'])."' required>
            </div>
            <div class='field' style='grid-column:1 / -1'>
                <label>Imagen actual</label>";
                if ($row['imagen']) {
                    $imgPath = 'uploads/' . rawurlencode($row['imagen']);
                    echo "<div>
                        <a href='".h($imgPath)."' target='_blank'><img class='thumb' src='".h($imgPath)."' alt='captura'></a>
                    </div>
                    <label><input type='checkbox' name='keep_image' checked> Mantener imagen actual</label>";
                } else {
                    echo "<div class='muted'>— (sin imagen)</div>
                    <input type='hidden' name='keep_image' value='0'>";
                }
                echo "
                <div style='margin-top:6px'>
                    <label>Subir nueva (opcional)</label>
                    <input type='file' name='imagen' accept='image/*'>
                </div>
                <span class='muted'>Si subes una nueva y desmarcas “mantener”, se reemplaza la imagen. Si no subes nada y desmarcas, se elimina.</span>
            </div>
        </div>
        <div style='margin-top:12px'>
            <button class='btn' type='submit'>Guardar cambios</button>
            <a class='btn secondary' href='".BASE_PATH."/admin_prestamos.php?action=list'>Cancelar</a>
        </div>
    </form></div>";
    footer_html(); exit;
}

// ---- 404 fallback ----
header_html('Prestamos | Panel');
nav();
echo "<div class='card'><h2>Ups</h2><p>Acción no válida. Vuelve al <a href='".BASE_PATH."/admin_prestamos.php?action=list'>listado</a>.</p></div>";
footer_html();
