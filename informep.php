<?php
// ACTIVAR MODO DEPURACIÓN
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Conexión BD
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Obtener lista de puestos de salud (rutas distintas)
$puestosSalud = [];
$resPuestos = $conn->query("SELECT DISTINCT ruta FROM viajes WHERE ruta IS NOT NULL AND ruta <> '' ORDER BY ruta ASC");
if ($resPuestos) {
    while ($r = $resPuestos->fetch_assoc()) {
        $puestosSalud[] = $r['ruta'];
    }
}

// Obtener lista de todos los conductores
$todosConductores = [];
$resCond = $conn->query("SELECT DISTINCT nombre, cedula, tipo_vehiculo FROM viajes WHERE nombre IS NOT NULL AND nombre <> '' ORDER BY nombre ASC");
if ($resCond) {
    while ($r = $resCond->fetch_assoc()) {
        $todosConductores[] = $r;
    }
}

// Función para obtener el tipo de vehículo formateado
function obtenerTipoVehiculo($tipo) {
    if (stripos($tipo, 'burbuja') !== false) {
        return 'Camioneta Burbuja 4x4 Doble Cabina';
    }
    if (stripos($tipo, 'carrotanque') !== false) {
        return 'Carrotanque';
    }
    if (stripos($tipo, '350') !== false) {
        return 'Camión 350';
    }
    if (stripos($tipo, 'copetrana') !== false) {
        return 'Copetrana';
    }
    return $tipo ?: '-';
}

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Configurar Conductores por Puesto de Salud | Sistema de Transporte</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --warning-color: #ffc107;
            --info-color: #0dcaf0;
            --danger-color: #dc3545;
            --dark-color: #212529;
            --light-color: #f8f9fa;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container-custom {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .main-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .card-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        
        .card-header-custom h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .card-header-custom p {
            margin-bottom: 0;
            opacity: 0.9;
        }
        
        .card-body-custom {
            padding: 2rem;
        }
        
        .btn-configurar {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1.1rem;
            border: none;
            transition: all 0.3s;
            width: 100%;
            margin-bottom: 2rem;
        }
        
        .btn-configurar:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(238,90,36,0.3);
        }
        
        .btn-agregar-puesto {
            background: linear-gradient(135deg, var(--success-color) 0%, #0f6848 100%);
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            font-weight: 500;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-agregar-puesto:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(25,135,84,0.3);
        }
        
        .btn-eliminar-puesto {
            background: var(--danger-color);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        
        .btn-eliminar-puesto:hover {
            background: #c82333;
            transform: scale(1.05);
        }
        
        .fila-puesto {
            background: var(--light-color);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s;
        }
        
        .fila-puesto:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .conductor-item-asignacion {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .conductor-info {
            flex: 1;
        }
        
        .conductor-nombre {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--dark-color);
        }
        
        .conductor-cedula {
            font-size: 0.7rem;
            color: var(--secondary-color);
        }
        
        .btn-eliminar-conductor {
            background: none;
            border: none;
            color: var(--danger-color);
            cursor: pointer;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            transition: all 0.2s;
        }
        
        .btn-eliminar-conductor:hover {
            background: #fee2e2;
            transform: scale(1.1);
        }
        
        .buscador-conductor {
            position: relative;
            margin-top: 0.5rem;
        }
        
        .buscador-conductor input {
            width: 100%;
            padding: 0.5rem 0.75rem 0.5rem 2rem;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.85rem;
            outline: none;
            transition: all 0.3s;
        }
        
        .buscador-conductor input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
        }
        
        .buscador-conductor i {
            position: absolute;
            left: 0.7rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary-color);
            font-size: 0.8rem;
        }
        
        .lista-conductores-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .lista-conductores-dropdown.show {
            display: block;
        }
        
        .conductor-option {
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            transition: background 0.2s;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .conductor-option:hover {
            background: var(--light-color);
        }
        
        .badge-vehiculo {
            display: inline-block;
            padding: 0.1rem 0.4rem;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 500;
            margin-top: 0.2rem;
        }
        
        .badge-carrotanque {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-otro {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .modal-xl {
            max-width: 1200px;
        }
        
        .resumen-asignaciones {
            background: #e7f3ff;
            border-radius: 12px;
            padding: 1rem;
            margin-top: 2rem;
        }
        
        .resumen-item {
            padding: 0.5rem;
            border-bottom: 1px solid #cce5ff;
            font-size: 0.85rem;
        }
        
        .resumen-item strong {
            color: var(--primary-color);
        }
        
        .info-banner {
            background: #fff3cd;
            border-left: 4px solid var(--warning-color);
            padding: 0.8rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }
        
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container-custom">
        <div class="main-card">
            <div class="card-header-custom">
                <i class="fas fa-handshake fa-3x mb-2"></i>
                <h1>🏥 Configurar Conductores por Puesto de Salud</h1>
                <p>Asociación de Transportistas Zona Norte Extrema Wuinpumuín</p>
            </div>
            
            <div class="card-body-custom">
                <div class="info-banner">
                    <i class="fas fa-info-circle"></i> 
                    <strong>¿Cómo funciona?</strong> Aquí puedes asignar uno o más conductores a cada puesto de salud. 
                    Estas asignaciones se guardarán y se usarán cuando generes los informes. Puedes agregar múltiples puestos 
                    y a cada puesto asignarle 1 o 2 conductores.
                </div>
                
                <!-- Botón para abrir el modal -->
                <button type="button" class="btn-configurar" data-bs-toggle="modal" data-bs-target="#modalAsignacion">
                    <i class="fas fa-users-cog"></i> 🎯 Asignar Conductores por Puesto de Salud
                </button>
                
                <!-- Aquí se mostrará el resumen de asignaciones -->
                <div id="resumenAsignaciones" class="resumen-asignaciones">
                    <h5><i class="fas fa-list-check"></i> Asignaciones actuales</h5>
                    <div id="listaResumen">
                        <p class="text-muted text-center">No hay asignaciones. Haz clic en el botón para configurar.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- MODAL DE ASIGNACIÓN -->
    <div class="modal fade" id="modalAsignacion" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-users-cog"></i> Asignar Conductores a Puestos de Salud
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Instrucciones:</strong> Agrega los puestos de salud que necesites. Para cada puesto, busca y selecciona 
                        los conductores que le corresponden (puedes seleccionar 1 o 2 conductores por puesto).
                    </div>
                    
                    <!-- Contenedor dinámico de puestos -->
                    <div id="contenedorPuestos">
                        <!-- Las filas se agregarán aquí dinámicamente -->
                    </div>
                    
                    <!-- Botón para agregar nuevo puesto -->
                    <div class="text-center mt-3">
                        <button type="button" class="btn-agregar-puesto" id="btnAgregarPuesto">
                            <i class="fas fa-plus-circle"></i> Agregar otro puesto de salud
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" id="btnGuardarAsignaciones">
                        <i class="fas fa-save"></i> Guardar Asignaciones
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Datos de PHP a JavaScript
        const puestosSalud = <?php echo json_encode($puestosSalud); ?>;
        const todosConductores = <?php echo json_encode($todosConductores); ?>;
        
        // Almacenar asignaciones
        let asignaciones = [];
        
        // Contador para IDs únicos
        let contadorPuestos = 0;
        
        // Función para obtener badge del tipo de vehículo
        function obtenerBadgeVehiculo(tipoVehiculo) {
            const esCarrotanque = tipoVehiculo && tipoVehiculo.toLowerCase().includes('carrotanque');
            const tipoFormateado = tipoVehiculo ? tipoVehiculo : '-';
            const badgeClass = esCarrotanque ? 'badge-carrotanque' : 'badge-otro';
            const icono = esCarrotanque ? 'fa-truck' : 'fa-car';
            return `<span class="badge-vehiculo ${badgeClass}"><i class="fas ${icono}"></i> ${tipoFormateado}</span>`;
        }
        
        // Función para renderizar la lista de conductores seleccionados para un puesto
        function renderizarConductoresSeleccionados(puestoId, conductoresAsignados) {
            const container = document.getElementById(`conductores-${puestoId}`);
            if (!container) return;
            
            if (!conductoresAsignados || conductoresAsignados.length === 0) {
                container.innerHTML = '<small class="text-muted">No hay conductores asignados. Busca y selecciona uno.</small>';
                return;
            }
            
            let html = '';
            conductoresAsignados.forEach((conductor, idx) => {
                html += `
                    <div class="conductor-item-asignacion">
                        <div class="conductor-info">
                            <div class="conductor-nombre">
                                <i class="fas fa-user-check" style="color: #198754;"></i> ${escapeHtml(conductor.nombre)}
                            </div>
                            <div class="conductor-cedula">
                                Cédula: ${conductor.cedula || 'N/A'}
                            </div>
                            ${obtenerBadgeVehiculo(conductor.tipo_vehiculo)}
                        </div>
                        <button type="button" class="btn-eliminar-conductor" onclick="eliminarConductor(${puestoId}, ${idx})">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                `;
            });
            container.innerHTML = html;
        }
        
        // Función para eliminar un conductor de un puesto
        window.eliminarConductor = function(puestoId, conductorIndex) {
            const fila = asignaciones.find(a => a.id === puestoId);
            if (fila && fila.conductores[conductorIndex]) {
                fila.conductores.splice(conductorIndex, 1);
                renderizarConductoresSeleccionados(puestoId, fila.conductores);
                actualizarContadorConductores(puestoId);
            }
        };
        
        // Función para actualizar el contador de conductores
        function actualizarContadorConductores(puestoId) {
            const fila = asignaciones.find(a => a.id === puestoId);
            const contadorSpan = document.getElementById(`contador-${puestoId}`);
            if (contadorSpan && fila) {
                contadorSpan.textContent = `${fila.conductores.length}/2 conductores`;
                if (fila.conductores.length >= 2) {
                    contadorSpan.style.color = '#dc3545';
                } else {
                    contadorSpan.style.color = '#198754';
                }
            }
        }
        
        // Función para agregar un nuevo puesto
        function agregarPuesto(puestoPredefinido = null) {
            const id = ++contadorPuestos;
            const puestoSeleccionado = puestoPredefinido || '';
            
            // Crear objeto de asignación
            asignaciones.push({
                id: id,
                puesto: puestoSeleccionado,
                conductores: []
            });
            
            const contenedor = document.getElementById('contenedorPuestos');
            const filaDiv = document.createElement('div');
            filaDiv.className = 'fila-puesto';
            filaDiv.id = `puesto-${id}`;
            filaDiv.innerHTML = `
                <div class="row">
                    <div class="col-md-5">
                        <label class="form-label fw-bold">
                            <i class="fas fa-hospital"></i> Puesto de Salud
                        </label>
                        <select class="form-select select-puesto" data-puesto-id="${id}">
                            <option value="">-- Seleccione un puesto --</option>
                            ${puestosSalud.map(p => `<option value="${escapeHtml(p)}" ${p === puestoSeleccionado ? 'selected' : ''}>${escapeHtml(p)}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-md-7">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-bold">
                                <i class="fas fa-users"></i> Conductores asignados
                            </label>
                            <span id="contador-${id}" style="font-size: 0.75rem; font-weight: 500; color: #198754;">
                                0/2 conductores
                            </span>
                            <button type="button" class="btn-eliminar-puesto" onclick="eliminarPuesto(${id})">
                                <i class="fas fa-trash-alt"></i> Eliminar puesto
                            </button>
                        </div>
                        <div id="conductores-${id}" class="mb-2">
                            <small class="text-muted">No hay conductores asignados. Busca y selecciona uno.</small>
                        </div>
                        <div class="buscador-conductor">
                            <i class="fas fa-search"></i>
                            <input type="text" 
                                   class="form-control input-buscador" 
                                   placeholder="Buscar conductor por nombre..." 
                                   data-puesto-id="${id}"
                                   autocomplete="off">
                            <div class="lista-conductores-dropdown" data-puesto-id="${id}"></div>
                        </div>
                    </div>
                </div>
            `;
            
            contenedor.appendChild(filaDiv);
            
            // Configurar evento del select
            const select = filaDiv.querySelector('.select-puesto');
            select.addEventListener('change', function(e) {
                const filaAsignacion = asignaciones.find(a => a.id === id);
                if (filaAsignacion) {
                    filaAsignacion.puesto = this.value;
                }
            });
            
            // Configurar buscador
            const inputBuscador = filaDiv.querySelector('.input-buscador');
            const dropdown = filaDiv.querySelector('.lista-conductores-dropdown');
            
            inputBuscador.addEventListener('focus', () => {
                filtrarConductoresDropdown(inputBuscador, dropdown, id);
            });
            
            inputBuscador.addEventListener('keyup', () => {
                filtrarConductoresDropdown(inputBuscador, dropdown, id);
            });
            
            // Cerrar dropdown al hacer clic fuera
            document.addEventListener('click', function(e) {
                if (!inputBuscador.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.remove('show');
                }
            });
        }
        
        // Función para filtrar conductores en el dropdown
        function filtrarConductoresDropdown(input, dropdown, puestoId) {
            const busqueda = input.value.toLowerCase().trim();
            let conductoresFiltrados = todosConductores;
            
            if (busqueda !== '') {
                conductoresFiltrados = todosConductores.filter(c => 
                    c.nombre.toLowerCase().startsWith(busqueda)
                );
            }
            
            if (conductoresFiltrados.length === 0) {
                dropdown.innerHTML = '<div class="conductor-option text-muted">No se encontraron conductores</div>';
            } else {
                dropdown.innerHTML = conductoresFiltrados.map(conductor => `
                    <div class="conductor-option" onclick="seleccionarConductor(${puestoId}, '${escapeHtml(conductor.nombre)}', '${escapeHtml(conductor.cedula || 'N/A')}', '${escapeHtml(conductor.tipo_vehiculo || '')}')">
                        <div><strong>${escapeHtml(conductor.nombre)}</strong></div>
                        <div style="font-size: 0.7rem; color: #6c757d;">Cédula: ${escapeHtml(conductor.cedula || 'N/A')}</div>
                        <div>${obtenerBadgeVehiculo(conductor.tipo_vehiculo)}</div>
                    </div>
                `).join('');
            }
            
            dropdown.classList.add('show');
        }
        
        // Función para seleccionar un conductor
        window.seleccionarConductor = function(puestoId, nombre, cedula, tipoVehiculo) {
            const filaAsignacion = asignaciones.find(a => a.id === puestoId);
            
            if (!filaAsignacion) return;
            
            // Verificar límite de 2 conductores
            if (filaAsignacion.conductores.length >= 2) {
                alert('⚠️ Solo puedes asignar máximo 2 conductores por puesto de salud.');
                return;
            }
            
            // Verificar si ya está asignado
            if (filaAsignacion.conductores.some(c => c.nombre === nombre)) {
                alert('⚠️ Este conductor ya está asignado a este puesto.');
                return;
            }
            
            // Agregar conductor
            filaAsignacion.conductores.push({
                nombre: nombre,
                cedula: cedula,
                tipo_vehiculo: tipoVehiculo
            });
            
            // Actualizar UI
            renderizarConductoresSeleccionados(puestoId, filaAsignacion.conductores);
            actualizarContadorConductores(puestoId);
            
            // Limpiar input y cerrar dropdown
            const inputBuscador = document.querySelector(`.input-buscador[data-puesto-id="${puestoId}"]`);
            if (inputBuscador) {
                inputBuscador.value = '';
            }
            const dropdown = document.querySelector(`.lista-conductores-dropdown[data-puesto-id="${puestoId}"]`);
            if (dropdown) {
                dropdown.classList.remove('show');
            }
        };
        
        // Función para eliminar un puesto completo
        window.eliminarPuesto = function(puestoId) {
            // Eliminar del array de asignaciones
            const index = asignaciones.findIndex(a => a.id === puestoId);
            if (index !== -1) {
                asignaciones.splice(index, 1);
            }
            
            // Eliminar del DOM
            const elemento = document.getElementById(`puesto-${puestoId}`);
            if (elemento) {
                elemento.remove();
            }
        };
        
        // Función para actualizar el resumen de asignaciones
        function actualizarResumen() {
            const resumenDiv = document.getElementById('listaResumen');
            const asignacionesActivas = asignaciones.filter(a => a.puesto && a.conductores.length > 0);
            
            if (asignacionesActivas.length === 0) {
                resumenDiv.innerHTML = '<p class="text-muted text-center mb-0">No hay asignaciones guardadas. Configura usando el botón.</p>';
                return;
            }
            
            let html = '';
            asignacionesActivas.forEach(asig => {
                html += `
                    <div class="resumen-item">
                        <strong><i class="fas fa-hospital"></i> ${escapeHtml(asig.puesto)}</strong><br>
                        <span style="font-size: 0.8rem;">Conductores asignados:</span><br>
                        ${asig.conductores.map(c => `&nbsp;&nbsp;<i class="fas fa-user"></i> ${escapeHtml(c.nombre)}`).join('<br>')}
                    </div>
                `;
            });
            resumenDiv.innerHTML = html;
        }
        
        // Guardar asignaciones
        document.getElementById('btnGuardarAsignaciones').addEventListener('click', function() {
            // Filtrar solo las asignaciones que tienen puesto y al menos un conductor
            const asignacionesValidas = asignaciones.filter(a => a.puesto && a.conductores.length > 0);
            
            if (asignacionesValidas.length === 0) {
                alert('⚠️ No has configurado ninguna asignación válida. Debes seleccionar un puesto y al menos un conductor.');
                return;
            }
            
            // Aquí puedes guardar en localStorage o enviar al servidor
            localStorage.setItem('asignacionesPuestosSalud', JSON.stringify(asignacionesValidas));
            
            actualizarResumen();
            
            // Cerrar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalAsignacion'));
            modal.hide();
            
            alert('✅ Asignaciones guardadas correctamente.');
        });
        
        // Cargar asignaciones guardadas al iniciar
        function cargarAsignacionesGuardadas() {
            const guardadas = localStorage.getItem('asignacionesPuestosSalud');
            if (guardadas) {
                try {
                    const asignacionesGuardadas = JSON.parse(guardadas);
                    asignacionesGuardadas.forEach(asig => {
                        agregarPuesto(asig.puesto);
                        // Esperar un poco para que se cree el DOM
                        setTimeout(() => {
                            const fila = asignaciones.find(a => a.puesto === asig.puesto);
                            if (fila) {
                                fila.conductores = asig.conductores;
                                renderizarConductoresSeleccionados(fila.id, fila.conductores);
                                actualizarContadorConductores(fila.id);
                            }
                        }, 50);
                    });
                    actualizarResumen();
                } catch(e) {
                    console.error('Error cargando asignaciones:', e);
                }
            }
        }
        
        // Función auxiliar para escape HTML
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Evento para abrir el modal y limpiar las asignaciones temporales
        document.getElementById('modalAsignacion').addEventListener('show.bs.modal', function() {
            // Limpiar el contenedor
            document.getElementById('contenedorPuestos').innerHTML = '';
            
            // Cargar asignaciones actuales
            const guardadas = localStorage.getItem('asignacionesPuestosSalud');
            asignaciones = [];
            contadorPuestos = 0;
            
            if (guardadas) {
                try {
                    const asignacionesGuardadas = JSON.parse(guardadas);
                    if (asignacionesGuardadas.length === 0) {
                        // Si no hay asignaciones, agregar un puesto vacío
                        agregarPuesto();
                    } else {
                        asignacionesGuardadas.forEach(asig => {
                            agregarPuesto(asig.puesto);
                            setTimeout(() => {
                                const fila = asignaciones.find(a => a.puesto === asig.puesto);
                                if (fila) {
                                    fila.conductores = asig.conductores;
                                    renderizarConductoresSeleccionados(fila.id, fila.conductores);
                                    actualizarContadorConductores(fila.id);
                                }
                            }, 50);
                        });
                    }
                } catch(e) {
                    agregarPuesto();
                }
            } else {
                agregarPuesto();
            }
        });
        
        // Botón para agregar puesto
        document.getElementById('btnAgregarPuesto').addEventListener('click', function() {
            agregarPuesto();
        });
        
        // Inicializar
        cargarAsignacionesGuardadas();
    </script>
</body>
</html>