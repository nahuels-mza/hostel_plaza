<?php
/**
 * Cliente mínimo de la API de Stripe vía cURL — sin SDK/Composer, mismo
 * estilo que bananadesk_reserve.php.
 */

/**
 * @param string $method 'get' | 'post'
 * @param string $path   Ej: 'checkout/sessions'
 * @param array  $params Se codifican como application/x-www-form-urlencoded
 *                        (http_build_query ya produce la notación con
 *                        corchetes que espera la API de Stripe para arrays anidados)
 * @return array{ok: bool, status: int, data: array}
 */
function hp_stripe_request(string $method, string $path, array $params, string $secretKey): array
{
    $ch = curl_init("https://api.stripe.com/v1/{$path}");
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$secretKey}"],
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_TIMEOUT        => 15,
    ];
    if ($params) {
        $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['ok' => false, 'status' => 0, 'data' => ['error' => ['message' => "cURL error: {$curlErr}"]]];
    }

    $data = json_decode((string)$body, true);
    if (!is_array($data)) $data = [];

    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'data' => $data];
}

/**
 * Verifica la firma de un webhook de Stripe (header Stripe-Signature) sin SDK.
 * https://docs.stripe.com/webhooks#verify-manually
 */
function hp_stripe_verify_signature(string $payload, string $sigHeader, string $webhookSecret): bool
{
    $parts = [];
    foreach (explode(',', $sigHeader) as $pair) {
        [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
        $parts[$k] = $v;
    }
    $timestamp = $parts['t'] ?? '';
    $signature = $parts['v1'] ?? '';
    if ($timestamp === '' || $signature === '') return false;

    $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $webhookSecret);
    return hash_equals($expected, $signature);
}
