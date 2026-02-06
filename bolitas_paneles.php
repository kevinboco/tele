<?php
// ================================================
// ARCHIVO: bolitas_paneles.php
// CONTIENE: TODO el sistema de bolitas flotantes
//           y sus paneles desplegables
// USO: Incluir en liquidacion.php
// ================================================

// ===== FUNCIONES PARA MANEJAR CLASIFICACIONES =====
function obtenerConexion() {
    // Ajusta esto a tu conexión
    $host = 'localhost';
    $dbname = 'tu_base_de_datos';
    $username = 'tu_usuario';
    $password = 'tu_password';
    
    try {
        $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch(PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

// Manejar peticiones AJAX para clasificaciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = obtenerConexion();
    
    if (isset($_POST['renombrar_clasificacion'])) {
        $viejo_nombre = strtolower(trim($_POST['viejo_nombre']));
        $nuevo_nombre = strtolower(trim($_POST['nuevo_nombre']));
        
        try {
            $conn->beginTransaction();
            
            // 1. Renombrar en tabla tarifas
            $stmt = $conn->prepare("UPDATE tarifas SET campo = :nuevo WHERE campo = :viejo");
            $stmt->execute([':nuevo' => $nuevo_nombre, ':viejo' => $viejo_nombre]);
            
            // 2. Renombrar en tabla rutas_clasificadas
            $stmt = $conn->prepare("UPDATE rutas_clasificadas SET clasificacion = :nuevo WHERE clasificacion = :viejo");
            $stmt->execute([':nuevo' => $nuevo_nombre, ':viejo' => $viejo_nombre]);
            
            // 3. Renombrar en config_columnas (si existe)
            $stmt = $conn->prepare("UPDATE config_columnas SET columna = :nuevo WHERE columna = :viejo");
            $stmt->execute([':nuevo' => $nuevo_nombre, ':viejo' => $viejo_nombre]);
            
            // 4. Actualizar variable de sesión si existe
            if (isset($_SESSION['columnas_seleccionadas'])) {
                $clave = array_search($viejo_nombre, $_SESSION['columnas_seleccionadas']);
                if ($clave !== false) {
                    $_SESSION['columnas_seleccionadas'][$clave] = $nuevo_nombre;
                }
            }
            
            $conn->commit();
            echo 'ok';
        } catch(Exception $e) {
            $conn->rollBack();
            echo 'error: ' . $e->getMessage();
        }
        exit;
    }
    
    if (isset($_POST['eliminar_clasificacion'])) {
        $nombre = strtolower(trim($_POST['nombre_clasificacion']));
        
        try {
            $conn->beginTransaction();
            
            // 1. Eliminar de tarifas
            $stmt = $conn->prepare("DELETE FROM tarifas WHERE campo = :nombre");
            $stmt->execute([':nombre' => $nombre]);
            
            // 2. Eliminar clasificación de rutas (dejar sin clasificar)
            $stmt = $conn->prepare("DELETE FROM rutas_clasificadas WHERE clasificacion = :nombre");
            $stmt->execute([':nombre' => $nombre]);
            
            // 3. Eliminar de config_columnas
            $stmt = $conn->prepare("DELETE FROM config_columnas WHERE columna = :nombre");
            $stmt->execute([':nombre' => $nombre]);
            
            // 4. Actualizar variable de sesión si existe
            if (isset($_SESSION['columnas_seleccionadas'])) {
                $_SESSION['columnas_seleccionadas'] = array_values(
                    array_filter($_SESSION['columnas_seleccionadas'], function($col) use ($nombre) {
                        return $col !== $nombre;
                    })
                );
            }
            
            $conn->commit();
            echo 'ok';
        } catch(Exception $e) {
            $conn->rollBack();
            echo 'error: ' . $e->getMessage();
        }
        exit;
    }
}
?>

<!-- ===== ESTILOS PARA LAS BOLITAS Y PANELES ===== -->
<style>
/* ===== ESTILOS ORIGINALES DE LAS BOLITAS Y PANELES ===== */
.floating-balls-container {
    position: fixed;
    left: 20px;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    flex-direction: column;
    gap: 15px;
    z-index: 9998;
}

.floating-ball {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 3px solid white;
    position: relative;
    z-index: 9999;
    overflow: hidden;
    user-select: none;
}

.floating-ball:hover {
    transform: scale(1.15) translateY(-2px);
    box-shadow: 0 12px 25px rgba(0, 0, 0, 0.3);
}

.floating-ball:active {
    transform: scale(0.95);
}

.ball-content {
    font-size: 24px;
    font-weight: bold;
    color: white;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    display: flex;
    align-items: center;
    justify-content: center;
}

.ball-tooltip {
    position: absolute;
    left: 70px;
    top: 50%;
    transform: translateY(-50%);
    background: white;
    color: #1e293b;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border: 1px solid #e2e8f0;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s;
    pointer-events: none;
    z-index: 10000;
}

.floating-ball:hover .ball-tooltip {
    opacity: 1;
    visibility: visible;
    left: 75px;
}

/* Colores específicos para cada bolita */
.ball-tarifas {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
}

.ball-crear-clasif {
    background: linear-gradient(135deg, #10b981, #059669);
}

.ball-clasif-rutas {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.ball-selector-columnas {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
}

/* ===== PANELES DESLIZANTES ===== */
.side-panel-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.4);
    z-index: 9997;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s;
}

.side-panel-overlay.active {
    opacity: 1;
    visibility: visible;
}

.side-panel {
    position: fixed;
    left: -450px;
    top: 0;
    width: 420px;
    height: 100vh;
    background: white;
    box-shadow: 4px 0 25px rgba(0, 0, 0, 0.15);
    z-index: 9998;
    transition: left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    overflow-y: auto;
    overflow-x: hidden;
}

.side-panel.active {
    left: 0;
}

.side-panel-header {
    position: sticky;
    top: 0;
    background: white;
    border-bottom: 1px solid #e2e8f0;
    padding: 1.25rem;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.side-panel-body {
    padding: 1.25rem;
    padding-bottom: 2rem;
}

.side-panel-close {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    color: #64748b;
}

.side-panel-close:hover {
    background: #e2e8f0;
    color: #1e293b;
}

/* ===== TABLA CENTRAL CON ANIMACIÓN ===== */
.table-container-wrapper {
    transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    margin-left: 0;
}

.table-container-wrapper.with-panel {
    margin-left: 420px;
}

/* Indicador de panel activo */
.ball-active {
    animation: pulse-ball 2s infinite;
    box-shadow: 0 0 0 8px rgba(59, 130, 246, 0.2);
}

@keyframes pulse-ball {
    0%, 100% { box-shadow: 0 8px 20px rgba(0,0,0,0.2), 0 0 0 0 rgba(59, 130, 246, 0.4); }
    50% { box-shadow: 0 8px 20px rgba(0,0,0,0.2), 0 0 0 12px rgba(59, 130, 246, 0); }
}

/* ===== ACORDEÓN PARA TARIFAS ===== */
.tarjeta-tarifa-acordeon {
    transition: all 0.3s ease;
}

.tarjeta-tarifa-acordeon:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.acordeon-header {
    transition: background-color 0.2s ease;
}

.acordeon-content {
    transition: all 0.3s ease;
    max-height: 0;
    overflow: hidden;
}

.acordeon-content.expanded {
    max-height: 2000px;
}

.acordeon-icon {
    transition: transform 0.3s ease;
}

.acordeon-icon.expanded {
    transform: rotate(90deg);
}

/* ===== COLORES PARA FILAS DE CLASIFICACIÓN DE RUTAS ===== */
.fila-clasificada-completo {
    background-color: rgba(209, 250, 229, 0.3) !important;
    border-left: 4px solid #10b981 !important;
}

.fila-clasificada-medio {
    background-color: rgba(254, 243, 199, 0.3) !important;
    border-left: 4px solid #f59e0b !important;
}

.fila-clasificada-extra {
    background-color: rgba(241, 245, 249, 0.3) !important;
    border-left: 4px solid #64748b !important;
}

.fila-clasificada-siapana {
    background-color: rgba(250, 232, 255, 0.3) !important;
    border-left: 4px solid #d946ef !important;
}

.fila-clasificada-carrotanque {
    background-color: rgba(207, 250, 254, 0.3) !important;
    border-left: 4px solid #06b6d4 !important;
}

.fila-clasificada-riohacha {
    background-color: rgba(224, 231, 255, 0.3) !important;
    border-left: 4px solid #4f46e5 !important;
}

.fila-clasificada-pru {
    background-color: rgba(204, 251, 241, 0.3) !important;
    border-left: 4px solid #14b8a6 !important;
}

.fila-clasificada-maco {
    background-color: rgba(255, 228, 230, 0.3) !important;
    border-left: 4px solid #f43f5e !important;
}

/* ===== NUEVOS ESTILOS PARA SELECTOR DE COLUMNAS ===== */
.columna-checkbox-item {
    transition: all 0.2s ease;
}

.columna-checkbox-item:hover {
    background-color: #f8fafc;
}

.columna-checkbox-item.selected {
    background-color: #eff6ff;
    border-color: #3b82f6;
}

.checkbox-columna {
    width: 18px;
    height: 18px;
    border-radius: 4px;
    border: 2px solid #cbd5e1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.checkbox-columna.checked {
    background-color: #3b82f6;
    border-color: #3b82f6;
}

.checkbox-columna.checked::after {
    content: "✓";
    color: white;
    font-size: 12px;
    font-weight: bold;
}

/* Estilo para columnas ocultas en la tabla */
.columna-oculta {
    display: none !important;
}

.columna-visualizada {
    display: table-cell !important;
}

/* ===== ESTILOS PARA BOTONES DE ACCIÓN EN TARIFAS ===== */
.tarifa-acciones {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    gap: 5px;
    opacity: 0;
    transition: opacity 0.2s;
}

.tarifa-item:hover .tarifa-acciones {
    opacity: 1;
}

.btn-editar-tarifa, .btn-eliminar-tarifa {
    width: 24px;
    height: 24px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;
}

.btn-editar-tarifa {
    background-color: #f0f9ff;
    color: #0369a1;
    border: 1px solid #bae6fd;
}

.btn-editar-tarifa:hover {
    background-color: #e0f2fe;
    color: #075985;
}

.btn-eliminar-tarifa {
    background-color: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

.btn-eliminar-tarifa:hover {
    background-color: #fee2e2;
    color: #b91c1c;
}

/* ===== MODALES PARA EDITAR/ELIMINAR ===== */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 10050;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-overlay.active {
    display: flex;
}

.modal-container {
    background: white;
    border-radius: 12px;
    width: 100%;
    max-width: 400px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    overflow: hidden;
    animation: modal-appear 0.3s ease-out;
    z-index: 10051;
}

@keyframes modal-appear {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    padding: 1.25rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modal-body {
    padding: 1.25rem;
}

.modal-footer {
    padding: 1rem 1.25rem;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.btn-modal {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid transparent;
}

.btn-modal-primary {
    background-color: #3b82f6;
    color: white;
}

.btn-modal-primary:hover {
    background-color: #2563eb;
}

.btn-modal-secondary {
    background-color: #f1f5f9;
    color: #475569;
    border-color: #cbd5e1;
}

.btn-modal-secondary:hover {
    background-color: #e2e8f0;
}

.btn-modal-danger {
    background-color: #ef4444;
    color: white;
}

.btn-modal-danger:hover {
    background-color: #dc2626;
}

/* Estilo para items de tarifa */
.tarifa-item {
    position: relative;
    padding-right: 70px; /* Espacio para los botones */
}

/* Responsive */
@media (max-width: 768px) {
    .floating-balls-container {
        bottom: 20px;
        top: auto;
        left: 50%;
        transform: translateX(-50%);
        flex-direction: row;
        gap: 10px;
    }
    
    .floating-ball {
        width: 50px;
        height: 50px;
    }
    
    .ball-content {
        font-size: 20px;
    }
    
    .side-panel {
        width: 90%;
        max-width: 400px;
        left: -100%;
    }
    
    .table-container-wrapper.with-panel {
        margin-left: 0;
    }
    
    .ball-tooltip {
        display: none;
    }
    
    .tarifa-acciones {
        opacity: 1; /* Siempre visibles en móvil */
    }
}
</style>

<!-- ===== BOLITAS FLOTANTES ===== -->
<div class="floating-balls-container">
    <div class="floating-ball ball-tarifas" id="ball-tarifas" data-panel="tarifas">
        <div class="ball-content">🚐</div>
        <div class="ball-tooltip">Tarifas por tipo de vehículo</div>
    </div>
    
    <div class="floating-ball ball-crear-clasif" id="ball-crear-clasif" data-panel="crear-clasif">
        <div class="ball-content">➕</div>
        <div class="ball-tooltip">Crear nueva clasificación</div>
    </div>
    
    <div class="floating-ball ball-clasif-rutas" id="ball-clasif-rutas" data-panel="clasif-rutas">
        <div class="ball-content">🧭</div>
        <div class="ball-tooltip">Clasificar rutas existentes</div>
    </div>
    
    <div class="floating-ball ball-selector-columnas" id="ball-selector-columnas" data-panel="selector-columnas">
        <div class="ball-content">📊</div>
        <div class="ball-tooltip">Seleccionar columnas</div>
    </div>
</div>

<!-- ===== MODALES PARA EDITAR/ELIMINAR CLASIFICACIONES ===== -->
<div class="modal-overlay" id="modalEditarClasificacion">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="text-lg font-semibold">✏️ Editar Clasificación</h3>
            <button class="side-panel-close" onclick="cerrarModal('modalEditarClasificacion')">✕</button>
        </div>
        <div class="modal-body">
            <div class="mb-4">
                <label class="block text-sm font-medium mb-2">Nombre actual</label>
                <input type="text" id="clasificacionActual" class="w-full rounded-xl border border-slate-300 px-4 py-2 text-sm bg-slate-100" readonly>
            </div>
            <div>
                <label class="block text-sm font-medium mb-2">Nuevo nombre</label>
                <input type="text" id="nuevoNombreClasificacion" class="w-full rounded-xl border border-slate-300 px-4 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500" placeholder="Ingresa el nuevo nombre">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-modal btn-modal-secondary" onclick="cerrarModal('modalEditarClasificacion')">Cancelar</button>
            <button class="btn-modal btn-modal-primary" onclick="guardarCambiosClasificacion()">Guardar Cambios</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modalEliminarClasificacion">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="text-lg font-semibold">🗑️ Eliminar Clasificación</h3>
            <button class="side-panel-close" onclick="cerrarModal('modalEliminarClasificacion')">✕</button>
        </div>
        <div class="modal-body">
            <p class="text-slate-600 mb-4">¿Estás seguro de que deseas eliminar la clasificación <strong id="nombreClasificacionEliminar"></strong>?</p>
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
                <p class="text-sm text-amber-800">
                    ⚠️ <strong>Advertencia:</strong> Al eliminar esta clasificación:
                </p>
                <ul class="text-sm text-amber-700 mt-2 space-y-1">
                    <li>• Todas las rutas que tengan esta clasificación quedarán <strong>sin clasificar</strong></li>
                    <li>• Se eliminará de la lista de columnas seleccionables</li>
                    <li>• Los valores de tarifa para esta clasificación se perderán</li>
                </ul>
            </div>
            <div class="text-sm text-slate-500">
                Rutas afectadas: <span id="contadorRutasAfectadas" class="font-semibold">0</span>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-modal btn-modal-secondary" onclick="cerrarModal('modalEliminarClasificacion')">Cancelar</button>
            <button class="btn-modal btn-modal-danger" onclick="confirmarEliminarClasificacion()">Eliminar Permanentemente</button>
        </div>
    </div>
</div>

<!-- ===== PANEL DE TARIFAS ===== -->
<div class="side-panel" id="panel-tarifas">
    <div class="side-panel-header">
        <h3 class="text-lg font-semibold flex items-center gap-2">
            <span>🚐 Tarifas por Tipo de Vehículo</span>
            <span class="text-xs text-slate-500">(<?= count($columnas_tarifas) ?> tipos de tarifas)</span>
        </h3>
        <button class="side-panel-close" data-panel="tarifas">✕</button>
    </div>
    <div class="side-panel-body">
        <div class="flex justify-between items-center mb-4">
            <div class="text-sm text-slate-600">
                <span id="contadorClasificaciones"><?= count($clasificaciones_disponibles) ?></span> clasificaciones disponibles
            </div>
            <div class="flex gap-2">
                <button onclick="expandirTodosTarifas()" 
                        class="text-xs px-3 py-1.5 rounded-lg border border-green-300 hover:bg-green-50 transition text-green-600">
                    Expandir todos
                </button>
                <button onclick="colapsarTodosTarifas()" 
                        class="text-xs px-3 py-1.5 rounded-lg border border-amber-300 hover:bg-amber-50 transition text-amber-600">
                    Colapsar todos
                </button>
            </div>
        </div>
        
        <div id="tarifas_grid" class="grid grid-cols-1 gap-3">
            <?php foreach ($vehiculos as $index => $veh):
                $color_vehiculo = obtenerColorVehiculo($veh);
                $t = $tarifas_guardadas[$veh] ?? [];
                $veh_id = preg_replace('/[^a-z0-9]/i', '-', strtolower($veh));
            ?>
            <div class="tarjeta-tarifa-acordeon rounded-xl border <?= $color_vehiculo['border'] ?> overflow-hidden shadow-sm"
                 data-vehiculo="<?= htmlspecialchars($veh) ?>"
                 id="acordeon-<?= $veh_id ?>"
                 style="background-color: <?= str_replace('bg-', '#', $color_vehiculo['dark']) ?>;">
                
                <div class="acordeon-header flex items-center justify-between px-4 py-3.5 cursor-pointer transition <?= $color_vehiculo['bg'] ?> hover:opacity-90"
                     onclick="toggleAcordeon('<?= $veh_id ?>')"
                     style="background-color: <?= str_replace('bg-', '#', $color_vehiculo['bg']) ?>;">
                    <div class="flex items-center gap-3">
                        <span class="acordeon-icon text-lg transition-transform duration-300 <?= $color_vehiculo['text'] ?>" id="icon-<?= $veh_id ?>">▶️</span>
                        <div>
                            <div class="text-base font-semibold <?= $color_vehiculo['text'] ?>">
                                <?= htmlspecialchars($veh) ?>
                            </div>
                            <div class="text-xs text-slate-500 mt-0.5">
                                <?= count($columnas_tarifas) ?> tipos de tarifas configurados
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs px-2 py-1 rounded-full <?= $color_vehiculo['text'] ?> border <?= $color_vehiculo['border'] ?> bg-white/80">
                            Configurar
                        </span>
                    </div>
                </div>
                
                <div class="acordeon-content px-4 py-3 border-t <?= $color_vehiculo['border'] ?> bg-white" id="content-<?= $veh_id ?>">
                    <div class="space-y-3">
                        <?php foreach ($columnas_tarifas as $columna): 
                            $valor = isset($t[$columna]) ? (float)$t[$columna] : 0;
                            $etiqueta = ucfirst($columna);
                            
                            $etiquetas_especiales = [
                                'completo' => 'Viaje Completo',
                                'medio' => 'Viaje Medio',
                                'extra' => 'Viaje Extra',
                                'carrotanque' => 'Carrotanque',
                                'siapana' => 'Siapana',
                                'riohacha' => 'Riohacha',
                                'pru' => 'Pru',
                                'maco' => 'Maco'
                            ];
                            
                            $etiqueta_final = $etiquetas_especiales[$columna] ?? $etiqueta;
                            $estilo_clasif = obtenerEstiloClasificacion($columna);
                        ?>
                        <div class="tarifa-item" data-clasificacion="<?= htmlspecialchars($columna) ?>">
                            <label class="block">
                                <span class="block text-sm font-medium mb-1 <?= $estilo_clasif['text'] ?>">
                                    <?= htmlspecialchars($etiqueta_final) ?>
                                </span>
                                <div class="relative">
                                    <input type="number" step="1000" value="<?= $valor ?>"
                                           data-campo="<?= htmlspecialchars($columna) ?>"
                                           class="w-full rounded-xl border <?= $estilo_clasif['border'] ?> px-3 py-2 pr-10 text-right bg-white outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition tarifa-input"
                                           style="border-color: <?= str_replace('border-', '#', $estilo_clasif['border']) ?>;"
                                           oninput="recalcular()">
                                    <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-sm font-semibold <?= $estilo_clasif['text'] ?>">
                                        $
                                    </span>
                                </div>
                            </label>
                            <div class="tarifa-acciones">
                                <button class="btn-editar-tarifa" 
                                        onclick="editarClasificacion('<?= htmlspecialchars($columna) ?>', '<?= htmlspecialchars($etiqueta_final) ?>')"
                                        title="Editar nombre">
                                    ✏️
                                </button>
                                <button class="btn-eliminar-tarifa" 
                                        onclick="eliminarClasificacion('<?= htmlspecialchars($columna) ?>', '<?= htmlspecialchars($etiqueta_final) ?>')"
                                        title="Eliminar clasificación">
                                    🗑️
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <p class="text-xs text-slate-500 mt-4">
            Los cambios se guardan automáticamente al modificar cualquier valor.
        </p>
    </div>
</div>

<!-- ===== PANEL CREAR CLASIFICACIÓN ===== -->
<div class="side-panel" id="panel-crear-clasif">
    <div class="side-panel-header">
        <h3 class="text-lg font-semibold flex items-center gap-2">
            <span>➕ Crear Nueva Clasificación</span>
            <span class="text-xs text-slate-500">Dinámico</span>
        </h3>
        <button class="side-panel-close" data-panel="crear-clasif">✕</button>
    </div>
    <div class="side-panel-body">
        <p class="text-sm text-slate-600 mb-4">
            Crea una nueva clasificación. Se agregará a la tabla tarifas.
        </p>

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-2">Nombre de la nueva clasificación</label>
                <input id="txt_nueva_clasificacion" type="text"
                       class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500"
                       placeholder="Ej: Premium, Nocturno, Express...">
            </div>
            <div>
                <label class="block text-sm font-medium mb-2">Texto que deben contener las rutas (opcional)</label>
                <input id="txt_patron_ruta" type="text"
                       class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500"
                       placeholder="Dejar vacío para solo crear la clasificación">
            </div>
            <button type="button"
                    onclick="crearYAsignarClasificacion()"
                    class="w-full inline-flex items-center justify-center rounded-xl bg-green-600 text-white px-4 py-3 text-sm font-semibold hover:bg-green-700 active:bg-green-800 focus:ring-4 focus:ring-green-200 transition">
                ⚙️ Crear y Aplicar
            </button>
        </div>

        <p class="text-xs text-slate-500 mt-4">
            La nueva clasificación se creará en la tabla tarifas. Vuelve a dar <strong>Filtrar</strong> para ver los cambios.
        </p>
    </div>
</div>

<!-- ===== PANEL CLASIFICACIÓN RUTAS ===== -->
<div class="side-panel" id="panel-clasif-rutas">
    <div class="side-panel-header">
        <h3 class="text-lg font-semibold flex items-center gap-2">
            <span>🧭 Clasificar Rutas Existentes</span>
            <span class="text-xs text-slate-500"><?= count($rutasUnicas) ?> rutas</span>
        </h3>
        <button class="side-panel-close" data-panel="clasif-rutas">✕</button>
    </div>
    <div class="side-panel-body">
        <div class="max-h-[calc(100vh-180px)] overflow-y-auto border border-slate-200 rounded-xl">
            <table class="w-full text-sm">
                <thead class="bg-slate-100 text-slate-600 sticky top-0 z-10">
                    <tr>
                        <th class="px-3 py-2 text-left sticky top-0 bg-slate-100">Ruta</th>
                        <th class="px-3 py-2 text-center sticky top-0 bg-slate-100">Vehículo</th>
                        <th class="px-3 py-2 text-center sticky top-0 bg-slate-100">Clasificación</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="tablaClasificacionRutas">
                <?php foreach($rutasUnicas as $info): 
                    $clasificacion_actual = $info['clasificacion'] ?? '';
                    $estilo = obtenerEstiloClasificacion($clasificacion_actual);
                    $clase_fila = $clasificacion_actual ? 'fila-clasificada-' . $clasificacion_actual : '';
                ?>
                    <tr class="fila-ruta hover:bg-slate-50 <?= $clase_fila ?>"
                        data-ruta="<?= htmlspecialchars($info['ruta']) ?>"
                        data-vehiculo="<?= htmlspecialchars($info['vehiculo']) ?>"
                        data-clasificacion="<?= htmlspecialchars($clasificacion_actual) ?>">
                        <td class="px-3 py-2 whitespace-nowrap text-left font-medium">
                            <?= htmlspecialchars($info['ruta']) ?>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <?php 
                                $color_vehiculo = obtenerColorVehiculo($info['vehiculo']);
                            ?>
                            <span class="inline-block px-2 py-1 rounded-md text-xs font-medium <?= $color_vehiculo['bg'] ?> <?= $color_vehiculo['text'] ?> border <?= $color_vehiculo['border'] ?>">
                                <?= htmlspecialchars($info['vehiculo']) ?>
                            </span>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <select class="select-clasif-ruta rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-100 w-full transition-all duration-300"
                                    data-ruta="<?= htmlspecialchars($info['ruta']) ?>"
                                    data-vehiculo="<?= htmlspecialchars($info['vehiculo']) ?>"
                                    onchange="actualizarColorFila(this)">
                                <option value="">Sin clasificar</option>
                                <?php foreach ($clasificaciones_disponibles as $clasif): 
                                    $estilo_opcion = obtenerEstiloClasificacion($clasif);
                                ?>
                                <option value="<?= htmlspecialchars($clasif) ?>" 
                                        <?= $info['clasificacion']===$clasif ? 'selected' : '' ?>
                                        style="background-color: <?= str_replace('bg-', '#', $estilo_opcion['bg']) ?>20; color: <?= str_replace('text-', '#', $estilo_opcion['text']) ?>; font-weight: 600;">
                                    <?= htmlspecialchars(ucfirst($clasif)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="text-xs text-slate-500 mt-4">
            Selecciona una clasificación para cada ruta. Los cambios se guardan automáticamente y la fila cambiará de color.
        </p>
    </div>
</div>

<!-- ===== PANEL SELECTOR DE COLUMNAS ===== -->
<div class="side-panel" id="panel-selector-columnas">
    <div class="side-panel-header">
        <h3 class="text-lg font-semibold flex items-center gap-2">
            <span>📊 Seleccionar Columnas</span>
            <span class="text-xs text-slate-500">Personalizar tabla</span>
        </h3>
        <button class="side-panel-close" data-panel="selector-columnas">✕</button>
    </div>
    <div class="side-panel-body">
        <div class="flex flex-col gap-4">
            <div>
                <p class="text-sm text-slate-600 mb-3">
                    Marca/desmarca las columnas que quieres ver en la tabla principal.
                    <span id="contador-seleccionadas-panel" class="font-semibold text-blue-600"><?= count($columnas_seleccionadas) ?></span> de 
                    <?= count($clasificaciones_disponibles) ?> seleccionadas
                </p>
            </div>
            
            <div class="flex flex-wrap gap-2">
                <button onclick="seleccionarTodasColumnas()" 
                        class="text-xs px-3 py-1.5 rounded-lg border border-green-300 bg-green-50 text-green-700 hover:bg-green-100 transition whitespace-nowrap">
                    ✅ Seleccionar todas
                </button>
                <button onclick="deseleccionarTodasColumnas()" 
                        class="text-xs px-3 py-1.5 rounded-lg border border-rose-300 bg-rose-50 text-rose-700 hover:bg-rose-100 transition whitespace-nowrap">
                    ❌ Deseleccionar todas
                </button>
                <button onclick="guardarSeleccionColumnas()" 
                        class="text-xs px-3 py-1.5 rounded-lg border border-blue-300 bg-blue-50 text-blue-700 hover:bg-blue-100 transition whitespace-nowrap">
                    💾 Guardar selección
                </button>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-[60vh] overflow-y-auto p-2 border border-slate-200 rounded-lg" id="listaColumnasSeleccionables">
                <?php foreach ($clasificaciones_disponibles as $clasif): 
                    $estilo = obtenerEstiloClasificacion($clasif);
                    $seleccionada = in_array($clasif, $columnas_seleccionadas);
                ?>
                <div class="columna-checkbox-item flex items-center gap-2 p-3 border border-slate-200 rounded-lg cursor-pointer transition <?= $seleccionada ? 'selected' : '' ?>"
                     data-columna="<?= htmlspecialchars($clasif) ?>"
                     onclick="toggleColumna('<?= htmlspecialchars($clasif) ?>')"
                     title="<?= htmlspecialchars(ucfirst($clasif)) ?>">
                    <div class="checkbox-columna <?= $seleccionada ? 'checked' : '' ?>" 
                         id="checkbox-<?= htmlspecialchars($clasif) ?>"></div>
                    <div class="flex-1 flex flex-col">
                        <span class="text-sm font-medium whitespace-nowrap <?= $estilo['text'] ?>">
                            <?php 
                                $nombres = [
                                    'completo' => 'Viaje Completo',
                                    'medio' => 'Viaje Medio', 
                                    'extra' => 'Viaje Extra',
                                    'carrotanque' => 'Carrotanque',
                                    'siapana' => 'Siapana',
                                    'riohacha' => 'Riohacha',
                                    'pru' => 'Pru',
                                    'maco' => 'Maco'
                                ];
                                echo $nombres[$clasif] ?? ucfirst($clasif);
                            ?>
                        </span>
                        <span class="text-xs text-slate-500 mt-0.5">Columna: <?= htmlspecialchars($clasif) ?></span>
                    </div>
                    <div class="w-3 h-3 rounded-full" style="background-color: <?= str_replace('bg-', '#', $estilo['bg']) ?>;"></div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <p class="text-xs text-slate-500 mt-2">
                La selección se aplica inmediatamente. Usa "Guardar selección" para recordarla en futuras visitas.
            </p>
        </div>
    </div>
</div>

<!-- ===== OVERLAY PARA PANELES ===== -->
<div class="side-panel-overlay" id="sidePanelOverlay"></div>

<!-- ===== JAVASCRIPT PARA LAS BOLITAS Y PANELES ===== -->
<script>
// ===== SISTEMA DE BOLITAS Y PANELES =====
let activePanel = null;
const panels = ['tarifas', 'crear-clasif', 'clasif-rutas', 'selector-columnas'];
let clasificacionEditando = null; // Variable global para saber qué clasificación estamos editando

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar sistema de paneles
    panels.forEach(panelId => {
        const ball = document.getElementById(`ball-${panelId}`);
        const panel = document.getElementById(`panel-${panelId}`);
        const closeBtn = panel.querySelector('.side-panel-close');
        const overlay = document.getElementById('sidePanelOverlay');
        
        ball.addEventListener('click', () => togglePanel(panelId));
        closeBtn.addEventListener('click', () => togglePanel(panelId));
        overlay.addEventListener('click', () => {
            if (activePanel === panelId) {
                togglePanel(panelId);
            }
        });
    });
    
    // Cerrar con ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && activePanel) {
            togglePanel(activePanel);
        }
    });
    
    // Inicializar componentes
    colapsarTodosTarifas();
    inicializarColoresClasificacion();
    inicializarSeleccionColumnas();
    configurarEventosTarifas();
    actualizarContadorClasificaciones();
});

// ===== FUNCIÓN PRINCIPAL PARA ABRIR/CERRAR PANELES =====
function togglePanel(panelId) {
    const ball = document.getElementById(`ball-${panelId}`);
    const panel = document.getElementById(`panel-${panelId}`);
    const overlay = document.getElementById('sidePanelOverlay');
    const tableWrapper = document.getElementById('tableContainerWrapper');
    
    if (activePanel === panelId) {
        // Cerrar panel actual
        panel.classList.remove('active');
        ball.classList.remove('ball-active');
        overlay.classList.remove('active');
        if (tableWrapper) tableWrapper.classList.remove('with-panel');
        activePanel = null;
    } else {
        // Cerrar panel anterior si existe
        if (activePanel) {
            document.getElementById(`panel-${activePanel}`).classList.remove('active');
            document.getElementById(`ball-${activePanel}`).classList.remove('ball-active');
        }
        
        // Abrir nuevo panel
        panel.classList.add('active');
        ball.classList.add('ball-active');
        overlay.classList.add('active');
        if (tableWrapper) tableWrapper.classList.add('with-panel');
        activePanel = panelId;
        
        // Scroll al inicio del panel
        setTimeout(() => {
            panel.scrollTop = 0;
        }, 100);
    }
}

// ===== FUNCIONES PARA EL ACORDEÓN DE TARIFAS =====
function toggleAcordeon(vehiculoId) {
    const content = document.getElementById('content-' + vehiculoId);
    const icon = document.getElementById('icon-' + vehiculoId);
    
    if (content.classList.contains('expanded')) {
        content.classList.remove('expanded');
        icon.classList.remove('expanded');
        content.style.maxHeight = '0';
    } else {
        content.classList.add('expanded');
        icon.classList.add('expanded');
        content.style.maxHeight = content.scrollHeight + 'px';
    }
}

function expandirTodosTarifas() {
    document.querySelectorAll('.acordeon-content').forEach(content => {
        if (!content.classList.contains('expanded')) {
            content.classList.add('expanded');
            content.style.maxHeight = content.scrollHeight + 'px';
            
            const vehiculoId = content.id.replace('content-', '');
            const icon = document.getElementById('icon-' + vehiculoId);
            if (icon) icon.classList.add('expanded');
        }
    });
}

function colapsarTodosTarifas() {
    document.querySelectorAll('.acordeon-content').forEach(content => {
        if (content.classList.contains('expanded')) {
            content.classList.remove('expanded');
            content.style.maxHeight = '0';
            
            const vehiculoId = content.id.replace('content-', '');
            const icon = document.getElementById('icon-' + vehiculoId);
            if (icon) icon.classList.remove('expanded');
        }
    });
}

// ===== FUNCIONES PARA EDITAR Y ELIMINAR CLASIFICACIONES =====
function editarClasificacion(clasificacion, nombreActual) {
    clasificacionEditando = clasificacion;
    document.getElementById('clasificacionActual').value = nombreActual;
    document.getElementById('nuevoNombreClasificacion').value = nombreActual;
    document.getElementById('nuevoNombreClasificacion').focus();
    abrirModal('modalEditarClasificacion');
}

function eliminarClasificacion(clasificacion, nombre) {
    clasificacionEditando = clasificacion;
    
    // Contar rutas afectadas
    const rutasAfectadas = document.querySelectorAll(`.select-clasif-ruta[value="${clasificacion}"]`);
    const contador = rutasAfectadas.length;
    
    document.getElementById('nombreClasificacionEliminar').textContent = nombre;
    document.getElementById('contadorRutasAfectadas').textContent = contador;
    
    abrirModal('modalEliminarClasificacion');
}

function abrirModal(modalId) {
    document.getElementById(modalId).classList.add('active');
    document.body.style.overflow = 'hidden';
}

function cerrarModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
    document.body.style.overflow = '';
    clasificacionEditando = null;
}

function guardarCambiosClasificacion() {
    const nuevoNombre = document.getElementById('nuevoNombreClasificacion').value.trim();
    const viejoNombre = clasificacionEditando;
    
    if (!nuevoNombre) {
        alert('El nombre no puede estar vacío.');
        return;
    }
    
    if (nuevoNombre.toLowerCase() === viejoNombre.toLowerCase()) {
        cerrarModal('modalEditarClasificacion');
        return;
    }
    
    fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            renombrar_clasificacion: 1,
            viejo_nombre: viejoNombre,
            nuevo_nombre: nuevoNombre.toLowerCase()
        })
    })
    .then(r => r.text())
    .then(respuesta => {
        if (respuesta.trim() === 'ok') {
            mostrarNotificacion('✅ Clasificación renombrada correctamente', 'success');
            cerrarModal('modalEditarClasificacion');
            
            // Recargar la página para ver cambios completos
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            mostrarNotificacion('❌ Error al renombrar: ' + respuesta, 'error');
        }
    })
    .catch(error => {
        mostrarNotificacion('❌ Error de conexión', 'error');
    });
}

