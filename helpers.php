<?php
// flow_manual.php - COMPLETO Y CORREGIDO

function manual_entrypoint($chat_id, $estado) {
    // Limpiar estado anterior
    $estado = [
        'flujo' => 'manual',
        'step' => 'conductor',
        'chat_id' => $chat_id,
        'data' => []
    ];
    saveState($chat_id, $estado);
    sendMessage($chat_id, "✏️ Escribe la *primera letra* del nombre del conductor para filtrar:");
}

function manual_handle_callback($chat_id, $estado, $cb_data, $cb_id) {
    // ✅ ARREGLA EL "CARGANDO" - Respuesta inmediata al botón
    answerCallbackQuery($cb_id);
    
    // Paginación de conductores
    if (str_starts_with($cb_data, 'manual_cond_page_')) {
        $page = (int)str_replace('manual_cond_page_', '', $cb_data);
        $estado['data']['pagina_conductor'] = $page;
        saveState($chat_id, $estado);
        
        $letra = $estado['data']['letra_conductor'] ?? '';
        $conn = db();
        if ($conn) {
            $conductores = obtenerConductoresPorLetra($conn, $chat_id, $letra);
            $conn->close();
            
            $keyboard = manual_crearKeyboardConductores($conductores, $page, $letra);
            editMessageText($chat_id, null, $cb_id, "✅ Selecciona el conductor:", $keyboard);
        }
        return;
    }
    
    // Paginación de rutas
    if (str_starts_with($cb_data, 'manual_ruta_page_')) {
        $page = (int)str_replace('manual_ruta_page_', '', $cb_data);
        $estado['data']['pagina_ruta'] = $page;
        saveState($chat_id, $estado);
        
        $letra = $estado['data']['letra_ruta'] ?? '';
        $conn = db();
        if ($conn) {
            $rutas = obtenerRutasPorLetra($conn, $chat_id, $letra);
            $conn->close();
            
            $keyboard = manual_crearKeyboardRutas($rutas, $page, $letra);
            editMessageText($chat_id, null, $cb_id, "✅ Selecciona la ruta:", $keyboard);
        }
        return;
    }
    
    // Volver a filtrar conductores
    if ($cb_data === 'manual_volver_filtrar_cond') {
        $estado['step'] = 'conductor';
        unset($estado['data']['letra_conductor']);
        unset($estado['data']['pagina_conductor']);
        saveState($chat_id, $estado);
        sendMessage($chat_id, "✏️ Escribe la *primera letra* del nombre del conductor para filtrar:");
        return;
    }
    
    // Volver a filtrar rutas
    if ($cb_data === 'manual_volver_filtrar_ruta') {
        $estado['step'] = 'ruta';
        unset($estado['data']['letra_ruta']);
        unset($estado['data']['pagina_ruta']);
        saveState($chat_id, $estado);
        sendMessage($chat_id, "✏️ Escribe la *primera letra* de la RUTA para filtrar:");
        return;
    }
    
    // Volver a conductores (desde selección de ruta)
    if ($cb_data === 'manual_volver_conductores') {
        $estado['step'] = 'conductor';
        unset($estado['data']['conductor_id']);
        unset($estado['data']['conductor_nombre']);
        unset($estado['data']['letra_conductor']);
        unset($estado['data']['pagina_conductor']);
        saveState($chat_id, $estado);
        sendMessage($chat_id, "✏️ Escribe la *primera letra* del nombre del conductor para filtrar:");
        return;
    }
    
    // Selección de conductor
    if (str_starts_with($cb_data, 'manual_conductor_')) {
        $conductor_id = (int)str_replace('manual_conductor_', '', $cb_data);
        
        // Obtener nombre del conductor
        $conn = db();
        $conductor_nombre = '';
        if ($conn) {
            $result = $conn->query("SELECT nombre FROM conductores_admin WHERE id = $conductor_id AND owner_chat_id = $chat_id LIMIT 1");
            if ($result && $result->num_rows) {
                $conductor_nombre = $result->fetch_assoc()['nombre'];
            }
            $conn->close();
        }
        
        $estado['step'] = 'ruta';
        $estado['data']['conductor_id'] = $conductor_id;
        $estado['data']['conductor_nombre'] = $conductor_nombre;
        saveState($chat_id, $estado);
        sendMessage($chat_id, "✏️ Escribe la *primera letra* de la RUTA para filtrar:");
        return;
    }
    
    // Selección de ruta
    if (str_starts_with($cb_data, 'manual_ruta_')) {
        $ruta_id = (int)str_replace('manual_ruta_', '', $cb_data);
        $estado['step'] = 'fecha';
        $estado['data']['ruta_id'] = $ruta_id;
        saveState($chat_id, $estado);
        sendMessage($chat_id, "📅 Selecciona la fecha del viaje:", kbFechaManual());
        return;
    }
    
    // Fecha hoy
    if ($cb_data === 'mfecha_hoy') {
        $estado['step'] = 'precio';
        $estado['data']['fecha'] = date('Y-m-d');
        saveState($chat_id, $estado);
        sendMessage($chat_id, "💰 Ingresa el *valor del viaje* (solo números, ej: 25000):");
        return;
    }
    
    // Fecha manual (otra fecha)
    if ($cb_data === 'mfecha_otro') {
        $estado['step'] = 'fecha_manual_espera';
        saveState($chat_id, $estado);
        sendMessage($chat_id, "✏️ Escribe la fecha en formato *YYYY-MM-DD* (ej: 2026-01-15):");
        return;
    }
}

