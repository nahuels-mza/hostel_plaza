<?php
/**
 * Captura (cobra) una orden de PayPal ya aprobada por el pagador, para el
 * total de la estadía. Se llama desde pay.php en el callback onApprove del
 * botón de PayPal.
 *
 * POST JSON: {booking_id, order_id}
 * → {ok:true, amountUsd} | {ok:false, error}
 */
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/paypal_lib.php';

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) $in = [];

$bookingId = trim((string)($in['booking_id'] ?? ''));
$orderId   = trim((string)($in['order_id'] ?? ''));

if ($bookingId === '' || $orderId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Datos incompletos.']);
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
if ((float)($booking['amountPaid'] ?? 0) >= $totalUsd) {
    echo json_encode(['ok' => false, 'error' => 'already_paid']);
    exit;
}

$result = hp_paypal_capture_order($orderId);

hp_write_log('paypal',
    ($result['ok'] ? 'OK' : 'ERROR') . " booking={$bookingId} order={$orderId}\n"
        . 'Client:   ' . hp_client_info() . "\n"
        . 'Response: ' . json_encode($result['response'], JSON_UNESCAPED_UNICODE) . "\n"
        . ($result['ok'] ? '' : "Error:    {$result['error']}\n")
        . str_repeat('=', 60)
);

if (!$result['ok']) {
    echo json_encode(['ok' => false, 'error' => $result['error']]);
    exit;
}

// Nunca confiar en el monto del cliente: lo tomamos de la captura que PayPal
// confirma en su respuesta, y verificamos que coincida con lo esperado.
$capture = $result['response']['purchase_units'][0]['payments']['captures'][0] ?? null;
$capturedUsd = (float)($capture['amount']['value'] ?? 0);
$captureId   = $capture['id'] ?? null;

if ($capturedUsd <= 0 || abs($capturedUsd - $totalUsd) > 0.01) {
    file_put_contents(
        $logDir . '/paypal.log',
        date('c') . " MISMATCH booking={$bookingId} expected={$totalUsd} captured={$capturedUsd}\n",
        FILE_APPEND
    );
    echo json_encode(['ok' => false, 'error' => 'El monto capturado no coincide con el esperado.']);
    exit;
}

// Re-leer antes de escribir (mismo patrón que book.php usa para el sync de BananaDesk)
$bookingsNow = json_decode(file_get_contents($bookingsFile), true) ?: [];
foreach ($bookingsNow as &$bb) {
    if ($bb['id'] === $bookingId) {
        $bb['amountPaid']        = (float)($bb['amountPaid'] ?? 0) + $capturedUsd;
        $bb['paymentMethod']     = 'PayPal';
        $bb['paypalCaptureId']   = $captureId;
        $bb['paypalOrderId']     = $orderId;
        break;
    }
}
unset($bb);
file_put_contents($bookingsFile, json_encode($bookingsNow, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode(['ok' => true, 'amountUsd' => $capturedUsd]);
