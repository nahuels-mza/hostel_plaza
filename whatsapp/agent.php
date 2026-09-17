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
    require_once dirname(__DIR__) . '/logger.php';
    hp_write_log('whatsapp', $msg);
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
        [
            'name' => 'save_guest_reservation_data',
            'description' => 'Guarda incrementalmente los datos del huésped para una reserva asistida. Se llama VARIAS veces durante el slot-filling (después de cada respuesta del huésped). Cada parámetro es opcional: pasás solo los que el huésped acaba de dar. NO crea la reserva — solo persiste los datos. Devuelve el estado actual completo de los slots del huésped para que sepas qué falta.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'guest_name' => ['type' => 'string', 'description' => 'Nombre y apellido completos.'],
                    'country'    => ['type' => 'string', 'description' => 'País del huésped.'],
                    'phone'      => ['type' => 'string', 'description' => 'Teléfono OBLIGATORIAMENTE con código de país al inicio (ej: "+54 261 5990326", "+1 415 5551234"). Si el huésped no lo incluyó, deducilo del país indicado y anteponelo. Nunca guardes un teléfono sin código de país.'],
                    'id_type'    => ['type' => 'string', 'description' => 'Tipo de documento: "DNI", "Passport", "Driver License", "National ID", etc.'],
                    'id_number'  => ['type' => 'string', 'description' => 'Número de documento.'],
                    'email'      => ['type' => 'string', 'description' => 'Email del huésped (opcional pero recomendado para confirmación).'],
                ],
                'required' => [],
            ],
        ],
        [
            'name' => 'send_terms_and_conditions',
            'description' => 'Envía las condiciones de reserva completas al huésped como mensaje de WhatsApp. Usar cuando el huésped elige "Quiero leer primero" ante la propuesta de aceptar T&C. Después de esto, preguntá si acepta con dos botones (Sí, acepto / No, cancelar).',
            'input_schema' => [
                'type' => 'object',
                'properties' => new stdClass(),
            ],
        ],
        [
            'name' => 'create_bananadesk_reservation',
            'description' => 'Crea la reserva en BananaDesk (motor real). SOLO usar cuando: (1) tenés todos los slots requeridos: check_in, check_out, guests_count, guest_name, phone, id_type, id_number, room_id; (2) el huésped ya vio el resumen y confirmó; (3) el huésped ya aceptó las T&C (slot terms_accepted). Devuelve {ok:true, reservation_id} o {ok:false, error}. Si falla, sugerí al huésped usar el link web como fallback.',
            'input_schema' => [
                'type' => 'object',
                'properties' => new stdClass(),
            ],
        ],
        [
            'name' => 'list_available_rooms',
            'description' => 'Consulta BananaDesk y devuelve las habitaciones disponibles para las fechas y cantidad de huéspedes indicadas. Devuelve un array con room_type_id, nombre, precio, moneda, disponibilidad, capacidad. Usar en el flujo asistido apenas el huésped confirma que quiere reservar por WhatsApp. Si no pasás fechas ni huéspedes, uso los slots guardados.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'check_in'     => ['type' => 'string',  'description' => 'YYYY-MM-DD (opcional, default = slot check_in)'],
                    'check_out'    => ['type' => 'string',  'description' => 'YYYY-MM-DD (opcional, default = slot check_out)'],
                    'guests_count' => ['type' => 'integer', 'description' => 'Cantidad de huéspedes (opcional, default = slot guests_count)'],
                ],
                'required' => [],
            ],
        ],
        [
            'name' => 'select_room',
            'description' => 'Registra la habitación elegida por el huésped en el flujo asistido. Pasás el room_type_id de BananaDesk (obtenido de list_available_rooms o del botón [BTN:ROOM_<id>]). Persiste room_id (local) y room_type_id para el POST final a BananaDesk.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'room_type_id' => ['type' => 'integer', 'description' => 'ID de BananaDesk del room_type elegido.'],
                ],
                'required' => ['room_type_id'],
            ],
        ],
        [
            'name' => 'send_room_buttons',
            'description' => 'Manda un mensaje interactivo de WhatsApp con hasta 3 botones de elección de habitación. Cada botón dispara [BTN:ROOM_<room_type_id>]. Los títulos de los botones deben ser cortos (≤ 20 chars). Usar después de list_available_rooms para que el huésped elija con un tap.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'body'    => ['type' => 'string', 'description' => 'Texto del mensaje, ej: "Elegí tu habitación:"'],
                    'options' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'room_type_id' => ['type' => 'integer'],
                                'title'        => ['type' => 'string', 'description' => 'Etiqueta del botón, ≤ 20 chars. Ej: "Doble $15k" o "Dorm 6 $8k"'],
                            ],
                            'required' => ['room_type_id', 'title'],
                        ],
                    ],
                ],
                'required' => ['body', 'options'],
            ],
        ],
        [
            'name' => 'send_terms_buttons',
            'description' => 'Manda mensaje interactivo con dos botones para T&C: "Acepto" (ID=TERMS_ACCEPT) / "Leer primero" (ID=TERMS_READ). Usar cuando terminaste de recolectar los datos del huésped y necesitás su aceptación de las condiciones.',
            'input_schema' => [
                'type' => 'object',
                'properties' => new stdClass(),
            ],
        ],
        [
            'name' => 'mark_terms_accepted',
            'description' => 'Marca en los slots que el huésped aceptó las T&C (setea terms_accepted_at). Llamar cuando llegue [BTN:TERMS_ACCEPT], antes de mostrar el resumen final.',
            'input_schema' => [
                'type' => 'object',
                'properties' => new stdClass(),
            ],
        ],
        [
            'name' => 'send_reservation_summary',
            'description' => 'Manda un mensaje interactivo con el resumen completo de la reserva (fechas, noches, habitación, huéspedes, datos del huésped) y dos botones: "Confirmar" (ID=CONFIRM_YES) / "Cancelar" (ID=CONFIRM_NO). Toma los datos de los slots. Devuelve error si falta algún dato requerido. Usar en el paso 4 del flujo asistido, después de que el huésped aceptó T&C.',
            'input_schema' => [
                'type' => 'object',
                'properties' => new stdClass(),
            ],
        ],
        [
            'name' => 'check_paso_cristo_redentor',
            'description' => 'Estima el estado del Paso Cristo Redentor (frontera Argentina-Chile, altura ~3.200 m) según el pronóstico del clima. NO es el estado oficial — es una estimación heurística basada en nieve, viento y visibilidad. USAR cuando el huésped pregunta por: cruzar a Chile, ir a Santiago/Valparaíso/Viña, el paso, la cordillera, esquiar en Portillo, "Los Libertadores" (nombre chileno del paso), etc. Devuelve un juicio ("likely_open" / "at_risk" / "likely_closed") + los datos del pronóstico + un disclaimer para confirmar oficialmente antes de viajar.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'date' => ['type' => 'string', 'description' => 'Fecha YYYY-MM-DD (opcional, hasta hoy+16). Si se omite, usa HOY.'],
                ],
                'required' => [],
            ],
        ],
    ];
}

