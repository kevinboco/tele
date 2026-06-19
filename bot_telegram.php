<?php
/**
 * Bot de Telegram para Registro de Viajes
 * Versión: 1.5 - Extracción mejorada con feedback detallado
 */

// ============================================================
// 1. CONFIGURACIÓN
// ============================================================

// Configuración de la Base de Datos
define('DB_HOST', 'mysql.hostinger.com');
define('DB_USER', 'u648222299_keboco5');
define('DB_PASS', 'Bucaramanga3011');
define('DB_NAME', 'u648222299_viajes');

// Token del Bot de Telegram
define('BOT_TOKEN', '8714260096:AAFEKzX-OYXh9NO4bo_jAiNfdaod3iM5TEs');

// Directorio para guardar imágenes (RAÍZ /uploads)
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// Crear directorio si no existe
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// ============================================================
// 2. FUNCIONES DE TELEGRAM
// ============================================================

/**
 * Envía un mensaje a Telegram
 */
function sendTelegramMessage($chat_id, $text, $keyboard = null, $parse_mode = 'HTML') {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $parse_mode
    ];
    
    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }
    
    return callTelegramAPI($url, $data);
}

/**
 * Envía un mensaje con teclado inline
 */
function sendTelegramInlineKeyboard($chat_id, $text, $inline_keyboard, $parse_mode = 'HTML') {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $parse_mode,
        'reply_markup' => json_encode(['inline_keyboard' => $inline_keyboard])
    ];
    
    return callTelegramAPI($url, $data);
}

/**
 * Responde a una callback query
 */
function answerCallbackQuery($callback_query_id, $text = null, $show_alert = false) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/answerCallbackQuery";
    
    $data = [
        'callback_query_id' => $callback_query_id,
        'show_alert' => $show_alert
    ];
    
    if ($text) {
        $data['text'] = $text;
    }
    
    return callTelegramAPI($url, $data);
}

/**
 * Función genérica para llamar a la API de Telegram
 */
function callTelegramAPI($url, $data) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code != 200) {
        error_log("Error en API Telegram: HTTP $http_code - $response");
    }
    
    return json_decode($response, true);
}

// ============================================================
// 3. BASE DE DATOS
// ============================================================

/**
 * Obtiene conexión a la base de datos
 */
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        error_log("Error de conexión: " . $conn->connect_error);
        throw new Exception("Error de conexión a la base de datos");
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

/**
 * Extrae TODOS los datos posibles del mensaje - VERSIÓN MEJORADA CON FEEDBACK
 */
