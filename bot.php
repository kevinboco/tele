<?php
// bot.php en /tele/

// Recibir el JSON que envía Telegram
$update = json_decode(file_get_contents("php://input"), true);

// Guardar en un log para pruebas
file_put_contents("log.txt", print_r($update, true), FILE_APPEND);

// Extraer datos básicos
$chat_id = $update["message"]["chat"]["id"] ?? null;
$text    = $update["message"]["text"] ?? "";

// Si es un mensaje de texto, responder
if ($chat_id && $text) {
    $token = "7574806582:AAFKWFTIGy-vrqEpijTV9BClkpHAz0lZ2Yw";
    $url = "https://api.telegram.org/bot$token/sendMessage";

    $data = [
        'chat_id' => $chat_id,
        'text'    => "Recibí tu mensaje: $text"
    ];

    // Enviar respuesta
    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
        ],
    ];
    $context  = stream_context_create($options);
    file_get_contents($url, false, $context);
}
