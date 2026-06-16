<?php
/**
 * Sirve imágenes hero desde storagedir/ (fuera de public_html).
 *
 * URL: /hero-img          → storagedir/hero.jpg (default)
 * URL: /hero-img?p=rooms  → storagedir/rooms-hero.jpg  (con fallback a hero.jpg)
 * URL: /hero-img?p=about  → storagedir/about-hero.jpg  (con fallback a hero.jpg)
 * URL: /hero-img?p=tourist→ storagedir/tourist-hero.jpg (con fallback a hero.jpg)
 *
 * Si ningún archivo local existe, redirige al CDN de fallback del sitio.
 */

$pageMap = [
    'index'   => [
        'file'     => 'index-hero.jpg',
        'fallback' => 'https://cf.bstatic.com/xdata/images/hotel/max1024x768/1337158401.jpg?k=b28097b21b7c0273&o=',
    ],
    'rooms'   => [
        'file'     => 'rooms-hero.jpg',
        'fallback' => 'https://cf.bstatic.com/xdata/images/hotel/max1024x768/729495907.jpg?k=d71bbf73091e760156f197c083753505fa465a09c4417af71328eb51926f0883&o=',
    ],
    'about'   => [
        'file'     => 'about-hero.jpg',
        'fallback' => 'https://a.hwstatic.com/image/upload/f_auto,q_auto,w_1024,c_limit,e_sharpen,e_improve,e_vibrance:60/propertyimages/3/326554/fw84qo7amtxdt3x44jfd.jpg',
    ],
    'tourist' => [
        'file'     => 'tourist-hero.jpg',
        'fallback' => 'https://images.unsplash.com/photo-1596394516093-501ba68a0ba6?q=80&w=2000&auto=format&fit=crop',
    ],
];

// Validate page param; unknown values fall back to 'index'
$page = trim((string)($_GET['p'] ?? 'index'));
if (!isset($pageMap[$page])) {
    $page = 'index';
}

$cfg        = $pageMap[$page];
$storageDir = dirname(__DIR__) . '/storagedir/';

// Try page-specific file, then shared hero.jpg
$heroPath = null;
foreach (array_unique([$cfg['file'], 'hero.jpg']) as $candidate) {
    if (is_file($storageDir . $candidate)) {
        $heroPath = $storageDir . $candidate;
        break;
    }
}

if ($heroPath === null) {
    header('Location: ' . $cfg['fallback']);
    exit;
}

$mtime = filemtime($heroPath);

// Support conditional GET (304 Not Modified)
$ifModSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
if ($ifModSince && strtotime($ifModSince) >= $mtime) {
    http_response_code(304);
    exit;
}

$ext  = strtolower(pathinfo($heroPath, PATHINFO_EXTENSION));
$mime = match($ext) {
    'png'        => 'image/png',
    'webp'       => 'image/webp',
    default      => 'image/jpeg',
};

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('Content-Length: ' . filesize($heroPath));
readfile($heroPath);
