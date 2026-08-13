<?php
/**
 * Cliente mínimo de la API de PayPal (Orders v2) vía cURL — sin SDK/Composer,
 * mismo estilo que bananadesk_reserve.php / payway_lib.php.
 *
 * 'paypal_env' en /storagedir/secrets.php ('sandbox' | 'live') es la única
 * variable que decide qué credenciales/URL se usan — todo lo demás en el
 * sitio llama a hp_paypal_config()/hp_paypal_public_config(), nunca lee
 * 'paypal_env' directamente.
 */

require_once __DIR__ . '/dev_env.php';

function hp_paypal_secrets(): array
{
    $secretsFile = hp_secrets_path(__DIR__);
    return is_file($secretsFile) ? (require $secretsFile) : [];
}

/**
 * Config server-side (incluye el secret). Nunca exponer al frontend.
 * @return array{env: string, base_url: string, client_id: string, secret: string}
 */
function hp_paypal_config(): array
{
    $s   = hp_paypal_secrets();
    $env = ($s['paypal_env'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox';

    return $env === 'live'
        ? [
            'env'       => 'live',
            'base_url'  => 'https://api-m.paypal.com',
            'client_id' => $s['paypal_live_client_id'] ?? '',
            'secret'    => $s['paypal_live_secret']    ?? '',
        ]
        : [
            'env'       => 'sandbox',
            'base_url'  => 'https://api-m.sandbox.paypal.com',
            'client_id' => $s['paypal_sandbox_client_id'] ?? '',
            'secret'    => $s['paypal_sandbox_secret']    ?? '',
        ];
}

/**
 * Config segura para el frontend (sin secret).
 * @return array{env: string, client_id: string}
 */
function hp_paypal_public_config(): array
{
    $cfg = hp_paypal_config();
    return ['env' => $cfg['env'], 'client_id' => $cfg['client_id']];
}

/**
 * Request genérico a la API de PayPal — pide el access token y hace la
 * llamada en un solo paso.
 *
 * @return array{ok: bool, status: int, data: array, error: string|null}
 */
function hp_paypal_request(string $method, string $path, ?array $body = null): array
{
    $cfg = hp_paypal_config();
    if (!$cfg['client_id'] || !$cfg['secret']) {
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'PayPal no está configurado (faltan client_id/secret).'];
    }

    $token = hp_paypal_access_token($cfg);
    if (!$token) {
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'No se pudo autenticar contra PayPal (OAuth2).'];
    }

    $ch = curl_init($cfg['base_url'] . $path);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_TIMEOUT        => 20,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
    }
    curl_setopt_array($ch, $opts);
    $respBody = curl_exec($ch);
    $respCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => "cURL error: {$curlErr}"];
    }

    $data = json_decode((string)$respBody, true);
    if (!is_array($data)) $data = [];

    if ($respCode < 200 || $respCode >= 300) {
        $reason = $data['message'] ?? substr((string)$respBody, 0, 300);
        if (!empty($data['details']) && is_array($data['details'])) {
            $details = array_map(function ($d) {
                return ($d['field'] ?? $d['issue'] ?? '?') . ': ' . ($d['description'] ?? $d['issue'] ?? 'invalid');
            }, $data['details']);
            $reason .= ' — ' . implode('; ', $details);
        }
        return ['ok' => false, 'status' => $respCode, 'data' => $data, 'error' => "PayPal devolvió HTTP {$respCode}: {$reason}"];
    }

    return ['ok' => true, 'status' => $respCode, 'data' => $data, 'error' => null];
}

/**
 * OAuth2 client_credentials — cacheado en un archivo temporal por el tiempo
 * de vida que PayPal indique (normalmente ~9hs), para no pedir un token nuevo
 * en cada request.
 */
function hp_paypal_access_token(array $cfg): ?string
{
    $cacheFile = sys_get_temp_dir() . '/hp_paypal_token_' . $cfg['env'] . '.json';
    if (is_file($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if (is_array($cached) && ($cached['expires_at'] ?? 0) > time() + 30) {
            return $cached['access_token'];
        }
    }

    $ch = curl_init($cfg['base_url'] . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
        CURLOPT_USERPWD        => $cfg['client_id'] . ':' . $cfg['secret'],
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $respBody = curl_exec($ch);
    $respCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($respCode < 200 || $respCode >= 300) return null;

    $data = json_decode((string)$respBody, true);
    if (!is_array($data) || empty($data['access_token'])) return null;

    @file_put_contents($cacheFile, json_encode([
        'access_token' => $data['access_token'],
        'expires_at'   => time() + (int)($data['expires_in'] ?? 3000),
    ]));

    return $data['access_token'];
}

/**
 * Crea una orden de pago por el total de la estadía (USD).
 * @return array{ok: bool, orderId: string|null, error: string|null}
 */
function hp_paypal_create_order(string $bookingId, float $amountUsd, string $description): array
{
    $result = hp_paypal_request('post', '/v2/checkout/orders', [
        'intent'         => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => $bookingId,
            'description'  => $description,
            'amount'       => [
                'currency_code' => 'USD',
                'value'         => number_format($amountUsd, 2, '.', ''),
            ],
        ]],
        'application_context' => [
            'brand_name'         => 'Hostel Plaza',
            'shipping_preference' => 'NO_SHIPPING',
            'user_action'        => 'PAY_NOW',
        ],
    ]);

    if (!$result['ok']) {
        return ['ok' => false, 'orderId' => null, 'error' => $result['error']];
    }
    return ['ok' => true, 'orderId' => $result['data']['id'] ?? null, 'error' => null];
}

/**
 * Captura (cobra) una orden ya aprobada por el pagador.
 * @return array{ok: bool, response: array|null, error: string|null}
 */
function hp_paypal_capture_order(string $orderId): array
{
    $result = hp_paypal_request('post', "/v2/checkout/orders/{$orderId}/capture");

    if (!$result['ok']) {
        return ['ok' => false, 'response' => $result['data'], 'error' => $result['error']];
    }

    $status = $result['data']['status'] ?? '';
    if ($status !== 'COMPLETED') {
        return ['ok' => false, 'response' => $result['data'], 'error' => "PayPal devolvió status: {$status}"];
    }

    return ['ok' => true, 'response' => $result['data'], 'error' => null];
}
