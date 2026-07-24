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
    $stripeSessionId = $session['id'] ?? '';
    $amountUSD = ((int)($session['amount_total'] ?? 0)) / 100;

    if ($bookingId !== '' && $stripeSessionId !== '' && $amountUSD > 0) {
        $bookingsFile = __DIR__ . '/bookings.json';
        $bookings = is_file($bookingsFile) ? (json_decode(file_get_contents($bookingsFile), true) ?: []) : [];

        $updatedBooking = null;
        foreach ($bookings as &$b) {
            if ($b['id'] === $bookingId) {
                // Stripe reintenta la entrega del webhook — no duplicar si ya
                // procesamos esta misma sesión.
                if (($b['stripeSessionId'] ?? '') !== $stripeSessionId) {
                    $b['amountPaid']      = (float)($b['amountPaid'] ?? 0) + $amountUSD;
                    $b['paymentMethod']   = 'Stripe';
                    $b['stripeSessionId'] = $stripeSessionId;
                    $updatedBooking = $b;
                }
                break;
            }
        }
        unset($b);

        if ($updatedBooking) {
            file_put_contents($bookingsFile, json_encode($bookings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            file_put_contents(
                __DIR__ . '/mail_debug.log',
                date('c') . " STRIPE payment received booking={$bookingId} amount=\${$amountUSD}\n",
                FILE_APPEND
            );

            // Room lookup (compartido entre mail y BananaDesk)
            $roomsFile = __DIR__ . '/rooms.json';
            $rooms = is_file($roomsFile) ? (json_decode(file_get_contents($roomsFile), true) ?: []) : [];
            $roomName = '';
            $roomMeta = null;
            foreach ($rooms as $r) {
                if ((string)($r['id'] ?? '') === (string)($updatedBooking['roomId'] ?? '')) {
                    $roomName = $r['name'];
                    $roomMeta = $r;
                    break;
                }
            }

            $nights = max(1, (int)round(
                (strtotime($updatedBooking['checkOut']) - strtotime($updatedBooking['checkIn'])) / 86400
            ));

            // Mail de confirmación (sin datos de pago, ya está pagado)
            require_once __DIR__ . '/send_mail.php';
            require_once __DIR__ . '/mail_extranjero.php';
            [$mailSubject, $mailBody, $mailAlt] = hp_mail_extranjero_confirmed(
                $updatedBooking, $roomName, (float)$updatedBooking['totalPrice'], $nights, (float)$updatedBooking['amountPaid']
            );
            hp_send_mail(
                $updatedBooking['email'], $updatedBooking['guestName'],
                $mailSubject, $mailBody, $mailAlt,
                "booking={$bookingId}"
            );

            // --- Crear reserva en BananaDesk ahora que el pago se confirmó ---
            if (empty($updatedBooking['bananadesk']['synced'])) {
                $mapPath      = __DIR__ . '/room_mapping.json';
                $roomMap      = is_file($mapPath) ? (json_decode(file_get_contents($mapPath), true) ?: []) : [];
                $bdRoomTypeId = (int)($roomMap[$updatedBooking['roomId'] ?? ''] ?? 0);
                $bdBookingUnit = $roomMeta['bookingUnit'] ?? 'room';

                if ($bdRoomTypeId > 0) {
                    require_once __DIR__ . '/bananadesk_reserve.php';
                    $bdResult = hp_bananadesk_reserve(
                        $updatedBooking['checkIn'],
                        $updatedBooking['checkOut'],
                        $bdRoomTypeId,
                        $updatedBooking['guestName'],
                        $updatedBooking['email'],
                        $updatedBooking['phone'],
                        (int)($updatedBooking['guestsCount'] ?? 1),
                        $bdBookingUnit
                    );

                    $bookingsNow = json_decode(file_get_contents($bookingsFile), true) ?: [];
                    foreach ($bookingsNow as &$bb) {
                        if ($bb['id'] === $bookingId) {
                            $bb['bananadesk'] = $bdResult['ok']
                                ? ['synced' => true,  'response' => $bdResult['response']]
                                : ['synced' => false, 'error'    => $bdResult['error']];
                            break;
                        }
                    }
                    unset($bb);
                    file_put_contents($bookingsFile, json_encode($bookingsNow, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                    file_put_contents(
                        __DIR__ . '/mail_debug.log',
                        date('c') . " BANANADESK " . ($bdResult['ok'] ? 'OK' : 'ERROR') . " booking={$bookingId} (post-stripe)\n",
                        FILE_APPEND
                    );
                }
            }
        }
    }
}

http_response_code(200);
echo 'ok';
