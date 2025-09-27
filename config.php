<?php
// config.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ⚠️ TIP: mueve el token a una variable de entorno si puedes.
$TOKEN  = "7574806582:AAFKWFTIGy-vrqEpijTV9BClkpHAz0lZ2Yw";
$APIURL = "https://api.telegram.org/bot$TOKEN/";

define('DB_HOST', 'mysql.hostinger.com');
define('DB_USER', 'u648222299_keboco5');
define('DB_PASS', 'Bucaramanga3011');
define('DB_NAME', 'u648222299_viajes');

define('STATE_TTL', 600); // 10 min