function confirmarEliminarClasificacion() {
    const clasificacion = clasificacionEditando;
    
    fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            eliminar_clasificacion: 1,
            nombre_clasificacion: clasificacion
        })
    })
    .then(r => r.text())
    .then(respuesta => {
        if (respuesta.trim() === 'ok') {
            mostrarNotificacion('✅ Clasificación eliminada correctamente', 'success');
            cerrarModal('modalEliminarClasificacion');
            
            // Recargar la página para ver cambios completos
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            mostrarNotificacion('❌ Error al eliminar: ' + respuesta, 'error');
        }
    })
    .catch(error => {
        mostrarNotificacion('❌ Error de conexión', 'error');
    });
}

function actualizarContadorClasificaciones() {
    const contador = document.getElementById('contadorClasificaciones');
    if (contador) {
        const items = document.querySelectorAll('.tarifa-item');
        contador.textContent = items.length;
    }
}

// ===== FUNCIONES PARA COLORES DE CLASIFICACIÓN DE RUTAS =====
function inicializarColoresClasificacion() {
    const filas = document.querySelectorAll('.fila-ruta');
    filas.forEach(fila => {
        const select = fila.querySelector('.select-clasif-ruta');
        actualizarColorFila(select);
    });
}

function actualizarColorFila(selectElement) {
    const fila = selectElement.closest('tr');
    const clasificacion = selectElement.value.toLowerCase();
    const ruta = fila.dataset.ruta;
    const vehiculo = fila.dataset.vehiculo;
    
    // Limpiar clases anteriores
    fila.classList.forEach(className => {
        if (className.startsWith('fila-clasificada-')) {
            fila.classList.remove(className);
        }
    });
    
    // Actualizar datos
    fila.dataset.clasificacion = clasificacion;
    
    // Agregar nueva clase si hay clasificación
    if (clasificacion) {
        fila.classList.add('fila-clasificada-' + clasificacion);
    }
    
    // Guardar en base de datos
    guardarClasificacionRuta(ruta, vehiculo, clasificacion);
}

