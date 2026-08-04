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
 * @return array{env: string, base_url: string, public_key: string, private_key: string, site_id: string}
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
            'site_id'     => $s['payway_prod_site_id']     ?? '',
            'template_id' => $s['payway_prod_template_id'] ?? '',
        ]
        : [
            'env'         => 'test',
            'base_url'    => 'https://developers.decidir.com',
            'public_key'  => $s['payway_test_public_key']  ?? '',
            'private_key' => $s['payway_test_private_key'] ?? '',
            'site_id'     => $s['payway_test_site_id']     ?? '',
            'template_id' => $s['payway_test_template_id'] ?? '',
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

    // site_id identifica el establecimiento ante Payway/CyberSource — sin él,
    // el antifraude puede rechazar de forma genérica (id:-1). Se agrega acá
    // para que ningún caller tenga que acordarse de mandarlo.
    if ($cfg['site_id'] && !isset($payload['site_id'])) {
        $payload['site_id'] = $cfg['site_id'];
    }
    // Experimental: template_id distingue perfiles "con/sin CyberSource" en
    // Payway. No está confirmado que este endpoint (/payments) lo use — se
    // manda solo si está configurado, sin romper nada si Payway lo ignora.
    if ($cfg['template_id'] && !isset($payload['template_id'])) {
        $payload['template_id'] = $cfg['template_id'];
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

    // Payway devuelve un "status" (approved/rejected/etc.) tanto con HTTP 200
    // como con 402 (pago procesado pero rechazado) — si viene ese campo,
    // usamos esa info rica en vez de tratarlo como un error de request.
    if (isset($respData['status'])) {
        // Allowlist explícita: solo "approved" se trata como éxito. Ante
        // cualquier otro status (rejected, in_process, o algo no contemplado)
        // fallamos seguro.
        if ($respData['status'] !== 'approved') {
            return ['ok' => false, 'response' => $respData, 'error' => "Pago {$respData['status']}: " . hp_payway_describe_status_details($respData['status_details'] ?? null)];
        }
        return ['ok' => true, 'response' => $respData, 'error' => null];
    }

    if ($respCode < 200 || $respCode >= 300) {
        $reason = $respData['message'] ?? $respData['error_type'] ?? substr((string)$respBody, 0, 300);
        if (!empty($respData['validation_errors']) && is_array($respData['validation_errors'])) {
            $details = array_map(function ($e) {
                $param = $e['param'] ?? ($e['code'] ?? '?');
                $msg   = $e['message'] ?? ($e['type'] ?? 'invalid');
                return "{$param}: {$msg}";
            }, $respData['validation_errors']);
            $reason .= ' — ' . implode('; ', $details);
        }
        return ['ok' => false, 'response' => $respData, 'error' => "Payway devolvió HTTP {$respCode}: {$reason}"];
    }

    // HTTP 2xx sin "status" reconocible — no debería pasar, pero no lo
    // tratamos como éxito ante la duda (allowlist, no denylist).
    return ['ok' => false, 'response' => $respData, 'error' => 'Respuesta de Payway sin status reconocible.'];
}

/**
 * Arma un texto legible a partir de status_details, sea cual sea su forma
 * exacta (varía según el tipo de rechazo — antifraude, banco, CyberSource, etc.).
 */
function hp_payway_describe_status_details($statusDetails): string
{
    if (!is_array($statusDetails)) {
        return $statusDetails !== null ? (string)$statusDetails : 'sin detalle';
    }

    $error = $statusDetails['error'] ?? null;
    if (is_array($error)) {
        $reason = $error['reason'] ?? null;
        $msg = is_array($reason) ? ($reason['message'] ?? $reason['description'] ?? null) : null;
        $type = $error['type'] ?? null;
        if ($msg) return "{$type}: {$msg}";
        if ($type) return (string)$type . ' — ' . json_encode($error, JSON_UNESCAPED_UNICODE);
    }

    return json_encode($statusDetails, JSON_UNESCAPED_UNICODE);
}
