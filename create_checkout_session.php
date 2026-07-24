<?php
/**
 * Crea una Stripe Checkout Session en modo embedded para el depósito de una
 * reserva y devuelve el client_secret que pay.php usa para montar el checkout.
 *
 * GET /create_checkout_session.php?booking=HP-XXXX
 */
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/stripe_lib.php';

$secretsFile = dirname(__DIR__) . '/storagedir/secrets.php';
$secrets = is_file($secretsFile) ? (require $secretsFile) : [];
$stripeSecretKey = $secrets['stripe_secret_key'] ?? '';

if (!$stripeSecretKey) {
    http_response_code(500);
    echo json_encode(['error' => 'Stripe no está configurado.']);
    exit;
}

$bookingId = trim((string)($_GET['booking'] ?? ''));
if ($bookingId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Falta el ID de reserva.']);
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
    echo json_encode(['error' => 'Reserva no encontrada.']);
    exit;
}

// Depósito mínimo: 1 noche (misma fórmula que mail_extranjero.php)
$nights     = max(1, (int)round((strtotime($booking['checkOut']) - strtotime($booking['checkIn'])) / 86400));
$nightlyUSD = round((float)$booking['totalPrice'] / $nights, 2);
$paidUSD    = (float)($booking['amountPaid'] ?? 0);

if ($paidUSD >= $nightlyUSD) {
    http_response_code(400);
    echo json_encode(['error' => 'Esta reserva ya tiene el depósito cubierto.']);
    exit;
}

$amountCents = (int)round($nightlyUSD * 100);
if ($amountCents < 50) { // mínimo de Stripe (~USD 0.50)
    http_response_code(400);
    echo json_encode(['error' => 'Monto inválido.']);
    exit;
}

$origin = (($_SERVER['HTTPS'] ?? '') !== 'off' && !empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

$result = hp_stripe_request('post', 'checkout/sessions', [
    'ui_mode'        => 'embedded_page',
    'mode'           => 'payment',
    'customer_email' => $booking['email'] ?? null,
    'line_items'     => [[
        'quantity'   => 1,
        'price_data' => [
            'currency'     => 'usd',
            'unit_amount'  => $amountCents,
            'product_data' => [
                'name' => "Hostel Plaza — Deposit ({$bookingId})",
            ],
        ],
    ]],
    'metadata'   => ['booking_id' => $bookingId],
    'return_url' => $origin . '/pay.php?booking=' . urlencode($bookingId) . '&session_id={CHECKOUT_SESSION_ID}',
], $stripeSecretKey);

if (!$result['ok']) {
    http_response_code(502);
    echo json_encode(['error' => $result['data']['error']['message'] ?? 'Error de Stripe.']);
    exit;
}

echo json_encode(['clientSecret' => $result['data']['client_secret']]);
