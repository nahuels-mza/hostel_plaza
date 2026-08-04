<?php
/**
 * Cliente mínimo de la API de Payway (Decidir) vía cURL — sin SDK/Composer,
 * mismo estilo que bananadesk_reserve.php.
 *
 * 'payway_env' en /storagedir/secrets.php ('test' | 'prod') es la única
 * variable que decide qué credenciales/URLs se usan — todo lo demás en el
 * sitio llama a hp_payway_config()/hp_payway_public_config(), nunca lee
 * 'payway_env' directamente.
 */

require_once __DIR__ . '/dev_env.php';

function hp_payway_secrets(): array
{
    $secretsFile = hp_secrets_path(__DIR__);
    return is_file($secretsFile) ? (require $secretsFile) : [];
}

/**
 * Config server-side (incluye la private key). Nunca exponer al frontend.
 * @return array{env: string, base_url: string, public_key: string, private_key: string}
 */
function hp_payway_config(): array
{
    $s   = hp_payway_secrets();
    $env = ($s['payway_env'] ?? 'test') === 'prod' ? 'prod' : 'test';

    return $env === 'prod'
        ? [
            'env'         => 'prod',
            'base_url'    => 'https://ventasonline.payway.com.ar',
            'public_key'  => $s['payway_prod_public_key']  ?? '',
            'private_key' => $s['payway_prod_private_key'] ?? '',
        ]
        : [
            'env'         => 'test',
            'base_url'    => 'https://developers.decidir.com',
            'public_key'  => $s['payway_test_public_key']  ?? '',
            'private_key' => $s['payway_test_private_key'] ?? '',
        ];
}

/**
 * Config segura para el frontend (sin private key).
 * @return array{env: string, sdk_base_url: string, public_key: string}
 */
function hp_payway_public_config(): array
{
    $cfg = hp_payway_config();
    return [
        'env'          => $cfg['env'],
        'sdk_base_url' => $cfg['base_url'] . '/api/v2',
        'public_key'   => $cfg['public_key'],
    ];
}

/**
 * Resuelve el payment_method_id de Payway a partir del BIN (primeros 6
 * dígitos de la tarjeta). Stub MVP: Visa/Mastercard/Amex por prefijo —
 * ver "Tabla de Medios de Pago" en el portal de Payway para ampliarla
 * (Cabal, Naranja, etc.) antes de ir a producción.
 */
function hp_payway_resolve_payment_method_id(string $bin): ?int
{
    $bin = preg_replace('/\D/', '', $bin);
    if (strlen($bin) < 6) return null;

    $first1 = substr($bin, 0, 1);
    $first2 = substr($bin, 0, 2);
    $first4 = (int)substr($bin, 0, 4);

    if ($first1 === '4') return 1;   // Visa
    if ($first1 === '5' || ($first4 >= 2221 && $first4 <= 2720)) return 104; // Mastercard
    if ($first2 === '34' || $first2 === '37') return 65; // Amex

    return null;
}

/**
 * @param array $payload Body ya armado para POST /api/v2/payments
 * @return array{ok: bool, response: array|null, error: string|null}
 */
function hp_payway_charge(array $payload): array
{
    $cfg = hp_payway_config();
    if (!$cfg['private_key']) {
        return ['ok' => false, 'response' => null, 'error' => 'Payway no está configurado (falta la private key).'];
    }

    $ch = curl_init($cfg['base_url'] . '/api/v2/payments');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'apikey: ' . $cfg['private_key'],
        ],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $respBody = curl_exec($ch);
    $respCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['ok' => false, 'response' => null, 'error' => "cURL error: {$curlErr}"];
    }

    $respData = json_decode((string)$respBody, true);
    if (!is_array($respData)) $respData = [];

    if ($respCode < 200 || $respCode >= 300) {
        $reason = $respData['message'] ?? $respData['error_type'] ?? substr((string)$respBody, 0, 300);
        return ['ok' => false, 'response' => $respData, 'error' => "Payway devolvió HTTP {$respCode}: {$reason}"];
    }

    // Allowlist explícita: solo "approved" se trata como éxito. Ante cualquier
    // otro status (rejected, in_process, o algo no contemplado) fallamos seguro.
    if (($respData['status'] ?? '') !== 'approved') {
        $details = $respData['status_details'] ?? $respData['status'] ?? 'unknown';
        return ['ok' => false, 'response' => $respData, 'error' => "Pago no aprobado: " . (is_string($details) ? $details : json_encode($details))];
    }

    return ['ok' => true, 'response' => $respData, 'error' => null];
}
