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
$guestsCount = isset($_GET['guests_count']) ? max(1, (int)$_GET['guests_count']) : 1;

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

// Tipo de cambio del sitio (config.json) para convertir USD → ARS cuando
// rooms.json no trae price_ars cargado.
$siteCfgPath = __DIR__ . '/config.json';
$siteCfg     = is_file($siteCfgPath) ? (json_decode(file_get_contents($siteCfgPath), true) ?: []) : [];
$exchangeARS = (float)($siteCfg['exchangeRateARS'] ?? 1370);

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
    $localId     = (string)$r['id'];
    $typeId      = isset($map[$localId]) ? $map[$localId] : null;
    $bd          = ($typeId && isset($bananaByType[(int)$typeId])) ? $bananaByType[(int)$typeId] : null;
    $bookingUnit = (($r['bookingUnit'] ?? 'room') === 'bed') ? 'bed' : 'room';
    $capacity    = (int)($r['capacity'] ?? 1);

    if ($bd) {
        // BananaDesk devuelve el precio TOTAL del stay (no por noche) de UNA unidad
        // (una cama, si es dormitorio compartido, o la habitación entera si es privada).
        $unitPrice    = (float)$bd['price'];
        $availability = (int)$bd['availability'];
        $minStay      = (int)$bd['min_stay'];
        $withinMinStay = ($minStay === 0 || $nights >= $minStay);

        if ($bookingUnit === 'bed') {
            // Compartido: cada huésped ocupa una cama, el precio escala con la cantidad.
            $available    = $withinMinStay && $availability >= $guestsCount;
            $totalForStay = $unitPrice * $guestsCount;
            if (!$withinMinStay)               $reason = 'min_stay';
            elseif ($availability < $guestsCount) $reason = 'not_enough_beds';
            else                                 $reason = 'ok';
        } else {
            // Privado: se vende la habitación entera, precio plano, tope = capacity.
            $available    = $withinMinStay && $availability >= 1 && $guestsCount <= $capacity;
            $totalForStay = $unitPrice;
            if (!$withinMinStay)              $reason = 'min_stay';
            elseif ($availability < 1)        $reason = 'sold_out';
            elseif ($guestsCount > $capacity) $reason = 'over_capacity';
            else                              $reason = 'ok';
        }
        $pricePerNight = $nights > 0 ? $totalForStay / $nights : $totalForStay;
    } else {
        // Sin mapeo o BananaDesk no devolvió este tipo. Calcular precio de
        // fallback: primero usar price_ars de rooms.json si está cargado,
        // si no, convertir el price USD usando el tipo de cambio del sitio.
        $rawArs = preg_replace('/[^0-9.]/', '', (string)($r['price_ars'] ?? ''));
        if ($rawArs !== '' && (float)$rawArs > 0) {
            $pricePerNight = (float)$rawArs;
        } else {
            $rawUsd = (float)preg_replace('/[^0-9.]/', '', (string)($r['price'] ?? '0'));
            $pricePerNight = $rawUsd * $exchangeARS;
        }
        $availability  = 0;
        $minStay       = 0;
        $available     = false;
        $reason        = 'no_data';
    }

    $out[] = [
        'id'                  => $localId,
        'name'                => $r['name'],
        'type'                => $r['type'] ?? '',
        'description'         => $r['description'] ?? '',
        'image'               => $r['image'] ?? '',
        'image_list'          => $r['image_list'] ?? [],
        'capacity'            => $capacity,
        'booking_unit'        => $bookingUnit,
        'amenities'           => $r['amenities'] ?? [],
        'price_usd_from'      => isset($r['price']) ? preg_replace('/[^0-9.]/', '', $r['price']) : '',
        'room_type_id'        => $typeId,
        'availability_count'  => $availability,
        'available'           => $available,
        'reason'              => $reason,
        'guests_count'        => $guestsCount,
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
