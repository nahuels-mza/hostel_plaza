<?php
/**
 * ⚠️  Archivo de configuración real con credenciales.
 *     NO subir a git. Reemplazá los placeholders con tus valores reales.
 *
 *     Cómo obtener cada valor está explicado en whatsapp/README.md
 */
return [
    'whatsapp' => [
        'phone_number_id' => '5492615372767',
        'access_token'    => 'EABAKAOZAig0YBRkQjuGZB4sZBlprTkk7DZBMlJ8W7KbVP3rxYMvW7kq4VUJ3mozoRCiPlQKah6NwrRCJDMZAc9IPX3QVCgOfddiMXtoK17KVIdf5BVSUw9VdpkAQ4ENTl2Bz86kVyl5FwnX3EeZAU4GxLIFvlQRqIsvDesAZCSkgnAiDebcmZCg7ekZCcUAF7OwZDZD',
        'verify_token'    => 'H0sT3lPl4z4V3r1fyT0k3n',
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
