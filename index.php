<?php
// Mostrar errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Token del bot
$token = "7574806582:AAFKWFTIGy-vrqEpijTV9BClkpHAz0lZ2Yw";
$apiURL = "https://api.telegram.org/bot$token/";

// Recibir datos de Telegram
$update = json_decode(file_get_contents("php://input"), true);

// Guardar log para depuración
file_put_contents("debug.txt", print_r($update, true) . PHP_EOL, FILE_APPEND);

// Extraer datos principales
$chat_id = $update["message"]["chat"]["id"] ?? null;
$text    = $update["message"]["text"] ?? "";
$photo   = $update["message"]["photo"] ?? null;
$caption = $update["message"]["caption"] ?? "";

// === Manejo de estados (para /agg) ===
$estadoFile = __DIR__ . "/estado_$chat_id.json";
$estado = file_exists($estadoFile) ? json_decode(file_get_contents($estadoFile), true) : [];

// --- Comando /start ---
if ($text == "/start") {
    $mensaje = "👋 Hola! Soy el bot de viajes. Escribe:\n\n📌 /viaje Nombre Cedula Ruta Fecha Vehiculo (con foto)\n📌 /agg para agregar viaje paso a paso";

// --- Comando /agg ---
} elseif ($text == "/agg") {
    $estado = ["paso" => "nombre"];
    file_put_contents($estadoFile, json_encode($estado));
    $mensaje = "✍️ Ingresa el *nombre* del conductor:";

// === Flujo paso a paso para /agg ===
} elseif (!empty($estado)) {
    switch ($estado["paso"]) {
        case "nombre":
            $estado["nombre"] = $text;
            $estado["paso"] = "cedula";
            $mensaje = "🔢 Ahora ingresa la *cédula*:";
            break;

        case "cedula":
            $estado["cedula"] = $text;
            $estado["paso"] = "ruta";
            $mensaje = "🛣️ Ingresa la *ruta*:";
            break;

        case "ruta":
            $estado["ruta"] = $text;
            $estado["paso"] = "fecha";
            $mensaje = "📅 Ingresa la *fecha* (ejemplo: 2025-09-02):";
            break;

        case "fecha":
            $estado["fecha"] = $text;
            $estado["paso"] = "vehiculo";
            $mensaje = "🚐 Ingresa el *vehículo*:";
            break;

        case "vehiculo":
            $estado["vehiculo"] = $text;
            $estado["paso"] = "foto";
            $mensaje = "📸 Ahora envía la *foto* del viaje:";
            break;

        case "foto":
            if (!$photo) {
                $mensaje = "⚠️ Debes enviar una *foto*.";
            } else {
                // --- Procesar imagen ---
                $file_id = end($photo)["file_id"]; // mejor calidad
                $fileInfo = file_get_contents("https://api.telegram.org/bot$token/getFile?file_id=$file_id");
                $fileInfo = json_decode($fileInfo, true);

                $nombreArchivo = null;
                if (isset($fileInfo["result"]["file_path"])) {
                    $file_path = $fileInfo["result"]["file_path"];
                    $fileUrl   = "https://api.telegram.org/file/bot$token/$file_path";

                    $carpeta = __DIR__ . "/uploads/";
                    if (!is_dir($carpeta)) {
                        mkdir($carpeta, 0777, true);
                    }

                    $nombreArchivo = time() . "_" . basename($file_path);
                    $rutaCompleta  = $carpeta . $nombreArchivo;

                    if (!file_put_contents($rutaCompleta, file_get_contents($fileUrl))) {
                        $nombreArchivo = null;
                    }
                }

                if (!$nombreArchivo) {
                    $mensaje = "❌ Error al guardar la imagen. Intenta de nuevo.";
                } else {
                    // --- Guardar en BD ---
                    $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
                    if ($conn->connect_error) {
                        $mensaje = "❌ Error de conexión BD";
                    } else {
                        $sql = "INSERT INTO viajes (nombre, cedula, fecha, ruta, tipo_vehiculo, imagen) 
                                VALUES ('{$estado['nombre']}','{$estado['cedula']}','{$estado['fecha']}','{$estado['ruta']}','{$estado['vehiculo']}','$nombreArchivo')";
                        if ($conn->query($sql) === TRUE) {
                            $mensaje = "✅ Viaje registrado con éxito!";
                        } else {
                            $mensaje = "❌ Error al registrar: " . $conn->error;
                        }
                        $conn->close();
                    }
                }

                // Borrar estado porque terminó el flujo
                unlink($estadoFile);
            }
            break;
    }

    // Guardar el progreso del estado
    file_put_contents($estadoFile, json_encode($estado));

// --- Registrar viaje rápido (/viaje clásico) ---
} elseif (strpos($text, "/viaje") === 0 || ($photo && strpos($caption, "/viaje") === 0)) {
    $textoViaje = $text ?: $caption;
    $partes = explode(" ", $textoViaje, 6);

    if (count($partes) < 6) {
        $mensaje = "⚠️ Formato incorrecto. Usa:\n/viaje Nombre Cedula Ruta Fecha Vehiculo";
    } elseif (!$photo) {
        $mensaje = "⚠️ Debes adjuntar una foto obligatoriamente junto con el comando.";
    } else {
        $nombre   = $partes[1];
        $cedula   = $partes[2];
        $ruta     = $partes[3];
        $fecha    = $partes[4];
        $vehiculo = $partes[5];
        $nombreArchivo = null;

        // Procesar imagen
        $file_id = end($photo)["file_id"];
        $fileInfo = file_get_contents("https://api.telegram.org/bot$token/getFile?file_id=$file_id");
        $fileInfo = json_decode($fileInfo, true);

        if (isset($fileInfo["result"]["file_path"])) {
            $file_path = $fileInfo["result"]["file_path"];
            $fileUrl   = "https://api.telegram.org/file/bot$token/$file_path";

            $carpeta = __DIR__ . "/uploads/";
            if (!is_dir($carpeta)) {
                mkdir($carpeta, 0777, true);
            }

            $nombreArchivo = time() . "_" . basename($file_path);
            $rutaCompleta  = $carpeta . $nombreArchivo;

            if (!file_put_contents($rutaCompleta, file_get_contents($fileUrl))) {
                $nombreArchivo = null;
            }
        }

        if (!$nombreArchivo) {
            $mensaje = "❌ Error al guardar la imagen. Intenta de nuevo.";
        } else {
            $conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
            if ($conn->connect_error) {
                $mensaje = "❌ Error de conexión BD";
            } else {
                $sql = "INSERT INTO viajes (nombre, cedula, fecha, ruta, tipo_vehiculo, imagen) 
                        VALUES ('$nombre','$cedula','$fecha','$ruta','$vehiculo','$nombreArchivo')";
                if ($conn->query($sql) === TRUE) {
                    $mensaje = "✅ Viaje registrado con éxito!";
                } else {
                    $mensaje = "❌ Error al registrar: " . $conn->error;
                }
                $conn->close();
            }
        }
    }

// --- Cualquier otro texto ---
} else {
    $mensaje = "❓ No te entendí. Usa /start para ver comandos.";
}

// --- Enviar respuesta ---
file_get_contents($apiURL."sendMessage?chat_id=".$chat_id."&text=".urlencode($mensaje)."&parse_mode=Markdown");
?>

