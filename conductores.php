<?php
// ============================================
// CONEXIÓN
// ============================================
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) die("Error: " . $conn->connect_error);
$conn->set_charset('utf8mb4');

// ============================================
// CREAR TABLA SI NO EXISTE
// ============================================
$conn->query("CREATE TABLE IF NOT EXISTS `conductores` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) UNIQUE,
    `cedula` VARCHAR(20) DEFAULT '',
    `cuenta_banco` VARCHAR(50) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ============================================
// ALIMENTAR CON NOMBRES NUEVOS (IGNORA DUPLICADOS)
// ============================================
$conn->query("INSERT IGNORE INTO conductores (nombre) 
              SELECT DISTINCT TRIM(nombre) FROM viajes 
              WHERE nombre IS NOT NULL AND TRIM(nombre) != ''");

// ============================================
// ELIMINAR POSIBLES DUPLICADOS (MANTIENE EL DE MENOR ID)
// ============================================
$conn->query("DELETE t1 FROM conductores t1 
              INNER JOIN conductores t2 
              ON t1.nombre = t2.nombre AND t1.id > t2.id");

// ============================================
// GUARDAR EDICIÓN
// ============================================
$mensaje = '';
if (isset($_POST['guardar'])) {
    $id = (int)$_POST['id'];
    $cedula = $conn->real_escape_string(trim($_POST['cedula']));
    $cuenta = $conn->real_escape_string(trim($_POST['cuenta']));
    
    $conn->query("UPDATE conductores SET cedula='$cedula', cuenta_banco='$cuenta' WHERE id=$id");
    
    if ($conn->affected_rows >= 0) {
        $mensaje = '<p style="color:green;font-weight:bold;">✅ Datos guardados correctamente</p>';
    } else {
        $mensaje = '<p style="color:red;">❌ Error: ' . $conn->error . '</p>';
    }
}

// ============================================
// API SUGERENCIAS
// ============================================
if (isset($_GET['buscar'])) {
    header('Content-Type: application/json');
    $q = $conn->real_escape_string(trim($_GET['buscar']));
    $res = $conn->query("SELECT id, nombre, cedula, cuenta_banco FROM conductores WHERE nombre LIKE '%$q%' ORDER BY nombre LIMIT 8");
    $data = [];
    while ($r = $res->fetch_assoc()) $data[] = $r;
    echo json_encode($data);
    exit;
}

// ============================================
// CARGAR CONDUCTOR PARA EDITAR
// ============================================
$editar = null;
if (isset($_GET['editar'])) {
    $id = (int)$_GET['editar'];
    $res = $conn->query("SELECT * FROM conductores WHERE id=$id");
    if ($res->num_rows) $editar = $res->fetch_assoc();
}

// ============================================
// LISTAR TODOS
// ============================================
$lista = $conn->query("SELECT * FROM conductores ORDER BY nombre");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Conductores</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:Arial; background:#eef1f5; padding:20px; }
        .contenedor { max-width:900px; margin:auto; }
        .caja { background:#fff; padding:25px; border-radius:12px; margin-bottom:20px; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
        h2 { margin-bottom:15px; color:#222; }
        input[type=text] { width:100%; padding:12px; border:2px solid #ddd; border-radius:8px; font-size:16px; }
        input[type=text]:focus { border-color:#4a90d9; outline:none; }
        .buscar-relativo { position:relative; }
        #sugBox { display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #ccc; border-top:0; border-radius:0 0 8px 8px; max-height:220px; overflow-y:auto; z-index:10; }
        .sg { padding:12px; cursor:pointer; border-bottom:1px solid #eee; }
        .sg:hover { background:#f0f4ff; }
        .sg b { display:block; }
        .sg span { font-size:13px; color:#777; }
        #formBox { display:none; margin-top:20px; padding:20px; background:#f7f8fa; border-radius:10px; }
        #formBox label { display:block; margin:10px 0 4px; font-weight:bold; color:#444; }
        #formBox input { margin-bottom:10px; }
        .btn { padding:10px 25px; border:none; border-radius:6px; font-size:15px; font-weight:bold; cursor:pointer; }
        .btn-azul { background:#4a90d9; color:#fff; }
        .btn-gris { background:#ccc; color:#333; margin-left:10px; }
        table { width:100%; border-collapse:collapse; }
        th { background:#4a90d9; color:#fff; padding:12px; text-align:left; }
        td { padding:10px 12px; border-bottom:1px solid #eee; }
        .vacio { color:#aaa; }
        .fila-estado { font-size:13px; padding:4px 10px; border-radius:20px; }
        .ok { background:#d4edda; color:#155724; }
        .falta { background:#fff3cd; color:#856404; }
        .btn-chico { padding:6px 15px; font-size:13px; background:#4a90d9; color:#fff; border:none; border-radius:5px; cursor:pointer; }
    </style>
</head>
<body>
<div class="contenedor">

    <div class="caja">
        <h2>🔍 Buscar Conductor</h2>
        <?= $mensaje ?>
        <div class="buscar-relativo">
            <input type="text" id="buscador" placeholder="Escribe el nombre..." autocomplete="off">
            <div id="sugBox"></div>
        </div>
        
        <div id="formBox">
            <h3>✏️ Editar Datos</h3>
            <form method="POST">
                <input type="hidden" name="guardar" value="1">
                <input type="hidden" name="id" id="fId">
                <label>Nombre</label>
                <input type="text" id="fNombre" disabled>
                <label>Cédula</label>
                <input type="text" name="cedula" id="fCedula" placeholder="Ingresa cédula">
                <label>Cuenta de Banco</label>
                <input type="text" name="cuenta" id="fCuenta" placeholder="Ingresa cuenta bancaria">
                <br>
                <button type="submit" class="btn btn-azul">💾 Guardar</button>
                <button type="button" class="btn btn-gris" onclick="cerrar()">Cancelar</button>
            </form>
        </div>
    </div>

    <div class="caja">
        <h2>📋 Lista de Conductores</h2>
        <table>
            <thead>
                <tr><th>Nombre</th><th>Cédula</th><th>Cuenta</th><th>Estado</th><th></th></tr>
            </thead>
            <tbody>
                <?php while($c = $lista->fetch_assoc()): 
                    $completo = ($c['cedula']!='' && $c['cuenta_banco']!='');
                ?>
                <tr>
                    <td><b><?= htmlspecialchars($c['nombre']) ?></b></td>
                    <td><?= $c['cedula'] ?: '<span class="vacio">—</span>' ?></td>
                    <td><?= $c['cuenta_banco'] ?: '<span class="vacio">—</span>' ?></td>
                    <td><span class="fila-estado <?= $completo ? 'ok' : 'falta' ?>"><?= $completo ? '✅ Completo' : '⚠️ Pendiente' ?></span></td>
                    <td>
                        <button class="btn-chico" onclick="editar(<?= $c['id'] ?>, '<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>', '<?= htmlspecialchars($c['cedula'], ENT_QUOTES) ?>', '<?= htmlspecialchars($c['cuenta_banco'], ENT_QUOTES) ?>')">Editar</button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
const input = document.getElementById('buscador');
const sugBox = document.getElementById('sugBox');
const formBox = document.getElementById('formBox');

// BUSCAR SUGERENCIAS
input.oninput = function(){
    let v = this.value.trim();
    if(v.length < 2){ sugBox.style.display='none'; return; }
    fetch('?buscar='+encodeURIComponent(v))
    .then(r=>r.json())
    .then(d=>{
        sugBox.innerHTML = '';
        if(!d.length){ sugBox.innerHTML = '<div class="sg"><span>Sin resultados</span></div>'; }
        else {
            d.forEach(c=>{
                let div = document.createElement('div');
                div.className = 'sg';
                div.innerHTML = '<b>'+c.nombre+'</b><span>Cédula: '+(c.cedula||'—')+' | Cuenta: '+(c.cuenta_banco||'—')+'</span>';
                div.onclick = ()=>editar(c.id, c.nombre, c.cedula, c.cuenta_banco);
                sugBox.appendChild(div);
            });
        }
        sugBox.style.display='block';
    });
};

// CERRAR SUGERENCIAS
document.addEventListener('click', function(e){
    if(!sugBox.contains(e.target) && e.target !== input) sugBox.style.display='none';
});

// FUNCIÓN EDITAR
function editar(id, nombre, cedula, cuenta){
    document.getElementById('fId').value = id;
    document.getElementById('fNombre').value = nombre;
    document.getElementById('fCedula').value = cedula;
    document.getElementById('fCuenta').value = cuenta;
    formBox.style.display = 'block';
    input.value = nombre;
    sugBox.style.display = 'none';
    formBox.scrollIntoView({behavior:'smooth'});
}

// CERRAR FORMULARIO
function cerrar(){
    formBox.style.display = 'none';
}
</script>
</body>
</html>