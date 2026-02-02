<?php
session_start();

$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) { die("Error conexión BD: " . $conn->connect_error); }
$conn->set_charset('utf8mb4');

// Crear tabla si no existe
$conn->query("
CREATE TABLE IF NOT EXISTS cuentas_guardadas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(255) NOT NULL,
    empresa VARCHAR(100) NOT NULL,
    desde DATE NOT NULL,
    hasta DATE NOT NULL,
    facturado DECIMAL(15,2) NOT NULL,
    porcentaje_ajuste DECIMAL(5,2) NOT NULL,
    datos_json LONGTEXT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    usuario VARCHAR(100),
    INDEX idx_empresa (empresa),
    INDEX idx_fecha (fecha_creacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// AJAX para obtener cuentas
if (isset($_GET['obtener_cuentas'])) {
    header('Content-Type: application/json');
    
    $empresa = $conn->real_escape_string($_GET['empresa'] ?? '');
    
    $sql = "SELECT id, nombre, empresa, desde, hasta, facturado, porcentaje_ajuste, 
                   datos_json, fecha_creacion, usuario 
            FROM cuentas_guardadas";
    
    if (!empty($empresa)) {
        $sql .= " WHERE empresa = '$empresa'";
    }
    
    $sql .= " ORDER BY fecha_creacion DESC";
    
    $result = $conn->query($sql);
    $cuentas = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $datos_json = json_decode($row['datos_json'], true);
            $row['datos_json'] = ($datos_json === null) ? [] : $datos_json;
            $cuentas[] = $row;
        }
    }
    
    echo json_encode($cuentas, JSON_UNESCAPED_UNICODE);
    exit;
}

// AJAX para eliminar cuenta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar_cuenta') {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    
    $sql = "DELETE FROM cuentas_guardadas WHERE id = $id";
    $resultado = $conn->query($sql);
    
    echo json_encode(['success' => $resultado, 'message' => $resultado ? 'Cuenta eliminada' : 'Error al eliminar']);
    exit;
}

