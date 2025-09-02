<?php
// Mostrar errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Token del bot
$token = "7574806582:AAFKWFTIGy-vrqEpijTV9BClkpHAz0lZ2Yw";
$apiURL = "https://api.telegram.org/bot$token/";

// Recibir datos de Telegram
$update = json_decode(file_get_contents("php://input"), true);

// Guardar log
file_put_contents("debug.txt", print_r($update, true) . PHP_EOL, FILE_APPEND);

$chat_id = $update["message"]["chat"]["id"] ?? null;
$text    = $update["message"]["text"] ?? "";
$photo   = $update["message"]["photo"] ?? null;
$caption = $update["message"]["caption"] ?? "";

// === Manejo de estado ===
$estadoFile = __DIR__ . "/estado_$chat_id.json";
$estado = file_exists($estadoFile) ? json_decode(file_get_contents($estadoFile), true) : [];

// --- Conexión BD ---
$conn = new mysqli("mysql.hostinger.com", "u648222299_keboco5", "Bucaramanga3011", "u648222299_viajes");
if ($conn->connect_error) {
    die("Error de conexión BD: " . $conn->connect_error);
}

// --- /start ---
if ($text == "/start") {
    $mensaje = "👋 Hola! Soy el bot de viajes.\n\n📌 /viaje Nombre Cedula Ruta Fecha Vehiculo (con foto)\n📌 /agg para registrar viaje paso a paso";

// --- /agg ---
} elseif ($text == "/agg") {
    // Cargar datos previos si existen
    $sql = "SELECT nombre, cedula, tipo_vehiculo FROM viajes WHERE chat_id='$chat_id' ORDER BY id DESC LIMIT 1";
    $res = $conn->query($sql);

    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        // Ya tiene datos → saltamos directo a ruta
        $estado = [
            "paso" => "ruta",
            "nombre" => $row["nombre"],
            "cedula" => $row["cedula"],
            "vehiculo" => $row["tipo_vehiculo"]
        ];

        // Buscar rutas usadas por este usuario
        $rutas = [];
        $rsRutas = $conn->query("SELECT DISTINCT ruta FROM viajes WHERE chat_id='$chat_id' ORDER BY id DESC LIMIT 5");
        while ($r = $rsRutas->fetch_assoc()) {
            $rutas[] = [["text" => $r["ruta"]]];
        }

        $keyboard = json_encode(["keyboard" => $rutas, "one_time_keyboard" => true, "resize_keyboard" => true]);
        $mensaje = "🛣️ Selecciona una *ruta* o escribe una nueva:";
        file_get_contents($apiURL."sendMessage?chat_id=".$chat_id."&text=".urlencode($mensaje)."&parse_mode=Markdown&reply_markup=".$keyboard);

        file_put_contents($estadoFile, json_encode($estado));
        exit;
    } else {
        // No tiene datos → pedir desde el inicio
        $estado = ["paso" => "nombre"];
        $mensaje = "✍️ Ingresa el *nombre* del conductor:";
    }