function extraerDatosDelMensaje($texto) {
    $texto_original = $texto;
    $texto = trim($texto);
    
    $resultado = [
        'ruta' => null,
        'conductor' => null,
        'tipo_vehiculo' => null,
        'origen' => null,
        'destino' => null,
        'empresa' => null,
        'paciente' => null,
        'auxiliar' => null,
        'acompanante' => null,
        'hora_salida' => null,
        // Para feedback
        'encontrado_conductor' => false,
        'encontrado_ruta' => false,
        'encontrado_origen' => false,
        'encontrado_destino' => false,
        'mensaje_feedback' => []
    ];
    
    // ============================================================
    // 1. EXTRAER CONDUCTOR - NUEVA LÓGICA MEJORADA
    // ============================================================
    
    // Patrones para buscar "conductor" en cualquier variante
    $patrones_conductor = [
        '/\bconductor\b/i',           // conductor exacto
        '/\bconductr\b/i',            // falta 'o'
        '/\bconductór\b/i',           // con tilde
        '/\bconduktor\b/i',           // error común
        '/\bconductorr\b/i',          // doble r
        '/\bconductores\b/i',         // plural
        '/\bconduc\b/i',              // abreviado
        '/\bcondcutor\b/i',           // error común
        '/\bconductor\s*:/i',         // con dos puntos
        '/\bconductor\s*-\s*/i',      // con guión
    ];
    
    $nombre_conductor = null;
    $posicion_conductor = -1;
    $palabra_encontrada = '';
    
    // Buscar cualquier variante de "conductor"
    foreach ($patrones_conductor as $patron) {
        if (preg_match($patron, $texto, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1];
            $palabra = $matches[0][0];
            
            // Si encontramos una coincidencia más cercana al inicio, la preferimos
            if ($posicion_conductor === -1 || $pos < $posicion_conductor) {
                $posicion_conductor = $pos;
                $palabra_encontrada = $palabra;
            }
        }
    }
    
    // Si encontramos "conductor", extraemos todo lo que está a la derecha
    if ($posicion_conductor !== -1) {
        // Tomamos todo el texto desde la posición de "conductor" + longitud de la palabra
        $inicio_nombre = $posicion_conductor + strlen($palabra_encontrada);
        $nombre_completo = trim(substr($texto, $inicio_nombre));
        
        // Limpiar el nombre: eliminar palabras clave que puedan aparecer después
        $palabras_clave = [
            '/\bdesde\b/i', '/\ba\b/i', '/\bhasta\b/i', 
            '/\bhospital\b/i', '/\bicbf\b/i', '/\bsunny\b/i',
            '/\bacpm\b/i', '/\bcava\b/i', '/\bp\.campaña\b/i',
            '/\bp\.nazareth\b/i', '/\bp\.siapana\b/i',
            '/\bburbuja\b/i', '/\bcamión\s+350\b/i', '/\bcamión\s+750\b/i',
            '/\bcarrotanque\b/i', '/\bvolqueta\b/i', '/\bcamioneta\b/i',
            '/\bcopetrana\b/i', '/\bpara\b/i', '/\ben\b/i',
            '/\bdestino\b/i', '/\borigen\b/i', '/\bhora\b/i',
            '/\bsalida\b/i', '/\bretorno\b/i', '/\bauxiliar\b/i',
            '/\bacompañante\b/i', '/\bpaciente\b/i'
        ];
        
        // Eliminar palabras clave del nombre
        foreach ($palabras_clave as $patron) {
            $nombre_completo = preg_replace($patron, '', $nombre_completo);
        }
        
        // Limpiar espacios múltiples y trim
        $nombre_completo = preg_replace('/\s+/', ' ', $nombre_completo);
        $nombre_completo = trim($nombre_completo);
        
        // Capitalizar correctamente (primer letra de cada palabra en mayúscula)
        if (!empty($nombre_completo)) {
            $nombre_completo = ucwords(strtolower($nombre_completo));
            $resultado['conductor'] = $nombre_completo;
            $resultado['encontrado_conductor'] = true;
            $resultado['mensaje_feedback'][] = "✅ Conductor: <b>" . htmlspecialchars($nombre_completo) . "</b>";
        }
    } else {
        $resultado['mensaje_feedback'][] = "❌ Conductor: <b>No encontrado</b> (busca 'conductor' en el texto)";
    }
    
    // ============================================================
    // 2. EXTRAER AUXILIAR
    // ============================================================
    if (preg_match('/auxiliar\s+([\w\sáéíóúñ.]+?)(?:\s+conductor|\s+desde|\s+a|\s+para|\s*$)/i', $texto, $matches)) {
        $resultado['auxiliar'] = ucwords(strtolower(trim($matches[1])));
        $resultado['mensaje_feedback'][] = "👤 Auxiliar: <b>" . htmlspecialchars($resultado['auxiliar']) . "</b>";
    }
    
    // ============================================================
    // 3. EXTRAER ACOMPAÑANTE
    // ============================================================
    if (preg_match('/acompañante\s+([\w\sáéíóúñ.]+?)(?:\s+auxiliar|\s+conductor|\s+desde|\s*$)/i', $texto, $matches)) {
        $resultado['acompanante'] = ucwords(strtolower(trim($matches[1])));
        $resultado['mensaje_feedback'][] = "👤 Acompañante: <b>" . htmlspecialchars($resultado['acompanante']) . "</b>";
    }
    
    // ============================================================
    // 4. EXTRAER PACIENTE
    // ============================================================
    if (preg_match('/(?:paciente|de\s+paciente)\s+([\w\sáéíóúñ.]+?)(?:\s+desde|\s+a|\s+para|\s+conductor|\s+auxiliar|\s*$)/i', $texto, $matches)) {
        $resultado['paciente'] = ucwords(strtolower(trim($matches[1])));
        $resultado['mensaje_feedback'][] = "👤 Paciente: <b>" . htmlspecialchars($resultado['paciente']) . "</b>";
    }
    
    // ============================================================
    // 5. EXTRAER HORA DE SALIDA
    // ============================================================
    if (preg_match('/hora\s*(?:de\s*)?salida\s*[:]\s*([\d:]+)/i', $texto, $matches)) {
        $resultado['hora_salida'] = trim($matches[1]);
        $resultado['mensaje_feedback'][] = "🕐 Hora salida: <b>" . htmlspecialchars($resultado['hora_salida']) . "</b>";
    }
    
    // ============================================================
    // 6. EXTRAER RUTA (origen - destino) - MEJORADO
    // ============================================================
    
    // Primero, eliminar la parte del conductor para extraer ruta
    $texto_para_ruta = $texto;
    if ($resultado['conductor']) {
        foreach ($patrones_conductor as $patron) {
            if (preg_match($patron, $texto_para_ruta, $matches, PREG_OFFSET_CAPTURE)) {
                $pos = $matches[0][1];
                $palabra = $matches[0][0];
                $texto_para_ruta = substr($texto_para_ruta, 0, $pos);
                break;
            }
        }
    }
    
    // Intentar múltiples patrones para extraer ruta
    
    // Patrón 1: "desde [origen] a [destino]"
    if (preg_match('/desde\s+([\w\sáéíóúñ]+?)\s+a\s+([\w\sáéíóúñ]+?)(?:\s+conductor|\s+para|\s+retorno|\s*$)/i', $texto_para_ruta, $matches)) {
        $resultado['origen'] = ucwords(strtolower(trim($matches[1])));
        $resultado['destino'] = ucwords(strtolower(trim($matches[2])));
        $resultado['ruta'] = $resultado['origen'] . ' - ' . $resultado['destino'];
        $resultado['encontrado_ruta'] = true;
        $resultado['encontrado_origen'] = true;
        $resultado['encontrado_destino'] = true;
        $resultado['mensaje_feedback'][] = "🗺️ Ruta: <b>" . htmlspecialchars($resultado['ruta']) . "</b>";
    } 
    // Patrón 2: "[origen] - [destino]" con guión
    elseif (preg_match('/([\w\s]+?)\s*[-–]\s*([\w\s]+?)(?:\s+conductor|\s+retorno|\s*$)/i', $texto_para_ruta, $matches)) {
        $resultado['origen'] = ucwords(strtolower(trim($matches[1])));
        $resultado['destino'] = ucwords(strtolower(trim($matches[2])));
        $resultado['ruta'] = $resultado['origen'] . ' - ' . $resultado['destino'];
        $resultado['encontrado_ruta'] = true;
        $resultado['encontrado_origen'] = true;
        $resultado['encontrado_destino'] = true;
        $resultado['mensaje_feedback'][] = "🗺️ Ruta: <b>" . htmlspecialchars($resultado['ruta']) . "</b>";
    }
    // Patrón 3: "[origen] a [destino]" sin "desde"
    elseif (preg_match('/([\w\s]+?)\s+(?:a|para)\s+([\w\s]+?)(?:\s+conductor|\s+retorno|\s*$)/i', $texto_para_ruta, $matches)) {
        $resultado['origen'] = ucwords(strtolower(trim($matches[1])));
        $resultado['destino'] = ucwords(strtolower(trim($matches[2])));
        $resultado['ruta'] = $resultado['origen'] . ' - ' . $resultado['destino'];
        $resultado['encontrado_ruta'] = true;
        $resultado['encontrado_origen'] = true;
        $resultado['encontrado_destino'] = true;
        $resultado['mensaje_feedback'][] = "🗺️ Ruta: <b>" . htmlspecialchars($resultado['ruta']) . "</b>";
    }
    // Patrón 4: Buscar "destino" específicamente
    elseif (preg_match('/destino\s+([\w\sáéíóúñ]+?)(?:\s+conductor|\s*$)/i', $texto, $matches)) {
        $resultado['destino'] = ucwords(strtolower(trim($matches[1])));
        $resultado['encontrado_destino'] = true;
        $resultado['mensaje_feedback'][] = "📍 Destino: <b>" . htmlspecialchars($resultado['destino']) . "</b>";
    }
    // Patrón 5: Buscar "origen" específicamente
    elseif (preg_match('/origen\s+([\w\sáéíóúñ]+?)(?:\s+destino|\s*$)/i', $texto, $matches)) {
        $resultado['origen'] = ucwords(strtolower(trim($matches[1])));
        $resultado['encontrado_origen'] = true;
        $resultado['mensaje_feedback'][] = "📍 Origen: <b>" . htmlspecialchars($resultado['origen']) . "</b>";
    }
    
    // Si no se encontró ruta completa pero hay origen o destino parcial
    if (!$resultado['encontrado_ruta']) {
        if ($resultado['origen'] && $resultado['destino']) {
            $resultado['ruta'] = $resultado['origen'] . ' - ' . $resultado['destino'];
            $resultado['encontrado_ruta'] = true;
        } elseif ($resultado['origen']) {
            $resultado['mensaje_feedback'][] = "⚠️ Solo se encontró <b>origen</b>: " . htmlspecialchars($resultado['origen']);
        } elseif ($resultado['destino']) {
            $resultado['mensaje_feedback'][] = "⚠️ Solo se encontró <b>destino</b>: " . htmlspecialchars($resultado['destino']);
        } else {
            $resultado['mensaje_feedback'][] = "❌ Ruta: <b>No encontrada</b> (busca 'desde ... a ...' o 'origen - destino')";
        }
    }
    
    // ============================================================
    // 7. EXTRAER EMPRESA
    // ============================================================
    $empresas = ['hospital', 'icbf', 'sunny app', 'acpm', 'cava', 'p.campaña', 'p.nazareth', 'p.siapana'];
    foreach ($empresas as $emp) {
        if (preg_match('/\b' . preg_quote($emp, '/') . '\b/i', $texto)) {
            $resultado['empresa'] = ucwords($emp);
            $resultado['mensaje_feedback'][] = "🏢 Empresa: <b>" . htmlspecialchars($resultado['empresa']) . "</b>";
            break;
        }
    }
    if (!$resultado['empresa']) {
        $resultado['empresa'] = 'Hospital';
        $resultado['mensaje_feedback'][] = "🏢 Empresa: <b>Hospital</b> (por defecto)";
    }
    
    // ============================================================
    // 8. EXTRAER TIPO DE VEHÍCULO
    // ============================================================
    $vehiculos = ['burbuja', 'camión 350', 'camión 750', 'carrotanque', 'volqueta', 'camioneta', 'copetrana'];
    foreach ($vehiculos as $veh) {
        if (preg_match('/\b' . preg_quote($veh, '/') . '\b/i', $texto)) {
            $resultado['tipo_vehiculo'] = ucwords($veh);
            $resultado['mensaje_feedback'][] = "🚙 Vehículo: <b>" . htmlspecialchars($resultado['tipo_vehiculo']) . "</b>";
            break;
        }
    }
    if (!$resultado['tipo_vehiculo']) {
        $resultado['tipo_vehiculo'] = 'Burbuja';
        $resultado['mensaje_feedback'][] = "🚙 Vehículo: <b>Burbuja</b> (por defecto)";
    }
    
    return $resultado;
}

