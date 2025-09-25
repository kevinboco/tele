<?php
// === Configuración inicial ===
error_reporting(E_ALL);
ini_set('display_errors', 1);

$token = "TU_TOKEN_AQUI";  // Reemplaza con tu token real
$apiURL = "https://api.telegram.org/bot$token/";

// Conexión BD (ajústala igual que en tu index.php original)
$conn = new mysqli("localhost", "usuario", "clave", "basedatos");
if ($conn->connect_error) {
    die("Error conexión BD: " . $conn->connect_error);
}

// Manejo de estado (asegúrate de tener estas funciones iguales a index.php original)
function saveState($state, $state_data = []) {
    file_put_contents("state.json", json_encode($state));
    file_put_contents("state_data.json", json_encode($state_data));
}
function loadState() {
    $state = file_exists("state.json") ? json_decode(file_get_contents("state.json"), true) : [];
    $state_data = file_exists("state_data.json") ? json_decode(file_get_contents("state_data.json"), true) : [];
    return [$state, $state_data];
}

// Recibir update de Telegram
$raw = file_get_contents("php://input");
$update = json_decode($raw, true);

$chat_id = $update["message"]["chat"]["id"] ?? null;
$text    = $update["message"]["text"] ?? "";

list($state, $state_data) = loadState();

// === Router de flujos ===
if (strpos($text, "/agg") === 0 || (isset($state[$chat_id]) && strpos($state[$chat_id], "agg") === 0)) {
    require "add.php";
    exit;
} elseif (strpos($text, "/manual") === 0 || (isset($state[$chat_id]) && strpos($state[$chat_id], "manual") === 0)) {
    require "manual.php";
    exit;
} else {
    // Aquí puedes manejar otros comandos generales o mensajes
    $reply = "Comando no reconocido. Usa /agg o /manual.";
    file_get_contents($apiURL."sendMessage?chat_id=$chat_id&text=".urlencode($reply));
}
