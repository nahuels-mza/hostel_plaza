<?php
/**
 * Endpoint público de disponibilidad por habitación.
 *
 * Consulta BananaDesk día por día y devuelve qué fechas de los próximos N
 * días están NO disponibles para el room_id local indicado, más la primera
 * fecha disponible (para autocompletar el formulario de book.php).
 *
 * Ejemplo:
 *   GET /room_availability.php?room_id=4&days=30
 *
 * Respuesta:
 *   {
 *     "ok": true,
 *     "room_id": "4",
 *     "room_type_id": 22851,
 *     "days_checked": 30,
 *     "unavailable_dates": ["2026-05-22","2026-05-23"],
 *     "first_available": {"check_in":"2026-05-24","check_out":"2026-05-25"},
 *     "cached": false
 *   }
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/whatsapp/availability.php';

// --- Config ---
$cfgPath = __DIR__ . '/whatsapp/config.php';
if (!is_file($cfgPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'whatsapp/config.php no existe']);
    exit;
}
$cfg = require $cfgPath;

// --- Mapeo room_id local -> room_type_id de BananaDesk ---
$mapPath = __DIR__ . '/room_mapping.json';
$map = is_file($mapPath) ? json_decode(file_get_contents($mapPath), true) : [];
if (!is_array($map)) $map = [];

// --- Parámetros ---
$roomId = isset($_GET['room_id']) ? trim((string)$_GET['room_id']) : '';
$days   = isset($_GET['days']) ? (int)$_GET['days'] : 30;
$days   = max(7, min(60, $days));

if ($roomId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Falta room_id']);
    exit;
}

$roomTypeId = isset($map[$roomId]) ? $map[$roomId] : null;

if (!$roomTypeId) {
    // No hay mapeo configurado: respondemos OK con lista vacía para que
    // el frontend no rompa, pero indicamos que no se aplicó filtro.
    echo json_encode([
        'ok'                => true,
        'room_id'           => $roomId,
        'room_type_id'      => null,
        'days_checked'      => 0,
        'unavailable_dates' => [],
        'first_available'   => null,
        'cached'            => false,
        'note'              => 'No hay mapeo BananaDesk para este room_id. Editá room_mapping.json para activar el filtro.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$res = hp_bananadesk_blocked_dates(
    $cfg['bananadesk'],
    (int)$roomTypeId,
    $days,
    $cfg['paths']['cache']
);
$res['room_id'] = $roomId;

echo json_encode($res, JSON_UNESCAPED_UNICODE);
