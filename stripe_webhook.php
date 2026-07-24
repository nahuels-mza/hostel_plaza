<?php
/**
 * Webhook de Stripe — marca la reserva como pagada en bookings.json cuando
 * el checkout embebido se completa.
 *
 * Configurar en Dashboard → Developers → Webhooks:
 *   URL:    https://hostelplaza.com.ar/stripe_webhook.php
 *   Evento: checkout.session.completed
 */
declare(strict_types=1);

require_once __DIR__ . '/stripe_lib.php';

$secretsFile = dirname(__DIR__) . '/storagedir/secrets.php';
$secrets = is_file($secretsFile) ? (require $secretsFile) : [];
$webhookSecret = $secrets['stripe_webhook_secret'] ?? '';

$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (!$webhookSecret || !hp_stripe_verify_signature($payload, $sigHeader, $webhookSecret)) {
    http_response_code(400);
    exit('Invalid signature');
}

$event = json_decode($payload, true);
if (!is_array($event)) {
    http_response_code(400);
    exit('Invalid payload');
}

if (($event['type'] ?? '') === 'checkout.session.completed') {
    $session   = $event['data']['object'] ?? [];
    $bookingId = $session['metadata']['booking_id'] ?? '';
    $amountUSD = ((int)($session['amount_total'] ?? 0)) / 100;

    if ($bookingId !== '' && $amountUSD > 0) {
        $bookingsFile = __DIR__ . '/bookings.json';
        $bookings = is_file($bookingsFile) ? (json_decode(file_get_contents($bookingsFile), true) ?: []) : [];

        foreach ($bookings as &$b) {
            if ($b['id'] === $bookingId) {
                $b['amountPaid']    = (float)($b['amountPaid'] ?? 0) + $amountUSD;
                $b['paymentMethod'] = 'Stripe';
                break;
            }
        }
        unset($b);

        file_put_contents($bookingsFile, json_encode($bookings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        file_put_contents(
            __DIR__ . '/mail_debug.log',
            date('c') . " STRIPE payment received booking={$bookingId} amount=\${$amountUSD}\n",
            FILE_APPEND
        );
    }
}

http_response_code(200);
echo 'ok';
