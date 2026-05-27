<?php
/**
 * Disponibilidad y precios de habitaciones.
 *
 * Fuente de verdad: BananaDesk (motor de reservas).
 * Endpoint:
 *   GET https://bananadesk.com/booking-engine/hostel-plaza/room-type-availability
 *       ?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD&room_type=both
 *
 * Las respuestas se cachean en disco con un TTL corto para no pegarle
 * al endpoint en cada mensaje entrante.
 */

function hp_load_json(string $path): array
{
    if (!is_file($path)) return [];
    $raw = file_get_contents($path);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function hp_load_rooms(string $path): array       { return hp_load_json($path); }
function hp_load_site_config(string $path): array { return hp_load_json($path); }

/* ---------- BananaDesk ---------- */

/**
 * Llama al endpoint de disponibilidad de BananaDesk. Resultado cacheado
 * en disco por $ttl segundos.
 *
 * Devuelve un array con la estructura:
 *   [
 *     'ok'        => bool,
 *     'date_from' => 'YYYY-MM-DD',
 *     'date_to'   => 'YYYY-MM-DD',
 *     'rooms'     => [ array de habitaciones normalizado ],
 *     'cached'    => bool,
 *     'error'     => string|null,
 *   ]
 */
function hp_bananadesk_fetch(array $bdCfg, string $dateFrom, string $dateTo, string $cacheDir): array
{
    $baseUrl  = rtrim($bdCfg['base_url'], '/');
    $hostel   = $bdCfg['hostel_slug'];
    $ttl      = (int)($bdCfg['cache_ttl'] ?? 300);
    $timeout  = (int)($bdCfg['timeout']  ?? 15);

    $cacheKey  = "avail_{$dateFrom}_{$dateTo}.json";
    $cachePath = rtrim($cacheDir, '/') . '/' . $cacheKey;

    // Cache hit?
    if (is_file($cachePath) && (time() - filemtime($cachePath)) < $ttl) {
        $cached = json_decode(file_get_contents($cachePath), true);
        if (is_array($cached)) {
            $cached['cached'] = true;
            return $cached;
        }
    }

    $url = sprintf(
        '%s/booking-engine/%s/room-type-availability?date_from=%s&date_to=%s&room_type=both',
        $baseUrl, rawurlencode($hostel),
        rawurlencode($dateFrom), rawurlencode($dateTo)
    );

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'User-Agent: HostelPlaza-WA-Bot/1.0',
        ],
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($code < 200 || $code >= 300 || !$body) {
        return [
            'ok'        => false,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'rooms'     => [],
            'cached'    => false,
            'error'     => $err ?: "HTTP $code",
        ];
    }

    $raw = json_decode($body, true);
    if (!is_array($raw)) {
        return [
            'ok'        => false,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'rooms'     => [],
            'cached'    => false,
            'error'     => 'Respuesta no es JSON válido',
        ];
    }

    $result = [
        'ok'        => true,
        'date_from' => $dateFrom,
        'date_to'   => $dateTo,
        'rooms'     => array_map('hp_bananadesk_normalize_room', $raw),
        'cached'    => false,
        'error'     => null,
    ];

    @mkdir($cacheDir, 0775, true);
    @file_put_contents($cachePath, json_encode($result, JSON_UNESCAPED_UNICODE));

    return $result;
}

/**
 * Normaliza una habitación de BananaDesk para que Claude reciba un objeto
 * compacto y consistente. La descripción suele venir bilingüe separada
 * por "///" - la partimos.
 */
function hp_bananadesk_normalize_room(array $r): array
{
    $desc = (string)($r['description'] ?? '');
    $parts = preg_split('/\s*\/\/\/\s*/u', $desc, 2);
    $descEs = trim($parts[0] ?? '');
    $descEn = trim($parts[1] ?? '');

    $stay = $r['stay_length_restriction'] ?? [];
    $minStay = !empty($stay['has_restriction']) ? (int)($stay['stay_length'] ?? 0) : 0;

    $photos = array_values(array_filter(array_map(
        fn($p) => $p['image'] ?? null,
        $r['photos'] ?? []
    )));

    return [
        'room_type_id'  => $r['room_type_id']  ?? null,
        'name'          => $r['name']          ?? '',
        'description'   => $descEs ?: $desc,
        'description_en'=> $descEn,
        'availability'  => (int)($r['availability'] ?? 0),
        'is_available'  => ((int)($r['availability'] ?? 0)) > 0,
        'price'         => $r['price']         ?? null,
        'currency'      => $r['currency']      ?? 'ARS',
        'min_stay'      => $minStay,
        'photos'        => array_slice($photos, 0, 2),
    ];
}

