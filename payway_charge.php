<?php
/**
 * Cobra 1 noche con tarjeta vía Payway para una reserva existente.
 * Recibe el token ya tokenizado en el navegador (los datos de tarjeta nunca
 * llegan a este endpoint) — ver pay.php.
 *
 * POST JSON: {booking_id, token, bin, billing: {street, city, state, postal_code, country}}
 * → {ok:true, operationId, amountArs} | {ok:false, error}
 */
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/payway_lib.php';

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) $in = [];

$bookingId = trim((string)($in['booking_id'] ?? ''));
$token     = trim((string)($in['token'] ?? ''));
$bin       = preg_replace('/\D/', '', (string)($in['bin'] ?? ''));
$billing   = is_array($in['billing'] ?? null) ? $in['billing'] : [];
$billStreet  = trim((string)($billing['street'] ?? ''));
$billCity    = trim((string)($billing['city'] ?? ''));
$billState   = trim((string)($billing['state'] ?? ''));
$billZip     = trim((string)($billing['postal_code'] ?? ''));
// País de facturación — 'AR' por default (huésped argentino no manda este
// campo), o el ISO alpha-2 elegido en pay.php si tildó "tarjeta extranjera".
$billCountry = strtoupper(trim((string)($billing['country'] ?? 'AR'))) ?: 'AR';

if ($bookingId === '' || $token === '' || strlen($bin) !== 6) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Datos incompletos.']);
    exit;
}

// CyberSource (antifraude de Payway) rechaza sin esto — ver payway.log:
// "Bill To is required".
if ($billStreet === '' || $billCity === '' || $billState === '' || $billZip === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Falta la dirección de facturación.']);
    exit;
}

$bookingsFile = __DIR__ . '/bookings.json';
$bookings = is_file($bookingsFile) ? (json_decode(file_get_contents($bookingsFile), true) ?: []) : [];

$booking = null;
foreach ($bookings as $b) {
    if ($b['id'] === $bookingId) { $booking = $b; break; }
}
if (!$booking) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Reserva no encontrada.']);
    exit;
}

if (($booking['paymentMethod'] ?? '') === 'Payway' && (float)($booking['amountPaid'] ?? 0) > 0) {
    echo json_encode(['ok' => false, 'error' => 'already_paid']);
    exit;
}

// Monto server-side — misma fórmula que pay.php, nunca confiar en el cliente
// (el cliente solo manda token/bin, no un monto).
$config          = is_file(__DIR__ . '/config.json') ? json_decode(file_get_contents(__DIR__ . '/config.json'), true) : [];
$exchangeRateARS = $config['exchangeRateARS'] ?? 1370;

$nights     = max(1, (int)round((strtotime($booking['checkOut']) - strtotime($booking['checkIn'])) / 86400));
$nightlyUSD = (float)$booking['totalPrice'] / $nights;
$nightlyARS = round($nightlyUSD * $exchangeRateARS);
$amountCents = (int)round($nightlyARS * 100);

if ($amountCents < 50) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Monto inválido.']);
    exit;
}

$paymentMethodId = hp_payway_resolve_payment_method_id($bin);
if ($paymentMethodId === null) {
    echo json_encode(['ok' => false, 'error' => 'Tarjeta no reconocida. Probá con otra o pagá directamente en el check-in.']);
    exit;
}

// Alfanumérico, sin guiones ni otros símbolos — la API de Payway lo pide así.
$siteTransactionId = substr(preg_replace('/[^A-Za-z0-9]/', '', $bookingId . bin2hex(random_bytes(4))), 0, 39);

// Nombre/apellido para bill_to — CyberSource los pide separados.
$nameParts = preg_split('/\s+/', trim((string)$booking['guestName']), 2);
$firstName = $nameParts[0] ?? $booking['guestName'];
$lastName  = $nameParts[1] ?? $nameParts[0] ?? '';

$payload = [
    'site_transaction_id' => $siteTransactionId,
    'token'               => $token,
    'customer'            => [
        'id'         => $bookingId,
        'email'      => $booking['email'] ?? '',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
    ],
    'payment_method_id'  => $paymentMethodId,
    'bin'                => $bin,
    'amount'             => $amountCents,
    'currency'           => 'ARS',
    'installments'       => 1,
    'description'        => "Hostel Plaza - {$bookingId}",
    'establishment_name' => 'Hostel Plaza',
    'payment_type'       => 'single',
    'sub_payments'       => [],
    'fraud_detection'    => [
        'send_to_cs' => true,
        'channel'    => 'web',
        'bill_to'    => [
            'first_name'   => $firstName,
            'last_name'    => $lastName,
            'email'        => $booking['email'] ?? '',
            'phone_number' => preg_replace('/[^0-9+]/', '', (string)($booking['phone'] ?? '')),
            'street1'      => $billStreet,
            'city'         => $billCity,
            'state'        => $billState,
            'postal_code'  => $billZip,
            'country'      => $billCountry,
            'customer_id'  => $bookingId,
        ],
        'purchase_totals' => [
            'currency' => 'ARS',
            'amount'   => $amountCents,
        ],
        'customer_in_site' => [
            'is_guest'            => true,
            'days_in_site'        => 0,
            'num_of_transactions' => 1,
        ],
        // No hay envío físico — es un depósito por 1 noche de hostel.
        // "homeDelivery" es el único valor que confirmamos documentado por
        // Payway; si lo rechaza, es el próximo dato a ajustar.
        'retail_transaction_data' => [
            'dispatch_method'  => 'homeDelivery',
            'days_to_delivery' => 0,
            'items'            => [
                [
                    'id'          => 'HOSTEL-1NIGHT',
                    'value'       => $amountCents,
                    'description' => "Hostel Plaza - 1 noche ({$bookingId})",
                    'quantity'    => 1,
                ],
            ],
        ],
    ],
];

$result = hp_payway_charge($payload);

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
file_put_contents(
    $logDir . '/payway.log',
    date('c') . ' ' . ($result['ok'] ? 'OK' : 'ERROR') . " booking={$bookingId} amount_ars={$nightlyARS} payment_method_id={$paymentMethodId}\n"
        . 'request: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n"
        . 'response: ' . json_encode($result['response'], JSON_UNESCAPED_UNICODE) . "\n"
        . ($result['ok'] ? '' : "error: {$result['error']}\n"),
    FILE_APPEND
);

if (!$result['ok']) {
    echo json_encode(['ok' => false, 'error' => $result['error']]);
    exit;
}

// Re-leer antes de escribir (mismo patrón que book.php usa para el sync de BananaDesk)
$bookingsNow = json_decode(file_get_contents($bookingsFile), true) ?: [];
foreach ($bookingsNow as &$bb) {
    if ($bb['id'] === $bookingId) {
        $bb['amountPaid']       = (float)($bb['amountPaid'] ?? 0) + $nightlyUSD;
        $bb['paymentMethod']    = 'Payway';
        $bb['paywayOperationId'] = $result['response']['id'] ?? null;
        break;
    }
}
unset($bb);
file_put_contents($bookingsFile, json_encode($bookingsNow, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode([
    'ok'         => true,
    'operationId'=> $result['response']['id'] ?? null,
    'amountArs'  => $nightlyARS,
]);
