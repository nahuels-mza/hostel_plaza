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
    // Purge oportunista: 1 de cada ~20 guardadas hacemos limpieza de viejas
    if (mt_rand(1, 20) === 1) {
        $all = hp_maybe_purge_old_conversations($all);
    }
    @file_put_contents(
        $cfg['paths']['conversations'],
        json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

/**
 * Devuelve true si la conversación para $phone superó reset_after_hours de inactividad.
 * Usado para arrancar limpio (borrar historial + slots).
 */
function hp_should_reset(string $phone): bool
{
    $cfg = hp_cfg();
    $resetHrs = (int)($cfg['agent']['reset_after_hours'] ?? 6);
    if ($resetHrs <= 0) return false;

    $all = hp_load_conversations();
    $lastSeen = $all[$phone]['last_seen'] ?? null;
    if (!$lastSeen) return false;

    return (time() - strtotime($lastSeen)) > ($resetHrs * 3600);
}

function hp_reset_conversation(string $phone): void
{
    $all = hp_load_conversations();
    if (isset($all[$phone])) {
        hp_log("Auto-reset de {$phone}: borro historial y slots (>reset_after_hours de inactividad)");
        unset($all[$phone]);
        hp_save_conversations($all);
    }
}

function hp_get_history(string $phone): array
{
    $all  = hp_load_conversations();
    $hist = $all[$phone]['messages'] ?? [];
    // Sanitiza cualquier historial guardado que empiece con tool_result huérfano
    // o termine con tool_use sin respuesta (esto puede pasar con datos previos al fix).
    return hp_prune_history($hist, PHP_INT_MAX);
}

/**
 * Elimina conversaciones cuyo último contacto fue hace más de purge_after_days.
 * Se ejecuta oportunísticamente al guardar (1 de cada ~20 escrituras) para no
 * penalizar cada request.
 */
function hp_maybe_purge_old_conversations(array $all): array
{
    $cfg = hp_cfg();
    $days = (int)($cfg['agent']['purge_after_days'] ?? 0);
    if ($days <= 0) return $all;

    $cutoff = time() - ($days * 86400);
    $before = count($all);
    foreach ($all as $phone => $conv) {
        $lastSeen = $conv['last_seen'] ?? null;
        if ($lastSeen && strtotime($lastSeen) < $cutoff) {
            unset($all[$phone]);
        }
    }
    $after = count($all);
    if ($before !== $after) {
        hp_log("Purge: eliminadas " . ($before - $after) . " conversaciones inactivas hace más de {$days} días");
    }
    return $all;
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

    // Conservar solo los últimos N turnos, pero sin romper pares tool_use/tool_result.
    // Claude rechaza cualquier request cuya historia arranque con un `tool_result`
    // porque no encuentra el `tool_use` correspondiente en un mensaje previo.
    $max  = ($cfg['agent']['history_turns'] ?? 8) * 2;
    $hist = hp_prune_history($hist, $max);

    $all[$phone] = array_merge($all[$phone] ?? [], [
        'last_seen' => date('c'),
        'messages'  => $hist,
    ]);
    hp_save_conversations($all);
}

/**
 * Poda el historial de conversación de forma "safe" para la API de Anthropic.
 * Reglas:
 *  1. Si el historial es <= $maxMessages, lo devuelve tal cual.
 *  2. Si hay que cortar, corta al final y luego avanza descartando mensajes hasta
 *     que el primero sea un `user` con contenido de texto plano (no `tool_result`).
 *  3. Descarta también trailing `assistant tool_use` sin `tool_result` posterior
 *     (evita el error inverso).
 */
function hp_prune_history(array $hist, int $maxMessages): array
{
    // 1. Corte por tamaño (si excede el máximo)
    if (count($hist) > $maxMessages) {
        $hist = array_slice($hist, -$maxMessages);
    }

    // 2. SIEMPRE avanzar hasta encontrar un user-text limpio como primer mensaje.
    //    Esto arregla tanto los cortes en pares tool_use/tool_result como cualquier
    //    historial guardado previamente corrupto.
    while (!empty($hist)) {
        $first = $hist[0];
        $role  = $first['role'] ?? '';
        $content = $first['content'] ?? '';

        $isSafeUser = false;
        if ($role === 'user') {
            if (is_string($content) && $content !== '') {
                $isSafeUser = true;
            } elseif (is_array($content)) {
                $hasToolResult = false;
                foreach ($content as $b) {
                    if (is_array($b) && ($b['type'] ?? '') === 'tool_result') {
                        $hasToolResult = true;
                        break;
                    }
                }
                $isSafeUser = !$hasToolResult;
            }
        }
        if ($isSafeUser) break;
        array_shift($hist);
    }

    // 3. Descartar assistant tool_use huérfano al final
    return hp_trim_dangling_tool_use($hist);
}

/**
 * Elimina del final del historial cualquier `assistant` con `tool_use` sin
 * `tool_result` respondiéndolo — Claude también rechaza eso.
 */
function hp_trim_dangling_tool_use(array $hist): array
{
    while (!empty($hist)) {
        $last = end($hist);
        $role = $last['role'] ?? '';
        $content = $last['content'] ?? '';
        if ($role !== 'assistant' || !is_array($content)) break;

        $hasToolUse = false;
        foreach ($content as $b) {
            if (is_array($b) && ($b['type'] ?? '') === 'tool_use') {
                $hasToolUse = true;
                break;
            }
        }
        if (!$hasToolUse) break;

        // Buscar si hay tool_results DESPUÉS de este assistant (no debería si es el last)
        array_pop($hist);
    }
    return $hist;
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
        [
            'name' => 'lookup_booking',
            'description' => 'Busca una reserva existente en bookings.json por su código (formato HP-XXXX o HP-YYMM-XXXXX). Usar cuando el huésped menciona su código de reserva o pregunta por el estado de su reserva. Devuelve fechas, habitación, estado, total y notas — NO devuelve teléfono/email/DNI por privacidad. Si el código no existe, devuelve {found: false}.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'reservation_id' => [
                        'type' => 'string',
                        'description' => 'Código de reserva. Ejemplos válidos: "HP-2604-C34C3", "HP-25CX", "hp-2604-c34c3" (case-insensitive).',
                    ],
                ],
                'required' => ['reservation_id'],
            ],
        ],
        [
            'name' => 'get_weather',
            'description' => 'Consulta el pronóstico del clima en Mendoza ciudad usando Open-Meteo (gratis, sin API key). Si el huésped no mencionó fechas, devuelve solo el clima de HOY. Si tenés fechas de check-in/check-out (por ejemplo en los slots o mencionadas en la conversación), pasalas y devuelve el pronóstico día por día para ese rango (hasta 16 días adelante, más lejano no está disponible). Los códigos meteorológicos son WMO (0=despejado, 1-3=parcialmente nublado a nublado, 45-48=niebla, 51-67=llovizna/lluvia, 71-77=nieve, 80-82=chubascos, 95-99=tormenta).',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'check_in'  => ['type' => 'string', 'description' => 'Fecha inicial YYYY-MM-DD (opcional). Si se omite, devuelve solo el clima de hoy.'],
                    'check_out' => ['type' => 'string', 'description' => 'Fecha final YYYY-MM-DD (opcional, exclusiva; solo se usa si check_in también viene).'],
                ],
                'required' => [],
            ],
        ],
    ];
}

