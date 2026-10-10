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

/**
 * @param string|array $system  Pasar string (sin cache) o array:
 *   ['static' => '... prefijo cacheable ...', 'dynamic' => '... parte que cambia ...']
 *   El prefijo lleva cache_control: ephemeral; el sufijo dinámico no.
 */
function claude_call(array $cfg, array $messages, array $tools = [], $system = ''): array
{
    $messages = _claude_normalize_messages($messages);
    $body = [
        'model'      => $cfg['claude']['model'],
        'max_tokens' => $cfg['claude']['max_tokens'],
        'messages'   => $messages,
    ];

    // ---- SYSTEM con prompt caching opcional ----
    if (is_array($system)) {
        $staticPart  = (string)($system['static']  ?? '');
        $dynamicPart = (string)($system['dynamic'] ?? '');
        $parts = [];
        if ($staticPart !== '') {
            $parts[] = [
                'type'          => 'text',
                'text'          => $staticPart,
                'cache_control' => ['type' => 'ephemeral'],
            ];
        }
        if ($dynamicPart !== '') {
            $parts[] = ['type' => 'text', 'text' => $dynamicPart];
        }
        if (!empty($parts)) $body['system'] = $parts;
    } elseif (is_string($system) && $system !== '') {
        $body['system'] = $system;
    }

    // ---- TOOLS: cacheamos el bloque entero marcando la última tool ----
    if (!empty($tools)) {
        $lastIdx = count($tools) - 1;
        $tools[$lastIdx]['cache_control'] = ['type' => 'ephemeral'];
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