function hp_run_tool(string $name, array $input, string $phone): array
{
    $cfg = hp_cfg();

    if ($name === 'check_paso_cristo_redentor') {
        $date = hp_normalize_date($input['date'] ?? '');
        $today = date('Y-m-d');
        if (!$date) $date = $today;
        if ($date < $today) $date = $today;
        $maxDate = date('Y-m-d', strtotime('+16 days'));
        if ($date > $maxDate) {
            return [
                'error' => "El pronóstico solo llega hasta {$maxDate}. Para fechas más lejanas, sugerí consultar https://www.andesargentina.com.ar",
            ];
        }
        return hp_paso_cristo_fetch($date, $cfg['paths']['cache']);
    }

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

        // Persistir en slots
        $slots = hp_get_slots($phone);
        $slots['check_in']      = $checkIn;
        $slots['check_out']     = $checkOut;
        $slots['guests_count']  = $guestsCount;
        $slots['proposed_link'] = $url;
        $slots['link_sent_at']  = date('c');
        hp_save_slots($phone, $slots);

        // Después de que Claude mande el mensaje con el link, encolamos un
        // segundo mensaje con botones ofreciendo asistencia. Se envía DESPUÉS
        // de la respuesta principal del bot (ver hp_handle_message).
        hp_schedule_assistance_offer($phone);

        return [
            'booking_link'         => $url,
            'check_in'             => $checkIn,
            'check_out'            => $checkOut,
            'guests_count'         => $guestsCount,
            'nights'               => (int)((strtotime($checkOut) - strtotime($checkIn)) / 86400),
            'assistance_scheduled' => true,
            'note'                 => 'Después de que envíes tu mensaje con el link, el bot le va a mandar automáticamente un segundo mensaje con botones ofreciendo asistencia para reservar. NO menciones esos botones en tu respuesta — solo pasá el link con cordialidad.',
        ];
    }

    if ($name === 'save_guest_reservation_data') {
        $slots = hp_get_slots($phone);
        if (!isset($slots['guest_data']) || !is_array($slots['guest_data'])) {
            $slots['guest_data'] = [];
        }
        $fields = ['guest_name','country','phone','id_type','id_number','email'];
        foreach ($fields as $f) {
            if (isset($input[$f]) && trim((string)$input[$f]) !== '') {
                $slots['guest_data'][$f] = trim((string)$input[$f]);
            }
        }
        hp_save_slots($phone, $slots);

        // Reportar qué falta todavía
        $required = ['guest_name','country','phone','id_type','id_number'];
        $missing  = array_values(array_filter($required, fn($f) => empty($slots['guest_data'][$f])));

        return [
            'ok'          => true,
            'guest_data'  => $slots['guest_data'],
            'missing'     => $missing,
            'complete'    => empty($missing),
            'terms_accepted' => !empty($slots['terms_accepted_at']),
        ];
    }

    if ($name === 'send_terms_and_conditions') {
        $text = hp_terms_as_text();
        $send = wa_send_text($cfg, $phone, $text);
        if (!$send['ok']) {
            hp_log("send_terms FAIL: code={$send['code']} body=" . substr((string)$send['body'], 0, 300));
            return ['ok' => false, 'error' => "No pude enviar las T&C: HTTP {$send['code']}"];
        }
        $slots = hp_get_slots($phone);
        $slots['terms_shown_at'] = date('c');
        hp_save_slots($phone, $slots);
        return ['ok' => true, 'note' => 'Las T&C ya fueron enviadas al huésped como un mensaje aparte. En tu respuesta preguntá si acepta con dos botones.'];
    }

    if ($name === 'create_bananadesk_reservation') {
        $slots = hp_get_slots($phone);
        $gd    = $slots['guest_data'] ?? [];

        // Validaciones defensivas
        $required = ['check_in','check_out','guests_count','room_id'];
        foreach ($required as $r) {
            if (empty($slots[$r])) {
                return ['ok' => false, 'error' => "Falta el slot obligatorio: {$r}."];
            }
        }
        foreach (['guest_name','phone','id_type','id_number'] as $r) {
            if (empty($gd[$r])) {
                return ['ok' => false, 'error' => "Falta el dato del huésped: {$r}."];
            }
        }
        if (empty($slots['terms_accepted_at'])) {
            return ['ok' => false, 'error' => 'El huésped todavía no aceptó las T&C. No podés crear la reserva.'];
        }

        // Mapear a BananaDesk room_type_id
        $map = hp_room_map();
        $localRoomId = (string)$slots['room_id'];
        $bdRoomTypeId = (int)($map[$localRoomId] ?? 0);
        if (!$bdRoomTypeId) {
            return ['ok' => false, 'error' => "El room_id {$localRoomId} no tiene mapeo BananaDesk."];
        }

        // Ver bookingUnit de rooms.json
        $rooms = hp_load_rooms(__DIR__ . '/../rooms.json');
        $bookingUnit = 'room';
        foreach ($rooms as $r) {
            if ((string)$r['id'] === $localRoomId) {
                $bookingUnit = $r['bookingUnit'] ?? 'room';
                break;
            }
        }

        require_once __DIR__ . '/../bananadesk_reserve.php';
        $result = hp_bananadesk_reserve(
            $slots['check_in'],
            $slots['check_out'],
            $bdRoomTypeId,
            $gd['guest_name'],
            $gd['email']  ?? 'sin-email@hostelplaza.com.ar',
            $gd['phone'],
            (int)$slots['guests_count'],
            $bookingUnit
        );

        if (!$result['ok']) {
            hp_log("create_bananadesk_reservation FAIL: " . ($result['error'] ?? 'unknown'));
            $slots['bd_error'] = $result['error'];
            hp_save_slots($phone, $slots);
            return [
                'ok'    => false,
                'error' => $result['error'],
                'hint'  => "Sugerí al huésped que use el link web para completar la reserva a mano: " . ($slots['proposed_link'] ?? $cfg['hostel']['booking_url']),
            ];
        }

        // Éxito: generar un HP-XXXX local, guardar en bookings.json también,
        // para tener trazabilidad como cualquier otra reserva.
        $reservationId = 'HP-' . date('ym') . '-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 5));
        $bookingsFile = __DIR__ . '/../bookings.json';
        $bookings = is_file($bookingsFile) ? (json_decode(file_get_contents($bookingsFile), true) ?: []) : [];
        array_unshift($bookings, [
            'id'          => $reservationId,
            'roomId'      => $localRoomId,
            'checkIn'     => $slots['check_in'],
            'checkOut'    => $slots['check_out'],
            'guestsCount' => (string)$slots['guests_count'],
            'guestName'   => $gd['guest_name'],
            'age'         => '',
            'gender'      => '',
            'nationality' => $gd['country']    ?? '',
            'idType'      => $gd['id_type']    ?? '',
            'idNumber'    => $gd['id_number']  ?? '',
            'phone'       => $gd['phone']      ?? '',
            'email'       => $gd['email']      ?? '',
            'notes'       => 'Reserva creada vía WhatsApp bot',
            'totalPrice'  => 0,
            'amountPaid'  => 0,
            'source'      => 'WhatsApp Bot',
            'status'      => 'Confirmed',
            'bananadesk'  => ['synced' => true, 'response' => $result['response']],
        ]);
        @file_put_contents($bookingsFile, json_encode($bookings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $slots['bd_reservation_id']   = $reservationId;
        $slots['bd_confirmed_at']     = date('c');
        hp_save_slots($phone, $slots);

        return [
            'ok'             => true,
            'reservation_id' => $reservationId,
            'guest_name'     => $gd['guest_name'],
            'check_in'       => $slots['check_in'],
            'check_out'      => $slots['check_out'],
        ];
    }

    if ($name === 'list_available_rooms') {
        $slots = hp_get_slots($phone);
        $ci = hp_normalize_date($input['check_in']  ?? '') ?: ($slots['check_in']  ?? null);
        $co = hp_normalize_date($input['check_out'] ?? '') ?: ($slots['check_out'] ?? null);
        $gc = (int)($input['guests_count'] ?? ($slots['guests_count'] ?? 0));
        if (!$ci || !$co) return ['error' => 'Faltan fechas. Necesito check_in y check_out para consultar disponibilidad.'];
        if ($gc < 1) $gc = 1;

        $bdCfg = $cfg['bananadesk'] ?? [];
        $avail = hp_bananadesk_fetch($bdCfg, $ci, $co, $cfg['paths']['cache']);
        if (empty($avail['ok'])) {
            return ['error' => 'BananaDesk devolvió error: ' . ($avail['error'] ?? 'desconocido')];
        }

        $rooms = [];
        foreach ($avail['rooms'] as $r) {
            if (empty($r['is_available'])) continue;
            $rooms[] = [
                'room_type_id' => (int)$r['room_type_id'],
                'name'         => $r['name'],
                'description'  => mb_substr((string)$r['description'], 0, 200),
                'price'        => $r['price'],
                'currency'     => $r['currency'],
                'availability' => $r['availability'],
                'min_stay'     => $r['min_stay'],
            ];
        }
        usort($rooms, fn($a, $b) => ((float)($a['price'] ?? 1e12)) <=> ((float)($b['price'] ?? 1e12)));

        $nights = (int)((strtotime($co) - strtotime($ci)) / 86400);
        return [
            'ok'           => true,
            'check_in'     => $ci,
            'check_out'    => $co,
            'nights'       => $nights,
            'guests_count' => $gc,
            'rooms'        => $rooms,
            'count'        => count($rooms),
        ];
    }

    if ($name === 'select_room') {
        $bdId = (int)($input['room_type_id'] ?? 0);
        if ($bdId < 1) return ['ok' => false, 'error' => 'room_type_id inválido.'];
        $localId = hp_banana_to_local($bdId);
        if (!$localId) return ['ok' => false, 'error' => "No hay mapeo local para room_type_id {$bdId}."];
        $slots = hp_get_slots($phone);
        $slots['room_id']      = $localId;
        $slots['room_type_id'] = $bdId;
        hp_save_slots($phone, $slots);
        return ['ok' => true, 'room_id' => $localId, 'room_type_id' => $bdId];
    }

    if ($name === 'send_room_buttons') {
        $body    = trim((string)($input['body'] ?? 'Elegí una habitación:'));
        $options = $input['options'] ?? [];
        if (!is_array($options) || count($options) < 1) {
            return ['ok' => false, 'error' => 'Necesito al menos 1 option.'];
        }
        $buttons = [];
        foreach (array_slice($options, 0, 3) as $opt) {
            $rid = (int)($opt['room_type_id'] ?? 0);
            $title = trim((string)($opt['title'] ?? ''));
            if ($rid < 1 || $title === '') continue;
            $buttons[] = ['id' => "ROOM_{$rid}", 'title' => $title];
        }
        if (empty($buttons)) return ['ok' => false, 'error' => 'Ningún option válido.'];
        hp_queue_after_reply($phone, [
            'kind'    => 'buttons',
            'body'    => $body,
            'buttons' => $buttons,
        ]);
        return ['ok' => true, 'note' => 'Botones encolados. Se envían justo después de tu mensaje de texto. Esperá el [BTN:ROOM_<id>] del huésped.'];
    }

    if ($name === 'send_terms_buttons') {
        hp_queue_after_reply($phone, [
            'kind'    => 'buttons',
            'body'    => 'Antes de confirmar tu reserva necesito que aceptes nuestras condiciones. ¿Cómo querés seguir?',
            'buttons' => [
                ['id' => 'TERMS_ACCEPT', 'title' => '✅ Acepto'],
                ['id' => 'TERMS_READ',   'title' => '📄 Leer primero'],
            ],
            'on_ok_slot' => 'terms_buttons_sent_at',
        ]);
        return ['ok' => true, 'note' => 'Botones T&C encolados.'];
    }

    if ($name === 'mark_terms_accepted') {
        $slots = hp_get_slots($phone);
        $slots['terms_accepted_at'] = date('c');
        hp_save_slots($phone, $slots);
        return ['ok' => true, 'terms_accepted_at' => $slots['terms_accepted_at']];
    }

    if ($name === 'send_reservation_summary') {
        $slots = hp_get_slots($phone);
        $gd    = $slots['guest_data'] ?? [];

        $missing = [];
        foreach (['check_in','check_out','guests_count','room_id'] as $f) {
            if (empty($slots[$f])) $missing[] = $f;
        }
        foreach (['guest_name','country','phone','id_type','id_number'] as $f) {
            if (empty($gd[$f])) $missing[] = "guest.{$f}";
        }
        if (!empty($missing)) {
            return ['ok' => false, 'error' => 'Faltan datos: ' . implode(', ', $missing)];
        }

        // Buscar nombre de habitación
        $roomName = $slots['room_id'];
        $rooms = hp_load_rooms(__DIR__ . '/../rooms.json');
        foreach ($rooms as $r) {
            if ((string)($r['id'] ?? '') === (string)$slots['room_id']) {
                $roomName = (string)($r['name'] ?? $roomName);
                break;
            }
        }

        $nights = (int)((strtotime($slots['check_out']) - strtotime($slots['check_in'])) / 86400);
        $summary = "🧾 Resumen de tu reserva\n"
                 . "━━━━━━━━━━━━━━━━━━\n"
                 . "📅 {$slots['check_in']} → {$slots['check_out']} ({$nights} noche" . ($nights === 1 ? '' : 's') . ")\n"
                 . "🛏 {$roomName}\n"
                 . "👥 {$slots['guests_count']} huésped" . ($slots['guests_count'] > 1 ? 'es' : '') . "\n"
                 . "\n"
                 . "👤 {$gd['guest_name']}\n"
                 . "🌍 {$gd['country']}\n"
                 . "📞 {$gd['phone']}\n"
                 . "🪪 {$gd['id_type']} {$gd['id_number']}";
        if (!empty($gd['email'])) $summary .= "\n✉️ {$gd['email']}";
        $summary .= "\n\n¿Confirmás la reserva?";

        hp_queue_after_reply($phone, [
            'kind'    => 'buttons',
            'body'    => $summary,
            'buttons' => [
                ['id' => 'CONFIRM_YES', 'title' => '✅ Confirmar'],
                ['id' => 'CONFIRM_NO',  'title' => 'Cancelar'],
            ],
            'on_ok_slot' => 'summary_sent_at',
        ]);
        return ['ok' => true, 'note' => 'Resumen encolado con botones Confirmar/Cancelar.'];
    }

    return ['error' => "Herramienta desconocida: $name"];
}

/* ---------- System prompt ---------- */

function hp_system_prompt(string $phone): string
{
    $cfg   = hp_cfg();
    $h     = $cfg['hostel'];

    // Fecha y hora en Mendoza (America/Argentina/Mendoza, UTC-3, no DST)
    $mzaTz  = new DateTimeZone('America/Argentina/Mendoza');
    $mzaNow = new DateTime('now', $mzaTz);
    $today   = $mzaNow->format('Y-m-d');
    $todayHr = (int)$mzaNow->format('G');   // 0-23
    $todayHM = $mzaNow->format('H:i');
    $todayDayName = $mzaNow->format('l');    // Monday, Tuesday...

    // Horario de atención presencial del staff (para saber cuándo derivar al teléfono humano)
    $staffOpenHr  = 8;   // 8am
    $staffCloseHr = 22;  // 10pm
    $staffOpenNow = ($todayHr >= $staffOpenHr && $todayHr < $staffCloseHr);
    $staffStatus  = $staffOpenNow
        ? "ABIERTO ahora (horario {$staffOpenHr}:00-{$staffCloseHr}:00)"
        : "CERRADO ahora (horario {$staffOpenHr}:00-{$staffCloseHr}:00, reabre a las {$staffOpenHr}:00)";

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
- Hoy es {$today} ({$todayDayName}), ahora son las {$todayHM} hora Argentina.
- Staff humano: {$staffStatus}.
- Check In a partir de 3pm, guardaequipaje sin costo a partir de 7:30am, check out hasta las 10 am

TU TAREA PRINCIPAL:
Ayudar al huésped en 3 tipos de consultas:

1. **Reservas nuevas** → Si mencionan fechas, llamás `generate_booking_link` y compartís el
   link para que vean disponibilidad y reserven en el sitio.

2. **Reservas existentes** → Si mencionan un código HP-XXXX o preguntan por el estado
   de "su reserva", llamás `lookup_booking` con ese código. Contame la info que sabés
   (fechas, habitación, estado, total pendiente, notas) SIN mencionar teléfono/email/DNI.
   Si quiere modificar o cancelar, derivá al staff SOLO si está ABIERTO ahora
   (ver status arriba). Fuera de horario, decí que el staff responde desde las {$staffOpenHr}:00 hs.

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
   → NO menciones en tu respuesta los botones de asistencia. El sistema los envía
     automáticamente como un mensaje separado justo después del tuyo.
5. Si el huésped dice "somos uno" o parece obvio que va solo (ej: "quiero reservar
   para el 3 al 5"), asumí guests_count=1 y aclará en la respuesta que puede
   cambiarlo desde el link si son más.

FLUJO DE RESERVA ASISTIDA (cuando el huésped acepta reservar por WhatsApp):
Los botones interactivos llegan como mensajes con formato "[BTN:ID] título". Los IDs:
- [BTN:ASSIST_YES]      → el huésped acepta que reserves por él.
- [BTN:ASSIST_NO]       → declina; agradecé y quedá disponible.
- [BTN:ROOM_<id>]       → eligió una habitación (donde <id> es el room_type_id de BananaDesk).
- [BTN:TERMS_ACCEPT]    → acepta las T&C → llamá `mark_terms_accepted`, después `send_reservation_summary`.
- [BTN:TERMS_READ]      → quiere leerlas antes → llamá `send_terms_and_conditions`, luego
                          `send_terms_buttons` de nuevo.
- [BTN:CONFIRM_YES]     → confirma el resumen final → llamá `create_bananadesk_reservation`.
- [BTN:CONFIRM_NO]      → cancela → agradecé y quedá disponible.

PASOS:

Paso 1 — Elegir habitación:
Cuando llegue [BTN:ASSIST_YES], llamá `list_available_rooms` con las fechas guardadas.
Devolvé al huésped un texto breve listando las opciones (con precio y disponibilidad) e
inmediatamente llamá `send_room_buttons` con hasta 3 opciones para que elija con un tap.
Si hay más de 3, listalas todas en el texto y en los botones ponés las 3 más baratas
(el huésped puede responder con el nombre si quiere otra).

Paso 2 — Datos del huésped (3 mensajes agrupados):
Una vez elegida la habitación (llegó [BTN:ROOM_<id>] o el huésped tipeó el nombre),
llamá `select_room` con ese room_type_id y arrancá la recolección:

  Turno A — "¡Buenísimo! ¿Cuál es tu nombre y apellido?"
    → Al recibir, llamá `save_guest_reservation_data(guest_name=...)`.

  Turno B — "Perfecto, [Nombre]. ¿De qué país sos y cuál es tu teléfono?
             (Formato: código de país + número, ej: +54 261 5990326 o +1 415 5551234)"
    → Al recibir, llamá `save_guest_reservation_data(country=..., phone=...)`.
    → El teléfono se guarda SIEMPRE con el código de país incluido (formato E.164
      o similar con "+"). Si el huésped mandó el número sin código, deducilo del
      país que dijo y anteponelo (ej: dijo "Argentina" y "2615990326" → guardá
      "+54 2615990326"). Si no podés deducirlo, volvé a preguntar el código.

  Turno C — "Último dato: ¿tipo y número de documento? Ej: DNI 12345678 o Passport AB1234567."
    → Al recibir, llamá `save_guest_reservation_data(id_type=..., id_number=...)`.

Paso 3 — T&C:
Cuando `save_guest_reservation_data` devuelva `complete: true`, mandá un texto corto tipo
"Antes de confirmar, tenés que aceptar las condiciones de reserva." y llamá
`send_terms_buttons` (envía los dos botones: "Acepto" / "Leer primero").

Paso 4 — Resumen y confirmación:
Cuando llegue [BTN:TERMS_ACCEPT], llamá `mark_terms_accepted` y después
`send_reservation_summary` (arma el resumen con los slots y manda los botones
"Confirmar" / "Cancelar" en un solo mensaje).

Paso 5 — Ejecutar la reserva:
Al llegar [BTN:CONFIRM_YES], llamá `create_bananadesk_reservation`.
- Si `ok:true` → respondé algo como: "¡Listo! Reserva confirmada ✅ Código: HP-XXXX.
  Te llegará confirmación por email."
- Si `ok:false` → sugerí el link web como fallback: "Ups, hubo un problema
  ({error}). Podés terminar la reserva desde el link que te pasé antes:
  {proposed_link}. También podés cambiar de habitación desde ahí."

REGLAS DEL FLUJO ASISTIDO:
- Nunca declares "reservado" sin `create_bananadesk_reservation` con `ok:true`.
- Mostrá SIEMPRE el resumen antes de crear la reserva.
- Si el huésped abandona a mitad de camino, no insistas; agradecé.
- Si corrige un dato en el medio ("no, mi teléfono es otro"), llamá
  `save_guest_reservation_data` con la corrección y seguí donde estabas.
- Si dice [BTN:ASSIST_NO] o dice "no" en cualquier momento, cerrá con cordialidad
  y aclarale que igual puede usar el link web si cambia de idea.


ESTILO:
- Detectá el idioma del último mensaje y respondé en ESE idioma (ES, EN, PT, etc).
- Cordial, breve. 1-2 frases por mensaje. Una pregunta por mensaje.
- No digas "te reservé" — el huésped confirma la reserva en el link.
- Si dan fechas raras (check-out antes que check-in, fechas pasadas), pedí aclaración
  con buena onda.
- Si te preguntan algo que NO está en el FAQ ni es sobre reservas, decí que no sabés
  y derivá a {$h['website']} — mencioná al staff (+54 9 2615 37-2767) SOLO si está
  ABIERTO ahora según status; si no, ofrecé responder mañana o email/web.

CONSULTAS SOBRE EL CLIMA:
Si el huésped pregunta por el clima ("¿va a llover?", "how's the weather?", "que tiempo hace"):
- Llamá `get_weather` sin parámetros para el clima de HOY en Mendoza.
- Si ya conocés las fechas de check-in/check-out del huésped (por los slots o la conversación),
  pasalas al tool para dar el pronóstico de esos días concretos.
- Presentá temperatura mínima/máxima y probabilidad de lluvia de forma breve y humana.
- Si el pronóstico predice tormenta o lluvia fuerte, sugerí llevar algo abrigado/paraguas
  con buena onda.

CONSULTAS SOBRE EL PASO CRISTO REDENTOR (frontera a Chile):
Si el huésped pregunta sobre cruzar a Chile, ir a Santiago/Valparaíso/Portillo,
el paso, "Los Libertadores", la cordillera, esquiar del lado chileno, etc.:
- Llamá `check_paso_cristo_redentor` con la fecha del cruce (si la mencionaron)
  o sin parámetros para HOY.
- Presentá el estado estimado (abierto/riesgo/cerrado) con las razones concretas
  (nevada X cm, viento Y km/h, etc.) de forma breve.
- Solo mencioná el viento si el tool devolvió `wind_max_kmh` con valor (si viene
  `null` es porque es aire quieto y no aporta info).
- SIEMPRE incluí el disclaimer del campo `disclaimer` del resultado. Si el tool
  devolvió `official_url`, usá ESE link (ya está verificado que está vivo);
  no inventes ni hardcodees otros.
- Si `likely_status` = "likely_closed" o "at_risk", sugerí planes B (quedarse
  en Mendoza, wine tour, etc.) con buena onda.

CONSULTAS SOBRE TOURS / EXCURSIONES / EVENTOS:
Estos temas los maneja el equipo de tours del hostel, NO vos. Cuando el huésped
pregunta sobre una actividad específica (wine tour, paragliding, rafting, bike rental,
horse rides, city tour, cualquier excursión, etc.):
- Confirmá brevemente que sí ofrecen ese tipo de actividades.
- Mencioná el link {$h['website']}/tourist-events para ver el calendario.
- Si el staff está ABIERTO ahora, sugerí escribir a **+54 9 2615 37-2767** para info detallada.
- Si el staff está CERRADO, NO sugieras el número. En su lugar, decí "el equipo de tours
  te va a poder atender mañana desde las {$staffOpenHr}:00 hs" o similar. Podés ofrecer que
  visite el sitio para reservar directamente cuando esté disponible.
- NO inventes precios, horarios ni disponibilidad de tours — no tenés esa info.
- Estacionamiento para autos, camionetas sugerir información de cochera, con ubicación en Google Maps (https://maps.app.goo.gl/R1q4GqDYp6vAvz4y8)

REGLA GENERAL SOBRE EL NÚMERO DE STAFF (+54 9 2615 37-2767):
- Solo sugerí ese número cuando el staff esté ABIERTO (revisá el status en DATOS BÁSICOS arriba).
- Fuera del horario, ofrecé alternativas: web ({$h['website']}), decí que responden a la
  mañana, o resolvé lo que puedas vos mismo con el FAQ.
- Esto vale para tours Y para cualquier otra derivación al staff humano.

RESERVAS PARA "HOY" A LA NOCHE (EDGE CASE):
El check-in del hostel es a partir de las {$h['check_in']}. Si el huésped consulta cerca
de la medianoche ("¿tienen lugar para esta noche?", "quiero una cama ya") y la hora
Argentina actual es tarde (después de las 20:00 aprox), tené en cuenta:
- Es muy probable que ya no llegue a tiempo al check-in de HOY.
- Aclarale: "Nuestro check-in es hasta las 23:30. Si llegás después, contá como
  entrada mañana desde las {$h['check_in']}."
- Ofrecé el link con check_in = MAÑANA (calculá la fecha correctamente), y comentá que
  puede cambiarla en el sitio si prefiere otra.
- Si es antes de las 20:00 y insiste con "hoy", generá el link con hoy pero avisá que
  BananaDesk mostrará solo las habitaciones que efectivamente puedan recibirlo hoy.

CONSULTAS SOBRE TRABAJO / VOLUNTARIADO:
Si el huésped pregunta sobre trabajar en el hostel, voluntariado, "work exchange",
"volunteer", intercambio, "puedo trabajar a cambio de alojamiento", etc.:
- Explicá brevemente que las vacantes de voluntariado y work-exchange las manejamos
  exclusivamente a través de la app **Worldpackers** (https://www.worldpackers.com).
- Sugerí que busque "Hostel Plaza Mendoza" en Worldpackers y aplique desde ahí.
- NO derives al staff humano para esto — Worldpackers es el único canal.

===== FAQ / INFO DEL HOSTEL (usá esto para responder consultas) =====
{$faqText}
======================================================================
PROMPT;
}

/**
 * Chequea si una URL está viva (responde 2xx/3xx). Cachea el resultado
 * por $ttl segundos (default 24 h) en whatsapp/cache/url_health.json.
 *
 * Uso pensado: verificar links que sugerimos al huésped, para no
 * mandarle URLs rotas cuando la fuente externa cambió/desapareció.
 */
function hp_url_alive(string $url, string $cacheDir, int $ttl = 86400): bool
{
    static $memCache = [];
    if (isset($memCache[$url])) return $memCache[$url];

    $healthFile = rtrim($cacheDir, '/') . '/url_health.json';
    $all = is_file($healthFile) ? (json_decode(file_get_contents($healthFile), true) ?: []) : [];
    $entry = $all[$url] ?? null;

    if ($entry && (time() - (int)$entry['checked_at']) < $ttl) {
        return $memCache[$url] = (bool)$entry['alive'];
    }

    // Actualizar con HEAD (fallback a GET si HEAD no está permitido)
    $alive = false;
    foreach (['HEAD', 'GET'] as $method) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => ($method === 'HEAD'),
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_HTTPHEADER     => ['User-Agent: HostelPlaza-Bot/1.0'],
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 400) { $alive = true; break; }
        if ($code >= 400 && $code < 500 && $method === 'HEAD') continue; // reintentar con GET
        break;
    }

    $all[$url] = ['alive' => $alive, 'checked_at' => time()];
    @mkdir($cacheDir, 0775, true);
    @file_put_contents($healthFile, json_encode($all, JSON_UNESCAPED_UNICODE));
    return $memCache[$url] = $alive;
}

/**
 * Devuelve la primera URL de la lista que responda OK, o null si ninguna.
 */
function hp_first_alive_url(array $urls, string $cacheDir): ?string
{
    foreach ($urls as $u) {
        if (hp_url_alive($u, $cacheDir)) return $u;
    }
    return null;
}

/**
 * Redondea un valor numérico al múltiplo más cercano ("5.5 km/h" → "5",
 * "62.4 km/h" → "60"). Devuelve int para display humano.
 */
function hp_round_to(float $value, int $step): int
{
    if ($step <= 0) return (int)round($value);
    return (int)(round($value / $step) * $step);
}

/**
 * Wrapper genérico a Open-Meteo con batch por rango + cache por día individual.
 *
 * @param array  $params   ['lat'=>..., 'lon'=>..., 'daily'=>'weather_code,...']
 * @param string $needDate Fecha "central" que el caller quiere
 * @param int    $windowBack Días hacia atrás desde needDate para incluir
 * @param int    $windowFwd  Días hacia adelante desde needDate para incluir
 * @param int    $ttl        TTL del cache por día individual (segundos)
 * @param string $cacheDir   Ruta del cache
 * @param string $cachePrefix Prefijo del nombre de archivo (ej "weather_mza")
 * @return array Datos crudos de Open-Meteo (misma estructura {daily: {...}}) filtrados
 *               a las fechas del rango pedido. cached=true si TODO viene de cache.
 */
function hp_openmeteo_batch(array $params, string $needDate, int $windowBack, int $windowFwd, int $ttl, string $cacheDir, string $cachePrefix): array
{
    $today   = date('Y-m-d');
    $maxDate = date('Y-m-d', strtotime('+16 days'));

    // Ventana efectiva, clampeada a [today, today+16]
    $startWanted = date('Y-m-d', strtotime("{$needDate} -{$windowBack} days"));
    $endWanted   = date('Y-m-d', strtotime("{$needDate} +{$windowFwd} days"));
    if ($startWanted < $today)   $startWanted = $today;
    if ($endWanted   > $maxDate) $endWanted   = $maxDate;

    // Ver qué días ya están en cache
    $daysInWindow = [];
    $d = $startWanted;
    while ($d <= $endWanted) {
        $daysInWindow[] = $d;
        $d = date('Y-m-d', strtotime("{$d} +1 day"));
    }

    $daysFromCache = [];
    $missingDays   = [];
    foreach ($daysInWindow as $day) {
        $file = rtrim($cacheDir, '/') . "/{$cachePrefix}_{$day}.json";
        if (is_file($file) && (time() - filemtime($file)) < $ttl) {
            $c = json_decode(file_get_contents($file), true);
            if (is_array($c)) { $daysFromCache[$day] = $c; continue; }
        }
        $missingDays[] = $day;
    }

    $allCached = empty($missingDays);

    // Si hay días faltantes, hacemos UNA sola request para el rango missing[0]..missing[last]
    if (!empty($missingDays)) {
        $fetchStart = $missingDays[0];
        $fetchEnd   = end($missingDays);
        $url = 'https://api.open-meteo.com/v1/forecast'
             . '?latitude='  . $params['lat']
             . '&longitude=' . $params['lon']
             . '&daily='     . rawurlencode($params['daily'])
             . '&timezone=America/Argentina/Mendoza'
             . '&start_date=' . rawurlencode($fetchStart)
             . '&end_date='   . rawurlencode($fetchEnd);
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
            // Fallback: si el fetch falla y no hay nada en cache, devolver error;
            // si hay algo en cache al menos, usar lo cacheado
            if ($allCached === false && empty($daysFromCache)) {
                return ['ok' => false, 'error' => "Open-Meteo HTTP $code", 'cached' => false];
            }
        } else {
            $raw = json_decode($body, true);
            if (is_array($raw) && !empty($raw['daily']['time'])) {
                @mkdir($cacheDir, 0775, true);
                $times = $raw['daily']['time'];
                foreach ($times as $i => $day) {
                    $perDay = ['date' => $day];
                    foreach ($raw['daily'] as $k => $arr) {
                        if ($k === 'time') continue;
                        $perDay[$k] = $arr[$i] ?? null;
                    }
                    $file = rtrim($cacheDir, '/') . "/{$cachePrefix}_{$day}.json";
                    @file_put_contents($file, json_encode($perDay, JSON_UNESCAPED_UNICODE));
                    $daysFromCache[$day] = $perDay;
                }
            }
        }
    }

    // Devolver solo los días de la ventana pedida, en orden
    $out = [];
    foreach ($daysInWindow as $day) {
        if (isset($daysFromCache[$day])) $out[] = $daysFromCache[$day];
    }
    return ['ok' => !empty($out), 'days' => $out, 'cached' => $allCached];
}

/**
 * Consulta el pronóstico del clima en Mendoza ciudad vía Open-Meteo.
 * Ahora usa batch fetch con cache por día individual: si el bot itera
 * sobre fechas cercanas, hits cache el 99% de las veces.
 *
 * Ventana: pide siempre 6 días alrededor de la fecha central, para
 * cubrir "¿y mañana?", "¿y pasado?" sin nuevas API calls.
 *
 * @param string $startDate Fecha inicial YYYY-MM-DD (>= hoy)
 * @param string $endDate   Fecha final YYYY-MM-DD (<= hoy+16)
 * @param string $cacheDir  Dónde cachear
 */
function hp_weather_fetch(string $startDate, string $endDate, string $cacheDir): array
{
    $isTodayOnly = ($startDate === date('Y-m-d') && $endDate === date('Y-m-d'));
    $ttl = $isTodayOnly ? 3600 : 21600;

    // Ventana adaptativa: si es solo hoy, cachear también los próximos 5 días
    // para las siguientes consultas del bot; si es un rango, cubrir el rango + 2 días extra a cada lado.
    $windowBack = 0;
    $windowFwd  = (int)((strtotime($endDate) - strtotime($startDate)) / 86400) + 2;

    $batch = hp_openmeteo_batch([
        'lat'   => '-32.8908',
        'lon'   => '-68.8272',
        'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max',
    ], $startDate, $windowBack, $windowFwd, $ttl, $cacheDir, 'weather_mza');

    if (!$batch['ok']) {
        return ['ok' => false, 'error' => $batch['error'] ?? 'sin datos', 'cached' => false];
    }

    // Filtrar solo el rango pedido
    $days = [];
    foreach ($batch['days'] as $d) {
        if ($d['date'] < $startDate || $d['date'] > $endDate) continue;
        $wc = (int)($d['weather_code'] ?? -1);
        $days[] = [
            'date'               => $d['date'],
            'temp_max_c'         => $d['temperature_2m_max'] ?? null,
            'temp_min_c'         => $d['temperature_2m_min'] ?? null,
            'precip_probability' => $d['precipitation_probability_max'] ?? null,
            'weather_code'       => $wc,
            'condition'          => hp_weather_code_to_text($wc),
        ];
    }

    return [
        'ok'         => true,
        'location'   => 'Mendoza, Argentina',
        'start_date' => $startDate,
        'end_date'   => $endDate,
        'days'       => $days,
        'source'     => 'Open-Meteo',
        'cached'     => $batch['cached'],
    ];
}

/**
 * Estima el estado del Paso Cristo Redentor (frontera AR-CL, ~3.200 m)
 * usando el pronóstico de Open-Meteo en las coordenadas del paso.
 *
 * NO es fuente oficial — es una estimación heurística. El estado oficial
 * lo publica Gendarmería Nacional / Vialidad Argentina.
 *
 * Heurística:
 *   - Nevada > 5 cm/día         → likely_closed
 *   - Viento max > 70 km/h       → likely_closed (voladura de nieve)
 *   - Nevada 1-5 cm o viento 50-70 → at_risk
 *   - Weather code de tormenta (95-99) → at_risk
 *   - Niebla densa (45,48)       → at_risk
 *   - Nada de lo anterior        → likely_open
 */
function hp_paso_cristo_fetch(string $date, string $cacheDir): array
{
    // Coordenadas del paso (Cumbre): 32°49'38"S 70°05'32"O ≈ -32.8272, -70.0921
    // Usamos batch (±2 días) para cachear la ventana y no repetir API calls
    // cuando el bot pregunta por días vecinos.
    $ttl = 21600; // 6h por día

    $batch = hp_openmeteo_batch([
        'lat'   => '-32.8272',
        'lon'   => '-70.0921',
        'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,snowfall_sum,wind_speed_10m_max,precipitation_sum',
    ], $date, 2, 3, $ttl, $cacheDir, 'paso_cristo');

    if (!$batch['ok']) {
        return ['ok' => false, 'error' => $batch['error'] ?? 'sin datos', 'cached' => false];
    }

    // Buscar el día pedido dentro del batch
    $found = null;
    foreach ($batch['days'] as $d) {
        if ($d['date'] === $date) { $found = $d; break; }
    }
    if (!$found) {
        return ['ok' => false, 'error' => "sin datos para {$date}", 'cached' => $batch['cached']];
    }

    $wc     = (int)($found['weather_code']         ?? -1);
    $tMax   = (float)($found['temperature_2m_max'] ?? 0);
    $tMin   = (float)($found['temperature_2m_min'] ?? 0);
    $snow   = (float)($found['snowfall_sum']       ?? 0);   // cm
    $windRaw = (float)($found['wind_speed_10m_max'] ?? 0);  // km/h
    $precip = (float)($found['precipitation_sum']  ?? 0);   // mm

    // Redondear viento a múltiplos de 5 para no mostrar falsa precisión
    // (el modelo digital de elevación es aproximado a 3.200 m)
    $wind = hp_round_to($windRaw, 5);
    $snowInt = (int)round($snow); // nevada en cm entera

    $condition = hp_weather_code_to_text($wc);

    // Heurística de estado (basada en $windRaw, no en $wind redondeado)
    $status  = 'likely_open';
    $reasons = [];
    if ($snow >= 5) {
        $status = 'likely_closed';
        $reasons[] = "nevada fuerte ({$snowInt} cm)";
    } elseif ($snow >= 1) {
        $status = 'at_risk';
        $reasons[] = "nevada leve ({$snowInt} cm)";
    }
    if ($windRaw >= 70) {
        $status = 'likely_closed';
        $reasons[] = "vientos fuertes ({$wind} km/h)";
    } elseif ($windRaw >= 50 && $status === 'likely_open') {
        $status = 'at_risk';
        $reasons[] = "vientos moderados ({$wind} km/h)";
    }
    if (in_array($wc, [95, 96, 99], true) && $status === 'likely_open') {
        $status = 'at_risk';
        $reasons[] = "tormenta pronosticada";
    }
    if (in_array($wc, [45, 48], true) && $status === 'likely_open') {
        $status = 'at_risk';
        $reasons[] = "niebla densa";
    }
    if (empty($reasons)) $reasons[] = 'sin condiciones adversas destacadas';

    // Fuente oficial: intentamos primero gubernamental, después el aggregator.
    // Health-check cacheado 24h, así no hacemos HEAD por cada mensaje.
    $officialUrls = [
        'https://www.argentina.gob.ar/seguridad/pasosinternacionales/detalle/ruta/29/Sistema-Cristo-Redentor',
        'https://www.andesargentina.com.ar/pasos-cordilleranos',
    ];
    $officialUrl = hp_first_alive_url($officialUrls, $cacheDir);

    if ($officialUrl) {
        $disclaimer = "ESTIMACIÓN según pronóstico, NO estado oficial. Confirmar antes de viajar en {$officialUrl} o en las redes de Vialidad Nacional.";
    } else {
        // Fuentes offline hoy — sugerimos solo canales sociales
        $disclaimer = "ESTIMACIÓN según pronóstico, NO estado oficial. Confirmar antes de viajar en las redes de Vialidad Nacional (@VialidadArg) o Gendarmería Nacional.";
    }

    return [
        'ok'            => true,
        'location'      => 'Paso Cristo Redentor (~3.200 m, frontera AR-CL)',
        'date'          => $date,
        'temp_max_c'    => $tMax,
        'temp_min_c'    => $tMin,
        'snowfall_cm'   => $snowInt,
        // Solo mostramos viento cuando es relevante (>= 20 km/h). Debajo de eso
        // es aire quieto en términos prácticos y solo suma ruido.
        'wind_max_kmh'  => ($wind >= 20 ? $wind : null),
        'precip_mm'     => $precip,
        'weather_code'  => $wc,
        'condition'     => $condition,
        'likely_status' => $status,
        'reasoning'     => implode(' + ', $reasons),
        'disclaimer'    => $disclaimer,
        'official_url'  => $officialUrl,
        'source'        => 'Open-Meteo',
        'cached'        => $batch['cached'],
    ];
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

/**
 * Carga terms.json y lo formatea como texto WhatsApp-friendly (bullets, sin HTML).
 */
function hp_terms_as_text(): string
{
    $path = __DIR__ . '/../terms.json';
    if (!is_file($path)) return '(Condiciones no disponibles)';
    $data = json_decode(file_get_contents($path), true);
    if (!is_array($data) || empty($data['sections'])) return '(Condiciones vacías)';

    $out = ["*Booking Conditions · Hostel Plaza*", ''];
    foreach ($data['sections'] as $sec) {
        $out[] = '*' . ($sec['title'] ?? '') . '*';
        foreach ($sec['items'] ?? [] as $item) {
            $out[] = "• " . trim($item);
        }
        $out[] = '';
    }
    return implode("\n", $out);
}

/**
 * Marca que después de responderle al huésped hay que mandarle un segundo
 * mensaje con botones ofreciendo asistencia para reservar.
 * hp_handle_message() lo lee y lo dispara post-respuesta.
 */
function hp_schedule_assistance_offer(string $phone): void
{
    hp_queue_after_reply($phone, ['kind' => 'assistance_offer']);
}

/**
 * Cola genérica de mensajes que se envían DESPUÉS de la respuesta de texto
 * principal del bot. Necesaria para que WhatsApp los muestre en orden y no
 * los agrupe en la misma burbuja.
 *
 * Cada item tiene la forma:
 *   ['kind' => 'assistance_offer']
 *   ['kind' => 'buttons', 'body' => ..., 'buttons' => [...], 'header' => ?, 'footer' => ?, 'on_ok_slot' => ?]
 *   ['kind' => 'text', 'body' => ...]
 *
 * $phone === '__flush__' → drenamos la cola.
 */
function hp_queue_after_reply(string $phone, ?array $item = null): void
{
    static $queue = [];
    if ($phone === '__flush__') {
        $q = $queue; $queue = [];
        foreach ($q as $entry) {
            [$to, $it] = $entry;
            hp_dispatch_queued_item($to, $it);
        }
        return;
    }
    if ($item === null) return;
    $queue[] = [$phone, $item];
}

function hp_dispatch_queued_item(string $phone, array $item): void
{
    $cfg = hp_cfg();
    $kind = $item['kind'] ?? '';
    if ($kind === 'assistance_offer') {
        hp_do_send_assistance_offer($phone);
        return;
    }
    if ($kind === 'buttons') {
        $send = wa_send_buttons(
            $cfg, $phone,
            (string)($item['body'] ?? ''),
            $item['buttons'] ?? [],
            $item['header'] ?? null,
            $item['footer'] ?? null
        );
        if (!$send['ok']) {
            hp_log("queued buttons FAIL: code={$send['code']} body=" . substr((string)$send['body'], 0, 300));
        } elseif (!empty($item['on_ok_slot'])) {
            $slots = hp_get_slots($phone);
            $slots[$item['on_ok_slot']] = date('c');
            hp_save_slots($phone, $slots);
        }
        return;
    }
    if ($kind === 'text') {
        wa_send_text($cfg, $phone, (string)($item['body'] ?? ''));
        return;
    }
    hp_log("dispatch_queued_item: kind desconocido '{$kind}'");
}

/**
 * Manda el mensaje interactivo "¿querés que reserve por vos?" con botones.
 */
function hp_do_send_assistance_offer(string $phone): void
{
    $cfg = hp_cfg();
    $body = "¿Querés que reserve por vos desde acá? Te pido unos datos y lo cierro yo. 😊";
    $send = wa_send_buttons(
        $cfg, $phone, $body,
        [
            ['id' => 'ASSIST_YES', 'title' => '✅ Sí, reservá'],
            ['id' => 'ASSIST_NO',  'title' => 'No, gracias'],
        ],
        null,
        'También podés usar el link 👆'
    );
    if (!$send['ok']) {
        hp_log("assistance_offer FAIL a +{$phone}: code={$send['code']} body=" . substr((string)$send['body'], 0, 300));
    } else {
        hp_log("assistance_offer OK a +{$phone}");
        $slots = hp_get_slots($phone);
        $slots['assistance_offered_at'] = date('c');
        hp_save_slots($phone, $slots);
    }
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

    $lastStopReason = '';
    $lastBlockKinds = '';
    $iterationsUsed = 0;
    for ($i = 0; $i < 8; $i++) {
        $iterationsUsed = $i + 1;
        $res = claude_call($cfg, $history, $tools, $system);
        if (!$res['ok']) {
            hp_log('Claude error: ' . json_encode($res));
            return "Lo siento, tuvimos un problema técnico. Por favor escribinos a +54 9 2615 37-2767 🙏";
        }

        $data = $res['data'];
        $stop = $data['stop_reason'] ?? '';
        $contentBlocks = $data['content'] ?? [];
        $lastStopReason = $stop;
        $lastBlockKinds = implode(',', array_map(fn($b) => ($b['type'] ?? '?'), $contentBlocks));
        $usage = $data['usage'] ?? [];
        hp_log("claude turn={$iterationsUsed} stop={$stop} blocks=[{$lastBlockKinds}] in_tok=" . ($usage['input_tokens'] ?? '?') . " out_tok=" . ($usage['output_tokens'] ?? '?'));

        $history[]  = ['role' => 'assistant', 'content' => $contentBlocks];
        $newTurns[] = ['role' => 'assistant', 'content' => $contentBlocks];

        // Acumulamos texto SIEMPRE que venga (incluso si stop=tool_use, puede
        // haber texto antes de la tool)
        foreach ($contentBlocks as $b) {
            if (($b['type'] ?? '') === 'text') {
                $finalText .= $b['text'];
            }
        }

        if ($stop !== 'tool_use') {
            break;
        }

        $toolResults = [];
        foreach ($contentBlocks as $b) {
            if (($b['type'] ?? '') !== 'tool_use') continue;
            $out = hp_run_tool($b['name'], $b['input'] ?? [], $phone);
            hp_log("tool_use {$b['name']} input=" . json_encode($b['input'] ?? []) . " | out=" . substr(json_encode($out), 0, 500));
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
        hp_log("EMPTY_REPLY: last stop={$lastStopReason} blocks=[{$lastBlockKinds}] turns={$iterationsUsed}");
        // Fallback: si terminó bien pero sin texto, es porque Claude ejecutó
        // solo una tool que ya mandó un mensaje (buttons/T&C/summary). En ese
        // caso NO respondemos con el mensaje de disculpa — devolvemos vacío
        // y en hp_handle_message evitamos wa_send_text si es empty.
        if ($lastStopReason === 'end_turn' && strpos($lastBlockKinds, 'tool_use') !== false) {
            return '';  // señal: no mandar mensaje de texto adicional
        }
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
    hp_log("OUT <{$from}>: " . ($reply === '' ? '(sin texto; solo mensajes de tools)' : $reply));

    // Solo mandamos texto si Claude produjo texto. Si el turno consistió
    // solo en tools (que ya encolaron sus propios mensajes interactivos)
    // no metemos un mensaje de disculpa vacío.
    if ($reply !== '') {
        $send = wa_send_text($cfg, $from, $reply);
        if (!$send['ok']) {
            hp_log('WA send error: ' . json_encode($send));
        }
    }

    // Después del mensaje principal: flusheamos la cola (assistance_offer,
    // buttons, T&C, resumen final...).
    hp_queue_after_reply('__flush__');

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
            $adminSend = wa_send_text($cfg, $admin, $note);
            if (!$adminSend['ok']) {
                hp_log("Admin forward FAIL a +{$admin}: code={$adminSend['code']} body=" . substr((string)$adminSend['body'], 0, 500));
            } else {
                // Extraemos message_id y wa_id para trazabilidad. Si wa_id != admin,
                // Meta puede no estar entregando aunque haya devuelto 200.
                $body = json_decode((string)$adminSend['body'], true);
                $msgId = $body['messages'][0]['id'] ?? '?';
                $waId  = $body['contacts'][0]['wa_id'] ?? '?';
                $match = ($waId === $admin) ? 'wa_id-match' : "wa_id-MISMATCH(got:{$waId})";
                hp_log("Admin forward OK a +{$admin} · msgid={$msgId} · {$match}");
            }
        } else {
            hp_log("Admin forward SKIP: admin ({$admin}) === from ({$from})");
        }
    } else {
        hp_log("Admin forward SKIP: forward=" . ($cfg['admin']['forward'] ? '1' : '0') . " phone=" . ($cfg['admin']['phone'] ?: 'empty'));
    }
}