/**
 * Guarda el viaje en la base de datos
 */
function guardarViaje($data) {
    $conn = getDBConnection();
    
    $nombre = $data['conductor'] ?? null;
    $cedula = $data['cedula'] ?? null;
    $fecha = $data['fecha'] ?? date('Y-m-d');
    $imagen = $data['imagen'] ?? null;
    $epicrisis = $data['epicrisis'] ?? null;
    $whatsapp = $data['whatsapp'] ?? null;
    $ruta = $data['ruta'] ?? null;
    $tipo_vehiculo = $data['tipo_vehiculo'] ?? 'Burbuja';
    $empresa = $data['empresa'] ?? 'Hospital';
    $pago_parcial = isset($data['pago_parcial']) ? $data['pago_parcial'] : null;
    $pagado = isset($data['pagado']) ? $data['pagado'] : 0;
    
    $sql = "INSERT INTO viajes 
            (nombre, cedula, fecha, imagen, epicrisis, whatsapp, ruta, tipo_vehiculo, empresa, pago_parcial, pagado) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssssssii",
        $nombre,
        $cedula,
        $fecha,
        $imagen,
        $epicrisis,
        $whatsapp,
        $ruta,
        $tipo_vehiculo,
        $empresa,
        $pago_parcial,
        $pagado
    );
    
    if ($stmt->execute()) {
        $id = $stmt->insert_id;
        $stmt->close();
        $conn->close();
        return $id;
    }
    
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    throw new Exception("Error al guardar viaje: " . $error);
}

