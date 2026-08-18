<?php
/**
 * Mail de confirmación de reserva — un solo template para todos los huéspedes
 * (ya no se distingue por nacionalidad). Sin datos de pago ni transferencia:
 * se paga directamente en el check-in.
 *
 * Idioma: se detecta del header Accept-Language del browser (hp_detect_lang),
 * no de la nacionalidad del huésped.
 *
 * Uso:
 *   require_once __DIR__ . '/mail_booking.php';
 *   [$subject, $body, $altBody] = hp_mail_booking($booking, $roomName, $totalARS, $nights, hp_detect_lang());
 */

/**
 * Detecta 'es' o 'en' a partir del header Accept-Language del browser.
 * Cualquier otro idioma cae a inglés.
 */
function hp_detect_lang(): string
{
    $header  = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    $primary = strtolower(substr(trim(explode(',', $header)[0] ?? ''), 0, 2));
    return $primary === 'es' ? 'es' : 'en';
}

/**
 * @param array  $b        Booking array
 * @param string $roomName Booked room name
 * @param float  $totalARS Total price in ARS (mismo monto que ve el huésped en el wizard)
 * @param int    $nights   Number of nights
 * @param string $lang     'es' | 'en'
 * @return array [subject, body, altBody]
 */
function hp_mail_booking(array $b, string $roomName, float $totalARS, int $nights, string $lang = 'en'): array
{
    $nights = max(1, $nights);
    $fTotal = 'AR$ ' . number_format($totalARS, 0, ',', '.');
    $isEs   = $lang === 'es';

    $t = $isEs ? [
        'headerAlt' => 'RESERVA RECIBIDA - Hostel Plaza Mendoza',
        'subject'   => 'Reserva Recibida - Hostel Plaza Mendoza',
        'greeting'  => "¡Hola {$b['guestName']}!",
        'intro'     => '¡Gracias por tu reserva! Ya la tenemos registrada y nuestro equipo va a estar esperándote. El pago se realiza directamente en el check-in — no necesitás hacer nada más por ahora.',
        'details'   => 'Detalles de la Reserva',
        'code'      => 'Código de Reserva',
        'room'      => 'Habitación',
        'guests'    => 'Huéspedes',
        'checkin'   => 'Check In',
        'checkout'  => 'Check Out',
        'eta'       => 'Llegada estimada',
        'total'     => 'Total',
        'questions' => '¿Preguntas? Escribinos por WhatsApp:',
        'yourinfo'  => 'Tus Datos',
        'fullname'  => 'Nombre',
        'email'     => 'Email',
        'phone'     => 'Teléfono',
        'id'        => 'Documento',
        'notes'     => 'Notas',
        'closing'   => '¡Nos vemos pronto!',
        'team'      => 'El equipo de Hostel Plaza',
    ] : [
        'headerAlt' => 'BOOKING RECEIVED - Hostel Plaza Mendoza',
        'subject'   => 'Booking Received - Hostel Plaza Mendoza',
        'greeting'  => "Hi {$b['guestName']}!",
        'intro'     => "Thank you for your reservation! We've got it on file and our team will be ready for you. Payment is made directly at check-in — no need to do anything else for now.",
        'details'   => 'Reservation Details',
        'code'      => 'Booking Code',
        'room'      => 'Room',
        'guests'    => 'Guests',
        'checkin'   => 'Check In',
        'checkout'  => 'Check Out',
        'eta'       => 'Estimated Arrival',
        'total'     => 'Total',
        'questions' => 'Questions? Chat with us on WhatsApp:',
        'yourinfo'  => 'Your Information',
        'fullname'  => 'Full Name',
        'email'     => 'Email',
        'phone'     => 'Phone',
        'id'        => 'ID',
        'notes'     => 'Notes',
        'closing'   => 'See you soon!',
        'team'      => 'The Hostel Plaza Team',
    ];

    $subject = $t['subject'];

    $body = "
    <html><body style='font-family:Arial,sans-serif;color:#1e293b;line-height:1.6;max-width:600px;margin:0 auto;padding:20px;background:#fff;'>

        <div style='text-align:center;padding-bottom:20px;border-bottom:2px solid #1c5457;'>
            <h1 style='color:#1c5457;margin:0;font-size:26px;'>Hostel Plaza Mendoza</h1>
        </div>

        <h2 style='color:#1c5457;margin-top:24px;'>{$t['greeting']}</h2>
        <p style='font-size:16px;'>{$t['intro']}</p>

        <h3 style='color:#1c5457;margin:28px 0 10px;font-size:17px;'>{$t['details']}</h3>
        <table style='width:100%;border-collapse:collapse;margin-bottom:24px;'>
            <tr><td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;width:45%;'>{$t['code']}</td>    <td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;font-family:monospace;'>{$b['id']}</td></tr>
            <tr><td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;width:45%;'>{$t['room']}</td>    <td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$roomName}</td></tr>
            <tr><td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>{$t['guests']}</td>            <td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['guestsCount']}</td></tr>
            <tr><td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>{$t['checkin']}</td>           <td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['checkIn']}</td></tr>
            <tr><td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>{$t['checkout']}</td>          <td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['checkOut']}</td></tr>
            <tr><td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>{$t['eta']}</td>               <td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>" . ($b['eta'] ?: '—') . "</td></tr>
            <tr><td style='padding:10px;color:#64748b;font-weight:bold;'>{$t['total']}</td>                               <td style='padding:10px;font-weight:bold;text-align:right;color:#1c5457;font-size:18px;'>{$fTotal}</td></tr>
        </table>

        <p style='font-size:15px;margin-bottom:6px;'>{$t['questions']}</p>
        <p style='margin:0;'><a href='https://api.whatsapp.com/send/?phone=5492612592729' style='color:#1c5457;font-weight:bold;text-decoration:none;'>+54 9 2612 59-2729</a></p>

        <h3 style='color:#1c5457;margin:28px 0 10px;font-size:17px;'>{$t['yourinfo']}</h3>
        <table style='width:100%;border-collapse:collapse;margin-bottom:28px;'>
            <tr><td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;width:45%;'>{$t['fullname']}</td>  <td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['guestName']}</td></tr>
            <tr><td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>{$t['email']}</td>               <td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['email']}</td></tr>
            <tr><td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>{$t['phone']}</td>               <td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['phone']}</td></tr>
            <tr><td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>{$t['id']}</td>                  <td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['idType']} {$b['idNumber']}</td></tr>"
            . ($b['notes'] ? "<tr><td style='padding:8px 10px;color:#64748b;vertical-align:top;'>{$t['notes']}</td><td style='padding:8px 10px;font-weight:bold;text-align:right;'>{$b['notes']}</td></tr>" : "") . "
        </table>

        <p style='margin-top:36px;font-size:14px;color:#64748b;'>{$t['closing']}<br><strong style='color:#1c5457;'>{$t['team']}</strong></p>
    </body></html>";

    $altBody = "{$t['headerAlt']}\n\n"
        . "{$t['greeting']}\n\n"
        . "{$t['intro']}\n\n"
        . strtoupper($t['details']) . "\n"
        . "{$t['code']}: {$b['id']}\n"
        . "{$t['room']}: {$roomName}\n"
        . "{$t['guests']}: {$b['guestsCount']}\n"
        . "{$t['checkin']}: {$b['checkIn']}\n"
        . "{$t['checkout']}: {$b['checkOut']}\n"
        . "{$t['total']}: {$fTotal}\n\n"
        . "WhatsApp: +54 9 2612 59-2729\n\n"
        . "Hostel Plaza Mendoza";

    return [$subject, $body, $altBody];
}
