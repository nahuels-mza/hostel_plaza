<?php
/**
 * Cliente mínimo para la API de Anthropic (Claude).
 * Soporta tool use: si Claude pide ejecutar una herramienta, el caller debe
 * resolverla y volver a invocar claude_call() con el tool_result agregado al
 * historial.
 */

function claude_call(array $cfg, array $messages, array $tools = [], string $system = ''): array
{
    $body = [
        'model'      => $cfg['claude']['model'],
        'max_tokens' => $cfg['claude']['max_tokens'],
        'messages'   => $messages,
    ];
    if ($system !== '') {
        $body['system'] = $system;
    }
    if (!empty($tools)) {
        $body['tools'] = $tools;
    }

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . $cfg['claude']['api_key'],
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 60,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($code < 200 || $code >= 300) {
        return [
            'ok'  => false,
            'code' => $code,
            'err' => $err ?: $resp,
        ];
    }

    return [
        'ok'   => true,
        'data' => json_decode($resp, true),
    ];
}