function manual_handle_text($chat_id, $estado, $text, $photo) {
    $step = $estado['step'] ?? null;
    
    // Paso 1: Esperando letra del conductor
    if ($step === 'conductor') {
        $letra = trim(substr($text, 0, 1));
        
        if (!preg_match('/[a-zA-Z]/', $letra)) {
            sendMessage($chat_id, "❌ Escribe una *letra válida* (A-Z) para filtrar conductores:");
            return;
        }
        
        $letra = strtoupper($letra);
        
        $conn = db();
        if (!$conn) {
            sendMessage($chat_id, "❌ Error de conexión. Intenta de nuevo.");
            return;
        }
        
        $conductores = obtenerConductoresPorLetra($conn, $chat_id, $letra);
        $conn->close();
        
        if (empty($conductores)) {
            sendMessage($chat_id, "⚠️ No hay conductores que empiecen con '$letra'. Prueba otra letra:");
            return;
        }
        
        $estado['step'] = 'conductor_seleccion';
        $estado['data']['letra_conductor'] = $letra;
        $estado['data']['pagina_conductor'] = 0;
        saveState($chat_id, $estado);
        
        $keyboard = manual_crearKeyboardConductores($conductores, 0, $letra);
        sendMessage($chat_id, "✅ Selecciona el conductor (mostrando conductores que empiezan con '$letra'):", $keyboard);
        return;
    }
    
    // Paso 2: Esperando letra de la ruta
    if ($step === 'ruta') {
        $letra = trim(substr($text, 0, 1));
        
        if (!preg_match('/[a-zA-Z]/', $letra)) {
            sendMessage($chat_id, "❌ Escribe una *letra válida* (A-Z) para filtrar rutas:");
            return;
        }
        
        $letra = strtoupper($letra);
        
        $conn = db();
        if (!$conn) {
            sendMessage($chat_id, "❌ Error de conexión. Intenta de nuevo.");
            return;
        }
        
        $rutas = obtenerRutasPorLetra($conn, $chat_id, $letra);
        $conn->close();
        
        if (empty($rutas)) {
            sendMessage($chat_id, "⚠️ No hay rutas que empiecen con '$letra'. Prueba otra letra o usa /prestamos para agregar rutas:");
            return;
        }
        
        $estado['step'] = 'ruta_seleccion';
        $estado['data']['letra_ruta'] = $letra;
        $estado['data']['pagina_ruta'] = 0;
        saveState($chat_id, $estado);
        
        $keyboard = manual_crearKeyboardRutas($rutas, 0, $letra);
        sendMessage($chat_id, "✅ Selecciona la ruta (mostrando rutas que empiezan con '$letra'):", $keyboard);
        return;
    }
    
    // Paso 3: Esperando fecha manual
    if ($step === 'fecha_manual_espera') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            sendMessage($chat_id, "❌ Formato inválido. Usa *YYYY-MM-DD* (ej: 2026-01-15):");
            return;
        }
        
        $estado['step'] = 'precio';
        $estado['data']['fecha'] = $text;
        saveState($chat_id, $estado);
        sendMessage($chat_id, "💰 Ingresa el *valor del viaje* (solo números, ej: 25000):");
        return;
    }
    
    // Paso 4: Esperando precio
    if ($step === 'precio') {
        $precio = (int)preg_replace('/[^0-9]/', '', $text);
        
        if ($precio <= 0) {
            sendMessage($chat_id, "❌ Ingresa un *número válido* (ej: 25000):");
            return;
        }
        
        $conn = db();
        if (!$conn) {
            sendMessage($chat_id, "❌ Error de conexión. Intenta de nuevo.");
            clearState($chat_id);
            return;
        }
        
        $conductor_id = $estado['data']['conductor_id'];
        $ruta_id = $estado['data']['ruta_id'];
        $fecha = $estado['data']['fecha'];
        
        // Obtener nombres para el mensaje de confirmación
        $conductor_nombre = '';
        $ruta_nombre = '';
        
        $result = $conn->query("SELECT nombre FROM conductores_admin WHERE id = $conductor_id LIMIT 1");
        if ($result && $result->num_rows) {
            $conductor_nombre = $result->fetch_assoc()['nombre'];
        }
        
        $result = $conn->query("SELECT ruta FROM rutas_admin WHERE id = $ruta_id LIMIT 1");
        if ($result && $result->num_rows) {
            $ruta_nombre = $result->fetch_assoc()['ruta'];
        }
        
        $stmt = $conn->prepare("INSERT INTO viajes (conductor_id, ruta_id, fecha, valor, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iisd", $conductor_id, $ruta_id, $fecha, $precio);
        
        if ($stmt->execute()) {
            $mensaje = "✅ ¡Viaje registrado exitosamente!\n\n";
            $mensaje .= "👤 Conductor: $conductor_nombre\n";
            $mensaje .= "🛣️ Ruta: $ruta_nombre\n";
            $mensaje .= "📅 Fecha: $fecha\n";
            $mensaje .= "💰 Valor: $" . number_format($precio, 0, ',', '.');
            sendMessage($chat_id, $mensaje);
        } else {
            sendMessage($chat_id, "❌ Error al guardar: " . $conn->error);
        }
        
        $stmt->close();
        $conn->close();
        
        clearState($chat_id);
        
        // Ofrecer continuar
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '➕ Registrar otro viaje', 'callback_data' => 'cmd_manual']],
                [['text' => '📊 Ver reportes', 'callback_data' => 'cmd_p']]
            ]
        ];
        sendMessage($chat_id, "¿Qué deseas hacer ahora?", $keyboard);
        return;
    }
    
    // Si no está en ningún paso esperado
    sendMessage($chat_id, "❌ No te entiendo. Usa /cancel para reiniciar o selecciona una opción del menú.");
}