function hp_run_tool(string $name, array $input, string $phone): array
{
    $cfg = hp_cfg();

    if ($name === 'get_weather') {
        $checkIn  = hp_normalize_date($input['check_in']  ?? '');
        $checkOut = hp_normalize_date($input['check_out'] ?? '');

        // Si solo dan check_in, tratamos como un solo día
        // Si no dan nada, hoy
        $today = date('Y-m-d');
        if (!$checkIn) {
            $start = $today;
            $end   = $today;
        } else {
            $start = $checkIn;
            // Open-Meteo espera end inclusive; el checkout del huésped es exclusive.
            // Restamos 1 día al checkout para consultar solo las noches que se hospeda.
            if ($checkOut && $checkOut > $checkIn) {
                $end = date('Y-m-d', strtotime($checkOut . ' -1 day'));
            } else {
                $end = $start;
            }
        }
        // Open-Meteo forecast_days=1..16 desde hoy. No sirve para fechas
        // muy lejanas — validamos.
        if ($start < $today) $start = $today;
        $maxDate = date('Y-m-d', strtotime('+16 days'));
        if ($end > $maxDate) $end = $maxDate;
        if ($start > $maxDate) {
            return [
                'error' => "El pronóstico solo llega hasta {$maxDate}. Para fechas más lejanas no hay datos confiables.",
            ];
        }

        $cache = hp_weather_fetch($start, $end, $cfg['paths']['cache']);
        return $cache;
    }

    if ($name === 'lookup_booking') {
        $raw = (string)($input['reservation_id'] ?? '');
        // Normalizar: strip espacios, uppercase. NO stripear guiones porque los IDs los usan.
        $needle = strtoupper(preg_replace('/\s+/', '', $raw));
        if ($needle === '') {
            return ['found' => false, 'error' => 'Código vacío'];
        }

        $bookingsPath = __DIR__ . '/../bookings.json';
        if (!is_file($bookingsPath)) {
            return ['found' => false, 'error' => 'No hay archivo de reservas disponible'];
        }
        $bookings = json_decode(file_get_contents($bookingsPath), true);
        if (!is_array($bookings)) {
            return ['found' => false, 'error' => 'Archivo de reservas corrupto'];
        }

        $found = null;
        foreach ($bookings as $b) {
            if (strtoupper(trim((string)($b['id'] ?? ''))) === $needle) {
                $found = $b;
                break;
            }
        }
        if (!$found) {
            hp_log("lookup_booking: sin match para {$needle}");
            return [
                'found'  => false,
                'reason' => 'no_match',
                'hint'   => 'El código debe ser exactamente el que vino en el email de confirmación (formato HP-XXXX o HP-YYMM-XXXXX).',
            ];
        }

        // Resolver nombre de habitación
        $rooms = hp_load_rooms(__DIR__ . '/../rooms.json');
        $roomName = 'Habitación';
        foreach ($rooms as $r) {
            if ((string)($r['id'] ?? '') === (string)($found['roomId'] ?? '')) {
                $roomName = (string)($r['name'] ?? $roomName);
                break;
            }
        }

        // Devolver SOLO campos no sensibles. Nunca phone/email/idNumber/nationality/age.
        return [
            'found'          => true,
            'id'             => $found['id']          ?? '',
            'guest_name'     => $found['guestName']   ?? '',
            'room_name'      => $roomName,
            'check_in'       => $found['checkIn']     ?? '',
            'check_out'      => $found['checkOut']    ?? '',
            'guests_count'   => $found['guestsCount'] ?? null,
            'status'         => $found['status']      ?? '',
            'total_price'    => $found['totalPrice']  ?? 0,
            'amount_paid'    => $found['amountPaid']  ?? 0,
            'payment_method' => $found['paymentMethod'] ?? '',
            'notes'          => $found['notes']       ?? '',
        ];
    }

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
Ayudar al huésped en 3 tipos de consultas:

1. **Reservas nuevas** → Si mencionan fechas, llamás `generate_booking_link` y compartís el
   link para que vean disponibilidad y reserven en el sitio.

2. **Reservas existentes** → Si mencionan un código HP-XXXX o preguntan por el estado
   de "su reserva", llamás `lookup_booking` con ese código. Contame la info que sabés
   (fechas, habitación, estado, total pendiente, notas) SIN mencionar teléfono/email/DNI.
   Si quiere modificar o cancelar, derivá al staff (+54 9 2615 37-2767).

3. **Consultas generales** (servicios, políticas, tours) → Respondés usando el FAQ de
   abajo. No inventes datos que no estén ahí.

Reglas generales:
- NUNCA pidas nombre, email, DNI ni datos personales para reservar. Eso se completa en el formulario web.
- NO consultes precios ni disponibilidad por tu cuenta — el link las muestra en tiempo real
  desde BananaDesk.
- Para reservas existentes: NUNCA reveles teléfono, email o DNI aunque el código exista.

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

CONSULTAS SOBRE EL CLIMA:
Si el huésped pregunta por el clima ("¿va a llover?", "how's the weather?", "que tiempo hace"):
- Llamá `get_weather` sin parámetros para el clima de HOY en Mendoza.
- Si ya conocés las fechas de check-in/check-out del huésped (por los slots o la conversación),
  pasalas al tool para dar el pronóstico de esos días concretos.
- Presentá temperatura mínima/máxima y probabilidad de lluvia de forma breve y humana.
- Si el pronóstico predice tormenta o lluvia fuerte, sugerí llevar algo abrigado/paraguas
  con buena onda.

CONSULTAS SOBRE TOURS / EXCURSIONES / EVENTOS:
Estos temas los maneja el equipo de tours del hostel, NO vos. Cuando el huésped
pregunta sobre una actividad específica (wine tour, paragliding, rafting, bike rental,
horse rides, city tour, cualquier excursión, etc.):
- Confirmá brevemente que sí ofrecen ese tipo de actividades.
- Derivalo SIEMPRE al número del equipo de tours: **+54 9 2615 37-2767**
- También podés mencionarles el link {$h['website']}/tourist-events para ver el calendario.
- NO inventes precios, horarios ni disponibilidad de tours — no tenés esa info.

===== FAQ / INFO DEL HOSTEL (usá esto para responder consultas) =====
{$faqText}
======================================================================
PROMPT;
}

