<?php
include("config.php");

// Recibir actualización de Telegram
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if(!$update) {
    exit;
}

$chatId = $update["message"]["chat"]["id"];
$text = $update["message"]["text"] ?? "";

// Estados simples para el flujo de registro
session_start();
if (!isset($_SESSION["step"])) $_SESSION["step"] = "inicio";

switch($_SESSION["step"]) {
    case "inicio":
        sendMessage($chatId, "¡Hola! 🚖 Por favor escribe tu *nombre completo*:");
        $_SESSION["step"] = "nombre";
        break;

    case "nombre":
        $_SESSION["nombre"] = $text;
        sendMessage($chatId, "Perfecto ✅ Ahora dime tu *cédula*:");
        $_SESSION["step"] = "cedula";
        break;

    case "cedula":
        $_SESSION["cedula"] = $text;
        sendMessage($chatId, "Gracias 🙌 Ahora escribe la *ruta* (ejemplo: MAICAO - NARETH):");
        $_SESSION["step"] = "ruta";
        break;

    case "ruta":
        $_SESSION["ruta"] = $text;
        sendMessage($chatId, "Genial 🚍 Ahora escribe el *tipo de vehículo* (carro, buseta, etc.):");
        $_SESSION["step"] = "vehiculo";
        break;

    case "vehiculo":
        $_SESSION["vehiculo"] = $text;
        sendMessage($chatId, "Por último 📸 envíame una *foto del viaje*.");
        $_SESSION["step"] = "foto";
        break;

    case "foto":
        if(isset($update["message"]["photo"])) {
            $fileId = end($update["message"]["photo"])["file_id"];
            $filePath = getFile($fileId);

            $savePath = "uploads/" . time() . ".jpg";
            file_put_contents($savePath, file_get_contents("https://api.telegram.org/file/bot$botToken/$filePath"));

            // Guardar en BD
            $stmt = $conexion->prepare("INSERT INTO viajes (nombre, cedula, fecha, ruta, tipo_vehiculo, imagen) VALUES (?, ?, NOW(), ?, ?, ?)");
            $stmt->bind_param("sssss", $_SESSION["nombre"], $_SESSION["cedula"], $_SESSION["ruta"], $_SESSION["vehiculo"], $savePath);
            $stmt->execute();

            sendMessage($chatId, "✅ Tu viaje ha sido registrado con éxito. ¡Gracias!");
            $_SESSION = []; // limpiar
        } else {
            sendMessage($chatId, "Por favor envíame una foto 📸");
        }
        break;
}

function sendMessage($chatId, $text) {
    global $telegramAPI;
    $url = $telegramAPI."sendMessage";
    $post = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'Markdown'];
    file_get_contents($url."?".http_build_query($post));
}

function getFile($fileId) {
    global $telegramAPI;
    $resp = file_get_contents($telegramAPI."getFile?file_id=".$fileId);
    $resp = json_decode($resp, true);
    return $resp["result"]["file_path"];
}
?>
