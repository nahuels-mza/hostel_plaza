<?php
/**
 * Mail template for international (non-Argentine) guests.
 *
 * Payment link is a placeholder — replace HP_PAYMENT_LINK with the real URL
 * once the payment gateway is set up.
 *
 * Usage:
 *   require_once __DIR__ . '/mail_extranjero.php';
 *   [$subject, $body, $altBody] = hp_mail_extranjero($booking, $roomName, $totalUSD, $nights);
 */

// ── Payment link ─────────────────────────────────────────────────────────────
// TODO: replace with the real payment gateway URL when ready.
define('HP_PAYMENT_LINK', '');   // e.g. 'https://pay.example.com/hostelplaza'
// ────────────────────────────────────────────────────────────────────────────

// ── US bank account (international wire transfer) ───────────────────────────
define('HP_US_ACCOUNT_HOLDER', 'Nahuel Eduardo Calderon');
define('HP_US_ACCOUNT_NUMBER', '8332195480');
define('HP_US_ACCOUNT_TYPE',   'Checking');
define('HP_US_ROUTING_NUMBER', '026073150');
define('HP_US_BANK_NAME',      'Community Federal Savings Bank');
define('HP_US_BANK_ADDRESS',   '5 Penn Plaza, 14th Floor, New York, NY 10001, US');
// ────────────────────────────────────────────────────────────────────────────

/**
 * @param array  $b        Booking array
 * @param string $roomName Booked room name
 * @param float  $totalUSD Total price in USD
 * @param int    $nights   Number of nights
 * @return array [subject, body, altBody]
 */