/**
 * Obtiene conductores existentes
 */
function obtenerConductores() {
    $conn = getDBConnection();
    $sql = "SELECT DISTINCT nombre FROM viajes WHERE nombre IS NOT NULL AND nombre != '' ORDER BY nombre LIMIT 50";
    $result = $conn->query($sql);
    
    $conductores = [];
    while ($row = $result->fetch_assoc()) {
        $conductores[] = $row['nombre'];
    }
    $conn->close();
    return $conductores;
}

/**
 * Obtiene rutas existentes
 */
function obtenerRutas() {
    $conn = getDBConnection();
    $sql = "SELECT DISTINCT ruta FROM viajes WHERE ruta IS NOT NULL AND ruta != '' ORDER BY ruta LIMIT 50";
    $result = $conn->query($sql);
    
    $rutas = [];
    while ($row = $result->fetch_assoc()) {
        $rutas[] = $row['ruta'];
    }
    $conn->close();
    return $rutas;
}

// ============================================================
// 4. MANEJADOR DEL BOT
// ============================================================

/**
 * Clase principal del Bot
 */
class BotHandler {
    private $chat_id;
    private $user_data = [];
    private $data_file;
    
    public function __construct($chat_id) {
        $this->chat_id = $chat_id;
        $this->data_file = sys_get_temp_dir() . '/bot_viajes_' . $chat_id . '.json';
        $this->loadUserData();
    }
    
    private function loadUserData() {
        if (file_exists($this->data_file)) {
            $this->user_data = json_decode(file_get_contents($this->data_file), true);
            if (!is_array($this->user_data)) {
                $this->user_data = ['step' => 'idle'];
            }
        } else {
            $this->user_data = ['step' => 'idle'];
        }
    }
    
    private function saveUserData() {
        file_put_contents($this->data_file, json_encode($this->user_data));
    }
    
    private function clearUserData() {
        if (file_exists($this->data_file)) {
            unlink($this->data_file);
        }
        $this->user_data = ['step' => 'idle'];
    }
    
    /**
     * Maneja mensajes de texto
     */
    public function handleMessage($message) {
        $text = isset($message['text']) ? trim($message['text']) : '';
        $chat_id = $this->chat_id;
        
        // Comandos
        if ($text === '/start') {
            return $this->sendWelcome();
        }
        
        if ($text === '/cancel' || $text === '/cancelar') {
            $this->clearUserData();
            return sendTelegramMessage($chat_id, "✅ Operación cancelada. Envía /start para comenzar de nuevo.");
        }
        
        if ($text === '/help' || $text === '/ayuda') {
            return $this->sendHelp();
        }
        
        if ($text === '/conductores') {
            return $this->listarConductores();
        }
        
        if ($text === '/rutas') {
            return $this->listarRutas();
        }
        
        // Si el usuario está en proceso de confirmación
        if (isset($this->user_data['step']) && $this->user_data['step'] === 'confirming') {
            if (strpos($text, 'sí') !== false || strpos($text, 'si') !== false || $text === '✅' || $text === '✅ Guardar') {
                return $this->guardarViajeFinal();
            } elseif (strpos($text, 'no') !== false || $text === '❌' || $text === '❌ Cancelar') {
                $this->clearUserData();
                return sendTelegramMessage($chat_id, "❌ Viaje cancelado. Envía /start para comenzar de nuevo.");
            }
            return $this->sendConfirmationKeyboard();
        }
        
        // Procesar mensaje de texto normal
        return $this->procesarTexto($text);
    }
    
