<?php
// correccion_masiva.php - Versión ULTRA COMPACTA (sin scroll, todo visible en una pantalla)
// Ideal para TV / proyector / pantalla grande
session_start();
include("conexion.php");

// ================== CONFIGURACIÓN ==================
// Obtener todas las empresas disponibles desde empresas_admin
$empresas_disponibles = [];
$res_empresas = $conexion->query("SELECT nombre FROM empresas_admin ORDER BY nombre ASC");
if ($res_empresas) {
    while($row = $res_empresas->fetch_assoc()) {
        $empresas_disponibles[] = $row['nombre'];
    }
}

// Si no hay empresas en la tabla, mostrar opciones por defecto
if (empty($empresas_disponibles)) {
    $empresas_disponibles = [
        'Transportes Unidos',
        'Transportes del Norte', 
        'Transportes del Sur',
        'Cootransmag',
        'Taxis Libres',
        'Transportes del Este',
        'Transportes del Oeste',
        'Transportes Ejemplo'
    ];
}

// ================== PROCESAR CORRECCIÓN VÍA AJAX ==================
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
        echo json_encode(['success' => false, 'error' => $conexion->error]);
    }
    exit();
}

// ================== OBTENER VIAJES POR RANGO DE FECHAS ==================
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

// Ajustar índice actual
if ($indice_actual >= $total_viajes && $total_viajes > 0) {
    $indice_actual = $total_viajes - 1;
}
if ($indice_actual < 0 && $total_viajes > 0) {
    $indice_actual = 0;
}

$viaje_actual = ($total_viajes > 0 && isset($viajes[$indice_actual])) ? $viajes[$indice_actual] : null;
$progreso = $total_viajes > 0 ? round(($indice_actual + 1) / $total_viajes * 100) : 0;

