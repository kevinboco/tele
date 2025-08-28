<?php
// Configuración de tu bot de Telegram
$botToken = "AQUI_TU_TOKEN"; 
$apiURL = "https://api.telegram.org/bot$botToken/";

// Si la petición es de Telegram (Webhook con POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents("php://input");
    $update = json_decode($input, true);

    if (isset($update["message"])) {
        $chat_id = $update["message"]["chat"]["id"];
        $text = strtolower(trim($update["message"]["text"]));

        // Ejemplo de respuestas
        if ($text === "hola") {
            $reply = "¡Hola! Bienvenido 🚍 ¿Quieres ver el catálogo de viajes?";
        } elseif ($text === "catalogo") {
            $reply = "Aquí tienes nuestro catálogo 📒: https://asociacion.asociaciondetransportistaszonanorte.io/catalogo.pdf";
        } else {
            $reply = "No entendí tu mensaje. Escribe 'catalogo' para ver los viajes.";
        }

        // Enviar respuesta
        file_get_contents($apiURL."sendMessage?chat_id=".$chat_id."&text=".urlencode($reply));
    }

    // Responder 200 OK a Telegram
    http_response_code(200);
    exit();
}

// Si la petición es normal (GET), carga tu web actual
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asociación de Transportistas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Asociación</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="bienvenida.php">Inicio</a></li>
                    <li class="nav-item"><a class="nav-link" href="formulario_asociado.php">Subir documento</a></li>
                    <li class="nav-item"><a class="nav-link" href="ver_documentos.php">Ver documentos</a></li>
                    <li class="nav-item"><a class="nav-link" href="ver_cuentas_cobro.php">Cuentas de Cobro</a></li>
                    <li class="nav-item"><a class="nav-link" href="estadisticas.php">Estadísticas</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <h1>Bienvenido a la Asociación de Transportistas Zona Norte</h1>
        <p>Desde aquí puedes navegar por el sistema.</p>
    </div>
</body>
</html>
