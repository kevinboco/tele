<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Generar Informe</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background: linear-gradient(135deg,#667eea,#764ba2);
    padding:20px;
}

/* tarjetas */
.section-card{
    background:white;
    border-radius:15px;
    box-shadow:0 5px 20px rgba(0,0,0,0.1);
    height:100%;
}

.section-header{
    background:#0d6efd;
    color:white;
    padding:10px 15px;
    border-radius:15px 15px 0 0;
    font-weight:600;
}

.section-content{
    padding:15px;
    max-height:500px;
    overflow-y:auto;
}

/* conductores */
.conductor-item{
    padding:10px;
    border:1px solid #ddd;
    border-radius:8px;
    margin-bottom:5px;
    cursor:pointer;
}
.conductor-item:hover{
    background:#f5f5f5;
}

/* resumen */
.selected-driver-item{
    background:#f1f1f1;
    padding:8px;
    border-radius:8px;
    margin-bottom:5px;
}

/* fechas + botón */
.top-bar{
    background:white;
    padding:15px;
    border-radius:15px;
    margin-bottom:20px;
    box-shadow:0 5px 20px rgba(0,0,0,0.1);
}

</style>
</head>

<body>

<div class="container-fluid">

<form method="post">

<!-- 🔹 FECHAS + BOTÓN -->
<div class="top-bar">
    <div class="row align-items-end g-3">
        
        <div class="col-md-3">
            <label>Desde</label>
            <input type="date" name="desde" class="form-control" required>
        </div>

        <div class="col-md-3">
            <label>Hasta</label>
            <input type="date" name="hasta" class="form-control" required>
        </div>

        <div class="col-md-3">
            <button class="btn btn-success w-100">
                🚀 Generar Informe
            </button>
        </div>

    </div>
</div>

<!-- 🔹 CONTENIDO PRINCIPAL EN UNA FILA -->
<div class="row g-3">

    <!-- 🟦 CONDUCTORES -->
    <div class="col-lg-5">
        <div class="section-card">
            <div class="section-header">👥 Conductores</div>
            <div class="section-content">

                <input type="text" id="buscador" class="form-control mb-2" placeholder="Buscar...">

                <div id="listaConductores">

                <?php foreach($todosConductores as $i => $c): ?>
                    
                    <div class="conductor-item">
                        <input type="checkbox" 
                               class="conductor-checkbox"
                               name="conductores_seleccionados[]"
                               value="<?= htmlspecialchars($c['nombre']) ?>">
                        
                        <strong><?= htmlspecialchars($c['nombre']) ?></strong><br>
                        <small><?= htmlspecialchars($c['cedula']) ?></small>
                    </div>

                <?php endforeach; ?>

                </div>

            </div>
        </div>
    </div>

    <!-- 🟨 RESUMEN -->
    <div class="col-lg-3">
        <div class="section-card">
            <div class="section-header">📋 Resumen</div>
            <div class="section-content">

                <h2 id="contador">0</h2>
                <p>seleccionados</p>

                <div id="listaSeleccionados"></div>

            </div>
        </div>
    </div>

    <!-- 🟩 EMPRESAS -->
    <div class="col-lg-4">
        <div class="section-card">
            <div class="section-header">🏢 Empresas</div>
            <div class="section-content">

                <?php foreach($empresas as $i => $e): ?>

                <div>
                    <input type="checkbox" name="empresas[]" value="<?= htmlspecialchars($e) ?>">
                    <?= htmlspecialchars($e) ?>
                </div>

                <?php endforeach; ?>

            </div>
        </div>
    </div>

</div>

</form>
</div>

<script>

// contador y resumen
const checkboxes = document.querySelectorAll('.conductor-checkbox');
const contador = document.getElementById('contador');
const lista = document.getElementById('listaSeleccionados');

checkboxes.forEach(cb=>{
    cb.addEventListener('change', ()=>{
        const activos = document.querySelectorAll('.conductor-checkbox:checked');
        contador.textContent = activos.length;

        lista.innerHTML = '';
        activos.forEach(a=>{
            lista.innerHTML += `<div class="selected-driver-item">${a.value}</div>`;
        });
    });
});

// buscador
document.getElementById('buscador').addEventListener('keyup', function(){
    let texto = this.value.toLowerCase();

    document.querySelectorAll('.conductor-item').forEach(item=>{
        let nombre = item.innerText.toLowerCase();
        item.style.display = nombre.includes(texto) ? '' : 'none';
    });
});

</script>

</body>
</html>