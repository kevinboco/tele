<?php
// cleanup.php - Ejecutar manualmente si hay problemas
$dir = __DIR__;
$files = glob($dir . "/estado_*.json");
$files = array_merge($files, glob($dir . "/lock_*.lock"));
$files = array_merge($files, glob($dir . "/last_update_*.txt"));
$files = array_merge($files, glob($dir . "/rate_*.json"));

foreach ($files as $file) {
    @unlink($file);
    echo "Eliminado: " . basename($file) . "\n";
}

echo "\n✅ Limpieza completada. Todos los estados y locks eliminados.\n";