// ===== SISTEMA DE SELECCIÓN DE COLUMNAS =====
let columnasSeleccionadas = <?= json_encode($columnas_seleccionadas) ?>;

function inicializarSeleccionColumnas() {
    // Marcar checkboxes según selección actual
    columnasSeleccionadas.forEach(columna => {
        const checkbox = document.getElementById('checkbox-' + columna);
        if (checkbox) {
            checkbox.classList.add('checked');
        }
        const item = document.querySelector('[data-columna="' + columna + '"]');
        if (item) {
            item.classList.add('selected');
        }
    });
    
    actualizarContadorColumnas();
    actualizarColumnasTabla();
}

function toggleColumna(columna) {
    const checkbox = document.getElementById('checkbox-' + columna);
    const item = document.querySelector('[data-columna="' + columna + '"]');
    
    if (columnasSeleccionadas.includes(columna)) {
        // Deseleccionar
        columnasSeleccionadas = columnasSeleccionadas.filter(c => c !== columna);
        checkbox.classList.remove('checked');
        item.classList.remove('selected');
    } else {
        // Seleccionar
        columnasSeleccionadas.push(columna);
        checkbox.classList.add('checked');
        item.classList.add('selected');
    }
    
    actualizarContadorColumnas();
    actualizarColumnasTabla();
}

