<?php
// correccion_masiva.php - Versión rediseñada (basada en tu imagen)
session_start();
include("conexion.php");

// Obtener todas las empresas disponibles
$empresas_disponibles = [];
$res_empresas = $conexion->query("SELECT nombre FROM empresas_admin ORDER BY nombre ASC");
if ($res_empresas) {
    while($row = $res_empresas->fetch_assoc()) {
        $empresas_disponibles[] = $row['nombre'];
    }
}
// Empresas por defecto si no hay en BD
if (empty($empresas_disponibles)) {
    $empresas_disponibles = [
        '1000000', 'ACPM', 'Aditional', 'Cava', 'Hospital', 'Hospital Nacional', 
        'ICU', 'p', 'p campaña', 'Pampafla máxica', 'Ufir de la guajira', 
        'p.nazareth', 'Pizarra', 'Puerto esterella', 'p.Punta espada', 
        'p.algemera', 'Fvilla fátima', 'Pendiente marzo', 'Sunny app'
    ];
}

// Procesar corrección AJAX
if (isset($_POST['ajax_corregir'])) {
    header('Content-Type: application/json');
    $id_viaje = (int)$_POST['id_viaje'];
    $nueva_empresa = $_POST['nueva_empresa'];
    
    if ($nueva_empresa === 'NINGUNA') {
        $sql = "UPDATE viajes SET empresa = NULL WHERE id = $id_viaje";
    } else {
        $nueva_empresa = $conexion->real_escape_string($nueva_empresa);
        $sql = "UPDATE viajes SET empresa = '$nueva_empresa' WHERE id = $id_viaje";
    }
    
    if ($conexion->query($sql)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}

// Obtener viajes
$viajes = [];
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';
$indice_actual = isset($_GET['indice']) ? (int)$_GET['indice'] : 0;

if ($desde && $hasta) {
    $desde = $conexion->real_escape_string($desde);
    $hasta = $conexion->real_escape_string($hasta);
    $sql = "SELECT * FROM viajes WHERE fecha BETWEEN '$desde' AND '$hasta' ORDER BY fecha ASC, id ASC";
    $resultado = $conexion->query($sql);
    if ($resultado) {
        while($row = $resultado->fetch_assoc()) {
            $viajes[] = $row;
        }
    }
}

$total_viajes = count($viajes);
if ($indice_actual >= $total_viajes && $total_viajes > 0) $indice_actual = $total_viajes - 1;
if ($indice_actual < 0 && $total_viajes > 0) $indice_actual = 0;

$viaje_actual = ($total_viajes > 0 && isset($viajes[$indice_actual])) ? $viajes[$indice_actual] : null;
$progreso = $total_viajes > 0 ? round(($indice_actual + 1) / $total_viajes * 100) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>🎮 Corrección Rápida de Viajes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #0f0f1a;
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            padding: 15px;
        }
        
        /* Contenedor principal */
        .fullscreen {
            max-width: 1600px;
            margin: 0 auto;
            background: linear-gradient(135deg, #0f0c29 0%, #1a1a3e 100%);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }
        
        /* BARRA SUPERIOR */
        .top-bar {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            padding: 12px 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .fecha-grupo {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #1e1e3a;
            padding: 6px 18px;
            border-radius: 50px;
        }
        
        .fecha-grupo input {
            background: transparent;
            border: none;
            color: white;
            padding: 8px 5px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .fecha-grupo input:focus {
            outline: none;
        }
        
        .fecha-grupo span {
            color: #888;
        }
        
        .btn-buscar {
            background: #3b82f6;
            border: none;
            border-radius: 50px;
            padding: 8px 25px;
            color: white;
            font-weight: bold;
            transition: all 0.2s;
        }
        
        .btn-buscar:hover {
            background: #2563eb;
            transform: scale(1.02);
        }
        
        .info-viaje-mini {
            background: #1e1e3a;
            border-radius: 50px;
            padding: 6px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .info-viaje-mini .id {
            font-size: 20px;
            font-weight: bold;
            color: #3b82f6;
        }
        
        .info-viaje-mini .empresa-badge {
            background: #dc2626;
            padding: 4px 14px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .progreso-grupo {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #1e1e3a;
            padding: 6px 20px;
            border-radius: 50px;
        }
        
        .progreso-grupo .barra {
            width: 180px;
            height: 6px;
            background: #2a2a4a;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .progreso-grupo .barra-fill {
            height: 100%;
            background: #22c55e;
            width: 0%;
            transition: width 0.3s;
        }
        
        /* CONTENIDO PRINCIPAL - 2 COLUMNAS */
        .main-content {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 20px;
            padding: 20px;
        }
        
        /* COLUMNA IZQUIERDA */
        .col-whatsapp {
            background: #1a1a2e;
            border-radius: 20px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .col-whatsapp .header {
            background: #075e54;
            padding: 12px 20px;
            color: white;
            font-weight: bold;
            font-size: 14px;
            letter-spacing: 1px;
        }
        
        /* Contenedor WhatsApp - ALTURA AUTOMÁTICA según contenido */
        .whatsapp-contenido {
            background: #dcf8c5;
            padding: 20px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-word;
            color: #1a1a2e;
        }
        
        /* Info del viaje (debajo del WhatsApp) */
        .info-viaje-detalle {
            background: #0f0f1a;
            padding: 15px 20px;
            border-top: 1px solid #2a2a4a;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            align-items: baseline;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .info-item .label {
            color: #888;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .info-item .value {
            color: white;
            font-size: 16px;
            font-weight: 600;
        }
        
        .info-item .value.empresa-mal {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.2);
            padding: 3px 10px;
            border-radius: 20px;
        }
        
        /* COLUMNA DERECHA - EMPRESAS */
        .col-empresas {
            background: #1a1a2e;
            border-radius: 20px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .col-empresas .header {
            background: #3b82f6;
            padding: 12px 20px;
            color: white;
            font-weight: bold;
            font-size: 14px;
            letter-spacing: 1px;
        }
        
        /* Grid de empresas - BOTONES MÁS GRANDES */
        .empresas-grid {
            padding: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 12px;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .btn-empresa {
            background: #2a2a4a;
            border: 1px solid #3a3a5a;
            border-radius: 14px;
            padding: 14px 10px;
            color: white;
            font-size: 13px;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-empresa:hover {
            background: #3b82f6;
            border-color: #3b82f6;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59,130,246,0.3);
        }
        
        .btn-empresa.ninguna {
            background: #4a4a6a;
            border-color: #ef4444;
        }
        
        .btn-empresa.ninguna:hover {
            background: #ef4444;
        }
        
        /* BARRA INFERIOR - NAVEGACIÓN */
        .bottom-nav {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            padding: 16px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .btn-nav {
            background: #2a2a4a;
            border: none;
            border-radius: 50px;
            padding: 12px 35px;
            color: white;
            font-weight: bold;
            font-size: 16px;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-nav:hover {
            background: #3b82f6;
            transform: scale(1.02);
            color: white;
        }
        
        .btn-nav.disabled, .btn-nav:disabled {
            opacity: 0.4;
            pointer-events: none;
        }
        
        .btn-nav.saltar {
            background: #eab308;
            color: #1a1a2e;
        }
        
        .btn-nav.saltar:hover {
            background: #ca8a04;
        }
        
        /* TOAST FLOTANTE */
        .toast-flotante {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: #22c55e;
            color: white;
            padding: 14px 32px;
            border-radius: 60px;
            font-weight: bold;
            z-index: 2000;
            animation: fadeInUp 0.3s ease;
            display: none;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            font-size: 15px;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateX(-50%) translateY(20px); }
            to { opacity: 1; transform: translateX(-50%) translateY(0); }
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #2a2a4a;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb {
            background: #3b82f6;
            border-radius: 3px;
        }
        
        /* Responsive */
        @media (max-width: 1000px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            .empresas-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            }
            .top-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .fecha-grupo, .info-viaje-mini, .progreso-grupo {
                justify-content: center;
            }
        }
        
        /* Estado de carga */
        .cargando {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
</head>
<body>

<div class="fullscreen">
    
    <!-- BARRA SUPERIOR -->
    <div class="top-bar">
        <div class="fecha-grupo">
            <input type="date" id="fechaDesde" value="<?= htmlspecialchars($desde) ?>">
            <span>→</span>
            <input type="date" id="fechaHasta" value="<?= htmlspecialchars($hasta) ?>">
            <button class="btn-buscar" id="btnBuscar">🔍 BUSCAR</button>
        </div>
        
        <?php if ($viaje_actual): ?>
        <div class="info-viaje-mini">
            <span class="id">🆔 #<?= $viaje_actual['id'] ?></span>
            <span class="empresa-badge">🏢 <?= !empty($viaje_actual['empresa']) ? mb_substr(htmlspecialchars($viaje_actual['empresa']), 0, 25) : 'SIN EMPRESA' ?></span>
        </div>
        <?php endif; ?>
        
        <div class="progreso-grupo">
            <span><strong><?= $indice_actual + 1 ?></strong>/<?= $total_viajes ?></span>
            <div class="barra">
                <div class="barra-fill" style="width: <?= $progreso ?>%"></div>
            </div>
            <span><?= $progreso ?>%</span>
        </div>
    </div>
    
    <?php if ($total_viajes > 0 && $viaje_actual): ?>
    
    <!-- CONTENIDO PRINCIPAL -->
    <div class="main-content">
        
        <!-- COLUMNA IZQUIERDA: WhatsApp + Info viaje -->
        <div class="col-whatsapp">
            <div class="header">
                💬 WHATSAPP ORIGINAL
            </div>
            <div class="whatsapp-contenido">
                <?php if (!empty($viaje_actual['whatsapp'])): ?>
                    <?= nl2br(htmlspecialchars($viaje_actual['whatsapp'])) ?>
                <?php else: ?>
                    <span style="color: #dc2626;">⚠️ No hay mensaje de WhatsApp guardado para este viaje</span>
                <?php endif; ?>
            </div>
            
            <!-- INFO DEL VIAJE (debajo del WhatsApp) -->
            <div class="info-viaje-detalle">
                <div class="info-item">
                    <span class="label">👤 CONDUCTOR:</span>
                    <span class="value"><?= htmlspecialchars($viaje_actual['nombre']) ?></span>
                </div>
                <div class="info-item">
                    <span class="label">🛣️ RUTA:</span>
                    <span class="value"><?= htmlspecialchars($viaje_actual['ruta']) ?></span>
                </div>
                <div class="info-item">
                    <span class="label">🏢 EMPRESA ACTUAL:</span>
                    <span class="value empresa-mal"><?= !empty($viaje_actual['empresa']) ? htmlspecialchars($viaje_actual['empresa']) : '(VACÍO)' ?></span>
                </div>
                <?php if (!empty($viaje_actual['tipo_vehiculo'])): ?>
                <div class="info-item">
                    <span class="label">🚐 VEHÍCULO:</span>
                    <span class="value"><?= htmlspecialchars($viaje_actual['tipo_vehiculo']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- COLUMNA DERECHA: Botones de empresas -->
        <div class="col-empresas">
            <div class="header">
                🖱️ EMPRESA CORRECTA → HAZ CLIC
            </div>
            <div class="empresas-grid" id="empresasGrid">
                <?php foreach($empresas_disponibles as $emp): ?>
                    <div class="btn-empresa" data-empresa="<?= htmlspecialchars($emp) ?>" data-id="<?= $viaje_actual['id'] ?>">
                        🏢 <?= htmlspecialchars($emp) ?>
                    </div>
                <?php endforeach; ?>
                <div class="btn-empresa ninguna" data-empresa="NINGUNA" data-id="<?= $viaje_actual['id'] ?>">
                    🗑️ NINGUNA (dejar vacío)
                </div>
            </div>
        </div>
    </div>
    
    <!-- BARRA INFERIOR - Navegación -->
    <div class="bottom-nav">
        <a href="?desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&indice=<?= max(0, $indice_actual - 1) ?>" 
           class="btn-nav" id="btnAnterior">◀ ANTERIOR</a>
        <a href="?desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&indice=<?= min($total_viajes - 1, $indice_actual + 1) ?>" 
           class="btn-nav saltar" id="btnSaltar">⏭️ SALTAR ESTE</a>
        <a href="?desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&indice=<?= min($total_viajes - 1, $indice_actual + 1) ?>" 
           class="btn-nav" id="btnSiguiente">SIGUIENTE ▶</a>
    </div>
    
    <?php elseif ($desde && $hasta && $total_viajes == 0): ?>
    
    <div style="padding: 80px; text-align: center;">
        <div style="font-size: 70px;">📭</div>
        <h3 style="color: white; margin-top: 20px;">No hay viajes en este rango</h3>
        <p style="color: #888;"><?= htmlspecialchars($desde) ?> → <?= htmlspecialchars($hasta) ?></p>
        <a href="index2.php" class="btn btn-secondary mt-3">🏠 Volver al inicio</a>
    </div>
    
    <?php elseif (!$desde || !$hasta): ?>
    
    <div style="padding: 80px; text-align: center;">
        <div style="font-size: 70px;">🎮</div>
        <h3 style="color: white; margin-top: 20px;">Selecciona un rango de fechas</h3>
        <p style="color: #888;">Arriba elige Desde y Hasta → clic en BUSCAR</p>
    </div>
    
    <?php endif; ?>
    
</div>

<div class="toast-flotante" id="toastFlotante">✅ Corregido</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let corrigiendo = false;

function mostrarToast(mensaje, esError = false) {
    const toast = document.getElementById('toastFlotante');
    toast.textContent = mensaje;
    if (esError) {
        toast.style.background = '#ef4444';
    } else {
        toast.style.background = '#22c55e';
    }
    toast.style.display = 'block';
    setTimeout(() => {
        toast.style.display = 'none';
        toast.style.background = '#22c55e';
    }, 1500);
}

// Auto-búsqueda al cambiar fechas
document.getElementById('fechaDesde').addEventListener('change', function() {
    document.getElementById('btnBuscar').click();
});
document.getElementById('fechaHasta').addEventListener('change', function() {
    document.getElementById('btnBuscar').click();
});

document.getElementById('btnBuscar').addEventListener('click', function() {
    const desde = document.getElementById('fechaDesde').value;
    const hasta = document.getElementById('fechaHasta').value;
    if (desde && hasta) {
        window.location.href = '?desde=' + encodeURIComponent(desde) + '&hasta=' + encodeURIComponent(hasta);
    } else {
        mostrarToast('❌ Selecciona ambas fechas', true);
    }
});

// Click en botones de empresas
document.querySelectorAll('.btn-empresa').forEach(btn => {
    btn.addEventListener('click', function() {
        if (corrigiendo) return;
        
        const idViaje = this.dataset.id;
        const empresa = this.dataset.empresa;
        const btnOriginal = this;
        const textoOriginal = btnOriginal.innerHTML;
        
        corrigiendo = true;
        
        // Mostrar estado de carga en el botón clickeado
        btnOriginal.innerHTML = '<span>⏳</span> Corrigiendo...';
        btnOriginal.style.opacity = '0.6';
        
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                ajax_corregir: 1,
                id_viaje: idViaje,
                nueva_empresa: empresa
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    let nombreEmpresa = empresa === 'NINGUNA' ? 'NINGUNA (vacío)' : empresa;
                    mostrarToast('✅ Viaje #' + idViaje + ' → ' + nombreEmpresa);
                    
                    const urlParams = new URLSearchParams(window.location.search);
                    let indiceActual = parseInt(urlParams.get('indice')) || 0;
                    const totalViajes = <?= $total_viajes ?>;
                    
                    if (indiceActual + 1 < totalViajes) {
                        urlParams.set('indice', indiceActual + 1);
                        window.location.href = '?' + urlParams.toString();
                    } else {
                        // Último viaje, mostrar completado y volver
                        mostrarToast('🎉 ¡Completaste todos los viajes!');
                        setTimeout(() => {
                            window.location.href = 'index2.php';
                        }, 1500);
                    }
                } else {
                    mostrarToast('❌ Error al corregir', true);
                    btnOriginal.innerHTML = textoOriginal;
                    btnOriginal.style.opacity = '1';
                    corrigiendo = false;
                }
            },
            error: function() {
                mostrarToast('❌ Error de conexión', true);
                btnOriginal.innerHTML = textoOriginal;
                btnOriginal.style.opacity = '1';
                corrigiendo = false;
            }
        });
    });
});

// Prevenir doble clic en navegación
document.querySelectorAll('.btn-nav').forEach(btn => {
    btn.addEventListener('click', function(e) {
        if (corrigiendo) {
            e.preventDefault();
            mostrarToast('⏳ Espera a que termine la corrección', true);
            return false;
        }
    });
});

// Confirmar al salir si quedan viajes
<?php if ($total_viajes > 0 && $indice_actual < $total_viajes - 1): ?>
window.addEventListener('beforeunload', function(e) {
    e.preventDefault();
    e.returnValue = 'Aún quedan viajes por revisar. ¿Seguro que quieres salir?';
    return 'Aún quedan viajes por revisar. ¿Seguro que quieres salir?';
});
<?php endif; ?>
</script>

</body>
</html>