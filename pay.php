<?php
/**
 * Página de pago con PayPal — el huésped confirma su estadía pagando el
 * total con tarjeta o cuenta de PayPal, sin salir del sitio. Opcional: la
 * reserva ya existe y es válida sin este pago (se puede pagar en el check-in).
 *
 * URL: /pay.php?booking_id=HP-XXXX
 *
 * Nota: hay una integración de Payway/Decidir (payway_lib.php,
 * payway_charge.php) que quedó sin usar acá — su cuenta exige un flujo de
 * antifraude de CyberSource (device fingerprint) que no pudimos completar
 * del todo lado cliente/servidor. Queda el código por si soporte de Payway
 * lo resuelve más adelante.
 */
declare(strict_types=1);

require_once __DIR__ . '/paypal_lib.php';
require_once __DIR__ . '/mail_booking.php'; // hp_detect_lang()

$guestLang = hp_detect_lang();
$isEs      = $guestLang === 'es';

$bookingId = trim((string)($_GET['booking_id'] ?? ''));

$bookingsFile = __DIR__ . '/bookings.json';
$bookings = is_file($bookingsFile) ? (json_decode(file_get_contents($bookingsFile), true) ?: []) : [];
$booking = null;
foreach ($bookings as $b) {
    if ($b['id'] === $bookingId) { $booking = $b; break; }
}

$totalUsd = $booking ? (float)$booking['totalPrice'] : 0;
$paidUsd  = $booking ? (float)($booking['amountPaid'] ?? 0) : 0;
$alreadyPaid = $booking && $paidUsd >= $totalUsd && $totalUsd > 0;
$fTotalUsd = '$' . number_format($totalUsd, 2) . ' USD';

$ppPublic = hp_paypal_public_config();

$t = $isEs ? [
    'title'        => 'Confirmá tu reserva',
    'notFound'     => 'No encontramos esa reserva. Usá el link de tu mail de confirmación.',
    'alreadyPaid'  => '✓ Esta reserva ya fue pagada por completo.',
    'guestLabel'   => 'Huésped',
    'amountLabel'  => 'Total de la reserva',
    'singleNote'   => 'Pago único, en dólares, con tarjeta o cuenta de PayPal.',
    'unavailable'  => 'El pago online no está disponible en este momento. Podés pagar directamente en el check-in.',
    'successTitle' => '✓ Pago confirmado',
    'successBody'  => '¡Gracias! Tu reserva quedó pagada por completo.',
    'backHome'     => 'Volver al inicio',
    'error'        => 'No pudimos procesar el pago. Podés intentar de nuevo o pagar directamente en el check-in.',
] : [
    'title'        => 'Confirm Your Booking',
    'notFound'     => "We couldn't find that booking. Please use the link from your confirmation email.",
    'alreadyPaid'  => '✓ This booking has already been paid in full.',
    'guestLabel'   => 'Guest',
    'amountLabel'  => 'Booking total',
    'singleNote'   => 'One-time payment, in USD, by card or PayPal account.',
    'unavailable'  => 'Online payment is temporarily unavailable. You can pay directly at check-in.',
    'successTitle' => '✓ Payment confirmed',
    'successBody'  => 'Thank you! Your booking is now paid in full.',
    'backHome'     => 'Back to home',
    'error'        => "We couldn't process the payment. You can try again or pay directly at check-in.",
];
?>
<!DOCTYPE html>
<html lang="<?php echo $isEs ? 'es' : 'en'; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($t['title']); ?> — Hostel Plaza</title>
<link rel="icon" href="/iconwhite.ico" sizes="any">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<script>
    tailwind.config = { theme: { extend: { colors: {
        teal: '#1c5457', 'teal-hover': '#144042', 'teal-light': '#e6f0f0',
    } } } };
</script>
</head>
<body class="bg-slate-50 min-h-screen flex flex-col items-center p-4 font-sans">

<?php $hasHero = false; include __DIR__ . '/header.php'; ?>

