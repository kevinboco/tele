<?php
// ver_log.php
header('Content-Type: text/plain; charset=utf-8');

$log_file = __DIR__ . "/manual_debug.log";

if (file_exists($log_file)) {
    echo "=== ÚLTIMAS 100 LÍNEAS DEL LOG ===\n\n";
    echo file_get_contents($log_file);
    echo "\n\n=== FIN DEL LOG ===";
} else {
    echo "El archivo de log aún no existe. Usa /manual en el bot primero.";
}