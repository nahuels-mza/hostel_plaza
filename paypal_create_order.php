<?php
/**
 * Crea una orden de PayPal por el total de una reserva existente (estadía
 * completa, no solo 1 noche). El botón de PayPal en pay.php llama a esto
 * para obtener el orderID que necesita para abrir el checkout.
 *
 * POST JSON: {booking_id}
 * → {ok:true, orderId} | {ok:false, error}
 */
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/paypal_lib.php';

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) $in = [];

$bookingId = trim((string)($in['booking_id'] ?? ''));
if ($bookingId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Falta el ID de reserva.']);
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

$totalUsd = (float)$booking['totalPrice'];
$paidUsd  = (float)($booking['amountPaid'] ?? 0);

if ($totalUsd <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Monto inválido.']);
    exit;
}
if ($paidUsd >= $totalUsd) {
    echo json_encode(['ok' => false, 'error' => 'already_paid']);
    exit;
}

$result = hp_paypal_create_order($bookingId, $totalUsd, "Hostel Plaza — {$bookingId}");

if (!$result['ok']) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $result['error']]);
    exit;
}

echo json_encode(['ok' => true, 'orderId' => $result['orderId']]);
