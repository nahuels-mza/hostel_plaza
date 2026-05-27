<?php
/**
 * Configuración del agente de WhatsApp para Hostel Plaza
 *
 * Copia este archivo como `config.php` y reemplaza los valores.
 * `config.php` NUNCA debería subirse al repositorio.
 */
return [
    // --- WhatsApp Cloud API (Meta) ---
    'whatsapp' => [
        // ID del número de teléfono dentro de Meta Business Manager
        'phone_number_id' => 'YOUR_PHONE_NUMBER_ID',
        // Token de acceso permanente del System User
        'access_token'    => 'YOUR_PERMANENT_ACCESS_TOKEN',
        // Cadena arbitraria que tú eliges; debe coincidir con la que cargues
        // en la configuración del webhook en Meta Business
        'verify_token'    => 'cambia_esto_por_algo_secreto',
        // Versión de Graph API
        'graph_version'   => 'v21.0',
    ],

    // --- Claude API (Anthropic) ---
    'claude' => [
        'api_key'    => 'sk-ant-YOUR_KEY',
        'model'      => 'claude-sonnet-4-5',
        'max_tokens' => 1024,
    ],

    // --- Notificaciones al administrador ---
    'admin' => [
        // Tu número personal de WhatsApp con código de país, sin '+' ni espacios.
        // Ejemplo Argentina: 5491155555555
        'phone'    => '5491100000000',
        // true => recibís un WhatsApp con cada consulta entrante
        'forward'  => true,
    ],

    // --- BananaDesk (motor de reservas, fuente de disponibilidad real) ---
    'bananadesk' => [
        'base_url'    => 'https://bananadesk.com',
        'hostel_slug' => 'hostel-plaza',
        'cache_ttl'   => 300,   // segundos. 5 min es razonable: balancea
                                 // datos frescos con no martillar el endpoint.
        'timeout'     => 15,
    ],

    // --- Rutas a los archivos del sitio ---
    'paths' => [
        'rooms'         => __DIR__ . '/../rooms.json',     // opcional: enriquecer
        'config_site'   => __DIR__ . '/../config.json',
        'log'           => __DIR__ . '/logs/whatsapp.log',
        'conversations' => __DIR__ . '/conversations.json',
        'cache'         => __DIR__ . '/cache',
    ],

    // --- Datos del hostel (para el contexto del agente) ---
    'hostel' => [
        'name'         => 'Hostel Plaza',
        'website'      => 'https://hostelplaza.com.ar',
        'booking_url'  => 'https://hostelplaza.com.ar/book.php',
        'check_in'     => '14:00',
        'check_out'    => '11:00',
        'breakfast'    => '07:30 - 10:00',
    ],

    // --- Comportamiento ---
    'agent' => [
        // Cuántos turnos previos mantener en memoria por número de teléfono
        'history_turns' => 8,
        // Si true, el bot solo responde durante horario; fuera de horario
        // envía un mensaje genérico. Dejá false para responder 24/7.
        'office_hours_only' => false,
    ],
];
