<?php
// correccion_masiva.php - Corrección interactiva de empresas en viajes
session_start();
include("conexion.php");

// Obtener todas las empresas disponibles desde empresas_admin
$empresas_disponibles = [];
$res_empresas = $conexion->query("SELECT nombre FROM empresas_admin ORDER BY nombre ASC");
if ($res_empresas) {
    while($row = $res_empresas->fetch_assoc()) {
        $empresas_disponibles[] = $row['nombre'];
    }
}

// Si no hay empresas en la tabla, agregar algunas por defecto
if (empty($empresas_disponibles)) {
    $empresas_disponibles = ['Ninguna', 'Transportes Unidos', 'Transportes del Norte', 'Transportes del Sur', 'Cootransmag', 'Taxis Libres'];
}

// Procesar corrección vía AJAX
if (isset($_POST['ajax_corregir'])) {
    header('Content-Type: application/json');
    $id_viaje = (int)$_POST['id_viaje'];
    $nueva_empresa = $_POST['nueva_empresa'];
    
    if ($nueva_empresa === 'NINGUNA') {
        $nueva_empresa = '';
    }
    
    if ($nueva_empresa === '') {
        $sql = "UPDATE viajes SET empresa = NULL WHERE id = $id_viaje";
    } else {
        $nueva_empresa = $conexion->real_escape_string($nueva_empresa);
        $sql = "UPDATE viajes SET empresa = '$nueva_empresa' WHERE id = $id_viaje";
    }
    
    if ($conexion->query($sql)) {
        echo json_encode(['success' => true, 'mensaje' => 'Corregido']);
    } else {
        echo json_encode(['success' => false, 'mensaje' => $conexion->error]);
    }
    exit();
}

// Obtener viajes según rango de fechas
$viajes = [];
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';
$total_viajes = 0;
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
        $total_viajes = count($viajes);
    }
}

// Ajustar índice actual
if ($indice_actual >= $total_viajes && $total_viajes > 0) {
    $indice_actual = $total_viajes - 1;
}
if ($indice_actual < 0 && $total_viajes > 0) {
    $indice_actual = 0;
}