// ================== DETECTAR SI SE COMPLETÓ ==================
$completado = isset($_GET['completado']) && $_GET['completado'] == 1;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>🎮 Corrección Rápida de Viajes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #0f0c29 0%, #1a1a3e 100%);
            height: 100vh;
            overflow: hidden;
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, sans-serif;
        }
        
        /* Contenedor principal - ocupa toda la pantalla */
        .fullscreen {
            height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 12px 20px;
        }
        
        /* ========== BARRA SUPERIOR ========== */
        .top-bar {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border-radius: 60px;
            padding: 8px 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: nowrap;
            flex-shrink: 0;
        }
        
        /* Filtro de fechas */
        .fecha-rango {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #1e1e3a;
            padding: 5px 15px;
            border-radius: 40px;
        }
        
        .fecha-rango input {
            background: transparent;
            border: none;
            color: white;
            padding: 8px 5px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
        }
        
        .fecha-rango input:focus {
            outline: none;
        }
        
        .fecha-rango input::-webkit-calendar-picker-indicator {
            filter: invert(1);
            cursor: pointer;
        }
        
        .fecha-rango span {
            color: #888;
        }
        
        .btn-buscar {
            background: #3b82f6;
            border: none;
            border-radius: 40px;
            padding: 8px 20px;
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-buscar:hover {
            background: #2563eb;
            transform: scale(1.02);
        }
        
        /* Info del viaje actual (mini) */
        .info-viaje-mini {
            background: #1e1e3a;
            border-radius: 40px;
            padding: 5px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .info-viaje-mini .id {
            font-size: 20px;
            font-weight: bold;
            color: #3b82f6;
        }
        
        .info-viaje-mini .empresa-actual {
            background: #dc2626;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            color: white;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        /* Progreso */
        .progreso-mini {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #1e1e3a;
            padding: 5px 18px;
            border-radius: 40px;
            color: white;
            font-weight: 500;
        }
        
        .progreso-mini .barra {
            width: 150px;
            height: 6px;
            background: #2a2a4a;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .progreso-mini .barra-fill {
            height: 100%;
            background: linear-gradient(90deg, #22c55e, #3b82f6);
            width: 0%;
            transition: width 0.2s ease;
        }
        
        /* ========== GRID PRINCIPAL - 2 COLUMNAS ========== */
        .main-grid {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 15px;
            flex: 1;
            min-height: 0;
        }
        
        /* Columna WhatsApp */
        .whatsapp-panel {
            background: rgba(26, 26, 46, 0.95);
            backdrop-filter: blur(5px);
            border-radius: 24px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }
        
        .whatsapp-panel .label {
            color: #888;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 12px;
            font-weight: 600;
        }
        
        .whatsapp-content {
            background: #dcf8c5;
            border-radius: 16px;
            padding: 20px;
            font-family: 'Courier New', 'Fira Code', monospace;
            font-size: 14px;
            line-height: 1.5;
            white-space: pre-wrap;
            word-break: break-word;
            flex: 1;
            overflow-y: auto;
            color: #1a1a2e;
        }
        
        /* Columna Empresas */
        .empresas-panel {
            background: rgba(26, 26, 46, 0.95);
            backdrop-filter: blur(5px);
            border-radius: 24px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }
        
        .empresas-panel .label {
            color: #888;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 12px;
            font-weight: 600;
        }
        
        /* Grid de botones de empresas - se ajusta automáticamente */
        .empresas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 10px;
            flex: 1;
            overflow-y: auto;
            align-content: start;
        }
        
        .btn-empresa {
            background: #2a2a4a;
            border: 1px solid #3a3a5a;
            border-radius: 12px;
            padding: 12px 8px;
            color: white;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.15s;
            text-align: center;
            cursor: pointer;
        }
        
        .btn-empresa:hover {
            background: #3b82f6;
            border-color: #3b82f6;
            transform: translateY(-2px);
        }
        
        /* ========== BARRA INFERIOR - Navegación ========== */
        .bottom-bar {
            margin-top: 15px;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border-radius: 60px;
            padding: 12px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }
        
        .btn-nav {
            background: #2a2a4a;
            border: none;
            border-radius: 40px;
            padding: 10px 30px;
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.15s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-nav:hover {
            background: #3b82f6;
            transform: scale(1.02);
        }
        
        .btn-nav.disabled, .btn-nav:disabled {
            opacity: 0.4;
            pointer-events: none;
        }
        
        /* ========== TOAST FLOTANTE ========== */
        .toast-flotante {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: #22c55e;
            color: white;
            padding: 12px 28px;
            border-radius: 50px;
            font-weight: bold;
            z-index: 2000;
            animation: fadeInUp 0.3s ease;
            display: none;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            font-size: 14px;
            white-space: nowrap;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }
        
        /* ========== PANTALLA DE COMPLETADO ========== */
        .completado-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 20px;
        }
        
        .completado-icono {
            font-size: 80px;
        }
        
        .completado-container h2 {
            color: white;
            font-size: 32px;
        }
        
        .completado-container p {
            color: #aaa;
            font-size: 18px;
        }
        
        .btn-volver {
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 40px;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
        }
        
        /* ========== PANTALLA INICIAL ========== */
        .inicial-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 20px;
        }
        
        .inicial-icono {
            font-size: 80px;
        }
        
        .inicial-container h2 {
            color: white;
            font-size: 28px;
        }
        
        .inicial-container p {
            color: #aaa;
            font-size: 16px;
        }
        
        /* ========== SCROLLBAR PERSONALIZADO ========== */
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
        
        /* ========== RESPONSIVE ========== */
        @media (max-width: 1000px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
            .empresas-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
            .top-bar {
                flex-wrap: wrap;
                border-radius: 20px;
            }
            .toast-flotante {
                white-space: normal;
                text-align: center;
                font-size: 12px;
            }
        }
        
        @media (max-width: 768px) {
            .fullscreen {
                padding: 8px 10px;
            }
            .btn-nav {
                padding: 8px 15px;
                font-size: 12px;
            }
            .info-viaje-mini .empresa-actual {
                max-width: 120px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>

<div class="fullscreen">
    
    <!-- ==================== BARRA SUPERIOR ==================== -->
    <div class="top-bar">
        <div class="fecha-rango">
            <input type="date" id="fechaDesde" value="<?= htmlspecialchars($desde) ?>">
            <span>→</span>
            <input type="date" id="fechaHasta" value="<?= htmlspecialchars($hasta) ?>">
        </div>
        <button class="btn-buscar" id="btnBuscar">🔍 BUSCAR</button>
        
        <?php if ($viaje_actual && !$completado): ?>
        <div class="info-viaje-mini">
            <span class="id">#<?= $viaje_actual['id'] ?></span>
            <span class="empresa-actual">🏢 <?= !empty($viaje_actual['empresa']) ? htmlspecialchars(mb_substr($viaje_actual['empresa'], 0, 25)) : 'SIN EMPRESA' ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($total_viajes > 0 && !$completado): ?>
        <div class="progreso-mini">
            <span><?= $indice_actual + 1 ?>/<?= $total_viajes ?></span>
            <div class="barra">
                <div class="barra-fill" style="width: <?= $progreso ?>%"></div>
            </div>
            <span><?= $progreso ?>%</span>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- ==================== CONTENIDO PRINCIPAL ==================== -->
    
    <?php if ($completado): ?>
        <!-- PANTALLA DE COMPLETADO -->
        <div class="completado-container">
            <div class="completado-icono">🎉</div>
            <h2>¡Corrección masiva completada!</h2>
            <p>Se revisaron todos los <?= $total_viajes ?> viajes del rango seleccionado</p>
            <a href="index2.php" class="btn-volver">🏠 Volver al inicio</a>
        </div>
        
    <?php elseif ($total_viajes > 0 && $viaje_actual): ?>
        
        <!-- GRID PRINCIPAL 2 COLUMNAS -->
        <div class="main-grid">
            
            <!-- COLUMNA 1: WHATSAPP ORIGINAL -->
            <div class="whatsapp-panel">
                <div class="label">💬 WHATSAPP ORIGINAL</div>
                <div class="whatsapp-content">
                    <?php if (!empty($viaje_actual['whatsapp'])): ?>
                        <?= nl2br(htmlspecialchars($viaje_actual['whatsapp'])) ?>
                    <?php else: ?>
                        <span style="color: #dc2626;">⚠️ No hay mensaje de WhatsApp guardado para este viaje</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- COLUMNA 2: BOTONES DE EMPRESAS -->
            <div class="empresas-panel">
                <div class="label">🖱️ EMPRESA CORRECTA → HAZ CLIC</div>
                <div class="empresas-grid" id="empresasGrid">
                    <?php foreach($empresas_disponibles as $emp): ?>
                        <div class="btn-empresa" data-empresa="<?= htmlspecialchars($emp) ?>" data-id="<?= $viaje_actual['id'] ?>">
                            🏢 <?= htmlspecialchars($emp) ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="btn-empresa" data-empresa="NINGUNA" data-id="<?= $viaje_actual['id'] ?>">
                        🗑️ NINGUNA (vacío)
                    </div>
                </div>
            </div>
        </div>
        
        <!-- BARRA INFERIOR - NAVEGACIÓN -->
        <div class="bottom-bar">
            <a href="?desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&indice=<?= max(0, $indice_actual - 1) ?>" 
               class="btn-nav <?= $indice_actual <= 0 ? 'disabled' : '' ?>" id="btnAnterior">
                ◀ ANTERIOR
            </a>
            <a href="?desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&indice=<?= min($total_viajes - 1, $indice_actual + 1) ?>" 
               class="btn-nav" id="btnSaltar">
                ⏭️ SALTAR ESTE
            </a>
            <a href="?desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&indice=<?= min($total_viajes - 1, $indice_actual + 1) ?>" 
               class="btn-nav" id="btnSiguiente">
                SIGUIENTE ▶
            </a>
        </div>
        
    <?php elseif ($desde && $hasta && $total_viajes == 0): ?>
        
        <!-- NO HAY VIAJES -->
        <div class="inicial-container">
            <div class="inicial-icono">📭</div>
            <h2>No hay viajes en este rango</h2>
            <p><?= htmlspecialchars($desde) ?> → <?= htmlspecialchars($hasta) ?></p>
            <a href="index2.php" class="btn-volver">🏠 Volver al inicio</a>
        </div>
        
    <?php else: ?>
        
        <!-- PANTALLA INICIAL - SELECCIONAR FECHAS -->
        <div class="inicial-container">
            <div class="inicial-icono">🎮</div>
            <h2>Selecciona un rango de fechas</h2>
            <p>Arriba elige "Fecha Desde" y "Fecha Hasta" → haz clic en BUSCAR</p>
            <a href="index2.php" class="btn-volver">🏠 Volver al inicio</a>
        </div>
        
    <?php endif; ?>
    
</div>

<!-- TOAST FLOTANTE -->
<div class="toast-flotante" id="toastFlotante">✅ Corregido</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    let corrigiendo = false;
    
    function mostrarToast(mensaje, esError = false) {
        const toast = document.getElementById('toastFlotante');
        toast.textContent = mensaje;
        if (esError) {
            toast.style.background = '#dc2626';
        } else {
            toast.style.background = '#22c55e';
        }
        toast.style.display = 'block';
        setTimeout(() => {
            toast.style.display = 'none';
        }, 1200);
        setTimeout(() => {
            toast.style.background = '#22c55e';
        }, 1300);
    }
    
    // Auto-búsqueda cuando cambian las fechas
    const fechaDesde = document.getElementById('fechaDesde');
    const fechaHasta = document.getElementById('fechaHasta');
    const btnBuscar = document.getElementById('btnBuscar');
    
    if (fechaDesde && fechaHasta && btnBuscar) {
        fechaDesde.addEventListener('change', function() {
            if (fechaDesde.value && fechaHasta.value) {
                btnBuscar.click();
            }
        });
        fechaHasta.addEventListener('change', function() {
            if (fechaDesde.value && fechaHasta.value) {
                btnBuscar.click();
            }
        });
        
        btnBuscar.addEventListener('click', function() {
            const desde = fechaDesde.value;
            const hasta = fechaHasta.value;
            if (desde && hasta) {
                window.location.href = '?desde=' + encodeURIComponent(desde) + '&hasta=' + encodeURIComponent(hasta);
            } else {
                mostrarToast('⚠️ Selecciona ambas fechas', true);
            }
        });
    }
    
    // Clic en botones de empresas
    document.querySelectorAll('.btn-empresa').forEach(btn => {
        btn.addEventListener('click', function() {
            if (corrigiendo) return;
            
            const idViaje = this.dataset.id;
            const empresa = this.dataset.empresa;
            const btnOriginal = this;
            
            corrigiendo = true;
            const textoOriginal = btnOriginal.innerHTML;
            btnOriginal.innerHTML = '<span>⏳</span> Corrigiendo...';
            btnOriginal.style.opacity = '0.6';
            btnOriginal.style.pointerEvents = 'none';
            
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
                        mostrarToast('✅ Viaje #' + idViaje + ' → ' + nombreEmpresa.substring(0, 30));
                        
                        const urlParams = new URLSearchParams(window.location.search);
                        let indiceActual = parseInt(urlParams.get('indice')) || 0;
                        const totalViajes = <?= $total_viajes ?>;
                        
                        if (indiceActual + 1 < totalViajes) {
                            urlParams.set('indice', indiceActual + 1);
                            window.location.href = '?' + urlParams.toString();
                        } else {
                            window.location.href = '?desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&completado=1';
                        }
                    } else {
                        mostrarToast('❌ Error al corregir', true);
                        btnOriginal.innerHTML = textoOriginal;
                        btnOriginal.style.opacity = '1';
                        btnOriginal.style.pointerEvents = 'auto';
                        corrigiendo = false;
                    }
                },
                error: function() {
                    mostrarToast('❌ Error de conexión', true);
                    btnOriginal.innerHTML = textoOriginal;
                    btnOriginal.style.opacity = '1';
                    btnOriginal.style.pointerEvents = 'auto';
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
    
    // Si la página se recarga por completado, mostrar mensaje
    <?php if (isset($_GET['completado']) && $_GET['completado'] == 1 && $total_viajes > 0): ?>
    mostrarToast('🎉 ¡Completaste todos los viajes!');
    <?php endif; ?>
</script>

</body>
</html>