<?php
// ============================================
// SISTEMA DE PAGOS PARCIALES PARA VIAJES
// ============================================

// Configuración de la base de datos
$host = "mysql.hostinger.com";
$username = "u648222299_keboco5";
$password = "Bucaramanga3011";
$db_name = "u648222299_viajes";

// Crear conexión
$conn = new mysqli($host, $username, $password, $db_name);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// Crear tabla de pagos si no existe
$crear_tabla = "
CREATE TABLE IF NOT EXISTS pagos_parciales (
    id INT(11) NOT NULL AUTO_INCREMENT,
    viaje_id INT(11) NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    fecha_pago DATE NOT NULL,
    metodo_pago VARCHAR(50) DEFAULT NULL,
    comentario TEXT DEFAULT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY viaje_id (viaje_id),
    FOREIGN KEY (viaje_id) REFERENCES viajes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if (!$conn->query($crear_tabla)) {
    echo "Error al crear tabla: " . $conn->error . "<br>";
}

// Crear vista si no existe
$crear_vista = "
CREATE OR REPLACE VIEW vista_viajes_pagos AS
SELECT 
    v.id,
    v.nombre,
    v.fecha,
    v.ruta,
    v.tipo_vehiculo,
    v.empresa,
    t.completo,
    t.medio,
    t.extra,
    t.carrotanque,
    t.siapana,
    -- Calcular tarifa según la ruta
    CASE 
        WHEN v.ruta LIKE '%nazareth-Maicao-nazareth%' OR v.ruta LIKE '%Maicao-nazareth-Maicao%' OR v.ruta LIKE '%Ida y vuelta%' THEN t.completo
        WHEN v.ruta LIKE '%nazareth-Maicao%' OR v.ruta LIKE '%Maicao-nazareth%' THEN t.medio
        WHEN v.ruta LIKE '%Siapana%' THEN t.siapana
        WHEN v.ruta = 'Nazareth' AND v.tipo_vehiculo = 'Carrotanque' THEN t.carrotanque
        ELSE COALESCE(t.extra, 0)
    END AS tarifa_total,
    -- Suma de pagos realizados
    COALESCE(SUM(p.monto), 0) AS pagado_total,
    -- Saldo pendiente
    (CASE 
        WHEN v.ruta LIKE '%nazareth-Maicao-nazareth%' OR v.ruta LIKE '%Maicao-nazareth-Maicao%' OR v.ruta LIKE '%Ida y vuelta%' THEN t.completo
        WHEN v.ruta LIKE '%nazareth-Maicao%' OR v.ruta LIKE '%Maicao-nazareth%' THEN t.medio
        WHEN v.ruta LIKE '%Siapana%' THEN t.siapana
        WHEN v.ruta = 'Nazareth' AND v.tipo_vehiculo = 'Carrotanque' THEN t.carrotanque
        ELSE COALESCE(t.extra, 0)
    END - COALESCE(SUM(p.monto), 0)) AS saldo_pendiente,
    -- Estado del pago
    CASE 
        WHEN COALESCE(SUM(p.monto), 0) >= (CASE 
            WHEN v.ruta LIKE '%nazareth-Maicao-nazareth%' OR v.ruta LIKE '%Maicao-nazareth-Maicao%' OR v.ruta LIKE '%Ida y vuelta%' THEN t.completo
            WHEN v.ruta LIKE '%nazareth-Maicao%' OR v.ruta LIKE '%Maicao-nazareth%' THEN t.medio
            WHEN v.ruta LIKE '%Siapana%' THEN t.siapana
            WHEN v.ruta = 'Nazareth' AND v.tipo_vehiculo = 'Carrotanque' THEN t.carrotanque
            ELSE COALESCE(t.extra, 0)
        END) THEN 'Pagado'
        WHEN COALESCE(SUM(p.monto), 0) > 0 THEN 'Pago parcial'
        ELSE 'Pendiente'
    END AS estado_pago
FROM viajes v
LEFT JOIN tarifas t ON v.empresa = t.empresa AND v.tipo_vehiculo = t.tipo_vehiculo
LEFT JOIN pagos_parciales p ON v.id = p.viaje_id
GROUP BY v.id, v.nombre, v.fecha, v.ruta, v.tipo_vehiculo, v.empresa, t.completo, t.medio, t.extra, t.carrotanque, t.siapana;
";

if (!$conn->query($crear_vista)) {
    echo "Error al crear vista: " . $conn->error . "<br>";
}

// ============================================
// FUNCIONES PRINCIPALES
// ============================================

function registrarPago($conn, $viaje_id, $monto, $fecha_pago, $metodo_pago, $comentario) {
    $viaje_id = $conn->real_escape_string($viaje_id);
    $monto = $conn->real_escape_string($monto);
    $fecha_pago = $conn->real_escape_string($fecha_pago);
    $metodo_pago = $conn->real_escape_string($metodo_pago);
    $comentario = $conn->real_escape_string($comentario);
    
    $sql = "INSERT INTO pagos_parciales (viaje_id, monto, fecha_pago, metodo_pago, comentario) 
            VALUES ('$viaje_id', '$monto', '$fecha_pago', '$metodo_pago', '$comentario')";
    
    if ($conn->query($sql) === TRUE) {
        return true;
    } else {
        return "Error: " . $conn->error;
    }
}

function obtenerPagosViaje($conn, $viaje_id) {
    $viaje_id = $conn->real_escape_string($viaje_id);
    $sql = "SELECT * FROM pagos_parciales WHERE viaje_id = '$viaje_id' ORDER BY fecha_pago DESC";
    $result = $conn->query($sql);
    
    $pagos = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $pagos[] = $row;
        }
    }
    return $pagos;
}

