<?php
/**
 * Agente de WhatsApp para Hostel Plaza — versión mínima.
 *
 * Objetivo: pedir SOLO check-in + check-out y la cantidad de huespedes, y mandar un link a book.php
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
            'description' => 'Arma el link de Hostel Plaza para ver disponibilidad y reservar. USAR apenas tengas check_in, check_out y guests_count. El link lleva al huésped al paso 2 del wizard, donde ve las habitaciones disponibles para esas fechas y cantidad de huéspedes con precio en vivo (no es necesario que el bot consulte disponibilidad por su cuenta).',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'check_in'     => ['type' => 'string',  'description' => 'Fecha de entrada en formato YYYY-MM-DD'],
                    'check_out'    => ['type' => 'string',  'description' => 'Fecha de salida en formato YYYY-MM-DD'],
                    'guests_count' => ['type' => 'integer', 'description' => 'Cantidad de huéspedes (mínimo 1). Si el huésped no lo dijo, asumí 1 y en la respuesta aclará "asumí 1 persona, cambialo en el link si son más".'],
                ],
                'required' => ['check_in', 'check_out', 'guests_count'],
            ],
        ],
    ];
}

function hp_run_tool(string $name, array $input, string $phone): array
{
    $cfg = hp_cfg();

    if ($name === 'generate_booking_link') {
        $checkIn     = hp_normalize_date($input['check_in']  ?? '');
        $checkOut    = hp_normalize_date($input['check_out'] ?? '');
        $guestsCount = max(1, (int)($input['guests_count'] ?? 1));

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
              . '?check_in='     . rawurlencode($checkIn)
              . '&check_out='    . rawurlencode($checkOut)
              . '&guests_count=' . rawurlencode((string)$guestsCount);

        // Persistir en slots para que el admin lo vea
        $slots = hp_get_slots($phone);
        $slots['check_in']      = $checkIn;
        $slots['check_out']     = $checkOut;
        $slots['guests_count']  = $guestsCount;
        $slots['proposed_link'] = $url;
        hp_save_slots($phone, $slots);

        return [
            'booking_link' => $url,
            'check_in'     => $checkIn,
            'check_out'    => $checkOut,
            'guests_count' => $guestsCount,
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

    $faqText = hp_faq_as_text();

    return <<<PROMPT
Sos el asistente virtual de {$h['name']}, un hostel en Mendoza, Argentina. Atendés por WhatsApp.

DATOS BÁSICOS:
- Sitio web: {$h['website']}
- Check-in: {$h['check_in']} | Check-out: {$h['check_out']} | Desayuno incluido: {$h['breakfast']}
- Tu WhatsApp (el del hostel): +54 9 261 259-2729
- Hoy es {$today}.

TU TAREA PRINCIPAL:
Ayudar al huésped a reservar. Para eso:
- Si mencionan fechas: llamás `generate_booking_link` y compartís el link para que vean
  disponibilidad y reserven directamente en el sitio.
- Si tienen otras consultas (servicios, ubicación, políticas, etc.): las respondés vos
  usando la info del FAQ de abajo. No inventes datos que no estén ahí.
- NUNCA pidas nombre, email, DNI ni datos personales. Eso se completa en el formulario web.
- NO consultes precios ni disponibilidad por tu cuenta — la disponibilidad la muestra el link
  en tiempo real desde BananaDesk.

ESTADO ACTUAL del huésped (memoria persistente entre mensajes):
{$slotsDump}

DATOS QUE NECESITÁS PARA EL LINK:
1. check_in  (fecha de entrada)
2. check_out (fecha de salida)
3. guests_count (cantidad de huéspedes, entero >= 1)

FLUJO PARA RESERVAS:
1. Saludá brevemente y preguntá las fechas si no las tenés.
2. Si mandan solo una fecha, pedí la otra.
3. Si ya tenés las dos fechas pero no sabés la cantidad de huéspedes, preguntá
   "¿para cuántas personas?" (una sola pregunta, breve).
4. Con las tres cosas (fechas + huéspedes) llamá `generate_booking_link` y compartí el link
   con un texto cordial adaptado al idioma del huésped, tipo "¡Listo! Seguí este link
   para ver la disponibilidad y reservar: <URL>".
5. Si el huésped dice "somos uno" o parece obvio que va solo (ej: "quiero reservar
   para el 3 al 5"), asumí guests_count=1 y aclará en la respuesta que puede
   cambiarlo desde el link si son más.


ESTILO:
- Detectá el idioma del último mensaje y respondé en ESE idioma (ES, EN, PT, etc).
- Cordial, breve. 1-2 frases por mensaje. Una pregunta por mensaje.
- No digas "te reservé" — el huésped confirma la reserva en el link.
- Si dan fechas raras (check-out antes que check-in, fechas pasadas), pedí aclaración
  con buena onda.
- Si te preguntan algo que NO está en el FAQ ni es sobre reservas, decí que no sabés
  y derivá a {$h['website']} o al staff en el mismo número.

===== FAQ / INFO DEL HOSTEL (usá esto para responder consultas) =====
{$faqText}
======================================================================
PROMPT;
}

/**
 * Carga hostel_faq.json y lo formatea como texto plano para el system prompt.
 */
function hp_faq_as_text(): string
{
    $path = __DIR__ . '/../hostel_faq.json';
    if (!is_file($path)) return '(FAQ no disponible)';
    $data = json_decode(file_get_contents($path), true);
    if (!is_array($data) || empty($data['categories'])) return '(FAQ vacío)';

    $out = [];
    foreach ($data['categories'] as $cat) {
        $out[] = '## ' . ($cat['title'] ?? '');
        foreach ($cat['items'] ?? [] as $item) {
            $q = trim($item['q'] ?? '');
            $a = trim($item['a'] ?? '');
            if ($q === '' || $a === '') continue;
            $out[] = "Q: {$q}";
            $out[] = "A: {$a}";
            $out[] = '';
        }
    }
    return implode("\n", $out);
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
            if (!empty($slots['guests_count']))   $bits[] = "Huéspedes: {$slots['guests_count']}";
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
