<?php
/**
 * Bot de Telegram para Registro de Viajes
 * Versión: 2.0 - Extracción inteligente basada en patrones reales
 * 
 * Basado en análisis de mensajes reales de la base de datos
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

// Directorio para guardar imágenes
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// Crear directorio si no existe
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// ============================================================
// 2. FUNCIONES DE TELEGRAM
// ============================================================

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

function callTelegramAPI($url, $data) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// ============================================================
// 3. BASE DE DATOS
// ============================================================

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// ============================================================
// 4. EXTRACCIÓN INTELIGENTE DE DATOS
// ============================================================

/**
 * Diccionario de nombres de lugares comunes
 */
function getLugaresMap() {
    return [
        'maíaco' => 'Maicao',
        'maicao' => 'Maicao',
        'nazareth' => 'Nazareth',
        'siapana' => 'Siapana',
        'paraíso' => 'Paraiso',
        'paraiso' => 'Paraiso',
        'uribia' => 'Uribia',
        'riohacha' => 'Riohacha',
        'puerto estrella' => 'Puerto Estrella',
        'puerto estrella' => 'Puerto Estrella',
        'villa fatima' => 'Villa Fátima',
        'villafatima' => 'Villa Fátima',
        'villa fátima' => 'Villa Fátima',
        'flor de la guajira' => 'Flor de la Guajira',
        'puerto nuevo' => 'Puerto Nuevo',
        'uribis' => 'Uribis',
        'taroa' => 'Taroa',
        'punta espada' => 'Punta Espada',
        'chimare' => 'Chimare',
        'cuestecita' => 'Cuestecita',
        'yolijalu' => 'Yolijalu',
        'ulisimou' => 'Ulisimou',
        'warerpa' => 'Warerpa',
        'jasariru' => 'Jasariru',
        'tawaira' => 'Tawaira',
        'cocomana' => 'Cocomana',
        'siliamana' => 'Siliamana',
        'sharimana' => 'Sharimana'
    ];
}

/**
 * Diccionario de conductores comunes
 */
function getConductoresMap() {
    return [
        'franco pimienta' => 'Franco Pimienta',
        'francisco pimienta' => 'Franco Pimienta',
        'fco pimienta' => 'Franco Pimienta',
        'guney gonzalez' => 'Guney González',
        'guney gonzále' => 'Guney González',
        'luis hernández' => 'Luis Hernández Polanco',
        'luis hernandez' => 'Luis Hernández Polanco',
        'luis hernandez polanco' => 'Luis Hernández Polanco',
        'luis hernández polanco' => 'Luis Hernández Polanco',
        'virgilio ortiz' => 'Virgilio Ortiz',
        'jorge palmar' => 'Jorge Eduardo Palmar',
        'jorge eduardo palmar' => 'Jorge Eduardo Palmar',
        'halder suárez' => 'Halder Enrrique Suárez Machado',
        'halder suarez' => 'Halder Enrrique Suárez Machado',
        'halder enrrique suárez machado' => 'Halder Enrrique Suárez Machado',
        'bernaudino suárez' => 'Bernardino Suárez',
        'bernardino suarez' => 'Bernardino Suárez',
        'ricardo gonzález' => 'Ricardo González Polanco',
        'ricardo gonzalez' => 'Ricardo González Polanco',
        'ricardo prieto' => 'Ricardo Prieto',
        'jaifer iguaran' => 'Jaifer Luis Iguaran',
        'jaifer luis iguaran' => 'Jaifer Luis Iguaran',
        'lino lópez' => 'Lino López',
        'lino lopez' => 'Lino López',
        'adalberto' => 'Adalberto',
        'roman iguarán' => 'Roman Oswaldo Iguarán Paz',
        'roman iguaran' => 'Roman Oswaldo Iguarán Paz',
        'yulian carreño' => 'Yulian Carreño',
        'yulian carreno' => 'Yulian Carreño',
        'yusein arregoces' => 'Yusein Arregoces',
        'lucio adán iguaran' => 'Lucio Adán Iguaran',
        'lucio adan iguaran' => 'Lucio Adán Iguaran',
        'cesar fernández' => 'César Fernández',
        'cesar fernandez' => 'César Fernández',
        'edixon castillo' => 'Edixon Castillo Fernández',
        'javier ospino' => 'Javier Arturo Ospino',
        'javier arturo ospino' => 'Javier Arturo Ospino',
        'francisco cambar' => 'Francisco Cambar',
        'elider ortiz' => 'Elider Ortiz Montiel',
        'constantino kuasth' => 'Constantino José Kuasth',
        'ricardo polanco' => 'Ricardo Polanco Hernandez',
        'benjamín castañeda' => 'Benjamín Castañeda',
        'benjamin castañeda' => 'Benjamín Castañeda',
        'alexis brito' => 'Alexis Brito',
        'osnaider iguaran' => 'Osnaider Iguaran',
        'tulio forero' => 'Tulio Forero',
        'ramón uriana' => 'Ramón Uriana',
        'ramon uriana' => 'Ramón Uriana'
    ];
}