/**
 * Devuelve los días NO DISPONIBLES de un room_type específico para los
 * próximos $days días contados desde hoy.
 *
 * Estrategia: hace una consulta de 1 noche por cada día (en paralelo con
 * curl_multi) y marca como bloqueado todo día en el que el room_type no
 * aparezca en la respuesta o tenga availability = 0.
 *
 * Se cachea por (room_type_id, days) durante $cacheTtl segundos. La
 * llamada inicial demora unos segundos; las subsiguientes son inmediatas.
 *
 * Retorna:
 *   [
 *     'ok'                => bool,
 *     'room_type_id'      => int,
 *     'days_checked'      => int,
 *     'unavailable_dates' => ['YYYY-MM-DD', ...],
 *     'first_available'   => ['check_in'=>'YYYY-MM-DD','check_out'=>'YYYY-MM-DD'] | null,
 *     'cached'            => bool,
 *   ]
 */
function hp_bananadesk_blocked_dates(array $bdCfg, int $roomTypeId, int $days, string $cacheDir, int $cacheTtl = 1800): array
{
    $cacheFile = rtrim($cacheDir, '/') . "/blocked_{$roomTypeId}_{$days}d.json";
    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
        $c = json_decode(file_get_contents($cacheFile), true);
        if (is_array($c)) { $c['cached'] = true; return $c; }
    }

    $today = new DateTimeImmutable('today');
    $ranges = [];
    $allDates = [];
    for ($i = 0; $i < $days; $i++) {
        $d    = $today->modify("+{$i} days");
        $next = $d->modify('+1 day');
        $from = $d->format('Y-m-d');
        $to   = $next->format('Y-m-d');
        $ranges[$i] = [$from, $to];
        $allDates[] = $from;
    }

    // Construir handles curl en paralelo
    $base  = rtrim($bdCfg['base_url'], '/');
    $slug  = rawurlencode($bdCfg['hostel_slug']);
    $timeout = (int)($bdCfg['timeout'] ?? 15);

    $mh = curl_multi_init();
    $handles = [];
    foreach ($ranges as $idx => [$from, $to]) {
        $url = sprintf(
            '%s/booking-engine/%s/room-type-availability?date_from=%s&date_to=%s&room_type=both',
            $base, $slug, rawurlencode($from), rawurlencode($to)
        );
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'User-Agent: HostelPlaza-Booking/1.0',
            ],
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$idx] = $ch;
    }

    // Ejecutar todas las requests
    do {
        $status = curl_multi_exec($mh, $active);
        if ($active) curl_multi_select($mh, 1.0);
    } while ($active && $status === CURLM_OK);

    $blocked = [];
    $errors  = 0;
    foreach ($handles as $idx => $ch) {
        $body = curl_multi_getcontent($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        if ($code < 200 || $code >= 300) { $errors++; continue; }

        $arr = json_decode((string)$body, true);
        if (!is_array($arr)) { $errors++; continue; }

        $found = false;
        foreach ($arr as $r) {
            if ((int)($r['room_type_id'] ?? 0) === $roomTypeId) {
                $found = true;
                if ((int)($r['availability'] ?? 0) < 1) {
                    $blocked[] = $ranges[$idx][0];
                }
                break;
            }
        }
        // Si el motor no devolvió el room type, lo tratamos como bloqueado
        if (!$found) {
            $blocked[] = $ranges[$idx][0];
        }
    }
    curl_multi_close($mh);

    // Primera fecha disponible y rango sugerido (1 noche por defecto)
    $blockedSet = array_flip($blocked);
    $firstAvail = null;
    foreach ($allDates as $d) {
        if (!isset($blockedSet[$d])) {
            $firstAvail = [
                'check_in'  => $d,
                'check_out' => (new DateTimeImmutable($d))->modify('+1 day')->format('Y-m-d'),
            ];
            break;
        }
    }

    $result = [
        'ok'                => $errors < count($ranges),
        'room_type_id'      => $roomTypeId,
        'days_checked'      => $days,
        'unavailable_dates' => array_values(array_unique($blocked)),
        'first_available'   => $firstAvail,
        'errors'            => $errors,
        'cached'            => false,
    ];

    @mkdir($cacheDir, 0775, true);
    @file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_UNICODE));

    return $result;
}

/**
 * Normaliza una fecha "humana" a Y-m-d. Devuelve null si no se puede.
 */
function hp_normalize_date(string $s): ?string
{
    $s = trim($s);
    if ($s === '') return null;
    try {
        $d = new DateTime($s);
        return $d->format('Y-m-d');
    } catch (Throwable $e) {
        return null;
    }
}
