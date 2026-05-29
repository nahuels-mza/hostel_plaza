<?php
/**
 * Endpoint: para un rango de fechas, devuelve TODAS las habitaciones del
 * hostel con su disponibilidad y precio en ARS para ese rango exacto.
 *
 * Combina datos de:
 *   - rooms.json (foto, descripción, nombre marketing, amenities)
 *   - room_mapping.json (room_id local → room_type_id de BananaDesk)
 *   - BananaDesk (availability + price para las fechas pedidas)
 *
 * Ejemplo:
 *   GET /rooms_for_dates.php?check_in=2026-06-05&check_out=2026-06-08
 *
 * Respuesta:
 *   {
 *     "ok": true,
 *     "check_in": "2026-06-05",
 *     "check_out": "2026-06-08",
 *     "nights": 3,
 *     "rooms": [
 *       {
 *         "id": "4",
 *         "name": "...",
 *         "image": "...",
 *         "capacity": 4,
 *         "amenities": [...],
 *         "room_type_id": 22874,
 *         "available": true,
 *         "availability_count": 2,
 *         "price_per_night_ars": 18000,
 *         "total_ars": 54000,
 *         "currency": "ARS",
 *         "min_stay": 0
 *       }, ...
 *     ]
 *   }
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=120');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/whatsapp/availability.php';

// --- params ---
$checkIn  = isset($_GET['check_in'])  ? trim((string)$_GET['check_in'])  : '';
$checkOut = isset($_GET['check_out']) ? trim((string)$_GET['check_out']) : '';

$checkIn  = hp_normalize_date($checkIn);
$checkOut = hp_normalize_date($checkOut);

if (!$checkIn || !$checkOut || $checkIn >= $checkOut) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'check_in y check_out válidos requeridos (check_out > check_in, formato YYYY-MM-DD)']);
    exit;
}

$nights = (int)((strtotime($checkOut) - strtotime($checkIn)) / 86400);

// --- config + maps ---
$cfg = require __DIR__ . '/whatsapp/config.php';
$mapPath = __DIR__ . '/room_mapping.json';
$map = is_file($mapPath) ? json_decode(file_get_contents($mapPath), true) : [];
if (!is_array($map)) $map = [];

$rooms = hp_load_rooms(__DIR__ . '/rooms.json');

// --- consulta única a BananaDesk con el rango entero ---
$banana = hp_bananadesk_fetch(
    $cfg['bananadesk'],
    $checkIn, $checkOut,
    $cfg['paths']['cache']
);

// Indexar por room_type_id para lookup rápido
$bananaByType = [];
if (!empty($banana['rooms'])) {
    foreach ($banana['rooms'] as $r) {
        if (!empty($r['room_type_id'])) {
            $bananaByType[(int)$r['room_type_id']] = $r;
        }
    }
}

$out = [];
foreach ($rooms as $r) {
    $localId    = (string)$r['id'];
    $typeId     = isset($map[$localId]) ? $map[$localId] : null;
    $bd         = ($typeId && isset($bananaByType[(int)$typeId])) ? $bananaByType[(int)$typeId] : null;

    if ($bd) {
        $pricePerNight = (float)$bd['price'];
        $availability  = (int)$bd['availability'];
        $minStay       = (int)$bd['min_stay'];
        $available     = $availability > 0 && ($minStay === 0 || $nights >= $minStay);
    } else {
        // Sin mapeo o BananaDesk no devolvió este tipo: precio de rooms.json
        // como fallback, marcar como no-confirmado.
        $pricePerNight = (float)preg_replace('/[^0-9.]/', '', (string)($r['price_ars'] ?? '0'));
        $availability  = 0;
        $minStay       = 0;
        $available     = false;
    }

    $out[] = [
        'id'                  => $localId,
        'name'                => $r['name'],
        'type'                => $r['type'] ?? '',
        'description'         => $r['description'] ?? '',
        'image'               => $r['image'] ?? '',
        'image_list'          => $r['image_list'] ?? [],
        'capacity'            => (int)($r['capacity'] ?? 0),
        'amenities'           => $r['amenities'] ?? [],
        'price_usd_from'      => isset($r['price']) ? preg_replace('/[^0-9.]/', '', $r['price']) : '',
        'room_type_id'        => $typeId,
        'availability_count'  => $availability,
        'available'           => $available,
        'price_per_night_ars' => $pricePerNight,
        'total_ars'           => $pricePerNight * $nights,
        'currency'            => $bd['currency'] ?? 'ARS',
        'min_stay'            => $minStay,
        'source'              => $bd ? 'bananadesk' : 'local',
    ];
}

echo json_encode([
    'ok'        => true,
    'check_in'  => $checkIn,
    'check_out' => $checkOut,
    'nights'    => $nights,
    'rooms'     => $out,
], JSON_UNESCAPED_UNICODE);