function seleccionarTodasColumnas() {
    const todasColumnas = document.querySelectorAll('.columna-checkbox-item');
    columnasSeleccionadas = [];
    
    todasColumnas.forEach(item => {
        const columna = item.dataset.columna;
        columnasSeleccionadas.push(columna);
        
        const checkbox = document.getElementById('checkbox-' + columna);
        checkbox.classList.add('checked');
        item.classList.add('selected');
    });
    
    actualizarContadorColumnas();
    actualizarColumnasTabla();
}

function deseleccionarTodasColumnas() {
    const todasColumnas = document.querySelectorAll('.columna-checkbox-item');
    columnasSeleccionadas = [];
    
    todasColumnas.forEach(item => {
        const columna = item.dataset.columna;
        
        const checkbox = document.getElementById('checkbox-' + columna);
        checkbox.classList.remove('checked');
        item.classList.remove('selected');
    });
    
    actualizarContadorColumnas();
    actualizarColumnasTabla();
}

function actualizarContadorColumnas() {
    const contadorSeleccionadas = document.getElementById('contador-seleccionadas-panel');
    const contadorVisibles = document.getElementById('contador-columnas-visibles');
    
    if (contadorSeleccionadas) {
        contadorSeleccionadas.textContent = columnasSeleccionadas.length;
    }
    
    if (contadorVisibles) {
        contadorVisibles.textContent = columnasSeleccionadas.length;
    }
}

