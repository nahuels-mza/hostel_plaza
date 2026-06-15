<?php
/**
 * Sirve storagedir/hero.jpg (fuera de public_html) como imagen HTTP.
 * URL de uso: /hero-img
 * Si el archivo no existe, redirige al fallback de Booking.com.
 */

$heroPath = dirname(__DIR__) . '/storagedir/hero.jpg';

if (!is_file($heroPath)) {
    header('Location: https://cf.bstatic.com/xdata/images/hotel/max1024x768/729495907.jpg?k=d71bbf73091e760156f197c083753505fa465a09c4417af71328eb51926f0883&o=');
    exit;
}

$mtime = filemtime($heroPath);

// Support conditional GET (304 Not Modified)
$ifModSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
if ($ifModSince && strtotime($ifModSince) >= $mtime) {
    http_response_code(304);
    exit;
}

header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=86400');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('Content-Length: ' . filesize($heroPath));
readfile($heroPath);
