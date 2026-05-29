<?php
// ─── SMTP DIAGNOSTIC SCRIPT ───────────────────────────────────────────────
// Acceder desde el servidor para diagnosticar el envío de mails.
// ELIMINAR este archivo una vez resuelto el problema.
// ──────────────────────────────────────────────────────────────────────────

// Protección básica: solo accesible con ?key=hostelplaza
if (($_GET['key'] ?? '') !== 'hostelplaza') {
    http_response_code(403);
    die('Forbidden');
}

$pathException = __DIR__ . '/PHPMailer-master/src/Exception.php';
$pathPHPMailer = __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
$pathSMTP      = __DIR__ . '/PHPMailer-master/src/SMTP.php';

echo "<pre>";
echo "=== Hostel Plaza SMTP Diagnostic ===\n\n";

// 1. Check PHPMailer files
echo "--- PHPMailer files ---\n";
foreach ([$pathException, $pathPHPMailer, $pathSMTP] as $f) {
    echo (file_exists($f) ? "✓" : "✗") . " " . basename($f) . "\n";
}

if (!file_exists($pathPHPMailer)) {
    die("\n✗ PHPMailer no encontrado. Verificar directorio PHPMailer-master/src/\n");
}

require_once $pathException;
require_once $pathPHPMailer;
require_once $pathSMTP;

use PHPMailer\PHPMailer\PHPMailer;

// 2. PHP info
echo "\n--- PHP / Server ---\n";
echo "PHP version: " . PHP_VERSION . "\n";
echo "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . "\n";
echo "OpenSSL: " . (extension_loaded('openssl') ? OPENSSL_VERSION_TEXT : 'NOT loaded') . "\n";
echo "sockets: " . (extension_loaded('sockets') ? 'loaded' : 'NOT loaded') . "\n";

// 3. SMTP connectivity test
echo "\n--- SMTP Connection Test ---\n";
$host = 'c2721166.ferozo.com';
$port = 465;
echo "Testing TCP connection to {$host}:{$port} ...\n";
$fp = @fsockopen("ssl://{$host}", $port, $errno, $errstr, 10);
if ($fp) {
    echo "✓ TCP connection OK\n";
    fclose($fp);
} else {
    echo "✗ TCP connection FAILED: [{$errno}] {$errstr}\n";
    echo "  → Probablemente el hosting bloquea el puerto o el hostname es incorrecto.\n";
}

// Also try port 587 with STARTTLS
$port587 = 587;
echo "Testing TCP connection to {$host}:{$port587} ...\n";
$fp2 = @fsockopen($host, $port587, $errno2, $errstr2, 10);
if ($fp2) {
    echo "✓ Port 587 reachable\n";
    fclose($fp2);
} else {
    echo "✗ Port 587 FAILED: [{$errno2}] {$errstr2}\n";
}

// 4. PHPMailer full send test
echo "\n--- PHPMailer SMTP Auth Test ---\n";
$mail = new PHPMailer(true);
$debugLog = '';
$mail->SMTPDebug  = 3;
$mail->Debugoutput = function($str, $level) use (&$debugLog) {
    $debugLog .= "[{$level}] " . trim($str) . "\n";
};

try {
    $mail->isSMTP();
    $mail->Host       = 'c2721166.ferozo.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'confirmation@hostelplaza.com.ar';
    $mail->Password   = 'ThHQ*RW5hG';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ];
    $mail->Timeout = 15;

    $mail->setFrom('confirmation@hostelplaza.com.ar', 'Hostel Plaza');
    $mail->addAddress('confirmation@hostelplaza.com.ar'); // se envía a sí mismo como test
    $mail->Subject = 'SMTP Test - ' . date('Y-m-d H:i:s');
    $mail->Body    = 'Este es un mail de prueba SMTP desde test_mail.php - ' . date('c');
    $mail->isHTML(false);

    $mail->send();
    echo "✓ Mail enviado correctamente!\n";
    echo "  Revisá la bandeja de confirmation@hostelplaza.com.ar\n";
} catch (\Exception $e) {
    echo "✗ FALLÓ: " . $e->getMessage() . "\n";
    echo "  ErrorInfo: " . $mail->ErrorInfo . "\n";
}

echo "\n--- SMTP Debug Log ---\n";
echo $debugLog ?: "(vacío)\n";

echo "\n=== Fin del diagnóstico ===\n";
echo "</pre>";
