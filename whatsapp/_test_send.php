<?php
/**
 * TEST TEMPORAL — mandar un WhatsApp desde el servidor.
 *
 * Uso:
 *   https://hostelplaza.com.ar/whatsapp/_test_send.php?to=549261XXXXXXX
 *
 *  - `to` es el número destino en formato E.164 sin '+' (ej: 5492612345678).
 *  - El texto es fijo ("Test bot Hostel Plaza · <hora>").
 *
 * ⚠️  Este archivo expone datos sensibles en pantalla. BORRARLO después
 *     de probar (o al menos moverlo fuera de public_html).
 */

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/whatsapp_client.php';

$cfg = require __DIR__ . '/config.php';

echo "=== TEST SEND ===\n\n";

// 1. Chequeos de config
echo "phone_number_id : " . ($cfg['whatsapp']['phone_number_id'] ?: '(vacío)') . "\n";
echo "access_token    : " . (empty($cfg['whatsapp']['access_token']) ? '(VACÍO — falla seguro)' : 'OK (' . strlen($cfg['whatsapp']['access_token']) . ' chars, empieza con ' . substr($cfg['whatsapp']['access_token'], 0, 8) . '...)') . "\n";
echo "verify_token    : " . ($cfg['whatsapp']['verify_token'] ?: '(vacío)') . "\n";
echo "graph_version   : " . $cfg['whatsapp']['graph_version'] . "\n";
echo "\n";

// 2. Destino
$to = isset($_GET['to']) ? preg_replace('/[^0-9]/', '', $_GET['to']) : '';
if ($to === '') {
    echo "❌ Falta ?to=NUMERO en la URL. Ejemplo: ?to=5492612345678\n";
    exit;
}
echo "Enviando a: +{$to}\n\n";

// 3. Envío
$msg = "Test bot Hostel Plaza · " . date('H:i:s');
$res = wa_send_text($cfg, $to, $msg);

// 4. Resultado
echo "=== RESULTADO ===\n";
echo "HTTP code : " . $res['code'] . "\n";
echo "curl err  : " . ($res['err'] ?: '(ninguno)') . "\n";
echo "ok        : " . ($res['ok'] ? 'SI ✅' : 'NO ❌') . "\n";
echo "body      : " . $res['body'] . "\n";