    /**
     * Maneja el envío de fotos
     */
    public function handlePhoto($photo) {
        $chat_id = $this->chat_id;
        
        if (!isset($this->user_data['step']) || $this->user_data['step'] !== 'awaiting_image') {
            return sendTelegramMessage($chat_id, 
                "❌ Primero envía la información del viaje.\n\n" .
                "Ejemplo:\n" .
                "Traslado de paciente desde Paraíso a Nazareth conductor Franco Pimienta\n\n" .
                "O escribe /start para comenzar."
            );
        }
        
        try {
            // Obtener la foto de mejor calidad (última de la lista)
            $file_id = $photo[count($photo) - 1]['file_id'];
            $file_path = $this->downloadImage($file_id);
            
            if (!$file_path) {
                throw new Exception("No se pudo descargar la imagen");
            }
            
            $this->user_data['imagen'] = $file_path;
            $this->user_data['step'] = 'confirming';
            $this->saveUserData();
            
            return $this->sendConfirmationKeyboard();
            
        } catch (Exception $e) {
            error_log("Error al procesar imagen: " . $e->getMessage());
            return sendTelegramMessage($chat_id, "❌ Error al procesar la imagen. Intenta nuevamente.");
        }
    }
    
    /**
     * Maneja respuestas de callback_query (botones inline)
     */
    public function handleCallbackQuery($callback_query) {
        $data = $callback_query['data'];
        $callback_id = $callback_query['id'];
        $chat_id = $this->chat_id;
        
        if ($data === 'confirm_yes') {
            answerCallbackQuery($callback_id, "✅ Guardando viaje...");
            return $this->guardarViajeFinal();
        } elseif ($data === 'confirm_no') {
            answerCallbackQuery($callback_id, "❌ Viaje cancelado");
            $this->clearUserData();
            return sendTelegramMessage($chat_id, "❌ Viaje cancelado. Envía /start para comenzar de nuevo.");
        }
        
        return null;
    }
    
