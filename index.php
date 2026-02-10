<?php
// index.php (MODIFICADO)
require_once __DIR__.'/router.php';

$raw = file_get_contents("php://input");
$update = json_decode($raw, true) ?: [];
file_put_contents("debug.txt", date('Y-m-d H:i:s') . " " . print_r($update, true) . PHP_EOL, FILE_APPEND);

// ========= NUEVO: EJECUTAR VERIFICACIÓN DE ALERTAS PROGRAMADAS =========
// Cada vez que alguien interactúa con el bot, verificamos si es hora de alertas
require_once __DIR__.'/flow_alert.php';
alert_verificar_programadas();
// ========= FIN NUEVO =========

routeUpdate($update);