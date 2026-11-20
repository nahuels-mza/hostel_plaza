<?php
/**
 * Áreas comunes del hostel (living, sala de TV, cowork, cocina, patio).
 *
 * Los datos viven en areas.json. Formato de cada entrada:
 *   {
 *     "slug":      "cowork",            // se usa en la URL /area/cowork
 *     "title":     "Cowork",
 *     "text":      "Texto corto",       // tarjeta del home
 *     "long_text": "Texto largo",       // página del área (opcional; \n = salto de línea)
 *     "image":     "<link a UNA foto>", // hero de la página del área y foto de la tarjeta
 *     "gallery":   "<link a una CARPETA de Drive>"  // galería de la página del área
 *   }
 *
 * - `image`: link compartido de una foto de Google Drive (o cualquier URL http(s)).
 * - `gallery`: link compartido de una carpeta de Google Drive. La página del área lista
 *   las fotos de esa carpeta, así que para cambiar la galería alcanza con agregar, quitar
 *   o renombrar fotos en Drive. Se muestran ordenadas por nombre de archivo (para elegir
 *   el orden: 01-living.jpg, 02-sillones.jpg, ...). Tanto la carpeta como las fotos tienen
 *   que estar compartidas como "Cualquier persona con el enlace".
 *   (Todavía se acepta una lista de links de fotos: ["<url>", "<url>"].)
 *
 * Las fotos de Drive se sirven por hotlink a drive.google.com/thumbnail, que entrega un
 * JPEG aunque el original sea HEIC. El listado de la carpeta se guarda en cache/ por
 * HP_DRIVE_FOLDER_TTL segundos, así no se consulta a Google en cada visita; si Google no
 * responde se sigue mostrando el último listado guardado.
 */
require_once __DIR__ . '/_about_gallery.php'; // hp_drive_file_id()

const HP_DRIVE_FOLDER_TTL = 604800; // 1 semana

function hp_area_photo_src(string $url, int $width = 1000): string
{
    $id = hp_drive_file_id(['url' => $url]);
    if ($id) {
        return 'https://drive.google.com/thumbnail?id=' . urlencode($id) . '&sz=w' . $width;
    }
    return $url;
}

/** ID de una carpeta de Drive a partir de su link (…/folders/ID, …?id=ID) o del ID suelto. */
function hp_drive_folder_id(string $value): ?string
{
    $value = trim($value);
    if (preg_match('#/folders/([a-zA-Z0-9_-]+)#', $value, $m)) return $m[1];
    if (preg_match('#[?&]id=([a-zA-Z0-9_-]+)#', $value, $m))   return $m[1];
    if (preg_match('#^[a-zA-Z0-9_-]{15,}$#', $value))          return $value;
    return null;
}

/**
 * Lista las fotos de una carpeta pública de Drive, ordenadas por nombre.
 * Devuelve links de archivo (https://drive.google.com/file/d/ID/view), o [] si no se pudo
 * obtener nada y tampoco hay un listado guardado.
 */
function hp_drive_folder_photos(
    string $folderId,
    string $cacheDir,
    int $ttl = HP_DRIVE_FOLDER_TTL,
    string $endpoint = 'https://drive.google.com/embeddedfolderview'
): array {
    $cachePath = rtrim($cacheDir, '/') . '/drive_folder_' . $folderId . '.json';
    $cached = is_file($cachePath) ? json_decode((string)file_get_contents($cachePath), true) : null;
    $cached = is_array($cached) ? $cached : null;

    if ($cached !== null && (time() - filemtime($cachePath)) < $ttl) {
        return $cached;
    }

    $ch = curl_init($endpoint . '?id=' . rawurlencode($folderId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; HostelPlaza-Site/1.0)',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $photos = [];
    if ($body && $code === 200) {
        // Cada archivo de la carpeta es: <a href=".../file/d/ID/view…"> … flip-entry-title">NOMBRE<
        preg_match_all(
            '#<a href="https://drive\.google\.com/file/d/([a-zA-Z0-9_-]+)/view[^"]*".*?flip-entry-title">([^<]*)<#s',
            $body, $matches, PREG_SET_ORDER
        );
        $named = [];
        foreach ($matches as $m) {
            $name = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            // Solo imágenes (la carpeta puede tener otros archivos o subcarpetas)
            if (!preg_match('/\.(jpe?g|png|webp|gif|heic|heif)$/i', $name)) continue;
            $named[$name] = 'https://drive.google.com/file/d/' . $m[1] . '/view';
        }
        uksort($named, 'strnatcasecmp');
        $photos = array_values($named);
    }

    if ($photos) {
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
        @file_put_contents($cachePath, json_encode($photos), LOCK_EX);
        return $photos;
    }

    // Google no respondió o la carpeta salió vacía: mejor el último listado bueno que nada.
    return $cached ?? [];
}

function hp_load_areas(string $jsonFile): array
{
    $raw = file_exists($jsonFile) ? (json_decode(file_get_contents($jsonFile), true) ?: []) : [];

    $areas = [];
    foreach ($raw as $item) {
        $slug  = trim((string)($item['slug'] ?? ''));
        $title = trim((string)($item['title'] ?? ''));
        if ($slug === '' || $title === '') continue;

        // `gallery` puede ser un link a una carpeta de Drive (string) o una lista de links de fotos.
        $galleryFolder = '';
        $gallery = [];
        $g = $item['gallery'] ?? '';
        if (is_string($g)) {
            $galleryFolder = hp_drive_folder_id($g) ?? '';
        } elseif (is_array($g)) {
            foreach ($g as $url) {
                if (is_string($url) && $url !== '') $gallery[] = $url;
            }
        }

        $image = (string)($item['image'] ?? '');
        if ($image === '' && $gallery) $image = $gallery[0];

        $areas[] = [
            'slug'           => $slug,
            'title'          => $title,
            'text'           => (string)($item['text'] ?? ''),
            'long_text'      => (string)($item['long_text'] ?? ''),
            'image'          => $image,
            'gallery'        => $gallery,        // lista de fotos (formato anterior)
            'gallery_folder' => $galleryFolder,  // ID de la carpeta de Drive
        ];
    }
    return $areas;
}

/**
 * Fotos de la galería de un área: las de su carpeta de Drive o, si no hay carpeta (o no se
 * pudo leer), la lista de links del JSON. Solo la usa la página del área (el home no la
 * necesita, así no consulta Drive en cada visita).
 */
function hp_area_gallery(array $area, string $cacheDir): array
{
    if ($area['gallery_folder'] !== '') {
        $photos = hp_drive_folder_photos($area['gallery_folder'], $cacheDir);
        if ($photos) return $photos;
    }
    return $area['gallery'];
}
