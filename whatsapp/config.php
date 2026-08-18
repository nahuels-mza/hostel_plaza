<?php
/**
 * Configuración del bot de WhatsApp.
 * Los secrets se leen desde /storagedir/secrets.php (fuera de public_html, nunca en Git).
 * Copiar secrets.example.php → /storagedir/secrets.php y completar los valores.
 */

require_once dirname(__DIR__) . '/dev_env.php';
$_secretsFile = hp_secrets_path(dirname(__DIR__));
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
        // Carpeta de logs (un archivo por día, ver logger.php en la raíz)
        'log_dir'       => dirname(__DIR__) . '/logs/whatsapp',
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
        // Cantidad de pares user+assistant a mantener en memoria por número
        'history_turns'          => 5,
        // Horas de inactividad tras las cuales la conversación se resetea
        // (evita arrastrar historial viejo de consultas ya cerradas)
        'reset_after_hours'      => 6,
        // Días tras los cuales una conversación inactiva se borra por completo
        // (limpia conversations.json de datos viejos)
        'purge_after_days'       => 30,
        'office_hours_only'      => false,
    ],
];