function hp_mail_extranjero(array $b, string $roomName, float $totalUSD, int $nights): array
{
    $nights      = max(1, $nights);
    $nightlyUSD  = $nights > 0 ? round($totalUSD / $nights, 2) : $totalUSD;
    $fTotal      = '$' . number_format($totalUSD,   2) . ' USD';
    $fNightly    = '$' . number_format($nightlyUSD, 2) . ' USD';

    $paymentSection = HP_PAYMENT_LINK
        ? "<div style='text-align:center;margin:28px 0;'>
               <a href='" . HP_PAYMENT_LINK . "'
                  style='display:inline-block;background:#1c5457;color:#fff;font-weight:bold;padding:14px 32px;border-radius:10px;text-decoration:none;font-size:16px;'>
                  Complete Payment →
               </a>
               <p style='font-size:12px;color:#94a3b8;margin-top:8px;'>Secure payment · {$fNightly} (1 night deposit)</p>
           </div>"
        : "<div style='background:#f0faf9;border:1.5px solid #1c5457;border-radius:10px;padding:22px;margin:24px 0;'>
               <h3 style='color:#1c5457;margin:0 0 14px;font-size:17px;'>🏦 Bank Transfer Details</h3>
               <table style='width:100%;border-collapse:collapse;'>
                   <tr><td style='padding:6px 0;color:#64748b;width:45%;'>Account Holder Name</td> <td style='padding:6px 0;font-weight:bold;text-align:right;'>" . HP_US_ACCOUNT_HOLDER . "</td></tr>
                   <tr><td style='padding:6px 0;color:#64748b;'>Account Number</td>                 <td style='padding:6px 0;font-weight:bold;font-family:monospace;text-align:right;'>" . HP_US_ACCOUNT_NUMBER . "</td></tr>
                   <tr><td style='padding:6px 0;color:#64748b;'>Account Type</td>                   <td style='padding:6px 0;font-weight:bold;text-align:right;'>" . HP_US_ACCOUNT_TYPE . "</td></tr>
                   <tr><td style='padding:6px 0;color:#64748b;'>ACH Routing Number</td>              <td style='padding:6px 0;font-weight:bold;font-family:monospace;text-align:right;'>" . HP_US_ROUTING_NUMBER . "</td></tr>
                   <tr><td style='padding:6px 0;color:#64748b;'>Bank Name</td>                      <td style='padding:6px 0;font-weight:bold;text-align:right;'>" . HP_US_BANK_NAME . "</td></tr>
                   <tr><td style='padding:6px 0;color:#64748b;vertical-align:top;'>Bank Address</td> <td style='padding:6px 0;font-weight:bold;text-align:right;'>" . HP_US_BANK_ADDRESS . "</td></tr>
                   <tr><td style='padding:10px 0 0;color:#64748b;font-weight:bold;'>Minimum amount</td><td style='padding:10px 0 0;font-weight:bold;color:#1c5457;font-size:16px;text-align:right;'>{$fNightly} <span style='font-size:12px;color:#94a3b8;'>(1 night)</span></td></tr>
               </table>
               <p style='margin:14px 0 0;font-size:13px;color:#64748b;'>Once you've sent the transfer, please share the confirmation with us via WhatsApp.</p>
           </div>";

    $paymentAltText = HP_PAYMENT_LINK
        ? "To confirm your booking, please complete a payment of {$fNightly} (1 night deposit):\n" . HP_PAYMENT_LINK . "\n\n"
        : "BANK TRANSFER DETAILS\n"
            . "Account Holder Name: " . HP_US_ACCOUNT_HOLDER . "\n"
            . "Account Number:      " . HP_US_ACCOUNT_NUMBER . "\n"
            . "Account Type:        " . HP_US_ACCOUNT_TYPE . "\n"
            . "ACH Routing Number:  " . HP_US_ROUTING_NUMBER . "\n"
            . "Bank Name:           " . HP_US_BANK_NAME . "\n"
            . "Bank Address:        " . HP_US_BANK_ADDRESS . "\n"
            . "Minimum amount:      {$fNightly} (1 night deposit)\n\n"
            . "Once you've sent the transfer, please share the confirmation with us via WhatsApp.\n\n";

    $subject = 'PreBooking Received  Hostel Plaza Mendoza';

    $body = "
    <html><body style='font-family:Arial,sans-serif;color:#1e293b;line-height:1.6;max-width:600px;margin:0 auto;padding:20px;background:#fff;'>

        <div style='text-align:center;padding-bottom:20px;border-bottom:2px solid #1c5457;'>
            <h1 style='color:#1c5457;margin:0;font-size:26px;'>Hostel Plaza Mendoza</h1>
        </div>

        <h2 style='color:#1c5457;margin-top:24px;'>Hi {$b['guestName']},</h2>
        <p style='font-size:16px;'>We've received your prebooking request. To <strong>confirm your stay</strong>, please complete a payment of at least <strong>{$fNightly}</strong> (1 night deposit).</p>

        {$paymentSection}

        <h3 style='color:#1c5457;margin:28px 0 10px;font-size:17px;'>Reservation Details</h3>
        <table style='width:100%;border-collapse:collapse;margin-bottom:24px;'>
            <tr><td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;width:45%;'>Room</td>             <td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$roomName}</td></tr>
            <tr><td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Guests</td>                     <td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['guestsCount']}</td></tr>
            <tr><td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Check In</td>                   <td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['checkIn']}</td></tr>
            <tr><td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Check Out</td>                  <td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['checkOut']}</td></tr>
            <tr><td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Estimated Arrival</td>          <td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>" . ($b['eta'] ?: '—') . "</td></tr>
            <tr><td style='padding:10px;color:#64748b;font-weight:bold;'>Total stay</td>                                    <td style='padding:10px;font-weight:bold;text-align:right;color:#1c5457;font-size:18px;'>{$fTotal}</td></tr>
        </table>

        <p style='font-size:15px;margin-bottom:6px;'>Questions? Chat with us on WhatsApp:</p>
        <p style='margin:0;'><a href='https://api.whatsapp.com/send/?phone=5492615372767' style='color:#1c5457;font-weight:bold;text-decoration:none;'>+54 9 2615 37-2767</a></p>

        <h3 style='color:#1c5457;margin:28px 0 10px;font-size:17px;'>Your Information</h3>
        <table style='width:100%;border-collapse:collapse;margin-bottom:28px;'>
            <tr><td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;width:45%;'>Full Name</td>        <td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['guestName']}</td></tr>
            <tr><td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Email</td>                      <td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['email']}</td></tr>
            <tr><td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Phone</td>                      <td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['phone']}</td></tr>
            <tr><td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>ID</td>                         <td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['idType']} {$b['idNumber']}</td></tr>"
            . ($b['notes'] ? "<tr><td style='padding:8px 10px;color:#64748b;vertical-align:top;'>Notes</td><td style='padding:8px 10px;font-weight:bold;text-align:right;'>{$b['notes']}</td></tr>" : "") . "
        </table>

        <p style='margin-top:36px;font-size:14px;color:#64748b;'>Safe travels,<br><strong style='color:#1c5457;'>The Hostel Plaza Team</strong></p>
    </body></html>";

    $altBody = "PREBOOKING RECEIVED  Hostel Plaza Mendoza\n\n"
        . "Hi {$b['guestName']},\n\n"
        . "To confirm your booking, please complete a payment of {$fNightly} (1 night deposit).\n"
        . $paymentAltText
        . "RESERVATION\n"
        . "Room:      {$roomName}\n"
        . "Guests:    {$b['guestsCount']}\n"
        . "Check In:  {$b['checkIn']}\n"
        . "Check Out: {$b['checkOut']}\n"
        . "Total:     {$fTotal}\n\n"
        . "WhatsApp: +54 9 2615 37-2767\n\n"
        . "Hostel Plaza Mendoza";

    return [$subject, $body, $altBody];
}

/**
 * Mail de confirmación post-pago para huéspedes extranjeros — lo dispara
 * stripe_webhook.php cuando el depósito se acredita vía Stripe. A diferencia
 * de hp_mail_extranjero(), NO incluye instrucciones de pago ni datos de
 * cuenta bancaria, porque el huésped ya pagó.
 *
 * @param array  $b           Booking array
 * @param string $roomName    Booked room name
 * @param float  $totalUSD    Total price in USD
 * @param int    $nights      Number of nights
 * @param float  $depositPaid Amount already paid in USD
 * @return array [subject, body, altBody]
 */