/**
 * Extrae datos del mensaje basado en patrones reales
 */
function extraerDatosDelMensaje($texto) {
    $texto_original = $texto;
    $texto_limpio = trim($texto);
    
    $resultado = [
        'conductor' => null,
        'ruta' => null,
        'origen' => null,
        'destino' => null,
        'empresa' => 'Hospital',
        'tipo_viaje' => 'ida_y_vuelta',
        'tipo_vehiculo' => 'Burbuja',
        'texto_original' => $texto_original,
        'paciente' => null,
        'completo' => false
    ];
    
    // ============================================================
    // PASO 1: EXTRAER CONDUCTOR
    // ============================================================
    
    $conductor_encontrado = null;
    
    // Patrón 1: "conductor" seguido de nombre
    if (preg_match('/conductor\s*[:.]?\s*([\w\sáéíóúñ.]+?)(?:\s+desde|\s+traslado|\s+con\s+retorno|\s+retorno|\s+hospital|\s+de\s+campaña|\s+para|\s*$)/i', $texto_original, $matches)) {
        $nombre = trim($matches[1]);
        // Limpiar texto adicional
        $nombre = preg_replace('/\s+con\s+retorno/i', '', $nombre);
        $nombre = preg_replace('/\s+retorno/i', '', $nombre);
        $nombre = preg_replace('/\s+hospital\s+de\s+campaña/i', '', $nombre);
        $nombre = preg_replace('/\s+hospital\s+nazareth/i', '', $nombre);
        $nombre = trim($nombre);
        if (!empty($nombre)) {
            $conductor_encontrado = ucwords(strtolower($nombre));
        }
    }
    
    // Patrón 2: "conductor" al final del mensaje
    if (!$conductor_encontrado) {
        if (preg_match('/conductor\s+([\w\sáéíóúñ.]+)$/i', trim($texto_original), $matches)) {
            $nombre = trim($matches[1]);
            if (!empty($nombre)) {
                $conductor_encontrado = ucwords(strtolower($nombre));
            }
        }
    }
    
    // Patrón 3: "conducido por"
    if (!$conductor_encontrado) {
        if (preg_match('/conducido\s+por\s+([\w\sáéíóúñ.]+?)(?:\s+desde|\s+traslado|\s*$)/i', $texto_original, $matches)) {
            $nombre = trim($matches[1]);
            if (!empty($nombre)) {
                $conductor_encontrado = ucwords(strtolower($nombre));
            }
        }
    }
    
    // Normalizar conductor con el mapa de nombres
    if ($conductor_encontrado) {
        $nombre_lower = strtolower($conductor_encontrado);
        $conductores_map = getConductoresMap();
        foreach ($conductores_map as $key => $value) {
            if (strpos($nombre_lower, $key) !== false || strpos($key, $nombre_lower) !== false) {
                $conductor_encontrado = $value;
                break;
            }
        }
        $resultado['conductor'] = $conductor_encontrado;
    }
    
    // ============================================================
    // PASO 2: EXTRAER RUTA
    // ============================================================
    
    $origen = null;
    $destino = null;
    $ruta = null;
    
    // Patrón 1: "desde X a Y" o "desde X hasta Y"
    if (preg_match('/desde\s+([\w\sáéíóúñ]+?)\s+(?:a|hasta)\s+([\w\sáéíóúñ]+?)(?:\s+conductor|\s+para|\s+con\s+retorno|\s+retorno|\s+hospital|\s*$)/i', $texto_original, $matches)) {
        $origen = limpiarLugar(trim($matches[1]));
        $destino = limpiarLugar(trim($matches[2]));
        $ruta = $origen . '-' . $destino;
    }
    
    // Patrón 2: "X - Y" o "X-Y"
    if (!$ruta) {
        if (preg_match('/([\w\sáéíóúñ]+?)\s*[-–]\s*([\w\sáéíóúñ]+?)(?:\s+conductor|\s+para|\s+con\s+retorno|\s+retorno|\s+hospital|\s*$)/i', $texto_original, $matches)) {
            $origen = limpiarLugar(trim($matches[1]));
            $destino = limpiarLugar(trim($matches[2]));
            $ruta = $origen . '-' . $destino;
        }
    }
    
    // Patrón 3: "X a Y" (sin "desde")
    if (!$ruta) {
        if (preg_match('/([\w\sáéíóúñ]+?)\s+a\s+([\w\sáéíóúñ]+?)(?:\s+conductor|\s+para|\s+con\s+retorno|\s+retorno|\s+hospital|\s*$)/i', $texto_original, $matches)) {
            $origen = limpiarLugar(trim($matches[1]));
            $destino = limpiarLugar(trim($matches[2]));
            $ruta = $origen . '-' . $destino;
        }
    }
    
    // Patrón 4: Rutas con múltiples puntos (ej: "Nazareth-Siapana-Maicao")
    if (!$ruta) {
        if (preg_match('/([\w\sáéíóúñ]+?)\s*[-–]\s*([\w\sáéíóúñ]+?)\s*[-–]\s*([\w\sáéíóúñ]+?)/i', $texto_original, $matches)) {
            $origen = limpiarLugar(trim($matches[1]));
            $destino = limpiarLugar(trim($matches[3]));
            $ruta = $origen . '-' . $destino;
        }
    }
    
    // Limpiar texto adicional de la ruta
    if ($ruta) {
        $palabras_eliminar = ['hospital', 'de', 'campaña', 'con', 'retorno', 'carpa', 'nazareth', 'maicao', 'siapana', 'paraíso'];
        foreach ($palabras_eliminar as $palabra) {
            $ruta = preg_replace('/\s*' . preg_quote($palabra, '/') . '\s*/i', '', $ruta);
        }
        $ruta = trim($ruta);
        // Eliminar guiones dobles
        $ruta = preg_replace('/-+/', '-', $ruta);
        $ruta = trim($ruta, '-');
        
        // Capitalizar correctamente
        $partes = explode('-', $ruta);
        $partes = array_map(function($p) {
            return ucwords(strtolower(trim($p)));
        }, $partes);
        $ruta = implode('-', $partes);
        
        $resultado['origen'] = $origen;
        $resultado['destino'] = $destino;
        $resultado['ruta'] = $ruta;
    }
    
    // ============================================================
    // PASO 3: EXTRAER EMPRESA
    // ============================================================
    
    $empresas = [
        '/icbf/i' => 'ICBF',
        '/sunny\s+app/i' => 'Sunny App',
        '/acpm/i' => 'ACPM',
        '/cava/i' => 'Cava',
        '/p\.campaña/i' => 'P.Campaña',
        '/p\.nazareth/i' => 'P.Nazareth',
        '/p\.siapana/i' => 'P.Siapana',
        '/p\.paraiso/i' => 'P.Paraiso',
        '/p\.villa\s+fátima/i' => 'P.Villa Fátima',
        '/p\.flor\s+de\s+la\s+guajira/i' => 'P.Flor de la Guajira',
        '/p\.puerto\s+estrella/i' => 'P.Puerto Estrella',
        '/hospital\s+de\s+campaña/i' => 'Hospital Campaña',
        '/hospital\s+nazareth/i' => 'Hospital Nazareth'
    ];
    
    foreach ($empresas as $patron => $empresa) {
        if (preg_match($patron, $texto_original)) {
            $resultado['empresa'] = $empresa;
            break;
        }
    }
    
    // ============================================================
    // PASO 4: DETECTAR TIPO DE VIAJE
    // ============================================================
    
    if (preg_match('/con\s+retorno|retorno/i', $texto_original)) {
        $resultado['tipo_viaje'] = 'con_retorno';
    } elseif (preg_match('/solo\s+subida|subida/i', $texto_original)) {
        $resultado['tipo_viaje'] = 'solo_subida';
    } elseif (preg_match('/solo\s+bajada|bajada/i', $texto_original)) {
        $resultado['tipo_viaje'] = 'solo_bajada';
    }
    
    // ============================================================
    // PASO 5: EXTRAER TIPO DE VEHÍCULO
    // ============================================================
    
    $vehiculos = [
        '/burbuja/i' => 'Burbuja',
        '/camión\s+350/i' => 'Camión 350',
        '/camión\s+750/i' => 'Camión 750',
        '/carrotanque/i' => 'Carrotanque',
        '/volqueta/i' => 'Volqueta',
        '/camioneta/i' => 'Camioneta',
        '/copetrana/i' => 'Copetrana'
    ];
    
    foreach ($vehiculos as $patron => $vehiculo) {
        if (preg_match($patron, $texto_original)) {
            $resultado['tipo_vehiculo'] = $vehiculo;
            break;
        }
    }
    
    // ============================================================
    // PASO 6: VERIFICAR SI ESTÁ COMPLETO
    // ============================================================
    
    $resultado['completo'] = ($resultado['conductor'] && $resultado['ruta']);
    
    return $resultado;
}

