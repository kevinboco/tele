<?php
// === Flujo /agg ===
// Extraído de index.php, sin modificaciones

// Verificamos si el texto recibido inicia con /agg
if ($text == "/agg") {
    // Pedir el nombre del conductor
    $reply = "Por favor ingresa el nombre del conductor:";
    file_get_contents($apiURL."sendMessage?chat_id=$chat_id&text=".urlencode($reply));
    $state[$chat_id] = "agg_conductor";
    saveState($state);
    exit;
}

if (($state[$chat_id] ?? "") == "agg_conductor") {
    $conductor = trim($text);

    // Guardamos conductor en BD si no existe
    $res = $conn->query("SELECT id FROM conductores WHERE nombre='$conductor'");
    if ($res->num_rows == 0) {
        $conn->query("INSERT INTO conductores (nombre) VALUES ('$conductor')");
    }

    $state_data[$chat_id]["conductor"] = $conductor;
    $reply = "Conductor guardado: $conductor\n\nAhora ingresa la ruta:";
    file_get_contents($apiURL."sendMessage?chat_id=$chat_id&text=".urlencode($reply));
    $state[$chat_id] = "agg_ruta";
    saveState($state, $state_data);
    exit;
}

if (($state[$chat_id] ?? "") == "agg_ruta") {
    $ruta = trim($text);

    // Guardamos ruta en BD si no existe
    $res = $conn->query("SELECT id FROM rutas WHERE nombre='$ruta'");
    if ($res->num_rows == 0) {
        $conn->query("INSERT INTO rutas (nombre) VALUES ('$ruta')");
    }

    $state_data[$chat_id]["ruta"] = $ruta;
    $reply = "Ruta guardada: $ruta\n\nAhora ingresa el año (YYYY):";
    file_get_contents($apiURL."sendMessage?chat_id=$chat_id&text=".urlencode($reply));
    $state[$chat_id] = "agg_anio";
    saveState($state, $state_data);
    exit;
}

if (($state[$chat_id] ?? "") == "agg_anio") {
    $anio = trim($text);
    $state_data[$chat_id]["anio"] = $anio;
    $reply = "Año guardado: $anio\n\nAhora ingresa el mes (MM):";
    file_get_contents($apiURL."sendMessage?chat_id=$chat_id&text=".urlencode($reply));
    $state[$chat_id] = "agg_mes";
    saveState($state, $state_data);
    exit;
}

if (($state[$chat_id] ?? "") == "agg_mes") {
    $mes = trim($text);
    $state_data[$chat_id]["mes"] = $mes;
    $reply = "Mes guardado: $mes\n\nAhora ingresa el día (DD):";
    file_get_contents($apiURL."sendMessage?chat_id=$chat_id&text=".urlencode($reply));
    $state[$chat_id] = "agg_dia";
    saveState($state, $state_data);
    exit;
}

if (($state[$chat_id] ?? "") == "agg_dia") {
    $dia = trim($text);

    $conductor = $state_data[$chat_id]["conductor"];
    $ruta      = $state_data[$chat_id]["ruta"];
    $anio      = $state_data[$chat_id]["anio"];
    $mes       = $state_data[$chat_id]["mes"];

    $fecha = "$anio-$mes-$dia";

    // Guardar viaje en la tabla viajes
    $stmt = $conn->prepare("INSERT INTO viajes (conductor, ruta, fecha) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $conductor, $ruta, $fecha);
    $stmt->execute();

    $reply = "✅ Viaje guardado:\nConductor: $conductor\nRuta: $ruta\nFecha: $fecha";
    file_get_contents($apiURL."sendMessage?chat_id=$chat_id&text=".urlencode($reply));

    unset($state[$chat_id]);
    unset($state_data[$chat_id]);
    saveState($state, $state_data);
    exit;
}