$viaje_actual = ($total_viajes > 0 && isset($viajes[$indice_actual])) ? $viajes[$indice_actual] : null;
$progreso = $total_viajes > 0 ? round(($indice_actual + 1) / $total_viajes * 100) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>🎮 Corrección Interactiva de Viajes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container-interactivo {
            max-width: 1200px;
            margin: 0 auto;
        }
        .card-viaje {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .card-header-viaje {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            padding: 20px;
        }
        .empresa-actual {
            background: #fff3cd;
            border-left: 5px solid #ffc107;
            padding: 15px;
            border-radius: 10px;
        }
        .empresa-actual-mal {
            background: #f8d7da;
            border-left: 5px solid #dc3545;
        }
        .whatsapp-mensaje {
            background: #dcf8c5;
            padding: 20px;
            border-radius: 15px;
            font-family: monospace;
            font-size: 14px;
            white-space: pre-wrap;
            max-height: 250px;
            overflow-y: auto;
        }
        .btn-empresa {
            padding: 12px;
            margin: 5px;
            border-radius: 10px;
            font-weight: bold;
            transition: all 0.2s;
            border: 2px solid #dee2e6;
            background: white;
        }
        .btn-empresa:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .btn-empresa-correcta {
            background: #28a745;
            color: white;
            border-color: #28a745;
        }
        .progreso-bar {
            height: 10px;
            border-radius: 5px;
            transition: width 0.3s ease;
        }
        .badge-progreso {
            font-size: 14px;
            padding: 8px 15px;
            border-radius: 20px;
        }
        .nav-botones {
            background: #f8f9fa;
            padding: 15px;
            border-top: 1px solid #dee2e6;
        }
        .btn-nav {
            padding: 10px 25px;
            font-weight: bold;
        }
        .toast-notificacion {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            min-width: 300px;
        }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .toast-show {
            animation: slideIn 0.3s ease-out;
        }
        .completado {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        .completado-icono {
            font-size: 80px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="container-interactivo">
    
    <!-- Título -->
    <div class="text-center mb-4">
        <h1 class="text-white">🎮 Corrección Interactiva de Viajes</h1>
        <p class="text-white-50">Selecciona la empresa correcta según el mensaje de WhatsApp</p>
    </div>
    
    <!-- Panel de filtros -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" action="" id="formFiltros" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold">📅 Fecha Desde</label>
                    <input type="date" name="desde" class="form-control" value="<?= htmlspecialchars($desde) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">📅 Fecha Hasta</label>
                    <input type="date" name="hasta" class="form-control" value="<?= htmlspecialchars($hasta) ?>" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                        🔍 BUSCAR VIAJES
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($total_viajes > 0 && $viaje_actual): ?>
        
        <!-- Progreso -->
        <div class="card shadow mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge-progreso bg-primary text-white">
                        📊 Viaje <?= $indice_actual + 1 ?> de <?= $total_viajes ?>
                    </span>
                    <span class="badge-progreso bg-secondary text-white">
                        🎯 <?= $progreso ?>% completado
                    </span>
                </div>
                <div class="progress" style="height: 12px;">
                    <div class="progress-bar progreso-bar bg-success" style="width: <?= $progreso ?>%"></div>
                </div>
            </div>
        </div>
        
        <!-- Tarjeta del viaje actual -->
        <div class="card card-viaje">
            <div class="card-header-viaje">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="mb-0">🆔 Viaje #<?= $viaje_actual['id'] ?></h2>
                    <span class="badge bg-light text-dark">📅 <?= date('d/m/Y', strtotime($viaje_actual['fecha'])) ?></span>
                </div>
                <div class="mt-2">
                    <span class="badge bg-info">👤 <?= htmlspecialchars($viaje_actual['nombre']) ?></span>
                    <span class="badge bg-secondary">🛣️ <?= htmlspecialchars($viaje_actual['ruta']) ?></span>
                    <span class="badge bg-secondary">🚐 <?= htmlspecialchars($viaje_actual['tipo_vehiculo']) ?></span>
                </div>
            </div>
            
            <div class="card-body p-4">
                
                <!-- Empresa actual (la que está mal o bien) -->
                <div class="mb-4">
                    <div class="empresa-actual <?= !empty($viaje_actual['empresa']) ? 'empresa-actual-mal' : '' ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-bold">⚠️ EMPRESA QUE ESTÁ PUESTA AHORA:</span>
                                <div class="mt-2">
                                    <span class="badge bg-danger fs-6 p-2">
                                        🏢 <?= !empty($viaje_actual['empresa']) ? htmlspecialchars($viaje_actual['empresa']) : '❌ (vacío - ninguna empresa)' ?>
                                    </span>
                                    <span class="ms-2 badge bg-warning text-dark">❌ POSIBLEMENTE MAL</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Mensaje de WhatsApp -->
                <div class="mb-4">
                    <label class="fw-bold mb-2">💬 MENSAJE ORIGINAL DE WHATSAPP (para comparar):</label>
                    <div class="whatsapp-mensaje">
                        <?php if (!empty($viaje_actual['whatsapp'])): ?>
                            <?= nl2br(htmlspecialchars($viaje_actual['whatsapp'])) ?>
                        <?php else: ?>
                            <span class="text-muted">⚠️ No hay mensaje de WhatsApp guardado para este viaje</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Botones de empresas -->
                <div class="mb-3">
                    <label class="fw-bold mb-3">🖱️ SELECCIONE LA EMPRESA CORRECTA:</label>
                    <div class="row g-2" id="empresasBotones">
                        <?php foreach($empresas_disponibles as $emp): ?>
                            <div class="col-md-4 col-lg-3">
                                <button type="button" class="btn-empresa w-100" data-empresa="<?= htmlspecialchars($emp) ?>" data-id="<?= $viaje_actual['id'] ?>">
                                    🏢 <?= htmlspecialchars($emp) ?>
                                </button>
                            </div>
                        <?php endforeach; ?>
                        <!-- Opción para dejar vacío -->
                        <div class="col-md-4 col-lg-3">
                            <button type="button" class="btn-empresa w-100" data-empresa="NINGUNA" data-id="<?= $viaje_actual['id'] ?>">
                                🗑️ NINGUNA (dejar vacío)
                            </button>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Botones de navegación -->
            <div class="nav-botones">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <a href="?desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&indice=<?= max(0, $indice_actual - 1) ?>" 
                           class="btn btn-secondary btn-nav <?= $indice_actual <= 0 ? 'disabled' : '' ?>">
                            ◀ ANTERIOR
                        </a>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="?desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&indice=<?= min($total_viajes - 1, $indice_actual + 1) ?>" 
                           class="btn btn-info btn-nav" id="btnSaltar">
                            ⏭️ SALTAR ESTE (sin corregir)
                        </a>
                        <a href="index2.php" class="btn btn-danger btn-nav">
                            🚪 SALIR Y VOLVER AL INICIO
                        </a>
                    </div>
                    <div>
                        <a href="?desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&indice=<?= min($total_viajes - 1, $indice_actual + 1) ?>" 
                           class="btn btn-primary btn-nav" id="btnSiguienteNormal">
                            SIGUIENTE ▶
                        </a>
                    </div>
                </div>
                <p class="text-muted small text-center mt-3 mb-0">
                    💡 Tips: Haz clic en cualquier empresa para CORREGIR automáticamente y pasar al siguiente viaje
                </p>
            </div>
        </div>
        
    <?php elseif ($desde && $hasta && $total_viajes == 0): ?>
        
        <!-- No hay viajes -->
        <div class="card shadow">
            <div class="card-body text-center py-5">
                <div class="completado-icono">📭</div>
                <h3>No hay viajes en este rango de fechas</h3>
                <p class="text-muted">No se encontraron viajes entre el <?= htmlspecialchars($desde) ?> y el <?= htmlspecialchars($hasta) ?></p>
                <a href="index2.php" class="btn btn-primary mt-3">🏠 Volver al inicio</a>
            </div>
        </div>
        
    <?php elseif (!$desde || !$hasta): ?>
        
        <!-- Mensaje inicial -->
        <div class="card shadow">
            <div class="card-body text-center py-5">
                <div class="completado-icono">🎮</div>
                <h3>Selecciona un rango de fechas para comenzar</h3>
                <p class="text-muted">Elige la fecha Desde y Hasta para filtrar los viajes a corregir</p>
                <div class="row justify-content-center mt-3">
                    <div class="col-md-3">
                        <a href="index2.php" class="btn btn-secondary">🏠 Volver al inicio</a>
                    </div>
                </div>
            </div>
        </div>
        
    <?php endif; ?>
    
</div>

<!-- Toast para notificaciones -->
<div class="toast-notificacion" id="toastNotificacion" style="display: none;">
    <div class="toast show toast-show" role="alert">
        <div class="toast-header" id="toastHeader">
            <strong class="me-auto">✅ Corrección</strong>
            <button type="button" class="btn-close" onclick="cerrarToast()"></button>
        </div>
        <div class="toast-body" id="toastMensaje">
            Empresa actualizada correctamente
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Variable para controlar si estamos esperando una corrección
let corrigiendo = false;

// Función para mostrar notificación
function mostrarNotificacion(mensaje, exito = true) {
    const toast = document.getElementById('toastNotificacion');
    const toastMensaje = document.getElementById('toastMensaje');
    const toastHeader = document.getElementById('toastHeader');
    
    toastMensaje.innerText = mensaje;
    
    if (exito) {
        toastHeader.className = 'toast-header bg-success text-white';
        toastHeader.innerHTML = '<strong class="me-auto">✅ ¡Corregido!</strong><button type="button" class="btn-close btn-close-white" onclick="cerrarToast()"></button>';
    } else {
        toastHeader.className = 'toast-header bg-danger text-white';
        toastHeader.innerHTML = '<strong class="me-auto">❌ Error</strong><button type="button" class="btn-close btn-close-white" onclick="cerrarToast()"></button>';
    }
    
    toast.style.display = 'block';
    setTimeout(() => {
        cerrarToast();
    }, 2000);
}

function cerrarToast() {
    const toast = document.getElementById('toastNotificacion');
    toast.style.display = 'none';
}

// Al hacer clic en un botón de empresa
document.querySelectorAll('.btn-empresa').forEach(btn => {
    btn.addEventListener('click', function() {
        if (corrigiendo) return;
        
        const idViaje = this.dataset.id;
        const empresa = this.dataset.empresa;
        const botonOriginal = this;
        
        corrigiendo = true;
        
        // Cambiar estilo del botón para mostrar que se está procesando
        const textoOriginal = botonOriginal.innerHTML;
        botonOriginal.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
        botonOriginal.disabled = true;
        
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
                    mostrarNotificacion('✅ Viaje #' + idViaje + ' actualizado a: ' + nombreEmpresa, true);
                    
                    // Redirigir al siguiente viaje
                    const urlParams = new URLSearchParams(window.location.search);
                    let indiceActual = parseInt(urlParams.get('indice')) || 0;
                    const totalViajes = <?= $total_viajes ?>;
                    
                    if (indiceActual + 1 < totalViajes) {
                        urlParams.set('indice', indiceActual + 1);
                        window.location.href = '?' + urlParams.toString();
                    } else {
                        // Terminó todos los viajes
                        window.location.href = '?desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&completado=1';
                    }
                } else {
                    mostrarNotificacion('❌ Error: ' + response.mensaje, false);
                    botonOriginal.innerHTML = textoOriginal;
                    botonOriginal.disabled = false;
                    corrigiendo = false;
                }
            },
            error: function() {
                mostrarNotificacion('❌ Error de conexión', false);
                botonOriginal.innerHTML = textoOriginal;
                botonOriginal.disabled = false;
                corrigiendo = false;
            }
        });
    });
});

// Prevenir doble clic en botones de navegación
document.querySelectorAll('.btn-nav').forEach(btn => {
    btn.addEventListener('click', function(e) {
        if (corrigiendo) {
            e.preventDefault();
            mostrarNotificacion('⏳ Espera a que termine la corrección actual', false);
            return false;
        }
    });
});

// Confirmar al salir
document.querySelectorAll('a[href*="index2.php"]').forEach(link => {
    link.addEventListener('click', function(e) {
        if (<?= $total_viajes > 0 && $indice_actual < $total_viajes - 1 ? 'true' : 'false' ?>) {
            if (!confirm('⚠️ Aún quedan viajes por revisar. ¿Seguro que quieres salir?')) {
                e.preventDefault();
            }
        }
    });
});

// Si se completó, mostrar mensaje
<?php if (isset($_GET['completado']) && $_GET['completado'] == 1): ?>
    Swal.fire({
        icon: 'success',
        title: '🎉 ¡Corrección masiva completada!',
        text: 'Se revisaron todos los viajes del rango seleccionado',
        confirmButtonText: 'Volver al inicio'
    }).then(() => {
        window.location.href = 'index2.php';
    });
<?php endif; ?>
</script>

<!-- SweetAlert para mejor experiencia -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</body>
</html>