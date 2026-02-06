<?php
// ================================================
// PROCESAR ELIMINACIÓN DE TARIFAS
// ================================================
if (isset($_POST['eliminar_tarifa'])) {
    $empresa = $_POST['empresa'] ?? '';
    $tipo_vehiculo = $_POST['tipo_vehiculo'] ?? '';
    $campo = $_POST['campo'] ?? '';
    
    // Validar datos
    if (empty($empresa) || empty($tipo_vehiculo) || empty($campo)) {
        echo "error: datos incompletos";
        exit;
    }
    
    // Aquí va tu lógica para eliminar la tarifa de la base de datos
    // Ejemplo con MySQLi:
    $conn = new mysqli("localhost", "usuario", "contraseña", "base_datos");
    
    // Primero, verificar si la tarifa existe
    $sql_check = "SELECT COUNT(*) as count FROM tarifas 
                  WHERE empresa = ? AND tipo_vehiculo = ? AND campo_tarifa = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("sss", $empresa, $tipo_vehiculo, $campo);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $row = $result_check->fetch_assoc();
    
    if ($row['count'] > 0) {
        // Eliminar la tarifa
        $sql_delete = "DELETE FROM tarifas 
                       WHERE empresa = ? AND tipo_vehiculo = ? AND campo_tarifa = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("sss", $empresa, $tipo_vehiculo, $campo);
        
        if ($stmt_delete->execute()) {
            // También eliminar de la variable $tarifas_guardadas si existe
            if (isset($tarifas_guardadas[$tipo_vehiculo][$campo])) {
                unset($tarifas_guardadas[$tipo_vehiculo][$campo]);
            }
            
            // Actualizar la lista de columnas de tarifas
            $columnas_tarifas = array_unique(array_merge(
                ...array_map('array_keys', $tarifas_guardadas)
            ));
            
            echo "ok";
        } else {
            echo "error: no se pudo eliminar";
        }
        $stmt_delete->close();
    } else {
        echo "error: tarifa no encontrada";
    }
    
    $stmt_check->close();
    $conn->close();
    exit;
}
?>