function actualizarColumnasTabla() {
    document.querySelectorAll('.columna-tabla').forEach(columna => {
        const nombreColumna = columna.dataset.columna;
        
        if (columnasSeleccionadas.includes(nombreColumna)) {
            columna.classList.remove('columna-oculta');
            columna.classList.add('columna-visualizada');
        } else {
            columna.classList.remove('columna-visualizada');
            columna.classList.add('columna-oculta');
        }
    });
}

function guardarSeleccionColumnas() {
    const desde = "<?= htmlspecialchars($desde) ?>";
    const hasta = "<?= htmlspecialchars($hasta) ?>";
    const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
    
    fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            guardar_columnas_seleccionadas: 1,
            columnas: JSON.stringify(columnasSeleccionadas),
            desde: desde,
            hasta: hasta,
            empresa: empresa
        })
    })
    .then(r => r.text())
    .then(respuesta => {
        if (respuesta.trim() === 'ok') {
            mostrarNotificacion('✅ Selección de columnas guardada', 'success');
        } else {
            mostrarNotificacion('❌ Error al guardar selección', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('❌ Error de conexión', 'error');
    });
}

// ===== FUNCIONALIDAD PARA RUTAS SIN CLASIFICAR =====
function mostrarResumenRutasSinClasificar() {
    const resumenDiv = document.getElementById('resumenRutasSinClasificar');
    const listaDiv = document.getElementById('listaRutasSinClasificarGlobal');
    const contadorSpan = document.getElementById('contadorRutasSinClasificarGlobal');
    
    const filas = document.querySelectorAll('#tabla_conductores_body tr');
    let totalRutasSinClasificar = 0;
    let contenidoHTML = '';
    
    filas.forEach(fila => {
        const sinClasificar = parseInt(fila.dataset.sinClasificar || '0');
        if (sinClasificar > 0) {
            totalRutasSinClasificar += sinClasificar;
            const conductor = fila.querySelector('.conductor-link').textContent;
            contenidoHTML += `
                <div class="flex items-center justify-between p-3 bg-amber-50 rounded-lg border border-amber-100 hover:bg-amber-100 transition">
                    <div class="flex items-center gap-2">
                        <span class="text-amber-600">⚠️</span>
                        <span class="font-medium text-amber-800">${conductor}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs bg-amber-500 text-white px-2 py-1 rounded-full">${sinClasificar}</span>
                        <button onclick="verViajesConductor('${conductor}')" 
                                class="text-xs text-amber-600 hover:text-amber-800 hover:underline">
                            Ver viajes
                        </button>
                    </div>
                </div>
            `;
        }
    });
    
    if (totalRutasSinClasificar > 0) {
        contadorSpan.textContent = totalRutasSinClasificar;
        listaDiv.innerHTML = contenidoHTML;
        resumenDiv.classList.remove('hidden');
        resumenDiv.scrollIntoView({ behavior: 'smooth' });
    } else {
        listaDiv.innerHTML = '<div class="text-center py-4 text-amber-600">🎉 ¡Excelente! Todas las rutas están clasificadas.</div>';
        contadorSpan.textContent = '0';
        resumenDiv.classList.remove('hidden');
    }
}

function verViajesConductor(nombre) {
    const botonesConductor = document.querySelectorAll('.conductor-link');
    botonesConductor.forEach(boton => {
        if (boton.textContent.trim() === nombre.trim()) {
            boton.click();
        }
    });
    
    document.getElementById('resumenRutasSinClasificar').classList.add('hidden');
}

function irAClasificacionRutas() {
    togglePanel('clasif-rutas');
    const resumenDiv = document.getElementById('resumenRutasSinClasificar');
    if (resumenDiv) resumenDiv.classList.add('hidden');
}

// ===== CREAR NUEVA CLASIFICACIÓN =====
function crearYAsignarClasificacion() {
    const nombreClasif = document.getElementById('txt_nueva_clasificacion').value.trim();
    const patronRuta = document.getElementById('txt_patron_ruta').value.trim().toLowerCase();
    
    if (!nombreClasif) {
        alert('Escribe el nombre de la nueva clasificación.');
        return;
    }

    fetch(window.location.pathname, {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({
            crear_clasificacion:1,
            nombre_clasificacion:nombreClasif
        })
    })
    .then(r=>r.text())
    .then(respuesta=>{
        if (respuesta.trim() === 'ok') {
            
            if (patronRuta) {
                const filas = document.querySelectorAll('.fila-ruta');
                let contador = 0;
                
                filas.forEach(row => {
                    const ruta = row.dataset.ruta.toLowerCase();
                    const vehiculo = row.dataset.vehiculo;
                    if (ruta.includes(patronRuta)) {
                        const sel = row.querySelector('.select-clasif-ruta');
                        sel.value = nombreClasif.toLowerCase();
                        actualizarColorFila(sel);
                        contador++;
                    }
                });
                
                if (contador > 0) {
                    alert('✅ Se creó "' + nombreClasif + '" y se aplicó a ' + contador + ' rutas. Recarga la página para ver los cambios.');
                } else {
                    alert('✅ Se creó "' + nombreClasif + '". No se encontraron rutas con "' + patronRuta + '". Recarga la página.');
                }
            } else {
                alert('✅ Se creó la clasificación "' + nombreClasif + '". Recarga la página para verla en los selectores.');
            }
            
            document.getElementById('txt_nueva_clasificacion').value = '';
            document.getElementById('txt_patron_ruta').value = '';
            
        } else {
            alert('❌ Error: ' + respuesta);
        }
    })
    .catch(error=>{
        alert('❌ Error de conexión: ' + error);
    });
}

// ===== GUARDAR TARIFAS DINÁMICAMENTE =====
function configurarEventosTarifas() {
    document.addEventListener('change', function(e) {
        if (e.target.matches('.tarifa-input')) {
            const input = e.target;
            const card = input.closest('.tarjeta-tarifa-acordeon');
            const tipoVehiculo = card.dataset.vehiculo;
            const empresa = "<?= htmlspecialchars($empresaFiltro) ?>";
            const campo = input.dataset.campo.toLowerCase();
            const valor = parseFloat(input.value) || 0;
            
            fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    guardar_tarifa: 1,
                    empresa: empresa,
                    tipo_vehiculo: tipoVehiculo,
                    campo: campo,
                    valor: valor
                })
            })
            .then(r => r.text())
            .then(t => {
                const respuesta = t.trim();
                if (respuesta === 'ok') {
                    input.defaultValue = input.value;
                    // Llamar a recalcular si existe
                    if (typeof recalcular === 'function') {
                        recalcular();
                    }
                } else {
                    console.error('Error guardando tarifa:', respuesta);
                    input.value = input.defaultValue;
                }
            })
    .catch(error => {
                console.error('Error de conexión:', error);
                input.value = input.defaultValue;
            });
        }
    });
    
    // Guardar valores iniciales
    document.querySelectorAll('.tarifa-input').forEach(input => {
        input.defaultValue = input.value;
    });
}

