<?php
/**
 * ⚠️  Archivo de configuración real con credenciales.
 *     NO subir a git. Reemplazá los placeholders con tus valores reales.
 *
 *     Cómo obtener cada valor está explicado en whatsapp/README.md
 */
return [
    'whatsapp' => [
        'phone_number_id' => 'YOUR_PHONE_NUMBER_ID',
        'access_token'    => 'YOUR_PERMANENT_ACCESS_TOKEN',
        'verify_token'    => 'cambia_esto_por_algo_secreto',
        'graph_version'   => 'v21.0',
    ],
    'claude' => [
        'api_key'    => 'sk-ant-YOUR_KEY',
        'model'      => 'claude-sonnet-4-5',
        'max_tokens' => 1024,
    ],
    'admin' => [
        // Número del hostel (+54 9 2615 37-2767) en formato E.164 sin '+'
        'phone'    => '5492615372767',
        'forward'  => true,
    ],
    'bananadesk' => [
        'base_url'    => 'https://bananadesk.com',
        'hostel_slug' => 'hostel-plaza',
        'cache_ttl'   => 300,
        'timeout'     => 15,
    ],
    'paths' => [
        'rooms'         => __DIR__ . '/../rooms.json',
        'config_site'   => __DIR__ . '/../config.json',
        'log'           => __DIR__ . '/logs/whatsapp.log',
        'conversations' => __DIR__ . '/conversations.json',
        'cache'         => __DIR__ . '/cache',
    ],
    'hostel' => [
        'name'         => 'Hostel Plaza',
        'website'      => 'https://hostelplaza.com.ar',
        'booking_url'  => 'https://hostelplaza.com.ar/book.php',
        'check_in'     => '14:00',
        'check_out'    => '11:00',
        'breakfast'    => '07:30 - 10:00',
    ],
    'agent' => [
        'history_turns'     => 8,
        'office_hours_only' => false,
    ],
];
