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

// User-Agent tipo navegador: BananaDesk (nginx) devolvió 403 al UA "HostelPlaza-Booking/1.0".
const HP_BD_USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

/**
 * HTTP helper con diagnóstico completo (para poder entender fallos tipo 403 de nginx/WAF).
 * @return array{code:int, body:string, err:string, errno:int, diag:array}
 */
function hp_bd_http(string $step, string $url, array $opts, array $headers): array
{
    $ch = curl_init($url);
    $respHeaders = [];
    curl_setopt_array($ch, $opts + [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADERFUNCTION => function ($c, $h) use (&$respHeaders) {
            $t = trim($h);
            if ($t !== '' && strpos($t, ':') !== false) $respHeaders[] = $t;
            return strlen($h);
        },
    ]);
    $body  = curl_exec($ch);
    $info  = curl_getinfo($ch);
    $err   = curl_error($ch);
    $errno = curl_errno($ch);
    curl_close($ch);

    $diag = [
        'step'         => $step,
        'url'          => $url,
        'http_code'    => (int)($info['http_code'] ?? 0),
        'effective_url'=> $info['url'] ?? $url,
        'redirects'    => (int)($info['redirect_count'] ?? 0),
        'server_ip'    => $info['primary_ip'] ?? '',
        'total_time_s' => round((float)($info['total_time'] ?? 0), 3),
        'curl_errno'   => $errno,
        'curl_error'   => $err,
        'resp_headers' => array_slice($respHeaders, -25),
        'resp_body'    => is_string($body) ? trim(preg_replace('/\s+/', ' ', strip_tags($body))) : '',
    ];
    return ['code' => $diag['http_code'], 'body' => is_string($body) ? $body : '', 'err' => $err, 'errno' => $errno, 'diag' => $diag];
}

/** Escribe un bloque de diagnóstico en logs/bananadesk/ cuando algo falla. */
function hp_bd_log_failure(string $reason, array $diag, ?string $payload = null): void
{
    if (!function_exists('hp_write_log')) require_once __DIR__ . '/logger.php';
    $lines = ["FAIL [{$diag['step']}] {$reason}"];
    foreach ($diag as $k => $v) {
        if ($k === 'step') continue;
        if (is_array($v)) $v = implode(' | ', $v);
        $lines[] = sprintf('  %-13s %s', $k . ':', substr((string)$v, 0, 800));
    }
    if ($payload !== null) $lines[] = '  payload:      ' . substr($payload, 0, 2000);
    $lines[] = '  context:      ' . hp_client_info() . ' | php ' . PHP_VERSION;
    $lines[] = str_repeat('-', 60);
    hp_write_log('bananadesk', implode("\n", $lines));
}

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

    $r = hp_bd_http('availability', $availUrl, [CURLOPT_TIMEOUT => $timeout],
        ['Accept: application/json', 'User-Agent: ' . HP_BD_USER_AGENT]);
    $body = $r['body'];
    $code = $r['code'];

    if ($r['errno'] || $code < 200 || $code >= 300 || !$body) {
        hp_bd_log_failure('availability fetch failed', $r['diag']);
        $why = $r['errno'] ? "cURL error {$r['errno']}: {$r['err']}" : "HTTP $code";
        return ['ok' => false, 'response' => null, 'error' => "Availability fetch failed: $why", 'diag' => $r['diag']];
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

    $r = hp_bd_http('reserve', $reserveUrl, [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => $jsonBody,
        CURLOPT_TIMEOUT    => $timeout,
    ], [
        'Content-Type: application/json',
        'Accept: application/json, text/plain, */*',
        'Accept-Language: es-AR,es;q=0.9,en;q=0.8',
        'Origin: ' . $base,
        'Referer: ' . $base . '/',
        'User-Agent: ' . HP_BD_USER_AGENT,
    ]);
    $respBody = $r['body'];
    $respCode = $r['code'];

    if ($r['errno']) {
        hp_bd_log_failure('cURL error', $r['diag'], $jsonBody);
        return ['ok' => false, 'response' => null, 'error' => "cURL error {$r['errno']}: {$r['err']}", 'diag' => $r['diag']];
    }

    $respData = json_decode($respBody, true);

    if ($respCode >= 200 && $respCode < 300) {
        return ['ok' => true, 'response' => $respData, 'error' => null];
    }

    hp_bd_log_failure("HTTP {$respCode} on reserve", $r['diag'], $jsonBody);
    return [
        'ok'       => false,
        'response' => $respData,
        'error'    => "BananaDesk returned HTTP {$respCode}: " . substr(trim(preg_replace('/\s+/', ' ', strip_tags((string)$respBody))), 0, 300),
        'diag'     => $r['diag'],
    ];
}
