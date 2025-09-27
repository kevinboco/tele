<?php
// flow_manual.php
require_once __DIR__.'/helpers.php';

function manual_entrypoint($chat_id, $estado) {
    // Si ya estás en manual, reenvía el paso
    if (!empty($estado) && ($estado['flujo'] ?? '') === 'manual') {
        return manual_resend_current_step($chat_id, $estado);
    }
    // Nuevo flujo
    $estado = ["flujo"=>"manual","paso"=>"manual_menu"];
    saveState($chat_id, $estado);

    $conn = db();
    $conductores = $conn ? obtenerConductoresAdmin($conn, $chat_id) : [];
    $conn?->close();

    if ($conductores) {
        $kb=["inline_keyboard"=>[]];
        foreach ($conductores as $c) $kb["inline_keyboard"][] = [[ "text"=>$c['nombre'], "callback_data"=>"manual_sel_".$c['id'] ]];
        $kb["inline_keyboard"][] = [[ "text"=>"➕ Nuevo conductor", "callback_data"=>"manual_nuevo" ]];
        sendMessage($chat_id, "Elige un *conductor* o crea uno nuevo:", $kb);
    } else {
        $estado['paso'] = 'manual_nombre_nuevo'; saveState($chat_id, $estado);
        sendMessage($chat_id, "No tienes conductores guardados.\n✍️ Escribe el *nombre* del nuevo conductor:");
    }
}

function manual_resend_current_step($chat_id, $estado) {
    $conn = db();
    switch ($estado['paso']) {
        case 'manual_menu':
            $conductores = $conn ? obtenerConductoresAdmin($conn, $chat_id) : [];
            if ($conductores) {
                $kb=["inline_keyboard"=>[]];
                foreach ($conductores as $c) $kb["inline_keyboard"][] = [[ "text"=>$c['nombre'], "callback_data"=>"manual_sel_".$c['id'] ]];
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nuevo conductor", "callback_data"=>"manual_nuevo" ]];
                sendMessage($chat_id, "Elige un *conductor* o crea uno nuevo:", $kb);
            } else {
                sendMessage($chat_id, "No tienes conductores guardados.\n✍️ Escribe el *nombre* del nuevo conductor:");
                $estado['paso']='manual_nombre_nuevo'; saveState($chat_id,$estado);
            }
            break;
        case 'manual_nombre_nuevo':
            sendMessage($chat_id, "✍️ Escribe el *nombre* del nuevo conductor:"); break;
        case 'manual_ruta_menu':
            $rutas = $conn ? obtenerRutasAdmin($conn, $chat_id) : [];
            if ($rutas) {
                $kb=["inline_keyboard"=>[]];
                foreach ($rutas as $r) $kb["inline_keyboard"][] = [[ "text"=>$r['ruta'], "callback_data"=>"manual_ruta_sel_".$r['id'] ]];
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nueva ruta", "callback_data"=>"manual_ruta_nueva" ]];
                sendMessage($chat_id, "Selecciona una *ruta* o crea una nueva:", $kb);
            } else {
                sendMessage($chat_id, "No tienes rutas guardadas.\n✍️ Escribe la *ruta del viaje*:");
                $estado['paso']='manual_ruta_nueva_texto'; saveState($chat_id,$estado);
            }
            break;
        case 'manual_ruta_nueva_texto':
            sendMessage($chat_id, "✍️ Escribe la *ruta del viaje*:"); break;
        case 'manual_ruta':
            sendMessage($chat_id, "🛣️ Ingresa la *ruta del viaje*:"); break;
        case 'manual_fecha':
            sendMessage($chat_id, "📅 Selecciona la *fecha*:", kbFechaManual()); break;
        case 'manual_fecha_mes':
            $anio=$estado["anio"] ?? date("Y");
            sendMessage($chat_id, "📆 Selecciona el *mes*:", kbMeses($anio)); break;
        case 'manual_fecha_dia_input':
            $anio=(int)($estado["anio"] ?? date("Y"));
            $mes =(int)($estado["mes"]  ?? date("m"));
            $maxDias=cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
            sendMessage($chat_id, "✍️ Escribe el *día* del mes (1–$maxDias):"); break;
        case 'manual_vehiculo_menu':
            $vehiculos = $conn ? obtenerVehiculosAdmin($conn, $chat_id) : [];
            if ($vehiculos) {
                $kb=["inline_keyboard"=>[]];
                foreach ($vehiculos as $v) $kb["inline_keyboard"][] = [[ "text"=>$v['vehiculo'], "callback_data"=>"manual_vehiculo_sel_".$v['id'] ]];
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nuevo vehículo", "callback_data"=>"manual_vehiculo_nuevo" ]];
                sendMessage($chat_id, "🚐 Selecciona el *tipo de vehículo* o crea uno nuevo:", $kb);
            } else {
                sendMessage($chat_id, "No tienes vehículos guardados.\n✍️ Escribe el *tipo de vehículo* (ej.: Toyota Hilux 4x4):");
                $estado['paso']='manual_vehiculo_nuevo_texto'; saveState($chat_id,$estado);
            }
            break;
        case 'manual_vehiculo_nuevo_texto':
            sendMessage($chat_id, "✍️ Escribe el *tipo de vehículo*:"); break;
        case 'manual_empresa_menu':
            $empresas = $conn ? obtenerEmpresasAdmin($conn, $chat_id) : [];
            if ($empresas) {
                $kb=["inline_keyboard"=>[]];
                foreach ($empresas as $e) $kb["inline_keyboard"][] = [[ "text"=>$e['nombre'], "callback_data"=>"manual_empresa_sel_".$e['id'] ]];
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nueva empresa", "callback_data"=>"manual_empresa_nuevo" ]];
                sendMessage($chat_id, "🏢 Selecciona la *empresa* o crea una nueva:", $kb);
            } else {
                sendMessage($chat_id, "No tienes empresas guardadas.\n✍️ Escribe el *nombre de la empresa*:");
                $estado['paso']='manual_empresa_nuevo_texto'; saveState($chat_id,$estado);
            }
            break;
        case 'manual_empresa_nuevo_texto':
            sendMessage($chat_id, "✍️ Escribe el *nombre de la empresa*:"); break;
        default:
            sendMessage($chat_id, "Continuamos donde ibas. Escribe /cancel para reiniciar.");
    }
    $conn?->close();
}

