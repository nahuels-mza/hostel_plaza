<?php
/**
 * Página de pago con Payway — el huésped confirma su estadía pagando 1
 * noche con tarjeta, sin salir del sitio. Opcional: la reserva ya existe
 * y es válida sin este pago (se puede pagar en el check-in).
 *
 * URL: /pay.php?booking_id=HP-XXXX
 */
declare(strict_types=1);

require_once __DIR__ . '/payway_lib.php';
require_once __DIR__ . '/mail_booking.php'; // hp_detect_lang()

$guestLang = hp_detect_lang();
$isEs      = $guestLang === 'es';

$config          = is_file(__DIR__ . '/config.json') ? json_decode(file_get_contents(__DIR__ . '/config.json'), true) : [];
$exchangeRateARS = $config['exchangeRateARS'] ?? 1370;

$bookingId = trim((string)($_GET['booking_id'] ?? ''));

$bookingsFile = __DIR__ . '/bookings.json';
$bookings = is_file($bookingsFile) ? (json_decode(file_get_contents($bookingsFile), true) ?: []) : [];
$booking = null;
foreach ($bookings as $b) {
    if ($b['id'] === $bookingId) { $booking = $b; break; }
}

$alreadyPaid = $booking && ($booking['paymentMethod'] ?? '') === 'Payway' && (float)($booking['amountPaid'] ?? 0) > 0;

$nightlyARS = 0;
if ($booking) {
    $nights     = max(1, (int)round((strtotime($booking['checkOut']) - strtotime($booking['checkIn'])) / 86400));
    $nightlyUSD = (float)$booking['totalPrice'] / $nights;
    $nightlyARS = round($nightlyUSD * $exchangeRateARS);
}
$fNightlyARS = 'AR$ ' . number_format($nightlyARS, 0, ',', '.');

$pwPublic = hp_payway_public_config();

$t = $isEs ? [
    'title'        => 'Confirmá tu estadía',
    'notFound'     => 'No encontramos esa reserva. Usá el link de tu mail de confirmación.',
    'alreadyPaid'  => '✓ Esta reserva ya fue confirmada con pago.',
    'guestLabel'   => 'Huésped',
    'amountLabel'  => '1 noche',
    'singleNote'   => 'Pago único, sin cuotas.',
    'cardNumber'   => 'Número de tarjeta',
    'expMonth'     => 'Mes (MM)',
    'expYear'      => 'Año (AA)',
    'cvv'          => 'Código de seguridad',
    'holderName'   => 'Nombre del titular',
    'docType'      => 'Tipo de documento',
    'docNumber'    => 'Número de documento',
    'billingTitle' => 'Dirección de facturación',
    'street'       => 'Calle y número',
    'city'         => 'Ciudad',
    'state'        => 'Provincia',
    'postalCode'   => 'Código postal',
    'submit'       => 'Pagar ' . $fNightlyARS,
    'processing'   => 'Procesando…',
    'unavailable'  => 'El pago online no está disponible en este momento. Podés pagar directamente en el check-in.',
    'successTitle' => '✓ Pago confirmado',
    'successBody'  => '¡Gracias! Tu estadía quedó confirmada.',
    'backHome'     => 'Volver al inicio',
] : [
    'title'        => 'Confirm Your Stay',
    'notFound'     => "We couldn't find that booking. Please use the link from your confirmation email.",
    'alreadyPaid'  => '✓ This booking has already been confirmed with payment.',
    'guestLabel'   => 'Guest',
    'amountLabel'  => '1 night',
    'singleNote'   => 'Single payment, no installments.',
    'cardNumber'   => 'Card number',
    'expMonth'     => 'Exp. month (MM)',
    'expYear'      => 'Exp. year (YY)',
    'cvv'          => 'Security code',
    'holderName'   => 'Cardholder name',
    'docType'      => 'ID type',
    'docNumber'    => 'ID number',
    'billingTitle' => 'Billing address',
    'street'       => 'Street and number',
    'city'         => 'City',
    'state'        => 'State / Province',
    'postalCode'   => 'Postal code',
    'submit'       => 'Pay ' . $fNightlyARS,
    'processing'   => 'Processing…',
    'unavailable'  => 'Online payment is temporarily unavailable. You can pay directly at check-in.',
    'successTitle' => '✓ Payment confirmed',
    'successBody'  => "Thank you! Your stay is now confirmed.",
    'backHome'     => 'Back to home',
];
?>
<!DOCTYPE html>
<html lang="<?php echo $isEs ? 'es' : 'en'; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($t['title']); ?> — Hostel Plaza</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = { theme: { extend: { colors: {
        teal: '#1c5457', 'teal-hover': '#144042', 'teal-light': '#e6f0f0',
    } } } };
