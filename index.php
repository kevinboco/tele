<?php
// Mostrar errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Token del bot
$token = "7574806582:AAAFKWFTIGy-vrqEpijTV9BClkpHAz0lZ2Yw";
$apiURL = "https://api.telegram.org/bot$token/";

// Recibir datos de Telegram
$update = json_decode(file_get_contents("php://input"), true);

// Extraer datos
$chat_id = $update["message"]["chat"]["id"] ?? null;
$text    = $update["message"]["text"] ?? "";

// --- ARCHIVO PARA GUARDAR ESTADOS DE USUARIOS ---
$estadoFile = __DIR__."/estado_$chat_id.json";

// Inicializar mensaje
$mensaje = "❓ No te entendí. Usa /start para ver comandos.";

// --- /start ---
if ($text == "/start") {
    $mensaje = "👋 Hola! Soy el bot de viajes.\n\n📌 /viaje Nombre Cedula Ruta Fecha Vehiculo\n📌 /agg (registro paso a paso)";

// --- /agg paso a paso ---
} elseif ($text == "/agg") {
    $estado = ["paso" => "nombre"];
    file_put_contents($estadoFile, json_encode($estado));
    $mensaje = "✍️ Ingresa tu *Nombre*:";

// --- flujo de /agg ---
} elseif (file_exists($estadoFile)) {
    $estado = json_decode(file_get_contents($estadoFile), true);

    switch ($estado["paso"]) {
        case "nombre":
            $estado["nombre"] = $text;
            $estado["paso"] = "cedula";
            $mensaje = "🔢 Ahora ingresa tu *Cédula*:";
            break;

        case "cedula":
            $estado["cedula"] = $text;
            $estado["paso"] = "ruta";
            $mensaje = "📍 Ingresa la *Ruta*:";
            break;

        case "ruta":
            $estado["ruta"] = $text;
            $estado["paso"] = "fecha";
            $mensaje = "📅 Ingresa la *Fecha* (YYYY-MM-DD):";
            break;

        case "fecha":
            $estado["fecha"] = $text;
            $estado["paso"] = "vehiculo";
            $mensaje = "🚐 Ingresa el *Vehículo*:";
            break;

        case "vehiculo":
            $estado["vehiculo"] = $text;

            // Guardar en BD
            $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
            if ($conn->connect_error) {
                $mensaje = "❌ Error de conexión BD";
            } else {
                $nombre = $conn->real_escape_string($estado["nombre"]);
                $cedula = $conn->real_escape_string($estado["cedula"]);
                $ruta = $conn->real_escape_string($estado["ruta"]);
                $fecha = $conn->real_escape_string($estado["fecha"]);
                $vehiculo = $conn->real_escape_string($estado["vehiculo"]);

                $sql = "INSERT INTO viajes (nombre, cedula, fecha, ruta, tipo_vehiculo) 
                        VALUES ('$nombre','$cedula','$fecha','$ruta','$vehiculo')";

                if ($conn->query($sql) === TRUE) {
                    $mensaje = "✅ Viaje registrado con éxito!\n\n👤 Nombre: $nombre\n🔢 Cédula: $cedula\n📍 Ruta: $ruta\n📅 Fecha: $fecha\n🚐 Vehículo: $vehiculo";
                } else {
                    $mensaje = "❌ Error al registrar: " . $conn->error;
                }
                $conn->close();
            }

            // Eliminar estado
            unlink($estadoFile);
            break;
    }

    file_put_contents($estadoFile, json_encode($estado));
}

// --- /viaje (modo antiguo, todo en una línea) ---
elseif (strpos($text, "/viaje ") === 0) {
    $partes = explode(" ", $text, 6); 
    if (count($partes) < 6) {
        $mensaje = "⚠️ Formato incorrecto. Usa:\n/viaje Nombre Cedula Ruta Fecha Vehiculo";
    } else {
        $nombre = $partes[1];
        $cedula = $partes[2];
        $ruta = $partes[3];
        $fecha = $partes[4];
        $vehiculo = $partes[5];

        $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
        if ($conn->connect_error) {
            $mensaje = "❌ Error de conexión BD";
        } else {
            $sql = "INSERT INTO viajes (nombre, cedula, fecha, ruta, tipo_vehiculo) 
                    VALUES ('$nombre','$cedula','$fecha','$ruta','$vehiculo')";
            if ($conn->query($sql) === TRUE) {
                $mensaje = "✅ Viaje registrado con éxito!";
            } else {
                $mensaje = "❌ Error al registrar: " . $conn->error;
            }
            $conn->close();
        }
    }
}

// --- Enviar respuesta ---
file_get_contents($apiURL."sendMessage?chat_id=".$chat_id."&text=".urlencode($mensaje)."&parse_mode=Markdown");
?>
