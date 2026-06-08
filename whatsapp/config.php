<?php
/**
 * Configuración del bot de WhatsApp.
 * Los secrets se leen desde /storagedir/secrets.php (fuera de public_html, nunca en Git).
 * Copiar secrets.example.php → /storagedir/secrets.php y completar los valores.
 */

$_secretsFile = dirname(dirname(__DIR__)) . '/storagedir/secrets.php';
$_s = is_file($_secretsFile) ? (require $_secretsFile) : [];

return [
    'whatsapp' => [
        'phone_number_id' => $_s['wa_phone_number_id'] ?? '',
        'access_token'    => $_s['wa_access_token']    ?? '',
        'verify_token'    => $_s['wa_verify_token']    ?? '',
        'graph_version'   => 'v21.0',
    ],
    'claude' => [
        'api_key'    => $_s['claude_api_key'] ?? '',
        'model'      => 'claude-sonnet-4-5',
        'max_tokens' => 1024,
    ],
    'admin' => [
        // Número del hostel (+54 9 2615 37-2767) en formato E.164 sin '+'
        'phone'   => '5492615372767',
        'forward' => true,
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
        'name'        => 'Hostel Plaza',
        'website'     => 'https://hostelplaza.com.ar',
        'booking_url' => 'https://hostelplaza.com.ar/book.php',
        'check_in'    => '14:00',
        'check_out'   => '11:00',
        'breakfast'   => '07:30 - 10:00',
    ],
    'agent' => [
        'history_turns'     => 8,
        'office_hours_only' => false,
    ],
];