// AJAX para cargar cuenta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cargar_cuenta') {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    $sql = "SELECT * FROM cuentas_guardadas WHERE id = $id";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $datos_json = json_decode($row['datos_json'], true);
        $row['datos_json'] = ($datos_json === null) ? [] : $datos_json;
        echo json_encode(['success' => true, 'cuenta' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Cuenta no encontrada']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestor de Cuentas Guardadas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .num { font-variant-numeric: tabular-nums; }
        .bd-badge { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">
    <div class="max-w-7xl mx-auto p-4">
        <!-- Header -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <h2 class="text-xl md:text-2xl font-bold">
                    📚 Cuentas guardadas en Base de Datos
                    <span class="bd-badge text-xs px-2 py-1 rounded-full ml-2">Base de Datos</span>
                </h2>
                <div class="flex items-center gap-2">
                    <select id="sel_empresa" class="rounded-lg border border-slate-300 px-3 py-2">
                        <option value="">-- Todas las empresas --</option>
                        <?php
                        $resEmp = $conn->query("SELECT DISTINCT empresa FROM cuentas_guardadas WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
                        if ($resEmp) while ($e = $resEmp->fetch_assoc()) {
                            echo '<option value="'.htmlspecialchars($e['empresa']).'">'.htmlspecialchars($e['empresa']).'</option>';
                        }
                        ?>
                    </select>
                    <button id="btnRecargarCuentas" class="rounded-lg border border-blue-300 px-3 py-2 bg-blue-50 hover:bg-blue-100">
                        🔄 Recargar
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabla de cuentas -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
                <div>
                    <h3 class="text-lg font-semibold">Cuentas almacenadas</h3>
                    <div class="text-xs text-slate-500 mt-1" id="contador-cuentas">
                        Cargando cuentas...
                    </div>
                </div>
                <div class="w-full md:w-64">
                    <input id="buscaCuenta" type="text" placeholder="Buscar por nombre..." 
                           class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
            </div>

            <div class="overflow-auto rounded-xl border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-blue-600 text-white">
                        <tr>
                            <th class="px-3 py-2 text-left">Nombre</th>
                            <th class="px-3 py-2 text-left">Rango</th>
                            <th class="px-3 py-2 text-right">Facturado</th>
                            <th class="px-3 py-2 text-right">% Ajuste</th>
                            <th class="px-3 py-2 text-center">Datos</th>
                            <th class="px-3 py-2 text-center">Fecha</th>
                            <th class="px-3 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyCuentas" class="divide-y divide-slate-100 bg-white">
                        <tr>
                            <td colspan="7" class="px-3 py-8 text-center text-slate-500">
                                <div class="animate-pulse">Cargando cuentas desde Base de Datos...</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const fmt = (n) => (n || 0).toLocaleString('es-CO');
        
        // Formatear fecha
        function formatFecha(fechaStr) {
            if (!fechaStr) return '';
            const fecha = new Date(fechaStr);
            return fecha.toLocaleDateString('es-CO', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Contar préstamos
        function contarPrestamosEnCuenta(cuenta) {
            if (!cuenta.datos_json || !cuenta.datos_json.prestamos) return 0;
            let total = 0;
            Object.values(cuenta.datos_json.prestamos).forEach(prestamosArray => {
                total += prestamosArray.length;
            });
            return total;
        }

        // Contar filas manuales
        function contarFilasManuales(cuenta) {
            if (!cuenta.datos_json || !cuenta.datos_json.filasManuales) return 0;
            return cuenta.datos_json.filasManuales.length;
        }

        // Renderizar cuentas
        async function renderCuentas() {
            const empresa = document.getElementById('sel_empresa').value;
            const filtro = document.getElementById('buscaCuenta').value.toLowerCase();
            
            try {
                const response = await fetch(`?obtener_cuentas=1&empresa=${encodeURIComponent(empresa)}`);
                const cuentas = await response.json();
                
                const tbodyCuentas = document.getElementById('tbodyCuentas');
                const contador = document.getElementById('contador-cuentas');
                
                // Filtrar por búsqueda
                const cuentasFiltradas = cuentas.filter(cuenta => 
                    !filtro || 
                    cuenta.nombre.toLowerCase().includes(filtro) ||
                    cuenta.usuario?.toLowerCase().includes(filtro)
                );
                
                contador.textContent = `Mostrando ${cuentasFiltradas.length} de ${cuentas.length} cuentas`;
                
                if (cuentasFiltradas.length === 0) {
                    tbodyCuentas.innerHTML = `
                        <tr>
                            <td colspan="7" class="px-3 py-8 text-center text-slate-500">
                                <div class="flex flex-col items-center gap-2">
                                    <div class="text-3xl">📭</div>
                                    <div>No hay cuentas guardadas</div>
                                    ${filtro ? '<div class="text-xs text-slate-400">No se encontraron cuentas con ese filtro</div>' : ''}
                                </div>
                            </td>
                        </tr>`;
                    return;
                }
                
                let html = '';
                cuentasFiltradas.forEach(cuenta => {
                    const totalPrestamos = contarPrestamosEnCuenta(cuenta);
                    const totalFilasManuales = contarFilasManuales(cuenta);
                    
                    html += `
                    <tr class="hover:bg-slate-50">
                        <td class="px-3 py-3">
                            <div class="font-medium">${cuenta.nombre}</div>
                            <div class="text-xs text-slate-500">${cuenta.usuario || 'Sistema'}</div>
                        </td>
                        <td class="px-3 py-3">
                            <div class="text-sm">${cuenta.desde} → ${cuenta.hasta}</div>
                        </td>
                        <td class="px-3 py-3 text-right num font-semibold">${fmt(cuenta.facturado || 0)}</td>
                        <td class="px-3 py-3 text-right num">${cuenta.porcentaje_ajuste || 0}%</td>
                        <td class="px-3 py-3 text-center">
                            <div class="flex flex-col gap-1 items-center">
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs ${totalPrestamos > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}">
                                    ${totalPrestamos} préstamos
                                </span>
                                ${totalFilasManuales > 0 ? 
                                `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] bg-blue-100 text-blue-700">
                                    ${totalFilasManuales} manuales
                                </span>` : ''}
                            </div>
                        </td>
                        <td class="px-3 py-3 text-center text-xs text-slate-500">
                            ${formatFecha(cuenta.fecha_creacion)}
                        </td>
                        <td class="px-3 py-3 text-right">
                            <div class="inline-flex gap-2">
                                <button class="btnCargarCuenta border px-3 py-2 rounded bg-blue-50 hover:bg-blue-100 text-xs text-blue-700" 
                                        data-id="${cuenta.id}"
                                        title="Cargar esta cuenta">
                                    📂 Cargar
                                </button>
                                <button class="btnEliminarCuenta border px-3 py-2 rounded bg-rose-50 hover:bg-rose-100 text-xs text-rose-700" 
                                        data-id="${cuenta.id}"
                                        title="Eliminar esta cuenta">
                                    🗑️
                                </button>
                            </div>
                        </td>
                    </tr>`;
                });
                
                tbodyCuentas.innerHTML = html;
                
                // Agregar eventos a los botones
                document.querySelectorAll('.btnCargarCuenta').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        const id = btn.dataset.id;
                        await cargarCuentaCompleta(id);
                    });
                });
                
                document.querySelectorAll('.btnEliminarCuenta').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        const id = btn.dataset.id;
                        await eliminarCuenta(id);
                    });
                });
                
            } catch (error) {
                console.error('Error al cargar cuentas:', error);
                document.getElementById('tbodyCuentas').innerHTML = `
                    <tr>
                        <td colspan="7" class="px-3 py-8 text-center text-rose-600">
                            <div class="flex flex-col items-center gap-2">
                                <div class="text-3xl">❌</div>
                                <div>Error al cargar cuentas</div>
                                <div class="text-xs">${error.message}</div>
                            </div>
                        </td>
                    </tr>`;
            }
        }

        // Cargar cuenta específica
        async function cargarCuentaCompleta(id) {
            const confirmacion = await Swal.fire({
                title: '¿Cargar esta cuenta?',
                text: 'Se restaurarán todos los datos guardados',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, cargar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#3b82f6'
            });
            
            if (!confirmacion.isConfirmed) return;
            
            try {
                const formData = new FormData();
                formData.append('accion', 'cargar_cuenta');
                formData.append('id', id);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const resultado = await response.json();
                
                if (resultado.success) {
                    Swal.fire({
                        title: '✅ Cuenta cargada',
                        text: 'Los datos de la cuenta están listos para usar',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    // Aquí podrías redirigir a la página principal con los datos
                    console.log('Cuenta cargada:', resultado.cuenta);
                } else {
                    throw new Error(resultado.message);
                }
            } catch (error) {
                Swal.fire({
                    title: '❌ Error',
                    text: error.message,
                    icon: 'error'
                });
            }
        }

        // Eliminar cuenta
        async function eliminarCuenta(id) {
            const confirmacion = await Swal.fire({
                title: '¿Eliminar esta cuenta?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ef4444'
            });
            
            if (!confirmacion.isConfirmed) return;
            
            try {
                const formData = new FormData();
                formData.append('accion', 'eliminar_cuenta');
                formData.append('id', id);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const resultado = await response.json();
                
                if (resultado.success) {
                    await renderCuentas();
                    Swal.fire({
                        title: '✅ Eliminada',
                        text: 'Cuenta eliminada correctamente',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    throw new Error(resultado.message);
                }
            } catch (error) {
                Swal.fire({
                    title: '❌ Error',
                    text: error.message,
                    icon: 'error'
                });
            }
        }

        // Eventos
        document.addEventListener('DOMContentLoaded', () => {
            renderCuentas();
            
            document.getElementById('btnRecargarCuentas').addEventListener('click', renderCuentas);
            document.getElementById('sel_empresa').addEventListener('change', renderCuentas);
            document.getElementById('buscaCuenta').addEventListener('input', renderCuentas);
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>