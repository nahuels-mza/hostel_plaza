<?php
/**
 * Cliente mínimo para la API de Anthropic (Claude).
 * Soporta tool use: si Claude pide ejecutar una herramienta, el caller debe
 * resolverla y volver a invocar claude_call() con el tool_result agregado al
 * historial.
 */

/**
 * Normaliza los mensajes antes de mandarlos a Anthropic.
 *
 * Anthropic exige que `tool_use.input` sea un objeto JSON ({}), no un array
 * vacío ([]). Cuando Claude no manda ningún argumento a una tool sin
 * parámetros, la respuesta trae `input: {}`, pero al decodificar con
 * json_decode(..., true) queda como `[]` en PHP y al re-encodearla como parte
 * del historial vuelve como `[]` (array). Forzamos que sea objeto castéandolo
 * a stdClass cuando esté vacío.
 */
function _claude_normalize_messages(array $messages): array
{
    // Mutación por índice — evita bugs sutiles de foreach ... as &$ref.
    foreach ($messages as $i => $msg) {
        $content = $msg['content'] ?? null;
        if (!is_array($content)) continue;
        foreach ($content as $j => $block) {
            if (!is_array($block)) continue;
            if (($block['type'] ?? '') !== 'tool_use') continue;
            $in = $block['input'] ?? null;
            if ($in === null || (is_array($in) && empty($in))) {
                $messages[$i]['content'][$j]['input'] = new stdClass();
            }
        }
    }
    return $messages;
}

function claude_call(array $cfg, array $messages, array $tools = [], string $system = ''): array
{
    $messages = _claude_normalize_messages($messages);
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
