<?php
/**
 * Carga las fotos del carrusel "about" desde Google Drive sin descargarlas
 * ni guardarlas en el servidor: cada entrada de about_gallery.json apunta a
 * un archivo de Drive compartido como "Cualquiera con el enlace" y se sirve
 * con un hotlink directo a drive.google.com (el navegador del visitante lo
 * carga desde Google, no pasa por este servidor).
 *
 * Formato esperado en about_gallery.json:
 *   [{ "url": "https://drive.google.com/file/d/FILE_ID/view?usp=sharing", "alt": "..." }]
 * También acepta { "id": "FILE_ID", "alt": "..." } directamente.
 *
 * Si el archivo no existe, está vacío, o ninguna entrada tiene un ID válido,
 * devuelve $fallback tal cual para no romper la sección.
 */
function hp_drive_file_id(array $item): ?string
{
    if (!empty($item['id'])) {
        return trim($item['id']);
    }
    if (!empty($item['url']) && preg_match('#/d/([a-zA-Z0-9_-]+)#', $item['url'], $m)) {
        return $m[1];
    }
    if (!empty($item['url']) && preg_match('#[?&]id=([a-zA-Z0-9_-]+)#', $item['url'], $m)) {
        return $m[1];
    }
    return null;
}

function hp_load_about_gallery(string $jsonFile, array $fallback): array
{
    $raw = file_exists($jsonFile) ? (json_decode(file_get_contents($jsonFile), true) ?: []) : [];

    $photos = [];
    foreach ($raw as $item) {
        $id = hp_drive_file_id($item);
        if (!$id) continue;
        $photos[] = [
            'src' => 'https://drive.google.com/thumbnail?id=' . urlencode($id) . '&sz=w1000',
            'alt' => $item['alt'] ?? 'Hostel Plaza',
        ];
    }

    return $photos ?: $fallback;
}