/**
 * Limpia y normaliza nombres de lugares
 */
function limpiarLugar($lugar) {
    $lugar = trim($lugar);
    
    // Si tiene muchas palabras, tomar las primeras 2
    $palabras = explode(' ', $lugar);
    if (count($palabras) > 2) {
        $lugar = $palabras[0] . ' ' . $palabras[1];
    }
    
    // Normalizar con el mapa de lugares
    $lugares_map = getLugaresMap();
    $lugar_lower = strtolower($lugar);
    foreach ($lugares_map as $key => $value) {
        if (strpos($lugar_lower, $key) !== false) {
            return $value;
        }
    }
    
    return ucwords(strtolower($lugar));
}

// ============================================================
// 5. GUARDAR VIAJE EN BASE DE DATOS
// ============================================================

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
    $pago_parcial = $data['pago_parcial'] ?? null;
    $pagado = $data['pagado'] ?? 0;
    
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

// ============================================================
// 6. BOT HANDLER PRINCIPAL
// ============================================================

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
        
        // Si el usuario está en proceso de confirmación
        if (isset($this->user_data['step']) && $this->user_data['step'] === 'confirming') {
            if (strpos($text, 'sí') !== false || strpos($text, 'si') !== false || $text === '✅' || $text === '✅ Guardar') {
                return $this->guardarViajeFinal();
            } elseif (strpos($text, 'no') !== false || $text === '❌' || $text === '❌ Cancelar') {
                $this->clearUserData();
                return sendTelegramMessage($chat_id, "❌ Viaje cancelado.");
            }
            return $this->sendConfirmationKeyboard();
        }
        
        // Procesar mensaje de texto
        return $this->procesarTexto($text);
    }
    
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
            return sendTelegramMessage($chat_id, "❌ Viaje cancelado.");
        }
        return null;
    }
    
    private function procesarTexto($texto) {
        $chat_id = $this->chat_id;
        
        // Si el usuario presionó "Nuevo viaje"
        if (strpos($texto, '📝 Nuevo viaje') !== false || strpos($texto, 'nuevo viaje') !== false) {
            $this->clearUserData();
            return sendTelegramMessage($chat_id, 
                "📝 Envía la información del viaje.\n\n" .
                "Ejemplo:\n" .
                "Traslado de paciente desde Paraíso a Nazareth conductor Franco Pimienta"
            );
        }
        
        // Extraer datos
        $datos = extraerDatosDelMensaje($texto);
        
        // Si falta conductor o ruta, pedir corrección
        if (!$datos['completo']) {
            $mensaje = "🤔 No entendí completamente el mensaje.\n\n";
            $mensaje .= "📝 <b>Lo que detecté:</b>\n";
            $mensaje .= "🚗 Conductor: " . ($datos['conductor'] ?? '❌ No detectado') . "\n";
            $mensaje .= "🗺️ Ruta: " . ($datos['ruta'] ?? '❌ No detectada') . "\n";
            $mensaje .= "🏢 Empresa: " . $datos['empresa'] . "\n\n";
            $mensaje .= "🔄 <b>Por favor, escribe el mensaje con el formato:</b>\n";
            $mensaje .= "<code>Traslado de paciente desde [origen] a [destino] conductor [nombre]</code>\n\n";
            $mensaje .= "💡 <b>Ejemplo:</b>\n";
            $mensaje .= "Traslado de paciente desde Paraíso a Nazareth conductor Franco Pimienta";
            
            return sendTelegramMessage($chat_id, $mensaje);
        }
        
        // Guardar datos temporalmente
        $this->user_data = [
            'step' => 'awaiting_image',
            'datos' => $datos,
            'texto_original' => $texto
        ];
        $this->saveUserData();
        
        $mensaje = "✅ <b>Información extraída correctamente</b>\n\n" .
                   "🚗 <b>Conductor:</b> " . htmlspecialchars($datos['conductor']) . "\n" .
                   "🗺️ <b>Ruta:</b> " . htmlspecialchars($datos['ruta']) . "\n" .
                   "🚙 <b>Vehículo:</b> " . htmlspecialchars($datos['tipo_vehiculo']) . "\n" .
                   "🏢 <b>Empresa:</b> " . htmlspecialchars($datos['empresa']) . "\n" .
                   "🔄 <b>Tipo:</b> " . htmlspecialchars($datos['tipo_viaje']) . "\n\n" .
                   "📸 <b>Ahora envía la imagen del viaje</b>\n\n" .
                   "💡 Puedes cancelar escribiendo /cancel";
        
        $keyboard = [
            'keyboard' => [
                ['❌ Cancelar']
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ];
        
        return sendTelegramMessage($chat_id, $mensaje, $keyboard);
    }
    
    private function sendConfirmationKeyboard() {
        $chat_id = $this->chat_id;
        $datos = $this->user_data['datos'];
        
        $mensaje = "📋 <b>Confirmar datos del viaje</b>\n\n" .
                   "🚗 <b>Conductor:</b> " . htmlspecialchars($datos['conductor']) . "\n" .
                   "🗺️ <b>Ruta:</b> " . htmlspecialchars($datos['ruta']) . "\n" .
                   "🚙 <b>Vehículo:</b> " . htmlspecialchars($datos['tipo_vehiculo']) . "\n" .
                   "🏢 <b>Empresa:</b> " . htmlspecialchars($datos['empresa']) . "\n" .
                   "🔄 <b>Tipo:</b> " . htmlspecialchars($datos['tipo_viaje']) . "\n" .
                   "📸 <b>Imagen:</b> Adjuntada ✅\n\n" .
                   "✅ <b>¿Confirmas guardar este viaje?</b>";
        
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
    
    private function guardarViajeFinal() {
        $chat_id = $this->chat_id;
        $datos = $this->user_data['datos'];
        $imagen_path = $this->user_data['imagen'] ?? null;
        $texto_original = $this->user_data['texto_original'] ?? null;
        
        try {
            $viaje_data = [
                'conductor' => $datos['conductor'],
                'ruta' => $datos['ruta'],
                'tipo_vehiculo' => $datos['tipo_vehiculo'] ?? 'Burbuja',
                'empresa' => $datos['empresa'] ?? 'Hospital',
                'imagen' => $imagen_path ? basename($imagen_path) : null,
                'whatsapp' => $texto_original,
                'fecha' => date('Y-m-d'),
                'pagado' => 0
            ];
            
            $id = guardarViaje($viaje_data);
            $this->clearUserData();
            
            $mensaje = "✅ <b>¡Viaje registrado exitosamente!</b> 🎉\n\n" .
                       "📋 <b>ID:</b> <code>#$id</code>\n" .
                       "🚗 <b>Conductor:</b> " . htmlspecialchars($datos['conductor']) . "\n" .
                       "🗺️ <b>Ruta:</b> " . htmlspecialchars($datos['ruta']) . "\n" .
                       "🚙 <b>Vehículo:</b> " . htmlspecialchars($datos['tipo_vehiculo']) . "\n" .
                       "🏢 <b>Empresa:</b> " . htmlspecialchars($datos['empresa']) . "\n" .
                       "🔄 <b>Tipo:</b> " . htmlspecialchars($datos['tipo_viaje']) . "\n" .
                       "📅 <b>Fecha:</b> " . date('d/m/Y H:i') . "\n" .
                       "📸 <b>Imagen:</b> " . ($imagen_path ? basename($imagen_path) : 'No disponible') . "\n\n" .
                       "🔄 Envía otro viaje o escribe /help para ayuda.";
            
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
                "Intenta nuevamente."
            );
        }
    }
    
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
        
        $nombre_archivo = 'viaje_' . date('Ymd_His') . '_' . uniqid() . '.' . $extension;
        $ruta_completa = UPLOAD_DIR . $nombre_archivo;
        
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
            throw new Exception("No se pudo descargar la imagen");
        }
        
        if (file_put_contents($ruta_completa, $image_data) === false) {
            throw new Exception("No se pudo guardar la imagen");
        }
        
        return $ruta_completa;
    }
    
    private function sendWelcome() {
        $chat_id = $this->chat_id;
        $this->clearUserData();
        
        $mensaje = "🚑 <b>¡Bienvenido al Bot de Registro de Viajes!</b>\n\n" .
                   "📝 <b>¿Cómo funciona?</b>\n" .
                   "1️⃣ Envía la información del viaje\n" .
                   "2️⃣ El bot extraerá los datos automáticamente\n" .
                   "3️⃣ Envía una foto del viaje\n" .
                   "4️⃣ Confirma y ¡listo!\n\n" .
                   "📌 <b>Formato recomendado:</b>\n" .
                   "<code>Traslado de paciente desde [origen] a [destino] conductor [nombre]</code>\n\n" .
                   "📌 <b>Ejemplo:</b>\n" .
                   "Traslado de paciente desde Paraíso a Nazareth conductor Franco Pimienta\n\n" .
                   "📋 <b>Comandos:</b>\n" .
                   "/start - Iniciar bot\n" .
                   "/help - Mostrar ayuda\n" .
                   "/cancel - Cancelar operación";
        
        return sendTelegramMessage($chat_id, $mensaje);
    }
    
    private function sendHelp() {
        $chat_id = $this->chat_id;
        
        $mensaje = "📖 <b>Guía del Bot</b>\n\n" .
                   "🔹 <b>Paso 1:</b> Envía el mensaje con la información\n" .
                   "🔹 <b>Paso 2:</b> Envía la foto del viaje\n" .
                   "🔹 <b>Paso 3:</b> Confirma los datos\n\n" .
                   "📌 <b>Formatos aceptados:</b>\n" .
                   "• Traslado de paciente desde X a Y conductor Z\n" .
                   "• X - Y conductor Z\n" .
                   "• desde X a Y conductor Z\n\n" .
                   "📋 <b>Comandos:</b>\n" .
                   "/start - Iniciar\n" .
                   "/help - Ayuda\n" .
                   "/cancel - Cancelar";
        
        return sendTelegramMessage($chat_id, $mensaje);
    }
}

// ============================================================
// 7. PROCESAMIENTO DEL WEBHOOK
// ============================================================

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    die("Bot de Viajes funcionando correctamente. Versión 2.0");
}

try {
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $handler = new BotHandler($chat_id);
        
        if (isset($message['photo'])) {
            $handler->handlePhoto($message['photo']);
        } elseif (isset($message['text'])) {
            $handler->handleMessage($message);
        } else {
            sendTelegramMessage($chat_id, "📝 Por favor, envía un mensaje de texto o una foto.");
        }
    } elseif (isset($update['callback_query'])) {
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