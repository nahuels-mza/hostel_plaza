<?php
/**
 * Automatiza la creación de una reserva en BananaDesk via su booking engine.
 *
 * Endpoint descubierto inspeccionando el tráfico del motor de reservas:
 *   POST https://bananadesk.com/booking-engine/hostel/{slug}/reserve-and-bank-transfer
 *
 * Flujo:
 *   1. Consulta disponibilidad para obtener precio, fotos y restricciones del room type
 *   2. Si hay disponibilidad, hace el POST de reserva con los datos del huésped
 *   3. Devuelve ['ok' => bool, 'response' => array|null, 'error' => string|null]
 *
 * Edge case — sin disponibilidad:
 *   Devuelve ok=false con error descriptivo. El booking ya fue guardado localmente
 *   así que no se pierde nada; la reserva en BananaDesk se puede crear a mano.
 */

/**
 * @param string $checkIn     YYYY-MM-DD
 * @param string $checkOut    YYYY-MM-DD
 * @param int    $roomTypeId  ID de room type en BananaDesk (de room_mapping.json)
 * @param string $guestName
 * @param string $guestEmail
 * @param string $guestPhone
 * @param int    $guestsCount Cantidad de huéspedes de la reserva (default 1)
 * @param string $bookingUnit 'room' (privada, se vende entera) o 'bed' (dormitorio
 *                             compartido, se vende por cama) — viene de rooms.json
 * @return array{ok: bool, response: array|null, error: string|null}
 */
function hp_bananadesk_reserve(
    string $checkIn,
    string $checkOut,
    int    $roomTypeId,
    string $guestName,
    string $guestEmail,
    string $guestPhone,
    int    $guestsCount = 1,
    string $bookingUnit = 'room'
): array {
    $cfg   = require __DIR__ . '/whatsapp/config.php';
    $bdCfg = $cfg['bananadesk'];
    $base  = rtrim($bdCfg['base_url'], '/');
    $slug  = $bdCfg['hostel_slug'];
    $timeout = (int)($bdCfg['timeout'] ?? 15);

    $nights = max(1, (int)round((strtotime($checkOut) - strtotime($checkIn)) / 86400));

    // --- 1. Obtener datos del room type (precio, fotos, restricciones) ---
    $availUrl = sprintf(
        '%s/booking-engine/%s/room-type-availability?date_from=%s&date_to=%s&room_type=both',
        $base, rawurlencode($slug), rawurlencode($checkIn), rawurlencode($checkOut)
    );

    $ch = curl_init($availUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json', 'User-Agent: HostelPlaza-Booking/1.0'],
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code < 200 || $code >= 300 || !$body) {
        return ['ok' => false, 'response' => null, 'error' => "Availability fetch failed: HTTP $code"];
    }

    $rooms = json_decode($body, true);
    if (!is_array($rooms)) {
        return ['ok' => false, 'response' => null, 'error' => 'Availability response is not valid JSON'];
    }

    // Buscar el room type pedido
    $room = null;
    foreach ($rooms as $r) {
        if ((int)($r['room_type_id'] ?? 0) === $roomTypeId) {
            $room = $r;
            break;
        }
    }

    if (!$room) {
        return ['ok' => false, 'response' => null, 'error' => "Room type {$roomTypeId} not found for {$checkIn}–{$checkOut}"];
    }

    // Determinar cantidad a reservar: 1 habitación entera para privadas, o la
    // cantidad de camas pedidas para dormitorios compartidos.
    $quantity = ($bookingUnit === 'bed') ? max(1, $guestsCount) : 1;

    if ((int)($room['availability'] ?? 0) < $quantity) {
        $available = (int)($room['availability'] ?? 0);
        return ['ok' => false, 'response' => null, 'error' => "Room type {$roomTypeId} has only {$available} of {$quantity} requested " . ($bookingUnit === 'bed' ? 'beds' : 'rooms') . " for {$checkIn}–{$checkOut}"];
    }

    // --- 2. Armar payload ---
    $unitPrice  = (float)($room['price'] ?? 0); // BananaDesk devuelve el total del stay para UNA unidad
    $totalPrice = $unitPrice * $quantity;

    $roomName  = $room['name'] ?? '';
    $salesUnit = ($bookingUnit === 'bed') ? 'beds' : 'rooms';

    $payload = [
        'data' => [
            'subtotal'     => $totalPrice,
            'total'        => $totalPrice,
            'reservations' => [[
                'room_type_id'            => $roomTypeId,
                'name'                    => $roomName,
                'description'             => $room['description'] ?? '',
                'pricing_type'            => $room['pricing_type'] ?? 2,
                'taxes'                   => $room['taxes'] ?? [],
                'photos'                  => $room['photos'] ?? [],
                'availability'            => (int)($room['availability'] ?? 1),
                'price'                   => number_format($unitPrice, 2, '.', ''),
                'currency'                => $room['currency'] ?? 'ARS',
                'stay_length_restriction' => $room['stay_length_restriction'] ?? [
                    'has_restriction'    => false,
                    'restriction_reason' => 'No stay length permission',
                    'stay_length'        => 0,
                ],
                'sales_unit'  => $salesUnit,
                'reservation' => $quantity,
            ]],
            'taxes'     => [['amount' => 0, 'percentage' => 0, '$$hashKey' => 'object:190']],
            'arrival'   => $checkIn,
            'departure' => $checkOut,
            'days'      => $nights,
        ],
        'userData' => [
            'full_name' => $guestName,
            'email'     => $guestEmail,
            'phone'     => $guestPhone,
        ],
    ];

    // --- 3. POST a reserve-and-bank-transfer ---
    $reserveUrl = sprintf('%s/booking-engine/hostel/%s/reserve-and-bank-transfer', $base, rawurlencode($slug));
    $jsonBody   = json_encode($payload, JSON_UNESCAPED_UNICODE);

    $ch = curl_init($reserveUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jsonBody,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: HostelPlaza-Booking/1.0',
        ],
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $respBody = curl_exec($ch);
    $respCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['ok' => false, 'response' => null, 'error' => "cURL error: {$curlErr}"];
    }

    $respData = json_decode($respBody, true);

    if ($respCode >= 200 && $respCode < 300) {
        return ['ok' => true, 'response' => $respData, 'error' => null];
    }

    return [
        'ok'       => false,
        'response' => $respData,
        'error'    => "BananaDesk returned HTTP {$respCode}: " . substr((string)$respBody, 0, 300),
    ];
}