    /**
     * Procesa el texto para extraer datos - CON FEEDBACK DETALLADO
     */
    private function procesarTexto($texto) {
        $chat_id = $this->chat_id;
        
        // Si el usuario presionó "Nuevo viaje"
        if (strpos($texto, '📝 Nuevo viaje') !== false || strpos($texto, 'nuevo viaje') !== false) {
            $this->clearUserData();
            return sendTelegramMessage($chat_id, 
                "📝 Envía la información del viaje.\n\n" .
                "El bot extraerá automáticamente:\n" .
                "• Conductor (busca la palabra 'conductor')\n" .
                "• Ruta (busca 'desde ... a ...' o 'origen - destino')\n" .
                "• Paciente, auxiliar, acompañante\n" .
                "• Hora de salida\n" .
                "• Empresa y tipo de vehículo\n\n" .
                "📌 <b>Ejemplos:</b>\n" .
                "• Traslado de paciente desde Paraíso a Nazareth conductor Mario Bocanegra\n" .
                "• Conductor Jorge Palmar desde Maicao a Nazareth\n" .
                "• Acompañante icela Hernández, Auxiliar María Antonia Velázquez, Conductor David Ricardo pimienta, Destino hospital San José de Maicao\n\n" .
                "💡 El bot te mostrará <b>qué datos encontró</b> y qué no."
            );
        }
        
        // Extraer datos (con feedback)
        $datos = extraerDatosDelMensaje($texto);
        
        // ============================================================
        // CONSTRUIR MENSAJE DE FEEDBACK DETALLADO
        // ============================================================
        
        $feedback = "📊 <b>ANÁLISIS DEL MENSAJE</b>\n\n";
        $feedback .= "🔍 <b>Datos encontrados:</b>\n";
        $feedback .= implode("\n", $datos['mensaje_feedback']);
        
        // Determinar si hay datos suficientes para guardar
        $tiene_conductor = $datos['encontrado_conductor'];
        $tiene_ruta = $datos['encontrado_ruta'];
        $tiene_origen = $datos['encontrado_origen'];
        $tiene_destino = $datos['encontrado_destino'];
        
        // Si NO tiene conductor, es un error crítico
        if (!$tiene_conductor) {
            $feedback .= "\n\n⚠️ <b>No se encontró el CONDUCTOR</b>\n";
            $feedback .= "Debes incluir la palabra 'conductor' seguida del nombre.\n";
            $feedback .= "Ejemplo: <code>... conductor Mario Bocanegra</code>\n\n";
            $feedback .= "📝 <b>Consejos:</b>\n";
            $feedback .= "• El bot reconoce: conductor, CONDUCTOR, Conductor, conductr, conductór\n";
            $feedback .= "• El nombre puede tener apellidos compuestos (ej: 'Mario José Bocanegra')\n";
            
            return sendTelegramMessage($chat_id, $feedback);
        }
        
        // Si tiene conductor pero no ruta completa, mostrar advertencia pero permitir continuar
        $advertencia = "";
        if (!$tiene_ruta) {
            $advertencia .= "\n⚠️ <b>No se encontró una RUTA completa</b>\n";
            if ($tiene_origen && !$tiene_destino) {
                $advertencia .= "Solo se encontró el origen: " . htmlspecialchars($datos['origen']) . "\n";
                $advertencia .= "Sugerencia: usa 'desde [origen] a [destino]'\n";
            } elseif (!$tiene_origen && $tiene_destino) {
                $advertencia .= "Solo se encontró el destino: " . htmlspecialchars($datos['destino']) . "\n";
                $advertencia .= "Sugerencia: usa 'desde [origen] a [destino]'\n";
            } else {
                $advertencia .= "Sugerencia: usa 'desde [origen] a [destino]' o '[origen] - [destino]'\n";
            }
        }
        
        $feedback .= "\n" . $advertencia;
        
        // Resumen para confirmar
        $feedback .= "\n📋 <b>Resumen para guardar:</b>\n";
        $feedback .= "🚗 Conductor: " . htmlspecialchars($datos['conductor'] ?? 'No disponible') . "\n";
        $feedback .= "🗺️ Ruta: " . htmlspecialchars($datos['ruta'] ?? 'No disponible') . "\n";
        $feedback .= "🚙 Vehículo: " . htmlspecialchars($datos['tipo_vehiculo'] ?? 'No especificado') . "\n";
        $feedback .= "🏢 Empresa: " . htmlspecialchars($datos['empresa'] ?? 'Hospital') . "\n";
        if ($datos['paciente']) {
            $feedback .= "👤 Paciente: " . htmlspecialchars($datos['paciente']) . "\n";
        }
        if ($datos['auxiliar']) {
            $feedback .= "👤 Auxiliar: " . htmlspecialchars($datos['auxiliar']) . "\n";
        }
        if ($datos['acompanante']) {
            $feedback .= "👤 Acompañante: " . htmlspecialchars($datos['acompanante']) . "\n";
        }
        if ($datos['hora_salida']) {
            $feedback .= "🕐 Hora salida: " . htmlspecialchars($datos['hora_salida']) . "\n";
        }
        
        // Si falta la ruta, preguntar si quiere continuar igual
        $feedback .= "\n📸 <b>Ahora envía la imagen del viaje</b>\n";
        if (!$tiene_ruta) {
            $feedback .= "⚠️ Puedes continuar, pero la ruta quedará incompleta.\n";
        }
        $feedback .= "\n💡 Escribe /cancel para cancelar";
        
        // Guardar datos temporalmente
        $this->user_data = [
            'step' => 'awaiting_image',
            'datos' => $datos,
            'texto_original' => $texto
        ];
        $this->saveUserData();
        
        // Teclado personalizado
        $keyboard = [
            'keyboard' => [
                ['❌ Cancelar']
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ];
        
        return sendTelegramMessage($chat_id, $feedback, $keyboard);
    }
    
    /**
     * Envía teclado de confirmación
     */
    private function sendConfirmationKeyboard() {
        $chat_id = $this->chat_id;
        $datos = $this->user_data['datos'];
        
        $mensaje = "📋 <b>Confirmar datos del viaje</b>\n\n" .
                   "🚗 <b>Conductor:</b> " . htmlspecialchars($datos['conductor']) . "\n" .
                   "🗺️ <b>Ruta:</b> " . htmlspecialchars($datos['ruta'] ?? 'No disponible') . "\n" .
                   "🚙 <b>Vehículo:</b> " . htmlspecialchars($datos['tipo_vehiculo'] ?? 'No especificado') . "\n" .
                   "🏢 <b>Empresa:</b> " . htmlspecialchars($datos['empresa'] ?? 'Hospital') . "\n";
        
        if ($datos['paciente']) {
            $mensaje .= "👤 <b>Paciente:</b> " . htmlspecialchars($datos['paciente']) . "\n";
        }
        if ($datos['auxiliar']) {
            $mensaje .= "👤 <b>Auxiliar:</b> " . htmlspecialchars($datos['auxiliar']) . "\n";
        }
        if ($datos['acompanante']) {
            $mensaje .= "👤 <b>Acompañante:</b> " . htmlspecialchars($datos['acompanante']) . "\n";
        }
        if ($datos['hora_salida']) {
            $mensaje .= "🕐 <b>Hora salida:</b> " . htmlspecialchars($datos['hora_salida']) . "\n";
        }
        
        $mensaje .= "\n📸 <b>Imagen:</b> Adjuntada ✅\n\n" .
                    "✅ <b>¿Confirmas guardar este viaje?</b>";
        
        // Teclado inline
        $inline_keyboard = [
            [
                ['text' => '✅ Sí, Guardar', 'callback_data' => 'confirm_yes'],
                ['text' => '❌ Cancelar', 'callback_data' => 'confirm_no']
            ]
        ];
        
        $this->user_data['step'] = 'confirming';
        $this->saveUserData();
        
        return sendTelegramInlineKeyboard($chat_id, $mensaje, $inline_keyboard);
    }
    
    /**
     * Guarda el viaje final
     */
    private function guardarViajeFinal() {
        $chat_id = $this->chat_id;
        $datos = $this->user_data['datos'];
        $imagen_path = $this->user_data['imagen'] ?? null;
        $texto_original = $this->user_data['texto_original'] ?? null;
        
        try {
            // Preparar datos
            $viaje_data = [
                'conductor' => $datos['conductor'],
                'ruta' => $datos['ruta'] ?? 'Sin ruta especificada',
                'tipo_vehiculo' => $datos['tipo_vehiculo'] ?? 'Burbuja',
                'empresa' => $datos['empresa'] ?? 'Hospital',
                'imagen' => $imagen_path ? basename($imagen_path) : null,
                'whatsapp' => $texto_original,
                'fecha' => date('Y-m-d'),
                'pagado' => 0
            ];
            
            // Guardar en base de datos
            $id = guardarViaje($viaje_data);
            
            // Limpiar datos del usuario
            $this->clearUserData();
            
            // Obtener el nombre del archivo de imagen
            $nombre_imagen = $imagen_path ? basename($imagen_path) : 'No disponible';
            
            $mensaje = "✅ <b>¡Viaje registrado exitosamente!</b> 🎉\n\n" .
                       "📋 <b>ID:</b> <code>#$id</code>\n" .
                       "🚗 <b>Conductor:</b> " . htmlspecialchars($datos['conductor']) . "\n" .
                       "🗺️ <b>Ruta:</b> " . htmlspecialchars($datos['ruta'] ?? 'Sin ruta') . "\n" .
                       "🚙 <b>Vehículo:</b> " . htmlspecialchars($datos['tipo_vehiculo'] ?? 'No especificado') . "\n" .
                       "🏢 <b>Empresa:</b> " . htmlspecialchars($datos['empresa'] ?? 'Hospital') . "\n" .
                       "📅 <b>Fecha:</b> " . date('d/m/Y H:i') . "\n" .
                       "📸 <b>Imagen:</b> " . $nombre_imagen . "\n\n" .
                       "🔄 Envía otro viaje o usa los comandos:\n" .
                       "/conductores - Ver conductores registrados\n" .
                       "/rutas - Ver rutas registradas";
            
            // Teclado para continuar
            $keyboard = [
                'keyboard' => [
                    ['📝 Nuevo viaje']
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ];
            
            return sendTelegramMessage($chat_id, $mensaje, $keyboard);
            
        } catch (Exception $e) {
            error_log("Error al guardar viaje: " . $e->getMessage());
            return sendTelegramMessage($chat_id, 
                "❌ Error al guardar el viaje.\n\n" .
                "Detalle: " . $e->getMessage() . "\n\n" .
                "Intenta nuevamente o contacta al administrador."
            );
        }
    }
    
    /**
     * Descarga una imagen de Telegram
     */
    private function downloadImage($file_id) {
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/getFile?file_id=" . $file_id;
        $response = file_get_contents($url);
        $data = json_decode($response, true);
        
        if (!$data['ok']) {
            throw new Exception("No se pudo obtener información del archivo");
        }
        
        $file_path = $data['result']['file_path'];
        $download_url = "https://api.telegram.org/file/bot" . BOT_TOKEN . "/" . $file_path;
        
        $extension = pathinfo($file_path, PATHINFO_EXTENSION);
        if (empty($extension)) {
            $extension = 'jpg';
        }
        
        // Guardar DIRECTAMENTE en /uploads
        $nombre_archivo = 'viaje_' . date('Ymd_His') . '_' . uniqid() . '.' . $extension;
        $ruta_completa = UPLOAD_DIR . $nombre_archivo;
        
        // Descargar archivo
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $download_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $image_data = curl_exec($ch);
        
        if (curl_error($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Error al descargar imagen: " . $error);
        }
        curl_close($ch);
        
        if (empty($image_data)) {
            throw new Exception("No se pudo descargar la imagen (datos vacíos)");
        }
        
        // Guardar imagen en /uploads
        if (file_put_contents($ruta_completa, $image_data) === false) {
            throw new Exception("No se pudo guardar la imagen en /uploads");
        }
        
        return $ruta_completa;
    }
    
    /**
     * Envía mensaje de bienvenida
     */
    private function sendWelcome() {
        $chat_id = $this->chat_id;
        $this->clearUserData();
        
        $mensaje = "🚑 <b>¡Bienvenido al Bot de Registro de Viajes!</b>\n\n" .
                   "📝 <b>¿Cómo funciona?</b>\n" .
                   "1️⃣ Envía la información del viaje\n" .
                   "2️⃣ El bot analizará el mensaje y te mostrará <b>QUÉ encontró</b>\n" .
                   "3️⃣ Envía una foto del viaje\n" .
                   "4️⃣ Confirma y ¡listo!\n\n" .
                   "📌 <b>El bot extrae automáticamente:</b>\n" .
                   "• Conductor (busca 'conductor')\n" .
                   "• Ruta (busca 'desde ... a ...' o 'origen - destino')\n" .
                   "• Paciente, auxiliar, acompañante\n" .
                   "• Hora de salida\n" .
                   "• Empresa y tipo de vehículo\n\n" .
                   "📌 <b>Ejemplos:</b>\n" .
                   "• Traslado de paciente desde Paraíso a Nazareth conductor Mario Bocanegra\n" .
                   "• Conductor Jorge Palmar desde Maicao a Nazareth\n" .
                   "• Acompañante icela Hernández, Auxiliar María Antonia Velázquez, Conductor David Ricardo pimienta, Destino hospital San José de Maicao\n\n" .
                   "📋 <b>Comandos:</b>\n" .
                   "/start - Iniciar bot\n" .
                   "/help - Mostrar ayuda\n" .
                   "/conductores - Listar conductores\n" .
                   "/rutas - Listar rutas\n" .
                   "/cancel - Cancelar operación";
        
        return sendTelegramMessage($chat_id, $mensaje);
    }
    
    /**
     * Envía ayuda
     */
    private function sendHelp() {
        $chat_id = $this->chat_id;
        
        $mensaje = "📖 <b>Guía completa del Bot</b>\n\n" .
                   "🔹 <b>El bot analiza tu mensaje y extrae:</b>\n\n" .
                   "📍 <b>Conductor</b> - Busca la palabra 'conductor'\n" .
                   "   Variantes: conductor, CONDUCTOR, Conductor, conductr, conductór\n" .
                   "   Ejemplo: <code>... conductor Mario Bocanegra</code>\n\n" .
                   "📍 <b>Ruta</b> - Busca 'desde ... a ...' o '[origen] - [destino]'\n" .
                   "   Ejemplo: <code>desde Paraíso a Nazareth</code>\n" .
                   "   Ejemplo: <code>Maicao - Nazareth</code>\n\n" .
                   "📍 <b>Paciente</b> - Busca 'paciente [nombre]'\n" .
                   "📍 <b>Auxiliar</b> - Busca 'auxiliar [nombre]'\n" .
                   "📍 <b>Acompañante</b> - Busca 'acompañante [nombre]'\n" .
                   "📍 <b>Hora salida</b> - Busca 'hora salida: [hora]'\n\n" .
                   "📌 <b>Ejemplos completos:</b>\n" .
                   "• Traslado de paciente desde Paraíso a Nazareth conductor Mario Bocanegra\n" .
                   "• Conductor Jorge Palmar desde Maicao a Nazareth\n" .
                   "• Acompañante icela Hernández, Auxiliar María Antonia Velázquez, Conductor David Ricardo pimienta, Destino hospital San José de Maicao, Hora salida: 5:30\n\n" .
                   "💡 <b>El bot te mostrará qué encontró y qué no</b>\n" .
                   "📋 <b>Comandos:</b>\n" .
                   "/start - Iniciar bot\n" .
                   "/help - Mostrar ayuda\n" .
                   "/conductores - Listar conductores registrados\n" .
                   "/rutas - Listar rutas registradas\n" .
                   "/cancel - Cancelar operación actual";
        
        return sendTelegramMessage($chat_id, $mensaje);
    }
    
    /**
     * Lista conductores registrados
     */
    private function listarConductores() {
        $chat_id = $this->chat_id;
        $conductores = obtenerConductores();
        
        if (empty($conductores)) {
            return sendTelegramMessage($chat_id, "📋 No hay conductores registrados aún.");
        }
        
        $cantidad = count($conductores);
        $lista = "🚗 <b>Conductores registrados ({$cantidad})</b>\n\n";
        
        foreach ($conductores as $conductor) {
            $lista .= "• " . htmlspecialchars($conductor) . "\n";
        }
        
        $lista .= "\n💡 Usa estos nombres en tus mensajes para que el bot los reconozca.";
        
        return sendTelegramMessage($chat_id, $lista, null, 'HTML');
    }
    
    /**
     * Lista rutas registradas
     */
    private function listarRutas() {
        $chat_id = $this->chat_id;
        $rutas = obtenerRutas();
        
        if (empty($rutas)) {
            return sendTelegramMessage($chat_id, "📋 No hay rutas registradas aún.");
        }
        
        $cantidad = count($rutas);
        $lista = "🗺️ <b>Rutas registradas ({$cantidad})</b>\n\n";
        
        sort($rutas);
        foreach ($rutas as $ruta) {
            $lista .= "• " . htmlspecialchars($ruta) . "\n";
        }
        
        return sendTelegramMessage($chat_id, $lista, null, 'HTML');
    }
}

// ============================================================
// 5. PROCESAMIENTO DE WEBHOOK
// ============================================================

// Obtener el contenido de la petición
$content = file_get_contents("php://input");
$update = json_decode($content, true);

// Si no hay datos, responder con un mensaje de prueba
if (!$update) {
    die("Bot de Viajes funcionando correctamente. Versión 1.5");
}

// Procesar el mensaje
try {
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        
        // Crear instancia del handler
        $handler = new BotHandler($chat_id);
        
        // Verificar si es una foto
        if (isset($message['photo'])) {
            $response = $handler->handlePhoto($message['photo']);
        } 
        // Verificar si es un mensaje de texto
        elseif (isset($message['text'])) {
            $response = $handler->handleMessage($message);
        }
        // Otros tipos de mensaje
        else {
            sendTelegramMessage($chat_id, "📝 Por favor, envía un mensaje de texto o una foto.");
        }
    }
    // Manejar callback queries (botones inline)
    elseif (isset($update['callback_query'])) {
        $callback_query = $update['callback_query'];
        $chat_id = $callback_query['message']['chat']['id'];
        
        $handler = new BotHandler($chat_id);
        $handler->handleCallbackQuery($callback_query);
    }
    
} catch (Exception $e) {
    error_log("Error en el bot: " . $e->getMessage());
    if (isset($chat_id)) {
        sendTelegramMessage($chat_id, "❌ Ocurrió un error inesperado. Por favor, intenta nuevamente.");
    }
}

echo "OK";
?>