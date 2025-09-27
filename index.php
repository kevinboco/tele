<?php
// index.php
require_once __DIR__.'/router.php';

$raw = file_get_contents("php://input");
$update = json_decode($raw, true) ?: [];
file_put_contents("debug.txt", date('Y-m-d H:i:s') . " " . print_r($update, true) . PHP_EOL, FILE_APPEND);

routeUpdate($update);
