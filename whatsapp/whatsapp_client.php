<?php
/**
 * Cliente mínimo para enviar mensajes con WhatsApp Cloud API (Meta).
 */

function wa_send_text(array $cfg, string $to, string $message): array
{
    $waCfg   = $cfg['whatsapp'];
    $url     = sprintf(
        'https://graph.facebook.com/%s/%s/messages',
        $waCfg['graph_version'],
        $waCfg['phone_number_id']
    );

    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type'    => 'individual',
        'to'                => $to,
        'type'              => 'text',
        'text'              => [
            'preview_url' => true,
            'body'        => mb_substr($message, 0, 4096),
        ],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $waCfg['access_token'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 20,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    return [
        'ok'   => $code >= 200 && $code < 300,
        'code' => $code,
        'body' => $resp,
        'err'  => $err,
    ];
}

/**
 * Envía un mensaje interactivo con botones de respuesta rápida (hasta 3).
 *
 * @param array  $cfg      Config global.
 * @param string $to       Número destino en E.164 sin '+'.
 * @param string $body     Texto del mensaje (obligatorio).
 * @param array  $buttons  Lista de [{id: 'BTN_ID', title: 'Etiqueta ≤ 20 chars'}, ...].
 *                         Los IDs los usamos internamente para dispatch en el webhook.
 * @param string|null $header  Texto de header opcional (max 60 chars).
 * @param string|null $footer  Texto de footer opcional (max 60 chars).
 */
function wa_send_buttons(array $cfg, string $to, string $body, array $buttons, ?string $header = null, ?string $footer = null): array
{
    $waCfg = $cfg['whatsapp'];
    $url = sprintf(
        'https://graph.facebook.com/%s/%s/messages',
        $waCfg['graph_version'],
        $waCfg['phone_number_id']
    );

    $btnList = [];
    foreach (array_slice($buttons, 0, 3) as $b) {
        $btnList[] = [
            'type'  => 'reply',
            'reply' => [
                'id'    => mb_substr((string)($b['id']    ?? ''), 0, 256),
                'title' => mb_substr((string)($b['title'] ?? ''), 0, 20),
            ],
        ];
    }

    $interactive = [
        'type'   => 'button',
        'body'   => ['text' => mb_substr($body, 0, 1024)],
        'action' => ['buttons' => $btnList],
    ];
    if ($header) $interactive['header'] = ['type' => 'text', 'text' => mb_substr($header, 0, 60)];
    if ($footer) $interactive['footer'] = ['text' => mb_substr($footer, 0, 60)];

    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type'    => 'individual',
        'to'                => $to,
        'type'              => 'interactive',
        'interactive'       => $interactive,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $waCfg['access_token'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 20,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    return [
        'ok'   => $code >= 200 && $code < 300,
        'code' => $code,
        'body' => $resp,
        'err'  => $err,
    ];
}

/**
 * Marca un mensaje entrante como leído (los dos tildes azules).
 */
function wa_mark_read(array $cfg, string $messageId): void
{
    $waCfg = $cfg['whatsapp'];
    $url = sprintf(
        'https://graph.facebook.com/%s/%s/messages',
        $waCfg['graph_version'],
        $waCfg['phone_number_id']
    );

    $payload = [
        'messaging_product' => 'whatsapp',
        'status'            => 'read',
        'message_id'        => $messageId,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $waCfg['access_token'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 10,
    ]);
    curl_exec($ch);
    curl_close($ch);
}