function obtenerViajesConPagos($conn, $filtro_estado = '', $filtro_fecha_inicio = '', $filtro_fecha_fin = '') {
    $sql = "SELECT * FROM vista_viajes_pagos WHERE 1=1";
    
    if ($filtro_estado) {
        $filtro_estado = $conn->real_escape_string($filtro_estado);
        $sql .= " AND estado_pago = '$filtro_estado'";
    }
    
    if ($filtro_fecha_inicio) {
        $filtro_fecha_inicio = $conn->real_escape_string($filtro_fecha_inicio);
        $sql .= " AND fecha >= '$filtro_fecha_inicio'";
    }
    
    if ($filtro_fecha_fin) {
        $filtro_fecha_fin = $conn->real_escape_string($filtro_fecha_fin);
        $sql .= " AND fecha <= '$filtro_fecha_fin'";
    }
    
    $sql .= " ORDER BY fecha DESC, nombre ASC";
    
    $result = $conn->query($sql);
    $viajes = [];
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $viajes[] = $row;
        }
    }
    return $viajes;
}

function obtenerViajePorId($conn, $id) {
    $id = $conn->real_escape_string($id);
    $sql = "SELECT * FROM vista_viajes_pagos WHERE id = '$id' LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

function obtenerResumenPagos($conn, $fecha_inicio = '', $fecha_fin = '') {
    $sql = "SELECT 
                estado_pago,
                COUNT(*) as total_viajes,
                SUM(tarifa_total) as total_tarifa,
                SUM(pagado_total) as total_pagado,
                SUM(saldo_pendiente) as total_pendiente
            FROM vista_viajes_pagos 
            WHERE 1=1";
    
    if ($fecha_inicio) {
        $fecha_inicio = $conn->real_escape_string($fecha_inicio);
        $sql .= " AND fecha >= '$fecha_inicio'";
    }
    
    if ($fecha_fin) {
        $fecha_fin = $conn->real_escape_string($fecha_fin);
        $sql .= " AND fecha <= '$fecha_fin'";
    }
    
    $sql .= " GROUP BY estado_pago";
    
    $result = $conn->query($sql);
    $resumen = [];
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $resumen[] = $row;
        }
    }
    return $resumen;
}

// ============================================
// MANEJO DE PETICIONES POST
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        switch ($_POST['accion']) {
            case 'registrar_pago':
                $resultado = registrarPago(
                    $conn,
                    $_POST['viaje_id'],
                    $_POST['monto'],
                    $_POST['fecha_pago'],
                    $_POST['metodo_pago'],
                    $_POST['comentario']
                );
                if ($resultado === true) {
                    $mensaje = "¡Pago registrado exitosamente!";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = $resultado;
                    $tipo_mensaje = "danger";
                }
                break;
        }
    }
}

// Obtener parámetros de filtro
$filtro_estado = $_GET['estado'] ?? '';
$filtro_fecha_inicio = $_GET['fecha_inicio'] ?? '';
$filtro_fecha_fin = $_GET['fecha_fin'] ?? '';
$viaje_id_detalle = $_GET['detalle'] ?? '';