function manual_handle_callback($chat_id, &$estado, $cb_data, $cb_id=null) {
    if (($estado["flujo"] ?? "") !== "manual") return;

    // Seleccionar conductor existente
    if (strpos($cb_data, 'manual_sel_') === 0) {
        $idSel = (int)substr($cb_data, strlen('manual_sel_'));
        $conn = db(); $row = obtenerConductorAdminPorId($conn, $idSel, $chat_id); $conn?->close();
        if (!$row) { sendMessage($chat_id, "⚠️ Conductor no encontrado. Vuelve a intentarlo con /manual."); }
        else {
            $estado['manual_nombre'] = $row['nombre'];
            $estado['paso'] = 'manual_ruta_menu'; saveState($chat_id,$estado);

            $conn = db(); $rutas = $conn ? obtenerRutasAdmin($conn, $chat_id) : []; $conn?->close();
            if ($rutas) {
                $kb=["inline_keyboard"=>[]];
                foreach ($rutas as $r) $kb["inline_keyboard"][] = [[ "text"=>$r['ruta'], "callback_data"=>"manual_ruta_sel_".$r['id'] ]];
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nueva ruta", "callback_data"=>"manual_ruta_nueva" ]];
                sendMessage($chat_id, "👤 Conductor: *{$row['nombre']}*\n\nSelecciona una *ruta* o crea una nueva:", $kb);
            } else {
                $estado['paso']='manual_ruta_nueva_texto'; saveState($chat_id,$estado);
                sendMessage($chat_id, "👤 Conductor: *{$row['nombre']}*\n\n✍️ Escribe la *ruta del viaje*:");
            }
        }
    }

    // Crear nuevo conductor
    if ($cb_data === 'manual_nuevo') {
        $estado['paso'] = 'manual_nombre_nuevo'; saveState($chat_id,$estado);
        sendMessage($chat_id, "✍️ Escribe el *nombre* del nuevo conductor:");
    }

    // Seleccionar ruta existente
    if (strpos($cb_data, 'manual_ruta_sel_') === 0) {
        $idRuta = (int)substr($cb_data, strlen('manual_ruta_sel_'));
        $conn = db(); $r = obtenerRutaAdminPorId($conn, $idRuta, $chat_id); $conn?->close();
        if (!$r) sendMessage($chat_id, "⚠️ Ruta no encontrada. Vuelve a intentarlo.");
        else {
            $estado['manual_ruta'] = $r['ruta'];
            $estado['paso'] = 'manual_fecha'; saveState($chat_id,$estado);
            sendMessage($chat_id, "🛣️ Ruta: *{$r['ruta']}*\n\n📅 Selecciona la *fecha*:", kbFechaManual());
        }
    }

    // Crear nueva ruta
    if ($cb_data === 'manual_ruta_nueva') {
        $estado['paso'] = 'manual_ruta_nueva_texto'; saveState($chat_id,$estado);
        sendMessage($chat_id, "✍️ Escribe la *ruta del viaje*:");
    }

    // Fecha
    if ($cb_data === 'mfecha_hoy') {
        $estado['manual_fecha'] = date("Y-m-d");
        $estado['paso'] = 'manual_vehiculo_menu'; saveState($chat_id,$estado);

        $conn = db(); $vehiculos = $conn ? obtenerVehiculosAdmin($conn, $chat_id) : []; $conn?->close();
        if ($vehiculos) {
            $kb=["inline_keyboard"=>[]];
            foreach ($vehiculos as $v) $kb["inline_keyboard"][] = [[ "text"=>$v['vehiculo'], "callback_data"=>"manual_vehiculo_sel_".$v['id'] ]];
            $kb["inline_keyboard"][] = [[ "text"=>"➕ Nuevo vehículo", "callback_data"=>"manual_vehiculo_nuevo" ]];
            sendMessage($chat_id, "🚐 Selecciona el *tipo de vehículo* o crea uno nuevo:", $kb);
        } else {
            $estado['paso']='manual_vehiculo_nuevo_texto'; saveState($chat_id,$estado);
            sendMessage($chat_id, "No tienes vehículos guardados.\n✍️ Escribe el *tipo de vehículo* (ej.: Toyota Hilux 4x4):");
        }
    }
    if ($cb_data === 'mfecha_otro') {
        $anio = date("Y"); $estado["anio"]=$anio;
        $estado["paso"]="manual_fecha_mes"; saveState($chat_id,$estado);
        sendMessage($chat_id, "📆 Selecciona el *mes* ($anio):", kbMeses($anio));
    }
    if (strpos($cb_data, 'mmes_') === 0) {
        $parts = explode('_', $cb_data);
        $estado["anio"] = $parts[1] ?? date("Y");
        $estado["mes"]  = $parts[2] ?? date("m");
        $estado["paso"] = "manual_fecha_dia_input"; saveState($chat_id,$estado);
        $anio=(int)$estado["anio"]; $mes=(int)$estado["mes"];
        $maxDias = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
        sendMessage($chat_id, "✍️ Escribe el *día* del mes (1–$maxDias):");
    }

    // Vehículo
    if (strpos($cb_data, 'manual_vehiculo_sel_') === 0) {
        $idVeh = (int)substr($cb_data, strlen('manual_vehiculo_sel_'));
        $conn = db(); $v = obtenerVehiculoAdminPorId($conn, $idVeh, $chat_id); $conn?->close();
        if (!$v) sendMessage($chat_id, "⚠️ Vehículo no encontrado. Vuelve a intentarlo.");
        else {
            $estado['manual_vehiculo'] = $v['vehiculo'];
            $estado['paso'] = 'manual_empresa_menu'; saveState($chat_id,$estado);

            $conn = db(); $empresas = $conn ? obtenerEmpresasAdmin($conn, $chat_id) : []; $conn?->close();
            if ($empresas) {
                $kb=["inline_keyboard"=>[]];
                foreach ($empresas as $e) $kb["inline_keyboard"][] = [[ "text"=>$e['nombre'], "callback_data"=>"manual_empresa_sel_".$e['id'] ]];
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nueva empresa", "callback_data"=>"manual_empresa_nuevo" ]];
                sendMessage($chat_id, "🏢 Selecciona la *empresa* o crea una nueva:", $kb);
            } else {
                $estado['paso']='manual_empresa_nuevo_texto'; saveState($chat_id,$estado);
                sendMessage($chat_id, "No tienes empresas guardadas.\n✍️ Escribe el *nombre de la empresa*:");
            }
        }
    }
    if ($cb_data === 'manual_vehiculo_nuevo') {
        $estado['paso'] = 'manual_vehiculo_nuevo_texto'; saveState($chat_id,$estado);
        sendMessage($chat_id, "✍️ Escribe el *tipo de vehículo*:");
    }

    // Empresa seleccionar / crear y guardar viaje
    if (strpos($cb_data, 'manual_empresa_sel_') === 0) {
        $idEmp = (int)substr($cb_data, strlen('manual_empresa_sel_'));
        $conn = db(); $e = obtenerEmpresaAdminPorId($conn, $idEmp, $chat_id); $conn?->close();
        if (!$e) sendMessage($chat_id, "⚠️ Empresa no encontrada. Vuelve a intentarlo.");
        else {
            $estado['manual_empresa'] = $e['nombre'];
            manual_insert_viaje_and_close($chat_id, $estado);
        }
    }
    if ($cb_data === 'manual_empresa_nuevo') {
        $estado['paso'] = 'manual_empresa_nuevo_texto'; saveState($chat_id,$estado);
        sendMessage($chat_id, "✍️ Escribe el *nombre de la empresa*:");
    }

    if ($cb_id) answerCallbackQuery($cb_id);
}

