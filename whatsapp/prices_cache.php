<?php
/**
 * Cache diaria de precios desde BananaDesk.
 *
 * hp_today_prices($rootDir) devuelve un array [localRoomId => precioARSporNoche].
 * La primera llamada del día consulta BananaDesk (hoy→mañana, 1 noche = precio base)
 * y guarda el resultado en whatsapp/cache/prices_today_YYYY-MM-DD.json.
 * Las llamadas siguientes del mismo día leen el archivo sin tocar la API.
 * Los archivos de días anteriores se eliminan automáticamente.
 */
function hp_today_prices(string $rootDir): array
{
    $today     = date('Y-m-d');
    $tomorrow  = date('Y-m-d', strtotime('+1 day'));
    $cacheDir  = rtrim($rootDir, '/') . '/whatsapp/cache';
    $cacheFile = $cacheDir . '/prices_today_' . $today . '.json';

    if (is_file($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached)) return $cached;
    }

    require_once $rootDir . '/whatsapp/availability.php';
    $cfg = require $rootDir . '/whatsapp/config.php';
    $map = is_file($rootDir . '/room_mapping.json')
        ? (json_decode(file_get_contents($rootDir . '/room_mapping.json'), true) ?: [])
        : [];

    $banana = hp_bananadesk_fetch(
        $cfg['bananadesk'],
        $today, $tomorrow,
        $cacheDir
    );

    if (!$banana['ok'] || empty($banana['rooms'])) return [];

    $byType = [];
    foreach ($banana['rooms'] as $r) {
        if (!empty($r['room_type_id'])) {
            $byType[(int)$r['room_type_id']] = (float)$r['price'];
        }
    }

    $prices = [];
    foreach ($map as $localId => $typeId) {
        if (isset($byType[(int)$typeId])) {
            $prices[(string)$localId] = (int)round($byType[(int)$typeId]);
        }
    }

    @mkdir($cacheDir, 0775, true);
    file_put_contents($cacheFile, json_encode($prices, JSON_UNESCAPED_UNICODE));

    // Limpiar archivos de días anteriores
    foreach (glob($cacheDir . '/prices_today_*.json') as $f) {
        if (basename($f) !== 'prices_today_' . $today . '.json') @unlink($f);
    }

    return $prices;
}

/**
 * Formatea el precio ARS de una habitación para mostrar en UI.
 * Devuelve '' si el precio no está disponible.
 */
function hp_format_price(array $todayPrices, $roomId): string
{
    $p = $todayPrices[(string)$roomId] ?? null;
    return $p ? 'AR$ ' . number_format($p, 0, ',', '.') : '';
}