// ===== CLASIFICACIONES INDIVIDUALES =====
function guardarClasificacionRuta(ruta, vehiculo, clasificacion) {
    if (!clasificacion) return;
    fetch(window.location.pathname, {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({
            guardar_clasificacion:1,
            ruta:ruta,
            tipo_vehiculo:vehiculo,
            clasificacion:clasificacion.toLowerCase()
        })
    })
    .then(r=>r.text())
    .then(t=>{
        if (t.trim() !== 'ok') console.error('Error guardando clasificación:', t);
    });
}

// ===== NOTIFICACIONES =====
function mostrarNotificacion(mensaje, tipo) {
    const notificacion = document.createElement('div');
    notificacion.className = `fixed top-4 right-4 px-4 py-3 rounded-lg shadow-lg z-[10001] animate-fade-in-down ${
        tipo === 'success' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 
        'bg-rose-100 text-rose-800 border border-rose-200'
    }`;
    notificacion.innerHTML = `
        <div class="flex items-center gap-2">
            <span class="text-lg">${tipo === 'success' ? '✅' : '❌'}</span>
            <span class="font-medium">${mensaje}</span>
        </div>
    `;
    
    document.body.appendChild(notificacion);
    
    setTimeout(() => {
        notificacion.remove();
    }, 3000);
}

// Animación para notificaciones
const style = document.createElement('style');
style.textContent = `
@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in-down {
    animation: fadeInDown 0.3s ease-out;
}
`;
document.head.appendChild(style);
</script>