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
    'ui_mode'              => 'embedded_page',
    'mode'                 => 'payment',
    'payment_method_types' => ['card'],
    'customer_email'       => $booking['email'] ?? null,
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
    $fallbackSent = false;

    // Intentar enviar email de fallback con datos de transferencia bancaria
    $pathException = __DIR__ . '/PHPMailer-master/src/Exception.php';
    $pathPHPMailer = __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
    $pathSMTP      = __DIR__ . '/PHPMailer-master/src/SMTP.php';

    if (
        file_exists($pathException) && file_exists($pathPHPMailer) && file_exists($pathSMTP)
        && !empty($booking['email'])
    ) {
        require_once $pathException;
        require_once $pathPHPMailer;
        require_once $pathSMTP;
        require_once __DIR__ . '/mail_extranjero.php';

        // Buscar nombre y metadatos de la habitación
        $rooms = is_file(__DIR__ . '/rooms.json')
            ? (json_decode(file_get_contents(__DIR__ . '/rooms.json'), true) ?: [])
            : [];
        $roomName = '';
        $roomMeta = null;
        foreach ($rooms as $r) {
            if ((string)$r['id'] === (string)($booking['roomId'] ?? '')) {
                $roomName = $r['name'];
                $roomMeta = $r;
                break;
            }
        }

        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->CharSet = PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
            $mail->isSMTP();
            $mail->Host       = 'c2721166.ferozo.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'confirmation@hostelplaza.com.ar';
            $mail->Password   = 'ThHQ*RW5hG';
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
            $mail->Timeout    = 15;
            $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

            $mail->setFrom('confirmation@hostelplaza.com.ar', 'Hostel Plaza');
            $mail->addAddress($booking['email'], $booking['guestName'] ?? '');
            $mail->addBCC('hostelplazamza@gmail.com');
            $mail->addReplyTo('info@hostelplaza.com.ar', 'Hostel Plaza Info');
            $mail->isHTML(true);

            [$subject, $body, $altBody] = hp_mail_extranjero(
                $booking, $roomName, (float)$booking['totalPrice'], $nights
            );
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = $altBody;
            $mail->send();
            $fallbackSent = true;

            file_put_contents(
                __DIR__ . '/mail_debug.log',
                date('c') . " FALLBACK_MAIL OK booking={$bookingId} to={$booking['email']} (stripe_error)\n",
                FILE_APPEND
            );
        } catch (\Exception $me) {
            file_put_contents(
                __DIR__ . '/mail_debug.log',
                date('c') . " FALLBACK_MAIL ERROR booking={$bookingId}: " . $me->getMessage() . "\n",
                FILE_APPEND
            );
        }

        // --- Crear reserva en BananaDesk aunque Stripe haya fallado ---
        // Solo si no fue sincronizado previamente (evita duplicados en reintentos).
        if (empty($booking['bananadesk']['synced'])) {
            $mapPath      = __DIR__ . '/room_mapping.json';
            $roomMap      = is_file($mapPath) ? (json_decode(file_get_contents($mapPath), true) ?: []) : [];
            $bdRoomTypeId = (int)($roomMap[$booking['roomId'] ?? ''] ?? 0);
            $bdBookingUnit = $roomMeta['bookingUnit'] ?? 'room';

            if ($bdRoomTypeId > 0) {
                require_once __DIR__ . '/bananadesk_reserve.php';
                $bdResult = hp_bananadesk_reserve(
                    $booking['checkIn'],
                    $booking['checkOut'],
                    $bdRoomTypeId,
                    $booking['guestName'],
                    $booking['email'],
                    $booking['phone'],
                    (int)($booking['guestsCount'] ?? 1),
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
                    date('c') . " BANANADESK " . ($bdResult['ok'] ? 'OK' : 'ERROR') . " booking={$bookingId} (stripe_fallback)\n",
                    FILE_APPEND
                );
            }
        }
    }

    http_response_code(502);
    echo json_encode([
        'error'               => $result['data']['error']['message'] ?? 'Error de Stripe.',
        'fallback_email_sent' => $fallbackSent,
    ]);
    exit;
}

echo json_encode(['clientSecret' => $result['data']['client_secret']]);
