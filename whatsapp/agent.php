<?php
/**
 * Agente de WhatsApp para Hostel Plaza — versión mínima.
 *
 * Objetivo: pedir SOLO check-in + check-out, y mandar un link a book.php
 * step 2 (grilla de habitaciones con disponibilidad real). El propio wizard
 * se encarga del resto.
 *
 * Tools:
 *   - generate_booking_link(check_in, check_out) → URL a book.php
 *
 * Entry point: hp_handle_message($from, $text)
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

/* ---------- room_mapping reverse lookup ---------- */

function hp_room_map(): array
{
    static $map = null;
    if ($map === null) {
        $path = __DIR__ . '/../room_mapping.json';
        $raw = is_file($path) ? json_decode(file_get_contents($path), true) : [];
        $map = is_array($raw) ? $raw : [];
    }
    return $map;
}

/** room_type_id (BananaDesk) → local room id (rooms.json) */
function hp_banana_to_local(int $bananaTypeId): ?string
{
    foreach (hp_room_map() as $local => $banana) {
        if (str_starts_with((string)$local, '_')) continue;
        if ((int)$banana === $bananaTypeId) return (string)$local;
    }
    return null;
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

function hp_get_slots(string $phone): array
{
    $all = hp_load_conversations();
    return $all[$phone]['slots'] ?? [];
}

function hp_save_slots(string $phone, array $slots): void
{
    $all = hp_load_conversations();
    $all[$phone]['slots']     = $slots;
    $all[$phone]['last_seen'] = date('c');
    hp_save_conversations($all);
}

function hp_append_history(string $phone, array $messages): void
{
    $cfg = hp_cfg();
    $all = hp_load_conversations();
    $hist = $all[$phone]['messages'] ?? [];
    foreach ($messages as $m) $hist[] = $m;

    // Conservar solo los últimos N turnos
    $max = ($cfg['agent']['history_turns'] ?? 8) * 2;
    if (count($hist) > $max) {
        $hist = array_slice($hist, -$max);
    }

    $all[$phone] = array_merge($all[$phone] ?? [], [
        'last_seen' => date('c'),
        'messages'  => $hist,
    ]);
    hp_save_conversations($all);
}

/* ---------- Tools que Claude puede llamar ---------- */

function hp_tools_definition(): array
{
    return [
        [
            'name' => 'generate_booking_link',
            'description' => 'Arma el link de Hostel Plaza para ver disponibilidad y reservar entre dos fechas. USAR apenas tengas check_in y check_out válidos. El link lleva al huésped al paso 2 del wizard, donde ve TODAS las habitaciones disponibles para esas fechas con precio en vivo (no es necesario que el bot consulte disponibilidad por su cuenta).',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'check_in'  => ['type' => 'string', 'description' => 'Fecha de entrada en formato YYYY-MM-DD'],
                    'check_out' => ['type' => 'string', 'description' => 'Fecha de salida en formato YYYY-MM-DD'],
                ],
                'required' => ['check_in', 'check_out'],
            ],
        ],
    ];
}

function hp_run_tool(string $name, array $input, string $phone): array
{
    $cfg = hp_cfg();

    if ($name === 'generate_booking_link') {
        $checkIn  = hp_normalize_date($input['check_in']  ?? '');
        $checkOut = hp_normalize_date($input['check_out'] ?? '');

        if (!$checkIn || !$checkOut) {
            return ['error' => 'Fechas inválidas. Necesito ambas en formato YYYY-MM-DD.'];
        }
        if ($checkIn >= $checkOut) {
            return ['error' => 'check_out debe ser posterior a check_in.'];
        }
        $today = date('Y-m-d');
        if ($checkIn < $today) {
            return ['error' => "check_in no puede ser anterior a hoy ({$today})."];
        }

        $base = $cfg['hostel']['booking_url'];
        $url  = $base
              . '?check_in='  . rawurlencode($checkIn)
              . '&check_out=' . rawurlencode($checkOut);

        // Persistir en slots para que el admin lo vea
        $slots = hp_get_slots($phone);
        $slots['check_in']      = $checkIn;
        $slots['check_out']     = $checkOut;
        $slots['proposed_link'] = $url;
        hp_save_slots($phone, $slots);

        return [
            'booking_link' => $url,
            'check_in'     => $checkIn,
            'check_out'    => $checkOut,
            'nights'       => (int)((strtotime($checkOut) - strtotime($checkIn)) / 86400),
        ];
    }

    return ['error' => "Herramienta desconocida: $name"];
}

/* ---------- System prompt ---------- */