// Obtener datos
$viajes = obtenerViajesConPagos($conn, $filtro_estado, $filtro_fecha_inicio, $filtro_fecha_fin);
$resumen = obtenerResumenPagos($conn, $filtro_fecha_inicio, $filtro_fecha_fin);

// Si se solicita detalle de un viaje
$viaje_detalle = null;
$pagos_viaje = [];
if ($viaje_id_detalle) {
    $viaje_detalle = obtenerViajePorId($conn, $viaje_id_detalle);
    if ($viaje_detalle) {
        $pagos_viaje = obtenerPagosViaje($conn, $viaje_id_detalle);
    }
}

// ============================================
// INTERFAZ HTML
// ============================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Pagos Parciales - Viajes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
            padding-bottom: 50px;
        }
        .header {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .estado-pagado {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .estado-parcial {
            background-color: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        .estado-pendiente {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .badge-estado {
            font-size: 0.8em;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0,123,255,0.05);
        }
        .resumen-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .btn-action {
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 0.9em;
        }
        .modal-content {
            border-radius: 10px;
            border: none;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .form-control:focus, .form-select:focus {
            border-color: #6a11cb;
            box-shadow: 0 0 0 0.2rem rgba(106, 17, 203, 0.25);
        }
        .pago-item {
            border-left: 4px solid #6a11cb;
            padding-left: 15px;
            margin-bottom: 10px;
        }
        .total-card {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }
        .filter-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header text-center">
            <h1><i class="bi bi-cash-stack"></i> Sistema de Pagos Parciales</h1>
            <p class="lead">Gestión de pagos para servicios de transporte</p>
        </div>

        <?php if (isset($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="filter-card">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Estado de Pago</label>
                    <select name="estado" class="form-select">
                        <option value="">Todos</option>
                        <option value="Pagado" <?php echo $filtro_estado == 'Pagado' ? 'selected' : ''; ?>>Pagado</option>
                        <option value="Pago parcial" <?php echo $filtro_estado == 'Pago parcial' ? 'selected' : ''; ?>>Pago Parcial</option>
                        <option value="Pendiente" <?php echo $filtro_estado == 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha Desde</label>
                    <input type="date" name="fecha_inicio" class="form-control" value="<?php echo $filtro_fecha_inicio; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha Hasta</label>
                    <input type="date" name="fecha_fin" class="form-control" value="<?php echo $filtro_fecha_fin; ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel"></i> Filtrar
                    </button>
                    <a href="?" class="btn btn-outline-secondary ms-2">
                        <i class="bi bi-x-circle"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Resumen -->
        <div class="row mb-4">
            <div class="col-12">
                <h3><i class="bi bi-graph-up"></i> Resumen General</h3>
            </div>
            <?php 
            $total_viajes = 0;
            $total_tarifa = 0;
            $total_pagado = 0;
            $total_pendiente = 0;
            
            foreach ($resumen as $item): 
                $total_viajes += $item['total_viajes'];
                $total_tarifa += $item['total_tarifa'];
                $total_pagado += $item['total_pagado'];
                $total_pendiente += $item['total_pendiente'];
            endforeach;
            ?>
            
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Total Viajes</h5>
                        <h2 class="text-primary"><?php echo $total_viajes; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Valor Total</h5>
                        <h2 class="text-success">$<?php echo number_format($total_tarifa, 0, ',', '.'); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Pagado</h5>
                        <h2 class="text-info">$<?php echo number_format($total_pagado, 0, ',', '.'); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Pendiente</h5>
                        <h2 class="text-danger">$<?php echo number_format($total_pendiente, 0, ',', '.'); ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de Viajes -->
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="bi bi-list-check"></i> Lista de Viajes</h4>
                <span class="badge bg-primary"><?php echo count($viajes); ?> viajes</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Nombre</th>
                                <th>Ruta</th>
                                <th>Vehículo</th>
                                <th>Empresa</th>
                                <th>Tarifa</th>
                                <th>Pagado</th>
                                <th>Pendiente</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($viajes)): ?>
                                <tr>
                                    <td colspan="11" class="text-center py-4">
                                        <i class="bi bi-inbox" style="font-size: 3rem; color: #6c757d;"></i>
                                        <p class="mt-2">No hay viajes registrados</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($viajes as $viaje): ?>
                                    <tr>
                                        <td><?php echo $viaje['id']; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($viaje['fecha'])); ?></td>
                                        <td><?php echo htmlspecialchars($viaje['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($viaje['ruta']); ?></td>
                                        <td><?php echo htmlspecialchars($viaje['tipo_vehiculo']); ?></td>
                                        <td><?php echo htmlspecialchars($viaje['empresa']); ?></td>
                                        <td>$<?php echo number_format($viaje['tarifa_total'], 0, ',', '.'); ?></td>
                                        <td>$<?php echo number_format($viaje['pagado_total'], 0, ',', '.'); ?></td>
                                        <td>
                                            <?php if ($viaje['saldo_pendiente'] > 0): ?>
                                                <span class="text-danger fw-bold">$<?php echo number_format($viaje['saldo_pendiente'], 0, ',', '.'); ?></span>
                                            <?php else: ?>
                                                <span class="text-success">$0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $clase_estado = '';
                                            if ($viaje['estado_pago'] == 'Pagado') $clase_estado = 'bg-success';
                                            if ($viaje['estado_pago'] == 'Pago parcial') $clase_estado = 'bg-warning';
                                            if ($viaje['estado_pago'] == 'Pendiente') $clase_estado = 'bg-danger';
                                            ?>
                                            <span class="badge <?php echo $clase_estado; ?>"><?php echo $viaje['estado_pago']; ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="verDetalle(<?php echo $viaje['id']; ?>)"
                                                    title="Ver detalles y pagos">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-success" 
                                                    onclick="registrarPago(<?php echo $viaje['id']; ?>, '<?php echo htmlspecialchars($viaje['nombre']); ?>', <?php echo $viaje['saldo_pendiente']; ?>)"
                                                    title="Registrar pago">
                                                <i class="bi bi-cash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal para Detalles del Viaje -->
        <?php if ($viaje_detalle): ?>
            <div class="modal fade show" id="modalDetalle" style="display: block; background-color: rgba(0,0,0,0.5);">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="bi bi-info-circle"></i> Detalles del Viaje #<?php echo $viaje_detalle['id']; ?>
                            </h5>
                            <a href="?" class="btn-close"></a>
                        </div>
                        <div class="modal-body">
                            <!-- Información del viaje -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6>Información del Cliente</h6>
                                    <p><strong>Nombre:</strong> <?php echo htmlspecialchars($viaje_detalle['nombre']); ?></p>
                                    <p><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($viaje_detalle['fecha'])); ?></p>
                                    <p><strong>Ruta:</strong> <?php echo htmlspecialchars($viaje_detalle['ruta']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Información del Servicio</h6>
                                    <p><strong>Vehículo:</strong> <?php echo htmlspecialchars($viaje_detalle['tipo_vehiculo']); ?></p>
                                    <p><strong>Empresa:</strong> <?php echo htmlspecialchars($viaje_detalle['empresa']); ?></p>
                                    <?php 
                                    $clase_estado = '';
                                    if ($viaje_detalle['estado_pago'] == 'Pagado') $clase_estado = 'estado-pagado';
                                    if ($viaje_detalle['estado_pago'] == 'Pago parcial') $clase_estado = 'estado-parcial';
                                    if ($viaje_detalle['estado_pago'] == 'Pendiente') $clase_estado = 'estado-pendiente';
                                    ?>
                                    <p><strong>Estado:</strong> <span class="<?php echo $clase_estado; ?> px-2 py-1 rounded"><?php echo $viaje_detalle['estado_pago']; ?></span></p>
                                </div>
                            </div>

                            <!-- Resumen financiero -->
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="total-card">
                                        <h6>Tarifa Total</h6>
                                        <h3>$<?php echo number_format($viaje_detalle['tarifa_total'], 0, ',', '.'); ?></h3>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="total-card" style="background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%);">
                                        <h6>Total Pagado</h6>
                                        <h3>$<?php echo number_format($viaje_detalle['pagado_total'], 0, ',', '.'); ?></h3>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="total-card" style="background: linear-gradient(135deg, #f46b45 0%, #eea849 100%);">
                                        <h6>Saldo Pendiente</h6>
                                        <h3>$<?php echo number_format($viaje_detalle['saldo_pendiente'], 0, ',', '.'); ?></h3>
                                    </div>
                                </div>
                            </div>

                            <!-- Historial de pagos -->
                            <h5><i class="bi bi-clock-history"></i> Historial de Pagos</h5>
                            <?php if (empty($pagos_viaje)): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> No se han registrado pagos para este viaje.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Monto</th>
                                                <th>Método</th>
                                                <th>Comentario</th>
                                                <th>Registrado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pagos_viaje as $pago): ?>
                                                <tr>
                                                    <td><?php echo date('d/m/Y', strtotime($pago['fecha_pago'])); ?></td>
                                                    <td class="text-success fw-bold">$<?php echo number_format($pago['monto'], 0, ',', '.'); ?></td>
                                                    <td><span class="badge bg-info"><?php echo htmlspecialchars($pago['metodo_pago']); ?></span></td>
                                                    <td><?php echo htmlspecialchars($pago['comentario']); ?></td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($pago['creado_en'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-success" onclick="registrarPago(<?php echo $viaje_detalle['id']; ?>, '<?php echo htmlspecialchars($viaje_detalle['nombre']); ?>', <?php echo $viaje_detalle['saldo_pendiente']; ?>)">
                                <i class="bi bi-cash"></i> Registrar Nuevo Pago
                            </button>
                            <a href="?" class="btn btn-secondary">Cerrar</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Modal para Registrar Pago -->
        <div class="modal fade" id="modalPago" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalPagoTitulo"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" id="formPago">
                        <div class="modal-body">
                            <input type="hidden" name="accion" value="registrar_pago">
                            <input type="hidden" name="viaje_id" id="viaje_id">
                            
                            <div class="mb-3">
                                <label class="form-label">Cliente</label>
                                <input type="text" class="form-control" id="cliente_nombre" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Saldo Pendiente</label>
                                <input type="text" class="form-control" id="saldo_pendiente" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Monto del Pago *</label>
                                <input type="number" class="form-control" name="monto" required step="0.01" min="0.01">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Fecha del Pago *</label>
                                <input type="date" class="form-control" name="fecha_pago" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Método de Pago</label>
                                <select class="form-select" name="metodo_pago">
                                    <option value="Efectivo">Efectivo</option>
                                    <option value="Transferencia">Transferencia</option>
                                    <option value="Tarjeta">Tarjeta</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Comentario (opcional)</label>
                                <textarea class="form-control" name="comentario" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle"></i> Registrar Pago
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function verDetalle(viajeId) {
            window.location.href = '?detalle=' + viajeId;
        }
        
        function registrarPago(viajeId, clienteNombre, saldoPendiente) {
            // Cerrar modal de detalle si está abierto
            const modalDetalle = document.getElementById('modalDetalle');
            if (modalDetalle) {
                modalDetalle.style.display = 'none';
            }
            
            // Configurar modal de pago
            document.getElementById('viaje_id').value = viajeId;
            document.getElementById('cliente_nombre').value = clienteNombre;
            document.getElementById('saldo_pendiente').value = '$' + saldoPendiente.toLocaleString();
            document.getElementById('modalPagoTitulo').textContent = 'Registrar Pago - ' + clienteNombre;
            
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('modalPago'));
            modal.show();
        }
        
        // Formatear números como moneda
        function formatCurrency(value) {
            return '$' + parseFloat(value).toLocaleString('es-ES');
        }
        
        // Actualizar saldo cuando se escribe el monto
        document.querySelector('input[name="monto"]')?.addEventListener('input', function(e) {
            const saldo = parseFloat(document.getElementById('saldo_pendiente').value.replace(/[^0-9.-]+/g,""));
            const monto = parseFloat(e.target.value) || 0;
            
            if (monto > saldo) {
                e.target.classList.add('is-invalid');
            } else {
                e.target.classList.remove('is-invalid');
            }
        });
        
        // Manejar envío del formulario
        document.getElementById('formPago')?.addEventListener('submit', function(e) {
            const monto = parseFloat(document.querySelector('input[name="monto"]').value);
            const saldo = parseFloat(document.getElementById('saldo_pendiente').value.replace(/[^0-9.-]+/g,""));
            
            if (monto > saldo) {
                e.preventDefault();
                alert('El monto no puede ser mayor al saldo pendiente.');
            }
        });
        
        // Si hay un mensaje de éxito, cerrar modal automáticamente
        <?php if (isset($mensaje) && $tipo_mensaje == 'success'): ?>
            const modalPago = bootstrap.Modal.getInstance(document.getElementById('modalPago'));
            if (modalPago) {
                modalPago.hide();
            }
        <?php endif; ?>
    </script>
</body>
</html>
<?php
// Cerrar conexión
$conn->close();
?>