function manual_handle_text($chat_id, &$estado, $text, $photo) {
    if (($estado["flujo"] ?? "") !== "manual") return;

    switch ($estado["paso"]) {
        case "manual_nombre": // compat
        case "manual_nombre_nuevo":
            $nombre = trim($text);
            if ($nombre==="") { sendMessage($chat_id, "⚠️ El nombre no puede estar vacío. Escribe el *nombre* del nuevo conductor:"); break; }
            $conn = db(); if ($conn) { crearConductorAdmin($conn, $chat_id, $nombre); $conn->close(); }
            $estado["manual_nombre"] = $nombre;
            $estado["paso"] = "manual_ruta_menu"; saveState($chat_id,$estado);

            $conn = db(); $rutas = $conn ? obtenerRutasAdmin($conn, $chat_id) : []; $conn?->close();
            if ($rutas) {
                $kb=["inline_keyboard"=>[]];
                foreach ($rutas as $r) $kb["inline_keyboard"][] = [[ "text"=>$r['ruta'], "callback_data"=>"manual_ruta_sel_".$r['id'] ]];
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nueva ruta", "callback_data"=>"manual_ruta_nueva" ]];
                sendMessage($chat_id, "👤 Conductor guardado: *{$nombre}*\n\nSelecciona una *ruta* o crea una nueva:", $kb);
            } else {
                $estado['paso']='manual_ruta_nueva_texto'; saveState($chat_id,$estado);
                sendMessage($chat_id, "👤 Conductor guardado: *{$nombre}*\n\n✍️ Escribe la *ruta del viaje*:");
            }
            break;

        case "manual_ruta": // compat
        case "manual_ruta_nueva_texto":
            $rutaTxt = trim($text);
            if ($rutaTxt==="") { sendMessage($chat_id, "⚠️ La ruta no puede estar vacía. Escribe la *ruta del viaje*:"); break; }
            $conn = db(); if ($conn) { crearRutaAdmin($conn, $chat_id, $rutaTxt); $conn->close(); }
            $estado["manual_ruta"] = $rutaTxt;
            $estado["paso"] = "manual_fecha"; saveState($chat_id,$estado);
            sendMessage($chat_id, "🛣️ Ruta guardada: *{$rutaTxt}*\n\n📅 Selecciona la *fecha*:", kbFechaManual());
            break;

        case "manual_fecha_dia_input":
            $anio=(int)($estado["anio"] ?? date("Y")); $mes=(int)($estado["mes"] ?? date("m"));
            if (!preg_match('/^\d{1,2}$/', $text)) {
                $max=cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
                sendMessage($chat_id, "⚠️ Debe ser un número entre 1 y $max. Escribe el *día* del mes:"); break;
            }
            $dia=(int)$text; $max=cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
            if ($dia<1 || $dia>$max) { sendMessage($chat_id, "⚠️ El día debe estar entre 1 y $max. Inténtalo de nuevo:"); break; }
            $estado["manual_fecha"] = sprintf("%04d-%02d-%02d",$anio,$mes,$dia);

            $estado['paso'] = 'manual_vehiculo_menu'; saveState($chat_id,$estado);
            $conn = db(); $vehiculos = $conn ? obtenerVehiculosAdmin($conn, $chat_id) : []; $conn?->close();
            if ($vehiculos) {
                $kb=["inline_keyboard"=>[]];
                foreach ($vehiculos as $v) $kb["inline_keyboard"][] = [[ "text"=>$v['vehiculo'], "callback_data"=>"manual_vehiculo_sel_".$v['id'] ]];
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nuevo vehículo", "callback_data"=>"manual_vehiculo_nuevo" ]];
                sendMessage($chat_id, "🚐 Selecciona el *tipo de vehículo* o crea uno nuevo:", $kb);
            } else {
                $estado['paso']='manual_vehiculo_nuevo_texto'; saveState($chat_id,$estado);
                sendMessage($chat_id, "No tienes vehículos guardados.\n✍️ Escribe el *tipo de vehículo* (ej.: Toyota Hilux 4x4):");
            }
            break;

        case "manual_vehiculo_nuevo_texto":
            $vehTxt = trim($text);
            if ($vehTxt==="") { sendMessage($chat_id, "⚠️ El *tipo de vehículo* no puede estar vacío. Escríbelo nuevamente:"); break; }
            $conn = db(); if ($conn) { crearVehiculoAdmin($conn, $chat_id, $vehTxt); $conn->close(); }
            $estado["manual_vehiculo"] = $vehTxt;
            $estado['paso'] = 'manual_empresa_menu'; saveState($chat_id,$estado);

            $conn = db(); $emp = $conn ? obtenerEmpresasAdmin($conn, $chat_id) : []; $conn?->close();
            if ($emp) {
                $kb=["inline_keyboard"=>[]];
                foreach ($emp as $e) $kb["inline_keyboard"][] = [[ "text"=>$e['nombre'], "callback_data"=>"manual_empresa_sel_".$e['id'] ]];
                $kb["inline_keyboard"][] = [[ "text"=>"➕ Nueva empresa", "callback_data"=>"manual_empresa_nuevo" ]];
                sendMessage($chat_id, "🏢 Selecciona la *empresa* o crea una nueva:", $kb);
            } else {
                $estado['paso']='manual_empresa_nuevo_texto'; saveState($chat_id,$estado);
                sendMessage($chat_id, "No tienes empresas guardadas.\n✍️ Escribe el *nombre de la empresa*:");
            }
            break;

        case "manual_empresa_nuevo_texto":
            $empTxt = trim($text);
            if ($empTxt==="") { sendMessage($chat_id, "⚠️ El *nombre de la empresa* no puede estar vacío. Escríbelo nuevamente:"); break; }
            $conn = db(); if ($conn) { crearEmpresaAdmin($conn, $chat_id, $empTxt); $conn->close(); }
            $estado["manual_empresa"] = $empTxt;
            manual_insert_viaje_and_close($chat_id, $estado);
            break;

        default:
            sendMessage($chat_id, "❌ Usa */manual* para registrar un viaje manual. */cancel* para reiniciar.");
            clearState($chat_id);
            break;
    }
}

function manual_insert_viaje_and_close($chat_id, &$estado) {
    $conn = db();
    if (!$conn) { sendMessage($chat_id, "❌ Error de conexión a la base de datos."); clearState($chat_id); return; }
    $stmt = $conn->prepare("INSERT INTO viajes (nombre, ruta, fecha, cedula, tipo_vehiculo, empresa, imagen) VALUES (?, ?, ?, NULL, ?, ?, NULL)");
    $stmt->bind_param("sssss", $estado["manual_nombre"], $estado["manual_ruta"], $estado["manual_fecha"], $estado["manual_vehiculo"], $estado["manual_empresa"]);
    if ($stmt->execute()) {
        sendMessage($chat_id,
            "✅ Viaje (manual) registrado:\n👤 ".$estado["manual_nombre"].
            "\n🛣️ ".$estado["manual_ruta"].
            "\n📅 ".$estado["manual_fecha"].
            "\n🚐 ".$estado["manual_vehiculo"].
            "\n🏢 ".$estado["manual_empresa"].
            "\n\nAtajos rápidos: /agg /manual"
        );
    } else {
        sendMessage($chat_id, "❌ Error al guardar el viaje: " . $conn->error);
    }
    $stmt->close(); $conn->close();
    clearState($chat_id);
}