// Función para crear keyboard de conductores con paginación
function manual_crearKeyboardConductores($conductores, $page, $letra) {
    $perPage = 8;
    $total = count($conductores);
    $start = $page * $perPage;
    $conductoresPage = array_slice($conductores, $start, $perPage);
    
    $keyboard = ['inline_keyboard' => []];
    
    foreach ($conductoresPage as $c) {
        $nombre = htmlspecialchars(substr($c['nombre'], 0, 40));
        $keyboard['inline_keyboard'][] = [
            ['text' => $nombre, 'callback_data' => "manual_conductor_{$c['id']}"]
        ];
    }
    
    // Navegación
    $nav = [];
    if ($page > 0) {
        $nav[] = ['text' => '◀️ Anterior', 'callback_data' => "manual_cond_page_" . ($page - 1)];
    }
    if (($page + 1) * $perPage < $total) {
        $nav[] = ['text' => 'Siguiente ▶️', 'callback_data' => "manual_cond_page_" . ($page + 1)];
    }
    if (!empty($nav)) {
        $keyboard['inline_keyboard'][] = $nav;
    }
    
    // Botones auxiliares
    $keyboard['inline_keyboard'][] = [
        ['text' => '🔄 Volver a filtrar', 'callback_data' => 'manual_volver_filtrar_cond']
    ];
    
    return $keyboard;
}

// Función para crear keyboard de rutas con paginación
function manual_crearKeyboardRutas($rutas, $page, $letra) {
    $perPage = 6;
    $total = count($rutas);
    $start = $page * $perPage;
    $rutasPage = array_slice($rutas, $start, $perPage);
    
    $keyboard = ['inline_keyboard' => []];
    
    foreach ($rutasPage as $r) {
        $nombre = htmlspecialchars(substr($r['ruta'], 0, 50));
        $keyboard['inline_keyboard'][] = [
            ['text' => $nombre, 'callback_data' => "manual_ruta_{$r['id']}"]
        ];
    }
    
    // Navegación
    $nav = [];
    if ($page > 0) {
        $nav[] = ['text' => '◀️ Anterior', 'callback_data' => "manual_ruta_page_" . ($page - 1)];
    }
    if (($page + 1) * $perPage < $total) {
        $nav[] = ['text' => 'Siguiente ▶️', 'callback_data' => "manual_ruta_page_" . ($page + 1)];
    }
    if (!empty($nav)) {
        $keyboard['inline_keyboard'][] = $nav;
    }
    
    // Botones auxiliares
    $keyboard['inline_keyboard'][] = [
        ['text' => '🔄 Volver a filtrar', 'callback_data' => 'manual_volver_filtrar_ruta'],
        ['text' => '🔙 Volver a conductores', 'callback_data' => 'manual_volver_conductores']
    ];
    
    return $keyboard;
}

// Función auxiliar para editar mensajes
function editMessageText($chat_id, $message_id, $callback_query_id, $text, $keyboard = null) {
    global $APIURL;
    
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];
    
    if ($message_id) {
        $data['message_id'] = $message_id;
    }
    
    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }
    
    @file_get_contents($APIURL . "editMessageText?" . http_build_query($data));
}