// === Flujo /agg ===
} elseif (!empty($estado)) {
    switch ($estado["paso"]) {
        case "nombre":
            $estado["nombre"] = $text;
            $estado["paso"] = "cedula";
            $mensaje = "🔢 Ahora ingresa la *cédula*:";
            break;

        case "cedula":
            $estado["cedula"] = $text;
            $estado["paso"] = "vehiculo";
            $mensaje = "🚐 Ingresa el *vehículo*:";
            break;

        case "vehiculo":
            $estado["vehiculo"] = $text;
            $estado["paso"] = "ruta";

            // Buscar rutas anteriores
            $rutas = [];
            $rsRutas = $conn->query("SELECT DISTINCT ruta FROM viajes WHERE chat_id='$chat_id' ORDER BY id DESC LIMIT 5");
            while ($r = $rsRutas->fetch_assoc()) {
                $rutas[] = [["text" => $r["ruta"]]];
            }

            $keyboard = json_encode(["keyboard" => $rutas, "one_time_keyboard" => true, "resize_keyboard" => true]);
            $mensaje = "🛣️ Selecciona una *ruta* o escribe una nueva:";
            file_get_contents($apiURL."sendMessage?chat_id=".$chat_id."&text=".urlencode($mensaje)."&parse_mode=Markdown&reply_markup=".$keyboard);
            file_put_contents($estadoFile, json_encode($estado));
            exit;

        case "ruta":
            $estado["ruta"] = $text;
            $estado["paso"] = "fecha";

            // Opción rápida: fecha de hoy
            $hoy = date("Y-m-d");
            $keyboard = json_encode([
                "keyboard" => [[["text" => $hoy]]],
                "one_time_keyboard" => true,
                "resize_keyboard" => true
            ]);

            $mensaje = "📅 Ingresa la *fecha* del viaje (o selecciona hoy):";
            file_get_contents($apiURL."sendMessage?chat_id=".$chat_id."&text=".urlencode($mensaje)."&parse_mode=Markdown&reply_markup=".$keyboard);
            file_put_contents($estadoFile, json_encode($estado));
            exit;

        case "fecha":
            $estado["fecha"] = $text;
            $estado["paso"] = "foto";
            $mensaje = "📸 Ahora envía la *foto* del viaje:";
            break;

        case "foto":
            if (!$photo) {
                $mensaje = "⚠️ Debes enviar una *foto*.";
            } else {
                // Procesar imagen
                $file_id = end($photo)["file_id"];
                $fileInfo = file_get_contents("https://api.telegram.org/bot$token/getFile?file_id=$file_id");
                $fileInfo = json_decode($fileInfo, true);

                $nombreArchivo = null;
                if (isset($fileInfo["result"]["file_path"])) {
                    $file_path = $fileInfo["result"]["file_path"];
                    $fileUrl   = "https://api.telegram.org/file/bot$token/$file_path";

                    $carpeta = __DIR__ . "/uploads/";
                    if (!is_dir($carpeta)) mkdir($carpeta, 0777, true);

                    $nombreArchivo = time() . "_" . basename($file_path);
                    $rutaCompleta  = $carpeta . $nombreArchivo;

                    if (!file_put_contents($rutaCompleta, file_get_contents($fileUrl))) {
                        $nombreArchivo = null;
                    }
                }

                if (!$nombreArchivo) {
                    $mensaje = "❌ Error al guardar la imagen. Intenta de nuevo.";
                } else {
                    // Guardar viaje en BD
                    $sql = "INSERT INTO viajes (chat_id, nombre, cedula, fecha, ruta, tipo_vehiculo, imagen) 
                            VALUES ('$chat_id','{$estado['nombre']}','{$estado['cedula']}','{$estado['fecha']}','{$estado['ruta']}','{$estado['vehiculo']}','$nombreArchivo')";
                    if ($conn->query($sql) === TRUE) {
                        $mensaje = "✅ Viaje registrado con éxito!";
                    } else {
                        $mensaje = "❌ Error al registrar: " . $conn->error;
                    }
                }

                // Terminar flujo
                unlink($estadoFile);
            }
            break;
    }

    file_put_contents($estadoFile, json_encode($estado));

// --- /viaje rápido ---
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

        // Procesar imagen
        $file_id = end($photo)["file_id"];
        $fileInfo = file_get_contents("https://api.telegram.org/bot$token/getFile?file_id=$file_id");
        $fileInfo = json_decode($fileInfo, true);

        $nombreArchivo = null;
        if (isset($fileInfo["result"]["file_path"])) {
            $file_path = $fileInfo["result"]["file_path"];
            $fileUrl   = "https://api.telegram.org/file/bot$token/$file_path";

            $carpeta = __DIR__ . "/uploads/";
            if (!is_dir($carpeta)) mkdir($carpeta, 0777, true);

            $nombreArchivo = time() . "_" . basename($file_path);
            $rutaCompleta  = $carpeta . $nombreArchivo;

            if (!file_put_contents($rutaCompleta, file_get_contents($fileUrl))) {
                $nombreArchivo = null;
            }
        }

        if (!$nombreArchivo) {
            $mensaje = "❌ Error al guardar la imagen.";
        } else {
            $sql = "INSERT INTO viajes (chat_id, nombre, cedula, fecha, ruta, tipo_vehiculo, imagen) 
                    VALUES ('$chat_id','$nombre','$cedula','$fecha','$ruta','$vehiculo','$nombreArchivo')";
            if ($conn->query($sql) === TRUE) {
                $mensaje = "✅ Viaje registrado con éxito!";
            } else {
                $mensaje = "❌ Error al registrar: " . $conn->error;
            }
        }
    }

// --- Otro texto ---
} else {
    $mensaje = "❓ No te entendí. Usa /start para ver comandos.";
}

// Enviar mensaje
file_get_contents($apiURL."sendMessage?chat_id=".$chat_id."&text=".urlencode($mensaje)."&parse_mode=Markdown");

$conn->close();
?>
