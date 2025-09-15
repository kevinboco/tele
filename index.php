<?php
require 'vendor/autoload.php';

use Telegram\Bot\Api;

// Token del bot
$telegram = new Api('AQUI_TU_TOKEN');

// Conexión a la BD
$host = "localhost";
$dbname = "transportes";
$user = "root";
$pass = "";
$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);

// Recibir datos del webhook
$update = json_decode(file_get_contents("php://input"), true);

$chatId = $update["message"]["chat"]["id"] ?? null;
$text   = trim($update["message"]["text"] ?? "");
$photo  = $update["message"]["photo"] ?? null;

// Manejo de estados en sesión (archivo temporal por usuario)
function setState($chatId, $state) {
    file_put_contents("states/{$chatId}.txt", $state);
}
function getState($chatId) {
    $file = "states/{$chatId}.txt";
    return file_exists($file) ? file_get_contents($file) : null;
}
function clearState($chatId) {
    $file = "states/{$chatId}.txt";
    if (file_exists($file)) unlink($file);
}

// FLUJO
if ($text) {
    if (strpos($text, "/agg") === 0) {
        // Inicia el flujo
        setState($chatId, "esperando_fecha");
        $telegram->sendMessage([
            "chat_id" => $chatId,
            "text" => "📅 Por favor ingresa la fecha del viaje (YYYY-MM-DD):"
        ]);
    } else {
        // Si no empieza con /agg no se permite el flujo
        $telegram->sendMessage([
            "chat_id" => $chatId,
            "text" => "⚠️ Para registrar un viaje debes iniciar con /agg"
        ]);
    }
} elseif ($photo) {
    $state = getState($chatId);
    if ($state === "esperando_foto") {
        // Guardar viaje en BD
        $stmt = $pdo->prepare("INSERT INTO viajes (chat_id, fecha, foto_id) VALUES (?, ?, ?)");
        $stmt->execute([
            $chatId,
            getState($chatId . "_fecha"), // guardamos la fecha temporal
            end($photo)["file_id"]
        ]);

        clearState($chatId);
        $telegram->sendMessage([
            "chat_id" => $chatId,
            "text" => "✅ Viaje registrado con éxito!"
        ]);
    } else {
        $telegram->sendMessage([
            "chat_id" => $chatId,
            "text" => "⚠️ Primero debes iniciar con /agg y enviar la fecha."
        ]);
    }
} else {
    $state = getState($chatId);
    if ($state === "esperando_fecha") {
        // Validar fecha
        if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $text)) {
            // Guardamos la fecha en un archivo temporal separado
            setState($chatId . "_fecha", $text);
            setState($chatId, "esperando_foto");

            $telegram->sendMessage([
                "chat_id" => $chatId,
                "text" => "📸 Debes enviar una foto del viaje."
            ]);
        } else {
            $telegram->sendMessage([
                "chat_id" => $chatId,
                "text" => "⚠️ Formato de fecha inválido. Usa YYYY-MM-DD."
            ]);
        }
    }
}
