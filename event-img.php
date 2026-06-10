<?php
/**
 * Sirve imágenes de eventos desde storagedir/events/ (fuera de public_html).
 * URL de uso: /event-img?f=sunset-vino.jpg
 *
 * Seguridad: solo permite nombres de archivo con letras, números, guiones,
 * guiones bajos y punto. Solo sirve jpg/jpeg/png/webp.
 */

$file = basename(trim((string)($_GET['f'] ?? '')));

// Validar nombre: solo caracteres seguros
if (!preg_match('/^[a-zA-Z0-9_\-]+\.(jpg|jpeg|png|webp)$/i', $file)) {
    http_response_code(400);
    exit;
}

$eventsDir = dirname(__DIR__) . '/storagedir/events/';
$fullPath  = $eventsDir . $file;

if (!is_file($fullPath)) {
    http_response_code(404);
    exit;
}

$ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mime = match($ext) {
    'jpg', 'jpeg' => 'image/jpeg',
    'png'         => 'image/png',
    'webp'        => 'image/webp',
    default       => 'application/octet-stream',
};

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400'); // 1 día de caché en browser
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
