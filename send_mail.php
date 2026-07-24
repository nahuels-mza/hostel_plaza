<?php
/**
 * Envío de mail vía SMTP de Ferozo (PHPMailer) — compartido entre book.php
 * (mail al crear la reserva) y stripe_webhook.php (confirmación post-pago).
 */

/**
 * @return array{ok: bool, error: string|null}
 */
function hp_send_mail(string $toEmail, string $toName, string $subject, string $body, string $altBody, string $logId = ''): array
{
    $pathException = __DIR__ . '/PHPMailer-master/src/Exception.php';
    $pathPHPMailer = __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
    $pathSMTP      = __DIR__ . '/PHPMailer-master/src/SMTP.php';

    if (!file_exists($pathException) || !file_exists($pathPHPMailer) || !file_exists($pathSMTP)) {
        return ['ok' => false, 'error' => "The 'PHPMailer' folder is missing!"];
    }

    require_once $pathException;
    require_once $pathPHPMailer;
    require_once $pathSMTP;

    $smtpDebugLog = '';
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->SMTPDebug   = 3;
        $mail->Debugoutput = function ($str, $level) use (&$smtpDebugLog) {
            $smtpDebugLog .= "[{$level}] " . trim($str) . "\n";
        };
        $mail->isSMTP();
        $mail->Host       = 'c2721166.ferozo.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'confirmation@hostelplaza.com.ar';
        $mail->Password   = 'ThHQ*RW5hG';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->Timeout    = 15;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->setFrom('confirmation@hostelplaza.com.ar', 'Hostel Plaza');
        $mail->addAddress($toEmail, $toName);
        $mail->addCC('confirmation@hostelplaza.com.ar', 'Hostel Plaza');
        $mail->addBCC('hostelplazamza@gmail.com');
        $mail->addReplyTo('info@hostelplaza.com.ar', 'Hostel Plaza Info');

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $altBody;

        $mail->send();

        file_put_contents(
            __DIR__ . '/mail_debug.log',
            date('c') . " OK {$logId} to={$toEmail}\n" . $smtpDebugLog . "\n",
            FILE_APPEND
        );
        return ['ok' => true, 'error' => null];
    } catch (\Exception $e) {
        $error = $e->getMessage() . ($mail->ErrorInfo ? " | {$mail->ErrorInfo}" : '');
        file_put_contents(
            __DIR__ . '/mail_debug.log',
            date('c') . " ERROR {$logId} to={$toEmail}\n{$error}\n{$smtpDebugLog}\n",
            FILE_APPEND
        );
        error_log("[Hostel Plaza] Mail error {$logId}: {$error}");
        return ['ok' => false, 'error' => $error];
    }
}