/**
 * Consulta el pronóstico del clima en Mendoza ciudad vía Open-Meteo.
 * Gratis, sin API key. Ver https://open-meteo.com/en/docs
 *
 * @param string $startDate Fecha inicial YYYY-MM-DD (>= hoy)
 * @param string $endDate   Fecha final YYYY-MM-DD (<= hoy+16)
 * @param string $cacheDir  Dónde cachear
 * @return array {ok, days: [{date, temp_max, temp_min, precip_probability, weather_code, condition}]}
 */
function hp_weather_fetch(string $startDate, string $endDate, string $cacheDir): array
{
    // Cache: 1h para hoy, 6h para forecasts. Key por (start, end).
    $isToday   = ($startDate === date('Y-m-d') && $endDate === date('Y-m-d'));
    $ttl       = $isToday ? 3600 : 21600; // 1h vs 6h
    $cacheFile = rtrim($cacheDir, '/') . "/weather_{$startDate}_{$endDate}.json";

    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        $c = json_decode(file_get_contents($cacheFile), true);
        if (is_array($c)) { $c['cached'] = true; return $c; }
    }

    // Mendoza ciudad: lat -32.8908, lon -68.8272
    $url = 'https://api.open-meteo.com/v1/forecast'
         . '?latitude=-32.8908&longitude=-68.8272'
         . '&daily=weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max'
         . '&timezone=America/Argentina/Mendoza'
         . '&start_date=' . rawurlencode($startDate)
         . '&end_date='   . rawurlencode($endDate);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json', 'User-Agent: HostelPlaza-Bot/1.0'],
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code < 200 || $code >= 300 || !$body) {
        return ['ok' => false, 'error' => "Open-Meteo HTTP $code", 'cached' => false];
    }
    $raw = json_decode($body, true);
    if (!is_array($raw) || empty($raw['daily'])) {
        return ['ok' => false, 'error' => 'Respuesta no válida de Open-Meteo', 'cached' => false];
    }

    $d = $raw['daily'];
    $days = [];
    for ($i = 0, $n = count($d['time'] ?? []); $i < $n; $i++) {
        $wc = (int)($d['weather_code'][$i] ?? -1);
        $days[] = [
            'date'               => $d['time'][$i],
            'temp_max_c'         => $d['temperature_2m_max'][$i] ?? null,
            'temp_min_c'         => $d['temperature_2m_min'][$i] ?? null,
            'precip_probability' => $d['precipitation_probability_max'][$i] ?? null,
            'weather_code'       => $wc,
            'condition'          => hp_weather_code_to_text($wc),
        ];
    }

    $result = [
        'ok'         => true,
        'location'   => 'Mendoza, Argentina',
        'start_date' => $startDate,
        'end_date'   => $endDate,
        'days'       => $days,
        'source'     => 'Open-Meteo',
        'cached'     => false,
    ];

    @mkdir($cacheDir, 0775, true);
    @file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_UNICODE));
    return $result;
}

