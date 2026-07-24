<?php
/**
 * Página de pago con Stripe Embedded Checkout — el huésped completa el
 * depósito de su reserva sin salir del sitio.
 *
 * URL: /pay.php?booking=HP-XXXX
 * Retorno de Stripe: /pay.php?booking=HP-XXXX&session_id=cs_...
 */
declare(strict_types=1);

require_once __DIR__ . '/stripe_lib.php';

$secretsFile = dirname(__DIR__) . '/storagedir/secrets.php';
$secrets = is_file($secretsFile) ? (require $secretsFile) : [];
$stripePublishableKey = $secrets['stripe_publishable_key'] ?? '';
$stripeSecretKey      = $secrets['stripe_secret_key'] ?? '';

$bookingId = trim((string)($_GET['booking'] ?? ''));
$sessionId = trim((string)($_GET['session_id'] ?? ''));

$bookingsFile = __DIR__ . '/bookings.json';
$bookings = is_file($bookingsFile) ? (json_decode(file_get_contents($bookingsFile), true) ?: []) : [];
$booking = null;
foreach ($bookings as $b) {
    if ($b['id'] === $bookingId) { $booking = $b; break; }
}

// Si volvemos de Stripe con un session_id, confirmamos el estado server-side
// (no confiamos en el query param solo).
$paymentStatus = null; // 'complete' | 'open' | 'expired' | null
if ($sessionId !== '' && $stripeSecretKey) {
    $result = hp_stripe_request('get', "checkout/sessions/{$sessionId}", [], $stripeSecretKey);
    if ($result['ok']) {
        $paymentStatus = $result['data']['status'] ?? null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Complete Payment — Hostel Plaza</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-lg bg-white rounded-2xl shadow-lg p-6 sm:p-8">
    <div class="text-center mb-6">
        <img src="H.png" alt="Hostel Plaza" style="height:48px;width:auto;" class="mx-auto mb-3">
        <h1 class="text-xl font-bold text-[#1c5457]">Complete Your Payment</h1>
        <?php if ($booking): ?>
            <p class="text-sm text-slate-500 mt-1">Booking <?php echo htmlspecialchars($bookingId); ?> — <?php echo htmlspecialchars($booking['guestName'] ?? ''); ?></p>
        <?php endif; ?>
    </div>

    <?php if (!$booking): ?>
        <p class="text-center text-red-600">We couldn't find that booking. Please use the link from your confirmation email.</p>

    <?php elseif ($paymentStatus === 'complete'): ?>
        <div class="text-center">
            <p class="text-emerald-600 text-lg font-bold mb-2">✓ Payment received</p>
            <p class="text-slate-600">Thank you! Your deposit has been confirmed. A confirmation email is on its way. See you soon at Hostel Plaza!</p>
        </div>

    <?php elseif (!$stripePublishableKey || !$stripeSecretKey): ?>
        <p class="text-center text-red-600">Online payment is temporarily unavailable. Please contact us on WhatsApp to arrange payment.</p>

    <?php else: ?>
        <div id="checkout">
            <p class="text-center text-slate-400 text-sm">Loading payment form…</p>
        </div>
        <script src="https://js.stripe.com/v3/"></script>
        <script>
        (async function () {
            const stripe = Stripe(<?php echo json_encode($stripePublishableKey); ?>);
            const bookingId = <?php echo json_encode($bookingId); ?>;

            const checkout = await stripe.initEmbeddedCheckout({
                fetchClientSecret: async () => {
                    const res  = await fetch('/create_checkout_session.php?booking=' + encodeURIComponent(bookingId));
                    const data = await res.json();
                    if (data.error) throw new Error(data.error);
                    return data.clientSecret;
                },
            });

            document.getElementById('checkout').innerHTML = '';
            checkout.mount('#checkout');
        })().catch((err) => {
            document.getElementById('checkout').innerHTML =
                '<p class="text-center text-red-600">' + err.message + '</p>';
        });
        </script>
    <?php endif; ?>

    <p class="text-center text-xs text-slate-400 mt-6">Questions? <a href="https://api.whatsapp.com/send/?phone=5492615372767" class="text-[#1c5457] font-bold">WhatsApp us</a></p>
</div>
</body>
</html>
