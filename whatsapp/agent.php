<?php
/**
 * Agente de WhatsApp para Hostel Plaza.
 *
 * Entry point principal: hp_handle_message($from, $text)
 *
 *  - Mantiene memoria por número de teléfono en conversations.json
 *  - Le pasa a Claude dos herramientas: check_availability y get_rooms
 *  - Detecta idioma automáticamente (el system prompt instruye a Claude
 *    a responder en el mismo idioma del huésped)
 */

require_once __DIR__ . '/availability.php';
require_once __DIR__ . '/claude_client.php';
require_once __DIR__ . '/whatsapp_client.php';

function hp_cfg(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/config.php';
    }
    return $cfg;
}

function hp_log(string $msg): void
{
    $cfg = hp_cfg();
    $path = $cfg['paths']['log'];
    @mkdir(dirname($path), 0775, true);
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents($path, $line, FILE_APPEND);
}

/* ---------- Memoria de conversación ---------- */

function hp_load_conversations(): array
{
    $cfg = hp_cfg();
    return hp_load_json($cfg['paths']['conversations']);
}

function hp_save_conversations(array $all): void
{
    $cfg = hp_cfg();
    @file_put_contents(
        $cfg['paths']['conversations'],
        json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

function hp_get_history(string $phone): array
{
    $all = hp_load_conversations();
    return $all[$phone]['messages'] ?? [];
}

function hp_append_history(string $phone, array $messages): void
{
    $cfg = hp_cfg();
    $all = hp_load_conversations();
    $hist = $all[$phone]['messages'] ?? [];
    foreach ($messages as $m) $hist[] = $m;

    // Conservar solo los últimos N turnos (user+assistant pairs)
    $max = ($cfg['agent']['history_turns'] ?? 8) * 2;
    if (count($hist) > $max) {
        $hist = array_slice($hist, -$max);
    }

    $all[$phone] = [
        'last_seen' => date('c'),
        'messages'  => $hist,
    ];
    hp_save_conversations($all);
}

/* ---------- Tools que Claude puede llamar ---------- */

function hp_tools_definition(): array
{
    return [
        [
            'name' => 'check_availability',
            'description' => 'Consulta el motor de reservas BananaDesk para obtener qué habitaciones están disponibles entre dos fechas, junto con el precio en ARS para esa estadía. SIEMPRE usar esta herramienta cuando el huésped mencione fechas o pregunte por disponibilidad/precio para una fecha específica. La fuente es en tiempo real.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'check_in' => [
                        'type' => 'string',
                        'description' => 'Fecha de entrada en formato YYYY-MM-DD',
                    ],
                    'check_out' => [
                        'type' => 'string',
                        'description' => 'Fecha de salida en formato YYYY-MM-DD',
                    ],
                ],
                'required' => ['check_in', 'check_out'],
            ],
        ],
        [
            'name' => 'get_rooms',
            'description' => 'Lista los tipos de habitación del hostel con precio de referencia y descripción (consulta BananaDesk con fechas de ejemplo próximas). Usar cuando el huésped pregunte qué tipos de habitación hay o cuál es el precio aproximado, sin mencionar fechas específicas. Si el huésped da fechas, usar `check_availability` en su lugar.',
            'input_schema' => [
                'type' => 'object',
                'properties' => new stdClass(),
            ],
        ],
    ];
}

function hp_run_tool(string $name, array $input): array
{
    $cfg = hp_cfg();

    if ($name === 'check_availability') {
        $checkIn  = hp_normalize_date($input['check_in']  ?? '');
        $checkOut = hp_normalize_date($input['check_out'] ?? '');

        if (!$checkIn || !$checkOut || $checkIn >= $checkOut) {
            return ['error' => 'Fechas inválidas. check_out debe ser posterior a check_in (formato YYYY-MM-DD).'];
        }

        $res = hp_bananadesk_fetch(
            $cfg['bananadesk'],
            $checkIn, $checkOut,
            $cfg['paths']['cache']
        );

        if (!$res['ok']) {
            hp_log('BananaDesk error: ' . ($res['error'] ?? 'unknown'));
            return [
                'error' => 'No pude consultar la disponibilidad en este momento. Sugerí al huésped reintentar en unos minutos o reservar directamente en ' . $cfg['hostel']['booking_url'],
            ];
        }

        // Resumen + detalle. El resumen ayuda a Claude a contestar sin
        // tener que iterar sobre todo el array.
        $available = array_values(array_filter($res['rooms'], fn($r) => $r['is_available']));
        $occupied  = array_values(array_filter($res['rooms'], fn($r) => !$r['is_available']));

        return [
            'check_in'        => $checkIn,
            'check_out'       => $checkOut,
            'nights'          => (int)((strtotime($checkOut) - strtotime($checkIn)) / 86400),
            'currency'        => 'ARS',
            'available_count' => count($available),
            'rooms'           => $res['rooms'],
            'cached'          => $res['cached'],
        ];
    }

    if ($name === 'get_rooms') {
        // Pedimos una noche desde mañana a BananaDesk para obtener la
        // lista oficial de tipos + precio actual.
        $tomorrow      = date('Y-m-d', strtotime('+1 day'));
        $dayAfter      = date('Y-m-d', strtotime('+2 day'));
        $res = hp_bananadesk_fetch(
            $cfg['bananadesk'],
            $tomorrow, $dayAfter,
            $cfg['paths']['cache']
        );
        if (!$res['ok']) {
            return ['error' => 'No pude obtener el catálogo de habitaciones ahora mismo.'];
        }
        // Devolvemos sólo nombre/precio/descripción para que la respuesta
        // sea compacta.
        $light = array_map(function ($r) {
            return [
                'room_type_id' => $r['room_type_id'],
                'name'         => $r['name'],
                'description'  => $r['description'],
                'price_from'   => $r['price'],
                'currency'     => $r['currency'],
                'min_stay'     => $r['min_stay'],
            ];
        }, $res['rooms']);
        return [
            'reference_dates' => "$tomorrow → $dayAfter (1 noche)",
            'currency'        => 'ARS',
            'rooms'           => $light,
        ];
    }

    return ['error' => "Herramienta desconocida: $name"];
}