/**
 * Traduce un weather code WMO a una descripción breve en inglés.
 * Claude se encarga de traducir al idioma del huésped.
 */
function hp_weather_code_to_text(int $code): string
{
    if ($code === 0)                              return 'Clear sky';
    if ($code === 1)                              return 'Mainly clear';
    if ($code === 2)                              return 'Partly cloudy';
    if ($code === 3)                              return 'Overcast';
    if (in_array($code, [45, 48], true))          return 'Fog';
    if (in_array($code, [51, 53, 55], true))      return 'Drizzle';
    if (in_array($code, [56, 57], true))          return 'Freezing drizzle';
    if (in_array($code, [61, 63, 65], true))      return 'Rain';
    if (in_array($code, [66, 67], true))          return 'Freezing rain';
    if (in_array($code, [71, 73, 75], true))      return 'Snow';
    if ($code === 77)                             return 'Snow grains';
    if (in_array($code, [80, 81, 82], true))      return 'Rain showers';
    if (in_array($code, [85, 86], true))          return 'Snow showers';
    if ($code === 95)                             return 'Thunderstorm';
    if (in_array($code, [96, 99], true))          return 'Thunderstorm with hail';
    return 'Unknown';
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

    // Si pasó mucho tiempo desde la última interacción, arrancamos limpio.
    if (hp_should_reset($from)) {
        hp_reset_conversation($from);
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
