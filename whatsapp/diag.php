<?php
/**
 * Diagnóstico del bot de WhatsApp.
 * Acceder con: https://hostelplaza.com.ar/whatsapp/diag.php?key=hostelplaza
 * ELIMINAR o proteger en producción una vez resuelto.
 */
if (($_GET['key'] ?? '') !== 'hostelplaza') { http_response_code(403); die('Forbidden'); }

$cfg = require __DIR__ . '/config.php';
echo "<pre style='font-family:monospace;font-size:13px;line-height:1.6;'>";
echo "=== WhatsApp Bot Diagnostic ===\n\n";

// ─── 0. Secrets file ─────────────────────────────────────────
echo "── 0. Archivo de secrets ──\n";
$secretsPath = dirname(dirname(__DIR__)) . '/storagedir/secrets.php';
echo "Ruta buscada : " . $secretsPath . "\n";
echo "¿Existe?     : " . (is_file($secretsPath) ? "✓ sí" : "✗ NO ENCONTRADO") . "\n";
if (is_file($secretsPath)) {
    $s = require $secretsPath;
    $keys = ['smtp_password', 'claude_api_key', 'wa_phone_number_id', 'wa_access_token', 'wa_verify_token'];
    foreach ($keys as $k) {
        $val = $s[$k] ?? null;
        echo "  $k : " . ($val === null ? "✗ falta la clave" : (trim($val) === '' ? "⚠ vacío" : "✓ tiene valor")) . "\n";
    }
}

// ─── 1. Config ───────────────────────────────────────────────
echo "\n── 1. Configuración ──\n";
$wa = $cfg['whatsapp'];
echo "phone_number_id : " . (trim($wa['phone_number_id']) === '' ? "✗ vacío (falta en secrets)" : "✓ " . substr($wa['phone_number_id'], 0, 8) . "...") . "\n";
echo "access_token    : " . (trim($wa['access_token'])    === '' ? "✗ vacío (falta en secrets)" : "✓ " . substr($wa['access_token'], 0, 12) . "...") . "\n";
echo "verify_token    : " . (trim($wa['verify_token'])    === '' ? "✗ vacío (falta en secrets)" : "✓ configurado") . "\n";
echo "graph_version   : " . $wa['graph_version'] . "\n";

$webhookUrl = 'https://hostelplaza.com.ar/whatsapp/webhook.php';
echo "\nURL del webhook a registrar en Meta:\n  → " . $webhookUrl . "\n";

// ─── 2. Archivos ─────────────────────────────────────────────
echo "\n── 2. Archivos ──\n";
$files = [
    __DIR__ . '/webhook.php',
    __DIR__ . '/agent.php',
    __DIR__ . '/claude_client.php',
    __DIR__ . '/whatsapp_client.php',
    __DIR__ . '/availability.php',
    __DIR__ . '/config.php',
    __DIR__ . '/../room_mapping.json',
    __DIR__ . '/../rooms.json',
];
foreach ($files as $f) {
    echo (file_exists($f) ? "✓" : "✗") . " " . basename($f) . "\n";
}

// ─── 3. Directorios escribibles ───────────────────────────────
echo "\n── 3. Directorios ──\n";
foreach (['logs', 'cache'] as $d) {
    $path = __DIR__ . '/' . $d;
    $exists   = is_dir($path);
    $writable = $exists && is_writable($path);
    echo ($writable ? "✓" : "✗") . " $d/ " . (!$exists ? "(no existe)" : (!$writable ? "(sin escritura)" : "")) . "\n";
}

// ─── 4. Log reciente ─────────────────────────────────────────
echo "\n── 4. Log reciente (últimas 30 líneas) ──\n";
$logPath = $cfg['paths']['log'];
if (!file_exists($logPath) || filesize($logPath) === 0) {
    echo "⚠ El log está vacío. Esto significa que el webhook aún no recibió ningún POST de Meta.\n";
    echo "  Causas probables:\n";
    echo "  a) El webhook no está registrado/verificado en Meta.\n";
    echo "  b) El .htaccess estaba redirigiendo webhook.php (ya corregido).\n";
    echo "  c) La suscripción al número no está activa.\n";
} else {
    $lines = file($logPath);
    $recent = array_slice($lines, -30);
    echo implode('', $recent);
}

// ─── 5. Test Claude API ──────────────────────────────────────
echo "\n── 5. Test Claude API ──\n";
require_once __DIR__ . '/claude_client.php';
$probe = claude_call(
    $cfg,
    [['role' => 'user', 'content' => 'Respondé solo: OK']],
    [],
    'Sos un test de conectividad.'
);
if ($probe['ok']) {
    $txt = '';
    foreach ($probe['data']['content'] ?? [] as $b) {
        if (($b['type'] ?? '') === 'text') $txt .= $b['text'];
    }
    echo "✓ Claude responde: " . trim($txt) . "\n";
} else {
    echo "✗ Claude error: " . json_encode($probe) . "\n";
}

// ─── 6. Test WhatsApp API (solo auth, sin enviar) ────────────
echo "\n── 6. Test WhatsApp API ──\n";
$waCfg = $cfg['whatsapp'];
$ch = curl_init(sprintf(
    'https://graph.facebook.com/%s/%s',
    $waCfg['graph_version'],
    $waCfg['phone_number_id']
));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $waCfg['access_token']],
    CURLOPT_TIMEOUT => 10,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$waData = json_decode($resp, true);
if ($code === 200) {
    echo "✓ Autenticación OK — número: " . ($waData['display_phone_number'] ?? 'N/A') . "\n";
} elseif ($code === 190 || isset($waData['error']['code'])) {
    echo "✗ Auth FAIL (HTTP $code): " . ($waData['error']['message'] ?? $resp) . "\n";
    echo "  → Revisá el access_token en config.php\n";
} else {
    echo "✗ Error HTTP $code: " . substr($resp, 0, 200) . "\n";
}

echo "\n=== Fin del diagnóstico ===\n";
echo "</pre>";