function hp_mail_extranjero_confirmed(array $b, string $roomName, float $totalUSD, int $nights, float $depositPaid): array
{
    $nights   = max(1, $nights);
    $balance  = max(0, $totalUSD - $depositPaid);
    $fTotal   = '$' . number_format($totalUSD,   2) . ' USD';
    $fDeposit = '$' . number_format($depositPaid, 2) . ' USD';
    $fBalance = '$' . number_format($balance,     2) . ' USD';

    $subject = 'Booking Confirmed - Hostel Plaza Mendoza';

    $body = "
    <html><body style='font-family:Arial,sans-serif;color:#1e293b;line-height:1.6;max-width:600px;margin:0 auto;padding:20px;background:#fff;'>

        <div style='text-align:center;padding-bottom:20px;border-bottom:2px solid #1c5457;'>
            <h1 style='color:#1c5457;margin:0;font-size:26px;'>Hostel Plaza Mendoza</h1>
        </div>

        <h2 style='color:#1c5457;margin-top:24px;'>Hi {$b['guestName']},</h2>
        <p style='font-size:16px;'>Your payment was received and your stay is <strong>confirmed</strong>. We can't wait to host you!</p>

        <div style='background:#f0faf9;border:1.5px solid #1c5457;border-radius:10px;padding:18px;margin:24px 0;text-align:center;'>
            <p style='margin:0;font-size:15px;color:#1c5457;'><strong>✓ Deposit paid: {$fDeposit}</strong></p>
            <p style='margin:6px 0 0;font-size:13px;color:#64748b;'>Balance due at check-in: {$fBalance}</p>
        </div>

        <h3 style='color:#1c5457;margin:28px 0 10px;font-size:17px;'>Reservation Details</h3>
        <table style='width:100%;border-collapse:collapse;margin-bottom:24px;'>
            <tr><td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;width:45%;'>Room</td>             <td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$roomName}</td></tr>
            <tr><td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Guests</td>                     <td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['guestsCount']}</td></tr>
            <tr><td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Check In</td>                   <td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['checkIn']}</td></tr>
            <tr><td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Check Out</td>                  <td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['checkOut']}</td></tr>
            <tr><td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Estimated Arrival</td>          <td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>" . ($b['eta'] ?: '—') . "</td></tr>
            <tr><td style='padding:10px;color:#64748b;font-weight:bold;'>Total stay</td>                                    <td style='padding:10px;font-weight:bold;text-align:right;color:#1c5457;font-size:18px;'>{$fTotal}</td></tr>
        </table>

        <p style='font-size:15px;margin-bottom:6px;'>Questions? Chat with us on WhatsApp:</p>
        <p style='margin:0;'><a href='https://api.whatsapp.com/send/?phone=5492615372767' style='color:#1c5457;font-weight:bold;text-decoration:none;'>+54 9 2615 37-2767</a></p>

        <h3 style='color:#1c5457;margin:28px 0 10px;font-size:17px;'>Your Information</h3>
        <table style='width:100%;border-collapse:collapse;margin-bottom:28px;'>
            <tr><td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;width:45%;'>Full Name</td>        <td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['guestName']}</td></tr>
            <tr><td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Email</td>                      <td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['email']}</td></tr>
            <tr><td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Phone</td>                      <td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['phone']}</td></tr>
            <tr><td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>ID</td>                         <td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['idType']} {$b['idNumber']}</td></tr>"
            . ($b['notes'] ? "<tr><td style='padding:8px 10px;color:#64748b;vertical-align:top;'>Notes</td><td style='padding:8px 10px;font-weight:bold;text-align:right;'>{$b['notes']}</td></tr>" : "") . "
        </table>

        <p style='margin-top:36px;font-size:14px;color:#64748b;'>Safe travels,<br><strong style='color:#1c5457;'>The Hostel Plaza Team</strong></p>
    </body></html>";

    $altBody = "BOOKING CONFIRMED - Hostel Plaza Mendoza\n\n"
        . "Hi {$b['guestName']},\n\n"
        . "Your payment was received and your stay is confirmed.\n"
        . "Deposit paid:            {$fDeposit}\n"
        . "Balance due at check-in: {$fBalance}\n\n"
        . "RESERVATION\n"
        . "Room:      {$roomName}\n"
        . "Guests:    {$b['guestsCount']}\n"
        . "Check In:  {$b['checkIn']}\n"
        . "Check Out: {$b['checkOut']}\n"
        . "Total:     {$fTotal}\n\n"
        . "WhatsApp: +54 9 2615 37-2767\n\n"
        . "Hostel Plaza Mendoza";

    return [$subject, $body, $altBody];
}