</script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4 font-sans">
<div class="w-full max-w-md bg-white rounded-3xl shadow-sm border border-slate-200 p-8">
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

    <?php elseif (!$pwPublic['public_key']): ?>
        <p class="text-center text-amber-700 text-sm"><?php echo htmlspecialchars($t['unavailable']); ?></p>

    <?php else: ?>
        <div class="bg-teal-light rounded-2xl p-4 mb-6">
            <div class="flex justify-between text-sm mb-1">
                <span class="text-teal/70"><?php echo htmlspecialchars($t['guestLabel']); ?></span>
                <span class="font-bold text-teal"><?php echo htmlspecialchars($booking['guestName']); ?></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-teal/70"><?php echo htmlspecialchars($t['amountLabel']); ?></span>
                <span class="font-bold text-teal"><?php echo htmlspecialchars($fNightlyARS); ?></span>
            </div>
            <p class="text-xs text-teal/60 mt-2"><?php echo htmlspecialchars($t['singleNote']); ?></p>
        </div>

        <form id="payForm" class="space-y-3">
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1"><?php echo htmlspecialchars($t['cardNumber']); ?></label>
                <input type="text" inputmode="numeric" autocomplete="cc-number" data-decidir="card_number" required
                       class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-teal">
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1"><?php echo htmlspecialchars($t['expMonth']); ?></label>
                    <input type="text" inputmode="numeric" maxlength="2" placeholder="MM" autocomplete="cc-exp-month" data-decidir="card_expiration_month" required
                           class="w-full border border-slate-200 rounded-xl px-3 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-teal">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1"><?php echo htmlspecialchars($t['expYear']); ?></label>
                    <input type="text" inputmode="numeric" maxlength="2" placeholder="AA" autocomplete="cc-exp-year" data-decidir="card_expiration_year" required
                           class="w-full border border-slate-200 rounded-xl px-3 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-teal">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1"><?php echo htmlspecialchars($t['cvv']); ?></label>
                    <input type="text" inputmode="numeric" maxlength="4" autocomplete="cc-csc" data-decidir="security_code" required
                           class="w-full border border-slate-200 rounded-xl px-3 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-teal">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1"><?php echo htmlspecialchars($t['holderName']); ?></label>
                <input type="text" autocomplete="cc-name" data-decidir="card_holder_name" required
                       value="<?php echo htmlspecialchars($booking['guestName']); ?>"
                       class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-teal">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1"><?php echo htmlspecialchars($t['docType']); ?></label>
                    <input type="text" data-decidir="card_holder_doc_type"
                           value="<?php echo htmlspecialchars($booking['idType'] ?? ''); ?>"
                           class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-teal">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1"><?php echo htmlspecialchars($t['docNumber']); ?></label>
                    <input type="text" data-decidir="card_holder_doc_number"
                           value="<?php echo htmlspecialchars($booking['idNumber'] ?? ''); ?>"
                           class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-teal">
                </div>
            </div>

            <div class="pt-1">
                <p class="text-xs font-bold text-slate-500 mb-2"><?php echo htmlspecialchars($t['billingTitle']); ?></p>
                <div class="space-y-3">
                    <input type="text" id="billStreet" autocomplete="address-line1" required
                           placeholder="<?php echo htmlspecialchars($t['street']); ?>"
                           class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-teal">
                    <div class="grid grid-cols-3 gap-3">
                        <input type="text" id="billCity" autocomplete="address-level2" required
                               placeholder="<?php echo htmlspecialchars($t['city']); ?>"
                               class="col-span-1 w-full border border-slate-200 rounded-xl px-3 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-teal">
                        <input type="text" id="billState" autocomplete="address-level1" required
                               placeholder="<?php echo htmlspecialchars($t['state']); ?>"
                               class="col-span-1 w-full border border-slate-200 rounded-xl px-3 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-teal">
                        <input type="text" id="billPostalCode" autocomplete="postal-code" required
                               placeholder="<?php echo htmlspecialchars($t['postalCode']); ?>"
                               class="col-span-1 w-full border border-slate-200 rounded-xl px-3 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-teal">
                    </div>
                </div>
            </div>

            <div id="payError" class="hidden text-red-600 text-xs bg-red-50 border border-red-200 rounded-xl p-3"></div>

            <button type="submit" id="paySubmit"
                    class="w-full bg-teal hover:bg-teal-hover text-white font-bold py-3.5 rounded-xl transition-all mt-2">
                <?php echo htmlspecialchars($t['submit']); ?>
            </button>
        </form>

        <div id="paySuccess" class="hidden text-center">
            <p class="text-emerald-600 text-lg font-bold mb-2"><?php echo htmlspecialchars($t['successTitle']); ?></p>
            <p class="text-slate-600 text-sm mb-4"><?php echo htmlspecialchars($t['successBody']); ?></p>
            <a href="/" class="inline-block px-6 py-3 bg-teal text-white rounded-xl font-bold hover:bg-teal-hover transition-all"><?php echo htmlspecialchars($t['backHome']); ?></a>
        </div>

        <script src="https://ventasonline.payway.com.ar/static/v2.6.4/decidir.js"></script>
        <script>
        (function () {
            const bookingId = <?php echo json_encode($bookingId); ?>;
            const decidir = new Decidir(<?php echo json_encode($pwPublic['sdk_base_url']); ?>);
            decidir.setPublishableKey(<?php echo json_encode($pwPublic['public_key']); ?>);
            decidir.setTimeout(5000);

            const form      = document.getElementById('payForm');
            const submitBtn = document.getElementById('paySubmit');
            const errorBox  = document.getElementById('payError');
            const submitLabel = submitBtn.textContent;

            function showError(msg) {
                errorBox.textContent = msg;
                errorBox.classList.remove('hidden');
            }

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                errorBox.classList.add('hidden');
                submitBtn.disabled = true;
                submitBtn.textContent = <?php echo json_encode($t['processing']); ?>;

                const cardNumberEl = form.querySelector('[data-decidir="card_number"]');
                const bin = cardNumberEl.value.replace(/\D/g, '').slice(0, 6);

                decidir.createToken(form, function (status, response) {
                    if (status === 200 || status === 201) {
                        // La doc de Payway dice "response.token", pero algunos ejemplos
                        // reales de su propio SDK devuelven "response.id" en su lugar —
                        // aceptamos cualquiera de los dos.
                        const tokenValue = response && (response.token || response.id);
                        if (!tokenValue) {
                            showError('No se pudo tokenizar la tarjeta (respuesta inesperada del SDK).');
                            submitBtn.disabled = false;
                            submitBtn.textContent = submitLabel;
                            console.warn('Decidir createToken response sin token/id:', response);
                            return;
                        }
                        fetch('payway_charge.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                booking_id: bookingId, token: tokenValue, bin: bin,
                                billing: {
                                    street: document.getElementById('billStreet').value,
                                    city: document.getElementById('billCity').value,
                                    state: document.getElementById('billState').value,
                                    postal_code: document.getElementById('billPostalCode').value,
                                },
                            }),
                        })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.ok) {
                                form.classList.add('hidden');
                                document.getElementById('paySuccess').classList.remove('hidden');
                            } else {
                                showError(data.error || 'Error');
                                submitBtn.disabled = false;
                                submitBtn.textContent = submitLabel;
                            }
                        })
                        .catch(function () {
                            showError('Network error');
                            submitBtn.disabled = false;
                            submitBtn.textContent = submitLabel;
                        });
                    } else {
                        const msgs = (response && response.length) ? response.map(function (e) { return e.message; }).join(' ') : 'Card validation error';
                        showError(msgs);
                        submitBtn.disabled = false;
                        submitBtn.textContent = submitLabel;
                    }
                });
            });
        })();
        </script>
    <?php endif; ?>
</div>
</body>
</html>