function hp_system_prompt(string $phone): string
{
    $cfg   = hp_cfg();
    $h     = $cfg['hostel'];
    $today = date('Y-m-d');

    $slots = hp_get_slots($phone);
    $slotsDump = empty($slots)
        ? '(ninguno todavía)'
        : json_encode($slots, JSON_UNESCAPED_UNICODE);

    return <<<PROMPT
Sos el asistente virtual de {$h['name']}, un hostel en Mendoza, Argentina. Atendés por WhatsApp.

DATOS BÁSICOS:
- Sitio web: {$h['website']}
- Check-in: {$h['check_in']} | Check-out: {$h['check_out']} | Desayuno incluido: {$h['breakfast']}
- Tu WhatsApp (el del hostel): +54 9 2615 37-2767
- Hoy es {$today}.

TU ÚNICA TAREA:
Conseguir las dos fechas del huésped (check-in y check-out) y mandarle un link
para que vea disponibilidad y reserve directamente en el sitio.
- NO consultes precios ni disponibilidad por tu cuenta — el link lleva al wizard
  que muestra todo en vivo desde BananaDesk.
- NO pidas nombre, email, DNI, ni datos personales. Eso se completa en el formulario.

ESTADO ACTUAL de este huésped (memoria persistente entre mensajes):
{$slotsDump}

FLUJO IDEAL:
1. Saludá brevemente y preguntá las fechas si no las tenés.
2. Si el huésped manda una sola fecha, pedile la otra.
3. Apenas tengas las dos fechas, llamá a `generate_booking_link` y compartí el link
   con un texto cordial: "¡Listo! Seguí este link para ver la disponibilidad y reservar:
   <URL>". Adaptá el wording al idioma del huésped.
4. Si después tiene más preguntas (servicios, ubicación, etc.), respondé corto y derivá
   a {$h['website']}.

ESTILO:
- Detectá el idioma del último mensaje y respondé en ESE idioma (ES, EN, PT, etc).
- Cordial, breve. 1-2 frases por mensaje. Una pregunta por mensaje.
- No inventes precios ni disponibilidad. No digas "te reservé" — el huésped reserva en el link.
- Si el huésped da fechas que no tienen sentido (check-out antes que check-in, fechas
  pasadas), pedile aclaración con buena onda.
PROMPT;
}

/* ---------- Loop principal con Claude ---------- */

function hp_ask_claude(string $phone, string $userText): string
{
    $cfg = hp_cfg();

    $history = hp_get_history($phone);
    $history[] = ['role' => 'user', 'content' => $userText];

    $tools  = hp_tools_definition();
    $system = hp_system_prompt($phone);

    $finalText = '';
    $newTurns  = [['role' => 'user', 'content' => $userText]];

    for ($i = 0; $i < 5; $i++) {
        $res = claude_call($cfg, $history, $tools, $system);
        if (!$res['ok']) {
            hp_log('Claude error: ' . json_encode($res));
            return "Lo siento, tuvimos un problema técnico. Por favor escribinos a {$cfg['hostel']['website']} 🙏";
        }

        $data = $res['data'];
        $stop = $data['stop_reason'] ?? '';
        $contentBlocks = $data['content'] ?? [];

        $history[]  = ['role' => 'assistant', 'content' => $contentBlocks];
        $newTurns[] = ['role' => 'assistant', 'content' => $contentBlocks];

        if ($stop !== 'tool_use') {
            foreach ($contentBlocks as $b) {
                if (($b['type'] ?? '') === 'text') {
                    $finalText .= $b['text'];
                }
            }
            break;
        }

        $toolResults = [];
        foreach ($contentBlocks as $b) {
            if (($b['type'] ?? '') !== 'tool_use') continue;
            $out = hp_run_tool($b['name'], $b['input'] ?? [], $phone);
            hp_log("tool_use {$b['name']} input=" . json_encode($b['input'] ?? []) . " | out_size=" . strlen(json_encode($out)));
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

    // Notificación al admin con un resumen de los slots conocidos
    if (!empty($cfg['admin']['forward']) && !empty($cfg['admin']['phone'])) {
        $admin = $cfg['admin']['phone'];
        if ($admin !== $from) {
            $slots = hp_get_slots($from);
            $bits  = [];
            if (!empty($slots['guest_name']))     $bits[] = "Nombre: {$slots['guest_name']}";
            if (!empty($slots['guest_email']))    $bits[] = "Email: {$slots['guest_email']}";
            if (!empty($slots['check_in']))       $bits[] = "Check-in: {$slots['check_in']}";
            if (!empty($slots['check_out']))      $bits[] = "Check-out: {$slots['check_out']}";
            if (!empty($slots['room_id']))        $bits[] = "Room ID: {$slots['room_id']}";
            if (!empty($slots['proposed_link']))  $bits[] = "Link enviado: {$slots['proposed_link']}";
            $slotSummary = empty($bits) ? '' : ("\n\n📋 Datos:\n— " . implode("\n— ", $bits));

            $note = "📩 WhatsApp · +{$from}\n"
                  . "Huésped: {$text}\n\n"
                  . "Bot: {$reply}"
                  . $slotSummary;
            wa_send_text($cfg, $admin, $note);
        }
    }
}