<div class="flex-1 w-full flex items-center justify-center pt-20">
<div class="notranslate w-full max-w-md bg-white rounded-3xl shadow-sm border border-slate-200 p-8">
    <div class="text-center mb-6">
        <img src="H.png" alt="Hostel Plaza" style="height:44px;width:auto;" class="mx-auto mb-3">
        <h1 class="text-xl font-bold text-slate-900"><?php echo htmlspecialchars($t['title']); ?></h1>
    </div>

    <?php if (!$booking): ?>
        <p class="text-center text-red-600 text-sm"><?php echo htmlspecialchars($t['notFound']); ?></p>

    <?php elseif ($alreadyPaid): ?>
        <div class="text-center">
            <p class="text-emerald-600 font-bold mb-4"><?php echo htmlspecialchars($t['alreadyPaid']); ?></p>
            <a href="/" class="inline-block px-6 py-3 bg-teal text-white rounded-xl font-bold hover:bg-teal-hover transition-all"><?php echo htmlspecialchars($t['backHome']); ?></a>
        </div>

    <?php elseif (!$ppPublic['client_id']): ?>
        <p class="text-center text-amber-700 text-sm"><?php echo htmlspecialchars($t['unavailable']); ?></p>

    <?php else: ?>
        <div class="bg-teal-light rounded-2xl p-4 mb-6">
            <div class="flex justify-between text-sm mb-1">
                <span class="text-teal/70"><?php echo htmlspecialchars($t['guestLabel']); ?></span>
                <span class="font-bold text-teal"><?php echo htmlspecialchars($booking['guestName']); ?></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-teal/70"><?php echo htmlspecialchars($t['amountLabel']); ?></span>
                <span class="font-bold text-teal"><?php echo htmlspecialchars($fTotalUsd); ?></span>
            </div>
            <p class="text-xs text-teal/60 mt-2"><?php echo htmlspecialchars($t['singleNote']); ?></p>
        </div>

        <div id="paypal-button-container"></div>
        <div id="payError" class="hidden text-red-600 text-xs bg-red-50 border border-red-200 rounded-xl p-3 mt-3"></div>

        <div id="paySuccess" class="hidden text-center">
            <p class="text-emerald-600 text-lg font-bold mb-2"><?php echo htmlspecialchars($t['successTitle']); ?></p>
            <p class="text-slate-600 text-sm mb-4"><?php echo htmlspecialchars($t['successBody']); ?></p>
            <a href="/" class="inline-block px-6 py-3 bg-teal text-white rounded-xl font-bold hover:bg-teal-hover transition-all"><?php echo htmlspecialchars($t['backHome']); ?></a>
        </div>

        <script src="https://www.paypal.com/sdk/js?client-id=<?php echo urlencode($ppPublic['client_id']); ?>&currency=USD&intent=capture"></script>
        <script>
        (function () {
            const bookingId = <?php echo json_encode($bookingId); ?>;
            const errorBox  = document.getElementById('payError');

            function showError(msg) {
                errorBox.textContent = msg;
                errorBox.classList.remove('hidden');
            }

            if (typeof paypal === 'undefined') {
                showError(<?php echo json_encode($t['unavailable']); ?>);
                return;
            }

            paypal.Buttons({
                style: { layout: 'vertical', color: 'blue', shape: 'rect', label: 'pay' },
                createOrder: function () {
                    errorBox.classList.add('hidden');
                    return fetch('paypal_create_order.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ booking_id: bookingId }),
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data.ok) throw new Error(data.error || 'Error');
                        return data.orderId;
                    })
                    .catch(function (err) {
                        showError(err.message || <?php echo json_encode($t['error']); ?>);
                        throw err;
                    });
                },
                onApprove: function (data) {
                    return fetch('paypal_capture_order.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ booking_id: bookingId, order_id: data.orderID }),
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (result) {
                        if (!result.ok) { showError(result.error || <?php echo json_encode($t['error']); ?>); return; }
                        document.getElementById('paypal-button-container').classList.add('hidden');
                        document.getElementById('paySuccess').classList.remove('hidden');
                    })
                    .catch(function () {
                        showError(<?php echo json_encode($t['error']); ?>);
                    });
                },
                onError: function () {
                    showError(<?php echo json_encode($t['error']); ?>);
                },
            }).render('#paypal-button-container');
        })();
        </script>
    <?php endif; ?>
</div>
</div>

<script>lucide.createIcons();</script>
</body>
</html>
