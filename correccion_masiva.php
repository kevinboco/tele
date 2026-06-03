<?php
// correccion_masiva.php - Versión panorámica para TV/pantalla ancha
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
if (empty($empresas_disponibles)) {
    $empresas_disponibles = ['Transportes Unidos', 'Transportes del Norte', 'Transportes del Sur', 'Cootransmag', 'Taxis Libres', 'Transportes del Este', 'Transportes del Oeste'];
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
        echo json_encode(['success' => false, 'error' => $conexion->error]);
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
    <title>🎮 Corrección Interactiva - Panorámica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            min-height: 100vh;
            padding: 15px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Contenedor principal - usa todo el ancho */
        .auditoria-container {
            max-width: 100%;
            margin: 0 auto;
        }
        
        /* Tarjeta principal */
        .card-auditoria {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            overflow: hidden;
        }
        
        /* Header con gradiente */
        .auditoria-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* Grid de 2 columnas laterales */
        .auditoria-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            min-height: 400px;
        }
        
        /* Columna izquierda */
        .col-datos {
            background: #f8fafc;
            padding: 25px;
            border-right: 1px solid #e2e8f0;
        }
        
        /* Columna derecha */
        .col-whatsapp {
            background: #ffffff;
            padding: 25px;
        }
        
        /* Tarjeta de datos */
        .info-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .info-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
        }
        
        .empresa-mal {
            background: #fef2f2;
            border: 2px solid #ef4444;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
        }
        
        .empresa-mal .badge-mal {
            background: #ef4444;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .empresa-mal .empresa-nombre {
            font-size: 24px;
            font-weight: bold;
            color: #dc2626;
            margin: 15px 0;
        }
        
        .whatsapp-box {
            background: #dcf8c5;
            border-radius: 16px;
            padding: 20px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
        }
        
        /* Botones de empresas - grid responsivo */
        .empresas-grid {
            padding: 25px;
            background: #f1f5f9;
            border-top: 1px solid #e2e8f0;
        }
        
        .empresas-grid h4 {
            margin-bottom: 20px;
            font-weight: 600;
            color: #0f172a;
        }
        
        .btn-empresa {
            padding: 14px 10px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s;
            border: 2px solid #cbd5e1;
            background: white;
            width: 100%;
            margin-bottom: 10px;
        }
        
        .btn-empresa:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            border-color: #3b82f6;
            background: #eff6ff;
        }
        
        .btn-empresa-correcta {
            background: #22c55e;
            color: white;
            border-color: #22c55e;
        }
        
        .btn-empresa-correcta:hover {
            background: #16a34a;
        }
        
        /* Barra de progreso */
        .progreso-container {
            background: #e2e8f0;
            border-radius: 99px;
            height: 8px;
            overflow: hidden;
        }
        
        .progreso-bar {
            background: linear-gradient(90deg, #22c55e, #3b82f6);
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
        }
        
        /* Footer navegación */
        .nav-footer {
            background: white;
            padding: 20px 30px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn-nav {
            padding: 12px 28px;
            border-radius: 40px;
            font-weight: 600;
        }
        
        /* Animaciones */
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .toast-correccion {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
            background: #22c55e;
            color: white;
            padding: 15px 25px;
            border-radius: 50px;
            font-weight: 600;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            animation: slideInRight 0.3s ease-out;
            display: none;
        }
        
        /* Responsive para tablets */
        @media (max-width: 992px) {
            .auditoria-grid {
                grid-template-columns: 1fr;
            }
            .col-datos {
                border-right: none;
                border-bottom: 1px solid #e2e8f0;
            }
            .btn-empresa {
                font-size: 12px;
                padding: 10px;
            }
        }
    </style>
</head>
<body>

<div class="auditoria-container">
    
    <div class="card-auditoria">
        
        <!-- Header -->
        <div class="auditoria-header">
            <div>
                <h1 class="mb-0" style="font-size: 28px;">🎮 Corrección Interactiva de Viajes</h1>
                <small class="text-white-50">Pantalla panorámica | Selecciona la empresa correcta según WhatsApp</small>
            </div>
            <a href="index2.php" class="btn btn-outline-light btn-lg px-4">🏠 SALIR</a>
        </div>
        
        <!-- Filtro de fechas -->
        <div class="p-4 bg-white border-bottom">
            <form method="GET" action="" id="formFiltros" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold">📅 Fecha Desde</label>
                    <input type="date" name="desde" class="form-control form-control-lg" value="<?= htmlspecialchars($desde) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">📅 Fecha Hasta</label>
                    <input type="date" name="hasta" class="form-control form-control-lg" value="<?= htmlspecialchars($hasta) ?>" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary btn-lg w-100 py-2 fw-bold">
                        🔍 BUSCAR VIAJES
                    </button>
                </div>
            </form>
        </div>
        
        <?php if ($total_viajes > 0 && $viaje_actual): ?>
        
        <!-- Progreso -->
        <div class="p-4 bg-light border-bottom">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <span class="fw-bold fs-5">📊 Viaje <?= $indice_actual + 1 ?> de <?= $total_viajes ?></span>
                </div>
                <div>
                    <span class="fw-bold text-success fs-5"><?= $progreso ?>% completado</span>
                </div>
            </div>
            <div class="progreso-container">
                <div class="progreso-bar" style="width: <?= $progreso ?>%"></div>
            </div>
        </div>
        
        <!-- Grid de 2 columnas -->
        <div class="auditoria-grid">
            
            <!-- Columna Izquierda: Datos del viaje + Empresa actual -->
            <div class="col-datos">
                
                <div class="info-card">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="info-label">🆔 ID DEL VIAJE</div>
                            <div class="info-value" style="font-size: 32px;">#<?= $viaje_actual['id'] ?></div>
                        </div>
                        <div class="text-end">
                            <div class="info-label">📅 FECHA</div>
                            <div class="info-value"><?= date('d/m/Y', strtotime($viaje_actual['fecha'])) ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">👤 CONDUCTOR</div>
                    <div class="info-value"><?= htmlspecialchars($viaje_actual['nombre']) ?></div>
                    
                    <?php if (!empty($viaje_actual['cedula'])): ?>
                        <div class="info-label mt-3">📋 CÉDULA</div>
                        <div class="info-value"><?= htmlspecialchars($viaje_actual['cedula']) ?></div>
                    <?php endif; ?>
                    
                    <div class="info-label mt-3">🛣️ RUTA</div>
                    <div class="info-value"><?= htmlspecialchars($viaje_actual['ruta']) ?></div>
                    
                    <div class="info-label mt-3">🚐 VEHÍCULO</div>
                    <div class="info-value"><?= htmlspecialchars($viaje_actual['tipo_vehiculo']) ?></div>
                </div>
                
                <!-- Empresa actual (la que está mal) -->
                <div class="empresa-mal">
                    <span class="badge-mal">⚠️ EMPRESA INCORRECTA</span>
                    <div class="empresa-nombre">
                        🏢 <?= !empty($viaje_actual['empresa']) ? htmlspecialchars($viaje_actual['empresa']) : '(VACÍO - SIN EMPRESA)' ?>
                    </div>
                    <div class="text-muted small mt-2">❌ Esta empresa está mal según WhatsApp</div>
                </div>
            </div>
            
            <!-- Columna Derecha: Mensaje WhatsApp -->
            <div class="col-whatsapp">
                <div class="info-label mb-2">💬 MENSAJE ORIGINAL DE WHATSAPP</div>
                <div class="info-label text-muted mb-2 small">📌 COMPARA CON EL MENSAJE PARA SABER LA EMPRESA CORRECTA</div>
                <div class="whatsapp-box">
                    <?php if (!empty($viaje_actual['whatsapp'])): ?>
                        <?= nl2br(htmlspecialchars($viaje_actual['whatsapp'])) ?>
                    <?php else: ?>
                        <span class="text-danger">⚠️ No hay mensaje de WhatsApp guardado para este viaje</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Botones de empresas -->
        <div class="empresas-grid">
            <h4>🖱️ SELECCIONE LA EMPRESA CORRECTA:</h4>
            <div class="row g-2">
                <?php foreach($empresas_disponibles as $emp): ?>
                    <div class="col-md-3 col-lg-2">
                        <button type="button" class="btn-empresa" data-empresa="<?= htmlspecialchars($emp) ?>" data-id="<?= $viaje_actual['id'] ?>">
                            🏢 <?= htmlspecialchars($emp) ?>
                        </button>
                    </div>
                <?php endforeach; ?>
                <div class="col-md-3 col-lg-2">
                    <button type="button" class="btn-empresa" data-empresa="NINGUNA" data-id="<?= $viaje_actual['id'] ?>">
                        🗑️ NINGUNA (vacío)
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Footer navegación -->
        <div class="nav-footer">
            <div>
                <a href="?desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&indice=<?= max(0, $indice_actual - 1) ?>" 
                   class="btn btn-secondary btn-nav <?= $indice_actual <= 0 ? 'disabled' : '' ?>">
                    ◀ ANTERIOR
                </a>
            </div>
            <div class="d-flex gap-3">
                <a href="?desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&indice=<?= min($total_viajes - 1, $indice_actual + 1) ?>" 
                   class="btn btn-warning btn-nav" id="btnSaltar">
                    ⏭️ SALTAR ESTE
                </a>
            </div>
            <div>
                <a href="?desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&indice=<?= min($total_viajes - 1, $indice_actual + 1) ?>" 
                   class="btn btn-primary btn-nav" id="btnSiguienteNormal">
                    SIGUIENTE ▶
                </a>
            </div>
        </div>
        
        <?php elseif ($desde && $hasta && $total_viajes == 0): ?>
            
            <div class="text-center py-5">
                <div style="font-size: 80px;">📭</div>
                <h3 class="mt-3">No hay viajes en este rango de fechas</h3>
                <p class="text-muted">Entre <?= htmlspecialchars($desde) ?> y <?= htmlspecialchars($hasta) ?></p>
            </div>
            
        <?php elseif (!$desde || !$hasta): ?>
            
            <div class="text-center py-5">
                <div style="font-size: 80px;">🎮</div>
                <h3 class="mt-3">Selecciona un rango de fechas para comenzar</h3>
                <p class="text-muted">Elige fecha Desde y Hasta para filtrar los viajes a corregir</p>
            </div>
            
        <?php endif; ?>
        
    </div>
</div>

<!-- Toast flotante -->
<div class="toast-correccion" id="toastCorreccion">
    ✅ Empresa actualizada correctamente
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let corrigiendo = false;

function mostrarToast(mensaje) {
    const toast = document.getElementById('toastCorreccion');
    toast.textContent = mensaje;
    toast.style.display = 'block';
    setTimeout(() => {
        toast.style.display = 'none';
    }, 1500);
}

document.querySelectorAll('.btn-empresa').forEach(btn => {
    btn.addEventListener('click', function() {
        if (corrigiendo) return;
        
        const idViaje = this.dataset.id;
        const empresa = this.dataset.empresa;
        const btnOriginal = this;
        
        corrigiendo = true;
        btnOriginal.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Corrigiendo...';
        btnOriginal.disabled = true;
        
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
                        window.location.href = '?desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&completado=1';
                    }
                } else {
                    mostrarToast('❌ Error al corregir');
                    btnOriginal.innerHTML = '🏢 ' + (empresa === 'NINGUNA' ? 'NINGUNA' : empresa);
                    btnOriginal.disabled = false;
                    corrigiendo = false;
                }
            },
            error: function() {
                mostrarToast('❌ Error de conexión');
                btnOriginal.innerHTML = '🏢 ' + (empresa === 'NINGUNA' ? 'NINGUNA' : empresa);
                btnOriginal.disabled = false;
                corrigiendo = false;
            }
        });
    });
});

<?php if (isset($_GET['completado']) && $_GET['completado'] == 1): ?>
    alert('🎉 ¡CORRECCIÓN MASIVA COMPLETADA!\n\nSe revisaron todos los ' + <?= $total_viajes ?> + ' viajes.');
    window.location.href = 'index2.php';
<?php endif; ?>
</script>

</body>
</html>