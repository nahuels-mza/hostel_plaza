<?php
/**
 * Template de mail para huéspedes argentinos.
 *
 * Incluye datos de transferencia bancaria para el depósito mínimo de 1 noche.
 * Los datos bancarios se completan en las constantes de abajo.
 *
 * Uso:
 *   require_once __DIR__ . '/mail_argentina.php';
 *   [$subject, $body, $altBody] = hp_mail_argentina($booking, $roomName, $totalARS, $nights);
 *   $mail->Subject = $subject;
 *   $mail->Body    = $body;
 *   $mail->AltBody = $altBody;
 */

// ── Datos bancarios ─────────────────────────────────────────────────────────
// COMPLETAR antes de subir al servidor.
define('HP_ALIAS',    'HOSTEL.PLAZA.MZA');
define('HP_CBU',      '0070300820000006886499');
define('HP_BANCO',    'Galicia');
define('HP_TITULAR',  'Manada SA');
// ────────────────────────────────────────────────────────────────────────────

/**
 * @param array  $b        Booking array (checkIn, checkOut, guestName, email, phone, etc.)
 * @param string $roomName Nombre de la habitación reservada
 * @param float  $totalARS Precio total del stay en ARS
 * @param int    $nights   Cantidad de noches
 * @return array [subject, body, altBody]
 */
function hp_mail_argentina(array $b, string $roomName, float $totalARS, int $nights): array
{
    $nights      = max(1, $nights);
    $nightlyARS  = round($totalARS / $nights);
    $fTotal      = 'AR$ ' . number_format($totalARS,   0, ',', '.');
    $fNightly    = 'AR$ ' . number_format($nightlyARS, 0, ',', '.');

    $subject = 'PreReserva Recibida — Hostel Plaza Mendoza';

    $body = "
    <html><body style='font-family:Arial,sans-serif;color:#1e293b;line-height:1.6;max-width:600px;margin:0 auto;padding:20px;background:#fff;'>

        <div style='text-align:center;padding-bottom:20px;border-bottom:2px solid #1c5457;'>
            <h1 style='color:#1c5457;margin:0;font-size:26px;'>Hostel Plaza Mendoza</h1>
        </div>

        <h2 style='color:#1c5457;margin-top:24px;'>Hola {$b['guestName']},</h2>
        <p style='font-size:16px;'>Recibimos tu solicitud de prerreserva. Para <strong>confirmar tu estadía</strong> necesitamos que realices una transferencia bancaria de al menos el monto de <strong>1 noche ({$fNightly})</strong>.</p>

        <h3 style='color:#1c5457;margin:28px 0 10px;font-size:17px;'>Detalles de la Reserva</h3>
        <table style='width:100%;border-collapse:collapse;margin-bottom:24px;'>
            <tr><td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;width:45%;'>Habitación</td>    <td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$roomName}</td></tr>
            <tr><td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Huéspedes</td>               <td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['guestsCount']}</td></tr>
            <tr><td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Check In</td>                <td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['checkIn']}</td></tr>
            <tr><td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Check Out</td>               <td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['checkOut']}</td></tr>
            <tr><td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Llegada estimada</td>        <td style='padding:9px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>" . ($b['eta'] ?: '—') . "</td></tr>
            <tr><td style='padding:10px;color:#64748b;font-weight:bold;'>Total estadía</td>                              <td style='padding:10px;font-weight:bold;text-align:right;color:#1c5457;font-size:18px;'>{$fTotal}</td></tr>
        </table>

        <div style='background:#f0faf9;border:1.5px solid #1c5457;border-radius:10px;padding:22px;margin:24px 0;'>
            <h3 style='color:#1c5457;margin:0 0 14px;font-size:17px;'>🏦 Datos para la Transferencia</h3>
            <table style='width:100%;border-collapse:collapse;'>
                <tr><td style='padding:6px 0;color:#64748b;width:40%;'>Alias</td>    <td style='padding:6px 0;font-weight:bold;font-family:monospace;font-size:15px;'>" . HP_ALIAS . "</td></tr>
                <tr><td style='padding:6px 0;color:#64748b;'>CBU</td>                 <td style='padding:6px 0;font-weight:bold;font-family:monospace;font-size:13px;'>" . HP_CBU . "</td></tr>
                <tr><td style='padding:6px 0;color:#64748b;'>Banco</td>               <td style='padding:6px 0;font-weight:bold;'>" . HP_BANCO . "</td></tr>
                <tr><td style='padding:6px 0;color:#64748b;'>Titular</td>             <td style='padding:6px 0;font-weight:bold;'>" . HP_TITULAR . "</td></tr>
                <tr><td style='padding:10px 0 0;color:#64748b;font-weight:bold;'>Monto mínimo</td><td style='padding:10px 0 0;font-weight:bold;color:#1c5457;font-size:16px;'>{$fNightly} <span style='font-size:12px;color:#94a3b8;'>(1 noche)</span></td></tr>
            </table>
        </div>

        <p style='font-size:15px;margin-bottom:6px;'>📲 Una vez realizada la transferencia, envianos el comprobante por WhatsApp:</p>
        <p style='margin:0;'><a href='https://api.whatsapp.com/send/?phone=5492615372767' style='color:#1c5457;font-weight:bold;text-decoration:none;'>+54 9 2615 37-2767</a></p>

        <h3 style='color:#1c5457;margin:28px 0 10px;font-size:17px;'>Tus Datos</h3>
        <table style='width:100%;border-collapse:collapse;margin-bottom:28px;'>
            <tr><td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;width:45%;'>Nombre</td>        <td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['guestName']}</td></tr>
            <tr><td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Email</td>                   <td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['email']}</td></tr>
            <tr><td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Teléfono</td>               <td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['phone']}</td></tr>
            <tr><td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Documento</td>               <td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$b['idType']} {$b['idNumber']}</td></tr>"
            . ($b['notes'] ? "<tr><td style='padding:8px 10px;color:#64748b;vertical-align:top;'>Notas</td><td style='padding:8px 10px;font-weight:bold;text-align:right;'>{$b['notes']}</td></tr>" : "") . "
        </table>

        <p style='margin-top:36px;font-size:14px;color:#64748b;'>¡Gracias por elegirnos!<br><strong style='color:#1c5457;'>El equipo de Hostel Plaza</strong></p>
    </body></html>";

    $altBody = "PRERRESERVA RECIBIDA — Hostel Plaza Mendoza\n\n"
        . "Hola {$b['guestName']},\n\n"
        . "Para confirmar tu reserva realizá una transferencia de al menos {$fNightly} (1 noche).\n\n"
        . "DATOS BANCARIOS\n"
        . "Alias:   " . HP_ALIAS   . "\n"
        . "CBU:     " . HP_CBU     . "\n"
        . "Banco:   " . HP_BANCO   . "\n"
        . "Titular: " . HP_TITULAR . "\n"
        . "Monto mínimo: {$fNightly}\n\n"
        . "Por Favor, envianos el comprobante por WhatsApp: +54 9 2615 37-2767\n\n"
        . "DATOS DE LA RESERVA\n"
        . "Habitación: {$roomName}\n"
        . "Huéspedes:  {$b['guestsCount']}\n"
        . "Check In:   {$b['checkIn']}\n"
        . "Check Out:  {$b['checkOut']}\n"
        . "Total:      {$fTotal}\n\n"
        . "Hostel Plaza Mendoza";

    return [$subject, $body, $altBody];
}