/* ---------- System prompt ---------- */

function hp_system_prompt(): string
{
    $cfg = hp_cfg();
    $h   = $cfg['hostel'];
    $today = date('Y-m-d');

    return <<<PROMPT
Sos el asistente virtual de {$h['name']}, un hostel ubicado en Argentina.
Sitio web: {$h['website']}
Reservas online: {$h['booking_url']}
Check-in: {$h['check_in']} | Check-out: {$h['check_out']} | Desayuno incluido: {$h['breakfast']}
Hoy es {$today}.

TU TAREA:
- Responder consultas por WhatsApp sobre precios, tipos de habitación y disponibilidad.
- Si el huésped menciona fechas, usá la herramienta `check_availability` con esas fechas.
- Si pregunta por habitaciones, tipos o precios sin fechas, usá `get_rooms`.
- Cuando haya disponibilidad, invitá a reservar mostrando el enlace: {$h['booking_url']}
- Si el huésped quiere conocer servicios extra del hostel (eventos, tour, etc.) sugerí que visite {$h['website']}.

REGLAS DE ESTILO:
- Detectá el idioma del último mensaje del huésped y respondé en ESE mismo idioma (español o inglés).
- Sé breve y cordial. Máximo 4-5 oraciones por respuesta, sin listas extensas.
- Los precios vienen en ARS desde BananaDesk; mostralos con el formato "ARS \$XX.XXX por noche".
- No inventes datos. Si no sabés algo, decilo y sugerí escribir a {$h['website']}.
- Nunca confirmes una reserva: vos solo informás. Para reservar redirigí a {$h['booking_url']}.
- Si la habitación que el huésped quiere está ocupada, ofrecé alternativas disponibles.
- Si la herramienta devuelve `min_stay > 0`, avisá al huésped que hay un mínimo de noches.
PROMPT;
}

/* ---------- Loop principal con Claude ---------- */

function hp_ask_claude(string $phone, string $userText): string
{
    $cfg = hp_cfg();

    $history = hp_get_history($phone);
    $history[] = [
        'role'    => 'user',
        'content' => $userText,
    ];

    $tools  = hp_tools_definition();
    $system = hp_system_prompt();

    $finalText = '';
    $newTurns  = [['role' => 'user', 'content' => $userText]];

    // Hasta 4 iteraciones (tool_use -> tool_result -> ...)
    for ($i = 0; $i < 4; $i++) {
        $res = claude_call($cfg, $history, $tools, $system);
        if (!$res['ok']) {
            hp_log('Claude error: ' . json_encode($res));
            return "Lo siento, tuvimos un problema técnico. Por favor escribinos directamente a {$cfg['hostel']['website']} 🙏";
        }

        $data = $res['data'];
        $stop = $data['stop_reason'] ?? '';
        $contentBlocks = $data['content'] ?? [];

        // Añadir respuesta del assistant al historial
        $history[] = ['role' => 'assistant', 'content' => $contentBlocks];
        $newTurns[] = ['role' => 'assistant', 'content' => $contentBlocks];

        if ($stop !== 'tool_use') {
            foreach ($contentBlocks as $b) {
                if (($b['type'] ?? '') === 'text') {
                    $finalText .= $b['text'];
                }
            }
            break;
        }

        // Ejecutar todas las tool_use de este turno
        $toolResults = [];
        foreach ($contentBlocks as $b) {
            if (($b['type'] ?? '') !== 'tool_use') continue;
            $out = hp_run_tool($b['name'], $b['input'] ?? []);
            hp_log("tool_use {$b['name']} input=" . json_encode($b['input'] ?? []));
            $toolResults[] = [
                'type'        => 'tool_result',
                'tool_use_id' => $b['id'],
                'content'     => json_encode($out, JSON_UNESCAPED_UNICODE),
            ];
        }
        $history[]  = ['role' => 'user', 'content' => $toolResults];
        $newTurns[] = ['role' => 'user', 'content' => $toolResults];
    }

    if ($finalText === '') {
        $finalText = "Disculpá, no pude responder esta consulta. ¿Podés reformularla? 🙏";
    }

    hp_append_history($phone, $newTurns);
    return $finalText;
}

/* ---------- Entry point ---------- */

function hp_handle_message(string $from, string $text, ?string $messageId = null): void
{
    $cfg = hp_cfg();
    hp_log("IN  <{$from}>: {$text}");

    if ($messageId) {
        wa_mark_read($cfg, $messageId);
    }

    $reply = hp_ask_claude($from, $text);
    hp_log("OUT <{$from}>: {$reply}");

    $send = wa_send_text($cfg, $from, $reply);
    if (!$send['ok']) {
        hp_log('WA send error: ' . json_encode($send));
    }

    // Notificación al admin
    if (!empty($cfg['admin']['forward']) && !empty($cfg['admin']['phone'])) {
        $admin = $cfg['admin']['phone'];
        if ($admin !== $from) {
            $note = "📩 Consulta WhatsApp\n"
                  . "De: +{$from}\n"
                  . "Mensaje: {$text}\n\n"
                  . "Respuesta del bot:\n{$reply}";
            wa_send_text($cfg, $admin, $note);
        }
    }
}
